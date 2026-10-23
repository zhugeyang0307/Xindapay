<?php
/**
 * 商户端邮件营销 · 公共初始化
 */
$__root = dirname(__DIR__, 2);
require_once $__root . '/includes/common.php';
require_once $__root . '/telegram/lib/DB.php';
require_once $__root . '/mail_track/config.php';
require_once $__root . '/mail_track/lib/MailTrack.php';
require_once $__root . '/mail_track/lib/MailSender.php';

if (empty($islogin2) || empty($userrow) || empty($userrow['uid'])) {
    // 兼容 AJAX 接口请求，返回 JSON 格式的错误信息
    if (strpos($_SERVER['SCRIPT_NAME'], 'api.php') !== false) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['code' => -1, 'msg' => '登录已过期，请刷新页面重新登录！']);
        exit;
    }
    header('Location: /user/login.php');
    exit;
}

$uid   = (int)$userrow['uid'];
$title = '邮件营销';

/** 校验活动归属 */
function mm_campaign($uid, $id) {
    return DB::one("SELECT * FROM pay_mail_campaign WHERE id=? AND uid=?", [(int)$id, (int)$uid]);
}

/** JSON 输出 */
function mm_json($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** 合并多个邮箱串，去重+去无效 */
function mm_parse_emails($raw) {
    $out = [];
    if (!$raw) return $out;
    foreach (preg_split('/[\s,;，；]+/u', $raw) as $em) {
        $em = trim($em);
        if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL)) {
            $out[$em] = true;
        }
    }
    return array_keys($out);
}

/** 测试/保留域名黑名单（大小写无关） */
function mm_blacklist_domains() {
    return [
        'example.com', 'example.org', 'example.net',
        'test.com', 'test.cn', 'test.local',
        'localhost', 'localhost.localdomain',
        'invalid', 'invalid.com',
        'fake.com', 'noreply.com', 'no-reply.com',
        'mailinator.com', 'guerrillamail.com', '10minutemail.com',
        'tempmail.com', 'temp-mail.org', 'throwawaymail.com',
        'yopmail.com', 'sharklasers.com', 'trashmail.com',
        'sample.com', 'demo.com', 'mydomain.com',
    ];
}

/** 检查域名是否合法（黑名单 + 可选 MX） */
function mm_valid_domain($email, $checkMx = true) {
    $pos = strrpos($email, '@');
    if ($pos === false) return [false, '格式错误'];
    $domain = strtolower(substr($email, $pos + 1));

    // 1. 黑名单
    $bl = mm_blacklist_domains();
    if (in_array($domain, $bl, true)) {
        return [false, '测试/保留域名，不接收真实邮件'];
    }

    // 2. 特殊后缀
    if (preg_match('/\.(test|invalid|local|localhost|example)$/i', $domain)) {
        return [false, '测试后缀域名'];
    }

    // 3. MX 记录检查（可选，默认开启）
    if ($checkMx) {
        if (function_exists('checkdnsrr')) {
            if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
                return [false, '域名不存在或无法接收邮件'];
            }
        }
        // 如果没有 checkdnsrr，跳过 MX 检查
    }

    return [true, ''];
}
/** 拆分邮箱：返回 [有效数组, 无效数组] */
function mm_split_emails($raw, $checkMx = true) {
    $valid = [];
    $invalid = [];
    if (!$raw) return [$valid, $invalid];
    foreach (preg_split('/[\s,;，；]+/u', $raw) as $em) {
        $em = trim($em);
        if ($em === '') continue;
        if (!filter_var($em, FILTER_VALIDATE_EMAIL)) {
            $invalid[] = $em . '（格式错误）';
            continue;
        }
        [$ok, $reason] = mm_valid_domain($em, $checkMx);
        if (!$ok) {
            $invalid[] = $em . '（' . $reason . '）';
            continue;
        }
        $valid[$em] = true;
    }
    return [array_keys($valid), array_values(array_unique($invalid))];
}
/** 解析 User-Agent 成中文可读文本 */
function mm_parse_ua($ua) {
    if (empty($ua)) return '未知设备';
    $browser = '未知浏览器';
    $os = '未知系统';
    $device = '';

    // 浏览器（按优先级）
    if (stripos($ua, 'MicroMessenger') !== false) $browser = '微信内置浏览器';
    elseif (stripos($ua, 'QQBrowser') !== false) $browser = 'QQ浏览器';
    elseif (stripos($ua, 'UCBrowser') !== false) $browser = 'UC浏览器';
    elseif (stripos($ua, 'Edg/') !== false || stripos($ua, 'Edge') !== false) $browser = 'Edge';
    elseif (stripos($ua, 'OPR/') !== false || stripos($ua, 'Opera') !== false) $browser = 'Opera';
    elseif (stripos($ua, 'Chrome') !== false) $browser = 'Chrome';
    elseif (stripos($ua, 'Firefox') !== false) $browser = 'Firefox';
    elseif (stripos($ua, 'Safari') !== false) $browser = 'Safari';
    elseif (stripos($ua, 'MSIE') !== false || stripos($ua, 'Trident') !== false) $browser = 'IE';
    elseif (stripos($ua, 'bot') !== false || stripos($ua, 'spider') !== false) $browser = '爬虫';

    // 操作系统 + 设备
    if (stripos($ua, 'iPhone') !== false) { $os = 'iPhone'; $device = '手机'; }
    elseif (stripos($ua, 'iPad') !== false) { $os = 'iPad'; $device = '平板'; }
    elseif (stripos($ua, 'Android') !== false) { $os = 'Android'; $device = '手机'; }
    elseif (stripos($ua, 'Windows NT 10.0') !== false) { $os = 'Windows 10/11'; $device = '电脑'; }
    elseif (stripos($ua, 'Windows NT 6.3') !== false) { $os = 'Windows 8.1'; $device = '电脑'; }
    elseif (stripos($ua, 'Windows NT 6.1') !== false) { $os = 'Windows 7'; $device = '电脑'; }
    elseif (stripos($ua, 'Windows') !== false) { $os = 'Windows'; $device = '电脑'; }
    elseif (stripos($ua, 'Mac OS X') !== false || stripos($ua, 'Macintosh') !== false) { $os = 'Mac'; $device = '电脑'; }
    elseif (stripos($ua, 'Linux') !== false) { $os = 'Linux'; $device = '电脑'; }

    if ($device) return $device . ' · ' . $os . ' · ' . $browser;
    return $os . ' · ' . $browser;
}
/** 取商户发件邮箱（已配的） */
function mm_smtp_user($uid) {
    return DB::col("SELECT mail_smtp_user FROM pay_user_botconfig WHERE uid=?", [(int)$uid]);
}