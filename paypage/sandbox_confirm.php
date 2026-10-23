<?php
/**
 * 测试订单专用确认入口
 * 用户点【✅我已完成付款】→ 生成 token + 发到账通知
 * URL: /paypage/sandbox_confirm.php?trade_no=xxx
 */
require_once __DIR__ . '/../includes/common.php';
require_once __DIR__ . '/../telegram/secrets.php';
@header('Content-Type: application/json; charset=UTF-8');

$trade_no = trim($_GET['trade_no'] ?? '');
if (empty($trade_no)) exit(json_encode(['code'=>-1, 'msg'=>'订单号不能为空']));

// 1. 查测试订单
$order = $DB->getRow("SELECT * FROM pay_order WHERE trade_no=? AND is_test=1 LIMIT 1", [$trade_no]);
if (!$order) exit(json_encode(['code'=>-1, 'msg'=>'测试订单不存在']));

// 2. 检查重复点击
$exist = $DB->getRow("SELECT id, token, status FROM pay_mail_pending WHERE trade_no=? ORDER BY id DESC LIMIT 1", [$trade_no]);
if ($exist && (int)$exist['status'] === 0) {
    // 已生成且未确认 → 返回旧 token
    exit(json_encode(['code'=>0, 'token'=>$exist['token'], 'msg'=>'已生成通知']));
}
if ($exist && (int)$exist['status'] === 1) {
    exit(json_encode(['code'=>-2, 'msg'=>'该订单已确认到账']));
}

// 3. 读 ext 里的回调配置
$ext = json_decode($order['ext'], true) ?: [];
$cbType = $ext['callback_type'] ?? 'tg';
$cbTarget = $ext['callback_target'] ?? '';
if (empty($cbTarget)) {
    exit(json_encode(['code'=>-1, 'msg'=>'未配置回调目标（请重新从机器人发起测试）']));
}

// 4. 记录支付 IP（用户点按钮时的请求 IP）
$payIp = $_SERVER['REMOTE_ADDR'] ?? '';
$DB->exec("UPDATE pay_order SET ip=? WHERE trade_no=?", [$payIp, $trade_no]);

// 5. 生成 token 存 pending
$token = hash_hmac('sha256', $trade_no . '|' . $order['uid'] . '|' . time(), SYS_KEY);
$token = substr($token, 0, 64);

$DB->exec("INSERT INTO pay_mail_pending (trade_no, uid, to_email, token, status, sent_at, expire_at)
           VALUES (?, ?, ?, ?, 0, NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR))",
    [$trade_no, $order['uid'], $cbTarget, $token]);

// 6. 构造确认页 URL
$okUrl = 'https://pay.yunzexinda.com/mail_confirm.php?token=' . $token . '&action=ok';
$noUrl = 'https://pay.yunzexinda.com/mail_confirm.php?token=' . $token . '&action=no';

$amount = number_format($order['realmoney'] > 0 ? $order['realmoney'] : $order['money'], 2);

// 7. 按类型发通知
if ($cbType === 'tg') {
    // ===== TG 推送（用沙箱 bot）=====
    $sandboxToken = SANDBOX_BOT_TOKEN;
    $kb = json_encode(['inline_keyboard' => [[
        ['text' => '✅ 确认到账', 'url' => $okUrl],
        ['text' => '❌ 忽略', 'url' => $noUrl],
    ]]]);
    $text = "🔔 <b>新订单到账</b> · <code>测试订单</code>\n"
          . "━━━━━━━━━━━━━━━━━━\n"
          . "📦 商品：测试订单\n"
          . "💰 金额：<b>￥{$amount}</b>\n"
          . "🔑 订单：<code>{$trade_no}</code>\n"
          . "🕐 时间：" . date('m-d H:i:s') . "\n"
          . "━━━━━━━━━━━━━━━━━━\n"
          . "💡 请点下方按钮确认到账";

    $ch = curl_init("https://api.telegram.org/bot{$sandboxToken}/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'chat_id' => $cbTarget,
            'text' => $text,
            'reply_markup' => $kb,
            'parse_mode' => 'HTML',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $r = curl_exec($ch);
    curl_close($ch);

    @file_put_contents('/tmp/sandbox_confirm.log',
        date('Y-m-d H:i:s') . " | TG | {$trade_no} | to={$cbTarget} | " . substr($r, 0, 200) . "\n", FILE_APPEND);

    exit(json_encode(['code'=>0, 'token'=>$token, 'msg'=>'TG 通知已发送']));
}

if ($cbType === 'mail') {
    // ===== 邮件推送 =====
    $hookUrl = 'https://pay.yunzexinda.com/mail_hook.php?uid=' . $order['uid']
             . '&trade_no=' . urlencode($trade_no)
             . '&to=' . urlencode($cbTarget)
             . '&is_test=1';
    $ch = curl_init($hookUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $r = curl_exec($ch);
    curl_close($ch);

    @file_put_contents('/tmp/sandbox_confirm.log',
        date('Y-m-d H:i:s') . " | MAIL | {$trade_no} | to={$cbTarget} | " . substr($r, 0, 200) . "\n", FILE_APPEND);

    exit(json_encode(['code'=>0, 'token'=>$token, 'msg'=>'邮件通知已发送']));
}

exit(json_encode(['code'=>-1, 'msg'=>'未知回调类型']));
