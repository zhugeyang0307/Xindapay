<?php
// 创建/复用 系统服务续费订单，返回 BEpusdt 支付链接
$is_defend = true;
include("../includes/common.php");
header('Content-Type: application/json; charset=UTF-8');

if($islogin2 != 1) exit(json_encode(['code'=>-1, 'msg'=>'请先登录']));

$uid = (int)$userrow['uid'];

// 读配置
function svc_cfg($k, $def='') {
    global $DB;
    $v = $DB->getColumn("SELECT v FROM pay_config WHERE k=:k", [':k'=>$k]);
    return ($v === null || $v === '') ? $def : $v;
}
$monthPrice = (float)svc_cfg('sys_month_price', 100);
$extraPrice = (float)svc_cfg('sys_extra_device_price', 50);

// ---------- A. 复用老订单重新支付 ----------
$reuse = trim($_POST['reuse'] ?? '');
if ($reuse !== '') {
    $order = $DB->getRow("SELECT * FROM pay_service_order WHERE trade_no=:t AND uid=:u AND status=0 LIMIT 1",
        [':t'=>$reuse, ':u'=>$uid]);
    if (!$order) exit(json_encode(['code'=>-1, 'msg'=>'订单不存在或已支付']));
    $amountUsdt = (float)$order['amount'];
    $trade_no   = $order['trade_no'];
} else {
    // ---------- B. 新建订单 ----------
    $months = (int)($_POST['months'] ?? 1);
    $extra  = (int)($_POST['extra_devices'] ?? 0);
    if ($months < 1 || $months > 120) exit(json_encode(['code'=>-1, 'msg'=>'续费月数错误']));
    if ($extra  < 0 || $extra  > 100) exit(json_encode(['code'=>-1, 'msg'=>'增加设备数错误']));

    $amountUsdt = round($months * $monthPrice + $extra * $extraPrice, 2);
    if ($amountUsdt <= 0) exit(json_encode(['code'=>-1, 'msg'=>'金额错误']));

    $trade_no = 'SVC' . date('YmdHis') . mt_rand(1000, 9999);

    if (!$DB->exec("INSERT INTO pay_service_order (uid, trade_no, months, extra_devices, amount, status, pay_type)
                    VALUES (:u, :t, :m, :e, :a, 0, 'usdt')",
        [':u'=>$uid, ':t'=>$trade_no, ':m'=>$months, ':e'=>$extra, ':a'=>$amountUsdt])) {
        exit(json_encode(['code'=>-1, 'msg'=>'创建订单失败']));
    }
}

// ---------- C. 汇率（复用 telegram/.rate_cache） ----------
$cacheFile = '/www/wwwroot/pay.yunzexinda.com/telegram/.rate_cache';
$rate = 0;
if (file_exists($cacheFile) && time() - filemtime($cacheFile) < 300) {
    $rate = floatval(file_get_contents($cacheFile));
}

$token  = getenv('BEPUSDT_TOKEN') ?: 'REPLACE_WITH_BEPUSDT_TOKEN';
$apiUrl = getenv('BEPUSDT_API_URL') ?: 'http://127.0.0.1:8080';

if ($rate <= 0) {
    // 兜底探测（同 ajax_apk.php 逻辑）
    $probeOrder = 'PROBE' . time();
    $probeParams = [
        'amount' => 100,
        'notify_url' => 'https://pay.yunzexinda.com/telegram/usdt_callback.php',
        'order_id' => $probeOrder,
        'redirect_url' => 'https://pay.yunzexinda.com/user/service.php',
        'trade_type' => 'usdt.trc20',
    ];
    $usdtAddr = $DB->getColumn("SELECT v FROM pay_config WHERE k='usdt_address'");
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

$amountCny = round($amountUsdt * $rate, 2);

// ---------- D. 调 BEpusdt ----------
$usdtAddr = $DB->getColumn("SELECT v FROM pay_config WHERE k='usdt_address'");
$notify   = 'https://pay.yunzexinda.com/telegram/usdt_callback.php';
$redirect = 'https://pay.yunzexinda.com/user/service.php';

$params = [
    'amount'       => $amountCny,
    'notify_url'   => $notify,
    'order_id'     => $trade_no,
    'redirect_url' => $redirect,
    'trade_type'   => 'usdt.trc20',
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

$payUrl = str_replace(
    ['http://127.0.0.1:8080', 'http://1.14.68.193:8080'],
    'https://usdt.yunzexinda.com',
    $r['data']['payment_url']
);

exit(json_encode([
    'code' => 1,
    'msg'  => 'ok',
    'trade_no' => $trade_no,
    'amount' => $amountUsdt,
    'pay_url' => $payUrl,
]));