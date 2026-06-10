<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>极简统计 - 轻量级网站统计系统</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="stylesheet" href="<?= assetVersion('assets/css/home.css') ?>">
</head>
<body>
    <div class="container">
        <!-- 英雄区域 -->
        <div class="hero">
            <h1>极简统计</h1>
            <p>轻量级网站统计系统<br>极简、快速、精准的访问数据分析</p>
            <a href="?page=login" class="btn-hero"><i class="bi bi-shield-lock"></i> 管理员登录</a>
        </div>
        
        <!-- 功能特点 -->
        <div class="feature-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="bi bi-lightning-charge"></i>
                </div>
                <div class="feature-title">极速加载</div>
                <div class="feature-desc">追踪脚本小于1KB，接口响应时间小于1ms，不影响网站性能</div>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="bi bi-bullseye"></i>
                </div>
                <div class="feature-title">精准统计</div>
                <div class="feature-desc">自动过滤机器人和爬虫，确保统计数据的真实性和准确性</div>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="bi bi-shield-check"></i>
                </div>
                <div class="feature-title">隐私安全</div>
                <div class="feature-desc">数据完全自主控制，不依赖第三方服务，保护用户隐私</div>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="bi bi-phone"></i>
                </div>
                <div class="feature-title">响应式设计</div>
                <div class="feature-desc">完美适配桌面和移动设备，随时随地查看统计数据</div>
            </div>
        </div>
        
        <!-- 实时统计演示 -->
        <div class="stats-demo">
            <h2><i class="bi bi-bar-chart"></i> 实时统计演示</h2>
            <div class="demo-grid">
                <div class="demo-stat">
                    <div class="demo-number">1,247</div>
                    <div class="demo-label">今日PV</div>
                </div>
                <div class="demo-stat">
                    <div class="demo-number">892</div>
                    <div class="demo-label">今日UV</div>
                </div>
                <div class="demo-stat">
                    <div class="demo-number">156</div>
                    <div class="demo-label">访客IP</div>
                </div>
                <div class="demo-stat">
                    <div class="demo-number">5</div>
                    <div class="demo-label">来源类型</div>
                </div>
            </div>
        </div>
        
        <!-- 内容区域 -->
        <div class="home-content">
            <!-- 核心功能 -->
            <div class="home-card">
                <h2><i class="bi bi-rocket-takeoff"></i> 核心功能</h2>
                <div class="feature-columns">
                    <div>
                        <h4><i class="bi bi-graph-up"></i> 基础统计</h4>
                        <ul>
                            <li>页面浏览量 (PV)</li>
                            <li>独立访客数 (UV)</li>
                            <li>访客IP列表</li>
                            <li>访问时间分析</li>
                        </ul>
                    </div>
                    <div>
                        <h4><i class="bi bi-search"></i> 来源分析</h4>
                        <ul>
                            <li>直接访问统计</li>
                            <li>搜索引擎来源</li>
                            <li>外链网站统计</li>
                            <li>社交媒体来源</li>
                        </ul>
                    </div>
                    <div>
                        <h4><i class="bi bi-file-text"></i> 页面统计</h4>
                        <ul>
                            <li>热门页面排行</li>
                            <li>页面访问次数</li>
                            <li>页面访问占比</li>
                            <li>实时数据更新</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- 使用说明 -->
            <div class="home-card">
                <h2><i class="bi bi-book"></i> 使用说明</h2>
                <div class="usage-guide">
                    <h4>1. 添加统计代码</h4>
                    <p>在需要统计的网页中添加以下代码：</p>
                    <code class="code-block">&lt;script src="YourName.com/assets/js/analytics.js" defer&gt;&lt;/script&gt;</code>
                    
                    <h4>2. 查看统计数据</h4>
                    <p>登录管理后台即可查看详细的访问统计数据和分析报告。</p>
                </div>
            </div>
        </div>
        
        <!-- 页脚 -->
        <div class="home-footer">
            <div class="footer-left">
                <p>&copy; 2025 极简统计. All rights reserved.</p>
            </div>
            <div class="footer-right">
                <p><a href="https://jesoo.org/" target="_blank" style="color: inherit; text-decoration: none;">@JesooTechLab</a></p>
            </div>
        </div>
    </div>
    
    <!-- 首页交互脚本 -->
    <script src="<?= assetVersion('assets/js/home.js') ?>" defer></script>
    <!-- 添加统计代码 - 统计首页访问数据到默认站点 -->
    <script src="<?= assetVersion('assets/js/analytics.js') ?>" defer data-site-key="default" data-endpoint="api/track.php"></script>
</body>
</html>