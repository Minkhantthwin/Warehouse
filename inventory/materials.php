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
        'text' => 'Add Material',
        'icon' => 'fas fa-plus',
        'class' => 'btn-primary',
        'onclick' => 'openModal(\'add-material-modal\')'
    ],
    [
        'text' => 'Import Materials',
        'icon' => 'fas fa-upload',
        'class' => 'btn-secondary',
        'onclick' => 'openModal(\'bulk-import-modal\')'
    ],
    [
        'text' => 'Export',
        'icon' => 'fas fa-download',
        'class' => 'btn-outline',
        'onclick' => 'exportMaterials()'
    ]
];

// Get material statistics
function getMaterialStats($pdo) {
    $stats = [];
    
    // Total materials
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM Material");
    $stats['total_materials'] = $stmt->fetch()['total'] ?? 0;
    
    // Active materials (assuming we have a status field, or count all)
    $stats['active_materials'] = $stats['total_materials']; // For now, all materials are considered active
    
    // Categories count
    $stmt = $pdo->query("SELECT COUNT(*) as categories FROM Material_Categories");
    $stats['categories_count'] = $stmt->fetch()['categories'] ?? 0;
    
    // Materials added this month
    $stmt = $pdo->query("SELECT COUNT(*) as new_materials FROM Material WHERE id > 0"); // Placeholder since we don't have created_at
    $stats['new_this_month'] = rand(5, 15); // Mock data for now
    
    return $stats;
}

// Get materials with pagination and filters
function getMaterials($pdo, $page = 1, $limit = 10, $filters = []) {
    $offset = ($page - 1) * $limit;
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if (!empty($filters['search'])) {
        $whereClause .= " AND (m.name LIKE :search OR m.description LIKE :search OR m.unit LIKE :search)";
        $params['search'] = "%" . $filters['search'] . "%";
    }
    
    if (!empty($filters['category'])) {
        $whereClause .= " AND m.category_id = :category";
        $params['category'] = $filters['category'];
    }
    
    if (!empty($filters['price_range'])) {
        switch ($filters['price_range']) {
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
    
    $query = "SELECT m.*, mc.name as category_name,
                     COALESCE(SUM(i.quantity), 0) as stock_quantity
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
    
    return $stmt->fetchAll();
}

// Get material categories for filters/dropdowns
function getMaterialCategories($pdo) {
    $stmt = $pdo->query("SELECT * FROM Material_Categories ORDER BY name");
    return $stmt->fetchAll();
}

// Get current filters and pagination
$filters = [
    'search' => $_GET['search'] ?? '',
    'category' => $_GET['category'] ?? '',
    'price_range' => $_GET['price_range'] ?? ''
];

$page = (int)($_GET['page'] ?? 1);
$limit = 10;

// Get data
$stats = getMaterialStats($pdo);
$materials = getMaterials($pdo, $page, $limit, $filters);
$categories = getMaterialCategories($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Material Management - Warehouse Admin</title>
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
                            <i class="fas fa-cube text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Materials</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['total_materials']); ?></p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <span class="text-green-600 text-sm font-medium">
                            <i class="fas fa-arrow-up"></i> Active
                        </span>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-check-circle text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Available</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['active_materials']); ?></p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <span class="text-blue-600 text-sm font-medium">
                            <i class="fas fa-info-circle"></i> In Stock
                        </span>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <i class="fas fa-tags text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Categories</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['categories_count']); ?></p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <span class="text-purple-600 text-sm font-medium">
                            <i class="fas fa-layer-group"></i> Active
                        </span>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                            <i class="fas fa-plus-circle text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Added This Month</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['new_this_month']); ?></p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <span class="text-orange-600 text-sm font-medium">
                            <i class="fas fa-calendar"></i> Recent
                        </span>
                    </div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <form method="GET" action="">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Search Materials</label>
                            <div class="relative">
                                <input type="text" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                       placeholder="Search by name, description, or unit..." 
                                       class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                            <select name="category" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo ($filters['category'] == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Price Range</label>
                            <select name="price_range" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="">All Prices</option>
                                <option value="low" <?php echo ($filters['price_range'] == 'low') ? 'selected' : ''; ?>>Under $100</option>
                                <option value="medium" <?php echo ($filters['price_range'] == 'medium') ? 'selected' : ''; ?>>$100 - $500</option>
                                <option value="high" <?php echo ($filters['price_range'] == 'high') ? 'selected' : ''; ?>>Over $500</option>
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

            <!-- Materials Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">Materials Inventory</h3>
                    <div class="flex items-center space-x-2">
                        <div class="relative">
                            <select onchange="handleBulkAction(this.value)" class="pr-8 pl-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="">Bulk Actions</option>
                                <option value="delete">Delete Selected</option>
                                <option value="export">Export Selected</option>
                                <option value="update_category">Update Category</option>
                            </select>
                        </div>
                        <span class="text-sm text-gray-500"><?php echo number_format($stats['total_materials']); ?> materials</span>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left">
                                    <input type="checkbox" id="select-all" class="rounded border-gray-300">
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Material</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price/Unit</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="materials-table-body">
                            <?php if (empty($materials)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-cube text-4xl text-gray-300 mb-2"></i>
                                        <p class="text-lg font-medium">No materials found</p>
                                        <p class="text-sm">Add your first material to get started.</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($materials as $material): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <input type="checkbox" class="rounded border-gray-300 material-checkbox" data-id="<?php echo $material['id']; ?>">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="h-10 w-10 rounded-lg bg-gradient-to-r from-blue-400 to-blue-600 flex items-center justify-center text-white font-semibold">
                                                <i class="fas fa-cube"></i>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($material['name']); ?></div>
                                                <div class="text-sm text-gray-500 max-w-xs truncate" title="<?php echo htmlspecialchars($material['description']); ?>">
                                                    <?php echo htmlspecialchars($material['description'] ?? 'No description'); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($material['category_name']): ?>
                                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                <?php echo htmlspecialchars($material['category_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                Uncategorized
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($material['unit'] ?? 'N/A'); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            $<?php echo number_format($material['price_per_unit'] ?? 0, 2); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        // Use actual stock data if available, otherwise default values
                                        $stockQuantity = $material['stock_quantity'] ?? 0;
                                        if ($stockQuantity > 50) {
                                            $statusClass = 'bg-green-100 text-green-800';
                                            $statusText = 'In Stock';
                                            $statusIcon = 'fa-check-circle';
                                        } elseif ($stockQuantity > 20) {
                                            $statusClass = 'bg-yellow-100 text-yellow-800';
                                            $statusText = 'Low Stock';
                                            $statusIcon = 'fa-exclamation-triangle';
                                        } else {
                                            $statusClass = 'bg-red-100 text-red-800';
                                            $statusText = 'Out of Stock';
                                            $statusIcon = 'fa-times-circle';
                                        }
                                        ?>
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                            <i class="fas <?php echo $statusIcon; ?> mr-1"></i>
                                            <?php echo $statusText; ?> (<?php echo $stockQuantity; ?>)
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button onclick="viewMaterial(<?php echo $material['id']; ?>)" class="text-blue-600 hover:text-blue-900" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button onclick="editMaterial(<?php echo $material['id']; ?>)" class="text-green-600 hover:text-green-900" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="viewStock(<?php echo $material['id']; ?>)" class="text-purple-600 hover:text-purple-900" title="Stock Levels">
                                                <i class="fas fa-warehouse"></i>
                                            </button>
                                           
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
                                Showing <span class="font-medium">1</span> to <span class="font-medium"><?php echo count($materials); ?></span> of 
                                <span class="font-medium"><?php echo $stats['total_materials']; ?></span> results
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                <!-- Pagination links would go here -->
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

    <!-- Add Material Modal -->
    <div id="add-material-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('add-material-modal')"></div>
        <div class="modal-content max-w-2xl">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Add New Material</h3>
                <button onclick="closeModal('add-material-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="add-material-form">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Material Name*</label>
                        <input type="text" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="Enter material name">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                        <select name="category_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Unit*</label>
                        <select name="unit" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select Unit</option>
                            <option value="pieces">Pieces</option>
                            <option value="kg">Kilograms</option>
                            <option value="meters">Meters</option>
                            <option value="liters">Liters</option>
                            <option value="boxes">Boxes</option>
                            <option value="rolls">Rolls</option>
                            <option value="sheets">Sheets</option>
                            <option value="tons">Tons</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Price per Unit*</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">$</span>
                            <input type="number" name="price_per_unit" step="0.01" min="0" required class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="0.00">
                        </div>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="Enter material description..."></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('add-material-modal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Material</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Material Modal -->
    <div id="edit-material-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('edit-material-modal')"></div>
        <div class="modal-content max-w-2xl">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Edit Material</h3>
                <button onclick="closeModal('edit-material-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="edit-material-form">
                <input type="hidden" name="id" id="edit-material-id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Material Name*</label>
                        <input type="text" name="name" id="edit-material-name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                        <select name="category_id" id="edit-material-category" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Unit*</label>
                        <select name="unit" id="edit-material-unit" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select Unit</option>
                            <option value="pieces">Pieces</option>
                            <option value="kg">Kilograms</option>
                            <option value="meters">Meters</option>
                            <option value="liters">Liters</option>
                            <option value="boxes">Boxes</option>
                            <option value="rolls">Rolls</option>
                            <option value="sheets">Sheets</option>
                            <option value="tons">Tons</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Price per Unit*</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">$</span>
                            <input type="number" name="price_per_unit" id="edit-material-price" step="0.01" min="0" required class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="description" id="edit-material-description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('edit-material-modal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Material</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Import Modal -->
    <div id="bulk-import-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('bulk-import-modal')"></div>
        <div class="modal-content max-w-lg">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Import Materials</h3>
                <button onclick="closeModal('bulk-import-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="text-center">
                <div class="mb-4">
                    <i class="fas fa-upload text-4xl text-gray-300"></i>
                </div>
                <p class="text-gray-600 mb-4">Upload a CSV file with material data</p>
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
                    <button onclick="importMaterials()" class="btn btn-primary">
                        <i class="fas fa-upload mr-2"></i>Import
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/dashboard.js"></script>
    <script src="../js/materials.js"></script>
    <script>
        // Initialize select all functionality
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.material-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        function handleBulkAction(action) {
            if (!action) return;
            
            const selectedItems = document.querySelectorAll('.material-checkbox:checked');
            if (selectedItems.length === 0) {
                showNotification('Please select at least one material', 'warning');
                return;
            }

            const selectedIds = Array.from(selectedItems).map(item => item.dataset.id);

            switch (action) {
                case 'delete':
                    if (confirm(`Are you sure you want to delete ${selectedItems.length} materials?`)) {
                        bulkDeleteMaterials(selectedIds);
                    }
                    break;
                case 'export':
                    exportSelectedMaterials(selectedIds);
                    break;
                case 'update_category':
                    showNotification('Bulk category update feature coming soon!', 'info');
                    break;
            }
        }

        async function bulkDeleteMaterials(ids) {
            try {
                const response = await fetch('../api/materials.php?action=bulk_delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ ids: ids })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification(result.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.error || 'Failed to delete materials', 'error');
                }
            } catch (error) {
                console.error('Bulk delete error:', error);
                showNotification('Network error occurred', 'error');
            }
        }

        async function exportSelectedMaterials(ids) {
            try {
                const idsParam = ids.join(',');
                const url = `../api/materials.php?action=export&format=csv&ids=${idsParam}`;
                window.open(url, '_blank');
                showNotification('Export started!', 'success');
            } catch (error) {
                console.error('Export error:', error);
                showNotification('Failed to export materials', 'error');
            }
        }

        function exportMaterials() {
            try {
                const url = '../api/materials.php?action=export&format=csv';
                window.open(url, '_blank');
                showNotification('Export started!', 'success');
            } catch (error) {
                console.error('Export error:', error);
                showNotification('Failed to export materials', 'error');
            }
        }

        function downloadTemplate() {
            const csvContent = 'name,description,unit,price_per_unit,category_name\n' +
                              'Steel Rod 12mm,High-grade steel rod,pieces,25.50,Steel Products\n' +
                              'Safety Helmet,Industrial safety helmet,pieces,45.00,Safety Equipment';

            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'materials_template.csv';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);

            showNotification('Template downloaded successfully!', 'success');
        }

        async function importMaterials() {
            const fileInput = document.getElementById('import-file');
            const file = fileInput.files[0];

            if (!file) {
                showNotification('Please select a file first', 'warning');
                return;
            }

            const formData = new FormData();
            formData.append('file', file);

            try {
                const response = await fetch('../api/materials.php?action=import', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    let message = `Successfully imported ${result.imported} materials!`;
                    if (result.errors && result.errors.length > 0) {
                        message += ` (${result.errors.length} errors)`;
                    }
                    showNotification(message, 'success');
                    closeModal('bulk-import-modal');
                    fileInput.value = '';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(result.error || 'Import failed', 'error');
                }
            } catch (error) {
                console.error('Import error:', error);
                showNotification('Network error occurred during import', 'error');
            }
        }

        // Individual material actions
        async function viewMaterial(id) {
            try {
                const response = await fetch(`../api/materials.php?action=get&id=${id}`);
                const result = await response.json();
                
                if (result.success) {
                    showMaterialDetails(result.data);
                } else {
                    showNotification(result.error || 'Failed to load material details', 'error');
                }
            } catch (error) {
                console.error('View material error:', error);
                showNotification('Network error occurred', 'error');
            }
        }

        async function editMaterial(id) {
            try {
                const response = await fetch(`../api/materials.php?action=get&id=${id}`);
                const result = await response.json();
                
                if (result.success) {
                    populateEditForm(result.data);
                    openModal('edit-material-modal');
                } else {
                    showNotification(result.error || 'Failed to load material data', 'error');
                }
            } catch (error) {
                console.error('Edit material error:', error);
                showNotification('Network error occurred', 'error');
            }
        }

        async function viewStock(id) {
            try {
                const response = await fetch(`../api/materials.php?action=check_stock&id=${id}`);
                const result = await response.json();
                
                if (result.success) {
                    showStockDetails(result.data);
                } else {
                    showNotification(result.error || 'Failed to load stock information', 'error');
                }
            } catch (error) {
                console.error('View stock error:', error);
                showNotification('Network error occurred', 'error');
            }
        }

        async function deleteMaterial(id) {
            if (!confirm('Are you sure you want to delete this material? This action cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch('../api/materials.php?action=delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification(result.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.error || 'Failed to delete material', 'error');
                }
            } catch (error) {
                console.error('Delete material error:', error);
                showNotification('Network error occurred', 'error');
            }
        }

        function populateEditForm(material) {
            document.getElementById('edit-material-id').value = material.id;
            document.getElementById('edit-material-name').value = material.name || '';
            document.getElementById('edit-material-category').value = material.category_id || '';
            document.getElementById('edit-material-unit').value = material.unit || '';
            document.getElementById('edit-material-price').value = material.price_per_unit || '';
            document.getElementById('edit-material-description').value = material.description || '';
        }

        function showMaterialDetails(material) {
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-overlay" onclick="this.parentElement.remove()"></div>
                <div class="modal-content max-w-2xl">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-800">Material Details</h3>
                        <button onclick="this.closest('.modal').remove()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Name</label>
                            <p class="text-gray-900">${escapeHtml(material.name)}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Category</label>
                            <p class="text-gray-900">${escapeHtml(material.category_name || 'Uncategorized')}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Unit</label>
                            <p class="text-gray-900">${escapeHtml(material.unit)}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Price per Unit</label>
                            <p class="text-gray-900">$${parseFloat(material.price_per_unit).toFixed(2)}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Stock Quantity</label>
                            <p class="text-gray-900">${material.stock_quantity || 0} ${material.unit}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Status</label>
                            <p class="text-gray-900">${escapeHtml(material.status)}</p>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Description</label>
                            <p class="text-gray-900">${escapeHtml(material.description || 'No description')}</p>
                        </div>
                    </div>
                    
                    <div class="flex justify-end mt-6">
                        <button onclick="this.closest('.modal').remove()" class="btn btn-secondary">Close</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
        }

        function showStockDetails(stockLevels) {
            const modal = document.createElement('div');
            modal.className = 'modal';
            
            const stockRows = stockLevels.map(stock => `
                <tr>
                    <td class="px-4 py-2 border">${escapeHtml(stock.location_name || 'Unknown Location')}</td>
                    <td class="px-4 py-2 border">${stock.quantity}</td>
                    <td class="px-4 py-2 border">${new Date(stock.last_updated).toLocaleDateString()}</td>
                </tr>
            `).join('');
            
            modal.innerHTML = `
                <div class="modal-overlay" onclick="this.parentElement.remove()"></div>
                <div class="modal-content max-w-2xl">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-800">Stock Levels</h3>
                        <button onclick="this.closest('.modal').remove()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full border-collapse border border-gray-300">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-4 py-2 border text-left">Location</th>
                                    <th class="px-4 py-2 border text-left">Quantity</th>
                                    <th class="px-4 py-2 border text-left">Last Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${stockRows}
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="flex justify-end mt-6">
                        <button onclick="this.closest('.modal').remove()" class="btn btn-secondary">Close</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Form submission handlers
        document.getElementById('add-material-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            
            try {
                const response = await fetch('../api/materials.php?action=create', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Material added successfully!', 'success');
                    closeModal('add-material-modal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.error || 'Failed to add material', 'error');
                }
            } catch (error) {
                console.error('Add material error:', error);
                showNotification('Network error occurred', 'error');
            }
        });

        document.getElementById('edit-material-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            
            try {
                const response = await fetch('../api/materials.php?action=update', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Material updated successfully!', 'success');
                    closeModal('edit-material-modal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.error || 'Failed to update material', 'error');
                }
            } catch (error) {
                console.error('Update material error:', error);
                showNotification('Network error occurred', 'error');
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
