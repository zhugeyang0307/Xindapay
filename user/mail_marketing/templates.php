<?php
require_once __DIR__ . '/_boot.php';

$editId = (int)($_GET['edit'] ?? 0);
$editRow = $editId ? DB::one("SELECT * FROM pay_mail_template WHERE id=? AND uid=?", [$editId, $uid]) : null;

$list = DB::query("SELECT id, name, subject, updated_at FROM pay_mail_template WHERE uid=? ORDER BY id DESC LIMIT 500", [$uid]);
include __DIR__ . '/../head.php';
?>
<div id="content" class="app-content" role="main">
<div class="app-content-body">

<div class="bg-light lter b-b wrapper-md">
  <h1 class="m-n font-thin h3"><i class="fa fa-file-code-o" style="color:#0d47a1;"></i> 邮件模板</h1>
</div>

<div class="wrapper-md">

<div class="row">
  <!-- 左侧：模板列表 -->
  <div class="col-md-4">
    <div class="panel panel-default">
      <div class="panel-heading font-bold">
        <i class="fa fa-list"></i> 我的模板（<?= count($list) ?>）
        <a href="templates.php" class="btn btn-xs btn-primary pull-right">
          <i class="fa fa-plus"></i> 新建模板
        </a>
      </div>
      <div class="panel-body" style="padding:0;max-height:70vh;overflow:auto;">
        <?php if (empty($list)): ?>
          <div style="padding:30px;text-align:center;color:#aaa;">还没有模板</div>
        <?php else: ?>
          <?php foreach ($list as $t): ?>
            <?php $__useCount = (int)DB::col("SELECT COUNT(*) FROM pay_mail_campaign WHERE uid=? AND tpl_id=?", [$uid, $t['id']]); ?>
            <div style="padding:12px 16px;border-bottom:1px solid #eee;<?= $editId == $t['id'] ? 'background:#eef6ff;' : '' ?>">
              <div style="display:flex;justify-content:space-between;align-items:center;">
                <a href="templates.php?edit=<?= $t['id'] ?>" style="font-weight:700;color:#1a2a4a;font-size:14px;">
                  <?= htmlspecialchars($t['name']) ?>
                </a>
                <div>
                  <span class="label label-info" style="font-size:10px;" title="被活动引用次数"><?= $__useCount ?> 次</span>
                  <button class="btn btn-xs btn-danger" onclick="delTpl(<?= $t['id'] ?>, '<?= htmlspecialchars($t['name'], ENT_QUOTES) ?>')">×</button>
                </div>
              </div>
              <div style="color:#888;font-size:12px;margin-top:4px;">
                <?= htmlspecialchars($t['subject'] ?: '（无主题）') ?>
                <span style="color:#aaa;margin-left:8px;"><?= $t['updated_at'] ?></span>
              </div>
              <div style="margin-top:8px;display:flex;gap:6px;">
                <a href="new.php?tpl=<?= $t['id'] ?>" class="btn btn-xs btn-primary"><i class="fa fa-paper-plane"></i> 用此模板建活动</a>
                <?php if ($__useCount > 0): ?>
                  <button class="btn btn-xs btn-default" onclick="showUsage(<?= $t['id'] ?>, '<?= htmlspecialchars($t['name'], ENT_QUOTES) ?>')">查看引用 (<?= $__useCount ?>)</button>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- 右侧：编辑区 -->
  <div class="col-md-8">
    <div class="panel panel-primary">
      <div class="panel-heading font-bold">
        <?= $editId ? '编辑模板 #' . $editId : '新建模板' ?>
      </div>
      <div class="panel-body">

        <div class="form-group">
          <label>模板名 <span style="color:#e63946;">*</span></label>
          <input type="text" id="t_name" class="form-control" value="<?= htmlspecialchars($editRow['name'] ?? '') ?>" placeholder="如：双十一促销模板">
        </div>

        <div class="form-group">
          <label>默认邮件主题（选填）</label>
          <input type="text" id="t_subject" class="form-control" value="<?= htmlspecialchars($editRow['subject'] ?? '') ?>" placeholder="导入模板到活动后可再改">
        </div>

        <div class="form-group">
          <div style="background:linear-gradient(135deg,#f0f9ff,#e0f2fe);border:1px solid #7dd3fc;border-radius:10px;padding:14px;margin-bottom:14px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
  <div style="font-weight:800;color:#0369a1;font-size:14px;">🤖 AI 帮我写（DeepSeek）</div>
  <button type="button" onclick="toggleAiHelp()" style="background:none;border:none;color:#0369a1;font-size:12px;cursor:pointer;text-decoration:underline;">❓ 使用说明 / 最佳实践</button>
</div>

<div id="aiHelpBox" style="display:none;background:#fff;border:1px solid #bae6fd;border-radius:8px;padding:14px;margin-bottom:12px;font-size:13px;line-height:1.85;color:#0c4a6e;">
  <div style="font-weight:800;color:#0369a1;margin-bottom:8px;">📖 怎么用效果最好？</div>

  <div style="margin-bottom:10px;">
    <b style="color:#0369a1;">✅ 描述越具体，AI 越懂你</b><br>
    <span style="color:#64748b;">差：</span>促销邮件<br>
    <span style="color:#16a34a;">好：</span>双十一大促，7 折优惠券限时 3 天，满 100 减 30，突出"仅限老客户"
  </div>

  <div style="margin-bottom:10px;">
    <b style="color:#0369a1;">📋 描述公式（5 要素）</b>
    <pre style="background:#f0f9ff;color:#0c4a6e;padding:8px 12px;border-radius:6px;font-size:12px;line-height:1.6;margin:6px 0;white-space:pre-wrap;">【场景】+【对象】+【优惠】+【行动】+【时间/限制】

例：新店开业 | 老客户 | 首单 5 折券 | 点按钮领取 | 仅限前 100 名</pre>
  </div>

  <div style="margin-bottom:10px;">
    <b style="color:#0369a1;">🎨 语气选择建议</b>
    <ul style="margin:6px 0 0 20px;color:#0c4a6e;">
      <li><b>亲切</b>：日常客情、复购提醒 → "亲爱的家人们~"</li>
      <li><b>正式</b>：账单、合同、重要通知 → "尊敬的客户"</li>
      <li><b>促销</b>：节日大促、限时抢购 → "错过再等一年！"</li>
      <li><b>通知</b>：物流、发货、到账提醒 → "您的订单已发出"</li>
      <li><b>节日</b>：春节/中秋/新年祝福 → "祝您新年快乐"</li>
    </ul>
  </div>

  <div style="margin-bottom:10px;">
    <b style="color:#0369a1;">🎭 风格选择建议</b>
    <ul style="margin:6px 0 0 20px;color:#0c4a6e;">
      <li><b>简约</b>：纯文字 + 一个按钮，适合短通知</li>
      <li><b>商务</b>：专业排版，适合对 B 客户</li>
      <li><b>节日</b>：喜庆配色 + 节日元素</li>
      <li><b>活力促销</b>：红色大促风 + 倒计时</li>
    </ul>
  </div>

  <div style="margin-bottom:10px;">
    <b style="color:#0369a1;">✨ 5 个最佳实践</b>
    <ol style="margin:6px 0 0 20px;color:#0c4a6e;">
      <li><b>先描述，再改</b>：先用 AI 生成骨架，再点「🔧 优化」微调</li>
      <li><b>一次一个目标</b>：一封邮件只做一件事（要么促销，要么通知）</li>
      <li><b>标题要具体</b>："您的 5 折券明天到期"比"优惠券到期提醒"点击率高</li>
      <li><b>加行动按钮</b>：在附加要求里写"加一个立即领取按钮，链接 https://xxx"</li>
      <li><b>必看预览</b>：生成后先点「👁 预览效果」看手机/电脑显示</li>
    </ol>
  </div>

  <div style="margin-bottom:10px;">
    <b style="color:#0369a1;">❌ 不要这样做</b>
    <ul style="margin:6px 0 0 20px;color:#dc2626;">
      <li>描述太短（如"促销"）→ AI 不知道你要推什么</li>
      <li>一次要 AI 做 5 件事 → 结果都做不好</li>
      <li>不点预览直接发 → 可能出现排版问题</li>
      <li>一次生成直接群发 → 建议先发 1-2 个测试邮箱</li>
    </ul>
  </div>

  <div style="margin-bottom:0;">
    <b style="color:#0369a1;">📝 描述示例（直接抄）</b>
    <div style="background:#f0f9ff;color:#0c4a6e;padding:8px 12px;border-radius:6px;font-size:12px;line-height:1.7;margin-top:6px;">
      · 双十一大促，全场 7 折，限时 3 天，突出"仅限老客户"，加一个领券按钮<br>
      · 新客户首单立减 20 元，强调"0 门槛"，语气亲切<br>
      · 中秋节祝福 + 送 50 元无门槛券，节日喜庆风<br>
      · 系统升级通知，2 小时内可能暂停服务，语气正式<br>
      · 老客户回馈，专属 8 折券已到账，提醒 7 天内使用
    </div>
  </div>
</div>
            <div style="margin-bottom:8px;">
              <input type="text" id="ai_topic" class="form-control" placeholder="描述你要写的邮件，如：双十一促销优惠券发放通知" style="margin-bottom:8px;">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                <select id="ai_tone" class="form-control">
                  <option value="亲切">语气：亲切</option>
                  <option value="正式">语气：正式</option>
                  <option value="促销">语气：促销</option>
                  <option value="通知">语气：通知</option>
                  <option value="节日">语气：节日祝福</option>
                </select>
                <select id="ai_style" class="form-control">
                  <option value="简约">风格：简约</option>
                  <option value="商务">风格：商务</option>
                  <option value="节日">风格：节日</option>
                  <option value="活力">风格：活力促销</option>
                </select>
              </div>
              <input type="text" id="ai_extra" class="form-control" placeholder="附加要求（选填），如：包含二维码、突出7折优惠" style="margin-top:8px;">
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
              <button type="button" class="btn btn-sm" onclick="aiGenerate()" style="background:#0369a1;color:#fff;font-weight:700;">
                ✨ 生成邮件
              </button>
              <button type="button" class="btn btn-sm" onclick="aiImprove()" style="background:#7c3aed;color:#fff;font-weight:700;">
                🔧 优化当前 HTML
              </button>
              <span style="font-size:12px;color:#64748b;line-height:30px;">AI 生成后会覆盖下方正文</span>
            </div>
            <div id="ai_status" style="margin-top:8px;font-size:12px;color:#0369a1;display:none;"></div>
          </div>

          <label>HTML 正文 <span style="color:#e63946;">*</span></label>
          <div style="margin-bottom:8px;">
            <input type="file" id="htmlFile" accept=".html,.htm" style="display:inline-block;">
            <button type="button" class="btn btn-sm btn-default" onclick="importFile()">
              <i class="fa fa-upload"></i> 导入 HTML 文件
            </button>
            <button type="button" class="btn btn-sm btn-info" onclick="previewHtml()" style="margin-left:8px;">
              <i class="fa fa-eye"></i> 预览效果
            </button>
            <span style="color:#86909c;margin-left:8px;font-size:12px;">从本地 .html 文件读入正文（支持 UTF-8）</span>
          </div>
          <textarea id="t_body" class="form-control" rows="20" style="font-family:Consolas,monospace;font-size:13px;" placeholder="&lt;p&gt;亲爱的客户：&lt;/p&gt;&#10;&lt;p&gt;...&lt;/p&gt;"><?= htmlspecialchars($editRow['body_html'] ?? '') ?></textarea>
          <div style="margin-top:6px;color:#86909c;font-size:12px;">
            💡 支持完整 HTML（含 &lt;style&gt;、&lt;table&gt;、&lt;img&gt;）。链接会自动添加点击追踪，打开也会被追踪。
          </div>
        </div>

        <div style="margin-top:12px;">
          <button class="btn btn-primary" id="saveBtn" onclick="saveTpl()">
            <i class="fa fa-save"></i> <?= $editId ? '保存修改' : '创建模板' ?>
          </button>
          <?php if ($editId): ?>
            <a href="templates.php" class="btn btn-default">新建其他</a>
          <?php endif; ?>
          <a href="index.php" class="btn btn-default">返回活动列表</a>
        </div>

      </div>
    </div>

    <div class="panel panel-info">
      <div class="panel-heading font-bold"><i class="fa fa-lightbulb-o"></i> 使用说明</div>
      <div class="panel-body" style="font-size:13px;line-height:1.9;">
        <p><b>1. 建好模板后</b> → 在「新建活动」或「编辑活动」页面，会看到「从模板导入」下拉，选一个就能一键填入正文。</p>
        <p><b>2. 导入 HTML 文件</b> → 直接选本地 <code>.html</code> 文件，浏览器会读取内容填到编辑框。</p>
        <p><b>3. 变量替换（可选）</b> → 正文里可写 <code>{email}</code> 占位，发送时会自动替换成收件人邮箱。</p>
      </div>
    </div>

  </div>
</div>

</div>
</div>
</div>

<script>
var editId = <?= (int)$editId ?>;

// ===== 切换 AI 说明 =====
function toggleAiHelp() {
  var b = document.getElementById('aiHelpBox');
  b.style.display = (b.style.display === 'none') ? 'block' : 'none';
}

// ===== AI 生成邮件 =====
function aiGenerate() {
  var topic = document.getElementById('ai_topic').value.trim();
  if (!topic) { alert('请先填「描述你要写的邮件」'); return; }
  var statusEl = document.getElementById('ai_status');
  statusEl.style.display = 'block';
  statusEl.innerHTML = '⏳ DeepSeek 生成中，约 5-15 秒...';
  statusEl.style.color = '#0369a1';

  var fd = new FormData();
  fd.append('act', 'ai_generate');
  fd.append('topic', topic);
  fd.append('tone', document.getElementById('ai_tone').value);
  fd.append('style', document.getElementById('ai_style').value);
  fd.append('extra', document.getElementById('ai_extra').value.trim());

  fetch('api.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
    if (d.code !== 0) {
      statusEl.innerHTML = '❌ ' + d.msg;
      statusEl.style.color = '#dc2626';
      return;
    }
    document.getElementById('t_body').value = d.html || '';
    if (d.subject && !document.getElementById('t_subject').value) {
      document.getElementById('t_subject').value = d.subject;
    }
    statusEl.innerHTML = '✅ 已生成！点击下方「预览效果」查看';
    statusEl.style.color = '#16a34a';
  }).catch(e=>{
    statusEl.innerHTML = '❌ 网络错误：' + e;
    statusEl.style.color = '#dc2626';
  });
}

// ===== AI 优化现有 HTML =====
function aiImprove() {
  var html = document.getElementById('t_body').value.trim();
  if (!html) { alert('正文为空，请先写内容或 AI 生成'); return; }
  var tip = prompt('你想怎么改？例如：\n· 语气更亲切\n· 突出 7 折优惠\n· 加上咨询二维码\n· 精简到 200 字以内');
  if (!tip) return;
  var statusEl = document.getElementById('ai_status');
  statusEl.style.display = 'block';
  statusEl.innerHTML = '⏳ AI 优化中...';
  statusEl.style.color = '#7c3aed';

  var fd = new FormData();
  fd.append('act', 'ai_improve');
  fd.append('html', html);
  fd.append('tip', tip);

  fetch('api.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
    if (d.code !== 0) {
      statusEl.innerHTML = '❌ ' + d.msg;
      statusEl.style.color = '#dc2626';
      return;
    }
    document.getElementById('t_body').value = d.html || '';
    statusEl.innerHTML = '✅ 已优化！点击下方「预览效果」查看';
    statusEl.style.color = '#16a34a';
  }).catch(e=>{
    statusEl.innerHTML = '❌ 网络错误：' + e;
    statusEl.style.color = '#dc2626';
  });
}

// ===== 预览 HTML =====
function previewHtml() {
  var html = document.getElementById('t_body').value;
  if (!html.trim()) { alert('正文为空'); return; }
  var subject = document.getElementById('t_subject').value.trim() || '（无主题）';
  var w = window.open('', '_blank');
  w.document.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>预览 - ' + subject + '</title>');
  w.document.write('<style>body{margin:0;padding:20px;background:#f5f7fa;font-family:sans-serif;}');
  w.document.write('.preview-bar{position:fixed;top:0;left:0;right:0;background:#1e293b;color:#fff;padding:10px 20px;z-index:9999;font-size:13px;}');
  w.document.write('.preview-content{padding-top:60px;max-width:700px;margin:0 auto;background:#fff;border-radius:8px;padding:20px;margin-top:50px;}');
  w.document.write('</style></head><body>');
  w.document.write('<div class="preview-bar">📧 <b>邮件预览</b> · 主题：' + subject + ' · <a href="javascript:window.close()" style="color:#60a5fa;">关闭</a></div>');
  w.document.write('<div class="preview-content">' + html + '</div>');
  w.document.write('</body></html>');
  w.document.close();
}

function showUsage(tid, name) {
  fetch('api.php?act=template_usage&tid=' + tid).then(r=>r.json()).then(d=>{
    if (d.code !== 0) { alert('加载失败'); return; }
    if (!d.list.length) { alert('暂无引用记录'); return; }
    var html = '<div style="padding:10px;"><h4>「' + name + '」被以下活动引用：</h4><table class="table table-sm">' +
               '<thead><tr><th>ID</th><th>活动名</th><th>状态</th><th>已发/总数</th><th>创建时间</th></tr></thead><tbody>';
    d.list.forEach(function(c){
      var st = c.status == 0 ? '草稿' : (c.status == 1 ? '发送中' : '已完成');
      html += '<tr><td>' + c.id + '</td><td>' + c.name + '</td><td>' + st + '</td><td>' + c.sent_count + '/' + c.total_count + '</td><td>' + c.created_at + '</td></tr>';
    });
    html += '</tbody></table></div>';
    // 简单弹窗
    var w = window.open('', '_blank', 'width=700,height=500');
    w.document.write('<html><head><meta charset="utf-8"><title>模板引用</title>');
    w.document.write('<link rel="stylesheet" href="/assets/css/bootstrap.min.css">');
    w.document.write('<style>body{padding:20px;font-family:sans-serif;} .table-sm td,.table-sm th{padding:6px;font-size:13px;}</style>');
    w.document.write('</head><body>');
    w.document.write(html);
    w.document.write('</body></html>');
  });
}

function delTpl(id, name) {
  if (!confirm('确认删除模板「' + name + '」？')) return;
  var fd = new FormData();
  fd.append('act', 'template_delete');
  fd.append('id', id);
  fetch('api.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
    alert(d.msg);
    if (d.code === 0) {
      if (editId == id) location.href = 'templates.php';
      else location.reload();
    }
  });
}

function importFile() {
  var f = document.getElementById('htmlFile').files[0];
  if (!f) { alert('请先选择 .html 文件'); return; }
  var reader = new FileReader();
  reader.onload = function(e) {
    document.getElementById('t_body').value = e.target.result;
  };
  reader.onerror = function() { alert('读取文件失败'); };
  reader.readAsText(f, 'UTF-8');
}

function saveTpl() {
  var name = document.getElementById('t_name').value.trim();
  var subject = document.getElementById('t_subject').value.trim();
  var body = document.getElementById('t_body').value;
  if (!name || !body) { alert('模板名和正文不能为空'); return; }

  var btn = document.getElementById('saveBtn');
  btn.disabled = true;
  btn.textContent = '保存中...';

  var fd = new FormData();
  fd.append('act', editId ? 'template_update' : 'template_create');
  if (editId) fd.append('id', editId);
  fd.append('name', name);
  fd.append('subject', subject);
  fd.append('body_html', body);

  fetch('api.php', {method:'POST', body:fd}).then(r=>{
    if (!r.ok) throw new Error('HTTP ' + r.status);
    return r.text();
  }).then(text => {
    var d;
    try { d = JSON.parse(text); } 
    catch(e) { throw new Error('返回格式错误: ' + text.substring(0, 50)); }
    
    alert(d.msg || '操作完成');
    if (d.code === 0) {
      if (editId) location.reload();
      else location.href = 'templates.php?edit=' + d.id;
    } else {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa fa-save"></i> 保存';
    }
  }).catch(err => {
    alert('保存失败：' + err.message);
    btn.disabled = false;
    btn.innerHTML = '<i class="fa fa-save"></i> 保存';
  });
}
</script>

<?php include __DIR__ . '/../foot.php'; ?>