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
        'text' => 'Add Return Item',
        'icon' => 'fas fa-plus',
        'class' => 'btn-primary',
        'onclick' => 'openModal(\'add-return-item-modal\')'
    ],
    [
        'text' => 'Export Data',
        'icon' => 'fas fa-download',
        'class' => 'btn-secondary',
        'onclick' => 'exportReturnItems()'
    ]
];

// Get return item statistics
function getReturnItemStats($pdo) {
    $stats = [];
    
    // Total return items
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM Return_Items");
    $stats['total_returns'] = $stmt->fetch()['total'] ?? 0;
    
    // Items by condition status
    $stmt = $pdo->query("SELECT COUNT(*) as good FROM Return_Items WHERE condition_status = 'good'");
    $stats['good_condition'] = $stmt->fetch()['good'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as damaged FROM Return_Items WHERE condition_status = 'damaged'");
    $stats['damaged_condition'] = $stmt->fetch()['damaged'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as lost FROM Return_Items WHERE condition_status = 'lost'");
    $stats['lost_condition'] = $stmt->fetch()['lost'] ?? 0;
    
    // Items with damage reports
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT ri.id) as with_reports 
        FROM Return_Items ri 
        INNER JOIN Damage_Report dr ON ri.id = dr.return_item_id
    ");
    $stats['with_damage_reports'] = $stmt->fetch()['with_reports'] ?? 0;
    
    // Recent returns (last 7 days)
    $stmt = $pdo->query("SELECT COUNT(*) as recent FROM Return_Items WHERE return_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['recent_returns'] = $stmt->fetch()['recent'] ?? 0;
    
    return $stats;
}

// Get return items with pagination and filters
function getReturnItems($pdo, $page = 1, $limit = 10, $filters = []) {
    $offset = ($page - 1) * $limit;
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if (!empty($filters['search'])) {
        $whereClause .= " AND (bi.item_description LIKE :search OR ri.damage_notes LIKE :search OR c.name LIKE :search OR e.name LIKE :search)";
        $params['search'] = "%" . $filters['search'] . "%";
    }
    
    if (!empty($filters['condition_status'])) {
        $whereClause .= " AND ri.condition_status = :condition_status";
        $params['condition_status'] = $filters['condition_status'];
    }
    
    if (!empty($filters['transaction_type'])) {
        $whereClause .= " AND bt.transaction_type = :transaction_type";
        $params['transaction_type'] = $filters['transaction_type'];
    }
    
    if (!empty($filters['request_status'])) {
        $whereClause .= " AND br.status = :request_status";
        $params['request_status'] = $filters['request_status'];
    }
    
    if (!empty($filters['customer_id'])) {
        $whereClause .= " AND br.customer_id = :customer_id";
        $params['customer_id'] = $filters['customer_id'];
    }
    
    if (!empty($filters['date_from'])) {
        $whereClause .= " AND DATE(ri.return_date) >= :date_from";
        $params['date_from'] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $whereClause .= " AND DATE(ri.return_date) <= :date_to";
        $params['date_to'] = $filters['date_to'];
    }
    
    $query = "SELECT ri.*, 
                     bi.item_description,
                     bi.quantity_requested,
                     bi.quantity_approved,
                     bi.quantity_borrowed,
                     bit.name as item_type_name,
                     bit.unit as item_type_unit,
                     bt.transaction_type,
                     bt.transaction_date,
                     bt.notes as transaction_notes,
                     pe.name as processed_by_name,
                     pe.employee_id as processed_by_employee_id,
                     br.id as request_id,
                     br.purpose as request_purpose,
                     br.status as request_status,
                     br.request_date,
                     br.required_date,
                     c.name as customer_name,
                     c.customer_type,
                     e.name as employee_name,
                     l.name as location_name,
                     l.city as location_city,
                     (SELECT COUNT(*) FROM Damage_Report dr WHERE dr.return_item_id = ri.id) as damage_reports_count
              FROM Return_Items ri 
              INNER JOIN Borrowing_Transaction bt ON ri.borrowing_transaction_id = bt.id 
              INNER JOIN Borrowing_Request br ON bt.borrowing_request_id = br.id 
              INNER JOIN Customer c ON br.customer_id = c.id 
              INNER JOIN Employee e ON br.employee_id = e.id 
              INNER JOIN Borrowing_Items bi ON ri.borrowing_item_id = bi.id 
              LEFT JOIN Borrowing_Item_Types bit ON bi.item_type_id = bit.id 
              LEFT JOIN Employee pe ON bt.processed_by = pe.id 
              LEFT JOIN Location l ON br.location_id = l.id 
              $whereClause 
              ORDER BY ri.return_date DESC, ri.id DESC 
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
    
    // Get customers
    $stmt = $pdo->query("SELECT id, name FROM Customer WHERE status = 'active' ORDER BY name");
    $options['customers'] = $stmt->fetchAll();
    
    // Get borrowing transactions (for creating new return items)
    $stmt = $pdo->query("
        SELECT bt.id, bt.transaction_type, bt.transaction_date, 
               br.id as request_id, br.purpose, c.name as customer_name,
               bi.id as borrowing_item_id, bi.item_description, bi.quantity_borrowed
        FROM Borrowing_Transaction bt 
        INNER JOIN Borrowing_Request br ON bt.borrowing_request_id = br.id 
        INNER JOIN Customer c ON br.customer_id = c.id 
        INNER JOIN Borrowing_Items bi ON br.id = bi.borrowing_request_id
        WHERE bt.transaction_type IN ('borrow', 'partial_return')
        AND br.status IN ('active', 'completed')
        AND bi.quantity_borrowed > 0
        AND bi.id NOT IN (
            SELECT ri.borrowing_item_id 
            FROM Return_Items ri 
            INNER JOIN Borrowing_Transaction bt2 ON ri.borrowing_transaction_id = bt2.id 
            WHERE bt2.transaction_type = 'return'
        )
        ORDER BY bt.transaction_date DESC 
        LIMIT 100
    ");
    $options['borrowing_transactions'] = $stmt->fetchAll();
    
    return $options;
}

// Get current filters and pagination
$filters = [
    'search' => $_GET['search'] ?? '',
    'condition_status' => $_GET['condition_status'] ?? '',
    'transaction_type' => $_GET['transaction_type'] ?? '',
    'request_status' => $_GET['request_status'] ?? '',
    'customer_id' => $_GET['customer_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

$page = (int)($_GET['page'] ?? 1);
$limit = 10;

// Get data
$stats = getReturnItemStats($pdo);
$returnItems = getReturnItems($pdo, $page, $limit, $filters);
$filterOptions = getFilterOptions($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Items - Warehouse Admin</title>
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
                            <i class="fas fa-undo text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Total Returns</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['total_returns']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Good Condition</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['good_condition']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-orange-100">
                            <i class="fas fa-exclamation-triangle text-orange-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Damaged</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['damaged_condition']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-red-100">
                            <i class="fas fa-times-circle text-red-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Lost Items</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['lost_condition']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100">
                            <i class="fas fa-clock text-yellow-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Recent (7 days)</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['recent_returns']); ?></p>
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
                                   placeholder="Search items, notes, customers..." 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Condition Status</label>
                            <select name="condition_status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All Conditions</option>
                                <option value="good" <?php echo $filters['condition_status'] === 'good' ? 'selected' : ''; ?>>Good</option>
                                <option value="damaged" <?php echo $filters['condition_status'] === 'damaged' ? 'selected' : ''; ?>>Damaged</option>
                                <option value="lost" <?php echo $filters['condition_status'] === 'lost' ? 'selected' : ''; ?>>Lost</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Transaction Type</label>
                            <select name="transaction_type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All Types</option>
                                <option value="return" <?php echo $filters['transaction_type'] === 'return' ? 'selected' : ''; ?>>Return</option>
                                <option value="partial_return" <?php echo $filters['transaction_type'] === 'partial_return' ? 'selected' : ''; ?>>Partial Return</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
                            <input type="date" name="date_from" value="<?php echo htmlspecialchars($filters['date_from']); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
                            <input type="date" name="date_to" value="<?php echo htmlspecialchars($filters['date_to']); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div class="flex justify-between items-center mt-4">
                        <div class="flex space-x-3">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                <i class="fas fa-search mr-2"></i>Search
                            </button>
                            <a href="return-items.php" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50">
                                <i class="fas fa-times mr-2"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Return Items Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800">Return Items</h3>
                    
                    <div class="flex items-center space-x-3">
                        <select onchange="handleBulkAction(this.value)" class="px-3 py-2 border border-gray-300 rounded-md">
                            <option value="">Bulk Actions</option>
                            <option value="delete">Delete Selected</option>
                            <option value="export">Export Selected</option>
                        </select>
                        
                        <button onclick="openModal('add-return-item-modal')" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>Add Return Item
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Return Info</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item Details</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantities</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction & Request</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($returnItems as $item): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <input type="checkbox" class="return-item-checkbox rounded border-gray-300" data-id="<?php echo $item['id']; ?>">
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    Return #<?php echo $item['id']; ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo date('M j, Y g:i A', strtotime($item['return_date'])); ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?php
                                                    $conditionColors = [
                                                        'good' => 'bg-green-100 text-green-800',
                                                        'damaged' => 'bg-orange-100 text-orange-800',
                                                        'lost' => 'bg-red-100 text-red-800'
                                                    ];
                                                    $conditionClass = $conditionColors[$item['condition_status']] ?? 'bg-gray-100 text-gray-800';
                                                    ?>
                                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $conditionClass; ?>">
                                                        <?php echo ucfirst($item['condition_status']); ?>
                                                    </span>
                                                </div>
                                                <?php if ($item['damage_reports_count'] > 0): ?>
                                                    <div class="text-sm text-red-600">
                                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                                        <?php echo $item['damage_reports_count']; ?> damage report(s)
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">
                                            <?php echo htmlspecialchars($item['item_description']); ?>
                                        </div>
                                        <?php if ($item['item_type_name']): ?>
                                            <div class="text-sm text-gray-500">
                                                Type: <?php echo htmlspecialchars($item['item_type_name']); ?>
                                                <?php if ($item['item_type_unit']): ?>
                                                    (<?php echo htmlspecialchars($item['item_type_unit']); ?>)
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($item['damage_notes']): ?>
                                            <div class="text-sm text-gray-500 truncate max-w-xs">
                                                <i class="fas fa-sticky-note mr-1"></i>
                                                <?php echo htmlspecialchars(substr($item['damage_notes'], 0, 50)); ?>...
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">
                                            <div>Returned: <strong><?php echo $item['quantity_returned']; ?></strong></div>
                                            <div class="text-gray-500">Borrowed: <?php echo $item['quantity_borrowed']; ?></div>
                                            <div class="text-gray-500">Approved: <?php echo $item['quantity_approved']; ?></div>
                                            <div class="text-gray-500">Requested: <?php echo $item['quantity_requested']; ?></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">
                                            <?php echo htmlspecialchars($item['customer_name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php
                                            $transactionColors = [
                                                'borrow' => 'bg-blue-100 text-blue-800',
                                                'return' => 'bg-green-100 text-green-800',
                                                'partial_return' => 'bg-orange-100 text-orange-800'
                                            ];
                                            $transactionClass = $transactionColors[$item['transaction_type']] ?? 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $transactionClass; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $item['transaction_type'])); ?>
                                            </span>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            Request #<?php echo $item['request_id']; ?>
                                        </div>
                                        <div class="text-sm text-gray-500 truncate max-w-xs">
                                            <?php echo htmlspecialchars(substr($item['request_purpose'], 0, 30)); ?>...
                                        </div>
                                        <?php if ($item['processed_by_name']): ?>
                                            <div class="text-sm text-gray-500">
                                                By: <?php echo htmlspecialchars($item['processed_by_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button onclick="viewReturnItem(<?php echo $item['id']; ?>)" 
                                                    class="text-blue-600 hover:text-blue-900" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button onclick="editReturnItem(<?php echo $item['id']; ?>)" 
                                                    class="text-indigo-600 hover:text-indigo-900" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($item['damage_reports_count'] == 0): ?>
                                                <button onclick="deleteReturnItem(<?php echo $item['id']; ?>)" 
                                                        class="text-red-600 hover:text-red-900" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php else: ?>
                                                <button disabled class="text-gray-400 cursor-not-allowed" title="Cannot delete - has damage reports">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
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
                            Showing <?php echo count($returnItems); ?> return items
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

    <!-- Add Return Item Modal -->
    <div id="add-return-item-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('add-return-item-modal')"></div>
        <div class="modal-content max-w-2xl">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Add New Return Item</h3>
                <button onclick="closeModal('add-return-item-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="add-return-item-form">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Borrowing Transaction & Item *</label>
                        <select name="borrowing_data" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Transaction & Item</option>
                            <?php foreach ($filterOptions['borrowing_transactions'] as $transaction): ?>
                                <option value="<?php echo $transaction['id']; ?>|<?php echo $transaction['borrowing_item_id']; ?>">
                                    Transaction #<?php echo $transaction['id']; ?> - <?php echo htmlspecialchars($transaction['item_description']); ?> 
                                    (<?php echo ucfirst(str_replace('_', ' ', $transaction['transaction_type'])); ?>) - 
                                    <?php echo htmlspecialchars($transaction['customer_name']); ?> - 
                                    Borrowed: <?php echo $transaction['quantity_borrowed']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Quantity Returned *</label>
                        <input type="number" name="quantity_returned" required min="1" 
                               placeholder="Enter quantity" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Condition Status *</label>
                        <select name="condition_status" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Condition</option>
                            <option value="good">Good</option>
                            <option value="damaged">Damaged</option>
                            <option value="lost">Lost</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Damage Notes</label>
                    <textarea name="damage_notes" rows="4" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                              placeholder="Add notes about damage or condition (optional)..."></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('add-return-item-modal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Create Return Item
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Return Item Modal -->
    <div id="edit-return-item-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('edit-return-item-modal')"></div>
        <div class="modal-content max-w-2xl">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Edit Return Item</h3>
                <button onclick="closeModal('edit-return-item-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="edit-return-item-form">
                <input type="hidden" id="edit-return-item-id" name="id">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Quantity Returned *</label>
                        <input type="number" name="quantity_returned" id="edit-quantity-returned" required min="1" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Condition Status *</label>
                        <select name="condition_status" id="edit-condition-status" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="good">Good</option>
                            <option value="damaged">Damaged</option>
                            <option value="lost">Lost</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Damage Notes</label>
                    <textarea name="damage_notes" id="edit-damage-notes" rows="4" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                              placeholder="Add notes about damage or condition (optional)..."></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('edit-return-item-modal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Update Return Item
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Return Item Modal -->
    <div id="view-return-item-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('view-return-item-modal')"></div>
        <div class="modal-content max-w-4xl">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Return Item Details</h3>
                <button onclick="closeModal('view-return-item-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div id="return-item-details-content">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <script src="js/dashboard.js"></script>
    <script>
        // Initialize select all functionality
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.return-item-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Global variables
        let editingReturnItemId = null;

        // Return item management functions
        async function viewReturnItem(id) {
            try {
                const response = await fetch(`api/return-items.php?action=get&id=${id}`);
                const result = await response.json();
                
                if (result.success) {
                    showReturnItemDetails(result.data);
                } else {
                    showNotification('Failed to load return item details', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            }
        }

        function showReturnItemDetails(item) {
            const conditionColors = {
                'good': 'bg-green-100 text-green-800',
                'damaged': 'bg-orange-100 text-orange-800',
                'lost': 'bg-red-100 text-red-800'
            };
            
            const transactionColors = {
                'borrow': 'bg-blue-100 text-blue-800',
                'return': 'bg-green-100 text-green-800',
                'partial_return': 'bg-orange-100 text-orange-800'
            };
            
            const conditionClass = conditionColors[item.condition_status] || 'bg-gray-100 text-gray-800';
            const transactionClass = transactionColors[item.transaction_type] || 'bg-gray-100 text-gray-800';
            
            const content = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <h4 class="text-md font-semibold text-gray-700">Return Information</h4>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="grid grid-cols-1 gap-3">
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Return ID:</span>
                                    <p class="text-sm text-gray-900">#${item.id}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Return Date:</span>
                                    <p class="text-sm text-gray-900">${new Date(item.return_date).toLocaleString()}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Quantity Returned:</span>
                                    <p class="text-sm text-gray-900">${item.quantity_returned}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Condition Status:</span>
                                    <div class="mt-1">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${conditionClass}">
                                            ${item.condition_status.charAt(0).toUpperCase() + item.condition_status.slice(1)}
                                        </span>
                                    </div>
                                </div>
                                ${item.damage_reports_count > 0 ? `
                                    <div>
                                        <span class="text-sm font-medium text-gray-500">Damage Reports:</span>
                                        <p class="text-sm text-red-600">${item.damage_reports_count} report(s)</p>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <h4 class="text-md font-semibold text-gray-700">Item Information</h4>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="grid grid-cols-1 gap-3">
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Item Description:</span>
                                    <p class="text-sm text-gray-900">${item.item_description}</p>
                                </div>
                                ${item.item_type_name ? `
                                    <div>
                                        <span class="text-sm font-medium text-gray-500">Item Type:</span>
                                        <p class="text-sm text-gray-900">${item.item_type_name}${item.item_type_unit ? ' (' + item.item_type_unit + ')' : ''}</p>
                                    </div>
                                ` : ''}
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Quantity Borrowed:</span>
                                    <p class="text-sm text-gray-900">${item.quantity_borrowed}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Quantity Approved:</span>
                                    <p class="text-sm text-gray-900">${item.quantity_approved}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Quantity Requested:</span>
                                    <p class="text-sm text-gray-900">${item.quantity_requested}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <h4 class="text-md font-semibold text-gray-700">Transaction Information</h4>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="grid grid-cols-1 gap-3">
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Transaction Type:</span>
                                    <div class="mt-1">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${transactionClass}">
                                            ${item.transaction_type.replace('_', ' ').charAt(0).toUpperCase() + item.transaction_type.replace('_', ' ').slice(1)}
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Transaction Date:</span>
                                    <p class="text-sm text-gray-900">${new Date(item.transaction_date).toLocaleString()}</p>
                                </div>
                                ${item.processed_by_name ? `
                                    <div>
                                        <span class="text-sm font-medium text-gray-500">Processed By:</span>
                                        <p class="text-sm text-gray-900">${item.processed_by_name}</p>
                                        <p class="text-xs text-gray-500">ID: ${item.processed_by_employee_id}</p>
                                    </div>
                                ` : ''}
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
                                ${item.location_name ? `
                                    <div>
                                        <span class="text-sm font-medium text-gray-500">Location:</span>
                                        <p class="text-sm text-gray-900">${item.location_name}${item.location_city ? ', ' + item.location_city : ''}</p>
                                    </div>
                                ` : ''}
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
                    <h4 class="text-md font-semibold text-gray-700 mb-3">Request Purpose</h4>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-900">${item.request_purpose}</p>
                    </div>
                </div>
                
                ${item.damage_notes ? `
                    <div class="mt-6">
                        <h4 class="text-md font-semibold text-gray-700 mb-3">Damage Notes</h4>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-900">${item.damage_notes}</p>
                        </div>
                    </div>
                ` : ''}
                
                ${item.transaction_notes ? `
                    <div class="mt-6">
                        <h4 class="text-md font-semibold text-gray-700 mb-3">Transaction Notes</h4>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-900">${item.transaction_notes}</p>
                        </div>
                    </div>
                ` : ''}
            `;
            
            document.getElementById('return-item-details-content').innerHTML = content;
            openModal('view-return-item-modal');
        }

        async function editReturnItem(id) {
            try {
                const response = await fetch(`api/return-items.php?action=get&id=${id}`);
                const result = await response.json();
                
                if (result.success) {
                    populateEditForm(result.data);
                    editingReturnItemId = id;
                    openModal('edit-return-item-modal');
                } else {
                    showNotification('Failed to load return item details', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            }
        }

        function populateEditForm(item) {
            document.getElementById('edit-return-item-id').value = item.id;
            document.getElementById('edit-quantity-returned').value = item.quantity_returned;
            document.getElementById('edit-condition-status').value = item.condition_status;
            document.getElementById('edit-damage-notes').value = item.damage_notes || '';
        }

        async function deleteReturnItem(id) {
            if (!confirm('Are you sure you want to delete this return item? This action cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch('api/return-items.php', {
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
                    showNotification('Return item deleted successfully!', 'success');
                    location.reload();
                } else {
                    showNotification(result.error || 'Failed to delete return item', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            }
        }

        async function handleBulkAction(action) {
            if (!action) return;
            
            const selectedItems = document.querySelectorAll('.return-item-checkbox:checked');
            if (selectedItems.length === 0) {
                showNotification('Please select return items first', 'warning');
                return;
            }

            const selectedIds = Array.from(selectedItems).map(item => item.dataset.id);

            switch (action) {
                case 'delete':
                    await bulkDeleteReturnItems(selectedIds);
                    break;
                case 'export':
                    await exportReturnItems(selectedIds);
                    break;
            }
            
            // Reset the select dropdown
            document.querySelector('select').value = '';
        }

        async function bulkDeleteReturnItems(itemIds) {
            if (!confirm(`Are you sure you want to delete ${itemIds.length} return items? This action cannot be undone.`)) {
                return;
            }

            try {
                const response = await fetch('api/return-items.php', {
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
                    showNotification(result.message || 'Failed to delete return items', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            }
        }

        async function exportReturnItems(itemIds = null) {
            const idsParam = itemIds ? `&item_ids=${itemIds.join(',')}` : '';
            const url = `api/return-items.php?action=export&format=csv${idsParam}`;
            
            try {
                window.open(url, '_blank');
                showNotification('Export started successfully!', 'success');
            } catch (error) {
                console.error('Error:', error);
                showNotification('Failed to export data', 'error');
            }
        }

        // Form submission handlers
        document.getElementById('add-return-item-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const borrowingData = formData.get('borrowing_data').split('|');
            
            const returnItemData = {
                action: 'create',
                borrowing_transaction_id: borrowingData[0],
                borrowing_item_id: borrowingData[1],
                quantity_returned: formData.get('quantity_returned'),
                condition_status: formData.get('condition_status'),
                damage_notes: formData.get('damage_notes')
            };

            try {
                const response = await fetch('api/return-items.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(returnItemData)
                });

                const result = await response.json();
                
                if (result.success) {
                    showNotification('Return item created successfully!', 'success');
                    closeModal('add-return-item-modal');
                    location.reload();
                } else {
                    showNotification(result.error || 'Failed to create return item', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            }
        });

        document.getElementById('edit-return-item-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const returnItemData = {
                action: 'update',
                id: formData.get('id'),
                quantity_returned: formData.get('quantity_returned'),
                condition_status: formData.get('condition_status'),
                damage_notes: formData.get('damage_notes')
            };

            try {
                const response = await fetch('api/return-items.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(returnItemData)
                });

                const result = await response.json();
                
                if (result.success) {
                    showNotification('Return item updated successfully!', 'success');
                    closeModal('edit-return-item-modal');
                    location.reload();
                } else {
                    showNotification(result.error || 'Failed to update return item', 'error');
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
