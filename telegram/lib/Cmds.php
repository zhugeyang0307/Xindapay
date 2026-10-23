<?php
require_once __DIR__ . '/Auth.php';

// ========== 商户自查 ==========
function cmdMyInfo($chat_id, $user_id) {
    $uid = Auth::merchantUid($user_id);
    if (!$uid) return Telegram::sendMessage($chat_id, "❌ 请先 /快捷登录");
    $u = DB::one("SELECT uid, email, phone, money, rate, addtime FROM pay_user WHERE uid=?", [$uid]);
    if (!$u) return Telegram::sendMessage($chat_id, "❌ 商户不存在");
    $today = date('Y-m-d') . ' 00:00:00';
    $cnt = DB::col("SELECT COUNT(*) FROM pay_order WHERE uid=? AND status=1 AND addtime>=?", [$uid, $today]);
    $sum = DB::col("SELECT COALESCE(SUM(realmoney),0) FROM pay_order WHERE uid=? AND status=1 AND addtime>=?", [$uid, $today]);
    $text = "<b>🏪 我的商户信息</b>\n━━━━━━━━━━━━━━━━━━\n"
          . "🆔 商户ID：<code>{$u['uid']}</code>\n"
          . "📧 账号：{$u['email']}\n"
          . "💰 余额：<b>" . number_format($u['money'], 2) . " 元</b>\n"
          . "📊 费率：{$u['rate']}%\n"
          . "📅 注册：" . substr($u['addtime'], 0, 10) . "\n"
          . "━━━━━━━━━━━━━━━━━━\n"
          . "<b>📈 今日数据</b>\n订单数：{$cnt} 笔\n流水：￥" . number_format($sum, 2);
    Telegram::sendMessage($chat_id, $text);
    Auth::log($user_id, 'self.info', "uid={$uid}");
}

function cmdMyOrders($chat_id, $user_id, $text) {
    $uid = Auth::merchantUid($user_id);
    if (!$uid) return Telegram::sendMessage($chat_id, "❌ 请先 /快捷登录");
    $orders = DB::query("SELECT trade_no, name, realmoney, status, addtime FROM pay_order WHERE uid=? ORDER BY addtime DESC LIMIT 10", [$uid]);
    if (!$orders) return Telegram::sendMessage($chat_id, "📋 暂无订单");
    $t = "📋 <b>最近 10 笔订单</b>\n━━━━━━━━━━━━━━━━━━\n";
    foreach ($orders as $o) {
        $st = $o['status'] == 1 ? '✅' : '⏳';
        $hm = date('m-d H:i', strtotime($o['addtime']));
        $t .= "{$st} ￥" . number_format($o['realmoney'], 2) . "  <code>{$o['trade_no']}</code>\n     {$o['name']} · {$hm}\n";
    }
    Telegram::sendMessage($chat_id, $t);
}

function cmdMyBill($chat_id, $user_id, $text) {
    $uid = Auth::merchantUid($user_id);
    if (!$uid) return Telegram::sendMessage($chat_id, "❌ 请先 /快捷登录");
    $today = date('Y-m-d') . ' 00:00:00';
    $week  = date('Y-m-d 00:00:00', strtotime('monday this week'));
    $month = date('Y-m-01 00:00:00');
    $t = "<b>📊 我的流水汇总</b>\n━━━━━━━━━━━━━━━━━━\n";
    foreach (['今日'=>$today, '本周'=>$week, '本月'=>$month] as $label => $start) {
        $cnt = DB::col("SELECT COUNT(*) FROM pay_order WHERE uid=? AND status=1 AND addtime>=?", [$uid, $start]);
        $sum = DB::col("SELECT COALESCE(SUM(realmoney),0) FROM pay_order WHERE uid=? AND status=1 AND addtime>=?", [$uid, $start]);
        $t .= "{$label}：{$cnt} 笔 / ￥" . number_format($sum, 2) . "\n";
    }
    Telegram::sendMessage($chat_id, $t);
}

// ========== 管理员查商户 ==========
function cmdMerchantInfo($chat_id, $user_id, $text) {
    if (!preg_match('#^/查商户\s+(\d+)#u', $text, $m)) {
        return Telegram::sendMessage($chat_id, "用法：/查商户 商户UID\n例：/查商户 1000");
    }
    $uid = (int)$m[1];
    $u = DB::one("SELECT uid, email, money, rate, status, addtime FROM pay_user WHERE uid=?", [$uid]);
    if (!$u) return Telegram::sendMessage($chat_id, "❌ 商户不存在");
    $bind = DB::one("SELECT tg_id FROM pay_tgbind WHERE uid=? LIMIT 1", [$uid]);
    $tgInfo = $bind ? "<code>{$bind['tg_id']}</code>" : '未绑定';
    $t = "<b>🏪 商户详情</b>\n━━━━━━━━━━━━━━━━━━\n"
       . "🆔 ID：<code>{$u['uid']}</code>\n"
       . "📧 账号：{$u['email']}\n"
       . "💰 余额：￥" . number_format($u['money'], 2) . "\n"
       . "📊 费率：{$u['rate']}%\n"
       . "📱 TG：{$tgInfo}\n"
       . "🎫 状态：" . ($u['status'] == 1 ? '✅ 正常' : '❌ 禁用') . "\n"
       . "📅 注册：" . substr($u['addtime'], 0, 10);
    Telegram::sendMessage($chat_id, $t);
    Auth::log($user_id, 'merchant.view', "uid={$uid}");
}

function cmdMerchantList($chat_id, $user_id, $text) {
    $rows = DB::query("SELECT uid, email, money, status FROM pay_user ORDER BY uid DESC LIMIT 30");
    if (!$rows) return Telegram::sendMessage($chat_id, "无商户");
    $t = "📋 <b>商户列表（最近 30）</b>\n━━━━━━━━━━━━━━━━━━\n";
    foreach ($rows as $r) {
        $st = $r['status'] == 1 ? '✅' : '❌';
        $t .= "{$st} <code>{$r['uid']}</code> {$r['email']}  ￥" . number_format($r['money'], 2) . "\n";
    }
    Telegram::sendMessage($chat_id, $t);
    Auth::log($user_id, 'merchant.list', '');
}

// ========== 系统状态 ==========
function cmdSysStatus($chat_id, $user_id) {
    // CPU
    $cpuInfo = file_get_contents('/proc/cpuinfo');
    $cores = max(1, substr_count($cpuInfo, 'processor'));
    $load = function_exists('sys_getloadavg') ? sys_getloadavg() : [0,0,0];
    $cpu = round($load[0] * 100 / $cores, 1);
    // 内存
    $mi = file_get_contents('/proc/meminfo');
    preg_match('/MemTotal:\s+(\d+)/', $mi, $mt);
    preg_match('/MemAvailable:\s+(\d+)/', $mi, $ma);
    $memT = round($mt[1] / 1048576, 2);
    $memF = round($ma[1] / 1048576, 2);
    $memU = round($memT - $memF, 2);
    // 磁盘
    $diskF = disk_free_space('/');
    $diskT = disk_total_space('/');
    $diskU = ($diskT - $diskF) / 1073741824;
    $diskT = $diskT / 1073741824;
    // Docker
    $docker = @shell_exec('docker ps --format "{{.Names}}: {{.Status}}" 2>/dev/null');
    $docker = $docker ? trim($docker) : '无容器';
    // 机器人
    $bot = @shell_exec('systemctl is-active paybot.service 2>/dev/null');
    $bot = $bot ? trim($bot) : 'unknown';
    // 平台数据
    $usr = DB::col("SELECT COUNT(*) FROM pay_user");
    $today = date('Y-m-d') . ' 00:00:00';
    $orders = DB::col("SELECT COUNT(*) FROM pay_order WHERE status=1 AND addtime>=?", [$today]);
    $money = DB::col("SELECT COALESCE(SUM(realmoney),0) FROM pay_order WHERE status=1 AND addtime>=?", [$today]);
    $t = "<b>🖥️ 服务器状态</b>\n━━━━━━━━━━━━━━━━━━\n"
       . "CPU：{$cpu}%（{$cores} 核）\n"
       . "内存：{$memU}G / {$memT}G\n"
       . "磁盘：" . round($diskU,1) . "G / " . round($diskT,1) . "G\n"
       . "━━━━━━━━━━━━━━━━━━\n"
       . "<b>🐳 Docker</b>\n{$docker}\n"
       . "━━━━━━━━━━━━━━━━━━\n"
       . "<b>🤖 机器人</b>\npaybot：{$bot}\n"
       . "━━━━━━━━━━━━━━━━━━\n"
       . "<b>📊 平台数据</b>\n"
       . "商户数：{$usr}\n今日订单：{$orders} 笔\n今日流水：￥" . number_format($money, 2);
    Telegram::sendMessage($chat_id, $t);
    Auth::log($user_id, 'sys.status', '');
}

// ========== 商户二维码管理 ==========
function qrListCmd($chat_id, $user_id) {
    $uid = Auth::merchantUid($user_id);
    if (!$uid) return Telegram::sendMessage($chat_id, "❌ 请先 /快捷登录");
    Auth::log($user_id, 'qr.list', "uid={$uid}");
    $list = Qrcode::listByUid($uid);
    if (!$list) {
        return Telegram::sendMessage($chat_id,
            "📱 你还没有二维码\n\n点击下方按钮添加第一个：",
            ['inline_keyboard' => [
                [['text' => '➕ 添加二维码', 'callback_data' => 'qr_add']],
                [['text' => '🔄 刷新', 'callback_data' => 'qr_list']]
            ]]);
    }
    $t = "📱 <b>我的二维码（{$uid}）</b>\n━━━━━━━━━━━━━━━━━━\n";
    $kb = [];
    foreach ($list as $q) {
        $st = $q['status'] == 1 ? '🟢' : '🔴';
        $used = $q['use_count'] ?? 0;
        $label = Qrcode::label($q['channel']);
        $t .= "{$st} <b>{$label}</b>\n"
            . "   备注：{$q['remark']}\n"
            . "   已用：{$used} 次\n"
            . "   <code>#{$q['id']}</code>\n\n";
        $kb[] = [
            ['text' => "⚙️ 设置 #{$q['id']}", 'callback_data' => "qr_set_{$q['id']}"],
            ['text' => "🗑 删除 #{$q['id']}", 'callback_data' => "qr_del_{$q['id']}"],
        ];
    }
    $kb[] = [['text' => '➕ 添加二维码', 'callback_data' => 'qr_add']];
    $kb[] = [['text' => '🔄 刷新', 'callback_data' => 'qr_list']];
    Telegram::sendMessage($chat_id, $t, ['inline_keyboard' => $kb]);
}

function qrAddCmd($chat_id, $user_id) {
    $uid = Auth::merchantUid($user_id);
    if (!$uid) return Telegram::sendMessage($chat_id, "❌ 请先 /快捷登录");
    $kb = [];
    foreach (Qrcode::$PAY_TYPES as $ch => $info) {
        $kb[] = [['text' => $info['label'], 'callback_data' => "qr_type_{$ch}"]];
    }
    $kb[] = [['text' => '❌ 取消', 'callback_data' => 'qr_cancel']];
    Telegram::sendMessage($chat_id,
        "📱 <b>选择要添加的收款方式类型</b>\n\n"
      . "选择后请直接发送二维码图片给机器人",
        ['inline_keyboard' => $kb]);
}

function qrDelCmd($chat_id, $user_id, $text) {
    if (!preg_match('#^/删除二维码\s+(\d+)#u', $text, $m)) {
        return Telegram::sendMessage($chat_id, "用法：/删除二维码 二维码ID\n例：/删除二维码 27");
    }
    $uid = Auth::merchantUid($user_id);
    if (!$uid) return Telegram::sendMessage($chat_id, "❌ 请先 /快捷登录");
    list($ok, $r) = Qrcode::delete($uid, (int)$m[1]);
    if (!$ok) return Telegram::sendMessage($chat_id, "❌ {$r}");
    Auth::log($user_id, 'qr.del', "id={$m[1]}", "uid={$uid}");
    Telegram::sendMessage($chat_id, "✅ 已删除二维码 #{$m[1]}");
}

// ========== 订单确认 ==========
function cmdToggleConfirm($chat_id, $user_id, $text) {
    $uid = Auth::merchantUid($user_id);
    if (!$uid) return Telegram::sendMessage($chat_id, "❌ 请先 /快捷登录");
    $on = (strpos($text, '/开启手动确认') === 0) ? 1 : 0;
    OrderConfirm::setConfirm($uid, $on);
    $st = $on ? '✅ 已开启' : '✅ 已关闭';
    $desc = $on
        ? "📱 到账后机器人会推送给你，需要你手动点【✅ 确认到账】"
        : "⚡ 到账后自动匹配完成，无需手动操作";
    Telegram::sendMessage($chat_id, "<b>手动确认模式 {$st}</b>\n\n{$desc}");
    Auth::log($user_id, 'confirm.toggle', "uid={$uid}", "on={$on}");
}

function cmdPendingList($chat_id, $user_id) {
    $uid = Auth::merchantUid($user_id);
    if (!$uid) return Telegram::sendMessage($chat_id, "❌ 请先 /快捷登录");
    $list = OrderConfirm::pending($uid);
    if (!$list) return Telegram::sendMessage($chat_id, "📋 暂无待确认订单");
    $t = "📋 <b>待确认订单</b>\n━━━━━━━━━━━━━━━━━━\n";
    $kb = [];
    foreach ($list as $p) {
        $hm = date('m-d H:i', strtotime($p['addtime']));
        $t .= "🔔 ￥" . number_format($p['amount'], 2) . "  <code>{$p['trade_no']}</code>\n     {$hm}\n";
        $kb[] = [
            ['text' => "✅ 确认 #{$p['id']}", 'callback_data' => "conf_ok_{$p['id']}"],
            ['text' => "❌ 忽略 #{$p['id']}", 'callback_data' => "conf_no_{$p['id']}"]
        ];
    }
    Telegram::sendMessage($chat_id, $t, ['inline_keyboard' => $kb]);
}

// ========== 审计日志（仅 Owner）==========
function cmdAuditLog($chat_id, $user_id, $text) {
    if (!Auth::isOwner($user_id)) {
        return Telegram::sendMessage($chat_id, "❌ 此功能仅所有者可用");
    }

    $opts = ['limit' => 30];
    $title = '今日审计日志';

    // 解析参数：/审计日志 [日期|UID|筛选]
    $arg = trim(substr($text, strlen('/审计日志')));
    if ($arg !== '') {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $arg)) {
            // 指定日期
            $opts['date'] = $arg;
            $title = "审计日志 {$arg}";
        } elseif (preg_match('/^\d+$/', $arg)) {
            // 指定操作者 ID
            $opts['tg_id'] = (int)$arg;
            $title = "操作者 {$arg} 的日志";
        } elseif ($arg === '今天') {
            $opts['date'] = date('Y-m-d');
        } elseif ($arg === '昨天') {
            $opts['date'] = date('Y-m-d', strtotime('-1 day'));
            $title = "审计日志 " . $opts['date'];
        } elseif (preg_match('/^[a-z_.]+$/i', $arg)) {
            $opts['action'] = $arg;
            $title = "操作类型 {$arg}";
        }
    } else {
        $opts['date'] = date('Y-m-d');
    }

    $rows = Audit::query($opts);
    if (!$rows) {
        return Telegram::sendMessage($chat_id, "📋 <b>{$title}</b>\n\n暂无记录");
    }

    $t = "📋 <b>{$title}</b>\n━━━━━━━━━━━━━━━━━━\n";
    foreach ($rows as $r) {
        $icon = Audit::roleIcon($r['role']);
        $aname = Audit::actionName($r['action']);
        $tm = date('H:i:s', strtotime($r['addtime']));
        $tmFull = $r['addtime'];
        $tgId = $r['tg_id'];
        $target = $r['target'] ? " → <code>{$r['target']}</code>" : '';
        $detail = $r['detail'] ? "\n      💬 {$r['detail']}" : '';

        $t .= "{$icon} <code>{$tgId}</code> · {$tm}\n"
            . "      <b>{$aname}</b>{$target}{$detail}\n\n";
    }

    $stats = Audit::stats($opts['date'] ?? date('Y-m-d'));
    $t .= "━━━━━━━━━━━━━━━━━━\n"
        . "📊 共 <b>{$stats['total']}</b> 条记录 · <b>{$stats['actors']}</b> 位操作者\n"
        . "🕐 时间：{$tmFull}\n"
        . "\n"
        . "💡 用法：\n"
        . "/审计日志 今天\n"
        . "/审计日志 昨天\n"
        . "/审计日志 2026-10-11\n"
        . "/审计日志 7772671784（按操作者）\n"
        . "/审计日志 order（按操作类型）";

    Telegram::sendMessage($chat_id, $t);
}

// ========== 二维码设置界面 ==========
function qrSettingsMenu($chat_id, $uid, $id) {
    $r = qrGetDetail($uid, $id);
    if (!$r) return Telegram::sendMessage($chat_id, "❌ 二维码不存在");
    $label = Qrcode::label($r['channel']);
    $t = "⚙️ <b>设置二维码 #{$id}</b>\n"
       . "━━━━━━━━━━━━━━━━━━\n"
       . "类型：{$label}\n"
       . "备注：{$r['remark']}\n"
       . "━━━━━━━━━━━━━━━━━━\n"
       . "💰 最小金额：<b>￥" . number_format($r['min_amount'], 2) . "</b>\n"
       . "💰 最大金额：<b>￥" . number_format($r['max_amount'], 2) . "</b>\n"
       . "📊 浮动费率：<b>{$r['float_rate']}%</b>\n"
       . "📈 每日笔数限制：<b>{$r['day_limit']}</b>\n"
       . "💰 每日金额限制：<b>￥" . number_format($r['day_max_amount'], 2) . "</b>\n"
       . "━━━━━━━━━━━━━━━━━━\n"
       . "💡 0 表示不限制";
    $kb = [
        'inline_keyboard' => [
            [['text' => '💰 最小金额', 'callback_data' => "qr_edit_{$id}_min_amount"],
             ['text' => '💰 最大金额', 'callback_data' => "qr_edit_{$id}_max_amount"]],
            [['text' => '📊 浮动费率', 'callback_data' => "qr_edit_{$id}_float_rate"],
             ['text' => '📈 每日笔数', 'callback_data' => "qr_edit_{$id}_day_limit"]],
            [['text' => '💰 每日金额', 'callback_data' => "qr_edit_{$id}_day_max_amount"],
             ['text' => '📝 备注', 'callback_data' => "qr_edit_{$id}_remark"]],
            [['text' => '🔄 刷新', 'callback_data' => "qr_set_{$id}"],
             ['text' => '⬅️ 返回列表', 'callback_data' => 'qr_list']]
        ]
    ];
    Telegram::sendMessage($chat_id, $t, $kb);
}

function qrPromptEdit($chat_id, $tgId, $id, $field, $uid) {
    $labels = [
        'min_amount'     => ['最小金额（元）', '请输入最低收款金额，如 1 或 0.01'],
        'max_amount'     => ['最大金额（元）', '请输入最高收款金额，如 5000'],
        'float_rate'     => ['浮动费率（%）', '在商户费率基础上浮动，可正可负，如 0.5 或 -0.3'],
        'day_limit'      => ['每日笔数', '每天最多收几笔，0 表示不限'],
        'day_max_amount' => ['每日金额（元）', '每天最多收多少钱，0 表示不限'],
        'remark'         => ['备注', '给这个二维码起个名字'],
    ];
    if (!isset($labels[$field])) return Telegram::sendMessage($chat_id, "❌ 未知参数");
    list($title, $hint) = $labels[$field];
    // 保存状态
    DB::exec("REPLACE INTO pay_tg_state (tg_id, action, data, updated_at) VALUES (?,?,?,?)",
             [$tgId, 'qr_edit', "$id|$field", time()]);
    Telegram::sendMessage($chat_id,
        "✏️ <b>修改 {$title}</b>\n\n{$hint}\n\n发送 /cancel 取消",
        ['inline_keyboard' => [[['text' => '❌ 取消', 'callback_data' => "qr_set_{$id}"]]]]);
}

// ========== APK 开通引导 / 我的激活码 ==========
function cmdBuyApk($chat_id, $user_id) {
    $uid = Auth::merchantUid($user_id);
    if (!$uid) return Telegram::sendMessage($chat_id, "❌ 请先 /快捷登录");

    $u = DB::one("SELECT service_expire, service_trial_end, apk_quota, cert FROM pay_user WHERE uid=?", [$uid]);
    $now = time();
    $e   = $u['service_expire'] ? strtotime($u['service_expire']) : 0;
    $tr  = $u['service_trial_end'] ? strtotime($u['service_trial_end']) : 0;

    if ($e > $now)              $status = '🟢 使用中';
    elseif ($tr > $now)         $status = '🟡 试用中';
    elseif ($e > 0 || $tr > 0)  $status = '🔴 已到期';
    else                        $status = '⚪ 未开通';

    $expire = max($e, $tr);
    $expireStr = $expire > 0 ? date('Y-m-d H:i:s', $expire) : '—';

    $used   = (int)DB::col("SELECT COUNT(*) FROM pay_license WHERE uid=? AND device_id IS NOT NULL AND device_id<>'' AND status<>2", [$uid]);
    $quota  = (int)($u['apk_quota'] ?: 3);
    $remain = max(0, $quota - $used);

    $t  = "📱 <b>收款监听 APK · 开通说明</b>\n";
    $t .= "━━━━━━━━━━━━━━━━━━\n";
    $t .= "🆔 商户ID：<code>{$uid}</code>\n";
    $t .= "📊 服务状态：{$status}\n";
    $t .= "📅 到期时间：{$expireStr}\n";
    $t .= "📱 设备额度：{$used} / {$quota}（剩余 {$remain}）\n";
    $t .= "🪪 实名认证：" . ($u['cert'] ? '✅ 已通过' : '⚠️ 未认证') . "\n";
    $t .= "━━━━━━━━━━━━━━━━━━\n";
    $t .= "<b>开通 / 领取流程</b>\n";
    $t .= "1. 前往商户后台 → 系统服务 → 开通（100 USDT / 月）\n";
    $t .= "2. 每份额度含 <b>3 台</b> APK 设备，超出每台 <b>50 USDT / 月</b>\n";
    $t .= "3. 开通后 → 我的激活码 → 点击「领取一个激活码」\n";
    $t .= "4. 打开 APK 输入「激活码 + 密钥」即可\n";
    $t .= "━━━━━━━━━━━━━━━━━━\n";
    $t .= "⚠️ 激活码绑定设备后<b>禁止换绑</b>，请确认手机长期可用\n";
    $t .= "💡 注册即送 <b>2 天</b> 免费试用";

    $kb = ['inline_keyboard' => [[
        ['text' => '🌐 前往商户后台', 'url' => 'https://pay.yunzexinda.com/user/service.php'],
        ['text' => '📋 我的激活码', 'callback_data' => 'apk_list'],
    ]]];
    Telegram::sendMessage($chat_id, $t, $kb);
    Auth::log($user_id, 'apk.guide', "uid={$uid}");
}

function cmdMyApk($chat_id, $user_id) {
    $uid = Auth::merchantUid($user_id);
    if (!$uid) return Telegram::sendMessage($chat_id, "❌ 请先 /快捷登录");

    $u = DB::one("SELECT service_expire, service_trial_end, apk_quota FROM pay_user WHERE uid=?", [$uid]);
    $now = time();
    $e   = $u['service_expire'] ? strtotime($u['service_expire']) : 0;
    $tr  = $u['service_trial_end'] ? strtotime($u['service_trial_end']) : 0;

    if ($e > $now)              $status = '🟢 使用中';
    elseif ($tr > $now)         $status = '🟡 试用中';
    elseif ($e > 0 || $tr > 0)  $status = '🔴 已到期';
    else                        $status = '⚪ 未开通';

    $expire = max($e, $tr);
    $expireStr = $expire > 0 ? date('Y-m-d H:i:s', $expire) : '—';

    $used   = (int)DB::col("SELECT COUNT(*) FROM pay_license WHERE uid=? AND device_id IS NOT NULL AND device_id<>'' AND status<>2", [$uid]);
    $quota  = (int)($u['apk_quota'] ?: 3);
    $remain = max(0, $quota - $used);

    $t  = "📱 <b>我的激活码</b>\n";
    $t .= "━━━━━━━━━━━━━━━━━━\n";
    $t .= "📊 服务状态：{$status}\n";
    $t .= "📅 到期时间：{$expireStr}\n";
    $t .= "📱 额度：{$used} / {$quota}（剩余 {$remain}）\n";
    $t .= "━━━━━━━━━━━━━━━━━━\n";

    $licenses = ApkOrder::licensesByUid($uid);
    if (!$licenses) {
        $t .= "📭 暂无激活码\n\n请前往商户后台 → 我的激活码 → 领取\n";
    } else {
        foreach ($licenses as $l) {
            $exp = $l['expire_time'] ? strtotime($l['expire_time']) : 0;
            if ($l['status'] == 2)           $st = '🚫 已禁用';
            elseif ($exp > 0 && $exp < $now) $st = '❌ 已过期';
            elseif ($l['device_id'])         $st = '🟢 已激活';
            else                             $st = '🟡 待激活';
            $t .= "{$st}\n";
            $t .= "🔑 激活码：<code>{$l['code']}</code>\n";
            if (!empty($l['secret'])) $t .= "🔐 密钥：<code>{$l['secret']}</code>\n";
            $t .= "📅 到期：" . ($l['expire_time'] ? substr($l['expire_time'], 0, 10) : '-') . "\n";
            $t .= "📱 设备：" . ($l['device_id'] ? substr($l['device_id'], 0, 12) . '...' : '未绑定') . "\n\n";
        }
    }
    $t .= "━━━━━━━━━━━━━━━━━━\n";
    $t .= "📱 下载 APK：https://pay.yunzexinda.com/xinda_listener.apk\n";
    $t .= "激活步骤：\n";
    $t .= "1. 打开 APK，输入上方激活码 + 密钥\n";
    $t .= "2. 填入服务器地址\n";
    $t .= "3. 开启通知监听权限\n";
    $t .= "4. 保存配置";

    $kb = ['inline_keyboard' => [
        [
            ['text' => '🌐 系统服务 / 续费', 'url' => 'https://pay.yunzexinda.com/user/service.php'],
            ['text' => '🎁 领取新激活码', 'url' => 'https://pay.yunzexinda.com/user/license.php'],
        ],
        [
            ['text' => '🔄 刷新', 'callback_data' => 'apk_list'],
        ]
    ]];
    Telegram::sendMessage($chat_id, $t, $kb);
}

function cmdMailPush($chat_id, $user_id) {
    $uid = Auth::merchantUid($user_id);

    // 所有者 / 管理员：没绑商户时，给出平台侧说明
    if (!$uid) {
        $role = Auth::role($user_id);
        if ($role === Auth::ROLE_OWNER || $role === Auth::ROLE_SUPERADMIN || $role === Auth::ROLE_ADMIN) {
            $kb = ['inline_keyboard' => [
                [['text' => '⚙️ 邮件推送后台（商户配置）', 'url' => 'https://pay.yunzexinda.com/user/mail_push.php']],
                [['text' => '📖 使用说明', 'url' => 'https://pay.yunzexinda.com/help/docs/邮件推送说明.html']],
            ]];
            $txt = "<b>📧 邮件推送</b>\n"
                 . "━━━━━━━━━━━━━━━━━━\n"
                 . "👑 你是管理员身份，未绑定商户\n"
                 . "如要测试商户侧邮件推送，请：\n"
                 . "1. 用商户号登录网页后台 → 邮件推送\n"
                 . "2. 或把本 TG 绑定到某个商户：\n"
                 . "   <code>INSERT INTO pay_tgbind VALUES (TG_ID, UID, NOW())</code>\n"
                 . "━━━━━━━━━━━━━━━━━━\n"
                 . "💰 费率：开户即用，不抽成，不按笔收费";
            return Telegram::sendMessage($chat_id, $txt, $kb);
        }
        return Telegram::sendMessage($chat_id, "❌ 请先 /快捷登录");
    }
    $c = DB::one("SELECT mail_enabled, mail_to, mail_smtp_host, mail_smtp_port, mail_smtp_user, mail_smtp_secure, mail_from_name FROM pay_user_botconfig WHERE uid=?", [$uid]);

    $enabled = intval($c['mail_enabled'] ?? 0);
    $status  = $enabled === 1 ? '✅ 已启用' : '⭕ 未启用';
    $mailTo  = htmlspecialchars($c['mail_to'] ?? '未设置', ENT_QUOTES, 'UTF-8');
    $smtp    = htmlspecialchars($c['mail_smtp_host'] ?? '未设置', ENT_QUOTES, 'UTF-8');
    $user    = htmlspecialchars($c['mail_smtp_user'] ?? '未设置', ENT_QUOTES, 'UTF-8');
    $fromN   = htmlspecialchars($c['mail_from_name'] ?? '默认', ENT_QUOTES, 'UTF-8');

    $text = "<b>📧 邮件推送配置</b>\n"
          . "━━━━━━━━━━━━━━━━━━\n"
          . "状态：{$status}\n"
          . "收件邮箱：<code>{$mailTo}</code>\n"
          . "SMTP：<code>{$smtp}</code>\n"
          . "发件邮箱：<code>{$user}</code>\n"
          . "发件名：{$fromN}\n"
          . "━━━━━━━━━━━━━━━━━━\n"
          . "<b>💡 邮件推送操作提示</b>\n"
          . "• 商户自带邮箱发件（需填应用密码，不是登录密码）\n"
          . "• 邮件延迟 1-2 分钟，急单建议同时开启 TG 推送\n"
          . "• 首次使用请检查垃圾邮件箱\n"
          . "• 建议收件邮箱 = 发件邮箱（自己给自己发，不进垃圾箱）\n"
          . "━━━━━━━━━━━━━━━━━━\n"
          . "💰 费率：开户即用，不抽成，不按笔收费";

    $kb = ['inline_keyboard' => [
        [['text' => '⚙️ 打开邮件推送配置', 'url' => 'https://pay.yunzexinda.com/user/mail_push.php']],
        [['text' => '📖 使用说明', 'url' => 'https://pay.yunzexinda.com/help/docs/邮件推送说明.html']],
        [['text' => '📨 申请应用密码（Gmail）', 'url' => 'https://myaccount.google.com/apppasswords']],
    ]];

    Telegram::sendMessage($chat_id, $text, $kb);
    Auth::log($user_id, 'self.mail', "uid={$uid}");
}
