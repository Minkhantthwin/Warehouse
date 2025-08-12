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
            handleListLocations();
            break;
        case 'get':
            handleGetLocation();
            break;
        case 'create':
            handleCreateLocation();
            break;
        case 'update':
            handleUpdateLocation();
            break;
        case 'delete':
            handleDeleteLocation();
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
        case 'check_usage':
            handleCheckUsage();
            break;
        case 'assign_materials':
            handleAssignMaterials();
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action specified']);
            break;
    }
} catch (Exception $e) {
    error_log("Locations API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error occurred']);
}

function handleListLocations() {
    global $pdo;
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = min((int)($_GET['limit'] ?? 10), 100); // Max 100 items per page
    $offset = ($page - 1) * $limit;
    
    $search = $_GET['search'] ?? '';
    $country = $_GET['country'] ?? '';
    $state = $_GET['state'] ?? '';
    $has_inventory = $_GET['has_inventory'] ?? '';
    $sort_by = $_GET['sort_by'] ?? 'name';
    $sort_order = $_GET['sort_order'] ?? 'ASC';
    
    // Validate sort parameters
    $validSortColumns = ['name', 'city', 'state', 'country', 'total_inventory', 'created_at'];
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
        $whereClause .= " AND (l.name LIKE :search OR l.address LIKE :search OR l.city LIKE :search OR l.state LIKE :search OR l.country LIKE :search)";
        $params['search'] = "%" . $search . "%";
    }
    
    if (!empty($country)) {
        $whereClause .= " AND l.country = :country";
        $params['country'] = $country;
    }
    
    if (!empty($state)) {
        $whereClause .= " AND l.state = :state";
        $params['state'] = $state;
    }
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM Location l $whereClause";
    $countStmt = $pdo->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue(":$key", $value);
    }
    $countStmt->execute();
    $totalItems = $countStmt->fetch()['total'];
    
    // Get locations with inventory and usage info
    $query = "SELECT l.*, 
                     COUNT(DISTINCT i.material_id) as unique_materials,
                     COALESCE(SUM(i.quantity), 0) as total_inventory,
                     COUNT(DISTINCT br.id) as borrowing_requests,
                     COUNT(DISTINCT bt.id) as transactions,
                     COALESCE(SUM(CASE WHEN i.quantity > 0 THEN 1 ELSE 0 END), 0) as materials_with_stock
              FROM Location l 
              LEFT JOIN Inventory i ON l.id = i.location_id 
              LEFT JOIN Borrowing_Request br ON l.id = br.location_id
              LEFT JOIN Borrowing_Transaction bt ON br.id = bt.borrowing_request_id
              $whereClause";
    
    // Apply has_inventory filter
    if ($has_inventory === 'true') {
        $query .= " HAVING total_inventory > 0";
    } elseif ($has_inventory === 'false') {
        $query .= " HAVING total_inventory = 0";
    }
    
    $query .= " GROUP BY l.id, l.name, l.address, l.city, l.state, l.zip_code, l.country";
    
    // Apply sorting
    if ($sort_by === 'total_inventory') {
        $query .= " ORDER BY total_inventory $sort_order";
    } else {
        $query .= " ORDER BY l.$sort_by $sort_order";
    }
    
    $query .= " LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate pagination info
    $totalPages = ceil($totalItems / $limit);
    $hasNext = $page < $totalPages;
    $hasPrev = $page > 1;
    
    echo json_encode([
        'success' => true,
        'data' => $locations,
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

function handleGetLocation() {
    global $pdo;
    
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Location ID is required']);
        return;
    }
    
    $query = "SELECT l.*, 
                     COUNT(DISTINCT i.material_id) as unique_materials,
                     COALESCE(SUM(i.quantity), 0) as total_inventory,
                     COUNT(DISTINCT br.id) as borrowing_requests,
                     COUNT(DISTINCT bt.id) as transactions,
                     COALESCE(SUM(CASE WHEN i.quantity > 0 THEN 1 ELSE 0 END), 0) as materials_with_stock
              FROM Location l 
              LEFT JOIN Inventory i ON l.id = i.location_id 
              LEFT JOIN Borrowing_Request br ON l.id = br.location_id
              LEFT JOIN Borrowing_Transaction bt ON br.id = bt.borrowing_request_id
              WHERE l.id = :id
              GROUP BY l.id";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $location = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$location) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Location not found']);
        return;
    }
    
    // Get recent inventory items in this location
    $inventoryQuery = "SELECT m.id, m.name, mc.name as category_name, i.quantity, m.unit,
                              m.price_per_unit, i.last_updated
                       FROM Inventory i 
                       INNER JOIN Material m ON i.material_id = m.id
                       LEFT JOIN Material_Categories mc ON m.category_id = mc.id
                       WHERE i.location_id = :id AND i.quantity > 0
                       ORDER BY i.last_updated DESC 
                       LIMIT 10";
    
    $inventoryStmt = $pdo->prepare($inventoryQuery);
    $inventoryStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $inventoryStmt->execute();
    
    $location['recent_inventory'] = $inventoryStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent borrowing requests for this location
    $requestsQuery = "SELECT br.id, br.request_date, br.status, br.purpose,
                             c.name as customer_name, e.name as employee_name
                      FROM Borrowing_Request br
                      LEFT JOIN Customer c ON br.customer_id = c.id
                      LEFT JOIN Employee e ON br.employee_id = e.id
                      WHERE br.location_id = :id
                      ORDER BY br.request_date DESC
                      LIMIT 5";
    
    $requestsStmt = $pdo->prepare($requestsQuery);
    $requestsStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $requestsStmt->execute();
    
    $location['recent_requests'] = $requestsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $location]);
}

function handleCreateLocation() {
    global $pdo, $currentAdmin;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $name = trim($input['name'] ?? '');
    $address = trim($input['address'] ?? '');
    $city = trim($input['city'] ?? '');
    $state = trim($input['state'] ?? '');
    $zip_code = trim($input['zip_code'] ?? '');
    $country = trim($input['country'] ?? '');
    
    // Validation
    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Location name is required']);
        return;
    }
    
    if (strlen($name) > 100) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Location name must not exceed 100 characters']);
        return;
    }
    
    // Check for duplicate name
    $checkQuery = "SELECT id FROM Location WHERE name = :name";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->bindValue(':name', $name);
    $checkStmt->execute();
    
    if ($checkStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'A location with this name already exists']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        $query = "INSERT INTO Location (name, address, city, state, zip_code, country) 
                  VALUES (:name, :address, :city, :state, :zip_code, :country)";
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':address', $address);
        $stmt->bindValue(':city', $city);
        $stmt->bindValue(':state', $state);
        $stmt->bindValue(':zip_code', $zip_code);
        $stmt->bindValue(':country', $country);
        $stmt->execute();
        
        $locationId = $pdo->lastInsertId();
        
        // Log the activity
        $logQuery = "INSERT INTO Activity_Log (admin_id, action, description, timestamp) 
                     VALUES (:admin_id, 'CREATE', :description, NOW())";
        $logStmt = $pdo->prepare($logQuery);
        $logStmt->bindValue(':admin_id', $currentAdmin['id']);
        $logStmt->bindValue(':description', "Created location: $name");
        $logStmt->execute();
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Location created successfully',
            'data' => [
                'id' => $locationId, 
                'name' => $name, 
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'zip_code' => $zip_code,
                'country' => $country
            ]
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error creating location: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to create location']);
    }
}

function handleUpdateLocation() {
    global $pdo, $currentAdmin;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = $input['id'] ?? null;
    $name = trim($input['name'] ?? '');
    $address = trim($input['address'] ?? '');
    $city = trim($input['city'] ?? '');
    $state = trim($input['state'] ?? '');
    $zip_code = trim($input['zip_code'] ?? '');
    $country = trim($input['country'] ?? '');
    
    // Validation
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Location ID is required']);
        return;
    }
    
    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Location name is required']);
        return;
    }
    
    if (strlen($name) > 100) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Location name must not exceed 100 characters']);
        return;
    }
    
    // Check if location exists
    $checkQuery = "SELECT name FROM Location WHERE id = :id";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $checkStmt->execute();
    
    $existingLocation = $checkStmt->fetch();
    if (!$existingLocation) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Location not found']);
        return;
    }
    
    // Check for duplicate name (excluding current location)
    $duplicateQuery = "SELECT id FROM Location WHERE name = :name AND id != :id";
    $duplicateStmt = $pdo->prepare($duplicateQuery);
    $duplicateStmt->bindValue(':name', $name);
    $duplicateStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $duplicateStmt->execute();
    
    if ($duplicateStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'A location with this name already exists']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        $query = "UPDATE Location SET name = :name, address = :address, city = :city, 
                  state = :state, zip_code = :zip_code, country = :country WHERE id = :id";
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':address', $address);
        $stmt->bindValue(':city', $city);
        $stmt->bindValue(':state', $state);
        $stmt->bindValue(':zip_code', $zip_code);
        $stmt->bindValue(':country', $country);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Log the activity
        $logQuery = "INSERT INTO Activity_Log (admin_id, action, description, timestamp) 
                     VALUES (:admin_id, 'UPDATE', :description, NOW())";
        $logStmt = $pdo->prepare($logQuery);
        $logStmt->bindValue(':admin_id', $currentAdmin['id']);
        $logStmt->bindValue(':description', "Updated location: {$existingLocation['name']} to $name");
        $logStmt->execute();
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Location updated successfully',
            'data' => [
                'id' => $id, 
                'name' => $name, 
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'zip_code' => $zip_code,
                'country' => $country
            ]
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error updating location: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update location']);
    }
}

function handleDeleteLocation() {
    global $pdo, $currentAdmin;
    
    $id = $_POST['id'] ?? $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Location ID is required']);
        return;
    }
    
    // Check if location exists and get usage info
    $checkQuery = "SELECT l.name, 
                          COALESCE(SUM(i.quantity), 0) as total_inventory,
                          COUNT(DISTINCT br.id) as borrowing_requests
                   FROM Location l 
                   LEFT JOIN Inventory i ON l.id = i.location_id 
                   LEFT JOIN Borrowing_Request br ON l.id = br.location_id
                   WHERE l.id = :id 
                   GROUP BY l.id, l.name";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $checkStmt->execute();
    
    $location = $checkStmt->fetch();
    if (!$location) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Location not found']);
        return;
    }
    
    // Check if location has inventory or borrowing requests
    if ($location['total_inventory'] > 0) {
        http_response_code(409);
        echo json_encode([
            'success' => false, 
            'error' => 'Cannot delete location that contains inventory. Please move all inventory items first.',
            'inventory_count' => $location['total_inventory']
        ]);
        return;
    }
    
    if ($location['borrowing_requests'] > 0) {
        http_response_code(409);
        echo json_encode([
            'success' => false, 
            'error' => 'Cannot delete location that has borrowing requests. Please resolve all requests first.',
            'requests_count' => $location['borrowing_requests']
        ]);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        $query = "DELETE FROM Location WHERE id = :id";
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Log the activity
        $logQuery = "INSERT INTO Activity_Log (admin_id, action, description, timestamp) 
                     VALUES (:admin_id, 'DELETE', :description, NOW())";
        $logStmt = $pdo->prepare($logQuery);
        $logStmt->bindValue(':admin_id', $currentAdmin['id']);
        $logStmt->bindValue(':description', "Deleted location: {$location['name']}");
        $logStmt->execute();
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Location deleted successfully']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error deleting location: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to delete location']);
    }
}

function handleGetStats() {
    global $pdo;
    
    try {
        // Total locations
        $totalQuery = "SELECT COUNT(*) as total FROM Location";
        $totalStmt = $pdo->query($totalQuery);
        $totalLocations = $totalStmt->fetch()['total'];
        
        // Locations with inventory
        $withInventoryQuery = "SELECT COUNT(DISTINCT l.id) as count 
                               FROM Location l 
                               INNER JOIN Inventory i ON l.id = i.location_id 
                               WHERE i.quantity > 0";
        $withInventoryStmt = $pdo->query($withInventoryQuery);
        $locationsWithInventory = $withInventoryStmt->fetch()['count'];
        
        // Empty locations
        $emptyLocations = $totalLocations - $locationsWithInventory;
        
        // Total inventory across all locations
        $inventoryQuery = "SELECT SUM(quantity) as total_inventory FROM Inventory";
        $inventoryStmt = $pdo->query($inventoryQuery);
        $totalInventory = $inventoryStmt->fetch()['total_inventory'] ?? 0;
        
        // Location with most inventory
        $topLocationQuery = "SELECT l.name, l.city, l.state, SUM(i.quantity) as inventory_count 
                             FROM Location l 
                             INNER JOIN Inventory i ON l.id = i.location_id 
                             GROUP BY l.id, l.name, l.city, l.state 
                             ORDER BY inventory_count DESC 
                             LIMIT 1";
        $topLocationStmt = $pdo->query($topLocationQuery);
        $topLocation = $topLocationStmt->fetch();
        
        // Average inventory per location
        $avgInventoryQuery = "SELECT AVG(inventory_count) as avg_inventory 
                              FROM (
                                  SELECT SUM(i.quantity) as inventory_count 
                                  FROM Location l 
                                  LEFT JOIN Inventory i ON l.id = i.location_id 
                                  GROUP BY l.id
                              ) as counts";
        $avgInventoryStmt = $pdo->query($avgInventoryQuery);
        $avgInventory = $avgInventoryStmt->fetch()['avg_inventory'] ?? 0;
        
        // Country distribution
        $countryQuery = "SELECT country, COUNT(*) as count 
                         FROM Location 
                         WHERE country IS NOT NULL 
                         GROUP BY country 
                         ORDER BY count DESC";
        $countryStmt = $pdo->query($countryQuery);
        $countryDistribution = $countryStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'total_locations' => (int)$totalLocations,
                'locations_with_inventory' => (int)$locationsWithInventory,
                'empty_locations' => (int)$emptyLocations,
                'total_inventory' => (int)$totalInventory,
                'top_location' => $topLocation,
                'average_inventory_per_location' => round($avgInventory, 2),
                'country_distribution' => $countryDistribution
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Error getting location stats: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to retrieve statistics']);
    }
}

function handleBulkDelete() {
    global $pdo, $currentAdmin;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $locationIds = $input['location_ids'] ?? [];
    
    if (empty($locationIds) || !is_array($locationIds)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Location IDs are required']);
        return;
    }
    
    // Validate that all IDs are integers
    $locationIds = array_filter($locationIds, 'is_numeric');
    if (empty($locationIds)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Valid location IDs are required']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        $placeholders = str_repeat('?,', count($locationIds) - 1) . '?';
        
        // Check for locations with inventory or borrowing requests
        $checkQuery = "SELECT l.id, l.name, 
                              COALESCE(SUM(i.quantity), 0) as inventory_count,
                              COUNT(DISTINCT br.id) as request_count
                       FROM Location l 
                       LEFT JOIN Inventory i ON l.id = i.location_id 
                       LEFT JOIN Borrowing_Request br ON l.id = br.location_id
                       WHERE l.id IN ($placeholders) 
                       GROUP BY l.id, l.name 
                       HAVING inventory_count > 0 OR request_count > 0";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->execute($locationIds);
        $locationsWithData = $checkStmt->fetchAll();
        
        if (!empty($locationsWithData)) {
            $pdo->rollBack();
            $names = array_column($locationsWithData, 'name');
            http_response_code(409);
            echo json_encode([
                'success' => false, 
                'error' => 'Cannot delete locations that contain inventory or have borrowing requests: ' . implode(', ', $names),
                'locations_with_data' => $locationsWithData
            ]);
            return;
        }
        
        // Get location names for logging
        $namesQuery = "SELECT name FROM Location WHERE id IN ($placeholders)";
        $namesStmt = $pdo->prepare($namesQuery);
        $namesStmt->execute($locationIds);
        $locationNames = $namesStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Delete locations
        $deleteQuery = "DELETE FROM Location WHERE id IN ($placeholders)";
        $deleteStmt = $pdo->prepare($deleteQuery);
        $deleteStmt->execute($locationIds);
        
        $deletedCount = $deleteStmt->rowCount();
        
        // Log the activity
        $logQuery = "INSERT INTO Activity_Log (admin_id, action, description, timestamp) 
                     VALUES (:admin_id, 'BULK_DELETE', :description, NOW())";
        $logStmt = $pdo->prepare($logQuery);
        $logStmt->bindValue(':admin_id', $currentAdmin['id']);
        $logStmt->bindValue(':description', "Bulk deleted $deletedCount locations: " . implode(', ', $locationNames));
        $logStmt->execute();
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => "$deletedCount locations deleted successfully",
            'deleted_count' => $deletedCount
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error in bulk delete locations: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to delete locations']);
    }
}

function handleExport() {
    global $pdo;
    
    $format = $_GET['format'] ?? 'csv';
    $locationIds = $_GET['location_ids'] ?? null;
    
    $whereClause = "";
    $params = [];
    
    if ($locationIds) {
        $ids = explode(',', $locationIds);
        $ids = array_filter($ids, 'is_numeric');
        if (!empty($ids)) {
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $whereClause = "WHERE l.id IN ($placeholders)";
            $params = $ids;
        }
    }
    
    $query = "SELECT l.*, 
                     COUNT(DISTINCT i.material_id) as unique_materials,
                     COALESCE(SUM(i.quantity), 0) as total_inventory,
                     COUNT(DISTINCT br.id) as borrowing_requests
              FROM Location l 
              LEFT JOIN Inventory i ON l.id = i.location_id 
              LEFT JOIN Borrowing_Request br ON l.id = br.location_id
              $whereClause
              GROUP BY l.id, l.name, l.address, l.city, l.state, l.zip_code, l.country
              ORDER BY l.name";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="locations_export_' . date('Y-m-d_H-i-s') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV Headers
        fputcsv($output, [
            'ID', 'Name', 'Address', 'City', 'State', 'ZIP Code', 'Country',
            'Unique Materials', 'Total Inventory', 'Borrowing Requests'
        ]);
        
        // CSV Data
        foreach ($locations as $location) {
            fputcsv($output, [
                $location['id'],
                $location['name'],
                $location['address'] ?? '',
                $location['city'] ?? '',
                $location['state'] ?? '',
                $location['zip_code'] ?? '',
                $location['country'] ?? '',
                $location['unique_materials'],
                $location['total_inventory'],
                $location['borrowing_requests']
            ]);
        }
        
        fclose($output);
    } else {
        // JSON format
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="locations_export_' . date('Y-m-d_H-i-s') . '.json"');
        
        echo json_encode([
            'export_date' => date('Y-m-d H:i:s'),
            'total_locations' => count($locations),
            'data' => $locations
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
            
            if (count($row) < 1) {
                $errors[] = "Row $rowNumber: Insufficient data (name required)";
                continue;
            }
            
            $name = trim($row[0]);
            $address = trim($row[1] ?? '');
            $city = trim($row[2] ?? '');
            $state = trim($row[3] ?? '');
            $zip_code = trim($row[4] ?? '');
            $country = trim($row[5] ?? '');
            
            if (empty($name)) {
                $errors[] = "Row $rowNumber: Location name is required";
                continue;
            }
            
            // Check for duplicate
            $checkQuery = "SELECT id FROM Location WHERE name = :name";
            $checkStmt = $pdo->prepare($checkQuery);
            $checkStmt->bindValue(':name', $name);
            $checkStmt->execute();
            
            if ($checkStmt->fetch()) {
                $errors[] = "Row $rowNumber: Location '$name' already exists";
                continue;
            }
            
            // Insert location
            $insertQuery = "INSERT INTO Location (name, address, city, state, zip_code, country) 
                            VALUES (:name, :address, :city, :state, :zip_code, :country)";
            $insertStmt = $pdo->prepare($insertQuery);
            $insertStmt->bindValue(':name', $name);
            $insertStmt->bindValue(':address', $address);
            $insertStmt->bindValue(':city', $city);
            $insertStmt->bindValue(':state', $state);
            $insertStmt->bindValue(':zip_code', $zip_code);
            $insertStmt->bindValue(':country', $country);
            
            if ($insertStmt->execute()) {
                $importedCount++;
            } else {
                $errors[] = "Row $rowNumber: Failed to insert location '$name'";
            }
        }
        
        fclose($file);
        
        // Log the activity
        $logQuery = "INSERT INTO Activity_Log (admin_id, action, description, timestamp) 
                     VALUES (:admin_id, 'IMPORT', :description, NOW())";
        $logStmt = $pdo->prepare($logQuery);
        $logStmt->bindValue(':admin_id', $currentAdmin['id']);
        $logStmt->bindValue(':description', "Imported $importedCount locations from CSV");
        $logStmt->execute();
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Import completed. $importedCount locations imported.",
            'imported_count' => $importedCount,
            'errors' => $errors
        ]);
        
    } catch (Exception $e) {
        if (isset($file)) fclose($file);
        $pdo->rollBack();
        error_log("Error importing locations: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to import locations']);
    }
}

function handleCheckUsage() {
    global $pdo;
    
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Location ID is required']);
        return;
    }
    
    try {
        // Check inventory count
        $inventoryQuery = "SELECT SUM(quantity) as count FROM Inventory WHERE location_id = :id";
        $inventoryStmt = $pdo->prepare($inventoryQuery);
        $inventoryStmt->bindValue(':id', $id, PDO::PARAM_INT);
        $inventoryStmt->execute();
        $inventoryCount = $inventoryStmt->fetch()['count'] ?? 0;
        
        // Check borrowing requests
        $requestsQuery = "SELECT COUNT(*) as count FROM Borrowing_Request WHERE location_id = :id";
        $requestsStmt = $pdo->prepare($requestsQuery);
        $requestsStmt->bindValue(':id', $id, PDO::PARAM_INT);
        $requestsStmt->execute();
        $requestsCount = $requestsStmt->fetch()['count'] ?? 0;
        
        echo json_encode([
            'success' => true,
            'data' => [
                'inventory_count' => (int)$inventoryCount,
                'requests_count' => (int)$requestsCount,
                'can_delete' => $inventoryCount == 0 && $requestsCount == 0,
                'usage_level' => $inventoryCount > 0 ? 'high' : ($requestsCount > 0 ? 'medium' : 'none')
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Error checking location usage: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to check location usage']);
    }
}

function handleAssignMaterials() {
    global $pdo, $currentAdmin;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $locationId = $input['location_id'] ?? null;
    $materialAssignments = $input['material_assignments'] ?? [];
    
    if (!$locationId || empty($materialAssignments)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Location ID and material assignments are required']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        $assignedCount = 0;
        $errors = [];
        
        foreach ($materialAssignments as $assignment) {
            $materialId = $assignment['material_id'] ?? null;
            $quantity = $assignment['quantity'] ?? 0;
            
            if (!$materialId || $quantity <= 0) {
                $errors[] = "Invalid material assignment: material_id=$materialId, quantity=$quantity";
                continue;
            }
            
            // Check if inventory record exists
            $checkQuery = "SELECT id, quantity FROM Inventory WHERE material_id = :material_id AND location_id = :location_id";
            $checkStmt = $pdo->prepare($checkQuery);
            $checkStmt->bindValue(':material_id', $materialId, PDO::PARAM_INT);
            $checkStmt->bindValue(':location_id', $locationId, PDO::PARAM_INT);
            $checkStmt->execute();
            
            $existingRecord = $checkStmt->fetch();
            
            if ($existingRecord) {
                // Update existing record
                $updateQuery = "UPDATE Inventory SET quantity = quantity + :quantity, last_updated = NOW() 
                                WHERE id = :id";
                $updateStmt = $pdo->prepare($updateQuery);
                $updateStmt->bindValue(':quantity', $quantity, PDO::PARAM_INT);
                $updateStmt->bindValue(':id', $existingRecord['id'], PDO::PARAM_INT);
                $updateStmt->execute();
            } else {
                // Create new record
                $insertQuery = "INSERT INTO Inventory (material_id, location_id, quantity, last_updated) 
                                VALUES (:material_id, :location_id, :quantity, NOW())";
                $insertStmt = $pdo->prepare($insertQuery);
                $insertStmt->bindValue(':material_id', $materialId, PDO::PARAM_INT);
                $insertStmt->bindValue(':location_id', $locationId, PDO::PARAM_INT);
                $insertStmt->bindValue(':quantity', $quantity, PDO::PARAM_INT);
                $insertStmt->execute();
            }
            
            $assignedCount++;
        }
        
        // Log the activity
        $logQuery = "INSERT INTO Activity_Log (admin_id, action, description, timestamp) 
                     VALUES (:admin_id, 'UPDATE', :description, NOW())";
        $logStmt = $pdo->prepare($logQuery);
        $logStmt->bindValue(':admin_id', $currentAdmin['id']);
        $logStmt->bindValue(':description', "Assigned $assignedCount materials to location ID: $locationId");
        $logStmt->execute();
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "$assignedCount materials assigned successfully",
            'assigned_count' => $assignedCount,
            'errors' => $errors
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error assigning materials: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to assign materials']);
    }
}
?>
