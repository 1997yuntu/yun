<?php
/**
 * MVP统计系统 - 数据收集API接口
 * 功能：接收并记录访客访问数据
 * 特点：快速响应（<1ms）、自动过滤机器人
 */

// 设置响应头（快速返回，不阻塞页面加载）
header('Content-Type: application/json; charset=utf-8');

// CORS 配置（支持带凭据的跨域请求）
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
if ($origin !== '*') {
    // 如果有具体来源，返回具体来源而不是 *
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Credentials: true');
} else {
    // 无来源时使用通配符
    header('Access-Control-Allow-Origin: *');
}

header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理OPTIONS预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 引入核心文件
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/functions.php';

// 检查数据库连接
if (!$pdo) {
    http_response_code(503);
    echo json_encode(['s' => 0, 'e' => 'Database connection failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 1. 获取访客信息
    $ip = getRealIP();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    // 优先用前端传入的 document.referrer（data.ref），其次回退到服务器的 HTTP_REFERER
    $referer = '';
    
    // 2. 获取页面URL和站点标识
    $pageUrl = '';
    $siteKey = 'default';  // 默认站点
    $data = [];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // 支持JSON和表单两种格式
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            $data = json_decode(file_get_contents('php://input'), true);
            $pageUrl = $data['url'] ?? '';
            $siteKey = $data['site_key'] ?? 'default';
            $referer = $data['ref'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
        } else {
            $pageUrl = $_POST['url'] ?? '';
            $siteKey = $_POST['site_key'] ?? 'default';
            $data = $_POST;
            $referer = $data['ref'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
        }
    } else {
        $pageUrl = $_GET['url'] ?? '';
        $siteKey = $_GET['site_key'] ?? 'default';
        $data = $_GET;
        $referer = $data['ref'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    }
    
    // 如果没有提供URL，返回错误
    if (empty($pageUrl)) {
        throw new Exception('URL不能为空');
    }
    
    // 3. 数据验证和清理
    $pageUrl = filter_var($pageUrl, FILTER_SANITIZE_URL);
    if (strlen($pageUrl) > 500) {
        $pageUrl = substr($pageUrl, 0, 500);
    }
    // 清洗 referer（保留协议+主机+路径的前500字符）
    if (!empty($referer)) {
        $referer = filter_var($referer, FILTER_SANITIZE_URL);
        if (strlen($referer) > 500) {
            $referer = substr($referer, 0, 500);
        }
    }
    
    // 4. 轻量级机器人检测（保持向后兼容）
    $clientBotScore = (int)($data['bot_score'] ?? 0);
    $interactionCount = (int)($data['interactions'] ?? 0);
    
    // 后端快速检测（保持原有逻辑）
    $serverBotDetected = isBot($userAgent);
    
    // 轻量级综合判断
    $finalBotScore = $clientBotScore;
    if ($serverBotDetected) {
        $finalBotScore = max($finalBotScore, 60); // 后端检测到机器人
    }
    
    // 简单行为分析
    if ($interactionCount === 0 && $clientBotScore > 0) {
        $finalBotScore += 15; // 无交互且有可疑行为
    }
    
    // 保持原有的机器人判断逻辑
    $isBotFlag = ($finalBotScore > 40 || $serverBotDetected) ? 1 : 0;
    
    // 识别爬虫名称（如果是爬虫）
    $botName = null;
    if ($isBotFlag === 1) {
        // 使用静态变量缓存配置，避免重复加载
        static $botPatterns = null;
        if ($botPatterns === null) {
            $botPatternsFile = __DIR__ . '/../../app/bot_patterns.php';
            if (file_exists($botPatternsFile)) {
                $botPatterns = require $botPatternsFile;
            } else {
                $botPatterns = []; // 配置文件不存在时使用空数组
            }
        }
        
        // 识别爬虫名称
        if (!empty($botPatterns)) {
            foreach ($botPatterns as $pattern => $name) {
                if (preg_match($pattern, $userAgent)) {
                    $botName = $name;
                    break;
                }
            }
        }
        
        // 如果没有匹配到，设置为未知爬虫
        if ($botName === null) {
            $botName = '未知爬虫';
        }
    }
    
    // 5. 插入数据库（轻量级版本，支持多站点，包含爬虫名称）
    $sql = "INSERT INTO pageviews (site_key, ip, user_agent, page_url, referer, is_bot, bot_score, bot_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    
    $result = $stmt->execute([
        $siteKey,                    // 站点标识
        $ip,
        substr($userAgent, 0, 500),  // 限制长度
        $pageUrl,
        substr($referer, 0, 500),    // 限制长度
        $isBotFlag,
        $finalBotScore,
        $botName                     // 爬虫名称（真实用户为NULL）
    ]);
    
    // 6. 返回成功响应（极简JSON，减少带宽）
    if ($result) {
        http_response_code(200);
        echo json_encode([
            's' => 1,      // status: 1=成功
            't' => time()  // timestamp
        ], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception('数据保存失败');
    }
    
} catch (Exception $e) {
    // 错误处理（不影响网站正常显示）
    http_response_code(500);
    echo json_encode([
        's' => 0,  // status: 0=失败
        'e' => $e->getMessage()  // error message
    ], JSON_UNESCAPED_UNICODE);
}

// 7. 定期清理旧数据（1%概率执行，保持数据库精简）
if (rand(1, 100) === 1) {
    try {
        // 保留最近N天的数据
        $cleanSql = "DELETE FROM pageviews WHERE visit_time < DATE_SUB(NOW(), INTERVAL " . DATA_RETENTION_DAYS . " DAY)";
        $pdo->exec($cleanSql);
    } catch (Exception $e) {
        // 清理失败不影响主功能，静默处理
        error_log('数据清理失败: ' . $e->getMessage());
    }
}
?>
