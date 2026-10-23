<?php
require_once __DIR__ . '/_boot.php';

$siteBase = 'https://pay.yunzexinda.com';
$sdkUrl   = $siteBase . '/mail_track/track/sdk.js';
$h5Tpl    = $siteBase . '/mail_track/track/landing_h5_template.html';
include __DIR__ . '/../head.php';
?>
<div id="content" class="app-content" role="main">
<div class="app-content-body">

<div class="bg-light lter b-b wrapper-md">
  <h1 class="m-n font-thin h3"><i class="fa fa-code" style="color:#0d47a1;"></i> 追踪接入</h1>
</div>

<div class="wrapper-md">

<div class="alert alert-info" style="font-size:13.5px;line-height:2;">
  <b>💡 选择你的场景：</b>
  <ul style="margin:8px 0 0 18px;">
    <li><b>有网页版/落地页</b> → 看「网页端接入」</li>
    <li><b>只有 APP</b> → 看「APP 端接入」</li>
    <li><b>H5 + APP 都有</b> → 用「H5 中转页」（一键模板）</li>
  </ul>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs" style="margin-bottom:20px;">
  <li class="active"><a href="#tab-web" data-toggle="tab"><i class="fa fa-globe"></i> 网页端接入</a></li>
  <li><a href="#tab-app" data-toggle="tab"><i class="fa fa-mobile"></i> APP 端接入</a></li>
  <li><a href="#tab-h5" data-toggle="tab"><i class="fa fa-exchange"></i> H5 中转页</a></li>
  <li><a href="#tab-faq" data-toggle="tab"><i class="fa fa-question-circle"></i> 常见问题</a></li>
</ul>

<div class="tab-content">

<!-- ============ TAB 1: 网页端 ============ -->
<div class="tab-pane active" id="tab-web">

<div class="panel panel-primary">
  <div class="panel-heading font-bold"><i class="fa fa-1"></i> 步骤 1：在落地页加入追踪代码（必须）</div>
  <div class="panel-body">
    <p style="color:#555;">把这段代码粘到你落地页 HTML 的 <code>&lt;head&gt;</code> 标签内，越靠前越好：</p>
    <pre id="code1" style="background:#1e1e1e;color:#d4d4d4;padding:16px;border-radius:6px;overflow-x:auto;font-size:13px;">&lt;script src="<?= $sdkUrl ?>"&gt;&lt;/script&gt;</pre>
    <button class="btn btn-sm btn-primary" onclick="copyCode('code1')"><i class="fa fa-copy"></i> 复制代码</button>
  </div>
</div>

<div class="panel panel-primary">
  <div class="panel-heading font-bold"><i class="fa fa-2"></i> 步骤 2：追踪业务事件（可选，强烈推荐）</div>
  <div class="panel-body">
    <p style="color:#555;">在你的落地页 JS 里，当用户完成某个动作时调用 <code>mtTrack.event()</code>：</p>

    <h4 style="font-size:14px;color:#0d47a1;margin-top:16px;">🛒 用户下单成功</h4>
    <pre id="code2" style="background:#1e1e1e;color:#d4d4d4;padding:16px;border-radius:6px;overflow-x:auto;font-size:13px;">mtTrack.event('purchase', {
  amount: 199,
  order: 'ORD20261016001',
  product: '高级会员'
});</pre>
    <button class="btn btn-xs btn-primary" onclick="copyCode('code2')"><i class="fa fa-copy"></i> 复制</button>

    <h4 style="font-size:14px;color:#0d47a1;margin-top:20px;">👤 用户注册成功</h4>
    <pre id="code3" style="background:#1e1e1e;color:#d4d4d4;padding:16px;border-radius:6px;overflow-x:auto;font-size:13px;">mtTrack.event('register', {
  name: '张三',
  phone: '138****8888'
});</pre>
    <button class="btn btn-xs btn-primary" onclick="copyCode('code3')"><i class="fa fa-copy"></i> 复制</button>
  </div>
</div>

</div>

<!-- ============ TAB 2: APP 端 ============ -->
<div class="tab-pane" id="tab-app">

<div class="alert alert-warning" style="font-size:13.5px;line-height:2;">
  <b>📱 APP 追踪原理：</b><br>
  邮件按钮 → 商户 H5 落地页（带 mt_rid） → H5 自动调起 APP 并把 mt_rid 传进去 → APP 拿到 mt_rid → 调 API 上报
</div>

<div class="panel panel-primary">
  <div class="panel-heading font-bold"><i class="fa fa-1"></i> 步骤 1：用「H5 中转页」方案（最推荐，最省事）</div>
  <div class="panel-body">
    <p>下载模板，改 3 个配置就完事：</p>
    <ol style="line-height:2;">
      <li>你的 APP 协议名（如 <code>yourapp</code>，配在 APP 的 AndroidManifest / Info.plist）</li>
      <li>APP 下载地址（iOS / Android）</li>
      <li>H5 兜底页（APP 没装时跳这里）</li>
    </ol>
    <a href="<?= $h5Tpl ?>" target="_blank" class="btn btn-primary">
      <i class="fa fa-download"></i> 查看/下载 H5 中转页模板
    </a>
    <a href="<?= $h5Tpl ?>" download="landing_h5_template.html" class="btn btn-default">
      <i class="fa fa-download"></i> 下载
    </a>
  </div>
</div>

<div class="panel panel-info">
  <div class="panel-heading font-bold"><i class="fa fa-2"></i> 步骤 2：APP 端上报 API</div>
  <div class="panel-body">

    <h4 style="font-size:14px;color:#0d47a1;">接口地址</h4>
    <pre style="background:#1e1e1e;color:#d4d4d4;padding:12px;border-radius:6px;font-size:12px;">POST <?= $siteBase ?>/mail_track/track/report.php
Content-Type: application/json</pre>

    <h4 style="font-size:14px;color:#0d47a1;margin-top:16px;">请求体</h4>
    <pre id="code_app_req" style="background:#1e1e1e;color:#d4d4d4;padding:12px;border-radius:6px;font-size:12px;">{
  "rid":  "6b579bae8234dbd2d6ebab7ea4efc301",
  "type": "custom",
  "name": "app_purchase",
  "data": {
    "amount": 199,
    "order": "ORD123"
  }
}</pre>
    <button class="btn btn-xs btn-primary" onclick="copyCode('code_app_req')"><i class="fa fa-copy"></i> 复制</button>

    <h4 style="font-size:14px;color:#0d47a1;margin-top:16px;">成功返回</h4>
    <pre style="background:#1e1e1e;color:#d4d4d4;padding:12px;border-radius:6px;font-size:12px;">{"code": 0}</pre>

  </div>
</div>

<div class="panel panel-default">
  <div class="panel-heading font-bold"><i class="fa fa-code"></i> 代码示例</div>
  <div class="panel-body">

    <h4 style="font-size:14px;color:#0d47a1;">🔹 Android（Kotlin）</h4>
    <pre id="code_android" style="background:#1e1e1e;color:#d4d4d4;padding:16px;border-radius:6px;overflow-x:auto;font-size:12px;line-height:1.6;">// 1. APP 启动时从 Intent 里读 mt_rid
override fun onCreate(savedInstanceState: Bundle?) {
    super.onCreate(savedInstanceState)
    val rid = intent?.data?.getQueryParameter("mt_rid")
    if (!rid.isNullOrEmpty()) {
        // 存到本地，后续用
        getSharedPreferences("mail_track", MODE_PRIVATE)
            .edit().putString("mt_rid", rid).apply()
    }
}

// 2. 下单成功时上报
fun reportPurchase(amount: Double, orderNo: String) {
    val rid = getSharedPreferences("mail_track", MODE_PRIVATE)
        .getString("mt_rid", null) ?: return

    val url = URL("<?= $siteBase ?>/mail_track/track/report.php")
    val conn = url.openConnection() as HttpURLConnection
    conn.requestMethod = "POST"
    conn.setRequestProperty("Content-Type", "application/json")
    conn.doOutput = true

    val body = JSONObject().apply {
        put("rid", rid)
        put("type", "custom")
        put("name", "app_purchase")
        put("data", JSONObject().apply {
            put("amount", amount)
            put("order", orderNo)
        })
    }.toString()

    conn.outputStream.use { it.write(body.toByteArray()) }
    conn.responseCode  // 200 表示成功
}</pre>
    <button class="btn btn-xs btn-primary" onclick="copyCode('code_android')"><i class="fa fa-copy"></i> 复制</button>

    <h4 style="font-size:14px;color:#0d47a1;margin-top:20px;">🔹 iOS（Swift）</h4>
    <pre id="code_ios" style="background:#1e1e1e;color:#d4d4d4;padding:16px;border-radius:6px;overflow-x:auto;font-size:12px;line-height:1.6;">// 1. 在 AppDelegate 或 SceneDelegate 里读 mt_rid
// Universal Link 或自定义 URL Scheme
func application(_ app: UIApplication, open url: URL, options: ...) -> Bool {
    if let rid = URLComponents(url: url, resolvingAgainstBaseURL: false)?
        .queryItems?.first(where: { $0.name == "mt_rid" })?.value {
        UserDefaults.standard.set(rid, forKey: "mt_rid")
    }
    return true
}

// 2. 下单成功时上报
func reportPurchase(amount: Double, orderNo: String) {
    guard let rid = UserDefaults.standard.string(forKey: "mt_rid") else { return }

    var request = URLRequest(url: URL(string: "<?= $siteBase ?>/mail_track/track/report.php")!)
    request.httpMethod = "POST"
    request.setValue("application/json", forHTTPHeaderField: "Content-Type")

    let body: [String: Any] = [
        "rid": rid,
        "type": "custom",
        "name": "app_purchase",
        "data": ["amount": amount, "order": orderNo]
    ]
    request.httpBody = try? JSONSerialization.data(withJSONObject: body)

    URLSession.shared.dataTask(with: request).resume()
}</pre>
    <button class="btn btn-xs btn-primary" onclick="copyCode('code_ios')"><i class="fa fa-copy"></i> 复制</button>

    <h4 style="font-size:14px;color:#0d47a1;margin-top:20px;">🔹 React Native / UniApp / Flutter</h4>
    <pre id="code_rn" style="background:#1e1e1e;color:#d4d4d4;padding:16px;border-radius:6px;overflow-x:auto;font-size:12px;line-height:1.6;">// 通用 fetch 上报（RN / UniApp / Flutter webview 都适用）
async function reportEvent(rid, name, data) {
  await fetch('<?= $siteBase ?>/mail_track/track/report.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      rid: rid,
      type: 'custom',
      name: name,
      data: data || {}
    })
  });
}

// 用法
reportEvent(rid, 'app_purchase', { amount: 199, order: 'ORD123' });</pre>
    <button class="btn btn-xs btn-primary" onclick="copyCode('code_rn')"><i class="fa fa-copy"></i> 复制</button>

  </div>
</div>

</div>

<!-- ============ TAB 3: H5 中转页 ============ -->
<div class="tab-pane" id="tab-h5">

<div class="alert alert-info" style="font-size:13.5px;line-height:2;">
  <b>什么是 H5 中转页？</b><br>
  用户点邮件 → 打开一个 H5 页面 → H5 自动调起你的 APP。整个过程追踪系统全程记录。
</div>

<div class="panel panel-primary">
  <div class="panel-heading font-bold">使用步骤</div>
  <div class="panel-body">
    <ol style="line-height:2.2;font-size:14px;">
      <li><a href="<?= $h5Tpl ?>" target="_blank">打开模板</a> → 右键另存为 <code>landing.html</code></li>
      <li>用编辑器打开，找到开头「商户需要修改的配置」：
        <pre style="background:#f7f9fc;padding:12px;border-radius:6px;font-size:12px;margin-top:8px;">var APP_SCHEME = 'yourapp';              // 你的 APP 协议名
var DOWNLOAD_URL_IOS = 'https://apps.apple.com/xxx';
var DOWNLOAD_URL_ANDROID = 'https://你的.apk';
var H5_FALLBACK = 'https://商户域名/h5home';</pre>
      </li>
      <li>上传到你的服务器（如 <code>https://你的域名/landing.html</code>）</li>
      <li>邮件里的按钮链接改指向 <code>https://你的域名/landing.html</code></li>
      <li>完成！</li>
    </ol>
  </div>
</div>

<div class="panel panel-warning">
  <div class="panel-heading font-bold">配套：APP 需要配置协议名（Scheme）</div>
  <div class="panel-body">
    <p><b>Android</b>：<code>AndroidManifest.xml</code> 里加：</p>
    <pre style="background:#1e1e1e;color:#d4d4d4;padding:12px;border-radius:6px;font-size:12px;">&lt;intent-filter&gt;
  &lt;action android:name="android.intent.action.VIEW" /&gt;
  &lt;category android:name="android.intent.category.DEFAULT" /&gt;
  &lt;category android:name="android.intent.category.BROWSABLE" /&gt;
  &lt;data android:scheme="yourapp" /&gt;
&lt;/intent-filter&gt;</pre>

    <p style="margin-top:16px;"><b>iOS</b>：<code>Info.plist</code> 里加：</p>
    <pre style="background:#1e1e1e;color:#d4d4d4;padding:12px;border-radius:6px;font-size:12px;">&lt;key&gt;CFBundleURLTypes&lt;/key&gt;
&lt;array&gt;
  &lt;dict&gt;
    &lt;key&gt;CFBundleURLSchemes&lt;/key&gt;
    &lt;array&gt;&lt;string&gt;yourapp&lt;/string&gt;&lt;/array&gt;
  &lt;/dict&gt;
&lt;/array&gt;</pre>
  </div>
</div>

</div>

<!-- ============ TAB 4: FAQ ============ -->
<div class="tab-pane" id="tab-faq">
<div class="panel panel-default">
  <div class="panel-body" style="font-size:13.5px;line-height:2.2;">

    <p><b>Q: 我的落地页是别人做的，怎么加？</b><br>
    A: 让开发把 <code>&lt;script&gt;</code> 那行加到 <code>&lt;head&gt;</code> 里，5 分钟搞定。</p>

    <p><b>Q: 我的 APP 没有 H5 落地页，能追踪吗？</b><br>
    A: 可以。用 H5 中转页方案（Tab 3），一份模板改 4 个配置，10 分钟搞定。</p>

    <p><b>Q: 邮件客户端把链接改成"打开 APP"，SDK 没机会加载怎么办？</b><br>
    A: 邮件里的链接**必须指向 H5 页面**（不是 APP 协议）。H5 页面负责调起 APP + 追踪。</p>

    <p><b>Q: APP 追踪会拖慢启动速度吗？</b><br>
    A: 不会。上报是异步的，不阻塞 APP 启动。</p>

    <p><b>Q: 上报失败怎么办？</b><br>
    A: 接口用了 3 秒超时，失败静默忽略，不影响用户体验。</p>

    <p><b>Q: 追踪数据保留多久？</b><br>
    A: 7 天。请及时到活动详情页「下载记录」保存本地 CSV。</p>

  </div>
</div>
</div>

</div><!-- /tab-content -->

</div>
</div>
</div>

<script>
function copyCode(id) {
  var el = document.getElementById(id);
  var text = el.innerText || el.textContent;
  if (navigator.clipboard) {
    navigator.clipboard.writeText(text).then(function(){ alert('已复制'); });
  } else {
    var ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    alert('已复制');
  }
}
</script>

<?php include __DIR__ . '/../foot.php'; ?>