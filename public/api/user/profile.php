<?php
/**
 * 用户个人资料 API
 *
 * PUT /api/user/profile.php
 * 更新用户资料
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
    
    // 使用 UserService 更新资料
    $userService = new UserService($pdo);
    $result = $userService->updateProfile($userId, $data);
    
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
