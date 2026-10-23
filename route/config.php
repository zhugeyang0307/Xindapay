<?php
require_once dirname(__DIR__) . '/config.php';
if (!class_exists('DB')) {
    require_once dirname(__DIR__) . '/telegram/lib/DB.php';
}
require_once dirname(__DIR__) . '/vendor/autoload.php';

// IP138 token（去 https://user.ip138.com/ 申请后填此处；留空则回退 ip-api.com 免费 API）
define('IP138_TOKEN', '');

// 路由后台密码（线上强密码）
define('ROUTE_ADMIN_PASS', getenv('ROUTE_ADMIN_PASS') ?: 'REPLACE_WITH_ROUTE_ADMIN_PASS');

// 智能路由总开关（紧急情况一键关闭：改成 false）
define('ROUTE_HOOK_ENABLED', true);

// 是否写路由日志（量大可关）
define('ROUTE_LOG_ENABLED', true);

// 调试开关（线上必须 false）
define('ROUTE_DEBUG', false);

require_once __DIR__ . '/lib/IpLocation.php';
IpLocation::init(IP138_TOKEN);

require_once __DIR__ . '/lib/RouteRule.php';
require_once __DIR__ . '/lib/IpRoute.php';

define('ROUTE_LOG', __DIR__ . '/logs/route.log');
function routeLog($msg) {
    @file_put_contents(ROUTE_LOG, date('Y-m-d H:i:s') . ' | ' . $msg . "\n", FILE_APPEND);
}