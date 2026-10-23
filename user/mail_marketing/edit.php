<?php
require_once __DIR__ . '/_boot.php';

$id = (int)($_GET['id'] ?? 0);
$c = mm_campaign($uid, $id);
if (!$c) {
    include __DIR__ . '/../head.php';
    echo '<div class="wrapper-md"><div class="alert alert-danger">活动不存在或不属于你</div><a href="index.php" class="btn btn-default">返回</a></div>';
    include __DIR__ . '/../foot.php';
    exit;
}
$hasSent = (int)$c['sent_count'] > 0;
$smtpUser = mm_smtp_user($uid);
include __DIR__ . '/../head.php';
?>
<div id="content" class="app-content" role="main">
<div class="app-content-body">

<div class="bg-light lter b-b wrapper-md">
  <h1 class="m-n font-thin h3"><i class="fa fa-pencil" style="color:#0d47a1;"></i> 编辑活动 #<?= $id ?></h1>
</div>

<div class="wrapper-md">

<?php if ($hasSent): ?>
  <div class="alert alert-warning">
    ⚠️ 此活动已开始发送（已发 <?= (int)$c['sent_count'] ?> 封），<b>不能修改内容和主题</b>。<br>
    如需新内容请 <a href="new.php">新建活动</a>。
  </div>
<?php endif; ?>

<?php if (!$smtpUser): ?>
  <div class="alert alert-warning">
    未配置发件邮箱：<a href="/user/mail_push.php">去配置</a>
  </div>
<?php endif; ?>

<div class="panel panel-default">
  <div class="panel-heading font-bold">活动信息</div>
  <div class="panel-body">

    <div class="form-group">
      <label>活动名 <span style="color:#e63946;">*</span></label>
      <input type="text" id="f_name" class="form-control" value="<?= htmlspecialchars($c['name']) ?>" <?= $hasSent ? 'disabled' : '' ?>>
    </div>

    <div class="form-group">
      <label>邮件主题 <span style="color:#e63946;">*</span></label>
      <input type="text" id="f_subject" class="form-control" value="<?= htmlspecialchars($c['subject']) ?>" <?= $hasSent ? 'disabled' : '' ?>>
    </div>

    <div class="form-group">
      <label>发件人显示名（选填）</label>
      <input type="text" id="f_from_name" class="form-control" value="<?= htmlspecialchars($c['from_name'] ?? '') ?>" <?= $hasSent ? 'disabled' : '' ?>>
    </div>

    <div class="form-group">
      <label>邮件正文（HTML） <span style="color:#e63946;">*</span></label>
      <?php if (!$hasSent): ?>
        <div style="margin-bottom:8px;">
          <select id="tplImport" class="form-control" style="display:inline-block;width:280px;" onchange="importTemplate(this.value)">
            <option value="">— 从模板导入 —</option>
            <?php foreach ($__tpls as $t): ?>
              <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="file" id="htmlFileEdit" accept=".html,.htm" style="display:inline-block;margin-left:6px;">
          <button type="button" class="btn btn-sm btn-default" onclick="importHtmlFile('htmlFileEdit','f_body')">
            <i class="fa fa-upload"></i> 导入 HTML
          </button>
          <a href="templates.php" class="btn btn-sm btn-link">管理模板</a>
        </div>
      <?php endif; ?>
      <textarea id="f_body" class="form-control" rows="16" <?= $hasSent ? 'disabled' : '' ?>><?= htmlspecialchars($c['body_html'] ?? '') ?></textarea>
    </div>

  </div>
</div>

<div class="panel panel-default">
  <div class="panel-heading font-bold">追加收件人（原有收件人不受影响）</div>
  <div class="panel-body">
    <textarea id="f_add_recipients" class="form-control" rows="6" placeholder="每行一个邮箱，或逗号/空格分隔"></textarea>
    <div style="margin-top:6px;color:#86909c;font-size:12px;">
      已输入 <b id="addCount">0</b> 个（重复的会自动跳过）<div id="addWarn" style="display:none;margin-top:6px;color:#e74c3c;font-size:12px;"></div>
    </div>
  </div>
</div>

<div class="panel panel-default">
  <div class="panel-body">
    <button class="btn btn-primary" id="saveBtn" onclick="saveEdit()"><i class="fa fa-save"></i> 保存</button>
    <a href="view.php?id=<?= $id ?>" class="btn btn-default">返回详情</a>
    <?php if (!$hasSent): ?>
      <span style="color:#86909c;margin-left:10px;font-size:12px;">保存后内容立即生效，可在详情页开始发送</span>
    <?php endif; ?>
  </div>
</div>

</div>
</div>
</div>

<script>
var campId = <?= $id ?>;
var hasSent = <?= $hasSent ? 'true' : 'false' ?>;

document.getElementById('f_add_recipients').addEventListener('input', function(){
  var v = this.value;
  var all = v.split(/[\s,;，；]+/).map(s=>s.trim()).filter(s=>s);
  var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  var valid = all.filter(s=>re.test(s));
  var invalid = all.filter(s=>!re.test(s));
  document.getElementById('addCount').textContent = new Set(valid).size;
  var warn = document.getElementById('addWarn');
  if (invalid.length) {
    warn.style.display = 'block';
    warn.innerHTML = '⚠️ 以下格式不正确：<br><b style="color:#e74c3c;">' + invalid.join('、') + '</b>';
  } else {
    warn.style.display = 'none';
  }
});

function saveEdit() {
  if (hasSent) { alert('已发送的活动不能修改'); return; }

  var name = document.getElementById('f_name').value.trim();
  var subject = document.getElementById('f_subject').value.trim();
  var body = document.getElementById('f_body').value.trim();
  var fromName = document.getElementById('f_from_name').value.trim();
  var addRecipients = document.getElementById('f_add_recipients').value;

  if (!name || !subject || !body) { alert('活动名/主题/正文不能为空'); return; }

  var btn = document.getElementById('saveBtn');
  btn.disabled = true;
  btn.textContent = '保存中...';

  var fd = new FormData();
  fd.append('act', 'update');
  fd.append('id', campId);
  fd.append('name', name);
  fd.append('subject', subject);
  fd.append('body_html', body);
  fd.append('from_name', fromName);
  fd.append('add_recipients', addRecipients);

  fetch('api.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
    alert(d.msg);
    if (d.code === 0) location.href = 'view.php?id=' + campId;
    else { btn.disabled = false; btn.innerHTML = '<i class="fa fa-save"></i> 保存'; }
  });
}
</script>

<?php include __DIR__ . '/../foot.php'; ?>