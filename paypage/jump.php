<?php
/**
 * 跳转入口（所有 scheme 只在这，前端零暴露）
 */
require_once __DIR__ . '/../includes/common.php';

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$log = function($stage, $detail = '') use ($ip) {
    @file_put_contents('/tmp/anti_scrape.log',
        date('Y-m-d H:i:s') . " | {$stage} | ip={$ip} | {$detail}\n", FILE_APPEND);
};

// ============ 1. UA 全面拦截 ============
$blockedUa = [
    // 命令行/脚本
    'curl', 'wget', 'python', 'java/', 'go-http', 'okhttp', 'postman',
    'insomnia', 'scrapy', 'httpie', 'axios', 'node-fetch', 'libwww',
    // 抓包工具
    'charles', 'fiddler', 'mitmproxy', 'burp', 'proxyman', 'stream',
    'surge', 'quantumult', 'shadowrocket', 'clash', 'v2ray',
    // 模拟器
    'android sdk', 'genymotion', 'bluestacks', 'nox', 'memu',
    'ldplayer', 'mumu', 'emulator',
    // headless / 自动化
    'headless', 'selenium', 'puppeteer', 'playwright', 'phantomjs',
    'nightmare', 'splash',
    // 扫描器
    'sqlmap', 'nikto', 'nmap', 'masscan', 'zgrab', 'wpscan',
    'acunetix', 'nessus', 'openvas', 'dirbuster', 'gobuster', 'nuclei',
    // 采集器
    'mj12bot', 'ahrefs', 'semrush', 'dotbot', 'petalbot', 'bytespider',
];

if (empty($ua)) {
    $log('UA_EMPTY');
    http_response_code(403); exit('forbidden');
}
foreach ($blockedUa as $kw) {
    if (stripos($ua, $kw) !== false) {
        $log('UA_BLOCK', "ua={$ua} kw={$kw}");
        http_response_code(403); exit('forbidden');
    }
}

// ============ 2. Referer 严格校验 ============
$ref = $_SERVER['HTTP_REFERER'] ?? '';
if (strpos($ref, 'https://pay.yunzexinda.com/') !== 0) {
    $log('REF_BLOCK', "ref={$ref}");
    http_response_code(403); exit('forbidden');
}

// ============ 3. IP 频率限制（1分钟最多 10 次）============
$rateFile = '/tmp/jump_rate_' . md5($ip);
$now = time();
$rate = file_exists($rateFile) ? json_decode(file_get_contents($rateFile), true) : [];
$rate = is_array($rate) ? array_filter($rate, function($t) use ($now) { return $t > $now - 60; }) : [];
if (count($rate) >= 10) {
    $log('RATE_BLOCK', 'too_many_requests');
    http_response_code(429); exit('too many requests');
}
$rate[] = $now;
file_put_contents($rateFile, json_encode($rate));

// ============ 4. Token 校验（HMAC + 5分钟 + 一次性）============
$tk = $_GET['tk'] ?? '';
if (empty($tk)) { $log('NO_TOKEN'); http_response_code(403); exit('forbidden'); }

$data = base64_decode($tk);
$parts = $data ? explode('|', $data) : [];
if (count($parts) !== 3) { $log('BAD_TOKEN'); http_response_code(403); exit('forbidden'); }
list($trade_no, $ts, $sign) = $parts;

if (abs(time() - intval($ts)) > 300) {
    $log('TK_EXPIRE', "trade_no={$trade_no}"); http_response_code(403); exit('forbidden');
}
$expected = substr(hash_hmac('sha256', $trade_no . '|' . $ts, SYS_KEY), 0, 16);
if (!hash_equals($expected, $sign)) {
    $log('TK_SIGN', "trade_no={$trade_no}"); http_response_code(403); exit('forbidden');
}

// ============ 5. 一次性：用完立即失效 ============
$usedFile = '/tmp/jump_used_' . md5($trade_no . '|' . $ts);
if (file_exists($usedFile)) {
    $log('TK_REPLAY', "trade_no={$trade_no}"); http_response_code(403); exit('forbidden');
}
file_put_contents($usedFile, $now);
@unlink($usedFile);  // 5 分钟后自清
@file_put_contents($usedFile . '.auto', $now);  // 保留标记

// ============ 6. 查订单 ============
$order = $DB->getRow("SELECT * FROM pay_order WHERE trade_no=? LIMIT 1", [$trade_no]);
if (!$order) { $log('NO_ORDER', "trade_no={$trade_no}"); http_response_code(404); exit(); }
if (intval($order['status']) !== 0) { http_response_code(410); exit(); }

$ext = json_decode($order['ext'], true) ?: [];
$qid = intval($ext['qrcode_id'] ?? 0);
$qr = $qid ? $DB->getRow("SELECT qrcode_content FROM pay_qrcode WHERE id=?", [$qid]) : null;
$content = trim($qr['qrcode_content'] ?? '');

// ============ 7. 生成 scheme（核心秘方）============
$type = intval($order['type']);
$enc = urlencode($content);
$scheme = '';

if ($type == 9 || (isset($order['type']) && $order['type'] == 9)) {
    // USDT(type 9) 直接跳转
    if (empty($content)) {
        echo "<script>alert('未获取到支付链接');history.back();</script>";
        exit;
    }
    $scheme = $content;
} elseif ($type == 1) {
    // 支付宝
    if (preg_match('#^alipays?://#i', $content)) {
        $scheme = $content;
    } elseif (!empty($content)) {
        // 完美秘方：强制包裹进支付宝扫一扫协议，拉起收银台！
        // 区分普通支付宝码和乐刷H5链接
        if (preg_match('#^https?://#i', $content)) {
            $scheme = 'alipayqr://platformapi/startapp?saId=10000007&qrcode=' . urlencode($content);
        } else {
            $scheme = 'alipayqr://platformapi/startapp?saId=10000007&qrcode=' . $enc;
        }
    } else {
        echo "<script>alert('该收款码为纯图片，请长按保存扫码付款');history.back();</script>";
        exit;
    }
} elseif ($type == 2) {
    if (preg_match('#^(wxp|weixin)://#i', $content)) {
        $scheme = $content;
    } elseif (preg_match('#^https?://#i', $content)) {
        $scheme = $content;
    } else {
        $scheme = 'weixin://';
    }
} else {
    echo "TYPE=" . $type; echo "<br>CONTENT=" . $content; echo "<br>SCHEME=" . ($scheme ?? "无"); exit();
}

@file_put_contents('/tmp/jump.log',
    date('Y-m-d H:i:s') . " | OK | {$trade_no} | type={$type} | ip={$ip}\n", FILE_APPEND);

header('Location: ' . $scheme);
exit;
