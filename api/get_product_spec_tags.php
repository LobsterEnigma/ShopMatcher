<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Product.php';

header('Content-Type: application/json');

$productId = $_GET['product_id'] ?? 0;
if (!$productId) {
    echo json_encode(['success' => false, 'message' => '产品ID不能为空']);
    exit;
}

$productObj = new Product();
$tags = $productObj->getProductSpecificationTags($productId);

echo json_encode([
    'success' => true,
    'tags' => $tags
]);
?>

