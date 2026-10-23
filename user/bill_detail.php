<?php
include("../includes/common.php");
if($islogin2==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
$title='账单明细';
include './head.php';

$uid = (int)$userrow['uid'];
$viewUid = (int)($_GET['uid'] ?? $uid);

// 权限：只能看自己或下级树的
function _isDescBill($DB, $root, $target, $max=3) {
    if ($root === $target) return true;
    $cur = $target;
    for ($i=0; $i<$max; $i++) {
        $up = (int)$DB->getColumn("SELECT upid FROM pay_user WHERE uid=:u", [':u'=>$cur]);
        if ($up === 0) return false;
        if ($up === $root) return true;
        $cur = $up;
    }
    return false;
}
if (!_isDescBill($DB, $uid, $viewUid)) {
    exit('<div style="padding:40px;text-align:center;color:#c00;">无权查看该账单</div>');
}

$batchId = $_GET['batch_id'] ?? '';
$rows = $DB->getAll("SELECT * FROM pay_alipay_bill WHERE uid=? AND batch_id=? ORDER BY trade_time ASC", [$viewUid, $batchId]);
$owner = $DB->getRow("SELECT uid, username FROM pay_user WHERE uid=?", [$viewUid]);
?>
<div id="content" class="app-content" role="main">
  <div class="app-content-body">
    <div class="bg-light lter b-b wrapper-md">
      <h1 class="m-n font-thin h3">账单明细</h1>
    </div>
    <div class="wrapper-md control">
      <div class="panel panel-default">
        <div class="panel-heading font-bold">
          <h3 class="panel-title">
            归属：<b><?php echo $viewUid === $uid ? '我自己' : '下级 '.$viewUid.' ('.htmlspecialchars($owner['username'] ?? '').')'; ?></b>
            &nbsp;|&nbsp; 批次：<code><?php echo htmlspecialchars($batchId); ?></code>
            &nbsp;|&nbsp; 共 <?php echo count($rows); ?> 条
          </h3>
        </div>
        <table class="table table-striped table-hover">
          <thead><tr>
            <th>时间</th><th>对方</th><th>对方账号</th><th>金额</th><th>状态</th><th>匹配</th>
          </tr></thead>
          <tbody>
          <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
              <td><?php echo $r['trade_time']; ?></td>
              <td><?php echo htmlspecialchars($r['counterparty']); ?></td>
              <td><?php echo htmlspecialchars($r['counterparty_account']); ?></td>
              <td><b style="color:#27ae60;">¥<?php echo number_format($r['amount'], 2); ?></b></td>
              <td><?php echo htmlspecialchars($r['trade_status']); ?></td>
              <td>
                <?php if($r['match_status']==1): ?>
                  <span style="color:#27ae60;">✅ <?php echo htmlspecialchars($r['matched_trade_no']); ?></span>
                <?php else: ?>
                  <span style="color:#f39c12;">未匹配</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="6" style="text-align:center;padding:30px;color:#999;">无数据</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
      </div>
      <a href="./bill.php" class="btn btn-default">← 返回下级账单</a>
      <a href="./bill_upload.php" class="btn btn-default">我的对账单</a>
    </div>
  </div>
</div>
</body>
</html>