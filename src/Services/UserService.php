<?php
/**
 * 用户服务类
 *
 * 处理用户注册、登录、认证、资料管理等业务逻辑
 *
 * @package JefCounts\Services
 */

namespace JefCounts\Services;

use PDO;
use DateTime;

class UserService
{
    /**
     * @var PDO 数据库连接对象
     */
    private $db;

    /**
     * @var int 最大登录失败次数
     */
    const MAX_LOGIN_ATTEMPTS = 5;

    /**
     * @var int 账户锁定时间（秒）
     */
    const LOCK_DURATION = 900; // 15 分钟

    /**
     * 构造函数
     *
     * @param PDO $db 数据库连接对象
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * 根据用户名查询用户
     *
     * @param string $username 用户名
     * @return array|null 用户信息或 null
     */
    public function getUserByUsername($username)
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * 根据邮箱查询用户
     *
     * @param string $email 邮箱
     * @return array|null 用户信息或 null
     */
    public function getUserByEmail($email)
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * 根据 ID 查询用户
     *
     * @param int $userId 用户 ID
     * @return array|null 用户信息或 null
     */
    public function getUserById($userId)
    {
        $stmt = $this->db->prepare("SELECT id, username, email, role, last_login_at, last_login_ip, created_at FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * 检查用户名是否已存在
     *
     * @param string $username 用户名
     * @return bool 是否存在
     */
    public function isUsernameTaken($username)
    {
        return $this->getUserByUsername($username) !== null;
    }

    /**
     * 检查邮箱是否已注册
     *
     * @param string $email 邮箱
     * @return bool 是否已注册
     */
    public function isEmailRegistered($email)
    {
        return $this->getUserByEmail($email) !== null;
    }

    /**
     * 用户注册
     *
     * @param string $username 用户名
     * @param string $email 邮箱
     * @param string $password 密码
     * @return array ['success' => bool, 'user_id' => int|null, 'message' => string]
     */
    public function register($username, $email, $password)
    {
        try {
            // 验证用户名唯一性
            if ($this->isUsernameTaken($username)) {
                return [
                    'success' => false,
                    'user_id' => null,
                    'message' => '用户名已被使用'
                ];
            }

            // 验证邮箱唯一性
            if ($this->isEmailRegistered($email)) {
                return [
                    'success' => false,
                    'user_id' => null,
                    'message' => '该邮箱已注册账户'
                ];
            }

            // 加密密码
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // 插入用户
            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, password_hash, role) 
                VALUES (:username, :email, :password_hash, 'user')
            ");
            
            $stmt->execute([
                'username' => $username,
                'email' => $email,
                'password_hash' => $passwordHash
            ]);

            $userId = (int) $this->db->lastInsertId();

            return [
                'success' => true,
                'user_id' => $userId,
                'message' => '注册成功'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'user_id' => null,
                'message' => '注册失败：' . $e->getMessage()
            ];
        }
    }

    /**
     * 检查账户是否被登录锁定
     *
     * @param int $userId 用户 ID
     * @return bool 是否被锁定
     */
    public function isAccountLocked($userId)
    {
        $user = $this->getUserById($userId);
        if (!$user) {
            return false;
        }

        if ($user['locked_until'] === null) {
            return false;
        }

        $lockedUntil = new DateTime($user['locked_until']);
        $now = new DateTime();

        // 如果锁定时间已过，解锁账户
        if ($now >= $lockedUntil) {
            $this->resetLoginAttempts($userId);
            return false;
        }

        return true;
    }

    /**
     * 用户登录
     *
     * @param string $username 用户名
     * @param string $password 密码
     * @param bool $remember 是否记住登录状态（7 天）
     * @param string $ip 登录 IP
     * @return array ['success' => bool, 'message' => string, 'user' => array|null]
     */
    public function login($username, $password, $remember = false, $ip = null)
    {
        try {
            // 查询用户
            $user = $this->getUserByUsername($username);
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => '用户名或密码错误',
                    'user' => null
                ];
            }

            // 检查账户是否被锁定
            if ($this->isAccountLocked($user['id'])) {
                return [
                    'success' => false,
                    'message' => '账户已锁定，请 15 分钟后再试',
                    'user' => null
                ];
            }

            // 验证密码
            if (!password_verify($password, $user['password_hash'])) {
                // 密码错误，增加失败次数
                $this->incrementLoginAttempts($user['id']);
                
                return [
                    'success' => false,
                    'message' => '用户名或密码错误',
                    'user' => null
                ];
            }

            // 登录成功，重置失败次数
            $this->resetLoginAttempts($user['id']);

            // 更新最后登录信息
            $this->updateLastLogin($user['id'], $ip);

            // 创建 Session
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['login_time'] = time();
            
            // 如果选择记住登录，设置 7 天 Cookie
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expires = time() + (7 * 24 * 60 * 60); // 7 天
                
                // 将来可以将 token 存入数据库实现持久化登录
                setcookie('remember_token', $token, $expires, '/', '', false, true);
            }

            return [
                'success' => true,
                'message' => '登录成功',
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '登录失败：' . $e->getMessage(),
                'user' => null
            ];
        }
    }

    /**
     * 退出登录
     *
     * @return void
     */
    public function logout()
    {
        session_start();
        
        // 清除所有 Session 数据
        $_SESSION = array();
        
        // 删除 Session Cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        // 销毁 Session
        session_destroy();
        
        // 删除记住登录的 Cookie
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }

    /**
     * 增加登录失败次数
     *
     * @param int $userId 用户 ID
     * @return void
     */
    private function incrementLoginAttempts($userId)
    {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET login_attempts = login_attempts + 1,
                locked_until = CASE 
                    WHEN login_attempts + 1 >= :max_attempts 
                    THEN DATE_ADD(NOW(), INTERVAL :lock_duration SECOND)
                    ELSE locked_until
                END
            WHERE id = :id
        ");
        
        $stmt->execute([
            'max_attempts' => self::MAX_LOGIN_ATTEMPTS,
            'lock_duration' => self::LOCK_DURATION,
            'id' => $userId
        ]);
    }

    /**
     * 重置登录失败次数
     *
     * @param int $userId 用户 ID
     * @return void
     */
    private function resetLoginAttempts($userId)
    {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET login_attempts = 0,
                locked_until = NULL
            WHERE id = :id
        ");
        
        $stmt->execute(['id' => $userId]);
    }

    /**
     * 更新最后登录信息
     *
     * @param int $userId 用户 ID
     * @param string|null $ip 登录 IP
     * @return void
     */
    public function updateLastLogin($userId, $ip = null)
    {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET last_login_at = NOW(),
                last_login_ip = :ip
            WHERE id = :id
        ");
        
        $stmt->execute([
            'ip' => $ip,
            'id' => $userId
        ]);
    }

    /**
     * 更新用户资料
     *
     * @param int $userId 用户 ID
     * @param array $data 用户资料数据
     * @return array ['success' => bool, 'message' => string]
     */
    public function updateProfile($userId, $data)
    {
        try {
            $updates = [];
            $params = ['id' => $userId];

            // 邮箱更新
            if (isset($data['email'])) {
                // 检查邮箱是否已被其他用户使用
                $existingUser = $this->getUserByEmail($data['email']);
                if ($existingUser && $existingUser['id'] !== $userId) {
                    return [
                        'success' => false,
                        'message' => '该邮箱已被其他账户使用'
                    ];
                }
                
                $updates[] = "email = :email";
                $params['email'] = $data['email'];
            }

            if (empty($updates)) {
                return [
                    'success' => false,
                    'message' => '没有需要更新的内容'
                ];
            }

            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return [
                'success' => true,
                'message' => '资料更新成功'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '更新失败：' . $e->getMessage()
            ];
        }
    }

    /**
     * 修改密码
     *
     * @param int $userId 用户 ID
     * @param string $oldPassword 原密码
     * @param string $newPassword 新密码
     * @return array ['success' => bool, 'message' => string]
     */
    public function changePassword($userId, $oldPassword, $newPassword)
    {
        try {
            // 查询当前用户
            $user = $this->getUserById($userId);
            
            // 由于 getUserById 不返回 password_hash，需要单独查询
            $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = :id");
            $stmt->execute(['id' => $userId]);
            $currentHash = $stmt->fetchColumn();

            // 验证原密码
            if (!password_verify($oldPassword, $currentHash)) {
                return [
                    'success' => false,
                    'message' => '原密码错误'
                ];
            }

            // 更新密码
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("
                UPDATE users 
                SET password_hash = :password_hash 
                WHERE id = :id
            ");
            $stmt->execute([
                'password_hash' => $newHash,
                'id' => $userId
            ]);

            return [
                'success' => true,
                'message' => '密码修改成功'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '修改失败：' . $e->getMessage()
            ];
        }
    }

    /**
     * 获取当前登录用户 ID
     *
     * @return int|null 用户 ID 或 null
     */
    public function getCurrentUserId()
    {
        session_start();
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    /**
     * 获取当前登录用户信息
     *
     * @return array|null 用户信息或 null
     */
    public function getCurrentUser()
    {
        session_start();
        
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        return $this->getUserById($_SESSION['user_id']);
    }

    /**
     * 检查用户是否已登录
     *
     * @return bool 是否已登录
     */
    public function isLoggedIn()
    {
        session_start();
        return isset($_SESSION['user_id']);
    }

    /**
     * 检查当前用户是否为管理员
     *
     * @return bool 是否为管理员
     */
    public function isAdmin()
    {
        session_start();
        
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        $user = $this->getUserById($_SESSION['user_id']);
        return $user && $user['role'] === 'admin';
    }

    /**
     * 验证用户的所有权（站点是否属于该用户）
     *
     * @param int $userId 用户 ID
     * @param int $siteId 站点 ID
     * @return bool 是否有所有权
     */
    public function ownsSite($userId, $siteId)
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM sites WHERE id = :id AND user_id = :user_id");
        $stmt->execute(['id' => $siteId, 'user_id' => $userId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
