<?php
require_once '../includes/config.php';
session_start();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'POST':
            if ($action === 'login') {
                loginAdmin();
            } elseif ($action === 'register') {
                registerAdmin();
            } elseif ($action === 'logout') {
                logoutAdmin();
            }
            break;
            
        case 'GET':
            if ($action === 'check') {
                checkAuthStatus();
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function loginAdmin() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($input['email']) || empty($input['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Email and password are required']);
        return;
    }
    
    try {
        // Get admin by email
        $stmt = $pdo->prepare("SELECT * FROM Admin WHERE email = :email AND status = 'active'");
        $stmt->execute(['email' => $input['email']]);
        $admin = $stmt->fetch();
        
        if (!$admin) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
            return;
        }
        
        // Verify password
        if (!password_verify($input['password'], $admin['password_hash'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
            return;
        }
        
        // Update last login
        $updateStmt = $pdo->prepare("UPDATE Admin SET last_login = NOW() WHERE id = :id");
        $updateStmt->execute(['id' => $admin['id']]);
        
        // Create session
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['admin_role'] = $admin['role'];
        $_SESSION['admin_permissions'] = $admin['permissions'] ? json_decode($admin['permissions'], true) : [];
        $_SESSION['login_time'] = time();
        
        // Set remember me cookie if requested
        if (!empty($input['remember-me'])) {
            $token = bin2hex(random_bytes(32));
            setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true); // 30 days
            
            // Store token in database (you might want to create a separate table for this)
            $tokenStmt = $pdo->prepare("UPDATE Admin SET remember_token = :token WHERE id = :id");
            $tokenStmt->execute(['token' => password_hash($token, PASSWORD_DEFAULT), 'id' => $admin['id']]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $admin['id'],
                'name' => $admin['name'],
                'email' => $admin['email'],
                'role' => $admin['role']
            ]
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Login failed: ' . $e->getMessage()]);
    }
}

function registerAdmin() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['name', 'email', 'password', 'confirm-password', 'role'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field '$field' is required"]);
            return;
        }
    }
    
    // Validate password confirmation
    if ($input['password'] !== $input['confirm-password']) {
        http_response_code(400);
        echo json_encode(['error' => 'Passwords do not match']);
        return;
    }
    
    // Validate password strength
    if (strlen($input['password']) < 8) {
        http_response_code(400);
        echo json_encode(['error' => 'Password must be at least 8 characters long']);
        return;
    }
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM Admin WHERE email = :email");
    $stmt->execute(['email' => $input['email']]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'Email already exists']);
        return;
    }
    
    try {
        // Set default permissions based on role
        $defaultPermissions = [
            'admin' => ['inventory_management', 'customer_management', 'employee_management'],
            'super-admin' => ['user_management', 'inventory_management', 'customer_management', 'employee_management', 'system_settings', 'reports']
        ];
        
        $permissions = $defaultPermissions[$input['role']] ?? $defaultPermissions['admin'];
        
        $query = "
            INSERT INTO Admin (
                name, email, password_hash, phone, role, status, permissions
            ) VALUES (
                :name, :email, :password_hash, :phone, :role, :status, :permissions
            )
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'name' => $input['name'],
            'email' => $input['email'],
            'password_hash' => password_hash($input['password'], PASSWORD_DEFAULT),
            'phone' => $input['phone'] ?? null,
            'role' => $input['role'],
            'status' => 'active', // You might want to set this to 'pending' for approval workflow
            'permissions' => json_encode($permissions)
        ]);
        
        $adminId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful! You can now login.',
            'data' => ['id' => $adminId]
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Registration failed: ' . $e->getMessage()]);
    }
}

function logoutAdmin() {
    // Clear remember me cookie if it exists
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }
    
    // Destroy session
    session_destroy();
    
    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
}

function checkAuthStatus() {
    if (isset($_SESSION['admin_id'])) {
        echo json_encode([
            'authenticated' => true,
            'user' => [
                'id' => $_SESSION['admin_id'],
                'name' => $_SESSION['admin_name'],
                'email' => $_SESSION['admin_email'],
                'role' => $_SESSION['admin_role'],
                'permissions' => $_SESSION['admin_permissions']
            ]
        ]);
    } else {
        echo json_encode(['authenticated' => false]);
    }
}
?>
