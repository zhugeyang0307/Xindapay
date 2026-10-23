<?php
require_once __DIR__ . '/Auth.php';

class Audit {

    /**
     * 查询日志
     * @param array $opts ['date'=>'2026-10-11', 'tg_id'=>123, 'action'=>'order', 'limit'=>50]
     */
    public static function query($opts = []) {
        $where = ['1=1'];
        $args = [];
        if (!empty($opts['date'])) {
            $where[] = "DATE(addtime)=?";
            $args[] = $opts['date'];
        }
        if (!empty($opts['tg_id'])) {
            $where[] = "tg_id=?";
            $args[] = (int)$opts['tg_id'];
        }
        if (!empty($opts['action'])) {
            $where[] = "action LIKE ?";
            $args[] = $opts['action'] . '%';
        }
        if (!empty($opts['start'])) {
            $where[] = "addtime>=?";
            $args[] = $opts['start'];
        }
        $limit = intval($opts['limit'] ?? 50);
        if ($limit < 1 || $limit > 200) $limit = 50;
        $sql = "SELECT * FROM pay_tg_audit WHERE " . implode(' AND ', $where) . " ORDER BY id DESC LIMIT $limit";
        return DB::query($sql, $args);
    }

    /**
     * 统计
     */
    public static function stats($date = null) {
        if (!$date) $date = date('Y-m-d');
        return [
            'total'  => (int)DB::col("SELECT COUNT(*) FROM pay_tg_audit WHERE DATE(addtime)=?", [$date]),
            'actors' => (int)DB::col("SELECT COUNT(DISTINCT tg_id) FROM pay_tg_audit WHERE DATE(addtime)=?", [$date]),
        ];
    }

    /**
     * 角色 icon
     */
    public static function roleIcon($role) {
        return [
            Auth::ROLE_OWNER      => '👑',
            Auth::ROLE_SUPERADMIN => '🛡️',
            Auth::ROLE_ADMIN      => '🔰',
            Auth::ROLE_MERCHANT   => '🏪',
            Auth::ROLE_USER       => '👤',
        ][$role] ?? '❓';
    }

    /**
     * 操作中文名
     */
    public static function actionName($action) {
        $map = [
            'self.info'         => '查看商户信息',
            'self.bill'         => '查看流水',
            'self.orders'       => '查看订单',
            'merchant.view'     => '查看商户',
            'merchant.list'     => '列出商户',
            'merchant.ban'      => '封禁商户',
            'merchant.unban'    => '解封商户',
            'merchant.unbind'   => '解除绑定',
            'group.list'        => '查看群组',
            'group.bind'        => '绑定群',
            'group.unbind'      => '解绑群',
            'group.mute'        => '群禁言',
            'group.unmute'      => '群解禁',
            'sys.status'        => '系统状态',
            'sys.request'       => '申请操作',
            'sys.approve'       => '审批通过',
            'sys.reject'        => '审批拒绝',
            'admin.add'         => '添加管理员',
            'admin.add.super'   => '添加超管',
            'admin.del'         => '删除管理员',
            'qr.add'            => '添加二维码',
            'qr.del'            => '删除二维码',
            'qr.info'           => '二维码详情',
            'confirm.toggle'    => '切换手动确认',
            'order.confirm'     => '确认订单到账',
            'order.ignore'      => '忽略订单',
            'login.success'     => '登录成功',
            'login.fail'        => '登录失败',
            'register'          => '注册账号',
        ];
        return $map[$action] ?? $action;
    }
}
