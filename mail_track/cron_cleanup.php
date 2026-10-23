<?php
/**
 * 邮件营销 · 7 天数据清理（cron 每天跑一次）
 * 用法：
 *   本地：php mail_track/cron_cleanup.php
 *   宝塔：计划任务 · shell · 每天 03:00
 *          /www/server/php/82/bin/php /www/wwwroot/pay.yunzexinda.com/mail_track/cron_cleanup.php
 */
require_once __DIR__ . '/config.php';

$days = 7;

$cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

// 1. 找 7 天前的活动
$rows = DB::query("SELECT id FROM pay_mail_campaign WHERE created_at < ?", [$cutoff]);

if (empty($rows)) {
    echo "[" . date('Y-m-d H:i:s') . "] 无过期活动\n";
    mailTrackLog("cleanup: 无过期活动");
    exit;
}

$ids = array_column($rows, 'id');
$idList = implode(',', array_map('intval', $ids));

// 2. 删 event → recipient → campaign（先子后父）
$e1 = DB::exec("DELETE FROM pay_mail_event WHERE campaign_id IN ({$idList})");
$e2 = DB::exec("DELETE FROM pay_mail_recipient WHERE campaign_id IN ({$idList})");
$e3 = DB::exec("DELETE FROM pay_mail_campaign WHERE id IN ({$idList})");

$msg = date('Y-m-d H:i:s') . " | 清理 {$days} 天前活动：" . count($ids) . " 个（event={$e1} recipient={$e2} campaign={$e3}）";
echo $msg . "\n";
mailTrackLog($msg);