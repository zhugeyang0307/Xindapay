<?php
require_once __DIR__ . '/../config.php';

class Bepusdt {

    // 探测 BEpusdt 实时汇率（CNY/USDT）
    // 创建 100 CNY 的探测订单，从返回 JSON 的 actual_amount 反推汇率
    // 缓存 5 分钟
    public static function getRealtimeRate() {
        $cacheFile = __DIR__ . '/../.rate_cache';
        if (file_exists($cacheFile) && time() - filemtime($cacheFile) < 300) {
            $r = floatval(file_get_contents($cacheFile));
            if ($r > 1 && $r < 20) return $r;
        }

        $orderId = 'RATEPROBE_' . time() . '_' . mt_rand(1000, 9999);
        $params = [
            'amount'       => 100,
            'notify_url'   => BEPUSDT_NOTIFY,
            'order_id'     => $orderId,
            'redirect_url' => BEPUSDT_REDIRECT,
            'trade_type'   => 'usdt.trc20',
        ];
        ksort($params);
        $base = urldecode(http_build_query($params)) . BEPUSDT_TOKEN;
        $params['signature'] = md5($base);
        $json = json_encode($params, JSON_UNESCAPED_UNICODE);

        $ch = curl_init(BEPUSDT_API_URL . '/api/v1/order/create-transaction');
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

        $usdt = floatval($r['data']['actual_amount'] ?? 0);
        if ($usdt > 0) {
            $rate = 100 / $usdt;
            @file_put_contents($cacheFile, (string)$rate);
            return $rate;
        }
        return null;
    }

    public static function createOrder($orderId, $amountUsdt, $tradeType = 'usdt.trc20') {
        $rate = self::getRealtimeRate();
        if (!$rate) {
            $rate = defined('REG_RECHARGE_RATE') ? REG_RECHARGE_RATE : 7.2;
            plog("汇率探测失败，使用兜底汇率 {$rate}");
        }
        $amountCny = round($amountUsdt * $rate, 2);

        $params = [
            'amount'       => $amountCny,
            'notify_url'   => BEPUSDT_NOTIFY,
            'order_id'     => $orderId,
            'redirect_url' => BEPUSDT_REDIRECT,
            'trade_type'   => $tradeType,
        ];
        ksort($params);
        $base = urldecode(http_build_query($params)) . BEPUSDT_TOKEN;
        $params['signature'] = md5($base);
        $json = json_encode($params, JSON_UNESCAPED_UNICODE);

        $ch = curl_init(BEPUSDT_API_URL . '/api/v1/order/create-transaction');
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
        plog('Bepusdt createOrder 响应: ' . substr($resp, 0, 800));
        return $r['data']['payment_url'] ?? null;
    }

    public static function verifyCallback($data) {
        $sign = $data['signature'] ?? '';
        unset($data['signature']);
        // 去掉空值字段（BEpusdt 回调验签要求）
        $data = array_filter($data, function($v) { return $v !== '' && $v !== null; });
        ksort($data);
        $base = urldecode(http_build_query($data)) . BEPUSDT_TOKEN;
        return md5($base) === $sign;
    }
}
