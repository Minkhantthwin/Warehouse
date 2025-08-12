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
        'text' => 'Add Category',
        'icon' => 'fas fa-plus',
        'class' => 'btn-primary',
        'onclick' => 'openModal(\'add-category-modal\')'
    ],
    [
        'text' => 'Import Categories',
        'icon' => 'fas fa-upload',
        'class' => 'btn-secondary',
        'onclick' => 'openModal(\'bulk-import-modal\')'
    ]
];

// Get category statistics
function getCategoryStats($pdo) {
    $stats = [];
    
    // Total categories
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM Material_Categories");
    $stats['total_categories'] = $stmt->fetch()['total'] ?? 0;
    
    // Active categories (all for now)
    $stats['active_categories'] = $stats['total_categories'];
    
    // Categories with materials
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT mc.id) as categories_with_materials 
        FROM Material_Categories mc 
        INNER JOIN Material m ON mc.id = m.category_id 
        WHERE m.status = 'active'
    ");
    $stats['categories_with_materials'] = $stmt->fetch()['categories_with_materials'] ?? 0;
    
    // Empty categories
    $stats['empty_categories'] = $stats['total_categories'] - $stats['categories_with_materials'];
    
    return $stats;
}

// Get categories with pagination and filters
function getCategories($pdo, $page = 1, $limit = 10, $filters = []) {
    $offset = ($page - 1) * $limit;
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if (!empty($filters['search'])) {
        $whereClause .= " AND (mc.name LIKE :search OR mc.description LIKE :search)";
        $params['search'] = "%" . $filters['search'] . "%";
    }
    
    $query = "SELECT mc.*, 
                     COUNT(m.id) as material_count,
                     COALESCE(SUM(CASE WHEN m.status = 'active' THEN 1 ELSE 0 END), 0) as active_materials
              FROM Material_Categories mc 
              LEFT JOIN Material m ON mc.id = m.category_id 
              $whereClause 
              GROUP BY mc.id, mc.name, mc.description
              ORDER BY mc.name ASC 
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

// Get current filters and pagination
$filters = [
    'search' => $_GET['search'] ?? ''
];

$page = (int)($_GET['page'] ?? 1);
$limit = 10;

// Get data
$stats = getCategoryStats($pdo);
$categories = getCategories($pdo, $page, $limit, $filters);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Material Categories - Warehouse Admin</title>
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
                            <i class="fas fa-tags text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Categories</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['total_categories']); ?></p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <span class="text-blue-600 text-sm font-medium">
                            <i class="fas fa-layer-group"></i> All Categories
                        </span>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-check-circle text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">With Materials</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['categories_with_materials']); ?></p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <span class="text-green-600 text-sm font-medium">
                            <i class="fas fa-cube"></i> Active
                        </span>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                            <i class="fas fa-inbox text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Empty Categories</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['empty_categories']); ?></p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <span class="text-yellow-600 text-sm font-medium">
                            <i class="fas fa-exclamation-triangle"></i> No Materials
                        </span>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <i class="fas fa-percent text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Usage Rate</p>
                            <p class="text-2xl font-semibold text-gray-900">
                                <?php 
                                $usageRate = $stats['total_categories'] > 0 ? 
                                    round(($stats['categories_with_materials'] / $stats['total_categories']) * 100) : 0;
                                echo $usageRate . '%';
                                ?>
                            </p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <span class="text-purple-600 text-sm font-medium">
                            <i class="fas fa-chart-line"></i> Utilization
                        </span>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <form method="GET" action="">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Search Categories</label>
                            <div class="relative">
                                <input type="text" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                       placeholder="Search by name or description..." 
                                       class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            </div>
                        </div>
                        
                        <div class="flex items-end space-x-2">
                            <button type="submit" class="flex-1 bg-primary text-white px-4 py-2 rounded-lg hover:bg-secondary transition-colors duration-200">
                                <i class="fas fa-filter mr-2"></i>Search
                            </button>
                            <a href="?" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-200">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Categories Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">Material Categories</h3>
                    <div class="flex items-center space-x-2">
                        <div class="relative">
                            <select onchange="handleBulkAction(this.value)" class="pr-8 pl-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="">Bulk Actions</option>
                                <option value="delete">Delete Selected</option>
                                <option value="export">Export Selected</option>
                                <option value="merge">Merge Categories</option>
                            </select>
                        </div>
                        <span class="text-sm text-gray-500"><?php echo count($categories); ?> categories</span>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left">
                                    <input type="checkbox" id="select-all" class="rounded border-gray-300">
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Materials Count</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="categories-table-body">
                            <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-tags text-4xl text-gray-300 mb-2"></i>
                                        <p class="text-lg font-medium">No categories found</p>
                                        <p class="text-sm">Add your first category to get started.</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($categories as $category): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <input type="checkbox" class="rounded border-gray-300 category-checkbox" data-id="<?php echo $category['id']; ?>">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="h-10 w-10 rounded-lg bg-gradient-to-r from-purple-400 to-purple-600 flex items-center justify-center text-white font-semibold">
                                                <i class="fas fa-tag"></i>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($category['name']); ?></div>
                                                <div class="text-sm text-gray-500">ID: <?php echo $category['id']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900 max-w-xs">
                                            <?php echo htmlspecialchars($category['description'] ?? 'No description available'); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="text-sm font-medium text-gray-900"><?php echo $category['material_count']; ?></div>
                                            <?php if ($category['active_materials'] != $category['material_count']): ?>
                                                <span class="ml-2 text-xs text-gray-500">
                                                    (<?php echo $category['active_materials']; ?> active)
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($category['material_count'] > 0): ?>
                                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                <i class="fas fa-check-circle mr-1"></i>
                                                In Use
                                            </span>
                                        <?php else: ?>
                                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                                Empty
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button onclick="viewCategory(<?php echo $category['id']; ?>)" class="text-blue-600 hover:text-blue-900" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button onclick="editCategory(<?php echo $category['id']; ?>)" class="text-green-600 hover:text-green-900" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="viewMaterials(<?php echo $category['id']; ?>)" class="text-purple-600 hover:text-purple-900" title="View Materials">
                                                <i class="fas fa-cube"></i>
                                            </button>
                                            <?php if ($category['material_count'] == 0): ?>
                                            <button onclick="deleteCategory(<?php echo $category['id']; ?>)" class="text-red-600 hover:text-red-900" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php else: ?>
                                            <button onclick="showCannotDelete()" class="text-gray-400 cursor-not-allowed" title="Cannot delete - has materials">
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
                                Showing <span class="font-medium">1</span> to <span class="font-medium"><?php echo count($categories); ?></span> of 
                                <span class="font-medium"><?php echo $stats['total_categories']; ?></span> results
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

    <!-- Add Category Modal -->
    <div id="add-category-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('add-category-modal')"></div>
        <div class="modal-content max-w-lg">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Add New Category</h3>
                <button onclick="closeModal('add-category-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="add-category-form">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category Name*</label>
                    <input type="text" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="Enter category name">
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="Enter category description..."></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('add-category-modal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Category</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div id="edit-category-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('edit-category-modal')"></div>
        <div class="modal-content max-w-lg">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Edit Category</h3>
                <button onclick="closeModal('edit-category-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="edit-category-form">
                <input type="hidden" name="id" id="edit-category-id">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category Name*</label>
                    <input type="text" name="name" id="edit-category-name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="description" id="edit-category-description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('edit-category-modal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Import Modal -->
    <div id="bulk-import-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('bulk-import-modal')"></div>
        <div class="modal-content max-w-lg">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Import Categories</h3>
                <button onclick="closeModal('bulk-import-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="text-center">
                <div class="mb-4">
                    <i class="fas fa-upload text-4xl text-gray-300"></i>
                </div>
                <p class="text-gray-600 mb-4">Upload a CSV file with category data</p>
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
                    <button onclick="importCategories()" class="btn btn-primary">
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
            const checkboxes = document.querySelectorAll('.category-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Category management functions
        async function viewCategory(id) {
            try {
                const response = await fetch(`../api/categories.php?action=get&id=${id}`);
                const result = await response.json();
                
                if (result.success) {
                    const category = result.data;
                    const modalContent = `
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Category Name</label>
                                    <p class="mt-1 text-sm text-gray-900">${category.name}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Total Materials</label>
                                    <p class="mt-1 text-sm text-gray-900">${category.material_count}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Active Materials</label>
                                    <p class="mt-1 text-sm text-gray-900">${category.active_materials}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Average Price</label>
                                    <p class="mt-1 text-sm text-gray-900">$${parseFloat(category.avg_price).toFixed(2)}</p>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Description</label>
                                <p class="mt-1 text-sm text-gray-900">${category.description || 'No description available'}</p>
                            </div>
                            ${category.recent_materials && category.recent_materials.length > 0 ? `
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Recent Materials</label>
                                    <div class="space-y-2">
                                        ${category.recent_materials.map(material => `
                                            <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
                                                <span class="text-sm">${material.name}</span>
                                                <span class="text-sm text-gray-500">$${parseFloat(material.price_per_unit).toFixed(2)}</span>
                                            </div>
                                        `).join('')}
                                    </div>
                                </div>
                            ` : ''}
                        </div>
                    `;
                    
                    showModal('Category Details', modalContent);
                } else {
                    showNotification(result.error, 'error');
                }
            } catch (error) {
                showNotification('Failed to load category details', 'error');
            }
        }

        async function editCategory(id) {
            try {
                const response = await fetch(`../api/categories.php?action=get&id=${id}`);
                const result = await response.json();
                
                if (result.success) {
                    const category = result.data;
                    document.getElementById('edit-category-id').value = category.id;
                    document.getElementById('edit-category-name').value = category.name;
                    document.getElementById('edit-category-description').value = category.description || '';
                    openModal('edit-category-modal');
                } else {
                    showNotification(result.error, 'error');
                }
            } catch (error) {
                showNotification('Failed to load category data', 'error');
            }
        }

        async function viewMaterials(id) {
            // Redirect to materials page with category filter
            window.location.href = `materials.php?category=${id}`;
        }

        async function deleteCategory(id) {
            if (confirm('Are you sure you want to delete this category? This action cannot be undone.')) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'delete');
                    formData.append('id', id);
                    
                    const response = await fetch('../api/categories.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showNotification(result.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showNotification(result.error, 'error');
                    }
                } catch (error) {
                    showNotification('Failed to delete category', 'error');
                }
            }
        }

        function showCannotDelete() {
            showNotification('Cannot delete category that contains materials. Move materials to another category first.', 'warning');
        }

        function handleBulkAction(action) {
            if (!action) return;
            
            const selectedItems = document.querySelectorAll('.category-checkbox:checked');
            if (selectedItems.length === 0) {
                showNotification('Please select at least one category', 'warning');
                return;
            }

            const selectedIds = Array.from(selectedItems).map(item => item.dataset.id);

            switch (action) {
                case 'delete':
                    if (confirm(`Are you sure you want to delete ${selectedItems.length} categories?`)) {
                        bulkDeleteCategories(selectedIds);
                    }
                    break;
                case 'export':
                    exportCategories(selectedIds);
                    break;
                case 'merge':
                    if (selectedItems.length < 2) {
                        showNotification('Please select at least 2 categories to merge', 'warning');
                        return;
                    }
                    showMergeModal(selectedIds);
                    break;
            }
            
            // Reset the select dropdown
            document.querySelector('select').value = '';
        }

        async function bulkDeleteCategories(categoryIds) {
            try {
                const response = await fetch('../api/categories.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'bulk_delete',
                        category_ids: categoryIds
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification(result.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.error, 'error');
                }
            } catch (error) {
                showNotification('Failed to delete categories', 'error');
            }
        }

        function exportCategories(selectedIds = null) {
            const params = new URLSearchParams({
                action: 'export',
                format: 'csv'
            });
            
            if (selectedIds && selectedIds.length > 0) {
                params.append('category_ids', selectedIds.join(','));
            }
            
            window.open(`../api/categories.php?${params.toString()}`, '_blank');
            showNotification('Export started successfully!', 'success');
        }

        function downloadTemplate() {
            const csvContent = 'name,description\n' +
                              'Steel Products,Various steel materials and products\n' +
                              'Safety Equipment,Personal protective equipment and safety gear';

            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'categories_template.csv';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);

            showNotification('Template downloaded successfully!', 'success');
        }

        async function importCategories() {
            const fileInput = document.getElementById('import-file');
            if (!fileInput.files.length) {
                showNotification('Please select a file first', 'warning');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'import');
            formData.append('file', fileInput.files[0]);
            
            try {
                const response = await fetch('../api/categories.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification(result.message, 'success');
                    if (result.errors && result.errors.length > 0) {
                        console.log('Import errors:', result.errors);
                        showNotification(`Imported with ${result.errors.length} warnings. Check console for details.`, 'warning');
                    }
                    closeModal('bulk-import-modal');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(result.error, 'error');
                }
            } catch (error) {
                showNotification('Failed to import categories', 'error');
            }
        }

        // Form submission handlers
        document.getElementById('add-category-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = {
                action: 'create',
                name: formData.get('name'),
                description: formData.get('description')
            };
            
            try {
                const response = await fetch('../api/categories.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification(result.message, 'success');
                    closeModal('add-category-modal');
                    this.reset();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.error, 'error');
                }
            } catch (error) {
                showNotification('Failed to create category', 'error');
            }
        });

        document.getElementById('edit-category-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = {
                action: 'update',
                id: formData.get('id'),
                name: formData.get('name'),
                description: formData.get('description')
            };
            
            try {
                const response = await fetch('../api/categories.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification(result.message, 'success');
                    closeModal('edit-category-modal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.error, 'error');
                }
            } catch (error) {
                showNotification('Failed to update category', 'error');
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

        // Modal helper functions
        function showModal(title, content) {
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-overlay" onclick="this.parentElement.remove()"></div>
                <div class="modal-content max-w-2xl">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-800">${title}</h3>
                        <button onclick="this.closest('.modal').remove()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    ${content}
                </div>
            `;
            
            document.body.appendChild(modal);
        }

        function showMergeModal(categoryIds) {
            fetch('../api/categories.php?action=list&limit=100')
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        const availableCategories = result.data.filter(cat => !categoryIds.includes(cat.id.toString()));
                        
                        const options = availableCategories.map(cat => 
                            `<option value="${cat.id}">${cat.name} (${cat.material_count} materials)</option>`
                        ).join('');
                        
                        const content = `
                            <form id="merge-categories-form">
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Merge Into Category</label>
                                    <select name="target_category" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                        <option value="">Select target category...</option>
                                        ${options}
                                    </select>
                                </div>
                                <div class="mb-6">
                                    <p class="text-sm text-gray-600">
                                        This will move all materials from the selected categories to the target category, 
                                        then delete the empty categories. This action cannot be undone.
                                    </p>
                                </div>
                                <div class="flex justify-end space-x-3">
                                    <button type="button" onclick="this.closest('.modal').remove()" class="btn btn-secondary">Cancel</button>
                                    <button type="submit" class="btn btn-warning">Merge Categories</button>
                                </div>
                            </form>
                        `;
                        
                        showModal('Merge Categories', content);
                        
                        // Handle form submission
                        document.getElementById('merge-categories-form').addEventListener('submit', async function(e) {
                            e.preventDefault();
                            
                            const formData = new FormData(this);
                            const targetCategoryId = formData.get('target_category');
                            
                            if (!targetCategoryId) {
                                showNotification('Please select a target category', 'warning');
                                return;
                            }
                            
                            try {
                                const response = await fetch('../api/categories.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                    },
                                    body: JSON.stringify({
                                        action: 'merge',
                                        target_category_id: targetCategoryId,
                                        source_category_ids: categoryIds
                                    })
                                });
                                
                                const result = await response.json();
                                
                                if (result.success) {
                                    showNotification(result.message, 'success');
                                    this.closest('.modal').remove();
                                    setTimeout(() => location.reload(), 1000);
                                } else {
                                    showNotification(result.error, 'error');
                                }
                            } catch (error) {
                                showNotification('Failed to merge categories', 'error');
                            }
                        });
                    }
                })
                .catch(error => {
                    showNotification('Failed to load categories for merge', 'error');
                });
        }
    </script>
</body>
</html>
