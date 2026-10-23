<?php
require_once __DIR__ . '/../../telegram/secrets.php';
/**
 * 邮件营销 AI 辅助 - DeepSeek 封装
 */

class MailAI {

    // DeepSeek 配置（复用 telegram 的 key）
    // const API_KEY 改用 DEEPSEEK_API_KEY 常量（来自 telegram/secrets.php）
    const API_URL = 'https://api.deepseek.com/v1/chat/completions';
    const MODEL   = 'deepseek-chat';

    /**
     * 生成邮件正文
     * @param string $topic      主题描述（用户输入）
     * @param string $tone       语气（正式/亲切/促销/通知）
     * @param string $style      风格（简约/商务/节日）
     * @param string $extraTips  附加要求
     * @return array              ['ok'=>bool, 'html'=>string, 'subject'=>string, 'error'=>string]
     */
    public static function generate($topic, $tone = '亲切', $style = '简约', $extraTips = '') {
        $system = "你是专业的营销邮件撰写助手。你需要生成完整的、可直接发送的 HTML 邮件正文。

【要求】
1. 用内联样式（style=\"...\"），不要用 <style> 标签（邮箱会过滤）
2. 宽度 600px，居中
3. 使用桌面 + 移动端兼容的排版
4. 不要用 JS、表单、iframe
5. 图片留空或用绝对 URL 占位
6. 中文内容，语气：{$tone}
7. 设计风格：{$style}
8. 邮件开头要有品牌标识区，结尾要有退订提示

【输出格式】严格只输出 JSON（不要 ```json 包裹）：
{\"subject\":\"邮件主题\",\"html\":\"完整 HTML 代码\"}

html 字段里是一整段 HTML 字符串（含转义的双引号）。";

        $user = "生成一封邮件：{$topic}";
        if ($extraTips) $user .= "\n\n附加要求：{$extraTips}";

        $payload = json_encode([
            'model' => self::MODEL,
            'messages' => [
                ['role'=>'system', 'content'=>$system],
                ['role'=>'user', 'content'=>$user],
            ],
            'temperature' => 0.7,
            'max_tokens' => 3000,
            'response_format' => ['type'=>'json_object'],
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . DEEPSEEK_API_KEY,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) return ['ok'=>false, 'error'=>'网络错误：'.$err];

        $r = json_decode($resp, true);
        if (!$r) return ['ok'=>false, 'error'=>'API 返回异常：'.substr($resp,0,200)];

        if (isset($r['error'])) return ['ok'=>false, 'error'=>'DeepSeek：'.($r['error']['message'] ?? '未知')];

        $content = $r['choices'][0]['message']['content'] ?? '';
        if (!$content) return ['ok'=>false, 'error'=>'AI 无返回'];

        $parsed = json_decode($content, true);
        if (!$parsed || empty($parsed['html'])) {
            return ['ok'=>false, 'error'=>'AI 返回格式异常，请重试'];
        }

        return [
            'ok' => true,
            'subject' => $parsed['subject'] ?? '',
            'html' => $parsed['html'],
        ];
    }

    /** 优化现有 HTML（润色/改语气） */
    public static function improve($html, $tip) {
        $system = "你是 HTML 邮件润色助手。用户会给你一段邮件 HTML，你按他的要求修改。

【要求】
1. 保持原有结构和主要信息
2. 内联样式，600px 宽度
3. 只输出 JSON：{\"subject\":\"新主题(如未改填原主题)\",\"html\":\"新 HTML\"}
4. 不要用 ```json 包裹";

        $user = "原 HTML：\n{$html}\n\n修改要求：{$tip}";

        $payload = json_encode([
            'model' => self::MODEL,
            'messages' => [
                ['role'=>'system', 'content'=>$system],
                ['role'=>'user', 'content'=>$user],
            ],
            'temperature' => 0.5,
            'max_tokens' => 3500,
            'response_format' => ['type'=>'json_object'],
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . DEEPSEEK_API_KEY,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $resp = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
        if ($err) return ['ok'=>false, 'error'=>'网络错误：'.$err];
        $r = json_decode($resp, true);
        if (isset($r['error'])) return ['ok'=>false, 'error'=>'DeepSeek：'.($r['error']['message'] ?? '未知')];
        $content = $r['choices'][0]['message']['content'] ?? '';
        $parsed = json_decode($content, true);
        if (!$parsed || empty($parsed['html'])) return ['ok'=>false, 'error'=>'AI 返回格式异常'];
        return ['ok'=>true, 'subject'=>$parsed['subject'] ?? '', 'html'=>$parsed['html']];
    }
}