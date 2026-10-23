<?php
require_once __DIR__ . '/Auth.php';

class Qrcode {
    // 与后台 user/qrcode/index.php 完全一致
    public static $PAY_TYPES = [
        'wechat_reward'    => ['label'=>'微信赞赏码',   'max'=>20],
        'wechat_personal'  => ['label'=>'微信个人码',   'max'=>20],
        'wechat_biz'       => ['label'=>'微信经营码',   'max'=>20],
        'alipay_card'      => ['label'=>'支付宝名片',   'max'=>20],
        'alipay_personal'  => ['label'=>'支付宝个人码', 'max'=>20],
        'alipay_biz'       => ['label'=>'支付宝经营码', 'max'=>20],
        'aggregate_wechat' => ['label'=>'聚合码·微信',   'max'=>5],
        'aggregate_alipay' => ['label'=>'聚合码·支付宝', 'max'=>5],
        'aggregate_both'   => ['label'=>'聚合码·通用',   'max'=>5],
    ];

    public static function label($ch) {
        return self::$PAY_TYPES[$ch]['label'] ?? $ch;
    }

    public static function listByUid($uid) {
        return DB::query("SELECT id, channel, category, remark, qrcode_img, use_count, status, addtime FROM pay_qrcode WHERE uid=? AND status=1 ORDER BY id DESC LIMIT 30", [$uid]);
    }

    public static function countByChannel($uid, $ch) {
        return (int)DB::col("SELECT COUNT(*) FROM pay_qrcode WHERE uid=? AND channel=? AND status=1", [$uid, $ch]);
    }

    public static function get($uid, $id) {
        return DB::one("SELECT * FROM pay_qrcode WHERE id=? AND uid=? LIMIT 1", [$id, $uid]);
    }

    public static function add($uid, $channel, $imgRelativePath, $remark='') {
        $pt = self::$PAY_TYPES[$channel] ?? null;
        if (!$pt) return [false, '类型错误'];
        $cnt = self::countByChannel($uid, $channel);
        if ($cnt >= $pt['max']) return [false, $pt['label']."已达上限（{$pt['max']}个）"];
        if ($remark === '') $remark = $pt['label'];
        DB::exec("INSERT INTO pay_qrcode (uid, channel, category, remark, qrcode_img, status, addtime) VALUES (?,?, 'qrcode', ?, ?, 1, NOW())",
                 [$uid, $channel, $remark, $imgRelativePath]);
        return [true, DB::conn()->lastInsertId()];
    }

    public static function delete($uid, $id) {
        $row = self::get($uid, $id);
        if (!$row) return [false, '二维码不存在'];
        DB::exec("UPDATE pay_qrcode SET status=0 WHERE id=? AND uid=?", [$id, $uid]);
        return [true, $row];
    }

    // Telegram file_id 下载并保存
    public static function saveFromTelegram($uid, $channel, $fileId) {
        $token = BOT_TOKEN;
        // 1. getFile
        $url = "https://api.telegram.org/bot{$token}/getFile?file_id=" . urlencode($fileId);
        $resp = @file_get_contents($url);
        $j = json_decode($resp, true);
        if (empty($j['result']['file_path'])) return [false, '获取图片失败'];
        $filePath = $j['result']['file_path'];

        // 2. 下载
        $downloadUrl = "https://api.telegram.org/file/bot{$token}/{$filePath}";
        $bin = @file_get_contents($downloadUrl);
        if (!$bin) return [false, '下载图片失败'];

        // 3. 保存
        $dir = "/www/wwwroot/pay.yunzexinda.com/assets/qrcode/{$uid}/";
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $fn = 'qr_' . $uid . '_' . date('YmdHis') . '_' . mt_rand(1000,9999) . '.png';
        if (!file_put_contents($dir . $fn, $bin)) return [false, '写入文件失败'];
        $relative = "assets/qrcode/{$uid}/{$fn}";
        return [true, $relative];
    }

    // 状态机
    public static function setState($tg_id, $action, $data='') {
        DB::exec("REPLACE INTO pay_tg_state (tg_id, action, data, updated_at) VALUES (?,?,?,?)",
                 [(int)$tg_id, $action, $data, time()]);
    }
    public static function getState($tg_id) {
        return DB::one("SELECT * FROM pay_tg_state WHERE tg_id=?", [(int)$tg_id]);
    }
    public static function clearState($tg_id) {
        DB::exec("DELETE FROM pay_tg_state WHERE tg_id=?", [(int)$tg_id]);
    }
}

// ========== 参数更新方法 ==========
if (!method_exists('Qrcode', 'updateField')) {
    // PHP 类定义后不能加方法，这里改用独立函数
}

function qrUpdateField($uid, $id, $field, $value) {
    $allow = ['float_rate', 'min_amount', 'max_amount', 'day_limit', 'day_max_amount', 'remark'];
    if (!in_array($field, $allow)) return [false, '参数不允许'];
    $row = Qrcode::get($uid, $id);
    if (!$row) return [false, '二维码不存在'];
    DB::exec("UPDATE pay_qrcode SET `$field`=? WHERE id=? AND uid=?", [$value, $id, $uid]);
    return [true, $value];
}

function qrGetDetail($uid, $id) {
    return DB::one("SELECT id, channel, remark, float_rate, min_amount, max_amount, day_limit, day_max_amount, use_count FROM pay_qrcode WHERE id=? AND uid=?", [$id, $uid]);
}
