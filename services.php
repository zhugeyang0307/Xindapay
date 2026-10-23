<?php
require_once('includes/common.php');
$TG_LINK = 'https://t.me/xindapay1H5_bot';
$SITE = $conf['sitename'] ?? '信达科技';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>技术服务中心 - <?php echo $SITE;?></title>
<link rel="stylesheet" href="/assets/vendor/font-awesome/4.7.0/css/font-awesome.min.css">
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:-apple-system,"PingFang SC","Microsoft YaHei",Arial,sans-serif;
       background:linear-gradient(180deg,#f0f7ff 0%,#e6f0fb 100%);
       color:#1a1d23; min-height:100vh; padding:40px 20px; }
.container { max-width:720px; margin:0 auto; }
.hero { text-align:center; margin-bottom:44px; }
.hero .logo { font-size:60px; margin-bottom:14px; }
.hero h1 { font-size:30px; font-weight:800; letter-spacing:-0.5px;
           background:linear-gradient(90deg,#1677ff,#40a9ff);
           -webkit-background-clip:text; -webkit-text-fill-color:transparent;
           background-clip:text; margin-bottom:10px; }
.hero p { color:#888; font-size:15px; }
.hero .tag { display:inline-block; margin-top:14px; padding:6px 16px;
             background:#e6f4ff; color:#1677ff;
             border-radius:999px; font-size:13px; font-weight:600; }
.card { background:#fff; border:1px solid #e5eef8; box-shadow:0 2px 14px rgba(22,119,255,.06);
        border-radius:14px; padding:26px; margin-bottom:16px; }
.card h3 { font-size:17px; color:#1677ff; margin-bottom:16px; display:flex; align-items:center; gap:10px; }
.service { display:flex; gap:16px; padding:16px 0; border-bottom:1px solid #f0f5fb; }
.service:last-child { border-bottom:none; padding-bottom:0; }
.service:first-of-type { padding-top:0; }
.service .icon { flex-shrink:0; width:48px; height:48px; border-radius:12px;
                 background:linear-gradient(135deg,#1677ff,#0d5cd6);
                 color:#fff; display:flex; align-items:center; justify-content:center;
                 font-size:22px; font-weight:800; }
.service .body { flex:1; }
.service .body h4 { font-size:16px; color:#1a1d23; margin-bottom:6px; font-weight:700; }
.service .body p { color:#666; font-size:13px; line-height:1.6; margin-bottom:8px; }
.service .body .tags { display:flex; flex-wrap:wrap; gap:6px; }
.service .body .tags span { display:inline-block; padding:3px 10px;
                            background:#e6f4ff; color:#1677ff;
                            font-size:11px; border-radius:6px; }
.steps { list-style:none; }
.steps li { display:flex; gap:14px; padding:12px 0; color:#555; font-size:14px; align-items:flex-start; }
.steps .num { width:26px; height:26px; border-radius:50%; background:#1677ff;
              color:#fff; display:flex; align-items:center; justify-content:center;
              font-size:13px; font-weight:800; flex-shrink:0; }
.steps b { color:#1677ff; }
.cta { text-align:center; margin:32px 0 16px; }
.cta h3 { color:#1677ff; font-size:20px; margin-bottom:8px; }
.cta p { color:#888; font-size:13px; margin-bottom:20px; }
.btn-tg { display:inline-flex; align-items:center; gap:10px; padding:16px 40px;
          background:#229ED9; color:#fff; border-radius:12px; font-size:16px; font-weight:700;
          text-decoration:none; transition:all .2s; }
.btn-tg:hover { background:#1a7fb0; transform:translateY(-2px); box-shadow:0 6px 20px rgba(34,158,217,.3); }
.footer { text-align:center; color:#555; font-size:12px; margin-top:40px; padding-top:20px;
          border-top:1px solid #e5eef8; }
.footer a { color:#1677ff; text-decoration:none; }
</style>
</head>
<body>
<div class="container">

  <div class="hero">
    <div class="logo">🐈‍⬛</div>
    <h1>信达科技 技术服务中心</h1>
    <p>支付系统 · 商城开发 · 全栈定制</p>
    <div class="tag">价格私聊 · 量身定制</div>
  </div>

  <div class="card">
    <h3><i class="fa fa-cubes"></i> 核心服务</h3>

    <div class="service">
      <div class="icon">💳</div>
      <div class="body">
        <h4>支付接口代开发</h4>
        <p>对接支付宝 / 微信官方支付，匹配你的线上商城前端页面，交易合规、流水清晰。</p>
        <div class="tags">
          <span>官方直连</span>
          <span>合规流水</span>
          <span>全自动回调</span>
          <span>前端对接</span>
        </div>
      </div>
    </div>

    <div class="service">
      <div class="icon">🛒</div>
      <div class="body">
        <h4>独立卡密商城</h4>
        <p>全自动发货 · 多商户 · 自定义 UI。无需第三方平台，数据完全自主。</p>
        <div class="tags">
          <span>全自动发货</span>
          <span>多商户</span>
          <span>后台管理</span>
          <span>定制 UI</span>
        </div>
      </div>
    </div>

    <div class="service">
      <div class="icon">📱</div>
      <div class="body">
        <h4>收款系统软件</h4>
        <p>上传收款码即可收款，APK 实时监听到账。无资质要求，资金直进自己账户。</p>
        <div class="tags">
          <span>无资质要求</span>
          <span>APK 监听</span>
          <span>自动匹配</span>
          <span>自主收款</span>
        </div>
      </div>
    </div>

    <div class="service">
      <div class="icon">🔧</div>
      <div class="body">
        <h4>其他定制</h4>
        <p>网站 / App 开发、支付系统搭建、服务器运维、系统对接，只要你能想到。</p>
        <div class="tags">
          <span>网站开发</span>
          <span>App 开发</span>
          <span>系统对接</span>
          <span>运维支持</span>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <h3><i class="fa fa-list-ol"></i> 服务流程</h3>
    <ul class="steps">
      <li><span class="num">1</span><div><b>需求沟通</b> — TG 联系客服，说明你的需求</div></li>
      <li><span class="num">2</span><div><b>报价方案</b> — 根据你的需求量身定制报价</div></li>
      <li><span class="num">3</span><div><b>签订合同</b> — 双方确认后开工</div></li>
      <li><span class="num">4</span><div><b>开发交付</b> — 开发 → 测试 → 验收</div></li>
      <li><span class="num">5</span><div><b>售后支持</b> — 长期技术维护</div></li>
    </ul>
  </div>

  <div class="cta">
    <h3>价格私聊 · 量身定制</h3>
    <p>不同需求，不同方案。联系客服，1 对 1 沟通</p>
    <a href="<?php echo $TG_LINK;?>" target="_blank" class="btn-tg">
      <i class="fa fa-paper-plane"></i> 联系客服咨询
    </a>
  </div>

  <div class="footer">
    <?php echo $SITE;?> · 湖北云泽信达网络科技有限公司<br>
    <a href="/">返回首页</a>
  </div>

</div>
</body>
</html>
