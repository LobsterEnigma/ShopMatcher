<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/User.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user = new User();
$userInfo = $user->getUserInfo($_SESSION['user_id']);

// 处理密码修改
if ($_POST && isset($_POST['change_password'])) {
    $oldPassword = $_POST['old_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if ($newPassword !== $confirmPassword) {
        $error = '两次输入的密码不一致';
    } elseif (strlen($newPassword) < 6) {
        $error = '密码长度至少6位';
    } else {
        if ($user->updatePassword($_SESSION['user_id'], $oldPassword, $newPassword)) {
            $success = '密码修改成功';
        } else {
            $error = '原密码错误';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>个人中心 - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .profile-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            margin: 0 auto 20px;
        }
        .stats-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
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
    </style>
</head>
<body>
    <!-- 导航栏 -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-gamepad"></i> <?php echo SITE_NAME; ?>
            </a>
            
            <div class="navbar-nav me-auto">
                <a class="nav-link" href="index.php">首页</a>
                <a class="nav-link" href="products.php">产品对比</a>
                <a class="nav-link" href="chat.php">讨论区</a>
                <a class="nav-link active" href="profile.php">个人中心</a>
            </div>
            
            <div class="navbar-nav">
                <a href="logout.php" class="btn btn-outline-light">退出</a>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <div class="row">
            <div class="col-md-4">
                <!-- 个人信息卡片 -->
                <div class="profile-card mb-4">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?php echo strtoupper(substr($userInfo['username'], 0, 1)); ?>
                        </div>
                        <h4><?php echo htmlspecialchars($userInfo['username']); ?></h4>
                        <p class="mb-0"><?php echo htmlspecialchars($userInfo['email']); ?></p>
                    </div>
                    
                    <div class="p-4">
                        <h6><i class="fas fa-info-circle"></i> 账户信息</h6>
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-calendar"></i> 
                                注册时间: <?php echo date('Y-m-d', strtotime($userInfo['created_at'])); ?>
                            </small>
                        </div>
                    </div>
                </div>
                
                <!-- 快速操作 -->
                <div class="profile-card">
                    <div class="p-4">
                        <h6><i class="fas fa-bolt"></i> 快速操作</h6>
                        <div class="d-grid gap-2">
                            <a href="products.php" class="btn btn-outline-primary">
                                <i class="fas fa-balance-scale"></i> 产品对比
                            </a>
                            <a href="chat.php" class="btn btn-outline-success">
                                <i class="fas fa-comments"></i> 讨论区
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <!-- 修改密码 -->
                <div class="profile-card mb-4">
                    <div class="p-4">
                        <h5><i class="fas fa-key"></i> 修改密码</h5>
                        
                        <?php if (isset($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($success)): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="old_password" class="form-label">原密码</label>
                                        <input type="password" class="form-control" id="old_password" name="old_password" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">新密码</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">确认新密码</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <button type="submit" name="change_password" class="btn btn-primary">
                                <i class="fas fa-save"></i> 修改密码
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- 账户统计 -->
                <div class="profile-card">
                    <div class="p-4">
                        <h5><i class="fas fa-chart-line"></i> 账户统计</h5>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 您的账户已创建，可以开始使用平台功能了！
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 密码确认验证
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.setCustomValidity('两次输入的密码不一致');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
