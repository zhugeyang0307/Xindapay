<?php
// 独立 CLI 脚本，用支付系统的环境调 Order::complete 并触发回调
// 用法: php complete_order.php <trade_no> <amount>
$nosession = true;
$_SERVER['HTTP_HOST'] = 'pay.yunzexinda.com';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_SCHEME'] = 'https';
$_SERVER['SERVER_PORT'] = 443;
$_SERVER['HTTPS'] = 'on';

$trade_no = $argv[1] ?? '';
$amount = $argv[2] ?? null;
if (!$trade_no) { echo 'no trade_no'; exit(1); }

chdir('/www/wwwroot/pay.yunzexinda.com/');
require '/www/wwwroot/pay.yunzexinda.com/includes/common.php';

try {
    // 1. 完成订单（标记已支付 + 扣积分 + 统计）
    $r = \lib\Order::complete($trade_no, $amount);

    // 2. 若成功完成，触发商户回调（带幂等锁）
    if (isset($r['code']) && in_array($r['code'], [0, 1], true)) {
        $order = $DB->find('order', '*', ['trade_no'=>$trade_no]);
        // 幂等：notify=0 说明已成功回调过，跳过
        if ($order && $order['notify'] === 0) {
            $r['notify'] = 'already_notified';
            echo json_encode($r, JSON_UNESCAPED_UNICODE);
            exit(0);
        }
        if ($order && !empty($order['notify_url'])) {
            $url = creat_callback($order);
            if (do_notify($url['notify'])) {
                $DB->exec("UPDATE pay_order SET notify=0, notifytime=NOW() WHERE trade_no=:t", [':t'=>$trade_no]);
                $r['notify'] = 'ok';
            } else {
                $DB->exec("UPDATE pay_order SET notify=-1, notifytime=NOW() WHERE trade_no=:t", [':t'=>$trade_no]);
                $r['notify'] = 'failed';
            }
        } else {
            $r['notify'] = 'no_url';
        }
    }

    echo json_encode($r, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo 'EXCEPTION: ' . $e->getMessage();
    exit(1);
}
