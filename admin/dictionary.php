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
$db = new Database();
$pdo = $db->getConnection();

$message = '';
$messageType = '';
$action = $_GET['action'] ?? 'list';
$fieldId = $_GET['field_id'] ?? null;
$tagId = $_GET['tag_id'] ?? null;

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    
    if ($postAction === 'update_field_order') {
        // 更新字段顺序
        $orders = $_POST['field_order'] ?? [];
        foreach ($orders as $fieldId => $order) {
            $stmt = $pdo->prepare("UPDATE specification_fields SET display_order = ? WHERE id = ?");
            $stmt->execute([(int)$order, (int)$fieldId]);
        }
        $message = '字段顺序已更新';
        $messageType = 'success';
    } elseif ($postAction === 'update_field_name') {
        // 更新字段名称
        $fieldId = $_POST['field_id'] ?? null;
        $fieldName = trim($_POST['field_name'] ?? '');
        if ($fieldId && $fieldName) {
            $stmt = $pdo->prepare("UPDATE specification_fields SET name = ? WHERE id = ?");
            if ($stmt->execute([$fieldName, $fieldId])) {
                $message = '字段名称已更新';
                $messageType = 'success';
            } else {
                $message = '更新失败';
                $messageType = 'danger';
            }
        }
    } elseif ($postAction === 'add_tag') {
        // 添加标签
        $fieldId = $_POST['field_id'] ?? null;
        $tagName = trim($_POST['tag_name'] ?? '');
        if ($fieldId && $tagName) {
            // 检查是否已存在
            $stmt = $pdo->prepare("SELECT id FROM specification_tags WHERE field_id = ? AND tag_name = ?");
            $stmt->execute([$fieldId, $tagName]);
            if ($stmt->fetch()) {
                $message = '标签已存在';
                $messageType = 'warning';
            } else {
                // 获取当前字段的最大display_order
                $stmt = $pdo->prepare("SELECT MAX(display_order) as max_order FROM specification_tags WHERE field_id = ?");
                $stmt->execute([$fieldId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $nextOrder = ($result['max_order'] ?? 0) + 1;
                
                $stmt = $pdo->prepare("INSERT INTO specification_tags (field_id, tag_name, display_order) VALUES (?, ?, ?)");
                if ($stmt->execute([$fieldId, $tagName, $nextOrder])) {
                    $message = '标签已添加';
                    $messageType = 'success';
                } else {
                    $message = '添加失败';
                    $messageType = 'danger';
                }
            }
        }
    } elseif ($postAction === 'update_tag') {
        // 更新标签
        $tagId = $_POST['tag_id'] ?? null;
        $tagName = trim($_POST['tag_name'] ?? '');
        $displayOrder = intval($_POST['display_order'] ?? 0);
        if ($tagId && $tagName) {
            // 获取字段ID
            $stmt = $pdo->prepare("SELECT field_id FROM specification_tags WHERE id = ?");
            $stmt->execute([$tagId]);
            $tag = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($tag) {
                // 检查是否与其他标签重复
                $stmt = $pdo->prepare("SELECT id FROM specification_tags WHERE field_id = ? AND tag_name = ? AND id != ?");
                $stmt->execute([$tag['field_id'], $tagName, $tagId]);
                if ($stmt->fetch()) {
                    $message = '标签已存在';
                    $messageType = 'warning';
                } else {
                    $stmt = $pdo->prepare("UPDATE specification_tags SET tag_name = ?, display_order = ? WHERE id = ?");
                    if ($stmt->execute([$tagName, $displayOrder, $tagId])) {
                        $message = '标签已更新';
                        $messageType = 'success';
                    } else {
                        $message = '更新失败';
                        $messageType = 'danger';
                    }
                }
            }
        }
    } elseif ($postAction === 'delete_tag') {
        // 删除标签
        $tagId = $_POST['tag_id'] ?? null;
        if ($tagId) {
            // 检查是否有产品使用此标签
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_specification_tags WHERE tag_id = ?");
            $stmt->execute([$tagId]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                $message = "无法删除：有 {$count} 个产品正在使用此标签";
                $messageType = 'warning';
            } else {
                $stmt = $pdo->prepare("DELETE FROM specification_tags WHERE id = ?");
                if ($stmt->execute([$tagId])) {
                    $message = '标签已删除';
                    $messageType = 'success';
                } else {
                    $message = '删除失败';
                    $messageType = 'danger';
                }
            }
        }
    } elseif ($postAction === 'update_tag_order') {
        // 更新标签顺序
        $fieldId = $_POST['field_id'] ?? null;
        $orders = $_POST['tag_order'] ?? [];
        if ($fieldId) {
            foreach ($orders as $tagId => $order) {
                $stmt = $pdo->prepare("UPDATE specification_tags SET display_order = ? WHERE id = ? AND field_id = ?");
                $stmt->execute([(int)$order, (int)$tagId, (int)$fieldId]);
            }
            $message = '标签顺序已更新';
            $messageType = 'success';
        }
    } elseif ($postAction === 'add_field') {
        // 添加字段（仅用于手柄分类）
        $fieldName = trim($_POST['field_name'] ?? '');
        if ($fieldName) {
            // 获取手柄分类ID
            $stmt = $pdo->query("SELECT id FROM categories WHERE name = '手柄' LIMIT 1");
            $handleCategory = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($handleCategory) {
                // 检查是否已存在相同名称的字段（在同一分类下）
                $stmt = $pdo->prepare("SELECT id FROM specification_fields WHERE name = ? AND category_id = ?");
                $stmt->execute([$fieldName, $handleCategory['id']]);
                if ($stmt->fetch()) {
                    $message = '字段名称已存在';
                    $messageType = 'warning';
                } else {
                    // 获取当前字段的最大display_order
                    $stmt = $pdo->prepare("SELECT MAX(display_order) as max_order FROM specification_fields WHERE category_id = ?");
                    $stmt->execute([$handleCategory['id']]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $nextOrder = ($result['max_order'] ?? 0) + 1;
                    
                    $stmt = $pdo->prepare("INSERT INTO specification_fields (name, display_order, category_id) VALUES (?, ?, ?)");
                    if ($stmt->execute([$fieldName, $nextOrder, $handleCategory['id']])) {
                        $message = '字段已添加';
                        $messageType = 'success';
                    } else {
                        $message = '添加失败';
                        $messageType = 'danger';
                    }
                }
            } else {
                $message = '未找到手柄分类';
                $messageType = 'danger';
            }
        }
    } elseif ($postAction === 'delete_field') {
        // 删除字段
        $fieldId = $_POST['field_id'] ?? null;
        if ($fieldId) {
            // 检查是否有产品使用此字段
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_specification_tags WHERE field_id = ?");
            $stmt->execute([$fieldId]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                $message = "无法删除：有 {$count} 个产品正在使用此字段";
                $messageType = 'warning';
            } else {
                // 删除字段及其关联的标签
                $stmt = $pdo->prepare("DELETE FROM specification_fields WHERE id = ?");
                if ($stmt->execute([$fieldId])) {
                    $message = '字段已删除';
                    $messageType = 'success';
                } else {
                    $message = '删除失败';
                    $messageType = 'danger';
                }
            }
        }
    }
}

// 获取手柄分类ID
$stmt = $pdo->query("SELECT id FROM categories WHERE name = '手柄' LIMIT 1");
$handleCategory = $stmt->fetch(PDO::FETCH_ASSOC);
$handleCategoryId = $handleCategory ? $handleCategory['id'] : null;

// 获取所有字段（仅显示手柄分类的字段）
if ($handleCategoryId) {
    $stmt = $pdo->prepare("SELECT * FROM specification_fields WHERE category_id = ? ORDER BY display_order ASC");
    $stmt->execute([$handleCategoryId]);
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // 如果没有找到手柄分类，显示所有字段（兼容旧数据）
    $stmt = $pdo->query("SELECT * FROM specification_fields ORDER BY display_order ASC");
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 获取所有标签（按字段分组）
$tagsByField = [];
if (!empty($fields)) {
    foreach ($fields as $field) {
        $stmt = $pdo->prepare("SELECT * FROM specification_tags WHERE field_id = ? ORDER BY display_order ASC, id ASC");
        $stmt->execute([$field['id']]);
        $tagsByField[$field['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>字典管理 - 后台管理</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Microsoft YaHei', sans-serif;
            background-color: #f5f5f5;
        }
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 16.666667%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            overflow-y: auto;
            z-index: 1000;
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
            margin-left: 16.666667%;
            width: 83.333333%;
        }
        .navbar-admin {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .form-card, .list-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 30px;
        }
        .field-card {
            border-left: 4px solid #667eea;
        }
        .tag-item {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px 15px;
            margin: 5px 0;
            cursor: move;
            transition: all 0.3s;
        }
        .tag-item:hover {
            background: #e9ecef;
            border-color: #667eea;
        }
        .tag-item.dragging {
            opacity: 0.5;
        }
        .sortable-ghost {
            opacity: 0.4;
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
            <a class="nav-link" href="product_comments.php">
                <i class="fas fa-star"></i> 站长点评
            </a>
            <a class="nav-link active" href="dictionary.php">
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
    <div class="main-content">
        <!-- 顶部导航 -->
        <nav class="navbar navbar-admin">
            <div class="container-fluid">
                <div class="navbar-brand">
                    <h5 class="mb-0"><i class="fas fa-book-open"></i> 字典管理</h5>
                </div>
            </div>
        </nav>
        
        <!-- 主内容 -->
        <div style="padding: 30px;">
                    <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <div class="card">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-list"></i> 技术规格字段管理</h5>
                            <button class="btn btn-light btn-sm" onclick="showAddFieldModal()">
                                <i class="fas fa-plus"></i> 添加字段
                            </button>
                        </div>
                        <div class="card-body">
                            <?php foreach ($fields as $field): ?>
                            <div class="card field-card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-grip-vertical text-muted me-2" style="cursor: move;"></i>
                                        <h6 class="mb-0">
                                            <span class="field-name-display" data-field-id="<?php echo $field['id']; ?>">
                                                <?php echo htmlspecialchars($field['name']); ?>
                                            </span>
                                            <button class="btn btn-sm btn-link text-primary ms-2" onclick="editFieldName(<?php echo $field['id']; ?>, '<?php echo htmlspecialchars($field['name'], ENT_QUOTES); ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </h6>
                                    </div>
                                    <div>
                                        <button class="btn btn-sm btn-primary me-2" onclick="showAddTagModal(<?php echo $field['id']; ?>, '<?php echo htmlspecialchars($field['name'], ENT_QUOTES); ?>')">
                                            <i class="fas fa-plus"></i> 添加标签
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteField(<?php echo $field['id']; ?>, '<?php echo htmlspecialchars($field['name'], ENT_QUOTES); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="tags-container" data-field-id="<?php echo $field['id']; ?>">
                                        <?php if (!empty($tagsByField[$field['id']])): ?>
                                            <?php foreach ($tagsByField[$field['id']] as $tag): ?>
                                            <div class="tag-item" data-tag-id="<?php echo $tag['id']; ?>">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-grip-vertical text-muted me-2" style="cursor: move;"></i>
                                                        <span class="tag-name-display" data-tag-id="<?php echo $tag['id']; ?>">
                                                            <?php echo htmlspecialchars($tag['tag_name']); ?>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <small class="text-muted me-2">顺序: <?php echo $tag['display_order']; ?></small>
                                                        <button class="btn btn-sm btn-link text-primary" onclick="editTag(<?php echo $tag['id']; ?>, '<?php echo htmlspecialchars($tag['tag_name'], ENT_QUOTES); ?>', <?php echo $tag['display_order']; ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-link text-danger" onclick="deleteTag(<?php echo $tag['id']; ?>, '<?php echo htmlspecialchars($tag['tag_name'], ENT_QUOTES); ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p class="text-muted">暂无标签，点击"添加标签"按钮添加</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <div class="mt-3">
                                <button class="btn btn-success" onclick="saveFieldOrder()">
                                    <i class="fas fa-save"></i> 保存字段顺序
                                </button>
                            </div>
                        </div>
                    </div>
        </div>
    </div>

    <!-- 添加字段模态框 -->
    <div class="modal fade" id="addFieldModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">添加字段</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_field">
                        <div class="mb-3">
                            <label class="form-label">字段名称</label>
                            <input type="text" class="form-control" name="field_name" required>
                            <small class="form-text text-muted">此字段将仅用于手柄分类</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary">添加</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 编辑字段名称模态框 -->
    <div class="modal fade" id="editFieldNameModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">编辑字段名称</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_field_name">
                        <input type="hidden" name="field_id" id="edit_field_id">
                        <div class="mb-3">
                            <label class="form-label">字段名称</label>
                            <input type="text" class="form-control" name="field_name" id="edit_field_name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary">保存</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 添加标签模态框 -->
    <div class="modal fade" id="addTagModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">添加标签</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_tag">
                        <input type="hidden" name="field_id" id="add_tag_field_id">
                        <div class="mb-3">
                            <label class="form-label">所属字段</label>
                            <input type="text" class="form-control" id="add_tag_field_name" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">标签名称</label>
                            <input type="text" class="form-control" name="tag_name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary">添加</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 编辑标签模态框 -->
    <div class="modal fade" id="editTagModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">编辑标签</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_tag">
                        <input type="hidden" name="tag_id" id="edit_tag_id">
                        <div class="mb-3">
                            <label class="form-label">标签名称</label>
                            <input type="text" class="form-control" name="tag_name" id="edit_tag_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">显示顺序</label>
                            <input type="number" class="form-control" name="display_order" id="edit_tag_display_order" min="1" value="1" required>
                            <small class="form-text text-muted">数字越小，显示越靠前。相同顺序时按创建时间排序。</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary">保存</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        // 初始化字段排序
        let fieldSortable = null;
        document.addEventListener('DOMContentLoaded', function() {
            // 字段排序 - 选择包含所有字段卡片的容器
            const fieldsContainer = document.querySelector('.card-body');
            if (fieldsContainer) {
                fieldSortable = new Sortable(fieldsContainer, {
                    handle: '.field-card .fa-grip-vertical',
                    animation: 150,
                    filter: '.mt-3', // 排除保存按钮区域
                    draggable: '.field-card', // 只允许拖动字段卡片
                    onEnd: function(evt) {
                        // 字段顺序变化时不需要立即保存，用户点击保存按钮时再保存
                        console.log('字段顺序已更改');
                    }
                });
            }
            
            // 为每个字段的标签容器初始化排序
            document.querySelectorAll('.tags-container').forEach(container => {
                new Sortable(container, {
                    handle: '.fa-grip-vertical',
                    animation: 150,
                    onEnd: function(evt) {
                        const fieldId = container.getAttribute('data-field-id');
                        saveTagOrder(fieldId);
                    }
                });
            });
        });
        
        function showAddFieldModal() {
            const modal = new bootstrap.Modal(document.getElementById('addFieldModal'));
            modal.show();
        }
        
        function editFieldName(fieldId, currentName) {
            document.getElementById('edit_field_id').value = fieldId;
            document.getElementById('edit_field_name').value = currentName;
            const modal = new bootstrap.Modal(document.getElementById('editFieldNameModal'));
            modal.show();
        }
        
        function deleteField(fieldId, fieldName) {
            if (confirm('确定要删除字段"' + fieldName + '"吗？\n\n注意：\n1. 如果此字段正在被产品使用，将无法删除。\n2. 删除字段将同时删除该字段下的所有标签。')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_field">
                    <input type="hidden" name="field_id" value="${fieldId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function showAddTagModal(fieldId, fieldName) {
            document.getElementById('add_tag_field_id').value = fieldId;
            document.getElementById('add_tag_field_name').value = fieldName;
            const modal = new bootstrap.Modal(document.getElementById('addTagModal'));
            modal.show();
        }
        
        function editTag(tagId, currentName, currentOrder) {
            document.getElementById('edit_tag_id').value = tagId;
            document.getElementById('edit_tag_name').value = currentName;
            document.getElementById('edit_tag_display_order').value = currentOrder || 1;
            const modal = new bootstrap.Modal(document.getElementById('editTagModal'));
            modal.show();
        }
        
        function deleteTag(tagId, tagName) {
            if (confirm('确定要删除标签"' + tagName + '"吗？\n\n注意：如果此标签正在被产品使用，将无法删除。')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_tag">
                    <input type="hidden" name="tag_id" value="${tagId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function saveFieldOrder() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="update_field_order">';
            
            const fieldCards = document.querySelectorAll('.field-card');
            fieldCards.forEach((card, index) => {
                const fieldNameDisplay = card.querySelector('.field-name-display');
                if (fieldNameDisplay) {
                    const fieldId = fieldNameDisplay.getAttribute('data-field-id');
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'field_order[' + fieldId + ']';
                    input.value = index + 1;
                    form.appendChild(input);
                }
            });
            
            document.body.appendChild(form);
            form.submit();
        }
        
        function saveTagOrder(fieldId) {
            const container = document.querySelector(`.tags-container[data-field-id="${fieldId}"]`);
            if (!container) return;
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="update_tag_order">
                <input type="hidden" name="field_id" value="${fieldId}">
            `;
            
            const tagItems = container.querySelectorAll('.tag-item');
            tagItems.forEach((item, index) => {
                const tagId = item.getAttribute('data-tag-id');
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'tag_order[' + tagId + ']';
                input.value = index + 1;
                form.appendChild(input);
            });
            
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>

