<?php
/**
 * 快捷查询命令处理
 * 从 handleUpdate 路由过来
 */

class Commands {

    // 命令入口，返回 true 表示已处理
    public static function dispatch($chat_id, $text, $user_id, $token = null) {
        // 切换 bot token（不传=用默认主 bot）
        if (class_exists('Telegram') && method_exists('Telegram', 'useToken')) {
            \Telegram::useToken($token);
        }
        $text = trim($text);

        // 计算器：纯算式
        if (preg_match('#^[-+0-9().\s*/%*×÷]+$#u', $text) && preg_match('#[-+*/%]#', $text)) {
            self::calc($chat_id, $text); return true;
        }
        if (preg_match('#^(?:算式|计算|算)\s*[:：]?\s*(.+)$#u', $text, $m)) {
            self::calc($chat_id, trim($m[1])); return true;
        }

        // 查IP
        if (preg_match('#^(?:查\s*)?[iI][pP]\s*[:：]?\s*([\d.]{7,15})$#u', $text, $m)) {
            self::queryIp($chat_id, $m[1]); return true;
        }

        // 天气
        if (preg_match('#^(?:查\s*)?(?:天气|tq)\s*[:：]?\s*(.+)$#u', $text, $m)) {
            self::weather($chat_id, trim($m[1])); return true;
        }

        // 二维码
        if (preg_match('#^(?:(?:生成|制作|做)\s*)?(?:二维码|qr)\s*[:：]?\s*(.+)$#us', $text, $m)) {
            self::qrcode($chat_id, trim($m[1])); return true;
        }

        // 翻译
        if (preg_match('#^(?:翻译|fy|translate)\s*[:：]?\s*(.+)$#us', $text, $m)) {
            self::translate($chat_id, trim($m[1])); return true;
        }

        // 身份证
        if (preg_match('#^(?:查\s*)?(?:身份证|sfz)\s*[:：]?\s*(\d{17}[\dXx])$#u', $text, $m)) {
            self::idcard($chat_id, strtoupper($m[1])); return true;
        }

        // 车牌
        if (preg_match('#^(?:查\s*)?(?:车牌|cp)\s*[:：]?\s*([京津沪渝冀豫云辽黑湘皖鲁新苏浙赣鄂桂甘晋蒙陕吉闽贵粤青藏川宁琼])([A-Z])?$#u', $text, $m)) {
            self::plate($chat_id, $m[1], $m[2] ?? ''); return true;
        }

        // 区号
        if (preg_match('#^(?:查\s*)?(?:区号|qh)\s*[:：]?\s*(.+)$#u', $text, $m)) {
            self::areaCode($chat_id, trim($m[1])); return true;
        }

        // 邮编
        if (preg_match('#^(?:查\s*)?(?:邮编|yb)\s*[:：]?\s*(.+)$#u', $text, $m)) {
            self::zipCode($chat_id, trim($m[1])); return true;
        }

        // Z0 商家汇率
        if (in_array(strtoupper($text), ['Z0', 'Z0汇率', '商家汇率', 'OKX商家', 'OKX商家汇率', 'Z0汇'])) {
            self::okxMerchants($chat_id); return true;
        }

        // 查汇率
        if (in_array($text, ['查汇率', '汇率', 'usdt汇率', 'USDT汇率', '实时汇率', '查usdt', 'USDT价格', 'usdt价格'])) {
            self::okxRate($chat_id); return true;
        }

        // 外汇
        if (in_array($text, ['外汇', '外汇牌价', '查外汇', 'forex', '法币汇率', '外币汇率'])) {
            self::forex($chat_id); return true;
        }

        // TRON（支持 "查 T地址"、"查T地址"、"T地址" 或直接贴地址）
        if (preg_match('#^(?:查\s*)?T?地址?\s+(T[1-9A-HJ-NP-Za-km-z]{33})$#u', $text, $m)
            || preg_match('#^查\s*(T[1-9A-HJ-NP-Za-km-z]{33})$#u', $text, $m)
            || preg_match('#^(T[1-9A-HJ-NP-Za-km-z]{33})$#u', $text, $m)) {
            self::tron($chat_id, $m[1]); return true;
        }

        // 监控地址（支持 "监控 T地址 xxx"、"监控 xxx"）
        if (preg_match('#^(?:监控|监听)\s+(?:T?地址?\s+)?(T[1-9A-HJ-NP-Za-km-z]{33})(?:\s+(.+))?$#u', $text, $m)) {
            self::watchAdd($chat_id, $m[1], trim($m[2] ?? '')); return true;
        }
        // 取消监控（支持 "取消监控 T地址 xxx"）
        if (preg_match('#^(?:取消监控|取消监听|停止监控|停止监听)\s+(?:T?地址?\s+)?(T[1-9A-HJ-NP-Za-km-z]{33})$#u', $text, $m)) {
            self::watchDel($chat_id, $m[1]); return true;
        }
        // 我的监控列表
        if (in_array($text, ['监控列表', '我的监控', '监听列表', '我的监听', '地址监控', '监控地址', '查询监控'])) {
            self::watchList($chat_id); return true;
        }

        // 获取群组ID
        if (in_array($text, ['获取群组', '获取群ID', '查群ID', '群ID', '获取群组ID', '群组ID', '群组id', '群id', '获取群id', '本群id', '本群ID', '查本群'])) {
            self::groupInfo($chat_id, $GLOBALS['__tg_msg__'] ?? null); return true;
        }

        // ETH
        if (preg_match('#^(?:查\s*)?(?:ETH|eth|以太坊|以太)?\s*(?:地址)?\s*[:：]?\s*(0x[a-fA-F0-9]{40})$#', $text, $m)) {
            self::eth($chat_id, $m[1]); return true;
        }

        return false;
    }

    // ================= 计算器 =================
    private static function calc($chat_id, $expr) {
        $expr = str_replace(['×','÷','（','）'], ['*','/','(',')'], $expr);
        $expr = trim($expr);
        if (!preg_match('#^[0-9+\-*/%.()\s]+$#', $expr)) {
            Telegram::sendMessage($chat_id, "❌ 只支持数字和 + - * / ( )");
            return;
        }
        if (strlen($expr) > 200) {
            Telegram::sendMessage($chat_id, "❌ 表达式过长");
            return;
        }
        try {
            $result = self::safeEval($expr);
            if (!is_numeric($result)) throw new Exception('无效');
            if (is_float($result) && floor($result) == $result && abs($result) < 1e15) {
                $result = (int)$result;
            }
            Telegram::sendMessage($chat_id, "🧮 <b>计算结果</b>\n━━━━━━━━━━━━━━\n表达式：<code>{$expr}</code>\n结果：<b>{$result}</b>");
        } catch (Exception $e) {
            Telegram::sendMessage($chat_id, "❌ 计算失败");
        }
    }

    // 安全表达式求值器（递归下降，不依赖 eval）
    private static function safeEval($expr) {
        $s = preg_replace('/\s+/', '', $expr);
        $i = 0;
        $v = self::parseExpr($s, $i);
        if ($i !== strlen($s)) throw new Exception('表达式错误');
        return $v;
    }
    private static function parseExpr(&$s, &$i) {
        $len = strlen($s);
        $v = self::parseTerm($s, $i);
        while ($i < $len && ($s[$i] === '+' || $s[$i] === '-')) {
            $op = $s[$i++];
            $r = self::parseTerm($s, $i);
            $v = $op === '+' ? $v + $r : $v - $r;
        }
        return $v;
    }
    private static function parseTerm(&$s, &$i) {
        $len = strlen($s);
        $v = self::parseFactor($s, $i);
        while ($i < $len && ($s[$i] === '*' || $s[$i] === '/' || $s[$i] === '%')) {
            $op = $s[$i++];
            $r = self::parseFactor($s, $i);
            if (($op === '/' || $op === '%') && $r == 0) throw new Exception('除零');
            if ($op === '*') $v = $v * $r;
            elseif ($op === '/') $v = $v / $r;
            else $v = fmod($v, $r);
        }
        return $v;
    }
    private static function parseFactor(&$s, &$i) {
        $len = strlen($s);
        if ($i >= $len) throw new Exception('意外结束');
        if ($s[$i] === '-') { $i++; return -self::parseFactor($s, $i); }
        if ($s[$i] === '+') { $i++; return  self::parseFactor($s, $i); }
        if ($s[$i] === '(') {
            $i++;
            $v = self::parseExpr($s, $i);
            if ($i >= $len || $s[$i] !== ')') throw new Exception('括号不匹配');
            $i++;
            return $v;
        }
        $start = $i;
        while ($i < $len && (ctype_digit($s[$i]) || $s[$i] === '.')) $i++;
        if ($start === $i) throw new Exception('非法字符');
        return (float)substr($s, $start, $i - $start);
    }

    // ================= IP 查询 =================
    private static function queryIp($chat_id, $ip) {
        $r = self::curl("http://ip-api.com/json/{$ip}?lang=zh-CN");
        $j = json_decode($r, true);
        if (!$j || ($j['status'] ?? '') != 'success') {
            Telegram::sendMessage($chat_id, "❌ IP 查询失败"); return;
        }
        $text = "🌐 <b>IP 归属地</b>\n━━━━━━━━━━━━━━\n"
              . "IP：<code>{$j['query']}</code>\n"
              . "国家：{$j['country']}\n"
              . "地区：{$j['regionName']}\n"
              . "城市：{$j['city']}\n"
              . "运营商：{$j['isp']}\n"
              . "时区：{$j['timezone']}";
        Telegram::sendMessage($chat_id, $text);
    }

    // ================= 天气 =================
    private static function weather($chat_id, $city) {
        $r = self::curl("https://wttr.in/" . urlencode($city) . "?format=j1");
        $j = json_decode($r, true);
        if (!$j || empty($j['current_condition'])) {
            Telegram::sendMessage($chat_id, "❌ 天气查询失败：{$city}"); return;
        }
        $c = $j['current_condition'][0];
        $desc = $c['lang_zh'][0]['value'] ?? $c['weatherDesc'][0]['value'] ?? '';
        $text = "🌤 <b>天气预报 · {$city}</b>\n━━━━━━━━━━━━━━\n"
              . "温度：{$c['temp_C']}°C（体感 {$c['FeelsLikeC']}°C）\n"
              . "天气：{$desc}\n"
              . "湿度：{$c['humidity']}%\n"
              . "风：{$c['winddir16Point']} {$c['windspeedKmph']} km/h";
        Telegram::sendMessage($chat_id, $text);
    }

    // ================= 二维码 =================
    private static function qrcode($chat_id, $content) {
        if (mb_strlen($content) > 1500) {
            Telegram::sendMessage($chat_id, "❌ 内容过长"); return;
        }
        $url = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=' . urlencode($content);
        $text = "🔲 <b>二维码生成</b>\n━━━━━━━━━━━━━━\n内容：" . htmlspecialchars(mb_substr($content, 0, 50)) . "\n\n[查看大图]({$url})";
        Telegram::sendMessage($chat_id, $text);
    }

    // ================= 翻译 =================
    private static function translate($chat_id, $text) {
        $langpair = preg_match('#[\x{4e00}-\x{9fa5}]#u', $text) ? 'zh-CN|en' : 'en|zh-CN';
        $r = self::curl("https://api.mymemory.translated.net/get?q=" . urlencode($text) . "&langpair={$langpair}");
        $j = json_decode($r, true);
        $res = $j['responseData']['translatedText'] ?? '';
        if (!$res) {
            Telegram::sendMessage($chat_id, "❌ 翻译失败"); return;
        }
        $dir = $langpair == 'zh-CN|en' ? '中 → 英' : '英 → 中';
        Telegram::sendMessage($chat_id, "🌐 <b>在线翻译（{$dir}）</b>\n━━━━━━━━━━━━━━\n原文：{$text}\n译文：<b>{$res}</b>");
    }

    // ================= 身份证 =================
    private static function idcard($chat_id, $idc) {
        $weights = [7,9,10,5,8,4,2,1,6,3,7,9,10,5,8,4,2];
        $checkmap = '10X98765432';
        $sum = 0;
        for ($i = 0; $i < 17; $i++) $sum += intval($idc[$i]) * $weights[$i];
        $check = $checkmap[$sum % 11];
        $ok = ($check === $idc[17]);
        $birth = substr($idc, 6, 8);
        $sex = intval($idc[16]) % 2 ? '男' : '女';
        $text = "🪪 <b>身份证验证</b>\n━━━━━━━━━━━━━━\n"
              . "号码：<code>{$idc}</code>\n"
              . "地区码：{$idc[0]}{$idc[1]}{$idc[2]}{$idc[3]}{$idc[4]}{$idc[5]}\n"
              . "出生：{$birth}\n"
              . "性别：{$sex}\n"
              . "结果：" . ($ok ? '✅ 校验通过' : '❌ 校验失败');
        Telegram::sendMessage($chat_id, $text);
    }

    // ================= 车牌 =================
    private static function plate($chat_id, $prov, $letter) {
        $map = ['京'=>'北京','津'=>'天津','沪'=>'上海','渝'=>'重庆','冀'=>'河北','豫'=>'河南','云'=>'云南','辽'=>'辽宁','黑'=>'黑龙江','湘'=>'湖南','皖'=>'安徽','鲁'=>'山东','新'=>'新疆','苏'=>'江苏','浙'=>'浙江','赣'=>'江西','鄂'=>'湖北','桂'=>'广西','甘'=>'甘肃','晋'=>'山西','蒙'=>'内蒙古','陕'=>'陕西','吉'=>'吉林','闽'=>'福建','贵'=>'贵州','粤'=>'广东','青'=>'青海','藏'=>'西藏','川'=>'四川','宁'=>'宁夏','琼'=>'海南'];
        $provName = $map[$prov] ?? '未知';
        Telegram::sendMessage($chat_id, "🚗 <b>车牌归属</b>\n━━━━━━━━━━━━━━\n车牌：{$prov}{$letter}\n省份：{$provName}");
    }

    // ================= 区号 =================
    private static function areaCode($chat_id, $city) {
        $map = ['北京'=>'010','上海'=>'021','广州'=>'020','深圳'=>'0755','天津'=>'022','重庆'=>'023','成都'=>'028','武汉'=>'027','南京'=>'025','杭州'=>'0571','西安'=>'029','沈阳'=>'024','长沙'=>'0731','郑州'=>'0371','济南'=>'0531','青岛'=>'0532','大连'=>'0411','厦门'=>'0592','福州'=>'0591','昆明'=>'0871','贵阳'=>'0851','南宁'=>'0771','海口'=>'0898','哈尔滨'=>'0451','长春'=>'0431','石家庄'=>'0311','太原'=>'0351','合肥'=>'0551','南昌'=>'0791','兰州'=>'0931','西宁'=>'0971','银川'=>'0951','乌鲁木齐'=>'0991','呼和浩特'=>'0471','拉萨'=>'0891'];
        foreach ($map as $k => $v) {
            if (mb_strpos($city, $k) !== false || mb_strpos($k, $city) !== false) {
                Telegram::sendMessage($chat_id, "📞 <b>城市区号</b>\n━━━━━━━━━━━━━━\n城市：{$k}\n区号：<b>{$v}</b>");
                return;
            }
        }
        Telegram::sendMessage($chat_id, "❌ 未收录城市：{$city}");
    }

    // ================= 邮编 =================
    private static function zipCode($chat_id, $city) {
        $map = ['北京'=>'100000','上海'=>'200000','广州'=>'510000','深圳'=>'518000','天津'=>'300000','重庆'=>'400000','成都'=>'610000','武汉'=>'430000','南京'=>'210000','杭州'=>'310000','西安'=>'710000','沈阳'=>'110000','长沙'=>'410000','郑州'=>'450000','济南'=>'250000','青岛'=>'266000','大连'=>'116000','厦门'=>'361000','福州'=>'350000','昆明'=>'650000','贵阳'=>'550000','南宁'=>'530000','海口'=>'570000','哈尔滨'=>'150000','长春'=>'130000','石家庄'=>'050000','太原'=>'030000','合肥'=>'230000','南昌'=>'330000','兰州'=>'730000','西宁'=>'810000','银川'=>'750000','乌鲁木齐'=>'830000','呼和浩特'=>'010000','拉萨'=>'850000'];
        foreach ($map as $k => $v) {
            if (mb_strpos($city, $k) !== false || mb_strpos($k, $city) !== false) {
                Telegram::sendMessage($chat_id, "📮 <b>城市邮编</b>\n━━━━━━━━━━━━━━\n城市：{$k}\n邮编：<b>{$v}</b>");
                return;
            }
        }
        Telegram::sendMessage($chat_id, "❌ 未收录城市：{$city}");
    }

    // ================= Z0 商家汇率 可切换 =================
    public static function okxMerchants($chat_id, $pm = 'aliPay') {
        $text = self::buildOkxText($pm);
        $keyboard = self::buildOkxKeyboard($pm);
        Telegram::sendMessage($chat_id, $text, $keyboard);
    }

    public static function editOkxMessage($chat_id, $message_id, $pm) {
        $text = self::buildOkxText($pm);
        $keyboard = self::buildOkxKeyboard($pm);
        Telegram::editMessageText($chat_id, $message_id, $text, $keyboard);
    }

    private static function buildOkxKeyboard($current) {
        $channels = [
            'aliPay' => '支付宝',
            'wxPay'  => '微信',
            'bank'   => '银行卡',
        ];
        $row = [];
        foreach ($channels as $pm => $label) {
            $mark = ($pm === $current) ? '✅ ' : '';
            $row[] = ['text' => $mark . $label, 'callback_data' => 'okx_' . $pm];
        }
        return ['inline_keyboard' => [$row]];
    }

    private static function buildOkxText($pm) {
        $label = ['aliPay'=>'支付宝','wxPay'=>'微信','bank'=>'银行卡'][$pm] ?? '支付宝';
        $url = "http://43.160.227.84:8081/v3/c2c/tradingOrders/books"
             . "?quoteCurrency=CNY&baseCurrency=USDT&side=sell"
             . "&paymentMethod={$pm}&userType=all&receivingAds=false"
             . "&t=" . (time() * 1000);
        $r = self::curl($url);
        $j = json_decode($r, true);
        $sells = $j['data']['sell'] ?? [];
        if (empty($sells)) {
            return "💰 <b>OKX {$label} 实时汇率</b>
━━━━━━━━━━━━━━
暂无数据";
        }
        $rows = [];
        $count = 0;
        foreach ($sells as $item) {
            if ($count >= 10) break;
            $price = $item['price'] ?? '--';
            $avail = floatval($item['availableAmount'] ?? 0);
            $availStr = self::humanAmount($avail);
            $nick = $item['nickName'] ?? '';
            if (mb_strlen($nick) > 8) $nick = mb_substr($nick, 0, 8) . '..';
            $n = str_pad($count + 1, 2, ' ', STR_PAD_LEFT);
            $p = str_pad($price, 6, ' ', STR_PAD_RIGHT);
            $a = str_pad($availStr, 9, ' ', STR_PAD_RIGHT);
            $rows[] = "{$n}  {$p} {$a} {$nick}";
            $count++;
        }
        return "💰 <b>OKX {$label} 实时汇率 TOP10</b>
"
             . "━━━━━━━━━━━━━━━━━━
"
             . "<pre>" . implode("
", $rows) . "</pre>
"
             . "🕐 " . date('H:i:s');
    }

    private static function humanAmount($n) {
        if ($n >= 1000000) return number_format($n / 1000000, 1) . 'M';
        if ($n >= 1000)    return number_format($n / 1000, 1) . 'K';
        return number_format($n, 0);
    }

    public static function handleCallback($cb, $token = null) {
        if (class_exists('Telegram') && method_exists('Telegram', 'useToken')) {
            \Telegram::useToken($token);
        }
        $data = $cb['data'] ?? '';
        $chat_id = $cb['message']['chat']['id'] ?? 0;

        // ========== 双向客服按钮 ==========
        if ($data === 'cb_biz') {
            Telegram::answerCallback($cb['id'], '已接入商务客服');
            Telegram::sendMessage($chat_id, "✅ 已接入 <b>【商务对接】</b>客服\n\n请直接发送您的问题或需求，客服稍后回复。");
            return true;
        }
        if ($data === 'cb_apk') {
            Telegram::answerCallback($cb['id'], '已接入 APK 客服');
            Telegram::sendMessage($chat_id, "✅ 已接入 <b>【APK 激活】</b>客服\n\n请直接发送您的问题（购买/激活/试用），客服稍后回复。");
            return true;
        }
        if ($data === 'cb_help') {
            Telegram::answerCallback($cb['id'], '已接入客服');
            Telegram::sendMessage($chat_id, "✅ 已接入 <b>【在线客服】</b>\n\n请直接描述您的问题，客服稍后回复。");
            return true;
        }
        // ==================================

        if (strpos($data, 'okx_') !== 0) return false;
        $pm = substr($data, 4);
        if (!in_array($pm, ['aliPay', 'wxPay', 'bank'])) {
            Telegram::answerCallback($cb['id'], '未知通道');
            return true;
        }
        Telegram::answerCallback($cb['id'], '已切换');
        self::editOkxMessage(
            $cb['message']['chat']['id'],
            $cb['message']['message_id'],
            $pm
        );
        return true;
    }

    // ================= OKX 汇率 =================
    private static function okxRate($chat_id) {
        $r = self::curl("https://www.okx.com/v3/c2c/tradingOrders/books?quoteCurrency=CNY&baseCurrency=USDT&side=sell&paymentMethod=all&userType=all&receivingAds=false");
        $j = json_decode($r, true);
        $sell = $j['data']['sell'][0]['price'] ?? null;
        $buy = $j['data']['buy'][0]['price'] ?? null;
        if (!$sell) { Telegram::sendMessage($chat_id, "❌ 汇率获取失败"); return; }
        $text = "💱 <b>OKX 实时汇率 USDT/CNY</b>\n━━━━━━━━━━━━━━\n"
              . "🟢 买价：{$buy}\n"
              . "🔴 卖价：{$sell}\n"
              . "🕐 " . date('Y-m-d H:i:s');
        Telegram::sendMessage($chat_id, $text);
    }

    // ================= 外汇 =================
    private static function forex($chat_id) {
        $r = self::curl("https://open.er-api.com/v6/latest/USD");
        $j = json_decode($r, true);
        $rates = $j['rates'] ?? [];
        if (!$rates) { Telegram::sendMessage($chat_id, "❌ 外汇获取失败"); return; }
        $text = "💱 <b>外汇牌价（基于 1 USD）</b>\n━━━━━━━━━━━━━━\n"
              . "🇨🇳 CNY：" . ($rates['CNY'] ?? '-') . "\n"
              . "🇪🇺 EUR：" . ($rates['EUR'] ?? '-') . "\n"
              . "🇯🇵 JPY：" . ($rates['JPY'] ?? '-') . "\n"
              . "🇬🇧 GBP：" . ($rates['GBP'] ?? '-') . "\n"
              . "🇭🇰 HKD：" . ($rates['HKD'] ?? '-') . "\n\n"
              . "更新：" . ($j['time_last_update_utc'] ?? '-');
        Telegram::sendMessage($chat_id, $text);
    }

    // ================= TRON =================
    private static function tron($chat_id, $addr) {
        $r = self::curl("https://apilist.tronscanapi.com/api/account?address={$addr}");
        $j = json_decode($r, true);
        if (!$j || empty($j['address'])) {
            Telegram::sendMessage($chat_id, "❌ 地址未激活或无数据
<code>{$addr}</code>");
            return;
        }

        $trx = ($j['balance'] ?? 0) / 1e6;
        $usdt = 0;
        foreach (($j['trc20token_balances'] ?? []) as $t) {
            if (($t['tokenId'] ?? '') == 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t') {
                $usdt = floatval($t['balance']) / pow(10, intval($t['tokenDecimal']));
            }
        }

        $txUrl = "https://apilist.tronscanapi.com/api/token_trc20/transfers"
               . "?limit=10&start=0&sort=-timestamp"
               . "&relatedAddress={$addr}"
               . "&contract_address=TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t";
        $txR = self::curl($txUrl);
        $txJ = json_decode($txR, true);
        $txs = $txJ['token_transfers'] ?? [];

        $rows = [];
        $count = 0;
        foreach ($txs as $tx) {
            if ($count >= 10) break;
            $dec = intval($tx['tokenInfo']['tokenDecimal'] ?? 6);
            $amt = floatval($tx['quant'] ?? 0) / pow(10, $dec);
            $toAddr = strtolower($tx['to_address'] ?? '');
            $isIn = ($toAddr === strtolower($addr));
            $flag = $isIn ? '⬇️' : '⬆️';
            $ts = intval($tx['block_ts'] ?? 0) / 1000;
            $tm = date('m-d H:i', $ts + 8 * 3600);
            $txid = $tx['transaction_id'] ?? '';
            $link = "https://tronscan.org/#/transaction/{$txid}";

            $peer = $isIn ? ($tx['from_address'] ?? '') : ($tx['to_address'] ?? '');
            $peerShort = substr($peer, 0, 6) . '..' . substr($peer, -4);
            $dir = $isIn ? '⬅️' : '➡️';   // 转入 = 来自, 转出 = 发往

            $n = str_pad($count + 1, 2, ' ', STR_PAD_LEFT);
            $amtStr = number_format($amt, 2);
            // 每笔一行：序号 方向 金额 对方地址 时间
            $rows[] = "{$n}. {$flag} <a href=\"{$link}\">{$amtStr} U</a> {$dir} <code>{$peerShort}</code>  <i>{$tm}</i>";
            $count++;
        }

        $text = "🔍 <b>TRON 地址查询</b>
"
              . "━━━━━━━━━━━━━━━━━━
"
              . "<code>{$addr}</code>
"
              . "💵 USDT：<b>" . number_format($usdt, 2) . "</b>  💰 TRX：<b>" . number_format($trx, 2) . "</b>
";

        if (!empty($rows)) {
            $text .= "━━━━━━━━━━━━━━━━━━
"
                   . "📜 <b>最近 10 笔 USDT 交易</b>（点金额看详情）

"
                   . implode("
", $rows);
        } else {
            $text .= "━━━━━━━━━━━━━━━━━━
暂无 USDT 交易";
        }

        Telegram::sendMessage($chat_id, $text);
    }

    // ================= ETH =================
    private static function eth($chat_id, $addr) {
        $r = self::curl("https://api.etherscan.io/api?module=account&action=balance&address={$addr}&tag=latest");
        $j = json_decode($r, true);
        if (($j['status'] ?? '') != '1') {
            Telegram::sendMessage($chat_id, "❌ ETH 查询失败（可能限速）"); return;
        }
        $eth = intval($j['result']) / 1e18;
        $text = "💎 <b>以太坊余额</b>\n━━━━━━━━━━━━━━\n地址：<code>{$addr}</code>\nETH：<b>" . number_format($eth, 6) . "</b>";
        Telegram::sendMessage($chat_id, $text);
    }

    // ================= curl 通用 =================
    private static function curl($url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; PayBot/1.0)',
        ]);
        $r = curl_exec($ch);
        curl_close($ch);
        return $r;
    }

    // ================= 地址监控 =================
    private static function watchAdd($chat_id, $addr, $remark) {
        $exists = DB::one("SELECT id FROM pay_tronwatch WHERE address=? AND chat_id=?", [$addr, $chat_id]);
        if ($exists) {
            Telegram::sendMessage($chat_id, "⚠️ 该地址已在监控中");
            return;
        }
        DB::exec("INSERT INTO pay_tronwatch(address, remark, chat_id, min_amount, status, addtime)
                  VALUES(?,?,?,1.00,1,NOW())", [$addr, $remark, $chat_id]);
        $text = "✅ <b>已添加监控</b>\n━━━━━━━━━━━━━━━━━━\n"
              . "地址：<code>{$addr}</code>\n"
              . ($remark ? "备注：{$remark}\n" : "")
              . "最小金额：1 USDT\n"
              . "━━━━━━━━━━━━━━━━━━\n"
              . "有 ≥1 USDT 的交易会自动推送到本" . ($chat_id > 0 ? "私聊" : "群") . "。";
        Telegram::sendMessage($chat_id, $text);
    }

    private static function watchDel($chat_id, $addr) {
        $n = DB::exec("DELETE FROM pay_tronwatch WHERE address=? AND chat_id=?", [$addr, $chat_id]);
        Telegram::sendMessage($chat_id, $n > 0 ? "✅ 已取消监控" : "❌ 未找到该监控");
    }

    private static function watchList($chat_id) {
        $rows = DB::query("SELECT * FROM pay_tronwatch WHERE chat_id=? ORDER BY id DESC", [$chat_id]);
        if (!$rows) {
            Telegram::sendMessage($chat_id, "📭 暂无监控地址\n\n用法：<code>监控 T地址 备注</code>");
            return;
        }
        $lines = ["📋 <b>我的监控地址</b>", "━━━━━━━━━━━━━━━━━━"];
        foreach ($rows as $i => $r) {
            $n = $i + 1;
            $status = $r['status'] ? '🟢' : '🔴';
            $lines[] = "{$status} <b>{$n}.</b> <code>{$r['address']}</code>";
            if ($r['remark']) $lines[] = "   备注：{$r['remark']}";
        }
        $lines[] = "━━━━━━━━━━━━━━━━━━";
        $lines[] = "取消：<code>取消监控 T地址</code>";
        Telegram::sendMessage($chat_id, implode("\n", $lines));
    }


    // ================= 获取群组信息 =================
    public static function groupInfo($chat_id, $msg) {
        // 不在群里就报错
        $type = $msg['chat']['type'] ?? 'private';
        if ($type === 'private') {
            Telegram::sendMessage($chat_id, "❌ 请在群内使用此命令\n\n群里发 <code>获取群组</code> 即可");
            return;
        }

        $title = $msg['chat']['title'] ?? '未知';
        $username = $msg['chat']['username'] ?? '';
        $typeName = ['group'=>'普通群', 'supergroup'=>'超级群', 'channel'=>'频道'][$type] ?? $type;

        $text = "👥 <b>群组信息</b>\n"
              . "━━━━━━━━━━━━━━\n"
              . "名称：{$title}\n";
        if ($username) {
            $text .= "用户名：@{$username}\n";
        }
        $text .= "类型：{$typeName}\n"
               . "ID：<code>{$chat_id}</code>\n";

        if (strpos((string)$chat_id, '-100') === 0) {
            $shortId = substr((string)$chat_id, 4);
            $text .= "短ID：<code>{$shortId}</code>\n";
        }
        $text .= "━━━━━━━━━━━━━━";

        Telegram::sendMessage($chat_id, $text);
    }

}