<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Product.php';

header('Content-Type: application/json');

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '请求方法错误']);
    exit;
}

// 获取请求数据
$input = json_decode(file_get_contents('php://input'), true);
$productId = $input['product_id'] ?? null;

if (!$productId) {
    echo json_encode(['success' => false, 'message' => '产品ID不能为空']);
    exit;
}

try {
    $product = new Product();
    $result = $product->generateAISummary($productId);
    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '服务器错误：' . $e->getMessage()]);
}
?>
