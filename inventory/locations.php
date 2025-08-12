<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check remember me token
checkRememberMe();

// Require login
requireLogin();

// Check permission for inventory management
if (!hasPermission('inventory_management')) {
    header('Location: ../index.php');
    exit();
}

$currentAdmin = getLoggedInAdmin();

// Define quick actions for this page
$quickActions = [
    [
        'text' => 'Add Location',
        'icon' => 'fas fa-plus',
        'class' => 'btn-primary',
        'onclick' => 'openModal(\'add-location-modal\')'
    ],
    [
        'text' => 'Import Locations',
        'icon' => 'fas fa-upload',
        'class' => 'btn-secondary',
        'onclick' => 'openModal(\'bulk-import-modal\')'
    ]
];

// Get location statistics
function getLocationStats($pdo) {
    $stats = [];
    
    // Total locations
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM Location");
    $stats['total_locations'] = $stmt->fetch()['total'] ?? 0;
    
    // Active locations (all for now)
    $stats['active_locations'] = $stats['total_locations'];
    
    // Locations with inventory
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT l.id) as locations_with_inventory 
        FROM Location l 
        INNER JOIN Inventory i ON l.id = i.location_id 
        WHERE i.quantity > 0
    ");
    $stats['locations_with_inventory'] = $stmt->fetch()['locations_with_inventory'] ?? 0;
    
    // Empty locations
    $stats['empty_locations'] = $stats['total_locations'] - $stats['locations_with_inventory'];
    
    // Total inventory items across all locations
    $stmt = $pdo->query("SELECT SUM(quantity) as total_inventory FROM Inventory");
    $stats['total_inventory'] = $stmt->fetch()['total_inventory'] ?? 0;
    
    return $stats;
}

// Get locations with pagination and filters
function getLocations($pdo, $page = 1, $limit = 10, $filters = []) {
    $offset = ($page - 1) * $limit;
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if (!empty($filters['search'])) {
        $whereClause .= " AND (l.name LIKE :search OR l.address LIKE :search OR l.city LIKE :search OR l.state LIKE :search OR l.country LIKE :search)";
        $params['search'] = "%" . $filters['search'] . "%";
    }
    
    if (!empty($filters['country'])) {
        $whereClause .= " AND l.country = :country";
        $params['country'] = $filters['country'];
    }
    
    if (!empty($filters['state'])) {
        $whereClause .= " AND l.state = :state";
        $params['state'] = $filters['state'];
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
              ORDER BY l.name ASC 
              LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($query);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll();
}

// Get unique countries and states for filters
function getLocationFilters($pdo) {
    $countries = [];
    $states = [];
    
    $countryStmt = $pdo->query("SELECT DISTINCT country FROM Location WHERE country IS NOT NULL ORDER BY country");
    $countries = $countryStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $stateStmt = $pdo->query("SELECT DISTINCT state FROM Location WHERE state IS NOT NULL ORDER BY state");
    $states = $stateStmt->fetchAll(PDO::FETCH_COLUMN);
    
    return ['countries' => $countries, 'states' => $states];
}

// Get current filters and pagination
$filters = [
    'search' => $_GET['search'] ?? '',
    'country' => $_GET['country'] ?? '',
    'state' => $_GET['state'] ?? ''
];

$page = (int)($_GET['page'] ?? 1);
$limit = 10;

// Get data
$stats = getLocationStats($pdo);
$locations = getLocations($pdo, $page, $limit, $filters);
$filterOptions = getLocationFilters($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse Locations - Warehouse Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3B82F6',
                        secondary: '#1E40AF',
                        accent: '#F59E0B',
                        success: '#10B981',
                        warning: '#F59E0B',
                        danger: '#EF4444',
                        dark: '#1F2937',
                        light: '#F9FAFB'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="bg-gray-100">
    <?php include '../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="lg:ml-64">
        <?php include '../includes/navbar.php'; ?>

        <!-- Main Content Area -->
        <main class="p-6">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-map-marker-alt text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Locations</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['total_locations']); ?></p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <span class="text-blue-600 text-sm font-medium">
                            <i class="fas fa-building"></i> All Warehouses
                        </span>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-boxes text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">With Inventory</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['locations_with_inventory']); ?></p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <span class="text-green-600 text-sm font-medium">
                            <i class="fas fa-check-circle"></i> Active Storage
                        </span>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                            <i class="fas fa-exclamation-triangle text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Empty Locations</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['empty_locations']); ?></p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <span class="text-yellow-600 text-sm font-medium">
                            <i class="fas fa-warehouse"></i> No Inventory
                        </span>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <i class="fas fa-cubes text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Inventory</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['total_inventory']); ?></p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <span class="text-purple-600 text-sm font-medium">
                            <i class="fas fa-layer-group"></i> All Items
                        </span>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <form method="GET" action="">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Search Locations</label>
                            <div class="relative">
                                <input type="text" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                       placeholder="Search by name, address, city..." 
                                       class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Country</label>
                            <select name="country" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="">All Countries</option>
                                <?php foreach ($filterOptions['countries'] as $country): ?>
                                    <option value="<?php echo htmlspecialchars($country); ?>" 
                                            <?php echo $filters['country'] === $country ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($country); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">State</label>
                            <select name="state" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="">All States</option>
                                <?php foreach ($filterOptions['states'] as $state): ?>
                                    <option value="<?php echo htmlspecialchars($state); ?>" 
                                            <?php echo $filters['state'] === $state ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($state); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="flex items-end space-x-2">
                            <button type="submit" class="flex-1 bg-primary text-white px-4 py-2 rounded-lg hover:bg-secondary transition-colors duration-200">
                                <i class="fas fa-filter mr-2"></i>Filter
                            </button>
                            <a href="?" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-200">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Locations Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">Warehouse Locations</h3>
                    <div class="flex items-center space-x-2">
                        <div class="relative">
                            <select onchange="handleBulkAction(this.value)" class="pr-8 pl-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="">Bulk Actions</option>
                                <option value="delete">Delete Selected</option>
                                <option value="export">Export Selected</option>
                                <option value="assign_materials">Assign Materials</option>
                            </select>
                        </div>
                        <span class="text-sm text-gray-500"><?php echo count($locations); ?> locations</span>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left">
                                    <input type="checkbox" id="select-all" class="rounded border-gray-300">
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Address</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Inventory</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="locations-table-body">
                            <?php if (empty($locations)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-map-marker-alt text-4xl text-gray-300 mb-2"></i>
                                        <p class="text-lg font-medium">No locations found</p>
                                        <p class="text-sm">Add your first warehouse location to get started.</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($locations as $location): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <input type="checkbox" class="rounded border-gray-300 location-checkbox" data-id="<?php echo $location['id']; ?>">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="h-10 w-10 rounded-lg bg-gradient-to-r from-blue-400 to-blue-600 flex items-center justify-center text-white font-semibold">
                                                <i class="fas fa-warehouse"></i>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($location['name']); ?></div>
                                                <div class="text-sm text-gray-500">ID: <?php echo $location['id']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">
                                            <?php echo htmlspecialchars($location['address'] ?? 'No address'); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php 
                                            $addressParts = array_filter([
                                                $location['city'], 
                                                $location['state'], 
                                                $location['zip_code']
                                            ]);
                                            echo htmlspecialchars(implode(', ', $addressParts));
                                            ?>
                                        </div>
                                        <div class="text-xs text-gray-400">
                                            <?php echo htmlspecialchars($location['country'] ?? ''); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-col">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo number_format($location['total_inventory']); ?> items
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo $location['unique_materials']; ?> materials
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($location['total_inventory'] > 0): ?>
                                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                <i class="fas fa-check-circle mr-1"></i>
                                                Active
                                            </span>
                                        <?php else: ?>
                                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                <i class="fas fa-circle mr-1"></i>
                                                Empty
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button onclick="viewLocation(<?php echo $location['id']; ?>)" class="text-blue-600 hover:text-blue-900" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button onclick="editLocation(<?php echo $location['id']; ?>)" class="text-green-600 hover:text-green-900" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="viewInventory(<?php echo $location['id']; ?>)" class="text-purple-600 hover:text-purple-900" title="View Inventory">
                                                <i class="fas fa-boxes"></i>
                                            </button>
                                            <?php if ($location['total_inventory'] == 0 && $location['borrowing_requests'] == 0): ?>
                                            <button onclick="deleteLocation(<?php echo $location['id']; ?>)" class="text-red-600 hover:text-red-900" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php else: ?>
                                            <button onclick="showCannotDelete()" class="text-gray-400 cursor-not-allowed" title="Cannot delete - has inventory or requests">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200">
                    <div class="flex-1 flex justify-between sm:hidden">
                        <a href="#" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Previous
                        </a>
                        <a href="#" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Next
                        </a>
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Showing <span class="font-medium">1</span> to <span class="font-medium"><?php echo count($locations); ?></span> of 
                                <span class="font-medium"><?php echo $stats['total_locations']; ?></span> results
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                <a href="#" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                                <a href="#" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>
                                <a href="#" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Location Modal -->
    <div id="add-location-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('add-location-modal')"></div>
        <div class="modal-content max-w-2xl">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Add New Location</h3>
                <button onclick="closeModal('add-location-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="add-location-form">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Location Name*</label>
                        <input type="text" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="Enter location name">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                        <textarea name="address" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="Enter street address..."></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">City</label>
                        <input type="text" name="city" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="Enter city">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">State</label>
                        <input type="text" name="state" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="Enter state">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">ZIP Code</label>
                        <input type="text" name="zip_code" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="Enter ZIP code">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Country</label>
                        <input type="text" name="country" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="Enter country" value="USA">
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('add-location-modal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Location</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Location Modal -->
    <div id="edit-location-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('edit-location-modal')"></div>
        <div class="modal-content max-w-2xl">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Edit Location</h3>
                <button onclick="closeModal('edit-location-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="edit-location-form">
                <input type="hidden" name="id" id="edit-location-id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Location Name*</label>
                        <input type="text" name="name" id="edit-location-name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                        <textarea name="address" id="edit-location-address" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">City</label>
                        <input type="text" name="city" id="edit-location-city" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">State</label>
                        <input type="text" name="state" id="edit-location-state" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">ZIP Code</label>
                        <input type="text" name="zip_code" id="edit-location-zip" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Country</label>
                        <input type="text" name="country" id="edit-location-country" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('edit-location-modal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Location</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Import Modal -->
    <div id="bulk-import-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('bulk-import-modal')"></div>
        <div class="modal-content max-w-lg">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Import Locations</h3>
                <button onclick="closeModal('bulk-import-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="text-center">
                <div class="mb-4">
                    <i class="fas fa-upload text-4xl text-gray-300"></i>
                </div>
                <p class="text-gray-600 mb-4">Upload a CSV file with location data</p>
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 mb-4">
                    <input type="file" id="import-file" accept=".csv" class="hidden">
                    <label for="import-file" class="cursor-pointer">
                        <i class="fas fa-cloud-upload-alt text-2xl text-gray-400 mb-2 block"></i>
                        <span class="text-gray-600">Click to select file or drag and drop</span>
                    </label>
                </div>
                <div class="flex justify-center space-x-3">
                    <button onclick="downloadTemplate()" class="btn btn-outline">
                        <i class="fas fa-download mr-2"></i>Download Template
                    </button>
                    <button onclick="importLocations()" class="btn btn-primary">
                        <i class="fas fa-upload mr-2"></i>Import
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/dashboard.js"></script>
    <script>
        // Initialize select all functionality
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.location-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Global variables
        let editingLocationId = null;

        // Location management functions
        async function viewLocation(id) {
            try {
                const response = await fetch(`../api/locations.php?action=get&id=${id}`);
                const result = await response.json();
                
                if (result.success) {
                    // Create a detailed view modal
                    showLocationDetails(result.data);
                } else {
                    showNotification(result.error || 'Failed to load location details', 'error');
                }
            } catch (error) {
                console.error('Error viewing location:', error);
                showNotification('Failed to load location details', 'error');
            }
        }

        function showLocationDetails(location) {
            const modalHtml = `
                <div id="location-details-modal" class="modal">
                    <div class="modal-overlay" onclick="closeModal('location-details-modal')"></div>
                    <div class="modal-content max-w-4xl">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold text-gray-800">Location Details: ${location.name}</h3>
                            <button onclick="closeModal('location-details-modal')" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h4 class="text-md font-semibold text-gray-700 mb-3">Location Information</h4>
                                <div class="space-y-2">
                                    <p><strong>Name:</strong> ${location.name}</p>
                                    <p><strong>Address:</strong> ${location.address || 'Not specified'}</p>
                                    <p><strong>City:</strong> ${location.city || 'Not specified'}</p>
                                    <p><strong>State:</strong> ${location.state || 'Not specified'}</p>
                                    <p><strong>ZIP Code:</strong> ${location.zip_code || 'Not specified'}</p>
                                    <p><strong>Country:</strong> ${location.country || 'Not specified'}</p>
                                </div>
                            </div>
                            
                            <div>
                                <h4 class="text-md font-semibold text-gray-700 mb-3">Inventory Summary</h4>
                                <div class="space-y-2">
                                    <p><strong>Total Items:</strong> ${parseInt(location.total_inventory).toLocaleString()}</p>
                                    <p><strong>Unique Materials:</strong> ${location.unique_materials}</p>
                                    <p><strong>Materials with Stock:</strong> ${location.materials_with_stock}</p>
                                    <p><strong>Borrowing Requests:</strong> ${location.borrowing_requests}</p>
                                    <p><strong>Transactions:</strong> ${location.transactions}</p>
                                </div>
                            </div>
                        </div>
                        
                        ${location.recent_inventory && location.recent_inventory.length > 0 ? `
                        <div class="mt-6">
                            <h4 class="text-md font-semibold text-gray-700 mb-3">Recent Inventory</h4>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Material</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Quantity</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Last Updated</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        ${location.recent_inventory.map(item => `
                                            <tr>
                                                <td class="px-4 py-2 text-sm text-gray-900">${item.name}</td>
                                                <td class="px-4 py-2 text-sm text-gray-900">${item.quantity} ${item.unit || ''}</td>
                                                <td class="px-4 py-2 text-sm text-gray-500">${new Date(item.last_updated).toLocaleDateString()}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        ` : ''}
                        
                        <div class="flex justify-end space-x-3 mt-6">
                            <button onclick="closeModal('location-details-modal')" class="btn btn-secondary">Close</button>
                            <button onclick="editLocation(${location.id}); closeModal('location-details-modal')" class="btn btn-primary">Edit Location</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
        }

        async function editLocation(id) {
            try {
                const response = await fetch(`../api/locations.php?action=get&id=${id}`);
                const result = await response.json();
                
                if (result.success) {
                    const location = result.data;
                    editingLocationId = id;
                    
                    // Populate the edit form
                    document.getElementById('edit-location-name').value = location.name;
                    document.getElementById('edit-location-address').value = location.address || '';
                    document.getElementById('edit-location-city').value = location.city || '';
                    document.getElementById('edit-location-state').value = location.state || '';
                    document.getElementById('edit-location-zip').value = location.zip_code || '';
                    document.getElementById('edit-location-country').value = location.country || '';
                    
                    // Show the modal
                    openModal('edit-location-modal');
                } else {
                    showNotification(result.error || 'Failed to load location details', 'error');
                }
            } catch (error) {
                console.error('Error loading location for edit:', error);
                showNotification('Failed to load location details', 'error');
            }
        }

        async function viewInventory(id) {
            // Redirect to inventory page with location filter
            window.location.href = `../inventory/materials.php?location=${id}`;
        }

        async function deleteLocation(id) {
            if (!confirm('Are you sure you want to delete this location? This action cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch('../api/locations.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete&id=${id}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Location deleted successfully', 'success');
                    // Reload the page to update the list
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification(result.error || 'Failed to delete location', 'error');
                }
            } catch (error) {
                console.error('Error deleting location:', error);
                showNotification('Failed to delete location', 'error');
            }
        }

        function showCannotDelete() {
            showNotification('Cannot delete location that has inventory or borrowing requests. Please move all items first.', 'warning');
        }

        async function handleBulkAction(action) {
            if (!action) return;
            
            const selectedItems = document.querySelectorAll('.location-checkbox:checked');
            if (selectedItems.length === 0) {
                showNotification('Please select at least one location', 'warning');
                return;
            }

            const selectedIds = Array.from(selectedItems).map(item => item.dataset.id);

            switch (action) {
                case 'delete':
                    if (confirm(`Are you sure you want to delete ${selectedItems.length} locations?`)) {
                        await bulkDeleteLocations(selectedIds);
                    }
                    break;
                case 'export':
                    await exportLocations(selectedIds);
                    break;
                case 'assign_materials':
                    showNotification('Assign materials feature coming soon!', 'info');
                    break;
            }
            
            // Reset the select dropdown
            document.querySelector('select').value = '';
        }

        async function bulkDeleteLocations(locationIds) {
            try {
                const response = await fetch('../api/locations.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'bulk_delete',
                        location_ids: locationIds
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification(`${result.deleted_count} locations deleted successfully`, 'success');
                    // Reload the page to update the list
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification(result.error || 'Failed to delete locations', 'error');
                }
            } catch (error) {
                console.error('Error bulk deleting locations:', error);
                showNotification('Failed to delete locations', 'error');
            }
        }

        async function exportLocations(locationIds = null) {
            const idsParam = locationIds ? `&location_ids=${locationIds.join(',')}` : '';
            const url = `../api/locations.php?action=export&format=csv${idsParam}`;
            
            try {
                const response = await fetch(url);
                if (response.ok) {
                    const blob = await response.blob();
                    const downloadUrl = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = downloadUrl;
                    a.download = `locations_export_${new Date().toISOString().slice(0, 10)}.csv`;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(downloadUrl);
                    document.body.removeChild(a);
                    
                    showNotification('Locations exported successfully', 'success');
                } else {
                    showNotification('Failed to export locations', 'error');
                }
            } catch (error) {
                console.error('Error exporting locations:', error);
                showNotification('Failed to export locations', 'error');
            }
        }

        function downloadTemplate() {
            const csvContent = 'name,address,city,state,zip_code,country\n' +
                              'Main Warehouse,123 Industrial Blvd,Industrial City,State,12345,USA\n' +
                              'Distribution Center,456 Commerce Ave,Commerce City,State,67890,USA';

            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'locations_template.csv';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);

            showNotification('Template downloaded successfully!', 'success');
        }

        async function importLocations() {
            const fileInput = document.getElementById('import-file');
            if (!fileInput.files.length) {
                showNotification('Please select a file first', 'warning');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'import');
            formData.append('file', fileInput.files[0]);

            try {
                const response = await fetch('../api/locations.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification(`${result.imported_count} locations imported successfully`, 'success');
                    if (result.errors && result.errors.length > 0) {
                        console.warn('Import errors:', result.errors);
                        showNotification(`Import completed with ${result.errors.length} errors. Check console for details.`, 'warning');
                    }
                    closeModal('bulk-import-modal');
                    // Reload the page to show new locations
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification(result.error || 'Failed to import locations', 'error');
                }
            } catch (error) {
                console.error('Error importing locations:', error);
                showNotification('Failed to import locations', 'error');
            }
        }

        // Form submission handlers
        document.getElementById('add-location-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const locationData = {
                action: 'create',
                name: formData.get('name'),
                address: formData.get('address'),
                city: formData.get('city'),
                state: formData.get('state'),
                zip_code: formData.get('zip_code'),
                country: formData.get('country')
            };

            try {
                const response = await fetch('../api/locations.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(locationData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Location created successfully', 'success');
                    closeModal('add-location-modal');
                    this.reset();
                    // Reload the page to show the new location
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification(result.error || 'Failed to create location', 'error');
                }
            } catch (error) {
                console.error('Error creating location:', error);
                showNotification('Failed to create location', 'error');
            }
        });

        document.getElementById('edit-location-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (!editingLocationId) {
                showNotification('No location selected for editing', 'error');
                return;
            }

            const formData = new FormData(this);
            const locationData = {
                action: 'update',
                id: editingLocationId,
                name: formData.get('name'),
                address: formData.get('address'),
                city: formData.get('city'),
                state: formData.get('state'),
                zip_code: formData.get('zip_code'),
                country: formData.get('country')
            };

            try {
                const response = await fetch('../api/locations.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(locationData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Location updated successfully', 'success');
                    closeModal('edit-location-modal');
                    editingLocationId = null;
                    // Reload the page to show the updated location
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification(result.error || 'Failed to update location', 'error');
                }
            } catch (error) {
                console.error('Error updating location:', error);
                showNotification('Failed to update location', 'error');
            }
        });

        // Notification helper
        function showNotification(message, type = 'info') {
            const colors = {
                'success': 'bg-green-500 text-white',
                'error': 'bg-red-500 text-white',
                'warning': 'bg-yellow-500 text-white',
                'info': 'bg-blue-500 text-white'
            };
            
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 p-4 rounded-md shadow-lg z-50 ${colors[type]}`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
    </script>
</body>
</html>
