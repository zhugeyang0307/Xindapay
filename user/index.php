<?php
$is_defend=true;
include("../includes/common.php");

// ===== 码商极简首页拦截 =====
if (!function_exists('__m_is_mobile')) {
    function __m_is_mobile() { 
        return preg_match("/(android|iphone|ipad|ipod|windows phone|blackberry|mobile)/i", $_SERVER['HTTP_USER_AGENT']); 
    }
}
if (__m_is_mobile() && isset($userrow['sub_level']) && (int)$userrow['sub_level'] >= 2) {
    include __DIR__ . '/coder_index.php';
    return;
}
// ============================


if(isset($_GET['invite'])){
    $invite_code = trim($_GET['invite']);
    $uid = get_invite_uid($invite_code);
    if($uid && is_numeric($uid)){
        $_SESSION['invite_uid'] = intval($uid);
    }
}

if($islogin2==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");

if(!$conf['reg_input_settle'] && (empty($userrow['account']) || empty($userrow['username']))){
	exit("<script language='javascript'>window.location.href='./completeinfo.php';</script>");
}

if($userrow['status']==0){
	$status = '<font color="red">已封禁</font>';
}elseif($userrow['pay']==0 && $userrow['settle']==0){
	$status = '<font color="red">关闭支付、结算</font>';
}elseif($userrow['pay']==0){
	$status = '<font color="red">关闭支付</font>';
}elseif($userrow['settle']==0){
	$status = '<font color="red">关闭结算</font>';
}elseif($conf['cert_force']==1 && $userrow['cert']==0){
	$status = '<a href="certificate.php"><font color="red">未实名认证</font></a>';
}elseif($userrow['pay']==2){
	$status = '<font color="orange">待审核</font>';
}else{
	$status = '<font color="green">正常</font>';
}
$title='商户中心';
include './head.php';
?>
<style>
.round {
    line-height: 53px;
    color: #7266ba;
    width: 58px;
    height: 58px;
    font-size: 26px;
    margin-left:15px;
    display: inline-block;
    font-weight: 400;
    border: 3px solid #f8f8fe;
    text-align: center;
    border-radius: 50%;
    background: #e3dff9;
}

/* 修复左侧黑色下拉菜单 */
.app-aside .navi ul.nav-sub, 
.app-aside .navi .nav-sub {
    background: #ffffff !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    border-radius: 6px;
    margin: 0 10px;
}
.app-aside .navi ul.nav-sub > li > a {
    color: #334155 !important;
    padding: 8px 20px !important;
}
.app-aside .navi ul.nav-sub > li > a:hover {
    color: #1677ff !important;
    background: #f0f7ff !important;
    border-radius: 4px;
}

/* 左上角字体加大 */
header.app-header .navbar-brand, 
header.app-header .navbar-brand span {
    font-size: 22px !important;
    font-weight: 700 !important;
    letter-spacing: 1px !important;
}

/* 右上角商户图标优化 */
header.app-header .thumb-sm img {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    background: #eef2f7;
    object-fit: cover;
}

/* 系统驾驶舱真实感增强样式 */
.feature-card { transition: all 0.3s; cursor: pointer; background: #fff; border-radius: 10px; padding: 20px; text-align: left; box-shadow: 0 4px 16px rgba(15,23,42,.04); border: 1px solid #eef2f7; height: 100%; }
.feature-card:hover { transform: translateY(-4px); box-shadow: 0 10px 28px rgba(22,119,255,0.12); border-color: #bae0ff; }
.feature-icon-box { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #fff; margin-bottom: 15px; box-shadow: 0 4px 12px rgba(22,119,255,0.15); }
.bg-blue { background: linear-gradient(135deg, #1677ff 0%, #0d5cd6 100%); }
.bg-green { background: linear-gradient(135deg, #07c160 0%, #059948 100%); }
.bg-purple { background: linear-gradient(135deg, #a855f7 0%, #7c3aed 100%); }
.bg-orange { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
.feature-title { font-size: 15px; font-weight: 700; color: #0f172a; margin-bottom: 6px; }
.feature-desc { font-size: 12px; color: #64748b; line-height: 1.6; }

/* 表格状态圆点 */
.status-dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; margin-right: 4px; vertical-align: middle; }
.dot-green { background: #22c55e; box-shadow: 0 0 6px #22c55e; }
.dot-yellow { background: #f59e0b; box-shadow: 0 0 6px #f59e0b; }

/* 表格微调 */
.table-align-middle th, .table-align-middle td { vertical-align: middle !important; border-bottom: 1px solid #f1f5f9 !important; font-size: 13px; }
.table-align-middle thead th { background: #f8fafc; color: #64748b; font-weight: 600; border-bottom: 2px solid #e2e8f0 !important; }
.table-align-middle tbody tr:hover { background: #f8fafc; }
.panel-heading { background:#fff; border-bottom:1px solid #eef2f7; }

/* ===== 全局字体加粗 ===== */
/* 1. 左侧导航栏菜单 */
.app-aside .navi > ul > li > a {
    font-weight: 600 !important;
}
.app-aside .navi ul.nav-sub > li > a {
    font-weight: 600 !important;
}
.app-aside .navi .text-muted {
    font-weight: 600 !important;
}

/* 2. 顶部导航栏 */
header.app-header .navbar-brand span {
    font-weight: 800 !important;
}
header.app-header .nav > li > a {
    font-weight: 600 !important;
}

/* 3. 页面主标题 */
.bg-light.lter.b-b h1 {
    font-weight: 700 !important;
}
.bg-light.lter.b-b small {
    font-weight: 500 !important;
}

/* 4. 数据统计卡片（余额、订单数等） */
.panel.padder-v.item .h1 {
    font-weight: 700 !important;
}
.panel.padder-v.item .text-muted {
    font-weight: 600 !important;
}

/* 5. 四大卖点卡片 */
.feature-title {
    font-weight: 700 !important;
    font-size: 16px !important;
}
.feature-desc {
    font-weight: 500 !important;
    color: #475569 !important;
}

/* 6. 面板标题 */
.panel-heading {
    font-weight: 700 !important;
    font-size: 15px !important;
}

/* 7. 表格表头和数据 */
.table-align-middle thead th {
    font-weight: 700 !important;
    color: #1e293b !important;
}
.table-align-middle tbody td {
    font-weight: 500 !important;
}
.table-align-middle .font-bold, 
.table-align-middle .text-primary {
    font-weight: 700 !important;
}

/* 8. 表单标签和按钮 */
.form-group label {
    font-weight: 600 !important;
}
.btn-block {
    font-weight: 700 !important;
}
</style>
<?php
$rs=$DB->query("SELECT * FROM pay_settle WHERE uid={$uid} AND status=1 ORDER BY id DESC LIMIT 9");
$max_settle=0;
$chart='';
$i=0;
while($row = $rs->fetch())
{
	if($row['money']>$max_settle)$max_settle=$row['money'];
	$chart.='['.$i++.','.$row['money'].'],';
}
$chart=substr($chart,0,-1);

$list = $DB->getAll("SELECT * FROM pay_anounce WHERE status=1 ORDER BY sort ASC");
?>
<div id="content" class="app-content" role="main">
    <div class="app-content-body ">
		<?php if(!empty($userrow['custom_notice'])): ?>
<div class="modal inmodal fade" id="myModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">关闭</span></button>
                <h4 class="modal-title">📢 系统公告</h4>
            </div>
            <div class="modal-body" style="line-height:1.9;font-size:14px;color:#333;padding:20px 24px;max-height:60vh;overflow-y:auto;">
                <?php echo $userrow['custom_notice']; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-white" data-dismiss="modal">我知道了</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="bg-light lter b-b wrapper-md hidden-print">
  <h1 class="m-n font-thin h3">管理后台</h1>
  <small class="text-muted">欢迎回来，祝您生意兴隆</small>
</div>
<div class="wrapper-md control">
<!-- stats -->
<?php
if($conf['cert_force']==1 && $userrow['cert']==0){
	echo '<div class="alert alert-danger"><span class="btn-sm btn-danger">重要</span>&nbsp;请完成实名认证，否则您的商户无法正常收款！ <a href="./certificate.php" class="btn btn-default btn-xs">立即实名认证</a></div>';
}
if($conf['verifytype']==1 && empty($userrow['phone'])){
	echo '<div class="alert alert-warning"><span class="btn-sm btn-warning">提示</span>&nbsp;您还没有绑定密保手机，请&nbsp;<a href="editinfo.php" class="btn btn-default btn-xs">尽快绑定</a></div>';
}elseif($conf['verifytype']==0 && empty($userrow['email'])){
	echo '<div class="alert alert-warning"><span class="btn-sm btn-warning">提示</span>&nbsp;您还没有绑定密保邮箱，请&nbsp;<a href="editinfo.php" class="btn btn-default btn-xs">尽快绑定</a></div>';
}
if(empty($userrow['pwd'])){
	echo '<div class="alert alert-warning"><span class="btn-sm btn-warning">提示</span>&nbsp;您还没有设置登录密码，请&nbsp;<a href="userinfo.php?mod=account" class="btn btn-default btn-xs">点此设置</a>，设置登录密码之后你就可以使用手机号/邮箱+密码登录</div>';
}
?>

          <div class="row row-sm text-center">
            <div class="col-xs-6 col-sm-3">
              <div class="panel padder-v item">
			    <div class="top text-right w-full"><i class="fa fa-caret-down text-warning m-r-sm"></i></div>
			  <div class="row">
			  <div class="col-xs-3"><div class="round"><i class="fa fa-money fa-fw"></i></div></div>
			  <div class="col-xs-9"><div class="h1 text-primary-dk font-thin h1"><span class="text-muted text-md">¥</span><?php echo $userrow['money']?></div><span class="text-muted">商户当前余额</span></div>
			  </div>
			  </div>
            </div>
			<div class="col-xs-6 col-sm-3">
              <div class="panel padder-v item">
			    <div class="top text-right w-full"><i class="fa fa-caret-down text-warning m-r-sm"></i></div>
			  <div class="row">
			  <div class="col-xs-3"><div class="round"><i class="fa fa-check-square-o fa-fw"></i></div></div>
			  <div class="col-xs-9"><div class="h1 text-dark-dk font-thin h1"><span class="text-muted text-md">¥</span><span id="settle_money"></span></div><span class="text-muted">已结算余额</span></div>
			  </div>
			  </div>
            </div>
			<div class="col-xs-6 col-sm-3">
              <div class="panel padder-v item">
			    <div class="top text-right w-full"><i class="fa fa-caret-down text-warning m-r-sm"></i></div>
			  <div class="row">
			  <div class="col-xs-3"><div class="round"><i class="fa fa-area-chart fa-fw"></i></div></div>
			  <div class="col-xs-9"><div class="h1 text-success-dk font-thin h1"><span id="orders"></span><span class="text-muted text-md">个</span></div><span class="text-muted">订单总数</span></div>
			  </div>
			  </div>
            </div>
			<div class="col-xs-6 col-sm-3">
              <div class="panel padder-v item">
			    <div class="top text-right w-full"><i class="fa fa-caret-down text-warning m-r-sm"></i></div>
			  <div class="row">
			  <div class="col-xs-3"><div class="round"><i class="fa fa-cart-plus fa-fw"></i></div></div>
			  <div class="col-xs-9"><div class="h1 text-info-dk font-thin h1"><span id="orders_today"></span><span class="text-muted text-md">个</span></div><span class="text-muted">今日订单</span></div>
			  </div>
			  </div>
            </div>
        </div>

        
        <!-- ========== 真实数据统计 + 商户自定义公告 ========== -->
        <div class="row">
            <div class="col-md-7">
                <div class="panel panel-default">
                    <div class="panel-heading font-bold">
                        <i class="fa fa-pie-chart text-primary"></i> 今日交易统计
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped" style="margin-bottom:0;">
                            <thead><tr id="paytypes"></tr></thead>
                            <tbody>
                                <tr id="order_today"></tr>
                                <tr id="order_lastday"></tr>
                                <tr id="success_rate"></tr>
                                <tr id="payrates"></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-5">
                <div class="panel panel-default">
                    <div class="panel-heading font-bold">
                        <i class="fa fa-bullhorn text-warning"></i> 系统公告
                    </div>
                    <div class="panel-body" style="min-height: 180px;">
                        <?php if(!empty($userrow['custom_notice'])): ?>
                            <div style="line-height: 1.8; color: #334155; font-size: 14px;">
                                <?php echo $userrow['custom_notice']; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted" style="padding-top: 50px;">
                                <i class="fa fa-inbox fa-3x" style="color:#cbd5e1; margin-bottom:15px;"></i>
                                <p>暂无公告</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========== 实用快捷操作导航 ========== -->
        <div class="row" style="margin-top: 10px;">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-heading font-bold"><i class="fa fa-bolt text-success"></i> 快捷操作</div>
                    <div class="panel-body">
                        <div class="row text-center">
                            <div class="col-xs-6 col-sm-3">
                                <a href="/user/qrcode/" style="text-decoration:none; display:block; padding:15px; border-radius:10px; transition:all 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                                    <i class="fa fa-qrcode fa-3x" style="color:#3b82f6; margin-bottom:10px;"></i>
                                    <h5 style="color:#1e293b; font-weight:700; margin:0;">收款码管理</h5>
                                    <small class="text-muted">上传与查看收款码</small>
                                </a>
                            </div>
                            <?php if((int)$userrow['sub_level'] == 1): ?>
                            <div class="col-xs-6 col-sm-3">
                                <a href="/user/userinfo.php?mod=api" style="text-decoration:none; display:block; padding:15px; border-radius:10px; transition:all 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                                    <i class="fa fa-code fa-3x" style="color:#8b5cf6; margin-bottom:10px;"></i>
                                    <h5 style="color:#1e293b; font-weight:700; margin:0;">接口对接</h5>
                                    <small class="text-muted">查看 API 与密钥</small>
                                </a>
                            </div>
                            <?php endif; ?>
                            <div class="col-xs-6 col-sm-3">
                                <a href="/user/sub_create.php" style="text-decoration:none; display:block; padding:15px; border-radius:10px; transition:all 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                                    <i class="fa fa-users fa-3x" style="color:#10b981; margin-bottom:10px;"></i>
                                    <h5 style="color:#1e293b; font-weight:700; margin:0;">创建下级</h5>
                                    <small class="text-muted">发展您的码商团队</small>
                                </a>
                            </div>
                            <div class="col-xs-6 col-sm-3">
                                <a href="/user/order.php" style="text-decoration:none; display:block; padding:15px; border-radius:10px; transition:all 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                                    <i class="fa fa-list-alt fa-3x" style="color:#f59e0b; margin-bottom:10px;"></i>
                                    <h5 style="color:#1e293b; font-weight:700; margin:0;">我的订单</h5>
                                    <small class="text-muted">查看交易流水明细</small>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

</div>
    </div>
  </div>

<script src="/assets/js/chart.js"></script>
<?php include 'foot.php';?>

<!-- ECharts 引入 -->
<script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>

<script>
$(document).ready(function(){
    // 原有的 AJAX 逻辑（保留，用于获取统计数据）
	$.ajax({
		type : "GET",
		url : "ajax2.php?act=getcount",
		dataType : 'json',
		async: true,
		success : function(data) {
			$('#orders').html(data.orders);
			$('#orders_today').html(data.orders_today);
			$('#settle_money').html(data.settle_money);
			$('#order_today_all').html(data.order_today_all);
			$('#order_lastday_all').html(data.order_lastday_all);
			$.each(data.channels, function (i, item) {
				$('#paytypes').append('<th style="text-align:center;"><img src="/assets/icon/'+item.name+'.ico" width="18px">&nbsp;'+item.showname+'</th>');
			});
			$.each(data.channels, function (i, item) {
				$('#order_today').append('<td>今日：'+item.order_today+' 元</td>');
				$('#order_lastday').append('<td>昨日：'+item.order_lastday+' 元</td>');
				$('#success_rate').append('<td>成功率：'+item.success_rate+' %</td>');
				$('#payrates').append('<td>费率：0%（开户即用，不抽成、不按笔收费）</td>');
			});
		}
	});
	
	<?php if(!empty($conf['modal'])){?>
	$('#myModal').modal('show');
	<?php }?>

    // ===== 初始化 K 线图 =====
    var chartDom = document.getElementById('tradeChart');
    if (chartDom && typeof echarts !== 'undefined') {
        var myChart = echarts.init(chartDom);
        var option = {
            tooltip: { trigger: 'axis' },
            grid: { top: 30, right: 20, bottom: 20, left: 50 },
            xAxis: { type: 'category', data: ['10-04', '10-05', '10-06', '10-07', '10-08', '10-09', '10-10'] },
            yAxis: { type: 'value', splitLine: { lineStyle: { type: 'dashed' } } },
            series: [{
                data: [12.5, 18.2, 15.8, 24.0, 21.5, 28.0, 32.5],
                type: 'line', smooth: true, symbol: 'circle', symbolSize: 6,
                itemStyle: { color: '#1677ff' }, lineStyle: { width: 3 },
                areaStyle: { color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{ offset: 0, color: 'rgba(22,119,255,0.3)' }, { offset: 1, color: 'rgba(22,119,255,0.05)' }]) }
            }]
        };
        myChart.setOption(option);
        window.addEventListener('resize', function() { myChart.resize(); });
    }

    // ===== 测试下单表单逻辑 =====
    window.testPay = function() {
        var amount = $('#test_amount').val();
        var channel = $('#test_channel').val();
        var ip = $('#test_ip').val();
        var resultDiv = $('#testResult');
        resultDiv.show().html('⏳ 正在生成 H5 支付链接，通过【' + ip + '】策略跳转...');
        setTimeout(function() {
            resultDiv.html('✅ 测试订单生成成功！<br>金额：¥' + amount + '<br>通道：' + channel + '<br>IP跳转：' + ip);
        }, 1000);
        return false;
    };

    // ===== 模拟实时进单（自动滚动新订单） =====
    var orderIndex = 1;
    setInterval(function() {
        var tableBody = $('#liveOrderTable tbody');
        if (tableBody.length === 0) return;
        
        var randomAmount = (Math.random() * 200 + 1).toFixed(2);
        var randomIp = ['广东电信', '浙江联通', '江苏移动', '四川电信', '湖北联通'][Math.floor(Math.random() * 5)];
        var randomOrder = 'H520261010' + (123400 + orderIndex++);
        var isPaid = Math.random() > 0.2;
        
        var newRow = '<tr style="background:#f0fdf4; transition: background 1s;">' +
            '<td style="padding-left:20px; font-family:monospace;">' + randomOrder + '</td>' +
            '<td class="text-primary font-bold">¥' + randomAmount + '</td>' +
            '<td><span class="label label-success" style="background:#e6f7ea; color:#07c160;">H5线上</span></td>' +
            '<td><span class="label label-info" style="background:#e0f2fe; color:#0369a1;">' + randomIp + '</span></td>' +
            '<td>' + (isPaid ? '<span class="label label-success">已支付</span>' : '<span class="label label-warning">待支付</span>') + '</td>' +
        '</tr>';
        
        tableBody.prepend(newRow);
        tableBody.find('tr:last').remove(); // 保持只有3行
        
        setTimeout(function(){
            tableBody.find('tr:first').css('background', '');
        }, 1500);
    }, 4000); // 每 4 秒进一单

    // 原有的 Tab 切换逻辑
	$('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
		var target = $(e.target).attr("href");
		if (target === '#order-tab') {
			loadOrderChart();
		}
	});

	function loadOrderChart() {
		if (window.orderChartInstance) {
			window.orderChartInstance.destroy();
		}
		$('#order-chart-container').html('<div class="text-center" style="padding:100px 0;">加载中...</div>');
		$.ajax({
			type : "GET",
			url : "ajax2.php?act=orderCount",
			dataType : 'json',
			async : true,
			success : function(data) {
				initOrderChart(data);
			}
		});
	}

	function initOrderChart(data) {
		$('#order-chart-container').html('<canvas id="orderChart"></canvas>');
		var ctx = document.getElementById('orderChart').getContext('2d');
		window.orderChartInstance = new Chart(ctx, {
			type: 'line',
			data: {
				labels: data.labels,
				datasets: data.datasets
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				scales: { y: { beginAtZero: true, ticks: { callback: function(value) { return '¥' + value; } } } },
				plugins: { legend: { display: true, position: 'top' }, tooltip: { mode: 'index', intersect: false } }
			}
		});
	}
});
</script>