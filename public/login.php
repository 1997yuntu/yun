<?php
/**
 * 用户登录页面
 */

// 使用安全的 Session 启动函数
require_once __DIR__ . '/../app/functions.php';
safeSessionStart();

// 如果已登录，直接跳转到仪表板
if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit;
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
    <style>
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h1 {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: #333;
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
        .remember-me {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        .remember-me input {
            margin-right: 8px;
            width: auto;
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: #4a90e2;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-login:hover {
            background: #357abd;
        }
        .alert {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        .login-footer {
            margin-top: 20px;
            text-align: center;
            font-size: 14px;
            color: #666;
        }
        .login-footer a {
            color: #4a90e2;
            text-decoration: none;
        }
        .login-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-header">
            <h1><img src="/assets/images/logo.svg" alt="Logo" style="width: 28px; height: 28px; vertical-align: middle; margin-right: 4px;"><?= APP_NAME ?></h1>
        </div>
        
        <div id="alert-container"></div>
        
        <form id="loginForm">
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" required autofocus autocomplete="username">
            </div>
            
            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            
            <div class="remember-me">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember" style="margin: 0; font-weight: normal;">记住我（7 天内自动登录）</label>
            </div>
            
            <button type="submit" class="btn-login">
                <i class="bi bi-box-arrow-in-right"></i> 登录
            </button>
        </form>
        
        <div class="login-footer">
            <p id="register-link-container">
                还没有账户？<a href="/register.php">立即注册</a>
            </p>
            <p style="margin-top: 10px;">
                <a href="/forgot_password.php" style="color: #4a90e2; text-decoration: none; font-size: 13px;">忘记密码？</a>
            </p>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 检查注册开关状态
            fetch('/api/settings.php?check=registration')
                .then(r => r.json())
                .then(data => {
                    if (!data.allowed) {
                        document.getElementById('register-link-container').style.display = 'none';
                    }
                })
                .catch(() => {});
            
            // 监听表单提交
            document.getElementById('loginForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                
                // 显示 loading 状态
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> 登录中...';
                
                const formData = {
                    username: document.getElementById('username').value.trim(),
                    password: document.getElementById('password').value,
                    remember: document.getElementById('remember').checked
                };
                
                fetch('/api/auth/login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showAlert('登录成功，正在跳转...', 'success');
                        setTimeout(() => {
                            window.location.href = data.redirect || '/dashboard.php';
                        }, 500);
                    } else {
                        showAlert(data.message || '登录失败', 'error');
                        // 恢复按钮状态
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                })
                .catch(err => {
                    showAlert('网络错误，请稍后重试', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                });
            });
        });
        
        function showAlert(message, type) {
            const alertContainer = document.getElementById('alert-container');
            const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
            const alertStyle = type === 'success' 
                ? 'background:#efe; color:#3c3; border:#cfc;' 
                : 'background:#fee; color:#c33; border:#fcc;';
            
            alertContainer.innerHTML = `
                <div class="alert ${alertClass}" style="${alertStyle}">
                    ${escapeHtml(message)}
                </div>
            `;
            
            setTimeout(() => {
                alertContainer.innerHTML = '';
            }, 5000);
        }
        
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
    </script>
</body>
</html>
