<?php
namespace lib;

/**
 * 邮件推送回调 - 商户邮箱确认订单
 */
class MailOrder
{
    /** 读取 SMTP 配置：优先用上级（upid）的，其次用自己的 */
    private static function getSmtpCfg($uid, $forceOwn = false)
    {
        global $DB;
        $uid = (int)$uid;
        // 先找上级（$forceOwn=true 时强制只用自己的 SMTP）
        $upid = (int)$DB->getColumn("SELECT upid FROM pay_user WHERE uid=?", [$uid]);
        $tryList = [];
        if ($upid > 0 && !$forceOwn) $tryList[] = $upid;
        $tryList[] = $uid;

        foreach ($tryList as $tryUid) {
            $row = $DB->getRow(
                "SELECT mail_smtp_host, mail_smtp_port, mail_smtp_user, mail_smtp_pwd,
                        mail_smtp_secure, mail_from_name
                 FROM pay_user_botconfig WHERE uid=?",
                [$tryUid]
            );
            if (!$row) continue;
            if (empty($row['mail_smtp_host']) || empty($row['mail_smtp_user']) || empty($row['mail_smtp_pwd'])) {
                continue;
            }
            $pwd = \authcode($row['mail_smtp_pwd'], 'DECODE', SYS_KEY);
            if (empty($pwd)) $pwd = $row['mail_smtp_pwd'];
            return [
                'host'      => $row['mail_smtp_host'],
                'port'      => intval($row['mail_smtp_port'] ?: 465),
                'user'      => $row['mail_smtp_user'],
                'pwd'       => $pwd,
                'secure'    => $row['mail_smtp_secure'] ?: 'ssl',
                'from_name' => $row['mail_from_name'] ?: '',
                'from_uid'  => $tryUid,
            ];
        }
        return null;
    }

    /** 邮件推送入口：给商户邮箱发送带确认按钮的订单邮件 */
    public static function push($order, $toEmail = null)
    {
        global $DB, $conf;

        $trade_no = $order['trade_no'];
        $uid      = (int)$order['uid'];
        $amount   = $order['realmoney'] ?: $order['money'];

        // 目标邮箱
        if (!$toEmail) {
            $toEmail = $DB->getColumn("SELECT email FROM pay_user WHERE uid=?", [$uid]);
        }
        if (!$toEmail) {
            return ['code'=>-1, 'msg'=>'商户未配置有效邮箱'];
        }

        // 支持逗号/分号/空格分隔的多邮箱，并为每个收件人单独生成唯一 token
        $__rawList = array_filter(array_map('trim', preg_split('/[,;，；\s]+/', $toEmail)));
        $__validList = [];
        foreach ($__rawList as $__em) {
            if ($__em !== '' && filter_var($__em, FILTER_VALIDATE_EMAIL)) {
                $__validList[] = $__em;
            }
        }
        if (empty($__validList)) {
            return ['code'=>-1, 'msg'=>'商户未配置有效邮箱'];
        }
        $emails = array_values(array_unique($__validList));

        // 标准商户组（gid=4）强制自建 SMTP，禁止回退平台
        $gid = (int)$DB->getColumn("SELECT gid FROM pay_user WHERE uid=?", [$uid]);
        $forceOwn = ($gid === 4);
        $smtpCfg = self::getSmtpCfg($uid, $forceOwn);
        if ($forceOwn && !$smtpCfg) {
            return ['code'=>-1, 'msg'=>'标准商户组必须先在「邮件推送」里配置自建 SMTP 服务器才能发邮件推送，平台不会代替您发送'];
        }

        $sub = '【' . ($conf['sitename'] ?? '信达支付') . '】新订单 ¥' . number_format($amount, 2);
        $siteurl = self::siteUrl();
        $tokenList = [];
        $firstToken = null;
        $sentOk = 0;
        $lastErr = '';

        foreach ($emails as $oneTo) {
            $token = hash_hmac('sha256', $trade_no . '|' . $uid . '|' . $oneTo . '|' . time() . '|' . mt_rand(100000, 999999), SYS_KEY);
            $token = substr($token, 0, 64);
            $tokenList[] = ['email' => $oneTo, 'token' => $token];
            $firstToken = $firstToken ?: $token;

            $okUrl = $siteurl . '/mail_confirm.php?token=' . $token . '&action=ok';
            $noUrl = $siteurl . '/mail_confirm.php?token=' . $token . '&action=no';
            $html  = self::buildHtml($order, $amount, $okUrl, $noUrl, $conf['sitename'] ?? '信达支付系统');

            $DB->exec(
                "INSERT INTO pay_mail_pending
                    (trade_no, uid, to_email, token, status, sent_at, expire_at)
                 VALUES (?, ?, ?, ?, 0, NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR))",
                [$trade_no, $uid, $oneTo, $token]
            );

            if ($smtpCfg) {
                $oneR = \send_mail_with_smtp($oneTo, $sub, $html, $smtpCfg);
            } else {
                $oneR = send_mail($oneTo, $sub, $html);
            }
            if ($oneR === true) {
                $sentOk++;
            } else {
                $lastErr = is_string($oneR) ? $oneR : '未知';
            }
        }

        $total = count($emails);
        if ($sentOk === $total) {
            return ['code'=>0, 'msg'=>"邮件已发送({$sentOk}/{$total})", 'token'=>$firstToken, 'tokens'=>$tokenList];
        }
        if ($sentOk > 0) {
            return ['code'=>0, 'msg'=>"部分发送成功({$sentOk}/{$total})，失败：{$lastErr}", 'token'=>$firstToken, 'tokens'=>$tokenList];
        }
        return ['code'=>-1, 'msg'=>'邮件发送失败: ' . $lastErr];
    }

    /** 校验 token */
    public static function verify($token)
    {
        global $DB;
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return ['code'=>-1, 'msg'=>'token 格式错误'];
        }
        $row = $DB->getRow(
            "SELECT * FROM pay_mail_pending WHERE token=? AND expire_at>NOW() LIMIT 1",
            [$token]
        );
        if (!$row) return ['code'=>-1, 'msg'=>'链接已失效或过期'];
        if ((int)$row['status'] !== 0) {
            return ['code'=>-2, 'msg'=>'订单已处理', 'row'=>$row];
        }
        return ['code'=>0, 'row'=>$row];
    }

    /** 标记已处理 */
    public static function markDone($id, $action, $ip = '')
    {
        global $DB;
        $status = ($action === 'ok') ? 1 : 2;
        $DB->exec(
            "UPDATE pay_mail_pending
             SET status=?, action=?, confirmed_at=NOW(), confirm_ip=?
             WHERE id=? AND status=0",
            [$status, $action, $ip, $id]
        );
    }

    /** 获取站点URL */
    public static function siteUrl()
    {
        global $conf;
        if (!empty($conf['siteurl'])) return rtrim($conf['siteurl'], '/');
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'pay.yunzexinda.com';
        return $scheme . '://' . $host;
    }

    /** 邮件 HTML */
    private static function buildHtml($order, $amount, $okUrl, $noUrl, $sitename)
    {
        $name = htmlspecialchars($order['name'] ?? '商品', ENT_QUOTES, 'UTF-8');
        $tn   = htmlspecialchars($order['trade_no'], ENT_QUOTES, 'UTF-8');
        $amt  = number_format($amount, 2);
        $time = date('Y-m-d H:i:s');

        return <<<HTML
<!DOCTYPE html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f5f7fa;font-family:-apple-system,'PingFang SC',sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f7fa;padding:30px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.06);max-width:600px;">
<tr><td style="background:linear-gradient(135deg,#0d47a1,#1e88e5);padding:30px;text-align:center;color:#fff;">
<h1 style="margin:0;font-size:22px;font-weight:800;letter-spacing:.5px;">🔔 新订单到账</h1>
<p style="margin:8px 0 0;font-size:14px;opacity:.85;">{$sitename}</p>
</td></tr>
<tr><td style="padding:30px;">
<table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;color:#1a2a4a;">
<tr><td style="padding:10px 0;color:#7a8699;width:90px;">📦 商品</td><td style="padding:10px 0;font-weight:600;">{$name}</td></tr>
<tr><td style="padding:10px 0;color:#7a8699;">💰 金额</td><td style="padding:10px 0;font-weight:800;color:#e63946;font-size:20px;">￥{$amt}</td></tr>
<tr><td style="padding:10px 0;color:#7a8699;">🔑 订单</td><td style="padding:10px 0;font-family:Menlo,monospace;font-size:13px;">{$tn}</td></tr>
<tr><td style="padding:10px 0;color:#7a8699;">🕐 时间</td><td style="padding:10px 0;">{$time}</td></tr>
</table>
<div style="height:1px;background:#eef0f5;margin:20px 0;"></div>
<p style="text-align:center;color:#7a8699;font-size:13px;margin:20px 0;">请确认客户是否已实际付款到账：</p>
<table width="100%" cellpadding="0" cellspacing="0">
<tr>
<td width="48%" align="center">
<a href="{$okUrl}" style="display:block;padding:16px;background:linear-gradient(135deg,#00c9a7,#00a86b);color:#fff;text-decoration:none;border-radius:10px;font-size:16px;font-weight:700;">✅ 确认到账</a>
</td>
<td width="4%"></td>
<td width="48%" align="center">
<a href="{$noUrl}" style="display:block;padding:16px;background:#f0f2f5;color:#5a6a7e;text-decoration:none;border-radius:10px;font-size:16px;font-weight:600;">❌ 忽略</a>
</td>
</tr>
</table>
<p style="text-align:center;color:#a5b4c9;font-size:12px;margin:24px 0 0;line-height:1.7;">
链接 24 小时内有效，点击后立即向商户系统发送回调。<br>
如按钮无法点击，可复制以下链接到浏览器打开：<br>
<span style="color:#1e88e5;word-break:break-all;">{$okUrl}</span>
</p>
</td></tr>
<tr><td style="background:#f7f9fc;padding:16px;text-align:center;color:#a5b4c9;font-size:12px;">
本邮件由 {$sitename} 系统自动发送，请勿直接回复。
</td></tr>
</table>
</td></tr>
</table>
</body></html>
HTML;
    }
}
