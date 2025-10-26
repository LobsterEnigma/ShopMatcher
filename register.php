<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/User.php';

// 如果已登录，重定向到首页
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_POST) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $captcha = $_POST['captcha'] ?? '';
    
    if (empty($username) || empty($email) || empty($password) || empty($confirmPassword) || empty($captcha)) {
        $error = '请填写所有字段';
    } elseif ($password !== $confirmPassword) {
        $error = '两次输入的密码不一致';
    } elseif (strlen($password) < 6) {
        $error = '密码长度至少6位';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '邮箱格式不正确';
    } elseif ($captcha !== ($_SESSION['captcha'] ?? '')) {
        $error = '验证码错误';
    } else {
        $user = new User();
        $result = $user->register($username, $email, $password, $captcha);
        
        if ($result['success']) {
            $success = '注册成功！请登录';
            // 清除POST数据
            $_POST = [];
        } else {
            $error = $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户注册 - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .register-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .register-body {
            padding: 40px;
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: bold;
        }
        .captcha-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .captcha-code {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 5px;
            padding: 10px;
            font-family: 'Courier New', monospace;
            font-size: 18px;
            font-weight: bold;
            color: #333;
            letter-spacing: 3px;
            user-select: none;
            min-width: 120px;
            text-align: center;
        }
        .password-strength {
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            margin-top: 5px;
            overflow: hidden;
        }
        .password-strength-bar {
            height: 100%;
            transition: all 0.3s;
            border-radius: 2px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="register-card">
                    <div class="register-header">
                        <h3><i class="fas fa-gamepad"></i> <?php echo SITE_NAME; ?></h3>
                        <p class="mb-0">创建新账号</p>
                    </div>
                    
                    <div class="register-body">
                        <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                            <div class="mt-2">
                                <a href="login.php" class="btn btn-success btn-sm">立即登录</a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" id="registerForm">
                            <div class="mb-3">
                                <label for="username" class="form-label">
                                    <i class="fas fa-user"></i> 用户名
                                </label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                                <div class="form-text">用户名长度3-20个字符，只能包含字母、数字和下划线</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope"></i> 邮箱
                                </label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock"></i> 密码
                                </label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <div class="password-strength">
                                    <div class="password-strength-bar" id="strengthBar"></div>
                                </div>
                                <div class="form-text">密码长度至少6位</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">
                                    <i class="fas fa-lock"></i> 确认密码
                                </label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-shield-alt"></i> 验证码
                                </label>
                                <div class="captcha-container">
                                    <div class="captcha-code" id="captcha-display">
                                        <?php echo generateCaptcha(); ?>
                                    </div>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="refreshCaptcha()">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                                <input type="text" class="form-control mt-2" name="captcha" placeholder="请输入验证码" required>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="agree" required>
                                <label class="form-check-label" for="agree">
                                    我已阅读并同意 <a href="#" class="text-decoration-none">用户协议</a> 和 <a href="#" class="text-decoration-none">隐私政策</a>
                                </label>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-register">
                                    <i class="fas fa-user-plus"></i> 注册
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-4">
                            <p class="mb-0">已有账号？ <a href="login.php" class="text-decoration-none">立即登录</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function refreshCaptcha() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let result = '';
            for (let i = 0; i < 4; i++) {
                result += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById('captcha-display').textContent = result;
        }
        
        // 密码强度检测
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strengthBar');
            let strength = 0;
            
            if (password.length >= 6) strength += 20;
            if (password.length >= 8) strength += 20;
            if (/[a-z]/.test(password)) strength += 20;
            if (/[A-Z]/.test(password)) strength += 20;
            if (/[0-9]/.test(password)) strength += 10;
            if (/[^A-Za-z0-9]/.test(password)) strength += 10;
            
            strengthBar.style.width = strength + '%';
            
            if (strength < 40) {
                strengthBar.style.backgroundColor = '#dc3545';
            } else if (strength < 70) {
                strengthBar.style.backgroundColor = '#ffc107';
            } else {
                strengthBar.style.backgroundColor = '#28a745';
            }
        });
        
        // 确认密码验证
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.setCustomValidity('两次输入的密码不一致');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>

<?php
function generateCaptcha() {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $captcha = '';
    for ($i = 0; $i < 4; $i++) {
        $captcha .= $chars[rand(0, strlen($chars) - 1)];
    }
    $_SESSION['captcha'] = $captcha;
    return $captcha;
}
?>
