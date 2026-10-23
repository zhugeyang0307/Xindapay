<?php
include("../includes/common.php");
if($islogin2==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
$title='到账对账单';
include './head.php';
?>
<style>
.dates{max-width:130px;}
</style>
<link href="../assets/css/datepicker.css" rel="stylesheet">
<div id="content" class="app-content" role="main">
  <div class="app-content-body">
    <div class="bg-light lter b-b wrapper-md hidden-print">
      <h1 class="m-n font-thin h3">到账对账单</h1>
    </div>
    <div class="wrapper-md control">
      <div class="panel panel-default">
        <div class="panel-heading font-bold">
          <h3 class="panel-title">到账对账单</h3>
        </div>
        <form method="GET" class="form-inline" style="padding:15px;">
          <div class="input-group input-daterange">
            <input type="text" id="starttime" name="starttime" class="form-control dates" placeholder="开始日期" autocomplete="off">
            <span class="input-group-addon">→</span>
            <input type="text" id="endtime" name="endtime" class="form-control dates" placeholder="结束日期" autocomplete="off">
          </div>
          <button class="btn btn-primary" type="submit">查询</button>
          <a class="btn btn-default" href="./bill.php">重置</a>
          <a class="btn btn-success" href="javascript:exportBill()">导出 CSV</a>
        </form>

        <?php
        $where = "uid=" . intval($uid);
        if (!empty($_GET['starttime'])) $where .= " AND server_time>='" . daddslashes($_GET['starttime']) . " 00:00:00'";
        if (!empty($_GET['endtime']))   $where .= " AND server_time<='" . daddslashes($_GET['endtime'])   . " 23:59:59'";

        $page = max(1, intval($_GET['page'] ?? 1));
        $size = 20;
        $offset = ($page - 1) * $size;

        $total = $DB->getColumn("SELECT COUNT(*) FROM pay_listen_log WHERE {$where}");
        $rows  = $DB->getAll("SELECT * FROM pay_listen_log WHERE {$where} ORDER BY id DESC LIMIT {$offset},{$size}");
        ?>
        <table class="table table-striped table-hover table-bordered">
          <thead>
            <tr>
              <th>到账时间</th>
              <th>金额</th>
              <th>来源</th>
              <th>原始通知</th>
              <th>匹配订单号</th>
              <th>状态</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
              <td><?php echo $r['server_time']; ?></td>
              <td><b style="color:#e74c3c;">¥<?php echo number_format($r['amount'], 2); ?></b></td>
              <td><?php
                $pkgName = $r['pkg'];
                if (strpos($pkgName, 'Alipay') !== false) echo '🅰️ 支付宝';
                elseif (strpos($pkgName, 'tencent') !== false) echo '💚 微信';
                elseif (strpos($pkgName, 'leshua') !== false) echo '🟠 乐刷';
                else echo htmlspecialchars($pkgName);
              ?></td>
              <td style="max-width:400px;word-break:break-all;font-size:12px;color:#666;"><?php echo htmlspecialchars($r['raw']); ?></td>
              <td><?php echo $r['order_trade_no'] ? htmlspecialchars($r['order_trade_no']) : '<span style="color:#999;">-</span>'; ?></td>
              <td><?php echo $r['match_status'] == 1 ? '<span style="color:#27ae60;">✅ 已匹配</span>' : '<span style="color:#f39c12;">⚠️ 未匹配</span>'; ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="6" style="text-align:center;padding:30px;color:#999;">暂无数据</td></tr>
            <?php endif; ?>
          </tbody>
        </table>

        <?php if ($total > $size): ?>
        <div style="text-align:center;padding:10px;">
          <?php for ($i = 1; $i <= ceil($total / $size); $i++): ?>
            <a class="btn btn-xs <?php echo $i == $page ? 'btn-primary' : 'btn-default'; ?>" href="?page=<?php echo $i; ?>&starttime=<?php echo urlencode($_GET['starttime'] ?? ''); ?>&endtime=<?php echo urlencode($_GET['endtime'] ?? ''); ?>"><?php echo $i; ?></a>
          <?php endfor; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script src="<?php echo $cdnpublic?>bootstrap-datepicker/1.10.0/js/bootstrap-datepicker.min.js"></script>
<script src="<?php echo $cdnpublic?>bootstrap-datepicker/1.10.0/locales/bootstrap-datepicker.zh-CN.min.js"></script>
<script>
function exportBill(){
  var s = $("input[name='starttime']").val();
  var e = $("input[name='endtime']").val();
  window.location.href = './download.php?act=bill&starttime=' + s + '&endtime=' + e;
}
$(function(){
  $('.input-daterange').datepicker({format:'yyyy-mm-dd', autoclose:true, clearBtn:true, language:'zh-CN'});
});
</script>
</body>
</html>