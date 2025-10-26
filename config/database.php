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
        
        // 插入默认数据
        $this->insertDefaultData();
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
        }
    }
}
?>
