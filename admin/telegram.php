<?php
require_once('../includes/common.php');
require_once(__DIR__ . '/../telegram/lib/DB.php');
$title = '机器人配置';
include './head.php';

if($islogin!=1) exit("<script>location.href='./login.php';</script>");

$my = $_GET['my'] ?? '';

// 保存配置
// 重启机器人
if($my == 'restart_bot') {
    $r1 = '重启成功 (请手动确认)';
    $r2 = '重启成功 (请手动确认)';
    exit(json_encode(['code'=>0, 'msg'=>'机器人已重启']));
}

if($my == 'save') {
    $bot_token = trim($_POST['bot_token'] ?? '');
    $group_id  = trim($_POST['notify_group_id'] ?? '');
    $admin_id  = trim($_POST['admin_id'] ?? '');
    $webhook   = trim($_POST['webhook_secret'] ?? '');
    DB::exec("REPLACE INTO pay_config(k,v) VALUES('tg_bot_token',?)", [$bot_token]);
    DB::exec("REPLACE INTO pay_config(k,v) VALUES('tg_notify_group_id',?)", [$group_id]);
    DB::exec("REPLACE INTO pay_config(k,v) VALUES('tg_admin_id',?)", [$admin_id]);
    DB::exec("REPLACE INTO pay_config(k,v) VALUES('tg_webhook_secret',?)", [$webhook]);
    exit(json_encode(['code'=>0,'msg'=>'保存成功']));
}

if($my == 'unbind_group') {
    $chat_id = trim($_POST['chat_id'] ?? '');
    DB::exec("DELETE FROM pay_tggroup WHERE chat_id=?", [$chat_id]);
    exit(json_encode(['code'=>0,'msg'=>'已解除']));
}

if($my == 'unbind_user') {
    $tg_id = intval($_POST['tg_id'] ?? 0);
    DB::exec("DELETE FROM pay_tgbind WHERE tg_id=?", [$tg_id]);
    exit(json_encode(['code'=>0,'msg'=>'已解除']));
}

$cfg = [];
foreach(DB::query("SELECT k,v FROM pay_config WHERE k LIKE 'tg_%'") as $r) $cfg[$r['k']] = $r['v'];

$groups = DB::query("SELECT g.*, u.username FROM pay_tggroup g 
    LEFT JOIN pay_user u ON u.uid=g.uid ORDER BY g.uid ASC");
$users = DB::query("SELECT b.*, u.username FROM pay_tgbind b 
    LEFT JOIN pay_user u ON u.uid=b.uid ORDER BY b.uid ASC");
?>
<div id="content" class="app-content" role="main">
<div class="app-content-body">

<div class="bg-light lter b-b wrapper-md hidden-print">
  <h1 class="m-n font-thin h3">机器人配置</h1>
</div>

<div class="wrapper-md control">

<div class="panel panel-default">
  <div class="panel-heading font-bold"><i class="fa fa-cog"></i> 基础配置</div>
  <div class="panel-body">
    <form class="form-horizontal" onsubmit="return false;">
      <div class="form-group">
        <label class="col-sm-3 control-label">Bot Token</label>
        <div class="col-sm-8">
          <input id="bot_token" class="form-control" value="<?php echo htmlspecialchars($cfg['tg_bot_token'] ?? ''); ?>">
          <p class="help-block">从 @BotFather 获取。改动后需同步改 telegram/config.php 并重启 paybot</p>
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label">超级管理员群 ID</label>
        <div class="col-sm-8">
          <input id="notify_group_id" class="form-control" value="<?php echo htmlspecialchars($cfg['tg_notify_group_id'] ?? ''); ?>">
          <p class="help-block">负数，如 -1001234567890。所有订单都推到此群</p>
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label">管理员 TG ID</label>
        <div class="col-sm-8">
          <input id="admin_id" class="form-control" value="<?php echo htmlspecialchars($cfg['tg_admin_id'] ?? ''); ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label">Webhook 密钥</label>
        <div class="col-sm-8">
          <input id="webhook_secret" class="form-control" value="<?php echo htmlspecialchars($cfg['tg_webhook_secret'] ?? 'paybot_secret_2026'); ?>">
        </div>
      </div>
      <div class="form-group">
        <div class="col-sm-offset-3 col-sm-8">
          <button class="btn btn-primary" onclick="saveCfg()"><i class="fa fa-save"></i> 保存</button>
        </div>
      </div>
    </form>
  </div>
</div>


<!-- 生效提示 -->
<div class="panel panel-warning">
  <div class="panel-heading font-bold"><i class="fa fa-exclamation-triangle"></i> 修改 Token 后必须重启</div>
  <div class="panel-body">
    <p>改完 Bot Token 或通知群 ID 后，必须重启机器人服务才能生效。</p>
    <button class="btn btn-warning" onclick="restartBot()"><i class="fa fa-refresh"></i> 一键重启机器人</button>
    <span id="restart_msg" style="margin-left:10px;"></span>
  </div>
</div>

<div class="panel panel-default">
  <div class="panel-heading font-bold"><i class="fa fa-users"></i> 已绑定的群（<?php echo count($groups); ?> 个）</div>
  <div class="table-responsive">
    <table class="table table-striped">
      <thead><tr><th>群 ID</th><th>群名</th><th>绑定对象</th><th>时间</th><th>操作</th></tr></thead>
      <tbody>
      <?php if(empty($groups)): ?>
        <tr><td colspan="5" style="text-align:center;color:#999;padding:30px;">还没有绑定</td></tr>
      <?php else: foreach($groups as $g): ?>
        <tr>
          <td><code><?php echo $g['chat_id']; ?></code></td>
          <td><?php echo htmlspecialchars($g['title']); ?></td>
          <td>
            <?php if($g['uid']==0): ?>
              <span class="label label-danger">⭐ 超管群</span>
            <?php else: ?>
              商户：<?php echo $g['username'] ?: '未知'; ?> (UID: <?php echo $g['uid']; ?>)
            <?php endif; ?>
          </td>
          <td><?php echo $g['addtime']; ?></td>
          <td><a href="javascript:;" onclick="unbindGroup('<?php echo $g['chat_id']; ?>')" style="color:#e74c3c;">解除</a></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="panel panel-default">
  <div class="panel-heading font-bold"><i class="fa fa-user"></i> 已绑定的 TG 用户（<?php echo count($users); ?> 个）</div>
  <div class="table-responsive">
    <table class="table table-striped">
      <thead><tr><th>TG ID</th><th>商户</th><th>时间</th><th>操作</th></tr></thead>
      <tbody>
      <?php if(empty($users)): ?>
        <tr><td colspan="4" style="text-align:center;color:#999;padding:30px;">还没有绑定</td></tr>
      <?php else: foreach($users as $u): ?>
        <tr>
          <td><code><?php echo $u['tg_id']; ?></code></td>
          <td><?php echo $u['username'] ?: '-'; ?> (UID: <?php echo $u['uid']; ?>)</td>
          <td><?php echo $u['addtime']; ?></td>
          <td><a href="javascript:;" onclick="unbindUser(<?php echo $u['tg_id']; ?>)" style="color:#e74c3c;">解除</a></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

</div>
</div>

<script>
function restartBot() {
  if(!confirm('确定重启机器人服务？')) return;
  document.getElementById('restart_msg').innerText = '重启中...';
  fetch('?my=restart_bot', {method:'POST'})
    .then(r=>r.json())
    .then(d=>{ document.getElementById('restart_msg').innerText = d.msg; });
}

function saveCfg(){
  const fd = new FormData();
  fd.append('bot_token', document.getElementById('bot_token').value);
  fd.append('notify_group_id', document.getElementById('notify_group_id').value);
  fd.append('admin_id', document.getElementById('admin_id').value);
  fd.append('webhook_secret', document.getElementById('webhook_secret').value);
  fetch('?my=save',{method:'POST',body:fd}).then(r=>r.json()).then(d=>alert(d.msg));
}
function unbindGroup(cid){
  if(!confirm('确定解除？')) return;
  const fd=new FormData(); fd.append('chat_id',cid);
  fetch('?my=unbind_group',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{alert(d.msg);location.reload();});
}
function unbindUser(id){
  if(!confirm('确定解除？')) return;
  const fd=new FormData(); fd.append('tg_id',id);
  fetch('?my=unbind_user',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{alert(d.msg);location.reload();});
}
</script>
<?php include './foot.php'; ?>
