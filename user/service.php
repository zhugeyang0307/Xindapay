<?php
$is_defend = true;
include("../includes/common.php");
if($islogin2==1){}else exit("<script>location.href='./login.php';</script>");

$uid = (int)$userrow['uid'];

// 读配置
function svc_cfg($k, $def='') {
    global $DB;
    $v = $DB->getColumn("SELECT v FROM pay_config WHERE k=:k", [':k'=>$k]);
    return ($v === null || $v === '') ? $def : $v;
}
$monthPrice  = (float)svc_cfg('sys_month_price', 100);
$extraPrice  = (float)svc_cfg('sys_extra_device_price', 50);
$trialDays   = (int)svc_cfg('sys_trial_days', 2);

// 当前状态
$status   = \lib\Service::getStatus($uid);
$expire   = \lib\Service::getExpire($uid);
$quota    = \lib\Service::getApkQuota($uid);
$used     = \lib\Service::getApkUsed($uid);
$remain   = max(0, $quota - $used);
$balance  = (float)$userrow['money'];

// 待付款订单
$pending = $DB->getRow("SELECT * FROM pay_service_order WHERE uid=:u AND status=0 AND addtime > DATE_SUB(NOW(), INTERVAL 30 MINUTE) ORDER BY id DESC LIMIT 1", [':u'=>$uid]);

// 最近订单
$orders = $DB->getAll("SELECT * FROM pay_service_order WHERE uid=:u ORDER BY id DESC LIMIT 20", [':u'=>$uid]);

$title = '系统服务';
include './head.php';
?>
<style>
.svc-card{background:#fff;border-radius:14px;padding:24px;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:20px;}
.svc-status{display:inline-block;padding:6px 14px;border-radius:20px;font-size:13px;font-weight:700;}
.svc-active{background:#d9f7be;color:#389e0d;}
.svc-trial{background:#fff7e6;color:#d46b08;}
.svc-expired{background:#fff1f0;color:#cf1322;}
.svc-none{background:#f5f5f5;color:#8c8c8c;}
.svc-price{font-size:48px;font-weight:800;color:#e74c3c;letter-spacing:-1px;}
.svc-price .unit{font-size:16px;color:#888;margin-left:8px;}
.svc-row{display:flex;gap:16px;flex-wrap:wrap;}
.svc-num{flex:1;min-width:140px;padding:16px;border:1px solid #e5eaf3;border-radius:10px;text-align:center;}
.svc-num .n{font-size:26px;font-weight:800;color:#1677ff;}
.svc-num .l{font-size:12px;color:#888;margin-top:4px;}
.btn-buy{background:linear-gradient(135deg,#1677ff,#0e4fb8);color:#fff;border:none;padding:14px 32px;border-radius:12px;font-size:15px;font-weight:700;cursor:pointer;}
.btn-buy:disabled{opacity:.6;cursor:not-allowed;}
.opt-row{display:flex;gap:12px;align-items:center;margin:10px 0;}
.opt-row label{width:120px;font-weight:600;color:#333;}
.opt-row input,.opt-row select{padding:10px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px;width:140px;}
.total-box{background:#f0f7ff;border-left:3px solid #1677ff;padding:14px 18px;border-radius:8px;margin:16px 0;font-size:15px;}
.total-box b{color:#e74c3c;font-size:22px;}
table.ord{width:100%;font-size:13px;border-collapse:collapse;}
table.ord th,table.ord td{padding:10px;border-bottom:1px solid #eef2f7;text-align:left;}
table.ord th{background:#f7fafd;color:#555;font-weight:600;}
</style>

<div id="content" class="app-content" role="main">
<div class="app-content-body">

<div class="bg-light lter b-b wrapper-md">
  <h1 class="m-n font-thin h3"><i class="fa fa-cube"></i> 系统服务</h1>
</div>

<div class="wrapper-md">

  <?php if($pending): ?>
  <div class="svc-card" style="text-align:center;background:#fff8e6;border:1px solid #ffd591;">
    <h4 style="margin-top:0;">⏳ 你有未完成的服务订单</h4>
    <div style="color:#666;margin:10px 0;">订单号：<code><?php echo htmlspecialchars($pending['trade_no']); ?></code></div>
    <div style="color:#666;margin:10px 0;">金额：<b style="color:#e74c3c;"><?php echo $pending['amount']; ?> USDT</b>（<?php echo $pending['months'];?> 月<?php if($pending['extra_devices']>0) echo ' + '.$pending['extra_devices'].' 设备';?>）</div>
    <button class="btn-buy" onclick="gotoPay('<?php echo htmlspecialchars($pending['trade_no']); ?>')">💳 重新支付</button>
  </div>
  <?php endif; ?>

  <div class="svc-card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
      <div>
        <div style="font-size:14px;color:#888;margin-bottom:8px;">当前状态</div>
        <?php
        $map = [
          'active'        => ['svc-active','使用中'],
          'trial'         => ['svc-trial','试用中'],
          'expired'       => ['svc-expired','已到期'],
          'trial_expired' => ['svc-expired','试用已结束'],
          'none'          => ['svc-none','未开通'],
        ];
        $s = $map[$status] ?? ['svc-none','未知'];
        ?>
        <span class="svc-status <?php echo $s[0]; ?>"><?php echo $s[1]; ?></span>
      </div>
      <div style="text-align:right;">
        <div style="font-size:14px;color:#888;">到期时间</div>
        <div style="font-size:18px;font-weight:700;color:#333;"><?php echo $expire ?: '—'; ?></div>
      </div>
    </div>

    <div class="svc-row" style="margin-top:20px;">
      <div class="svc-num"><div class="n"><?php echo $used; ?> / <?php echo $quota; ?></div><div class="l">APK 设备额度</div></div>
      <div class="svc-num"><div class="n"><?php echo $remain; ?></div><div class="l">剩余可激活</div></div>
      <div class="svc-num"><div class="n"><?php echo number_format($balance,2); ?></div><div class="l">账户余额</div></div>
    </div>
  </div>

  <div class="svc-card">
    <h4 style="margin-top:0;color:#333;"><i class="fa fa-shopping-cart"></i> 续费 / 扩容</h4>

    <div class="opt-row">
      <label>续费月数</label>
      <select id="months" onchange="calc()">
        <?php for($i=1;$i<=12;$i++): ?>
        <option value="<?php echo $i;?>"><?php echo $i;?> 个月</option>
        <?php endfor; ?>
      </select>
    </div>

    <div class="opt-row">
      <label>增加设备</label>
      <select id="extra" onchange="calc()">
        <?php for($i=0;$i<=20;$i++): ?>
        <option value="<?php echo $i;?>">+ <?php echo $i;?> 台</option>
        <?php endfor; ?>
      </select>
      <span style="color:#888;font-size:13px;">（每台 <?php echo $extraPrice;?> USDT/月，永久累加到额度）</span>
    </div>

    <div class="total-box">
      应付：<b id="total"><?php echo $monthPrice; ?></b> USDT
      <span style="color:#888;font-size:13px;margin-left:8px;">= <?php echo $monthPrice; ?> USDT × 月数 + <?php echo $extraPrice;?> USDT/台 × 设备数</span>
    </div>

    <div style="margin-top:16px;">
      <button class="btn-buy" id="btnBuy" onclick="createOrder()">💳 立即下单</button>
    </div>

    <div style="margin-top:16px;font-size:13px;color:#888;line-height:1.7;">
      · 续费从当前到期日（或今天，取较晚）起算 <br>
      · 增加设备额度永久累加（不会因到期清零）<br>
      · 到期未续 → 支付接口 + APK 全部停用
    </div>
  </div>

  <?php if($orders): ?>
  <div class="svc-card">
    <h4 style="margin-top:0;color:#333;"><i class="fa fa-list"></i> 历史订单</h4>
    <table class="ord">
      <thead><tr><th>订单号</th><th>时长</th><th>加设备</th><th>金额</th><th>状态</th><th>创建时间</th></tr></thead>
      <tbody>
      <?php foreach($orders as $o):
        $st = $o['status']==1 ? '<span style="color:#389e0d;">已支付</span>' : '<span style="color:#d46b08;">待支付</span>';
      ?>
      <tr>
        <td><code><?php echo htmlspecialchars($o['trade_no']); ?></code></td>
        <td><?php echo (int)$o['months'];?> 月</td>
        <td><?php echo (int)$o['extra_devices'];?> 台</td>
        <td><b style="color:#e74c3c;"><?php echo $o['amount']; ?></b> USDT</td>
        <td><?php echo $st; ?></td>
        <td><?php echo $o['addtime']; ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</div>
</div>
</div>

<script>
var MONTH_PRICE = <?php echo $monthPrice; ?>;
var EXTRA_PRICE = <?php echo $extraPrice; ?>;

function calc(){
  var m = parseInt(document.getElementById('months').value) || 1;
  var e = parseInt(document.getElementById('extra').value) || 0;
  var t = (m * MONTH_PRICE + e * EXTRA_PRICE).toFixed(2);
  document.getElementById('total').textContent = t;
}

function createOrder(){
  var btn = document.getElementById('btnBuy');
  var m = parseInt(document.getElementById('months').value);
  var e = parseInt(document.getElementById('extra').value);
  if (btn){ btn.disabled = true; btn.textContent = '⏳ 创建中...'; }
  var fd = new FormData();
  fd.append('months', m);
  fd.append('extra_devices', e);
  fetch('./ajax_service.php', { method:'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d.code === 1){ window.location.href = d.pay_url; }
      else { alert('创建失败：' + d.msg); if(btn){btn.disabled=false;btn.textContent='💳 立即下单';} }
    })
    .catch(function(e){ alert('网络错误：' + e.message); if(btn){btn.disabled=false;btn.textContent='💳 立即下单';} });
}

function gotoPay(tradeNo){
  var fd = new FormData();
  fd.append('reuse', tradeNo);
  fetch('./ajax_service.php', { method:'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d.code === 1){ window.location.href = d.pay_url; }
      else { alert('重新支付失败：' + d.msg); }
    });
}
</script>

<?php include './foot.php'; ?>