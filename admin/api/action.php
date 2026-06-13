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
    
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    $id = (int)($data['id'] ?? 0);
    
    if (!$id || !$action) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
        exit;
    }

    if ($action === 'approve') {
        $stmt = $pdo->prepare("SELECT * FROM join_requests WHERE id = :id AND status = 'pending'");
        $stmt->execute([':id' => $id]);
        $request = $stmt->fetch();
        
        if ($request) {
            $pdo->beginTransaction();
            $ins = $pdo->prepare("INSERT INTO members (name, email, phone, department, roll, section) VALUES (:name, :email, :phone, :department, :roll, :section)");
            $ins->execute([
                ':name' => $request['name'],
                ':email' => $request['email'],
                ':phone' => $request['phone'],
                ':department' => $request['department'],
                ':roll' => $request['roll'],
                ':section' => $request['section']
            ]);
            
            $upd = $pdo->prepare("UPDATE join_requests SET status = 'approved' WHERE id = :id");
            $upd->execute([':id' => $id]);
            $pdo->commit();
            
            echo json_encode(['success' => true, 'message' => 'Member approved!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Request not found or already processed.']);
        }
    } elseif ($action === 'reject') {
        $upd = $pdo->prepare("UPDATE join_requests SET status = 'rejected' WHERE id = :id");
        $upd->execute([':id' => $id]);
        echo json_encode(['success' => true, 'message' => 'Request rejected.']);
    } elseif ($action === 'delete') {
        $del = $pdo->prepare("DELETE FROM members WHERE id = :id");
        $del->execute([':id' => $id]);
        echo json_encode(['success' => true, 'message' => 'Member deleted.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Action failed due to server error.']);
}
