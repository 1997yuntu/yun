<?php
/**
 * 找回密码页面
 */

require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../vendor/autoload.php';

use JefCounts\Services\UserService;

$pdo = getDbConnection();
$userService = new UserService($pdo);

$message = '';
$messageType = '';
$emailSent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $message = '请输入邮箱地址';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = '邮箱格式不正确';
        $messageType = 'error';
    } else {
        // 查找用户
        $user = $userService->getUserByEmail($email);
        
        if ($user) {
            // TODO: 生成重置令牌并发送邮箱
            // 为了安全，不管是否找到用户都显示成功提示
            $emailSent = true;
            
            // 生成重置令牌（实际应该保存到数据库）
            $resetToken = bin2hex(random_bytes(32));
            $resetLink = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . 
                        "://$_SERVER[HTTP_HOST]/reset_password.php?token=$resetToken&email=" . urlencode($email);
            
            // 模拟发送邮件（实际应该使用 mail() 或 SMTP）
            // mail($email, '找回密码', "重置链接：$resetLink");
            
            // 在实际应用中，应该：
            // 1. 将 token 和过期时间保存到 reset_tokens 表
            // 2. 发送包含重置链接的邮件
            // 3. 用户点击链接后验证 token
        }
        
        $message = '如果该邮箱已注册，您将收到一封包含密码重置链接的邮件';
        $messageType = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>找回密码 - <?= APP_NAME ?></title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="stylesheet" href="<?= assetVersion('assets/css/dashboard.css') ?>">
    <style>
        .forgot-container {
            max-width: 450px;
            margin: 80px auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .forgot-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .forgot-header h1 {
            font-size: 24px;
            color: #333;
            margin-bottom: 10px;
        }
        .forgot-header p {
            color: #666;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .form-group input:focus {
            outline: none;
            border-color: #4a90e2;
        }
        .btn-submit {
            width: 100%;
            padding: 12px;
            background: #4a90e2;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
        }
        .btn-submit:hover {
            background: #357abd;
        }
        .alert {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        .forgot-footer {
            margin-top: 20px;
            text-align: center;
            font-size: 14px;
            color: #666;
        }
        .forgot-footer a {
            color: #4a90e2;
            text-decoration: none;
        }
        .icon-lock {
            font-size: 48px;
            color: #4a90e2;
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="icon-lock">
            <i class="bi bi-key"></i>
        </div>
        
        <div class="forgot-header">
            <h1>找回密码</h1>
            <p>输入您的邮箱，我们将发送密码重置链接</p>
        </div>
        
        <div id="alert-container">
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>">
                    <?= e($message) ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (!$emailSent): ?>
            <form method="POST">
                <div class="form-group">
                    <label for="email">注册邮箱</label>
                    <input type="email" id="email" name="email" required 
                           placeholder="your@email.com" autofocus>
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="bi bi-send"></i> 发送重置链接
                </button>
            </form>
        <?php endif; ?>
        
        <div class="forgot-footer">
            <p>还记得密码？<a href="/login.php">返回登录</a></p>
        </div>
    </div>
</body>
</html>
