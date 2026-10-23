<?php
// 邮件追踪系统配置
require_once dirname(__DIR__) . '/config.php';           // 主 config（含 $dbconfig）
require_once dirname(__DIR__) . '/telegram/lib/DB.php';  // 引入 DB 类（自动加载 telegram/config.php）

// 追踪基础 URL（本地/线上自动切换）
if (!defined('MAIL_TRACK_BASE')) {
    $isLocal = ($_SERVER['HTTP_HOST'] ?? '') === 'pay.test'
            || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;
    define('MAIL_TRACK_BASE', $isLocal 
        ? 'http://pay.test/mail_track' 
        : 'https://pay.yunzexinda.com/mail_track');
}

define('MAIL_RATE_LIMIT_PER_SEC', 2);
define('MAIL_BATCH_SIZE', 100);
define('MAIL_TRACK_LOG', __DIR__ . '/logs/send.log');

function mailTrackLog($msg) {
    @file_put_contents(MAIL_TRACK_LOG, date('Y-m-d H:i:s') . ' | ' . $msg . "\n", FILE_APPEND);
}
