<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<title>路由后台登录</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-body">
<form method="post" class="login-box">
    <h2>智能路由 · 后台</h2>
    <?php if (!empty($loginError)): ?>
        <p class="error"><?= htmlspecialchars($loginError) ?></p>
    <?php endif; ?>
    <input type="password" name="_pass" placeholder="请输入密码" autofocus required>
    <button type="submit">登录</button>
</form>
</body>
</html>