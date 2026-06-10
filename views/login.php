<?php
/**
 * 登录页面
 */

// 处理登录提交
$loginResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginResult = handleLogin();
    if ($loginResult['success']) {
        header('Location: /?page=dashboard');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - <?= APP_NAME ?></title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="stylesheet" href="<?= assetVersion('assets/css/dashboard.css') ?>">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-header">
            <h1><img src="/assets/images/logo.svg" alt="Logo" style="width: 28px; height: 28px; vertical-align: middle; margin-right: 4px;"><?= APP_NAME ?></h1>
         
        </div>
        
        <?php if ($loginResult && !$loginResult['success']): ?>
            <div class="alert alert-error">
                <?= e($loginResult['message']) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn-login"><i class="bi bi-box-arrow-in-right"></i> 登录</button>
        </form>
    </div>
</body>
</html>
