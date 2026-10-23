<?php
// TG 机器人内部桥接：真实发送/校验验证码
$nosession = true;
require __DIR__ . '/includes/common.php';
require __DIR__ . '/includes/lib/VerifyCode.php';

// 只允许本机访问
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', '124.156.163.186'])) {
    http_response_code(403); exit('forbidden');
}

// 内部调用密钥（请改掉）
$INTERNAL_KEY = 'a308f099ebf9e18ef1ab03d5137c9a8e07b916b19addf244';
if (($_GET['key'] ?? '') !== $INTERNAL_KEY) {
    http_response_code(403); exit('forbidden');
}

// VerifyCode 依赖 $clientip
if (!isset($clientip)) {
    $clientip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

$action = $_GET['action'] ?? '';
$scene  = $_GET['scene']  ?? 'reg';
$type   = intval($_GET['type'] ?? 0);  // 0=邮件 1=手机
$to     = trim($_GET['to'] ?? '');
$code   = trim($_GET['code'] ?? '');

if ($to === '') { echo '账号不能为空'; exit; }

if ($action === 'send') {
    $r = \lib\VerifyCode::send_code($scene, $type, $to);
    echo $r === true ? 'ok' : $r;
} elseif ($action === 'verify') {
    $r = \lib\VerifyCode::verify_code($scene, $type, $to, $code);
    if ($r === true) {
        \lib\VerifyCode::void_code();
        echo 'ok';
    } else {
        echo $r;
    }
} else {
    echo 'bad action';
}
