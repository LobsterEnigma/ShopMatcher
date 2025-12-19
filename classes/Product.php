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
        $specMode = $data['specification_mode'] ?? 'markdown';
        $stmt = $pdo->prepare("
            INSERT INTO products (category_id, name, brand, price, image_url, description, features, specifications, faq, specification_mode, created_at, last_modified) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
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
            $specMode
        ]);
        
        // 如果有标签，设置标签
        if ($result && isset($data['tags']) && !empty($data['tags'])) {
            $productId = $pdo->lastInsertId();
            $this->setProductTags($productId, $data['tags']);
            return $productId;
        }
        
        return $result ? $pdo->lastInsertId() : false;
    }
    
    // 更新产品
    public function updateProduct($productId, $data) {
        $pdo = $this->db->getConnection();
        $specMode = $data['specification_mode'] ?? 'markdown';
        $stmt = $pdo->prepare("
            UPDATE products SET 
                category_id = ?, name = ?, brand = ?, price = ?, image_url = ?, 
                description = ?, features = ?, specifications = ?, faq = ?,
                specification_mode = ?, last_modified = datetime('now')
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
            $specMode,
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

        // 清洗产品ID：去重、转为整数、移除无效值
        $productIds = array_values(array_filter(array_unique(array_map('intval', $productIds))));
        if (count($productIds) < 2) {
            return ['success' => false, 'message' => '请至少选择2个产品进行对比'];
        }
        
        // 获取用户对比信息与限制
        $user = $this->getUserComparisonInfo($userId);
        if (!$user) {
            return ['success' => false, 'message' => '用户信息异常，请重新登录'];
        }
        
        $isVip = $user['vip_level'] > 0 && (!$user['vip_expire_date'] || strtotime($user['vip_expire_date']) > time());
        $maxProducts = $this->getSettingInt(
            $isVip 
                ? ['max_products_compare', 'max_products_compare_vip', 'max_products_compare_normal']
                : ['max_products_compare', 'max_products_compare_normal', 'max_products_compare_vip'],
            $isVip ? 10 : 2
        );
        $maxDaily = $this->getSettingInt(
            $isVip 
                ? ['max_comparison_per_day', 'max_comparison_per_day_vip', 'max_comparison_per_day_normal']
                : ['max_comparison_per_day', 'max_comparison_per_day_normal', 'max_comparison_per_day_vip'],
            $isVip ? 20 : 5
        );
        
        if (count($productIds) > $maxProducts) {
            return ['success' => false, 'message' => "最多同时对比{$maxProducts}个产品"];
        }
        
        if (($user['today_comparisons'] ?? 0) >= $maxDaily) {
            return ['success' => false, 'message' => "今日对比次数已达上限 ({$maxDaily}次)" ];
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
        
        return ['success' => true, 'products' => $products, 'limits' => ['max_products' => $maxProducts, 'max_daily' => $maxDaily, 'today' => $user['today_comparisons'] ?? 0]];
    }

    // 获取指定用户的对比限制（前端提示用）
    public function getCompareLimitsForUser($userId = null) {
        $user = null;
        if ($userId) {
            $user = $this->getUserComparisonInfo($userId);
        } else {
            $user = [
                'vip_level' => 0,
                'vip_expire_date' => null,
                'today_comparisons' => 0
            ];
        }

        if (!$user) {
            $user = [
                'vip_level' => 0,
                'vip_expire_date' => null,
                'today_comparisons' => 0
            ];
        }

        $isVip = $user['vip_level'] > 0 && (!$user['vip_expire_date'] || strtotime($user['vip_expire_date']) > time());
        $maxProducts = $this->getSettingInt(
            $isVip 
                ? ['max_products_compare', 'max_products_compare_vip', 'max_products_compare_normal']
                : ['max_products_compare', 'max_products_compare_normal', 'max_products_compare_vip'],
            $isVip ? 10 : 2
        );
        $maxDaily = $this->getSettingInt(
            $isVip 
                ? ['max_comparison_per_day', 'max_comparison_per_day_vip', 'max_comparison_per_day_normal']
                : ['max_comparison_per_day', 'max_comparison_per_day_normal', 'max_comparison_per_day_vip'],
            $isVip ? 20 : 5
        );

        return [
            'is_vip' => $isVip,
            'max_products' => $maxProducts,
            'max_daily' => $maxDaily,
            'today' => $user['today_comparisons'] ?? 0
        ];
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
    private function canCompare($user, $productCount, $maxProducts, $maxDaily) {
        if ($productCount > $maxProducts) {
            return false;
        }
        if (($user['today_comparisons'] ?? 0) >= $maxDaily) {
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

    // 读取整数配置，支持优先级列表
    private function getSettingInt(array $keys, $default) {
        foreach ($keys as $key) {
            $value = $this->getSetting($key, null);
            if ($value !== null && $value !== '') {
                return (int)$value;
            }
        }
        return (int)$default;
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
    
    // ========== 技术规格标签相关方法 ==========
    
    // 获取所有技术规格字段（用于手柄分类）
    public function getSpecificationFields() {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->query("SELECT * FROM specification_fields ORDER BY display_order ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // 获取指定字段的所有标签
    public function getSpecificationTagsByField($fieldId) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT * FROM specification_tags WHERE field_id = ? ORDER BY display_order ASC, tag_name ASC");
        $stmt->execute([$fieldId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // 创建或获取技术规格标签
    public function createOrGetSpecificationTag($fieldId, $tagName) {
        $pdo = $this->db->getConnection();
        $tagName = trim($tagName);
        if (empty($tagName)) {
            return null;
        }
        
        // 先尝试获取现有标签
        $stmt = $pdo->prepare("SELECT id FROM specification_tags WHERE field_id = ? AND tag_name = ?");
        $stmt->execute([$fieldId, $tagName]);
        $tag = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($tag) {
            return $tag['id'];
        }
        
        // 获取当前字段的最大display_order
        $stmt = $pdo->prepare("SELECT MAX(display_order) as max_order FROM specification_tags WHERE field_id = ?");
        $stmt->execute([$fieldId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextOrder = ($result['max_order'] ?? 0) + 1;
        
        // 创建新标签
        $stmt = $pdo->prepare("INSERT INTO specification_tags (field_id, tag_name, display_order) VALUES (?, ?, ?)");
        $stmt->execute([$fieldId, $tagName, $nextOrder]);
        return $pdo->lastInsertId();
    }
    
    // 获取产品的技术规格标签
    public function getProductSpecificationTags($productId) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            SELECT pst.*, sf.name as field_name, sf.display_order, 
                   COALESCE(st.tag_name, pst.custom_value) as tag_name,
                   COALESCE(st.display_order, 999999) as tag_display_order,
                   pst.custom_value
            FROM product_specification_tags pst
            INNER JOIN specification_fields sf ON pst.field_id = sf.id
            LEFT JOIN specification_tags st ON pst.tag_id = st.id
            WHERE pst.product_id = ?
            ORDER BY sf.display_order ASC, COALESCE(st.display_order, 999999) ASC
        ");
        $stmt->execute([$productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // 设置产品的技术规格标签
    public function setProductSpecificationTags($productId, $specData) {
        $pdo = $this->db->getConnection();
        
        try {
            // 开始事务
            $pdo->beginTransaction();
            
            // 删除现有标签关联
            $stmt = $pdo->prepare("DELETE FROM product_specification_tags WHERE product_id = ?");
            $stmt->execute([$productId]);
            
            // 添加新标签
            if (!empty($specData) && is_array($specData)) {
                $stmt = $pdo->prepare("
                    INSERT INTO product_specification_tags (product_id, field_id, tag_id, custom_value) 
                    VALUES (?, ?, ?, ?)
                ");
                
                foreach ($specData as $fieldId => $values) {
                    if (empty($values) || !is_array($values)) {
                        continue;
                    }
                    
                    foreach ($values as $value) {
                        $value = trim($value);
                        if (empty($value)) {
                            continue;
                        }
                        
                        // 尝试查找现有标签
                        $tagStmt = $pdo->prepare("SELECT id FROM specification_tags WHERE field_id = ? AND tag_name = ?");
                        $tagStmt->execute([$fieldId, $value]);
                        $tag = $tagStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($tag) {
                            // 使用现有标签
                            $stmt->execute([$productId, $fieldId, $tag['id'], null]);
                            error_log("使用现有标签 - productId: $productId, fieldId: $fieldId, tagId: {$tag['id']}, value: $value");
                        } else {
                            // 创建新标签并使用
                            $tagId = $this->createOrGetSpecificationTag($fieldId, $value);
                            if ($tagId) {
                                $stmt->execute([$productId, $fieldId, $tagId, null]);
                                error_log("创建并使用新标签 - productId: $productId, fieldId: $fieldId, tagId: $tagId, value: $value");
                            } else {
                                // 如果创建失败，使用自定义值
                                $stmt->execute([$productId, $fieldId, null, $value]);
                                error_log("使用自定义值 - productId: $productId, fieldId: $fieldId, value: $value");
                            }
                        }
                    }
                }
            }
            
            // 提交事务
            $pdo->commit();
            return true;
        } catch (Exception $e) {
            // 回滚事务
            $pdo->rollBack();
            error_log("保存技术规格标签时出错: " . $e->getMessage());
            return false;
        }
    }
}
?>
