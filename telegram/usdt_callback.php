<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/DB.php';
require_once __DIR__ . '/lib/Telegram.php';
require_once __DIR__ . '/lib/Bepusdt.php';
require_once __DIR__ . '/lib/Register.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) $data = $_POST;
file_put_contents(__DIR__ . '/usdt_callback.log',
    date('Y-m-d H:i:s') . ' 回调: ' . json_encode($data) . "\n", FILE_APPEND);

if (!Bepusdt::verifyCallback($data)) {
    http_response_code(403); echo 'sign error'; exit;
}
if (!in_array((int)($data['status'] ?? 0), [2, 3])) { echo 'ok'; exit; }

$orderId = $data['order_id'] ?? '';
$tradeId = $data['trade_id'] ?? '';

if (strpos($orderId, 'REG_') === 0) {
    list($ok, $info) = Register::completePay($orderId);
    if (!$ok) { echo 'ok'; exit; }

    Telegram::sendMessage($info['chat_id'],
        "🎉 <b>预充值成功！账号信息如下：</b>\n"
      . "━━━━━━━━━━━━━━\n"
      . "🆔 商户ID：<code>{$info['uid']}</code>\n"
      . "📧 登录账号：<code>{$info['account']}</code>\n"
      . "🔐 登录密码：<code>{$info['pwd']}</code>\n"
      . "🔑 商户密钥：<code>{$info['key']}</code>\n"
      . "💰 已充值：<b>{$info['amount_cny']} 元</b>\n"
      . "━━━━━━━━━━━━━━\n"
      . "请登录：<a href=\"https://pay.yunzexinda.com/user/\">商户后台</a>\n"
      . "建议立即修改密码。");

    Telegram::sendMessage(NOTIFY_GROUP_ID,
        "💰 <b>新商户注册并预充值</b>\n"
      . "商户ID：<code>{$info['uid']}</code>\n"
      . "账号：{$info['account']}\n"
      . "金额：" . REG_RECHARGE_USDT . " USDT（{$info['amount_cny']}元）\n"
      . "TG：<code>{$info['chat_id']}</code>");

    Register::clearState($info['chat_id']);
    echo 'ok'; exit;
}

// ========== 商户后台余额充值（U 开头）==========
if (strpos($orderId, 'U') === 0) {
    $row = DB::one("SELECT * FROM pay_usdt_recharge WHERE trade_no=? LIMIT 1", [$orderId]);
    if (!$row) {
        file_put_contents(__DIR__ . '/usdt_callback.log',
            date('Y-m-d H:i:s') . " U订单不存在: $orderId\n", FILE_APPEND);
        echo 'order not found'; exit;
    }
    if ((int)$row['status'] !== 0) { echo 'ok'; exit; }

    DB::exec("UPDATE pay_usdt_recharge SET status=1, txid=?, paytime=NOW() WHERE id=?",
             [$tradeId, $row['id']]);
    changeUserMoney($row['uid'], $row['amount_cny'], true, 'USDT充值', $row['trade_no']);

    file_put_contents(__DIR__ . '/usdt_callback.log',
        date('Y-m-d H:i:s') . " U订单到账: {$orderId} uid={$row['uid']} +¥{$row['amount_cny']}\n", FILE_APPEND);
    echo 'ok'; exit;
}
// ========== APK 购买订单（APK 开头）==========
if (strpos($orderId, 'APK') === 0) {
    require_once __DIR__ . '/lib/ApkOrder.php';
    list($ok, $info) = ApkOrder::complete($orderId);
    if (!$ok) {
        file_put_contents(__DIR__ . '/usdt_callback.log',
            date('Y-m-d H:i:s') . " APK订单处理失败: $orderId - {$info}\n", FILE_APPEND);
    } else {
        file_put_contents(__DIR__ . '/usdt_callback.log',
            date('Y-m-d H:i:s') . " APK订单完成: $orderId code={$info['code']} uid={$info['uid']}\n", FILE_APPEND);
    }
    echo 'ok'; exit;
}

// ========== 系统服务续费订单（SVC 开头）==========
if (strpos($orderId, 'SVC') === 0) {
    $row = DB::one("SELECT * FROM pay_service_order WHERE trade_no=? LIMIT 1", [$orderId]);
    if (!$row) {
        file_put_contents(__DIR__ . '/usdt_callback.log',
            date('Y-m-d H:i:s') . " SVC订单不存在: $orderId\n", FILE_APPEND);
        echo 'order not found'; exit;
    }
    if ((int)$row['status'] !== 0) { echo 'ok'; exit; }

    $uid = (int)$row['uid'];
    $u = DB::one("SELECT service_expire, service_trial_end, apk_quota FROM pay_user WHERE uid=?", [$uid]);
    if (!$u) { echo 'user not found'; exit; }

    $base = max(
        time(),
        $u['service_expire'] ? strtotime($u['service_expire']) : 0,
        $u['service_trial_end'] ? strtotime($u['service_trial_end']) : 0
    );
    $newExpire = date('Y-m-d H:i:s', $base + (int)$row['months'] * 30 * 86400);
    $newQuota  = (int)$u['apk_quota'] + (int)$row['extra_devices'];

    DB::exec("UPDATE pay_user SET service_expire=?, apk_quota=? WHERE uid=?",
             [$newExpire, $newQuota, $uid]);
    DB::exec("UPDATE pay_service_order SET status=1, paytime=NOW() WHERE id=?",
             [$row['id']]);

    // 通知商户
    $merchantTgId = DB::col("SELECT tg_id FROM pay_tgbind WHERE uid=? LIMIT 1", [$uid]);
    if ($merchantTgId) {
        $msg = "✅ <b>系统服务续费成功</b>\n"
             . "━━━━━━━━━━━━━━━━━━\n"
             . "📦 订单：<code>{$orderId}</code>\n"
             . "⏱ 续费：<b>{$row['months']} 个月</b>\n";
        if ((int)$row['extra_devices'] > 0) {
            $msg .= "📱 增加设备：<b>+{$row['extra_devices']} 台</b>\n";
        }
        $msg .= "💰 金额：<b>{$row['amount']} USDT</b>\n"
              . "📅 新到期时间：<b>{$newExpire}</b>\n"
              . "📊 新设备额度：<b>{$newQuota} 台</b>\n"
              . "━━━━━━━━━━━━━━━━━━\n"
              . "商户后台 → 系统服务";
        $token = BOT_TOKEN;
        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        $post = http_build_query(['chat_id'=>$merchantTgId, 'text'=>$msg, 'parse_mode'=>'HTML']);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$post, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10]);
        curl_exec($ch); curl_close($ch);
    }

    file_put_contents(__DIR__ . '/usdt_callback.log',
        date('Y-m-d H:i:s') . " SVC续费成功: {$orderId} uid={$uid} months={$row['months']} extra={$row['extra_devices']} newExpire={$newExpire} newQuota={$newQuota}\n", FILE_APPEND);
    echo 'ok'; exit;
}
// ========== 普通订单（19位 trade_no）==========
if (preg_match('/^\d{19}$/', $orderId)) {
    $order = DB::one("SELECT trade_no, uid, name, money, realmoney, status FROM pay_order WHERE trade_no=? LIMIT 1", [$orderId]);
    if (!$order) { echo 'order not found'; exit; }
    if ((int)$order['status'] !== 0) { echo 'ok'; exit; }

    $uid = (int)$order['uid'];
    $amount = $order['realmoney'] ?: $order['money'];

    // 查商户手动确认开关
    $confirmOn = (int)DB::col("SELECT tg_confirm FROM pay_user WHERE uid=?", [$uid]);
    $merchantTgId = DB::col("SELECT tg_id FROM pay_tgbind WHERE uid=? LIMIT 1", [$uid]);
    $groups = DB::query("SELECT chat_id FROM pay_tggroup WHERE uid=? AND is_merchant_group=1", [$uid]);

    if ($confirmOn === 1 && ($merchantTgId || $groups)) {
        // 写待确认表
        DB::exec("INSERT INTO pay_tg_pending_order (trade_no, uid, amount, pkg, tg_chat_id, status) VALUES (?,?,?,?,?,0)",
                 [$orderId, $uid, $amount, 'usdt.trc20', $merchantTgId ?: 0]);
        $pendingId = DB::conn()->lastInsertId();

        $text = "🔔 <b>收到新订单（USDT 到账）</b>\n"
              . "━━━━━━━━━━━━━━━━━━\n"
              . "📦 商品：{$order['name']}\n"
              . "💰 金额：<b>￥" . number_format($amount, 2) . "</b>\n"
              . "🔑 订单：<code>{$orderId}</code>\n"
              . "📱 通道：USDT-TRC20\n"
              . "🔗 链上交易：<code>{$tradeId}</code>\n"
              . "🕐 时间：" . date('m-d H:i:s') . "\n"
              . "━━━━━━━━━━━━━━━━━━\n请确认是否到账：";

        $kb = json_encode(['inline_keyboard' => [[
            ['text' => '✅ 确认到账', 'callback_data' => "conf_ok_{$pendingId}"],
            ['text' => '❌ 忽略', 'callback_data' => "conf_no_{$pendingId}"]
        ]]]);

        $token = BOT_TOKEN;
        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        if ($merchantTgId) {
            $post = http_build_query(['chat_id'=>$merchantTgId, 'text'=>$text, 'parse_mode'=>'HTML', 'reply_markup'=>$kb]);
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$post, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10]);
            curl_exec($ch); curl_close($ch);
        }
        foreach ($groups as $g) {
            $post = http_build_query(['chat_id'=>$g['chat_id'], 'text'=>$text, 'parse_mode'=>'HTML', 'reply_markup'=>$kb]);
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$post, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10]);
            curl_exec($ch); curl_close($ch);
        }
        file_put_contents(__DIR__ . '/usdt_callback.log',
            date('Y-m-d H:i:s') . " 普通订单待确认 pendingId={$pendingId} trade_no={$orderId}\n", FILE_APPEND);
        echo 'ok pending'; exit;
    } else {
        // 自动完成
        $script = __DIR__ . '/complete_order.php';
        $cmd = 'php ' . escapeshellarg($script) . ' ' . escapeshellarg($orderId) . ' ' . escapeshellarg($amount) . ' 2>&1';
        $output = @shell_exec($cmd);
        file_put_contents(__DIR__ . '/usdt_callback.log',
            date('Y-m-d H:i:s') . " 普通订单自动完成 output=" . trim($output ?: '') . "\n", FILE_APPEND);
        echo 'ok matched'; exit;
    }
}

// ========== 演示订单 ==========
$row = DB::one("SELECT * FROM pay_demo_orders WHERE order_id=? LIMIT 1", [$orderId]);
if (!$row) { echo 'order not found'; exit; }
if ($row['status'] >= 1) { echo 'ok'; exit; }

DB::exec("UPDATE pay_demo_orders SET status=1, trade_id=?, paid_at=? WHERE order_id=?",
         [$tradeId, time(), $orderId]);

sendDemoAccount($row['chat_id']);

DB::exec("UPDATE pay_demo_orders SET status=2 WHERE order_id=?", [$orderId]);

Telegram::sendMessage(NOTIFY_GROUP_ID,
    "💰 <b>演示账号已售出</b>\n订单：<code>{$orderId}</code>\n金额：" . DEMO_PRICE_USDT . " USDT\nTG用户：<code>{$row['tg_user_id']}</code>");

echo 'ok';
