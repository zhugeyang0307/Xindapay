<?php
$is_defend = true;
include("../includes/common.php");
if($islogin2==1){}else exit("<script>location.href='./login.php';</script>");

$uid = $userrow['uid'];
$TG_LINK = 'https://t.me/xindawangluo1_bot';

// 已开通插件
$owned = [];
$ep = $userrow['extra_plugins'] ?? '';
if ($ep) {
    $decoded = json_decode($ep, true);
    if (is_array($decoded)) $owned = $decoded;
}

// 已上架插件
$list = $DB->getAll("SELECT * FROM pay_plugin_market WHERE status=1 ORDER BY sort DESC, plugin ASC");

// 提取插件显示名
$installed = [];
foreach (scandir(ROOT.'plugins/') as $d) {
    if ($d === '.' || $d === '..') continue;
    $f = ROOT.'plugins/'.$d.'/'.$d.'_plugin.php';
    if (file_exists($f)) {
        $c = file_get_contents($f);
        if (preg_match("/'showname'\s*=>\s*'([^']+)'/", $c, $m)) $installed[$d] = $m[1];
        else $installed[$d] = $d;
    }
}

$title = '插件市场';
include './head.php';
?>
<style>
.pm-card { background:#fff; border-radius:14px; padding:24px; box-shadow:0 2px 14px rgba(22,119,255,.06); margin-bottom:18px; position:relative; transition:all .2s; }
.pm-card:hover { box-shadow:0 6px 20px rgba(22,119,255,.12); transform:translateY(-2px); }
.pm-card .header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:14px; }
.pm-card .title { font-size:18px; font-weight:800; color:#1a1d23; }
.pm-card .subtitle { color:#666; font-size:13px; margin-top:4px; }
.pm-card .price { font-size:32px; font-weight:900; color:#e74c3c; letter-spacing:-1px; white-space:nowrap; }
.pm-card .price .unit { font-size:14px; color:#999; font-weight:400; margin-left:4px; }
.pm-card .features { margin:14px 0 18px; }
.pm-card .feat { display:flex; align-items:center; gap:8px; color:#555; font-size:14px; padding:5px 0; }
.pm-card .feat::before { content:'✅'; font-size:14px; }
.pm-card .actions { display:flex; gap:10px; padding-top:14px; border-top:1px solid #f0f0f0; }
.btn-buy { background:linear-gradient(135deg,#1677ff,#0d5cd6); color:#fff; border:none; padding:11px 24px; border-radius:8px; font-size:14px; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:6px; transition:all .2s; }
.btn-buy:hover { color:#fff; box-shadow:0 4px 12px rgba(22,119,255,.4); }
.btn-buy:disabled, .btn-buy.owned { background:#f6ffed; color:#52c41a; border:1px solid #b7eb8f; cursor:default; }
.owned-badge { position:absolute; top:16px; right:16px; background:#f6ffed; color:#52c41a; font-size:12px; padding:4px 10px; border-radius:999px; font-weight:600; }
.empty { text-align:center; padding:60px 20px; color:#999; }
</style>

<div id="content" class="app-content" role="main">
<div class="app-content-body">
<div class="bg-light lter b-b wrapper-md">
  <h1 class="m-n font-thin h3"><i class="fa fa-plug" style="color:#1677ff;"></i> 插件市场</h1>
  <p style="color:#888;font-size:13px;margin-top:6px;">支付通道 · 代开发 · 免签收款，付款后联系客服开通</p>
</div>

<div class="wrapper-md">
  <?php if(empty($list)): ?>
    <div class="empty">
      <i class="fa fa-inbox" style="font-size:48px;color:#ddd;"></i>
      <p style="margin-top:15px;">暂无上架插件</p>
    </div>
  <?php else: ?>
    <?php foreach($list as $r):
      $plugin = $r['plugin'];
      $showname = $installed[$plugin] ?? $plugin;
      $isOwned = in_array($plugin, $owned);
      $feats = $r['features'] ? preg_split('/[\r\n]+/', trim($r['features'])) : [];
    ?>
    <div class="pm-card">
      <?php if($isOwned): ?><div class="owned-badge">✅ 已开通</div><?php endif; ?>
      <div class="header">
        <div>
          <div class="title"><?php echo htmlspecialchars($showname); ?></div>
          <?php if($r['subtitle']): ?>
            <div class="subtitle"><?php echo htmlspecialchars($r['subtitle']); ?></div>
          <?php endif; ?>
        </div>
        <div class="price"><?php echo floatval($r['price_usdt']); ?><span class="unit">USDT</span></div>
      </div>

      <?php if($r['remark']): ?>
        <div style="color:#888;font-size:13px;margin-bottom:10px;"><?php echo htmlspecialchars($r['remark']); ?></div>
      <?php endif; ?>

      <?php if(!empty($feats)): ?>
      <div class="features">
        <?php foreach($feats as $f): if(trim($f)==='')continue; ?>
          <div class="feat"><?php echo htmlspecialchars(trim($f)); ?></div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="actions">
        <?php if($isOwned): ?>
          <button class="btn-buy owned" disabled>
            <i class="fa fa-check"></i> 已开通 · 客服已为你配置
          </button>
        <?php else: ?>
          <a href="<?php echo $TG_LINK;?>" target="_blank" class="btn-buy">
            <i class="fa fa-paper-plane"></i> 联系客服购买
          </a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <div style="text-align:center;margin-top:30px;color:#999;font-size:13px;">
    购买流程：<b>联系客服</b> → 支付费用 → 客服开通 → 通知你使用
  </div>
</div>
</div>
</div>

<?php include './foot.php'; ?>
