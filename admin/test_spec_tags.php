<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Product.php';

header('Content-Type: text/html; charset=utf-8');

$db = new Database();
$pdo = $db->getConnection();

echo "<h2>技术规格标签测试</h2>";

// 1. 检查字段
echo "<h3>1. 检查技术规格字段</h3>";
$stmt = $pdo->query("SELECT * FROM specification_fields ORDER BY display_order");
$fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($fields);
echo "</pre>";

// 2. 检查标签
echo "<h3>2. 检查技术规格标签</h3>";
$stmt = $pdo->query("SELECT st.*, sf.name as field_name FROM specification_tags st 
                     INNER JOIN specification_fields sf ON st.field_id = sf.id 
                     ORDER BY sf.display_order, st.tag_name");
$tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($tags);
echo "</pre>";

// 3. 检查产品标签关联
echo "<h3>3. 检查产品标签关联</h3>";
$stmt = $pdo->query("SELECT pst.*, sf.name as field_name, 
                     COALESCE(st.tag_name, pst.custom_value) as tag_value
                     FROM product_specification_tags pst
                     INNER JOIN specification_fields sf ON pst.field_id = sf.id
                     LEFT JOIN specification_tags st ON pst.tag_id = st.id
                     ORDER BY pst.product_id, sf.display_order");
$productTags = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($productTags);
echo "</pre>";

// 4. 检查产品的specification_mode
echo "<h3>4. 检查产品的specification_mode</h3>";
$stmt = $pdo->query("SELECT id, name, category_id, specification_mode FROM products WHERE specification_mode = 'tagged' LIMIT 10");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($products);
echo "</pre>";

// 5. 测试保存功能
if (isset($_GET['test_save']) && isset($_GET['product_id'])) {
    $productId = (int)$_GET['product_id'];
    $productObj = new Product();
    
    // 测试数据
    $testData = [
        1 => ['test1', 'test2'], // 字段ID 1 的标签
        2 => ['PC', 'Switch']    // 字段ID 2 的标签
    ];
    
    echo "<h3>5. 测试保存功能</h3>";
    echo "<p>产品ID: $productId</p>";
    echo "<p>测试数据: " . json_encode($testData) . "</p>";
    
    $result = $productObj->setProductSpecificationTags($productId, $testData);
    echo "<p>保存结果: " . ($result ? '成功' : '失败') . "</p>";
    
    // 重新查询
    $savedTags = $productObj->getProductSpecificationTags($productId);
    echo "<p>保存后的标签:</p>";
    echo "<pre>";
    print_r($savedTags);
    echo "</pre>";
}

echo "<hr>";
echo "<p><a href='?test_save=1&product_id=48'>测试保存到产品ID 48</a></p>";
?>

