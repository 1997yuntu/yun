/**
 * MVP统计系统 - 前端追踪脚本 (轻量级增强版)
 * 特点：轻量、零依赖、快速加载、智能机器人检测
 * 使用：<script src="/public/assets/js/analytics.js" defer></script>
 */

(function() {
    'use strict';
    
    // 获取当前脚本标签的配置
    var currentScript = document.currentScript || (function() {
        var scripts = document.getElementsByTagName('script');
        return scripts[scripts.length - 1];
    })();
    
    // 配置项
    var config = {
        siteKey: currentScript ? currentScript.getAttribute('data-site-key') : 'default',
        endpoint: currentScript ? currentScript.getAttribute('data-endpoint') : 'api/track.php',
        timeout: 1500,              // 超时时间（毫秒）
        enableBotDetection: true    // 启用轻量级机器人检测
    };
    
    // 轻量级检测状态
    var detection = {
        score: 0,
        interactions: 0,
        start: Date.now()
    };
    
    /**
     * 超轻量级机器人检测（只检测最关键的）
     */
    function quickDetect() {
        var score = 0;
        
        // 最关键的检测项（按重要性排序）
        if (navigator.webdriver) score += 60;                           // WebDriver
        if (window.callPhantom || window._phantom) score += 60;         // PhantomJS  
        if (navigator.userAgent.indexOf('HeadlessChrome') > -1) score += 60; // 无头Chrome
        if (!navigator.languages || navigator.languages.length === 0) score += 30; // 缺少语言
        if (navigator.plugins.length === 0) score += 20;               // 无插件
        if (screen.width === 0 || screen.height === 0) score += 40;    // 异常屏幕
        
        return Math.min(score, 100);
    }
    
    /**
     * 轻量级交互跟踪
     */
    function trackInteraction() {
        detection.interactions++;
        if (detection.interactions > 3) {
            // 已经足够证明是真实用户，停止跟踪
            removeListeners();
        }
    }
    
    /**
     * 设置轻量级行为监听
     */
    function setupTracking() {
        if (!config.enableBotDetection) return;
        
        // 只监听最关键的交互
        document.addEventListener('mousemove', trackInteraction, { passive: true, once: false });
        document.addEventListener('click', trackInteraction, { passive: true });
        
        // 3秒后自动移除监听器
        setTimeout(removeListeners, 3000);
    }
    
    /**
     * 移除事件监听器
     */
    function removeListeners() {
        document.removeEventListener('mousemove', trackInteraction);
        document.removeEventListener('click', trackInteraction);
    }
    
    /**
     * 发送追踪数据（轻量级版本）
     */
    function track() {
        try {
            // 获取当前页面信息（支持URL参数传递完整来源）
            var referrer = document.referrer || '';
            
            // 检查URL参数中的来源信息
            var urlParams = new URLSearchParams(window.location.search);
            var utmSource = urlParams.get('utm_source');
            var refParam = urlParams.get('ref');
            
            // 优先级：URL参数 > document.referrer
            // 如果有URL参数传递的来源，优先使用（这样可以获取完整路径）
            if (refParam && refParam.startsWith('http')) {
                referrer = refParam; // 使用完整的ref参数
            } else if (utmSource) {
                referrer = 'utm_source=' + utmSource;
            }
            // 否则使用document.referrer（可能被浏览器截断）
            
            var data = {
                url: window.location.href,
                title: document.title || '',
                ref: referrer,
                site_key: config.siteKey || 'default'
            };
            
            // 调试模式：在控制台记录来源信息
            if (window.location.search.indexOf('debug=1') !== -1) {
                console.log('JefCounts Debug - Referrer Info:', {
                    'document.referrer': document.referrer,
                    'final_referrer': referrer,
                    'utm_source': utmSource,
                    'ref_param': refParam,
                    'current_url': window.location.href
                });
            }
            
            // 添加轻量级检测结果
            if (config.enableBotDetection) {
                var score = quickDetect();
                
                // 简单行为分析
                if (detection.interactions === 0 && Date.now() - detection.start > 1000) {
                    score += 25; // 无交互惩罚
                }
                
                // 只在可疑时添加检测数据（减少数据传输）
                if (score > 20 || detection.interactions === 0) {
                    data.bot_score = score;
                    data.interactions = detection.interactions;
                }
            }
            
            // 使用最快的发送方式（保持原有逻辑）
            if (navigator.sendBeacon) {
                var blob = new Blob(
                    [JSON.stringify(data)], 
                    { type: 'application/json' }
                );
                navigator.sendBeacon(config.endpoint, blob);
            } 
            else if (window.fetch) {
                fetch(config.endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data),
                    keepalive: true
                }).catch(function() {
                    // 静默失败，不影响用户体验
                });
            }
            else {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', config.endpoint, true);
                xhr.setRequestHeader('Content-Type', 'application/json');
                xhr.timeout = config.timeout;
                xhr.send(JSON.stringify(data));
            }
        } catch (e) {
            // 静默失败
        }
    }
    
    // 轻量级初始化
    function init() {
        setupTracking();
        
        // 延迟发送，给用户时间产生交互
        setTimeout(track, 800);
    }
    
    // 页面加载完成后初始化
    if (document.readyState === 'complete') {
        init();
    } else {
        window.addEventListener('load', init);
    }
    
    // 单页应用支持（监听URL变化）
    if (window.history && window.history.pushState) {
        var pushState = history.pushState;
        history.pushState = function() {
            pushState.apply(history, arguments);
            setTimeout(track, 100);  // 延迟100ms确保页面更新
        };
        
        window.addEventListener('popstate', function() {
            setTimeout(track, 100);
        });
    }
    
})();
