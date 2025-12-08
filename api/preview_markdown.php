<?php
require_once '../config/config.php';
require_once '../classes/Markdown.php';

header('Content-Type: text/html; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
    $content = $_POST['content'] ?? '';
    echo Markdown::toHtml($content);
} else {
    echo '';
}
?>

