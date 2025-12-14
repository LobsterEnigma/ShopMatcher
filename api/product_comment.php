<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/User.php';

header('Content-Type: application/json; charset=utf-8');

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

$db = new Database();
$pdo = $db->getConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add_comment':
        $productId = intval($_POST['product_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        
        if (empty($productId) || empty($content)) {
            echo json_encode(['success' => false, 'message' => '产品ID和评论内容不能为空']);
            exit;
        }
        
        // 检查产品是否存在
        $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => '产品不存在']);
            exit;
        }
        
        // 插入评论（待审核状态）
        $stmt = $pdo->prepare("INSERT INTO product_comments (product_id, user_id, content, status) VALUES (?, ?, ?, 'pending')");
        if ($stmt->execute([$productId, $_SESSION['user_id'], $content])) {
            echo json_encode(['success' => true, 'message' => '评论已提交，等待审核']);
        } else {
            echo json_encode(['success' => false, 'message' => '评论提交失败']);
        }
        break;
        
    case 'like_comment':
        $commentId = intval($_POST['comment_id'] ?? 0);
        
        if (empty($commentId)) {
            echo json_encode(['success' => false, 'message' => '评论ID不能为空']);
            exit;
        }
        
        // 检查是否已点赞
        $stmt = $pdo->prepare("SELECT id FROM comment_likes WHERE comment_id = ? AND user_id = ?");
        $stmt->execute([$commentId, $_SESSION['user_id']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // 取消点赞
            $stmt = $pdo->prepare("DELETE FROM comment_likes WHERE comment_id = ? AND user_id = ?");
            $stmt->execute([$commentId, $_SESSION['user_id']]);
            
            // 更新点赞数
            $stmt = $pdo->prepare("UPDATE product_comments SET like_count = like_count - 1 WHERE id = ?");
            $stmt->execute([$commentId]);
            
            echo json_encode(['success' => true, 'liked' => false, 'message' => '已取消点赞']);
        } else {
            // 添加点赞
            $stmt = $pdo->prepare("INSERT INTO comment_likes (comment_id, user_id) VALUES (?, ?)");
            $stmt->execute([$commentId, $_SESSION['user_id']]);
            
            // 更新点赞数
            $stmt = $pdo->prepare("UPDATE product_comments SET like_count = like_count + 1 WHERE id = ?");
            $stmt->execute([$commentId]);
            
            echo json_encode(['success' => true, 'liked' => true, 'message' => '点赞成功']);
        }
        break;
        
    case 'add_reply':
        $commentId = intval($_POST['comment_id'] ?? 0);
        $replyToUserId = intval($_POST['reply_to_user_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        
        if (empty($commentId) || empty($content)) {
            echo json_encode(['success' => false, 'message' => '评论ID和回复内容不能为空']);
            exit;
        }
        
        // 插入回复（待审核状态）
        $stmt = $pdo->prepare("INSERT INTO comment_replies (comment_id, user_id, reply_to_user_id, content, status) VALUES (?, ?, ?, ?, 'pending')");
        if ($stmt->execute([$commentId, $_SESSION['user_id'], $replyToUserId ?: null, $content])) {
            // 更新评论的回复数
            $stmt = $pdo->prepare("UPDATE product_comments SET reply_count = reply_count + 1 WHERE id = ?");
            $stmt->execute([$commentId]);
            
            echo json_encode(['success' => true, 'message' => '回复已提交，等待审核']);
        } else {
            echo json_encode(['success' => false, 'message' => '回复提交失败']);
        }
        break;
        
    case 'report':
        $commentId = intval($_POST['comment_id'] ?? 0);
        $replyId = intval($_POST['reply_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        
        if ((empty($commentId) && empty($replyId)) || empty($reason)) {
            echo json_encode(['success' => false, 'message' => '请选择要举报的内容并填写举报原因']);
            exit;
        }
        
        // 检查是否已举报过
        $stmt = $pdo->prepare("SELECT id FROM comment_reports WHERE user_id = ? AND ((comment_id = ? AND comment_id IS NOT NULL) OR (reply_id = ? AND reply_id IS NOT NULL))");
        $stmt->execute([$_SESSION['user_id'], $commentId, $replyId]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => '您已经举报过此内容']);
            exit;
        }
        
        // 插入举报记录
        $stmt = $pdo->prepare("INSERT INTO comment_reports (comment_id, reply_id, user_id, reason, status) VALUES (?, ?, ?, ?, 'pending')");
        if ($stmt->execute([$commentId ?: null, $replyId ?: null, $_SESSION['user_id'], $reason])) {
            echo json_encode(['success' => true, 'message' => '举报已提交，我们会尽快处理']);
        } else {
            echo json_encode(['success' => false, 'message' => '举报提交失败']);
        }
        break;
        
    case 'get_comments':
        $productId = intval($_GET['product_id'] ?? 0);
        
        if (empty($productId)) {
            echo json_encode(['success' => false, 'message' => '产品ID不能为空']);
            exit;
        }
        
        // 获取已审核通过的评论
        $stmt = $pdo->prepare("
            SELECT pc.*, u.username, u.id as user_id,
                   (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = pc.id AND cl.user_id = ?) as is_liked,
                   (SELECT COUNT(*) FROM comment_replies cr WHERE cr.comment_id = pc.id AND cr.status = 'approved') as actual_reply_count
            FROM product_comments pc
            LEFT JOIN users u ON pc.user_id = u.id
            WHERE pc.product_id = ? AND pc.status = 'approved'
            ORDER BY pc.created_at DESC
        ");
        $stmt->execute([$_SESSION['user_id'], $productId]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 获取每个评论的回复
        foreach ($comments as $key => $comment) {
            $stmt = $pdo->prepare("
                SELECT cr.*, u.username, u.id as user_id,
                       ru.username as reply_to_username
                FROM comment_replies cr
                LEFT JOIN users u ON cr.user_id = u.id
                LEFT JOIN users ru ON cr.reply_to_user_id = ru.id
                WHERE cr.comment_id = ? AND cr.status = 'approved'
                ORDER BY cr.created_at ASC
            ");
            $stmt->execute([$comment['id']]);
            $comments[$key]['replies'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // 更新回复数为实际已审核通过的回复数
            $comments[$key]['reply_count'] = $comment['actual_reply_count'];
        }
        
        echo json_encode(['success' => true, 'comments' => $comments]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => '无效的操作']);
        break;
}
?>

