<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch all public servers (mocking public for now)
$stmt = $pdo->prepare("SELECT * FROM servers WHERE id NOT IN (SELECT server_id FROM server_members WHERE user_id = ?)");
$stmt->execute([$user_id]);
$public_servers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discover Servers - AllChat</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .discover-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .hero-section {
            background: var(--accent-gradient);
            padding: 60px 20px;
            border-radius: var(--radius-xl);
            text-align: center;
            color: white;
            margin-bottom: 40px;
            box-shadow: var(--shadow-lg);
        }
        .server-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        .discover-card {
            background: var(--background-primary);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            transition: transform 0.3s ease;
            cursor: pointer;
            border: 1px solid var(--background-accent);
        }
        .discover-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        .card-banner {
            height: 120px;
            background: var(--background-tertiary);
            position: relative;
        }
        .card-icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            background: var(--background-primary);
            position: absolute;
            bottom: -32px;
            left: 20px;
            border: 4px solid var(--background-primary);
            box-shadow: var(--shadow-sm);
        }
        .card-body {
            padding: 48px 20px 20px;
        }
        .card-title {
            font-weight: 800;
            font-size: 18px;
            margin-bottom: 8px;
            color: var(--header-primary);
        }
        .card-desc {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 20px;
            line-height: 1.5;
        }
    </style>
</head>
<body style="overflow: auto; background: var(--background-secondary);">
    <div class="discover-container">
        <a href="index.php" style="display: inline-flex; align-items: center; gap: 8px; color: var(--text-muted); text-decoration: none; margin-bottom: 20px; font-weight: 600;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Back to Home
        </a>
        
        <div class="hero-section">
            <h1 style="font-size: 32px; font-weight: 900; margin-bottom: 12px; letter-spacing: -0.03em;">Find your community on AllChat</h1>
            <p style="font-size: 18px; opacity: 0.9;">From gaming, to music, to learning, there's a place for you.</p>
        </div>

        <div class="server-grid">
            <?php foreach ($public_servers as $server): ?>
                <div class="discover-card" onclick="joinServer('<?php echo $server['invite_code']; ?>')">
                    <div class="card-banner" style="background: <?php echo $server['banner_color'] ?: 'var(--accent-gradient)'; ?>">
                        <div class="card-icon" style="background-image: url('assets/img/<?php echo $server['icon']; ?>'); background-size: cover;"></div>
                    </div>
                    <div class="card-body">
                        <div class="card-title"><?php echo htmlspecialchars($server['name']); ?></div>
                        <p class="card-desc">Welcome to the <?php echo htmlspecialchars($server['name']); ?> community! Join us for cheerful conversations.</p>
                        <button class="btn-primary" style="width: 100%; padding: 10px;">Join Server</button>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($public_servers)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 60px; color: var(--text-muted);">
                    <div style="font-size: 48px; margin-bottom: 20px;">🕵️‍♂️</div>
                    <h3>No new servers found to discover.</h3>
                    <p>Why not create your own group?</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        async function joinServer(code) {
            const formData = new FormData();
            formData.append('invite_code', code);
            try {
                const response = await fetch('api/join_server.php', { method: 'POST', body: formData });
                const data = await response.json();
                if (data.success) {
                    window.location.href = `index.php?server_id=${data.group_id}`;
                } else {
                    alert(data.error);
                }
            } catch (e) { console.error(e); }
        }
    </script>
</body>
</html>
