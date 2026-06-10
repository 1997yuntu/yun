<?php

require_once __DIR__ . '/DataProviderInterface.php';
require_once __DIR__ . '/UserAgentService.php';

/**
 * 机器人数据提供者
 */
class BotsDataProvider implements DataProviderInterface {
    private $pdo;
    private $userAgentService;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->userAgentService = new UserAgentService();
    }
    
    /**
     * 获取总记录数
     */
    public function getTotalCount(FilterCriteria $filter) {
        $whereData = $filter->buildWhereConditions();
        
        // 修改筛选条件以包含机器人
        $botFilter = clone $filter;
        $botFilter->setExcludeBots(false);
        $botWhereData = $botFilter->buildWhereConditions();
        
        $sql = "
            SELECT COUNT(DISTINCT user_agent) as total
            FROM pageviews 
            {$botWhereData['where']} AND is_bot = 1
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($botWhereData['params']);
        $result = $stmt->fetch();
        
        return (int)$result['total'];
    }
    
    /**
     * 获取分页数据
     * @param FilterCriteria $filter 筛选条件
     * @param int $offset 偏移量
     * @param int $limit 每页数量
     * @param string|null $sortBy 排序方式：'visits'=按访问次数，'time'=按时间，null=按访问次数（默认）
     */
    public function getPageData(FilterCriteria $filter, $offset, $limit, $sortBy = null) {
        // 修改筛选条件以包含机器人
        $botFilter = clone $filter;
        $botFilter->setExcludeBots(false);
        $botWhereData = $botFilter->buildWhereConditions();
        
        // 获取总访问数（用于计算百分比）
        $totalSql = "
            SELECT COUNT(*) as total
            FROM pageviews 
            {$botWhereData['where']}
        ";
        $totalStmt = $this->pdo->prepare($totalSql);
        $totalStmt->execute($botWhereData['params']);
        $totalVisits = $totalStmt->fetch()['total'];
        
        // 根据排序参数确定ORDER BY子句
        // 默认按访问次数排序（与前端初始状态一致）
        $orderBy = 'visits DESC, last_visit DESC';
        if ($sortBy === 'time') {
            $orderBy = 'last_visit DESC, visits DESC'; // 按时间排序
        }
        
        // 获取机器人数据（按 bot_name 和 user_agent 分组）
        $sql = "
            SELECT 
                user_agent,
                bot_name,
                COUNT(*) as visits,
                ROUND(COUNT(*) * 100.0 / {$totalVisits}, 2) as percentage,
                MAX(visit_time) as last_visit,
                COUNT(DISTINCT ip) as unique_ips,
                SUBSTRING_INDEX(GROUP_CONCAT(ip ORDER BY visit_time DESC), ',', 1) as primary_ip,
                GROUP_CONCAT(DISTINCT ip ORDER BY visit_time DESC) as all_ips
            FROM pageviews 
            {$botWhereData['where']} AND is_bot = 1
            GROUP BY user_agent, bot_name
            ORDER BY {$orderBy}
            LIMIT ? OFFSET ?
        ";
        
        $params = array_merge($botWhereData['params'], [$limit, $offset]);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();
        
        // 处理数据，添加机器人分类
        $processedResults = [];
        foreach ($results as $row) {
            $botInfo = $this->userAgentService->detectBotType($row['user_agent']);
            
            // 优先使用数据库中的 bot_name，如果为空则实时识别
            $botName = $row['bot_name'];
            if (empty($botName)) {
                $botName = $this->extractBotName($row['user_agent']);
            }
            
            // 提取机器人类型
            $botType = 'unknown';
            if ($botInfo && is_array($botInfo)) {
                $botType = $botInfo['category'] ?? 'unknown';
            }
            
            // 处理所有IP地址
            $allIps = explode(',', $row['all_ips']);
            $allIps = array_filter(array_map('trim', $allIps)); // 去除空值和空格
            
            $processedResults[] = [
                'user_agent' => $row['user_agent'],
                'ip' => $row['primary_ip'], // 使用主要IP
                'all_ips' => $allIps, // 所有IP列表
                'visits' => (int)$row['visits'],
                'percentage' => (float)$row['percentage'],
                'last_visit' => $row['last_visit'],
                'unique_ips' => (int)($row['unique_ips'] ?? 1),
                'bot_type' => $botType,
                'bot_name' => $botName
            ];
        }
        
        return $processedResults;
    }
    
    /**
     * 从User-Agent中提取机器人名称
     * 支持 300+ 种主流爬虫识别
     */
    private function extractBotName($userAgent) {
        // 使用静态变量缓存配置，避免重复加载
        static $patterns = null;
        
        if ($patterns === null) {
            $configFile = __DIR__ . '/../../app/bot_patterns.php';
            if (file_exists($configFile)) {
                $patterns = require $configFile;
            } else {
                // 如果配置文件不存在，使用基础规则
                $patterns = [
                    '/Googlebot/i' => 'Google Bot',
                    '/bingbot/i' => 'Bing Bot',
                    '/Baiduspider/i' => 'Baidu Spider',
                    '/YandexBot/i' => 'Yandex Bot',
                    '/DuckDuckBot/i' => 'DuckDuckGo Bot',
                    '/ClaudeBot/i' => 'Claude Bot',
                    '/GPTBot/i' => 'GPT Bot',
                    '/AhrefsBot/i' => 'Ahrefs Bot',
                    '/DotBot/i' => 'DotBot',
                    '/Amazonbot/i' => 'Amazon Bot',
                    '/Applebot/i' => 'Apple Bot',
                ];
            }
        }
        
        foreach ($patterns as $pattern => $name) {
            if (preg_match($pattern, $userAgent)) {
                return $name;
            }
        }
        
        // 如果没有匹配到，返回未知爬虫
        return '未知爬虫';
    }
}
?>
