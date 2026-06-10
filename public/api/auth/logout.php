<?php
/**
 * 用户退出 API
 *
 * GET/POST /api/auth/logout.php
 * 用户退出登录
 */

require_once __DIR__ . '/../../app/functions.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use JefCounts\Services\UserService;

// 获取数据库连接
$pdo = getDbConnection();

// 执行退出
$userService = new UserService($pdo);
$userService->logout();

// 重定向到登录页
header('Location: /login.php');
exit;
?>
