<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$configPath = __DIR__ . "/../../api/config.php";
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server configuration missing.']);
    exit;
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
    
    $pending = $pdo->query("SELECT * FROM join_requests WHERE status = 'pending' ORDER BY created_at DESC")->fetchAll();
    $members = $pdo->query("SELECT * FROM members ORDER BY joined_at DESC")->fetchAll();
    $rejected_count = $pdo->query("SELECT COUNT(*) FROM join_requests WHERE status = 'rejected'")->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_members' => count($members),
            'pending_requests' => count($pending),
            'rejected_applications' => (int)$rejected_count
        ],
        'pending' => $pending,
        'members' => $members
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
