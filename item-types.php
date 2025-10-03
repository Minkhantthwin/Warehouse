<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Check remember me token
checkRememberMe();

// Require login
requireLogin();

// Check permission for borrowing management
if (!hasPermission('borrowing_management')) {
    header('Location: index.php');
    exit();
}

$currentAdmin = getLoggedInAdmin();

// Define quick actions for this page
$quickActions = [
    [
        'text' => 'Add Item Type',
        'icon' => 'fas fa-plus',
        'class' => 'btn-primary',
        'onclick' => 'openModal(\'add-type-modal\')'
    ],
    [
        'text' => 'Export Data',
        'icon' => 'fas fa-download',
        'class' => 'btn-secondary',
        'onclick' => 'exportItemTypes()'
    ]
];

// Get item type statistics
function getItemTypeStats($pdo) {
    $stats = [];
    
    // Total types
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM Borrowing_Item_Types");
    $stats['total_types'] = $stmt->fetch()['total'] ?? 0;
    
    // Types with borrowing items
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT item_type_id) as used 
        FROM Borrowing_Items 
        WHERE item_type_id IS NOT NULL
    ");
    $stats['used_types'] = $stmt->fetch()['used'] ?? 0;
    
    // Total estimated value
    $stmt = $pdo->query("SELECT SUM(estimated_value) as total_value FROM Borrowing_Item_Types");
    $stats['total_value'] = $stmt->fetch()['total_value'] ?? 0;
    
    // Most borrowed type
    $stmt = $pdo->query("
        SELECT bt.name, COUNT(bi.id) as borrow_count 
        FROM Borrowing_Item_Types bt
        LEFT JOIN Borrowing_Items bi ON bt.id = bi.item_type_id
        GROUP BY bt.id
        ORDER BY borrow_count DESC
        LIMIT 1
    ");
    $result = $stmt->fetch();
    $stats['most_borrowed'] = $result ? $result['name'] : 'N/A';
    
    return $stats;
}

// Get item types with pagination and filters
function getItemTypes($pdo, $page = 1, $limit = 10, $filters = []) {
    $offset = ($page - 1) * $limit;
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if (!empty($filters['search'])) {
        $whereClause .= " AND (bt.name LIKE :search OR bt.description LIKE :search OR bt.unit LIKE :search)";
        $params['search'] = "%" . $filters['search'] . "%";
    }
    
    if (!empty($filters['min_value'])) {
        $whereClause .= " AND bt.estimated_value >= :min_value";
        $params['min_value'] = $filters['min_value'];
    }
    
    if (!empty($filters['max_value'])) {
        $whereClause .= " AND bt.estimated_value <= :max_value";
        $params['max_value'] = $filters['max_value'];
    }
    
    $query = "SELECT bt.*, 
                     COUNT(DISTINCT bi.id) as borrow_count,
                     COUNT(DISTINCT bi.borrowing_request_id) as request_count
              FROM Borrowing_Item_Types bt 
              LEFT JOIN Borrowing_Items bi ON bt.id = bi.item_type_id 
              $whereClause 
              GROUP BY bt.id
              ORDER BY bt.name ASC 
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

// Get total count for pagination
function getTotalItemTypes($pdo, $filters = []) {
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if (!empty($filters['search'])) {
        $whereClause .= " AND (name LIKE :search OR description LIKE :search OR unit LIKE :search)";
        $params['search'] = "%" . $filters['search'] . "%";
    }
    
    if (!empty($filters['min_value'])) {
        $whereClause .= " AND estimated_value >= :min_value";
        $params['min_value'] = $filters['min_value'];
    }
    
    if (!empty($filters['max_value'])) {
        $whereClause .= " AND estimated_value <= :max_value";
        $params['max_value'] = $filters['max_value'];
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM Borrowing_Item_Types $whereClause");
    
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->execute();
    
    return $stmt->fetch()['total'] ?? 0;
}

// Get current filters and pagination
$filters = [
    'search' => $_GET['search'] ?? '',
    'min_value' => $_GET['min_value'] ?? '',
    'max_value' => $_GET['max_value'] ?? ''
];

$page = (int)($_GET['page'] ?? 1);
$limit = 10;

// Get data
$stats = getItemTypeStats($pdo);
$itemTypes = getItemTypes($pdo, $page, $limit, $filters);
$totalTypes = getTotalItemTypes($pdo, $filters);
$totalPages = ceil($totalTypes / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Item Types - Warehouse Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3B82F6',
                        secondary: '#1E40AF',
                        dark: '#1F2937',
                        light: '#F3F4F6'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-gray-100">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="lg:ml-64">
        <?php include 'includes/navbar.php'; ?>

        <!-- Main Content Area -->
        <main class="p-6">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100">
                            <i class="fas fa-boxes text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Total Types</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['total_types']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">In Use</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['used_types']); ?></p>
                        </div>
                    </div>
                </div>


                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100">
                            <i class="fas fa-star text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Most Borrowed</p>
                            <p class="text-lg font-semibold text-gray-900 truncate"><?php echo htmlspecialchars($stats['most_borrowed']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <form method="GET" action="">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                   placeholder="Search by name, description, or unit..." 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Min Value ($)</label>
                            <input type="number" name="min_value" value="<?php echo htmlspecialchars($filters['min_value']); ?>" 
                                   step="0.01" min="0"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Max Value ($)</label>
                            <input type="number" name="max_value" value="<?php echo htmlspecialchars($filters['max_value']); ?>" 
                                   step="0.01" min="0"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div class="flex justify-between items-center mt-4">
                        <div class="flex space-x-3">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                <i class="fas fa-search mr-2"></i>Search
                            </button>
                            <a href="item-types.php" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50">
                                <i class="fas fa-times mr-2"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Item Types Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800">Item Types</h3>
                    
                    <div class="flex items-center space-x-3">
                        <select onchange="handleBulkAction(this.value)" class="px-3 py-2 border border-gray-300 rounded-md">
                            <option value="">Bulk Actions</option>
                            <option value="delete">Delete Selected</option>
                            <option value="export">Export Selected</option>
                        </select>
                        
                        <button onclick="openModal('add-type-modal')" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>Add Item Type
                        </button>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left">
                                    <input type="checkbox" id="select-all" class="rounded border-gray-300">
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Est. Value</th>

                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($itemTypes as $type): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <input type="checkbox" class="type-checkbox rounded border-gray-300" data-id="<?php echo $type['id']; ?>">
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($type['name']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-500 max-w-xs truncate">
                                            <?php echo htmlspecialchars($type['description'] ?? 'No description'); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">
                                            <?php echo htmlspecialchars($type['unit'] ?? 'N/A'); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">
                                            $<?php echo number_format($type['estimated_value'] ?? 0, 2); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button onclick="viewItemType(<?php echo $type['id']; ?>)" 
                                                    class="text-blue-600 hover:text-blue-900" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button onclick="editItemType(<?php echo $type['id']; ?>)" 
                                                    class="text-indigo-600 hover:text-indigo-900" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="deleteItemType(<?php echo $type['id']; ?>)" 
                                                    class="text-red-600 hover:text-red-900" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="bg-white px-6 py-4 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Showing <?php echo (($page - 1) * $limit) + 1; ?> to <?php echo min($page * $limit, $totalTypes); ?> of <?php echo $totalTypes; ?> types
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($filters); ?>" 
                                       class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <a href="?page=<?php echo $i; ?>&<?php echo http_build_query($filters); ?>" 
                                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?php echo $i === $page ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:bg-gray-50'; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($filters); ?>" 
                                       class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Item Type Modal -->
    <div id="add-type-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('add-type-modal')"></div>
        <div class="modal-content max-w-2xl">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Add New Item Type</h3>
                <button onclick="closeModal('add-type-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="add-type-form">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Name *</label>
                        <input type="text" name="name" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               placeholder="e.g., Construction Tools">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Unit</label>
                        <input type="text" name="unit" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               placeholder="e.g., pieces, sets, boxes">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="description" rows="3" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                              placeholder="Describe this item type..."></textarea>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Estimated Value ($)</label>
                    <input type="number" name="estimated_value" step="0.01" min="0" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                           placeholder="0.00">
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('add-type-modal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Add Item Type
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Item Type Modal -->
    <div id="edit-type-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('edit-type-modal')"></div>
        <div class="modal-content max-w-2xl">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Edit Item Type</h3>
                <button onclick="closeModal('edit-type-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="edit-type-form">
                <input type="hidden" id="edit-type-id" name="id">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Name *</label>
                        <input type="text" name="name" id="edit-name" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Unit</label>
                        <input type="text" name="unit" id="edit-unit" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="description" id="edit-description" rows="3" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Estimated Value ($)</label>
                    <input type="number" name="estimated_value" id="edit-estimated-value" step="0.01" min="0" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('edit-type-modal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Update Item Type
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Item Type Modal -->
    <div id="view-type-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('view-type-modal')"></div>
        <div class="modal-content max-w-3xl">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Item Type Details</h3>
                <button onclick="closeModal('view-type-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div id="type-details-content">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <script src="js/dashboard.js"></script>
    <script>
        // Initialize select all functionality
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.type-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Item Type management functions
        async function viewItemType(id) {
            try {
                const response = await fetch(`api/item-types.php?action=get&id=${id}`);
                const result = await response.json();
                
                if (result.success) {
                    showTypeDetails(result.data);
                } else {
                    showNotification('Failed to load item type details', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            }
        }

        function showTypeDetails(type) {
            const content = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <h4 class="text-md font-semibold text-gray-700">Basic Information</h4>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="mb-3">
                                <span class="text-sm font-medium text-gray-500">Name:</span>
                                <p class="text-sm text-gray-900 mt-1 font-semibold">${type.name}</p>
                            </div>
                            <div class="mb-3">
                                <span class="text-sm font-medium text-gray-500">Unit:</span>
                                <p class="text-sm text-gray-900">${type.unit || 'Not specified'}</p>
                            </div>
                            <div class="mb-3">
                                <span class="text-sm font-medium text-gray-500">Estimated Value:</span>
                                <p class="text-sm text-gray-900">$${parseFloat(type.estimated_value || 0).toFixed(2)}</p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-500">Created:</span>
                                <p class="text-sm text-gray-900">${new Date(type.created_at).toLocaleString()}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <h4 class="text-md font-semibold text-gray-700">Usage Statistics</h4>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="text-center">
                                    <div class="text-3xl font-bold text-blue-600">${type.borrow_count || 0}</div>
                                    <div class="text-sm text-gray-500">Total Borrowings</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-3xl font-bold text-green-600">${type.request_count || 0}</div>
                                    <div class="text-sm text-gray-500">Total Requests</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                ${type.description ? `
                <div class="mt-6">
                    <h4 class="text-md font-semibold text-gray-700 mb-3">Description</h4>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-900">${type.description}</p>
                    </div>
                </div>
                ` : ''}
            `;
            
            document.getElementById('type-details-content').innerHTML = content;
            openModal('view-type-modal');
        }

        async function editItemType(id) {
            try {
                const response = await fetch(`api/item-types.php?action=get&id=${id}`);
                const result = await response.json();
                
                if (result.success) {
                    populateEditForm(result.data);
                    openModal('edit-type-modal');
                } else {
                    showNotification('Failed to load item type details', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            }
        }

        function populateEditForm(type) {
            document.getElementById('edit-type-id').value = type.id;
            document.getElementById('edit-name').value = type.name;
            document.getElementById('edit-unit').value = type.unit || '';
            document.getElementById('edit-description').value = type.description || '';
            document.getElementById('edit-estimated-value').value = type.estimated_value || '';
        }

        async function deleteItemType(id) {
            if (!confirm('Are you sure you want to delete this item type? This action cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch('api/item-types.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'delete',
                        id: id
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    showNotification('Item type deleted successfully!', 'success');
                    location.reload();
                } else {
                    showNotification(result.error || 'Failed to delete item type', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            }
        }

        async function handleBulkAction(action) {
            if (!action) return;
            
            const selectedTypes = document.querySelectorAll('.type-checkbox:checked');
            if (selectedTypes.length === 0) {
                showNotification('Please select item types first', 'warning');
                return;
            }

            const selectedIds = Array.from(selectedTypes).map(type => type.dataset.id);

            switch (action) {
                case 'delete':
                    await bulkDeleteTypes(selectedIds);
                    break;
                case 'export':
                    await exportItemTypes(selectedIds);
                    break;
            }
            
            // Reset the select dropdown
            document.querySelector('select').value = '';
        }

        async function bulkDeleteTypes(typeIds) {
            if (!confirm(`Are you sure you want to delete ${typeIds.length} item types? This action cannot be undone.`)) {
                return;
            }

            try {
                const response = await fetch('api/item-types.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'bulk_delete',
                        ids: typeIds
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    showNotification(result.message, 'success');
                    location.reload();
                } else {
                    showNotification(result.message || 'Failed to delete item types', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            }
        }

        async function exportItemTypes(typeIds = null) {
            const idsParam = typeIds ? `&type_ids=${typeIds.join(',')}` : '';
            const url = `api/item-types.php?action=export&format=csv${idsParam}`;
            
            try {
                window.open(url, '_blank');
                showNotification('Export started successfully!', 'success');
            } catch (error) {
                console.error('Error:', error);
                showNotification('Failed to export data', 'error');
            }
        }

        // Form submission handlers
        document.getElementById('add-type-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const typeData = {
                action: 'create',
                name: formData.get('name'),
                description: formData.get('description'),
                unit: formData.get('unit'),
                estimated_value: formData.get('estimated_value')
            };

            try {
                const response = await fetch('api/item-types.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(typeData)
                });

                const result = await response.json();
                
                if (result.success) {
                    showNotification('Item type created successfully!', 'success');
                    closeModal('add-type-modal');
                    location.reload();
                } else {
                    showNotification(result.error || 'Failed to create item type', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            }
        });

        document.getElementById('edit-type-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const typeData = {
                action: 'update',
                id: formData.get('id'),
                name: formData.get('name'),
                description: formData.get('description'),
                unit: formData.get('unit'),
                estimated_value: formData.get('estimated_value')
            };

            try {
                const response = await fetch('api/item-types.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(typeData)
                });

                const result = await response.json();
                
                if (result.success) {
                    showNotification('Item type updated successfully!', 'success');
                    closeModal('edit-type-modal');
                    location.reload();
                } else {
                    showNotification(result.error || 'Failed to update item type', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            }
        });

        // Notification helper
        function showNotification(message, type = 'info') {
            const colors = {
                success: 'bg-green-500 text-white',
                error: 'bg-red-500 text-white',
                warning: 'bg-yellow-500 text-black',
                info: 'bg-blue-500 text-white'
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
