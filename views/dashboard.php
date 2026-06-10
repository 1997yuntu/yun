<?php
/**
 * 仪表板视图
 * 显示统计数据
 */

/**
 * 生成智能分页控件HTML
 */
function renderPagination($pagination, $cardType) {
    if ($pagination['total_pages'] <= 1) {
        return ''; // 只有一页时不显示分页
    }
    
    $current = $pagination['current_page'];
    $total = $pagination['total_pages'];
    $html = '<div class="card-pagination" data-card="' . $cardType . '">';
    
    // 上一页按钮
    if ($pagination['has_prev']) {
        $html .= '<button class="page-btn page-prev" data-page="' . ($current - 1) . '">‹</button>';
    }
    
    // 页码显示逻辑
    $html .= '<div class="page-numbers">';
    
    if ($total <= 7) {
        // 总页数少于等于7页，显示所有页码
        for ($i = 1; $i <= $total; $i++) {
            $active = $i == $current ? ' active' : '';
            $html .= '<button class="page-btn page-num' . $active . '" data-page="' . $i . '">' . $i . '</button>';
        }
    } else {
        // 智能显示页码
        // 始终显示第1页
        $active = $current == 1 ? ' active' : '';
        $html .= '<button class="page-btn page-num' . $active . '" data-page="1">1</button>';
        
        if ($current > 4) {
            $html .= '<span class="page-dots">...</span>';
        }
        
        // 显示当前页附近的页码
        $start = max(2, $current - 1);
        $end = min($total - 1, $current + 1);
        
        for ($i = $start; $i <= $end; $i++) {
            if ($i > 1 && $i < $total) {
                $active = $i == $current ? ' active' : '';
                $html .= '<button class="page-btn page-num' . $active . '" data-page="' . $i . '">' . $i . '</button>';
            }
        }
        
        if ($current < $total - 3) {
            $html .= '<span class="page-dots">...</span>';
        }
        
        // 始终显示最后一页
        if ($total > 1) {
            $active = $current == $total ? ' active' : '';
            $html .= '<button class="page-btn page-num' . $active . '" data-page="' . $total . '">' . $total . '</button>';
        }
    }
    
    $html .= '</div>';
    
    // 下一页按钮
    if ($pagination['has_next']) {
        $html .= '<button class="page-btn page-next" data-page="' . ($current + 1) . '">›</button>';
    }
    
    // 页码信息
    $html .= '<div class="page-info">' . $current . '/' . $total . '</div>';
    
    $html .= '</div>';
    return $html;
}

/**
 * 获取机器人类型图标
 */
function getBotIcon($botType) {
    $icons = [
        'ai' => 'bi-cpu',
        'search' => 'bi-search',
        'social' => 'bi-share',
        'seo' => 'bi-graph-up',
        'tool' => 'bi-tools',
        'unknown' => 'bi-question-circle'
    ];
    return $icons[$botType] ?? 'bi-robot';
}

/**
 * 获取机器人类型名称
 */
function getBotTypeName($botType) {
    $names = [
        'ai' => 'AI爬虫',
        'search' => '搜索引擎',
        'social' => '社交媒体',
        'seo' => 'SEO工具',
        'tool' => '开发工具',
        'unknown' => '未知爬虫'
    ];
    return $names[$botType] ?? '未知';
}

/**
 * 格式化日期时间
 */
function formatDateTime($datetime) {
    if (empty($datetime)) {
        return '-';
    }
    
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return '-';
    }
    
    $now = time();
    $diff = $now - $timestamp;
    
    // 如果是今天，显示时间
    if (date('Y-m-d', $timestamp) === date('Y-m-d', $now)) {
        return date('H:i', $timestamp);
    }
    
    // 如果是昨天
    if (date('Y-m-d', $timestamp) === date('Y-m-d', $now - 86400)) {
        return '昨天 ' . date('H:i', $timestamp);
    }
    
    // 如果是本年，显示月-日
    if (date('Y', $timestamp) === date('Y', $now)) {
        return date('m-d H:i', $timestamp);
    }
    
    // 其他情况显示完整日期
    return date('Y-m-d H:i', $timestamp);
}

/**
 * 安全的字符串截取（兼容无 mbstring 扩展的环境）
 * @param string $str 要截取的字符串
 * @param int $start 起始位置
 * @param int|null $length 截取长度
 * @return string 截取后的字符串
 */
function safeSubstr($str, $start, $length = null) {
    // 优先使用 mb_substr（支持多字节字符）
    if (function_exists('mb_substr')) {
        return $length === null ? mb_substr($str, $start) : mb_substr($str, $start, $length);
    }
    // 降级使用 substr（可能对中文等多字节字符处理不完美，但不会报错）
    return $length === null ? substr($str, $start) : substr($str, $start, $length);
}

/**
 * 安全的字符串长度获取（兼容无 mbstring 扩展的环境）
 * @param string $str 要计算长度的字符串
 * @return int 字符串长度
 */
function safeStrlen($str) {
    // 优先使用 mb_strlen（支持多字节字符）
    if (function_exists('mb_strlen')) {
        return mb_strlen($str);
    }
    // 降级使用 strlen（可能对中文等多字节字符计算不准确，但不会报错）
    return strlen($str);
}

// 引入服务类
require_once __DIR__ . '/../src/Services/DashboardService.php';
require_once __DIR__ . '/../src/Services/UserAgentService.php';
require_once __DIR__ . '/../src/Services/SettingsService.php';
require_once __DIR__ . '/../src/Services/SitesService.php';
require_once __DIR__ . '/../src/Services/FilterCriteria.php';
require_once __DIR__ . '/../src/Services/UserPreferenceService.php';

// 初始化服务
$dashboardService = new DashboardService($pdo);
$userAgentService = new UserAgentService();
$settingsService = new SettingsService($pdo);
$sitesService = new SitesService($pdo);
$prefService = new UserPreferenceService($pdo);

// 获取当前站点
$currentSiteKey = $_GET['site'] ?? '';
if ($currentSiteKey === '') {
    // 若URL未指定站点，则尝试读取用户偏好（数据库）
    $currentUser = getCurrentUser();
    if ($currentUser && !empty($currentUser['username'])) {
        $defaultSiteId = $prefService->getDefaultSiteId($currentUser['username']);
        if ($defaultSiteId) {
            $siteById = $sitesService->getSiteById($defaultSiteId);
            if ($siteById && !empty($siteById['site_key'])) {
                $currentSiteKey = $siteById['site_key'];
            }
        }
    }
    // 兜底
    if ($currentSiteKey === '') {
        $currentSiteKey = 'default';
    }
}
$currentSite = $sitesService->getSiteByKey($currentSiteKey);
if (!$currentSite) {
    $currentSiteKey = 'default';
    $currentSite = $sitesService->getSiteByKey('default');
}

// 获取所有站点（用于下拉选择）
$allSites = $sitesService->getAllSites();

// 获取时间筛选参数
$period = $_GET['period'] ?? 'today';
$customDate = $_GET['custom_date'] ?? $_GET['date'] ?? ''; // 兼容两种参数名
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

// 使用新的FilterCriteria类
$filter = new FilterCriteria($currentSiteKey, $period, $customDate, $startDate, $endDate);
$periodLabel = $filter->getLabel();

// 兼容旧的whereClause格式（用于还未迁移的代码）
$whereClause = $filter->getLegacyFormat();

// 初始化分页服务和筛选条件（提前初始化，供后续统计使用）
require_once __DIR__ . '/../src/Services/PaginationService.php';
require_once __DIR__ . '/../src/Services/FilterCriteria.php';
$filter = new FilterCriteria($currentSiteKey, $period, $customDate);
$paginationService = new PaginationService($pdo);

// 1. 核心指标统计
$coreStats = $dashboardService->getCoreStats($whereClause, $whereClause);
$pvToday = $coreStats['pv']['current'];
$pvYesterday = $coreStats['pv']['previous'];
$uvToday = $coreStats['uv']['current'];
$uvYesterday = $coreStats['uv']['previous'];
$totalIPs = $coreStats['ip']['current'];
$totalIPsYesterday = $coreStats['ip']['previous'];

// 2. 来源统计（第一页）- 传入站点域名以排除内部链接
$sourcesResult = $dashboardService->getSourceStats($whereClause, 1, $currentSite['domain'] ?? null);
$sources = $sourcesResult['data'];
$sourcesPagination = $sourcesResult['pagination'];

// 3. IP访问统计（新的分页架构）
$ipsResult = $paginationService->getPaginatedData('ips', $filter, 1);
$ipStats = $ipsResult['data'];
$ipsPagination = $ipsResult['pagination'];

// 4. 热门页面统计（第一页）
$pagesResult = $dashboardService->getPopularPages($whereClause, 1);
$popularPages = $pagesResult['data'];
$pagesPagination = $pagesResult['pagination'];

// 5. 访问地区统计（第一页）
$regionResult = $dashboardService->getRegionStats($whereClause, 1);
$regionStats = $regionResult['data'];
$regionsPagination = $regionResult['pagination'];

// 6. 客户端终端统计（第一页）
$clientResult = $dashboardService->getClientStats($whereClause, 1);
$clientStats = $clientResult['data'];
$clientsPagination = $clientResult['pagination'];

// 6.1 操作系统统计（分页数据）
$osResult = $paginationService->getPaginatedData('os', $filter, 1);
$osPagination = $osResult['pagination'];

// 6.2 设备类型统计（分页数据）
$deviceResult = $paginationService->getPaginatedData('devices', $filter, 1);
$devicePagination = $deviceResult['pagination'];

// 7. 机器人/爬虫统计（支持时间筛选）
$botClientStats = $dashboardService->getBotStats($whereClause, $currentSiteKey);
$botParsed = $dashboardService->parseBotStats($botClientStats);
$botStats = $botParsed['stats'];
$totalBots = $botParsed['total'];

// 8. 机器人分页数据（新的分页架构）
$botsResult = $paginationService->getPaginatedData('bots', $filter, 1);
$botsPagination = $botsResult['pagination'];

$sourceCount = count($sources);
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?></title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="stylesheet" href="<?= assetVersion('assets/css/dashboard.css') ?>">
</head>
<body>
    <div class="container">
        <!-- 头部 -->
        <div class="header">
            <div class="header-left">
                <h1><img src="/assets/images/logo.svg" alt="Logo" style="width: 28px; height: 28px; vertical-align: middle; margin-right: 8px;"><?= APP_NAME ?></h1>
                <div class="site-info">
                    <?php if (count($allSites) > 1): ?>
                        <div class="site-selector">
                            <span class="site-label">站点:</span>
                            <select id="siteSelect" onchange="changeSite(this)">
                                <?php foreach ($allSites as $site): ?>
                                    <option value="<?= e($site['site_key']) ?>" data-site-id="<?= (int)$site['id'] ?>" <?= $site['site_key'] === $currentSiteKey ? 'selected' : '' ?>>
                                        <?= e($site['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <div class="current-site">
                            <span class="site-label">站点:</span>
                            <span class="site-name"><?= e($allSites[0]['name'] ?? '默认站点') ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="header-right">
                <a href="/?page=sites&from_site=<?= e($currentSiteKey) ?>" class="btn-nav" title="站点管理">
                    <i class="bi bi-globe"></i>
                    <span class="btn-text">站点</span>
                </a>
                <a href="/?page=settings&site=<?= e($currentSiteKey) ?>" class="btn-nav" title="设置">
                    <i class="bi bi-gear"></i>
                    <span class="btn-text">设置</span>
                </a>

                <a href="/?logout=1" class="btn-logout" title="退出登录">
                    <i class="bi bi-box-arrow-right"></i>
                    <span class="btn-text">退出</span>
                </a>
            </div>
        </div>
        
        <!-- 时间筛选器 -->
        <div class="time-filter">
            <div class="filter-buttons">
                <a href="?page=dashboard&site=<?= e($currentSiteKey) ?>&period=today" class="filter-btn <?= $period === 'today' ? 'active' : '' ?>">今日</a>
                <a href="?page=dashboard&site=<?= e($currentSiteKey) ?>&period=yesterday" class="filter-btn <?= $period === 'yesterday' ? 'active' : '' ?>">昨日</a>
                <a href="?page=dashboard&site=<?= e($currentSiteKey) ?>&period=week" class="filter-btn <?= $period === 'week' ? 'active' : '' ?>">近7天</a>
                <a href="?page=dashboard&site=<?= e($currentSiteKey) ?>&period=month" class="filter-btn <?= $period === 'month' ? 'active' : '' ?>">近30天</a>
                <a href="?page=dashboard&site=<?= e($currentSiteKey) ?>&period=year" class="filter-btn <?= $period === 'year' ? 'active' : '' ?>">近1年</a>
                
                <!-- 自定义日期区间选择器 -->
                <div class="custom-date-dropdown">
                    <button class="filter-btn custom-btn <?= in_array($period, ['custom', 'range']) ? 'active' : '' ?>" id="customDateBtn">
                        <i class="bi bi-calendar3"></i> 自定义 <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="dropdown-panel" id="dateRangePanel">
                        <div class="panel-header">
                            <h4>选择日期范围</h4>
                            <button class="close-btn" id="closePanelBtn"><i class="bi bi-x"></i></button>
                        </div>
                        <div class="date-inputs">
                            <div class="input-group">
                                <label for="startDate">开始日期</label>
                                <input type="date" id="startDate" class="date-input">
                            </div>
                            <div class="input-group">
                                <label for="endDate">结束日期</label>
                                <input type="date" id="endDate" class="date-input">
                            </div>
                        </div>
                        <div class="quick-select">
                            <div class="quick-select-label">快捷选择:</div>
                            <div class="quick-buttons">
                                <button class="quick-btn" data-range="thisWeek">本周</button>
                                <button class="quick-btn" data-range="thisMonth">本月</button>
                                <button class="quick-btn" data-range="lastMonth">上月</button>
                                <button class="quick-btn" data-range="thisQuarter">本季度</button>
                            </div>
                        </div>
                        <div class="panel-actions">
                            <button class="btn-cancel" id="cancelBtn">取消</button>
                            <button class="btn-confirm" id="confirmBtn">确定</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="current-period">
                <span class="period-label">当前: <?= $periodLabel ?></span>
            </div>
        </div>
        
        <!-- 核心指标卡片 -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon pv-icon">
                    <i class="bi bi-eye"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">PV</div>
                    <div class="stat-main">
                        <div class="stat-value"><?= formatNumber($pvToday) ?></div>
                        <div class="stat-change <?= $pvToday > $pvYesterday ? 'positive' : ($pvToday < $pvYesterday ? 'negative' : 'neutral') ?>">
                            <?= getPercentChange($pvToday, $pvYesterday) ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon uv-icon">
                    <i class="bi bi-people"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">UV</div>
                    <div class="stat-main">
                        <div class="stat-value"><?= formatNumber($uvToday) ?></div>
                        <div class="stat-change <?= $uvToday > $uvYesterday ? 'positive' : ($uvToday < $uvYesterday ? 'negative' : 'neutral') ?>">
                            <?= getPercentChange($uvToday, $uvYesterday) ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon ip-icon">
                    <i class="bi bi-geo-alt"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">总IP数</div>
                    <div class="stat-main">
                        <div class="stat-value"><?= formatNumber($totalIPs) ?></div>
                        <div class="stat-change <?= $totalIPs > $totalIPsYesterday ? 'positive' : ($totalIPs < $totalIPsYesterday ? 'negative' : 'neutral') ?>">
                            <?= getPercentChange($totalIPs, $totalIPsYesterday) ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon source-icon">
                    <i class="bi bi-link-45deg"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">来源类型</div>
                    <div class="stat-main">
                        <div class="stat-value"><?= $sourceCount ?></div>
                        <div class="stat-change neutral">
                            种渠道
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 访问来源统计 -->
        <div class="card">
            <div class="card-title">访问来源统计</div>
            <?php if (empty($sources)): ?>
                <div class="no-data">暂无访问数据</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>来源页面</th>
                            <th>类型</th>
                            <th>访问次数</th>
                            <th>占比</th>
                            <th>最后访问</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sources as $source): ?>
                            <tr>
                                <td class="source-url">
                                    <?php if ($source['source_url'] === '直接访问'): ?>
                                        <span class="direct-visit">直接访问</span>
                                    <?php else: ?>
                                        <a href="<?= e($source['source_url']) ?>" target="_blank" rel="noopener" title="<?= e($source['source_url']) ?>">
                                            <?= e(parse_url($source['source_url'], PHP_URL_HOST) . parse_url($source['source_url'], PHP_URL_PATH)) ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="source-type-badge"><?= e($source['source_type']) ?></span>
                                </td>
                                <td><?= formatNumber($source['visits']) ?></td>
                                <td><?= $source['percentage'] ?>%</td>
                                <td><?= timeAgo($source['last_visit']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?= renderPagination($sourcesPagination, 'sources') ?>
            <?php endif; ?>
        </div>
        
        <!-- 访问地区统计 -->
        <div class="card">
            <div class="card-title">访问地区</div>
            <?php if (empty($regionStats)): ?>
                <div class="no-data">暂无访问数据</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>地区</th>
                            <th>访问次数</th>
                            <th>独立IP</th>
                            <th>占比</th>
                            <th>最后访问</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($regionStats as $region): ?>
                            <tr>
                                <td><?= e($region['region']) ?></td>
                                <td><?= formatNumber($region['visits']) ?></td>
                                <td><?= formatNumber($region['unique_ips']) ?></td>
                                <td><?= $region['percentage'] ?>%</td>
                                <td><?= timeAgo($region['last_visit']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?= renderPagination($regionsPagination, 'regions') ?>
            <?php endif; ?>
        </div>
        
        <!-- 访客IP统计 -->
        <div class="card">
            <div class="card-title">访客IP统计</div>
            <?php if (empty($ipStats)): ?>
                <div class="no-data">暂无访问数据</div>
            <?php else: ?>
                <table class="ip-stats-table">
                    <thead>
                        <tr>
                            <th>IP地址</th>
                            <th>地区</th>
                            <th>访问次数</th>
                            <th>最后访问时间</th>
                            <th>主要来源</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ipStats as $ip): ?>
                            <tr>
                                <td><?= e($ip['ip']) ?></td>
                                <td><?= e($ip['region'] ?? '未知地区') ?></td>
                                <td><?= formatNumber($ip['visits']) ?></td>
                                <td><?= formatDateTime($ip['last_visit']) ?></td>
                                <td><?= e($ip['main_source']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- 分页控件 -->
                <?= renderPagination($ipsPagination, 'ips') ?>
            <?php endif; ?>
        </div>
        
        <!-- 热门页面统计 -->
        <div class="card">
            <div class="card-title">热门页面</div>
            <?php if (empty($popularPages)): ?>
                <div class="no-data">暂无访问数据</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>页面路径</th>
                            <th>访问次数</th>
                            <th>占比</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($popularPages as $page): ?>
                            <tr>
                                <td class="page-url-cell">
                                    <a href="<?= e($page['page_url']) ?>" target="_blank" rel="noopener" title="<?= e($page['page_url']) ?>">
                                        <?php 
                                        // 提取并显示简化的URL（域名+路径）
                                        $parsedUrl = parse_url($page['page_url']);
                                        $displayUrl = '';
                                        if (isset($parsedUrl['host'])) {
                                            $displayUrl = $parsedUrl['host'];
                                            if (isset($parsedUrl['path'])) {
                                                $displayUrl .= $parsedUrl['path'];
                                            }
                                        } else {
                                            // 如果无法解析（可能是相对路径），直接显示原URL
                                            $displayUrl = $page['page_url'];
                                        }
                                        echo e($displayUrl);
                                        ?>
                                    </a>
                                </td>
                                <td><?= formatNumber($page['visits']) ?></td>
                                <td><?= $page['percentage'] ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?= renderPagination($pagesPagination, 'pages') ?>
            <?php endif; ?>
        </div>
        
        <!-- 客户端终端统计 -->
        <div class="client-stats-grid">
            <!-- 浏览器统计 -->
            <div class="card">
                <div class="card-title">浏览器统计</div>
                <?php if (empty($clientResult['data'])): ?>
                    <div class="no-data">暂无数据</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>浏览器</th>
                                <th>访问次数</th>
                                <th>占比</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // 使用分页数据并解析浏览器信息
                            foreach ($clientResult['data'] as $client): 
                                $parsedUA = $userAgentService->parseUserAgent($client['user_agent']);
                                $browserName = $parsedUA['browser'] ?? 'Unknown';
                                if (!empty($parsedUA['browser_version'])) {
                                    $browserName .= ' ' . explode('.', $parsedUA['browser_version'])[0];
                                }
                            ?>
                                <tr>
                                    <td><?= e($browserName) ?></td>
                                    <td><?= formatNumber($client['visits']) ?></td>
                                    <td><?= $client['percentage'] ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?= renderPagination($clientsPagination, 'clients') ?>
                <?php endif; ?>
            </div>
            
            <!-- 操作系统统计 -->
            <div class="card">
                <div class="card-title">操作系统统计</div>
                <?php if (empty($osResult['data'])): ?>
                    <div class="no-data">暂无数据</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>操作系统</th>
                                <th>访问次数</th>
                                <th>占比</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($osResult['data'] as $os): ?>
                                <tr>
                                    <td><?= e($os['os_name']) ?></td>
                                    <td><?= formatNumber($os['visits']) ?></td>
                                    <td><?= $os['percentage'] ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?= renderPagination($osPagination, 'os') ?>
                <?php endif; ?>
            </div>
            
            <!-- 设备类型统计 -->
            <div class="card">
                <div class="card-title">设备类型统计</div>
                <?php if (empty($deviceResult['data'])): ?>
                    <div class="no-data">暂无数据</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>设备类型</th>
                                <th>访问次数</th>
                                <th>占比</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deviceResult['data'] as $device): ?>
                                <tr>
                                    <td>
                                        <?php if ($device['device_type'] === 'Mobile'): ?>
                                            <i class="bi bi-phone"></i> 手机
                                        <?php elseif ($device['device_type'] === 'Tablet'): ?>
                                            <i class="bi bi-tablet"></i> 平板
                                        <?php else: ?>
                                            <i class="bi bi-laptop"></i> 桌面
                                        <?php endif; ?>
                                    </td>
                                    <td><?= formatNumber($device['visits']) ?></td>
                                    <td><?= $device['percentage'] ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?= renderPagination($devicePagination, 'devices') ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 机器人/AI爬虫统计 -->
        <div class="card">
            <div class="card-title">机器人/AI爬虫统计</div>
            <?php if (!empty($botsResult['data'])): ?>
                <table class="stats-table bot-stats-table">
                    <thead>
                        <tr>
                            <th>机器人名称</th>
                            <th>IP地址</th>
                            <th>User-Agent</th>
                            <th>类型</th>
                            <th class="sortable-header">
                                <span>访问次数</span>
                                <button class="sort-btn" id="sortVisitsBtn" title="点击恢复时间排序">
                                    <i class="bi bi-sort-numeric-down sort-icon active"></i>
                                </button>
                            </th>
                            <th>占比</th>
                            <th>最后访问</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($botsResult['data'] as $bot): ?>
                            <tr>
                                <td class="bot-name">
                                    <i class="bi <?= getBotIcon($bot['bot_type']) ?>"></i>
                                    <?= e($bot['bot_name']) ?>
                                </td>
                                <td class="bot-ip">
                                    <div class="ip-container">
                                        <span class="ip-text" title="点击复制IP地址"><?= e($bot['ip']) ?></span>
                                        <?php if ($bot['unique_ips'] > 1): ?>
                                            <span class="ip-count expandable" 
                                                  title="点击查看所有 <?= $bot['unique_ips'] ?> 个IP地址"
                                                  onclick="toggleIpList(this)"
                                                  data-all-ips="<?= e(json_encode($bot['all_ips'])) ?>">
                                                +<?= $bot['unique_ips'] - 1 ?>
                                            </span>
                                        <?php endif; ?>
                                        <button class="copy-btn copy-ip" data-copy="<?= e($bot['ip']) ?>" title="复制主要IP地址">
                                            <i class="bi bi-copy"></i>
                                        </button>
                                    </div>
                                    <?php if ($bot['unique_ips'] > 1): ?>
                                        <div class="ip-list" style="display: none;">
                                            <div class="ip-list-header">所有IP地址：</div>
                                            <div class="ip-list-content">
                                                <?php foreach ($bot['all_ips'] as $index => $ip): ?>
                                                    <div class="ip-item">
                                                        <span class="ip-text secondary" title="点击复制"><?= e($ip) ?></span>
                                                        <?php if ($index === 0): ?>
                                                            <span class="ip-badge primary">主要</span>
                                                        <?php endif; ?>
                                                        <button class="copy-btn copy-ip-small" data-copy="<?= e($ip) ?>" title="复制此IP">
                                                            <i class="bi bi-copy"></i>
                                                        </button>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="user-agent-cell">
                                    <div class="ua-container">
                                        <span class="ua-preview" title="<?= e($bot['user_agent']) ?>">
                                            <?= e(safeSubstr($bot['user_agent'], 0, 30)) ?><?= safeStrlen($bot['user_agent']) > 30 ? '...' : '' ?>
                                        </span>
                                        <div class="ua-actions">
                                            <button class="copy-btn copy-ua" data-copy="<?= e($bot['user_agent']) ?>" title="复制User-Agent">
                                                <i class="bi bi-copy"></i>
                                            </button>
                                            <button class="expand-btn" onclick="toggleUADetails(this)" title="查看完整User-Agent">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="ua-full" style="display: none;">
                                        <div class="ua-full-text"><?= e($bot['user_agent']) ?></div>
                                        <button class="copy-btn copy-ua-full" data-copy="<?= e($bot['user_agent']) ?>" title="复制完整User-Agent">
                                            <i class="bi bi-clipboard"></i> 复制完整UA
                                        </button>
                                    </div>
                                </td>
                                <td class="bot-type">
                                    <span class="bot-type-badge bot-type-<?= e($bot['bot_type']) ?>">
                                        <?= getBotTypeName($bot['bot_type']) ?>
                                    </span>
                                </td>
                                <td class="visits"><?= formatNumber($bot['visits']) ?></td>
                                <td class="percentage"><?= number_format($bot['percentage'], 1) ?>%</td>
                                <td class="last-visit"><?= formatDateTime($bot['last_visit']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?= renderPagination($botsPagination, 'bots') ?>
            <?php else: ?>
                <div class="no-data">
                    <i class="bi bi-robot"></i>
                    <p>暂无机器人访问数据</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- 页脚 -->
        <div class="footer">
            <div class="footer-left">
                <p>&copy; 2025 <?= APP_NAME ?> v<?= APP_VERSION ?>. All rights reserved.</p>
            </div>
            <div class="footer-right">
                <p><a href="https://jesoo.org/" target="_blank" style="color: inherit; text-decoration: none;">@JesooTechLab</a></p>
            </div>
        </div>
    </div>
    
    <script src="<?= assetVersion('assets/js/dashboard.js') ?>"></script>
    <script>
        // 全局变量供分页功能使用
        const currentSite = '<?= e($currentSiteKey) ?>';
        const currentPeriod = '<?= e($period) ?>';
        const currentCustomDate = '<?= e($customDate) ?>';
        const currentStartDate = '<?= e($startDate) ?>';
        const currentEndDate = '<?= e($endDate) ?>';
        
        // 切换站点
        function changeSite(selectEl) {
            const siteKey = typeof selectEl === 'string' ? selectEl : (selectEl?.value || '');
            const siteId = typeof selectEl === 'string' ? null : (selectEl?.selectedOptions?.[0]?.dataset?.siteId || null);
            
            // 更新分页筛选条件
            if (window.updatePaginationFilters) {
                window.updatePaginationFilters({ site: siteKey });
            }
            
            // 构建跳转URL
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('site', siteKey);
            const targetUrl = '?' + urlParams.toString();
            
            // 保存用户偏好（同步方式，确保保存成功后再跳转）
            if (siteId) {
                const form = new FormData();
                form.append('site_id', siteId);
                
                // 使用 fetch 同步保存，成功后再跳转
                fetch('/api/user_pref.php', {
                    method: 'POST',
                    body: form,
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    // 无论保存成功与否，都跳转
                    window.location.href = targetUrl;
                })
                .catch(() => {
                    // 保存失败也跳转
                    window.location.href = targetUrl;
                });
            } else {
                // 没有站点ID，直接跳转
                window.location.href = targetUrl;
            }
        }
        
        // 自定义日期筛选（兼容旧版本）
        function filterByDate(date) {
            if (date) {
                // 更新分页筛选条件
                if (window.updatePaginationFilters) {
                    window.updatePaginationFilters({ 
                        period: 'custom', 
                        customDate: date 
                    });
                }
                
                window.location.href = '?page=dashboard&site=' + currentSite + '&period=custom&custom_date=' + date;
            }
        }
        
        // 日期区间筛选
        function filterByDateRange(startDate, endDate) {
            if (startDate && endDate) {
                // 更新分页筛选条件
                if (window.updatePaginationFilters) {
                    window.updatePaginationFilters({ 
                        period: 'range',
                        startDate: startDate,
                        endDate: endDate
                    });
                }
                
                window.location.href = '?page=dashboard&site=' + currentSite + '&period=range&start_date=' + startDate + '&end_date=' + endDate;
            }
        }
        
        // 日期区间选择器逻辑
        document.addEventListener('DOMContentLoaded', function() {
            const customBtn = document.getElementById('customDateBtn');
            const panel = document.getElementById('dateRangePanel');
            const startDateInput = document.getElementById('startDate');
            const endDateInput = document.getElementById('endDate');
            const closeBtn = document.getElementById('closePanelBtn');
            const cancelBtn = document.getElementById('cancelBtn');
            const confirmBtn = document.getElementById('confirmBtn');
            const quickBtns = document.querySelectorAll('.quick-btn');
            
            // 初始化日期值
            if (currentStartDate) startDateInput.value = currentStartDate;
            if (currentEndDate) endDateInput.value = currentEndDate;
            
            // 显示/隐藏面板
            function togglePanel() {
                const isVisible = panel.classList.contains('show');
                if (isVisible) {
                    hidePanel();
                } else {
                    showPanel();
                }
            }
            
            function showPanel() {
                panel.classList.add('show');
                customBtn.classList.add('open');
                document.addEventListener('click', handleOutsideClick);
            }
            
            function hidePanel() {
                panel.classList.remove('show');
                customBtn.classList.remove('open');
                document.removeEventListener('click', handleOutsideClick);
            }
            
            function handleOutsideClick(e) {
                if (!panel.contains(e.target) && !customBtn.contains(e.target)) {
                    hidePanel();
                }
            }
            
            // 快捷选择逻辑
            function getQuickRange(range) {
                const today = new Date();
                const year = today.getFullYear();
                const month = today.getMonth();
                const date = today.getDate();
                const day = today.getDay();
                
                switch (range) {
                    case 'thisWeek':
                        const startOfWeek = new Date(today);
                        startOfWeek.setDate(date - day + 1); // 周一
                        return {
                            start: formatDate(startOfWeek),
                            end: formatDate(today)
                        };
                    case 'thisMonth':
                        return {
                            start: formatDate(new Date(year, month, 1)),
                            end: formatDate(today)
                        };
                    case 'lastMonth':
                        const lastMonth = new Date(year, month - 1, 1);
                        const lastMonthEnd = new Date(year, month, 0);
                        return {
                            start: formatDate(lastMonth),
                            end: formatDate(lastMonthEnd)
                        };
                    case 'thisQuarter':
                        const quarterStart = new Date(year, Math.floor(month / 3) * 3, 1);
                        return {
                            start: formatDate(quarterStart),
                            end: formatDate(today)
                        };
                    default:
                        return { start: '', end: '' };
                }
            }
            
            function formatDate(date) {
                return date.toISOString().split('T')[0];
            }
            
            function validateDates() {
                const start = startDateInput.value;
                const end = endDateInput.value;
                
                if (!start || !end) {
                    confirmBtn.disabled = true;
                    return false;
                }
                
                if (new Date(start) > new Date(end)) {
                    confirmBtn.disabled = true;
                    return false;
                }
                
                confirmBtn.disabled = false;
                return true;
            }
            
            // 事件监听
            customBtn.addEventListener('click', togglePanel);
            closeBtn.addEventListener('click', hidePanel);
            cancelBtn.addEventListener('click', hidePanel);
            
            confirmBtn.addEventListener('click', function() {
                if (validateDates()) {
                    filterByDateRange(startDateInput.value, endDateInput.value);
                }
            });
            
            startDateInput.addEventListener('change', validateDates);
            endDateInput.addEventListener('change', validateDates);
            
            quickBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const range = this.dataset.range;
                    const dates = getQuickRange(range);
                    
                    startDateInput.value = dates.start;
                    endDateInput.value = dates.end;
                    
                    // 更新按钮状态
                    quickBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    validateDates();
                });
            });
            
            // 初始验证
            validateDates();
            
            // 监听其他时间筛选按钮点击（为了更新分页状态）
            const filterButtons = document.querySelectorAll('.filter-btn:not(.custom-btn)');
            filterButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const href = this.getAttribute('href');
                    if (href) {
                        const url = new URL(href, window.location.origin);
                        const period = url.searchParams.get('period') || 'today';
                        const site = url.searchParams.get('site') || currentSite;
                        
                        // 更新分页筛选条件
                        if (window.updatePaginationFilters) {
                            window.updatePaginationFilters({ 
                                period: period,
                                site: site,
                                customDate: '',
                                startDate: '',
                                endDate: ''
                            });
                        }
                    }
                });
            });
        });
        
        // 机器人统计卡的增强功能
        
        /**
         * 复制文本到剪贴板
         */
        function copyToClipboard(text, button) {
            if (navigator.clipboard && window.isSecureContext) {
                // 现代浏览器的异步API
                navigator.clipboard.writeText(text).then(() => {
                    showCopySuccess(button);
                }).catch(() => {
                    fallbackCopy(text, button);
                });
            } else {
                // 降级方案
                fallbackCopy(text, button);
            }
        }
        
        /**
         * 降级复制方案
         */
        function fallbackCopy(text, button) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                showCopySuccess(button);
            } catch (err) {
                console.error('复制失败:', err);
                showCopyError(button);
            } finally {
                document.body.removeChild(textArea);
            }
        }
        
        /**
         * 显示复制成功提示
         */
        function showCopySuccess(button) {
            const originalIcon = button.innerHTML;
            button.innerHTML = '<i class="bi bi-check"></i>';
            button.classList.add('copy-success');
            
            setTimeout(() => {
                button.innerHTML = originalIcon;
                button.classList.remove('copy-success');
            }, 1500);
        }
        
        /**
         * 显示复制失败提示
         */
        function showCopyError(button) {
            const originalIcon = button.innerHTML;
            button.innerHTML = '<i class="bi bi-x"></i>';
            button.classList.add('copy-error');
            
            setTimeout(() => {
                button.innerHTML = originalIcon;
                button.classList.remove('copy-error');
            }, 1500);
        }
        
        /**
         * 切换User-Agent详细信息显示
         */
        function toggleUADetails(button) {
            const row = button.closest('tr');
            const uaFull = row.querySelector('.ua-full');
            const icon = button.querySelector('i');
            
            if (uaFull.style.display === 'none') {
                uaFull.style.display = 'block';
                icon.className = 'bi bi-eye-slash';
                button.title = '隐藏完整User-Agent';
            } else {
                uaFull.style.display = 'none';
                icon.className = 'bi bi-eye';
                button.title = '查看完整User-Agent';
            }
        }
        
        /**
         * 切换IP列表显示
         */
        function toggleIpList(element) {
            const row = element.closest('tr');
            const ipList = row.querySelector('.ip-list');
            
            if (!ipList) return;
            
            if (ipList.style.display === 'none') {
                ipList.style.display = 'block';
                element.classList.add('expanded');
                element.title = '点击收起IP列表';
            } else {
                ipList.style.display = 'none';
                element.classList.remove('expanded');
                const count = element.textContent.replace('+', '');
                element.title = `点击查看所有 ${parseInt(count) + 1} 个IP地址`;
            }
        }
        
        // 绑定复制按钮事件
        document.addEventListener('click', function(e) {
            if (e.target.closest('.copy-btn')) {
                e.preventDefault();
                const button = e.target.closest('.copy-btn');
                const textToCopy = button.getAttribute('data-copy');
                copyToClipboard(textToCopy, button);
            }
        });
        
        // IP地址点击复制
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('ip-text')) {
                e.preventDefault();
                const ipText = e.target.textContent;
                const copyBtn = e.target.nextElementSibling;
                copyToClipboard(ipText, copyBtn);
            }
        });
        
        // 机器人统计排序功能 - 当前排序状态
        // 默认按访问次数排序（与后端BotsDataProvider的默认行为一致）
        let currentBotSort = 'visits';
        window.currentBotSort = currentBotSort; // 暴露给全局作用域
        
        // 初始化排序按钮状态
        /**
         * 初始化排序按钮状态
         * 注意：使用事件委托，无需重复绑定
         */
        function initSortButton() {
            const sortBtn = document.getElementById('sortVisitsBtn');
            const sortIcon = sortBtn?.querySelector('.sort-icon');
            if (sortBtn && sortIcon) {
                // 根据当前排序状态设置图标
                if (currentBotSort === 'visits') {
                    sortIcon.className = 'bi bi-sort-numeric-down sort-icon active';
                    sortBtn.title = '点击恢复时间排序';
                } else {
                    sortIcon.className = 'bi bi-arrow-down-up sort-icon';
                    sortBtn.title = '点击按访问次数排序';
                }
            }
        }
        
        // 使用全局变量防止重复绑定事件
        let botSortEventBound = false;
        
        document.addEventListener('DOMContentLoaded', function() {
            // 初始化排序按钮状态
            initSortButton();
            
            // 使用事件委托：在document上监听点击（只绑定一次）
            if (!botSortEventBound) {
                document.addEventListener('click', function(e) {
                    const sortBtn = e.target.closest('#sortVisitsBtn');
                    if (sortBtn) {
                        e.preventDefault();
                        e.stopPropagation();
                        toggleBotSort();
                    }
                });
                botSortEventBound = true;
            }
        });
        
        /**
         * 切换机器人统计排序方式
         */
        let isTogglingSort = false; // 防抖标志
        
        function toggleBotSort() {
            // 防止重复点击
            if (isTogglingSort) {
                console.log('排序中，请稍候...');
                return;
            }
            
            console.log('toggleBotSort 被调用');
            
            const sortBtn = document.getElementById('sortVisitsBtn');
            if (!sortBtn) {
                console.error('排序按钮未找到');
                return;
            }
            
            const sortIcon = sortBtn.querySelector('.sort-icon');
            if (!sortIcon) {
                console.error('排序图标未找到');
                return;
            }
            
            // 设置防抖标志
            isTogglingSort = true;
            
            // 切换排序方式
            if (currentBotSort === 'time') {
                currentBotSort = 'visits';
                window.currentBotSort = 'visits';
                sortIcon.className = 'bi bi-sort-numeric-down sort-icon active';
                sortBtn.title = '点击恢复时间排序';
                console.log('切换到访问次数排序');
            } else {
                currentBotSort = 'time';
                window.currentBotSort = 'time';
                sortIcon.className = 'bi bi-arrow-down-up sort-icon';
                sortBtn.title = '点击按访问次数排序';
                console.log('切换到时间排序');
            }
            
            // 重新加载机器人数据
            refreshBotData();
        }
        
        /**
         * 刷新机器人统计数据
         */
        function refreshBotData() {
            // 兼容两种全局变量形式：window.globalPagination 或全局变量 globalPagination
            const pagination = (typeof window !== 'undefined' && window.globalPagination)
                ? window.globalPagination
                : (typeof globalPagination !== 'undefined' ? globalPagination : null);
            if (!pagination) {
                console.error('分页管理器未初始化');
                return;
            }
            
            // 获取当前筛选条件
            const currentFilters = pagination.getCurrentFilters();
            
            // 构建API URL
            const params = new URLSearchParams({
                card: 'bots',
                type: 'bots',
                page: 1, // 重置到第一页
                period: currentFilters.period,
                site: currentFilters.site,
                sort: currentBotSort
            });
            
            // 添加自定义日期参数
            if (currentFilters.customDate) {
                params.append('custom_date', currentFilters.customDate);
            }
            if (currentFilters.startDate) {
                params.append('start_date', currentFilters.startDate);
            }
            if (currentFilters.endDate) {
                params.append('end_date', currentFilters.endDate);
            }
            
            const url = '/api/pagination.php?' + params.toString();
            
            // 显示加载状态（元素健壮性判断，避免空指针）
            const sortBtn = document.getElementById('sortVisitsBtn');
            let originalIcon = null;
            if (sortBtn) {
                const iconEl = sortBtn.querySelector('.sort-icon');
                if (iconEl) {
                    originalIcon = iconEl.className;
                    // 保留 sort-icon，避免后续查询不到图标元素
                    iconEl.className = 'bi bi-arrow-clockwise sort-icon spin';
                }
                sortBtn.disabled = true;
            }
            
            // 发送请求（必须携带凭证，保持与分页机制一致）
            fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(async (response) => {
                    if (!response.ok) {
                        const text = await response.text();
                        throw new Error(`HTTP ${response.status}: ${text}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // 更新表格内容（注意：renderTableRows的参数顺序是 cardType, data）
                        const tbody = document.querySelector('.bot-stats-table tbody');
                        if (tbody && pagination.renderTableRows) {
                            tbody.innerHTML = pagination.renderTableRows('bots', data.data);
                        }
                        
                        // 更新分页控件（更健壮的查找方式）
                        const botsTable = document.querySelector('.bot-stats-table');
                        if (botsTable && pagination.renderPagination) {
                            // 查找表格后面的分页控件
                            let paginationContainer = botsTable.nextElementSibling;
                            
                            // 如果找到了分页控件，替换它
                            if (paginationContainer && paginationContainer.classList.contains('card-pagination')) {
                                paginationContainer.outerHTML = pagination.renderPagination(data.pagination, 'bots');
                            } else if (paginationContainer && paginationContainer.dataset && paginationContainer.dataset.card === 'bots') {
                                // 兼容旧版本的分页控件结构
                                paginationContainer.outerHTML = pagination.renderPagination(data.pagination, 'bots');
                            }
                        }
                        
                        // 刷新后重新初始化排序按钮状态
                        // 注意：使用setTimeout确保DOM更新完成后再初始化
                        setTimeout(() => {
                            initSortButton();
                        }, 50);
                    } else {
                        console.error('排序请求失败:', data.error);
                    }
                })
                .catch(error => {
                    console.error('排序请求错误:', error);
                })
                .finally(() => {
                    // 恢复按钮状态（元素健壮性判断）
                    const btn = document.getElementById('sortVisitsBtn');
                    if (btn) {
                        const iconEl = btn.querySelector('.sort-icon');
                        if (iconEl && originalIcon) {
                            iconEl.className = originalIcon;
                        }
                        btn.disabled = false;
                    }
                    
                    // 释放防抖标志
                    isTogglingSort = false;
                });
        }
    </script>
</body>
</html>
