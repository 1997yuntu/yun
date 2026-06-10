<?php
/**
 * 仪表板页面
 * 显示网站统计数据概览
 */

require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/auth.php';

// 要求必须登录
requireLogin();

$currentPage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>仪表板 - <?= APP_NAME ?></title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="stylesheet" href="<?= assetVersion('assets/css/dashboard.css') ?>">
</head>
<body>
    <?php include __DIR__ . '/../views/_header.php'; ?>
    
    <div class="main-content">
        <h1>仪表板</h1>
        <p>欢迎访问 <?= APP_NAME ?>！请选择要查看的站点。</p>
        
        <div style="margin-top: 30px; padding: 40px; background: #f9f9f9; border-radius: 8px; text-align: center;">
            <i class="bi bi-speedometer2" style="font-size: 48px; color: #4a90e2;"></i>
            <h2 style="margin: 20px 0;">数据统计仪表板</h2>
            <p style="color: #666;">此功能需要连接到统计服务，当前为演示页面。</p>
        </div>
    </div>
</body>
</html>
