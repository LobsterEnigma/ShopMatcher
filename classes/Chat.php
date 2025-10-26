<?php
class Chat {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    // 发送消息
    public function sendMessage($userId, $message) {
        // 检查用户是否被禁言
        if ($this->isUserMuted($userId)) {
            return ['success' => false, 'message' => '您已被禁言'];
        }
        
        // 检查用户状态
        if ($this->isUserRestricted($userId)) {
            return ['success' => false, 'message' => '您的账户功能受限'];
        }
        
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("INSERT INTO chat_messages (user_id, message) VALUES (?, ?)");
        
        if ($stmt->execute([$userId, $message])) {
            // 添加积分
            $userObj = new User();
            $userObj->addPoints($userId, POINTS_CHAT_MESSAGE, '参与聊天');
            
            return ['success' => true, 'message' => '消息发送成功'];
        }
        
        return ['success' => false, 'message' => '消息发送失败'];
    }
    
    // 获取聊天消息
    public function getMessages($limit = 50, $offset = 0) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            SELECT cm.*, u.username
            FROM chat_messages cm
            LEFT JOIN users u ON cm.user_id = u.id
            ORDER BY cm.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    // 检查用户是否被禁言
    private function isUserMuted($userId) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT mute_until FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user['mute_until'] && strtotime($user['mute_until']) > time();
    }
    
    // 检查用户是否受限
    private function isUserRestricted($userId) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user['status'] === 'restricted';
    }
    
    // 删除消息（管理员功能）
    public function deleteMessage($messageId) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("DELETE FROM chat_messages WHERE id = ?");
        return $stmt->execute([$messageId]);
    }
    
    // 获取用户聊天统计
    public function getUserChatStats($userId) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_messages,
                   COUNT(CASE WHEN DATE(created_at) = DATE('now') THEN 1 END) as today_messages
            FROM chat_messages 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
