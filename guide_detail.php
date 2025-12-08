<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/Markdown.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 获取指南ID或slug
$guideId = $_GET['id'] ?? null;
$guideSlug = $_GET['slug'] ?? null;

if (!$guideId && !$guideSlug) {
    header('Location: guide.php');
    exit;
}

// 获取指南内容
$db = new Database();
$pdo = $db->getConnection();

if ($guideSlug) {
    $stmt = $pdo->prepare("SELECT * FROM guides WHERE slug = ? AND is_active = 1");
    $stmt->execute([$guideSlug]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM guides WHERE id = ? AND is_active = 1");
    $stmt->execute([$guideId]);
}

$guide = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$guide) {
    header('Location: guide.php');
    exit;
}

// 增加浏览次数
$stmt = $pdo->prepare("UPDATE guides SET view_count = view_count + 1 WHERE id = ?");
$stmt->execute([$guide['id']]);
$guide['view_count']++;
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($guide['title']); ?> - <?php echo getSiteName(); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .guide-container {
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
        .guide-content {
            line-height: 1.8;
            color: #495057;
        }
        .guide-content h1,
        .guide-content h2,
        .guide-content h3 {
            color: #667eea;
            margin-top: 30px;
            margin-bottom: 15px;
        }
        .guide-content h1 {
            font-size: 2rem;
        }
        .guide-content h2 {
            font-size: 1.5rem;
        }
        .guide-content h3 {
            font-size: 1.25rem;
        }
        .guide-content ul,
        .guide-content ol {
            padding-left: 2rem;
            margin-bottom: 1rem;
        }
        .guide-content li {
            margin-bottom: 0.5rem;
        }
        .guide-content code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
        }
        .guide-content pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            margin-bottom: 1rem;
        }
        .guide-content blockquote {
            border-left: 4px solid #667eea;
            padding-left: 20px;
            margin-left: 0;
            color: #6c757d;
            font-style: italic;
        }
        .guide-content table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
        }
        .guide-content table th,
        .guide-content table td {
            border: 1px solid #dee2e6;
            padding: 8px 12px;
            text-align: left;
        }
        .guide-content table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .guide-content img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin: 1rem 0;
        }
        .guide-content a {
            color: #667eea;
            text-decoration: none;
        }
        .guide-content a:hover {
            text-decoration: underline;
        }
        .guide-meta {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .guide-meta i {
            margin-right: 5px;
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
                <a class="nav-link" href="chat.php">讨论区</a>
                <a class="nav-link" href="profile.php">个人中心</a>
            </div>
            
            <div class="navbar-nav">
                <span class="text-light me-3">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                </span>
                <a href="logout.php" class="btn btn-outline-light">退出</a>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <div class="row">
            <div class="col-md-12">
                <div class="guide-container">
                    <div class="guide-header">
                        <h1><?php echo htmlspecialchars($guide['title']); ?></h1>
                        <div class="guide-meta">
                            <span class="me-3">
                                <i class="fas fa-calendar"></i>
                                发布时间：<?php echo date('Y-m-d H:i', strtotime($guide['created_at'])); ?>
                            </span>
                            <span class="me-3">
                                <i class="fas fa-edit"></i>
                                最后更新：<?php echo date('Y-m-d H:i', strtotime($guide['updated_at'] ?? $guide['created_at'])); ?>
                            </span>
                            <span>
                                <i class="fas fa-eye"></i>
                                阅读量：<?php echo number_format($guide['view_count']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="guide-content">
                        <?php echo Markdown::toHtml($guide['content']); ?>
                    </div>
                </div>
                
                <div class="text-center">
                    <a href="guide.php" class="btn btn-outline-primary btn-lg">
                        <i class="fas fa-arrow-left"></i> 返回指南列表
                    </a>
                    <a href="products.php" class="btn btn-primary btn-lg ms-2">
                        <i class="fas fa-balance-scale"></i> 开始对比产品
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

