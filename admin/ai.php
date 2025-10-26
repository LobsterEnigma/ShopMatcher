<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Admin.php';


if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$admin = new Admin();


define('ENCRYPTED_AI_BASE_URL', base64_encode('https://api.longx.de/v1'));


if ($_POST) {
    $action = $_POST['action'] ?? '';
    switch ($action) {
        case 'update_ai_settings':
            
            $aiApiUrl = base64_decode(ENCRYPTED_AI_BASE_URL);
            $aiApiKey = $_POST['ai_api_key'] ?? '';
            $aiModel = $_POST['ai_model'] ?? '';
            
            $admin->updateSystemSetting('ai_api_url', $aiApiUrl);
            $admin->updateSystemSetting('ai_api_key', $aiApiKey);
            $admin->updateSystemSetting('ai_model', $aiModel);
            $success = 'AI设置已保存';
            break;
            
        case 'add_prompt':
            $name = $_POST['name'] ?? '';
            $prompt = $_POST['prompt'] ?? '';
            $type = $_POST['type'] ?? '';
            if (!empty($name) && !empty($prompt)) {
                $admin->addAIPrompt($name, $prompt, $type);
                $success = '提示词已添加';
            }
            break;
            
        case 'update_prompt':
            $id = $_POST['id'] ?? '';
            $name = $_POST['name'] ?? '';
            $prompt = $_POST['prompt'] ?? '';
            $type = $_POST['type'] ?? '';
            if (!empty($id)) {
                $admin->updateAIPrompt($id, $name, $prompt, $type);
                $success = '提示词已更新';
            }
            break;
            
        case 'delete_prompt':
            $id = $_POST['id'] ?? '';
            if (!empty($id)) {
                $admin->deleteAIPrompt($id);
                $success = '提示词已删除';
            }
            break;
    }
    
    header('Location: ai.php');
    exit;
}


$settings = $admin->getSystemSettings();
$aiPrompts = $admin->getAllAIPrompts();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI设置 - <?php echo getSiteName(); ?></title>
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
                        <a class="nav-link active" href="ai.php">
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
            
            
            <div class="col-md-10 p-0">
                <div class="main-content">
                    <nav class="navbar navbar-admin">
                        <div class="container-fluid">
                            <div class="navbar-brand">
                                <h5 class="mb-0"><i class="fas fa-robot"></i> AI设置</h5>
                            </div>
                        </div>
                    </nav>
                    
 
                    <div class="p-4">
                        <?php if (isset($success)): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        </div>
                        <?php endif; ?>
                        

                        <div class="card mb-4">
                            <div class="card-header">
                                <h6><i class="fas fa-cog"></i> AI API设置</h6>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_ai_settings">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Base URL</label>
                                                <input type="text" class="form-control" 
                                                       value="<?php echo base64_decode(ENCRYPTED_AI_BASE_URL); ?>" 
                                                       readonly disabled
                                                       style="background-color: #e9ecef; cursor: not-allowed;">
                                                <div class="form-text text-muted">
                                                    <i class="fas fa-lock"></i> <a href="https://api.longx.de/">King API XPro</a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">API Key</label>
                                                <input type="password" class="form-control" name="ai_api_key" 
                                                       value="<?php echo htmlspecialchars($settings['ai_api_key'] ?? ''); ?>" 
                                                       placeholder="sk-...">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Model</label>
                                                <input type="text" class="form-control" name="ai_model" 
                                                       value="<?php echo htmlspecialchars($settings['ai_model'] ?? 'gpt-4.1'); ?>" 
                                                       placeholder="gpt-4.1">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> 保存设置
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6><i class="fas fa-list"></i> AI提示词管理</h6>
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addPromptModal">
                                    <i class="fas fa-plus"></i> 添加提示词
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>名称</th>
                                                <th>类型</th>
                                                <th>提示词</th>
                                                <th>创建时间</th>
                                                <th>操作</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($aiPrompts as $prompt): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($prompt['name']); ?></td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo htmlspecialchars($prompt['type']); ?></span>
                                                </td>
                                                <td><?php echo htmlspecialchars(mb_substr($prompt['prompt'], 0, 100)); ?>...</td>
                                                <td><?php echo date('Y-m-d H:i', strtotime($prompt['created_at'])); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary" onclick="editPrompt(<?php echo $prompt['id']; ?>, '<?php echo htmlspecialchars($prompt['name']); ?>', '<?php echo htmlspecialchars($prompt['prompt']); ?>', '<?php echo htmlspecialchars($prompt['type']); ?>')">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-outline-danger" onclick="deletePrompt(<?php echo $prompt['id']; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
    <div class="modal fade" id="addPromptModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">添加AI提示词</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_prompt">
                        <div class="mb-3">
                            <label class="form-label">提示词名称</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">提示词类型</label>
                            <select class="form-select" name="type" required>
                                <option value="product_summary">产品总结</option>
                                <option value="ai_guide">AI导购</option>
                                <option value="comparison">产品对比</option>
                                <option value="recommendation">推荐理由</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">提示词内容</label>
                            <textarea class="form-control" name="prompt" rows="8" required placeholder="请输入AI提示词内容..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary">添加提示词</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    
    <div class="modal fade" id="editPromptModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">编辑AI提示词</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_prompt">
                        <input type="hidden" name="id" id="editPromptId">
                        <div class="mb-3">
                            <label class="form-label">提示词名称</label>
                            <input type="text" class="form-control" name="name" id="editPromptName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">提示词类型</label>
                            <select class="form-select" name="type" id="editPromptType" required>
                                <option value="product_summary">产品总结</option>
                                <option value="ai_guide">AI导购</option>
                                <option value="comparison">产品对比</option>
                                <option value="recommendation">推荐理由</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">提示词内容</label>
                            <textarea class="form-control" name="prompt" id="editPromptContent" rows="8" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary">更新提示词</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editPrompt(id, name, prompt, type) {
            document.getElementById('editPromptId').value = id;
            document.getElementById('editPromptName').value = name;
            document.getElementById('editPromptContent').value = prompt;
            document.getElementById('editPromptType').value = type;
            const modal = new bootstrap.Modal(document.getElementById('editPromptModal'));
            modal.show();
        }
        
        function deletePrompt(id) {
            if (confirm('确定要删除这个提示词吗？')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_prompt">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
