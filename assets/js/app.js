// Real-time Chat Logic
const messagesContainer = document.getElementById('messages-list');
const sendMessageForm = document.getElementById('send-message-form');
const messageInput = document.getElementById('message-input');

// Get channel_id and server_id from URL
const urlParams = new URLSearchParams(window.location.search);
const currentChannelId = urlParams.get('channel_id');
const currentServerId = urlParams.get('server_id');

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

// Render messages to DOM
function renderMessages(messages) {
    if (messages.length === 0) {
        messagesContainer.innerHTML = `
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-muted);">
                <h2 style="color: var(--header-primary); margin-bottom: 8px;">Welcome to #General</h2>
                <p>This is the start of the #General channel.</p>
            </div>
        `;
        return;
    }

    messagesContainer.innerHTML = messages.map(msg => `
        <div class="message-item">
            <div class="message-avatar" style="background-image: url('assets/img/${msg.avatar}'); background-size: cover;">
                ${!msg.avatar || msg.avatar === 'default_avatar.png' ? `<span style="display:flex; justify-content:center; align-items:center; height:100%; color:white; font-weight:bold;">${msg.username[0].toUpperCase()}</span>` : ''}
            </div>
            <div class="message-content-wrapper">
                <div class="message-user-info">
                    <span class="message-username">${escapeHTML(msg.username)}</span>
                    <span class="message-timestamp">${formatDate(msg.created_at)}</span>
                </div>
                <div class="message-body">${escapeHTML(msg.content)}</div>
            </div>
        </div>
    `).join('');
}

// Send message
sendMessageForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const content = messageInput.value.trim();
    if (!content || !currentChannelId) return;

    const formData = new FormData();
    formData.append('channel_id', currentChannelId);
    formData.append('content', content);

    messageInput.value = '';

    try {
        const response = await fetch('api/send_message.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            fetchMessages(); // Refresh messages immediately
        } else {
            alert('Error sending message: ' + data.error);
        }
    } catch (error) {
        console.error('Error sending message:', error);
    }
});

// Helper functions
function scrollToBottom() {
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

// Modal Toggle Logic
const joinModal = document.getElementById('join-server-modal');
const modalChoices = document.getElementById('modal-choices');
const createContainer = document.getElementById('create-server-form-container');
const joinContainer = document.getElementById('join-server-form-container');

const addServerBtn = document.getElementById('add-server-btn');
const joinServerForm = document.getElementById('join-server-form');
const createServerForm = document.getElementById('create-server-form');
const inviteInput = document.getElementById('join-invite-code');
const serverNameInput = document.getElementById('create-server-name');

if (addServerBtn) {
    addServerBtn.addEventListener('click', () => {
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
    serverNameInput.focus();
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
                window.location.href = `index.php?server_id=${data.server_id}`;
            } else {
                alert(data.error);
            }
        } catch (error) {
            console.error('Error joining server:', error);
        }
    });
}

// Create Server Logic
if (createServerForm) {
    createServerForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const serverName = serverNameInput.value.trim();
        if (!serverName) return;

        const formData = new FormData();
        formData.append('name', serverName);

        try {
            const response = await fetch('api/create_server.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.success) {
                window.location.href = `index.php?server_id=${data.server_id}&channel_id=${data.channel_id}`;
            } else {
                alert(data.error);
            }
        } catch (error) {
            console.error('Error creating server:', error);
        }
    });
}

function escapeHTML(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleString([], { hour: '2-digit', minute: '2-digit', hour12: true });
}

// Initial fetch and polling
if (currentChannelId) {
    fetchMessages();
    setInterval(fetchMessages, 2000); // Poll every 2 seconds
} else if (!currentServerId) {
    fetchFriends();
    setInterval(fetchFriends, 5000); // Poll friends every 5 seconds
}

// Tab state
let currentTab = 'online';
let cachedFriendsData = { friends: [], pending: [], blocked: [] };

// Friends System Logic
async function fetchFriends() {
    if (currentServerId) return;

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
            <a href="#" class="channel-item">
                <div class="user-avatar-small" style="width: 32px; height: 32px; background-image: url('assets/img/${f.avatar || 'default_avatar.png'}'); background-size: cover;">
                    <div class="status-indicator" style="background-color: ${f.status === 'online' ? '#23a55a' : '#949ba4'}"></div>
                    ${!f.avatar || f.avatar === 'default_avatar.png' ? `<span style="display:flex; justify-content:center; align-items:center; height:100%; color:white; font-size: 12px; font-weight:bold;">${f.username[0].toUpperCase()}</span>` : ''}
                </div>
                <span style="margin-left: 8px;">${escapeHTML(f.username)}</span>
            </a>
        `).join('') : '<div style="padding: 8px 16px; color: var(--text-muted); font-size: 12px;">No friends yet.</div>';
    }

    // Render Main Friends List (filtered by active tab)
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
                            ${!p.avatar || p.avatar === 'default_avatar.png' ? `<span style="display:flex; justify-content:center; align-items:center; height:100%; color:white; font-weight:bold;">${p.username[0].toUpperCase()}</span>` : ''}
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
                            ${!b.avatar || b.avatar === 'default_avatar.png' ? `<span style="display:flex; justify-content:center; align-items:center; height:100%; color:white; font-weight:bold;">${b.username[0].toUpperCase()}</span>` : ''}
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
                        ${!f.avatar || f.avatar === 'default_avatar.png' ? `<span style="display:flex; justify-content:center; align-items:center; height:100%; color:white; font-weight:bold;">${f.username[0].toUpperCase()}</span>` : ''}
                    </div>
                    <div>
                        <div class="message-username">${escapeHTML(f.username)}</div>
                        <div class="card-status">${f.status.charAt(0).toUpperCase() + f.status.slice(1)}</div>
                    </div>
                </div>
                <div class="friend-actions">
                    <div class="action-btn" title="Message"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg></div>
                    <div class="action-btn" title="More"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"></circle><circle cx="12" cy="5" r="1"></circle><circle cx="12" cy="19" r="1"></circle></svg></div>
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
                            ${!p.avatar || p.avatar === 'default_avatar.png' ? `<span style="display:flex; justify-content:center; align-items:center; height:100%; color:white; font-weight:bold;">${p.username[0].toUpperCase()}</span>` : ''}
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
                        ${!of.avatar || of.avatar === 'default_avatar.png' ? `<span style="display:flex; justify-content:center; align-items:center; height:100%; color:white; font-size: 10px; font-weight:bold;">${of.username[0].toUpperCase()}</span>` : ''}
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

