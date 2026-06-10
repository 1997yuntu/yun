<?php
/**
 * 分页数据获取API
 * 为仪表板卡片提供分页数据
 */

// 错误处理：捕获所有错误并返回JSON
error_reporting(E_ALL);
ini_set('display_errors', 0); // 不直接显示错误，避免破坏JSON格式

// 将所有错误转换为异常，便于统一处理
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// 捕获致命错误并输出JSON
register_shutdown_function(function() {
    $lastError = error_get_last();
    if ($lastError && in_array($lastError['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_length()) ob_clean();
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => '服务器致命错误: ' . $lastError['message'],
        ], JSON_UNESCAPED_UNICODE);
    }
});

// 开启输出缓冲，捕获任何意外输出
ob_start();

header('Content-Type: application/json; charset=utf-8');

// 引入核心文件（使用API专用配置，避免HTML输出）
try {
    require_once __DIR__ . '/../../app/api_config.php';
    require_once __DIR__ . '/../../src/Services/PaginationService.php';
    require_once __DIR__ . '/../../src/Services/FilterCriteria.php';
    
    // 清除可能的意外输出
    ob_clean();
} catch (Exception $e) {
    ob_clean(); // 清除缓冲区
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => '系统初始化失败: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 验证登录状态
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '未登录'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 获取请求参数 - 支持新旧两种参数格式
    $dataType = $_GET['type'] ?? $_GET['card'] ?? '';  // 新格式用type，兼容旧格式card
    $page = max(1, intval($_GET['page'] ?? 1));
    $period = $_GET['period'] ?? 'today';
    $customDate = $_GET['custom_date'] ?? '';
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    $siteKey = $_GET['site'] ?? 'default';
    $sortBy = $_GET['sort'] ?? null;  // 排序参数
    
    // 验证数据类型
    $allowedTypes = ['sources', 'pages', 'clients', 'regions', 'ips', 'bots', 'os', 'devices'];
    if (!in_array($dataType, $allowedTypes)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '无效的数据类型'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 创建筛选条件对象
    $filter = new FilterCriteria($siteKey, $period, $customDate, $startDate, $endDate);
    
    // 获取站点域名（用于排除内部链接）
    $siteDomain = null;
    if ($dataType === 'sources') {
        require_once __DIR__ . '/../../src/Services/SitesService.php';
        $sitesService = new SitesService($pdo);
        $currentSite = $sitesService->getSiteByKey($siteKey);
        $siteDomain = $currentSite['domain'] ?? null;
    }
    
    // 使用新的分页服务获取数据
    $paginationService = new PaginationService($pdo);
    $result = $paginationService->getPaginatedData($dataType, $filter, $page, $sortBy, $siteDomain);
    
    // 返回成功结果（保持兼容格式）
    echo json_encode([
        'success' => true,
        'card_type' => $dataType,  // 保持兼容字段名
        'type' => $dataType,       // 新字段名
        'data' => $result['data'],
        'pagination' => $result['pagination'],
        'filter_info' => $result['filter_info']  // 新增筛选信息
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => '服务器错误: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
