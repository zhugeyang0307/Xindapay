<?php
$trade_no = $argv[1] ?? ($_GET['trade_no'] ?? ($_POST['trade_no'] ?? ''));
if (!$trade_no) { echo "no trade_no\n"; exit; }

// 【自动发货触发】放在最前，不受后续任何 exit 影响
$__dq = 'https://pay.yunzexinda.com/telegram/tg_shop/hook_deliver.php?trade_no=' . urlencode($trade_no);
$__chq = curl_init($__dq);
curl_setopt_array($__chq, [
    CURLOPT_TIMEOUT => 1, CURLOPT_NOSIGNAL => 1,
    CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_HTTPHEADER => ['Host: pay.yunzexinda.com'],
]);
curl_exec($__chq); curl_close($__chq);
// 【自动发货触发结束】

require_once __DIR__ . '/lib/DB.php';
require_once __DIR__ . '/lib/Telegram.php';

$logf = __DIR__ . '/hook.log';
$log = function($msg) use ($logf) {
    file_put_contents($logf, date('Y-m-d H:i:s') . " | $msg\n", FILE_APPEND);
};

$log("start trade_no=$trade_no");

$order = DB::one("SELECT trade_no, uid, name, money, realmoney, ext FROM pay_order WHERE trade_no=?", [$trade_no]);
if (!$order) { $log("order not found"); exit; }
$log("order found: " . json_encode($order, JSON_UNESCAPED_UNICODE));

$uid = (int)$order['uid'];
$amount = $order['realmoney'] ?: $order['money'];
// 上级（用于 SMTP / Bot 发件）
$__upid = (int)DB::col("SELECT upid FROM pay_user WHERE uid=?", [$uid]);
// 推送目标：优先码 owner（钱进谁微信谁去核对）
$__extP = json_decode($order['ext'] ?? '', true) ?: [];
$__qrcodeUid = (int)($__extP['qrcode_uid'] ?? 0);
$pushUid = ($__qrcodeUid > 0 && $__qrcodeUid !== $uid) ? $__qrcodeUid : $uid;
$log("pushUid={$pushUid} (订单uid={$uid} 码owner={$__qrcodeUid})");

// ===== 推送渠道判断（商户在推送配置页选择）=====
$__bc2 = DB::one("SELECT push_channel FROM pay_user_botconfig WHERE uid=?", [$pushUid]);
$push_channel = $__bc2 ? (int)$__bc2['push_channel'] : 0;

// 【核心覆盖】如果这个订单的码绑定了专属群，无视商户推送渠道，强制推 TG
$__orderQrGroup = null;
if (!empty($order['ext'])) {
    $__extQr = json_decode($order['ext'], true) ?: [];
    $__extQrId = intval($__extQr['qrcode_id'] ?? 0);
    if ($__extQrId > 0) {
        $__qgCheck = DB::col("SELECT group_id FROM pay_qrcode WHERE id=? AND group_id != '' AND group_id != '0'", [$__extQrId]);
        if (!empty($__qgCheck)) {
            $__orderQrGroup = $__qgCheck;
            $log("码绑定专属群 {$__orderQrGroup}，强制推送 TG");
        }
    }
}

$push_email = ($push_channel & 1) === 1;
$push_tg    = ($push_channel & 2) === 2 || $__orderQrGroup !== null;
$log("push_channel={$push_channel} email=" . ($push_email?'Y':'N') . " tg=" . ($push_tg?'Y':'N'));

if (!$push_email && !$push_tg) {
    $log("push_channel=0，商户未选择推送渠道，跳过");
    exit;
}

// ===== 邮件推送（仅当商户选了邮件渠道）=====
if ($push_email) {
    $__qmailTo = '';
    if (!empty($order['ext'])) {
        $__ext2 = json_decode($order['ext'], true) ?: [];
        $__qid2 = intval($__ext2['qrcode_id'] ?? 0);
        if ($__qid2 > 0) {
            $__qrow = DB::one("SELECT mail_to, mail_enabled FROM pay_qrcode WHERE id=?", [$__qid2]);
            if ($__qrow && intval($__qrow['mail_enabled']) === 1 && !empty($__qrow['mail_to'])) {
                $__qmailTo = $__qrow['mail_to'];
                $log("码级邮件 {$__qid2} → {$__qmailTo}");
            }
        }
    }
    $__mailSent = false;
    if (!empty($__qmailTo)) {
        $__hookUrl = (getenv('MAIL_HOOK_BASE') ?: 'http://pay.test') . '/mail_hook.php?uid=' . $uid . '&trade_no=' . urlencode($trade_no) . '&to=' . urlencode($__qmailTo);
        $__ch = curl_init($__hookUrl);
        curl_setopt_array($__ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>3, CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_SSL_VERIFYHOST=>0]);
        curl_exec($__ch); curl_close($__ch);
        $log("码级邮件推送已触发 trade_no=$trade_no to={$__qmailTo}");
        $__mailSent = true;
    }
    if (!$__mailSent) {
        $__sp = DB::one("SELECT sub_push_email, sub_push_status FROM pay_user WHERE uid=?", [$pushUid]);
        if ($__sp && intval($__sp['sub_push_status']) === 1 && !empty($__sp['sub_push_email'])) {
            $__hookUrl = (getenv('MAIL_HOOK_BASE') ?: 'http://pay.test') . '/mail_hook.php?uid=' . $pushUid . '&trade_no=' . urlencode($trade_no) . '&to=' . urlencode($__sp['sub_push_email']);
            $__ch = curl_init($__hookUrl);
            curl_setopt_array($__ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>3, CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_SSL_VERIFYHOST=>0]);
            curl_exec($__ch); curl_close($__ch);
            $log("下级邮件推送已触发 trade_no=$trade_no to={$__sp['sub_push_email']}");
            $__mailSent = true;
        }
    }
    if (!$__mailSent) {
        $__mc = DB::one("SELECT mail_enabled, mail_to FROM pay_user_botconfig WHERE uid=?", [$pushUid]);
        if ($__mc && intval($__mc['mail_enabled']) === 1 && !empty($__mc['mail_to'])) {
            $__hookUrl = (getenv('MAIL_HOOK_BASE') ?: 'http://pay.test') . '/mail_hook.php?uid=' . $pushUid . '&trade_no=' . urlencode($trade_no);
            $__ch = curl_init($__hookUrl);
            curl_setopt_array($__ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>3, CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_SSL_VERIFYHOST=>0]);
            curl_exec($__ch); curl_close($__ch);
            $log("邮件推送已触发 trade_no=$trade_no to={$__mc['mail_to']}");
            $__mailSent = true;
        }
    }
}

// ===== TG 推送（仅当商户选了 TG 渠道）=====
if (!$push_tg) {
    $log("push_channel 未含 TG，跳过");
    exit;
}

$merchantTgId = DB::col("SELECT tg_id FROM pay_tgbind WHERE uid=? LIMIT 1", [$pushUid]);
$groups = DB::query("SELECT chat_id FROM pay_tggroup WHERE uid=? AND is_merchant_group=1", [$pushUid]);
$log("merchantTgId=$merchantTgId groups=" . json_encode($groups));

DB::exec("INSERT INTO pay_tg_pending_order (trade_no, uid, amount, pkg, tg_chat_id, status) VALUES (?,?,?,?,?,0)",
         [$trade_no, $uid, $amount, 'hook', $merchantTgId ?: 0]);
$pendingId = DB::conn()->lastInsertId();
$log("pendingId=$pendingId");



// ===== 计算推送目标：优先用二维码绑定的群 =====
$__botToken = '';
$__target = '';
// 优先用上级的 Bot Token（下级没配 Bot）
$__botUid = ($__upid > 0) ? $__upid : $uid;
// 强制查码主及商户的自建机器人
$__bc = DB::one("SELECT use_own_bot, own_bot_token, own_bot_chatid, own_bot_groupid FROM pay_user_botconfig WHERE uid=?", [$pushUid]);
if (!$__bc || intval($__bc['use_own_bot']) !== 1 || empty($__bc['own_bot_token'])) {
    if ($uid != $pushUid) {
        $__bc = DB::one("SELECT use_own_bot, own_bot_token, own_bot_chatid, own_bot_groupid FROM pay_user_botconfig WHERE uid=?", [$uid]);
    }
}
if ($__bc && intval($__bc['use_own_bot']) === 1 && !empty($__bc['own_bot_token'])) {
    $__botToken = $__bc['own_bot_token'];
    $__target = !empty($__bc['own_bot_groupid']) ? $__bc['own_bot_groupid'] : ($__bc['own_bot_chatid'] ?? '');
}

// 如果没有启用自建机器人，才走码绑定群的逻辑
if (!$__hasOwnBot && !empty($order['ext'])) {
    $__ext = json_decode($order['ext'], true) ?: [];
    $__qid = intval($__ext['qrcode_id'] ?? 0);
    if ($__qid > 0) {
        $__qg = DB::col("SELECT group_id FROM pay_qrcode WHERE id=?", [$__qid]);
        if (!empty($__qg) && $__qg !== '0') {
            $log("qrcode {$__qid} → 绑定群 {$__qg}（覆盖默认群）");
            $__target = $__qg;
            if (empty($__botToken)) {
                $__botToken = defined('BOT_TOKEN') ? BOT_TOKEN : 'DEFAULT_TOKEN';
            }
        }
    }
}

// ===== 队列模式（优先）=====
require_once __DIR__ . '/../includes/tg_queue.php';
// 强制同步
// if (false && tgQueuePush($uid, $trade_no, $order, $__botToken, $__target)) {
//     $log("已入队 trade_no=$trade_no target=$__target");
//     exit;
// }
// 队列不可用，降级到同步推送

// ===== 商户自建机器人推送（同步降级）=====
if (!empty($__botToken) && !empty($__target)) {
    require_once __DIR__ . '/../includes/bot_sender.php';
    if (strval($__target)[0] === '-') {
        $__grpTitle = DB::col("SELECT title FROM pay_tggroup WHERE chat_id=? LIMIT 1", [$__target]) ?: '未命名群';
        $__icon = '🏠 群名';
    } else {
        $__uname = DB::col("SELECT tg_username FROM pay_tggroup_operator WHERE tg_id=? LIMIT 1", [$__target]) ?: '';
        $__grpTitle = $__uname ? '@' . $__uname : "私聊用户 {$__target}";
        $__icon = '👤 私聊';
    }
    // 查码的备注作为收款人
    $__qrInfo = DB::one("SELECT remark, pay_name, pay_account FROM pay_qrcode WHERE id=(SELECT JSON_UNQUOTE(JSON_EXTRACT(ext, '$.qrcode_id')) FROM pay_order WHERE trade_no=?) LIMIT 1", [$trade_no]);
    $__payee = $__qrInfo['remark'] ?: ($__qrInfo['pay_name'] ?: '未知');
    
    $__text = "🔔 <b>新订单到账</b>\n"
            . "👤 收款人：<b>{$__payee}</b>\n"
            . "💰 金额：<b>￥" . number_format($amount, 2) . "</b>\n"
            . "🔑 订单：<code>{$trade_no}</code>\n"
            . "💡 请点下方按钮确认";
    $__kb = ['inline_keyboard' => [[
        ['text' => '✅ 确认到账', 'callback_data' => "conf_ok_{$pendingId}"],
        ['text' => '❌ 忽略', 'callback_data' => "conf_no_{$pendingId}"]
    ]]];
    // 推送 + 失败自动重试（应对 TG 429 限流）
    $__ok = false;
    $__lastErr = '';
    for ($__try = 1; $__try <= 4; $__try++) {
        $__ok = botSend($__botToken, $__target, $__text, $__kb);
        if ($__ok) break;
        // 取原始错误
        $__ch = curl_init("https://api.telegram.org/bot{$__botToken}/sendMessage");
        curl_setopt_array($__ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'chat_id' => $__target, 'text' => '(probe)', 'disable_notification' => true,
            ]),
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5,
        ]);
        $__probe = curl_exec($__ch); curl_close($__ch);
        $__j = json_decode($__probe, true);
        $__lastErr = $__j['description'] ?? 'unknown';
        // 如果是 429，取 retry_after
        $__retry = (int)($__j['parameters']['retry_after'] ?? 3);
        if ($__retry < 1) $__retry = 3;
        @file_put_contents('/tmp/tg_ratelimit.log',
            date('H:i:s') . " try{$__try} target={$__target} err={$__lastErr} retry={$__retry}s\n",
            FILE_APPEND);
        sleep($__retry);
    }
    $log("商户自建bot推送[target={$__target}]: " . var_export($__ok, true) . ($__ok ? '' : " err={$__lastErr}"));
    exit; // 用了自建bot就不再发平台默认的
}

$__qrInfo = DB::one("SELECT remark, pay_name, pay_account FROM pay_qrcode WHERE id=(SELECT JSON_UNQUOTE(JSON_EXTRACT(ext, '$.qrcode_id')) FROM pay_order WHERE trade_no=?) LIMIT 1", [$trade_no]);
    $__payee = $__qrInfo['remark'] ?: ($__qrInfo['pay_name'] ?: '未知');

    $text = "🔔 <b>新订单到账</b>\n"
      . "👤 收款人：<b>{$__payee}</b>\n"
      . "💰 金额：<b>￥" . number_format($amount, 2) . "</b>\n"
      . "🔑 订单：<code>{$trade_no}</code>\n"
      . "💡 请点下方按钮确认";

$kb = ['inline_keyboard' => [[
    ['text' => '✅ 确认已看到', 'callback_data' => "conf_ok_{$pendingId}"],
    ['text' => '❌ 忽略', 'callback_data' => "conf_no_{$pendingId}"]
]]];

// ===== 标准商户组：如果没有自建bot也没有码绑定的专群，才跳过 =====
$__gid = (int)DB::col("SELECT gid FROM pay_user WHERE uid=?", [$pushUid]);
if ($__gid === 4 && empty($__target) && empty($groups)) {
    $log("gid=4 标准商户组未配置自建机器人，且无专属群，跳过平台默认 TG 推送");
    exit;
}

if ($merchantTgId) {
    $r = Telegram::sendMessage($merchantTgId, $text, $kb);
    $log("私聊返回: " . json_encode($r, JSON_UNESCAPED_UNICODE));
}
foreach ($groups as $g) {
    $r = Telegram::sendMessage($g['chat_id'], $text, $kb);
    $log("群 {$g['chat_id']} 返回: " . json_encode($r, JSON_UNESCAPED_UNICODE));
}
$log("done");
