<?php
include("../includes/common.php");
if($islogin2 != 1) exit("<script>location.href='./login.php';</script>");

$uid = $userrow['uid'];

// ===== 处理 TOTP 生成 =====
if(($_GET['totp'] ?? '') === 'generate') {
    header('Content-Type: application/json; charset=utf-8');
    $t = \lib\TOTP::create();
    $t->setLabel($userrow['username'] ?: 'user'.$uid);
    $t->setIssuer($conf['sitename']);
    exit(json_encode(['code'=>0, 'data'=>['secret'=>$t->getSecret(), 'qrcode'=>$t->getProvisioningUri()]]));
}

// ===== 最终提交 =====
if(($_POST['act'] ?? '') === 'bind') {
    header('Content-Type: application/json; charset=utf-8');
    $secret = trim($_POST['secret'] ?? '');
    $code   = trim($_POST['code'] ?? '');
    $q1 = intval($_POST['q1'] ?? 0); $a1 = trim($_POST['a1'] ?? '');
    $q2 = intval($_POST['q2'] ?? 0); $a2 = trim($_POST['a2'] ?? '');
    $q3 = intval($_POST['q3'] ?? 0); $a3 = trim($_POST['a3'] ?? '');

    if(!$secret || !$code) exit(json_encode(['code'=>-1,'msg'=>'请先扫码获取密钥']));
    if(!$q1 || !$q2 || !$q3) exit(json_encode(['code'=>-1,'msg'=>'请选择 3 个密保问题']));
    if(!$a1 || !$a2 || !$a3) exit(json_encode(['code'=>-1,'msg'=>'请填写全部密保答案']));
    if($q1 == $q2 || $q1 == $q3 || $q2 == $q3) exit(json_encode(['code'=>-1,'msg'=>'密保问题不能重复']));
    if(mb_strlen($a1) < 2 || mb_strlen($a2) < 2 || mb_strlen($a3) < 2) exit(json_encode(['code'=>-1,'msg'=>'密保答案至少 2 个字']));

    try {
        $t = \lib\TOTP::create($secret);
        if(!$t->verify($code)) exit(json_encode(['code'=>-1,'msg'=>'动态码错误']));
    } catch (\Exception $e) {
        exit(json_encode(['code'=>-1,'msg'=>'验证异常：'.$e->getMessage()]));
    }

    $qa = json_encode([
        ['q'=>$q1, 'a'=>md5(mb_strtolower($a1))],
        ['q'=>$q2, 'a'=>md5(mb_strtolower($a2))],
        ['q'=>$q3, 'a'=>md5(mb_strtolower($a3))]
    ], JSON_UNESCAPED_UNICODE);

    $DB->exec("UPDATE pay_user SET totp_secret=:s, totp_open=1, security_qa=:q, bind_required=0, login_count=0 WHERE uid=:u",
        [':s'=>$secret, ':q'=>$qa, ':u'=>$uid]);

    exit(json_encode(['code'=>0, 'msg'=>'绑定成功！正在跳转...', 'url'=>'./']));
}

$title = '绑定二次验证';
include './head.php';

$QUESTIONS = [
    1 => '你母亲的姓名是？',
    2 => '你出生的城市是？',
    3 => '你第一所小学的名字是？',
    4 => '你最喜欢的宠物名字是？',
    5 => '你父亲的生日是？（YYYY-MM-DD）',
    6 => '你的第一辆车是什么品牌？',
    7 => '你最好的朋友叫什么？',
    8 => '你最喜欢的老师姓什么？',
    9 => '你的童年绰号是什么？',
];
?>
<div id="content" class="app-content" role="main">
<div class="app-content-body">
<div class="bg-light lter b-b wrapper-md">
  <h1 class="m-n font-thin h3">🔐 绑定二次验证</h1>
</div>
<div class="wrapper-md">
  <div class="alert alert-warning">
    <b>⚠️ 系统检测到您已登录 3 次，需要绑定二次验证才能继续使用。</b><br>
    请完成下方 2 步操作（约需 3 分钟）。
  </div>

  <div class="panel panel-info">
    <div class="panel-heading font-bold"><i class="fa fa-qrcode"></i> 第 1 步：绑定谷歌/微软验证器</div>
    <div class="panel-body">
      <p style="color:#666;font-size:13px;">手机 App Store / 应用商店搜 "Google Authenticator" 或 "微软验证器" 安装</p>
      <div id="step1"><button class="btn btn-primary" onclick="genSecret()"><i class="fa fa-key"></i> 生成密钥</button></div>
      <div id="step2" style="display:none;">
        <div style="text-align:center;padding:20px;background:#f8f9fc;border-radius:8px;margin-top:15px;">
          <div id="qrCode"></div>
          <p style="margin-top:15px;">手动输入密钥：<code id="secretText" style="font-size:16px;"></code></p>
        </div>
        <div style="max-width:320px;margin:20px auto 0;">
          <input id="totp_code" class="form-control" placeholder="输入 6 位动态码" maxlength="6" style="font-size:20px;text-align:center;letter-spacing:8px;">
        </div>
      </div>
    </div>
  </div>

  <div class="panel panel-warning">
    <div class="panel-heading font-bold"><i class="fa fa-shield"></i> 第 2 步：设置密保问题（忘记密码时使用）</div>
    <div class="panel-body">
      <?php for($i=1;$i<=3;$i++): ?>
      <div class="form-group">
        <label>密保问题 <?php echo $i; ?></label>
        <select class="form-control" id="q<?php echo $i; ?>">
          <option value="0">-- 请选择 --</option>
          <?php foreach($QUESTIONS as $n=>$q): ?>
          <option value="<?php echo $n; ?>"><?php echo $q; ?></option>
          <?php endforeach; ?>
        </select>
        <input class="form-control" id="a<?php echo $i; ?>" placeholder="答案（至少 2 个字，不分大小写）" style="margin-top:8px;">
      </div>
      <?php endfor; ?>
    </div>
  </div>

  <button class="btn btn-success btn-lg btn-block" onclick="submitBind()">
    <i class="fa fa-check"></i> 完成绑定并进入后台
  </button>
</div>
</div>
</div>

<script>
var currentSecret = '';
function genSecret() {
  fetch('?totp=generate').then(function(r){return r.json()}).then(function(d){
    if(d.code !== 0) { alert('生成失败'); return; }
    currentSecret = d.data.secret;
    document.getElementById('secretText').textContent = currentSecret;
    document.getElementById('qrCode').innerHTML = '<img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='+encodeURIComponent(d.data.qrcode)+'">';
    document.getElementById('step1').style.display = 'none';
    document.getElementById('step2').style.display = 'block';
  });
}
function submitBind() {
  if(!currentSecret) { alert('请先扫码生成密钥'); return; }
  var code = document.getElementById('totp_code').value.trim();
  if(code.length !== 6) { alert('请输入 6 位动态码'); return; }

  var fd = new FormData();
  fd.append('act', 'bind');
  fd.append('secret', currentSecret);
  fd.append('code', code);
  for(var i=1;i<=3;i++){
    fd.append('q'+i, document.getElementById('q'+i).value);
    fd.append('a'+i, document.getElementById('a'+i).value.trim());
  }
  fetch('', {method:'POST', body:fd}).then(function(r){return r.json()}).then(function(d){
    if(d.code === 0){
      alert(d.msg);
      location.href = d.url;
    } else {
      alert(d.msg);
    }
  });
}
</script>
<?php include './foot.php'; ?>
