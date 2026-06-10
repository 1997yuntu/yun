# Requirements Document

## Introduction

为 JefCounts 极简统计系统添加用户中心功能，支持用户自助注册登录，并为每个注册用户提供独立的网站统计功能。管理员可在后台控制用户注册开关。

## Glossary

- **系统**: JefCounts 极简统计系统
- **管理员**: 拥有系统最高权限的用户，可管理系统配置和所有用户
- **普通用户**: 通过注册功能创建的用户，可管理自己的网站统计数据
- **注册开关**: 管理员控制是否允许新用户注册的全局配置项

## Requirements

### Requirement 1: 用户注册功能

**User Story:** AS 访客，I want 自助注册账户，so that 可以使用系统统计自己的网站数据

#### Acceptance Criteria

1. 系统 SHALL 提供用户注册页面，包含用户名、邮箱、密码输入字段
2. 系统 SHALL 验证用户名唯一性，重复用户名 SHALL 返回错误提示
3. 系统 SHALL 验证邮箱格式有效性，邮箱格式错误 SHALL 返回提示
4. 系统 SHALL 验证密码强度，密码长度 SHALL 至少为 8 位
5. 系统 SHALL 对密码进行加密存储，使用 bcrypt 算法
6. 系统 SHALL 在注册成功后自动登录并跳转到仪表板
7. 系统 SHALL 在注册页面显示当前注册开关状态，关闭时不显示注册入口

### Requirement 2: 用户登录功能

**User Story:** AS 注册用户，I want 使用用户名和密码登录，so that 可以访问自己的网站统计数据

#### Acceptance Criteria

1. 系统 SHALL 提供用户登录页面，包含用户名和密码输入字段
2. 系统 SHALL 验证用户名和密码的正确性，验证失败 SHALL 返回错误提示
3. 系统 SHALL 在登录成功后创建 Session，Session 有效期为 2 小时
4. 系统 SHALL 在登录成功后跳转到用户仪表板
5. 系统 SHALL 支持记住登录状态功能（可选 7 天自动登录）
6. 系统 SHALL 对连续登录失败 5 次的账户锁定 15 分钟
7. 系统 SHALL 记录最近登录时间和登录 IP

### Requirement 3: 退出登录功能

**User Story:** AS 已登录用户，I want 安全退出登录，so that 保护账户安全

#### Acceptance Criteria

1. 系统 SHALL 在所有页面提供退出登录按钮
2. 用户点击退出按钮时，系统 SHALL 销毁 Session 并跳转到登录页面
3. 用户点击退出按钮后，系统 SHALL 清除所有认证相关 Cookies

### Requirement 4: 管理员控制注册开关

**User Story:** AS 管理员，I want 开启或关闭用户注册功能，so that 控制系统用户增长

#### Acceptance Criteria

1. 系统 SHALL 在管理员设置页面添加"允许用户注册"开关选项
2. 系统 SHALL 将注册开关状态持久化存储到 settings 表
3. 系统 SHALL 在注册开关关闭时隐藏所有注册入口
4. 系统 SHALL 在注册开关关闭时直接访问注册页面返回禁止访问错误

### Requirement 5: 用户网站管理功能

**User Story:** AS 注册用户，I want 添加和管理自己的网站，so that 可以统计多个网站的访问数据

#### Acceptance Criteria

1. 系统 SHALL 为每个用户提供独立的站点管理功能
2. 系统 SHALL 允许用户添加多个网站统计（上限 10 个）
3. 系统 SHALL 为每个网站生成唯一的 site_key 标识符
4. 系统 SHALL 显示网站的统计脚本代码供用户复制
5. 系统 SHALL 允许用户编辑网站名称和描述
6. 系统 SHALL 允许用户删除自己的网站及其所有统计数据

### Requirement 6: 用户仪表板功能

**User Story:** AS 已登录用户，I want 查看自己网站的统计仪表板，so that 了解网站访问情况

#### Acceptance Criteria

1. 系统 SHALL 为每个用户显示其名下所有网站的统计概览
2. 用户选择特定网站时，系统 SHALL 显示该网站的详细统计数据
3. 系统 SHALL 支持按时间范围筛选统计数据（今日、昨日、近 7 天、近 30 天）
4. 系统 SHALL 显示 PV、UV、来源、热门页面、地区、设备等统计维度

### Requirement 7: 用户个人设置功能

**User Story:** AS 注册用户，I want 修改个人信息和密码，so that 管理账户安全

#### Acceptance Criteria

1. 系统 SHALL 提供用户个人设置页面
2. 用户 SHALL 可以修改邮箱地址
3. 用户 SHALL 可以修改密码，修改时需要验证原密码
4. 系统 SHALL 在修改敏感信息前要求用户重新输入密码验证

### Requirement 8: 数据库扩展支持

**User Story:** AS 系统，I want 扩展数据库结构支持用户体系，so that 存储用户信息和权限

#### Acceptance Criteria

1. 系统 SHALL 创建 users 表，包含 id、username、email、password_hash、role、last_login_at、last_login_ip、created_at、updated_at 字段
2. 系统 SHALL 在 sites 表添加 user_id 外键字段，关联 users 表
3. 系统 SHALL 为 users 表的 username 和 email 字段创建唯一索引
4. 系统 SHALL 为 users 表的角色字段提供默认值"user"
5. 系统 SHALL 保留原有 admin 管理员账户体系，角色为"admin"
