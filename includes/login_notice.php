<?php
/**
 * 登录安全提醒推送
 */
if (!function_exists('sendLoginNotice')) {
    function sendLoginNotice($botToken, $chatId, $nickname, $type, $ip, $city, $device, $browser, $time) {
        if (empty($botToken) || empty($chatId)) return false;
        $msg = "🔐 <b>登录安全提醒</b>\n"
             . "━━━━━━━━━━━━━━━━━━\n"
             . "👤 账号：<b>" . htmlspecialchars($nickname) . "</b>\n"
             . "🕐 时间：{$time}\n"
             . "📍 IP：{$ip}\n"
             . "🌏 地区：" . ($city ?: '未知') . "\n"
             . "💻 设备：{$device} / {$browser}\n"
             . "🔑 方式：{$type}\n"
             . "━━━━━━━━━━━━━━━━━━\n"
             . "⚠️ 如非本人操作，请立即修改密码！";

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'chat_id' => $chatId,
                'text' => $msg,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true
            ]),
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $r = curl_exec($ch);
        curl_close($ch);
        return $r !== false;
    }
}
