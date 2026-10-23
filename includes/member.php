<?php
$clientip=real_ip($conf['ip_type']?$conf['ip_type']:0);

if(isset($_COOKIE["admin_token"]))
{
	$token=authcode(daddslashes($_COOKIE['admin_token']), 'DECODE', SYS_KEY);
	list($user, $sid, $expiretime) = explode("\t", $token);
	$session=md5($conf['admin_user'].$conf['admin_pwd'].$password_hash);
	if($session==$sid && $expiretime>time()) {
		$islogin=1;
	}
}
if(isset($_COOKIE["user_token"]))
{
	$token=authcode(daddslashes($_COOKIE['user_token']), 'DECODE', SYS_KEY);
	list($uid, $sid, $expiretime) = explode("\t", $token);
	$uid = intval($uid);
	$userrow=$DB->getRow("SELECT * FROM pay_user WHERE uid=:uid limit 1", [':uid'=>$uid]);
	$session=md5($userrow['uid'].$userrow['key'].$password_hash);
	if($userrow && $session==$sid && $expiretime>time()) {
		$islogin2=1;
	}
}

// ============ 本地开发免密（仅显式启用时生效，线上不受影响） ============
if ((($_SERVER['HTTP_HOST'] ?? '') === 'pay.test') && defined('ALLOW_LOCAL_DEV') && ALLOW_LOCAL_DEV) {
    // 管理后台：默认登录
    $islogin = 1;

    // 商户后台：默认以 uid=1000 登录
    if (empty($islogin2) || $islogin2 != 1) {
        $__dev_uid = 1000;
        $__dev_row = $DB->getRow("SELECT * FROM pay_user WHERE uid=:uid LIMIT 1", [':uid'=>$__dev_uid]);
        if ($__dev_row) {
            $userrow = $__dev_row;
            $islogin2 = 1;
            $uid = $__dev_uid;                    // 补 $uid（很多页面直接用它）
            $GLOBALS['uid'] = $__dev_uid;
            // 让 $_SESSION 也同步（有些页面用 session 判断）
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['user_uid'] = $__dev_uid;
                $_SESSION['uid'] = $__dev_uid;
            }
        }
    }
}
?>