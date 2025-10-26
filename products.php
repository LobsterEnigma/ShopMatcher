<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/Product.php';

$productObj = new Product();
$categoryId = $_GET['category'] ?? null;
$products = $productObj->getAllProducts($categoryId);

// 获取分类
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
    <title>产品对比 - <?php echo getSiteName(); ?></title>
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
        .compare-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 10;
        }
        .selected-products {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            padding: 15px;
            max-width: 300px;
        }
        .filter-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .price-range {
            color: #28a745;
            font-weight: bold;
        }
        .ai-summary-badge {
            background: linear-gradient(45deg, #ff6b6b, #feca57);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
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
                <a class="nav-link active" href="products.php">产品对比</a>
                <a class="nav-link" href="chat.php">讨论区</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                <a class="nav-link" href="profile.php">个人中心</a>
                <?php endif; ?>
            </div>
            
            <div class="navbar-nav">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="logout.php" class="btn btn-outline-light">退出</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline-light me-2">登录</a>
                    <a href="register.php" class="btn btn-primary">注册</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <h2 class="mb-4"><i class="fas fa-balance-scale"></i> 产品对比</h2>
        
        <!-- 筛选区域 -->
        <div class="filter-section">
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label">分类筛选</label>
                    <select class="form-select" onchange="filterByCategory(this.value)">
                        <option value="">全部分类</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo $categoryId == $category['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">价格范围</label>
                    <select class="form-select" onchange="filterByPrice(this.value)">
                        <option value="">全部价格</option>
                        <option value="0-100">100元以下</option>
                        <option value="100-300">100-300元</option>
                        <option value="300-500">300-500元</option>
                        <option value="500-1000">500-1000元</option>
                        <option value="1000+">1000元以上</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">排序方式</label>
                    <select class="form-select" onchange="sortProducts(this.value)">
                        <option value="newest">最新发布</option>
                        <option value="price_low">价格从低到高</option>
                        <option value="price_high">价格从高到低</option>
                        <option value="name">按名称排序</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">搜索产品</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="searchInput" placeholder="输入产品名称...">
                        <button class="btn btn-outline-secondary" onclick="searchProducts()">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 产品列表 -->
        <div class="row" id="productsContainer">
            <?php foreach ($products as $product): ?>
            <div class="col-md-4 mb-4 product-item" data-price="<?php echo $product['price']; ?>" data-name="<?php echo strtolower($product['name']); ?>">
                <div class="card product-card h-100 position-relative">
                    <?php if ($product['image_url']): ?>
                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>" style="height: 200px; object-fit: cover;">
                    <?php endif; ?>
                    
                    <!-- 对比按钮 -->
                    <button class="btn btn-sm btn-outline-primary compare-btn" onclick="toggleCompare(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')">
                        <i class="fas fa-plus"></i> 对比
                    </button>
                    
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                        <p class="card-text text-muted"><?php echo htmlspecialchars($product['brand']); ?></p>
                        <p class="card-text"><?php echo mb_substr(strip_tags($product['description']), 0, 100); ?>...</p>
                        
                        <?php if ($product['ai_summary']): ?>
                        <div class="mb-2">
                            <span class="ai-summary-badge">
                                <i class="fas fa-robot"></i> AI总结
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mt-auto">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="h5 text-primary price-range">¥<?php echo number_format($product['price'], 2); ?></span>
                                <div>
                                    <a href="product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary btn-sm">查看详情</a>
                                    <?php if ($product['ai_summary']): ?>
                                    <button class="btn btn-warning btn-sm" onclick="viewAISummary(<?php echo $product['id']; ?>)">
                                        <i class="fas fa-robot"></i> AI总结
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($products)): ?>
        <div class="text-center py-5">
            <i class="fas fa-search fa-3x text-muted mb-3"></i>
            <h4 class="text-muted">暂无产品</h4>
            <p class="text-muted">请尝试其他筛选条件</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- 已选产品对比栏 -->
    <div id="selectedProducts" class="selected-products" style="display: none;">
        <h6><i class="fas fa-balance-scale"></i> 已选产品 (<span id="selectedCount">0</span>)</h6>
        <div id="selectedList"></div>
        <div class="mt-3">
            <button class="btn btn-primary btn-sm" onclick="startCompare()" id="compareBtn" disabled>
                <i class="fas fa-balance-scale"></i> 开始对比
            </button>
            <button class="btn btn-outline-secondary btn-sm" onclick="clearSelection()">
                <i class="fas fa-trash"></i> 清空
            </button>
        </div>
    </div>

    <!-- AI总结模态框 -->
    <div class="modal fade" id="aiSummaryModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-robot"></i> AI产品总结</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="aiSummaryContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">加载中...</span>
                        </div>
                        <p class="mt-2">AI正在分析产品信息...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedProducts = [];
        const maxCompare = <?php echo isset($_SESSION['user_id']) && isset($_SESSION['vip_level']) ? 10 : 2; ?>;
        
        function toggleCompare(productId, productName) {
            const index = selectedProducts.findIndex(p => p.id === productId);
            
            if (index > -1) {
                // 移除
                selectedProducts.splice(index, 1);
            } else {
                // 添加
                if (selectedProducts.length >= maxCompare) {
                    alert('最多只能对比' + maxCompare + '个产品');
                    return;
                }
                selectedProducts.push({id: productId, name: productName});
            }
            
            updateSelectedProducts();
        }
        
        function updateSelectedProducts() {
            const container = document.getElementById('selectedProducts');
            const count = document.getElementById('selectedCount');
            const list = document.getElementById('selectedList');
            const compareBtn = document.getElementById('compareBtn');
            
            count.textContent = selectedProducts.length;
            
            if (selectedProducts.length > 0) {
                container.style.display = 'block';
                list.innerHTML = selectedProducts.map(p => 
                    `<div class="d-flex justify-content-between align-items-center mb-1">
                        <span>${p.name}</span>
                        <button class="btn btn-sm btn-outline-danger" onclick="removeFromCompare(${p.id})">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>`
                ).join('');
                compareBtn.disabled = selectedProducts.length < 2;
            } else {
                container.style.display = 'none';
            }
        }
        
        function removeFromCompare(productId) {
            selectedProducts = selectedProducts.filter(p => p.id !== productId);
            updateSelectedProducts();
        }
        
        function clearSelection() {
            selectedProducts = [];
            updateSelectedProducts();
        }
        
        function startCompare() {
            if (selectedProducts.length < 2) {
                alert('请至少选择2个产品进行对比');
                return;
            }
            
            const productIds = selectedProducts.map(p => p.id).join(',');
            window.location.href = `compare.php?ids=${productIds}`;
        }
        
        function viewAISummary(productId) {
            const modal = new bootstrap.Modal(document.getElementById('aiSummaryModal'));
            modal.show();
            
            // 调用AI总结API
            fetch('api/ai_summary.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({product_id: productId})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('aiSummaryContent').innerHTML = `
                        <div class="ai-summary">
                            <div class="rating mb-3">
                                <h6>AI评分：${data.rating}星</h6>
                                <div class="stars">
                                    ${'★'.repeat(data.rating)}${'☆'.repeat(5-data.rating)}
                                </div>
                            </div>
                            <div class="summary">
                                <h6>产品总结：</h6>
                                <p>${data.summary}</p>
                            </div>
                            <div class="recommendation">
                                <h6>推荐原因：</h6>
                                <p>${data.reason}</p>
                            </div>
                        </div>
                    `;
                } else {
                    document.getElementById('aiSummaryContent').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> ${data.message}
                        </div>
                    `;
                }
            })
            .catch(error => {
                document.getElementById('aiSummaryContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> 加载失败，请稍后重试
                    </div>
                `;
            });
        }
        
        function filterByCategory(categoryId) {
            if (categoryId) {
                window.location.href = `products.php?category=${categoryId}`;
            } else {
                window.location.href = 'products.php';
            }
        }
        
        function filterByPrice(priceRange) {
            const products = document.querySelectorAll('.product-item');
            products.forEach(product => {
                const price = parseFloat(product.dataset.price);
                let show = true;
                
                if (priceRange) {
                    if (priceRange === '0-100') {
                        show = price < 100;
                    } else if (priceRange === '100-300') {
                        show = price >= 100 && price < 300;
                    } else if (priceRange === '300-500') {
                        show = price >= 300 && price < 500;
                    } else if (priceRange === '500-1000') {
                        show = price >= 500 && price < 1000;
                    } else if (priceRange === '1000+') {
                        show = price >= 1000;
                    }
                }
                
                product.style.display = show ? 'block' : 'none';
            });
        }
        
        function sortProducts(sortBy) {
            const container = document.getElementById('productsContainer');
            const products = Array.from(container.children);
            
            products.sort((a, b) => {
                switch(sortBy) {
                    case 'price_low':
                        return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
                    case 'price_high':
                        return parseFloat(b.dataset.price) - parseFloat(a.dataset.price);
                    case 'name':
                        return a.dataset.name.localeCompare(b.dataset.name);
                    default:
                        return 0;
                }
            });
            
            products.forEach(product => container.appendChild(product));
        }
        
        function searchProducts() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const products = document.querySelectorAll('.product-item');
            
            products.forEach(product => {
                const name = product.dataset.name;
                const show = name.includes(searchTerm);
                product.style.display = show ? 'block' : 'none';
            });
        }
        
        // 搜索框回车事件
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchProducts();
            }
        });
    </script>
</body>
</html>
