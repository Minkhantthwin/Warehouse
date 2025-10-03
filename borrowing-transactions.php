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
        'text' => 'Add Transaction',
        'icon' => 'fas fa-plus',
        'class' => 'btn-primary',
        'onclick' => 'openModal(\'add-transaction-modal\')'
    ],
    [
        'text' => 'Export Data',
        'icon' => 'fas fa-download',
        'class' => 'btn-secondary',
        'onclick' => 'exportTransactions()'
    ]
];

// Get borrowing transaction statistics
function getBorrowingTransactionStats($pdo) {
    $stats = [];
    
    // Total transactions
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM Borrowing_Transaction");
    $stats['total_transactions'] = $stmt->fetch()['total'] ?? 0;
    
    // Transactions by type
    $stmt = $pdo->query("SELECT COUNT(*) as borrow FROM Borrowing_Transaction WHERE transaction_type = 'borrow'");
    $stats['borrow_transactions'] = $stmt->fetch()['borrow'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as return_count FROM Borrowing_Transaction WHERE transaction_type = 'return'");
    $stats['return_transactions'] = $stmt->fetch()['return_count'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as partial_return FROM Borrowing_Transaction WHERE transaction_type = 'partial_return'");
    $stats['partial_return_transactions'] = $stmt->fetch()['partial_return'] ?? 0;
    
    // Recent transactions (last 7 days)
    $stmt = $pdo->query("SELECT COUNT(*) as recent FROM Borrowing_Transaction WHERE transaction_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['recent_transactions'] = $stmt->fetch()['recent'] ?? 0;
    
    return $stats;
}

// Get borrowing transactions with pagination and filters
function getBorrowingTransactions($pdo, $page = 1, $limit = 10, $filters = []) {
    $offset = ($page - 1) * $limit;
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if (!empty($filters['search'])) {
        $whereClause .= " AND (bt.notes LIKE :search OR c.name LIKE :search OR e.name LIKE :search OR pe.name LIKE :search OR br.purpose LIKE :search)";
        $params['search'] = "%" . $filters['search'] . "%";
    }
    
    if (!empty($filters['transaction_type'])) {
        $whereClause .= " AND bt.transaction_type = :transaction_type";
        $params['transaction_type'] = $filters['transaction_type'];
    }
    
    if (!empty($filters['status'])) {
        $whereClause .= " AND br.status = :status";
        $params['status'] = $filters['status'];
    }
    
    if (!empty($filters['borrowing_request_id'])) {
        $whereClause .= " AND bt.borrowing_request_id = :borrowing_request_id";
        $params['borrowing_request_id'] = $filters['borrowing_request_id'];
    }
    
    if (!empty($filters['processed_by'])) {
        $whereClause .= " AND bt.processed_by = :processed_by";
        $params['processed_by'] = $filters['processed_by'];
    }
    
    if (!empty($filters['date_from'])) {
        $whereClause .= " AND DATE(bt.transaction_date) >= :date_from";
        $params['date_from'] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $whereClause .= " AND DATE(bt.transaction_date) <= :date_to";
        $params['date_to'] = $filters['date_to'];
    }
    
    $query = "SELECT bt.*, 
                     br.id as request_id,
                     br.status as request_status,
                     br.request_date,
                     br.required_date,
                     br.purpose,
                     c.name as customer_name,
                     c.customer_type,
                     e.name as employee_name,
                     l.name as location_name,
                     l.city as location_city,
                     pe.name as processed_by_name,
                     pe.employee_id as processed_by_employee_id,
                     COUNT(ri.id) as return_items_count
              FROM Borrowing_Transaction bt 
              INNER JOIN Borrowing_Request br ON bt.borrowing_request_id = br.id 
              INNER JOIN Customer c ON br.customer_id = c.id 
              INNER JOIN Employee e ON br.employee_id = e.id 
              INNER JOIN Location l ON br.location_id = l.id 
              LEFT JOIN Employee pe ON bt.processed_by = pe.id 
              LEFT JOIN Return_Items ri ON bt.id = ri.borrowing_transaction_id
              $whereClause 
              GROUP BY bt.id
              ORDER BY bt.transaction_date DESC, bt.id DESC 
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
    
    // Get borrowing requests
    $stmt = $pdo->query("
        SELECT br.id, br.purpose, c.name as customer_name, br.status 
        FROM Borrowing_Request br 
        INNER JOIN Customer c ON br.customer_id = c.id 
        WHERE br.status IN ('approved', 'active', 'completed')
        ORDER BY br.request_date DESC 
        LIMIT 100
    ");
    $options['borrowing_requests'] = $stmt->fetchAll();
    
    // Get employees
    $stmt = $pdo->query("SELECT id, name, employee_id FROM Employee WHERE status = 'active' ORDER BY name");
    $options['employees'] = $stmt->fetchAll();
    
    return $options;
}

// Get current filters and pagination
$filters = [
    'search' => $_GET['search'] ?? '',
    'transaction_type' => $_GET['transaction_type'] ?? '',
    'status' => $_GET['status'] ?? '',
    'borrowing_request_id' => $_GET['borrowing_request_id'] ?? '',
    'processed_by' => $_GET['processed_by'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

$page = (int)($_GET['page'] ?? 1);
$limit = 10;

// Get data
$stats = getBorrowingTransactionStats($pdo);
$transactions = getBorrowingTransactions($pdo, $page, $limit, $filters);
$filterOptions = getFilterOptions($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrowing Transactions - Warehouse Admin</title>
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
                            <i class="fas fa-exchange-alt text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Total Transactions</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['total_transactions']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100">
                            <i class="fas fa-hand-holding text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500"> Transactions</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['borrow_transactions']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100">
                            <i class="fas fa-undo text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Return Transactions</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['return_transactions']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-orange-100">
                            <i class="fas fa-redo text-orange-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Partial Returns</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['partial_return_transactions']); ?></p>
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
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['recent_transactions']); ?></p>
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
                                   placeholder="Search transactions, notes, customers..." 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Transaction Type</label>
                            <select name="transaction_type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All Types</option>
                                <option value="borrow" <?php echo $filters['transaction_type'] === 'borrow' ? 'selected' : ''; ?>>Borrow</option>
                                <option value="return" <?php echo $filters['transaction_type'] === 'return' ? 'selected' : ''; ?>>Return</option>
                                <option value="partial_return" <?php echo $filters['transaction_type'] === 'partial_return' ? 'selected' : ''; ?>>Partial Return</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Request Status</label>
                            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All Statuses</option>
                                <option value="approved" <?php echo $filters['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="completed" <?php echo $filters['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="overdue" <?php echo $filters['status'] === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
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
                            <a href="borrowing-transactions.php" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50">
                                <i class="fas fa-times mr-2"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Transactions Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800"> Transactions</h3>
                    
                    <div class="flex items-center space-x-3">
                        <select onchange="handleBulkAction(this.value)" class="px-3 py-2 border border-gray-300 rounded-md">
                            <option value="">Bulk Actions</option>
                            <option value="delete">Delete Selected</option>
                            <option value="export">Export Selected</option>
                        </select>
                        
                        <button onclick="openModal('add-transaction-modal')" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>Add Transaction
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction Info</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request Details</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer & Location</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Processed By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($transactions as $transaction): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <input type="checkbox" class="transaction-checkbox rounded border-gray-300" data-id="<?php echo $transaction['id']; ?>">
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    Transaction #<?php echo $transaction['id']; ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?php
                                                    $typeColors = [
                                                        'borrow' => 'bg-blue-100 text-blue-800',
                                                        'return' => 'bg-green-100 text-green-800',
                                                        'partial_return' => 'bg-orange-100 text-orange-800'
                                                    ];
                                                    $typeClass = $typeColors[$transaction['transaction_type']] ?? 'bg-gray-100 text-gray-800';
                                                    ?>
                                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $typeClass; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $transaction['transaction_type'])); ?>
                                                    </span>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo date('M j, Y g:i A', strtotime($transaction['transaction_date'])); ?>
                                                </div>
                                                <?php if ($transaction['return_items_count'] > 0): ?>
                                                    <div class="text-sm text-gray-500">
                                                        <?php echo $transaction['return_items_count']; ?> return item(s)
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">
                                            Request #<?php echo $transaction['request_id']; ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php
                                            $statusColors = [
                                                'pending' => 'bg-yellow-100 text-yellow-800',
                                                'approved' => 'bg-green-100 text-green-800',
                                                'rejected' => 'bg-red-100 text-red-800',
                                                'active' => 'bg-blue-100 text-blue-800',
                                                'completed' => 'bg-gray-100 text-gray-800',
                                                'overdue' => 'bg-red-100 text-red-800'
                                            ];
                                            $statusClass = $statusColors[$transaction['request_status']] ?? 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $statusClass; ?>">
                                                <?php echo ucfirst($transaction['request_status']); ?>
                                            </span>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            Requested: <?php echo date('M j, Y', strtotime($transaction['request_date'])); ?>
                                        </div>
                                        <div class="text-sm text-gray-500 truncate max-w-xs">
                                            <?php echo htmlspecialchars(substr($transaction['purpose'], 0, 50)); ?>...
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">
                                            <?php echo htmlspecialchars($transaction['customer_name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo htmlspecialchars($transaction['location_name']); ?>
                                            <?php if ($transaction['location_city']): ?>
                                                , <?php echo htmlspecialchars($transaction['location_city']); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            Employee: <?php echo htmlspecialchars($transaction['employee_name']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">
                                            <?php echo htmlspecialchars($transaction['processed_by_name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            ID: <?php echo htmlspecialchars($transaction['processed_by_employee_id']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button onclick="viewTransaction(<?php echo $transaction['id']; ?>)" 
                                                    class="text-blue-600 hover:text-blue-900" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button onclick="editTransaction(<?php echo $transaction['id']; ?>)" 
                                                    class="text-indigo-600 hover:text-indigo-900" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="deleteTransaction(<?php echo $transaction['id']; ?>)" 
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
                            Showing <?php echo count($transactions); ?> transactions
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

    <!-- Add Transaction Modal -->
    <div id="add-transaction-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('add-transaction-modal')"></div>
        <div class="modal-content max-w-2xl">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Add New Transaction</h3>
                <button onclick="closeModal('add-transaction-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="add-transaction-form">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2"> Request *</label>
                        <select name="borrowing_request_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Storage Request</option>
                            <?php foreach ($filterOptions['borrowing_requests'] as $request): ?>
                                <option value="<?php echo $request['id']; ?>">
                                    Request #<?php echo $request['id']; ?> - <?php echo htmlspecialchars($request['customer_name']); ?> 
                                    (<?php echo ucfirst($request['status']); ?>) - <?php echo htmlspecialchars(substr($request['purpose'], 0, 30)); ?>...
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Transaction Type *</label>
                        <select name="transaction_type" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Type</option>
                            <option value="borrow">Borrow</option>
                            <option value="return">Return</option>
                            <option value="partial_return">Partial Return</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Processed By *</label>
                        <select name="processed_by" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Employee</option>
                            <?php foreach ($filterOptions['employees'] as $employee): ?>
                                <option value="<?php echo $employee['id']; ?>">
                                    <?php echo htmlspecialchars($employee['name']); ?> (ID: <?php echo htmlspecialchars($employee['employee_id']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                    <textarea name="notes" rows="4" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                              placeholder="Additional notes about this transaction..."></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('add-transaction-modal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Create Transaction
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Transaction Modal -->
    <div id="edit-transaction-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('edit-transaction-modal')"></div>
        <div class="modal-content max-w-2xl">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Edit Transaction</h3>
                <button onclick="closeModal('edit-transaction-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="edit-transaction-form">
                <input type="hidden" id="edit-transaction-id" name="id">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Transaction Type *</label>
                        <select name="transaction_type" id="edit-transaction-type" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="borrow">Borrow</option>
                            <option value="return">Return</option>
                            <option value="partial_return">Partial Return</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Processed By *</label>
                        <select name="processed_by" id="edit-processed-by" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <?php foreach ($filterOptions['employees'] as $employee): ?>
                                <option value="<?php echo $employee['id']; ?>">
                                    <?php echo htmlspecialchars($employee['name']); ?> (ID: <?php echo htmlspecialchars($employee['employee_id']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                    <textarea name="notes" id="edit-notes" rows="4" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                              placeholder="Additional notes about this transaction..."></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('edit-transaction-modal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Update Transaction
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Transaction Modal -->
    <div id="view-transaction-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('view-transaction-modal')"></div>
        <div class="modal-content max-w-4xl">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Transaction Details</h3>
                <button onclick="closeModal('view-transaction-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div id="transaction-details-content">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <script src="js/dashboard.js"></script>
    <script>
        // Initialize select all functionality
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.transaction-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Global variables
        let editingTransactionId = null;

        // Transaction management functions
        async function viewTransaction(id) {
            try {
                const response = await fetch(`api/borrowing-transactions.php?action=get&id=${id}`);
                const result = await response.json();
                
                if (result.success) {
                    showTransactionDetails(result.data);
                } else {
                    showNotification('Failed to load transaction details', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            }
        }

        function showTransactionDetails(transaction) {
            const typeColors = {
                'borrow': 'bg-blue-100 text-blue-800',
                'return': 'bg-green-100 text-green-800',
                'partial_return': 'bg-orange-100 text-orange-800'
            };
            
            const statusColors = {
                'pending': 'bg-yellow-100 text-yellow-800',
                'approved': 'bg-green-100 text-green-800',
                'rejected': 'bg-red-100 text-red-800',
                'active': 'bg-blue-100 text-blue-800',
                'completed': 'bg-gray-100 text-gray-800',
                'overdue': 'bg-red-100 text-red-800'
            };
            
            const typeClass = typeColors[transaction.transaction_type] || 'bg-gray-100 text-gray-800';
            const statusClass = statusColors[transaction.request_status] || 'bg-gray-100 text-gray-800';
            
            let returnItemsHtml = '';
            if (transaction.return_items && transaction.return_items.length > 0) {
                returnItemsHtml = `
                    <div class="mt-6">
                        <h4 class="text-md font-semibold text-gray-700 mb-3">Return Items</h4>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="space-y-3">
                                ${transaction.return_items.map(item => `
                                    <div class="flex justify-between items-center p-3 bg-white rounded border">
                                        <div>
                                            <div class="font-medium">${item.item_description}</div>
                                            <div class="text-sm text-gray-500">Quantity: ${item.quantity_returned}</div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm font-medium ${item.condition_status === 'good' ? 'text-green-600' : item.condition_status === 'damaged' ? 'text-orange-600' : 'text-red-600'}">
                                                ${item.condition_status.charAt(0).toUpperCase() + item.condition_status.slice(1)}
                                            </div>
                                            ${item.damage_notes ? `<div class="text-xs text-gray-500">${item.damage_notes}</div>` : ''}
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                `;
            }
            
            const content = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <h4 class="text-md font-semibold text-gray-700">Transaction Information</h4>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="grid grid-cols-1 gap-3">
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Transaction ID:</span>
                                    <p class="text-sm text-gray-900">#${transaction.id}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Type:</span>
                                    <div class="mt-1">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${typeClass}">
                                            ${transaction.transaction_type.replace('_', ' ').charAt(0).toUpperCase() + transaction.transaction_type.replace('_', ' ').slice(1)}
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Date:</span>
                                    <p class="text-sm text-gray-900">${new Date(transaction.transaction_date).toLocaleString()}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Processed By:</span>
                                    <p class="text-sm text-gray-900">${transaction.processed_by_name}</p>
                                    <p class="text-xs text-gray-500">ID: ${transaction.processed_by_employee_id}</p>
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
                                    <p class="text-sm text-gray-900">#${transaction.request_id}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Status:</span>
                                    <div class="mt-1">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${statusClass}">
                                            ${transaction.request_status.charAt(0).toUpperCase() + transaction.request_status.slice(1)}
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Customer:</span>
                                    <p class="text-sm text-gray-900">${transaction.customer_name}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Employee:</span>
                                    <p class="text-sm text-gray-900">${transaction.employee_name}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Location:</span>
                                    <p class="text-sm text-gray-900">${transaction.location_name}${transaction.location_city ? ', ' + transaction.location_city : ''}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Request Date:</span>
                                    <p class="text-sm text-gray-900">${new Date(transaction.request_date).toLocaleDateString()}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Required Date:</span>
                                    <p class="text-sm text-gray-900">${new Date(transaction.required_date).toLocaleDateString()}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6">
                    <h4 class="text-md font-semibold text-gray-700 mb-3">Purpose</h4>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-900">${transaction.purpose}</p>
                    </div>
                </div>
                
                ${transaction.notes ? `
                    <div class="mt-6">
                        <h4 class="text-md font-semibold text-gray-700 mb-3">Transaction Notes</h4>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-900">${transaction.notes}</p>
                        </div>
                    </div>
                ` : ''}
                
                ${returnItemsHtml}
            `;
            
            document.getElementById('transaction-details-content').innerHTML = content;
            openModal('view-transaction-modal');
        }

        async function editTransaction(id) {
            try {
                const response = await fetch(`api/borrowing-transactions.php?action=get&id=${id}`);
                const result = await response.json();
                
                if (result.success) {
                    populateEditForm(result.data);
                    editingTransactionId = id;
                    openModal('edit-transaction-modal');
                } else {
                    showNotification('Failed to load transaction details', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            }
        }

        function populateEditForm(transaction) {
            document.getElementById('edit-transaction-id').value = transaction.id;
            document.getElementById('edit-transaction-type').value = transaction.transaction_type;
            document.getElementById('edit-processed-by').value = transaction.processed_by;
            document.getElementById('edit-notes').value = transaction.notes || '';
        }

        async function deleteTransaction(id) {
            if (!confirm('Are you sure you want to delete this borrowing transaction? This action cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch('api/borrowing-transactions.php', {
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
                    showNotification('Transaction deleted successfully!', 'success');
                    location.reload();
                } else {
                    showNotification(result.error || 'Failed to delete transaction', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            }
        }

        async function handleBulkAction(action) {
            if (!action) return;
            
            const selectedTransactions = document.querySelectorAll('.transaction-checkbox:checked');
            if (selectedTransactions.length === 0) {
                showNotification('Please select transactions first', 'warning');
                return;
            }

            const selectedIds = Array.from(selectedTransactions).map(transaction => transaction.dataset.id);

            switch (action) {
                case 'delete':
                    await bulkDeleteTransactions(selectedIds);
                    break;
                case 'export':
                    await exportTransactions(selectedIds);
                    break;
            }
            
            // Reset the select dropdown
            document.querySelector('select').value = '';
        }

        async function bulkDeleteTransactions(transactionIds) {
            if (!confirm(`Are you sure you want to delete ${transactionIds.length} borrowing transactions? This action cannot be undone.`)) {
                return;
            }

            try {
                const response = await fetch('api/borrowing-transactions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'bulk_delete',
                        ids: transactionIds
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    showNotification(result.message, 'success');
                    location.reload();
                } else {
                    showNotification(result.message || 'Failed to delete transactions', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            }
        }

        async function exportTransactions(transactionIds = null) {
            const idsParam = transactionIds ? `&transaction_ids=${transactionIds.join(',')}` : '';
            const url = `api/borrowing-transactions.php?action=export&format=csv${idsParam}`;
            
            try {
                window.open(url, '_blank');
                showNotification('Export started successfully!', 'success');
            } catch (error) {
                console.error('Error:', error);
                showNotification('Failed to export data', 'error');
            }
        }

        // Form submission handlers
        document.getElementById('add-transaction-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const transactionData = {
                action: 'create',
                borrowing_request_id: formData.get('borrowing_request_id'),
                transaction_type: formData.get('transaction_type'),
                processed_by: formData.get('processed_by'),
                notes: formData.get('notes')
            };

            try {
                const response = await fetch('api/borrowing-transactions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(transactionData)
                });

                const result = await response.json();
                
                if (result.success) {
                    showNotification('Borrowing transaction created successfully!', 'success');
                    closeModal('add-transaction-modal');
                    location.reload();
                } else {
                    showNotification(result.error || 'Failed to create transaction', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            }
        });

        document.getElementById('edit-transaction-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const transactionData = {
                action: 'update',
                id: formData.get('id'),
                transaction_type: formData.get('transaction_type'),
                processed_by: formData.get('processed_by'),
                notes: formData.get('notes')
            };

            try {
                const response = await fetch('api/borrowing-transactions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(transactionData)
                });

                const result = await response.json();
                
                if (result.success) {
                    showNotification('Borrowing transaction updated successfully!', 'success');
                    closeModal('edit-transaction-modal');
                    location.reload();
                } else {
                    showNotification(result.error || 'Failed to update transaction', 'error');
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
