/**
 * JefCounts MVP统计系统 - 首页交互脚本
 * 简单的首页动效和交互
 */

(function() {
    'use strict';
    
    /**
     * 初始化首页功能
     */
    function init() {
        // 添加统计数字动画
        animateNumbers();
        
        // 添加平滑滚动
        setupSmoothScroll();
        
        // 添加功能卡片动画
        setupCardAnimations();
    }
    
    /**
     * 统计数字动画
     */
    function animateNumbers() {
        const numbers = document.querySelectorAll('.demo-number');
        
        numbers.forEach(number => {
            const target = parseInt(number.textContent.replace(/,/g, ''));
            let current = 0;
            const increment = target / 50;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                number.textContent = Math.floor(current).toLocaleString();
            }, 30);
        });
    }
    
    /**
     * 平滑滚动
     */
    function setupSmoothScroll() {
        const links = document.querySelectorAll('a[href^="#"]');
        
        links.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }
    
    /**
     * 功能卡片动画
     */
    function setupCardAnimations() {
        const cards = document.querySelectorAll('.feature-card, .demo-stat');
        
        // 使用 Intersection Observer 实现滚动动画
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            });
            
            cards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(card);
            });
        }
    }
    
    // 页面加载完成后初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
})();
