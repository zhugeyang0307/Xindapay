<?php
if (!function_exists('botSend')) {
    /**
     * 通用 TG 发送（支持任意 bot token + inline 按钮）
     */
    function botSend($token, $chatId, $text, $keyboard = null) {
        if (empty($token) || empty($chatId)) return false;
        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        $post = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];
        if ($keyboard) $post['reply_markup'] = json_encode($keyboard, JSON_UNESCAPED_UNICODE);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($post),
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $r = curl_exec($ch);
        curl_close($ch);
        $j = json_decode($r, true);
        return !empty($j['ok']);
    }
}
