<?php
/**
 * 用户登录 API
 *
 * POST /api/auth/login.php
 * 用户登录认证
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
    
    // 获取请求数据
    $data = json_decode(file_get_contents('php://input'), true);
    
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';
    $remember = isset($data['remember']) && $data['remember'] === true;
    
    // 基本验证
    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '用户名和密码不能为空']);
        exit;
    }
    
    // 使用 UserService 执行登录
    $userService = new UserService($pdo);
    $result = $userService->login($username, $password, $remember, getRealIP());
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => '登录成功',
            'user' => $result['user'],
            'redirect' => '/dashboard.php'
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => $result['message']]);
    }
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '服务器错误：' . $e->getMessage()]);
}
?>
