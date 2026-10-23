<?php
$is_defend = true;
include("../includes/common.php");
if($islogin2 != 1) exit("<script>location.href='./login.php';</script>");

$uid = (int)$userrow['uid'];
$title = '资金互转';

// 处理 AJAX 请求
if(isset($_POST['act'])){
    header('Content-Type: application/json; charset=utf-8');
    
    // 发起转账
    if($_POST['act'] == 'submit'){
        $to_uid = (int)$_POST['to_uid'];
        $money = round((float)$_POST['money'], 2);
        $remark = addslashes(trim($_POST['remark'] ?? ''));
        
        if($to_uid <= 0 || $money <= 0) exit(json_encode(['code'=>-1, 'msg'=>'参数错误']));
        if($to_uid == $uid) exit(json_encode(['code'=>-1, 'msg'=>'不能给自己转账']));
        
        $toUser = $DB->getRow("SELECT uid, username FROM pay_user WHERE uid=:u", [':u'=>$to_uid]);
        if(!$toUser) exit(json_encode(['code'=>-1, 'msg'=>'对方商户号不存在']));
        
        $myMoney = (float)$DB->getColumn("SELECT money FROM pay_user WHERE uid=:u", [':u'=>$uid]);
        if($myMoney < $money) exit(json_encode(['code'=>-1, 'msg'=>'您的余额不足']));
        
        changeUserMoney($uid, $money, false, "发起互转给[UID:{$to_uid}]", null);
        $DB->insert('money_transfer', [
            'from_uid'=>$uid, 'to_uid'=>$to_uid, 'money'=>$money, 'status'=>0, 'remark'=>$remark, 'addtime'=>'NOW()'
        ]);
        exit(json_encode(['code'=>0, 'msg'=>'发起成功，等待对方审核']));
    }
    
    // 审核
    if($_POST['act'] == 'review'){
        $id = (int)$_POST['id'];
        $action = (int)$_POST['action'];
        $row = $DB->getRow("SELECT * FROM pay_money_transfer WHERE id=:i AND to_uid=:u AND status=0", [':i'=>$id, ':u'=>$uid]);
        if(!$row) exit(json_encode(['code'=>-1, 'msg'=>'记录不存在或已处理']));
        
        if($action == 1){
            changeUserMoney($uid, $row['money'], true, "收到互转[来自UID:{$row['from_uid']}]", null);
            $DB->update('money_transfer', ['status'=>1, 'updatetime'=>'NOW()'], ['id'=>$id]);
            exit(json_encode(['code'=>0, 'msg'=>'已同意并到账']));
        } else {
            changeUserMoney($row['from_uid'], $row['money'], true, "互转被拒退回[接收方UID:{$uid}]", null);
            $DB->update('money_transfer', ['status'=>2, 'updatetime'=>'NOW()'], ['id'=>$id]);
            exit(json_encode(['code'=>0, 'msg'=>'已拒绝并退回']));
        }
    }
}

$received_list = $DB->getAll("SELECT * FROM pay_money_transfer WHERE to_uid=:u ORDER BY id DESC LIMIT 20", [':u'=>$uid]);
$sent_list = $DB->getAll("SELECT * FROM pay_money_transfer WHERE from_uid=:u ORDER BY id DESC LIMIT 20", [':u'=>$uid]);

include("./head.php");
?>
<div class="wrapper-md">
  <div class="row">
    <div class="col-md-4">
      <div class="panel panel-default">
        <div class="panel-heading"><i class="fa fa-exchange"></i> 发起转账</div>
        <div class="panel-body form-horizontal">
          <div class="form-group">
            <label class="col-sm-4 control-label">对方商户号</label>
            <div class="col-sm-8">
              <input type="number" class="form-control" id="to_uid" placeholder="请输入对方商户UID">
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-4 control-label">转账金额</label>
            <div class="col-sm-8">
              <input type="number" class="form-control" id="money" placeholder="0.00" step="0.01">
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-4 control-label">备注说明</label>
            <div class="col-sm-8">
              <input type="text" class="form-control" id="remark" placeholder="选填">
            </div>
          </div>
          <div class="form-group">
            <div class="col-sm-8 col-sm-offset-4">
              <button class="btn btn-primary btn-block" onclick="doTransfer()">确认发起转账</button>
            </div>
          </div>
          <div class="form-group">
            <div class="col-sm-12">
              <p class="text-muted" style="font-size:12px; margin-bottom:0;">注意：资金将先从您的余额中扣除，对方审核通过后存入对方余额。若对方拒绝，资金将原路退回。</p>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <div class="col-md-8">
      <div class="panel panel-default">
        <div class="panel-heading">收到的转账申请</div>
        <table class="table table-striped m-b-none">
          <thead><tr><th>发起方</th><th>金额</th><th>备注</th><th>状态</th><th>操作</th></tr></thead>
          <tbody>
            <?php foreach($received_list as $r): ?>
            <tr>
              <td>UID:<?php echo $r['from_uid'];?></td>
              <td class="text-danger font-bold">¥<?php echo $r['money'];?></td>
              <td><?php echo htmlspecialchars($r['remark']);?></td>
              <td>
                <?php if($r['status']==0) echo '<span class="label label-warning">待审核</span>';
                      elseif($r['status']==1) echo '<span class="label label-success">已到账</span>';
                      else echo '<span class="label label-default">已拒绝</span>'; ?>
              </td>
              <td>
                <?php if($r['status']==0): ?>
                <button class="btn btn-xs btn-success" onclick="doReview(<?php echo $r['id'];?>, 1)">同意</button>
                <button class="btn btn-xs btn-danger" onclick="doReview(<?php echo $r['id'];?>, 2)">拒绝</button>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      
      <div class="panel panel-default">
        <div class="panel-heading">我发起的转账</div>
        <table class="table table-striped m-b-none">
          <thead><tr><th>接收方</th><th>金额</th><th>备注</th><th>状态</th><th>时间</th></tr></thead>
          <tbody>
            <?php foreach($sent_list as $s): ?>
            <tr>
              <td>UID:<?php echo $s['to_uid'];?></td>
              <td class="text-danger font-bold">¥<?php echo $s['money'];?></td>
              <td><?php echo htmlspecialchars($s['remark']);?></td>
              <td>
                <?php if($s['status']==0) echo '<span class="label label-warning">等待对方审核</span>';
                      elseif($s['status']==1) echo '<span class="label label-success">已到账</span>';
                      else echo '<span class="label label-default">被拒绝已退回</span>'; ?>
              </td>
              <td><?php echo $s['addtime'];?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
function doTransfer() {
    var to_uid = $('#to_uid').val();
    var money = $('#money').val();
    var remark = $('#remark').val();
    if(!to_uid || !money || money <= 0) return alert('请填写正确的商户号和金额');
    if(!confirm('确定要向 UID:'+to_uid+' 转账 ¥'+money+' 吗？\n资金将先从您账户扣除。')) return;
    
    $.post('money_transfer.php', {act:'submit', to_uid:to_uid, money:money, remark:remark}, function(res){
        if(res.code == 0) {
            alert(res.msg);
            location.reload();
        } else {
            alert(res.msg);
        }
    }, 'json');
}
function doReview(id, action) {
    if(!confirm(action==1 ? '确定同意这笔转账并入账吗？' : '确定拒绝这笔转账并退回对方吗？')) return;
    $.post('money_transfer.php', {act:'review', id:id, action:action}, function(res){
        alert(res.msg);
        if(res.code == 0) location.reload();
    }, 'json');
}
</script>
<?php include("./foot.php");?>