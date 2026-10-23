<?php

class mapay_plugin
{
    static public $info = [
        'name'     => 'mapay',
        'showname' => '码支付（商户代收）',
        'author'   => 'HeimaoPay',
        'link'     => '',
        'types'    => ['alipay','wxpay','bank'],
        'transtypes' => [],
        'inputs' => [
            'appkey' => [
                'name' => '监听密钥',
                'type' => 'input',
                'note' => '监听 App 推送时校验用，自定义一串随机字符串',
            ],
        ],
        'select' => [
            'wechat' => '微信收款码',
            'alipay' => '支付宝收款码',
            'bank'   => '银行卡转账',
        ],
        'note' => '<p>1. 先在 <b>收款码管理</b> 上传多个码<br>2. 系统按"金额区间/日限额/使用次数最少"自动选码<br>3. 到账确认需配监听 App 或云喇叭<br>4. 支持聚合码回退</p>',
        'bindwxmp' => false,
        'bindwxa'  => false,
    ];

    static public function submit(){
        global $DB, $channel, $order, $ordername, $conf, $clientip;

        $uid   = intval($order['uid']);
        $money = floatval($order['realmoney'] > 0 ? $order['realmoney'] : $order['money']);

        // 1. 按订单 type 映射候选通道（细粒度 + 脏数据兼容 + 聚合码回退）
        $candidates = self::candidateChannels($order['type']);
        if (empty($candidates)) {
            return ['type'=>'error', 'msg'=>'暂不支持的支付方式'];
        }

        // 2. 惰性日重置：该商户所有 today_date 过期的码清零
        $DB->exec(
            "UPDATE pay_qrcode SET today_count=0, today_amount=0, today_date=CURDATE()
             WHERE uid=:uid AND (today_date IS NULL OR today_date < CURDATE())",
            [':uid'=>$uid]
        );

        // 3. 统一通道找码（优先路由，其次按权重随机）
        $qrcode = null;
        $mainCh = null;
        if ($order['type'] == 1) $mainCh = 'alipay';
        elseif ($order['type'] == 2) $mainCh = 'wechat';
        elseif ($order['type'] == 4) $mainCh = 'bank';

        if ($mainCh) {
            $qrcode = self::pickWithRouteOrChildren($DB, $uid, $mainCh, $money);
        }
        // 4. 兜底：不限通道
        if (!$qrcode) {
            $qrcode = self::pickWithRouteOrChildren($DB, $uid, null, $money);
        }

        if (!$qrcode) {
            return ['type'=>'error', 'msg'=>'当前商户暂无可用的收款码，请联系客服'];
        }

        // 5. 累加使用次数（today_* 留给 Order::complete 累加，避免未支付订单占额度）
        $DB->exec(
            "UPDATE pay_qrcode SET use_count=use_count+1, last_used=:t WHERE id=:id",
            [':t'=>time(), ':id'=>$qrcode['id']]
        );

        // 6. 写订单 param（raw SQL 绕开 pay_ 前缀坑）
        // 只往 ext 里写 qrcode_id，其他字段都能从 pay_qrcode 查
        // 不动 param（那是商户自定义参数）
        $extData = json_encode([
            'qrcode_id'  => $qrcode['id'],
            'qrcode_uid' => (int)$qrcode['uid'],
        ], JSON_UNESCAPED_UNICODE);

        $DB->exec("UPDATE pay_order SET ext=:e WHERE trade_no=:t",
                  [':e'=>$extData, ':t'=>$order['trade_no']]);

        // ===== 纯本地内联推送（拉起即推送） =====
        @file_put_contents('/tmp/push_debug.log', date('H:i:s') . " | 进入推送代码\n", FILE_APPEND);
        $__tradeNo = $order['trade_no'];
        // 🔥 暴力修复：从数据库重新拉取 ext，防止变量丢失
        $__db_ext = $DB->getColumn("SELECT ext FROM pay_order WHERE trade_no=:t", [':t'=>$__tradeNo]);
        $__ext = json_decode($__db_ext ?: $extData, true);
        $__qid = (int)($__ext['qrcode_id'] ?? 0);
        $__qrcodeUid = (int)($__ext['qrcode_uid'] ?? 0);
        
        if ($__qid > 0) {
            $__bc = null;
            $__curUid = $__qrcodeUid;
            while ($__curUid > 0) {
                $__bc = $DB->getRow("SELECT * FROM pay_user_botconfig WHERE uid=:u AND use_own_bot=1 AND own_bot_token IS NOT NULL AND own_bot_token != ''", [':u'=>$__curUid]);
                if ($__bc) break;
                $__curUid = (int)$DB->getColumn("SELECT upid FROM pay_user WHERE uid=:u", [':u'=>$__curUid]);
            }
            if ($__bc) {
                @file_put_contents('/tmp/push_debug.log', date('H:i:s') . " | 找到Bot配置\n", FILE_APPEND);
                $__target = $__bc['own_bot_groupid'] ?: $__bc['own_bot_chatid'];
                if ($__target) {
                    $__exists = $DB->getRow("SELECT id FROM pay_tg_pending_order WHERE trade_no=:t", [':t'=>$__tradeNo]);
                    if (!$__exists) {
                        $DB->exec("INSERT INTO pay_tg_pending_order (trade_no, uid, amount, pkg, tg_chat_id, status) VALUES (?,?,?,?,?,0)", [$__tradeNo, $order['uid'], $order['money'], 'hook', 0]);
                        $__pendingId = $DB->lastInsertId();
                        @file_put_contents('/tmp/push_debug.log', date('H:i:s') . " | 插入PendingId={$__pendingId}\n", FILE_APPEND);

                        $__qr = $DB->getRow("SELECT remark, pay_name FROM pay_qrcode WHERE id=:i", [':i'=>$__qid]);
                        $__payee = $__qr['remark'] ?: ($__qr['pay_name'] ?: '未命名');
                        $__msg = "⏳ <b>新订单待支付</b>\n"
                               . "👤 收款人：<b>{$__payee}</b>\n"
                               . "💰 金额：<b>￥" . number_format($order['money'], 2) . "</b>\n"
                               . "🔑 订单：<code>{$__tradeNo}</code>\n"
                               . "💡 客户已拉起支付，请留意收款。";
                        
                        $__kb = ['inline_keyboard' => [[
                            ['text' => '✅ 确认到账', 'callback_data' => "conf_ok_{$__pendingId}"],
                            ['text' => '❌ 忽略', 'callback_data' => "conf_no_{$__pendingId}"]
                        ]]];

                        @file_put_contents('/tmp/push_debug.log', date('H:i:s') . " | 准备加载 bot_sender.php\n", FILE_APPEND);
                        require_once dirname(__DIR__) . '/../includes/bot_sender.php';
                        @file_put_contents('/tmp/push_debug.log', date('H:i:s') . " | 加载完毕，准备发送给 {$__target}\n", FILE_APPEND);
                        
                        foreach (explode(',', $__target) as $__t) {
                            $__res = botSend($__bc['own_bot_token'], trim($__t), $__msg, $__kb);
                            @file_put_contents('/tmp/push_debug.log', date('H:i:s') . " | 发送结果: " . json_encode($__res) . "\n", FILE_APPEND);
                        }
                    } else {
                        @file_put_contents('/tmp/push_debug.log', date('H:i:s') . " | 订单已存在于pending表，跳过\n", FILE_APPEND);
                    }
                } else {
                    @file_put_contents('/tmp/push_debug.log', date('H:i:s') . " | 目标群为空\n", FILE_APPEND);
                }
            } else {
                @file_put_contents('/tmp/push_debug.log', date('H:i:s') . " | 没找到Bot配置\n", FILE_APPEND);
            }
        } else {
            @file_put_contents('/tmp/push_debug.log', date('H:i:s') . " | qid为0\n", FILE_APPEND);
        }

        // 7. 跳转到付款展示页
        return ['type'=>'jump', 'url'=>'/paypage/mapay.php?trade_no='.$order['trade_no']];
    }

    // 订单 type → 候选收款码 channel 列表（按优先级）
    // type: 1=alipay, 2=wxpay, 3=qqpay, 4=bank
    static private function candidateChannels($type){
        $type = intval($type);
        if ($type == 1) { // 支付宝
            return [
                'alipay_personal','alipay_biz','alipay_card','alipay_receipt',
                'alipay_aa','alipay_pouch','alipay_account','alipay_token',
                'alipay', // 脏数据兼容
                'aggregate_alipay','aggregate_both',
            ];
        }
        if ($type == 2) { // 微信
            return [
                'wechat_personal','wechat_biz','wechat_reward',
                'wechat_bank','wechat_phone',
                'wechat', // 脏数据兼容
                'aggregate_wechat','aggregate_both',
            ];
        }
        if ($type == 4) { // 银行卡
            return ['bank','wechat_bank','alipay_account'];
        }
        return [];
    }

    // 选一条符合金额/日限额的码；$ch=null 表示不限通道
    // 先找本人码，找不到再找下级（多级）
    static public function pickWithChildren($DB, $uid, $ch, $money){
        // 1. 本人
        $q = self::pickOne($DB, $uid, $ch, $money);
        if ($q) return $q;
        // 2. 下级（BFS 遍历，最多 3 层）
        $seen = [(int)$uid];
        $queue = [(int)$uid];
        $depth = 0;
        while ($queue && $depth < 3) {
            $nextQueue = [];
            foreach ($queue as $u) {
                $kids = $DB->getAll("SELECT uid FROM pay_user WHERE upid=:u AND sub_level>1", [":u"=>$u]);
                foreach ($kids as $k) {
                    $ku = (int)$k["uid"];
                    if (in_array($ku, $seen)) continue;
                    $seen[] = $ku;
                    $q = self::pickOne($DB, $ku, $ch, $money);
                    if ($q) return $q;
                    $nextQueue[] = $ku;
                }
            }
            $queue = $nextQueue;
            $depth++;
        }
        return null;
    }


    static private function pickOne($DB, $uid, $ch, $money){
        // 【关键修复】强制将所有支付宝细分通道归一化为大类，让权重池包含所有同类码
        if ($ch !== null) {
            if (strpos($ch, 'alipay') === 0 || $ch === 'aggregate_alipay' || $ch === 'aggregate_both') {
                $ch = 'alipay';
            } elseif (strpos($ch, 'wechat') === 0 || $ch === 'aggregate_wechat') {
                $ch = 'wechat';
            }
        }

        // 客户省份（一次取）
        static $userProv = null;
        if ($userProv === null) {
            $userProv = function_exists('_mapay_user_province') ? _mapay_user_province() : '';
        }

        $sql = "SELECT * FROM pay_qrcode WHERE uid=:uid AND status=1";
        $params = [':uid'=>$uid];

        if ($ch !== null) {
            if ($ch === 'alipay') {
                $sql .= " AND (channel LIKE 'alipay%' OR channel LIKE 'aggregate%')";
            } elseif ($ch === 'wechat') {
                $sql .= " AND (channel LIKE 'wechat%' OR channel LIKE 'aggregate%')";
            } else {
                $sql .= " AND channel=:ch";
                $params[':ch'] = $ch;
            }
        }

        $sql .= " AND (min_amount = 0 OR min_amount <= :m1)";
        $params[':m1'] = $money;
        $sql .= " AND (max_amount = 0 OR max_amount >= :m2)";
        $params[':m2'] = $money;
        $sql .= " AND (day_limit = 0 OR today_count < day_limit)";
        $sql .= " AND (day_max_amount = 0 OR today_amount + :m3 <= day_max_amount)";
        $params[':m3'] = $money;

        $cands = $DB->getAll($sql, $params);
        if (!$cands) return null;

        // 1. 客户省份已知 → 优先匹配（region_province 或 bind_province）
        if ($userProv !== '') {
            $matched = [];
            foreach ($cands as $c) {
                $rp = trim($c['region_province'] ?? '');
                $bp = trim($c['bind_province'] ?? '');
                if ($rp === $userProv || $bp === $userProv) $matched[] = $c;
            }
            if (!empty($matched)) $cands = $matched;
        }

        // 2. 单个直接返回
        if (count($cands) === 1) return $cands[0];

        // 3. 绝对公平轮询：优先使用次数最少的（保证每个码都被均匀派到）
        // 先按 use_count 排序
        usort($cands, function($a,$b){ return $a['use_count'] - $b['use_count']; });
        
        // 取出使用次数最少的那些码，组成新的候选池
        $minCount = (int)$cands[0]['use_count'];
        $pool = [];
        foreach ($cands as $c) {
            if ((int)$c['use_count'] === $minCount) {
                $pool[] = $c;
            }
        }
        
        // 如果最少次数的码只有一个，直接返回它！
        if (count($pool) === 1) return $pool[0];
        
        // 如果最少次数的码有多个，再按 weight 加权随机
        $total = 0;
        foreach ($pool as $c) {
            $w = (int)($c['weight'] ?? 100);
            if ($w < 1) $w = 1;
            $total += $w;
        }
        if ($total > 0) {
            $roll = mt_rand(1, $total);
            $acc = 0;
            foreach ($pool as $c) {
                $w = (int)($c['weight'] ?? 100);
                if ($w < 1) $w = 1;
                $acc += $w;
                if ($roll <= $acc) return $c;
            }
        }
        
        return $pool[0];
    }
    // __WEIGHT_PATCH_END__
    static public function pickByIds($DB, $ids, $money){
        // 供路由使用：按指定 id 列表选（含金额/日限额过滤）
        if (empty($ids)) return null;
        $in = implode(',', array_map('intval', $ids));
        return $DB->getRow("SELECT * FROM pay_qrcode WHERE id IN ($in) AND status=1
            AND (min_amount=0 OR min_amount<=:m1) AND (max_amount=0 OR max_amount>=:m2)
            AND (day_limit=0 OR today_count<day_limit)
            AND (day_max_amount=0 OR today_amount+:m3<=day_max_amount)
            ORDER BY use_count ASC LIMIT 1",
            [':m1'=>$money, ':m2'=>$money, ':m3'=>$money]);
    }
    static public function pickByWeight($DB, $ids, $money){
        // 供路由使用：按指定 id 列表 + 权重随机
        if (empty($ids)) return null;
        $in = implode(',', array_map('intval', $ids));
        $cands = $DB->getAll("SELECT * FROM pay_qrcode WHERE id IN ($in) AND status=1
            AND (min_amount=0 OR min_amount<=:m1) AND (max_amount=0 OR max_amount>=:m2)
            AND (day_limit=0 OR today_count<day_limit)
            AND (day_max_amount=0 OR today_amount+:m3<=day_max_amount)",
            [':m1'=>$money, ':m2'=>$money, ':m3'=>$money]);
        if (!$cands) return null;
        if (count($cands)===1) return $cands[0];
        $total = 0;
        foreach ($cands as $c){ $w=(int)($c['weight'] ?? 100); if($w<1)$w=1; $total+=$w; }
        if ($total>0){
            $roll = mt_rand(1,$total); $acc=0;
            foreach ($cands as $c){ $w=(int)($c['weight'] ?? 100); if($w<1)$w=1; $acc+=$w; if($roll<=$acc)return $c; }
        }
        return $cands[0];
    }
    // __WEIGHT_PATCH_END__
    static public function pickByRoute($DB, $uid, $ch, $money){
        // 按 pay_qrcode_route 规则选码
        static $routeLoaded = null;
        if ($routeLoaded === null) {
            $routeLoaded = [];
            if ($DB->getColumn("SHOW TABLES LIKE 'pay_qrcode_route'")) {
                $routeLoaded = $DB->getAll("SELECT * FROM pay_qrcode_route WHERE uid=:u AND status=1 ORDER BY priority ASC, id ASC", ['":u'=>$uid]) ?: [];
            }
        }
        if (!$routeLoaded) return null;

        // 取客户省份（一次）
        $userProv = function_exists('_mapay_user_province') ? _mapay_user_province() : '';

        foreach ($routeLoaded as $rule) {
            $mv = json_decode($rule['match_value'], true) ?: [];
            $hit = false;
            switch ($rule['match_type']) {
                case 'province':
                    if ($userProv !== '' && in_array($userProv, $mv['provinces'] ?? [])) $hit = true;
                    break;
                case 'city':
                    // 城市匹配暂用 province 兜底
                    break;
                case 'weight':
                    $hit = true; // 权重规则无条件命中
                    break;
                case 'all':
                    $hit = true;
                    break;
            }
            if ($hit) {
                $ids = $mv['qrcode_ids'] ?? [];
                if (!$ids) return null;
                if (($rule['match_type'] ?? '') === 'weight' || count($ids) > 1) {
                    return self::pickByWeight($DB, $ids, $money);
                }
                return self::pickByIds($DB, $ids, $money);
            }
        }
        return null;
    }
    // __WEIGHT_PATCH_END__
    static public function pickWithRouteOrChildren($DB, $uid, $ch, $money){
        // 优先路由规则 → 无则走原逻辑
        $r = self::pickByRoute($DB, $uid, $ch, $money);
        if ($r) return $r;
        return self::pickWithChildren($DB, $uid, $ch, $money);
    }
    // __WEIGHT_PATCH_END__
}
