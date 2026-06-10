<?php
/**
 * 极简统计系统 - 安装向导
 * 功能：引导用户完成数据库配置和系统初始化
 */

// 防止重复安装
$configFile = __DIR__ . '/../../app/config.php';
$lockFile = __DIR__ . '/../../app/installed.lock';

// 检查是否已安装
$alreadyInstalled = file_exists($lockFile);

// 处理安装请求
$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

// Step 1: 环境检查
if ($step == 1) {
    $checks = [
        'PHP版本 >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'PDO扩展' => extension_loaded('pdo'),
        'PDO MySQL驱动' => extension_loaded('pdo_mysql'),
        'JSON扩展' => extension_loaded('json'),
        'Composer依赖' => file_exists(__DIR__ . '/../../vendor/autoload.php'),
        'app目录可写' => is_writable(__DIR__ . '/../../app'),
        'data目录可写' => is_writable(__DIR__ . '/../../data'),
    ];
    
    $allPassed = !in_array(false, $checks, true);
}

// Step 2: 数据库配置
if ($step == 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = $_POST['db_host'] ?? 'localhost';
    $dbName = $_POST['db_name'] ?? 'db_name';
    $dbUser = $_POST['db_user'] ?? 'db_name';
    $dbPass = $_POST['db_pass'] ?? 'password';
    $dbPort = $_POST['db_port'] ?? '3306';
    
    // 测试数据库连接
    try {
        $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        // 创建数据库（如果不存在）
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$dbName}`");
        
        // 执行初始化SQL
        $sqlFile = __DIR__ . '/../../database/init.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception('初始化SQL文件不存在：' . $sqlFile);
        }
        
        $sql = file_get_contents($sqlFile);
        
        // 第一步：移除所有的 /* ... */ 多行注释
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        
        // 第二步：逐行处理，保留多行语句的完整性
        $lines = explode("\n", $sql);
        $currentStatement = '';
        $statements = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // 跳过空行和纯注释行（-- 单行注释）
            if (empty($line) || substr($line, 0, 2) === '--') {
                continue;
            }
            
            // 移除行尾注释（但保留字符串中的 '--'）
            if (strpos($line, '--') !== false) {
                // 简单处理：如果 -- 前面有单引号，可能在字符串中，暂时保留
                if (preg_match('/^([^\']*(?:\'[^\']*\'[^\']*)*)--/', $line, $matches)) {
                    $line = trim($matches[1]);
                }
            }
            
            // 累积当前语句
            $currentStatement .= ' ' . $line;
            
            // 如果行以分号结尾，表示语句结束
            if (substr($line, -1) === ';') {
                $statement = trim($currentStatement);
                if (!empty($statement)) {
                    $statements[] = $statement;
                }
                $currentStatement = '';
            }
        }
        
        // 如果还有未结束的语句，也添加进去
        if (!empty(trim($currentStatement))) {
            $statements[] = trim($currentStatement);
        }
        
        // 执行每条语句
        $executedCount = 0;
        $failedStatements = [];
        
        foreach ($statements as $index => $statement) {
            // 跳过 SET 和 SELECT 等非关键语句的错误
            try {
                $pdo->exec($statement);
                $executedCount++;
            } catch (PDOException $e) {
                $stmtType = '';
                if (stripos($statement, 'CREATE TABLE') !== false) {
                    $stmtType = 'CREATE TABLE';
                } elseif (stripos($statement, 'INSERT INTO') !== false) {
                    $stmtType = 'INSERT';
                } elseif (stripos($statement, 'SELECT') !== false) {
                    $stmtType = 'SELECT';
                } elseif (stripos($statement, 'SET') !== false) {
                    $stmtType = 'SET';
                }
                
                // 如果是关键语句失败，则抛出异常
                if ($stmtType === 'CREATE TABLE' || $stmtType === 'INSERT') {
                    $failedStatements[] = [
                        'type' => $stmtType,
                        'error' => $e->getMessage(),
                        'statement' => $statement
                    ];
                    
                    throw new Exception(
                        "SQL执行失败 [{$stmtType}]: " . $e->getMessage() . 
                        "\n\n完整语句:\n" . $statement . 
                        "\n\n请检查语法或数据库权限"
                    );
                }
                // 其他语句失败（如 SELECT）可以忽略
            }
        }
        
        // 验证表是否创建成功
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('pageviews', $tables) || !in_array('sites', $tables)) {
            throw new Exception('数据表创建失败，请检查数据库权限。已创建的表: ' . implode(', ', $tables));
        }
        
        // 生成 config.php
        $configContent = <<<PHP
<?php
/**
 * 极简统计系统 - 核心配置文件
 * 
 * ⚠️ 安全提示：
 * 1. 生产环境请使用强密码
 * 2. 确保此文件不被 Git 追踪（已在 .gitignore 中）
 * 3. 文件权限建议设置为 640 或 600
 * 4. 定期更换数据库密码
 */

// =============================================
// 1. 数据库配置
// =============================================
define('DB_HOST', '{$dbHost}');
define('DB_PORT', '{$dbPort}');
define('DB_NAME', '{$dbName}');
define('DB_USER', '{$dbUser}');
define('DB_PASS', '{$dbPass}');

// 数据库连接
try {
    \$dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException \$e) {
    die('数据库连接失败: ' . \$e->getMessage());
}

// =============================================
// 2. 系统配置
// =============================================
define('APP_NAME', '极简统计');
define('APP_VERSION', '1.0.0');

// 时区设置（重要！确保与服务器时区一致）
date_default_timezone_set('Asia/Shanghai');

// Session 过期时间（秒）
define('SESSION_LIFETIME', 7200); // 2小时

// 数据保留天数（自动清理，0表示不自动清理）
define('DATA_RETENTION_DAYS', 365);

// 管理员配置文件路径
define('ADMIN_CONFIG_FILE', __DIR__ . '/admin.json');

// 设置文件路径
define('SETTINGS_FILE', __DIR__ . '/settings.json');

// 安装锁文件路径
define('INSTALL_LOCK_FILE', __DIR__ . '/installed.lock');

// =============================================
// 3. 安全配置
// =============================================
// 注意：Session 安全配置已在 public/index.php 中设置（必须在 session_start() 之前）

// 生产环境错误显示（开发时可设为1）
if (defined('PRODUCTION') && PRODUCTION === true) {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../data/error.log');
} else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

// =============================================
// 4. 性能配置
// =============================================

// OPcache建议配置（在php.ini中设置）
/*
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
*/

// =============================================
// 配置加载完成
// =============================================
?>
PHP;
        
        // 写入配置文件
        if (!file_put_contents($configFile, $configContent)) {
            throw new Exception('无法写入配置文件，请检查 app/ 目录权限');
        }
        
        // 创建安装锁文件
        file_put_contents($lockFile, date('Y-m-d H:i:s'));
        
        $success = '数据库初始化成功！';
        $step = 3;
        
    } catch (Exception $e) {
        $error = '安装失败: ' . $e->getMessage();
    }
}

// Step 3: 管理员账户设置
if ($step == 3 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_username'])) {
    $username = $_POST['admin_username'] ?? 'admin';
    $password = $_POST['admin_password'] ?? '';
    $passwordConfirm = $_POST['admin_password_confirm'] ?? '';
    
    if (empty($password) || strlen($password) < 6) {
        $error = '密码长度至少6位';
    } elseif ($password !== $passwordConfirm) {
        $error = '两次密码输入不一致';
    } else {
        // 创建管理员配置
        $adminData = [
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $adminFile = __DIR__ . '/../../app/admin.json';
        if (file_put_contents($adminFile, json_encode($adminData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            $success = '管理员账户创建成功！';
            $step = 4;
        } else {
            $error = '无法创建管理员配置文件';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>极简统计 安装向导</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            max-width: 650px;
            width: 100%;
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }
        
        .header {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            border-bottom: 3px solid #1e40af;
        }
        
        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
            font-weight: 600;
            letter-spacing: -0.5px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .steps {
            display: flex;
            justify-content: space-between;
            padding: 20px 30px;
            background: #f7f9fc;
            border-bottom: 1px solid #e1e8ed;
        }
        
        .step {
            flex: 1;
            text-align: center;
            position: relative;
        }
        
        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 15px;
            right: -50%;
            width: 100%;
            height: 2px;
            background: #ddd;
            z-index: 0;
        }
        
        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #ddd;
            color: #666;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
            position: relative;
            z-index: 1;
            margin-bottom: 5px;
        }
        
        .step.active .step-number {
            background: #2563eb;
            color: white;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        
        .step.completed .step-number {
            background: #10b981;
            color: white;
        }
        
        .step-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .content {
            padding: 30px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
            border-radius: 8px;
        }
        
        .alert-success {
            background: #f0fdf4;
            border-left: 4px solid #10b981;
            color: #065f46;
            border-radius: 8px;
        }
        
        .check-list {
            list-style: none;
        }
        
        .check-item {
            padding: 12px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .check-item:last-child {
            border-bottom: none;
        }
        
        .check-status {
            font-weight: bold;
        }
        
        .check-status.pass {
            color: #10b981;
            font-weight: 600;
        }
        
        .check-status.fail {
            color: #ef4444;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #6b7280;
            font-size: 12px;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-block;
        }
        
        .btn-primary {
            background: #2563eb;
            color: white;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.2);
        }
        
        .btn-primary:hover {
            background: #1d4ed8;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
            transform: translateY(-1px);
        }
        
        .btn-primary:disabled {
            background: #d1d5db;
            cursor: not-allowed;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);
        }
        
        .btn-success:hover {
            background: #059669;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
            transform: translateY(-1px);
        }
        
        .actions {
            margin-top: 30px;
            text-align: right;
        }
        
        .info-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #2563eb;
            padding: 18px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .info-box h3 {
            margin-bottom: 12px;
            color: #1e40af;
            font-size: 15px;
            font-weight: 600;
        }
        
        .info-box ul {
            margin-left: 20px;
            color: #475569;
            line-height: 1.8;
        }
        
        .info-box code {
            background: #e2e8f0;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 13px;
            color: #1e293b;
            font-family: 'Monaco', 'Courier New', monospace;
        }
        
        .info-box a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 500;
        }
        
        .info-box a:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #10b981;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin: 0 auto 20px;
            box-shadow: 0 4px 20px rgba(16, 185, 129, 0.3);
        }
        
        .warning-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #f59e0b;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin: 0 auto 20px;
            box-shadow: 0 4px 20px rgba(245, 158, 11, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>极简统计 安装向导</h1>
            <p>轻量级网站统计系统 - <a href="https://jesoo.org/" target="_blank" style="color: #2563eb; text-decoration: none;">@JesooTechLab</a></p>
        </div>
        
        <div class="steps">
            <div class="step <?= $step == 1 ? 'active' : ($step > 1 ? 'completed' : '') ?>">
                <div class="step-number">1</div>
                <div class="step-label">环境检查</div>
            </div>
            <div class="step <?= $step == 2 ? 'active' : ($step > 2 ? 'completed' : '') ?>">
                <div class="step-number">2</div>
                <div class="step-label">数据库配置</div>
            </div>
            <div class="step <?= $step == 3 ? 'active' : ($step > 3 ? 'completed' : '') ?>">
                <div class="step-number">3</div>
                <div class="step-label">管理员设置</div>
            </div>
            <div class="step <?= $step == 4 ? 'active' : '' ?>">
                <div class="step-number">4</div>
                <div class="step-label">完成</div>
            </div>
        </div>
        
        <div class="content">
            <?php if ($alreadyInstalled): ?>
                <!-- 已安装提示 -->
                <div style="text-align: center; padding: 40px 0;">
                    <div class="warning-icon">⚠️</div>
                    <h2 style="margin-bottom: 15px; color: #d97706; font-weight: 600;">系统已安装</h2>
                    <p style="color: #6b7280; margin-bottom: 30px;">
                        检测到系统已经完成安装，无法重复安装
                    </p>
                    
                    <div class="info-box" style="text-align: left; margin-bottom: 30px;">
                        <h3>💡 下一步操作</h3>
                        <ul>
                            <li>访问 <a href="/?page=login" style="color: #2563eb;">管理后台登录</a></li>
                            <li>如需重新安装，请删除 <code>app/installed.lock</code> 文件</li>
                            <li>如需修改配置，请编辑 <code>app/config.php</code></li>
                            <li>⚠️ 建议删除安装目录以提高安全性</li>
                        </ul>
                    </div>
                    
                    <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                        <a href="/" class="btn btn-secondary" style="text-decoration: none;">
                            返回首页
                        </a>
                        <a href="/?page=login" class="btn btn-primary" style="text-decoration: none;">
                            管理后台
                        </a>
                    </div>
                </div>
            <?php elseif ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success && !$alreadyInstalled): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <?php if (!$alreadyInstalled && $step == 1): ?>
                <h2 style="margin-bottom: 20px;">环境检查</h2>
                <ul class="check-list">
                    <?php foreach ($checks as $name => $passed): ?>
                        <li class="check-item">
                            <span><?= $name ?></span>
                            <span class="check-status <?= $passed ? 'pass' : 'fail' ?>">
                                <?= $passed ? '✓ 通过' : '✗ 失败' ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                
                <?php if (!$allPassed): ?>
                    <div class="info-box" style="margin-top: 20px;">
                        <h3>⚠️ 环境要求未满足</h3>
                        <ul>
                            <li>PHP 7.4 或更高版本</li>
                            <li>PDO 和 PDO_MySQL 扩展</li>
                            <li>执行 <code>composer install</code> 安装依赖</li>
                            <li>确保 app/ 和 data/ 目录可写</li>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="actions">
                    <button 
                        class="btn btn-primary" 
                        onclick="window.location.href='?step=2'"
                        <?= !$allPassed ? 'disabled' : '' ?>
                    >
                        下一步
                    </button>
                </div>
                
            <?php elseif (!$alreadyInstalled && $step == 2): ?>
                <h2 style="margin-bottom: 20px;">数据库配置</h2>
                <form method="POST" action="?step=2">
                    <div class="form-group">
                        <label>数据库主机</label>
                        <input type="text" name="db_host" value="localhost" required>
                        <small>通常为 localhost 或 127.0.0.1</small>
                    </div>
                    
                    <div class="form-group">
                        <label>数据库端口</label>
                        <input type="text" name="db_port" value="3306" required>
                        <small>MySQL默认端口为 3306</small>
                    </div>
                    
                    <div class="form-group">
                        <label>数据库名称</label>
                        <input type="text" name="db_name" value="jef_analytics_mvp" required>
                        <small>如果数据库不存在，将自动创建</small>
                    </div>
                    
                    <div class="form-group">
                        <label>数据库用户名</label>
                        <input type="text" name="db_user" value="root" required>
                    </div>
                    
                    <div class="form-group">
                        <label>数据库密码</label>
                        <input type="password" name="db_pass">
                        <small>如果没有密码请留空</small>
                    </div>
                    
                    <div class="actions">
                        <button type="submit" class="btn btn-primary">测试连接并初始化</button>
                    </div>
                </form>
                
            <?php elseif (!$alreadyInstalled && $step == 3): ?>
                <h2 style="margin-bottom: 20px;">创建管理员账户</h2>
                <form method="POST" action="?step=3">
                    <div class="form-group">
                        <label>管理员用户名</label>
                        <input type="text" name="admin_username" value="admin" required>
                        <small>用于登录管理后台</small>
                    </div>
                    
                    <div class="form-group">
                        <label>管理员密码</label>
                        <input type="password" name="admin_password" required minlength="6">
                        <small>密码长度至少6位，建议使用字母+数字+符号组合</small>
                    </div>
                    
                    <div class="form-group">
                        <label>确认密码</label>
                        <input type="password" name="admin_password_confirm" required minlength="6">
                    </div>
                    
                    <div class="info-box">
                        <h3>🔒 安全提示</h3>
                        <ul>
                            <li>请务必使用强密码</li>
                            <li>不要使用常见密码（如123456、password等）</li>
                            <li>建议定期更换密码</li>
                        </ul>
                    </div>
                    
                    <div class="actions">
                        <button type="submit" class="btn btn-primary">创建管理员</button>
                    </div>
                </form>
                
            <?php elseif (!$alreadyInstalled && $step == 4): ?>
                <div style="text-align: center;">
                    <div class="success-icon">✓</div>
                    <h2 style="margin-bottom: 15px; color: #10b981; font-weight: 600;">安装完成！</h2>
                    <p style="color: #6b7280; margin-bottom: 30px;">
                        极简统计系统已成功安装
                    </p>
                    
                    <div class="info-box" style="text-align: left; margin-bottom: 30px;">
                        <h3>📋 下一步操作</h3>
                        <ul>
                            <li>访问 <strong>首页</strong> 查看系统介绍</li>
                            <li>点击下方按钮 <strong>登录管理后台</strong></li>
                            <li>在"站点管理"中添加您的网站</li>
                            <li>复制统计代码到您的网站</li>
                            <li>⚠️ <strong>安全提示</strong>：安装完成后请手动删除 <code>public/install</code> 目录</li>
                        </ul>
                    </div>
                    
                    <div class="info-box" style="text-align: left;">
                        <h3>🔐 重要安全提示</h3>
                        <ul>
                            <li>配置文件已生成：<code>app/config.php</code></li>
                            <li>安装锁文件：<code>app/installed.lock</code></li>
                            <li>✅ 默认管理员：您刚才设置的用户名和密码</li>
                            <li>⚠️ <strong>必须删除安装目录</strong>：<code>rm -rf public/install</code></li>
                            <li>建议配置HTTPS和防火墙</li>
                        </ul>
                    </div>
                    
                    <div class="actions" style="text-align: center;">
                        <a href="/?page=login" class="btn btn-success" style="text-decoration: none; display: inline-block;">
                            进入管理后台
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

