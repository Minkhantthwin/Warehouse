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
        'text' => 'Add Item',
        'icon' => 'fas fa-plus',
        'class' => 'btn-primary',
        'onclick' => 'openModal(\'add-item-modal\')'
    ],
    [
        'text' => 'Export Data',
        'icon' => 'fas fa-download',
        'class' => 'btn-secondary',
        'onclick' => 'exportItems()'
    ]
];

// Get borrowing item statistics
function getBorrowingItemStats($pdo) {
    $stats = [];
    
    // Total items
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM Borrowing_Items");
    $stats['total_items'] = $stmt->fetch()['total'] ?? 0;
    
    // Items by status (based on request status)
    $stmt = $pdo->query("
        SELECT COUNT(*) as pending 
        FROM Borrowing_Items bi 
        INNER JOIN Borrowing_Request br ON bi.borrowing_request_id = br.id 
        WHERE br.status = 'pending'
    ");
    $stats['pending_items'] = $stmt->fetch()['pending'] ?? 0;
    
    $stmt = $pdo->query("
        SELECT COUNT(*) as approved 
        FROM Borrowing_Items bi 
        INNER JOIN Borrowing_Request br ON bi.borrowing_request_id = br.id 
        WHERE br.status = 'approved'
    ");
    $stats['approved_items'] = $stmt->fetch()['approved'] ?? 0;
    
    $stmt = $pdo->query("
        SELECT COUNT(*) as active 
        FROM Borrowing_Items bi 
        INNER JOIN Borrowing_Request br ON bi.borrowing_request_id = br.id 
        WHERE br.status = 'active'
    ");
    $stats['active_items'] = $stmt->fetch()['active'] ?? 0;
    
    // Items with quantity differences
    $stmt = $pdo->query("
        SELECT COUNT(*) as quantity_diff 
        FROM Borrowing_Items 
        WHERE quantity_approved IS NOT NULL 
        AND quantity_requested != quantity_approved
    ");
    $stats['quantity_differences'] = $stmt->fetch()['quantity_diff'] ?? 0;
    
    return $stats;
}

// Get borrowing items with pagination and filters
function getBorrowingItems($pdo, $page = 1, $limit = 10, $filters = []) {
    $offset = ($page - 1) * $limit;
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if (!empty($filters['search'])) {
        $whereClause .= " AND (bi.item_description LIKE :search OR bt.name LIKE :search OR br.purpose LIKE :search OR c.name LIKE :search)";
        $params['search'] = "%" . $filters['search'] . "%";
    }
    
    if (!empty($filters['status'])) {
        $whereClause .= " AND br.status = :status";
        $params['status'] = $filters['status'];
    }
    
    if (!empty($filters['item_type_id'])) {
        $whereClause .= " AND bi.item_type_id = :item_type_id";
        $params['item_type_id'] = $filters['item_type_id'];
    }
    
    if (!empty($filters['borrowing_request_id'])) {
        $whereClause .= " AND bi.borrowing_request_id = :borrowing_request_id";
        $params['borrowing_request_id'] = $filters['borrowing_request_id'];
    }
    
    if (!empty($filters['date_from'])) {
        $whereClause .= " AND DATE(br.request_date) >= :date_from";
        $params['date_from'] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $whereClause .= " AND DATE(br.request_date) <= :date_to";
        $params['date_to'] = $filters['date_to'];
    }
    
    $query = "SELECT bi.*, 
                     bt.name as item_type_name, 
                     bt.unit as item_type_unit,
                     br.id as request_id,
                     br.status as request_status,
                     br.request_date,
                     br.required_date,
                     br.purpose,
                     c.name as customer_name,
                     c.customer_type,
                     e.name as employee_name,
                     l.name as location_name,
                     l.city as location_city
              FROM Borrowing_Items bi 
              INNER JOIN Borrowing_Request br ON bi.borrowing_request_id = br.id 
              INNER JOIN Customer c ON br.customer_id = c.id 
              INNER JOIN Employee e ON br.employee_id = e.id 
              INNER JOIN Location l ON br.location_id = l.id 
              LEFT JOIN Borrowing_Item_Types bt ON bi.item_type_id = bt.id 
              $whereClause 
              ORDER BY br.request_date DESC, bi.id DESC 
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

// Get filter options
function getFilterOptions($pdo) {
    $options = [];
    
    // Get item types
    $stmt = $pdo->query("SELECT id, name FROM Borrowing_Item_Types ORDER BY name");
    $options['item_types'] = $stmt->fetchAll();
    
    // Get borrowing requests
    $stmt = $pdo->query("
        SELECT br.id, br.purpose, c.name as customer_name 
        FROM Borrowing_Request br 
        INNER JOIN Customer c ON br.customer_id = c.id 
        ORDER BY br.request_date DESC 
        LIMIT 100
    ");
    $options['borrowing_requests'] = $stmt->fetchAll();
    
    return $options;
}

// Get current filters and pagination
$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    'item_type_id' => $_GET['item_type_id'] ?? '',
    'borrowing_request_id' => $_GET['borrowing_request_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

$page = (int)($_GET['page'] ?? 1);
$limit = 10;

// Get data
$stats = getBorrowingItemStats($pdo);
$items = getBorrowingItems($pdo, $page, $limit, $filters);
$filterOptions = getFilterOptions($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrowing Items - Warehouse Admin</title>
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
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100">
                            <i class="fas fa-boxes text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Total Items</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['total_items']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100">
                            <i class="fas fa-clock text-yellow-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Pending Items</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['pending_items']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Approved Items</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['approved_items']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100">
                            <i class="fas fa-hand-holding text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Active Items</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['active_items']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-orange-100">
                            <i class="fas fa-exclamation-triangle text-orange-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Quantity Diffs</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['quantity_differences']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <form method="GET" action="">
                    <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                   placeholder="Search items, types, purposes..." 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All Statuses</option>
                                <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo $filters['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo $filters['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="completed" <?php echo $filters['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="overdue" <?php echo $filters['status'] === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Item Type</label>
                            <select name="item_type_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All Types</option>
                                <?php foreach ($filterOptions['item_types'] as $type): ?>
                                    <option value="<?php echo $type['id']; ?>" <?php echo $filters['item_type_id'] == $type['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
                            <input type="date" name="date_from" value="<?php echo htmlspecialchars($filters['date_from']); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="flex items-end">
                            <div class="w-full">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
                                <input type="date" name="date_to" value="<?php echo htmlspecialchars($filters['date_to']); ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-between items-center mt-4">
                        <div class="flex space-x-3">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                <i class="fas fa-search mr-2"></i>Search
                            </button>
                            <a href="borrowing-items.php" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50">
                                <i class="fas fa-times mr-2"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Items Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800">Borrowing Items</h3>
                    
                    <div class="flex items-center space-x-3">
                        <select onchange="handleBulkAction(this.value)" class="px-3 py-2 border border-gray-300 rounded-md">
                            <option value="">Bulk Actions</option>
                            <option value="delete">Delete Selected</option>
                            <option value="export">Export Selected</option>
                        </select>
                        
                        <button onclick="openModal('add-item-modal')" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>Add Item
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item Details</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request Info</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantities</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($items as $item): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <input type="checkbox" class="item-checkbox rounded border-gray-300" data-id="<?php echo $item['id']; ?>">
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($item['item_description']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?php if ($item['item_type_name']): ?>
                                                        Type: <?php echo htmlspecialchars($item['item_type_name']); ?>
                                                        <?php if ($item['item_type_unit']): ?>
                                                            (<?php echo htmlspecialchars($item['item_type_unit']); ?>)
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        No type specified
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($item['estimated_value']): ?>
                                                    <div class="text-sm text-gray-500">
                                                        Value: $<?php echo number_format($item['estimated_value'], 2); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">
                                            Request #<?php echo $item['request_id']; ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo htmlspecialchars($item['customer_name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo htmlspecialchars($item['location_name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo date('M j, Y', strtotime($item['request_date'])); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">
                                            Requested: <?php echo number_format($item['quantity_requested']); ?>
                                        </div>
                                        <?php if ($item['quantity_approved'] !== null): ?>
                                            <div class="text-sm text-gray-500">
                                                Approved: <?php echo number_format($item['quantity_approved']); ?>
                                                <?php if ($item['quantity_approved'] != $item['quantity_requested']): ?>
                                                    <i class="fas fa-exclamation-triangle text-orange-500 ml-1" title="Quantity differs from requested"></i>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($item['quantity_borrowed'] !== null): ?>
                                            <div class="text-sm text-gray-500">
                                                Borrowed: <?php echo number_format($item['quantity_borrowed']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php
                                        $statusColors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'approved' => 'bg-green-100 text-green-800',
                                            'rejected' => 'bg-red-100 text-red-800',
                                            'active' => 'bg-blue-100 text-blue-800',
                                            'completed' => 'bg-gray-100 text-gray-800',
                                            'overdue' => 'bg-red-100 text-red-800'
                                        ];
                                        $statusClass = $statusColors[$item['request_status']] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $statusClass; ?>">
                                            <?php echo ucfirst($item['request_status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button onclick="viewItem(<?php echo $item['id']; ?>)" 
                                                    class="text-blue-600 hover:text-blue-900" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button onclick="editItem(<?php echo $item['id']; ?>)" 
                                                    class="text-indigo-600 hover:text-indigo-900" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="deleteItem(<?php echo $item['id']; ?>)" 
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
                            Showing <?php echo count($items); ?> items
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                <!-- Pagination links would go here -->
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Item Modal -->
    <div id="add-item-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('add-item-modal')"></div>
        <div class="modal-content max-w-2xl">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Add New Borrowing Item</h3>
                <button onclick="closeModal('add-item-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="add-item-form">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Borrowing Request *</label>
                        <select name="borrowing_request_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Borrowing Request</option>
                            <?php foreach ($filterOptions['borrowing_requests'] as $request): ?>
                                <option value="<?php echo $request['id']; ?>">
                                    Request #<?php echo $request['id']; ?> - <?php echo htmlspecialchars($request['customer_name']); ?> 
                                    (<?php echo htmlspecialchars(substr($request['purpose'], 0, 50)); ?>...)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Item Type</label>
                        <select name="item_type_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Item Type</option>
                            <?php foreach ($filterOptions['item_types'] as $type): ?>
                                <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Estimated Value</label>
                        <input type="number" name="estimated_value" step="0.01" min="0" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               placeholder="0.00">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Item Description *</label>
                    <textarea name="item_description" required rows="3" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                              placeholder="Describe the item to be borrowed..."></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Quantity Requested *</label>
                        <input type="number" name="quantity_requested" required min="1" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               placeholder="1">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Quantity Approved</label>
                        <input type="number" name="quantity_approved" min="0" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               placeholder="Leave empty if not approved yet">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Quantity Borrowed</label>
                        <input type="number" name="quantity_borrowed" min="0" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               placeholder="Leave empty if not borrowed yet">
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('add-item-modal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Add Item
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Item Modal -->
    <div id="edit-item-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('edit-item-modal')"></div>
        <div class="modal-content max-w-2xl">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Edit Borrowing Item</h3>
                <button onclick="closeModal('edit-item-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="edit-item-form">
                <input type="hidden" id="edit-item-id" name="id">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Item Type</label>
                        <select name="item_type_id" id="edit-item-type-id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Item Type</option>
                            <?php foreach ($filterOptions['item_types'] as $type): ?>
                                <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Estimated Value</label>
                        <input type="number" name="estimated_value" id="edit-estimated-value" step="0.01" min="0" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               placeholder="0.00">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Item Description *</label>
                    <textarea name="item_description" id="edit-item-description" required rows="3" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                              placeholder="Describe the item to be borrowed..."></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Quantity Requested *</label>
                        <input type="number" name="quantity_requested" id="edit-quantity-requested" required min="1" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               placeholder="1">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Quantity Approved</label>
                        <input type="number" name="quantity_approved" id="edit-quantity-approved" min="0" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               placeholder="Leave empty if not approved yet">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Quantity Borrowed</label>
                        <input type="number" name="quantity_borrowed" id="edit-quantity-borrowed" min="0" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               placeholder="Leave empty if not borrowed yet">
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('edit-item-modal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Update Item
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Item Modal -->
    <div id="view-item-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('view-item-modal')"></div>
        <div class="modal-content max-w-3xl">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Item Details</h3>
                <button onclick="closeModal('view-item-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div id="item-details-content">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <script src="js/dashboard.js"></script>
    <script>
        // Initialize select all functionality
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.item-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Global variables
        let editingItemId = null;

        // Item management functions
        async function viewItem(id) {
            try {
                const response = await fetch(`api/borrowing-items.php?action=get&id=${id}`);
                const result = await response.json();
                
                if (result.success) {
                    showItemDetails(result.data);
                } else {
                    showNotification('Failed to load item details', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            }
        }

        function showItemDetails(item) {
            const statusColors = {
                'pending': 'bg-yellow-100 text-yellow-800',
                'approved': 'bg-green-100 text-green-800',
                'rejected': 'bg-red-100 text-red-800',
                'active': 'bg-blue-100 text-blue-800',
                'completed': 'bg-gray-100 text-gray-800',
                'overdue': 'bg-red-100 text-red-800'
            };
            
            const statusClass = statusColors[item.request_status] || 'bg-gray-100 text-gray-800';
            
            const content = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <h4 class="text-md font-semibold text-gray-700">Item Information</h4>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="mb-3">
                                <span class="text-sm font-medium text-gray-500">Description:</span>
                                <p class="text-sm text-gray-900 mt-1">${item.item_description}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Type:</span>
                                    <p class="text-sm text-gray-900">${item.item_type_name || 'Not specified'}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Unit:</span>
                                    <p class="text-sm text-gray-900">${item.item_type_unit || 'N/A'}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Est. Value:</span>
                                    <p class="text-sm text-gray-900">$${item.estimated_value ? parseFloat(item.estimated_value).toFixed(2) : '0.00'}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Status:</span>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${statusClass}">
                                        ${item.request_status.charAt(0).toUpperCase() + item.request_status.slice(1)}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <h4 class="text-md font-semibold text-gray-700">Request Information</h4>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="grid grid-cols-1 gap-3">
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Request ID:</span>
                                    <p class="text-sm text-gray-900">#${item.request_id}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Customer:</span>
                                    <p class="text-sm text-gray-900">${item.customer_name}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Employee:</span>
                                    <p class="text-sm text-gray-900">${item.employee_name}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Location:</span>
                                    <p class="text-sm text-gray-900">${item.location_name}${item.location_city ? ', ' + item.location_city : ''}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Request Date:</span>
                                    <p class="text-sm text-gray-900">${new Date(item.request_date).toLocaleDateString()}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Required Date:</span>
                                    <p class="text-sm text-gray-900">${new Date(item.required_date).toLocaleDateString()}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6">
                    <h4 class="text-md font-semibold text-gray-700 mb-3">Quantity Information</h4>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="grid grid-cols-3 gap-4">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-blue-600">${item.quantity_requested}</div>
                                <div class="text-sm text-gray-500">Requested</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-green-600">${item.quantity_approved || '—'}</div>
                                <div class="text-sm text-gray-500">Approved</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-purple-600">${item.quantity_borrowed || '—'}</div>
                                <div class="text-sm text-gray-500">Borrowed</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6">
                    <h4 class="text-md font-semibold text-gray-700 mb-3">Purpose</h4>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-900">${item.purpose}</p>
                    </div>
                </div>
            `;
            
            document.getElementById('item-details-content').innerHTML = content;
            openModal('view-item-modal');
        }

        async function editItem(id) {
            try {
                const response = await fetch(`api/borrowing-items.php?action=get&id=${id}`);
                const result = await response.json();
                
                if (result.success) {
                    populateEditForm(result.data);
                    editingItemId = id;
                    openModal('edit-item-modal');
                } else {
                    showNotification('Failed to load item details', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            }
        }

        function populateEditForm(item) {
            document.getElementById('edit-item-id').value = item.id;
            document.getElementById('edit-item-type-id').value = item.item_type_id || '';
            document.getElementById('edit-item-description').value = item.item_description;
            document.getElementById('edit-quantity-requested').value = item.quantity_requested;
            document.getElementById('edit-quantity-approved').value = item.quantity_approved || '';
            document.getElementById('edit-quantity-borrowed').value = item.quantity_borrowed || '';
            document.getElementById('edit-estimated-value').value = item.estimated_value || '';
        }

        async function deleteItem(id) {
            if (!confirm('Are you sure you want to delete this borrowing item? This action cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch('api/borrowing-items.php', {
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
                    showNotification('Item deleted successfully!', 'success');
                    location.reload();
                } else {
                    showNotification(result.error || 'Failed to delete item', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            }
        }

        async function handleBulkAction(action) {
            if (!action) return;
            
            const selectedItems = document.querySelectorAll('.item-checkbox:checked');
            if (selectedItems.length === 0) {
                showNotification('Please select items first', 'warning');
                return;
            }

            const selectedIds = Array.from(selectedItems).map(item => item.dataset.id);

            switch (action) {
                case 'delete':
                    await bulkDeleteItems(selectedIds);
                    break;
                case 'export':
                    await exportItems(selectedIds);
                    break;
            }
            
            // Reset the select dropdown
            document.querySelector('select').value = '';
        }

        async function bulkDeleteItems(itemIds) {
            if (!confirm(`Are you sure you want to delete ${itemIds.length} borrowing items? This action cannot be undone.`)) {
                return;
            }

            try {
                const response = await fetch('api/borrowing-items.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'bulk_delete',
                        ids: itemIds
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    showNotification(result.message, 'success');
                    location.reload();
                } else {
                    showNotification(result.message || 'Failed to delete items', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            }
        }

        async function exportItems(itemIds = null) {
            const idsParam = itemIds ? `&item_ids=${itemIds.join(',')}` : '';
            const url = `api/borrowing-items.php?action=export&format=csv${idsParam}`;
            
            try {
                window.open(url, '_blank');
                showNotification('Export started successfully!', 'success');
            } catch (error) {
                console.error('Error:', error);
                showNotification('Failed to export data', 'error');
            }
        }

        // Form submission handlers
        document.getElementById('add-item-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const itemData = {
                action: 'create',
                borrowing_request_id: formData.get('borrowing_request_id'),
                item_type_id: formData.get('item_type_id'),
                item_description: formData.get('item_description'),
                quantity_requested: formData.get('quantity_requested'),
                quantity_approved: formData.get('quantity_approved'),
                quantity_borrowed: formData.get('quantity_borrowed'),
                estimated_value: formData.get('estimated_value')
            };

            try {
                const response = await fetch('api/borrowing-items.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(itemData)
                });

                const result = await response.json();
                
                if (result.success) {
                    showNotification('Borrowing item created successfully!', 'success');
                    closeModal('add-item-modal');
                    location.reload();
                } else {
                    showNotification(result.error || 'Failed to create item', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            }
        });

        document.getElementById('edit-item-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const itemData = {
                action: 'update',
                id: formData.get('id'),
                item_type_id: formData.get('item_type_id'),
                item_description: formData.get('item_description'),
                quantity_requested: formData.get('quantity_requested'),
                quantity_approved: formData.get('quantity_approved'),
                quantity_borrowed: formData.get('quantity_borrowed'),
                estimated_value: formData.get('estimated_value')
            };

            try {
                const response = await fetch('api/borrowing-items.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(itemData)
                });

                const result = await response.json();
                
                if (result.success) {
                    showNotification('Borrowing item updated successfully!', 'success');
                    closeModal('edit-item-modal');
                    location.reload();
                } else {
                    showNotification(result.error || 'Failed to update item', 'error');
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
