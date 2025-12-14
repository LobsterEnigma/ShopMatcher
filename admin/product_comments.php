<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Admin.php';
require_once '../classes/Product.php';
require_once '../classes/Markdown.php';

// 检查管理员是否登录
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$admin = new Admin();
$productObj = new Product();
$db = new Database();
$pdo = $db->getConnection();

$message = '';
$messageType = '';
$action = $_GET['action'] ?? 'list';
$productId = $_GET['product_id'] ?? null;
$commentId = $_GET['id'] ?? null;

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    
    if ($postAction === 'save') {
        $productId = $_POST['product_id'] ?? null;
        $adminName = trim($_POST['admin_name'] ?? '');
        $comment = $_POST['comment'] ?? '';
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $displayOrder = intval($_POST['display_order'] ?? 0);
        $commentId = $_POST['comment_id'] ?? null;
        
        if (empty($productId) || empty($adminName) || empty($comment)) {
            $message = '产品、站长名称和点评内容不能为空';
            $messageType = 'danger';
            $action = $commentId ? 'edit' : 'add';
        } else {
            if ($commentId) {
                // 更新现有点评
                $stmt = $pdo->prepare("UPDATE product_admin_comments SET product_id = ?, admin_name = ?, comment = ?, is_active = ?, display_order = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                if ($stmt->execute([$productId, $adminName, $comment, $isActive, $displayOrder, $commentId])) {
                    $message = '站长点评更新成功';
                    $messageType = 'success';
                    $action = 'list';
                } else {
                    $message = '更新失败';
                    $messageType = 'danger';
                    $action = 'edit';
                }
            } else {
                // 创建新点评
                $stmt = $pdo->prepare("INSERT INTO product_admin_comments (product_id, admin_id, admin_name, comment, is_active, display_order) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$productId, $_SESSION['admin_id'], $adminName, $comment, $isActive, $displayOrder])) {
                    $message = '站长点评创建成功';
                    $messageType = 'success';
                    $action = 'list';
                } else {
                    $message = '创建失败';
                    $messageType = 'danger';
                    $action = 'add';
                }
            }
        }
    } elseif ($postAction === 'delete') {
        $commentId = $_POST['comment_id'] ?? null;
        if ($commentId) {
            $stmt = $pdo->prepare("DELETE FROM product_admin_comments WHERE id = ?");
            if ($stmt->execute([$commentId])) {
                $message = '站长点评已删除';
                $messageType = 'success';
            } else {
                $message = '删除失败';
                $messageType = 'danger';
            }
        }
        $action = 'list';
    }
}

// 获取所有产品（用于下拉选择）
$allProducts = $productObj->getAllProducts();

// 获取点评列表
if ($action === 'list') {
    $sql = "SELECT pac.*, p.name as product_name, a.username as admin_username 
            FROM product_admin_comments pac
            LEFT JOIN products p ON pac.product_id = p.id
            LEFT JOIN admins a ON pac.admin_id = a.id";
    
    if ($productId) {
        $sql .= " WHERE pac.product_id = ?";
        $stmt = $pdo->prepare($sql . " ORDER BY pac.display_order ASC, pac.created_at DESC");
        $stmt->execute([$productId]);
    } else {
        $stmt = $pdo->prepare($sql . " ORDER BY pac.created_at DESC");
        $stmt->execute();
    }
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 获取要编辑的点评
$comment = null;
if ($action === 'edit' && $commentId) {
    $stmt = $pdo->prepare("SELECT * FROM product_admin_comments WHERE id = ?");
    $stmt->execute([$commentId]);
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$comment) {
        $message = '点评不存在';
        $messageType = 'danger';
        $action = 'list';
    }
}

if ($action === 'add') {
    $comment = [
        'product_id' => $productId ?? '',
        'admin_name' => $_SESSION['admin_username'] ?? '',
        'comment' => '',
        'is_active' => 1,
        'display_order' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>站长点评管理 - <?php echo getSiteName(); ?></title>
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
        .form-card, .list-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 30px;
        }
        .comment-item {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .comment-item:hover {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .EasyMDEContainer {
            margin-bottom: 15px;
        }
        .EasyMDEContainer .CodeMirror {
            border: 1px solid #ced4da;
            border-radius: 4px;
            min-height: 300px;
        }
        .EasyMDEContainer .editor-toolbar {
            border: 1px solid #ced4da;
            border-bottom: none;
            border-radius: 4px 4px 0 0;
        }
        .EasyMDEContainer .editor-preview {
            border: 1px solid #ced4da;
            border-top: none;
            border-radius: 0 0 4px 4px;
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
                        <a class="nav-link" href="products.php">
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
                        <a class="nav-link active" href="product_comments.php">
                            <i class="fas fa-star"></i> 站长点评
                        </a>
                        <a class="nav-link" href="admins.php">
                            <i class="fas fa-user-shield"></i> 管理员
                        </a>
                        <a class="nav-link" href="logout.php">
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
                                <h5 class="mb-0"><i class="fas fa-comment-dots"></i> 站长点评管理</h5>
                            </div>
                            <div class="navbar-nav">
                                <span class="text-muted">
                                    <i class="fas fa-calendar"></i> <?php echo date('Y年m月d日 H:i'); ?>
                                </span>
                            </div>
                        </div>
                    </nav>
                    
                    <!-- 内容区 -->
                    <div class="p-4">
                        <?php if ($message): ?>
                        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i> 
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($action === 'list'): ?>
                        <!-- 点评列表 -->
                        <div class="list-card">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4><i class="fas fa-list"></i> 站长点评列表</h4>
                                <div>
                                    <?php if ($productId): ?>
                                    <a href="?action=list" class="btn btn-outline-secondary btn-sm me-2">
                                        <i class="fas fa-list"></i> 查看全部
                                    </a>
                                    <?php endif; ?>
                                    <a href="?action=add<?php echo $productId ? '&product_id=' . $productId : ''; ?>" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> 新建点评
                                    </a>
                                </div>
                            </div>
                            
                            <?php if (empty($comments)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-comment-dots fa-3x text-muted mb-3"></i>
                                <p class="text-muted">暂无站长点评</p>
                                <a href="?action=add<?php echo $productId ? '&product_id=' . $productId : ''; ?>" class="btn btn-primary">创建第一条点评</a>
                            </div>
                            <?php else: ?>
                            <?php foreach ($comments as $c): ?>
                            <div class="comment-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6>
                                            <a href="../product.php?id=<?php echo $c['product_id']; ?>" target="_blank">
                                                <?php echo htmlspecialchars($c['product_name']); ?>
                                            </a>
                                        </h6>
                                        <div class="mb-2">
                                            <strong class="text-primary">
                                                <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($c['admin_name']); ?>
                                            </strong>
                                            <small class="text-muted ms-2">
                                                <i class="fas fa-calendar"></i> <?php echo date('Y-m-d H:i', strtotime($c['created_at'])); ?>
                                            </small>
                                        </div>
                                        <div class="text-muted mb-2">
                                            <?php echo htmlspecialchars(mb_substr(Markdown::toPlainText($c['comment']), 0, 150)); ?>...
                                        </div>
                                        <div class="d-flex gap-3 align-items-center">
                                            <span class="status-badge <?php echo $c['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo $c['is_active'] ? '已发布' : '未发布'; ?>
                                            </span>
                                            <small class="text-muted">
                                                排序：<?php echo $c['display_order']; ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="ms-3">
                                        <a href="?action=edit&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i> 编辑
                                        </a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('确定要删除这条点评吗？');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="comment_id" value="<?php echo $c['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash"></i> 删除
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <?php elseif ($action === 'add' || $action === 'edit'): ?>
                        <!-- 编辑表单 -->
                        <div class="form-card">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4><i class="fas fa-edit"></i> <?php echo $action === 'add' ? '新建站长点评' : '编辑站长点评'; ?></h4>
                                <a href="?action=list<?php echo $productId ? '&product_id=' . $productId : ''; ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> 返回列表
                                </a>
                            </div>
                            
                            <form method="POST">
                                <input type="hidden" name="action" value="save">
                                <input type="hidden" name="comment_id" value="<?php echo $comment['id'] ?? ''; ?>">
                                
                                <div class="mb-3">
                                    <label for="product_id" class="form-label">选择产品 <span class="text-danger">*</span></label>
                                    <select class="form-select" id="product_id" name="product_id" required>
                                        <option value="">请选择产品</option>
                                        <?php foreach ($allProducts as $product): ?>
                                        <option value="<?php echo $product['id']; ?>" <?php echo ($comment['product_id'] ?? '') == $product['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($product['name']); ?> (<?php echo htmlspecialchars($product['brand']); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="admin_name" class="form-label">站长名称 <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="admin_name" name="admin_name" 
                                           value="<?php echo htmlspecialchars($comment['admin_name']); ?>" required>
                                    <div class="form-text">显示在产品详情页面的站长名称</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="comment" class="form-label">点评内容 <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="comment" name="comment" rows="10"><?php echo htmlspecialchars($comment['comment']); ?></textarea>
                                    <div class="form-text">支持 Markdown 格式</div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="display_order" class="form-label">显示顺序</label>
                                            <input type="number" class="form-control" id="display_order" name="display_order" 
                                                   value="<?php echo $comment['display_order'] ?? 0; ?>" min="0">
                                            <div class="form-text">数字越小越靠前，默认为0</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <div class="form-check mt-4">
                                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                                       <?php echo ($comment['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="is_active">
                                                    发布点评（取消勾选后用户将无法查看）
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> 保存点评
                                    </button>
                                    <a href="../product.php?id=<?php echo $comment['product_id'] ?? ''; ?>" target="_blank" class="btn btn-outline-info">
                                        <i class="fas fa-eye"></i> 查看产品页面
                                    </a>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if ($action === 'add' || $action === 'edit'): ?>
        // 初始化 EasyMDE Markdown 编辑器
        const easyMDE = new EasyMDE({
            element: document.getElementById('comment'),
            spellChecker: false,
            placeholder: '在此输入站长点评内容，支持 Markdown 语法...',
            status: ['lines', 'words', 'cursor'],
            toolbar: [
                'bold', 'italic', 'heading', '|',
                'quote', 'unordered-list', 'ordered-list', '|',
                'link', 'image', 'table', '|',
                'preview', 'side-by-side', 'fullscreen', '|',
                'guide'
            ],
            minHeight: '300px',
            autofocus: false
        });
        
        // 表单提交前确保内容已同步
        document.querySelector('form').addEventListener('submit', function(e) {
            const commentValue = easyMDE.value();
            document.getElementById('comment').value = commentValue;
            
            if (!commentValue || commentValue.trim() === '') {
                e.preventDefault();
                alert('点评内容不能为空');
                return false;
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>

