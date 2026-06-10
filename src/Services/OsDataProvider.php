<?php
/**
 * 操作系统数据提供者
 * 处理操作系统统计的数据获取逻辑
 */

require_once __DIR__ . '/DataProviderInterface.php';
require_once __DIR__ . '/FilterCriteria.php';
require_once __DIR__ . '/UserAgentService.php';

class OsDataProvider implements DataProviderInterface {
    private $pdo;
    private $userAgentService;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->userAgentService = new UserAgentService();
    }
    
    /**
     * 获取操作系统总数
     */
    public function getTotalCount(FilterCriteria $filter) {
        $whereData = $filter->buildWhereConditions();
        
        // 获取所有User-Agent，然后解析统计操作系统种类
        $sql = "SELECT DISTINCT user_agent FROM pageviews " . $whereData['where'];
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($whereData['params']);
        $userAgents = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $osTypes = [];
        foreach ($userAgents as $userAgent) {
            $parsed = $this->userAgentService->parseUserAgent($userAgent);
            $osKey = $parsed['os'] . ($parsed['os_version'] ? ' ' . explode('.', $parsed['os_version'])[0] : '');
            $osTypes[$osKey] = true;
        }
        
        return count($osTypes);
    }
    
    /**
     * 获取操作系统分页数据
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
        
        // 解析并聚合操作系统数据
        $osStats = [];
        foreach ($userAgentData as $row) {
            $parsed = $this->userAgentService->parseUserAgent($row['user_agent']);
            $osKey = $parsed['os'] . ($parsed['os_version'] ? ' ' . explode('.', $parsed['os_version'])[0] : '');
            
            if (!isset($osStats[$osKey])) {
                $osStats[$osKey] = ['visits' => 0, 'percentage' => 0];
            }
            $osStats[$osKey]['visits'] += $row['visits'];
        }
        
        // 计算百分比
        foreach ($osStats as $os => &$stats) {
            $stats['percentage'] = round($stats['visits'] * 100.0 / $totalVisits, 2);
        }
        
        // 按访问次数排序
        uasort($osStats, function($a, $b) {
            return $b['visits'] - $a['visits'];
        });
        
        // 应用分页
        $osStatsArray = [];
        $index = 0;
        foreach ($osStats as $os => $stats) {
            if ($index >= $offset && count($osStatsArray) < $limit) {
                $osStatsArray[] = [
                    'os_name' => $os,
                    'visits' => $stats['visits'],
                    'percentage' => $stats['percentage']
                ];
            }
            $index++;
        }
        
        return $osStatsArray;
    }
}
?>
