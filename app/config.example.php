<?php
/**
 * JefCounts 统计系统 - 配置文件示例
 * 安装说明：安装向导会自动生成 config.php
 */
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'jef_analytics_mvp');
define('DB_USER', 'root');
define('DB_PASS', 'your_password_here');

// 数据库连接
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    die('数据库连接失败: ' . $e->getMessage());
}

define('APP_NAME', '极简统计');
define('APP_VERSION', '1.1.4');
date_default_timezone_set('Asia/Shanghai');
define('SESSION_LIFETIME', 7200);
define('DATA_RETENTION_DAYS', 365);
define('ADMIN_CONFIG_FILE', __DIR__ . '/admin.json');
define('SETTINGS_FILE', __DIR__ . '/settings.json');
define('INSTALL_LOCK_FILE', __DIR__ . '/installed.lock');

if (defined('PRODUCTION') && PRODUCTION === true) {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../data/error.log');
} else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}
?>
