<?php
// 强制登出（不检查 referer）
setcookie("user_token", "", time() - 86400, "/");
setcookie("user_token", "", time() - 86400, "/user");
setcookie("user_token", "", time() - 86400);
setcookie("PHPSESSID", "", time() - 86400, "/");
if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
header('Content-Type: text/html; charset=UTF-8');
echo "<script>alert('已强制登出，请重新登录');location.href='/user/login.php';</script>";