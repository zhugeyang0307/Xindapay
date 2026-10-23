<?php
// 创建 APK 授权订单，返回 BEpusdt 支付链接
$is_defend = true;
include("../includes/common.php");
header('Content-Type: application/json; charset=UTF-8');

if($islogin2 != 1) exit(json_encode(['code'=>-1, 'msg'=>'请先登录']));

$uid = (int)$userrow['uid'];

// ========== 领取激活码（认证通过 + 服务期有效 + 额度未满） ==========
$act = $_POST['act'] ?? $_GET['act'] ?? '';
if ($act === 'claim') {
    if (empty($userrow['cert'])) {
        exit(json_encode(['code'=>-1, 'msg'=>'请先完成实名认证后再领取激活码']));
    }
    if (!\lib\Service::isActive($uid)) {
        exit(json_encode(['code'=>-1, 'msg'=>'系统服务已到期，请前往「系统服务」续费后再领取']));
    }
    // APK 额度限制已彻底解除，全站免费无限使用
    $code   = 'LIC-' . strtoupper(bin2hex(random_bytes(5)));
    $secret = bin2hex(random_bytes(16));
    if (!$DB->exec("INSERT INTO pay_license (code, uid, secret, status, addtime) VALUES (:c, :u, :s, 0, NOW())",
        [':c'=>$code, ':u'=>$uid, ':s'=>$secret])) {
        exit(json_encode(['code'=>-1, 'msg'=>'生成激活码失败']));
    }
    exit(json_encode(['code'=>1, 'msg'=>'领取成功', 'data'=>['code'=>$code, 'secret'=>$secret]]));
}

$recvType = $_POST['receive_type'] ?? 'tg';
$recvValue = trim($_POST['receive_value'] ?? '');

if (!in_array($recvType, ['tg', 'email'])) exit(json_encode(['code'=>-1, 'msg'=>'接收方式错误']));
if ($recvValue === '') exit(json_encode(['code'=>-1, 'msg'=>'请填写接收账号']));

// 简单校验
if ($recvType === 'email' && !filter_var($recvValue, FILTER_VALIDATE_EMAIL)) {
    exit(json_encode(['code'=>-1, 'msg'=>'邮箱格式不正确']));
}
if ($recvType === 'tg' && !is_numeric($recvValue)) {
    exit(json_encode(['code'=>-1, 'msg'=>'TG ID 必须是数字']));
}

// ========== 1. 读价格 ==========
$price = (float)$DB->getColumn("SELECT v FROM pay_config WHERE k='license_price_usdt'");
if ($price <= 0) $price = 50;
$days = (int)$DB->getColumn("SELECT v FROM pay_config WHERE k='license_days'");
if ($days <= 0) $days = 30;

// ========== 2. 生成订单号 ==========
$trade_no = 'APK' . date('YmdHis') . mt_rand(1000, 9999);

// 查实时汇率（CNY/USDT），让用户实际付 $price USDT
$cacheFile = '/www/wwwroot/pay.yunzexinda.com/telegram/.rate_cache';
$rate = 0;
$token = getenv('BEPUSDT_TOKEN') ?: 'REPLACE_WITH_BEPUSDT_TOKEN';
$apiUrl = getenv('BEPUSDT_API_URL') ?: 'http://127.0.0.1:8080';
if (file_exists($cacheFile) && time() - filemtime($cacheFile) < 300) {
    $rate = floatval(file_get_contents($cacheFile));
}
if ($rate <= 0) {
    // 兜底：调 Bepusdt 探测
    $probeOrder = 'PROBE' . time();
    $probeParams = [
        'amount' => 100,
        'notify_url' => 'https://pay.yunzexinda.com/telegram/usdt_callback.php',
        'order_id' => $probeOrder,
        'redirect_url' => 'https://pay.yunzexinda.com/',
        'trade_type' => 'usdt.trc20',
    ];
    if (!empty($usdtAddr)) $probeParams['address'] = $usdtAddr;
    ksort($probeParams);
    $probeBase = urldecode(http_build_query($probeParams)) . $token;
    $probeParams['signature'] = md5($probeBase);
    $probeJson = json_encode($probeParams, JSON_UNESCAPED_UNICODE);
    $ch2 = curl_init($apiUrl . '/api/v1/order/create-transaction');
    curl_setopt_array($ch2, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$probeJson, CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10]);
    $probeResp = json_decode(curl_exec($ch2), true);
    curl_close($ch2);
    if (!empty($probeResp['data']['actual_amount'])) {
        $rate = 100 / floatval($probeResp['data']['actual_amount']);
        @file_put_contents($cacheFile, (string)$rate);
    }
}
if ($rate <= 0) $rate = 6.68;
$amountCny = round($price * $rate, 2);

// ========== 3. 写订单 ==========
if (!$DB->exec("INSERT INTO pay_apk_order (uid, trade_no, amount, days, status, pay_type, receive_type, receive_value) VALUES (:u, :t, :a, :d, 0, 'usdt', :rt, :rv)",
    [':u'=>$uid, ':t'=>$trade_no, ':a'=>$price, ':d'=>$days, ':rt'=>$recvType, ':rv'=>$recvValue])) {
    exit(json_encode(['code'=>-1, 'msg'=>'创建订单失败']));
}

// ========== 4. 调 BEpusdt 创建支付订单 ==========
// 读收款地址
$usdtAddr = $DB->getColumn("SELECT v FROM pay_config WHERE k='usdt_address'");

$token = getenv('BEPUSDT_TOKEN') ?: 'REPLACE_WITH_BEPUSDT_TOKEN';
$apiUrl = getenv('BEPUSDT_API_URL') ?: 'http://127.0.0.1:8080';
$notify = 'https://pay.yunzexinda.com/telegram/usdt_callback.php';
$redirect = 'https://pay.yunzexinda.com/user/license.php';
$trade_type = 'usdt.trc20';

$params = [
    'amount'       => $amountCny,
    'notify_url'   => $notify,
    'order_id'     => $trade_no,
    'redirect_url' => $redirect,
    'trade_type'   => $trade_type,
];
if ($usdtAddr) $params['address'] = $usdtAddr;
ksort($params);
$base = urldecode(http_build_query($params)) . $token;
$params['signature'] = md5($base);
$json = json_encode($params, JSON_UNESCAPED_UNICODE);

$ch = curl_init($apiUrl . '/api/v1/order/create-transaction');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $json,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
]);
$resp = curl_exec($ch);
curl_close($ch);
$r = json_decode($resp, true);

if (empty($r['data']['payment_url'])) {
    exit(json_encode(['code'=>-1, 'msg'=>'创建支付链接失败: ' . ($r['message'] ?? '未知错误')]));
}

// 替换内网地址为外网
$payUrl = str_replace(
    ['http://127.0.0.1:8080', 'http://1.14.68.193:8080'],
    'https://usdt.yunzexinda.com',
    $r['data']['payment_url']
);

exit(json_encode([
    'code' => 1,
    'msg'  => 'ok',
    'trade_no' => $trade_no,
    'price' => $price,
    'days' => $days,
    'pay_url' => $payUrl,
]));
