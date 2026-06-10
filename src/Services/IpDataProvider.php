<?php
/**
 * 访客IP数据提供者
 * 处理独立访客IP统计的数据获取逻辑
 */

require_once __DIR__ . '/DataProviderInterface.php';
require_once __DIR__ . '/FilterCriteria.php';
require_once __DIR__ . '/IpLocationService.php';

class IpDataProvider implements DataProviderInterface {
    private $pdo;
    private $ipLocationService;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->ipLocationService = new IpLocationService();
    }
    
    /**
     * 获取独立IP总数
     */
    public function getTotalCount(FilterCriteria $filter) {
        $whereData = $filter->buildWhereConditions();
        
        $sql = "SELECT COUNT(DISTINCT ip) as total FROM pageviews " . $whereData['where'];
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($whereData['params']);
        
        return (int)$stmt->fetch()['total'];
    }
    
    /**
     * 获取IP分页数据
     */
    public function getPageData(FilterCriteria $filter, $offset, $limit, $sortBy = null) {
        $whereData = $filter->buildWhereConditions();
        
        // 先获取总访问数用于计算百分比
        $totalSql = "SELECT COUNT(*) as total FROM pageviews " . $whereData['where'];
        $totalStmt = $this->pdo->prepare($totalSql);
        $totalStmt->execute($whereData['params']);
        $totalVisits = $totalStmt->fetch()['total'];
        
        // 获取分页数据
        $sql = "
            SELECT 
                ip,
                COUNT(*) as visits,
                ROUND(COUNT(*) * 100.0 / ?, 2) as percentage,
                MAX(visit_time) as last_visit,
                SUBSTRING_INDEX(GROUP_CONCAT(
                    CASE 
                        WHEN referer IS NULL OR referer = '' THEN '直接访问'
                        WHEN referer LIKE '%google%' THEN 'Google'
                        WHEN referer LIKE '%baidu%' THEN '百度'
                        WHEN referer LIKE '%bing%' THEN 'Bing'
                        WHEN referer LIKE '%yahoo%' THEN 'Yahoo'
                        WHEN referer LIKE '%sogou%' THEN '搜狗'
                        WHEN referer LIKE '%so.com%' OR referer LIKE '%360.cn%' THEN '360'
                        ELSE '外链'
                    END 
                    ORDER BY visit_time DESC
                ), ',', 1) as main_source
            FROM pageviews 
            " . $whereData['where'] . "
            GROUP BY ip
            ORDER BY visits DESC, last_visit DESC
            LIMIT ? OFFSET ?
        ";
        
        $params = array_merge([$totalVisits], $whereData['params'], [$limit, $offset]);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 为每个IP添加地区信息
        foreach ($results as &$row) {
            $locationData = $this->ipLocationService->search($row['ip']);
            $row['region'] = $this->formatLocationString($locationData);
        }
        
        return $results;
    }
    
    /**
     * 格式化地区字符串
     */
    private function formatLocationString($locationData) {
        if (empty($locationData)) {
            return '未知地区';
        }
        
        $parts = [];
        
        // 国家
        if (!empty($locationData['country']) && $locationData['country'] !== '0') {
            $parts[] = $locationData['country'];
        }
        
        // 省份
        if (!empty($locationData['province']) && $locationData['province'] !== '0') {
            $parts[] = $locationData['province'];
        }
        
        // 城市
        if (!empty($locationData['city']) && $locationData['city'] !== '0') {
            $parts[] = $locationData['city'];
        }
        
        return !empty($parts) ? implode(' ', $parts) : '未知地区';
    }
}
?>

