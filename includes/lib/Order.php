<?php
namespace lib;

class Order
{
    public static function freeze($trade_no){
        global $DB;
        $row = $DB->find('order', 'uid,getmoney,status,channel', ['trade_no'=>$trade_no]);
        if(!$row)
            return ['code'=>-1, 'msg'=>'当前订单不存在！'];
        if($row['status']!=1)
            return ['code'=>-1, 'msg'=>'只支持冻结已支付状态的订单'];
        $channel = \lib\Channel::get($row['channel']);
        if($channel['mode']==1)
            return ['code'=>-1, 'msg'=>'当前支付通道为商户直清，不支持冻结'];
        if($row['getmoney']>0){
            changeUserMoney($row['uid'], $row['getmoney'], false, '订单冻结', $trade_no);
            $DB->update('order', ['status'=>3], ['trade_no'=>$trade_no]);
        }
        return ['code'=>0, 'msg'=>'已成功从UID:'.$row['uid'].'冻结'.$row['getmoney'].'元余额'];
    }

    public static function unfreeze($trade_no){
        global $DB;
        $row = $DB->find('order', 'uid,getmoney,status,channel', ['trade_no'=>$trade_no]);
        if(!$row)
            return ['code'=>-1, 'msg'=>'当前订单不存在！'];
        if($row['status']!=3)
            return ['code'=>-1, 'msg'=>'只支持解冻已冻结状态的订单'];
        $channel = \lib\Channel::get($row['channel']);
        if($channel['mode']==1)
            return ['code'=>-1, 'msg'=>'当前支付通道为商户直清，不支持冻结'];
        if($row['getmoney']>0){
            changeUserMoney($row['uid'], $row['getmoney'], true, '订单解冻', $trade_no);
            $DB->update('order', ['status'=>1], ['trade_no'=>$trade_no]);
        }
        return ['code'=>0, 'msg'=>'已成功为UID:'.$row['uid'].'恢复'.$row['getmoney'].'元余额'];
    }

    public static function complete($trade_no, $amount = null){
        global $DB, $conf;

        $order = $DB->find('order', '*', ['trade_no'=>$trade_no]);
        if(!$order) return ['code'=>-1, 'msg'=>'订单不存在'];
        if($order['status'] != 0) return ['code'=>0, 'msg'=>'订单已处理'];

        $api_trade_no = !empty($order['api_trade_no'])
            ? $order['api_trade_no']
            : ($conf['order_prefix'] . date('YmdHis') . mt_rand(1000, 9999));

        // 1. 标记已支付（幂等：只更新 status=0）
        $DB->exec(
            "UPDATE pay_order SET status=1, endtime=NOW(), api_trade_no=:a
             WHERE trade_no=:t AND status=0",
            [':a'=>$api_trade_no, ':t'=>$trade_no]
        );

        // ===== 测试沙箱订单：跳过扣费 =====
        if (isset($order['is_test']) && (int)$order['is_test'] === 1) {
            $DB->exec(
                "UPDATE pay_order SET status=1, endtime=NOW(), api_trade_no=:a
                 WHERE trade_no=:t AND status=0",
                [':a'=>'SANDBOX' . date('YmdHis'), ':t'=>$trade_no]
            );
            return ['code'=>0, 'msg'=>'测试订单完成（未扣积分）'];
        }

                        // 错误死锁逻辑已被彻底铲除

        // 更新状态为已支付
        $DB->exec(
            "UPDATE pay_order SET status=1, endtime=NOW(), WHERE trade_no=:t AND status=0", 
            [':a'=>'PAY' . date('YmdHis'), ':t'=>$trade_no]
        );

        // 3. 商户主权结算引擎（系统不参与任何资金抽成）
        $extData2 = json_decode($order['ext'] ?? '', true) ?: [];
        $qrcodeUid = (int)($extData2['qrcode_uid'] ?? 0);
        $qrcodeId  = (int)($extData2['qrcode_id'] ?? 0);

        if ($qrcodeUid > 0 && $qrcodeUid !== (int)$order['uid']) {
            $orderMoney = (float)$order['money'];

            // 3.1 获取码商专属费率
            $floatRate = 0;
            if ($qrcodeId > 0) {
                $floatRate = (float)$DB->getColumn("SELECT float_rate FROM pay_qrcode WHERE id=:id", [':id'=>$qrcodeId]);
            }
            if ($floatRate <= 0) {
                $floatRate = (float)$DB->getColumn("SELECT rate FROM pay_user WHERE uid=:u", [':u'=>$qrcodeUid]);
            }

            // 计算码商需扣除的本金（比如 100元 10%费率，实际扣 90 元）
            $deductMoney = round($orderMoney * (100 - $floatRate) / 100, 2);
            if ($deductMoney <= 0) $deductMoney = $orderMoney;

            // 3.2 🔥 资金双向结算（纯底层 SQL 穿透，绕过封装报错）
            $DB->exec("UPDATE pay_user SET money = money - :m WHERE uid = :u", [':m'=>$deductMoney, ':u'=>$qrcodeUid]);
            $DB->exec("UPDATE pay_user SET money = money + :m WHERE uid = :u", [':m'=>$deductMoney, ':u'=>$order['uid']]);
            
            // 记录资金明细 (使用 date('Y-m-d H:i:s') 防止 MySQL 严格模式报错)
            $now = date('Y-m-d H:i:s');
            $DB->insert('record', ['uid'=>$qrcodeUid, 'action'=>2, 'money'=>$deductMoney, 'type'=>'码商代收转出', 'trade_no'=>$trade_no, 'date'=>$now]);
            $DB->insert('record', ['uid'=>$order['uid'], 'action'=>1, 'money'=>$deductMoney, 'type'=>'码商代收转入', 'trade_no'=>$trade_no, 'date'=>$now]);

            // 记录到订单中
            $DB->exec("UPDATE pay_order SET profitmoney=:p WHERE trade_no=:t", [':p'=>$deductMoney, ':t'=>$trade_no]);

            // 3.3 🔥 码商上级的级差三级分润
            $currentUid = $qrcodeUid; 
            $currentRate = $floatRate; 
            
            for ($level = 1; $level <= 3; $level++) {
                $parent = $DB->getRow("SELECT uid, upid FROM pay_user WHERE uid = :u", [':u' => $currentUid]);
                if (!$parent || $parent['upid'] <= 0) break;

                $parentUid = (int)$parent['upid'];
                $parentRate = (float)$DB->getColumn("SELECT rate FROM pay_user WHERE uid=:u", [':u'=>$parentUid]);
                
                // 计算级差利润：当前费率 - 上级费率 = 差价
                $profitRate = $currentRate - $parentRate;
                
                if ($profitRate > 0) {
                    $commMoney = round($orderMoney * $profitRate / 100, 2);
                    if ($commMoney > 0) {
                        $DB->exec("UPDATE pay_user SET money = money + :m WHERE uid = :u", [':m'=>$commMoney, ':u'=>$parentUid]);
                        $DB->insert('record', ['uid'=>$parentUid, 'action'=>1, 'money'=>$commMoney, 'type'=>"下级[UID{$currentUid}]级差分润", 'trade_no'=>$trade_no, 'date'=>date('Y-m-d H:i:s')]);
                    }
                }
                
                $currentUid = $parentUid;
                $currentRate = $parentRate;
            }
        }

        // 4. 更新对应收款码的日统计（优先读 ext）
        // 3. 更新对应收款码的日统计（优先读 ext）
        // 3. 更新对应收款码的日统计（优先读 ext）
        $extData = json_decode($order['ext'], true) ?: [];
        $qrcodeId = intval($extData['qrcode_id'] ?? 0);
        // 老订单兼容：param 里
        if(!$qrcodeId){
            $paramData = json_decode($order['param'], true) ?: [];
            $qrcodeId = intval($paramData['qrcode_id'] ?? 0);
        }
        if($qrcodeId > 0){
            $DB->exec(
                "UPDATE pay_qrcode SET
                   today_count  = today_count + 1,
                   today_amount = today_amount + :m,
                   today_date   = CURDATE()
                 WHERE id=:id",
                [':m'=>$order['money'], ':id'=>$qrcodeId]
            );
        }

        // 4. 回调商户
        if(!empty($order['notify_url'])){
            $url = creat_callback($order);
            if(do_notify($url['notify'])){
                $DB->exec("UPDATE pay_order SET notify=0, notifytime=NOW() WHERE trade_no=:t", [':t'=>$trade_no]);
            }else{
                $DB->exec("UPDATE pay_order SET notify=1, notifytime=DATE_ADD(NOW(), INTERVAL 1 MINUTE) WHERE trade_no=:t", [':t'=>$trade_no]);
            }
        }

        // 5. 触发推送（邮件 + TG）
        $hookScript = __DIR__ . '/../../telegram/hook_order_paid.php';
        if (is_file($hookScript)) {
            $phpBin = '';
        foreach (['D:\\phpstudy_pro\\Extensions\\php\\php8.2.9nts\\php.exe','C:\\php\\php.exe','/www/server/php/82/bin/php','/usr/bin/php'] as $cand) { if (is_file($cand)) { $phpBin = $cand; break; } }
        if (!$phpBin) { $phpBin = 'php'; }
            // 已废弃 exec 方式，改用 curl
            $__notifyUrl = creat_callback($srow)['notify'] ?? '';
            if ($__notifyUrl) {
                $__ch = curl_init($__notifyUrl);
                curl_setopt($__ch, CURLOPT_TIMEOUT, 1);
                curl_setopt($__ch, CURLOPT_NOSIGNAL, 1);
                curl_setopt($__ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($__ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_exec($__ch);
                curl_close($__ch);
            }
        }

        return ['code'=>0, 'msg'=>'订单已完成'];
    }

    public static function refund_info($trade_no, $api = 0, $uid = 0){
        global $DB;
        $where = ['trade_no'=>$trade_no];
        if($uid > 0) $where['uid'] = $uid;
        $order = $DB->find('order', '*', $where);
        if(!$order)
            return ['code'=>-1, 'msg'=>'当前订单不存在！'];
        if(!in_array($order['status'], [1,2,3]))
            return ['code'=>-1, 'msg'=>'该订单状态不支持退款！'];
        if($order['status'] == 2 && empty($order['refundmoney'])) return ['code'=>-1, 'msg'=>'该订单已退款！'];
        if($order['status'] == 2 && $order['refundmoney'] > 0 && $order['refundmoney'] >= $order['realmoney']) return ['code'=>-1, 'msg'=>'该订单已全额退款！'];
        $money = !empty($order['refundmoney']) ? round($order['realmoney'] - $order['refundmoney'], 2) : $order['realmoney'];

        if($api==1){
            if(!$order['api_trade_no']) return ['code'=>-1, 'msg'=>'接口订单号不存在'];
            $channel = \lib\Channel::get($order['channel']);
            if(!$channel) return ['code'=>-1, 'msg'=>'当前支付通道信息不存在'];
            if(\lib\Plugin::isrefund($channel['plugin'])==false){
                return ['code'=>-1, 'msg'=>'当前支付通道不支持API退款'];
            }
        }

        return ['code'=>0, 'money'=>$money];
    }

    public static function refund($refund_no, $trade_no, $money, $api = 0, $uid = 0, $out_refund_no = null){
        global $DB, $order, $conf;

        $where = ['trade_no'=>$trade_no];
        if($uid > 0) $where['uid'] = $uid;
        $order = $DB->find('order', '*', $where);
        if(!$order)
            return ['code'=>-1, 'msg'=>'当前订单不存在！'];
        if(!in_array($order['status'], [1,2,3]))
            return ['code'=>-1, 'msg'=>'该订单状态不支持退款！'];
        if(empty($money)) $money = $order['realmoney'];
        if($money>$order['realmoney']) return ['code'=>-1, 'msg'=>'退款金额不能大于订单金额'];
        if(!$order['api_trade_no']) return ['code'=>-1, 'msg'=>'接口订单号不存在'];
        if($order['status'] == 2 && empty($order['refundmoney'])) return ['code'=>-1, 'msg'=>'该订单已退款！'];
        if($order['status'] == 2 && $order['refundmoney'] > 0 && $order['refundmoney'] >= $order['realmoney']) return ['code'=>-1, 'msg'=>'该订单已全额退款！'];
        if($order['status'] == 2 && $order['refundmoney'] > 0 && $money > round($order['realmoney'] - $order['refundmoney'], 2) ||$money > $order['realmoney']) return ['code'=>-1, 'msg'=>'退款金额不能超过该订单剩余可退款金额！'];
        $refunded = $order['refundmoney'];

        if(!$out_refund_no) $out_refund_no = $refund_no;

        $mode = $DB->findColumn('channel', 'mode', ['id'=>$order['channel']]);
        if($order['status'] == 3 || $mode == 1){
            $reducemoney = 0;
        }elseif($conf['refund_fee_type']==1 && $money == $order['realmoney']){
            $reducemoney = $order['realmoney'];
        }elseif(!$conf['refund_fee_type'] && ($money == $order['realmoney'] || $money >= $order['getmoney'])){
            $reducemoney = $order['getmoney'];
        }else{
            $reducemoney = $money;
        }

        if($uid > 0 && $reducemoney > 0){
            $usermoney = $DB->findColumn('user', 'money', ['uid'=>$uid]);
            if($reducemoney > $usermoney){
                return ['code'=>-1, 'msg'=>'商户余额不足，请先充值'];
            }
        }

        if($api == 1){
            $message = null;
            if(!\lib\Plugin::refund($refund_no, $trade_no, $money, $message)){
                return ['code'=>-1, 'msg'=>'退款失败：'.$message];
            }
        }
        if($reducemoney > 0){
            if($order['tid'] == 2){
                $param = json_decode($order['param'], true);
                if(isset($param['uid'])){
                    $order['uid'] = $param['uid'];
                }
            }
            
        }
        $refundmoney = !empty($refunded) ? round($refunded + $money, 2) : $money;
        if($mode == 1 && !$conf['refund_fee_type'] && $refundmoney >= $order['realmoney']){
            $record = $DB->getRow("SELECT * FROM pay_record WHERE trade_no='{$trade_no}' AND (type='订单服务费' OR type='在线收款服务费') LIMIT 1");
            if($record){
                $addmoney = $record['money'];
                
            }
        }

        if($api == 1){
            $DB->update('order', ['status'=>2, 'refundmoney'=>$refundmoney], ['trade_no'=>$trade_no]);
            $DB->insert('refundorder', ['refund_no'=>$refund_no, 'out_refund_no'=>$out_refund_no, 'trade_no'=>$trade_no, 'uid'=>$order['uid'], 'money'=>$money, 'reducemoney'=>$reducemoney, 'addtime'=>date('Y-m-d H:i:s'), 'endtime'=>date('Y-m-d H:i:s'), 'status'=>1]);
        }else{
            $DB->update('order', ['status'=>2], ['trade_no'=>$trade_no]);
        }
        
        return ['code'=>0, 'refund_no'=>$refund_no, 'out_refund_no'=>$out_refund_no, 'trade_no'=>$trade_no, 'out_trade_no'=>$order['out_trade_no'], 'uid'=>$order['uid'], 'money'=>$money, 'reducemoney'=>$reducemoney];
    }

    public static function close($trade_no, $uid = 0){
        global $DB, $order, $conf;

        $where = ['trade_no'=>$trade_no];
        if($uid > 0) $where['uid'] = $uid;
        $order = $DB->find('order', '*', $where);
        if(!$order)
            return ['code'=>-1, 'msg'=>'当前订单不存在！'];
        if($order['status'] != 0)
            return ['code'=>-1, 'msg'=>'该订单状态不支持关闭！'];

        if(!\lib\Plugin::close($trade_no, $message)){
            return ['code'=>-1, 'msg'=>$message];
        }
        return ['code'=>0];
    }
}
