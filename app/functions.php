<?php
/**
 * MVP统计系统 - 工具函数库
 */

/**
 * 获取资源文件版本号（基于文件修改时间）
 * @param string $file 文件路径（相对于public目录）
 * @return string 带版本号的文件路径
 */
function assetVersion($file) {
    $publicPath = __DIR__ . '/../public/' . ltrim($file, '/');
    
    // 如果文件存在，使用文件修改时间作为版本号
    if (file_exists($publicPath)) {
        $version = filemtime($publicPath);
        return $file . '?v=' . $version;
    }
    
    // 如果文件不存在，使用当前时间戳
    return $file . '?v=' . time();
}

/**
 * 获取访客真实IP地址
 */
function getRealIP() {
    // 优先级：Cloudflare > Nginx > Apache > 直接连接
    $headers = [
        'HTTP_CF_CONNECTING_IP',  // Cloudflare
        'HTTP_X_REAL_IP',          // Nginx
        'HTTP_X_FORWARDED_FOR',    // 代理
        'HTTP_CLIENT_IP',          // 其他代理
        'REMOTE_ADDR'              // 直接连接
    ];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            $ip = trim($ips[0]);
            
            // 验证IP格式（IPv4和IPv6）
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '未知';
}

/**
 * 解析访问来源类型
 */
function getSourceType($referer) {
    if (empty($referer)) {
        return '直接访问';
    }
    
    $referer = strtolower($referer);
    
    // 搜索引擎匹配
    $searchEngines = [
        'google'    => 'Google搜索',
        'baidu'     => '百度搜索',
        'bing'      => 'Bing搜索',
        'yahoo'     => 'Yahoo搜索',
        'sogou'     => '搜狗搜索',
        '360.cn'    => '360搜索',
        'yandex'    => 'Yandex搜索',
        'duckduckgo'=> 'DuckDuckGo',
    ];
    
    foreach ($searchEngines as $domain => $name) {
        if (strpos($referer, $domain) !== false) {
            return $name;
        }
    }
    
    // 社交媒体匹配
    $socialMedia = [
        'facebook', 'twitter', 'instagram', 'linkedin', 
        'weibo', 'wechat', 'qq.com', 'zhihu', 'douban',
        'reddit', 'pinterest', 'tumblr', 'tiktok'
    ];
    
    foreach ($socialMedia as $social) {
        if (strpos($referer, $social) !== false) {
            return '社交媒体';
        }
    }
    
    return '其他网站';
}

/**
 * 格式化数字显示（带千分位）
 */
function formatNumber($num) {
    if ($num >= 1000000) {
        return round($num / 1000000, 1) . 'M';
    } elseif ($num >= 1000) {
        return round($num / 1000, 1) . 'K';
    }
    return number_format($num);
}

/**
 * 计算百分比变化
 */
function getPercentChange($current, $previous) {
    if ($previous == 0) {
        return $current > 0 ? '+100%' : '0%';
    }
    $change = (($current - $previous) / $previous) * 100;
    $sign = $change >= 0 ? '+' : '';
    return $sign . round($change, 1) . '%';
}

/**
 * 格式化时间显示（相对时间）
 * 按照 秒 -> 分钟 -> 小时 -> 天 -> 周 -> 月 -> 年 的格式
 */
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;

    // 如果是负数（未来时间），取绝对值并显示为"刚刚"
    if ($diff < 0) {
        return '刚刚';
    }

    // 小于1分钟显示秒
    if ($diff < 60) {
        return $diff . '秒前';
    }
    // 小于1小时显示分钟
    elseif ($diff < 3600) {
        return floor($diff / 60) . '分钟前';
    }
    // 24小时内显示小时
    elseif ($diff < 86400) {
        return floor($diff / 3600) . '小时前';
    }
    // 7天内显示天数
    elseif ($diff < 604800) {
        return floor($diff / 86400) . '天前';
    }
    // 30天内显示周数
    elseif ($diff < 2592000) {
        return floor($diff / 604800) . '周前';
    }
    // 365天内显示月数
    elseif ($diff < 31536000) {
        return floor($diff / 2592000) . '月前';
    }
    // 超过1年显示年数
    else {
        return floor($diff / 31536000) . '年前';
    }
}

/**
 * 解析User-Agent字符串，提取浏览器、操作系统、设备信息
 * 使用UserAgentService进行解析
 */
function parseUserAgent($userAgent) {
    static $userAgentService = null;
    
    if ($userAgentService === null) {
        require_once __DIR__ . '/../src/Services/UserAgentService.php';
        $userAgentService = new UserAgentService();
    }
    
    return $userAgentService->parseUserAgent($userAgent);
}

/**
 * 检查是否为机器人（简单快速版本）
 * 优化：避免误判正常浏览器
 */
function isBot($userAgent) {
    if (empty($userAgent)) {
        return true;
    }
    
    $ua = strtolower($userAgent);
    
    // 排除正常浏览器（避免误判）
    $browserPatterns = ['mozilla/5.0', 'chrome/', 'safari/', 'firefox/', 'edge/', 'opera/'];
    $isBrowser = false;
    foreach ($browserPatterns as $pattern) {
        if (strpos($ua, $pattern) !== false) {
            $isBrowser = true;
            break;
        }
    }
    
    // 如果是浏览器，进一步检查是否包含明确的机器人标识
    if ($isBrowser) {
        $explicitBotKeywords = ['bot', 'crawler', 'spider', 'scraper', 'headless'];
        foreach ($explicitBotKeywords as $keyword) {
            if (strpos($ua, $keyword) !== false) {
                return true; // 明确包含机器人关键词
            }
        }
        return false; // 是浏览器且没有机器人标识
    }
    
    // 非浏览器User-Agent，检查是否为工具/爬虫
    $toolKeywords = [
        'curl', 'wget', 'python-requests', 'go-http-client', 
        'java/', 'okhttp', 'axios', 'node-fetch', 'scrapy',
        'libwww-perl', 'php/', 'ruby', 'postman', 'httpie',
        // 添加更多爬虫特征
        'bot', 'crawler', 'spider', 'scraper', 'checker',
        'reader', 'fetch', 'scan', 'monitor', 'validator',
        // 社交媒体爬虫
        'facebookexternalhit', 'twitterbot', 'linkedinbot',
        'whatsapp', 'telegram', 'slack', 'discord'
    ];
    
    foreach ($toolKeywords as $keyword) {
        if (strpos($ua, $keyword) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * 详细的机器人类型检测和分类
 * 使用UserAgentService进行检测
 */
function detectBotType($userAgent) {
    static $userAgentService = null;
    
    if ($userAgentService === null) {
        require_once __DIR__ . '/../src/Services/UserAgentService.php';
        $userAgentService = new UserAgentService();
    }
    
    return $userAgentService->detectBotType($userAgent);
}

/**
 * 安全的HTML输出
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * 生成CSRF Token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        // 立即写入 Session，确保 Token 被保存
        // 注意：这不会关闭 Session，只是强制写入
        session_commit();
        
        // 重新启动 Session（以便后续使用）
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    return $_SESSION['csrf_token'];
}

/**
 * 验证CSRF Token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * 读取管理员配置
 */
function getAdminConfig() {
    if (!defined('ADMIN_CONFIG_FILE') || !file_exists(ADMIN_CONFIG_FILE)) {
        return null;
    }
    
    $content = file_get_contents(ADMIN_CONFIG_FILE);
    if ($content === false) {
        return null;
    }
    
    $config = json_decode($content, true);
    return $config ?: null;
}

/**
 * 保存管理员配置
 */
function saveAdminConfig($username, $password) {
    if (!defined('ADMIN_CONFIG_FILE')) {
        return false;
    }
    
    $config = [
        'username' => $username,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents(ADMIN_CONFIG_FILE, $json) !== false;
}

/**
 * 修改管理员密码
 * @param string $currentPassword 当前密码
 * @param string $newPassword 新密码
 * @return array ['success' => bool, 'message' => string]
 */
function changeAdminPassword($currentPassword, $newPassword) {
    // 获取当前配置
    $adminConfig = getAdminConfig();
    if (empty($adminConfig)) {
        return ['success' => false, 'message' => '系统配置错误'];
    }
    
    // 验证当前密码
    if (!password_verify($currentPassword, $adminConfig['password'])) {
        return ['success' => false, 'message' => '当前密码不正确'];
    }
    
    // 验证新密码强度
    if (strlen($newPassword) < 6) {
        return ['success' => false, 'message' => '新密码长度至少为6位'];
    }
    
    // 不允许新旧密码相同
    if ($currentPassword === $newPassword) {
        return ['success' => false, 'message' => '新密码不能与当前密码相同'];
    }
    
    // 更新配置（保留原创建时间）
    $config = [
        'username' => $adminConfig['username'],
        'password' => password_hash($newPassword, PASSWORD_DEFAULT),
        'created_at' => $adminConfig['created_at'] ?? date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (file_put_contents(ADMIN_CONFIG_FILE, $json) !== false) {
        return ['success' => true, 'message' => '密码修改成功'];
    } else {
        return ['success' => false, 'message' => '密码保存失败，请检查文件权限'];
    }
}
?>
