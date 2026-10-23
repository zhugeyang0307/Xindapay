<?php
require_once __DIR__ . '/_boot.php';
require_once __DIR__ . '/ai.php';

$act = $_POST['act'] ?? $_GET['act'] ?? '';

/* ---------- 创建活动 ---------- */
if ($act === 'create') {
    $name     = trim($_POST['name'] ?? '');
    $subject  = trim($_POST['subject'] ?? '');
    $bodyHtml = (string)($_POST['body_html'] ?? '');
    $fromName = trim($_POST['from_name'] ?? '');

    if ($name === '' || $subject === '' || $bodyHtml === '') {
        mm_json(['code' => -1, 'msg' => '活动名/邮件主题/正文不能为空']);
    }

    $smtpUser = mm_smtp_user($uid);
    if (!$smtpUser) {
        mm_json(['code' => -1, 'msg' => '请先到「邮件推送」页面配置发件邮箱 SMTP']);
    }

    $emails = [];
    $bad = [];
    foreach ([mm_split_emails($_POST['recipients'] ?? ''), mm_split_emails($_POST['picked_emails'] ?? '')] as $pair) {
        foreach ($pair[0] as $em) $emails[$em] = true;
        foreach ($pair[1] as $em) $bad[] = $em;
    }
    if (!empty($bad)) {
        mm_json(['code' => -2, 'msg' => '以下邮箱格式不正确，请修正后重试：' . "\n" . implode("\n", array_unique($bad))]);
    }

    if (empty($emails)) {
        mm_json(['code' => -1, 'msg' => '至少需要一个有效收件人邮箱']);
    }

    $tplId = (int)($_POST['tpl_id'] ?? 0);
    $campaignId = MailTrack::createCampaign($uid, $name, $subject, $bodyHtml, $smtpUser, $fromName);
    if ($tplId > 0) {
        DB::exec("UPDATE pay_mail_campaign SET tpl_id=? WHERE id=?", [$tplId, $campaignId]);
    }
    foreach (array_keys($emails) as $em) {
        MailTrack::addRecipient($uid, $campaignId, $em);
    }
    DB::exec("UPDATE pay_mail_campaign SET total_count=? WHERE id=?", [count($emails), $campaignId]);

    mm_json(['code' => 0, 'msg' => '创建成功', 'id' => $campaignId]);
}

/* ---------- 编辑活动 ---------- */
if ($act === 'update') {
    $id       = (int)($_POST['id'] ?? 0);
    $name     = trim($_POST['name'] ?? '');
    $subject  = trim($_POST['subject'] ?? '');
    $bodyHtml = (string)($_POST['body_html'] ?? '');
    $fromName = trim($_POST['from_name'] ?? '');

    if (!mm_campaign($uid, $id)) mm_json(['code' => -1, 'msg' => '活动不存在']);
    if ($name === '' || $subject === '' || $bodyHtml === '') {
        mm_json(['code' => -1, 'msg' => '活动名/主题/正文不能为空']);
    }
    // 已发送过的活动不允许改正文（防止统计错乱）


    DB::exec("UPDATE pay_mail_campaign SET name=?, subject=?, body_html=?, from_name=? WHERE id=? AND uid=?",
        [$name, $subject, $bodyHtml, $fromName, $id, $uid]);

    // 追加新收件人（去重）
    [$newEmails, $bad] = mm_split_emails($_POST['add_recipients'] ?? '');
    if (!empty($bad)) {
        mm_json(['code' => -2, 'msg' => '以下邮箱格式不正确，请修正后重试：' . "\n" . implode("\n", array_unique($bad))]);
    }
    $added = 0;
    foreach ($newEmails as $em) {
        $exist = DB::col("SELECT COUNT(*) FROM pay_mail_recipient WHERE campaign_id=? AND to_email=?", [$id, $em]);
        if ($exist > 0) continue;
        MailTrack::addRecipient($uid, $id, $em);
        $added++;
    }
    if ($added > 0) {
        DB::exec("UPDATE pay_mail_campaign SET total_count=total_count+? WHERE id=?", [$added, $id]);
    }

    mm_json(['code' => 0, 'msg' => '已保存（新增收件人 ' . $added . ' 个）']);
}
/* ---------- 查询追踪明细 ---------- */
if ($act === 'events') {
    $id   = (int)($_GET['id'] ?? 0);
    $type = trim($_GET['type'] ?? '');  // '', 'open', 'click'
    $page = max(1, (int)($_GET['page'] ?? 1));
    $size = 50;
    $offset = ($page - 1) * $size;

    if (!mm_campaign($uid, $id)) mm_json(['code' => -1, 'msg' => '活动不存在']);

    $sql = "SELECT e.*, r.to_email FROM pay_mail_event e
            INNER JOIN pay_mail_recipient r ON r.id = e.recipient_id
            WHERE e.campaign_id = ?";
    $args = [$id];
    if (in_array($type, ['open','click','landing','custom'], true)) {
        $sql .= " AND e.event_type = ?";
        $args[] = $type;
    }

    $total = (int)DB::col("SELECT COUNT(*) FROM (" . $sql . ") t", $args);
    $sql .= " ORDER BY e.id DESC LIMIT {$size} OFFSET {$offset}";
    $rows = DB::query($sql, $args) ?: [];
    foreach ($rows as &$__r) {
        $__r['ua_text'] = mm_parse_ua($__r['user_agent'] ?? '');
    }
    unset($__r);

    mm_json(['code' => 0, 'total' => $total, 'list' => $rows, 'page' => $page, 'size' => $size]);
}
/* ---------- 导出事件明细 CSV ---------- */
if ($act === 'export_events') {
    $id = (int)($_GET['id'] ?? 0);
    $cRow = mm_campaign($uid, $id);
    if (!$cRow) { header('Content-Type: text/plain'); exit('活动不存在'); }

    $rows = DB::query(
        "SELECT e.created_at, e.event_type, r.to_email, e.ip, e.target_url, e.user_agent
         FROM pay_mail_event e
         INNER JOIN pay_mail_recipient r ON r.id = e.recipient_id
         WHERE e.campaign_id = ?
         ORDER BY e.id DESC", [$id]);

    $filename = 'mail_events_' . $id . '_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');
    fputcsv($out, ['活动名', $cRow['name']]);
    fputcsv($out, ['导出时间', date('Y-m-d H:i:s')]);
    fputcsv($out, ['事件总数', count($rows)]);
    fputcsv($out, []);
    fputcsv($out, ['时间', '事件类型', '收件人邮箱', 'IP', '点击的链接', '设备与浏览器']);

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['created_at'],
            $r['event_type'] === 'open' ? '打开邮件' : '点击链接',
            $r['to_email'],
            $r['ip'] ?: '',
            $r['target_url'] ?: '',
            mm_parse_ua($r['user_agent'] ?? ''),
        ]);
    }
    fclose($out);
    exit;
}
/* ---------- 创建模板 ---------- */
if ($act === 'template_create') {
    $name    = trim($_POST['name'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $body    = (string)($_POST['body_html'] ?? '');
    if ($name === '' || $body === '') mm_json(['code' => -1, 'msg' => '模板名和正文不能为空']);

    DB::exec("INSERT INTO pay_mail_template (uid, name, subject, body_html) VALUES (?,?,?,?)",
        [$uid, $name, $subject, $body]);
    mm_json(['code' => 0, 'msg' => '模板已创建', 'id' => DB::conn()->lastInsertId()]);
}

/* ---------- 更新模板 ---------- */
if ($act === 'template_update') {
    $id      = (int)($_POST['id'] ?? 0);
    $name    = trim($_POST['name'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $body    = (string)($_POST['body_html'] ?? '');
    if ($name === '' || $body === '') mm_json(['code' => -1, 'msg' => '模板名和正文不能为空']);

    $t = DB::one("SELECT id FROM pay_mail_template WHERE id=? AND uid=?", [$id, $uid]);
    if (!$t) mm_json(['code' => -1, 'msg' => '模板不存在']);

    DB::exec("UPDATE pay_mail_template SET name=?, subject=?, body_html=? WHERE id=? AND uid=?",
        [$name, $subject, $body, $id, $uid]);
    mm_json(['code' => 0, 'msg' => '模板已更新']);
}

/* ---------- 删除模板 ---------- */
if ($act === 'template_delete') {
    $id = (int)($_POST['id'] ?? 0);
    DB::exec("DELETE FROM pay_mail_template WHERE id=? AND uid=?", [$id, $uid]);
    mm_json(['code' => 0, 'msg' => '已删除']);
}

/* ---------- 获取单个模板（供活动编辑导入） ---------- */
if ($act === 'template_get') {
    $id = (int)($_GET['id'] ?? 0);
    $t = DB::one("SELECT * FROM pay_mail_template WHERE id=? AND uid=?", [$id, $uid]);
    if (!$t) mm_json(['code' => -1, 'msg' => '模板不存在']);
    mm_json(['code' => 0, 'data' => $t]);
}
/* ---------- 模板引用统计 ---------- */
if ($act === 'template_usage') {
    $tid = (int)($_GET['tid'] ?? 0);
    $rows = DB::query("SELECT id, name, status, total_count, sent_count, created_at FROM pay_mail_campaign WHERE uid=? AND tpl_id=? ORDER BY id DESC LIMIT 100", [$uid, $tid]);
    mm_json(['code' => 0, 'list' => $rows ?: []]);
}
/* ---------- 添加单个收件人 ---------- */
if ($act === 'add_recipient') {
    $id = (int)($_POST['id'] ?? 0);
    $priority = (int)($_POST['priority'] ?? 0) === 1 ? 1 : 0;
    [$emails, $bad] = mm_split_emails($_POST['emails'] ?? '');
    if (!mm_campaign($uid, $id)) mm_json(['code' => -1, 'msg' => '活动不存在']);
    if (!empty($bad)) {
        mm_json(['code' => -2, 'msg' => '以下邮箱格式不正确，请修正后重试：' . "\n" . implode("\n", array_unique($bad))]);
    }
    if (empty($emails)) mm_json(['code' => -1, 'msg' => '没有有效邮箱']);

    $added = 0;
    foreach ($emails as $em) {
        $exist = DB::col("SELECT COUNT(*) FROM pay_mail_recipient WHERE campaign_id=? AND to_email=?", [$id, $em]);
        if ($exist > 0) continue;
        // 手动 INSERT（带 priority）
        $token = md5($em . '|' . $id . '|' . mt_rand() . '|' . microtime());
        DB::exec("INSERT INTO pay_mail_recipient (campaign_id, uid, token, to_email, priority) VALUES (?,?,?,?,?)",
            [$id, $uid, $token, $em, $priority]);
        $added++;
    }
    if ($added > 0) {
        DB::exec("UPDATE pay_mail_campaign SET total_count=total_count+? WHERE id=?", [$added, $id]);
    }
    $tag = $priority === 1 ? '（优先发送）' : '';
    mm_json(['code' => 0, 'msg' => "已添加 {$added} 个收件人{$tag}"]);
}

/* ---------- 下载记录 CSV ---------- */
if ($act === 'export') {
    $id = (int)($_GET['id'] ?? 0);
    $cRow = mm_campaign($uid, $id);
    if (!$cRow) {
        header('Content-Type: text/plain; charset=utf-8');
        exit('活动不存在');
    }

    $recipients = DB::query("SELECT * FROM pay_mail_recipient WHERE campaign_id=? ORDER BY id ASC", [$id]);
    $events = DB::query("SELECT recipient_id, event_type, MAX(created_at) AS last_at FROM pay_mail_event WHERE campaign_id=? GROUP BY recipient_id, event_type", [$id]);
    $eventMap = [];
    foreach ($events as $e) $eventMap[$e['recipient_id']][$e['event_type']] = $e['last_at'];

    $filename = 'mail_campaign_' . $id . '_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    // BOM 让 Excel 正确识别 UTF-8
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');

    // 活动信息头
    fputcsv($out, ['活动名', $cRow['name']]);
    fputcsv($out, ['邮件主题', $cRow['subject']]);
    fputcsv($out, ['发件人', $cRow['from_email']]);
    fputcsv($out, ['收件人总数', $cRow['total_count']]);
    fputcsv($out, ['已发送', $cRow['sent_count']]);
    fputcsv($out, ['失败', $cRow['failed_count']]);
    fputcsv($out, ['导出时间', date('Y-m-d H:i:s')]);
    fputcsv($out, []); // 空行

    // 明细表头
    fputcsv($out, ['ID', '邮箱', '状态', '发送时间', '是否打开', '首次打开时间', '是否点击', '首次点击时间', '错误信息']);

    $stText = [0 => '待发送', 1 => '已发送', 2 => '失败'];
    foreach ($recipients as $r) {
        $st = (int)$r['status'];
        $opened = isset($eventMap[$r['id']]['open']);
        $clicked = isset($eventMap[$r['id']]['click']);
        fputcsv($out, [
            $r['id'],
            $r['to_email'],
            $stText[$st] ?? '-',
            $r['sent_at'] ?: '',
            $opened ? '是' : '否',
            $opened ? ($eventMap[$r['id']]['open'] ?: '') : '',
            $clicked ? '是' : '否',
            $clicked ? ($eventMap[$r['id']]['click'] ?: '') : '',
            $r['error_msg'] ?: '',
        ]);
    }
    fclose($out);
    exit;
}
/* ---------- 删除活动 ---------- */
if ($act === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if (!mm_campaign($uid, $id)) mm_json(['code' => -1, 'msg' => '活动不存在']);
    DB::exec("DELETE FROM pay_mail_event WHERE campaign_id=?", [$id]);
    DB::exec("DELETE FROM pay_mail_recipient WHERE campaign_id=?", [$id]);
    DB::exec("DELETE FROM pay_mail_campaign WHERE id=? AND uid=?", [$id, $uid]);
    mm_json(['code' => 0, 'msg' => '已删除']);
}

/* ---------- 发送一批 ---------- */
if ($act === 'send') {
    @set_time_limit(600);
    ignore_user_abort(true);
    $id    = (int)($_POST['id'] ?? 0);
    $limit = min(100, max(1, (int)($_POST['limit'] ?? 10)));
    $perEmailDelay = max(0, min(60, (float)($_POST['per_email_delay'] ?? 0.5)));
    if (!mm_campaign($uid, $id)) mm_json(['code' => -1, 'msg' => '活动不存在']);
    $r = MailSender::sendBatch($uid, $id, $limit, $perEmailDelay);
    if (empty($r['ok'])) {
        $r['ok'] = false;
        $r['code'] = -1;
    } else {
        $r['code'] = 0;
    }
    mm_json($r);
}

/* ---------- 从订单拉邮箱 ---------- */
if ($act === 'orders') {
    $rows = DB::query(
        "SELECT DISTINCT buyer FROM pay_order
         WHERE uid=? AND buyer LIKE '%@%' AND status>0
         ORDER BY buyer LIMIT 5000", [$uid]
    );
    $list = [];
    foreach ($rows as $r) {
        $em = trim($r['buyer'] ?? '');
        if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL)) $list[] = $em;
    }
    mm_json(['code' => 0, 'list' => array_values(array_unique($list))]);
}

/* ---------- 手动删除收件人 ---------- */
if ($act === 'recipient_delete') {
    $rid = (int)($_POST['rid'] ?? 0);
    $row = DB::one("SELECT r.* FROM pay_mail_recipient r
                    INNER JOIN pay_mail_campaign c ON c.id=r.campaign_id
                    WHERE r.id=? AND c.uid=?", [$rid, $uid]);
    if (!$row) mm_json(['code' => -1, 'msg' => '收件人不存在']);
    DB::exec("DELETE FROM pay_mail_recipient WHERE id=?", [$rid]);
    DB::exec("UPDATE pay_mail_campaign SET total_count=GREATEST(total_count-1,0) WHERE id=?", [$row['campaign_id']]);
    mm_json(['code' => 0, 'msg' => '已删除']);
}

/* ---------- AI 生成邮件 ---------- */
if ($act === 'ai_generate') {
    $topic = trim($_POST['topic'] ?? '');
    $tone  = trim($_POST['tone'] ?? '亲切');
    $style = trim($_POST['style'] ?? '简约');
    $extra = trim($_POST['extra'] ?? '');
    if ($topic === '') mm_json(['code'=>-1, 'msg'=>'请填写邮件主题/描述']);

    $r = MailAI::generate($topic, $tone, $style, $extra);
    if (!$r['ok']) mm_json(['code'=>-1, 'msg'=>'❌ '.$r['error']]);
    mm_json([
        'code' => 0,
        'msg' => '✅ AI 已生成',
        'subject' => $r['subject'],
        'html' => $r['html'],
    ]);
}

/* ---------- AI 优化现有 HTML ---------- */
if ($act === 'ai_improve') {
    $html = (string)($_POST['html'] ?? '');
    $tip  = trim($_POST['tip'] ?? '');
    if ($html === '' || $tip === '') mm_json(['code'=>-1, 'msg'=>'HTML 和修改要求都不能为空']);

    $r = MailAI::improve($html, $tip);
    if (!$r['ok']) mm_json(['code'=>-1, 'msg'=>'❌ '.$r['error']]);
    mm_json([
        'code' => 0,
        'msg' => '✅ AI 已优化',
        'subject' => $r['subject'],
        'html' => $r['html'],
    ]);
}
mm_json(['code' => -1, 'msg' => '未知操作']);