<?php
session_start();
require_once '../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = 'Email and password are required';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['avatar'] = $user['avatar'];
            
            // Update status to online
            $stmt = $pdo->prepare('UPDATE users SET status = "online" WHERE id = ?');
            $stmt->execute([$user['id']]);

            header('Location: ../index.php');
            exit;
        } else {
            $error = 'Invalid email or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AllChat</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <div class="brand-wrapper">
            <div class="brand-logo-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
            </div>
            <div class="brand-text-container">
                <span class="brand-text">AllChat</span>
                <span class="brand-tagline">Nexus Protocol</span>
            </div>
        </div>
        <h1 class="auth-title">Welcome back!</h1>
        <div class="auth-subtitle">We're so excited to see you again!</div>
        
        <?php if ($error): ?>
            <div class="error-msg"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="label">Email</label>
                <input type="email" name="email" class="input" required autofocus>
            </div>
            <div class="form-group">
                <label class="label">Password</label>
                <div class="input-wrapper">
                    <input type="password" name="password" id="password" class="input" required>
                    <button type="button" class="password-toggle" id="toggle-password">
                        <svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </button>
                </div>
                <a href="forgot_password.php" class="auth-link" style="font-size: 12px; margin-top: 8px; display: inline-block;">Forgot your password?</a>
            </div>
            <button type="submit" class="btn-primary">Log In</button>
        </form>
        
        <div class="auth-footer">
            Need an account? <a href="register.php" class="auth-link">Register</a>
        </div>
    </div>

    <script>
        const togglePassword = document.getElementById('toggle-password');
        const password = document.getElementById('password');

        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('active');
        });
    </script>
</body>
</html>
