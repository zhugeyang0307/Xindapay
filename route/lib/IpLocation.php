<?php
/**
 * IP 归属地查询
 *  - 优先：hejunjie/ip138 SDK（需 token，配在 route/config.php）
 *  - 回退：ip-api.com 免费 API（无需 token，本地测试用）
 */
class IpLocation {
    private static $token = '';
    private static $cacheDir = null;
    private static $testMode = false;
    private static $fakeIp = '';

    public static function init($token = '') {
        self::$token = $token;
        self::$cacheDir = __DIR__ . '/../data/ipcache';
        if (!is_dir(self::$cacheDir)) {
            @mkdir(self::$cacheDir, 0755, true);
        }
    }

    public static function setTestIp($ip) {
        self::$testMode = true;
        self::$fakeIp = $ip;
    }

    public static function clearTest() {
        self::$testMode = false;
        self::$fakeIp = '';
    }

    public static function lookup($ip) {
        if (self::$testMode) {
            $ip = self::$fakeIp;
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return ['province'=>'', 'city'=>'', 'isp'=>'', 'raw'=>''];
        }

        if (preg_match('/^(10\.|172\.(1[6-9]|2[0-9]|3[01])\.|192\.168\.|127\.)/', $ip)) {
            return ['province'=>'内网', 'city'=>'', 'isp'=>'', 'raw'=>'内网'];
        }

        $cached = self::_cacheGet($ip);
        if ($cached !== null) return $cached;

        $result = null;

        // 1. IP138（有 token）
        if (self::$token !== '' && class_exists('Hejunjie\\Ip138\\IP138')) {
            $result = self::_queryIp138($ip);
        }

        // 2. 回退 ip-api.com
        if ($result === null) {
            $result = self::_queryIpApi($ip);
        }

        self::_cacheSet($ip, $result);
        return $result;
    }

    private static function _queryIp138($ip) {
        try {
            $cls = 'Hejunjie\\Ip138\\IP138';
            $client = new $cls($cls::URL_DOMESTIC_HTTPS, ['ipdata' => self::$token]);
            $resp = $client->ipLookup($ip);
            if (($resp['ret'] ?? '') !== 'ok' || empty($resp['data'])) return null;
            $d = $resp['data'];
            return [
                'province' => $d[1] ?? '',
                'city'     => $d[2] ?? '',
                'isp'      => $d[4] ?? '',
                'raw'      => implode('|', array_slice($d, 0, 5)),
            ];
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function _queryIpApi($ip) {
        $url = "http://ip-api.com/json/{$ip}?lang=zh-CN&fields=status,country,regionName,city,isp,query";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || !$resp) return ['province'=>'', 'city'=>'', 'isp'=>'', 'raw'=>''];
        $data = json_decode($resp, true);
        if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
            return ['province'=>'', 'city'=>'', 'isp'=>'', 'raw'=>''];
        }
        return [
            'province' => $data['regionName'] ?? '',
            'city'     => $data['city'] ?? '',
            'isp'      => $data['isp'] ?? '',
            'raw'      => ($data['country'] ?? '') . '|' . ($data['regionName'] ?? '') . '|' . ($data['city'] ?? '') . '|' . ($data['isp'] ?? ''),
        ];
    }

    private static function _cacheGet($ip) {
        $file = self::$cacheDir . '/' . md5($ip) . '.json';
        if (!file_exists($file)) return null;
        if (time() - filemtime($file) > 86400 * 7) {
            @unlink($file);
            return null;
        }
        $data = @json_decode(@file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    private static function _cacheSet($ip, $data) {
        $file = self::$cacheDir . '/' . md5($ip) . '.json';
        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}