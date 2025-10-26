<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Admin.php';
require_once '../classes/Product.php';

// 检查管理员是否登录
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$admin = new Admin();
$productObj = new Product();

// 处理产品操作
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add':
            $data = [
                'category_id' => $_POST['category_id'],
                'name' => $_POST['name'],
                'brand' => $_POST['brand'],
                'price' => $_POST['price'],
                'image_url' => $_POST['image_url'],
                'description' => $_POST['description'],
                'features' => $_POST['features'],
                'specifications' => $_POST['specifications'],
                'faq' => $_POST['faq']
            ];
            $productObj->addProduct($data);
            break;
        case 'update':
            $productId = $_POST['product_id'];
            $data = [
                'category_id' => $_POST['category_id'],
                'name' => $_POST['name'],
                'brand' => $_POST['brand'],
                'price' => $_POST['price'],
                'image_url' => $_POST['image_url'],
                'description' => $_POST['description'],
                'features' => $_POST['features'],
                'specifications' => $_POST['specifications'],
                'faq' => $_POST['faq']
            ];
            $productObj->updateProduct($productId, $data);
            break;
        case 'delete':
            $productId = $_POST['product_id'];
            $productObj->deleteProduct($productId);
            break;
    }
    
    header('Location: products.php');
    exit;
}

// 获取产品列表
$products = $productObj->getAllProducts();

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
    <title>产品管理 - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border-radius: 8px;
            margin: 2px 0;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
        .navbar-admin {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .product-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- 侧边栏 -->
            <div class="col-md-2 p-0">
                <div class="sidebar">
                    <div class="p-4">
                        <h4><i class="fas fa-cogs"></i> 后台管理</h4>
                        <p class="text-light small"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></p>
                    </div>
                    
                    <nav class="nav flex-column px-3">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-tachometer-alt"></i> 仪表板
                        </a>
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users"></i> 用户管理
                        </a>
                        <a class="nav-link active" href="products.php">
                            <i class="fas fa-gamepad"></i> 产品管理
                        </a>
                        <a class="nav-link" href="categories.php">
                            <i class="fas fa-tags"></i> 分类管理
                        </a>
                        <a class="nav-link" href="chat.php">
                            <i class="fas fa-comments"></i> 聊天管理
                        </a>
                        <a class="nav-link" href="ai.php">
                            <i class="fas fa-robot"></i> AI设置
                        </a>
                        <a class="nav-link" href="settings.php">
                            <i class="fas fa-cog"></i> 系统设置
                        </a>
                        <a class="nav-link" href="admins.php">
                            <i class="fas fa-user-shield"></i> 管理员
                        </a>
                        <a class="nav-link" href="../logout.php">
                            <i class="fas fa-sign-out-alt"></i> 退出登录
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- 主内容区 -->
            <div class="col-md-10 p-0">
                <div class="main-content">
                    <!-- 顶部导航 -->
                    <nav class="navbar navbar-admin">
                        <div class="container-fluid">
                            <div class="navbar-brand">
                                <h5 class="mb-0"><i class="fas fa-gamepad"></i> 产品管理</h5>
                            </div>
                            <div class="navbar-nav">
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                    <i class="fas fa-plus"></i> 添加产品
                                </button>
                            </div>
                        </div>
                    </nav>
                    
                    <!-- 产品列表 -->
                    <div class="p-4">
                        <div class="row">
                            <?php foreach ($products as $product): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="product-card">
                                    <div class="d-flex align-items-center mb-3">
                                        <?php if ($product['image_url']): ?>
                                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                             class="me-3" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                                        <?php endif; ?>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                            <p class="text-muted small mb-0"><?php echo htmlspecialchars($product['brand']); ?></p>
                                            <span class="h6 text-primary">¥<?php echo number_format($product['price'], 2); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <?php echo date('Y-m-d', strtotime($product['created_at'])); ?>
                                        </small>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="editProduct(<?php echo $product['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="deleteProduct(<?php echo $product['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (empty($products)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-gamepad fa-3x text-muted mb-3"></i>
                            <h4 class="text-muted">暂无产品</h4>
                            <p class="text-muted">点击上方按钮添加第一个产品</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 添加产品模态框 -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">添加产品</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">产品名称</label>
                                    <input type="text" class="form-control" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">品牌</label>
                                    <input type="text" class="form-control" name="brand" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">价格</label>
                                    <input type="number" class="form-control" name="price" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">分类</label>
                                    <select class="form-select" name="category_id" required>
                                        <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">产品图片URL</label>
                            <input type="url" class="form-control" name="image_url">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">产品描述</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">产品特性</label>
                            <textarea class="form-control" name="features" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">技术规格</label>
                            <textarea class="form-control" name="specifications" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">常见问题</label>
                            <textarea class="form-control" name="faq" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary">添加产品</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 编辑产品模态框 -->
    <div class="modal fade" id="editProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">编辑产品</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editProductForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="product_id" id="edit_product_id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">产品名称</label>
                                    <input type="text" class="form-control" name="name" id="edit_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">品牌</label>
                                    <input type="text" class="form-control" name="brand" id="edit_brand" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">价格</label>
                                    <input type="number" class="form-control" name="price" id="edit_price" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">分类</label>
                                    <select class="form-select" name="category_id" id="edit_category_id" required>
                                        <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">产品图片URL</label>
                            <input type="url" class="form-control" name="image_url" id="edit_image_url">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">产品描述</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">产品特性</label>
                            <textarea class="form-control" name="features" id="edit_features" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">技术规格</label>
                            <textarea class="form-control" name="specifications" id="edit_specifications" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">常见问题</label>
                            <textarea class="form-control" name="faq" id="edit_faq" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary">保存修改</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 产品数据
        const productsData = <?php echo json_encode($products); ?>;
        
        function editProduct(productId) {
            // 查找产品数据
            const product = productsData.find(p => p.id == productId);
            if (!product) {
                alert('产品不存在');
                return;
            }
            
            // 填充表单
            document.getElementById('edit_product_id').value = product.id;
            document.getElementById('edit_name').value = product.name;
            document.getElementById('edit_brand').value = product.brand;
            document.getElementById('edit_price').value = product.price;
            document.getElementById('edit_category_id').value = product.category_id;
            document.getElementById('edit_image_url').value = product.image_url || '';
            document.getElementById('edit_description').value = product.description || '';
            document.getElementById('edit_features').value = product.features || '';
            document.getElementById('edit_specifications').value = product.specifications || '';
            document.getElementById('edit_faq').value = product.faq || '';
            
            // 显示模态框
            const modal = new bootstrap.Modal(document.getElementById('editProductModal'));
            modal.show();
        }
        
        function deleteProduct(productId) {
            if (confirm('确定要删除这个产品吗？')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="product_id" value="${productId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
