<?php

// ===== 绝对优先级：手机端 App 路由拦截 =====
if (!function_exists('is_mobile_router')) {
    function is_mobile_router() { 
        return preg_match("/(android|iphone|ipad|ipod|windows phone|blackberry|mobile)/i", $_SERVER['HTTP_USER_AGENT']); 
    }
}
if (is_mobile_router()) {
    include __DIR__ . '/m_head.php';
    return; // 彻底拦截，不再输出下方所有 PC 端代码
}
// ============================================

@header('Content-Type: text/html; charset=UTF-8');
// 封禁逻辑已彻底移除
// ===== 强制二次验证拦截 =====
$__current_script = basename($_SERVER['SCRIPT_NAME'] ?? '');
$__skip = ['force_bind.php', 'login.php', 'logout.php', 'findpwd.php'];
// 只有上级商户（sub_level < 2）才强制验证，下级码商随便他们
if (($userrow['bind_required'] ?? 0) == 1
    && (int)$userrow['sub_level'] < 2 
    && empty($userrow['totp_open'])
    && !in_array($__current_script, $__skip)) {
    header('Location: ./force_bind.php');
    exit;
}

switch($conf['user_style']){
	case 1: $style=['bg-black','bg-black','bg-white']; break;
	case 2: $style=['bg-dark','bg-white','bg-dark']; break;
	case 3: $style=['bg-dark','bg-dark','bg-light']; break;
	case 4: $style=['bg-info','bg-info','bg-black']; break;
	case 5: $style=['bg-info','bg-info','bg-white']; break;
	case 6: $style=['bg-primary','bg-primary','bg-dark']; break;
	case 7: $style=['bg-primary','bg-primary','bg-white']; break;
	default: $style=['bg-black','bg-white','bg-black']; break;
}
$groupconfig = getGroupConfig($userrow['gid']);
$conf = array_merge($conf, $groupconfig);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8" />
  <title><?php echo $title?> | <?php echo $conf['sitename']?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
  <link rel="stylesheet" href="<?php echo $cdnpublic?>twitter-bootstrap/3.4.1/css/bootstrap.min.css" type="text/css" />
  <link rel="stylesheet" href="<?php echo $cdnpublic?>animate.css/3.7.2/animate.min.css" type="text/css" />
  <link rel="stylesheet" href="<?php echo $cdnpublic?>font-awesome/4.7.0/css/font-awesome.min.css" type="text/css" />
  <link rel="stylesheet" href="/user/assets/css/font.css" type="text/css" />
  <link rel="stylesheet" href="/user/assets/css/app.css" type="text/css" />
  <link rel="stylesheet" href="/assets/css/bootstrap-table.css?v=1"/>
  <link rel="stylesheet" href="/assets/css/theme-heimao.css?v=20261008" />
<style>
/* ========== 蓝色主题 - 内联强覆盖 ========== */
.app-header, 
header.app-header.navbar,
.app-header .navbar-header {
  background: linear-gradient(90deg, #ffffff 0%, #f5f7fa 100%) !important;
  background-image: none !important;
  box-shadow: 0 1px 0 rgba(15,23,42,.06) !important;
}
.app-header .navbar-collapse,
.app-header .bg-white,
.app-header .box-shadow {
  background: linear-gradient(90deg, #ffffff 0%, #f5f7fa 100%) !important;
  background-image: none !important;
}
.app-header .navbar-brand {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #001529 !important;
  font-weight: 600;
  line-height: 1;
}
.app-header .navbar-brand .brand-logo {
  display: block;
  width: auto;
  height: 32px;
  max-width: 160px;
  object-fit: contain;
}
.app-header .navbar-brand .brand-text {
  white-space: nowrap;
}
.app-header .navbar-brand .fa-btc { color: #1890ff !important; }

/* 顶栏右侧图标/文字 */
.app-header .nav > li > a {
  color: rgba(31,45,61,.8) !important;
}
.app-header .nav > li > a:hover {
  background: rgba(24,144,255,.08) !important;
  color: #1677ff !important;
}

/* 侧栏 */
.app-aside,
aside.app-aside,
.app-aside.bg-black {
  background: #001529 !important;
  background-image: none !important;
}
.app-aside .navi > ul > li > a {
  color: rgba(255,255,255,.75) !important;
  background: transparent !important;
  border-radius: 6px;
  margin: 2px 8px;
}
.app-aside .navi > ul > li > a:hover {
  background: #0b1f33 !important;
  color: #fff !important;
}
.app-aside .navi > ul > li.active > a,
.app-aside .navi > ul > li.open > a {
  background: linear-gradient(90deg, #1677ff 0%, #0d5cd6 100%) !important;
  color: #fff !important;
  box-shadow: inset 0 0 0 1px rgba(255,255,255,.05);
}
.app-aside .navi > ul > li.active > a .icon,
.app-aside .navi > ul > li.open > a .icon {
  color: #fff !important;
}
.app-aside .navi ul.nav-sub > li > a {
  color: rgba(255,255,255,.6) !important;
  background: rgba(255,255,255,.02) !important;
}
.app-aside .navi ul.nav-sub > li > a:hover {
  color: #fff !important;
  background: rgba(22,119,255,.08) !important;
}
.app-aside .navi .text-muted,
.app-aside .text-muted {
  color: rgba(255,255,255,.35) !important;
}
.app-aside .line.dk,
.app-aside .line {
  background: rgba(255,255,255,.08) !important;
}

/* 侧栏底部进度条 */
.app-aside .progress-bar-info { background: #1677ff !important; }
.app-aside .progress-bar-primary { background: #40a9ff !important; }

/* 侧栏折叠按钮 */
.app-header .navbar-btn,
.app-header .btn.no-shadow {
  color: rgba(31,45,61,.8) !important;
}

/* 主按钮统一蓝色 */
.btn-primary {
  background: #1677ff !important;
  border-color: #1677ff !important;
  color: #fff !important;
}
.btn-primary:hover, .btn-primary:focus, .btn-primary:active {
  background: #0d5cd6 !important;
  border-color: #0d5cd6 !important;
  color: #fff !important;
}

/* 徽章/标签 */
.badge { background: #1677ff !important; color: #fff !important; }
.bg-info { background: #1677ff !important; color: #fff !important; }
.label-warning { background: #1677ff !important; color: #fff !important; }

/* ========== 侧栏顶部公司名优化 ========== */
.app-aside .navbar-brand,
.app-aside .aside-wrap > .brand {
  padding: 15px 20px !important;
}

/* ===== XINDA UI PATCH ===== */
/* 顶栏左侧跟侧栏连成一体 */
.app-header .navbar-header,
.app-header .navbar-header.bg-black {
  background: #001529 !important;
  background-image: none !important;
  border-bottom: none !important;
}
.app-header .navbar-header .navbar-brand {
  color: #fff !important;
}
.app-header .navbar-header .navbar-brand .brand-text {
  color: #fff !important;
}
/* 侧栏底部装饰 */
.xinda-version {
  padding: 18px 20px 8px;
  color: rgba(255,255,255,.35);
  font-size: 12px;
  text-align: center;
  letter-spacing: .3px;
}
.xinda-version .dot {
  display: inline-block;
  width: 6px; height: 6px;
  background: #22c55e;
  border-radius: 50%;
  margin-right: 5px;
  vertical-align: middle;
  box-shadow: 0 0 6px #22c55e;
}
/* 主内容标题更紧凑 */
.app-content-body .bg-light.lter.b-b > h1 {
  font-size: 20px;
  font-weight: 500;
}


/* ===== 手机端顶栏重构 ===== */
@media (max-width: 767px) {
    .app-header { padding: 0 12px !important; display: flex; align-items: center; justify-content: space-between; }
    .app-header .navbar-header { width: 100%; display: flex !important; align-items: center; justify-content: space-between; float: none !important; }
    
    /* 统一样式的按钮 */
    .mobile-btn { 
        background: rgba(255,255,255,0.15) !important; 
        color: #fff !important; 
        padding: 6px 14px !important; 
        border-radius: 20px !important; 
        font-size: 14px !important; 
        font-weight: 600 !important;
        text-decoration: none !important; 
        display: inline-flex !important; 
        align-items: center; 
        gap: 6px;
        border: none;
    }
    .mobile-btn:hover { background: rgba(255,255,255,0.25) !important; }
    .mobile-btn i { font-size: 16px; }
    
    /* 圆形首字母头像 */
    .header-avatar { 
        width: 36px; height: 36px; border-radius: 50%; 
        background: #fff; display: flex; align-items: center; justify-content: center; 
        color: #1677ff; font-weight: 800; font-size: 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15); cursor: pointer;
    }
    
    /* 下拉菜单对齐 */
    .header-dropdown .dropdown-menu-right { right: 0; left: auto; min-width: 140px; }
}

/* ===== 终极修复手机端顶栏 ===== */
@media (max-width: 767px) {
    .app-header { padding: 0 15px !important; display: flex; align-items: center; justify-content: space-between; height: 54px !important; }
    
    .visible-xs-flex { display: flex !important; align-items: center; }
    .mobile-right-wrap { gap: 14px; }
    
    .mobile-btn { 
        background: rgba(255,255,255,0.15) !important; 
        color: #fff !important; 
        padding: 6px 14px !important; 
        border-radius: 20px !important; 
        font-size: 14px !important; 
        font-weight: 600 !important;
        text-decoration: none !important; 
        display: inline-flex !important; 
        align-items: center; 
        gap: 6px;
        border: none;
    }
    .mobile-btn:hover { background: rgba(255,255,255,0.25) !important; }
    .mobile-btn i { font-size: 16px; }
    
    .header-avatar { 
        width: 36px; height: 36px; border-radius: 50%; 
        background: #fff; display: flex; align-items: center; justify-content: center; 
        color: #1677ff; font-weight: 800; font-size: 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15); cursor: pointer;
        border: 2px solid rgba(255,255,255,0.8);
    }
    .header-dropdown .dropdown-menu-right { right: 0; left: auto; min-width: 150px; margin-top: 12px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); border: none; }
}

/* ===== PWA App 全屏模式适配 ===== */
@media all and (display-mode: standalone) {
    /* 当以 App 模式打开时，顶部状态栏安全区留白 */
    body { padding-top: env(safe-area-inset-top, 20px) !important; }
    .app-header { top: env(safe-area-inset-top, 20px) !important; }
    .app-aside { padding-top: env(safe-area-inset-top, 20px) !important; }
    /* 隐藏“返回”按钮（因为 App 模式下没有网页历史） */
    .mobile-btn[href*="history.back"] { display: none !important; }
}
</style>

  <link rel="stylesheet" href="/assets/css/sidebar-light.css?v=1" />




<style id="xinda-head-final">
/* ===== 顶栏一条蓝，左上角不再单独黑 ===== */
header.app-header,
header.app-header.navbar,
.app-header {
  background: linear-gradient(90deg, #0d5cd6 0%, #1677ff 100%) !important;
  background-image: linear-gradient(90deg, #0d5cd6 0%, #1677ff 100%) !important;
  border: none !important;
  box-shadow: 0 2px 8px rgba(13,92,214,.25) !important;
  height: 54px !important;
}

/* 左上角品牌区：透明，不单独黑底 */
header.app-header .navbar-header,
header.app-header .navbar-header.bg-black {
  background: transparent !important;
  background-image: none !important;
  border: none !important;
  box-shadow: none !important;
  padding: 0 20px !important;
  height: 54px !important;
}

/* 品牌文字：白色 */
header.app-header .navbar-brand,
header.app-header .navbar-brand span,
header.app-header .navbar-header .navbar-brand {
  color: #fff !important;
  font-size: 19px !important;
  font-weight: 700 !important;
  letter-spacing: .5px !important;
  padding: 0 !important;
  line-height: 54px !important;
}

/* 右上菜单区：透明 */
header.app-header .navbar-collapse,
header.app-header .collapse.navbar-collapse,
header.app-header .bg-white,
header.app-header .box-shadow,
header.app-header .pos-rlt {
  background: transparent !important;
  background-image: none !important;
  border: none !important;
  box-shadow: none !important;
}

header.app-header .nav > li > a,
header.app-header .navbar-nav > li > a {
  color: #fff !important;
  font-weight: 600 !important;
}
header.app-header .nav > li > a:hover,
header.app-header .nav > li.open > a {
  background: rgba(255,255,255,.15) !important;
  color: #fff !important;
}
header.app-header .thumb-sm img {
  border: 2px solid rgba(255,255,255,.6) !important;
}

/* 主内容给顶栏让位 */
body { padding-top: 54px !important; }

/* ===== 页面统一视觉修正 ===== */
.app-content-body {
  background: linear-gradient(180deg, #f6f9ff 0%, #f5f7fb 100%) !important;
}
.app-content-body .wrapper-md {
  padding-top: 18px !important;
  padding-bottom: 18px !important;
}
.app-content-body .panel,
.app-content-body .panel-default,
.app-content-body .box,
.app-content-body .panel-body {
  border-radius: 14px !important;
  border: 1px solid #eaf0f8 !important;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04) !important;
}
.app-content-body .panel-heading {
  border-bottom: 1px solid #edf2f7 !important;
  background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%) !important;
  color: #0f172a !important;
  font-weight: 700 !important;
  padding: 16px 20px !important;
}
.app-content-body .panel-body {
  background: #fff !important;
  padding: 20px !important;
}
.app-content-body .table,
.app-content-body .table-responsive {
  border-radius: 12px !important;
  overflow: hidden !important;
}
.app-content-body .table > thead > tr > th {
  background: #f5f8ff !important;
  color: #495c78 !important;
  border-bottom: 1px solid #e7edf7 !important;
  font-size: 12.5px !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.04em !important;
}
.app-content-body .table > tbody > tr > td {
  border-bottom: 1px solid #eef3f9 !important;
  background: #fff !important;
  color: #1f2937 !important;
  vertical-align: middle !important;
}
.app-content-body .table > tbody > tr:hover > td {
  background: #f8fbff !important;
}
.app-content-body .btn,
.app-content-body .btn-default,
.app-content-body .btn-primary,
.app-content-body .btn-success,
.app-content-body .btn-info,
.app-content-body .btn-danger,
.app-content-body .btn-warning {
  border-radius: 10px !important;
  font-weight: 600 !important;
  transition: all 0.2s ease !important;
}
.app-content-body .btn-primary,
.app-content-body .btn-success,
.app-content-body .btn-info {
  background: linear-gradient(135deg, #1677ff 0%, #0d5cd6 100%) !important;
  border: none !important;
  box-shadow: 0 6px 18px rgba(22, 119, 255, 0.18) !important;
}
.app-content-body .btn-default {
  background: #fff !important;
  border: 1px solid #dfe8f3 !important;
  color: #334155 !important;
}
.app-content-body .btn-danger {
  background: #fff1f0 !important;
  border: 1px solid #f8c9c4 !important;
  color: #d94a41 !important;
}
.app-content-body .form-control,
.app-content-body select,
.app-content-body textarea {
  border: 1px solid #dfe8f3 !important;
  border-radius: 10px !important;
  box-shadow: none !important;
  background: #fff !important;
  color: #1f2937 !important;
}
.app-content-body .form-control:focus,
.app-content-body select:focus,
.app-content-body textarea:focus {
  border-color: #7bb6ff !important;
  box-shadow: 0 0 0 3px rgba(22, 119, 255, 0.10) !important;
}
.app-content-body .alert {
  border-radius: 12px !important;
  box-shadow: none !important;
}
</style>


  <!-- PWA 支持 -->
  <link rel="manifest" href="/user/manifest.json">
  <meta name="theme-color" content="#1677ff">
  <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="信达支付">
  <link rel="apple-touch-icon" href="/assets/img/logo.svg">
</head>
<script>
// 修补：将模板中相对 logo 路径快速替换为绝对带版本号，避免批量改模板
(function(){
  try{
    var vUrl = '/assets/img/logo.svg';
    // 替换 img[src] 中的相对路径
    document.addEventListener('DOMContentLoaded', function(){
      document.querySelectorAll('img').forEach(function(img){
        var s = img.getAttribute('src')||'';
        if(s.indexOf('/assets/img/logo.svg')!==-1 && s.indexOf('//assets/img/logo.svg')===-1){
          img.src = vUrl;
        }
      });
      // 替换内联 style 中的 background 图片
      document.querySelectorAll('[style]').forEach(function(el){
        var bg = el.style.backgroundImage || '';
        if(bg.indexOf('/assets/img/logo.svg')!==-1 && bg.indexOf('//assets/img/logo.svg')===-1){
          el.style.backgroundImage = bg.replace(/assets\/img\/logo\.png/g, '/assets/img/logo.svg');
        }
      });
      // 替换内联 <style> 内容
      document.querySelectorAll('style').forEach(function(st){
        st.innerHTML = st.innerHTML.replace(/url\((['"]?)assets\/img\/logo\.png(['"]?)\)/g, 'url($1/assets/img/logo.svg$2)');
      });
    });
  }catch(e){console && console.error && console.error(e)}
})();
</script>
<body>
<div class="app app-header-fixed  ">
  <!-- header -->
  <header id="header" class="app-header navbar" role="menu">
    <!-- 手机端左侧：返回按钮 -->
    <a href="javascript:history.back();" class="mobile-btn visible-xs-flex" style="display:none;">
        <i class="fa fa-arrow-left"></i> 返回
    </a>

    <!-- PC端 Logo -->
    <a href="/user/" class="navbar-brand text-lt brand-logo-wrap hidden-xs">
        <span style="color:#fff;font-size:18px;font-weight:700;"><?php echo ((int)$userrow["sub_level"] >= 2) ? "信达支付 - 码商后台" : "信达支付 - 主控后台"; ?></span>
    </a>

    <!-- 手机端右侧：汉堡菜单 + 头像 -->
    <div class="mobile-right-wrap visible-xs-flex" style="display:none;">
        <a href="javascript:;" class="mobile-btn" ui-toggle="off-screen" target=".app-aside" ui-scroll="app">
            <i class="fa fa-bars" style="font-size:16px;"></i>
        </a>
        <div class="dropdown header-dropdown">
            <div class="header-avatar" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <?php echo mb_substr($userrow['username'] ?: 'U', 0, 1); ?>
            </div>
            <ul class="dropdown-menu dropdown-menu-right">
                <li><a href="/user/userinfo.php?mod=account"><i class="fa fa-user fa-fw"></i> 个人资料</a></li>
                <li><a href="/user/editinfo.php"><i class="fa fa-cog fa-fw"></i> 修改资料</a></li>
                <li role="separator" class="divider"></li>
                <li><a href="/user/logout.php" style="color:#e74c3c;"><i class="fa fa-power-off fa-fw"></i> 退出登录</a></li>
            </ul>
        </div>
    </div>

    <div class="collapse pos-rlt navbar-collapse box-shadow <?php echo $style[1]?>">
        <!-- buttons -->
        <div class="nav navbar-nav hidden-xs">
          <a href="#" class="btn no-shadow navbar-btn" ui-toggle="app-aside-folded" target=".app">
            <i class="fa fa-dedent fa-fw text"></i>
            <i class="fa fa-indent fa-fw text-active"></i>
          </a>
        </div>
        <!-- / buttons -->

        <!-- nabar right -->
        <ul class="nav navbar-nav navbar-left">
  <li>
    <a href="javascript:;" onclick="if(history.length>1){history.back();}else{location.href='/user/';}" title="返回上一页">
      <i class="fa fa-arrow-left"></i> 返回
    </a>
  </li>
</ul>
<ul class="nav navbar-nav navbar-right">
          <li class="dropdown">
            <a href="#" data-toggle="dropdown" class="dropdown-toggle clear" data-toggle="dropdown">
              <span class="thumb-sm avatar pull-right m-t-n-sm m-b-n-sm m-l-sm">
                <img src="<?php echo ($userrow['qq'])?'/assets/img/logo.svg?x='.$userrow['qq'].'&src_uin='.$userrow['qq'].'&fid='.$userrow['qq'].'&spec=100&url_enc=0&referer=bu_interface&term_type=PC':'assets/img/user.png'?>">
                <i class="on md b-white bottom"></i>
              </span>
              <span class="hidden-sm hidden-md" style="text-transform:uppercase;"><?php echo $uid?></span> <b class="caret"></b>
            </a>
            <!-- dropdown -->
            <ul class="dropdown-menu animated fadeInRight w">
              <li>
                <a href="/user/index.php">
                  <?php echo ((int)$userrow["sub_level"] >= 2) ? "👤 码商管理后台" : "👑 主控管理后台 (接口对接/分销)"; ?>
                </a>
              </li>
              <li>
                <a href="/user/editinfo.php">
                  <span>修改资料</span>
                </a>
              </li>
			  <li>
                <a href="/user/userinfo.php?mod=account">
                  <span>修改密码</span>
                </a>
              </li>
              <li class="divider"></li>
              <li>
                <a ui-sref="access.signin" href="/user/login.php?logout">退出登录</a>
              </li>
            </ul>
            <!-- / dropdown -->
          </li>
        </ul>
        <!-- / navbar right -->
      </div>
      <!-- / navbar collapse -->
  </header>
  <!-- / header -->
  <!-- aside -->
  <aside id="aside" class="app-aside hidden-xs <?php echo $style[2]?>">
      <div class="aside-wrap">
        <div class="navi-wrap">

          <!-- nav -->
                    <nav ui-nav class="navi clearfix">
  <?php $__isSub = ((int)$userrow['sub_level'] >= 2); ?>
  <ul class="nav">
    
    <!-- 1. 控制台 -->
    <li class="hidden-folded padder m-t m-b-sm text-muted text-xs">
      <span>控制台</span>
    </li>
    <li class="<?php echo checkIfActive('index,')?>">
      <a href="/user/">
        <i class="glyphicon glyphicon-home icon text-primary-dker"></i>
        <span class="font-bold">管理后台</span>
      </a>
    </li>

    <!-- 2. 收款业务 -->
    <li class="line dk hidden-folded"></li>
    <li class="hidden-folded padder m-t m-b-sm text-muted text-xs"><span>收款业务</span></li>
    <li class="<?php echo checkIfActive('qrcode,qrcode_route,test_center,coder_test,myip,onecode,license')?>">
      <a href class="auto">
        <span class="pull-right text-muted"><i class="fa fa-fw fa-angle-right text"></i><i class="fa fa-fw fa-angle-down text-active"></i></span>
        <i class="fa fa-mobile icon text-primary"></i>
        <span>收款与工具</span>
      </a>
      <ul class="nav nav-sub dk">
        <li><a href="/user/qrcode/"><span>收款码管理</span></a></li>
        <li><a href="/user/usdt_setting.php"><span>USDT 收款配置</span></a></li>
        <li><a href="/user/license.php"><span>APK鉴权密钥</span></a></li>
        <li><a href="/user/myip.php"><span>IP 检测</span></a></li>
        <?php if($conf['onecode']==1 || $userrow['open_code'] == 1){?>
        <li><a href="/user/qrcode_route.php"><span>收款码路由</span></a></li>
        <li><a href="/user/onecode.php"><span>聚合收款</span></a></li>
        <?php }?>
        <?php if(!empty($conf['appurl'])){?>
        <li><a href="<?php echo $conf['appurl']?>" target="blank"><span>APP下载</span></a></li>
        <?php }?>
      </ul>
    </li>

    <!-- 3. 数据与订单 -->
    <li class="line dk hidden-folded"></li>
    <li class="hidden-folded padder m-t m-b-sm text-muted text-xs"><span>数据与订单</span></li>
    <li class="<?php echo checkIfActive('order,record,settle')?>">
      <a href class="auto">
        <span class="pull-right text-muted"><i class="fa fa-fw fa-angle-right text"></i><i class="fa fa-fw fa-angle-down text-active"></i></span>
        <i class="fa fa-bar-chart icon text-info"></i>
        <span>我的数据</span>
      </a>
      <ul class="nav nav-sub dk">
        <li><a href="/user/order.php"><span>订单记录</span></a></li>
        <li><a href="/user/record.php"><span>资金明细</span></a></li>
        <?php if((int)$userrow['sub_level'] < 2){ ?><li><a href="/user/settle.php"><span>结算管理</span></a></li><?php } ?>
      </ul>
    </li>

    <!-- 4. 下级团队 -->
    <li class="line dk hidden-folded"></li>
    <li class="hidden-folded padder m-t m-b-sm text-muted text-xs"><span>下级团队</span></li>
    <li class="<?php echo checkIfActive('sub_list,sub_create,sub_edit,sub_review,sub_bills,sub_orders,sub_qrcodes,bill_sub')?>">
      <a href class="auto">
        <span class="pull-right text-muted"><i class="fa fa-fw fa-angle-right text"></i><i class="fa fa-fw fa-angle-down text-active"></i></span>
        <i class="fa fa-sitemap icon text-warning"></i>
        <span>下级账户管理</span>
      </a>
      <ul class="nav nav-sub dk">
        <li><a href="/user/sub_list.php"><span>下级商户列表</span></a></li>
        <li><a href="/user/sub_create.php"><span style="color:#10b981;font-weight:bold;">➕ 创建下级码商</span></a></li>
        <?php if($DB->getColumn("SELECT COUNT(*) FROM pay_user WHERE upid=".(int)$userrow['uid']) > 0){?>
        <li><a href="/user/sub_review.php"><span>下级推送审批</span></a></li>
        <?php }?>
        <li><a href="/user/bill.php"><span>下级账单核对</span></a></li>
        <li><a href="/user/sub_orders.php"><span>下级订单</span></a></li>
        <li><a href="/user/sub_qrcodes.php"><span>下级收款码</span></a></li>
      </ul>
    </li>

    <!-- 5. 推送中心 -->
    <li class="line dk hidden-folded"></li>
    <li class="hidden-folded padder m-t m-b-sm text-muted text-xs"><span>推送中心</span></li>
    <?php if($__isSub){?>
    <li class="<?php echo checkIfActive('sub_push')?>">
      <a href="/user/sub_push.php">
        <i class="fa fa-paper-plane icon text-info"></i>
        <span>我的推送资料</span>
      </a>
    </li>
    <?php } else { ?>
    <li class="<?php echo checkIfActive('telegram,mail_push,mail_marketing')?>">
      <a href class="auto">
        <span class="pull-right text-muted"><i class="fa fa-fw fa-angle-right text"></i><i class="fa fa-fw fa-angle-down text-active"></i></span>
        <i class="fa fa-paper-plane icon text-info"></i>
        <span>推送与通知</span>
      </a>
      <ul class="nav nav-sub dk">
        <li><a href="/user/telegram.php"><span>机器人管理</span></a></li>
        <li><a href="/user/mail_push.php"><span>邮件推送</span></a></li>
        <li><a href="/user/mail_marketing/index.php"><span>邮件营销</span></a></li>
        <li><a href="/user/mail_marketing/templates.php"><span>邮件模板</span></a></li>
        <li><a href="/user/mail_marketing/track_sdk.php"><span>追踪接入</span></a></li>
      </ul>
    </li>
    <?php } ?>

    <!-- 6. 账号与安全 -->
    <?php if(!$__isSub){?>
    <li class="line dk hidden-folded"></li>
    <li class="hidden-folded padder m-t m-b-sm text-muted text-xs"><span>账号与安全</span></li>
    <li class="<?php echo checkIfActive('userinfo,editinfo,totp')?>">
      <a href class="auto">
        <span class="pull-right text-muted"><i class="fa fa-fw fa-angle-right text"></i><i class="fa fa-fw fa-angle-down text-active"></i></span>
        <i class="fa fa-user-circle-o icon text-success-lter"></i>
        <span>账号设置</span>
      </a>
      <ul class="nav nav-sub dk">
        <?php if((int)$userrow["sub_level"] == 1){?><li><a href="/user/userinfo.php?mod=api"><span style="color:#eab308;font-weight:bold;">🔑 接口对接 (API信息)</span></a></li><?php }?>
        <li><a href="/user/editinfo.php"><span>修改资料</span></a></li>
        <li><a href="/user/brand_setting.php"><span>品牌与域名设置</span></a></li>
        <li><a href="/user/userinfo.php?mod=account"><span>修改密码</span></a></li>
        <li><a href="/user/totp.php"><span>谷歌验证器</span></a></li>
      </ul>
    </li>
    <?php }?>

    <!-- 7. 服务与充值 -->
    <li class="line dk hidden-folded"></li>
    <li class="<?php echo checkIfActive('service,recharge,usdt_recharge,domain')?>">
      <a href class="auto">
        <span class="pull-right text-muted"><i class="fa fa-fw fa-angle-right text"></i><i class="fa fa-fw fa-angle-down text-active"></i></span>
        <i class="fa fa-diamond icon text-danger"></i>
        <span>服务与充值</span>
      </a>
      <ul class="nav nav-sub dk">
        <li><a href="/user/recharge.php"><span>USDT 充值</span></a></li>
        <li><a href="/user/domain.php"><span>授权域名</span></a></li>
      </ul>
    </li>

    <!-- 8. 帮助中心 -->
    <li class="line dk hidden-folded"></li>
    <li class="<?php echo checkIfActive('app_help')?>">
      <a href="/help/">
        <i class="fa fa-book icon text-success"></i>
        <span>帮助中心</span>
      </a>
    </li>

  </ul>
</nav>
          <!-- nav -->

          <!-- aside footer -->
          <div class="xinda-version"><span class="dot"></span>v1.0.0 · 系统运行正常</div>
          <!-- / aside footer -->
        </div>
      </div>
  </aside>
  <!-- / aside -->
  <!-- content -->

<?php if(!empty($_SESSION['viewing_as'])){?>
<a href="/user/sub_switch.php?act=back" style="position:fixed;bottom:24px;right:24px;z-index:99999;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;padding:14px 24px;border-radius:50px;font-weight:700;font-size:14px;text-decoration:none;box-shadow:0 4px 18px rgba(245,158,11,.5);display:flex;align-items:center;gap:8px;animation:pulse 2s infinite;">
  <span style="font-size:18px;">👁</span> 返回我的后台
</a>
<style>
@keyframes pulse{0%,100%{transform:scale(1);}50%{transform:scale(1.05);}}
</style>
<?php }?>
