<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                getCustomers();
            } elseif ($action === 'get' && isset($_GET['id'])) {
                getCustomer($_GET['id']);
            } elseif ($action === 'stats') {
                getCustomerStats();
            }
            break;
            
        case 'POST':
            if ($action === 'create') {
                createCustomer();
            } elseif ($action === 'update') {
                updateCustomer();
            } elseif ($action === 'delete') {
                deleteCustomer();
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

function getCustomers() {
    global $pdo;
    
    // Get filter parameters
    $search = $_GET['search'] ?? '';
    $type = $_GET['type'] ?? '';
    $status = $_GET['status'] ?? '';
    $location = $_GET['location'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 10);
    $offset = ($page - 1) * $limit;
    
    // Build query with filters
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if ($search) {
        $whereClause .= " AND (c.name LIKE :search OR c.email LIKE :search OR c.phone LIKE :search)";
        $params['search'] = "%$search%";
    }
    
    if ($type) {
        $whereClause .= " AND c.customer_type = :type";
        $params['type'] = $type;
    }
    
    if ($status) {
        $whereClause .= " AND c.status = :status";
        $params['status'] = $status;
    }
    
    if ($location) {
        $whereClause .= " AND c.location_type = :location";
        $params['location'] = $location;
    }
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM Customer c $whereClause";
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];
    
    // Get customers with pagination
    $query = "
        SELECT 
            c.*,
            COALESCE(br_count.total_requests, 0) as total_requests,
            COALESCE(br_count.active_requests, 0) as active_requests
        FROM Customer c
        LEFT JOIN (
            SELECT 
                customer_id,
                COUNT(*) as total_requests,
                SUM(CASE WHEN status IN ('approved', 'active') THEN 1 ELSE 0 END) as active_requests
            FROM Borrowing_Request 
            GROUP BY customer_id
        ) br_count ON c.id = br_count.customer_id
        $whereClause
        ORDER BY c.created_at DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $customers = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $customers,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($total / $limit),
            'total_records' => $total,
            'limit' => $limit
        ]
    ]);
}

function getCustomer($id) {
    global $pdo;
    
    $query = "
        SELECT 
            c.*,
            COUNT(br.id) as total_requests,
            SUM(CASE WHEN br.status IN ('approved', 'active') THEN 1 ELSE 0 END) as active_requests,
            MAX(br.request_date) as last_request_date
        FROM Customer c
        LEFT JOIN Borrowing_Request br ON c.id = br.customer_id
        WHERE c.id = :id
        GROUP BY c.id
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['id' => $id]);
    $customer = $stmt->fetch();
    
    if ($customer) {
        // Get recent borrowing requests
        $requestsQuery = "
            SELECT 
                br.*,
                e.name as employee_name,
                l.name as location_name
            FROM Borrowing_Request br
            LEFT JOIN Employee e ON br.employee_id = e.id
            LEFT JOIN Location l ON br.location_id = l.id
            WHERE br.customer_id = :id
            ORDER BY br.request_date DESC
            LIMIT 5
        ";
        
        $requestsStmt = $pdo->prepare($requestsQuery);
        $requestsStmt->execute(['id' => $id]);
        $customer['recent_requests'] = $requestsStmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $customer]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Customer not found']);
    }
}

function getCustomerStats() {
    global $pdo;
    
    $stats = [];
    
    // Total customers
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM Customer");
    $stats['total_customers'] = $stmt->fetch()['total'];
    
    // Active customers (those with recent activity)
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT c.id) as active 
        FROM Customer c 
        INNER JOIN Borrowing_Request br ON c.id = br.customer_id 
        WHERE br.request_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stats['active_customers'] = $stmt->fetch()['active'];
    
    // VIP customers (customers with more than 10 requests)
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT customer_id) as vip 
        FROM (
            SELECT customer_id, COUNT(*) as request_count 
            FROM Borrowing_Request 
            GROUP BY customer_id 
            HAVING request_count > 10
        ) as vip_customers
    ");
    $stats['vip_customers'] = $stmt->fetch()['vip'];
    
    // New customers this month
    $stmt = $pdo->query("
        SELECT COUNT(*) as new_customers 
        FROM Customer 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stats['new_this_month'] = $stmt->fetch()['new_customers'];
    
    echo json_encode(['success' => true, 'data' => $stats]);
}

function createCustomer() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['name', 'email', 'phone'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field '$field' is required"]);
            return;
        }
    }
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM Customer WHERE email = :email");
    $stmt->execute(['email' => $input['email']]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'Email already exists']);
        return;
    }
    
    try {
        $query = "
            INSERT INTO Customer (
                name, email, phone, address, customer_type, location_type, 
                contact_person, alt_phone, credit_limit, payment_terms,
                billing_address, shipping_address, status
            ) VALUES (
                :name, :email, :phone, :address, :customer_type, :location_type,
                :contact_person, :alt_phone, :credit_limit, :payment_terms,
                :billing_address, :shipping_address, :status
            )
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'name' => $input['name'],
            'email' => $input['email'],
            'phone' => $input['phone'],
            'address' => $input['address'] ?? null,
            'customer_type' => $input['customer_type'] ?? 'retail',
            'location_type' => $input['location'] ?? 'local',
            'contact_person' => $input['contact_person'] ?? null,
            'alt_phone' => $input['alt_phone'] ?? null,
            'credit_limit' => $input['credit_limit'] ?? 0,
            'payment_terms' => $input['payment_terms'] ?? 'net-30',
            'billing_address' => $input['billing_address'] ?? null,
            'shipping_address' => $input['shipping_address'] ?? null,
            'status' => 'active'
        ]);
        
        $customerId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Customer created successfully',
            'data' => ['id' => $customerId]
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create customer: ' . $e->getMessage()]);
    }
}

function updateCustomer() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Customer ID is required']);
        return;
    }
    
    try {
        $query = "
            UPDATE Customer SET 
                name = :name,
                email = :email,
                phone = :phone,
                address = :address,
                customer_type = :customer_type,
                location_type = :location_type,
                contact_person = :contact_person,
                alt_phone = :alt_phone,
                credit_limit = :credit_limit,
                payment_terms = :payment_terms,
                billing_address = :billing_address,
                shipping_address = :shipping_address,
                status = :status
            WHERE id = :id
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'id' => $id,
            'name' => $input['name'],
            'email' => $input['email'],
            'phone' => $input['phone'],
            'address' => $input['address'] ?? null,
            'customer_type' => $input['customer_type'] ?? 'retail',
            'location_type' => $input['location'] ?? 'local',
            'contact_person' => $input['contact_person'] ?? null,
            'alt_phone' => $input['alt_phone'] ?? null,
            'credit_limit' => $input['credit_limit'] ?? 0,
            'payment_terms' => $input['payment_terms'] ?? 'net-30',
            'billing_address' => $input['billing_address'] ?? null,
            'shipping_address' => $input['shipping_address'] ?? null,
            'status' => $input['status'] ?? 'active'
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Customer updated successfully'
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update customer: ' . $e->getMessage()]);
    }
}

function deleteCustomer() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Customer ID is required']);
        return;
    }
    
    try {
        // Check if customer has active borrowing requests
        $stmt = $pdo->prepare("SELECT COUNT(*) as active FROM Borrowing_Request WHERE customer_id = :id AND status IN ('approved', 'active')");
        $stmt->execute(['id' => $id]);
        $activeRequests = $stmt->fetch()['active'];
        
        if ($activeRequests > 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot delete customer with active borrowing requests']);
            return;
        }
        
        $stmt = $pdo->prepare("DELETE FROM Customer WHERE id = :id");
        $stmt->execute(['id' => $id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Customer deleted successfully'
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete customer: ' . $e->getMessage()]);
    }
}

function bulkActions() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $ids = $input['ids'] ?? [];
    
    if (empty($ids)) {
        http_response_code(400);
        echo json_encode(['error' => 'No customer IDs provided']);
        return;
    }
    
    try {
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        
        switch ($action) {
            case 'activate':
                $stmt = $pdo->prepare("UPDATE Customer SET status = 'active' WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                break;
                
            case 'deactivate':
                $stmt = $pdo->prepare("UPDATE Customer SET status = 'inactive' WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                break;
                
            case 'update-type':
                $newType = $input['new_type'] ?? 'retail';
                $stmt = $pdo->prepare("UPDATE Customer SET customer_type = ? WHERE id IN ($placeholders)");
                $stmt->execute(array_merge([$newType], $ids));
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
