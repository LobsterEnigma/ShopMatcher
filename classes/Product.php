<?php
class Product {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    // 获取所有产品
    public function getAllProducts($categoryId = null) {
        $pdo = $this->db->getConnection();
        $sql = "SELECT p.*, c.name as category_name FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id";
        $params = [];
        
        if ($categoryId) {
            $sql .= " WHERE p.category_id = ?";
            $params[] = $categoryId;
        }
        
        $sql .= " ORDER BY p.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // 获取单个产品详情
    public function getProductById($productId) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.id = ?
        ");
        $stmt->execute([$productId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // 添加产品
    public function addProduct($data) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO products (category_id, name, brand, price, image_url, description, features, specifications, faq, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
        ");
        return $stmt->execute([
            $data['category_id'],
            $data['name'],
            $data['brand'],
            $data['price'],
            $data['image_url'],
            $data['description'],
            $data['features'],
            $data['specifications'],
            $data['faq']
        ]);
    }
    
    // 更新产品
    public function updateProduct($productId, $data) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            UPDATE products SET 
                category_id = ?, name = ?, brand = ?, price = ?, image_url = ?, 
                description = ?, features = ?, specifications = ?, faq = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['category_id'],
            $data['name'],
            $data['brand'],
            $data['price'],
            $data['image_url'],
            $data['description'],
            $data['features'],
            $data['specifications'],
            $data['faq'],
            $productId
        ]);
    }
    
    // 删除产品
    public function deleteProduct($productId) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        return $stmt->execute([$productId]);
    }
    
    // 产品对比
    public function compareProducts($productIds, $userId) {
        $pdo = $this->db->getConnection();
        
        // 检查用户对比权限
        $user = $this->getUserComparisonInfo($userId);
        if (!$this->canCompare($user, count($productIds))) {
            return ['success' => false, 'message' => '对比权限不足'];
        }
        
        // 获取产品信息
        $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
        $stmt->execute($productIds);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($products) != count($productIds)) {
            return ['success' => false, 'message' => '部分产品不存在'];
        }
        
        // 记录对比
        $stmt = $pdo->prepare("INSERT INTO comparison_records (user_id, product_ids) VALUES (?, ?)");
        $stmt->execute([$userId, implode(',', $productIds)]);
        
        // 添加积分
        $userObj = new User();
        $userObj->addPoints($userId, POINTS_COMPARISON, '产品对比');
        
        return ['success' => true, 'products' => $products];
    }
    
    // 获取用户对比信息
    private function getUserComparisonInfo($userId) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            SELECT u.vip_level, u.vip_expire_date,
                   COUNT(cr.id) as today_comparisons
            FROM users u
            LEFT JOIN comparison_records cr ON u.id = cr.user_id 
                AND DATE(cr.comparison_date) = DATE('now')
            WHERE u.id = ?
            GROUP BY u.id
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // 检查是否可以对比
    private function canCompare($user, $productCount) {
        $isVip = $user['vip_level'] > 0 && (!$user['vip_expire_date'] || strtotime($user['vip_expire_date']) > time());
        
        if ($isVip) {
            $maxProducts = 10;
            $maxDaily = 20;
        } else {
            $maxProducts = 2;
            $maxDaily = 5;
        }
        
        if ($productCount > $maxProducts) {
            return false;
        }
        
        if ($user['today_comparisons'] >= $maxDaily) {
            return false;
        }
        
        return true;
    }
    
    // 生成AI产品总结
    public function generateAISummary($productId) {
        $product = $this->getProductById($productId);
        if (!$product) {
            return ['success' => false, 'message' => '产品不存在'];
        }
        
        // 检查用户是否有权限查看AI总结
        if (!isset($_SESSION['user_id'])) {
            return ['success' => false, 'message' => '请先登录'];
        }
        
        $user = new User();
        if (!$user->isVip($_SESSION['user_id'])) {
            return ['success' => false, 'message' => '需要VIP权限'];
        }
        
        // 检查每日限制
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as ai_views_today 
            FROM ai_summary_views 
            WHERE user_id = ? AND DATE(created_at) = DATE('now')
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $dailyLimit = $this->getSetting('ai_summary_daily_limit', 3);
        if ($result['ai_views_today'] >= $dailyLimit) {
            return ['success' => false, 'message' => '今日AI总结查看次数已用完'];
        }
        
        // 调用AI API
        $aiSummary = $this->callAIForSummary($product);
        
        if ($aiSummary['success']) {
            // 更新产品AI总结
            $stmt = $pdo->prepare("UPDATE products SET ai_summary = ?, ai_rating = ?, ai_recommendation_reason = ? WHERE id = ?");
            $stmt->execute([
                $aiSummary['summary'],
                $aiSummary['rating'],
                $aiSummary['reason'],
                $productId
            ]);
            
            // 记录查看
            $stmt = $pdo->prepare("INSERT INTO ai_summary_views (user_id, product_id) VALUES (?, ?)");
            $stmt->execute([$_SESSION['user_id'], $productId]);
        }
        
        return $aiSummary;
    }
    
    // 调用AI生成总结
    private function callAIForSummary($product) {
        $prompt = $this->getAIPrompt('product_summary');
        $prompt = str_replace('{product_name}', $product['name'], $prompt);
        $prompt = str_replace('{product_description}', $product['description'], $prompt);
        $prompt = str_replace('{product_features}', $product['features'], $prompt);
        $prompt = str_replace('{product_specifications}', $product['specifications'], $prompt);
        
        // 获取系统设置中的AI配置
        $aiApiUrl = $this->getSetting('ai_api_url', AI_API_URL);
        $aiApiKey = $this->getSetting('ai_api_key', AI_API_KEY);
        $aiModel = $this->getSetting('ai_model', AI_MODEL);
        
        $data = [
            'model' => $aiModel,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 1000,
            'temperature' => 0.7
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $aiApiUrl . '/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $aiApiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            $content = $result['choices'][0]['message']['content'];
            
            // 解析AI返回的内容，提取评分和推荐原因
            preg_match('/评分：(\d+)星/', $content, $ratingMatch);
            preg_match('/推荐原因：(.+?)(?=\n|$)/', $content, $reasonMatch);
            
            return [
                'success' => true,
                'summary' => $content,
                'rating' => isset($ratingMatch[1]) ? (int)$ratingMatch[1] : 5,
                'reason' => isset($reasonMatch[1]) ? trim($reasonMatch[1]) : 'AI推荐'
            ];
        }
        
        return ['success' => false, 'message' => 'AI服务暂时不可用'];
    }
    
    // 获取AI提示词
    private function getAIPrompt($type) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT prompt FROM ai_prompts WHERE type = ?");
        $stmt->execute([$type]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result['prompt'];
        }
        
        // 默认提示词
        return "请分析以下产品信息，生成详细的产品总结，包括产品特点、优缺点、适用场景，并给出1-5星的评分和推荐原因。\n\n产品名称：{product_name}\n产品描述：{product_description}\n产品特性：{product_features}\n技术规格：{product_specifications}";
    }
    
    // 获取系统设置
    private function getSetting($key, $default = null) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : $default;
    }
}
?>
