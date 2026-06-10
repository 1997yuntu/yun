<?php
/**
 * MVP统计系统 - 入口文件
 * 路由：安装向导 → 首页 → 登录 → 仪表板
 */

// =============================================
// 1. 检查是否需要安装
// =============================================
$configFile = __DIR__ . '/../app/config.php';
$lockFile = __DIR__ . '/../app/installed.lock';

// 检查是否已安装（必须同时存在配置文件和安装锁文件）
$isInstalled = file_exists($configFile) && file_exists($lockFile);

// 如果未安装，跳转到安装页面
if (!$isInstalled) {
    // 检查是否已经在安装页面
    if (strpos($_SERVER['REQUEST_URI'], '/install') === false) {
        header('Location: /install/');
        exit;
    }
    // 如果在安装页面，直接退出，不加载配置文件
    exit;
}

// =============================================
// 2. Session 配置和启动
// =============================================
// Session 安全配置（必须在 session_start() 之前）
ini_set('session.cookie_httponly', 1);    // 防止 JavaScript 访问 Cookie
ini_set('session.use_only_cookies', 1);   // 只使用 Cookie 存储 Session ID
ini_set('session.cookie_samesite', 'Lax'); // CSRF 保护
ini_set('session.gc_maxlifetime', 7200);   // Session 生命周期：2小时
ini_set('session.cookie_lifetime', 0);     // Cookie 在浏览器关闭时过期

// 启动 Session（带错误处理）
if (session_status() === PHP_SESSION_NONE) {
    if (!session_start()) {
        // Session 启动失败，尝试清理后重试
        session_write_close();
        session_start();
    }
}

// =============================================
// 3. 引入核心文件
// =============================================
// 尝试加载配置文件，如果数据库连接失败，跳转到安装页面
try {
    require_once $configFile;
    
    // 验证数据库连接是否可用
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('数据库连接对象不存在');
    }
    
    // 简单测试数据库连接
    $pdo->query("SELECT 1");
    
} catch (Exception $e) {
    // 数据库连接失败，删除配置文件和锁文件，跳转到安装页面
    @unlink($configFile);
    @unlink($lockFile);
    
    // 如果不在安装页面，跳转到安装页面
    if (strpos($_SERVER['REQUEST_URI'], '/install') === false) {
        header('Location: /install/');
        exit;
    }
    exit;
}

require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/auth.php';

// 获取页面参数
$page = $_GET['page'] ?? 'home';

// 处理登出
if (isset($_GET['logout'])) {
    logout();
    header('Location: /');
    exit;
}

// 路由处理
switch ($page) {
    case 'home':
        // 首页 - 介绍页面
        require_once __DIR__ . '/home.php';
        break;
        
    case 'login':
        // 登录页面
        if (isLoggedIn()) {
            header('Location: /?page=dashboard');
            exit;
        }
        require_once __DIR__ . '/../views/login.php';
        break;
        
    case 'dashboard':
        // 仪表板 - 需要登录
        if (!isLoggedIn()) {
            header('Location: /?page=login');
            exit;
        }
        require_once __DIR__ . '/../views/dashboard.php';
        break;

    case 'settings':
        // 设置页面 - 需要登录
        if (!isLoggedIn()) {
            header('Location: /?page=login');
            exit;
        }
        require_once __DIR__ . '/../views/settings.php';
        break;

    case 'sites':
        // 站点管理页面 - 需要登录
        if (!isLoggedIn()) {
            header('Location: /?page=login');
            exit;
        }
        require_once __DIR__ . '/../views/sites.php';
        break;

    default:
        // 默认跳转到首页
        header('Location: /');
        exit;
}
?>