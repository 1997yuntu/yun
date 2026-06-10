<?php
/**
 * 站点管理服务类
 * 负责站点的增删改查和统计概览
 */

class SitesService {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * 获取所有站点列表
     */
    public function getAllSites() {
        try {
            $sql = "
                SELECT 
                    s.*,
                    COUNT(p.id) as total_pageviews,
                    COUNT(DISTINCT p.ip) as unique_visitors,
                    MAX(p.visit_time) as last_visit
                FROM sites s
                LEFT JOIN pageviews p ON s.site_key = p.site_key AND p.is_bot = 0
                GROUP BY s.id
                ORDER BY s.created_at DESC
            ";
            
            $stmt = $this->pdo->query($sql);
            $sites = $stmt->fetchAll();
            
            // 格式化数据
            foreach ($sites as &$site) {
                $site['total_pageviews'] = (int)$site['total_pageviews'];
                $site['unique_visitors'] = (int)$site['unique_visitors'];
                $site['last_visit'] = $site['last_visit'] ? $site['last_visit'] : null;
            }
            
            return $sites;
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * 根据ID获取站点信息
     */
    public function getSiteById($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM sites WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * 根据 site_key 获取站点
     */
    public function getSiteByKey($siteKey) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM sites WHERE site_key = ?");
            $stmt->execute([$siteKey]);
            return $stmt->fetch();
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * 根据site_key获取站点信息
     */
    public function getSiteBySiteKey($siteKey) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM sites WHERE site_key = ?");
            $stmt->execute([$siteKey]);
            return $stmt->fetch();
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * 创建新站点
     */
    public function createSite($data) {
        try {
            // 清理和标准化域名（移除协议前缀和@符号）
            $cleanDomain = $this->cleanDomain($data['domain']);
            
            // 生成唯一的 site_key（使用清理后的域名）
            $siteKey = $this->generateSiteKey($cleanDomain);
            
            // 验证数据（使用清理后的域名）
            $data['domain'] = $cleanDomain;
            $errors = $this->validateSiteData($data);
            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors];
            }
            
            // 检查域名是否已存在（使用 $data['domain'] 保持一致）
            if ($this->isDomainExists($data['domain'])) {
                return ['success' => false, 'errors' => ['domain' => '该域名已存在']];
            }
            
            // 插入站点数据（created_at 和 updated_at 由数据库自动设置）
            $sql = "INSERT INTO sites (name, domain, site_key, description, status) VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            
            $result = $stmt->execute([
                $data['name'],
                $data['domain'],
                $siteKey,
                $data['description'] ?? '',
                $data['status'] ?? 'active'
            ]);
            
            if ($result) {
                $siteId = $this->pdo->lastInsertId();
                return [
                    'success' => true, 
                    'site_id' => $siteId,
                    'site_key' => $siteKey,
                    'message' => '站点创建成功'
                ];
            } else {
                return ['success' => false, 'errors' => ['general' => '站点创建失败']];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'errors' => ['general' => $e->getMessage()]];
        }
    }
    
    /**
     * 更新站点信息
     */
    public function updateSite($id, $data) {
        try {
            // 清理和标准化域名（移除协议前缀和@符号）
            $cleanDomain = $this->cleanDomain($data['domain']);
            $data['domain'] = $cleanDomain;
            
            // 验证数据
            $errors = $this->validateSiteData($data, $id);
            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors];
            }
            
            // 检查域名是否已被其他站点使用（使用 $data['domain'] 保持一致）
            if ($this->isDomainExists($data['domain'], $id)) {
                return ['success' => false, 'errors' => ['domain' => '该域名已被其他站点使用']];
            }
            
            $sql = "UPDATE sites SET name = ?, domain = ?, description = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            
            $result = $stmt->execute([
                $data['name'],
                $data['domain'],
                $data['description'] ?? '',
                $data['status'] ?? 'active',
                $id
            ]);
            
            if ($result) {
                return ['success' => true, 'message' => '站点更新成功'];
            } else {
                return ['success' => false, 'errors' => ['general' => '站点更新失败']];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'errors' => ['general' => $e->getMessage()]];
        }
    }
    
    /**
     * 删除站点
     */
    public function deleteSite($id) {
        try {
            // 检查是否为默认站点
            $site = $this->getSiteById($id);
            if (!$site) {
                return ['success' => false, 'message' => '站点不存在'];
            }
            
            if ($site['site_key'] === 'default') {
                return ['success' => false, 'message' => '默认站点不能删除'];
            }
            
            // 开始事务
            $this->pdo->beginTransaction();
            
            // 删除相关的统计数据
            $stmt = $this->pdo->prepare("DELETE FROM pageviews WHERE site_key = ?");
            $stmt->execute([$site['site_key']]);
            
            // 删除站点
            $stmt = $this->pdo->prepare("DELETE FROM sites WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                $this->pdo->commit();
                return ['success' => true, 'message' => '站点删除成功'];
            } else {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => '站点删除失败'];
            }
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 获取站点统计概览
     */
    public function getSiteStats($siteKey, $days = 30) {
        try {
            $sql = "
                SELECT 
                    COUNT(*) as total_pageviews,
                    COUNT(DISTINCT ip) as unique_visitors,
                    COUNT(DISTINCT DATE(visit_time)) as active_days,
                    MAX(visit_time) as last_visit,
                    MIN(visit_time) as first_visit
                FROM pageviews 
                WHERE site_key = ? 
                AND is_bot = 0 
                AND visit_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$siteKey, $days]);
            $stats = $stmt->fetch();
            
            // 获取今日统计
            $todaySQL = "
                SELECT 
                    COUNT(*) as today_pageviews,
                    COUNT(DISTINCT ip) as today_visitors
                FROM pageviews 
                WHERE site_key = ? 
                AND is_bot = 0 
                AND DATE(visit_time) = CURDATE()
            ";
            
            $stmt = $this->pdo->prepare($todaySQL);
            $stmt->execute([$siteKey]);
            $todayStats = $stmt->fetch();
            
            return array_merge($stats, $todayStats);
            
        } catch (Exception $e) {
            return [
                'total_pageviews' => 0,
                'unique_visitors' => 0,
                'active_days' => 0,
                'today_pageviews' => 0,
                'today_visitors' => 0,
                'last_visit' => null,
                'first_visit' => null
            ];
        }
    }
    
    /**
     * 生成站点跟踪代码
     */
    public function generateTrackingCode($siteKey, $domain) {
        $trackingCode = <<<HTML
<!-- JefCounts 统计代码 -->
<script>
(function() {
    var script = document.createElement('script');
    script.src = 'https://{$domain}/public/assets/js/analytics.js';
    script.defer = true;
    script.setAttribute('data-site-key', '{$siteKey}');
    document.head.appendChild(script);
})();
</script>
<!-- JefCounts 统计代码结束 -->
HTML;
        
        return $trackingCode;
    }
    
    /**
     * 生成唯一的 site_key
     */
    private function generateSiteKey($domain) {
        // 基于域名和时间戳生成
        $base = preg_replace('/[^a-zA-Z0-9]/', '', $domain) . time();
        return substr(md5($base), 0, 16);
    }
    
    /**
     * 验证站点数据
     */
    private function validateSiteData($data, $excludeId = null) {
        $errors = [];
        
        // 验证站点名称
        if (empty($data['name']) || strlen(trim($data['name'])) < 2) {
            $errors['name'] = '站点名称至少需要2个字符';
        }
        
        // 验证域名
        if (empty($data['domain'])) {
            $errors['domain'] = '域名不能为空';
        } elseif (!$this->isValidDomain($data['domain'])) {
            $errors['domain'] = '请输入有效的域名格式';
        }
        
        // 验证状态
        if (isset($data['status']) && !in_array($data['status'], ['active', 'inactive'])) {
            $errors['status'] = '无效的状态值';
        }
        
        return $errors;
    }
    
    /**
     * 检查域名是否已存在
     */
    private function isDomainExists($domain, $excludeId = null) {
        try {
            $sql = "SELECT id FROM sites WHERE domain = ?";
            $params = [$domain];
            
            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetch() !== false;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * 清理域名（移除协议、@符号、路径等）
     */
    private function cleanDomain($domain) {
        // 移除协议前缀（支持 https:// 和 http://）
        $domain = preg_replace('/^https?:\/\//', '', $domain);
        
        // 移除 @ 前缀（如果有）
        $domain = preg_replace('/^@/', '', $domain);
        
        // 移除末尾的斜杠和路径
        $domain = preg_replace('/\/.*$/', '', $domain);
        
        // 移除空格
        $domain = trim($domain);
        
        return $domain;
    }
    
    /**
     * 验证域名格式
     */
    private function isValidDomain($domain) {
        // 先清理域名
        $cleanedDomain = $this->cleanDomain($domain);
        
        // 基本域名格式验证
        return filter_var($cleanedDomain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }
    
    /**
     * 切换站点状态
     */
    public function toggleSiteStatus($id) {
        try {
            $site = $this->getSiteById($id);
            if (!$site) {
                return ['success' => false, 'message' => '站点不存在'];
            }
            
            $newStatus = $site['status'] === 'active' ? 'inactive' : 'active';
            
            $stmt = $this->pdo->prepare("UPDATE sites SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $result = $stmt->execute([$newStatus, $id]);
            
            if ($result) {
                return [
                    'success' => true, 
                    'message' => '状态更新成功',
                    'new_status' => $newStatus
                ];
            } else {
                return ['success' => false, 'message' => '状态更新失败'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
?>
