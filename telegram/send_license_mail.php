<?php
// 独立脚本，用支付系统环境发邮件
$nosession = true;
$_SERVER['HTTP_HOST'] = 'pay.yunzexinda.com';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_SCHEME'] = 'https';
$_SERVER['SERVER_PORT'] = 443;
$_SERVER['HTTPS'] = 'on';

$email = $argv[1] ?? '';
$code = $argv[2] ?? '';
$secret = $argv[3] ?? '';
if (!$email || !$code) exit(1);

chdir('/www/wwwroot/pay.yunzexinda.com/');
require '/www/wwwroot/pay.yunzexinda.com/includes/common.php';
require '/www/wwwroot/pay.yunzexinda.com/telegram/lib/ApkTutorial.php';

$site = $conf['sitename'] ?? '信达网络';
$sub = "{$site} - APK 授权激活码";
$msg = '<h2>APK 授权购买成功</h2>'
     . '感谢您购买收款监听 APK 授权！<br/><br/>'
     . '<b>激活码</b>：<code>' . htmlspecialchars($code) . '</code><br/>'
     . '<b>上报密钥</b>：<code>' . htmlspecialchars($secret) . '</code><br/><br/>'
     . ApkTutorial::mail();

$r = send_mail($email, $sub, $msg);
echo $r === true ? 'ok' : 'fail: ' . $r;
