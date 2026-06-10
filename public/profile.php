<?php
/**
 * 用户个人设置页面
 */

require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../vendor/autoload.php';

use JefCounts\Services\UserService;

$pdo = getDbConnection();
requireLogin();

$userService = new UserService($pdo);
$user = $userService->getCurrentUser();

if (!$user) {
    header('Location: /login.php');
    exit;
}

$currentPage = 'profile';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>个人设置 - <?= APP_NAME ?></title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="stylesheet" href="<?= assetVersion('assets/css/dashboard.css') ?>">
    <style>
        .profile-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        .profile-header {
            margin-bottom: 30px;
        }
        .profile-header h1 {
            font-size: 24px;
            color: #333;
            margin-bottom: 10px;
        }
        .profile-section {
            background: white;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .profile-section h2 {
            font-size: 18px;
            color: #4a90e2;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        .form-row {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 20px;
            margin-bottom: 20px;
            align-items: center;
        }
        .form-row label {
            font-weight: 600;
            color: #555;
        }
        .form-row input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            width: 100%;
            box-sizing: border-box;
        }
        .form-row input:focus {
            outline: none;
            border-color: #4a90e2;
        }
        .form-row small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 12px;
        }
        .btn-update {
            padding: 10px 25px;
            background: #4a90e2;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-update:hover {
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
        .user-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        .user-info-item {
            padding: 15px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        .user-info-item-label {
            font-size: 13px;
            color: #666;
            margin-bottom: 5px;
        }
        .user-info-item-value {
            font-size: 16px;
            color: #333;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?php include '_header.php'; ?>
    
    <div class="main-content">
        <div class="profile-container">
            <div class="profile-header">
                <h1>个人设置</h1>
                <p style="color: #666;">管理您的账户信息和安全设置</p>
            </div>
            
            <div id="alert-container"></div>
            
            <!-- 账户信息 -->
            <div class="profile-section">
                <h2><i class="bi bi-person-circle"></i> 账户信息</h2>
                <div class="user-info-grid">
                    <div class="user-info-item">
                        <div class="user-info-item-label">用户名</div>
                        <div class="user-info-item-value"><?= e($user['username']) ?></div>
                    </div>
                    <div class="user-info-item">
                        <div class="user-info-item-label">邮箱</div>
                        <div class="user-info-item-value"><?= e($user['email']) ?></div>
                    </div>
                    <div class="user-info-item">
                        <div class="user-info-item-label">账户角色</div>
                        <div class="user-info-item-value">
                            <?php if ($user['role'] === 'admin'): ?>
                                <span style="color: #4a90e2;">管理员</span>
                            <?php else: ?>
                                <span style="color: #666;">普通用户</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="user-info-item">
                        <div class="user-info-item-label">注册时间</div>
                        <div class="user-info-item-value"><?= date('Y-m-d H:i', strtotime($user['created_at'])) ?></div>
                    </div>
                    <?php if ($user['last_login_at']): ?>
                    <div class="user-info-item">
                        <div class="user-info-item-label">最后登录</div>
                        <div class="user-info-item-value">
                            <?= date('Y-m-d H:i', strtotime($user['last_login_at'])) ?>
                            <?php if ($user['last_login_ip']): ?>
                                <br><small style="color: #666;">IP: <?= e($user['last_login_ip']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 修改资料 -->
            <div class="profile-section">
                <h2><i class="bi bi-pencil-square"></i> 修改资料</h2>
                <form id="profileForm">
                    <div class="form-row">
                        <label for="email">邮箱地址</label>
                        <div>
                            <input type="email" id="email" name="email" value="<?= e($user['email']) ?>" required>
                            <small>用于账户找回和重要通知</small>
                        </div>
                    </div>
                    <div class="form-row">
                        <label></label>
                        <div>
                            <button type="submit" class="btn-update">
                                <i class="bi bi-check-lg"></i> 更新资料
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- 修改密码 -->
            <div class="profile-section">
                <h2><i class="bi bi-lock"></i> 修改密码</h2>
                <form id="passwordForm">
                    <div class="form-row">
                        <label for="old_password">原密码</label>
                        <div>
                            <input type="password" id="old_password" name="old_password" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <label for="new_password">新密码</label>
                        <div>
                            <input type="password" id="new_password" name="new_password" minlength="8" required>
                            <small>至少 8 位，建议使用大小写字母、数字和符号组合</small>
                        </div>
                    </div>
                    <div class="form-row">
                        <label for="confirm_password">确认新密码</label>
                        <div>
                            <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <label></label>
                        <div>
                            <button type="submit" class="btn-update">
                                <i class="bi bi-key"></i> 修改密码
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // 更新资料
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value.trim();
            
            fetch('/api/user/profile.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ email })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showAlert('资料更新成功', 'success');
                } else {
                    showAlert(data.message || '更新失败', 'error');
                }
            })
            .catch(err => {
                showAlert('网络错误，请稍后重试', 'error');
            });
        });
        
        // 修改密码
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const oldPassword = document.getElementById('old_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                showAlert('两次输入的新密码不一致', 'error');
                return;
            }
            
            fetch('/api/user/password.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    old_password: oldPassword,
                    new_password: newPassword,
                    confirm_password: confirmPassword
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showAlert('密码修改成功', 'success');
                    document.getElementById('passwordForm').reset();
                } else {
                    showAlert(data.message || '修改失败', 'error');
                }
            })
            .catch(err => {
                showAlert('网络错误，请稍后重试', 'error');
            });
        });
        
        function showAlert(message, type) {
            const alertContainer = document.getElementById('alert-container');
            const alertStyle = type === 'success' 
                ? 'background:#efe; color:#3c3; border:#cfc;' 
                : 'background:#fee; color:#c33; border:#fcc;';
            
            alertContainer.innerHTML = `
                <div class="alert alert-success" style="${alertStyle}">
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
