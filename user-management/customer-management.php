<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check remember me token
checkRememberMe();
 
requireLogin();

// Check permission for customer management
if (!hasPermission('customer_management')) {
    header('Location: ../index.php');
    exit();
}

$currentAdmin = getLoggedInAdmin();

// Define quick actions for this page
$quickActions = [
    [
        'text' => 'Add Customer',
        'icon' => 'fas fa-plus',
        'class' => 'btn-primary',
        'onclick' => 'openModal(\'add-customer-modal\')'
    ]
];

// Get customer statistics
function getCustomerStats($pdo) {
    $stats = [];
    
    // Total customers
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM Customer");
    $stats['total_customers'] = $stmt->fetch()['total'] ?? 0;
    
    // Active customers
    $stmt = $pdo->query("SELECT COUNT(*) as active FROM Customer WHERE status = 'active'");
    $stats['active_customers'] = $stmt->fetch()['active'] ?? 0;
    
    // VIP customers
    $stmt = $pdo->query("SELECT COUNT(*) as vip FROM Customer WHERE status = 'vip'");
    $stats['vip_customers'] = $stmt->fetch()['vip'] ?? 0;
    
    // New customers this month
    $stmt = $pdo->query("SELECT COUNT(*) as new_customers FROM Customer WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats['new_this_month'] = $stmt->fetch()['new_customers'] ?? 0;
    
    return $stats;
}

// Get customers with pagination and filters
function getCustomers($pdo, $page = 1, $limit = 10, $filters = []) {
    $offset = ($page - 1) * $limit;
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if (!empty($filters['search'])) {
        $whereClause .= " AND (name LIKE :search OR email LIKE :search OR phone LIKE :search)";
        $params['search'] = "%" . $filters['search'] . "%";
    }
    
    if (!empty($filters['type'])) {
        $whereClause .= " AND customer_type = :type";
        $params['type'] = $filters['type'];
    }
    
    if (!empty($filters['status'])) {
        $whereClause .= " AND status = :status";
        $params['status'] = $filters['status'];
    }
    
    if (!empty($filters['location'])) {
        $whereClause .= " AND location_type = :location";
        $params['location'] = $filters['location'];
    }
    
    $query = "SELECT * FROM Customer $whereClause ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
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
    'search' => $_GET['search'] ?? '',
    'type' => $_GET['type'] ?? '',
    'status' => $_GET['status'] ?? '',
    'location' => $_GET['location'] ?? ''
];

$page = (int)($_GET['page'] ?? 1);
$limit = 10;

// Get data
$stats = getCustomerStats($pdo);
$customers = getCustomers($pdo, $page, $limit, $filters);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management - Warehouse Admin</title>
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
                            <i class="fas fa-handshake text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-sm font-medium text-gray-600">Total Customers</h2>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_customers']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-check-circle text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-sm font-medium text-gray-600">Active Customers</h2>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['active_customers']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                            <i class="fas fa-star text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-sm font-medium text-gray-600">VIP Customers</h2>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['vip_customers']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <i class="fas fa-user-plus text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-sm font-medium text-gray-600">New This Month</h2>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['new_this_month']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <form method="GET" action="">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                            <div class="relative">
                                <input type="text" name="search" placeholder="Search customers..." 
                                       value="<?php echo htmlspecialchars($filters['search']); ?>"
                                       class="form-input pl-10" id="search-input">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center">
                                    <i class="fas fa-search text-gray-400"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Customer Type</label>
                            <select name="type" class="form-select" id="type-filter">
                                <option value="">All Types</option>
                                <option value="retail" <?php echo $filters['type'] === 'retail' ? 'selected' : ''; ?>>Retail</option>
                                <option value="wholesale" <?php echo $filters['type'] === 'wholesale' ? 'selected' : ''; ?>>Wholesale</option>
                                <option value="corporate" <?php echo $filters['type'] === 'corporate' ? 'selected' : ''; ?>>Corporate</option>
                                <option value="government" <?php echo $filters['type'] === 'government' ? 'selected' : ''; ?>>Government</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select name="status" class="form-select" id="status-filter">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $filters['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="vip" <?php echo $filters['status'] === 'vip' ? 'selected' : ''; ?>>VIP</option>
                                <option value="suspended" <?php echo $filters['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Location</label>
                            <select name="location" class="form-select" id="location-filter">
                                <option value="">All Locations</option>
                                <option value="local" <?php echo $filters['location'] === 'local' ? 'selected' : ''; ?>>Local</option>
                                <option value="regional" <?php echo $filters['location'] === 'regional' ? 'selected' : ''; ?>>Regional</option>
                                <option value="national" <?php echo $filters['location'] === 'national' ? 'selected' : ''; ?>>National</option>
                                <option value="international" <?php echo $filters['location'] === 'international' ? 'selected' : ''; ?>>International</option>
                            </select>
                        </div>
                        
                        <div class="flex items-end space-x-2">
                            <button type="submit" class="btn btn-primary flex-1">
                                <i class="fas fa-search mr-2"></i>Search
                            </button>
                            <a href="customer-management.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Customers Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">Customer Directory</h3>
                    <div class="flex items-center space-x-2">
                        <button onclick="exportCustomers()" class="text-blue-600 hover:text-blue-800 text-sm">
                            <i class="fas fa-download mr-1"></i>Export
                        </button>
                        <div class="border-l border-gray-300 h-4"></div>
                        <select onchange="handleBulkAction(this.value)" class="text-sm border-gray-300 rounded">
                            <option value="">Bulk Actions</option>
                            <option value="activate">Activate Selected</option>
                            <option value="deactivate">Deactivate Selected</option>
                            <option value="update-type">Update Type</option>
                            <option value="send-newsletter">Send Newsletter</option>
                        </select>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <input type="checkbox" class="rounded border-gray-300" id="select-all">
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Join Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="customers-table-body">
                            <?php if (empty($customers)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                                    No customers found
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($customers as $customer): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <input type="checkbox" class="rounded border-gray-300 customer-checkbox" data-id="<?php echo $customer['id']; ?>">
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <img class="h-10 w-10 rounded-full" src="https://ui-avatars.com/api/?name=<?php echo urlencode($customer['name']); ?>&background=3B82F6&color=fff" alt="">
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($customer['name']); ?></div>
                                            <div class="text-sm text-gray-500">Customer ID: #C<?php echo str_pad($customer['id'], 3, '0', STR_PAD_LEFT); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($customer['email']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($customer['phone']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $typeColors = [
                                        'retail' => 'bg-yellow-100 text-yellow-800',
                                        'wholesale' => 'bg-green-100 text-green-800',
                                        'corporate' => 'bg-blue-100 text-blue-800',
                                        'government' => 'bg-red-100 text-red-800'
                                    ];
                                    $typeColor = $typeColors[$customer['customer_type']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $typeColor; ?>">
                                        <?php echo ucfirst($customer['customer_type']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($customer['address'] ?? ucfirst($customer['location_type'])); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $statusClasses = [
                                        'active' => 'badge-active',
                                        'inactive' => 'badge-inactive',
                                        'vip' => 'badge-vip',
                                        'suspended' => 'badge-warning'
                                    ];
                                    $statusClass = $statusClasses[$customer['status']] ?? 'badge-inactive';
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst($customer['status']); ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo date('M j, Y', strtotime($customer['created_at'])); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <button onclick="editCustomer(<?php echo $customer['id']; ?>)" class="text-green-600 hover:text-green-900" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="viewOrders(<?php echo $customer['id']; ?>)" class="text-purple-600 hover:text-purple-900" title="View Orders">
                                            <i class="fas fa-box"></i>
                                        </button>
                                        <button onclick="deleteCustomer(<?php echo $customer['id']; ?>)" class="text-orange-600 hover:text-orange-900" title="Delete Customer">
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
                <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200">
                    <div class="flex-1 flex justify-between sm:hidden">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($filters); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Previous
                        </a>
                        <?php endif; ?>
                        
                        <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($filters); ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Next
                        </a>
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Showing <span class="font-medium"><?php echo ($page - 1) * $limit + 1; ?></span> to 
                                <span class="font-medium"><?php echo min($page * $limit, $stats['total_customers']); ?></span> of 
                                <span class="font-medium"><?php echo $stats['total_customers']; ?></span> results
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($filters); ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php
                                $totalPages = ceil($stats['total_customers'] / $limit);
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                
                                for ($i = $startPage; $i <= $endPage; $i++):
                                ?>
                                <a href="?page=<?php echo $i; ?>&<?php echo http_build_query($filters); ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 <?php echo $i === $page ? 'bg-primary text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> text-sm font-medium">
                                    <?php echo $i; ?>
                                </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($filters); ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
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

    <!-- Add Customer Modal -->
    <div id="add-customer-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('add-customer-modal')"></div>
        <div class="modal-content max-w-3xl">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Add New Customer</h3>
                <button onclick="closeModal('add-customer-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="add-customer-form" action="../api/customers.php?action=create" method="POST">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Company/Customer Name *</label>
                        <input type="text" name="name" class="form-input" placeholder="Enter company or customer name" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Customer ID</label>
                        <input type="text" name="customer_id" class="form-input" placeholder="Auto-generated" readonly>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                        <input type="email" name="email" class="form-input" placeholder="contact@company.com" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number *</label>
                        <input type="tel" name="phone" class="form-input" placeholder="+1 555 123 4567" required>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Customer Type *</label>
                        <select name="customer_type" class="form-select" required>
                            <option value="">Select Type</option>
                            <option value="retail">Retail</option>
                            <option value="wholesale">Wholesale</option>
                            <option value="corporate">Corporate</option>
                            <option value="government">Government</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Location *</label>
                        <select name="location" class="form-select" required>
                            <option value="">Select Location</option>
                            <option value="local">Local</option>
                            <option value="regional">Regional</option>
                            <option value="national">National</option>
                            <option value="international">International</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Contact Person</label>
                        <input type="text" name="contact_person" class="form-input" placeholder="Primary contact name">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Alternative Phone</label>
                        <input type="tel" name="alt_phone" class="form-input" placeholder="+1 555 123 4567">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Credit Limit</label>
                        <input type="number" name="credit_limit" class="form-input" placeholder="0.00" step="0.01">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment Terms</label>
                        <select name="payment_terms" class="form-select">
                            <option value="net-30">Net 30 Days</option>
                            <option value="net-60">Net 60 Days</option>
                            <option value="cod">Cash on Delivery</option>
                            <option value="advance">Advance Payment</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Billing Address</label>
                    <textarea name="billing_address" rows="3" class="form-textarea" placeholder="Enter complete billing address"></textarea>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Shipping Address</label>
                    <div class="flex items-center mb-2">
                        <input type="checkbox" id="same-as-billing" class="rounded border-gray-300 mr-2">
                        <label for="same-as-billing" class="text-sm text-gray-600">Same as billing address</label>
                    </div>
                    <textarea name="shipping_address" rows="3" class="form-textarea" placeholder="Enter complete shipping address"></textarea>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Order Preferences</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center">
                            <input type="checkbox" name="preferences[]" value="priority_shipping" class="rounded border-gray-300 mr-2">
                            <span class="text-sm">Priority Shipping</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="preferences[]" value="bulk_orders" class="rounded border-gray-300 mr-2">
                            <span class="text-sm">Bulk Orders</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="preferences[]" value="email_notifications" class="rounded border-gray-300 mr-2">
                            <span class="text-sm">Email Notifications</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="preferences[]" value="sms_alerts" class="rounded border-gray-300 mr-2">
                            <span class="text-sm">SMS Alerts</span>
                        </label>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('add-customer-modal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Customer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Customer Modal -->
    <div id="edit-customer-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('edit-customer-modal')"></div>
        <div class="modal-content max-w-3xl">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Edit Customer</h3>
                <button onclick="closeModal('edit-customer-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="edit-customer-form">
                <input type="hidden" name="id" id="edit-customer-id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Company/Customer Name *</label>
                        <input type="text" name="name" id="edit-name" class="form-input" placeholder="Enter company or customer name" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Customer ID</label>
                        <input type="text" id="edit-customer-display-id" class="form-input" placeholder="Auto-generated" readonly>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                        <input type="email" name="email" id="edit-email" class="form-input" placeholder="contact@company.com" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number *</label>
                        <input type="tel" name="phone" id="edit-phone" class="form-input" placeholder="+1 555 123 4567" required>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Customer Type *</label>
                        <select name="customer_type" id="edit-customer-type" class="form-select" required>
                            <option value="">Select Type</option>
                            <option value="retail">Retail</option>
                            <option value="wholesale">Wholesale</option>
                            <option value="corporate">Corporate</option>
                            <option value="government">Government</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Location *</label>
                        <select name="location" id="edit-location" class="form-select" required>
                            <option value="">Select Location</option>
                            <option value="local">Local</option>
                            <option value="regional">Regional</option>
                            <option value="national">National</option>
                            <option value="international">International</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Contact Person</label>
                        <input type="text" name="contact_person" id="edit-contact-person" class="form-input" placeholder="Primary contact name">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Alternative Phone</label>
                        <input type="tel" name="alt_phone" id="edit-alt-phone" class="form-input" placeholder="+1 555 123 4567">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Credit Limit</label>
                        <input type="number" name="credit_limit" id="edit-credit-limit" class="form-input" placeholder="0.00" step="0.01">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment Terms</label>
                        <select name="payment_terms" id="edit-payment-terms" class="form-select">
                            <option value="net-30">Net 30 Days</option>
                            <option value="net-60">Net 60 Days</option>
                            <option value="cod">Cash on Delivery</option>
                            <option value="advance">Advance Payment</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" id="edit-status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="vip">VIP</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                        <input type="text" name="address" id="edit-address" class="form-input" placeholder="Customer address">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Billing Address</label>
                    <textarea name="billing_address" id="edit-billing-address" rows="3" class="form-textarea" placeholder="Enter complete billing address"></textarea>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Shipping Address</label>
                    <div class="flex items-center mb-2">
                        <input type="checkbox" id="edit-same-as-billing" class="rounded border-gray-300 mr-2">
                        <label for="edit-same-as-billing" class="text-sm text-gray-600">Same as billing address</label>
                    </div>
                    <textarea name="shipping_address" id="edit-shipping-address" rows="3" class="form-textarea" placeholder="Enter complete shipping address"></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('edit-customer-modal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Customer</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/dashboard.js"></script>
    <script src="../js/customer-management.js"></script>
    <script>
        // PHP backend integration
        const API_BASE = '../api/customers.php';
        
        // Handle form submission
        document.getElementById('add-customer-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            
            try {
                const response = await fetch(`${API_BASE}?action=create`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Customer added successfully', 'success');
                    closeModal('add-customer-modal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.error || 'Failed to add customer', 'error');
                }
            } catch (error) {
                showNotification('Network error occurred', 'error');
            }
        });
        
        // Handle edit form submission
        document.getElementById('edit-customer-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            
            try {
                const response = await fetch(`${API_BASE}?action=update`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Customer updated successfully', 'success');
                    closeModal('edit-customer-modal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.error || 'Failed to update customer', 'error');
                }
            } catch (error) {
                showNotification('Network error occurred', 'error');
            }
        });
        
        // Handle same as billing address checkbox
        document.getElementById('edit-same-as-billing').addEventListener('change', function() {
            const billingAddress = document.getElementById('edit-billing-address').value;
            const shippingAddress = document.getElementById('edit-shipping-address');
            
            if (this.checked) {
                shippingAddress.value = billingAddress;
                shippingAddress.readOnly = true;
            } else {
                shippingAddress.readOnly = false;
            }
        });
        
        // Populate edit form with customer data
        function populateEditForm(customer) {
            document.getElementById('edit-customer-id').value = customer.id;
            document.getElementById('edit-customer-display-id').value = `#C${customer.id.toString().padStart(3, '0')}`;
            document.getElementById('edit-name').value = customer.name || '';
            document.getElementById('edit-email').value = customer.email || '';
            document.getElementById('edit-phone').value = customer.phone || '';
            document.getElementById('edit-customer-type').value = customer.customer_type || '';
            document.getElementById('edit-location').value = customer.location_type || '';
            document.getElementById('edit-contact-person').value = customer.contact_person || '';
            document.getElementById('edit-alt-phone').value = customer.alt_phone || '';
            document.getElementById('edit-credit-limit').value = customer.credit_limit || '';
            document.getElementById('edit-payment-terms').value = customer.payment_terms || '';
            document.getElementById('edit-status').value = customer.status || '';
            document.getElementById('edit-address').value = customer.address || '';
            document.getElementById('edit-billing-address').value = customer.billing_address || '';
            document.getElementById('edit-shipping-address').value = customer.shipping_address || '';
            
            // Check if shipping address is same as billing
            if (customer.billing_address && customer.shipping_address === customer.billing_address) {
                document.getElementById('edit-same-as-billing').checked = true;
                document.getElementById('edit-shipping-address').readOnly = true;
            }
        }
        
        // Show customer details modal
        function showCustomerModal(customer) {
            // Create a simple customer details modal
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-overlay" onclick="this.parentElement.remove()"></div>
                <div class="modal-content max-w-2xl">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-800">Customer Details</h3>
                        <button onclick="this.closest('.modal').remove()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="font-medium text-gray-700 mb-3">Basic Information</h4>
                            <div class="space-y-2">
                                <p><span class="font-medium">Name:</span> ${customer.name}</p>
                                <p><span class="font-medium">Email:</span> ${customer.email}</p>
                                <p><span class="font-medium">Phone:</span> ${customer.phone}</p>
                                <p><span class="font-medium">Type:</span> ${customer.customer_type || 'N/A'}</p>
                                <p><span class="font-medium">Status:</span> <span class="badge badge-${customer.status}">${customer.status || 'N/A'}</span></p>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="font-medium text-gray-700 mb-3">Contact Information</h4>
                            <div class="space-y-2">
                                <p><span class="font-medium">Contact Person:</span> ${customer.contact_person || 'N/A'}</p>
                                <p><span class="font-medium">Alt Phone:</span> ${customer.alt_phone || 'N/A'}</p>
                                <p><span class="font-medium">Address:</span> ${customer.address || 'N/A'}</p>
                                <p><span class="font-medium">Location Type:</span> ${customer.location_type || 'N/A'}</p>
                            </div>
                        </div>
                        
                        <div class="md:col-span-2">
                            <h4 class="font-medium text-gray-700 mb-3">Business Information</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <p><span class="font-medium">Credit Limit:</span> $${customer.credit_limit || '0.00'}</p>
                                <p><span class="font-medium">Payment Terms:</span> ${customer.payment_terms || 'N/A'}</p>
                                <p><span class="font-medium">Total Requests:</span> ${customer.total_requests || 0}</p>
                                <p><span class="font-medium">Active Requests:</span> ${customer.active_requests || 0}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end mt-6">
                        <button onclick="this.closest('.modal').remove()" class="btn btn-secondary">Close</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
        }
        
        // View customer details
        async function viewCustomer(id) {
            try {
                const response = await fetch(`${API_BASE}?action=get&id=${id}`);
                const result = await response.json();
                
                if (result.success) {
                    showCustomerModal(result.data);
                } else {
                    showNotification(result.error || 'Failed to load customer', 'error');
                }
            } catch (error) {
                showNotification(error.message || error.toString(), 'error');
            }
        }
        
        // Edit customer
        async function editCustomer(id) {
            try {
                const response = await fetch(`${API_BASE}?action=get&id=${id}`);
                const result = await response.json();
                
                if (result.success) {
                    populateEditForm(result.data);
                    openModal('edit-customer-modal');
                } else {
                    showNotification(result.error || 'Failed to load customer', 'error');
                }
            } catch (error) {
                showNotification(error.message || error.toString(), 'error');
            }
        }
        
        // Delete customer
        async function deleteCustomer(id) {
            if (!confirm('Are you sure you want to delete this customer?')) return;
            
            try {
                const response = await fetch(`${API_BASE}?action=delete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Customer deleted successfully', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.error || 'Failed to delete customer', 'error');
                }
            } catch (error) {
                showNotification('Network error occurred', 'error');
            }
        }
        
        // Handle bulk actions
        async function handleBulkAction(action) {
            if (!action) return;
            
            const selectedIds = Array.from(document.querySelectorAll('.customer-checkbox:checked'))
                .map(cb => cb.dataset.id);
            
            if (selectedIds.length === 0) {
                showNotification('Please select customers first', 'warning');
                return;
            }
            
            try {
                const response = await fetch(`${API_BASE}?action=bulk`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ action, ids: selectedIds })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification(result.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.error || 'Failed to perform bulk action', 'error');
                }
            } catch (error) {
                showNotification('Network error occurred', 'error');
            }
        }
        
        // Export customers
        async function exportCustomers() {
            try {
                const currentFilters = new URLSearchParams(window.location.search);
                const response = await fetch(`${API_BASE}?action=export&${currentFilters.toString()}`);
                
                if (response.ok) {
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'customers.csv';
                    a.click();
                    window.URL.revokeObjectURL(url);
                } else {
                    showNotification('Failed to export customers', 'error');
                }
            } catch (error) {
                showNotification('Network error occurred', 'error');
            }
        }
        
        // Notification helper
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 p-4 rounded-md shadow-lg z-50 ${
                type === 'success' ? 'bg-green-500 text-white' :
                type === 'error' ? 'bg-red-500 text-white' :
                type === 'warning' ? 'bg-yellow-500 text-black' :
                'bg-blue-500 text-white'
            }`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
    </script>
</body>
</html>
