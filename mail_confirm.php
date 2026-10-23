<?php
/**
 * 邮件推送回调 - 确认入口
 * URL: /mail_confirm.php?token=xxx&action=ok|no
 */
require_once('includes/common.php');
require_once(__DIR__ . '/telegram/secrets.php');
require_once('includes/lib/MailOrder.php');

use lib\MailOrder;

// ===== 全量审计日志（所有请求，含重复点击 / 无效 token / 参数错误）=====
function __auditLog($stage, $extra = []) {
    $tok  = $_GET['token']  ?? $_POST['token']  ?? '';
    $act  = $_GET['action'] ?? $_POST['action'] ?? '-';
    $ip   = $_SERVER['REMOTE_ADDR'] ?? '-';
    $ua   = substr($_SERVER['HTTP_USER_AGENT'] ?? '-', 0, 120);
    $ref  = substr($_SERVER['HTTP_REFERER'] ?? '-', 0, 120);
    $line = date('Y-m-d H:i:s')
          . ' | stage=' . $stage
          . ' | ip=' . $ip
          . ' | action=' . $act
          . ' | token=' . substr($tok, 0, 12)
          . ' | ua=' . $ua
          . ' | ref=' . $ref;
    if (!empty($extra)) {
        $line .= ' | ' . json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    // 多路径 fallback：/tmp 优先，失败则写站点 runtime/
    $candidates = [
        '/tmp/mail_confirm_all.log',
        __DIR__ . '/runtime/mail_confirm_all.log',
    ];
    foreach ($candidates as $f) {
        $dir = dirname($f);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        if (!file_exists($f)) {
            @touch($f);
            @chmod($f, 0666);
        }
        if (is_writable($f)) {
            @file_put_contents($f, $line . "\n", FILE_APPEND | LOCK_EX);
            return;
        }
    }
    // 兜底：写 PHP error log
    error_log('[mail_confirm_audit] ' . $line);
}

// 支持 POST（部分邮件客户端点击后可能用 POST）
$token  = $_GET['token']  ?? $_POST['token']  ?? '';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

__auditLog('received');

// 页面渲染函数
function render($title, $emoji, $color, $msg, $extra = '')
{
    $site = htmlspecialchars($GLOBALS['conf']['sitename'] ?? '信达支付', ENT_QUOTES, 'UTF-8');
    echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>{$title} - {$site}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,"PingFang SC","Microsoft YaHei",sans-serif;
     min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;
     background:linear-gradient(135deg,#f5f7fa,#e8eef7);color:#1a2a4a}
.card{background:#fff;border-radius:20px;padding:48px 36px;text-align:center;
      box-shadow:0 20px 60px rgba(13,71,161,.15);max-width:480px;width:100%}
.icon{font-size:80px;line-height:1;margin-bottom:20px;display:block}
h1{font-size:24px;font-weight:800;color:{$color};margin-bottom:14px;letter-spacing:-.3px}
p{font-size:15px;color:#5a6a7e;line-height:1.8;margin-bottom:20px}
.extra{background:#f7f9fc;border-radius:10px;padding:16px;text-align:left;font-size:13.5px;
       color:#3a4a5e;line-height:2;font-family:Menlo,Consolas,monospace;word-break:break-all}
.extra b{color:#1a2a4a;font-family:-apple-system,sans-serif;font-weight:700}
.foot{margin-top:24px;font-size:12.5px;color:#a5b4c9}
</style>
</head>
<body>
<div class="card">
<span class="icon">{$emoji}</span>
<h1>{$title}</h1>
<p>{$msg}</p>
{$extra}
<div class="foot">{$site} · 系统自动处理</div>
</div>
</body>
</html>
HTML;
    exit;
}

// 参数校验
if (empty($token)) {
    __auditLog('param_error', ['reason'=>'empty_token']);
    render('参数错误', '⚠️', '#e63946', '未接收到有效的确认令牌，请检查邮件中的链接是否完整。');
}
if (!in_array($action, ['ok', 'no'], true)) {
    __auditLog('param_error', ['reason'=>'bad_action', 'action'=>$action]);
    render('参数错误', '⚠️', '#e63946', '无效的操作类型，只支持 ok 或 no。');
}

// 校验 token
$v = MailOrder::verify($token);
if ($v['code'] === -2) {
    // 已处理（重复点击）
    $__prev = $v['row'] ?? [];
    __auditLog('repeat_click', [
        'trade_no' => $__prev['trade_no'] ?? '',
        'first_action' => $__prev['action'] ?? '',
        'first_ip' => $__prev['confirm_ip'] ?? '',
        'first_time' => $__prev['confirmed_at'] ?? '',
    ]);
    $__pTN   = htmlspecialchars($__prev['trade_no'] ?? '', ENT_QUOTES, 'UTF-8');
    $__pMail = htmlspecialchars($__prev['to_email'] ?? '', ENT_QUOTES, 'UTF-8');
    $__pTime = htmlspecialchars($__prev['confirmed_at'] ?? '—', ENT_QUOTES, 'UTF-8');
    $__pIp   = htmlspecialchars($__prev['confirm_ip'] ?? '—', ENT_QUOTES, 'UTF-8');
    $__isOk  = ($__prev['action'] ?? '') === 'ok';
    $__icon  = $__isOk ? '✅' : '⏭';
    $__color = $__isOk ? '#00a86b' : '#7a8699';
    $__title = $__isOk ? '支付成功' : '订单已忽略';
    $__sub   = $__isOk ? '订单已确认到账，无需重复操作。' : '该订单已被忽略，如需处理请联系商户。';

    // 取订单信息
    $__ord2 = $DB->getRow("SELECT ip, money, realmoney, is_test, ext FROM pay_order WHERE trade_no=? LIMIT 1", [$__prev['trade_no'] ?? '']);
    $__payIp2 = htmlspecialchars($__ord2['ip'] ?? '—', ENT_QUOTES, 'UTF-8');
    $__amt2 = number_format(($__ord2['realmoney'] > 0 ? $__ord2['realmoney'] : ($__ord2['money'] ?? 0)), 2);

    $__userLabel = $__pMail;
    if (is_numeric($__pMail)) $__userLabel = "TG 用户 ID: {$__pMail}";

    // 测试订单：推送到发起者 TG
    if (!empty($__ord2['is_test'])) {
        $__ext2 = json_decode($__ord2['ext'], true) ?: [];
        $__cbType2 = $__ext2['callback_type'] ?? '';
        $__cbTarget2 = $__ext2['callback_target'] ?? '';
        $__initTgId2 = (int)($__ext2['init_tg_id'] ?? 0);
        $__pushTo2 = $__initTgId2 > 0 ? $__initTgId2 : (is_numeric($__cbTarget2) ? (int)$__cbTarget2 : 0);
        if ($__pushTo2 > 0) {
            $__sandboxToken2 = SANDBOX_BOT_TOKEN;
            $__pushText2 = "🎉 <b>测试完成</b>\n"
                        . "━━━━━━━━━━━━━━━━━━\n"
                        . "📦 订单号：<code>{$__pTN}</code>\n"
                        . "💰 金额：<b>￥{$__amt2}</b>\n"
                        . "🌐 支付 IP：{$__payIp2}\n"
                        . "━━━━━━━━━━━━━━━━━━\n"
                        . "👤 确认人：" . (is_numeric($__cbTarget2) ? "TG {$__cbTarget2}" : "邮箱 {$__cbTarget2}") . "\n"
                        . "🔒 确认 IP：{$__pIp}\n"
                        . "🕐 确认时间：{$__pTime}\n"
                        . "━━━━━━━━━━━━━━━━━━\n"
                        . "💡 你已完整体验测试流程！";
            $__ch2b = curl_init("https://api.telegram.org/bot{$__sandboxToken2}/sendMessage");
            curl_setopt_array($__ch2b, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    'chat_id' => $__pushTo2,
                    'text' => $__pushText2,
                    'parse_mode' => 'HTML',
                ]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ]);
            curl_exec($__ch2b);
            curl_close($__ch2b);
        }
    }
    $__extra = "<div class='extra'>"
             . "<b>订单号：</b>{$__pTN}<br>"
             . "<b>金额：</b>￥{$__amt2}<br>"
             . "<b>支付 IP：</b>{$__payIp2}<br>"
             . "<hr style='border:none;border-top:1px dashed #e2e8f0;margin:8px 0;'>"
             . "<b>状态：</b>" . ($__isOk ? '✅ 已确认到账' : '❌ 已忽略') . "<br>"
             . "<b>确认人：</b>{$__userLabel}<br>"
             . "<b>确认 IP：</b>{$__pIp}<br>"
             . "<b>确认时间：</b>{$__pTime}<br>"
             . "<hr style='border:none;border-top:1px dashed #e2e8f0;margin:8px 0;'>"
             . "<b>提示：</b>订单已处理，无需重复操作"
             . "</div>";
    render($__title, $__icon, $__color, $__sub, $__extra);
}
if ($v['code'] !== 0) {
    __auditLog('verify_fail', ['msg'=>$v['msg'] ?? '']);
    render('链接失效', '⏰', '#e63946', $v['msg']);
}

$row      = $v['row'];
$trade_no = $row['trade_no'];
$uid      = (int)$row['uid'];
$ip       = $_SERVER['REMOTE_ADDR'] ?? '';

// 忽略操作
if ($action === 'no') {
    __auditLog('ignore_ok', ['trade_no'=>$trade_no, 'uid'=>$uid]);
    MailOrder::markDone($row['id'], 'no', $ip);
    $__mailTo = htmlspecialchars($row['to_email'] ?? '', ENT_QUOTES, 'UTF-8');
    $__now    = date('Y-m-d H:i:s');
    $__ip     = htmlspecialchars($ip, ENT_QUOTES, 'UTF-8');
    $__tn     = htmlspecialchars($trade_no, ENT_QUOTES, 'UTF-8');
    $__extra  = "<div class='extra'>"
              . "<b>订单号：</b>{$__tn}<br>"
              . "<b>状态：</b>❌ 已忽略<br>"
              . "<b>处理邮箱：</b>{$__mailTo}<br>"
              . "<b>处理时间：</b>{$__now}<br>"
              . "<b>处理 IP：</b>{$__ip}"
              . "</div>";
    render('已忽略', '⏭', '#7a8699', '该订单已忽略，商户可稍后手动处理。', $__extra);
}

// ===== 确认到账 =====

// 1) 调用 Order::complete 完成订单（含扣积分 + 幂等）
$result = null;
try {
    $result = \lib\Order::complete($trade_no, $row['amount'] ?? null);
} catch (\Throwable $e) {
    $result = ['code' => -1, 'msg' => $e->getMessage()];
}

$ok = is_array($result) && in_array($result['code'] ?? -1, [0, 1], true);

if (!$ok) {
    $errmsg = $result['msg'] ?? '未知错误';
    __auditLog('complete_fail', ['trade_no'=>$trade_no, 'uid'=>$uid, 'err'=>$errmsg]);
    render('处理失败', '❌', '#e63946', "订单处理失败：{$errmsg}",
        "<div class='extra'><b>订单号：</b>{$trade_no}</div>");
}

// 2) 标记已处理
MailOrder::markDone($row['id'], 'ok', $ip);

// 3) 触发回调 do_notify（复用现有逻辑）
$notifyResult = 'no_url';
$ord = $DB->getRow("SELECT * FROM pay_order WHERE trade_no=?", [$trade_no]);
if ($ord) {
    if ((string)($ord['notify'] ?? '') === '0') {
        $notifyResult = 'already_ok';
    } elseif (empty($ord['notify_url'])) {
        $notifyResult = 'no_url';
    } else {
        try {
            $cb = creat_callback($ord);
            if (function_exists('do_notify') && do_notify($cb['notify'])) {
                $DB->exec("UPDATE pay_order SET notify=0, notifytime=NOW() WHERE trade_no=?", [$trade_no]);
                $notifyResult = 'ok';
            } else {
                $DB->exec("UPDATE pay_order SET notify=-1, notifytime=NOW() WHERE trade_no=?", [$trade_no]);
                $notifyResult = 'failed';
            }
        } catch (\Throwable $e) {
            $notifyResult = 'err: ' . substr($e->getMessage(), 0, 40);
            @file_put_contents('/tmp/mail_callback_err.log',
                date('H:i:s') . " {$e->getMessage()} | {$trade_no}\n", FILE_APPEND);
        }
    }
}

// 4) 日志（双写：简要 + 审计）
@file_put_contents('/tmp/mail_confirm.log',
    date('Y-m-d H:i:s') . " | {$trade_no} | uid={$uid} | ip={$ip} | notify={$notifyResult}\n",
    FILE_APPEND);
__auditLog('confirm_ok', [
    'trade_no' => $trade_no,
    'uid' => $uid,
    'notify' => $notifyResult,
]);

// ===== 测试订单：推送完整结果给发起者 TG =====
if (!empty($ord['is_test'])) {
    $__ext = json_decode($ord['ext'], true) ?: [];
    $__cbType = $__ext['callback_type'] ?? '';
    $__cbTarget = $__ext['callback_target'] ?? '';
    $__initTgId = (int)($__ext['init_tg_id'] ?? 0);
    $__pushTo = $__initTgId > 0 ? $__initTgId : (is_numeric($__cbTarget) ? (int)$__cbTarget : 0);
    if ($__pushTo > 0) {
        $__sandboxToken = SANDBOX_BOT_TOKEN;
        $__payIp = $ord['ip'] ?? '—';
        $__confirmIp = $ip;
        $__confirmTime = date('Y-m-d H:i:s');
        $__amount = number_format($ord['realmoney'] > 0 ? $ord['realmoney'] : $ord['money'], 2);
        $__nMap2 = ['ok'=>'✅ 已发送','already_ok'=>'⏭ 此前已发送','no_url'=>'⚠️ 未配置回调URL','failed'=>'❌ 发送失败'];
        $__nTxt2 = $__nMap2[$notifyResult] ?? $notifyResult;

        $__pushText = "🎉 <b>测试完成</b>\n"
                    . "━━━━━━━━━━━━━━━━━━\n"
                    . "📦 订单号：<code>{$trade_no}</code>\n"
                    . "💰 金额：<b>￥{$__amount}</b>\n"
                    . "🌐 支付 IP：{$__payIp}\n"
                    . "━━━━━━━━━━━━━━━━━━\n"
                    . "👤 确认人：" . (is_numeric($__cbTarget) ? "TG {$__cbTarget}" : "邮箱 {$__cbTarget}") . "\n"
                    . "🔒 确认 IP：{$__confirmIp}\n"
                    . "🕐 确认时间：{$__confirmTime}\n"
                    . "📡 回调状态：{$__nTxt2}\n"
                    . "━━━━━━━━━━━━━━━━━━\n"
                    . "💡 你已完整体验测试流程！";

        $__ch2 = curl_init("https://api.telegram.org/bot{$__sandboxToken}/sendMessage");
        curl_setopt_array($__ch2, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'chat_id' => $__pushTo,
                'text' => $__pushText,
                'parse_mode' => 'HTML',
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        curl_exec($__ch2);
        curl_close($__ch2);
        @file_put_contents('/tmp/sandbox_confirm.log',
            date('Y-m-d H:i:s') . " | PUSH_RESULT | {$trade_no} | to={$__cbTarget}\n", FILE_APPEND);
    }
}

// 5) 渲染成功页
$nMap = [
    'ok'         => '✅ 已发送回调',
    'already_ok' => '⏭ 回调此前已发送',
    'no_url'     => '⚠️ 订单未配置回调URL',
    'failed'     => '❌ 回调发送失败',
];
$nTxt = $nMap[$notifyResult] ?? $notifyResult;

$amt   = number_format($ord['money'] ?? 0, 2);
$payIp = htmlspecialchars($ord['ip'] ?? '—', ENT_QUOTES, 'UTF-8');

// 取 pending 记录（确认人信息）
$__pend = $DB->getRow("SELECT * FROM pay_mail_pending WHERE trade_no=? ORDER BY id DESC LIMIT 1", [$trade_no]);
$__confirmTime = htmlspecialchars($__pend['confirmed_at'] ?? date('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8');
$__confirmIp   = htmlspecialchars($__pend['confirm_ip'] ?? $ip, ENT_QUOTES, 'UTF-8');
$__confirmTo   = htmlspecialchars($__pend['to_email'] ?? '—', ENT_QUOTES, 'UTF-8');

// 判断是否 TG 用户
$__confirmUser = $__confirmTo;
if (is_numeric($__confirmTo)) {
    $__confirmUser = "TG 用户 ID: {$__confirmTo}";
}

$extra = "<div class='extra'>"
       . "<b>订单号：</b>{$trade_no}<br>"
       . "<b>金额：</b>￥{$amt}<br>"
       . "<b>支付 IP：</b>{$payIp}<br>"
       . "<hr style='border:none;border-top:1px dashed #e2e8f0;margin:8px 0;'>"
       . "<b>确认人：</b>{$__confirmUser}<br>"
       . "<b>确认 IP：</b>{$__confirmIp}<br>"
       . "<b>确认时间：</b>{$__confirmTime}<br>"
       . "<hr style='border:none;border-top:1px dashed #e2e8f0;margin:8px 0;'>"
       . "<b>回调状态：</b>{$nTxt}"
       . "</div>";

render('支付成功', '✅', '#00a86b',
    '订单已确认，商户系统已收到支付回调。', $extra);
