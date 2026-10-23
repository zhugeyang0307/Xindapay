<?php
namespace lib;

/**
 * 商户服务期 + APK 额度管理
 * 服务期：付费期内 或 试用期内 均为有效
 * 额度：默认为 sys_apk_quota（默认 5），续费时按 extra_devices 增加
 */
class Service
{
    /** 是否有效（付费期内 或 试用期内） */
    public static function isActive($uid)
    {
        global $DB;
        $row = $DB->getRow("SELECT service_expire, service_trial_end FROM pay_user WHERE uid=:u", [':u'=>(int)$uid]);
        if (!$row) return false;
        $now = time();
        $e = $row['service_expire'] ? strtotime($row['service_expire']) : 0;
        $t = $row['service_trial_end'] ? strtotime($row['service_trial_end']) : 0;
        return ($e > $now) || ($t > $now);
    }

    /** 综合到期时间（付费到期与试用结束取最大） */
    public static function getExpire($uid)
    {
        global $DB;
        $row = $DB->getRow("SELECT service_expire, service_trial_end FROM pay_user WHERE uid=:u", [':u'=>(int)$uid]);
        if (!$row) return null;
        $e = $row['service_expire'] ? strtotime($row['service_expire']) : 0;
        $t = $row['service_trial_end'] ? strtotime($row['service_trial_end']) : 0;
        $m = max($e, $t);
        return $m > 0 ? date('Y-m-d H:i:s', $m) : null;
    }

    /** 状态：none / trial / active / trial_expired / expired */
    public static function getStatus($uid)
    {
        global $DB;
        $row = $DB->getRow("SELECT service_expire, service_trial_end FROM pay_user WHERE uid=:u", [':u'=>(int)$uid]);
        if (!$row) return 'none';
        $now = time();
        $e = $row['service_expire'] ? strtotime($row['service_expire']) : 0;
        $t = $row['service_trial_end'] ? strtotime($row['service_trial_end']) : 0;
        if ($e > $now) return 'active';
        if ($t > $now) return 'trial';
        if ($e > 0) return 'expired';
        if ($t > 0) return 'trial_expired';
        return 'none';
    }

    /** 初始化试用（注册时调用，已初始化过不重复） */
    public static function initTrial($uid)
    {
        global $DB, $conf;
        $uid = (int)$uid;
        $row = $DB->getRow("SELECT service_expire, service_trial_end FROM pay_user WHERE uid=:u", [':u'=>$uid]);
        if (!$row) return false;
        if (!empty($row['service_expire']) || !empty($row['service_trial_end'])) return false;

        $freeUntil = trim((string)($conf['sys_free_until'] ?? ''));
        if ($freeUntil !== '') {
            $ts = strtotime($freeUntil . ' 23:59:59');
            if (!$ts || $ts <= time()) return false;
            $end = date('Y-m-d H:i:s', $ts);
        } else {
            $days = (int)($conf['sys_trial_days'] ?? 2);
            if ($days <= 0) return false;
            $end = date('Y-m-d H:i:s', time() + $days * 86400);
        }

        $res = $DB->exec("UPDATE pay_user SET service_trial_end=:e, apk_quota=3 WHERE uid=:u", [':e'=>$end, ':u'=>$uid]);
        
        // ===== 自动生成 3 个体验授权码 =====
        // 检查是否已经生成过（防止重复触发）
        $hasLic = $DB->getColumn("SELECT COUNT(*) FROM pay_license WHERE uid=:u AND is_trial=1", [':u'=>$uid]);
        if ($res && $hasLic == 0) {
            $expire_time = '2026-11-01 23:59:59'; // 统一截止到活动结束
            for ($i = 0; $i < 3; $i++) {
                // 生成唯一激活码
                $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
                $seg = function() use ($chars) {
                    $str = '';
                    for ($j = 0; $j < 4; $j++) $str .= $chars[random_int(0, strlen($chars)-1)];
                    return $str;
                };
                $code = 'XD-' . $seg() . '-' . $seg() . '-' . $seg();
                
                $DB->exec("INSERT INTO pay_license (code, uid, status, is_trial, expire_time, remark, addtime) 
                           VALUES (:c, :u, 0, 1, :e, '注册赠送体验码', NOW())", 
                           [':c'=>$code, ':u'=>$uid, ':e'=>$expire_time]);
            }
        }
        
        return $res;
    }

    /** 续费：从 当前付费到期/试用结束/now 三者最大值起算 +months*30 天 */
    public static function extend($uid, $months, $extraDevices = 0)
    {
        global $DB;
        $uid = (int)$uid;
        $months = max(1, (int)$months);
        $extraDevices = max(0, (int)$extraDevices);
        $row = $DB->getRow("SELECT service_expire, service_trial_end, apk_quota FROM pay_user WHERE uid=:u", [':u'=>$uid]);
        if (!$row) return false;
        $base = max(
            time(),
            $row['service_expire'] ? strtotime($row['service_expire']) : 0,
            $row['service_trial_end'] ? strtotime($row['service_trial_end']) : 0
        );
        $newExpire = date('Y-m-d H:i:s', $base + $months * 30 * 86400);
        $newQuota  = (int)$row['apk_quota'] + $extraDevices;
        return $DB->exec(
            "UPDATE pay_user SET service_expire=:e, apk_quota=:q WHERE uid=:u",
            [':e'=>$newExpire, ':q'=>$newQuota, ':u'=>$uid]
        );
    }

    /** 已激活 APK 设备数（device_id 非空且未禁用） */
    public static function getApkUsed($uid)
    {
        global $DB;
        return (int)$DB->getColumn(
            "SELECT COUNT(*) FROM pay_license WHERE uid=:u AND device_id IS NOT NULL AND device_id<>'' AND status<>2",
            [':u'=>(int)$uid]
        );
    }

    /** 额度 */
    public static function getApkQuota($uid)
    {
        global $DB;
        return (int)$DB->getColumn("SELECT apk_quota FROM pay_user WHERE uid=:u", [':u'=>(int)$uid]);
    }

    /** 是否还能激活新设备 */
    public static function canActivateApk($uid)
    {
        return self::getApkUsed($uid) < self::getApkQuota($uid);
    }
}