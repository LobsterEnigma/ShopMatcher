<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/Product.php';
require_once 'classes/Markdown.php';

$productId = $_GET['id'] ?? 0;
if (!$productId) {
    header('Location: products.php');
    exit;
}

$productObj = new Product();
$product = $productObj->getProductById($productId);

if (!$product) {
    $_SESSION['error'] = '产品不存在';
    header('Location: products.php');
    exit;
}

// 获取产品标签
$product['tags'] = $productObj->getProductTags($productId);

// 获取技术规格标签（如果是标签化模式）
$productSpecTags = [];
if (isset($product['specification_mode']) && $product['specification_mode'] === 'tagged') {
    $productSpecTags = $productObj->getProductSpecificationTags($productId);
}

// 获取该产品的站长点评
$db = new Database();
$pdo = $db->getConnection();
$stmt = $pdo->prepare("
    SELECT pac.*, a.username as admin_username 
    FROM product_admin_comments pac
    LEFT JOIN admins a ON pac.admin_id = a.id
    WHERE pac.product_id = ? AND pac.is_active = 1
    ORDER BY pac.display_order ASC, pac.created_at DESC
");
$stmt->execute([$productId]);
$adminComments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - <?php echo getSiteName(); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .product-detail {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .product-image-large {
            width: 100%;
            height: 400px;
            object-fit: cover;
        }
        .product-info {
            padding: 30px;
        }
        .price-tag {
            font-size: 2rem;
            font-weight: bold;
            color: #28a745;
        }
        .product-section {
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .product-section h5 {
            color: #667eea;
            margin-bottom: 15px;
            font-weight: bold;
        }
        .markdown-content p {
            margin-bottom: 0.5rem;
        }
        .markdown-content ul,
        .markdown-content ol {
            padding-left: 1.25rem;
            margin-bottom: 1rem;
        }
        .breadcrumb {
            background: transparent;
            padding: 10px 0;
        }
        .badge-category {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        .admin-comment-item {
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        .admin-comment-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        /* 用户评论样式 */
        .user-comment-item {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .user-comment-item:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .comment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .comment-actions {
            display: flex;
            gap: 15px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #e9ecef;
        }
        .comment-action-btn {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 4px;
            transition: all 0.2s;
        }
        .comment-action-btn:hover {
            background: #f8f9fa;
            color: #495057;
        }
        .comment-action-btn.liked {
            color: #dc3545;
        }
        .reply-form {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            display: none;
        }
        .reply-item {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 3px solid #667eea;
        }
        .reply-to {
            color: #667eea;
            font-weight: bold;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
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
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="切换导航">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarMain">
                <div class="navbar-nav me-auto">
                    <a class="nav-link" href="index.php">首页</a>
                    <a class="nav-link active" href="products.php">产品对比</a>
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <a class="nav-link" href="chat.php">讨论区</a>
                    <a class="nav-link" href="guide.php">使用指南</a>
                    <a class="nav-link" href="profile.php">个人中心</a>
                    <?php endif; ?>
                </div>
                
                <div class="user-info">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <span class="text-light">
                            <i class="fas fa-user"></i> 
                            <?php echo htmlspecialchars($_SESSION['username'] ?? '用户'); ?>
                        </span>
                        <a href="logout.php" class="btn btn-outline-light btn-sm">退出</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-light me-2">登录</a>
                        <a href="register.php" class="btn btn-primary">注册</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <!-- 面包屑导航 -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">首页</a></li>
                <li class="breadcrumb-item"><a href="products.php">产品列表</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($product['name']); ?></li>
            </ol>
        </nav>

        <div class="product-detail">
            <div class="row">
                <!-- 产品图片 -->
                <div class="col-md-6">
                    <?php if ($product['image_url']): ?>
                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                         class="product-image-large" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>">
                    <?php else: ?>
                    <div class="product-image-large d-flex align-items-center justify-content-center bg-light">
                        <i class="fas fa-image fa-5x text-muted"></i>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- 产品信息 -->
                <div class="col-md-6 product-info">
                    <div class="mb-3">
                        <span class="badge-category">
                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($product['category_name'] ?? '未分类'); ?>
                        </span>
                        <?php if (!empty($product['tags'])): ?>
                            <?php foreach ($product['tags'] as $tag): ?>
                            <a href="tag.php?name=<?php echo urlencode($tag['name']); ?>" class="badge bg-secondary text-decoration-none ms-2">
                                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($tag['name']); ?>
                            </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <h2 class="mb-3"><?php echo htmlspecialchars($product['name']); ?></h2>
                    
                    <div class="mb-3">
                        <span class="text-muted">品牌：</span>
                        <strong><?php echo htmlspecialchars($product['brand']); ?></strong>
                    </div>
                    
                    <div class="mb-4">
                        <span class="price-tag">¥<?php echo number_format($product['price'], 2); ?></span>
                    </div>
                    
                    <?php if (!empty($product['description'])): ?>
                    <div class="mb-4">
                        <h5>产品描述</h5>
                        <div class="text-muted markdown-content">
                            <?php echo Markdown::toHtml($product['description']); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-grid gap-2">
                        <a href="products.php" class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-arrow-left"></i> 返回产品列表
                        </a>
                        <button class="btn btn-primary btn-lg" onclick="addToCompare(<?php echo $product['id']; ?>)">
                            <i class="fas fa-balance-scale"></i> 添加到对比
                        </button>
                    </div>
                </div>
            </div>

            <!-- 详细信息 -->
            <div class="row mt-4">
                <div class="col-12">
                    <?php if (!empty($product['features'])): ?>
                    <div class="product-section">
                        <h5><i class="fas fa-star"></i> 产品特性</h5>
                        <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="markdown-content"><?php echo Markdown::toHtml($product['features']); ?></div>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-lock"></i> 此内容需要登录后才能查看，请先 <a href="login.php">登录</a> 或 <a href="register.php">注册</a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($product['specifications']) || !empty($productSpecTags)): ?>
                    <div class="product-section">
                        <h5><i class="fas fa-cog"></i> 技术规格</h5>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <?php if (isset($product['specification_mode']) && $product['specification_mode'] === 'tagged' && !empty($productSpecTags)): ?>
                                <!-- 标签化显示 -->
                                <div class="specification-tagged-display">
                                    <?php
                                    // 按字段分组
                                    $tagsByField = [];
                                    foreach ($productSpecTags as $tag) {
                                        $fieldId = $tag['field_id'];
                                        $tagValue = $tag['tag_name'] ?? $tag['custom_value'];
                                        // 过滤空值
                                        if (empty($tagValue)) {
                                            continue;
                                        }
                                        
                                        if (!isset($tagsByField[$fieldId])) {
                                            $tagsByField[$fieldId] = [
                                                'field_name' => $tag['field_name'] ?? '',
                                                'display_order' => $tag['display_order'] ?? 999999,
                                                'tags' => []
                                            ];
                                        }
                                        $tagsByField[$fieldId]['tags'][] = [
                                            'value' => $tagValue,
                                            'tag_display_order' => $tag['tag_display_order'] ?? 999999
                                        ];
                                    }
                                    
                                    // 按display_order排序字段
                                    uasort($tagsByField, function($a, $b) {
                                        return $a['display_order'] <=> $b['display_order'];
                                    });
                                    
                                    foreach ($tagsByField as $fieldId => $fieldData):
                                        // 对标签按display_order排序
                                        usort($fieldData['tags'], function($a, $b) {
                                            return $a['tag_display_order'] <=> $b['tag_display_order'];
                                        });
                                    ?>
                                    <div class="mb-3 p-3 border rounded">
                                        <div class="fw-bold mb-2"><?php echo htmlspecialchars($fieldData['field_name']); ?>：</div>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach ($fieldData['tags'] as $tagItem): ?>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($tagItem['value']); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <!-- Markdown显示 -->
                                <div class="markdown-content"><?php echo Markdown::toHtml($product['specifications']); ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-lock"></i> 此内容需要登录后才能查看，请先 <a href="login.php">登录</a> 或 <a href="register.php">注册</a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($product['faq'])): ?>
                    <div class="product-section">
                        <h5><i class="fas fa-question-circle"></i> 常见问题</h5>
                        <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="markdown-content"><?php echo Markdown::toHtml($product['faq']); ?></div>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-lock"></i> 此内容需要登录后才能查看，请先 <a href="login.php">登录</a> 或 <a href="register.php">注册</a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($adminComments)): ?>
                    <div class="product-section">
                        <h5><i class="fas fa-comments"></i> 站长意见</h5>
                        <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="admin-comments">
                            <?php foreach ($adminComments as $comment): ?>
                            <div class="admin-comment-item mb-4 p-3 bg-white rounded border-start border-primary border-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <strong class="text-primary">
                                            <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($comment['admin_name'] ?: $comment['admin_username']); ?>
                                        </strong>
                                        <small class="text-muted ms-2">
                                            <i class="fas fa-calendar"></i> <?php echo date('Y-m-d H:i', strtotime($comment['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="markdown-content text-muted">
                                    <?php echo Markdown::toHtml($comment['comment']); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-lock"></i> 此内容需要登录后才能查看，请先 <a href="login.php">登录</a> 或 <a href="register.php">注册</a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 用户评论区 -->
        <div class="product-detail mt-4">
            <div class="product-section">
                <h5><i class="fas fa-comments"></i> 用户评论</h5>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                <!-- 评论表单 -->
                <div class="mb-4">
                    <form id="commentForm" class="mb-3">
                        <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                        <div class="mb-3">
                            <textarea class="form-control" id="commentContent" name="content" rows="3" placeholder="写下您的评论..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> 发表评论
                        </button>
                    </form>
                </div>
                
                <!-- 评论列表 -->
                <div id="commentsContainer">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">加载中...</span>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-lock"></i> 用户评论需要登录后才能查看，请先 <a href="login.php">登录</a> 或 <a href="register.php">注册</a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 相关产品推荐 -->
        <div class="mt-5">
            <h4 class="mb-4"><i class="fas fa-th-large"></i> 相关产品推荐</h4>
            <div class="row">
                <?php
                // 获取同分类的其他产品
                $relatedProducts = $productObj->getAllProducts($product['category_id']);
                $relatedProducts = array_filter($relatedProducts, function($p) use ($productId) {
                    return $p['id'] != $productId;
                });
                $relatedProducts = array_slice($relatedProducts, 0, 3);
                ?>
                
                <?php if (!empty($relatedProducts)): ?>
                    <?php foreach ($relatedProducts as $relatedProduct): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <?php if ($relatedProduct['image_url']): ?>
                            <img src="<?php echo htmlspecialchars($relatedProduct['image_url']); ?>" 
                                 class="card-img-top" 
                                 style="height: 200px; object-fit: cover;"
                                 alt="<?php echo htmlspecialchars($relatedProduct['name']); ?>">
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($relatedProduct['name']); ?></h5>
                                <p class="card-text text-muted"><?php echo htmlspecialchars($relatedProduct['brand']); ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="h5 text-primary mb-0">¥<?php echo number_format($relatedProduct['price'], 2); ?></span>
                                    <a href="product.php?id=<?php echo $relatedProduct['id']; ?>" class="btn btn-primary btn-sm">查看详情</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <p class="text-muted text-center">暂无相关产品</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const productId = <?php echo $productId; ?>;
        
        function addToCompare(productId) {
            // 获取当前对比列表
            let compareList = JSON.parse(localStorage.getItem('compareList') || '[]');
            
            // 检查是否已在对比列表中
            if (compareList.includes(productId)) {
                alert('该产品已在对比列表中');
                return;
            }
            
            // 检查对比列表是否已满
            if (compareList.length >= 10) {
                alert('对比列表已满，最多只能对比10个产品');
                return;
            }
            
            // 添加到对比列表
            compareList.push(productId);
            localStorage.setItem('compareList', JSON.stringify(compareList));
            
            // 提示并跳转
            if (confirm('已添加到对比列表！是否立即前往对比？')) {
                window.location.href = 'products.php';
            }
        }
        
        // 加载评论
        function loadComments() {
            fetch(`api/product_comment.php?action=get_comments&product_id=${productId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderComments(data.comments);
                    } else {
                        document.getElementById('commentsContainer').innerHTML = `
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> ${data.message}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    document.getElementById('commentsContainer').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> 加载评论失败，请刷新页面重试
                        </div>
                    `;
                });
        }
        
        // 渲染评论
        function renderComments(comments) {
            const container = document.getElementById('commentsContainer');
            
            if (comments.length === 0) {
                container.innerHTML = '<p class="text-muted text-center py-4">暂无评论，快来发表第一条评论吧！</p>';
                return;
            }
            
            container.innerHTML = comments.map(comment => `
                <div class="user-comment-item" data-comment-id="${comment.id}">
                    <div class="comment-header">
                        <div>
                            <strong>${escapeHtml(comment.username)}</strong>
                            <small class="text-muted ms-2">
                                <i class="fas fa-calendar"></i> ${formatDate(comment.created_at)}
                            </small>
                        </div>
                    </div>
                    <div class="comment-content mb-2">
                        ${escapeHtml(comment.content).replace(/\n/g, '<br>')}
                    </div>
                    <div class="comment-actions">
                        <button class="comment-action-btn ${comment.is_liked ? 'liked' : ''}" onclick="toggleLike(${comment.id}, this)">
                            <i class="fas fa-heart"></i> 点赞 (${comment.like_count})
                        </button>
                        <button class="comment-action-btn" onclick="showReplyForm(${comment.id}, ${comment.user_id}, '${escapeHtml(comment.username)}')">
                            <i class="fas fa-reply"></i> 回复
                        </button>
                        <button class="comment-action-btn" onclick="reportComment(${comment.id}, null)">
                            <i class="fas fa-flag"></i> 举报
                        </button>
                    </div>
                    
                    <!-- 回复表单 -->
                    <div class="reply-form" id="replyForm_${comment.id}">
                        <form onsubmit="submitReply(event, ${comment.id}, ${comment.user_id})">
                            <div class="mb-2">
                                <small class="text-muted">回复 <span class="reply-to">@${escapeHtml(comment.username)}</span></small>
                            </div>
                            <textarea class="form-control mb-2" rows="2" placeholder="写下您的回复..." required></textarea>
                            <div>
                                <button type="submit" class="btn btn-sm btn-primary">提交回复</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="hideReplyForm(${comment.id})">取消</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- 回复列表 -->
                    ${comment.replies && comment.replies.length > 0 ? `
                        <div class="replies mt-3">
                            ${comment.replies.map(reply => `
                                <div class="reply-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <strong>${escapeHtml(reply.username)}</strong>
                                            ${reply.reply_to_username ? `<span class="text-muted ms-2">回复 <span class="reply-to">@${escapeHtml(reply.reply_to_username)}</span></span>` : ''}
                                            <small class="text-muted ms-2">
                                                <i class="fas fa-calendar"></i> ${formatDate(reply.created_at)}
                                            </small>
                                            <div class="mt-1">${escapeHtml(reply.content).replace(/\n/g, '<br>')}</div>
                                        </div>
                                        <button class="btn btn-sm btn-link text-danger p-0 ms-2" onclick="reportComment(null, ${reply.id})" title="举报">
                                            <i class="fas fa-flag"></i>
                                        </button>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    ` : ''}
                </div>
            `).join('');
        }
        
        // 提交评论
        <?php if (isset($_SESSION['user_id'])): ?>
        document.getElementById('commentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const content = document.getElementById('commentContent').value.trim();
            
            if (!content) {
                alert('请输入评论内容');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'add_comment');
            formData.append('product_id', productId);
            formData.append('content', content);
            
            fetch('api/product_comment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    document.getElementById('commentContent').value = '';
                    loadComments();
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                alert('评论提交失败，请稍后重试');
            });
        });
        <?php endif; ?>
        
        // 点赞/取消点赞
        function toggleLike(commentId, button) {
            const formData = new FormData();
            formData.append('action', 'like_comment');
            formData.append('comment_id', commentId);
            
            fetch('api/product_comment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 更新按钮状态
                    if (data.liked) {
                        button.classList.add('liked');
                    } else {
                        button.classList.remove('liked');
                    }
                    // 更新点赞数
                    const likeText = button.innerHTML;
                    const match = likeText.match(/\((\d+)\)/);
                    if (match) {
                        const currentCount = parseInt(match[1]);
                        const newCount = data.liked ? currentCount + 1 : currentCount - 1;
                        button.innerHTML = likeText.replace(/\(\d+\)/, `(${newCount})`);
                    }
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                alert('操作失败，请稍后重试');
            });
        }
        
        // 显示回复表单
        function showReplyForm(commentId, replyToUserId, replyToUsername) {
            const form = document.getElementById(`replyForm_${commentId}`);
            form.style.display = 'block';
            form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        
        // 隐藏回复表单
        function hideReplyForm(commentId) {
            document.getElementById(`replyForm_${commentId}`).style.display = 'none';
        }
        
        // 提交回复
        function submitReply(e, commentId, replyToUserId) {
            e.preventDefault();
            const form = e.target;
            const content = form.querySelector('textarea').value.trim();
            
            if (!content) {
                alert('请输入回复内容');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'add_reply');
            formData.append('comment_id', commentId);
            formData.append('reply_to_user_id', replyToUserId);
            formData.append('content', content);
            
            fetch('api/product_comment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    form.querySelector('textarea').value = '';
                    hideReplyForm(commentId);
                    loadComments();
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                alert('回复提交失败，请稍后重试');
            });
        }
        
        // 举报
        function reportComment(commentId, replyId) {
            const reason = prompt('请输入举报原因：');
            if (!reason || !reason.trim()) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'report');
            if (commentId) formData.append('comment_id', commentId);
            if (replyId) formData.append('reply_id', replyId);
            formData.append('reason', reason.trim());
            
            fetch('api/product_comment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
            })
            .catch(error => {
                alert('举报提交失败，请稍后重试');
            });
        }
        
        // 工具函数
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diff = now - date;
            const minutes = Math.floor(diff / 60000);
            const hours = Math.floor(diff / 3600000);
            const days = Math.floor(diff / 86400000);
            
            if (minutes < 1) return '刚刚';
            if (minutes < 60) return `${minutes}分钟前`;
            if (hours < 24) return `${hours}小时前`;
            if (days < 7) return `${days}天前`;
            
            return date.toLocaleDateString('zh-CN', { year: 'numeric', month: 'long', day: 'numeric' });
        }
        
        // 页面加载时加载评论（仅登录用户）
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['user_id'])): ?>
            loadComments();
            <?php endif; ?>
        });
    </script>
</body>
</html>

