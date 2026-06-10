<?php
/**
 * 仪表板数据服务类
 * 负责处理仪表板的所有数据查询和统计逻辑
 */

class DashboardService {
    private $pdo;
    private $settingsService;
    private $ipLocationService;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        require_once __DIR__ . '/SettingsService.php';
        require_once __DIR__ . '/IpLocationService.php';
        $this->settingsService = new SettingsService($pdo);
        $this->ipLocationService = new IpLocationService();
    }
    
    /**
     * 计算分页信息
     */
    private function calculatePagination($totalRecords, $page = 1, $limit = 10) {
        $totalPages = max(1, ceil($totalRecords / $limit));
        $currentPage = max(1, min($page, $totalPages));
        $offset = ($currentPage - 1) * $limit;
        
        return [
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'limit' => $limit,
            'offset' => $offset,
            'has_prev' => $currentPage > 1,
            'has_next' => $currentPage < $totalPages
        ];
    }
    
    /**
     * 根据时间筛选条件生成WHERE子句（支持多站点）
     */
    public function getTimeFilterClause($period = 'today', $customDate = '', $siteKey = 'default') {
        // 添加站点过滤条件
        $siteFilter = "site_key = " . $this->pdo->quote($siteKey);
        
        switch ($period) {
            case 'yesterday':
                return [
                    'current' => "WHERE {$siteFilter} AND is_bot = 0 AND DATE(visit_time) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)",
                    'previous' => "WHERE {$siteFilter} AND is_bot = 0 AND DATE(visit_time) = DATE_SUB(CURDATE(), INTERVAL 2 DAY)",
                    'label' => "昨日"
                ];
            case 'week':
                return [
                    'current' => "WHERE {$siteFilter} AND is_bot = 0 AND visit_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
                    'previous' => "WHERE {$siteFilter} AND is_bot = 0 AND visit_time >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND visit_time < DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
                    'label' => "近7天"
                ];
            case 'month':
                return [
                    'current' => "WHERE {$siteFilter} AND is_bot = 0 AND visit_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
                    'previous' => "WHERE {$siteFilter} AND is_bot = 0 AND visit_time >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND visit_time < DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
                    'label' => "近30天"
                ];
            case 'year':
                return [
                    'current' => "WHERE {$siteFilter} AND is_bot = 0 AND visit_time >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)",
                    'previous' => "WHERE {$siteFilter} AND is_bot = 0 AND visit_time >= DATE_SUB(CURDATE(), INTERVAL 730 DAY) AND visit_time < DATE_SUB(CURDATE(), INTERVAL 365 DAY)",
                    'label' => "近365天"
                ];
            case 'custom':
                if (!empty($customDate)) {
                    return [
                        'current' => "WHERE {$siteFilter} AND is_bot = 0 AND DATE(visit_time) = '$customDate'",
                        'previous' => "WHERE {$siteFilter} AND is_bot = 0 AND DATE(visit_time) = DATE_SUB('$customDate', INTERVAL 1 DAY)",
                        'label' => $customDate
                    ];
                }
                // 如果自定义日期为空，回退到今日
                break;
            default: // today
                return [
                    'current' => "WHERE {$siteFilter} AND is_bot = 0 AND DATE(visit_time) = CURDATE()",
                    'previous' => "WHERE {$siteFilter} AND is_bot = 0 AND DATE(visit_time) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)",
                    'label' => "今日"
                ];
        }
    }
    
    /**
     * 获取核心指标统计（只统计真实用户，过滤机器人）
     */
    public function getCoreStats($whereClause, $wherePrevious) {
        // whereClause 已经包含 is_bot = 0，直接使用
        $currentFilter = $whereClause['current'] ?? '';
        $previousFilter = $whereClause['previous'] ?? '';
        
        // 确保筛选条件不为空
        if (empty($currentFilter)) {
            $currentFilter = "WHERE is_bot = 0";
        }
        if (empty($previousFilter)) {
            $previousFilter = "WHERE is_bot = 0 AND DATE(visit_time) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        }
        
        // PV统计（只统计真实用户）
        $pvCurrent = $this->pdo->query("SELECT COUNT(*) as count FROM pageviews {$currentFilter}")->fetch()['count'];
        $pvPrevious = $this->pdo->query("SELECT COUNT(*) as count FROM pageviews {$previousFilter}")->fetch()['count'];
        
        // UV统计（只统计真实用户）
        $uvCurrent = $this->pdo->query("SELECT COUNT(DISTINCT ip) as count FROM pageviews {$currentFilter}")->fetch()['count'];
        $uvPrevious = $this->pdo->query("SELECT COUNT(DISTINCT ip) as count FROM pageviews {$previousFilter}")->fetch()['count'];
        
        // IP统计（只统计真实用户）
        $ipCurrent = $this->pdo->query("SELECT COUNT(DISTINCT ip) as count FROM pageviews {$currentFilter}")->fetch()['count'];
        $ipPrevious = $this->pdo->query("SELECT COUNT(DISTINCT ip) as count FROM pageviews {$previousFilter}")->fetch()['count'];
        
        return [
            'pv' => ['current' => $pvCurrent, 'previous' => $pvPrevious],
            'uv' => ['current' => $uvCurrent, 'previous' => $uvPrevious],
            'ip' => ['current' => $ipCurrent, 'previous' => $ipPrevious]
        ];
    }
    
    
    /**
     * 获取访问来源统计（只统计真实用户）- 显示具体来源URL
     * @param array $whereClause 筛选条件
     * @param int $page 页码
     * @param string|null $siteDomain 当前站点域名（用于排除内部链接）
     */
    public function getSourceStats($whereClause, $page = 1, $siteDomain = null) {
        $limit = $this->settingsService->getSetting('display', 'source_stats_limit') ?? 10;
        $currentFilter = $whereClause['current'] ?? "WHERE is_bot = 0"; // 已经包含 is_bot = 0，不需要重复添加
        
        // 添加排除内部链接的条件
        $excludeInternalLinks = '';
        if (!empty($siteDomain)) {
            // 排除来自当前站点域名的 referer（内部跳转）
            // 使用 addslashes 转义特殊字符，防止 SQL 注入
            $escapedDomain = addslashes($siteDomain);
            $excludeInternalLinks = " AND (referer IS NULL OR referer = '' OR referer NOT LIKE '%{$escapedDomain}%')";
        }
        
        // 第一步：获取时间筛选范围内的所有来源数据（不分页）
        $allSourceData = $this->pdo->query("
            SELECT 
                COALESCE(NULLIF(referer, ''), '直接访问') as source_url,
                CASE 
                    WHEN referer IS NULL OR referer = '' THEN '直接访问'
                    WHEN referer LIKE '%google%' THEN 'Google搜索'
                    WHEN referer LIKE '%baidu%' THEN '百度搜索'
                    WHEN referer LIKE '%bing%' THEN 'Bing搜索'
                    WHEN referer LIKE '%yahoo%' THEN 'Yahoo搜索'
                    WHEN referer LIKE '%sogou%' THEN '搜狗搜索'
                    WHEN referer LIKE '%so.com%' OR referer LIKE '%360.cn%' THEN '360搜索'
                    WHEN referer LIKE '%facebook%' OR referer LIKE '%fb.%' THEN 'Facebook'
                    WHEN referer LIKE '%twitter%' OR referer LIKE '%t.co%' THEN 'Twitter'
                    WHEN referer LIKE '%weibo%' THEN '微博'
                    WHEN referer LIKE '%linkedin%' THEN 'LinkedIn'
                    WHEN referer LIKE '%instagram%' THEN 'Instagram'
                    WHEN referer LIKE '%youtube%' THEN 'YouTube'
                    WHEN referer LIKE '%reddit%' THEN 'Reddit'
                    WHEN referer LIKE '%zhihu%' THEN '知乎'
                    WHEN referer LIKE '%douban%' THEN '豆瓣'
                    WHEN referer LIKE '%juejin%' THEN '掘金'
                    WHEN referer LIKE '%csdn%' THEN 'CSDN'
                    ELSE '外部链接'
                END as source_type,
                COUNT(*) as visits,
                ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM pageviews {$currentFilter}{$excludeInternalLinks}), 2) as percentage,
                MAX(visit_time) as last_visit
            FROM pageviews 
            {$currentFilter}{$excludeInternalLinks}
            GROUP BY referer
            ORDER BY visits DESC
        ")->fetchAll();
        
        if (empty($allSourceData)) {
            return [
                'data' => [],
                'pagination' => [
                    'current_page' => 1,
                    'total_pages' => 1,
                    'total_records' => 0,
                    'limit' => $limit,
                    'offset' => 0,
                    'has_prev' => false,
                    'has_next' => false
                ]
            ];
        }
        
        // 第二步：对来源列表进行分页
        $totalSources = count($allSourceData);
        $pagination = $this->calculatePagination($totalSources, $page, $limit);
        
        // 获取当前页的来源数据
        $pageData = array_slice($allSourceData, $pagination['offset'], $limit);
        
        return [
            'data' => $pageData,
            'pagination' => $pagination
        ];
    }
    
    /**
     * 获取IP访问统计（只统计真实用户）
     */
    public function getIpStats($whereClause) {
        $limit = $this->settingsService->getSetting('display', 'ip_stats_limit') ?? 10;
        $currentFilter = $whereClause['current'] ?? "WHERE is_bot = 0"; // 已经包含 is_bot = 0
        
        return $this->pdo->query("
            SELECT 
                ip,
                COUNT(*) as visit_count,
                MAX(visit_time) as last_visit,
                SUBSTRING_INDEX(GROUP_CONCAT(
                    CASE 
                        WHEN referer IS NULL OR referer = '' THEN '直接访问'
                        WHEN referer LIKE '%google%' THEN 'Google'
                        WHEN referer LIKE '%baidu%' THEN '百度'
                        WHEN referer LIKE '%bing%' THEN 'Bing'
                        ELSE '外链'
                    END 
                    ORDER BY visit_time DESC
                ), ',', 1) as main_source
            FROM pageviews 
            {$currentFilter}
            GROUP BY ip
            ORDER BY visit_count DESC, last_visit DESC
            LIMIT $limit
        ")->fetchAll();
    }
    
    /**
     * 获取热门页面统计（只统计真实用户）
     */
    public function getPopularPages($whereClause, $page = 1) {
        $limit = $this->settingsService->getSetting('display', 'popular_pages_limit') ?? 10;
        $currentFilter = $whereClause['current'] ?? "WHERE is_bot = 0"; // 已经包含 is_bot = 0
        
        // 先获取总记录数
        $totalRecords = $this->pdo->query("
            SELECT COUNT(DISTINCT page_url) as total
            FROM pageviews 
            {$currentFilter}
        ")->fetch()['total'];
        
        // 计算分页信息
        $pagination = $this->calculatePagination($totalRecords, $page, $limit);
        
        // 获取分页数据
        $data = $this->pdo->query("
            SELECT 
                page_url,
                COUNT(*) as visits,
                ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM pageviews {$currentFilter}), 2) as percentage
            FROM pageviews 
            {$currentFilter}
            GROUP BY page_url
            ORDER BY visits DESC
            LIMIT {$limit} OFFSET {$pagination['offset']}
        ")->fetchAll();
        
        return [
            'data' => $data,
            'pagination' => $pagination
        ];
    }
    
    /**
     * 获取客户端统计数据（只统计真实用户）
     */
    public function getClientStats($whereClause, $page = 1) {
        $limit = $this->settingsService->getSetting('display', 'browser_stats_limit') ?? 10;
        $currentFilter = $whereClause['current'] ?? "WHERE is_bot = 0"; // 已经包含 is_bot = 0
        
        // 先获取总记录数
        $totalRecords = $this->pdo->query("
            SELECT COUNT(DISTINCT user_agent) as total
            FROM pageviews 
            {$currentFilter}
        ")->fetch()['total'];
        
        // 计算分页信息
        $pagination = $this->calculatePagination($totalRecords, $page, $limit);
        
        // 获取分页数据
        $rawData = $this->pdo->query("
            SELECT 
                user_agent,
                COUNT(*) as visits,
                ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM pageviews {$currentFilter}), 2) as percentage,
                MAX(visit_time) as last_visit
            FROM pageviews 
            {$currentFilter}
            GROUP BY user_agent
            ORDER BY visits DESC
            LIMIT {$limit} OFFSET {$pagination['offset']}
        ")->fetchAll();
        
        // 直接返回原始数据，让前端处理显示
        return [
            'data' => $rawData,
            'pagination' => $pagination
        ];
    }
    
    /**
     * 获取机器人统计数据（支持时间筛选）
     */
    public function getBotStats($whereClause, $siteKey = 'default') {
        // 如果传入的是旧格式（只有siteKey），为了向后兼容
        if (is_string($whereClause) && func_num_args() == 1) {
            $siteKey = $whereClause;
            $siteFilter = "site_key = " . $this->pdo->quote($siteKey);
            $botFilter = "WHERE {$siteFilter} AND is_bot = 1";
            $totalFilter = "WHERE {$siteFilter}";
            $params = [];
        } elseif ($whereClause instanceof FilterCriteria) {
            // 新格式：使用FilterCriteria对象
            $filter = clone $whereClause;
            $filter->setExcludeBots(false); // 包含机器人数据
            $whereData = $filter->buildWhereConditions();
            
            // 机器人筛选条件
            $botFilter = str_replace('WHERE ', 'WHERE ', $whereData['where']) . ' AND is_bot = 1';
            
            // 总数筛选条件（所有访问）
            $totalFilter = $whereData['where'];
            
            $params = $whereData['params'];
        } else {
            // 兼容数组格式
            $currentFilter = is_array($whereClause) ? ($whereClause['current'] ?? '') : $whereClause;
            
            // 确保$currentFilter不为空
            if (empty($currentFilter)) {
                $currentFilter = "WHERE site_key = '{$siteKey}' AND is_bot = 0";
            }
            
            // 将 is_bot = 0 改为 is_bot = 1 来获取机器人数据
            $botFilter = str_replace('is_bot = 0', 'is_bot = 1', $currentFilter);
            
            // 计算总访问数时使用原始筛选条件（包含所有访问）
            $totalFilter = str_replace('is_bot = 0', '1=1', $currentFilter);
            $params = [];
        }
        
        if (empty($params)) {
            // 非参数化查询（兼容旧格式）
            return $this->pdo->query("
                SELECT 
                    user_agent,
                    COUNT(*) as visits,
                    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM pageviews {$totalFilter}), 2) as percentage,
                    MAX(visit_time) as last_visit
                FROM pageviews 
                {$botFilter}
                GROUP BY user_agent
                ORDER BY visits DESC
                LIMIT 50
            ")->fetchAll();
        } else {
            // 参数化查询
            $sql = "
                SELECT 
                    user_agent,
                    COUNT(*) as visits,
                    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM pageviews {$totalFilter}), 2) as percentage,
                    MAX(visit_time) as last_visit
                FROM pageviews 
                {$botFilter}
                GROUP BY user_agent
                ORDER BY visits DESC
                LIMIT 50
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge($params, $params)); // 两次参数：一次给子查询，一次给主查询
            return $stmt->fetchAll();
        }
    }
    
    /**
     * 获取操作系统和设备统计（全量数据，不分页）
     */
    public function getOsAndDeviceStats($whereClause) {
        $currentFilter = $whereClause['current'] ?? "WHERE is_bot = 0";
        
        // 获取所有User-Agent数据（不分页）
        $allClientData = $this->pdo->query("
            SELECT 
                user_agent,
                COUNT(*) as visits,
                ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM pageviews {$currentFilter}), 2) as percentage
            FROM pageviews 
            {$currentFilter}
            GROUP BY user_agent
            ORDER BY visits DESC
        ")->fetchAll();
        
        return $this->parseClientStats($allClientData);
    }
    
    /**
     * 解析客户端统计数据
     */
    public function parseClientStats($clientStats) {
        // 初始化UserAgentService
        require_once __DIR__ . '/UserAgentService.php';
        $userAgentService = new UserAgentService();
        
        $browserStats = [];
        $osStats = [];
        $deviceStats = [];
        
        foreach ($clientStats as $client) {
            // 检查必要字段是否存在
            if (!isset($client['user_agent'])) {
                continue; // 跳过没有user_agent的记录
            }
            
            $parsed = $userAgentService->parseUserAgent($client['user_agent']);
            
            // 浏览器统计
            $browserKey = $parsed['browser'] . ($parsed['browser_version'] ? ' ' . explode('.', $parsed['browser_version'])[0] : '');
            if (!isset($browserStats[$browserKey])) {
                $browserStats[$browserKey] = ['visits' => 0, 'percentage' => 0, 'last_visit' => null];
            }
            $browserStats[$browserKey]['visits'] += $client['visits'];
            $browserStats[$browserKey]['percentage'] += $client['percentage'];
            
            // 更新最后访问时间
            if (isset($client['last_visit'])) {
                if (!$browserStats[$browserKey]['last_visit'] || $client['last_visit'] > $browserStats[$browserKey]['last_visit']) {
                    $browserStats[$browserKey]['last_visit'] = $client['last_visit'];
                }
            }
            
            // 操作系统统计
            $osKey = $parsed['os'] . ($parsed['os_version'] ? ' ' . explode('.', $parsed['os_version'])[0] : '');
            if (!isset($osStats[$osKey])) {
                $osStats[$osKey] = ['visits' => 0, 'percentage' => 0];
            }
            $osStats[$osKey]['visits'] += $client['visits'];
            $osStats[$osKey]['percentage'] += $client['percentage'];
            
            // 设备类型统计
            $deviceKey = $parsed['device_type'];
            if (!isset($deviceStats[$deviceKey])) {
                $deviceStats[$deviceKey] = ['visits' => 0, 'percentage' => 0];
            }
            $deviceStats[$deviceKey]['visits'] += $client['visits'];
            $deviceStats[$deviceKey]['percentage'] += $client['percentage'];
        }
        
        // 排序统计结果
        arsort($browserStats);
        arsort($osStats);
        arsort($deviceStats);
        
        return [
            'browser' => $browserStats,
            'os' => $osStats,
            'device' => $deviceStats
        ];
    }
    
    /**
     * 解析机器人统计数据
     */
    public function parseBotStats($botClientStats) {
        // 初始化UserAgentService
        require_once __DIR__ . '/UserAgentService.php';
        $userAgentService = new UserAgentService();
        
        $botStats = [];
        $totalBots = 0;
        
        foreach ($botClientStats as $client) {
            $botInfo = $userAgentService->detectBotType($client['user_agent']);
            if ($botInfo) {
                $category = $botInfo['category'];
                $name = $botInfo['name'];
                
                // 只处理我们关心的三个类别
                if (!in_array($category, ['ai', 'search', 'social'])) {
                    continue;
                }
                
                if (!isset($botStats[$category])) {
                    $botStats[$category] = ['visits' => 0, 'bots' => []];
                }
                
                if (!isset($botStats[$category]['bots'][$name])) {
                    $botStats[$category]['bots'][$name] = ['visits' => 0, 'percentage' => 0];
                }
                
                $botStats[$category]['visits'] += $client['visits'];
                $botStats[$category]['bots'][$name]['visits'] += $client['visits'];
                $botStats[$category]['bots'][$name]['percentage'] += $client['percentage'];
                $totalBots += $client['visits'];
            }
        }
        
        // 排序机器人统计
        foreach ($botStats as &$category) {
            arsort($category['bots']);
        }
        arsort($botStats);
        
        return [
            'stats' => $botStats,
            'total' => $totalBots
        ];
    }
    
    /**
     * 获取访问地区统计（只统计真实用户）
     */
    public function getRegionStats($whereClause, $page = 1) {
        // 直接将 whereClause 和分页参数交给 IpLocationService 处理，
        // 避免在这里二次拼接导致 SQL 语法问题（例如多余 AND/WHERE）
        return $this->ipLocationService->getRegionStats($this->pdo, $whereClause, $page);
    }
}
?>
