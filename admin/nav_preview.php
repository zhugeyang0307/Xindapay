<?php
include("../includes/common.php");
$title='导航预览（新版）';
include './head_preview.php';
?>
<div class="container-fluid" style="padding-top:80px;">
<div class="row">
  <div class="col-md-12">
    <div class="alert alert-info" style="font-size:14px;line-height:1.9;">
      <b>📌 管理员端导航预览（仅下拉分组优化）</b><br>
      • 当前页面使用 <code>head_preview.php</code>，原 <code>head.php</code> 未动<br>
      • 点顶部「商户管理」「系统设置」「支付接口」看下拉里的分组效果<br>
      • 新增分隔线 + 分组标题（如「📋 商户核心」「🏠 基础配置」）<br>
      • 右侧菜单项加了图标（fa-fw 对齐）<br>
      • <b>H5 手机端表现 100% 不变</b>：点右上角汉堡按钮 → 还是原生 BS3 折叠<br>
      • <b>47 个 admin 页面完全不受影响</b>（因为只改导航内部）<br>
      <br>
      👉 手机测试：用手机浏览器打开本站 admin 后台，点右上角 ☰ 汉堡，验证下拉是否正常<br>
      👉 确认无误后告诉我，合并到正式 <code>head.php</code>
    </div>
  </div>
</div>
</div>
<?php include './foot.php'; ?>
