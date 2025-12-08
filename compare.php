<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/Product.php';
require_once 'classes/User.php';
require_once 'classes/Markdown.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$productIds = array_values(array_filter(array_unique(array_map('intval', explode(',', $_GET['ids'] ?? '')))));
if (count($productIds) < 2) {
    $_SESSION['compare_error'] = '请至少选择2个产品进行对比';
    header('Location: products.php');
    exit;
}

$productObj = new Product();
$result = $productObj->compareProducts($productIds, $_SESSION['user_id']);

if (!$result['success']) {
    $_SESSION['compare_error'] = $result['message'];
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
        .markdown-content p {
            margin-bottom: 0.5rem;
        }
        .markdown-content ul,
        .markdown-content ol {
            padding-left: 1.25rem;
            margin-bottom: 1rem;
        }
        .comparison-stats {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        /* 首次使用提示框样式 */
        .guide-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }
        .guide-modal {
            background: white;
            border-radius: 20px;
            padding: 0;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.4s ease;
            overflow-x: hidden;
        }
        @media (max-width: 768px) {
            .guide-modal {
                width: 95%;
                max-height: 85vh;
            }
            .guide-modal-header h3 {
                font-size: 1.4rem;
            }
            .guide-modal-body {
                padding: 30px 20px;
            }
            .guide-modal-footer {
                flex-direction: column;
                padding: 20px;
            }
            .guide-modal-footer .btn {
                width: 100%;
            }
        }
        .guide-modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }
        .guide-modal-header h3 {
            margin: 0;
            font-size: 1.8rem;
        }
        .guide-modal-header .close-btn {
            position: absolute;
            top: 15px;
            right: 20px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.2rem;
            transition: all 0.3s;
        }
        .guide-modal-header .close-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }
        .guide-modal-body {
            padding: 40px;
            text-align: center;
        }
        .guide-modal-body .icon {
            font-size: 4rem;
            color: #667eea;
            margin-bottom: 20px;
            animation: bounce 1s ease infinite;
        }
        .guide-modal-body h4 {
            color: #495057;
            margin-bottom: 15px;
        }
        .guide-modal-body p {
            color: #6c757d;
            line-height: 1.8;
            margin-bottom: 30px;
        }
        .guide-modal-footer {
            padding: 20px 40px 40px;
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        .guide-modal-footer .btn {
            padding: 12px 30px;
            font-size: 1rem;
            border-radius: 25px;
            transition: all 0.3s;
        }
        .guide-modal-footer .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .guide-modal-footer .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .guide-modal-footer .btn-outline-secondary:hover {
            transform: translateY(-2px);
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
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

    <!-- 首次使用提示框 -->
    <div id="guideModal" class="guide-modal-overlay" style="display: none;">
        <div class="guide-modal">
            <div class="guide-modal-header">
                <button class="close-btn" onclick="closeGuideModal()">
                    <i class="fas fa-times"></i>
                </button>
                <h3><i class="fas fa-lightbulb"></i> 欢迎使用对比功能！</h3>
            </div>
            <div class="guide-modal-body">
                <div class="icon">
                    <i class="fas fa-book-open"></i>
                </div>
                <h4>第一次使用产品对比？</h4>
                <p>
                    我们为您准备了详细的使用指南，帮助您快速了解如何使用对比功能，<br>
                    找到最适合您的产品。建议您先查看指南，这样能更好地使用对比功能。
                </p>
            </div>
            <div class="guide-modal-footer">
                <a href="guide.php" class="btn btn-primary">
                    <i class="fas fa-book"></i> 查看指南列表
                </a>
                <button class="btn btn-outline-secondary" onclick="closeGuideModal()">
                    <i class="fas fa-times"></i> 稍后查看
                </button>
            </div>
        </div>
    </div>

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
                                    <?php
                                        $descriptionPlain = Markdown::toPlainText($product['description']);
                                        $descriptionShort = mb_substr($descriptionPlain, 0, 200);
                                    ?>
                                    <td class="feature-value">
                                        <?php echo nl2br(htmlspecialchars($descriptionShort)); ?>
                                        <?php if (mb_strlen($descriptionPlain) > 200): ?>...<?php endif; ?>
                                    </td>
                                    <?php endforeach; ?>
                                </tr>
                                
                                <!-- 产品特性 -->
                                <?php if (!empty($products[0]['features'])): ?>
                                <tr class="comparison-row">
                                    <td class="feature-label">产品特性</td>
                                    <?php foreach ($products as $product): ?>
                                    <td class="feature-value markdown-content">
                                        <?php echo Markdown::toHtml($product['features']); ?>
                                    </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endif; ?>
                                
                                <!-- 技术规格 -->
                                <?php if (!empty($products[0]['specifications'])): ?>
                                <tr class="comparison-row">
                                    <td class="feature-label">技术规格</td>
                                    <?php foreach ($products as $product): ?>
                                    <td class="feature-value markdown-content">
                                        <?php echo Markdown::toHtml($product['specifications']); ?>
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
        // 检查是否首次使用对比功能
        function checkFirstTimeCompare() {
            const userId = <?php echo $_SESSION['user_id']; ?>;
            const storageKey = 'guide_shown_user_' + userId;
            const hasSeenGuide = localStorage.getItem(storageKey);
            
            if (!hasSeenGuide) {
                // 显示提示框
                document.getElementById('guideModal').style.display = 'flex';
            }
        }
        
        // 关闭提示框
        function closeGuideModal() {
            const userId = <?php echo $_SESSION['user_id']; ?>;
            const storageKey = 'guide_shown_user_' + userId;
            localStorage.setItem(storageKey, 'true');
            document.getElementById('guideModal').style.display = 'none';
        }
        
        // 点击遮罩层关闭
        document.getElementById('guideModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeGuideModal();
            }
        });
        
        // 页面加载时检查
        document.addEventListener('DOMContentLoaded', function() {
            checkFirstTimeCompare();
        });
        
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
