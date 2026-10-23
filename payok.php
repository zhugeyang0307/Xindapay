<?php
$nosession = true;
require_once __DIR__ . '/includes/common.php';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=0">
<title>支付成功</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
body {
  font-family: -apple-system, "PingFang SC", "Helvetica Neue", "Microsoft YaHei", sans-serif;
  background: linear-gradient(180deg, #f0f3f8 0%, #e8ecf3 100%);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  -webkit-font-smoothing: antialiased;
}
.card {
  background: #fff;
  border-radius: 22px;
  padding: 44px 28px 32px;
  max-width: 380px;
  width: 100%;
  text-align: center;
  box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 8px 24px rgba(15,23,42,.08), 0 20px 60px rgba(15,23,42,.06);
  animation: fadeIn .4s ease;
}
@keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
.icon-wrap {
  width: 84px;
  height: 84px;
  border-radius: 50%;
  background: linear-gradient(135deg, #33d17a 0%, #07c160 100%);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 24px;
  box-shadow: 0 10px 30px rgba(7,193,96,.35);
  animation: pop .5s cubic-bezier(.5,1.6,.5,1);
}
@keyframes pop { from { transform: scale(0); } to { transform: scale(1); } }
.icon-wrap svg { width: 46px; height: 46px; }
.title {
  font-size: 22px;
  font-weight: 800;
  color: #1f2d3d;
  margin-bottom: 10px;
  letter-spacing: .3px;
}
.desc {
  font-size: 14px;
  color: #86909c;
  line-height: 1.6;
  margin-bottom: 12px;
}
.amount-tip {
  display: inline-block;
  padding: 6px 16px;
  background: #f0faf4;
  border: 1px solid #d9f0e2;
  border-radius: 20px;
  font-size: 12.5px;
  color: #07c160;
  font-weight: 700;
  margin-bottom: 28px;
}
.btn {
  display: block;
  width: 100%;
  padding: 15px;
  border: none;
  border-radius: 14px;
  background: linear-gradient(180deg, #2ecb75 0%, #07c160 100%);
  color: #fff;
  font-size: 15.5px;
  font-weight: 800;
  cursor: pointer;
  font-family: inherit;
  letter-spacing: .3px;
  box-shadow: 0 8px 20px rgba(7,193,96,.3), 0 2px 4px rgba(7,193,96,.15);
  transition: all .15s;
}
.btn:active { transform: translateY(1px) scale(.995); }
.brand {
  margin-top: 26px;
  font-size: 11px;
  color: #c8cdd6;
  letter-spacing: .5px;
  font-weight: 600;
}
.brand .name { color: #86909c; font-weight: 700; }
.brand .dot { margin: 0 6px; }
</style>
</head>
<body>
<div class="card">
  <div class="icon-wrap">
    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M5 12.5 L10 17.5 L19 7.5" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
  </div>
  <div class="title">支付成功</div>
  <div class="desc">您已完成支付，请返回查看订单</div>
  <div class="amount-tip">✓ 订单已完成</div>
  <button class="btn" onclick="goBack()">返回商家</button>
  <div class="brand">
    <span class="name">信达网络</span>
    <span class="dot">·</span>
    安全支付
  </div>
</div>
<script>
function goBack() {
  if (window.history.length > 2) {
    window.history.back();
  } else if (window.opener) {
    window.opener.focus();
    window.close();
  } else {
    window.close();
  }
}
</script>
</body>
</html>
