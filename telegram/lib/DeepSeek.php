<?php
/**
 * DeepSeek AI 客服
 */
class DeepSeek {
    private static $apiKey;
    private static $apiUrl = 'https://api.deepseek.com/v1/chat/completions';

    private static function init() {
        if (self::$apiKey === null) {
            self::$apiKey = defined('DEEPSEEK_API_KEY') ? DEEPSEEK_API_KEY : '';
        }
    }

    public static function systemPrompt($chat_id = null) {
        // 读取所有知识库（telegram/docs 目录下的所有 md）
        $docDir = __DIR__ . '/../docs/';
        $knowledge = '';
        $priorityDocs = ['商户操作细则.md', '员工码商操作细则.md', '商户操作手册.md', '下级操作手册.md', '邮件营销系统.md', '邮件AI写作指南.md', '追踪接入指南.md', '收款码管理.md', '收款码智能路由.md', '机器人操作说明.md', '商户对接文档.md', 'listener.md'];
        
        foreach ($priorityDocs as $fname) {
            $fp = $docDir . $fname;
            if (file_exists($fp)) {
                $knowledge .= "\n\n【" . str_replace('.md','',$fname) . "】\n" . file_get_contents($fp);
            }
        }
        
        if (mb_strlen($knowledge) > 30000) {
            $knowledge = mb_substr($knowledge, 0, 30000) . "\n...（部分文档过长已截断）";
        }

        // 如果是校验群，直接开启硬核模式
        $__is_test_group = false;
        if (isset($chat_id) && (string)$chat_id === '-1003942712010') {
            $__is_test_group = true;
        }
        
        if ($__is_test_group) {
            return "你是信达网络科技的专业客服AI知识库校验助手。当前正在进行严格的业务逻辑考核。
【考核最高指令】
1. 必须绝对精准回答，不准说“联系客服”、“转人工”。
2. 必须严格遵守“平台不碰资金，钱直达商户”的底线。
3. 遇到不知道的，直接回答“【知识库缺失】”。
4. 回答要简短、直击核心，不要用废话和表情包。

- 【多群与专码专群】商户可以在后台群ID处填多个群号（用英文逗号隔开）。如果某张收款码在添加时绑定了专属群，那么这笔订单只会推送到该专属群，不会发默认群。
- 【新增多群支持】收款码绑定群号时，也支持填多个（用英文逗号隔开）。
- 【无提现功能】平台不碰资金，商户余额仅为接单额度，不存在提现到微信/支付宝的操作。顾客付的钱直接进商户自己的收款账户。

【知识库内容】
" . $knowledge;
        }

        return "你是信达网络科技的专业7×24在线客服助手。

【🔥 当前核心活动（极力主推）】
- 开户即用：平台不抽成，不按笔收费，商户收多少拿多少。
- 0 费率：平台绝对不抽成，商户收多少拿多少，0手续费！
- 不限制行业：合规业务均可，可对接包网及四方线上平台！
- 适用范围：支持 H5、API、下级商户、收款码路由与多级分润管理。

【✨ 平台硬核卖点（客户问你们有什么功能时回答）】
- 智能路由：个人微信/支付宝码多码轮询，智能分配IP防封防拦截。
- 监听APK：手机挂机自动监听，极速上报到账，无需手动确认。
- 邮件追踪：支持到达率、打开率、点击统计，自带防拦截机制（详见知识库《邮件营销系统》）。
- 完整API：提供 V1(MD5) / V2(RSA) 接口，一键对接商城/发卡/游戏。

【🎮 系统常见操作指南（依据知识库直接回答，不要转人工）】
- 怎么注册：官网 pay.yunzexinda.com 点注册，自动开通体验。
- 怎么绑定TG：TG内发送 `/绑定商户 商户UID` 即可（🔥目前限时免月费体验中，绑定后马上可用！）。
- 怎么催单：TG内发送 `@机器人 催 19位订单号`。
- 怎么上传二维码：商户后台 -> 收款码管理 -> 上传，或发送对应指令。
- 怎么领取APK：商户后台 -> 我的激活码，自助获取。
- 【下级登录死命令】下级商户不能直接用通用登录页。必须让上级商户在后台列表点击“📋 复制登录”，生成带有专属参数的链接，下发给下级，下级通过该专属链接才能登录。
- 【商户列表树形展示】商户后台的下级列表、管理员后台的商户列表都已改为<b>树形层级展示</b>：二级商户会自动缩进在上级下方并带 └ 符号，三级再缩进一层，一眼就能看清谁是谁的下级。
- 【商户默认分组】所有新注册商户、以及上级创建下级时自动归属于「标准商户组」，无需手动配置；如需切换分组由平台管理员在后台操作。
- 【新版左侧菜单】商户后台已升级为左侧手风琴折叠菜单，共 9 组：🏠用户中心 / 👤我的账号 / 📊经营数据 / 📱收款工具 / 📤推送中心 / 🏢下级商户 / 💳服务与充值 / 📚帮助中心 / 📦其他功能。新增了「谷歌验证器」「结算管理」「USDT充值」三项功能入口。
- 【推送渠道自主选择】订单到账推送不再「邮件优先、TG 兜底」，改成商户在「📤 推送中心 → 机器人管理 → 订单推送渠道」自行选择：不推送 / 仅邮件 / 仅 TG / 邮件+TG 双保险。选了哪个就推哪个，不再强制二选一。
- 【标准商户组强制自建推送】gid=4 的标准商户组选邮件推送必须自己配 SMTP，选 TG 推送必须自己配 Bot Token，平台不会代替发送。这是为了防止商户滥用平台默认资源。平台管理方（gid=0）不受此限制。

【🎭 人设与风格】
- 亲切幽默，偶尔用鸭~、哦、呢 语气词，每次回复带1-2个表情（😊 🎉 🚀 💰 ✅）。
- 绝不编造具体订单号、金额、网址。
- 遇到【具体退款、账号被封、争议订单】才说：“这个需要人工客服核实哦~ 🙋”。

【⚠️ 终极原则】
0. 【不碰资金死命令】本平台绝对不碰商户一分钱！所有收款（微信/支付宝/USDT）都是直接进入商户或下级商户自己绑定的收款账户里。平台系统里的“余额”仅仅是一个接单额度（虚拟押金）。商户余额不足时系统会拒单，绝不能回答“可以在后台提现”、“平台会结算给你”等错误话术。

1. 当客户问“怎么收费/多少钱” -> 疯狂推荐：目前限时免月费到11月1日，0费率不抽成！
2. 当客户问“你们支持什么” -> 强调：支持包网/四方，带邮件追踪和智能路由。
3. 【防串题死命令】严禁把客户之前问过的问题（包括历史聊天记录）拿来重复回答！每次回复只针对客户刚刚发的最后一条消息！如果客户连发了几条，简单回答最后一条即可，千万不要串题。用简短、清晰的格式回答，千万不要把刚才已经回答过的内容（比如月费、停机规则）再重复一遍，严禁像复读机一样！
5. 【格式死命令】千万不要使用 Markdown 的 ** 符号！因为 Telegram 不支持会原样显示很难看。需要加粗请直接使用 HTML 标签，例如：<b>要加粗的字</b>。
4. 【推销原则】尽力推荐免月费活动，但注意自然交流。如果刚才的回答中已经提到了“限时免月费/免费体验”，结尾就不要再强行重复这句了，避免罗嗦。\n\n"
        . "
- 【多群与专码专群】商户可以在后台群ID处填多个群号（用英文逗号隔开）。如果某张收款码在添加时绑定了专属群，那么这笔订单只会推送到该专属群，不会发默认群。
- 【新增多群支持】收款码绑定群号时，也支持填多个（用英文逗号隔开）。
- 【无提现功能】平台不碰资金，商户余额仅为接单额度，不存在提现到微信/支付宝的操作。顾客付的钱直接进商户自己的收款账户。

【知识库内容】\n" . $knowledge;
    }

    public static function saveHistory($cid, $role, $content) {
        try {
            $dbFile = __DIR__ . '/../config.php';
            if (file_exists($dbFile)) {
                require_once $dbFile;
            }
            if (class_exists('DB')) {
                DB::exec("INSERT INTO pay_tg_chat_history (customer_id, role, content) VALUES (?, ?, ?)", [$cid, $role, $content]);
                // 限制保留最近 10 条
                DB::exec("DELETE FROM pay_tg_chat_history WHERE customer_id=? AND id NOT IN (SELECT id FROM (SELECT id FROM pay_tg_chat_history WHERE customer_id=? ORDER BY id DESC LIMIT 10) t)", [$cid, $cid]);
            }
        } catch (Exception $e) {}
    }

    public static function chat($cid, $text) {
        // 绝对拦截：AI 绝不处理 /id 指令
        if (preg_match('/^\/id(@\w+)?$/i', trim($text))) {
            return "INTERCEPTED_ID_CMD"; 
        }

        self::init();
        if (empty(self::$apiKey)) return '系统提示：未配置 API KEY';

        // 读取最近 6 条历史记录
        $history = [];
        try {
            $dbFile = __DIR__ . '/../config.php';
            if (file_exists($dbFile)) require_once $dbFile;
            if (class_exists('DB')) {
                $rows = DB::query("SELECT role, content FROM pay_tg_chat_history WHERE customer_id=? ORDER BY id ASC LIMIT 6", [$cid]);
                if ($rows) {
                    foreach ($rows as $r) $history[] = ['role' => $r['role'], 'content' => $r['content']];
                }
            }
        } catch (Exception $e) {}

        $messages = [['role' => 'system', 'content' => self::systemPrompt()]];
        foreach ($history as $h) $messages[] = $h;
        $messages[] = ['role' => 'user', 'content' => $text];

        $data = [
            'model' => defined('DEEPSEEK_MODEL') ? DEEPSEEK_MODEL : 'deepseek-chat',
            'messages' => $messages,
            'temperature' => 0.3,
            'max_tokens' => 800
        ];

        $ch = curl_init(self::$apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . self::$apiKey
            ],
            CURLOPT_TIMEOUT => 60
        ]);

        $resp = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) return 'AI 服务连接失败，请稍后再试。';
        $json = json_decode($resp, true);
        if (isset($json['choices'][0]['message']['content'])) {
            $reply = trim($json['choices'][0]['message']['content']);
            // 代码兜底：把 AI 万一发出的 ** 转成 TG 支持的 <b>
            $reply = preg_replace('/\*\*(.+?)\*\*/s', '<b>$1</b>', $reply);
            return $reply;
        }
        return 'AI 返回异常，请稍后再试。';
    }
}