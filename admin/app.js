document.addEventListener('DOMContentLoaded', () => {
    checkAuth();

    // Login Form Submit
    document.getElementById('login-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const password = document.getElementById('admin-password').value;
        const msgDiv = document.getElementById('login-message');

        try {
            const res = await fetch('api/auth.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ password })
            });
            const data = await res.json();

            if (data.success) {
                showDashboard();
            } else {
                msgDiv.innerHTML = `<div class="alert alert-error" style="margin-bottom:1rem; padding:0.5rem; font-size:0.9rem;">${data.message || 'Login failed.'}</div>`;
            }
        } catch (err) {
            msgDiv.innerHTML = `<div class="alert alert-error" style="margin-bottom:1rem; padding:0.5rem; font-size:0.9rem;">Network error.</div>`;
        }
    });

    // Logout Button
    document.getElementById('logout-btn').addEventListener('click', async () => {
        await fetch('api/auth.php', { method: 'DELETE' });
        showLogin();
    });
});

async function checkAuth() {
    try {
        const res = await fetch('api/auth.php');
        const data = await res.json();
        
        if (data.logged_in) {
            showDashboard();
        } else {
            showLogin();
        }
    } catch (err) {
        showLogin();
    }
}

function showLogin() {
    document.getElementById('login-view').classList.remove('hidden');
    document.getElementById('dashboard-view').classList.add('hidden');
    document.getElementById('admin-password').value = '';
    document.getElementById('login-message').innerHTML = '';
}

function showDashboard() {
    document.getElementById('login-view').classList.add('hidden');
    document.getElementById('dashboard-view').classList.remove('hidden');
    loadDashboardData();
}

async function loadDashboardData() {
    try {
        const res = await fetch('api/data.php');
        if (res.status === 401) {
            showLogin();
            return;
        }
        const data = await res.json();
        if (data.success) {
            renderDashboard(data);
        } else {
            showAlert('error', data.message || 'Failed to load data.');
        }
    } catch (err) {
        showAlert('error', 'Network error loading dashboard.');
    }
}

function renderDashboard(data) {
    // Update Stats
    document.getElementById('stat-members').innerText = data.stats.total_members;
    document.getElementById('stat-pending').innerText = data.stats.pending_requests;
    document.getElementById('stat-rejected').innerText = data.stats.rejected_applications;

    // Render Pending Requests
    const pendingTbody = document.getElementById('pending-tbody');
    if (data.pending.length > 0) {
        pendingTbody.innerHTML = data.pending.map(req => `
            <tr>
                <td><strong>${escapeHTML(req.name)}</strong></td>
                <td>
                    <div>${escapeHTML(req.email)}</div>
                    <div style="color: var(--text-muted); font-size: 0.85rem;">${escapeHTML(req.phone)}</div>
                </td>
                <td>
                    <div>${escapeHTML(req.department)}</div>
                    <div style="color: var(--text-muted); font-size: 0.85rem;">
                        ID: ${escapeHTML(req.roll)} | Sec: ${escapeHTML(req.section)}
                    </div>
                </td>
                <td><span class="badge badge-pending">Pending</span></td>
                <td style="color: var(--text-muted); font-size: 0.9rem;">
                    ${formatDate(req.created_at)}
                </td>
                <td>
                    <div class="actions">
                        <button class="btn btn-success" onclick="handleAction('approve', ${req.id})">Approve</button>
                        <button class="btn btn-danger" onclick="handleAction('reject', ${req.id}, true)">Reject</button>
                    </div>
                </td>
            </tr>
        `).join('');
    } else {
        pendingTbody.innerHTML = `<tr><td colspan="6" class="empty-state">No pending requests right now. You're all caught up!</td></tr>`;
    }

    // Render Approved Members
    const membersTbody = document.getElementById('members-tbody');
    if (data.members.length > 0) {
        membersTbody.innerHTML = data.members.map(mem => `
            <tr>
                <td><strong>${escapeHTML(mem.name)}</strong></td>
                <td>
                    <div>${escapeHTML(mem.email)}</div>
                    <div style="color: var(--text-muted); font-size: 0.85rem;">${escapeHTML(mem.phone)}</div>
                </td>
                <td>
                    <div>${escapeHTML(mem.department)}</div>
                    <div style="color: var(--text-muted); font-size: 0.85rem;">
                        ID: ${escapeHTML(mem.roll)} | Sec: ${escapeHTML(mem.section)}
                    </div>
                </td>
                <td><span class="badge badge-approved">Active</span></td>
                <td style="color: var(--text-muted); font-size: 0.9rem;">
                    ${formatDate(mem.joined_at)}
                </td>
                <td>
                    <button class="btn btn-danger" onclick="handleAction('delete', ${mem.id}, true)">Remove</button>
                </td>
            </tr>
        `).join('');
    } else {
        membersTbody.innerHTML = `<tr><td colspan="6" class="empty-state">No active members yet. Approve some pending requests to build your club!</td></tr>`;
    }
}

async function handleAction(action, id, confirmAction = false) {
    if (confirmAction) {
        const title = action === 'reject' ? 'reject this request' : 'remove this member';
        if (!confirm(`Are you sure you want to ${title}?`)) {
            return;
        }
    }

    try {
        const res = await fetch('api/action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, id })
        });
        const data = await res.json();

        if (data.success) {
            showAlert('success', data.message);
            loadDashboardData(); // Refresh data
        } else {
            showAlert('error', data.message);
        }
    } catch (err) {
        showAlert('error', 'Network error.');
    }
}

function showAlert(type, message) {
    const container = document.getElementById('alert-container');
    container.innerHTML = `<div class="alert alert-${type}">${escapeHTML(message)}</div>`;
    setTimeout(() => {
        container.innerHTML = '';
    }, 4000);
}

function escapeHTML(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.innerText = str;
    return div.innerHTML;
}

function formatDate(dateString) {
    if (!dateString) return '';
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    const date = new Date(dateString);
    return date.toLocaleDateString(undefined, options);
}