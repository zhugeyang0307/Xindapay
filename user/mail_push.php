<?php
include("../includes/common.php");
require_once(__DIR__ . '/../telegram/lib/DB.php');
if($islogin2==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
$title = '邮件推送';
$uid = $userrow['uid'];
$my = $_GET['my'] ?? '';

// ===== 读取现有配置 =====
$cfg = DB::one("SELECT * FROM pay_user_botconfig WHERE uid=?", [$uid]) ?: [];

// ===== 保存 =====
if($my == 'save') {
    $enabled = intval($_POST['mail_enabled'] ?? 0);
    $mail_to = trim($_POST['mail_to'] ?? '');

    if ($enabled === 1 && $mail_to === '') {
        exit(json_encode(['code'=>-1, 'msg'=>'启用邮件推送时必须填写收件邮箱']));
    }
    if ($mail_to !== '') {
        $emails = array_filter(array_map('trim', preg_split('/[,;，；\s]+/', $mail_to)));
        foreach ($emails as $em) {
            if (!filter_var($em, FILTER_VALIDATE_EMAIL)) {
                exit(json_encode(['code'=>-1, 'msg'=>"邮箱格式错误：{$em}"]));
            }
        }
        $mail_to = implode(',', $emails);
    }

    $smtp_host = trim($_POST['mail_smtp_host'] ?? '');
    $smtp_port = intval($_POST['mail_smtp_port'] ?? 465);
    $smtp_user = trim($_POST['mail_smtp_user'] ?? '');
    $smtp_pwd  = trim($_POST['mail_smtp_pwd'] ?? '');
    $smtp_sec  = in_array($_POST['mail_smtp_secure'] ?? '', ['ssl','tls','none'], true) ? $_POST['mail_smtp_secure'] : 'ssl';
    $from_name = trim($_POST['mail_from_name'] ?? '');

    // 密码：留空表示不修改
    if ($smtp_pwd === '') {
        $smtp_pwd_saved = $cfg['mail_smtp_pwd'] ?? null;
    } else {
        $smtp_pwd_saved = authcode($smtp_pwd, 'ENCODE', SYS_KEY);
    }

    $exists = DB::one("SELECT uid FROM pay_user_botconfig WHERE uid=?", [$uid]);
    if (!$exists) {
        DB::exec("INSERT INTO pay_user_botconfig (uid, addtime) VALUES (?, NOW())", [$uid]);
    }
    DB::exec(
        "UPDATE pay_user_botconfig
         SET mail_enabled=?, mail_to=?,
             mail_smtp_host=?, mail_smtp_port=?, mail_smtp_user=?, mail_smtp_pwd=?,
             mail_smtp_secure=?, mail_from_name=?
         WHERE uid=?",
        [
            $enabled, $mail_to ?: null,
            $smtp_host ?: null, $smtp_port ?: 465, $smtp_user ?: null, $smtp_pwd_saved,
            $smtp_sec, $from_name ?: null,
            $uid
        ]
    );
    exit(json_encode(['code'=>0, 'msg'=>'配置已保存']));
}

// ===== 测试发送 =====
if($my == 'test') {
    $mail_to   = trim($_POST['mail_to'] ?? '');
    $smtp_host = trim($_POST['mail_smtp_host'] ?? '');
    $smtp_port = intval($_POST['mail_smtp_port'] ?? 465);
    $smtp_user = trim($_POST['mail_smtp_user'] ?? '');
    $smtp_pwd  = trim($_POST['mail_smtp_pwd'] ?? '');
    $smtp_sec  = $_POST['mail_smtp_secure'] ?? 'ssl';
    $from_name = trim($_POST['mail_from_name'] ?? '');

    if (!$mail_to) exit(json_encode(['code'=>-1, 'msg'=>'请先填写收件邮箱']));
    if (!$smtp_host || !$smtp_user) exit(json_encode(['code'=>-1, 'msg'=>'请先填写 SMTP 服务器和账号']));

    // 密码留空 → 用库里已存的
    if ($smtp_pwd === '') {
        $stored = $cfg['mail_smtp_pwd'] ?? '';
        if (!$stored) exit(json_encode(['code'=>-1, 'msg'=>'请填写应用密码']));
        $smtp_pwd = authcode($stored, 'DECODE', SYS_KEY) ?: $stored;
    }

    require_once(__DIR__ . '/../includes/functions.php');
    $testHtml = '<div style="font-family:sans-serif;padding:20px;background:#f5f7fa;">'
              . '<div style="max-width:520px;margin:0 auto;background:#fff;border-radius:12px;padding:30px;">'
              . '<h2 style="color:#0d47a1;margin:0 0 16px;">✅ 邮件配置测试</h2>'
              . '<p style="color:#5a6a7e;font-size:14px;line-height:1.8;">'
              . '你收到了这封邮件，说明邮件推送配置正确。<br>'
              . '订单到达时，你将收到带【✅确认到账】按钮的正式邮件。</p>'
              . '<p style="color:#a5b4c9;font-size:12px;margin-top:24px;">发送时间：' . date('Y-m-d H:i:s') . '</p>'
              . '</div></div>';

    $cfgTest = [
        'host'      => $smtp_host,
        'port'      => $smtp_port,
        'user'      => $smtp_user,
        'pwd'       => $smtp_pwd,
        'secure'    => $smtp_sec,
        'from_name' => $from_name ?: '订单通知',
    ];
    $r = send_mail_with_smtp($mail_to, '【测试】邮件推送配置成功', $testHtml, $cfgTest);
    if ($r === true) exit(json_encode(['code'=>0, 'msg'=>'✅ 测试邮件已发送到 ' . $mail_to]));
    exit(json_encode(['code'=>-1, 'msg'=>'❌ 发送失败：' . (is_string($r) ? $r : '未知错误')]));
}

// ===== 页面渲染（非接口请求）=====
include './head.php';

// ===== 读取当前配置用于展示 =====
$hasPwd = !empty($cfg['mail_smtp_pwd']);
?>
<div id="content" class="app-content" role="main">
<div class="app-content-body">

<div class="bg-light lter b-b wrapper-md hidden-print">

<div class="alert alert-info" style="margin-bottom: 20px; border-left: 4px solid #3b82f6; background: #eff6ff; color: #1e40af; padding: 15px; border-radius: 8px;">
    <h4 style="margin-top:0;"><i class="fa fa-envelope"></i> 邮件推送配置说明</h4>
    <p>建议使用 <b>QQ邮箱 / 163邮箱</b> 发送通知，防拦截率更高。请先在邮箱设置中开启 <b>SMTP 服务</b> 并获取 <b>授权码</b>（不是登录密码）。</p>
    <p style="margin-bottom:0;">点击下方的预设按钮可以一键填充服务器地址和端口，填好账号和授权码后，点击底部的保存即可。</p>
</div>

  <h1 class="m-n font-thin h3"><i class="fa fa-envelope" style="color:#0d47a1;"></i> 邮件推送</h1>
</div>

<div class="wrapper-md control">

  <div class="alert alert-info" style="font-size:13px;line-height:1.9;">
    <b>作用：</b>订单到达时，系统用<b>你的发件邮箱</b>发送订单通知到<b>你指定的收件邮箱</b>，邮件里带【✅确认到账】按钮。<br>
    <b>优点：</b>不用装 APP、不用 TG 账号、全球通用、可存档、可转发。<br>
    <b>注意：</b>邮件有 1-2 分钟延迟，<b>急单建议同时开启 TG 推送</b>。<br>
    <b>发件方：</b>用你自己邮箱的 SMTP 发送（需填写应用密码，不是登录密码）。
  </div>

  <div class="alert alert-warning" style="font-size:13px;line-height:1.9;border-left:4px solid #ff9500;background:#fff7e6;color:#8a5a00;">
    <h4 style="margin:0 0 8px;font-weight:800;color:#8a5a00;">⚠️ 邮件进垃圾箱？先看这里</h4>
    <b>最推荐的做法：把「发件邮箱」和「收件邮箱」填成同一个。</b>（自己给自己发，几乎不会进垃圾箱）<br>
    如果必须用 A 邮箱发给 B 邮箱，请按以下步骤设置白名单：<br>
    <ol style="margin:6px 0 0 18px;padding:0;">
      <li>首次发测试邮件后，<b>去 B 邮箱的垃圾邮件箱找</b>，把邮件标记为「<b>这不是垃圾邮件</b>」</li>
      <li>把 A 邮箱地址添加为 B 邮箱的「<b>联系人 / 白名单</b>」</li>
      <li>在 B 邮箱设置里创建过滤规则：「发件人 = A 邮箱 → 永不进垃圾箱」</li>
      <li>重复测试几次，B 邮箱就会记住 A 邮箱是可信发件人</li>
    </ol>
    <div style="margin-top:8px;color:#a35c00;font-size:12.5px;">
      💡 <b>两个邮箱互加好友可以大幅降低进垃圾箱的概率：</b><br>
      A 邮箱加 B 为联系人 + B 邮箱加 A 为联系人，互发几封正常邮件后，双方就互相"认识"了。
    </div>
  </div>

  <!-- 邮件推送开关 + 收件邮箱 -->
  <div class="panel panel-default">
    <div class="panel-heading font-bold"><i class="fa fa-toggle-on"></i> 基础配置</div>
    <div class="panel-body">
      <div class="form-group">
        <label><input type="checkbox" id="mail_enabled" <?php echo intval($cfg['mail_enabled'] ?? 0)===1 ? 'checked' : ''; ?>> 启用邮件推送</label>
      </div>
      <div class="form-group">
        <label>收件邮箱（订单通知发到这里）</label>
        <input type="text" id="mail_to" class="form-control" placeholder="如 boss@company.com" value="<?php echo htmlspecialchars($cfg['mail_to'] ?? ''); ?>">
        <div class="alert alert-warning" style="padding:8px 12px;font-size:12px;margin-top:8px;border-left:3px solid #ff9500;background:#fff7e6;color:#8a5a00;border-radius:4px;">
          ⚠️ <b>建议只填写 1 个邮箱。</b>多个邮箱时，任一邮箱点【确认到账】后，其余邮箱的链接会立即失效。<br>
          💡 <b>最佳实践：收件邮箱填成和发件邮箱一样</b>（自己给自己发，不易进垃圾箱）。<br>
          📌 <b>首次测试后请检查垃圾邮件箱</b>，找到邮件后标记为「非垃圾邮件」，并把发件人加为联系人。
        </div>
      </div>
    </div>
  </div>

  <!-- 发件邮箱 SMTP 配置 -->
  <div class="panel panel-info">
    <div class="panel-heading font-bold">
      <i class="fa fa-paper-plane"></i> 发件邮箱 SMTP 配置
      <small style="font-weight:normal;">（用你自己的邮箱发件，需要邮箱提供商的应用密码）</small>
    </div>
    <div class="panel-body">

      <div class="alert alert-warning" style="font-size:12.5px;line-height:1.9;border-left:3px solid #ff9500;background:#fff7e6;color:#8a5a00;">
        <b>🧪 配置完成后的正确验证流程：</b>
        <ol style="margin:6px 0 0 18px;padding:0;">
          <li>保存配置 → 点「发送测试邮件」</li>
          <li>去收件邮箱 <b>同时检查「收件箱」和「垃圾邮件箱」</b></li>
          <li>如果进了垃圾箱 → 打开邮件点「<b>标记为非垃圾邮件</b>」+ 把发件人加为联系人</li>
          <li>重复点 2-3 次「发送测试邮件」，直到测试邮件直接进收件箱</li>
          <li>确认稳定后，再去实际订单里使用</li>
        </ol>
      </div>

      <div class="alert alert-info" style="font-size:12.5px;line-height:1.9;">
        <b>常用邮箱快速填充：</b>
        <button type="button" class="btn btn-xs btn-default" onclick="applyPreset('gmail')">Gmail</button>
        <button type="button" class="btn btn-xs btn-default" onclick="applyPreset('icloud')">iCloud</button>
        <button type="button" class="btn btn-xs btn-default" onclick="applyPreset('qq')">QQ邮箱</button>
        <button type="button" class="btn btn-xs btn-default" onclick="applyPreset('163')">163邮箱</button>
        <button type="button" class="btn btn-xs btn-default" onclick="applyPreset('outlook')">Outlook</button>
        <br>
        <span style="color:#7a8699;">点击后会填入服务器/端口/加密方式，你只需填写邮箱地址和应用密码。</span>
      </div>

      <div class="form-group">
        <label>SMTP 服务器</label>
        <input type="text" id="mail_smtp_host" class="form-control" placeholder="如 smtp.gmail.com" value="<?php echo htmlspecialchars($cfg['mail_smtp_host'] ?? ''); ?>">
      </div>
      <div class="form-group">
        <label>端口</label>
        <input type="text" id="mail_smtp_port" class="form-control" placeholder="465" value="<?php echo htmlspecialchars($cfg['mail_smtp_port'] ?? '465'); ?>">
      </div>
      <div class="form-group">
        <label>加密方式</label>
        <select id="mail_smtp_secure" class="form-control">
          <option value="ssl" <?php echo ($cfg['mail_smtp_secure'] ?? 'ssl')==='ssl'?'selected':''; ?>>SSL（推荐，465 端口）</option>
          <option value="tls" <?php echo ($cfg['mail_smtp_secure'] ?? '')==='tls'?'selected':''; ?>>TLS（587 端口）</option>
          <option value="none" <?php echo ($cfg['mail_smtp_secure'] ?? '')==='none'?'selected':''; ?>>无（25 端口，不推荐）</option>
        </select>
      </div>
      <div class="form-group">
        <label>发件邮箱地址（也是 SMTP 用户名）</label>
        <input type="text" id="mail_smtp_user" class="form-control" placeholder="如 yourname@gmail.com" value="<?php echo htmlspecialchars($cfg['mail_smtp_user'] ?? ''); ?>">
      </div>
      <div class="form-group">
        <label>应用密码 / 授权码</label>
        <input type="password" id="mail_smtp_pwd" class="form-control" placeholder="<?php echo $hasPwd ? '已设置，留空则不修改' : '请填写邮箱提供商生成的应用密码'; ?>">
        <div style="background:#fff7e6;border-left:3px solid #ff9500;padding:10px 14px;margin-top:8px;font-size:12.5px;color:#8a5a00;border-radius:4px;line-height:1.9;">
          ⚠️ <b>不是邮箱登录密码</b>，需要在邮箱后台生成"应用专用密码 / 授权码"。<br>
          📌 点击下方按钮直达对应邮箱的应用密码申请页：
          <div style="margin-top:8px;">
            <a href="https://myaccount.google.com/apppasswords" target="_blank" class="btn btn-xs btn-primary" style="margin:2px 4px 2px 0;">
              <i class="fa fa-external-link"></i> Gmail 应用密码
            </a>
            <a href="https://appleid.apple.com/account/manage" target="_blank" class="btn btn-xs btn-primary" style="margin:2px 4px 2px 0;">
              <i class="fa fa-external-link"></i> iCloud App 专用密码
            </a>
            <a href="https://service.mail.qq.com/detail/0/75" target="_blank" class="btn btn-xs btn-primary" style="margin:2px 4px 2px 0;">
              <i class="fa fa-external-link"></i> QQ 邮箱授权码
            </a>
            <a href="https://help.mail.163.com/faqDetail.do?code=d7a5dc8471cd0c0e8b4b8f4f8e49998b374173cfe9171305fa1ce630d7feee" target="_blank" class="btn btn-xs btn-primary" style="margin:2px 4px 2px 0;">
              <i class="fa fa-external-link"></i> 163 邮箱授权码
            </a>
            <a href="https://account.microsoft.com/security" target="_blank" class="btn btn-xs btn-primary" style="margin:2px 4px 2px 0;">
              <i class="fa fa-external-link"></i> Outlook 应用密码
            </a>
          </div>
          <div style="margin-top:8px;color:#7a8699;font-size:12px;">
            💡 <b>操作指引：</b>点按钮 → 登录你的邮箱 → 按页面提示生成"应用专用密码/授权码"（16 位左右）→ 复制粘贴到上方输入框。
          </div>
        </div>
      </div>
      <div class="form-group">
        <label>发件人显示名（选填）</label>
        <input type="text" id="mail_from_name" class="form-control" placeholder="如 XX店铺订单通知" value="<?php echo htmlspecialchars($cfg['mail_from_name'] ?? ''); ?>">
      </div>
    </div>
  </div>

  <div class="panel panel-default">
    <div class="panel-body">
      <button class="btn btn-primary" onclick="saveAll()"><i class="fa fa-save"></i> 保存配置</button>
      <button class="btn btn-success" onclick="testSend()"><i class="fa fa-paper-plane"></i> 发送测试邮件</button>
    </div>
  </div>

</div>
</div>
</div>

<script>
function applyPreset(name) {
  var p = {
    gmail:   {host:'smtp.gmail.com',       port:465, secure:'ssl'},
    icloud:  {host:'smtp.mail.me.com',     port:587, secure:'tls'},
    qq:      {host:'smtp.qq.com',          port:465, secure:'ssl'},
    '163':   {host:'smtp.163.com',         port:465, secure:'ssl'},
    outlook: {host:'smtp.office365.com',   port:587, secure:'tls'}
  }[name];
  if (!p) return;
  document.getElementById('mail_smtp_host').value = p.host;
  document.getElementById('mail_smtp_port').value = p.port;
  document.getElementById('mail_smtp_secure').value = p.secure;
}

function collect() {
  var fd = new FormData();
  fd.append('mail_enabled', document.getElementById('mail_enabled').checked ? 1 : 0);
  fd.append('mail_to', document.getElementById('mail_to').value.trim());
  fd.append('mail_smtp_host', document.getElementById('mail_smtp_host').value.trim());
  fd.append('mail_smtp_port', document.getElementById('mail_smtp_port').value.trim());
  fd.append('mail_smtp_user', document.getElementById('mail_smtp_user').value.trim());
  fd.append('mail_smtp_pwd', document.getElementById('mail_smtp_pwd').value);
  fd.append('mail_smtp_secure', document.getElementById('mail_smtp_secure').value);
  fd.append('mail_from_name', document.getElementById('mail_from_name').value.trim());
  return fd;
}

function saveAll() {
  fetch('?my=save', {method:'POST', body:collect()}).then(r=>r.json()).then(d=>{
    alert(d.msg);
    if (d.code === 0) location.reload();
  });
}

function testSend() {
  fetch('?my=test', {method:'POST', body:collect()}).then(r=>r.json()).then(d=>{
    alert(d.msg);
  });
}
</script>

<?php include './foot.php'; ?>
