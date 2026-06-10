# JefCounts 极简统计

> 轻量级、高性能的网站访问统计系统

## ✨ 特性

- 🚀 **轻量高效** - 单文件统计，响应速度 <100ms，不影响网站加载
- 📊 **核心指标** - PV、UV、IP、来源分析、地区统计、设备统计
- 🤖 **智能过滤** - 自动识别并过滤AI爬虫、搜索引擎、社交媒体机器人
- 🌐 **多站点支持** - 统一管理多个网站的统计数据
- 🎨 **现代化界面** - 响应式布局，支持移动端
- 🔒 **注重安全** - CSRF保护、密码加密、SQL注入防护
- 📦 **极简部署** - 上传即用，5分钟完成安装

## 📋 系统要求

- **PHP**: 7.4 或更高版本
- **MySQL**: 5.7 或更高版本
- **扩展**: PDO、PDO_MySQL、JSON

## 🚀 快速安装

### 1. 上传文件

将项目文件上传到服务器

### 2. 设置运行目录

在宝塔面板（或其他控制面板）中，将网站运行目录设置为 `public`

### 3. 运行安装向导

访问您的域名，系统会自动跳转到安装向导：`http://your-domain.com/install/`

安装向导将引导您完成：
1. 环境检查
2. 数据库配置
3. 管理员账户设置

### 4. 开始使用

安装完成后：
- 登录管理后台
- 添加站点并获取统计代码
- 将统计代码添加到您的网站 `<head>` 标签中

## 📝 统计代码示例

```html
<!-- JefCounts 统计代码 -->
<script 
    src="https://your-stats-domain.com/assets/js/analytics.js" 
    defer 
    data-site-key="your-site-key"
    data-endpoint="https://your-stats-domain.com/api/track.php">
</script>
<!-- JefCounts 统计代码结束 -->
```

## 🔧 数据库配置

安装向导会自动生成 `app/config.php` 配置文件，包含以下配置：

```php
define('DB_HOST', 'localhost');      // 数据库主机
define('DB_NAME', 'jef_analytics');  // 数据库名称
define('DB_USER', 'your_username');  // 数据库用户名
define('DB_PASS', 'your_password');  // 数据库密码
```

如需手动修改，请编辑 `app/config.php` 文件。

## 🛠️ 主要功能

### 核心统计
- **PV/UV统计** - 页面浏览量和独立访客数
- **访问来源** - 详细的来源分析
- **热门页面** - 页面访问排行
- **地区统计** - 基于IP的地理位置分析
- **设备统计** - 浏览器、操作系统、设备类型

### 智能过滤
- 自动识别和过滤AI爬虫、搜索引擎机器人
- 真实用户数据分析

### 多站点管理
- 统一管理多个网站
- 数据完全隔离
- 独立的统计代码

## 🔒 安全建议

1. **使用强密码** - 设置复杂的数据库密码和管理员密码
2. **配置HTTPS** - 建议使用SSL证书
3. **定期备份** - 定期备份数据库数据

## 📮 联系方式

- **作者博客**：[https://www.jeffer.xyz/](https://www.jeffer.xyz/)
- **项目主页**：[https://www.jefcounts.com/](https://www.jefcounts.com/)

## 🙏 鸣谢

- IP数据库：[ip2region](https://github.com/lionsoul2014/ip2region)
- 图标库：[Bootstrap Icons](https://icons.getbootstrap.com)
