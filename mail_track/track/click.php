<?php
require_once __DIR__ . '/../config.php';

$token = trim($_GET['t'] ?? '');
$url   = base64_decode($_GET['url'] ?? '');

if (!$token || !$url) {
    http_response_code(400);
    exit('bad request');
}

// 安全检查
if (!preg_match('#^https?://#i', $url)) {
    http_response_code(400);
    exit('invalid url');
}

try {
    $row = DB::one("SELECT * FROM pay_mail_recipient WHERE token=? LIMIT 1", [$token]);
    if ($row) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $ip = trim(explode(',', $ip)[0]);
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

        DB::exec("INSERT INTO pay_mail_event (campaign_id, recipient_id, token, event_type, ip, user_agent, target_url) 
                  VALUES (?,?,?,'click',?,?,?)",
            [$row['campaign_id'], $row['id'], $token, $ip, $ua, substr($url, 0, 512)]);

        DB::exec("UPDATE pay_mail_recipient SET 
                    click_count=click_count+1,
                    first_click_at=COALESCE(first_click_at, NOW()),
                    last_click_at=NOW()
                  WHERE id=?", [$row['id']]);

        DB::exec("UPDATE pay_mail_campaign SET click_count=click_count+1 WHERE id=?", [$row['campaign_id']]);
    }
} catch (Exception $e) {
    mailTrackLog('click error: ' . $e->getMessage());
}

// 追加 mt_rid 到目标 URL（用于落地页追踪）
$__sep = (strpos($url, '?') === false) ? '?' : '&';
$__redirectUrl = $url . $__sep . 'mt_rid=' . $token;
header('Location: ' . $__redirectUrl, true, 302);
exit;