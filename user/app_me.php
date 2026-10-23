<?php
require_once('../includes/common.php');
if($islogin2 != 1) exit("<script>location.href='./login.php';</script>");
$uid = (int)$userrow['uid'];
$sub_level = (int)$userrow['sub_level'];
$is_coder = ($sub_level >= 2); // 码商判断
$title = $is_coder ? '员工工作台' : '我的控制台';
include './head.php';
?>
<style>
/* 全局浅灰背景，去掉顶部默认白条 */
.m-top-bar { display: none !important; }
body { padding-top: 0 !important; background: #f5f7fa !important; }
.app-content { padding: 0 !important; margin: 0 !important; }

/* ===== 极简纯白卡片头部 ===== */
.app-header-new {
    background: #ffffff;
    padding: calc(24px + env(safe-area-inset-top)) 20px 20px;
    border-radius: 0 0 24px 24px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.02);
    margin-bottom: 20px;
}
.user-profile {
    display: flex; align-items: center; margin-bottom: 24px;
}
.user-avatar {
    width: 64px; height: 64px; border-radius: 50%;
    background: linear-gradient(135deg, #1677ff 0%, #0d5cd6 100%);
    color: #fff; font-size: 28px; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    margin-right: 16px; box-shadow: 0 4px 12px rgba(22,119,255,0.25);
    flex-shrink: 0;
}
.user-info h2 { font-size: 20px; font-weight: 800; color: #0f172a; margin: 0 0 6px; }
.user-info p { font-size: 13px; color: #64748b; margin: 0; font-weight: 500; }

/* 数据统计区域 */
.user-stats {
    display: flex; justify-content: space-between;
    background: #f8fafc; border-radius: 16px; padding: 16px 10px;
    border: 1px solid #f1f5f9;
}
.stat-item { flex: 1; text-align: center; }
.stat-item .val { font-size: 18px; font-weight: 800; color: #0f172a; }
.stat-item .lbl { font-size: 12px; color: #64748b; margin-top: 4px; font-weight: 500;}

/* ===== 功能宫格 ===== */
.app-grid-large { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 16px; padding: 0 16px; }
.app-grid-item-large { 
    background: #fff; border-radius: 16px; padding: 20px 10px; 
    display: flex; flex-direction: column; align-items: center; justify-content: center; 
    text-decoration: none !important; box-shadow: 0 2px 12px rgba(0,0,0,0.02); 
    transition: transform 0.2s; border: 1px solid #f1f5f9;
}
.app-grid-item-large:active { transform: scale(0.95); }
.app-grid-item-large i { font-size: 28px; color: #1677ff; margin-bottom: 10px; }
.app-grid-item-large span { font-size: 13px; color: #334155; font-weight: 600; }

/* 老板列表样式 */
.app-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; background: #fff; border-radius: 16px; padding: 20px; margin: 0 16px 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.02); border: 1px solid #f1f5f9; }
.app-grid-item { display: flex; flex-direction: column; align-items: center; text-decoration: none !important; }
.app-grid-item i { font-size: 26px; color: #1677ff; margin-bottom: 8px; }
.app-grid-item span { font-size: 12px; color: #334155; font-weight: 600; }
.app-menu-group { background: #fff; border-radius: 16px; margin: 0 16px 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.02); overflow: hidden; border: 1px solid #f1f5f9; }
.app-menu-item { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid #f1f5f9; text-decoration: none !important; }
.app-menu-item:last-child { border-bottom: none; }
.app-menu-item .left { display: flex; align-items: center; font-size: 15px; color: #334155; font-weight: 600; }
.app-menu-item .left i { color: #64748b; width: 24px; font-size: 18px; margin-right: 12px; text-align: center;}
.app-menu-item .fa-angle-right { color: #cbd5e1; font-size: 18px; }
</style>

<div class="app-header-new">
    <div class="user-profile">
        <div class="user-avatar"><?php echo mb_substr($userrow['username'] ?: 'U', 0, 1); ?></div>
        <div class="user-info">
            <h2><?php echo $userrow['username'] ?: '员工'.$uid; ?></h2>
            <p>UID: <?php echo $uid; ?> · <?php echo $is_coder ? 'L'.$sub_level.' 员工' : '主控后台'; ?></p>
        </div>
    </div>
    <div class="user-stats">
        <div class="stat-item">
            <div class="val">¥<?php echo $userrow['money']; ?></div>
            <div class="lbl">可用余额</div>
        </div>
        <?php if(!$is_coder): ?>
        <div class="stat-item">
            <div class="val"><?php echo $userrow['apk_quota']; ?></div>
            <div class="lbl">APK额度</div>
        </div>
        <div class="stat-item">
            <div class="val"><?php echo $userrow['service_expire'] ? '正常' : '未开通'; ?></div>
            <div class="lbl">服务状态</div>
        </div>
        <?php else: ?>
        <div class="stat-item">
            <div class="val"><?php echo $userrow['sub_push_status']==1 ? '已配' : '未配'; ?></div>
            <div class="lbl">推送状态</div>
        </div>
        <div class="stat-item">
            <div class="val"><?php echo $userrow['status']==1 ? '正常' : '停用'; ?></div>
            <div class="lbl">账号状态</div>
        </div>
        <?php endif; ?>
    </div>
</div>


<!-- 语音播报开关 -->
<div class="app-menu-group" style="margin: 0 16px 16px;">
    <div class="app-menu-item" style="justify-content: space-between;">
        <div class="left">
            <i class="fa fa-volume-up" style="color:#f59e0b;"></i>
            <span>新订单语音播报</span>
        </div>
        <div class="switch">
            <input type="checkbox" id="notifySwitch" onchange="toggleNotify(this)">
            <label for="notifySwitch"></label>
        </div>
    </div>
</div>

<style>
/* 优雅的 iOS 风格 Switch 开关 */
.switch { position: relative; width: 50px; height: 30px; }
.switch input { opacity: 0; width: 0; height: 0; }
.switch label { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 30px; }
.switch label:before { position: absolute; content: ""; height: 26px; width: 26px; left: 2px; bottom: 2px; background-color: white; transition: .4s; border-radius: 50%; }
.switch input:checked + label { background-color: #07c160; }
.switch input:checked + label:before { transform: translateX(20px); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var sw = document.getElementById('notifySwitch');
    var allowed = localStorage.getItem('notify_allowed');
    if (allowed === 'yes') sw.checked = true;
});

function toggleNotify(el) {
    if (el.checked) {
        // 开启：请求通知权限和音频权限
        if ("Notification" in window) {
            Notification.requestPermission().then(function(permission) {
                if (permission === 'granted') {
                    localStorage.setItem('notify_allowed', 'yes');
                    alert('✅ 语音播报已开启！\n有订单时将播放声音并弹窗提醒。');
                    // 初始化音频引擎（必须由用户点击触发）
                    var ctx = new (window.AudioContext || window.webkitAudioContext)();
                    if (ctx.state === 'suspended') ctx.resume();
                } else {
                    el.checked = false;
                    alert('❌ 通知权限被拒绝，请在浏览器设置中允许通知。');
                }
            });
        } else {
            localStorage.setItem('notify_allowed', 'yes');
            alert('✅ 语音播报已开启！');
        }
    } else {
        // 关闭
        localStorage.setItem('notify_allowed', 'no');
    }
}
</script>
<?php if($is_coder): ?>
<!-- ====== 员工极简大宫格 ====== -->
<div class="app-grid-large">
    <a href="/user/order.php" class="app-grid-item-large"><i class="fa fa-list-alt" style="color:#1677ff;"></i><span>我的订单</span></a>
    <a href="/user/bill_upload.php" class="app-grid-item-large"><i class="fa fa-balance-scale" style="color:#f59e0b;"></i><span>上传对账单</span></a>
    <a href="/user/qrcode/" class="app-grid-item-large"><i class="fa fa-qrcode" style="color:#07c160;"></i><span>收款码</span></a>
    <a href="/user/sub_push.php" class="app-grid-item-large"><i class="fa fa-bell" style="color:#e74c3c;"></i><span>接收设置</span></a>
    <a href="/user/coder_test.php" class="app-grid-item-large"><i class="fa fa-flask" style="color:#8b5cf6;"></i><span>IP/TG工具</span></a>
    <a href="/user/license.php" class="app-grid-item-large"><i class="fa fa-key" style="color:#0ea5e9;"></i><span>激活码</span></a>
</div>

<div class="app-menu-group">
    <a href="/user/sub_list.php" class="app-menu-item"><div class="left"><i class="fa fa-sitemap" style="color:#0ea5e9;"></i>我的下级商户</div><i class="fa fa-angle-right"></i></a>
    <a href="/user/sub_qrcodes.php" class="app-menu-item"><div class="left"><i class="fa fa-qrcode" style="color:#7c3aed;"></i>下级费率与收款码</div><i class="fa fa-angle-right"></i></a>
</div>

<div class="app-menu-group">
    <a href="/user/app_help.php?file=员工码商使用指南.html" class="app-menu-item"><div class="left"><i class="fa fa-book" style="color:#1677ff;"></i>员工使用指南</div><i class="fa fa-angle-right"></i></a>
</div>

<div class="app-menu-group">
    <a href="/user/login.php?logout" onclick="return confirm('确定退出登录吗？')" class="app-menu-item" style="justify-content: center; color: #e74c3c; font-weight: 700;">退出登录</a>
</div>

<?php else: ?>
<!-- ====== 上级老板完整版 ====== -->
<div class="app-grid">
    <a href="/user/qrcode/" class="app-grid-item"><i class="fa fa-qrcode"></i><span>收款码</span></a>
    <a href="/user/order.php" class="app-grid-item"><i class="fa fa-list-alt"></i><span>订单</span></a>
    <a href="/user/record.php" class="app-grid-item"><i class="fa fa-yen"></i><span>资金明细</span></a>
    <a href="/user/license.php" class="app-grid-item"><i class="fa fa-key"></i><span>激活码</span></a>
</div>

<div class="app-menu-group">
    <a href="/user/userinfo.php?mod=api" class="app-menu-item"><div class="left"><i class="fa fa-user"></i>个人资料与 API</div><i class="fa fa-angle-right"></i></a>
    <a href="/user/telegram.php" class="app-menu-item"><div class="left"><i class="fa fa-paper-plane" style="color:#1677ff;"></i>机器人管理 (TG 设置)</div><i class="fa fa-angle-right"></i></a>
    <a href="/user/mail_push.php" class="app-menu-item"><div class="left"><i class="fa fa-envelope" style="color:#07c160;"></i>邮件推送设置</div><i class="fa fa-angle-right"></i></a>
    <a href="/user/sub_push.php" class="app-menu-item"><div class="left"><i class="fa fa-bullhorn" style="color:#f59e0b;"></i>推送资料与审批</div><i class="fa fa-angle-right"></i></a>
</div>

<div class="app-menu-group">
    <a href="/user/sub_list.php" class="app-menu-item"><div class="left"><i class="fa fa-sitemap"></i>下级商户管理</div><i class="fa fa-angle-right"></i></a>
    <a href="/user/bill.php" class="app-menu-item"><div class="left"><i class="fa fa-balance-scale"></i>下级对账中心</div><i class="fa fa-angle-right"></i></a>
</div>

<div class="app-menu-group">
    <a href="/user/service.php" class="app-menu-item"><div class="left"><i class="fa fa-diamond" style="color:#e74c3c;"></i><span style="color:#e74c3c;">服务续费</span></div><i class="fa fa-angle-right"></i></a>
    <a href="/user/recharge.php" class="app-menu-item"><div class="left"><i class="fa fa-usd" style="color:#10b981;"></i>USDT 充值</div><i class="fa fa-angle-right"></i></a>
    <a href="/user/domain.php" class="app-menu-item"><div class="left"><i class="fa fa-globe"></i>授权域名</div><i class="fa fa-angle-right"></i></a>
    <a href="/user/totp.php" class="app-menu-item"><div class="left"><i class="fa fa-shield"></i>谷歌验证器 (2FA)</div><i class="fa fa-angle-right"></i></a>
</div>

<div class="app-menu-group">
    <a href="/user/app_help.php?file=商户后台使用指南.html" class="app-menu-item"><div class="left"><i class="fa fa-star" style="color:#f59e0b;"></i>新手操作指南</div><i class="fa fa-angle-right"></i></a>
    <a href="/user/app_help.php?file=商户操作手册.html" class="app-menu-item"><div class="left"><i class="fa fa-book"></i>帮助中心与文档</div><i class="fa fa-angle-right"></i></a>
    <a href="/user/login.php?logout" onclick="return confirm('确定退出登录吗？')" class="app-menu-item" style="justify-content: center; color: #e74c3c; font-weight: 700;">退出登录</a>
</div>
<?php endif; ?>

<?php include './foot.php'; ?>
