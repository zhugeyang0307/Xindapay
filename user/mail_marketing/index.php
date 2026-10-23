<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../head.php';

$smtpUser = mm_smtp_user($uid);
$list = DB::query("SELECT * FROM pay_mail_campaign WHERE uid=? ORDER BY id DESC LIMIT 200", [$uid]);
?>
<div id="content" class="app-content" role="main">
<div class="app-content-body">

<div class="bg-light lter b-b wrapper-md">
  <h1 class="m-n font-thin h3"><i class="fa fa-envelope-o" style="color:#0d47a1;"></i> 邮件营销</h1>
</div>

<div class="wrapper-md">

<?php if (!$smtpUser): ?>
  <div class="alert alert-warning">
    <b>⚠️ 未配置发件邮箱</b><br>
    发邮件前请先到 <a href="/user/mail_push.php" class="btn btn-xs btn-primary">邮件推送</a> 页面配置 SMTP 发件邮箱（用你自己的邮箱）。
  </div>
<?php else: ?>
  <div class="alert alert-info" style="font-size:13px;">
    <b>发件邮箱：</b><?= htmlspecialchars($smtpUser) ?>
    &nbsp;|&nbsp; 每批最多发送 20 封，每秒最多 2 封（防止被判定为垃圾邮件）
  </div>
<?php endif; ?>

<div class="panel panel-default">
  <div class="panel-heading font-bold">
    <a href="new.php" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> 新建活动</a>
    <span style="margin-left:12px;color:#888;">共 <?= count($list) ?> 个活动</span>
  </div>
  <div class="panel-body">
    <?php if (empty($list)): ?>
      <div style="text-align:center;padding:40px;color:#aaa;">还没有活动，点「新建活动」开始</div>
    <?php else: ?>
      <table class="table table-bordered table-hover">
        <thead><tr>
          <th style="width:60px;">ID</th>
          <th>活动名</th>
          <th>主题</th>
          <th style="width:90px;">收件人</th>
          <th style="width:80px;">已发</th>
          <th style="width:80px;">失败</th>
          <th style="width:80px;">打开率</th>
          <th style="width:80px;">点击率</th>
          <th style="width:90px;">状态</th>
          <th style="width:180px;">操作</th>
        </tr></thead>
        <tbody>
        <?php foreach ($list as $c):
          $stats = MailTrack::stats($c['id'], $uid);
          $st = (int)$c['status'];
          $stText = [0=>'<span class="label label-default">草稿</span>',
                     1=>'<span class="label label-info">发送中</span>',
                     2=>'<span class="label label-success">已完成</span>'][$st] ?? '<span class="label label-default">-</span>';
        ?>
          <tr>
            <td><?= $c['id'] ?></td>
            <td><a href="view.php?id=<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></a></td>
            <td><?= htmlspecialchars($c['subject']) ?></td>
            <td><?= (int)$c['total_count'] ?></td>
            <td><?= (int)$c['sent_count'] ?></td>
            <td><?= (int)$c['failed_count'] ?></td>
            <td><?= $stats['open_rate'] ?>%</td>
            <td><?= $stats['click_rate'] ?>%</td>
            <td><?= $stText ?></td>
            <td>
              <a href="view.php?id=<?= $c['id'] ?>" class="btn btn-xs btn-info">详情</a>
              <button class="btn btn-xs btn-danger" onclick="mmDel(<?= $c['id'] ?>, '<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>')">删除</button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

</div>
</div>
</div>

<script>
function mmDel(id, name) {
  if (!confirm('确认删除活动「' + name + '」及其所有收件人/统计数据？')) return;
  var fd = new FormData();
  fd.append('act', 'delete');
  fd.append('id', id);
  fetch('api.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
    alert(d.msg);
    if (d.code === 0) location.reload();
  });
}
</script>

<?php include __DIR__ . '/../foot.php'; ?>