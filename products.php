<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/Product.php';
require_once 'classes/Markdown.php';

$productObj = new Product();
$categoryId = $_GET['category'] ?? null;
$tagName = isset($_GET['tag']) ? urldecode($_GET['tag']) : null;
$brandName = isset($_GET['brand']) ? urldecode($_GET['brand']) : null;

// 根据标签、品牌或分类获取产品
if ($tagName) {
    // 如果选择了标签，获取该标签下的产品
    $products = $productObj->getProductsByTagName($tagName);
    
    // 如果同时选择了分类，进一步筛选
    if ($categoryId) {
        $products = array_filter($products, function($product) use ($categoryId) {
            return $product['category_id'] == $categoryId;
        });
        $products = array_values($products);
    }
    
    // 如果同时选择了品牌，进一步筛选
    if ($brandName) {
        $products = array_filter($products, function($product) use ($brandName) {
            return $product['brand'] == $brandName;
        });
        $products = array_values($products);
    }
} elseif ($brandName) {
    // 如果只选择了品牌，获取该品牌的产品
    $products = $productObj->getAllProducts($categoryId);
    $products = array_filter($products, function($product) use ($brandName) {
        return $product['brand'] == $brandName;
    });
    $products = array_values($products);
} else {
    // 否则按分类获取
    $products = $productObj->getAllProducts($categoryId);
}

// 为每个产品添加标签信息
foreach ($products as $key => $product) {
    $products[$key]['tags'] = $productObj->getProductTags($product['id']);
}

// 获取分类
$db = new Database();
$pdo = $db->getConnection();
$stmt = $pdo->prepare("SELECT * FROM categories ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取所有有产品关联的标签（只显示实际使用的标签）
$allTags = $productObj->getActiveTags();

// 获取所有有产品的品牌（只显示实际使用的品牌）
$stmt = $pdo->prepare("SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand != '' ORDER BY brand");
$stmt->execute();
$allBrands = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取当前用户的对比限制
$compareLimits = $productObj->getCompareLimitsForUser($_SESSION['user_id'] ?? null);
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
                    <a class="nav-link active" href="products.php">产品对比</a>
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

    <div class="container my-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0"><i class="fas fa-balance-scale"></i> 产品对比</h2>
            <?php if ($tagName || $categoryId || $brandName): ?>
            <div>
                <?php if ($tagName): ?>
                <span class="badge bg-primary me-2">
                    <i class="fas fa-tag"></i> 标签：<?php echo htmlspecialchars($tagName); ?>
                    <a href="products.php<?php 
                        $params = [];
                        if ($categoryId) $params[] = 'category=' . $categoryId;
                        if ($brandName) $params[] = 'brand=' . urlencode($brandName);
                        echo $params ? '?' . implode('&', $params) : '';
                    ?>" class="text-white ms-2" style="text-decoration: none;">
                        <i class="fas fa-times"></i>
                    </a>
                </span>
                <?php endif; ?>
                <?php if ($brandName): ?>
                <span class="badge bg-success me-2">
                    <i class="fas fa-trademark"></i> 品牌：<?php echo htmlspecialchars($brandName); ?>
                    <a href="products.php<?php 
                        $params = [];
                        if ($categoryId) $params[] = 'category=' . $categoryId;
                        if ($tagName) $params[] = 'tag=' . urlencode($tagName);
                        echo $params ? '?' . implode('&', $params) : '';
                    ?>" class="text-white ms-2" style="text-decoration: none;">
                        <i class="fas fa-times"></i>
                    </a>
                </span>
                <?php endif; ?>
                <?php if ($categoryId): ?>
                    <?php 
                    $currentCategory = null;
                    foreach ($categories as $cat) {
                        if ($cat['id'] == $categoryId) {
                            $currentCategory = $cat;
                            break;
                        }
                    }
                    ?>
                    <?php if ($currentCategory): ?>
                    <span class="badge bg-info me-2">
                        <i class="fas fa-folder"></i> 分类：<?php echo htmlspecialchars($currentCategory['name']); ?>
                        <a href="products.php<?php 
                            $params = [];
                            if ($tagName) $params[] = 'tag=' . urlencode($tagName);
                            if ($brandName) $params[] = 'brand=' . urlencode($brandName);
                            echo $params ? '?' . implode('&', $params) : '';
                        ?>" class="text-white ms-2" style="text-decoration: none;">
                            <i class="fas fa-times"></i>
                        </a>
                    </span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if (isset($_SESSION['compare_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['compare_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['compare_error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['compare_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['compare_success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['compare_success']); ?>
        <?php endif; ?>
        
        <!-- 筛选区域 -->
        <div class="filter-section">
            <div class="row">
                <div class="col-md-2">
                    <label class="form-label">分类筛选</label>
                    <select class="form-select" id="categoryFilter" onchange="filterByCategory(this.value)">
                        <option value="">全部分类</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo $categoryId == $category['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">标签筛选</label>
                    <select class="form-select" id="tagFilter" onchange="filterByTag(this.value)">
                        <option value="">全部标签</option>
                        <?php foreach ($allTags as $tag): ?>
                        <option value="<?php echo htmlspecialchars($tag['name']); ?>" <?php echo $tagName == $tag['name'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tag['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">品牌筛选</label>
                    <select class="form-select" id="brandFilter" onchange="filterByBrand(this.value)">
                        <option value="">全部品牌</option>
                        <?php foreach ($allBrands as $brand): ?>
                        <option value="<?php echo htmlspecialchars($brand['brand']); ?>" <?php echo $brandName == $brand['brand'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($brand['brand']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
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
                <div class="col-md-2">
                    <label class="form-label">排序方式</label>
                    <select class="form-select" onchange="sortProducts(this.value)">
                        <option value="newest">最新发布</option>
                        <option value="price_low">价格从低到高</option>
                        <option value="price_high">价格从高到低</option>
                        <option value="name">按名称排序</option>
                    </select>
                </div>
                <div class="col-md-2">
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
            <div class="col-md-4 mb-4 product-item" data-id="<?php echo $product['id']; ?>" data-price="<?php echo $product['price']; ?>" data-name="<?php echo strtolower($product['name']); ?>">
                <div class="card product-card h-100 position-relative">
                    <?php if ($product['image_url']): ?>
                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>" style="height: 200px; object-fit: cover;">
                    <?php endif; ?>
                    
                    <!-- 对比按钮 -->
                    <button class="btn btn-sm btn-outline-primary compare-btn" data-id="<?php echo $product['id']; ?>" onclick="toggleCompare(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')">
                        <i class="fas fa-plus"></i> 对比
                    </button>
                    
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                        <p class="card-text text-muted"><?php echo htmlspecialchars($product['brand']); ?></p>
                        <?php
                            $productDescription = Markdown::toPlainText($product['description']);
                            $productDescriptionShort = mb_substr($productDescription, 0, 100);
                        ?>
                        <p class="card-text"><?php echo htmlspecialchars($productDescriptionShort); ?>...</p>
                        
                        <?php if (!empty($product['tags'])): ?>
                        <div class="mb-2">
                            <?php foreach ($product['tags'] as $tag): ?>
                            <a href="tag.php?name=<?php echo urlencode($tag['name']); ?>" class="badge bg-secondary text-decoration-none me-1">
                                <?php echo htmlspecialchars($tag['name']); ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
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
            <div class="text-muted small mt-2">
                今日已用 <?php echo $compareLimits['today']; ?>/<?php echo $compareLimits['max_daily']; ?> 次，单次最多可对比 <?php echo $compareLimits['max_products']; ?> 个产品
            </div>
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
        const productsData = <?php echo json_encode($products, JSON_UNESCAPED_UNICODE); ?>;
        const productMap = {};
        productsData.forEach(p => { productMap[p.id] = {id: p.id, name: p.name}; });

        let selectedProducts = [];
        const maxCompare = <?php echo (int)$compareLimits['max_products']; ?>;

        // 从 localStorage 载入对比列表
        function loadCompareFromStorage() {
            const stored = localStorage.getItem('compareList');
            if (!stored) return;
            try {
                const ids = JSON.parse(stored);
                if (Array.isArray(ids)) {
                    selectedProducts = ids
                        .filter(id => Number.isInteger(id) || typeof id === 'number')
                        .map(id => productMap[id] || {id, name: `产品 #${id}`});
                }
            } catch (e) {
                console.warn('compareList parse error', e);
            }
        }

        function syncCompareStorage() {
            localStorage.setItem('compareList', JSON.stringify(selectedProducts.map(p => p.id)));
        }
        
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
            syncCompareStorage();
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

            // 同步按钮选中状态
            document.querySelectorAll('.compare-btn').forEach(btn => {
                const id = parseInt(btn.dataset.id, 10);
                if (!id) return;
                const chosen = selectedProducts.some(p => p.id === id);
                btn.classList.toggle('btn-primary', chosen);
                btn.classList.toggle('btn-outline-primary', !chosen);
                btn.innerHTML = chosen ? '<i class="fas fa-check"></i> 已选' : '<i class="fas fa-plus"></i> 对比';
            });
        }
        
        function removeFromCompare(productId) {
            selectedProducts = selectedProducts.filter(p => p.id !== productId);
            updateSelectedProducts();
            syncCompareStorage();
        }
        
        function clearSelection() {
            selectedProducts = [];
            updateSelectedProducts();
            syncCompareStorage();
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
            const tagFilter = document.getElementById('tagFilter');
            const brandFilter = document.getElementById('brandFilter');
            const tagName = tagFilter ? tagFilter.value : '';
            const brandName = brandFilter ? brandFilter.value : '';
            let url = 'products.php';
            const params = [];
            
            if (categoryId) {
                params.push(`category=${categoryId}`);
            }
            if (tagName) {
                params.push(`tag=${encodeURIComponent(tagName)}`);
            }
            if (brandName) {
                params.push(`brand=${encodeURIComponent(brandName)}`);
            }
            
            if (params.length > 0) {
                url += '?' + params.join('&');
            }
            
            window.location.href = url;
        }
        
        function filterByTag(tagName) {
            const categoryFilter = document.getElementById('categoryFilter');
            const brandFilter = document.getElementById('brandFilter');
            const categoryId = categoryFilter ? categoryFilter.value : '';
            const brandName = brandFilter ? brandFilter.value : '';
            let url = 'products.php';
            const params = [];
            
            if (categoryId) {
                params.push(`category=${categoryId}`);
            }
            if (tagName) {
                params.push(`tag=${encodeURIComponent(tagName)}`);
            }
            if (brandName) {
                params.push(`brand=${encodeURIComponent(brandName)}`);
            }
            
            if (params.length > 0) {
                url += '?' + params.join('&');
            }
            
            window.location.href = url;
        }
        
        function filterByBrand(brandName) {
            const categoryFilter = document.getElementById('categoryFilter');
            const tagFilter = document.getElementById('tagFilter');
            const categoryId = categoryFilter ? categoryFilter.value : '';
            const tagName = tagFilter ? tagFilter.value : '';
            let url = 'products.php';
            const params = [];
            
            if (categoryId) {
                params.push(`category=${categoryId}`);
            }
            if (tagName) {
                params.push(`tag=${encodeURIComponent(tagName)}`);
            }
            if (brandName) {
                params.push(`brand=${encodeURIComponent(brandName)}`);
            }
            
            if (params.length > 0) {
                url += '?' + params.join('&');
            }
            
            window.location.href = url;
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

        // 页面加载时恢复对比列表
        document.addEventListener('DOMContentLoaded', () => {
            loadCompareFromStorage();
            updateSelectedProducts();
        });
    </script>
</body>
</html>
