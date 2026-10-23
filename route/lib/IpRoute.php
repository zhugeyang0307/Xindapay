<?php
require_once __DIR__ . '/IpLocation.php';
require_once __DIR__ . '/RouteRule.php';

class IpRoute {
    private $ip;
    private $uid;
    private $device;
    private $location;

    public function __construct($ip, $device = 'pc', $uid = 0) {
        $this->ip = $ip;
        $this->uid = (int)$uid;
        $this->device = $device;
    }

    public function match() {
        $this->location = IpLocation::lookup($this->ip);
        $rules = RouteRule::getEnabledRules();

        foreach ($rules as $rule) {
            $matchValue = json_decode($rule['match_value'], true) ?: [];

            switch ($rule['match_type']) {
                case 'uid':
                    if (in_array($this->uid, $matchValue['uids'] ?? [])) {
                        return $this->makeResult($rule, 'uid match');
                    }
                    break;

                case 'province':
                    if (in_array($this->location['province'], $matchValue['provinces'] ?? [])) {
                        return $this->makeResult($rule, 'province match');
                    }
                    break;

                case 'isp':
                    foreach ($matchValue['isps'] ?? [] as $ispPattern) {
                        if (stripos($this->location['isp'], $ispPattern) !== false) {
                            return $this->makeResult($rule, 'isp match: ' . $ispPattern);
                        }
                    }
                    break;

                case 'device':
                    if (in_array($this->device, $matchValue['devices'] ?? [])) {
                        return $this->makeResult($rule, 'device match');
                    }
                    break;

                case 'ip_range':
                    $ranges = $matchValue['ranges'] ?? [];
                    if ($this->_inIpRanges($this->ip, $ranges)) {
                        return $this->makeResult($rule, 'ip_range match');
                    }
                    break;

                case 'weight':
                    $channels = $matchValue['channels'] ?? [];
                    if (!empty($channels)) {
                        $channelId = $this->_weightedRandom($channels);
                        return [
                            'channel_id' => $channelId,
                            'rule_id'    => $rule['id'],
                            'rule_name'  => $rule['name'],
                            'reason'     => 'weight random',
                        ];
                    }
                    break;
            }
        }

        return [
            'channel_id' => 0,
            'rule_id'    => 0,
            'rule_name'  => '',
            'reason'     => 'no rule matched',
        ];
    }

    public function getLocation() {
        return $this->location;
    }

    private function makeResult($rule, $reason) {
        return [
            'channel_id' => (int)$rule['channel_id'],
            'rule_id'    => (int)$rule['id'],
            'rule_name'  => $rule['name'],
            'reason'     => $reason,
        ];
    }

    private function _inIpRanges($ip, $ranges) {
        $ipLong = ip2long($ip);
        foreach ($ranges as $r) {
            $start = ip2long($r['start'] ?? '');
            $end   = ip2long($r['end'] ?? '');
            if ($start === false || $end === false) continue;
            if ($ipLong >= $start && $ipLong <= $end) {
                return true;
            }
        }
        return false;
    }

    private function _weightedRandom($channels) {
        $total = 0;
        foreach ($channels as $c) {
            $total += (int)($c['weight'] ?? 0);
        }
        if ($total <= 0) return 0;

        $roll = mt_rand(1, $total);
        $acc = 0;
        foreach ($channels as $c) {
            $acc += (int)($c['weight'] ?? 0);
            if ($roll <= $acc) return (int)$c['channel_id'];
        }
        return (int)$channels[0]['channel_id'];
    }
}