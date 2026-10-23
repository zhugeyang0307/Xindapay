<?php
require_once __DIR__ . '/../config.php';

/**
 * 权限系统
 * L4 Owner      - ADMIN_ID（你）
 * L3 SuperAdmin - pay_tgadmin.level=1
 * L2 Admin      - pay_tgadmin.level=2
 * L1 Merchant   - pay_tgbind 有绑定
 * L0 User       - 默认
 */
class Auth {
    const ROLE_OWNER      = 'owner';
    const ROLE_SUPERADMIN = 'superadmin';
    const ROLE_ADMIN      = 'admin';
    const ROLE_MERCHANT   = 'merchant';
    const ROLE_USER       = 'user';

    // 权限表
    private static $perms = [
        // 基础
        'basic'               => ['owner','superadmin','admin','merchant','user'],

        // 商户自查（仅自己）
        'self.view'           => ['owner','superadmin','admin','merchant'],
        'self.orders'         => ['owner','superadmin','admin','merchant'],
        'self.qrcode'         => ['owner','superadmin','admin','merchant'],
        'self.bill'           => ['owner','superadmin','admin','merchant'],

        // 商户管理（管理员级）
        'merchant.view'       => ['owner','superadmin','admin'],
        'merchant.list'       => ['owner','superadmin','admin'],
        'merchant.unbind'     => ['owner','superadmin','admin'],
        'merchant.ban'        => ['owner','superadmin','admin'],
        'merchant.unban'      => ['owner','superadmin','admin'],

        // 群组管理
        'group.list'          => ['owner','superadmin','admin'],
        'group.bind'          => ['owner','superadmin','admin'],
        'group.unbind'        => ['owner','superadmin','admin'],
        'group.mute'          => ['owner','superadmin','admin'],
        'group.unmute'        => ['owner','superadmin','admin'],

        // 客服
        'support.reply'       => ['owner','superadmin','admin'],

        // 数据统计
        'stats.view'          => ['owner','superadmin','admin'],
        'bill.view'           => ['owner','superadmin','admin'],

        // 系统操作申请（管理员可申请）
        'sys.request'         => ['owner','superadmin','admin'],
        'sys.approve'         => ['owner','superadmin'],       // 只有 owner/超管审批
        'sys.request.list'    => ['owner','superadmin'],

        // 系统操作（危险，仅 owner/超管）
        'sys.status'          => ['owner','superadmin'],
        'sys.restart'         => ['owner','superadmin'],
        'sys.maintenance'     => ['owner','superadmin'],
        'sys.exec'            => ['owner','superadmin'],

        // 管理员管理（仅 owner）
        'admin.add.super'     => ['owner'],
        'admin.add'           => ['owner'],
        'admin.del'           => ['owner'],
        'admin.list'          => ['owner','superadmin'],

        // 审计日志（owner + 超管）
        'audit.view'          => ['owner','superadmin'],
    ];

    // 缓存，避免重复查库
    private static $roleCache = [];
    private static $uidCache  = [];

    /**
     * 获取 TG 用户身份
     */
    public static function role($tg_id) {
        $tg_id = (int)$tg_id;
        if (isset(self::$roleCache[$tg_id])) return self::$roleCache[$tg_id];

        $role = self::ROLE_USER;

        // 1. Owner
        if ($tg_id === (int)ADMIN_ID) {
            $role = self::ROLE_OWNER;
        } else {
            // 2. 查 pay_tgadmin
            $row = DB::one("SELECT level, status FROM pay_tgadmin WHERE tg_id=? LIMIT 1", [$tg_id]);
            if ($row && (int)$row['status'] === 1) {
                $role = ((int)$row['level'] === 1) ? self::ROLE_SUPERADMIN : self::ROLE_ADMIN;
            } else {
                // 3. 查 pay_tgbind
                $bind = DB::one("SELECT uid FROM pay_tgbind WHERE tg_id=? LIMIT 1", [$tg_id]);
                if ($bind && $bind['uid'] > 0) {
                    $role = self::ROLE_MERCHANT;
                }
            }
        }

        self::$roleCache[$tg_id] = $role;
        return $role;
    }

    /**
     * 权限检查
     */
    public static function can($tg_id, $action) {
        $role = self::role($tg_id);
        if (!isset(self::$perms[$action])) return false;
        return in_array($role, self::$perms[$action], true);
    }

    /**
     * 获取绑定的商户 uid（仅商户身份有效）
     */
    public static function merchantUid($tg_id) {
        $tg_id = (int)$tg_id;
        if (isset(self::$uidCache[$tg_id])) return self::$uidCache[$tg_id];
        $row = DB::one("SELECT uid FROM pay_tgbind WHERE tg_id=? LIMIT 1", [$tg_id]);
        $uid = $row ? (int)$row['uid'] : 0;
        self::$uidCache[$tg_id] = $uid;
        return $uid;
    }

    /**
     * 审计日志
     */
    public static function log($tg_id, $action, $target = '', $detail = '') {
        try {
            DB::exec("INSERT INTO pay_tg_audit (tg_id, role, action, target, detail) VALUES (?,?,?,?,?)",
                [(int)$tg_id, self::role($tg_id), $action, $target, $detail]);
        } catch (Exception $e) {
            plog("审计写入失败: " . $e->getMessage());
        }
        // 更新最后活跃
        try {
            DB::exec("UPDATE pay_tgadmin SET last_active=? WHERE tg_id=?", [time(), (int)$tg_id]);
        } catch (Exception $e) {}
    }

    // 便捷判断
    public static function isOwner($tg_id)      { return self::role($tg_id) === self::ROLE_OWNER; }
    public static function isSuperAdmin($tg_id) { return in_array(self::role($tg_id), [self::ROLE_OWNER, self::ROLE_SUPERADMIN], true); }
    public static function isAdmin($tg_id)      { return in_array(self::role($tg_id), [self::ROLE_OWNER, self::ROLE_SUPERADMIN, self::ROLE_ADMIN], true); }
    public static function isMerchant($tg_id)   { return self::role($tg_id) === self::ROLE_MERCHANT; }

    /**
     * 角色显示名
     */
    public static function roleName($role) {
        return [
            self::ROLE_OWNER      => '👑 所有者',
            self::ROLE_SUPERADMIN => '🛡️ 超级管理员',
            self::ROLE_ADMIN      => '🔰 管理员',
            self::ROLE_MERCHANT   => '🏪 商户',
            self::ROLE_USER       => '👤 用户',
        ][$role] ?? '未知';
    }
}
