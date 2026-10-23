<?php
$is_defend = true;
include("../includes/common.php");
if($islogin2==1){}else exit("<script>location.href='./login.php';</script>");
$uid = (int)$userrow['uid'];

// 处理重置密钥请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_key') {
    $lid = (int)$_POST['license_id'];
    // 确保只能重置自己的密钥
    $check = $DB->getRow("SELECT id FROM pay_license WHERE id=:id AND uid=:u", [':id'=>$lid, ':u'=>$uid]);
    if ($check) {
        $newCode = 'LIC-' . strtoupper(bin2hex(random_bytes(5)));
        $newSecret = bin2hex(random_bytes(16));
        $DB->exec("UPDATE pay_license SET code=:c, secret=:s WHERE id=:id", [':c'=>$newCode, ':s'=>$newSecret, ':id'=>$lid]);
        exit("<script>alert('✅ 鉴权密钥已重置成功！旧的密钥已失效，请重新配置 APK。');location.href='./license.php';</script>");
    }
    exit("<script>alert('❌ 非法操作！');history.back();</script>");
}

// 处理生成新密钥请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_key') {
    $code = 'LIC-' . strtoupper(bin2hex(random_bytes(5)));
    $secret = bin2hex(random_bytes(16));
    $DB->exec("INSERT INTO pay_license (code, uid, secret, status, addtime) VALUES (:c, :u, :s, 0, NOW())", [':c'=>$code, ':u'=>$uid, ':s'=>$secret]);
    exit("<script>alert('✅ 密钥生成成功！');location.href='./license.php';</script>");
}

$licenses = $DB->getAll("SELECT * FROM pay_license WHERE uid=:u ORDER BY id DESC LIMIT 10", [':u'=>$uid]);
$title = 'APK鉴权密钥';
include './head.php';
?>
<style>
.lic-card{background:#fff;border-radius:14px;padding:24px;box-shadow:0 4px 20px rgba(0,0,0,0.04);margin-bottom:20px;border:1px solid #eef2f7;}
.lic-item{border:1px solid #e5eaf3;border-radius:10px;padding:20px;margin-bottom:15px;display:flex;justify-content:space-between;align-items:center;gap:12px;background:#f8fafc;transition:all 0.2s;}
.lic-item:hover{border-color:#1677ff;box-shadow:0 4px 12px rgba(22,119,255,0.08);}
.lic-code{font-family:Consolas,Monaco,monospace;font-size:18px;font-weight:800;color:#1677ff;letter-spacing:1.5px;}
.lic-secret{font-family:Consolas,Monaco,monospace;font-size:13px;color:#64748b;margin-top:6px; background:#fff; padding:4px 8px; border-radius:4px; border:1px dashed #cbd5e1; display:inline-block;}
.tip{background:#eff6ff;border-left:4px solid #3b82f6;padding:16px;border-radius:8px;margin-bottom:20px;font-size:14px;color:#1e40af;line-height:1.8;}
.btn-create{background:linear-gradient(135deg,#10b981,#059669);color:#fff;border:none;padding:14px 40px;border-radius:10px;font-size:16px;font-weight:bold;cursor:pointer;width:100%;box-shadow:0 4px 12px rgba(16,185,129,0.3);}
.btn-create:hover{opacity:0.9;}
.btn-reset{background:#fff1f0;color:#e74c3c;border:1px solid #f8c9c4;padding:8px 16px;border-radius:8px;font-size:13px;cursor:pointer;transition:all 0.2s;}
.btn-reset:hover{background:#e74c3c;color:#fff;}
</style>

<div id="content" class="app-content" role="main">
<div class="app-content-body">
<div class="bg-light lter b-b wrapper-md">
  <h1 class="m-n font-thin h3"><i class="fa fa-shield"></i> APK鉴权密钥</h1>
  <small class="text-muted">用于 APK 挂机端与服务器通讯的安全凭证</small>
</div>

<div class="wrapper-md">
  <div class="row">
    <div class="col-md-8 col-md-offset-2">
      
      <div class="tip">
        <b>💡 安全须知：</b><br>
        1. 这里的 <b>Secret（密钥）</b> 是您配置在挂机 APK 上的核心凭证，<b>请勿泄露给他人</b>，否则会导致订单被伪造。<br>
        2. 如果您的密钥不慎泄露，请立即点击右侧的 <b>“重置密钥”</b>。重置后，旧的密钥将立刻失效，请及时更新您手机端的配置。
      </div>

      <div class="lic-card">
        <h4 style="margin-top:0; margin-bottom:20px; font-weight:bold; color:#1e293b;">🔑 我的密钥列表</h4>
        
        <?php if(empty($licenses)): ?>
            <div class="text-center" style="padding:30px 0; color:#94a3b8;">
                <i class="fa fa-inbox fa-3x" style="margin-bottom:15px; color:#cbd5e1;"></i>
                <p>您还没有生成过鉴权密钥</p>
            </div>
        <?php else: ?>
            <?php foreach($licenses as $row): ?>
                <div class="lic-item">
                    <div>
                        <div class="lic-code"><?php echo htmlspecialchars($row['code']); ?></div>
                        <div class="lic-secret">Secret: <?php echo htmlspecialchars($row['secret']); ?></div>
                    </div>
                    <div>
                        <form method="POST" onsubmit="return confirm('⚠️ 确定要重置该密钥吗？重置后旧的立即失效，您需要重新配置 APK！');" style="margin:0;">
                            <input type="hidden" name="action" value="reset_key">
                            <input type="hidden" name="license_id" value="<?php echo $row['id']; ?>">
                            <button type="submit" class="btn-reset"><i class="fa fa-refresh"></i> 重置密钥</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <form method="POST" style="margin-top: 25px;">
            <input type="hidden" name="action" value="create_key">
            <button type="submit" class="btn-create">➕ 生成新的鉴权密钥</button>
        </form>

      </div>
    </div>
  </div>
</div>

</div>
</div>
<?php include './foot.php'; ?>
