<?php
if (isset($userrow['sub_level']) && (int)$userrow['sub_level'] >= 2) {
    exit("<script>alert('无权访问该功能');window.location.href='./index.php';</script>");
}
?>

$is_defend = true;
include("../includes/common.php");
if($islogin2 != 1) exit("<script>location.href='./login.php';</script>");

$myUid = (int)$userrow['uid'];
$act = $_GET['act'] ?? '';

// ───── 只允许返回自己的后台 ─────
if ($act === 'back') {
    $backUid = (int)($_SESSION['viewing_as'] ?? 0);
    if ($backUid > 0) {
        $backRow = $DB->getRow("SELECT uid, `key` FROM pay_user WHERE uid=:u", [':u'=>$backUid]);
        if ($backRow) {
            $password_hash = '!@#%!s!0';
            $session = md5($backRow['uid'] . $backRow['key'] . $password_hash);
            $expiretime = time() + 604800;
            $token = authcode("{$backRow['uid']}\t{$session}\t{$expiretime}", 'ENCODE', SYS_KEY);
            ob_clean();
            setcookie("user_token", $token, time() + 2592000);
            unset($_SESSION['viewing_as'], $_SESSION['viewing_name']);
            header('Location: ./');
            exit;
        }
    }
    unset($_SESSION['viewing_as'], $_SESSION['viewing_name']);
    header('Location: ./sub_list.php');
    exit;
}

// ───── 其余一律禁止 ─────
exit("<script>alert('⚠️ 系统已禁用模拟登录。\n\n上下级各自独立登录，互不干涉。\n如需查看下级数据，请用「下级订单」「下级账单核对」「下级收款码」等功能。');history.back();</script>");