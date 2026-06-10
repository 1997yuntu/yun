# ⚠️ 警告：此脚本用于迁移旧版管理员账户到新版用户系统

# 使用方法：
# php migrate_admin.php

<?php
/**
 * 管理员账户迁移脚本
 * 将 admin.json 中的账户迁移到 users 表
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 引入必要文件
require_once __DIR__ . '/../app/functions.php';

echo "========================================\n";
echo "JefCounts 管理员账户迁移工具\n";
echo "========================================\n\n";

try {
    // 获取数据库连接
    $pdo = getDbConnection();
    
    // 检查 admin.json 是否存在
    $adminConfigFile = __DIR__ . '/../app/admin.json';
    
    if (!file_exists($adminConfigFile)) {
        echo "❌ admin.json 文件不存在\n";
        echo "说明：系统可能还未安装，或使用新的用户系统。\n";
        exit(1);
    }
    
    // 读取 admin.json
    $adminConfig = getAdminConfig();
    
    if (empty($adminConfig)) {
        echo "❌ 无法读取 admin.json 或文件内容为空\n";
        exit(1);
    }
    
    echo "✅ 找到 admin.json 文件\n";
    echo "用户名：{$adminConfig['username']}\n";
    echo "创建时间：{$adminConfig['created_at']}\n\n";
    
    // 检查 users 表是否已存在
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE username = '{$adminConfig['username']}'");
    $exists = (int) $stmt->fetchColumn() > 0;
    
    if ($exists) {
        echo "ℹ️  用户 {$adminConfig['username']} 已存在于 users 表中\n";
        echo "是否需要更新密码？(y/n): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        
        if (trim(strtolower($line)) === 'y') {
            // 更新密码
            $stmt = $pdo->prepare("UPDATE users SET password_hash = :password_hash WHERE username = :username");
            $stmt->execute([
                'password_hash' => $adminConfig['password'],
                'username' => $adminConfig['username']
            ]);
            echo "✅ 密码已更新到 users 表\n";
        } else {
            echo "⏭️  跳过密码更新\n";
        }
    } else {
        // 插入新用户
        echo "📝 将管理员账户插入 users 表...\n";
        
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password_hash, role, created_at, updated_at)
            VALUES (:username, :email, :password_hash, 'admin', :created_at, NOW())
        ");
        
        $stmt->execute([
            'username' => $adminConfig['username'],
            'email' => 'admin@localhost',
            'password_hash' => $adminConfig['password'],
            'created_at' => $adminConfig['created_at']
        ]);
        
        $userId = (int) $pdo->lastInsertId();
        echo "✅ 用户 {$adminConfig['username']} 已插入 users 表，ID: {$userId}\n";
    }
    
    // 迁移 sites 表的 user_id
    echo "\n📝 迁移站点归属权...\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM sites WHERE user_id = 0");
    $count = (int) $stmt->fetchColumn();
    
    if ($count > 0) {
        echo "发现 {$count} 个站点的 user_id 为 0，正在迁移...\n";
        
        $stmt = $pdo->prepare("UPDATE sites SET user_id = :user_id WHERE user_id = 0");
        $stmt->execute(['user_id' => $userId]);
        
        echo "✅ 已迁移 {$stmt->rowCount()} 个站点到用户 {$adminConfig['username']}\n";
    } else {
        echo "✅ 所有站点已有关联的用户\n";
    }
    
    // 迁移 user_preferences
    echo "\n📝 迁移用户偏好设置...\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM user_preferences WHERE username = '{$adminConfig['username']}'");
    $prefCount = (int) $stmt->fetchColumn();
    
    if ($prefCount > 0) {
        echo "✅ 用户偏好设置已存在\n";
    } else {
        echo "ℹ️  暂无用户偏好设置\n";
    }
    
    echo "\n========================================\n";
    echo "✅ 迁移完成！\n";
    echo "========================================\n\n";
    
    echo "后续步骤：\n";
    echo "1. 使用新登录页面 (/login.php) 测试登录\n";
    echo "2. 验证仪表板和站点管理功能\n";
    echo "3. （可选）删除或备份 admin.json 文件\n\n";
    
    echo "⚠️  注意：\n";
    echo "- admin.json 文件暂时保留以保证向后兼容\n";
    echo "- 建议在新系统稳定后再删除 admin.json\n\n";
    
} catch (Exception $e) {
    echo "\n❌ 迁移失败：" . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
?>
