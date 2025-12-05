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
        $result = $stmt->execute([
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
        
        // 如果有标签，设置标签
        if ($result && isset($data['tags']) && !empty($data['tags'])) {
            $productId = $pdo->lastInsertId();
            $this->setProductTags($productId, $data['tags']);
        }
        
        return $result;
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
        $result = $stmt->execute([
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
        
        // 更新标签
        if ($result && isset($data['tags'])) {
            $this->setProductTags($productId, $data['tags']);
        }
        
        return $result;
    }
    
    // 删除产品
    public function deleteProduct($productId) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $result = $stmt->execute([$productId]);
        
        // 删除产品后，自动清理未使用的标签
        if ($result) {
            $this->cleanUnusedTags();
        }
        
        return $result;
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
    
    // ========== 标签相关方法 ==========
    
    // 获取产品的所有标签
    public function getProductTags($productId) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            SELECT t.id, t.name 
            FROM tags t
            INNER JOIN product_tags pt ON t.id = pt.tag_id
            WHERE pt.product_id = ?
            ORDER BY t.name
        ");
        $stmt->execute([$productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // 获取所有标签
    public function getAllTags() {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->query("SELECT * FROM tags ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // 创建或获取标签
    public function createOrGetTag($tagName) {
        $pdo = $this->db->getConnection();
        $tagName = trim($tagName);
        if (empty($tagName)) {
            return null;
        }
        
        // 先尝试获取现有标签
        $stmt = $pdo->prepare("SELECT id FROM tags WHERE name = ?");
        $stmt->execute([$tagName]);
        $tag = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($tag) {
            return $tag['id'];
        }
        
        // 创建新标签
        $stmt = $pdo->prepare("INSERT INTO tags (name) VALUES (?)");
        $stmt->execute([$tagName]);
        return $pdo->lastInsertId();
    }
    
    // 设置产品的标签
    public function setProductTags($productId, $tagNames) {
        $pdo = $this->db->getConnection();
        
        // 删除现有标签关联
        $stmt = $pdo->prepare("DELETE FROM product_tags WHERE product_id = ?");
        $stmt->execute([$productId]);
        
        // 添加新标签
        if (!empty($tagNames) && is_array($tagNames)) {
            $stmt = $pdo->prepare("INSERT INTO product_tags (product_id, tag_id) VALUES (?, ?)");
            foreach ($tagNames as $tagName) {
                $tagName = trim($tagName);
                if (empty($tagName)) {
                    continue;
                }
                $tagId = $this->createOrGetTag($tagName);
                if ($tagId) {
                    try {
                        $stmt->execute([$productId, $tagId]);
                    } catch (PDOException $e) {
                        // 忽略重复键错误
                        if ($e->getCode() != 23000) {
                            throw $e;
                        }
                    }
                }
            }
        }
        
        // 自动清理未使用的标签
        $this->cleanUnusedTags();
        
        return true;
    }
    
    // 根据标签获取产品
    public function getProductsByTag($tagId) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            SELECT DISTINCT p.*, c.name as category_name 
            FROM products p
            INNER JOIN product_tags pt ON p.id = pt.product_id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE pt.tag_id = ?
            ORDER BY p.id DESC
        ");
        $stmt->execute([$tagId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // 根据标签名称获取产品
    public function getProductsByTagName($tagName) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            SELECT DISTINCT p.*, c.name as category_name 
            FROM products p
            INNER JOIN product_tags pt ON p.id = pt.product_id
            INNER JOIN tags t ON pt.tag_id = t.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE t.name = ?
            ORDER BY p.id DESC
        ");
        $stmt->execute([$tagName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // 获取标签信息
    public function getTagById($tagId) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT * FROM tags WHERE id = ?");
        $stmt->execute([$tagId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // 获取标签信息（根据名称）
    public function getTagByName($tagName) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT * FROM tags WHERE name = ?");
        $stmt->execute([$tagName]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // 清理未使用的标签（没有产品关联的标签）
    public function cleanUnusedTags() {
        $pdo = $this->db->getConnection();
        
        // 先获取要删除的标签ID列表
        $stmt = $pdo->query("
            SELECT t.id 
            FROM tags t
            LEFT JOIN product_tags pt ON t.id = pt.tag_id
            WHERE pt.tag_id IS NULL
        ");
        $tagsToDelete = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $count = count($tagsToDelete);
        
        if ($count > 0) {
            // 删除未使用的标签
            $placeholders = str_repeat('?,', count($tagsToDelete) - 1) . '?';
            $stmt = $pdo->prepare("DELETE FROM tags WHERE id IN ($placeholders)");
            $stmt->execute($tagsToDelete);
        }
        
        return $count;
    }
    
    // 获取有产品关联的标签列表
    public function getActiveTags() {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            SELECT DISTINCT t.id, t.name 
            FROM tags t
            INNER JOIN product_tags pt ON t.id = pt.tag_id
            ORDER BY t.name
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
