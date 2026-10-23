<?php
/**
 * TRON 地址监听
 * 每 1 分钟执行一次，检测新交易并推送
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/DB.php';
require_once __DIR__ . '/../lib/Telegram.php';

date_default_timezone_set('Asia/Shanghai');

$USDT_CONTRACT = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';

function curl_get($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0',
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    return $r;
}

$watches = DB::query("SELECT * FROM pay_tronwatch WHERE status=1");
if (!$watches) {
    echo "无监听地址\n";
    exit;
}

foreach ($watches as $w) {
    $addr = $w['address'];
    $lastTxId = $w['last_tx_id'];

    $url = "https://apilist.tronscanapi.com/api/token_trc20/transfers"
         . "?limit=5&start=0&sort=-timestamp"
         . "&relatedAddress={$addr}"
         . "&contract_address={$USDT_CONTRACT}";

    $r = curl_get($url);
    $j = json_decode($r, true);
    $txs = $j['token_transfers'] ?? [];

    if (empty($txs)) continue;

    $newTxs = [];
    foreach ($txs as $tx) {
        $txid = $tx['transaction_id'] ?? '';
        if ($txid === $lastTxId) break;
        $newTxs[] = $tx;
    }

    // 第一次监听：只记录最新 txid，不推送
    if (empty($lastTxId)) {
        DB::exec("UPDATE pay_tronwatch SET last_tx_id=? WHERE id=?", [$txs[0]['transaction_id'], $w['id']]);
        echo "[初始化] {$addr} 记录最新 txid\n";
        continue;
    }

    // 有新交易
    foreach (array_reverse($newTxs) as $tx) {
        $dec = intval($tx['tokenInfo']['tokenDecimal'] ?? 6);
        $amt = floatval($tx['quant'] ?? 0) / pow(10, $dec);
        $toAddr = strtolower($tx['to_address'] ?? '');
        $isIn = ($toAddr === strtolower($addr));
        $flag = $isIn ? '⬇️ 转入' : '⬆️ 转出';
        $peer = $isIn ? ($tx['from_address'] ?? '') : ($tx['to_address'] ?? '');
        $txid = $tx['transaction_id'] ?? '';
        $ts = intval($tx['block_ts'] ?? 0) / 1000;
        $link = "https://tronscan.org/#/transaction/{$txid}";

        // 低于最小金额不推
        if ($amt < floatval($w['min_amount'])) continue;

        $text = "🚨 <b>地址监控通知</b>\n"
              . "━━━━━━━━━━━━━━━━━━\n"
              . "地址：<code>{$addr}</code>\n"
              . ($w['remark'] ? "备注：{$w['remark']}\n" : "")
              . "━━━━━━━━━━━━━━━━━━\n"
              . "{$flag} <b>" . number_format($amt, 2) . " USDT</b>\n"
              . "对方：<code>" . substr($peer, 0, 8) . ".." . substr($peer, -6) . "</code>\n"
              . "时间：" . date('Y-m-d H:i:s', $ts + 8 * 3600) . "\n"
              . "━━━━━━━━━━━━━━━━━━\n"
              . "🔗 <a href=\"{$link}\">查看交易详情</a>";

        Telegram::sendMessage($w['chat_id'], $text);
        echo "推送：{$addr} {$amt} USDT\n";
    }

    // 更新 last_tx_id
    if (!empty($txs)) {
        DB::exec("UPDATE pay_tronwatch SET last_tx_id=? WHERE id=?", [$txs[0]['transaction_id'], $w['id']]);
    }
}

echo "扫描完成\n";
