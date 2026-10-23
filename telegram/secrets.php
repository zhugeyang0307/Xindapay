<?php
/**
 * 集中密钥文件（不应被直接 HTTP 访问）
 * 使用：require_once __DIR__ . '/[相对路径]/telegram/secrets.php';
 * 或直接 require_once __DIR__ . '/secrets.php';（config.php 内）
 * 仅保留示例占位值，实际值应由部署环境注入。
 */
if (PHP_SAPI !== 'cli' && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(404);
    exit('Not Found');
}

if (!defined('SANDBOX_BOT_TOKEN')) {
    define('SANDBOX_BOT_TOKEN', getenv('SANDBOX_BOT_TOKEN') ?: 'REPLACE_WITH_BOT_TOKEN');
}
if (!defined('DEEPSEEK_API_KEY')) {
    define('DEEPSEEK_API_KEY', getenv('DEEPSEEK_API_KEY') ?: 'REPLACE_WITH_DEEPSEEK_API_KEY');
}