<?php
include("../includes/common.php");
if($islogin2==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
$title='USDT 充值';
include './head.php';

$trade_no = $_GET['trade_no'] ?? '';
$order = $DB->find('usdt_recharge', '*', ['trade_no'=>$trade_no, 'uid'=>$uid]);
if(!$order) exit('订单不存在');

$expired = strtotime($order['expire_time']) < time();
?>
<style>
.pay-wrap { max-width: 500px; margin: 0 auto; }
.pay-card { background: #fff; border-radius: 14px; padding: 24px; box-shadow: 0 2px 12px rgba(15,23,42,.06); text-align: center; }
.pay-title { color: #666; font-size: 14px; }
.usdt-big { font-size: 32px; font-weight: 800; color: #e74c3c; margin: 10px 0 6px; letter-spacing: -0.5px; }
.usdt-unit { font-size: 16px; color: #666; font-weight: 500; }
.addr-box { background: #f7f9fc; border-radius: 10px; padding: 14px; margin: 18px 0; text-align: left; word-break: break-all; font-family: Consolas,monospace; font-size: 13px; color: #1f2d3d; position: relative; }
.addr-label { font-size: 12px; color: #999; margin-bottom: 6px; }
.copy-btn { position: absolute; top: 10px; right: 10px; background: #1677ff; color: #fff; border: none; border-radius: 6px; padding: 4px 12px; font-size: 12px; cursor: pointer; }
.qr-box { margin: 20px auto; }
.qr-box img { width: 220px; height: 220px; border: 4px solid #1677ff; border-radius: 10px; }
.warn-box { background: linear-gradient(135deg,#fff1f0,#ffe7e6); border: 1.5px solid #ffccc7; border-radius: 10px; padding: 12px; margin-top: 16px; text-align: left; }
.warn-box .ti { font-size: 12px; color: #cf1322; line-height: 1.7; font-weight: 600; }
.warn-box .ti b { color: #a8071a; }
.status-box { padding: 14px; background: #f0f6ff; border-radius: 10px; margin-top: 16px; color: #1677ff; font-weight: 600; font-size: 14px; }
.status-box.ok { background: #e8fbed; color: #07c160; }
.status-box.expired { background: #f0f0f0; color: #999; }
.btn-confirm { width: 100%; padding: 14px; background: linear-gradient(135deg,#07c160,#059748); color: #fff; border: none; border-radius: 10px; font-size: 16px; font-weight: 700; cursor: pointer; margin-top: 16px; }
.btn-confirm:hover { opacity: .9; }
.btn-confirm:disabled { background: #ccc; cursor: not-allowed; }
.countdown { color: #e74c3c; font-weight: 700; }
</style>

<div id="content" class="app-content" role="main">
<div class="app-content-body">
<div class="bg-light lter b-b wrapper-md hidden-print">
  <h1 class="m-n font-thin h3">USDT 转账</h1>
</div>
<div class="wrapper-md control">
<div class="pay-wrap">
  <div class="pay-card">

    <div class="pay-title">请转账以下金额</div>
    <div class="usdt-big"><?php echo number_format($order['amount_usdt'], 4); ?> <span class="usdt-unit">USDT</span></div>
    <div style="color:#999;font-size:12px;">（订单金额 ¥<?php echo number_format($order['amount_cny'], 2); ?>，汇率 <?php echo number_format($order['rate'], 4); ?>）</div>

    <div class="qr-box">
      <img src="https://api.qrserver.com/v1/create-qr-code/?size=400x400&ecc=H&margin=10&data=<?php echo urlencode($order['address']); ?>">
    </div>

    <div class="addr-box">
      <button class="copy-btn" onclick="copyAddr()">复制</button>
      <div class="addr-label">TRC20 收款地址</div>
      <div id="addr"><?php echo htmlspecialchars($order['address']); ?></div>
    </div>

    <div class="warn-box">
      <div class="ti">
        ⚠️ <b>必须使用 TRC20 网络</b>转账<br>
        ⚠️ <b>必须精确转账 <?php echo number_format($order['amount_usdt'], 4); ?> USDT</b><br>
        ⚠️ 转账完成后点击下方"我已完成转账"按钮
      </div>
    </div>

    <?php if($order['status'] == 1): ?>
      <div class="status-box ok">✅ 已确认到账，余额已增加 ¥<?php echo number_format($order['amount_cny'], 2); ?></div>
    <?php elseif($expired || $order['status'] == 2): ?>
      <div class="status-box expired">❌ 订单已过期，请返回重新发起</div>
    <?php else: ?>
      <div class="status-box">
        ⏳ 等待转账...
        <div style="font-size:12px;margin-top:6px;color:#666;">
          剩余时间：<span class="countdown" id="countdown">--:--</span>
        </div>
      </div>
      <button class="btn-confirm" id="btnConfirm">✅ 我已完成转账，确认到账</button>
    <?php endif; ?>

    <div style="margin-top:18px;">
      <a href="./recharge.php" class="btn btn-default" style="width:100%;padding:12px;border-radius:10px;">返回</a>
    </div>

  </div>
</div>
</div>
</div>
</div>

<?php include 'foot.php';?>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.js"></script>
<script>
var STATUS = <?php echo json_encode($order['status']); ?>;
var EXPIRE = <?php echo json_encode(strtotime($order['expire_time'])); ?>;
var TRADE_NO = <?php echo json_encode($trade_no); ?>;
var ADDR = <?php echo json_encode($order['address']); ?>;

function copyAddr(){
  if(navigator.clipboard){
    navigator.clipboard.writeText(ADDR).then(function(){ layer.msg('地址已复制'); });
  } else {
    var ta = document.createElement('textarea');
    ta.value = ADDR;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    layer.msg('地址已复制');
  }
}

if(STATUS == 0){
  function tick(){
    var left = EXPIRE - Math.floor(Date.now()/1000);
    if(left <= 0){ $('#countdown').text('已过期'); return; }
    var m = Math.floor(left / 60);
    var s = left % 60;
    $('#countdown').text(String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0'));
  }
  tick();
  setInterval(tick, 1000);
}

$('#btnConfirm').click(function(){
  if(!confirm('确认你已完成 USDT 转账？\n\n请注意：恶意点击确认到账将被封号处理。')) return;
  var btn = $(this);
  btn.prop('disabled', true).text('确认中...');
  $.ajax({
    type: 'POST',
    url: 'ajax2.php?act=usdtConfirm',
    data: {trade_no: TRADE_NO},
    dataType: 'json',
    success: function(d){
      if(d.code == 0){
        layer.msg(d.msg, {icon: 1});
        setTimeout(function(){ location.reload(); }, 1000);
      } else {
        layer.alert(d.msg, {icon: 2});
        btn.prop('disabled', false).text('✅ 我已完成转账，确认到账');
      }
    },
    error: function(){
      layer.msg('服务器错误');
      btn.prop('disabled', false).text('✅ 我已完成转账，确认到账');
    }
  });
});
</script>