<?php
$trade_no = $_GET['trade_no'] ?? ($argv[1] ?? '');
if (!$trade_no) exit;
@file_put_contents(__DIR__.'/created_debug.log', date('Y-m-d H:i:s')." | trade_no={$trade_no}\n", FILE_APPEND);
require_once __DIR__ . '/lib/DB.php';
require_once __DIR__ . '/lib/Telegram.php';
require_once __DIR__ . '/../includes/bot_sender.php';

$order = DB::one("SELECT * FROM pay_order WHERE trade_no=?", [$trade_no]);
if (!$order || $order['status'] != 0) exit;

$ext = json_decode($order['ext'] ?? '{}', true);
$qid = (int)($ext['qrcode_id'] ?? 0);
$qrcodeUid = (int)($ext['qrcode_uid'] ?? 0);
if (!$qid) exit;

// 找机器人配置：优先码主 -> 上级 -> 顶级
$bc = null;
$curUid = $qrcodeUid;
while ($curUid > 0) {
    $bc = DB::one("SELECT * FROM pay_user_botconfig WHERE uid=? AND use_own_bot=1 AND own_bot_token IS NOT NULL AND own_bot_token != ''", [$curUid]);
    if ($bc) break;
    $curUid = (int)DB::col("SELECT upid FROM pay_user WHERE uid=?", [$curUid]);
}
if (!$bc) exit;

$target = $bc['own_bot_groupid'] ?: $bc['own_bot_chatid'];
if (!$target) exit;

$qr = DB::one("SELECT remark, pay_name FROM pay_qrcode WHERE id=?", [$qid]);
$payee = $qr['remark'] ?: ($qr['pay_name'] ?: '未命名');

$msg = "⏳ <b>新订单待支付</b>\n"
     . "👤 收款人：<b>{$payee}</b>\n"
     . "💰 金额：<b>￥" . number_format($order['money'], 2) . "</b>\n"
     . "🔑 订单：<code>{$trade_no}</code>\n"
     . "💡 客户已拉起支付，请留意收款。";

foreach (explode(',', $target) as $t) {
    botSend($bc['own_bot_token'], trim($t), $msg);
}
echo 'ok';
