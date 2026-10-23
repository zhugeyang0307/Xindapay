<?php
require_once('../includes/common.php');
if($islogin != 1) exit("<script>location.href='../login.php';</script>");

$my = $_GET['my'] ?? '';

// 生成随机码 HM-XXXX-XXXX-XXXX
function genCode() {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // 去掉易混 O I 0 1
    $seg = function() use ($chars) {
        $s = '';
        for ($i=0; $i<4; $i++) $s .= $chars[random_int(0, strlen($chars)-1)];
        return $s;
    };
    return 'HM-'.$seg().'-'.$seg().'-'.$seg();
}

// ========== AJAX: 生成 ==========
if($my == 'gen') {
    $num    = max(1, min(500, intval($_POST['num'] ?? 1)));
    $days   = intval($_POST['days'] ?? 30);
    $remark = trim($_POST['remark'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $codes = [];
    for ($i=0; $i<$num; $i++) {
        $c = genCode();
        // 唯一性
        while ($DB->getColumn("SELECT COUNT(*) FROM pay_license WHERE code=:c", [':c'=>$c])) {
            $c = genCode();
        }
        $secret = substr(md5($c . mt_rand() . time()), 0, 32);
        $DB->exec("INSERT INTO pay_license (code, email, expire_time, remark, status, secret)
                   VALUES (:c, :e, NULL, :r, 0, :s)",
                  [':c'=>$c, ':e'=>$email, ':r'=>$remark, ':s'=>$secret]);
        $codes[] = $c;
    }
    exit(json_encode(['code'=>0, 'msg'=>'生成 '.count($codes).' 个', 'codes'=>$codes]));
}

// ========== AJAX: 续期 ==========
if($my == 'renew') {
    $id   = intval($_POST['id'] ?? 0);
    $days = intval($_POST['days'] ?? 30);
    if (!$id) exit(json_encode(['code'=>-1,'msg'=>'参数错误']));
    $row = $DB->getRow("SELECT l.*, u.username FROM pay_license l LEFT JOIN pay_user u ON l.uid = u.uid WHERE id=:id", [':id'=>$id]);
    if (!$row) exit(json_encode(['code'=>-1,'msg'=>'不存在']));
    $base = max(time(), strtotime($row['expire_time'] ?: 'now'));
    $newExp = date('Y-m-d H:i:s', $base + $days * 86400);
    $DB->exec("UPDATE pay_license SET expire_time=:e, is_trial=0, status=1 WHERE id=:id",
              [':e'=>$newExp, ':id'=>$id]);
    exit(json_encode(['code'=>0, 'msg'=>'续期至 '.$newExp]));
}

// ========== AJAX: 禁用/启用 ==========
if($my == 'toggle') {
    $id = intval($_POST['id'] ?? 0);
    $DB->exec("UPDATE pay_license SET status=1-status WHERE id=:id", [':id'=>$id]);
    exit(json_encode(['code'=>0,'msg'=>'已切换']));
}

// ========== AJAX: 解绑设备 ==========
if($my == 'unbind') {
    $id = intval($_POST['id'] ?? 0);
    $DB->exec("UPDATE pay_license SET device_id=NULL, device_info='', token=NULL WHERE id=:id", [':id'=>$id]);
    exit(json_encode(['code'=>0,'msg'=>'已解绑']));
}

// ========== AJAX: 删除 ==========
if($my == 'del') {
    $id = intval($_POST['id'] ?? 0);
    $DB->exec("DELETE FROM pay_license WHERE id=:id AND device_id IS NULL", [':id'=>$id]);
    exit(json_encode(['code'=>0,'msg'=>'已删除']));
}

// ========== 列表 ==========
$status = $_GET['status'] ?? '';
$kw     = trim($_GET['kw'] ?? '');
$where  = '1=1';
$params = [];
if ($status === '0') { $where .= " AND status=0"; }
if ($status === '1') { $where .= " AND status=1 AND is_trial=0"; }
if ($status === '2') { $where .= " AND is_trial=1"; }
if ($status === '9') { $where .= " AND status=2"; }
if ($kw) { $where .= " AND (code LIKE :kw OR device_info LIKE :kw2 OR remark LIKE :kw3)";
           $params[':kw'] = "%$kw%"; $params[':kw2'] = "%$kw%"; $params[':kw3'] = "%$kw%"; }

$list = $DB->getAll("SELECT * FROM pay_license WHERE $where ORDER BY id DESC LIMIT 500", $params);

// 统计
$stat = [
    'total'  => intval($DB->getColumn("SELECT COUNT(*) FROM pay_license")),
    'unused' => intval($DB->getColumn("SELECT COUNT(*) FROM pay_license WHERE status=0")),
    'trial'  => intval($DB->getColumn("SELECT COUNT(*) FROM pay_license WHERE is_trial=1 AND status=1")),
    'active' => intval($DB->getColumn("SELECT COUNT(*) FROM pay_license WHERE is_trial=0 AND status=1")),
    'expired'=> intval($DB->getColumn("SELECT COUNT(*) FROM pay_license WHERE expire_time < NOW() AND status=1")),
];
$price = $DB->getColumn("SELECT v FROM pay_config WHERE k='license_price_usdt'") ?: 30;
$days  = $DB->getColumn("SELECT v FROM pay_config WHERE k='license_days'") ?: 30;
?>
<?php include 'head.php'; ?>

<div class="container-fluid">

<h3>APK 授权码管理 <small style="font-size:14px;color:#888;">单价 <?php echo $price; ?> USDT / <?php echo $days; ?> 天</small></h3>

<div class="stat-row">
  <div class="stat-card"><div class="lbl">总数</div><div class="val"><?php echo $stat['total']; ?></div></div>
  <div class="stat-card"><div class="lbl">未使用</div><div class="val"><?php echo $stat['unused']; ?></div></div>
  <div class="stat-card"><div class="lbl">试用中</div><div class="val"><?php echo $stat['trial']; ?></div></div>
  <div class="stat-card"><div class="lbl">已激活</div><div class="val"><?php echo $stat['active']; ?></div></div>
  <div class="stat-card"><div class="lbl">已过期</div><div class="val"><?php echo $stat['expired']; ?></div></div>
</div>

<div class="card">
  <h4>生成激活码</h4>
  <div class="row">
    <div class="col-md-2">
      <label>数量</label>
      <input type="number" id="gen_num" class="form-control" value="1" min="1" max="500">
    </div>
    <div class="col-md-2">
      <label>天数</label>
      <input type="number" id="gen_days" class="form-control" value="<?php echo $days; ?>" min="1">
    </div>
    <div class="col-md-3">
      <label>备注（可选）</label>
      <input type="text" id="gen_remark" class="form-control" placeholder="如：微信-张三">
    </div>
    <div class="col-md-3">
      <label>商户邮箱（可选）</label>
      <input type="email" id="gen_email" class="form-control" placeholder="选了会自动发码">
    </div>
    <div class="col-md-2">
      <label>&nbsp;</label>
      <button class="btn btn-primary btn-block" onclick="genCodes()">生成</button>
    </div>
  </div>
  <div id="gen_result" style="margin-top:15px;"></div>
</div>

<div class="card">
  <form method="get" class="form-inline" style="margin-bottom:15px;">
    <label>状态：</label>
    <select name="status" class="form-control">
      <option value="">全部</option>
      <option value="0" <?php if($status==='0')echo 'selected';?>>未使用</option>
      <option value="2" <?php if($status==='2')echo 'selected';?>>试用中</option>
      <option value="1" <?php if($status==='1')echo 'selected';?>>已激活</option>
      <option value="9" <?php if($status==='9')echo 'selected';?>>已禁用</option>
    </select>
    &nbsp;<label>搜索：</label>
    <input type="text" name="kw" class="form-control" value="<?php echo htmlspecialchars($kw);?>" placeholder="码/设备/备注">
    &nbsp;<button class="btn btn-primary">筛选</button>
    <a href="?" class="btn btn-default">重置</a>
  </form>

  <table class="table table-hover table-striped">
    <thead>
      <tr>
        <th>ID</th><th>归属商户</th><th>激活码</th><th>状态</th><th>绑定设备</th>
        <th>到期时间</th><th>最后心跳</th><th>备注</th><th>操作</th>
      </tr>
    </thead>
    <tbody>
    <?php if(empty($list)): ?>
      <tr><td colspan="8" style="text-align:center;padding:40px;color:#999;">暂无数据</td></tr>
    <?php else: foreach($list as $r):
      if ($r['status']==2) $tag='<span class="tag tag-disabled">已禁用</span>';
      elseif ($r['status']==0) $tag='<span class="tag tag-unused">未使用</span>';
      elseif ($r['is_trial']) $tag='<span class="tag tag-trial">试用中</span>';
      elseif (strtotime($r['expire_time']) < time()) $tag='<span class="tag tag-expired">已过期</span>';
      else $tag='<span class="tag tag-active">已激活</span>';
    ?>
      <tr>
        <td><?php echo $r['id'];?></td>
        <td><span class="code-cell" onclick="copyText('<?php echo $r['code'];?>')"><?php echo $r['code'];?></span></td>
        <td><?php echo $tag;?></td>
        <td style="font-size:12px;"><?php echo htmlspecialchars($r['device_info'] ?: '-');?></td>
        <td style="font-size:12px;"><?php echo $r['expire_time'] ?: '-';?></td>
        <td style="font-size:12px;color:#999;"><?php echo $r['last_beat'] ?: '-';?></td>
        <td style="font-size:12px;"><?php echo htmlspecialchars($r['remark']);?></td>
        <td>
          <button class="btn btn-xs btn-default" onclick="renew(<?php echo $r['id'];?>)">续期</button>
          <button class="btn btn-xs btn-warning" onclick="toggle(<?php echo $r['id'];?>)">
            <?php echo $r['status']==2?'启用':'禁用';?>
          </button>
          <?php if($r['device_id']):?>
          <button class="btn btn-xs btn-info" onclick="unbind(<?php echo $r['id'];?>)">解绑</button>
          <?php else:?>
          <button class="btn btn-xs btn-danger" onclick="del(<?php echo $r['id'];?>)">删除</button>
          <?php endif;?>
        </td>
      </tr>
    <?php endforeach; endif;?>
    </tbody>
  </table>
</div>

</div>

<script src="/assets/vendor/jquery/3.4.1/jquery.min.js"></script>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.js"></script>
<script>
function genCodes(){
  var num = document.getElementById('gen_num').value;
  var days = document.getElementById('gen_days').value;
  var remark = document.getElementById('gen_remark').value;
  var email = document.getElementById('gen_email').value;
  var fd = new FormData();
  fd.append('num', num); fd.append('days', days);
  fd.append('remark', remark); fd.append('email', email);
  fetch('?my=gen', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
    if(d.code===0){
      var html = '<div style="background:#f6ffed;border:1px solid #b7eb8f;padding:12px;border-radius:6px;">';
      html += '<b>生成了 '+d.codes.length+' 个激活码（点击复制）：</b><br><br>';
      d.codes.forEach(c=>{
        html += '<span class="code-cell" onclick="copyText(\''+c+'\')" style="display:inline-block;margin:4px 6px;padding:6px 12px;background:#fff;border:1px dashed #c00;border-radius:4px;">'+c+'</span>';
      });
      html += '</div>';
      document.getElementById('gen_result').innerHTML = html;
      layer.msg('生成成功，点击码即可复制');
    } else {
      layer.msg(d.msg);
    }
  });
}
function copyText(t){
  var i = document.createElement('input');
  i.value = t; document.body.appendChild(i);
  i.select(); document.execCommand('copy');
  document.body.removeChild(i);
  layer.msg('已复制：'+t);
}
function renew(id){
  layer.prompt({title:'续期天数', value:<?php echo $days;?>}, function(v,idx){
    var fd = new FormData(); fd.append('id',id); fd.append('days',parseInt(v));
    fetch('?my=renew',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
      layer.msg(d.msg); if(d.code===0) setTimeout(()=>location.reload(),800);
    });
  });
}
function toggle(id){
  var fd = new FormData(); fd.append('id',id);
  fetch('?my=toggle',{method:'POST',body:fd}).then(r=>r.json()).then(d=>location.reload());
}
function unbind(id){
  if(!confirm('确定解绑该设备？解绑后该设备可以重新激活到其他码'))return;
  var fd = new FormData(); fd.append('id',id);
  fetch('?my=unbind',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    layer.msg(d.msg); setTimeout(()=>location.reload(),800);
  });
}
function del(id){
  if(!confirm('确定删除？'))return;
  var fd = new FormData(); fd.append('id',id);
  fetch('?my=del',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    layer.msg(d.msg); setTimeout(()=>location.reload(),800);
  });
}
</script>

