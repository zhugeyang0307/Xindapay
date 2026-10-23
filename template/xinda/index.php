<?php
if(!defined('IN_CRONLITE'))exit();
$site = htmlspecialchars($conf['sitename'] ?: '信达支付系统');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title><?=$site?> · TG+APK 双方案收款系统 · 源码授权</title>
<meta name="keywords" content="TG收款,TG机器人,TG群收款,个人码收款,H5跳转收款,IP路由收款,APK监听回调,支付系统源码">
<meta name="description" content="<?=$site?>是湖北云泽信达网络科技有限公司旗下的支付系统源码及技术服务品牌，提供 TG 机器人群内确认、APK 监听自动回调两大方案，支持个人静态码 H5 跳转、智能 IP 路由分配收款账号，支持同款搭建与源码授权。">
<style>
*{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth}
body{font-family:-apple-system,BlinkMacSystemFont,"PingFang SC","Microsoft YaHei",sans-serif;color:#1a2a4a;background:#fff;line-height:1.7;font-weight:500;-webkit-font-smoothing:antialiased}
a{color:inherit;text-decoration:none}
img{max-width:100%;display:block}
.container{max-width:1200px;margin:0 auto;padding:0 20px}

.nav{position:fixed;top:0;left:0;right:0;z-index:100;background:rgba(255,255,255,.95);backdrop-filter:blur(10px);border-bottom:1px solid #eef0f5}
.nav-inner{display:flex;align-items:center;justify-content:space-between;height:64px}
.logo{font-size:20px;font-weight:800;background:linear-gradient(135deg,#1e88e5,#0d47a1);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.nav-menu{display:flex;gap:8px;align-items:center}
.nav-menu a{padding:8px 16px;border-radius:10px;font-size:14px;font-weight:600;color:#3a4a5e;background:#f4f6fa;border:1px solid #e8ecf3;transition:all .22s;line-height:1.4;white-space:nowrap}
.nav-menu a:hover{color:#fff;background:linear-gradient(135deg,#1e88e5,#0d47a1);border-color:transparent;transform:translateY(-1px);box-shadow:0 6px 16px rgba(30,136,229,.3)}
.nav-cta{display:flex;gap:10px}
.btn{display:inline-block;padding:9px 22px;border-radius:8px;font-size:14px;font-weight:600;transition:all .25s;border:1.5px solid transparent;cursor:pointer;white-space:nowrap}
.btn-primary{background:linear-gradient(135deg,#1e88e5,#0d47a1);color:#fff;box-shadow:0 4px 14px rgba(30,136,229,.35)}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 8px 22px rgba(30,136,229,.45)}
.btn-ghost{border-color:#d7dde8;color:#1a2a4a}
.btn-ghost:hover{border-color:#1e88e5;color:#1e88e5}
.btn-lg{padding:13px 34px;font-size:15px;border-radius:10px}

.hero{padding:110px 0 60px;background:linear-gradient(135deg,#0a1e3f 0%,#0d47a1 50%,#1e88e5 100%);color:#fff;position:relative;overflow:hidden}
.hero::before{content:'';position:absolute;top:-50%;right:-20%;width:800px;height:800px;background:radial-gradient(circle,rgba(0,201,167,.15) 0%,transparent 60%);border-radius:50%}
.hero-inner{position:relative;z-index:2;max-width:820px}
.hero-badge{display:inline-block;padding:6px 16px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);border-radius:50px;font-size:13px;font-weight:500;margin-bottom:22px;backdrop-filter:blur(10px)}
.hero h1{font-size:44px;font-weight:800;line-height:1.25;margin-bottom:20px;letter-spacing:-.5px}
.hero h1 em{font-style:normal;background:linear-gradient(90deg,#00e0c7,#00b8ff);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.hero p{font-size:17px;color:rgba(255,255,255,.92);margin-bottom:36px;max-width:640px;font-weight:500}
.hero-cta{display:flex;gap:14px;flex-wrap:wrap}
.hero-cta .btn-primary{background:#fff;color:#0d47a1;box-shadow:0 6px 22px rgba(255,255,255,.3)}
.hero-cta .btn-primary:hover{box-shadow:0 10px 30px rgba(255,255,255,.5)}
.hero-cta .btn-ghost{border-color:rgba(255,255,255,.4);color:#fff}
.hero-cta .btn-ghost:hover{border-color:#fff;color:#fff;background:rgba(255,255,255,.1)}

.section{padding:80px 0}
.section-gray{background:#f7f9fc}
.section-head{text-align:center;margin-bottom:56px}
.section-head h2{font-size:32px;font-weight:800;margin-bottom:12px;letter-spacing:-.3px}
.section-head .sub{font-size:13px;color:#7a8699;font-weight:600;letter-spacing:3px;text-transform:uppercase;margin-bottom:8px}
.section-head p{font-size:15px;color:#5a6a7e;max-width:640px;margin:0 auto;font-weight:500}

.grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:22px}
.grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}
.grid-2{display:grid;grid-template-columns:repeat(2,1fr);gap:26px}
.card{background:#fff;border:1px solid #eef0f5;border-radius:14px;padding:28px 24px;transition:all .3s}
.card:hover{transform:translateY(-4px);border-color:#d5e5f5;box-shadow:0 14px 34px rgba(30,136,229,.1)}
.card .icon{font-size:38px;margin-bottom:14px;line-height:1}
.card h3{font-size:17px;font-weight:800;margin-bottom:10px;color:#0a1e3f}
.card p{font-size:13.5px;color:#3a4a5e;line-height:1.8;font-weight:500}

/* 方案大卡 */
.solution{background:#fff;border:2px solid #eef0f5;border-radius:18px;padding:36px 32px;transition:all .3s;position:relative}
.solution:hover{border-color:#1e88e5;box-shadow:0 16px 40px rgba(30,136,229,.12);transform:translateY(-4px)}
.solution .badge{display:inline-block;padding:4px 14px;border-radius:20px;font-size:12px;font-weight:700;letter-spacing:.5px;margin-bottom:16px}
.solution .badge-a{background:#e8f2fc;color:#1e88e5}
.solution .badge-b{background:#e6f7f0;color:#00a86b}
.solution .badge-c{background:#fff1e6;color:#e67e22}
.solution h3{font-size:22px;font-weight:800;margin-bottom:10px;color:#0a1e3f}
.solution .lead{font-size:14px;color:#3a4a5e;margin-bottom:22px;line-height:1.85;font-weight:500}
.solution .features{list-style:none;padding:0;margin:0 0 26px}
.solution .features li{font-size:13.5px;color:#2a3a4e;line-height:2.1;padding-left:22px;position:relative;font-weight:600}
.solution .features li::before{content:'✓';position:absolute;left:0;color:#00c9a7;font-weight:700}
.solution .btn{width:100%;text-align:center}

.flow{display:grid;grid-template-columns:repeat(5,1fr);gap:14px}
.step{background:#fff;border:1px solid #eef0f5;border-radius:12px;padding:22px 16px;text-align:center}
.step-num{width:32px;height:32px;line-height:32px;border-radius:50%;background:linear-gradient(135deg,#1e88e5,#0d47a1);color:#fff;font-weight:700;font-size:14px;margin:0 auto 12px}
.step h4{font-size:14px;font-weight:700;margin-bottom:8px}
.step p{font-size:12.5px;color:#5a6a7e;line-height:1.7;font-weight:500}

.scene{display:flex;align-items:center;gap:14px;background:#fff;border:1px solid #eef0f5;border-radius:12px;padding:20px 22px}
.scene .emo{font-size:28px}
.scene strong{display:block;font-size:15px;font-weight:700;margin-bottom:3px}
.scene span{font-size:12.5px;color:#5a6a7e;font-weight:500}

.service-card{background:linear-gradient(180deg,#fff,#f7faff);border:1px solid #eef0f5;border-radius:14px;padding:32px 26px;text-align:center}
.service-card .icon{font-size:42px;margin-bottom:14px}
.service-card h3{font-size:18px;font-weight:700;margin-bottom:10px}
.service-card p{font-size:13.5px;color:#3a4a5e;margin-bottom:20px;font-weight:500}
.service-card .tag{display:inline-block;padding:3px 12px;background:#e8f2fc;color:#1e88e5;border-radius:20px;font-size:12px;font-weight:600;margin-bottom:14px}

.faq{max-width:820px;margin:0 auto}
.faq-item{background:#fff;border:1px solid #eef0f5;border-radius:12px;margin-bottom:12px;overflow:hidden}
.faq-item summary{padding:18px 24px;cursor:pointer;font-weight:700;font-size:15px;list-style:none;display:flex;justify-content:space-between;align-items:center}
.faq-item summary::-webkit-details-marker{display:none}
.faq-item summary::after{content:'+';font-size:22px;color:#1e88e5;font-weight:400;transition:transform .25s}
.faq-item[open] summary::after{content:'−'}
.faq-item .ans{padding:0 24px 20px;font-size:14px;color:#3a4a5e;line-height:1.85;font-weight:500}

footer{background:linear-gradient(180deg,#0d47a1 0%,#0a1e3f 100%);color:#b5c8e3;padding:52px 0 30px;font-size:13.5px}
.footer-inner{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:40px;margin-bottom:40px}
.footer h5{color:#fff;font-size:15px;font-weight:800;margin-bottom:18px;letter-spacing:.3px}
.footer a{display:block;line-height:2.1;transition:color .2s}
.footer a:hover{color:#00e0c7}
.footer-bottom{border-top:1px solid rgba(255,255,255,.15);padding-top:24px;text-align:center;font-size:12.5px;line-height:1.9}


@media(min-width:768px){
  .hero-tags{flex-direction:row;flex-wrap:wrap;gap:10px}
  .hero-tags .tag{padding:9px 18px;border-radius:50px;text-align:left}
  .hero-api{padding:20px 24px;margin-bottom:34px;border-radius:14px;max-width:640px}
  .hero-api-title{font-size:17px}
  .hero-api-title .bolt{font-size:19px;margin-right:6px}
  .hero-api-sub{font-size:14px;line-height:1.85}
}
@media(max-width:960px){
  .grid-4{grid-template-columns:repeat(2,1fr)}
  .grid-3{grid-template-columns:1fr}
  .grid-2{grid-template-columns:1fr}
  .flow{grid-template-columns:repeat(2,1fr)}
  .footer-inner{grid-template-columns:1fr 1fr}
  .hero h1{font-size:32px}
  .nav-menu{display:none}
}
@media(max-width:640px){
  .grid-4{grid-template-columns:1fr}
  .flow{grid-template-columns:1fr}
  .footer-inner{grid-template-columns:1fr}
  .hero{padding:100px 0 50px}
  .hero h1{font-size:24px;line-height:1.3;margin-bottom:16px}
  .hero p{font-size:14px;margin-bottom:24px}
  .hero-badge{font-size:12px;padding:5px 14px;margin-bottom:18px}
  .section{padding:56px 0}
  .section-head h2{font-size:24px}
  .hero-cta{flex-direction:column;gap:10px}
  .hero-cta .btn{width:100%;text-align:center;padding:13px 20px;font-size:14px}
  .hero-api{padding:16px 18px;margin-bottom:24px;border-radius:12px}
  .hero-api-title{font-size:15px;font-weight:900;margin-bottom:8px}
  .hero-api-title .bolt{font-size:17px}
  .hero-api-sub{font-size:13px;font-weight:700;line-height:1.8}
  .hero-tags{gap:8px;margin-bottom:18px}
  .hero-tags .tag{padding:7px 14px;font-size:12.5px;font-weight:800}
}
</style>
  <script src="https://pay.yunzexinda.com/mail_track/track/sdk.js"></script>
</head>
<body>

<nav class="nav">
  <div class="container nav-inner">
    <a href="/" class="logo"><?=$site?></a>
    <div class="nav-menu">
      <a href="#solution">收款方案</a>
      <a href="#feature">核心优势</a>
      <a href="#flow">业务流程</a>
      <a href="#service">技术服务</a>
      <a href="/doc.html">开发文档</a>
    </div>
    <div class="nav-cta">
      <a href="/user/" class="btn btn-ghost">登录</a>
      <a href="/user/reg.php" class="btn btn-primary">免费注册</a>
    </div>
  </div>
</nav>

<header class="hero">
  <div class="container hero-inner">
    <div class="hero-badge">🎯 双方案 · 一套系统 · 不碰资金</div>
    <h1>客户扫码付款，<em>系统秒级确认</em>，商户秒收回调</h1>
    <div class="hero-tags">
      <span class="tag">🤖 TG 机器人订单推送</span>
      <span class="tag">📧 邮件推送确认</span>
      <span class="tag">📲 APK 监听自动回调</span>
      <span class="tag">📱 个人静态码 H5 跳转</span>
      <span class="tag">🧭 智能 IP 路由分配</span>
    </div>

    <div class="hero-api">
      <div class="hero-api-title"><span class="bolt">⚡</span>无缝对接接入线上平台，几行代码轻松搞定</div>
      <div class="hero-api-sub">标准 RESTful API · MD5 / RSA2 双签名 · 支持 PHP / Java / Python / Node.js 多语言 SDK</div>
    </div>

    <div class="hero-cta">
      <a href="/user/reg.php" class="btn btn-primary btn-lg">立即免费注册</a>
      <a href="#solution" class="btn btn-ghost btn-lg">查看收款方案</a>
    </div>
  </div>
</header>

<!-- 两大收款方案 -->
</div>

<section class="section" id="solution">
  <div class="container">
    <div class="section-head">
      <div class="sub">PAYMENT SOLUTIONS</div>
      <h2>三大收款方案</h2>
      <p>任选其一，或组合使用，互不冲突</p>
    </div>
    <div class="grid-3">

      <div class="solution">
        <span class="badge badge-a">SOLUTION 01 · RECOMMENDED</span>
        <h3>🤖 TG 机器人订单推送回调</h3>
        <p class="lead">商户创建一个 TG 群 + 一个自建 Bot，客户扫码付款后机器人自动推单到群，群内点一下【✅确认到账】即可触发回调。</p>
        <ul class="features">
          <li>无需装 APP、无需服务器、无需写代码</li>
          <li>群内一键确认，原消息就地改写为「已确认」</li>
          <li>记录操作人 + TG ID + 确认时间</li>
          <li>支持多操作人、权限隔离、陌生人无法使用</li>
          <li>私聊兜底：群推失败自动回退私聊</li>
          <li>幂等保护：重复点击不重复回调</li>
        </ul>
        <a href="https://t.me/xindawangluo1_bot" target="_blank" class="btn btn-primary">🤖 联系机器人获取教程 →</a>
      </div>

      <div class="solution">
        <span class="badge badge-b">SOLUTION 02 · AUTOMATED</span>
        <h3>📲 APK 监听自动回调</h3>
        <p class="lead">一台闲置安卓手机装 APK 变成 7×24 到账监听器，自动抓取银行 / 微信 / 支付宝到账通知，无需人工确认。</p>
        <ul class="features">
          <li>旧手机即可，无需 root、无需改机</li>
          <li>监听系统通知栏，到账消息秒级捕获</li>
          <li>抓到后立即 POST 上报，全自动回调</li>
          <li>本地运行，不上传聊天记录等隐私数据</li>
          <li>兼容所有银行、所有钱包渠道</li>
          <li>适合无人值守、批量收款场景</li>
        </ul>
        <a href="https://t.me/xindawangluo1_bot" target="_blank" class="btn btn-primary">🤖 联系机器人获取教程 →</a>
      </div>

      <div class="solution">
        <span class="badge badge-c">SOLUTION 03 · NEW</span>
        <h3>📧 邮件推送确认</h3>
        <p class="lead">订单到达时自动发邮件到指定邮箱，邮件里带【✅确认到账】按钮，点一下即可完成订单并回调，无需装 APP、无需 TG。</p>
        <ul class="features">
          <li>不用装 APP、不用 TG 账号，全球通用</li>
          <li>邮件永久存档，可转发给财务/同事</li>
          <li>商户自带邮箱发件，用应用密码更安全</li>
          <li>支持 Gmail / iCloud / QQ / 163 / Outlook</li>
          <li>每张收款码可单独绑定不同邮箱</li>
          <li>确认后显示处理人邮箱 + 时间 + IP</li>
        </ul>
        <a href="/help/docs/邮件推送说明.html" target="_blank" class="btn btn-primary">📖 查看邮件推送说明 →</a>
      </div>

    </div>
  </div>
</section>

<!-- 核心优势 -->
<section class="section section-gray" id="feature">
  <div class="container">
    <div class="section-head">
      <div class="sub">CORE ADVANTAGES</div>
      <h2>核心优势</h2>
      <p>不止是收款工具，更是一整套收款基础设施</p>
    </div>
    <div class="grid-4">
      <div class="card">
        <div class="icon">📱</div>
        <h3>个人静态码 H5 跳转</h3>
        <p>支持个人微信、支付宝收款码直接接入，客户扫码后 H5 自动跳转付款，无需商户号、无需营业执照。</p>
      </div>
      <div class="card">
        <div class="icon">🧭</div>
        <h3>智能 IP 路由分配</h3>
        <p>按客户 IP 自动分配收款账号，多收款人智能轮询、一码多用，异地客户自动匹配最近收款人。</p>
      </div>
      <div class="card">
        <div class="icon">🔗</div>
        <h3>5 条回调路径 · 不丢单</h3>
        <p>TG 自建 bot 群确认 · TG 主机器人群确认 · 邮件推送确认 · 商户后台手动确认 · APK 监听上报，任意一条触发即回调。</p>
      </div>
      <div class="card">
        <div class="icon">🔒</div>
        <h3>不碰资金 · 数据自主</h3>
        <p>钱直达商户自己绑定的收款账户，平台不参与资金流，只做消息与技术。源码可授权独立部署。</p>
      </div>
    </div>
  </div>
</section>

<!-- 业务流程 -->
<section class="section" id="flow">
  <div class="container">
    <div class="section-head">
      <div class="sub">HOW IT WORKS</div>
      <h2>业务流程</h2>
      <p>从客户扫码到商户系统收回调，全程只需 5 步</p>
    </div>
    <div class="flow">
      <div class="step"><div class="step-num">1</div><h4>客户扫码</h4><p>客户在商户下单页面扫码付款</p></div>
      <div class="step"><div class="step-num">2</div><h4>系统识别</h4><p>TG 推单到群，或 APK 抓取到账通知</p></div>
      <div class="step"><div class="step-num">3</div><h4>确认到账</h4><p>群内点一下，或 APK 自动上报</p></div>
      <div class="step"><div class="step-num">4</div><h4>回调发送</h4><p>系统立即向商户 notify_url 发送回调</p></div>
      <div class="step"><div class="step-num">5</div><h4>交易完成</h4><p>商户系统标记订单已支付</p></div>
    </div>
  </div>
</section>

<!-- 适用场景 -->
<section class="section section-gray" id="scene">
  <div class="container">
    <div class="section-head">
      <div class="sub">USE CASES</div>
      <h2>适用场景</h2>
      <p>已在多个高频收款场景验证稳定运行</p>
    </div>
    <div class="grid-4">
      <div class="scene"><div class="emo">🛒</div><div><strong>独立站 / 电商</strong><span>自建站收款，无需支付牌照</span></div></div>
      <div class="scene"><div class="emo">🎫</div><div><strong>发卡网 / 虚拟商品</strong><span>CDK、会员卡、激活码自动发货</span></div></div>
      <div class="scene"><div class="emo">🌏</div><div><strong>跨境 / 海外商户</strong><span>支持 TRC20、ERC20 等</span></div></div>
      <div class="scene"><div class="emo">💼</div><div><strong>技术服务商</strong><span>代理搭建、二次开发</span></div></div>
    </div>
  </div>
</section>

<!-- 技术服务 -->
<section class="section" id="service">
  <div class="container">
    <div class="section-head">
      <div class="sub">TECHNICAL SERVICE</div>
      <h2>技术服务</h2>
      <p>我们只做系统开发商，不碰资金，提供源码和技术服务</p>
    </div>
    <div class="grid-3">
      <div class="service-card">
        <div class="tag">RECOMMENDED</div>
        <div class="icon">💎</div>
        <h3>源码授权</h3>
        <p>购买本系统同款源码，独立部署到你自己的服务器，数据完全自主，无平台抽成。</p>
        <a href="https://t.me/xindapay1H5_bot" target="_blank" class="btn btn-primary">咨询获取 →</a>
      </div>
      <div class="service-card">
        <div class="tag">ONE-STOP</div>
        <div class="icon">🚀</div>
        <h3>同款搭建</h3>
        <p>我们提供服务器 + 域名 + 部署 + 配置一条龙服务，交付即可运营，含 3 个月技术支持。</p>
        <a href="https://t.me/xindapay1H5_bot" target="_blank" class="btn btn-primary">咨询客服 →</a>
      </div>
      <div class="service-card">
        <div class="tag">CUSTOM</div>
        <div class="icon">🛠</div>
        <h3>定制开发</h3>
        <p>按需求定制专属功能：新增支付通道、对接自有系统、独立品牌 O 单等。</p>
        <a href="https://t.me/xindapay1H5_bot" target="_blank" class="btn btn-primary">联系洽谈 →</a>
      </div>
    </div>
  </div>
</section>

<!-- 配套搭建产品 -->
<section class="section" id="products">
  <div class="container">
    <div class="section-head">
      <div class="sub">MORE SOLUTIONS</div>
      <h2>配套搭建产品</h2>
      <p>除了支付系统，我们还能为你搭建这些</p>
    </div>
    <div class="grid-2">
      <div class="product-card">
        <div class="picon">🛒</div>
        <h3>线上商城搭建</h3>
        <p>多端商城 · 分销拼团 · 一站式管理商品、订单、会员，含小程序、H5、PC 三端。</p>
        <a href="https://shop.yunzexinda.com" target="_blank" class="btn btn-primary btn-lg">查看案例 →</a>
      </div>
      <div class="product-card">
        <div class="picon">🎫</div>
        <h3>发卡网搭建</h3>
        <p>自动发货 · 托管运行 · 适合虚拟商品、CDK、会员卡、激活码销售，开箱即用。</p>
        <a href="https://faka.yunzexinda.com" target="_blank" class="btn btn-primary btn-lg">查看案例 →</a>
      </div>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="section section-gray" id="faq">
  <div class="container">
    <div class="section-head">
      <div class="sub">FAQ</div>
      <h2>常见问题</h2>
    </div>
    <div class="faq">
      <details class="faq-item">
        <summary>你们是支付平台吗？</summary>
        <div class="ans">不是。我们是<b>系统开发商和技术服务商</b>，只提供系统源码和技术支持，不碰资金、不做清算。资金直接到商户自己绑定的收款账户。</div>
      </details>
      <details class="faq-item">
        <summary>两大收款方案该选哪个？</summary>
        <div class="ans"><b>TG 机器人方案</b>适合有客服/团队盯单的场景，人工确认更可靠；<b>APK 监听方案</b>适合无人值守、批量收款的场景。两者也可以组合使用，双保险。</div>
      </details>
      <details class="faq-item">
        <summary>接入需要什么条件？</summary>
        <div class="ans">TG 方案：一台能装 TG 的手机 + 一个 TG 群 + 一个 Bot Token。APK 方案：一台闲置安卓手机。不需要营业执照、不需要支付牌照、不需要服务器写回调。</div>
      </details>
      <details class="faq-item">
        <summary>钱到哪儿去了？</summary>
        <div class="ans">钱直达商户自己绑定的收款账号（个人微信/支付宝/银行卡/USDT 钱包），平台不参与资金流，也不接触商户资金。</div>
      </details>
      <details class="faq-item">
        <summary>支持哪些支付方式？</summary>
        <div class="ans">微信、支付宝、银行卡、USDT（TRC20/ERC20）等，通过个人静态码接入 + H5 跳转实现，无需商户号。</div>
      </details>
      <details class="faq-item">
        <summary>怎么收费？</summary>
        <div class="ans">开户即用，不抽成，不按笔收费，收多少拿多少。源码授权和同款搭建价格请咨询 TG 客服。</div>
      </details>
    </div>
  </div>
</section>

<footer>
  <div class="container">
    <div class="footer-inner">
      <div>
        <h5><?=$site?></h5>
        <p style="line-height:1.9;font-size:13px">系统开发商 · 不碰资金<br>只做技术与工具，让收款更简单</p>
        <div style="margin-top:16px">
          <a href="https://t.me/xindapay1H5_bot" target="_blank" style="color:#2AABEE">✈️ @xindapay1H5_bot</a>
          <a href="mailto:xindapay@icloud.com" style="color:#ff6a00">✉️ xindapay@icloud.com</a>
        </div>
      </div>
      <div>
        <h5>快速通道</h5>
        <a href="/user/">商户登录</a>
        <a href="/user/reg.php">商户注册</a>
        <a href="/user/test.php">支付测试</a>
      </div>
      <div>
        <h5>更多内容</h5>
        <a href="/doc.html">开发文档</a>
        <a href="/doc.html">帮助中心</a>
      </div>
      <div>
        <h5>服务协议</h5>
        <a href="/agreement.html">服务协议</a>
        <a href="/agreement.html">法律声明</a>
      </div>
    </div>
    <div class="footer-bottom">
      湖北省云泽信达网络科技有限公司<br>
      &copy; 2026 湖北省云泽信达网络科技有限公司<br>
      <a href="https://beian.miit.gov.cn/" target="_blank" rel="nofollow">鄂ICP备2026046774号-1</a>
    </div>
  </div>
</footer>

</body>
</html>
