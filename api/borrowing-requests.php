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

// Get action from various sources
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// If action is not found in GET/POST, check JSON body
if (empty($action) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
}

try {
    switch ($action) {
        case 'list':
            handleListRequests();
            break;
        case 'get':
            handleGetRequest();
            break;
        case 'create':
            handleCreateRequest();
            break;
        case 'update':
            handleUpdateRequest();
            break;
        case 'delete':
            handleDeleteRequest();
            break;
        case 'approve':
            handleApproveRequest();
            break;
        case 'reject':
            handleRejectRequest();
            break;
        case 'process_borrow':
            handleProcessBorrow();
            break;
        case 'process_return':
            handleProcessReturn();
            break;
        case 'stats':
            handleGetStats();
            break;
        case 'bulk_action':
            handleBulkAction();
            break;
        case 'export':
            handleExport();
            break;
        case 'get_items':
            handleGetRequestItems();
            break;
        case 'update_items':
            handleUpdateRequestItems();
            break;
        case 'check_availability':
            handleCheckAvailability();
            break;
        case 'get_overdue':
            handleGetOverdueRequests();
            break;
        case 'get_item_types':
            handleGetItemTypes();
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action specified']);
            break;
    }
} catch (Exception $e) {
    error_log("Borrowing Requests API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error occurred']);
}

function handleListRequests() {
    global $pdo;
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = min((int)($_GET['limit'] ?? 10), 100);
    $offset = ($page - 1) * $limit;
    
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    $customer_id = $_GET['customer_id'] ?? '';
    $employee_id = $_GET['employee_id'] ?? '';
    $location_id = $_GET['location_id'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $sort_by = $_GET['sort_by'] ?? 'request_date';
    $sort_order = $_GET['sort_order'] ?? 'DESC';
    
    // Validate sort parameters
    $validSortColumns = ['request_date', 'status', 'customer_name', 'employee_name', 'required_date', 'id'];
    $validSortOrders = ['ASC', 'DESC'];
    
    if (!in_array($sort_by, $validSortColumns)) {
        $sort_by = 'request_date';
    }
    if (!in_array(strtoupper($sort_order), $validSortOrders)) {
        $sort_order = 'DESC';
    }
    
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $whereClause .= " AND (c.name LIKE :search OR e.name LIKE :search OR br.purpose LIKE :search OR br.id = :search_id)";
        $params['search'] = "%" . $search . "%";
        $params['search_id'] = is_numeric($search) ? $search : 0;
    }
    
    if (!empty($status)) {
        $whereClause .= " AND br.status = :status";
        $params['status'] = $status;
    }
    
    if (!empty($customer_id)) {
        $whereClause .= " AND br.customer_id = :customer_id";
        $params['customer_id'] = $customer_id;
    }
    
    if (!empty($employee_id)) {
        $whereClause .= " AND br.employee_id = :employee_id";
        $params['employee_id'] = $employee_id;
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
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM Borrowing_Request br 
                   LEFT JOIN Customer c ON br.customer_id = c.id 
                   LEFT JOIN Employee e ON br.employee_id = e.id 
                   $whereClause";
    $countStmt = $pdo->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue(":$key", $value);
    }
    $countStmt->execute();
    $totalItems = $countStmt->fetch()['total'];
    
    // Get requests with related data
    $query = "SELECT br.*, 
                     c.name as customer_name, c.email as customer_email, c.phone as customer_phone,
                     e.name as employee_name, e.employee_id, e.department,
                     l.name as location_name,
                     a.name as approved_by_name,
                     COUNT(DISTINCT bi.id) as total_items,
                     SUM(bi.quantity_requested) as total_quantity_requested,
                     SUM(bi.quantity_approved) as total_quantity_approved,
                     SUM(bi.quantity_borrowed) as total_quantity_borrowed,
                     COUNT(DISTINCT bt.id) as transactions_count
              FROM Borrowing_Request br 
              LEFT JOIN Customer c ON br.customer_id = c.id 
              LEFT JOIN Employee e ON br.employee_id = e.id 
              LEFT JOIN Location l ON br.location_id = l.id
              LEFT JOIN Admin a ON br.approved_by = a.id
              LEFT JOIN Borrowing_Items bi ON br.id = bi.borrowing_request_id
              LEFT JOIN Borrowing_Transaction bt ON br.id = bt.borrowing_request_id
              $whereClause 
              GROUP BY br.id";
    
    // Apply sorting
    if ($sort_by === 'customer_name') {
        $query .= " ORDER BY c.name $sort_order";
    } elseif ($sort_by === 'employee_name') {
        $query .= " ORDER BY e.name $sort_order";
    } else {
        $query .= " ORDER BY br.$sort_by $sort_order";
    }
    
    $query .= " LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate pagination info
    $totalPages = ceil($totalItems / $limit);
    $hasNext = $page < $totalPages;
    $hasPrev = $page > 1;
    
    echo json_encode([
        'success' => true,
        'data' => $requests,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_items' => $totalItems,
            'items_per_page' => $limit,
            'has_next' => $hasNext,
            'has_prev' => $hasPrev
        ]
    ]);
}

function handleGetRequest() {
    global $pdo;
    
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Request ID is required']);
        return;
    }
    
    // Get request details
    $query = "SELECT br.*, 
                     c.name as customer_name, c.email as customer_email, c.phone as customer_phone,
                     c.address as customer_address, c.customer_type, c.contact_person,
                     e.name as employee_name, e.employee_id, e.department, e.position,
                     l.name as location_name, l.address as location_address,
                     a.name as approved_by_name
              FROM Borrowing_Request br 
              LEFT JOIN Customer c ON br.customer_id = c.id 
              LEFT JOIN Employee e ON br.employee_id = e.id 
              LEFT JOIN Location l ON br.location_id = l.id
              LEFT JOIN Admin a ON br.approved_by = a.id
              WHERE br.id = :id";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Request not found']);
        return;
    }
    
    // Get request items
    $itemsQuery = "SELECT bi.*, m.name as material_name, m.unit, m.price_per_unit,
                          mc.name as category_name,
                          COALESCE(SUM(i.quantity), 0) as available_quantity
                   FROM Borrowing_Items bi
                   INNER JOIN Material m ON bi.material_id = m.id
                   LEFT JOIN Material_Categories mc ON m.category_id = mc.id
                   LEFT JOIN Inventory i ON m.id = i.material_id AND i.location_id = :location_id
                   WHERE bi.borrowing_request_id = :request_id
                   GROUP BY bi.id
                   ORDER BY m.name";
    
    $itemsStmt = $pdo->prepare($itemsQuery);
    $itemsStmt->bindValue(':request_id', $id, PDO::PARAM_INT);
    $itemsStmt->bindValue(':location_id', $request['location_id'], PDO::PARAM_INT);
    $itemsStmt->execute();
    
    $request['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get transactions
    $transactionsQuery = "SELECT bt.*, e.name as processed_by_name
                          FROM Borrowing_Transaction bt
                          LEFT JOIN Employee e ON bt.processed_by = e.id
                          WHERE bt.borrowing_request_id = :request_id
                          ORDER BY bt.transaction_date DESC";
    
    $transactionsStmt = $pdo->prepare($transactionsQuery);
    $transactionsStmt->bindValue(':request_id', $id, PDO::PARAM_INT);
    $transactionsStmt->execute();
    
    $request['transactions'] = $transactionsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get return items if any
    if (in_array($request['status'], ['returned', 'active'])) {
        $returnsQuery = "SELECT ri.*, m.name as material_name, bt.transaction_date
                         FROM Return_Items ri
                         INNER JOIN Borrowing_Transaction bt ON ri.borrowing_transaction_id = bt.id
                         INNER JOIN Material m ON ri.material_id = m.id
                         WHERE bt.borrowing_request_id = :request_id
                         ORDER BY ri.return_date DESC";
        
        $returnsStmt = $pdo->prepare($returnsQuery);
        $returnsStmt->bindValue(':request_id', $id, PDO::PARAM_INT);
        $returnsStmt->execute();
        
        $request['returns'] = $returnsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode(['success' => true, 'data' => $request]);
}

function handleCreateRequest() {
    global $pdo, $currentAdmin;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $customer_id = $input['customer_id'] ?? null;
    $employee_id = $input['employee_id'] ?? null;
    $location_id = $input['location_id'] ?? null;
    $required_date = $input['required_date'] ?? null;
    $purpose = trim($input['purpose'] ?? '');
    $items = $input['items'] ?? [];
    $notes = trim($input['notes'] ?? '');
    
    // Validation
    if (!$customer_id || !$employee_id || !$location_id || empty($purpose) || empty($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Customer, employee, location, purpose, and items are required']);
        return;
    }
    
    // Validate items
    foreach ($items as $item) {
        if (!isset($item['item_type_id']) || !isset($item['quantity']) || !isset($item['item_description']) || 
            $item['quantity'] <= 0 || empty(trim($item['item_description']))) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid item data provided']);
            return;
        }
    }
    
    try {
        $pdo->beginTransaction();
        
        // Create borrowing request
        $requestQuery = "INSERT INTO Borrowing_Request (customer_id, employee_id, location_id, required_date, purpose, notes, status) 
                         VALUES (:customer_id, :employee_id, :location_id, :required_date, :purpose, :notes, 'pending')";
        $requestStmt = $pdo->prepare($requestQuery);
        $requestStmt->bindValue(':customer_id', $customer_id, PDO::PARAM_INT);
        $requestStmt->bindValue(':employee_id', $employee_id, PDO::PARAM_INT);
        $requestStmt->bindValue(':location_id', $location_id, PDO::PARAM_INT);
        $requestStmt->bindValue(':required_date', $required_date);
        $requestStmt->bindValue(':purpose', $purpose);
        $requestStmt->bindValue(':notes', $notes);
        $requestStmt->execute();
        
        $requestId = $pdo->lastInsertId();
        
        // Add borrowing items
        $itemQuery = "INSERT INTO Borrowing_Items (borrowing_request_id, item_type_id, item_description, quantity_requested, estimated_value) 
                      VALUES (:request_id, :item_type_id, :item_description, :quantity, :estimated_value)";
        $itemStmt = $pdo->prepare($itemQuery);
        
        foreach ($items as $item) {
            // Get item type estimated value
            $valueQuery = "SELECT estimated_value FROM Borrowing_Item_Types WHERE id = :item_type_id";
            $valueStmt = $pdo->prepare($valueQuery);
            $valueStmt->bindValue(':item_type_id', $item['item_type_id'], PDO::PARAM_INT);
            $valueStmt->execute();
            $estimatedValue = $valueStmt->fetch()['estimated_value'] ?? 0;
            
            $itemStmt->bindValue(':request_id', $requestId, PDO::PARAM_INT);
            $itemStmt->bindValue(':item_type_id', $item['item_type_id'], PDO::PARAM_INT);
            $itemStmt->bindValue(':item_description', trim($item['item_description']));
            $itemStmt->bindValue(':quantity', $item['quantity'], PDO::PARAM_INT);
            $itemStmt->bindValue(':estimated_value', $estimatedValue);
            $itemStmt->execute();
        }
        
        // Log the activity
        $logQuery = "INSERT INTO Activity_Log (admin_id, action, description, timestamp) 
                     VALUES (:admin_id, 'CREATE', :description, NOW())";
        $logStmt = $pdo->prepare($logQuery);
        $logStmt->bindValue(':admin_id', $currentAdmin['id']);
        $logStmt->bindValue(':description', "Created borrowing request #$requestId");
        $logStmt->execute();
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Borrowing request created successfully',
            'data' => ['id' => $requestId]
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error creating borrowing request: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to create borrowing request']);
    }
}

function handleUpdateRequest() {
    global $pdo, $currentAdmin;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = $input['id'] ?? null;
    $customer_id = $input['customer_id'] ?? null;
    $employee_id = $input['employee_id'] ?? null;
    $location_id = $input['location_id'] ?? null;
    $required_date = $input['required_date'] ?? null;
    $purpose = trim($input['purpose'] ?? '');
    $notes = trim($input['notes'] ?? '');
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Request ID is required']);
        return;
    }
    
    // Check if request exists and is editable
    $checkQuery = "SELECT status FROM Borrowing_Request WHERE id = :id";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $checkStmt->execute();
    
    $request = $checkStmt->fetch();
    if (!$request) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Request not found']);
        return;
    }
    
    if (!in_array($request['status'], ['pending', 'approved'])) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Cannot edit request in current status']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        $updateQuery = "UPDATE Borrowing_Request SET 
                        customer_id = :customer_id, 
                        employee_id = :employee_id, 
                        location_id = :location_id, 
                        required_date = :required_date, 
                        purpose = :purpose, 
                        notes = :notes
                        WHERE id = :id";
        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->bindValue(':customer_id', $customer_id, PDO::PARAM_INT);
        $updateStmt->bindValue(':employee_id', $employee_id, PDO::PARAM_INT);
        $updateStmt->bindValue(':location_id', $location_id, PDO::PARAM_INT);
        $updateStmt->bindValue(':required_date', $required_date);
        $updateStmt->bindValue(':purpose', $purpose);
        $updateStmt->bindValue(':notes', $notes);
        $updateStmt->bindValue(':id', $id, PDO::PARAM_INT);
        $updateStmt->execute();
        
        // Log the activity
        $logQuery = "INSERT INTO Activity_Log (admin_id, action, description, timestamp) 
                     VALUES (:admin_id, 'UPDATE', :description, NOW())";
        $logStmt = $pdo->prepare($logQuery);
        $logStmt->bindValue(':admin_id', $currentAdmin['id']);
        $logStmt->bindValue(':description', "Updated borrowing request #$id");
        $logStmt->execute();
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Request updated successfully']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error updating borrowing request: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update request']);
    }
}

function handleApproveRequest() {
    global $pdo, $currentAdmin;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = $input['id'] ?? null;
    $approved_quantities = $input['approved_quantities'] ?? [];
    $notes = trim($input['notes'] ?? '');
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Request ID is required']);
        return;
    }
    
    // Check if request can be approved
    $checkQuery = "SELECT status FROM Borrowing_Request WHERE id = :id";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $checkStmt->execute();
    
    $request = $checkStmt->fetch();
    if (!$request) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Request not found']);
        return;
    }
    
    if ($request['status'] !== 'pending') {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Request is not in pending status']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Update request status
        $updateQuery = "UPDATE Borrowing_Request SET 
                        status = 'approved', 
                        approved_by = :approved_by, 
                        approved_date = NOW(),
                        notes = CONCAT(COALESCE(notes, ''), :notes)
                        WHERE id = :id";
        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->bindValue(':approved_by', $currentAdmin['id'], PDO::PARAM_INT);
        $updateStmt->bindValue(':notes', $notes ? "\n\nApproval Notes: " . $notes : '');
        $updateStmt->bindValue(':id', $id, PDO::PARAM_INT);
        $updateStmt->execute();
        
        // Update approved quantities for items
        if (!empty($approved_quantities)) {
            $itemUpdateQuery = "UPDATE Borrowing_Items SET quantity_approved = :approved_qty WHERE id = :item_id";
            $itemUpdateStmt = $pdo->prepare($itemUpdateQuery);
            
            foreach ($approved_quantities as $item_id => $approved_qty) {
                $itemUpdateStmt->bindValue(':approved_qty', $approved_qty, PDO::PARAM_INT);
                $itemUpdateStmt->bindValue(':item_id', $item_id, PDO::PARAM_INT);
                $itemUpdateStmt->execute();
            }
        } else {
            // If no specific quantities provided, approve all requested quantities
            $autoApproveQuery = "UPDATE Borrowing_Items SET quantity_approved = quantity_requested WHERE borrowing_request_id = :id";
            $autoApproveStmt = $pdo->prepare($autoApproveQuery);
            $autoApproveStmt->bindValue(':id', $id, PDO::PARAM_INT);
            $autoApproveStmt->execute();
        }
        
        // Log the activity
        $logQuery = "INSERT INTO Activity_Log (admin_id, action, description, timestamp) 
                     VALUES (:admin_id, 'UPDATE', :description, NOW())";
        $logStmt = $pdo->prepare($logQuery);
        $logStmt->bindValue(':admin_id', $currentAdmin['id']);
        $logStmt->bindValue(':description', "Approved borrowing request #$id");
        $logStmt->execute();
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Request approved successfully']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error approving borrowing request: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to approve request']);
    }
}

function handleRejectRequest() {
    global $pdo, $currentAdmin;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = $input['id'] ?? null;
    $rejection_reason = trim($input['rejection_reason'] ?? '');
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Request ID is required']);
        return;
    }
    
    if (empty($rejection_reason)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Rejection reason is required']);
        return;
    }
    
    // Check if request can be rejected
    $checkQuery = "SELECT status FROM Borrowing_Request WHERE id = :id";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $checkStmt->execute();
    
    $request = $checkStmt->fetch();
    if (!$request) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Request not found']);
        return;
    }
    
    if ($request['status'] !== 'pending') {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Request is not in pending status']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        $updateQuery = "UPDATE Borrowing_Request SET 
                        status = 'rejected', 
                        approved_by = :approved_by, 
                        approved_date = NOW(),
                        notes = CONCAT(COALESCE(notes, ''), :rejection_notes)
                        WHERE id = :id";
        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->bindValue(':approved_by', $currentAdmin['id'], PDO::PARAM_INT);
        $updateStmt->bindValue(':rejection_notes', "\n\nRejection Reason: " . $rejection_reason);
        $updateStmt->bindValue(':id', $id, PDO::PARAM_INT);
        $updateStmt->execute();
        
        // Log the activity
        $logQuery = "INSERT INTO Activity_Log (admin_id, action, description, timestamp) 
                     VALUES (:admin_id, 'UPDATE', :description, NOW())";
        $logStmt = $pdo->prepare($logQuery);
        $logStmt->bindValue(':admin_id', $currentAdmin['id']);
        $logStmt->bindValue(':description', "Rejected borrowing request #$id");
        $logStmt->execute();
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Request rejected successfully']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error rejecting borrowing request: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to reject request']);
    }
}

function handleProcessBorrow() {
    global $pdo, $currentAdmin;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = $input['id'] ?? null;
    $borrowed_quantities = $input['borrowed_quantities'] ?? [];
    $processed_by = $input['processed_by'] ?? null;
    $notes = trim($input['notes'] ?? '');
    
    if (!$id || empty($borrowed_quantities) || !$processed_by) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Request ID, borrowed quantities, and processor are required']);
        return;
    }
    
    // Check if request can be processed
    $checkQuery = "SELECT br.*, l.id as location_id FROM Borrowing_Request br 
                   LEFT JOIN Location l ON br.location_id = l.id
                   WHERE br.id = :id AND br.status = 'approved'";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $checkStmt->execute();
    
    $request = $checkStmt->fetch();
    if (!$request) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Request not found or not approved']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Create borrowing transaction
        $transactionQuery = "INSERT INTO Borrowing_Transaction (borrowing_request_id, transaction_type, processed_by, notes) 
                             VALUES (:request_id, 'borrow', :processed_by, :notes)";
        $transactionStmt = $pdo->prepare($transactionQuery);
        $transactionStmt->bindValue(':request_id', $id, PDO::PARAM_INT);
        $transactionStmt->bindValue(':processed_by', $processed_by, PDO::PARAM_INT);
        $transactionStmt->bindValue(':notes', $notes);
        $transactionStmt->execute();
        
        // Update borrowed quantities and reduce inventory
        $itemUpdateQuery = "UPDATE Borrowing_Items SET quantity_borrowed = :borrowed_qty WHERE id = :item_id";
        $itemUpdateStmt = $pdo->prepare($itemUpdateQuery);
        
        $inventoryUpdateQuery = "UPDATE Inventory SET quantity = quantity - :quantity 
                                 WHERE material_id = :material_id AND location_id = :location_id";
        $inventoryUpdateStmt = $pdo->prepare($inventoryUpdateQuery);
        
        foreach ($borrowed_quantities as $item_id => $borrowed_qty) {
            // Update borrowed quantity
            $itemUpdateStmt->bindValue(':borrowed_qty', $borrowed_qty, PDO::PARAM_INT);
            $itemUpdateStmt->bindValue(':item_id', $item_id, PDO::PARAM_INT);
            $itemUpdateStmt->execute();
            
            // Get material ID
            $materialQuery = "SELECT material_id FROM Borrowing_Items WHERE id = :item_id";
            $materialStmt = $pdo->prepare($materialQuery);
            $materialStmt->bindValue(':item_id', $item_id, PDO::PARAM_INT);
            $materialStmt->execute();
            $material_id = $materialStmt->fetch()['material_id'];
            
            // Update inventory
            $inventoryUpdateStmt->bindValue(':quantity', $borrowed_qty, PDO::PARAM_INT);
            $inventoryUpdateStmt->bindValue(':material_id', $material_id, PDO::PARAM_INT);
            $inventoryUpdateStmt->bindValue(':location_id', $request['location_id'], PDO::PARAM_INT);
            $inventoryUpdateStmt->execute();
        }
        
        // Update request status to active
        $statusUpdateQuery = "UPDATE Borrowing_Request SET status = 'active' WHERE id = :id";
        $statusUpdateStmt = $pdo->prepare($statusUpdateQuery);
        $statusUpdateStmt->bindValue(':id', $id, PDO::PARAM_INT);
        $statusUpdateStmt->execute();
        
        // Log the activity
        $logQuery = "INSERT INTO Activity_Log (admin_id, action, description, timestamp) 
                     VALUES (:admin_id, 'UPDATE', :description, NOW())";
        $logStmt = $pdo->prepare($logQuery);
        $logStmt->bindValue(':admin_id', $currentAdmin['id']);
        $logStmt->bindValue(':description', "Processed borrowing for request #$id");
        $logStmt->execute();
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Borrowing processed successfully']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error processing borrowing: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to process borrowing']);
    }
}

function handleProcessReturn() {
    global $pdo, $currentAdmin;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = $input['id'] ?? null;
    $returned_items = $input['returned_items'] ?? [];
    $processed_by = $input['processed_by'] ?? null;
    $notes = trim($input['notes'] ?? '');
    
    if (!$id || empty($returned_items) || !$processed_by) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Request ID, returned items, and processor are required']);
        return;
    }
    
    // Check if request can have returns processed
    $checkQuery = "SELECT br.*, l.id as location_id FROM Borrowing_Request br 
                   LEFT JOIN Location l ON br.location_id = l.id
                   WHERE br.id = :id AND br.status = 'active'";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $checkStmt->execute();
    
    $request = $checkStmt->fetch();
    if (!$request) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Request not found or not active']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Create return transaction
        $transactionQuery = "INSERT INTO Borrowing_Transaction (borrowing_request_id, transaction_type, processed_by, notes) 
                             VALUES (:request_id, :transaction_type, :processed_by, :notes)";
        $transactionStmt = $pdo->prepare($transactionQuery);
        
        $isPartialReturn = count($returned_items) < count(array_filter($returned_items, function($item) {
            return $item['quantity_returned'] > 0;
        }));
        
        $transactionType = $isPartialReturn ? 'partial_return' : 'return';
        
        $transactionStmt->bindValue(':request_id', $id, PDO::PARAM_INT);
        $transactionStmt->bindValue(':transaction_type', $transactionType);
        $transactionStmt->bindValue(':processed_by', $processed_by, PDO::PARAM_INT);
        $transactionStmt->bindValue(':notes', $notes);
        $transactionStmt->execute();
        
        $transactionId = $pdo->lastInsertId();
        
        // Process each returned item
        $returnItemQuery = "INSERT INTO Return_Items (borrowing_transaction_id, material_id, quantity_returned, condition_status, damage_notes) 
                            VALUES (:transaction_id, :material_id, :quantity_returned, :condition_status, :damage_notes)";
        $returnItemStmt = $pdo->prepare($returnItemQuery);
        
        $inventoryUpdateQuery = "UPDATE Inventory SET quantity = quantity + :quantity 
                                 WHERE material_id = :material_id AND location_id = :location_id";
        $inventoryUpdateStmt = $pdo->prepare($inventoryUpdateQuery);
        
        $allItemsReturned = true;
        
        foreach ($returned_items as $item) {
            $material_id = $item['material_id'];
            $quantity_returned = $item['quantity_returned'];
            $condition_status = $item['condition_status'] ?? 'good';
            $damage_notes = $item['damage_notes'] ?? '';
            
            if ($quantity_returned > 0) {
                // Insert return record
                $returnItemStmt->bindValue(':transaction_id', $transactionId, PDO::PARAM_INT);
                $returnItemStmt->bindValue(':material_id', $material_id, PDO::PARAM_INT);
                $returnItemStmt->bindValue(':quantity_returned', $quantity_returned, PDO::PARAM_INT);
                $returnItemStmt->bindValue(':condition_status', $condition_status);
                $returnItemStmt->bindValue(':damage_notes', $damage_notes);
                $returnItemStmt->execute();
                
                // Update inventory only for items in good condition
                if ($condition_status === 'good') {
                    $inventoryUpdateStmt->bindValue(':quantity', $quantity_returned, PDO::PARAM_INT);
                    $inventoryUpdateStmt->bindValue(':material_id', $material_id, PDO::PARAM_INT);
                    $inventoryUpdateStmt->bindValue(':location_id', $request['location_id'], PDO::PARAM_INT);
                    $inventoryUpdateStmt->execute();
                }
            }
            
            // Check if all items are returned
            $borrowedQuery = "SELECT quantity_borrowed FROM Borrowing_Items WHERE borrowing_request_id = :request_id AND material_id = :material_id";
            $borrowedStmt = $pdo->prepare($borrowedQuery);
            $borrowedStmt->bindValue(':request_id', $id, PDO::PARAM_INT);
            $borrowedStmt->bindValue(':material_id', $material_id, PDO::PARAM_INT);
            $borrowedStmt->execute();
            $borrowed_qty = $borrowedStmt->fetch()['quantity_borrowed'] ?? 0;
            
            $returnedQuery = "SELECT SUM(ri.quantity_returned) as total_returned
                              FROM Return_Items ri
                              INNER JOIN Borrowing_Transaction bt ON ri.borrowing_transaction_id = bt.id
                              WHERE bt.borrowing_request_id = :request_id AND ri.material_id = :material_id";
            $returnedStmt = $pdo->prepare($returnedQuery);
            $returnedStmt->bindValue(':request_id', $id, PDO::PARAM_INT);
            $returnedStmt->bindValue(':material_id', $material_id, PDO::PARAM_INT);
            $returnedStmt->execute();
            $total_returned = $returnedStmt->fetch()['total_returned'] ?? 0;
            
            if ($total_returned < $borrowed_qty) {
                $allItemsReturned = false;
            }
        }
        
        // Update request status if all items are returned
        if ($allItemsReturned && !$isPartialReturn) {
            $statusUpdateQuery = "UPDATE Borrowing_Request SET status = 'returned' WHERE id = :id";
            $statusUpdateStmt = $pdo->prepare($statusUpdateQuery);
            $statusUpdateStmt->bindValue(':id', $id, PDO::PARAM_INT);
            $statusUpdateStmt->execute();
        }
        
        // Log the activity
        $logQuery = "INSERT INTO Activity_Log (admin_id, action, description, timestamp) 
                     VALUES (:admin_id, 'UPDATE', :description, NOW())";
        $logStmt = $pdo->prepare($logQuery);
        $logStmt->bindValue(':admin_id', $currentAdmin['id']);
        $logStmt->bindValue(':description', "Processed return for request #$id");
        $logStmt->execute();
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Return processed successfully']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error processing return: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to process return']);
    }
}

function handleDeleteRequest() {
    global $pdo, $currentAdmin;
    
    $id = $_POST['id'] ?? $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Request ID is required']);
        return;
    }
    
    // Check if request can be deleted
    $checkQuery = "SELECT status FROM Borrowing_Request WHERE id = :id";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $checkStmt->execute();
    
    $request = $checkStmt->fetch();
    if (!$request) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Request not found']);
        return;
    }
    
    if (in_array($request['status'], ['active', 'overdue'])) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Cannot delete active or overdue requests']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Delete related records in correct order
        $pdo->prepare("DELETE FROM Return_Items WHERE borrowing_transaction_id IN 
                       (SELECT id FROM Borrowing_Transaction WHERE borrowing_request_id = :id)")->execute([':id' => $id]);
        $pdo->prepare("DELETE FROM Borrowing_Transaction WHERE borrowing_request_id = :id")->execute([':id' => $id]);
        $pdo->prepare("DELETE FROM Borrowing_Items WHERE borrowing_request_id = :id")->execute([':id' => $id]);
        $pdo->prepare("DELETE FROM Borrowing_Request WHERE id = :id")->execute([':id' => $id]);
        
        // Log the activity
        $logQuery = "INSERT INTO Activity_Log (admin_id, action, description, timestamp) 
                     VALUES (:admin_id, 'DELETE', :description, NOW())";
        $logStmt = $pdo->prepare($logQuery);
        $logStmt->bindValue(':admin_id', $currentAdmin['id']);
        $logStmt->bindValue(':description', "Deleted borrowing request #$id");
        $logStmt->execute();
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Request deleted successfully']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error deleting borrowing request: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to delete request']);
    }
}

function handleGetStats() {
    global $pdo;
    
    try {
        // Total requests
        $totalQuery = "SELECT COUNT(*) as total FROM Borrowing_Request";
        $totalStmt = $pdo->query($totalQuery);
        $totalRequests = $totalStmt->fetch()['total'];
        
        // Status breakdown
        $statusQuery = "SELECT status, COUNT(*) as count FROM Borrowing_Request GROUP BY status";
        $statusStmt = $pdo->query($statusQuery);
        $statusBreakdown = [];
        while ($row = $statusStmt->fetch()) {
            $statusBreakdown[$row['status']] = (int)$row['count'];
        }
        
        // Recent activity
        $recentQuery = "SELECT COUNT(*) as today FROM Borrowing_Request WHERE DATE(request_date) = CURDATE()";
        $recentStmt = $pdo->query($recentQuery);
        $todayRequests = $recentStmt->fetch()['today'];
        
        $weekQuery = "SELECT COUNT(*) as week FROM Borrowing_Request WHERE WEEK(request_date) = WEEK(NOW()) AND YEAR(request_date) = YEAR(NOW())";
        $weekStmt = $pdo->query($weekQuery);
        $weekRequests = $weekStmt->fetch()['week'];
        
        // Overdue requests
        $overdueQuery = "SELECT COUNT(*) as overdue FROM Borrowing_Request 
                         WHERE status = 'active' AND required_date < CURDATE()";
        $overdueStmt = $pdo->query($overdueQuery);
        $overdueRequests = $overdueStmt->fetch()['overdue'];
        
        // Top customers
        $topCustomersQuery = "SELECT c.name, COUNT(br.id) as request_count 
                              FROM Customer c 
                              INNER JOIN Borrowing_Request br ON c.id = br.customer_id 
                              GROUP BY c.id, c.name 
                              ORDER BY request_count DESC 
                              LIMIT 5";
        $topCustomersStmt = $pdo->query($topCustomersQuery);
        $topCustomers = $topCustomersStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'total_requests' => (int)$totalRequests,
                'status_breakdown' => $statusBreakdown,
                'today_requests' => (int)$todayRequests,
                'week_requests' => (int)$weekRequests,
                'overdue_requests' => (int)$overdueRequests,
                'top_customers' => $topCustomers
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Error getting borrowing request stats: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to retrieve statistics']);
    }
}

function handleBulkAction() {
    global $pdo, $currentAdmin;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $request_ids = $input['request_ids'] ?? [];
    
    if (empty($action) || empty($request_ids)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Action and request IDs are required']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        $successCount = 0;
        $errors = [];
        
        foreach ($request_ids as $id) {
            try {
                switch ($action) {
                    case 'approve':
                        $stmt = $pdo->prepare("UPDATE Borrowing_Request SET status = 'approved', approved_by = :admin_id, approved_date = NOW() WHERE id = :id AND status = 'pending'");
                        $stmt->execute([':admin_id' => $currentAdmin['id'], ':id' => $id]);
                        break;
                    case 'reject':
                        $stmt = $pdo->prepare("UPDATE Borrowing_Request SET status = 'rejected', approved_by = :admin_id, approved_date = NOW() WHERE id = :id AND status = 'pending'");
                        $stmt->execute([':admin_id' => $currentAdmin['id'], ':id' => $id]);
                        break;
                    case 'delete':
                        // Check if deletable
                        $checkStmt = $pdo->prepare("SELECT status FROM Borrowing_Request WHERE id = :id");
                        $checkStmt->execute([':id' => $id]);
                        $status = $checkStmt->fetch()['status'];
                        
                        if (!in_array($status, ['active', 'overdue'])) {
                            // Delete related records
                            $pdo->prepare("DELETE FROM Return_Items WHERE borrowing_transaction_id IN (SELECT id FROM Borrowing_Transaction WHERE borrowing_request_id = :id)")->execute([':id' => $id]);
                            $pdo->prepare("DELETE FROM Borrowing_Transaction WHERE borrowing_request_id = :id")->execute([':id' => $id]);
                            $pdo->prepare("DELETE FROM Borrowing_Items WHERE borrowing_request_id = :id")->execute([':id' => $id]);
                            $pdo->prepare("DELETE FROM Borrowing_Request WHERE id = :id")->execute([':id' => $id]);
                        } else {
                            $errors[] = "Request #$id cannot be deleted (active/overdue)";
                            continue 2;
                        }
                        break;
                    default:
                        $errors[] = "Invalid action: $action";
                        continue 2;
                }
                $successCount++;
            } catch (Exception $e) {
                $errors[] = "Failed to process request #$id: " . $e->getMessage();
            }
        }
        
        // Log the activity
        $logQuery = "INSERT INTO Activity_Log (admin_id, action, description, timestamp) 
                     VALUES (:admin_id, 'BULK_UPDATE', :description, NOW())";
        $logStmt = $pdo->prepare($logQuery);
        $logStmt->bindValue(':admin_id', $currentAdmin['id']);
        $logStmt->bindValue(':description', "Bulk $action on $successCount requests");
        $logStmt->execute();
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "$successCount requests processed successfully",
            'success_count' => $successCount,
            'errors' => $errors
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error in bulk action: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to process bulk action']);
    }
}

function handleExport() {
    global $pdo;
    
    $format = $_GET['format'] ?? 'csv';
    $request_ids = $_GET['request_ids'] ?? null;
    
    $whereClause = "";
    $params = [];
    
    if ($request_ids) {
        $ids = explode(',', $request_ids);
        $ids = array_filter($ids, 'is_numeric');
        if (!empty($ids)) {
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $whereClause = "WHERE br.id IN ($placeholders)";
            $params = $ids;
        }
    }
    
    $query = "SELECT br.*, 
                     c.name as customer_name, c.email as customer_email,
                     e.name as employee_name, e.employee_id,
                     l.name as location_name,
                     a.name as approved_by_name
              FROM Borrowing_Request br 
              LEFT JOIN Customer c ON br.customer_id = c.id 
              LEFT JOIN Employee e ON br.employee_id = e.id 
              LEFT JOIN Location l ON br.location_id = l.id
              LEFT JOIN Admin a ON br.approved_by = a.id
              $whereClause
              ORDER BY br.request_date DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="borrowing_requests_export_' . date('Y-m-d_H-i-s') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV Headers
        fputcsv($output, [
            'ID', 'Customer', 'Employee', 'Location', 'Request Date', 'Required Date',
            'Status', 'Purpose', 'Approved By', 'Approved Date', 'Notes'
        ]);
        
        // CSV Data
        foreach ($requests as $request) {
            fputcsv($output, [
                $request['id'],
                $request['customer_name'],
                $request['employee_name'] . ' (' . $request['employee_id'] . ')',
                $request['location_name'],
                $request['request_date'],
                $request['required_date'] ?? '',
                $request['status'],
                $request['purpose'],
                $request['approved_by_name'] ?? '',
                $request['approved_date'] ?? '',
                $request['notes'] ?? ''
            ]);
        }
        
        fclose($output);
    } else {
        // JSON format
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="borrowing_requests_export_' . date('Y-m-d_H-i-s') . '.json"');
        
        echo json_encode([
            'export_date' => date('Y-m-d H:i:s'),
            'total_requests' => count($requests),
            'data' => $requests
        ], JSON_PRETTY_PRINT);
    }
}

function handleGetRequestItems() {
    global $pdo;
    
    $request_id = $_GET['request_id'] ?? null;
    if (!$request_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Request ID is required']);
        return;
    }
    
    $query = "SELECT bi.*, m.name as material_name, m.unit, m.price_per_unit,
                     mc.name as category_name,
                     COALESCE(SUM(i.quantity), 0) as available_quantity
              FROM Borrowing_Items bi
              INNER JOIN Material m ON bi.material_id = m.id
              LEFT JOIN Material_Categories mc ON m.category_id = mc.id
              LEFT JOIN Inventory i ON m.id = i.material_id
              WHERE bi.borrowing_request_id = :request_id
              GROUP BY bi.id
              ORDER BY m.name";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':request_id', $request_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $items]);
}

function handleUpdateRequestItems() {
    global $pdo, $currentAdmin;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $request_id = $input['request_id'] ?? null;
    $items = $input['items'] ?? [];
    
    if (!$request_id || empty($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Request ID and items are required']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Delete existing items
        $deleteQuery = "DELETE FROM Borrowing_Items WHERE borrowing_request_id = :request_id";
        $deleteStmt = $pdo->prepare($deleteQuery);
        $deleteStmt->bindValue(':request_id', $request_id, PDO::PARAM_INT);
        $deleteStmt->execute();
        
        // Insert updated items
        $insertQuery = "INSERT INTO Borrowing_Items (borrowing_request_id, material_id, quantity_requested, quantity_approved, unit_price) 
                        VALUES (:request_id, :material_id, :quantity_requested, :quantity_approved, :unit_price)";
        $insertStmt = $pdo->prepare($insertQuery);
        
        foreach ($items as $item) {
            $insertStmt->bindValue(':request_id', $request_id, PDO::PARAM_INT);
            $insertStmt->bindValue(':material_id', $item['material_id'], PDO::PARAM_INT);
            $insertStmt->bindValue(':quantity_requested', $item['quantity_requested'], PDO::PARAM_INT);
            $insertStmt->bindValue(':quantity_approved', $item['quantity_approved'] ?? null);
            $insertStmt->bindValue(':unit_price', $item['unit_price']);
            $insertStmt->execute();
        }
        
        // Log the activity
        $logQuery = "INSERT INTO Activity_Log (admin_id, action, description, timestamp) 
                     VALUES (:admin_id, 'UPDATE', :description, NOW())";
        $logStmt = $pdo->prepare($logQuery);
        $logStmt->bindValue(':admin_id', $currentAdmin['id']);
        $logStmt->bindValue(':description', "Updated items for borrowing request #$request_id");
        $logStmt->execute();
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Request items updated successfully']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error updating request items: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update request items']);
    }
}

function handleCheckAvailability() {
    global $pdo;
    
    $material_id = $_GET['material_id'] ?? null;
    $location_id = $_GET['location_id'] ?? null;
    $quantity = $_GET['quantity'] ?? 0;
    
    if (!$material_id || !$location_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Material ID and location ID are required']);
        return;
    }
    
    $query = "SELECT COALESCE(SUM(quantity), 0) as available_quantity
              FROM Inventory 
              WHERE material_id = :material_id AND location_id = :location_id";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':material_id', $material_id, PDO::PARAM_INT);
    $stmt->bindValue(':location_id', $location_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $available = $stmt->fetch()['available_quantity'];
    $isAvailable = $available >= $quantity;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'available_quantity' => (int)$available,
            'requested_quantity' => (int)$quantity,
            'is_available' => $isAvailable,
            'shortage' => $isAvailable ? 0 : ($quantity - $available)
        ]
    ]);
}

function handleGetOverdueRequests() {
    global $pdo;
    
    $query = "SELECT br.*, c.name as customer_name, e.name as employee_name,
                     DATEDIFF(CURDATE(), br.required_date) as days_overdue
              FROM Borrowing_Request br
              LEFT JOIN Customer c ON br.customer_id = c.id
              LEFT JOIN Employee e ON br.employee_id = e.id
              WHERE br.status = 'active' AND br.required_date < CURDATE()
              ORDER BY br.required_date ASC";
    
    $stmt = $pdo->query($query);
    $overdueRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Update status to overdue
    if (!empty($overdueRequests)) {
        $updateQuery = "UPDATE Borrowing_Request SET status = 'overdue' 
                        WHERE status = 'active' AND required_date < CURDATE()";
        $pdo->query($updateQuery);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $overdueRequests
    ]);
}

function handleGetItemTypes() {
    global $pdo;
    
    try {
        $query = "SELECT id, name, description, unit, estimated_value 
                  FROM Borrowing_Item_Types 
                  ORDER BY name ASC";
        
        $stmt = $pdo->query($query);
        $itemTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $itemTypes
        ]);
        
    } catch (Exception $e) {
        error_log("Error fetching item types: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'error' => 'Failed to fetch item types'
        ]);
    }
}
?>
?>
