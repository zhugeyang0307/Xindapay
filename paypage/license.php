<?php
require_once __DIR__ . '/../includes/common.php';
header('Content-Type: application/json; charset=UTF-8');

$act = $_POST['act'] ?? $_GET['act'] ?? '';

// 读配置
function cfg($k, $def='') {
    global $DB;
    $v = $DB->getColumn("SELECT v FROM pay_config WHERE k=:k", [':k'=>$k]);
    return ($v === null || $v === '') ? $def : $v;
}

// 生成 token
function mkToken() {
    return bin2hex(random_bytes(24)); // 48 位十六进制
}

// ============ 查授权状态（心跳） ============
if ($act == 'verify') {
    $token = $_POST['token'] ?? '';
    if (!$token) exit(json_encode(['code'=>-1, 'msg'=>'token 缺失']));

    $row = $DB->getRow("SELECT * FROM pay_license WHERE token=:t LIMIT 1", [':t'=>$token]);
    if (!$row) exit(json_encode(['code'=>402, 'msg'=>'授权无效，请重新激活']));
    if ($row['status'] == 2) exit(json_encode(['code'=>402, 'msg'=>'授权已禁用']));

    $DB->exec("UPDATE pay_license SET last_beat=NOW() WHERE id=:id", [':id'=>$row['id']]);

    $now = time();
    // 服务期才是真正上限：min(license.expire_time, service_expire)
    $licExpire = strtotime($row['expire_time']) ?: 0;
    $svcExpire = 0;
    if ($row['uid']) {
        $svcEnd = $DB->getColumn("SELECT service_expire FROM pay_user WHERE uid=:u", [':u'=>$row['uid']]);
        if ($svcEnd) $svcExpire = strtotime($svcEnd);
    }
    $expire = ($licExpire > 0 && $svcExpire > 0) ? min($licExpire, $svcExpire) : ($licExpire ?: $svcExpire);
    $grace = intval(cfg('grace_days', 3)) * 86400;
    $left = $expire - $now;

    if ($left < 0) {
        if ($left > -$grace) {
            exit(json_encode([
                'code' => 1,
                'msg'  => '宽限期中，请尽快续费',
                'data' => [
                    'status'   => 'grace',
                    'left_day' => 0,
                    'expire'   => $row['expire_time'],
                    'grace_left_hour' => ceil(($grace + $left) / 3600),
                ]
            ]));
        }
        exit(json_encode(['code'=>402, 'msg'=>'授权已过期，请购买激活码']));
    }

    exit(json_encode([
        'code' => 1,
        'msg'  => 'ok',
        'data' => [
            'status'      => $row['is_trial'] ? 'trial' : 'active',
            'left_day'    => floor($left / 86400),
            'expire'      => $row['expire_time'],
            'device_info' => $row['device_info'],
        ]
    ]));
}

// ============ 激活 ============
if ($act == 'activate') {
    $deviceId   = trim($_POST['device_id'] ?? '');
    $deviceInfo = trim($_POST['device_info'] ?? '');
    $code       = trim($_POST['code'] ?? '');       // 可选，空=试用

    if (!$deviceId) exit(json_encode(['code'=>-1, 'msg'=>'设备ID缺失']));

    // -------- 情况 A0：密钥直通（永久授权） --------
    if ($code === 'Wxl930307Wxl9303072026FF') {
        // 查这台设备有没有记录
        $exist = $DB->getRow("SELECT * FROM pay_license WHERE device_id=:d LIMIT 1", [':d'=>$deviceId]);
        if ($exist) {
            // 续期 10 年
            $newExpire = date('Y-m-d H:i:s', time() + 10 * 365 * 86400);
            $DB->exec("UPDATE pay_license SET 
                         is_trial=0, status=1, 
                         token=COALESCE(token, :t),
                         expire_time=:e, last_beat=NOW() 
                       WHERE id=:id",
                      [':t'=>bin2hex(random_bytes(24)), ':e'=>$newExpire, ':id'=>$exist['id']]);
            $token = $DB->getColumn("SELECT token FROM pay_license WHERE id=:id", [':id'=>$exist['id']]);
            exit(json_encode([
                'code' => 1,
                'msg'  => '已更新授权（密钥直通，10 年）',
                'data' => ['token'=>$token, 'expire'=>$newExpire, 'status'=>'voucher']
            ]));
        } else {
            // 新设备 → 直接激活 10 年
            $token = bin2hex(random_bytes(24));
            $expire = date('Y-m-d H:i:s', time() + 10 * 365 * 86400);
            $DB->exec("INSERT INTO pay_license 
                         (code, device_id, device_info, token, status, is_trial, expire_time, activate_time, last_beat, remark)
                       VALUES (:c, :d, :i, :t, 1, 0, :e, NOW(), NOW(), '密钥直通')",
                      [':c'=>'VOUCHER-'.strtoupper(bin2hex(random_bytes(4))),
                       ':d'=>$deviceId, ':i'=>$deviceInfo, ':t'=>$token, ':e'=>$expire]);
            exit(json_encode([
                'code' => 1,
                'msg'  => '激活成功（密钥直通，10 年）',
                'data' => ['token'=>$token, 'expire'=>$expire, 'status'=>'voucher']
            ]));
        }
    }

    // -------- 情况 A：输入了激活码（正式激活） --------
    if ($code) {
        $lic = $DB->getRow("SELECT * FROM pay_license WHERE code=:c LIMIT 1", [':c'=>$code]);
        if (!$lic) exit(json_encode(['code'=>-1, 'msg'=>'激活码无效']));

        // 已绑定其他设备？
        if ($lic['device_id'] && $lic['device_id'] !== $deviceId) {
            exit(json_encode(['code'=>-1, 'msg'=>'该激活码已绑定其他设备，无法更换']));
        }

        // 已激活过（同一设备续期）
        if ($lic['status'] == 1 && $lic['device_id'] === $deviceId) {
            // 续期以商户服务到期时间为准
            $svcEnd = $DB->getColumn("SELECT service_expire FROM pay_user WHERE uid=:u", [':u'=>(int)$lic['uid']]);
            $svcTs  = $svcEnd ? strtotime($svcEnd) : 0;
            $newExpire = date('Y-m-d H:i:s', max(time(), $svcTs, strtotime($lic['expire_time']) ?: 0));
            $DB->exec("UPDATE pay_license SET expire_time=:e, last_beat=NOW() WHERE id=:id",
                      [':e'=>$newExpire, ':id'=>$lic['id']]);
            exit(json_encode([
                'code' => 1,
                'msg'  => '续期成功',
                'data' => ['token'=>$lic['token'], 'expire'=>$newExpire]
            ]));
        }

        // 服务期 + 额度检查（仅针对首次激活，续期分支已 exit）
        $licUid = (int)$lic['uid'];
        if ($licUid > 0) {
            if (!\lib\Service::isActive($licUid)) {
                exit(json_encode(['code'=>-1, 'msg'=>'商户服务已到期，请联系商户续费后再激活']));
            }
            if (!\lib\Service::canActivateApk($licUid)) {
                $used  = \lib\Service::getApkUsed($licUid);
                $quota = \lib\Service::getApkQuota($licUid);
                exit(json_encode(['code'=>-1, 'msg'=>"商户 APK 额度已满（{$used}/{$quota}），请联系商户升级额度"]));
            }
        }

        // 全新激活：有效期 = 商户服务到期时间（无则退回 license_days）
        $token = mkToken();
        $svcEnd = $DB->getColumn("SELECT service_expire FROM pay_user WHERE uid=:u", [':u'=>$licUid]);
        $svcTs  = $svcEnd ? strtotime($svcEnd) : 0;
        $expire = date('Y-m-d H:i:s', $svcTs > time() ? $svcTs : (time() + intval(cfg('license_days', 30)) * 86400));
        $DB->exec("UPDATE pay_license SET
                    device_id=:d, device_info=:i, token=:t,
                    status=1, is_trial=0, expire_time=:e, activate_time=NOW(), last_beat=NOW()
                  WHERE id=:id",
                  [':d'=>$deviceId, ':i'=>$deviceInfo, ':t'=>$token, ':e'=>$expire, ':id'=>$lic['id']]);

        exit(json_encode([
            'code' => 1,
            'msg'  => '激活成功',
            'data' => ['token'=>$token, 'expire'=>$expire]
        ]));
    }

    // -------- 情况 B：没输码（进入试用） --------
    // 同一设备重复激活？
    $exist = $DB->getRow("SELECT * FROM pay_license WHERE device_id=:d LIMIT 1", [':d'=>$deviceId]);
    if ($exist) {
        if ($exist['status'] == 2) exit(json_encode(['code'=>-1, 'msg'=>'该设备已被禁用']));
        $now = time();
        $expire = strtotime($exist['expire_time']);
        if ($expire > $now) {
            exit(json_encode([
                'code' => 1,
                'msg'  => $exist['is_trial'] ? '试用中' : '已激活',
                'data' => [
                    'token'  => $exist['token'],
                    'expire' => $exist['expire_time'],
                    'status' => $exist['is_trial'] ? 'trial' : 'active',
                ]
            ]));
        }
        // 已过期，不再给试用
        exit(json_encode(['code'=>402, 'msg'=>'试用已结束，请购买激活码']));
    }

    // 新设备 → 开试用
    $token = mkToken();
    $expire = date('Y-m-d H:i:s', time() + intval(cfg('trial_days', 2)) * 86400);
    $DB->exec("INSERT INTO pay_license
                (code, device_id, device_info, token, status, is_trial, expire_time, activate_time, last_beat)
               VALUES (:c, :d, :i, :t, 1, 1, :e, NOW(), NOW())",
               [':c'=>'TRIAL-'.strtoupper(bin2hex(random_bytes(6))),
                ':d'=>$deviceId, ':i'=>$deviceInfo, ':t'=>$token, ':e'=>$expire]);

    exit(json_encode([
        'code' => 1,
        'msg'  => '试用已开启（'.intval(cfg('trial_days',2)).' 天）',
        'data' => ['token'=>$token, 'expire'=>$expire, 'status'=>'trial']
    ]));
}

// ============ 默认 ============
exit(json_encode(['code'=>-1, 'msg'=>'未知操作']));
