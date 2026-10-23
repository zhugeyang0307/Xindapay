<?php
include("../includes/common.php");
if($islogin2!=1){
    if (($_GET['act'] ?? '') === 'query') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['code'=>-1, 'msg'=>'登录已过期，请重新登录']);
        exit;
    }
    exit("<script language='javascript'>window.location.href='./login.php';</script>");
}

// 取真实 IP（提前定义，供 act 分支用）
function _myip_real() {
    foreach (['HTTP_X_REAL_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $k) {
        $v = $_SERVER[$k] ?? '';
        if ($v) { $v = trim(explode(',', $v)[0]); if ($v) return $v; }
    }
    return '';
}

if (($_GET['act'] ?? '') === 'query') {
    header('Content-Type: application/json; charset=utf-8');
    $qip = trim($_GET['ip'] ?? '');
    if ($qip === 'me') { $qip = _myip_real(); }
    if (!filter_var($qip, FILTER_VALIDATE_IP)) {
        echo json_encode(['code'=>-1, 'msg'=>'IP 格式错误']); exit;
    }
    try {
        require_once __DIR__ . '/../route/lib/IpLocation.php';
        if (!defined('__IPLOC_INITED')) { IpLocation::init(); define('__IPLOC_INITED', 1); }
        $r = IpLocation::lookup($qip);
        echo json_encode([
            'code'=>0, 'ip'=>$qip,
            'province'=>preg_replace('/[省市区]$/u', '', $r['province'] ?? ''),
            'city'=>$r['city'] ?? '',
            'isp'=>$r['isp'] ?? ''
        ]);
    } catch (Throwable $e) {
        echo json_encode(['code'=>-1, 'msg'=>$e->getMessage()]);
    }
    exit;
}
$title='IP 检测';
include './head.php';

$uid = (int)$userrow['uid'];

$myIp = _myip_real();

// 本地库查询
$loc = ['province'=>'','city'=>'','isp'=>''];
try {
    require_once __DIR__ . '/../route/lib/IpLocation.php';
    if (class_exists('IpLocation')) {
        if (!defined('__IPLOC_INITED')) { IpLocation::init(); define('__IPLOC_INITED', 1); }
        $r = IpLocation::lookup($myIp);
        $loc['province'] = preg_replace('/[省市区]$/u', '', $r['province'] ?? '');
        $loc['city'] = $r['city'] ?? '';
        $loc['isp'] = $r['isp'] ?? '';
    }
} catch (Throwable $e) {}
?>
<div id="content" class="app-content" role="main">
<div class="app-content-body">

<div class="bg-light lter b-b wrapper-md">
  <h1 class="m-n font-thin h3"><i class="fa fa-map-marker" style="color:#0d47a1;"></i> IP 检测工具</h1>
</div>

<div class="wrapper-md">

<div class="alert alert-warning" style="margin-bottom:16px;"><b>⚠️ 提示：</b>如果你开启了 <b>VPN / 代理 / 加速器</b>，显示的 IP 可能是代理 IP，不是你的真实地区。请<b>关闭 VPN 后刷新</b>，或<b>手动输入真实 IP</b>查询。</div>
<div class="panel panel-primary">
  <div class="panel-heading font-bold">当前访问 IP 信息</div>
  <div class="panel-body">
    <div style="font-size:16px;line-height:2.2;">
      <div><b>IP 地址：</b><code style="font-size:18px;color:#e63946;"><?= htmlspecialchars($myIp) ?></code>
        <a href="https://www.ip138.com/iplookup.asp?ip=<?= urlencode($myIp) ?>&action=2" target="_blank" class="btn btn-xs btn-default" style="margin-left:8px;">在 ip138 查看 →</a>
      </div>
      <div><b>省份：</b><?= htmlspecialchars($loc['province'] ?: '未识别') ?></div>
      <div><b>城市：</b><?= htmlspecialchars($loc['city'] ?: '未识别') ?></div>
      <div><b>运营商：</b><?= htmlspecialchars($loc['isp'] ?: '未识别') ?></div>
    </div>
  </div>
</div>

<div class="panel panel-default">
  <div class="panel-heading font-bold">📌 用途说明</div>
  <div class="panel-body">
    <ul style="line-height:2;">
      <li><b>上传收款码时：</b>打开本页 → 复制 IP → 填入收款码的「绑定 IP」→ 后续仅该 IP 地域的客户能匹配到该码</li>
      <li><b>下单时自动匹配：</b>系统按客户 IP 归属地（省/市）与收款码的绑定地域对比 → 匹配则分配</li>
      <li><b>路由规则：</b>在「收款码路由」里可配更复杂规则（按省/按城/按运营商/权重）</li>
    </ul>
  </div>
</div>

<div class="panel panel-info">
  <div class="panel-heading font-bold">🔍 查询其他 IP</div>
  <div class="panel-body">
    <div style="display:flex;gap:8px;max-width:520px;">
      <input type="text" id="qip" class="form-control" placeholder="如 113.108.182.1">
      <button class="btn btn-primary" onclick="queryIp()">查询</button>
    </div>
    <div id="qres" style="margin-top:12px;font-size:14px;line-height:2;"></div>
  </div>
</div>

<div class="panel panel-default">
  <div class="panel-heading font-bold">📋 常用地区参考</div>
  <div class="panel-body">
    <p style="color:#888;">省份列表（配收款码时可直接选）：</p>
    <div style="display:flex;flex-wrap:wrap;gap:6px;">
      <?php foreach (['北京','上海','广东','江苏','浙江','山东','河南','河北','四川','湖北','湖南','福建','安徽','陕西','江西','重庆','辽宁','广西','山西','内蒙古','黑龙江','吉林','天津','贵州','云南','新疆','甘肃','海南','宁夏','青海','西藏','香港','澳门','台湾'] as $prov): ?>
        <span class="label label-default" style="padding:6px 10px;font-size:12px;"><?= $prov ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</div>

</div>
</div>
</div>

<script>
function queryIp() {
  var ip = document.getElementById('qip').value.trim();
  if (!ip) return alert('请输入 IP');
  fetch('myip.php?act=query&ip=' + encodeURIComponent(ip))
    .then(r=>r.json()).then(d=>{
      if (d.code !== 0) { document.getElementById('qres').innerHTML = '<span style="color:#e74c3c;">' + d.msg + '</span>'; return; }
      document.getElementById('qres').innerHTML =
        '<b>IP：</b>' + d.ip + '<br>' +
        '<b>省份：</b>' + (d.province || '未识别') + '<br>' +
        '<b>城市：</b>' + (d.city || '未识别') + '<br>' +
        '<b>运营商：</b>' + (d.isp || '未识别');
    });
}
</script>

<?php include './foot.php'; ?>
