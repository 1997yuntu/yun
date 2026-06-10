<?php
/**
 * 设置服务类
 * 负责处理系统设置的读取、保存和管理
 */

class SettingsService {
    private $pdo;
    private $settingsFile;
    private $settings = null;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->settingsFile = __DIR__ . '/../../app/settings.json';
        $this->loadSettings();
    }
    
    /**
     * 加载设置
     */
    private function loadSettings() {
        if (file_exists($this->settingsFile)) {
            $this->settings = json_decode(file_get_contents($this->settingsFile), true);
        }
        
        // 如果设置文件不存在或为空，使用默认设置
        if (!$this->settings) {
            $this->settings = $this->getDefaultSettings();
            $this->saveSettings();
        }
    }
    
    /**
     * 获取默认设置
     */
    private function getDefaultSettings() {
        return [
            'display' => [
                'ip_stats_limit' => 10,           // IP访问统计显示行数
                'popular_pages_limit' => 10,      // 热门页面显示行数
                'browser_stats_limit' => 10,      // 浏览器统计显示行数
                'os_stats_limit' => 10,           // 操作系统统计显示行数
                'bot_stats_limit' => 10,          // 机器人统计每类显示行数
                'source_stats_limit' => 10,       // 访问来源统计显示行数
                'region_stats_limit' => 10,       // 访问地区统计显示行数
            ],
            'data' => [
                'retention_days' => 365,          // 数据保留天数
                'auto_cleanup' => true,           // 自动清理过期数据
                'cleanup_time' => '02:00',        // 自动清理时间
            ],
            'dashboard' => [
                'auto_refresh' => true,           // 自动刷新
                'refresh_interval' => 30,         // 刷新间隔（秒）
                'default_period' => 'today',      // 默认时间筛选
                'show_percentage' => true,        // 显示百分比
            ],
            'security' => [
                'session_timeout' => 7200,       // 会话超时时间（秒）
                'max_login_attempts' => 5,       // 最大登录尝试次数
                'lockout_duration' => 300,       // 锁定时长（秒）
            ],
            'user' => [
                'allow_registration' => false,   // 是否允许用户注册
                'max_sites_per_user' => 10,      // 每个用户最多可添加的站点数
            ],
            'performance' => [
                'enable_cache' => true,           // 启用缓存
                'cache_duration' => 300,          // 缓存时长（秒）
                'compress_response' => true,      // 压缩响应
            ]
        ];
    }
    
    /**
     * 获取所有设置
     */
    public function getAllSettings() {
        return $this->settings;
    }
    
    /**
     * 获取特定设置
     */
    public function getSetting($category, $key = null) {
        if ($key === null) {
            return $this->settings[$category] ?? [];
        }
        return $this->settings[$category][$key] ?? null;
    }
    
    /**
     * 更新设置
     */
    public function updateSetting($category, $key, $value) {
        if (!isset($this->settings[$category])) {
            $this->settings[$category] = [];
        }
        $this->settings[$category][$key] = $value;
        return $this->saveSettings();
    }
    
    /**
     * 批量更新设置
     */
    public function updateSettings($newSettings) {
        foreach ($newSettings as $category => $settings) {
            foreach ($settings as $key => $value) {
                $this->updateSetting($category, $key, $value);
            }
        }
        return $this->saveSettings();
    }
    
    /**
     * 保存设置到文件
     */
    private function saveSettings() {
        $this->settings['updated_at'] = date('Y-m-d H:i:s');
        return file_put_contents(
            $this->settingsFile, 
            json_encode($this->settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        ) !== false;
    }
    
    /**
     * 重置为默认设置
     */
    public function resetToDefaults() {
        $this->settings = $this->getDefaultSettings();
        return $this->saveSettings();
    }
    
    /**
     * 清理统计数据
     */
    public function cleanupData($options = []) {
        $results = [];
        
        try {
            // 快捷清理：删除指定日期之前的所有数据
            if (isset($options['before_date'])) {
                $stmt = $this->pdo->prepare("DELETE FROM pageviews WHERE visit_time < ?");
                $stmt->execute([$options['before_date']]);
                $results['deleted_records'] = $stmt->rowCount();
            }
            // 根据日期范围清理
            elseif (isset($options['date_range'])) {
                $startDate = $options['date_range']['start'] ?? null;
                $endDate = $options['date_range']['end'] ?? null;
                
                $whereClause = "WHERE 1=1";
                if ($startDate) {
                    $whereClause .= " AND DATE(visit_time) >= '$startDate'";
                }
                if ($endDate) {
                    $whereClause .= " AND DATE(visit_time) <= '$endDate'";
                }
                
                $stmt = $this->pdo->prepare("DELETE FROM pageviews $whereClause");
                $stmt->execute();
                $results['deleted_records'] = $stmt->rowCount();
            }
            
            // 清理机器人数据
            if (isset($options['cleanup_bots']) && $options['cleanup_bots']) {
                $stmt = $this->pdo->prepare("DELETE FROM pageviews WHERE is_bot = 1");
                $stmt->execute();
                $results['deleted_bots'] = $stmt->rowCount();
            }
            
            // 清理过期数据
            if (isset($options['cleanup_expired']) && $options['cleanup_expired']) {
                $retentionDays = $this->getSetting('data', 'retention_days') ?? 365;
                $stmt = $this->pdo->prepare("DELETE FROM pageviews WHERE visit_time < DATE_SUB(NOW(), INTERVAL ? DAY)");
                $stmt->execute([$retentionDays]);
                $results['deleted_expired'] = $stmt->rowCount();
            }
            
            // 优化数据库表
            if (isset($options['optimize_tables']) && $options['optimize_tables']) {
                $this->pdo->exec("OPTIMIZE TABLE pageviews");
                $results['optimized'] = true;
            }
            
            $results['success'] = true;
            
        } catch (Exception $e) {
            $results['success'] = false;
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * 获取数据库统计信息
     */
    public function getDatabaseStats() {
        try {
            // 总记录数
            $totalRecords = $this->pdo->query("SELECT COUNT(*) as count FROM pageviews")->fetch()['count'];
            
            // 机器人记录数
            $botRecords = $this->pdo->query("SELECT COUNT(*) as count FROM pageviews WHERE is_bot = 1")->fetch()['count'];
            
            // 最早记录时间
            $oldestRecord = $this->pdo->query("SELECT MIN(visit_time) as oldest FROM pageviews")->fetch()['oldest'];
            
            // 最新记录时间
            $newestRecord = $this->pdo->query("SELECT MAX(visit_time) as newest FROM pageviews")->fetch()['newest'];
            
            // 数据库大小（近似）
            $dbSize = $this->pdo->query("
                SELECT 
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() AND table_name = 'pageviews'
            ")->fetch()['size_mb'] ?? 0;
            
            return [
                'total_records' => $totalRecords,
                'bot_records' => $botRecords,
                'human_records' => $totalRecords - $botRecords,
                'oldest_record' => $oldestRecord,
                'newest_record' => $newestRecord,
                'database_size_mb' => $dbSize,
                'retention_days' => $this->getSetting('data', 'retention_days')
            ];
            
        } catch (Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 检查是否允许用户注册
     */
    public function isRegistrationAllowed() {
        return $this->getSetting('user', 'allow_registration') === true;
    }

    /**
     * 切换用户注册开关
     *
     * @param bool $enabled 是否启用
     * @return bool 保存是否成功
     */
    public function toggleRegistration($enabled) {
        return $this->updateSetting('user', 'allow_registration', $enabled);
    }

    /**
     * 获取每个用户的最大站点数限制
     *
     * @return int 最大站点数
     */
    public function getMaxSitesPerUser() {
        return $this->getSetting('user', 'max_sites_per_user') ?? 10;
    }

    /**
     * 验证设置值
     */
    public function validateSetting($category, $key, $value) {
        $validations = [
            'display' => [
                'ip_stats_limit' => ['type' => 'int', 'min' => 5, 'max' => 100],
                'popular_pages_limit' => ['type' => 'int', 'min' => 5, 'max' => 50],
                'browser_stats_limit' => ['type' => 'int', 'min' => 5, 'max' => 20],
                'os_stats_limit' => ['type' => 'int', 'min' => 5, 'max' => 20],
                'bot_stats_limit' => ['type' => 'int', 'min' => 3, 'max' => 10],
                'source_stats_limit' => ['type' => 'int', 'min' => 5, 'max' => 20],
                'region_stats_limit' => ['type' => 'int', 'min' => 5, 'max' => 20],
            ],
            'data' => [
                'retention_days' => ['type' => 'int', 'min' => 30, 'max' => 3650],
            ],
            'dashboard' => [
                'refresh_interval' => ['type' => 'int', 'min' => 10, 'max' => 300],
            ],
            'security' => [
                'session_timeout' => ['type' => 'int', 'min' => 300, 'max' => 86400],
                'max_login_attempts' => ['type' => 'int', 'min' => 3, 'max' => 10],
                'lockout_duration' => ['type' => 'int', 'min' => 60, 'max' => 3600],
            ]
        ];
        
        if (!isset($validations[$category][$key])) {
            return true; // 没有验证规则的设置直接通过
        }
        
        $rules = $validations[$category][$key];
        
        if ($rules['type'] === 'int') {
            $value = (int)$value;
            if (isset($rules['min']) && $value < $rules['min']) {
                return false;
            }
            if (isset($rules['max']) && $value > $rules['max']) {
                return false;
            }
        }
        
        return true;
    }
}
?>
