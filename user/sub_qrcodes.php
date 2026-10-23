<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$is_defend = true;
include("../includes/common.php");
if($islogin2 != 1) exit("");
$uid = (int)$userrow['uid'];

function getDesc($DB, $root, $max = 3) {
    $r = []; $q = [['u'=>$root,'d'=>1]];
    while ($q) {
        $c = array_shift($q);
        if ($c['d'] >= $max) continue;
        foreach ($DB->getAll("SELECT uid FROM pay_user WHERE upid=:u", [':u'=>$c['u']]) as $k) {
            $r[] = (int)$k['uid'];
            $q[] = ['u'=>(int)$k['uid'],'d'=>$c['d']+1];
        }
    }
    return $r;
}
$desc = getDesc($DB, $uid);
$allUids = array_merge([$uid], $desc);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');
    $act = $_POST['act'] ?? '';
    if ($act === 'toggle') {
        $qid = (int)$_POST['qid'];
        $st = (int)$_POST['status'];
        $owner = (int)$DB->getColumn("SELECT uid FROM pay_qrcode WHERE id=:id", [':id'=>$qid]);
        if (!in_array($owner, $allUids, true)) exit(json_encode(['code'=>-1,'msg'=>'无权']));
        $DB->exec("UPDATE pay_qrcode SET status=:s WHERE id=:id", [':s'=>$st, ':id'=>$qid]);
        exit(json_encode(['code'=>1,'msg'=>'已'.($st?'开启':'关闭')]));
    }


    if ($act === 'save_push') {
        $qid = (int)$_POST['qid'];
        $owner = (int)$DB->getColumn("SELECT uid FROM pay_qrcode WHERE id=:id", [':id'=>$qid]);
        if (!in_array($owner, $allUids, true) || $owner === $uid) exit(json_encode(['code'=>-1,'msg'=>'无权修改']));
        
        $gid = trim($_POST['group_id'] ?? '');
        $otg = trim($_POST['operator_tg'] ?? '');
        $mail = trim($_POST['mail_to'] ?? '');
        
        $DB->exec("UPDATE pay_qrcode SET group_id=:g, operator_tg=:t, mail_to=:m WHERE id=:id", [':g'=>$gid, ':t'=>$otg, ':m'=>$mail, ':id'=>$qid]);
        exit(json_encode(['code'=>1,'msg'=>'推送设置已更新']));
    }
    
    if ($act === 'save_qrcode') {
        $qid = (int)$_POST['qid'];
        $owner = (int)$DB->getColumn("SELECT uid FROM pay_qrcode WHERE id=:id", [':id'=>$qid]);
        if (!in_array($owner, $allUids, true) || $owner === $uid) exit(json_encode(['code'=>-1,'msg'=>'无权修改该码']));
        
        $rate = (float)$_POST['float_rate'];
        $weight = (int)($_POST['weight'] ?? 100);
        if ($weight < 1) $weight = 100;
        $gid = trim($_POST['group_id'] ?? '');
        $otg = trim($_POST['operator_tg'] ?? '');
        $mail = trim($_POST['mail_to'] ?? '');
        
        $DB->exec("UPDATE pay_qrcode SET float_rate=:r, weight=:w, group_id=:g, operator_tg=:t, mail_to=:m WHERE id=:id", [':r'=>$rate, ':w'=>$weight, ':g'=>$gid, ':t'=>$otg, ':m'=>$mail, ':id'=>$qid]);
        exit(json_encode(['code'=>1,'msg'=>'保存成功']));
    }
    exit(json_encode(['code'=>-1,'msg'=>'未知操作']));


}

$in = implode(',', array_map('intval', $allUids));
if (empty($in)) { $in = '0'; }
$qrs = $DB->getAll("SELECT q.id,q.uid,q.channel,q.status,q.remark,q.today_count,q.today_amount,q.use_count,q.mail_to,q.mail_enabled,q.group_id,q.operator_tg, q.float_rate,q.weight,q.qrcode_content,q.qrcode_img, u.username FROM pay_qrcode q LEFT JOIN pay_user u ON q.uid=u.uid WHERE q.uid IN ($in) ORDER BY q.uid, q.id");
if (!$qrs && $DB->error()) {
    die('<div style="color:red;padding:20px;">SQL 错误: ' . $DB->error() . '<br>IN: ' . htmlspecialchars($in) . '</div>');
}
$title = '下级收款码';
include './head.php';
?>
<style>
.app-sub-qrcode-page {
  background: linear-gradient(180deg, #f6f9ff 0%, #f3f7fb 100%);
}
.app-sub-qrcode-page .wrapper-md {
  padding-top: 14px !important;
  padding-bottom: 18px !important;
}
.app-sub-qrcode-page .bg-light.lter.b-b.wrapper-md {
  background: transparent !important;
  border: 0 !important;
  padding-bottom: 8px !important;
}
.app-sub-qrcode-page .bg-light.lter.b-b.wrapper-md h1 {
  font-size: 28px !important;
  font-weight: 800 !important;
  color: #0f172a !important;
  letter-spacing: 0.02em;
}
.qc-card{
  background:#fff;
  border-radius:18px;
  padding:18px 16px;
  box-shadow:0 8px 22px rgba(15,23,42,0.04);
  border:1px solid #edf3fb;
  margin-bottom:16px;
}
.qc-summary {
  display:flex;
  align-items:center;
  justify-content:space-between;
  flex-wrap:wrap;
  gap:12px;
  padding:16px 18px;
  border-radius:16px;
  background:linear-gradient(135deg,#f0f6ff 0%,#eef8ff 100%);
  border:1px solid #dfeeff;
  margin-bottom:16px;
}
.qc-summary strong { color:#0f172a; }
.qc-summary .pill {
  display:inline-flex;
  align-items:center;
  height:32px;
  padding:0 12px;
  background:#fff;
  border:1px solid #dfe8f3;
  color:#1677ff;
  border-radius:999px;
  font-size:12px;
  font-weight:700;
}
table.qc{width:100%;border-collapse:collapse;font-size:12px;}
table.qc th,table.qc td{padding:10px 8px;border-bottom:1px solid #eef2f7;text-align:left;vertical-align:middle;}
table.qc th{background:#f7fafd;color:#555;font-weight:700; font-size:11px; letter-spacing:0.04em; text-transform:uppercase;}
table.qc tbody tr:hover td{background:#f9fbff;}
.tag{display:inline-block;padding:3px 8px;border-radius:999px;font-size:11px;font-weight:700;}
.on{background:#d9f7be;color:#389e0d;}
.off{background:#fff1f0;color:#cf1322;}
.b-xs{padding:6px 10px;font-size:12px;border-radius:10px;border:none;cursor:pointer;font-weight:700;}
.b-on{background:linear-gradient(135deg,#10b981,#0ea272);color:#fff;box-shadow:0 8px 18px rgba(16,185,129,.14);}
.b-off{background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;box-shadow:0 8px 18px rgba(239,68,68,.14);}
.app-sub-qrcode-page .qc-card .b-xs {
  min-height: 34px;
  line-height: 1.2;
}
@media (max-width: 768px) {
  .app-sub-qrcode-page .bg-light.lter.b-b.wrapper-md h1 { font-size: 26px !important; }
  table.qc { display:block; overflow-x:auto; }
  .app-sub-qrcode-page .qc-card { padding: 14px 12px; }
}
</style>
<div id="content" class="app-content app-sub-qrcode-page" role="main">
<div class="app-content-body">
<div class="bg-light lter b-b wrapper-md"><h1 class="m-n font-thin h3"><i class="fa fa-qrcode"></i> 下级收款码管理</h1></div>
<div class="wrapper-md">
  <div class="qc-card">
    <div class="qc-summary">
      <div style="font-size:14px;color:#334155;">
        共 <strong><?php echo count($qrs);?></strong> 个收款码（含自己 + 全部下级）
      </div>
      <div class="pill">层级视图 · 可编辑费率</div>
    </div>
    <div class="alert alert-info" style="border-radius:10px; padding:15px 20px; margin-bottom:20px; font-size:13px; line-height:1.8; background-color:#f0f8ff; border-color:#cce5ff; color:#004085;">
    <b><i class="fa fa-info-circle"></i> 💡 系统是怎么给客户派单的？（必看）</b><br>
    系统分配收款码，按以下顺序进行：<br>
    <b>第 1 步：看【智能路由】规则</b><br>
    如果您在左侧【收款码路由】里设置了规则（比如：广东客户只走 A 码），系统会<b>优先按照地区规则派单</b>。只要客户对上了规则，就只用那个指定的码。<br>
    <b>第 2 步：看【权重】抽签</b><br>
    如果客户没有对上任何地区规则，系统就会在所有正常的码里，根据您填写的<b>“权重”来抽签</b>。<br>
    举个栗子🌰：如果您把 A 码权重设成 100，B 码权重设成 200，那么 B 码被抽中的概率就是 A 码的 2 倍！<br>
    <span style="color:#d9534f; font-size:12px;">⚠️ 提示：默认权重是 100。只要码被绑在【智能路由】规则里，就算权重再低，该地区的客户依然会分配给它。</span>
</div>

  <?php if (!$qrs): ?>
      <div style="text-align:center;padding:40px;color:#999;">暂无收款码</div>
    <?php else: ?>
    <table class="qc">
      <thead><tr>
        <th>ID</th><th>码图</th><th>归属商户</th><th>收款方式</th><th>备注</th><th>状态</th><th>费率 / 推送目标</th><th>今日笔数</th><th>今日金额</th><th>总笔数</th><th>操作</th>
      </tr></thead>
      <tbody>
      <?php foreach ($qrs as $q):
        $isMine = ((int)$q['uid'] === $uid);
      ?>
        <tr>
          <td><b><?php echo $q['id'];?></b></td>
          <td><img src="<?php echo $q['qrcode_img'] ? ('/'.ltrim($q['qrcode_img'],'/')) : ('https://api.qrserver.com/v1/create-qr-code/?size=60x60&data='.urlencode($q['qrcode_content'] ?: 'EMPTY'));?>" style="width:50px;height:50px;border:1px solid #eee;border-radius:4px;"></td>
          <td>
            <?php if ($isMine): ?>
              <span style="color:#1677ff;font-weight:700;">🟢 本级 (UID:<?php echo $q['uid'];?>)</span>
            <?php else: ?>
              <span style="color:#888;">🔹 下级 (UID:<?php echo $q['uid'];?>)</span><br>
              <small><?php echo htmlspecialchars($q['username'] ?: '—');?></small>
            <?php endif; ?>
          </td>
          <td>
            <?php 
                $__chMap = [
                    'aggregate_alipay' => '聚合支付宝',
                    'aggregate_both' => '聚合(支付宝/微信)',
                    'aggregate_wechat' => '聚合微信',
                    'alipay_personal' => '支付宝个人码',
                    'alipay_biz' => '支付宝商业码',
                    'alipay_account' => '支付宝账号',
                    'wechat_personal' => '微信个人码',
                    'wechat_reward' => '微信赞赏码',
//                    'wechat_bank' => '微信银行卡',
//                    'bank' => '银行卡'
                ];
                $__chName = $__chMap[$q['channel']] ?? $q['channel'];
            ?>
            <span style="font-size:12px; font-weight:bold; color:#d81b60;"><?php echo htmlspecialchars($__chName);?></span>
          </td>
          <td><?php echo htmlspecialchars($q['remark'] ?: '—');?></td>
          <td>
            <?php if ((int)$q['status'] === 1): ?>
              <span class="tag on">✅ 启用</span>
            <?php else: ?>
              <span class="tag off">⛔ 停用</span>
            <?php endif; ?>
          </td>
          <td>
            <div style="font-size:12px; line-height:1.6; max-width:220px; word-break:break-all;">
                <?php if (!$isMine): ?>
                    <div style="color:#e74c3c; font-weight:bold; margin-bottom:3px;">费率: <?php echo (float)$q['float_rate'];?>%</div>
                    <div style="color:#1677ff; margin-bottom:3px;">权重: <?php echo (int)($q['weight'] ?: 100);?></div>
                    <?php 
                        // 查询这个码绑在哪些路由规则里（加容错，防止白屏）
                        $routeNames = [];
                        try {
                            $routeNames = $DB->query("SELECT name FROM pay_qrcode_route WHERE status=1 AND JSON_CONTAINS(match_value, :qid, '$.qrcode_ids')", [':qid' => json_encode((string)$q['id'])]);
                        } catch (Exception $e) {
                            // 如果 JSON 解析报错，直接忽略，不显示路由名称
                        }
                        if (!empty($routeNames)): 
                    ?>
                        <div style="color:#e67e22; margin-top:5px; font-size:11px;">
                            <i class="fa fa-map-marker"></i> 路由规则: 
                            <?php foreach($routeNames as $rn) echo htmlspecialchars($rn['name']) . ' '; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (!empty($q['group_id']) && $q['group_id'] != '0'): ?><div>👥 <?php echo htmlspecialchars($q['group_id']);?></div><?php endif; ?>
                <?php if (!empty($q['operator_tg'])): ?><div>👤 <?php echo htmlspecialchars($q['operator_tg']);?></div><?php endif; ?>
                <?php if (!empty($q['mail_to'])): ?><div>📧 <?php echo htmlspecialchars($q['mail_to']);?></div><?php endif; ?>
                <?php if (empty($q['group_id']) && empty($q['operator_tg']) && empty($q['mail_to'])): ?>
                    <span style="color:#999;">未绑定推送</span>
                <?php endif; ?>
            </div>
          </td>
          <td><?php echo (int)$q['today_count'];?></td>
          <td>¥<?php echo number_format((float)$q['today_amount'], 2);?></td>
          <td><?php echo (int)$q['use_count'];?></td>
          <td>
            <?php if (!$isMine): ?>
              <button class="b-xs" style="background:#1677ff;color:#fff;margin-bottom:5px;width:100%;" onclick="openEditModal(<?php echo $q['id'];?>, <?php echo (float)$q['float_rate'];?>, <?php echo (int)($q['weight'] ?: 100);?>, '<?php echo addslashes($q['group_id'] ?? '');?>', '<?php echo addslashes($q['operator_tg'] ?? '');?>', '<?php echo addslashes($q['mail_to'] ?? '');?>')">✏️ 编辑</button>
            <?php endif; ?>
            <?php if ((int)$q['status'] === 1): ?>
              <button class="b-xs b-off" style="width:100%;" onclick="tg(<?php echo $q['id'];?>, 0)">停用</button>
            <?php else: ?>
              <button class="b-xs b-on" style="width:100%;" onclick="tg(<?php echo $q['id'];?>, 1)">启用</button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>
</div>
</div>
<script>
function tg(qid, st) {
  var fd = new FormData();
  fd.append('act','toggle'); fd.append('qid',qid); fd.append('status',st);
  fetch('./sub_qrcodes.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    alert(d.msg); if(d.code===1) location.reload();
  });
}

function openEditModal(qid, rate, weight, gid, otg, mail) {
    document.getElementById('edit_qid').value = qid;
    document.getElementById('edit_rate').value = rate;
    document.getElementById('edit_weight').value = weight;
    document.getElementById('edit_gid').value = gid || '';
    document.getElementById('edit_otg').value = otg || '';
    document.getElementById('edit_mail').value = mail || '';
    document.getElementById('editQrcodeModal').style.display = 'flex';
}

function saveEditModal() {
    var qid = document.getElementById('edit_qid').value;
    var btn = document.getElementById('btnSaveQrcode');
    btn.innerText = '保存中...';
    btn.disabled = true;

    var fd = new FormData();
    fd.append('act', 'save_qrcode');
    fd.append('qid', qid);
    fd.append('float_rate', document.getElementById('edit_rate').value);
    fd.append('weight', document.getElementById('edit_weight').value);
    fd.append('group_id', document.getElementById('edit_gid').value.trim());
    fd.append('operator_tg', document.getElementById('edit_otg').value.trim());
    fd.append('mail_to', document.getElementById('edit_mail').value.trim());

    fetch('', {method:'POST', body:fd})
    .then(r=>r.json()).then(d=>{
        btn.innerText = '保存';
        btn.disabled = false;
        if (d.code === 1) {
            document.getElementById('editQrcodeModal').style.display = 'none';
            location.reload();
        } else {
            alert(d.msg);
        }
    }).catch(e => {
        btn.innerText = '保存';
        btn.disabled = false;
        alert('网络异常');
    });
}

</script>

<!-- 编辑收款码弹窗 -->
<div id="editQrcodeModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:99999; align-items:center; justify-content:center;">
  <div style="background:#fff; border-radius:10px; width:400px; padding:25px; box-shadow:0 4px 15px rgba(0,0,0,0.2);">
      <h4 style="margin-top:0; margin-bottom:20px; font-weight:bold; color:#333;">编辑收款码设置</h4>
      <input type="hidden" id="edit_qid">
      <div class="form-group" style="margin-bottom:15px;">
          <label style="font-weight:bold; color:#555; margin-bottom:5px; display:block;">专属费率 (%)</label>
          <input type="number" step="0.01" id="edit_rate" class="form-control" style="height:38px; width:100%; border:1px solid #ccc; border-radius:6px; padding:0 10px;">
      </div>
      <div class="form-group" style="margin-bottom:15px;">
          <label style="font-weight:bold; color:#555; margin-bottom:5px; display:block;">轮询权重 (默认100，越高越容易被选中)</label>
          <input type="number" step="1" id="edit_weight" class="form-control" style="height:38px; width:100%; border:1px solid #ccc; border-radius:6px; padding:0 10px;">
      </div>
      <div class="form-group" style="margin-bottom:15px;">
          <label style="font-weight:bold; color:#555; margin-bottom:5px; display:block;">绑定 TG 群号</label>
          <input type="text" id="edit_gid" class="form-control" placeholder="例如: -100xxx" style="height:38px; width:100%; border:1px solid #ccc; border-radius:6px; padding:0 10px;">
      </div>
      <div class="form-group" style="margin-bottom:15px;">
          <label style="font-weight:bold; color:#555; margin-bottom:5px; display:block;">绑定 TG 个人号</label>
          <input type="text" id="edit_otg" class="form-control" placeholder="例如: 12345678" style="height:38px; width:100%; border:1px solid #ccc; border-radius:6px; padding:0 10px;">
      </div>
      <div class="form-group" style="margin-bottom:20px;">
          <label style="font-weight:bold; color:#555; margin-bottom:5px; display:block;">接收邮箱</label>
          <input type="email" id="edit_mail" class="form-control" placeholder="例如: test@qq.com" style="height:38px; width:100%; border:1px solid #ccc; border-radius:6px; padding:0 10px;">
      </div>
      <div style="text-align:right;">
          <button class="btn btn-default" onclick="document.getElementById('editQrcodeModal').style.display='none'" style="padding:8px 20px; border-radius:6px; border:1px solid #ddd; background:#f8f9fa;">取消</button>
          <button class="btn btn-primary" id="btnSaveQrcode" onclick="saveEditModal()" style="padding:8px 20px; border-radius:6px; background:#1677ff; color:#fff; border:none; margin-left:10px;">保存</button>
      </div>
  </div>
</div>

<?php include './foot.php'; ?>
