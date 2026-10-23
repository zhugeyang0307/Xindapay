<?php
include("../includes/common.php");
if($islogin!=1) exit("<script language='javascript'>window.location.href='./login.php';</script>");
$title='修改密码';

$my = $_GET['my'] ?? '';
if($my == 'save') {
    $oldpwd = trim($_POST['oldpwd'] ?? '');
    $newpwd = trim($_POST['newpwd'] ?? '');
    $newpwd2 = trim($_POST['newpwd2'] ?? '');
    if(!$oldpwd || !$newpwd) exit(json_encode(['code'=>-1,'msg'=>'参数不全']));
    if($newpwd !== $newpwd2) exit(json_encode(['code'=>-1,'msg'=>'两次密码不一致']));
    if(strlen($newpwd) < 6) exit(json_encode(['code'=>-1,'msg'=>'密码至少 6 位']));
    if($oldpwd !== $conf['admin_pwd']) exit(json_encode(['code'=>-1,'msg'=>'旧密码错误']));

    saveSetting('admin_pwd', $newpwd);
    $CACHE->clear();
    exit(json_encode(['code'=>0,'msg'=>'密码修改成功，请重新登录']));
}

include './head.php';
?>
<div id="content" class="app-content" role="main">
<div class="app-content-body">
<div class="bg-light lter b-b wrapper-md hidden-print">
  <h1 class="m-n font-thin h3">修改密码</h1>
</div>
<div class="wrapper-md control">
<div class="panel panel-default">
  <div class="panel-heading font-bold"><i class="fa fa-lock"></i> 管理员密码</div>
  <div class="panel-body">
    <div style="max-width:400px;">
      <div class="form-group">
        <label>旧密码</label>
        <input type="password" id="oldpwd" class="form-control" placeholder="当前密码">
      </div>
      <div class="form-group">
        <label>新密码</label>
        <input type="password" id="newpwd" class="form-control" placeholder="至少 6 位">
      </div>
      <div class="form-group">
        <label>确认新密码</label>
        <input type="password" id="newpwd2" class="form-control" placeholder="再次输入">
      </div>
      <button class="btn btn-primary" onclick="savePwd()"><i class="fa fa-save"></i> 保存</button>
    </div>
  </div>
</div>
</div>
</div>
<script>
function savePwd() {
  const fd = new FormData();
  fd.append('oldpwd', document.getElementById('oldpwd').value);
  fd.append('newpwd', document.getElementById('newpwd').value);
  fd.append('newpwd2', document.getElementById('newpwd2').value);
  fetch('?my=save', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
    alert(d.msg);
    if(d.code === 0) location.href = 'login.php?logout';
  });
}
</script>
<?php include './foot.php'; ?>
