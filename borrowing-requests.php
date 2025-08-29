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
        'text' => 'Add Request',
        'icon' => 'fas fa-plus',
        'class' => 'btn-primary',
        'onclick' => 'openModal(\'add-request-modal\')'
    ],
    [
        'text' => 'Export Data',
        'icon' => 'fas fa-download',
        'class' => 'btn-secondary',
        'onclick' => 'exportRequests()'
    ]
];

// Get borrowing request statistics
function getBorrowingStats($pdo) {
    $stats = [];
    
    // Total requests
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM Borrowing_Request");
    $stats['total_requests'] = $stmt->fetch()['total'] ?? 0;
    
    // Pending requests
    $stmt = $pdo->query("SELECT COUNT(*) as pending FROM Borrowing_Request WHERE status = 'pending'");
    $stats['pending_requests'] = $stmt->fetch()['pending'] ?? 0;
    
    // Approved requests
    $stmt = $pdo->query("SELECT COUNT(*) as approved FROM Borrowing_Request WHERE status = 'approved'");
    $stats['approved_requests'] = $stmt->fetch()['approved'] ?? 0;
    
    // Active borrowings
    $stmt = $pdo->query("SELECT COUNT(*) as active FROM Borrowing_Request WHERE status = 'active'");
    $stats['active_borrowings'] = $stmt->fetch()['active'] ?? 0;
    
    // Overdue returns
    $stmt = $pdo->query("SELECT COUNT(*) as overdue FROM Borrowing_Request WHERE status = 'overdue'");
    $stats['overdue_returns'] = $stmt->fetch()['overdue'] ?? 0;
    
    // Recent activity (last 7 days)
    $stmt = $pdo->query("SELECT COUNT(*) as recent FROM Borrowing_Request WHERE request_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['recent_requests'] = $stmt->fetch()['recent'] ?? 0;
    
    return $stats;
}

// Get borrowing requests with pagination and filters
function getBorrowingRequests($pdo, $page = 1, $limit = 10, $filters = []) {
    $offset = ($page - 1) * $limit;
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if (!empty($filters['search'])) {
        $whereClause .= " AND (c.name LIKE :search OR br.purpose LIKE :search OR e.name LIKE :search OR l.name LIKE :search)";
        $params['search'] = "%" . $filters['search'] . "%";
    }
    
    if (!empty($filters['status'])) {
        $whereClause .= " AND br.status = :status";
        $params['status'] = $filters['status'];
    }
    
    if (!empty($filters['customer_id'])) {
        $whereClause .= " AND br.customer_id = :customer_id";
        $params['customer_id'] = $filters['customer_id'];
    }
    
    if (!empty($filters['location_id'])) {
        $whereClause .= " AND br.location_id = :location_id";
        $params['location_id'] = $filters['location_id'];
    }
    
    if (!empty($filters['date_from'])) {
        $whereClause .= " AND DATE(br.request_date) >= :date_from";
        $params['date_from'] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $whereClause .= " AND DATE(br.request_date) <= :date_to";
        $params['date_to'] = $filters['date_to'];
    }
    
    $query = "SELECT br.*, 
                     c.name as customer_name, 
                     c.customer_type,
                     e.name as employee_name, 
                     l.name as location_name,
                     l.city as location_city,
                     a.name as approved_by_name,
                     COUNT(bi.id) as total_items
              FROM Borrowing_Request br 
              INNER JOIN Customer c ON br.customer_id = c.id 
              INNER JOIN Employee e ON br.employee_id = e.id 
              INNER JOIN Location l ON br.location_id = l.id 
              LEFT JOIN Admin a ON br.approved_by = a.id
              LEFT JOIN Borrowing_Items bi ON br.id = bi.borrowing_request_id
              $whereClause 
              GROUP BY br.id
              ORDER BY br.request_date DESC 
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
    
    // Get employees
    $stmt = $pdo->query("SELECT id, name FROM Employee WHERE status = 'active' ORDER BY name");
    $options['employees'] = $stmt->fetchAll();
    
    // Get locations
    $stmt = $pdo->query("SELECT id, name, city FROM Location ORDER BY name");
    $options['locations'] = $stmt->fetchAll();
    
    // Get item types
    $stmt = $pdo->query("SELECT id, name FROM Borrowing_Item_Types ORDER BY name");
    $options['item_types'] = $stmt->fetchAll();
    
    return $options;
}

// Get current filters and pagination
$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    'customer_id' => $_GET['customer_id'] ?? '',
    'location_id' => $_GET['location_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

$page = (int)($_GET['page'] ?? 1);
$limit = 10;

// Get data
$stats = getBorrowingStats($pdo);
$requests = getBorrowingRequests($pdo, $page, $limit, $filters);
$filterOptions = getFilterOptions($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrowing Requests - Warehouse Admin</title>
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
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-clipboard-list text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Requests</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['total_requests']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                            <i class="fas fa-clock text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Pending</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['pending_requests']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-check text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Approved</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['approved_requests']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <i class="fas fa-handshake text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Active</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['active_borrowings']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-red-100 text-red-600">
                            <i class="fas fa-exclamation-triangle text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Overdue</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['overdue_returns']); ?></p>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Search and Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <form method="GET" action="">
                    <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                   placeholder="Search requests..." 
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
                                <option value="returned" <?php echo $filters['status'] === 'returned' ? 'selected' : ''; ?>>Returned</option>
                                <option value="overdue" <?php echo $filters['status'] === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Customer</label>
                            <select name="customer_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All Customers</option>
                                <?php foreach ($filterOptions['customers'] as $customer): ?>
                                    <option value="<?php echo $customer['id']; ?>" <?php echo $filters['customer_id'] == $customer['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($customer['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Location</label>
                            <select name="location_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All Locations</option>
                                <?php foreach ($filterOptions['locations'] as $location): ?>
                                    <option value="<?php echo $location['id']; ?>" <?php echo $filters['location_id'] == $location['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($location['name'] . ' - ' . $location['city']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                            <input type="date" name="date_from" value="<?php echo htmlspecialchars($filters['date_from']); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                            <input type="date" name="date_to" value="<?php echo htmlspecialchars($filters['date_to']); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-2">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <i class="fas fa-search mr-2"></i>Filter
                        </button>
                        <a href="borrowing-requests.php" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500">
                            <i class="fas fa-times mr-2"></i>Clear
                        </a>
                    </div>
                </form>
            </div>

            <!-- Requests Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">Borrowing Requests</h3>
                    <div class="flex items-center space-x-2">
                        <select onchange="handleBulkAction(this.value)" class="px-3 py-1 border border-gray-300 rounded text-sm">
                            <option value="">Bulk Actions</option>
                            <option value="delete">Delete Selected</option>
                            <option value="export">Export Selected</option>
                        </select>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <input type="checkbox" id="select-all" class="rounded">
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purpose</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Required Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($requests as $request): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="checkbox" class="request-checkbox rounded" data-id="<?php echo $request['id']; ?>">
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">#<?php echo str_pad($request['id'], 4, '0', STR_PAD_LEFT); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($request['customer_name']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo ucfirst($request['customer_type']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($request['location_name']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($request['location_city']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 max-w-xs truncate" title="<?php echo htmlspecialchars($request['purpose']); ?>">
                                        <?php echo htmlspecialchars($request['purpose']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">by <?php echo htmlspecialchars($request['employee_name']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo date('M j, Y', strtotime($request['request_date'])); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo date('g:i A', strtotime($request['request_date'])); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo date('M j, Y', strtotime($request['required_date'])); ?></div>
                                    <?php 
                                    $daysDiff = ceil((strtotime($request['required_date']) - time()) / (60 * 60 * 24));
                                    if ($daysDiff < 0): ?>
                                        <div class="text-sm text-red-500">Overdue</div>
                                    <?php elseif ($daysDiff <= 3): ?>
                                        <div class="text-sm text-orange-500">Due soon</div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $statusColors = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'approved' => 'bg-green-100 text-green-800',
                                        'rejected' => 'bg-red-100 text-red-800',
                                        'active' => 'bg-blue-100 text-blue-800',
                                        'returned' => 'bg-gray-100 text-gray-800',
                                        'overdue' => 'bg-red-100 text-red-800'
                                    ];
                                    $statusClass = $statusColors[$request['status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                        <?php echo ucfirst($request['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo $request['total_items']; ?> items</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-2">
                                        <button onclick="viewRequest(<?php echo $request['id']; ?>)" 
                                                class="text-blue-600 hover:text-blue-900" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="editRequest(<?php echo $request['id']; ?>)" 
                                                class="text-green-600 hover:text-green-900" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($request['status'] === 'pending'): ?>
                                            <button onclick="approveRequest(<?php echo $request['id']; ?>)" 
                                                    class="text-green-600 hover:text-green-900" title="Approve">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button onclick="rejectRequest(<?php echo $request['id']; ?>)" 
                                                    class="text-red-600 hover:text-red-900" title="Reject">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button onclick="deleteRequest(<?php echo $request['id']; ?>)" 
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
                                Showing <span class="font-medium"><?php echo (($page - 1) * $limit) + 1; ?></span> to 
                                <span class="font-medium"><?php echo min($page * $limit, count($requests)); ?></span> of 
                                <span class="font-medium"><?php echo count($requests); ?></span> results
                            </p>
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

    <!-- Add Request Modal -->
    <div id="add-request-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('add-request-modal')"></div>
        <div class="modal-content max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Add New Borrowing Request</h3>
                <button onclick="closeModal('add-request-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="add-request-form">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Customer *</label>
                        <select name="customer_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Customer</option>
                            <?php foreach ($filterOptions['customers'] as $customer): ?>
                                <option value="<?php echo $customer['id']; ?>"><?php echo htmlspecialchars($customer['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Employee *</label>
                        <select name="employee_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Employee</option>
                            <?php foreach ($filterOptions['employees'] as $employee): ?>
                                <option value="<?php echo $employee['id']; ?>"><?php echo htmlspecialchars($employee['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Location *</label>
                        <select name="location_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Location</option>
                            <?php foreach ($filterOptions['locations'] as $location): ?>
                                <option value="<?php echo $location['id']; ?>"><?php echo htmlspecialchars($location['name'] . ' - ' . $location['city']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Required Date *</label>
                        <input type="datetime-local" name="required_date" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Purpose *</label>
                    <textarea name="purpose" required rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Describe the purpose of this borrowing request..."></textarea>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                    <textarea name="notes" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Additional notes..."></textarea>
                </div>
                
                <!-- Items Section -->
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-md font-medium text-gray-700">Requested Items</h4>
                        <button type="button" onclick="addItemRow()" class="px-3 py-1 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm">
                            <i class="fas fa-plus mr-1"></i>Add Item
                        </button>
                    </div>
                    
                    <div id="items-container">
                        <div class="item-row grid grid-cols-1 md:grid-cols-5 gap-2 mb-2">
                            <div>
                                <select name="items[0][item_type_id]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select Type</option>
                                    <?php foreach ($filterOptions['item_types'] as $type): ?>
                                        <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <input type="text" name="items[0][item_description]" placeholder="Item description" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <input type="number" name="items[0][quantity_requested]" placeholder="Quantity" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <input type="number" name="items[0][estimated_value]" placeholder="Est. Value" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <button type="button" onclick="removeItemRow(this)" class="w-full px-3 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('add-request-modal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Create Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Request Modal -->
    <div id="edit-request-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('edit-request-modal')"></div>
        <div class="modal-content max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Edit Borrowing Request</h3>
                <button onclick="closeModal('edit-request-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="edit-request-form">
                <input type="hidden" name="id" id="edit-request-id">
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Customer *</label>
                        <select name="customer_id" id="edit-customer-id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Customer</option>
                            <?php foreach ($filterOptions['customers'] as $customer): ?>
                                <option value="<?php echo $customer['id']; ?>"><?php echo htmlspecialchars($customer['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Employee *</label>
                        <select name="employee_id" id="edit-employee-id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Employee</option>
                            <?php foreach ($filterOptions['employees'] as $employee): ?>
                                <option value="<?php echo $employee['id']; ?>"><?php echo htmlspecialchars($employee['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Location *</label>
                        <select name="location_id" id="edit-location-id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Location</option>
                            <?php foreach ($filterOptions['locations'] as $location): ?>
                                <option value="<?php echo $location['id']; ?>"><?php echo htmlspecialchars($location['name'] . ' - ' . $location['city']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" id="edit-status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="active">Active</option>
                            <option value="returned">Returned</option>
                            <option value="overdue">Overdue</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Required Date *</label>
                        <input type="datetime-local" name="required_date" id="edit-required-date" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Purpose *</label>
                    <textarea name="purpose" id="edit-purpose" required rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Describe the purpose of this borrowing request..."></textarea>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                    <textarea name="notes" id="edit-notes" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Additional notes..."></textarea>
                </div>
                
                <!-- Items Section -->
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-md font-medium text-gray-700">Requested Items</h4>
                        <button type="button" onclick="addEditItemRow()" class="px-3 py-1 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm">
                            <i class="fas fa-plus mr-1"></i>Add Item
                        </button>
                    </div>
                    
                    <div id="edit-items-container">
                        <!-- Items will be populated dynamically -->
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('edit-request-modal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Update Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Request Modal -->
    <div id="view-request-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('view-request-modal')"></div>
        <div class="modal-content max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Borrowing Request Details</h3>
                <button onclick="closeModal('view-request-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div id="request-details-content">
                <!-- Request details will be loaded here -->
            </div>
        </div>
    </div>

    <script src="js/dashboard.js"></script>
    <script>
        // Initialize select all functionality
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.request-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Global variables
        let editingRequestId = null;
        let itemRowCounter = 1;

        // Request management functions
        async function viewRequest(id) {
            try {
                const response = await fetch(`api/borrowing-requests.php?action=get&id=${id}`);
                const result = await response.json();
                
                if (result.success) {
                    showRequestDetails(result.data);
                } else {
                    showNotification(result.message || 'Failed to load request details', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            }
        }

        function showRequestDetails(request) {
            const statusColors = {
                'pending': 'bg-yellow-100 text-yellow-800',
                'approved': 'bg-green-100 text-green-800',
                'rejected': 'bg-red-100 text-red-800',
                'active': 'bg-blue-100 text-blue-800',
                'returned': 'bg-gray-100 text-gray-800',
                'overdue': 'bg-red-100 text-red-800'
            };
            
            const statusClass = statusColors[request.status] || 'bg-gray-100 text-gray-800';
            
            const itemsHtml = request.items.map(item => `
                <tr>
                    <td class="px-4 py-2 border">${item.item_type_name || 'N/A'}</td>
                    <td class="px-4 py-2 border">${item.item_description || 'N/A'}</td>
                    <td class="px-4 py-2 border text-center">${item.quantity_requested}</td>
                    <td class="px-4 py-2 border text-center">${item.quantity_approved || 0}</td>
                    <td class="px-4 py-2 border text-right">$${parseFloat(item.estimated_value || 0).toFixed(2)}</td>
                </tr>
            `).join('');
            
            const content = `
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <h4 class="text-xl font-bold">Request #${String(request.id).padStart(4, '0')}</h4>
                        <span class="px-3 py-1 text-sm font-semibold rounded-full ${statusClass}">
                            ${request.status.charAt(0).toUpperCase() + request.status.slice(1)}
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h5 class="font-semibold mb-3">Customer Information</h5>
                            <div class="space-y-2">
                                <p><strong>Name:</strong> ${request.customer_name}</p>
                                <p><strong>Type:</strong> ${request.customer_type}</p>
                                <p><strong>Email:</strong> ${request.customer_email}</p>
                                <p><strong>Phone:</strong> ${request.customer_phone}</p>
                            </div>
                        </div>
                        
                        <div>
                            <h5 class="font-semibold mb-3">Request Information</h5>
                            <div class="space-y-2">
                                <p><strong>Employee:</strong> ${request.employee_name}</p>
                                <p><strong>Location:</strong> ${request.location_name}, ${request.location_city}</p>
                                <p><strong>Request Date:</strong> ${new Date(request.request_date).toLocaleString()}</p>
                                <p><strong>Required Date:</strong> ${new Date(request.required_date).toLocaleString()}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h5 class="font-semibold mb-2">Purpose</h5>
                        <p class="text-gray-700">${request.purpose}</p>
                    </div>
                    
                    ${request.notes ? `
                        <div>
                            <h5 class="font-semibold mb-2">Notes</h5>
                            <p class="text-gray-700">${request.notes}</p>
                        </div>
                    ` : ''}
                    
                    ${request.approved_by_name ? `
                        <div>
                            <h5 class="font-semibold mb-2">Approval Information</h5>
                            <p><strong>Approved by:</strong> ${request.approved_by_name}</p>
                            <p><strong>Approved on:</strong> ${new Date(request.approved_date).toLocaleString()}</p>
                        </div>
                    ` : ''}
                    
                    <div>
                        <h5 class="font-semibold mb-3">Requested Items</h5>
                        <table class="w-full border-collapse border border-gray-300">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-4 py-2 border text-left">Type</th>
                                    <th class="px-4 py-2 border text-left">Description</th>
                                    <th class="px-4 py-2 border text-center">Requested</th>
                                    <th class="px-4 py-2 border text-center">Approved</th>
                                    <th class="px-4 py-2 border text-right">Est. Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${itemsHtml}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            document.getElementById('request-details-content').innerHTML = content;
            openModal('view-request-modal');
        }

        async function editRequest(id) {
            try {
                const response = await fetch(`api/borrowing-requests.php?action=get&id=${id}`);
                const result = await response.json();
                
                if (result.success) {
                    populateEditForm(result.data);
                    editingRequestId = id;
                    openModal('edit-request-modal');
                } else {
                    showNotification(result.message || 'Failed to load request details', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            }
        }

        function populateEditForm(request) {
            // Populate form fields with request data
            document.getElementById('edit-request-id').value = request.id;
            // Add more field population as needed
        }

        async function approveRequest(id) {
            if (!confirm('Are you sure you want to approve this borrowing request?')) {
                return;
            }

            try {
                console.log('Approving request ID:', id);
                
                const response = await fetch('api/borrowing-requests.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'approve',
                        id: id
                    })
                });

                console.log('Approve response status:', response.status);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();
                console.log('Approve response result:', result);
                
                if (result.success) {
                    showNotification('Request approved successfully!', 'success');
                    location.reload();
                } else {
                    showNotification(result.message || 'Failed to approve request', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred: ' + error.message, 'error');
            }
        }

        async function rejectRequest(id) {
            const notes = prompt('Please provide a reason for rejection (optional):');
            if (notes === null) return; // User cancelled

            try {
                console.log('Rejecting request ID:', id, 'with notes:', notes);
                
                const response = await fetch('api/borrowing-requests.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'reject',
                        id: id,
                        notes: notes
                    })
                });

                console.log('Reject response status:', response.status);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();
                console.log('Reject response result:', result);
                
                if (result.success) {
                    showNotification('Request rejected successfully!', 'success');
                    location.reload();
                } else {
                    showNotification(result.message || 'Failed to reject request', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred: ' + error.message, 'error');
            }
        }

        async function deleteRequest(id) {
            if (!confirm('Are you sure you want to delete this borrowing request? This action cannot be undone.')) {
                return;
            }

            try {
                console.log('Deleting request ID:', id);
                
                const response = await fetch('api/borrowing-requests.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'delete',
                        id: id
                    })
                });

                console.log('Delete response status:', response.status);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();
                console.log('Delete response result:', result);
                
                if (result.success) {
                    showNotification('Request deleted successfully!', 'success');
                    location.reload();
                } else {
                    showNotification(result.message || 'Failed to delete request', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred: ' + error.message, 'error');
            }
        }

        async function handleBulkAction(action) {
            if (!action) return;
            
            const selectedItems = document.querySelectorAll('.request-checkbox:checked');
            if (selectedItems.length === 0) {
                showNotification('Please select at least one request', 'warning');
                return;
            }

            const selectedIds = Array.from(selectedItems).map(item => item.dataset.id);

            switch (action) {
                case 'delete':
                    await bulkDeleteRequests(selectedIds);
                    break;
                case 'export':
                    await exportRequests(selectedIds);
                    break;
            }
            
            // Reset the select dropdown
            document.querySelector('select').value = '';
        }

        async function bulkDeleteRequests(requestIds) {
            if (!confirm(`Are you sure you want to delete ${requestIds.length} borrowing requests? This action cannot be undone.`)) {
                return;
            }

            try {
                const response = await fetch('api/borrowing-requests.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'bulk_delete',
                        ids: requestIds
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    showNotification(result.message, 'success');
                    location.reload();
                } else {
                    showNotification(result.message || 'Failed to delete requests', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            }
        }

        async function exportRequests(requestIds = null) {
            const idsParam = requestIds ? `&request_ids=${requestIds.join(',')}` : '';
            const url = `api/borrowing-requests.php?action=export&format=csv${idsParam}`;
            
            try {
                window.open(url, '_blank');
                showNotification('Export started successfully!', 'success');
            } catch (error) {
                console.error('Error:', error);
                showNotification('Failed to export data', 'error');
            }
        }

        // Item types for JavaScript use
        const itemTypes = <?php echo json_encode($filterOptions['item_types']); ?>;
        
        // Item management functions
        function addItemRow() {
            const container = document.getElementById('items-container');
            const newRow = document.createElement('div');
            newRow.className = 'item-row grid grid-cols-1 md:grid-cols-5 gap-2 mb-2';
            
            // Build options for item types
            let optionsHtml = '<option value="">Select Type</option>';
            itemTypes.forEach(type => {
                optionsHtml += `<option value="${type.id}">${type.name}</option>`;
            });
            
            newRow.innerHTML = `
                <div>
                    <select name="items[${itemRowCounter}][item_type_id]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        ${optionsHtml}
                    </select>
                </div>
                <div>
                    <input type="text" name="items[${itemRowCounter}][item_description]" placeholder="Item description" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <input type="number" name="items[${itemRowCounter}][quantity_requested]" placeholder="Quantity" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <input type="number" name="items[${itemRowCounter}][estimated_value]" placeholder="Est. Value" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <button type="button" onclick="removeItemRow(this)" class="w-full px-3 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            container.appendChild(newRow);
            itemRowCounter++;
        }

        function removeItemRow(button) {
            const container = document.getElementById('items-container');
            if (container.children.length > 1) {
                button.closest('.item-row').remove();
            } else {
                showNotification('At least one item row is required', 'warning');
            }
        }

        // Form submission handlers
        document.getElementById('add-request-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            console.log('Form submitted');
            
            const formData = new FormData(this);
            console.log('Form data entries:');
            for (let [key, value] of formData.entries()) {
                console.log(key, value);
            }
            
            const requestData = {
                action: 'create',
                customer_id: formData.get('customer_id'),
                employee_id: formData.get('employee_id'),
                location_id: formData.get('location_id'),
                required_date: formData.get('required_date'),
                purpose: formData.get('purpose'),
                notes: formData.get('notes'),
                items: []
            };
            
            console.log('Initial request data:', requestData);

            // Collect items data
            const itemRows = document.querySelectorAll('.item-row');
            console.log('Found item rows:', itemRows.length);
            
            itemRows.forEach((row, index) => {
                // Try different ways to find the inputs in case the naming is off
                const itemTypeSelect = row.querySelector('select[name*="item_type_id"]');
                const descriptionInput = row.querySelector('input[name*="item_description"]');
                const quantityInput = row.querySelector('input[name*="quantity_requested"]');
                const valueInput = row.querySelector('input[name*="estimated_value"]');
                
                const itemTypeId = itemTypeSelect?.value || '';
                const description = descriptionInput?.value || '';
                const quantity = quantityInput?.value || '';
                const value = valueInput?.value || '';
                
                console.log(`Item ${index}:`, {
                    itemTypeId,
                    description,
                    quantity,
                    value
                });
                
                if (description && quantity) {
                    requestData.items.push({
                        item_type_id: itemTypeId || null,
                        item_description: description,
                        quantity_requested: parseInt(quantity),
                        estimated_value: parseFloat(value) || 0
                    });
                }
            });
            
            console.log('Final request data:', requestData);

            try {
                const response = await fetch('api/borrowing-requests.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(requestData)
                });

                console.log('Response status:', response.status);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();
                console.log('Response result:', result);
                
                if (result.success) {
                    showNotification('Borrowing request created successfully!', 'success');
                    closeModal('add-request-modal');
                    location.reload();
                } else {
                    showNotification(result.message || 'Failed to create request', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred: ' + error.message, 'error');
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
