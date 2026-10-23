<?php
require_once __DIR__ . '/../config.php';

class Telegram {
    private static $currentToken = null;

    /** 切换当前使用的 bot token（null=用默认） */
    public static function useToken($token) {
        self::$currentToken = $token ?: null;
    }
    public static function currentToken() {
        return self::$currentToken ?: (defined('BOT_TOKEN') ? BOT_TOKEN : '');
    }

    public static function api($method, $params = []) {
        $url = 'https://api.telegram.org/bot' . self::currentToken() . '/' . $method;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) { plog("TG API失败[{$method}]: $err"); return null; }
        return json_decode($resp, true);
    }

    public static function sendMessage($chat_id, $text, $keyboard = null) {
        $params = [
            'chat_id' => $chat_id,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];
        if ($keyboard) $params['reply_markup'] = json_encode($keyboard);
        return self::api('sendMessage', $params);
    }

    public static function editMessageText($chat_id, $message_id, $text, $keyboard = null) {
        $params = [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];
        if ($keyboard) $params['reply_markup'] = json_encode($keyboard);
        return self::api('editMessageText', $params);
    }

    public static function answerCallback($id, $text = '') {
        return self::api('answerCallbackQuery', ['callback_query_id'=>$id, 'text'=>$text]);
    }

    public static function sendDocument($chat_id, $filePath, $caption = '') {
        if (!file_exists($filePath)) return null;
        $token = self::currentToken();
        $url = "https://api.telegram.org/bot{$token}/sendDocument";
        $post = [
            'chat_id' => $chat_id,
            'document' => new CURLFile($filePath),
        ];
        if ($caption !== '') $post['caption'] = $caption;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_TIMEOUT => 60,
        ]);
        $r = curl_exec($ch);
        curl_close($ch);
        return json_decode($r, true);
    }

    public static function getUpdates($offset, $timeout = 25) {
        return self::api('getUpdates', ['offset'=>$offset, 'timeout'=>$timeout]);
    }


    /**
     * 根据 @username 或 chat_id 获取 Chat 信息（成功返回数组，失败返回 null）
     */
    public static function getChat($chat_id_or_username) {
        $r = self::api('getChat', ['chat_id' => $chat_id_or_username]);
        if (!empty($r['ok'])) return $r['result'];
        return null;
    }
}
