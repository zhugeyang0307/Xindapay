<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/DB.php';
require_once __DIR__ . '/lib/Telegram.php';

header('Content-Type: application/json');

if (($_POST['secret'] ?? '') !== WEBHOOK_SECRET) {
    plog('Webhook 密钥错误');
    exit(json_encode(['code'=>-1,'msg'=>'forbidden']));
}

$event = $_POST['event'] ?? '';
$data = json_decode($_POST['data'] ?? '{}', true);

if ($event === 'order_paid') pushOrderPaid($data);
elseif ($event === 'order_created') pushOrderCreated($data);

echo json_encode(['code'=>0]);

function pushOrderPaid($data) {
    $trade_no = $data['trade_no'] ?? '';
    $order = DB::one("SELECT o.*, u.username, t.showname AS type_name
                      FROM pay_order o
                      LEFT JOIN pay_user u ON u.uid=o.uid
                      LEFT JOIN pay_type t ON t.id=o.type
                      WHERE o.trade_no=?", [$trade_no]);
    if (!$order) return;

    $text = "✅ <b>到账通知</b>\n"
          . "━━━━━━━━━━━━━━\n"
          . "商户：{$order['username']} (UID: {$order['uid']})\n"
          . "通道：{$order['type_name']}\n"
          . "金额：<b>￥" . number_format($order['realmoney'], 2) . "</b>\n"
          . "订单号：<code>{$trade_no}</code>\n"
          . "时间：" . date('Y-m-d H:i:s');

    // 1. 超级管理员群（所有订单都推）
    $adminGroups = DB::query("SELECT chat_id FROM pay_tggroup WHERE uid=0");
    foreach ($adminGroups as $g) {
        Telegram::sendMessage($g['chat_id'], $text);
    }
    // 兼容 config.php 里的静态超管群
    if (NOTIFY_GROUP_ID > 0) {
        Telegram::sendMessage(NOTIFY_GROUP_ID, $text);
    }

    // 2. 商户群（只推该商户的）
    $merchantGroups = DB::query("SELECT chat_id FROM pay_tggroup WHERE uid=?", [$order['uid']]);
    foreach ($merchantGroups as $g) {
        Telegram::sendMessage($g['chat_id'], $text);
    }

    // 3. 商户私聊（绑定的 TG 用户）
    $bind = DB::one("SELECT tg_id FROM pay_tgbind WHERE uid=?", [$order['uid']]);
    if ($bind && $bind['tg_id']) {
        Telegram::sendMessage($bind['tg_id'], $text);
    }
}

function pushOrderCreated($data) {
    if (NOTIFY_GROUP_ID > 0) {
        $text = "🆕 <b>新订单</b>\n"
              . "商户UID：{$data['uid']}\n"
              . "金额：￥{$data['money']}\n"
              . "订单号：<code>{$data['trade_no']}</code>";
        Telegram::sendMessage(NOTIFY_GROUP_ID, $text);
    }
}
