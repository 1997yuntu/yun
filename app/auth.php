<?php
/**
 * MVP统计系统 - 认证系统
 * 处理用户登录、登出、会话管理
 */

/**
 * 检查用户是否已登录
 */
function isLoggedIn() {
    // 检查会话中是否有登录标记
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        // 检查会话是否过期
        if (isset($_SESSION['last_activity'])) {
            $inactive = time() - $_SESSION['last_activity'];
            if ($inactive > SESSION_LIFETIME) {
                logout();
                return false;
            }
        }
        
        // 更新最后活动时间
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    return false;
}

/**
 * 处理用户登录
 */
function handleLogin() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => '无效的请求方法'];
    }
    
    // 验证CSRF Token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        // CSRF验证失败，重新生成token（避免用户反复失败）
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return ['success' => false, 'message' => '安全验证失败，请刷新页面重试'];
    }
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // 基本验证
    if (empty($username) || empty($password)) {
        return ['success' => false, 'message' => '用户名和密码不能为空'];
    }
    
    // 获取管理员配置
    $adminConfig = getAdminConfig();
    
    if (empty($adminConfig)) {
        return ['success' => false, 'message' => '系统配置错误，请重新安装'];
    }
    
    // 验证用户名和密码
    if ($username === $adminConfig['username'] && password_verify($password, $adminConfig['password'])) {
        // 登录成功，重新生成 Session ID（防止 Session 固定攻击）
        session_regenerate_id(true);
        
        // 设置会话
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['last_activity'] = time();
        $_SESSION['login_time'] = date('Y-m-d H:i:s');
        
        // 记录登录IP
        $_SESSION['login_ip'] = getRealIP();
        
        // 强制写入 Session（确保数据立即保存）
        session_write_close();
        
        // 重新启动 Session（以便后续使用）
        session_start();
        
        return ['success' => true, 'message' => '登录成功'];
    }
    
    // 登录失败
    return ['success' => false, 'message' => '用户名或密码错误'];
}

/**
 * 用户登出
 */
function logout() {
    // 清除所有会话数据
    $_SESSION = [];
    
    // 删除会话cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // 销毁会话
    session_destroy();
}

/**
 * 获取当前登录用户信息
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'username' => $_SESSION['admin_username'] ?? '',
        'login_time' => $_SESSION['login_time'] ?? '',
        'login_ip' => $_SESSION['login_ip'] ?? ''
    ];
}
?>
