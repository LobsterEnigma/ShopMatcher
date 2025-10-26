<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/Product.php';

// 检查维护模式
$db = new Database();
$pdo = $db->getConnection();
$stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
$stmt->execute();
$maintenanceMode = $stmt->fetchColumn();

if ($maintenanceMode === '1') {
    include 'maintenance.php';
    exit;
}

// 获取公告
$stmt = $pdo->prepare("SELECT * FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 3");
$stmt->execute();
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取产品
$productObj = new Product();
$products = $productObj->getAllProducts();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo getSiteName(); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 80px 0;
        }
        .product-card {
            transition: transform 0.3s;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.2);
        }
        .announcement-item {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin-bottom: 10px;
        }
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
    <!-- 导航栏 -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-gamepad"></i> <?php echo getSiteName(); ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">首页</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="products.php">产品对比</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="chat.php">讨论区</a>
                    </li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">个人中心</a>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <div class="user-info">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <span class="text-light">
                            <i class="fas fa-user"></i> 
                            <?php echo htmlspecialchars($_SESSION['username'] ?? '用户'); ?>
                        </span>
                        <a href="logout.php" class="btn btn-outline-light btn-sm">退出</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-light me-2">登录</a>
                        <a href="register.php" class="btn btn-primary">注册</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- 主要内容 -->
    <div class="container-fluid">
        <!-- 英雄区域 -->
        <div class="hero-section text-center">
            <div class="container">
                <h1 class="display-4 mb-4">专业手柄对比平台</h1>
                <p class="lead mb-4">智能对比，选择最适合你的游戏手柄</p>
                <a href="products.php" class="btn btn-light btn-lg">开始对比</a>
            </div>
        </div>

        <!-- 公告区域 -->
        <?php if (!empty($announcements)): ?>
        <div class="container my-5">
            <h3 class="mb-4"><i class="fas fa-bullhorn"></i> 最新公告</h3>
            <?php foreach ($announcements as $announcement): ?>
            <div class="announcement-item">
                <h5><?php echo htmlspecialchars($announcement['title']); ?></h5>
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                <small class="text-muted"><?php echo date('Y-m-d H:i', strtotime($announcement['created_at'])); ?></small>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- 产品展示 -->
        <div class="container my-5">
            <h3 class="mb-4"><i class="fas fa-gamepad"></i> 热门产品</h3>
            <div class="row">
                <?php foreach (array_slice($products, 0, 6) as $product): ?>
                <div class="col-md-4 mb-4">
                    <div class="card product-card h-100">
                        <?php if ($product['image_url']): ?>
                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>" style="height: 200px; object-fit: cover;">
                        <?php endif; ?>
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                            <p class="card-text text-muted"><?php echo htmlspecialchars($product['brand']); ?></p>
                            <p class="card-text"><?php echo mb_substr(strip_tags($product['description']), 0, 100); ?>...</p>
                            <div class="mt-auto">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="h5 text-primary">¥<?php echo number_format($product['price'], 2); ?></span>
                                    <a href="product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary">查看详情</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (count($products) > 6): ?>
            <div class="text-center mt-4">
                <a href="products.php" class="btn btn-outline-primary btn-lg">查看更多产品</a>
            </div>
            <?php endif; ?>
        </div>

        <!-- 功能特色 -->
        <div class="container my-5">
            <h3 class="text-center mb-5">平台特色</h3>
            <div class="row">
                <div class="col-md-4 text-center mb-4">
                    <div class="card border-0">
                        <div class="card-body">
                            <i class="fas fa-balance-scale fa-3x text-primary mb-3"></i>
                            <h5>智能对比</h5>
                            <p class="text-muted">多维度对比产品参数，帮您做出最佳选择</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-center mb-4">
                    <div class="card border-0">
                        <div class="card-body">
                            <i class="fas fa-robot fa-3x text-success mb-3"></i>
                            <h5>AI导购</h5>
                            <p class="text-muted">AI智能推荐，个性化购物体验</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-center mb-4">
                    <div class="card border-0">
                        <div class="card-body">
                            <i class="fas fa-users fa-3x text-warning mb-3"></i>
                            <h5>社区讨论</h5>
                            <p class="text-muted">与玩家交流使用心得，分享游戏体验</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 页脚 -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><?php echo getSiteName(); ?></h5>
                    <p>专业的游戏手柄对比平台，为您提供最全面的产品信息和购买建议。</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p>&copy; 2024 <?php echo getSiteName(); ?>. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
