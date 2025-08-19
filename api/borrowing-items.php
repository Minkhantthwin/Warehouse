<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Check remember me token
checkRememberMe();

// Require login
requireLogin();

// Check permission for borrowing management
if (!hasPermission('borrowing_management')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Get JSON input for POST requests
$jsonInput = null;
if ($method === 'POST') {
    $jsonInput = json_decode(file_get_contents('php://input'), true);
    if ($jsonInput && isset($jsonInput['action'])) {
        $action = $jsonInput['action'];
    }
}

try {
    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                getBorrowingItems();
            } elseif ($action === 'get' && isset($_GET['id'])) {
                getBorrowingItem($_GET['id']);
            } elseif ($action === 'export') {
                exportBorrowingItems();
            } else {
                getBorrowingItems();
            }
            break;
            
        case 'POST':
            if ($action === 'create') {
                createBorrowingItem($jsonInput);
            } elseif ($action === 'update') {
                updateBorrowingItem($jsonInput);
            } elseif ($action === 'delete') {
                deleteBorrowingItem($jsonInput);
            } elseif ($action === 'bulk_delete') {
                bulkDeleteBorrowingItems($jsonInput);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
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

function getBorrowingItems() {
    global $pdo;
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 10);
    $offset = ($page - 1) * $limit;
    
    $whereClause = "WHERE 1=1";
    $params = [];
    
    // Search filter
    if (!empty($_GET['search'])) {
        $whereClause .= " AND (bi.item_description LIKE :search OR bt.name LIKE :search OR br.purpose LIKE :search OR c.name LIKE :search)";
        $params['search'] = "%" . $_GET['search'] . "%";
    }
    
    // Status filter (based on borrowing request status)
    if (!empty($_GET['status'])) {
        $whereClause .= " AND br.status = :status";
        $params['status'] = $_GET['status'];
    }
    
    // Item type filter
    if (!empty($_GET['item_type_id'])) {
        $whereClause .= " AND bi.item_type_id = :item_type_id";
        $params['item_type_id'] = $_GET['item_type_id'];
    }
    
    // Borrowing request filter
    if (!empty($_GET['borrowing_request_id'])) {
        $whereClause .= " AND bi.borrowing_request_id = :borrowing_request_id";
        $params['borrowing_request_id'] = $_GET['borrowing_request_id'];
    }
    
    // Date range filter
    if (!empty($_GET['date_from'])) {
        $whereClause .= " AND DATE(br.request_date) >= :date_from";
        $params['date_from'] = $_GET['date_from'];
    }
    
    if (!empty($_GET['date_to'])) {
        $whereClause .= " AND DATE(br.request_date) <= :date_to";
        $params['date_to'] = $_GET['date_to'];
    }
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total 
                   FROM Borrowing_Items bi 
                   INNER JOIN Borrowing_Request br ON bi.borrowing_request_id = br.id 
                   INNER JOIN Customer c ON br.customer_id = c.id 
                   LEFT JOIN Borrowing_Item_Types bt ON bi.item_type_id = bt.id 
                   $whereClause";
    
    $countStmt = $pdo->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue(":$key", $value);
    }
    $countStmt->execute();
    $totalRecords = $countStmt->fetch()['total'];
    
    // Get items
    $query = "SELECT bi.*, 
                     bt.name as item_type_name, 
                     bt.unit as item_type_unit,
                     br.id as request_id,
                     br.status as request_status,
                     br.request_date,
                     br.required_date,
                     br.purpose,
                     c.name as customer_name,
                     c.customer_type,
                     e.name as employee_name,
                     l.name as location_name,
                     l.city as location_city
              FROM Borrowing_Items bi 
              INNER JOIN Borrowing_Request br ON bi.borrowing_request_id = br.id 
              INNER JOIN Customer c ON br.customer_id = c.id 
              INNER JOIN Employee e ON br.employee_id = e.id 
              INNER JOIN Location l ON br.location_id = l.id 
              LEFT JOIN Borrowing_Item_Types bt ON bi.item_type_id = bt.id 
              $whereClause 
              ORDER BY br.request_date DESC, bi.id DESC 
              LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($query);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $items = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $items,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($totalRecords / $limit),
            'total_records' => $totalRecords,
            'limit' => $limit
        ]
    ]);
}

function getBorrowingItem($id) {
    global $pdo;
    
    $query = "SELECT bi.*, 
                     bt.name as item_type_name, 
                     bt.unit as item_type_unit,
                     br.id as request_id,
                     br.status as request_status,
                     br.request_date,
                     br.required_date,
                     br.purpose,
                     c.name as customer_name,
                     c.customer_type,
                     e.name as employee_name,
                     l.name as location_name,
                     l.city as location_city
              FROM Borrowing_Items bi 
              INNER JOIN Borrowing_Request br ON bi.borrowing_request_id = br.id 
              INNER JOIN Customer c ON br.customer_id = c.id 
              INNER JOIN Employee e ON br.employee_id = e.id 
              INNER JOIN Location l ON br.location_id = l.id 
              LEFT JOIN Borrowing_Item_Types bt ON bi.item_type_id = bt.id 
              WHERE bi.id = :id";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['id' => $id]);
    $item = $stmt->fetch();
    
    if (!$item) {
        http_response_code(404);
        echo json_encode(['error' => 'Borrowing item not found']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $item
    ]);
}

function createBorrowingItem($data) {
    global $pdo;
    
    // Validate required fields
    $required = ['borrowing_request_id', 'item_description', 'quantity_requested'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field '$field' is required"]);
            return;
        }
    }
    
    // Validate borrowing request exists
    $stmt = $pdo->prepare("SELECT id FROM Borrowing_Request WHERE id = :id");
    $stmt->execute(['id' => $data['borrowing_request_id']]);
    if (!$stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid borrowing request ID']);
        return;
    }
    
    // Validate item type if provided
    if (!empty($data['item_type_id'])) {
        $stmt = $pdo->prepare("SELECT id FROM Borrowing_Item_Types WHERE id = :id");
        $stmt->execute(['id' => $data['item_type_id']]);
        if (!$stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid item type ID']);
            return;
        }
    }
    
    try {
        $query = "INSERT INTO Borrowing_Items (
                    borrowing_request_id, item_type_id, item_description, 
                    quantity_requested, quantity_approved, quantity_borrowed, estimated_value
                  ) VALUES (
                    :borrowing_request_id, :item_type_id, :item_description, 
                    :quantity_requested, :quantity_approved, :quantity_borrowed, :estimated_value
                  )";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'borrowing_request_id' => $data['borrowing_request_id'],
            'item_type_id' => $data['item_type_id'] ?: null,
            'item_description' => $data['item_description'],
            'quantity_requested' => $data['quantity_requested'],
            'quantity_approved' => $data['quantity_approved'] ?? null,
            'quantity_borrowed' => $data['quantity_borrowed'] ?? null,
            'estimated_value' => $data['estimated_value'] ?? null
        ]);
        
        $itemId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Borrowing item created successfully',
            'data' => ['id' => $itemId]
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create borrowing item: ' . $e->getMessage()]);
    }
}

function updateBorrowingItem($data) {
    global $pdo;
    
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Item ID is required']);
        return;
    }
    
    // Check if item exists
    $stmt = $pdo->prepare("SELECT id FROM Borrowing_Items WHERE id = :id");
    $stmt->execute(['id' => $data['id']]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Borrowing item not found']);
        return;
    }
    
    // Validate item type if provided
    if (!empty($data['item_type_id'])) {
        $stmt = $pdo->prepare("SELECT id FROM Borrowing_Item_Types WHERE id = :id");
        $stmt->execute(['id' => $data['item_type_id']]);
        if (!$stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid item type ID']);
            return;
        }
    }
    
    try {
        $query = "UPDATE Borrowing_Items SET 
                    item_type_id = :item_type_id,
                    item_description = :item_description,
                    quantity_requested = :quantity_requested,
                    quantity_approved = :quantity_approved,
                    quantity_borrowed = :quantity_borrowed,
                    estimated_value = :estimated_value
                  WHERE id = :id";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'id' => $data['id'],
            'item_type_id' => $data['item_type_id'] ?: null,
            'item_description' => $data['item_description'],
            'quantity_requested' => $data['quantity_requested'],
            'quantity_approved' => $data['quantity_approved'] ?? null,
            'quantity_borrowed' => $data['quantity_borrowed'] ?? null,
            'estimated_value' => $data['estimated_value'] ?? null
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Borrowing item updated successfully'
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update borrowing item: ' . $e->getMessage()]);
    }
}

function deleteBorrowingItem($data) {
    global $pdo;
    
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Item ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM Borrowing_Items WHERE id = :id");
        $stmt->execute(['id' => $data['id']]);
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Borrowing item not found']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Borrowing item deleted successfully'
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete borrowing item: ' . $e->getMessage()]);
    }
}

function bulkDeleteBorrowingItems($data) {
    global $pdo;
    
    if (empty($data['ids']) || !is_array($data['ids'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Item IDs array is required']);
        return;
    }
    
    try {
        $placeholders = str_repeat('?,', count($data['ids']) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM Borrowing_Items WHERE id IN ($placeholders)");
        $stmt->execute($data['ids']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Borrowing items deleted successfully',
            'data' => ['deleted_count' => $stmt->rowCount()]
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete borrowing items: ' . $e->getMessage()]);
    }
}

function exportBorrowingItems() {
    global $pdo;
    
    $format = $_GET['format'] ?? 'csv';
    
    if ($format !== 'csv') {
        http_response_code(400);
        echo json_encode(['error' => 'Only CSV format is supported']);
        return;
    }
    
    $whereClause = "WHERE 1=1";
    $params = [];
    
    // Apply same filters as list function
    if (!empty($_GET['search'])) {
        $whereClause .= " AND (bi.item_description LIKE :search OR bt.name LIKE :search)";
        $params['search'] = "%" . $_GET['search'] . "%";
    }
    
    if (!empty($_GET['status'])) {
        $whereClause .= " AND br.status = :status";
        $params['status'] = $_GET['status'];
    }
    
    if (!empty($_GET['item_type_id'])) {
        $whereClause .= " AND bi.item_type_id = :item_type_id";
        $params['item_type_id'] = $_GET['item_type_id'];
    }
    
    $query = "SELECT bi.id, bi.item_description, bi.quantity_requested, bi.quantity_approved, 
                     bi.quantity_borrowed, bi.estimated_value,
                     bt.name as item_type_name, bt.unit as item_type_unit,
                     br.id as request_id, br.status as request_status, br.request_date, br.purpose,
                     c.name as customer_name, e.name as employee_name, l.name as location_name
              FROM Borrowing_Items bi 
              INNER JOIN Borrowing_Request br ON bi.borrowing_request_id = br.id 
              INNER JOIN Customer c ON br.customer_id = c.id 
              INNER JOIN Employee e ON br.employee_id = e.id 
              INNER JOIN Location l ON br.location_id = l.id 
              LEFT JOIN Borrowing_Item_Types bt ON bi.item_type_id = bt.id 
              $whereClause 
              ORDER BY br.request_date DESC, bi.id DESC";
    
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->execute();
    
    $items = $stmt->fetchAll();
    
    // Set CSV headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="borrowing_items_' . date('Y-m-d_H-i-s') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, [
        'ID', 'Request ID', 'Item Description', 'Item Type', 'Unit', 'Quantity Requested', 
        'Quantity Approved', 'Quantity Borrowed', 'Estimated Value', 'Request Status', 
        'Request Date', 'Purpose', 'Customer', 'Employee', 'Location'
    ]);
    
    // CSV data
    foreach ($items as $item) {
        fputcsv($output, [
            $item['id'],
            $item['request_id'],
            $item['item_description'],
            $item['item_type_name'],
            $item['item_type_unit'],
            $item['quantity_requested'],
            $item['quantity_approved'],
            $item['quantity_borrowed'],
            $item['estimated_value'],
            $item['request_status'],
            $item['request_date'],
            $item['purpose'],
            $item['customer_name'],
            $item['employee_name'],
            $item['location_name']
        ]);
    }
    
    fclose($output);
    exit;
}
?>
