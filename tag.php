<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/Product.php';
require_once 'classes/Markdown.php';

$productObj = new Product();
$tagName = isset($_GET['name']) ? urldecode($_GET['name']) : '';

if (empty($tagName)) {
    header('Location: products.php');
    exit;
}

// 获取标签信息
$tag = $productObj->getTagByName($tagName);
if (!$tag) {
    header('Location: products.php');
    exit;
}

// 获取该标签下的所有产品
$products = $productObj->getProductsByTagName($tagName);

// 为每个产品添加标签信息
foreach ($products as $key => $product) {
    $products[$key]['tags'] = $productObj->getProductTags($product['id']);
}

// 获取分类（用于导航）
$db = new Database();
$pdo = $db->getConnection();
$stmt = $pdo->prepare("SELECT * FROM categories ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>标签：<?php echo htmlspecialchars($tagName); ?> - <?php echo getSiteName(); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .product-card {
            transition: transform 0.3s;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.2);
        }
        .tag-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
        }
        .tag-badge {
            font-size: 1.2rem;
            padding: 8px 16px;
            border-radius: 20px;
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
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="切换导航">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarMain">
                <div class="navbar-nav me-auto">
                        <a class="nav-link" href="index.php">首页</a>
                        <a class="nav-link" href="products.php">产品对比</a>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a class="nav-link" href="chat.php">讨论区</a>
                    <a class="nav-link" href="guide.php">使用指南</a>
                    <a class="nav-link" href="profile.php">个人中心</a>
                    <?php endif; ?>
                </div>
                
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

    <!-- 标签头部 -->
    <div class="tag-header">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="index.php" class="text-white">首页</a></li>
                    <li class="breadcrumb-item"><a href="products.php" class="text-white">产品列表</a></li>
                    <li class="breadcrumb-item active text-white" aria-current="page">标签</li>
                </ol>
            </nav>
            <h1 class="mt-3 mb-2">
                <span class="badge tag-badge bg-light text-dark">
                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($tagName); ?>
                </span>
            </h1>
            <p class="mb-0">找到 <strong><?php echo count($products); ?></strong> 个相关产品</p>
        </div>
    </div>

    <div class="container my-5">
        <?php if (!empty($products)): ?>
        <div class="row">
            <?php foreach ($products as $product): ?>
            <div class="col-md-4 mb-4">
                <div class="card product-card h-100 position-relative">
                    <?php if ($product['image_url']): ?>
                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                         class="card-img-top" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                         style="height: 200px; object-fit: cover;">
                    <?php endif; ?>
                    
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                        <p class="card-text text-muted"><?php echo htmlspecialchars($product['brand']); ?></p>
                        <?php
                            $descriptionPreview = Markdown::toPlainText($product['description']);
                            $descriptionShort = mb_substr($descriptionPreview, 0, 100);
                        ?>
                        <p class="card-text"><?php echo htmlspecialchars($descriptionShort); ?>...</p>
                        
                        <?php if (!empty($product['tags'])): ?>
                        <div class="mb-2">
                            <?php foreach ($product['tags'] as $tag): ?>
                            <a href="tag.php?name=<?php echo urlencode($tag['name']); ?>" 
                               class="badge bg-secondary text-decoration-none me-1">
                                <?php echo htmlspecialchars($tag['name']); ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mt-auto">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="h5 text-primary">¥<?php echo number_format($product['price'], 2); ?></span>
                                <a href="product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary btn-sm">
                                    查看详情
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-tag fa-3x text-muted mb-3"></i>
            <h4 class="text-muted">该标签下暂无产品</h4>
            <p class="text-muted">
                <a href="products.php" class="btn btn-primary mt-3">返回产品列表</a>
            </p>
        </div>
        <?php endif; ?>
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

