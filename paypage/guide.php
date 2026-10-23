<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>信达收款监听 APP - 操作说明</title>
<style>
body{font-family:-apple-system,"PingFang SC","Microsoft YaHei",Arial,sans-serif;margin:0;background:#f5f7fb;color:#333;}
.wrap{max-width:780px;margin:0 auto;padding:24px 20px;background:#fff;min-height:100vh;}
pre{white-space:pre-wrap;word-wrap:break-word;font-family:inherit;font-size:15px;line-height:1.9;margin:0;}
</style>
</head>
<body>
<div class="wrap">
<pre># 信达收款监听 APP - 操作说明

&gt; v1.0 | Android 7.0+

## 一、下载

手机浏览器打开：
https://pay.yunzexinda.com/xinda_listener.apk

允许"未知来源"安装。

## 二、配置 APP

- 服务器地址：https://pay.yunzexinda.com/paypage/notify_listen.php
- 上报密钥：向客服索取（每个商户不同）

点【保存配置】。

## 三、开启通知监听权限

1. 点【① 开启通知监听权限】
2. 系统跳到"通知使用权"设置
3. 找到"信达监听"→ 打开
4. 返回 APP，显示 ✅ 已开启

## 四、测试连通

点【② 发送测试】。

服务器查看：
tail -5 /www/wwwroot/pay.yunzexinda.com/paypage/listen.log

看到 test.pkg 就算通。

## 五、真实收款

让朋友转 0.01 元。

服务器查看：
tail -5 /www/wwwroot/pay.yunzexinda.com/paypage/listen.log

看到 pkg=com.eg.android.AlipayGphone 就算抓到。

## 六、后台保活（必做）

- 小米：省电策略 → 无限制；自启动 → 允许
- 华为：应用启动管理 → 手动管理，3 开关全开
- OPPO：应用耗电管理 → 允许后台运行
- VIVO：i管家 → 自启动 → 允许

手机插充电器，别断电。

## 七、支付 App 通知

支付宝：
- 系统设置 → 支付宝 → 通知 → 全部允许
- 支付宝 → 我的 → 设置 → 消息通知 → 开启

微信：
- 系统设置 → 微信 → 通知 → 全部允许
</pre>
</div>
</body>
</html>
