<?php
/**
 * API专用配置文件
 * 只加载必要的配置，不输出HTML
 */

// 定义API模式标识
define('IS_API_MODE', true);

// 检查是否已安装
$configFile = __DIR__ . '/config.php';
$lockFile = __DIR__ . '/installed.lock';
$isInstalled = file_exists($configFile) && file_exists($lockFile);

// 如果未安装，返回错误
if (!$isInstalled) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => '系统未安装，请先访问 /install/ 完成安装'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 引入主配置文件（包含数据库配置和连接）
// config.php 会自动建立 $pdo 连接
try {
    require_once $configFile;
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => '系统初始化失败: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 引入工具函数
require_once __DIR__ . '/functions.php';

// Session管理（API模式）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * 检查是否已登录
 */
function isLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * 获取管理员配置（如果全局函数未定义则定义，避免重复声明）
 */
if (!function_exists('getAdminConfig')) {
    function getAdminConfig() {
        $configFile = __DIR__ . '/admin_config.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true);
        }
        return ['username' => 'admin', 'password' => password_hash('admin123', PASSWORD_DEFAULT)];
    }
}
?>
