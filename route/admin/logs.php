<?php
require_once __DIR__ . '/_auth.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$where = [];
$args = [];
if (!empty($_GET['ip'])) {
    $where[] = "client_ip LIKE ?";
    $args[] = '%' . $_GET['ip'] . '%';
}
if (!empty($_GET['rule'])) {
    $where[] = "matched_rule_name LIKE ?";
    $args[] = '%' . $_GET['rule'] . '%';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$totalRow = DB::one("SELECT COUNT(*) AS c FROM pay_route_log $whereSql", $args);
$total = (int)($totalRow['c'] ?? 0);
$logs = DB::query("SELECT * FROM pay_route_log $whereSql ORDER BY id DESC LIMIT $perPage OFFSET $offset", $args) ?: [];
$totalPages = max(1, (int)ceil($total / $perPage));

$qs = function($p) {
    $q = ['page' => $p];
    if (!empty($_GET['ip'])) $q['ip'] = $_GET['ip'];
    if (!empty($_GET['rule'])) $q['rule'] = $_GET['rule'];
    return http_build_query($q);
};
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<title>路由日志</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="wrap">
<header>
    <h1>智能路由 · 日志</h1>
    <nav>
        <a href="index.php">规则列表</a>
        <a href="logs.php" class="active">路由日志</a>
        <a href="?logout=1" class="logout">退出</a>
    </nav>
</header>

<form method="get" class="toolbar">
    <input type="text" name="ip" placeholder="按 IP 过滤" value="<?= htmlspecialchars($_GET['ip'] ?? '') ?>">
    <input type="text" name="rule" placeholder="按规则名过滤" value="<?= htmlspecialchars($_GET['rule'] ?? '') ?>">
    <button type="submit" class="btn">筛选</button>
    <a href="logs.php" class="btn">重置</a>
    <span class="muted">共 <?= $total ?> 条</span>
</form>

<table class="list">
<thead>
<tr>
    <th>时间</th><th>订单号</th><th>UID</th><th>IP</th><th>省份</th><th>城市</th><th>运营商</th><th>设备</th><th>命中规则</th><th>通道</th>
</tr>
</thead>
<tbody>
<?php foreach ($logs as $l): ?>
<tr>
    <td class="muted"><?= htmlspecialchars($l['created_at']) ?></td>
    <td><?= htmlspecialchars($l['trade_no']) ?></td>
    <td><?= (int)$l['uid'] ?></td>
    <td><?= htmlspecialchars($l['client_ip']) ?></td>
    <td><?= htmlspecialchars($l['province']) ?></td>
    <td><?= htmlspecialchars($l['city']) ?></td>
    <td><?= htmlspecialchars($l['isp']) ?></td>
    <td><?= htmlspecialchars($l['device']) ?></td>
    <td><?= htmlspecialchars($l['matched_rule_name']) ?></td>
    <td><?= htmlspecialchars($l['channel_name']) ?></td>
</tr>
<?php endforeach; ?>
<?php if (empty($logs)): ?>
<tr><td colspan="10" class="empty">暂无日志</td></tr>
<?php endif; ?>
</tbody>
</table>

<?php if ($totalPages > 1): ?>
<div class="pager">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?<?= $qs($i) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
</div>
</body>
</html>