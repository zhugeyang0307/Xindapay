<?php




// ===== 文档路径选择：优先 .html，降级 .md =====
if (!function_exists('pickDoc')) {
    function pickDoc($name) {
        $html = __DIR__ . '/docs/' . $name . '.html';
        $md   = __DIR__ . '/docs/' . $name . '.md';
        if (file_exists($html)) return $html;
        if (file_exists($md)) return $md;
        return '';
    }
}

// ===== 文档分类字典 + 辅助 =====
$GLOBALS['__DOC_MAP__'] = [
    'start'    => ['name' => '🚀 新手入门', 'docs' => [
        '商户手册'       => '📘 商户手册（总纲）',
        '商户操作手册'   => '👨‍💼 商户操作手册',
        '2FA开启教程'    => '🔐 2FA 谷歌验证器',
    ]],
    'business' => ['name' => '💼 商户经营', 'docs' => [
        '订单管理说明'   => '📋 订单管理说明',
        '收款码管理'     => '💳 收款码管理',
        '收款码智能路由' => '🧭 收款码智能路由',
    ]],
    'sub'      => ['name' => '👥 下级商户', 'docs' => [
        '下级操作手册'   => '📗 下级操作手册',
        '下级推送指南'   => '📤 下级推送指南',
    ]],
    'apk'      => ['name' => '📱 APK 监听', 'docs' => [
        'listener'       => '📡 APK 监听器说明',
    ]],
    'mail'     => ['name' => '📧 邮件系统', 'docs' => [
        '邮件推送说明'    => '📮 邮件推送说明',
        '邮件营销系统'    => '📢 邮件营销系统',
        '邮件模板管理'    => '🎨 邮件模板管理',
        '邮件AI写作指南'  => '🤖 邮件 AI 写作',
        '追踪接入指南'    => '📍 追踪接入指南',
    ]],
    'bot'      => ['name' => '🤖 TG 机器人', 'docs' => [
        '机器人操作说明'      => '📘 机器人操作说明',
        '机器人创建'          => '🛠 机器人创建',
        'BotFather设置说明'   => '👨‍🍳 BotFather 设置',
        'TG推送限流'          => '📡 TG 推送限流',
    ]],
    'api'      => ['name' => '🔌 技术对接', 'docs' => [
        '商户对接文档'    => '📄 商户对接文档（V1+V2）',
        '收款码推送说明'  => '🔗 收款码推送说明',
    ]],
];

if (!function_exists('buildDocMenu')) {
    function buildDocMenu() {
        $rows = [];
        foreach ($GLOBALS['__DOC_MAP__'] as $k => $cat) {
            $rows[] = [['text' => $cat['name'] . '（' . count($cat['docs']) . '）', 'callback_data' => 'cb_cat_' . $k]];
        }
        $rows[] = [['text' => '📦 下载全部文档', 'callback_data' => 'cb_doc_all']];
        $rows[] = [['text' => '🚀 申请加速审核', 'callback_data' => 'cb_speed_review'], ['text' => '👤 转人工客服', 'callback_data' => 'cb_need_human']];
        return ['inline_keyboard' => $rows];
    }
}
if (!function_exists('buildCatMenu')) {
    function buildCatMenu($catKey) {
        if (!isset($GLOBALS['__DOC_MAP__'][$catKey])) return null;
        $cat = $GLOBALS['__DOC_MAP__'][$catKey];
        $rows = [];
        foreach ($cat['docs'] as $docName => $label) {
            $rows[] = [['text' => $label, 'callback_data' => 'cb_send_' . $docName]];
        }
        $rows[] = [['text' => '⬅️ 返回主菜单', 'callback_data' => 'cb_back_menu']];
        return ['inline_keyboard' => $rows];
    }
}
if (!function_exists('sendDocToChat')) {
    function sendDocToChat($cid, $docName) {
        $path = pickDoc($docName);
        if (!$path || !file_exists($path)) {
            Telegram::sendMessage($cid, "📄 文档暂不可用：{$docName}");
            return false;
        }
        Telegram::sendDocument($cid, $path, "📄 " . $docName);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'html') {
            $url = 'https://pay.yunzexinda.com/help/docs/' . rawurlencode($docName) . '.html';
            Telegram::sendMessage($cid, "🔗 在线查看：<a href=\"{$url}\">{$docName}</a>");
        }
        return true;
    }
}
define('BOT_MODE', 'customer');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/DB.php';

require_once __DIR__ . '/lib/Telegram.php';

// ===== 终极物理拦截 /id 指令 =====
$__input = file_get_contents('php://input');
$__upd = json_decode($__input, true);
if (isset($__upd['message']['text']) && preg_match('/^\/id(@\w+)?$/i', trim($__upd['message']['text']))) {
    $__chat_id = $__upd['message']['chat']['id'];
    $__user_id = $__upd['message']['from']['id'];
    $__name = $__upd['message']['from']['first_name'] ?? '';
    $__msg = "🆔 <b>你的专属 TG ID 获取成功</b>\n"
           . "━━━━━━━━━━━━━━━━━━\n"
           . "👤 名称：<b>{$__name}</b>\n"
           . "🆔 个人 ID：<code>{$__user_id}</code>\n"
           . "💬 Chat ID：<code>{$__chat_id}</code>\n"
           . "━━━━━━━━━━━━━━━━━━\n"
           . "💡 请复制上方数字 ID 填入后台配置。";
    \Telegram::sendMessage($__chat_id, $__msg);
    exit;
}
// ==================================
require_once __DIR__ . '/lib/Commands.php';
require_once __DIR__ . '/lib/Bepusdt.php';
require_once __DIR__ . '/lib/Register.php';
require_once __DIR__ . '/lib/Auth.php';
require_once __DIR__ . '/lib/Menu.php';
require_once __DIR__ . '/lib/Cmds.php';
require_once __DIR__ . '/lib/Qrcode.php';
require_once __DIR__ . '/lib/OrderConfirm.php';
require_once __DIR__ . '/lib/Audit.php';
require_once __DIR__ . '/lib/GroupOp.php';
require_once __DIR__ . '/lib/GroupCmds.php';
require_once __DIR__ . '/lib/ApkOrder.php';
require_once __DIR__ . '/lib/ApkTutorial.php';
require_once __DIR__ . '/lib/DeepSeek.php';
require_once __DIR__ . '/../includes/common.php';

plog('===== 机器人启动 =====');

$offsetFile = __DIR__ . '/.offset_customer';
$offset = file_exists($offsetFile) ? (int)file_get_contents($offsetFile) : 0;

while (true) {
    $res = Telegram::getUpdates($offset, 25);
    if (!$res || !($res['ok'] ?? false)) { sleep(2); continue; }
    foreach ($res['result'] as $upd) {
        file_put_contents(__DIR__.'/debug_update.log',
            date('H:i:s').' keys='.implode(',', array_keys($upd))
            .' cb='.($upd['callback_query']['data'] ?? '-')
            .' txt='.substr($upd['message']['text'] ?? '-', 0, 30)."\n",
            FILE_APPEND);
        $offset = $upd['update_id'] + 1;
        file_put_contents($offsetFile, $offset);
        try { handleUpdate($upd); }
        catch (Exception $e) { plog('处理异常: ' . $e->getMessage()); }
    }
}

function tgBridgeSend($type, $to, $scene='reg') {
    $key = 'a308f099ebf9e18ef1ab03d5137c9a8e07b916b19addf244';
    $params = http_build_query(['key'=>$key, 'action'=>'send', 'scene'=>$scene, 'type'=>$type, 'to'=>$to]);
    $ch = curl_init('https://127.0.0.1/tg_bridge.php?' . $params);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15, CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_SSL_VERIFYHOST=>0, CURLOPT_HTTPHEADER=>['Host: pay.yunzexinda.com']]);
    $r = curl_exec($ch); curl_close($ch);
    return trim($r);
}
function tgBridgeVerify($type, $to, $code, $scene='reg') {
    $key = 'a308f099ebf9e18ef1ab03d5137c9a8e07b916b19addf244';
    $params = http_build_query(['key'=>$key, 'action'=>'verify', 'scene'=>$scene, 'type'=>$type, 'to'=>$to, 'code'=>$code]);
    $ch = curl_init('https://127.0.0.1/tg_bridge.php?' . $params);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15, CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_SSL_VERIFYHOST=>0, CURLOPT_HTTPHEADER=>['Host: pay.yunzexinda.com']]);
    $r = curl_exec($ch); curl_close($ch);
    return trim($r);
}

function handleUpdate($upd) {
    botlog('customer', $upd);
    // ========== 客服机器人专属：只做传话 ==========
    if (defined('BOT_MODE') && BOT_MODE === 'customer') {
        return handleCustomerOnly($upd);
    }
    // ========== 主机器人：走原有逻辑 ==========

    // ========== 管理员回复消息 → 转发给客户 ==========
    if (isset($upd['message']['reply_to_message'])) {
        $m = $upd['message'];
        $fromId = $m['from']['id'] ?? 0;
        $replyMid = $m['reply_to_message']['message_id'] ?? 0;
        $admins = explode(',', RELAY_ADMINS);
        if (in_array((string)$fromId, array_map('trim', $admins)) && $replyMid) {
            $row = DB::one(
                "SELECT * FROM pay_tg_relay WHERE admin_msg_id=:a OR group_msg_id=:g LIMIT 1",
                [':a'=>$replyMid, ':g'=>$replyMid]
            );
            if ($row) {
                $replyText = $m['text'] ?? '';
                if ($replyText !== '') {
                    Telegram::sendMessage($row['customer_id'], $replyText);
                    Telegram::sendMessage($m['chat']['id'], "✅ 已发送给客户");
                    return;
                }
            }
        }
    }
    // ==================================================

    if (isset($upd['callback_query'])) {
        $cq = $upd['callback_query'];

        // 客户主动点"转人工"
        file_put_contents(__DIR__.'/debug_update.log',
            date('H:i:s').' [CB IN] data='.var_export($cq['data'] ?? null, true)
            .' len='.strlen($cq['data'] ?? '')."\n",
            FILE_APPEND);
        if (trim($cq['data'] ?? '') === 'cb_need_human' || strpos($cq['data'] ?? '', 'cb_need_human') !== false) {
            $cid = $cq['from']['id'];
            $cname = trim(($cq['from']['first_name'] ?? '') . ' ' . ($cq['from']['last_name'] ?? ''));
            $cuname = $cq['from']['username'] ?? '';
            Telegram::answerCallback($cq['id'], '客服已接入');
            DB::exec("INSERT INTO pay_tg_human_session (customer_id, status, started_at, updated_at)
                      VALUES (:c, 1, NOW(), NOW())
                      ON DUPLICATE KEY UPDATE status=1, updated_at=NOW()",
                     [':c'=>$cid]);
            $sendRes = Telegram::sendMessage($cid,
                "✅ <b>已接入人工客服</b>\n\n"
                . "请不要离开当前会话，并描述您的问题以便及时解决。\n\n"
                . "（如需结束人工服务，发送 /结束人工）",
                ['inline_keyboard' => [[['text' => '❌ 结束人工', 'callback_data' => 'cb_end_human']]]]);
            file_put_contents(__DIR__.'/debug_update.log',
                date('H:i:s').' [sendTo='.$cid.'] result='.json_encode($sendRes, JSON_UNESCAPED_UNICODE)."\n",
                FILE_APPEND);
            // 获取客户最近 5 条聊天记录
            $__hist = DB::query("SELECT role, content FROM pay_tg_chat_history WHERE customer_id=? ORDER BY id DESC LIMIT 5", [$cid]);
            $__hist = array_reverse($__hist);
            $__histText = "";
            foreach ($__hist as $__h) {
                $__role = $__h['role'] === 'user' ? '👤 客户' : '🤖 AI';
                $__histText .= "{$__role}：{$__h['content']}\n";
            }
            if (!$__histText) $__histText = "（无历史对话记录，客户直接点击了转人工）";

            $__msgText = "🚨 <b>新客户请求人工服务</b>\n"
                    . "🆔 客户标识：<code>{$cid}</code>\n"
                    . "━━━━━━━━━━━━━━━━━━\n"
                    . "💬 <b>最近对话：</b>\n{$__histText}"
                    . "━━━━━━━━━━━━━━━━━━\n"
                    . "—— 直接回复此消息即可回复客户 ——";
            
            // 发给管理员
            $admins = array_map('trim', explode(',', RELAY_ADMINS));
            $firstAdminMsgId = null;
            $firstGroupMsgId = null;
            foreach ($admins as $aid) {
                if (!$aid) continue;
                $r = Telegram::sendMessage($aid, $__msgText);
                if ($firstAdminMsgId === null && isset($r['result']['message_id'])) {
                    $firstAdminMsgId = $r['result']['message_id'];
                }
            }
            // 发给群组
            if (defined('RELAY_GROUP') && RELAY_GROUP) {
                $rg = Telegram::sendMessage(RELAY_GROUP, $__msgText);
                if (isset($rg['result']['message_id'])) {
                    $firstGroupMsgId = $rg['result']['message_id'];
                }
            }

            // 🔥 核心修复：记录到数据库，让引用回复能查到！
            DB::exec("INSERT INTO pay_tg_relay 
                        (customer_id, customer_name, customer_uname, admin_msg_id, group_msg_id)
                       VALUES (:c, :n, :u, :a, :g)",
                      [':c'=>$cid, ':n'=>$cname, ':u'=>$cuname, ':a'=>$firstAdminMsgId, ':g'=>$firstGroupMsgId]);
            return;
        }

        $cbData = $cq['data'] ?? '';
        if ($cbData === 'cb_reg') {
            Telegram::answerCallback($cq['id']);
            Register::setStep($cq['from']['id'], 'email');
            Telegram::sendMessage($cq['message']['chat']['id'],
                "📝 <b>快捷注册商户</b>\n━━━━━━━━━━━━━━\n请输入你的<b>邮箱或手机号码</b>（作为登录账号）：\n发送 /cancel 可取消");
            return;
        }
        if ($cbData === 'cb_reg_check') {
            Telegram::answerCallback($cq['id'], '正在检查...');
            $uid = $cq['from']['id'];
            $st = Register::getState($uid);
            if ($st && $st['step'] === 'done') {
                Telegram::sendMessage($cq['message']['chat']['id'], "✅ 已完成支付，请查看上一条消息。");
            } else {
                Telegram::sendMessage($cq['message']['chat']['id'], "尚未检测到支付，请稍后再试。");
            }
            return;
        }
        // ===== 购买 APK 回调 =====
        if ($cbData === 'apk_buy') {
            Telegram::answerCallback($cq['id']);
            cmdBuyApk($cq['message']['chat']['id'], $cq['from']['id']);
            return;
        }
        if ($cbData === "apk_tutorial") {
            Telegram::answerCallback($cq["id"]);
            Telegram::sendMessage($cq["message"]["chat"]["id"], ApkTutorial::tg());
            return;
        }
        if ($cbData === 'apk_list') {
            Telegram::answerCallback($cq['id']);
            cmdMyApk($cq['message']['chat']['id'], $cq['from']['id']);
            return;
        }
        // ===== 订单确认回调 =====
        if (strpos($cbData, 'conf_ok_') === 0) {
            Telegram::answerCallback($cq['id'], '正在确认...');
            $id = (int)substr($cbData, 8);
            list($ok, $r, $operator) = OrderConfirm::confirm($id, $cq['from']['id'], $cq['message']['chat']['id'] ?? null);
            if (!$ok) {
                Telegram::sendMessage($cq['message']['chat']['id'], "❌ {$r}");
                return;
            }
            $operator = $operator ?: "ID:{$cq['from']['id']}";
            $orig = $cq['message']['text'] ?? '';
            $newText = $orig;
            // 替换标题
            $newText = preg_replace('/🔔 <b>收到新订单<\/b>/u', '✅ <b>订单已确认到账</b>', $newText);
            // 去掉末尾"请确认是否到账："
            $newText = preg_replace('/请确认是否到账：\s*$/u', '', $newText);
            // 追加操作人信息
            $newText .= "\n━━━━━━━━━━━━━━━━━━\n"
                      . "👤 操作人：{$operator}\n"
                      . "🕐 确认时间：" . date('m-d H:i:s');
            Telegram::editMessageText($cq['message']['chat']['id'], $cq['message']['message_id'], $newText);
            Auth::log($cq['from']['id'], 'order.confirm', "pending_id={$id}");
            return;
        }
        if (strpos($cbData, 'conf_no_') === 0) {
            Telegram::answerCallback($cq['id'], '已忽略');
            $id = (int)substr($cbData, 8);
            list($ok, $r, $operator) = OrderConfirm::ignore($id, $cq['from']['id'], $cq['message']['chat']['id'] ?? null);
            if (!$ok) {
                Telegram::sendMessage($cq['message']['chat']['id'], "❌ {$r}");
                return;
            }
            $operator = $operator ?: "ID:{$cq['from']['id']}";
            $orig = $cq['message']['text'] ?? '';
            $newText = $orig;
            $newText = preg_replace('/🔔 <b>收到新订单<\/b>/u', '❌ <b>已忽略订单</b>', $newText);
            $newText = preg_replace('/请确认是否到账：\s*$/u', '', $newText);
            $newText .= "\n━━━━━━━━━━━━━━━━━━\n"
                      . "👤 操作人：{$operator}\n"
                      . "🕐 忽略时间：" . date('m-d H:i:s');
            Telegram::editMessageText($cq['message']['chat']['id'], $cq['message']['message_id'], $newText);
            Auth::log($cq['from']['id'], 'order.ignore', "pending_id={$id}");
            return;
        }
        // ===== 二维码回调 =====
        if ($cbData === 'qr_list') {
            Telegram::answerCallback($cq['id']);
            qrListCmd($cq['message']['chat']['id'], $cq['from']['id']);
            return;
        }
        if ($cbData === 'qr_add') {
            Telegram::answerCallback($cq['id']);
            qrAddCmd($cq['message']['chat']['id'], $cq['from']['id']);
            return;
        }
        if ($cbData === 'qr_cancel') {
            Telegram::answerCallback($cq['id']);
            Qrcode::clearState($cq['from']['id']);
            Telegram::sendMessage($cq['message']['chat']['id'], "已取消");
            return;
        }
        if (strpos($cbData, 'qr_type_') === 0) {
            Telegram::answerCallback($cq['id']);
            $ch = substr($cbData, 8);
            if (!isset(Qrcode::$PAY_TYPES[$ch])) {
                Telegram::sendMessage($cq['message']['chat']['id'], "❌ 类型错误");
                return;
            }
            Qrcode::setState($cq['from']['id'], 'qr_wait_img', $ch);
            $label = Qrcode::label($ch);
            Telegram::sendMessage($cq['message']['chat']['id'],
                "📤 已选择：<b>{$label}</b>\n\n"
              . "请把二维码图片<b>直接发给机器人</b>（不要压缩）\n"
              . "发送 /cancel 取消",
                ['inline_keyboard' => [[['text' => '❌ 取消', 'callback_data' => 'qr_cancel']]]]);
            return;
        }
        if (strpos($cbData, 'qr_del_') === 0) {
            Telegram::answerCallback($cq['id'], '正在删除...');
            $id = (int)substr($cbData, 7);
            $uid = Auth::merchantUid($cq['from']['id']);
            if (!$uid) { Telegram::sendMessage($cq['message']['chat']['id'], "❌ 请先快捷登录"); return; }
            list($ok, $r) = Qrcode::delete($uid, $id);
            Telegram::sendMessage($cq['message']['chat']['id'], $ok ? "✅ 已删除 #{$id}" : "❌ {$r}");
            return;
        }
        if (strpos($cbData, 'qr_info_') === 0) {
            Telegram::answerCallback($cq['id']);
            $id = (int)substr($cbData, 8);
            $uid = Auth::merchantUid($cq['from']['id']);
            $row = Qrcode::get($uid, $id);
            if (!$row) { Telegram::sendMessage($cq['message']['chat']['id'], "❌ 不存在"); return; }
            $label = Qrcode::label($row['channel']);
            $t = "📊 <b>二维码详情</b>\n"
               . "ID：<code>#{$row['id']}</code>\n"
               . "类型：{$label}\n"
               . "备注：{$row['remark']}\n"
               . "已用：{$row['use_count']} 次\n"
               . "状态：" . ($row['status']==1?'🟢 启用':'🔴 禁用') . "\n"
               . "添加：" . $row['addtime'];
            Telegram::sendMessage($cq['message']['chat']['id'], $t);
            return;
        }
        if ($cbData === 'cb_demo') {
            Telegram::answerCallback($cq['id']);
            handleDemo($cq['message']['chat']['id'], $cq['id'], $cq['from']['id']);
            return;
        }
        if ($cbData === 'cb_demo_check') {
            Telegram::answerCallback($cq['id'], '正在检查支付状态...');
            $uid = $cq['from']['id'];
            $r = DB::one("SELECT * FROM pay_demo_orders WHERE tg_user_id=? AND status>=1 LIMIT 1", [$uid]);
            if ($r) {
                sendDemoAccount($cq['message']['chat']['id']);
            } else {
                Telegram::sendMessage($cq['message']['chat']['id'], "尚未检测到支付，请稍后再试。");
            }
            return;
        }
        if (Commands::handleCallback($cq)) return;
        Telegram::answerCallback($upd['callback_query']['id']);
        return;
    }
    if (!isset($upd['message'])) return;
    $msg = $upd['message'];
    $GLOBALS['__tg_msg__'] = $msg;
    $chat_id = $msg['chat']['id'];
    $chat_type = $msg['chat']['type'] ?? 'private';
    $user_id = $msg['from']['id'] ?? 0;
    $text = trim($msg['text'] ?? '');
        // ===== 免斜杠自动纠错 =====
        $text = preg_replace('/^(start|help|id|余额|订单|统计|绑定群|解绑群|我的|二维码|取消|绑定uid|解绑|绑定|群列表|admin|菜单|帮助)(\s+|$)/i', '/$1$2', $text);

    // 自动去除 @bot 后缀（/start@xindapay1H5_bot → /start）
    $text = preg_replace('/@[a-zA-Z0-9_]+bot\s*$/iu', '', $text);
    $text = trim($text);

    
    $isGroup = in_array($chat_type, ['group', 'supergroup']);

    // ========== 群里命令处理 ==========
    if ($isGroup && $text) {
        // 去掉可能的 @bot 后缀
        $cmd = preg_replace('/@\w+\s*$/', '', $text);
        $cmd = trim($cmd);

        // 群里 @机器人 的催单消息（纯订单号）
        if (preg_match('/@\w+/', $text)) {
            if (handleGroupOrder($chat_id, $user_id, $text, $msg)) return;
        }

        // 绑定/解绑商户群
        if (strpos($cmd, '/绑定商户群') === 0) {
            return cmdBindMerchantGroup($chat_id, $user_id, $msg);
        }
        if (strpos($cmd, '/解绑商户群') === 0) {
            return cmdUnbindMerchantGroup($chat_id, $user_id);
        }
        if (strpos($cmd, '/群信息') === 0) {
            return cmdGroupInfo($chat_id, $user_id);
        }

        // 操作人管理
        if (strpos($cmd, '/加操作人') === 0) {
            return cmdAddOperator($chat_id, $user_id, $cmd, $msg);
        }
        if (strpos($cmd, '/删操作人') === 0) {
            return cmdDelOperator($chat_id, $user_id, $cmd, $msg);
        }
        if (strpos($cmd, '/群操作人') === 0) {
            return cmdListOperators($chat_id, $user_id);
        }

        // 群里上传二维码
        if (strpos($cmd, '/群上传二维码') === 0 || strpos($cmd, '/上传二维码') === 0) {
            return cmdGroupQrStart($chat_id, $user_id);
        }
        if (strpos($cmd, '/群二维码') === 0) {
            return cmdGroupQrList($chat_id, $user_id);
        }
        if (strpos($cmd, '/取消') === 0) {
            GroupOp::clearState($user_id);
            Telegram::sendMessage($chat_id, "已取消。");
            return;
        }
    }
    // ==========================================

    if ($text === '' && !isset($msg['photo'])) return;

    // ========== 全局 /cancel 清理 ==========
    if (!$isGroup && $user_id && $text === '/cancel') {
        // 清理二维码等待状态
        $qst = Qrcode::getState($user_id);
        if ($qst && $qst['action'] === 'qr_wait_img') {
            Qrcode::clearState($user_id);
            Telegram::sendMessage($chat_id, "✅ 已取消二维码上传。");
            return;
        }
    }

    // ========== TG 快捷注册对话处理 ==========
    if (!$isGroup && $user_id) {
        $st = Register::getState($user_id);
        if ($st && !empty($st['step']) && $st['step'] !== 'done') {
            if ($text === '/cancel') {
                Register::clearState($user_id);
                Telegram::sendMessage($chat_id, "已取消注册。");
                return;
            }
            if ($st['step'] === 'email') {
                $isEmail = preg_match('/^[A-z0-9._-]+@[A-z0-9._-]+\.[A-z0-9._-]+$/', $text);
                $isPhone = preg_match('/^1[3-9]\d{9}$/', $text);
                if (!$isEmail && !$isPhone) {
                    Telegram::sendMessage($chat_id, "❌ 请输入正确的邮箱或手机号码（如 13888889999）：");
                    return;
                }
                if ($isPhone) {
                    $exist = DB::one("SELECT uid FROM pay_user WHERE phone=?", [$text]);
                } else {
                    $exist = DB::one("SELECT uid FROM pay_user WHERE email=?", [$text]);
                }
                if ($exist) {
                    Telegram::sendMessage($chat_id, "❌ 该账号已被注册，请换一个：");
                    return;
                }
                Register::setStep($user_id, 'pwd', ['email'=>$text]);
                Telegram::sendMessage($chat_id, "请输入<b>密码</b>（至少6位，不能纯数字）：");
                return;
            }
            if ($st['step'] === 'pwd') {
                if (strlen($text) < 6) {
                    Telegram::sendMessage($chat_id, "❌ 密码不能低于6位，请重新输入：");
                    return;
                }
                if (is_numeric($text)) {
                    Telegram::sendMessage($chat_id, "❌ 密码不能为纯数字，请重新输入：");
                    return;
                }
                // 保存密码 + 账号，进入验证码步骤
                $account = $st['email'];
                Register::setStep($user_id, 'code', ['email'=>$account, 'pwd'=>$text]);
                // 判断账号类型并发送真实验证码
                $isPhone = preg_match('/^1[3-9]\d{9}$/', $account);
                $type = $isPhone ? 1 : 0;
                $r = tgBridgeSend($type, $account, 'reg');
                if ($r === 'ok') {
                    $sendto = $isPhone ? '手机短信' : '邮箱';
                    Telegram::sendMessage($chat_id,
                        "📧 验证码已发送到你的<b>{$sendto}</b>，请查收并输入验证码完成注册（1小时内有效）\n发送 /cancel 可取消");
                } else {
                    Telegram::sendMessage($chat_id, "❌ 验证码发送失败：{$r}\n请重新输入密码尝试，或发送 /cancel 取消");
                }
                return;
            }
            if ($st['step'] === 'code') {
                $account = $st['email'];
                $isPhone = preg_match('/^1[3-9]\d{9}$/', $account);
                $type = $isPhone ? 1 : 0;
                $r = tgBridgeVerify($type, $account, $text, 'reg');
                if ($r !== 'ok') {
                    Telegram::sendMessage($chat_id, "❌ {$r}");
                    return;
                }
                list($ok, $info) = Register::createUser($user_id);
                if (!$ok) {
                    Telegram::sendMessage($chat_id, "❌ 注册失败：{$info}");
                    return;
                }
                $payOrderId = 'REG_' . $info['uid'] . '_' . time();
                $payUrl = Bepusdt::createOrder($payOrderId, REG_RECHARGE_USDT);
                if (!$payUrl) {
                    Telegram::sendMessage($chat_id, "❌ 创建支付订单失败。商户ID：{$info['uid']}\n请联系客服。");
                    return;
                }
                Register::setPendingPay($user_id, $info['uid'], $info['key'], $payOrderId, $info['email'], $info['pwd_plain']);
                Telegram::sendMessage($chat_id,
                    "✅ <b>资料已提交！</b>\n"
                  . "━━━━━━━━━━━━━━\n"
                  . "🆔 商户ID：<code>{$info['uid']}</code>\n"
                  . "📧 邮箱：{$info['email']}\n"
                  . "━━━━━━━━━━━━━━\n"
                  . "请支付 <b>" . REG_RECHARGE_USDT . " USDT</b> 预充值费用。\n"
                  . "支付成功后自动发送完整账号信息。",
                    ['inline_keyboard' => [
                        [['text' => '💳 支付 ' . REG_RECHARGE_USDT . ' USDT', 'url' => $payUrl]],
                        [['text' => '🔄 我已支付，检查状态', 'callback_data' => 'cb_reg_check']]
                    ]]
                );
                return;
            }
            if ($st['step'] === 'pay') {
                Telegram::sendMessage($chat_id, "请先完成 " . REG_RECHARGE_USDT . " USDT 的支付。如已支付，请稍等 1-2 分钟。");
                return;
            }
        }
    }
    // ==========================================

    // ===== 处理图片消息（二维码上传）=====
    if (!$isGroup && isset($msg['photo']) && is_array($msg['photo'])) {
        $st = Qrcode::getState($user_id);
        if ($st && $st['action'] === 'qr_wait_img') {
            $ch = $st['data'];
            $uid = Auth::merchantUid($user_id);
            if (!$uid) {
                Telegram::sendMessage($chat_id, "❌ 请先 /快捷登录");
                Qrcode::clearState($user_id);
                return;
            }
            // 取最大尺寸
            $photo = end($msg['photo']);
            $fileId = $photo['file_id'];
            Telegram::sendMessage($chat_id, "⏳ 正在保存...");
            list($ok, $r) = Qrcode::saveFromTelegram($uid, $ch, $fileId);
            if (!$ok) {
                Telegram::sendMessage($chat_id, "❌ {$r}");
                return;
            }
            list($ok2, $id) = Qrcode::add($uid, $ch, $r);
            Qrcode::clearState($user_id);
            if (!$ok2) {
                Telegram::sendMessage($chat_id, "❌ {$id}");
                return;
            }
            $label = Qrcode::label($ch);
            Telegram::sendMessage($chat_id,
                "✅ <b>二维码已添加</b>\n"
              . "ID：<code>#{$id}</code>\n"
              . "类型：{$label}");
            Auth::log($user_id, 'qr.add', "id={$id}", "channel={$ch}");
            return;
        }
    }

    saveTgUser($user_id, $msg['from']);

    // ===== 管理员权限 =====
    $isAdmin = isTgAdmin($user_id);

    // ========== 客户消息 → 转发给管理员（双向客服） ==========
    // 条件：非管理员 + 私聊 + 非命令开头
    if (!$isAdmin && !$isGroup && $text !== '' && $text[0] !== '/') {
        // 群里 @机器人 已处理，这里只处理私聊
        $name  = trim(($msg['from']['first_name'] ?? '') . ' ' . ($msg['from']['last_name'] ?? ''));
        $uname = $msg['from']['username'] ?? '';
        $hint  = "📩 来自 [{$name}]" . ($uname ? " (@{$uname})" : '') . " (chat_id: {$chat_id}):\n\n" . $text . "\n\n—— 直接回复此条消息即可回复客户 ——";
        $admins = array_map('trim', explode(',', RELAY_ADMINS));
        $firstAdminMsgId = null;
        $firstGroupMsgId = null;
        foreach ($admins as $aid) {
            if (!$aid) continue;
            $r = Telegram::sendMessage($aid, $hint);
            if ($firstAdminMsgId === null && isset($r['result']['message_id'])) {
                $firstAdminMsgId = $r['result']['message_id'];
            }
        }
        if (defined('RELAY_GROUP') && RELAY_GROUP) {
            $rg = Telegram::sendMessage(RELAY_GROUP, $hint);
            if (isset($rg['result']['message_id'])) {
                $firstGroupMsgId = $rg['result']['message_id'];
            }
        }
        DB::exec("INSERT INTO pay_tg_relay
                    (customer_id, customer_name, customer_uname, admin_msg_id, group_msg_id)
                   VALUES (:c, :n, :u, :a, :g)",
                  [':c'=>$chat_id, ':n'=>$name, ':u'=>$uname, ':a'=>$firstAdminMsgId, ':g'=>$firstGroupMsgId]);
        // 提示客户
        Telegram::sendMessage($chat_id, defined('RELAY_HINT') ? RELAY_HINT : '📩 已收到');
        return;
    }
    // ==========================================================

    if (!$isAdmin) {
        // 放行非管理员的常用命令（商户自查、快捷注册、二维码、订单确认等）
        $allow_prefix = [
            '/start', '/help', '/id',
            '/快捷注册', '/快捷登录',
            '/我的', '/我的订单', '/我的流水', '/我的二维码', '/二维码',
            '/添加二维码', '/删除二维码',
            '/开启手动确认', '/关闭手动确认',
            '/待确认', '/cancel',
            '/余额', '/订单',
            '/邮件', '/mail'
        ];
        $allowed = false;
        foreach ($allow_prefix as $p) {
            if (strpos($text, $p) === 0) { $allowed = true; break; }
        }
        if (!$allowed) return;
    }
    // ======================

    // 英文命令别名（来自 BotFather 菜单）
    $__alias = ['/balance'=>'/余额', '/orders'=>'/订单', '/stats'=>'/统计', '/bind'=>'/绑定', '/unbind'=>'/解绑', '/mail'=>'/邮件'];
    foreach ($__alias as $__en => $__cn) {
        if (strpos($text, $__en) === 0) { $text = $__cn . substr($text, strlen($__en)); break; }
    }

    // ===== 公开 =====
    if (strpos($text, '/start') === 0)  return cmdStart($chat_id);
    if (strpos($text, '/help') === 0)   return cmdStart($chat_id);
    if (strpos($text, '/id') === 0)     return cmdId($chat_id, $user_id, $msg);

    // ===== 商户自查 =====
    if (strpos($text, '/开启手动确认') === 0 || strpos($text, '/关闭手动确认') === 0) {
        if (!Auth::can($user_id, 'self.view')) return Telegram::sendMessage($chat_id, "❌ 权限不足，请 /快捷登录");
        return cmdToggleConfirm($chat_id, $user_id, $text);
    }
    if (strpos($text, '/待确认') === 0) {
        if (!Auth::can($user_id, 'self.view')) return Telegram::sendMessage($chat_id, "❌ 权限不足，请 /快捷登录");
        return cmdPendingList($chat_id, $user_id);
    }
    if (strpos($text, '/我的流水') === 0) {
        if (!Auth::can($user_id, 'self.bill')) return Telegram::sendMessage($chat_id, "❌ 权限不足，请 /快捷登录");
        return cmdMyBill($chat_id, $user_id, $text);
    }
    if (strpos($text, '/我的订单') === 0) {
        if (!Auth::can($user_id, 'self.orders')) return Telegram::sendMessage($chat_id, "❌ 权限不足，请 /快捷登录");
        return cmdMyOrders($chat_id, $user_id, $text);
    }
    if (strpos($text, '/购买apk') === 0 || strpos($text, '/购买APK') === 0 || strpos($text, '/买apk') === 0) {
        if (!Auth::can($user_id, 'self.view')) return Telegram::sendMessage($chat_id, "❌ 权限不足，请 /快捷登录");
        return cmdBuyApk($chat_id, $user_id);
    }
    if (strpos($text, '/我的apk') === 0 || strpos($text, '/我的APK') === 0) {
        if (!Auth::can($user_id, 'self.view')) return Telegram::sendMessage($chat_id, "❌ 权限不足，请 /快捷登录");
        return cmdMyApk($chat_id, $user_id);
    }
    if (strpos($text, '/我的二维码') === 0 || strpos($text, '/二维码') === 0) {
        if (!Auth::can($user_id, 'self.qrcode')) return Telegram::sendMessage($chat_id, "❌ 权限不足，请 /快捷登录");
        return qrListCmd($chat_id, $user_id);
    }
    if (strpos($text, '/添加二维码') === 0) {
        if (!Auth::can($user_id, 'self.qrcode')) return Telegram::sendMessage($chat_id, "❌ 权限不足，请 /快捷登录");
        return qrAddCmd($chat_id, $user_id);
    }
    if (strpos($text, '/删除二维码') === 0) {
        if (!Auth::can($user_id, 'self.qrcode')) return Telegram::sendMessage($chat_id, "❌ 权限不足，请 /快捷登录");
        return qrDelCmd($chat_id, $user_id, $text);
    }
    if (strpos($text, '/邮件') === 0) {
        if (!Auth::can($user_id, 'self.view')) return Telegram::sendMessage($chat_id, "❌ 权限不足，请 /快捷登录");
        return cmdMailPush($chat_id, $user_id);
    }
    if (strpos($text, '/我的') === 0) {
        if (!Auth::can($user_id, 'self.view')) return Telegram::sendMessage($chat_id, "❌ 权限不足，请 /快捷登录");
        return cmdMyInfo($chat_id, $user_id);
    }
    if (strpos($text, '/余额') === 0) {
        if (!Auth::can($user_id, 'self.view')) return Telegram::sendMessage($chat_id, "❌ 权限不足，请 /快捷登录");
        return cmdBalance($chat_id, $user_id);
    }
    if (strpos($text, '/订单') === 0) {
        if (!Auth::can($user_id, 'self.orders')) return Telegram::sendMessage($chat_id, "❌ 权限不足，请 /快捷登录");
        return cmdOrders($chat_id, $user_id);
    }

    // ===== 管理员功能 =====
    if (strpos($text, '/审计日志') === 0) {
        if (!Auth::isOwner($user_id)) return Telegram::sendMessage($chat_id, "❌ 此功能仅所有者可用");
        return cmdAuditLog($chat_id, $user_id, $text);
    }
    if (strpos($text, '/系统状态') === 0) {
        if (!Auth::can($user_id, 'sys.status')) return Telegram::sendMessage($chat_id, "❌ 权限不足");
        return cmdSysStatus($chat_id, $user_id);
    }
    if (strpos($text, '/统计') === 0) {
        if (!Auth::can($user_id, 'stats.view')) return Telegram::sendMessage($chat_id, "❌ 权限不足");
        return cmdStats($chat_id);
    }
    if (strpos($text, '/商户列表') === 0) {
        if (!Auth::can($user_id, 'merchant.list')) return Telegram::sendMessage($chat_id, "❌ 权限不足");
        return cmdMerchantList($chat_id, $user_id, $text);
    }
    if (strpos($text, '/查商户') === 0) {
        if (!Auth::can($user_id, 'merchant.view')) return Telegram::sendMessage($chat_id, "❌ 权限不足");
        return cmdMerchantInfo($chat_id, $user_id, $text);
    }
    if (strpos($text, '/绑定群') === 0) {
        if (!Auth::can($user_id, 'group.bind')) return Telegram::sendMessage($chat_id, "❌ 权限不足");
        return cmdBindGroup($chat_id, $user_id, $text, $msg);
    }
    if (strpos($text, '/解绑群') === 0) {
        if (!Auth::can($user_id, 'group.unbind')) return Telegram::sendMessage($chat_id, "❌ 权限不足");
        return cmdUnbindGroup($chat_id, $user_id);
    }
    if (strpos($text, '/群列表') === 0) {
        if (!Auth::can($user_id, 'group.list')) return Telegram::sendMessage($chat_id, "❌ 权限不足");
        return cmdListGroups($chat_id, $user_id);
    }
    if (strpos($text, '/绑定') === 0) {
        if (!Auth::can($user_id, 'merchant.unbind')) return Telegram::sendMessage($chat_id, "❌ 权限不足");
        return cmdBind($chat_id, $user_id, $text);
    }
    if (strpos($text, '/解绑') === 0) {
        if (!Auth::can($user_id, 'merchant.unbind')) return Telegram::sendMessage($chat_id, "❌ 权限不足");
        return cmdUnbind($chat_id, $user_id);
    }
    if (strpos($text, '/添加管理员') === 0) {
        if (!Auth::can($user_id, 'admin.add')) return Telegram::sendMessage($chat_id, "❌ 权限不足");
        return cmdAddAdmin($chat_id, $user_id, $text);
    }
    if (strpos($text, '/删除管理员') === 0) {
        if (!Auth::can($user_id, 'admin.del')) return Telegram::sendMessage($chat_id, "❌ 权限不足");
        return cmdDelAdmin($chat_id, $user_id, $text);
    }
    if (strpos($text, '/管理员') === 0) {
        if (!Auth::can($user_id, 'admin.list')) return Telegram::sendMessage($chat_id, "❌ 权限不足");
        return cmdListAdmins($chat_id, $user_id);
    }

    if (Commands::dispatch($chat_id, $text, $user_id)) return;
    if (handleFaq($chat_id, $text)) return;
    if (($msg['chat']['type'] ?? 'private') === 'private') {
        Telegram::sendMessage($chat_id, "🤖 未知指令，发送 /help 查看帮助");
    }
}

// ============ 群里 @机器人 查订单 ============
function handleGroupOrder($chat_id, $user_id, $text, $msg) {
    // 匹配 19 位订单号
    if (!preg_match_all('/(\d{19})/', $text, $m)) return false;
    $orders = $m[1];
    if (empty($orders)) return false;

    $results = [];
    foreach ($orders as $trade_no) {
        $order = DB::one(
            "SELECT o.trade_no, o.uid, o.realmoney, o.status, o.addtime,
                    u.username, t.showname AS type_name
             FROM pay_order o
             LEFT JOIN pay_user u ON u.uid=o.uid
             LEFT JOIN pay_type t ON t.id=o.type
             WHERE o.trade_no=?", [$trade_no]);
        if (!$order) {
            $results[] = "❌ <code>{$trade_no}</code> 未找到";
            continue;
        }
        $st = $order['status'] == 1 ? '✅ 已支付' : '⏳ 待支付';
        $results[] = "📋 <b>订单查询</b>\n"
                   . "订单号：<code>{$trade_no}</code>\n"
                   . "商户：{$order['username']} (UID: {$order['uid']})\n"
                   . "通道：{$order['type_name']}\n"
                   . "金额：￥{$order['realmoney']}\n"
                   . "状态：{$st}\n"
                   . "下单：{$order['addtime']}";
    }
    Telegram::sendMessage($chat_id, implode("\n\n", $results));
    return true;
}


function pushUrgeToMerchant($order, $trade_no, $from_user_id, $msg) {
    $from_name = $msg['from']['username'] ?? $msg['from']['first_name'] ?? '管理员';
    $text = "🚨 <b>催单通知</b>
"
          . "━━━━━━━━━━━━━━
"
          . "订单号：<code>{$trade_no}</code>
"
          . "金额：￥{$order['realmoney']}
"
          . "通道：{$order['type_name']}
"
          . "下单：{$order['addtime']}
"
          . "━━━━━━━━━━━━━━
"
          . "请尽快处理。";

    // 1. 私聊绑定的 TG 用户
    $bind = DB::one("SELECT tg_id FROM pay_tgbind WHERE uid=?", [$order['uid']]);
    if ($bind && $bind['tg_id']) {
        Telegram::sendMessage($bind['tg_id'], $text);
    }

    // 2. 商户群
    $grp = DB::one("SELECT chat_id FROM pay_tggroup WHERE uid=?", [$order['uid']]);
    if ($grp && $grp['chat_id']) {
        Telegram::sendMessage($grp['chat_id'], $text);
    }
}

// ============ 绑定商户群 ============
function cmdBindGroup($chat_id, $user_id, $text, $msg = null) {
    // 强行清洗：去掉 /绑定群 之后的所有非数字字符（防止带上 @机器人 后缀）
    $__clean = preg_replace('/[^0-9]/', '', $text);
    if (empty($__clean)) {
        return Telegram::sendMessage($chat_id, "用法：<code>/绑定群 商户UID</code>\n或 <code>/绑定群 0</code> 绑定超级管理员群");
    }
    if (!preg_match('#^/绑定群\s+(\d+)#', $text, $m)) {
        $m[1] = (int)$__clean;
    }
    $uid = (int)$m[1];

    // 群标题
    $title = $msg['chat']['title'] ?? '';

    // 绑定超管群：只有 ADMIN_ID 可操作
    if ($uid === 0) {
        if ($user_id != ADMIN_ID) {
            return Telegram::sendMessage($chat_id, "❌ 只有超级管理员才能绑定超管群");
        }
        DB::exec("INSERT INTO pay_tggroup(chat_id,uid,title,addtime) VALUES(?,0,?,NOW())
                  ON DUPLICATE KEY UPDATE uid=0, title=VALUES(title)",
            [$chat_id, $title]);
        return Telegram::sendMessage($chat_id, "✅ <b>超级管理员群</b>已绑定\n\n所有订单都会推送到本群。");
    }

    // 绑定商户群
    $u = DB::one("SELECT uid,username FROM pay_user WHERE uid=?", [$uid]);
    if (!$u) return Telegram::sendMessage($chat_id, "❌ 商户UID不存在");
    DB::exec("INSERT INTO pay_tggroup(chat_id,uid,title,addtime,is_merchant_group,push_orders,set_by,set_time) 
              VALUES(?,?,?,NOW(),1,1,?,NOW())
              ON DUPLICATE KEY UPDATE uid=VALUES(uid), title=VALUES(title), is_merchant_group=1, push_orders=1",
        [$chat_id, $uid, $title, $user_id]);
    Telegram::sendMessage($chat_id, "✅ <b>商户群</b>已绑定\n\n商户：{$u['username']}\nUID：{$uid}\n\n该商户的订单会推送到本群。");
}

function cmdUnbindGroup($chat_id, $user_id) {
    if ($user_id != ADMIN_ID) {
        return Telegram::sendMessage($chat_id, "❌ 只有超级管理员可解绑");
    }
    $row = DB::one("SELECT * FROM pay_tggroup WHERE chat_id=?", [$chat_id]);
    if (!$row) return Telegram::sendMessage($chat_id, "本群未绑定");
    DB::exec("DELETE FROM pay_tggroup WHERE chat_id=?", [$chat_id]);
    Telegram::sendMessage($chat_id, "✅ 本群已解绑");
}

function cmdListGroups($chat_id, $user_id) {
    if ($user_id != ADMIN_ID) {
        return Telegram::sendMessage($chat_id, "❌ 只有超级管理员可查看");
    }
    $rows = DB::query("SELECT g.chat_id, g.uid, g.title, u.username
                       FROM pay_tggroup g LEFT JOIN pay_user u ON u.uid=g.uid
                       ORDER BY g.uid ASC");
    if (!$rows) return Telegram::sendMessage($chat_id, "还没有绑定任何群");
    $text = "📋 <b>已绑定的群列表</b>\n━━━━━━━━━━━━━━\n";
    foreach ($rows as $r) {
        $name = $r['uid'] == 0 ? '⭐ 超管群' : ($r['username'] ?: 'UID:'.$r['uid']);
        $text .= "{$name}\n<code>{$r['chat_id']}</code>  {$r['title']}\n\n";
    }
    Telegram::sendMessage($chat_id, $text);
}

function handleDemo($chat_id, $cbId, $user_id) {
    $row = DB::one("SELECT * FROM pay_demo_orders WHERE tg_user_id=? AND status>=1 LIMIT 1", [$user_id]);
    if ($row) { sendDemoAccount($chat_id); return; }

    $pending = DB::one("SELECT * FROM pay_demo_orders WHERE tg_user_id=? AND status=0 AND created_at>? LIMIT 1",
                       [$user_id, time() - 1800]);
    if ($pending) {
        Telegram::sendMessage($chat_id, "您有未完成的支付订单，请先完成支付。");
        return;
    }

    $orderId = 'DEMO_' . $user_id . '_' . time();
    $payUrl  = Bepusdt::createOrder($orderId, DEMO_PRICE_USDT);
    if (!$payUrl) {
        Telegram::sendMessage($chat_id, "创建支付订单失败，请稍后重试。");
        return;
    }
    DB::exec("INSERT INTO pay_demo_orders (order_id, tg_user_id, chat_id, amount, status, created_at)
              VALUES (?,?,?,?,0,?)",
             [$orderId, $user_id, $chat_id, DEMO_PRICE_USDT, time()]);

    $keyboard = [
        'inline_keyboard' => [
            [['text' => '💳 支付 ' . DEMO_PRICE_USDT . ' USDT', 'url' => $payUrl]],
            [['text' => '🔄 我已支付，检查状态', 'callback_data' => 'cb_demo_check']]
        ]
    ];
    Telegram::sendMessage($chat_id,
        "🔐 为防止恶意获取，请支付 <b>" . DEMO_PRICE_USDT . " USDT</b> 获取演示账号。
支付成功后自动发放。",
        $keyboard);
}

function sendDemoAccount($chat_id) {
    $text = "<b>🎁 信达网络演示账号</b>
━━━━━━━━━━━━━━━━━━
🛒 <b>电商商城</b>
地址：https://shop.yunzexinda.com/
账号：yanshi
密码：yanshi123456

🎫 <b>发卡网</b>
地址：https://faka.yunzexinda.com/
账号：yanshi@yunzexinda.com
密码：yanshi123456
━━━━━━━━━━━━━━━━━━
⚠️ 演示账号仅供体验，请勿修改密码或删除数据";
    Telegram::sendMessage($chat_id, $text);
}

function cmdStart($chat_id) {
    // ========== 按身份显示菜单 ==========
    $user_id = $GLOBALS['__tg_msg__']['from']['id'] ?? 0;
    $role = Auth::role($user_id);
    if ($role === Auth::ROLE_OWNER)      return menuOwner($chat_id);
    if ($role === Auth::ROLE_SUPERADMIN) return menuSuperAdmin($chat_id);
    if ($role === Auth::ROLE_ADMIN)      return menuAdmin($chat_id);
    if ($role === Auth::ROLE_MERCHANT)   return menuMerchant($chat_id);

    // ========== 普通用户菜单 ==========
    $fromId = $user_id;
    if (!isTgAdmin($fromId)) {
        // 普通用户 → 业务 + 指令 + 按钮
        $text = "<b>👋 欢迎使用信达支付 · 官方客服</b>\n"
              . "━━━━━━━━━━━━━━━━━━━━\n"
              . "\n"
              . "<b>💎 一站数字化业务搭建</b>\n"
              . "支付系统 · 电商商城 · 发卡网 · 定制开发\n"
              . "\n"
              . "<b>🎯 三大核心优势</b>\n"
              . "\n"
              . "<b>① TG 群收款确认（推荐）</b>\n"
              . "   客户扫码 → 群里点一下 → 秒收回调\n"
              . "   不用装 APP · 不用写代码 · 3 分钟接入\n"
              . "\n"
              . "<b>② 个人码 H5 跳转</b>\n"
              . "   微信 / 支付宝个人码，自动拉起 APP 付款\n"
              . "   无需商户号 · 无需营业执照\n"
              . "\n"
              . "<b>③ APK 监听自动回调</b>\n"
              . "   旧安卓机装 APP 当监听器\n"
              . "   到账秒回调 · 无需人工确认\n"
              . "\n"
              . "━━━━━━━━━━━━━━━━━━━━\n"
              . "<b>📋 商户合作规则</b>\n"
              . "• 开户即用，不抽成，不按笔收费\n"
              . "• 含全部接口与回调，无其他隐藏费用\n"
              . "• 内置智能路由，收款成功率 +10%\n"
              . "• 源码授权 / 同款搭建请联系客服\n"
              . "\n"
              . "━━━━━━━━━━━━━━━━━━━━\n"
              . "<b>💬 直接发送问题，AI 秒回</b>\n"
              . "涉及资金 / 订单 / 授权会自动转人工\n"
              . "\n"
              . "👇 点击下方按钮快速访问";
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📚 文档中心', 'url' => 'https://pay.yunzexinda.com/help/'],
                    ['text' => '💳 信达支付系统', 'url' => 'https://pay.yunzexinda.com'],
                ],
                [
                    ['text' => '🛒 电商商城', 'url' => 'https://shop.yunzexinda.com/'],
                    ['text' => '🎫 发卡网', 'url' => 'https://faka.yunzexinda.com'],
                ],
                [
                    ['text' => '👤 商户登录', 'url' => 'https://pay.yunzexinda.com/user/'],
                    ['text' => '📝 商户注册', 'url' => 'https://pay.yunzexinda.com/user/reg.php'],
                ],
                [
                    ['text' => '📝 快捷注册', 'callback_data' => 'cb_reg'],
                    ['text' => '🔑 快捷登录', 'callback_data' => 'cb_login'],
                ],
                [
                    ['text' => '💳 收款码推送说明', 'url' => 'https://pay.yunzexinda.com/help/docs/收款码推送方案.html'],
                    ['text' => '📘 APK 教程', 'callback_data' => 'apk_tutorial'],
                ],
                [
                    ['text' => '🎁 演示账号', 'callback_data' => 'cb_demo'],
                    ['text' => '📞 联系客服', 'callback_data' => 'cb_help'],
                ],
            ]
        ];
        Telegram::sendMessage($chat_id, $text, $keyboard);
        return;
    }
    // ========== 管理员走原菜单 ==========
    $text = "<b>信达科技 · 使用指南</b>\n"
          . "━━━━━━━━━━━━━━━━━━\n"
          . "\n"
          . "📋 <b>商户指令</b>\n"
          . "/余额 · 商户余额\n"
          . "/订单 · 最近 10 笔订单\n"
          . "/统计 · 平台数据\n"
          . "/绑定 UID · 绑定商户\n"
          . "/解绑 · 解绑账号\n"
          . "/id · 查看 TG ID\n"
          . "\n"
          . "👥 <b>群组管理</b>\n"
          . "/绑定群 UID · 绑定商户群\n"
          . "/绑定群 0 · 绑定超管群\n"
          . "/解绑群 · 解绑本群\n"
          . "/群列表 · 查看所有群\n"
          . "\n"
          . "🔍 <b>快捷查询</b>\n"
          . "· 查IP 8.8.8.8\n"
          . "· 天气 北京\n"
          . "· 查身份证 440xxxxxxxxxxxxxxx\n"
          . "· 车牌 粤A\n"
          . "· 区号 深圳\n"
          . "· 邮编 深圳\n"
          . "· 查汇率 / 外汇\n"
          . "· 查ETH 0x地址\n"
          . "· 直接发 T 开头地址查 TRON\n"
          . "\n"
          . "🛠 <b>实用工具</b>\n"
          . "· 算式 100+200\n"
          . "· 二维码 文字\n"
          . "· 翻译 hello\n"
          . "\n"
          . "━━━━━━━━━━━━━━━━━━\n"
          . "💡 群里 @机器人 + 订单号 可催单\n"
          . "━━━━━━━━━━━━━━━━━━\n"
          . "💰 高成率 · 秒到账 · 稳定通道\n"
          . "";
    Telegram::sendMessage($chat_id, $text);
}

function cmdId($chat_id, $user_id, $msg = null) {
    $chat_type = $msg['chat']['type'] ?? 'private';
    if ($chat_type === 'private') {
        $reply = "🆔 <b>你的专属 TG ID 获取成功</b>\n"
               . "━━━━━━━━━━━━━━━━━━\n"
               . "请复制下方数字 ID：\n"
               . "<code>{$user_id}</code>\n"
               . "━━━━━━━━━━━━━━━━━━\n"
               . "💡 将该数字填入商户后台的推送设置中即可接收通知。";
    } else {
        $title = htmlspecialchars($msg['chat']['title'] ?? '当前群组');
        $reply = "🆔 <b>群组 ID 获取成功</b>\n"
               . "━━━━━━━━━━━━━━━━━━\n"
               . "群名：<b>{$title}</b>\n"
               . "群 ID：<code>{$chat_id}</code>\n"
               . "你的个人 ID：<code>{$user_id}</code>\n"
               . "━━━━━━━━━━━━━━━━━━\n"
               . "💡 群填群ID，个人填个人ID。";
    }
    Telegram::sendMessage($chat_id, $reply);
}

function cmdBalance($chat_id, $user_id) {
    $bind = DB::one("SELECT * FROM pay_tgbind WHERE tg_id=?", [$user_id]);
    if (!$bind) return Telegram::sendMessage($chat_id, "❌ 未绑定\n发送：<code>/绑定 商户UID</code>");
    $u = DB::one("SELECT uid,username,money,deposit FROM pay_user WHERE uid=?", [$bind['uid']]);
    if (!$u) return Telegram::sendMessage($chat_id, "❌ 商户不存在");
    $text = "💰 <b>商户余额</b>\n━━━━━━━━━━━━━━\n"
          . "UID：{$u['uid']}\n"
          . "用户名：{$u['username']}\n"
          . "余额：<b>￥{$u['money']}</b>\n"
          . "保证金：￥{$u['deposit']}";
    Telegram::sendMessage($chat_id, $text);
}

function cmdOrders($chat_id, $user_id) {
    $bind = DB::one("SELECT * FROM pay_tgbind WHERE tg_id=?", [$user_id]);
    if (!$bind) return Telegram::sendMessage($chat_id, "❌ 请先 /绑定 商户UID");
    $orders = DB::query(
        "SELECT o.trade_no, o.realmoney, o.status, o.addtime,
                o.type, t.showname AS type_name, t.name AS type_code
         FROM pay_order o
         LEFT JOIN pay_type t ON t.id = o.type
         WHERE o.uid=? ORDER BY o.addtime DESC LIMIT 10", [$bind['uid']]);
    if (!$orders) return Telegram::sendMessage($chat_id, "暂无订单");
    $text = "📋 <b>最近 10 笔订单</b>\n";
    $text .= "━━━━━━━━━━━━━━━━━━\n";
    foreach ($orders as $o) {
        $st = $o['status'] == 1 ? '✅' : '⏳';
        $typeName = $o['type_name'] ?: '未知';
        // 简称
        $tc = strtolower($o['type_code'] ?? '');
        if (strpos($tc, 'alipay') !== false) $typeName = '支付宝';
        elseif (strpos($tc, 'wx') !== false) $typeName = '微信';
        elseif (strpos($tc, 'qq') !== false) $typeName = 'QQ';
        elseif (strpos($tc, 'bank') !== false) $typeName = '银行卡';
        elseif (strpos($tc, 'jd') !== false) $typeName = '京东';
        elseif (strpos($tc, 'douyin') !== false) $typeName = '抖音';
        // 时间只留 HH:MM
        $hm = date('H:i', strtotime($o['addtime']));
        // 订单号长截断
        $tn = $o['trade_no'];
        if (strlen($tn) > 16) $tn = substr($tn, 0, 14) . '..';
        $money = number_format($o['realmoney'], 2);
        $text .= "{$st} {$typeName} ￥{$money}  <code>{$tn}</code>  {$hm}\n";
    }
    Telegram::sendMessage($chat_id, $text);
}

function cmdStats($chat_id) {
    $today = date('Y-m-d') . ' 00:00:00';
    $in  = DB::col("SELECT COALESCE(SUM(realmoney),0) FROM pay_order WHERE status=1 AND addtime>=?", [$today]);
    $cnt = DB::col("SELECT COUNT(*) FROM pay_order WHERE status=1 AND addtime>=?", [$today]);
    $usr = DB::col("SELECT COUNT(*) FROM pay_user");
    $text = "📊 <b>平台统计</b>\n━━━━━━━━━━━━━━\n"
          . "今日入款：<b>￥" . number_format($in, 2) . "</b>\n"
          . "今日订单：{$cnt} 笔\n"
          . "商户总数：{$usr} 个";
    Telegram::sendMessage($chat_id, $text);
}

function cmdBind($chat_id, $user_id, $text) {
    if (!preg_match('#^/绑定\s+(\d+)#', $text, $m))
        return Telegram::sendMessage($chat_id, "用法：/绑定 商户UID\n例：/绑定 1000");
    $uid = (int)$m[1];
    $u = DB::one("SELECT uid,username FROM pay_user WHERE uid=?", [$uid]);
    if (!$u) return Telegram::sendMessage($chat_id, "❌ 商户UID不存在");
    DB::exec("INSERT INTO pay_tgbind(tg_id,uid,addtime) VALUES(?,?,NOW())
              ON DUPLICATE KEY UPDATE uid=VALUES(uid)", [$user_id, $uid]);
    Telegram::sendMessage($chat_id, "✅ 已绑定：{$u['username']} (UID: {$uid})");
}

function cmdUnbind($chat_id, $user_id) {
    DB::exec("DELETE FROM pay_tgbind WHERE tg_id=?", [$user_id]);
    Telegram::sendMessage($chat_id, "✅ 已解绑");
}

function handleFaq($chat_id, $text) {
    $faqs = [
        [['充值', '怎么充值'], "💡 充值方式：\n登录 → 用户中心 → 余额充值 → 扫码支付"],
        [['提现', '结算'], "💡 提现规则：\nT+1 工作日自动结算，或手动申请"],
        [['客服', '人工', '联系'], "\n工作日 9:00-22:00"],
        [['费率', '手续费'], "💡 费率说明：\n入款 0%\n下发 1%"],
    ];
    foreach ($faqs as $faq) {
        foreach ($faq[0] as $kw) {
            if (mb_strpos($text, $kw) !== false) {
                Telegram::sendMessage($chat_id, $faq[1]);
                return true;
            }
        }
    }
    return false;
}

function saveTgUser($tg_id, $from) {
    DB::exec("INSERT INTO pay_tguser(tg_id,username,first_name,last_name,last_seen)
              VALUES(?,?,?,?,UNIX_TIMESTAMP())
              ON DUPLICATE KEY UPDATE
                username=VALUES(username),first_name=VALUES(first_name),
                last_name=VALUES(last_name),last_seen=VALUES(last_seen)",
        [$tg_id, $from['username'] ?? '', $from['first_name'] ?? '', $from['last_name'] ?? '']);
}

function isTgAdmin($tg_id) {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (DB::query("SELECT tg_id FROM pay_tgadmin") as $r) $cache[(int)$r['tg_id']] = 1;
        } catch (Exception $e) {}
    }
    if ((int)$tg_id === (int)ADMIN_ID) return true;
    return isset($cache[(int)$tg_id]);
}

function cmdAddAdmin($chat_id, $user_id, $text) {
    if ((int)$user_id !== (int)ADMIN_ID) return Telegram::sendMessage($chat_id, "❌ 只有超级管理员可添加");

    $msg = $GLOBALS['__tg_msg__'] ?? null;
    $tid = 0; $remark = ''; $username = '';

    // 方式1：回复某人的消息
    if ($msg && isset($msg['reply_to_message']['from']['id'])) {
        $t = $msg['reply_to_message']['from'];
        $tid = (int)$t['id'];
        $username = $t['username'] ?? '';
        $remark = trim(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? ''));
        if ($remark === '') $remark = $username ?: ('ID:' . $tid);
    }
    // 方式2：@用户名（优先 getChat，对方无需先互动）
    elseif (preg_match('#^/添加管理员\s+@(\w+)(?:\s+(.+))?#u', $text, $m)) {
        $username = $m[1];
        $remark = trim($m[2] ?? '');
        $chatInfo = Telegram::getChat("@" . $username);
        if ($chatInfo && !empty($chatInfo["id"])) {
            $tid = (int)$chatInfo["id"];
        } else {
            $row = DB::one("SELECT tg_id FROM pay_tguser WHERE username=? ORDER BY last_seen DESC LIMIT 1", [$username]);
            if (!$row) {
                return Telegram::sendMessage($chat_id, "❌ 未找到 @{$username}\n\n原因可能：\n1. 对方没设置公开 @用户名\n2. 中转 API 不支持 getChat");
            }
            $tid = (int)$row["tg_id"];
        }
        if ($remark === '') $remark = '@' . $username;
    }
    // 方式3：数字 TG ID
    elseif (preg_match('#^/添加管理员\s+(\d+)(?:\s+(.+))?#u', $text, $m)) {
        $tid = (int)$m[1];
        $remark = trim($m[2] ?? '');
    }
    else {
        return Telegram::sendMessage($chat_id,
            "📖 <b>添加管理员</b>\n━━━━━━━━━━━━━━━━━━\n"
          . "① 回复某人的消息 → /添加管理员\n"
          . "② /添加管理员 @用户名 [备注]\n"
          . "③ /添加管理员 TG数字ID [备注]");
    }

    if ($tid <= 0) return Telegram::sendMessage($chat_id, "❌ 无效的 TG ID");
    if ($tid === (int)ADMIN_ID) return Telegram::sendMessage($chat_id, "ℹ️ 超管已在列表中");

    DB::exec("INSERT INTO pay_tgadmin(tg_id,username,remark) VALUES(?,?,?)
              ON DUPLICATE KEY UPDATE username=VALUES(username), remark=VALUES(remark)",
        [$tid, $username, $remark]);
    Telegram::sendMessage($chat_id, "✅ 已添加管理员\nID：<code>{$tid}</code>\n备注：{$remark}");
}

function cmdDelAdmin($chat_id, $user_id, $text) {
    if ((int)$user_id !== (int)ADMIN_ID) return Telegram::sendMessage($chat_id, "❌ 只有超级管理员可删除");

    $msg = $GLOBALS['__tg_msg__'] ?? null;
    $tid = 0;

    if ($msg && isset($msg['reply_to_message']['from']['id'])) {
        $tid = (int)$msg['reply_to_message']['from']['id'];
    }
    elseif (preg_match('#^/删除管理员\s+@(\w+)#u', $text, $m)) {
        $chatInfo = Telegram::getChat("@" . $m[1]);
        if ($chatInfo && !empty($chatInfo["id"])) {
            $tid = (int)$chatInfo["id"];
        } else {
            $row = DB::one("SELECT tg_id FROM pay_tguser WHERE username=? ORDER BY last_seen DESC LIMIT 1", [$m[1]]);
            if (!$row) return Telegram::sendMessage($chat_id, "❌ 未找到 @{$m[1]}");
            $tid = (int)$row["tg_id"];
        }
    }
    elseif (preg_match('#^/删除管理员\s+(\d+)#u', $text, $m)) {
        $tid = (int)$m[1];
    }
    else {
        return Telegram::sendMessage($chat_id,
            "📖 <b>删除管理员</b>\n"
          . "① 回复某人的消息 → /删除管理员\n"
          . "② /删除管理员 @用户名\n"
          . "③ /删除管理员 TG数字ID");
    }

    if ($tid === (int)ADMIN_ID) return Telegram::sendMessage($chat_id, "❌ 不能删除超级管理员");
    DB::exec("DELETE FROM pay_tgadmin WHERE tg_id=?", [$tid]);
    Telegram::sendMessage($chat_id, "✅ 已删除管理员：<code>{$tid}</code>");
}

function cmdListAdmins($chat_id, $user_id) {
    if (!isTgAdmin($user_id)) return;
    $rows = DB::query("SELECT * FROM pay_tgadmin ORDER BY addtime ASC");
    $t = "👑 <b>管理员列表</b>\n";
    $t .= "━━━━━━━━━━━━━━━━━━\n";
    foreach ($rows as $r) {
        $isSuper = ((int)$r['tg_id'] === (int)ADMIN_ID);
        $icon    = $isSuper ? '👑' : '🛡️';
        $title   = $isSuper ? '超级管理员' : '管理员';
        $uname   = $r['username'] ?? '';
        if ($uname === '') {
            $u = DB::one("SELECT username FROM pay_tguser WHERE tg_id=? LIMIT 1", [$r['tg_id']]);
            if ($u && !empty($u['username'])) $uname = $u['username'];
        }
        $t .= "{$icon} <b>{$title}</b>\n";
        $t .= "   🆔 <code>{$r['tg_id']}</code>\n";
        if ($uname !== '')        $t .= "   💬 @{$uname}\n";
        if (!empty($r['remark'])) $t .= "   🏷️ {$r['remark']}\n";
        $t .= "\n";
    }
    Telegram::sendMessage($chat_id, rtrim($t));
}


// ========== 客服机器人专属逻辑 ==========
function handleCustomerOnly($upd) {
    // ========== 新增：群组/私聊 引用回复客户消息 ==========
    if (isset($upd['message']['reply_to_message'])) {
        $m = $upd['message'];
        $chat_id = $m['chat']['id'];
        $replyMid = $m['reply_to_message']['message_id'] ?? 0;
        $replyText = $m['text'] ?? '';
        
        $isGroup = in_array($m['chat']['type'] ?? '', ['group', 'supergroup']);
        
        if ($replyMid && $replyText !== '') {
            try {
                // 精准查询：群聊查群消息ID，私聊查私聊消息ID，彻底防止串号
                if ($isGroup) {
                    $row = DB::one("SELECT * FROM pay_tg_relay WHERE group_msg_id=:g LIMIT 1", [':g'=>$replyMid]);
                } else {
                    $row = DB::one("SELECT * FROM pay_tg_relay WHERE admin_msg_id=:a LIMIT 1", [':a'=>$replyMid]);
                }
                
                if ($row) {
                    Telegram::sendMessage($row['customer_id'], $replyText);
                    Telegram::sendMessage($chat_id, "✅ 已发送给客户（ID: {$row['customer_id']}）");
                    return;
                } else {
                    // 兜底方案：针对没有存库的旧消息，暴力提取 ID
                    $quotedText = $m['reply_to_message']['text'] ?? '';
                    // 兼容可能没有 code 标签的情况
                    if (preg_match('/(?:客户标识：|🆔\s*)(\d+)/u', $quotedText, $m_id)) {
                        $cid_old = $m_id[1];
                        // 强制过滤：绝不能发给管理员自己！
                        $admins = array_map('trim', explode(',', RELAY_ADMINS));
                        if (!in_array((string)$cid_old, $admins) && $cid_old != $chat_id) {
                            Telegram::sendMessage($cid_old, $replyText);
                            Telegram::sendMessage($chat_id, "✅ 已发送给旧消息客户（ID: {$cid_old}）");
                            return;
                        }
                    }
                }
            } catch (Exception $e) {
                Telegram::sendMessage($chat_id, "❌ 发送失败: " . $e->getMessage());
                return;
            }
        }
    }
    // ========== 新增结束 ==========


    // ---------- 0. callback_query（点按钮） ----------
    if (isset($upd['callback_query'])) {
        $cq = $upd['callback_query'];
        // ===== 新：文档分类导航 =====
        $cbData = $cq['data'] ?? '';
        if (preg_match('#^cb_cat_([a-z]+)$#', $cbData, $m)) {
            $cid = $cq['from']['id'];
            $catKey = $m[1];
            Telegram::answerCallback($cq['id'], '加载中...');
            $kb = buildCatMenu($catKey);
            if ($kb) {
                $catName = $GLOBALS['__DOC_MAP__'][$catKey]['name'];
                Telegram::sendMessage($cid, "📂 <b>{$catName}</b>\n请选择要查看的文档：", $kb);
            } else {
                Telegram::sendMessage($cid, "❌ 分类不存在");
            }
            return;
        }
        if ($cbData === 'cb_back_menu') {
            $cid = $cq['from']['id'];
            Telegram::answerCallback($cq['id'], '返回主菜单');
            Telegram::sendMessage($cid, "📚 <b>文档中心</b> — 请选择分类：", buildDocMenu());
            return;
        }
        if (preg_match('#^cb_send_(.+)$#u', $cbData, $m)) {
            $cid = $cq['from']['id'];
            $docName = $m[1];
            Telegram::answerCallback($cq['id'], '发送中...');
            sendDocToChat($cid, $docName);
            return;
        }
        // ===== 新：文档分类导航 end =====
        if (strpos($cq['data'] ?? '', 'cb_doc_duijie') !== false) {
            $cid = $cq['from']['id'];
            Telegram::answerCallback($cq['id'], '发送中...');
            $p = pickDoc('商户对接文档');
            if (file_exists($p)) {
                Telegram::sendDocument($cid, $p, "📄 信达支付系统 · 商户对接文档（V1 + V2）- 带样式");
                Telegram::sendMessage($cid, "📄 已发送，手机点开即可查看~");
            } else {
                Telegram::sendMessage($cid, "文档暂不可用");
            }
            return;
        }
        if (strpos($cq['data'] ?? '', 'cb_doc_bot') !== false) {
            $cid = $cq['from']['id'];
            Telegram::answerCallback($cq['id'], '发送中...');
            $p = pickDoc('机器人操作说明');
            if (file_exists($p)) {
                Telegram::sendDocument($cid, $p, "📘 信达网络 · 机器人操作说明");
                Telegram::sendMessage($cid, "📘 已发送，手机点开即可查看~");
            } else {
                Telegram::sendMessage($cid, "文档暂不可用");
            }
            return;
        }
        if (strpos($cq['data'] ?? '', 'cb_doc_operation') !== false) {
            $cid = $cq['from']['id'];
            Telegram::answerCallback($cq['id'], '发送中...');
            $p = pickDoc('操作文档');
            if (file_exists($p)) {
                Telegram::sendDocument($cid, $p, "📗 信达网络 · 操作文档");
                Telegram::sendMessage($cid, "📗 已发送，手机点开即可查看~");
            } else {
                Telegram::sendMessage($cid, "文档暂不可用");
            }
            return;
        }
        if (strpos($cq['data'] ?? '', 'cb_doc_all') !== false) {
            $cid = $cq['from']['id'];
            Telegram::answerCallback($cq['id'], '发送全部文档...');
            $n = 0;
            foreach ($GLOBALS['__DOC_MAP__'] as $cat) {
                foreach ($cat['docs'] as $docName => $label) {
                    if (sendDocToChat($cid, $docName)) { $n++; sleep(1); }
                }
            }
            Telegram::sendMessage($cid, "📦 已发送 <b>{$n}</b> 份文档~");
            return;
        }
        if (strpos($cq['data'] ?? '', 'cb_bot_doc') !== false) {
            $cid = $cq['from']['id'];
            Telegram::answerCallback($cq['id'], '发送中...');
            $docPath = pickDoc('机器人操作说明');
            if (!file_exists($docPath)) {
                Telegram::sendMessage($cid, "📘 文档暂不可用，请稍后再试~");
                return;
            }
            Telegram::sendDocument($cid, $docPath, "📘 信达网络 · 机器人操作说明");
            Telegram::sendMessage($cid, "📘 文件已发送，手机点开就能看哦~\n有具体问题也可以直接问我~ 😊");
            return;
        }
        // ===== 新：申请加速审核 =====
        if (($cq['data'] ?? '') === 'cb_speed_review') {
            $cid = $cq['from']['id'];
            Telegram::answerCallback($cq['id'], '请稍候...');
            @file_put_contents(__DIR__ . '/.speed_' . $cid, '1');
            Telegram::sendMessage($cid, "🚀 <b>申请加速审核</b>\n\n请输入您注册时使用的<b>邮箱</b>或<b>手机号</b>（发送 /cancel 可取消）：");
            return;
        }
        
        if (strpos($cq['data'] ?? '', 'cb_end_human') !== false) {
            $cid = $cq['from']['id'];
            Telegram::answerCallback($cq['id'], '已退出人工');
            DB::exec("UPDATE pay_tg_human_session SET status=0, updated_at=NOW() WHERE customer_id=:c",
                     [':c'=>$cid]);
            Telegram::sendMessage($cid,
                "✅ <b>已退出人工模式</b>\n\nAI 助手继续为您服务~ 🤖\n"
                . "如需再次转人工，随时点击消息下方的【👤 转人工客服】按钮。");
            return;
        }
        if (strpos($cq['data'] ?? '', 'cb_need_human') !== false) {
            $cid = $cq['from']['id'];
            $cname = trim(($cq['from']['first_name'] ?? '') . ' ' . ($cq['from']['last_name'] ?? ''));
            $cuname = $cq['from']['username'] ?? '';
            Telegram::answerCallback($cq['id'], '已接入在线客服');
            DB::exec("INSERT INTO pay_tg_human_session (customer_id, status, started_at, updated_at)
                      VALUES (:c, 1, NOW(), NOW())
                      ON DUPLICATE KEY UPDATE status=1, updated_at=NOW()",
                     [':c'=>$cid]);
            Telegram::sendMessage($cid,
                "👤 <b>人工客服已接入</b>\n\n"
                . "请不要离开当前窗口，为了尽快解决您的问题，请直接描述您的问题，方便客服尽快处理~",
                ['inline_keyboard' => [[
                    ['text' => '❌ 结束人工', 'callback_data' => 'cb_end_human']
                ]]]);
            // 获取客户最近 5 条聊天记录
            $__hist = DB::query("SELECT role, content FROM pay_tg_chat_history WHERE customer_id=? ORDER BY id DESC LIMIT 5", [$cid]);
            $__hist = array_reverse($__hist);
            $__histText = "";
            foreach ($__hist as $__h) {
                $__role = $__h['role'] === 'user' ? '👤 客户' : '🤖 AI';
                $__histText .= "{$__role}：{$__h['content']}\n";
            }
            if (!$__histText) $__histText = "（无历史对话记录，客户直接点击了转人工）";

            $__msgText = "🚨 <b>新客户请求人工服务</b>\n"
                    . "🆔 客户标识：<code>{$cid}</code>\n"
                    . "━━━━━━━━━━━━━━━━━━\n"
                    . "💬 <b>最近对话：</b>\n{$__histText}"
                    . "━━━━━━━━━━━━━━━━━━\n"
                    . "—— 直接回复此消息即可回复客户 ——";
            
            // 发给管理员
            $admins = array_map('trim', explode(',', RELAY_ADMINS));
            $firstAdminMsgId = null;
            $firstGroupMsgId = null;
            foreach ($admins as $aid) {
                if (!$aid) continue;
                $r = Telegram::sendMessage($aid, $__msgText);
                if ($firstAdminMsgId === null && isset($r['result']['message_id'])) {
                    $firstAdminMsgId = $r['result']['message_id'];
                }
            }
            // 发给群组
            if (defined('RELAY_GROUP') && RELAY_GROUP) {
                $rg = Telegram::sendMessage(RELAY_GROUP, $__msgText);
                if (isset($rg['result']['message_id'])) {
                    $firstGroupMsgId = $rg['result']['message_id'];
                }
            }

            // 🔥 核心修复：记录到数据库，让引用回复能查到！
            DB::exec("INSERT INTO pay_tg_relay 
                        (customer_id, customer_name, customer_uname, admin_msg_id, group_msg_id)
                       VALUES (:c, :n, :u, :a, :g)",
                      [':c'=>$cid, ':n'=>$cname, ':u'=>$cuname, ':a'=>$firstAdminMsgId, ':g'=>$firstGroupMsgId]);
            return;
        }
        return;
    }

    // ---------- 1. 管理员回复 → 转给客户 ----------
    if (isset($upd['message']['reply_to_message'])) {
        $m = $upd['message'];
        $fromId = $m['from']['id'] ?? 0;
        $replyMid = $m['reply_to_message']['message_id'] ?? 0;
        $admins = array_map('trim', explode(',', RELAY_ADMINS));
        if (in_array((string)$fromId, $admins) && $replyMid) {
            $row = DB::one(
                "SELECT * FROM pay_tg_relay WHERE admin_msg_id=:a OR group_msg_id=:g LIMIT 1",
                [':a'=>$replyMid, ':g'=>$replyMid]
            );
            if ($row) {
                $replyText = $m['text'] ?? '';
                if ($replyText !== '') {
                    Telegram::sendMessage($row['customer_id'], $replyText);
                    Telegram::sendMessage($m['chat']['id'], "✅ 已发送给客户");
                }
            }
        }
        return;
    }

    // 只处理 message
    if (!isset($upd['message'])) return;

    $msg = $upd['message'];
    $chat_id = $msg['chat']['id'] ?? 0;
    $user_id = $msg['from']['id'] ?? 0;
    $text = trim($msg['text'] ?? '');
    $isGroup = (($msg['chat']['type'] ?? 'private') !== 'private');
    $isAdmin = isTgAdmin($user_id);

    // 保存 TG 用户
    if (isset($msg['from'])) saveTgUser($user_id, $msg['from']);


    // ---------- 1.5 群组/私聊 引用回复客户消息 ----------
    if (isset($msg['reply_to_message'])) {
        $replyMid = $msg['reply_to_message']['message_id'] ?? 0;
        $replyText = $msg['text'] ?? '';
        
        if ($replyMid && $replyText !== '') {
            // 查一下这个消息ID是不是咱们转发给群里的
            $row = DB::one(
                "SELECT * FROM pay_tg_relay WHERE admin_msg_id=:a OR group_msg_id=:g LIMIT 1",
                [':a'=>$replyMid, ':g'=>$replyMid]
            );
            
            if ($row) {
                // 找到了对应的客户，直接发给客户
                Telegram::sendMessage($row['customer_id'], $replyText);
                // 在当前聊天窗口回复提示
                Telegram::sendMessage($chat_id, "✅ 已发送给客户");
                return;
            }
        }
    }

    // ---------- 2. /start ----------
    if ($text === '/start' || $text === '/help' || $text === '/帮助' || $text === '/菜单' || $text === '/文档') {
        Telegram::sendMessage($chat_id,
            "✨ <b>欢迎来到信达网络科技 · 7×24 客服</b> 🎁\n"
          . "━━━━━━━━━━━━━━\n"
          . "🎁 <b>新商户限时惊喜福利</b> 🎊\n"
          . "🎉 <b>即日起注册，免月费体验全部高级功能（至 2026-11-01）！</b>\n"
          . "👉 <b>✅ 0 费率：平台不抽成，收多少拿多少！</b>\n"
          . "🔥 <b>不限制行业，可对接包网及四方线上平台！</b>\n"
          . "💬 <b>想了解更多？请点击：<a href=\"https://t.me/xindapay1H5_bot\">👉 联系客服 👈</a></b>\n\n"
          . "有任何问题直接发消息，人工客服会尽快回复。\n\n"
          . "📚 <b>文档中心</b>（点击下方分类查看）\n"
          . "7 大类 · 21 份文档，支持在线预览与下载。\n\n"
          . "💡 常见问题：\n"
          . "• 付款后未到账\n"
          . "• 订单状态查询\n"
          . "• API 对接协助\n"
          . "• APK 授权售后\n\n"
          . "请直接描述你的问题。",
            buildDocMenu());
        return;
    }

    // ---------- 2.5 文档命令（发文件） ----------
    if (in_array($text, ['/对接文档', '/对接', '/商户对接文档', '/文档'], true)) {
        $docPath = pickDoc('商户对接文档');
        if (!file_exists($docPath)) {
            Telegram::sendMessage($chat_id, "文档暂不可用，请联系客服");
            return;
        }
        Telegram::sendDocument($chat_id, $docPath, "📄 信达支付系统 · 商户对接文档（V1 + V2）- 带样式");
        Telegram::sendMessage($chat_id,
            "📄 <b>文档已发送</b>\n\n"
            . "• V1 接口 = MD5 签名\n"
            . "• V2 接口 = RSA2 签名\n\n"
            . "手机点开文件即可查看~");
        return;
    }

    if (in_array($text, ['/机器人说明', '/机器人操作说明', '/机器人文档'], true)) {
        $docPath = pickDoc('机器人操作说明');
        if (!file_exists($docPath)) {
            Telegram::sendMessage($chat_id, "文档暂不可用，请联系客服");
            return;
        }
        Telegram::sendDocument($chat_id, $docPath, "📘 信达网络 · 机器人操作说明");
        Telegram::sendMessage($chat_id, "📘 <b>文件已发送</b>，手机点开就能看哦~");
        return;
    }

    if (in_array($text, ['/操作文档', '/操作说明'], true)) {
        $docPath = pickDoc('操作文档');
        if (!file_exists($docPath)) {
            Telegram::sendMessage($chat_id, "文档暂不可用，请联系客服");
            return;
        }
        Telegram::sendDocument($chat_id, $docPath, "📗 信达网络 · 操作文档");
        Telegram::sendMessage($chat_id, "📗 <b>文件已发送</b>，手机点开就能看哦~");
        return;
    }

    if (in_array($text, ['/收款码推送', '/收款码配置', '/推送说明', '/码推送'], true)) {
        $html = pickDoc('收款码推送说明');
        $md   = pickDoc('收款码推送说明');
        if (file_exists($html)) {
            Telegram::sendDocument($chat_id, $html, "💳 收款码推送配置说明");
        } elseif (file_exists($md)) {
            Telegram::sendDocument($chat_id, $md, "💳 收款码推送配置说明");
        } else {
            Telegram::sendMessage($chat_id, "文档暂不可用，请联系客服");
            return;
        }
        Telegram::sendMessage($chat_id, "💳 <b>文件已发送</b>，手机点开就能看哦~");
        return;
    }

    if (in_array($text, ['/2FA', '/2fa', '/谷歌验证器'], true)) {
        $html = pickDoc('2FA开启教程');
        $md   = pickDoc('2FA开启教程');
        if (file_exists($html)) {
            Telegram::sendDocument($chat_id, $html, "🔐 2FA 谷歌验证器教程");
        } elseif (file_exists($md)) {
            Telegram::sendDocument($chat_id, $md, "🔐 2FA 谷歌验证器教程");
        } else {
            Telegram::sendMessage($chat_id, "文档暂不可用，请联系客服");
            return;
        }
        Telegram::sendMessage($chat_id, "🔐 <b>文件已发送</b>，手机点开就能看哦~");
        return;
    }

    if (in_array($text, ['/订单说明', '/订单管理', '/订单管理说明'], true)) {
        $html = pickDoc('订单管理说明');
        $md   = pickDoc('订单管理说明');
        if (file_exists($html)) {
            Telegram::sendDocument($chat_id, $html, "📋 订单管理说明");
        } elseif (file_exists($md)) {
            Telegram::sendDocument($chat_id, $md, "📋 订单管理说明");
        } else {
            Telegram::sendMessage($chat_id, "文档暂不可用，请联系客服");
            return;
        }
        Telegram::sendMessage($chat_id, "📋 <b>文件已发送</b>，手机点开就能看哦~");
        return;
    }

    if (in_array($text, ['/机器人创建', '/创建机器人', '/创建bot', '/新建bot'], true)) {
        $html = pickDoc('机器人创建');
        $md   = pickDoc('机器人创建');
        if (file_exists($html)) {
            Telegram::sendDocument($chat_id, $html, "🤖 自建机器人创建教程");
        } elseif (file_exists($md)) {
            Telegram::sendDocument($chat_id, $md, "🤖 自建机器人创建教程");
        } else {
            Telegram::sendMessage($chat_id, "文档暂不可用，请联系客服");
            return;
        }
        Telegram::sendMessage($chat_id, "🤖 <b>文件已发送</b>，手机点开就能看哦~");
        return;
    }

    if (in_array($text, ['/TG限流', '/tg限流', '/限流说明', '/推送限流'], true)) {
        $html = pickDoc('TG推送限流');
        $md   = pickDoc('TG推送限流');
        if (file_exists($html)) {
            Telegram::sendDocument($chat_id, $html, "📡 TG 推送限流说明");
        } elseif (file_exists($md)) {
            Telegram::sendDocument($chat_id, $md, "📡 TG 推送限流说明");
        } else {
            Telegram::sendMessage($chat_id, "文档暂不可用，请联系客服");
            return;
        }
        Telegram::sendMessage($chat_id, "📡 <b>文件已发送</b>，手机点开就能看哦~");
        return;
    }

    if (in_array($text, ['/全部文档', '/所有文档', '/文档大全'], true)) {
        $files = [
            ['/docs/商户对接文档.html', '📄 信达支付系统 · 商户对接文档（V1 + V2）'],
            ['/docs/机器人操作说明.md', '📘 信达网络 · 机器人操作说明'],
            ['/docs/操作文档.md', '📗 信达网络 · 操作文档'],
        ];
        $sent = 0;
        foreach ($files as $f) {
            $p = __DIR__ . $f[0];
            if (file_exists($p)) {
                Telegram::sendDocument($chat_id, $p, $f[1]);
                $sent++;
                sleep(1);
            }
        }
        if ($sent === 0) {
            Telegram::sendMessage($chat_id, "文档暂不可用，请联系客服");
        } else {
            Telegram::sendMessage($chat_id, "📦 已发送 <b>{$sent}</b> 份文档，手机点开即可查看~");
        }
        return;
    }

    // ---------- 2.7 /结束人工 ----------
    if (!$isAdmin && !$isGroup && in_array($text, ['/结束人工', '/结束', '/退出人工', '/exit'])) {
        DB::exec("UPDATE pay_tg_human_session SET status=0, updated_at=NOW() WHERE customer_id=:c",
                 [':c'=>$user_id]);
        Telegram::sendMessage($chat_id,
            "✅ <b>已退出人工模式</b>\n\nAI 助手继续为您服务~ 🤖\n"
            . "如需再次转人工，随时点击消息下方的【👤 转人工客服】按钮。");
        return;
    }

    // ---------- 2.8 拦截：加速审核输入 ----------
    $speedFile = __DIR__ . '/.speed_' . $user_id;
    if (!$isAdmin && !$isGroup && file_exists($speedFile)) {
        $account = trim($text);
        if ($account === '/cancel' || $account === '取消') {
            @unlink($speedFile);
            Telegram::sendMessage($chat_id, "已取消申请加速审核。");
            return;
        }
        if (!preg_match('/^[A-z0-9._-]+@[A-z0-9._-]+\.[A-z0-9._-]+$/', $account) && !preg_match('/^1[3-9]\d{9}$/', $account)) {
            if (strpos($text, '/绑定群') === 0 || strpos($text, '/id') === 0 || strpos($text, '/cancel') === 0) {
            unset($_SESSION['waiting_for_login']);
            unset($_SESSION['wait_for_account']);
            unset($_SESSION['state']);
        } else {
            Telegram::sendMessage($chat_id, "⚠️ 格式不正确，请输入正确的邮箱或11位手机号（发送 /cancel 取消）：");
        }
            return;
        }
        @unlink($speedFile);
        
        $msg = "🚀 <b>收到加速审核申请</b>\n"
             . "👤 [{$name}]" . ($uname ? " (@{$uname})" : '') . "\n"
             . "🆔 <code>{$chat_id}</code>\n"
             . "📧 注册账号：<b>{$account}</b>\n"
             . "—— 请及时为您审核";
             
        $admins = array_map('trim', explode(',', RELAY_ADMINS));
        foreach ($admins as $aid) {
            if (!$aid) continue;
            Telegram::sendMessage($aid, $msg);
        }
        if (defined('RELAY_GROUP') && RELAY_GROUP) {
            Telegram::sendMessage(RELAY_GROUP, $msg);
        }
        Telegram::sendMessage($chat_id, "✅ <b>申请已提交！</b>\n客服会尽快为您加速审核，请稍候~ 🚀");
        return;
    }

    // ---------- 3. 客户私聊（非命令）→ AI回复 + 转发管理员 ----------
    if (!$isAdmin && !$isGroup && $text !== '' && $text[0] !== '/') {
        $name  = trim(($msg['from']['first_name'] ?? '') . ' ' . ($msg['from']['last_name'] ?? ''));
        $uname = $msg['from']['username'] ?? '';

        // ★ 人工会话模式：直转管理员，不走 AI
        $hsRow = DB::one("SELECT status FROM pay_tg_human_session WHERE customer_id=:c AND status=1 LIMIT 1",
                         [':c'=>$user_id]);
        if ($hsRow) {
            $showText = trim($text) ?: '（客户发送了图片或附件，请直接回复查看）';
            $hint = "💬 <b>收到客户消息</b> (ID: <code>{$chat_id}</code>)\n"
                  . "━━━━━━━━━━━━━━━━━━\n"
                  . "{$showText}\n"
                  . "━━━━━━━━━━━━━━━━━━\n"
                  . "—— 直接回复此条消息即可回复客户 ——";
            $admins = array_map('trim', explode(',', RELAY_ADMINS));
            $firstAdminMsgId = null;
            $firstGroupMsgId = null;
            foreach ($admins as $aid) {
                if (!$aid) continue;
                $r = Telegram::sendMessage($aid, $hint);
                if ($firstAdminMsgId === null && isset($r['result']['message_id'])) {
                    $firstAdminMsgId = $r['result']['message_id'];
                }
            }
            if (defined('RELAY_GROUP') && RELAY_GROUP) {
                $rg = Telegram::sendMessage(RELAY_GROUP, $hint);
                if (isset($rg['result']['message_id'])) {
                    $firstGroupMsgId = $rg['result']['message_id'];
                }
            }
            DB::exec("INSERT INTO pay_tg_relay
                        (customer_id, customer_name, customer_uname, admin_msg_id, group_msg_id)
                       VALUES (:c, :n, :u, :a, :g)",
                      [':c'=>$chat_id, ':n'=>$name, ':u'=>$uname, ':a'=>$firstAdminMsgId, ':g'=>$firstGroupMsgId]);
            Telegram::sendMessage($chat_id, "📩 已发送给人工客服，请稍等回复~");
            return;
        }

        // 1) 存用户消息
        DeepSeek::saveHistory($chat_id, 'user', $text);

        // 2) 直接调 AI，由 AI 自己判断是否需要人工（提示词里已说明）
        $aiReply = DeepSeek::chat($chat_id, $text);

        // 3) 检测 AI 是否主动说"需要人工"（说明问题超出 AI 能力）
        $aiNeedHuman = $aiReply && preg_match('/需要人工|转人工|找人工|人工确认|转客服处理/i', $aiReply);

        // 4) 有 AI 回复 → 先发给客户
        if ($aiReply) {
            DeepSeek::saveHistory($chat_id, 'assistant', $aiReply);
            $kb2 = ['inline_keyboard' => [
                [['text' => '📘 机器人说明', 'callback_data' => 'cb_bot_doc']],
                [['text' => '👤 转人工客服', 'callback_data' => 'cb_need_human']]
            ]];
            Telegram::sendMessage($chat_id, $aiReply, $kb2);

            // 5) 同时转发给管理员（附 AI 已答内容）
            $hint = ($aiNeedHuman ? "🤖 <b>客户咨询（AI已答，建议人工跟进）</b>\n" : "🤖 <b>客户咨询（AI已自动回复）</b>\n")
                  . "👤 [{$name}]" . ($uname ? " (@{$uname})" : '') . "\n"
                  . "🆔 <code>{$chat_id}</code>\n"
                  . "━━━━━━━━━━━━━━━━━━\n"
                  . "💬 客户：{$text}\n"
                  . "━━━━━━━━━━━━━━━━━━\n"
                  . "🤖 AI答：{$aiReply}\n"
                  . "━━━━━━━━━━━━━━━━━━\n"
                  . "—— 如需纠正，直接回复此条消息 ——";

            $admins = array_map('trim', explode(',', RELAY_ADMINS));
            $firstAdminMsgId = null;
            $firstGroupMsgId = null;
            foreach ($admins as $aid) {
                if (!$aid) continue;
                $r = Telegram::sendMessage($aid, $hint);
                if ($firstAdminMsgId === null && isset($r['result']['message_id'])) {
                    $firstAdminMsgId = $r['result']['message_id'];
                }
            }
            if (defined('RELAY_GROUP') && RELAY_GROUP) {
                $rg = Telegram::sendMessage(RELAY_GROUP, $hint);
                if (isset($rg['result']['message_id'])) {
                    $firstGroupMsgId = $rg['result']['message_id'];
                }
            }
            DB::exec("INSERT INTO pay_tg_relay
                        (customer_id, customer_name, customer_uname, admin_msg_id, group_msg_id)
                       VALUES (:c, :n, :u, :a, :g)",
                      [':c'=>$chat_id, ':n'=>$name, ':u'=>$uname, ':a'=>$firstAdminMsgId, ':g'=>$firstGroupMsgId]);
            return;
        }

        // 6) AI 没答（敏感词 / API 失败）→ 纯转发人工
        $hint = "📩 <b>客户咨询（需人工）</b>\n"
               . "👤 [{$name}]" . ($uname ? " (@{$uname})" : '') . "\n"
               . "🆔 <code>{$chat_id}</code>\n"
               . "━━━━━━━━━━━━━━━━━━\n"
               . "{$text}\n"
               . "━━━━━━━━━━━━━━━━━━\n"
               . "—— 直接回复此条消息即可回复客户 ——";

        $admins = array_map('trim', explode(',', RELAY_ADMINS));
        $firstAdminMsgId = null;
        $firstGroupMsgId = null;
        foreach ($admins as $aid) {
            if (!$aid) continue;
            $r = Telegram::sendMessage($aid, $hint);
            if ($firstAdminMsgId === null && isset($r['result']['message_id'])) {
                $firstAdminMsgId = $r['result']['message_id'];
            }
        }
        if (defined('RELAY_GROUP') && RELAY_GROUP) {
            $rg = Telegram::sendMessage(RELAY_GROUP, $hint);
            if (isset($rg['result']['message_id'])) {
                $firstGroupMsgId = $rg['result']['message_id'];
            }
        }
        DB::exec("INSERT INTO pay_tg_relay
                    (customer_id, customer_name, customer_uname, admin_msg_id, group_msg_id)
                   VALUES (:c, :n, :u, :a, :g)",
                  [':c'=>$chat_id, ':n'=>$name, ':u'=>$uname, ':a'=>$firstAdminMsgId, ':g'=>$firstGroupMsgId]);

        // 兜底：给客户提示 + 转人工按钮
        $kb = ['inline_keyboard' => [
            [['text' => '📘 机器人说明', 'callback_data' => 'cb_bot_doc']],
            [['text' => '👤 转人工客服', 'callback_data' => 'cb_need_human']]
        ]];
        Telegram::sendMessage($chat_id,
            "这个问题可能需要人工客服帮您处理哦~ 🙋\n您可以点击下方按钮转人工，客服看到后会尽快回复您~",
            $kb);
        return;
    }

    // ---------- 4. 管理员私聊机器人 → AI 智能回复 ----------
    if ($isAdmin && !$isGroup) {
        if ($text === '') return;
        DeepSeek::saveHistory($chat_id, 'user', $text);
        $aiReply = DeepSeek::chat($chat_id, $text);
        if ($aiReply) {
            DeepSeek::saveHistory($chat_id, 'assistant', $aiReply);
            $kb_main = ['inline_keyboard' => [
                [['text' => '🚀 申请加速审核', 'callback_data' => 'cb_speed_review']],
                [['text' => '👤 转人工客服', 'callback_data' => 'cb_need_human']]
            ]];
            Telegram::sendMessage($chat_id, $aiReply, $kb_main);
        } else {
            Telegram::sendMessage($chat_id, "抱歉，AI 暂时无法回答，请稍后再试。\n如需管理功能，请用主机器人 @xindawangluo1_bot");
        }
        return;
    }

    // ---------- 5. 其他情况 → 忽略 ----------
    return;
}