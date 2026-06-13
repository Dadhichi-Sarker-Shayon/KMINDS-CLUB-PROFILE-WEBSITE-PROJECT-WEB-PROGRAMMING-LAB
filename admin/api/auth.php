<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $pass = $data['password'] ?? '';
    
    // In a real application, use a hashed password stored securely
    if ($pass === 'admin') {
        $_SESSION['admin_logged_in'] = true;
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid password.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

// GET method to check auth status
echo json_encode(['logged_in' => isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true]);
