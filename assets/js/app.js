// Real-time Chat Logic
const messagesContainer = document.getElementById('messages-list');
const sendMessageForm = document.getElementById('send-message-form');
const messageInput = document.getElementById('message-input');

// Get channel_id and server_id from URL or fallback to injected PHP config
const urlParams = new URLSearchParams(window.location.search);
const currentChannelId = urlParams.get('channel_id') || (window.allChatConfig ? window.allChatConfig.currentChannelId : null);
const currentGroupId = urlParams.get('server_id') || (window.allChatConfig ? window.allChatConfig.currentServerId : null);

let lastMessageCount = 0;

// Fetch messages
async function fetchMessages() {
    if (!currentChannelId) return;

    try {
        const response = await fetch(`api/get_messages.php?channel_id=${currentChannelId}`);
        const data = await response.json();

        if (data.success) {
            if (data.messages.length !== lastMessageCount) {
                renderMessages(data.messages);
                lastMessageCount = data.messages.length;
                scrollToBottom();
            }
        }
    } catch (error) {
        console.error('Error fetching messages:', error);
    }
}

// Fetch Group Members
async function fetchGroupMembers() {
    if (!currentGroupId) return;

    try {
        const response = await fetch(`api/get_group_members.php?group_id=${currentGroupId}`);
        const data = await response.json();
        if (data.success) {
            renderGroupMembers(data.members);
        }
    } catch (error) {
        console.error('Error fetching group members:', error);
    }
}

function renderGroupMembers(members) {
    const container = document.getElementById('members-list-container');
    if (!container) return;

    const online = members.filter(m => m.status === 'online');
    const offline = members.filter(m => m.status === 'offline' || !m.status);

    let html = '';

    if (online.length > 0) {
        html += `<div class="members-category">Online — ${online.length}</div>`;
        html += online.map(m => `
            <div class="member-item">
                <div class="user-avatar-small" style="width: 32px; height: 32px; background-image: url('assets/img/${m.avatar || 'default_avatar.png'}'); background-size: cover;">
                    <div class="status-indicator" style="background-color: #23a55a; border: 2px solid var(--bg-users);"></div>
                    ${!m.avatar || m.avatar === 'default_avatar.png' ? `<svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.8; padding: 20%;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>` : ''}
                </div>
                <div class="message-username" style="color: var(--text-normal); margin-left: 8px;">${escapeHTML(m.username)}</div>
            </div>
        `).join('');
    }

    if (offline.length > 0) {
        html += `<div class="members-category" style="margin-top: 20px;">Offline — ${offline.length}</div>`;
        html += offline.map(m => `
            <div class="member-item" style="opacity: 0.6;">
                <div class="user-avatar-small" style="width: 32px; height: 32px; background-image: url('assets/img/${m.avatar || 'default_avatar.png'}'); background-size: cover;">
                    <div class="status-indicator" style="background-color: #949ba4; border: 2px solid var(--bg-users);"></div>
                    ${!m.avatar || m.avatar === 'default_avatar.png' ? `<svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.8; padding: 20%;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>` : ''}
                </div>
                <div class="message-username" style="margin-left: 8px;">${escapeHTML(m.username)}</div>
            </div>
        `).join('');
    }

    container.innerHTML = html;
}

// Render messages to DOM
function renderMessages(messages) {
    if (messages.length === 0) {
        messagesContainer.innerHTML = `
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-muted);">
                <h2 style="color: var(--header-primary); margin-bottom: 8px;">Welcome to the Chat</h2>
                <p>This is the start of this group conversation.</p>
            </div>
        `;
        return;
    }

    messagesContainer.innerHTML = messages.map(msg => `
            <div class="message-avatar" style="background-image: url('assets/img/${msg.avatar || 'default_avatar.png'}'); background-size: cover;">
                ${!msg.avatar || msg.avatar === 'default_avatar.png' ? `<svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.8; padding: 20%;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>` : ''}
            </div>
            <div class="message-content-wrapper">
                <div class="message-user-info">
                    <span class="message-username">${escapeHTML(msg.username)}</span>
                    <span class="message-timestamp">${formatDate(msg.created_at)}</span>
                </div>
                ${msg.content ? `<div class="message-body">${escapeHTML(msg.content)}</div>` : ''}
                ${msg.attachment_url ? renderAttachment(msg) : ''}
            </div>
        </div>
    `).join('');
}

function renderAttachment(msg) {
    const imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (imageTypes.includes(msg.attachment_type)) {
        return `
            <div style="margin-top: 6px;">
                <a href="${msg.attachment_url}" target="_blank">
                    <img src="${msg.attachment_url}" alt="${escapeHTML(msg.attachment_name)}" 
                         style="max-width: 400px; max-height: 300px; border-radius: 8px; cursor: pointer; display: block;" 
                         onerror="this.style.display='none'">
                </a>
            </div>`;
    }
    // Generic file
    const icon = getFileIcon(msg.attachment_type);
    return `
        <div style="display: inline-flex; align-items: center; gap: 10px; background: var(--bg-channels); border: 1px solid rgba(255,255,255,0.06); border-radius: 8px; padding: 10px 14px; margin-top: 6px;">
            <span style="font-size: 24px;">${icon}</span>
            <div>
                <a href="${msg.attachment_url}" download="${escapeHTML(msg.attachment_name)}" style="color: var(--accent); font-size: 14px; font-weight: 600; text-decoration: none;">${escapeHTML(msg.attachment_name)}</a>
                <div style="font-size: 12px; color: var(--text-muted);">${msg.attachment_type}</div>
            </div>
            <a href="${msg.attachment_url}" download="${escapeHTML(msg.attachment_name)}" style="color: var(--text-muted); margin-left: 8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            </a>
        </div>`;
}

function getFileIcon(mimeType) {
    if (!mimeType) return '📎';
    if (mimeType.includes('pdf')) return '📄';
    if (mimeType.includes('zip')) return '🗜️';
    if (mimeType.includes('word') || mimeType.includes('document')) return '📝';
    if (mimeType.includes('excel') || mimeType.includes('sheet')) return '📊';
    if (mimeType.includes('text')) return '📃';
    return '📎';
}

// File attachment state
let selectedFile = null;

// Handle file selection
function handleFileSelect(event) {
    const file = event.target.files[0];
    if (!file) return;
    if (file.size > 10 * 1024 * 1024) {
        alert('File too large. Max 10MB.');
        event.target.value = '';
        return;
    }
    selectedFile = file;
    const previewArea = document.getElementById('file-preview-area');
    const previewContent = document.getElementById('file-preview-content');
    previewArea.style.display = 'flex';

    const imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (imageTypes.includes(file.type)) {
        const reader = new FileReader();
        reader.onload = e => {
            previewContent.innerHTML = `
                <img src="${e.target.result}" style="max-height: 80px; max-width: 120px; border-radius: 6px; object-fit: cover;">
                <span style="color: var(--text-normal); font-size: 13px;">${escapeHTML(file.name)}</span>
            `;
        };
        reader.readAsDataURL(file);
    } else {
        previewContent.innerHTML = `
            <span style="font-size: 24px;">${getFileIcon(file.type)}</span>
            <span style="color: var(--text-normal); font-size: 13px;">${escapeHTML(file.name)}</span>
        `;
    }
}

function clearFileAttachment() {
    selectedFile = null;
    document.getElementById('file-upload-input').value = '';
    document.getElementById('file-preview-area').style.display = 'none';
    document.getElementById('file-preview-content').innerHTML = '';
}

// Send message
sendMessageForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const content = messageInput.value.trim();
    if (!content && !selectedFile) return;
    if (!currentChannelId) return;

    messageInput.value = '';

    // If there's a file, upload it (along with optional text)
    if (selectedFile) {
        const formData = new FormData();
        formData.append('channel_id', currentChannelId);
        formData.append('content', content);
        formData.append('file', selectedFile);
        clearFileAttachment();
        try {
            const response = await fetch('api/upload.php', { method: 'POST', body: formData });
            const data = await response.json();
            if (data.success) { fetchMessages(); }
            else { alert('Upload error: ' + data.error); }
        } catch (error) { console.error('Upload error:', error); }
        return;
    }

    // Text-only message
    const formData = new FormData();
    formData.append('channel_id', currentChannelId);
    formData.append('content', content);

    try {
        const response = await fetch('api/send_message.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.success) { fetchMessages(); }
        else { alert('Error sending message: ' + data.error); }
    } catch (error) { console.error('Error sending message:', error); }
});

// Helper functions
function scrollToBottom() {
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

// Modal Toggle Logic
const joinModal = document.getElementById('group-actions-modal');
const modalChoices = document.getElementById('modal-choices');
const createContainer = document.getElementById('create-group-form-container');
const joinContainer = document.getElementById('join-group-form-container');

const addGroupBtn = document.getElementById('add-group-btn');
const joinServerForm = document.getElementById('join-server-form');
const createServerForm = document.getElementById('create-server-form');
const inviteInput = document.getElementById('join-invite-code');
const groupNameInput = document.getElementById('create-group-name');

if (addGroupBtn) {
    addGroupBtn.addEventListener('click', () => {
        joinModal.style.display = 'flex';
        backToChoices();
    });
}

function closeServerModal() {
    joinModal.style.display = 'none';
}

function showCreateForm() {
    modalChoices.style.display = 'none';
    createContainer.style.display = 'block';
    groupNameInput.focus();
}

function showJoinForm() {
    modalChoices.style.display = 'none';
    joinContainer.style.display = 'block';
    inviteInput.focus();
}

function backToChoices() {
    modalChoices.style.display = 'block';
    createContainer.style.display = 'none';
    joinContainer.style.display = 'none';
}

window.addEventListener('click', (e) => {
    if (e.target === joinModal) {
        closeServerModal();
    }
});

// Join Server Logic
if (joinServerForm) {
    joinServerForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const inviteCode = inviteInput.value.trim();
        if (!inviteCode) return;

        const formData = new FormData();
        formData.append('invite_code', inviteCode);

        try {
            const response = await fetch('api/join_server.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.success) {
                window.location.href = `index.php?server_id=${data.group_id}`;
            } else {
                alert(data.error);
            }
        } catch (error) {
            console.error('Error joining group:', error);
        }
    });
}

// Create Group Logic
if (createServerForm) {
    createServerForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const groupName = groupNameInput.value.trim();
        if (!groupName) return;

        const formData = new FormData();
        formData.append('name', groupName);

        try {
            const response = await fetch('api/create_server.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.success) {
                window.location.href = `index.php?server_id=${data.group_id}&channel_id=${data.channel_id}`;
            } else {
                alert(data.error);
            }
        } catch (error) {
            console.error('Error creating group:', error);
        }
    });
}

function escapeHTML(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

async function confirmDeleteGroup(groupId) {
    if (!confirm('Are you sure you want to delete this group? This action cannot be undone.')) {
        return;
    }

    const formData = new FormData();
    formData.append('group_id', groupId);

    try {
        const response = await fetch('api/delete_group.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            window.location.href = 'index.php'; // Redirect to home
        } else {
            alert(data.error);
        }
    } catch (error) {
        console.error('Error deleting group:', error);
        alert('An error occurred while deleting the group.');
    }
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleString([], { hour: '2-digit', minute: '2-digit', hour12: true });
}
// View state
let currentHomeView = 'friends';

function switchHomeView(view) {
    currentHomeView = view;

    const friendsArea = document.getElementById('friends-area');
    const msgRequestsArea = document.getElementById('message-requests-area');
    const navFriends = document.getElementById('nav-friends');
    const navMsgRequests = document.getElementById('nav-message-requests');
    const headerTabs = document.querySelector('.home-header-tabs');

    if (!friendsArea || !msgRequestsArea) return;

    if (view === 'friends') {
        friendsArea.style.display = 'block';
        msgRequestsArea.style.display = 'none';
        if (headerTabs) headerTabs.style.display = 'flex';

        navFriends.classList.add('active');
        navMsgRequests.classList.remove('active');
    } else if (view === 'message-requests') {
        friendsArea.style.display = 'none';
        msgRequestsArea.style.display = 'block';
        if (headerTabs) headerTabs.style.display = 'none';

        navFriends.classList.remove('active');
        navMsgRequests.classList.add('active');
    }
}

// DM State
let currentDmUserId = null;
let lastDmMessageCount = 0;
let dmPollInterval = null;

// Open DM view
function openDM(friendId, friendUsername, friendAvatar) {
    currentDmUserId = friendId;
    currentHomeView = 'dm';

    // Update Header
    const avatarEl = document.getElementById('dm-header-avatar');
    if (friendAvatar && friendAvatar !== 'default_avatar.png') {
        avatarEl.style.backgroundImage = `url('assets/img/${friendAvatar}')`;
        avatarEl.innerHTML = '';
    } else {
        avatarEl.style.backgroundImage = 'none';
        avatarEl.innerHTML = `<svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.8; padding: 20%;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>`;
    }
    document.getElementById('dm-header-username').textContent = friendUsername;

    // Toggle Views
    const friendsArea = document.getElementById('friends-area');
    const msgRequestsArea = document.getElementById('message-requests-area');
    const dmArea = document.getElementById('dm-chat-area');
    const headerTabs = document.querySelector('.home-header-tabs');

    if (friendsArea) friendsArea.style.display = 'none';
    if (msgRequestsArea) msgRequestsArea.style.display = 'none';
    if (dmArea) dmArea.style.display = 'flex';
    if (headerTabs) headerTabs.style.display = 'none';

    // Un-highlight nav
    document.getElementById('nav-friends')?.classList.remove('active');
    document.getElementById('nav-message-requests')?.classList.remove('active');

    // Fetch messages immediately
    lastDmMessageCount = 0;
    document.getElementById('dm-messages-list').innerHTML = '';
    fetchDMs();

    // Start DM polling
    if (dmPollInterval) clearInterval(dmPollInterval);
    dmPollInterval = setInterval(fetchDMs, 2000);
}

// Ensure DM view hides when clicking back to Friends
const originalSwitchHomeView = switchHomeView;
switchHomeView = function (view) {
    if (dmPollInterval) {
        clearInterval(dmPollInterval);
        dmPollInterval = null;
    }
    currentDmUserId = null;
    const dmArea = document.getElementById('dm-chat-area');
    if (dmArea) dmArea.style.display = 'none';

    // call original
    originalSwitchHomeView(view);
}

// Initial fetch and polling
if (currentChannelId) {
    fetchMessages();
    setInterval(fetchMessages, 2000); // Poll every 2 seconds
    if (currentGroupId) {
        fetchGroupMembers();
        setInterval(fetchGroupMembers, 10000); // Poll group members every 10 seconds
    }
} else if (!currentGroupId) {
    fetchFriends();
    setInterval(fetchFriends, 5000); // Poll friends every 5 seconds
}

// Always poll for friend request notifications (regardless of page)
setInterval(checkFriendRequestNotifications, 10000); // every 10s

// Notification: check for new friend requests
async function checkFriendRequestNotifications() {
    try {
        const response = await fetch('api/notifications.php');
        const data = await response.json();
        if (data.success && data.new_requests.length > 0) {
            data.new_requests.forEach(req => showFriendRequestToast(req));
            // If on friends page, refresh the list too
            if (!currentGroupId) fetchFriends();
        }
    } catch (e) { }
}

function showFriendRequestToast(req) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const initial = req.username ? req.username[0].toUpperCase() : '?';
    const toast = document.createElement('div');
    toast.style.cssText = `
        pointer-events: all;
        background: #2b2d31;
        border: 1px solid rgba(255,255,255,0.1);
        border-left: 4px solid #5865f2;
        border-radius: 8px;
        padding: 14px 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 300px;
        max-width: 360px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.4);
        animation: slideInToast 0.4s ease;
        cursor: pointer;
    `;
    toast.innerHTML = `
        <div style="width:36px; height:36px; border-radius:50%; background:#5865f2; display:flex; align-items:center; justify-content:center; font-weight:bold; color:white; flex-shrink:0;">${initial}</div>
        <div style="flex:1;">
            <div style="font-weight:700; color:#fff; font-size:13px;">Friend Request Received!</div>
            <div style="color:#949ba4; font-size:12px; margin-top:2px;"><b style="color:#dbdee1;">${escapeHTML(req.username)}</b> sent you a friend request.</div>
        </div>
        <div onclick="this.parentElement.remove()" style="color:#949ba4; font-size:20px; line-height:1; padding:4px; cursor:pointer;">×</div>
    `;

    // Click to switch to pending tab
    toast.addEventListener('click', (e) => {
        if (e.target.tagName !== 'DIV' || e.target.textContent !== '×') {
            if (!currentServerId && typeof switchTab === 'function') {
                switchTab('pending');
            } else {
                window.location.href = 'index.php';
            }
            toast.remove();
        }
    });

    container.appendChild(toast);

    // Auto dismiss after 6 seconds
    setTimeout(() => {
        toast.style.animation = 'fadeOutToast 0.4s ease forwards';
        setTimeout(() => toast.remove(), 400);
    }, 6000);
}

// Inject toast animations
const toastStyle = document.createElement('style');
toastStyle.textContent = `
    @keyframes slideInToast {
        from { transform: translateX(120%); opacity: 0; }
        to   { transform: translateX(0);    opacity: 1; }
    }
    @keyframes fadeOutToast {
        from { transform: translateX(0);    opacity: 1; }
        to   { transform: translateX(120%); opacity: 0; }
    }
`;
document.head.appendChild(toastStyle);

// Tab state
let currentTab = 'online';
let cachedFriendsData = { friends: [], pending: [], blocked: [] };

// Friends System Logic
async function fetchFriends() {
    if (currentGroupId) return;

    try {
        const response = await fetch('api/friends.php?action=list');
        const data = await response.json();
        if (data.success) {
            cachedFriendsData = { friends: data.friends, pending: data.pending || [], blocked: data.blocked || [] };
            renderFriends(cachedFriendsData.friends, cachedFriendsData.pending, cachedFriendsData.blocked);
        }
    } catch (error) {
        console.error('Error fetching friends:', error);
    }
}

function switchTab(tab) {
    currentTab = tab;
    // Update active tab visuals
    document.querySelectorAll('.tab-item[data-tab]').forEach(el => {
        el.classList.toggle('active', el.dataset.tab === tab);
    });
    renderFriends(cachedFriendsData.friends, cachedFriendsData.pending, cachedFriendsData.blocked);
}

function renderFriends(friends, pending, blocked = []) {
    const friendsList = document.getElementById('friends-list-content');
    const pendingList = document.getElementById('pending-friends-content');
    const dmList = document.getElementById('dm-list');
    const activeNowPanel = document.querySelector('.sidebar-active-now');
    const onlineCounter = document.querySelector('.user-category[style*="margin-top: 8px"]');

    const onlineFriends = friends.filter(f => f.status === 'online');

    // Update counter label
    if (onlineCounter) {
        const labels = { online: `Online — ${onlineFriends.length}`, all: `All Friends — ${friends.length}`, pending: `Pending — ${pending.length}`, blocked: `Blocked — ${blocked.length}` };
        onlineCounter.textContent = labels[currentTab] || `Online — ${onlineFriends.length}`;
    }

    // Render Sidebar DM List (always shows accepted friends)
    if (dmList) {
        dmList.innerHTML = friends.length ? friends.map(f => `
            <a href="#" class="channel-item" onclick="openDM(${f.user_id}, '${escapeHTML(f.username.replace(/'/g, "\\'"))}', '${f.avatar || 'default_avatar.png'}')">
                <div class="user-avatar-small" style="width: 32px; height: 32px; background-image: url('assets/img/${f.avatar || 'default_avatar.png'}'); background-size: cover;">
                    <div class="status-indicator" style="background-color: ${f.status === 'online' ? '#23a55a' : '#949ba4'}"></div>
                    ${!f.avatar || f.avatar === 'default_avatar.png' ? `<svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.8; padding: 20%;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>` : ''}
                </div>
                <span style="margin-left: 8px;">${escapeHTML(f.username)}</span>
            </a>
        `).join('') : '<div style="padding: 8px 16px; color: var(--text-muted); font-size: 12px;">No friends yet.</div>';
    }

// Search Functionality
const mainSearchInput = document.querySelector('.home-search-container input');
if (mainSearchInput) {
    mainSearchInput.addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase();
        const friends = cachedFriendsData.friends.filter(f => f.username.toLowerCase().includes(query));
        renderFriends(friends, cachedFriendsData.pending, cachedFriendsData.blocked);
    });
}
    if (friendsList) {
        let displayList = [];
        let emptyMessage = '';

        if (currentTab === 'online') {
            displayList = onlineFriends;
            emptyMessage = '<div style="padding: 20px; text-align: center; color: var(--text-muted);">No friends are online right now.</div>';
        } else if (currentTab === 'all') {
            displayList = friends;
            emptyMessage = '<div style="padding: 20px; text-align: center; color: var(--text-muted);">No friends yet. Add some to start chatting!</div>';
        } else if (currentTab === 'pending') {
            // Render pending inside friendsList
            friendsList.innerHTML = pending.length ? pending.map(p => `
                <div class="friend-item" style="border-top: 1px solid rgba(255,255,255,0.05);">
                    <div class="friend-info">
                        <div class="user-avatar-small" style="background-image: url('assets/img/${p.avatar || 'default_avatar.png'}'); background-size: cover;">
                            ${!p.avatar || p.avatar === 'default_avatar.png' ? `<svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.8; padding: 20%;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>` : ''}
                        </div>
                        <div>
                            <div class="message-username">${escapeHTML(p.username)}</div>
                            <div class="card-status">${p.direction === 'outgoing' ? 'Outgoing Request' : 'Incoming Request'}</div>
                        </div>
                    </div>
                    <div class="friend-actions">
                        ${p.direction !== 'outgoing' ? `<div class="action-btn accept" onclick="respondFriend(${p.id}, 'accepted')" title="Accept"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg></div>` : ''}
                        <div class="action-btn reject" onclick="respondFriend(${p.id}, 'rejected')" title="${p.direction === 'outgoing' ? 'Cancel' : 'Reject'}"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></div>
                    </div>
                </div>
            `).join('') : '<div style="padding: 20px; text-align: center; color: var(--text-muted);">No pending requests.</div>';
            return; // skip generic render below
        } else if (currentTab === 'blocked') {
            friendsList.innerHTML = blocked.length ? blocked.map(b => `
                <div class="friend-item" style="border-top: 1px solid rgba(255,255,255,0.05);">
                    <div class="friend-info">
                        <div class="user-avatar-small" style="background-image: url('assets/img/${b.avatar || 'default_avatar.png'}'); background-size: cover;">
                            ${!b.avatar || b.avatar === 'default_avatar.png' ? `<svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.8; padding: 20%;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>` : ''}
                        </div>
                        <div>
                            <div class="message-username">${escapeHTML(b.username)}</div>
                            <div class="card-status">Blocked</div>
                        </div>
                    </div>
                    <div class="friend-actions">
                        <div class="action-btn" onclick="respondFriend(${b.id}, 'rejected')" title="Unblock" style="font-size: 11px; width: auto; padding: 0 10px; border-radius: 4px;">Unblock</div>
                    </div>
                </div>
            `).join('') : '<div style="padding: 20px; text-align: center; color: var(--text-muted);">No blocked users.</div>';
            return; // skip generic render below
        }

        friendsList.innerHTML = displayList.length ? displayList.map(f => `
            <div class="friend-item" style="border-top: 1px solid rgba(255,255,255,0.05);">
                <div class="friend-info">
                    <div class="user-avatar-small" style="background-image: url('assets/img/${f.avatar || 'default_avatar.png'}'); background-size: cover;">
                        <div class="status-indicator" style="background-color: ${f.status === 'online' ? '#23a55a' : '#949ba4'}"></div>
                        ${!f.avatar || f.avatar === 'default_avatar.png' ? `<svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.8; padding: 20%;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>` : ''}
                    </div>
                    <div>
                        <div class="message-username">${escapeHTML(f.username)}</div>
                        <div class="card-status">${f.status.charAt(0).toUpperCase() + f.status.slice(1)}</div>
                    </div>
                </div>
                <div class="friend-actions">
                    <div class="action-btn" title="Message" onclick="openDM(${f.user_id}, '${escapeHTML(f.username.replace(/'/g, "\\'"))}', '${f.avatar || 'default_avatar.png'}')"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg></div>
                    <div class="action-btn" title="More" onclick="showFriendContextMenu(event, ${f.id}, ${f.user_id}, '${escapeHTML(f.username.replace(/'/g, "\\'"))}', '${f.avatar || 'default_avatar.png'}')"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"></circle><circle cx="12" cy="5" r="1"></circle><circle cx="12" cy="19" r="1"></circle></svg></div>
                </div>
            </div>
        `).join('') : emptyMessage;
    }

    // Render Pending Requests
    if (pendingList) {
        const pendingContainer = pendingList.parentElement;
        if (pending.length) {
            pendingContainer.style.display = 'block';
            pendingList.innerHTML = pending.map(p => `
                <div class="friend-item" style="border-top: 1px solid rgba(255,255,255,0.05);">
                    <div class="friend-info">
                        <div class="user-avatar-small" style="background-image: url('assets/img/${p.avatar || 'default_avatar.png'}'); background-size: cover;">
                            ${!p.avatar || p.avatar === 'default_avatar.png' ? `<svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.8; padding: 20%;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>` : ''}
                        </div>
                        <div>
                            <div class="message-username">${escapeHTML(p.username)}</div>
                            <div class="card-status">Incoming Friend Request</div>
                        </div>
                    </div>
                    <div class="friend-actions">
                        <div class="action-btn accept" onclick="respondFriend(${p.id}, 'accepted')" title="Accept"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
                        <div class="action-btn reject" onclick="respondFriend(${p.id}, 'rejected')" title="Reject"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></div>
                    </div>
                </div>
            `).join('');
        } else {
            pendingContainer.style.display = 'none';
        }
    }

    // Render Active Now Panel
    if (activeNowPanel) {
        const title = '<div class="active-now-title">Active Now</div>';
        const cards = onlineFriends.map(of => `
            <div class="active-card">
                <div class="card-header">
                    <div class="card-avatar" style="background-image: url('assets/img/${of.avatar || 'default_avatar.png'}'); background-size: cover;">
                        ${!of.avatar || of.avatar === 'default_avatar.png' ? `<svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.8; padding: 20%;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>` : ''}
                    </div>
                    <div class="card-info">
                        <span class="card-user">${escapeHTML(of.username)}</span>
                        <span class="card-status">Online</span>
                    </div>
                </div>
            </div>
        `).join('');

        const emptyState = `
            <div style="background: rgba(0,0,0,0.2); border-radius: 8px; padding: 20px; text-align: center; color: var(--text-muted); font-size: 14px;">
                <div style="font-weight: bold; color: white; margin-bottom: 4px;">It's quiet for now...</div>
                When a friend starts an activity—like playing a game or hanging out on voice—it'll show up here!
            </div>
        `;

        activeNowPanel.innerHTML = title + (onlineFriends.length ? cards : emptyState);
    }
}

async function respondFriend(requestId, status) {
    try {
        const response = await fetch('api/friends.php?action=respond', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: requestId, status: status })
        });
        const data = await response.json();
        if (data.success) fetchFriends();
        else alert(data.error);
    } catch (error) {
        console.error('Error responding to friend request:', error);
    }
}

// Add Friend Modal Logic
const addFriendModal = document.getElementById('add-friend-modal');
const closeAddFriendBtn = document.getElementById('close-add-friend-modal');
const addFriendForm = document.getElementById('add-friend-form');
const addFriendInput = document.getElementById('add-friend-username');

function showAddFriendModal() {
    addFriendModal.style.display = 'flex';
    addFriendInput.focus();
}

if (closeAddFriendBtn) {
    closeAddFriendBtn.addEventListener('click', () => {
        addFriendModal.style.display = 'none';
    });
}

if (addFriendForm) {
    addFriendForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const username = addFriendInput.value.trim();
        if (!username) return;

        try {
            const response = await fetch('api/friends.php?action=send', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username: username })
            });
            const data = await response.json();
            if (data.success) {
                alert('Friend request sent!');
                addFriendModal.style.display = 'none';
                addFriendInput.value = '';
                fetchFriends();
            } else {
                alert(data.error);
            }
        } catch (error) {
            console.error('Error sending friend request:', error);
        }
    });
}

// Mobile Sidebar Logic
const mobileMenuBtn = document.getElementById('mobile-menu-btn');
const mobileUsersBtn = document.getElementById('mobile-users-btn');
const sidebarOverlay = document.getElementById('sidebar-overlay');
const serverSidebar = document.querySelector('.sidebar-servers');
const channelSidebar = document.querySelector('.sidebar-channels');
const userSidebar = document.querySelector('.sidebar-users');

if (mobileMenuBtn) {
    mobileMenuBtn.addEventListener('click', () => {
        serverSidebar.classList.add('active');
        channelSidebar.classList.add('active');
        sidebarOverlay.classList.add('active');
    });
}

if (mobileUsersBtn) {
    mobileUsersBtn.addEventListener('click', () => {
        if (userSidebar) userSidebar.classList.toggle('active');
        const sidebarMembers = document.querySelector('.sidebar-members');
        if (sidebarMembers) sidebarMembers.classList.toggle('active');
        sidebarOverlay.classList.add('active');
    });
}

if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', () => {
        serverSidebar.classList.remove('active');
        channelSidebar.classList.remove('active');
        if (userSidebar) userSidebar.classList.remove('active');
        const sidebarMembers = document.querySelector('.sidebar-members');
        if (sidebarMembers) sidebarMembers.classList.remove('active');
        sidebarOverlay.classList.remove('active');
    });
}

// Search Button interaction
window.addEventListener('load', () => {
    const searchBtn = document.querySelector('.home-search-btn');
    if (searchBtn && !searchBtn.tagName === 'INPUT') { // If it's the button one
        searchBtn.onclick = () => {
             const input = document.querySelector('.home-search-container input');
             if (input) input.focus();
        };
    }
});

// Group DM Modal Logic
const groupDMModal = document.getElementById('group-dm-modal');
const openGroupDMBtn = document.getElementById('open-group-dm-btn');
const closeGroupDMBtn = document.getElementById('close-group-dm-modal');
const createGroupDMForm = document.getElementById('create-group-dm-form');
const friendsSelectionList = document.getElementById('friends-selection-list');

window.showGroupDMModal = function () {
    if (typeof populateFriendsSelection === 'function') {
        populateFriendsSelection();
    }
    if (groupDMModal) {
        groupDMModal.style.display = 'flex';
        groupDMModal.style.zIndex = '10000'; // Ensure it's on top
    }
};

if (openGroupDMBtn) {
    // Keep internal listener as backup
    openGroupDMBtn.addEventListener('click', showGroupDMModal);
}

if (closeGroupDMBtn) {
    closeGroupDMBtn.addEventListener('click', () => {
        groupDMModal.style.display = 'none';
    });
}

function populateFriendsSelection() {
    if (!friendsSelectionList) return;

    if (cachedFriendsData.friends.length === 0) {
        friendsSelectionList.innerHTML = '<div style="padding: 10px; color: var(--text-muted); font-size: 13px;">No friends found to add.</div>';
        return;
    }

    friendsSelectionList.innerHTML = cachedFriendsData.friends.map(f => `
        <label style="display: flex; align-items: center; gap: 10px; padding: 8px; cursor: pointer; border-radius: 4px; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.05)'" onmouseout="this.style.background='transparent'">
            <input type="checkbox" name="group-members" value="${f.user_id}" style="width: 18px; height: 18px; cursor: pointer;">
            <div class="user-avatar-small" style="width: 24px; height: 24px; background-image: url('assets/img/${f.avatar || 'default_avatar.png'}'); background-size: cover;">
                ${!f.avatar || f.avatar === 'default_avatar.png' ? `<svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.8; padding: 20%;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>` : ''}
            </div>
            <span style="color: var(--text-normal); font-size: 14px;">${escapeHTML(f.username)}</span>
        </label>
    `).join('');
}

if (createGroupDMForm) {
    createGroupDMForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const groupName = document.getElementById('group-dm-name').value.trim();
        const selectedMembers = Array.from(document.querySelectorAll('input[name="group-members"]:checked')).map(cb => cb.value);

        if (!groupName) return;
        if (selectedMembers.length === 0) {
            alert('Please select at least one friend to add to the group.');
            return;
        }

        const formData = new FormData();
        formData.append('name', groupName);
        formData.append('members', JSON.stringify(selectedMembers));

        try {
            const response = await fetch('api/create_server.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.success) {
                groupDMModal.style.display = 'none';
                document.getElementById('group-dm-name').value = '';
                // Redirect to the new group chat
                window.location.href = `index.php?server_id=${data.group_id}&channel_id=${data.channel_id}`;
            } else {
                alert(data.error);
            }
        } catch (error) {
            console.error('Error creating group chat:', error);
            alert('An error occurred while creating the group.');
        }
    });
}

/* --- DIRECT MESSAGES LOGIC --- */

// Fetch DMs
async function fetchDMs() {
    if (!currentDmUserId) return;
    try {
        const response = await fetch(`api/get_dms.php?friend_id=${currentDmUserId}`);
        const data = await response.json();
        if (data.success) {
            if (data.messages.length !== lastDmMessageCount) {
                renderDMMessages(data.messages);
                lastDmMessageCount = data.messages.length;
                const dmContainer = document.getElementById('dm-messages-list');
                dmContainer.scrollTop = dmContainer.scrollHeight;
            }
        }
    } catch (e) { console.error('Error fetching DMs:', e); }
}

// Render DMs
function renderDMMessages(messages) {
    const dmContainer = document.getElementById('dm-messages-list');
    if (messages.length === 0) {
        dmContainer.innerHTML = `
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-muted); padding-bottom: 20px;">
                <h2 style="color: var(--header-primary); margin-bottom: 8px;">No messages yet</h2>
                <p>Send a message or wave to start the conversation!</p>
            </div>
        `;
        return;
    }

    dmContainer.innerHTML = messages.map(msg => `
        <div class="message-item">
            <div class="message-avatar" style="background-image: url('assets/img/${msg.avatar || 'default_avatar.png'}'); background-size: cover;">
                ${!msg.avatar || msg.avatar === 'default_avatar.png' ? `<svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.8; padding: 20%;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>` : ''}
            </div>
            <div class="message-content-wrapper">
                <div class="message-user-info">
                    <span class="message-username">${escapeHTML(msg.username)}</span>
                    <span class="message-timestamp">${formatDate(msg.created_at)}</span>
                </div>
                ${msg.content ? `<div class="message-body">${escapeHTML(msg.content)}</div>` : ''}
                ${msg.attachment_url ? renderAttachment(msg) : ''}
            </div>
        </div>
    `).join('');
}

// DM Attachment Logic
let selectedDMFile = null;

function handleDMFileSelect(event) {
    const file = event.target.files[0];
    if (!file) return;
    if (file.size > 10 * 1024 * 1024) {
        alert('File too large. Max 10MB.');
        event.target.value = '';
        return;
    }
    selectedDMFile = file;
    const previewArea = document.getElementById('dm-file-preview-area');
    const previewContent = document.getElementById('dm-file-preview-content');
    previewArea.style.display = 'flex';

    const imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (imageTypes.includes(file.type)) {
        const reader = new FileReader();
        reader.onload = e => {
            previewContent.innerHTML = `
                <img src="${e.target.result}" style="max-height: 80px; max-width: 120px; border-radius: 6px; object-fit: cover;">
                <span style="color: var(--text-normal); font-size: 13px;">${escapeHTML(file.name)}</span>
            `;
        };
        reader.readAsDataURL(file);
    } else {
        previewContent.innerHTML = `
            <span style="font-size: 24px;">${getFileIcon(file.type)}</span>
            <span style="color: var(--text-normal); font-size: 13px;">${escapeHTML(file.name)}</span>
        `;
    }
}

function clearDMFileAttachment() {
    selectedDMFile = null;
    document.getElementById('dm-file-upload-input').value = '';
    document.getElementById('dm-file-preview-area').style.display = 'none';
    document.getElementById('dm-file-preview-content').innerHTML = '';
}

// Send DM
const dmSendMessageForm = document.getElementById('dm-send-message-form');
if (dmSendMessageForm) {
    console.log("DM Form found and listener attached");
    dmSendMessageForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        console.log("DM form submitted");
        const dmMessageInput = document.getElementById('dm-message-input');
        const content = dmMessageInput.value.trim();

        console.log("DM Content:", content);
        console.log("currentDmUserId:", currentDmUserId);

        if (!content && !selectedDMFile) {
            console.log("Empty content and no file, aborting");
            return;
        }
        if (!currentDmUserId) {
            console.log("No currentDmUserId, aborting");
            alert("Error: No friend selected to DM!");
            return;
        }

        dmMessageInput.value = '';

        if (selectedDMFile) {
            const formData = new FormData();
            formData.append('friend_id', currentDmUserId);
            formData.append('content', content);
            formData.append('file', selectedDMFile);
            clearDMFileAttachment();
            try {
                const response = await fetch('api/upload_dm.php', { method: 'POST', body: formData });
                const data = await response.json();
                console.log("Upload DM API response:", data);
                if (data.success) { fetchDMs(); }
                else { alert('Upload error: ' + data.error); }
            } catch (error) { console.error('Upload error:', error); }
            return;
        }

        // Text-only DM
        const formData = new FormData();
        formData.append('friend_id', currentDmUserId);
        formData.append('content', content);

        try {
            console.log("Sending text DM to API...");
            const response = await fetch('api/send_dm.php', { method: 'POST', body: formData });
            const data = await response.json();
            console.log("Send DM API response:", data);
            if (data.success) { fetchDMs(); }
            else { alert('Error sending message: ' + data.error); }
        } catch (error) { console.error('Error sending message:', error); }
    });
} else {
    console.error("CRITICAL: dm-send-message-form NOT FOUND in DOM during app.js execution!");
}

// Friend Context Menu Logic
let activeFriendshipId = null;
let activeFriendData = null;
const friendContextMenu = document.createElement('div');
friendContextMenu.className = 'friend-context-menu';
friendContextMenu.innerHTML = `
    <div class="context-menu-item" id="ctx-open-dm">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
        Message
    </div>
    <div class="context-menu-item danger" id="ctx-remove-friend">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
        Remove Friend
    </div>
`;
document.body.appendChild(friendContextMenu);

window.showFriendContextMenu = function(event, friendshipId, userId, username, avatar) {
    event.preventDefault();
    event.stopPropagation();
    activeFriendshipId = friendshipId;
    activeFriendData = { userId, username, avatar };
    
    // Position the menu
    const x = event.clientX;
    const y = event.clientY;
    
    // Check if it's too close to the bottom
    const menuHeight = 100;
    const menuWidth = 160;
    
    let left = x - menuWidth;
    let top = y;
    
    if (left < 0) left = 0;
    if (top + menuHeight > window.innerHeight) top = window.innerHeight - menuHeight;
    
    friendContextMenu.style.left = `${left}px`;
    friendContextMenu.style.top = `${top}px`;
    friendContextMenu.style.display = 'block';
};

window.addEventListener('click', () => {
    friendContextMenu.style.display = 'none';
});

document.getElementById('ctx-remove-friend').onclick = async () => {
    if (!activeFriendshipId) return;
    if (!confirm('Are you sure you want to remove this friend?')) return;
    
    try {
        const response = await fetch(`api/friends.php?action=remove`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: activeFriendshipId })
        });
        const data = await response.json();
        if (data.success) {
            fetchFriends();
        } else {
            alert(data.error);
        }
    } catch (e) { console.error(e); }
};

document.getElementById('ctx-open-dm').onclick = () => {
    if (activeFriendData) {
        openDM(activeFriendData.userId, activeFriendData.username, activeFriendData.avatar);
    }
    friendContextMenu.style.display = 'none';
};

// Group Switcher Dropdown
const groupSwitcherHeader = document.getElementById('group-switcher-header');
const groupSwitcherDropdown = document.getElementById('group-switcher-dropdown');

if (groupSwitcherHeader && groupSwitcherDropdown) {
    groupSwitcherHeader.addEventListener('click', function(e) {
        // Only toggle if clicking the header itself or the toggle icon, not the dropdown contents
        if (e.target.closest('.group-switcher-dropdown')) return;
        
        e.preventDefault();
        e.stopPropagation();
        groupSwitcherDropdown.classList.toggle('active');
        
        // Close other menus if any
        if (window.friendContextMenu) {
            window.friendContextMenu.style.display = 'none';
        }
    });

    // Close on any click outside
    document.addEventListener('click', function(e) {
        if (!groupSwitcherHeader.contains(e.target)) {
            groupSwitcherDropdown.classList.remove('active');
        }
    });
}

const switcherAddGroup = document.getElementById('switcher-add-group');
if (switcherAddGroup) {
    switcherAddGroup.addEventListener('click', (e) => {
        e.preventDefault();
        showGroupActionsModal();
    });
}

