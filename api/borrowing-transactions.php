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
                getBorrowingTransactions();
            } elseif ($action === 'get' && isset($_GET['id'])) {
                getBorrowingTransaction($_GET['id']);
            } elseif ($action === 'export') {
                exportBorrowingTransactions();
            } else {
                getBorrowingTransactions();
            }
            break;
            
        case 'POST':
            if ($action === 'create') {
                createBorrowingTransaction($jsonInput);
            } elseif ($action === 'update') {
                updateBorrowingTransaction($jsonInput);
            } elseif ($action === 'delete') {
                deleteBorrowingTransaction($jsonInput);
            } elseif ($action === 'bulk_delete') {
                bulkDeleteBorrowingTransactions($jsonInput);
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

function getBorrowingTransactions() {
    global $pdo;
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 10);
    $offset = ($page - 1) * $limit;
    
    $whereClause = "WHERE 1=1";
    $params = [];
    
    // Search filter
    if (!empty($_GET['search'])) {
        $whereClause .= " AND (bt.notes LIKE :search OR c.name LIKE :search OR e.name LIKE :search OR pe.name LIKE :search OR br.purpose LIKE :search)";
        $params['search'] = "%" . $_GET['search'] . "%";
    }
    
    // Transaction type filter
    if (!empty($_GET['transaction_type'])) {
        $whereClause .= " AND bt.transaction_type = :transaction_type";
        $params['transaction_type'] = $_GET['transaction_type'];
    }
    
    // Status filter (based on borrowing request status)
    if (!empty($_GET['status'])) {
        $whereClause .= " AND br.status = :status";
        $params['status'] = $_GET['status'];
    }
    
    // Borrowing request filter
    if (!empty($_GET['borrowing_request_id'])) {
        $whereClause .= " AND bt.borrowing_request_id = :borrowing_request_id";
        $params['borrowing_request_id'] = $_GET['borrowing_request_id'];
    }
    
    // Employee filter (processed by)
    if (!empty($_GET['processed_by'])) {
        $whereClause .= " AND bt.processed_by = :processed_by";
        $params['processed_by'] = $_GET['processed_by'];
    }
    
    // Date range filter
    if (!empty($_GET['date_from'])) {
        $whereClause .= " AND DATE(bt.transaction_date) >= :date_from";
        $params['date_from'] = $_GET['date_from'];
    }
    
    if (!empty($_GET['date_to'])) {
        $whereClause .= " AND DATE(bt.transaction_date) <= :date_to";
        $params['date_to'] = $_GET['date_to'];
    }
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total 
                   FROM Borrowing_Transaction bt 
                   INNER JOIN Borrowing_Request br ON bt.borrowing_request_id = br.id 
                   INNER JOIN Customer c ON br.customer_id = c.id 
                   INNER JOIN Employee e ON br.employee_id = e.id 
                   LEFT JOIN Employee pe ON bt.processed_by = pe.id 
                   $whereClause";
    
    $countStmt = $pdo->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue(":$key", $value);
    }
    $countStmt->execute();
    $totalRecords = $countStmt->fetch()['total'];
    
    // Get transactions
    $query = "SELECT bt.*, 
                     br.id as request_id,
                     br.status as request_status,
                     br.request_date,
                     br.required_date,
                     br.purpose,
                     c.name as customer_name,
                     c.customer_type,
                     e.name as employee_name,
                     l.name as location_name,
                     l.city as location_city,
                     pe.name as processed_by_name,
                     pe.employee_id as processed_by_employee_id,
                     COUNT(ri.id) as return_items_count
              FROM Borrowing_Transaction bt 
              INNER JOIN Borrowing_Request br ON bt.borrowing_request_id = br.id 
              INNER JOIN Customer c ON br.customer_id = c.id 
              INNER JOIN Employee e ON br.employee_id = e.id 
              INNER JOIN Location l ON br.location_id = l.id 
              LEFT JOIN Employee pe ON bt.processed_by = pe.id 
              LEFT JOIN Return_Items ri ON bt.id = ri.borrowing_transaction_id
              $whereClause 
              GROUP BY bt.id
              ORDER BY bt.transaction_date DESC, bt.id DESC 
              LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($query);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $transactions = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $transactions,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($totalRecords / $limit),
            'total_records' => $totalRecords,
            'limit' => $limit
        ]
    ]);
}

function getBorrowingTransaction($id) {
    global $pdo;
    
    $query = "SELECT bt.*, 
                     br.id as request_id,
                     br.status as request_status,
                     br.request_date,
                     br.required_date,
                     br.purpose,
                     c.name as customer_name,
                     c.customer_type,
                     e.name as employee_name,
                     l.name as location_name,
                     l.city as location_city,
                     pe.name as processed_by_name,
                     pe.employee_id as processed_by_employee_id
              FROM Borrowing_Transaction bt 
              INNER JOIN Borrowing_Request br ON bt.borrowing_request_id = br.id 
              INNER JOIN Customer c ON br.customer_id = c.id 
              INNER JOIN Employee e ON br.employee_id = e.id 
              INNER JOIN Location l ON br.location_id = l.id 
              LEFT JOIN Employee pe ON bt.processed_by = pe.id 
              WHERE bt.id = :id";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['id' => $id]);
    $transaction = $stmt->fetch();
    
    if (!$transaction) {
        http_response_code(404);
        echo json_encode(['error' => 'Borrowing transaction not found']);
        return;
    }
    
    // Get return items if this is a return transaction
    if (in_array($transaction['transaction_type'], ['return', 'partial_return'])) {
        $returnQuery = "SELECT ri.*, bi.item_description, bi.quantity_requested, bi.quantity_borrowed
                        FROM Return_Items ri 
                        INNER JOIN Borrowing_Items bi ON ri.borrowing_item_id = bi.id 
                        WHERE ri.borrowing_transaction_id = :transaction_id";
        
        $returnStmt = $pdo->prepare($returnQuery);
        $returnStmt->execute(['transaction_id' => $id]);
        $transaction['return_items'] = $returnStmt->fetchAll();
    }
    
    echo json_encode([
        'success' => true,
        'data' => $transaction
    ]);
}

function createBorrowingTransaction($data) {
    global $pdo;
    
    // Validate required fields
    $required = ['borrowing_request_id', 'transaction_type', 'processed_by'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field '$field' is required"]);
            return;
        }
    }
    
    // Validate borrowing request exists
    $stmt = $pdo->prepare("SELECT id, status FROM Borrowing_Request WHERE id = :id");
    $stmt->execute(['id' => $data['borrowing_request_id']]);
    $request = $stmt->fetch();
    if (!$request) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid borrowing request ID']);
        return;
    }
    
    // Validate employee exists
    $stmt = $pdo->prepare("SELECT id FROM Employee WHERE id = :id AND status = 'active'");
    $stmt->execute(['id' => $data['processed_by']]);
    if (!$stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid employee ID']);
        return;
    }
    
    // Validate transaction type
    $validTypes = ['borrow', 'return', 'partial_return'];
    if (!in_array($data['transaction_type'], $validTypes)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid transaction type']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Create transaction
        $query = "INSERT INTO Borrowing_Transaction (
                    borrowing_request_id, transaction_type, processed_by, notes
                  ) VALUES (
                    :borrowing_request_id, :transaction_type, :processed_by, :notes
                  )";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'borrowing_request_id' => $data['borrowing_request_id'],
            'transaction_type' => $data['transaction_type'],
            'processed_by' => $data['processed_by'],
            'notes' => $data['notes'] ?? null
        ]);
        
        $transactionId = $pdo->lastInsertId();
        
        // Update request status based on transaction type
        if ($data['transaction_type'] === 'borrow' && $request['status'] === 'approved') {
            $updateStmt = $pdo->prepare("UPDATE Borrowing_Request SET status = 'active' WHERE id = :id");
            $updateStmt->execute(['id' => $data['borrowing_request_id']]);
        } elseif (in_array($data['transaction_type'], ['return', 'partial_return']) && $request['status'] === 'active') {
            // Check if this is a full return (all items returned)
            if ($data['transaction_type'] === 'return') {
                $updateStmt = $pdo->prepare("UPDATE Borrowing_Request SET status = 'completed' WHERE id = :id");
                $updateStmt->execute(['id' => $data['borrowing_request_id']]);
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Borrowing transaction created successfully',
            'data' => ['id' => $transactionId]
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create borrowing transaction: ' . $e->getMessage()]);
    }
}

function updateBorrowingTransaction($data) {
    global $pdo;
    
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Transaction ID is required']);
        return;
    }
    
    // Check if transaction exists
    $stmt = $pdo->prepare("SELECT id FROM Borrowing_Transaction WHERE id = :id");
    $stmt->execute(['id' => $data['id']]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Borrowing transaction not found']);
        return;
    }
    
    // Validate employee if provided
    if (!empty($data['processed_by'])) {
        $stmt = $pdo->prepare("SELECT id FROM Employee WHERE id = :id AND status = 'active'");
        $stmt->execute(['id' => $data['processed_by']]);
        if (!$stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid employee ID']);
            return;
        }
    }
    
    // Validate transaction type if provided
    if (!empty($data['transaction_type'])) {
        $validTypes = ['borrow', 'return', 'partial_return'];
        if (!in_array($data['transaction_type'], $validTypes)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid transaction type']);
            return;
        }
    }
    
    try {
        $query = "UPDATE Borrowing_Transaction SET 
                    transaction_type = COALESCE(:transaction_type, transaction_type),
                    processed_by = COALESCE(:processed_by, processed_by),
                    notes = COALESCE(:notes, notes)
                  WHERE id = :id";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'id' => $data['id'],
            'transaction_type' => $data['transaction_type'] ?? null,
            'processed_by' => $data['processed_by'] ?? null,
            'notes' => $data['notes'] ?? null
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Borrowing transaction updated successfully'
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update borrowing transaction: ' . $e->getMessage()]);
    }
}

function deleteBorrowingTransaction($data) {
    global $pdo;
    
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Transaction ID is required']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Delete related return items first
        $stmt = $pdo->prepare("DELETE FROM Return_Items WHERE borrowing_transaction_id = :id");
        $stmt->execute(['id' => $data['id']]);
        
        // Delete the transaction
        $stmt = $pdo->prepare("DELETE FROM Borrowing_Transaction WHERE id = :id");
        $stmt->execute(['id' => $data['id']]);
        
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['error' => 'Borrowing transaction not found']);
            return;
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Borrowing transaction deleted successfully'
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete borrowing transaction: ' . $e->getMessage()]);
    }
}

function bulkDeleteBorrowingTransactions($data) {
    global $pdo;
    
    if (empty($data['ids']) || !is_array($data['ids'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Transaction IDs array is required']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Delete related return items first
        $placeholders = str_repeat('?,', count($data['ids']) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM Return_Items WHERE borrowing_transaction_id IN ($placeholders)");
        $stmt->execute($data['ids']);
        
        // Delete transactions
        $stmt = $pdo->prepare("DELETE FROM Borrowing_Transaction WHERE id IN ($placeholders)");
        $stmt->execute($data['ids']);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Borrowing transactions deleted successfully',
            'data' => ['deleted_count' => $stmt->rowCount()]
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete borrowing transactions: ' . $e->getMessage()]);
    }
}

function exportBorrowingTransactions() {
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
        $whereClause .= " AND (bt.notes LIKE :search OR c.name LIKE :search OR e.name LIKE :search)";
        $params['search'] = "%" . $_GET['search'] . "%";
    }
    
    if (!empty($_GET['transaction_type'])) {
        $whereClause .= " AND bt.transaction_type = :transaction_type";
        $params['transaction_type'] = $_GET['transaction_type'];
    }
    
    if (!empty($_GET['status'])) {
        $whereClause .= " AND br.status = :status";
        $params['status'] = $_GET['status'];
    }
    
    $query = "SELECT bt.id, bt.transaction_type, bt.transaction_date, bt.notes,
                     br.id as request_id, br.status as request_status, br.request_date, br.purpose,
                     c.name as customer_name, e.name as employee_name, l.name as location_name,
                     pe.name as processed_by_name, pe.employee_id as processed_by_employee_id
              FROM Borrowing_Transaction bt 
              INNER JOIN Borrowing_Request br ON bt.borrowing_request_id = br.id 
              INNER JOIN Customer c ON br.customer_id = c.id 
              INNER JOIN Employee e ON br.employee_id = e.id 
              INNER JOIN Location l ON br.location_id = l.id 
              LEFT JOIN Employee pe ON bt.processed_by = pe.id 
              $whereClause 
              ORDER BY bt.transaction_date DESC, bt.id DESC";
    
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->execute();
    
    $transactions = $stmt->fetchAll();
    
    // Set CSV headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="borrowing_transactions_' . date('Y-m-d_H-i-s') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, [
        'ID', 'Request ID', 'Transaction Type', 'Transaction Date', 'Customer', 'Employee', 
        'Location', 'Processed By', 'Processed By ID', 'Request Status', 'Request Date', 
        'Purpose', 'Notes'
    ]);
    
    // CSV data
    foreach ($transactions as $transaction) {
        fputcsv($output, [
            $transaction['id'],
            $transaction['request_id'],
            $transaction['transaction_type'],
            $transaction['transaction_date'],
            $transaction['customer_name'],
            $transaction['employee_name'],
            $transaction['location_name'],
            $transaction['processed_by_name'],
            $transaction['processed_by_employee_id'],
            $transaction['request_status'],
            $transaction['request_date'],
            $transaction['purpose'],
            $transaction['notes']
        ]);
    }
    
    fclose($output);
    exit;
}
?>
