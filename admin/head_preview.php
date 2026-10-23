<?php
@header('Content-Type: text/html; charset=UTF-8');

$admin_cdnpublic = 0;
if($admin_cdnpublic==1){
	$cdnpublic = '//lib.baomitu.com/';
}elseif($admin_cdnpublic==2){
	$cdnpublic = 'https://s4.zstatic.net/ajax/libs/';
}elseif($admin_cdnpublic==4){
	$cdnpublic = 'https://cdnjs.snrat.com/ajax/libs/';
}else{
	$cdnpublic = '/assets/vendor/';
}
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
  <meta charset="utf-8"/>
  <meta name="renderer" content="webkit">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title><?php echo $title ?></title>
  <link href="<?php echo $cdnpublic?>twitter-bootstrap/3.4.1/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="../assets/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="../assets/css/bootstrap-table.css?v=1" rel="stylesheet"/>
  <link href="<?php echo $cdnpublic?>font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet"/>
  <script src="<?php echo $cdnpublic?>modernizr/2.8.3/modernizr.min.js"></script>
  <script src="<?php echo $cdnpublic?>jquery/3.4.1/jquery.min.js"></script>
  <script src="<?php echo $cdnpublic?>twitter-bootstrap/3.4.1/js/bootstrap.min.js"></script>
  <!--[if lt IE 9]>
    <script src="<?php echo $cdnpublic?>html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="<?php echo $cdnpublic?>respond.js/1.4.2/respond.min.js"></script>
  <![endif]-->
  <link rel="stylesheet" href="/assets/css/theme-heimao.css?v=20261008" />
  <style>
    .navbar-brand {
      display: flex;
      align-items: center;
      gap: 8px;
      line-height: 1;
      padding-top: 15px;
      padding-bottom: 15px;
    }
    .navbar-brand .brand-logo {
      display: block;
      width: auto;
      height: 28px;
      max-width: 160px;
      object-fit: contain;
    }
    .navbar-brand .brand-text {
      white-space: nowrap;
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


</style>
  <link rel="stylesheet" href="/assets/css/sidebar-light.css?v=1" />


<style id="xinda-admin-theme">
/* ====== 信达后台 · 顶栏统一 v2 ====== */
.navbar, .navbar-default, .navbar-inverse, .navbar-fixed-top,
header.navbar, .top-nav, .admin-header {
  background: linear-gradient(90deg, #0d5cd6 0%, #1677ff 100%) !important;
  background-image: linear-gradient(90deg, #0d5cd6 0%, #1677ff 100%) !important;
  border: none !important;
  box-shadow: 0 2px 8px rgba(13,92,214,.25) !important;
  padding-top: 6px !important;
  padding-bottom: 6px !important;
}

/* 菜单一行排开，别换行 */
.navbar .navbar-nav {
  display: flex !important;
  flex-wrap: nowrap !important;
  align-items: center;
}
.navbar .navbar-nav > li {
  white-space: nowrap;
}

/* 文字与图标 */
.navbar .navbar-brand,
.navbar .nav > li > a,
.navbar a,
.navbar .navbar-nav > li > a {
  color: #fff !important;
  font-weight: 600 !important;
  font-size: 14px !important;
  padding-top: 8px !important;
  padding-bottom: 8px !important;
}
.navbar .nav > li > a:hover,
.navbar .navbar-nav > li.open > a {
  background: rgba(255,255,255,.14) !important;
  color: #fff !important;
}
.navbar .navbar-brand {
  font-size: 19px !important;
  letter-spacing: .5px;
  padding-top: 4px !important;
  padding-bottom: 4px !important;
}

/* 主内容区留出顶栏空间，别被盖住 */

.app-content-

/* 全局字体加粗 */
body, .table, .panel, .form-control, .btn, td, th, label, input, select, textarea {
  font-weight: 500 !important;
}
h1,h2,h3,h4,h5 { font-weight: 700 !important; }
.table 
.btn { font-weight: 600 !important; border-radius: 6px !important; }

/* 表格卡片化 */
.table-responsive, .panel, .card {
  border-radius: 8px !important;
  box-shadow: 0 2px 8px rgba(15,23,42,.06) !important;
  border: none !important;
}
</style>

</head>
<script>
// 修补：将模板中相对 logo 路径快速替换为绝对带版本号
(function(){
  try{
    var vUrl = '/assets/img/logo.svg';
    document.addEventListener('DOMContentLoaded', function(){
      document.querySelectorAll('img').forEach(function(img){
        var s = img.getAttribute('src')||'';
        if(s.indexOf('/assets/img/logo.svg')!==-1 && s.indexOf('//assets/img/logo.svg')===-1){
          img.src = vUrl;
        }
      });
      document.querySelectorAll('[style]').forEach(function(el){
        var bg = el.style.backgroundImage || '';
        if(bg.indexOf('/assets/img/logo.svg')!==-1 && bg.indexOf('//assets/img/logo.svg')===-1){
          el.style.backgroundImage = bg.replace(/assets\/img\/logo\.png/g, '/assets/img/logo.svg');
        }
      });
    });
  }catch(e){}
})();
</script>
<body>
<?php if($islogin==1){?>
  <nav class="navbar navbar-fixed-top navbar-default">
    <div class="container-fluid">
      <div class="navbar-header">
        <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
          <span class="sr-only">导航按钮</span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
        </button>
        <a class="navbar-brand brand-logo-wrap" href="./">
          <span style="color:#fff;font-size:20px;font-weight:bold;">信达支付系统</span>
          <span class="brand-text">支付管理中心</span>
        </a>
      </div><!-- /.navbar-header -->
      <div id="navbar" class="collapse navbar-collapse">
  <ul class="nav navbar-nav">
    <li>
      <a href="javascript:;" onclick="if(history.length>1){history.back();}else{location.href='./';}" title="返回上一页">
        <i class="fa fa-arrow-left"></i> 返回
      </a>
    </li>
    <li class="<?php echo checkIfActive('index,')?>">
      <a href="./"><i class="fa fa-home"></i> 首页</a>
    </li>
    <li class="<?php echo checkIfActive('order,export,buyerstat,blacklist')?>">
      <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-list"></i> 收款订单<b class="caret"></b></a>
      <ul class="dropdown-menu">
        <li><a href="./order.php">订单管理</a></li>
        <li><a href="./export.php">导出订单</a></li>
        <li><a href="./buyerstat.php">支付用户统计</a></li>
        <li><a href="./blacklist.php">黑名单管理</a></li>
      </ul>
    </li>
    <li class="<?php echo checkIfActive('ulist,glist,gedit,group,record,uset,domain,ustat,qrcode,license,service,invitecode')?>">
      <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-user"></i> 商户管理<b class="caret"></b></a>
      <ul class="dropdown-menu">
        <li><a href="./ulist.php">用户列表</a></li>
        <li><a href="./service.php" style="color:#e74c3c;font-weight:700;">⭐ 系统服务管理</a></li>
        <li><a href="./license.php">授权码管理</a></li>
        <li><a href="./qrcode/">收款码管理</a></li>
        <li role="separator" class="divider"></li>
        <li><a href="./glist.php">用户组设置</a></li>
        <li><a href="./group.php">用户组购买</a></li>
        <li><a href="./record.php">资金明细</a></li>
        <li><a href="./ustat.php">支付统计</a></li>
        <?php if($conf['pay_domain_forbid']==1 || $conf['pay_domain_open']==1){?><li><a href="./domain.php">授权域名</a></li><?php }?>
        <?php if($conf['reg_open']==2){?><li><a href="./invitecode.php">邀请码管理</a></li><?php }?>
      </ul>
    </li>
    <li class="<?php echo checkIfActive('pay_channel,pay_roll,pay_type,pay_plugin,pay_weixin,applyments_channel,applyments_merchant,applyments_form')?>">
      <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-credit-card"></i> 支付接口<b class="caret"></b></a>
      <ul class="dropdown-menu">
        <li><a href="./pay_channel.php">支付通道</a></li>
        <li><a href="./pay_type.php">支付方式</a></li>
        <li><a href="./pay_plugin.php">支付插件</a></li>
        <li><a href="./pay_roll.php">支付通道轮询</a></li>
        <li><a href="./pay_weixin.php">公众号小程序</a></li>
        <?php if(class_exists('\\lib\\Applyments\\CommUtil')){?>
        <li role="separator" class="divider"></li>
        <li><a href="./applyments_channel.php">进件渠道管理</a></li>
        <li><a href="./applyments_merchant.php">进件商户管理</a></li>
        <?php }?>
      </ul>
    </li>
  </ul>

  <ul class="nav navbar-nav navbar-right">
    <li class="<?php echo checkIfActive('set,gonggao,set_wxkf,telegram,set_totp,iproute')?>">
      <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-cog"></i> 系统设置<b class="caret"></b></a>
      <ul class="dropdown-menu">
        <li class="dropdown-header">🏠 基础配置</li>
        <li><a href="./set.php?mod=site"><i class="fa fa-home fa-fw"></i> 网站信息配置</a></li>
        <li><a href="./set.php?mod=template"><i class="fa fa-paint-brush fa-fw"></i> 首页模板配置</a></li>
        <li><a href="./set.php?mod=upimg"><i class="fa fa-image fa-fw"></i> 网站 Logo 上传</a></li>
        <li><a href="./gonggao.php"><i class="fa fa-bullhorn fa-fw"></i> 网站公告配置</a></li>
        <li role="separator" class="divider"></li>
        <li class="dropdown-header">💳 支付与结算</li>
        <li><a href="./set.php?mod=pay"><i class="fa fa-credit-card fa-fw"></i> 支付相关配置</a></li>
        <li><a href="./set.php?mod=settle"><i class="fa fa-balance-scale fa-fw"></i> 结算规则配置</a></li>
        <li><a href="./set.php?mod=transfer"><i class="fa fa-exchange fa-fw"></i> 转账付款配置</a></li>
        <li role="separator" class="divider"></li>
        <li class="dropdown-header">🔔 通知与集成</li>
        <li><a href="./set.php?mod=notice"><i class="fa fa-bell fa-fw"></i> 消息提醒配置</a></li>
        <li><a href="./set.php?mod=mail"><i class="fa fa-envelope fa-fw"></i> 邮箱与短信配置</a></li>
        <li><a href="./telegram.php"><i class="fa fa-paper-plane fa-fw"></i> 机器人配置</a></li>
        <li><a href="./set_wxkf.php"><i class="fa fa-comments fa-fw"></i> H5 跳转微信客服支付</a></li>
        <li role="separator" class="divider"></li>
        <li class="dropdown-header">🛡️ 风控与安全</li>
        <li><a href="./set.php?mod=risk"><i class="fa fa-shield fa-fw"></i> 风控检测配置</a></li>
        <li><a href="./set.php?mod=certificate"><i class="fa fa-id-card fa-fw"></i> 实名认证配置</a></li>
        <li><a href="./set_totp.php"><i class="fa fa-lock fa-fw"></i> 谷歌验证器</a></li>
        <li role="separator" class="divider"></li>
        <li class="dropdown-header">⚙️ 高级</li>
        <li><a href="./set.php?mod=oauth"><i class="fa fa-key fa-fw"></i> 快捷登录配置</a></li>
        <li><a href="./set.php?mod=proxy"><i class="fa fa-globe fa-fw"></i> 中转代理配置</a></li>
        <li><a href="./iproute.php"><i class="fa fa-map fa-fw"></i> 地区智能路由</a></li>
        <li><a href="./set.php?mod=cron"><i class="fa fa-clock-o fa-fw"></i> 计划任务配置</a></li>
        <li><a href="./set.php?mod=account"><i class="fa fa-key fa-fw"></i> 修改密码</a></li>
      </ul>
    </li>
    <li class="<?php echo checkIfActive('risk,log,clean,gettoken')?>">
      <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-book"></i> 帮助 & 日志<b class="caret"></b></a>
      <ul class="dropdown-menu">
        <li><a href="/help/" target="_blank"><i class="fa fa-question-circle"></i> 帮助中心</a></li>
        <li role="separator" class="divider"></li>
        <li><a href="./risk.php">风控记录</a></li>
        <li><a href="./log.php">登录日志</a></li>
        <li><a href="./clean.php">数据清理</a></li>
        <li><a href="./gettoken.php">获取用户标识</a></li>
      </ul>
    </li>
    <li class="<?php echo checkIfActive('settle,settle_batch,slist,transfer,transfer_add,transfer_export,transfer_red,transfer_batch,transfer_stat,ps_receiver,ps_order,complain,complain_info,mchrisk')?>">
      <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-cube"></i> 其他功能<b class="caret"></b></a>
      <ul class="dropdown-menu">
        <li><a href="./slist.php">结算管理</a></li>
        <li><a href="./settle.php">批量结算</a></li>
        <li role="separator" class="divider"></li>
        <li><a href="./transfer.php">付款记录</a></li>
        <li><a href="./transfer_add.php">新增付款</a></li>
        <li><a href="./transfer_red.php">创建红包</a></li>
        <li><a href="./transfer_stat.php">付款统计</a></li>
        <li><a href="./transfer_export.php">导出付款记录</a></li>
        <li role="separator" class="divider"></li>
        <li><a href="./ps_receiver.php">分账规则</a></li>
        <li><a href="./ps_order.php">分账记录</a></li>
        <?php if(class_exists('\\lib\\Complain\\CommUtil')){?>
        <li role="separator" class="divider"></li>
        <li><a href="./complain.php">支付交易投诉</a></li>
        <?php }?>
        <?php if(class_exists('\\lib\\WxMchRisk')){?><li><a href="./mchrisk.php">渠道商户违规记录</a></li><?php }?>
      </ul>
    </li>
    <li><a href="./login.php?logout" onclick="return confirm('是否确定退出登录？')"><i class="fa fa-power-off"></i> 退出</a></li>
  </ul>
</div><!-- /.navbar-collapse -->
</div><!-- /.container -->
</nav><!-- /.navbar -->
<?php }?><style>
/* 下拉菜单配色统一 */
.dropdown-menu {
  background: #ffffff !important;
  border: 1px solid #e6edf7 !important;
  border-radius: 8px !important;
  box-shadow: 0 6px 20px rgba(13,92,214,.12) !important;
  padding: 6px 0 !important;
  min-width: 150px !important;
}
.dropdown-menu > li > a {
  color: #334155 !important;
  font-weight: 500 !important;
  font-size: 13px !important;
  padding: 8px 18px !important;
  transition: all .15s;
}
.dropdown-menu > li > a:hover,
.dropdown-menu > li > a:focus {
  background: #f0f5ff !important;
  color: #1677ff !important;
}
.dropdown-menu > .divider {
  background: #eef2f8 !important;
  margin: 4px 0 !important;
}
/* 顶栏悬停时，当前菜单高亮 */
.navbar .navbar-nav > li > a:hover,
.navbar .navbar-nav > .open > a {
  background: rgba(255,255,255,.15) !important;
  color: #fff !important;
}
</style>
