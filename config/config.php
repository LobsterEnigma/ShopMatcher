<?php
// 基础配置
define('SITE_NAME', '手柄对比网');
define('SITE_URL', 'http://localhost');
define('ADMIN_EMAIL', 'admin@example.com');

// 数据库配置
define('DB_PATH', 'database.db');

// 安全配置
define('SESSION_LIFETIME', 3600); // 1小时
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15分钟

// 文件上传配置
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);

// AI配置
define('AI_API_URL', 'https://api.openai.com/v1/chat/completions');
define('AI_API_KEY', ''); // 需要设置
define('AI_MODEL', 'gpt-3.5-turbo');

// 支付配置
define('E_PAYMENT_URL', 'https://api.epayment.com');
define('E_PAYMENT_MERCHANT_ID', '');
define('E_PAYMENT_MERCHANT_KEY', '');

// 谷歌广告配置
define('GOOGLE_ADS_CLIENT_ID', '');
define('GOOGLE_ADS_ENABLED', false);

// 积分系统配置
define('POINTS_CHAT_MESSAGE', 1);
define('POINTS_COMPARISON', 2);
define('POINTS_DAILY_LOGIN', 5);

// 等级配置
define('MAX_LEVEL', 20);
define('POINTS_PER_LEVEL', 100);

// 验证码配置
define('CAPTCHA_SECRET', 'your_captcha_secret_key');

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 自动加载
spl_autoload_register(function ($class) {
    $file = 'classes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// 启动会话
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 获取系统设置（从数据库）- 不使用缓存，每次都读取最新值
function getSiteSetting($key, $default = '') {
    try {
        $dbPath = dirname(__DIR__) . '/database.db';
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA busy_timeout = 5000');
        
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        
        return $result !== false ? $result : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

// 动态获取网站名称
function getSiteName() {
    return getSiteSetting('site_name', SITE_NAME);
}
?>
