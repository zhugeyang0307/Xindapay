<?php
$is_defend = true;
include("../includes/common.php");
if($islogin2 != 1) exit("<script>location.href='./login.php';</script>");

$uid = (int)$userrow['uid'];
$myLevel = (int)$userrow['sub_level'];
$myRate  = (float)$userrow['rate'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');
    
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $pwd      = trim($_POST['pwd'] ?? '');
    $rate     = (float)($_POST['rate'] ?? 0);
    $parentUid = (int)($_POST['parent_uid'] ?? $uid);
    
    if ($myLevel >= 3) exit(json_encode(['code'=>-1, 'msg'=>'您已达最深级别，无法创建下级']));
    if (strlen($username) < 2) exit(json_encode(['code'=>-1, 'msg'=>'账号至少 2 位']));
    if (strlen($pwd) < 6) exit(json_encode(['code'=>-1, 'msg'=>'密码至少 6 位']));
    if ($rate < 0 || $rate > 50) exit(json_encode(['code'=>-1, 'msg'=>'分润比例 0-50']));
    
    // 校验 parent_uid 是不是我的下级（仅一级可帮下级创建）
    if ($parentUid !== $uid) {
//        if ($myLevel !== 1) exit(json_encode(['code'=>-1, 'msg'=>'无权操作']));
        $parent = $DB->getRow("SELECT uid, sub_level, upid FROM pay_user WHERE uid=:u", [':u'=>$parentUid]);
        if (!$parent) exit(json_encode(['code'=>-1, 'msg'=>'目标上级不存在']));
        // 必须是我的直接下级
        if ((int)$parent['upid'] !== $uid) exit(json_encode(['code'=>-1, 'msg'=>'只能帮直接下级创建']));
//        if ((int)$parent['sub_level'] !== 2) exit(json_encode(['code'=>-1, 'msg'=>'该下级已达最深，无法创建']));
    }
    
    // 账号唯一检查
    if ($DB->getColumn("SELECT COUNT(*) FROM pay_user WHERE username=:u", [':u'=>$username])) {
        exit(json_encode(['code'=>-1, 'msg'=>'账号已存在']));
    }
    if ($email && $DB->getColumn("SELECT COUNT(*) FROM pay_user WHERE email=:e", [':e'=>$email])) {
        exit(json_encode(['code'=>-1, 'msg'=>'邮箱已存在']));
    }
    
    // 计算新层级
    $parentInfo = $DB->getRow("SELECT uid, sub_level FROM pay_user WHERE uid=:u", [':u'=>$parentUid]);
    // 强制新建账号为码商身份，无 API 权限
    $newLevel = 2;
    
    $key = random(32);
    
    $ok = $DB->exec("INSERT INTO pay_user (upid, gid, `key`, money, email, phone, username, sub_level, rate, addtime, pay, settle, keylogin, apply, status) 
                     VALUES (:upid, 4, :key, 0.00, :email, :phone, :username, :lv, :rate, NOW(), 1, 1, 1, 1, 1)",
        [':upid'=>$parentUid, ':key'=>$key, ':email'=>$email, ':phone'=>$phone, ':username'=>$username, ':lv'=>$newLevel, ':rate'=>$rate]);
    
    if (!$ok) exit(json_encode(['code'=>-1, 'msg'=>'创建失败']));
    $newUid = (int)$DB->lastInsertId();
    
    // 设置密码
    $pwdHash = getMd5Pwd($pwd, $newUid);
    $DB->exec("UPDATE pay_user SET pwd=:p WHERE uid=:u", [':p'=>$pwdHash, ':u'=>$newUid]);
    
    exit(json_encode([
        'code'=>1, 
        'msg'=>"创建成功！\nUID：{$newUid}\n账号：{$username}\n密码：{$pwd}\n层级：L{$newLevel}\n分润：{$rate}%",
        'uid'=>$newUid
    ]));
}

// GET：渲染页面
// 可选：帮某个下级创建（?for=child 显示下拉选 parent）
$canHelpChild = ($myLevel === 1);
$myDirectSubs = [];
if ($canHelpChild) {
    $myDirectSubs = $DB->getAll("SELECT uid, username, email, sub_level FROM pay_user WHERE upid=:u AND sub_level=2 ORDER BY uid", [':u'=>$uid]);
}

$title = '创建下级商户';
include './head.php';
?>
<style>
.crt-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 32px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.04);
    border: 1px solid #f0f0f0;
    margin-bottom: 20px;
    max-width: 720px;
    margin-left: auto;
    margin-right: auto;
}
.fm-row { margin-bottom: 20px; }
.fm-row label {
    display: block;
    font-size: 14px;
    color: #1f2937;
    font-weight: 600;
    margin-bottom: 8px;
}
.fm-row input {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    font-size: 15px;
    box-sizing: border-box;
    transition: all 0.2s;
    outline: none;
    background: #f9fafb;
}
.fm-row input:focus {
    border-color: #3b82f6;
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}
.fm-row .hint {
    font-size: 12px;
    color: #9ca3af;
    margin-top: 6px;
}
.btn-crt {
    background: #3b82f6;
    color: #fff;
    border: none;
    padding: 14px 40px;
    border-radius: 10px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    width: 100%;
    transition: background 0.2s;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
}
.btn-crt:hover { background: #2563eb; }
.btn-crt:disabled { opacity: .6; cursor: not-allowed; }
.tip {
    background: #eff6ff;
    border-left: 4px solid #3b82f6;
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 24px;
    font-size: 14px;
    color: #1e40af;
    line-height: 1.8;
}
</style>

<div id="content" class="app-content" role="main">
<div class="app-content-body">
<div class="bg-light lter b-b wrapper-md">
  <h1 class="m-n font-thin h3"><i class="fa fa-user-plus"></i> 创建下级商户</h1>
</div>
<div class="wrapper-md">

    <div class="crt-card">
    <div class="tip">
      · 您当前身份是：<b><?php echo $myLevel == 1 ? '主控后台' : '码商后台'; ?></b><br>
      · 新账号身份为：<b>下级码商</b>（无 API 接口权限，仅支持上传收款码）<br>
      · 分润比例：下级每笔收款，按比例分给您（从下级余额扣）
    </div>

    <div class="fm-row">
      <label>👤 登录账号 <span style="color:red;">*</span></label>
      <input type="text" id="username" placeholder="如：dianpu001" maxlength="32">
    </div>
    
    <div class="fm-row">
      <label>🔑 登录密码 <span style="color:red;">*</span></label>
      <input type="text" id="pwd" placeholder="至少 6 位，创建后请告知下级" maxlength="32">
    </div>

    <div class="fm-row">
      <label>📧 电子邮箱</label>
      <input type="email" id="email" placeholder="用于找回密码和邮件推送" maxlength="64">
    </div>

    <div class="fm-row">
      <label>📱 手机号</label>
      <input type="text" id="phone" placeholder="11 位手机号" maxlength="20">
    </div>

    <div class="fm-row">
      <label>💰 分润比例 (%)</label>
      <input type="number" id="rate" value="0" min="0" max="50" step="0.01">
      <div class="hint">例如填 10，表示下级收款 100 元，扣 10 元本金分给您。</div>
    </div>

    <input type="hidden" id="parent_uid" value="<?php echo $uid;?>">
    
    <button class="btn-crt" onclick="submitCreate(this)" id="createBtn">🚀 立即创建账号</button>
  </div></div></div></div>

<script>
function doCreate() {
  var btn = document.getElementById('btnCrt');
  var data = {
    parent_uid: document.getElementById('parent_uid').value,
    username:   document.getElementById('username').value.trim(),
    pwd:        document.getElementById('pwd').value.trim(),
    email:      document.getElementById('email').value.trim(),
    phone:      document.getElementById('phone').value.trim(),
    rate:       document.getElementById('rate').value
  };
  if (!data.username || data.username.length < 2) { alert('账号至少 2 位'); return; }
  if (!data.pwd || data.pwd.length < 6) { alert('密码至少 6 位'); return; }

  btn.disabled = true; btn.textContent = '⏳ 创建中...';
  var fd = new FormData();
  for (var k in data) fd.append(k, data[k]);
  fetch('./sub_create.php', {method:'POST', body:fd})
    .then(r => r.json())
    .then(d => {
      if (d.code === 1) {
        // 构建带复制按钮的弹窗内容
        var copyHtml = '<div style="padding:10px 0;">'
            + '<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:15px; font-size:14px; line-height:2; white-space:pre-wrap; margin-bottom:15px; color:#334155;" id="copyTextArea">'
            + d.copy_text
            + '</div>'
            + '<button type="button" onclick="copyLoginInfo()" style="width:100%; padding:10px; background:#3b82f6; color:#fff; border:none; border-radius:6px; font-size:15px; font-weight:bold; cursor:pointer; transition: background 0.2s;" onmouseover="this.style.background=\'#2563eb\'" onmouseout="this.style.background=\'#3b82f6\'">📋 一键复制登录信息</button>'
            + '</div>';

        if (typeof layer !== 'undefined') {
            layer.open({
                type: 1,
                title: '✅ 创建成功',
                area: ['90%', 'auto'],
                maxWidth: 400,
                content: copyHtml,
                btn: ['前往列表'],
                yes: function(index){
                    layer.close(index);
                    location.href = './sub_list.php';
                }
            });
            
            // 定义全局复制函数供按钮调用
            window.copyLoginInfo = function() {
                var text = d.copy_text;
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(function() {
                        layer.msg('✅ 复制成功！可以去粘贴给下级了', {icon:1});
                    }).catch(function(err) {
                        fallbackCopyText(text);
                    });
                } else {
                    fallbackCopyText(text);
                }
            };
            
            function fallbackCopyText(text) {
                var textarea = document.createElement("textarea");
                textarea.value = text;
                textarea.style.position = "fixed";
                textarea.style.opacity = "0";
                document.body.appendChild(textarea);
                textarea.select();
                try {
                    document.execCommand('copy');
                    layer.msg('✅ 复制成功！可以去粘贴给下级了', {icon:1});
                } catch (err) {
                    layer.alert('复制失败，请手动长按选中文字复制！');
                }
                document.body.removeChild(textarea);
            }
        } else {
            // 如果没引入 layer 库的备选方案
            var input = document.createElement('textarea');
            input.value = d.copy_text;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
            alert('✅ 登录信息已复制到剪贴板！点击确定前往列表。');
            location.href = './sub_list.php';
        }
      } else {
        alert('失败：' + d.msg);
        btn.disabled = false; btn.textContent = '✅ 创建下级商户';
      }
    })
    .catch(e => {
      alert('网络错误：' + e.message);
      btn.disabled = false; btn.textContent = '✅ 创建下级商户';
    });
}
</script>
<?php include './foot.php'; ?>