<?php

declare(strict_types=1);

header("Content-Type: application/json; charset=utf-8");

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(405, ["success" => false, "message" => "Method not allowed."]);
}

$input = [
    "name" => trim((string)($_POST["name"] ?? "")),
    "email" => trim((string)($_POST["email"] ?? "")),
    "phone" => trim((string)($_POST["phone"] ?? "")),
    "department" => trim((string)($_POST["department"] ?? "")),
    "roll" => trim((string)($_POST["roll"] ?? "")),
    "section" => trim((string)($_POST["section"] ?? ""))
];

$errors = [];
foreach ($input as $key => $value) {
    if ($value === "") {
        $errors[$key] = "Required";
    }
}

if ($input["email"] !== "" && !filter_var($input["email"], FILTER_VALIDATE_EMAIL)) {
    $errors["email"] = "Invalid email";
}

if ($errors) {
    respond(422, ["success" => false, "message" => "Please fill all required fields.", "errors" => $errors]);
}

$configPath = __DIR__ . "/config.php";
if (!file_exists($configPath)) {
    respond(500, ["success" => false, "message" => "Server configuration missing."]);
}

$config = require $configPath;

try {
    $db = $config["db"];
    $port = isset($db["port"]) ? (int)$db["port"] : 3306;
    $dsn = "mysql:host={$db['host']};port={$port};dbname={$db['name']};charset={$db['charset']}";
    $pdo = new PDO($dsn, $db["user"], $db["pass"], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $stmt = $pdo->prepare(
        "INSERT INTO join_requests (name, email, phone, department, roll, section)\n" .
        "VALUES (:name, :email, :phone, :department, :roll, :section)"
    );
    $stmt->execute([
        ":name" => $input["name"],
        ":email" => $input["email"],
        ":phone" => $input["phone"],
        ":department" => $input["department"],
        ":roll" => $input["roll"],
        ":section" => $input["section"]
    ]);
} catch (Throwable $error) {
    respond(500, ["success" => false, "message" => "Database error. Please try again later."]);
}

$autoload = __DIR__ . "/../vendor/autoload.php";
if (!file_exists($autoload)) {
    respond(500, [
        "success" => false,
        "message" => "PHPMailer is not installed. Run composer install and try again."
    ]);
}

require_once $autoload;

$mailConfig = $config["mail"];

try {
    $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = $mailConfig["host"];
    $mailer->SMTPAuth = true;
    $mailer->Username = $mailConfig["username"];
    $mailer->Password = $mailConfig["password"];
    $mailer->SMTPSecure = $mailConfig["encryption"];
    $mailer->Port = (int)$mailConfig["port"];

    $mailer->setFrom($mailConfig["from_email"], $mailConfig["from_name"]);
    $mailer->addAddress($input["email"], $input["name"]);
    $mailer->Subject = "K-MiNDS: Application received";
    $mailer->Body = "Hi {$input['name']},\n\n" .
        "We have received your application to join K-MiNDS.\n" .
        "We will contact you soon with the next steps.\n\n" .
        "Thanks,\nK-MiNDS Team";
    $mailer->send();

    $adminMailer = new PHPMailer\PHPMailer\PHPMailer(true);
    $adminMailer->isSMTP();
    $adminMailer->Host = $mailConfig["host"];
    $adminMailer->SMTPAuth = true;
    $adminMailer->Username = $mailConfig["username"];
    $adminMailer->Password = $mailConfig["password"];
    $adminMailer->SMTPSecure = $mailConfig["encryption"];
    $adminMailer->Port = (int)$mailConfig["port"];

    $adminMailer->setFrom($mailConfig["from_email"], $mailConfig["from_name"]);
    $adminMailer->addAddress($mailConfig["admin_email"], "K-MiNDS Admin");
    $adminMailer->Subject = "New K-MiNDS Join Request";
    $adminMailer->Body =
        "New application details:\n\n" .
        "Name: {$input['name']}\n" .
        "Email: {$input['email']}\n" .
        "Phone: {$input['phone']}\n" .
        "Department: {$input['department']}\n" .
        "Roll: {$input['roll']}\n" .
        "Section: {$input['section']}\n";
    $adminMailer->send();
} catch (Throwable $error) {
    respond(500, ["success" => false, "message" => "Email failed. Check SMTP settings."]);
}

respond(200, ["success" => true, "message" => "Application submitted."]);
