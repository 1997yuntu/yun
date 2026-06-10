# 用户中心功能 - 细节优化清单

## 🔧 必须修复的问题（Critical）

### 1. 缺少 e() 转义函数
**问题**: 代码中多处使用 `e()` 函数进行 HTML 转义，但 `functions.php` 中未定义

**修复**:
```php
// 添加到 app/functions.php
/**
 * HTML 转义输出
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
```

---

### 2. dashboard.php 和 sites.php 缺少权限验证
**问题**: 这两个页面还没有添加用户权限验证，任何人都可以直接访问

**修复**:
```php
// 在 dashboard.php 和 sites.php 顶部添加
require_once __DIR__ . '/../app/functions.php';
requireLogin();
```

---

### 3. 旧版 admin.json 密码迁移问题
**问题**: 现有系统的 admin.json 密码需要迁移到 users 表

**修复**: 创建迁移工具脚本
- 读取 admin.json 中的 password_hash
- 插入到 users 表
- 关联现有 sites 到 admin 用户

---

### 4. register.php 和 login.php 重复 session_start()
**问题**: 两个文件都调用了 session_start()，但 auth.php 中的函数也会调用

**修复**: 添加检查
```php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
```

---

## ⚠️ 重要改进（High Priority）

### 5. 登录页面缺少找回密码功能
**问题**: 用户忘记密码后无法找回

**建议**:
- 添加"忘记密码"链接
- 实现邮箱发送重置密码功能
- 需要配置 SMTP 或使用 mail() 函数

---

### 6. 注册缺少邮箱验证
**问题**: 用户可以随意使用他人邮箱注册

**建议**:
- 注册后发送验证邮件
- 添加 email_verified 字段
- 未验证邮箱限制部分功能

---

### 7. 密码强度检查不够严格
**问题**: 只检查长度（8 位），未要求复杂度

**建议**:
```php
// 增强密码验证
if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
    return '密码必须包含大小写字母和数字';
}
```

---

### 8. 用户设置页面缺少导航
**问题**: profile.php 使用了 `_header.php` 但可能未正确引入

**修复**: 确保所有页面都包含导航
```php
<?php include __DIR__ . '/../views/_header.php'; ?>
```

---

### 9. 站点管理页面未改造
**问题**: sites.php 还是旧版本，没有用户权限过滤

**需要改造**:
- 只显示当前用户的站点
- 添加"每用户最多站点数"限制检查
- 生成统计脚本时显示 site_key

---

### 10. 仪表板页面未适配
**问题**: dashboard.php 没有检查用户对 site_id 的所有权

**需要改造**:
- 验证用户只能查看自己的站点数据
- SitesService 查询时添加 user_id 条件

---

## 📝 用户体验改进（Medium Priority）

### 11. 添加加载状态提示
**问题**: 表单提交时没有加载动画

**建议**:
```javascript
// 在提交时显示 loading
button.innerHTML = '<i class="bi bi-hourglass-split"></i> 注册中...';
button.disabled = true;
```

---

### 12. 表单验证优化
**问题**: 前端验证可以更友好

**建议**:
- 实时验证用户名可用性（AJAX 检查）
- 邮箱输入时验证格式
- 密码强度实时显示

---

### 13. 错误提示优化
**问题**: 错误提示不够具体

**建议**:
```php
// 区分具体的错误原因
if ($this->isUsernameTaken($username)) {
    return '该用户名已被注册，试试其他名字吧';
}
if ($this->isEmailRegistered($email)) {
    return '该邮箱已注册账户，可以通过"忘记密码"找回';
}
```

---

### 14. 添加成功页面/提示
**问题**: 修改密码成功后没有足够反馈

**建议**:
- 显示绿色成功提示条
- 3 秒后自动消失
- 敏感操作（修改密码、邮箱）发送通知邮件

---

### 15. 密码修改确认后强制重新登录
**问题**: 修改密码后 Session 还保持登录状态

**安全风险**: 如果账户被盗，攻击者可以继续操作

**建议**:
```php
// 修改密码后销毁所有 Session
$userService->logout();
echo json_encode(['success' => true, 'message' => '密码修改成功，请重新登录']);
```

---

## 🛡️ 安全性增强（Security）

### 16. CSRF 保护不完善
**问题**: 只有设置页面有 CSRF Token，注册登录 API 没有

**建议**:
- 为所有 POST/PUT 请求添加 CSRF Token
- 使用双重提交 Cookie 模式
- API 通过 X-CSRF-Token header 验证

---

### 17. 缺乏速率限制（Rate Limit）
**问题**: 注册、登录 API 可以被滥用

**建议**:
```php
// 检查 IP 的最近请求次数
function checkRateLimit($action, $limit = 5, $period = 60) {
    $key = "rate_limit:{$action}:" . getRealIP();
    $count = $redis->get($key);
    if ($count >= $limit) {
        return false; // 超出限制
    }
    $redis->incr($key);
    $redis->expire($key, $period);
    return true;
}
```

---

### 18. 缺乏审计日志
**问题**: 敏感操作没有记录日志

**建议**:
- 记录所有登录尝试（成功/失败）
- 记录密码修改操作
- 记录站点删除操作

---

### 19. 用户枚举漏洞
**问题**: 可以通过错误信息判断用户名是否存在

**当前**:
```
用户名不存在 → "用户名或密码错误"  ❌
密码错误 → "用户名或密码错误"  ✅
```

**已经修复**: 登录 API 统一返回"用户名或密码错误"

---

### 20. Session 固定攻击风险
**问题**: login() 方法中应该重新生成 Session ID

**修复**:
```php
// 登录成功后重新生成 Session ID
session_regenerate_id(true);
```

---

## 🎨 UI/UX 改进（Nice to Have）

### 21. 响应式设计优化
**问题**: 移动端显示可能不够友好

**建议**:
- 检查手机端注册/登录表单显示
- 优化移动端导航菜单
- 添加触摸友好的按钮大小

---

### 22. 添加社交登录
**建议**:
- GitHub OAuth
- Google OAuth
- 微信登录（国内）

---

### 23. 添加双因素认证（2FA）
**建议**:
- TOTP（Google Authenticator）
- 短信验证
- 邮箱验证码

---

### 24. 用户头像
**建议**:
- 使用 Gravatar 头像
- 用户上传自定义头像
- 自动生成带字母的头像

---

## 📊 管理员功能增强

### 25. 用户管理页面
**缺失**: 管理员无法查看用户列表

**建议**:
- 用户列表（分页显示）
- 搜索用户（用户名/邮箱）
- 查看用户详情（站点数、注册时间、最后登录）
- 禁用/启用用户
- 重置用户密码

---

### 26. 统计信息
**建议**:
- 总用户数
- 今日新增用户
- 活跃用户数（最近 7 天登录）
- 平均每用户站点数

---

### 27. 批量操作
**建议**:
- 批量发送站内信/邮件
- 批量清理僵尸账户（长期未登录）
- 批量调整站点限额

---

## 🔍 代码质量改进

### 28. 统一错误处理
**问题**: 有些 API 返回 JSON，有些直接 echo

**建议**:
- 创建统一的 JSON 响应函数
- 统一错误码定义
- 添加异常处理器

---

### 29. 代码注释
**问题**: 部分代码缺少注释

**建议**:
- PHPDoc 格式注释
- 复杂逻辑添加注释
- 关键安全操作注释说明

---

### 30. 日志记录
**建议**:
```php
// 创建日志目录
mkdir -p /workspace/data/logs

// 添加日志函数
function logUserAction($userId, $action, $details) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'user_id' => $userId,
        'action' => $action,
        'details' => $details,
        'ip' => getRealIP()
    ];
    file_put_contents(
        'data/logs/user_actions.log',
        json_encode($logEntry) . PHP_EOL,
        FILE_APPEND
    );
}
```

---

## ✅ 立即可以完成的小优化

### 31. 添加输入自动完成属性
```html
<input type="text" autocomplete="username">
<input type="password" autocomplete="current-password">
```

### 32. 优化表单焦点
```css
input:focus {
    outline: none;
    border-color: #4a90e2;
    box-shadow: 0 0 0 3px rgba(74,144,226,0.1);
}
```

### 33. 添加页面标题
```html
<title><?= e($pageTitle) ?> - <?= APP_NAME ?></title>
```

### 34. 优化移动端 viewport
```html
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
```

### 35. 添加 favicon 链接
```html
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
```

---

## 📋 优先级排序

| 优先级 | 问题编号 | 预计工时 | 说明 |
|--------|----------|----------|------|
| 🔴 P0 | 1, 2, 3, 4 | 1 小时 | 必须修复，影响功能使用 |
| 🟠 P1 | 5, 9, 10 | 3 小时 | 重要功能缺失 |
| 🟡 P2 | 7, 8, 11, 12, 13 | 2 小时 | 用户体验改进 |
| 🟢 P3 | 其他 | 按需 | 锦上添花的功能 |

---

## 💡 推荐立即实施的优化组合

**15 分钟快速优化包**:
- ✅ 添加 e() 函数
- ✅ 修复 session_start() 重复调用
- ✅ 添加 loading 状态提示
- ✅ 优化错误提示信息

**2 小时用户体验包**:
- ✅ 找回密码功能
- ✅ 实时验证（用户名/邮箱可用性）
- ✅ 密码强度增强检查
- ✅ 修改密码后强制重新登录

**1 天完整功能包**:
- ✅ 改造 sites.php 和 dashboard.php
- ✅ 用户管理页面（管理员）
- ✅ 速率限制
- ✅ 审计日志
