<?php
require_once '../config/config.php';

// 生成新的验证码
function generateCaptcha() {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $captcha = '';
    for ($i = 0; $i < 4; $i++) {
        $captcha .= $chars[rand(0, strlen($chars) - 1)];
    }
    $_SESSION['captcha'] = $captcha;
    return $captcha;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'captcha' => generateCaptcha()]);
?>

