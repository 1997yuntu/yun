<?php
/**
 * 统一分页服务类
 * 提供统一的分页数据获取接口，管理所有类型的分页逻辑
 */

require_once __DIR__ . '/FilterCriteria.php';
require_once __DIR__ . '/SettingsService.php';
require_once __DIR__ . '/SourcesDataProvider.php';
require_once __DIR__ . '/PagesDataProvider.php';
require_once __DIR__ . '/RegionsDataProvider.php';
require_once __DIR__ . '/ClientsDataProvider.php';
require_once __DIR__ . '/IpDataProvider.php';

class PaginationService {
    private $pdo;
    private $settingsService;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->settingsService = new SettingsService($pdo);
    }
    
    /**
     * 统一的分页数据获取方法
     * @param string $dataType 数据类型 (sources, pages, regions, clients, ips, bots, os, devices)
     * @param FilterCriteria $filter 筛选条件
     * @param int $page 页码
     * @param string|null $sortBy 排序字段
     * @param string|null $siteDomain 站点域名（用于排除内部链接）
     * @return array 包含data和pagination的结果
     */
    public function getPaginatedData($dataType, FilterCriteria $filter, $page = 1, $sortBy = null, $siteDomain = null) {
        // 获取数据提供者
        $provider = $this->getDataProvider($dataType, $siteDomain);
        
        // 获取每页显示数量
        $limit = $this->getPageLimit($dataType);
        
        // 计算总记录数
        $totalRecords = $provider->getTotalCount($filter);
        
        // 计算分页信息
        $pagination = $this->calculatePagination($totalRecords, $page, $limit);
        
        // 获取当前页数据（统一传递排序参数）
        $data = $provider->getPageData($filter, $pagination['offset'], $limit, $sortBy);
        
        return [
            'data' => $data,
            'pagination' => $pagination,
            'filter_info' => [
                'period' => $filter->getPeriod(),
                'site_key' => $filter->getSiteKey(),
                'date_range' => $filter->getDateRange(),
                'label' => $filter->getLabel()
            ]
        ];
    }
    
    /**
     * 计算分页信息
     */
    private function calculatePagination($totalRecords, $page, $limit) {
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
     * 获取页面显示限制
     */
    private function getPageLimit($dataType) {
        $limitMap = [
            'sources' => 'source_stats_limit',
            'pages' => 'popular_pages_limit',
            'regions' => 'region_stats_limit',
            'clients' => 'browser_stats_limit',
            'ips' => 'ip_stats_limit',
            'bots' => 'bot_stats_limit',
            'os' => 'os_stats_limit',
            'devices' => 'os_stats_limit'  // 使用相同的设置
        ];
        
        $settingKey = $limitMap[$dataType] ?? 'source_stats_limit';
        return $this->settingsService->getSetting('display', $settingKey) ?? 10;
    }
    
    /**
     * 获取数据提供者
     * @param string $dataType 数据类型
     * @param string|null $siteDomain 站点域名（用于排除内部链接）
     */
    private function getDataProvider($dataType, $siteDomain = null) {
        switch ($dataType) {
            case 'sources':
                $provider = new SourcesDataProvider($this->pdo);
                // 如果提供了站点域名，设置到provider中用于排除内部链接
                if (!empty($siteDomain)) {
                    $provider->setSiteDomain($siteDomain);
                }
                return $provider;
            case 'pages':
                return new PagesDataProvider($this->pdo);
            case 'regions':
                return new RegionsDataProvider($this->pdo);
            case 'clients':
                return new ClientsDataProvider($this->pdo);
            case 'ips':
                return new IpDataProvider($this->pdo);
            case 'bots':
                require_once __DIR__ . '/BotsDataProvider.php';
                return new BotsDataProvider($this->pdo);
            case 'os':
                require_once __DIR__ . '/OsDataProvider.php';
                return new OsDataProvider($this->pdo);
            case 'devices':
                require_once __DIR__ . '/DeviceDataProvider.php';
                return new DeviceDataProvider($this->pdo);
            default:
                throw new InvalidArgumentException("不支持的数据类型: $dataType");
        }
    }
    
    /**
     * 兼容方法：为现有的DashboardService提供兼容接口
     * 这样可以逐步迁移而不破坏现有功能
     */
    public function getCompatibleSourceStats(FilterCriteria $filter, $page = 1) {
        return $this->getPaginatedData('sources', $filter, $page);
    }
    
    public function getCompatiblePopularPages(FilterCriteria $filter, $page = 1) {
        return $this->getPaginatedData('pages', $filter, $page);
    }
    
    public function getCompatibleRegionStats(FilterCriteria $filter, $page = 1) {
        return $this->getPaginatedData('regions', $filter, $page);
    }
    
    public function getCompatibleClientStats(FilterCriteria $filter, $page = 1) {
        return $this->getPaginatedData('clients', $filter, $page);
    }
}
?>
