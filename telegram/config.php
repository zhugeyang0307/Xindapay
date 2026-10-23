<?php
// ========== Telegram（支持主机器人 / 客服机器人双模式） ==========
// 使用方式：
//   主机器人：  require config.php（默认 BOT_MODE = main）
//   客服机器人：先 define('BOT_MODE', 'customer'); 再 require config.php
if (!defined('BOT_MODE')) define('BOT_MODE', 'main');

$__botTokens = [
    'main'     => getenv('MAIN_BOT_TOKEN') ?: 'REPLACE_WITH_MAIN_BOT_TOKEN',
    'customer' => getenv('CUSTOMER_BOT_TOKEN') ?: 'REPLACE_WITH_CUSTOMER_BOT_TOKEN',
];
$__botLogs = [
    'main'     => __DIR__ . '/bot.log',
    'customer' => __DIR__ . '/customer_bot.log',
];

define('BOT_TOKEN', $__botTokens[BOT_MODE]);
define('ADMIN_ID', 0);              // 你的 TG 数字ID
define('NOTIFY_GROUP_ID', 0);       // 平台通知群 ID

// ========== MySQL（跟支付系统一致） ==========
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_USER', 'xindapay');
define('DB_PASS', getenv('DB_PASS') ?: 'REPLACE_WITH_DB_PASSWORD');
define('DB_NAME', 'xindapay');

// ========== Webhook ==========
define('WEBHOOK_SECRET', getenv('WEBHOOK_SECRET') ?: 'REPLACE_WITH_WEBHOOK_SECRET');

// ========== 日志 ==========
define('LOG_FILE', $__botLogs[BOT_MODE]);

function plog($msg) {
    file_put_contents(LOG_FILE, date('Y-m-d H:i:s') . ' | ' . $msg . "\n", FILE_APPEND);
}

// ========== 双向客服 ==========
define('RELAY_ADMINS', '7811967320,8338998360');       // 接收客户消息的管理员（逗号分隔）
define('RELAY_GROUP',   '-1003700116634');              // 接收客户消息的群
define('RELAY_HINT',    '📩 已收到，客服稍后回复');       // 客户侧提示



// ========== 明细页密钥 ==========
define('CRON_KEY', getenv('CRON_KEY') ?: 'REPLACE_WITH_CRON_KEY');
define('SITE_URL', 'https://pay.yunzexinda.com');

// ========== 明细页约定密钥（自己改，不要告诉任何人）==========

// ========== BEpusdt ==========
define('BEPUSDT_API_URL', 'http://127.0.0.1:8080');
define('BEPUSDT_TOKEN',   getenv('BEPUSDT_TOKEN') ?: 'REPLACE_WITH_BEPUSDT_TOKEN');
define('BEPUSDT_NOTIFY',  'https://pay.yunzexinda.com/telegram/usdt_callback.php');
define('BEPUSDT_REDIRECT','https://pay.yunzexinda.com/');
define('DEMO_PRICE_USDT', 5);

// ========== TG 快捷注册 ==========
define('REG_RECHARGE_USDT', 300);
define('REG_RECHARGE_RATE', 6.68);

// ========== DeepSeek AI 客服 ==========
define('DEEPSEEK_API_KEY', getenv('DEEPSEEK_API_KEY') ?: 'REPLACE_WITH_DEEPSEEK_API_KEY');
define('DEEPSEEK_MODEL',   'deepseek-chat');
define('DEEPSEEK_ENABLED', true);   // true=开启AI自动回复，false=纯人工   // USDT 兑 CNY 汇率


function botlog($bot, $upd) {
    try {
        $tgId = 0; $username = ''; $content = ''; $action = 'other';
        if (isset($upd['message'])) {
            $m = $upd['message'];
            $tgId = $m['from']['id'] ?? 0;
            $username = $m['from']['username'] ?? '';
            $content = $m['text'] ?? ($m['caption'] ?? '[non-text]');
            $action = (strpos($content, '/') === 0) ? 'command' : 'message';
        } elseif (isset($upd['callback_query'])) {
            $cq = $upd['callback_query'];
            $tgId = $cq['from']['id'] ?? 0;
            $username = $cq['from']['username'] ?? '';
            $content = $cq['data'] ?? '';
            $action = 'callback';
        }
        $updateId = $upd['update_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $line = date('Y-m-d H:i:s') . " | [" . $bot . "] tg=" . $tgId . " user=" . $username . " action=" . $action . " update=" . $updateId . " content=" . mb_substr($content, 0, 500) . PHP_EOL;
        @file_put_contents(__DIR__ . "/bot_call_" . $bot . ".log", $line, FILE_APPEND);
        if (class_exists('DB')) {
            DB::exec("INSERT INTO pay_bot_log (bot,tg_id,username,action,content,update_id,ip) VALUES (?,?,?,?,?,?,?)",
                [$bot, $tgId, $username, $action, mb_substr($content, 0, 500), $updateId, $ip]);
        }
    } catch (Exception $e) {}
}

require_once __DIR__ . '/secrets.php';
