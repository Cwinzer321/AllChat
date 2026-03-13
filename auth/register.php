<?php
session_start();
require_once '../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($username) || empty($email) || empty($password)) {
        $error = 'All fields are required';
    } else {
        // Check if username or email exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = 'Username or Email already exists';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
            if ($stmt->execute([$username, $email, $hashedPassword])) {
                $new_user_id = $pdo->lastInsertId();
                $_SESSION['user_id'] = $new_user_id;
                $_SESSION['username'] = $username;

                // User registered successfully without joining any servers

                header('Location: ../index.php');
                exit;
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create an account - AllChat</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <h1 class="auth-title">Create an account</h1>
        <div class="auth-subtitle">Join the conversation today!</div>
        
        <?php if ($error): ?>
            <div class="error-msg"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="label">Email</label>
                <input type="email" name="email" class="input" required autofocus>
            </div>
            <div class="form-group">
                <label class="label">Username</label>
                <input type="text" name="username" class="input" required>
            </div>
            <div class="form-group">
                <label class="label">Password</label>
                <div class="input-wrapper">
                    <input type="password" name="password" id="password" class="input" required>
                    <button type="button" class="password-toggle" id="toggle-password">
                        <svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn-primary">Continue</button>
        </form>
        
        <div class="auth-footer">
            Already have an account? <a href="login.php" class="auth-link">Login</a>
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
