<?php
require_once('includes/common.php');
$SITE = $conf['sitename'] ?? '信达科技';
$LOGIN_URL = '/user/login.php';
$REG_URL   = '/user/reg.php';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>收款监听 APP - <?php echo $SITE;?></title>
<link rel="stylesheet" href="/assets/vendor/font-awesome/4.7.0/css/font-awesome.min.css">
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:-apple-system,"PingFang SC","Microsoft YaHei",Arial,sans-serif;
       background:linear-gradient(180deg,#f0f7ff 0%,#e6f0fb 100%);
       color:#1a1d23; min-height:100vh; padding:40px 20px; }
.container { max-width:720px; margin:0 auto; }
.hero { text-align:center; margin-bottom:40px; }
.hero .logo { font-size:56px; margin-bottom:12px; }
.hero h1 { font-size:28px; font-weight:800; letter-spacing:-0.5px;
           background:linear-gradient(90deg,#1677ff,#40a9ff);
           -webkit-background-clip:text; -webkit-text-fill-color:transparent;
           background-clip:text; margin-bottom:8px; }
.hero p { color:#888; font-size:14px; }
.card { background:#fff; border:1px solid #e5eef8; box-shadow:0 2px 12px rgba(22,119,255,.06);
        border-radius:14px; padding:24px; margin-bottom:16px; }
.card h3 { font-size:16px; color:#1677ff; margin-bottom:14px; display:flex; align-items:center; gap:8px; }
.features { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.feature { display:flex; align-items:flex-start; gap:10px; font-size:14px; color:#555; }
.feature i { color:#1677ff; font-size:16px; margin-top:3px; flex-shrink:0; }
.price-row { display:flex; align-items:baseline; justify-content:center; gap:8px; margin:10px 0 20px; }
.price-num { font-size:56px; font-weight:900; color:#e74c3c; letter-spacing:-2px; }
.price-unit { font-size:16px; color:#888; }
.steps { list-style:none; }
.steps li { display:flex; gap:12px; padding:10px 0; color:#555; font-size:14px; }
.steps .num { width:24px; height:24px; border-radius:50%; background:#1677ff;
              color:#fff; display:flex; align-items:center; justify-content:center;
              font-size:12px; font-weight:700; flex-shrink:0; }
.btn { display:inline-flex; align-items:center; justify-content:center; gap:8px;
       padding:14px 28px; border-radius:10px; font-size:15px; font-weight:700;
       text-decoration:none; transition:all .2s; width:100%; margin-bottom:10px; }
.btn-primary { background:linear-gradient(135deg,#1677ff,#0e4fb8); color:#fff; box-shadow:0 4px 14px rgba(22,119,255,.3); }
.btn-primary:hover { transform:translateY(-1px); color:#fff; text-decoration:none; }
.btn-ghost { background:#fff; color:#1677ff; border:1.5px solid #1677ff; }
.btn-ghost:hover { background:#f0f7ff; color:#1677ff; text-decoration:none; }
.footer { text-align:center; color:#888; font-size:12px; margin-top:40px; padding-top:20px;
          border-top:1px solid #e5eef8; }
.tip { background:#f0f7ff; border-left:3px solid #1677ff; padding:12px 16px;
       border-radius:6px; font-size:13px; color:#333; line-height:1.7; margin-top:14px; }
</style>
</head>
<body>
<div class="container">

  <div class="hero">
    <div class="logo">📡</div>
    <h1>收款监听 APP</h1>
    <p>安卓免签收款 · 自动到账匹配 · 支持微信 / 支付宝</p>
  </div>

  <div class="card">
    <h3><i class="fa fa-star"></i> 产品功能</h3>
    <div class="features">
      <div class="feature"><i class="fa fa-check-circle"></i><div>微信 / 支付宝到账通知</div></div>
      <div class="feature"><i class="fa fa-check-circle"></i><div>自动匹配订单，无需人工</div></div>
      <div class="feature"><i class="fa fa-check-circle"></i><div>支持乐刷 / YeahKa 等设备</div></div>
      <div class="feature"><i class="fa fa-check-circle"></i><div>静默监听，不打扰手机</div></div>
      <div class="feature"><i class="fa fa-check-circle"></i><div>一码一设备，防共享</div></div>
      <div class="feature"><i class="fa fa-check-circle"></i><div>系统服务期内无限使用</div></div>
    </div>
  </div>

  <div class="card">
    <h3><i class="fa fa-tag"></i> 费用说明</h3>
    <div style="text-align:center;">
      <div class="price-row">
        <span class="price-num">100</span>
        <span class="price-unit">USDT / 月</span>
      </div>
      <div style="color:#555;font-size:14px;line-height:2;">
        ✅ 系统全部回调 / 接口，不限笔数<br>
        ✅ 含 <b>3 台</b> APK 监听设备<br>
        ✅ 额外设备 <b>50 USDT / 台 / 月</b><br>
        ✅ 不抽成、不按笔收费<br>
        ✅ 注册送 <b>2 天</b> 免费试用
      </div>
    </div>
  </div>

  <div class="card">
    <h3><i class="fa fa-list-ol"></i> 使用流程</h3>
    <ul class="steps">
      <li><span class="num">1</span><div>注册商户账号</div></li>
      <li><span class="num">2</span><div>提交实名认证（管理员审核）</div></li>
      <li><span class="num">3</span><div>商户后台 → 系统服务 → 开通（100U/月）</div></li>
      <li><span class="num">4</span><div>商户后台 → 我的激活码 → 领取</div></li>
      <li><span class="num">5</span><div>下载 APK → 输入激活码 + 密钥 → 完成</div></li>
    </ul>
    <div class="tip">
      💡 已注册商户请直接登录，<b>未认证</b>请先在后台提交认证；
      认证通过 + 系统服务有效，即可免费领取激活码。
    </div>
  </div>

  <div class="card">
    <a href="<?php echo $REG_URL;?>" class="btn btn-primary">
      <i class="fa fa-user-plus"></i> 立即注册商户
    </a>
    <a href="<?php echo $LOGIN_URL;?>" class="btn btn-ghost">
      <i class="fa fa-sign-in"></i> 已有账号，登录
    </a>
  </div>

  <div class="footer">
    <?php echo $SITE;?> · 湖北云泽信达网络科技有限公司<br>
    <a href="/" style="color:#1677ff;text-decoration:none;">返回首页</a>
  </div>

</div>
</body>
</html>