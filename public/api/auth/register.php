<?php
/**
 * 用户注册 API
 *
 * POST /api/auth/register.php
 * 注册用户新账户
 */

require_once __DIR__ . '/../../app/functions.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use JefCounts\Services\UserService;

header('Content-Type: application/json; charset=utf-8');

// 只允许 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit;
}

try {
    // 获取数据库连接
    $pdo = getDbConnection();
    
    // 检查是否已安装
    if (!isInstalled()) {
        echo json_encode(['success' => false, 'message' => '系统尚未安装']);
        exit;
    }
    
    // 检查是否允许注册
    $settingsService = new SettingsService($pdo);
    if (!$settingsService->isRegistrationAllowed()) {
        echo json_encode(['success' => false, 'message' => '当前系统关闭了用户注册']);
        exit;
    }
    
    // 获取请求数据
    $data = json_decode(file_get_contents('php://input'), true);
    
    $username = trim($data['username'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $confirmPassword = $data['confirm_password'] ?? '';
    
    // 服务端验证
    $errors = validateRegistration($username, $email, $password, $confirmPassword);
    
    if (!empty($errors)) {
        echo json_encode([
            'success' => false,
            'message' => $errors[0],
            'errors' => $errors
        ]);
        exit;
    }
    
    // 使用 UserService 执行注册
    $userService = new UserService($pdo);
    $result = $userService->register($username, $email, $password);
    
    if ($result['success']) {
        // 注册成功，自动登录
        $loginResult = $userService->login($username, $password, false, getRealIP());
        
        if ($loginResult['success']) {
            echo json_encode([
                'success' => true,
                'message' => '注册成功',
                'user' => $loginResult['user']
            ]);
        } else {
            // 注册成功但登录失败
            echo json_encode([
                'success' => true,
                'message' => '注册成功，请登录',
                'redirect' => '/login.php'
            ]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $result['message']]);
    }
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '服务器错误：' . $e->getMessage()]);
}

/**
 * 验证注册数据
 */
function validateRegistration($username, $email, $password, $confirmPassword) {
    $errors = [];
    
    // 用户名验证
    if (empty($username)) {
        $errors[] = '用户名不能为空';
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $errors[] = '用户名长度必须在 3-50 个字符之间';
    } elseif (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]+$/', $username)) {
        $errors[] = '用户名只能包含字母、数字和下划线，且必须以字母开头';
    }
    
    // 邮箱验证
    if (empty($email)) {
        $errors[] = '邮箱不能为空';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '邮箱格式不正确';
    }
    
    // 密码验证
    if (empty($password)) {
        $errors[] = '密码不能为空';
    } elseif (strlen($password) < 8) {
        $errors[] = '密码长度至少为 8 位';
    }
    
    // 确认密码验证
    if ($password !== $confirmPassword) {
        $errors[] = '两次输入的密码不一致';
    }
    
    return $errors;
}
?>
