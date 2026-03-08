<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['reset_email'])) {
    header('Location: forgot_password.php');
    exit;
}

$error = '';
$success = '';
$email = $_SESSION['reset_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($password)) {
        $error = 'Password is required';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE email = ?');
        if ($stmt->execute([$hashedPassword, $email])) {
            unset($_SESSION['reset_email']);
            $success = 'Password reset successful! You can now log in.';
        } else {
            $error = 'Failed to reset password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - AllChat</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .success-msg {
            color: #23a55a;
            background: rgba(35, 165, 90, 0.1);
            padding: 10px;
            border-radius: 4px;
            font-size: 14px;
            margin-bottom: 16px;
            border: 1px solid rgba(35, 165, 90, 0.2);
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <h1 class="auth-title">Reset Password</h1>
        <div class="auth-subtitle">Resetting password for: <br><strong><?php echo htmlspecialchars($email); ?></strong></div>
        
        <?php if ($error): ?>
            <div class="error-msg"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-msg"><?php echo $success; ?></div>
            <a href="login.php" class="btn-primary" style="display: block; text-decoration: none; text-align: center;">Go to Login</a>
        <?php else: ?>
            <form method="POST">
                <div class="form-group">
                    <label class="label">New Password</label>
                    <div class="input-wrapper">
                        <input type="password" name="password" id="password" class="input" required autofocus>
                        <button type="button" class="password-toggle" id="toggle-password">
                            <svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="label">Confirm Password</label>
                    <input type="password" name="confirm_password" class="input" required>
                </div>
                <button type="submit" class="btn-primary">Reset Password</button>
            </form>
        <?php endif; ?>
    </div>

    <script>
        const togglePassword = document.getElementById('toggle-password');
        const passwordInput = document.getElementById('password');

        if (togglePassword) {
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.classList.toggle('active');
            });
        }
    </script>
</body>
</html>
