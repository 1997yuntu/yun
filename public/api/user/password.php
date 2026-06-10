<?php
/**
 * 用户修改密码 API
 *
 * PUT /api/user/password.php
 * 修改用户密码
 */

require_once __DIR__ . '/../../app/functions.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use JefCounts\Services\UserService;

header('Content-Type: application/json; charset=utf-8');

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit;
}

try {
    // 获取数据库连接
    $pdo = getDbConnection();
    
    // 验证登录
    session_start();
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => '未登录']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    
    // 获取请求数据
    $data = json_decode(file_get_contents('php://input'), true);
    
    $oldPassword = $data['old_password'] ?? '';
    $newPassword = $data['new_password'] ?? '';
    $confirmPassword = $data['confirm_password'] ?? '';
    
    // 验证输入
    if (empty($oldPassword)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '请输入原密码']);
        exit;
    }
    
    if (strlen($newPassword) < 8) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '新密码长度至少为 8 位']);
        exit;
    }
    
    if ($newPassword !== $confirmPassword) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '两次输入的新密码不一致']);
        exit;
    }
    
    // 使用 UserService 修改密码
    $userService = new UserService($pdo);
    $result = $userService->changePassword($userId, $oldPassword, $newPassword);
    
    if ($result['success']) {
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '服务器错误：' . $e->getMessage()]);
}
?>
