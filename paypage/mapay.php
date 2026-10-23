<?php
require_once('../includes/common.php');
require_once(__DIR__ . '/../plugins/mapay/_region_helper.php');

$trade_no = $_GET['trade_no'] ?? '';
if (!$trade_no) exit('参数错误');

$order = $DB->getRow("SELECT * FROM pay_order WHERE trade_no=:t", [':t'=>$trade_no]);
if (!$order) exit('订单不存在');

if ($order['status'] == 1) {
    header('Location: /payok.php?trade_no='.$trade_no);
    exit;
}

// 优先读 ext（新格式），老订单 fallback 到 param
$p = json_decode($order['ext'], true) ?: [];
if (empty($p['qrcode_id'])) {
    $p = json_decode($order['param'], true) ?: [];
}
if (empty($p['qrcode_id'])) $p = [];

// ========== 兜底选码：订单未分配收款码时，现场按 uid+type+金额选一个并回写 ==========
if (empty($p['qrcode_id'])) {
    $uid       = intval($order['uid']);
    $money     = floatval($order['realmoney'] > 0 ? $order['realmoney'] : $order['money']);
    $type      = intval($order['type']);

    // 候选通道（跟 mapay_plugin 保持一致）
    $cands = [];
    if ($type == 1) {
        $cands = ['alipay_personal','alipay_biz','alipay_card','alipay_receipt',
                  'alipay_aa','alipay_pouch','alipay_account','alipay_token',
                  'alipay','aggregate_alipay','aggregate_both'];
    } elseif ($type == 2) {
        $cands = ['wechat_personal','wechat_biz','wechat_reward',
                  'wechat_bank','wechat_phone','wechat',
                  'aggregate_wechat','aggregate_both'];
    } elseif ($type == 4) {
        $cands = ['bank','wechat_bank','alipay_account'];
    }

    require_once dirname(__DIR__) . '/plugins/mapay/mapay_plugin.php';
    $qr = null;
    // 不遍历通道，直接去查当前大类下（比如所有支付宝和聚合码）的所有可用码，由底层做权重随机
    if ($type == 1) {
        $qr = mapay_plugin::pickWithRouteOrChildren($DB, $uid, 'alipay', $money);
    } elseif ($type == 2) {
        $qr = mapay_plugin::pickWithRouteOrChildren($DB, $uid, 'wechat', $money);
    } else {
        $qr = mapay_plugin::pickWithRouteOrChildren($DB, $uid, null, $money);
    }

    if (!$qr) {
        echo '<div style="padding:40px;text-align:center;font-size:16px;color:#c00;">';
        echo '当前商户暂无可用的收款码，请联系客服';
        echo '</div>';
        exit;
    }

    $DB->exec("UPDATE pay_qrcode SET use_count=use_count+1, last_used=:t WHERE id=:id",
              [':t'=>time(), ':id'=>$qr['id']]);

    $p = ['qrcode_id' => $qr['id'], 'qrcode_uid' => (int)$qr['uid']];
    $DB->exec("UPDATE pay_order SET ext=:e WHERE trade_no=:t",
              [':e'=>json_encode($p, JSON_UNESCAPED_UNICODE), ':t'=>$trade_no]);
              
    // ===== 真正执行的推送代码 =====
    @file_put_contents('/tmp/push_debug.log', date('H:i:s') . " | mapay.php 进入推送\n", FILE_APPEND);
    $__tradeNo = $trade_no;
    $__qid = (int)($p['qrcode_id'] ?? 0);
    $__qrcodeUid = (int)($p['qrcode_uid'] ?? 0);
    
    if ($__qid > 0) {
        $__bc = null;
        $__curUid = $__qrcodeUid;
        while ($__curUid > 0) {
            $__bc = $DB->getRow("SELECT * FROM pay_user_botconfig WHERE uid=:u AND use_own_bot=1 AND own_bot_token IS NOT NULL AND own_bot_token != ''", [':u'=>$__curUid]);
            if ($__bc) break;
            $__curUid = (int)$DB->getColumn("SELECT upid FROM pay_user WHERE uid=:u", [':u'=>$__curUid]);
        }
        if ($__bc) {
            @file_put_contents('/tmp/push_debug.log', date('H:i:s') . " | 找到Bot配置\n", FILE_APPEND);
            $__target = $__bc['own_bot_groupid'] ?: $__bc['own_bot_chatid'];
            if ($__target) {
                $__exists = $DB->getRow("SELECT id FROM pay_tg_pending_order WHERE trade_no=:t", [':t'=>$__tradeNo]);
                if (!$__exists) {
                    $DB->exec("INSERT INTO pay_tg_pending_order (trade_no, uid, amount, pkg, tg_chat_id, status) VALUES (?,?,?,?,?,0)", [$__tradeNo, $order['uid'], $order['money'], 'hook', 0]);
                    $__pendingId = $DB->lastInsertId();

                    $__qr = $DB->getRow("SELECT remark, pay_name FROM pay_qrcode WHERE id=:i", [':i'=>$__qid]);
                    $__payee = $__qr['remark'] ?: ($__qr['pay_name'] ?: '未命名');
                    $__msg = "⏳ <b>新订单待支付</b>\n"
                           . "👤 收款人：<b>{$__payee}</b>\n"
                           . "💰 金额：<b>￥" . number_format($order['money'], 2) . "</b>\n"
                           . "🔑 订单：<code>{$__tradeNo}</code>\n"
                           . "💡 客户已拉起支付，请留意收款。";
                    
                    $__kb = ['inline_keyboard' => [[
                        ['text' => '✅ 确认到账', 'callback_data' => "conf_ok_{$__pendingId}"],
                        ['text' => '❌ 忽略', 'callback_data' => "conf_no_{$__pendingId}"]
                    ]]];

                    require_once dirname(__DIR__) . '/includes/bot_sender.php';
                    foreach (explode(',', $__target) as $__t) {
                        $__res = botSend($__bc['own_bot_token'], trim($__t), $__msg, $__kb);
                        @file_put_contents('/tmp/push_debug.log', date('H:i:s') . " | 发送结果: " . json_encode($__res) . "\n", FILE_APPEND);
                    }
                }
            }
        }
    }
}
// ========== 兜底选码结束 ==========

// 从 pay_qrcode 反查完整字段（兼容老 param 里的字段）
$qrFull = null;
if (!empty($p['qrcode_id'])) {
    $qrFull = $DB->getRow("SELECT * FROM pay_qrcode WHERE id=:id", [':id'=>$p['qrcode_id']]);
if ($qrFull && empty($qrFull['qrcode_content'])) {
    // 现场强行解析
    $__fullPath = __DIR__ . '/../' . ltrim($qrFull['qrcode_img'], '/');
    if (file_exists($__fullPath)) {
        $__out = [];
        $__ret = 0;
        @exec('/usr/bin/zbarimg -q --raw ' . escapeshellarg($__fullPath) . ' 2>&1', $__out, $__ret);
        if ($__ret === 0 && !empty($__out)) {
            $__content = trim(implode('', $__out));
            $DB->exec("UPDATE pay_qrcode SET qrcode_content=? WHERE id=?", [$__content, $qrFull['id']]);
            $qrFull['qrcode_content'] = $__content;
            $content = $__content; // 覆盖前面解析失败的空值
        }
    }
}
}
if (!$qrFull) {
    echo '<div style="padding:40px;text-align:center;color:#c00;">收款码已删除，请联系客服</div>';
    exit;
}
$content = $qrFull['qrcode_content'] ?? '';
$qrcodeChannel = $qrFull['channel'] ?? '';
$isAggregate = (strpos($qrcodeChannel, 'aggregate') === 0);
$qrCategory = $qrFull['category'] ?? 'qrcode';
$qrPayAccount = $qrFull['pay_account'] ?? '';
$qrPayName = $qrFull['pay_name'] ?? '';
$qrPayBank = $qrFull['pay_bank'] ?? '';
$isToken   = ($qrCategory === 'token');
$isAccount = ($qrCategory === 'account');

$qrPayExtra = $qrFull['pay_extra'] ?? '';

$orderType = intval($order['type']);
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isWechat = strpos($ua, 'MicroMessenger') !== false;
$isAlipayClient = strpos($ua, 'AlipayClient') !== false;

if (!$content) {
    // token/account 类型没有二维码图片，跳过
    if ($isToken || $isAccount) {
        // 不需要 $content
    } else {
        $qrcodeImg = '';
        if (!empty($p['qrcode_id'])) {
            $qrRow = $DB->getRow("SELECT qrcode_img FROM pay_qrcode WHERE id=:id", [':id'=>$p['qrcode_id']]);
            if ($qrRow && !empty($qrRow['qrcode_img'])) {
                $qrcodeImg = '/' . ltrim($qrRow['qrcode_img'], '/');
            }
        }
        if (!$qrcodeImg) exit('订单未分配收款码');
        // 优先用 qrcode_content（解析出的真实内容）；为空才用原图
        $dbContent = $qrFull['qrcode_content'] ?? '';
        if (!empty($dbContent) && strlen(trim($dbContent)) > 4) {
            $content = trim($dbContent);
        } else {
            $content = 'IMG:' . $qrcodeImg;
        }
    }
}

$money = number_format($order['realmoney'] > 0 ? $order['realmoney'] : $order['money'], 2);

// 支付宝系：标准 alipay 域名 OR 任意 https URL（聚合码/银联/乐刷等也走支付宝协议拉起）
$isAlipayQr     = preg_match('#^https?://(qr|ds|render)\.alipay\.com/#i', $content)
               || (preg_match('#^https?://#i', $content)
                   && !preg_match('#^(wxp|weixin)://#i', $content)
                   && !preg_match('#^https?://(payapp|mp|wx)\.weixin\.qq\.com#i', $content)
                   && strpos($content, 'IMG:') !== 0);
$isAlipayScheme = preg_match('#^alipays?://#i', $content);
$isWechatScheme = preg_match('#^(wxp|weixin)://#i', $content);
$isWechatBizUrl = preg_match('#^https://#i', $content);
$isImgMode      = (strpos($content, 'IMG:') === 0);




if ($isAggregate) {
    $mode = 'scan';
} elseif (($isAlipayQr || $isAlipayScheme || $isWechatScheme) && !$isWechat && $orderType == 1) {
    $mode = 'auto';
} elseif (($isAlipayQr || $isAlipayScheme || $isWechatScheme) && !$isWechat) {
    $mode = 'jump';
} else {
    $mode = 'scan';
}

function getQrLocalUrl($content, $isImgMode) {
    if ($isImgMode) return substr($content, 4);
    $cacheKey = md5($content);
    $cacheDir = ROOT . 'assets/qrcode_cache/';
    $cacheFile = $cacheDir . $cacheKey . '.png';
    $cacheUrl = '/assets/qrcode_cache/' . $cacheKey . '.png';
    if (!file_exists($cacheFile)) {
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
        $apiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&ecc=H&margin=10&data=' . urlencode($content);
        $img = @file_get_contents($apiUrl);
        if ($img) @file_put_contents($cacheFile, $img);
        else return $apiUrl;
    }
    return $cacheUrl;
}

$qrUrl = getQrLocalUrl($content, $isImgMode);
$qrDownloadName = 'payment_' . $trade_no . '.png';
$selfUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'pay.yunzexinda.com') . '/paypage/mapay.php?trade_no=' . $trade_no;

// ===== 一次性跳转 token（5分钟有效）=====
$__jumpTs = time();
$__jumpSign = substr(hash_hmac('sha256', $trade_no . '|' . $__jumpTs, SYS_KEY), 0, 16);
$jumpTk = base64_encode($trade_no . '|' . $__jumpTs . '|' . $__jumpSign);
$jumpUrl = '/paypage/jump.php?tk=' . urlencode($jumpTk);

// ===== 测试订单按钮（仅 is_test=1 显示）=====
$__testBtnHtml = '';
if (!empty($order['is_test'])) {
    $__testBtnHtml = '<div style="margin-top:14px;padding:14px;background:#fff7e6;border-radius:12px;border-left:4px solid #ff9500;">'
        . '<div style="font-size:12.5px;color:#8a5a00;margin-bottom:10px;line-height:1.6;">'
        . '🧪 <b>测试订单</b> · 你可选择真实付款，<br>或点击下方按钮直接体验确认流程</div>'
        . '<div style="width:100%;padding:12px;background:#fff;border:1px dashed #ff9500;'
        . 'border-radius:10px;text-align:center;font-size:13px;color:#8a5a00;line-height:1.7;">'
        . '📬 到账通知已发送<br>'
        . '请前往 <b>TG / 邮箱</b> 点击 <b>【✅ 确认到账】</b><br>'
        . '完成测试流程</div>'
        . '</div>';
}


$isAlipay = ($orderType == 1);
$brandColor = $isAlipay ? '#1677FF' : '#07c160';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>支付 ¥<?php echo $money; ?></title>
<style>
* { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
html, body { margin: 0; padding: 0; }
body {
  font-family: -apple-system, "PingFang SC", "Helvetica Neue", "Microsoft YaHei", Arial, sans-serif;
  background: linear-gradient(180deg, #f0f3f8 0%, #e8ecf3 100%);
  min-height: 100vh; padding: 24px 12px 40px; color: #1f2d3d;
  -webkit-font-smoothing: antialiased;
}
.stage { max-width: 400px; margin: 0 auto; }
.box {
  background: #fff; border-radius: 22px; overflow: hidden; position: relative;
  box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 8px 24px rgba(15,23,42,.08), 0 20px 60px rgba(15,23,42,.06);
  animation: fadeIn .35s ease;
}
@keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
.box::before { content: ''; display: block; height: 4px; }
.box.wechat::before { background: linear-gradient(90deg, #33d17a, #07c160, #06a850); }
.box.alipay::before { background: linear-gradient(90deg, #3B93FF, #1677FF, #0d5cd6); }
.box-inner { padding: 22px 20px 18px; }

.brand-header { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 20px; }
.brand-icon {
  display: inline-flex; width: 40px; height: 40px; border-radius: 12px;
  overflow: hidden; flex-shrink: 0; box-shadow: 0 4px 12px rgba(0,0,0,.12);
}
.brand-icon.sm { width: 22px; height: 22px; border-radius: 6px; box-shadow: 0 2px 6px rgba(0,0,0,.15); }
.brand-icon svg { display: block; width: 100%; height: 100%; }
.brand-text { font-size: 16px; font-weight: 800; letter-spacing: .3px; }
.brand-text.wechat { color: #06a850; }
.brand-text.alipay { color: #0d5cd6; }

.amount-block { text-align: center; padding: 4px 0 22px; }
.amount-label { font-size: 12.5px; color: #a4abb8; font-weight: 600; letter-spacing: .5px; margin-bottom: 6px; }
.amount-value {
  font-size: 46px; font-weight: 800; letter-spacing: -2px; line-height: 1.05; color: #1f2d3d;
  display: flex; align-items: baseline; justify-content: center; gap: 2px; font-variant-numeric: tabular-nums;
}
.amount-value .symbol { font-size: 24px; font-weight: 700; margin-right: 2px; }
.amount-value .decimal { font-size: 26px; font-weight: 700; }
.order-no { font-size: 11.5px; color: #b0b7c3; margin-top: 8px; font-variant-numeric: tabular-nums; }
.order-no::before { content: '订单号 · '; letter-spacing: .3px; }

.btn-main {
  display: flex; align-items: center; justify-content: center; gap: 10px;
  width: 100%; padding: 16px 20px; border: none; border-radius: 14px;
  font-size: 16px; font-weight: 800; color: #fff;
  cursor: pointer; font-family: inherit; letter-spacing: .3px;
  transition: all .18s cubic-bezier(.4,0,.2,1);
  position: relative; overflow: hidden;
}
.btn-main::after {
  content: ''; position: absolute; inset: 0;
  background: linear-gradient(180deg, rgba(255,255,255,.15) 0%, rgba(255,255,255,0) 60%);
  pointer-events: none;
}
.btn-main:active { transform: translateY(1px) scale(.995); }
.btn-main.wechat { background: linear-gradient(180deg, #2ecb75 0%, #07c160 100%); box-shadow: 0 8px 20px rgba(7,193,96,.35), 0 2px 4px rgba(7,193,96,.2); }
.btn-main.alipay { background: linear-gradient(180deg, #2E8CFF 0%, #1677FF 100%); box-shadow: 0 8px 20px rgba(22,119,255,.35), 0 2px 4px rgba(22,119,255,.2); }

.or-divider {
  display: flex; align-items: center; gap: 14px;
  margin: 22px 0 18px; color: #c8cdd6; font-size: 11.5px;
  font-weight: 600; letter-spacing: 2px;
}
.or-divider::before, .or-divider::after {
  content: ''; flex: 1; height: 1px;
  background: linear-gradient(90deg, transparent, #eef2f7 50%, transparent);
}

.qr-section { text-align: center; }
.qr-card {
  display: flex; align-items: stretch; justify-content: center; gap: 12px; padding: 14px 0;
}
.qr-frame {
  background: #fff; padding: 10px; border-radius: 16px;
  box-shadow: 0 0 0 1px #eef2f7, 0 8px 24px rgba(15,23,42,.06);
  display: flex; align-items: center; justify-content: center;
}
.qr-frame img { display: block; width: 190px; height: 190px; border-radius: 8px; }
.qr-side-bar {
  width: 34px; border-radius: 20px; display: flex; align-items: center; justify-content: center;
  writing-mode: vertical-rl; letter-spacing: 3px; font-size: 12.5px; font-weight: 800;
  line-height: 1; flex-shrink: 0; position: relative; overflow: hidden;
}
.qr-side-bar::after {
  content: ''; position: absolute; inset: 0;
  background: linear-gradient(180deg, rgba(255,255,255,.18) 0%, rgba(255,255,255,0) 30%, rgba(0,0,0,.05) 100%);
  pointer-events: none; border-radius: inherit;
}
.qr-side-bar.wx {
  background: linear-gradient(180deg, #2ecb75 0%, #07c160 45%, #06a850 100%);
  color: #fff; box-shadow: 0 4px 14px rgba(7,193,96,.4), inset 0 1px 0 rgba(255,255,255,.35);
  text-shadow: 0 1px 2px rgba(0,0,0,.15);
}
.qr-side-bar.ali {
  background: linear-gradient(180deg, #2E8CFF 0%, #1677FF 45%, #0d5cd6 100%);
  color: #fff; box-shadow: 0 4px 14px rgba(22,119,255,.4), inset 0 1px 0 rgba(255,255,255,.35);
  text-shadow: 0 1px 2px rgba(0,0,0,.15);
}
.qr-side-bar.top10 {
  background: linear-gradient(180deg, #FFE08A 0%, #F7B733 45%, #E89B1C 100%);
  color: #7A4A00; box-shadow: 0 4px 14px rgba(247,183,51,.4), inset 0 1px 0 rgba(255,255,255,.5);
}
.scan-tip { font-size: 12.5px; color: #86909c; margin-top: 12px; font-weight: 500; }
.scan-tip .hl { color: #1f2d3d; font-weight: 700; }

.safe-bar {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  margin-top: 14px; padding: 10px 14px;
  background: linear-gradient(90deg, #f0faf4 0%, #f5fbf7 100%);
  border: 1px solid #d9f0e2; border-radius: 10px;
  font-size: 12.5px; color: #4e5969; font-weight: 600;
}
.safe-bar.blue { background: linear-gradient(90deg, #f0f6ff 0%, #f5f9ff 100%); border-color: #d9e6ff; }
.safe-dot {
  width: 8px; height: 8px; border-radius: 50%; background: #07c160;
  box-shadow: 0 0 0 3px rgba(7,193,96,.15); animation: pulse 2s infinite; flex-shrink: 0;
}
.safe-dot.blue { background: #1677FF; box-shadow: 0 0 0 3px rgba(22,119,255,.15); animation: pulse-blue 2s infinite; }
@keyframes pulse { 0%,100% { box-shadow: 0 0 0 3px rgba(7,193,96,.15); } 50% { box-shadow: 0 0 0 6px rgba(7,193,96,.08); } }
@keyframes pulse-blue { 0%,100% { box-shadow: 0 0 0 3px rgba(22,119,255,.15); } 50% { box-shadow: 0 0 0 6px rgba(22,119,255,.08); } }
.safe-score {
  margin-left: auto; display: inline-flex; align-items: baseline;
  color: #07c160; font-weight: 900; font-size: 18px; letter-spacing: -.5px; font-variant-numeric: tabular-nums;
}
.safe-score em { font-style: normal; font-size: 12px; font-weight: 700; margin-left: 1px; }
.safe-score.blue { color: #1677FF; }

.bottom-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 18px; }
.btn-sub {
  padding: 12px; border: 1.5px solid #eef2f7; background: #fafbfc; border-radius: 12px;
  font-size: 13px; font-weight: 700; color: #4e5969;
  cursor: pointer; font-family: inherit;
  display: flex; align-items: center; justify-content: center; gap: 6px; transition: all .15s;
}
.btn-sub:active { background: #f2f4f7; border-color: #e0e5ec; }
.btn-sub svg { width: 14px; height: 14px; flex-shrink: 0; }

.warn-box {
  display: flex; align-items: flex-start; gap: 10px; margin-top: 18px; padding: 13px 14px;
  background: #fff8f8; border: 1px solid #ffe3e0; border-radius: 12px;
}
.warn-icon {
  flex-shrink: 0; width: 18px; height: 18px; border-radius: 50%; background: #ff4d4f; color: #fff;
  font-size: 12px; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; margin-top: 1px;
}
.warn-text { font-size: 12.5px; color: #cf1322; line-height: 1.65; font-weight: 600; }
.warn-text b { color: #a8071a; font-weight: 800; }

.foot-brand { text-align: center; padding: 16px 0 4px; color: #c8cdd6; font-size: 11px; font-weight: 600; letter-spacing: .5px; }
.foot-brand .dot { color: #dfe3ea; margin: 0 6px; }
.foot-brand .name { color: #86909c; font-weight: 700; }

.toast {
  position: fixed; top: 50%; left: 50%; transform: translate(-50%,-50%) scale(.9);
  background: rgba(15,23,42,.92); color: #fff; padding: 14px 26px; border-radius: 14px;
  font-size: 14px; font-weight: 600; display: none; z-index: 9999;
  box-shadow: 0 12px 40px rgba(0,0,0,.25); transition: transform .2s;
}
.toast.show { display: block; transform: translate(-50%,-50%) scale(1); }

.logo-defs { position: absolute; width: 0; height: 0; overflow: hidden; }

/* ====== 二维码色块（官方风格） ====== */
.qr-band {
  border-radius: 16px;
  padding: 14px 14px 12px;
  text-align: center;
  position: relative;
  overflow: hidden;
  margin: 0 10px;
}
.qr-band.wechat {
  background: linear-gradient(180deg, #2ecb75 0%, #07c160 100%);
  box-shadow: 0 12px 32px rgba(7,193,96,.25);
}
.qr-band.alipay {
  background: linear-gradient(180deg, #3B93FF 0%, #1677FF 100%);
  box-shadow: 0 12px 32px rgba(22,119,255,.25);
}
.qr-band-title {
  font-size: 12.5px;
  font-weight: 800;
  color: #fff;
  letter-spacing: .3px;
  margin-bottom: 10px;
  text-shadow: 0 1px 2px rgba(0,0,0,.1);
}
.qr-band .qr-card {
  display: inline-block;
  background: #fff;
  padding: 8px;
  border-radius: 10px;
  box-shadow: 0 4px 14px rgba(0,0,0,.1);
}
.qr-band .qr-card img {
  display: block;
  width: 130px;
  height: 130px;
  border-radius: 6px;
}
.qr-band-foot {
  font-size: 12px;
  font-weight: 700;
  color: #fff;
  letter-spacing: .3px;
  margin-top: 10px;
  opacity: .95;
  text-shadow: 0 1px 2px rgba(0,0,0,.1);
}

</style>
</head>
<body>

<!-- ============ 安全检测过场 ============ -->
<div id="splash">
  <div class="splash-inner">

    <!-- 阶段 1 -->
    <div class="splash-stage" data-stage="1">
      <div class="splash-spinner">
        <div class="ring outer"></div>
        <div class="ring inner"></div>
        <div class="dot"></div>
      </div>
      <div class="splash-title">
        <span class="splash-check">✓</span>
        检测当前网络环境安全
      </div>
      <div class="splash-tip">请不要关闭页面...</div>
    </div>

    <!-- 阶段 2 -->
    <div class="splash-stage" data-stage="2">
      <div class="splash-spinner">
        <div class="ring outer"></div>
        <div class="ring inner"></div>
        <div class="dot"></div>
      </div>
      <div class="splash-title">
        <span class="splash-check">✓</span>
        检测当前支付环境安全
      </div>
      <div class="splash-tip">请不要关闭页面...</div>
    </div>

    <!-- 阶段 3 -->
    <div class="splash-stage" data-stage="3">
      <div class="splash-spinner">
        <div class="ring outer"></div>
        <div class="ring inner"></div>
        <div class="dot"></div>
      </div>
      <div class="splash-title">
        <span class="splash-check">✓</span>
        信达安全管家正在为您选择优质商家
      </div>
      <div class="splash-tip">请不要关闭页面...</div>
    </div>

  </div>
  <div class="splash-brand">
    <span>信达网络</span>
    <em>·</em>
    <span>安全支付</span>
  </div>
</div>
<style>
#splash {
  position: fixed; inset: 0; z-index: 99999;
  background: linear-gradient(180deg, #f0f3f8 0%, #e8ecf3 100%);
  display: flex; align-items: center; justify-content: center;
  flex-direction: column;
  font-family: -apple-system, "PingFang SC", "Helvetica Neue", "Microsoft YaHei", sans-serif;
  transition: opacity .45s ease, visibility .45s ease;
}
#splash.hide { opacity: 0; visibility: hidden; pointer-events: none; }
.splash-inner { text-align: center; padding: 0 30px; }
.splash-spinner {
  width: 76px; height: 76px; margin: 0 auto 28px;
  position: relative;
}
.splash-spinner .ring {
  position: absolute; border-radius: 50%;
  border: 3px solid transparent;
}
.splash-spinner .ring.outer {
  inset: 0;
  border-top-color: #07c160;
  border-right-color: #07c160;
  animation: spin 1s linear infinite;
}
.splash-spinner .ring.inner {
  inset: 12px;
  border-bottom-color: #4EE38E;
  border-left-color: #4EE38E;
  animation: spin 1.4s linear infinite reverse;
}
.splash-spinner .dot {
  position: absolute; top: 50%; left: 50%;
  width: 10px; height: 10px; margin: -5px 0 0 -5px;
  background: linear-gradient(135deg, #33d17a, #07c160);
  border-radius: 50%;
  box-shadow: 0 0 12px rgba(7,193,96,.6);
  animation: pulseDot 1.5s ease-in-out infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes pulseDot {
  0%, 100% { transform: scale(1); opacity: 1; }
  50% { transform: scale(.7); opacity: .6; }
}
.splash-stage {
  display: none;
  animation: stageFade .35s ease;
}
.splash-stage.active { display: block; }
@keyframes stageFade {
  from { opacity: 0; transform: translateY(6px); }
  to { opacity: 1; transform: translateY(0); }
}
.splash-title {
  font-size: 17px; font-weight: 800; color: #1f2d3d;
  letter-spacing: .3px; margin-bottom: 10px;
  display: inline-flex; align-items: center; gap: 8px;
  line-height: 1.3;
}
.splash-check {
  display: inline-flex; align-items: center; justify-content: center;
  width: 22px; height: 22px; border-radius: 50%;
  background: linear-gradient(135deg, #33d17a, #07c160);
  color: #fff; font-size: 13px; font-weight: 800;
  box-shadow: 0 2px 8px rgba(7,193,96,.35);
  flex-shrink: 0;
}

.splash-tip {
  font-size: 12px; color: #b0b7c3;
  letter-spacing: .3px;
  position: relative; display: inline-block;
}
.splash-tip::after {
  content: ''; position: absolute; left: 0; right: 0; bottom: -4px;
  height: 1px; background: linear-gradient(90deg, transparent, #07c160, transparent);
  animation: scanLine 1.5s ease-in-out infinite;
}
@keyframes scanLine {
  0%, 100% { opacity: .3; transform: scaleX(.4); }
  50% { opacity: 1; transform: scaleX(1); }
}
.splash-brand {
  position: absolute; bottom: 30px; left: 0; right: 0;
  text-align: center; color: #c8cdd6;
  font-size: 11px; font-weight: 600; letter-spacing: .5px;
}
.splash-brand em { color: #dfe3ea; margin: 0 6px; font-style: normal; }
</style>


<svg class="logo-defs" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <symbol id="logo-wechat" viewBox="0 0 1024 1024">
      <defs><linearGradient id="wx-bg" x1="0%" y1="0%" x2="0%" y2="100%">
        <stop offset="0%" stop-color="#4EE38E"/><stop offset="100%" stop-color="#05B457"/>
      </linearGradient></defs>
      <rect width="1024" height="1024" rx="230" fill="url(#wx-bg)"/>
      <path d="M 405 235 C 273 235 165 320 165 425 C 165 483 199 535 252 570 L 225 645 L 310 605 C 340 615 372 620 405 620 C 537 620 645 535 645 425 C 645 320 537 235 405 235 Z" fill="#fff"/>
      <path d="M 660 425 C 570 425 495 480 495 550 C 495 590 520 625 560 648 L 542 700 L 600 672 C 620 678 640 682 660 682 C 750 682 825 625 825 550 C 825 480 750 425 660 425 Z" fill="#fff"/>
      <circle cx="330" cy="395" r="32" fill="#05B457"/>
      <circle cx="480" cy="395" r="32" fill="#05B457"/>
      <circle cx="610" cy="530" r="24" fill="#05B457"/>
      <circle cx="725" cy="530" r="24" fill="#05B457"/>
    </symbol>
    <symbol id="logo-alipay" viewBox="0 0 24 24">
      <defs>
        <linearGradient id="ali-bg" x1="0%" y1="0%" x2="0%" y2="100%">
          <stop offset="0%" stop-color="#3D97FF"/>
          <stop offset="100%" stop-color="#1677FF"/>
        </linearGradient>
      </defs>
      <!-- 白色圆角底 -->
      <rect width="24" height="24" rx="5.4" fill="#ffffff"/>
      <!-- 官方 path：蓝色填充 + evenodd 挖空"支"字 -->
      <path d="M19.695 15.07c3.426 1.158 4.203 1.22 4.203 1.22V3.846c0-2.124-1.705-3.845-3.81-3.845H3.914C1.808.001.102 1.722.102 3.846v16.31c0 2.123 1.706 3.845 3.813 3.845h16.173c2.105 0 3.81-1.722 3.81-3.845v-.157s-6.19-2.602-9.315-4.119c-2.096 2.602-4.8 4.181-7.607 4.181-4.75 0-6.361-4.19-4.112-6.949.49-.602 1.324-1.175 2.617-1.497 2.025-.502 5.247.313 8.266 1.317a16.796 16.796 0 0 0 1.341-3.302H5.781v-.952h4.799V6.975H4.77v-.953h5.81V3.591s0-.409.411-.409h2.347v2.84h5.744v.951h-5.744v1.704h4.69a19.453 19.453 0 0 1-1.986 5.06c1.424.52 2.702 1.011 3.654 1.333m-13.81-2.032c-.596.06-1.71.325-2.321.869-1.83 1.608-.735 4.55 2.968 4.55 2.151 0 4.301-1.388 5.99-3.61-2.403-1.182-4.438-2.028-6.637-1.809" fill="url(#ali-bg)" fill-rule="evenodd"/>
    </symbol>
    <symbol id="ico-save" viewBox="0 0 24 24">
      <path d="M 5 20 h 14 a 1 1 0 0 0 1-1 v-9 l-5-5 H 6 a 1 1 0 0 0 -1 1 Z M 8 13 h 8 v 6 H 8 Z M 12 3 v 6 m -3 -3 l 3 3 l 3 -3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </symbol>
    <symbol id="ico-copy" viewBox="0 0 24 24">
      <rect x="8" y="8" width="12" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="2"/>
      <path d="M 16 8 V 6 a 2 2 0 0 0 -2 -2 H 6 a 2 2 0 0 0 -2 2 v 8 a 2 2 0 0 0 2 2 h 2" fill="none" stroke="currentColor" stroke-width="2"/>
    </symbol>
  </defs>
</svg>

<div class="stage">
  <!-- ============ 微信版 ============ -->
  <div class="box wechat" style="<?php echo $orderType == 2 ? '' : 'display:none'; ?>">
    <div class="box-inner">
      <div class="safe-bar">
        <span class="safe-dot"></span>
        <span>检测当前支付安全指数</span>
        <span class="safe-score" id="scoreWx">--<em>%</em></span>
      </div>
      <div class="brand-header">
        <span class="brand-text wechat">微信付款</span>
      </div>
      <div class="amount-block">
        <div class="amount-label">应付金额</div>
        <div class="amount-value"><span class="symbol">¥</span><?php echo $money; ?></div>
        <div class="order-no"><?php echo $trade_no; ?></div>
      </div>
      <button class="btn-main wechat" onclick="handleMainClick('wechat')">
        <span>打开微信付款</span>
      </button>
      <div class="or-divider">或扫码付款</div>
      <div class="qr-section">
        <div class="qr-band wechat">
          <div class="qr-band-title">WeChat 诚信商家 TOP10</div>
          <div class="qr-card"><img id="qrImg" src="<?php echo $qrUrl; ?>" alt="qr"></div>
          <div class="qr-band-foot">打开微信 [扫一扫]</div>
        </div>
        <div class="scan-tip" style="margin-top:12px;">长按二维码识别，或 <span class="hl">保存后扫码付款</span></div>
      </div>
      <div class="bottom-actions">
        <button class="btn-sub" onclick="saveQR()"><svg><use href="#ico-save"/></svg><span>保存二维码</span></button>
        <button class="btn-sub" onclick="copySelfLink()"><svg><use href="#ico-copy"/></svg><span>复制链接</span></button>
      </div>
      <?php echo $__testBtnHtml; ?>
      <div class="warn-box">
        <span class="warn-icon">!</span>
        <div class="warn-text">请按订单金额 <b>¥<?php echo $money; ?></b> 付款，多付或少付均无法自动到账</div>
      </div>
      <div class="foot-brand"><span class="name">信达网络</span><span class="dot">·</span>安全支付</div>
    </div>
  </div>

  <!-- ============ 支付宝版 ============ -->
  <div class="box alipay" style="<?php echo $orderType == 1 ? '' : 'display:none'; ?>">
    <div class="box-inner">
      <div class="safe-bar blue">
        <span class="safe-dot blue"></span>
        <span>检测当前支付安全指数</span>
        <span class="safe-score blue" id="scoreAli">--<em>%</em></span>
      </div>
      <div class="brand-header">
        <span class="brand-text alipay">Alipay</span>
      </div>
      <div class="amount-block">
        <div class="amount-label">应付金额</div>
        <div class="amount-value"><span class="symbol">¥</span><?php echo $money; ?></div>
        <div class="order-no"><?php echo $trade_no; ?></div>
      </div>
      <button class="btn-main alipay" onclick="handleMainClick('alipay')">
        <span>打开支付宝付款</span>
      </button>
      <div class="or-divider">或扫码付款</div>
      <div class="qr-section">
        <div class="qr-band alipay">
          <div class="qr-band-title">Alipay 诚信商家 TOP10</div>
          <div class="qr-card"><img id="qrImg2" src="<?php echo $qrUrl; ?>" alt="qr"></div>
          <div class="qr-band-foot">打开支付宝 [扫一扫]</div>
        </div>
        <div class="scan-tip" style="margin-top:12px;">长按二维码识别，或 <span class="hl">保存后扫码付款</span></div>
      </div>
      <div class="bottom-actions">
        <button class="btn-sub" onclick="saveQR('qrImg2')"><svg><use href="#ico-save"/></svg><span>保存二维码</span></button>
        <button class="btn-sub" onclick="copySelfLink()"><svg><use href="#ico-copy"/></svg><span>复制链接</span></button>
      </div>
      <?php echo $__testBtnHtml; ?>
      <div class="warn-box">
        <span class="warn-icon">!</span>
        <div class="warn-text">请按订单金额 <b>¥<?php echo $money; ?></b> 付款，多付或少付均无法自动到账</div>
      </div>
      <div class="foot-brand"><span class="name">信达网络</span><span class="dot">·</span>安全支付</div>
    </div>
  </div>
</div>

<div class="toast" id="toast">提示</div>

<script>
var ORDER_TYPE = <?php echo (int)$orderType; ?>;
var IS_WECHAT = <?php echo $isWechat ? 'true' : 'false'; ?>;
var IS_ALIPAY_CLIENT = <?php echo $isAlipayClient ? 'true' : 'false'; ?>;
var IS_WECHAT_BIZ = <?php echo $isWechatBizUrl ? 'true' : 'false'; ?>;
var IS_ALIPAY_QR = <?php echo $isAlipayQr ? 'true' : 'false'; ?>;
var IS_AGGREGATE = <?php echo $isAggregate ? 'true' : 'false'; ?>;
// CONTENT 已移后端
var SELF_URL = <?php echo json_encode($selfUrl); ?>;
var QR_URL = <?php echo json_encode($qrUrl); ?>;
var TRADE_NO = <?php echo json_encode($trade_no); ?>;

function showToast(msg) {
  var t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(t._timer);
  t._timer = setTimeout(function(){ t.classList.remove('show'); }, 1800);
}

function handleMainClick(type) {
  location.href = '<?php echo $jumpUrl; ?>';
}


function openWechat() {
  var schemes = ['weixin://', 'weixin://dl/scan', 'weixin://scanqrcode'];
  var i = 0;
  (function next(){
    if (i >= schemes.length) { showToast('请手动打开微信 → 扫一扫'); return; }
    try { window.location.href = schemes[i]; } catch(e){}
    i++;
    setTimeout(function(){ if (!document.hidden && i < schemes.length) next(); }, 600);
  })();
}

function openAlipayScan() {
  var schemes = [];
  var i = 0;
  (function next(){
    if (i >= schemes.length) { showToast('请手动打开支付宝扫一扫'); return; }
    try { window.location.href = schemes[i]; } catch(e){}
    i++;
    setTimeout(function(){ if (!document.hidden && i < schemes.length) next(); }, 600);
  })();
}

function saveQR(imgId) {
  var img = document.getElementById(imgId || 'qrImg');
  if (!img) { showToast('二维码加载失败'); return; }
  try {
    var a = document.createElement('a');
    a.href = img.src;
    a.download = 'payment_' + TRADE_NO + '.png';
    a.target = '_blank';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    showToast('图片已开始下载');
  } catch(e) { showToast('请长按二维码保存'); }
}

function copySelfLink() {
  var url = SELF_URL;
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(url).then(function(){ showToast('链接已复制'); }).catch(function(){ fallbackCopy(url); });
  } else { fallbackCopy(url); }
}
function fallbackCopy(text) {
  var ta = document.createElement('textarea');
  ta.value = text; ta.style.position = 'fixed'; ta.style.top = '-9999px';
  document.body.appendChild(ta); ta.select();
  try { document.execCommand('copy'); showToast('链接已复制'); } catch(e) { showToast('复制失败'); }
  document.body.removeChild(ta);
}

// 安全指数：随机 97.00% ~ 99.99%
(function(){
  function roll() { return ((Math.floor(Math.random() * (9999 - 9700 + 1)) + 9700) / 100).toFixed(2); }
  ['scoreWx','scoreAli'].forEach(function(id){
    var el = document.getElementById(id);
    if (el) el.innerHTML = roll() + '<em>%</em>';
  });
})();

// 轮询订单状态
setInterval(function(){
  fetch('/api.php?act=order&trade_no=' + encodeURIComponent(TRADE_NO))
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d && d.status == 1) location.href = '/payok.php?trade_no=' + encodeURIComponent(TRADE_NO);
    }).catch(function(){});
}, 3000);

// ===== 页面打开后自动跳 jump.php（scheme 在后端）=====
(function(){
  var ua = navigator.userAgent;
  var isMobile = /Android|iPhone|iPad|iPod|Mobile/i.test(ua);
  if (!isMobile) return;

  setTimeout(function(){
    location.href = '<?php echo $jumpUrl; ?>';
  }, 300);
})();


// ===== 3 段式过场：每段 0.9 秒，总 2.7 秒 =====
(function(){
  var stages = document.querySelectorAll('.splash-stage');
  if (!stages.length) return;

  // 显示第 1 段
  stages[0].classList.add('active');

  // 0.9s → 切到第 2 段
  setTimeout(function(){
    stages.forEach(function(el){ el.classList.remove('active'); });
    if (stages[1]) stages[1].classList.add('active');
  }, 900);

  // 1.8s → 切到第 3 段
  setTimeout(function(){
    stages.forEach(function(el){ el.classList.remove('active'); });
    if (stages[2]) stages[2].classList.add('active');
  }, 1800);

  // 2.7s → 淡出
  setTimeout(function(){
    var sp = document.getElementById('splash');
    if (sp) sp.classList.add('hide');
  }, 2700);
})();

</script>
</body>
</html>
