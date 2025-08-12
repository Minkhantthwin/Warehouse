<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check remember me token
checkRememberMe();

// Require login
requireLogin();

// Check permission for employee management
if (!hasPermission('employee_management')) {
    header('Location: ../index.php');
    exit();
}

$currentAdmin = getLoggedInAdmin();

// Define quick actions for this page
$quickActions = [
    [
        'text' => 'Add Employee',
        'icon' => 'fas fa-plus',
        'class' => 'btn-primary',
        'onclick' => 'openModal(\'add-employee-modal\')'
    ],
    [
        'text' => 'Import',
        'icon' => 'fas fa-upload',
        'class' => 'btn-secondary',
        'onclick' => 'openModal(\'bulk-import-modal\')'
    ]
];

// Get employee statistics
function getEmployeeStats($pdo) {
    $stats = [];
    
    // Total employees
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM Employee");
    $stats['total_employees'] = $stmt->fetch()['total'] ?? 0;
    
    // Active employees
    $stmt = $pdo->query("SELECT COUNT(*) as active FROM Employee WHERE status = 'active'");
    $stats['active_employees'] = $stmt->fetch()['active'] ?? 0;
    
    // On duty employees (those with recent activity)
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT e.id) as on_duty 
        FROM Employee e 
        INNER JOIN Borrowing_Transaction bt ON e.id = bt.processed_by 
        WHERE bt.transaction_date >= CURDATE()
    ");
    $stats['on_duty'] = $stmt->fetch()['on_duty'] ?? 0;
    
    // New employees this month
    $stmt = $pdo->query("
        SELECT COUNT(*) as new_employees 
        FROM Employee 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stats['new_this_month'] = $stmt->fetch()['new_employees'] ?? 0;
    
    return $stats;
}

// Get employees with pagination and filters
function getEmployees($pdo, $page = 1, $limit = 10, $filters = []) {
    $offset = ($page - 1) * $limit;
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if (!empty($filters['search'])) {
        $whereClause .= " AND (name LIKE :search OR email LIKE :search OR phone LIKE :search OR employee_id LIKE :search)";
        $params['search'] = "%" . $filters['search'] . "%";
    }
    
    if (!empty($filters['department'])) {
        $whereClause .= " AND department = :department";
        $params['department'] = $filters['department'];
    }
    
    if (!empty($filters['position'])) {
        $whereClause .= " AND position LIKE :position";
        $params['position'] = "%" . $filters['position'] . "%";
    }
    
    if (!empty($filters['status'])) {
        $whereClause .= " AND status = :status";
        $params['status'] = $filters['status'];
    }
    
    $query = "SELECT * FROM Employee $whereClause ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
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
    'department' => $_GET['department'] ?? '',
    'position' => $_GET['position'] ?? '',
    'status' => $_GET['status'] ?? ''
];

$page = (int)($_GET['page'] ?? 1);
$limit = 10;

// Get data
$stats = getEmployeeStats($pdo);
$employees = getEmployees($pdo, $page, $limit, $filters);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management - Warehouse Admin</title>
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
        <!-- Top Navigation -->
         <?php include '../includes/navbar.php'; ?>

        <!-- Main Content Area -->
        <main class="p-6">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-users text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-sm font-medium text-gray-600">Total Employees</h2>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_employees']; ?></p>
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
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['active_employees']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                            <i class="fas fa-briefcase text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-sm font-medium text-gray-600">On Duty</h2>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['on_duty']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <i class="fas fa-calendar-plus text-2xl"></i>
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
                                <input type="text" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                       placeholder="Search employees..." class="form-input pl-10" id="search-input">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center">
                                    <i class="fas fa-search text-gray-400"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                            <select name="department" class="form-select" id="department-filter">
                                <option value="">All Departments</option>
                                <option value="warehouse" <?php echo $filters['department'] === 'warehouse' ? 'selected' : ''; ?>>Warehouse</option>
                                <option value="logistics" <?php echo $filters['department'] === 'logistics' ? 'selected' : ''; ?>>Logistics</option>
                                <option value="inventory" <?php echo $filters['department'] === 'inventory' ? 'selected' : ''; ?>>Inventory</option>
                                <option value="quality" <?php echo $filters['department'] === 'quality' ? 'selected' : ''; ?>>Quality Control</option>
                                <option value="maintenance" <?php echo $filters['department'] === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                <option value="administration" <?php echo $filters['department'] === 'administration' ? 'selected' : ''; ?>>Administration</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Position</label>
                            <input type="text" name="position" value="<?php echo htmlspecialchars($filters['position']); ?>" 
                                   placeholder="Search positions..." class="form-input" id="position-filter">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select name="status" class="form-select" id="status-filter">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $filters['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="on-leave" <?php echo $filters['status'] === 'on-leave' ? 'selected' : ''; ?>>On Leave</option>
                                <option value="terminated" <?php echo $filters['status'] === 'terminated' ? 'selected' : ''; ?>>Terminated</option>
                            </select>
                        </div>
                        
                        <div class="flex items-end space-x-2">
                            <button type="submit" class="btn btn-primary flex-1">
                                <i class="fas fa-search mr-2"></i>Search
                            </button>
                            <!-- <a href="employee-management.php" class="btn btn-secondary">
                                <i class="fas fa-times mr-2"></i>Clear
                            </a> -->
                        </div>
                    </div>
                </form>
            </div>

            <!-- Employees Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">Employee Directory</h3>
                    <div class="flex items-center space-x-2">
                        <button onclick="exportEmployees()" class="text-blue-600 hover:text-blue-800 text-sm">
                            <i class="fas fa-download mr-1"></i>Export
                        </button>
                        <div class="border-l border-gray-300 h-4"></div>
                        <select onchange="handleBulkAction(this.value)" class="text-sm border-gray-300 rounded">
                            <option value="">Bulk Actions</option>
                            <option value="activate">Activate Selected</option>
                            <option value="deactivate">Deactivate Selected</option>
                            <option value="update-department">Update Department</option>
                            <option value="send-notification">Send Notification</option>
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hire Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="employees-table-body">
                            <?php if (empty($employees)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-users text-4xl text-gray-300 mb-2"></i>
                                        <p class="text-lg font-medium">No employees found</p>
                                        <p class="text-sm">Add your first employee to get started.</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($employees as $employee): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <input type="checkbox" class="rounded border-gray-300 employee-checkbox" data-id="<?php echo $employee['id']; ?>">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <?php 
                                            $nameForAvatar = urlencode($employee['name']);
                                            $avatarColors = ['3B82F6', '10B981', 'F59E0B', 'EF4444', '8B5CF6', '06B6D4', 'F97316'];
                                            $colorIndex = abs(crc32($employee['name'])) % count($avatarColors);
                                            $avatarColor = $avatarColors[$colorIndex];
                                            ?>
                                            <img class="h-10 w-10 rounded-full" src="https://ui-avatars.com/api/?name=<?php echo $nameForAvatar; ?>&background=<?php echo $avatarColor; ?>&color=fff" alt="">
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($employee['name']); ?></div>
                                                <div class="text-sm text-gray-500">Emp ID: <?php echo htmlspecialchars($employee['employee_id']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($employee['email']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($employee['phone']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php 
                                        $departmentColors = [
                                            'warehouse' => 'bg-blue-100 text-blue-800',
                                            'inventory' => 'bg-green-100 text-green-800',
                                            'logistics' => 'bg-yellow-100 text-yellow-800',
                                            'quality-control' => 'bg-red-100 text-red-800',
                                            'administration' => 'bg-purple-100 text-purple-800',
                                            'security' => 'bg-gray-100 text-gray-800'
                                        ];
                                        $colorClass = $departmentColors[$employee['department']] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $colorClass; ?>">
                                            <?php echo ucfirst(str_replace('-', ' ', $employee['department'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($employee['position']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $statusClasses = [
                                            'active' => 'badge-active',
                                            'inactive' => 'badge-inactive', 
                                            'on-leave' => 'badge-warning',
                                            'terminated' => 'badge-danger'
                                        ];
                                        $statusClass = $statusClasses[$employee['status']] ?? 'badge-inactive';
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst(str_replace('-', ' ', $employee['status'])); ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo $employee['hire_date'] ? date('M j, Y', strtotime($employee['hire_date'])) : 'N/A'; ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button onclick="viewEmployee(<?php echo $employee['id']; ?>)" class="text-blue-600 hover:text-blue-900" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button onclick="editEmployee(<?php echo $employee['id']; ?>)" class="text-green-600 hover:text-green-900" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="assignShift(<?php echo $employee['id']; ?>)" class="text-purple-600 hover:text-purple-900" title="Assign Shift">
                                                <i class="fas fa-calendar-alt"></i>
                                            </button>
                                            <button onclick="toggleEmployeeStatus(<?php echo $employee['id']; ?>)" class="text-orange-600 hover:text-orange-900" title="Toggle Status">
                                                <?php if ($employee['status'] === 'active'): ?>
                                                <i class="fas fa-toggle-on"></i>
                                                <?php else: ?>
                                                <i class="fas fa-toggle-off"></i>
                                                <?php endif; ?>
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
                                Showing <span class="font-medium">1</span> to <span class="font-medium">4</span> of <span class="font-medium">25</span> results
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                <button class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <button class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</button>
                                <button class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">2</button>
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

    <!-- Add Employee Modal -->
    <div id="add-employee-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('add-employee-modal')"></div>
        <div class="modal-content max-w-3xl">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Add New Employee</h3>
                <button onclick="closeModal('add-employee-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="add-employee-form">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                        <input type="text" name="name" class="form-input" placeholder="Enter full name" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Employee ID</label>
                        <input type="text" name="employee_id" class="form-input" placeholder="Auto-generated" readonly>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                        <input type="email" name="email" class="form-input" placeholder="employee@warehouse.com" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number *</label>
                        <input type="tel" name="phone" class="form-input" placeholder="+1 234 567 8900" required>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Department *</label>
                        <select name="department" class="form-select" required>
                            <option value="">Select Department</option>
                            <option value="warehouse">Warehouse Operations</option>
                            <option value="logistics">Logistics & Shipping</option>
                            <option value="inventory">Inventory Management</option>
                            <option value="quality">Quality Control</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Position *</label>
                        <select name="position" class="form-select" required>
                            <option value="">Select Position</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="operator">Operator</option>
                            <option value="technician">Technician</option>
                            <option value="clerk">Clerk</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Hire Date *</label>
                        <input type="date" name="hire_date" class="form-input" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Salary</label>
                        <input type="number" name="salary" class="form-input" placeholder="0.00" step="0.01">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Shift</label>
                        <select name="shift" class="form-select">
                            <option value="day">Day Shift (8AM - 4PM)</option>
                            <option value="evening">Evening Shift (4PM - 12AM)</option>
                            <option value="night">Night Shift (12AM - 8AM)</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Emergency Contact</label>
                        <input type="tel" name="emergency_contact" class="form-input" placeholder="+1 234 567 8900">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                    <textarea name="address" rows="3" class="form-textarea" placeholder="Enter complete address"></textarea>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Access Permissions</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center">
                            <input type="checkbox" name="permissions[]" value="inventory_view" class="rounded border-gray-300 mr-2">
                            <span class="text-sm">Inventory View</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="permissions[]" value="borrowing_requests" class="rounded border-gray-300 mr-2">
                            <span class="text-sm">Borrowing Requests</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="permissions[]" value="material_handling" class="rounded border-gray-300 mr-2">
                            <span class="text-sm">Material Handling</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="permissions[]" value="reporting" class="rounded border-gray-300 mr-2">
                            <span class="text-sm">Basic Reporting</span>
                        </label>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('add-employee-modal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Employee</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/dashboard.js"></script>
    <script src="../js/employee-management.js"></script>
</body>
</html>
