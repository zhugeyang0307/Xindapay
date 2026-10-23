<?php
// 判断是否为专属链接
$__is_pid = (isset($_GET['pid']) && (int)$_GET['pid'] > 0);

// 强制物理销毁所有旧身份
if ($__is_pid) {
    setcookie("user_token", "", time() - 3600, "/");
    $_COOKIE['user_token'] = ''; // 必须清空当前请求的全局变量
    if (session_status() == PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        session_destroy();
    }
}

/**
 * 登录
**/
$is_defend=true;
include("../includes/common.php");

// 专属链接强行覆盖登录状态
if ($__is_pid) {
    $islogin2 = 0;
    $_SESSION = [];
}

if(isset($_GET['logout'])){
	if(!checkRefererHost())exit();
	setcookie("user_token", "", time() - 2592000);
	@header('Content-Type: text/html; charset=UTF-8');
	exit("<script language='javascript'>alert('您已成功注销本次登录！');window.location.href='./login.php';</script>");
}elseif($islogin2==1){
	exit("<script language='javascript'>alert('您已登录！');window.location.href='./';</script>");
}
$csrf_token = md5(mt_rand(0,999).time());
$_SESSION['csrf_token'] = $csrf_token;
if ($conf['captcha_open_login'] == 1 && (!isset($_SESSION['math_a']) || !isset($_SESSION['math_b']))) {
    $_SESSION['math_a'] = rand(1, 9);
    $_SESSION['math_b'] = rand(1, 9);
}
if ($conf['captcha_open_login'] != 1) {
    unset($_SESSION['math_a'], $_SESSION['math_b']);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8" />
<title>登录 | X</title>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
<link rel="stylesheet" href="<?php echo $cdnpublic?>twitter-bootstrap/3.4.1/css/bootstrap.min.css" type="text/css" />
<link rel="stylesheet" href="<?php echo $cdnpublic?>animate.css/3.7.2/animate.min.css" type="text/css" />
<link rel="stylesheet" href="<?php echo $cdnpublic?>font-awesome/4.7.0/css/font-awesome.min.css" type="text/css" />
<link rel="stylesheet" href="./assets/css/font.css" type="text/css" />
<link rel="stylesheet" href="./assets/css/app.css" type="text/css" />
<link rel="stylesheet" href="./assets/css/captcha.css" type="text/css" />
<style>input:-webkit-autofill{-webkit-box-shadow:0 0 0px 1000px white inset;-webkit-text-fill-color:#333;}</style>












<style id="xinda-login-final">
/* ==== 彻底重置 body，禁止任何 flex ==== */
html, body {
  display: block !important;
  flex-direction: row !important;
  margin: 0 !important;
  padding: 0 !important;
  box-sizing: border-box !important;
  width: 100% !important;
}
body {
  min-height: 100vh !important;
  background: linear-gradient(160deg, #e0ecff 0%, #c7ddfa 45%, #a8c8f5 100%) !important;
  background-attachment: fixed !important;
  font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", sans-serif !important;
  padding-bottom: 80px !important;
  overflow-x: hidden !important;
}

/* ==== 登录区：经典 text-align + inline-block 居中 ==== */
.app {
  display: block !important;
  width: 100% !important;
  padding: 40px 20px 0 !important;
  margin: 0 !important;
  background: transparent !important;
  float: none !important;
  text-align: center !important;
  box-sizing: border-box !important;
}
.app > .container.w-xxl {
  display: inline-block !important;
  width: 100% !important;
  max-width: 420px !important;
  margin: 0 !important;
  padding: 0 !important;
  float: none !important;
  text-align: left !important;
  vertical-align: top !important;
}
.navbar-brand.block.m-t {
  display: block !important;
  text-align: center !important;
  font-size: 36px !important;
  font-weight: 900 !important;
  letter-spacing: 4px !important;
  margin: 0 0 10px !important;
  padding: 0 !important;
  background: linear-gradient(90deg, #0d5cd6 0%, #1677ff 60%, #40a9ff 100%) !important;
  -webkit-background-clip: text !important;
  -webkit-text-fill-color: transparent !important;
  background-clip: text !important;
  line-height: 1.15 !important;
}
.wrapper.text-center strong { color: #475569 !important; font-weight: 500 !important; font-size: 13px !important; }
form.form-validation {
  background: #fff !important;
  border-radius: 14px !important;
  box-shadow: 0 10px 34px rgba(15,23,42,0.12) !important;
  padding: 24px 26px !important;
  border: 1px solid rgba(255,255,255,0.8) !important;
  margin-top: 10px !important;
  text-align: left !important;
}
.nav-tabs { border: none !important; background: #f1f5f9 !important; border-radius: 9px !important; padding: 3px !important; display: flex !important; margin: 0 0 16px !important; }
.nav-tabs > li { flex: 1 !important; float: none !important; width: auto !important; margin: 0 !important; }
.nav-tabs > li > a { border: none !important; border-radius: 7px !important; color: #64748b !important; font-weight: 600 !important; margin: 0 !important; padding: 8px 0 !important; text-align: center !important; background: transparent !important; font-size: 13px !important; }
.nav-tabs > li.active > a { background: #fff !important; color: #1677ff !important; box-shadow: 0 2px 8px rgba(22,119,255,0.12) !important; }
.list-group-item { border: 1px solid #e2e8f0 !important; border-radius: 9px !important; background: #f8fafc !important; margin-bottom: 12px !important; padding: 0 !important; }
.list-group-item .form-control { height: 44px !important; border: none !important; background: transparent !important; font-size: 14px !important; padding: 0 14px !important; box-shadow: none !important; color: #0f172a !important; }
.list-group-item .form-control::placeholder { color: #94a3b8 !important; }
.list-group-item:focus-within { border-color: #1677ff !important; background: #fff !important; box-shadow: 0 0 0 3px rgba(22,119,255,0.1) !important; }
#submit { height: 44px !important; background: linear-gradient(90deg, #0d5cd6 0%, #1677ff 100%) !important; border: none !important; border-radius: 9px !important; font-size: 15px !important; font-weight: 700 !important; letter-spacing: 2px !important; width: 100% !important; color: #fff !important; box-shadow: 0 4px 12px rgba(22,119,255,0.25) !important; }
#submit:hover { background: linear-gradient(90deg, #1677ff 0%, #40a9ff 100%) !important; }
.btn-rounded { border-radius: 7px !important; padding: 6px 14px !important; font-size: 12.5px !important; font-weight: 600 !important; }
.btn-info { background: #e0f2fe !important; border-color: #e0f2fe !important; color: #0369a1 !important; }
.btn-danger { background: #fee2e2 !important; border-color: #fee2e2 !important; color: #b91c1c !important; }
.line-dashed { border-top: 1px dashed #e2e8f0 !important; margin: 16px 0 !important; }
.text-center small, .text-center small a { color: #64748b !important; font-size: 12px !important; }

/* ==== 卡片区：同样 text-align + inline-block 居中 ==== */
.xinda-page-wrap {
  display: block !important;
  width: 100% !important;
  padding: 0 20px 100px !important;
  margin: 0 !important;
  box-sizing: border-box !important;
  float: none !important;
  text-align: center !important;
  clear: both !important;
}
.login-right-panel {
  display: inline-grid !important;
  grid-template-columns: repeat(4, 1fr) !important;
  gap: 16px !important;
  width: 100% !important;
  max-width: 1100px !important;
  margin: 0 !important;
  padding: 0 !important;
  text-align: left !important;
  vertical-align: top !important;
}
.login-right-panel .xinda-feature-card { background: rgba(255,255,255,0.95); border-radius: 14px; padding: 18px; box-shadow: 0 4px 18px rgba(15,23,42,0.06); border: 1px solid rgba(255,255,255,0.9); transition: all .25s; }
.login-right-panel .xinda-feature-card:hover { transform: translateY(-2px); box-shadow: 0 8px 26px rgba(22,119,255,0.15); border-color: #bae0ff; }
.login-right-panel .xinda-feature-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 17px; margin-bottom: 10px; }
.login-right-panel .xinda-feature-title { font-size: 14px; font-weight: 700; color: #0f172a; margin-bottom: 5px; }
.login-right-panel .xinda-feature-desc { font-size: 12px; color: #64748b; line-height: 1.55; }

/* ==== 版权：fixed 贴底，脱离所有布局 ==== */
.xinda-footer-copyright {
  position: fixed !important;
  bottom: 0 !important;
  left: 0 !important;
  right: 0 !important;
  z-index: 999 !important;
  text-align: center !important;
  color: #fff !important;
  font-size: 12px !important;
  line-height: 1.7 !important;
  padding: 10px 20px !important;
  background: rgba(13,92,214,0.4) !important;
  backdrop-filter: blur(6px) !important;
  border-top: 1px solid rgba(255,255,255,0.18) !important;
  box-sizing: border-box !important;
  width: 100% !important;
  float: none !important;
  margin: 0 !important;
}
.xinda-footer-copyright a { color: rgba(255,255,255,0.85) !important; text-decoration: none !important; }

@media (max-width: 900px) {
  .login-right-panel { grid-template-columns: repeat(2, 1fr) !important; }
  .navbar-brand.block.m-t { font-size: 30px !important; }
}
@media (max-width: 600px) {
  .login-right-panel { display: none !important; }
  .navbar-brand.block.m-t { font-size: 28px !important; letter-spacing: 2px !important; }
  .app { padding-top: 24px !important; }
}
</style>

<style>
@media (max-width: 767px) {
    body { padding-top: 20px !important; background: #f4f6fb !important; }
    .container { width: 95% !important; padding: 0 10px !important; }
    .panel { border-radius: 16px !important; border: none !important; box-shadow: 0 4px 20px rgba(0,0,0,0.05) !important; }
    .panel-heading { border-radius: 16px 16px 0 0 !important; padding: 20px !important; text-align: center; }
    .panel-body { padding: 24px 20px !important; }
    .form-control { height: 48px !important; font-size: 16px !important; border-radius: 10px !important; }
    .btn { height: 48px !important; font-size: 16px !important; border-radius: 10px !important; }
}
</style>
</head>
<body>


<div class="app app-header-fixed  ">
<div class="container w-xxl w-auto-xs" ng-controller="SigninFormController" ng-init="app.settings.container = false;">
<span class="navbar-brand block m-t">X</span>
<div class="m-b-lg">
<div class="wrapper text-center">
<strong>请输入您的商户信息</strong>
</div>
<form name="form" class="form-validation" method="post" action="login.php">
<input type="hidden" name="csrf_token" value="<?php echo $csrf_token?>">
<div class="text-danger wrapper text-center" ng-show="authError">
</div>
<?php if(!$conf['close_keylogin']){?>
<ul class="nav nav-tabs">
    <li style="width: 50%;" align="center" class="<?php echo $_GET['m']!='key'?'active':null;?>">
  <a href="./login.php">密码登录(New)</a>
</li>
    <li style="width: 50%;" align="center" class="<?php echo $_GET['m']=='key'?'active':null;?>">
  <a href="./login.php?m=key">密钥登录</a>
</li>
</ul><?php }?>
<div class="tab-content">
<div class="tab-pane active">
<div class="list-group list-group-sm swaplogin">
<?php if($_GET['m']=='key'){?>
<input type="hidden" name="type" value="0"/>
<div class="list-group-item">
<input type="text" name="user" placeholder="商户ID" value="" class="form-control no-border" onkeydown="if(event.keyCode==13){$('#submit').click()}">
</div>
<div class="list-group-item">
<input type="password" name="pass" placeholder="商户密钥" value="" class="form-control no-border" onkeydown="if(event.keyCode==13){$('#submit').click()}">
</div>
  <div class="list-group-item" style="background:#fef3c7;border:1px solid #fcd34d;padding:12px;">
    <div style="font-size:13px;font-weight:700;color:#92400e;margin-bottom:8px;">🔐 密保问题验证（必须全部答对）</div>
    <?php
    $__QUESTIONS = [
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
    for($__i=1;$__i<=3;$__i++):
    ?>
    <div style="margin-bottom:8px;">
      <select name="q<?=$__i?>" class="form-control" style="height:38px;border-radius:6px;border:1px solid #fcd34d;font-size:13px;">
        <option value="0">-- 选择密保问题 <?=$__i?> --</option>
        <?php foreach($__QUESTIONS as $__n=>$__q): ?>
        <option value="<?=$__n?>"><?=$__q?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="a<?=$__i?>" placeholder="答案 <?=$__i?>" class="form-control" style="height:38px;border-radius:6px;border:1px solid #fcd34d;margin-top:6px;font-size:13px;">
    </div>
    <?php endfor; ?>
    <div style="font-size:11px;color:#92400e;margin-top:4px;">答案不分大小写</div>
  </div>

<?php }else{?>
<input type="hidden" name="type" value="1"/>
<input type="hidden" id="parent_pid" name="parent_pid" value="<?php echo (int)($_GET["pid"] ?? 0); ?>"/>
<div class="list-group-item">
<input type="text" name="user" placeholder="邮箱/手机号" value="" class="form-control no-border" onkeydown="if(event.keyCode==13){$('#submit').click()}">
</div>
<div class="list-group-item">
<input type="password" name="pass" placeholder="密码" value="" class="form-control no-border" onkeydown="if(event.keyCode==13){$('#submit').click()}">
</div>
<?php }?>
<?php if($conf['captcha_open_login']==1){ ?>
<div id="math-box" style="margin: 10px 0; padding: 15px; background: #f0f7ff; border: 1px solid #bfdbfe; border-radius: 8px; text-align: center;">
    <div id="math-question" style="font-size: 16px; font-weight: bold; color: #1e293b; margin-bottom: 8px;">安全校验：<?php echo $_SESSION['math_a']; ?> + <?php echo $_SESSION['math_b']; ?> = ?</div>
    <div style="display: flex; gap: 8px; align-items: center;">
        <input type="number" name="math_captcha" id="math_captcha" placeholder="请输入答案" required style="flex:1; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; text-align: center; font-size: 16px; box-sizing: border-box;">
        <button type="button" id="verifyMathBtn" style="padding: 10px 14px; border: 0; border-radius: 6px; background: #1677ff; color: white; font-weight: 700; white-space: nowrap;">点击验证人机</button>
    </div>
</div>
<?php } ?>
</div>
<div class="list-group-item" id="totp_box" style="display:none; background:#fff8e6; border:1px solid #ffd591;">
<input type="text" name="totp_code" id="totp_code" maxlength="6" placeholder="请输入谷歌验证器 6 位动态码" class="form-control no-border" autocomplete="off" style="letter-spacing:8px; text-align:center; font-size:18px; font-weight:bold;">
<div style="text-align:right;margin-top:6px;">
<a href="javascript:;" id="switchToQa" style="color:#1677ff;font-size:12px;">🔑 无法使用验证器？使用密保登录</a>
</div>
</div>
<div class="list-group-item" id="qa_box" style="display:none; background:#fef3c7; border:1px solid #fcd34d; padding:12px;">
<div style="font-size:13px;font-weight:700;color:#92400e;margin-bottom:8px;">🔐 密保问题验证（3 题全对才能登录）</div>
<?php
$__QUESTIONS = [
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
for($__i=1;$__i<=3;$__i++):
?>
<div style="margin-bottom:8px;">
<select name="q<?=$__i?>" class="form-control" style="height:38px;border-radius:6px;border:1px solid #fcd34d;font-size:13px;">
<option value="0">-- 选择密保问题 <?=$__i?> --</option>
<?php foreach($__QUESTIONS as $__n=>$__q): ?>
<option value="<?=$__n?>"><?=$__q?></option>
<?php endforeach; ?>
</select>
<input type="text" name="a<?=$__i?>" placeholder="答案 <?=$__i?>" class="form-control" style="height:38px;border-radius:6px;border:1px solid #fcd34d;margin-top:6px;font-size:13px;">
</div>
<?php endfor; ?>
<div style="text-align:right;margin-top:6px;">
<a href="javascript:;" id="switchToTotp" style="color:#1677ff;font-size:12px;">← 返回验证器登录</a>
</div>
</div>
<div style="display:none;">
  🔐 <b>账户安全提示</b><br>
  累计登录 <b>3 次</b> 后需绑定谷歌/微软验证器，之后每次登录需输入 6 位动态验证码。
</div>

<button type="button" class="btn btn-lg btn-primary btn-block" id="submit">立即登录</button>
</div>
</div>
<div class="line line-dashed"></div>
<div class="form-group">
	<a href="findpwd.php" class="btn btn-info btn-rounded"><i class="fa fa-unlock"></i>&nbsp;找回密码</a>
	<a href="reg.php" class="btn btn-danger btn-rounded <?php echo $conf['reg_open']==0?'hide':null;?>" style="float:right;"><i class="fa fa-user-plus"></i>&nbsp;注册商户</a>
</div>
<?php if(!isset($_GET['connect'])){?>
<div class="wrapper text-center">
<?php if($conf['login_alipay']>0 || $conf['login_alipay']==-1){?>
<button type="button" class="btn btn-rounded btn-lg btn-icon btn-default" title="支付宝快捷登录" onclick="connect('alipay')"><img src="../assets/icon/alipay.ico" style="border-radius:50px;"></button>
<?php }?>
<?php if($conf['login_qq']>0){?>
<button type="button" class="btn btn-rounded btn-lg btn-icon btn-default" title="QQ快捷登录" onclick="connect('qq')"><i class="fa fa-qq fa-lg" style="color: #0BB2FF"></i></button>
<?php }?>
<?php if($conf['login_wx']>0 || $conf['login_wx']==-1){?>
<button type="button" class="btn btn-rounded btn-lg btn-icon btn-default" title="微信快捷登录" onclick="connect('wx')"><i class="fa fa-wechat fa-lg" style="color: green"></i></button>
</div>
<?php }?>
<?php }?>
</form>
</div>

</div>
</div>
<script src="<?php echo $cdnpublic?>jquery/3.4.1/jquery.min.js"></script>
<script src="<?php echo $cdnpublic?>twitter-bootstrap/3.4.1/js/bootstrap.min.js"></script>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.js"></script>
<script src="<?php echo $cdnpublic?>jsencrypt/3.5.4/jsencrypt.min.js"></script>

<script>
window.appendChildOrg = Element.prototype.appendChild;
Element.prototype.appendChild = function() {
    if(arguments[0].tagName == 'SCRIPT'){
        arguments[0].setAttribute('referrerpolicy', 'no-referrer');
    }
    return window.appendChildOrg.apply(this, arguments);
};
</script>

<script>
const PUBLIC_KEY_PEM = `<?php echo base64ToPem($conf['public_key'], 'PUBLIC KEY')?>`;
var captcha_open = 0;
var requireMathCaptcha = <?php echo ($conf['captcha_open_login']==1 ? 'true' : 'false'); ?>;
var mathValidated = !requireMathCaptcha;
var handlerEmbed = function (captchaObj) {};
$(document).ready(function(){
$("#switchToQa").click(function(){ $("#totp_box").hide(); $("#qa_box").show(); });
$("#switchToTotp").click(function(){ $("#qa_box").hide(); $("#totp_box").show(); });

$('#verifyMathBtn').click(function(){
    var $input = $('#math_captcha');
    var val = $.trim($input.val());
    if (val === '') {
        layer.alert('请先输入答案，再点击验证人机');
        $input.focus();
        return;
    }
    var question = $('#math-question').text() || '';
    var nums = question.match(/\d+/g) || [];
    if (nums.length < 2) {
        layer.alert('人机验证题目异常，请刷新页面重试');
        return;
    }
    var expected = Number(nums[0]) + Number(nums[1]);
    if (Number(val) !== expected) {
        layer.alert('答案错误，请重新计算后再验证');
        $input.val('').focus();
        mathValidated = false;
        $('#verifyMathBtn').text('点击验证人机').css('background', '#1677ff');
        return;
    }
    mathValidated = true;
    $('#verifyMathBtn').text('已验证').css('background', '#2ecc71');
    layer.msg('人机验证已通过', {icon: 1, time: 1200});
});
$('#math_captcha').on('input change', function(){
    if (mathValidated) {
        mathValidated = false;
        $('#verifyMathBtn').text('点击验证人机').css('background', '#1677ff');
    }
});

	$("#submit").click(function(){
		if (requireMathCaptcha && !mathValidated) {
			layer.alert('请先完成人机验证，再登录');
			$('#math_captcha').focus();
			return false;
		}
		var type=$("input[name='type']").val();
		var user=$("input[name='user']").val();
		var pass=$("input[name='pass']").val();
		if(user=='' || pass==''){layer.alert(type==1?'账号和密码不能为空！':'ID和密钥不能为空！');return false;}
		                if(type==0){
                        var q1=$("select[name='q1']").val(),a1=$.trim($("input[name='a1']").val()||"");
                        var q2=$("select[name='q2']").val(),a2=$.trim($("input[name='a2']").val()||"");
                        var q3=$("select[name='q3']").val(),a3=$.trim($("input[name='a3']").val()||"");
                        if(!q1||q1=='0'||!q2||q2=='0'||!q3||q3=='0'){layer.alert('请选择 3 个密保问题');return false;}
                        if(!a1||!a2||!a3){layer.alert('请填写 3 个密保答案');return false;}
                }

          submitLogin(type,user,pass);
	});
});
function submitLogin(type,user,pass){
	var csrf_token=$("input[name='csrf_token']").val();
	var enc_type = '0';
	if(PUBLIC_KEY_PEM != ''){
		const enc = new JSEncrypt();
		enc.setPublicKey(PUBLIC_KEY_PEM);
		pass = enc.encrypt(pass);
		if(pass) enc_type = '1';
	}
	var ii = layer.load();
	$.ajax({
		type: "POST",
		dataType: "json",
		data: {type:type, user:user, pass:pass, enc:enc_type, csrf_token:csrf_token, totp_code: $("#totp_code").val()||"", q1: $("select[name='q1']").val()||0, a1: $.trim($("input[name='a1']").val()||""), q2: $("select[name='q2']").val()||0, a2: $.trim($("input[name='a2']").val()||""), q3: $("select[name='q3']").val()||0, a3: $.trim($("input[name='a3']").val()||""), parent_pid: $('input[name="parent_pid"]').val() || 0, math_captcha: $("#math_captcha").val() },
		url: "ajax.php?act=login",
		success: function (data, textStatus) {
			layer.close(ii);
			if (data.code == 0) {
				layer.msg(data.msg, {icon: 16,time: 10000,shade:[0.3, "#000"]});
				setTimeout(function(){ window.location.href=data.url }, 1000);
			                        }else if (data.vcode == 2) {
                                $("#totp_box").show();
                                $("#totp_code").focus();
                                layer.msg(data.msg, {icon: 16, time: 3000});
                        }else{
                                layer.alert(data.msg, {icon: 2});
                                if($.captchaObj) $.captchaObj.reset();
                        }
		},
		error: function (data) {
			layer.msg('服务器错误', {icon: 2});
			return false;
		}
	});
}
function connect(type){
	var ii = layer.load();
	$.ajax({
		type : "POST",
		url : "ajax.php?act=connect",
		data : {type:type},
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				window.location.href = data.url;
			}else{
				layer.alert(data.msg, {icon: 7});
			}
		} 
	});
}
</script>


<div class="xinda-page-wrap">
  <div class="login-right-panel">
    <div class="xinda-feature-card">
      <div class="xinda-feature-icon" style="background:linear-gradient(135deg,#1677ff,#0d5cd6);"><i class="fa fa-cubes"></i></div>
      <div class="xinda-feature-body">
        <div class="xinda-feature-title">进件开户即用</div>
        <div class="xinda-feature-desc">开户即可使用H5通道，支持线上商城、虚拟卡密、游戏话费充值搭建。</div>
      </div>
    </div>
    <div class="xinda-feature-card">
      <div class="xinda-feature-icon" style="background:linear-gradient(135deg,#07c160,#059948);"><i class="fa fa-credit-card"></i></div>
      <div class="xinda-feature-body">
        <div class="xinda-feature-title">开户即用</div>
        <div class="xinda-feature-desc">不抽成、不按笔收费，含全部支付接口与回调，开户即可开始收款。</div>
      </div>
    </div>
    <div class="xinda-feature-card">
      <div class="xinda-feature-icon" style="background:linear-gradient(135deg,#a855f7,#7c3aed);"><i class="fa fa-qrcode"></i></div>
      <div class="xinda-feature-body">
        <div class="xinda-feature-title">智能路由 +10%</div>
        <div class="xinda-feature-desc">自动匹配最优通道，收款成功率 +10%，防封防拦截。</div>
      </div>
    </div>
    <div class="xinda-feature-card">
      <div class="xinda-feature-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);"><i class="fa fa-random"></i></div>
      <div class="xinda-feature-body">
        <div class="xinda-feature-title">H5 通道</div>
        <div class="xinda-feature-desc">H5 通道 + 智能 IP 跳转，全场景兼容，全国无死角收款。</div>
      </div>
    </div>
  </div>
</div>
<div class="xinda-footer-copyright">
</div>
</body>
</html>