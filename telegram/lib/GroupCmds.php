<?php
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/GroupOp.php';
require_once __DIR__ . '/Qrcode.php';

// ========== 群绑定 ==========
function cmdBindMerchantGroup($chat_id, $user_id, $msg) {
    $title = $msg['chat']['title'] ?? '';
    list($ok, $r) = GroupOp::bindMerchantGroup($chat_id, $user_id, $title);
    if (!$ok) return Telegram::sendMessage($chat_id, "❌ {$r}");
    $u = DB::one("SELECT uid, email FROM pay_user WHERE uid=?", [$r]);
    Telegram::sendMessage($chat_id,
        "✅ <b>商户群绑定成功</b>\n"
      . "━━━━━━━━━━━━━━━━━━\n"
      . "🏪 商户：{$u['email']}\n"
      . "🆔 UID：{$u['uid']}\n"
      . "━━━━━━━━━━━━━━━━━━\n"
      . "📌 后续操作：\n"
      . "1. 把机器人设为<b>群管理员</b>（否则收不到消息）\n"
      . "2. 用 <code>/加操作人 @用户名</code> 添加操作人\n"
      . "3. 用 <code>/群上传二维码</code> 上传收款码\n"
      . "4. 订单会自动推送到本群");
    Auth::log($user_id, 'group.bind', "chat_id={$chat_id}", "uid={$r}");
}

function cmdUnbindMerchantGroup($chat_id, $user_id) {
    list($ok, $r) = GroupOp::unbind($chat_id, $user_id);
    if (!$ok) return Telegram::sendMessage($chat_id, "❌ {$r}");
    Telegram::sendMessage($chat_id, "✅ 已解绑商户群");
    Auth::log($user_id, 'group.unbind', "chat_id={$chat_id}");
}

function cmdGroupInfo($chat_id, $user_id) {
    $g = GroupOp::getGroup($chat_id);
    if (!$g) return Telegram::sendMessage($chat_id, "📭 本群未绑定");
    $u = DB::one("SELECT uid, email, money FROM pay_user WHERE uid=?", [$g['uid']]);
    $ops = GroupOp::listOperators($chat_id);
    $t = "📋 <b>本群信息</b>\n"
       . "━━━━━━━━━━━━━━━━━━\n"
       . "📛 群名：{$g['title']}\n"
       . "🆔 Chat ID：<code>{$g['chat_id']}</code>\n"
       . "🏪 归属商户：{$u['email']} (UID {$u['uid']})\n"
       . "💰 余额：￥" . number_format($u['money'], 2) . "\n"
       . "📊 类型：" . ($g['is_merchant_group'] ? '商户群' : '其他') . "\n"
       . "📨 订单推送：" . ($g['push_orders'] ? '✅ 开启' : '❌ 关闭') . "\n"
       . "━━━━━━━━━━━━━━━━━━\n"
       . "👥 <b>操作人（" . count($ops) . "）</b>\n";
    if (!$ops) $t .= "（无，商户本人可操作）\n";
    foreach ($ops as $op) {
        $t .= "• " . ($op['tg_name'] ?: $op['tg_username']) . " <code>{$op['tg_id']}</code>\n";
    }
    Telegram::sendMessage($chat_id, $t);
}

// ========== 操作人管理 ==========
function cmdAddOperator($chat_id, $user_id, $text, $msg) {
    $g = GroupOp::getGroup($chat_id);
    if (!$g || !$g['is_merchant_group']) return Telegram::sendMessage($chat_id, "❌ 本群不是商户群");

    $tid = 0; $username = ''; $name = '';

    // 方式1：回复某人消息
    if (isset($msg['reply_to_message']['from']['id'])) {
        $t = $msg['reply_to_message']['from'];
        $tid = (int)$t['id'];
        $username = $t['username'] ?? '';
        $name = trim(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? ''));
    }
    // 方式2：@username
    elseif (preg_match('#^/加操作人\s+@(\w+)#u', $text, $m)) {
        $username = $m[1];
        $name = '@' . $username;
        // 从已记录的用户里找
        $row = DB::one("SELECT tg_id FROM pay_tguser WHERE username=? ORDER BY last_seen DESC LIMIT 1", [$username]);
        if ($row) $tid = (int)$row['tg_id'];
        else return Telegram::sendMessage($chat_id, "❌ 找不到 @{$username}，让对方先在本群发一条消息");
    }
    // 方式3：数字 TG ID
    elseif (preg_match('#^/加操作人\s+(\d+)#', $text, $m)) {
        $tid = (int)$m[1];
        $name = "ID:{$tid}";
    }
    else {
        return Telegram::sendMessage($chat_id,
            "📖 <b>添加操作人</b>\n"
          . "① 回复某人的消息 → /加操作人\n"
          . "② /加操作人 @用户名\n"
          . "③ /加操作人 TG数字ID");
    }

    if ($tid <= 0) return Telegram::sendMessage($chat_id, "❌ 无效 TG ID");

    list($ok, $r) = GroupOp::addOperator($chat_id, $user_id, $tid, $username, $name);
    if (!$ok) return Telegram::sendMessage($chat_id, "❌ {$r}");
    Telegram::sendMessage($chat_id, "✅ 已添加操作人：{$name} <code>{$tid}</code>");
    Auth::log($user_id, 'group.add_op', "chat_id={$chat_id}", "target={$tid}");
}

function cmdDelOperator($chat_id, $user_id, $text, $msg) {
    $tid = 0; $name = '';
    if (isset($msg['reply_to_message']['from']['id'])) {
        $t = $msg['reply_to_message']['from'];
        $tid = (int)$t['id'];
        $name = trim(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? ''));
    }
    elseif (preg_match('#^/删操作人\s+(\d+)#', $text, $m)) {
        $tid = (int)$m[1]; $name = "ID:{$tid}";
    }
    else return Telegram::sendMessage($chat_id, "用法：回复某人消息 → /删操作人");

    list($ok, $r) = GroupOp::delOperator($chat_id, $user_id, $tid);
    if (!$ok) return Telegram::sendMessage($chat_id, "❌ {$r}");
    Telegram::sendMessage($chat_id, "✅ 已删除操作人：{$name}");
    Auth::log($user_id, 'group.del_op', "chat_id={$chat_id}", "target={$tid}");
}

function cmdListOperators($chat_id, $user_id) {
    $ops = GroupOp::listOperators($chat_id);
    if (!$ops) return Telegram::sendMessage($chat_id, "📭 暂无操作人");
    $t = "👥 <b>本群操作人</b>\n━━━━━━━━━━━━━━━━━━\n";
    foreach ($ops as $op) {
        $t .= "• " . ($op['tg_name'] ?: '未命名')
            . " <code>{$op['tg_id']}</code>"
            . ($op['tg_username'] ? " @{$op['tg_username']}" : '')
            . "\n";
    }
    Telegram::sendMessage($chat_id, $t);
}

// ========== 群内上传二维码 ==========
function cmdGroupQrStart($chat_id, $user_id) {
    if (!GroupOp::canOperate($chat_id, $user_id)) {
        return Telegram::sendMessage($chat_id, "❌ 你没有操作权限（需商户本人或操作人）");
    }
    // 弹类型选择
    $kb = [];
    foreach (Qrcode::$PAY_TYPES as $ch => $info) {
        $kb[] = [['text' => $info['label'], 'callback_data' => "gqr_type_{$chat_id}_{$ch}"]];
    }
    $kb[] = [['text' => '❌ 取消', 'callback_data' => 'gqr_cancel']];
    Telegram::sendMessage($chat_id,
        "📱 <b>选择收款方式类型</b>\n\n"
      . "选好后请发送二维码图片\n"
      . "发送 /取消 可中止",
        ['inline_keyboard' => $kb]);
}

function cmdGroupQrList($chat_id, $user_id) {
    if (!GroupOp::canOperate($chat_id, $user_id)) {
        return Telegram::sendMessage($chat_id, "❌ 无权限");
    }
    $uid = GroupOp::groupMerchantUid($chat_id);
    if (!$uid) return Telegram::sendMessage($chat_id, "❌ 本群未绑定商户");
    $list = Qrcode::listByUid($uid);
    if (!$list) return Telegram::sendMessage($chat_id, "📱 该商户还没有二维码");
    $t = "📱 <b>商户二维码（UID {$uid}）</b>\n━━━━━━━━━━━━━━━━━━\n";
    $kb = [];
    foreach ($list as $q) {
        $st = $q['status'] == 1 ? '🟢' : '🔴';
        $label = Qrcode::label($q['channel']);
        $t .= "{$st} <b>{$label}</b>\n"
            . "   {$q['remark']}\n"
            . "   已用 {$q['use_count']} 次 · <code>#{$q['id']}</code>\n\n";
        $kb[] = [
            ['text' => "❌ 关闭 #{$q['id']}", 'callback_data' => "gqr_disable_{$q['id']}"],
            ['text' => "🗑 删除 #{$q['id']}", 'callback_data' => "gqr_del_{$q['id']}"],
        ];
    }
    $kb[] = [['text' => '➕ 上传新码', 'callback_data' => 'gqr_add']];
    Telegram::sendMessage($chat_id, $t, ['inline_keyboard' => $kb]);
}
