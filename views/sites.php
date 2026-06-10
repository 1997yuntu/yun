<?php
/**
 * 站点管理页面
 */

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../src/Services/SitesService.php';

// 检查登录状态（session已在index.php中启动）
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: /?page=login');
    exit;
}

$sitesService = new SitesService($pdo);
$message = '';
$messageType = '';

// 处理POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF保护
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $message = 'CSRF验证失败，请刷新页面重试';
        $messageType = 'error';
        goto skip_post_processing;
    }
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $result = $sitesService->createSite($_POST);
            if ($result['success']) {
                $message = $result['message'];
                $messageType = 'success';
            } else {
                $message = implode(', ', $result['errors']);
                $messageType = 'error';
            }
            break;
            
        case 'update':
            $result = $sitesService->updateSite($_POST['id'], $_POST);
            if ($result['success']) {
                $message = $result['message'];
                $messageType = 'success';
            } else {
                $message = implode(', ', $result['errors']);
                $messageType = 'error';
            }
            break;
            
        case 'delete':
            $result = $sitesService->deleteSite($_POST['id']);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'error';
            break;
            
        case 'toggle_status':
            $result = $sitesService->toggleSiteStatus($_POST['id']);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'error';
            break;
    }
    
    skip_post_processing:
    // POST 请求后重定向，避免刷新时重复提交表单
    if ($messageType !== 'error' || strpos($message, 'CSRF') === false) {
        header('Location: /?page=sites');
        exit;
    }
}

// 获取站点列表
$sites = $sitesService->getAllSites();

// 获取编辑站点信息
$editSite = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editSite = $sitesService->getSiteById($_GET['edit']);
}

// 获取来源站点（用于返回时保持选择状态）
$fromSite = $_GET['from_site'] ?? null;

$currentUser = getCurrentUser();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>站点管理 - <?= APP_NAME ?></title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link href="/assets/css/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link rel="stylesheet" href="<?= assetVersion('assets/css/sites.css') ?>">
</head>
<body>
    <div class="container">
        <!-- 头部 -->
        <div class="header">
            <div class="header-left">
                <h1><i class="bi bi-globe"></i> 站点管理</h1>
                <span class="user-info">欢迎，<?= e($currentUser['username']) ?></span>
            </div>
            <div class="header-right">
                <a href="/?page=dashboard<?= $fromSite ? '&site=' . e($fromSite) : '' ?>" class="btn-nav" title="仪表板">
                    <i class="bi bi-graph-up"></i>
                    <span class="btn-text">仪表板</span>
                </a>
                <a href="/?page=settings<?= $fromSite ? '&site=' . e($fromSite) : '' ?>" class="btn-nav" title="设置">
                    <i class="bi bi-gear"></i>
                    <span class="btn-text">设置</span>
                </a>

                <a href="/?logout=1" class="btn-logout" title="退出登录">
                    <i class="bi bi-box-arrow-right"></i>
                    <span class="btn-text">退出</span>
                </a>
            </div>
        </div>

        <div class="sites-container">
            <!-- 页面标题和操作 -->
            <div class="sites-header">
                <h2 class="sites-title">站点管理</h2>
                <button class="btn-add" onclick="showCreateModal()">
                    <i class="bi bi-plus-lg"></i> 添加站点
                </button>
            </div>

            <!-- 消息提示 -->
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>">
                    <i class="bi bi-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                    <?= e($message) ?>
                </div>
            <?php endif; ?>

            <!-- 站点列表 -->
            <?php if (!empty($sites)): ?>
                <div class="sites-list">
                    <?php foreach ($sites as $site): ?>
                        <div class="site-card <?= $site['status'] === 'active' ? '' : 'inactive' ?>">
                            <!-- 第一行：站点名称 + 统计指标 -->
                            <div class="site-row site-row-top">
                                <div class="cell cell-name">
                                    <span class="site-title">
                                        <?= e($site['name']) ?>
                                        <?php if ($site['site_key'] === 'default'): ?>
                                            <span class="demo-badge"><i class="bi bi-star-fill"></i> 演示</span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="site-status status-<?= $site['status'] ?>">
                                        <i class="bi bi-<?= $site['status'] === 'active' ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
                                        <?= $site['status'] === 'active' ? '活跃' : '停用' ?>
                                    </span>
                                </div>
                                <div class="cell cell-metric">
                                    <div class="metric-value"><?= formatNumber($site['total_pageviews']) ?></div>
                                    <div class="metric-label">总访问量</div>
                                </div>
                                <div class="cell cell-metric">
                                    <div class="metric-value"><?= formatNumber($site['unique_visitors']) ?></div>
                                    <div class="metric-label">独立访客</div>
                                </div>
                                <div class="cell cell-metric">
                                    <div class="metric-value"><?= $site['last_visit'] ? timeAgo($site['last_visit']) : '无' ?></div>
                                    <div class="metric-label">最后访问</div>
                                </div>
                                <div class="cell cell-metric">
                                    <div class="metric-value"><?= date('m-d', strtotime($site['created_at'])) ?></div>
                                    <div class="metric-label">创建时间</div>
                                </div>
                            </div>

                            <!-- 第二行：描述 + 操作按钮（柔和芯片风格） -->
                            <div class="site-row site-row-bottom">
                                <div class="cell cell-desc">
                                    <?php if ($site['site_key'] === 'default'): ?>
                                        <span class="desc-text demo-desc"><i class="bi bi-info-circle"></i> 演示站点 - 统计本站首页访问数据，此站点不可编辑或删除</span>
                                    <?php elseif (!empty($site['description'])): ?>
                                        <span class="desc-text"><i class="bi bi-card-text"></i> <?= e($site['description']) ?></span>
                                    <?php else: ?>
                                        <span class="desc-text muted">无描述</span>
                                    <?php endif; ?>
                                </div>
                                <div class="cell cell-actions">
                                    <a href="/?page=dashboard&site=<?= e($site['site_key']) ?>" class="btn-chip btn-chip-blue"><i class="bi bi-bar-chart"></i> 统计</a>
                                    <button class="btn-chip btn-chip-green" onclick="showTrackingCode('<?= e($site['site_key']) ?>', '<?= e($site['domain']) ?>')"><i class="bi bi-code-slash"></i> 代码</button>
                                    <?php if ($site['site_key'] === 'default'): ?>
                                        <button class="btn-chip btn-chip-disabled" disabled title="演示站点不可编辑"><i class="bi bi-lock"></i> 锁定</button>
                                    <?php else: ?>
                                        <button class="btn-chip btn-chip-gray" onclick="editSite(<?= $site['id'] ?>)"><i class="bi bi-pencil"></i> 编辑</button>
                                    <?php endif; ?>
                                    <?php if ($site['site_key'] !== 'default'): ?>
                                        <form method="POST" class="inline-form" onsubmit="return confirm('确定要删除这个站点吗？这将删除所有相关数据！')">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $site['id'] ?>">
                                            <button type="submit" class="btn-chip btn-chip-red"><i class="bi bi-trash"></i> 删除</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-globe"></i>
                    <h3>暂无站点</h3>
                    <p>点击上方"添加站点"按钮创建您的第一个站点</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 创建/编辑站点模态框 -->
    <div id="siteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle" class="modal-title">添加站点</h2>
                <button class="close-btn" onclick="closeSiteModal()">&times;</button>
            </div>
            <form id="siteForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="siteId">
                
                <div class="form-group">
                    <label class="form-label">站点名称 *</label>
                    <input type="text" name="name" id="siteName" class="form-input" required placeholder="请输入站点名称">
                </div>
                
                <div class="form-group">
                    <label class="form-label">域名 *</label>
                    <input type="text" name="domain" id="siteDomain" class="form-input" 
                           placeholder="请输入域名" required>
              
                </div>
                
                <div class="form-group">
                    <label class="form-label">描述</label>
                    <textarea name="description" id="siteDescription" class="form-textarea" 
                              placeholder="站点描述信息（可选）"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">状态</label>
                    <select name="status" id="siteStatus" class="form-select">
                        <option value="active">活跃</option>
                        <option value="inactive">停用</option>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-sm btn-secondary-sm" onclick="closeSiteModal()">取消</button>
                    <button type="submit" class="btn-sm btn-primary-sm">保存</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 跟踪代码模态框 -->
    <div id="trackingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">统计代码</h2>
                <button class="close-btn" onclick="closeTrackingModal()">&times;</button>
            </div>
            <p>将以下代码添加到您网站的 &lt;head&gt; 标签中：</p>
            <div class="tracking-code" id="trackingCode"></div>
            <div class="modal-actions">
                <button class="btn-sm btn-success-sm" onclick="copyTrackingCode()">
                    <i class="bi bi-clipboard"></i> 复制代码
                </button>
                <button class="btn-sm btn-secondary-sm" onclick="closeTrackingModal()">关闭</button>
            </div>
        </div>
    </div>

    <!-- 引入独立的JS文件 -->
    <script src="<?= assetVersion('assets/js/sites.js') ?>"></script>
    
    <?php if ($editSite): ?>
    <!-- 编辑模式：填充表单数据 -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('modalTitle').textContent = '编辑站点';
            document.getElementById('formAction').value = 'update';
            document.getElementById('siteId').value = '<?= $editSite['id'] ?>';
            document.getElementById('siteName').value = '<?= addslashes($editSite['name']) ?>';
            document.getElementById('siteDomain').value = '<?= addslashes($editSite['domain']) ?>';
            document.getElementById('siteDescription').value = '<?= addslashes($editSite['description']) ?>';
            document.getElementById('siteStatus').value = '<?= $editSite['status'] ?>';
            document.getElementById('siteModal').classList.add('show');
        });
    </script>
    <?php endif; ?>
</body>
</html>