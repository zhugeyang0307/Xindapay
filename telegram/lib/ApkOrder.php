<?php
require_once __DIR__ . '/DB.php';
require_once __DIR__ . '/Telegram.php';
require_once __DIR__ . '/Bepusdt.php';
require_once __DIR__ . '/ApkTutorial.php';

class ApkOrder {

    /**
     * 生成激活码
     */
    public static function genCode() {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $seg = function() use ($chars) {
            $s = '';
            for ($i=0; $i<4; $i++) $s .= $chars[random_int(0, strlen($chars)-1)];
            return $s;
        };
        return 'XD-'.$seg().'-'.$seg().'-'.$seg();
    }

    /**
     * 创建购买订单（返回 trade_no + 支付链接）
     */
    public static function create($uid, $days = 30) {
        // 价格从 pay_config 读
        $price = (float)DB::col("SELECT v FROM pay_config WHERE k='license_price_usdt'");
        if ($price <= 0) $price = 50;

        // 生成内部订单号
        $trade_no = 'APK' . date('YmdHis') . mt_rand(1000, 9999);

        // 写记录
        DB::exec("INSERT INTO pay_apk_order (uid, trade_no, amount, days, status, pay_type) VALUES (?,?,?,?,0,'usdt')",
                 [$uid, $trade_no, $price, $days]);
        $id = DB::conn()->lastInsertId();

        // 调 BEpusdt 创建支付订单（用 Bepusdt 类，金额是 USDT）
        $payUrl = Bepusdt::createOrder($trade_no, $price);
        if (!$payUrl) return [false, '创建支付订单失败'];
        return [true, ['id'=>$id, 'trade_no'=>$trade_no, 'pay_url'=>$payUrl, 'price'=>$price, 'days'=>$days]];
    }

    /**
     * 支付完成（回调时调用）
     */
    public static function complete($trade_no) {
        $row = DB::one("SELECT * FROM pay_apk_order WHERE trade_no=?", [$trade_no]);
        if (!$row) return [false, '订单不存在'];
        if ((int)$row['status'] >= 1) return [false, '已处理'];

        $uid = (int)$row['uid'];
        $days = (int)$row['days'];

        // 生成激活码
        $code = self::genCode();
        while (DB::col("SELECT COUNT(*) FROM pay_license WHERE code=?", [$code])) {
            $code = self::genCode();
        }

        // 算过期时间
        $expire = date('Y-m-d H:i:s', time() + $days * 86400);

        // 生成独立上报密钥 + 上报 token
        $secret = substr(md5($code . mt_rand() . time()), 0, 32);
        $token  = substr(hash('sha256', $code . $uid . mt_rand() . microtime() . $secret), 0, 46);

        // 写 pay_license（含 token，用于 APK 上报授权）
        DB::exec("INSERT INTO pay_license (code, uid, expire_time, remark, status, secret, token) VALUES (?,?,?,?,1,?,?)",
                 [$code, $uid, $expire, "APK-购买-{$trade_no}", $secret, $token]);

        // 更新订单
        DB::exec("UPDATE pay_apk_order SET status=2, license_code=?, paytime=NOW() WHERE trade_no=?",
                 [$code, $trade_no]);

        // 根据接收方式分发
        $recvType = $row['receive_type'] ?? 'tg';
        $recvValue = $row['receive_value'] ?? '';

        // 兜底：如果没填，用商户绑定的 TG
        if (!$recvValue) {
            $recvType = 'tg';
            $recvValue = (string)DB::col("SELECT tg_id FROM pay_tgbind WHERE uid=? LIMIT 1", [$uid]);
        }

        $text = "🎉 <b>APK 授权购买成功</b>\n"
              . "━━━━━━━━━━━━━━━━━━\n"
              . "🆔 商户ID：<code>{$uid}</code>\n"
              . "💰 支付金额：<b>{$row['amount']} USDT</b>\n"
              . "📅 有效期：{$days} 天\n"
              . "🔑 激活码：<code>{$code}</code>\n"
              . "🔐 上报密钥：<code>{$secret}</code>\n"
              . "━━━━━━━━━━━━━━━━━━\n"
              . "📱 打开 APK，填：\n"
              . "   • 激活码：{$code}\n"
              . "   • 上报密钥：{$secret}\n"
              . "APK 下载：https://pay.yunzexinda.com/xinda_listener.apk";

        if ($recvType === 'tg' && $recvValue) {
            Telegram::sendMessage($recvValue, $text);
            Telegram::sendMessage($recvValue, ApkTutorial::tg());
        } elseif ($recvType === 'email' && $recvValue) {
            // 兼容禁用了 shell_exec 的环境，改用 include 执行
            $__argv = [$recvValue, $code, $secret];
            ob_start();
            try {
                include __DIR__ . '/../send_license_mail.php';
            } catch (Throwable $e) {}
            ob_end_clean();
        }

        $tgId = $recvValue; // 兼容返回值

        // 通知管理员群
        if (defined('NOTIFY_GROUP_ID')) {
            Telegram::sendMessage(NOTIFY_GROUP_ID,
                "💰 <b>商户购买 APK 授权</b>\n"
              . "商户ID：<code>{$uid}</code>\n"
              . "金额：{$row['amount']} USDT\n"
              . "激活码：<code>{$code}</code>");
        }

        return [true, ['code'=>$code, 'uid'=>$uid, 'days'=>$days, 'tg_id'=>$tgId]];
    }

    /**
     * 查商户的购买记录
     */
    public static function listByUid($uid) {
        return DB::query("SELECT * FROM pay_apk_order WHERE uid=? ORDER BY id DESC LIMIT 20", [$uid]);
    }

    /**
     * 查商户所有激活码
     */
    public static function licensesByUid($uid) {
        return DB::query("SELECT * FROM pay_license WHERE uid=? ORDER BY id DESC LIMIT 20", [$uid]);
    }
}
