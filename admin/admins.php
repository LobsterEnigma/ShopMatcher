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

// 检查并初始化管理员数据
$db = new Database();
$pdo = $db->getConnection();

// 检查是否有管理员数据
$stmt = $pdo->prepare("SELECT COUNT(*) FROM admins");
$stmt->execute();
$adminCount = $stmt->fetchColumn();

if ($adminCount == 0) {
    // 创建默认主管理员
    $username = 'admin';
    $password = 'admin123';
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
        INSERT INTO admins (username, password, is_main, created_at) 
        VALUES (?, ?, 1, datetime('now'))
    ");
    $stmt->execute([$username, $hashedPassword]);
    
    // 更新当前会话
    $_SESSION['admin_id'] = $pdo->lastInsertId();
    $_SESSION['admin_username'] = $username;
    $_SESSION['is_main_admin'] = 1;
}

// 获取当前管理员信息
$currentAdmin = $admin->getAdminInfo($_SESSION['admin_id']);
if (!$currentAdmin) {
    header('Location: index.php');
    exit;
}

// 如果没有主管理员，将当前管理员设为主管理员
$stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE is_main = 1");
$stmt->execute();
$mainAdminCount = $stmt->fetchColumn();

if ($mainAdminCount == 0) {
    // 将当前管理员设为主管理员
    $stmt = $pdo->prepare("UPDATE admins SET is_main = 1 WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $currentAdmin['is_main'] = 1;
}

// 检查权限 - 允许所有管理员访问，但只有主管理员可以管理其他管理员
// 这里不进行权限检查，让所有管理员都能看到管理员列表

// 处理管理员操作
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'change_my_password':
            $oldPassword = $_POST['old_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            if ($newPassword !== $confirmPassword) {
                $error = '两次输入的密码不一致';
            } elseif (strlen($newPassword) < 6) {
                $error = '密码长度至少6位';
            } else {
                // 验证旧密码
                if (password_verify($oldPassword, $currentAdmin['password'])) {
                    $admin->changeAdminPassword($_SESSION['admin_id'], $newPassword);
                    $success = '密码修改成功，请重新登录';
                    // 清除会话，要求重新登录
                    session_destroy();
                    header('Location: login.php');
                    exit;
                } else {
                    $error = '原密码错误';
                }
            }
            break;
            
        case 'add_admin':
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $email = $_POST['email'] ?? '';
            if (!empty($username) && !empty($password)) {
                $admin->addAdmin($username, $password, $email);
                $success = '管理员已添加';
            }
            break;
            
        case 'delete_admin':
            $adminId = $_POST['admin_id'] ?? '';
            if (!empty($adminId) && $adminId != $_SESSION['admin_id']) {
                $admin->deleteAdmin($adminId);
                $success = '管理员已删除';
            }
            break;
            
        case 'change_admin_password':
            $adminId = $_POST['admin_id'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            if (!empty($adminId) && !empty($newPassword)) {
                $admin->changeAdminPassword($adminId, $newPassword);
                $success = '管理员密码已修改';
            }
            break;
    }
    
    header('Location: admins.php');
    exit;
}

// 获取所有管理员
$admins = $admin->getAllAdmins();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员管理 - <?php echo SITE_NAME; ?></title>
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
                    <div class="p-3">
                        <h5 class="mb-0">后台管理</h5>
                        <small>admin</small>
                    </div>
                    <nav class="nav flex-column p-3">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-tachometer-alt"></i> 仪表板
                        </a>
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users"></i> 用户管理
                        </a>
                        <a class="nav-link" href="products.php">
                            <i class="fas fa-box"></i> 产品管理
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
                        <a class="nav-link active" href="admins.php">
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
                                <h5 class="mb-0"><i class="fas fa-user-shield"></i> 管理员管理</h5>
                            </div>
                        </div>
                    </nav>
                    
                    <!-- 管理员管理内容 -->
                    <div class="p-4">
                        <?php if (isset($success)): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- 修改我的密码 -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6><i class="fas fa-key"></i> 修改我的密码</h6>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="change_my_password">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">原密码</label>
                                                <input type="password" class="form-control" name="old_password" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">新密码</label>
                                                <input type="password" class="form-control" name="new_password" required minlength="6">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">确认新密码</label>
                                                <input type="password" class="form-control" name="confirm_password" required minlength="6">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <button type="submit" class="btn btn-warning">
                                            <i class="fas fa-key"></i> 修改密码
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- 添加管理员 -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6><i class="fas fa-plus"></i> 添加管理员</h6>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="add_admin">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">用户名</label>
                                                <input type="text" class="form-control" name="username" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">密码</label>
                                                <input type="password" class="form-control" name="password" required minlength="6">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">邮箱</label>
                                                <input type="email" class="form-control" name="email">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-plus"></i> 添加管理员
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- 管理员列表 -->
                        <div class="card">
                            <div class="card-header">
                                <h6><i class="fas fa-list"></i> 管理员列表</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>用户名</th>
                                                <th>邮箱</th>
                                                <th>类型</th>
                                                <th>创建时间</th>
                                                <th>最后登录</th>
                                                <th>操作</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($admins as $adminItem): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($adminItem['username']); ?>
                                                    <?php if ($adminItem['is_main']): ?>
                                                    <span class="badge bg-warning ms-1">主管理员</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($adminItem['email'] ?? ''); ?></td>
                                                <td>
                                                    <?php if ($adminItem['is_main']): ?>
                                                    <span class="badge bg-warning">主管理员</span>
                                                    <?php else: ?>
                                                    <span class="badge bg-info">普通管理员</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('Y-m-d H:i', strtotime($adminItem['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($adminItem['last_login']): ?>
                                                    <?php echo date('Y-m-d H:i', strtotime($adminItem['last_login'])); ?>
                                                    <?php else: ?>
                                                    从未登录
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!$adminItem['is_main']): ?>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary" onclick="changePassword(<?php echo $adminItem['id']; ?>)">
                                                            <i class="fas fa-key"></i>
                                                        </button>
                                                        <button class="btn btn-outline-danger" onclick="deleteAdmin(<?php echo $adminItem['id']; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                    <?php else: ?>
                                                    <span class="text-muted">主管理员</span>
                                                    <?php endif; ?>
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

    <!-- 修改密码模态框 -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">修改管理员密码</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="change_admin_password">
                        <input type="hidden" name="admin_id" id="changePasswordAdminId">
                        <div class="mb-3">
                            <label class="form-label">新密码</label>
                            <input type="password" class="form-control" name="new_password" required minlength="6">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary">修改密码</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function changePassword(adminId) {
            document.getElementById('changePasswordAdminId').value = adminId;
            const modal = new bootstrap.Modal(document.getElementById('changePasswordModal'));
            modal.show();
        }
        
        function deleteAdmin(adminId) {
            if (confirm('确定要删除这个管理员吗？')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_admin">
                    <input type="hidden" name="admin_id" value="${adminId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
