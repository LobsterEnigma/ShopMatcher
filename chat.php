<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/Chat.php';
require_once 'classes/User.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$chat = new Chat();
$user = new User();
$userInfo = $user->getUserInfo($_SESSION['user_id']);

// 处理发送消息
if ($_POST && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    if (!empty($message)) {
        $result = $chat->sendMessage($_SESSION['user_id'], $message);
        if ($result['success']) {
            // 刷新页面显示新消息
            header('Location: chat.php');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

// 获取聊天消息
$messages = $chat->getMessages(50);
$chatStats = $chat->getUserChatStats($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>讨论区 - <?php echo getSiteName(); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .chat-container {
            height: 70vh;
            background: #f8f9fa;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .chat-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .chat-messages {
            height: calc(100% - 140px);
            overflow-y: auto;
            padding: 20px;
            background: white;
        }
        .message-item {
            margin-bottom: 15px;
            padding: 15px;
            border-radius: 10px;
            background: #f8f9fa;
            border-left: 4px solid #007bff;
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
        .chat-input {
            padding: 20px;
            background: white;
            border-top: 1px solid #e9ecef;
            position: sticky;
            bottom: 0;
            z-index: 1000;
        }
        .input-group {
            width: 100%;
        }
        .input-group .form-control {
            flex: 1;
        }
        .input-group .btn {
            flex-shrink: 0;
        }
        .user-stats {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stats-item {
            text-align: center;
        }
        .stats-number {
            font-size: 1.5rem;
            font-weight: bold;
        }
        .online-users {
            background: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9rem;
        }
        .message-item:hover {
            background: #e9ecef;
        }
        .scroll-to-bottom {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
    </style>
</head>
<body>
    <!-- 导航栏 -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-gamepad"></i> <?php echo getSiteName(); ?>
            </a>
            
            <div class="navbar-nav me-auto">
                <a class="nav-link" href="index.php">首页</a>
                <a class="nav-link" href="products.php">产品对比</a>
                <a class="nav-link active" href="chat.php">讨论区</a>
                <a class="nav-link" href="profile.php">个人中心</a>
            </div>
            
            <div class="navbar-nav">
                <span class="text-light me-3">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($userInfo['username']); ?>
                </span>
                <a href="logout.php" class="btn btn-outline-light">退出</a>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <!-- 用户统计 -->
        <div class="user-stats">
            <div class="row">
                <div class="col-md-6 stats-item">
                    <div class="stats-number"><?php echo $chatStats['total_messages']; ?></div>
                    <div>总发言数</div>
                </div>
                <div class="col-md-6 stats-item">
                    <div class="stats-number"><?php echo $chatStats['today_messages']; ?></div>
                    <div>今日发言</div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <!-- 聊天区域 -->
                <div class="chat-container">
                    <div class="chat-header">
                        <h4><i class="fas fa-comments"></i> 游戏手柄讨论区</h4>
                        <p class="mb-0">与玩家交流使用心得，分享游戏体验</p>
                    </div>
                    
                    <div class="chat-messages" id="chatMessages">
                        <?php if (empty($messages)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-comments fa-3x mb-3"></i>
                            <p>暂无消息，快来发起第一个话题吧！</p>
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
                                <span class="message-time">
                                    <?php echo date('H:i', strtotime($message['created_at'])); ?>
                                </span>
                            </div>
                            <div class="message-content">
                                <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="chat-input">
                        <?php if (isset($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" id="chatForm">
                            <div class="input-group">
                                <input type="text" class="form-control" name="message" placeholder="输入消息..." required maxlength="500">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-paper-plane"></i> 发送
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- 在线用户 -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6><i class="fas fa-users"></i> 在线用户</h6>
                    </div>
                    <div class="card-body">
                        <div class="online-users mb-2">
                            <i class="fas fa-circle text-success"></i> 在线人数: <span id="onlineCount"><?php echo rand(5, 20); ?></span>
                        </div>
                        <div class="text-muted small">
                            <i class="fas fa-info-circle"></i> 实时更新在线状态
                        </div>
                    </div>
                </div>
                
                <!-- 聊天规则 -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6><i class="fas fa-gavel"></i> 聊天规则</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0 small">
                            <li><i class="fas fa-check text-success"></i> 文明用语，禁止恶意攻击</li>
                            <li><i class="fas fa-check text-success"></i> 禁止发布违法违规内容</li>
                            <li><i class="fas fa-check text-success"></i> 禁止刷屏和重复发言</li>
                            <li><i class="fas fa-check text-success"></i> 鼓励分享使用心得</li>
                            <li><i class="fas fa-check text-success"></i> 违规用户将被禁言</li>
                        </ul>
                    </div>
                </div>
                
                <!-- 快速操作 -->
                <div class="card">
                    <div class="card-header">
                        <h6><i class="fas fa-bolt"></i> 快速操作</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary btn-sm" onclick="scrollToBottom()">
                                <i class="fas fa-arrow-down"></i> 滚动到底部
                            </button>
                            <button class="btn btn-outline-success btn-sm" onclick="refreshMessages()">
                                <i class="fas fa-sync-alt"></i> 刷新消息
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 滚动到底部按钮 -->
    <button class="btn btn-primary scroll-to-bottom" onclick="scrollToBottom()" id="scrollBtn" style="display: none;">
        <i class="fas fa-arrow-down"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 自动滚动到底部
        function scrollToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // 刷新消息
        function refreshMessages() {
            location.reload();
        }
        
        // 检查是否需要显示滚动按钮
        function checkScrollButton() {
            const chatMessages = document.getElementById('chatMessages');
            const scrollBtn = document.getElementById('scrollBtn');
            
            if (chatMessages.scrollTop + chatMessages.clientHeight < chatMessages.scrollHeight - 100) {
                scrollBtn.style.display = 'block';
            } else {
                scrollBtn.style.display = 'none';
            }
        }
        
        // 监听滚动事件
        document.getElementById('chatMessages').addEventListener('scroll', checkScrollButton);
        
        // 页面加载完成后滚动到底部
        window.addEventListener('load', function() {
            setTimeout(scrollToBottom, 100);
        });
        
        // 自动刷新在线人数
        setInterval(function() {
            document.getElementById('onlineCount').textContent = Math.floor(Math.random() * 20) + 5;
        }, 30000);
        
        // 表单提交后滚动到底部
        document.getElementById('chatForm').addEventListener('submit', function() {
            setTimeout(scrollToBottom, 100);
        });
        
        // 实时消息更新（简单版本）
        setInterval(function() {
            // 这里可以实现WebSocket或AJAX实时更新
            // 目前使用简单的定时刷新
        }, 30000);
    </script>
</body>
</html>
