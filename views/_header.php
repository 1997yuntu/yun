<nav class="navbar">
    <div class="navbar-brand">
        <a href="/dashboard.php" style="display: flex; align-items: center; text-decoration: none; color: inherit;">
            <img src="/assets/images/logo.svg" alt="Logo" style="width: 32px; height: 32px;">
            <span style="font-size: 18px; font-weight: 600; margin-left: 8px;"><?= APP_NAME ?></span>
        </a>
    </div>
    
    <div class="navbar-menu">
        <a href="/dashboard.php" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i> 仪表板
        </a>
        <a href="/sites.php" class="nav-item <?= $currentPage === 'sites' ? 'active' : '' ?>">
            <i class="bi bi-globe"></i> 站点管理
        </a>
        <a href="/profile.php" class="nav-item <?= $currentPage === 'profile' ? 'active' : '' ?>">
            <i class="bi bi-person-circle"></i> 个人设置
        </a>
        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
        <a href="/?page=settings" class="nav-item <?= $currentPage === 'settings' ? 'active' : '' ?>">
            <i class="bi bi-gear"></i> 系统设置
        </a>
        <?php endif; ?>
    </div>
    
    <div class="navbar-user">
        <span class="user-name" title="<?= e($_SESSION['username'] ?? '') ?>">
            <i class="bi bi-person-circle"></i> 
            <span style="max-width: 100px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                <?= e($_SESSION['username'] ?? '') ?>
            </span>
        </span>
        <a href="/api/auth/logout.php" class="btn-logout" title="退出登录">
            <i class="bi bi-box-arrow-right"></i> <span class="btn-text">退出</span>
        </a>
    </div>
</nav>
