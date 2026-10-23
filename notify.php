<?php
/**
 * 统一异步回调接收点
 * 仅用于兼容系统内部回调探测与测试；收到回调后返回 success 即视为成功
 */
header('Content-Type: text/plain; charset=UTF-8');
http_response_code(200);

function __redactCallbackData($data) {
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
            $out[$k] = __redactCallbackData($v);
        } elseif (is_string($v) && strlen($v) > 256) {
            $out[$k] = substr($v, 0, 128) . '...[truncated]';
        } else {
            $out[$k] = $v;
        }
    }
    return $out;
}

$data = array_merge($_GET, $_POST);
@file_put_contents('/tmp/notify_callback_log.log',
    date('Y-m-d H:i:s') . ' | ' . json_encode(__redactCallbackData($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
    FILE_APPEND
);

echo 'success';
exit;
