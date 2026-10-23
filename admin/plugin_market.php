<?php
include("../includes/common.php");
$title = '插件市场管理';
include './head.php';
if($islogin==1){}else exit("<script>location.href='./login.php';</script>");

$my = $_GET['my'] ?? '';

// 保存上架
if($my == 'save') {
    $plugin = trim($_POST['plugin'] ?? '');
    $price  = floatval($_POST['price'] ?? 0);
    $sort   = intval($_POST['sort'] ?? 0);
    $remark = trim($_POST['remark'] ?? '');
    $status = intval($_POST['status'] ?? 1);
    if(!$plugin) exit(json_encode(['code'=>-1,'msg'=>'插件名不能为空']));
    $DB->exec("INSERT INTO pay_plugin_market (plugin, price_usdt, sort, status, remark)
               VALUES (:p,:pr,:s,:st,:r)
               ON DUPLICATE KEY UPDATE price_usdt=:pr2, sort=:s2, status=:st2, remark=:r2",
              [':p'=>$plugin, ':pr'=>$price, ':s'=>$sort, ':st'=>$status, ':r'=>$remark,
               ':pr2'=>$price, ':s2'=>$sort, ':st2'=>$status, ':r2'=>$remark]);
    exit(json_encode(['code'=>0,'msg'=>'已保存']));
}

// 下架
if($my == 'del') {
    $plugin = trim($_POST['plugin'] ?? '');
    $DB->exec("DELETE FROM pay_plugin_market WHERE plugin=:p", [':p'=>$plugin]);
    exit(json_encode(['code'=>0,'msg'=>'已删除']));
}

// 扫描本地插件目录
$installed = [];
$dir = ROOT.'plugins/';
foreach (scandir($dir) as $d) {
    if ($d === '.' || $d === '..') continue;
    $f = $dir.$d.'/'.$d.'_plugin.php';
    if (file_exists($f)) {
        $c = file_get_contents($f);
        // 简单提取 showname
        if (preg_match("/'showname'\s*=>\s*'([^']+)'/", $c, $m)) $showname = $m[1];
        elseif (preg_match('/"showname"\s*=>\s*"([^"]+)"/', $c, $m)) $showname = $m[1];
        else $showname = $d;
        $installed[$d] = $showname;
    }
}

// 已上架
$market = [];
$rows = $DB->getAll("SELECT * FROM pay_plugin_market");
foreach ($rows as $r) $market[$r['plugin']] = $r;
?>
<div class="app-content-body">
<div class="bg-light lter b-b wrapper-md">
  <h1 class="m-n font-thin h3"><i class="fa fa-plug"></i> 插件市场管理（共 <?php echo count($installed);?> 个插件，已上架 <?php echo count($market);?> 个）</h1>
</div>

<div class="wrapper-md">
  <div class="card" style="background:#fff;padding:20px;border-radius:8px;margin-bottom:20px;">
    <h4>已上架</h4>
    <table class="table table-hover">
      <thead><tr><th>插件名</th><th>显示名</th><th>价格(USDT)</th><th>排序</th><th>卖点</th><th>状态</th><th>操作</th></tr></thead>
      <tbody>
      <?php if(empty($market)):?>
        <tr><td colspan="7" style="text-align:center;color:#999;padding:30px;">暂无上架插件</td></tr>
      <?php else: foreach($market as $p=>$m):?>
        <tr>
          <td><code><?php echo htmlspecialchars($p);?></code></td>
          <td><?php echo htmlspecialchars($installed[$p] ?? '-');?></td>
          <td><b style="color:#e74c3c;"><?php echo floatval($m['price_usdt']);?> U</b></td>
          <td><?php echo intval($m['sort']);?></td>
          <td style="font-size:12px;"><?php echo htmlspecialchars($m['remark']);?></td>
          <td><?php echo $m['status']?'<span style="color:green;">上架</span>':'<span style="color:#999;">下架</span>';?></td>
          <td>
            <button class="btn btn-xs btn-primary" onclick="editPlugin('<?php echo $p;?>',<?php echo floatval($m['price_usdt']);?>,<?php echo intval($m['sort']);?>,<?php echo intval($m['status']);?>,'<?php echo htmlspecialchars($m['remark'],ENT_QUOTES);?>')">编辑</button>
            <button class="btn btn-xs btn-danger" onclick="delPlugin('<?php echo $p;?>')">删除</button>
          </td>
        </tr>
      <?php endforeach; endif;?>
      </tbody>
    </table>
  </div>

  <div class="card" style="background:#fff;padding:20px;border-radius:8px;">
    <h4>可上架插件（未上架）</h4>
    <table class="table table-hover">
      <thead><tr><th>插件名</th><th>显示名</th><th>操作</th></tr></thead>
      <tbody>
      <?php foreach($installed as $p=>$name): if(isset($market[$p])) continue;?>
        <tr>
          <td><code><?php echo htmlspecialchars($p);?></code></td>
          <td><?php echo htmlspecialchars($name);?></td>
          <td><button class="btn btn-xs btn-success" onclick="editPlugin('<?php echo $p;?>',0,0,1,'')">上架</button></td>
        </tr>
      <?php endforeach;?>
      </tbody>
    </table>
  </div>
</div>
</div>

<script src="<?php echo $cdnpublic?>jquery/3.4.1/jquery.min.js"></script>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.js"></script>
<script>
function editPlugin(p, price, sort, status, remark){
  layer.prompt({title:'价格(USDT)', value:price}, function(v, idx){
    layer.close(idx);
    var p2 = prompt('排序（数字，越大越靠前）', sort);
    if(p2 === null) return;
    var r2 = prompt('卖点介绍（一句话）', remark);
    if(r2 === null) return;
    var s2 = confirm('是否上架？点"确定"=上架，"取消"=下架') ? 1 : 0;
    var fd = new FormData();
    fd.append('plugin', p); fd.append('price', v); fd.append('sort', p2);
    fd.append('remark', r2); fd.append('status', s2);
    fetch('?my=save',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
      layer.msg(d.msg); if(d.code===0) setTimeout(()=>location.reload(),800);
    });
  });
}
function delPlugin(p){
  if(!confirm('确定删除？')) return;
  var fd = new FormData(); fd.append('plugin', p);
  fetch('?my=del',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    layer.msg(d.msg); setTimeout(()=>location.reload(),800);
  });
}
</script>
<?php include './foot.php';?>
