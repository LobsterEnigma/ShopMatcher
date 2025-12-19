<?php
class Database {
    private $pdo;
    
    public function __construct() {
        try {
            // 使用绝对路径确保所有页面访问同一个数据库文件
            $dbPath = dirname(__DIR__) . '/database.db';
            $this->pdo = new PDO('sqlite:' . $dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 设置超时和WAL模式以避免数据库锁定
            $this->pdo->exec('PRAGMA busy_timeout = 5000');
            $this->pdo->exec('PRAGMA journal_mode = WAL');
            
            $this->createTables();
        } catch(PDOException $e) {
            die("数据库连接失败: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    private function createTables() {
        // 用户表
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(50) UNIQUE NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                vip_level INTEGER DEFAULT 0,
                vip_expire_date DATETIME,
                level INTEGER DEFAULT 1,
                points INTEGER DEFAULT 0,
                status VARCHAR(20) DEFAULT 'active',
                ban_until DATETIME,
                mute_until DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_login DATETIME,
                login_token VARCHAR(255)
            )
        ");
        
        // 产品分类表
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // 产品表
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER,
                name VARCHAR(200) NOT NULL,
                brand VARCHAR(100),
                price DECIMAL(10,2),
                image_url VARCHAR(500),
                description TEXT,
                features TEXT,
                specifications TEXT,
                faq TEXT,
                ai_summary TEXT,
                ai_rating INTEGER DEFAULT 0,
                ai_recommendation_reason TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (category_id) REFERENCES categories(id)
            )
        ");
        
        // 产品对比记录表
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS comparison_records (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                product_ids TEXT,
                comparison_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");
        
        // 聊天消息表
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS chat_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                message TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");
        
        // 头衔表
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS titles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                color VARCHAR(20) DEFAULT '#000000',
                points_required INTEGER DEFAULT 0,
                requirements TEXT,
                icon VARCHAR(50) DEFAULT 'fas fa-trophy',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // 用户头衔关联表
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS user_titles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                title_id INTEGER,
                assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                expire_at DATETIME,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (title_id) REFERENCES titles(id)
            )
        ");
        
        // AI提示词表
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS ai_prompts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                prompt TEXT NOT NULL,
                type VARCHAR(50) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // 积分记录表
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS point_records (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                points INTEGER,
                reason VARCHAR(200),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");
        
        // VIP套餐表
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS vip_packages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                price DECIMAL(10,2) NOT NULL,
                duration_months INTEGER NOT NULL,
                benefits TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // 支付记录表
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS payment_records (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                package_id INTEGER,
                amount DECIMAL(10,2),
                payment_method VARCHAR(50),
                transaction_id VARCHAR(200),
                status VARCHAR(20) DEFAULT 'pending',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (package_id) REFERENCES vip_packages(id)
            )
        ");
        
        // 系统设置表
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS system_settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key VARCHAR(100) UNIQUE NOT NULL,
                setting_value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // 公告表
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS announcements (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(200) NOT NULL,
                content TEXT NOT NULL,
                is_active BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // 管理员表
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS admins (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(50) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                email VARCHAR(100),
                is_main BOOLEAN DEFAULT 0,
                permissions TEXT,
                last_login DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // AI总结查看记录表
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS ai_summary_views (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                product_id INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (product_id) REFERENCES products(id)
            )
        ");
        
        // AI聊天记录表
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS ai_chat_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                question TEXT,
                answer TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");
        
        // 标签表
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS tags (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(50) UNIQUE NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // 产品标签关联表
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS product_tags (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER NOT NULL,
                tag_id INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
                UNIQUE(product_id, tag_id)
            )
        ");
        
        // 指南表（支持多个文章，博客式）
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS guides (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(200) NOT NULL,
                slug VARCHAR(200) UNIQUE,
                content TEXT NOT NULL,
                excerpt TEXT,
                is_active BOOLEAN DEFAULT 1,
                view_count INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // 产品站长点评表
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS product_admin_comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER NOT NULL,
                admin_id INTEGER NOT NULL,
                admin_name VARCHAR(100) NOT NULL,
                comment TEXT NOT NULL,
                is_active BOOLEAN DEFAULT 1,
                display_order INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
            )
        ");
        
        // 产品用户评论表
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS product_comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                content TEXT NOT NULL,
                status VARCHAR(20) DEFAULT 'pending',
                like_count INTEGER DEFAULT 0,
                reply_count INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        // 评论点赞表
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS comment_likes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                comment_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (comment_id) REFERENCES product_comments(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE(comment_id, user_id)
            )
        ");
        
        // 评论回复表
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS comment_replies (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                comment_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                reply_to_user_id INTEGER,
                content TEXT NOT NULL,
                status VARCHAR(20) DEFAULT 'pending',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (comment_id) REFERENCES product_comments(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (reply_to_user_id) REFERENCES users(id) ON DELETE SET NULL
            )
        ");
        
        // 评论举报表
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS comment_reports (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                comment_id INTEGER,
                reply_id INTEGER,
                user_id INTEGER NOT NULL,
                reason TEXT NOT NULL,
                status VARCHAR(20) DEFAULT 'pending',
                admin_id INTEGER,
                admin_note TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                processed_at DATETIME,
                FOREIGN KEY (comment_id) REFERENCES product_comments(id) ON DELETE CASCADE,
                FOREIGN KEY (reply_id) REFERENCES comment_replies(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
            )
        ");
        
        // 为现有表添加新字段（如果不存在）
        try {
            $this->pdo->exec("ALTER TABLE guides ADD COLUMN slug VARCHAR(200)");
        } catch (PDOException $e) {
            // 字段已存在，忽略错误
        }
        try {
            $this->pdo->exec("ALTER TABLE guides ADD COLUMN excerpt TEXT");
        } catch (PDOException $e) {
            // 字段已存在，忽略错误
        }
        try {
            $this->pdo->exec("ALTER TABLE guides ADD COLUMN view_count INTEGER DEFAULT 0");
        } catch (PDOException $e) {
            // 字段已存在，忽略错误
        }
        
        // 为products表添加last_modified字段（如果不存在）
        try {
            // 检查字段是否存在
            $stmt = $this->pdo->query("PRAGMA table_info(products)");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $hasLastModified = false;
            foreach ($columns as $column) {
                if ($column['name'] === 'last_modified') {
                    $hasLastModified = true;
                    break;
                }
            }
            
            if (!$hasLastModified) {
                $this->pdo->exec("ALTER TABLE products ADD COLUMN last_modified DATETIME");
                // 为现有产品初始化last_modified字段
                $this->pdo->exec("UPDATE products SET last_modified = created_at WHERE last_modified IS NULL");
            }
        } catch (PDOException $e) {
            // 如果出错，尝试直接添加字段
            try {
                $this->pdo->exec("ALTER TABLE products ADD COLUMN last_modified DATETIME");
                $this->pdo->exec("UPDATE products SET last_modified = created_at WHERE last_modified IS NULL");
            } catch (PDOException $e2) {
                // 忽略错误
            }
        }
        
        // 技术规格字段表（固定字段，仅用于手柄分类）
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS specification_fields (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                display_order INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // 技术规格标签表（每个字段的标签值）
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS specification_tags (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                field_id INTEGER NOT NULL,
                tag_name VARCHAR(100) NOT NULL,
                display_order INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (field_id) REFERENCES specification_fields(id) ON DELETE CASCADE,
                UNIQUE(field_id, tag_name)
            )
        ");
        
        // 为specification_tags表添加display_order字段（如果不存在）
        try {
            $stmt = $this->pdo->query("PRAGMA table_info(specification_tags)");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $hasDisplayOrder = false;
            foreach ($columns as $column) {
                if ($column['name'] === 'display_order') {
                    $hasDisplayOrder = true;
                    break;
                }
            }
            
            if (!$hasDisplayOrder) {
                $this->pdo->exec("ALTER TABLE specification_tags ADD COLUMN display_order INTEGER DEFAULT 0");
                // 为现有标签初始化display_order
                $this->pdo->exec("UPDATE specification_tags SET display_order = id WHERE display_order = 0 OR display_order IS NULL");
            }
        } catch (PDOException $e) {
            // 忽略错误
        }
        
        // 产品技术规格标签关联表
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS product_specification_tags (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER NOT NULL,
                field_id INTEGER NOT NULL,
                tag_id INTEGER,
                custom_value TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                FOREIGN KEY (field_id) REFERENCES specification_fields(id) ON DELETE CASCADE,
                FOREIGN KEY (tag_id) REFERENCES specification_tags(id) ON DELETE SET NULL
            )
        ");
        
        // 为products表添加specification_mode字段（标记使用MD模式还是标签化模式）
        try {
            $stmt = $this->pdo->query("PRAGMA table_info(products)");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $hasSpecMode = false;
            foreach ($columns as $column) {
                if ($column['name'] === 'specification_mode') {
                    $hasSpecMode = true;
                    break;
                }
            }
            if (!$hasSpecMode) {
                $this->pdo->exec("ALTER TABLE products ADD COLUMN specification_mode VARCHAR(20) DEFAULT 'markdown'");
            }
        } catch (PDOException $e) {
            // 忽略错误
        }
        
        // 为specification_fields表添加category_id字段（用于支持多分类）
        try {
            $stmt = $this->pdo->query("PRAGMA table_info(specification_fields)");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $hasCategoryId = false;
            foreach ($columns as $column) {
                if ($column['name'] === 'category_id') {
                    $hasCategoryId = true;
                    break;
                }
            }
            if (!$hasCategoryId) {
                $this->pdo->exec("ALTER TABLE specification_fields ADD COLUMN category_id INTEGER");
                // 为现有字段设置默认分类（手柄分类）
                $stmt = $this->pdo->query("SELECT id FROM categories WHERE name = '手柄' LIMIT 1");
                $handleCategory = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($handleCategory) {
                    $this->pdo->exec("UPDATE specification_fields SET category_id = " . $handleCategory['id'] . " WHERE category_id IS NULL");
                }
            }
        } catch (PDOException $e) {
            // 忽略错误
        }
        
        // 初始化技术规格字段（无论是否有其他数据）
        $this->initializeSpecificationFields();
        
        // 插入默认数据
        $this->insertDefaultData();
    }
    
    private function initializeSpecificationFields() {
        // 检查技术规格字段是否已存在
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM specification_fields");
        $fieldCount = $stmt->fetchColumn();
        
        // 如果字段表为空，插入12个默认字段（仅用于手柄分类）
        if ($fieldCount == 0) {
            // 获取手柄分类ID
            $stmt = $this->pdo->query("SELECT id FROM categories WHERE name = '手柄' LIMIT 1");
            $handleCategory = $stmt->fetch(PDO::FETCH_ASSOC);
            $categoryId = $handleCategory ? $handleCategory['id'] : null;
            
            $specFields = [
                '连接方式', '支持平台', '摇杆', '扳机', '肩键', '十字键', 
                'ABXY键', '震动', '电量', '陀螺仪', '自定义按键', '重量'
            ];
            foreach ($specFields as $index => $fieldName) {
                try {
                    if ($categoryId) {
                        $stmt = $this->pdo->prepare("INSERT INTO specification_fields (name, display_order, category_id) VALUES (?, ?, ?)");
                        $stmt->execute([$fieldName, $index + 1, $categoryId]);
                    } else {
                        $stmt = $this->pdo->prepare("INSERT INTO specification_fields (name, display_order) VALUES (?, ?)");
                        $stmt->execute([$fieldName, $index + 1]);
                    }
                } catch (PDOException $e) {
                    // 忽略重复插入错误
                }
            }
        }
    }
    
    private function insertDefaultData() {
        // 检查是否已有数据
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM categories");
        if ($stmt->fetchColumn() == 0) {
            // 插入默认分类
            $this->pdo->exec("INSERT INTO categories (name, description) VALUES ('手柄', '游戏手柄产品对比')");
            
            // 插入默认管理员
            $this->pdo->exec("INSERT INTO admins (username, password, is_main, created_at) VALUES ('admin', '" . password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 4]) . "', 1, datetime('now'))");
            
            // 插入默认头衔
            $this->pdo->exec("INSERT INTO titles (name, description, color) VALUES 
                ('新手', '刚注册的用户', '#808080'),
                ('活跃用户', '经常参与讨论的用户', '#4CAF50'),
                ('种子用户', '社区贡献者', '#FF9800'),
                ('活跃大师', '社区活跃度很高的用户', '#9C27B0')
            ");
            
            // 插入默认VIP套餐
            $this->pdo->exec("INSERT INTO vip_packages (name, price, duration_months, benefits) VALUES 
                ('月度VIP', 29.90, 1, '每日20次对比，最多同时对比10个产品，解锁AI产品总结'),
                ('季度VIP', 79.90, 3, '每日20次对比，最多同时对比10个产品，解锁AI产品总结'),
                ('年度VIP', 299.90, 12, '每日20次对比，最多同时对比10个产品，解锁AI产品总结')
            ");
            
            // 插入默认系统设置
            $this->pdo->exec("INSERT INTO system_settings (setting_key, setting_value) VALUES 
                ('maintenance_mode', '0'),
                ('chat_points_per_day', '10'),
                ('comparison_points_per_day', '5'),
                ('max_comparison_per_day_normal', '5'),
                ('max_comparison_per_day_vip', '20'),
                ('max_products_compare_normal', '2'),
                ('max_products_compare_vip', '10'),
                ('ai_summary_daily_limit', '3'),
                ('google_ads_enabled', '0'),
                ('google_ads_client_id', ''),
                ('e_payment_merchant_id', ''),
                ('e_payment_merchant_key', '')
            ");
            
            // 插入默认指南内容
            $defaultGuideContent = "# 产品对比使用指南

## 欢迎使用产品对比功能！

本指南将帮助您快速了解如何使用产品对比功能，找到最适合您的产品。

## 如何使用对比功能

### 第一步：选择产品
在产品列表页面，点击产品卡片上的「对比」按钮，将产品添加到对比列表。

### 第二步：开始对比
选择至少2个产品后，点击右下角的「开始对比」按钮，即可查看详细的对比结果。

### 第三步：查看对比结果
对比页面会显示以下信息：
- **价格对比**：清晰展示各产品的价格
- **品牌信息**：了解产品品牌
- **产品描述**：查看产品详细介绍
- **产品特性**：对比各产品的特色功能
- **技术规格**：查看详细的技术参数

## 对比功能限制

- **普通用户**：每日最多对比5次，单次最多对比2个产品
- **VIP用户**：每日最多对比20次，单次最多对比10个产品

## 小贴士

1. 建议先浏览产品详情，了解基本信息后再进行对比
2. 可以根据分类和标签筛选产品，快速找到目标产品
3. 对比结果可以分享给朋友，一起讨论

祝您使用愉快！";
            
            $defaultExcerpt = "本指南将帮助您快速了解如何使用产品对比功能，找到最适合您的产品。";
            $defaultSlug = 'product-comparison-guide';
            
            // 检查是否已有指南
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM guides");
            $stmt->execute();
            if ($stmt->fetchColumn() == 0) {
                $stmt = $this->pdo->prepare("INSERT INTO guides (title, slug, content, excerpt, is_active) VALUES (?, ?, ?, ?, 1)");
                $stmt->execute(['产品对比使用指南', $defaultSlug, $defaultGuideContent, $defaultExcerpt]);
            }
        }
    }
}
?>
