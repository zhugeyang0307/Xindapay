<?php
// 发货触发入口：由 hook_order_paid.php 最开头异步 curl
// 只做：查 param → 写队列 → 触发 worker。不碰分账、不碰推送。
$trade_no = $_GET['trade_no'] ?? '';
if (!$trade_no) { http_response_code(400); exit('no trade_no'); }

if (function_exists('fastcgi_finish_request')) {
    echo 'ok';
    fastcgi_finish_request();
}

if (file_exists(__DIR__ . '/../config.php')) {
    @require_once __DIR__ . '/../config.php';
}
require_once __DIR__ . '/../lib/DB.php';

$logf = __DIR__ . '/deliver.log';
$log = function($msg) use ($logf) {
    file_put_contents($logf, date('Y-m-d H:i:s') . " | $msg\n", FILE_APPEND);
};

try {
    $order = DB::one("SELECT trade_no, uid, name, money, realmoney, param, status FROM pay_order WHERE trade_no=?", [$trade_no]);
    if (!$order) { $log("order not found: $trade_no"); exit; }
    if ((int)$order['status'] !== 1) { $log("not paid: $trade_no status={$order['status']}"); exit; }

    $param = [];
    foreach (explode(';', $order['param'] ?? '') as $kv) {
        $p = explode(':', $kv, 2);
        if (count($p) === 2) $param[trim($p[0])] = trim($p[1]);
    }
    $buyerTgId = (int)($param['buyer_tg_id'] ?? 0);
    $goodsId   = (int)($param['goods_id'] ?? 0);
    $botId     = (int)($param['bot_id'] ?? 0);

    if (!$buyerTgId || !$goodsId) {
        $log("no tg param: $trade_no param=" . ($order['param'] ?? ''));
        exit;
    }

    DB::exec(
        "INSERT INTO tg_delivery_queue (trade_no, goods_id, buyer_tg_id, bot_id, quantity, status, created_at, updated_at)
         VALUES (?,?,?,?,1,0,NOW(),NOW())
         ON DUPLICATE KEY UPDATE updated_at=NOW()",
        [$trade_no, $goodsId, $buyerTgId, $botId]
    );
    $log("queued: $trade_no goods=$goodsId buyer=$buyerTgId bot=$botId");

    $workerUrl = 'https://pay.yunzexinda.com/telegram/tg_shop/delivery_worker.php?trade_no=' . urlencode($trade_no);
    $ch = curl_init($workerUrl);
    curl_setopt_array($ch, [
        CURLOPT_TIMEOUT => 1, CURLOPT_NOSIGNAL => 1,
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_HTTPHEADER => ['Host: pay.yunzexinda.com'],
    ]);
    curl_exec($ch);
    curl_close($ch);
} catch (Throwable $e) {
    $log("error: " . $e->getMessage());
}
