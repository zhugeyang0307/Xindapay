<?php
require_once dirname(__DIR__, 2) . '/includes/lib/mail/PHPMailer/Exception.php';
require_once dirname(__DIR__, 2) . '/includes/lib/mail/PHPMailer/SMTP.php';
require_once dirname(__DIR__, 2) . '/includes/lib/mail/PHPMailer/PHPMailer.php';

header('Content-Type: text/plain; charset=utf-8');
echo "=== 域名/IPv4 对比测试 ===\n\n";

echo "--- 1) 域名 smtp.mail.me.com:587 ---\n";
$s1 = new \lib\mail\PHPMailer\SMTP;
$r1 = $s1->connect('smtp.mail.me.com', 587, 10);
var_dump($r1);
print_r($s1->getError());
echo "\n";

echo "--- 2) IPv4 17.156.192.29:587 ---\n";
$s2 = new \lib\mail\PHPMailer\SMTP;
$r2 = $s2->connect('17.156.192.29', 587, 10);
var_dump($r2);
print_r($s2->getError());

echo "\n=== DNS A ===\n";
print_r(dns_get_record('smtp.mail.me.com', DNS_A));
echo "\n=== DNS AAAA ===\n";
print_r(dns_get_record('smtp.mail.me.com', DNS_AAAA));