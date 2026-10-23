<?php
require_once('../includes/common.php');
require_once(__DIR__ . '/../telegram/lib/DB.php');
$title = '地区智能路由';
include './head.php';
if($islogin!=1) exit("<script>location.href='./login.php';</script>");

$my = $_GET['my'] ?? '';

if($my == 'save') {
    $id = intval($_POST['id'] ?? 0);
    $prov = trim($_POST['match_province'] ?? '');
    $city = trim($_POST['match_city'] ?? '');
    $isp = trim($_POST['match_isp'] ?? '');
    $cid = intval($_POST['channel_id'] ?? 0);
    $pri = intval($_POST['priority'] ?? 0);
    if(!$prov && !$city && !$isp) exit(json_encode(['code'=>-1,'msg'=>'至少填一个匹配条件']));
    if($id > 0) {
        DB::exec("UPDATE pay_iproute SET match_province=?, match_city=?, match_isp=?, channel_id=?, priority=? WHERE id=?",
            [$prov, $city, $isp, $cid, $pri, $id]);
    } else {
        DB::exec("INSERT INTO pay_iproute(uid, match_province, match_city, match_isp, channel_id, priority, status, addtime)
                  VALUES(0,?,?,?,?,?,1,NOW())", [$prov, $city, $isp, $cid, $pri]);
    }
    exit(json_encode(['code'=>0,'msg'=>'保存成功']));
}

if($my == 'delete') {
    $id = intval($_POST['id'] ?? 0);
    DB::exec("DELETE FROM pay_iproute WHERE id=? AND uid=0", [$id]);
    exit(json_encode(['code'=>0,'msg'=>'已删除']));
}

if($my == 'toggle') {
    $id = intval($_POST['id'] ?? 0);
    DB::exec("UPDATE pay_iproute SET status=1-status WHERE id=? AND uid=0", [$id]);
    exit(json_encode(['code'=>0,'msg'=>'已切换']));
}

if($my == 'test') {
    $ip = trim($_POST['ip'] ?? '');
    $geo = get_ip_geo($ip);
    exit(json_encode(['code'=>0, 'data'=>$geo]));
}

$rules = DB::query("SELECT r.*, c.name AS channel_name FROM pay_iproute r 
                    LEFT JOIN pay_channel c ON c.id=r.channel_id 
                    WHERE r.uid=0 ORDER BY r.priority DESC, r.id DESC");
$channels = DB::query("SELECT id, name FROM pay_channel WHERE status=1 ORDER BY id");
?>
<div id="content" class="app-content" role="main">
<div class="app-content-body">

<div class="bg-light lter b-b wrapper-md hidden-print">
  <h1 class="m-n font-thin h3">地区智能路由</h1>
</div>

<div class="wrapper-md control">

<div class="panel panel-info">
  <div class="panel-heading font-bold"><i class="fa fa-map-marker"></i> 说明</div>
  <div class="panel-body" style="font-size:13px;line-height:1.9;">
    <p>按用户 IP 归属地自动跳转到对应通道。让每个地区的用户付款时都走"本地商户"，降低风控。</p>
    <p><b>匹配优先级：</b>省+市+运营商 &gt; 省+市 &gt; 省 &gt; 市 &gt; 运营商 &gt; 全部。多个规则命中时按 <b>priority 数字大的先命中</b>。</p>
    <p><b>建议配置：</b></p>
    <ul>
      <li>广东 → 广东支付宝通道</li>
      <li>江苏 → 江苏支付宝通道</li>
      <li>浙江 → 浙江支付宝通道</li>
      <li>不填条件（兜底） → 默认通道</li>
    </ul>
  </div>
</div>

<div class="panel panel-default">
  <div class="panel-heading font-bold"><i class="fa fa-plus"></i> 添加/编辑规则</div>
  <div class="panel-body">
    <form class="form-inline" onsubmit="return false;">
      <input type="hidden" id="rule_id" value="0">
      <div class="form-group" style="margin-right:10px;">
        <label>省：</label>
        <input id="match_province" class="form-control" style="width:110px;" placeholder="如：广东">
      </div>
      <div class="form-group" style="margin-right:10px;">
        <label>市：</label>
        <input id="match_city" class="form-control" style="width:110px;" placeholder="如：深圳">
      </div>
      <div class="form-group" style="margin-right:10px;">
        <label>运营商：</label>
        <select id="match_isp" class="form-control">
          <option value="">不限</option>
          <option value="电信">电信</option>
          <option value="联通">联通</option>
          <option value="移动">移动</option>
          <option value="广电">广电</option>
          <option value="教育网">教育网</option>
        </select>
      </div>
      <div class="form-group" style="margin-right:10px;">
        <label>→ 通道：</label>
        <select id="channel_id" class="form-control" style="width:180px;">
          <option value="0">-- 不跳通道 --</option>
          <?php foreach($channels as $ch): ?>
            <option value="<?php echo $ch['id']; ?>"><?php echo htmlspecialchars($ch['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin-right:10px;">
        <label>优先级：</label>
        <input id="priority" class="form-control" style="width:70px;" type="number" value="0">
      </div>
      <button class="btn btn-primary" onclick="saveRule()"><i class="fa fa-save"></i> 保存</button>
      <button class="btn btn-default" onclick="resetForm()">重置</button>
    </form>
  </div>
</div>

<div class="panel panel-default">
  <div class="panel-heading font-bold"><i class="fa fa-flask"></i> 测试 IP 归属</div>
  <div class="panel-body">
    <form class="form-inline" onsubmit="return false;">
      <input id="test_ip" class="form-control" style="width:200px;" placeholder="输入 IP 如 8.8.8.8">
      <button class="btn btn-info" onclick="testIp()">查询归属</button>
      <span id="test_result" style="margin-left:15px;font-size:14px;"></span>
    </form>
  </div>
</div>

<div class="panel panel-default">
  <div class="panel-heading font-bold"><i class="fa fa-list"></i> 规则列表（<?php echo count($rules); ?>）</div>
  <div class="table-responsive">
    <table class="table table-striped">
      <thead><tr><th>ID</th><th>省</th><th>市</th><th>运营商</th><th>跳转通道</th><th>优先级</th><th>状态</th><th>操作</th></tr></thead>
      <tbody>
      <?php if(empty($rules)): ?>
        <tr><td colspan="8" style="text-align:center;color:#999;padding:30px;">暂无规则</td></tr>
      <?php else: foreach($rules as $r): ?>
        <tr>
          <td><?php echo $r['id']; ?></td>
          <td><?php echo $r['match_province'] ?: '*'; ?></td>
          <td><?php echo $r['match_city'] ?: '*'; ?></td>
          <td><?php echo $r['match_isp'] ?: '*'; ?></td>
          <td><?php echo $r['channel_name'] ? htmlspecialchars($r['channel_name']) : '-'; ?></td>
          <td><?php echo $r['priority']; ?></td>
          <td>
            <?php if($r['status']): ?>
              <span class="label label-success">启用</span>
            <?php else: ?>
              <span class="label label-default">停用</span>
            <?php endif; ?>
          </td>
          <td>
            <a href="javascript:;" onclick='editRule(<?php echo json_encode($r); ?>)' style="color:#1677ff;">编辑</a>
            <span style="color:#ddd;">|</span>
            <a href="javascript:;" onclick="toggleRule(<?php echo $r['id']; ?>)" style="color:#faad14;">切换</a>
            <span style="color:#ddd;">|</span>
            <a href="javascript:;" onclick="delRule(<?php echo $r['id']; ?>)" style="color:#e74c3c;">删除</a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

</div>
</div>

<script>
function saveRule() {
  const fd = new FormData();
  fd.append('id', document.getElementById('rule_id').value);
  fd.append('match_province', document.getElementById('match_province').value);
  fd.append('match_city', document.getElementById('match_city').value);
  fd.append('match_isp', document.getElementById('match_isp').value);
  fd.append('channel_id', document.getElementById('channel_id').value);
  fd.append('priority', document.getElementById('priority').value);
  fetch('?my=save', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
    alert(d.msg); if(d.code===0) location.reload();
  });
}
function editRule(r) {
  document.getElementById('rule_id').value = r.id;
  document.getElementById('match_province').value = r.match_province || '';
  document.getElementById('match_city').value = r.match_city || '';
  document.getElementById('match_isp').value = r.match_isp || '';
  document.getElementById('channel_id').value = r.channel_id;
  document.getElementById('priority').value = r.priority;
  window.scrollTo(0, 0);
}
function resetForm() {
  document.getElementById('rule_id').value = 0;
  document.getElementById('match_province').value = '';
  document.getElementById('match_city').value = '';
  document.getElementById('match_isp').value = '';
  document.getElementById('channel_id').value = 0;
  document.getElementById('priority').value = 0;
}
function toggleRule(id) {
  const fd = new FormData(); fd.append('id', id);
  fetch('?my=toggle', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{alert(d.msg); location.reload();});
}
function delRule(id) {
  if(!confirm('确定删除？')) return;
  const fd = new FormData(); fd.append('id', id);
  fetch('?my=delete', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{alert(d.msg); location.reload();});
}
function testIp() {
  const ip = document.getElementById('test_ip').value.trim();
  if(!ip) return;
  document.getElementById('test_result').innerHTML = '查询中...';
  const fd = new FormData(); fd.append('ip', ip);
  fetch('?my=test', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
    if(d.code === 0) {
      document.getElementById('test_result').innerHTML = 
        '<b>' + (d.data.province||'?') + '</b> / ' + (d.data.city||'?') + ' / ' + (d.data.isp||'?');
    } else {
      document.getElementById('test_result').innerHTML = '查询失败';
    }
  });
}
</script>
<?php include './foot.php'; ?>
