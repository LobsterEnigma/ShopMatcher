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

// 获取分类（必须在switch之前定义，因为case中会用到）
$db = new Database();
$pdo = $db->getConnection();
$stmt = $pdo->prepare("SELECT * FROM categories ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                'tags' => isset($_POST['tags']) ? explode(',', $_POST['tags']) : [],
                'specification_mode' => $_POST['specification_mode'] ?? 'markdown'
            ];
            $productId = $productObj->addProduct($data);
            
            // 如果是手柄分类且使用标签化模式，保存技术规格标签
            $categoryId = (int)$_POST['category_id'];
            $specMode = $_POST['specification_mode'] ?? 'markdown';
            $categoryName = '';
            
            // 确保 $categories 已定义
            if (!isset($categories) || !is_array($categories)) {
                error_log("错误：\$categories 未定义或不是数组，重新获取");
                $db = new Database();
                $pdo = $db->getConnection();
                $stmt = $pdo->prepare("SELECT * FROM categories ORDER BY name");
                $stmt->execute();
                $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            foreach ($categories as $cat) {
                if ($cat['id'] == $categoryId) {
                    $categoryName = $cat['name'];
                    break;
                }
            }
            
            if ($categoryName === '手柄' && $specMode === 'tagged' && $productId) {
                $specData = [];
                foreach ($_POST as $key => $value) {
                    if (strpos($key, 'spec_field_') === 0) {
                        $fieldId = str_replace('spec_field_', '', $key);
                        if (!empty($value)) {
                            // 处理多个值（用逗号分隔）
                            $values = array_filter(array_map('trim', explode(',', $value)));
                            if (!empty($values)) {
                                $specData[$fieldId] = $values;
                            }
                        }
                    }
                }
                // 调试：记录保存的数据
                if (!empty($specData)) {
                    error_log("添加产品技术规格标签 - 产品ID: $productId, 数据: " . json_encode($specData));
                    $result = $productObj->setProductSpecificationTags($productId, $specData);
                    if (!$result) {
                        error_log("添加产品技术规格标签失败 - 产品ID: $productId");
                    }
                } else {
                    error_log("添加产品技术规格标签数据为空 - 产品ID: $productId, POST数据: " . json_encode($_POST));
                }
            }
            
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
                'tags' => isset($_POST['tags']) ? explode(',', $_POST['tags']) : [],
                'specification_mode' => $_POST['specification_mode'] ?? 'markdown'
            ];
            $productObj->updateProduct($productId, $data);
            
            // 如果是手柄分类且使用标签化模式，保存技术规格标签
            $categoryId = (int)$_POST['category_id'];
            $specMode = $_POST['specification_mode'] ?? 'markdown';
            $categoryName = '';
            foreach ($categories as $cat) {
                if ($cat['id'] == $categoryId) {
                    $categoryName = $cat['name'];
                    break;
                }
            }
            
            // 调试：记录关键信息
            error_log("=== 更新产品标签处理开始 ===");
            error_log("产品ID: $productId");
            error_log("分类ID: $categoryId, 分类名称: $categoryName");
            error_log("规格模式: $specMode");
            error_log("是否处理标签: " . ($categoryName === '手柄' && $specMode === 'tagged' ? '是' : '否'));
            error_log("所有POST键: " . implode(', ', array_keys($_POST)));
            
            if ($categoryName === '手柄' && $specMode === 'tagged') {
                $specData = [];
                
                // 收集所有 spec_field_ 开头的字段
                foreach ($_POST as $key => $value) {
                    if (strpos($key, 'spec_field_') === 0) {
                        $fieldId = str_replace('spec_field_', '', $key);
                        error_log("找到字段: $key, fieldId: $fieldId, value: " . var_export($value, true));
                        if (!empty($value) && trim($value) !== '') {
                            // 处理多个值（用逗号分隔）
                            $values = array_filter(array_map('trim', explode(',', $value)));
                            if (!empty($values)) {
                                $specData[$fieldId] = $values;
                                error_log("字段 $fieldId 的值: " . json_encode($values));
                            }
                        }
                    }
                }
                
                error_log("准备保存的技术规格标签数据: " . json_encode($specData));
                error_log("数据是否为空: " . (empty($specData) ? '是' : '否'));
                
                if (!empty($specData)) {
                    $result = $productObj->setProductSpecificationTags($productId, $specData);
                    if (!$result) {
                        error_log("保存技术规格标签失败 - 产品ID: $productId");
                    } else {
                        error_log("保存技术规格标签成功 - 产品ID: $productId");
                        // 标记需要刷新下拉菜单
                        $_SESSION['refresh_spec_tags'] = true;
                    }
                } else {
                    error_log("技术规格标签数据为空 - 产品ID: $productId");
                    // 即使数据为空，也要删除现有标签
                    $productObj->setProductSpecificationTags($productId, []);
                }
            } else {
                error_log("跳过标签处理 - 分类: $categoryName, 模式: $specMode");
            }
            error_log("=== 更新产品标签处理结束 ===");
            
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

// 获取系统设置（每页显示数量）
$adminObj = new Admin();
$systemSettings = $adminObj->getSystemSettings();
$itemsPerPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : (isset($systemSettings['products_per_page']) ? (int)$systemSettings['products_per_page'] : 10);
if ($itemsPerPage < 1) $itemsPerPage = 10;
if ($itemsPerPage > 100) $itemsPerPage = 100;

// 如果URL参数中指定了每页显示数量，且与系统设置不同，则更新系统设置
if (isset($_GET['per_page']) && (int)$_GET['per_page'] != ($systemSettings['products_per_page'] ?? 10)) {
    $adminObj->updateSystemSetting('products_per_page', $itemsPerPage);
}

// 获取当前分类筛选
$selectedCategory = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
// 获取未修改时间筛选（0=全部，15=半个月，30=1个月，60=2个月，90=3个月）
$notModifiedDays = isset($_GET['not_modified']) ? (int)$_GET['not_modified'] : 0;

// 获取产品列表（根据分类筛选和未修改时间筛选）
$pdo = $db->getConnection();

// 检查last_modified字段是否存在
$hasLastModifiedField = false;
try {
    $stmt = $pdo->query("PRAGMA table_info(products)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        if ($column['name'] === 'last_modified') {
            $hasLastModifiedField = true;
            break;
        }
    }
    
    // 如果字段不存在，尝试添加
    if (!$hasLastModifiedField) {
        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN last_modified DATETIME");
            $pdo->exec("UPDATE products SET last_modified = created_at WHERE last_modified IS NULL");
            $hasLastModifiedField = true;
        } catch (PDOException $e) {
            // 添加失败，继续使用created_at作为替代
        }
    }
} catch (PDOException $e) {
    // 忽略错误，使用created_at作为替代
}

$sql = "SELECT p.*, c.name as category_name FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id WHERE 1=1";
$params = [];

if ($selectedCategory > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $selectedCategory;
}

// 添加未修改时间筛选（只有在字段存在时才使用）
if ($notModifiedDays > 0 && $hasLastModifiedField) {
    // SQLite使用datetime('now', '-' || days || ' days')语法，需要直接拼接天数
    $sql .= " AND (p.last_modified IS NULL OR p.last_modified < datetime('now', '-' || " . intval($notModifiedDays) . " || ' days'))";
} elseif ($notModifiedDays > 0 && !$hasLastModifiedField) {
    // 如果字段不存在，使用created_at作为替代
    $sql .= " AND (p.created_at < datetime('now', '-' || " . intval($notModifiedDays) . " || ' days'))";
}

$sql .= " ORDER BY p.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 为每个产品添加标签信息和分类名称
foreach ($products as $key => $product) {
    $products[$key]['tags'] = $productObj->getProductTags($product['id']);
    // 获取分类名称
    foreach ($categories as $cat) {
        if ($cat['id'] == $product['category_id']) {
            $products[$key]['category_name'] = $cat['name'];
            break;
        }
    }
}

// 按分类分组产品
$productsByCategory = [];
foreach ($products as $product) {
    $catId = $product['category_id'];
    if (!isset($productsByCategory[$catId])) {
        $productsByCategory[$catId] = [
            'category' => null,
            'products' => []
        ];
        // 查找分类信息
        foreach ($categories as $cat) {
            if ($cat['id'] == $catId) {
                $productsByCategory[$catId]['category'] = $cat;
                break;
            }
        }
    }
    $productsByCategory[$catId]['products'][] = $product;
}

// 计算分页
$totalProducts = count($products);
$totalPages = ceil($totalProducts / $itemsPerPage);
$offset = ($currentPage - 1) * $itemsPerPage;
$paginatedProducts = array_slice($products, $offset, $itemsPerPage);

// 为分页后的产品添加标签信息（如果还没有）
foreach ($paginatedProducts as $key => $product) {
    if (!isset($product['tags'])) {
        $paginatedProducts[$key]['tags'] = $productObj->getProductTags($product['id']);
    }
    // 确保有分类名称
    if (!isset($product['category_name'])) {
        foreach ($categories as $cat) {
            if ($cat['id'] == $product['category_id']) {
                $paginatedProducts[$key]['category_name'] = $cat['name'];
                break;
            }
        }
    }
}

// 获取所有标签（用于标签输入提示）
$allTags = $productObj->getAllTags();

// 获取技术规格字段（用于手柄分类）
$specFields = $productObj->getSpecificationFields();
$specFieldsMap = [];
$specTagsMap = [];

// 如果字段为空，尝试初始化（仅用于调试）
if (empty($specFields)) {
    // 字段可能未初始化，这会在首次使用时自动创建
    // 但为了确保，我们可以检查数据库
    $db = new Database(); // 这会触发表创建
    $specFields = $productObj->getSpecificationFields();
}

foreach ($specFields as $field) {
    $specFieldsMap[$field['id']] = $field;
    $specTagsMap[$field['id']] = $productObj->getSpecificationTagsByField($field['id']);
}
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
            position: fixed;
            top: 0;
            left: 0;
            width: 16.666667%;
            height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            z-index: 1000;
        }
        .sidebar .nav {
            flex: 1;
            padding-bottom: 20px;
        }
        @media (max-width: 991.98px) {
            .sidebar {
                width: 100%;
                position: relative;
                height: auto;
                min-height: auto;
            }
        }
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.5);
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
            width: 100%;
        }
        .content-wrapper {
            margin-left: 16.666667%;
            width: calc(100% - 16.666667%);
        }
        @media (max-width: 991.98px) {
            .content-wrapper {
                margin-left: 0;
                width: 100%;
            }
        }
        .navbar-admin {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .product-card {
            background: white;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
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
    <!-- 侧边栏 -->
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
            <a class="nav-link" href="comments.php">
                <i class="fas fa-comment-dots"></i> 评论管理
            </a>
            <a class="nav-link" href="product_comments.php">
                <i class="fas fa-star"></i> 站长点评
            </a>
            <a class="nav-link" href="dictionary.php">
                <i class="fas fa-book-open"></i> 字典管理
            </a>
            <a class="nav-link" href="admins.php">
                <i class="fas fa-user-shield"></i> 管理员
            </a>
            <a class="nav-link" href="logout.php">
                <i class="fas fa-sign-out-alt"></i> 退出登录
            </a>
        </nav>
    </div>
    
    <!-- 主内容区 -->
    <div class="content-wrapper">
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
                        <?php 
                        $justSaved = true;
                        $refreshTags = isset($_SESSION['refresh_spec_tags']) && $_SESSION['refresh_spec_tags'];
                        unset($_SESSION['product_success']);
                        unset($_SESSION['refresh_spec_tags']);
                        ?>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['product_error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['product_error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['product_error']); ?>
                        <?php endif; ?>
                        
                        <!-- 分类筛选和分页信息 -->
                        <div class="card mb-3" style="border: none; box-shadow: 0 1px 4px rgba(0,0,0,0.08);">
                            <div class="card-body py-2">
                                <div class="row align-items-center g-2">
                                    <div class="col-md-2">
                                        <label class="form-label mb-0" style="font-size: 0.85rem;">按分类筛选：</label>
                                        <select class="form-select form-select-sm" id="categoryFilter" onchange="filterByCategory()">
                                            <option value="0" <?php echo $selectedCategory == 0 ? 'selected' : ''; ?>>全部分类</option>
                                            <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" <?php echo $selectedCategory == $category['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label mb-0" style="font-size: 0.85rem;">未修改时间：</label>
                                        <select class="form-select form-select-sm" id="notModifiedFilter" onchange="filterByNotModified()">
                                            <option value="0" <?php echo $notModifiedDays == 0 ? 'selected' : ''; ?>>全部产品</option>
                                            <option value="15" <?php echo $notModifiedDays == 15 ? 'selected' : ''; ?>>半个月未修改</option>
                                            <option value="30" <?php echo $notModifiedDays == 30 ? 'selected' : ''; ?>>1个月未修改</option>
                                            <option value="60" <?php echo $notModifiedDays == 60 ? 'selected' : ''; ?>>2个月未修改</option>
                                            <option value="90" <?php echo $notModifiedDays == 90 ? 'selected' : ''; ?>>3个月未修改</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label mb-0" style="font-size: 0.85rem;">每页显示：</label>
                                        <select class="form-select form-select-sm" id="itemsPerPage" onchange="changeItemsPerPage()">
                                            <option value="10" <?php echo $itemsPerPage == 10 ? 'selected' : ''; ?>>10 个</option>
                                            <option value="20" <?php echo $itemsPerPage == 20 ? 'selected' : ''; ?>>20 个</option>
                                            <option value="30" <?php echo $itemsPerPage == 30 ? 'selected' : ''; ?>>30 个</option>
                                            <option value="50" <?php echo $itemsPerPage == 50 ? 'selected' : ''; ?>>50 个</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <small class="text-muted" style="font-size: 0.8rem;">
                                            共 <strong><?php echo $totalProducts; ?></strong> 个产品，第 <strong><?php echo $currentPage; ?></strong>/<strong><?php echo $totalPages; ?></strong> 页
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row g-3">
                            <?php foreach ($paginatedProducts as $product): ?>
                            <div class="col-md-6 col-lg-4 col-xl-3">
                                <div class="product-card">
                                    <div class="d-flex align-items-center mb-2">
                                        <?php if ($product['image_url']): ?>
                                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                             class="me-2" style="width: 50px; height: 50px; object-fit: cover; border-radius: 6px; flex-shrink: 0;" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                                        <?php endif; ?>
                                        <div class="flex-grow-1 min-w-0">
                                            <h6 class="mb-1 text-truncate" style="font-size: 0.9rem; font-weight: 600;"><?php echo htmlspecialchars($product['name']); ?></h6>
                                            <p class="text-muted mb-1" style="font-size: 0.75rem; line-height: 1.2;">
                                                <?php echo htmlspecialchars($product['brand']); ?>
                                                <?php if (isset($product['category_name'])): ?>
                                                    <span class="badge bg-secondary ms-1" style="font-size: 0.7rem; padding: 2px 6px;"><?php echo htmlspecialchars($product['category_name']); ?></span>
                                                <?php endif; ?>
                                            </p>
                                            <span class="text-primary fw-bold" style="font-size: 0.95rem;">¥<?php echo number_format($product['price'], 2); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                        <div>
                                            <small class="text-muted d-block" style="font-size: 0.7rem;">
                                                创建：<?php echo date('Y-m-d', strtotime($product['created_at'])); ?>
                                            </small>
                                            <small class="text-muted d-block" style="font-size: 0.7rem;">
                                                修改：<?php 
                                                $lastModified = (isset($product['last_modified']) && !empty($product['last_modified'])) 
                                                    ? $product['last_modified'] 
                                                    : $product['created_at'];
                                                echo date('Y-m-d', strtotime($lastModified)); 
                                                ?>
                                            </small>
                                        </div>
                                        <div class="btn-group btn-group-sm">
                                            <a href="product_comments.php?action=add&product_id=<?php echo $product['id']; ?>" class="btn btn-outline-info btn-sm" style="padding: 2px 8px; font-size: 0.75rem;" title="添加点评">
                                                <i class="fas fa-comment-dots"></i>
                                            </a>
                                            <button class="btn btn-outline-primary btn-sm" style="padding: 2px 8px; font-size: 0.75rem;" onclick="editProduct(<?php echo $product['id']; ?>)" title="编辑">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-danger btn-sm" style="padding: 2px 8px; font-size: 0.75rem;" onclick="deleteProduct(<?php echo $product['id']; ?>)" title="删除">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (empty($paginatedProducts)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-gamepad fa-3x text-muted mb-3"></i>
                            <h4 class="text-muted"><?php echo $selectedCategory > 0 ? '该分类下暂无产品' : '暂无产品'; ?></h4>
                            <p class="text-muted"><?php echo $selectedCategory > 0 ? '请选择其他分类或点击上方按钮添加产品' : '点击上方按钮添加第一个产品'; ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <!-- 分页导航 -->
                        <?php if ($totalPages > 1): ?>
                        <nav aria-label="产品分页">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $currentPage == 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $currentPage - 1; ?>&category=<?php echo $selectedCategory; ?>&per_page=<?php echo $itemsPerPage; ?>&not_modified=<?php echo $notModifiedDays; ?>">上一页</a>
                                </li>
                                <?php
                                $startPage = max(1, $currentPage - 2);
                                $endPage = min($totalPages, $currentPage + 2);
                                if ($startPage > 1): ?>
                                    <li class="page-item"><a class="page-link" href="?page=1&category=<?php echo $selectedCategory; ?>&per_page=<?php echo $itemsPerPage; ?>&not_modified=<?php echo $notModifiedDays; ?>">1</a></li>
                                    <?php if ($startPage > 2): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <li class="page-item <?php echo $i == $currentPage ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&category=<?php echo $selectedCategory; ?>&per_page=<?php echo $itemsPerPage; ?>&not_modified=<?php echo $notModifiedDays; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                <?php if ($endPage < $totalPages): ?>
                                    <?php if ($endPage < $totalPages - 1): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                    <li class="page-item"><a class="page-link" href="?page=<?php echo $totalPages; ?>&category=<?php echo $selectedCategory; ?>&per_page=<?php echo $itemsPerPage; ?>&not_modified=<?php echo $notModifiedDays; ?>"><?php echo $totalPages; ?></a></li>
                                <?php endif; ?>
                                <li class="page-item <?php echo $currentPage == $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $currentPage + 1; ?>&category=<?php echo $selectedCategory; ?>&per_page=<?php echo $itemsPerPage; ?>&not_modified=<?php echo $notModifiedDays; ?>">下一页</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
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
                <form method="POST" id="addProductForm" onsubmit="return syncAddEditors()" enctype="multipart/form-data">
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
                                    <select class="form-select" name="category_id" id="category_id" required>
                                        <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" <?php echo ($category['name'] === '手柄') ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">产品图片URL</label>
                            <input type="text" class="form-control" name="image_url" placeholder="支持完整网址或/开头的相对路径">
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
                            <div class="mb-2">
                                <label class="form-check-label">
                                    <input type="radio" name="specification_mode" id="add_spec_mode_markdown" value="markdown" class="form-check-input" checked onchange="toggleSpecificationMode('add')">
                                    Markdown模式
                                </label>
                                <label class="form-check-label ms-3">
                                    <input type="radio" name="specification_mode" id="add_spec_mode_tagged" value="tagged" class="form-check-input" onchange="toggleSpecificationMode('add')">
                                    标签化模式（仅手柄分类）
                                </label>
                            </div>
                            <div id="add_spec_markdown_container">
                                <textarea class="form-control markdown-editor" name="specifications" id="add_specifications" rows="5"></textarea>
                            </div>
                            <div id="add_spec_tagged_container" style="display: none;">
                                <div class="specification-tagged-fields" style="display: block;">
                                    <?php if (empty($specFields)): ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle"></i> 技术规格字段未初始化，请检查数据库配置。
                                    </div>
                                    <?php else: ?>
                                    <?php foreach ($specFields as $field): ?>
                                    <div class="mb-3 p-3 border rounded bg-light">
                                        <h6 class="mb-3 fw-bold">
                                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($field['name']); ?>
                                        </h6>
                                        <div class="d-flex flex-wrap gap-2 mb-2" id="add_spec_tags_<?php echo $field['id']; ?>">
                                            <!-- 已有标签会通过JavaScript动态添加 -->
                                        </div>
                                        <div class="input-group">
                                            <input type="text" 
                                                   class="form-control spec-tag-input" 
                                                   id="add_spec_input_<?php echo $field['id']; ?>"
                                                   placeholder="输入标签（多个用逗号分隔）或选择已有标签"
                                                   data-field-id="<?php echo $field['id']; ?>"
                                                   onkeypress="if(event.key==='Enter') { event.preventDefault(); addSpecTag('add', <?php echo $field['id']; ?>); }">
                                            <button type="button" class="btn btn-outline-primary" onclick="addSpecTag('add', <?php echo $field['id']; ?>)">
                                                <i class="fas fa-plus"></i> 添加
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" id="add_spec_dropdown_btn_<?php echo $field['id']; ?>">
                                                <i class="fas fa-list"></i> 选择已有
                                            </button>
                                            <ul class="dropdown-menu" id="add_spec_dropdown_<?php echo $field['id']; ?>" style="max-height: 200px; overflow-y: auto;">
                                                <?php if (!empty($specTagsMap[$field['id']])): ?>
                                                    <?php foreach ($specTagsMap[$field['id']] as $tag): ?>
                                                    <li><a class="dropdown-item" href="#" onclick="selectSpecTag('add', <?php echo $field['id']; ?>, '<?php echo htmlspecialchars($tag['tag_name']); ?>'); return false;">
                                                        <?php echo htmlspecialchars($tag['tag_name']); ?>
                                                    </a></li>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <li><a class="dropdown-item text-muted" href="#">暂无标签</a></li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                        <input type="hidden" name="spec_field_<?php echo $field['id']; ?>" id="add_spec_field_<?php echo $field['id']; ?>" value="">
                                    </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
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
                <form method="POST" id="editProductForm" enctype="multipart/form-data">
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
                            <input type="text" class="form-control" name="image_url" id="edit_image_url" placeholder="支持完整网址或/开头的相对路径">
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
                            <div class="mb-2">
                                <label class="form-check-label">
                                    <input type="radio" name="specification_mode" id="edit_spec_mode_markdown" value="markdown" class="form-check-input" checked onchange="toggleSpecificationMode('edit')">
                                    Markdown模式
                                </label>
                                <label class="form-check-label ms-3">
                                    <input type="radio" name="specification_mode" id="edit_spec_mode_tagged" value="tagged" class="form-check-input" onchange="toggleSpecificationMode('edit')">
                                    标签化模式（仅手柄分类）
                                </label>
                            </div>
                            <div id="edit_spec_markdown_container">
                                <textarea class="form-control markdown-editor" name="specifications" id="edit_specifications" rows="5"></textarea>
                            </div>
                            <div id="edit_spec_tagged_container" style="display: none;">
                                <div class="specification-tagged-fields" style="display: block;">
                                    <?php if (empty($specFields)): ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle"></i> 技术规格字段未初始化，请检查数据库配置。
                                    </div>
                                    <?php else: ?>
                                    <?php foreach ($specFields as $field): ?>
                                    <div class="mb-3 p-3 border rounded bg-light">
                                        <h6 class="mb-3 fw-bold">
                                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($field['name']); ?>
                                        </h6>
                                        <div class="d-flex flex-wrap gap-2 mb-2" id="edit_spec_tags_<?php echo $field['id']; ?>">
                                            <!-- 已有标签会通过JavaScript动态添加 -->
                                        </div>
                                        <div class="input-group">
                                            <input type="text" 
                                                   class="form-control spec-tag-input" 
                                                   id="edit_spec_input_<?php echo $field['id']; ?>"
                                                   placeholder="输入标签（多个用逗号分隔）或选择已有标签"
                                                   data-field-id="<?php echo $field['id']; ?>"
                                                   onkeypress="if(event.key==='Enter') { event.preventDefault(); addSpecTag('edit', <?php echo $field['id']; ?>); }">
                                            <button type="button" class="btn btn-outline-primary" onclick="addSpecTag('edit', <?php echo $field['id']; ?>)">
                                                <i class="fas fa-plus"></i> 添加
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" id="edit_spec_dropdown_btn_<?php echo $field['id']; ?>">
                                                <i class="fas fa-list"></i> 选择已有
                                            </button>
                                            <ul class="dropdown-menu" id="edit_spec_dropdown_<?php echo $field['id']; ?>" style="max-height: 200px; overflow-y: auto;">
                                                <?php if (!empty($specTagsMap[$field['id']])): ?>
                                                    <?php foreach ($specTagsMap[$field['id']] as $tag): ?>
                                                    <li><a class="dropdown-item" href="#" onclick="selectSpecTag('edit', <?php echo $field['id']; ?>, '<?php echo htmlspecialchars($tag['tag_name']); ?>'); return false;">
                                                        <?php echo htmlspecialchars($tag['tag_name']); ?>
                                                    </a></li>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <li><a class="dropdown-item text-muted" href="#">暂无标签</a></li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                        <input type="hidden" name="spec_field_<?php echo $field['id']; ?>" id="edit_spec_field_<?php echo $field['id']; ?>" value="">
                                    </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
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
        const productsData = <?php echo json_encode($paginatedProducts); ?>;
        
        // 分类筛选
        function filterByCategory() {
            const categoryId = document.getElementById('categoryFilter').value;
            const perPage = document.getElementById('itemsPerPage').value;
            const notModified = document.getElementById('notModifiedFilter').value;
            window.location.href = `?category=${categoryId}&per_page=${perPage}&not_modified=${notModified}`;
        }
        
        // 未修改时间筛选
        function filterByNotModified() {
            const categoryId = document.getElementById('categoryFilter').value;
            const perPage = document.getElementById('itemsPerPage').value;
            const notModified = document.getElementById('notModifiedFilter').value;
            window.location.href = `?category=${categoryId}&per_page=${perPage}&not_modified=${notModified}&page=1`;
        }
        
        // 修改每页显示数量
        function changeItemsPerPage() {
            const categoryId = document.getElementById('categoryFilter').value;
            const perPage = document.getElementById('itemsPerPage').value;
            const notModified = document.getElementById('notModifiedFilter').value;
            // 直接跳转，系统会在后台保存设置
            window.location.href = `?category=${categoryId}&per_page=${perPage}&not_modified=${notModified}&page=1`;
        }
        
        // Markdown编辑器实例
        let editors = {
            add: {},
            edit: {}
        };
        
        // EasyMDE配置
        const editorConfig = {
            spellChecker: false,
            placeholder: '输入内容后可以点击预览查看效果...',
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
            
            // 为编辑表单添加提交事件监听器
            const editForm = document.getElementById('editProductForm');
            if (editForm) {
                editForm.addEventListener('submit', function(e) {
                    console.log('=== 表单提交事件触发 ===');
                    // 先同步编辑器内容
                    syncEditEditors();
                    // 再次确保所有字段值都已更新（同步方式，立即执行）
                    const specFields = <?php echo json_encode($specFields); ?>;
                    if (specFields && specFields.length > 0) {
                        console.log('表单提交前最后更新所有字段值');
                        specFields.forEach(field => {
                            updateSpecFieldValue('edit', field.id);
                            const hiddenField = document.getElementById('edit_spec_field_' + field.id);
                            if (hiddenField && hiddenField.value) {
                                console.log('最终字段值 - ' + field.name + ' (ID: ' + field.id + '):', hiddenField.value);
                            }
                        });
                    }
                    console.log('=== 表单提交 ===');
                    return true;
                });
            }
            
            // 为添加表单添加提交事件监听器
            const addForm = document.getElementById('addProductForm');
            if (addForm) {
                addForm.addEventListener('submit', function(e) {
                    console.log('=== 添加表单提交事件触发 ===');
                    // 先同步编辑器内容
                    syncAddEditors();
                    // 再次确保所有字段值都已更新（同步方式，立即执行）
                    const specFields = <?php echo json_encode($specFields); ?>;
                    if (specFields && specFields.length > 0) {
                        console.log('表单提交前最后更新所有字段值');
                        specFields.forEach(field => {
                            updateSpecFieldValue('add', field.id);
                            const hiddenField = document.getElementById('add_spec_field_' + field.id);
                            if (hiddenField && hiddenField.value) {
                                console.log('最终字段值 - ' + field.name + ' (ID: ' + field.id + '):', hiddenField.value);
                            }
                        });
                    }
                    console.log('=== 表单提交 ===');
                    return true;
                });
            }
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
        
        // 同步编辑器内容到表单（提交前）
        function syncAddEditors() {
            // 同步Markdown编辑器内容
            if (editors.add.description) {
                document.getElementById('add_description').value = editors.add.description.value();
            }
            if (editors.add.features) {
                document.getElementById('add_features').value = editors.add.features.value();
            }
            if (editors.add.specifications) {
                document.getElementById('add_specifications').value = editors.add.specifications.value();
            }
            if (editors.add.faq) {
                document.getElementById('add_faq').value = editors.add.faq.value();
            }
            
            // 确保所有技术规格标签字段的值都已更新
            const specFields = <?php echo json_encode($specFields); ?>;
            if (specFields && specFields.length > 0) {
                console.log('开始同步技术规格标签字段（添加模式），字段数量:', specFields.length);
                specFields.forEach(field => {
                    updateSpecFieldValue('add', field.id);
                    const hiddenField = document.getElementById('add_spec_field_' + field.id);
                    const fieldValue = hiddenField ? hiddenField.value : '';
                    console.log('字段 ' + field.name + ' (ID: ' + field.id + ') 的值:', fieldValue);
                });
            }
            
            return true;
        }
        
        function syncEditEditors() {
            // 同步Markdown编辑器内容
            if (editors.edit.description) {
                document.getElementById('edit_description').value = editors.edit.description.value();
            }
            if (editors.edit.features) {
                document.getElementById('edit_features').value = editors.edit.features.value();
            }
            if (editors.edit.specifications) {
                document.getElementById('edit_specifications').value = editors.edit.specifications.value();
            }
            if (editors.edit.faq) {
                document.getElementById('edit_faq').value = editors.edit.faq.value();
            }
            
            // 确保所有技术规格标签字段的值都已更新
            const specFields = <?php echo json_encode($specFields); ?>;
            if (specFields && specFields.length > 0) {
                console.log('开始同步技术规格标签字段，字段数量:', specFields.length);
                const allValues = {};
                specFields.forEach(field => {
                    // 先更新字段值
                    updateSpecFieldValue('edit', field.id);
                    // 然后获取更新后的值
                    const hiddenField = document.getElementById('edit_spec_field_' + field.id);
                    const fieldValue = hiddenField ? hiddenField.value : '';
                    allValues['spec_field_' + field.id] = fieldValue;
                    console.log('字段 ' + field.name + ' (ID: ' + field.id + ') 的值:', fieldValue);
                });
                console.log('所有技术规格标签值（提交前）:', allValues);
                
                // 验证表单数据
                const form = document.getElementById('editProductForm');
                if (form) {
                    const formData = new FormData(form);
                    const specFieldData = {};
                    for (let [key, value] of formData.entries()) {
                        if (key.startsWith('spec_field_')) {
                            specFieldData[key] = value;
                        }
                    }
                    console.log('表单中的技术规格标签数据:', specFieldData);
                    
                    // 检查是否有标签数据
                    const hasTagData = Object.keys(specFieldData).some(key => {
                        const val = specFieldData[key];
                        return val && val.trim().length > 0;
                    });
                    if (!hasTagData) {
                        console.warn('警告：没有找到任何技术规格标签数据！');
                        console.warn('这可能是因为：');
                        console.warn('1. 标签没有添加到界面上');
                        console.warn('2. 隐藏字段的值没有正确更新');
                        console.warn('3. 表单提交时数据丢失');
                    } else {
                        console.log('✓ 找到技术规格标签数据，准备提交');
                    }
                }
            } else {
                console.warn('没有找到技术规格字段定义');
            }
            
            // 最后再次确保所有字段值都已更新（延迟一点确保DOM更新完成）
            if (specFields && specFields.length > 0) {
                console.log('最后检查：再次更新所有字段值');
                setTimeout(() => {
                    specFields.forEach(field => {
                        updateSpecFieldValue('edit', field.id);
                        const hiddenField = document.getElementById('edit_spec_field_' + field.id);
                        if (hiddenField) {
                            console.log('最终字段值 - ' + field.name + ' (ID: ' + field.id + '):', hiddenField.value);
                        }
                    });
                }, 50);
            }
            
            return true;
        }
        
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
            
            // 设置技术规格模式
            const specMode = product.specification_mode || 'markdown';
            if (specMode === 'tagged') {
                document.getElementById('edit_spec_mode_tagged').checked = true;
                toggleSpecificationMode('edit');
                
                // 通过AJAX获取产品的技术规格标签
                console.log('开始加载产品的技术规格标签，产品ID:', productId);
                fetch('../api/get_product_spec_tags.php?product_id=' + productId)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok: ' + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('获取到的标签数据:', data);
                        if (data.success && data.tags && data.tags.length > 0) {
                            console.log('找到', data.tags.length, '个标签');
                            // 按字段分组
                            const tagsByField = {};
                            data.tags.forEach(tag => {
                                const fieldId = tag.field_id;
                                if (!tagsByField[fieldId]) {
                                    tagsByField[fieldId] = [];
                                }
                                const tagValue = tag.tag_name || tag.custom_value;
                                if (tagValue) {
                                    tagsByField[fieldId].push(tagValue);
                                }
                            });
                            
                            console.log('按字段分组的标签:', tagsByField);
                            
                            // 为每个字段添加标签
                            Object.keys(tagsByField).forEach(fieldId => {
                                console.log('为字段', fieldId, '添加标签:', tagsByField[fieldId]);
                                tagsByField[fieldId].forEach(tagValue => {
                                    addTagBadge('edit', fieldId, tagValue);
                                });
                                updateSpecFieldValue('edit', fieldId);
                            });
                        } else {
                            console.log('没有找到标签数据');
                        }
                    })
                    .catch(error => {
                        console.error('加载技术规格标签时出错:', error);
                    });
            } else {
                document.getElementById('edit_spec_mode_markdown').checked = true;
                toggleSpecificationMode('edit');
            }
            
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
        
        // 技术规格标签化相关函数
        function toggleSpecificationMode(mode) {
            const isTagged = document.getElementById(mode + '_spec_mode_tagged').checked;
            const categorySelectId = mode === 'add' ? 'category_id' : 'edit_category_id';
            const categorySelect = document.getElementById(categorySelectId);
            
            // 获取容器
            const markdownContainer = document.getElementById(mode + '_spec_markdown_container');
            const taggedContainer = document.getElementById(mode + '_spec_tagged_container');
            
            if (!markdownContainer || !taggedContainer) {
                console.error('容器不存在:', mode, markdownContainer, taggedContainer);
                return;
            }
            
            // 如果切换到标签化模式，检查分类
            if (isTagged) {
                if (!categorySelect) {
                    console.warn('分类选择框不存在');
                    // 仍然允许切换，可能是初始化时
                } else {
                    const categoryId = categorySelect.value;
                    const categoryName = getCategoryName(categoryId);
                    
                    if (!categoryId || categoryName !== '手柄') {
                        alert('标签化模式仅适用于"手柄"分类，请先选择"手柄"分类');
                        document.getElementById(mode + '_spec_mode_markdown').checked = true;
                        markdownContainer.style.display = 'block';
                        taggedContainer.style.display = 'none';
                        return;
                    }
                }
            }
            
            // 切换显示
            if (isTagged) {
                markdownContainer.style.display = 'none';
                taggedContainer.style.display = 'block';
                // 确保内部容器也显示
                const innerContainer = taggedContainer.querySelector('.specification-tagged-fields');
                if (innerContainer) {
                    innerContainer.style.display = 'block';
                }
                // 调试信息
                console.log('切换到标签化模式', {
                    mode: mode,
                    taggedContainer: taggedContainer,
                    innerContainer: innerContainer,
                    fieldsCount: innerContainer ? innerContainer.children.length : 0
                });
            } else {
                markdownContainer.style.display = 'block';
                taggedContainer.style.display = 'none';
            }
        }
        
        function getCategoryName(categoryId) {
            if (!categoryId) return '';
            const categories = <?php echo json_encode($categories); ?>;
            // 确保类型匹配（都转为数字或字符串进行比较）
            const catId = String(categoryId);
            for (let cat of categories) {
                if (String(cat.id) === catId) {
                    return cat.name;
                }
            }
            return '';
        }
        
        function addSpecTag(mode, fieldId) {
            const input = document.getElementById(mode + '_spec_input_' + fieldId);
            if (!input) {
                console.error('输入框不存在:', mode + '_spec_input_' + fieldId);
                return;
            }
            const value = input.value.trim();
            if (!value) {
                console.warn('输入值为空');
                return;
            }
            
            console.log('添加标签 - mode:', mode, 'fieldId:', fieldId, 'value:', value);
            
            // 处理多个值（用逗号分隔）
            const values = value.split(',').map(v => v.trim()).filter(v => v);
            console.log('处理后的值:', values);
            values.forEach(tagValue => {
                if (tagValue) {
                    addTagBadge(mode, fieldId, tagValue);
                }
            });
            
            input.value = '';
            // 立即更新隐藏字段
            updateSpecFieldValue(mode, fieldId);
        }
        
        function addTagBadge(mode, fieldId, tagValue) {
            const container = document.getElementById(mode + '_spec_tags_' + fieldId);
            
            // 检查是否已存在
            const existing = container.querySelector(`[data-tag-value="${tagValue.replace(/"/g, '&quot;')}"]`);
            if (existing) return;
            
            const badge = document.createElement('span');
            badge.className = 'badge bg-primary me-1 mb-1';
            badge.style.cursor = 'pointer';
            badge.setAttribute('data-tag-value', tagValue);
            badge.innerHTML = tagValue + ' <i class="fas fa-times"></i>';
            badge.onclick = function() {
                badge.remove();
                updateSpecFieldValue(mode, fieldId);
            };
            container.appendChild(badge);
        }
        
        function selectSpecTag(mode, fieldId, tagValue) {
            console.log('选择标签 - mode:', mode, 'fieldId:', fieldId, 'tagValue:', tagValue);
            addTagBadge(mode, fieldId, tagValue);
            updateSpecFieldValue(mode, fieldId);
            // Bootstrap dropdown会自动处理，不需要手动移除show类
        }
        
        // 刷新字段的下拉菜单标签列表
        function refreshSpecTagDropdown(mode, fieldId) {
            console.log('刷新下拉菜单 - mode:', mode, 'fieldId:', fieldId);
            const url = '../api/get_spec_tags_by_field.php?field_id=' + fieldId;
            console.log('请求URL:', url);
            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('获取到的标签数据 (fieldId: ' + fieldId + '):', data);
                    const dropdownId = mode + '_spec_dropdown_' + fieldId;
                    const dropdown = document.getElementById(dropdownId);
                    if (!dropdown) {
                        console.error('下拉菜单不存在:', dropdownId);
                        return;
                    }
                    dropdown.innerHTML = '';
                    if (data.success && data.tags && data.tags.length > 0) {
                        console.log('找到', data.tags.length, '个标签');
                        data.tags.forEach(tag => {
                            const li = document.createElement('li');
                            const a = document.createElement('a');
                            a.className = 'dropdown-item';
                            a.href = '#';
                            a.textContent = tag.tag_name;
                            a.onclick = function(e) {
                                e.preventDefault();
                                selectSpecTag(mode, fieldId, tag.tag_name);
                            };
                            li.appendChild(a);
                            dropdown.appendChild(li);
                        });
                    } else {
                        console.log('没有标签，显示"暂无标签"');
                        const li = document.createElement('li');
                        const a = document.createElement('a');
                        a.className = 'dropdown-item text-muted';
                        a.href = '#';
                        a.textContent = '暂无标签';
                        li.appendChild(a);
                        dropdown.appendChild(li);
                    }
                })
                .catch(error => {
                    console.error('刷新标签时出错 (fieldId: ' + fieldId + '):', error);
                    const dropdown = document.getElementById(mode + '_spec_dropdown_' + fieldId);
                    if (dropdown) {
                        dropdown.innerHTML = '<li><a class="dropdown-item text-danger" href="#">刷新失败: ' + error.message + '</a></li>';
                    }
                });
        }
        
        function updateSpecFieldValue(mode, fieldId) {
            const container = document.getElementById(mode + '_spec_tags_' + fieldId);
            if (!container) {
                console.error('容器不存在:', mode + '_spec_tags_' + fieldId);
                return;
            }
            const badges = container.querySelectorAll('.badge[data-tag-value]');
            const values = Array.from(badges).map(badge => badge.getAttribute('data-tag-value'));
            const hiddenField = document.getElementById(mode + '_spec_field_' + fieldId);
            if (!hiddenField) {
                console.error('隐藏字段不存在:', mode + '_spec_field_' + fieldId);
                return;
            }
            const valueStr = values.join(',');
            hiddenField.value = valueStr;
            console.log('更新字段值 - mode:', mode, 'fieldId:', fieldId, 'values:', values, 'valueStr:', valueStr);
        }
        
        // 监听分类变化，自动切换技术规格模式
        document.addEventListener('DOMContentLoaded', function() {
            const addCategorySelect = document.getElementById('category_id');
            const editCategorySelect = document.getElementById('edit_category_id');
            
            if (addCategorySelect) {
                addCategorySelect.addEventListener('change', function() {
                    const categoryName = getCategoryName(this.value);
                    if (categoryName !== '手柄') {
                        document.getElementById('add_spec_mode_markdown').checked = true;
                        toggleSpecificationMode('add');
                    }
                });
            }
            
            if (editCategorySelect) {
                editCategorySelect.addEventListener('change', function() {
                    const categoryName = getCategoryName(this.value);
                    if (categoryName !== '手柄') {
                        document.getElementById('edit_spec_mode_markdown').checked = true;
                        toggleSpecificationMode('edit');
                    }
                });
            }
            
            // 如果刚刚保存成功且保存了标签，刷新所有下拉菜单
            <?php if (isset($justSaved) && $justSaved && isset($refreshTags) && $refreshTags): ?>
            console.log('检测到保存成功且保存了标签，准备刷新下拉菜单');
            setTimeout(() => {
                const specFields = <?php echo json_encode($specFields); ?>;
                console.log('字段列表:', specFields);
                if (specFields && specFields.length > 0) {
                    console.log('开始刷新', specFields.length, '个字段的下拉菜单');
                    specFields.forEach(field => {
                        console.log('刷新字段:', field.name, 'ID:', field.id);
                        refreshSpecTagDropdown('add', field.id);
                        refreshSpecTagDropdown('edit', field.id);
                    });
                } else {
                    console.warn('字段列表为空，无法刷新下拉菜单');
                }
            }, 1000);
            <?php endif; ?>
        });
        
        // 修改editProduct函数，加载已有的技术规格标签
        const originalEditProduct = editProduct;
        window.editProduct = function(productId) {
            originalEditProduct(productId);
            
            // 加载技术规格标签
            const product = productsData.find(p => p.id == productId);
            if (product) {
                // 设置技术规格模式
                const specMode = product.specification_mode || 'markdown';
                if (specMode === 'tagged') {
                    document.getElementById('edit_spec_mode_tagged').checked = true;
                    toggleSpecificationMode('edit');
                    
                    // 通过AJAX获取产品的技术规格标签
                    fetch('../api/get_product_spec_tags.php?product_id=' + productId)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                data.tags.forEach(tag => {
                                    addTagBadge('edit', tag.field_id, tag.tag_name || tag.custom_value);
                                });
                            }
                        })
                        .catch(error => console.error('Error loading spec tags:', error));
                } else {
                    document.getElementById('edit_spec_mode_markdown').checked = true;
                    toggleSpecificationMode('edit');
                }
            }
        };
    </script>
</body>
</html>
