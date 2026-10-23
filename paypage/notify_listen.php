<?php
require_once __DIR__ . '/../includes/common.php';

$secret = 'Wxl930307Wxl9303072026FF';

$pkg       = $_POST['pkg']    ?? '';
$amountRaw = $_POST['amount'] ?? '';
$amount    = floatval($amountRaw);
$time      = intval($_POST['time'] ?? 0);
$raw       = $_POST['raw']    ?? '';
$sign      = $_POST['sign']   ?? '';
$ip        = $_SERVER['REMOTE_ADDR'] ?? '';

header('Content-Type: text/plain; charset=utf-8');

// 0. 授权
$token = $_POST['token'] ?? '';
$lic = $token ? $DB->getRow("SELECT * FROM pay_license WHERE token=:t LIMIT 1", [':t'=>$token]) : null;
if (!$lic) { echo 'unauthorized'; exit; }
if ($lic['status'] == 2) { echo 'disabled'; exit; }
if (strtotime($lic['expire_time']) + 3*86400 < time()) { echo 'expired'; exit; }
$DB->exec("UPDATE pay_license SET last_beat=NOW() WHERE id=:id", [':id'=>$lic['id']]);

// 1. 验签
$amountJava = (string)(float)$amount;
if (strpos($amountJava, '.') === false) $amountJava .= '.0';
$useSecret = !empty($lic["secret"]) ? $lic["secret"] : $secret;
if ($sign !== md5($amountJava . $time . $useSecret)) {
    file_put_contents(__DIR__ . '/listen.log',
        date('Y-m-d H:i:s') . " | bad sign | pkg=$pkg amount=$amount\n", FILE_APPEND);
    echo 'bad sign'; exit;
}

// 2. 时间窗口
if (abs(time() - $time) > 600) { echo 'timeout'; exit; }

// 3. 商户 uid
$licUid = intval($lic['uid']);
if ($licUid <= 0) { echo 'license 未绑定商户'; exit; }
if (!\lib\Service::isActive($licUid)) { echo 'service expired'; exit; }

// 4. 匹配订单（FIFO + 排除 pending，不用事务）
$order = $DB->getRow(
    "SELECT trade_no, uid, ext, param, name FROM pay_order
     WHERE status=0 AND realmoney=?
     AND uid=?
     AND addtime > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
     AND trade_no NOT IN (SELECT trade_no FROM pay_tg_pending_order WHERE status=0)
     ORDER BY addtime ASC LIMIT 1",
    [$amount, $licUid]
);

// 5. 写日志
if ($order) {
    $DB->exec("INSERT INTO pay_listen_log (pkg, amount, raw, report_time, ip, uid, order_trade_no, match_status)
               VALUES (?, ?, ?, FROM_UNIXTIME(?), ?, ?, ?, 1)",
              [$pkg, $amount, $raw, $time, $ip, $order['uid'], $order['trade_no']]);
} else {
    $DB->exec("INSERT INTO pay_listen_log (pkg, amount, raw, report_time, ip)
               VALUES (?, ?, ?, FROM_UNIXTIME(?), ?)",
              [$pkg, $amount, $raw, $time, $ip]);
    echo 'ok unmatched'; exit;
}

// 6. 查码信息
$extData = json_decode($order['ext'] ?? '', true) ?: [];
$qrcodeId = intval($extData['qrcode_id'] ?? 0);
if (!$qrcodeId) {
    $paramData = json_decode($order['param'] ?? '', true) ?: [];
    $qrcodeId = intval($paramData['qrcode_id'] ?? 0);
}
$qrRemark = ''; $qrOperator = '';
if ($qrcodeId > 0) {
    $qrRow = $DB->getRow("SELECT remark, operator_name FROM pay_qrcode WHERE id=:id", [':id'=>$qrcodeId]);
    $qrRemark = $qrRow['remark'] ?? '';
    $qrOperator = $qrRow['operator_name'] ?? '';
}

// 7. 确认开关
$confirmOn = (int)$DB->getColumn("SELECT tg_confirm FROM pay_user WHERE uid=:u", [':u'=>$order['uid']]);
$merchantTgId = $DB->getColumn("SELECT tg_id FROM pay_tgbind WHERE uid=:u LIMIT 1", [':u'=>$order['uid']]);

if ($confirmOn === 1) {
    $DB->exec("INSERT INTO pay_tg_pending_order (trade_no, uid, amount, pkg, tg_chat_id, status) 
               VALUES (:t, :u, :a, :p, :g, 0)",
              [':t'=>$order['trade_no'], ':u'=>$order['uid'], ':a'=>$amount, ':p'=>$pkg, ':g'=>$merchantTgId ?: 0]);
    $pendingId = $DB->lastInsertId();

    $name = $order['name'] ?? '订单';
    $pkgMap = [
        'com.eg.android.AlipayGphone' => '支付宝',
        'com.tencent.mm'              => '微信',
        'com.tencent.mobileqq'        => 'QQ钱包',
        'com.unionpay'                => '云闪付',
    ];
    $pkgLabel = $pkgMap[$pkg] ?? $pkg;

    $text = "🔔 <b>收到新订单</b>\n"
          . "━━━━━━━━━━━━━━━━━━\n"
          . "📦 商品：{$name}\n"
          . "💰 金额：<b>￥" . number_format($amount, 2) . "</b>\n"
          . "🔑 订单：<code>{$order['trade_no']}</code>\n";
    if ($qrRemark) $text .= "📱 收款码：<b>{$qrRemark}</b> #{$qrcodeId}\n";
    if ($qrOperator) $text .= "👤 码主：{$qrOperator}\n";
    $text .= "🕐 时间：" . date('m-d H:i:s') . "\n";
    if ($pkg) $text .= "📡 来源：{$pkgLabel}\n";
    $text .= "━━━━━━━━━━━━━━━━━━\n"
          . "商户：UID {$order['uid']}\n";
    if ($qrOperator) $text .= "{$qrOperator} 请确认是否到账：";
    else $text .= "请确认是否到账：";

    $kb = json_encode(['inline_keyboard' => [[
        ['text' => '✅ 确认到账', 'callback_data' => "conf_ok_{$pendingId}"],
        ['text' => '❌ 忽略', 'callback_data' => "conf_no_{$pendingId}"]
    ]]]);

    $targets = [];
    if ($merchantTgId) $targets[] = $merchantTgId;
    $groups = $DB->getAll("SELECT chat_id FROM pay_tggroup WHERE uid=:u AND is_merchant_group=1", [':u'=>$order['uid']]);
    foreach ($groups as $g) $targets[] = $g['chat_id'];

    $botToken = 'YOUR_MAIN_BOT_TOKEN_HERE';
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    foreach ($targets as $tid) {
        $post = http_build_query(['chat_id'=>$tid, 'text'=>$text, 'parse_mode'=>'HTML', 'reply_markup'=>$kb]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$post, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10]);
        curl_exec($ch); curl_close($ch);
    }
    echo 'ok pending';
} else {
    \lib\Order::complete($order['trade_no'], $amount);
    echo 'ok matched';
}
