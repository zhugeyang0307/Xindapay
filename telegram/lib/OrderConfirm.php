<?php
require_once __DIR__ . '/Auth.php';

class OrderConfirm {

    /**
     * 商户开关
     */
    public static function setConfirm($uid, $on) {
        DB::exec("UPDATE pay_user SET tg_confirm=? WHERE uid=?", [$on ? 1 : 0, $uid]);
    }
    public static function isConfirmOn($uid) {
        return (int)DB::col("SELECT tg_confirm FROM pay_user WHERE uid=?", [$uid]) === 1;
    }

    /**
     * 创建待确认记录 + 推送
     */
    public static function push($trade_no, $uid, $amount, $pkg = '') {
        $tgId = DB::col("SELECT tg_id FROM pay_tgbind WHERE uid=? LIMIT 1", [$uid]);
        if (!$tgId) {
            // 商户没绑定 TG → 直接自动完成
            return ['auto'];
        }
        // 写库
        DB::exec("INSERT INTO pay_tg_pending_order (trade_no, uid, amount, pkg, tg_chat_id, status) VALUES (?,?,?,?,?,0)",
                 [$trade_no, $uid, $amount, $pkg, $tgId]);
        $id = DB::conn()->lastInsertId();

        // 组装消息
        $q = DB::one("SELECT name, addtime FROM pay_order WHERE trade_no=?", [$trade_no]);
        $name = $q['name'] ?? '订单';
        $hm = date('m-d H:i:s');
        $text = "🔔 <b>收到新订单</b>\n"
              . "━━━━━━━━━━━━━━━━━━\n"
              . "📦 商品：{$name}\n"
              . "💰 金额：<b>￥" . number_format($amount, 2) . "</b>\n"
              . "🔑 订单：<code>{$trade_no}</code>\n"
              . "🕐 时间：{$hm}\n"
              . ($pkg ? "📱 来源：{$pkg}\n" : '')
              . "━━━━━━━━━━━━━━━━━━\n"
              . "请确认是否到账：";
        $kb = ['inline_keyboard' => [
            [
                ['text' => '✅ 确认到账', 'callback_data' => "conf_ok_{$id}"],
                ['text' => '❌ 忽略', 'callback_data' => "conf_no_{$id}"]
            ]
        ]];
        $r = Telegram::sendMessage($tgId, $text, $kb);
        if (!empty($r['result']['message_id'])) {
            DB::exec("UPDATE pay_tg_pending_order SET tg_msg_id=? WHERE id=?", [$r['result']['message_id'], $id]);
        }
        return ['pushed', $id];
    }

    /**
     * 检查点击者权限：
     * - 商户本人（通过 tg_id → uid == 该订单 uid）
     * - 商户群里的操作人
     * - 系统管理员
     */
        private static function canConfirm($tgId, $uid, $chat_id = null) {
        // 管理员
        if (class_exists('Auth') && Auth::isAdmin($tgId)) return true;

        // 该 TG 是否绑定到该商户
        $bindUid = DB::col("SELECT uid FROM pay_tgbind WHERE tg_id=? LIMIT 1", [$tgId]);
        if ($bindUid && (int)$bindUid === (int)$uid) return true;

        // 若在群里点击，检查是否是操作人
        if ($chat_id && class_exists('GroupOp')) {
            // 🔥 兼容 TG 匿名管理员 (GroupAnonymousBot ID: 1087968824)
            if ($tgId == 1087968824) {
                $groupBindUid = DB::col("SELECT uid FROM pay_tgbind WHERE tg_id=? LIMIT 1", [$chat_id]);
                if ($groupBindUid && (int)$groupBindUid === (int)$uid) return true;
                if (GroupOp::groupMerchantUid($chat_id) === (int)$uid) return true;
            }
            $grpUid = GroupOp::groupMerchantUid($chat_id);
            if ((int)$grpUid === (int)$uid && GroupOp::canOperate($chat_id, $tgId)) return true;
        }
        return false;
    }

    /**
     * 确认到账
     */
    public static function confirm($id, $tgId, $chat_id = null) {
        $row = DB::one("SELECT * FROM pay_tg_pending_order WHERE id=?", [$id]);
        if (!$row) return [false, '订单不存在'];
        if ((int)$row['status'] !== 0) return [false, '已处理'];

        // 权限校验
        if (!self::canConfirm($tgId, $row['uid'], $chat_id)) {
            return [false, '无权操作'];
        }

        // 调独立脚本完成订单（不污染机器人进程）
        $script = __DIR__ . '/../complete_order.php';
        $cmd = 'php ' . escapeshellarg($script) . ' ' . escapeshellarg($row['trade_no']) . ' ' . escapeshellarg($row['amount']) . ' 2>&1';
        $output = @shell_exec($cmd);
        $output = trim($output ?: '');
        $r = json_decode($output, true);
        if (!is_array($r)) $r = ['code' => -1, 'msg' => $output ?: '未知错误'];

        // 判断脚本是否成功
        if (isset($r['code']) && ($r['code'] == 0 || $r['code'] == 1)) {
            DB::exec("UPDATE pay_tg_pending_order SET status=1, confirm_time=NOW() WHERE id=?", [$id]);

            // 查操作人信息
            $operator = self::getOperatorInfo($tgId);
            return [true, $r['msg'] ?? '订单已完成', $operator];
        }
        return [false, '完成失败：' . ($r['msg'] ?? 'unknown')];
    }

    /**
     * 查操作人显示名
     */
    private static function getOperatorInfo($tgId) {
        $u = DB::one("SELECT username, first_name, last_name FROM pay_tguser WHERE tg_id=? LIMIT 1", [$tgId]);
        if (!$u) return "ID:{$tgId}";
        $name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
        if ($u['username']) $name .= " (@" . $u['username'] . ")";
        if (!$name) $name = "ID:{$tgId}";
        return $name . " <code>{$tgId}</code>";
    }

    /**
     * 忽略
     */
    public static function ignore($id, $tgId, $chat_id = null) {
        $row = DB::one("SELECT * FROM pay_tg_pending_order WHERE id=?", [$id]);
        if (!$row) return [false, '订单不存在'];
        if ((int)$row['status'] !== 0) return [false, '已处理'];
        if (!self::canConfirm($tgId, $row['uid'], $chat_id)) return [false, '无权操作'];
        DB::exec("UPDATE pay_tg_pending_order SET status=2, confirm_time=NOW() WHERE id=?", [$id]);
        $operator = self::getOperatorInfo($tgId);
        return [true, '已忽略', $operator];
    }

    /**
     * 列出待确认
     */
    public static function pending($uid) {
        return DB::query("SELECT * FROM pay_tg_pending_order WHERE uid=? AND status=0 ORDER BY id DESC LIMIT 20", [$uid]);
    }
}
