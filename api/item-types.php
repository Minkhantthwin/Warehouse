<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Check authentication
checkRememberMe();
requireLogin();

// Check permission
if (!hasPermission('borrowing_management')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

$currentAdmin = getLoggedInAdmin();

// Get action from GET or POST
$action = $_GET['action'] ?? '';
if (empty($action)) {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
}

try {
    switch ($action) {
        case 'get':
            getItemType($pdo);
            break;
        case 'create':
            createItemType($pdo, $currentAdmin);
            break;
        case 'update':
            updateItemType($pdo, $currentAdmin);
            break;
        case 'delete':
            deleteItemType($pdo, $currentAdmin);
            break;
        case 'bulk_delete':
            bulkDeleteItemTypes($pdo, $currentAdmin);
            break;
        case 'export':
            exportItemTypes($pdo);
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function getItemType($pdo) {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        throw new Exception('Item type ID is required');
    }
    
    $stmt = $pdo->prepare("
        SELECT bt.*, 
               COUNT(DISTINCT bi.id) as borrow_count,
               COUNT(DISTINCT bi.borrowing_request_id) as request_count
        FROM Borrowing_Item_Types bt 
        LEFT JOIN Borrowing_Items bi ON bt.id = bi.item_type_id 
        WHERE bt.id = :id
        GROUP BY bt.id
    ");
    $stmt->execute(['id' => $id]);
    $itemType = $stmt->fetch();
    
    if (!$itemType) {
        throw new Exception('Item type not found');
    }
    
    echo json_encode(['success' => true, 'data' => $itemType]);
}

function createItemType($pdo, $currentAdmin) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        throw new Exception('Invalid JSON data received');
    }
    
    // Validate required fields
    if (empty($data['name'])) {
        throw new Exception('Name is required');
    }
    
    // Insert item type
    $stmt = $pdo->prepare("
        INSERT INTO Borrowing_Item_Types (name, description, unit, estimated_value, created_at) 
        VALUES (:name, :description, :unit, :estimated_value, NOW())
    ");
    
    $estimatedValue = !empty($data['estimated_value']) ? floatval($data['estimated_value']) : 0;
    
    $stmt->execute([
        'name' => trim($data['name']),
        'description' => !empty($data['description']) ? trim($data['description']) : null,
        'unit' => !empty($data['unit']) ? trim($data['unit']) : null,
        'estimated_value' => $estimatedValue
    ]);
    
    $itemTypeId = $pdo->lastInsertId();
    
    // Log activity
    logActivity($pdo, $currentAdmin['id'], 'CREATE', "Created item type: {$data['name']} (ID: $itemTypeId)");
    
    echo json_encode([
        'success' => true, 
        'message' => 'Item type created successfully',
        'id' => $itemTypeId
    ]);
}

function updateItemType($pdo, $currentAdmin) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        throw new Exception('Invalid JSON data received');
    }
    
    // Validate required fields
    if (empty($data['id'])) {
        throw new Exception('Item type ID is required');
    }
    
    if (empty($data['name'])) {
        throw new Exception('Name is required');
    }
    
    // Check if item type exists
    $stmt = $pdo->prepare("SELECT id, name FROM Borrowing_Item_Types WHERE id = :id");
    $stmt->execute(['id' => $data['id']]);
    $existingType = $stmt->fetch();
    
    if (!$existingType) {
        throw new Exception('Item type not found');
    }
    
    // Update item type
    $stmt = $pdo->prepare("
        UPDATE Borrowing_Item_Types 
        SET name = :name,
            description = :description,
            unit = :unit,
            estimated_value = :estimated_value
        WHERE id = :id
    ");
    
    $estimatedValue = !empty($data['estimated_value']) ? floatval($data['estimated_value']) : 0;
    
    $stmt->execute([
        'id' => $data['id'],
        'name' => trim($data['name']),
        'description' => !empty($data['description']) ? trim($data['description']) : null,
        'unit' => !empty($data['unit']) ? trim($data['unit']) : null,
        'estimated_value' => $estimatedValue
    ]);
    
    // Log activity
    logActivity($pdo, $currentAdmin['id'], 'UPDATE', "Updated item type: {$data['name']} (ID: {$data['id']})");
    
    echo json_encode(['success' => true, 'message' => 'Item type updated successfully']);
}

function deleteItemType($pdo, $currentAdmin) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        throw new Exception('Item type ID is required');
    }
    
    // Check if item type is being used
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM Borrowing_Items WHERE item_type_id = :id");
    $stmt->execute(['id' => $data['id']]);
    $usage = $stmt->fetch();
    
    if ($usage['count'] > 0) {
        throw new Exception('Cannot delete item type that is being used in borrowing items');
    }
    
    // Get item type name for logging
    $stmt = $pdo->prepare("SELECT name FROM Borrowing_Item_Types WHERE id = :id");
    $stmt->execute(['id' => $data['id']]);
    $itemType = $stmt->fetch();
    
    if (!$itemType) {
        throw new Exception('Item type not found');
    }
    
    // Delete item type
    $stmt = $pdo->prepare("DELETE FROM Borrowing_Item_Types WHERE id = :id");
    $stmt->execute(['id' => $data['id']]);
    
    // Log activity
    logActivity($pdo, $currentAdmin['id'], 'DELETE', "Deleted item type: {$itemType['name']} (ID: {$data['id']})");
    
    echo json_encode(['success' => true, 'message' => 'Item type deleted successfully']);
}

function bulkDeleteItemTypes($pdo, $currentAdmin) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['ids']) || !is_array($data['ids'])) {
        throw new Exception('Item type IDs are required');
    }
    
    $placeholders = str_repeat('?,', count($data['ids']) - 1) . '?';
    
    // Check if any item types are being used
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM Borrowing_Items 
        WHERE item_type_id IN ($placeholders)
    ");
    $stmt->execute($data['ids']);
    $usage = $stmt->fetch();
    
    if ($usage['count'] > 0) {
        throw new Exception('Cannot delete item types that are being used in borrowing items');
    }
    
    // Delete item types
    $stmt = $pdo->prepare("DELETE FROM Borrowing_Item_Types WHERE id IN ($placeholders)");
    $stmt->execute($data['ids']);
    
    $deletedCount = $stmt->rowCount();
    
    // Log activity
    logActivity($pdo, $currentAdmin['id'], 'BULK_UPDATE', "Bulk deleted $deletedCount item types");
    
    echo json_encode([
        'success' => true, 
        'message' => "$deletedCount item type(s) deleted successfully"
    ]);
}

function exportItemTypes($pdo) {
    $typeIds = isset($_GET['type_ids']) ? explode(',', $_GET['type_ids']) : null;
    
    $query = "
        SELECT bt.id, bt.name, bt.description, bt.unit, bt.estimated_value,
               COUNT(DISTINCT bi.id) as total_borrowings,
               COUNT(DISTINCT bi.borrowing_request_id) as total_requests,
               bt.created_at
        FROM Borrowing_Item_Types bt
        LEFT JOIN Borrowing_Items bi ON bt.id = bi.item_type_id
    ";
    
    if ($typeIds) {
        $placeholders = str_repeat('?,', count($typeIds) - 1) . '?';
        $query .= " WHERE bt.id IN ($placeholders)";
    }
    
    $query .= " GROUP BY bt.id ORDER BY bt.name";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($typeIds ?: []);
    $itemTypes = $stmt->fetchAll();
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="item-types-' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add CSV headers
    fputcsv($output, ['ID', 'Name', 'Description', 'Unit', 'Estimated Value', 'Total Borrowings', 'Total Requests', 'Created At']);
    
    // Add data rows
    foreach ($itemTypes as $type) {
        fputcsv($output, [
            $type['id'],
            $type['name'],
            $type['description'],
            $type['unit'],
            $type['estimated_value'],
            $type['total_borrowings'],
            $type['total_requests'],
            $type['created_at']
        ]);
    }
    
    fclose($output);
    exit();
}

function logActivity($pdo, $adminId, $action, $description) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO Activity_Log (admin_id, action, description, timestamp) 
            VALUES (:admin_id, :action, :description, NOW())
        ");
        
        $stmt->execute([
            'admin_id' => $adminId,
            'action' => $action,
            'description' => $description
        ]);
    } catch (Exception $e) {
        // Log error but don't fail the main operation
        error_log("Failed to log activity: " . $e->getMessage());
    }
}
