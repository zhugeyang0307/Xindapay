<?php
require_once dirname(__DIR__) . '/config.php';

class RouteRule {
    public static function getEnabledRules() {
        return DB::query("SELECT * FROM pay_route_rule WHERE status=1 ORDER BY priority ASC, id ASC") ?: [];
    }

    public static function getAll() {
        return DB::query("SELECT * FROM pay_route_rule ORDER BY priority ASC, id ASC") ?: [];
    }

    public static function get($id) {
        return DB::one("SELECT * FROM pay_route_rule WHERE id=?", [$id]);
    }

    public static function add($data) {
        DB::exec("INSERT INTO pay_route_rule (name, priority, match_type, match_value, channel_id, status, remark)
                  VALUES (?,?,?,?,?,?,?)",
            [
                $data['name'] ?? '',
                (int)($data['priority'] ?? 100),
                $data['match_type'] ?? '',
                is_string($data['match_value'] ?? '') ? $data['match_value'] : json_encode($data['match_value'] ?? [], JSON_UNESCAPED_UNICODE),
                (int)($data['channel_id'] ?? 0),
                (int)($data['status'] ?? 1),
                $data['remark'] ?? '',
            ]);
        return DB::conn()->lastInsertId();
    }

    public static function update($id, $data) {
        $fields = [];
        $args = [];
        foreach (['name','priority','match_type','match_value','channel_id','status','remark'] as $k) {
            if (array_key_exists($k, $data)) {
                $fields[] = "$k=?";
                $args[] = is_array($data[$k]) ? json_encode($data[$k], JSON_UNESCAPED_UNICODE) : $data[$k];
            }
        }
        if (empty($fields)) return 0;
        $args[] = $id;
        return DB::exec("UPDATE pay_route_rule SET " . implode(',', $fields) . " WHERE id=?", $args);
    }

    public static function delete($id) {
        return DB::exec("DELETE FROM pay_route_rule WHERE id=?", [$id]);
    }

    public static function log($tradeNo, $uid, $ip, $location, $device, $matchResult) {
        $channelName = '';
        if ($matchResult['channel_id'] > 0) {
            $ch = DB::one("SELECT name FROM pay_channel WHERE id=?", [$matchResult['channel_id']]);
            $channelName = $ch['name'] ?? '';
        }
        DB::exec("INSERT INTO pay_route_log
                  (trade_no, uid, client_ip, province, city, isp, device,
                   matched_rule_id, matched_rule_name, channel_id, channel_name)
                  VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            [
                $tradeNo, $uid, $ip,
                $location['province'] ?? '', $location['city'] ?? '', $location['isp'] ?? '',
                $device,
                $matchResult['rule_id'], $matchResult['rule_name'],
                $matchResult['channel_id'], $channelName,
            ]);
    }
}