<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check remember me token
checkRememberMe();

// Require login
requireLogin();

$currentAdmin = getLoggedInAdmin();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            getEmployees();
            break;
        case 'get':
            getEmployee($_GET['id']);
            break;
        case 'stats':
            getEmployeeStats();
            break;
        case 'create':
            createEmployee();
            break;
        case 'update':
            updateEmployee();
            break;
        case 'delete':
            deleteEmployee();
            break;
        case 'bulk':
            bulkActions();
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    error_log("Employees API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error occurred']);
}

function getEmployees() {
    global $pdo;
    
    // Get filter parameters
    $search = $_GET['search'] ?? '';
    $department = $_GET['department'] ?? '';
    $position = $_GET['position'] ?? '';
    $status = $_GET['status'] ?? '';
    $active_only = $_GET['active_only'] ?? false;
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 10);
    $offset = ($page - 1) * $limit;
    
    // Build query with filters
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if ($search) {
        $whereClause .= " AND (e.name LIKE :search OR e.email LIKE :search OR e.phone LIKE :search OR e.employee_id LIKE :search)";
        $params['search'] = "%$search%";
    }
    
    if ($department) {
        $whereClause .= " AND e.department = :department";
        $params['department'] = $department;
    }
    
    if ($position) {
        $whereClause .= " AND e.position = :position";
        $params['position'] = $position;
    }
    
    if ($status) {
        $whereClause .= " AND e.status = :status";
        $params['status'] = $status;
    }
    
    if ($active_only) {
        $whereClause .= " AND e.status = 'active'";
    }
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM Employee e $whereClause";
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];
    
    // Get employees with pagination
    $query = "
        SELECT 
            e.*,
            a.name as admin_name,
            COALESCE(br_count.total_processed, 0) as total_processed,
            COALESCE(br_count.active_tasks, 0) as active_tasks
        FROM Employee e
        LEFT JOIN Admin a ON e.admin_id = a.id
        LEFT JOIN (
            SELECT 
                processed_by,
                COUNT(*) as total_processed,
                SUM(CASE WHEN bt.transaction_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as active_tasks
            FROM Borrowing_Transaction bt
            GROUP BY processed_by
        ) br_count ON e.id = br_count.processed_by
        $whereClause
        ORDER BY e.created_at DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $employees = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $employees,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($total / $limit),
            'total_records' => $total,
            'limit' => $limit
        ]
    ]);
}

function getEmployee($id) {
    global $pdo;
    
    $query = "
        SELECT 
            e.*,
            a.name as admin_name,
            COUNT(bt.id) as total_transactions,
            MAX(bt.transaction_date) as last_activity_date
        FROM Employee e
        LEFT JOIN Admin a ON e.admin_id = a.id
        LEFT JOIN Borrowing_Transaction bt ON e.id = bt.processed_by
        WHERE e.id = :id
        GROUP BY e.id
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['id' => $id]);
    $employee = $stmt->fetch();
    
    if ($employee) {
        // Get recent transactions
        $transactionsQuery = "
            SELECT 
                bt.*,
                br.status as request_status,
                c.name as customer_name,
                l.name as location_name
            FROM Borrowing_Transaction bt
            LEFT JOIN Borrowing_Request br ON bt.borrowing_request_id = br.id
            LEFT JOIN Customer c ON br.customer_id = c.id
            LEFT JOIN Location l ON br.location_id = l.id
            WHERE bt.processed_by = :id
            ORDER BY bt.transaction_date DESC
            LIMIT 10
        ";
        
        $transactionsStmt = $pdo->prepare($transactionsQuery);
        $transactionsStmt->execute(['id' => $id]);
        $employee['recent_transactions'] = $transactionsStmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $employee]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Employee not found']);
    }
}

function getEmployeeStats() {
    global $pdo;
    
    $stats = [];
    
    // Total employees
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM Employee");
    $stats['total_employees'] = $stmt->fetch()['total'];
    
    // Active employees
    $stmt = $pdo->query("SELECT COUNT(*) as active FROM Employee WHERE status = 'active'");
    $stats['active_employees'] = $stmt->fetch()['active'];
    
    // On duty employees (those with recent activity)
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT e.id) as on_duty 
        FROM Employee e 
        INNER JOIN Borrowing_Transaction bt ON e.id = bt.processed_by 
        WHERE bt.transaction_date >= CURDATE()
    ");
    $stats['on_duty'] = $stmt->fetch()['on_duty'];
    
    // New employees this month
    $stmt = $pdo->query("
        SELECT COUNT(*) as new_employees 
        FROM Employee 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stats['new_this_month'] = $stmt->fetch()['new_employees'];
    
    echo json_encode(['success' => true, 'data' => $stats]);
}

function createEmployee() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['name', 'email', 'phone', 'department', 'position'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field '$field' is required"]);
            return;
        }
    }
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM Employee WHERE email = :email");
    $stmt->execute(['email' => $input['email']]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'Email already exists']);
        return;
    }
    
    try {
        // Generate employee ID
        $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(employee_id, 4) AS UNSIGNED)) as max_id FROM Employee WHERE employee_id LIKE 'EMP%'");
        $maxId = $stmt->fetch()['max_id'] ?? 0;
        $employeeId = 'EMP' . str_pad($maxId + 1, 4, '0', STR_PAD_LEFT);
        
        $query = "
            INSERT INTO Employee (
                employee_id, name, email, password_hash, phone, role, 
                department, position, hire_date, salary, shift, 
                address, emergency_contact, status, admin_id
            ) VALUES (
                :employee_id, :name, :email, :password_hash, :phone, :role,
                :department, :position, :hire_date, :salary, :shift,
                :address, :emergency_contact, :status, :admin_id
            )
        ";
        
        // Generate default password (employee can change later)
        $defaultPassword = 'employee123';
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'employee_id' => $employeeId,
            'name' => $input['name'],
            'email' => $input['email'],
            'password_hash' => password_hash($defaultPassword, PASSWORD_DEFAULT),
            'phone' => $input['phone'],
            'role' => $input['position'], // Using position as role for now
            'department' => $input['department'],
            'position' => $input['position'],
            'hire_date' => $input['hire_date'] ?? date('Y-m-d'),
            'salary' => $input['salary'] ?? null,
            'shift' => $input['shift'] ?? 'day',
            'address' => $input['address'] ?? null,
            'emergency_contact' => $input['emergency_contact'] ?? null,
            'status' => 'active',
            'admin_id' => $_SESSION['admin_id'] // Assign to current admin
        ]);
        
        $employeeDbId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Employee created successfully',
            'data' => [
                'id' => $employeeDbId,
                'employee_id' => $employeeId,
                'default_password' => $defaultPassword
            ]
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create employee: ' . $e->getMessage()]);
    }
}

function updateEmployee() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Employee ID is required']);
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
            $fields[] = "email = :email";
            $params['email'] = $input['email'];
        }
        
        if (isset($input['phone'])) {
            $fields[] = "phone = :phone";
            $params['phone'] = $input['phone'];
        }
        
        if (isset($input['department'])) {
            $fields[] = "department = :department";
            $params['department'] = $input['department'];
        }
        
        if (isset($input['position'])) {
            $fields[] = "position = :position";
            $params['position'] = $input['position'];
        }
        
        if (isset($input['salary'])) {
            $fields[] = "salary = :salary";
            $params['salary'] = $input['salary'];
        }
        
        if (isset($input['shift'])) {
            $fields[] = "shift = :shift";
            $params['shift'] = $input['shift'];
        }
        
        if (isset($input['address'])) {
            $fields[] = "address = :address";
            $params['address'] = $input['address'];
        }
        
        if (isset($input['emergency_contact'])) {
            $fields[] = "emergency_contact = :emergency_contact";
            $params['emergency_contact'] = $input['emergency_contact'];
        }
        
        if (isset($input['status'])) {
            $fields[] = "status = :status";
            $params['status'] = $input['status'];
        }
        
        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            return;
        }
        
        $query = "UPDATE Employee SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        echo json_encode([
            'success' => true,
            'message' => 'Employee updated successfully'
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update employee: ' . $e->getMessage()]);
    }
}

function deleteEmployee() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Employee ID is required']);
        return;
    }
    
    try {
        // Check if employee has processed any transactions
        $stmt = $pdo->prepare("SELECT COUNT(*) as transaction_count FROM Borrowing_Transaction WHERE processed_by = :id");
        $stmt->execute(['id' => $id]);
        $transactionCount = $stmt->fetch()['transaction_count'];
        
        if ($transactionCount > 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot delete employee with transaction history. Consider deactivating instead.']);
            return;
        }
        
        $stmt = $pdo->prepare("DELETE FROM Employee WHERE id = :id");
        $stmt->execute(['id' => $id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Employee deleted successfully'
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete employee: ' . $e->getMessage()]);
    }
}

function bulkActions() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $ids = $input['ids'] ?? [];
    
    if (empty($ids)) {
        http_response_code(400);
        echo json_encode(['error' => 'No employee IDs provided']);
        return;
    }
    
    try {
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        
        switch ($action) {
            case 'activate':
                $stmt = $pdo->prepare("UPDATE Employee SET status = 'active' WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                break;
                
            case 'deactivate':
                $stmt = $pdo->prepare("UPDATE Employee SET status = 'inactive' WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                break;
                
            case 'update-department':
                $department = $input['department'] ?? '';
                if (!$department) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Department is required for bulk update']);
                    return;
                }
                $stmt = $pdo->prepare("UPDATE Employee SET department = ? WHERE id IN ($placeholders)");
                $stmt->execute(array_merge([$department], $ids));
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
