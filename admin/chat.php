<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Admin.php';
require_once '../classes/Chat.php';

// 检查管理员是否登录
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$admin = new Admin();
$chat = new Chat();

// 处理聊天操作
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'delete_message':
            $messageId = $_POST['message_id'] ?? '';
            if (!empty($messageId)) {
                $chat->deleteMessage($messageId);
            }
            break;
    }
    
    header('Location: chat.php');
    exit;
}

// 获取聊天消息
$messages = $chat->getMessages(100);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>聊天管理 - <?php echo SITE_NAME; ?></title>
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
        .message-item {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .message-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 8px;
        }
        .username {
            font-weight: bold;
            color: #495057;
        }
        .message-time {
            color: #6c757d;
            font-size: 0.8rem;
        }
        .message-content {
            color: #495057;
            line-height: 1.5;
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
                        <a class="nav-link active" href="chat.php">
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
                                <h5 class="mb-0"><i class="fas fa-comments"></i> 聊天管理</h5>
                            </div>
                            <div class="navbar-nav">
                                <span class="text-muted">
                                    共 <?php echo count($messages); ?> 条消息
                                </span>
                            </div>
                        </div>
                    </nav>
                    
                    <!-- 聊天消息列表 -->
                    <div class="p-4">
                        <?php if (empty($messages)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                            <h4 class="text-muted">暂无聊天消息</h4>
                            <p class="text-muted">用户还没有开始聊天</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($messages as $message): ?>
                        <div class="message-item">
                            <div class="message-header">
                                <div>
                                    <span class="username">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($message['username']); ?>
                                    </span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <span class="message-time me-3">
                                        <?php echo date('Y-m-d H:i:s', strtotime($message['created_at'])); ?>
                                    </span>
                                    <button class="btn btn-outline-danger btn-sm" onclick="deleteMessage(<?php echo $message['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="message-content">
                                <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteMessage(messageId) {
            if (confirm('确定要删除这条消息吗？')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_message">
                    <input type="hidden" name="message_id" value="${messageId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
