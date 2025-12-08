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

function handleProductImageUpload(string $fieldName = 'image_file')
{
    if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
        return null;
    }
    
    $file = $_FILES[$fieldName];
    if ($file['error'] === UPLOAD_ERR_NO_FILE || empty($file['name'])) {
        return null;
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['product_error'] = '图片上传失败，请重试。';
        return false;
    }
    
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        $_SESSION['product_error'] = '图片大小不能超过5MB。';
        return false;
    }
    
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    
    // 优先使用 fileinfo，若未安装扩展则回退到其他可用方法
    $mimeType = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
    } elseif (function_exists('mime_content_type')) {
        $mimeType = mime_content_type($file['tmp_name']);
    } elseif (function_exists('getimagesize')) {
        $info = @getimagesize($file['tmp_name']);
        if ($info && isset($info['mime'])) {
            $mimeType = $info['mime'];
        }
    }
    
    if (!$mimeType || !isset($allowedTypes[$mimeType])) {
        $_SESSION['product_error'] = '仅支持上传 JPG/PNG/GIF/WebP 图片。';
        return false;
    }
    
    $uploadDir = dirname(__DIR__) . '/upload/image/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        $_SESSION['product_error'] = '无法创建上传目录，请检查权限。';
        return false;
    }
    
    $fileName = uniqid('product_', true) . '.' . $allowedTypes[$mimeType];
    $destination = $uploadDir . $fileName;
    
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        $_SESSION['product_error'] = '保存上传图片失败，请稍后重试。';
        return false;
    }
    
    return '/upload/image/' . $fileName;
}

// 处理产品操作
if ($_POST) {
    $action = $_POST['action'] ?? '';
    $uploadedImagePath = null;
    
    if (in_array($action, ['add', 'update'])) {
        $uploadedImagePath = handleProductImageUpload();
        if ($uploadedImagePath === false) {
            header('Location: products.php');
            exit;
        }
    }
    
    switch ($action) {
        case 'add':
            $imageUrl = $uploadedImagePath ?? ($_POST['image_url'] ?? '');
            $data = [
                'category_id' => $_POST['category_id'],
                'name' => $_POST['name'],
                'brand' => $_POST['brand'],
                'price' => $_POST['price'],
                'image_url' => $imageUrl,
                'description' => $_POST['description'],
                'features' => $_POST['features'],
                'specifications' => $_POST['specifications'],
                'faq' => $_POST['faq'],
                'tags' => isset($_POST['tags']) ? explode(',', $_POST['tags']) : []
            ];
            $productObj->addProduct($data);
            $_SESSION['product_success'] = '产品添加成功';
            break;
        case 'update':
            $productId = $_POST['product_id'];
            $imageUrl = $uploadedImagePath ?? ($_POST['image_url'] ?? '');
            $data = [
                'category_id' => $_POST['category_id'],
                'name' => $_POST['name'],
                'brand' => $_POST['brand'],
                'price' => $_POST['price'],
                'image_url' => $imageUrl,
                'description' => $_POST['description'],
                'features' => $_POST['features'],
                'specifications' => $_POST['specifications'],
                'faq' => $_POST['faq'],
                'tags' => isset($_POST['tags']) ? explode(',', $_POST['tags']) : []
            ];
            $productObj->updateProduct($productId, $data);
            $_SESSION['product_success'] = '产品信息已更新';
            break;
        case 'delete':
            $productId = $_POST['product_id'];
            $productObj->deleteProduct($productId);
            $_SESSION['product_success'] = '产品已删除';
            break;
    }
    
    header('Location: products.php');
    exit;
}

// 获取产品列表
$products = $productObj->getAllProducts();

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

// 获取所有标签（用于标签输入提示）
$allTags = $productObj->getAllTags();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>产品管理 - <?php echo getSiteName(); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- EasyMDE Markdown Editor -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">
    <script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>
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
        /* Markdown编辑器样式 */
        .EasyMDEContainer {
            margin-bottom: 15px;
        }
        .EasyMDEContainer .CodeMirror {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            min-height: 200px;
        }
        .EasyMDEContainer .editor-toolbar {
            border: 1px solid #dee2e6;
            border-bottom: none;
            border-radius: 0.375rem 0.375rem 0 0;
        }
        .EasyMDEContainer .editor-toolbar button {
            color: #495057;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: auto;
        }
        .EasyMDEContainer .editor-toolbar button:hover,
        .EasyMDEContainer .editor-toolbar button.active {
            background: #e9ecef;
            border-color: #dee2e6;
        }
        .EasyMDEContainer .editor-preview {
            border: 1px solid #dee2e6;
            border-top: none;
            border-radius: 0 0 0.375rem 0.375rem;
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
                        <a class="nav-link" href="guide.php">
                            <i class="fas fa-book"></i> 指南管理
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
                        <?php if (isset($_SESSION['product_success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['product_success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['product_success']); ?>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['product_error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['product_error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['product_error']); ?>
                        <?php endif; ?>
                        
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
                <form method="POST" id="addProductForm" onsubmit="syncAddEditors()" enctype="multipart/form-data">
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
                            <label class="form-label">或上传本地图片</label>
                            <input type="file" class="form-control" name="image_file" accept="image/*">
                            <small class="text-muted">支持 JPG/PNG/GIF/WebP，大小不超过5MB。上传后将覆盖上方图片URL。</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">产品描述</label>
                            <textarea class="form-control markdown-editor" name="description" id="add_description" rows="5"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">产品特性</label>
                            <textarea class="form-control markdown-editor" name="features" id="add_features" rows="5"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">技术规格</label>
                            <textarea class="form-control markdown-editor" name="specifications" id="add_specifications" rows="5"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">常见问题</label>
                            <textarea class="form-control markdown-editor" name="faq" id="add_faq" rows="5"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">标签 <small class="text-muted">(用逗号分隔，例如：热门,新品,推荐)</small></label>
                            <input type="text" class="form-control" name="tags" id="add_tags" 
                                   placeholder="输入标签，用逗号分隔">
                            <small class="form-text text-muted">可选，用于分类和搜索产品</small>
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
                <form method="POST" id="editProductForm" onsubmit="syncEditEditors()" enctype="multipart/form-data">
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
                            <label class="form-label">或上传本地图片</label>
                            <input type="file" class="form-control" name="image_file" accept="image/*">
                            <small class="text-muted">支持 JPG/PNG/GIF/WebP，大小不超过5MB。上传后将覆盖上方图片URL。</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">产品描述</label>
                            <textarea class="form-control markdown-editor" name="description" id="edit_description" rows="5"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">产品特性</label>
                            <textarea class="form-control markdown-editor" name="features" id="edit_features" rows="5"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">技术规格</label>
                            <textarea class="form-control markdown-editor" name="specifications" id="edit_specifications" rows="5"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">常见问题</label>
                            <textarea class="form-control markdown-editor" name="faq" id="edit_faq" rows="5"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">标签 <small class="text-muted">(用逗号分隔，例如：热门,新品,推荐)</small></label>
                            <input type="text" class="form-control" name="tags" id="edit_tags" 
                                   placeholder="输入标签，用逗号分隔">
                            <small class="form-text text-muted">可选，用于分类和搜索产品</small>
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
        
        // Markdown编辑器实例
        let editors = {
            add: {},
            edit: {}
        };
        
        // EasyMDE配置
        const editorConfig = {
            spellChecker: false,
            placeholder: '支持Markdown语法，输入内容后可以点击预览查看效果...',
            status: ['lines', 'words', 'cursor'],
            toolbar: [
                'bold', 'italic', 'heading', '|',
                'quote', 'unordered-list', 'ordered-list', '|',
                'link', 'image', 'table', '|',
                'preview', 'side-by-side', 'fullscreen', '|',
                'guide'
            ],
            minHeight: '200px',
            autofocus: false
        };
        
        // 初始化添加产品的编辑器
        function initAddEditors() {
            if (Object.keys(editors.add).length === 0) {
                editors.add.description = new EasyMDE({
                    element: document.getElementById('add_description'),
                    ...editorConfig
                });
                editors.add.features = new EasyMDE({
                    element: document.getElementById('add_features'),
                    ...editorConfig
                });
                editors.add.specifications = new EasyMDE({
                    element: document.getElementById('add_specifications'),
                    ...editorConfig
                });
                editors.add.faq = new EasyMDE({
                    element: document.getElementById('add_faq'),
                    ...editorConfig
                });
            }
        }
        
        // 初始化编辑产品的编辑器
        function initEditEditors() {
            if (Object.keys(editors.edit).length === 0) {
                editors.edit.description = new EasyMDE({
                    element: document.getElementById('edit_description'),
                    ...editorConfig
                });
                editors.edit.features = new EasyMDE({
                    element: document.getElementById('edit_features'),
                    ...editorConfig
                });
                editors.edit.specifications = new EasyMDE({
                    element: document.getElementById('edit_specifications'),
                    ...editorConfig
                });
                editors.edit.faq = new EasyMDE({
                    element: document.getElementById('edit_faq'),
                    ...editorConfig
                });
            }
        }
        
        // 页面加载时初始化编辑器
        document.addEventListener('DOMContentLoaded', function() {
            // 延迟初始化，确保DOM完全加载
            setTimeout(() => {
                initAddEditors();
                initEditEditors();
            }, 100);
        });
        
        // 当添加产品模态框显示时初始化编辑器
        document.getElementById('addProductModal').addEventListener('shown.bs.modal', function() {
            initAddEditors();
        });
        
        // 当添加产品模态框关闭时清空编辑器
        document.getElementById('addProductModal').addEventListener('hidden.bs.modal', function() {
            if (editors.add.description) editors.add.description.value('');
            if (editors.add.features) editors.add.features.value('');
            if (editors.add.specifications) editors.add.specifications.value('');
            if (editors.add.faq) editors.add.faq.value('');
        });
        
        // 当编辑产品模态框关闭时清理
        document.getElementById('editProductModal').addEventListener('hidden.bs.modal', function() {
            currentEditProduct = null;
        });
        
        // 当前编辑的产品数据
        let currentEditProduct = null;
        
        // 当编辑产品模态框显示时初始化编辑器和设置内容
        document.getElementById('editProductModal').addEventListener('shown.bs.modal', function() {
            initEditEditors();
            
            // 如果有待设置的产品数据，设置编辑器内容
            if (currentEditProduct) {
                setTimeout(() => {
                    if (editors.edit.description) {
                        editors.edit.description.value(currentEditProduct.description || '');
                    }
                    if (editors.edit.features) {
                        editors.edit.features.value(currentEditProduct.features || '');
                    }
                    if (editors.edit.specifications) {
                        editors.edit.specifications.value(currentEditProduct.specifications || '');
                    }
                    if (editors.edit.faq) {
                        editors.edit.faq.value(currentEditProduct.faq || '');
                    }
                }, 100);
            }
        });
        
        function editProduct(productId) {
            // 查找产品数据
            const product = productsData.find(p => p.id == productId);
            if (!product) {
                alert('产品不存在');
                return;
            }
            
            // 保存产品数据供模态框显示时使用
            currentEditProduct = product;
            
            // 填充表单
            document.getElementById('edit_product_id').value = product.id;
            document.getElementById('edit_name').value = product.name;
            document.getElementById('edit_brand').value = product.brand;
            document.getElementById('edit_price').value = product.price;
            document.getElementById('edit_category_id').value = product.category_id;
            document.getElementById('edit_image_url').value = product.image_url || '';
            
            // 填充标签
            const tags = product.tags || [];
            const tagNames = tags.map(t => t.name).join(',');
            document.getElementById('edit_tags').value = tagNames;
            
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
