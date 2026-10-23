<?php
$is_defend = true;
include("../includes/common.php");
if($islogin2 != 1) exit("<script>location.href='./login.php';</script>");

$uid = (int)$userrow['uid'];
$myLevel = (int)$userrow['sub_level'];

// 拿所有子孙 uid
function getSubTree($DB, $rootUid, $maxDepth = 3) {
    $tree = [];
    $queue = [['uid'=>$rootUid, 'depth'=>1]];
    while ($queue) {
        $cur = array_shift($queue);
        if ($cur['depth'] >= $maxDepth) continue;
        $children = $DB->getAll("SELECT uid FROM pay_user WHERE upid=:u", [':u'=>$cur['uid']]);
        foreach ($children as $c) {
            $tree[] = $c['uid'];
            $queue[] = ['uid'=>$c['uid'], 'depth'=>$cur['depth']+1];
        }
    }
    return $tree;
}

$subUids = getSubTree($DB, $uid);
$subUids[] = $uid; // 允许查看自己（如果自己也是下级）

// ───── 提交审批 ─────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');
    
    $act = $_POST['act'] ?? '';
    $targetUid = (int)($_POST['uid'] ?? 0);
    
    if ($targetUid <= 0) exit(json_encode(['code'=>-1, 'msg'=>'参数错误']));
    
    // 权限：目标必须在我的子孙（或我自己）
    // 但自己不能审批自己（除非是想看看，但不能改状态）
    if ($targetUid !== $uid && !in_array($targetUid, $subUids)) {
        exit(json_encode(['code'=>-1, 'msg'=>'无权操作']));
    }
    
    if ($act === 'approve') {
        $target = $DB->getRow("SELECT sub_push_tg, sub_push_email, sub_push_smtp_host, sub_push_smtp_user, sub_push_smtp_pwd, sub_push_smtp_secure FROM pay_user WHERE uid=:u", [':u'=>$targetUid]);
        if (!$target) exit(json_encode(['code'=>-1, 'msg'=>'商户不存在']));
        
        // 通过 → status=1
        $DB->exec("UPDATE pay_user SET sub_push_status=1 WHERE uid=:u", [':u'=>$targetUid]);
        
        // 同步到 pay_user_botconfig（走现有推送系统）
        $tgEnabled = $target['sub_push_tg'] ? 1 : 0;
        $mailEnabled = $target['sub_push_email'] ? 1 : 0;
        
        // 检查 botconfig 有没有记录
        $hasBot = $DB->getColumn("SELECT COUNT(*) FROM pay_user_botconfig WHERE uid=:u", [':u'=>$targetUid]);
        if ($hasBot) {
            $DB->exec("UPDATE pay_user_botconfig SET 
                          notice_paid=:np,
                          mail_enabled=:me, mail_to=:mt,
                          mail_smtp_host=:mh, mail_smtp_user=:mu, mail_smtp_pwd=:mp, mail_smtp_secure=:ms,
                          use_own_bot=0, own_bot_chatid=:obc
                        WHERE uid=:u",
                [':np'=>$tgEnabled, ':me'=>$mailEnabled, ':mt'=>$target['sub_push_email'], 
                 ':mh'=>$target['sub_push_smtp_host'], ':mu'=>$target['sub_push_smtp_user'], 
                 ':mp'=>$target['sub_push_smtp_pwd'], ':ms'=>$target['sub_push_smtp_secure'] ?: 'ssl',
                 ':obc'=>$target['sub_push_tg'], ':u'=>$targetUid]);
        } else {
            $DB->exec("INSERT INTO pay_user_botconfig 
                          (uid, notice_paid, mail_enabled, mail_to, mail_smtp_host, mail_smtp_user, mail_smtp_pwd, mail_smtp_secure, use_own_bot, own_bot_chatid, addtime)
                        VALUES 
                          (:u, :np, :me, :mt, :mh, :mu, :mp, :ms, 0, :obc, NOW())",
                [':u'=>$targetUid, ':np'=>$tgEnabled, ':me'=>$mailEnabled, ':mt'=>$target['sub_push_email'],
                 ':mh'=>$target['sub_push_smtp_host'], ':mu'=>$target['sub_push_smtp_user'],
                 ':mp'=>$target['sub_push_smtp_pwd'], ':ms'=>$target['sub_push_smtp_secure'] ?: 'ssl',
                 ':obc'=>$target['sub_push_tg']]);
        }
        
        exit(json_encode(['code'=>1, 'msg'=>'已通过，推送配置已生效']));
    }
    
    if ($act === 'reject') {
        $DB->exec("UPDATE pay_user SET sub_push_status=2 WHERE uid=:u", [':u'=>$targetUid]);
        exit(json_encode(['code'=>1, 'msg'=>'已拒绝']));
    }
    
    if ($act === 'reset') {
        $DB->exec("UPDATE pay_user SET sub_push_status=0 WHERE uid=:u", [':u'=>$targetUid]);
        exit(json_encode(['code'=>1, 'msg'=>'已重置为待审批']));
    }
    
    exit(json_encode(['code'=>-1, 'msg'=>'未知操作']));
}

// ───── 列表 ─────
if ($subUids) {
    $inList = implode(',', array_map('intval', $subUids));
    $list = $DB->getAll("SELECT uid, username, email, sub_level, sub_push_tg, sub_push_email, sub_push_smtp_host, sub_push_smtp_user, sub_push_smtp_secure, sub_push_status 
                         FROM pay_user WHERE uid IN ({$inList}) ORDER BY sub_push_status ASC, uid ASC");
} else {
    $list = [];
}

$title = '下级推送审批';
include './head.php';
?>
<style>
.rv-card{background:#fff;border-radius:14px;padding:24px;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:20px;}
table.rv{width:100%;border-collapse:collapse;font-size:13px;}
table.rv th,table.rv td{padding:10px;border-bottom:1px solid #eef2f7;text-align:left;vertical-align:top;}
table.rv th{background:#f7fafd;color:#555;font-weight:600;}
.badge{display:inline-block;padding:3px 10px;border-radius:10px;font-size:11px;font-weight:700;}
.bd-0{background:#fff7e6;color:#d46b08;}
.bd-1{background:#d9f7be;color:#389e0d;}
.bd-2{background:#fff1f0;color:#cf1322;}
.btn-xs{padding:4px 12px;font-size:12px;border-radius:6px;border:none;cursor:pointer;text-decoration:none;display:inline-block;margin-right:4px;}
.b-green{background:#07c160;color:#fff;}
.b-red{background:#e74c3c;color:#fff;}
.b-gray{background:#e5e7eb;color:#333;}
.tag-lv{display:inline-block;padding:1px 8px;border-radius:8px;font-size:11px;font-weight:700;background:#dbeafe;color:#1e40af;}
</style>

<div id="content" class="app-content" role="main">
<div class="app-content-body">
<div class="bg-light lter b-b wrapper-md">
  <h1 class="m-n font-thin h3"><i class="fa fa-check-circle"></i> 下级推送审批</h1>
</div>
<div class="wrapper-md">

  <div class="rv-card">
    <h4 style="margin-top:0;color:#333;"><i class="fa fa-list"></i> 待审批 / 全部申请（共 <?php echo count((array)$list);?> 条）</h4>

    <?php if (!$list): ?>
      <div style="text-align:center;padding:40px;color:#888;">
        <div style="font-size:48px;margin-bottom:12px;opacity:.4;">📭</div>
        <div>暂无下级提交推送资料</div>
      </div>
    <?php else: ?>
      <div class="table-responsive">
      <table class="rv">
        <thead>
          <tr>
            <th>层级</th>
            <th>UID</th>
            <th>账号</th>
            <th>TG Chat ID</th>
            <th>接收邮箱</th>
            <th>SMTP 服务器</th>
            <th>状态</th>
            <th>操作</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($list as $r):
          $st = (int)$r['sub_push_status'];
          $stTxt = [0=>['bd-0','⏳ 待审批'], 1=>['bd-1','✅ 已通过'], 2=>['bd-2','❌ 已拒绝']][$st] ?? ['bd-0','?'];
        ?>
          <tr>
            <td><span class="tag-lv">L<?php echo $r['sub_level'];?></span></td>
            <td><b><?php echo $r['uid'];?></b><?php if ($r['uid'] === $uid) echo ' <small style="color:#1677ff;">(我)</small>';?></td>
            <td><?php echo htmlspecialchars($r['username'] ?: '—');?></td>
            <td><code><?php echo htmlspecialchars($r['sub_push_tg'] ?: '—');?></code></td>
            <td><code style="font-size:12px;"><?php echo htmlspecialchars($r['sub_push_email'] ?: '—');?></code></td>
            <td><code style="font-size:12px;"><?php echo htmlspecialchars($r['sub_push_smtp_host'] ?: '—');?></code></td>
            <td><span class="badge <?php echo $stTxt[0];?>"><?php echo $stTxt[1];?></span></td>
            <td>
              <?php if ($st === 0): ?>
                <button class="btn-xs" style="background:#10b981;font-weight:700;" onclick="doApproveTest(<?php echo $r['uid'];?>)">✅ 通过并测试</button>
                <button class="btn-xs b-green" onclick="doApprove(<?php echo $r['uid'];?>)">仅通过</button>
                <button class="btn-xs b-red" onclick="doReject(<?php echo $r['uid'];?>)">❌ 拒绝</button>
              <?php else: ?>
                <button class="btn-xs" style="background:#8b5cf6;" onclick="doTestPush(<?php echo $r['uid'];?>)">🧪 测推送</button>
                <button class="btn-xs b-gray" onclick="doReset(<?php echo $r['uid'];?>)">🔄 重置为待审</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="rv-card">
    <h4 style="margin-top:0;color:#333;">📖 说明</h4>
    <ul style="line-height:2;color:#555;padding-left:20px;margin:0;">
      <li><b>通过</b>：下级的 TG / 邮箱 / SMTP 配置写入推送系统，下级将收到订单通知</li>
      <li><b>拒绝</b>：下级需重新填写并提交</li>
      <li><b>重置</b>：已审批过的可以重置为待审批，方便重新操作</li>
      <li>你只能审批<b>自己链上</b>的下级（A 能审 B 和 C，B 只能审 C）</li>
    </ul>
  </div>

</div>
</div>
</div>

<script>
function post(data) {
  var fd = new FormData();
  for (var k in data) fd.append(k, data[k]);
  return fetch('./sub_review.php', {method:'POST', body:fd}).then(r=>r.json());
}
function doApprove(uid) {
  if (!confirm('确定通过吗？通过后下级将收到订单推送')) return;
  post({act:'approve', uid:uid}).then(d=>{ alert(d.msg); if(d.code===1) location.reload(); });
}
function doReject(uid) {
  if (!confirm('确定拒绝吗？')) return;
  post({act:'reject', uid:uid}).then(d=>{ alert(d.msg); if(d.code===1) location.reload(); });
}
function doApproveTest(uid) {
  if (!confirm('确定通过并立即发一封测试邮件给该下级？')) return;
  post({act:'approve', uid:uid, auto_test:1}).then(d=>{ alert(d.msg); if(d.code===1) location.reload(); });
}
function doTestPush(uid) {
  post({act:'test_push', uid:uid}).then(d=>alert(d.msg));
}

function doReset(uid) {
  if (!confirm('确定重置为待审批吗？')) return;
  post({act:'reset', uid:uid}).then(d=>{ alert(d.msg); if(d.code===1) location.reload(); });
}
</script>
<?php include './foot.php'; ?>
