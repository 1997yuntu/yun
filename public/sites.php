<?php
/**
 * 站点管理页面 - 多用户版本
 */

require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../vendor/autoload.php';

use JefCounts\Services\SitesService;
use JefCounts\Services\UserService;
use JefCounts\Services\SettingsService;

// 要求必须登录
requireLogin();

// 获取数据库连接
$pdo = getDbConnection();

// 初始化服务
$userService = new UserService($pdo);
$sitesService = new SitesService($pdo);
$settingsService = new SettingsService($pdo);

// 获取当前用户信息
$currentUser = $userService->getCurrentUser();
$currentUserId = $userService->getCurrentUserId();
$isAdmin = $userService->isAdmin();

$message = '';
$messageType = '';

// 处理 POST 请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $message = 'CSRF 验证失败，请刷新页面重试';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create':
                if ($isAdmin) {
                    $result = $sitesService->createSite($currentUserId, $_POST);
                } else {
                    // 检查普通用户的站点数量限制
                    $maxSites = $settingsService->getMaxSitesPerUser();
                    $userSites = $sitesService->getAllSites($currentUserId);
                    if (count($userSites) >= $maxSites) {
                        $result = [
                            'success' => false,
                            'message' => "您创建的站点数量已达到上限（{$maxSites}个）"
                        ];
                    } else {
                        $result = $sitesService->createSite($currentUserId, $_POST);
                    }
                }
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
                break;
                
            case 'delete':
                $siteId = (int) $_POST['id'];
                $result = $sitesService->deleteSite($currentUserId, $siteId);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
                break;
                
            case 'update':
                $siteId = (int) $_POST['id'];
                // 验证所有权
                if ($isAdmin || $userService->ownsSite($currentUserId, $siteId)) {
                    $result = $sitesService->updateSite($siteId, $_POST);
                    $message = $result['message'];
                    $messageType = $result['success'] ? 'success' : 'error';
                } else {
                    $message = '无权操作此站点';
                    $messageType = 'error';
                }
                break;
        }
        
        // 成功后重定向
        if ($messageType === 'success') {
            header('Location: /sites.php');
            exit;
        }
    }
}

// 获取站点列表（根据权限过滤）
$sites = $sitesService->getAllSites($currentUserId);

// 获取编辑站点信息
$editSite = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editSiteId = (int) $_GET['edit'];
    // 验证所有权
    if ($isAdmin || $userService->ownsSite($currentUserId, $editSiteId)) {
        $editSite = $sitesService->getSiteById($editSiteId);
    }
}

// 获取统计脚本代码示例
function getEmbedCode($siteKey, $domain) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $endpoint = "{$protocol}://{$host}/api/track.php";
    
    return <<<HTML
<script 
    src="{$protocol}://{$host}/assets/js/analytics.js" 
    defer 
    data-site-key="{$siteKey}"
    data-endpoint="{$endpoint}">
</script>
HTML;
}

$currentPage = 'sites';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>站点管理 - <?= APP_NAME ?></title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="stylesheet" href="<?= assetVersion('assets/css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= assetVersion('assets/css/sites.css') ?>">
    <style>
        .site-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .site-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        .site-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        .site-domain {
            color: #666;
            font-size: 14px;
        }
        .site-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 15px 0;
        }
        .stat-item {
            text-align: center;
        }
        .stat-value {
            font-size: 24px;
            font-weight: 600;
            color: #4a90e2;
        }
        .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .site-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }
        .embed-code {
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 12px;
            font-family: monospace;
            font-size: 12px;
            margin-top: 10px;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .btn-copy {
            background: #4a90e2;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            margin-top: 5px;
        }
        .btn-copy:hover {
            background: #357abd;
        }
        .alert {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            border-radius: 8px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        .modal-header {
            margin-bottom: 20px;
        }
        .modal-title {
            font-size: 20px;
            font-weight: 600;
        }
        .modal-close {
            float: right;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        .modal-close:hover {
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../views/_header.php'; ?>
    
    <div class="main-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h1>站点管理</h1>
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="bi bi-plus-lg"></i> 添加站点
            </button>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <?= e($message) ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($sites)): ?>
            <div style="text-align: center; padding: 60px 20px; background: #f9f9f9; border-radius: 8px;">
                <i class="bi bi-globe" style="font-size: 48px; color: #ccc;"></i>
                <h3 style="margin: 20px 0; color: #666;">暂无站点</h3>
                <p style="color: #999;">点击右上角按钮添加您的第一个网站</p>
                <button class="btn btn-primary" style="margin-top: 20px;" onclick="openAddModal()">
                    <i class="bi bi-plus-lg"></i> 添加站点
                </button>
            </div>
        <?php else: ?>
            <div class="sites-grid">
                <?php foreach ($sites as $site): ?>
                    <div class="site-card">
                        <div class="site-header">
                            <div>
                                <div class="site-name"><?= e($site['name']) ?></div>
                                <div class="site-domain"><?= e($site['domain']) ?></div>
                            </div>
                            <span class="badge badge-<?= $site['status'] ?>">
                                <?= $site['status'] === 'active' ? '活跃' : '停用' ?>
                            </span>
                        </div>
                        
                        <div class="site-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?= number_format($site['total_pageviews'] ?? 0) ?></div>
                                <div class="stat-label">总访问量</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?= number_format($site['unique_visitors'] ?? 0) ?></div>
                                <div class="stat-label">独立访客</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?= $site['last_visit'] ? date('m-d', strtotime($site['last_visit'])) : '-' ?></div>
                                <div class="stat-label">最后访问</div>
                            </div>
                        </div>
                        
                        <div class="site-actions">
                            <a href="/dashboard.php?site=<?= $site['id'] ?>" class="btn btn-sm">
                                <i class="bi bi-speedometer2"></i> 查看统计
                            </a>
                            <button class="btn btn-sm" onclick="showEmbedCode('<?= e($site['site_key']) ?>', '<?= e($site['domain']) ?>')">
                                <i class="bi bi-code-slash"></i> 统计代码
                            </button>
                            <button class="btn btn-sm" onclick="openEditModal(<?= $site['id'] ?>, '<?= e($site['name']) ?>', '<?= e($site['domain']) ?>', '<?= e($site['description'] ?? '') ?>')">
                                <i class="bi bi-pencil"></i> 编辑
                            </button>
                            <?php if ($isAdmin || $site['site_key'] !== 'default'): ?>
                                <button class="btn btn-sm btn-danger" onclick="deleteSite(<?= $site['id'] ?>, '<?= e($site['name']) ?>')">
                                    <i class="bi bi-trash"></i> 删除
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- 添加站点 Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <button class="modal-close" onclick="closeModal('addModal')">&times;</button>
                <h2 class="modal-title">添加新站点</h2>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label>站点名称 *</label>
                    <input type="text" name="name" required placeholder="例如：我的博客">
                </div>
                
                <div class="form-group">
                    <label>域名 *</label>
                    <input type="text" name="domain" required placeholder="例如：example.com">
                    <small style="color: #666; font-size: 12px;">不需要 http:// 或 https:// 前缀</small>
                </div>
                
                <div class="form-group">
                    <label>描述</label>
                    <textarea name="description" rows="3" placeholder="站点描述（可选）"></textarea>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn" onclick="closeModal('addModal')">取消</button>
                    <button type="submit" class="btn btn-primary">创建站点</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- 编辑站点 Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
                <h2 class="modal-title">编辑站点</h2>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editSiteId">
                
                <div class="form-group">
                    <label>站点名称 *</label>
                    <input type="text" name="name" id="editSiteName" required>
                </div>
                
                <div class="form-group">
                    <label>域名 *</label>
                    <input type="text" name="domain" id="editSiteDomain" required>
                </div>
                
                <div class="form-group">
                    <label>描述</label>
                    <textarea name="description" id="editSiteDescription" rows="3"></textarea>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn" onclick="closeModal('editModal')">取消</button>
                    <button type="submit" class="btn btn-primary">保存更改</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- 统计代码 Modal -->
    <div id="embedModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <button class="modal-close" onclick="closeModal('embedModal')">&times;</button>
                <h2 class="modal-title">统计代码</h2>
            </div>
            <p style="color: #666; margin-bottom: 15px;">将以下代码添加到您的网站页面 &lt;/body&gt; 标签之前：</p>
            <div class="embed-code" id="embedCodeDisplay"></div>
            <button class="btn-copy" onclick="copyEmbedCode()">
                <i class="bi bi-clipboard"></i> 复制代码
            </button>
        </div>
    </div>
    
    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('show');
        }
        
        function openEditModal(id, name, domain, description) {
            document.getElementById('editSiteId').value = id;
            document.getElementById('editSiteName').value = name;
            document.getElementById('editSiteDomain').value = domain;
            document.getElementById('editSiteDescription').value = description;
            document.getElementById('editModal').classList.add('show');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }
        
        function showEmbedCode(siteKey, domain) {
            const protocol = window.location.protocol;
            const host = window.location.host;
            const endpoint = `${protocol}//${host}/api/track.php`;
            
            const code = `<script 
    src="${protocol}//${host}/assets/js/analytics.js" 
    defer 
    data-site-key="${siteKey}"
    data-endpoint="${endpoint}">
</script>`;
            
            document.getElementById('embedCodeDisplay').textContent = code;
            document.getElementById('embedModal').classList.add('show');
        }
        
        function copyEmbedCode() {
            const code = document.getElementById('embedCodeDisplay').textContent;
            navigator.clipboard.writeText(code).then(() => {
                alert('代码已复制到剪贴板');
            });
        }
        
        function deleteSite(id, name) {
            if (confirm(`确定要删除站点 "${name}" 吗？\n\n删除后将无法恢复，相关统计数据也会被删除。`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // 点击 Modal 外部关闭
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }
    </script>
</body>
</html>
