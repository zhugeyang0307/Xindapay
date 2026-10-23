<?php
$is_defend = true;
include("../includes/common.php");
if($islogin2 != 1) exit("<script>location.href='./login.php';</script>");

$uid = (int)$userrow['uid'];
$myLevel = (int)$userrow['sub_level'];

// 递归拿所有子孙 uid（最多 3 级）
function getSubTree($DB, $rootUid, $maxDepth = 3) {
    $tree = [];
    $queue = [['uid'=>$rootUid, 'depth'=>1]];
    while ($queue) {
        $cur = array_shift($queue);
        if ($cur['depth'] >= $maxDepth) continue;
        $children = $DB->getAll("SELECT uid, upid, username, email, phone, money, rate, sub_level, sub_push_status, status, pay FROM pay_user WHERE upid=:u ORDER BY uid ASC", [':u'=>$cur['uid']]);
        foreach ($children as $c) {
            $c['depth'] = $cur['depth'];
            $tree[] = $c;
            $queue[] = ['uid'=>$c['uid'], 'depth'=>$cur['depth']+1];
        }
    }
    return $tree;
}

$subs = getSubTree($DB, $uid);

// 附加：每个下级的订单数、总收款、已分润
foreach ($subs as &$s) {
    $stat = $DB->getRow("SELECT COUNT(*) AS cnt, COALESCE(SUM(realmoney),0) AS total FROM pay_order WHERE uid=:u AND status=1", [':u'=>$s['uid']]);
    $s['order_cnt']  = (int)($stat['cnt'] ?? 0);
    $s['order_total']= (float)($stat['total'] ?? 0);
    $s['profit_total'] = (float)$DB->getColumn("SELECT COALESCE(SUM(profitmoney),0) FROM pay_order WHERE uid=:u AND status=1", [':u'=>$s['uid']]);
}
unset($s);

$title = '下级商户';
include './head.php';
?>
<style>
.app-sub-page {
  background: linear-gradient(180deg, #f6f9ff 0%, #f3f7fb 100%);
}
.app-sub-page .wrapper-md {
  padding-top: 14px !important;
  padding-bottom: 18px !important;
}
.app-sub-page .bg-light.lter.b-b.wrapper-md {
  background: transparent !important;
  border: 0 !important;
  padding-bottom: 8px !important;
}
.app-sub-page .bg-light.lter.b-b.wrapper-md h1 {
  font-size: 28px !important;
  font-weight: 800 !important;
  color: #0f172a !important;
  letter-spacing: 0.02em;
}
.sub-card {
  background: #fff;
  border-radius: 18px;
  padding: 18px 16px;
  box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
  border: 1px solid #edf3fb;
  margin-bottom: 16px;
}
.sub-top-summary {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  background: linear-gradient(135deg, #f0f6ff 0%, #eef8ff 100%);
  border: 1px solid #dfeeff;
  border-radius: 16px;
  padding: 16px 18px;
}
.sub-summary-item {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 160px;
}
.sub-summary-icon {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #edf5ff;
  color: #1677ff;
  font-weight: 700;
  font-size: 15px;
}
.sub-status{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;}
.st-pay-on{background:#d9f7be;color:#389e0d;}
.st-pay-off{background:#fff1f0;color:#cf1322;}
.st-push-0{background:#fff7e6;color:#d46b08;}
.st-push-1{background:#d9f7be;color:#389e0d;}
.st-push-2{background:#fff1f0;color:#cf1322;}
table.sub{width:100%;border-collapse:collapse;font-size:12px;}
table.sub th,table.sub td{padding:10px 8px;border-bottom:1px solid #eef2f7;text-align:left;vertical-align:middle;}
table.sub th{background:#f7fafd;color:#555;font-weight:700; font-size:11px; text-transform:uppercase; letter-spacing:0.04em;}
table.sub tbody tr:hover td{background:#f9fbff;}
.tag-lv{display:inline-block;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:700;}
.lv-2{background:#dbeafe;color:#1e40af;}
.lv-3{background:#fce7f3;color:#be185d;}
.btn-xs{padding:6px 10px;font-size:12px;border-radius:10px;border:none;cursor:pointer;text-decoration:none;display:inline-block;font-weight:700;}
.b-green{background:linear-gradient(135deg,#10b981,#0ea272);color:#fff;box-shadow:0 8px 18px rgba(16,185,129,.14);}
.b-red{background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;}
.b-blue{background:linear-gradient(135deg,#1677ff,#0d5cd6);color:#fff;box-shadow:0 8px 18px rgba(22,119,255,.14);}
.b-gray{background:#f3f4f6;color:#334155;}
.app-sub-page .sub-card .btn-xs {
  min-height: 34px;
  line-height: 1.2;
}
@media (max-width: 768px) {
  .app-sub-page .bg-light.lter.b-b.wrapper-md h1 { font-size: 26px !important; }
  .sub-top-summary { align-items: flex-start; }
  table.sub { display:block; overflow-x:auto; }
  .app-sub-page .sub-card { padding: 14px 12px; }
}
</style>

<div id="content" class="app-content app-sub-page" role="main">
<div class="app-content-body">
<div class="bg-light lter b-b wrapper-md">
  <h1 class="m-n font-thin h3"><i class="fa fa-sitemap"></i> 下级商户管理</h1>
</div>
<div class="wrapper-md">

  <div class="sub-card">
    <div class="sub-top-summary">
      <div class="sub-summary-item">
        <div class="sub-summary-icon">L</div>
        <div>
          <div style="font-size:12px;color:#64748b;">我的层级</div>
          <div style="font-size:18px;font-weight:800;color:#0f172a;">第 <?php echo $myLevel;?> 级</div>
        </div>
      </div>
      <div class="sub-summary-item">
        <div class="sub-summary-icon">T</div>
        <div>
          <div style="font-size:12px;color:#64748b;">下级数量</div>
          <div style="font-size:18px;font-weight:800;color:#0f172a;"><?php echo count($subs);?></div>
        </div>
      </div>
      <div>
        <?php if ($myLevel < 3): ?>
          <?php if ($myLevel == 1): ?>
            <a href="./sub_create.php" class="btn-xs b-green" style="padding:10px 22px;font-size:14px;">➕ 创建下级商户</a>
            <?php if (count($subs) > 0): ?>
              <a href="./sub_create.php?for=child" class="btn-xs b-blue" style="padding:10px 22px;font-size:14px;">➕ 帮下级创建下级</a>
            <?php endif; ?>
          <?php else: ?>
            <a href="javascript:;" onclick="alert('请联系您的上级帮您创建下级商户');" class="btn-xs b-gray" style="padding:10px 22px;font-size:14px;">➕ 创建下级（需上级协助）</a>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if (!$subs): ?>
    <div class="sub-card" style="text-align:center;padding:60px 20px;color:#888;">
      <div style="font-size:48px;margin-bottom:12px;opacity:.4;">📭</div>
      <div>暂无下级商户</div>
    </div>
  <?php else: ?>
    <div class="sub-card">
      <table class="sub">
        <thead>
          <tr>
            <th>层级</th>
            <th>UID</th>
            <th>账号</th>
            <th>收款权限</th>
            <th>分润比例</th>
            <th>当前余额</th>
            <th>订单数</th>
            <th>总收款</th>
            <th>已分润给我</th>
            <th>推送状态</th>
            <th>登录信息（点击直接复制）</th>
            <th>操作</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($subs as $s):
          $lv = (int)$s['sub_level'];
        ?>
          <tr>
            <td><span class="tag-lv lv-<?php echo $lv;?>">L<?php echo $lv;?></span></td>
            <td style="padding-left:<?php echo 10 + (int)$s['depth'] * 28;?>px;"><?php if ((int)$s['depth'] > 0) echo '<span style="color:#94a3b8;margin-right:4px;">└</span>';?><b><?php echo $s['uid'];?></b></td>
            <td>
              <?php echo htmlspecialchars($s['username'] ?: '—');?><br>
              <small style="color:#888;"><?php echo htmlspecialchars($s['email'] ?: ($s['phone'] ?: '—'));?></small>
            </td>
            <td>
              <?php if ((int)$s['pay'] === 1 && (int)$s['status'] === 1): ?>
                <span class="sub-status st-pay-on">✅ 已开启</span>
              <?php else: ?>
                <span class="sub-status st-pay-off">⛔ 已关闭</span>
              <?php endif; ?>
            </td>
            <td><b style="color:#e74c3c;"><?php echo (float)$s['rate'];?>%</b></td>
            <td><b style="color:#1677ff;">¥<?php echo number_format((float)$s['money'], 2);?></b></td>
            <td><?php echo (int)$s['order_cnt'];?></td>
            <td>¥<?php echo number_format((float)$s['order_total'], 2);?></td>
            <td><b style="color:#07c160;">¥<?php echo number_format((float)$s['profit_total'], 2);?></b></td>
            <td>
              <?php
              $ps = (int)$s['sub_push_status'];
              if ($ps === 0)      echo '<span class="sub-status st-push-0">⏳ 待审批</span>';
              elseif ($ps === 1)  echo '<span class="sub-status st-push-1">✅ 已通过</span>';
              else                echo '<span class="sub-status st-push-2">❌ 已拒绝</span>';
              ?>
            </td>
            <td>
              <div style="font-size:12px; line-height:1.8;">
                <div>🔗 链接：<a href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/user/login.php?pid=<?php echo $uid;?>" target="_blank" style="color:#1677ff;">点击去登录</a></div>
                <div>👤 账号：<span style="font-weight:bold; color:#e11d48;"><?php echo htmlspecialchars($s['username']);?></span></div>
                <div>🔑 密码：<span style="font-weight:bold; color:#e11d48;">123456</span> <span style="color:#94a3b8;">(默认)</span></div>
                <button type="button" onclick="fastCopy(this, <?php echo $s['uid'];?>, '<?php echo htmlspecialchars($s['username']);?>')" style="margin-top:5px; padding:4px 10px; background:#10b981; color:#fff; border:none; border-radius:4px; font-size:12px; cursor:pointer;">📋 一键复制全部</button>
              </div>
            </td>
            <td style="white-space:nowrap;">
              <a href="./sub_switch.php?uid=<?php echo $s['uid'];?>" class="btn-xs b-green" onclick="return confirm('切换到下级 [<?php echo htmlspecialchars($s['username']);?>] 的后台？\n\n切换后顶部会显示返回按钮，可随时切回。')">🔓 进入后台</a>
              <a href="./sub_edit.php?uid=<?php echo $s['uid'];?>" class="btn-xs b-blue">编辑</a>
              <a href="./sub_review.php?uid=<?php echo $s['uid'];?>" class="btn-xs b-gray">审批</a>
              
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <div class="sub-card">
    <h4 style="margin-top:0;color:#333;">📖 说明</h4>
    <ul style="line-height:2;color:#555;padding-left:20px;margin:0;">
      <li>系统最多 <b>3 级</b>：A（一级）→ B（二级）→ C（三级）</li>
      <li>每个商户 <b>直接下级数量不限</b>，但层级最多到 3</li>
      <li><b>分润比例</b>：下级每笔收款，按比例分给你（从下级余额扣，加到你的余额）</li>
      <li><b>收款权限</b>：关闭后下级无法收款（但账号还能登录）</li>
      <li><b>推送资料</b>：下级填写后需要你审批，审批通过才生效</li>
    </ul>
  </div>

</div>
</div>
</div>







<script>
function fastCopy(btn, uid, username) {
    var originalText = btn.innerHTML;
    btn.innerHTML = '⏳ 复制中...';
    btn.disabled = true;

    var formData = new FormData();
    formData.append('uid', uid);
    formData.append('pwd', '123456'); // 强行重置为默认密码

    fetch('ajax2.php?act=reset_sub_pwd', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if (res.code === 1) {
            var parentPid = "<?php echo (int)$userrow['uid']; ?>";
            var loginUrl = "https://" + window.location.host + "/user/login.php?pid=" + parentPid;
            var copyText = "【信达支付系统 - 下级商户登录信息】\n专属登录链接：" + loginUrl + "\n登录账号：" + username + "\n登录密码：123456\n（注：请妥善保管，建议首次登录后修改密码）";

            // 原生无弹窗复制
            var textarea = document.createElement("textarea");
            textarea.value = copyText;
            textarea.style.position = "fixed";
            textarea.style.opacity = "0";
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                btn.innerHTML = '✅ 复制成功';
            } catch (err) {
                btn.innerHTML = '❌ 复制失败';
                alert('复制失败，请手动复制文本：\n\n' + copyText);
            }
            document.body.removeChild(textarea);
        } else {
            btn.innerHTML = '❌ 重置失败';
            alert(res.msg || '系统重置密码失败');
        }
        setTimeout(function(){ btn.innerHTML = originalText; btn.disabled = false; }, 2000);
    })
    .catch(err => {
        btn.innerHTML = '❌ 网络异常';
        setTimeout(function(){ btn.innerHTML = originalText; btn.disabled = false; }, 2000);
    });
}
</script>

<?php include './foot.php'; ?>