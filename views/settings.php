<?php
/**
 * 设置页面视图
 * 系统配置和管理
 */

// 引入服务类
require_once __DIR__ . '/../src/Services/SettingsService.php';

// 初始化服务
$settingsService = new SettingsService($pdo);

// 处理POST请求
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF保护
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $message = 'CSRF验证失败，请刷新页面重试';
        $messageType = 'error';
    } elseif (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_settings':
                // 更新设置
                $newSettings = [];
                foreach (['display', 'data', 'dashboard', 'security', 'performance', 'user'] as $category) {
                    if (isset($_POST[$category])) {
                        $newSettings[$category] = $_POST[$category];
                    }
                }
                
                // 处理复选框（未选中时不会在 POST 中出现）
                $checkboxes = [
                    'data' => ['auto_cleanup'],
                    'performance' => ['enable_cache', 'compress_response'],
                    'user' => ['allow_registration']
                ];
                
                foreach ($checkboxes as $category => $fields) {
                    if (!isset($newSettings[$category])) {
                        $newSettings[$category] = [];
                    }
                    foreach ($fields as $field) {
                        // 如果复选框没有在 POST 中，设为 false
                        if (!isset($newSettings[$category][$field])) {
                            $newSettings[$category][$field] = false;
                        } else {
                            // 如果存在，设为 true
                            $newSettings[$category][$field] = true;
                        }
                    }
                }
                
                if ($settingsService->updateSettings($newSettings)) {
                    $message = '设置已成功保存！';
                    $messageType = 'success';
                    // 重新获取设置
                    $settings = $settingsService->getAllSettings();
                } else {
                    $message = '设置保存失败，请重试。';
                    $messageType = 'error';
                }
                break;
                
            case 'update_user_settings':
                // 更新用户管理设置
                $newUserSettings = [
                    'user' => [
                        'allow_registration' => isset($_POST['user']['allow_registration']) ? true : false,
                        'max_sites_per_user' => isset($_POST['user']['max_sites_per_user']) ? (int) $_POST['user']['max_sites_per_user'] : 10
                    ]
                ];
                
                if ($settingsService->updateSettings($newUserSettings)) {
                    $message = '用户设置已成功保存！';
                    $messageType = 'success';
                    // 重新获取设置
                    $settings = $settingsService->getAllSettings();
                } else {
                    $message = '用户设置保存失败，请重试。';
                    $messageType = 'error';
                }
                break;
                
            case 'change_password':
                // 修改密码
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';
                
                // 基本验证
                if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                    $message = '所有密码字段都必须填写';
                    $messageType = 'error';
                } elseif ($newPassword !== $confirmPassword) {
                    $message = '两次输入的新密码不一致';
                    $messageType = 'error';
                } else {
                    // 调用密码修改函数
                    $result = changeAdminPassword($currentPassword, $newPassword);
                    $message = $result['message'];
                    $messageType = $result['success'] ? 'success' : 'error';
                }
                break;
                
            case 'cleanup_data':
                // 清理数据
                $cleanupOptions = [];
                
                // 快捷清理选项
                if (isset($_POST['quick_cleanup'])) {
                    $quickType = $_POST['quick_cleanup'];
                    switch ($quickType) {
                        case '3months':
                            $cutoffDate = date('Y-m-d H:i:s', strtotime('-3 months'));
                            $cleanupOptions['before_date'] = $cutoffDate;
                            break;
                        case '6months':
                            $cutoffDate = date('Y-m-d H:i:s', strtotime('-6 months'));
                            $cleanupOptions['before_date'] = $cutoffDate;
                            break;
                        case '1year':
                            $cutoffDate = date('Y-m-d H:i:s', strtotime('-1 year'));
                            $cleanupOptions['before_date'] = $cutoffDate;
                            break;
                    }
                }
                
                // 自定义日期范围
                if (!empty($_POST['start_date']) || !empty($_POST['end_date'])) {
                    $cleanupOptions['date_range'] = [
                        'start' => $_POST['start_date'] ?? null,
                        'end' => $_POST['end_date'] ?? null
                    ];
                }
                
                if (isset($_POST['cleanup_bots'])) {
                    $cleanupOptions['cleanup_bots'] = true;
                }
                
                if (isset($_POST['cleanup_expired'])) {
                    $cleanupOptions['cleanup_expired'] = true;
                }
                
                if (isset($_POST['optimize_tables'])) {
                    $cleanupOptions['optimize_tables'] = true;
                }
                
                $result = $settingsService->cleanupData($cleanupOptions);
                
                if ($result['success']) {
                    $deletedCount = ($result['deleted_records'] ?? 0) + 
                                   ($result['deleted_bots'] ?? 0) + 
                                   ($result['deleted_expired'] ?? 0);
                    $message = "数据清理完成！共删除 {$deletedCount} 条记录。";
                    $messageType = 'success';
                    // 重新获取数据库统计信息
                    $dbStats = $settingsService->getDatabaseStats();
                    // 清除可能的缓存
                    if (function_exists('opcache_reset')) {
                        opcache_reset();
                    }
                } else {
                    $message = '数据清理失败：' . ($result['error'] ?? '未知错误');
                    $messageType = 'error';
                }
                break;
                
            case 'reset_settings':
                // 重置设置
                if ($settingsService->resetToDefaults()) {
                    $message = '设置已重置为默认值！';
                    $messageType = 'success';
                    // 重新获取设置
                    $settings = $settingsService->getAllSettings();
                } else {
                    $message = '设置重置失败，请重试。';
                    $messageType = 'error';
                }
                break;
        }
    }
}

// 获取当前设置
$settings = $settingsService->getAllSettings();
$dbStats = $settingsService->getDatabaseStats();
$currentUser = getCurrentUser();

// 获取站点参数（用于返回时保持选择状态）
$currentSiteKey = $_GET['site'] ?? null;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统设置 - <?= APP_NAME ?></title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="stylesheet" href="<?= assetVersion('assets/css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= assetVersion('assets/css/settings.css') ?>">
</head>
<body>
    <div class="container">
        <!-- 头部 -->
        <div class="header">
            <div class="header-left">
                <h1><i class="bi bi-gear"></i> 系统设置</h1>
                <span class="user-info">欢迎，<?= e($currentUser['username']) ?></span>
            </div>
            <div class="header-right">
                <a href="/?page=sites<?= $currentSiteKey ? '&from_site=' . e($currentSiteKey) : '' ?>" class="btn-nav" title="站点管理">
                    <i class="bi bi-globe"></i>
                    <span class="btn-text">站点</span>
                </a>
                <a href="/?page=dashboard<?= $currentSiteKey ? '&site=' . e($currentSiteKey) : '' ?>" class="btn-nav" title="仪表板">
                    <i class="bi bi-graph-up"></i>
                    <span class="btn-text">仪表板</span>
                </a>

                <a href="/?logout=1" class="btn-logout" title="退出登录">
                    <i class="bi bi-box-arrow-right"></i>
                    <span class="btn-text">退出</span>
                </a>
            </div>
        </div>
        
        <!-- 消息提示 -->
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <i class="bi bi-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                <?= e($message) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <input type="hidden" name="action" value="update_settings">
            
            <div class="settings-grid">
                <!-- 显示设置 -->
                <div class="settings-section">
                    <h2 class="section-title">
                        <i class="bi bi-display"></i>
                        显示设置
                    </h2>
                    
                    <div class="form-group">
                        <label class="form-label">IP访问统计显示行数</label>
                        <input type="number" name="display[ip_stats_limit]" class="form-input" 
                               value="<?= $settings['display']['ip_stats_limit'] ?? 10 ?>" min="5" max="100">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">热门页面显示行数</label>
                        <input type="number" name="display[popular_pages_limit]" class="form-input" 
                               value="<?= $settings['display']['popular_pages_limit'] ?? 10 ?>" min="5" max="50">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">浏览器统计显示行数</label>
                        <input type="number" name="display[browser_stats_limit]" class="form-input" 
                               value="<?= $settings['display']['browser_stats_limit'] ?? 8 ?>" min="5" max="20">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">操作系统统计显示行数</label>
                        <input type="number" name="display[os_stats_limit]" class="form-input" 
                               value="<?= $settings['display']['os_stats_limit'] ?? 8 ?>" min="5" max="20">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">机器人统计每类显示行数</label>
                        <input type="number" name="display[bot_stats_limit]" class="form-input" 
                               value="<?= $settings['display']['bot_stats_limit'] ?? 5 ?>" min="3" max="10">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">访问来源统计显示行数</label>
                        <input type="number" name="display[source_stats_limit]" class="form-input" 
                               value="<?= $settings['display']['source_stats_limit'] ?? 10 ?>" min="5" max="20">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">访问地区统计显示行数</label>
                        <input type="number" name="display[region_stats_limit]" class="form-input" 
                               value="<?= $settings['display']['region_stats_limit'] ?? 10 ?>" min="5" max="20">
                    </div>
                </div>
                
                <!-- 数据管理 -->
                <div class="settings-section">
                    <h2 class="section-title">
                        <i class="bi bi-database"></i>
                        数据管理
                    </h2>
                    
                    <div class="form-group">
                        <label class="form-label">数据保留天数</label>
                        <input type="number" name="data[retention_days]" class="form-input" 
                               value="<?= $settings['data']['retention_days'] ?? 365 ?>" min="30" max="3650">
                    </div>
                    
                    <div class="form-checkbox">
                        <input type="checkbox" name="data[auto_cleanup]" id="auto_cleanup" 
                               <?= ($settings['data']['auto_cleanup'] ?? true) ? 'checked' : '' ?>>
                        <label for="auto_cleanup">自动清理过期数据</label>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">自动清理时间</label>
                        <input type="time" name="data[cleanup_time]" class="form-input" 
                               value="<?= $settings['data']['cleanup_time'] ?? '02:00' ?>">
                    </div>
                </div>
                
                
                <!-- 安全设置 -->
                <div class="settings-section">
                    <h2 class="section-title">
                        <i class="bi bi-shield-check"></i>
                        安全设置
                    </h2>
                    
                    <div class="form-group">
                        <label class="form-label">会话超时时间（秒）</label>
                        <input type="number" name="security[session_timeout]" class="form-input" 
                               value="<?= $settings['security']['session_timeout'] ?? 7200 ?>" min="300" max="86400">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">最大登录尝试次数</label>
                        <input type="number" name="security[max_login_attempts]" class="form-input" 
                               value="<?= $settings['security']['max_login_attempts'] ?? 5 ?>" min="3" max="10">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">锁定时长（秒）</label>
                        <input type="number" name="security[lockout_duration]" class="form-input" 
                               value="<?= $settings['security']['lockout_duration'] ?? 300 ?>" min="60" max="3600">
                    </div>
                </div>
            </div>
            
            <!-- 操作按钮 -->
            <div style="text-align: center; margin-bottom: 32px;">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> 保存设置
                </button>
            </div>
        </form>
        
        <!-- 密码修改 -->
        <form method="POST" class="settings-form" id="passwordForm">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <input type="hidden" name="action" value="change_password">
            
            <div class="settings-section">
                <h2 class="section-title">
                    <i class="bi bi-key"></i>
                    修改密码
                </h2>
                
                <div class="form-group">
                    <label class="form-label" for="current_password">
                        <i class="bi bi-lock"></i> 当前密码
                        <span class="required">*</span>
                    </label>
                    <input type="password" 
                           id="current_password" 
                           name="current_password" 
                           class="form-input" 
                           required 
                           autocomplete="current-password"
                           placeholder="请输入当前密码">
                    <small class="form-help">请输入您当前使用的密码以验证身份</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="new_password">
                        <i class="bi bi-shield-lock"></i> 新密码
                        <span class="required">*</span>
                    </label>
                    <input type="password" 
                           id="new_password" 
                           name="new_password" 
                           class="form-input" 
                           required 
                           autocomplete="new-password"
                           minlength="6"
                           placeholder="请输入新密码（至少 6 位）">
                    <small class="form-help">密码长度至少为 6 位，建议使用字母、数字和特殊字符的组合</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="confirm_password">
                        <i class="bi bi-shield-check"></i> 确认新密码
                        <span class="required">*</span>
                    </label>
                    <input type="password" 
                           id="confirm_password" 
                           name="confirm_password" 
                           class="form-input" 
                           required 
                           autocomplete="new-password"
                           minlength="6"
                           placeholder="请再次输入新密码">
                    <small class="form-help">请再次输入新密码以确认</small>
                </div>
                
                <div style="text-align: center; margin-top: 24px;">
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-key-fill"></i> 修改密码
                    </button>
                </div>
            </div>
        </form>
        
        <!-- 用户管理设置 -->
        <form method="POST" class="settings-form" id="userManagementForm">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <input type="hidden" name="action" value="update_user_settings">
            
            <div class="settings-section">
                <h2 class="section-title">
                    <i class="bi bi-people"></i>
                    用户管理设置
                </h2>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="bi bi-person-plus"></i> 允许用户注册
                    </label>
                    <div class="switch-container">
                        <label class="switch">
                            <input type="checkbox" name="user[allow_registration]" 
                                   <?= ($settings['user']['allow_registration'] ?? false) ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                        <span class="switch-label">开启后，用户可以自助注册账户</span>
                    </div>
                    <small class="form-help">关闭后，注册入口将隐藏，直接访问注册页面会返回 403 错误</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="bi bi-collection"></i> 每个用户最多可创建站点数
                    </label>
                    <input type="number" name="user[max_sites_per_user]" class="form-input" 
                           value="<?= $settings['user']['max_sites_per_user'] ?? 10 ?>" 
                           min="1" max="100">
                    <small class="form-help">限制每个用户可添加的站点数量，防止滥用（1-100）</small>
                </div>
                
                <div style="text-align: center; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> 保存用户设置
                    </button>
                </div>
            </div>
        </form>
        
        <!-- 数据库统计和清理 -->
        <div class="settings-grid">
            <!-- 数据库统计 -->
            <div class="settings-section">
                <h2 class="section-title">
                    <i class="bi bi-bar-chart"></i>
                    数据库统计
                </h2>
                
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-value"><?= formatNumber($dbStats['total_records'] ?? 0) ?></div>
                        <div class="stat-label">总记录数</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?= formatNumber($dbStats['human_records'] ?? 0) ?></div>
                        <div class="stat-label">用户记录</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?= formatNumber($dbStats['bot_records'] ?? 0) ?></div>
                        <div class="stat-label">机器人记录</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?= $dbStats['database_size_mb'] ?? 0 ?> MB</div>
                        <div class="stat-label">数据库大小</div>
                    </div>
                </div>
                
                <?php if (isset($dbStats['oldest_record']) && $dbStats['oldest_record']): ?>
                    <p style="font-size: 14px; color: #64748b; margin-top: 12px;">
                        数据范围：<?= date('Y-m-d', strtotime($dbStats['oldest_record'])) ?> 
                        至 <?= date('Y-m-d', strtotime($dbStats['newest_record'] ?? $dbStats['oldest_record'])) ?>
                    </p>
                <?php endif; ?>
            </div>
            
            <!-- 数据清理与删除 -->
            <div class="settings-section">
                <h2 class="section-title">
                    <i class="bi bi-trash"></i>
                    数据清理与删除
                </h2>
                
                <p style="color: #64748b; font-size: 14px; margin-bottom: 20px;">
                    <i class="bi bi-exclamation-triangle"></i>
                    数据清理将永久删除选定的记录，用于保持数据库合理大小。此操作不可恢复！
                </p>
                
                <!-- 快捷清理选项 -->
                <div class="form-group">
                    <label class="form-label">快捷清理（删除指定时间前的所有数据）</label>
                    <div class="quick-cleanup-buttons">
                        <form method="POST" style="display: inline-block;" onsubmit="return confirm('确定要删除3个月前的所有数据吗？此操作不可恢复！')">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="cleanup_data">
                            <input type="hidden" name="quick_cleanup" value="3months">
                            <button type="submit" class="btn-quick-cleanup">
                                <i class="bi bi-calendar-minus"></i>
                                删除3个月前数据
                            </button>
                        </form>
                        <form method="POST" style="display: inline-block;" onsubmit="return confirm('确定要删除6个月前的所有数据吗？此操作不可恢复！')">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="cleanup_data">
                            <input type="hidden" name="quick_cleanup" value="6months">
                            <button type="submit" class="btn-quick-cleanup">
                                <i class="bi bi-calendar-minus"></i>
                                删除6个月前数据
                            </button>
                        </form>
                        <form method="POST" style="display: inline-block;" onsubmit="return confirm('确定要删除1年前的所有数据吗？此操作不可恢复！')">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="cleanup_data">
                            <input type="hidden" name="quick_cleanup" value="1year">
                            <button type="submit" class="btn-quick-cleanup">
                                <i class="bi bi-calendar-minus"></i>
                                删除1年前数据
                            </button>
                        </form>
                    </div>
                </div>
                
                <form method="POST" action="" onsubmit="return confirm('确定要执行数据清理操作吗？此操作不可恢复！')">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="cleanup_data">
                    
                    <!-- 自定义日期范围清理 -->
                    <div class="form-group">
                        <label class="form-label">自定义日期范围删除</label>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <input type="date" name="start_date" class="form-input" placeholder="开始日期">
                            <span>至</span>
                            <input type="date" name="end_date" class="form-input" placeholder="结束日期">
                        </div>
                    </div>
                    
                    <!-- 其他清理选项 -->
                    <div class="form-checkbox">
                        <input type="checkbox" name="cleanup_bots" id="cleanup_bots">
                        <label for="cleanup_bots">删除所有机器人/爬虫数据</label>
                    </div>
                    
                    <div class="form-checkbox">
                        <input type="checkbox" name="cleanup_expired" id="cleanup_expired">
                        <label for="cleanup_expired">删除过期数据（超过 <?= $settings['data']['retention_days'] ?? 365 ?> 天）</label>
                    </div>
                    
                    <div class="form-checkbox">
                        <input type="checkbox" name="optimize_tables" id="optimize_tables">
                        <label for="optimize_tables">优化数据库表（不删除数据）</label>
                    </div>
                    
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash"></i> 执行清理删除
                    </button>
                </form>
            </div>
        </div>
        
        <!-- 系统操作 -->
        <div style="text-align: center; margin-bottom: 32px;">
            <form method="POST" action="" style="display: inline;" onsubmit="return confirm('确定要重置所有设置为默认值吗？')">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="reset_settings">
                <button type="submit" class="btn btn-secondary">
                    <i class="bi bi-arrow-clockwise"></i> 重置为默认设置
                </button>
            </form>
        </div>
        
        <!-- 页脚 -->
        <div class="footer">
            <div class="footer-left">
                <p>&copy; 2025 <?= APP_NAME ?> v<?= APP_VERSION ?>. All rights reserved.</p>
            </div>
            <div class="footer-right">
                <p><a href="https://jesoo.org/" target="_blank" style="color: inherit; text-decoration: none;">@JesooTechLab</a></p>
            </div>
        </div>
    </div>
    
    <script>
    // 密码修改表单验证
    document.getElementById('passwordForm')?.addEventListener('submit', function(e) {
        const currentPassword = document.getElementById('current_password').value;
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        // 验证所有字段是否填写
        if (!currentPassword || !newPassword || !confirmPassword) {
            e.preventDefault();
            alert('请填写所有密码字段');
            return false;
        }
        
        // 验证新密码长度
        if (newPassword.length < 6) {
            e.preventDefault();
            alert('新密码长度至少为6位');
            document.getElementById('new_password').focus();
            return false;
        }
        
        // 验证两次输入的新密码是否一致
        if (newPassword !== confirmPassword) {
            e.preventDefault();
            alert('两次输入的新密码不一致，请重新输入');
            document.getElementById('confirm_password').value = '';
            document.getElementById('confirm_password').focus();
            return false;
        }
        
        // 验证新密码不能与当前密码相同
        if (currentPassword === newPassword) {
            e.preventDefault();
            alert('新密码不能与当前密码相同');
            document.getElementById('new_password').value = '';
            document.getElementById('confirm_password').value = '';
            document.getElementById('new_password').focus();
            return false;
        }
        
        // 二次确认
        if (!confirm('确定要修改密码吗？修改后需要重新登录。')) {
            e.preventDefault();
            return false;
        }
        
        return true;
    });
    
    // 新密码输入时显示强度提示
    document.getElementById('new_password')?.addEventListener('input', function(e) {
        const password = e.target.value;
        const strength = calculatePasswordStrength(password);
        
        // 移除之前的提示
        const existingHint = e.target.parentElement.querySelector('.password-strength-hint');
        if (existingHint) {
            existingHint.remove();
        }
        
        // 如果密码不为空，显示强度提示
        if (password) {
            const hint = document.createElement('small');
            hint.className = 'password-strength-hint';
            hint.style.display = 'block';
            hint.style.marginTop = '8px';
            hint.style.padding = '8px';
            hint.style.borderRadius = '4px';
            hint.style.fontSize = '12px';
            
            if (strength.score < 2) {
                hint.style.background = '#fee';
                hint.style.color = '#c33';
                hint.textContent = '⚠️ 密码强度：弱';
            } else if (strength.score < 3) {
                hint.style.background = '#ffc';
                hint.style.color = '#960';
                hint.textContent = '⚡ 密码强度：中等';
            } else {
                hint.style.background = '#efe';
                hint.style.color = '#390';
                hint.textContent = '✓ 密码强度：强';
            }
            
            e.target.parentElement.appendChild(hint);
        }
    });
    
    // 计算密码强度
    function calculatePasswordStrength(password) {
        let score = 0;
        
        if (password.length >= 6) score++;
        if (password.length >= 10) score++;
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
        if (/\d/.test(password)) score++;
        if (/[^a-zA-Z0-9]/.test(password)) score++;
        
        return { score: Math.min(score, 3) };
    }
    
    // 确认密码输入时实时验证
    document.getElementById('confirm_password')?.addEventListener('input', function(e) {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = e.target.value;
        
        // 移除之前的提示
        const existingHint = e.target.parentElement.querySelector('.password-match-hint');
        if (existingHint) {
            existingHint.remove();
        }
        
        // 如果确认密码不为空，显示匹配提示
        if (confirmPassword) {
            const hint = document.createElement('small');
            hint.className = 'password-match-hint';
            hint.style.display = 'block';
            hint.style.marginTop = '8px';
            hint.style.fontSize = '12px';
            
            if (newPassword === confirmPassword) {
                hint.style.color = '#390';
                hint.textContent = '✓ 两次密码输入一致';
            } else {
                hint.style.color = '#c33';
                hint.textContent = '✗ 两次密码输入不一致';
            }
            
            e.target.parentElement.appendChild(hint);
        }
    });
    </script>
</body>
</html>
