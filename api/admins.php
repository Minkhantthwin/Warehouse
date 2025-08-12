<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                getAdmins();
            } elseif ($action === 'get' && isset($_GET['id'])) {
                getAdmin($_GET['id']);
            } elseif ($action === 'stats') {
                getAdminStats();
            }
            break;
            
        case 'POST':
            if ($action === 'create') {
                createAdmin();
            } elseif ($action === 'update') {
                updateAdmin();
            } elseif ($action === 'delete') {
                deleteAdmin();
            } elseif ($action === 'bulk') {
                bulkActions();
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

function getAdmins() {
    global $pdo;
    
    // Get filter parameters
    $search = $_GET['search'] ?? '';
    $role = $_GET['role'] ?? '';
    $status = $_GET['status'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 10);
    $offset = ($page - 1) * $limit;
    
    // Build query with filters
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if ($search) {
        $whereClause .= " AND (a.name LIKE :search OR a.email LIKE :search OR a.phone LIKE :search)";
        $params['search'] = "%$search%";
    }
    
    if ($role) {
        $whereClause .= " AND a.role = :role";
        $params['role'] = $role;
    }
    
    if ($status) {
        $whereClause .= " AND a.status = :status";
        $params['status'] = $status;
    }
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM Admin a $whereClause";
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];
    
    // Get admins with pagination
    $query = "
        SELECT 
            a.*,
            COALESCE(emp_count.employee_count, 0) as managed_employees
        FROM Admin a
        LEFT JOIN (
            SELECT 
                admin_id,
                COUNT(*) as employee_count
            FROM Employee 
            GROUP BY admin_id
        ) emp_count ON a.id = emp_count.admin_id
        $whereClause
        ORDER BY a.created_at DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $admins = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $admins,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($total / $limit),
            'total_records' => $total,
            'limit' => $limit
        ]
    ]);
}

function getAdmin($id) {
    global $pdo;
    
    $query = "
        SELECT 
            a.*,
            COUNT(e.id) as managed_employees,
            MAX(a.last_login) as last_login_date
        FROM Admin a
        LEFT JOIN Employee e ON a.id = e.admin_id
        WHERE a.id = :id
        GROUP BY a.id
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['id' => $id]);
    $admin = $stmt->fetch();
    
    if ($admin) {
        // Get managed employees
        $employeesQuery = "
            SELECT 
                e.id,
                e.name,
                e.email,
                e.role,
                e.created_at
            FROM Employee e
            WHERE e.admin_id = :id
            ORDER BY e.created_at DESC
            LIMIT 10
        ";
        
        $employeesStmt = $pdo->prepare($employeesQuery);
        $employeesStmt->execute(['id' => $id]);
        $admin['managed_employees_list'] = $employeesStmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $admin]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Admin not found']);
    }
}

function getAdminStats() {
    global $pdo;
    
    $stats = [];
    
    // Total admins
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM Admin");
    $stats['total_admins'] = $stmt->fetch()['total'];
    
    // Active admins
    $stmt = $pdo->query("SELECT COUNT(*) as active FROM Admin WHERE status = 'active'");
    $stats['active_admins'] = $stmt->fetch()['active'];
    
    // Super admins
    $stmt = $pdo->query("SELECT COUNT(*) as super_admins FROM Admin WHERE role = 'super-admin'");
    $stats['super_admins'] = $stmt->fetch()['super_admins'];
    
    // Recently added (last 30 days)
    $stmt = $pdo->query("SELECT COUNT(*) as recent FROM Admin WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats['recent_admins'] = $stmt->fetch()['recent'];
    
    echo json_encode(['success' => true, 'data' => $stats]);
}

function createAdmin() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['name', 'email', 'password'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field '$field' is required"]);
            return;
        }
    }
    
    // Validate password confirmation
    if ($input['password'] !== $input['confirm_password']) {
        http_response_code(400);
        echo json_encode(['error' => 'Passwords do not match']);
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
        $query = "
            INSERT INTO Admin (
                name, email, password_hash, phone, role, status, permissions, last_login
            ) VALUES (
                :name, :email, :password_hash, :phone, :role, :status, :permissions, NULL
            )
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'name' => $input['name'],
            'email' => $input['email'],
            'password_hash' => password_hash($input['password'], PASSWORD_DEFAULT),
            'phone' => $input['phone'] ?? null,
            'role' => $input['role'] ?? 'admin',
            'status' => 'active',
            'permissions' => json_encode($input['permissions'] ?? [])
        ]);
        
        $adminId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Admin created successfully',
            'data' => ['id' => $adminId]
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create admin: ' . $e->getMessage()]);
    }
}

function updateAdmin() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Admin ID is required']);
        return;
    }
    
    try {
        // Build dynamic query based on provided fields
        $fields = [];
        $params = ['id' => $id];
        
        if (isset($input['name'])) {
            $fields[] = "name = :name";
            $params['name'] = $input['name'];
        }
        
        if (isset($input['email'])) {
            // Check if new email already exists (excluding current admin)
            $stmt = $pdo->prepare("SELECT id FROM Admin WHERE email = :email AND id != :id");
            $stmt->execute(['email' => $input['email'], 'id' => $id]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'Email already exists']);
                return;
            }
            $fields[] = "email = :email";
            $params['email'] = $input['email'];
        }
        
        if (isset($input['phone'])) {
            $fields[] = "phone = :phone";
            $params['phone'] = $input['phone'];
        }
        
        if (isset($input['role'])) {
            $fields[] = "role = :role";
            $params['role'] = $input['role'];
        }
        
        if (isset($input['status'])) {
            $fields[] = "status = :status";
            $params['status'] = $input['status'];
        }
        
        if (isset($input['permissions'])) {
            $fields[] = "permissions = :permissions";
            $params['permissions'] = json_encode($input['permissions']);
        }
        
        // Update password if provided
        if (!empty($input['password'])) {
            if ($input['password'] !== $input['confirm_password']) {
                http_response_code(400);
                echo json_encode(['error' => 'Passwords do not match']);
                return;
            }
            $fields[] = "password_hash = :password_hash";
            $params['password_hash'] = password_hash($input['password'], PASSWORD_DEFAULT);
        }
        
        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            return;
        }
        
        $query = "UPDATE Admin SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        echo json_encode([
            'success' => true,
            'message' => 'Admin updated successfully'
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update admin: ' . $e->getMessage()]);
    }
}

function deleteAdmin() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Admin ID is required']);
        return;
    }
    
    try {
        // Check if admin has managed employees
        $stmt = $pdo->prepare("SELECT COUNT(*) as employee_count FROM Employee WHERE admin_id = :id");
        $stmt->execute(['id' => $id]);
        $employeeCount = $stmt->fetch()['employee_count'];
        
        if ($employeeCount > 0) {
            http_response_code(400);
            echo json_encode(['error' => "Cannot delete admin with $employeeCount managed employees"]);
            return;
        }
        
        // Check if this is the last super admin
        $stmt = $pdo->prepare("SELECT role FROM Admin WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $admin = $stmt->fetch();
        
        if ($admin && $admin['role'] === 'super-admin') {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM Admin WHERE role = 'super-admin'");
            $superAdminCount = $stmt->fetch()['count'];
            
            if ($superAdminCount <= 1) {
                http_response_code(400);
                echo json_encode(['error' => 'Cannot delete the last super admin']);
                return;
            }
        }
        
        $stmt = $pdo->prepare("DELETE FROM Admin WHERE id = :id");
        $stmt->execute(['id' => $id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Admin deleted successfully'
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete admin: ' . $e->getMessage()]);
    }
}

function bulkActions() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $ids = $input['ids'] ?? [];
    
    if (empty($ids)) {
        http_response_code(400);
        echo json_encode(['error' => 'No admin IDs provided']);
        return;
    }
    
    try {
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        
        switch ($action) {
            case 'activate':
                $stmt = $pdo->prepare("UPDATE Admin SET status = 'active' WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                break;
                
            case 'deactivate':
                // Check if any of the selected admins are the last super admin
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM Admin WHERE role = 'super-admin' AND id NOT IN ($placeholders)");
                $stmt->execute($ids);
                $remainingSuperAdmins = $stmt->fetch()['count'];
                
                if ($remainingSuperAdmins < 1) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Cannot deactivate all super admins']);
                    return;
                }
                
                $stmt = $pdo->prepare("UPDATE Admin SET status = 'inactive' WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                break;
                
            case 'reset-password':
                // Generate temporary passwords and send email (simplified version)
                $tempPassword = bin2hex(random_bytes(8));
                $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("UPDATE Admin SET password_hash = ? WHERE id IN ($placeholders)");
                $stmt->execute(array_merge([$hashedPassword], $ids));
                
                // In a real application, you would send emails with the temporary password
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid bulk action']);
                return;
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Bulk action '$action' completed successfully"
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to perform bulk action: ' . $e->getMessage()]);
    }
}
?>
