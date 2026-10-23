<?php
if(!defined('IN_CRONLITE'))exit();
require INDEX_ROOT.'head.php';
?>
<style>
.doc-page-header { background: linear-gradient(135deg, #0d5cd6 0%, #1677ff 100%); color: #fff; padding: 40px 20px; text-align: center; box-shadow: 0 4px 12px rgba(13,92,214,.2); margin: -20px -15px 20px; }
.doc-page-header h1 { font-size: 28px; margin: 0 0 8px; font-weight: 700; color: #fff; }
.doc-page-header p { font-size: 14px; opacity: .9; margin: 0; color: #fff; }
.doc-wrap { max-width: 1200px; margin: 0 auto; padding: 0 15px; display: flex; gap: 24px; align-items: flex-start; }
.doc-sidebar { width: 240px; flex-shrink: 0; }
.toc-box { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 12px rgba(15,23,42,.06); position: sticky; top: 80px; }
.toc-box h4 { font-size: 14px; color: #0f172a; margin: 0 0 12px; padding-bottom: 8px; border-bottom: 1px solid #e6ebf2; font-weight: 700; }
.toc-list { list-style: none; padding: 0; margin: 0; }
.toc-list li { margin: 0; }
.toc-list li.divider { height: 1px; background: #e6ebf2; margin: 8px 0; }
.toc-list a { display: block; padding: 6px 10px; color: #475569; text-decoration: none; font-size: 13.5px; border-radius: 6px; transition: .2s; }
.toc-list a:hover { background: #f0f8ff; color: #1677ff; }
.doc-main { flex: 1; min-width: 0; }
.doc-panel { background: #fff; border-radius: 12px; padding: 28px 32px; box-shadow: 0 2px 12px rgba(15,23,42,.06); }
.doc-panel .api_block { margin-bottom: 32px; padding-bottom: 24px; border-bottom: 1px dashed #e6ebf2; }
.doc-panel .api_block:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
.doc-panel h3 { font-size: 18px; color: #1677ff !important; margin: 0 0 16px !important; padding: 0 0 0 12px !important; border-left: 4px solid #1677ff; line-height: 1.4; font-weight: 700; }
.doc-panel p { margin: 8px 0; font-size: 14px; color: #475569; line-height: 1.7; }
.doc-panel font[color="#777"] { color: #64748b !important; }
.doc-panel font[color="#29389f"] { color: #1677ff !important; font-weight: 600; }
.doc-panel font[color="#993939"] { color: #dc2626 !important; }
.doc-panel table.table { width: 100% !important; border-collapse: collapse; margin: 14px 0; font-size: 13.5px; }
.doc-panel table.table th { background: linear-gradient(180deg, #f0f5ff 0%, #eaf2ff 100%) !important; color: #0f172a !important; font-weight: 700 !important; padding: 10px 12px !important; border: 1px solid #e6ebf2 !important; text-align: left; }
.doc-panel table.table td { padding: 10px 12px !important; border: 1px solid #e6ebf2 !important; color: #475569; vertical-align: top; background: #fff; }
.doc-panel table.table tr:nth-child(even) td { background: #fafbfc; }
.doc-panel table.table tr:hover td { background: #f0f8ff; }
.doc-panel a { color: #1677ff; text-decoration: none; }
.doc-panel a:hover { text-decoration: underline; }
.doc-panel hr { border: none; border-top: 1px dashed #e6ebf2; margin: 24px 0; }
@media (max-width: 800px) {
  .doc-wrap { flex-direction: column; gap: 16px; }
  .doc-sidebar { width: 100%; }
  .toc-box { position: static; }
  .doc-panel { padding: 16px; }
  .doc-page-header h1 { font-size: 20px; }
  .doc-panel h3 { font-size: 16px; }
  .doc-panel table.table { font-size: 12px; }
  .doc-panel table.table th, .doc-panel table.table td { padding: 8px !important; }
}
</style>

<div class="doc-page-header">
  <h1>📖 信达支付 · 开发文档</h1>
  <p>完整 API 接口说明 · 页面跳转支付 · API接口支付 · 支付结果通知</p>
</div>

<div class="doc-wrap">
  <div class="doc-sidebar">
    <div class="toc-box">
      <h4>📑 接口目录</h4>
      <ul class="toc-list">
        <li><a href="#toc2">协议规则</a></li>
        <li class="divider"></li>
        <li><a href="#pay0">页面跳转支付</a></li>
        <li><a href="#pay1">API接口支付</a></li>
        <li><a href="#pay2">支付结果通知</a></li>
        <li><a href="#pay3">MD5签名算法</a></li>
        <li><a href="#pay4">支付方式列表</a></li>
        <li><a href="#pay5">设备类型列表</a></li>
        <li class="divider"></li>
        <li><a href="#api1">[API]查询商户信息</a></li>
        <li><a href="#api3">[API]查询结算记录</a></li>
        <li><a href="#api4">[API]查询单个订单</a></li>
        <li><a href="#api5">[API]批量查询订单</a></li>
        <li><a href="#api6">[API]提交订单退款</a></li>
        <li class="divider"></li>
        <li><a href="#sdk0">SDK下载</a></li>
      </ul>
    </div>
  </div>
  <div class="doc-main">
    <div class="doc-panel">
      <?php include INDEX_ROOT.'doc.inc.php';?>
    </div>
  </div>
</div>

<?php require INDEX_ROOT.'foot.php';?>
