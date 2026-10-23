<?php
namespace lib;

use Exception;

class VerifyCode{

    const SMS_PHONE_DAYLY_TIME = 3; //短信验证码每个手机号每天最多发送次数
    const SMS_IP_DAYLY_TIME = 6; //短信验证码每个IP每天最多发送次数
    const EMAIL_ADDRESS_DAYLY_TIME = 7; //邮箱验证码每个邮箱每天最多发送次数
    const EMAIL_IP_DAYLY_TIME = 11; //邮箱验证码每个IP每天最多发送次数

    private static $regcodeid;

    /**
     * 发送验证码
     * @param string $scene 验证码场景
     * @param int $type 验证码类型:0邮件,1手机
     * @param string $sendto 接收人
     * @param int $uid 用户ID
     * @return mixed
     */
    public static function send_code($scene, $type, $sendto, $uid = 0){
        global $DB, $conf, $clientip;

        if($type == 1){
            $phone = $sendto;
            $row=$DB->getRow("select * from pay_regcode where `to`=:phone order by id desc limit 1", [':phone'=>$phone]);
            if($row['time']>time()-60){
                return '两次发送短信之间需要相隔60秒！';
            }
            $count=$DB->getColumn("select count(*) from pay_regcode where `to`=:phone and time>'".(time()-3600*24)."'", [':phone'=>$phone]);
            if($count>=self::SMS_PHONE_DAYLY_TIME){
                return '该手机号码发送次数过多，请更换号码！';
            }
            $count=$DB->getColumn("select count(*) from pay_regcode where ip=:ip and time>'".(time()-3600*24)."'", [':ip'=>$clientip]);
            if($count>=self::SMS_IP_DAYLY_TIME){
                return '你今天发送次数过多，请明天再试！';
            }
            $code = rand(111111,999999);
            $result = send_sms($phone, $code, $scene);
            if($result===true){
                if($DB->insert('regcode', ['uid'=>$uid, 'scene'=>$scene, 'type'=>$type, 'code'=>$code, 'to'=>$phone, 'time'=>time(), 'ip'=>$clientip, 'status'=>0])){
                    return true;
                }else{
                    return '写入数据库失败。'.$DB->error();
                }
            }else{
                return '短信发送失败 '.$result;
            }
        }else{
            $email = $sendto;
            $row=$DB->getRow("select * from pay_regcode where `to`=:email order by id desc limit 1", [':email'=>$email]);
            if($row['time']>time()-60){
                return '两次发送邮件之间需要相隔60秒！';
            }
            $count=$DB->getColumn("select count(*) from pay_regcode where `to`=:email and time>'".(time()-3600*24)."'", [':email'=>$email]);
            if($count>=self::EMAIL_ADDRESS_DAYLY_TIME){
                return '该邮箱发送次数过多，请更换邮箱！';
            }
            $count=$DB->getColumn("select count(*) from pay_regcode where ip=:ip and time>'".(time()-3600*24)."'", [':ip'=>$clientip]);
            if($count>=self::EMAIL_IP_DAYLY_TIME){
                return '你今天发送次数过多，请明天再试！';
            }
            $code = rand(1111111,9999999);
            $result = self::send_mail_code($email, $code, $scene);
            if($result===true){
                if($DB->insert('regcode', ['uid'=>$uid, 'scene'=>$scene, 'type'=>$type, 'code'=>$code, 'to'=>$email, 'time'=>time(), 'ip'=>$clientip, 'status'=>0])){
                    return true;
                }else{
                    return '写入数据库失败。'.$DB->error();
                }
            }else{
                return '邮件发送失败 '.$result;
            }
        }
    }

    private static function send_mail_code($email, $code, $scene){
        global $conf;
        $sitename = htmlspecialchars($conf['sitename'], ENT_QUOTES, 'UTF-8');
        $siteurl  = isset($conf['siteurl']) && $conf['siteurl'] ? $conf['siteurl'] : ('https://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'pay.yunzexinda.com'));

        $sceneMap = [
            'reg'   => ['title' => '注册验证', 'desc' => '您正在进行新商户注册', 'color' => '#1677ff', 'tip' => '请勿将验证码转发或告知他人，包括平台工作人员。'],
            'login' => ['title' => '登录验证', 'desc' => '您正在进行商户后台登录', 'color' => '#07c160', 'tip' => '如非本人操作，请立即修改密码并联系客服。'],
            'find'  => ['title' => '找回密码', 'desc' => '您正在进行密码重置操作', 'color' => '#f59e0b', 'tip' => '重置密码有效期为 10 分钟，请勿泄露给他人。'],
            'edit'  => ['title' => '安全验证', 'desc' => '您正在进行敏感信息变更', 'color' => '#ef4444', 'tip' => '此操作涉及资金安全，如非本人操作请立即联系客服。'],
        ];
        $info  = isset($sceneMap[$scene]) ? $sceneMap[$scene] : ['title' => '验证码', 'desc' => '验证码获取', 'color' => '#1677ff', 'tip' => '如非本人操作请忽略本邮件。'];
        $title = $sitename . ' - ' . $info['title'] . '验证码';

        $body = '<!DOCTYPE html>'
            . '<html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            . '<body style="margin:0;padding:0;background:#f4f6fb;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,PingFang SC,Microsoft YaHei,sans-serif;color:#1a2a4a;line-height:1.6;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6fb;padding:30px 12px;"><tr><td align="center">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 4px 20px rgba(22,119,255,.08);">'
            . '<tr><td style="background:linear-gradient(135deg,' . $info['color'] . ' 0%,#0f2b5c 100%);padding:28px 32px;text-align:left;">'
            . '<div style="font-size:20px;font-weight:700;color:#ffffff;letter-spacing:.5px;">' . $sitename . '</div>'
            . '<div style="font-size:13px;color:rgba(255,255,255,.75);margin-top:4px;">安全 · 稳定 · 专业收款平台</div></td></tr>'
            . '<tr><td style="padding:32px;">'
            . '<div style="font-size:18px;font-weight:700;color:#0f172a;margin-bottom:8px;">' . $info['desc'] . '</div>'
            . '<div style="font-size:14px;color:#64748b;margin-bottom:24px;">为保障您的账户安全，请输入下方验证码完成验证</div>'
            . '<div style="background:#f8fafc;border:2px dashed ' . $info['color'] . ';border-radius:12px;padding:22px;text-align:center;margin:24px 0;">'
            . '<div style="font-size:12px;color:#94a3b8;letter-spacing:3px;margin-bottom:8px;">验证码 · VERIFICATION CODE</div>'
            . '<div style="font-size:38px;font-weight:800;letter-spacing:10px;color:' . $info['color'] . ';font-family:Menlo,Consolas,monospace;">' . $code . '</div>'
            . '<div style="font-size:12px;color:#94a3b8;margin-top:10px;">有效期内请尽快使用</div></div>'
            . '<div style="background:#fff7ed;border-left:4px solid #f59e0b;border-radius:6px;padding:12px 16px;margin:16px 0;">'
            . '<div style="font-size:13px;color:#92400e;font-weight:600;margin-bottom:4px;">安全提示</div>'
            . '<div style="font-size:13px;color:#78350f;">' . $info['tip'] . '</div></div>'
            . '<div style="font-size:13px;color:#94a3b8;margin-top:24px;padding-top:16px;border-top:1px solid #e2e8f0;">如非本人操作，请忽略本邮件，您的账号不会受到任何影响。</div>'
            . '</td></tr>'
            . '<tr><td style="background:#f8fafc;padding:20px 32px;text-align:center;border-top:1px solid #e2e8f0;">'
            . '<div style="font-size:12px;color:#64748b;">本邮件由系统自动发送，请勿直接回复</div>'
            . '<div style="font-size:12px;color:#94a3b8;margin-top:6px;">&copy; ' . date('Y') . ' ' . $sitename . ' &middot; <a href="' . $siteurl . '" style="color:' . $info['color'] . ';text-decoration:none;">' . $siteurl . '</a></div>'
            . '</td></tr></table></td></tr></table></body></html>';

        return send_mail($email, $title, $body);
    }

    /**
     * 验证验证码
     * @param string $scene 验证码场景
     * @param int $type 验证码类型:0邮件,1手机
     * @param string $sendto 接收人
     * @param string $code 验证码
     * @param int $uid 用户ID
     * @return mixed
     */
    public static function verify_code($scene, $type, $sendto, $code, $uid = 0){
        global $DB;
        $where = ['scene'=>$scene, 'type'=>$type, 'to'=>$sendto];
        if($uid > 0) $where['uid'] = $uid;
        $row = $DB->find('regcode', '*', $where, 'id DESC', 1);
        if (!$row) {
            return '请重新获取验证码！';
        }elseif($row['time']<time()-3600 || $row['status']>0 || $row['errcount']>=5){
            return '验证码已失效，请重新获取';
        }elseif($row['code']!=$code){
            $DB->exec("update `pay_regcode` set `errcount`=`errcount`+1 where `id`='{$row['id']}'");
            return '验证码不正确！';
        }
        self::$regcodeid = $row['id'];
        return true;
    }

    //作废验证码
    public static function void_code(){
        global $DB;
        if(self::$regcodeid){
            $DB->exec("update `pay_regcode` set `status`='1' where `id`=:id", [':id'=>self::$regcodeid]);
            self::$regcodeid = null;
        }
    }

}