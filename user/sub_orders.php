<?php
$is_defend = true;
include("../includes/common.php");
if($islogin2 != 1) exit("<script>location.href='./login.php';</script>");
$uid = (int)$userrow['uid'];

function gd3($DB,$root,$max=3){ $r=[];$q=[['u'=>$root,'d'=>1]]; while($q){$c=array_shift($q); if($c['d']>=$max)continue; foreach($DB->getAll("SELECT uid FROM pay_user WHERE upid=:u",[':u'=>$c['u']]) as $k){$r[]=(int)$k['uid'];$q[]=['u'=>(int)$k['uid'],'d'=>$c['d']+1];}} return $r;}
$desc = gd3($DB,$uid);
$allUids = array_merge([$uid],$desc);
$in = implode(',', array_map('intval', $allUids));

$scope = $_GET['scope'] ?? 'sub';
$uidFilter = $scope === 'mine' ? "uid={$uid}" : "uid IN ($in)";
$kw = trim($_GET['kw'] ?? '');
$extra = '';
if ($kw !== '') {
    $kwEsc = addslashes($kw);
    $extra = " AND (trade_no LIKE '%{$kwEsc}%' OR out_trade_no LIKE '%{$kwEsc}%')";
}

$page = max(1, (int)($_GET['page'] ?? 1));
$size = 30;
$offset = ($page-1)*$size;

$total = (int)$DB->getColumn("SELECT COUNT(*) FROM pay_order WHERE $uidFilter OR (JSON_EXTRACT(ext, '$.qrcode_uid') = $uid) $extra");
$rows = $DB->getAll("SELECT trade_no,uid,out_trade_no,name,money,realmoney,status,notify,addtime FROM pay_order WHERE $uidFilter OR (JSON_EXTRACT(ext, '$.qrcode_uid') = $uid) $extra ORDER BY trade_no DESC LIMIT $size OFFSET $offset");
$pages = max(1, ceil($total/$size));

$title='下级订单';
include './head.php';
?>
<style>
.so-card{background:#fff;border-radius:14px;padding:22px;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:20px;}
table.so{width:100%;border-collapse:collapse;font-size:13px;}
table.so th,table.so td{padding:10px;border-bottom:1px solid #eef2f7;text-align:left;}
table.so th{background:#f7fafd;color:#555;font-weight:600;}
.tag{display:inline-block;padding:2px 8px;border-radius:8px;font-size:11px;font-weight:700;}
.st-done{background:#d9f7be;color:#389e0d;}
.st-wait{background:#fff7e6;color:#d46b08;}
.st-fail{background:#fff1f0;color:#cf1322;}
.tabs a{padding:8px 18px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;margin-right:8px;background:#f1f5f9;color:#333;}
.tabs a.on{background:#1677ff;color:#fff;}
.pg{margin-top:16px;text-align:center;}
.pg a{padding:6px 14px;margin:0 4px;border-radius:6px;background:#f1f5f9;color:#333;text-decoration:none;font-size:13px;}
.pg a.on{background:#1677ff;color:#fff;}
</style>
<div id="content" class="app-content" role="main">
<div class="app-content-body">
<div class="bg-light lter b-b wrapper-md"><h1 class="m-n font-thin h3"><i class="fa fa-list-alt"></i> 下级订单记录</h1></div>
<div class="wrapper-md">
  <div class="so-card" style="padding:14px;">
    <div class="tabs" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
      <div>
        <a href="?scope=sub<?php echo $kw?('&kw='.urlencode($kw)):'';?>" class="<?php echo $scope!=='mine'?'on':'';?>">含下级</a>
        <a href="?scope=mine<?php echo $kw?('&kw='.urlencode($kw)):'';?>" class="<?php echo $scope==='mine'?'on':'';?>">只看我的</a>
      </div>
      <form method="get" style="display:flex;gap:8px;align-items:center;">
        <input type="hidden" name="scope" value="<?php echo htmlspecialchars($scope);?>">
        <input type="text" name="kw" value="<?php echo htmlspecialchars($kw);?>" placeholder="订单号搜索" style="padding:8px 14px;border:1px solid #d9e2ec;border-radius:8px;font-size:13px;">
        <button type="submit" class="tabs" style="padding:8px 18px;border:none;border-radius:8px;background:#1677ff;color:#fff;font-weight:700;cursor:pointer;font-size:13px;">搜索</button>
      </form>
    </div>
  </div>
  <div class="so-card">
    <div style="color:#555;font-size:13px;margin-bottom:14px;">共 <b><?php echo $total;?></b> 笔</div>
    <table class="so">
      <thead><tr>
        <th>订单号</th><th>归属</th><th>商品</th><th>金额</th><th>状态</th><th>回调</th><th>时间</th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $r):
        $isMine = ((int)$r['uid']===$uid);
      ?>
        <tr>
          <td><code style="font-size:11px;"><?php echo htmlspecialchars($r['trade_no']);?></code><br><small><?php echo htmlspecialchars($r['out_trade_no']);?></small></td>
          <td>
            <?php if ($isMine): ?>
              <span style="color:#1677ff;font-weight:700;">我</span>
            <?php else: ?>
              <span style="color:#888;">下级 <?php echo $r['uid'];?></span>
            <?php endif; ?>
          </td>
          <td><?php echo htmlspecialchars($r['name']);?></td>
          <td><b style="color:#07c160;">¥<?php echo number_format((float)($r['realmoney']?:$r['money']),2);?></b></td>
          <td>
            <?php if ((int)$r['status']===1): ?>
              <span class="tag st-done">✅ 成功</span>
            <?php elseif ((int)$r['status']===0): ?>
              <span class="tag st-wait">⏳ 待付</span>
            <?php else: ?>
              <span class="tag st-fail">❌ 其他</span>
            <?php endif; ?>
          </td>
          <td><?php $n=(int)$r['notify']; echo $n===0?'✅':($n===1?'⏳':'❌');?></td>
          <td><small><?php echo $r['addtime'];?></small></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="7" style="text-align:center;padding:40px;color:#999;">暂无订单</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
    <?php if ($pages > 1): ?>
    <div class="pg">
      <?php for($i=1; $i<=$pages && $i<=20; $i++):?>
        <a href="?scope=<?php echo $scope;?>&kw=<?php echo urlencode($kw);?>&page=<?php echo $i;?>" class="<?php echo $i===$page?'on':'';?>"><?php echo $i;?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
</div>
</div>
<?php include './foot.php'; ?>