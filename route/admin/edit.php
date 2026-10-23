<?php
require_once __DIR__ . '/_auth.php';
require_once dirname(__DIR__) . '/lib/RouteRule.php';

$id = (int)($_GET['id'] ?? 0);
$rule = $id ? DB::one("SELECT * FROM pay_route_rule WHERE id=?", [$id]) : null;
$matchValue = $rule ? (json_decode($rule['match_value'], true) ?: []) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matchType = $_POST['match_type'] ?? 'province';
    $mv = [];
    switch ($matchType) {
        case 'uid':
            $uids = array_filter(array_map('intval', preg_split('/[\s,]+/', $_POST['uids'] ?? '')));
            $mv = ['uids' => array_values($uids)];
            break;
        case 'province':
            $mv = ['provinces' => array_values($_POST['provinces'] ?? [])];
            break;
        case 'isp':
            $mv = ['isps' => array_values($_POST['isps'] ?? [])];
            break;
        case 'device':
            $mv = ['devices' => array_values($_POST['devices'] ?? [])];
            break;
        case 'ip_range':
            $ranges = [];
            foreach (preg_split('/\r?\n/', $_POST['ip_ranges'] ?? '') as $line) {
                $line = trim($line);
                if ($line === '') continue;
                if (preg_match('/^(\S+?)\s*[-~]\s*(\S+)$/', $line, $m)) {
                    $ranges[] = ['start' => $m[1], 'end' => $m[2]];
                }
            }
            $mv = ['ranges' => $ranges];
            break;
        case 'weight':
            $chs = [];
            foreach (($_POST['weights'] ?? []) as $row) {
                if (!is_array($row)) continue;
                $cid = (int)($row['channel_id'] ?? 0);
                $w = (int)($row['weight'] ?? 0);
                if ($cid > 0 && $w > 0) {
                    $chs[] = ['channel_id' => $cid, 'weight' => $w];
                }
            }
            $mv = ['channels' => $chs];
            break;
    }

    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'priority' => (int)($_POST['priority'] ?? 100),
        'match_type' => $matchType,
        'match_value' => json_encode($mv, JSON_UNESCAPED_UNICODE),
        'channel_id' => (int)($_POST['channel_id'] ?? 0),
        'status' => isset($_POST['status']) ? 1 : 0,
        'remark' => trim($_POST['remark'] ?? ''),
    ];

    if ($id) {
        RouteRule::update($id, $data);
    } else {
        RouteRule::add($data);
    }
    header('Location: index.php');
    exit;
}

$channels = DB::query("SELECT id, name FROM pay_channel ORDER BY id ASC") ?: [];
$typeLabels = [
    'uid' => '指定用户',
    'province' => '按省份',
    'isp' => '按运营商',
    'device' => '按设备',
    'ip_range' => '按IP段',
    'weight' => '按权重（多通道随机）',
];
$provinces = ['北京','天津','河北','山西','内蒙古','辽宁','吉林','黑龙江','上海','江苏','浙江','安徽','福建','江西','山东','河南','湖北','湖南','广东','广西','海南','重庆','四川','贵州','云南','西藏','陕西','甘肃','青海','宁夏','新疆','香港','澳门','台湾'];
$isps = ['电信','联通','移动','铁通','广电','阿里云','腾讯云','华为云','百度云','世纪互联','鹏博士','教育网'];
$devices = ['pc'=>'PC','mobile'=>'手机','ios'=>'iOS','android'=>'Android','wechat'=>'微信','alipay'=>'支付宝'];

$weightRows = $matchValue['channels'] ?? [];
if (empty($weightRows)) $weightRows = [['channel_id'=>0,'weight'=>100]];
$weightIdx = count($weightRows);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<title><?= $id ? '编辑规则' : '新增规则' ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="wrap">
<header>
    <h1><?= $id ? '编辑规则 #' . $id : '新增规则' ?></h1>
    <nav>
        <a href="index.php">← 返回列表</a>
    </nav>
</header>

<form method="post" class="form">
    <div class="row">
        <label>规则名 *</label>
        <input type="text" name="name" required value="<?= htmlspecialchars($rule['name'] ?? '') ?>" placeholder="如：广东电信走凤凰通道">
    </div>
    <div class="row half">
        <div>
            <label>优先级（数字小的先匹配）</label>
            <input type="number" name="priority" value="<?= htmlspecialchars($rule['priority'] ?? 100) ?>">
        </div>
        <div>
            <label>状态</label>
            <label class="check"><input type="checkbox" name="status" <?= (!$rule || $rule['status']) ? 'checked' : '' ?>> 启用</label>
        </div>
    </div>

    <div class="row">
        <label>匹配类型 *</label>
        <select name="match_type" id="match_type" onchange="switchType()">
            <?php foreach ($typeLabels as $k => $v): ?>
                <option value="<?= $k ?>" <?= ($rule['match_type'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="row type-group" data-type="uid">
        <label>用户 UID 列表（每行一个，或逗号分隔）</label>
        <textarea name="uids" rows="5" placeholder="1001&#10;1002&#10;1003"><?= htmlspecialchars(implode("\n", $matchValue['uids'] ?? [])) ?></textarea>
    </div>

    <div class="row type-group" data-type="province">
        <label>省份（可多选）</label>
        <div class="checks">
            <?php foreach ($provinces as $p): ?>
                <label class="check"><input type="checkbox" name="provinces[]" value="<?= $p ?>" <?= in_array($p, $matchValue['provinces'] ?? []) ? 'checked' : '' ?>> <?= $p ?></label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="row type-group" data-type="isp">
        <label>运营商（可多选）</label>
        <div class="checks">
            <?php foreach ($isps as $i): ?>
                <label class="check"><input type="checkbox" name="isps[]" value="<?= $i ?>" <?= in_array($i, $matchValue['isps'] ?? []) ? 'checked' : '' ?>> <?= $i ?></label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="row type-group" data-type="device">
        <label>设备类型（可多选）</label>
        <div class="checks">
            <?php foreach ($devices as $k => $v): ?>
                <label class="check"><input type="checkbox" name="devices[]" value="<?= $k ?>" <?= in_array($k, $matchValue['devices'] ?? []) ? 'checked' : '' ?>> <?= $v ?></label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="row type-group" data-type="ip_range">
        <label>IP 段（每行一个，格式：起始IP - 结束IP）</label>
        <textarea name="ip_ranges" rows="5" placeholder="1.1.1.1 - 1.1.1.255&#10;8.8.8.0 - 8.8.8.255"><?php
            $lines = [];
            foreach (($matchValue['ranges'] ?? []) as $r) $lines[] = ($r['start'] ?? '') . ' - ' . ($r['end'] ?? '');
            echo htmlspecialchars(implode("\n", $lines));
        ?></textarea>
    </div>

    <div class="row type-group" data-type="weight">
        <label>权重分派（同一规则下多个通道按权重随机）</label>
        <table class="weight-table" id="weight-table">
            <thead><tr><th>通道</th><th>权重</th><th></th></tr></thead>
            <tbody>
            <?php $i = 0; foreach ($weightRows as $row): $i++; ?>
                <tr>
                    <td>
                        <select name="weights[<?= $i ?>][channel_id]">
                            <option value="0">— 请选择 —</option>
                            <?php foreach ($channels as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= (int)$row['channel_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="number" name="weights[<?= $i ?>][weight]" value="<?= (int)$row['weight'] ?>" min="1"></td>
                    <td><button type="button" class="btn small danger" onclick="this.closest('tr').remove()">×</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <button type="button" class="btn small" onclick="addWeightRow()">+ 添加通道</button>
    </div>

    <div class="row type-group" data-type="!weight">
        <label>目标通道 *</label>
        <select name="channel_id">
            <option value="0">— 请选择 —</option>
            <?php foreach ($channels as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (int)($rule['channel_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="row">
        <label>备注</label>
        <input type="text" name="remark" value="<?= htmlspecialchars($rule['remark'] ?? '') ?>">
    </div>

    <div class="actions">
        <button type="submit" class="btn primary">保存</button>
        <a href="index.php" class="btn">取消</a>
    </div>
</form>
</div>

<script>
var weightIdx = <?= $weightIdx ?>;
var CHANNEL_OPTIONS = '<?php
    foreach ($channels as $c) {
        echo '<option value="' . $c['id'] . '">' . htmlspecialchars($c['name'], ENT_QUOTES) . '</option>';
    }
?>';

function switchType() {
    var t = document.getElementById('match_type').value;
    document.querySelectorAll('.type-group').forEach(function(el) {
        var dt = el.getAttribute('data-type');
        var show = (dt === t) || (dt === '!weight' && t !== 'weight');
        el.style.display = show ? '' : 'none';
    });
}

function addWeightRow() {
    weightIdx++;
    var tbody = document.querySelector('#weight-table tbody');
    var tr = document.createElement('tr');
    tr.innerHTML =
        '<td><select name="weights[' + weightIdx + '][channel_id]"><option value="0">— 请选择 —</option>' + CHANNEL_OPTIONS + '</select></td>' +
        '<td><input type="number" name="weights[' + weightIdx + '][weight]" value="100" min="1"></td>' +
        '<td><button type="button" class="btn small danger" onclick="this.closest(\'tr\').remove()">×</button></td>';
    tbody.appendChild(tr);
}

switchType();
</script>
</body>
</html>