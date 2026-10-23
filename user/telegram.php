<?php
include("../includes/common.php");
require_once(__DIR__ . '/../telegram/lib/DB.php');
if($islogin2 != 1) {
    if (isset($_GET['my']) && !empty($_GET['my'])) {
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode(['code'=>-1, 'msg'=>'登录状态已过期，请刷新页面重新登录']));
    }
    exit("<script language='javascript'>window.location.href='./login.php';</script>");
}
$title = '机器人管理';
$uid = $userrow['uid'];
$my = $_GET['my'] ?? '';

if (!$my) {
    include './head.php';
}

// 解绑群
if($my == 'unbind_group') {
    $chat_id = trim($_POST['chat_id'] ?? '');
    DB::exec("DELETE FROM pay_tggroup WHERE chat_id=? AND uid=?", [$chat_id, $uid]);
    exit(json_encode(['code'=>0,'msg'=>'已解除']));
}

// 解绑TG用户
if($my == 'unbind_user') {
    $tg_id = intval($_POST['tg_id'] ?? 0);
    DB::exec("DELETE FROM pay_tgbind WHERE tg_id=? AND uid=?", [$tg_id, $uid]);
    exit(json_encode(['code'=>0,'msg'=>'已解除']));
}

// ===== 商户自建机器人推送接口 =====
if($my == 'save_own_bot') {
    @file_put_contents('/tmp/save_bot_debug.log', date('Y-m-d H:i:s') . " | POST: " . json_encode($_POST, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    $use = intval($_POST['use_own_bot'] ?? 0);
    $tk  = trim($_POST['own_bot_token'] ?? '');
    $cid = trim($_POST['own_bot_chatid'] ?? '');
    $gid = trim($_POST['own_bot_groupid'] ?? '');

    // token 唯一性校验
    if ($tk) {
        $dup = DB::one("SELECT uid FROM pay_user_botconfig WHERE own_bot_token=? AND uid<>?", [$tk, $uid]);
        if ($dup) exit(json_encode(['code'=>-1, 'msg'=>'该 Token 已被其他商户绑定，请勿重复使用']));
    }

    // 生成/复用 webhook key
    $old = DB::one("SELECT own_bot_webhook_key FROM pay_user_botconfig WHERE uid=?", [$uid]);
    $whKey = $old['own_bot_webhook_key'] ?? '';
    if (!$whKey) $whKey = bin2hex(random_bytes(16));

    DB::exec("INSERT INTO pay_user_botconfig (uid, use_own_bot, own_bot_token, own_bot_chatid, own_bot_groupid, own_bot_webhook_key, addtime)
              VALUES (?,?,?,?,?,?,NOW())
              ON DUPLICATE KEY UPDATE use_own_bot=VALUES(use_own_bot), own_bot_token=VALUES(own_bot_token), own_bot_chatid=VALUES(own_bot_chatid), own_bot_groupid=VALUES(own_bot_groupid), own_bot_webhook_key=VALUES(own_bot_webhook_key)",
        [$uid, $use, $tk ?: null, $cid ?: null, $gid ?: null, $whKey]);

    // 只要开启了自建机器人，自动把推送渠道加上 TG（按位或 2）
    if ($use === 1) {
        $currentPc = (int)DB::col("SELECT push_channel FROM pay_user_botconfig WHERE uid=?", [$uid]);
        $newPc = $currentPc | 2; // 0变2，1变3
        DB::exec("UPDATE pay_user_botconfig SET push_channel=?, notice_paid=1 WHERE uid=?", [$newPc, $uid]);
    }

    // 自动 setWebhook
    $whResult = '';
    if ($tk) {
        $hookUrl = 'https://pay.yunzexinda.com/user_bot_hook.php?key=' . $whKey;
        $api = "https://api.telegram.org/bot{$tk}/setWebhook?url=" . urlencode($hookUrl);
        $ch = curl_init($api);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10, CURLOPT_SSL_VERIFYPEER=>false]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $r = curl_exec($ch);
        curl_close($ch);
        $j = json_decode($r, true);
        $whResult = !empty($j['ok']) ? '（已成功绑定 Webhook，私聊机器人发 /id 可获取 ID）' : '（Webhook 绑定失败，请检查 Token 或网络）';
    }

    exit(json_encode(['code'=>0, 'msg'=>'配置保存成功！' . $whResult]));
}
if($my == 'test_own_bot') {
    require_once(__DIR__ . '/../includes/bot_sender.php');
    $tk  = trim($_POST['own_bot_token'] ?? '');
    $cid = trim($_POST['own_bot_chatid'] ?? '');
    $gid = trim($_POST['own_bot_groupid'] ?? '');
    $target = $gid ?: $cid;
    if (!$tk || !$target) exit(json_encode(['code'=>-1, 'msg'=>'请先填写 Token 和推送目标 ID']));
    $txt = "🔔 <b>测试订单推送</b>\n"
         . "━━━━━━━━━━━━━━━━━━\n"
         . "📦 商品：测试商品\n"
         . "💰 金额：<b>￥88.88</b>\n"
         . "🔑 订单：TEST" . date('His') . "\n"
         . "🕐 时间：" . date('Y-m-d H:i:s') . "\n"
         . "━━━━━━━━━━━━━━━━━━\n"
         . "✅ 说明你的自建机器人已配置成功";
    $ok = botSend($tk, $target, $txt);
    exit(json_encode(['code'=>$ok?0:-1, 'msg'=>$ok?'测试订单已发送，请查看 TG':'发送失败，请检查 Token 和推送目标 ID']));
}

// ===== 推送渠道选择保存 =====
if($my == 'save_push_channel') {
    $channel = intval($_POST['push_channel'] ?? 0);
    if ($channel < 0 || $channel > 3) exit(json_encode(['code'=>-1, 'msg'=>'参数错误']));
    $__gid = (int)DB::col("SELECT gid FROM pay_user WHERE uid=?", [$uid]);
    $isStandard = ($__gid === 4); // 标准商户组
    if ($channel & 1) {
        $m = DB::one("SELECT mail_enabled, mail_to, mail_smtp_host, mail_smtp_user, mail_smtp_pwd FROM pay_user_botconfig WHERE uid=?", [$uid]);
        if (!$m || intval($m['mail_enabled']) !== 1 || empty($m['mail_to'])) {
            exit(json_encode(['code'=>-1, 'msg'=>'请先在「邮件推送」里填写收件邮箱并开启']));
        }
        if ($isStandard) {
            $hasSmtp = !empty($m['mail_smtp_host']) && !empty($m['mail_smtp_user']) && !empty($m['mail_smtp_pwd']);
            if (!$hasSmtp) {
                exit(json_encode(['code'=>-1, 'msg'=>'标准商户组必须配置「自建 SMTP 服务器」才能选邮件推送，平台不会代替您发送']));
            }
        }
    }
    if ($channel & 2) {
        $b = DB::one("SELECT use_own_bot, own_bot_token FROM pay_user_botconfig WHERE uid=?", [$uid]);
        $hasOwn = $b && intval($b['use_own_bot'])===1 && !empty($b['own_bot_token']);
        if ($isStandard) {
            if (!$hasOwn) {
                exit(json_encode(['code'=>-1, 'msg'=>'标准商户组必须配置「自建机器人 Token」才能选 TG 推送，平台不会代替您发送']));
            }
        } else {
            $hasBind = DB::col("SELECT COUNT(*) FROM pay_tgbind WHERE uid=?", [$uid]);
            if (!$hasOwn && $hasBind == 0) {
                exit(json_encode(['code'=>-1, 'msg'=>'请先配置「自建机器人」或绑定平台机器人 TG 后再选 TG 推送']));
            }
        }
    }
    $exists = DB::one("SELECT uid FROM pay_user_botconfig WHERE uid=?", [$uid]);
    if (!$exists) DB::exec("INSERT INTO pay_user_botconfig (uid, addtime) VALUES (?, NOW())", [$uid]);
    DB::exec("UPDATE pay_user_botconfig SET push_channel=? WHERE uid=?", [$channel, $uid]);
    exit(json_encode(['code'=>0, 'msg'=>'推送渠道已保存']));
}

// ===== 邮件推送保存 =====
if($my == 'save_mail_push') {
    $enabled = intval($_POST['mail_enabled'] ?? 0);
    $mail_to = trim($_POST['mail_to'] ?? '');
    if ($enabled === 1) {
        if (empty($mail_to)) exit(json_encode(['code'=>-1, 'msg'=>'请填写收件邮箱']));
        $emails = array_map('trim', explode(',', $mail_to));
        foreach ($emails as $em) {
            if (!filter_var($em, FILTER_VALIDATE_EMAIL)) {
                exit(json_encode(['code'=>-1, 'msg'=>"邮箱格式错误：{$em}"]));
            }
        }
    }
    $exists = DB::one("SELECT uid FROM pay_user_botconfig WHERE uid=?", [$uid]);
    if (!$exists) DB::exec("INSERT INTO pay_user_botconfig (uid, addtime) VALUES (?, NOW())", [$uid]);
    DB::exec("UPDATE pay_user_botconfig SET mail_enabled=?, mail_to=? WHERE uid=?",
        [$enabled, $mail_to ?: null, $uid]);
    exit(json_encode(['code'=>0, 'msg'=>'邮件推送配置已保存']));
}

if($my == 'test_mail_push') {
    $mail_to = trim($_POST['mail_to'] ?? '');
    if (empty($mail_to)) exit(json_encode(['code'=>-1, 'msg'=>'请先填写邮箱']));
    require_once(__DIR__ . '/../includes/lib/MailOrder.php');
    $testOrder = [
        'trade_no' => 'TEST' . date('YmdHis') . rand(1000,9999),
        'uid' => $uid,
        'name' => '邮件推送测试',
        'money' => '1.00',
        'realmoney' => '1.00',
    ];
    $r = \lib\MailOrder::push($testOrder, $mail_to);
    exit(json_encode($r));
}

// ===== 登录安全提醒设置接口 =====
if($my == 'save_login_notice') {
    $tg_confirm = intval($_POST['tg_confirm'] ?? 0);
    $bot_token  = trim($_POST['bot_token'] ?? '');
    $chat_id    = trim($_POST['chat_id'] ?? '');
    DB::exec("UPDATE pay_user SET tg_confirm=?, login_bot_token=?, login_bot_chatid=? WHERE uid=?",
        [$tg_confirm, $bot_token ?: null, $chat_id ?: null, $uid]);
    exit(json_encode(['code'=>0, 'msg'=>'保存成功']));
}
if($my == 'test_login_notice') {
    require_once(__DIR__ . '/../includes/login_notice.php');
    $bot_token = trim($_POST['bot_token'] ?? '');
    $chat_id   = trim($_POST['chat_id'] ?? '');
    if (!$bot_token || !$chat_id) exit(json_encode(['code'=>-1, 'msg'=>'请先填写 Token 和 Chat ID']));
    $ok = @sendLoginNotice($bot_token, $chat_id, $userrow['email'] ?: ('UID'.$uid),
        '测试推送', '1.2.3.4', '本地测试', 'Windows', 'Chrome', date('Y-m-d H:i:s'));
    exit(json_encode(['code'=>$ok?0:-1, 'msg'=>$ok?'测试消息已发送，请查看 TG':'发送失败，请检查 Token 和 Chat ID']));
}

// 保存通知配置
if($my == 'save_notify') {
    $notice_paid = intval($_POST['notice_paid'] ?? 0);
    $notice_created = intval($_POST['notice_created'] ?? 0);
    DB::exec("REPLACE INTO pay_user_botconfig(uid, notice_paid, notice_created, addtime) 
              VALUES(?,?,?,NOW())", [$uid, $notice_paid, $notice_created]);
    exit(json_encode(['code'=>0,'msg'=>'保存成功']));
}

$groups = DB::query("SELECT * FROM pay_tggroup WHERE uid=? ORDER BY addtime DESC", [$uid]);
$users  = DB::query("SELECT * FROM pay_tgbind WHERE uid=? ORDER BY addtime DESC", [$uid]);

// 读配置
$cfg = DB::one("SELECT * FROM pay_user_botconfig WHERE uid=?", [$uid]);
if (!$cfg) $cfg = ['notice_paid'=>1, 'notice_created'=>0];

// 定义页面头部状态变量
$hasOwn = $cfg && intval($cfg['use_own_bot'] ?? 0)===1 && !empty($cfg['own_bot_token']);

// 检测表是否存在
try {
    DB::query("SELECT 1 FROM pay_user_botconfig LIMIT 1");
} catch (Exception $e) {}
?>
<div id="content" class="app-content" role="main">
<div class="app-content-body">

<div class="bg-light lter b-b wrapper-md hidden-print">

<div class="alert alert-success" style="margin-bottom: 20px; border-left: 4px solid #10b981; background: #ecfdf5; color: #065f46; padding: 15px; border-radius: 8px;">
    <h4 style="margin-top:0;"><i class="fa fa-telegram"></i> 您的专属机器人配置</h4>
    <p style="margin-bottom:10px;">为了保证您的订单不被其他商户干扰，请配置您自己的机器人。</p>
    <ol style="padding-left: 20px; margin-bottom: 15px; line-height: 1.8;">
        <li>在 Telegram 搜索 <b>@BotFather</b>，发送 <code>/newbot</code> 创建机器人，获取 <b>Token</b>。</li>
        <li>搜索 <b>@userinfobot</b>，获取您的数字 <b>ChatID</b>。</li>
        <li>将 Token 和 ChatID 填入下方，并确保上方的“推送渠道”选择了 <b>“自有机器人”</b>。</li>
    </ol>
    <div class="row">
        <div class="col-sm-6">
            <label>机器人 Token</label>
            <input type="text" id="own_bot_token" class="form-control" placeholder="如 12345678:AAH..." value="<?php echo htmlspecialchars($botConf['own_bot_token'] ?? ''); ?>">
        </div>
        <div class="col-sm-6">
            <label>您的 ChatID</label>
            <input type="text" id="own_bot_chatid" class="form-control" placeholder="如 7811967320" value="<?php echo htmlspecialchars($botConf['own_bot_chatid'] ?? ''); ?>">
        </div>
    </div>
    <button class="btn btn-success" style="margin-top:15px;" onclick="saveOwnBot()">保存专属机器人配置</button>
</div>
<script>
function saveOwnBot() {
    var tk = document.getElementById('own_bot_token').value;
    var cid = document.getElementById('own_bot_chatid').value;
    $.post('telegram.php', { my: 'save_own_bot', use_own_bot: 1, own_bot_token: tk, own_bot_chatid: cid }, function(res){
        if(res.code === 0) { alert(res.msg); location.reload(); }
        else { alert('保存失败: ' + res.msg); }
    }, 'json');
}
</script>

  <h1 class="m-n font-thin h3">🤖 机器人管理</h1>
</div>

<div class="wrapper-md control">

<!-- 状态卡片 -->
<div class="row">
  <div class="col-md-4">
    <div class="panel panel-info">
      <div class="panel-heading font-bold"><i class="fa fa-info-circle"></i> 机器人状态</div>
      <div class="panel-body text-center">
        <?php if ($hasOwn): ?>
            <div style="font-size:36px; color:#52c41a;"><i class="fa fa-check-circle"></i></div>
            <p style="margin-top:10px;">自建机器人运行中</p>
            <p style="color:#999; font-size:12px;"><?php echo substr($cfg['own_bot_token'], 0, strpos($cfg['own_bot_token'], ':') ?: 10); ?></p>
        <?php else: ?>
            <div style="font-size:36px; color:#ccc;"><i class="fa fa-exclamation-circle"></i></div>
            <p style="margin-top:10px;">未配置自建机器人</p>
            <p style="color:#999; font-size:12px;">请拉至下方配置</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="panel panel-default">
      <div class="panel-heading font-bold"><i class="fa fa-users"></i> 绑定群</div>
      <div class="panel-body text-center">
        <?php 
            $gCount = count($groups);
            if ($hasOwn && !empty($cfg['own_bot_groupid'])) {
                $gArr = array_filter(array_map('trim', explode(',', $cfg['own_bot_groupid'])));
                $gCount = count($gArr);
            }
        ?>
        <div style="font-size:36px; color:#1677ff;"><?php echo $gCount; ?></div>
        <p style="color:#999; font-size:12px;">个群已绑定</p>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="panel panel-default">
      <div class="panel-heading font-bold"><i class="fa fa-user"></i> 绑定用户</div>
      <div class="panel-body text-center">
        <?php 
            $uCount = count($users);
            if ($hasOwn && !empty($cfg['own_bot_chatid'])) {
                $uCount = 1;
            }
        ?>
        <div style="font-size:36px; color:#faad14;"><?php echo $uCount; ?></div>
        <p style="color:#999; font-size:12px;">个TG用户</p>
      </div>
    </div>
  </div>
</div>

<!-- 推送渠道选择 -->
<div class="panel panel-warning" style="border:2px solid #faad14;">
  <div class="panel-heading font-bold" style="background:#fffbe6;"><i class="fa fa-bullhorn" style="color:#faad14;"></i> 订单推送渠道（商户自主选择）</div>
  <div class="panel-body">
    <div class="alert alert-info" style="font-size:13px;margin-bottom:16px;">
      <b>说明：</b>订单到账时，系统按您选择的渠道推送确认通知。可单选也可都选，二者互不影响。
      <br>⚠️ <b>未选择任何渠道 = 不推送订单通知</b>，请务必选择一种。<br>📌 <b style="color:#e11d48;">标准商户组</b>：邮件必须自建 SMTP，TG 必须自建机器人，平台不会代替您发送。
    </div>
    <form class="form-horizontal" onsubmit="return false;">
      <?php
      $__pc = 0;
      $__pcRow = DB::one("SELECT push_channel FROM pay_user_botconfig WHERE uid=?", [$uid]);
      if ($__pcRow) $__pc = (int)$__pcRow['push_channel'];
      ?>
      <div class="form-group">
        <label class="col-sm-3 control-label">推送方式</label>
        <div class="col-sm-9">
          <label class="radio-inline">
            <input type="radio" name="push_channel_radio" value="0" <?php echo $__pc===0?'checked':''; ?>>
            <b>不推送</b>（关闭订单通知）
          </label>
          <br>
          <label class="radio-inline">
            <input type="radio" name="push_channel_radio" value="1" <?php echo $__pc===1?'checked':''; ?>>
            📧 <b>仅邮件</b>（发到配置的收件邮箱）
          </label>
          <br>
          <label class="radio-inline">
            <input type="radio" name="push_channel_radio" value="2" <?php echo $__pc===2?'checked':''; ?>>
            🤖 <b>仅 TG</b>（推送到绑定的 TG 用户/群 / 自建机器人）
          </label>
          <br>
          <label class="radio-inline">
            <input type="radio" name="push_channel_radio" value="3" <?php echo $__pc===3?'checked':''; ?>>
            📧+🤖 <b>邮件 + TG</b>（双保险，两路都发）
          </label>
        </div>
      </div>
      <div class="form-group">
        <div class="col-sm-offset-3 col-sm-9">
          <button class="btn btn-warning" onclick="savePushChannel()"><i class="fa fa-save"></i> 保存推送渠道</button>
          <span id="__pc_status" style="margin-left:12px;color:#999;font-size:13px;"></span>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
function savePushChannel(){
  var r = document.querySelector('input[name="push_channel_radio"]:checked');
  if(!r){ alert('请选择推送方式'); return; }
  var fd = new FormData();
  fd.append('push_channel', r.value);
  fetch('?my=save_push_channel', {method:'POST', body:fd})
    .then(x=>x.json()).then(d=>{
      var el = document.getElementById('__pc_status');
      el.textContent = d.msg;
      el.style.color = d.code===0 ? '#07c160' : '#e74c3c';
      setTimeout(()=>{ el.textContent=''; }, 4000);
    });
}
</script>

<!-- 通知配置 -->
<div class="panel panel-default">
  <div class="panel-heading font-bold"><i class="fa fa-bell"></i> 通知配置</div>
  <div class="panel-body">
    <form class="form-horizontal" onsubmit="return false;">
      <div class="form-group">
        <label class="col-sm-3 control-label">到账通知</label>
        <div class="col-sm-9">
          <label class="checkbox-inline">
            <input type="checkbox" id="notice_paid" <?php echo $cfg['notice_paid'] ? 'checked' : ''; ?>>
            订单支付成功时推送通知到绑定群
          </label>
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label">下单通知</label>
        <div class="col-sm-9">
          <label class="checkbox-inline">
            <input type="checkbox" id="notice_created" <?php echo $cfg['notice_created'] ? 'checked' : ''; ?>>
            有新订单下单时推送（默认为关闭，避免骚扰）
          </label>
        </div>
      </div>
      <div class="form-group">
        <div class="col-sm-offset-3 col-sm-9">
          <button class="btn btn-primary" onclick="saveNotify()"><i class="fa fa-save"></i> 保存配置</button>
        </div>
      </div>
    </form>
  </div>
</div>



<!-- 自建机器人推送订单 -->
<div class="panel panel-primary">
  <div class="panel-heading font-bold"><i class="fa fa-cogs"></i> 自建机器人推送订单（推荐）</div>
  <div style="padding:8px 15px;background:#f0f8ff;border-bottom:1px solid #d6e4ff;font-size:13px;">
    📖 <b>必看教程：</b>
    <a href="docs/机器人说明.html" target="_blank" style="color:#1677ff;text-decoration:underline;font-weight:700;">📖 打开机器人完整说明（推荐）</a>
    <span style="color:#999;margin-left:8px;">包含创建教程 + 命令大全</span>
  </div>
  <div class="panel-body">
    <div class="alert alert-warning" style="font-size:13px;">
      <b>作用：</b>用你自己的 TG 机器人推送订单数据到你的 TG 账号或群，<b>不依赖平台默认机器人</b>。<br>
      <b>优点：</b>推送稳定、可自定义、可绑定自己的私人群。<br>
      <b>💡 获取 ID 超简单：</b>保存配置后，<b>私聊你的机器人发 <code>/id</code></b> 获取个人 ID；<br>
      <b>把机器人拉进群，群里发 <code>/id</code></b> 获取群 ID。
    </div>
    <form class="form-horizontal" onsubmit="return false;">
      <div class="form-group">
        <label class="col-sm-3 control-label">启用自建推送</label>
        <div class="col-sm-9">
          <label class="checkbox-inline">
            <input type="checkbox" id="use_own_bot" <?php echo intval($cfg['use_own_bot'] ?? 0)===1 ? 'checked' : ''; ?>>
            用自建机器人推送订单
          </label>
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label">自建机器人 Token</label>
        <div class="col-sm-9">
          <input type="text" id="own_bot_token" class="form-control" placeholder="从 @BotFather 创建获取" value="<?php echo htmlspecialchars($cfg['own_bot_token'] ?? ''); ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label">私聊 Chat ID</label>
        <div class="col-sm-9">
          <input type="text" id="own_bot_chatid" class="form-control" placeholder="例如：7811967320" value="<?php echo htmlspecialchars($cfg['own_bot_chatid'] ?? ''); ?>">
          <small style="color:#999;">私聊机器人发 /id 可获取你的 Chat ID</small>
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label">群 ID（选填）</label>
        <div class="col-sm-9">
          <input type="text" id="own_bot_groupid" class="form-control" placeholder="例如：-1001234567890，多群用逗号隔开" value="<?php echo htmlspecialchars($cfg['own_bot_groupid'] ?? ''); ?>">
          <small style="color:#e67e22;">★ 填了群 ID → 订单推送到群；留空 → 推送到私聊。<br>获取群 ID：把机器人拉进群，在群里 @userinfobot 或转发消息给 @userinfobot</small>
        </div>
      </div>
      <div class="form-group">
        <div class="col-sm-offset-3 col-sm-9">
          <button class="btn btn-primary" onclick="saveOwnBot()"><i class="fa fa-save"></i> 保存</button>
          <button class="btn btn-default" onclick="testOwnBot()"><i class="fa fa-paper-plane"></i> 发送测试订单</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- 登录安全提醒配置 -->
<div class="panel panel-success">
  <div class="panel-heading font-bold"><i class="fa fa-shield"></i> 登录安全提醒（推荐开启）</div>
  <div class="panel-body">
    <div class="alert alert-info" style="font-size:13px;">
      <b>作用：</b>每次有人登录你的商户账号，立即推送 TG 消息（含时间/IP/地区/设备）。<br>
      <b>用途：</b>发现非本人登录时第一时间察觉，防止账号被盗、资料被篡改。
    </div>
    <form class="form-horizontal" onsubmit="return false;">
      <div class="form-group">
        <label class="col-sm-3 control-label">开启提醒</label>
        <div class="col-sm-9">
          <label class="checkbox-inline">
            <input type="checkbox" id="tg_confirm" <?php echo intval($userrow['tg_confirm'])===1 ? 'checked' : ''; ?>>
            登录时推送 TG 提醒
          </label>
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label">机器人 Token</label>
        <div class="col-sm-9">
          <input type="text" id="login_bot_token" class="form-control" placeholder="例如：8625129023:AAFL..." value="<?php echo htmlspecialchars($userrow['login_bot_token'] ?? ''); ?>">
          <small style="color:#999;">在 @BotFather 创建自己的机器人获取</small>
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label">Chat ID</label>
        <div class="col-sm-9">
          <input type="text" id="login_bot_chatid" class="form-control" placeholder="例如：7811967320" value="<?php echo htmlspecialchars($userrow['login_bot_chatid'] ?? ''); ?>">
          <small style="color:#999;">私聊机器人发 /id 可查看你的 Chat ID</small>
        </div>
      </div>
      <div class="form-group">
        <div class="col-sm-offset-3 col-sm-9">
          <button class="btn btn-success" onclick="saveLoginNotice()"><i class="fa fa-save"></i> 保存</button>
          <button class="btn btn-default" onclick="testLoginNotice()"><i class="fa fa-paper-plane"></i> 发送测试</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- 已绑定的群 -->
<div class="panel panel-default">
  <div class="panel-heading font-bold"><i class="fa fa-users"></i> 已绑定的群（<?php echo count($groups); ?>）</div>
  <div class="table-responsive">
    <table class="table table-striped">
      <thead><tr><th>群 ID</th><th>群名</th><th>时间</th><th>操作</th></tr></thead>
      <tbody>
      <?php if(empty($groups)): ?>
        <tr><td colspan="4" style="text-align:center;color:#999;padding:30px;">还没有绑定</td></tr>
      <?php else: foreach($groups as $g): ?>
        <tr>
          <td><code><?php echo $g['chat_id']; ?></code></td>
          <td><?php echo htmlspecialchars($g['title']); ?></td>
          <td><?php echo $g['addtime']; ?></td>
          <td><a href="javascript:;" onclick="unbindGroup('<?php echo $g['chat_id']; ?>')" style="color:#e74c3c;">解除</a></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- 已绑定的用户 -->
<div class="panel panel-default">
  <div class="panel-heading font-bold"><i class="fa fa-user"></i> 已绑定的 TG 用户（<?php echo count($users); ?>）</div>
  <div class="table-responsive">
    <table class="table table-striped">
      <thead><tr><th>TG ID</th><th>时间</th><th>操作</th></tr></thead>
      <tbody>
      <?php if(empty($users)): ?>
        <tr><td colspan="3" style="text-align:center;color:#999;padding:30px;">还没有绑定</td></tr>
      <?php else: foreach($users as $u): ?>
        <tr>
          <td><code><?php echo $u['tg_id']; ?></code></td>
          <td><?php echo $u['addtime']; ?></td>
          <td><a href="javascript:;" onclick="unbindUser(<?php echo $u['tg_id']; ?>)" style="color:#e74c3c;">解除</a></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- 快捷指令 -->
<div class="panel panel-default">
  <div class="panel-heading font-bold"><i class="fa fa-terminal"></i> 机器人支持的指令</div>
  <div class="panel-body" style="font-size:13px; line-height:2;">
    <div class="row">
      <div class="col-md-6">
        <h5><b>查询类</b></h5>
        <p><code>/余额</code> — 查商户余额<br>
        <code>/订单</code> — 最近 10 笔订单<br>
        <code>/统计</code> — 平台数据</p>
      </div>
      <div class="col-md-6">
        <h5><b>工具类</b></h5>
        <p><code>查 T地址</code> — TRON 余额查询<br>
        <code>监控 T地址</code> — 地址监控<br>
        <code>Z0</code> — OKX 汇率</p>
      </div>
    </div>
  </div>
</div>

</div>
</div>

<script>
function unbindGroup(cid) {
  if(!confirm('确定解除该群绑定？')) return;
  const fd = new FormData(); fd.append('chat_id', cid);
  fetch('?my=unbind_group', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
    alert(d.msg); location.reload();
  });
}
function unbindUser(id) {
  if(!confirm('确定解除？')) return;
  const fd = new FormData(); fd.append('tg_id', id);
  fetch('?my=unbind_user', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
    alert(d.msg); location.reload();
  });
}
function saveOwnBot() {
  var fd = new FormData();
  fd.append('use_own_bot', document.getElementById('use_own_bot').checked ? 1 : 0);
  fd.append('own_bot_token', document.getElementById('own_bot_token').value.trim());
  fd.append('own_bot_chatid', document.getElementById('own_bot_chatid').value.trim());
  fd.append('own_bot_groupid', (document.getElementById('own_bot_groupid')||{value:''}).value.trim());
  fetch('?my=save_own_bot', {method:'POST', body:fd})
  .then(async r => {
      let text = await r.text();
      try {
          return JSON.parse(text);
      } catch(e) {
          // 如果解析失败，说明后端抛出了HTML错误，截取最前面的文字
          alert('保存失败！系统返回了异常数据，请检查配置或刷新重试。');
          throw new Error('Not JSON');
      }
  })
  .then(d=>{ alert(d.msg); })
  .catch(e=>{});
}
function testOwnBot() {
  var fd = new FormData();
  fd.append('own_bot_token', document.getElementById('own_bot_token').value.trim());
  fd.append('own_bot_chatid', document.getElementById('own_bot_chatid').value.trim());
  fd.append('own_bot_groupid', (document.getElementById('own_bot_groupid')||{value:''}).value.trim());
  fetch('?my=test_own_bot', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{ alert(d.msg); });
}

function saveMailPush() {
  var fd = new FormData();
  fd.append('mail_enabled', document.getElementById('mail_enabled').checked ? 1 : 0);
  fd.append('mail_to', document.getElementById('mail_to').value.trim());
  fetch('?my=save_mail_push', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{ alert(d.msg); });
}
function testMailPush() {
  var fd = new FormData();
  fd.append('mail_to', document.getElementById('mail_to').value.trim());
  fetch('?my=test_mail_push', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{ alert(d.msg); });
}

function saveLoginNotice() {
  var fd = new FormData();
  fd.append('tg_confirm', document.getElementById('tg_confirm').checked ? 1 : 0);
  fd.append('bot_token', document.getElementById('login_bot_token').value.trim());
  fd.append('chat_id', document.getElementById('login_bot_chatid').value.trim());
  fetch('?my=save_login_notice', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{ alert(d.msg); });
}
function testLoginNotice() {
  var fd = new FormData();
  fd.append('bot_token', document.getElementById('login_bot_token').value.trim());
  fd.append('chat_id', document.getElementById('login_bot_chatid').value.trim());
  fetch('?my=test_login_notice', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{ alert(d.msg); });
}

function saveNotify() {
  const fd = new FormData();
  fd.append('notice_paid', document.getElementById('notice_paid').checked ? 1 : 0);
  fd.append('notice_created', document.getElementById('notice_created').checked ? 1 : 0);
  fetch('?my=save_notify', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
    alert(d.msg);
  });
}
</script>
<?php include './foot.php'; ?>
