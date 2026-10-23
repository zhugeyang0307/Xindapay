<?php
// 按身份显示菜单
require_once __DIR__ . '/Auth.php';

function menuOwner($chat_id) {
    $text = "<b>👑 所有者面板</b>\n"
          . "━━━━━━━━━━━━━━━━━━\n"
          . "欢迎，信达网络所有者\n"
          . "拥有最高权限，所有操作均在审计中\n"
          . "━━━━━━━━━━━━━━━━━━\n"
          . "<b>📊 数据查询</b>\n"
          . "/系统状态      服务器运行状态\n"
          . "/统计          平台统计\n"
          . "/商户列表      所有商户\n"
          . "/群列表        绑定群组\n"
          . "/审计日志      操作记录\n"
          . "\n"
          . "<b>🏪 商户管理</b>\n"
          . "/查商户 UID    查看商户详情\n"
          . "/封禁商户 UID  禁用商户\n"
          . "/解封商户 UID  解封\n"
          . "/解绑商户 @人  解除 TG 绑定\n"
          . "\n"
          . "<b>👥 管理员管理</b>\n"
          . "/加超管 @人    添加超级管理员\n"
          . "/加管理员 @人  添加管理员\n"
          . "/删管理员 @人  删除管理员\n"
          . "/管理员列表    查看所有管理员\n"
          . "\n"
          . "<b>⚙️ 系统操作</b>\n"
          . "/待审批        查看待审批申请\n"
          . "/审批 ID 通过  处理申请\n"
          . "━━━━━━━━━━━━━━━━━━\n"
          . "💬 直接发消息即可联系客服";
    $kb = ['inline_keyboard' => [[['text' => '🚀 申请加速审核', 'callback_data' => 'cb_speed_review']]]];
    Telegram::sendMessage($chat_id, $text, $kb);
}

function menuSuperAdmin($chat_id) {
    $text = "<b>🛡️ 超级管理员面板</b>\n"
          . "━━━━━━━━━━━━━━━━━━\n"
          . "<b>📊 数据查询</b>\n"
          . "/系统状态      服务器状态\n"
          . "/统计          平台统计\n"
          . "/商户列表      所有商户\n"
          . "/群列表        绑定群组\n"
          . "\n"
          . "<b>🏪 商户管理</b>\n"
          . "/查商户 UID    商户详情\n"
          . "/封禁商户 UID  禁用\n"
          . "/解封商户 UID  解封\n"
          . "/解绑商户 @人  解除绑定\n"
          . "\n"
          . "<b>⚙️ 审批</b>\n"
          . "/待审批        查看申请\n"
          . "/审批 ID 通过  处理申请\n"
          . "━━━━━━━━━━━━━━━━━━\n"
          . "💬 直接发消息即可联系客服";
    $kb = ['inline_keyboard' => [[['text' => '🚀 申请加速审核', 'callback_data' => 'cb_speed_review']]]];
    Telegram::sendMessage($chat_id, $text, $kb);
}

function menuAdmin($chat_id) {
    $text = "<b>🔰 管理员面板</b>\n"
          . "━━━━━━━━━━━━━━━━━━\n"
          . "<b>📊 数据查询</b>\n"
          . "/统计          平台统计\n"
          . "/商户列表      所有商户\n"
          . "/群列表        绑定群组\n"
          . "\n"
          . "<b>🏪 商户管理</b>\n"
          . "/查商户 UID    商户详情\n"
          . "\n"
          . "<b>⚙️ 系统操作申请</b>\n"
          . "/申请 内容     向超管申请操作权限\n"
          . "━━━━━━━━━━━━━━━━━━\n"
          . "💡 系统操作需超管审批";
    $kb = ['inline_keyboard' => [[['text' => '🚀 申请加速审核', 'callback_data' => 'cb_speed_review']]]];
    Telegram::sendMessage($chat_id, $text, $kb);
}

function menuMerchant($chat_id) {
    $uid = Auth::merchantUid($GLOBALS['__tg_msg__']['from']['id'] ?? 0);
    $u = $uid ? DB::one("SELECT uid, email, money, rate FROM pay_user WHERE uid=?", [$uid]) : null;
    if (!$u) return Telegram::sendMessage($chat_id, "❌ 请重新 /快捷登录");
    $text = "✨ <b>欢迎来到商户中心</b> 🎁\n"
          . "━━━━━━━━━━━━━━━━━━\n"
          . "🎁 <b>新商户限时惊喜福利</b> 🎊\n"
          . "🎉 <b>即日起注册，免月费体验全部高级功能（至 2026-11-01）！</b>\n"
          . "👉 <b>✅ 0 费率：平台不抽成，收多少拿多少！</b>\n"
          . "🔥 <b>不限制行业，可对接包网及四方线上平台！</b>\n"
          . "💬 <b>想了解更多？请点击：<a href=\"https://t.me/xindapay1H5_bot\">👉 联系客服 👈</a></b>\n"
          . "━━━━━━━━━━━━━━━━━━\n"
          . "🆔 商户ID：<code>{$u['uid']}</code>\n"
          . "📧 账号：{$u['email']}\n"
          . "💰 余额：<b>" . number_format($u['money'], 2) . " 元</b>\n"
          . "📊 费率：{$u['rate']}%\n"
          . "━━━━━━━━━━━━━━━━━━\n"
          . "/我的          刷新商户信息\n"
          . "/我的订单      最近 10 笔订单\n"
          . "/我的流水      今日/本周/本月汇总\n"
          . "/我的二维码    管理收款码\n"
          . "/待确认        查看待确认订单\n"
          . "/邮件          邮件推送配置\n"
          . "━━━━━━━━━━━━━━━━━━\n"
          . "⚙️ <b>收款设置</b>\n"
          . "/开启手动确认  到账后手动点确认\n"
          . "/关闭手动确认  到账后自动完成\n"
          . "/商户手册      商户操作手册\n"
          . "/下级手册      下级操作手册\n"
          . "━━━━━━━━━━━━━━━━━━\n"
          . "💬 有问题请直接发消息联系客服";
    $kb = ['inline_keyboard' => [[['text' => '🚀 申请加速审核', 'callback_data' => 'cb_speed_review']]]];
    Telegram::sendMessage($chat_id, $text, $kb);
}