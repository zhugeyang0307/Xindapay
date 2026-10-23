<?php
require_once('../includes/common.php');
if($islogin2 != 1) exit("<script>location.href='./login.php';</script>");
$uid = (int)$userrow['uid'];
$title = '品牌与域名绑定';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $logo_url = trim($_POST['logo_url'] ?? '');
    $bind_domain = trim($_POST['bind_domain'] ?? '');
    $bind_domain = preg_replace('#^https?://#', '', $bind_domain);
    $custom_notice = trim($_POST['custom_notice'] ?? '');
    
    $DB->exec("UPDATE pay_user SET logo_url=:l, bind_domain=:d, custom_notice=:n WHERE uid=:u", 
              [':l'=>$logo_url, ':d'=>$bind_domain, ':n'=>$custom_notice, ':u'=>$uid]);
    exit("<script>alert('设置保存成功！');location.href='./brand_setting.php';</script>");
}

include './head.php';
?>
<div class="wrapper-md">
  <div class="panel panel-default" style="max-width:680px;margin:0 auto;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.05);">
    <div class="panel-heading" style="background:#fff;border-bottom:1px solid #eee;font-weight:bold;">
      <i class="fa fa-paint-brush text-primary"></i> 品牌与独立域名设置
    </div>
    <div class="panel-body">
      <form method="POST">
        <div class="form-group">
          <label>🖼️ 商户 Logo 图片地址 (URL)</label>
          <input type="text" name="logo_url" class="form-control" placeholder="https://..." value="<?= htmlspecialchars($userrow['logo_url'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>🌐 绑定您的独立域名</label>
          <input type="text" name="bind_domain" class="form-control" placeholder="例如：pay.mydomain.com" value="<?= htmlspecialchars($userrow['bind_domain'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>📝 自定义公告 / 开户话术 (支持 HTML)</label>
          <textarea name="custom_notice" class="form-control" rows="4" placeholder="留空则不显示"><?= htmlspecialchars($userrow['custom_notice'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;padding:12px;font-weight:bold;">保存设置</button>
      </form>
    </div>
  </div>
</div>
<?php include './foot.php'; ?>
