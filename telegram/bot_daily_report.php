<?php
/**
 * Bot 访问数据日报 - 通过沙箱 bot 推送给超级管理员
 * 用法：php bot_daily_report.php
 * 定时：0 9 * * * /www/server/php/82/bin/php /www/wwwroot/pay.yunzexinda.com/telegram/bot_daily_report.php >> /tmp/bot_daily.log 2>&1
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/DB.php';
require_once __DIR__ . '/lib/Telegram.php';

// 关键：用沙箱 bot 的 token 发送
Telegram::useToken(SANDBOX_BOT_TOKEN);

set_time_limit(60);
date_default_timezone_set('Asia/Shanghai');

$date  = date('Y-m-d');
$start = $date . ' 00:00:00';
$end   = $date . ' 23:59:59';

try {
    $total = (int) DB::col("SELECT COUNT(*) FROM pay_bot_log WHERE created_at>=? AND created_at<=?", [$start, $end]);
    $users = (int) DB::col("SELECT COUNT(DISTINCT tg_id) FROM pay_bot_log WHERE created_at>=? AND created_at<=?", [$start, $end]);

    $byBot = DB::query(
        "SELECT bot, COUNT(*) AS cnt, COUNT(DISTINCT tg_id) AS users
         FROM pay_bot_log WHERE created_at>=? AND created_at<=?
         GROUP BY bot", [$start, $end]);

    $topUsers = DB::query(
        "SELECT tg_id, username, COUNT(*) AS cnt
         FROM pay_bot_log WHERE created_at>=? AND created_at<=?
         GROUP BY tg_id ORDER BY cnt DESC LIMIT 5", [$start, $end]);

    $topActions = DB::query(
        "SELECT content, COUNT(*) AS cnt
         FROM pay_bot_log
         WHERE created_at>=? AND created_at<=? AND action='command' AND content<>''
         GROUP BY content ORDER BY cnt DESC LIMIT 10", [$start, $end]);

    $newUsers = DB::query(
        "SELECT DISTINCT l.tg_id, l.username
         FROM pay_bot_log l
         WHERE l.created_at>=? AND l.created_at<=?
           AND NOT EXISTS (
             SELECT 1 FROM pay_bot_log l2
             WHERE l2.tg_id=l.tg_id AND l2.created_at < ?
           )
         LIMIT 10", [$start, $end, $start]);
} catch (Exception $e) {
    plog("bot_daily_report DB error: " . $e->getMessage());
    exit("DB error\n");
}

$botName = ['main'=>'主bot','customer'=>'客服bot','sandbox'=>'沙箱bot'];
$botIcon = ['main'=>'🟦','customer'=>'🟩','sandbox'=>'🟨'];

$text  = "📊 <b>Bot 访问日报 - {$date}</b>\n";
$text .= "━━━━━━━━━━━━━━━━━━\n";
$text .= "📈 总调用：<b>{$total}</b> 次\n";
$text .= "👥 独立用户：<b>{$users}</b> 人\n";

if ($byBot) {
    $text .= "━━━━━━━━━━━━━━━━━━\n";
    $text .= "🤖 <b>分 Bot 统计</b>\n";
    foreach ($byBot as $r) {
        $b  = $r['bot'];
        $ic = $botIcon[$b] ?? '⚪';
        $nm = $botName[$b] ?? $b;
        $text .= "{$ic} {$nm}：{$r['cnt']} 次 / {$r['users']} 人\n";
    }
}

if ($topActions) {
    $text .= "━━━━━━━━━━━━━━━━━━\n";
    $text .= "🔥 <b>热门命令 TOP</b>\n";
    $i = 1;
    foreach ($topActions as $r) {
        $c = htmlspecialchars(mb_substr($r['content'], 0, 30), ENT_QUOTES);
        $text .= "{$i}. <code>{$c}</code> × {$r['cnt']}\n";
        $i++;
    }
}

if ($topUsers) {
    $text .= "━━━━━━━━━━━━━━━━━━\n";
    $text .= "🏆 <b>活跃用户 TOP5</b>\n";
    $i = 1;
    foreach ($topUsers as $r) {
        $nm = $r['username'] ?: ('TG:' . $r['tg_id']);
        $text .= "{$i}. " . htmlspecialchars($nm, ENT_QUOTES) . " × {$r['cnt']}\n";
        $i++;
    }
}

if ($newUsers) {
    $text .= "━━━━━━━━━━━━━━━━━━\n";
    $text .= "🆕 <b>今日新用户</b>\n";
    foreach ($newUsers as $r) {
        $nm = $r['username'] ?: ('TG:' . $r['tg_id']);
        $text .= "• " . htmlspecialchars($nm, ENT_QUOTES) . "\n";
    }
}

$text .= "━━━━━━━━━━━━━━━━━━\n";
$text .= "🕐 " . date('Y-m-d H:i:s');

$receivers = [7811967320, 5811494428];
foreach ($receivers as $adminId) {
    $r = Telegram::sendMessage($adminId, $text);
    if ($r && isset($r['ok']) && $r['ok']) {
        plog("bot_daily_report sent to admin {$adminId}");
        echo "OK, sent to {$adminId}\n";
    } else {
        plog("bot_daily_report TG failed to {$adminId}: " . json_encode($r));
        echo "TG failed to {$adminId}: " . json_encode($r) . "\n";
    }
}
