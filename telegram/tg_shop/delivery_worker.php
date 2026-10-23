<?php
// 发货 worker：锁卡 → 发 TG → 标记已售。可 HTTP 触发，也可 cron 重试。
$trade_no = $_GET['trade_no'] ?? ($argv[1] ?? '');
if (!$trade_no) { http_response_code(400); exit('no trade_no'); }

if (php_sapi_name() !== 'cli' && function_exists('fastcgi_finish_request')) {
    echo 'ok';
    fastcgi_finish_request();
}

if (file_exists(__DIR__ . '/../config.php')) {
    @require_once __DIR__ . '/../config.php';
}
require_once __DIR__ . '/../lib/DB.php';

$logf = __DIR__ . '/worker.log';
$log = function($msg) use ($logf) {
    file_put_contents($logf, date('Y-m-d H:i:s') . " | $msg\n", FILE_APPEND);
};

function releaseCards($ids, $trade_no) {
    if (empty($ids)) return;
    $in = implode(',', array_fill(0, count($ids), '?'));
    DB::exec("UPDATE tg_cards SET status=0, order_no=NULL, buyer_tg_id=NULL, locked_at=NULL, updated_at=NOW() WHERE id IN ($in) AND order_no=? AND status=1", array_merge($ids, [$trade_no]));
}

function notifyAdmin($msg) {
    $token = defined('BOT_TOKEN') ? BOT_TOKEN : '';
    $admin = defined('ADMIN_ID') ? ADMIN_ID : 7811967320;
    if (!$token) return;
    $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['chat_id' => $admin, 'text' => $msg, 'parse_mode' => 'HTML']),
        CURLOPT_TIMEOUT => 3, CURLOPT_SSL_VERIFYPEER => false,
    ]);
    curl_exec($ch); curl_close($ch);
}

$pdo = DB::conn();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$ids = [];
try {
    $q = DB::one("SELECT * FROM tg_delivery_queue WHERE trade_no=? AND status IN (0,3) LIMIT 1", [$trade_no]);
    if (!$q) { $log("queue not found or done: $trade_no"); exit; }

    DB::exec("UPDATE tg_delivery_queue SET status=1, updated_at=NOW() WHERE id=? AND status IN (0,3)", [$q['id']]);
    $log("processing: $trade_no");

    $pdo->beginTransaction();
    $cards = DB::query(
        "SELECT id, card_no, card_secret FROM tg_cards
         WHERE goods_id=? AND status=0 AND (expire_at IS NULL OR expire_at>NOW())
         ORDER BY id ASC LIMIT " . (int)$q['quantity'] . " FOR UPDATE",
        [$q['goods_id']]
    );
    if (count($cards) < (int)$q['quantity']) {
        $pdo->rollBack();
        DB::exec("UPDATE tg_delivery_queue SET status=3, retry_count=retry_count+1, next_retry_at=DATE_ADD(NOW(), INTERVAL 5 MINUTE), last_error='out of stock', updated_at=NOW() WHERE id=?", [$q['id']]);
        $log("out of stock: $trade_no goods={$q['goods_id']}");
        notifyAdmin("❌ 卡密库存不足\n订单：{$trade_no}\n商品ID：{$q['goods_id']}\n买家：{$q['buyer_tg_id']}");
        exit;
    }
    $ids = array_column($cards, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    DB::exec("UPDATE tg_cards SET status=1, order_no=?, buyer_tg_id=?, locked_at=NOW(), updated_at=NOW() WHERE id IN ($in) AND status=0", array_merge([$trade_no, $q['buyer_tg_id']], $ids));
    $pdo->commit();
    $log("locked " . count($ids) . " cards for $trade_no");

    $goods = DB::one("SELECT name FROM tg_goods WHERE id=?", [$q['goods_id']]);
    $goodsName = $goods['name'] ?? '商品';
    $cardsText = '';
    foreach ($cards as $i => $c) {
        $cardsText .= ($i+1) . ". <code>{$c['card_secret']}</code>\n";
    }
    $msg = "🎉 <b>购买成功，自动发货</b>\n"
         . "━━━━━━━━━━━━━━━━━━\n"
         . "📦 商品：{$goodsName}\n"
         . "🔢 数量：{$q['quantity']}\n"
         . "🔑 卡密：\n{$cardsText}"
         . "━━━━━━━━━━━━━━━━━━\n"
         . "💡 请妥善保管！";

    $token = '';
    if (!empty($q['bot_id'])) {
        $b = DB::one("SELECT token FROM tg_bots WHERE id=? AND status=1", [$q['bot_id']]);
        $token = $b['token'] ?? '';
    }
    if (!$token) {
        $order = DB::one("SELECT uid FROM pay_order WHERE trade_no=?", [$trade_no]);
        if ($order) {
            $bc = DB::one("SELECT own_bot_token FROM pay_user_botconfig WHERE uid=? AND use_own_bot=1", [$order['uid']]);
            $token = $bc['own_bot_token'] ?? '';
        }
    }
    if (!$token && defined('BOT_TOKEN')) $token = BOT_TOKEN;

    if (!$token) {
        releaseCards($ids, $trade_no);
        DB::exec("UPDATE tg_delivery_queue SET status=3, retry_count=retry_count+1, next_retry_at=DATE_ADD(NOW(), INTERVAL 5 MINUTE), last_error='no bot token', updated_at=NOW() WHERE id=?", [$q['id']]);
        $log("no bot token: $trade_no");
        exit;
    }

    $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['chat_id' => $q['buyer_tg_id'], 'text' => $msg, 'parse_mode' => 'HTML']),
        CURLOPT_TIMEOUT => 5, CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    $j = json_decode($resp, true);

    if (!empty($j['ok'])) {
        DB::exec("UPDATE tg_cards SET status=2, sold_at=NOW(), updated_at=NOW() WHERE id IN ($in) AND order_no=? AND status=1", array_merge($ids, [$trade_no]));
        DB::exec("UPDATE tg_delivery_queue SET status=2, updated_at=NOW() WHERE id=?", [$q['id']]);
        DB::exec("UPDATE tg_goods SET stock=GREATEST(stock-?,0) WHERE id=?", [(int)$q['quantity'], $q['goods_id']]);
        $log("delivered: $trade_no cards=" . implode(',', $ids));
    } else {
        releaseCards($ids, $trade_no);
        $em = mb_substr($err ?: ($j['description'] ?? 'unknown'), 0, 500);
        DB::exec("UPDATE tg_delivery_queue SET status=3, retry_count=retry_count+1, next_retry_at=DATE_ADD(NOW(), INTERVAL 5 MINUTE), last_error=?, updated_at=NOW() WHERE id=?", [$em, $q['id']]);
        $log("send fail: $trade_no err=$em");
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    releaseCards($ids, $trade_no);
    $em = mb_substr($e->getMessage(), 0, 500);
    DB::exec("UPDATE tg_delivery_queue SET status=3, last_error=?, updated_at=NOW() WHERE trade_no=? AND status IN (0,1,3)", [$em, $trade_no]);
    $log("exception: $trade_no $em");
}
