<?php
/**
 * 用户注册页面
 */

// 使用安全的 Session 启动函数
require_once __DIR__ . '/../app/functions.php';
safeSessionStart();

// 如果已登录，直接跳转到仪表板
if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit;
}

// 检查是否允许注册（服务端验证）
require_once __DIR__ . '/../app/functions.php';

try {
    $pdo = getDbConnection();
    if (isInstalled()) {
        $settingsService = new SettingsService($pdo);
        if (!$settingsService->isRegistrationAllowed()) {
            header('HTTP/1.1 403 Forbidden');
            echo '<h1>禁止访问</h1><p>当前系统关闭了用户注册功能</p>';
            exit;
        }
    }
} catch (\Exception $e) {
    // 如果检查失败，继续显示页面（客户端会再次验证）
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注册 - <?= APP_NAME ?></title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="stylesheet" href="<?= assetVersion('assets/css/dashboard.css') ?>">
    <style>
        .register-container {
            max-width: 450px;
            margin: 60px auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .register-header h1 {
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
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 12px;
        }
        .btn-register {
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
        .btn-register:hover {
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
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
        .register-footer {
            margin-top: 20px;
            text-align: center;
            font-size: 14px;
            color: #666;
        }
        .register-footer a {
            color: #4a90e2;
            text-decoration: none;
        }
        .register-footer a:hover {
            text-decoration: underline;
        }
        .password-strength {
            margin-top: 8px;
            height: 4px;
            background: #eee;
            border-radius: 2px;
            overflow: hidden;
        }
        .password-strength-bar {
            height: 100%;
            width: 0;
            transition: width 0.3s, background 0.3s;
        }
        .strength-weak { background: #f44; width: 33%; }
        .strength-medium { background: #fa0; width: 66%; }
        .strength-strong { background: #3c3; width: 100%; }
    </style>
</head>
<body class="register-page">
    <div class="register-container">
        <div class="register-header">
            <h1><img src="/assets/images/logo.svg" alt="Logo" style="width: 28px; height: 28px; vertical-align: middle; margin-right: 4px;"><?= APP_NAME ?></h1>
            <p style="color: #666; margin-top: 10px;">创建您的账户</p>
        </div>
        
        <div id="alert-container"></div>
        
        <form id="registerForm">
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" required autocomplete="username" placeholder="3-50 位字母、数字、下划线">
                <small>只能包含字母、数字和下划线，必须以字母开头</small>
            </div>
            
            <div class="form-group">
                <label for="email">邮箱</label>
                <input type="email" id="email" name="email" required autocomplete="email" placeholder="your@email.com">
            </div>
            
            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" required autocomplete="new-password" minlength="8" placeholder="至少 8 位">
                <div class="password-strength">
                    <div class="password-strength-bar" id="strength-bar"></div>
                </div>
                <small>至少 8 位，建议使用大小写字母、数字和符号组合</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">确认密码</label>
                <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password" placeholder="再次输入密码">
            </div>
            
            <button type="submit" class="btn-register">
                <i class="bi bi-person-plus"></i> 立即注册
            </button>
        </form>
        
        <div class="register-footer">
            <p>已有账户？<a href="/login.php">返回登录</a></p>
        </div>
    </div>
    
    <script>
        // 用户名可用性检查（防抖）
        let usernameTimer = null;
        document.getElementById('username').addEventListener('input', function(e) {
            const username = e.target.value.trim();
            clearTimeout(usernameTimer);
            
            if (username.length < 3) return;
            
            // 500ms 防抖
            usernameTimer = setTimeout(() => {
                fetch('/api/auth/check_username.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username })
                })
                .then(r => r.json())
                .then(data => {
                    const input = document.getElementById('username');
                    if (data.exists) {
                        input.style.borderColor = '#f44';
                        input.setCustomValidity('该用户名已被使用');
                        showInlineError('username', '该用户名已被使用');
                    } else {
                        input.style.borderColor = '#3c3';
                        input.setCustomValidity('');
                        clearInlineError('username');
                    }
                });
            }, 500);
        });
        
        // 邮箱可用性检查（防抖）
        let emailTimer = null;
        document.getElementById('email').addEventListener('input', function(e) {
            const email = e.target.value.trim();
            clearTimeout(emailTimer);
            
            if (!email.includes('@')) return;
            
            emailTimer = setTimeout(() => {
                fetch('/api/auth/check_email.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email })
                })
                .then(r => r.json())
                .then(data => {
                    const input = document.getElementById('email');
                    if (data.exists) {
                        input.style.borderColor = '#f44';
                        input.setCustomValidity('该邮箱已注册账户');
                        showInlineError('email', '该邮箱已注册账户');
                    } else {
                        input.style.borderColor = '#3c3';
                        input.setCustomValidity('');
                        clearInlineError('email');
                    }
                });
            }, 500);
        });
        
        // 监听表单提交
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            if (password !== confirmPassword) {
                showAlert('两次输入的密码不一致', 'error');
                return;
            }
            
            // 显示 loading 状态
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> 注册中...';
            
            const formData = {
                username: document.getElementById('username').value.trim(),
                email: document.getElementById('email').value.trim(),
                password: password,
                confirm_password: confirmPassword
            };
            
            fetch('/api/auth/register.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message || '注册成功', 'success');
                    if (data.redirect) {
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 1000);
                    } else {
                        setTimeout(() => {
                            window.location.href = '/dashboard.php';
                        }, 1000);
                    }
                } else {
                    showAlert(data.message || '注册失败', 'error');
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
            
        // 显示行内错误
        function showInlineError(fieldId, message) {
            clearInlineError(fieldId);
            const input = document.getElementById(fieldId);
            const errorDiv = document.createElement('div');
            errorDiv.className = 'inline-error';
            errorDiv.id = fieldId + '-error';
            errorDiv.style.cssText = 'color: #f44; font-size: 12px; margin-top: 5px;';
            errorDiv.innerHTML = '<i class="bi bi-exclamation-circle"></i> ' + message;
            input.parentNode.appendChild(errorDiv);
        }
        
        function clearInlineError(fieldId) {
            const existingError = document.getElementById(fieldId + '-error');
            if (existingError) {
                existingError.parentNode.removeChild(existingError);
            }
        }
        
        // 密码强度检查
        document.getElementById('password').addEventListener('input', function(e) {
            const password = e.target.value;
            const bar = document.getElementById('strength-bar');
            
            const strength = calculatePasswordStrength(password);
            
            if (password.length === 0) {
                bar.className = 'password-strength-bar';
                bar.setAttribute('title', '');
            } else if (strength < 3) {
                bar.className = 'password-strength-bar strength-weak';
                bar.setAttribute('title', '密码强度：弱');
            } else if (strength < 5) {
                bar.className = 'password-strength-bar strength-medium';
                bar.setAttribute('title', '密码强度：中');
            } else {
                bar.className = 'password-strength-bar strength-strong';
                bar.setAttribute('title', '密码强度：强');
            }
        });
        });
        
        function calculatePasswordStrength(password) {
            let score = 0;
            if (password.length >= 8) score++;
            if (password.length >= 12) score++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
            if (/\d/.test(password)) score++;
            if (/[^a-zA-Z0-9]/.test(password)) score++;
            return score;
        }
        
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
