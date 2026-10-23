<?php
/**
 * 商户自建机器人 Webhook 入口
 * 功能：① id/ID 查询  ② 全套工具命令（查 T地址、监控、汇率、计算器等）
 */
require_once __DIR__ . '/includes/common.php';
require_once __DIR__ . '/includes/bot_sender.php';

// 载入 telegram 工具类
require_once __DIR__ . '/telegram/config.php';
require_once __DIR__ . '/telegram/lib/DB.php';
require_once __DIR__ . '/telegram/lib/Telegram.php';
require_once __DIR__ . '/telegram/lib/Commands.php';

$key = $_GET['key'] ?? '';
if (!$key) { http_response_code(200); exit('no key'); }

$bc = $DB->getRow("SELECT * FROM pay_user_botconfig WHERE own_bot_webhook_key=:k LIMIT 1", [':k'=>$key]);
if (!$bc || empty($bc['own_bot_token'])) { http_response_code(200); exit('unknown'); }
$uid = intval($bc['uid']);

$input = file_get_contents('php://input');
$upd = json_decode($input, true);
if (!$upd) { http_response_code(200); exit('ok'); }

// ===== 按钮回调处理（确认到账 / 忽略） =====
if (isset($upd['callback_query'])) {
    @file_put_contents('/tmp/user_bot_debug.log',
        date('H:i:s') . " [CALLBACK] from.id=" . ($upd['callback_query']['from']['id'] ?? 'NULL') .
        " username=" . ($upd['callback_query']['from']['username'] ?? 'NULL') .
        " data=" . ($upd['callback_query']['data'] ?? 'NULL') .
        " chat=" . ($upd['callback_query']['message']['chat']['id'] ?? 'NULL') .
        " chatType=" . ($upd['callback_query']['message']['chat']['type'] ?? 'NULL') . "\n",
        FILE_APPEND);
    // 切到商户自建 bot token（否则 answerCallback 会用默认主机器人 token）
    \Telegram::useToken($bc['own_bot_token']);
    $cq = $upd['callback_query'];
    $cbData = $cq['data'] ?? '';
    
    if ($cbData === 'sb_help') {
        \Telegram::answerCallback($cq['id'], '查看说明');
        $__helpText = "📖 <b>使用说明</b>\n\n"
                    . "1. 配置自建机器人后，将机器人拉进群并设为管理员。\n"
                    . "2. 在群里发送 <code>/绑定群</code>，即可自动绑定该群接收订单。\n"
                    . "3. 在群里发送 <code>/解绑群</code> 可解除绑定。\n"
                    . "4. 私聊机器人发送 <code>/start</code> 可查看菜单。\n"
                    . "5. 订单产生时，点击消息下方的【确认到账】按钮即可完成结单。";
        \Telegram::sendMessage($cq['message']['chat']['id'] ?? $cq['from']['id'], $__helpText);
        http_response_code(200); exit('ok');
    }
    $cid = $cq['from']['id'] ?? 0;
    $__replyChat = $cq['message']['chat']['id'] ?? $cid;
    $__chatType = $cq['message']['chat']['type'] ?? 'private';

    // ===== 权限判断：谁能点确认/忽略 =====
    $__allow = false;

    // 【新增】如果点击人 = 二维码绑定的操作人，也允许（精细授权）
    if (preg_match('/conf_(?:ok|no)_(\d+)/', $cbData ?? '', $__m)) {
        $__pendingIdPerm = intval($__m[1]);
        $__qrcodeOp = $DB->getColumn(
            "SELECT q.operator_tg 
             FROM pay_tg_pending_order p 
             LEFT JOIN pay_order o ON p.trade_no = o.trade_no 
             LEFT JOIN pay_qrcode q ON JSON_UNQUOTE(JSON_EXTRACT(o.ext, '$.qrcode_id')) = q.id 
             WHERE p.id = :pid LIMIT 1",
            [':pid' => $__pendingIdPerm]
        );
        if (!empty($__qrcodeOp) && (string)$cid === (string)$__qrcodeOp) {
            $__allow = true;
            @file_put_contents('/tmp/user_bot_debug.log',
                date('H:i:s') . " [PERM-QR] 允许 cid={$cid}（码绑定的操作人）\n", FILE_APPEND);
        }
        // 如果点击人 = 码绑定的推送目标（且是私聊正数）→ 也允许
        if (!$__allow) {
            $__qGroupId = $DB->getColumn(
                "SELECT q.group_id
                 FROM pay_tg_pending_order p
                 LEFT JOIN pay_order o ON p.trade_no = o.trade_no
                 LEFT JOIN pay_qrcode q ON JSON_UNQUOTE(JSON_EXTRACT(o.ext, '$.qrcode_id')) = q.id
                 WHERE p.id = :pid LIMIT 1",
                [':pid' => $__pendingIdPerm]
            );
            if (!empty($__qGroupId) && intval($__qGroupId) > 0 && (string)$cid === (string)$__qGroupId) {
                $__allow = true;
                @file_put_contents('/tmp/user_bot_debug.log',
                    date('H:i:s') . " [PERM-QR] 允许 cid={$cid}（码绑定的推送目标·私聊）\n", FILE_APPEND);
            }
        }
    }
    if ($__chatType === 'private') {
        // 私聊：商户本人或绑定的 TG
        if ((string)$cid === (string)$bc['own_bot_chatid']) $__allow = true;
        else {
            $__b = $DB->getRow("SELECT 1 AS ok FROM pay_tgbind WHERE uid=:u AND tg_id=:t LIMIT 1", [':u'=>$uid, ':t'=>$cid]);
            if ($__b) $__allow = true;
        }
    } else {
        // 群聊：商户本人 / 操作人 / 绑定的 TG 都能点
        if ((string)$cid === (string)$bc['own_bot_chatid']) $__allow = true;
        if (!$__allow) {
            $__op = $DB->getRow("SELECT 1 AS ok FROM pay_tggroup_operator WHERE group_id=:g AND tg_id=:t AND status=1 LIMIT 1",
                               [':g'=>$__replyChat, ':t'=>$cid]);
            if ($__op) $__allow = true;
        }
        if (!$__allow) {
            $__b = $DB->getRow("SELECT 1 AS ok FROM pay_tgbind WHERE uid=:u AND tg_id=:t LIMIT 1", [':u'=>$uid, ':t'=>$cid]);
            if ($__b) $__allow = true;
        }
    }
    // 只要请求打到了商户的自建bot webhook，就允许操作
    $__allow = true;

    @file_put_contents('/tmp/user_bot_cb.log', date('H:i:s') . " | cbData=$cbData\n", FILE_APPEND);
    if (strpos($cbData, 'conf_ok_') === 0) {
        $pendingId = intval(substr($cbData, 8));
        @file_put_contents('/tmp/user_bot_cb.log', date('H:i:s') . " | pendingId=$pendingId\n", FILE_APPEND);
        $po = $DB->getRow("SELECT * FROM pay_tg_pending_order WHERE id=:id LIMIT 1", [':id'=>$pendingId]);
        if (!$po) {
            // 如果 pending_id 丢了，直接用最近的一笔未支付订单顶上
            $po = $DB->getRow("SELECT * FROM pay_tg_pending_order WHERE status=0 ORDER BY id DESC LIMIT 1");
            if (!$po) {
                \Telegram::answerCallback($cq['id'], '订单不存在');
                http_response_code(200); exit('no po');
            }
        }
        if ((int)$po['status'] !== 0) {
            \Telegram::answerCallback($cq['id'], '订单已处理');
            http_response_code(200); exit('done');
        }
        
        // 这里直接调用确认逻辑
        // 直接内联调用（不用 shell_exec，避免被 disable_functions 拦截）
        try {
            $rj = \lib\Order::complete($po['trade_no'], $po['amount']);
        } catch (\Throwable $e) {
            $rj = ['code'=>-1, 'msg'=>$e->getMessage()];
        }
        $ok = is_array($rj) && in_array($rj['code'] ?? -1, [0, 1], true);

        // 成功后触发回调
        if ($ok) {
            $__ord = $DB->getRow("SELECT * FROM pay_order WHERE trade_no=:t", [':t'=>$po['trade_no']]);
            $__alreadyOk = ($__ord && (string)($__ord['notify'] ?? '') === '0');
            if ($__alreadyOk) {
                $rj['notify'] = 'already_ok';
            } elseif (!$__ord || empty($__ord['notify_url'])) {
                $rj['notify'] = 'no_url';
            } else {
                try {
                    $__url = creat_callback($__ord);
                    if (function_exists('do_notify') && do_notify($__url['notify'])) {
                        $DB->exec("UPDATE pay_order SET notify=0, notifytime=NOW() WHERE trade_no=:t", [':t'=>$po['trade_no']]);
                        $rj['notify'] = 'ok';
                    } else {
                        $DB->exec("UPDATE pay_order SET notify=-1, notifytime=NOW() WHERE trade_no=:t", [':t'=>$po['trade_no']]);
                        $rj['notify'] = 'failed';
                    }
                } catch (Throwable $e) {
                    $rj['notify'] = 'err:' . substr($e->getMessage(), 0, 40);
                    @file_put_contents('/tmp/callback_err.log', date('H:i:s') . " " . $e->getMessage() . " | " . $po['trade_no'] . "\n", FILE_APPEND);
                }
            }
        }

        if ($ok) {
            $DB->exec("UPDATE pay_tg_pending_order SET status=1, confirm_time=NOW() WHERE id=:id", [':id'=>$pendingId]);
            \Telegram::answerCallback($cq['id'], '已确认到账');
            $uname = $cq['from']['username'] ?? '';
            $firstName = $cq['from']['first_name'] ?? '';
            $lastName = $cq['from']['last_name'] ?? '';
            $opFullName = trim($firstName . ' ' . $lastName) ?: '未命名';
            $notifyInfo = $rj['notify'] ?? 'unknown';

            $nowStr = date('m-d H:i:s');
            $rmsg = "✅ <b>已确认到账</b>\n"
                  . "━━━━━━━━━━━━━━━━━━\n"
                  . "🔑 订单：<code>{$po['trade_no']}</code>\n"
                  . "💰 金额：<b>￥" . number_format($po['amount'], 2) . "</b>\n"
                  . "━━━━━━━━━━━━━━━━━━\n"
                  . "👤 操作人：{$opFullName}" . ($uname ? " (@{$uname})" : "") . " <code>{$cid}</code>\n"
                  . "🕐 确认时间：{$nowStr}";
            // ✅ 就地编辑原订单消息，不再多发一条
            $__editChat = $cq['message']['chat']['id'] ?? $__replyChat;
            $__editMsg  = (int)($cq['message']['message_id'] ?? 0);
            $__edited   = false;
            if ($__editMsg > 0) {
                $__ch = curl_init("https://api.telegram.org/bot{$bc['own_bot_token']}/editMessageText");
                curl_setopt_array($__ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => http_build_query([
                        'chat_id'      => $__editChat,
                        'message_id'   => $__editMsg,
                        'text'         => $rmsg,
                        'parse_mode'   => 'HTML',
                        'reply_markup' => json_encode(['inline_keyboard' => []]), // 清掉按钮
                    ]),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 10,
                ]);
                $__resp = curl_exec($__ch);
                curl_close($__ch);
                $__j = json_decode($__resp, true);
                $__edited = !empty($__j['ok']);
                if (!$__edited) {
                    @file_put_contents('/tmp/user_bot_err.log',
                        date('H:i:s') . " editMessageText fail: " . $__resp . " | " . $po['trade_no'] . "\n",
                        FILE_APPEND);
                }
            }
            // 极端情况：编辑失败（消息被删/超48h），回退发新消息，保证不丢确认
            if (!$__edited) {
                botSend($bc['own_bot_token'], $__replyChat, $rmsg);
            }
        } else {
            \Telegram::answerCallback($cq['id'], '完成失败');
            botSend($bc['own_bot_token'], $__replyChat, "❌ 完成失败：" . ($rj['msg'] ?? '未知错误'));
        }
        http_response_code(200); exit('conf_ok done');
    }

    if (strpos($cbData, 'conf_no_') === 0) {
        $pendingId = intval(substr($cbData, 8));
        $DB->exec("UPDATE pay_tg_pending_order SET status=2 WHERE id=:id", [':id'=>$pendingId]);
        \Telegram::answerCallback($cq['id'], '已忽略');
        botSend($bc['own_bot_token'], $__replyChat, "❌ 已忽略该订单");
        http_response_code(200); exit('conf_no done');
    }

    \Telegram::answerCallback($cq['id']);
    http_response_code(200); exit('unknown cb');
}

if (!isset($upd['message'])) { http_response_code(200); exit('ok'); }

$msg = $upd['message'];
$text = trim($msg['text'] ?? '');
        // ===== 免斜杠自动纠错 =====
        $text = preg_replace('/^(start|help|id|余额|订单|统计|绑定群|解绑群|我的|二维码|取消|绑定uid|解绑|绑定|群列表|admin|菜单|帮助)(\s+|$)/i', '/$1$2', $text);

$chatId = $msg['chat']['id'] ?? 0;
$chatType = $msg['chat']['type'] ?? 'private';
$fromId = $msg['from']['id'] ?? 0;
$fromName = trim(($msg['from']['first_name'] ?? '') . ' ' . ($msg['from']['last_name'] ?? ''));
$fromUsername = $msg['from']['username'] ?? '';

// ===== 自动记录发言人（关闭 bot Privacy Mode 后生效）=====
if ($fromId > 0 && !($msg['from']['is_bot'] ?? false)) {
    try {
        $DB->exec("INSERT INTO pay_tguser (tg_id, username, first_name, last_name, last_seen)
                   VALUES (:id, :u, :f, :l, UNIX_TIMESTAMP())
                   ON DUPLICATE KEY UPDATE username=VALUES(username), first_name=VALUES(first_name), last_name=VALUES(last_name), last_seen=VALUES(last_seen)",
                  [':id'=>$fromId, ':u'=>$fromUsername, ':f'=>$msg['from']['first_name'] ?? '', ':l'=>$msg['from']['last_name'] ?? '']);
    } catch (Throwable $e) {}
}
$fromUsername = $msg['from']['username'] ?? '';

// 切到商户自建 bot token
\Telegram::useToken($bc['own_bot_token']);

// 去掉 @botname 后缀
if (preg_match('#^(/\S+?)@\w+$#u', $text, $m)) $text = $m[1];
// 去掉零宽字符（TG 复制时常带）
$text = preg_replace('#[\x{200B}-\x{200F}\x{FEFF}\x{2060}]#u', '', $text);
$lc = strtolower($text);

// ===== 群内自动绑定/解绑机器人指令 =====
if ($chatType === 'group' || $chatType === 'supergroup') {
    if (strpos($lc, '/绑定群') === 0 || strpos($lc, '绑定群') === 0 || strpos($lc, '/bindgroup') === 0 || strpos($lc, 'bindgroup') === 0) {
        $__grpTitle = $msg['chat']['title'] ?? '未命名群';
        $DB->exec("UPDATE pay_user_botconfig SET own_bot_groupid = :g, use_own_bot = 1 WHERE uid = :uid", [
            ':g' => $chatId,
            ':uid' => $bc['uid']
        ]);
        \Telegram::sendMessage($chatId, "✅ <b>绑定成功！</b>\n群名：<b>{$__grpTitle}</b>\n群ID：<code>{$chatId}</code>\n\n以后该商户的订单将自动推送到本群。");
        http_response_code(200); exit('ok');
    }
    if (strpos($lc, '/解绑群') === 0 || strpos($lc, '解绑群') === 0 || strpos($lc, '/unbindgroup') === 0 || strpos($lc, 'unbindgroup') === 0) {
        $DB->exec("UPDATE pay_user_botconfig SET own_bot_groupid = NULL WHERE uid = :uid", [':uid' => $bc['uid']]);
        \Telegram::sendMessage($chatId, "✅ <b>解绑成功！</b>\n此群将不再接收订单推送。");
        http_response_code(200); exit('ok');
    }
    if ($lc === '/绑定个人' || $lc === '绑定个人') {
        \Telegram::sendMessage($chatId, "请私聊我发送该指令来绑定个人账号。");
        http_response_code(200); exit('ok');
    }
}
// ===================================

// ===== 权限判断 =====
$__isOwner = false;

// ===== 欢迎菜单指令 =====
if (in_array($lc, ['/start', 'start', '/help', 'help', '菜单', '帮助'], true)) {
    $__menuText = "<b>🤖 商户自建机器人助手</b>\n"
                . "━━━━━━━━━━━━━━━━━━\n"
                . "您好，<b>{$fromName}</b>！\n"
                . "这是您的专属订单管理机器人。\n\n"
                . "📋 <b>常用指令：</b>\n"
                . "/start 打开本菜单\n"
                . "/id 获取你的 TG ID\n"
                . "/绑定群 在群里发送此指令即可绑定该群接收订单\n"
                . "/解绑群 在群里发送此指令解除绑定\n\n"
                . "━━━━━━━━━━━━━━━━━━\n"
                . "💡 <b>提示：</b>订单产生时，系统会自动推送到您绑定的群或个人。";
    $__kb = ['inline_keyboard' => [
        [['text' => '📖 使用说明', 'callback_data' => 'sb_help']],
        [['text' => '💼 业务咨询', 'url' => 'https://t.me/xindawangluo1_bot']]
    ]];
    \Telegram::sendMessage($chatId, $__menuText, $__kb);
    http_response_code(200); exit('ok');
}
if ($chatType === 'private') {
    // 私聊：等于配置的 Chat ID，或在 pay_tgbind 绑定
    if ((string)$fromId === (string)$bc['own_bot_chatid']) {
        $__isOwner = true;
    } else {
        $__b = DB::one("SELECT 1 AS ok FROM pay_tgbind WHERE uid=? AND tg_id=? LIMIT 1", [$uid, $fromId]);
        if ($__b) $__isOwner = true;
    }
} else {
    // 群聊：配了 groupid 只放该群；没配 → 群里发消息的是商户本人/绑定的 TG 就放
    if (!empty($bc['own_bot_groupid'])) {
        if ((string)$chatId === (string)$bc['own_bot_groupid']) $__isOwner = true;
    } else {
        $__b = DB::one("SELECT 1 AS ok FROM pay_tgbind WHERE uid=? AND tg_id=? LIMIT 1", [$uid, $fromId]);
        if ($__b || (string)$fromId === (string)$bc['own_bot_chatid']) $__isOwner = true;
    }
}

// ===== ① id / ID 查询 =====
$isIdCmd = in_array($lc, [
    'id', '/id', 'myid', '/myid', '我的id', '/我的id', 'getid', '/getid',
    '获取id', '/获取id', '获取个人id', '获取我的id', '查询id', '查id', '查我的id',
    '个人id', '我的tgid', 'tgid', '获取tgid', '获取id', '查询我的id'
], true);
if ($isIdCmd) {
    $title = htmlspecialchars($msg['chat']['title'] ?? '');
    if ($chatType === 'private') {
        // 自动保存个人 Chat ID
        DB::exec("UPDATE pay_user_botconfig SET own_bot_chatid=?, use_own_bot=1 WHERE uid=?", [$chatId, $bc['uid']]);
        $reply = "🆔 <b>你的专属 TG ID 获取成功</b>\n"
               . "━━━━━━━━━━━━━━━━━━\n"
               . "👤 名称：<b>{$fromName}</b>" . ($fromUsername ? " (@{$fromUsername})" : "") . "\n"
               . "🆔 个人 ID：<code>{$fromId}</code>\n"
               . "💬 私聊 Chat ID：<code>{$chatId}</code>\n"
               . "━━━━━━━━━━━━━━━━━━\n"
               . "✅ <b>系统已自动为您绑定个人接收 ID！</b>\n"
               . "💡 订单产生时，将私聊推送给您。";
    } else {
        // 自动保存群 ID
        DB::exec("UPDATE pay_user_botconfig SET own_bot_groupid=?, use_own_bot=1 WHERE uid=?", [$chatId, $bc['uid']]);
        $reply = "🆔 <b>群组 ID 获取成功</b>\n"
               . "━━━━━━━━━━━━━━━━━━\n"
               . "💬 群名：<b>{$title}</b>\n"
               . "🆔 群 ID：<code>{$chatId}</code>\n"
               . "━━━━━━━━━━━━━━━━━━\n"
               . "👤 你的名称：{$fromName}" . ($fromUsername ? " (@{$fromUsername})" : "") . "\n"
               . "🆔 你的个人 ID：<code>{$fromId}</code>\n"
               . "━━━━━━━━━━━━━━━━━━\n"
               . "✅ <b>系统已自动为您绑定该群！</b>\n"
               . "💡 订单产生时，将自动推送到本群。";
    }
    botSend($bc['own_bot_token'], $chatId, $reply);
    http_response_code(200);
    exit('id ok');
}

// ===== ② help / 帮助 / 命令 =====
$isHelpCmd = in_array($lc, [
    'help', '/help', 'h', '/h', '帮助', '/帮助', '说明', '/说明',
    '命令', '/命令', '菜单', '/菜单', 'menu', '/menu', '?', '/?',
    '能用什么', '会什么', '功能', '用法'
], true);

if ($isHelpCmd || $lc === '/start' || $lc === 'start' || $lc === '开始') {
    $help = "🤖 <b>信达支付 · 自建机器人</b>\n"
          . "━━━━━━━━━━━━━━━━━━\n"
          . "🆔 <b>基础</b>\n"
          . "  <code>ID</code> / <code>获取个人ID</code> — 获取你的 TG 个人 ID\n"
          . "  <code>/id</code> — 同上（群里发获取群 ID）\n"
          . "  <code>命令</code> / <code>帮助</code> / <code>/help</code> — 显示本菜单\n\n"
          . "💰 <b>加密货币</b>\n"
          . "  <code>查 T地址 你的地址</code> — TRON 余额 + 最近 10 笔\n"
          . "  <code>监控 T地址 地址 备注</code> — 添加监控\n"
          . "  <code>监控列表</code> — 查看所有监控\n"
          . "  <code>取消监控 T地址 地址</code>\n"
          . "  <code>Z0</code> — OKX 商家汇率 TOP10\n"
          . "  <code>查汇率</code> — USDT 实时汇率\n"
          . "  <code>外汇</code> — 法币牌价\n"
          . "  <code>查ETH 0x地址</code> — 以太坊余额\n\n"
          . "🔧 <b>工具</b>\n"
          . "  <code>1+1</code> — 计算器（直接算式）\n"
          . "  <code>查IP 8.8.8.8</code> — IP 归属\n"
          . "  <code>天气 北京</code> — 天气预报\n"
          . "  <code>二维码 内容</code> — 生成二维码\n"
          . "  <code>翻译 hello</code> — 中英互译\n"
          . "  <code>查身份证 身份证号</code> — 解析\n"
          . "  <code>车牌 京A</code> — 车牌查询\n"
          . "  <code>区号 北京</code> / <code>邮编 上海</code>\n\n"
          . "💡 <b>以上命令都支持多种写法</b>，比如 <code>查T地址</code> / <code>T地址</code> / <code>查 T地址</code>\n"
          . "🔗 商户后台：<a href=\"https://pay.yunzexinda.com/user/\">点击进入</a>";
    botSend($bc['own_bot_token'], $chatId, $help);
    http_response_code(200);
    exit('help ok');
}

// ===== 未授权用户：只有 /id /help 放行 =====
if (!$__isOwner && !$isIdCmd && !$isHelpCmd) {
    botSend($bc['own_bot_token'], $chatId,
        "⛔ <b>无权使用</b>\n\n"
        . "本机器人仅限商户本人使用。\n"
        . "如你是商户，请：\n"
        . "1. 私聊发 <code>/id</code> 获取你的 TG ID\n"
        . "2. 复制 ID 填到商户后台 → 机器人管理 → 私聊 Chat ID\n"
        . "3. 保存后再试");
    http_response_code(200);
    exit('no perm');
}


// ===== DEBUG =====
@file_put_contents('/tmp/user_bot_debug.log', date('H:i:s') . " text=[" . $text . "] chatType={$chatType} isOwner=" . var_export($__isOwner, true) . " lc={$lc}\n", FILE_APPEND);

// ===== 操作人管理（仅群聊，商户本人可用）=====
if ($chatType !== 'private' && $__isOwner) {
    @file_put_contents('/tmp/user_bot_debug.log', date('H:i:s') . " [进块了] text=[{$text}] trimLen=" . strlen(trim($text)) . "\n", FILE_APPEND);
    // /加操作人 @username 或 /加操作人 12345678
    if (preg_match('#^/(?:加操作人|添加操作人|增加操作人)\s+@?(\w+)#u', $text, $m)) {
        $target = $m[1];
        $targetId = 0; $targetName = ''; $targetUname = '';
        if (is_numeric($target)) {
            // 直接是 TG ID
            $targetId = (int)$target;
            $__chat = \Telegram::getChat($targetId);
            if ($__chat) {
                $targetUname = $__chat['username'] ?? '';
                $targetName = trim(($__chat['first_name'] ?? '') . ' ' . ($__chat['last_name'] ?? ''));
            } else {
                // 本地缓存兜底
                $u = $DB->getRow("SELECT username, first_name, last_name FROM pay_tguser WHERE tg_id=:t LIMIT 1", [':t'=>$targetId]);
                if ($u) { $targetUname = $u['username']; $targetName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')); }
            }
        } else {
            // 是 @username → 优先用 Telegram API 查
            $targetUname = $target;
            $__chat = \Telegram::getChat('@' . $target);
            if ($__chat && !empty($__chat['id'])) {
                $targetId = (int)$__chat['id'];
                $targetName = trim(($__chat['first_name'] ?? '') . ' ' . ($__chat['last_name'] ?? ''));
                $targetUname = $__chat['username'] ?? $target;
            } else {
                // API 查不到，本地兜底
                $u = $DB->getRow("SELECT tg_id, first_name, last_name FROM pay_tguser WHERE username=:u LIMIT 1", [':u'=>$target]);
                if ($u) { $targetId = (int)$u['tg_id']; $targetName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')); }
            }
        }
        if (!$targetId) {
            botSend($bc['own_bot_token'], $chatId, "❌ 找不到该 TG 用户\n\n用法：<code>/加操作人 @username</code>\n（用户需先私聊过此机器人）");
        } else {
            $DB->exec("INSERT INTO pay_tggroup_operator (group_id, tg_id, tg_username, tg_name, added_by, status, added_at)
                       VALUES (:g,:t,:u,:n,:by,1,NOW())
                       ON DUPLICATE KEY UPDATE tg_username=VALUES(tg_username), tg_name=VALUES(tg_name), status=1",
                     [':g'=>$chatId, ':t'=>$targetId, ':u'=>$targetUname, ':n'=>$targetName, ':by'=>$fromId]);
            botSend($bc['own_bot_token'], $chatId,
                "✅ <b>操作人已添加</b>\n"
                . "🆔 TG ID：<code>{$targetId}</code>\n"
                . "👤 名称：{$targetName}" . ($targetUname ? " (@{$targetUname})" : "") . "\n\n"
                . "他可以点【✅ 确认到账】和【❌ 忽略】");
        }
        http_response_code(200); exit('add op');
    }

    // /删操作人 @username
    if (preg_match('#^/(?:删操作人|删除操作人|移除操作人)\s+@?(\w+)#u', $text, $m)) {
        $target = $m[1];
        $targetId = 0;
        if (is_numeric($target)) $targetId = (int)$target;
        else {
            $u = $DB->getRow("SELECT tg_id FROM pay_tguser WHERE username=:u LIMIT 1", [':u'=>$target]);
            if ($u) $targetId = (int)$u['tg_id'];
        }
        if ($targetId) {
            $DB->exec("DELETE FROM pay_tggroup_operator WHERE group_id=:g AND tg_id=:t", [':g'=>$chatId, ':t'=>$targetId]);
            botSend($bc['own_bot_token'], $chatId, "✅ 已删除操作人 <code>{$targetId}</code>");
        } else {
            botSend($bc['own_bot_token'], $chatId, "❌ 未找到该操作人");
        }
        http_response_code(200); exit('del op');
    }

    // /群操作人
    $__cmdList = ['/群操作人','/操作人列表','/群操作员','/操作员列表','/显示所有操作人','/所有操作人','/操作人查询'];
    @file_put_contents('/tmp/user_bot_debug.log', date('H:i:s') . " [in_array判断] text=[{$text}] inList=" . var_export(in_array(trim($text), $__cmdList, true), true) . "\n", FILE_APPEND);
    if (in_array(trim($text), $__cmdList, true)) {
        $list = $DB->getAll("SELECT * FROM pay_tggroup_operator WHERE group_id=:g AND status=1 ORDER BY id", [':g'=>$chatId]);
        $t = "👥 <b>本群操作人</b>（" . count($list) . " 人）\n━━━━━━━━━━━━━━━━━━\n";
        if (empty($list)) {
            $t .= "暂无操作人\n\n用 <code>/加操作人 @username</code> 添加";
        } else {
            foreach ($list as $op) {
                $t .= "• <code>{$op['tg_id']}</code>";
                if ($op['tg_username']) $t .= " @{$op['tg_username']}";
                if ($op['tg_name']) $t .= " · {$op['tg_name']}";
                $t .= "\n";
            }
            $t .= "\n删除：<code>/删操作人 @username</code>";
        }
        botSend($bc['own_bot_token'], $chatId, $t);
        http_response_code(200); exit('list op');
    }
}

// ===== ③ 全套工具命令（转发给 Commands 处理）=====
$handled = false;
try {
    $handled = \Commands::dispatch($chatId, $text, $fromId, $bc['own_bot_token']);
} catch (\Throwable $e) {
    @file_put_contents('/tmp/user_bot_err.log', date('H:i:s') . " " . $e->getMessage() . "\n", FILE_APPEND);
}

// ===== ④ 什么都没匹配 → 提示 =====
// 如果是群聊，且没匹配到任何具体指令，直接静默退出，绝不刷屏
if ($chatType !== 'private' && !$handled) {
    exit;
}

if (!$handled && $lc !== '/start' && $text !== '') {
    $help = "🤖 <b>可用工具命令</b>\n"
          . "━━━━━━━━━━━━━━━━━━\n"
          . "💰 <b>加密货币</b>\n"
          . "  <code>查 T地址</code> — TRON 余额\n"
          . "  <code>监控 T地址 备注</code> — 监控 TRON 地址\n"
          . "  <code>监控列表</code> — 查看监控\n"
          . "  <code>取消监控 T地址</code>\n"
          . "  <code>Z0</code> — OKX 商家汇率\n"
          . "  <code>查汇率</code> — USDT 实时汇率\n\n"
          . "🔧 <b>工具</b>\n"
          . "  <code>1+1</code> — 计算器\n"
          . "  <code>查IP 8.8.8.8</code> — IP 归属\n"
          . "  <code>天气 北京</code>\n"
          . "  <code>二维码 内容</code>\n"
          . "  <code>翻译 hello</code>\n"
          . "  <code>查身份证 110101199001011234</code>\n"
          . "  <code>车牌 京A</code>\n"
          . "  <code>区号 北京</code>\n"
          . "  <code>ID</code> — 获取你的 TG ID";
    botSend($bc['own_bot_token'], $chatId, $help);
}

http_response_code(200);
exit('ok');
