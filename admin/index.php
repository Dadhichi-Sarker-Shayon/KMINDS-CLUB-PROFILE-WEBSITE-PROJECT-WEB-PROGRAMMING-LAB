<?php
session_start();

// Simple hardcoded password for admin panel protection
// In a real production app, use hashed passwords in the database.
$admin_password = 'admin';

if (isset($_POST['password'])) {
    if ($_POST['password'] === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid password.";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

// -------------------------------------------------------------------
// Login Page View
// -------------------------------------------------------------------
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K-MiNDS Admin Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Space Grotesk', sans-serif; }
        body { 
            display: flex; justify-content: center; align-items: center; 
            min-height: 100vh; margin: 0; background: #0f172a; color: #fff;
        }
        .login-box { 
            background: #1e293b; padding: 2.5rem; border-radius: 12px; 
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.5); 
            text-align: center; width: 100%; max-width: 400px;
        }
        .login-box h2 { margin-top: 0; margin-bottom: 0.5rem; color: #38bdf8; font-size: 1.8rem; }
        .login-box p { color: #94a3b8; margin-bottom: 2rem; }
        input[type="password"] { 
            padding: 0.8rem 1rem; width: 100%; margin-bottom: 1.5rem; 
            background: #0f172a; border: 1px solid #334155; color: white;
            border-radius: 6px; font-size: 1rem; outline: none; transition: border 0.3s;
        }
        input[type="password"]:focus { border-color: #38bdf8; }
        button { 
            padding: 0.8rem 1rem; width: 100%; background: #0ea5e9; 
            color: white; border: none; border-radius: 6px; 
            font-size: 1rem; font-weight: 600; cursor: pointer; transition: background 0.3s;
        }
        button:hover { background: #0284c7; }
        .error { color: #ef4444; background: #fee2e2; padding: 0.5rem; border-radius: 4px; margin-bottom: 1rem; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>K-MiNDS</h2>
        <p>Admin Control Panel</p>
        <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>
        <form method="POST">
            <input type="password" name="password" placeholder="Enter Admin Password" required autofocus>
            <button type="submit">Unlock Dashboard</button>
        </form>
    </div>
</body>
</html>
<?php
    exit;
}

// -------------------------------------------------------------------
// Dashboard Controller
// -------------------------------------------------------------------

// Database Connection
$configPath = __DIR__ . "/../api/config.php";
if (!file_exists($configPath)) {
    die("Server configuration missing.");
}
$config = require $configPath;
$db = $config["db"];
$port = isset($db["port"]) ? (int)$db["port"] : 3306;
$dsn = "mysql:host={$db['host']};port={$port};dbname={$db['name']};charset={$db['charset']}";
try {
    $pdo = new PDO($dsn, $db["user"], $db["pass"], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Database connection failed.");
}

$msg = "";
$msgType = "";

// Handle Actions (Approve/Reject/Delete)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    
    if ($action === 'approve') {
        $stmt = $pdo->prepare("SELECT * FROM join_requests WHERE id = :id AND status = 'pending'");
        $stmt->execute([':id' => $id]);
        $request = $stmt->fetch();
        
        if ($request) {
            $pdo->beginTransaction();
            try {
                $insert_stmt = $pdo->prepare("INSERT INTO members (name, email, phone, department, roll, section) VALUES (:name, :email, :phone, :department, :roll, :section)");
                $insert_stmt->execute([
                    ':name' => $request['name'],
                    ':email' => $request['email'],
                    ':phone' => $request['phone'],
                    ':department' => $request['department'],
                    ':roll' => $request['roll'],
                    ':section' => $request['section']
                ]);
                
                $update_stmt = $pdo->prepare("UPDATE join_requests SET status = 'approved' WHERE id = :id");
                $update_stmt->execute([':id' => $id]);
                
                $pdo->commit();
                $msg = "Member {$request['name']} approved and added successfully.";
                $msgType = "success";
            } catch (Exception $e) {
                $pdo->rollBack();
                $msg = "Error approving member.";
                $msgType = "error";
            }
        }
    } elseif ($action === 'reject') {
        $update_stmt = $pdo->prepare("UPDATE join_requests SET status = 'rejected' WHERE id = :id");
        $update_stmt->execute([':id' => $id]);
        $msg = "Request rejected.";
        $msgType = "success";
    } elseif ($action === 'delete_member') {
        $delete_stmt = $pdo->prepare("DELETE FROM members WHERE id = :id");
        $delete_stmt->execute([':id' => $id]);
        $msg = "Member deleted successfully.";
        $msgType = "success";
    }
    
    // Redirect to clear URL action params
    header("Location: index.php?msg=" . urlencode($msg) . "&type=" . urlencode($msgType));
    exit;
}

if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
    $msgType = $_GET['type'] ?? 'success';
}

// Fetch stats and lists
$pending_requests = $pdo->query("SELECT * FROM join_requests WHERE status = 'pending' ORDER BY created_at DESC")->fetchAll();
$members = $pdo->query("SELECT * FROM members ORDER BY joined_at DESC")->fetchAll();

$total_members = count($members);
$total_pending = count($pending_requests);
$total_rejected = $pdo->query("SELECT COUNT(*) FROM join_requests WHERE status = 'rejected'")->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K-MiNDS Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0ea5e9;
            --primary-hover: #0284c7;
            --bg-color: #f1f5f9;
            --sidebar-bg: #0f172a;
            --card-bg: #ffffff;
            --text-main: #334155;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Space Grotesk', sans-serif; }
        body { background-color: var(--bg-color); color: var(--text-main); display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 260px; background: var(--sidebar-bg); color: white; display: flex; flex-direction: column; }
        .sidebar .brand { padding: 2rem; font-size: 1.5rem; font-weight: 700; color: #38bdf8; border-bottom: 1px solid #1e293b; }
        .sidebar nav { flex: 1; padding: 1rem 0; }
        .sidebar nav a { display: block; padding: 1rem 2rem; color: #cbd5e1; text-decoration: none; transition: 0.2s; }
        .sidebar nav a:hover, .sidebar nav a.active { background: #1e293b; color: white; border-left: 4px solid var(--primary); }
        .sidebar .logout { padding: 1rem 2rem; background: #dc2626; color: white; text-decoration: none; text-align: center; margin: 1rem; border-radius: 6px; }
        .sidebar .logout:hover { background: #b91c1c; }

        /* Main Content */
        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .header { background: white; padding: 1.5rem 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 1.5rem; font-weight: 600; margin: 0; }
        .content { padding: 2rem; overflow-y: auto; flex: 1; }

        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: var(--card-bg); padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .stat-card h3 { font-size: 0.9rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem; }
        .stat-card .value { font-size: 2.5rem; font-weight: 700; color: var(--primary); }

        /* Tables & Sections */
        .section-header { margin-bottom: 1rem; margin-top: 2rem; display: flex; justify-content: space-between; align-items: flex-end; }
        .section-header h2 { font-size: 1.2rem; font-weight: 600; }
        .table-container { background: var(--card-bg); border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); overflow-x: auto; margin-bottom: 2rem; }
        table { width: 100%; border-collapse: collapse; white-space: nowrap; }
        th, td { padding: 1rem 1.5rem; text-align: left; border-bottom: 1px solid var(--border-color); }
        th { background: #f8fafc; font-weight: 600; font-size: 0.9rem; color: var(--text-muted); text-transform: uppercase; }
        tr:hover td { background: #f1f5f9; }
        
        /* Badges & Buttons */
        .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 500; }
        .badge-pending { background: #fef3c7; color: #d97706; }
        .badge-approved { background: #d1fae5; color: #059669; }
        
        .btn { display: inline-flex; align-items: center; padding: 0.5rem 1rem; border-radius: 6px; font-size: 0.875rem; font-weight: 500; text-decoration: none; cursor: pointer; transition: 0.2s; border: none; }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #059669; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: #dc2626; }
        .actions { display: flex; gap: 0.5rem; }

        /* Alerts */
        .alert { padding: 1rem 1.5rem; border-radius: 6px; margin-bottom: 1.5rem; font-weight: 500; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #10b981; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #ef4444; }

        /* Responsive */
        @media (max-width: 768px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; flex-direction: row; align-items: center; justify-content: space-between; padding: 0; }
            .sidebar .brand { padding: 1rem; border-bottom: none; }
            .sidebar nav { display: none; }
            .sidebar .logout { margin: 0 1rem; padding: 0.5rem 1rem; }
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="brand">K-MiNDS Admin</div>
        <nav>
            <a href="#dashboard" class="active">Dashboard</a>
            <a href="#pending">Pending Requests</a>
            <a href="#members">Club Members</a>
        </nav>
        <a href="?logout=1" class="logout">Logout</a>
    </aside>

    <main class="main-content">
        <header class="header">
            <h1 id="dashboard">System Overview</h1>
            <div class="user-info">Logged in as Administrator</div>
        </header>

        <div class="content">
            <?php if ($msg): ?>
                <div class="alert alert-<?= $msgType === 'error' ? 'error' : 'success' ?>">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Members</h3>
                    <div class="value"><?= $total_members ?></div>
                </div>
                <div class="stat-card">
                    <h3>Pending Requests</h3>
                    <div class="value" style="color: var(--warning);"><?= $total_pending ?></div>
                </div>
                <div class="stat-card">
                    <h3>Rejected Applications</h3>
                    <div class="value" style="color: var(--text-muted);"><?= $total_rejected ?></div>
                </div>
            </div>

            <div class="section-header" id="pending">
                <h2>Pending Join Requests</h2>
            </div>
            
            <div class="table-container">
                <?php if ($total_pending > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Applicant Name</th>
                            <th>Contact Info</th>
                            <th>Academics</th>
                            <th>Status</th>
                            <th>Date Applied</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_requests as $req): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($req['name']) ?></strong>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($req['email']) ?></div>
                                <div style="color: var(--text-muted); font-size: 0.85rem;"><?= htmlspecialchars($req['phone']) ?></div>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($req['department']) ?></div>
                                <div style="color: var(--text-muted); font-size: 0.85rem;">
                                    ID: <?= htmlspecialchars($req['roll']) ?> | Sec: <?= htmlspecialchars($req['section']) ?>
                                </div>
                            </td>
                            <td><span class="badge badge-pending">Pending</span></td>
                            <td style="color: var(--text-muted); font-size: 0.9rem;">
                                <?= date('M d, Y h:i A', strtotime($req['created_at'])) ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="?action=approve&id=<?= $req['id'] ?>" class="btn btn-success" title="Approve">Approve</a>
                                    <a href="?action=reject&id=<?= $req['id'] ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to reject this request?');" title="Reject">Reject</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div style="padding: 2rem; text-align: center; color: var(--text-muted);">
                        No pending requests right now. You're all caught up!
                    </div>
                <?php endif; ?>
            </div>

            <div class="section-header" id="members">
                <h2>Approved Members</h2>
            </div>

            <div class="table-container">
                <?php if ($total_members > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Member Name</th>
                            <th>Contact Info</th>
                            <th>Academics</th>
                            <th>Status</th>
                            <th>Member Since</th>
                            <th>Administration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $mem): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($mem['name']) ?></strong>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($mem['email']) ?></div>
                                <div style="color: var(--text-muted); font-size: 0.85rem;"><?= htmlspecialchars($mem['phone']) ?></div>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($mem['department']) ?></div>
                                <div style="color: var(--text-muted); font-size: 0.85rem;">
                                    ID: <?= htmlspecialchars($mem['roll']) ?> | Sec: <?= htmlspecialchars($mem['section']) ?>
                                </div>
                            </td>
                            <td><span class="badge badge-approved">Active</span></td>
                            <td style="color: var(--text-muted); font-size: 0.9rem;">
                                <?= date('M d, Y', strtotime($mem['joined_at'])) ?>
                            </td>
                            <td>
                                <a href="?action=delete_member&id=<?= $mem['id'] ?>" class="btn btn-danger" onclick="return confirm('Are you absolutely sure you want to remove this member? This action cannot be undone.');">Remove</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div style="padding: 2rem; text-align: center; color: var(--text-muted);">
                        No active members yet. Approve some pending requests to build your club!
                    </div>
                <?php endif; ?>
            </div>

            <footer style="margin-top: 3rem; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                &copy; <?= date('Y') ?> K-MiNDS. Admin Dashboard System.
            </footer>
        </div>
    </main>
</body>
</html>