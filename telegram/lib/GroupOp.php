<?php
require_once __DIR__ . '/Auth.php';

class GroupOp {

    // ========== 群绑定 ==========

    /**
     * 绑定商户群（商户在群里发 /绑定商户群）
     */
    public static function bindMerchantGroup($chat_id, $user_id, $title) {
        // 1. 查绑定者是不是商户
        $uid = Auth::merchantUid($user_id);
        if (!$uid) return [false, '你不是商户，无法绑定商户群（请先 /快捷登录）'];

        // 2. 检查群是否已绑定到别的商户
        $exist = DB::one("SELECT uid, title FROM pay_tggroup WHERE chat_id=?", [$chat_id]);
        if ($exist && $exist['uid'] != 0 && $exist['uid'] != $uid) {
            return [false, "该群已绑定到商户 UID={$exist['uid']}，无法重复绑定"];
        }

        // 3. 绑定
        DB::exec("INSERT INTO pay_tggroup (chat_id, uid, title, addtime, is_merchant_group, set_by, set_time, push_orders)
                  VALUES (?,?,?,NOW(), 1, ?, NOW(), 1)
                  ON DUPLICATE KEY UPDATE
                    uid=VALUES(uid), title=VALUES(title),
                    is_merchant_group=1, set_by=VALUES(set_by), set_time=NOW()",
                 [$chat_id, $uid, $title, $user_id]);

        return [true, $uid];
    }

    /**
     * 解绑
     */
    public static function unbind($chat_id, $user_id) {
        $g = DB::one("SELECT uid FROM pay_tggroup WHERE chat_id=?", [$chat_id]);
        if (!$g) return [false, '本群未绑定'];
        if ((int)$g['uid'] !== Auth::merchantUid($user_id) && !Auth::isAdmin($user_id)) {
            return [false, '无权操作'];
        }
        DB::exec("DELETE FROM pay_tggroup WHERE chat_id=?", [$chat_id]);
        DB::exec("DELETE FROM pay_tggroup_operator WHERE group_id=?", [$chat_id]);
        return [true, 'ok'];
    }

    /**
     * 查群绑定
     */
    public static function getGroup($chat_id) {
        return DB::one("SELECT * FROM pay_tggroup WHERE chat_id=?", [$chat_id]);
    }

    // ========== 操作人管理 ==========

    /**
     * 添加操作人
     */
    public static function addOperator($chat_id, $by_user_id, $target_tg_id, $username, $name) {
        $g = self::getGroup($chat_id);
        if (!$g || !$g['is_merchant_group']) return [false, '本群不是商户群'];
        // 校验操作权限：商户本人可以加
        $uid = Auth::merchantUid($by_user_id);
        if ($uid != $g['uid'] && !Auth::isAdmin($by_user_id)) {
            return [false, '只有商户本人或管理员可以添加操作人'];
        }
        // 不允许把商户自己加为操作人（商户天然有权限）
        if ($target_tg_id == $by_user_id) return [false, '你已经是商户，无需添加为操作人'];

        DB::exec("INSERT INTO pay_tggroup_operator (group_id, tg_id, tg_username, tg_name, added_by, status)
                  VALUES (?,?,?,?,?,1)
                  ON DUPLICATE KEY UPDATE tg_username=VALUES(tg_username), tg_name=VALUES(tg_name), status=1",
                 [$chat_id, $target_tg_id, $username, $name, $by_user_id]);
        return [true, 'ok'];
    }

    /**
     * 删除操作人
     */
    public static function delOperator($chat_id, $by_user_id, $target_tg_id) {
        $g = self::getGroup($chat_id);
        if (!$g || !$g['is_merchant_group']) return [false, '本群不是商户群'];
        $uid = Auth::merchantUid($by_user_id);
        if ($uid != $g['uid'] && !Auth::isAdmin($by_user_id)) {
            return [false, '无权操作'];
        }
        DB::exec("DELETE FROM pay_tggroup_operator WHERE group_id=? AND tg_id=?", [$chat_id, $target_tg_id]);
        return [true, 'ok'];
    }

    /**
     * 列出操作人
     */
    public static function listOperators($chat_id) {
        return DB::query("SELECT * FROM pay_tggroup_operator WHERE group_id=? AND status=1 ORDER BY id", [$chat_id]);
    }

    /**
     * 判断某人是否有群操作权限（含商户本人）
     */
    public static function canOperate($chat_id, $tg_id) {
        $g = self::getGroup($chat_id);
        if (!$g || !$g['is_merchant_group']) return false;

        // 管理员天然有权限
        if (Auth::isAdmin($tg_id)) return true;

        // 商户本人有权限
        $uid = Auth::merchantUid($tg_id);
        if ($uid && $uid == $g['uid']) return true;

        // 操作人
        $op = DB::one("SELECT id FROM pay_tggroup_operator WHERE group_id=? AND tg_id=? AND status=1", [$chat_id, $tg_id]);
        return $op ? true : false;
    }

    /**
     * 获取群主商户 uid
     */
    public static function groupMerchantUid($chat_id) {
        $g = self::getGroup($chat_id);
        return $g ? (int)$g['uid'] : 0;
    }

    // ========== 状态机（群内上传二维码）==========

    public static function setState($tg_id, $action, $data = '') {
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
