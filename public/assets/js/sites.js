/**
 * 站点管理页面 JavaScript
 */

// 显示创建站点模态框
function showCreateModal() {
    document.getElementById('modalTitle').textContent = '添加站点';
    document.getElementById('formAction').value = 'create';
    document.getElementById('siteForm').reset();
    document.getElementById('siteId').value = '';
    document.getElementById('siteModal').classList.add('show');
}

// 编辑站点 - 使用 POST 重定向避免表单重新提交警告
function editSite(id) {
    // 使用 location.replace 避免浏览器后退时的表单重新提交提示
    window.location.replace('/?page=sites&edit=' + id);
}

// 关闭站点模态框
function closeSiteModal() {
    document.getElementById('siteModal').classList.remove('show');
}

// 显示跟踪代码
function showTrackingCode(siteKey, domain) {
    // 获取当前统计系统的域名
    const currentHost = window.location.host;
    const protocol = window.location.protocol;
    
    const code = `<!-- JefCounts 统计代码 -->
<script>
(function() {
    var script = document.createElement('script');
    script.src = '${protocol}//${currentHost}/assets/js/analytics.js';
    script.defer = true;
    script.setAttribute('data-site-key', '${siteKey}');
    script.setAttribute('data-endpoint', '${protocol}//${currentHost}/api/track.php');
    document.head.appendChild(script);
})();
<\/script>
<!-- JefCounts 统计代码结束 -->`;
    
    document.getElementById('trackingCode').textContent = code;
    document.getElementById('trackingModal').classList.add('show');
}

// 关闭跟踪代码模态框
function closeTrackingModal() {
    document.getElementById('trackingModal').classList.remove('show');
}

// 复制跟踪代码
function copyTrackingCode() {
    const code = document.getElementById('trackingCode').textContent;
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(code).then(() => {
            alert('代码已复制到剪贴板！');
        }).catch(err => {
            console.error('复制失败:', err);
            fallbackCopyTextToClipboard(code);
        });
    } else {
        fallbackCopyTextToClipboard(code);
    }
}

// 降级复制方案
function fallbackCopyTextToClipboard(text) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.top = '0';
    textArea.style.left = '0';
    textArea.style.width = '2em';
    textArea.style.height = '2em';
    textArea.style.padding = '0';
    textArea.style.border = 'none';
    textArea.style.outline = 'none';
    textArea.style.boxShadow = 'none';
    textArea.style.background = 'transparent';
    
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        const successful = document.execCommand('copy');
        if (successful) {
            alert('代码已复制到剪贴板！');
        } else {
            alert('复制失败，请手动复制');
        }
    } catch (err) {
        console.error('复制失败:', err);
        alert('复制失败，请手动复制');
    }
    
    document.body.removeChild(textArea);
}

// 注意：点击模态框外部不会关闭，只能通过点击X按钮或ESC键关闭
// 这样可以防止用户误操作导致输入的数据丢失

// ESC键关闭模态框
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeSiteModal();
        closeTrackingModal();
    }
});
