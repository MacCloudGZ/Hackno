<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$db_host = 'localhost';
$db_name = 'hackno';
$db_user = 'root';
$db_pass = '';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

$user_ip = $_SERVER['REMOTE_ADDR'];
$response = ['username' => null, 'taken' => false];

$stmt = $conn->prepare("SELECT id FROM ip_logins WHERE ip_address = ?");
$stmt->bind_param("s", $user_ip);
$stmt->execute();
$stmt->bind_result($ip_id);
if ($stmt->fetch()) {
    $stmt->close();
    $stmt = $conn->prepare("SELECT user_name FROM usernames WHERE id = ?");
    $stmt->bind_param("i", $ip_id);
    $stmt->execute();
    $stmt->bind_result($existing_username);
    if ($stmt->fetch()) {
        $response['username'] = $existing_username;
    }
    $stmt->close();
}

// Check if a username is passed for validation
if (isset($_GET['check']) && !empty($_GET['check'])) {
    $check_username = trim($_GET['check']);
    $stmt = $conn->prepare("SELECT id FROM usernames WHERE user_name = ?");
    $stmt->bind_param("s", $check_username);
    $stmt->execute();
    $stmt->bind_result($found_id);
    if ($stmt->fetch()) {
        if (!isset($ip_id) || $ip_id != $found_id) {
            $response['taken'] = true;
        }
    }
    $stmt->close();
}

$conn->close();
header('Content-Type: application/json');
echo json_encode($response);
?>
