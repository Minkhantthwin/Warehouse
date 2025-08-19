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
                getDamageReports();
            } elseif ($action === 'get' && isset($_GET['id'])) {
                getDamageReport($_GET['id']);
            } elseif ($action === 'export') {
                exportDamageReports();
            } else {
                getDamageReports();
            }
            break;
            
        case 'POST':
            if ($action === 'create') {
                createDamageReport($jsonInput);
            } elseif ($action === 'update') {
                updateDamageReport($jsonInput);
            } elseif ($action === 'delete') {
                deleteDamageReport($jsonInput);
            } elseif ($action === 'bulk_delete') {
                bulkDeleteDamageReports($jsonInput);
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

function getDamageReports() {
    global $pdo;
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 10);
    $offset = ($page - 1) * $limit;
    
    $whereClause = "WHERE 1=1";
    $params = [];
    
    // Search filter
    if (!empty($_GET['search'])) {
        $whereClause .= " AND (dr.damage_type LIKE :search OR dr.damage_description LIKE :search OR bi.item_description LIKE :search OR e.name LIKE :search OR c.name LIKE :search)";
        $params['search'] = "%" . $_GET['search'] . "%";
    }
    
    // Damage type filter
    if (!empty($_GET['damage_type'])) {
        $whereClause .= " AND dr.damage_type = :damage_type";
        $params['damage_type'] = $_GET['damage_type'];
    }
    
    // Condition status filter
    if (!empty($_GET['condition_status'])) {
        $whereClause .= " AND ri.condition_status = :condition_status";
        $params['condition_status'] = $_GET['condition_status'];
    }
    
    // Employee filter
    if (!empty($_GET['reported_by'])) {
        $whereClause .= " AND dr.reported_by = :reported_by";
        $params['reported_by'] = $_GET['reported_by'];
    }
    
    // Date range filter
    if (!empty($_GET['date_from'])) {
        $whereClause .= " AND DATE(dr.report_date) >= :date_from";
        $params['date_from'] = $_GET['date_from'];
    }
    
    if (!empty($_GET['date_to'])) {
        $whereClause .= " AND DATE(dr.report_date) <= :date_to";
        $params['date_to'] = $_GET['date_to'];
    }
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total 
                   FROM Damage_Report dr 
                   INNER JOIN Return_Items ri ON dr.return_item_id = ri.id 
                   INNER JOIN Borrowing_Transaction bt ON ri.borrowing_transaction_id = bt.id 
                   INNER JOIN Borrowing_Request br ON bt.borrowing_request_id = br.id 
                   INNER JOIN Customer c ON br.customer_id = c.id 
                   INNER JOIN Employee e ON dr.reported_by = e.id 
                   $whereClause";
    
    $countStmt = $pdo->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue(":$key", $value);
    }
    $countStmt->execute();
    $totalRecords = $countStmt->fetch()['total'];
    
    // Get damage reports
    $query = "SELECT dr.*, 
                     bi.item_description as return_item_description,
                     ri.quantity_returned,
                     ri.condition_status,
                     ri.damage_notes as return_damage_notes,
                     ri.return_date,
                     bt.transaction_type,
                     bt.transaction_date,
                     br.id as request_id,
                     br.purpose as request_purpose,
                     br.status as request_status,
                     c.name as customer_name,
                     c.customer_type,
                     e.name as reported_by_name,
                     e.employee_id as reported_by_employee_id,
                     bi.item_description as borrowed_item_description,
                     bit.name as item_type_name
              FROM Damage_Report dr 
              INNER JOIN Return_Items ri ON dr.return_item_id = ri.id 
              INNER JOIN Borrowing_Transaction bt ON ri.borrowing_transaction_id = bt.id 
              INNER JOIN Borrowing_Request br ON bt.borrowing_request_id = br.id 
              INNER JOIN Customer c ON br.customer_id = c.id 
              INNER JOIN Employee e ON dr.reported_by = e.id 
              INNER JOIN Borrowing_Items bi ON ri.borrowing_item_id = bi.id 
              LEFT JOIN Borrowing_Item_Types bit ON bi.item_type_id = bit.id 
              $whereClause 
              ORDER BY dr.report_date DESC, dr.id DESC 
              LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($query);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $reports = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $reports,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($totalRecords / $limit),
            'total_records' => $totalRecords,
            'limit' => $limit
        ]
    ]);
}

function getDamageReport($id) {
    global $pdo;
    
    $query = "SELECT dr.*, 
                     bi.item_description as return_item_description,
                     ri.quantity_returned,
                     ri.condition_status,
                     ri.damage_notes as return_damage_notes,
                     ri.return_date,
                     bt.transaction_type,
                     bt.transaction_date,
                     br.id as request_id,
                     br.purpose as request_purpose,
                     br.status as request_status,
                     c.name as customer_name,
                     c.customer_type,
                     e.name as reported_by_name,
                     e.employee_id as reported_by_employee_id,
                     bi.item_description as borrowed_item_description,
                     bit.name as item_type_name
              FROM Damage_Report dr 
              INNER JOIN Return_Items ri ON dr.return_item_id = ri.id 
              INNER JOIN Borrowing_Transaction bt ON ri.borrowing_transaction_id = bt.id 
              INNER JOIN Borrowing_Request br ON bt.borrowing_request_id = br.id 
              INNER JOIN Customer c ON br.customer_id = c.id 
              INNER JOIN Employee e ON dr.reported_by = e.id 
              INNER JOIN Borrowing_Items bi ON ri.borrowing_item_id = bi.id 
              LEFT JOIN Borrowing_Item_Types bit ON bi.item_type_id = bit.id 
              WHERE dr.id = :id";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['id' => $id]);
    $report = $stmt->fetch();
    
    if (!$report) {
        http_response_code(404);
        echo json_encode(['error' => 'Damage report not found']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $report
    ]);
}

function createDamageReport($data) {
    global $pdo;
    
    // Validate required fields
    $required = ['return_item_id', 'damage_type', 'damage_description', 'reported_by'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field '$field' is required"]);
            return;
        }
    }
    
    // Validate return item exists
    $stmt = $pdo->prepare("SELECT id FROM Return_Items WHERE id = :id");
    $stmt->execute(['id' => $data['return_item_id']]);
    if (!$stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid return item ID']);
        return;
    }
    
    // Validate employee exists
    $stmt = $pdo->prepare("SELECT id FROM Employee WHERE id = :id");
    $stmt->execute(['id' => $data['reported_by']]);
    if (!$stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid employee ID']);
        return;
    }
    
    try {
        $query = "INSERT INTO Damage_Report (
                    return_item_id, damage_type, damage_description, 
                    repair_cost, replacement_cost, reported_by
                  ) VALUES (
                    :return_item_id, :damage_type, :damage_description, 
                    :repair_cost, :replacement_cost, :reported_by
                  )";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'return_item_id' => $data['return_item_id'],
            'damage_type' => $data['damage_type'],
            'damage_description' => $data['damage_description'],
            'repair_cost' => $data['repair_cost'] ?? null,
            'replacement_cost' => $data['replacement_cost'] ?? null,
            'reported_by' => $data['reported_by']
        ]);
        
        $reportId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Damage report created successfully',
            'data' => ['id' => $reportId]
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create damage report: ' . $e->getMessage()]);
    }
}

function updateDamageReport($data) {
    global $pdo;
    
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Report ID is required']);
        return;
    }
    
    // Check if report exists
    $stmt = $pdo->prepare("SELECT id FROM Damage_Report WHERE id = :id");
    $stmt->execute(['id' => $data['id']]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Damage report not found']);
        return;
    }
    
    // Validate employee exists if provided
    if (!empty($data['reported_by'])) {
        $stmt = $pdo->prepare("SELECT id FROM Employee WHERE id = :id");
        $stmt->execute(['id' => $data['reported_by']]);
        if (!$stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid employee ID']);
            return;
        }
    }
    
    try {
        $query = "UPDATE Damage_Report SET 
                    damage_type = :damage_type,
                    damage_description = :damage_description,
                    repair_cost = :repair_cost,
                    replacement_cost = :replacement_cost,
                    reported_by = :reported_by
                  WHERE id = :id";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'id' => $data['id'],
            'damage_type' => $data['damage_type'],
            'damage_description' => $data['damage_description'],
            'repair_cost' => $data['repair_cost'] ?? null,
            'replacement_cost' => $data['replacement_cost'] ?? null,
            'reported_by' => $data['reported_by']
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Damage report updated successfully'
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update damage report: ' . $e->getMessage()]);
    }
}

function deleteDamageReport($data) {
    global $pdo;
    
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Report ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM Damage_Report WHERE id = :id");
        $stmt->execute(['id' => $data['id']]);
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Damage report not found']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Damage report deleted successfully'
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete damage report: ' . $e->getMessage()]);
    }
}

function bulkDeleteDamageReports($data) {
    global $pdo;
    
    if (empty($data['ids']) || !is_array($data['ids'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Report IDs array is required']);
        return;
    }
    
    try {
        $placeholders = str_repeat('?,', count($data['ids']) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM Damage_Report WHERE id IN ($placeholders)");
        $stmt->execute($data['ids']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Damage reports deleted successfully',
            'data' => ['deleted_count' => $stmt->rowCount()]
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete damage reports: ' . $e->getMessage()]);
    }
}

function exportDamageReports() {
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
        $whereClause .= " AND (dr.damage_type LIKE :search OR dr.damage_description LIKE :search OR bi.item_description LIKE :search)";
        $params['search'] = "%" . $_GET['search'] . "%";
    }
    
    if (!empty($_GET['damage_type'])) {
        $whereClause .= " AND dr.damage_type = :damage_type";
        $params['damage_type'] = $_GET['damage_type'];
    }
    
    if (!empty($_GET['condition_status'])) {
        $whereClause .= " AND ri.condition_status = :condition_status";
        $params['condition_status'] = $_GET['condition_status'];
    }
    
    $query = "SELECT dr.id, dr.damage_type, dr.damage_description, dr.repair_cost, dr.replacement_cost, 
                     dr.report_date,
                     bi.item_description as return_item_description, ri.quantity_returned, ri.condition_status,
                     bt.transaction_type, bt.transaction_date,
                     br.id as request_id, br.purpose as request_purpose,
                     c.name as customer_name, e.name as reported_by_name, e.employee_id as reported_by_employee_id
              FROM Damage_Report dr 
              INNER JOIN Return_Items ri ON dr.return_item_id = ri.id 
              INNER JOIN Borrowing_Transaction bt ON ri.borrowing_transaction_id = bt.id 
              INNER JOIN Borrowing_Request br ON bt.borrowing_request_id = br.id 
              INNER JOIN Customer c ON br.customer_id = c.id 
              INNER JOIN Employee e ON dr.reported_by = e.id 
              INNER JOIN Borrowing_Items bi ON ri.borrowing_item_id = bi.id 
              $whereClause 
              ORDER BY dr.report_date DESC, dr.id DESC";
    
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->execute();
    
    $reports = $stmt->fetchAll();
    
    // Set CSV headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="damage_reports_' . date('Y-m-d_H-i-s') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, [
        'ID', 'Damage Type', 'Damage Description', 'Repair Cost', 'Replacement Cost', 'Report Date',
        'Return Item', 'Quantity Returned', 'Condition Status', 'Transaction Type', 'Transaction Date',
        'Request ID', 'Request Purpose', 'Customer', 'Reported By', 'Employee ID'
    ]);
    
    // CSV data
    foreach ($reports as $report) {
        fputcsv($output, [
            $report['id'],
            $report['damage_type'],
            $report['damage_description'],
            $report['repair_cost'],
            $report['replacement_cost'],
            $report['report_date'],
            $report['return_item_description'],
            $report['quantity_returned'],
            $report['condition_status'],
            $report['transaction_type'],
            $report['transaction_date'],
            $report['request_id'],
            $report['request_purpose'],
            $report['customer_name'],
            $report['reported_by_name'],
            $report['reported_by_employee_id']
        ]);
    }
    
    fclose($output);
    exit;
}
?>
