<?php
require_once('../../includes/common.php');

// 监听 App 需要带上密钥
$key = $_GET['key'] ?? $_POST['key'] ?? '';

// 🔥 修复：从 config 字段中解析 appkey
$channel = $DB->getRow("SELECT config FROM pay_channel WHERE plugin='mapay' AND status=1 LIMIT 1");
$config = json_decode($channel['config'] ?? '', true) ?: [];
$appkey = $config['appkey'] ?? '';

if(empty($appkey) || $key !== $appkey) {
    exit('forbidden');
}

$amount = floatval($_POST['amount'] ?? 0);
$time   = $_POST['time'] ?? date('Y-m-d H:i:s');
$raw    = $_POST['raw'] ?? '';

file_put_contents(__DIR__.'/notify.log', date('Y-m-d H:i:s')." 收到 ¥{$amount} | {$raw}\n", FILE_APPEND);

if($amount <= 0) exit('invalid amount');

// 匹配最近 10 分钟内金额相同的待支付订单
$order = $DB->getRow("SELECT * FROM pay_order
    WHERE status=0 AND realmoney=:m
    AND addtime > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    ORDER BY addtime DESC LIMIT 1", [':m'=>$amount]);

if(!$order) {
    file_put_contents(__DIR__.'/notify.log', "  ⚠️ 无匹配订单\n", FILE_APPEND);
    exit('no match');
}

// 完成订单
\lib\Order::complete($order['trade_no'], $amount);
file_put_contents(__DIR__.'/notify.log', "  ✅ 完成订单 {$order['trade_no']}\n", FILE_APPEND);
echo 'success';
