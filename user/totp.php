<?php
include("../includes/common.php");
if($islogin2==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
$title = '谷歌验证器';
$uid = $userrow['uid'];
include './head.php';

$my = $_GET['my'] ?? '';

// 生成新密钥
if($my == 'generate') {
    $totp = \lib\TOTP::create();
    $totp->setLabel($userrow['username'] ?: 'user'.$uid);
    $totp->setIssuer($conf['sitename']);
    exit(json_encode([
        'code' => 0,
        'data' => [
            'secret' => $totp->getSecret(),
            'qrcode' => $totp->getProvisioningUri()
        ]
    ]));
}

// 开启
if($my == 'enable') {
    $secret = trim($_POST['secret'] ?? '');
    $code = trim($_POST['code'] ?? '');
    if(!$secret || !$code) exit(json_encode(['code'=>-1,'msg'=>'参数不全']));
    $totp = \lib\TOTP::create($secret);
    if(!$totp->verify($code)) exit(json_encode(['code'=>-1,'msg'=>'验证码错误']));

    $DB->exec("UPDATE pay_user SET totp_secret=:s, totp_open=1 WHERE uid=:u", [":s"=>$secret, ":u"=>$uid]);
    exit(json_encode(['code'=>0,'msg'=>'谷歌验证器已开启']));
}

// 关闭
if($my == 'disable') {
    $code = trim($_POST['code'] ?? '');
    if(!$userrow['totp_secret']) exit(json_encode(['code'=>-1,'msg'=>'未开启']));
    $totp = \lib\TOTP::create($userrow['totp_secret']);
    if(!$totp->verify($code)) exit(json_encode(['code'=>-1,'msg'=>'验证码错误']));

    $DB->exec("UPDATE pay_user SET totp_secret='', totp_open=0 WHERE uid=:u", [":u"=>$uid]);
    exit(json_encode(['code'=>0,'msg'=>'已关闭谷歌验证器']));
}

// 重新读
$userrow = $DB->getRow("SELECT * FROM pay_user WHERE uid=:u", [":u"=>$uid]);
$isOpen = $userrow['totp_open'] == 1;
?>
<div id="content" class="app-content" role="main">
<div class="app-content-body">
<div class="bg-light lter b-b wrapper-md hidden-print">
  <h1 class="m-n font-thin h3">谷歌验证器</h1>
</div>
<div class="wrapper-md control">

<div class="panel panel-info">
  <div class="panel-heading font-bold"><i class="fa fa-shield"></i> 两步验证（2FA）</div>
  <div class="panel-body">
    <p>绑定谷歌验证器后，登录时需输入 6 位动态验证码，账号更安全。</p>
    <p style="margin-top:10px;"><b>当前状态：</b>
      <?php if($isOpen): ?>
        <span class="label label-success">已开启</span>
      <?php else: ?>
        <span class="label label-default">未开启</span>
      <?php endif; ?>
    </p>
  </div>
</div>

<?php if(!$isOpen): ?>
<div class="panel panel-default">
  <div class="panel-heading font-bold">开启步骤</div>
  <div class="panel-body">
    <div id="step1">
      <p><b>第 1 步：下载谷歌验证器 App</b></p>
      <p style="font-size:13px;color:#666;">Android/iOS 搜 "Google Authenticator" 或 "微软验证器"</p>
      <button class="btn btn-primary" onclick="genSecret()"><i class="fa fa-qrcode"></i> 生成密钥</button>
    </div>
    <div id="step2" style="display:none;">
      <p style="margin-top:20px;"><b>第 2 步：扫描二维码</b></p>
      <div style="text-align:center;padding:20px;background:#f8f9fc;border-radius:8px;">
        <div id="qrCode"></div>
        <p style="margin-top:15px;">密钥：<code id="secretText" style="font-size:16px;"></code></p>
      </div>
      <p style="margin-top:20px;"><b>第 3 步：输入验证码</b></p>
      <div style="max-width:300px;">
        <input id="code" class="form-control" placeholder="6 位验证码" maxlength="6" style="font-size:20px;text-align:center;letter-spacing:8px;">
        <button class="btn btn-primary" style="margin-top:10px;width:100%;" onclick="enableTotp()">
          <i class="fa fa-check"></i> 确认开启
        </button>
      </div>
    </div>
  </div>
</div>
<?php else: ?>
<div class="panel panel-default">
  <div class="panel-heading font-bold">关闭验证器</div>
  <div class="panel-body">
    <div style="max-width:300px;">
      <input id="code" class="form-control" placeholder="6 位验证码" maxlength="6" style="font-size:20px;text-align:center;letter-spacing:8px;">
      <button class="btn btn-danger" style="margin-top:10px;width:100%;" onclick="disableTotp()">
        <i class="fa fa-times"></i> 关闭谷歌验证器
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

</div>
</div>

<script>
let currentSecret = '';
function genSecret() {
  fetch('?my=generate').then(r=>r.json()).then(d=>{
    if(d.code !== 0) { alert('生成失败'); return; }
    currentSecret = d.data.secret;
    document.getElementById('secretText').textContent = currentSecret;
    document.getElementById('qrCode').innerHTML = '<img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='+encodeURIComponent(d.data.qrcode)+'">';
    document.getElementById('step1').style.display = 'none';
    document.getElementById('step2').style.display = 'block';
  });
}
function enableTotp() {
  const code = document.getElementById('code').value.trim();
  if(code.length !== 6) { alert('请输入 6 位验证码'); return; }
  const fd = new FormData();
  fd.append('secret', currentSecret);
  fd.append('code', code);
  fetch('?my=enable', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
    alert(d.msg); if(d.code === 0) location.reload();
  });
}
function disableTotp() {
  const code = document.getElementById('code').value.trim();
  if(code.length !== 6) { alert('请输入 6 位验证码'); return; }
  const fd = new FormData();
  fd.append('code', code);
  fetch('?my=disable', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
    alert(d.msg); if(d.code === 0) location.reload();
  });
}
</script>
<?php include './foot.php'; ?>
