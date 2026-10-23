<?php
require_once __DIR__ . '/../config.php';

class MailTrack {

    // 创建活动（uid=0 表示平台活动）
    public static function createCampaign($uid, $name, $subject, $bodyHtml, $fromEmail, $fromName = '') {
        DB::exec("INSERT INTO pay_mail_campaign (uid, name, subject, body_html, from_email, from_name, created_at)
                  VALUES (?,?,?,?,?,?,NOW())",
            [(int)$uid, $name, $subject, $bodyHtml, $fromEmail, $fromName]);
        return DB::conn()->lastInsertId();
    }

    // 添加收件人（uid 即商户 uid）
    public static function addRecipient($uid, $campaignId, $email) {
        $token = md5($email . '|' . $campaignId . '|' . mt_rand() . '|' . microtime());
        DB::exec("INSERT INTO pay_mail_recipient (campaign_id, uid, token, to_email)
                  VALUES (?,?,?,?)",
            [$campaignId, (int)$uid, $token, $email]);
        return $token;
    }

    // 处理邮件 HTML（加追踪像素+点击跳转）
    public static function prepareBody($bodyHtml, $token) {
        $base = MAIL_TRACK_BASE . '/track';

        $bodyHtml = preg_replace_callback(
            '/<a\s+([^>]*?)href=["\']([^"\']+)["\']([^>]*?)>/i',
            function ($m) use ($base, $token) {
                $url = $m[2];
                if (strpos($url, 'mail_track') !== false ||
                    stripos($url, 'mailto:') === 0 ||
                    stripos($url, 'unsubscribe') !== false) {
                    return $m[0];
                }
                $tracked = $base . '/click.php?t=' . $token . '&url=' . base64_encode($url);
                return '<a ' . $m[1] . 'href="' . $tracked . '"' . $m[3] . '>';
            },
            $bodyHtml
        );

        $pixel = '<img src="' . $base . '/open.php?t=' . $token . '" width="1" height="1" style="display:none" alt="">';
        if (stripos($bodyHtml, '</body>') !== false) {
            $bodyHtml = preg_replace('/<\/body>/i', $pixel . '</body>', $bodyHtml);
        } else {
            $bodyHtml .= $pixel;
        }
        return $bodyHtml;
    }

    // 统计（$uid 非 0 时校验归属，防止跨商户查看）
    public static function stats($campaignId, $uid = 0) {
        if ($uid > 0) {
            $c = DB::one("SELECT * FROM pay_mail_campaign WHERE id=? AND uid=?", [$campaignId, $uid]);
        } else {
            $c = DB::one("SELECT * FROM pay_mail_campaign WHERE id=?", [$campaignId]);
        }
        if (!$c) return null;

        $c['unique_open_count']  = (int) DB::col("SELECT COUNT(DISTINCT recipient_id) FROM pay_mail_event WHERE campaign_id=? AND event_type='open'", [$campaignId]);
        $c['unique_click_count'] = (int) DB::col("SELECT COUNT(DISTINCT recipient_id) FROM pay_mail_event WHERE campaign_id=? AND event_type='click'", [$campaignId]);
        $c['open_rate']  = $c['sent_count'] > 0 ? round($c['unique_open_count'] / $c['sent_count'] * 100, 2) : 0;
        $c['click_rate'] = $c['sent_count'] > 0 ? round($c['unique_click_count'] / $c['sent_count'] * 100, 2) : 0;
        $c['ctr']        = $c['unique_open_count'] > 0 ? round($c['unique_click_count'] / $c['unique_open_count'] * 100, 2) : 0;
        return $c;
    }
}