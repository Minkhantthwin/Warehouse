<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']);

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit();
}

try {
    // Check if customer exists
    $stmt = $pdo->prepare("SELECT * FROM Customer WHERE email = ? AND status = 'active'");
    $stmt->execute([$email]);
    $customer = $stmt->fetch();
    
    if (!$customer) {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        exit();
    }
    
    // Verify password
    if (empty($customer['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Please contact support to set up your password']);
        exit();
    }
    
    if (!password_verify($password, $customer['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        exit();
    }
    
    // Set session
    $_SESSION['customer_id'] = $customer['id'];
    $_SESSION['customer_name'] = $customer['name'];
    $_SESSION['customer_email'] = $customer['email'];
    
    // Handle remember me
    if ($remember) {
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        // Store remember token in database (you'd need to add this column)
        // For now, we'll just set a longer session
        session_set_cookie_params(2592000); // 30 days
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Login successful',
        'customer' => [
            'id' => $customer['id'],
            'name' => $customer['name'],
            'email' => $customer['email']
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Customer login error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred during login']);
}
?>
