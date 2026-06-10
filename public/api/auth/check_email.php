<?php
/**
 * 检查邮箱是否已注册
 *
 * POST /api/auth/check_email.php
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
    $email = trim($data['email'] ?? '');
    
    if (empty($email)) {
        echo json_encode(['exists' => false]);
        exit;
    }
    
    $userService = new UserService($pdo);
    $exists = $userService->isEmailRegistered($email);
    
    echo json_encode(['exists' => $exists]);
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => '服务器错误']);
}
?>
