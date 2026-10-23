<?php
function tgQueuePush($uid, $trade_no, $order, $botToken = '', $target = '') {
    if (!class_exists('Redis')) return false;
    try {
        $r = new Redis();
        $r->connect('127.0.0.1', 6379, 1.0);
        $r->select(1);
        $job = json_encode([
            'uid'       => $uid,
            'trade_no'  => $trade_no,
            'order'     => $order,
            'bot_token' => $botToken,
            'target'    => $target,
            'enqueue_at'=> time(),
        ], JSON_UNESCAPED_UNICODE);
        $r->lPush('xinda:tg:queue', $job);
        $r->close();
        return true;
    } catch (Throwable $e) {
        @file_put_contents('/tmp/tg_queue_err.log',
            date('H:i:s').' enqueue fail: '.$e->getMessage()."\n", FILE_APPEND);
        return false;
    }
}
