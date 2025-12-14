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
$commentId = $_GET['id'] ?? null;
$status = $_GET['status'] ?? 'all';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    
    if ($postAction === 'approve') {
        $commentId = $_POST['comment_id'] ?? null;
        $type = $_POST['type'] ?? 'comment'; // comment or reply
        
        if ($type === 'comment') {
            $stmt = $pdo->prepare("UPDATE product_comments SET status = 'approved', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            if ($stmt->execute([$commentId])) {
                $message = '评论已审核通过';
                $messageType = 'success';
            } else {
                $message = '审核失败';
                $messageType = 'danger';
            }
        } else {
            // 先获取回复所属的评论ID
            $stmt = $pdo->prepare("SELECT comment_id FROM comment_replies WHERE id = ?");
            $stmt->execute([$commentId]);
            $reply = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("UPDATE comment_replies SET status = 'approved' WHERE id = ?");
            if ($stmt->execute([$commentId])) {
                // 更新评论的回复数
                if ($reply) {
                    $stmt = $pdo->prepare("UPDATE product_comments SET reply_count = (SELECT COUNT(*) FROM comment_replies WHERE comment_id = ? AND status = 'approved') WHERE id = ?");
                    $stmt->execute([$reply['comment_id'], $reply['comment_id']]);
                }
                $message = '回复已审核通过';
                $messageType = 'success';
            } else {
                $message = '审核失败';
                $messageType = 'danger';
            }
        }
    } elseif ($postAction === 'reject') {
        $commentId = $_POST['comment_id'] ?? null;
        $type = $_POST['type'] ?? 'comment';
        
        if ($type === 'comment') {
            $stmt = $pdo->prepare("UPDATE product_comments SET status = 'rejected', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            if ($stmt->execute([$commentId])) {
                $message = '评论已拒绝';
                $messageType = 'success';
            } else {
                $message = '操作失败';
                $messageType = 'danger';
            }
        } else {
            $stmt = $pdo->prepare("UPDATE comment_replies SET status = 'rejected' WHERE id = ?");
            if ($stmt->execute([$commentId])) {
                $message = '回复已拒绝';
                $messageType = 'success';
            } else {
                $message = '操作失败';
                $messageType = 'danger';
            }
        }
    } elseif ($postAction === 'delete') {
        $commentId = $_POST['comment_id'] ?? null;
        $type = $_POST['type'] ?? 'comment';
        
        if ($type === 'comment') {
            $stmt = $pdo->prepare("DELETE FROM product_comments WHERE id = ?");
            if ($stmt->execute([$commentId])) {
                $message = '评论已删除';
                $messageType = 'success';
            } else {
                $message = '删除失败';
                $messageType = 'danger';
            }
        } else {
            // 先获取回复所属的评论ID
            $stmt = $pdo->prepare("SELECT comment_id FROM comment_replies WHERE id = ?");
            $stmt->execute([$commentId]);
            $reply = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($reply) {
                $stmt = $pdo->prepare("DELETE FROM comment_replies WHERE id = ?");
                if ($stmt->execute([$commentId])) {
                    // 更新评论的回复数（只计算已审核通过的回复）
                    $stmt = $pdo->prepare("UPDATE product_comments SET reply_count = (SELECT COUNT(*) FROM comment_replies WHERE comment_id = ? AND status = 'approved') WHERE id = ?");
                    $stmt->execute([$reply['comment_id'], $reply['comment_id']]);
                    $message = '回复已删除';
                    $messageType = 'success';
                } else {
                    $message = '删除失败';
                    $messageType = 'danger';
                }
            } else {
                $message = '回复不存在';
                $messageType = 'danger';
            }
        }
    } elseif ($postAction === 'process_report') {
        $reportId = $_POST['report_id'] ?? null;
        $reportAction = $_POST['report_action'] ?? '';
        $adminNote = trim($_POST['admin_note'] ?? '');
        
        if ($reportAction === 'approve') {
            // 处理举报：删除被举报的内容
            $stmt = $pdo->prepare("SELECT comment_id, reply_id FROM comment_reports WHERE id = ?");
            $stmt->execute([$reportId]);
            $report = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($report['comment_id']) {
                $stmt = $pdo->prepare("DELETE FROM product_comments WHERE id = ?");
                $stmt->execute([$report['comment_id']]);
            } elseif ($report['reply_id']) {
                $stmt = $pdo->prepare("DELETE FROM comment_replies WHERE id = ?");
                $stmt->execute([$report['reply_id']]);
            }
            
            $stmt = $pdo->prepare("UPDATE comment_reports SET status = 'processed', admin_id = ?, admin_note = ?, processed_at = CURRENT_TIMESTAMP WHERE id = ?");
            if ($stmt->execute([$_SESSION['admin_id'], $adminNote, $reportId])) {
                $message = '举报已处理，相关内容已删除';
                $messageType = 'success';
            }
        } elseif ($reportAction === 'reject') {
            $stmt = $pdo->prepare("UPDATE comment_reports SET status = 'rejected', admin_id = ?, admin_note = ?, processed_at = CURRENT_TIMESTAMP WHERE id = ?");
            if ($stmt->execute([$_SESSION['admin_id'], $adminNote, $reportId])) {
                $message = '举报已拒绝';
                $messageType = 'success';
            }
        }
    }
}

// 获取评论列表
$comments = [];
$replies = [];
$reports = [];

if ($action === 'list') {
    // 获取评论
    $sql = "SELECT pc.*, u.username, p.name as product_name, p.id as product_id
            FROM product_comments pc
            LEFT JOIN users u ON pc.user_id = u.id
            LEFT JOIN products p ON pc.product_id = p.id
            WHERE 1=1";
    
    $params = [];
    if ($status === 'pending') {
        $sql .= " AND pc.status = 'pending'";
    } elseif ($status === 'approved') {
        $sql .= " AND pc.status = 'approved'";
    } elseif ($status === 'rejected') {
        $sql .= " AND pc.status = 'rejected'";
    }
    
    $sql .= " ORDER BY pc.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 获取回复
    $sql = "SELECT cr.*, u.username, ru.username as reply_to_username, pc.product_id, p.name as product_name
            FROM comment_replies cr
            LEFT JOIN users u ON cr.user_id = u.id
            LEFT JOIN users ru ON cr.reply_to_user_id = ru.id
            LEFT JOIN product_comments pc ON cr.comment_id = pc.id
            LEFT JOIN products p ON pc.product_id = p.id
            WHERE 1=1";
    
    $params = [];
    if ($status === 'pending') {
        $sql .= " AND cr.status = 'pending'";
    } elseif ($status === 'approved') {
        $sql .= " AND cr.status = 'approved'";
    } elseif ($status === 'rejected') {
        $sql .= " AND cr.status = 'rejected'";
    }
    
    $sql .= " ORDER BY cr.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 获取举报列表
if ($action === 'reports') {
    $sql = "SELECT cr.*, 
                   u.username as reporter_username,
                   pc.content as comment_content,
                   pc.user_id as comment_user_id,
                   cu.username as comment_username,
                   rp.content as reply_content,
                   rp.user_id as reply_user_id,
                   ru.username as reply_username,
                   p.name as product_name
            FROM comment_reports cr
            LEFT JOIN users u ON cr.user_id = u.id
            LEFT JOIN product_comments pc ON cr.comment_id = pc.id
            LEFT JOIN users cu ON pc.user_id = cu.id
            LEFT JOIN comment_replies rp ON cr.reply_id = rp.id
            LEFT JOIN users ru ON rp.user_id = ru.id
            LEFT JOIN products p ON (pc.product_id = p.id OR (SELECT product_id FROM product_comments WHERE id = rp.comment_id) = p.id)
            WHERE cr.status = 'pending'
            ORDER BY cr.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>评论管理 - 后台管理</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            padding: 30px;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-approved {
            background: #d4edda;
            color: #155724;
        }
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        .comment-item {
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 15px;
            background: white;
            border-radius: 8px;
        }
        .btn-sm {
            padding: 5px 12px;
            font-size: 12px;
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
                        <a class="nav-link" href="ai.php">
                            <i class="fas fa-robot"></i> AI设置
                        </a>
                        <a class="nav-link" href="settings.php">
                            <i class="fas fa-cog"></i> 系统设置
                        </a>
                        <a class="nav-link" href="guide.php">
                            <i class="fas fa-book"></i> 指南管理
                        </a>
                        <a class="nav-link active" href="comments.php">
                            <i class="fas fa-comment-dots"></i> 评论管理
                        </a>
                        <a class="nav-link" href="product_comments.php">
                            <i class="fas fa-star"></i> 站长点评
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
            <div class="col-md-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-comment-dots"></i> 评论管理</h2>
                    <div>
                        <a href="comments.php?action=list&status=all" class="btn btn-outline-primary btn-sm <?php echo $status === 'all' ? 'active' : ''; ?>">全部</a>
                        <a href="comments.php?action=list&status=pending" class="btn btn-outline-warning btn-sm <?php echo $status === 'pending' ? 'active' : ''; ?>">待审核</a>
                        <a href="comments.php?action=list&status=approved" class="btn btn-outline-success btn-sm <?php echo $status === 'approved' ? 'active' : ''; ?>">已通过</a>
                        <a href="comments.php?action=list&status=rejected" class="btn btn-outline-danger btn-sm <?php echo $status === 'rejected' ? 'active' : ''; ?>">已拒绝</a>
                        <a href="comments.php?action=reports" class="btn btn-outline-danger btn-sm <?php echo $action === 'reports' ? 'active' : ''; ?>">
                            <i class="fas fa-flag"></i> 举报管理
                        </a>
                    </div>
                </div>

                <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($action === 'reports'): ?>
                <!-- 举报列表 -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-4"><i class="fas fa-flag"></i> 待处理举报</h5>
                        <?php if (empty($reports)): ?>
                        <p class="text-muted text-center py-4">暂无待处理的举报</p>
                        <?php else: ?>
                        <?php foreach ($reports as $report): ?>
                        <div class="comment-item">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <strong>举报人：</strong><?php echo htmlspecialchars($report['reporter_username']); ?>
                                    <small class="text-muted ms-2">
                                        <i class="fas fa-calendar"></i> <?php echo date('Y-m-d H:i', strtotime($report['created_at'])); ?>
                                    </small>
                                </div>
                                <span class="status-badge status-pending">待处理</span>
                            </div>
                            <div class="mb-2">
                                <strong>举报原因：</strong><?php echo htmlspecialchars($report['reason']); ?>
                            </div>
                            <?php if ($report['comment_content']): ?>
                            <div class="mb-2 p-2 bg-light rounded">
                                <strong>被举报评论：</strong>
                                <div><?php echo htmlspecialchars($report['comment_content']); ?></div>
                                <small class="text-muted">评论人：<?php echo htmlspecialchars($report['comment_username']); ?></small>
                                <br>
                                <small class="text-muted">产品：<a href="../product.php?id=<?php echo $report['product_id']; ?>" target="_blank"><?php echo htmlspecialchars($report['product_name']); ?></a></small>
                            </div>
                            <?php endif; ?>
                            <?php if ($report['reply_content']): ?>
                            <div class="mb-2 p-2 bg-light rounded">
                                <strong>被举报回复：</strong>
                                <div><?php echo htmlspecialchars($report['reply_content']); ?></div>
                                <small class="text-muted">回复人：<?php echo htmlspecialchars($report['reply_username']); ?></small>
                            </div>
                            <?php endif; ?>
                            <form method="POST" class="mt-3">
                                <input type="hidden" name="action" value="process_report">
                                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                <div class="mb-2">
                                    <textarea class="form-control" name="admin_note" rows="2" placeholder="处理备注（可选）"></textarea>
                                </div>
                                <button type="submit" name="report_action" value="approve" class="btn btn-danger btn-sm" onclick="return confirm('确定要删除被举报的内容吗？')">
                                    <i class="fas fa-trash"></i> 删除内容
                                </button>
                                <button type="submit" name="report_action" value="reject" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-times"></i> 拒绝举报
                                </button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <!-- 评论列表 -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-4"><i class="fas fa-comments"></i> 评论列表</h5>
                        <?php if (empty($comments) && empty($replies)): ?>
                        <p class="text-muted text-center py-4">暂无评论</p>
                        <?php else: ?>
                        <?php foreach ($comments as $comment): ?>
                        <div class="comment-item">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <strong><?php echo htmlspecialchars($comment['username']); ?></strong>
                                    <small class="text-muted ms-2">
                                        <i class="fas fa-calendar"></i> <?php echo date('Y-m-d H:i', strtotime($comment['created_at'])); ?>
                                    </small>
                                    <br>
                                    <small class="text-muted">
                                        产品：<a href="../product.php?id=<?php echo $comment['product_id']; ?>" target="_blank"><?php echo htmlspecialchars($comment['product_name']); ?></a>
                                    </small>
                                </div>
                                <span class="status-badge status-<?php echo $comment['status']; ?>">
                                    <?php 
                                    echo $comment['status'] === 'pending' ? '待审核' : 
                                         ($comment['status'] === 'approved' ? '已通过' : '已拒绝'); 
                                    ?>
                                </span>
                            </div>
                            <div class="mb-2">
                                <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                            </div>
                            <div class="d-flex gap-2">
                                <span class="badge bg-secondary">点赞: <?php echo $comment['like_count']; ?></span>
                                <span class="badge bg-secondary">回复: <?php echo $comment['reply_count']; ?></span>
                            </div>
                            <div class="mt-3">
                                <?php if ($comment['status'] === 'pending'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                    <input type="hidden" name="type" value="comment">
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="fas fa-check"></i> 通过
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                    <input type="hidden" name="type" value="comment">
                                    <button type="submit" class="btn btn-warning btn-sm">
                                        <i class="fas fa-times"></i> 拒绝
                                    </button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('确定要删除这条评论吗？')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                    <input type="hidden" name="type" value="comment">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash"></i> 删除
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <hr class="my-4">
                        <h6 class="mb-3"><i class="fas fa-reply"></i> 回复列表</h6>
                        <?php foreach ($replies as $reply): ?>
                        <div class="comment-item" style="border-left-color: #28a745;">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <strong><?php echo htmlspecialchars($reply['username']); ?></strong>
                                    <?php if ($reply['reply_to_username']): ?>
                                    <span class="text-muted">回复 @<?php echo htmlspecialchars($reply['reply_to_username']); ?></span>
                                    <?php endif; ?>
                                    <small class="text-muted ms-2">
                                        <i class="fas fa-calendar"></i> <?php echo date('Y-m-d H:i', strtotime($reply['created_at'])); ?>
                                    </small>
                                    <br>
                                    <small class="text-muted">
                                        产品：<a href="../product.php?id=<?php echo $reply['product_id']; ?>" target="_blank"><?php echo htmlspecialchars($reply['product_name']); ?></a>
                                    </small>
                                </div>
                                <span class="status-badge status-<?php echo $reply['status']; ?>">
                                    <?php 
                                    echo $reply['status'] === 'pending' ? '待审核' : 
                                         ($reply['status'] === 'approved' ? '已通过' : '已拒绝'); 
                                    ?>
                                </span>
                            </div>
                            <div class="mb-2">
                                <?php echo nl2br(htmlspecialchars($reply['content'])); ?>
                            </div>
                            <div class="mt-3">
                                <?php if ($reply['status'] === 'pending'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="comment_id" value="<?php echo $reply['id']; ?>">
                                    <input type="hidden" name="type" value="reply">
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="fas fa-check"></i> 通过
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="comment_id" value="<?php echo $reply['id']; ?>">
                                    <input type="hidden" name="type" value="reply">
                                    <button type="submit" class="btn btn-warning btn-sm">
                                        <i class="fas fa-times"></i> 拒绝
                                    </button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('确定要删除这条回复吗？')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="comment_id" value="<?php echo $reply['id']; ?>">
                                    <input type="hidden" name="type" value="reply">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash"></i> 删除
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

