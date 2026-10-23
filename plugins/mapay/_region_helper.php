<?php
/**
 * mapay 二维码区域匹配 helper
 * 获取用户 IP 省份，用于 pickOne 里按 region_province 过滤
 */

function _mapay_user_province() {
    static $prov = null;
    if ($prov !== null) return $prov;
    $prov = '';

    // 加载 IpLocation（已 init 则跳过）
    if (!class_exists('IpLocation')) {
        $lib = dirname(__DIR__, 2) . '/route/lib/IpLocation.php';
        if (file_exists($lib)) {
            @include_once $lib;
            if (class_exists('IpLocation') && !defined('__IPLOC_INITED')) {
                @IpLocation::init();
                define('__IPLOC_INITED', 1);
            }
        }
    }
    if (!class_exists('IpLocation')) return $prov;

    $ip = $_SERVER['HTTP_X_REAL_IP'] ?? '';
    if (!$ip) {
        $fwd = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        $ip = trim(explode(',', $fwd)[0]);
    }
    if (!$ip) $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!$ip) return $prov;

    $ip = trim((string)$ip);

    try {
        $loc = IpLocation::lookup($ip);
        $raw = $loc['province'] ?? '';
        $prov = preg_replace('/[省市区]$/u', '', $raw);
        if (in_array($prov, ['内网', '海外', ''], true)) $prov = '';
    } catch (Throwable $e) {
        $prov = '';
    }
    return $prov;
}