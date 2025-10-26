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
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$users = $admin->getAllUsers($limit, $offset);
$totalUsers = $admin->getTotalUsersCount(); // 获取总数
$totalPages = ceil($totalUsers / $limit);

// 用户操作已禁用 - 此页面仅用于查看用户信息
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户管理 - <?php echo SITE_NAME; ?></title>
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
        .user-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .status-active { background: #d4edda; color: #155724; }
        .status-banned { background: #f8d7da; color: #721c24; }
        .status-restricted { background: #fff3cd; color: #856404; }
        .vip-badge {
            background: linear-gradient(45deg, #ff6b6b, #feca57);
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
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
                        <a class="nav-link active" href="users.php">
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
                                <h5 class="mb-0"><i class="fas fa-users"></i> 用户管理</h5>
                            </div>
                            <div class="navbar-nav">
                                <span class="text-muted">
                                    共 <?php echo $totalUsers; ?> 个用户
                                </span>
                            </div>
                        </div>
                    </nav>
                    
                    <!-- 用户管理内容 -->
                    <div class="p-4">
                        <?php if (isset($success)): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- 用户统计 -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <h5 class="text-primary"><?php echo $totalUsers; ?></h5>
                                        <p class="text-muted mb-0">总用户数</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <h5 class="text-success"><?php echo count($users); ?></h5>
                                        <p class="text-muted mb-0">当前页用户</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <h5 class="text-info"><?php echo count(array_filter($users, function($u) { return $u['status'] === 'active'; })); ?></h5>
                                        <p class="text-muted mb-0">正常用户</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 搜索 -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <form method="GET" class="row align-items-center">
                                    <div class="col-md-10">
                                        <input type="text" class="form-control" name="search" placeholder="搜索用户名或邮箱" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-search"></i> 搜索
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- 用户列表 -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6><i class="fas fa-users"></i> 用户列表</h6>
                                <span class="text-muted">共<?php echo $totalUsers; ?>个用户（当前页显示<?php echo count($users); ?>个）</span>
                            </div>
                            <div class="card-body">
                                <?php if (empty($users)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">暂无用户数据</p>
                                </div>
                                <?php else: ?>
                                <div class="row">
                                    <?php foreach ($users as $user): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="user-card">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="user-avatar me-3">
                                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($user['username']); ?></h6>
                                            <p class="text-muted small mb-0"><?php echo htmlspecialchars($user['email']); ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="text-muted small">注册时间</div>
                                        <div class="fw-bold"><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="status-badge status-<?php echo $user['status']; ?>">
                                                <?php echo $user['status'] === 'active' ? '正常' : ucfirst($user['status']); ?>
                                            </span>
                                        </div>
                                        
                                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="showUserModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                            <i class="fas fa-eye"></i> 查看详情
                                        </button>
                                    </div>
                                </div>
                            </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- 分页 -->
                        <?php if ($totalPages > 1): ?>
                        <nav aria-label="用户分页">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="users.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 用户详情模态框 -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">用户详情</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="userDetails">
                    <!-- 用户详情内容 -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showUserModal(userId, username) {
            document.getElementById('userDetails').innerHTML = `
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">加载中...</span>
                    </div>
                    <p class="mt-2">加载用户详情中...</p>
                </div>
            `;
            
            const modal = new bootstrap.Modal(document.getElementById('userModal'));
            modal.show();
            
            // 这里应该通过AJAX获取用户详情
            setTimeout(() => {
                document.getElementById('userDetails').innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6>基本信息</h6>
                            <p><strong>用户名:</strong> ${username}</p>
                            <p><strong>用户ID:</strong> ${userId}</p>
                        </div>
                        <div class="col-md-6">
                            <h6>账户状态</h6>
                            <p><strong>状态:</strong> 正常</p>
                            <p><strong>注册时间:</strong> 2024-01-01</p>
                        </div>
                    </div>
                `;
            }, 1000);
        }
    </script>
</body>
</html>
