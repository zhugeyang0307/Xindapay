<?php
if (session_status() === PHP_SESSION_NONE) {
    session_name('ROUTEADMIN');
    session_start();
}
require_once dirname(__DIR__) . '/config.php';

if (isset($_GET['logout'])) {
    unset($_SESSION['route_admin_ok']);
    header('Location: index.php');
    exit;
}

if (!empty($_POST['_pass'])) {
    if ($_POST['_pass'] === ROUTE_ADMIN_PASS) {
        $_SESSION['route_admin_ok'] = true;
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    } else {
        $loginError = '密码错误';
    }
}

if (empty($_SESSION['route_admin_ok'])) {
    include __DIR__ . '/_login.php';
    exit;
}