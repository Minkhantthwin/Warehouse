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
            handleListCategories();
            break;
        case 'get':
            handleGetCategory();
            break;
        case 'create':
            handleCreateCategory();
            break;
        case 'update':
            handleUpdateCategory();
            break;
        case 'delete':
            handleDeleteCategory();
            break;
        case 'stats':
            handleGetStats();
            break;
        case 'bulk_delete':
            handleBulkDelete();
            break;
        case 'merge':
            handleMergeCategories();
            break;
        case 'export':
            handleExport();
            break;
        case 'import':
            handleImport();
            break;
        case 'check_usage':
            handleCheckUsage();
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action specified']);
            break;
    }
} catch (Exception $e) {
    error_log("Categories API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error occurred']);
}

function handleListCategories() {
    global $pdo;
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = min((int)($_GET['limit'] ?? 10), 100); // Max 100 items per page
    $offset = ($page - 1) * $limit;
    
    $search = $_GET['search'] ?? '';
    $has_materials = $_GET['has_materials'] ?? '';
    $sort_by = $_GET['sort_by'] ?? 'name';
    $sort_order = $_GET['sort_order'] ?? 'ASC';
    
    // Validate sort parameters
    $validSortColumns = ['name', 'description', 'material_count', 'created_at'];
    $validSortOrders = ['ASC', 'DESC'];
    
    if (!in_array($sort_by, $validSortColumns)) {
        $sort_by = 'name';
    }
    if (!in_array(strtoupper($sort_order), $validSortOrders)) {
        $sort_order = 'ASC';
    }
    
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $whereClause .= " AND (mc.name LIKE :search OR mc.description LIKE :search)";
        $params['search'] = "%" . $search . "%";
    }
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM Material_Categories mc $whereClause";
    $countStmt = $pdo->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue(":$key", $value);
    }
    $countStmt->execute();
    $totalItems = $countStmt->fetch()['total'];
    
    // Get categories with material count and usage info
    $query = "SELECT mc.*, 
                     COUNT(m.id) as material_count,
                     COALESCE(SUM(CASE WHEN m.status = 'active' THEN 1 ELSE 0 END), 0) as active_materials,
                     COALESCE(SUM(CASE WHEN m.status = 'inactive' THEN 1 ELSE 0 END), 0) as inactive_materials,
                     COALESCE(AVG(m.price_per_unit), 0) as avg_price,
                     COALESCE(SUM(i.quantity), 0) as total_stock
              FROM Material_Categories mc 
              LEFT JOIN Material m ON mc.id = m.category_id 
              LEFT JOIN Inventory i ON m.id = i.material_id
              $whereClause";
    
    // Apply has_materials filter
    if ($has_materials === 'true') {
        $query .= " HAVING material_count > 0";
    } elseif ($has_materials === 'false') {
        $query .= " HAVING material_count = 0";
    }
    
    $query .= " GROUP BY mc.id, mc.name, mc.description";
    
    // Apply sorting
    if ($sort_by === 'material_count') {
        $query .= " ORDER BY material_count $sort_order";
    } else {
        $query .= " ORDER BY mc.$sort_by $sort_order";
    }
    
    $query .= " LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate pagination info
    $totalPages = ceil($totalItems / $limit);
    $hasNext = $page < $totalPages;
    $hasPrev = $page > 1;
    
    echo json_encode([
        'success' => true,
        'data' => $categories,
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

function handleGetCategory() {
    global $pdo;
    
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Category ID is required']);
        return;
    }
    
    $query = "SELECT mc.*, 
                     COUNT(m.id) as material_count,
                     COALESCE(SUM(CASE WHEN m.status = 'active' THEN 1 ELSE 0 END), 0) as active_materials,
                     COALESCE(SUM(CASE WHEN m.status = 'inactive' THEN 1 ELSE 0 END), 0) as inactive_materials,
                     COALESCE(AVG(m.price_per_unit), 0) as avg_price,
                     COALESCE(MIN(m.price_per_unit), 0) as min_price,
                     COALESCE(MAX(m.price_per_unit), 0) as max_price,
                     COALESCE(SUM(i.quantity), 0) as total_stock
              FROM Material_Categories mc 
              LEFT JOIN Material m ON mc.id = m.category_id 
              LEFT JOIN Inventory i ON m.id = i.material_id
              WHERE mc.id = :id
              GROUP BY mc.id";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Category not found']);
        return;
    }
    
    // Get recent materials in this category
    $materialsQuery = "SELECT m.id, m.name, m.price_per_unit, m.status, m.created_at,
                              COALESCE(SUM(i.quantity), 0) as stock_quantity
                       FROM Material m 
                       LEFT JOIN Inventory i ON m.id = i.material_id
                       WHERE m.category_id = :id 
                       GROUP BY m.id
                       ORDER BY m.created_at DESC 
                       LIMIT 5";
    
    $materialsStmt = $pdo->prepare($materialsQuery);
    $materialsStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $materialsStmt->execute();
    
    $category['recent_materials'] = $materialsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $category]);
}

function handleCreateCategory() {
    global $pdo, $currentAdmin;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $name = trim($input['name'] ?? '');
    $description = trim($input['description'] ?? '');
    
    // Validation
    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Category name is required']);
        return;
    }
    
    if (strlen($name) > 100) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Category name must not exceed 100 characters']);
        return;
    }
    
    if (strlen($description) > 500) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Description must not exceed 500 characters']);
        return;
    }
    
    // Check for duplicate name
    $checkQuery = "SELECT id FROM Material_Categories WHERE name = :name";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->bindValue(':name', $name);
    $checkStmt->execute();
    
    if ($checkStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'A category with this name already exists']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        $query = "INSERT INTO Material_Categories (name, description) VALUES (:name, :description)";
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':description', $description);
        $stmt->execute();
        
        $categoryId = $pdo->lastInsertId();
        
        // Log the activity
        $logQuery = "INSERT INTO Activity_Log (admin_id, action, description, timestamp) 
                     VALUES (:admin_id, 'CREATE', :description, NOW())";
        $logStmt = $pdo->prepare($logQuery);
        $logStmt->bindValue(':admin_id', $currentAdmin['id']);
        $logStmt->bindValue(':description', "Created category: $name");
        $logStmt->execute();
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Category created successfully',
            'data' => ['id' => $categoryId, 'name' => $name, 'description' => $description]
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error creating category: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to create category']);
    }
}

function handleUpdateCategory() {
    global $pdo, $currentAdmin;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = $input['id'] ?? null;
    $name = trim($input['name'] ?? '');
    $description = trim($input['description'] ?? '');
    
    // Validation
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Category ID is required']);
        return;
    }
    
    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Category name is required']);
        return;
    }
    
    if (strlen($name) > 100) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Category name must not exceed 100 characters']);
        return;
    }
    
    if (strlen($description) > 500) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Description must not exceed 500 characters']);
        return;
    }
    
    // Check if category exists
    $checkQuery = "SELECT name FROM Material_Categories WHERE id = :id";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $checkStmt->execute();
    
    $existingCategory = $checkStmt->fetch();
    if (!$existingCategory) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Category not found']);
        return;
    }
    
    // Check for duplicate name (excluding current category)
    $duplicateQuery = "SELECT id FROM Material_Categories WHERE name = :name AND id != :id";
    $duplicateStmt = $pdo->prepare($duplicateQuery);
    $duplicateStmt->bindValue(':name', $name);
    $duplicateStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $duplicateStmt->execute();
    
    if ($duplicateStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'A category with this name already exists']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        $query = "UPDATE Material_Categories SET name = :name, description = :description WHERE id = :id";
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':description', $description);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Log the activity
        $logQuery = "INSERT INTO Activity_Log (admin_id, action, description, timestamp) 
                     VALUES (:admin_id, 'UPDATE', :description, NOW())";
        $logStmt = $pdo->prepare($logQuery);
        $logStmt->bindValue(':admin_id', $currentAdmin['id']);
        $logStmt->bindValue(':description', "Updated category: {$existingCategory['name']} to $name");
        $logStmt->execute();
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Category updated successfully',
            'data' => ['id' => $id, 'name' => $name, 'description' => $description]
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error updating category: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update category']);
    }
}

function handleDeleteCategory() {
    global $pdo, $currentAdmin;
    
    $id = $_POST['id'] ?? $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Category ID is required']);
        return;
    }
    
    // Check if category exists
    $checkQuery = "SELECT mc.name, COUNT(m.id) as material_count 
                   FROM Material_Categories mc 
                   LEFT JOIN Material m ON mc.id = m.category_id 
                   WHERE mc.id = :id 
                   GROUP BY mc.id, mc.name";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $checkStmt->execute();
    
    $category = $checkStmt->fetch();
    if (!$category) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Category not found']);
        return;
    }
    
    // Check if category has materials
    if ($category['material_count'] > 0) {
        http_response_code(409);
        echo json_encode([
            'success' => false, 
            'error' => 'Cannot delete category that contains materials. Please move or remove all materials first.',
            'material_count' => $category['material_count']
        ]);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        $query = "DELETE FROM Material_Categories WHERE id = :id";
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Log the activity
        $logQuery = "INSERT INTO Activity_Log (admin_id, action, description, timestamp) 
                     VALUES (:admin_id, 'DELETE', :description, NOW())";
        $logStmt = $pdo->prepare($logQuery);
        $logStmt->bindValue(':admin_id', $currentAdmin['id']);
        $logStmt->bindValue(':description', "Deleted category: {$category['name']}");
        $logStmt->execute();
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error deleting category: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to delete category']);
    }
}

function handleGetStats() {
    global $pdo;
    
    try {
        // Total categories
        $totalQuery = "SELECT COUNT(*) as total FROM Material_Categories";
        $totalStmt = $pdo->query($totalQuery);
        $totalCategories = $totalStmt->fetch()['total'];
        
        // Categories with materials
        $withMaterialsQuery = "SELECT COUNT(DISTINCT mc.id) as count 
                               FROM Material_Categories mc 
                               INNER JOIN Material m ON mc.id = m.category_id 
                               WHERE m.status = 'active'";
        $withMaterialsStmt = $pdo->query($withMaterialsQuery);
        $categoriesWithMaterials = $withMaterialsStmt->fetch()['count'];
        
        // Empty categories
        $emptyCategories = $totalCategories - $categoriesWithMaterials;
        
        // Usage rate
        $usageRate = $totalCategories > 0 ? round(($categoriesWithMaterials / $totalCategories) * 100, 2) : 0;
        
        // Most used category
        $mostUsedQuery = "SELECT mc.name, COUNT(m.id) as material_count 
                          FROM Material_Categories mc 
                          LEFT JOIN Material m ON mc.id = m.category_id 
                          GROUP BY mc.id, mc.name 
                          ORDER BY material_count DESC 
                          LIMIT 1";
        $mostUsedStmt = $pdo->query($mostUsedQuery);
        $mostUsedCategory = $mostUsedStmt->fetch();
        
        // Average materials per category
        $avgMaterialsQuery = "SELECT AVG(material_count) as avg_materials 
                              FROM (
                                  SELECT COUNT(m.id) as material_count 
                                  FROM Material_Categories mc 
                                  LEFT JOIN Material m ON mc.id = m.category_id 
                                  GROUP BY mc.id
                              ) as counts";
        $avgMaterialsStmt = $pdo->query($avgMaterialsQuery);
        $avgMaterials = $avgMaterialsStmt->fetch()['avg_materials'] ?? 0;
        
        echo json_encode([
            'success' => true,
            'data' => [
                'total_categories' => (int)$totalCategories,
                'categories_with_materials' => (int)$categoriesWithMaterials,
                'empty_categories' => (int)$emptyCategories,
                'usage_rate' => $usageRate,
                'most_used_category' => $mostUsedCategory,
                'average_materials_per_category' => round($avgMaterials, 2)
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Error getting category stats: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to retrieve statistics']);
    }
}

function handleBulkDelete() {
    global $pdo, $currentAdmin;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $categoryIds = $input['category_ids'] ?? [];
    
    if (empty($categoryIds) || !is_array($categoryIds)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Category IDs are required']);
        return;
    }
    
    // Validate that all IDs are integers
    $categoryIds = array_filter($categoryIds, 'is_numeric');
    if (empty($categoryIds)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Valid category IDs are required']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        $placeholders = str_repeat('?,', count($categoryIds) - 1) . '?';
        
        // Check for categories with materials
        $checkQuery = "SELECT mc.id, mc.name, COUNT(m.id) as material_count 
                       FROM Material_Categories mc 
                       LEFT JOIN Material m ON mc.id = m.category_id 
                       WHERE mc.id IN ($placeholders) 
                       GROUP BY mc.id, mc.name 
                       HAVING material_count > 0";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->execute($categoryIds);
        $categoriesWithMaterials = $checkStmt->fetchAll();
        
        if (!empty($categoriesWithMaterials)) {
            $pdo->rollBack();
            $names = array_column($categoriesWithMaterials, 'name');
            http_response_code(409);
            echo json_encode([
                'success' => false, 
                'error' => 'Cannot delete categories that contain materials: ' . implode(', ', $names),
                'categories_with_materials' => $categoriesWithMaterials
            ]);
            return;
        }
        
        // Get category names for logging
        $namesQuery = "SELECT name FROM Material_Categories WHERE id IN ($placeholders)";
        $namesStmt = $pdo->prepare($namesQuery);
        $namesStmt->execute($categoryIds);
        $categoryNames = $namesStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Delete categories
        $deleteQuery = "DELETE FROM Material_Categories WHERE id IN ($placeholders)";
        $deleteStmt = $pdo->prepare($deleteQuery);
        $deleteStmt->execute($categoryIds);
        
        $deletedCount = $deleteStmt->rowCount();
        
        // Log the activity
        $logQuery = "INSERT INTO Activity_Log (admin_id, action, description, timestamp) 
                     VALUES (:admin_id, 'BULK_DELETE', :description, NOW())";
        $logStmt = $pdo->prepare($logQuery);
        $logStmt->bindValue(':admin_id', $currentAdmin['id']);
        $logStmt->bindValue(':description', "Bulk deleted $deletedCount categories: " . implode(', ', $categoryNames));
        $logStmt->execute();
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => "$deletedCount categories deleted successfully",
            'deleted_count' => $deletedCount
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error in bulk delete categories: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to delete categories']);
    }
}

function handleMergeCategories() {
    global $pdo, $currentAdmin;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $targetCategoryId = $input['target_category_id'] ?? null;
    $sourceCategoryIds = $input['source_category_ids'] ?? [];
    
    if (!$targetCategoryId || empty($sourceCategoryIds) || !is_array($sourceCategoryIds)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Target category ID and source category IDs are required']);
        return;
    }
    
    // Validate that target is not in source list
    if (in_array($targetCategoryId, $sourceCategoryIds)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Target category cannot be in the source categories list']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Check if target category exists
        $targetQuery = "SELECT name FROM Material_Categories WHERE id = :id";
        $targetStmt = $pdo->prepare($targetQuery);
        $targetStmt->bindValue(':id', $targetCategoryId, PDO::PARAM_INT);
        $targetStmt->execute();
        $targetCategory = $targetStmt->fetch();
        
        if (!$targetCategory) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Target category not found']);
            return;
        }
        
        $placeholders = str_repeat('?,', count($sourceCategoryIds) - 1) . '?';
        
        // Get source category names for logging
        $sourceNamesQuery = "SELECT name FROM Material_Categories WHERE id IN ($placeholders)";
        $sourceNamesStmt = $pdo->prepare($sourceNamesQuery);
        $sourceNamesStmt->execute($sourceCategoryIds);
        $sourceNames = $sourceNamesStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Update all materials from source categories to target category
        $updateQuery = "UPDATE Material SET category_id = ? WHERE category_id IN ($placeholders)";
        $updateStmt = $pdo->prepare($updateQuery);
        $updateParams = array_merge([$targetCategoryId], $sourceCategoryIds);
        $updateStmt->execute($updateParams);
        
        $movedMaterials = $updateStmt->rowCount();
        
        // Delete source categories
        $deleteQuery = "DELETE FROM Material_Categories WHERE id IN ($placeholders)";
        $deleteStmt = $pdo->prepare($deleteQuery);
        $deleteStmt->execute($sourceCategoryIds);
        
        $deletedCategories = $deleteStmt->rowCount();
        
        // Log the activity
        $logQuery = "INSERT INTO Activity_Log (admin_id, action, description, timestamp) 
                     VALUES (:admin_id, 'MERGE', :description, NOW())";
        $logStmt = $pdo->prepare($logQuery);
        $logStmt->bindValue(':admin_id', $currentAdmin['id']);
        $logStmt->bindValue(':description', "Merged categories (" . implode(', ', $sourceNames) . ") into {$targetCategory['name']}. Moved $movedMaterials materials.");
        $logStmt->execute();
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => "Successfully merged $deletedCategories categories into {$targetCategory['name']}",
            'moved_materials' => $movedMaterials,
            'deleted_categories' => $deletedCategories
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error merging categories: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to merge categories']);
    }
}

function handleExport() {
    global $pdo;
    
    $format = $_GET['format'] ?? 'csv';
    $categoryIds = $_GET['category_ids'] ?? null;
    
    $whereClause = "";
    $params = [];
    
    if ($categoryIds) {
        $ids = explode(',', $categoryIds);
        $ids = array_filter($ids, 'is_numeric');
        if (!empty($ids)) {
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $whereClause = "WHERE mc.id IN ($placeholders)";
            $params = $ids;
        }
    }
    
    $query = "SELECT mc.*, 
                     COUNT(m.id) as material_count,
                     COALESCE(SUM(CASE WHEN m.status = 'active' THEN 1 ELSE 0 END), 0) as active_materials,
                     COALESCE(AVG(m.price_per_unit), 0) as avg_price
              FROM Material_Categories mc 
              LEFT JOIN Material m ON mc.id = m.category_id 
              $whereClause
              GROUP BY mc.id, mc.name, mc.description
              ORDER BY mc.name";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="categories_export_' . date('Y-m-d_H-i-s') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV Headers
        fputcsv($output, [
            'ID', 'Name', 'Description', 'Material Count', 
            'Active Materials', 'Average Price'
        ]);
        
        // CSV Data
        foreach ($categories as $category) {
            fputcsv($output, [
                $category['id'],
                $category['name'],
                $category['description'] ?? '',
                $category['material_count'],
                $category['active_materials'],
                number_format($category['avg_price'], 2)
            ]);
        }
        
        fclose($output);
    } else {
        // JSON format
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="categories_export_' . date('Y-m-d_H-i-s') . '.json"');
        
        echo json_encode([
            'export_date' => date('Y-m-d H:i:s'),
            'total_categories' => count($categories),
            'data' => $categories
        ], JSON_PRETTY_PRINT);
    }
}

function handleImport() {
    global $pdo, $currentAdmin;
    
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error occurred']);
        return;
    }
    
    $uploadedFile = $_FILES['file'];
    $fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
    
    if ($fileExtension !== 'csv') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Only CSV files are supported']);
        return;
    }
    
    try {
        $file = fopen($uploadedFile['tmp_name'], 'r');
        if (!$file) {
            throw new Exception('Could not open uploaded file');
        }
        
        $pdo->beginTransaction();
        
        // Skip header row
        $header = fgetcsv($file);
        
        $importedCount = 0;
        $errors = [];
        $rowNumber = 1;
        
        while (($row = fgetcsv($file)) !== FALSE) {
            $rowNumber++;
            
            if (count($row) < 2) {
                $errors[] = "Row $rowNumber: Insufficient data (name and description required)";
                continue;
            }
            
            $name = trim($row[0]);
            $description = trim($row[1] ?? '');
            
            if (empty($name)) {
                $errors[] = "Row $rowNumber: Category name is required";
                continue;
            }
            
            // Check for duplicate
            $checkQuery = "SELECT id FROM Material_Categories WHERE name = :name";
            $checkStmt = $pdo->prepare($checkQuery);
            $checkStmt->bindValue(':name', $name);
            $checkStmt->execute();
            
            if ($checkStmt->fetch()) {
                $errors[] = "Row $rowNumber: Category '$name' already exists";
                continue;
            }
            
            // Insert category
            $insertQuery = "INSERT INTO Material_Categories (name, description) VALUES (:name, :description)";
            $insertStmt = $pdo->prepare($insertQuery);
            $insertStmt->bindValue(':name', $name);
            $insertStmt->bindValue(':description', $description);
            
            if ($insertStmt->execute()) {
                $importedCount++;
            } else {
                $errors[] = "Row $rowNumber: Failed to insert category '$name'";
            }
        }
        
        fclose($file);
        
        // Log the activity
        $logQuery = "INSERT INTO Activity_Log (admin_id, action, description, timestamp) 
                     VALUES (:admin_id, 'IMPORT', :description, NOW())";
        $logStmt = $pdo->prepare($logQuery);
        $logStmt->bindValue(':admin_id', $currentAdmin['id']);
        $logStmt->bindValue(':description', "Imported $importedCount categories from CSV");
        $logStmt->execute();
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Import completed. $importedCount categories imported.",
            'imported_count' => $importedCount,
            'errors' => $errors
        ]);
        
    } catch (Exception $e) {
        if (isset($file)) fclose($file);
        $pdo->rollBack();
        error_log("Error importing categories: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to import categories']);
    }
}

function handleCheckUsage() {
    global $pdo;
    
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Category ID is required']);
        return;
    }
    
    try {
        // Check materials count
        $materialsQuery = "SELECT COUNT(*) as count FROM Material WHERE category_id = :id";
        $materialsStmt = $pdo->prepare($materialsQuery);
        $materialsStmt->bindValue(':id', $id, PDO::PARAM_INT);
        $materialsStmt->execute();
        $materialCount = $materialsStmt->fetch()['count'];
        
        // Check borrowing history (if there are borrowed materials from this category)
        $borrowingQuery = "SELECT COUNT(DISTINCT br.id) as count 
                           FROM Borrowing_Record br 
                           INNER JOIN Material m ON br.material_id = m.id 
                           WHERE m.category_id = :id";
        $borrowingStmt = $pdo->prepare($borrowingQuery);
        $borrowingStmt->bindValue(':id', $id, PDO::PARAM_INT);
        $borrowingStmt->execute();
        $borrowingCount = $borrowingStmt->fetch()['count'] ?? 0;
        
        echo json_encode([
            'success' => true,
            'data' => [
                'material_count' => (int)$materialCount,
                'borrowing_count' => (int)$borrowingCount,
                'can_delete' => $materialCount == 0,
                'usage_level' => $materialCount > 0 ? 'high' : 'none'
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Error checking category usage: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to check category usage']);
    }
}
?>
