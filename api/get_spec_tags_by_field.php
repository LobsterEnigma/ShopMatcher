<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Product.php';

header('Content-Type: application/json');

$fieldId = $_GET['field_id'] ?? 0;
if (!$fieldId) {
    echo json_encode(['success' => false, 'message' => '字段ID不能为空']);
    exit;
}

$productObj = new Product();
$tags = $productObj->getSpecificationTagsByField($fieldId);

echo json_encode([
    'success' => true,
    'tags' => $tags
]);
?>

