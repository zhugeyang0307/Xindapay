<?php
include("../includes/common.php");
if($islogin != 1) exit('{"code":403}');
if(!checkRefererHost())exit('{"code":403}');
@header('Content-Type: application/json; charset=UTF-8');

$act = $_GET['act'] ?? '';

// 列出已上架插件
if ($act == 'list') {
    $rows = $DB->getAll("SELECT * FROM pay_plugin_market WHERE status=1 ORDER BY sort DESC, plugin ASC");
    $installed = [];
    foreach (scandir(ROOT.'plugins/') as $d) {
        if ($d === '.' || $d === '..') continue;
        $f = ROOT.'plugins/'.$d.'/'.$d.'_plugin.php';
        if (file_exists($f)) {
            $c = file_get_contents($f);
            if (preg_match("/'showname'\s*=>\s*'([^']+)'/", $c, $m)) $installed[$d] = $m[1];
            else $installed[$d] = $d;
        }
    }
    foreach ($rows as &$r) {
        $r['showname'] = $installed[$r['plugin']] ?? $r['plugin'];
    }
    exit(json_encode(['code'=>1, 'list'=>$rows]));
}

// 查商户已开通
if ($act == 'user') {
    $uid = intval($_GET['uid'] ?? 0);
    $ep = $DB->getColumn("SELECT extra_plugins FROM pay_user WHERE uid=:u", [':u'=>$uid]);
    $plugins = [];
    if ($ep) {
        $decoded = json_decode($ep, true);
        if (is_array($decoded)) $plugins = $decoded;
    }
    exit(json_encode(['code'=>1, 'plugins'=>$plugins]));
}

// 保存
if ($act == 'save') {
    $uid = intval($_POST['uid'] ?? 0);
    $plugins = trim($_POST['plugins'] ?? '');
    if (!$uid) exit(json_encode(['code'=>-1, 'msg'=>'uid 缺失']));
    $arr = $plugins ? explode(',', $plugins) : [];
    $arr = array_values(array_filter(array_map('trim', $arr)));
    $DB->exec("UPDATE pay_user SET extra_plugins=:ep WHERE uid=:u",
              [':ep'=>json_encode($arr), ':u'=>$uid]);
    exit(json_encode(['code'=>0, 'msg'=>'已保存']));
}

exit(json_encode(['code'=>-1, 'msg'=>'未知操作']));
