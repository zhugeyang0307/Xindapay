<?php
require_once "/www/wwwroot/pay.yunzexinda.com/telegram/config.php";
require_once "/www/wwwroot/pay.yunzexinda.com/telegram/lib/DB.php";
require_once "/www/wwwroot/pay.yunzexinda.com/telegram/lib/Telegram.php";

set_time_limit(0); // 防止超时
date_default_timezone_set('Asia/Shanghai');

$hour = (int)date('H');
$greeting = ($hour < 12) ? "🌞 <b>早上好！</b>" : "🌙 <b>晚上好！</b>";

$text = "{$greeting}\n"
      . "🎉 <b>信达网络科技 · 限时惊喜福利</b> 🎊\n"
      . "━━━━━━━━━━━━━━\n"
      . "🔥 <b>即日起注册，免月费体验全部高级功能（至 2026-11-01）！</b>\n"
      . "✅ <b>0 费率：平台不抽成，收多少拿多少！</b>\n"
      . "🚀 <b>不限制行业，可对接包网及四方线上平台！</b>\n\n"
      . "✨ <b>核心亮点：</b>\n"
      . "• 智能路由防封防拦截 / 监听APK自动上报\n"
      . "• 邮件追踪（到达率、打开率、点击统计）\n"
      . "• 完整API接口，支持3级下级代客\n\n"
      . "💬 有任何疑问，欢迎点击下方按钮联系客服！";

$kb = ['inline_keyboard' => [[['text' => '👉 联系客服', 'url' => 'https://t.me/xindapay1H5_bot']]]];

$targets = [];
// 只发给有交互历史用户 (pay_tguser)
$users = DB::query("SELECT DISTINCT tg_id FROM pay_tguser");
foreach ($users as $u) { if (!empty($u['tg_id'])) $targets[] = $u['tg_id']; }

$tokens = [
    '主机器人' => getenv('MAIN_BOT_TOKEN') ?: 'REPLACE_WITH_MAIN_BOT_TOKEN',
    '客服机器人' => getenv('CUSTOMER_BOT_TOKEN') ?: 'REPLACE_WITH_CUSTOMER_BOT_TOKEN',
    '沙箱机器人' => getenv('SANDBOX_BOT_TOKEN') ?: 'REPLACE_WITH_SANDBOX_BOT_TOKEN'
];

$successCount = 0;
$sentTargets = [];

foreach ($tokens as $botName => $token) {
    Telegram::useToken($token);
    foreach ($targets as $target) {
        if (in_array($target, $sentTargets)) continue;
        
        $res = Telegram::sendMessage($target, $text, $kb);
        if (!empty($res['ok'])) {
            $successCount++;
            $sentTargets[] = $target;
        }
        sleep(1); // 防封禁
    }
}

// 记录日志
$logFile = __DIR__ . '/cron_push_activity.log';
$log = date('Y-m-d H:i:s') . " | 推送完成 | 目标数: " . count($targets) . " | 成功数: {$successCount}\n";
file_put_contents($logFile, $log, FILE_APPEND);
