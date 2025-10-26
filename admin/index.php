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
$dashboardData = $admin->getDashboardData();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台管理 - <?php echo SITE_NAME; ?></title>
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
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: #495057;
        }
        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
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
                        <a class="nav-link active" href="index.php">
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
                                <h5 class="mb-0"><i class="fas fa-tachometer-alt"></i> 仪表板</h5>
                            </div>
                            <div class="navbar-nav">
                                <span class="text-muted">
                                    <i class="fas fa-calendar"></i> <?php echo date('Y年m月d日 H:i'); ?>
                                </span>
                            </div>
                        </div>
                    </nav>
                    
                    <!-- 仪表板内容 -->
                    <div class="p-4">
                        <h2 class="mb-4">欢迎回来，<?php echo htmlspecialchars($_SESSION['admin_username']); ?>！</h2>
                        
                        <!-- 统计卡片 -->
                        <div class="row mb-4">
                            <div class="col-md-3 mb-3">
                                <div class="stats-card">
                                    <div class="d-flex align-items-center">
                                        <div class="stats-icon bg-primary text-white">
                                            <i class="fas fa-user-plus"></i>
                                        </div>
                                        <div class="ms-3">
                                            <div class="stats-number"><?php echo $dashboardData['new_users_today']; ?></div>
                                            <div class="stats-label">今日新增用户</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <div class="stats-card">
                                    <div class="d-flex align-items-center">
                                        <div class="stats-icon bg-success text-white">
                                            <i class="fas fa-robot"></i>
                                        </div>
                                        <div class="ms-3">
                                            <div class="stats-number"><?php echo $dashboardData['ai_chats_today']; ?></div>
                                            <div class="stats-label">今日AI对话</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <div class="stats-card">
                                    <div class="d-flex align-items-center">
                                        <div class="stats-icon bg-warning text-white">
                                            <i class="fas fa-balance-scale"></i>
                                        </div>
                                        <div class="ms-3">
                                            <div class="stats-number"><?php echo $dashboardData['comparisons_today']; ?></div>
                                            <div class="stats-label">今日对比次数</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <div class="stats-card">
                                    <div class="d-flex align-items-center">
                                        <div class="stats-icon bg-info text-white">
                                            <i class="fas fa-comments"></i>
                                        </div>
                                        <div class="ms-3">
                                            <div class="stats-number"><?php echo $dashboardData['chat_messages_today']; ?></div>
                                            <div class="stats-label">今日聊天消息</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 总体统计 -->
                        <div class="row mb-4">
                            <div class="col-md-12 mb-3">
                                <div class="stats-card">
                                    <div class="d-flex align-items-center">
                                        <div class="stats-icon bg-secondary text-white">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <div class="ms-3">
                                            <div class="stats-number"><?php echo $dashboardData['total_users']; ?></div>
                                            <div class="stats-label">总用户数</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 快速操作 -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-bolt"></i> 快速操作</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-3 mb-3">
                                                <a href="users.php" class="btn btn-outline-primary w-100">
                                                    <i class="fas fa-users"></i><br>
                                                    用户管理
                                                </a>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <a href="products.php" class="btn btn-outline-success w-100">
                                                    <i class="fas fa-gamepad"></i><br>
                                                    产品管理
                                                </a>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <a href="chat.php" class="btn btn-outline-warning w-100">
                                                    <i class="fas fa-comments"></i><br>
                                                    聊天管理
                                                </a>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <a href="settings.php" class="btn btn-outline-info w-100">
                                                    <i class="fas fa-cog"></i><br>
                                                    系统设置
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
