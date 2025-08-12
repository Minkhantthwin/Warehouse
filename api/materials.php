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

// Check permission for inventory management
if (!hasPermission('inventory_management')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied. Insufficient permissions.']);
    exit();
}

$currentAdmin = getLoggedInAdmin();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            handleListMaterials();
            break;
        case 'get':
            handleGetMaterial();
            break;
        case 'create':
            handleCreateMaterial();
            break;
        case 'update':
            handleUpdateMaterial();
            break;
        case 'delete':
            handleDeleteMaterial();
            break;
        case 'categories':
            handleGetCategories();
            break;
        case 'stats':
            handleGetStats();
            break;
        case 'bulk_delete':
            handleBulkDelete();
            break;
        case 'export':
            handleExport();
            break;
        case 'import':
            handleImport();
            break;
        case 'check_stock':
            handleCheckStock();
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action specified']);
            break;
    }
} catch (Exception $e) {
    error_log("Materials API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error occurred']);
}

function handleListMaterials() {
    global $pdo;
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = min((int)($_GET['limit'] ?? 10), 100); // Max 100 items per page
    $offset = ($page - 1) * $limit;
    
    $search = $_GET['search'] ?? '';
    $category = $_GET['category'] ?? '';
    $price_range = $_GET['price_range'] ?? '';
    $status = $_GET['status'] ?? '';
    
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $whereClause .= " AND (m.name LIKE :search OR m.description LIKE :search OR m.unit LIKE :search)";
        $params['search'] = "%" . $search . "%";
    }
    
    if (!empty($category)) {
        $whereClause .= " AND m.category_id = :category";
        $params['category'] = $category;
    }
    
    if (!empty($status)) {
        $whereClause .= " AND m.status = :status";
        $params['status'] = $status;
    }
    
    if (!empty($price_range)) {
        switch ($price_range) {
            case 'low':
                $whereClause .= " AND m.price_per_unit < 100";
                break;
            case 'medium':
                $whereClause .= " AND m.price_per_unit BETWEEN 100 AND 500";
                break;
            case 'high':
                $whereClause .= " AND m.price_per_unit > 500";
                break;
        }
    }
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total 
                   FROM Material m 
                   LEFT JOIN Material_Categories mc ON m.category_id = mc.id 
                   $whereClause";
    
    $countStmt = $pdo->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue(":$key", $value);
    }
    $countStmt->execute();
    $totalItems = $countStmt->fetch()['total'];
    
    // Get materials with stock information
    $query = "SELECT m.*, mc.name as category_name,
                     COALESCE(SUM(i.quantity), 0) as stock_quantity,
                     CASE 
                         WHEN COALESCE(SUM(i.quantity), 0) = 0 THEN 'out_of_stock'
                         WHEN COALESCE(SUM(i.quantity), 0) <= 20 THEN 'low_stock'
                         ELSE 'in_stock'
                     END as stock_status
              FROM Material m 
              LEFT JOIN Material_Categories mc ON m.category_id = mc.id 
              LEFT JOIN Inventory i ON m.id = i.material_id
              $whereClause 
              GROUP BY m.id, m.name, m.description, m.category_id, m.unit, m.price_per_unit, m.status, m.created_at, m.updated_at, mc.name
              ORDER BY m.created_at DESC 
              LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate pagination info
    $totalPages = ceil($totalItems / $limit);
    $hasNext = $page < $totalPages;
    $hasPrev = $page > 1;
    
    echo json_encode([
        'success' => true,
        'data' => $materials,
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

function handleGetMaterial() {
    global $pdo;
    
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Material ID is required']);
        return;
    }
    
    $query = "SELECT m.*, mc.name as category_name,
                     COALESCE(SUM(i.quantity), 0) as stock_quantity,
                     CASE 
                         WHEN COALESCE(SUM(i.quantity), 0) = 0 THEN 'out_of_stock'
                         WHEN COALESCE(SUM(i.quantity), 0) <= 20 THEN 'low_stock'
                         ELSE 'in_stock'
                     END as stock_status
              FROM Material m 
              LEFT JOIN Material_Categories mc ON m.category_id = mc.id 
              LEFT JOIN Inventory i ON m.id = i.material_id
              WHERE m.id = :id
              GROUP BY m.id";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $material = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$material) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Material not found']);
        return;
    }
    
    echo json_encode(['success' => true, 'data' => $material]);
}

function handleCreateMaterial() {
    global $pdo, $currentAdmin;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $requiredFields = ['name', 'unit', 'price_per_unit'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => ucfirst($field) . ' is required']);
            return;
        }
    }
    
    // Validate price
    if (!is_numeric($input['price_per_unit']) || $input['price_per_unit'] < 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Price must be a positive number']);
        return;
    }
    
    // Check if material name already exists
    $checkStmt = $pdo->prepare("SELECT id FROM Material WHERE name = :name");
    $checkStmt->bindValue(':name', $input['name']);
    $checkStmt->execute();
    
    if ($checkStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Material with this name already exists']);
        return;
    }
    
    // Validate category if provided
    if (!empty($input['category_id'])) {
        $categoryStmt = $pdo->prepare("SELECT id FROM Material_Categories WHERE id = :id");
        $categoryStmt->bindValue(':id', $input['category_id'], PDO::PARAM_INT);
        $categoryStmt->execute();
        
        if (!$categoryStmt->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid category selected']);
            return;
        }
    }
    
    try {
        $pdo->beginTransaction();
        
        $query = "INSERT INTO Material (name, description, category_id, unit, price_per_unit, status) 
                  VALUES (:name, :description, :category_id, :unit, :price_per_unit, :status)";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':name', $input['name']);
        $stmt->bindValue(':description', $input['description'] ?? null);
        $stmt->bindValue(':category_id', !empty($input['category_id']) ? $input['category_id'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':unit', $input['unit']);
        $stmt->bindValue(':price_per_unit', $input['price_per_unit']);
        $stmt->bindValue(':status', $input['status'] ?? 'active');
        
        $stmt->execute();
        $materialId = $pdo->lastInsertId();
        
        // Initialize inventory with 0 quantity at default location
        $inventoryStmt = $pdo->prepare("INSERT INTO Inventory (material_id, quantity, location_id) VALUES (:material_id, 0, 1)");
        $inventoryStmt->bindValue(':material_id', $materialId, PDO::PARAM_INT);
        $inventoryStmt->execute();
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Material created successfully',
            'data' => ['id' => $materialId]
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Create material error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to create material']);
    }
}

function handleUpdateMaterial() {
    global $pdo, $currentAdmin;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Material ID is required']);
        return;
    }
    
    // Check if material exists
    $checkStmt = $pdo->prepare("SELECT id FROM Material WHERE id = :id");
    $checkStmt->bindValue(':id', $input['id'], PDO::PARAM_INT);
    $checkStmt->execute();
    
    if (!$checkStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Material not found']);
        return;
    }
    
    // Validate required fields
    $requiredFields = ['name', 'unit', 'price_per_unit'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => ucfirst($field) . ' is required']);
            return;
        }
    }
    
    // Validate price
    if (!is_numeric($input['price_per_unit']) || $input['price_per_unit'] < 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Price must be a positive number']);
        return;
    }
    
    // Check if name is unique (excluding current material)
    $nameCheckStmt = $pdo->prepare("SELECT id FROM Material WHERE name = :name AND id != :id");
    $nameCheckStmt->bindValue(':name', $input['name']);
    $nameCheckStmt->bindValue(':id', $input['id'], PDO::PARAM_INT);
    $nameCheckStmt->execute();
    
    if ($nameCheckStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Material with this name already exists']);
        return;
    }
    
    // Validate category if provided
    if (!empty($input['category_id'])) {
        $categoryStmt = $pdo->prepare("SELECT id FROM Material_Categories WHERE id = :id");
        $categoryStmt->bindValue(':id', $input['category_id'], PDO::PARAM_INT);
        $categoryStmt->execute();
        
        if (!$categoryStmt->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid category selected']);
            return;
        }
    }
    
    try {
        $query = "UPDATE Material 
                  SET name = :name, description = :description, category_id = :category_id, 
                      unit = :unit, price_per_unit = :price_per_unit, status = :status 
                  WHERE id = :id";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':id', $input['id'], PDO::PARAM_INT);
        $stmt->bindValue(':name', $input['name']);
        $stmt->bindValue(':description', $input['description'] ?? null);
        $stmt->bindValue(':category_id', !empty($input['category_id']) ? $input['category_id'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':unit', $input['unit']);
        $stmt->bindValue(':price_per_unit', $input['price_per_unit']);
        $stmt->bindValue(':status', $input['status'] ?? 'active');
        
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Material updated successfully'
        ]);
        
    } catch (Exception $e) {
        error_log("Update material error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update material']);
    }
}

function handleDeleteMaterial() {
    global $pdo, $currentAdmin;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Material ID is required']);
        return;
    }
    
    // Check if material exists
    $checkStmt = $pdo->prepare("SELECT id FROM Material WHERE id = :id");
    $checkStmt->bindValue(':id', $input['id'], PDO::PARAM_INT);
    $checkStmt->execute();
    
    if (!$checkStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Material not found']);
        return;
    }
    
    // Check if material is referenced in other tables
    $referencesStmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM Borrowing_Items WHERE material_id = :id) as borrowing_count,
            (SELECT COUNT(*) FROM Return_Items WHERE material_id = :id) as return_count
    ");
    $referencesStmt->bindValue(':id', $input['id'], PDO::PARAM_INT);
    $referencesStmt->execute();
    $references = $referencesStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($references['borrowing_count'] > 0 || $references['return_count'] > 0) {
        // Instead of hard delete, soft delete by setting status to inactive
        try {
            $softDeleteStmt = $pdo->prepare("UPDATE Material SET status = 'discontinued' WHERE id = :id");
            $softDeleteStmt->bindValue(':id', $input['id'], PDO::PARAM_INT);
            $softDeleteStmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Material has been discontinued due to existing references'
            ]);
        } catch (Exception $e) {
            error_log("Soft delete material error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to discontinue material']);
        }
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Delete inventory records first
        $deleteInventoryStmt = $pdo->prepare("DELETE FROM Inventory WHERE material_id = :id");
        $deleteInventoryStmt->bindValue(':id', $input['id'], PDO::PARAM_INT);
        $deleteInventoryStmt->execute();
        
        // Delete the material
        $deleteMaterialStmt = $pdo->prepare("DELETE FROM Material WHERE id = :id");
        $deleteMaterialStmt->bindValue(':id', $input['id'], PDO::PARAM_INT);
        $deleteMaterialStmt->execute();
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Material deleted successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Delete material error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to delete material']);
    }
}

function handleGetCategories() {
    global $pdo;
    
    try {
        $query = "SELECT * FROM Material_Categories ORDER BY name";
        $stmt = $pdo->query($query);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $categories]);
        
    } catch (Exception $e) {
        error_log("Get categories error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to retrieve categories']);
    }
}

function handleGetStats() {
    global $pdo;
    
    try {
        $stats = [];
        
        // Total materials
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM Material WHERE status != 'discontinued'");
        $stats['total_materials'] = $stmt->fetch()['total'] ?? 0;
        
        // Active materials
        $stmt = $pdo->query("SELECT COUNT(*) as active FROM Material WHERE status = 'active'");
        $stats['active_materials'] = $stmt->fetch()['active'] ?? 0;
        
        // Categories count
        $stmt = $pdo->query("SELECT COUNT(*) as categories FROM Material_Categories");
        $stats['categories_count'] = $stmt->fetch()['categories'] ?? 0;
        
        // Low stock materials
        $stmt = $pdo->query("
            SELECT COUNT(*) as low_stock 
            FROM Material m 
            LEFT JOIN Inventory i ON m.id = i.material_id 
            WHERE m.status = 'active' 
            AND COALESCE(SUM(i.quantity), 0) <= 20
            GROUP BY m.id
        ");
        $stats['low_stock_count'] = $stmt->rowCount();
        
        // Materials added this month
        $stmt = $pdo->query("
            SELECT COUNT(*) as new_materials 
            FROM Material 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND status != 'discontinued'
        ");
        $stats['new_this_month'] = $stmt->fetch()['new_materials'] ?? 0;
        
        // Total inventory value
        $stmt = $pdo->query("
            SELECT SUM(m.price_per_unit * COALESCE(i.quantity, 0)) as total_value
            FROM Material m 
            LEFT JOIN Inventory i ON m.id = i.material_id 
            WHERE m.status = 'active'
        ");
        $stats['total_inventory_value'] = $stmt->fetch()['total_value'] ?? 0;
        
        echo json_encode(['success' => true, 'data' => $stats]);
        
    } catch (Exception $e) {
        error_log("Get stats error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to retrieve statistics']);
    }
}

function handleBulkDelete() {
    global $pdo, $currentAdmin;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['ids']) || !is_array($input['ids'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Material IDs array is required']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        $deletedCount = 0;
        $discontinuedCount = 0;
        
        foreach ($input['ids'] as $id) {
            // Check if material is referenced in other tables
            $referencesStmt = $pdo->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM Borrowing_Items WHERE material_id = :id) as borrowing_count,
                    (SELECT COUNT(*) FROM Return_Items WHERE material_id = :id) as return_count
            ");
            $referencesStmt->bindValue(':id', $id, PDO::PARAM_INT);
            $referencesStmt->execute();
            $references = $referencesStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($references['borrowing_count'] > 0 || $references['return_count'] > 0) {
                // Soft delete by setting status to discontinued
                $softDeleteStmt = $pdo->prepare("UPDATE Material SET status = 'discontinued' WHERE id = :id");
                $softDeleteStmt->bindValue(':id', $id, PDO::PARAM_INT);
                $softDeleteStmt->execute();
                $discontinuedCount++;
            } else {
                // Hard delete
                $deleteInventoryStmt = $pdo->prepare("DELETE FROM Inventory WHERE material_id = :id");
                $deleteInventoryStmt->bindValue(':id', $id, PDO::PARAM_INT);
                $deleteInventoryStmt->execute();
                
                $deleteMaterialStmt = $pdo->prepare("DELETE FROM Material WHERE id = :id");
                $deleteMaterialStmt->bindValue(':id', $id, PDO::PARAM_INT);
                $deleteMaterialStmt->execute();
                $deletedCount++;
            }
        }
        
        $pdo->commit();
        
        $message = "";
        if ($deletedCount > 0) {
            $message .= "$deletedCount materials deleted";
        }
        if ($discontinuedCount > 0) {
            if ($deletedCount > 0) $message .= ", ";
            $message .= "$discontinuedCount materials discontinued";
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'deleted' => $deletedCount,
            'discontinued' => $discontinuedCount
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Bulk delete error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to delete materials']);
    }
}

function handleExport() {
    global $pdo;
    
    $format = $_GET['format'] ?? 'csv';
    $ids = $_GET['ids'] ?? null;
    
    $whereClause = "WHERE m.status != 'discontinued'";
    $params = [];
    
    if ($ids) {
        $idArray = explode(',', $ids);
        $placeholders = str_repeat('?,', count($idArray) - 1) . '?';
        $whereClause .= " AND m.id IN ($placeholders)";
        $params = $idArray;
    }
    
    try {
        $query = "SELECT m.id, m.name, m.description, mc.name as category, m.unit, 
                         m.price_per_unit, m.status, COALESCE(SUM(i.quantity), 0) as stock_quantity,
                         m.created_at
                  FROM Material m 
                  LEFT JOIN Material_Categories mc ON m.category_id = mc.id 
                  LEFT JOIN Inventory i ON m.id = i.material_id
                  $whereClause
                  GROUP BY m.id
                  ORDER BY m.name";
        
        $stmt = $pdo->prepare($query);
        if (!empty($params)) {
            foreach ($params as $index => $value) {
                $stmt->bindValue($index + 1, $value, PDO::PARAM_INT);
            }
        }
        $stmt->execute();
        
        $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="materials_export_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            
            // Add CSV headers
            fputcsv($output, [
                'ID', 'Name', 'Description', 'Category', 'Unit', 
                'Price per Unit', 'Status', 'Stock Quantity', 'Created At'
            ]);
            
            // Add data rows
            foreach ($materials as $material) {
                fputcsv($output, [
                    $material['id'],
                    $material['name'],
                    $material['description'],
                    $material['category'] ?? 'Uncategorized',
                    $material['unit'],
                    $material['price_per_unit'],
                    $material['status'],
                    $material['stock_quantity'],
                    $material['created_at']
                ]);
            }
            
            fclose($output);
        } else {
            echo json_encode(['success' => true, 'data' => $materials]);
        }
        
    } catch (Exception $e) {
        error_log("Export error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to export materials']);
    }
}

function handleImport() {
    global $pdo, $currentAdmin;
    
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
        return;
    }
    
    $file = $_FILES['file'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($fileExtension !== 'csv') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Only CSV files are allowed']);
        return;
    }
    
    try {
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            throw new Exception('Could not open uploaded file');
        }
        
        $pdo->beginTransaction();
        
        // Skip header row
        $header = fgetcsv($handle);
        
        $importedCount = 0;
        $errors = [];
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            if (count($data) < 5) continue; // Skip incomplete rows
            
            $name = trim($data[0]);
            $description = trim($data[1]) ?: null;
            $unit = trim($data[2]);
            $price = floatval($data[3]);
            $categoryName = trim($data[4]) ?: null;
            
            if (empty($name) || empty($unit) || $price <= 0) {
                $errors[] = "Row skipped: Invalid data for material '$name'";
                continue;
            }
            
            // Check if material already exists
            $checkStmt = $pdo->prepare("SELECT id FROM Material WHERE name = :name");
            $checkStmt->bindValue(':name', $name);
            $checkStmt->execute();
            
            if ($checkStmt->fetch()) {
                $errors[] = "Material '$name' already exists";
                continue;
            }
            
            // Get category ID if category name is provided
            $categoryId = null;
            if ($categoryName) {
                $categoryStmt = $pdo->prepare("SELECT id FROM Material_Categories WHERE name = :name");
                $categoryStmt->bindValue(':name', $categoryName);
                $categoryStmt->execute();
                $category = $categoryStmt->fetch();
                
                if ($category) {
                    $categoryId = $category['id'];
                } else {
                    // Create new category
                    $createCategoryStmt = $pdo->prepare("INSERT INTO Material_Categories (name) VALUES (:name)");
                    $createCategoryStmt->bindValue(':name', $categoryName);
                    $createCategoryStmt->execute();
                    $categoryId = $pdo->lastInsertId();
                }
            }
            
            // Insert material
            $insertStmt = $pdo->prepare("
                INSERT INTO Material (name, description, category_id, unit, price_per_unit, status) 
                VALUES (:name, :description, :category_id, :unit, :price_per_unit, 'active')
            ");
            $insertStmt->bindValue(':name', $name);
            $insertStmt->bindValue(':description', $description);
            $insertStmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
            $insertStmt->bindValue(':unit', $unit);
            $insertStmt->bindValue(':price_per_unit', $price);
            $insertStmt->execute();
            
            $materialId = $pdo->lastInsertId();
            
            // Initialize inventory
            $inventoryStmt = $pdo->prepare("INSERT INTO Inventory (material_id, quantity, location_id) VALUES (:material_id, 0, 1)");
            $inventoryStmt->bindValue(':material_id', $materialId, PDO::PARAM_INT);
            $inventoryStmt->execute();
            
            $importedCount++;
        }
        
        fclose($handle);
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Import completed successfully",
            'imported' => $importedCount,
            'errors' => $errors
        ]);
        
    } catch (Exception $e) {
        if (isset($handle)) fclose($handle);
        $pdo->rollBack();
        error_log("Import error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to import materials: ' . $e->getMessage()]);
    }
}

function handleCheckStock() {
    global $pdo;
    
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Material ID is required']);
        return;
    }
    
    try {
        $query = "SELECT l.name as location_name, i.quantity, i.last_updated
                  FROM Inventory i 
                  LEFT JOIN Location l ON i.location_id = l.id 
                  WHERE i.material_id = :id";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $stockLevels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $stockLevels]);
        
    } catch (Exception $e) {
        error_log("Check stock error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to check stock levels']);
    }
}
?>
