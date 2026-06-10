<?php
/**
 * 设置查询 API
 *
 * GET /api/settings.php
 * 查询系统设置状态
 */

require_once __DIR__ . '/../app/functions.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getDbConnection();
    
    // 检查是否已安装
    if (!isInstalled()) {
        echo json_encode(['success' => false, 'message' => '系统尚未安装']);
        exit;
    }
    
    $settingsService = new SettingsService($pdo);
    
    // 检查特定设置项
    $check = $_GET['check'] ?? null;
    
    if ($check === 'registration') {
        echo json_encode([
            'success' => true,
            'allowed' => $settingsService->isRegistrationAllowed()
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => '请指定要检查的设置项'
        ]);
    }
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '服务器错误：' . $e->getMessage()]);
}
?>
