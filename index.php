<?php
session_start();
require_once 'config/db.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Mock categories and channels for initial layout
$servers = $pdo->query("SELECT s.* FROM servers s JOIN server_members sm ON s.id = sm.server_id WHERE sm.user_id = $user_id")->fetchAll();
$current_server_id = isset($_GET['server_id']) ? (int)$_GET['server_id'] : null;

// Stay on Home (Friends) if no server_id is provided

$channels = [];
$current_channel_id = isset($_GET['channel_id']) ? (int)$_GET['channel_id'] : null;

if ($current_server_id) {
    $stmt = $pdo->prepare("SELECT * FROM channels WHERE server_id = ?");
    $stmt->execute([$current_server_id]);
    $channels = $stmt->fetchAll();
    
    if (!$current_channel_id && !empty($channels)) {
        $current_channel_id = $channels[0]['id'];
    }
}

// Get server name
$current_server_name = "Select a Server";
if ($current_server_id) {
    $stmt = $pdo->prepare("SELECT name FROM servers WHERE id = ?");
    $stmt->execute([$current_server_id]);
    $current_server_name = $stmt->fetchColumn();
}

// Fetch friends and pending requests for Home view
$friends = [];
$pending_requests = [];
if (!$current_server_id) {
    // Accepted friends
    $stmt = $pdo->prepare("
        SELECT f.id as friendship_id, u.id, u.username, u.status, u.avatar 
        FROM friends f 
        JOIN users u ON (f.user_id = u.id OR f.friend_id = u.id)
        WHERE (f.user_id = ? OR f.friend_id = ?) 
        AND f.status = 'accepted' 
        AND u.id != ?
    ");
    $stmt->execute([$user_id, $user_id, $user_id]);
    $friends = $stmt->fetchAll();

    // Pending requests
    $stmt = $pdo->prepare("
        SELECT f.id, u.username, u.avatar 
        FROM friends f 
        JOIN users u ON f.user_id = u.id 
        WHERE f.friend_id = ? AND f.status = 'pending'
    ");
    $stmt->execute([$user_id]);
    $pending_requests = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AllChat</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://kit.fontawesome.com/your-code.js" crossorigin="anonymous"></script> <!-- Placeholder for icons -->
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Servers -->
        <div class="sidebar-servers">
            <a href="index.php" class="server-icon <?php echo !$current_server_id ? 'active' : ''; ?>" title="Home">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
            </a>
            <div class="separator"></div>
            
            <?php foreach ($servers as $server): ?>
                <a href="?server_id=<?php echo $server['id']; ?>" class="server-icon <?php echo $current_server_id == $server['id'] ? 'active' : ''; ?>" title="<?php echo htmlspecialchars($server['name']); ?>">
                    <img src="assets/img/<?php echo $server['icon']; ?>" alt="<?php echo substr($server['name'], 0, 1); ?>">
                </a>
            <?php endforeach; ?>

            <div class="server-icon" id="add-server-btn" title="Add a Server" style="background-color: var(--bg-chat); color: #23a55a;">
                <span style="font-size: 24px;">+</span>
            </div>
        </div>

        <!-- Sidebar Channels -->
        <div class="sidebar-channels">
            <div class="channel-header">
                <span><?php echo htmlspecialchars($current_server_name); ?></span>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
            </div>
            
            <div class="channel-list">
                <?php if ($current_server_id): ?>
                    <?php foreach ($channels as $channel): ?>
                        <a href="?server_id=<?php echo $current_server_id; ?>&channel_id=<?php echo $channel['id']; ?>" class="channel-item <?php echo $current_channel_id == $channel['id'] ? 'active' : ''; ?>">
                            <span style="color: var(--text-muted)">#</span>
                            <span><?php echo htmlspecialchars($channel['name']); ?></span>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="home-search-container">
                        <button class="home-search-btn" onclick="alert('Search feature coming soon!')">Find or start a conversation</button>
                    </div>
                    
                    <a href="index.php" class="channel-item active">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                        <span>Friends</span>
                    </a>


                    <div class="user-category" style="margin-top: 18px; display: flex; justify-content: space-between; align-items: center; padding-right: 8px;">
                        <span>Direct Messages</span>
                        <span style="font-size: 16px; cursor: pointer;">+</span>
                    </div>
                    <div id="dm-list">
                        <?php foreach ($friends as $friend): ?>
                            <a href="#" class="channel-item">
                                <div class="user-avatar-small" style="width: 32px; height: 32px; background-image: url('assets/img/<?php echo $friend['avatar'] ?: 'default_avatar.png'; ?>'); background-size: cover;">
                                    <div class="status-indicator" style="background-color: <?php echo $friend['status'] === 'online' ? '#23a55a' : '#949ba4'; ?>"></div>
                                    <?php if (!$friend['avatar'] || $friend['avatar'] === 'default_avatar.png'): ?>
                                        <span style="display:flex; justify-content:center; align-items:center; height:100%; color:white; font-size: 12px; font-weight:bold;"><?php echo strtoupper(substr($friend['username'], 0, 1)); ?></span>
                                    <?php endif; ?>
                                </div>
                                <span style="margin-left: 8px;"><?php echo htmlspecialchars($friend['username']); ?></span>
                            </a>
                        <?php endforeach; ?>
                        <?php if (empty($friends)): ?>
                            <div style="padding: 8px 16px; color: var(--text-muted); font-size: 12px;">No friends yet.</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- User Footer -->
            <div class="user-footer">
                <div class="footer-profile">
                    <div class="user-avatar-small">
                        <div class="status-indicator"></div>
                    </div>
                    <div class="footer-info">
                        <div class="footer-username"><?php echo htmlspecialchars($username); ?></div>
                        <div class="footer-status">Online</div>
                    </div>
                </div>
                <div style="display: flex; gap: 4px; align-items: center;">
                    <div class="action-btn" style="background: transparent; width: 32px; height: 32px;" title="User Settings">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    </div>
                    <a href="auth/logout.php" class="btn-logout" title="Log Out" style="margin-left: 4px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    </a>
                </div>
            </div>
        </div>

        <!-- Chat Area -->
        <div class="chat-main">
            <div class="chat-header" id="chat-header-area" style="justify-content: space-between;">
                <div style="display: flex; align-items: center;" id="header-left">
                    <?php if ($current_channel_id): ?>
                        <span style="color: var(--text-muted); margin-right: 8px;">#</span>
                        <span>General</span>
                    <?php elseif (!$current_server_id): ?>
                        <div class="home-header-tabs">
                            <div style="display: flex; align-items: center; gap: 8px; padding-right: 8px; border-right: 1px solid rgba(255,255,255,0.1); margin-right: 8px;">
                                <svg style="color: var(--text-muted);" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                <span style="font-weight: bold; color: white;">Friends</span>
                            </div>
                            <div class="tab-item active" data-tab="online" onclick="switchTab('online')">Online</div>
                            <div class="tab-item" data-tab="all" onclick="switchTab('all')">All</div>
                            <div class="tab-item" data-tab="pending" onclick="switchTab('pending')">Pending</div>
                            <div class="tab-item" data-tab="blocked" onclick="switchTab('blocked')">Blocked</div>
                            <div class="tab-item btn-add" onclick="showAddFriendModal()">Add Friend</div>
                        </div>
                    <?php endif; ?>
                </div>

                <div id="header-right">
                    <?php if ($current_server_id): ?>
                        <?php 
                            $stmt = $pdo->prepare("SELECT invite_code FROM servers WHERE id = ?");
                            $stmt->execute([$current_server_id]);
                            $code = $stmt->fetchColumn();
                        ?>
                        <div style="font-size: 12px; color: var(--text-muted); background: rgba(0,0,0,0.2); padding: 4px 8px; border-radius: 4px;">
                            Invite: <span style="color: var(--accent); font-weight: bold;"><?php echo $code; ?></span>
                        </div>
                    <?php elseif (!$current_server_id): ?>
                        <div style="display: flex; gap: 16px; color: var(--text-muted);">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="messages-container" id="messages-list" style="<?php echo !$current_server_id ? 'padding: 0; flex-direction: row;' : ''; ?>">
                <?php if (!$current_server_id): ?>
                    <div id="friends-area" style="flex: 1; padding: 16px;">
                        <div class="home-search-container" style="padding: 0 0 16px 0; height: auto; box-shadow: none;">
                            <input type="text" class="home-search-btn" style="background-color: var(--home-search-bg); width: 100%; border-radius: 4px; padding: 8px 12px;" placeholder="Search">
                        </div>
                        <?php $online_friends = array_filter($friends, function($f) { return $f['status'] === 'online'; }); ?>
                        <div class="user-category" style="margin-top: 8px;">Online — <?php echo count($online_friends); ?></div>
                        <div id="friends-list-content">
                            <?php foreach ($friends as $friend): ?>
                                <div class="friend-item" style="border-top: 1px solid rgba(255,255,255,0.05);">
                                    <div class="friend-info">
                                        <div class="user-avatar-small" style="background-image: url('assets/img/<?php echo $friend['avatar'] ?: 'default_avatar.png'; ?>'); background-size: cover;">
                                            <div class="status-indicator" style="background-color: <?php echo $friend['status'] === 'online' ? '#23a55a' : '#949ba4'; ?>"></div>
                                            <?php if (!$friend['avatar'] || $friend['avatar'] === 'default_avatar.png'): ?>
                                                <span style="display:flex; justify-content:center; align-items:center; height:100%; color:white; font-weight:bold;"><?php echo strtoupper(substr($friend['username'], 0, 1)); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="message-username"><?php echo htmlspecialchars($friend['username']); ?></div>
                                            <div class="card-status"><?php echo ucfirst($friend['status']); ?></div>
                                        </div>
                                    </div>
                                    <div class="friend-actions">
                                        <div class="action-btn" title="Message"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg></div>
                                        <div class="action-btn" title="More"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"></circle><circle cx="12" cy="5" r="1"></circle><circle cx="12" cy="19" r="1"></circle></svg></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($friends)): ?>
                                <div style="padding: 20px; text-align: center; color: var(--text-muted);">No friends yet. Add some to start chatting!</div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($pending_requests)): ?>
                            <div class="user-category" style="margin-top: 32px;">Pending Requests — <?php echo count($pending_requests); ?></div>
                            <div id="pending-friends-content">
                                <?php foreach ($pending_requests as $req): ?>
                                    <div class="friend-item" style="border-top: 1px solid rgba(255,255,255,0.05);">
                                        <div class="friend-info">
                                            <div class="user-avatar-small" style="background-image: url('assets/img/<?php echo $req['avatar'] ?: 'default_avatar.png'; ?>'); background-size: cover;">
                                                <?php if (!$req['avatar'] || $req['avatar'] === 'default_avatar.png'): ?>
                                                    <span style="display:flex; justify-content:center; align-items:center; height:100%; color:white; font-weight:bold;"><?php echo strtoupper(substr($req['username'], 0, 1)); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="message-username"><?php echo htmlspecialchars($req['username']); ?></div>
                                                <div class="card-status">Incoming Friend Request</div>
                                            </div>
                                        </div>
                                        <div class="friend-actions">
                                            <div class="action-btn accept" onclick="respondFriend(<?php echo $req['id']; ?>, 'accepted')" title="Accept"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
                                            <div class="action-btn reject" onclick="respondFriend(<?php echo $req['id']; ?>, 'rejected')" title="Reject"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="sidebar-active-now">
                        <div class="active-now-title">Active Now</div>
                        <?php 
                        $online_friends = array_filter($friends, function($f) { return $f['status'] === 'online'; });
                        foreach ($online_friends as $of): 
                        ?>
                            <div class="active-card">
                                <div class="card-header">
                                    <div class="card-avatar" style="background-image: url('assets/img/<?php echo $of['avatar'] ?: 'default_avatar.png'; ?>'); background-size: cover;">
                                        <?php if (!$of['avatar'] || $of['avatar'] === 'default_avatar.png'): ?>
                                            <span style="display:flex; justify-content:center; align-items:center; height:100%; color:white; font-size: 10px; font-weight:bold;"><?php echo strtoupper(substr($of['username'], 0, 1)); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-info">
                                        <span class="card-user"><?php echo htmlspecialchars($of['username']); ?></span>
                                        <span class="card-status">Online</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($online_friends)): ?>
                            <div style="background: rgba(0,0,0,0.2); border-radius: 8px; padding: 20px; text-align: center; color: var(--text-muted); font-size: 14px;">
                                <div style="font-weight: bold; color: white; margin-bottom: 4px;">It's quiet for now...</div>
                                When a friend starts an activity—like playing a game or hanging out on voice—it'll show up here!
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-muted);">
                        <h2 style="color: var(--header-primary); margin-bottom: 8px;">Welcome to #General</h2>
                        <p>This is the start of the #General channel.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="chat-input-area" id="chat-input-row" <?php echo !$current_server_id ? 'style="display:none;"' : ''; ?>>
                <!-- File preview area -->
                <div id="file-preview-area" style="display: none; padding: 8px 16px; background: var(--bg-channels); border-radius: 8px; margin: 0 16px 8px; align-items: center; gap: 12px; border: 1px solid rgba(255,255,255,0.1);">
                    <div id="file-preview-content" style="flex: 1; display: flex; align-items: center; gap: 10px;"></div>
                    <button type="button" onclick="clearFileAttachment()" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 20px; line-height: 1;">×</button>
                </div>

                <form id="send-message-form">
                    <div class="input-box" style="display: flex; align-items: center; gap: 8px; padding: 0 12px;">
                        <!-- Upload Button -->
                        <label for="file-upload-input" style="cursor: pointer; color: var(--text-muted); display: flex; align-items: center; flex-shrink: 0;" title="Attach file">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
                        </label>
                        <input type="file" id="file-upload-input" style="display: none;" accept="image/*,.pdf,.txt,.zip,.doc,.docx,.xls,.xlsx" onchange="handleFileSelect(event)">

                        <input type="text" id="message-input" class="message-input" placeholder="Message #General" autocomplete="off" style="flex: 1; background: none; border: none; outline: none; color: var(--text-normal);">
                    </div>
                </form>
            </div>
        </div>

        <!-- Sidebar Users -->
        <div class="sidebar-users" id="users-list">
            <div class="user-category">Online — 1</div>
            <div class="user-item">
                <div class="user-avatar-small">
                    <div class="status-indicator"></div>
                </div>
                <div class="user-name"><?php echo htmlspecialchars($username); ?></div>
            </div>
        </div>
    </div>

    <!-- Server Actions Modal (Create or Join) -->
    <div id="join-server-modal" class="modal">
        <div class="modal-content" id="modal-choices">
            <h2 style="color: var(--header-primary); text-align: center; margin-bottom: 24px;">Create a server</h2>
            <p style="color: var(--text-muted); text-align: center; margin-bottom: 24px;">Your server is where you and your friends hang out. Make yours and start talking.</p>
            
            <button class="btn-primary" onclick="showCreateForm()" style="width: 100%; margin-bottom: 12px; padding: 12px;">Create My Own</button>
            <div style="text-align: center; margin: 16px 0; color: var(--text-muted); font-size: 12px; font-weight: bold; text-transform: uppercase;">Have an invite already?</div>
            <button class="btn-secondary" onclick="showJoinForm()" style="width: 100%; background: #4e5058; color: white; padding: 12px; border-radius: 3px; text-decoration: none;">Join a Server</button>
            
            <div style="display:flex; justify-content: center; margin-top: 24px;">
                <button type="button" class="btn-secondary" onclick="closeServerModal()">Back</button>
            </div>
        </div>

        <!-- Create Form (Hidden by default) -->
        <div class="modal-content" id="create-server-form-container" style="display: none;">
            <h2 style="color: var(--header-primary); text-align: center; margin-bottom: 8px;">Customize your server</h2>
            <p style="color: var(--text-muted); text-align: center; margin-bottom: 24px;">Give your new server a personality with a name. You can always change it later.</p>
            
            <form id="create-server-form">
                <div class="form-group">
                    <label class="label">Server Name</label>
                    <input type="text" id="create-server-name" class="input" placeholder="<?php echo htmlspecialchars($username); ?>'s server" required>
                </div>
                <div style="display:flex; justify-content: space-between; align-items: center; margin-top: 24px; background: #2b2d31; margin-left: -24px; margin-right: -24px; margin-bottom: -24px; padding: 16px 24px;">
                    <button type="button" class="btn-secondary" onclick="backToChoices()">Back</button>
                    <button type="submit" class="btn-primary" style="width: auto; padding: 10px 24px;">Create</button>
                </div>
            </form>
        </div>

        <!-- Join Form (Hidden by default) -->
        <div class="modal-content" id="join-server-form-container" style="display: none;">
            <h2 style="color: var(--header-primary); text-align: center; margin-bottom: 8px;">Join a Server</h2>
            <p style="color: var(--text-muted); text-align: center; margin-bottom: 24px;">Enter an invite below to join an existing server.</p>
            
            <form id="join-server-form">
                <div class="form-group">
                    <label class="label">Invite Code</label>
                    <input type="text" id="join-invite-code" class="input" placeholder="e.g. WELCOME" required>
                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 8px;">Invites should look like <span style="font-weight: bold; color: var(--text-normal);">WELCOME</span> or <span style="font-weight: bold; color: var(--text-normal);">H6J2K8L1</span></div>
                </div>
                <div style="display:flex; justify-content: space-between; align-items: center; margin-top: 24px; background: #2b2d31; margin-left: -24px; margin-right: -24px; margin-bottom: -24px; padding: 16px 24px;">
                    <button type="button" class="btn-secondary" onclick="backToChoices()">Back</button>
                    <button type="submit" class="btn-primary" style="width: auto; padding: 10px 24px;">Join Server</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Friend Modal -->
    <div id="add-friend-modal" class="modal">
        <div class="modal-content">
            <h2 style="color: var(--header-primary); margin-bottom: 8px;">Add Friend</h2>
            <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 20px;">You can add friends with their AllChat username.</p>
            
            <form id="add-friend-form">
                <div class="form-group">
                    <label class="label">Username</label>
                    <input type="text" id="add-friend-username" class="input" placeholder="Enter username" required>
                </div>
                <div style="display:flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                    <button type="button" class="btn-secondary" id="close-add-friend-modal">Cancel</button>
                    <button type="submit" class="btn-primary" style="width: auto; padding: 10px 24px;">Send Friend Request</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>
