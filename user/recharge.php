<?php
include("../includes/common.php");
if($islogin2==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
$title='余额充值';
include './head.php';

$rate = getUsdtRate();
$usdtAddr = getUsdtAddress();
$minCny = floatval($conf['usdt_min_cny'] ?? 10);
$expireMin = intval($conf['usdt_expire_minutes'] ?? 30);

// 查最近的充值订单
$recent = $DB->getAll("SELECT * FROM pay_usdt_recharge WHERE uid=? ORDER BY id DESC LIMIT 5", [$uid]);
?>
<style>
.recharge-wrap { max-width: 520px; margin: 0 auto; }
.recharge-card { background: #fff; border-radius: 14px; padding: 24px; box-shadow: 0 2px 12px rgba(15,23,42,.06); }
.recharge-title { font-size: 20px; font-weight: 700; margin-bottom: 20px; color: #1f2d3d; }
.rate-box { background: linear-gradient(135deg,#e8f3ff,#f0f6ff); border-radius: 10px; padding: 12px 16px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
.rate-label { color: #666; font-size: 13px; }
.rate-value { color: #1677ff; font-size: 18px; font-weight: 700; }
.form-group { margin-bottom: 18px; }
.form-group label { display: block; color: #333; font-weight: 600; font-size: 14px; margin-bottom: 8px; }
.form-group input { width: 100%; padding: 12px 14px; border: 1.5px solid #dfe7f1; border-radius: 10px; font-size: 15px; box-sizing: border-box; }
.form-group input:focus { border-color: #1677ff; outline: none; }
.usdt-preview { background: #f7f9fc; border-radius: 10px; padding: 14px 16px; margin-bottom: 18px; }
.usdt-preview .row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; }
.usdt-preview .row .lbl { color: #666; }
.usdt-preview .row .val { color: #1f2d3d; font-weight: 600; }
.usdt-preview .val.big { color: #e74c3c; font-size: 18px; }
.btn-submit { width: 100%; padding: 14px; background: linear-gradient(135deg,#1677ff,#0d5cd6); color: #fff; border: none; border-radius: 10px; font-size: 16px; font-weight: 700; cursor: pointer; }
.btn-submit:hover { opacity: .9; }
.btn-submit:disabled { background: #ccc; cursor: not-allowed; }
.recent-list { margin-top: 24px; }
.recent-list h3 { font-size: 15px; color: #333; margin-bottom: 12px; }
.recent-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eef2f7; font-size: 13px; }
.recent-item .time { color: #999; font-size: 12px; }
.recent-item .st { padding: 2px 8px; border-radius: 4px; font-size: 11px; }
.st-0 { background: #fff7e6; color: #d48806; }
.st-1 { background: #e8fbed; color: #07c160; }
.st-2 { background: #f0f0f0; color: #999; }
.tip { background: #fff8e6; border-left: 4px solid #faad14; padding: 12px 16px; border-radius: 8px; font-size: 13px; color: #866800; line-height: 1.7; margin-bottom: 20px; }
</style>

<div id="content" class="app-content" role="main">
<div class="app-content-body">
<div class="bg-light lter b-b wrapper-md hidden-print">
  <h1 class="m-n font-thin h3">余额充值</h1>
</div>
<div class="wrapper-md control">
<div class="recharge-wrap">

  <div class="recharge-card">
    <div class="recharge-title">💰 USDT 余额充值</div>

    <div class="tip">
      ⚠️ 请使用 <b>TRC20 网络</b> 转账 USDT，<b>必须按显示金额精确转账</b>（多付或少付都无法自动到账）<br>
      ⏰ 订单有效期：<?php echo $expireMin; ?> 分钟，过期后金额将释放
    </div>

    <div class="rate-box">
      <span class="rate-label">实时汇率（每分钟更新）</span>
      <span class="rate-value">1 USDT ≈ <?php echo number_format($rate, 4); ?> CNY</span>
    </div>

    <div class="form-group">
      <label>充值金额（人民币）</label>
      <input type="number" id="cny" placeholder="请输入充值金额，最低 <?php echo $minCny; ?> 元" step="0.01" min="<?php echo $minCny; ?>">
    </div>

    <div class="usdt-preview" id="preview" style="display:none;">
      <div class="row">
        <span class="lbl">汇率</span>
        <span class="val">1 USDT = <?php echo number_format($rate, 4); ?> CNY</span>
      </div>
      <div class="row">
        <span class="lbl">应付 USDT</span>
        <span class="val big" id="usdtAmount">-</span>
      </div>
    </div>

    <button class="btn-submit" id="btnSubmit" disabled>生成充值订单</button>
  </div>

  <?php if($recent): ?>
  <div class="recent-list">
    <h3>最近充值记录</h3>
    <?php foreach($recent as $r): ?>
    <div class="recent-item">
      <div>
        <div><b>¥<?php echo number_format($r['amount_cny'],2); ?></b> → <b><?php echo number_format($r['amount_usdt'],4); ?> USDT</b></div>
        <div class="time"><?php echo $r['addtime']; ?></div>
      </div>
      <span class="st st-<?php echo $r['status']; ?>">
        <?php
          if($r['status']==0) echo '待支付';
          elseif($r['status']==1) echo '已完成';
          else echo '已过期';
        ?>
      </span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>
</div>
</div>
</div>

<?php include 'foot.php';?>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.js"></script>
<script>
var RATE = <?php echo json_encode($rate); ?>;
var MIN_CNY = <?php echo json_encode($minCny); ?>;

$('#cny').on('input', function(){
  var v = parseFloat($(this).val());
  if(!v || v < MIN_CNY){
    $('#preview').hide();
    $('#btnSubmit').prop('disabled', true);
    return;
  }
  // 加随机尾数（0.0001 ~ 0.0099）防止金额冲突
  var suffix = (Math.floor(Math.random() * 99) + 1) / 10000;
  var usdt = (v / RATE + suffix).toFixed(4);
  $('#usdtAmount').text(usdt + ' USDT');
  $('#preview').show();
  $('#btnSubmit').prop('disabled', false);
});

$('#btnSubmit').click(function(){
  var cny = parseFloat($('#cny').val());
  if(!cny || cny < MIN_CNY){ layer.alert('最低充值 '+MIN_CNY+' 元'); return; }

  $(this).prop('disabled', true).text('生成中...');
  $.ajax({
    type: 'POST',
    url: 'ajax2.php?act=usdtRecharge',
    data: {cny: cny},
    dataType: 'json',
    success: function(d){
      if(d.code == 0){
        location.href = 'usdt_recharge.php?trade_no=' + d.trade_no;
      } else {
        layer.alert(d.msg, {icon: 2});
        $('#btnSubmit').prop('disabled', false).text('生成充值订单');
      }
    },
    error: function(){
      layer.msg('服务器错误');
      $('#btnSubmit').prop('disabled', false).text('生成充值订单');
    }
  });
});
</script>