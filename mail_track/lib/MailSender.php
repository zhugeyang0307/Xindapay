<?php
if (!defined('SYS_KEY')) {
    require_once dirname(__DIR__, 2) . '/includes/common.php';
}


require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/MailTrack.php';

class MailSender {

    // 读取商户 SMTP 配置（pay_user_botconfig）
    private static function getSmtpCfg($uid) {
        $row = DB::one("SELECT mail_smtp_host, mail_smtp_port, mail_smtp_user, mail_smtp_pwd,
                               mail_smtp_secure, mail_from_name
                        FROM pay_user_botconfig WHERE uid=?", [(int)$uid]);
        if (!$row) return null;
        if (empty($row['mail_smtp_host']) || empty($row['mail_smtp_user']) || empty($row['mail_smtp_pwd'])) {
            return null;
        }
        $pwd = \authcode($row['mail_smtp_pwd'], 'DECODE', SYS_KEY);
        if (empty($pwd)) $pwd = $row['mail_smtp_pwd'];  // 兼容历史明文
        return [
            'host'      => $row['mail_smtp_host'],
            'port'      => intval($row['mail_smtp_port'] ?: 465),
            'user'      => $row['mail_smtp_user'],
            'pwd'       => $pwd,
            'secure'    => $row['mail_smtp_secure'] ?: 'ssl',
            'from_name' => $row['mail_from_name'] ?: '',
        ];
    }

    // 批量发送
    public static function sendBatch($uid, $campaignId, $limit = MAIL_BATCH_SIZE, $perEmailDelay = 0.5) {
        $uid = (int)$uid;
        $campaign = DB::one("SELECT * FROM pay_mail_campaign WHERE id=? AND uid=?", [$campaignId, $uid]);
        if (!$campaign) return ['ok' => false, 'msg' => '活动不存在'];

        // 商户 SMTP 检查
        $smtp = self::getSmtpCfg($uid);
        if (!$smtp) {
            return ['ok' => false, 'msg' => '请先在「邮件推送」页面配置发件邮箱 SMTP'];
        }

        DB::exec("UPDATE pay_mail_campaign SET status=1, started_at=COALESCE(started_at, NOW()) WHERE id=?", [$campaignId]);

        $limit = (int)$limit;
        $perEmailDelay = max(0, min(60, (float)$perEmailDelay));  // 0-60 秒
        $recipients = DB::query("SELECT * FROM pay_mail_recipient WHERE campaign_id=? AND status=0 ORDER BY id ASC LIMIT {$limit}",
            [$campaignId]);
        $ok = 0; $fail = 0;
        $lastErr = '';
        mailTrackLog("DEBUG: uid={$uid} campaign={$campaignId} limit={$limit} found=" . count($recipients));

        $details = [];
        $__bl = ['example.com','example.org','example.net','test.com','test.cn','test.local','localhost','invalid.com','fake.com','sample.com','demo.com','mydomain.com'];
        foreach ($recipients as $r) {
            $__domain = strtolower(substr(strrchr($r['to_email'], '@'), 1));
            if (in_array($__domain, $__bl, true) || preg_match('/\.(test|invalid|local|localhost|example)$/i', $__domain)) {
                DB::exec("UPDATE pay_mail_recipient SET status=2, error_msg=? WHERE id=?",
                    ['黑名单域名（' . $__domain . '）', $r['id']]);
                $fail++;
                $details[] = ['email' => $r['to_email'], 'ok' => false, 'msg' => '黑名单域名'];
                mailTrackLog("SKIP-BLACKLIST: id={$r['id']} to={$r['to_email']}");
                continue;
            }
            mailTrackLog("SENDING: id={$r['id']} to={$r['to_email']}");
            $body = MailTrack::prepareBody($campaign['body_html'], $r['token']);

            $result = self::_sendOne($smtp, [
                'to'       => $r['to_email'],
                'subject'  => $campaign['subject'],
                'html'     => $body,
                'from'     => $campaign['from_email'] ?: $smtp['user'],
                'fromName' => $campaign['from_name'] ?: ($smtp['from_name'] ?: '通知'),
            ]);

            if (!empty($result['ok'])) {
                DB::exec("UPDATE pay_mail_recipient SET status=1, sent_at=NOW() WHERE id=?", [$r['id']]);
                $ok++;
                $details[] = ['email' => $r['to_email'], 'ok' => true, 'msg' => ''];
            } else {
                $lastErr = $result['msg'] ?? 'unknown';
                DB::exec("UPDATE pay_mail_recipient SET status=2, error_msg=? WHERE id=?",
                    [substr($lastErr, 0, 255), $r['id']]);
                $fail++;
                $details[] = ['email' => $r['to_email'], 'ok' => false, 'msg' => $lastErr];
            }

            if ($perEmailDelay > 0) usleep((int)($perEmailDelay * 1000000));
        }

        DB::exec("UPDATE pay_mail_campaign SET sent_count=sent_count+?, failed_count=failed_count+? WHERE id=?",
            [$ok, $fail, $campaignId]);

        $remain = (int) DB::col("SELECT COUNT(*) FROM pay_mail_recipient WHERE campaign_id=? AND status=0", [$campaignId]);
        if ($remain == 0) {
            DB::exec("UPDATE pay_mail_campaign SET status=2, finished_at=NOW() WHERE id=?", [$campaignId]);
        }

        mailTrackLog("uid={$uid} campaign={$campaignId} sent={$ok} failed={$fail} remain={$remain}");

        return [
            'ok'       => true,
            'sent'     => $ok,
            'failed'   => $fail,
            'remain'   => $remain,
            'last_err' => $lastErr,
            'details'  => $details,
            'per_email_delay' => $perEmailDelay,
        ];
    }

    // 单封发送（用商户 SMTP）
    private static function _sendOne($smtp, $params) {
        $host = $smtp['host'];
        $user = $smtp['user'];
        $pwd  = $smtp['pwd'];
        $port = $smtp['port'];
        $secure = $smtp['secure'];

        $phpMailer = dirname(__DIR__, 2) . '/includes/lib/mail/PHPMailer/PHPMailer.php';
        $smtpClass = dirname(__DIR__, 2) . '/includes/lib/mail/PHPMailer/SMTP.php';
        $excClass  = dirname(__DIR__, 2) . '/includes/lib/mail/PHPMailer/Exception.php';

        if (!file_exists($phpMailer)) return ['ok' => false, 'msg' => 'PHPMailer 不存在'];
        if (file_exists($excClass))  require_once $excClass;
        if (file_exists($smtpClass)) require_once $smtpClass;
        require_once $phpMailer;

        try {
            $mail = new \lib\mail\PHPMailer\PHPMailer(true);
            $mail->SMTPDebug   = 0;
            $mail->CharSet     = 'UTF-8';
            $mail->Timeout     = 15;
            $mail->isSMTP();
            $mail->Host        = $host;
            $mail->SMTPAuth    = true;
            $mail->Username    = $user;
            $mail->Password    = $pwd;
            if ($secure === 'ssl' || $port == 465) {
                $mail->SMTPSecure = 'ssl';
                $mail->Port = $port ?: 465;
            } elseif ($secure === 'none') {
                $mail->SMTPAutoTLS = false;
                $mail->Port = $port ?: 25;
            } else {
                $mail->SMTPSecure = 'tls';
                $mail->SMTPAutoTLS = true;
                $mail->Port = $port ?: 587;
            }
            $mail->setFrom($params['from'] ?: $user, $params['fromName'] ?: $user);
            $mail->addAddress($params['to']);
            $mail->isHTML(true);
            $mail->Subject = $params['subject'];
            $mail->Body    = $params['html'];
            $mail->send();
            return ['ok' => true, 'msg' => ''];
        } catch (\Throwable $e) {
            $err = $e->getMessage();
            mailTrackLog("FAIL -> {$params['to']} | {$err}");
            return ['ok' => false, 'msg' => substr($err, 0, 250)];
        }
    }
}