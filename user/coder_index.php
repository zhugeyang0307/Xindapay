<?php
// 获取今日统计
$today_start = strtotime(date('Y-m-d 00:00:00'));
$today_orders = $DB->getColumn("SELECT count(*) FROM pay_order WHERE uid=:u AND addtime>=:t AND status=1", [':u'=>$uid, ':t'=>date('Y-m-d 00:00:00')]);
$today_money = $DB->getColumn("SELECT COALESCE(SUM(realmoney),0) FROM pay_order WHERE uid=:u AND addtime>=:t AND status=1", [':u'=>$uid, ':t'=>date('Y-m-d 00:00:00')]);
include './head.php';
?>
<style>
/* 码商专属极简首页样式 */
.coder-home-wrap { padding: 16px; }
.home-balance-card { 
    background: linear-gradient(135deg, #1677ff 0%, #0d5cd6 100%); 
    color: #fff; border-radius: 20px; padding: 24px; 
    box-shadow: 0 8px 24px rgba(22,119,255,0.25); 
    margin-bottom: 20px; position: relative; overflow: hidden;
}
.home-balance-card::after {
    content: ''; position: absolute; right: -20px; bottom: -20px;
    width: 120px; height: 120px; background: rgba(255,255,255,0.1);
    border-radius: 50%;
}
.bal-label { font-size: 14px; opacity: 0.85; margin-bottom: 8px; }
.bal-val { font-size: 36px; font-weight: 800; font-family: -apple-system, sans-serif; letter-spacing: -0.5px; }
.bal-sub { font-size: 13px; opacity: 0.8; margin-top: 12px; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 12px; display: flex; justify-content: space-between;}

/* 两宫格按钮 */
.coder-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 20px; }
.coder-btn-card { 
    background: #fff; border-radius: 16px; padding: 20px 16px; 
    display: flex; flex-direction: column; align-items: flex-start; 
    text-decoration: none !important; box-shadow: 0 2px 12px rgba(0,0,0,0.03); 
    transition: transform 0.2s; border: 1px solid #f1f5f9;
}
.coder-btn-card:active { transform: scale(0.96); }
.coder-btn-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-bottom: 12px; }
.coder-btn-title { font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
.coder-btn-desc { font-size: 12px; color: #94a3b8; }

.bg-soft-blue { background: #eff6ff; color: #1677ff; }
.bg-soft-green { background: #f0fdf4; color: #07c160; }
.bg-soft-orange { background: #fff7ed; color: #f59e0b; }
.bg-soft-purple { background: #faf5ff; color: #a855f7; }

.tip-box { 
    background: #f8fafc; border-radius: 12px; padding: 16px; 
    font-size: 13px; color: #64748b; display: flex; align-items: flex-start; gap: 10px; line-height: 1.6; 
}
.tip-box i { color: #1677ff; margin-top: 2px; }
</style>

<div class="coder-home-wrap">
    <!-- 余额卡片 -->
    <div class="home-balance-card">
        <div class="bal-label">当前可用余额 (元)</div>
        <div class="bal-val">¥<?php echo number_format($userrow['money'], 2); ?></div>
        <div class="bal-sub">
            <div>今日订单：<b><?php echo $today_orders; ?></b> 笔</div>
            <div>今日收款：<b>¥<?php echo number_format($today_money, 2); ?></b></div>
        </div>
    </div>

    <!-- 核心快捷按钮 -->
    <div class="coder-grid-2">
        <a href="/user/qrcode/" class="coder-btn-card">
            <div class="coder-btn-icon bg-soft-blue"><i class="fa fa-qrcode"></i></div>
            <div class="coder-btn-title">我的收款码</div>
            <div class="coder-btn-desc">查看与上传</div>
        </a>
        <a href="/user/order.php" class="coder-btn-card">
            <div class="coder-btn-icon bg-soft-green"><i class="fa fa-list-alt"></i></div>
            <div class="coder-btn-title">查询订单</div>
            <div class="coder-btn-desc">查看收款记录</div>
        </a>
        <a href="/user/bill_upload.php" class="coder-btn-card">
            <div class="coder-btn-icon bg-soft-orange"><i class="fa fa-balance-scale"></i></div>
            <div class="coder-btn-title">上传对账单</div>
            <div class="coder-btn-desc">提交给上级核对</div>
        </a>
        <a href="/user/sub_push.php" class="coder-btn-card">
            <div class="coder-btn-icon bg-soft-purple"><i class="fa fa-bell"></i></div>
            <div class="coder-btn-title">接收设置</div>
            <div class="coder-btn-desc">绑定 TG 或邮箱</div>
        </a>
    </div>

    <!-- 提示信息 -->
    <div class="tip-box">
        <i class="fa fa-info-circle"></i>
        <div>
            如果接不到订单，请检查底部【收款】页面的 APK 挂机是否在线。<br>
            余额低于单笔限额时，将无法正常派单，请联系上级补充余额。
        </div>
    </div>
</div>

<?php include './foot.php'; ?>
