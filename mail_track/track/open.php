<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// 1x1 透明 GIF
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

$token = trim($_GET['t'] ?? '');
if (!$token) exit;

try {
    $row = DB::one("SELECT * FROM pay_mail_recipient WHERE token=? LIMIT 1", [$token]);
    if (!$row) exit;

    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $ip = trim(explode(',', $ip)[0]);
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    DB::exec("INSERT INTO pay_mail_event (campaign_id, recipient_id, token, event_type, ip, user_agent) 
              VALUES (?,?,?,'open',?,?)",
        [$row['campaign_id'], $row['id'], $token, $ip, $ua]);

    DB::exec("UPDATE pay_mail_recipient SET 
                open_count=open_count+1,
                first_open_at=COALESCE(first_open_at, NOW()),
                last_open_at=NOW()
              WHERE id=?", [$row['id']]);

    DB::exec("UPDATE pay_mail_campaign SET open_count=open_count+1 WHERE id=?", [$row['campaign_id']]);
} catch (Exception $e) {
    mailTrackLog('open error: ' . $e->getMessage());
}