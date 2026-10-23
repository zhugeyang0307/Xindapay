<?php
$is_defend = true;
include("../includes/common.php");
if($islogin2 != 1) exit("<script>location.href='./login.php';</script>");
$uid = (int)$userrow['uid'];
$title = '支付域名管理';
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    header('Content-Type: application/json; charset=utf-8');
    try {
        $act = $_POST['act'] ?? '';
    
    if($act === 'add'){
        $domain = trim($_POST['domain'] ?? '');
        // 清洗域名
        $domain = preg_replace('#^https?://#i', '', $domain);
        $domain = rtrim($domain, '/');
        $domain = explode('/', $domain)[0];
        
        if(!$domain || !filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)){
            exit(json_encode(['code'=>-1, 'msg'=>'域名格式错误，请填写如 example.com']));
        }
        
        $exists = $DB->getRow("SELECT id FROM pay_domain WHERE uid=:u AND domain=:d", [':u'=>$uid, ':d'=>$domain]);
        if($exists){
            exit(json_encode(['code'=>-1, 'msg'=>'该域名已在您的白名单中']));
        }
        
        $DB->exec("INSERT INTO pay_domain (uid, domain, status, addtime) VALUES (:u, :d, 1, NOW())", [':u'=>$uid, ':d'=>$domain]);
        exit(json_encode(['code'=>0, 'msg'=>'添加成功，已自动放行']));
    }
    
    if($act === 'del'){
        $id = (int)($_POST['id'] ?? 0);
        $DB->exec("DELETE FROM pay_domain WHERE id=:id AND uid=:u", [':id'=>$id, ':u'=>$uid]);
        exit(json_encode(['code'=>0, 'msg'=>'删除成功']));
    }
    exit(json_encode(['code'=>-1, 'msg'=>'未知操作']));
    } catch (Throwable $e) {
        exit(json_encode(['code'=>-1, 'msg'=>'系统错误：' . $e->getMessage()]));
    }
}

include './head.php';



$domains = $DB->query("SELECT * FROM pay_domain WHERE uid=:u ORDER BY id DESC", [':u'=>$uid]);
?>
<style>
.dom-card{background:#fff;border-radius:10px;padding:25px;box-shadow:0 2px 10px rgba(0,0,0,.05);margin-bottom:20px;}
table.dom{width:100%;border-collapse:collapse;font-size:14px;}
table.dom th,table.dom td{padding:12px;border-bottom:1px solid #eee;text-align:left;}
table.dom th{background:#f8fafc;font-weight:600;color:#555;}
</style>
<div id="content" class="app-content" role="main">
<div class="app-content-body">
<div class="bg-light lter b-b wrapper-md"><h1 class="m-n font-thin h3">🌐 支付域名管理</h1></div>
<div class="wrapper-md">
  
  <div class="dom-card">
    <div class="alert alert-info" style="font-size:13px; margin-bottom:20px;">
      <b>作用：</b>当您的系统开启了域名白名单限制时，若您的发起支付域名未在此列表，将无法正常发起支付。<br>
      <b>操作：</b>在下方输入您的网站域名（例如 <code>test.com</code>），点击添加即可自动放行，无需等待客服审核。
    </div>
    
    <div style="display:flex; gap:10px; max-width:600px;">
      <input type="text" id="domain_input" class="form-control" placeholder="请输入域名（如 example.com）" style="height:40px;">
      <button type="button" class="btn btn-primary" id="btnAddDomain" onclick="addDomain()" style="height:40px; padding:0 20px; white-space:nowrap;"><i class="fa fa-plus"></i> 添加域名</button>
    </div>
  </div>

  <div class="dom-card">
    <h4 style="margin-top:0; margin-bottom:15px; font-size:16px; font-weight:bold; color:#333;">已授权的域名</h4>
    <table class="dom">
      <thead><tr><th>ID</th><th>域名</th><th>添加时间</th><th>状态</th><th>操作</th></tr></thead>
      <tbody>
        <?php if(empty($domains)): ?>
        <tr><td colspan="5" style="text-align:center; color:#999; padding:30px;">暂无授权域名，请在上方添加</td></tr>
        <?php else: ?>
        <?php foreach($domains as $d): ?>
        <tr>
          <td><?php echo $d['id'];?></td>
          <td><b><?php echo htmlspecialchars($d['domain']);?></b></td>
          <td><?php echo $d['addtime'];?></td>
          <td><span style="color:#07c160;">✅ 已放行</span></td>
          <td><a href="javascript:;" style="color:#e74c3c;" onclick="delDomain(<?php echo $d['id'];?>)">删除</a></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</div>
</div>
</div>
<script>
function addDomain(){
  var dom = document.getElementById('domain_input').value.trim();
  if(!dom){ alert('请输入域名'); return; }
  
  var btn = document.getElementById('btnAddDomain');
  var oldText = btn.innerHTML;
  btn.innerHTML = '添加中...';
  btn.disabled = true;

  var fd = new FormData(); fd.append('act', 'add'); fd.append('domain', dom);
  
  fetch('?my=add_domain', {method:'POST', body:fd})
  .then(r => r.text()) // 先读文本，防止 JSON 解析失败
  .then(text => {
    try {
        var d = JSON.parse(text);
        alert(d.msg);
        if(d.code === 0) location.reload();
    } catch(e) {
        alert('服务器返回异常：' + text);
    }
  })
  .catch(e => {
    alert('网络请求失败：' + e);
  })
  .finally(() => {
    btn.innerHTML = oldText;
    btn.disabled = false;
  });
}
function delDomain(id){
  if(!confirm('确定删除该域名吗？删除后将无法使用该域名发起支付。')) return;
  var fd = new FormData(); fd.append('act', 'del'); fd.append('id', id);
  fetch('', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
    alert(d.msg); if(d.code === 0) location.reload();
  });
}
</script>
<?php include './foot.php'; ?>
