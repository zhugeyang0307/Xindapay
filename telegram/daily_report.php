<?php
/**
 * 每日账单推送
 * 用法：php daily_report.php
 * 定时：58 23 * * * /www/server/php/82/bin/php /www/wwwroot/pay.yunzexinda.com/telegram/daily_report.php
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/DB.php';
require_once __DIR__ . '/lib/Telegram.php';

set_time_limit(60);
date_default_timezone_set('Asia/Shanghai');

$today_start = date('Y-m-d') . ' 00:00:00';
$today_end   = date('Y-m-d') . ' 23:59:59';
$date = date('Y-m-d');

try {
    $total_amount = (float) DB::col("SELECT COALESCE(SUM(realmoney),0) FROM pay_order WHERE status=1 AND addtime>=? AND addtime<=?", [$today_start, $today_end]);
    $paid_cnt     = (int)   DB::col("SELECT COUNT(*) FROM pay_order WHERE status=1 AND addtime>=? AND addtime<=?", [$today_start, $today_end]);
    $unpaid_cnt   = (int)   DB::col("SELECT COUNT(*) FROM pay_order WHERE status=0 AND addtime>=? AND addtime<=?", [$today_start, $today_end]);
    $all_cnt      = (int)   DB::col("SELECT COUNT(*) FROM pay_order WHERE addtime>=? AND addtime<=?", [$today_start, $today_end]);
    $active_users = (int)   DB::col("SELECT COUNT(DISTINCT uid) FROM pay_order WHERE status=1 AND addtime>=? AND addtime<=?", [$today_start, $today_end]);

    $top = DB::query(
        "SELECT o.uid, u.username, SUM(o.realmoney) AS total, COUNT(*) AS cnt
         FROM pay_order o LEFT JOIN pay_user u ON u.uid=o.uid
         WHERE o.status=1 AND o.addtime>=? AND o.addtime<=?
         GROUP BY o.uid ORDER BY total DESC LIMIT 5",
        [$today_start, $today_end]);

    $bytype = DB::query(
        "SELECT o.type, t.showname AS type_name, t.name AS type_code,
                SUM(o.realmoney) AS total, COUNT(*) AS cnt
         FROM pay_order o LEFT JOIN pay_type t ON t.id=o.type
         WHERE o.status=1 AND o.addtime>=? AND o.addtime<=?
         GROUP BY o.type ORDER BY total DESC",
        [$today_start, $today_end]);
} catch (Exception $e) {
    plog("daily_report DB error: " . $e->getMessage());
    exit("DB error: " . $e->getMessage() . "\n");
}

$avg = $paid_cnt > 0 ? number_format($total_amount / $paid_cnt, 2) : '0.00';

$text  = "📊 <b>信达科技 - {$date} 日账单</b>\n";
$text .= "━━━━━━━━━━━━━━━━━━\n";
$text .= "💰 今日入款：<b>¥ " . number_format($total_amount, 2) . "</b>\n";
$text .= "📦 今日订单：{$all_cnt} 笔\n";
$text .= "✅ 已支付：{$paid_cnt} 笔\n";
$text .= "⏳ 未支付：{$unpaid_cnt} 笔\n";
$text .= "💵 平均金额：¥ {$avg}\n";
$text .= "👥 活跃商户：{$active_users} 个\n";

if ($top) {
    $text .= "━━━━━━━━━━━━━━━━━━\n";
    $text .= "🏆 <b>TOP 商户</b>\n";
    $i = 1;
    foreach ($top as $t) {
        $name = $t['username'] ?: ('UID:' . $t['uid']);
        $text .= "{$i}. " . htmlspecialchars($name, ENT_QUOTES) . "  ¥ " . number_format($t['total'], 2) . " ({$t['cnt']}笔)\n";
        $i++;
    }
}

if ($bytype) {
    $text .= "━━━━━━━━━━━━━━━━━━\n";
    $text .= "⚡ <b>通道分布</b>\n";
    foreach ($bytype as $t) {
        $tc = strtolower($t['type_code'] ?? '');
        $icon = '💳';
        if (strpos($tc, 'alipay') !== false) $icon = '🅰️';
        elseif (strpos($tc, 'wx') !== false)    $icon = '💚';
        elseif (strpos($tc, 'qq') !== false)    $icon = '🐧';
        elseif (strpos($tc, 'bank') !== false)  $icon = '🏦';
        elseif (strpos($tc, 'jd') !== false)    $icon = '🟥';
        elseif (strpos($tc, 'douyin') !== false) $icon = '🎵';
        $name = $t['type_name'] ?: '未知';
        $text .= "{$icon} " . htmlspecialchars($name, ENT_QUOTES) . "  ¥ " . number_format($t['total'], 2) . " ({$t['cnt']}笔)\n";
    }
}

$text .= "━━━━━━━━━━━━━━━━━━\n";
$text .= "🕐 生成时间：" . date('Y-m-d H:i:s') . "\n";

$groups = DB::query("SELECT chat_id, uid, title FROM pay_tggroup");
if (!$groups) {
    plog("daily_report: no groups bound");
    exit("No groups bound\n");
}

$sent = 0;
foreach ($groups as $g) {
    if ($g['chat_id'] < 0) {
        try {
            Telegram::sendMessage($g['chat_id'], $text);
            plog("daily_report sent to {$g['chat_id']} ({$g['title']})");
            $sent++;
        } catch (Exception $e) {
            plog("daily_report TG error: " . $e->getMessage());
        }
    }
}

echo "OK, sent to {$sent} group(s)\n";
