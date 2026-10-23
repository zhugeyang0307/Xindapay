<?php
require_once __DIR__ . '/../telegram/lib/DB.php';
require_once __DIR__ . '/../telegram/lib/Telegram.php';
require_once __DIR__ . '/../includes/bot_sender.php';

$LOG = __DIR__ . '/logs/worker.log';
function wlog($m) { global $LOG; file_put_contents($LOG, date('Y-m-d H:i:s')." | $m\n", FILE_APPEND); }

wlog("=== Worker 启动 ===");

$lastSendAt = [];
$MIN_INTERVAL = 3.5;

$r = new Redis();
$r->connect('127.0.0.1', 6379, 2.0);
$r->select(1);

while (true) {
    $job = $r->brPop(['xinda:tg:queue'], 5);
    if (!$job) continue;

    $data = json_decode($job[1], true);
    if (!$data || empty($data['trade_no'])) {
        wlog("bad job");
        continue;
    }

    $uid      = (int)$data['uid'];
    $trade_no = $data['trade_no'];
    $order    = $data['order'];

    // job 里带的 token/target 优先；没有则回退查数据库
    $botToken = $data['bot_token'] ?? '';
    $target   = $data['target'] ?? '';

    if (empty($botToken) || empty($target)) {
        $bc = DB::one("SELECT use_own_bot, own_bot_token, own_bot_chatid, own_bot_groupid
                       FROM pay_user_botconfig WHERE uid=?", [$uid]);
        if (!$bc || intval($bc['use_own_bot']) !== 1 || empty($botToken)) {
            wlog("skip $trade_no: no own bot");
            continue;
        }
        $botToken = $botToken;
        $target   = !empty($bc['own_bot_groupid']) ? $bc['own_bot_groupid'] : ($bc['own_bot_chatid'] ?? '');
    }

    if (empty($target)) {
        wlog("skip $trade_no: no target");
        continue;
    }

    // 动态限速：负数（群）= 3.5 秒；正数（私聊）= 0.8 秒
    $__interval = (strval($target)[0] === '-') ? $MIN_INTERVAL : 0.8;
    $now = microtime(true);
    if (isset($lastSendAt[$target])) {
        $elapsed = $now - $lastSendAt[$target];
        if ($elapsed < $__interval) {
            usleep((int)(($__interval - $elapsed) * 1000000));
        }
    }

    $amount = $order['realmoney'] ?: $order['money'];

    // 查群名 + 操作人
    // 负数=群，查 pay_tggroup；正数=私聊，查 pay_tgbind 或显示"私聊"
    if (strval($target)[0] === '-') {
        $grpTitle = DB::col("SELECT title FROM pay_tggroup WHERE chat_id=? LIMIT 1", [$target]) ?: '未命名群';
        $__icon = '🏠 群名';
    } else {
        $__uname = DB::col("SELECT tg_username FROM pay_tggroup_operator WHERE tg_id=? LIMIT 1", [$target]) ?: '';
        $grpTitle = $__uname ? '@' . $__uname : "私聊用户 {$target}";
        $__icon = '👤 私聊';
    }

    // 查码绑定的操作人（可选）
    $opName = '';
    $qid = intval($data['qrcode_id'] ?? 0);
    if ($qid > 0) {
        $opName = DB::col("SELECT operator_name FROM pay_qrcode WHERE id=? LIMIT 1", [$qid]) ?: '';
    }

    $text = "🔔 <b>新订单到账</b> · <code>{$grpTitle}</code>\n"
          . "━━━━━━━━━━━━━━━━━━\n"
          . "📦 商品：{$order['name']}\n"
          . "💰 金额：<b>￥" . number_format($amount, 2) . "</b>\n"
          . "🔑 订单：<code>{$trade_no}</code>\n"
          . "{$__icon}：<b>{$grpTitle}</b>\n"
          . ($opName ? "👤 操作人：{$opName}\n" : "")
          . "🕐 时间：" . date('m-d H:i:s') . "\n"
          . "━━━━━━━━━━━━━━━━━━\n"
          . "💡 请点下方按钮确认到账";

    $pendingId = DB::col("SELECT id FROM pay_tg_pending_order WHERE trade_no=? ORDER BY id DESC LIMIT 1", [$trade_no]);
    if (!$pendingId) {
        DB::exec("INSERT INTO pay_tg_pending_order (trade_no, uid, amount, pkg, tg_chat_id, status) VALUES (?,?,?,?,?,0)",
                 [$trade_no, $uid, $amount, 'queue', $target]);
        $pendingId = DB::conn()->lastInsertId();
    }

    $kb = ['inline_keyboard' => [[
        ['text' => '✅ 确认到账', 'callback_data' => "conf_ok_{$pendingId}"],
        ['text' => '❌ 忽略', 'callback_data' => "conf_no_{$pendingId}"],
    ]]];

    $ok = botSend($botToken, $target, $text, $kb);
    if (!$ok) {
        $ch = curl_init("https://api.telegram.org/bot{$botToken}/sendMessage");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['chat_id'=>$target, 'text'=>'(probe)']),
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5,
        ]);
        $resp = curl_exec($ch); curl_close($ch);
        $j = json_decode($resp, true);
        $retryAfter = (int)($j['parameters']['retry_after'] ?? 5);
        wlog("fail $trade_no, wait {$retryAfter}s, retry");
        sleep($retryAfter);
        $ok = botSend($botToken, $target, $text, $kb);
    }

    $lastSendAt[$target] = microtime(true);
    wlog(($ok ? "✅" : "❌") . " sent $trade_no to $target");
}

$r->close();
