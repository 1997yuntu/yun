<?php
/**
 * IP地理位置服务
 * 基于 zoujingli/ip2region Composer 包实现
 * 文档：https://github.com/zoujingli/ip2region
 */

require_once __DIR__ . '/../../vendor/autoload.php';

class IpLocationService {
    private $ip2region;
    private $cache = [];
    private $cacheFile;
    
    // 缓存配置
    private $maxCacheSize = 10000; // 最多缓存10000个IP地址
    private $cacheExpireDays = 30;  // 缓存30天后过期
    
    public function __construct() {
        $this->cacheFile = __DIR__ . '/../../data/ip_location_cache.json';
        $this->loadCache();
        $this->cleanExpiredCache(); // 启动时清理过期缓存
        
        try {
            // 使用默认策略，性能和稳定性最佳
            $this->ip2region = new Ip2Region();
        } catch (Exception $e) {
            // 如果数据库文件不存在，使用简化模式
            $this->ip2region = null;
        }
    }
    
    /**
     * 加载缓存
     */
    private function loadCache() {
        if (file_exists($this->cacheFile)) {
            $data = file_get_contents($this->cacheFile);
            $this->cache = json_decode($data, true) ?: [];
        }
    }
    
    /**
     * 保存缓存
     */
    private function saveCache() {
        // 在保存前检查缓存大小，如果超过限制则清理
        if (count($this->cache) > $this->maxCacheSize) {
            $this->trimCache();
        }
        
        file_put_contents($this->cacheFile, json_encode($this->cache, JSON_UNESCAPED_UNICODE));
    }
    
    /**
     * 清理过期的缓存
     * 根据最后访问时间删除超过30天的缓存项
     */
    private function cleanExpiredCache() {
        if (empty($this->cache)) {
            return;
        }
        
        $expireTime = time() - ($this->cacheExpireDays * 86400);
        $cleaned = 0;
        
        foreach ($this->cache as $ip => $data) {
            // 检查是否有时间戳字段
            if (isset($data['cached_at']) && $data['cached_at'] < $expireTime) {
                unset($this->cache[$ip]);
                $cleaned++;
            }
        }
        
        // 如果清理了缓存，保存一次
        if ($cleaned > 0) {
            $this->saveCache();
        }
    }
    
    /**
     * 修剪缓存到合理大小
     * 保留最近使用的IP，删除旧的
     */
    private function trimCache() {
        // 如果缓存大小超过限制，保留最近访问的80%
        $targetSize = (int)($this->maxCacheSize * 0.8);
        
        // 按时间戳排序（如果有的话）
        $cacheWithTime = [];
        $cacheWithoutTime = [];
        
        foreach ($this->cache as $ip => $data) {
            if (isset($data['cached_at'])) {
                $cacheWithTime[$ip] = $data;
            } else {
                $cacheWithoutTime[$ip] = $data;
            }
        }
        
        // 按时间戳降序排序（最新的在前）
        uasort($cacheWithTime, function($a, $b) {
            return ($b['cached_at'] ?? 0) - ($a['cached_at'] ?? 0);
        });
        
        // 保留最新的数据
        $this->cache = array_slice($cacheWithTime, 0, $targetSize, true);
        
        // 如果还有空间，添加一些没有时间戳的
        $remaining = $targetSize - count($this->cache);
        if ($remaining > 0) {
            $this->cache = array_merge(
                $this->cache, 
                array_slice($cacheWithoutTime, 0, $remaining, true)
            );
        }
    }
    
    /**
     * 查询IP地址的地理位置
     * 
     * @param string $ip IP地址
     * @return array 地理位置信息
     */
    public function search($ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return $this->getDefaultLocation();
        }
        
        // 检查缓存
        if (isset($this->cache[$ip])) {
            return $this->cache[$ip];
        }
        
        try {
            if ($this->ip2region) {
                // 使用 ip2region 库查询，使用 search 方法获取结构化数据
                $result = $this->ip2region->search($ip);
                if ($result && $result !== '未知') {
                    $location = $this->parseIp2RegionResult($result);
                } else {
                    // 如果 ip2region 返回未知，尝试降级搜索
                    $location = $this->fallbackSearch($ip);
                }
            } else {
                // 降级到简化识别
                $location = $this->fallbackSearch($ip);
            }
            
            // 缓存结果（添加时间戳）
            $location['cached_at'] = time();
            $this->cache[$ip] = $location;
            
            // 每20个IP保存一次缓存
            if (count($this->cache) % 20 === 0) {
                $this->saveCache();
            }
            
            return $location;
            
        } catch (Exception $e) {
            return $this->getDefaultLocation();
        }
    }
    
    /**
     * 解析 ip2region 查询结果
     * 
     * @param string $result ip2region 返回的结果，格式如："中国广东省深圳市【电信】" 或 "中国|广东省|深圳市|电信"
     * @return array 解析后的地理位置信息
     */
    private function parseIp2RegionResult($result) {
        if (empty($result)) {
            return $this->getDefaultLocation();
        }
        
        $country = '';
        $province = '';
        $city = '';
        $isp = '';
        
        // 检查是否是管道分隔格式（search方法返回的格式）
        if (strpos($result, '|') !== false) {
            $parts = explode('|', $result);
            $country = trim($parts[0] ?? '');
            $province = trim($parts[1] ?? '');
            $city = trim($parts[2] ?? '');
            $isp = trim($parts[3] ?? '');
            
            // 清理无效数据
            $country = $this->cleanLocationData($country);
            $province = $this->cleanLocationData($province);
            $city = $this->cleanLocationData($city);
            $isp = $this->cleanLocationData($isp);
        } else {
            // 解析格式：中国广东省深圳市【电信】
            // 提取ISP信息（在【】中）
            if (preg_match('/【(.+?)】/', $result, $matches)) {
                $isp = $matches[1];
                $result = str_replace($matches[0], '', $result);
            }
            
            // 解析地理位置
            if (strpos($result, '中国') === 0) {
                $country = '中国';
                $result = substr($result, 2); // 移除"中国"
                
                // 解析省份
                if (preg_match('/^(.+?省|.+?市|.+?自治区|.+?特别行政区)/', $result, $matches)) {
                    $province = $matches[1];
                    $result = substr($result, strlen($province));
                    
                    // 解析城市
                    if (preg_match('/^(.+?市|.+?县|.+?区)/', $result, $matches)) {
                        $city = $matches[1];
                    }
                }
            } else {
                // 非中国IP
                $country = $result ?: '未知';
            }
        }
        
        // 构建显示名称
        $displayName = $this->buildDisplayName($country, $province, $city);
        
        return [
            'country' => $country ?: '未知',
            'province' => $province,
            'city' => $city,
            'isp' => $isp,
            'display_name' => $displayName,
            'full_location' => $result
        ];
    }
    
    /**
     * 降级搜索（当 ip2region 不可用时）
     */
    private function fallbackSearch($ip) {
        // 基于IP段的简单识别
        $ipLong = ip2long($ip);
        
        if ($ipLong === false) {
            return $this->getDefaultLocation();
        }
        
        // 常见的中国IP段
        $chineseRanges = [
            ['start' => ip2long('1.0.0.0'), 'end' => ip2long('1.255.255.255'), 'location' => '中国'],
            ['start' => ip2long('14.0.0.0'), 'end' => ip2long('14.255.255.255'), 'location' => '中国'],
            ['start' => ip2long('27.0.0.0'), 'end' => ip2long('27.255.255.255'), 'location' => '中国'],
            ['start' => ip2long('36.0.0.0'), 'end' => ip2long('36.255.255.255'), 'location' => '中国'],
            ['start' => ip2long('39.0.0.0'), 'end' => ip2long('39.255.255.255'), 'location' => '中国'],
            ['start' => ip2long('42.0.0.0'), 'end' => ip2long('42.255.255.255'), 'location' => '中国'],
            ['start' => ip2long('58.0.0.0'), 'end' => ip2long('58.255.255.255'), 'location' => '中国'],
            ['start' => ip2long('59.0.0.0'), 'end' => ip2long('59.255.255.255'), 'location' => '中国'],
            ['start' => ip2long('60.0.0.0'), 'end' => ip2long('60.255.255.255'), 'location' => '中国'],
            ['start' => ip2long('61.0.0.0'), 'end' => ip2long('61.255.255.255'), 'location' => '中国'],
            ['start' => ip2long('101.0.0.0'), 'end' => ip2long('101.255.255.255'), 'location' => '中国'],
            ['start' => ip2long('106.0.0.0'), 'end' => ip2long('106.255.255.255'), 'location' => '中国'],
            ['start' => ip2long('110.0.0.0'), 'end' => ip2long('110.255.255.255'), 'location' => '中国'],
            ['start' => ip2long('111.0.0.0'), 'end' => ip2long('111.255.255.255'), 'location' => '中国'],
            ['start' => ip2long('112.0.0.0'), 'end' => ip2long('112.255.255.255'), 'location' => '中国'],
            ['start' => ip2long('113.0.0.0'), 'end' => ip2long('113.255.255.255'), 'location' => '中国'],
            ['start' => ip2long('114.0.0.0'), 'end' => ip2long('114.255.255.255'), 'location' => '中国'],
            ['start' => ip2long('115.0.0.0'), 'end' => ip2long('115.255.255.255'), 'location' => '中国'],
            ['start' => ip2long('116.0.0.0'), 'end' => ip2long('116.255.255.255'), 'location' => '中国'],
            ['start' => ip2long('117.0.0.0'), 'end' => ip2long('117.255.255.255'), 'location' => '中国'],
            ['start' => ip2long('118.0.0.0'), 'end' => ip2long('118.255.255.255'), 'location' => '中国'],
            ['start' => ip2long('119.0.0.0'), 'end' => ip2long('119.255.255.255'), 'location' => '中国'],
            ['start' => ip2long('120.0.0.0'), 'end' => ip2long('120.255.255.255'), 'location' => '中国'],
            ['start' => ip2long('121.0.0.0'), 'end' => ip2long('121.255.255.255'), 'location' => '中国'],
            ['start' => ip2long('122.0.0.0'), 'end' => ip2long('122.255.255.255'), 'location' => '中国'],
            ['start' => ip2long('123.0.0.0'), 'end' => ip2long('123.255.255.255'), 'location' => '中国'],
            ['start' => ip2long('124.0.0.0'), 'end' => ip2long('124.255.255.255'), 'location' => '中国'],
            ['start' => ip2long('125.0.0.0'), 'end' => ip2long('125.255.255.255'), 'location' => '中国'],
        ];
        
        foreach ($chineseRanges as $range) {
            if ($ipLong >= $range['start'] && $ipLong <= $range['end']) {
                return [
                    'country' => '中国',
                    'province' => '',
                    'city' => '',
                    'isp' => '',
                    'display_name' => '中国',
                    'full_location' => '中国'
                ];
            }
        }
        
        return $this->getDefaultLocation();
    }
    
    /**
     * 清理位置数据
     */
    private function cleanLocationData($data) {
        if (empty($data) || $data === '0' || $data === '*' || $data === '未知') {
            return '';
        }
        return $data;
    }
    
    /**
     * 构建显示名称
     */
    private function buildDisplayName($country, $province, $city) {
        $parts = [];
        
        // 处理中国地区
        if ($country === '中国') {
            if ($province) {
                // 处理特别行政区
                if (strpos($province, '香港') !== false) {
                    $parts[] = '香港';
                } elseif (strpos($province, '澳门') !== false) {
                    $parts[] = '澳门';
                } elseif (strpos($province, '台湾') !== false || strpos($province, '台北') !== false) {
                    $parts[] = '台湾';
                    if ($city && $city !== $province) {
                        $parts[] = $city;
                    }
                } else {
                    // 普通省份
                    $parts[] = $province;
                    if ($city && $city !== $province && !empty($city)) {
                        $parts[] = $city;
                    }
                }
            } else {
                $parts[] = '中国';
            }
        } else {
            // 国外地区
            if ($country && $country !== '未知') {
                $parts[] = $country;
                
                // 添加州/省信息（如果有且不同于国家）
                if ($province && $province !== $country && $province !== '0') {
                    $parts[] = $province;
                }
                
                // 添加城市信息（如果有且不同于省份）
                if ($city && $city !== $province && $city !== $country && $city !== '0') {
                    $parts[] = $city;
                }
            } else {
                $parts[] = '未知地区';
            }
        }
        
        return empty($parts) ? '未知地区' : implode(' ', $parts);
    }
    
    /**
     * 获取默认位置信息
     */
    private function getDefaultLocation() {
        return [
            'country' => '未知',
            'province' => '',
            'city' => '',
            'isp' => '',
            'display_name' => '未知地区',
            'full_location' => '未知'
        ];
    }
    
    /**
     * 批量查询IP地址
     */
    public function batchSearch($ips) {
        $results = [];
        
        if ($this->ip2region && method_exists($this->ip2region, 'batchSearch')) {
            // 使用 ip2region 的批量查询
            try {
                $batchResults = $this->ip2region->batchSearch($ips);
                foreach ($batchResults as $ip => $result) {
                    $results[$ip] = $this->parseIp2RegionResult($result);
                }
            } catch (Exception $e) {
                // 降级到单个查询
                foreach ($ips as $ip) {
                    $results[$ip] = $this->search($ip);
                }
            }
        } else {
            // 单个查询
            foreach ($ips as $ip) {
                $results[$ip] = $this->search($ip);
            }
        }
        
        return $results;
    }
    
    /**
     * 获取访问地区统计
     */
    public function getRegionStats($pdo, $whereClause = '', $page = 1) {
        try {
            // 获取设置服务以获取显示限制
            require_once __DIR__ . '/SettingsService.php';
            $settingsService = new SettingsService($pdo);
            $limit = $settingsService->getSetting('display', 'region_stats_limit') ?? 10;
            
            // 处理 whereClause：支持数组或字符串格式
            $timeFilter = '';
            if (!empty($whereClause)) {
                // 如果是数组，提取 'current' 键
                $whereString = is_array($whereClause) ? ($whereClause['current'] ?? '') : $whereClause;
                
                // 从 WHERE 子句中提取条件（去掉 WHERE 和 is_bot = 0）
                $timeFilter = str_replace('WHERE', '', $whereString);
                $timeFilter = str_replace('is_bot = 0', '', $timeFilter);
                
                // 清理多余的 AND 和空格
                $timeFilter = preg_replace('/\s+AND\s+AND\s+/', ' AND ', $timeFilter); // 多个AND
                $timeFilter = preg_replace('/^\s*AND\s*/', '', $timeFilter); // 开头的AND
                $timeFilter = preg_replace('/\s*AND\s*$/', '', $timeFilter); // 结尾的AND
                $timeFilter = trim($timeFilter);
                
                // 确保以 AND 开头（如果有内容）
                if (!empty($timeFilter)) {
                    $timeFilter = 'AND ' . $timeFilter;
                }
            }
            
            // 第一步：获取时间筛选范围内的所有IP数据（不分页）
            $sql = "
                SELECT 
                    ip,
                    COUNT(*) as visit_count,
                    MAX(visit_time) as last_visit
                FROM pageviews 
                WHERE is_bot = 0 {$timeFilter}
                GROUP BY ip
                ORDER BY visit_count DESC
            ";
            
            $stmt = $pdo->query($sql);
            $allIpData = $stmt->fetchAll();
            
            if (empty($allIpData)) {
                return [
                    'data' => [],
                    'pagination' => [
                        'current_page' => 1,
                        'total_pages' => 1,
                        'total_records' => 0,
                        'limit' => $limit,
                        'offset' => 0,
                        'has_prev' => false,
                        'has_next' => false
                    ]
                ];
            }
            
            // 第二步：对所有IP进行地理位置解析和聚合
            $ips = array_column($allIpData, 'ip');
            $locations = $this->batchSearch($ips);
            
            $regionStats = [];
            
            foreach ($allIpData as $row) {
                $ip = $row['ip'];
                $location = $locations[$ip] ?? $this->getDefaultLocation();
                $regionName = $location['display_name'];
                
                if (!isset($regionStats[$regionName])) {
                    $regionStats[$regionName] = [
                        'region' => $regionName,
                        'visits' => 0,
                        'unique_ips' => 0,
                        'last_visit' => $row['last_visit'],
                        'country' => $location['country'],
                        'province' => $location['province'],
                        'city' => $location['city']
                    ];
                }
                
                $regionStats[$regionName]['visits'] += $row['visit_count'];
                $regionStats[$regionName]['unique_ips']++;
                
                if ($row['last_visit'] > $regionStats[$regionName]['last_visit']) {
                    $regionStats[$regionName]['last_visit'] = $row['last_visit'];
                }
            }
            
            // 按访问量排序
            uasort($regionStats, function($a, $b) {
                return $b['visits'] - $a['visits'];
            });
            
            // 计算百分比
            $totalVisits = array_sum(array_column($regionStats, 'visits'));
            foreach ($regionStats as &$stat) {
                $stat['percentage'] = $totalVisits > 0 ? round(($stat['visits'] / $totalVisits) * 100, 1) : 0;
            }
            
            // 第三步：对聚合后的地区数据进行分页
            $allRegionData = array_values($regionStats);
            $totalRegions = count($allRegionData);
            
            // 计算分页信息（基于地区数量，不是IP数量）
            $totalPages = max(1, ceil($totalRegions / $limit));
            $currentPage = max(1, min($page, $totalPages));
            $offset = ($currentPage - 1) * $limit;
            
            // 获取当前页的地区数据
            $pageData = array_slice($allRegionData, $offset, $limit);
            
            return [
                'data' => $pageData,
                'pagination' => [
                    'current_page' => $currentPage,
                    'total_pages' => $totalPages,
                    'total_records' => $totalRegions, // 这里是地区总数，不是IP总数
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_prev' => $currentPage > 1,
                    'has_next' => $currentPage < $totalPages
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'data' => [],
                'pagination' => [
                    'current_page' => 1,
                    'total_pages' => 1,
                    'total_records' => 0,
                    'limit' => $limit,
                    'offset' => 0,
                    'has_prev' => false,
                    'has_next' => false
                ]
            ];
        }
    }
    
    /**
     * 获取性能统计
     */
    public function getStats() {
        if ($this->ip2region && method_exists($this->ip2region, 'getStats')) {
            return $this->ip2region->getStats();
        }
        
        return [
            'cache_hits' => count($this->cache),
            'library' => 'fallback',
            'version' => '1.0.0'
        ];
    }
    
    /**
     * 析构函数，保存缓存
     */
    public function __destruct() {
        if (!empty($this->cache)) {
            $this->saveCache();
        }
    }
}
?>
