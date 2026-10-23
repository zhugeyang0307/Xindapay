<?php
require_once('../../includes/common.php');
if($islogin != 1) exit("<script>location.href='../login.php';</script>");

$my = $_GET['my'] ?? '';

// ========== AJAX: 切换状态 ==========
if($my == 'toggle') {
    $id = intval($_POST['id'] ?? 0);
    $DB->exec("UPDATE pay_qrcode SET status=1-status WHERE id=:id", [':id'=>$id]);
    exit(json_encode(['code'=>0,'msg'=>'已切换']));
}

// ========== AJAX: 删除 ==========
if($my == 'delete') {
    $id = intval($_POST['id'] ?? 0);
    $DB->delete('qrcode', ['id'=>$id]);
    exit(json_encode(['code'=>0,'msg'=>'已删除']));
}

// ========== AJAX: 批量操作 ==========
if($my == 'batch') {
    $ids = $_POST['ids'] ?? '';
    $op = $_POST['op'] ?? '';
    if(!$ids || !in_array($op, ['enable','disable','delete'])) exit(json_encode(['code'=>-1,'msg'=>'参数错误']));
    $arr = array_map('intval', explode(',', $ids));
    $idStr = implode(',', $arr);
    if($op == 'enable') $DB->exec("UPDATE pay_qrcode SET status=1 WHERE id IN ($idStr)");
    elseif($op == 'disable') $DB->exec("UPDATE pay_qrcode SET status=0 WHERE id IN ($idStr)");
    else $DB->exec("DELETE FROM pay_qrcode WHERE id IN ($idStr)");
    exit(json_encode(['code'=>0,'msg'=>'已操作 '.count($arr).' 条']));
}

// ========== 筛选 ==========
$where = '1=1';
$params = [];
if(!empty($_GET['uid'])) { $where .= ' AND q.uid=:uid'; $params[':uid'] = intval($_GET['uid']); }
if(!empty($_GET['channel'])) { $where .= ' AND q.channel=:ch'; $params[':ch'] = $_GET['channel']; }
if($_GET['status'] !== '' && isset($_GET['status'])) { $where .= ' AND q.status=:st'; $params[':st'] = intval($_GET['status']); }

// ========== 加载列表（JOIN 商户表） ==========
$list = $DB->getAll("
    SELECT q.*, u.username, u.rate 
    FROM pay_qrcode q 
    LEFT JOIN pay_user u ON u.uid = q.uid 
    WHERE $where 
    ORDER BY q.id DESC LIMIT 500", $params);

// 统计
$stat = $DB->getAll("SELECT channel, COUNT(*) as cnt, SUM(use_count) as total FROM pay_qrcode GROUP BY channel");
?>
<?php include '../head.php'; ?>

<div class="container-fluid">

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
  <h3 style="margin:0;font-weight:600;color:#001529;">商户收款码管理</h3>
  <div>
    <a href="/admin/" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> 返回后台</a>
    <a href="/" class="btn btn-default btn-sm" target="_blank"><i class="fa fa-home"></i> 前台首页</a>
  </div>
</div>


<!-- 审计看板 -->
<div style="background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #eef2f7;">
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px;">
        <div>
            <h3 style="margin: 0 0 5px 0; font-size: 18px; color: #1e293b; font-weight: bold;">📊 全量收款审计看板</h3>
            <p style="margin: 0; font-size: 13px; color: #64748b;">资金不经过平台。本审计数据实时同步自各商户绑定的独立收款码。</p>
        </div>
    </div>

    <?php
        $totalQr = 0; $totalUse = 0;
        foreach($stat as $s){ $totalQr += $s['cnt']; $totalUse += $s['total']; }
        
        // 翻译通道名称
        $channelMap = [
            'alipay' => '支付宝',
            'alipay_personal' => '支付宝个人码',
            'alipay_biz' => '支付宝商家码',
            'wechat' => '微信支付',
            'wechat_personal' => '微信个人码',
            'wechat_biz' => '微信商家码',
            'aggregate_both' => '聚合码(双端通用)',
            'aggregate_alipay' => '聚合码(仅支付宝)',
            'aggregate_wechat' => '聚合码(仅微信)'
        ];
    ?>

    <!-- 顶部总计 -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
        <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 20px; text-align: center;">
            <div style="color: #1e40af; font-size: 14px; font-weight: bold; margin-bottom: 5px;">全站收款码总数</div>
            <div style="font-size: 32px; font-weight: 900; color: #3b82f6; line-height: 1;"><?php echo $totalQr; ?> <span style="font-size: 14px; font-weight: normal;">个</span></div>
        </div>
        <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 10px; padding: 20px; text-align: center;">
            <div style="color: #065f46; font-size: 14px; font-weight: bold; margin-bottom: 5px;">系统累计触达次数</div>
            <div style="font-size: 32px; font-weight: 900; color: #10b981; line-height: 1;"><?php echo $totalUse; ?> <span style="font-size: 14px; font-weight: normal;">次</span></div>
        </div>
    </div>

    <!-- 通道明细网格 -->
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px;">
        <?php foreach($stat as $s): 
            $cName = $channelMap[$s['channel']] ?? $s['channel'];
        ?>
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 15px; display: flex; justify-content: space-between; align-items: center;">
            <div style="font-size: 13px; color: #475569; font-weight: bold;"><?php echo htmlspecialchars($cName); ?></div>
            <div style="font-size: 12px; color: #94a3b8;">
                <span style="color: #3b82f6; font-weight: bold; font-size: 14px;"><?php echo $s['cnt']; ?></span> 个 / 
                <span style="color: #10b981; font-weight: bold; font-size: 14px;"><?php echo $s['total']; ?></span> 次
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- 筛选 -->
<form class="toolbar" method="get">
  <label>商户UID：</label>
  <input type="number" name="uid" value="<?php echo htmlspecialchars($_GET['uid'] ?? ''); ?>" placeholder="留空=全部" style="width:120px;">

  <label>通道：</label>
  <select name="channel">
    <option value="">全部</option>
    <option value="wechat" <?php if(($_GET['channel'] ?? '')=='wechat')echo 'selected';?>>微信</option>
    <option value="alipay" <?php if(($_GET['channel'] ?? '')=='alipay')echo 'selected';?>>支付宝</option>
    <option value="bank" <?php if(($_GET['channel'] ?? '')=='bank')echo 'selected';?>>银行卡</option>
    <option value="aggregate_wechat" <?php if(($_GET['channel'] ?? '')=='aggregate_wechat')echo 'selected';?>>聚合码·微信</option>
    <option value="aggregate_alipay" <?php if(($_GET['channel'] ?? '')=='aggregate_alipay')echo 'selected';?>>聚合码·支付宝</option>
    <option value="aggregate_both" <?php if(($_GET['channel'] ?? '')=='aggregate_both')echo 'selected';?>>聚合码·通用</option>
  </select>

  <label>状态：</label>
  <select name="status">
    <option value="">全部</option>
    <option value="1" <?php if(($_GET['status'] ?? '')==='1')echo 'selected';?>>启用</option>
    <option value="0" <?php if(($_GET['status'] ?? '')==='0')echo 'selected';?>>停用</option>
  </select>

  <button type="submit" class="btn btn-primary btn-sm">筛选</button>
  <a href="./" class="btn btn-default btn-sm">重置</a>
</form>

<!-- 批量操作 -->
<div class="toolbar">
  <button class="btn btn-success btn-sm" onclick="batchOp('enable')">批量启用</button>
  <button class="btn btn-warning btn-sm" onclick="batchOp('disable')">批量停用</button>
  <button class="btn btn-danger btn-sm" onclick="batchOp('delete')">批量删除</button>
  <span style="color:#999;font-size:12px;">共 <?php echo count($list); ?> 条（最多显示 500）</span>
</div>

<!-- 列表 -->
<div class="card">
<table class="table table-striped table-hover">
  <thead>
    <tr>
      <th style="width:36px;"><input type="checkbox" id="checkAll"></th>
      <th style="width:60px;">ID</th>
      <th style="width:80px;">商户UID</th>
      <th style="width:120px;">商户名</th>
      <th style="width:80px;">费率</th>
      <th style="width:130px;">通道类型</th>
      <th style="width:90px;">预览</th>
      <th>备注</th>
      <th style="width:80px;">已用</th>
      <th style="width:70px;">状态</th>
      <th style="width:140px;">操作</th>
    </tr>
  </thead>
  <tbody>
  <?php if(empty($list)): ?>
    <tr><td colspan="11" style="text-align:center;padding:40px;color:#999;">暂无数据</td></tr>
  <?php else: foreach($list as $qr):
    $ch = $qr['channel'];
    if(strpos($ch, 'aggregate') === 0) $badge = 'badge-agg';
    elseif($ch == 'wechat') $badge = 'badge-wx';
    elseif($ch == 'alipay') $badge = 'badge-ali';
    else $badge = 'badge-bank';

    $isAgg = (strpos($ch, 'aggregate') === 0);
    $cat = $qr['category'] ?: ($isAgg ? 'qrcode' : 'qrcode');
  ?>
    <tr>
      <td><input type="checkbox" class="check-item" value="<?php echo $qr['id'];?>"></td>
      <td><?php echo $qr['id']; ?></td>
      <td><b><?php echo $qr['uid']; ?></b></td>
      <td><?php echo htmlspecialchars($qr['username'] ?: '-'); ?></td>
      <td><?php echo floatval($qr['rate']); ?>%</td>
      <td><span class="label <?php echo $badge; ?>"><?php echo htmlspecialchars($ch); ?></span></td>
      <td>
        <?php if($qr['qrcode_img']): ?>
          <img src="/<?php echo $qr['qrcode_img']; ?>" class="qr-thumb" style="width: 60px; height: 60px; object-fit: contain; border-radius: 6px; border: 1px solid #e2e8f0; cursor: pointer;" onclick="preview('/<?php echo $qr['qrcode_img'];?>')">
        <?php else: ?>-<?php endif; ?>
      </td>
      <td style="font-size:12px;"><?php echo htmlspecialchars($qr['remark']); ?></td>
      <td><?php echo intval($qr['use_count']); ?></td>
      <td>
        <?php if($qr['status']): ?>
          <span style="color:#27ae60;">●启用</span>
        <?php else: ?>
          <span style="color:#999;">●停用</span>
        <?php endif; ?>
      </td>
      <td>
        <a href="javascript:;" onclick="toggleQr(<?php echo $qr['id'];?>)" class="btn btn-xs btn-default">
          <?php echo $qr['status'] ? '停用' : '启用'; ?>
        </a>
        <a href="javascript:;" onclick="delQr(<?php echo $qr['id'];?>)" class="btn btn-xs btn-danger">删除</a>
      </td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>
</div>

</div>

<script src="/assets/vendor/jquery/3.4.1/jquery.min.js"></script>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.js"></script>
<script>
document.getElementById('checkAll').onchange = function(){
  document.querySelectorAll('.check-item').forEach(function(c){ c.checked = this.checked; }.bind(this));
};

function getChecked(){
  return Array.prototype.slice.call(document.querySelectorAll('.check-item:checked')).map(function(c){return c.value;}).join(',');
}

function batchOp(op){
  var ids = getChecked();
  if(!ids){ layer.msg('请先勾选'); return; }
  var msg = op === 'delete' ? '确定删除选中的 '+ids.split(',').length+' 条？' : '确定操作？';
  if(!confirm(msg)) return;
  var fd = new FormData();
  fd.append('ids', ids);
  fd.append('op', op);
  fetch('?my=batch', {method:'POST', body:fd}).then(r=>r.json()).then(function(d){
    layer.msg(d.msg);
    if(d.code === 0) setTimeout(function(){ location.reload(); }, 800);
  });
}

function toggleQr(id){
  var fd = new FormData(); fd.append('id', id);
  fetch('?my=toggle', {method:'POST', body:fd}).then(r=>r.json()).then(function(){ location.reload(); });
}

function delQr(id){
  if(!confirm('确定删除该收款码？')) return;
  var fd = new FormData(); fd.append('id', id);
  fetch('?my=delete', {method:'POST', body:fd}).then(r=>r.json()).then(function(){ location.reload(); });
}

function preview(url){ window.open(url, '_blank'); }
</script>

