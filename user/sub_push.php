<?php
$is_defend = true;
include("../includes/common.php");
if($islogin2 != 1) exit("<script>location.href='./login.php';</script>");

$uid = (int)$userrow['uid'];
$myLevel = (int)$userrow['sub_level'];

// ───── 测试推送 ─────
if (($_GET['act'] ?? '') === 'test') {
    header('Content-Type: application/json; charset=utf-8');
    $tg     = trim($_POST['tg'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $host   = trim($_POST['smtp_host'] ?? '');
    $user   = trim($_POST['smtp_user'] ?? '');
    $pwd    = trim($_POST['smtp_pwd'] ?? '');
    $secure = trim($_POST['smtp_secure'] ?? 'ssl');

    $okTg = false; $okMail = false; $msgs = [];

    // 1) 测 TG
    if ($tg !== '') {
        // 拿上级的 Bot Token
        $upid = (int)$DB->getColumn("SELECT upid FROM pay_user WHERE uid=?", [$uid]);
        $botToken = '';
        if ($upid > 0) {
            $botToken = (string)$DB->getColumn("SELECT own_bot_token FROM pay_user_botconfig WHERE uid=?", [$upid]);
        }
        if (!$botToken) $botToken = (string)$DB->getColumn("SELECT own_bot_token FROM pay_user_botconfig WHERE uid=?", [$uid]);
        if ($botToken) {
            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
            $post = http_build_query([
                'chat_id' => $tg,
                'text' => "🔔 <b>测试推送</b>\n━━━━━━━━━━━━━━━━━━\n📦 商品：下级推送测试\n💰 金额：<b>￥1.00</b>\n🔑 订单：SUB" . date('His') . "\n🕐 时间：" . date('Y-m-d H:i:s') . "\n━━━━━━━━━━━━━━━━━━\n💡 配置成功！",
                'parse_mode' => 'HTML',
            ]);
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$post, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10, CURLOPT_SSL_VERIFYPEER=>false]);
            $resp = curl_exec($ch); curl_close($ch);
            $j = json_decode($resp, true);
            $okTg = ($j['ok'] ?? false) === true;
            $msgs[] = $okTg ? "✅ TG 测试消息已发送" : "❌ TG 失败：" . ($j['description'] ?? '未知');
        } else {
            $msgs[] = "⚠️ 上级未配置 Bot Token，无法测 TG";
        }
    }

    // 2) 测邮件（用表单里填的 SMTP，或回退到上级的 SMTP）
    if ($email !== '') {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msgs[] = "❌ 邮箱格式错误";
        } else {
            if ($host && $user) {
                // 用表单填的 SMTP 试发
                $cfgTest = [
                    'host' => $host,
                    'port' => (int)($_POST['smtp_port'] ?? 465),
                    'user' => $user,
                    'pwd'  => $pwd,
                    'secure' => $secure,
                    'from_name' => '',
                ];
                $testHtml = '<div style="font-family:sans-serif;padding:20px;background:#f5f7fa;">'
                    . '<h2 style="color:#0d47a1;margin:0 0 16px;">✅ 下级推送测试</h2>'
                    . '<p>如果你收到这封邮件，说明邮件推送配置正常。</p>'
                    . '<p style="color:#a5b4c9;font-size:12px;margin-top:24px;">发送时间：' . date('Y-m-d H:i:s') . '</p>'
                    . '</div>';
                $r = @send_mail_with_smtp($email, '【测试】下级推送测试', $testHtml, $cfgTest);
                $okMail = ($r === true);
                $msgs[] = $okMail ? "✅ 测试邮件已发送到 $email" : "❌ 邮件失败：" . (is_string($r) ? $r : '未知');
            } else {
                // 用上级 SMTP 发
                $upid = (int)$DB->getColumn("SELECT upid FROM pay_user WHERE uid=?", [$uid]);
                require_once __DIR__ . '/../includes/lib/MailOrder.php';
                $testOrder = [
                    'trade_no' => 'SUBTEST' . date('YmdHis'),
                    'uid'      => $upid ?: $uid,
                    'name'     => '下级推送测试',
                    'money'    => '1.00',
                    'realmoney'=> '1.00',
                ];
                try {
                    $r = \lib\MailOrder::push($testOrder, $email);
                    $okMail = (bool)$r;
                    $msgs[] = $okMail ? "✅ 测试邮件已发送（用上级 SMTP）" : "❌ 邮件失败";
                } catch (Throwable $e) {
                    $msgs[] = "❌ 邮件异常：" . $e->getMessage();
                }
            }
        }
    }

    if (empty($msgs)) $msgs[] = "⚠️ 请至少填 TG 或邮箱";
    $code = ($okTg || $okMail) ? 0 : -1;
    exit(json_encode(['code'=>$code, 'msg'=>implode("\n", $msgs)]));
}
// ───── 提交 ─────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');
    
    $tg     = trim($_POST['tg'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $host   = trim($_POST['smtp_host'] ?? '');
    $user   = trim($_POST['smtp_user'] ?? '');
    $pwd    = trim($_POST['smtp_pwd'] ?? '');
    $secure = trim($_POST['smtp_secure'] ?? 'ssl');
    
    if ($tg !== '' && !is_numeric($tg)) exit(json_encode(['code'=>-1, 'msg'=>'TG ID 必须是数字']));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) exit(json_encode(['code'=>-1, 'msg'=>'邮箱格式错误']));
    if ($host !== '' && !in_array($secure, ['ssl','tls',''])) exit(json_encode(['code'=>-1, 'msg'=>'加密方式错误']));
    
    $DB->exec("UPDATE pay_user SET 
                 sub_push_tg=:tg, 
                 sub_push_email=:e,
                 sub_push_smtp_host=:h, 
                 sub_push_smtp_user=:u,
                 sub_push_smtp_pwd=:p, 
                 sub_push_smtp_secure=:s,
                 sub_push_status=0
               WHERE uid=:uid",
        [':tg'=>$tg, ':e'=>$email, ':h'=>$host, ':u'=>$user, ':p'=>$pwd, ':s'=>$secure ?: 'ssl', ':uid'=>$uid]);
    
    exit(json_encode(['code'=>1, 'msg'=>'已提交，等待上级审批']));
}

// ───── 渲染 ─────
$p = $DB->getRow("SELECT sub_push_tg, sub_push_email, sub_push_smtp_host, sub_push_smtp_user, sub_push_smtp_pwd, sub_push_smtp_secure, sub_push_status FROM pay_user WHERE uid=:u", [':u'=>$uid]);

$title = '推送资料';
include './head.php';
?>
<style>
.pp-card{background:#fff;border-radius:14px;padding:28px;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:20px;max-width:720px;margin-left:auto;margin-right:auto;}
.fm-row{margin-bottom:18px;}
.fm-row label{display:block;font-size:13px;color:#333;font-weight:600;margin-bottom:6px;}
.fm-row input,.fm-row select{width:100%;padding:11px 14px;border:1px solid #d9e2ec;border-radius:8px;font-size:14px;box-sizing:border-box;}
.fm-row .hint{font-size:12px;color:#888;margin-top:4px;line-height:1.6;}
.btn-save{background:linear-gradient(135deg,#1677ff,#0e4fb8);color:#fff;border:none;padding:14px 40px;border-radius:12px;font-size:16px;font-weight:700;cursor:pointer;width:100%;}
.btn-save:disabled{opacity:.6;cursor:not-allowed;}
.badge{display:inline-block;padding:6px 14px;border-radius:20px;font-size:13px;font-weight:700;}
.bd-0{background:#fff7e6;color:#d46b08;}
.bd-1{background:#d9f7be;color:#389e0d;}
.bd-2{background:#fff1f0;color:#cf1322;}
.tip{background:#f0f7ff;border-left:3px solid #1677ff;padding:12px 16px;border-radius:6px;margin-bottom:20px;font-size:13px;line-height:1.8;}
</style>

<div id="content" class="app-content" role="main">
<div class="app-content-body">
<div class="bg-light lter b-b wrapper-md">
  <h1 class="m-n font-thin h3"><i class="fa fa-paper-plane"></i> 我的推送资料</h1>
</div>
<div class="wrapper-md">

  <div class="pp-card">
    <?php
    $st = (int)$p['sub_push_status'];
    if ($st === 0) echo '<div style="text-align:center;margin-bottom:20px;"><span class="badge bd-0">⏳ 待上级审批</span></div>';
    elseif ($st === 1) echo '<div style="text-align:center;margin-bottom:20px;"><span class="badge bd-1">✅ 已通过（正在生效）</span></div>';
    else echo '<div style="text-align:center;margin-bottom:20px;"><span class="badge bd-2">❌ 已被拒绝，请修改后重新提交</span></div>';
    ?>

    <div class="tip">
      · 填写后提交给上级审批，<b>审批通过后</b>订单通知会推送到你这里<br>
      · 请先点击下方【官方机器人】发送 <code>/id</code> 获取你的 Telegram Chat ID<br>
      · 订单通知可同时推送到 <b>TG</b> 和 <b>邮箱</b><br>
      · 如果是发给你的专属邮箱，系统会自动使用上级的发件服务，无需填写发件服务器
    </div>

    <form id="ppForm" onsubmit="return false;">
      <div class="fm-row">
        <label>📱 Telegram Chat ID（你的 TG 数字 ID）</label>
        <input type="text" id="tg" value="<?php echo htmlspecialchars($p['sub_push_tg'] ?? '');?>" placeholder="如 7811967320">
        <div class="hint" style="margin-top: 10px; line-height: 1.6;">
    获取方式：点击下方按钮打开我们的官方机器人，发送 <code>/id</code> 指令，机器人会自动回复你的数字 ID。<br>
    <div style="display: flex; gap: 10px; margin-top: 10px;">
        <a href="https://t.me/xindawangluo1_bot" target="_blank" class="btn btn-xs" style="background:#2AABEE; color:#fff; padding: 6px 12px; border-radius: 6px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;"><i class="fa fa-telegram"></i> 官方机器人</a>
        <a href="https://t.me/xindapay1H5_bot" target="_blank" class="btn btn-xs" style="background:#1677ff; color:#fff; padding: 6px 12px; border-radius: 6px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;"><i class="fa fa-headphones"></i> 备用客服</a>
    </div>
</div>
      </div>

      <div class="fm-row">
        <label>📧 接收邮箱</label>
        <input type="email" id="email" value="<?php echo htmlspecialchars($p['sub_push_email'] ?? '');?>" placeholder="如 xxx@qq.com">
      </div>

<?php if((int)$userrow['sub_level'] < 2){ ?>
      <div class="fm-row">
        <label>📮 邮件 SMTP 服务器（用于发信）</label>
        <input type="text" id="smtp_host" value="<?php echo htmlspecialchars($p['sub_push_smtp_host'] ?? '');?>" placeholder="如 smtp.qq.com">
      </div>

      <div class="fm-row">
        <label>SMTP 用户名</label>
        <input type="text" id="smtp_user" value="<?php echo htmlspecialchars($p['sub_push_smtp_user'] ?? '');?>" placeholder="如 xxx@qq.com">
      </div>

      <div class="fm-row">
        <label>SMTP 密码 / 授权码</label>
        <input type="text" id="smtp_pwd" value="<?php echo htmlspecialchars($p['sub_push_smtp_pwd'] ?? '');?>" placeholder="邮箱授权码，不是登录密码">
      </div>

      <div class="fm-row">
        <label>加密方式</label>
        <select id="smtp_secure">
          <option value="ssl" <?php echo ($p['sub_push_smtp_secure'] ?? 'ssl')==='ssl'?'selected':'';?>>SSL（推荐，端口 465）</option>
          <option value="tls" <?php echo ($p['sub_push_smtp_secure'] ?? '')==='tls'?'selected':'';?>>TLS（端口 587）</option>
        </select>
      </div>

      <button class="btn-save" style="background:linear-gradient(135deg,#f59e0b,#d97706);margin-bottom:12px;" onclick="doTest()">🧪 测试推送（发一封测试邮件到你的邮箱）</button>
<?php } ?>
      <button class="btn-save" id="btnSave" onclick="doSubmit()">📤 提交给上级审批</button>
    </form>
  </div>

</div>
</div>
</div>

<script>
function doTest() {
  var fd = new FormData();
  fd.append('tg', document.getElementById('tg').value.trim());
  fd.append('email', document.getElementById('email').value.trim());
  fd.append('smtp_host', (document.getElementById('smtp_host')||{value:''}).value.trim());
  fd.append('smtp_port', (document.getElementById('smtp_port')||{value:'465'}).value);
  fd.append('smtp_user', (document.getElementById('smtp_user')||{value:''}).value.trim());
  fd.append('smtp_pwd', (document.getElementById('smtp_pwd')||{value:''}).value);
  fd.append('smtp_secure', (document.getElementById('smtp_secure')||{value:'ssl'}).value);
  var btn = event.target;
  btn.disabled = true; btn.textContent = '⏳ 发送中...';
  fetch('./sub_push.php?act=test', {method:'POST', body:fd})
    .then(r=>r.json())
    .then(d=>{ alert(d.msg); btn.disabled = false; btn.textContent = '🧪 测试推送'; })
    .catch(e=>{ alert('网络错误：'+e); btn.disabled = false; btn.textContent = '🧪 测试推送'; });
}

function doSubmit() {
  var btn = document.getElementById('btnSave');
  var data = {
    tg:        document.getElementById('tg').value.trim(),
    email:     document.getElementById('email').value.trim(),
    smtp_host: document.getElementById('smtp_host').value.trim(),
    smtp_user: document.getElementById('smtp_user').value.trim(),
    smtp_pwd:  document.getElementById('smtp_pwd').value.trim(),
    smtp_secure: document.getElementById('smtp_secure').value
  };
  btn.disabled = true; btn.textContent = '⏳ 提交中...';
  var fd = new FormData();
  for (var k in data) fd.append(k, data[k]);
  fetch('./sub_push.php', {method:'POST', body:fd})
    .then(r => r.json())
    .then(d => {
      alert(d.msg);
      btn.disabled = false; btn.textContent = '📤 提交给上级审批';
      if (d.code === 1) location.reload();
    })
    .catch(e => {
      alert('网络错误：' + e.message);
      btn.disabled = false; btn.textContent = '📤 提交给上级审批';
    });
}
</script>
<?php include './foot.php'; ?>