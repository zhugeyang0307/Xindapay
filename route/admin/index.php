<?php
require_once __DIR__ . '/_auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle'])) {
        DB::exec("UPDATE pay_route_rule SET status = 1 - status WHERE id=?", [(int)$_POST['toggle']]);
    }
    if (isset($_POST['del'])) {
        DB::exec("DELETE FROM pay_route_rule WHERE id=?", [(int)$_POST['del']]);
    }
    header('Location: index.php');
    exit;
}

$rules = DB::query("SELECT * FROM pay_route_rule ORDER BY priority ASC, id ASC") ?: [];

$typeLabels = [
    'uid' => '指定用户',
    'province' => '按省份',
    'isp' => '按运营商',
    'device' => '按设备',
    'ip_range' => '按IP段',
    'weight' => '按权重',
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<title>路由规则管理</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="wrap">
<header>
    <h1>智能路由 · 规则管理</h1>
    <nav>
        <a href="index.php" class="active">规则列表</a>
        <a href="logs.php">路由日志</a>
        <a href="?logout=1" class="logout">退出</a>
    </nav>
</header>

<div class="toolbar">
    <a href="edit.php" class="btn primary">+ 新增规则</a>
    <span class="muted">共 <?= count($rules) ?> 条规则，按优先级从小到大依次匹配</span>
</div>

<table class="list">
<thead>
<tr>
    <th>ID</th><th>优先级</th><th>规则名</th><th>类型</th><th>目标通道</th><th>状态</th><th>备注</th><th>操作</th>
</tr>
</thead>
<tbody>
<?php foreach ($rules as $r): ?>
<tr>
    <td><?= $r['id'] ?></td>
    <td><?= (int)$r['priority'] ?></td>
    <td><?= htmlspecialchars($r['name']) ?></td>
    <td><span class="tag"><?= htmlspecialchars($typeLabels[$r['match_type']] ?? $r['match_type']) ?></span></td>
    <td><?php
        if ($r['match_type'] === 'weight') {
            echo '<span class="muted">见权重配置</span>';
        } else {
            $ch = DB::one("SELECT name FROM pay_channel WHERE id=?", [$r['channel_id']]);
            echo htmlspecialchars($ch['name'] ?? ('#' . $r['channel_id']));
        }
    ?></td>
    <td>
        <form method="post" style="display:inline">
            <button type="submit" name="toggle" value="<?= $r['id'] ?>" class="badge <?= $r['status'] ? 'on' : 'off' ?>">
                <?= $r['status'] ? '启用' : '停用' ?>
            </button>
        </form>
    </td>
    <td class="muted"><?= htmlspecialchars($r['remark']) ?></td>
    <td>
        <a href="edit.php?id=<?= $r['id'] ?>" class="btn small">编辑</a>
        <form method="post" style="display:inline" onsubmit="return confirm('确认删除规则？')">
            <button type="submit" name="del" value="<?= $r['id'] ?>" class="btn small danger">删除</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($rules)): ?>
<tr><td colspan="8" class="empty">暂无规则，点「+ 新增规则」开始</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</body>
</html>