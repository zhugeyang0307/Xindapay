<?php
require_once __DIR__ . '/_boot.php';
include __DIR__ . '/../head.php';

$smtpUser = mm_smtp_user($uid);
$preTplId = (int)($_GET['tpl'] ?? 0);
$preTpl = $preTplId ? DB::one("SELECT * FROM pay_mail_template WHERE id=? AND uid=?", [$preTplId, $uid]) : null;
$__tpls = DB::query("SELECT id, name FROM pay_mail_template WHERE uid=? ORDER BY id DESC", [$uid]);
?>
<div id="content" class="app-content" role="main">
<div class="app-content-body">

<div class="bg-light lter b-b wrapper-md">
  <h1 class="m-n font-thin h3"><i class="fa fa-plus-circle" style="color:#0d47a1;"></i> 新建邮件活动</h1>
</div>

<div class="wrapper-md">

<?php if (!$smtpUser): ?>
  <div class="alert alert-warning">
    发邮件前请先到 <a href="/user/mail_push.php" class="btn btn-xs btn-primary">邮件推送</a> 配置发件邮箱 SMTP。
  </div>
<?php else: ?>
  <div class="alert alert-info" style="font-size:13px;">
    <b>发件人：</b><?= htmlspecialchars($smtpUser) ?>（可在下方自定义显示名）
  </div>
<?php endif; ?>

<div class="panel panel-default">
  <div class="panel-heading font-bold">活动信息</div>
  <div class="panel-body">

    <div class="form-group">
      <label>活动名 <span style="color:#e63946;">*</span>（仅自己可见，方便管理）</label>
      <input type="text" id="f_name" class="form-control" placeholder="如：双十一促销、新店开业通知" value="<?= $preTpl ? htmlspecialchars($preTpl['name']) : '' ?>">
    </div>

    <div class="form-group">
      <label>邮件主题 <span style="color:#e63946;">*</span>（收件人看到的标题）</label>
      <input type="text" id="f_subject" class="form-control" placeholder="如：您的老客户专属优惠已到账" value="<?= $preTpl ? htmlspecialchars($preTpl['subject'] ?? '') : '' ?>">
    </div>

    <div class="form-group">
      <label>发件人显示名（选填）</label>
      <input type="text" id="f_from_name" class="form-control" placeholder="如：XX 商城">
    </div>

    <div class="form-group">
      <label>邮件正文（HTML，支持 &lt;a&gt;、&lt;img&gt;、&lt;p&gt; 等标签） <span style="color:#e63946;">*</span></label>
      <?php if (!empty($__tpls)): ?>
        <div style="margin-bottom:8px;">
          <select id="tplImport" class="form-control" style="display:inline-block;width:280px;" onchange="importTemplate(this.value)">
            <option value="">— 从模板导入 —</option>
            <?php foreach ($__tpls as $t): ?>
              <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="file" id="htmlFileNew" accept=".html,.htm" style="display:inline-block;margin-left:6px;">
          <button type="button" class="btn btn-sm btn-default" onclick="importHtmlFile('htmlFileNew','f_body')">
            <i class="fa fa-upload"></i> 导入 HTML
          </button>
          <a href="templates.php" class="btn btn-sm btn-link">管理模板</a>
        </div>
      <?php else: ?>
        <div style="margin-bottom:8px;">
          <a href="templates.php" class="btn btn-sm btn-default"><i class="fa fa-file-code-o"></i> 去创建模板</a>
          <input type="file" id="htmlFileNew" accept=".html,.htm" style="display:inline-block;margin-left:6px;">
          <button type="button" class="btn btn-sm btn-default" onclick="importHtmlFile('htmlFileNew','f_body')">
            <i class="fa fa-upload"></i> 导入 HTML
          </button>
        </div>
      <?php endif; ?>
      <textarea id="f_body" class="form-control" rows="14"><?= $preTpl ? htmlspecialchars($preTpl['body_html']) : '' ?></textarea>
      <div style="margin-top:6px;color:#86909c;font-size:12px;">
        💡 链接会自动追踪点击，邮件打开也会被追踪（用于统计打开率）。
      </div>
    </div>

  </div>
</div>

<div class="panel panel-default">
  <div class="panel-heading font-bold">收件人</div>
  <div class="panel-body">

    <div class="form-group">
      <label>方式 1：粘贴 / 手动输入（每行一个，或逗号/空格分隔）</label>
      <textarea id="f_recipients" class="form-control" rows="6" placeholder="customer1@example.com&#10;customer2@example.com&#10;customer3@example.com"></textarea>
      <div style="margin-top:6px;color:#86909c;font-size:12px;">
        已输入 <b id="pastedCount">0</b> 个有效邮箱<div id="pastedWarn" style="display:none;margin-top:6px;color:#e74c3c;font-size:12px;"></div>
      </div>
    </div>

    <div class="form-group">
      <label>方式 2：从已支付的订单买家邮箱里选</label>
      <button type="button" class="btn btn-sm btn-default" onclick="loadOrderEmails()">
        <i class="fa fa-download"></i> 加载历史买家邮箱
      </button>
      <div id="pickedArea" style="margin-top:10px;max-height:220px;overflow:auto;border:1px solid #eee;border-radius:4px;padding:8px;display:none;">
        <div style="margin-bottom:6px;">
          <label><input type="checkbox" id="pickAll" onclick="toggleAll(this)"> 全选 / 全不选</label>
          <span style="color:#86909c;margin-left:8px;font-size:12px;">已勾选 <b id="pickedCount">0</b> 个</span>
        </div>
        <div id="pickedList"></div>
      </div>
    </div>

  </div>
</div>

<div class="panel panel-default">
  <div class="panel-body">
    <button class="btn btn-primary" onclick="submitNew()"><i class="fa fa-save"></i> 保存活动</button>
    <a href="index.php" class="btn btn-default">返回列表</a>
    <span style="color:#86909c;margin-left:10px;font-size:12px;">保存后自动跳转到详情页，可在那里点「开始发送」</span>
  </div>
</div>

</div>
</div>
</div>

<script>
function importTemplate(tid) {
  if (!tid) return;
  fetch('api.php?act=template_get&id=' + tid).then(r=>r.json()).then(d=>{
    if (d.code !== 0) { alert(d.msg); return; }
    if (!confirm('导入模板「' + d.data.name + '」会覆盖当前正文，继续？')) return;
    document.getElementById('f_body').value = d.data.body_html || '';
    if (d.data.subject && !document.getElementById('f_subject').value) {
      document.getElementById('f_subject').value = d.data.subject;
    }
  });
}

function importHtmlFile(inputId, targetId) {
  var f = document.getElementById(inputId).files[0];
  if (!f) { alert('请先选择 .html 文件'); return; }
  var reader = new FileReader();
  reader.onload = function(e) { document.getElementById(targetId).value = e.target.result; };
  reader.readAsText(f, 'UTF-8');
}

function countPasted() {
  var v = document.getElementById('f_recipients').value;
  var all = v.split(/[\s,;，；]+/).map(s=>s.trim()).filter(s=>s);
  var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  var valid = all.filter(s=>re.test(s));
  var invalid = all.filter(s=>!re.test(s));
  document.getElementById('pastedCount').textContent = new Set(valid).size;
  var warn = document.getElementById('pastedWarn');
  if (invalid.length) {
    warn.style.display = 'block';
    warn.innerHTML = '⚠️ 以下格式不正确：<br><b style="color:#e74c3c;">' + invalid.join('、') + '</b>';
  } else {
    warn.style.display = 'none';
  }
}
document.getElementById('f_recipients').addEventListener('input', countPasted);

function loadOrderEmails() {
  document.getElementById('pickedArea').style.display = 'block';
  fetch('api.php?act=orders').then(r=>r.json()).then(d=>{
    if (d.code !== 0) { alert(d.msg || '加载失败'); return; }
    var html = '';
    if (!d.list.length) {
      html = '<div style="color:#aaa;">暂无历史买家邮箱</div>';
    } else {
      d.list.forEach(function(em){
        html += '<label style="display:block;padding:3px 0;"><input type="checkbox" class="pickEmail" value="' + em + '" onclick="countPicked()"> ' + em + '</label>';
      });
    }
    document.getElementById('pickedList').innerHTML = html;
    countPicked();
  });
}
function toggleAll(el) {
  document.querySelectorAll('.pickEmail').forEach(cb => cb.checked = el.checked);
  countPicked();
}
function countPicked() {
  document.getElementById('pickedCount').textContent = document.querySelectorAll('.pickEmail:checked').length;
}

function submitNew() {
  var name = document.getElementById('f_name').value.trim();
  var subject = document.getElementById('f_subject').value.trim();
  var body = document.getElementById('f_body').value.trim();
  var fromName = document.getElementById('f_from_name').value.trim();
  var recipients = document.getElementById('f_recipients').value;
  var picked = [];
  document.querySelectorAll('.pickEmail:checked').forEach(cb => picked.push(cb.value));

  if (!name) return alert('请填写活动名');
  if (!subject) return alert('请填写邮件主题');
  if (!body) return alert('请填写邮件正文');
  if (!recipients.trim() && !picked.length) return alert('请至少输入或勾选一个收件人');

  var btn = event.target;
  btn.disabled = true;
  btn.textContent = '提交中...';

  var fd = new FormData();
  fd.append('act', 'create');
  fd.append('name', name);
  fd.append('subject', subject);
  fd.append('body_html', body);
  fd.append('from_name', fromName);
  fd.append('recipients', recipients);
  fd.append('picked_emails', picked.join(','));
  fd.append('tpl_id', <?= $preTplId ?>);

  fetch('api.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
    if (d.code === 0) {
      alert('创建成功');
      if (d.code === 0) {
        location.href = 'view.php?id=' + d.id;
      } else {
        alert(d.msg || '创建失败，请重试');
        btn.disabled = false;
        btn.innerHTML = '开始发送'; // 恢复按钮状态
      }
    } else {
      alert(d.msg);
      btn.disabled = false;
      btn.innerHTML = '<i class="fa fa-save"></i> 保存活动';
    }
  });
}
</script>

<?php include __DIR__ . '/../foot.php'; ?>