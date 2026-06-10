<?php
/**
 * 访问来源数据提供者
 * 处理访问来源统计的数据获取逻辑
 */

require_once __DIR__ . '/DataProviderInterface.php';
require_once __DIR__ . '/FilterCriteria.php';

class SourcesDataProvider implements DataProviderInterface {
    private $pdo;
    private $siteDomain; // 当前站点域名，用于排除内部链接
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->siteDomain = null;
    }
    
    /**
     * 设置当前站点域名（用于排除内部链接）
     */
    public function setSiteDomain($domain) {
        $this->siteDomain = $domain;
        return $this;
    }
    
    /**
     * 获取来源总数（按referer分组）
     */
    public function getTotalCount(FilterCriteria $filter) {
        $whereData = $filter->buildWhereConditions();
        
        // 添加排除内部链接的条件
        $additionalWhere = '';
        $additionalParams = [];
        if (!empty($this->siteDomain)) {
            // 排除来自当前站点域名的 referer（内部跳转）
            $additionalWhere = " AND (referer IS NULL OR referer = '' OR referer NOT LIKE ?)";
            $additionalParams[] = '%' . $this->siteDomain . '%';
        }
        
        $sql = "SELECT COUNT(DISTINCT referer) as total FROM pageviews " . $whereData['where'] . $additionalWhere;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($whereData['params'], $additionalParams));
        
        return (int)$stmt->fetch()['total'];
    }
    
    /**
     * 获取来源分页数据
     */
    public function getPageData(FilterCriteria $filter, $offset, $limit, $sortBy = null) {
        $whereData = $filter->buildWhereConditions();
        
        // 添加排除内部链接的条件
        $additionalWhere = '';
        $additionalParams = [];
        if (!empty($this->siteDomain)) {
            // 排除来自当前站点域名的 referer（内部跳转）
            $additionalWhere = " AND (referer IS NULL OR referer = '' OR referer NOT LIKE ?)";
            $additionalParams[] = '%' . $this->siteDomain . '%';
        }
        
        // 先获取总访问数用于计算百分比（排除内部链接）
        $totalSql = "SELECT COUNT(*) as total FROM pageviews " . $whereData['where'] . $additionalWhere;
        $totalStmt = $this->pdo->prepare($totalSql);
        $totalStmt->execute(array_merge($whereData['params'], $additionalParams));
        $totalVisits = $totalStmt->fetch()['total'];
        
        // 获取分页数据
        $sql = "
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
                ROUND(COUNT(*) * 100.0 / ?, 2) as percentage,
                MAX(visit_time) as last_visit
            FROM pageviews 
            " . $whereData['where'] . $additionalWhere . "
            GROUP BY referer
            ORDER BY visits DESC, last_visit DESC
            LIMIT ? OFFSET ?
        ";
        
        $params = array_merge([$totalVisits], $whereData['params'], $additionalParams, [$limit, $offset]);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
