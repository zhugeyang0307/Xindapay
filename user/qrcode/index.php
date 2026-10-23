<?php
include("../../includes/common.php");
if($islogin2==1){}else exit("<script language='javascript'>window.location.href='../login.php';</script>");
$title='收款方式管理';
$uid = $userrow['uid'];
$my = $_GET['my'] ?? '';
// 按 IP 查询归属地（供 bind_ip 使用）
function __qrcode_ip2region($ip) {
    if (!filter_var($ip, FILTER_VALIDATE_IP)) return ['province'=>null,'city'=>null];
    try {
        require_once dirname(__DIR__, 2) . '/route/lib/IpLocation.php';
        if (class_exists('IpLocation')) {
            if (!defined('__IPLOC_INITED')) { IpLocation::init(); define('__IPLOC_INITED', 1); }
            $r = IpLocation::lookup($ip);
            $p = preg_replace('/[省市区]$/u', '', $r['province'] ?? '');
            $c = $r['city'] ?? '';
            if (in_array($p, ['内网','海外',''])) $p = null;
            return ['province'=>$p, 'city'=>$c ?: null];
        }
    } catch (Throwable $e) {}
    return ['province'=>null,'city'=>null];
}

function __norm_mail_to($s) {
    $s = str_replace(['，','；','、',' ',"\t","\n","\r"], ',', $s);
    $parts = array_filter(array_map('trim', explode(',', $s)));
    $ok = [];
    foreach ($parts as $em) {
        if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL)) $ok[] = $em;
    }
    return implode(',', $ok);
}

$PAY_TYPES = [
    'wechat_reward'    => ['platform'=>'wechat','category'=>'qrcode', 'label'=>'微信赞赏码',   'max'=>20],
    'wechat_personal'  => ['platform'=>'wechat','category'=>'qrcode', 'label'=>'微信个人码',   'max'=>20],
    'wechat_biz'       => ['platform'=>'wechat','category'=>'qrcode', 'label'=>'微信经营码',   'max'=>20],
    'wechat_bank'      => ['platform'=>'wechat','category'=>'account','label'=>'微信银行转账','max'=>5],
    'wechat_phone'     => ['platform'=>'wechat','category'=>'account','label'=>'微信手机号',  'max'=>5],
    'alipay_card'      => ['platform'=>'alipay','category'=>'qrcode', 'label'=>'支付宝名片',   'max'=>20],
    'alipay_personal'  => ['platform'=>'alipay','category'=>'qrcode', 'label'=>'支付宝个人码', 'max'=>20],
    'alipay_biz'       => ['platform'=>'alipay','category'=>'qrcode', 'label'=>'支付宝经营码', 'max'=>20],
    'alipay_receipt'   => ['platform'=>'alipay','category'=>'qrcode', 'label'=>'经营收款单',   'max'=>20],
    'alipay_aa'        => ['platform'=>'alipay','category'=>'qrcode', 'label'=>'AA收款',       'max'=>20],
    'alipay_pouch'     => ['platform'=>'alipay','category'=>'qrcode', 'label'=>'支付宝小荷包', 'max'=>20],
    'alipay_account'   => ['platform'=>'alipay','category'=>'account','label'=>'支付宝账号转账','max'=>5],
    'alipay_token'     => ['platform'=>'alipay','category'=>'token',  'label'=>'支付宝口令',   'max'=>10],
    'aggregate_wechat' => ['platform'=>'aggregate','category'=>'qrcode','label'=>'聚合码·微信',   'max'=>5],
    'aggregate_alipay' => ['platform'=>'aggregate','category'=>'qrcode','label'=>'聚合码·支付宝', 'max'=>5],
    'aggregate_both'   => ['platform'=>'aggregate','category'=>'qrcode','label'=>'聚合码·通用',   'max'=>5],
];
$AGGREGATE_CHANNELS = ['aggregate_wechat','aggregate_alipay','aggregate_both'];
// 收款码不再限制数量。但请及时删除已失效/不能收款的码，避免被系统派单导致订单失败。

// 各类型获取指引（显示在上传弹窗里）
$PAY_HINTS = [
    'wechat_reward'   => ['微信 → 我 → 服务 → 收付款 → 赞赏码（点击生成）', '截图保存整张二维码，注意不要裁剪四周留白'],
    'wechat_personal' => ['微信 → 我 → 服务 → 收付款 → 二维码收款', '截图保存整张二维码'],
    'wechat_biz'      => ['微信收款商业版小程序 → 我 → 收款码 → 保存收款码', '若用微信支付商家版，从「商家服务」小程序获取'],
    // 'wechat_bank'     => ['微信 → 我 → 服务 → 钱包 → 银行卡 → 添加银行卡', '填写银行卡号 + 开户行 + 户名，无需上传图片'],
    // 'wechat_phone'    => ['微信 → 我 → 头像 → 微信号/手机号', '填写手机号，用于微信手机号转账'],
    // 'alipay_card'     => ['支付宝 → 首页搜索「收款名片」→ 生成我的名片', '保存名片二维码，四周留白不要裁剪'],
    'alipay_personal' => ['支付宝 → 首页 → 收钱 → 保存图片', '个人收款码，注意截图整张'],
    'alipay_biz'      => ['支付宝 → 我的 → 商家服务 → 收款码 → 保存', '商家经营码，带「支」logo 的那种'],
    'alipay_receipt'  => ['支付宝 → 搜索「收款单」→ 创建收款单 → 生成二维码', '经营收款单，注意设置金额模式'],
    'alipay_aa'       => ['支付宝 → 首页搜索「AA收款」→ 发起 AA → 生成二维码', 'AA 收款码'],
    'alipay_pouch'    => ['支付宝 → 首页搜索「小荷包」→ 创建 → 收款码', '小荷包收款码'],
    'alipay_account'  => ['支付宝账号（手机号/邮箱）', '填写支付宝账号 + 真实姓名，用于转账'],
    'alipay_token'    => ['支付宝 → 收钱 → 向 TA 要钱 → 生成口令', '复制完整口令文本，粘贴到下方'],
    'aggregate_wechat'=> ['收银机 / 聚合服务商后台 → 导出微信聚合码', '★ 必须上传原图，不要截图裁剪，不要加白边'],
    'aggregate_alipay'=> ['收银机 / 聚合服务商后台 → 导出支付宝聚合码', '★ 必须上传原图，不要截图裁剪'],
    'aggregate_both'  => ['收银机 / 聚合服务商后台 → 通用聚合码', '★ 必须上传原图，微信/支付宝通用，识别后能同时收两种'],
];

// 输出成 JS
$HINTS_JSON = json_encode($PAY_HINTS, JSON_UNESCAPED_UNICODE);

// ========== 保存 ==========
if($my == 'save') {
    $channel = $_POST['channel'] ?? '';
    $remark  = trim($_POST['remark'] ?? '');
    $imgData = $_POST['img_data'] ?? '';
    $content = trim($_POST['qrcode_content'] ?? '');
    $pay_account = trim($_POST['pay_account'] ?? '');
    $pay_name = trim($_POST['pay_name'] ?? '');
    $pay_bank = trim($_POST['pay_bank'] ?? '');
    $pay_extra = trim($_POST['pay_extra'] ?? '');
    $operator_name = trim($_POST['operator_name'] ?? '');
    $group_id      = trim($_POST['group_id'] ?? '');
    $operator_tg   = intval($_POST['operator_tg'] ?? 0);

    $float_rate     = floatval($_POST['float_rate'] ?? 0);
    $min_amount     = floatval($_POST['min_amount'] ?? 0);
    $max_amount     = floatval($_POST['max_amount'] ?? 0);
    $bindIp         = trim($_POST['bind_ip'] ?? '');
    $bindLoc        = $bindIp ? __qrcode_ip2region($bindIp) : ['province'=>null,'city'=>null];
    $bindLoc        = $bindIp ? __qrcode_ip2region($bindIp) : ['province'=>null,'city'=>null];
    $day_limit      = intval($_POST['day_limit'] ?? 0);
    $day_max_amount = floatval($_POST['day_max_amount'] ?? 0);

    if(!$channel || !isset($PAY_TYPES[$channel])) exit(json_encode(['code'=>-1,'msg'=>'类型错误']));
    $pt = $PAY_TYPES[$channel];
    $category = $pt['category'];

    if($category == 'qrcode' && !$imgData) exit(json_encode(['code'=>-1,'msg'=>'请上传二维码图片']));
    if($category == 'account' && !$pay_account) exit(json_encode(['code'=>-1,'msg'=>'请填写收款账号']));
    if($category == 'token' && !$pay_extra) exit(json_encode(['code'=>-1,'msg'=>'请填写口令内容']));

    if($min_amount > 0 && $max_amount > 0 && $min_amount > $max_amount) {
        exit(json_encode(['code'=>-1,'msg'=>'最低金额不能大于最高金额']));
    }


    $isRaw = (strpos($content, '__RAW__') === 0);
    if($isRaw) $content = '';

    // ===== 后端兜底解析（zbarimg） =====
    if (empty($content) && !empty($imgPath)) {
        // 兼容 ROOT 常量是否有末尾斜杠
        $fullPath = rtrim(ROOT, '/') . '/' . ltrim($imgPath, '/');
        
        if (function_exists('exec')) {
            $out = [];
            $ret = 0;
            @exec('/usr/bin/zbarimg -q --raw ' . escapeshellarg($fullPath) . ' 2>&1', $out, $ret);
            
            // 写入日志观察执行情况
            @file_put_contents(ROOT . 'qrcode_debug.log', date('H:i:s') . " | IMG=$fullPath | Ret=$ret | Out=" . implode('', $out) . "\n", FILE_APPEND);
            
            if ($ret === 0 && !empty($out)) {
                $content = trim(implode('', $out));
            }
        }
    }

    $imgPath = '';
    if($imgData) {
        $imgData = preg_replace('#^data:image/\w+;base64,#i','',$imgData);
        $imgBin = base64_decode($imgData);
        $fn = 'qr_'.$uid.'_'.date('YmdHis').'_'.mt_rand(1000,9999).'.png';
        $dir = ROOT.'assets/qrcode/'.$uid.'/';
        if(!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($dir.$fn, $imgBin);
        $imgPath = 'assets/qrcode/'.$uid.'/'.$fn;


    }

    // 自动授权：如果 operator_tg 留空 且 group_id 是正数（私聊目标）→ 自动用 group_id
    if (empty($operator_tg) && !empty($group_id) && strpos($group_id, '-') === 0) {
        $operator_tg = $group_id;
    }

    $DB->insert('qrcode', [
        'uid' => $uid,
        'channel' => $channel,
        'category' => $category,
        'remark' => $remark,
        'qrcode_content' => $content,
        'qrcode_img' => $imgPath,
        'pay_account' => $pay_account,
        'pay_name' => $pay_name,
        'pay_bank' => $pay_bank,
        'pay_extra' => $pay_extra,
        'operator_name' => $operator_name,
        
        'group_id' => $group_id,
        'mail_to' => __norm_mail_to($_POST['mail_to'] ?? '') ?: null,
        'mail_enabled' => __norm_mail_to($_POST['mail_to'] ?? '') !== '' ? 1 : 0,
        'region_province' => trim($_POST['region_province'] ?? '') ?: null,
        'bind_ip' => $bindIp ?: null,
        'bind_province' => $bindLoc['province'],
        'bind_city' => $bindLoc['city'],
        'bind_province' => $bindLoc['province'],
        'bind_city' => $bindLoc['city'],
        'region_city' => trim($_POST['region_city'] ?? '') ?: null,
        'operator_tg' => $operator_tg,
        'float_rate' => $float_rate,
        'min_amount' => $min_amount,
        'max_amount' => $max_amount,
        'day_limit' => $day_limit,
        'day_max_amount' => $day_max_amount,
        'status' => 1,
        'addtime' => 'NOW()'
    ]);
    exit(json_encode(['code'=>0,'msg'=>'上传成功']));
}

// ========== 编辑保存 ==========
if($my == 'update') {
    $id = intval($_POST['id'] ?? 0);
    if(!$id) exit(json_encode(['code'=>-1,'msg'=>'参数错误']));
    $row = $DB->find('qrcode', '*', ['id'=>$id, 'uid'=>$uid]);
    if(!$row) exit(json_encode(['code'=>-1,'msg'=>'不存在']));

    $float_rate     = floatval($_POST['float_rate'] ?? 0);
    $min_amount     = floatval($_POST['min_amount'] ?? 0);
    $max_amount     = floatval($_POST['max_amount'] ?? 0);
    $bindIp         = trim($_POST['bind_ip'] ?? '');
    $bindLoc        = $bindIp ? __qrcode_ip2region($bindIp) : ['province'=>null,'city'=>null];
    $bindLoc        = $bindIp ? __qrcode_ip2region($bindIp) : ['province'=>null,'city'=>null];
    $day_limit      = intval($_POST['day_limit'] ?? 0);
    $day_max_amount = floatval($_POST['day_max_amount'] ?? 0);
    $remark         = trim($_POST['remark'] ?? '');
    $pay_account    = trim($_POST['pay_account'] ?? '');
    $pay_name       = trim($_POST['pay_name'] ?? '');
    $pay_bank       = trim($_POST['pay_bank'] ?? '');
    $pay_extra      = trim($_POST['pay_extra'] ?? '');
    $operator_name  = trim($_POST['operator_name'] ?? '');
    $group_id       = trim($_POST['group_id'] ?? '');
    $operator_tg    = intval($_POST['operator_tg'] ?? 0);

    if($min_amount > 0 && $max_amount > 0 && $min_amount > $max_amount) {
        exit(json_encode(['code'=>-1,'msg'=>'最低金额不能大于最高金额']));
    }

    // 自动授权：如果 operator_tg 留空 且 group_id 是正数 → 自动用 group_id
    if (empty($operator_tg) && !empty($group_id) && strpos($group_id, '-') === 0) {
        $operator_tg = $group_id;
    }

    $DB->update('qrcode', [
        'remark' => $remark,
        'float_rate' => $float_rate,
        'min_amount' => $min_amount,
        'max_amount' => $max_amount,
        'day_limit' => $day_limit,
        'day_max_amount' => $day_max_amount,
        'pay_account' => $pay_account,
        'pay_name' => $pay_name,
        'pay_bank' => $pay_bank,
        'pay_extra' => $pay_extra,
        'operator_name' => $operator_name,
        
        'group_id' => $group_id,
        'mail_to' => __norm_mail_to($_POST['mail_to'] ?? '') ?: null,
        'mail_enabled' => __norm_mail_to($_POST['mail_to'] ?? '') !== '' ? 1 : 0,
        'region_province' => trim($_POST['region_province'] ?? '') ?: null,
        'bind_ip' => $bindIp ?: null,
        'bind_province' => $bindLoc['province'],
        'bind_city' => $bindLoc['city'],
        'bind_province' => $bindLoc['province'],
        'bind_city' => $bindLoc['city'],
        'region_city' => trim($_POST['region_city'] ?? '') ?: null,
        'operator_tg' => $operator_tg,
    ], ['id'=>$id, 'uid'=>$uid]);
    exit(json_encode(['code'=>0,'msg'=>'保存成功']));
}

if($my == 'delete') {
    $id = intval($_POST['id'] ?? 0);
    $DB->exec("DELETE FROM pay_qrcode WHERE id=:id AND uid=:uid", [':id'=>$id, ':uid'=>$uid]);
    exit(json_encode(['code'=>0,'msg'=>'已删除']));
}

if($my == 'batch_delete') {
    $ids = $_POST['ids'] ?? '';
    if(!$ids) exit(json_encode(['code'=>-1,'msg'=>'请选择']));
    $idArr = array_map('intval', explode(',', $ids));
    $idStr = implode(',', $idArr);
    $DB->exec("DELETE FROM pay_qrcode WHERE id IN ($idStr) AND uid=:uid", [':uid'=>$uid]);
    exit(json_encode(['code'=>0,'msg'=>'已删除 '.count($idArr).' 条']));
}

if($my == 'toggle') {
    $id = intval($_POST['id'] ?? 0);
    $DB->exec("UPDATE pay_qrcode SET status=1-status WHERE id=:id AND uid=:uid", [':id'=>$id, ':uid'=>$uid]);
    exit(json_encode(['code'=>0,'msg'=>'已切换']));
}

if($my == 'batch_status') {
    $ids = $_POST['ids'] ?? '';
    $status = intval($_POST['status'] ?? 1);
    if(!$ids) exit(json_encode(['code'=>-1,'msg'=>'请选择']));
    $idArr = array_map('intval', explode(',', $ids));
    $idStr = implode(',', $idArr);
    $DB->exec("UPDATE pay_qrcode SET status=:s WHERE id IN ($idStr) AND uid=:uid", [':s'=>$status, ':uid'=>$uid]);
    exit(json_encode(['code'=>0,'msg'=>'已操作 '.count($idArr).' 条']));
}

if($my == 'reset_today') {
    $id = intval($_POST['id'] ?? 0);
    $DB->exec("UPDATE pay_qrcode SET today_count=0, today_amount=0, today_date=CURDATE() WHERE id=:id AND uid=:uid", [':id'=>$id, ':uid'=>$uid]);
    exit(json_encode(['code'=>0,'msg'=>'已重置今日统计']));
}

// ========== 列表 ==========
$where = "(uid=:uid OR uid IN (SELECT uid FROM pay_user WHERE upid=:uid2))";
$params = [':uid'=>$uid, ':uid2'=>$uid];
$fChannel = $_GET['f_channel'] ?? '';
$fStatus  = $_GET['f_status'] ?? '';
if($fChannel) { $where .= " AND channel=:ch"; $params[':ch']=$fChannel; }
if($fStatus !== '') { $where .= " AND status=:st"; $params[':st']=intval($fStatus); }

$list = $DB->getAll("SELECT * FROM pay_qrcode WHERE $where ORDER BY id DESC", $params);
$aggTotal = $DB->getColumn("SELECT COUNT(*) FROM pay_qrcode WHERE uid=:u AND channel IN ('" . implode("','", $AGGREGATE_CHANNELS) . "')", [':u'=>$uid]);

include('../head.php');
?>
<style>
.user-qrcode-page {
  background: linear-gradient(180deg, #f6f9ff 0%, #f3f7fb 100%);
}
.user-qrcode-page .app-content-body {
  background: transparent !important;
}
.user-qrcode-page .wrapper-md {
  padding-top: 22px !important;
  padding-bottom: 18px !important;
}
.user-qrcode-page .bg-light.lter.b-b.wrapper-md {
  background: transparent !important;
  border: 0 !important;
  padding-bottom: 10px !important;
}
.user-qrcode-page .bg-light.lter.b-b.wrapper-md h1 {
  font-size: 28px !important;
  font-weight: 800 !important;
  color: #0f172a !important;
  letter-spacing: 0.02em;
}
.user-qrcode-page .alert-warning {
  border-radius: 14px !important;
  border: 1px solid #f5e0a6 !important;
  box-shadow: 0 10px 24px rgba(245, 158, 11, 0.08) !important;
}
.user-qrcode-page .panel {
  border: 1px solid #eaf0f8 !important;
  border-radius: 16px !important;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.03) !important;
  overflow: hidden;
}
.user-qrcode-page .panel-heading {
  background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%) !important;
  border-bottom: 1px solid #edf2f7 !important;
  font-weight: 800 !important;
  color: #0f172a !important;
}
.user-qrcode-page .panel-body {
  background: #fff !important;
}
.user-qrcode-page .panel-footer {
  border-top: 1px solid #edf2f7 !important;
  background: #fafcff !important;
}
.user-qrcode-page .btn {
  border-radius: 10px !important;
  font-weight: 600 !important;
  transition: all 0.2s ease !important;
}
.user-qrcode-page .btn-primary,
.user-qrcode-page .btn-success,
.user-qrcode-page .btn-warning,
.user-qrcode-page .btn-danger {
  box-shadow: 0 8px 18px rgba(22, 119, 255, 0.12) !important;
}
.user-qrcode-page .btn-default {
  border: 1px solid #dfe8f3 !important;
  background: #fff !important;
  color: #334155 !important;
}
.user-qrcode-page .table > thead > tr > th {
  background: #f5f8ff !important;
  color: #53657d !important;
  font-size: 12px !important;
  text-transform: uppercase !important;
  letter-spacing: 0.05em;
  border-bottom: 1px solid #eaf0f8 !important;
}
.user-qrcode-page .table > tbody > tr > td {
  background: #fff !important;
  color: #1f2937 !important;
  border-bottom: 1px solid #eef3f9 !important;
  vertical-align: middle !important;
}
.user-qrcode-page .table > tbody > tr:hover > td {
  background: #f8fbff !important;
}
.user-qrcode-page .label {
  border-radius: 999px !important;
  padding: 5px 10px !important;
  font-size: 11px !important;
  font-weight: 700 !important;
}
.user-qrcode-page .form-control,
.user-qrcode-page select,
.user-qrcode-page input[type="text"],
.user-qrcode-page input[type="number"],
.user-qrcode-page textarea {
  border: 1px solid #dfe8f3 !important;
  border-radius: 10px !important;
  box-shadow: none !important;
  background: #fff !important;
}
.user-qrcode-page .form-control:focus,
.user-qrcode-page select:focus,
.user-qrcode-page textarea:focus {
  border-color: #7bb6ff !important;
  box-shadow: 0 0 0 3px rgba(22, 119, 255, 0.08) !important;
}
.user-qrcode-page .modal-content {
  border: 0 !important;
  border-radius: 18px !important;
  box-shadow: 0 24px 60px rgba(15, 23, 42, 0.18) !important;
}
.user-qrcode-page .modal-header {
  background: linear-gradient(180deg, #f9fbff 0%, #ffffff 100%) !important;
  border-bottom: 1px solid #edf2f7 !important;
  padding: 18px 22px !important;
}
.user-qrcode-page .modal-body {
  padding: 22px !important;
  background: #fff !important;
}
.user-qrcode-page .platform-tabs .ptab {
  border: 2px solid #e2e8f0 !important;
  background: #fff !important;
  border-radius: 14px !important;
  transition: all 0.2s ease !important;
}
.user-qrcode-page .platform-tabs .ptab.active {
  border-color: #7bb6ff !important;
  background: linear-gradient(180deg, #f1f8ff 0%, #edf5ff 100%) !important;
  box-shadow: 0 10px 22px rgba(22, 119, 255, 0.08) !important;
}
.user-qrcode-page .switch {
  width: 42px !important;
  height: 22px !important;
  accent-color: #1f9d6f !important;
}
.user-qrcode-page .control .form-inline {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}
.user-qrcode-page .control .form-group {
  margin-right: 0 !important;
}
</style>
<div id="content" class="app-content user-qrcode-page" role="main">
    <div class="app-content-body ">

<div class="bg-light lter b-b wrapper-md hidden-print">
  <h1 class="m-n font-thin h3">收款方式管理</h1>
<div class="alert alert-warning" style="margin:16px 0;border-left:4px solid #f59e0b;background:#fffbeb;">
  <b>⚠️ 温馨提示</b>
  <ul style="margin:8px 0 0 20px;line-height:1.9;">
    <li>收款码<b>不限数量</b>，可自由添加（微信/支付宝/聚合码）</li>
    <li>请及时<b>删除已失效 / 不能收款</b>的码，避免被系统派单导致订单失败</li>
    <li>暂时不用的码建议点「停用」而不是删除（保留历史数据）</li>
  </ul>
</div>
  <ol class="breadcrumb" style="background:transparent;padding:0;margin-top:8px;font-size:12px;">
    <li><a href="./">用户中心</a></li>
    <li class="active">收款方式管理</li>
  </ol>
</div>

<div class="wrapper-md control">

<div style="background:linear-gradient(135deg,#f0f6ff 0%,#e6f0ff 100%);border-left:4px solid #1677ff;padding:14px 18px;border-radius:8px;margin-bottom:20px;">
  <b style="color:#1677ff;">💡 支持 16 种收款方式 · 可设置金额浮动和每日限额</b><br>
  <span style="color:#666;font-size:13px;">微信赞赏码 / 个人码 / 经营码 / 银行账号 / 手机号 · 支付宝名片 / 个人码 / 经营码 / 收款单 / AA收款 / 小荷包 / 账号 / 口令 · 聚合码</span>
  <div style="margin-top:8px;font-size:13px;color:#666;">
    聚合码已上传 <b style="color:#1677ff;font-size:16px;"><?php echo $aggTotal; ?></b> / 5
  </div>
</div>

<div class="panel panel-default">
  <div class="panel-body">
    <form class="form-inline" method="get" style="margin-bottom:0;">
      <div class="form-group" style="margin-right:20px;">
        <label style="margin-right:8px;">类型：</label>
        <select name="f_channel" class="form-control" style="width:180px;">
          <option value="">全部类型</option>
          <optgroup label="微信">
            <?php foreach($PAY_TYPES as $k=>$v): if($v['platform']!='wechat')continue;?>
            <option value="<?php echo $k;?>" <?php if($fChannel==$k)echo 'selected';?>><?php echo $v['label'];?></option>
            <?php endforeach;?>
          </optgroup>
          <optgroup label="支付宝">
            <?php foreach($PAY_TYPES as $k=>$v): if($v['platform']!='alipay')continue;?>
            <option value="<?php echo $k;?>" <?php if($fChannel==$k)echo 'selected';?>><?php echo $v['label'];?></option>
            <?php endforeach;?>
          </optgroup>
          <optgroup label="聚合码">
            <?php foreach($PAY_TYPES as $k=>$v): if($v['platform']!='aggregate')continue;?>
            <option value="<?php echo $k;?>" <?php if($fChannel==$k)echo 'selected';?>><?php echo $v['label'];?></option>
            <?php endforeach;?>
          </optgroup>
        </select>
      </div>
      <div class="form-group" style="margin-right:20px;">
        <label style="margin-right:8px;">状态：</label>
        <select name="f_status" class="form-control" style="width:140px;">
          <option value="">全部</option>
          <option value="1" <?php if($fStatus==='1')echo 'selected';?>>开启</option>
          <option value="0" <?php if($fStatus==='0')echo 'selected';?>>关闭</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary" style="margin-right:8px;">查询</button>
      <a href="./" class="btn btn-default">重置</a>
    </form>
  </div>
</div>

<div style="margin-bottom:15px;">
  <button class="btn btn-primary" onclick="openUpload()">
    <i class="fa fa-plus"></i>&nbsp;添加收款方式
  </button>
  <button class="btn btn-success" onclick="batchStatus(1)"><i class="fa fa-play"></i>&nbsp;批量开启</button>
  <button class="btn btn-warning" onclick="batchStatus(0)"><i class="fa fa-pause"></i>&nbsp;批量关闭</button>
  <button class="btn btn-danger" onclick="batchDelete()"><i class="fa fa-trash"></i>&nbsp;批量删除</button>
</div>

<div class="panel panel-default">
  <div class="table-responsive">
    <table class="table table-striped b-t b-light" style="margin-bottom:0;">
      <thead>
        <tr>
          <th style="width:36px;"><input type="checkbox" id="checkAll"></th>
          <th style="width:50px;">ID</th>
          <th style="width:100px;">类型</th>
          <th style="width:70px;">预览</th>
          <th>备注</th>
          <th style="width:180px;">限额配置</th>
          <th style="width:110px;">今日</th>
          <th style="width:60px;">状态</th>
          <th style="width:140px;">操作</th>
        </tr>
      </thead>
      <tbody>
      <?php if(empty($list)): ?>
        <tr><td colspan="9" style="text-align:center;padding:60px 0;color:#999;">
          <i class="fa fa-inbox" style="font-size:40px;display:block;margin-bottom:12px;color:#ddd;"></i>
          暂无收款方式
        </td></tr>
      <?php else: foreach($list as $qr):
        $def = $PAY_TYPES[$qr['channel']] ?? ['label'=>$qr['channel'],'platform'=>'other','category'=>'qrcode'];
        $label = $def['label'];
        $platform = $def['platform'];
        $cat = $qr['category'] ?: $def['category'];
        $badgeClass = $platform=='wechat' ? 'label-success' : ($platform=='alipay' ? 'label-info' : 'label-primary');
        $todayCount = ($qr['today_date'] == date('Y-m-d')) ? intval($qr['today_count']) : 0;
        $todayAmount = ($qr['today_date'] == date('Y-m-d')) ? floatval($qr['today_amount']) : 0;
      ?>
        <tr>
          <td><input type="checkbox" class="check-item" value="<?php echo $qr['id'];?>"></td>
          <td><?php echo $qr['id']; ?></td>
          <td><span class="label <?php echo $badgeClass; ?>"><?php echo $label; ?></span></td>
          <td>
            <?php if($cat=='qrcode' && $qr['qrcode_img']): ?>
              <img src="/<?php echo $qr['qrcode_img']; ?>" style="max-width:50px;max-height:50px;border-radius:4px;border:1px solid #eee;cursor:pointer;" onclick="previewImg('/<?php echo $qr['qrcode_img'];?>')">
            <?php elseif($cat=='account'): ?><span style="color:#faad14;">📋账号</span>
            <?php elseif($cat=='token'): ?><span style="color:#faad14;">🔑口令</span>
            <?php else: ?><span style="color:#ccc;">-</span><?php endif; ?>
          </td>
          <td style="font-size:12px;"><?php echo htmlspecialchars($qr['remark']); ?></td>
          <td style="font-size:11px;line-height:1.6;color:#666;">
            <b>单笔：</b>
            <?php
              $min = floatval($qr['min_amount']);
              $max = floatval($qr['max_amount']);
              if($min <= 0 && $max <= 0) echo '不限';
              elseif($min > 0 && $max > 0) echo "¥{$min} ~ ¥{$max}";
              elseif($min > 0) echo "≥ ¥{$min}";
              else echo "≤ ¥{$max}";
            ?><br>
            <b>浮动：</b><?php echo floatval($qr['float_rate']) > 0 ? '±'.floatval($qr['float_rate']).'%' : '无'; ?><br>
            <b>日笔数：</b><?php echo intval($qr['day_limit']) > 0 ? intval($qr['day_limit']).' 笔' : '不限'; ?><br>
            <b>日金额：</b><?php echo floatval($qr['day_max_amount']) > 0 ? '¥'.floatval($qr['day_max_amount']) : '不限'; ?>
          </td>
          <td style="font-size:11px;color:#666;">
            <b style="color:#1677ff;"><?php echo $todayCount; ?></b> 笔<br>
            <b style="color:#1677ff;">¥<?php echo number_format($todayAmount, 2); ?></b>
          </td>
          <td>
            <input type="checkbox" class="switch" <?php echo $qr['status']?'checked':'';?> onchange="toggleQr(<?php echo $qr['id']; ?>)">
          </td>
          <td>
            <a href="javascript:;" onclick="editQr(<?php echo $qr['id'];?>)" style="color:#1677ff;">编辑</a>
            <span style="color:#ddd;">|</span>
            <a href="javascript:;" onclick="resetToday(<?php echo $qr['id'];?>)" style="color:#faad14;">重置</a>
            <span style="color:#ddd;">|</span>
            <a href="javascript:;" onclick="delQr(<?php echo $qr['id']; ?>)" style="color:#e74c3c;">删除</a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if(!empty($list)): ?>
  <div class="panel-footer">
    <span style="color:#999;font-size:12px;">共 <?php echo count($list); ?> 条，按使用次数自动轮询</span>
  </div>
  <?php endif; ?>
</div>

<div class="panel panel-default">
  <div class="panel-heading font-bold"><i class="fa fa-info-circle"></i>&nbsp;说明</div>
  <div class="panel-body" style="color:#666;font-size:13px;line-height:1.8;">
    <p>1. <b>扫码类</b>（赞赏码/个人码/经营码/收款单/名片/AA/小荷包/聚合码）→ 上传二维码图片。</p>
    <p>2. <b>账号类</b>（银行/手机号/支付宝账号）→ 填写收款账号。</p>
    <p>3. <b>口令类</b>（支付宝口令）→ 填写口令文本。</p>
    <p>4. <b>限额配置</b>：单笔金额范围 / 金额浮动 / 每日笔数 / 每日金额。超过限制时系统会跳过该码，使用下一个可用码。</p>
    <p>5. <b>今日统计</b>：显示今日已用笔数和金额，每日 0 点自动重置。</p>
  </div>
</div>

</div>
    </div>
  </div>

<!-- ========== 上传/编辑弹窗 ========== -->
<div class="modal fade" id="uploadModal" tabindex="-1">
  <div class="modal-dialog" style="max-width:560px;">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title" id="modalTitle"><i class="fa fa-upload"></i> 添加收款方式</h4>
      </div>
      <div class="modal-body">
        <input type="hidden" id="edit_id" value="0">

        <!-- 平台 Tab（编辑时隐藏） -->
        <div class="form-group" id="platformGroup">
          <label>平台 <span style="color:red;">*</span></label>
          <div class="platform-tabs" style="display:flex;gap:12px;">
            <button type="button" class="ptab ptab-wechat" data-platform="wechat" onclick="switchPlatform('wechat')" style="flex:1; padding:15px 10px; border-radius:12px; border:2px solid #e2e8f0; background:#fff; display:flex; flex-direction:column; align-items:center; gap:8px;">
                <div style="width:40px;height:40px;background:#07c160;border-radius:10px;color:#fff;font-size:24px;font-weight:800;display:flex;align-items:center;justify-content:center;">微</div>
                <span style="font-size:14px; font-weight:700; color:#334155;">微信</span>
            </button>
            <button type="button" class="ptab ptab-alipay" data-platform="alipay" onclick="switchPlatform('alipay')" style="flex:1; padding:15px 10px; border-radius:12px; border:2px solid #e2e8f0; background:#fff; display:flex; flex-direction:column; align-items:center; gap:8px;">
                <div style="width:40px;height:40px;background:#1677ff;border-radius:10px;color:#fff;font-size:24px;font-weight:800;display:flex;align-items:center;justify-content:center;">支</div>
                <span style="font-size:14px; font-weight:700; color:#334155;">支付宝</span>
            </button>
            <button type="button" class="ptab ptab-agg" data-platform="aggregate" onclick="switchPlatform('aggregate')" style="flex:1; padding:15px 10px; border-radius:12px; border:2px solid #e2e8f0; background:#fff; display:flex; flex-direction:column; align-items:center; gap:8px;">
                <div style="width:40px;height:40px;background:#a855f7;border-radius:10px;color:#fff;font-size:24px;font-weight:800;display:flex;align-items:center;justify-content:center;">合</div>
                <span style="font-size:14px; font-weight:700; color:#334155;">聚合码</span>
            </button>
          </div>
        </div>

        <div class="form-group" id="channelGroup">
          <label>收款码模式<span style="color:red;">*</span></label>
          <select id="channel" class="form-control" onchange="onChannelChange()"></select>
        </div>

        <!-- 各类型获取提示 -->
        <div id="channelHint" style="display:none;background:#f0f7ff;border-left:3px solid #1677ff;padding:10px 12px;border-radius:6px;margin-bottom:12px;font-size:13px;line-height:1.7;">
          <div style="font-weight:700;color:#1677ff;margin-bottom:4px;"><i class="fa fa-lightbulb-o"></i> 从哪里获取</div>
          <div id="channelHintPath" style="color:#333;"></div>
          <div id="channelHintTip" style="color:#e67e22;margin-top:6px;font-size:12px;"></div>
        </div>

        <div class="form-group">
          <label>备注（商户识别该二维码所属账户）</label>
          <input id="remark" class="form-control" placeholder="如：张三-微信经营码">
        </div>

        <div class="form-group">
          <label>telegram账户（建议有订单就@该账号）</label>
          <input id="operator_name" class="form-control" placeholder="如：@BotFather">
          <small style="color:#86909c;font-size:12px;">有订单时机器人会 @ 该账号，留空则不 @</small>
        </div>

        <div class="form-group">
          <label>推送目标（群 或 个人）</label>
          <input id="group_id" class="form-control" placeholder="群填 -100xxxxxxx / 个人填 7xxxxxxxxx（留空走默认）">
          <small style="color:#86909c;font-size:12px;">
            📌 <b>负数开头</b> = TG 群（限速 20 条/分钟）<br>
            📌 <b>正数开头</b> = 个人私聊（限速 60 条/分钟，容量更大）
          </small>
        </div>

        <div class="form-group">
          <label>操作人 TG 数字 ID</label>
          <input id="operator_tg" class="form-control" placeholder="如 7811967320（留空则不绑定）">

          <div class="form-group" id="mail_to_input">
            <label>邮件推送邮箱（选填）</label>
            <input id="mail_to" class="form-control" placeholder="如 boss@company.com">
            <div class="alert alert-warning" style="padding:8px 12px;font-size:12px;margin-top:8px;margin-bottom:6px;border-left:3px solid #ff9500;background:#fff7e6;color:#8a5a00;border-radius:4px;">
              ⚠️ <b>建议只填写 1 个邮箱。</b>多个邮箱时，任一邮箱点【确认到账】后，其余邮箱的链接会立即失效。<br>
              📌 <b>请务必确认该邮箱能正常接收邮件</b>（首次使用请检查垃圾邮件箱，把发件人加白名单）。
            </div>
          </div>

          <div class="form-group" id="region_input">
            <label>归属地区（选填）</label>
            <div style="display:flex;gap:8px;">
              <select id="region_province" class="form-control" style="flex:1;" >
                <option value="">-- 省 / 直辖市 --</option>
                <option value="北京">北京</option>
                <option value="天津">天津</option>
                <option value="河北">河北</option>
                <option value="山西">山西</option>
                <option value="内蒙古">内蒙古</option>
                <option value="辽宁">辽宁</option>
                <option value="吉林">吉林</option>
                <option value="黑龙江">黑龙江</option>
                <option value="上海">上海</option>
                <option value="江苏">江苏</option>
                <option value="浙江">浙江</option>
                <option value="安徽">安徽</option>
                <option value="福建">福建</option>
                <option value="江西">江西</option>
                <option value="山东">山东</option>
                <option value="河南">河南</option>
                <option value="湖北">湖北</option>
                <option value="湖南">湖南</option>
                <option value="广东">广东</option>
                <option value="广西">广西</option>
                <option value="海南">海南</option>
                <option value="重庆">重庆</option>
                <option value="四川">四川</option>
                <option value="贵州">贵州</option>
                <option value="云南">云南</option>
                <option value="西藏">西藏</option>
                <option value="陕西">陕西</option>
                <option value="甘肃">甘肃</option>
                <option value="青海">青海</option>
                <option value="宁夏">宁夏</option>
                <option value="新疆">新疆</option>
                <option value="香港">香港</option>
                <option value="澳门">澳门</option>
                <option value="台湾">台湾</option>
              </select>
              <input id="region_city" class="form-control" style="flex:1;" placeholder="市 / 区（选填）">
            </div>
            <small style="color:#86909c;font-size:12px;">填写收款码归属地区，用于智能路由分配（选填，留空则不参与地区匹配）。</small>
          <div class="form-group" id="bindip_input">
            <label>绑定 IP（选填）</label>
            <div style="display:flex;gap:8px;">
              <input id="bind_ip" class="form-control" style="flex:1;" placeholder="如 113.108.182.1">
              <button type="button" class="btn btn-default" onclick="fillMyIp()" style="white-space:nowrap;">🔍 用我的 IP</button>
            </div>
            <small style="color:#86909c;font-size:12px;">⚠️ <b style="color:#e63946;">开启了 VPN 或代理的请手动输入真实 IP</b>（自动获取的可能是代理 IP）。<br>填入后系统会自动识别该 IP 的归属地，只有对应地区的客户才能匹配到此码。<a href="./myip.php" target="_blank">打开 IP 检测工具</a></small>
            <div id="bind_ip_tip" style="display:none;margin-top:6px;padding:8px 12px;background:#f0fdf4;border-radius:6px;font-size:12px;color:#166534;border-left:3px solid #16a34a;line-height:1.6;"></div>
          </div>
          </div>
          <small style="color:#86909c;font-size:12px;">只有该 ID 能在群里点【确认到账】</small>
        </div>

        <!-- 扫码类 -->
        <div id="formQrcode" style="display:none;">
          <div class="form-group">
            <label>二维码图片 <span style="color:red;">*</span></label>
            <input type="file" id="file" accept="image/*" class="form-control">
            <div id="preview-wrap" style="margin-top:10px;text-align:center;"></div>
            <div id="result" style="padding:10px;background:#f5f5f5;border-radius:6px;margin-top:10px;font-size:13px;color:#666;">请选择一张收款码图片</div>
          </div>
        </div>

        <!-- 账号类 -->
        <div id="formAccount" style="display:none;">
          <div class="form-group">
            <label>收款账号 <span style="color:red;">*</span></label>
            <input id="pay_account" class="form-control" placeholder="手机号 / 支付宝账号">
          </div>
          <div class="form-group" id="formBankGroup" style="display:none;">
            <label>开户银行</label>
            <input id="pay_bank" class="form-control" placeholder="如：中国建设银行">
          </div>
          <div class="form-group">
            <label>户名 / 收款人</label>
            <input id="pay_name" class="form-control" placeholder="如：张三">
          </div>
        </div>

        <!-- 口令类 -->
        <div id="formToken" style="display:none;">
          <div class="form-group">
            <label>口令内容 <span style="color:red;">*</span></label>
            <textarea id="pay_extra" class="form-control" rows="3" placeholder="粘贴支付宝口令"></textarea>
          </div>
        </div>

        <!-- ========== 限额配置 ========== -->
        <div style="background:#f7f9fc;border-radius:10px;padding:14px;border:1px solid #eef2f7;margin-top:14px;">
          <div style="font-weight:700;color:#1f2d3d;font-size:13px;margin-bottom:12px;">
            💰 限额配置（留空或 0 = 不限制）
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
            <div>
              <label style="font-size:12px;color:#666;">单笔最低金额（元）</label>
              <input id="min_amount" type="number" step="0.01" min="0" class="form-control" placeholder="如 1">
            </div>
            <div>
              <label style="font-size:12px;color:#666;">单笔最高金额（元）</label>
              <input id="max_amount" type="number" step="0.01" min="0" class="form-control" placeholder="如 5000">
            </div>
          </div>

          <div style="margin-top:10px;">
            <label style="font-size:12px;color:#666;">金额浮动比例（%）</label>
            <input id="float_rate" type="number" step="0.01" min="0" max="100" class="form-control" placeholder="如 5 表示 ±5%，0 表示不浮动">
            <div style="font-size:11px;color:#999;margin-top:4px;">例：订单 100 元，浮动 5% → 实际展示 95~105 元</div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px;">
            <div>
              <label style="font-size:12px;color:#666;">每日笔数限制</label>
              <input id="day_limit" type="number" min="0" class="form-control" placeholder="如 50">
            </div>
            <div>
              <label style="font-size:12px;color:#666;">每日金额限制（元）</label>
              <input id="day_max_amount" type="number" step="0.01" min="0" class="form-control" placeholder="如 10000">
            </div>
          </div>
        </div>

      </div>
      <div class="modal-footer">
        <button class="btn btn-default" data-dismiss="modal">取消</button>
        <button class="btn btn-primary" onclick="submitQr()"><i class="fa fa-check"></i> 提交</button>
      </div>
    </div>
  </div>
</div>

<style>
.user-qrcode-page { color: #1f2937; }
.user-qrcode-page .bg-light.lter { background: #fff !important; border-bottom: 1px solid #e6ebf2 !important; }
.user-qrcode-page .bg-light.lter h1 { color: #001529 !important; font-weight: 700 !important; }
.user-qrcode-page .panel { border-radius: 10px !important; border: 1px solid #e6ebf2 !important; box-shadow: 0 2px 12px rgba(15,23,42,.05) !important; overflow: hidden; }
.user-qrcode-page .table > thead > tr > th { background: linear-gradient(180deg, #f0f5ff 0%, #eaf2ff 100%) !important; color: #0f172a !important; border-bottom: 2px solid #b9d6ff !important; font-weight: 700 !important; font-size: 12px !important; padding: 12px 10px !important; }
.user-qrcode-page .table > tbody > tr > td { padding: 10px !important; border-top: 1px solid #eef2f7 !important; font-size: 13px; vertical-align: middle; }
.user-qrcode-page .label { font-size: 11px !important; font-weight: 600 !important; padding: 4px 8px !important; border-radius: 999px !important; }
.user-qrcode-page .label-info { background: #1677ff !important; color: #fff !important; }
.user-qrcode-page .label-success { background: #27ae60 !important; color: #fff !important; }
.user-qrcode-page .label-primary { background: linear-gradient(135deg,#1677ff,#0d5cd6) !important; color: #fff !important; }
.user-qrcode-page .switch { width: 40px; height: 20px; appearance: none; background: #dfe7f1; border-radius: 999px; position: relative; cursor: pointer; border: none; }
.user-qrcode-page .switch::after { content: ""; position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; border-radius: 50%; background: #fff; transition: all .25s; }
.user-qrcode-page .switch:checked { background: #52c41a; }
.user-qrcode-page .switch:checked::after { left: 22px; }

.ptab { flex: 1; padding: 12px 6px; background: #fff; border: 2px solid #eef0f3; border-radius: 10px; cursor: pointer; transition: .15s; display: flex; flex-direction: column; align-items: center; gap: 4px; font-size: 13px; font-weight: 600; color: #666; font-family: inherit; }
.ptab:hover { border-color: #1677ff; }
.ptab.active { border-color: #1677ff; background: #f0f6ff; color: #1677ff; }
.ptab-logo { display: inline-flex; width: 30px; height: 30px; border-radius: 8px; align-items: center; justify-content: center; color: #fff; font-size: 15px; font-weight: 700; }
.ptab-logo.wechat { background: linear-gradient(135deg,#33d17a,#07c160); }
.ptab-logo.alipay { background: linear-gradient(135deg,#3B93FF,#1677FF); }
.ptab-logo.agg { background: linear-gradient(135deg,#a855f7,#7c3aed); }

#preview-wrap { max-height: 260px; overflow: hidden; text-align: center; margin-top: 10px; }
#preview-wrap img, #preview { display: inline-block !important; max-width: 200px !important; max-height: 200px !important; width: 200px !important; height: 200px !important; object-fit: contain !important; border: 1px solid #e6ebf2 !important; border-radius: 8px !important; background: #fff !important; }
#uploadModal .modal-content { border-radius: 10px !important; border: none !important; }
#uploadModal .modal-body .form-control { border-radius: 6px !important; border: 1px solid #dfe7f1 !important; height: 38px; box-shadow: none !important; }
#uploadModal .modal-body textarea.form-control { height: auto; }
#uploadModal .modal-body .form-control:focus { border-color: #1677ff !important; box-shadow: 0 0 0 3px rgba(22,119,255,.08) !important; }
#uploadModal .modal-body label { color: #334155 !important; font-weight: 600 !important; font-size: 13px; }
#uploadModal .btn-primary { background: linear-gradient(135deg, #40a9ff 0%, #1677ff 100%) !important; border-color: #1677ff !important; }
#uploadModal #result { color: #475569 !important; }
#uploadModal #result.success { background: #eaf9ee !important; color: #156d3d !important; }
#uploadModal #result.error { background: #fdf0f0 !important; color: #b42318 !important; }

.ptab.active { border-color: #1677ff !important; background: #f0f7ff !important; box-shadow: 0 4px 12px rgba(22,119,255,0.1); }
</style>

<script src="/assets/js/jsQR.min.js"></script>
<script src="/assets/vendor/jquery/3.4.1/jquery.min.js"></script>
<script src="/assets/vendor/twitter-bootstrap/3.4.1/js/bootstrap.min.js"></script>
<script>
var PAY_TYPES = <?php echo json_encode($PAY_TYPES, JSON_UNESCAPED_UNICODE); ?>;
var PAY_HINTS = <?php echo $HINTS_JSON; ?>;
var currentPlatform = 'wechat';
var qrContent = '';
var imgData = '';
var uploadMode = 1;
var isEdit = false;

function switchPlatform(platform) {
  currentPlatform = platform;
  document.querySelectorAll('.ptab').forEach(function(t){ t.classList.toggle('active', t.getAttribute('data-platform') === platform); });
  var sel = document.getElementById('channel');
  sel.innerHTML = '';
  for (var k in PAY_TYPES) {
    if (PAY_TYPES[k].platform === platform) {
      var opt = document.createElement('option');
      opt.value = k;
      opt.textContent = PAY_TYPES[k].label;
      sel.appendChild(opt);
    }
  }
  onChannelChange();
}

function onChannelChange() {
  var ch = document.getElementById('channel').value;
  var info = PAY_TYPES[ch] || {};
  var cat = info.category || 'qrcode';

  // 显示获取指引
  var hintBox  = document.getElementById('channelHint');
  var hintPath = document.getElementById('channelHintPath');
  var hintTip  = document.getElementById('channelHintTip');
  if (hintBox && ch && PAY_HINTS[ch]) {
    var h = PAY_HINTS[ch];
    hintPath.innerHTML = '<b>步骤：</b>' + h[0];
    hintTip.innerHTML  = h[1] ? '⚠ ' + h[1] : '';
    hintBox.style.display = 'block';
  } else if (hintBox) {
    hintBox.style.display = 'none';
  }
  document.getElementById('formQrcode').style.display = (cat === 'qrcode' && !isEdit) ? 'block' : 'none';
  document.getElementById('formAccount').style.display = (cat === 'account' && !isEdit) ? 'block' : 'none';
  document.getElementById('formToken').style.display = (cat === 'token' && !isEdit) ? 'block' : 'none';
  var bankGroup = document.getElementById('formBankGroup');
  if (bankGroup) bankGroup.style.display = (ch === 'wechat_bank') ? 'block' : 'none';
}

function openUpload() {
  isEdit = false;
  qrContent = ''; imgData = '';
  document.getElementById('edit_id').value = 0;
  document.getElementById('file').value = '';
  document.getElementById('remark').value = '';
  document.getElementById('pay_account').value = '';
  document.getElementById('pay_name').value = '';
  document.getElementById('pay_bank').value = '';
  document.getElementById('pay_extra').value = '';
  document.getElementById('float_rate').value = '';
  document.getElementById('min_amount').value = '';
  document.getElementById('max_amount').value = '';
  document.getElementById('day_limit').value = '';
  document.getElementById('day_max_amount').value = '';
  document.getElementById('preview-wrap').innerHTML = '';
  document.getElementById('result').className = '';
  document.getElementById('result').innerHTML = '请选择一张收款码图片';
  document.getElementById('modalTitle').innerHTML = '<i class="fa fa-upload"></i> 添加收款方式';
  document.getElementById('platformGroup').style.display = 'block';
  document.getElementById('channelGroup').style.display = 'block';
  switchPlatform('wechat');
  if (window.jQuery) jQuery('#uploadModal').modal('show');
}

function editQr(id) {
  fetch('../ajax2.php?act=qrcodeDetail&id='+id).then(function(r){return r.json();}).then(function(d){
    if(d.code !== 0){ alert(d.msg); return; }
    var q = d.data;
    isEdit = true;
    document.getElementById('edit_id').value = q.id;
    document.getElementById('remark').value = q.remark || '';
    document.getElementById('pay_account').value = q.pay_account || '';
    document.getElementById('pay_name').value = q.pay_name || '';
    document.getElementById('pay_bank').value = q.pay_bank || '';
    document.getElementById('pay_extra').value = q.pay_extra || '';
    document.getElementById('operator_name').value = q.operator_name || '';
    document.getElementById('group_id').value = q.group_id || '';
    document.getElementById('operator_tg').value = q.operator_tg || '';
  if(document.getElementById('mail_to'))document.getElementById('mail_to').value = q.mail_to || '';
    if(document.getElementById('region_province'))document.getElementById('region_province').value = q.region_province || '';
    if(document.getElementById('bind_ip'))document.getElementById('bind_ip').value = q.bind_ip || '';
    if(document.getElementById('bind_ip'))document.getElementById('bind_ip').value = q.bind_ip || '';
    if(document.getElementById('region_city'))document.getElementById('region_city').value = q.region_city || '';
    document.getElementById('float_rate').value = parseFloat(q.float_rate) || '';
    document.getElementById('min_amount').value = parseFloat(q.min_amount) || '';
    document.getElementById('max_amount').value = parseFloat(q.max_amount) || '';
    document.getElementById('day_limit').value = parseInt(q.day_limit) || '';
    document.getElementById('day_max_amount').value = parseFloat(q.day_max_amount) || '';
    document.getElementById('modalTitle').innerHTML = '<i class="fa fa-pencil"></i> 编辑收款方式';
    document.getElementById('platformGroup').style.display = 'none';
    document.getElementById('channelGroup').style.display = 'none';
    document.getElementById('formQrcode').style.display = 'none';
    document.getElementById('formAccount').style.display = (q.category === 'account') ? 'block' : 'none';
    document.getElementById('formToken').style.display = (q.category === 'token') ? 'block' : 'none';
    var bankGroup = document.getElementById('formBankGroup');
    if (bankGroup) bankGroup.style.display = (q.channel === 'wechat_bank') ? 'block' : 'none';
    jQuery('#uploadModal').modal('show');
  });
}

document.getElementById('file').onchange = function(e) {
  var f = e.target.files[0]; if(!f) return;
  var reader = new FileReader();
  reader.onload = function(ev) {
    imgData = ev.target.result;
    document.getElementById('preview-wrap').innerHTML = '<img id="preview" src="'+imgData+'">';
    processFile();
  };
  reader.readAsDataURL(f);
};

function processFile() {
  var r = document.getElementById('result');
  r.className = '';
  if (!imgData) return;
  var ch = document.getElementById('channel').value;
  var isAgg = (ch.indexOf('aggregate') === 0);
  if (uploadMode === 2 || isAgg) {
    qrContent = '__RAW__' + Date.now();
    r.className = 'success';
    r.innerHTML = '✅ 原图已选择';
    return;
  }
  r.innerHTML = '正在识别…';
  var img = new Image();
  img.onload = function() {
    var c = document.createElement('canvas');
    c.width = img.width; c.height = img.height;
    var ctx = c.getContext('2d');
    ctx.drawImage(img, 0, 0);
    var d = ctx.getImageData(0, 0, c.width, c.height);
    var res = (typeof jsQR !== 'undefined') ? jsQR(d.data, c.width, c.height) : null;
    if(res && res.data) {
      qrContent = res.data;
      r.className = 'success';
      r.innerHTML = '✅ 识别成功：<br><b>'+qrContent+'</b>';
    } else {
      qrContent = '__RAW__' + Date.now();
      r.className = 'success';
      r.innerHTML = '⚠️ 未识别出内容，将以原图方式保存';
    }
  };
  img.src = imgData;
}



function fillMyIp(){
  var btn = event.target;
  var tip = document.getElementById('bind_ip_tip');
  var inp = document.getElementById('bind_ip');
  if (!inp) return alert('缺少 bind_ip 输入框');
  btn.disabled = true; btn.textContent = '⏳ 查询中';
  if (tip) { tip.style.display = 'block'; tip.innerHTML = '查询中...'; }
  fetch('../myip.php?act=query&ip=me').then(r=>r.json()).then(d=>{
    btn.disabled = false; btn.textContent = '🔍 用我的 IP';
    if (d.code === 0 && d.ip) {
      inp.value = d.ip;
      var sel = document.getElementById('region_province');
      var cityInput = document.getElementById('region_city');
      var autoMsg = '';
      if (sel && d.province) {
        var matched = false;
        for (var i=0; i<sel.options.length; i++) {
          if (sel.options[i].value === d.province) { sel.selectedIndex = i; matched = true; break; }
        }
        autoMsg = matched ? '（已自动选省：' + d.province + '）' : '（省份「' + d.province + '」不在列表，可手动选）';
      }
      if (cityInput && d.city && !cityInput.value) cityInput.value = d.city;
      if (tip) {
        tip.style.display = 'block';
        tip.innerHTML = '✅ 已填入：<b>' + d.ip + '</b> · ' + (d.province || '未识别') + ' ' + (d.city || '') + ' · ' + (d.isp || '') + autoMsg + '<br><span style="color:#94a3b8;">保存后系统会自动识别此 IP 的归属地省份</span>';
      }
    } else {
      if (tip) { tip.style.display = 'block'; tip.innerHTML = '❌ 查询失败：' + (d.msg || '未知错误'); }
    }
  }).catch(e=>{
    btn.disabled = false; btn.textContent = '🔍 用我的 IP';
    if (tip) { tip.style.display = 'block'; tip.innerHTML = '❌ 网络错误：' + e; }
  });
}
function submitQr() {
  var isEditMode = parseInt(document.getElementById('edit_id').value) > 0;
  var fd = new FormData();

  if (isEditMode) {
    fd.append('id', document.getElementById('edit_id').value);
    fd.append('remark', document.getElementById('remark').value);
    fd.append('float_rate', document.getElementById('float_rate').value || 0);
    fd.append('min_amount', document.getElementById('min_amount').value || 0);
    fd.append('max_amount', document.getElementById('max_amount').value || 0);
    fd.append('day_limit', document.getElementById('day_limit').value || 0);
    fd.append('day_max_amount', document.getElementById('day_max_amount').value || 0);
    fd.append('pay_account', document.getElementById('pay_account').value.trim());
    fd.append('pay_name', document.getElementById('pay_name').value.trim());
    fd.append('pay_bank', document.getElementById('pay_bank').value.trim());
    fd.append('pay_extra', document.getElementById('pay_extra').value.trim());
    fd.append('operator_name', document.getElementById('operator_name').value.trim());
    fd.append('group_id', document.getElementById('group_id').value.trim());
    fd.append('operator_tg', document.getElementById('operator_tg').value.trim());
  fd.append('mail_to', (document.getElementById('mail_to')||{value:''}).value.trim());
  fd.append('region_province', (document.getElementById('region_province')||{value:''}).value.trim());
  fd.append('bind_ip', (document.getElementById('bind_ip')||{value:''}).value.trim());
  fd.append('bind_ip', (document.getElementById('bind_ip')||{value:''}).value.trim());
  fd.append('region_city', (document.getElementById('region_city')||{value:''}).value.trim());

    fetch('?my=update', {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(d){
      alert(d.msg); if(d.code===0) location.reload();
    });
    return;
  }

  var ch = document.getElementById('channel').value;
  if (!ch || !PAY_TYPES[ch]) { alert('请选择收款方式'); return; }
  var cat = PAY_TYPES[ch].category;

  fd.append('channel', ch);
  fd.append('remark', document.getElementById('remark').value);
  fd.append('operator_name', document.getElementById('operator_name').value.trim());
  fd.append('group_id', document.getElementById('group_id').value.trim());
  fd.append('operator_tg', document.getElementById('operator_tg').value.trim());
  fd.append('float_rate', document.getElementById('float_rate').value || 0);
  fd.append('min_amount', document.getElementById('min_amount').value || 0);
  fd.append('max_amount', document.getElementById('max_amount').value || 0);
  fd.append('day_limit', document.getElementById('day_limit').value || 0);
  fd.append('day_max_amount', document.getElementById('day_max_amount').value || 0);

  if (cat === 'qrcode') {
    if (!imgData) { alert('请上传二维码图片'); return; }
    if (!qrContent) qrContent = '__RAW__' + Date.now();
    fd.append('qrcode_content', qrContent);
    fd.append('img_data', imgData);
  } else if (cat === 'account') {
    var acc = document.getElementById('pay_account').value.trim();
    if (!acc) { alert('请填写收款账号'); return; }
    fd.append('pay_account', acc);
    fd.append('pay_name', document.getElementById('pay_name').value.trim());
    fd.append('pay_bank', document.getElementById('pay_bank').value.trim());
  } else if (cat === 'token') {
    var tok = document.getElementById('pay_extra').value.trim();
    if (!tok) { alert('请填写口令内容'); return; }
    fd.append('pay_extra', tok);
  }

  fetch('?my=save', {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(d){
    alert(d.msg); if(d.code===0) location.reload();
  });
}

document.getElementById('checkAll') && (document.getElementById('checkAll').onchange = function() {
  document.querySelectorAll('.check-item').forEach(function(c){ c.checked = this.checked; }.bind(this));
});

function getCheckedIds() {
  return Array.prototype.slice.call(document.querySelectorAll('.check-item:checked')).map(function(c){ return c.value; }).join(',');
}

function toggleQr(id) {
  var fd = new FormData(); fd.append('id', id);
  fetch('?my=toggle', {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(){location.reload();});
}

function delQr(id) {
  if(!confirm('确定删除该收款方式？')) return;
  var fd = new FormData(); fd.append('id', id);
  fetch('?my=delete', {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(){location.reload();});
}

function resetToday(id) {
  if(!confirm('确定重置该码的今日统计？')) return;
  var fd = new FormData(); fd.append('id', id);
  fetch('?my=reset_today', {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(d){
    alert(d.msg); location.reload();
  });
}

function batchDelete() {
  var ids = getCheckedIds();
  if(!ids) { alert('请先勾选'); return; }
  if(!confirm('确定删除选中的 '+ids.split(',').length+' 条？')) return;
  var fd = new FormData(); fd.append('ids', ids);
  fetch('?my=batch_delete', {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(d){
    alert(d.msg); if(d.code===0) location.reload();
  });
}

function batchStatus(s) {
  var ids = getCheckedIds();
  if(!ids) { alert('请先勾选'); return; }
  var fd = new FormData(); fd.append('ids', ids); fd.append('status', s);
  fetch('?my=batch_status', {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(d){
    alert(d.msg); if(d.code===0) location.reload();
  });
}

function previewImg(url) { window.open(url, '_blank'); }
function showContent(c) { alert('二维码内容：\n\n' + c); }
</script>
<?php include('../foot.php'); ?>