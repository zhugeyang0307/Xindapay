<?php
@header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
  <meta charset="utf-8"/>
  <meta name="renderer" content="webkit">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title><?php echo isset($title) ? $title . ' - ' : ''; ?>支付管理中心</title>
  
  <link href="/assets/vendor/twitter-bootstrap/3.4.1/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="/assets/vendor/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet"/>
  <script src="/assets/vendor/jquery/3.4.1/jquery.min.js"></script>
  <script src="/assets/vendor/twitter-bootstrap/3.4.1/js/bootstrap.min.js"></script>
  
  <style>
    /* ================= 提取官网的高级 UI 基因 ================= */
    body {
        margin: 0 !important; 
        padding-top: 75px !important; 
        background-color: #f7f9fc !important; /* 官网同款高级浅灰背景 */
        font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", sans-serif !important;
        color: #1a2a4a !important; /* 官网同款深蓝黑字体 */
        font-size: 14px !important;
        font-weight: 500 !important;
        -webkit-font-smoothing: antialiased; /* 字体抗锯齿，更清晰 */
    }
    
    /* 全局排版深度统一 */
    p, span, div, li, td { font-weight: 500 !important; }
    h1, h2, h3, h4, h5, h6, b, strong, th { font-weight: 800 !important; color: #0a1e3f !important; }
    a { color: #1e88e5 !important; text-decoration: none !important; transition: all .2s; }
    a:hover { color: #0d47a1 !important; }

    /* 核心布局容器 */
    body > .container, 
    body > .container-fluid,
    .container {
        width: 100% !important;
        max-width: 1400px !important; /* 限制一个最大宽度显得更专业，不散架 */
        margin: 0 auto !important;
        padding: 0 24px 24px 24px !important;
        box-sizing: border-box !important;
        float: none !important;
    }

    /* 官网同款渐变顶栏 */
    .navbar {
        background: #fff !important; /* 官网是白底毛玻璃，后台固定用白底更清爽 */
        border-bottom: 1px solid #eef0f5 !important;
        box-shadow: 0 4px 20px rgba(10,30,63,.03) !important;
        border-radius: 0 !important;
        min-height: 64px !important;
        margin-bottom: 0 !important;
        position: fixed !important; top: 0; left: 0; right: 0; z-index: 1050;
    }
    .navbar-brand {
        color: #0a1e3f !important; font-size: 20px !important; font-weight: 800 !important;
        height: 64px !important; line-height: 64px !important; padding: 0 20px 0 0 !important;
        background: linear-gradient(135deg,#1e88e5,#0d47a1); -webkit-background-clip: text; -webkit-text-fill-color: transparent; /* 官网同款渐变字 */
    }
    /* 导航项 */
    .navbar-nav > li > a {
        color: #3a4a5e !important; font-size: 14.5px !important; font-weight: 600 !important;
        padding: 0 16px !important; height: 64px !important; line-height: 64px !important;
        background: transparent !important; transition: all 0.2s ease;
    }
    .navbar-nav > li > a > i.fa { margin-right: 6px; font-size: 15px; color: #1e88e5 !important; }
    .navbar-nav > li > a:hover, .navbar-nav > li.open > a, .navbar-nav > li.active > a {
        background: #f4f6fa !important; color: #1e88e5 !important;
    }

    /* 下拉菜单 */
    .dropdown-menu {
        background: #ffffff !important; border: 1px solid #eef0f5 !important;
        border-radius: 12px !important; box-shadow: 0 14px 34px rgba(30,136,229,.1) !important;
        padding: 10px 0 !important; margin-top: 0 !important; min-width: 180px !important;
    }
    .dropdown-menu > li > a {
        color: #3a4a5e !important; font-size: 14px !important; font-weight: 600 !important;
        padding: 10px 24px !important; transition: all 0.15s ease;
    }
    .dropdown-menu > li > a:hover { background: #e8f2fc !important; color: #1e88e5 !important; }
    .dropdown-menu > .divider { background: #eef0f5 !important; margin: 8px 0 !important; }
    .dropdown-header { color: #7a8699 !important; font-size: 12px !important; font-weight: 800 !important; padding: 12px 24px 6px !important; }

    /* ================= 页面统一视觉补丁 ================= */
    .panel { 
        border: 1px solid #eef0f5 !important; 
        border-radius: 14px !important; 
        box-shadow: 0 4px 16px rgba(10,30,63,.03) !important; 
        margin-bottom: 24px !important; 
        background: #ffffff !important; 
        transition: all .3s;
    }
    .panel-heading {
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%) !important;
        border-bottom: 1px solid #eef0f5 !important;
        padding: 18px 24px !important;
    }
    .panel-title { font-size: 16px !important; font-weight: 800 !important; color: #0a1e3f !important; }
    .panel-body { padding: 24px !important; }
    .btn {
        border-radius: 9px !important;
        font-weight: 600 !important;
        transition: all .2s ease !important;
    }
    .btn-primary, .btn-success, .btn-info {
        background: linear-gradient(135deg,#1e88e5,#0d47a1) !important;
        border: none !important;
        color: #fff !important;
        box-shadow: 0 4px 14px rgba(30,136,229,.18) !important;
    }
    .btn-default {
        border: 1px solid #d7dde8 !important;
        background: #fff !important;
        color: #1a2a4a !important;
    }
    .btn-danger {
        background: #fff1f0 !important;
        border: 1px solid #ffa39e !important;
        color: #f5222d !important;
    }
    .table > thead > tr > th {
        background: #f7f9fc !important;
        border-bottom: 1px solid #eef0f5 !important;
        color: #5a6a7e !important;
    }
    .table > tbody > tr > td {
        border-bottom: 1px solid #eef0f5 !important;
        color: #1a2a4a !important;
    }
    .form-control {
        border-radius: 8px !important;
        border: 1px solid #d7dde8 !important;
        box-shadow: none !important;
    }
    .form-control:focus {
        border-color: #1e88e5 !important;
        box-shadow: 0 0 0 3px rgba(30,136,229,0.08) !important;
    }
    .panel:hover { box-shadow: 0 14px 34px rgba(30,136,229,.08) !important; transform: translateY(-2px); }
    .panel-heading { 
        background: #ffffff !important; 
        border-bottom: 1px solid #eef0f5 !important; 
        padding: 18px 24px !important; 
        border-radius: 14px 14px 0 0 !important;
    }
    .panel-title { font-size: 16px !important; font-weight: 800 !important; color: #0a1e3f !important; }
    .panel-body { padding: 24px !important; }

    /* ================= 表格高级化 ================= */
    .table { background: transparent !important; border-color: #eef0f5 !important; margin-bottom: 0 !important; }
    .table > thead > tr > th { 
        background-color: #f7f9fc !important; 
        border-bottom: 2px solid #eef0f5 !important; 
        color: #5a6a7e !important; 
        padding: 16px !important; 
        font-size: 13.5px !important;
    }
    .table > tbody > tr > td { 
        padding: 16px !important; 
        border-color: #eef0f5 !important; 
        vertical-align: middle !important;
        color: #1a2a4a !important;
        font-size: 14px !important;
    }
    .table-striped > tbody > tr:nth-of-type(odd) { background-color: #fcfdfe !important; }
    .table-hover > tbody > tr:hover { background-color: #f4f6fa !important; }

    /* ================= 按钮与表单（高级渐变） ================= */
    .form-control { 
        height: 40px !important; 
        border-radius: 8px !important; 
        border: 1px solid #d7dde8 !important; 
        box-shadow: none !important; 
        font-weight: 500 !important;
        color: #1a2a4a !important;
        padding: 6px 14px !important;
    }
    .form-control:focus { border-color: #1e88e5 !important; box-shadow: 0 0 0 3px rgba(30,136,229, 0.1) !important; }
    label { font-weight: 600 !important; color: #3a4a5e !important; }

    .btn { border-radius: 8px !important; font-weight: 600 !important; padding: 9px 22px !important; transition: all .25s !important; }
    .btn-primary { 
        background: linear-gradient(135deg,#1e88e5,#0d47a1) !important; 
        border: none !important; 
        color: #fff !important; 
        box-shadow: 0 4px 14px rgba(30,136,229,.35) !important; 
    }
    .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 8px 22px rgba(30,136,229,.45) !important; }
    .btn-default { background: #fff !important; border: 1.5px solid #d7dde8 !important; color: #1a2a4a !important; }
    .btn-default:hover { border-color: #1e88e5 !important; color: #1e88e5 !important; }
  
    /* ================= 深度重写：表格与按钮 ================= */
    /* 1. 按钮颜色全部统一（消灭原生红绿蓝黄） */
    .btn-primary, .btn-info, .btn-success {
        background: linear-gradient(135deg,#1e88e5,#0d47a1) !important;
        border: none !important;
        color: #fff !important;
        box-shadow: 0 2px 6px rgba(30,136,229,.2) !important;
    }
    .btn-primary:hover, .btn-info:hover, .btn-success:hover {
        box-shadow: 0 4px 12px rgba(30,136,229,.4) !important;
        transform: translateY(-1px);
    }
    .btn-danger {
        background: #fff1f0 !important;
        border: 1px solid #ffa39e !important;
        color: #f5222d !important;
    }
    .btn-danger:hover {
        background: #ffa39e !important;
        color: #fff !important;
    }
    .btn-warning {
        background: #fffbe6 !important;
        border: 1px solid #ffe58f !important;
        color: #faad14 !important;
    }
    .btn-warning:hover {
        background: #ffe58f !important;
        color: #fff !important;
    }
    /* 所有小按钮统一极简圆角 */
    .btn-xs, .btn-sm {
        padding: 5px 12px !important;
        font-size: 12px !important;
        border-radius: 6px !important;
        margin-right: 4px !important;
        margin-bottom: 4px !important;
        font-weight: 600 !important;
    }

    /* 2. 表格高级化（去掉丑陋的边框，改成圆角卡片样式） */
    .table-responsive { 
        border: none !important; 
        box-shadow: none !important; 
        border-radius: 12px !important;
        overflow: hidden !important;
    }
    .table { 
        background: #fff !important; 
        border-collapse: separate !important; 
        border-spacing: 0 !important; 
        width: 100% !important; 
        border: 1px solid #eef0f5 !important; 
        margin-bottom: 0 !important;
    }
    .table > thead > tr > th { 
        border-bottom: 1px solid #eef0f5 !important; 
        background: #f7f9fc !important; 
        padding: 16px !important; 
        font-size: 13.5px !important; 
        font-weight: 700 !important; 
        color: #5a6a7e !important; 
        white-space: nowrap !important;
    }
    .table > tbody > tr > td { 
        border-bottom: 1px solid #eef0f5 !important; 
        padding: 16px !important; 
        vertical-align: middle !important; 
        font-size: 13.5px !important; 
        color: #1a2a4a !important;
    }
    .table > tbody > tr:hover > td { 
        background-color: #f7faff !important; 
    }
    .table > tbody > tr:last-child > td { 
        border-bottom: none !important; 
    }

    /* 3. 筛选栏的高度对齐 */
    .form-inline .form-control { 
        display: inline-block !important; 
        width: auto !important; 
        vertical-align: middle !important; 
        margin-right: 8px !important; 
        margin-bottom: 10px !important; 
    }
    .form-inline .btn { 
        margin-bottom: 10px !important; 
    }
    /* 表格内的操作下拉按钮 */
    .dropdown-group .btn { 
        background: #fff !important; 
        border: 1px solid #d7dde8 !important; 
        color: #1a2a4a !important; 
        box-shadow: none !important;
    }
    .dropdown-group .btn:hover { 
        border-color: #1e88e5 !important; 
        color: #1e88e5 !important; 
    }

    /* 4. 分页统一高级感 */
    .pagination > li > a, .pagination > li > span {
        color: #1e88e5 !important;
        border-color: #eef0f5 !important;
        font-weight: 600 !important;
        padding: 8px 14px !important;
    }
    .pagination > .active > a, .pagination > .active > span {
        background: #1e88e5 !important;
        border-color: #1e88e5 !important;
        color: #fff !important;
    }
</style>
</head>
<body>
  <nav class="navbar navbar-fixed-top">
    <div class="container-fluid" style="max-width: 1400px; padding-left:20px; padding-right:20px;">
      <div class="navbar-header">
        <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false">
          <span class="sr-only">导航按钮</span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
        </button>
        <a class="navbar-brand" href="./">信达支付系统</a>
      </div>
      
      <div id="navbar" class="collapse navbar-collapse" style="padding-left:0;padding-right:0;border:none;">
        <ul class="nav navbar-nav">
          <li>
            <a href="javascript:;" onclick="if(history.length>1){history.back();}else{location.href='./';}"><i class="fa fa-arrow-left"></i> 返回</a>
          </li>
          <li class="<?php echo checkIfActive('index,')?>">
            <a href="./"><i class="fa fa-home"></i> 首页</a>
          </li>
          <li class="dropdown <?php echo checkIfActive('order,export,buyerstat,blacklist')?>">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-list"></i> 收款订单<b class="caret"></b></a>
            <ul class="dropdown-menu">
              <li><a href="./order.php">订单管理</a></li>
              
              
              <li><a href="./blacklist.php">黑名单管理</a></li>
            </ul>
          </li>
          <li class="dropdown <?php echo checkIfActive('ulist,service,license,qrcode,glist,group,record,ustat,domain,invitecode')?>">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-user"></i> 商户管理<b class="caret"></b></a>
            <ul class="dropdown-menu">
              <li><a href="./ulist.php">用户列表</a></li>
              <li><a href="./service.php" style="color:#e74c3c;font-weight:800;">系统服务管理</a></li>
              <li><a href="./license.php">授权码管理</a></li>
              <li><a href="./qrcode/">📱 全局收款码</a></li>
              <li role="separator" class="divider"></li>
              
              
              <li><a href="./record.php">📊 资金流水明细</a></li>
              <li><a href="./ustat.php">支付统计</a></li>
              <li><a href="./domain.php">授权域名</a></li>
              
            </ul>
          </li>
          <li class="dropdown <?php echo checkIfActive('pay_channel,pay_type,pay_plugin,pay_roll,pay_weixin,applyments')?>">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-credit-card"></i> 支付接口<b class="caret"></b></a>
            <ul class="dropdown-menu">
              <li><a href="./pay_channel.php">💳 支付通道管理</a></li>
              <li><a href="./pay_type.php">支付方式</a></li>
              <!-- 已废弃 -->
              <!-- 已废弃 -->
              
              <li role="separator" class="divider"></li>
              
              
            </ul>
          </li>
        </ul>
        
        <ul class="nav navbar-nav navbar-right">
          <li class="dropdown <?php echo checkIfActive('set,gonggao,telegram,set_wxkf,iproute')?>">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-cog"></i> 系统设置<b class="caret"></b></a>
            <ul class="dropdown-menu" style="width:220px;">
              <li class="dropdown-header">基础配置</li>
              <li><a href="./set.php?mod=site">网站信息配置</a></li>
              <li><a href="./set.php?mod=template">首页模板配置</a></li>
              <li><a href="./set.php?mod=upimg">网站Logo上传</a></li>
              <li><a href="./gonggao.php">网站公告配置</a></li>
              <li role="separator" class="divider"></li>
              <li class="dropdown-header">支付与结算</li>
              <li><a href="./set.php?mod=pay">支付相关配置</a></li>
              <!-- 已废弃 -->
              <!-- 已废弃 -->
              <li role="separator" class="divider"></li>
              <li class="dropdown-header">通知与集成</li>
              <li><a href="./set.php?mod=notice">消息提醒配置</a></li>
              <li><a href="./set.php?mod=mail">邮箱与短信配置</a></li>
              <li><a href="./telegram.php">机器人配置</a></li>
              <li><a href="./set_wxkf.php">H5跳转微信客服支付</a></li>
              <li role="separator" class="divider"></li>
              <li class="dropdown-header">风控与安全</li>
              <li><a href="./set.php?mod=risk">风控检测配置</a></li>
              <li><a href="./set.php?mod=certificate">实名认证配置</a></li>
              <li><a href="./set_totp.php">谷歌验证器</a></li>
              <li role="separator" class="divider"></li>
              <li class="dropdown-header">高级</li>
              <li><a href="./set.php?mod=oauth">快捷登录配置</a></li>
              <li><a href="./set.php?mod=proxy">中转代理配置</a></li>
              <li><a href="./iproute.php">地区智能路由</a></li>
              <li><a href="./set.php?mod=cron">计划任务配置</a></li>
              <li><a href="./set.php?mod=account">修改密码</a></li>
            </ul>
          </li>
          <li class="dropdown <?php echo checkIfActive('risk,log,clean,gettoken,help')?>">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-book"></i> 帮助 & 日志<b class="caret"></b></a>
            <ul class="dropdown-menu">
              <li><a href="/admin/app_help.php"><i class="fa fa-question-circle"></i> 帮助中心</a></li>
              <li role="separator" class="divider"></li>
              <li><a href="./risk.php">风控记录</a></li>
              <li><a href="./log.php">登录日志</a></li>
              <li><a href="./clean.php">数据清理</a></li>
              <li><a href="./gettoken.php">获取用户标识</a></li>
            </ul>
          </li>
          <li class="dropdown <?php echo checkIfActive('slist,settle,transfer,ps_')?>">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-cube"></i> 其他功能<b class="caret"></b></a>
            <ul class="dropdown-menu">
              <!-- 已废弃 -->
              <!-- 已废弃 -->
              <li role="separator" class="divider"></li>
              
              
              
              
              
              <li role="separator" class="divider"></li>
              
              
            </ul>
          </li>
          <li><a href="./login.php?logout" onclick="return confirm('是否确定退出登录？')"><i class="fa fa-power-off"></i> 退出</a></li>
        </ul>
      </div>
    </div>
  </nav>
