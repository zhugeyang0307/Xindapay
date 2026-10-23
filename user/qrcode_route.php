<?php
include("../includes/common.php");
if($islogin2==1){}else exit("<script>location.href='./login.php';</script>");
$uid = (int)$userrow['uid'];
$title = '收款码路由';
$my = $_GET['my'] ?? '';

// 取本人 + 下级所有码
function _route_myqrs($DB, $uid) {
    $all = [(int)$uid];
    $q = [(int)$uid]; $d = 0;
    while ($q && $d < 3) {
        $nx = [];
        foreach ($q as $u) {
            $kids = $DB->getAll("SELECT uid FROM pay_user WHERE upid=:u AND sub_level>1", [':u'=>$u]);
            foreach ($kids as $k) { $all[] = (int)$k['uid']; $nx[] = (int)$k['uid']; }
        }
        $q = $nx; $d++;
    }
    $in = implode(',', $all);
    return $DB->getAll("SELECT id,uid,channel,remark,status,weight FROM pay_qrcode WHERE uid IN ($in) ORDER BY uid ASC, id ASC");
}

if ($my === 'save') {
    header('Content-Type: application/json; charset=UTF-8');
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $type = trim($_POST['match_type'] ?? '');
    $pri  = (int)($_POST['priority'] ?? 100);
    $remark = trim($_POST['remark'] ?? '');
    $status = (int)($_POST['status'] ?? 1);

    if ($name === '' || $type === '') exit(json_encode(['code'=>-1,'msg'=>'名称/类型不能为空']));

    $mv = [];
    if ($type === 'province') {
        $mv['provinces'] = array_values($_POST['provinces'] ?? []);
        if (empty($mv['provinces'])) exit(json_encode(['code'=>-1,'msg'=>'请至少选择一个省']));
    } elseif ($type === 'weight') {
        $ids = array_filter(array_map('intval', $_POST['qrcode_ids'] ?? []));
        if (count($ids) < 2) exit(json_encode(['code'=>-1,'msg'=>'权重模式至少选 2 个码']));
        $mv['qrcode_ids'] = array_values($ids);
    } elseif ($type === 'all') {
        $ids = array_filter(array_map('intval', $_POST['qrcode_ids'] ?? []));
        if (empty($ids)) exit(json_encode(['code'=>-1,'msg'=>'请至少选一个码']));
        $mv['qrcode_ids'] = array_values($ids);
    } else {
        exit(json_encode(['code'=>-1,'msg'=>'暂不支持的类型']));
    }

    $json = json_encode($mv, JSON_UNESCAPED_UNICODE);
    if ($id > 0) {
        $DB->exec("UPDATE pay_qrcode_route SET name=?, match_type=?, match_value=?, priority=?, status=?, remark=? WHERE id=? AND uid=?",
            [$name, $type, $json, $pri, $status, $remark, $id, $uid]);
    } else {
        $DB->exec("INSERT INTO pay_qrcode_route (uid, name, match_type, match_value, priority, status, remark)
                   VALUES (?,?,?,?,?,?,?)", [$uid, $name, $type, $json, $pri, $status, $remark]);
        $id = $DB->lastInsertId();
    }
    exit(json_encode(['code'=>0, 'msg'=>'保存成功', 'id'=>$id]));
}

if ($my === 'del') {
    header('Content-Type: application/json; charset=UTF-8');
    $id = (int)($_POST['id'] ?? 0);
    $DB->exec("DELETE FROM pay_qrcode_route WHERE id=? AND uid=?", [$id, $uid]);
    exit(json_encode(['code'=>0, 'msg'=>'已删除']));
}

if ($my === 'toggle') {
    header('Content-Type: application/json; charset=UTF-8');
    $id = (int)($_POST['id'] ?? 0);
    $DB->exec("UPDATE pay_qrcode_route SET status=1-status WHERE id=? AND uid=?", [$id, $uid]);
    exit(json_encode(['code'=>0, 'msg'=>'已切换']));
}

$editId = (int)($_GET['edit'] ?? 0);
$edit = $editId ? $DB->getRow("SELECT * FROM pay_qrcode_route WHERE id=? AND uid=?", [$editId, $uid]) : null;
$rules = $DB->getAll("SELECT * FROM pay_qrcode_route WHERE uid=? ORDER BY priority ASC, id DESC", [$uid]) ?: [];
$qrs = _route_myqrs($DB, $uid);

$typeLabels = ['province'=>'按省份','weight'=>'按权重','all'=>'全部（兜底）'];

include './head.php';
?>
<style>
.rr-card{background:#fff;border-radius:14px;padding:22px;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:20px;}
table.rr{width:100%;border-collapse:collapse;font-size:13px;}
table.rr th,table.rr td{padding:10px;border-bottom:1px solid #eef2f7;text-align:left;}
table.rr th{background:#f7fafd;color:#555;font-weight:600;}
.tag{display:inline-block;padding:2px 8px;border-radius:8px;font-size:11px;font-weight:700;}
.t-on{background:#d9f7be;color:#389e0d;}
.t-off{background:#fff1f0;color:#cf1322;}
.t-blue{background:#dbeafe;color:#1e40af;}
.b-xs{padding:4px 12px;font-size:12px;border-radius:6px;text-decoration:none;display:inline-block;margin-right:4px;font-weight:600;border:none;cursor:pointer;color:#fff;}
.b-blue{background:#1677ff;}
.b-green{background:#07c160;}
.b-red{background:#e74c3c;}
.b-gray{background:#6b7280;}
.qr-check{display:inline-block;padding:4px 10px;margin:3px;background:#f1f5f9;border-radius:6px;cursor:pointer;font-size:12px;}
.qr-check input{margin-right:4px;}
.qr-check.on{background:#dbeafe;color:#1e40af;}
</style>
<div id="content" class="app-content" role="main">
<div class="app-content-body">
<div class="bg-light lter b-b wrapper-md">
  <h1 class="m-n font-thin h3"><i class="fa fa-share-alt"></i> 收款码路由规则</h1>
</div>
<div class="wrapper-md">

<div class="rr-card">
  <div style="display:flex;justify-content:space-between;align-items:center;">
    <div style="color:#555;">
      <b>共 <?= count($rules); ?> 条规则</b>
      <span style="color:#888;font-size:12px;margin-left:10px;">优先级越小越先匹配；命中后按规则选码；未命中走原逻辑（本人优先→下级）</span>
    </div>
    <a href="?edit=0" class="b-xs b-green" style="padding:10px 22px;font-size:14px;">➕ 新增规则</a>
  </div>
</div>

<div class="rr-card">
  <?php if (!$rules): ?>
    <div style="text-align:center;padding:40px;color:#999;">暂无规则</div>
  <?php else: ?>
  <table class="rr">
    <thead><tr><th>ID</th><th>优先级</th><th>规则名</th><th>类型</th><th>匹配值</th><th>状态</th><th>操作</th></tr></thead>
    <tbody>
    <?php foreach ($rules as $r):
      $mv = json_decode($r['match_value'], true) ?: [];
      $desc = '';
      if ($r['match_type'] === 'province') $desc = implode('、', $mv['provinces'] ?? []);
      elseif ($r['match_type'] === 'weight') $desc = count($mv['qrcode_ids'] ?? []) . ' 个码加权随机';
      elseif ($r['match_type'] === 'all') $desc = count($mv['qrcode_ids'] ?? []) . ' 个码';
    ?>
      <tr>
        <td><b><?= $r['id']; ?></b></td>
        <td><?= (int)$r['priority']; ?></td>
        <td><?= htmlspecialchars($r['name']); ?></td>
        <td><span class="tag t-blue"><?= $typeLabels[$r['match_type']] ?? $r['match_type']; ?></span></td>
        <td style="font-size:12px;color:#666;"><?= htmlspecialchars($desc); ?></td>
        <td><?= (int)$r['status'] === 1 ? '<span class="tag t-on">启用</span>' : '<span class="tag t-off">停用</span>'; ?></td>
        <td>
          <a class="b-xs b-blue" href="?edit=<?= $r['id']; ?>">编辑</a>
          <button class="b-xs <?= (int)$r['status']===1?'b-gray':'b-green'; ?>" onclick="tg(<?= $r['id']; ?>)"><?= (int)$r['status']===1?'停用':'启用'; ?></button>
          <button class="b-xs b-red" onclick="del(<?= $r['id']; ?>, '<?= htmlspecialchars($r['name'], ENT_QUOTES); ?>')">删除</button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php if ($edit || $editId === 0): ?>
<div class="rr-card" id="editForm">
  <h4 style="margin-top:0;"><?= $edit ? '编辑规则 #' . $editId : '新增规则'; ?></h4>
  <?php
    $cur_mv = $edit ? (json_decode($edit['match_value'], true) ?: []) : [];
    $cur_type = $edit['match_type'] ?? 'province';
    $cur_pri = $edit['priority'] ?? 100;
    $cur_status = $edit['status'] ?? 1;
    $cur_name = $edit['name'] ?? '';
    $cur_remark = $edit['remark'] ?? '';
    $cur_ids = $cur_mv['qrcode_ids'] ?? [];
    $cur_provs = $cur_mv['provinces'] ?? [];
  ?>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
    <div>
      <label style="font-size:12px;color:#666;">规则名 *</label>
      <input id="r_name" class="form-control" placeholder="如：广东省优先" value="<?= htmlspecialchars($cur_name); ?>">
    </div>
    <div>
      <label style="font-size:12px;color:#666;">优先级（越小越先匹配）</label>
      <input id="r_pri" type="number" class="form-control" value="<?= (int)$cur_pri; ?>">
    </div>
  </div>

  <div style="margin-top:12px;">
    <label style="font-size:12px;color:#666;">匹配类型</label>
    <select id="r_type" class="form-control" onchange="showTypePanel()">
      <option value="province" <?= $cur_type==='province'?'selected':''; ?>>按省份</option>
      <option value="weight" <?= $cur_type==='weight'?'selected':''; ?>>按权重（多码加权随机）</option>
      <option value="all" <?= $cur_type==='all'?'selected':''; ?>>全部（兜底）</option>
    </select>
  </div>

  <!-- 省份面板 -->
  <div id="panel_province" style="margin-top:14px;display:<?= $cur_type==='province'?'block':'none'; ?>;">
    <label style="font-size:12px;color:#666;">选择省份（可多选）</label>
    <div id="provBox" style="margin-top:6px;display:flex;flex-wrap:wrap;gap:6px;">
      <?php foreach (['北京','天津','河北','山西','内蒙古','辽宁','吉林','黑龙江','上海','江苏','浙江','安徽','福建','江西','山东','河南','湖北','湖南','广东','广西','海南','重庆','四川','贵州','云南','西藏','陕西','甘肃','青海','宁夏','新疆','香港','澳门','台湾'] as $pv):
        $on = in_array($pv, $cur_provs);
      ?>
        <label class="qr-check <?= $on?'on':''; ?>">
          <input type="checkbox" class="prov-cb" value="<?= $pv; ?>" <?= $on?'checked':''; ?> onchange="this.parentNode.classList.toggle('on',this.checked)"><?= $pv; ?>
        </label>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- 选码面板 -->
  <div id="panel_qr" style="margin-top:14px;display:<?= $cur_type!=='province'?'block':'none'; ?>;">
    <label style="font-size:12px;color:#666;">选择收款码（勾选表示该规则的候选池）</label>
    <div style="margin-top:6px;max-height:260px;overflow:auto;border:1px solid #eef2f7;border-radius:8px;padding:8px;">
      <?php if (!$qrs): ?>
        <div style="text-align:center;color:#aaa;padding:20px;">暂无收款码</div>
      <?php else: foreach ($qrs as $q):
        $on = in_array((int)$q['id'], array_map('intval',$cur_ids));
      ?>
        <label class="qr-check <?= $on?'on':''; ?>" title="uid=<?= $q['uid']; ?> <?= htmlspecialchars($q['remark'] ?: '未命名'); ?>">
          <input type="checkbox" class="qr-cb" value="<?= (int)$q['id']; ?>" <?= $on?'checked':''; ?> onchange="this.parentNode.classList.toggle('on',this.checked)">
          #<?= $q['id']; ?> · uid=<?= $q['uid']; ?> · <?= htmlspecialchars($q['channel']); ?> · <?= htmlspecialchars($q['remark'] ?: '—'); ?> · 权重<?= (int)$q['weight']; ?>
        </label>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <div style="margin-top:12px;">
    <label style="font-size:12px;color:#666;">状态</label>
    <select id="r_status" class="form-control" style="width:180px;display:inline-block;margin-left:8px;">
      <option value="1" <?= $cur_status==1?'selected':''; ?>>启用</option>
      <option value="0" <?= $cur_status==0?'selected':''; ?>>停用</option>
    </select>
  </div>

  <div style="margin-top:12px;">
    <label style="font-size:12px;color:#666;">备注（选填）</label>
    <input id="r_remark" class="form-control" value="<?= htmlspecialchars($cur_remark); ?>">
  </div>

  <div style="margin-top:18px;">
    <button class="b-xs b-green" style="padding:10px 32px;font-size:14px;" onclick="saveRule(<?= (int)$editId; ?>)">💾 保存</button>
    <?php if ($editId): ?>
      <a class="b-xs b-gray" style="padding:10px 22px;font-size:14px;margin-left:8px;" href="./qrcode_route.php">取消编辑</a>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

</div>
</div>
</div>
<script>
function showTypePanel(){
  var t = document.getElementById('r_type').value;
  document.getElementById('panel_province').style.display = (t==='province') ? 'block' : 'none';
  document.getElementById('panel_qr').style.display = (t==='province') ? 'none' : 'block';
}
function saveRule(id){
  var name = document.getElementById('r_name').value.trim();
  var type = document.getElementById('r_type').value;
  var pri  = document.getElementById('r_pri').value || 100;
  var status = document.getElementById('r_status').value;
  var remark = document.getElementById('r_remark').value.trim();
  if(!name) return alert('请填规则名');

  var fd = new FormData();
  fd.append('id', id);
  fd.append('name', name);
  fd.append('match_type', type);
  fd.append('priority', pri);
  fd.append('status', status);
  fd.append('remark', remark);
  if (type==='province'){
    document.querySelectorAll('.prov-cb:checked').forEach(function(c){ fd.append('provinces[]', c.value); });
  } else {
    document.querySelectorAll('.qr-cb:checked').forEach(function(c){ fd.append('qrcode_ids[]', c.value); });
  }
  fetch('./qrcode_route.php?my=save', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
    alert(d.msg);
    if (d.code===0) location.href = './qrcode_route.php';
  }).catch(e=>alert('网络错误：'+e));
}
function del(id, name){
  if(!confirm('确定删除规则「'+name+'」？')) return;
  var fd = new FormData(); fd.append('id', id);
  fetch('./qrcode_route.php?my=del', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
    alert(d.msg); if(d.code===0) location.reload();
  });
}
function tg(id){
  var fd = new FormData(); fd.append('id', id);
  fetch('./qrcode_route.php?my=toggle', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
    if(d.code===0) location.reload();
  });
}
</script>
<?php include './foot.php'; ?>