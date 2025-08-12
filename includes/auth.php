<?php
// Session utility functions for authentication
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function getLoggedInAdmin() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['admin_id'],
        'name' => $_SESSION['admin_name'],
        'email' => $_SESSION['admin_email'],
        'role' => $_SESSION['admin_role'],
        'permissions' => $_SESSION['admin_permissions'] ?? []
    ];
}

function hasPermission($permission) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $permissions = $_SESSION['admin_permissions'] ?? [];
    return in_array($permission, $permissions) || $_SESSION['admin_role'] === 'super-admin';
}

function redirectIfLoggedIn() {
    if (isLoggedIn()) {
        header('Location: index.php');
        exit();
    }
}

function logout() {
    // Clear remember me cookie if it exists
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }
    
    // Destroy session
    session_destroy();
    header('Location: login.php');
    exit();
}

// Auto-login with remember me token
function checkRememberMe() {
    global $pdo;
    
    if (!isLoggedIn() && isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM Admin WHERE remember_token IS NOT NULL AND status = 'active'");
            $stmt->execute();
            $admins = $stmt->fetchAll();
            
            foreach ($admins as $admin) {
                if (password_verify($token, $admin['remember_token'])) {
                    // Recreate session
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_name'] = $admin['name'];
                    $_SESSION['admin_email'] = $admin['email'];
                    $_SESSION['admin_role'] = $admin['role'];
                    $_SESSION['admin_permissions'] = $admin['permissions'] ? json_decode($admin['permissions'], true) : [];
                    $_SESSION['login_time'] = time();
                    
                    // Update last login
                    $updateStmt = $pdo->prepare("UPDATE Admin SET last_login = NOW() WHERE id = :id");
                    $updateStmt->execute(['id' => $admin['id']]);
                    break;
                }
            }
        } catch (PDOException $e) {
            // Handle error silently for auto-login
        }
    }
}
?>
