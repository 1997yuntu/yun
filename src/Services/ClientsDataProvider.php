<?php
/**
 * 客户端数据提供者
 * 处理浏览器/客户端统计的数据获取逻辑
 */

require_once __DIR__ . '/DataProviderInterface.php';
require_once __DIR__ . '/FilterCriteria.php';

class ClientsDataProvider implements DataProviderInterface {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * 获取客户端总数（按user_agent分组）
     */
    public function getTotalCount(FilterCriteria $filter) {
        $whereData = $filter->buildWhereConditions();
        
        $sql = "SELECT COUNT(DISTINCT user_agent) as total FROM pageviews " . $whereData['where'];
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($whereData['params']);
        
        return (int)$stmt->fetch()['total'];
    }
    
    /**
     * 获取客户端分页数据
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
                user_agent,
                COUNT(*) as visits,
                ROUND(COUNT(*) * 100.0 / ?, 2) as percentage,
                MAX(visit_time) as last_visit
            FROM pageviews 
            " . $whereData['where'] . "
            GROUP BY user_agent
            ORDER BY visits DESC, last_visit DESC
            LIMIT ? OFFSET ?
        ";
        
        $params = array_merge([$totalVisits], $whereData['params'], [$limit, $offset]);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
