<?php
$is_defend = true;
include("../includes/common.php");

if($islogin2 != 1) {
    if (isset($_GET['my']) || isset($_POST['uid'])) {
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode(['code'=>-1, 'msg'=>'登录已过期，请刷新后台重新登录']));
    }
    exit("<script>location.href='./login.php';</script>");
}



if(isset($_GET['my']) && $_GET['my'] === 'get_record') {
    header('Content-Type: application/json; charset=utf-8');
    $targetUid = (int)($_GET['uid'] ?? 0);
    $target = $DB->getRow("SELECT uid FROM pay_user WHERE uid=:u AND upid=:p", [':u'=>$targetUid, ':p'=>$uid]);
    if (!$target) exit(json_encode(['code'=>-1, 'msg'=>'该商户不是您的下级']));
    $records = $DB->getAll("SELECT * FROM pay_record WHERE uid=:u ORDER BY id DESC LIMIT 30", [':u'=>$targetUid]);
    foreach ($records as &$r) {
        $r['action_str'] = ((int)$r['action'] === 1) ? '增加' : '扣减';
        $r['color'] = ((int)$r['action'] === 1) ? '#07c160' : '#e74c3c';
    }
    exit(json_encode(['code'=>0, 'data'=>$records]));
}

// ===== 处理资金转动开关 =====
if (isset($_POST['act']) && $_POST['act'] == 'toggleFlow') {
    header('Content-Type: application/json; charset=utf-8');
    $targetUid = (int)($_GET['uid'] ?? 0); // 补上获取 UID 的逻辑
    $target = $DB->getRow("SELECT uid, upid FROM pay_user WHERE uid=:u", [':u'=>$targetUid]);
    
    // 校验目标是否存在，且是不是当前登录人的下级
    $myUid = (int)$userrow['uid'];
    if (!$target || (int)$target['upid'] !== $myUid) {
        exit(json_encode(['code'=>-1, 'msg'=>'无权操作该商户']));
    }
    
    $flow = isset($_POST['flow']) && $_POST['flow'] == 1 ? 1 : 0;
    $DB->exec("UPDATE pay_user SET money_flow=:f WHERE uid=:u", [':f'=>$flow, ':u'=>$targetUid]);
    exit(json_encode(['code'=>0, 'msg'=>'资金转动状态已更新', 'flow'=>$flow]));
}

// ===== 处理加/减余额 AJAX 请求 =====
if(isset($_GET['my']) && in_array($_GET['my'], ['add_money', 'reduce_money'])) {
    header('Content-Type: application/json; charset=utf-8');
    $targetUid = (int)($_POST['target_uid'] ?? 0);
    $money = (float)($_POST['money'] ?? 0);
    $action = $_GET['my']; // add_money 或 reduce_money
    
    if ($targetUid <= 0 || $money <= 0) {
        exit(json_encode(['code'=>-1, 'msg'=>'参数错误']));
    }
    
    // 校验目标是否为自己的下级
    // 这里简化处理：直接查上级
    $target = $DB->getRow("SELECT uid, money FROM pay_user WHERE uid=:u AND upid=:p", [':u'=>$targetUid, ':p'=>$uid]);
    if (!$target) {
        exit(json_encode(['code'=>-1, 'msg'=>'该商户不是您的下级']));
    }
    
    if ($action === 'add_money') {
        // 平台不碰资金，直接给下级加额度
        $DB->exec("UPDATE pay_user SET money = money + :m WHERE uid=:u", [':m'=>$money, ':u'=>$targetUid]);
        exit(json_encode(['code'=>0, 'msg'=>'增加余额成功']));
    } else {
        if ($target['money'] < $money) {
            exit(json_encode(['code'=>-1, 'msg'=>'下级余额不足']));
        }
        $DB->exec("UPDATE pay_user SET money = money - :m WHERE uid=:u", [':m'=>$money, ':u'=>$targetUid]);
        exit(json_encode(['code'=>0, 'msg'=>'扣减余额成功']));
    }
}

// 允许所有已登录商户访问下级编辑页，按实际上级/下级关系做权限校验
$uid = (int)$userrow['uid'];
$myLevel = (int)$userrow['sub_level'];

$targetUid = (int)($_GET['uid'] ?? $_POST['uid'] ?? 0);
if ($targetUid <= 0) exit("<script>alert('参数错误');location.href='./sub_list.php';</script>");

// 校验目标是我下级的链上成员（最多 2 层下）
function isMyDescendant($DB, $myUid, $targetUid, $maxDepth = 2) {
    $cur = $targetUid;
    for ($i = 0; $i < $maxDepth; $i++) {
        $upid = (int)$DB->getColumn("SELECT upid FROM pay_user WHERE uid=:u", [':u'=>$cur]);
        if ($upid === $myUid) return true;
        if ($upid === 0) return false;
        $cur = $upid;
    }
    return false;
}
if (!isMyDescendant($DB, $uid, $targetUid)) {
    exit("<script>alert('无权操作该商户');location.href='./sub_list.php';</script>");
}

$target = $DB->getRow("SELECT * FROM pay_user WHERE uid=:u", [':u'=>$targetUid]);
if (!$target) exit("<script>alert('商户不存在');location.href='./sub_list.php';</script>");

// ───── 提交操作 ─────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');
    $act = $_POST['act'] ?? '';
    
    if ($act === 'save') {
        $rate = (float)($_POST['rate'] ?? 0);
        $pay  = (int)($_POST['pay'] ?? 1) === 1 ? 1 : 0;
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if ($rate < 0 || $rate > 50) exit(json_encode(['code'=>-1, 'msg'=>'分润 0-50']));
        
        $DB->exec("UPDATE pay_user SET rate=:r, pay=:p, email=:e, phone=:ph WHERE uid=:u",
            [':r'=>$rate, ':p'=>$pay, ':e'=>$email, ':ph'=>$phone, ':u'=>$targetUid]);
        exit(json_encode(['code'=>1, 'msg'=>'已保存']));
    }
    
    if ($act === 'reset_pwd') {
        $newPwd = trim($_POST['new_pwd'] ?? '');
        if (strlen($newPwd) < 6) exit(json_encode(['code'=>-1, 'msg'=>'密码至少 6 位']));
        $hash = getMd5Pwd($newPwd, $targetUid);
        $DB->exec("UPDATE pay_user SET pwd=:p WHERE uid=:u", [':p'=>$hash, ':u'=>$targetUid]);
        exit(json_encode(['code'=>1, 'msg'=>"新密码：{$newPwd}"]));
    }
    
    if ($act === 'add_money') {
        @file_put_contents('/tmp/add_money_debug.log', date('Y-m-d H:i:s') . " | Req Add Money: uid={$uid} target={$targetUid} amount=" . ($_POST['amount'] ?? 0) . "\n", FILE_APPEND);
        $amount = (float)($_POST['amount'] ?? 0);
        $remark = trim($_POST['remark'] ?? '');
        if ($amount <= 0) exit(json_encode(['code'=>-1, 'msg'=>'金额必须大于 0']));
        if ($amount > 100000) exit(json_encode(['code'=>-1, 'msg'=>'单次上限 100000']));
        
        // 检查上级余额
        
        
        
        // 从上级扣
        
        // 给下级加
        changeUserMoney($targetUid, $amount, true, '预充值['.$uid.']'.($remark?'：'.$remark:''), null);
        
        $new = (float)$DB->getColumn("SELECT money FROM pay_user WHERE uid=:u", [':u'=>$targetUid]);
        $myNew = (float)$DB->getColumn("SELECT money FROM pay_user WHERE uid=:u", [':u'=>$uid]);
        exit(json_encode(['code'=>1, 'msg'=>"✅ 已给下级充值 ¥{$amount}\n你的余额：¥{$myNew}\n下级余额：¥{$new}"]));
    }

    if ($act === 'sub_money') {
        $amount = (float)($_POST['amount'] ?? 0);
        $remark = trim($_POST['remark'] ?? '上级扣款');
        if ($amount <= 0) exit(json_encode(['code'=>-1, 'msg'=>'金额必须大于 0']));
        
        $old = (float)$target['money'];
        if ($old < $amount) exit(json_encode(['code'=>-1, 'msg'=>"下级余额不足（当前 ¥{$old}）"]));
        changeUserMoney($targetUid, $amount, false, '预充值退回：' . $remark, null);
        $new = (float)$DB->getColumn("SELECT money FROM pay_user WHERE uid=:u", [':u'=>$targetUid]);
        exit(json_encode(['code'=>1, 'msg'=>"扣款成功：¥{$old} → ¥{$new}"]));
    }

    if ($act === 'delete') {
        // 只有上级的直属下级能删
        if ((int)$target['upid'] !== $uid) exit(json_encode(['code'=>-1, 'msg'=>'只能删除直接下级']));
        if ((int)$target['money'] > 0) exit(json_encode(['code'=>-1, 'msg'=>'下级余额不为 0，无法删除']));
        if ((int)$DB->getColumn("SELECT COUNT(*) FROM pay_user WHERE upid=:u", [':u'=>$targetUid])) {
            exit(json_encode(['code'=>-1, 'msg'=>'该下级还有下级，无法删除']));
        }
        $DB->exec("DELETE FROM pay_user WHERE uid=:u", [':u'=>$targetUid]);
        exit(json_encode(['code'=>1, 'msg'=>'已删除', 'redir'=>1]));
    }
    
    exit(json_encode(['code'=>-1, 'msg'=>'未知操作']));
}

$title = '编辑下级';
include './head.php'; ?>
<style>
.app-sub-edit-page {
  background: linear-gradient(180deg, #f6f9ff 0%, #f3f7fb 100%);
}
.app-sub-edit-page .wrapper-md {
  padding-top: 16px !important;
  padding-bottom: 18px !important;
}
.app-sub-edit-page .bg-light.lter.b-b.wrapper-md {
  background: transparent !important;
  border: 0 !important;
  padding-bottom: 8px !important;
}
.app-sub-edit-page .bg-light.lter.b-b.wrapper-md h1 {
  font-size: 28px !important;
  font-weight: 800 !important;
  color: #0f172a !important;
}
.sub-edit-shell {
  background: #fff;
  border-radius: 18px;
  border: 1px solid #edf3fb;
  box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
  overflow: hidden;
}
.sub-edit-body {
  padding: 20px !important;
}
.sub-header-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  padding: 18px 20px;
  background: linear-gradient(135deg, #f0f6ff 0%, #eef8ff 100%);
  border: 1px solid #dfeeff;
  border-radius: 16px;
  margin-bottom: 20px;
}
.sub-header-card .user-meta {
  display: flex;
  align-items: center;
  gap: 12px;
}
.sub-header-card .avatar {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  background: linear-gradient(135deg, #1677ff, #0d5cd6);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
}
.sub-header-card .money {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  color: #334155;
}
.sub-header-card .money b {
  font-size: 20px;
  color: #1677ff;
}
.sub-panel {
  margin-top: 16px;
  border: 1px solid #e4e7ed;
  border-left: 4px solid #1677ff;
  border-radius: 16px;
  background: #fff;
  box-shadow: none;
  overflow: hidden;
}
.sub-panel .panel-heading {
  background: linear-gradient(180deg, #f9fbff 0%, #ffffff 100%) !important;
  border-bottom: 1px solid #edf2f7 !important;
  color: #0f172a !important;
  font-weight: 800 !important;
  padding: 14px 18px !important;
}
.sub-panel .panel-body {
  padding: 18px !important;
}
.custom-switch { display: inline-flex; align-items: center; cursor: pointer; vertical-align: middle; margin-bottom: 0; }
.custom-slider-bg { width: 68px; height: 34px; border-radius: 34px; background-color: #dcdcdc; position: relative; transition: background-color 0.3s; }
.custom-slider-bg.checked { background-color: #07c160; }
.custom-knob { width: 26px; height: 26px; border-radius: 50%; background-color: #fff; position: absolute; top: 4px; left: 4px; transition: transform 0.3s; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
.custom-slider-bg.checked .custom-knob { transform: translateX(34px); }
.flow-text { font-size: 15px; font-weight: 600; vertical-align: middle; margin-left: 12px; }
.form-group label {
  display: block;
  color: #334155;
  font-weight: 700;
  margin-bottom: 8px;
}
.form-control {
  border: 1px solid #dfe8f3 !important;
  border-radius: 10px !important;
  box-shadow: none !important;
  height: 40px !important;
}
.form-control:focus {
  border-color: #7bb6ff !important;
  box-shadow: 0 0 0 3px rgba(22, 119, 255, 0.08) !important;
}
.app-sub-edit-page .btn {
  border-radius: 10px !important;
  font-weight: 700 !important;
  padding: 9px 18px !important;
}
.app-sub-edit-page .btn-primary {
  background: linear-gradient(135deg,#1677ff,#0d5cd6) !important;
  border: none !important;
  box-shadow: 0 8px 18px rgba(22, 119, 255, 0.16) !important;
}
.app-sub-edit-page .btn-success {
  background: linear-gradient(135deg,#10b981,#0ea272) !important;
  border: none !important;
  box-shadow: 0 8px 18px rgba(16, 185, 129, 0.16) !important;
}
.app-sub-edit-page .btn-danger {
  background: linear-gradient(135deg,#ef4444,#dc2626) !important;
  border: none !important;
}
@media (max-width: 768px) {
  .app-sub-edit-page .bg-light.lter.b-b.wrapper-md h1 { font-size: 26px !important; }
  .sub-header-card { padding: 16px 14px; }
  .sub-edit-body { padding: 14px !important; }
}
</style>
<div id="content" class="app-content app-sub-edit-page" role="main">
  <div class="app-content-body">
    <div class="bg-light lter b-b wrapper-md">
      <h1 class="m-n font-thin h3"><i class="fa fa-edit"></i> 编辑下级 [UID <?php echo $targetUid; ?>]</h1>
    </div>
    <div class="wrapper-md">
      <div class="sub-edit-shell">
        <div class="sub-edit-body">
          <div class="sub-header-card">
            <div class="user-meta">
              <div class="avatar"><?php echo mb_substr($target['username'] ?: 'U', 0, 1); ?></div>
              <div>
                <div style="font-size:13px;color:#64748b;">账号</div>
                <div style="font-size:18px;font-weight:800;color:#0f172a;"><?php echo htmlspecialchars($target['username']); ?></div>
              </div>
            </div>
            <div class="money">
              <span class="label label-primary" style="font-size:12px; padding:5px 10px; border-radius:999px;">L<?php echo (int)$target['sub_level']; ?></span>
              <div>余额：<b>¥<?php echo number_format((float)$target['money'], 2); ?></b></div>
              <a href="javascript:;" onclick="showRecordList()" style="font-size:13px; color:#1677ff; text-decoration:none;">查看明细</a>
            </div>
          </div>

          <div class="sub-panel panel panel-default">
            <div class="panel-heading"><i class="fa fa-cog"></i> 资金转动开关</div>
            <div class="panel-body">
              <p class="text-muted">开启后：下级码商收款，扣除的本金将自动转入您的账户。<br>关闭后：只扣下级码商的余额，您不会获得下级代收的本金。</p>
              <div class="custom-switch" onclick="toggleFlowUI()">
                  <div class="custom-slider-bg <?php echo ((int)$target['money_flow'] === 1 || $target['money_flow'] === null) ? 'checked' : ''; ?>" id="flowSliderBg">
                      <div class="custom-knob"></div>
                  </div>
              </div>
              <span id="flowStatusText" class="flow-text" style="color:#07c160;">
                  <?php echo ((int)$target['money_flow'] === 1 || $target['money_flow'] === null) ? '已开启（资金自动流转）' : '已关闭（资金不流转）'; ?>
              </span>
            </div>
          </div>

          <script>
          var isFlowOn = <?php echo ((int)$target['money_flow'] === 1 || $target['money_flow'] === null) ? 'true' : 'false'; ?>;
          function toggleFlowUI() {
              isFlowOn = !isFlowOn;
              var bg = document.getElementById('flowSliderBg');
              var txt = document.getElementById('flowStatusText');
              
              if (isFlowOn) {
                  bg.classList.add('checked');
                  txt.innerText = '已开启（资金自动流转）';
                  txt.style.color = '#07c160';
              } else {
                  bg.classList.remove('checked');
                  txt.innerText = '已关闭（资金不流转）';
                  txt.style.color = '#666';
              }
              
              $.post('sub_edit.php?uid=<?php echo $targetUid; ?>', {act: 'toggleFlow', flow: isFlowOn ? 1 : 0}, function(res) {
                  if(res.code !== 0) {
                      alert(res.msg);
                      toggleFlowUI();
                  }
              }, 'json');
          }
          </script>

          <div class="row" style="margin-top:20px;">
            <div class="col-md-6">
              <div class="form-group" style="margin-bottom: 20px;">
                <label>分润比例 (%)，下级收款时按比例分给我</label>
                <input type="number" step="0.01" id="rate" class="form-control" value="<?php echo (float)$target['rate']; ?>">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group" style="margin-bottom: 20px;">
                <label>收款权限</label>
                <select id="pay" class="form-control">
                  <option value="1" <?php if((int)$target['pay']===1) echo 'selected'; ?>>✅ 开启（下级可以收款）</option>
                  <option value="0" <?php if((int)$target['pay']===0) echo 'selected'; ?>>⛔ 关闭（下级无法收款）</option>
                </select>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="form-group" style="margin-bottom: 20px;">
                <label>邮箱</label>
                <input type="email" id="email" class="form-control" value="<?php echo htmlspecialchars($target['email']); ?>">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group" style="margin-bottom: 20px;">
                <label>手机号</label>
                <input type="text" id="phone" class="form-control" value="<?php echo htmlspecialchars($target['phone']); ?>">
              </div>
            </div>
          </div>

          <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eef2f7; display:flex; flex-wrap:wrap; gap:10px;">
            <button class="btn btn-primary" onclick="doSave()"><i class="fa fa-save"></i> 保存修改</button>
            <button class="btn btn-success" onclick="doResetPwd()"><i class="fa fa-key"></i> 重置密码</button>
            <button class="btn btn-success" onclick="openMoneyPanel('add')"><i class="fa fa-plus"></i> 增加余额</button>
            <button class="btn btn-danger" onclick="openMoneyPanel('sub')"><i class="fa fa-minus"></i> 减少余额</button>
            <?php if ((int)$target['upid'] === $uid): ?>
            <button class="btn btn-default" onclick="doDelete()" style="margin-left:auto; color:#e74c3c; border-color:#e74c3c; background:#fff;"> <i class="fa fa-trash"></i> 删除该下级</button>
            <?php endif; ?>
          </div>

          <div id="moneyPanel" style="display: none; margin-top: 20px; padding: 18px; background: #fafbfc; border: 1px solid #e2e8f0; border-radius: 16px;">
            <h4 id="moneyPanelTitle" style="margin-top: 0; margin-bottom: 12px; font-size: 16px; font-weight: 800; color: #0f172a;">调整余额</h4>
            <div style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
              <div style="flex: 1; min-width: 160px;">
                <label style="font-weight: 600; color: #334155; margin-bottom: 5px; display: block;">金额 (¥)</label>
                <input type="number" id="op_amount" class="form-control" placeholder="请输入金额">
              </div>
              <div style="flex: 2; min-width: 220px;">
                <label style="font-weight: 600; color: #334155; margin-bottom: 5px; display: block;">备注</label>
                <input type="text" id="op_remark" class="form-control" value="上级操作">
              </div>
              <div>
                <button class="btn" id="btnSubmitOp" onclick="submitMoney()" style="background:#07c160;color:#fff;border:none;">确认提交</button>
                <button class="btn btn-default" onclick="document.getElementById('moneyPanel').style.display='none'" style="margin-left: 8px;">取消</button>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<div id="recordModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:99999; align-items:center; justify-content:center;">
  <div style="background:#fff; border-radius:18px; width:800px; max-width:90%; max-height:80vh; display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(15, 23, 42, 0.18);">
      <div style="padding:16px 20px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center;">
          <h4 style="margin:0; font-weight:800; color:#0f172a;">余额变动明细（最近30条）</h4>
          <button onclick="document.getElementById('recordModal').style.display='none'" style="background:none; border:none; font-size:24px; cursor:pointer; color:#999;">&times;</button>
      </div>
      <div style="padding:20px; overflow-y:auto; flex:1;">
          <table class="table" style="width:100%; text-align:left; border-collapse:collapse;">
              <thead>
                  <tr style="background:#f8fafc; border-bottom:1px solid #eee;">
                      <th style="padding:10px; color:#475569;">时间</th>
                      <th style="padding:10px; color:#475569;">类型</th>
                      <th style="padding:10px; color:#475569;">金额</th>
                      <th style="padding:10px; color:#475569;">变动后余额</th>
                      <th style="padding:10px; color:#475569;">备注/说明</th>
                  </tr>
              </thead>
              <tbody id="recordBody">
                  <tr><td colspan="5" style="text-align:center; padding:20px; color:#999;">加载中...</td></tr>
              </tbody>
          </table>
      </div>
  </div>
</div>

<script>
var currentMoneyAction = 'add_money';

function doSave() {
    var fd = new FormData();
    fd.append('act', 'save');
    fd.append('uid', <?php echo $targetUid; ?>);
    fd.append('rate', document.getElementById('rate').value);
    fd.append('pay', document.getElementById('pay').value);
    fd.append('email', document.getElementById('email').value.trim());
    fd.append('phone', document.getElementById('phone').value.trim());
    
    fetch('./sub_edit.php?uid=<?php echo $targetUid; ?>', {method:'POST', body:fd})
    .then(r=>r.json()).then(d=>{
        alert(d.msg);
        if(d.code===1) location.reload();
    });
}

function doResetPwd() {
    var p = prompt('输入新密码（至少 6 位）：');
    if (!p) return;
    if (p.length < 6) { alert('至少 6 位'); return; }
    var fd = new FormData();
    fd.append('act', 'reset_pwd');
    fd.append('uid', <?php echo $targetUid; ?>);
    fd.append('new_pwd', p);
    fetch('./sub_edit.php?uid=<?php echo $targetUid; ?>', {method:'POST', body:fd})
    .then(r=>r.json()).then(d=>alert(d.msg));
}

function doDelete() {
    if (!confirm('确定删除该下级吗？（不可恢复）')) return;
    var fd = new FormData();
    fd.append('act', 'delete');
    fd.append('uid', <?php echo $targetUid; ?>);
    fetch('./sub_edit.php?uid=<?php echo $targetUid; ?>', {method:'POST', body:fd})
    .then(r=>r.json()).then(d=>{
        alert(d.msg);
        if (d.code===1) location.href='./sub_list.php';
    });
}

function openMoneyPanel(type) {
    currentMoneyAction = type === 'add' ? 'add_money' : 'sub_money';
    var isAdd = type === 'add';
    
    document.getElementById('moneyPanelTitle').innerText = isAdd ? '增加下级余额（授信）' : '减少下级余额';
    var btn = document.getElementById('btnSubmitOp');
    btn.style.background = isAdd ? '#07c160' : '#e74c3c';
    btn.innerText = isAdd ? '确认增加' : '确认减少';
    
    document.getElementById('op_amount').value = '';
    document.getElementById('op_remark').value = isAdd ? '上级授权增加' : '上级扣回';
    document.getElementById('moneyPanel').style.display = 'block';
    document.getElementById('op_amount').focus();
}

function submitMoney() {
    var amt = parseFloat(document.getElementById('op_amount').value);
    var remark = document.getElementById('op_remark').value.trim();
    if (!amt || amt <= 0) { alert('金额必须大于 0'); return; }
    
    var btn = document.getElementById('btnSubmitOp');
    var oldText = btn.innerText;
    btn.innerText = '处理中...';
    btn.disabled = true;
    
    var fd = new FormData();
    fd.append('act', currentMoneyAction);
    fd.append('uid', <?php echo $targetUid; ?>);
    fd.append('amount', amt);
    fd.append('remark', remark);
    
    fetch('./sub_edit.php?uid=<?php echo $targetUid; ?>', {method:'POST', body:fd})
    .then(r=>r.json()).then(d=>{
        btn.innerText = oldText;
        btn.disabled = false;
        alert(d.msg);
        if (d.code === 1) location.reload();
    }).catch(e => {
        btn.innerText = oldText;
        btn.disabled = false;
        alert('网络异常');
    });
}

function showRecordList() {
    document.getElementById('recordModal').style.display = 'flex';
    var tbody = document.getElementById('recordBody');
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:20px; color:#999;">加载中...</td></tr>';
    
    fetch('?my=get_record&uid=<?php echo $targetUid; ?>')
    .then(r => r.json())
    .then(d => {
        if (d.code !== 0) { alert(d.msg); return; }
        if (!d.data || d.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:20px; color:#999;">暂无记录</td></tr>';
            return;
        }
        var html = '';
        d.data.forEach(r => {
            html += '<tr style="border-bottom:1px solid #f1f5f9;">';
            html += '<td style="padding:10px; color:#666;">' + r.date + '</td>';
            html += '<td style="padding:10px;"><span style="color:' + r.color + '; font-weight:bold;">' + r.action_str + '</span></td>';
            html += '<td style="padding:10px; color:' + r.color + '; font-weight:bold;">' + (r.action_str === '增加' ? '+' : '-') + ' ¥' + parseFloat(r.money).toFixed(2) + '</td>';
            html += '<td style="padding:10px; color:#1677ff; font-weight:bold;">¥' + parseFloat(r.newmoney).toFixed(2) + '</td>';
            html += '<td style="padding:10px; color:#666;">' + (r.type || '-') + '</td>';
            html += '</tr>';
        });
        tbody.innerHTML = html;
    })
    .catch(e => {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:20px; color:red;">加载失败</td></tr>';
    });
}
</script>
<?php include './foot.php'; ?>
            <div class="col-md-6">
              <div class="form-group" style="margin-bottom: 20px;">
                <label style="font-weight: bold; color: #333;">分润比例 (%)，下级收款时按比例分给我</label>
                <input type="number" step="0.01" id="rate" class="form-control" style="height: 40px;" value="<?php echo (float)$target['rate']; ?>">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group" style="margin-bottom: 20px;">
                <label style="font-weight: bold; color: #333;">收款权限</label>
                <select id="pay" class="form-control" style="height: 40px;">
                  <option value="1" <?php if((int)$target['pay']===1) echo 'selected'; ?>>✅ 开启（下级可以收款）</option>
                  <option value="0" <?php if((int)$target['pay']===0) echo 'selected'; ?>>⛔ 关闭（下级无法收款）</option>
                </select>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="form-group" style="margin-bottom: 20px;">
                <label style="font-weight: bold; color: #333;">邮箱</label>
                <input type="email" id="email" class="form-control" style="height: 40px;" value="<?php echo htmlspecialchars($target['email']); ?>">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group" style="margin-bottom: 20px;">
                <label style="font-weight: bold; color: #333;">手机号</label>
                <input type="text" id="phone" class="form-control" style="height: 40px;" value="<?php echo htmlspecialchars($target['phone']); ?>">
              </div>
            </div>
          </div>

          <!-- 按钮操作区 -->
          <div style="margin-top: 20px; padding-top: 25px; border-top: 1px solid #eee;">
            <button class="btn btn-primary" onclick="doSave()" style="padding: 8px 24px; font-size: 14px;"><i class="fa fa-save"></i> 保存修改</button>
            <button class="btn" onclick="doResetPwd()" style="padding: 8px 24px; font-size: 14px; background-color:#07c160; color:#fff; border:none; margin-left: 8px;"><i class="fa fa-key"></i> 重置密码</button>
            <button class="btn" onclick="openMoneyPanel('add')" style="padding: 8px 24px; font-size: 14px; background-color:#07c160; color:#fff; border:none; margin-left: 8px;"><i class="fa fa-plus"></i> 增加余额</button>
            <button class="btn" onclick="openMoneyPanel('sub')" style="padding: 8px 24px; font-size: 14px; background-color:#e74c3c; color:#fff; border:none; margin-left: 8px;"><i class="fa fa-minus"></i> 减少余额</button>
            <?php if ((int)$target['upid'] === $uid): ?>
            <button class="btn btn-default" onclick="doDelete()" style="padding: 8px 24px; font-size: 14px; float: right; color: #e74c3c; border-color: #e74c3c;"><i class="fa fa-trash"></i> 删除该下级</button>
            <?php endif; ?>
          </div>

          <!-- 余额操作面板（默认隐藏，点击按钮展开） -->
          <div id="moneyPanel" style="display: none; margin-top: 25px; padding: 20px; background: #fafbfc; border: 1px solid #e2e8f0; border-radius: 8px;">
            <h4 id="moneyPanelTitle" style="margin-top: 0; margin-bottom: 15px; font-size: 16px; font-weight: bold; color: #333;">调整余额</h4>
            <div style="display: flex; gap: 15px; align-items: flex-end;">
              <div style="flex: 1;">
                <label style="font-weight: normal; color: #555; margin-bottom: 5px; display: block;">金额 (¥)</label>
                <input type="number" id="op_amount" class="form-control" placeholder="请输入金额" style="height: 40px;">
              </div>
              <div style="flex: 2;">
                <label style="font-weight: normal; color: #555; margin-bottom: 5px; display: block;">备注</label>
                <input type="text" id="op_remark" class="form-control" value="上级操作" style="height: 40px;">
              </div>
              <div>
                <button class="btn" id="btnSubmitOp" onclick="submitMoney()" style="height: 40px; padding: 0 25px; color: #fff; border: none;">确认提交</button>
                <button class="btn btn-default" onclick="document.getElementById('moneyPanel').style.display='none'" style="height: 40px; margin-left: 8px;">取消</button>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<!-- 流水明细弹窗 -->
<div id="recordModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:99999; align-items:center; justify-content:center;">
  <div style="background:#fff; border-radius:8px; width:800px; max-width:90%; max-height:80vh; display:flex; flex-direction:column; box-shadow:0 4px 12px rgba(0,0,0,0.15);">
      <div style="padding:16px 20px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center;">
          <h4 style="margin:0; font-weight:bold; color:#333;">余额变动明细（最近30条）</h4>
          <button onclick="document.getElementById('recordModal').style.display='none'" style="background:none; border:none; font-size:24px; cursor:pointer; color:#999;">&times;</button>
      </div>
      <div style="padding:20px; overflow-y:auto; flex:1;">
          <table class="table" style="width:100%; text-align:left; border-collapse:collapse;">
              <thead>
                  <tr style="background:#f8fafc; border-bottom:1px solid #eee;">
                      <th style="padding:10px; color:#475569;">时间</th>
                      <th style="padding:10px; color:#475569;">类型</th>
                      <th style="padding:10px; color:#475569;">金额</th>
                      <th style="padding:10px; color:#475569;">变动后余额</th>
                      <th style="padding:10px; color:#475569;">备注/说明</th>
                  </tr>
              </thead>
              <tbody id="recordBody">
                  <tr><td colspan="5" style="text-align:center; padding:20px; color:#999;">加载中...</td></tr>
              </tbody>
          </table>
      </div>
  </div>
</div>

<script>
var currentMoneyAction = 'add_money';

function doSave() {
    var fd = new FormData();
    fd.append('act', 'save');
    fd.append('uid', <?php echo $targetUid; ?>);
    fd.append('rate', document.getElementById('rate').value);
    fd.append('pay', document.getElementById('pay').value);
    fd.append('email', document.getElementById('email').value.trim());
    fd.append('phone', document.getElementById('phone').value.trim());
    
    fetch('./sub_edit.php?uid=<?php echo $targetUid; ?>', {method:'POST', body:fd})
    .then(r=>r.json()).then(d=>{
        alert(d.msg);
        if(d.code===1) location.reload();
    });
}

function doResetPwd() {
    var p = prompt('输入新密码（至少 6 位）：');
    if (!p) return;
    if (p.length < 6) { alert('至少 6 位'); return; }
    var fd = new FormData();
    fd.append('act', 'reset_pwd');
    fd.append('uid', <?php echo $targetUid; ?>);
    fd.append('new_pwd', p);
    fetch('./sub_edit.php?uid=<?php echo $targetUid; ?>', {method:'POST', body:fd})
    .then(r=>r.json()).then(d=>alert(d.msg));
}

function doDelete() {
    if (!confirm('确定删除该下级吗？（不可恢复）')) return;
    var fd = new FormData();
    fd.append('act', 'delete');
    fd.append('uid', <?php echo $targetUid; ?>);
    fetch('./sub_edit.php?uid=<?php echo $targetUid; ?>', {method:'POST', body:fd})
    .then(r=>r.json()).then(d=>{
        alert(d.msg);
        if (d.code===1) location.href='./sub_list.php';
    });
}

function openMoneyPanel(type) {
    currentMoneyAction = type === 'add' ? 'add_money' : 'sub_money';
    var isAdd = type === 'add';
    
    document.getElementById('moneyPanelTitle').innerText = isAdd ? '增加下级余额（授信）' : '减少下级余额';
    var btn = document.getElementById('btnSubmitOp');
    btn.style.background = isAdd ? '#07c160' : '#e74c3c';
    btn.innerText = isAdd ? '确认增加' : '确认减少';
    
    document.getElementById('op_amount').value = '';
    document.getElementById('op_remark').value = isAdd ? '上级授权增加' : '上级扣回';
    document.getElementById('moneyPanel').style.display = 'block';
    document.getElementById('op_amount').focus();
}

function submitMoney() {
    var amt = parseFloat(document.getElementById('op_amount').value);
    var remark = document.getElementById('op_remark').value.trim();
    if (!amt || amt <= 0) { alert('金额必须大于 0'); return; }
    
    var btn = document.getElementById('btnSubmitOp');
    var oldText = btn.innerText;
    btn.innerText = '处理中...';
    btn.disabled = true;
    
    var fd = new FormData();
    fd.append('act', currentMoneyAction);
    fd.append('uid', <?php echo $targetUid; ?>);
    fd.append('amount', amt);
    fd.append('remark', remark);
    
    fetch('./sub_edit.php?uid=<?php echo $targetUid; ?>', {method:'POST', body:fd})
    .then(r=>r.json()).then(d=>{
        btn.innerText = oldText;
        btn.disabled = false;
        alert(d.msg);
        if (d.code === 1) location.reload();
    }).catch(e => {
        btn.innerText = oldText;
        btn.disabled = false;
        alert('网络异常');
    });
}

function showRecordList() {
    document.getElementById('recordModal').style.display = 'flex';
    var tbody = document.getElementById('recordBody');
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:20px; color:#999;">加载中...</td></tr>';
    
    fetch('?my=get_record&uid=<?php echo $targetUid; ?>')
    .then(r => r.json())
    .then(d => {
        if (d.code !== 0) { alert(d.msg); return; }
        if (!d.data || d.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:20px; color:#999;">暂无记录</td></tr>';
            return;
        }
        var html = '';
        d.data.forEach(r => {
            html += '<tr style="border-bottom:1px solid #f1f5f9;">';
            html += '<td style="padding:10px; color:#666;">' + r.date + '</td>';
            html += '<td style="padding:10px;"><span style="color:' + r.color + '; font-weight:bold;">' + r.action_str + '</span></td>';
            html += '<td style="padding:10px; color:' + r.color + '; font-weight:bold;">' + (r.action_str === '增加' ? '+' : '-') + ' ¥' + parseFloat(r.money).toFixed(2) + '</td>';
            html += '<td style="padding:10px; color:#1677ff; font-weight:bold;">¥' + parseFloat(r.newmoney).toFixed(2) + '</td>';
            html += '<td style="padding:10px; color:#666;">' + (r.type || '-') + '</td>';
            html += '</tr>';
        });
        tbody.innerHTML = html;
    })
    .catch(e => {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:20px; color:red;">加载失败</td></tr>';
    });
}
</script>
<?php include './foot.php'; ?>