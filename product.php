<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/Product.php';

$productId = $_GET['id'] ?? 0;
if (!$productId) {
    header('Location: products.php');
    exit;
}

$productObj = new Product();
$product = $productObj->getProductById($productId);

if (!$product) {
    $_SESSION['error'] = '产品不存在';
    header('Location: products.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .product-detail {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .product-image-large {
            width: 100%;
            height: 400px;
            object-fit: cover;
        }
        .product-info {
            padding: 30px;
        }
        .price-tag {
            font-size: 2rem;
            font-weight: bold;
            color: #28a745;
        }
        .product-section {
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .product-section h5 {
            color: #667eea;
            margin-bottom: 15px;
            font-weight: bold;
        }
        .breadcrumb {
            background: transparent;
            padding: 10px 0;
        }
        .badge-category {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <!-- 导航栏 -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-gamepad"></i> <?php echo SITE_NAME; ?>
            </a>
            
            <div class="navbar-nav me-auto">
                <a class="nav-link" href="index.php">首页</a>
                <a class="nav-link active" href="products.php">产品对比</a>
                <a class="nav-link" href="chat.php">讨论区</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                <a class="nav-link" href="profile.php">个人中心</a>
                <?php endif; ?>
            </div>
            
            <div class="navbar-nav">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span class="text-light me-3">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </span>
                    <a href="logout.php" class="btn btn-outline-light">退出</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline-light me-2">登录</a>
                    <a href="register.php" class="btn btn-primary">注册</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <!-- 面包屑导航 -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">首页</a></li>
                <li class="breadcrumb-item"><a href="products.php">产品列表</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($product['name']); ?></li>
            </ol>
        </nav>

        <div class="product-detail">
            <div class="row">
                <!-- 产品图片 -->
                <div class="col-md-6">
                    <?php if ($product['image_url']): ?>
                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                         class="product-image-large" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>">
                    <?php else: ?>
                    <div class="product-image-large d-flex align-items-center justify-content-center bg-light">
                        <i class="fas fa-image fa-5x text-muted"></i>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- 产品信息 -->
                <div class="col-md-6 product-info">
                    <div class="mb-3">
                        <span class="badge-category">
                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($product['category_name'] ?? '未分类'); ?>
                        </span>
                    </div>
                    
                    <h2 class="mb-3"><?php echo htmlspecialchars($product['name']); ?></h2>
                    
                    <div class="mb-3">
                        <span class="text-muted">品牌：</span>
                        <strong><?php echo htmlspecialchars($product['brand']); ?></strong>
                    </div>
                    
                    <div class="mb-4">
                        <span class="price-tag">¥<?php echo number_format($product['price'], 2); ?></span>
                    </div>
                    
                    <div class="mb-4">
                        <h5>产品描述</h5>
                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <a href="products.php" class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-arrow-left"></i> 返回产品列表
                        </a>
                        <button class="btn btn-primary btn-lg" onclick="addToCompare(<?php echo $product['id']; ?>)">
                            <i class="fas fa-balance-scale"></i> 添加到对比
                        </button>
                    </div>
                </div>
            </div>

            <!-- 详细信息 -->
            <div class="row mt-4">
                <div class="col-12">
                    <?php if (!empty($product['features'])): ?>
                    <div class="product-section">
                        <h5><i class="fas fa-star"></i> 产品特性</h5>
                        <div><?php echo nl2br(htmlspecialchars($product['features'])); ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($product['specifications'])): ?>
                    <div class="product-section">
                        <h5><i class="fas fa-cog"></i> 技术规格</h5>
                        <div><?php echo nl2br(htmlspecialchars($product['specifications'])); ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($product['faq'])): ?>
                    <div class="product-section">
                        <h5><i class="fas fa-question-circle"></i> 常见问题</h5>
                        <div><?php echo nl2br(htmlspecialchars($product['faq'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 相关产品推荐 -->
        <div class="mt-5">
            <h4 class="mb-4"><i class="fas fa-th-large"></i> 相关产品推荐</h4>
            <div class="row">
                <?php
                // 获取同分类的其他产品
                $relatedProducts = $productObj->getAllProducts($product['category_id']);
                $relatedProducts = array_filter($relatedProducts, function($p) use ($productId) {
                    return $p['id'] != $productId;
                });
                $relatedProducts = array_slice($relatedProducts, 0, 3);
                ?>
                
                <?php if (!empty($relatedProducts)): ?>
                    <?php foreach ($relatedProducts as $relatedProduct): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <?php if ($relatedProduct['image_url']): ?>
                            <img src="<?php echo htmlspecialchars($relatedProduct['image_url']); ?>" 
                                 class="card-img-top" 
                                 style="height: 200px; object-fit: cover;"
                                 alt="<?php echo htmlspecialchars($relatedProduct['name']); ?>">
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($relatedProduct['name']); ?></h5>
                                <p class="card-text text-muted"><?php echo htmlspecialchars($relatedProduct['brand']); ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="h5 text-primary mb-0">¥<?php echo number_format($relatedProduct['price'], 2); ?></span>
                                    <a href="product.php?id=<?php echo $relatedProduct['id']; ?>" class="btn btn-primary btn-sm">查看详情</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <p class="text-muted text-center">暂无相关产品</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function addToCompare(productId) {
            // 获取当前对比列表
            let compareList = JSON.parse(localStorage.getItem('compareList') || '[]');
            
            // 检查是否已在对比列表中
            if (compareList.includes(productId)) {
                alert('该产品已在对比列表中');
                return;
            }
            
            // 检查对比列表是否已满
            if (compareList.length >= 10) {
                alert('对比列表已满，最多只能对比10个产品');
                return;
            }
            
            // 添加到对比列表
            compareList.push(productId);
            localStorage.setItem('compareList', JSON.stringify(compareList));
            
            // 提示并跳转
            if (confirm('已添加到对比列表！是否立即前往对比？')) {
                window.location.href = 'products.php';
            }
        }
    </script>
</body>
</html>

