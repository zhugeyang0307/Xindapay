<?php
$__m_cur = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8" />
    <title><?php echo isset($title) ? $title . ' - ' : ''; ?>信达支付</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
    <link rel="stylesheet" href="/assets/vendor/twitter-bootstrap/3.4.1/css/bootstrap.min.css" type="text/css" />
    <link rel="stylesheet" href="/assets/vendor/font-awesome/4.7.0/css/font-awesome.min.css" type="text/css" />
    <script src="/assets/vendor/jquery/3.4.1/jquery.min.js"></script>
    <script src="/assets/vendor/twitter-bootstrap/3.4.1/js/bootstrap.min.js"></script>
    
    <link rel="manifest" href="/user/manifest.json">
    <meta name="theme-color" content="#ffffff">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="apple-touch-icon" href="/assets/img/app-icon.svg">

    
<style>
/* ================= 毁灭级 App 移动端完全重构 ================= */
html, body {
    margin: 0 !important; padding: 0 !important;
    overflow-x: hidden !important;
    background-color: #f4f6fb !important;
    -webkit-tap-highlight-color: transparent;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", sans-serif !important;
    color: #334155 !important;
    padding-top: 56px !important;
    padding-bottom: calc(75px + env(safe-area-inset-bottom)) !important;
    font-size: 14px !important;
}

/* 1. 顶部悬浮导航 */
.m-top-bar {
    position: fixed; top: 0; left: 0; right: 0; height: 56px;
    background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(10px);
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 16px; z-index: 9999; border-bottom: 1px solid #eef0f5;
    padding-top: env(safe-area-inset-top);
}
.m-top-bar .nav-btn { color: #334155; font-size: 22px; width: 36px; text-align: center; text-decoration: none; }
.m-top-bar .title { font-size: 17px; font-weight: 800; color: #0f172a; display:flex; align-items:center; gap:8px;}

/* 2. 彻底干碎原有的容器宽度和浮动 */
.container, .container-fluid, .wrapper-md, .app-content-body { 
    width: 100% !important; max-width: 100% !important; 
    padding: 12px !important; margin: 0 !important; float: none !important;
}
.row { margin-left: -6px !important; margin-right: -6px !important; }
.col-xs-1, .col-sm-1, .col-md-1, .col-lg-1,
.col-xs-2, .col-sm-2, .col-md-2, .col-lg-2,
.col-xs-3, .col-sm-3, .col-md-3, .col-lg-3,
.col-xs-4, .col-sm-4, .col-md-4, .col-lg-4,
.col-xs-5, .col-sm-5, .col-md-5, .col-lg-5,
.col-xs-6, .col-sm-6, .col-md-6, .col-lg-6,
.col-xs-7, .col-sm-7, .col-md-7, .col-lg-7,
.col-xs-8, .col-sm-8, .col-md-8, .col-lg-8,
.col-xs-9, .col-sm-9, .col-md-9, .col-lg-9,
.col-xs-10, .col-sm-10, .col-md-10, .col-lg-10,
.col-xs-11, .col-sm-11, .col-md-11, .col-lg-11,
.col-xs-12, .col-sm-12, .col-md-12, .col-lg-12 {
    width: 100% !important; float: none !important; padding: 0 6px !important; margin-bottom: 12px;
}

/* 3. 任何图片、视频、iframe 绝不超出屏幕 */
img, video, iframe { max-width: 100% !important; height: auto !important; }

/* 4. 全面表格卡片化（不依赖原有结构） */
.table-responsive { overflow: visible !important; border: none !important; }
.table { display: block !important; width: 100% !important; border: none !important; background: transparent !important; margin-bottom: 0 !important; }
.table > thead { display: none !important; }
.table > tbody { display: block !important; }
.table > tbody > tr { 
    display: block !important; background: #fff !important; border-radius: 12px !important; 
    margin-bottom: 12px !important; padding: 12px 16px !important; 
    box-shadow: 0 2px 8px rgba(0,0,0,0.03) !important; border: 1px solid #eef0f5 !important;
}
.table > tbody > tr > td { 
    display: flex !important; justify-content: space-between !important; align-items: center !important; 
    border: none !important; border-bottom: 1px dashed #f1f5f9 !important; 
    padding: 10px 0 !important; text-align: right !important; font-size: 14px !important;
}
.table > tbody > tr > td:last-child { border-bottom: none !important; padding-bottom: 0 !important; }
.table > tbody > tr > td:before { content: attr(data-label); color: #64748b; font-weight: 600; float: left; text-align: left; }
/* 如果是操作按钮行，居中显示 */
.table > tbody > tr > td .btn-group, .table > tbody > tr > td .btn { margin: 0 auto; display: block; width: 100%; }

/* 5. 表单、按钮、弹窗全面适应手指 */
.form-control { height: 46px !important; font-size: 15px !important; border-radius: 10px !important; margin-bottom: 12px; background: #f8fafc !important; border: 1px solid #e2e8f0 !important;}
select.form-control { padding: 0 12px; }
.btn { height: 46px !important; font-size: 15px !important; border-radius: 10px !important; display: flex; align-items: center; justify-content: center; }
.btn-block { width: 100% !important; }
.btn-xs, .btn-sm { height: 32px !important; font-size: 13px !important; display: inline-flex; width: auto !important; padding: 0 12px; margin-bottom: 0; }

/* 6. 彻底修复弹窗 UI 与底部遮挡 */
.modal-dialog { margin: 15px auto !important; max-width: 95% !important; }
.modal-content { border-radius: 16px !important; border: none !important; box-shadow: 0 10px 30px rgba(0,0,0,0.1) !important; }
.modal-body { max-height: 70vh; overflow-y: auto; padding: 20px !important; padding-bottom: calc(20px + env(safe-area-inset-bottom)) !important; }
.modal-header { padding: 16px 20px !important; border-bottom: 1px solid #f1f5f9 !important; border-radius: 16px 16px 0 0 !important; }
.modal-footer { padding: 16px 20px !important; border-top: 1px solid #f1f5f9 !important; }
.modal-title { font-size: 16px !important; font-weight: 800 !important; }

/* 7. 隐藏一切不需要的 PC 残留 */
.bg-light.lter.b-b { display: none !important; }
.app-aside, .app-header, .app-footer { display: none !important; }

    /* 物理超度旧侧边栏，绝不显示 */
    .app-aside, .app-aside-wrap, #aside, .navbar-toggle { 
        display: none !important; 
    }
    /* 锁定 Logo 尺寸 */
    .m-top-bar .title img { 
        height: 24px !important; 
        width: 24px !important; 
        object-fit: contain; 
        max-height: 24px !important;
    }


/* ===== 终极表格防挤压 & 空状态修复 ===== */
.table-responsive, .fixed-table-container, .bootstrap-table {
    width: 100% !important; overflow-x: auto !important; border: none !important;
}
.no-records, .no-records-found {
    display: block !important;
    width: 100% !important;
    text-align: center !important;
    padding: 30px 10px !important;
    color: #94a3b8 !important;
    font-size: 14px !important;
    word-break: keep-all !important;
    white-space: nowrap !important;
}
.fixed-table-body { overflow-x: auto !important; }
.table > tbody > tr > td { word-break: break-all !important; }
/* 修复顶部搜索框被挤压 */
.form-inline .form-control { width: 100% !important; margin-bottom: 10px !important; }
.form-inline .btn { width: 100% !important; margin-bottom: 10px !important; }

</style>
</head>

<body>
    <div class="m-top-bar">
        <div style="width: 40px;">
            <?php if($__m_cur != 'index.php' && $__m_cur != 'app_me.php'): ?>
                <a href="javascript:void(0);" onclick="if(history.length>1){history.back();}else{location.href='/user/app_me.php';}" class="nav-btn"><i class="fa fa-angle-left"></i></a>
            <?php endif; ?>
        </div>
        <div class="title"><img src="/assets/img/app-icon.svg"> <?php echo ((int)$userrow["sub_level"] >= 2) ? "员工工作台" : "信达支付"; ?></div>
        <div style="width: 40px; text-align: right;"></div>
    </div>
    <div class="app-content">
