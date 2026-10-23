<?php
require_once('../includes/common.php');
// 已解除登录限制，允许公开访问
$uid = (int)$userrow['uid'];
$title = '帮助中心';

$file = isset($_GET['file']) ? basename($_GET['file']) : '';
if ($file !== '') {
    if(!preg_match('/\.html$/i', $file)) $file .= '.html';
    $doc_path = dirname(__DIR__) . '/help/docs/' . $file;
    if(!file_exists($doc_path)) $file = '商户操作手册.html';
    $src = '/help/docs/' . $file;
} else {
    $src = '/help/';
}

include './head.php';
?>
<style>
    .app-content-body { padding: 0 !important; background: #fff !important; }
    .app-content { margin: 0 !important; padding: 0 !important; }
    .help-app-shell {
        position: fixed;
        inset: 0;
        padding-top: 54px;
        background: #f5f7fb;
        z-index: 1000;
    }
    .help-app-topbar {
        position: fixed;
        left: 0;
        right: 0;
        top: 0;
        height: 54px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 0 14px;
        background: linear-gradient(135deg, #1677ff, #0d5cd6);
        color: #fff;
        box-shadow: 0 8px 22px rgba(22, 119, 255, 0.18);
        z-index: 1100;
    }
    .help-app-back, .help-app-home {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 68px;
        height: 32px;
        padding: 0 12px;
        border-radius: 999px;
        border: 1px solid rgba(255,255,255,0.2);
        background: rgba(255,255,255,0.12);
        color: #fff;
        text-decoration: none;
        font-weight: 700;
        font-size: 13px;
        cursor: pointer;
    }
    .help-app-title {
        flex: 1;
        text-align: center;
        font-size: 15px;
        font-weight: 700;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .help-app-frame {
        position: absolute;
        top: 54px;
        left: 0;
        right: 0;
        bottom: 0;
        width: 100%;
        height: calc(100% - 54px);
        border: none;
        background: #fff;
    }
    @media (max-width: 768px) {
       .help-app-title { font-size: 14px; }
       .help-app-back, .help-app-home { min-width: 58px; padding: 0 10px; }
    }
</style>
<div class="help-app-shell">
    <div class="help-app-topbar">
        <button class="help-app-back" type="button" onclick="(function(){ if (document.referrer && document.referrer.indexOf(location.host) !== -1 && history.length > 1) { history.back(); } else { location.href = '/user/'; } })();">← 返回</button>
        <div class="help-app-title">帮助中心</div>
        <a class="help-app-home" href="/user/app_help.php">首页</a>
    </div>
    <iframe id="helpFrame" class="help-app-frame" src="<?php echo htmlspecialchars($src, ENT_QUOTES); ?>"></iframe>
</div>
<script>
(function(){
    var frame = document.getElementById('helpFrame');
    if (!frame) return;
    var applyMobile = function(){
        try {
            var doc = frame.contentDocument || frame.contentWindow.document;
            if (!doc || !doc.body) return;
            var style = doc.getElementById('app-help-mobile-style');
            if (!style) {
                style = doc.createElement('style');
                style.id = 'app-help-mobile-style';
                doc.head.appendChild(style);
            }
            style.textContent = [
                'body{max-width:900px;margin:0 auto;padding:18px 16px 80px !important;font-size:16px;line-height:1.8;color:#1f2937;background:#fff;}',
                '*{box-sizing:border-box;}',
                'img,video,iframe,table,pre{max-width:100% !important;height:auto;}',
                'table{display:block;overflow-x:auto;border-collapse:collapse;width:100%;}',
                'a{color:#1677ff;text-decoration:none;}',
                'h1,h2,h3,h4{line-height:1.4;margin-top:1.2em;}',
                'code,pre{white-space:pre-wrap;word-break:break-word;}',
                '.header,.topbar,.right,.breadcrumb,.footer,.nav,nav,header{display:none !important;}'
            ].join(' ');
            var links = doc.querySelectorAll('a[href]');
            for (var i = 0; i < links.length; i++) {
                var a = links[i];
                var href = a.getAttribute('href') || '';
                if (!href || href.indexOf('javascript:') === 0 || href.indexOf('#') === 0) continue;
                if (href.indexOf('http://') === 0 || href.indexOf('https://') === 0 || href.indexOf('mailto:') === 0) {
                    a.setAttribute('target', '_blank');
                    a.setAttribute('rel', 'noopener noreferrer');
                    continue;
                }
                if (href.indexOf('/help/') !== -1 || href.indexOf('docs/') !== -1) {
                    a.setAttribute('target', '_self');
                    a.onclick = function(e){
                        var target = this.getAttribute('href');
                        if (!target) return;
                        e.preventDefault();
                        var url = new URL(target, window.location.origin);
                        var fileName = url.pathname.split('/help/docs/').pop();
                        if (fileName) {
                            window.location.href = '/user/app_help.php?file=' + encodeURIComponent(fileName);
                        } else {
                            window.location.href = '/user/app_help.php';
                        }
                    };
                }
            }
        } catch (e) {}
    };
    frame.addEventListener('load', applyMobile);
    applyMobile();
})();
</script>
<?php include './foot.php'; ?>
