<?php
/**
 * 筛选条件类
 * 统一管理时间筛选、站点筛选等条件，避免字符串拼接错误
 */

class FilterCriteria {
    private $siteKey;
    private $period;
    private $customDate;
    private $customStartDate;
    private $customEndDate;
    private $startDate;
    private $endDate;
    private $excludeBots;
    private $label;
    
    public function __construct($siteKey = 'default', $period = 'today', $customDate = '', $customStartDate = '', $customEndDate = '') {
        $this->siteKey = $siteKey;
        $this->period = $period;
        $this->customDate = $customDate;
        $this->customStartDate = $customStartDate;
        $this->customEndDate = $customEndDate;
        $this->excludeBots = true; // 默认排除机器人
        $this->calculateDateRange();
    }
    
    /**
     * 根据period和customDate计算具体的日期范围
     */
    private function calculateDateRange() {
        switch ($this->period) {
            case 'yesterday':
                $this->startDate = date('Y-m-d', strtotime('-1 day'));
                $this->endDate = date('Y-m-d', strtotime('-1 day'));
                $this->label = '昨日';
                break;
            case 'week':
                $this->startDate = date('Y-m-d', strtotime('-7 days'));
                $this->endDate = date('Y-m-d');
                $this->label = '近7天';
                break;
            case 'month':
                $this->startDate = date('Y-m-d', strtotime('-30 days'));
                $this->endDate = date('Y-m-d');
                $this->label = '近30天';
                break;
            case 'year':
                $this->startDate = date('Y-m-d', strtotime('-365 days'));
                $this->endDate = date('Y-m-d');
                $this->label = '近365天';
                break;
            case 'custom':
                if (!empty($this->customDate)) {
                    // 兼容旧的单日期格式
                    $this->startDate = $this->customDate;
                    $this->endDate = $this->customDate;
                    $this->label = $this->customDate;
                } else {
                    // 回退到今日
                    $this->startDate = date('Y-m-d');
                    $this->endDate = date('Y-m-d');
                    $this->label = '今日';
                }
                break;
            case 'range':
                if (!empty($this->customStartDate) && !empty($this->customEndDate)) {
                    $this->startDate = $this->customStartDate;
                    $this->endDate = $this->customEndDate;
                    
                    // 生成标签
                    if ($this->startDate === $this->endDate) {
                        $this->label = $this->startDate;
                    } else {
                        $this->label = $this->startDate . ' ~ ' . $this->endDate;
                    }
                } else {
                    // 回退到今日
                    $this->startDate = date('Y-m-d');
                    $this->endDate = date('Y-m-d');
                    $this->label = '今日';
                }
                break;
            default: // today
                $this->startDate = date('Y-m-d');
                $this->endDate = date('Y-m-d');
                $this->label = '今日';
                break;
        }
    }
    
    /**
     * 生成WHERE条件（参数化查询，避免SQL注入）
     */
    public function buildWhereConditions() {
        $conditions = [];
        $params = [];
        
        // 站点筛选
        $conditions[] = "site_key = ?";
        $params[] = $this->siteKey;
        
        // 机器人筛选
        if ($this->excludeBots) {
            $conditions[] = "is_bot = 0";
        }
        
        // 时间范围筛选
        if ($this->startDate === $this->endDate) {
            // 单日查询
            $conditions[] = "DATE(visit_time) = ?";
            $params[] = $this->startDate;
        } else {
            // 范围查询
            $conditions[] = "visit_time >= ? AND visit_time < DATE_ADD(?, INTERVAL 1 DAY)";
            $params[] = $this->startDate;
            $params[] = $this->endDate;
        }
        
        return [
            'where' => 'WHERE ' . implode(' AND ', $conditions),
            'params' => $params
        ];
    }
    
    /**
     * 兼容原有系统的格式 - 返回类似原getTimeFilterClause的结果
     */
    public function getLegacyFormatOld() {
        $whereData = $this->buildWhereConditions();
        return [
            'current' => $whereData['where'],
            'previous' => $this->getPreviousPeriodWhere(),
            'label' => $this->label,
            'params' => $whereData['params'],
            'previous_params' => $this->getPreviousPeriodParams()
        ];
    }
    
    /**
     * 获取上一个周期的WHERE条件（用于对比）
     */
    private function getPreviousPeriodWhere() {
        $conditions = [];
        $conditions[] = "site_key = ?";
        
        if ($this->excludeBots) {
            $conditions[] = "is_bot = 0";
        }
        
        // 计算上一个周期的时间范围
        switch ($this->period) {
            case 'yesterday':
                $conditions[] = "DATE(visit_time) = DATE_SUB(?, INTERVAL 1 DAY)";
                break;
            case 'week':
                $conditions[] = "visit_time >= DATE_SUB(?, INTERVAL 7 DAY) AND visit_time < ?";
                break;
            case 'month':
                $conditions[] = "visit_time >= DATE_SUB(?, INTERVAL 30 DAY) AND visit_time < ?";
                break;
            case 'year':
                $conditions[] = "visit_time >= DATE_SUB(?, INTERVAL 365 DAY) AND visit_time < ?";
                break;
            case 'custom':
                $conditions[] = "DATE(visit_time) = DATE_SUB(?, INTERVAL 1 DAY)";
                break;
            case 'range':
                // 对于日期区间，上一周期是相同长度的前一个区间
                $daysDiff = (strtotime($this->endDate) - strtotime($this->startDate)) / 86400;
                $conditions[] = "visit_time >= DATE_SUB(?, INTERVAL " . ($daysDiff + 1) . " DAY) AND visit_time < ?";
                break;
            default: // today
                $conditions[] = "DATE(visit_time) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                break;
        }
        
        return 'WHERE ' . implode(' AND ', $conditions);
    }
    
    /**
     * 获取上一个周期的参数
     */
    private function getPreviousPeriodParams() {
        $params = [$this->siteKey];
        
        switch ($this->period) {
            case 'yesterday':
                $params[] = $this->startDate;
                break;
            case 'week':
                $params[] = $this->startDate;
                $params[] = $this->startDate;
                break;
            case 'month':
                $params[] = $this->startDate;
                $params[] = $this->startDate;
                break;
            case 'year':
                $params[] = $this->startDate;
                $params[] = $this->startDate;
                break;
            case 'custom':
                $params[] = $this->startDate;
                break;
            case 'range':
                $params[] = $this->startDate;
                $params[] = $this->startDate;
                break;
            default: // today - 不需要额外参数
                break;
        }
        
        return $params;
    }
    
    // Getter 方法
    public function getSiteKey() {
        return $this->siteKey;
    }
    
    public function getPeriod() {
        return $this->period;
    }
    
    public function getCustomDate() {
        return $this->customDate;
    }
    
    public function getLabel() {
        return $this->label;
    }
    
    public function getDateRange() {
        return [
            'start' => $this->startDate,
            'end' => $this->endDate
        ];
    }
    
    public function isExcludingBots() {
        return $this->excludeBots;
    }
    
    public function setExcludeBots($exclude) {
        $this->excludeBots = $exclude;
        return $this;
    }
    
    /**
     * 获取兼容旧格式的WHERE条件（用于还未迁移的代码）
     */
    public function getLegacyFormat() {
        $whereData = $this->buildWhereConditions();
        
        // 生成旧格式的字符串WHERE条件（不安全，仅用于兼容）
        $whereString = $whereData['where'];
        $params = $whereData['params'];
        
        // 替换参数占位符为实际值（仅用于兼容，不推荐）
        foreach ($params as $param) {
            $whereString = preg_replace('/\?/', "'" . addslashes($param) . "'", $whereString, 1);
        }
        
        // 生成上一周期的WHERE条件
        $previousWhere = $this->getPreviousPeriodWhere();
        $previousParams = $this->getPreviousPeriodParams();
        
        // 替换上一周期的参数占位符
        if (!empty($previousParams)) {
            foreach ($previousParams as $param) {
                if ($param !== null) {
                    $previousWhere = preg_replace('/\?/', "'" . addslashes($param) . "'", $previousWhere, 1);
                }
            }
        }
        
        return [
            'current' => $whereString,
            'previous' => $previousWhere,
            'label' => $this->label,
            'where' => $whereString  // 兼容某些只使用where字段的代码
        ];
    }
}
?>
