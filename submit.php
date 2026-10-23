<?php

// ===== 浏览器直连智能跳转（防 JSON 乱码） =====
if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Mozilla') !== false) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $url = $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, 
        CURLOPT_TIMEOUT => 10, 
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'XindaPay-Internal-Curl'
    ]);
    $json = curl_exec($ch);
    curl_close($ch);
    
    if (preg_match('/"payData":"(.*?)"/i', $json, $m)) {
        $payUrl = str_replace('\\/', '/', $m[1]);
        header('Location: ' . $payUrl);
        exit;
    }
    exit($json);
}
// ==========================================

if(!isset($_GET['pid']) && !isset($_POST['pid'])){
@header('Content-Type: text/html; charset=UTF-8');
exit('你还未配置支付接口商户！');
}
$nosession = true;
define('API_JSON_MODE', true);
function __safeLogData($data) {
    if (!is_array($data)) return ['raw' => 'non-array'];
    $redactKeys = ['key','sign','signature','token','secret','password','passwd','appkey','authorization','cookie','set-cookie'];
    $out = [];
    foreach ($data as $k => $v) {
        $lk = strtolower((string)$k);
        if (in_array($lk, $redactKeys, true)) {
            $out[$k] = '[REDACTED]';
            continue;
        }
        if (is_array($v)) {
            $out[$k] = __safeLogData($v);
        } elseif (is_string($v) && strlen($v) > 256) {
            $out[$k] = substr($v, 0, 128) . '...[truncated]';
        } else {
            $out[$k] = $v;
        }
    }
    return $out;
}
@file_put_contents('/tmp/submit_debug.log', date('Y-m-d H:i:s').' | '.json_encode(__safeLogData($_POST ?: $_GET), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL, FILE_APPEND);
require './includes/common.php';

// 开启输出缓存，截获插件的输出
ob_start();
try {
    (new \lib\api\Pay())->submit();
} catch (Exception $e) {
    ob_end_clean();
    throw $e;
}
$__output = ob_get_clean();

// 智能拦截：如果输出了包含 payData 的 JSON，直接跳转
if (strpos($__output, '"payDataType":"payUrl"') !== false && preg_match('/"payData":"(.*?)"/i', $__output, $__m)) {
    $__url = str_replace('\/', '/', $__m[1]);
    header('Location: ' . $__url);
    exit;
}

// 否则原样输出
echo $__output;
