<?php
/**
 * 邮件推送 webhook（内部调用）
 * 由 hook_order_paid.php 通过 HTTP 触发
 */
require_once('includes/common.php');
require_once('includes/lib/MailOrder.php');

// 只允许本机
$allow = ['127.0.0.1', '::1', '124.156.163.186'];
if (!empty($conf['server_ip'])) $allow[] = $conf['server_ip'];

if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $allow, true)) {
    http_response_code(403);
    exit('forbidden');
}

$uid      = intval($_GET['uid'] ?? 0);
$trade_no = trim($_GET['trade_no'] ?? '');
if (!$uid || !$trade_no) exit('param error');

$toOverride = trim($_GET['to'] ?? '');
$mc = $DB->getRow("SELECT mail_enabled, mail_to FROM pay_user_botconfig WHERE uid=?", [$uid]);
if (!empty($toOverride)) {
    // 码级邮箱优先，跳过全局开关判断
    $mc = ['mail_enabled' => 1, 'mail_to' => $toOverride];
}
if (!$mc || intval($mc['mail_enabled']) !== 1 || empty($mc['mail_to'])) {
    exit('disabled');
}

$order = $DB->getRow("SELECT * FROM pay_order WHERE trade_no=?", [$trade_no]);
if (!$order) exit('order not found');

$r = \lib\MailOrder::push($order, $mc['mail_to']);

@file_put_contents('/tmp/mail_hook.log',
    date('Y-m-d H:i:s') . " | uid={$uid} | {$trade_no} | " . json_encode($r) . "\n",
    FILE_APPEND);

echo json_encode($r);
