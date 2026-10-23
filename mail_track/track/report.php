<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);
if (!is_array($in)) $in = $_REQUEST;

$rid  = preg_replace('/[^a-f0-9]/i', '', (string)($in['rid'] ?? ''));
$type = (string)($in['type'] ?? '');
$name = substr(trim((string)($in['name'] ?? '')), 0, 32);
$data = $in['data'] ?? [];

if (!$rid || !in_array($type, ['landing', 'custom'], true)) {
    echo json_encode(['code' => -1, 'msg' => 'bad params']); exit;
}

try {
    $row = DB::one("SELECT * FROM pay_mail_recipient WHERE token=? LIMIT 1", [$rid]);
    if (!$row) { echo json_encode(['code' => -1, 'msg' => 'rid not found']); exit; }

    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $ip = trim(explode(',', $ip)[0]);
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    DB::exec("INSERT INTO pay_mail_event
              (campaign_id, recipient_id, token, event_type, event_name, event_data, ip, user_agent)
              VALUES (?,?,?,?,?,?,?,?)",
        [
            $row['campaign_id'], $row['id'], $rid, $type,
            $name ?: null,
            is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : null,
            $ip, $ua
        ]);

    // 更新收件人计数（landing 算 open？不，独立统计）
    if ($type === 'custom') {
        // 扩展计数可放这
    }

    echo json_encode(['code' => 0]); exit;
} catch (\Throwable $e) {
    mailTrackLog('report error: ' . $e->getMessage());
    echo json_encode(['code' => -1, 'msg' => 'internal error']); exit;
}