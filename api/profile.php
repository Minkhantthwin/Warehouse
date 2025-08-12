<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

session_start();

// Require login
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit();
}

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            getProfile();
            break;
            
        case 'POST':
            updateProfile();
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

function getProfile() {
    global $pdo;
    
    $adminId = $_SESSION['admin_id'];
    
    try {
        $stmt = $pdo->prepare("SELECT id, name, email, phone, role, status, created_at, last_login FROM Admin WHERE id = :id");
        $stmt->execute(['id' => $adminId]);
        $admin = $stmt->fetch();
        
        if ($admin) {
            echo json_encode([
                'success' => true,
                'data' => $admin
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Profile not found']);
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch profile: ' . $e->getMessage()]);
    }
}

function updateProfile() {
    global $pdo;
    
    $adminId = $_SESSION['admin_id'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input data']);
        return;
    }
    
    try {
        // Start building the update query
        $updateFields = [];
        $params = ['id' => $adminId];
        
        // Update basic profile information
        if (isset($input['name']) && !empty($input['name'])) {
            $updateFields[] = "name = :name";
            $params['name'] = trim($input['name']);
        }
        
        if (isset($input['phone'])) {
            $updateFields[] = "phone = :phone";
            $params['phone'] = !empty($input['phone']) ? trim($input['phone']) : null;
        }
        
        // Handle password change
        if (!empty($input['new_password'])) {
            if (empty($input['current_password'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Current password is required']);
                return;
            }
            
            // Verify current password
            $stmt = $pdo->prepare("SELECT password_hash FROM Admin WHERE id = :id");
            $stmt->execute(['id' => $adminId]);
            $currentAdmin = $stmt->fetch();
            
            if (!$currentAdmin || !password_verify($input['current_password'], $currentAdmin['password_hash'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Current password is incorrect']);
                return;
            }
            
            // Validate new password
            if (strlen($input['new_password']) < 8) {
                http_response_code(400);
                echo json_encode(['error' => 'New password must be at least 8 characters long']);
                return;
            }
            
            $updateFields[] = "password_hash = :password_hash";
            $params['password_hash'] = password_hash($input['new_password'], PASSWORD_DEFAULT);
        }
        
        if (empty($updateFields)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            return;
        }
        
        // Add updated_at timestamp
        $updateFields[] = "updated_at = NOW()";
        
        // Execute update
        $query = "UPDATE Admin SET " . implode(', ', $updateFields) . " WHERE id = :id";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        // Update session data if name was changed
        if (isset($params['name'])) {
            $_SESSION['admin_name'] = $params['name'];
        }
        
        // Get updated profile data
        $stmt = $pdo->prepare("SELECT id, name, email, phone, role, status FROM Admin WHERE id = :id");
        $stmt->execute(['id' => $adminId]);
        $updatedAdmin = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $updatedAdmin
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update profile: ' . $e->getMessage()]);
    }
}
?>
