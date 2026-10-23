<?php
/**
 * USDT 充值到账监听
 * 每分钟执行一次：php /www/wwwroot/pay.yunzexinda.com/cron_usdt.php
 */
require_once __DIR__ . '/includes/common.php';
date_default_timezone_set('Asia/Shanghai');

$USDT_CONTRACT = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';

function get_tron_txs($addr){
    global $USDT_CONTRACT;
    $url = "https://apilist.tronscanapi.com/api/token_trc20/transfers"
         . "?limit=20&start=0&sort=-timestamp"
         . "&relatedAddress={$addr}"
         . "&contract_address={$USDT_CONTRACT}";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0',
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    return json_decode($r, true);
}

// 1. 标记过期订单
$DB->exec("UPDATE pay_usdt_recharge SET status=2 WHERE status=0 AND expire_time < NOW()");

// 2. 拿所有地址
$addrs = [];
foreach(['usdt_address','usdt_address_2','usdt_address_3'] as $k){
    if(!empty($conf[$k])) $addrs[] = trim($conf[$k]);
}
if(empty($addrs)){
    echo "未配置USDT地址\n";
    exit;
}

// 3. 拉取每笔待支付订单
$orders = $DB->getAll("SELECT * FROM pay_usdt_recharge WHERE status=0");

foreach($addrs as $addr){
    $j = get_tron_txs($addr);
    $txs = $j['token_transfers'] ?? [];
    if(empty($txs)) continue;

    foreach($txs as $tx){
        // 只处理转入
        if(strtolower($tx['to_address'] ?? '') !== strtolower($addr)) continue;
        
        $txid = $tx['transaction_id'] ?? '';
        $dec = intval($tx['tokenInfo']['tokenDecimal'] ?? 6);
        $amt = floatval($tx['quant'] ?? 0) / pow(10, $dec);
        $ts = intval($tx['block_ts'] ?? 0) / 1000;
        
        // 匹配订单：金额 ±0.5% 容差，时间在订单创建后
        foreach($orders as $o){
            if($o['address'] !== $addr) continue;
            if($txid === $o['txid']) continue; // 已处理过
            $diff = abs($amt - $o['amount_usdt']);
            $tolerance = $o['amount_usdt'] * 0.005; // 0.5% 容差
            if($diff <= $tolerance && $ts >= strtotime($o['addtime'])){
                // 匹配成功
                $DB->exec("UPDATE pay_usdt_recharge SET status=1, txid=?, paytime=NOW() WHERE id=?", [$txid, $o['id']]);
                changeUserMoney($o['uid'], $o['amount_cny'], true, 'USDT充值', $o['trade_no']);
                echo "✅ 到账：UID {$o['uid']} +¥{$o['amount_cny']} (TX: {$txid})\n";
                // 从订单列表移除，避免重复匹配
                break;
            }
        }
    }
}

echo "扫描完成 " . date('Y-m-d H:i:s') . "\n";