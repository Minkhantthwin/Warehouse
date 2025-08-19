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
                getReturnItems();
            } elseif ($action === 'get' && isset($_GET['id'])) {
                getReturnItem($_GET['id']);
            } elseif ($action === 'export') {
                exportReturnItems();
            } else {
                getReturnItems();
            }
            break;
            
        case 'POST':
            if ($action === 'create') {
                createReturnItem($jsonInput);
            } elseif ($action === 'update') {
                updateReturnItem($jsonInput);
            } elseif ($action === 'delete') {
                deleteReturnItem($jsonInput);
            } elseif ($action === 'bulk_delete') {
                bulkDeleteReturnItems($jsonInput);
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

function getReturnItems() {
    global $pdo;
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 10);
    $offset = ($page - 1) * $limit;
    
    $whereClause = "WHERE 1=1";
    $params = [];
    
    // Search filter
    if (!empty($_GET['search'])) {
        $whereClause .= " AND (bi.item_description LIKE :search OR ri.damage_notes LIKE :search OR c.name LIKE :search OR e.name LIKE :search)";
        $params['search'] = "%" . $_GET['search'] . "%";
    }
    
    // Condition status filter
    if (!empty($_GET['condition_status'])) {
        $whereClause .= " AND ri.condition_status = :condition_status";
        $params['condition_status'] = $_GET['condition_status'];
    }
    
    // Transaction type filter
    if (!empty($_GET['transaction_type'])) {
        $whereClause .= " AND bt.transaction_type = :transaction_type";
        $params['transaction_type'] = $_GET['transaction_type'];
    }
    
    // Request status filter
    if (!empty($_GET['request_status'])) {
        $whereClause .= " AND br.status = :request_status";
        $params['request_status'] = $_GET['request_status'];
    }
    
    // Customer filter
    if (!empty($_GET['customer_id'])) {
        $whereClause .= " AND br.customer_id = :customer_id";
        $params['customer_id'] = $_GET['customer_id'];
    }
    
    // Date range filter
    if (!empty($_GET['date_from'])) {
        $whereClause .= " AND DATE(ri.return_date) >= :date_from";
        $params['date_from'] = $_GET['date_from'];
    }
    
    if (!empty($_GET['date_to'])) {
        $whereClause .= " AND DATE(ri.return_date) <= :date_to";
        $params['date_to'] = $_GET['date_to'];
    }
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total 
                   FROM Return_Items ri 
                   INNER JOIN Borrowing_Transaction bt ON ri.borrowing_transaction_id = bt.id 
                   INNER JOIN Borrowing_Request br ON bt.borrowing_request_id = br.id 
                   INNER JOIN Customer c ON br.customer_id = c.id 
                   INNER JOIN Employee e ON br.employee_id = e.id 
                   INNER JOIN Borrowing_Items bi ON ri.borrowing_item_id = bi.id 
                   $whereClause";
    
    $countStmt = $pdo->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue(":$key", $value);
    }
    $countStmt->execute();
    $totalRecords = $countStmt->fetch()['total'];
    
    // Get return items
    $query = "SELECT ri.*, 
                     bi.item_description,
                     bi.quantity_requested,
                     bi.quantity_approved,
                     bi.quantity_borrowed,
                     bit.name as item_type_name,
                     bit.unit as item_type_unit,
                     bt.transaction_type,
                     bt.transaction_date,
                     bt.notes as transaction_notes,
                     pe.name as processed_by_name,
                     pe.employee_id as processed_by_employee_id,
                     br.id as request_id,
                     br.purpose as request_purpose,
                     br.status as request_status,
                     br.request_date,
                     br.required_date,
                     c.name as customer_name,
                     c.customer_type,
                     e.name as employee_name,
                     l.name as location_name,
                     l.city as location_city,
                     (SELECT COUNT(*) FROM Damage_Report dr WHERE dr.return_item_id = ri.id) as damage_reports_count
              FROM Return_Items ri 
              INNER JOIN Borrowing_Transaction bt ON ri.borrowing_transaction_id = bt.id 
              INNER JOIN Borrowing_Request br ON bt.borrowing_request_id = br.id 
              INNER JOIN Customer c ON br.customer_id = c.id 
              INNER JOIN Employee e ON br.employee_id = e.id 
              INNER JOIN Borrowing_Items bi ON ri.borrowing_item_id = bi.id 
              LEFT JOIN Borrowing_Item_Types bit ON bi.item_type_id = bit.id 
              LEFT JOIN Employee pe ON bt.processed_by = pe.id 
              LEFT JOIN Location l ON br.location_id = l.id 
              $whereClause 
              ORDER BY ri.return_date DESC, ri.id DESC 
              LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($query);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $returnItems = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $returnItems,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($totalRecords / $limit),
            'total_records' => $totalRecords,
            'limit' => $limit
        ]
    ]);
}

function getReturnItem($id) {
    global $pdo;
    
    $query = "SELECT ri.*, 
                     bi.item_description,
                     bi.quantity_requested,
                     bi.quantity_approved,
                     bi.quantity_borrowed,
                     bit.name as item_type_name,
                     bit.unit as item_type_unit,
                     bt.transaction_type,
                     bt.transaction_date,
                     bt.notes as transaction_notes,
                     pe.name as processed_by_name,
                     pe.employee_id as processed_by_employee_id,
                     br.id as request_id,
                     br.purpose as request_purpose,
                     br.status as request_status,
                     br.request_date,
                     br.required_date,
                     c.name as customer_name,
                     c.customer_type,
                     e.name as employee_name,
                     l.name as location_name,
                     l.city as location_city,
                     (SELECT COUNT(*) FROM Damage_Report dr WHERE dr.return_item_id = ri.id) as damage_reports_count
              FROM Return_Items ri 
              INNER JOIN Borrowing_Transaction bt ON ri.borrowing_transaction_id = bt.id 
              INNER JOIN Borrowing_Request br ON bt.borrowing_request_id = br.id 
              INNER JOIN Customer c ON br.customer_id = c.id 
              INNER JOIN Employee e ON br.employee_id = e.id 
              INNER JOIN Borrowing_Items bi ON ri.borrowing_item_id = bi.id 
              LEFT JOIN Borrowing_Item_Types bit ON bi.item_type_id = bit.id 
              LEFT JOIN Employee pe ON bt.processed_by = pe.id 
              LEFT JOIN Location l ON br.location_id = l.id 
              WHERE ri.id = :id";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['id' => $id]);
    $returnItem = $stmt->fetch();
    
    if (!$returnItem) {
        http_response_code(404);
        echo json_encode(['error' => 'Return item not found']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $returnItem
    ]);
}

function createReturnItem($data) {
    global $pdo;
    
    // Validate required fields
    $required = ['borrowing_transaction_id', 'borrowing_item_id', 'quantity_returned', 'condition_status'];
    foreach ($required as $field) {
        if (empty($data[$field]) && $data[$field] !== '0') {
            http_response_code(400);
            echo json_encode(['error' => "Field '$field' is required"]);
            return;
        }
    }
    
    // Validate borrowing transaction exists
    $stmt = $pdo->prepare("SELECT id FROM Borrowing_Transaction WHERE id = :id");
    $stmt->execute(['id' => $data['borrowing_transaction_id']]);
    if (!$stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid borrowing transaction ID']);
        return;
    }
    
    // Validate borrowing item exists
    $stmt = $pdo->prepare("SELECT id FROM Borrowing_Items WHERE id = :id");
    $stmt->execute(['id' => $data['borrowing_item_id']]);
    if (!$stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid borrowing item ID']);
        return;
    }
    
    try {
        $query = "INSERT INTO Return_Items (
                    borrowing_transaction_id, borrowing_item_id, quantity_returned, 
                    condition_status, damage_notes
                  ) VALUES (
                    :borrowing_transaction_id, :borrowing_item_id, :quantity_returned, 
                    :condition_status, :damage_notes
                  )";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'borrowing_transaction_id' => $data['borrowing_transaction_id'],
            'borrowing_item_id' => $data['borrowing_item_id'],
            'quantity_returned' => $data['quantity_returned'],
            'condition_status' => $data['condition_status'],
            'damage_notes' => $data['damage_notes'] ?? null
        ]);
        
        $returnItemId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Return item created successfully',
            'data' => ['id' => $returnItemId]
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create return item: ' . $e->getMessage()]);
    }
}

function updateReturnItem($data) {
    global $pdo;
    
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Return item ID is required']);
        return;
    }
    
    // Check if return item exists
    $stmt = $pdo->prepare("SELECT id FROM Return_Items WHERE id = :id");
    $stmt->execute(['id' => $data['id']]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Return item not found']);
        return;
    }
    
    try {
        $query = "UPDATE Return_Items SET 
                    quantity_returned = :quantity_returned,
                    condition_status = :condition_status,
                    damage_notes = :damage_notes
                  WHERE id = :id";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'id' => $data['id'],
            'quantity_returned' => $data['quantity_returned'],
            'condition_status' => $data['condition_status'],
            'damage_notes' => $data['damage_notes'] ?? null
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Return item updated successfully'
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update return item: ' . $e->getMessage()]);
    }
}

function deleteReturnItem($data) {
    global $pdo;
    
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Return item ID is required']);
        return;
    }
    
    try {
        // Check if there are related damage reports
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM Damage_Report WHERE return_item_id = :id");
        $stmt->execute(['id' => $data['id']]);
        $damageReportsCount = $stmt->fetch()['count'];
        
        if ($damageReportsCount > 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot delete return item that has associated damage reports']);
            return;
        }
        
        $stmt = $pdo->prepare("DELETE FROM Return_Items WHERE id = :id");
        $stmt->execute(['id' => $data['id']]);
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Return item not found']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Return item deleted successfully'
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete return item: ' . $e->getMessage()]);
    }
}

function bulkDeleteReturnItems($data) {
    global $pdo;
    
    if (empty($data['ids']) || !is_array($data['ids'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Return item IDs array is required']);
        return;
    }
    
    try {
        // Check if any have related damage reports
        $placeholders = str_repeat('?,', count($data['ids']) - 1) . '?';
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM Damage_Report WHERE return_item_id IN ($placeholders)");
        $stmt->execute($data['ids']);
        $damageReportsCount = $stmt->fetch()['count'];
        
        if ($damageReportsCount > 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot delete return items that have associated damage reports']);
            return;
        }
        
        $stmt = $pdo->prepare("DELETE FROM Return_Items WHERE id IN ($placeholders)");
        $stmt->execute($data['ids']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Return items deleted successfully',
            'data' => ['deleted_count' => $stmt->rowCount()]
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete return items: ' . $e->getMessage()]);
    }
}

function exportReturnItems() {
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
        $whereClause .= " AND (bi.item_description LIKE :search OR ri.damage_notes LIKE :search OR c.name LIKE :search)";
        $params['search'] = "%" . $_GET['search'] . "%";
    }
    
    if (!empty($_GET['condition_status'])) {
        $whereClause .= " AND ri.condition_status = :condition_status";
        $params['condition_status'] = $_GET['condition_status'];
    }
    
    if (!empty($_GET['transaction_type'])) {
        $whereClause .= " AND bt.transaction_type = :transaction_type";
        $params['transaction_type'] = $_GET['transaction_type'];
    }
    
    $query = "SELECT ri.id, ri.quantity_returned, ri.condition_status, ri.damage_notes, ri.return_date,
                     bi.item_description, bi.quantity_requested, bi.quantity_approved, bi.quantity_borrowed,
                     bit.name as item_type_name, bt.transaction_type, bt.transaction_date,
                     br.id as request_id, br.purpose as request_purpose, br.status as request_status,
                     c.name as customer_name, e.name as employee_name, l.name as location_name,
                     pe.name as processed_by_name, pe.employee_id as processed_by_employee_id
              FROM Return_Items ri 
              INNER JOIN Borrowing_Transaction bt ON ri.borrowing_transaction_id = bt.id 
              INNER JOIN Borrowing_Request br ON bt.borrowing_request_id = br.id 
              INNER JOIN Customer c ON br.customer_id = c.id 
              INNER JOIN Employee e ON br.employee_id = e.id 
              INNER JOIN Borrowing_Items bi ON ri.borrowing_item_id = bi.id 
              LEFT JOIN Borrowing_Item_Types bit ON bi.item_type_id = bit.id 
              LEFT JOIN Employee pe ON bt.processed_by = pe.id 
              LEFT JOIN Location l ON br.location_id = l.id 
              $whereClause 
              ORDER BY ri.return_date DESC, ri.id DESC";
    
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->execute();
    
    $returnItems = $stmt->fetchAll();
    
    // Set CSV headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="return_items_' . date('Y-m-d_H-i-s') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, [
        'ID', 'Item Description', 'Item Type', 'Quantity Returned', 'Condition Status', 'Damage Notes', 'Return Date',
        'Transaction Type', 'Transaction Date', 'Request ID', 'Request Purpose', 'Request Status',
        'Customer', 'Employee', 'Location', 'Processed By', 'Employee ID', 
        'Qty Requested', 'Qty Approved', 'Qty Borrowed'
    ]);
    
    // CSV data
    foreach ($returnItems as $item) {
        fputcsv($output, [
            $item['id'],
            $item['item_description'],
            $item['item_type_name'],
            $item['quantity_returned'],
            $item['condition_status'],
            $item['damage_notes'],
            $item['return_date'],
            $item['transaction_type'],
            $item['transaction_date'],
            $item['request_id'],
            $item['request_purpose'],
            $item['request_status'],
            $item['customer_name'],
            $item['employee_name'],
            $item['location_name'],
            $item['processed_by_name'],
            $item['processed_by_employee_id'],
            $item['quantity_requested'],
            $item['quantity_approved'],
            $item['quantity_borrowed']
        ]);
    }
    
    fclose($output);
    exit;
}
?>
