<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Admin.php';

// 检查管理员是否登录
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$admin = new Admin();

// 处理分类操作
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add':
            $name = $_POST['name'] ?? '';
            $description = $_POST['description'] ?? '';
            if (!empty($name)) {
                $db = new Database();
                $pdo = $db->getConnection();
                $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
                $stmt->execute([$name, $description]);
            }
            break;
        case 'update':
            $id = $_POST['id'] ?? '';
            $name = $_POST['name'] ?? '';
            $description = $_POST['description'] ?? '';
            if (!empty($id) && !empty($name)) {
                $db = new Database();
                $pdo = $db->getConnection();
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $description, $id]);
            }
            break;
        case 'delete':
            $id = $_POST['id'] ?? '';
            if (!empty($id)) {
                $db = new Database();
                $pdo = $db->getConnection();
                $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$id]);
            }
            break;
    }
    
    header('Location: categories.php');
    exit;
}

// 获取分类列表
$db = new Database();
$pdo = $db->getConnection();
$stmt = $pdo->prepare("SELECT * FROM categories ORDER BY created_at DESC");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>分类管理 - <?php echo SITE_NAME; ?></title>
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
        .category-card {
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
                        <a class="nav-link" href="products.php">
                            <i class="fas fa-gamepad"></i> 产品管理
                        </a>
                        <a class="nav-link active" href="categories.php">
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
                                <h5 class="mb-0"><i class="fas fa-tags"></i> 分类管理</h5>
                            </div>
                            <div class="navbar-nav">
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                    <i class="fas fa-plus"></i> 添加分类
                                </button>
                            </div>
                        </div>
                    </nav>
                    
                    <!-- 分类列表 -->
                    <div class="p-4">
                        <div class="row">
                            <?php foreach ($categories as $category): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="category-card">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($category['name']); ?></h6>
                                            <p class="text-muted small mb-0"><?php echo htmlspecialchars($category['description']); ?></p>
                                        </div>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="editCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>', '<?php echo htmlspecialchars($category['description']); ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="deleteCategory(<?php echo $category['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            创建时间: <?php echo date('Y-m-d', strtotime($category['created_at'])); ?>
                                        </small>
                                        <span class="badge bg-primary">分类ID: <?php echo $category['id']; ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (empty($categories)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                            <h4 class="text-muted">暂无分类</h4>
                            <p class="text-muted">点击上方按钮添加第一个分类</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 添加分类模态框 -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">添加分类</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">分类名称</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">分类描述</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary">添加分类</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 编辑分类模态框 -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">编辑分类</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" id="editCategoryId">
                        <div class="mb-3">
                            <label class="form-label">分类名称</label>
                            <input type="text" class="form-control" name="name" id="editCategoryName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">分类描述</label>
                            <textarea class="form-control" name="description" id="editCategoryDescription" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary">更新分类</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editCategory(id, name, description) {
            document.getElementById('editCategoryId').value = id;
            document.getElementById('editCategoryName').value = name;
            document.getElementById('editCategoryDescription').value = description;
            const modal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
            modal.show();
        }
        
        function deleteCategory(id) {
            if (confirm('确定要删除这个分类吗？')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
