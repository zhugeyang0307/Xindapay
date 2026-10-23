<?php
require_once('../includes/common.php');
if($islogin2 != 1) exit("<script>location.href='./login.php';</script>");
$uid = (int)$userrow['uid'];
$title = 'USDT 收款配置';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $api_url = trim($_POST['usdt_api_url'] ?? '');
    $api_token = trim($_POST['usdt_api_token'] ?? '');
    $DB->exec("UPDATE pay_user SET usdt_api_url=:u, usdt_api_token=:t WHERE uid=:id", [':u'=>$api_url, ':t'=>$api_token, ':id'=>$uid]);
    exit("<script>alert('✅ USDT 配置保存成功！');location.href='./usdt_setting.php';</script>");
}

include './head.php';
?>
<style>
.setting-container { max-width: 760px; margin: 0 auto; padding: 20px 15px; }
.setting-card { background: #fff; border-radius: 16px; padding: 30px; box-shadow: 0 8px 30px rgba(0,0,0,0.04); border: 1px solid #eef2f7; }
.setting-header { border-bottom: 1px solid #f1f5f9; padding-bottom: 20px; margin-bottom: 25px; }
.setting-header h2 { font-size: 22px; font-weight: 800; color: #1e293b; margin: 0 0 8px 0; display: flex; align-items: center; gap: 10px; }
.setting-header p { color: #64748b; font-size: 14px; margin: 0; }
.form-label { font-weight: 600; color: #334155; font-size: 14px; margin-bottom: 8px; display: block; }
.form-control-custom { width: 100%; padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 14px; transition: all 0.2s; background: #f8fafc; color: #1e293b; margin-bottom: 20px; }
.form-control-custom:focus { outline: none; border-color: #3b82f6; background: #fff; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
.btn-save { background: linear-gradient(135deg, #3b82f6, #2563eb); color: #fff; border: none; padding: 14px; width: 100%; border-radius: 10px; font-size: 16px; font-weight: bold; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 12px rgba(59,130,246,0.2); }
.btn-save:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(59,130,246,0.3); }
.tip-box { padding: 20px; border-radius: 12px; margin-bottom: 20px; line-height: 1.8; font-size: 14px; }
.tip-green { background: #f0fdf4; border-left: 4px solid #22c55e; color: #166534; }
.tip-yellow { background: #fffbeb; border-left: 4px solid #f59e0b; color: #92400e; }
.tip-blue { background: #eff6ff; border-left: 4px solid #3b82f6; color: #1e40af; }
</style>

<div class="setting-container">
    <div class="setting-card">
        <div class="setting-header">
            <h2><i class="fa fa-btc" style="color:#f59e0b;"></i> USDT 独立收款配置</h2>
            <p>配置您专属的 USDT 收银台，资金直达您的钱包，平台绝不碰钱。</p>
        </div>

        <!-- 1. 核心推荐与成本公开 -->
        <div class="tip-box tip-green">
            <h4 style="margin-top:0; font-weight:bold; color:#15803d; font-size:16px;"><i class="fa fa-shield"></i> 强烈建议：部署您自己的专属收银台</h4>
            <p style="margin-bottom:10px;">为了保证您的资金绝对安全，平台<strong>绝不触碰、绝不代收</strong>您的任何一分钱。我们强烈建议您自己购买一台海外服务器部署收银台。您也可选择直接提供您的 TRC20 钱包地址，让客户直接联系您转账。</p>
            <div style="background:#fff; padding:15px; border-radius:8px; border:1px solid #bbf7d0; margin-top:10px;">
                <b>💰 服务器成本参考：</b><br>
                常规业务，只需购买一台 <b>1核1G内存</b> 的海外 VPS 即可完美运行。<br>
                参考价格：<b>约 $5 - $10 / 月</b>（折合人民币约 40 - 70 元）。<br>
                <span style="color:#64748b; font-size:13px; margin-top:5px; display:block;">* 推荐使用 RackNerd、CloudCone、腾讯云/阿里云国际站等，避开国内机房以防被墙。</span>
            </div>
        </div>

        <!-- 2. 风险警告 -->
        <div class="tip-box tip-yellow">
            <h4 style="margin-top:0; font-weight:bold; color:#b45309; font-size:16px;"><i class="fa fa-exclamation-triangle"></i> 资金安全与风控红线</h4>
            <ul style="padding-left:20px; line-height:2; margin-bottom:0;">
                <li><b>资金池风险：</b>若使用平台公共网关，所有资金会汇聚到平台，极容易触发链上风控，被交易所标记为“黑U”（涉嫌洗钱或网赌）。</li>
                <li><b>跑路风险：</b>将大额资金存放在第三方公共接口，一旦接口方跑路或被查，您的资金将血本无归。</li>
                <li><b>商户主权：</b>只有使用您自己服务器生成的独立地址，才能确保每一笔钱都直接进入您的冷钱包。</li>
            </ul>
        </div>

        <!-- 3. 极简部署指南 -->
        <div class="tip-box tip-blue" style="margin-bottom:30px;">
            <h4 style="margin-top:0; font-weight:bold; color:#1d4ed8; font-size:16px;"><i class="fa fa-rocket"></i> 如何极速部署自己的网关？</h4>
            <ol style="padding-left:20px; line-height:2; margin-bottom:10px;">
                <li>购买上述推荐配置的海外服务器，安装 Linux (CentOS/Ubuntu)。</li>
                <li>执行一键 Docker 部署命令拉取 BEpusdt 容器。</li>
                <li>在您的服务器后台生成 <b>API 对接令牌</b>。</li>
                <li>将该令牌和您的服务器 IP 填入下方表单，即可完成无缝对接。</li>
            </ol>
            <a href="https://github.com/v03413/BEpusdt" target="_blank" style="color:#2563eb; text-decoration:underline; font-weight:bold;">点击查看 BEpusdt 官方一键部署教程 →</a>
        </div>

        <!-- 表单区 -->
        <form method="POST">
            <div class="form-group">
                <label class="form-label">🔗 API 网关地址 (URL)</label>
                <input type="text" name="usdt_api_url" class="form-control-custom" placeholder="例如：http://您的服务器IP:8080" value="<?= htmlspecialchars($userrow['usdt_api_url'] ?? 'http://127.0.0.1:8080') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">🔑 对接令牌 (API Token)</label>
                <input type="text" name="usdt_api_token" class="form-control-custom" placeholder="填入 BEpusdt 后台获取的 API 令牌" value="<?= htmlspecialchars($userrow['usdt_api_token'] ?? '') ?>">
            </div>

            <button type="submit" class="btn-save">保存 USDT 收款配置</button>
        </form>
    </div>
</div>
<?php include './foot.php'; ?>
