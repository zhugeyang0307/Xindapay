<?php
/**
 * USDT 充值辅助函数
 * - getUsdtRate():  实时探测优先，失败用上次缓存，再失败用 pay_config 固定值
 * - getUsdtAddress(): 从 pay_config 读取 BEpusdt 配置的收款地址
 */

if (!function_exists('getUsdtRate')) {
    function getUsdtRate() {
        global $DB;
        $cacheFile = '/www/wwwroot/pay.yunzexinda.com/includes/.usdt_rate_cache';

        // 1) 缓存 5 分钟内直接用
        if (file_exists($cacheFile) && time() - filemtime($cacheFile) < 300) {
            $r = floatval(file_get_contents($cacheFile));
            if ($r > 1 && $r < 20) return $r;
        }

        // 2) 实时探测
        $token = getenv('BEPUSDT_TOKEN') ?: 'REPLACE_WITH_BEPUSDT_TOKEN';
        $params = [
            'amount'       => 100,
            'notify_url'   => 'https://pay.yunzexinda.com/telegram/usdt_callback.php',
            'order_id'     => 'RATEPROBE_' . time() . '_' . mt_rand(1000, 9999),
            'redirect_url' => 'https://pay.yunzexinda.com/',
            'trade_type'   => 'usdt.trc20',
        ];
        ksort($params);
        $params['signature'] = md5(urldecode(http_build_query($params)) . $token);

        $ch = curl_init('http://127.0.0.1:8080/api/v1/order/create-transaction');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
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

        // 3) 探测失败，用上次缓存（不判断过期）
        if (file_exists($cacheFile)) {
            $r = floatval(file_get_contents($cacheFile));
            if ($r > 1 && $r < 20) return $r;
        }

        // 4) 最后兜底
        $fixed = floatval($DB->getColumn("SELECT v FROM pay_config WHERE k='usdt_rate_fixed'"));
        return $fixed > 0 ? $fixed : 7.2;
    }
}

if (!function_exists('getUsdtAddress')) {
    function getUsdtAddress() {
        global $DB;
        for ($i = 1; $i <= 3; $i++) {
            $k = ($i === 1) ? 'usdt_address' : "usdt_address_{$i}";
            $v = trim((string)$DB->getColumn("SELECT v FROM pay_config WHERE k=?", [$k]));
            if ($v !== '') return $v;
        }
        return '';
    }
}
