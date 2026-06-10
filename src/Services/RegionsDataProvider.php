<?php
/**
 * 访问地区数据提供者
 * 处理访问地区统计的数据获取逻辑（简化版，避免复杂的字符串处理）
 */

require_once __DIR__ . '/DataProviderInterface.php';
require_once __DIR__ . '/FilterCriteria.php';
require_once __DIR__ . '/IpLocationService.php';

class RegionsDataProvider implements DataProviderInterface {
    private $pdo;
    private $ipLocationService;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->ipLocationService = new IpLocationService();
    }
    
    /**
     * 获取地区总数
     * 注意：这个方法可能比较慢，因为需要查询所有IP并解析地区
     * 在实际使用中可以考虑缓存或预计算
     */
    public function getTotalCount(FilterCriteria $filter) {
        // 获取所有唯一IP
        $whereData = $filter->buildWhereConditions();
        
        $sql = "SELECT DISTINCT ip FROM pageviews " . $whereData['where'];
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($whereData['params']);
        $ips = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // 统计唯一地区数量
        $regions = [];
        foreach ($ips as $ip) {
            $locationData = $this->ipLocationService->search($ip);
            // 组合地区信息：国家-省份-城市
            $location = $this->formatLocationString($locationData);
            $regions[$location] = true;
        }
        
        return count($regions);
    }
    
    /**
     * 获取地区分页数据
     */
    public function getPageData(FilterCriteria $filter, $offset, $limit, $sortBy = null) {
        // 获取IP访问统计
        $whereData = $filter->buildWhereConditions();
        
        $sql = "
            SELECT 
                ip,
                COUNT(*) as visits,
                MAX(visit_time) as last_visit
            FROM pageviews 
            " . $whereData['where'] . "
            GROUP BY ip
            ORDER BY visits DESC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($whereData['params']);
        $ipData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 按地区聚合数据
        $regionStats = [];
        foreach ($ipData as $row) {
            $locationData = $this->ipLocationService->search($row['ip']);
            $region = $this->formatLocationString($locationData);
            
            if (!isset($regionStats[$region])) {
                $regionStats[$region] = [
                    'region' => $region,
                    'visits' => 0,
                    'unique_ips' => 0,
                    'last_visit' => null
                ];
            }
            
            $regionStats[$region]['visits'] += $row['visits'];
            $regionStats[$region]['unique_ips']++;
            
            if (!$regionStats[$region]['last_visit'] || 
                $row['last_visit'] > $regionStats[$region]['last_visit']) {
                $regionStats[$region]['last_visit'] = $row['last_visit'];
            }
        }
        
        // 排序：首先按访问次数降序，然后按最后访问时间降序
        usort($regionStats, function($a, $b) {
            // 首先比较访问次数
            $visitsComparison = $b['visits'] - $a['visits'];
            if ($visitsComparison !== 0) {
                return $visitsComparison;
            }
            // 如果访问次数相同，则按最后访问时间降序
            return strcmp($b['last_visit'], $a['last_visit']);
        });
        
        // 计算百分比
        $totalVisits = array_sum(array_column($regionStats, 'visits'));
        foreach ($regionStats as &$region) {
            $region['percentage'] = $totalVisits > 0 ? 
                round($region['visits'] * 100.0 / $totalVisits, 2) : 0;
        }
        
        // 分页
        return array_slice($regionStats, $offset, $limit);
    }
    
    /**
     * 格式化地区信息字符串
     * @param array $locationData IP定位服务返回的数据
     * @return string 格式化的地区字符串
     */
    private function formatLocationString($locationData) {
        if (empty($locationData) || !is_array($locationData)) {
            return '未知地区';
        }
        
        $parts = [];
        
        // 添加国家
        if (!empty($locationData['country']) && $locationData['country'] !== '未知') {
            $parts[] = $locationData['country'];
        }
        
        // 添加省份
        if (!empty($locationData['province']) && $locationData['province'] !== '未知') {
            $parts[] = $locationData['province'];
        }
        
        // 添加城市
        if (!empty($locationData['city']) && $locationData['city'] !== '未知') {
            $parts[] = $locationData['city'];
        }
        
        // 如果没有有效信息，返回未知
        if (empty($parts)) {
            return '未知地区';
        }
        
        // 组合地区信息
        return implode('-', $parts);
    }
}
?>
