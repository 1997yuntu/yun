/**
 * MVP统计系统 - 仪表板交互脚本
 * 动画效果、交互增强、分页功能
 */

(function() {
    'use strict';
    
    // 配置
    const CONFIG = {
        animateNumbers: true         // 是否启用数字动画
    };
    
    /**
     * 初始化
     */
    function init() {
        // 添加进度条动画
        animateProgressBars();
        
        // 添加表格行高亮
        setupTableHighlight();
    }
    
    /**
     * 进度条动画
     */
    function animateProgressBars() {
        const bars = document.querySelectorAll('.source-bar-fill');
        bars.forEach((bar, index) => {
            const targetWidth = bar.style.width;
            bar.style.width = '0%';
            
            setTimeout(() => {
                bar.style.width = targetWidth;
            }, 100 + (index * 100));
        });
    }
    
    /**
     * 表格行高亮
     */
    function setupTableHighlight() {
        const rows = document.querySelectorAll('tbody tr');
        rows.forEach(row => {
            row.addEventListener('click', function() {
                // 移除其他行的高亮
                rows.forEach(r => r.style.background = '');
                // 高亮当前行
                this.style.background = '#f0f7ff';
            });
        });
    }
    
    
    /**
     * 数字滚动动画（可选）
     */
    function animateNumber(element, target) {
        const duration = 1000;
        const start = 0;
        const increment = target / (duration / 16);
        let current = start;
        
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            element.textContent = Math.floor(current).toLocaleString();
        }, 16);
    }
    
    // 页面加载完成后初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
})();

/**
 * 分页功能 - 改进版，更稳定的参数传递
 */
class CardPagination {
    constructor() {
        // 从页面获取当前筛选状态
        this.currentFilters = this.getCurrentFilters();
        // 简单的内存缓存：key -> { data, pagination }
        this.cache = new Map();
        this.init();
    }
    
    /**
     * 从页面获取当前筛选条件
     */
    getCurrentFilters() {
        // 从URL参数获取当前筛选条件
        const urlParams = new URLSearchParams(window.location.search);
        return {
            site: urlParams.get('site') || window.currentSite || 'default',
            period: urlParams.get('period') || window.currentPeriod || 'today',
            customDate: urlParams.get('custom_date') || window.currentCustomDate || '',
            startDate: urlParams.get('start_date') || window.currentStartDate || '',
            endDate: urlParams.get('end_date') || window.currentEndDate || ''
        };
    }
    
    init() {
        // 绑定分页按钮点击事件
        document.addEventListener('click', (e) => {
            if (e.target.matches('.page-btn[data-page]')) {
                e.preventDefault();
                this.handlePageClick(e.target);
            }
        });
        
        // 监听筛选条件变化（当用户切换时间或站点时）
        this.watchFilterChanges();
    }
    
    /**
     * 监听筛选条件变化
     */
    watchFilterChanges() {
        // 监听URL变化
        window.addEventListener('popstate', () => {
            this.currentFilters = this.getCurrentFilters();
        });
        
        // 监听全局变量变化（如果有的话）
        if (window.currentSite !== undefined) {
            this.currentFilters.site = window.currentSite;
        }
        if (window.currentPeriod !== undefined) {
            this.currentFilters.period = window.currentPeriod;
        }
        if (window.currentCustomDate !== undefined) {
            this.currentFilters.customDate = window.currentCustomDate;
        }
    }
    
    async handlePageClick(button) {
        const pagination = button.closest('.card-pagination');
        const cardType = pagination.dataset.card;
        const page = parseInt(button.dataset.page);
        
        if (!cardType || !page) return;
        // 优先尝试读取缓存，命中则立即渲染，弱化卡顿体验
        // 同时后台仍会发起请求以刷新缓存
        
        try {
            // 重新获取最新的筛选条件（确保数据一致性）
            this.currentFilters = this.getCurrentFilters();
            
            // 构造请求参数 - 使用更稳定的参数传递
            const params = new URLSearchParams({
                card: cardType,  // 保持兼容性
                type: cardType,  // 新参数名
                page: page,
                period: this.currentFilters.period,
                site: this.currentFilters.site
            });
            
            // 添加日期参数
            if (this.currentFilters.customDate) {
                params.append('custom_date', this.currentFilters.customDate);
            }
            if (this.currentFilters.startDate) {
                params.append('start_date', this.currentFilters.startDate);
            }
            if (this.currentFilters.endDate) {
                params.append('end_date', this.currentFilters.endDate);
            }
            
            // 添加排序参数（仅对机器人统计有效）
            if (cardType === 'bots' && window.currentBotSort) {
                params.append('sort', window.currentBotSort);
            }

            // 计算缓存键
            const cacheKey = this.buildCacheKey(cardType, page, params);
            const cached = this.cache.get(cacheKey);

            if (cached) {
                // 使用缓存即时渲染，提升体验
                this.updateCardContent(cardType, cached.data, cached.pagination);
            } else {
                // 未命中缓存才显示加载遮罩（不清空表格内容）
                this.showLoading(pagination);
            }
            
            // 调试信息（开发时可用）
            console.log('分页请求参数:', {
                cardType,
                page,
                filters: this.currentFilters,
                url: `/api/pagination.php?${params}`
            });
            
            // 请求新数据（携带凭证，确保会话可用）
            const response = await fetch(`/api/pagination.php?${params}`, {
                credentials: 'same-origin'
            });
            
            // 获取响应文本用于调试
            const responseText = await response.text();
            
            // 尝试解析JSON
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON解析失败，响应状态:', response.status, response.statusText);
                console.error('JSON解析失败，响应内容:', responseText.substring(0, 500));
                const statusMsg = `HTTP ${response.status}: ${response.statusText}`;
                throw new Error(`${statusMsg} - 服务器未返回JSON`);
            }
            
            if (result.success) {
                // 更新卡片内容
                this.updateCardContent(cardType, result.data, result.pagination);
                // 更新缓存
                this.cache.set(cacheKey, { data: result.data, pagination: result.pagination });
                // 预取下一页（若存在）
                this.prefetchNextPage(cardType, result.pagination, params);
            } else {
                console.error('分页请求失败:', result.error);
                this.showError(pagination, result.error);
            }
            
        } catch (error) {
            console.error('分页请求错误:', error);
            this.showError(pagination, error.message || '网络请求失败');
        }
    }
    
    showLoading(pagination) {
        const card = pagination.closest('.card');
        if (!card) return;
        // 添加覆盖式加载遮罩，避免清空表格造成抖动
        let overlay = card.querySelector('.loading-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'loading-overlay';
            overlay.innerHTML = '<div class="loading-content"><i class="bi bi-arrow-clockwise spin"></i><span> 加载中...</span></div>';
            card.appendChild(overlay);
        }
        overlay.style.display = 'flex';
        
        // 禁用分页按钮
        pagination.querySelectorAll('.page-btn').forEach(btn => {
            btn.disabled = true;
        });
    }
    
    showError(pagination, message) {
        const card = pagination.closest('.card');
        // 隐藏加载遮罩
        const overlay = card.querySelector('.loading-overlay');
        if (overlay) overlay.style.display = 'none';
        
        const table = card.querySelector('table tbody');
        if (table) {
            table.innerHTML = `<tr><td colspan="100%" style="text-align: center; padding: 20px; color: #ef4444;">加载失败: ${message}</td></tr>`;
        }
        
        // 恢复分页按钮
        pagination.querySelectorAll('.page-btn').forEach(btn => {
            btn.disabled = false;
        });
    }
    
    updateCardContent(cardType, data, paginationInfo) {
        // 通过分页控件找到对应的卡片
        const paginationEl = document.querySelector(`[data-card="${cardType}"]`);
        if (!paginationEl) {
            console.error('找不到分页控件:', cardType);
            return;
        }
        
        const card = paginationEl.closest('.card');
        const table = card.querySelector('table tbody');
        
        if (!table) {
            console.error('找不到表格:', cardType);
            return;
        }
        
        // 更新表格内容
        table.innerHTML = this.renderTableRows(cardType, data);
        
        // 更新分页控件（仅替换控件HTML，避免其它DOM干扰）
        paginationEl.outerHTML = this.renderPagination(paginationInfo, cardType);
        
        // 隐藏加载遮罩
        const overlay = card.querySelector('.loading-overlay');
        if (overlay) overlay.style.display = 'none';
    }
    
    renderTableRows(cardType, data) {
        if (!data || data.length === 0) {
            return '<tr><td colspan="100%" style="text-align: center; padding: 20px; color: #64748b;">暂无数据</td></tr>';
        }
        
        let html = '';
        
        switch (cardType) {
            case 'sources':
                data.forEach(source => {
                    const sourceUrl = source.source_url === '直接访问' 
                        ? '<span class="direct-visit">直接访问</span>'
                        : `<a href="${source.source_url}" target="_blank" rel="noopener" title="${source.source_url}">${this.truncateUrl(source.source_url)}</a>`;
                    
                    html += `
                        <tr>
                            <td class="source-url">${sourceUrl}</td>
                            <td><span class="source-type-badge">${source.source_type}</span></td>
                            <td>${this.formatNumber(source.visits)}</td>
                            <td>${source.percentage}%</td>
                            <td>${this.timeAgo(source.last_visit)}</td>
                        </tr>
                    `;
                });
                break;
                
            case 'pages':
                data.forEach(page => {
                    // 解析URL，提取域名+路径
                    let displayUrl = page.page_url;
                    try {
                        const url = new URL(page.page_url);
                        displayUrl = url.hostname + url.pathname;
                    } catch (e) {
                        // 如果无法解析（相对路径），保持原样
                        displayUrl = page.page_url;
                    }
                    
                    html += `
                        <tr>
                            <td class="page-url-cell">
                                <a href="${this.escapeHtml(page.page_url)}" target="_blank" rel="noopener" title="${this.escapeHtml(page.page_url)}">
                                    ${this.escapeHtml(displayUrl)}
                                </a>
                            </td>
                            <td>${this.formatNumber(page.visits)}</td>
                            <td>${page.percentage}%</td>
                        </tr>
                    `;
                });
                break;
                
            case 'regions':
                data.forEach(region => {
                    html += `
                        <tr>
                            <td>${region.region}</td>
                            <td>${this.formatNumber(region.visits)}</td>
                            <td>${this.formatNumber(region.unique_ips)}</td>
                            <td>${region.percentage}%</td>
                            <td>${this.timeAgo(region.last_visit)}</td>
                        </tr>
                    `;
                });
                break;
                
            case 'clients':
                data.forEach(client => {
                    // 简单的浏览器解析（前端版本）
                    const browserName = this.parseBrowserName(client.user_agent);
                    html += `
                        <tr>
                            <td title="${client.user_agent}">${browserName}</td>
                            <td>${this.formatNumber(client.visits)}</td>
                            <td>${client.percentage}%</td>
                        </tr>
                    `;
                });
                break;
                
            case 'ips':
                data.forEach(ip => {
                    html += `
                        <tr>
                            <td>${this.escapeHtml(ip.ip)}</td>
                            <td>${this.escapeHtml(ip.region || '未知地区')}</td>
                            <td>${this.formatNumber(ip.visits)}</td>
                            <td>${this.timeAgo(ip.last_visit)}</td>
                            <td>${this.escapeHtml(ip.main_source)}</td>
                        </tr>
                    `;
                });
                break;
                
            case 'bots':
                data.forEach(bot => {
                    const botTypeClass = `bot-type-${bot.bot_type}`;
                    const botIcon = this.getBotIcon(bot.bot_type);
                    const truncatedUA = bot.user_agent.length > 30 
                        ? bot.user_agent.substring(0, 30) + '...' 
                        : bot.user_agent;
                    
                    html += `
                        <tr>
                            <td class="bot-name">
                                <i class="bi ${botIcon}"></i>
                                ${bot.bot_name}
                            </td>
                            <td class="bot-ip">
                                <div class="ip-container">
                                    <span class="ip-text" title="点击复制IP地址">${bot.ip}</span>
                                    ${bot.unique_ips > 1 ? `
                                        <span class="ip-count expandable" 
                                              title="点击查看所有 ${bot.unique_ips} 个IP地址"
                                              onclick="toggleIpList(this)"
                                              data-all-ips="${this.escapeHtml(JSON.stringify(bot.all_ips || []))}">
                                            +${bot.unique_ips - 1}
                                        </span>
                                    ` : ''}
                                    <button class="copy-btn copy-ip" data-copy="${bot.ip}" title="复制主要IP地址">
                                        <i class="bi bi-copy"></i>
                                    </button>
                                </div>
                                ${bot.unique_ips > 1 ? `
                                    <div class="ip-list" style="display: none;">
                                        <div class="ip-list-header">所有IP地址：</div>
                                        <div class="ip-list-content">
                                            ${(bot.all_ips || []).map((ip, index) => `
                                                <div class="ip-item">
                                                    <span class="ip-text secondary" title="点击复制">${ip}</span>
                                                    ${index === 0 ? '<span class="ip-badge primary">主要</span>' : ''}
                                                    <button class="copy-btn copy-ip-small" data-copy="${ip}" title="复制此IP">
                                                        <i class="bi bi-copy"></i>
                                                    </button>
                                                </div>
                                            `).join('')}
                                        </div>
                                    </div>
                                ` : ''}
                            </td>
                            <td class="user-agent-cell">
                                <div class="ua-container">
                                    <span class="ua-preview" title="${bot.user_agent}">
                                        ${truncatedUA}
                                    </span>
                                    <div class="ua-actions">
                                        <button class="copy-btn copy-ua" data-copy="${bot.user_agent}" title="复制User-Agent">
                                            <i class="bi bi-copy"></i>
                                        </button>
                                        <button class="expand-btn" onclick="toggleUADetails(this)" title="查看完整User-Agent">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="ua-full" style="display: none;">
                                    <div class="ua-full-text">${bot.user_agent}</div>
                                    <button class="copy-btn copy-ua-full" data-copy="${bot.user_agent}" title="复制完整User-Agent">
                                        <i class="bi bi-clipboard"></i> 复制完整UA
                                    </button>
                                </div>
                            </td>
                            <td class="bot-type">
                                <span class="bot-type-badge ${botTypeClass}">
                                    ${this.getBotTypeName(bot.bot_type)}
                                </span>
                            </td>
                            <td class="visits">${this.formatNumber(bot.visits)}</td>
                            <td class="percentage">${bot.percentage}%</td>
                            <td class="last-visit">${this.timeAgo(bot.last_visit)}</td>
                        </tr>
                    `;
                });
                break;
                
            case 'os':
                data.forEach(os => {
                    html += `
                        <tr>
                            <td>${os.os_name}</td>
                            <td>${this.formatNumber(os.visits)}</td>
                            <td>${os.percentage}%</td>
                        </tr>
                    `;
                });
                break;
                
            case 'devices':
                data.forEach(device => {
                    let deviceIcon = 'bi-laptop';
                    let deviceName = '桌面';
                    
                    if (device.device_type === 'Mobile') {
                        deviceIcon = 'bi-phone';
                        deviceName = '手机';
                    } else if (device.device_type === 'Tablet') {
                        deviceIcon = 'bi-tablet';
                        deviceName = '平板';
                    }
                    
                    html += `
                        <tr>
                            <td>
                                <i class="bi ${deviceIcon}"></i> ${deviceName}
                            </td>
                            <td>${this.formatNumber(device.visits)}</td>
                            <td>${device.percentage}%</td>
                        </tr>
                    `;
                });
                break;
        }
        
        return html;
    }
    
    renderPagination(pagination, cardType) {
        if (pagination.total_pages <= 1) {
            return '';
        }
        
        const current = pagination.current_page;
        const total = pagination.total_pages;
        let html = `<div class="card-pagination" data-card="${cardType}">`;
        
        // 上一页按钮
        if (pagination.has_prev) {
            html += `<button class="page-btn page-prev" data-page="${current - 1}">‹</button>`;
        }
        
        // 页码显示逻辑
        html += '<div class="page-numbers">';
        
        if (total <= 7) {
            // 总页数少于等于7页，显示所有页码
            for (let i = 1; i <= total; i++) {
                const active = i === current ? ' active' : '';
                html += `<button class="page-btn page-num${active}" data-page="${i}">${i}</button>`;
            }
        } else {
            // 智能显示页码
            // 始终显示第1页
            const active1 = current === 1 ? ' active' : '';
            html += `<button class="page-btn page-num${active1}" data-page="1">1</button>`;
            
            if (current > 4) {
                html += '<span class="page-dots">...</span>';
            }
            
            // 显示当前页附近的页码
            const start = Math.max(2, current - 1);
            const end = Math.min(total - 1, current + 1);
            
            for (let i = start; i <= end; i++) {
                if (i > 1 && i < total) {
                    const active = i === current ? ' active' : '';
                    html += `<button class="page-btn page-num${active}" data-page="${i}">${i}</button>`;
                }
            }
            
            if (current < total - 3) {
                html += '<span class="page-dots">...</span>';
            }
            
            // 始终显示最后一页
            if (total > 1) {
                const activeLast = current === total ? ' active' : '';
                html += `<button class="page-btn page-num${activeLast}" data-page="${total}">${total}</button>`;
            }
        }
        
        html += '</div>';
        
        // 下一页按钮
        if (pagination.has_next) {
            html += `<button class="page-btn page-next" data-page="${current + 1}">›</button>`;
        }
        
        // 页码信息
        html += `<div class="page-info">${current}/${total}</div>`;
        
        html += '</div>';
        return html;
    }
    
    // 工具方法
    formatNumber(num) {
        return new Intl.NumberFormat('zh-CN').format(num);
    }
    
    parseBrowserName(userAgent) {
        if (!userAgent) return '未知浏览器';
        
        // 简单的浏览器识别
        if (userAgent.includes('Edg/')) return 'Edge';
        if (userAgent.includes('Chrome/')) return 'Chrome';
        if (userAgent.includes('Firefox/')) return 'Firefox';
        if (userAgent.includes('Safari/') && !userAgent.includes('Chrome/')) return 'Safari';
        if (userAgent.includes('Opera/') || userAgent.includes('OPR/')) return 'Opera';
        if (userAgent.includes('MSIE') || userAgent.includes('Trident/')) return 'IE';
        
        return '未知浏览器';
    }
    
    /**
     * 格式化日期时间（与后端PHP的formatDateTime()保持一致）
     */
    timeAgo(dateString) {
        if (!dateString) return '-';
        
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return '-';
        
        const now = new Date();
        const timestamp = date.getTime();
        const nowTimestamp = now.getTime();
        
        // 获取日期字符串（不含时间）
        const dateOnly = date.toISOString().split('T')[0];
        const todayOnly = now.toISOString().split('T')[0];
        
        // 如果是今天，只显示时间
        if (dateOnly === todayOnly) {
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            return `${hours}:${minutes}`;
        }
        
        // 如果是昨天
        const yesterday = new Date(now);
        yesterday.setDate(yesterday.getDate() - 1);
        const yesterdayOnly = yesterday.toISOString().split('T')[0];
        if (dateOnly === yesterdayOnly) {
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            return `昨天 ${hours}:${minutes}`;
        }
        
        // 如果是本年，显示月-日 时:分
        if (date.getFullYear() === now.getFullYear()) {
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            return `${month}-${day} ${hours}:${minutes}`;
        }
        
        // 其他情况显示完整日期时间
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${year}-${month}-${day} ${hours}:${minutes}`;
    }
    
    truncateUrl(url) {
        try {
            const parsed = new URL(url);
            const path = parsed.pathname + parsed.search;
            return parsed.hostname + (path.length > 30 ? path.substring(0, 30) + '...' : path);
        } catch {
            return url.length > 50 ? url.substring(0, 50) + '...' : url;
        }
    }
    
    getBotIcon(botType) {
        const icons = {
            'ai': 'bi-cpu',
            'search': 'bi-search',
            'social': 'bi-share',
            'seo': 'bi-graph-up',
            'tool': 'bi-tools',
            'unknown': 'bi-question-circle'
        };
        return icons[botType] || 'bi-robot';
    }
    
    getBotTypeName(botType) {
        const names = {
            'ai': 'AI爬虫',
            'search': '搜索引擎',
            'social': '社交媒体',
            'seo': 'SEO工具',
            'tool': '开发工具',
            'unknown': '未知爬虫'
        };
        return names[botType] || '未知';
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // 生成缓存键
    buildCacheKey(cardType, page, params) {
        // 只取稳定参数作为key，忽略非关键顺序
        const keyObj = {
            cardType,
            page,
            period: this.currentFilters.period,
            site: this.currentFilters.site,
            custom_date: this.currentFilters.customDate || '',
            start_date: this.currentFilters.startDate || '',
            end_date: this.currentFilters.endDate || '',
            sort: params.get('sort') || ''
        };
        return JSON.stringify(keyObj);
    }

    // 预取下一页
    async prefetchNextPage(cardType, paginationInfo, baseParams) {
        try {
            if (!paginationInfo || !paginationInfo.has_next) return;
            const nextPage = paginationInfo.current_page + 1;
            const params = new URLSearchParams(baseParams);
            params.set('page', nextPage);
            const cacheKey = this.buildCacheKey(cardType, nextPage, params);
            if (this.cache.has(cacheKey)) return; // 已缓存，无需预取
            const resp = await fetch(`/api/pagination.php?${params}`, { credentials: 'same-origin' });
            const txt = await resp.text();
            const json = JSON.parse(txt);
            if (json && json.success) {
                this.cache.set(cacheKey, { data: json.data, pagination: json.pagination });
            }
        } catch (e) {
            // 预取失败静默忽略
        }
    }
    
    /**
     * 公共方法：更新筛选条件
     * 当用户切换时间或站点时调用此方法
     */
    updateFilters(newFilters) {
        this.currentFilters = { ...this.currentFilters, ...newFilters };
        console.log('筛选条件已更新:', this.currentFilters);
    }
    
    /**
     * 公共方法：获取当前筛选条件
     */
    getFilters() {
        return { ...this.currentFilters };
    }
}

// 全局分页实例
let globalPagination = null;

// 初始化分页功能
document.addEventListener('DOMContentLoaded', () => {
    globalPagination = new CardPagination();
    
    // 将分页实例暴露到全局，方便其他脚本调用
    window.updatePaginationFilters = (filters) => {
        if (globalPagination) {
            globalPagination.updateFilters(filters);
        }
    };
});