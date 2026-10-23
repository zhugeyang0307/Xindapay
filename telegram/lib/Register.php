<?php
require_once __DIR__ . '/../config.php';

class Register {

    public static function setStep($tg_user_id, $step, $data = []) {
        $email = $data['email'] ?? '';
        $pwd   = $data['pwd'] ?? '';
        $code  = $data['code'] ?? '';
        DB::exec("REPLACE INTO pay_tg_reg_state (tg_user_id, step, email, pwd, code, code_time, created_at)
                  VALUES (?,?,?,?,?,?,?)",
                 [$tg_user_id, $step, $email, $pwd, $code, time(), time()]);
    }

    public static function getState($tg_user_id) {
        return DB::one("SELECT * FROM pay_tg_reg_state WHERE tg_user_id=?", [$tg_user_id]);
    }

    public static function clearState($tg_user_id) {
        DB::exec("DELETE FROM pay_tg_reg_state WHERE tg_user_id=?", [$tg_user_id]);
    }

    public static function genCode($tg_user_id, $account, $pwd) {
        $code = str_pad((string)mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        self::setStep($tg_user_id, 'code', ['email'=>$account, 'pwd'=>$pwd, 'code'=>$code]);
        return $code;
    }

    public static function verifyCode($tg_user_id, $input) {
        $st = self::getState($tg_user_id);
        if (!$st || $st['step'] !== 'code') return '状态错误，请重新注册';
        if (time() - $st['code_time'] > 600) return '验证码已过期，请重新注册';
        if ($st['code'] !== $input) return '验证码错误';
        return true;
    }

    public static function createUser($tg_user_id) {
        $st = self::getState($tg_user_id);
        if (!$st) return [false, '状态错误'];

        $account = $st['email'];
        $pwd     = $st['pwd'];

        $isPhone = preg_match('/^1[3-9]\d{9}$/', $account);
        $email = $isPhone ? '' : $account;
        $phone = $isPhone ? $account : '';

        if ($isPhone) {
            $exist = DB::one("SELECT uid FROM pay_user WHERE phone=?", [$account]);
        } else {
            $exist = DB::one("SELECT uid FROM pay_user WHERE email=?", [$account]);
        }
        if ($exist) return [false, '该账号已被注册'];

        $key = self::randomKey(32);
        $db = DB::conn();

        $stmt = $db->prepare("INSERT INTO `pay_user` (`upid`, `key`, `money`, `email`, `phone`, `addtime`, `pay`, `settle`, `keylogin`, `apply`, `status`)
                              VALUES (0, ?, '0.00', ?, ?, NOW(), 1, 1, 0, 0, 0)");
        $stmt->execute([$key, $email, $phone]);
        $uid = $db->lastInsertId();
        if ($uid) { \lib\Service::initTrial($uid); }

        $encPwd = md5(md5($pwd) . md5('1277180438' . $uid));
        $db->prepare("UPDATE pay_user SET pwd=? WHERE uid=?")->execute([$encPwd, $uid]);

        return [true, ['uid'=>$uid, 'key'=>$key, 'account'=>$account, 'email'=>$email, 'phone'=>$phone, 'pwd_plain'=>$pwd]];
    }

    public static function setPendingPay($tg_user_id, $uid, $userkey, $pay_order_id, $account, $pwd_plain) {
        DB::exec("REPLACE INTO pay_tg_reg_state
                  (tg_user_id, step, email, pwd, code, code_time, created_at, uid, userkey, pay_order_id)
                  VALUES (?,?,?,?,?,?,?,?,?,?)",
                 [$tg_user_id, 'pay', $account, $pwd_plain, '', time(), time(), $uid, $userkey, $pay_order_id]);
    }

    public static function getByPayOrderId($pay_order_id) {
        return DB::one("SELECT * FROM pay_tg_reg_state WHERE pay_order_id=? LIMIT 1", [$pay_order_id]);
    }

    public static function completePay($pay_order_id) {
        $st = self::getByPayOrderId($pay_order_id);
        if (!$st) return [false, '订单不存在'];
        if ($st['step'] === 'done') return [false, '已处理'];

        $amount_cny = REG_RECHARGE_USDT * REG_RECHARGE_RATE;
        DB::exec("UPDATE pay_user SET money = money + ?, status = 1 WHERE uid = ?",
                 [$amount_cny, $st['uid']]);
        DB::exec("UPDATE pay_tg_reg_state SET step='done' WHERE pay_order_id=?", [$pay_order_id]);

        DB::exec("INSERT INTO pay_tgbind(tg_id, uid, addtime) VALUES(?,?,NOW())
                  ON DUPLICATE KEY UPDATE uid=VALUES(uid)", [$st['tg_user_id'], $st['uid']]);

        return [true, [
            'uid' => $st['uid'],
            'key' => $st['userkey'],
            'account' => $st['email'],
            'pwd' => $st['pwd'],
            'amount_cny' => $amount_cny,
            'chat_id' => $st['tg_user_id'],
        ]];
    }

    private static function randomKey($len) {
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $s = '';
        for ($i = 0; $i < $len; $i++) $s .= $chars[mt_rand(0, strlen($chars)-1)];
        return $s;
    }
}
