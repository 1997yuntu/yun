<?php
/**
 * 用户偏好API：保存默认站点
 */

// 统一JSON输出
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../app/api_config.php';
    require_once __DIR__ . '/../../src/Services/UserPreferenceService.php';
    require_once __DIR__ . '/../../src/Services/SitesService.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => '系统初始化失败']);
    exit;
}

// 权限校验
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '未登录']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => '仅支持POST']);
    exit;
}

$siteId = isset($_POST['site_id']) ? (int)$_POST['site_id'] : 0;
if ($siteId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => '无效的站点ID']);
    exit;
}

$username = $_SESSION['admin_username'] ?? '';
if ($username === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => '无法识别用户']);
    exit;
}

// 校验站点是否存在
$sitesService = new SitesService($pdo);
$site = $sitesService->getSiteById($siteId);
if (!$site) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => '站点不存在']);
    exit;
}

// 保存偏好
$prefService = new UserPreferenceService($pdo);
$ok = $prefService->setDefaultSiteId($username, $siteId);

echo json_encode(['success' => (bool)$ok]);
?>


