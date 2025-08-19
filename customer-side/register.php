<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');
$customer_type = $_POST['customer_type'] ?? 'retail';
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Validate required fields
if (empty($name) || empty($email) || empty($phone) || empty($address) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

// Validate password
if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']);
    exit();
}

if ($password !== $confirm_password) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
    exit();
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit();
}

// Validate customer type
$allowed_types = ['retail', 'wholesale', 'corporate', 'government'];
if (!in_array($customer_type, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid customer type']);
    exit();
}

try {
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM Customer WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'An account with this email already exists']);
        exit();
    }
    
    // Generate password hash from user's password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new customer
    $stmt = $pdo->prepare("
        INSERT INTO Customer (name, email, phone, address, customer_type, password_hash, status)
        VALUES (?, ?, ?, ?, ?, ?, 'active')
    ");
    
    $stmt->execute([$name, $email, $phone, $address, $customer_type, $password_hash]);
    $customer_id = $pdo->lastInsertId();
    
    // Set session for auto-login
    $_SESSION['customer_id'] = $customer_id;
    $_SESSION['customer_name'] = $name;
    $_SESSION['customer_email'] = $email;
    
    echo json_encode([
        'success' => true,
        'message' => 'Account created successfully! You are now logged in.',
        'customer' => [
            'id' => $customer_id,
            'name' => $name,
            'email' => $email
        ]
    ]);
    
} catch (PDOException $e) {
    if ($e->getCode() == 23000) { // Duplicate entry
        echo json_encode(['success' => false, 'message' => 'An account with this email already exists']);
    } else {
        error_log("Customer registration error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred during registration']);
    }
} catch (Exception $e) {
    error_log("Customer registration error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred during registration']);
}
?>
