<?php
class User {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    // 用户注册
    public function register($username, $email, $password, $captcha) {
        // 验证验证码
        if (!$this->verifyCaptcha($captcha)) {
            return ['success' => false, 'message' => '验证码错误'];
        }
        
        // 检查用户名和邮箱是否已存在
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => '用户名或邮箱已存在'];
        }
        
        // 创建用户
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, created_at) VALUES (?, ?, ?, datetime('now'))");
        if ($stmt->execute([$username, $email, $hashedPassword])) {
            return ['success' => true, 'message' => '注册成功'];
        }
        return ['success' => false, 'message' => '注册失败'];
    }
    
    // 用户登录
    public function login($username, $password, $captcha) {
        // 验证验证码
        if (!$this->verifyCaptcha($captcha)) {
            return ['success' => false, 'message' => '验证码错误'];
        }
        
        // 检查登录尝试次数
        if ($this->isLoginLocked($username)) {
            return ['success' => false, 'message' => '账户被锁定，请稍后再试'];
        }
        
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND status != 'banned'");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            // 检查是否被封禁
            if ($user['ban_until'] && strtotime($user['ban_until']) > time()) {
                return ['success' => false, 'message' => '账户已被封禁'];
            }
            
            // 生成登录token，强制其他设备下线
            $loginToken = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP, login_token = ? WHERE id = ?");
            $stmt->execute([$loginToken, $user['id']]);
            
            // 设置会话
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['login_token'] = $loginToken;
            $_SESSION['vip_level'] = $user['vip_level'];
            
            // 清除登录失败记录
            $this->clearLoginAttempts($username);
            
            return ['success' => true, 'message' => '登录成功', 'user' => $user];
        } else {
            $this->recordLoginAttempt($username);
            return ['success' => false, 'message' => '用户名或密码错误'];
        }
    }
    
    // 检查用户是否在线
    public function isUserOnline($userId) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT login_token FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user && isset($_SESSION['login_token']) && $_SESSION['login_token'] === $user['login_token'];
    }
    
    // 强制用户下线
    public function forceLogout($userId) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("UPDATE users SET login_token = NULL WHERE id = ?");
        $stmt->execute([$userId]);
    }
    
    // 获取用户信息
    public function getUserInfo($userId) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            SELECT u.*, 
                   GROUP_CONCAT(t.name) as titles,
                   GROUP_CONCAT(t.color) as title_colors
            FROM users u 
            LEFT JOIN user_titles ut ON u.id = ut.user_id 
            LEFT JOIN titles t ON ut.title_id = t.id 
            WHERE u.id = ? 
            GROUP BY u.id
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // 更新用户密码
    public function updatePassword($userId, $oldPassword, $newPassword) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (password_verify($oldPassword, $user['password'])) {
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 4]);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            return $stmt->execute([$hashedPassword, $userId]);
        }
        return false;
    }
    
    // 检查VIP状态
    public function isVip($userId) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT vip_level, vip_expire_date FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user['vip_level'] > 0 && (!$user['vip_expire_date'] || strtotime($user['vip_expire_date']) > time());
    }
    
    // 添加积分
    public function addPoints($userId, $points, $reason) {
        $pdo = $this->db->getConnection();
        
        // 添加积分记录
        $stmt = $pdo->prepare("INSERT INTO point_records (user_id, points, reason) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $points, $reason]);
        
        // 更新用户积分
        $stmt = $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?");
        $stmt->execute([$points, $userId]);
        
        // 检查是否升级
        $this->checkLevelUp($userId);
    }
    
    // 检查升级
    private function checkLevelUp($userId) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT points, level FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $newLevel = min(floor($user['points'] / POINTS_PER_LEVEL) + 1, MAX_LEVEL);
        if ($newLevel > $user['level']) {
            $stmt = $pdo->prepare("UPDATE users SET level = ? WHERE id = ?");
            $stmt->execute([$newLevel, $userId]);
        }
    }
    
    // 验证码验证
    private function verifyCaptcha($captcha) {
        // 这里应该集成真实的验证码服务
        return !empty($captcha);
    }
    
    // 记录登录尝试
    private function recordLoginAttempt($username) {
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = [];
        }
        $_SESSION['login_attempts'][$username] = ($_SESSION['login_attempts'][$username] ?? 0) + 1;
        $_SESSION['login_attempts_time'][$username] = time();
    }
    
    // 检查是否被锁定
    private function isLoginLocked($username) {
        if (!isset($_SESSION['login_attempts'][$username])) {
            return false;
        }
        
        $attempts = $_SESSION['login_attempts'][$username];
        $lastAttempt = $_SESSION['login_attempts_time'][$username] ?? 0;
        
        if ($attempts >= MAX_LOGIN_ATTEMPTS && (time() - $lastAttempt) < LOGIN_LOCKOUT_TIME) {
            return true;
        }
        
        return false;
    }
    
    // 清除登录尝试记录
    private function clearLoginAttempts($username) {
        unset($_SESSION['login_attempts'][$username]);
        unset($_SESSION['login_attempts_time'][$username]);
    }
    
    // 用户登出
    public function logout() {
        session_destroy();
        return true;
    }
}
?>
