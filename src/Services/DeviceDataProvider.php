<?php
/**
 * 设备类型数据提供者
 * 处理设备类型统计的数据获取逻辑
 */

require_once __DIR__ . '/DataProviderInterface.php';
require_once __DIR__ . '/FilterCriteria.php';
require_once __DIR__ . '/UserAgentService.php';

class DeviceDataProvider implements DataProviderInterface {
    private $pdo;
    private $userAgentService;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->userAgentService = new UserAgentService();
    }
    
    /**
     * 获取设备类型总数
     */
    public function getTotalCount(FilterCriteria $filter) {
        $whereData = $filter->buildWhereConditions();
        
        // 获取所有User-Agent，然后解析统计设备类型种类
        $sql = "SELECT DISTINCT user_agent FROM pageviews " . $whereData['where'];
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($whereData['params']);
        $userAgents = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $deviceTypes = [];
        foreach ($userAgents as $userAgent) {
            $parsed = $this->userAgentService->parseUserAgent($userAgent);
            $deviceKey = $parsed['device_type'];
            $deviceTypes[$deviceKey] = true;
        }
        
        return count($deviceTypes);
    }
    
    /**
     * 获取设备类型分页数据
     */
    public function getPageData(FilterCriteria $filter, $offset, $limit, $sortBy = null) {
        $whereData = $filter->buildWhereConditions();
        
        // 先获取总访问数用于计算百分比
        $totalSql = "SELECT COUNT(*) as total FROM pageviews " . $whereData['where'];
        $totalStmt = $this->pdo->prepare($totalSql);
        $totalStmt->execute($whereData['params']);
        $totalVisits = $totalStmt->fetch()['total'];
        
        // 获取所有User-Agent数据
        $sql = "
            SELECT 
                user_agent,
                COUNT(*) as visits
            FROM pageviews 
            " . $whereData['where'] . "
            GROUP BY user_agent
            ORDER BY visits DESC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($whereData['params']);
        $userAgentData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 解析并聚合设备类型数据
        $deviceStats = [];
        foreach ($userAgentData as $row) {
            $parsed = $this->userAgentService->parseUserAgent($row['user_agent']);
            $deviceKey = $parsed['device_type'];
            
            if (!isset($deviceStats[$deviceKey])) {
                $deviceStats[$deviceKey] = ['visits' => 0, 'percentage' => 0];
            }
            $deviceStats[$deviceKey]['visits'] += $row['visits'];
        }
        
        // 计算百分比
        foreach ($deviceStats as $device => &$stats) {
            $stats['percentage'] = round($stats['visits'] * 100.0 / $totalVisits, 2);
        }
        
        // 按访问次数排序
        uasort($deviceStats, function($a, $b) {
            return $b['visits'] - $a['visits'];
        });
        
        // 应用分页
        $deviceStatsArray = [];
        $index = 0;
        foreach ($deviceStats as $device => $stats) {
            if ($index >= $offset && count($deviceStatsArray) < $limit) {
                $deviceStatsArray[] = [
                    'device_type' => $device,
                    'visits' => $stats['visits'],
                    'percentage' => $stats['percentage']
                ];
            }
            $index++;
        }
        
        return $deviceStatsArray;
    }
}
?>
