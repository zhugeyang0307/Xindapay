<?php
namespace lib\sms;

class SmsBao {
    private $user;
    private $pass;

    function __construct($user, $pass){
        $this->user = trim($user);
        $this->pass = trim($pass);
    }

    public function send($phone, $param, $moban, $sign){
        if(empty($this->user)||empty($this->pass))return false;

        $statusStr = array(
            "0"  => "短信发送成功",
            "-1" => "参数不全",
            "-2" => "服务器空间不支持",
            "30" => "密码错误",
            "40" => "账号不存在",
            "41" => "余额不足",
            "42" => "帐户已过期",
            "43" => "IP地址限制",
            "50" => "内容含有敏感词"
        );

        // 替换模板中的 {code} 等占位符
        foreach($param as $k=>$v){
            $moban = str_replace('{'.$k.'}',$v,$moban);
        }

        // 拼接最终短信内容：【签名】模板正文
        $content = '【'.trim($sign).'】'.$moban;

        // 拼请求 URL（全部 trim 掉两端空格，防止 URL 里出现空格）
        $sendurl = "https://api.smsbao.com/sms"
                 . "?u=".trim($this->user)
                 . "&p=".trim($this->pass)
                 . "&m=".trim($phone)
                 . "&c=".urlencode($content);

        // 发起请求
        $result = get_curl($sendurl);

        // 调试日志
        file_put_contents(
            '/tmp/smsbao_debug.log',
            date('Y-m-d H:i:s')
            ."\nCONTENT = ".$content
            ."\nURL     = ".$sendurl
            ."\nRESULT  = ".var_export($result, true)
            ."\n------------------------------------------------\n",
            FILE_APPEND
        );

        if ($result == '0'){
            return true;
        }else{
            return isset($statusStr[$result]) ? $statusStr[$result] : ('CODE:'.$result);
        }
    }
}