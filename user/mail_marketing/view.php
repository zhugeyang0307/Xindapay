<?php
require_once __DIR__ . '/_boot.php';

$id = (int)($_GET['id'] ?? 0);
$c = mm_campaign($uid, $id);
if (!$c) {
    include __DIR__ . '/../head.php';
    echo '<div class="wrapper-md"><div class="alert alert-danger">活动不存在或不属于你</div><a href="index.php" class="btn btn-default">返回列表</a></div>';
    include __DIR__ . '/../foot.php';
    exit;
}

$stats = MailTrack::stats($id, $uid);
$convertCount = (int)DB::col("SELECT COUNT(DISTINCT recipient_id) FROM pay_mail_event WHERE campaign_id=? AND event_type='custom'", [$id]);
$landingCount = (int)DB::col("SELECT COUNT(DISTINCT recipient_id) FROM pay_mail_event WHERE campaign_id=? AND event_type='landing'", [$id]);
$stats['convert_count'] = $convertCount;
$stats['landing_count'] = $landingCount;
$recipients = DB::query("SELECT * FROM pay_mail_recipient WHERE campaign_id=? ORDER BY id ASC LIMIT 500", [$id]);
$events = DB::query("SELECT recipient_id, event_type, MAX(created_at) AS last_at FROM pay_mail_event WHERE campaign_id=? GROUP BY recipient_id, event_type", [$id]);

$eventMap = [];
foreach ($events as $e) {
    $eventMap[$e['recipient_id']][$e['event_type']] = $e['last_at'];
}

include __DIR__ . '/../head.php';
?>
<div id="content" class="app-content" role="main">
<div class="app-content-body">

<div class="bg-light lter b-b wrapper-md">
  <h1 class="m-n font-thin h3"><i class="fa fa-envelope-open-o" style="color:#0d47a1;"></i> <?= htmlspecialchars($c['name']) ?></h1>
</div>

<div class="wrapper-md">

<?php if (!mm_smtp_user($uid)): ?>
  <div class="alert alert-warning">
    未配置发件邮箱，无法发送。请先到 <a href="/user/mail_push.php">邮件推送</a> 配置 SMTP。
  </div>
<?php endif; ?>

<div class="row">
  <div class="col-md-3">
    <div class="panel panel-info">
      <div class="panel-body text-center">
        <div style="font-size:32px;font-weight:800;color:#0d47a1;"><?= (int)$stats['total_count'] ?></div>
        <div style="color:#888;">收件人总数</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="panel panel-success">
      <div class="panel-body text-center">
        <div style="font-size:32px;font-weight:800;color:#00a86b;"><?= (int)$stats['sent_count'] ?></div>
        <div style="color:#888;">已发送</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="panel panel-warning">
      <div class="panel-body text-center">
        <div style="font-size:32px;font-weight:800;color:#f39c12;"><?= $stats['open_rate'] ?>%</div>
        <div style="color:#888;">打开率（<?= (int)$stats['unique_open_count'] ?>人）</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="panel panel-danger">
      <div class="panel-body text-center">
        <div style="font-size:32px;font-weight:800;color:#e74c3c;"><?= $stats['click_rate'] ?>%</div>
        <div style="color:#888;">点击率（<?= (int)$stats['unique_click_count'] ?>人）</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="panel panel-warning">
      <div class="panel-body text-center">
        <div style="font-size:32px;font-weight:800;color:#f39c12;"><?= (int)$stats['landing_count'] ?></div>
        <div style="color:#888;">落地页访问（人）</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="panel panel-success">
      <div class="panel-body text-center">
        <div style="font-size:32px;font-weight:800;color:#00a86b;"><?= (int)$stats['convert_count'] ?></div>
        <div style="color:#888;">转化成功（人）</div>
      </div>
    </div>
  </div>
</div>

<div class="alert alert-warning" style="font-size:13px;">
  <b>⚠️ 数据保留政策：</b>收件人、打开/点击记录<b>只保留 7 天</b>，7 天后系统自动清除。请及时点右上「<b>下载记录</b>」保存本地 CSV 备份。
</div>

<div class="panel panel-primary">
  <div class="panel-heading font-bold">
    <i class="fa fa-paper-plane"></i> 发送控制
  </div>
  <div class="panel-body">
    <div style="font-size:14px;line-height:2;">
      <b>邮件主题：</b><?= htmlspecialchars($c['subject']) ?><br>
      <b>发件人：</b><?= htmlspecialchars($c['from_email']) ?>（显示名：<?= htmlspecialchars($c['from_name'] ?: '未设置') ?>）<br>
      <b>未发送：</b><span id="remainCount" style="color:#e74c3c;font-weight:800;"><?= (int)$stats['total_count'] - (int)$stats['sent_count'] - (int)$stats['failed_count'] ?></span> 封
    </div>
    <div style="margin-top:16px;">
      <label style="font-weight:700;">发送策略：</label>
      <select id="sendMode" class="form-control" style="width:260px;display:inline-block;" onchange="applyMode()">
        <option value="turbo">极速（20封/批 · 0.3秒/封）</option>
        <option value="standard" selected>标准（10封/批 · 1秒/封）</option>
        <option value="slow">慢速稳发（5封/批 · 3秒/封）</option>
        <option value="all">全部一次提交（≤100封）</option>
        <option value="custom">自定义</option>
      </select>
      <div id="modeHint" style="margin-top:8px;color:#e67e22;font-size:12px;line-height:1.8;"></div>

      <div id="customPanel" style="display:none;margin-top:12px;padding:12px;background:#f7f9fc;border-radius:6px;">
        <div class="row">
          <div class="col-md-4">
            <label>每批封数</label>
            <input type="number" id="c_batch" class="form-control" value="10" min="1" max="100">
          </div>
          <div class="col-md-4">
            <label>每封间隔（秒）</label>
            <input type="number" id="c_perEmail" class="form-control" value="1" min="0" max="60" step="0.1">
          </div>
          <div class="col-md-4">
            <label>每批间隔（秒）</label>
            <input type="number" id="c_gap" class="form-control" value="2" min="0" max="300">
          </div>
        </div>
        <div style="margin-top:8px;color:#86909c;font-size:12px;">
          每封间隔越小发得越快，也越容易被邮箱服务商限流。<b>建议 ≥ 1 秒</b>。
        </div>
      </div>

      <div id="estimate" style="margin-top:12px;padding:10px;background:#eef6ff;border-radius:6px;font-size:13px;"></div>

      <div style="margin-top:16px;padding:14px;background:#f0fff4;border-left:4px solid #00a86b;border-radius:6px;">
        <button id="sendBtn" onclick="startSend()"
                style="display:inline-block !important;background:#00a86b;color:#fff;border:none;
                       padding:12px 36px;font-size:16px;font-weight:800;border-radius:6px;cursor:pointer;
                       box-shadow:0 4px 12px rgba(0,168,107,.3);">
          🚀 开始发送
        </button>
        <button id="stopBtn" onclick="stopSend()"
                style="display:none;background:#e74c3c;color:#fff;border:none;
                       padding:12px 36px;font-size:16px;font-weight:800;border-radius:6px;cursor:pointer;margin-left:10px;">
          ⏹ 停止
        </button>
        <span style="color:#4a6a5a;font-size:12px;margin-left:14px;">点击开始后，下面实时显示进度日志</span>
      </div>
    </div>
    <div id="sendLog" style="margin-top:14px;background:#f7f9fc;border-radius:6px;padding:12px;max-height:220px;overflow:auto;font-family:Consolas,monospace;font-size:13px;display:none;"></div>

    <hr style="margin:18px 0;">
    <label><b>追加收件人</b>（每行一个邮箱）</label>
    <textarea id="addEmails" class="form-control" rows="4" style="margin-top:6px;" placeholder="a@example.com&#10;b@example.com"></textarea>
    <div style="margin-top:8px;">
      <label style="font-weight:normal;font-size:13px;margin-right:16px;">
        <input type="radio" name="priMode" value="0" checked> 追加到末尾（按顺序发）
      </label>
      <label style="font-weight:normal;font-size:13px;">
        <input type="radio" name="priMode" value="1"> <b style="color:#e67e22;">优先发送</b>（下一批立即发）
      </label>
    </div>
    <button class="btn btn-primary btn-sm" style="margin-top:8px;" onclick="addRecipients()"><i class="fa fa-plus"></i> 添加</button>
  </div>
</div>



<!-- ============== 追踪明细 ============== -->
<div class="panel panel-info" style="margin-top:20px;">
  <div class="panel-heading font-bold">
    <i class="fa fa-line-chart"></i> 追踪明细（打开 / 点击）
    <div class="pull-right">
      <select id="eventFilter" class="form-control" style="display:inline-block;width:150px;" onchange="loadEvents(1)">
        <option value="">全部事件</option>
        <option value="open">仅打开</option>
        <option value="click">仅点击</option>
        <option value="landing">仅落地页</option>
        <option value="custom">仅转化</option>
      </select>
      <a href="api.php?act=export_events&id=<?= $id ?>" class="btn btn-xs btn-success" style="margin-left:8px;">
        <i class="fa fa-download"></i> 导出明细 CSV
      </a>
    </div>
  </div>
  <div class="panel-body" style="padding:0;">
    <table class="table table-striped table-hover" style="margin:0;">
      <thead><tr>
        <th style="width:160px;">时间</th>
        <th style="width:90px;">事件</th>
        <th>收件人</th>
        <th style="width:130px;">IP</th>
        <th>点击的链接</th>
        <th style="width:220px;">设备与浏览器</th>
      </tr></thead>
      <tbody id="eventsBody">
        <tr><td colspan="6" style="text-align:center;color:#aaa;padding:20px;">加载中...</td></tr>
      </tbody>
    </table>
    <div style="padding:12px;text-align:center;">
      <button class="btn btn-xs btn-default" onclick="loadEvents(eventsPage-1)" id="prevBtn" disabled>上一页</button>
      <span style="margin:0 10px;color:#666;" id="pageInfo"></span>
      <button class="btn btn-xs btn-default" onclick="loadEvents(eventsPage+1)" id="nextBtn" disabled>下一页</button>
    </div>
  </div>
</div>

<div class="panel panel-default">
  <div class="panel-heading font-bold">
    <i class="fa fa-list"></i> 收件人列表（<?= count($recipients) ?> 条，最多显示 500）
    <div class="pull-right">
      <a href="api.php?act=export&id=<?= $id ?>" class="btn btn-xs btn-success"><i class="fa fa-download"></i> 下载记录</a>
      <a href="track_sdk.php" class="btn btn-xs btn-info"><i class="fa fa-code"></i> 追踪接入</a>
      <a href="edit.php?id=<?= $id ?>" class="btn btn-xs btn-warning"><i class="fa fa-pencil"></i> 编辑</a>
      <a href="index.php" class="btn btn-xs btn-default">返回列表</a>
    </div>
  </div>
  <div class="panel-body" style="padding:0;">
    <table class="table table-striped table-hover" style="margin:0;">
      <thead><tr>
        <th style="width:60px;">ID</th>
        <th>邮箱</th>
        <th style="width:100px;">状态</th>
        <th style="width:100px;">打开</th>
        <th style="width:100px;">点击</th>
        <th style="width:170px;">发送时间</th>
        <th style="width:100px;">操作</th>
      </tr></thead>
      <tbody>
      <?php foreach ($recipients as $r):
        $st = (int)$r['status'];
        $opened = isset($eventMap[$r['id']]['open']);
        $clicked = isset($eventMap[$r['id']]['click']);
      ?>
        <tr>
          <td><?= $r['id'] ?></td>
          <td><?= htmlspecialchars($r['to_email']) ?></td>
          <td>
            <?php if ($st === 1): ?>
              <span class="label label-success"><i class="fa fa-check"></i> 已发送</span>
              <?php if ($r['sent_at']): ?>
                <br><span style="color:#888;font-size:11px;"><?= htmlspecialchars($r['sent_at']) ?></span>
              <?php endif; ?>
            <?php elseif ($st === 2): ?>
              <span class="label label-danger"><i class="fa fa-times"></i> 失败</span>
              <?php if ($r['error_msg']): ?>
                <br><span style="color:#e74c3c;font-size:11px;word-break:break-all;"><?= htmlspecialchars($r['error_msg']) ?></span>
              <?php endif; ?>
            <?php else: ?>
              <span class="label label-default">待发送</span>
            <?php endif; ?>
          </td>
          <td><?= $opened ? '<i class="fa fa-check-circle" style="color:#00a86b;"></i>' : '<span style="color:#ccc;">-</span>' ?></td>
          <td><?= $clicked ? '<i class="fa fa-hand-pointer-o" style="color:#e74c3c;"></i>' : '<span style="color:#ccc;">-</span>' ?></td>
          <td style="font-size:12px;color:#888;"><?= htmlspecialchars($r['sent_at'] ?: '') ?></td>
          <td>
            <?php if ($st === 0): ?>
              <button class="btn btn-xs btn-danger" onclick="mmDelRecipient(<?= $r['id'] ?>)">删除</button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

</div>



</div>

</div>

<script>
var sending = false;
var campId = <?= (int)$id ?>;
var remainTotal = <?= max(0, (int)$stats['total_count'] - (int)$stats['sent_count'] - (int)$stats['failed_count']) ?>;

var MODES = {
  turbo:    {batch: 20,  perEmail: 0.3, gap: 1,  hint: '⚡ 极速：20 封/批，每封间隔 0.3 秒。<b>适合 Gmail/QQ 等宽松邮箱</b>，iCloud 慎用。'},
  standard: {batch: 10,  perEmail: 1,   gap: 2,  hint: '✅ 标准：10 封/批，每封间隔 1 秒。<b>推荐大多数场景</b>，平衡速度和账号安全。'},
  slow:     {batch: 5,   perEmail: 3,   gap: 5,  hint: '🐢 慢速稳发：5 封/批，每封间隔 3 秒。<b>保护账号，收件人 100+ 时推荐</b>，防止被误判垃圾邮件。'},
  all:      {batch: 100, perEmail: 1,   gap: 0,  hint: '📤 全部一次提交：一次处理最多 100 封。<b>收件人 ≤100 时可用</b>，页面需保持开启，可能耗时几分钟。'},
  custom:   null
};

function currentMode() {
  var mode = document.getElementById('sendMode').value;
  if (mode === 'custom') {
    return {
      batch: parseInt(document.getElementById('c_batch').value) || 10,
      perEmail: parseFloat(document.getElementById('c_perEmail').value) || 1,
      gap: parseInt(document.getElementById('c_gap').value) || 2,
      hint: '🔧 自定义模式'
    };
  }
  return MODES[mode] || MODES.standard;
}

function applyMode() {
  var mode = document.getElementById('sendMode').value;
  document.getElementById('customPanel').style.display = (mode === 'custom') ? 'block' : 'none';
  document.getElementById('modeHint').innerHTML = currentMode().hint;
  updateEstimate();
}

function updateEstimate() {
  var m = currentMode();
  if (remainTotal <= 0) {
    document.getElementById('estimate').innerHTML = '<b>已全部发送完成</b>';
    return;
  }
  var batches = Math.ceil(remainTotal / m.batch);
  var totalSec = remainTotal * m.perEmail + Math.max(0, batches - 1) * m.gap;
  var min = Math.floor(totalSec / 60);
  var sec = Math.round(totalSec % 60);
  var timeStr = min > 0 ? (min + ' 分 ' + sec + ' 秒') : (sec + ' 秒');
  document.getElementById('estimate').innerHTML =
    '📊 <b>待发 ' + remainTotal + ' 封</b>，' + batches + ' 批，预计耗时约 <b>' + timeStr + '</b>。';
}

document.getElementById('sendMode').addEventListener('change', applyMode);
document.getElementById('c_batch').addEventListener('input', updateEstimate);
document.getElementById('c_perEmail').addEventListener('input', updateEstimate);
document.getElementById('c_gap').addEventListener('input', updateEstimate);

function log(msg) {
  var el = document.getElementById('sendLog');
  if (!el) { console.log(msg); return; }
  el.style.display = 'block';
  el.innerHTML += '[' + new Date().toLocaleTimeString() + '] ' + msg + '<br>';
  el.scrollTop = el.scrollHeight;
}

function startSend() {
  if (sending) return;
  if (remainTotal <= 0) { alert('已无待发送的收件人'); return; }
  sending = true;
  document.getElementById('sendBtn').style.display = 'none';
  document.getElementById('stopBtn').style.display = '';
  log('🚀 开始发送...（模式：' + document.getElementById('sendMode').value + '）');
  sendOne();
}

function stopSend() {
  sending = false;
  log('⏹ 已请求停止，当前批次完成后停止');
}

function sendOne() {
  if (!sending) {
    document.getElementById('sendBtn').style.display = '';
    document.getElementById('stopBtn').style.display = 'none';
    return;
  }
  var m = currentMode();

  var fd = new FormData();
  fd.append('act', 'send');
  fd.append('id', campId);
  fd.append('limit', m.batch);
  fd.append('per_email_delay', m.perEmail);

  fetch('api.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
    // 兼容后端返回 code 的情况
    if (!d.ok && d.code === -1) {
      log('❌ ' + (d.msg || '登录已过期'));
      alert(d.msg || '登录已过期，请重新登录');
      location.href = '/user/login.php';
      return;
    }
    if (!d.ok) {
      log('❌ ' + (d.msg || '未知错误'));
      sending = false;
      document.getElementById('sendBtn').style.display = '';
      document.getElementById('stopBtn').style.display = 'none';
      return;
    }
    var line = '✅ 本批：成功 <b style="color:#00a86b;">' + d.sent + '</b>，失败 <b style="color:#e74c3c;">' + d.failed + '</b>，剩余 ' + d.remain;
    log(line);
    if (d.failed > 0 && d.last_err) log('  <span style="color:#e74c3c;">└ 错误：' + d.last_err + '</span>');
    document.getElementById('remainCount').textContent = d.remain;
    remainTotal = d.remain || 0;

    if (d.remain > 0) {
      setTimeout(function(){ sendOne(); }, m.gap * 1000);
    } else {
      log('🎉 全部发送完成！');
      sending = false;
      document.getElementById('sendBtn').style.display = '';
      document.getElementById('stopBtn').style.display = 'none';
      setTimeout(function(){ location.reload(); }, 1500);
    }
  }).catch(function(e){
    log('❌ 网络错误：' + e);
    sending = false;
    document.getElementById('sendBtn').style.display = '';
    document.getElementById('stopBtn').style.display = 'none';
  });
}
// ============ 追踪明细 ============
var eventsPage = 1;
var eventsTotalPages = 1;

function loadEvents(page) {
  if (page < 1) return;
  var type = document.getElementById('eventFilter').value;
  var url = 'api.php?act=events&id=' + campId + '&page=' + page + '&type=' + type;

  fetch(url).then(r=>r.json()).then(d=>{
    if (d.code !== 0) {
      document.getElementById('eventsBody').innerHTML = '<tr><td colspan="6" style="text-align:center;color:#e74c3c;padding:20px;">' + (d.msg||'加载失败') + '</td></tr>';
      return;
    }
    eventsPage = d.page;
    eventsTotalPages = Math.max(1, Math.ceil(d.total / d.size));

    var html = '';
    if (!d.list || !d.list.length) {
      html = '<tr><td colspan="6" style="text-align:center;color:#aaa;padding:20px;">暂无事件记录</td></tr>';
    } else {
      d.list.forEach(function(e){
        var tag;
        if (e.event_type === 'open') {
          tag = '<span class="label label-info"><i class="fa fa-eye"></i> 打开</span>';
        } else if (e.event_type === 'click') {
          tag = '<span class="label label-success"><i class="fa fa-hand-pointer-o"></i> 点击</span>';
        } else if (e.event_type === 'landing') {
          tag = '<span class="label label-warning"><i class="fa fa-globe"></i> 落地页</span>';
        } else if (e.event_type === 'custom') {
          tag = '<span class="label label-danger"><i class="fa fa-star"></i> ' + (e.event_name || '转化') + '</span>';
        } else {
          tag = '<span class="label label-default">' + e.event_type + '</span>';
        }
        // 自定义事件额外显示数据
        var extraData = '';
        if (e.event_type === 'custom' && e.event_data) {
          try {
            var d = JSON.parse(e.event_data);
            var parts = [];
            if (d.amount) parts.push('¥' + d.amount);
            if (d.order) parts.push('订单:' + d.order);
            if (parts.length) extraData = '<br><span style="color:#e74c3c;font-size:11px;">' + parts.join(' · ') + '</span>';
          } catch (err) {}
        }
        var url = e.target_url ? '<a href="' + e.target_url + '" target="_blank" rel="noopener" style="word-break:break-all;font-size:12px;">' + e.target_url + '</a>' : '<span style="color:#ccc;">—</span>';
        var uaText = e.ua_text || '未知设备';
        var uaRaw = (e.user_agent||'').replace(/"/g,'&quot;');
        html += '<tr>' +
          '<td style="font-size:12px;color:#555;">' + e.created_at + '</td>' +
          '<td>' + tag + extraData + '</td>' +
          '<td style="font-size:13px;">' + e.to_email + '</td>' +
          '<td style="font-size:12px;font-family:monospace;">' + (e.ip||'—') + '</td>' +
          '<td>' + url + '</td>' +
          '<td style="font-size:12px;color:#555;" title="' + uaRaw + '">' + uaText + '</td>' +
          '</tr>';
      });
    }
    document.getElementById('eventsBody').innerHTML = html;
    document.getElementById('pageInfo').textContent = '第 ' + eventsPage + ' / ' + eventsTotalPages + ' 页（共 ' + d.total + ' 条）';
    document.getElementById('prevBtn').disabled = (eventsPage <= 1);
    document.getElementById('nextBtn').disabled = (eventsPage >= eventsTotalPages);
  }).catch(function(err){
    document.getElementById('eventsBody').innerHTML = '<tr><td colspan="6" style="text-align:center;color:#e74c3c;padding:20px;">网络错误：' + err + '</td></tr>';
  });
}

// 页面加载后初始化
applyMode();
loadEvents(1);

function addRecipients() {
  var v = document.getElementById('addEmails').value;
  if (!v.trim()) { alert('请输入邮箱'); return; }
  var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  var all = v.split(/[\s,;，；]+/).map(s=>s.trim()).filter(s=>s);
  var invalid = all.filter(s=>!re.test(s));
  if (invalid.length) {
    alert('以下邮箱格式不正确：\n\n' + invalid.join('\n'));
    return;
  }
  var priInput = document.querySelector('input[name="priMode"]:checked');
  var pri = priInput ? priInput.value : '0';
  var fd = new FormData();
  fd.append('act', 'add_recipient');
  fd.append('id', campId);
  fd.append('emails', v);
  fd.append('priority', pri);
  fetch('api.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
    alert(d.msg);
    if (d.code === 0) location.reload();
  }).catch(function(err){ alert('网络错误：' + err); });
}

function mmDelRecipient(rid) {
  if (!confirm('确认删除该收件人？')) return;
  var fd = new FormData();
  fd.append('act', 'recipient_delete');
  fd.append('rid', rid);
  fetch('api.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
    if (d.code === 0) location.reload();
    else alert(d.msg);
  });
}
</script>

<?php include __DIR__ . '/../foot.php'; ?>