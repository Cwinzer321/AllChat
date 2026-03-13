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

// Get group details
$current_server_name = "Select a Group";
$is_owner = false;
if ($current_server_id) {
    $stmt = $pdo->prepare("SELECT name, owner_id FROM servers WHERE id = ?");
    $stmt->execute([$current_server_id]);
    $server_data = $stmt->fetch();
    if ($server_data) {
        $current_server_name = $server_data['name'];
        $is_owner = ($server_data['owner_id'] == $user_id);
    }
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
    <link rel="stylesheet" href="assets/css/main.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <div class="app-container">
        <!-- Sidebar Servers -->
        <div class="sidebar-servers">
            <a href="index.php" class="server-icon <?php echo !$current_server_id ? 'active' : ''; ?>" title="Home">
                <i class="fa-solid fa-house"></i>
            </a>
            <div class="separator"></div>
            
            <?php foreach ($servers as $server): ?>
                <a href="?server_id=<?php echo $server['id']; ?>" class="server-icon <?php echo $current_server_id == $server['id'] ? 'active' : ''; ?>" title="<?php echo htmlspecialchars($server['name']); ?>">
                    <img src="assets/img/<?php echo $server['icon']; ?>" alt="<?php echo substr($server['name'], 0, 1); ?>">
                </a>
            <?php endforeach; ?>

            <div class="server-icon" id="add-group-btn" title="Create Group" style="background-color: var(--bg-chat); color: #23a55a;">
                <i class="fa-solid fa-plus" style="font-size: 20px;"></i>
            </div>

            <a href="discover.php" class="server-icon" title="Discover" style="background-color: var(--bg-chat); color: #23a55a;">
                <i class="fa-solid fa-compass"></i>
            </a>
        </div>

        <!-- Sidebar Channels -->
        <div class="sidebar-channels">
            <!-- <div class="brand-header">
                <div class="brand-wrapper">
                    <div class="brand-logo-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                    </div>
                    <div class="brand-text-container">
                        <span class="brand-text">AllChat</span>
                        <span class="brand-tagline">Nexus Protocol</span>
                    </div>
                </div>
            </div> -->

            <div class="sidebar-channels-header" id="group-switcher-header">
                <span><?php echo htmlspecialchars($current_server_name); ?></span>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
                
                <div class="group-switcher-dropdown" id="group-switcher-dropdown">
                    <a href="index.php" class="switcher-item <?php echo !$current_server_id ? 'active' : ''; ?>">
                        <div class="switcher-icon">
                            <i class="fa-solid fa-house" style="font-size: 12px;"></i>
                        </div>
                        <span>Home Dashboard</span>
                    </a>
                    
                    <div class="switcher-separator"></div>

                    <a href="#" class="switcher-item" id="switcher-add-group" style="color: var(--accent-positive);">
                        <div class="switcher-icon" style="background: rgba(16, 185, 129, 0.1);">
                            <i class="fa-solid fa-plus" style="font-size: 12px;"></i>
                        </div>
                        <span>Create a Group</span>
                    </a>
                    <a href="discover.php" class="switcher-item">
                        <div class="switcher-icon">
                            <i class="fa-solid fa-compass" style="font-size: 12px;"></i>
                        </div>
                        <span>Discover Groups</span>
                    </a>
                </div>
            </div>
            
            <div class="channel-list">
                <?php if ($current_server_id): ?>
                    <!-- Channels hidden to simplify UI to a direct Group chat -->
                    <div style="padding: 16px; color: var(--text-muted); font-size: 13px;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px; color: var(--text-normal); font-weight: 600;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                            <span>Group Membership</span>
                        </div>
                        <p style="line-height: 1.4;">You are currently in <strong><?php echo htmlspecialchars($current_server_name); ?></strong>. All messages sent here are seen by group members.</p>
                    </div>
                <?php else: ?>
                    <a href="#" class="channel-item active" id="nav-friends" onclick="switchHomeView('friends')">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                        <span>Friends</span>
                    </a>
                    <div class="channel-item" id="nav-message-requests" onclick="switchHomeView('message-requests')">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                        <span>Message Requests</span>
                    </div>
                    <div class="user-category" style="margin-top: 18px; display: flex; justify-content: space-between; align-items: center; padding-right: 8px;">
                        <span>Direct Messages</span>
                        <span id="open-group-dm-btn" onclick="showGroupDMModal()" style="font-size: 16px; cursor: pointer; padding: 4px;" title="Create Group Chat">+</span>
                    </div>
                    <div id="dm-list">
                        <?php foreach ($friends as $friend): ?>
                            <a href="#" class="channel-item" onclick="openDM(<?php echo $friend['id']; ?>, '<?php echo htmlspecialchars(addslashes($friend['username'])); ?>', '<?php echo $friend['avatar'] ?: 'default_avatar.png'; ?>'); return false;">
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
                    <div class="user-avatar-small" style="background-image: url('assets/img/default_avatar.png'); background-size: cover; display: flex; align-items: center; justify-content: center;">
                        <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.8; padding: 20%;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        <div class="status-indicator"></div>
                    </div>
                    <div class="footer-info">
                        <div class="footer-username"><?php echo htmlspecialchars($username); ?></div>
                        <div class="footer-status">Online</div>
                    </div>
                </div>
                <div style="display: flex; gap: 4px; align-items: center;">
                    <a href="profile_settings.php" class="action-btn" style="background: transparent; width: 32px; height: 32px; color: inherit;" title="User Settings">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    </a>
                    <a href="auth/logout.php" class="btn-logout" title="Log Out" style="margin-left: 4px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    </a>
                </div>
            </div>
        </div>

        <!-- Chat Area -->
        <div class="chat-main">
            <div class="chat-inner-card">
                <div class="chat-header" id="chat-header-area">
                    <div class="header-left" id="header-left">
                        <?php if ($current_channel_id): ?>
                            <span class="header-name"><?php echo htmlspecialchars($current_server_name); ?></span>
                        <?php elseif (!$current_server_id): ?>
                            <div class="home-header-tabs">
                                <div class="home-icon-group">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                    <span class="header-title">Friends</span>
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
                            <div style="font-size: 12px; color: var(--text-muted); background: var(--background-secondary); padding: 4px 12px; border-radius: var(--radius-pill); border: 1px solid var(--background-accent);">
                                Invite: <span style="color: var(--accent); font-weight: 800;"><?php echo $code; ?></span>
                            </div>
                            <?php if ($is_owner): ?>
                                <div class="action-btn delete-group-btn" title="Delete Group" onclick="confirmDeleteGroup(<?php echo $current_server_id; ?>)" style="color: var(--accent-danger); background: rgba(244, 63, 94, 0.1); width: 32px; height: 32px; margin-left: 12px; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer;">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                </div>
                            <?php endif; ?>
                        <?php elseif (!$current_server_id): ?>
                            <!-- Redundant icons removed as per user request -->
                        <?php endif; ?>
                    </div>
                </div>

                <div class="messages-container" id="messages-list" style="<?php echo !$current_server_id ? 'flex-direction: row;' : 'padding: 24px;'; ?>">
                    <?php if (!$current_server_id): ?>
                        <div id="friends-area" style="flex: 1; display: flex; flex-direction: column;">
                            <div class="home-search-container">
                                <input type="text" class="home-search-btn" style="width: 100%; padding: 10px 16px; border-radius: 8px;" placeholder="Search for friends...">
                            </div>
                            <div id="friends-list-content" style="flex: 1; padding: 16px; overflow-y: auto;">
                                <?php $online_friends = array_filter($friends, function($f) { return $f['status'] === 'online'; }); ?>
                                <div class="user-category">Online — <?php echo count($online_friends); ?></div>
                                <?php foreach ($friends as $friend): ?>
                                    <div class="friend-item">
                                        <div class="friend-info">
                                            <div class="user-avatar-small" style="background-image: url('assets/img/<?php echo $friend['avatar'] ?: 'default_avatar.png'; ?>'); background-size: cover; border-radius: 12px;">
                                                <div class="status-indicator" style="background-color: <?php echo $friend['status'] === 'online' ? 'var(--accent-positive)' : 'var(--text-muted)'; ?>; border-color: var(--background-primary);"></div>
                                                <?php if (!$friend['avatar'] || $friend['avatar'] === 'default_avatar.png'): ?>
                                                    <span style="display:flex; justify-content:center; align-items:center; height:100%; color:white; font-weight:bold;"><?php echo strtoupper(substr($friend['username'], 0, 1)); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="message-username" style="color: var(--header-primary);"><?php echo htmlspecialchars($friend['username']); ?></div>
                                                <div class="card-status" style="color: var(--accent-positive);"><?php echo ucfirst($friend['status']); ?></div>
                                            </div>
                                        </div>
                                        <div class="friend-actions">
                                            <div class="action-btn" title="Message" onclick="openDM(<?php echo $friend['id']; ?>, '<?php echo htmlspecialchars(addslashes($friend['username'])); ?>', '<?php echo $friend['avatar'] ?: 'default_avatar.png'; ?>')"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg></div>
                                            <div class="action-btn" title="More"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"></circle><circle cx="12" cy="5" r="1"></circle><circle cx="12" cy="19" r="1"></circle></svg></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($friends)): ?>
                                    <div style="padding: 40px; text-align: center; color: var(--text-muted);">
                                        <div style="font-size: 48px; margin-bottom: 16px;">👋</div>
                                        <div style="font-weight: 800; color: var(--header-primary); margin-bottom: 8px;">No friends yet</div>
                                        Add some to start a cheerful conversation!
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($pending_requests)): ?>
                                <div class="user-category" style="margin-top: 32px;">Pending Requests — <?php echo count($pending_requests); ?></div>
                                <div id="pending-friends-content">
                                    <?php foreach ($pending_requests as $req): ?>
                                        <div class="friend-item" style="border-top: 1px solid var(--background-accent);">
                                            <div class="friend-info">
                                                <div class="user-avatar-small" style="background-image: url('assets/img/<?php echo $req['avatar'] ?: 'default_avatar.png'; ?>'); background-size: cover; border-radius: 12px;">
                                                    <?php if (!$req['avatar'] || $req['avatar'] === 'default_avatar.png'): ?>
                                                        <span style="display:flex; justify-content:center; align-items:center; height:100%; color:white; font-weight:bold;"><?php echo strtoupper(substr($req['username'], 0, 1)); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <div class="message-username" style="color: var(--header-primary);"><?php echo htmlspecialchars($req['username']); ?></div>
                                                    <div class="card-status" style="color: var(--accent);">Incoming Friend Request</div>
                                                </div>
                                            </div>
                                            <div class="friend-actions">
                                                <div class="action-btn accept" onclick="respondFriend(<?php echo $req['id']; ?>, 'accepted')" title="Accept" style="background: rgba(16, 185, 129, 0.1); color: var(--accent-positive);"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
                                                <div class="action-btn reject" onclick="respondFriend(<?php echo $req['id']; ?>, 'rejected')" title="Reject" style="background: rgba(244, 63, 94, 0.1); color: var(--accent-danger);"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Message Requests View -->
                        <div id="message-requests-area" style="flex: 1; padding: 24px; display: none;">
                            <h2 style="color: var(--header-primary); margin-bottom: 8px; font-weight: 800;">Message Requests</h2>
                            <p style="color: var(--text-muted); font-size: 14px;">Messages from people you don't know yet.</p>
                            <div style="background: var(--background-tertiary); border-radius: var(--radius-lg); padding: 60px 20px; text-align: center; color: var(--text-muted); margin-top: 24px; border: 2px dashed var(--background-accent);">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 20px; color: var(--background-accent);"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                                <div style="font-weight: 800; color: var(--header-primary); margin-bottom: 8px; font-size: 18px;">All caught up!</div>
                                <div style="font-size: 14px;">You don't have any pending message requests.</div>
                            </div>
                        </div>

                        <!-- Direct Message Chat Area -->
                        <div id="dm-chat-area" style="flex: 1; display: none; flex-direction: column; height: 100%;">
                            <!-- DM Header replaces the normal Home search bar/tabs -->
                            <div class="chat-header" style="border-bottom: 1px solid var(--background-accent); padding: 0 24px; height: 64px; min-height: 64px; display: flex; align-items: center; justify-content: space-between;">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div id="dm-header-avatar" class="user-avatar-small" style="width: 32px; height: 32px; background-size: cover; border-radius: 10px;"></div>
                                    <span id="dm-header-username" style="font-weight: 800; color: var(--header-primary); display:flex; align-items:center;"></span>
                                </div>
                            </div>

                            <!-- DM Messages List -->
                            <div class="messages-container" id="dm-messages-list" style="flex: 1; overflow-y: auto; padding: 24px; display: flex; flex-direction: column;">
                                <!-- Messages will be injected here via JS -->
                            </div>

                            <!-- DM Input -->
                            <div class="chat-input-area" style="padding: 24px; flex-shrink: 0; background: var(--background-primary);">
                                <div id="dm-file-preview-area" style="display: none; padding: 12px 16px; background: var(--background-tertiary); border-radius: var(--radius-md); margin-bottom: 12px; align-items: center; gap: 12px; border: 1px solid var(--background-accent);">
                                    <div id="dm-file-preview-content" style="flex: 1; display: flex; align-items: center; gap: 10px;"></div>
                                    <button type="button" onclick="clearDMFileAttachment()" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 24px; line-height: 1;">×</button>
                                </div>
                                <form id="dm-send-message-form">
                                    <div class="input-box" style="display: flex; align-items: center; gap: 12px; padding: 0 16px;">
                                        <label for="dm-file-upload-input" style="cursor: pointer; color: var(--text-muted); display: flex; align-items: center; flex-shrink: 0;" title="Attach file">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
                                        </label>
                                        <input type="file" id="dm-file-upload-input" style="display: none;" accept="image/*,.pdf,.txt,.zip,.doc,.docx,.xls,.xlsx" onchange="handleDMFileSelect(event)">
                                        <input type="text" id="dm-message-input" class="message-input" placeholder="Say something cheerful..." autocomplete="off">
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="sidebar-active-now">
                            <div class="active-now-title">Cheerful Activity</div>
                            <?php 
                            $online_friends = array_filter($friends, function($f) { return $f['status'] === 'online'; });
                            foreach ($online_friends as $of): 
                            ?>
                                <div class="active-card">
                                    <div class="card-header">
                                        <div class="card-avatar" style="background-image: url('assets/img/<?php echo $of['avatar'] ?: 'default_avatar.png'; ?>'); background-size: cover; border-radius: 12px;">
                                            <?php if (!$of['avatar'] || $of['avatar'] === 'default_avatar.png'): ?>
                                                <span style="display:flex; justify-content:center; align-items:center; height:100%; color:white; font-size: 10px; font-weight:bold;"><?php echo strtoupper(substr($of['username'], 0, 1)); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-info">
                                            <span class="card-user"><?php echo htmlspecialchars($of['username']); ?></span>
                                            <span class="card-status">Online now!</span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($online_friends)): ?>
                                <div style="background: var(--background-secondary); border-radius: var(--radius-lg); padding: 32px 20px; text-align: center; color: var(--text-muted); font-size: 14px; border: 1px solid var(--background-accent);">
                                    <div style="font-weight: 800; color: var(--header-primary); margin-bottom: 8px;">Waiting for friends...</div>
                                    When your friends are online, their activity will shine here! ✨
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-muted); padding: 40px; text-align: center;">
                            <div style="font-size: 64px; margin-bottom: 24px;">✨</div>
                            <h2 style="color: var(--header-primary); margin-bottom: 12px; font-weight: 800; font-size: 28px; letter-spacing: -0.03em;">Welcome to <?php echo htmlspecialchars($current_server_name); ?>!</h2>
                            <p style="font-size: 16px; max-width: 400px; line-height: 1.6;">This is the start of a wonderful group conversation. Say hello to everyone!</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="chat-input-area" id="chat-input-row" style="flex-shrink: 0; <?php echo !$current_server_id ? 'display:none;' : ''; ?>">
                    <!-- File preview area -->
                    <div id="file-preview-area" style="display: none; padding: 12px 16px; background: var(--background-tertiary); border-radius: var(--radius-md); margin: 0 0 16px; align-items: center; gap: 12px; border: 1px solid var(--background-accent);">
                        <div id="file-preview-content" style="flex: 1; display: flex; align-items: center; gap: 10px;"></div>
                        <button type="button" onclick="clearFileAttachment()" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 24px; line-height: 1;">×</button>
                    </div>

                    <form id="send-message-form">
                        <div class="input-box" style="display: flex; align-items: center; gap: 12px; padding: 0 16px;">
                            <!-- Upload Button -->
                            <label for="file-upload-input" style="cursor: pointer; color: var(--text-muted); display: flex; align-items: center; flex-shrink: 0;" title="Attach file">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
                            </label>
                            <input type="file" id="file-upload-input" style="display: none;" accept="image/*,.pdf,.txt,.zip,.doc,.docx,.xls,.xlsx" onchange="handleFileSelect(event)">

                            <input type="text" id="message-input" class="message-input" placeholder="Type a cheerful message..." autocomplete="off">
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar Users -->
        <div class="sidebar-users" id="users-list">
            <?php if (!$current_server_id): ?>
                <div class="user-category">Online — 1</div>
                <div class="user-item">
                    <div class="user-avatar" style="background-image: url('assets/img/default_avatar.png'); background-size: cover; border-radius: 12px;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.8;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        <div class="status-indicator"></div>
                    </div>
                    <div class="user-name"><?php echo htmlspecialchars($username); ?></div>
                </div>
            <?php endif; ?>

            <!-- Sidebar Members (Right) -->
            <?php if ($current_server_id): ?>
                <div class="sidebar-members" id="sidebar-members">
                    <div class="members-inner">
                        <div id="members-list-container">
                            <!-- Members will be injected here by JS -->
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Group Actions Modal (Create or Join) -->
    <div id="group-actions-modal" class="modal">
        <div class="modal-content" id="modal-choices">
            <h2 style="color: var(--header-primary); text-align: center; margin-bottom: 24px;">Create a group</h2>
            <p style="color: var(--text-muted); text-align: center; margin-bottom: 24px;">Your group is where you and your friends hang out. Make yours and start talking.</p>
            
            <button class="btn-primary" onclick="showCreateForm()" style="width: 100%; margin-bottom: 12px; padding: 12px;">Create My Own</button>
            <div style="text-align: center; margin: 16px 0; color: var(--text-muted); font-size: 12px; font-weight: bold; text-transform: uppercase;">Have a code already?</div>
            <button class="btn-secondary" onclick="showJoinForm()" style="width: 100%; background: #4e5058; color: white; padding: 12px; border-radius: 3px; text-decoration: none;">Join a Group</button>
            
            <div style="display:flex; justify-content: center; margin-top: 24px;">
                <button type="button" class="btn-secondary" onclick="closeServerModal()">Back</button>
            </div>
        </div>

        <!-- Create Form (Hidden by default) -->
        <div class="modal-content" id="create-group-form-container" style="display: none;">
            <h2 style="color: var(--header-primary); text-align: center; margin-bottom: 8px;">Customize your group</h2>
            <p style="color: var(--text-muted); text-align: center; margin-bottom: 24px;">Give your new group a personality with a name. You can always change it later.</p>
            
            <form id="create-server-form">
                <div class="form-group">
                    <label class="label">Group Name</label>
                    <input type="text" id="create-group-name" class="input" placeholder="<?php echo htmlspecialchars($username); ?>'s group" required>
                </div>
                <div style="display:flex; justify-content: space-between; align-items: center; margin-top: 24px; background: #2b2d31; margin-left: -24px; margin-right: -24px; margin-bottom: -24px; padding: 16px 24px;">
                    <button type="button" class="btn-secondary" onclick="backToChoices()">Back</button>
                    <button type="submit" class="btn-primary" style="width: auto; padding: 10px 24px;">Create</button>
                </div>
            </form>
        </div>

        <!-- Join Form (Hidden by default) -->
        <div class="modal-content" id="join-group-form-container" style="display: none;">
            <h2 style="color: var(--header-primary); text-align: center; margin-bottom: 8px;">Join a Group</h2>
            <p style="color: var(--text-muted); text-align: center; margin-bottom: 24px;">Enter a code below to join an existing group.</p>
            
            <form id="join-server-form">
                <div class="form-group">
                    <label class="label">Group Code</label>
                    <input type="text" id="join-invite-code" class="input" placeholder="e.g. WELCOME" required>
                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 8px;">Codes should look like <span style="font-weight: bold; color: var(--text-normal);">WELCOME</span> or <span style="font-weight: bold; color: var(--text-normal);">H6J2K8L1</span></div>
                </div>
                <div style="display:flex; justify-content: space-between; align-items: center; margin-top: 24px; background: #2b2d31; margin-left: -24px; margin-right: -24px; margin-bottom: -24px; padding: 16px 24px;">
                    <button type="button" class="btn-secondary" onclick="backToChoices()">Back</button>
                    <button type="submit" class="btn-primary" style="width: auto; padding: 10px 24px;">Join Group</button>
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

    <!-- Create Group DM Modal -->
    <div id="group-dm-modal" class="modal">
        <div class="modal-content">
            <h2 style="color: var(--header-primary); margin-bottom: 8px;">Create Group Chat</h2>
            <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 20px;">Select friends to add to your new group.</p>
            
            <form id="create-group-dm-form">
                <div class="form-group">
                    <label class="label">Group Name</label>
                    <input type="text" id="group-dm-name" class="input" placeholder="Enter group name" required>
                </div>
                
                <div class="user-category" style="margin-top: 20px; margin-bottom: 8px;">Select Friends</div>
                <div id="friends-selection-list" style="max-height: 200px; overflow-y: auto; background: rgba(0,0,0,0.1); border-radius: 4px; padding: 4px;">
                    <!-- Friends will be injected here -->
                </div>

                <div style="display:flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                    <button type="button" class="btn-secondary" id="close-group-dm-modal">Cancel</button>
                    <button type="submit" class="btn-primary" style="width: auto; padding: 10px 24px;">Create Group</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toast-container" style="
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 10px;
        pointer-events: none;
    "></div>

    <script>
        // Inject PHP variables into JS
        window.allChatConfig = {
            currentServerId: <?php echo $current_server_id ?: 'null'; ?>,
            currentChannelId: <?php echo $current_channel_id ?: 'null'; ?>
        };
    </script>
    <script src="assets/js/app.js"></script>
</body>
</html>
