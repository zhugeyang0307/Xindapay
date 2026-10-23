<?php
require_once('../includes/common.php');
if($islogin != 1) exit("<script>location.href='./login.php';</script>");

$action = $_POST['action'] ?? $_GET['action'] ?? '';
header('Content-Type: application/json; charset=UTF-8');

// ───── AJAX 操作 ─────
if ($action) {
    $uid = (int)($_POST['uid'] ?? 0);
    if ($uid <= 0) exit(json_encode(['code'=>-1, 'msg'=>'uid 无效']));

    switch ($action) {
        case 'extend': // 手动续费
            $months = max(1, min(60, (int)($_POST['months'] ?? 1)));
            $extra  = max(0, min(100, (int)($_POST['extra']  ?? 0)));
            $row = $DB->getRow("SELECT service_expire, service_trial_end, apk_quota FROM pay_user WHERE uid=:u", [':u'=>$uid]);
            if (!$row) exit(json_encode(['code'=>-1, 'msg'=>'商户不存在']));
            $base = max(time(),
                $row['service_expire'] ? strtotime($row['service_expire']) : 0,
                $row['service_trial_end'] ? strtotime($row['service_trial_end']) : 0
            );
            $newExp = date('Y-m-d H:i:s', $base + $months * 30 * 86400);
            $newQ   = (int)$row['apk_quota'] + $extra;
            $DB->exec("UPDATE pay_user SET service_expire=:e, apk_quota=:q WHERE uid=:u",
                [':e'=>$newExp, ':q'=>$newQ, ':u'=>$uid]);
            // 同步所有 license
            $DB->exec("UPDATE pay_license SET expire_time=:e WHERE uid=:u AND status<>2",
                [':e'=>$newExp, ':u'=>$uid]);
            exit(json_encode(['code'=>1, 'msg'=>"续费成功：到期 {$newExp}，额度 {$newQ}", 'expire'=>$newExp, 'quota'=>$newQ]));

        case 'lock': // 禁用服务
            $DB->exec("UPDATE pay_user SET service_expire=DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE uid=:u", [':u'=>$uid]);
            exit(json_encode(['code'=>1, 'msg'=>'已锁定']));

        case 'unlock': // 恢复（+30 天）
            $newExp = date('Y-m-d H:i:s', time() + 30 * 86400);
            $DB->exec("UPDATE pay_user SET service_expire=:e WHERE uid=:u", [':e'=>$newExp, ':u'=>$uid]);
            exit(json_encode(['code'=>1, 'msg'=>"已解锁，到期 {$newExp}"]));

        case 'set_quota': // 直接设置额度
            $q = max(0, min(500, (int)($_POST['quota'] ?? 3)));
            $DB->exec("UPDATE pay_user SET apk_quota=:q WHERE uid=:u", [':q'=>$q, ':u'=>$uid]);
            exit(json_encode(['code'=>1, 'msg'=>"额度已设为 {$q}"]));
    }
    exit(json_encode(['code'=>-1, 'msg'=>'未知操作']));
}

// ───── 列表页 ─────
$kw = trim($_GET['kw'] ?? '');
$where = '1=1';
$params = [];
if ($kw !== '') {
    $where = '(uid=:kw OR email LIKE :kw2 OR phone LIKE :kw3 OR username LIKE :kw4)';
    $params = [':kw'=>$kw, ':kw2'=>"%{$kw}%", ':kw3'=>"%{$kw}%", ':kw4'=>"%{$kw}%"];
}

$users = $DB->getAll("SELECT uid, username, email, phone, cert, money, rate, service_expire, service_trial_end, apk_quota
                      FROM pay_user WHERE {$where} ORDER BY uid DESC LIMIT 500", $params);

// 附加：每个商户已激活设备数
foreach ($users as &$u) {
    $u['apk_used'] = (int)$DB->getColumn("SELECT COUNT(*) FROM pay_license WHERE uid=:u AND device_id IS NOT NULL AND device_id<>'' AND status<>2", [':u'=>$u['uid']]);
    $e = $u['service_expire'] ? strtotime($u['service_expire']) : 0;
    $t = $u['service_trial_end'] ? strtotime($u['service_trial_end']) : 0;
    $now = time();
    if ($e > $now)              $u['st'] = 'active';
    elseif ($t > $now)          $u['st'] = 'trial';
    elseif ($e > 0 || $t > 0)   $u['st'] = 'expired';
    else                        $u['st'] = 'none';
    $u['expire_show'] = $e > $t ? $u['service_expire'] : ($t ? $u['service_trial_end'] : '—');
}
unset($u);

$title = '系统服务管理';
include './head.php';
?>
<div class="container-fluid" style="padding-top:70px;">
  <div class="col-md-12 center-block" style="float:none;">

    <div class="panel panel-default">
      <div class="panel-heading"><h3 class="panel-title"><i class="fa fa-users"></i> 平台商户资产总览</h3></div>
      <div class="panel-body">
        <form method="GET" class="form-inline" style="margin-bottom:16px;">
          <div class="form-group">
            <input type="text" name="kw" class="form-control" placeholder="搜索 uid / 邮箱 / 手机 / 用户名" value="<?php echo htmlspecialchars($kw);?>" style="width:280px;">
          </div>
          <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> 搜索</button>
          <a href="./service.php" class="btn btn-default"><i class="fa fa-refresh"></i> 刷新</a>
          <span style="margin-left:16px;color:#888;">共 <?php echo count($users);?> 个商户</span>
        </form>

        <div class="table-responsive">
        <table class="table table-striped table-hover table-bordered">
          <thead>
            <tr>
              <th>专属 UID</th>
              <th>商户账号 / 联系方式</th>
              <th>账户余额</th>
              <th style="width: 180px;">操作</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($users as $u): 
              $prefix = $conf['uid_prefix'] ?? 'M';
          ?>
            <tr data-uid="<?php echo $u['uid'];?>">
              <td>
                  <span class="label label-primary" style="font-size:13px; padding:5px 10px; background:#3b82f6;">
                      <?php echo htmlspecialchars($prefix . $u['uid']);?>
                  </span>
              </td>
              <td>
                <div style="font-weight:bold; color:#1e293b;"><?php echo htmlspecialchars($u['username'] ?: '未设置账号');?></div>
                <small style="color:#64748b;"><?php echo htmlspecialchars($u['email'] ?: ($u['phone'] ?: '无联系方式'));?></small>
              </td>
              <td>
                  <span style="font-weight:900; font-size:15px; color:#e11d48;">¥<?php echo number_format((float)$u['money'], 2);?></span>
              </td>
              <td>
                <div class="btn-group btn-group-xs">
                  <?php if ($u['st'] === 'active' || $u['st'] === 'trial'): ?>
                    <button class="btn btn-warning" onclick="svcLock(<?php echo $u['uid'];?>)" title="封禁该商户"><i class="fa fa-lock"></i> 锁定</button>
                  <?php else: ?>
                    <button class="btn btn-success" onclick="svcUnlock(<?php echo $u['uid'];?>)" title="解封该商户"><i class="fa fa-unlock"></i> 解锁</button>
                  <?php endif; ?>
                  <a href="./ulist.php?kw=<?php echo $u['uid'];?>" class="btn btn-info" title="前往商户列表管理"><i class="fa fa-cog"></i> 管理</a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      </div>
    </div>

  </div>
</div>

<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.js"></script>
<script>
function ajax(action, data) {
    var fd = new FormData();
    fd.append('action', action);
    for (var k in data) fd.append(k, data[k]);
    return fetch('./service.php', {method:'POST', body:fd}).then(r=>r.json());
}

function svcExtend(uid) {
    layer.prompt({title:'续费月数（1-60）', value:'1', formType:2}, function(v, i){
        var m = parseInt(v);
        if (!m || m < 1) { layer.msg('月数无效'); return; }
        layer.close(i);
        layer.prompt({title:'增加设备数（0-100，可填0）', value:'0', formType:2}, function(v2, i2){
            var e = parseInt(v2) || 0;
            layer.close(i2);
            ajax('extend', {uid:uid, months:m, extra:e}).then(d=>{
                layer.alert(d.msg, {icon: d.code===1?1:2}, function(){ location.reload(); });
            });
        });
    });
}

function svcSetQuota(uid, cur) {
    layer.prompt({title:'直接设置额度（0-500）', value:cur, formType:2}, function(v, i){
        var q = parseInt(v);
        if (isNaN(q) || q < 0) { layer.msg('额度无效'); return; }
        layer.close(i);
        ajax('set_quota', {uid:uid, quota:q}).then(d=>{
            layer.alert(d.msg, {icon: d.code===1?1:2}, function(){ location.reload(); });
        });
    });
}

function svcLock(uid) {
    layer.confirm('确定锁定该商户服务吗？（到期日将设为昨天，支付+APK 立即失效）', {icon:3}, function(i){
        layer.close(i);
        ajax('lock', {uid:uid}).then(d=>{ layer.alert(d.msg, {icon:1}, ()=>location.reload()); });
    });
}

function svcUnlock(uid) {
    layer.confirm('确定解锁该商户吗？（将自动 +30 天服务期）', {icon:3}, function(i){
        layer.close(i);
        ajax('unlock', {uid:uid}).then(d=>{ layer.alert(d.msg, {icon:1}, ()=>location.reload()); });
    });
}
</script>
<?php include './foot.php'; ?>