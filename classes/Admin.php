<?php
class Admin {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    // 管理员登录
    public function login($username, $password) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['is_main_admin'] = $admin['is_main'] ? 1 : 0;
            return ['success' => true, 'message' => '登录成功'];
        }
        
        return ['success' => false, 'message' => '用户名或密码错误'];
    }
    
    // 获取管理员信息
    public function getAdminInfo($adminId) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
        $stmt->execute([$adminId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // 添加管理员
    public function addAdmin($username, $password, $email = '') {
        $pdo = $this->db->getConnection();
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO admins (username, password, email, is_main, created_at) VALUES (?, ?, ?, 0, datetime('now'))");
        return $stmt->execute([$username, $hashedPassword, $email]);
    }
    
    // 删除管理员
    public function deleteAdmin($adminId) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ? AND is_main = 0");
        return $stmt->execute([$adminId]);
    }
    
    // 修改管理员密码
    public function changeAdminPassword($adminId, $newPassword) {
        $pdo = $this->db->getConnection();
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
        return $stmt->execute([$hashedPassword, $adminId]);
    }
    
    // 获取所有管理员
    public function getAllAdmins() {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT * FROM admins ORDER BY is_main DESC, created_at ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // 获取仪表板数据
    public function getDashboardData() {
        $pdo = $this->db->getConnection();
        
        $data = [];
        
        // 今日新增用户
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = DATE('now', 'localtime')");
        $stmt->execute();
        $data['new_users_today'] = $stmt->fetchColumn();
        
        // 今日AI导购对话
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM ai_chat_logs WHERE DATE(created_at) = DATE('now', 'localtime')");
        $stmt->execute();
        $data['ai_chats_today'] = $stmt->fetchColumn();
        
        // 今日对比次数
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM comparison_records WHERE DATE(comparison_date) = DATE('now', 'localtime')");
        $stmt->execute();
        $data['comparisons_today'] = $stmt->fetchColumn();
        
        // 今日聊天消息数
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM chat_messages WHERE DATE(created_at) = DATE('now', 'localtime')");
        $stmt->execute();
        $data['chat_messages_today'] = $stmt->fetchColumn();
        
        // 总用户数
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
        $stmt->execute();
        $data['total_users'] = $stmt->fetchColumn();
        
        return $data;
    }
    
    // 用户管理
    public function getAllUsers($limit = 50, $offset = 0) {
        $pdo = $this->db->getConnection();
        
        // SQLite的LIMIT和OFFSET需要使用整数类型
        $limit = (int)$limit;
        $offset = (int)$offset;
        
        $stmt = $pdo->prepare("
            SELECT u.*
            FROM users u
            ORDER BY u.id DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // 获取用户总数
    public function getTotalUsersCount() {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        return (int)$stmt->fetchColumn();
    }
    
    // 系统设置管理
    public function getSystemSettings() {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT * FROM system_settings");
        $stmt->execute();
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }
    
    // 更新系统设置
    public function updateSystemSetting($key, $value) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            INSERT OR REPLACE INTO system_settings (setting_key, setting_value, updated_at) 
            VALUES (?, ?, CURRENT_TIMESTAMP)
        ");
        return $stmt->execute([$key, $value]);
    }
    
    // 获取最后插入的ID
    public function getLastInsertId() {
        $pdo = $this->db->getConnection();
        return $pdo->lastInsertId();
    }
    
    // AI提示词管理
    public function getAllAIPrompts() {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT * FROM ai_prompts ORDER BY created_at DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // 添加AI提示词
    public function addAIPrompt($name, $prompt, $type) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("INSERT INTO ai_prompts (name, prompt, type) VALUES (?, ?, ?)");
        return $stmt->execute([$name, $prompt, $type]);
    }
    
    // 更新AI提示词
    public function updateAIPrompt($id, $name, $prompt, $type) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("UPDATE ai_prompts SET name = ?, prompt = ?, type = ? WHERE id = ?");
        return $stmt->execute([$name, $prompt, $type, $id]);
    }
    
    // 删除AI提示词
    public function deleteAIPrompt($id) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("DELETE FROM ai_prompts WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
}
?>
