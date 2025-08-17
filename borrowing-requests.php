<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Check remember me token
checkRememberMe();

// Require login
requireLogin();

$currentAdmin = getLoggedInAdmin();

// Define quick actions for this page
$quickActions = [
    [
        'text' => 'New Request',
        'icon' => 'fas fa-plus',
        'class' => 'btn-primary',
        'onclick' => 'openModal(\'new-request-modal\')'
    ],
    [
        'text' => 'Export Requests',
        'icon' => 'fas fa-download',
        'class' => 'btn-secondary',
        'onclick' => 'exportRequests()'
    ]
];

// Get borrowing requests statistics
function getRequestStats($pdo) {
    $stats = [];
    
    // Total requests
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM Borrowing_Request");
    $stats['total_requests'] = $stmt->fetch()['total'] ?? 0;
    
    // Pending requests
    $stmt = $pdo->query("SELECT COUNT(*) as pending FROM Borrowing_Request WHERE status = 'pending'");
    $stats['pending_requests'] = $stmt->fetch()['pending'] ?? 0;
    
    // Active requests
    $stmt = $pdo->query("SELECT COUNT(*) as active FROM Borrowing_Request WHERE status = 'active'");
    $stats['active_requests'] = $stmt->fetch()['active'] ?? 0;
    
    // Overdue requests
    $stmt = $pdo->query("SELECT COUNT(*) as overdue FROM Borrowing_Request WHERE status = 'overdue'");
    $stats['overdue_requests'] = $stmt->fetch()['overdue'] ?? 0;
    
    // Today's requests
    $stmt = $pdo->query("SELECT COUNT(*) as today FROM Borrowing_Request WHERE DATE(request_date) = CURDATE()");
    $stats['today_requests'] = $stmt->fetch()['today'] ?? 0;
    
    // This week's requests
    $stmt = $pdo->query("SELECT COUNT(*) as week FROM Borrowing_Request WHERE WEEK(request_date) = WEEK(NOW()) AND YEAR(request_date) = YEAR(NOW())");
    $stats['week_requests'] = $stmt->fetch()['week'] ?? 0;
    
    return $stats;
}

// Get borrowing requests with filters and pagination
function getBorrowingRequests($pdo, $page = 1, $limit = 10, $filters = []) {
    $offset = ($page - 1) * $limit;
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if (!empty($filters['status'])) {
        $whereClause .= " AND br.status = :status";
        $params['status'] = $filters['status'];
    }
    
    if (!empty($filters['search'])) {
        $whereClause .= " AND (c.name LIKE :search OR e.name LIKE :search OR br.purpose LIKE :search OR br.id = :search_id)";
        $params['search'] = "%" . $filters['search'] . "%";
        $params['search_id'] = is_numeric($filters['search']) ? $filters['search'] : 0;
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
                     c.name as customer_name, c.email as customer_email,
                     e.name as employee_name, e.employee_id,
                     l.name as location_name,
                     a.name as approved_by_name,
                     COUNT(bi.id) as total_items,
                     SUM(bi.quantity_requested) as total_quantity
              FROM Borrowing_Request br 
              LEFT JOIN Customer c ON br.customer_id = c.id 
              LEFT JOIN Employee e ON br.employee_id = e.id 
              LEFT JOIN Location l ON br.location_id = l.id
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
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get customers for dropdown
function getCustomers($pdo) {
    $stmt = $pdo->query("SELECT id, name, email FROM Customer WHERE status = 'active' ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get employees for dropdown
function getEmployees($pdo) {
    $stmt = $pdo->query("SELECT id, name, employee_id FROM Employee WHERE status = 'active' ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get locations for dropdown
function getLocations($pdo) {
    $stmt = $pdo->query("SELECT id, name FROM Location ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get materials for request creation
function getMaterials($pdo) {
    $stmt = $pdo->query("SELECT m.id, m.name, m.unit, mc.name as category_name, 
                                COALESCE(SUM(i.quantity), 0) as available_quantity
                         FROM Material m 
                         LEFT JOIN Material_Categories mc ON m.category_id = mc.id
                         LEFT JOIN Inventory i ON m.id = i.material_id
                         WHERE m.status = 'active'
                         GROUP BY m.id
                         ORDER BY m.name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get current filter values
$filters = [
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? '',
    'customer_id' => $_GET['customer_id'] ?? '',
    'location_id' => $_GET['location_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

$stats = getRequestStats($pdo);
$requests = getBorrowingRequests($pdo, 1, 20, $filters);
$customers = getCustomers($pdo);
$employees = getEmployees($pdo);
$locations = getLocations($pdo);
$materials = getMaterials($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrowing Requests - Warehouse Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#2563eb',
                        secondary: '#1e40af',
                        accent: '#3b82f6',
                        dark: '#1f2937',
                        light: '#f8fafc'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-light">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="lg:ml-64">
        <?php include 'includes/navbar.php'; ?>
        
        <main class="p-6">
            <!-- Page Header -->
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">Borrowing Requests</h1>
                        <p class="text-gray-600 mt-2">Manage material borrowing requests and approvals</p>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-clipboard-list text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Requests</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['total_requests']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                            <i class="fas fa-clock text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Pending</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['pending_requests']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-check-circle text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Active</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['active_requests']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-red-100 text-red-600">
                            <i class="fas fa-exclamation-triangle text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Overdue</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['overdue_requests']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex flex-wrap items-center gap-4">
                    <!-- Search -->
                    <div class="flex-1 min-w-64">
                        <div class="relative">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                            <input type="text" id="search" placeholder="Search by ID, customer, employee, or purpose..." 
                                   value="<?php echo htmlspecialchars($filters['search']); ?>"
                                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                    </div>

                    <!-- Status Filter -->
                    <select id="status-filter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $filters['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $filters['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="returned" <?php echo $filters['status'] === 'returned' ? 'selected' : ''; ?>>Returned</option>
                        <option value="overdue" <?php echo $filters['status'] === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                    </select>

                    <!-- Customer Filter -->
                    <select id="customer-filter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        <option value="">All Customers</option>
                        <?php foreach ($customers as $customer): ?>
                        <option value="<?php echo $customer['id']; ?>" <?php echo $filters['customer_id'] == $customer['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($customer['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Location Filter -->
                    <select id="location-filter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        <option value="">All Locations</option>
                        <?php foreach ($locations as $location): ?>
                        <option value="<?php echo $location['id']; ?>" <?php echo $filters['location_id'] == $location['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($location['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Date Range -->
                    <input type="date" id="date-from" value="<?php echo htmlspecialchars($filters['date_from']); ?>"
                           class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    <span class="text-gray-500">to</span>
                    <input type="date" id="date-to" value="<?php echo htmlspecialchars($filters['date_to']); ?>"
                           class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">

                    <!-- Filter Actions -->
                    <button onclick="applyFilters()" class="btn btn-primary">
                        <i class="fas fa-filter mr-2"></i>Filter
                    </button>
                    <button onclick="clearFilters()" class="btn btn-secondary">
                        <i class="fas fa-times mr-2"></i>Clear
                    </button>
                </div>
            </div>

            <!-- Requests Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">Borrowing Requests</h3>
                    <div class="flex items-center space-x-2">
                        <div class="relative">
                            <select onchange="handleBulkAction(this.value)" class="pr-8 pl-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="">Bulk Actions</option>
                                <option value="approve">Approve Selected</option>
                                <option value="reject">Reject Selected</option>
                                <option value="export">Export Selected</option>
                                <option value="delete">Delete Selected</option>
                            </select>
                        </div>
                        <span class="text-sm text-gray-500"><?php echo count($requests); ?> requests</span>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table id="requests-table" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left">
                                    <input type="checkbox" id="select-all" class="rounded border-gray-300">
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="requests-table-body">
                            <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-clipboard-list text-4xl text-gray-300 mb-2"></i>
                                        <p class="text-lg font-medium">No borrowing requests found</p>
                                        <p class="text-sm">Create a new request to get started.</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($requests as $request): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <input type="checkbox" class="rounded border-gray-300 request-checkbox" data-id="<?php echo $request['id']; ?>">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="h-10 w-10 rounded-lg bg-gradient-to-r from-blue-400 to-blue-600 flex items-center justify-center text-white font-semibold">
                                                <i class="fas fa-file-alt"></i>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">#<?php echo $request['id']; ?></div>
                                                <div class="text-sm text-gray-500"><?php echo date('M j, Y', strtotime($request['request_date'])); ?></div>
                                                <?php if ($request['required_date']): ?>
                                                <div class="text-xs text-gray-400">Due: <?php echo date('M j, Y', strtotime($request['required_date'])); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($request['customer_name']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($request['customer_email']); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($request['employee_name']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($request['employee_id']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo $request['total_items']; ?> items</div>
                                        <div class="text-sm text-gray-500"><?php echo number_format($request['total_quantity']); ?> total qty</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($request['location_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $statusColors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'approved' => 'bg-blue-100 text-blue-800',
                                            'rejected' => 'bg-red-100 text-red-800',
                                            'active' => 'bg-green-100 text-green-800',
                                            'returned' => 'bg-gray-100 text-gray-800',
                                            'overdue' => 'bg-red-100 text-red-800'
                                        ];
                                        $statusIcons = [
                                            'pending' => 'fa-clock',
                                            'approved' => 'fa-check',
                                            'rejected' => 'fa-times',
                                            'active' => 'fa-play',
                                            'returned' => 'fa-undo',
                                            'overdue' => 'fa-exclamation-triangle'
                                        ];
                                        ?>
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusColors[$request['status']]; ?>">
                                            <i class="fas <?php echo $statusIcons[$request['status']]; ?> mr-1"></i>
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button onclick="viewRequest(<?php echo $request['id']; ?>)" class="text-blue-600 hover:text-blue-900" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button onclick="editRequest(<?php echo $request['id']; ?>)" class="text-green-600 hover:text-green-900" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($request['status'] === 'pending'): ?>
                                            <button onclick="approveRequest(<?php echo $request['id']; ?>)" class="text-green-600 hover:text-green-900" title="Approve">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button onclick="rejectRequest(<?php echo $request['id']; ?>)" class="text-red-600 hover:text-red-900" title="Reject">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            <?php endif; ?>
                                            <?php if ($request['status'] === 'active'): ?>
                                            <button onclick="processReturn(<?php echo $request['id']; ?>)" class="text-purple-600 hover:text-purple-900" title="Process Return">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                            <?php endif; ?>
                                            <button onclick="deleteRequest(<?php echo $request['id']; ?>)" class="text-red-600 hover:text-red-900" title="Delete">
                                                <i class="fas fa-trash"></i>
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
                <div id="pagination" class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200">
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
                                Showing <span class="font-medium">1</span> to <span class="font-medium"><?php echo count($requests); ?></span> of 
                                <span class="font-medium"><?php echo $stats['total_requests']; ?></span> results
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

    <!-- New Request Modal -->
    <div id="new-request-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('new-request-modal')"></div>
        <div class="modal-content max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Create New Borrowing Request</h3>
                <button onclick="closeModal('new-request-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="new-request-form">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Customer*</label>
                        <select name="customer_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select Customer</option>
                            <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>"><?php echo htmlspecialchars($customer['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Employee*</label>
                        <select name="employee_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $employee): ?>
                            <option value="<?php echo $employee['id']; ?>"><?php echo htmlspecialchars($employee['name']) . ' (' . $employee['employee_id'] . ')'; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Location*</label>
                        <select name="location_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select Location</option>
                            <?php foreach ($locations as $location): ?>
                            <option value="<?php echo $location['id']; ?>"><?php echo htmlspecialchars($location['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Required Date</label>
                        <input type="date" name="required_date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Purpose*</label>
                    <textarea name="purpose" required rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="Describe the purpose of this borrowing request..."></textarea>
                </div>
                
                <!-- Materials Selection -->
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <label class="block text-sm font-medium text-gray-700">Materials*</label>
                        <button type="button" onclick="addMaterialRow()" class="btn btn-secondary btn-sm">
                            <i class="fas fa-plus mr-2"></i>Add Material
                        </button>
                    </div>
                    
                    <div id="materials-container">
                        <div class="material-row grid grid-cols-12 gap-2 items-end mb-2">
                            <div class="col-span-5">
                                <select name="materials[0][material_id]" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <option value="">Select Material</option>
                                    <?php foreach ($materials as $material): ?>
                                    <option value="<?php echo $material['id']; ?>" data-unit="<?php echo $material['unit']; ?>" data-available="<?php echo $material['available_quantity']; ?>">
                                        <?php echo htmlspecialchars($material['name']); ?> (Available: <?php echo $material['available_quantity']; ?> <?php echo $material['unit']; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-span-3">
                                <input type="number" name="materials[0][quantity]" min="1" required placeholder="Quantity" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                            <div class="col-span-2">
                                <input type="text" readonly class="unit-display w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-gray-50" placeholder="Unit">
                            </div>
                            <div class="col-span-2">
                                <button type="button" onclick="removeMaterialRow(this)" class="btn btn-danger btn-sm w-full">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('new-request-modal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Request</button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/dashboard.js"></script>
    <script src="js/borrowing-requests.js"></script>
</body>
</html>
