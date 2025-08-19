<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check remember me token
checkRememberMe();

// Require login
requireLogin();

// Check permission for borrowing management
if (!hasPermission('borrowing_management')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

header('Content-Type: application/json');

// Get action from GET, POST, or JSON body
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// If no action found in GET/POST, try to get it from JSON body
if (!$action) {
    $json_input = file_get_contents('php://input');
    if ($json_input) {
        $json_data = json_decode($json_input, true);
        if ($json_data && isset($json_data['action'])) {
            $action = $json_data['action'];
        }
    }
}

// Add debugging
error_log("API called with action: " . $action);
if (!empty($json_input)) {
    error_log("JSON input received: " . $json_input);
}

try {
    switch ($action) {
        case 'list':
            listBorrowingRequests();
            break;
        case 'get':
            getBorrowingRequest();
            break;
        case 'create':
            createBorrowingRequest();
            break;
        case 'update':
            updateBorrowingRequest();
            break;
        case 'delete':
            deleteBorrowingRequest();
            break;
        case 'bulk_delete':
            bulkDeleteBorrowingRequests();
            break;
        case 'approve':
            approveBorrowingRequest();
            break;
        case 'reject':
            rejectBorrowingRequest();
            break;
        case 'export':
            exportBorrowingRequests();
            break;
        case 'stats':
            getBorrowingStats();
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid or missing action parameter. Action received: ' . $action]);
            break;
    }
} catch (Exception $e) {
    error_log("Borrowing Requests API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

function listBorrowingRequests() {
    global $pdo;
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 10);
    $offset = ($page - 1) * $limit;
    
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    $customer_id = $_GET['customer_id'] ?? '';
    $location_id = $_GET['location_id'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $whereClause .= " AND (c.name LIKE :search OR br.purpose LIKE :search OR e.name LIKE :search OR l.name LIKE :search)";
        $params['search'] = "%" . $search . "%";
    }
    
    if (!empty($status)) {
        $whereClause .= " AND br.status = :status";
        $params['status'] = $status;
    }
    
    if (!empty($customer_id)) {
        $whereClause .= " AND br.customer_id = :customer_id";
        $params['customer_id'] = $customer_id;
    }
    
    if (!empty($location_id)) {
        $whereClause .= " AND br.location_id = :location_id";
        $params['location_id'] = $location_id;
    }
    
    if (!empty($date_from)) {
        $whereClause .= " AND DATE(br.request_date) >= :date_from";
        $params['date_from'] = $date_from;
    }
    
    if (!empty($date_to)) {
        $whereClause .= " AND DATE(br.request_date) <= :date_to";
        $params['date_to'] = $date_to;
    }
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total 
                   FROM Borrowing_Request br 
                   INNER JOIN Customer c ON br.customer_id = c.id 
                   INNER JOIN Employee e ON br.employee_id = e.id 
                   INNER JOIN Location l ON br.location_id = l.id 
                   $whereClause";
    
    $countStmt = $pdo->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue(":$key", $value);
    }
    $countStmt->execute();
    $total = $countStmt->fetch()['total'];
    
    // Get borrowing requests
    $query = "SELECT br.*, 
                     c.name as customer_name, 
                     c.customer_type,
                     e.name as employee_name, 
                     l.name as location_name,
                     l.city as location_city,
                     a.name as approved_by_name,
                     COUNT(bi.id) as total_items
              FROM Borrowing_Request br 
              INNER JOIN Customer c ON br.customer_id = c.id 
              INNER JOIN Employee e ON br.employee_id = e.id 
              INNER JOIN Location l ON br.location_id = l.id 
              LEFT JOIN Admin a ON br.approved_by = a.id
              LEFT JOIN Borrowing_Items bi ON br.id = bi.borrowing_request_id
              $whereClause 
              GROUP BY br.id
              ORDER BY br.request_date DESC 
              LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $requests = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $requests,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($total / $limit),
            'total_items' => $total,
            'items_per_page' => $limit
        ]
    ]);
}

function getBorrowingRequest() {
    global $pdo;
    
    $id = $_GET['id'] ?? '';
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        return;
    }
    
    $query = "SELECT br.*, 
                     c.name as customer_name, 
                     c.email as customer_email,
                     c.phone as customer_phone,
                     c.customer_type,
                     e.name as employee_name, 
                     e.email as employee_email,
                     l.name as location_name,
                     l.address as location_address,
                     l.city as location_city,
                     l.state as location_state,
                     a.name as approved_by_name
              FROM Borrowing_Request br 
              INNER JOIN Customer c ON br.customer_id = c.id 
              INNER JOIN Employee e ON br.employee_id = e.id 
              INNER JOIN Location l ON br.location_id = l.id 
              LEFT JOIN Admin a ON br.approved_by = a.id
              WHERE br.id = :id";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $request = $stmt->fetch();
    
    if (!$request) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Borrowing request not found']);
        return;
    }
    
    // Get borrowing items
    $itemsQuery = "SELECT bi.*, bit.name as item_type_name
                   FROM Borrowing_Items bi 
                   LEFT JOIN Borrowing_Item_Types bit ON bi.item_type_id = bit.id
                   WHERE bi.borrowing_request_id = :id";
    
    $itemsStmt = $pdo->prepare($itemsQuery);
    $itemsStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $itemsStmt->execute();
    
    $request['items'] = $itemsStmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $request]);
}

function createBorrowingRequest() {
    global $pdo;
    
    $raw_input = file_get_contents('php://input');
    error_log("Create request - Raw input: " . $raw_input);
    
    $data = json_decode($raw_input, true);
    
    if (!$data) {
        error_log("Create request - JSON decode failed");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data. Raw input: ' . substr($raw_input, 0, 200)]);
        return;
    }
    
    error_log("Create request - Decoded data: " . print_r($data, true));
    
    $required_fields = ['customer_id', 'employee_id', 'location_id', 'required_date', 'purpose'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            error_log("Create request - Missing field: $field");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Field '$field' is required. Received data: " . json_encode($data)]);
            return;
        }
    }
    
    // Validate that referenced entities exist
    try {
        // Check customer exists
        $stmt = $pdo->prepare("SELECT id FROM Customer WHERE id = ? AND status = 'active'");
        $stmt->execute([$data['customer_id']]);
        if (!$stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid customer ID']);
            return;
        }
        
        // Check employee exists
        $stmt = $pdo->prepare("SELECT id FROM Employee WHERE id = ? AND status = 'active'");
        $stmt->execute([$data['employee_id']]);
        if (!$stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
            return;
        }
        
        // Check location exists
        $stmt = $pdo->prepare("SELECT id FROM Location WHERE id = ?");
        $stmt->execute([$data['location_id']]);
        if (!$stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid location ID']);
            return;
        }
        
    } catch (Exception $e) {
        error_log("Validation error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Validation failed']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        $query = "INSERT INTO Borrowing_Request (customer_id, employee_id, location_id, required_date, purpose, status, notes) 
                  VALUES (:customer_id, :employee_id, :location_id, :required_date, :purpose, :status, :notes)";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'customer_id' => $data['customer_id'],
            'employee_id' => $data['employee_id'],
            'location_id' => $data['location_id'],
            'required_date' => $data['required_date'],
            'purpose' => $data['purpose'],
            'status' => $data['status'] ?? 'pending',
            'notes' => $data['notes'] ?? null
        ]);
        
        $requestId = $pdo->lastInsertId();
        
        // Add items if provided
        if (!empty($data['items'])) {
            $itemQuery = "INSERT INTO Borrowing_Items (borrowing_request_id, item_type_id, item_description, quantity_requested, estimated_value) 
                          VALUES (:request_id, :item_type_id, :item_description, :quantity_requested, :estimated_value)";
            $itemStmt = $pdo->prepare($itemQuery);
            
            foreach ($data['items'] as $item) {
                // Validate item data
                if (empty($item['item_description']) || empty($item['quantity_requested'])) {
                    throw new Exception('Item description and quantity are required');
                }
                
                $itemStmt->execute([
                    'request_id' => $requestId,
                    'item_type_id' => $item['item_type_id'] ?: null,
                    'item_description' => $item['item_description'],
                    'quantity_requested' => (int)$item['quantity_requested'],
                    'estimated_value' => (float)($item['estimated_value'] ?: 0)
                ]);
            }
        }
        
        // Log activity
        logActivity($pdo, 'CREATE', "Created borrowing request ID: $requestId");
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Borrowing request created successfully', 'id' => $requestId]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Create borrowing request error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create borrowing request: ' . $e->getMessage()]);
    }
}

function updateBorrowingRequest() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        $query = "UPDATE Borrowing_Request SET 
                    customer_id = :customer_id, 
                    employee_id = :employee_id, 
                    location_id = :location_id, 
                    required_date = :required_date, 
                    purpose = :purpose, 
                    status = :status, 
                    notes = :notes 
                  WHERE id = :id";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'id' => $data['id'],
            'customer_id' => $data['customer_id'],
            'employee_id' => $data['employee_id'],
            'location_id' => $data['location_id'],
            'required_date' => $data['required_date'],
            'purpose' => $data['purpose'],
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null
        ]);
        
        // Log activity
        logActivity($pdo, 'UPDATE', "Updated borrowing request ID: {$data['id']}");
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Borrowing request updated successfully']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Update borrowing request error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update borrowing request']);
    }
}

function deleteBorrowingRequest() {
    global $pdo;
    
    // Handle both POST form data and JSON data
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? $_POST['id'] ?? $_GET['id'] ?? '';
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Delete borrowing items first
        $stmt = $pdo->prepare("DELETE FROM Borrowing_Items WHERE borrowing_request_id = ?");
        $stmt->execute([$id]);
        
        // Delete borrowing request
        $stmt = $pdo->prepare("DELETE FROM Borrowing_Request WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Borrowing request not found']);
            return;
        }
        
        // Log activity
        logActivity($pdo, 'DELETE', "Deleted borrowing request ID: $id");
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Borrowing request deleted successfully']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Delete borrowing request error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete borrowing request']);
    }
}

function bulkDeleteBorrowingRequests() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $ids = $data['ids'] ?? [];
    
    if (empty($ids)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No IDs provided']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        
        // Delete borrowing items first
        $stmt = $pdo->prepare("DELETE FROM Borrowing_Items WHERE borrowing_request_id IN ($placeholders)");
        $stmt->execute($ids);
        
        // Delete borrowing requests
        $stmt = $pdo->prepare("DELETE FROM Borrowing_Request WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        
        $deletedCount = $stmt->rowCount();
        
        // Log activity
        logActivity($pdo, 'BULK_DELETE', "Bulk deleted $deletedCount borrowing requests");
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => "$deletedCount borrowing requests deleted successfully"]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Bulk delete borrowing requests error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete borrowing requests']);
    }
}

function approveBorrowingRequest() {
    global $pdo;
    
    $raw_input = file_get_contents('php://input');
    error_log("Approve request - Raw input: " . $raw_input);
    
    $data = json_decode($raw_input, true);
    error_log("Approve request - Decoded data: " . print_r($data, true));
    
    $id = $data['id'] ?? '';
    
    if (!$id) {
        error_log("Approve request - No ID provided");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID is required. Received data: ' . json_encode($data)]);
        return;
    }
    
    $currentAdmin = getLoggedInAdmin();
    
    if (!$currentAdmin) {
        error_log("Approve request - No current admin");
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'User not authenticated']);
        return;
    }
    
    try {
        // First check if the request exists and is in pending status
        $checkStmt = $pdo->prepare("SELECT status FROM Borrowing_Request WHERE id = ?");
        $checkStmt->execute([$id]);
        $request = $checkStmt->fetch();
        
        if (!$request) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Borrowing request not found']);
            return;
        }
        
        if ($request['status'] !== 'pending') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Only pending requests can be approved']);
            return;
        }
        
        $stmt = $pdo->prepare("UPDATE Borrowing_Request SET status = 'approved', approved_by = ?, approved_date = NOW() WHERE id = ?");
        $stmt->execute([$currentAdmin['id'], $id]);
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Failed to update borrowing request']);
            return;
        }
        
        // Log activity
        logActivity($pdo, 'APPROVE', "Approved borrowing request ID: $id");
        
        echo json_encode(['success' => true, 'message' => 'Borrowing request approved successfully']);
        
    } catch (Exception $e) {
        error_log("Approve borrowing request error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to approve borrowing request: ' . $e->getMessage()]);
    }
}

function rejectBorrowingRequest() {
    global $pdo;
    
    $raw_input = file_get_contents('php://input');
    error_log("Reject request - Raw input: " . $raw_input);
    
    $data = json_decode($raw_input, true);
    error_log("Reject request - Decoded data: " . print_r($data, true));
    
    $id = $data['id'] ?? '';
    $notes = $data['notes'] ?? '';
    
    if (!$id) {
        error_log("Reject request - No ID provided");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID is required. Received data: ' . json_encode($data)]);
        return;
    }
    
    $currentAdmin = getLoggedInAdmin();
    
    if (!$currentAdmin) {
        error_log("Reject request - No current admin");
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'User not authenticated']);
        return;
    }
    
    try {
        // First check if the request exists and is in pending status
        $checkStmt = $pdo->prepare("SELECT status FROM Borrowing_Request WHERE id = ?");
        $checkStmt->execute([$id]);
        $request = $checkStmt->fetch();
        
        if (!$request) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Borrowing request not found']);
            return;
        }
        
        if ($request['status'] !== 'pending') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Only pending requests can be rejected']);
            return;
        }
        
        $stmt = $pdo->prepare("UPDATE Borrowing_Request SET status = 'rejected', approved_by = ?, approved_date = NOW(), notes = ? WHERE id = ?");
        $stmt->execute([$currentAdmin['id'], $notes, $id]);
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Failed to update borrowing request']);
            return;
        }
        
        // Log activity
        logActivity($pdo, 'REJECT', "Rejected borrowing request ID: $id");
        
        echo json_encode(['success' => true, 'message' => 'Borrowing request rejected successfully']);
        
    } catch (Exception $e) {
        error_log("Reject borrowing request error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to reject borrowing request: ' . $e->getMessage()]);
    }
}

function exportBorrowingRequests() {
    global $pdo;
    
    $format = $_GET['format'] ?? 'csv';
    $ids = $_GET['request_ids'] ?? '';
    
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if (!empty($ids)) {
        $idArray = explode(',', $ids);
        $placeholders = str_repeat('?,', count($idArray) - 1) . '?';
        $whereClause .= " AND br.id IN ($placeholders)";
        $params = $idArray;
    }
    
    $query = "SELECT br.id, br.request_date, br.required_date, br.purpose, br.status, br.notes,
                     c.name as customer_name, c.customer_type,
                     e.name as employee_name,
                     l.name as location_name, l.city as location_city,
                     a.name as approved_by_name, br.approved_date
              FROM Borrowing_Request br 
              INNER JOIN Customer c ON br.customer_id = c.id 
              INNER JOIN Employee e ON br.employee_id = e.id 
              INNER JOIN Location l ON br.location_id = l.id 
              LEFT JOIN Admin a ON br.approved_by = a.id
              $whereClause 
              ORDER BY br.request_date DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $requests = $stmt->fetchAll();
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="borrowing_requests.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'ID', 'Request Date', 'Required Date', 'Customer', 'Customer Type', 
            'Employee', 'Location', 'Purpose', 'Status', 'Approved By', 
            'Approved Date', 'Notes'
        ]);
        
        // CSV data
        foreach ($requests as $request) {
            fputcsv($output, [
                $request['id'],
                $request['request_date'],
                $request['required_date'],
                $request['customer_name'],
                $request['customer_type'],
                $request['employee_name'],
                $request['location_name'] . ', ' . $request['location_city'],
                $request['purpose'],
                ucfirst($request['status']),
                $request['approved_by_name'],
                $request['approved_date'],
                $request['notes']
            ]);
        }
        
        fclose($output);
    } else {
        echo json_encode(['success' => true, 'data' => $requests]);
    }
}

function getBorrowingStats() {
    global $pdo;
    
    $stats = [];
    
    // Total requests
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM Borrowing_Request");
    $stats['total_requests'] = $stmt->fetch()['total'] ?? 0;
    
    // Pending requests
    $stmt = $pdo->query("SELECT COUNT(*) as pending FROM Borrowing_Request WHERE status = 'pending'");
    $stats['pending_requests'] = $stmt->fetch()['pending'] ?? 0;
    
    // Approved requests
    $stmt = $pdo->query("SELECT COUNT(*) as approved FROM Borrowing_Request WHERE status = 'approved'");
    $stats['approved_requests'] = $stmt->fetch()['approved'] ?? 0;
    
    // Active borrowings
    $stmt = $pdo->query("SELECT COUNT(*) as active FROM Borrowing_Request WHERE status = 'active'");
    $stats['active_borrowings'] = $stmt->fetch()['active'] ?? 0;
    
    // Overdue returns
    $stmt = $pdo->query("SELECT COUNT(*) as overdue FROM Borrowing_Request WHERE status = 'overdue'");
    $stats['overdue_returns'] = $stmt->fetch()['overdue'] ?? 0;
    
    // Recent activity (last 7 days)
    $stmt = $pdo->query("SELECT COUNT(*) as recent FROM Borrowing_Request WHERE request_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['recent_requests'] = $stmt->fetch()['recent'] ?? 0;
    
    echo json_encode(['success' => true, 'data' => $stats]);
}

function logActivity($pdo, $action, $description) {
    $currentAdmin = getLoggedInAdmin();
    if ($currentAdmin) {
        $stmt = $pdo->prepare("INSERT INTO Activity_Log (admin_id, action, description) VALUES (?, ?, ?)");
        $stmt->execute([$currentAdmin['id'], $action, $description]);
    }
}
?>
