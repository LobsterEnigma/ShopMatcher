<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Admin.php';
require_once '../classes/Markdown.php';

// 检查管理员是否登录
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$admin = new Admin();
$db = new Database();
$pdo = $db->getConnection();

$message = '';
$messageType = '';
$action = $_GET['action'] ?? 'list';
$editId = $_GET['id'] ?? null;

// 生成slug的函数
function generateSlug($title) {
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    
    if ($postAction === 'save') {
        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $content = $_POST['content'] ?? '';
        $excerpt = trim($_POST['excerpt'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $guideId = $_POST['guide_id'] ?? null;
        
        if (empty($title) || empty($content)) {
            $message = '标题和内容不能为空';
            $messageType = 'danger';
            $action = $guideId ? 'edit' : 'add';
        } else {
            // 如果没有提供slug，从标题生成
            if (empty($slug)) {
                $slug = generateSlug($title);
            }
            
            // 确保slug唯一
            $baseSlug = $slug;
            $counter = 1;
            while (true) {
                $stmt = $pdo->prepare("SELECT id FROM guides WHERE slug = ? AND id != ?");
                $stmt->execute([$slug, $guideId ?? 0]);
                if (!$stmt->fetch()) {
                    break;
                }
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }
            
            // 如果没有提供摘要，从内容生成
            if (empty($excerpt)) {
                $excerpt = Markdown::toPlainText($content, 150);
            }
            
            if ($guideId) {
                // 更新现有指南
                $stmt = $pdo->prepare("UPDATE guides SET title = ?, slug = ?, content = ?, excerpt = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                if ($stmt->execute([$title, $slug, $content, $excerpt, $isActive, $guideId])) {
                    $message = '指南更新成功';
                    $messageType = 'success';
                    $action = 'list';
                } else {
                    $message = '指南更新失败';
                    $messageType = 'danger';
                    $action = 'edit';
                }
            } else {
                // 创建新指南
                $stmt = $pdo->prepare("INSERT INTO guides (title, slug, content, excerpt, is_active) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$title, $slug, $content, $excerpt, $isActive])) {
                    $message = '指南创建成功';
                    $messageType = 'success';
                    $action = 'list';
                } else {
                    $message = '指南创建失败';
                    $messageType = 'danger';
                    $action = 'add';
                }
            }
        }
    } elseif ($postAction === 'delete') {
        $guideId = $_POST['guide_id'] ?? null;
        if ($guideId) {
            $stmt = $pdo->prepare("DELETE FROM guides WHERE id = ?");
            if ($stmt->execute([$guideId])) {
                $message = '指南已删除';
                $messageType = 'success';
            } else {
                $message = '删除失败';
                $messageType = 'danger';
            }
        }
        $action = 'list';
    }
}

// 获取指南列表
if ($action === 'list') {
    $stmt = $pdo->prepare("SELECT id, title, slug, excerpt, is_active, view_count, created_at, updated_at FROM guides ORDER BY updated_at DESC, created_at DESC");
    $stmt->execute();
    $guides = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 获取要编辑的指南
$guide = null;
if ($action === 'edit' && $editId) {
    $stmt = $pdo->prepare("SELECT * FROM guides WHERE id = ?");
    $stmt->execute([$editId]);
    $guide = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$guide) {
        $message = '指南不存在';
        $messageType = 'danger';
        $action = 'list';
    }
}

if ($action === 'add') {
    $guide = [
        'title' => '',
        'slug' => '',
        'content' => '',
        'excerpt' => '',
        'is_active' => 1
    ];
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>指南管理 - <?php echo getSiteName(); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f5f7fa;
        }
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
        #content {
            min-height: 400px;
            font-family: 'Courier New', monospace;
        }
        .preview-area {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            min-height: 200px;
            max-height: 500px;
            overflow-y: auto;
        }
        /* EasyMDE 编辑器容器样式 */
        .EasyMDEContainer {
            margin-bottom: 15px;
        }
        .EasyMDEContainer .CodeMirror {
            border: 1px solid #ced4da;
            border-radius: 4px;
            min-height: 400px;
        }
        .EasyMDEContainer .CodeMirror-focused {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
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
        /* 确保表格在预览中正常显示 */
        .EasyMDEContainer .editor-preview table,
        .EasyMDEContainer .editor-preview-side table {
            display: table !important;
            width: 100% !important;
            border-collapse: collapse !important;
        }
        .EasyMDEContainer .editor-preview table tr,
        .EasyMDEContainer .editor-preview-side table tr {
            display: table-row !important;
        }
        .EasyMDEContainer .editor-preview table th,
        .EasyMDEContainer .editor-preview table td,
        .EasyMDEContainer .editor-preview-side table th,
        .EasyMDEContainer .editor-preview-side table td {
            display: table-cell !important;
            border: 1px solid #dee2e6 !important;
            padding: 8px 12px !important;
        }
        .guide-item {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .guide-item:hover {
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
                        <a class="nav-link active" href="guide.php">
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
            </div>
            
            <!-- 主内容区 -->
            <div class="col-md-10 p-0">
                <div class="main-content">
                    <!-- 顶部导航 -->
                    <nav class="navbar navbar-admin">
                        <div class="container-fluid">
                            <div class="navbar-brand">
                                <h5 class="mb-0"><i class="fas fa-book"></i> 指南管理</h5>
                            </div>
                            <div class="navbar-nav">
                                <span class="text-muted">
                                    <i class="fas fa-calendar"></i> <?php echo date('Y年m月d日 H:i'); ?>
                                </span>
                            </div>
                        </div>
                    </nav>
                    
                    <!-- 内容区 -->
                    <div style="padding: 30px;">
                        <?php if ($message): ?>
                        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i> 
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($action === 'list'): ?>
                        <!-- 指南列表 -->
                        <div class="list-card">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4><i class="fas fa-list"></i> 指南列表</h4>
                                <a href="?action=add" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> 新建指南
                                </a>
                            </div>
                            
                            <?php if (empty($guides)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                                <p class="text-muted">暂无指南文章</p>
                                <a href="?action=add" class="btn btn-primary">创建第一篇指南</a>
                            </div>
                            <?php else: ?>
                            <?php foreach ($guides as $g): ?>
                            <div class="guide-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h5>
                                            <a href="../guide_detail.php?<?php echo $g['slug'] ? 'slug=' . urlencode($g['slug']) : 'id=' . $g['id']; ?>" target="_blank">
                                                <?php echo htmlspecialchars($g['title']); ?>
                                            </a>
                                        </h5>
                                        <?php if ($g['excerpt']): ?>
                                        <p class="text-muted mb-2"><?php echo htmlspecialchars(mb_substr($g['excerpt'], 0, 100)); ?>...</p>
                                        <?php endif; ?>
                                        <div class="d-flex gap-3 align-items-center">
                                            <span class="status-badge <?php echo $g['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo $g['is_active'] ? '已发布' : '未发布'; ?>
                                            </span>
                                            <small class="text-muted">
                                                <i class="fas fa-eye"></i> <?php echo number_format($g['view_count']); ?> 次阅读
                                            </small>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar"></i> <?php echo date('Y-m-d H:i', strtotime($g['updated_at'] ?? $g['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="ms-3">
                                        <a href="?action=edit&id=<?php echo $g['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i> 编辑
                                        </a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('确定要删除这篇指南吗？');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="guide_id" value="<?php echo $g['id']; ?>">
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
                                <h4><i class="fas fa-edit"></i> <?php echo $action === 'add' ? '新建指南' : '编辑指南'; ?></h4>
                                <a href="?action=list" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> 返回列表
                                </a>
                            </div>
                            
                            <form method="POST">
                                <input type="hidden" name="action" value="save">
                                <input type="hidden" name="guide_id" value="<?php echo $guide['id'] ?? ''; ?>">
                                
                                <div class="mb-3">
                                    <label for="title" class="form-label">指南标题 <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($guide['title']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="slug" class="form-label">URL别名（留空自动生成）</label>
                                    <input type="text" class="form-control" id="slug" name="slug" 
                                           value="<?php echo htmlspecialchars($guide['slug']); ?>" 
                                           pattern="[a-z0-9\-]+" title="只能包含小写字母、数字和连字符">
                                    <div class="form-text">用于生成友好的URL，如：product-comparison-guide</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="excerpt" class="form-label">摘要（留空自动生成）</label>
                                    <textarea class="form-control" id="excerpt" name="excerpt" rows="3"><?php echo htmlspecialchars($guide['excerpt']); ?></textarea>
                                    <div class="form-text">显示在列表页面的简短描述，建议150字以内</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="content" class="form-label">指南内容 <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="content" name="content" rows="15"><?php echo htmlspecialchars($guide['content']); ?></textarea>
                                    <div class="invalid-feedback" id="content-error"></div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                               <?php echo ($guide['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">
                                            发布指南（取消勾选后用户将无法查看）
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> 保存指南
                                    </button>
                                    <a href="../guide.php" target="_blank" class="btn btn-outline-info">
                                        <i class="fas fa-eye"></i> 查看用户页面
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

    <!-- EasyMDE Markdown Editor -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">
    <script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if ($action === 'add' || $action === 'edit'): ?>
        // 初始化 EasyMDE Markdown 编辑器 - 参考产品管理的配置
        const easyMDE = new EasyMDE({
            element: document.getElementById('content'),
            spellChecker: false,
            placeholder: '在此输入指南内容，支持 Markdown 语法...',
            status: ['lines', 'words', 'cursor'],
            toolbar: [
                'bold', 'italic', 'heading', '|',
                'quote', 'unordered-list', 'ordered-list', '|',
                'link', 'image', 'table', '|',
                'preview', 'side-by-side', 'fullscreen', '|',
                'guide'
            ],
            minHeight: '400px',
            autofocus: false
        });
        
        const titleInput = document.getElementById('title');
        const slugInput = document.getElementById('slug');
        
        // 从标题自动生成slug
        titleInput.addEventListener('blur', function() {
            if (!slugInput.value) {
                const title = this.value.trim().toLowerCase();
                const slug = title.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
                slugInput.value = slug;
            }
        });
        
        // 表单提交前确保内容已同步并验证
        document.querySelector('form').addEventListener('submit', function(e) {
            // 同步 EasyMDE 内容到 textarea
            const contentValue = easyMDE.value();
            document.getElementById('content').value = contentValue;
            
            // 验证内容是否为空
            if (!contentValue || contentValue.trim() === '') {
                e.preventDefault();
                const contentError = document.getElementById('content-error');
                contentError.textContent = '指南内容不能为空';
                document.getElementById('content').classList.add('is-invalid');
                
                // 滚动到错误位置
                document.getElementById('content').scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }
            
            // 清除错误状态
            document.getElementById('content').classList.remove('is-invalid');
            return true;
        });
        <?php endif; ?>
    </script>
</body>
</html>
