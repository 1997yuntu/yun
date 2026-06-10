<?php
/**
 * 数据提供者接口
 * 定义统一的数据获取方法，支持不同类型的统计数据
 */

interface DataProviderInterface {
    /**
     * 获取总记录数
     * @param FilterCriteria $filter 筛选条件
     * @return int 总记录数
     */
    public function getTotalCount(FilterCriteria $filter);
    
    /**
     * 获取分页数据
     * @param FilterCriteria $filter 筛选条件
     * @param int $offset 偏移量
     * @param int $limit 限制数量
     * @param string|null $sortBy 排序方式（可选）
     * @return array 数据数组
     */
    public function getPageData(FilterCriteria $filter, $offset, $limit, $sortBy = null);
}
?>
