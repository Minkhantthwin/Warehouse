<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check remember me token
checkRememberMe();

// Require login
requireLogin();

// Check permission for admin management
if (!hasPermission('user_management') && !hasPermission('admin_management')) {
    header('Location: ../index.php');
    exit();
}

$currentAdmin = getLoggedInAdmin();

// Define quick actions for this page
$quickActions = [
    [
        'text' => 'Add Admin',
        'icon' => 'fas fa-plus',
        'class' => 'btn-primary',
        'onclick' => 'openModal(\'add-admin-modal\')'
    ]
    // [
    //     'text' => 'Import',
    //     'icon' => 'fas fa-upload',
    //     'class' => 'btn-secondary',
    //     'onclick' => 'openModal(\'bulk-import-modal\')'
    // ]
];

// Get admin statistics
function getAdminStats($pdo) {
    $stats = [];
    
    // Total admins
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM Admin");
    $stats['total_admins'] = $stmt->fetch()['total'] ?? 0;
    
    // Active admins
    $stmt = $pdo->query("SELECT COUNT(*) as active FROM Admin WHERE status = 'active'");
    $stats['active_admins'] = $stmt->fetch()['active'] ?? 0;
    
    // Super admins
    $stmt = $pdo->query("SELECT COUNT(*) as super_admins FROM Admin WHERE role = 'super-admin'");
    $stats['super_admins'] = $stmt->fetch()['super_admins'] ?? 0;
    
    // Recently added (last 30 days)
    $stmt = $pdo->query("SELECT COUNT(*) as recent FROM Admin WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats['recent_admins'] = $stmt->fetch()['recent'] ?? 0;
    
    return $stats;
}

// Get admins with pagination and filters
function getAdmins($pdo, $page = 1, $limit = 10, $filters = []) {
    $offset = ($page - 1) * $limit;
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if (!empty($filters['search'])) {
        $whereClause .= " AND (name LIKE :search OR email LIKE :search OR phone LIKE :search)";
        $params['search'] = "%" . $filters['search'] . "%";
    }
    
    if (!empty($filters['role'])) {
        $whereClause .= " AND role = :role";
        $params['role'] = $filters['role'];
    }
    
    if (!empty($filters['status'])) {
        $whereClause .= " AND status = :status";
        $params['status'] = $filters['status'];
    }
    
    $query = "SELECT * FROM Admin $whereClause ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
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
    'role' => $_GET['role'] ?? '',
    'status' => $_GET['status'] ?? ''
];

$page = (int)($_GET['page'] ?? 1);
$limit = 10;

// Get data
$stats = getAdminStats($pdo);
$admins = getAdmins($pdo, $page, $limit, $filters);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management - Warehouse Admin</title>
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
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <i class="fas fa-user-shield text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-sm font-medium text-gray-600">Total Admins</h2>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_admins']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-check-circle text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-sm font-medium text-gray-600">Active</h2>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['active_admins']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-crown text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-sm font-medium text-gray-600">Super Admins</h2>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['super_admins']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                            <i class="fas fa-clock text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-sm font-medium text-gray-600">Recently Added</h2>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['recent_admins']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <form method="GET" action="">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                            <div class="relative">
                                <input type="text" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                       placeholder="Search admins..." class="form-input pl-10" id="search-input">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center">
                                    <i class="fas fa-search text-gray-400"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                            <select name="role" class="form-select" id="role-filter">
                                <option value="">All Roles</option>
                                <option value="super-admin" <?php echo $filters['role'] === 'super-admin' ? 'selected' : ''; ?>>Super Admin</option>
                                <option value="admin" <?php echo $filters['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select name="status" class="form-select" id="status-filter">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $filters['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="flex items-end space-x-2">
                            <button type="submit" class="btn btn-primary flex-1">
                                <i class="fas fa-search mr-2"></i>Search
                            </button>
                            <a href="admin-management.php" class="btn btn-secondary">
                                <i class="fas fa-times mr-2"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Admins Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">System Administrators</h3>
                    <div class="flex items-center space-x-2">
                        <button onclick="exportAdmins()" class="text-blue-600 hover:text-blue-800 text-sm">
                            <i class="fas fa-download mr-1"></i>Export
                        </button>
                        <div class="border-l border-gray-300 h-4"></div>
                        <select onchange="handleBulkAction(this.value)" class="text-sm border-gray-300 rounded">
                            <option value="">Bulk Actions</option>
                            <option value="activate">Activate Selected</option>
                            <option value="deactivate">Deactivate Selected</option>
                            <option value="reset-password">Reset Password</option>
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Admin</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Login</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="admins-table-body">
                            <?php foreach ($admins as $admin): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <input type="checkbox" class="rounded border-gray-300 admin-checkbox" data-id="<?php echo $admin['id']; ?>">
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <img class="h-10 w-10 rounded-full" 
                                             src="https://ui-avatars.com/api/?name=<?php echo urlencode($admin['name']); ?>&background=3B82F6&color=fff" 
                                             alt="<?php echo htmlspecialchars($admin['name']); ?>">
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($admin['name']); ?></div>
                                            <div class="text-sm text-gray-500">#A<?php echo str_pad($admin['id'], 3, '0', STR_PAD_LEFT); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($admin['email']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($admin['phone'] ?? 'N/A'); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $admin['role'] === 'super-admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                        <?php echo ucfirst(str_replace('-', ' ', $admin['role'])); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $admin['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo ucfirst($admin['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo date('M j, Y', strtotime($admin['created_at'])); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo date('g:i A', strtotime($admin['created_at'])); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php if ($admin['last_login']): ?>
                                            <?php echo date('M j, Y', strtotime($admin['last_login'])); ?>
                                        <?php else: ?>
                                            Never
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($admin['last_login']): ?>
                                    <div class="text-sm text-gray-500"><?php echo date('g:i A', strtotime($admin['last_login'])); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center space-x-2">
                                        <button onclick="editAdmin(<?php echo $admin['id']; ?>)" 
                                                class="text-green-600 hover:text-green-900" title="Edit Admin">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($admin['role'] !== 'super-admin' || $stats['super_admins'] > 1): ?>
                                        <button onclick="deleteAdmin(<?php echo $admin['id']; ?>)" 
                                                class="text-red-600 hover:text-red-900" title="Delete Admin">
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
                <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200">
                    <div class="flex-1 flex justify-between sm:hidden">
                        <button class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Previous
                        </button>
                        <button class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Next
                        </button>
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Showing <span class="font-medium">1</span> to <span class="font-medium">3</span> of <span class="font-medium">5</span> results
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                <button class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <button class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</button>
                                <button class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Admin Modal -->
    <div id="add-admin-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('add-admin-modal')"></div>
        <div class="modal-content max-w-2xl">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Add New Administrator</h3>
                <button onclick="closeModal('add-admin-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="add-admin-form">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                        <input type="text" name="name" class="form-input" placeholder="Enter full name" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                        <input type="email" name="email" class="form-input" placeholder="admin@warehouse.com" required>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                        <input type="tel" name="phone" class="form-input" placeholder="+1 234 567 8900">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Admin Role *</label>
                        <select name="role" class="form-select" required>
                            <option value="">Select Role</option>
                            <option value="super-admin">Super Administrator</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Password *</label>
                        <input type="password" name="password" class="form-input" placeholder="Enter secure password" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password *</label>
                        <input type="password" name="confirm_password" class="form-input" placeholder="Confirm password" required>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Permissions</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center">
                            <input type="checkbox" name="permissions[]" value="user_management" class="rounded border-gray-300 mr-2">
                            <span class="text-sm">User Management</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="permissions[]" value="inventory_management" class="rounded border-gray-300 mr-2">
                            <span class="text-sm">Inventory Management</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="permissions[]" value="borrowing_requests" class="rounded border-gray-300 mr-2">
                            <span class="text-sm">Borrowing Requests</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="permissions[]" value="reports" class="rounded border-gray-300 mr-2">
                            <span class="text-sm">Reports & Analytics</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="permissions[]" value="system_settings" class="rounded border-gray-300 mr-2">
                            <span class="text-sm">System Settings</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="permissions[]" value="audit_logs" class="rounded border-gray-300 mr-2">
                            <span class="text-sm">Audit Logs</span>
                        </label>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('add-admin-modal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Administrator</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Admin Modal -->
    <div id="edit-admin-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('edit-admin-modal')"></div>
        <div class="modal-content max-w-2xl">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Edit Administrator</h3>
                <button onclick="closeModal('edit-admin-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="edit-admin-form">
                <input type="hidden" name="id" id="edit-admin-id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Admin ID</label>
                        <input type="text" id="edit-admin-display-id" class="form-input bg-gray-100" readonly>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                        <input type="text" name="name" id="edit-name" class="form-input" placeholder="Enter full name" required>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                        <input type="email" name="email" id="edit-email" class="form-input" placeholder="admin@warehouse.com" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                        <input type="tel" name="phone" id="edit-phone" class="form-input" placeholder="+1 234 567 8900">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Admin Role *</label>
                        <select name="role" id="edit-role" class="form-select" required>
                            <option value="">Select Role</option>
                            <option value="admin">Admin</option>
                            <option value="super-admin">Super Admin</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status *</label>
                        <select name="status" id="edit-status" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">New Password (Optional)</label>
                        <input type="password" name="password" id="edit-password" class="form-input" placeholder="Leave blank to keep current">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                        <input type="password" name="confirm_password" id="edit-confirm-password" class="form-input" placeholder="Confirm new password">
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Permissions</label>
                    <div class="grid grid-cols-2 gap-3" id="edit-permissions">
                        <label class="flex items-center">
                            <input type="checkbox" name="permissions[]" value="user_management" class="rounded border-gray-300 mr-2">
                            <span class="text-sm text-gray-700">User Management</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="permissions[]" value="inventory_management" class="rounded border-gray-300 mr-2">
                            <span class="text-sm text-gray-700">Inventory Management</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="permissions[]" value="customer_management" class="rounded border-gray-300 mr-2">
                            <span class="text-sm text-gray-700">Customer Management</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="permissions[]" value="employee_management" class="rounded border-gray-300 mr-2">
                            <span class="text-sm text-gray-700">Employee Management</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="permissions[]" value="reports" class="rounded border-gray-300 mr-2">
                            <span class="text-sm text-gray-700">Reports</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="permissions[]" value="system_settings" class="rounded border-gray-300 mr-2">
                            <span class="text-sm text-gray-700">System Settings</span>
                        </label>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('edit-admin-modal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Administrator</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/dashboard.js"></script>
    <script src="../js/admin-management.js"></script>
    <script>
        // PHP backend integration
        const API_BASE = '../api/admins.php';
        
        // Handle form submission
        document.getElementById('add-admin-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            
            // Handle permissions array
            data.permissions = Array.from(document.querySelectorAll('input[name="permissions[]"]:checked')).map(cb => cb.value);
            
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
                    showNotification('Admin created successfully', 'success');
                    closeModal('add-admin-modal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.error || 'Failed to create admin', 'error');
                }
            } catch (error) {
                showNotification('Network error occurred', 'error');
            }
        });
        
        // Handle edit form submission
        document.getElementById('edit-admin-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            
            // Handle permissions array
            data.permissions = Array.from(document.querySelectorAll('#edit-permissions input[name="permissions[]"]:checked')).map(cb => cb.value);
            
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
                    showNotification('Admin updated successfully', 'success');
                    closeModal('edit-admin-modal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.error || 'Failed to update admin', 'error');
                }
            } catch (error) {
                showNotification('Network error occurred', 'error');
            }
        });
        
        // Populate edit form with admin data
        function populateEditForm(admin) {
            document.getElementById('edit-admin-id').value = admin.id;
            document.getElementById('edit-admin-display-id').value = `#A${admin.id.toString().padStart(3, '0')}`;
            document.getElementById('edit-name').value = admin.name || '';
            document.getElementById('edit-email').value = admin.email || '';
            document.getElementById('edit-phone').value = admin.phone || '';
            document.getElementById('edit-role').value = admin.role || '';
            document.getElementById('edit-status').value = admin.status || '';
            
            // Handle permissions
            const permissions = admin.permissions ? JSON.parse(admin.permissions) : [];
            document.querySelectorAll('#edit-permissions input[name="permissions[]"]').forEach(checkbox => {
                checkbox.checked = permissions.includes(checkbox.value);
            });
        }
        
        // View admin details
        async function viewAdmin(id) {
            try {
                const response = await fetch(`${API_BASE}?action=get&id=${id}`);
                const result = await response.json();
                
                if (result.success) {
                    showAdminModal(result.data);
                } else {
                    showNotification(result.error || 'Failed to fetch admin details', 'error');
                }
            } catch (error) {
                showNotification('Network error occurred', 'error');
            }
        }
        
        // Edit admin
        async function editAdmin(id) {
            try {
                const response = await fetch(`${API_BASE}?action=get&id=${id}`);
                const result = await response.json();
                
                if (result.success) {
                    populateEditForm(result.data);
                    openModal('edit-admin-modal');
                } else {
                    showNotification(result.error || 'Failed to fetch admin details', 'error');  
                }
            } catch (error) {
                showNotification('Network error occurred', 'error');
            }
        }
        
        // Delete admin
        async function deleteAdmin(id) {
            if (!confirm('Are you sure you want to delete this administrator?')) return;
            
            try {
                const response = await fetch(`${API_BASE}?action=delete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Admin deleted successfully', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.error || 'Failed to delete admin', 'error');
                }
            } catch (error) {
                showNotification('Network error occurred', 'error');
            }
        }
        
        // Show admin details modal
        function showAdminModal(admin) {
            const permissions = admin.permissions ? JSON.parse(admin.permissions) : [];
            const permissionsList = permissions.map(p => p.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())).join(', ') || 'None';
            
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-overlay" onclick="this.parentElement.remove()"></div>
                <div class="modal-content max-w-2xl">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-800">Administrator Details</h3>
                        <button onclick="this.closest('.modal').remove()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Basic Information</h4>
                            <div class="space-y-2">
                                <p><span class="text-sm text-gray-500">ID:</span> #A${admin.id.toString().padStart(3, '0')}</p>
                                <p><span class="text-sm text-gray-500">Name:</span> ${admin.name}</p>
                                <p><span class="text-sm text-gray-500">Email:</span> ${admin.email}</p>
                                <p><span class="text-sm text-gray-500">Phone:</span> ${admin.phone || 'N/A'}</p>
                                <p><span class="text-sm text-gray-500">Role:</span> 
                                    <span class="px-2 py-1 text-xs rounded-full ${admin.role === 'super-admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'}">
                                        ${admin.role.replace('-', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                                    </span>
                                </p>
                                <p><span class="text-sm text-gray-500">Status:</span> 
                                    <span class="px-2 py-1 text-xs rounded-full ${admin.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                        ${admin.status.charAt(0).toUpperCase() + admin.status.slice(1)}
                                    </span>
                                </p>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-3">System Information</h4>
                            <div class="space-y-2">
                                <p><span class="text-sm text-gray-500">Created:</span> ${new Date(admin.created_at).toLocaleDateString()}</p>
                                <p><span class="text-sm text-gray-500">Last Login:</span> ${admin.last_login ? new Date(admin.last_login).toLocaleDateString() : 'Never'}</p>
                                <p><span class="text-sm text-gray-500">Managed Employees:</span> ${admin.managed_employees || 0}</p>
                            </div>
                            
                            <h4 class="text-sm font-medium text-gray-700 mb-3 mt-4">Permissions</h4>
                            <p class="text-sm text-gray-600">${permissionsList}</p>
                        </div>
                    </div>
                    
                    <div class="flex justify-end mt-6">
                        <button onclick="this.closest('.modal').remove()" class="btn btn-secondary">Close</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
        }
        
        // Handle bulk actions
        async function handleBulkAction(action) {
            if (!action) return;
            
            const selectedIds = Array.from(document.querySelectorAll('.admin-checkbox:checked'))
                .map(checkbox => parseInt(checkbox.getAttribute('data-id')));
            
            if (selectedIds.length === 0) {
                showNotification('Please select at least one administrator', 'warning');
                return;
            }
            
            try {
                const response = await fetch(`${API_BASE}?action=bulk`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ action: action, ids: selectedIds })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification(`Bulk action completed successfully`, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.error || 'Failed to perform bulk action', 'error');
                }
            } catch (error) {
                showNotification('Network error occurred', 'error');
            }
        }
        
        // Export admins
        async function exportAdmins() {
            try {
                const response = await fetch(`${API_BASE}?action=list&format=export`);
                const result = await response.json();
                
                if (result.success) {
                    // Simple CSV export
                    const csvContent = "data:text/csv;charset=utf-8," 
                        + "ID,Name,Email,Phone,Role,Status,Created\n"
                        + result.data.map(admin => 
                            `${admin.id},"${admin.name}","${admin.email}","${admin.phone || ''}","${admin.role}","${admin.status}","${admin.created_at}"`
                        ).join("\n");
                    
                    const encodedUri = encodeURI(csvContent);
                    const link = document.createElement("a");
                    link.setAttribute("href", encodedUri);
                    link.setAttribute("download", "admins_export.csv");
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    showNotification('Export completed successfully', 'success');
                } else {
                    showNotification('Failed to export data', 'error');
                }
            } catch (error) {
                showNotification('Export failed', 'error');
            }
        }
        
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
