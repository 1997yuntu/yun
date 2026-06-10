<?php
/**
 * 用户偏好服务
 * 将“默认站点”持久化到数据库（按用户名绑定站点ID）
 */

class UserPreferenceService {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->ensureTable();
    }
    
    /**
     * 确保偏好表存在（最小侵入创建）
     */
    private function ensureTable() {
        $sql = "
            CREATE TABLE IF NOT EXISTS user_preferences (
                username VARCHAR(191) PRIMARY KEY,
                default_site_id INT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_site_id (default_site_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        try {
            $this->pdo->exec($sql);
        } catch (Exception $e) {
            // 保守处理：不抛出，避免影响页面
        }
    }
    
    /**
     * 获取用户默认站点ID
     */
    public function getDefaultSiteId($username) {
        try {
            $stmt = $this->pdo->prepare("SELECT default_site_id FROM user_preferences WHERE username = ?");
            $stmt->execute([$username]);
            $row = $stmt->fetch();
            return $row ? (int)$row['default_site_id'] : null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * 设置用户默认站点ID
     */
    public function setDefaultSiteId($username, $siteId) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO user_preferences (username, default_site_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE default_site_id = VALUES(default_site_id)");
            return $stmt->execute([$username, $siteId]);
        } catch (Exception $e) {
            return false;
        }
    }
}
?>


