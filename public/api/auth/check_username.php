<?php
/**
 * 检查用户名是否可用
 *
 * POST /api/auth/check_username.php
 */

require_once __DIR__ . '/../../app/functions.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use JefCounts\Services\UserService;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => '无效的请求方法']);
    exit;
}

try {
    $pdo = getDbConnection();
    $data = json_decode(file_get_contents('php://input'), true);
    $username = trim($data['username'] ?? '');
    
    if (empty($username)) {
        echo json_encode(['exists' => false]);
        exit;
    }
    
    $userService = new UserService($pdo);
    $exists = $userService->isUsernameTaken($username);
    
    echo json_encode(['exists' => $exists]);
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => '服务器错误']);
}
?>
