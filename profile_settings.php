<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_username = $_POST['username'];
    $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
    if ($stmt->execute([$new_username, $user_id])) {
        $_SESSION['username'] = $new_username;
        $success = "Profile updated successfully!";
        $username = $new_username;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Settings - AllChat</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .settings-container {
            display: flex;
            height: 100vh;
            background: var(--background-primary);
        }
        .settings-sidebar {
            width: 280px;
            background: var(--background-secondary);
            padding: 60px 20px;
            border-right: 1px solid var(--background-accent);
        }
        .settings-content {
            flex: 1;
            padding: 60px 40px;
            max-width: 800px;
        }
        .settings-nav-item {
            padding: 10px 16px;
            border-radius: 4px;
            color: var(--text-muted);
            cursor: pointer;
            font-weight: 600;
            margin-bottom: 2px;
        }
        .settings-nav-item.active {
            background: var(--background-modifier-selected);
            color: var(--header-primary);
        }
        .settings-section {
            margin-bottom: 40px;
        }
        .settings-title {
            font-size: 20px;
            font-weight: 800;
            color: var(--header-primary);
            margin-bottom: 20px;
        }
    </style>
</head>
<body style="overflow: hidden;">
    <div class="settings-container">
        <div class="settings-sidebar">
            <div class="user-category">User Settings</div>
            <div class="settings-nav-item active">My Account</div>
            <div class="settings-nav-item">Profiles</div>
            <div class="settings-nav-item">Privacy & Safety</div>
            <div style="margin-top: 20px;" class="user-category">App Settings</div>
            <div class="settings-nav-item">Appearance</div>
            <div class="settings-nav-item">Notifications</div>
            
            <div style="margin-top: 40px; border-top: 1px solid var(--background-accent); padding-top: 20px;">
                <a href="index.php" class="settings-nav-item" style="display: flex; align-items: center; gap: 8px; text-decoration: none;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                    Close Settings
                </a>
            </div>
        </div>

        <div class="settings-content">
            <div class="settings-section">
                <div class="settings-title">My Account</div>
                
                <?php if (isset($success)): ?>
                    <div style="background: rgba(16, 185, 129, 0.1); color: var(--accent-positive); padding: 12px; border-radius: 8px; margin-bottom: 20px; border: 1px solid var(--accent-positive);">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" style="background: var(--background-tertiary); padding: 24px; border-radius: var(--radius-lg);">
                    <div class="form-group">
                        <label class="label">Username</label>
                        <div style="display: flex; gap: 12px;">
                            <input type="text" name="username" class="input" value="<?php echo htmlspecialchars($username); ?>" required>
                            <button type="submit" class="btn-primary" style="padding: 10px 20px;">Edit</button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="label">Email</label>
                        <div style="color: var(--text-normal); font-weight: 500;">********@email.com</div>
                    </div>
                </form>
            </div>

            <div class="settings-section">
                <div class="settings-title">Password and Authentication</div>
                <button class="btn-primary" style="background: var(--accent);">Change Password</button>
            </div>
        </div>
    </div>
</body>
</html>
