<?php
require_once('../../../includes/common.php');

$trade_no = $_GET['trade_no'] ?? '';
if(!$trade_no) exit('参数错误');

$order = $DB->getRow("SELECT * FROM pay_order WHERE trade_no=:t", [':t'=>$trade_no]);
if(!$order) exit('订单不存在');
if($order['status'] == 1) { header('Location: /payok.php?trade_no='.$trade_no); exit; }

$p = json_decode($order['param'], true);
$content = $p['qrcode_content'] ?? '';
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data='.urlencode($content);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>扫码支付</title>
<style>
body { font-family: -apple-system, Arial; text-align: center; padding: 30px 15px; background: #f5f5f5; }
.box { background: #fff; border-radius: 16px; padding: 30px; max-width: 420px; margin: 0 auto; box-shadow: 0 4px 20px rgba(0,0,0,.08); }
.title { color: #888; font-size: 14px; }
.amount { font-size: 40px; color: #e74c3c; font-weight: bold; margin: 8px 0 20px; }
.qr img { width: 280px; height: 280px; }
.tip { color: #999; font-size: 13px; margin-top: 15px; }
</style>
</head>
<body>
<div class="box">
  <div class="title">应付金额</div>
  <div class="amount">¥<?php echo number_format($order['realmoney'],2); ?></div>
  <div class="qr"><img src="<?php echo $qrUrl; ?>"></div>
  <div class="tip">请使用对应 App 扫码支付</div>
  <div class="tip">支付后本页自动跳转…</div>
</div>
<script>
setInterval(()=>{
  fetch('/api.php?act=order&trade_no=<?php echo $trade_no; ?>')
    .then(r=>r.json()).then(d=>{
      if(d.status==1) location.href='/payok.php?trade_no=<?php echo $trade_no; ?>';
    }).catch(()=>{});
}, 3000);
</script>
</body>
</html>
