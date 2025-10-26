<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/Product.php';
require_once 'classes/User.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$productIds = explode(',', $_GET['ids'] ?? '');
if (count($productIds) < 2) {
    header('Location: products.php');
    exit;
}

$productObj = new Product();
$result = $productObj->compareProducts($productIds, $_SESSION['user_id']);

if (!$result['success']) {
    $_SESSION['error'] = $result['message'];
    header('Location: products.php');
    exit;
}

$products = $result['products'];
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>产品对比 - <?php echo getSiteName(); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .comparison-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .product-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
        }
        .comparison-row {
            border-bottom: 1px solid #e9ecef;
            padding: 15px 0;
        }
        .comparison-row:last-child {
            border-bottom: none;
        }
        .feature-label {
            font-weight: bold;
            color: #495057;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
        }
        .feature-value {
            padding: 10px;
            color: #6c757d;
        }
        .price-highlight {
            font-size: 1.5rem;
            font-weight: bold;
            color: #28a745;
        }
        .comparison-stats {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
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
            
            <div class="navbar-nav me-auto">
                <a class="nav-link" href="index.php">首页</a>
                <a class="nav-link" href="products.php">产品对比</a>
                <a class="nav-link" href="chat.php">讨论区</a>
                <a class="nav-link" href="profile.php">个人中心</a>
            </div>
            
            <div class="navbar-nav">
                <span class="text-light me-3">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                </span>
                <a href="logout.php" class="btn btn-outline-light">退出</a>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-balance-scale"></i> 产品对比结果</h2>
                    <a href="products.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> 返回产品列表
                    </a>
                </div>
                
                <!-- 对比统计 -->
                <div class="comparison-stats">
                    <div class="row">
                        <div class="col-md-6 text-center">
                            <h5 class="text-primary"><?php echo count($products); ?></h5>
                            <p class="text-muted mb-0">对比产品数</p>
                        </div>
                        <div class="col-md-6 text-center">
                            <h5 class="text-success"><?php echo date('Y-m-d H:i:s'); ?></h5>
                            <p class="text-muted mb-0">对比时间</p>
                        </div>
                    </div>
                </div>
                
                <!-- 对比表格 -->
                <div class="comparison-table">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 200px;">对比项目</th>
                                    <?php foreach ($products as $product): ?>
                                    <th class="text-center" style="width: 250px;">
                                        <div class="product-header">
                                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                                 class="product-image mb-2" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                            <h5><?php echo htmlspecialchars($product['name']); ?></h5>
                                            <p class="mb-0"><?php echo htmlspecialchars($product['brand']); ?></p>
                                        </div>
                                    </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- 价格对比 -->
                                <tr class="comparison-row">
                                    <td class="feature-label">价格</td>
                                    <?php foreach ($products as $product): ?>
                                    <td class="feature-value text-center">
                                        <div class="price-highlight">¥<?php echo number_format($product['price'], 2); ?></div>
                                    </td>
                                    <?php endforeach; ?>
                                </tr>
                                
                                <!-- 品牌对比 -->
                                <tr class="comparison-row">
                                    <td class="feature-label">品牌</td>
                                    <?php foreach ($products as $product): ?>
                                    <td class="feature-value text-center">
                                        <?php echo htmlspecialchars($product['brand']); ?>
                                    </td>
                                    <?php endforeach; ?>
                                </tr>
                                
                                <!-- 产品描述 -->
                                <tr class="comparison-row">
                                    <td class="feature-label">产品描述</td>
                                    <?php foreach ($products as $product): ?>
                                    <td class="feature-value">
                                        <?php echo nl2br(htmlspecialchars(mb_substr($product['description'], 0, 200))); ?>
                                        <?php if (mb_strlen($product['description']) > 200): ?>...<?php endif; ?>
                                    </td>
                                    <?php endforeach; ?>
                                </tr>
                                
                                <!-- 产品特性 -->
                                <?php if (!empty($products[0]['features'])): ?>
                                <tr class="comparison-row">
                                    <td class="feature-label">产品特性</td>
                                    <?php foreach ($products as $product): ?>
                                    <td class="feature-value">
                                        <?php echo nl2br(htmlspecialchars($product['features'])); ?>
                                    </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endif; ?>
                                
                                <!-- 技术规格 -->
                                <?php if (!empty($products[0]['specifications'])): ?>
                                <tr class="comparison-row">
                                    <td class="feature-label">技术规格</td>
                                    <?php foreach ($products as $product): ?>
                                    <td class="feature-value">
                                        <?php echo nl2br(htmlspecialchars($product['specifications'])); ?>
                                    </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endif; ?>
                                
                                
                                <!-- 操作按钮 -->
                                <tr class="comparison-row">
                                    <td class="feature-label">操作</td>
                                    <?php foreach ($products as $product): ?>
                                    <td class="feature-value text-center">
                                        <a href="product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"></i> 查看详情
                                        </a>
                                    </td>
                                    <?php endforeach; ?>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                
                <!-- 分享功能 -->
                <div class="text-center mt-4">
                    <h5>分享对比结果</h5>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-primary" onclick="shareResult('wechat')">
                            <i class="fab fa-weixin"></i> 微信
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="shareResult('weibo')">
                            <i class="fab fa-weibo"></i> 微博
                        </button>
                        <button type="button" class="btn btn-outline-info" onclick="copyLink()">
                            <i class="fas fa-link"></i> 复制链接
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function shareResult(platform) {
            const url = window.location.href;
            const title = '产品对比结果';
            
            if (platform === 'wechat') {
                // 微信分享逻辑
                alert('请复制链接分享到微信：' + url);
            } else if (platform === 'weibo') {
                // 微博分享
                window.open('https://service.weibo.com/share/share.php?url=' + encodeURIComponent(url) + '&title=' + encodeURIComponent(title));
            }
        }
        
        function copyLink() {
            navigator.clipboard.writeText(window.location.href).then(() => {
                alert('链接已复制到剪贴板');
            });
        }
    </script>
</body>
</html>
