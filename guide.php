<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/Markdown.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 获取指南列表
$db = new Database();
$pdo = $db->getConnection();
$stmt = $pdo->prepare("SELECT id, title, slug, excerpt, view_count, created_at, updated_at FROM guides WHERE is_active = 1 ORDER BY updated_at DESC, created_at DESC");
$stmt->execute();
$guides = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 为每个指南生成摘要（如果没有excerpt）
foreach ($guides as $key => $guide) {
    if (empty($guide['excerpt'])) {
        // 从内容中提取摘要
        $stmt = $pdo->prepare("SELECT content FROM guides WHERE id = ?");
        $stmt->execute([$guide['id']]);
        $content = $stmt->fetchColumn();
        $plainText = Markdown::toPlainText($content, 150);
        $guides[$key]['excerpt'] = $plainText;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>使用指南 - <?php echo getSiteName(); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .guide-list-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 40px;
            margin-bottom: 30px;
        }
        .guide-header {
            border-bottom: 3px solid #667eea;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .guide-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            transition: all 0.3s;
            background: white;
        }
        .guide-card:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transform: translateY(-3px);
            border-color: #667eea;
        }
        .guide-card h3 {
            color: #667eea;
            margin-bottom: 15px;
        }
        .guide-card h3 a {
            color: inherit;
            text-decoration: none;
        }
        .guide-card h3 a:hover {
            text-decoration: underline;
        }
        .guide-excerpt {
            color: #6c757d;
            line-height: 1.8;
            margin-bottom: 15px;
        }
        .guide-meta {
            display: flex;
            align-items: center;
            gap: 20px;
            color: #adb5bd;
            font-size: 0.9rem;
        }
        .guide-meta i {
            margin-right: 5px;
        }
        .read-more {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        .read-more:hover {
            text-decoration: underline;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #dee2e6;
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
                    <a class="nav-link" href="products.php">产品对比</a>
                    <a class="nav-link" href="chat.php">讨论区</a>
                    <a class="nav-link active" href="guide.php">使用指南</a>
                    <a class="nav-link" href="profile.php">个人中心</a>
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
        <div class="row">
            <div class="col-md-12">
                <div class="guide-list-container">
                    <div class="guide-header">
                        <h1><i class="fas fa-book"></i> 使用指南</h1>
                        <p class="text-muted mb-0">查看产品对比功能的使用教程和技巧</p>
                    </div>
                    
                    <?php if (empty($guides)): ?>
                    <div class="empty-state">
                        <i class="fas fa-book-open"></i>
                        <h4>暂无指南文章</h4>
                        <p>指南内容正在准备中，敬请期待...</p>
                    </div>
                    <?php else: ?>
                    <div class="guide-list">
                        <?php foreach ($guides as $guide): ?>
                        <div class="guide-card">
                            <h3>
                                <a href="guide_detail.php?<?php echo $guide['slug'] ? 'slug=' . urlencode($guide['slug']) : 'id=' . $guide['id']; ?>">
                                    <?php echo htmlspecialchars($guide['title']); ?>
                                </a>
                            </h3>
                            <div class="guide-excerpt">
                                <?php echo htmlspecialchars($guide['excerpt']); ?>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="guide-meta">
                                    <span>
                                        <i class="fas fa-calendar"></i>
                                        <?php echo date('Y-m-d', strtotime($guide['updated_at'] ?? $guide['created_at'])); ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-eye"></i>
                                        <?php echo number_format($guide['view_count']); ?> 次阅读
                                    </span>
                                </div>
                                <a href="guide_detail.php?<?php echo $guide['slug'] ? 'slug=' . urlencode($guide['slug']) : 'id=' . $guide['id']; ?>" class="read-more">
                                    阅读全文 <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="text-center">
                    <a href="products.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-balance-scale"></i> 开始对比产品
                    </a>
                    <a href="profile.php" class="btn btn-outline-secondary btn-lg ms-2">
                        <i class="fas fa-user"></i> 返回个人中心
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
