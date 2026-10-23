<?php
include("../includes/common.php");
if($islogin2==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
$title='个人资料';
include './head.php';
?>
<?php
$mod=isset($_GET['mod'])?$_GET['mod']:'api';

if(strlen($userrow['phone'])==11){
	$userrow['phone']=substr($userrow['phone'],0,3).'****'.substr($userrow['phone'],7,10);
}

?>
<input type="hidden" id="situation" value="">
 <div id="content" class="app-content" role="main">
    <div class="app-content-body ">
		<div class="modal inmodal fade" id="myModal" tabindex="-1" role="dialog" aria-hidden="true">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">关闭</span>
						</button>
						<h4 class="modal-title">验证密保信息</h4>
					</div>
					<div class="modal-body">
<?php if($conf['verifytype']==1){?>
<div class="list-group-item">密保手机：<?php echo $userrow['phone']?></div>
<div class="list-group-item">
<div class="input-group">
<input type="text" name="code" placeholder="输入短信验证码" class="form-control" required>
<a class="input-group-addon" id="sendcode">获取验证码</a>
</div>
</div>
<?php }else{?>
<div class="list-group-item">密保邮箱：<?php echo $userrow['email']?></div>
<div class="list-group-item">
<div class="input-group">
<input type="text" name="code" placeholder="输入验证码" class="form-control" required>
<a class="input-group-addon" id="sendcode">获取验证码</a>
</div>
</div>
<?php }?>
<button type="button" id="verifycode" class="btn btn-primary btn-block">确定</button>
<div id="embed-captcha"></div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-white" data-dismiss="modal">关闭</button>
					</div>
				</div>
			</div>
		</div>
		<div class="modal inmodal fade" id="myModal2" tabindex="-1" role="dialog" aria-hidden="true">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">关闭</span>
						</button>
						<h4 class="modal-title">修改密保信息</h4>
					</div>
					<div class="modal-body">
<?php if($conf['verifytype']==1){?>
<div class="list-group-item">
<input type="text" name="phone_n" placeholder="输入新的手机号码" class="form-control" required>
</div>
<div class="list-group-item">
<div class="input-group">
<input type="text" name="code_n" placeholder="输入短信验证码" class="form-control" required>
<a class="input-group-addon" id="sendcode2">获取验证码</a>
</div>
</div>
<?php }else{?>
<div class="list-group-item">
<input type="email" name="email_n" placeholder="输入新的邮箱" class="form-control" required>
</div>
<div class="list-group-item">
<div class="input-group">
<input type="text" name="code_n" placeholder="输入验证码" class="form-control" required>
<a class="input-group-addon" id="sendcode2">获取验证码</a>
</div>
</div>
<?php }?>
<button type="button" id="editBind" class="btn btn-primary btn-block">确定</button>
<div id="embed-captcha"></div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-white" data-dismiss="modal">关闭</button>
					</div>
				</div>
			</div>
		</div>
		<div class="modal inmodal fade" id="myModal3" tabindex="-1" role="dialog" aria-hidden="true">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">关闭</span>
						</button>
						<h4 class="modal-title">修改手机号码</h4>
					</div>
					<div class="modal-body">
<div class="list-group-item">
<input type="text" name="phone_s" placeholder="输入新的手机号码" class="form-control" required>
</div>
<div class="list-group-item">
<div class="input-group">
<input type="text" name="code_s" placeholder="输入短信验证码" class="form-control" required>
<a class="input-group-addon" id="sendcode3">获取验证码</a>
</div>
</div>
<button type="button" id="editBindPhone" class="btn btn-primary btn-block">确定</button>
<div id="embed-captcha"></div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-white" data-dismiss="modal">关闭</button>
					</div>
				</div>
			</div>
		</div>
<div class="bg-light lter b-b wrapper-md hidden-print">
  <h1 class="m-n font-thin h3">个人资料</h1>
</div>
<div class="wrapper-md control">
<?php if(isset($msg)){?>
<div class="alert alert-info">
	<?php echo $msg?>
</div>
<?php }?>
<div class="tab-container ng-isolate-scope">
<ul class="nav nav-tabs">
	<li style="width: 25%;" align="center">
		<a href="userinfo.php?mod=api">API信息</a>
	</li>
	<li style="width: 25%;" align="center" class="active">
		<a href="editinfo.php">修改资料</a>
	</li>
	<li style="width: 25%;" align="center">
		<a href="userinfo.php?mod=account">修改密码</a>
	</li>
	<?php if($conf['cert_open']>0){?>
	<li style="width: 25%;" align="center">
		<a href="certificate.php">实名认证</a>
	</li>
	<?php }?>
</ul>
	<div class="tab-content">
		<div class="tab-pane ng-scope active">
			<form class="form-horizontal devform">
                            <!-- 统一风格的原生活动横幅 -->
                            <div class="form-group" style="margin-top: 20px; margin-bottom: 10px;">
                                <div class="col-sm-offset-2 col-sm-9">
                                    <div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 12px 15px; border-radius: 4px; font-size: 13px;">
                                        🎉 <b>信达支付限时福利：</b>即日起注册，免月费体验所有高级功能至 <b>2026-11-01</b>！平台 <b>0 费率</b> 不抽成，收多少拿多少。
                                    </div>
                                </div>
                            </div>
                            <div class="line line-dashed b-b line-lg pull-in" style="margin-top: 10px;"></div>

                            <div class="form-group"><div class="col-sm-offset-2 col-sm-4"><h4>联系方式与功能设置：</h4></div></div>

                            <!-- 手机号码 -->
                            <div class="form-group">
                                <label class="col-sm-2 control-label">手机号码</label>
                                <div class="col-sm-9">
                                    <div class="input-group">
                                        <input class="form-control" type="text" name="phone" value="<?php echo $userrow['phone'] ? substr_replace($userrow['phone'],'****',3,4) : ''?>" disabled>
                                        <a class="input-group-addon" id="checkbind" style="cursor:pointer;">修改绑定</a>
                                    </div>
                                </div>
                            </div>

                            <!-- 邮箱账号 -->
                            <div class="form-group">
                                <label class="col-sm-2 control-label">邮箱账号</label>
                                <div class="col-sm-9">
                                    <input class="form-control" type="text" name="email" value="<?php echo $userrow['email']?>">
                                </div>
                            </div>

                            <!-- 网站域名 -->
                            <div class="form-group">
                                <label class="col-sm-2 control-label">网站域名</label>
                                <div class="col-sm-9">
                                    <input class="form-control" type="text" name="url" value="<?php echo $userrow['url']?>">
                                </div>
                            </div>

                            <!-- 开启密钥登录 -->
                            <div class="form-group">
                                <label class="col-sm-2 control-label">开启密钥登录</label>
                                <div class="col-sm-9">
                                    <select class="form-control" name="keylogin" default="<?php echo $userrow['keylogin']?>">
                                        <option value="0">关闭</option>
                                        <option value="1">开启</option>
                                    </select>
                                </div>
                            </div>

                            <!-- 隐藏域，防止底部 JS 获取不到值导致保存报错或清空数据 -->
                            <div style="display:none;">
                                <input class="form-control" type="text" name="pay_maxmoney" value="<?php echo $userrow['pay_maxmoney']?>">
                                <input class="form-control" type="text" name="pay_minmoney" value="<?php echo $userrow['pay_minmoney']?>">
                                <input type="text" name="remain_money" value="<?php echo $userrow['remain_money']?>">
                                <select name="refund"><option value="<?php echo $userrow['refund']?>"></option></select>
                                <select name="transfer"><option value="<?php echo $userrow['transfer']?>"></option></select>
                                <input type="text" name="qq" value="<?php echo $userrow['qq']?>">
                            </div>

                            <!-- 确定修改按钮 -->
                            <div class="form-group">
                                <div class="col-sm-offset-2 col-sm-4">
                                    <input type="button" id="editInfo" value="确定修改" class="btn btn-primary form-control"/>
                                </div>
                            </div>
                        </form>
		</div>
	</div>
</div>
</div>
    </div>
  </div>
<?php include 'foot.php';?>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.js"></script>
<script src="<?php echo $cdnpublic?>jquery.qrcode/1.0/jquery.qrcode.min.js"></script>
<script src="//static.geetest.com/static/tools/gt.js"></script>
<script>
window.appendChildOrg = Element.prototype.appendChild;
Element.prototype.appendChild = function() {
    if(arguments[0].tagName == 'SCRIPT'){
        arguments[0].setAttribute('referrerpolicy', 'no-referrer');
    }
    return window.appendChildOrg.apply(this, arguments);
};
</script>
<script src="//static.geetest.com/v4/gt4.js"></script>
<script>
function invokeSettime(obj){
    var countdown=60;
    settime(obj);
    function settime(obj) {
        if (countdown == 0) {
            $(obj).attr("data-lock", "false");
			$(obj).attr("disabled",false);
            $(obj).text("获取验证码");
            countdown = 60;
            return;
        } else {
			$(obj).attr("data-lock", "true");
            $(obj).attr("disabled",true);
            $(obj).text("(" + countdown + ") s 重新发送");
            countdown--;
        }
        setTimeout(function() {
                    settime(obj) }
                ,1000)
    }
}
var handlerEmbed = function (captchaObj) {
	var target;
	captchaObj.onReady(function () {
		$("#wait").hide();
	}).onSuccess(function () {
		var result = captchaObj.getValidate();
		if (!result) {
			return alert('请完成验证');
		}
		var situation=$("#situation").val();
		var ii = layer.load(2, {shade:[0.1,'#fff']});
		$.ajax({
			type : "POST",
			url : "ajax2.php?act=sendcode",
			data : {situation:situation,target:target, ...result},
			dataType : 'json',
			success : function(data) {
				layer.close(ii);
				if(data.code == 0){
					new invokeSettime("#sendcode");
					new invokeSettime("#sendcode2");
					new invokeSettime("#sendcode3");
					layer.msg('发送成功，请注意查收！');
				}else{
					layer.alert(data.msg);
					captchaObj.reset();
				}
			} 
		});
	}).onError(function(){
		layer.msg('验证码加载失败，请刷新页面重试', {icon: 5});
	});
	$('#sendcode').click(function () {
		if ($(this).attr("data-lock") === "true") return;
		if(typeof captchaObj.showCaptcha === 'function'){
			captchaObj.showCaptcha();
		}else{
			captchaObj.verify();
		}
	});
	$('#sendcode2').click(function () {
		if ($(this).attr("data-lock") === "true") return;
		if($("input[name='phone_n']").length>0){
			target=$("input[name='phone_n']").val();
			if(target==''){layer.alert('手机号码不能为空！');return false;}
			if(target.length!=11){layer.alert('手机号码不正确！');return false;}
		}else{
			target=$("input[name='email_n']").val();
			if(target==''){layer.alert('邮箱不能为空！');return false;}
			var reg = /^([a-zA-Z0-9_-])+@([a-zA-Z0-9_-])+(.[a-zA-Z0-9_-])+/;
			if(!reg.test(target)){layer.alert('邮箱格式不正确！');return false;}
		}
		if(typeof captchaObj.showCaptcha === 'function'){
			captchaObj.showCaptcha();
		}else{
			captchaObj.verify();
		}
	});
	$('#sendcode3').click(function () {
		if ($(this).attr("data-lock") === "true") return;
		target=$("input[name='phone_s']").val();
		if(target==''){layer.alert('手机号码不能为空！');return false;}
		if(target.length!=11){layer.alert('手机号码不正确！');return false;}
		if(typeof captchaObj.showCaptcha === 'function'){
			captchaObj.showCaptcha();
		}else{
			captchaObj.verify();
		}
	})
};
$(document).ready(function(){
	var items = $("select[default]");
	for (i = 0; i < items.length; i++) {
		$(items[i]).val($(items[i]).attr("default")||0);
	}
	$("select[name='stype']").change(function(){
		var input = $("select[name='stype'] option:selected").attr("input");
		$("#typename").html(input);
		if($(this).val() == 2){
			$("#getopenid_form").show();
		}else{
			$("#getopenid_form").hide();
		}
	});
	$("select[name='stype']").change();
	$("#editSettle").click(function(){
		var stype=$("select[name='stype']").val();
		var account=$("input[name='account']").val();
		var username=$("input[name='username']").val();
		if(account=='' || username==''){layer.alert('请确保各项不能为空！');return false;}
		var ii = layer.load(2, {shade:[0.1,'#fff']});
		$.ajax({
			type : "POST",
			url : "ajax2.php?act=edit_settle",
			data : {stype:stype,account:account,username:username},
			dataType : 'json',
			success : function(data) {
				layer.close(ii);
				if(data.code == 1){
					layer.alert('修改成功！', {icon:1});
				}else if(data.code == 2){
					$("#situation").val("settle");
					$('#myModal').modal('show');
				}else{
					layer.alert(data.msg);
				}
			}
		});
	});
	$("#editInfo").click(function(){
        var email=$("input[name='email']").val();
        var url=$("input[name='url']").val() || ''; // 网站域名允许为空或取默认
        var keylogin=$("select[name='keylogin']").val();
        
        // 隐藏域的备选值，防止后端没数据
        var refund=$("select[name='refund']").val() || '1'; 
        var transfer=$("select[name='transfer']").val() || '0';
        var remain_money=$("input[name='remain_money']").val() || '';
        var pay_maxmoney=$("input[name='pay_maxmoney']").val() || '';
        var pay_minmoney=$("input[name='pay_minmoney']").val() || '';
        
        // 核心校验：只校验邮箱不为空
        if(email==''){layer.alert('邮箱账号不能为空！');return false;}
        
        // 邮箱格式正则校验
        var reg = /^([a-zA-Z0-9_-])+@([a-zA-Z0-9_-])+(.[a-zA-Z0-9_-])+/;
        if(!reg.test(email)){layer.alert('邮箱格式不正确！');return false;}
        
        // 域名处理逻辑
        if (url.indexOf(" ")>=0){ url = url.replace(/ /g,""); }
        if (url.toLowerCase().indexOf("http://")==0){ url = url.slice(7); }
        if (url.toLowerCase().indexOf("https://")==0){ url = url.slice(8); }
        if (url.length > 0 && url.slice(url.length-1)=="/"){ url = url.slice(0,url.length-1); }
        $("input[name='url']").val(url);

        var ii = layer.load(2, {shade:[0.1,'#fff']});
        $.ajax({
            type : "POST",
            url : "ajax2.php?act=edit_info",
            data : {email:email,url:url,keylogin:keylogin,refund:refund,transfer:transfer,remain_money:remain_money, pay_maxmoney:pay_maxmoney,pay_minmoney:pay_minmoney},
            dataType : 'json',
			success : function(data) {
				layer.close(ii);
				if(data.code == 1){
					layer.alert('修改成功！', {icon:1});
				}else{
					layer.alert(data.msg);
				}
			}
		});
	});
	$("#editChannelInfo").click(function(){
		var ii = layer.load(2, {shade:[0.1,'#fff']});
		var setting = {};
		$("input[name^='setting']").each(function(i, el) {
			setting[el.name] =$(this).val();
		});
		$.ajax({
			type : "POST",
			url : "ajax2.php?act=edit_channel_info",
			data : setting,
			dataType : 'json',
			success : function(data) {
				layer.close(ii);
				if(data.code == 1){
					layer.alert('修改成功！', {icon:1});
				}else{
					layer.alert(data.msg);
				}
			}
		});
	});
	$("#editMode").click(function(){
		var mode=$("select[name='mode']").val();
		var ii = layer.load(2, {shade:[0.1,'#fff']});
		$.ajax({
			type : "POST",
			url : "ajax2.php?act=edit_mode",
			data : {mode:mode},
			dataType : 'json',
			success : function(data) {
				layer.close(ii);
				if(data.code == 1){
					layer.alert('修改成功！', {icon:1});
				}else{
					layer.alert(data.msg);
				}
			}
		});
	});
	$("#editVoice").click(function(){
		var voice_devid=$("input[name='voice_devid']").val();
		var voice_order=$("select[name='voice_order']").val();
		var ii = layer.load(2, {shade:[0.1,'#fff']});
		$.ajax({
			type : "POST",
			url : "ajax2.php?act=edit_voice",
			data : {voice_devid:voice_devid, voice_order:voice_order},
			dataType : 'json',
			success : function(data) {
				layer.close(ii);
				if(data.code == 1){
					layer.alert('修改成功！', {icon:1});
				}else{
					layer.alert(data.msg);
				}
			}
		});
	});
	$("#editPrint").click(function(){
		var print_order=$("select[name='print_order']").val();
		var print_devid=$("input[name='print_devid']").val();
		var print_count=$("input[name='print_count']").val();
		var print_voice=$("select[name='print_voice']").val();
		var ii = layer.load(2, {shade:[0.1,'#fff']});
		$.ajax({
			type : "POST",
			url : "ajax2.php?act=edit_print",
			data : {print_order:print_order, print_devid:print_devid, print_count:print_count, print_voice:print_voice},
			dataType : 'json',
			success : function(data) {
				layer.close(ii);
				if(data.code == 1){
					layer.alert('修改成功！', {icon:1});
				}else{
					layer.alert(data.msg);
				}
			}
		});
	});
	$("#editMsgConfig").click(function(){
		var notice_order=$("select[name='notice_order']").val();
		var notice_settle=$("select[name='notice_settle']").val();
		var notice_login=$("select[name='notice_login']").val();
		var notice_complain=$("select[name='notice_complain']").val();
		var notice_mchrisk=$("select[name='notice_mchrisk']").val();
		var notice_order_money=$("input[name='notice_order_money']").val();
		var notice_balance=$("select[name='notice_balance']").val();
		var notice_balance_money=$("input[name='notice_balance_money']").val();
		var notice_msgrobot_url=$("input[name='notice_msgrobot_url']").val();
		var ii = layer.load(2, {shade:[0.1,'#fff']});
		$.ajax({
			type : "POST",
			url : "ajax2.php?act=edit_msgconfig",
			data : {notice_order:notice_order, notice_settle:notice_settle, notice_login:notice_login, notice_complain:notice_complain, notice_mchrisk:notice_mchrisk, notice_order_money:notice_order_money, notice_balance:notice_balance, notice_balance_money:notice_balance_money, notice_msgrobot_url:notice_msgrobot_url},
			dataType : 'json',
			success : function(data) {
				layer.close(ii);
				if(data.code == 1){
					layer.alert('修改成功！', {icon:1});
				}else{
					layer.alert(data.msg);
				}
			}
		});
	});
	$("#checkbind").click(function(){
		var ii = layer.load(2, {shade:[0.1,'#fff']});
		$.ajax({
			type : "GET",
			url : "ajax2.php?act=checkbind",
			dataType : 'json',
			success : function(data) {
				layer.close(ii);
				if(data.code == 1){
					$("#situation").val("bind");
					$('#myModal2').modal('show');
				}else if(data.code == 2){
					$("#situation").val("mibao");
					$('#myModal').modal('show');
				}else{
					layer.alert(data.msg);
				}
			}
		});
	});
	$("#editBind").click(function(){
		var phone=$("input[name='phone_n']").val();
		var email=$("input[name='email_n']").val();
		var code=$("input[name='code_n']").val();
		if(code==''){layer.alert('请输入验证码！');return false;}
		var ii = layer.load(2, {shade:[0.1,'#fff']});
		$.ajax({
			type : "POST",
			url : "ajax2.php?act=edit_bind",
			data : {phone:phone,email:email,code:code},
			dataType : 'json',
			success : function(data) {
				layer.close(ii);
				if(data.code == 1){
					layer.alert('修改绑定成功！', {icon:1}, function(){window.location.reload()});
				}else{
					layer.alert(data.msg);
				}
			}
		});
	});
	$("#editBindPhone").click(function(){
		var phone=$("input[name='phone_s']").val();
		var code=$("input[name='code_s']").val();
		if(code==''){layer.alert('请输入验证码！');return false;}
		var ii = layer.load(2, {shade:[0.1,'#fff']});
		$.ajax({
			type : "POST",
			url : "ajax2.php?act=edit_bind",
			data : {phone:phone,code:code},
			dataType : 'json',
			success : function(data) {
				layer.close(ii);
				if(data.code == 1){
					layer.alert('修改绑定成功！', {icon:1}, function(){window.location.reload()});
				}else{
					layer.alert(data.msg);
				}
			}
		});
	});
	$("#verifycode").click(function(){
		var code=$("input[name='code']").val();
		var situation=$("#situation").val();
		if(code==''){layer.alert('请输入验证码！');return false;}
		var ii = layer.load(2, {shade:[0.1,'#fff']});
		$.ajax({
			type : "POST",
			url : "ajax2.php?act=verifycode",
			data : {code:code},
			dataType : 'json',
			success : function(data) {
				layer.close(ii);
				if(data.code == 1){
					layer.msg('验证成功！', {icon:1});
					$('#myModal').modal('hide');
					if(situation=='settle'){
						$("#editSettle").click();
					}else if(situation=='mibao'){
						$("#situation").val("bind");
						$('#myModal2').modal('show');
					}else if(situation=='bind'){
						$('#myModal2').modal('hide');
						window.location.reload();
					}
				}else{
					layer.alert(data.msg);
				}
			}
		});
	});
	$("#bindphone").click(function(){
		$("#situation").val("bindphone");
		$('#myModal3').modal('show');
	});
	$('#getopenid').click(function () {
		if ($(this).attr("data-lock") === "true") return;
		$(this).attr("data-lock", "true");
		$.ajax({
			type : "GET",
			url : "ajax.php?act=qrcode",
			dataType : 'json',
			success : function(data) {
				$('#getopenid').attr("data-lock", "false");
				if(data.code == 0){
					$.openidform = layer.open({
					  type: 1,
					  title: '请使用微信扫描以下二维码',
					  skin: 'layui-layer-demo',
					  anim: 2,
					  shadeClose: true,
					  content: '<div id="qrcode" class="list-group-item text-center"></div>',
					  success: function(){
						$('#qrcode').qrcode({
							text: data.url,
							width: 230,
							height: 230,
							foreground: "#000000",
							background: "#ffffff",
							typeNumber: -1
						});
						$.ostart = true;
						setTimeout('checkopenid()', 2000);
					  },
					  end: function(){
						$.ostart = false;
					  }
					});
				}else{
					layer.alert(data.msg, {icon: 0});
				}
			},
			error:function(data){
				layer.msg('服务器错误', {icon: 2});
				return false;
			}
		});
	});
	$.ajax({
		url: "ajax.php?act=captcha",
		type: "get",
		cache: false,
		dataType: "json",
		success: function (data) {
			if(data.version == 1){
				initGeetest4({
					captchaId: data.gt,
					product: 'bind',
					protocol: 'https://',
					riskType: 'slide',
					hideSuccess: true,
				}, handlerEmbed);
			}else{
				initGeetest({
					width: '100%',
					gt: data.gt,
					challenge: data.challenge,
					new_captcha: data.new_captcha,
					product: "bind",
					offline: !data.success
				}, handlerEmbed);
			}
		}
	});
});
function checkopenid(){
	$.ajax({
		type: "GET",
		dataType: "json",
		url: "ajax.php?act=getopenid",
		success: function (data, textStatus) {
			if (data.code == 0) {
				layer.msg('Openid获取成功');
				layer.close($.openidform);
				$("input[name='account']").val(data.openid);
			}else if($.ostart==true){
				setTimeout('checkopenid()', 2000);
			}else{
				return false;
			}
		},
		error: function (data) {
			layer.msg('服务器错误', {icon: 2});
			return false;
		}
	});
}
function connect(type){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : "POST",
		url : "ajax.php?act=connect",
		data : {type:type, bind:'1'},
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