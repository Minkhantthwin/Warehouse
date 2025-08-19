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
        'text' => 'Add Report',
        'icon' => 'fas fa-plus',
        'class' => 'btn-primary',
        'onclick' => 'openModal(\'add-report-modal\')'
    ],
    [
        'text' => 'Export Data',
        'icon' => 'fas fa-download',
        'class' => 'btn-secondary',
        'onclick' => 'exportReports()'
    ]
];

// Get damage report statistics
function getDamageReportStats($pdo) {
    $stats = [];
    
    // Total reports
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM Damage_Report");
    $stats['total_reports'] = $stmt->fetch()['total'] ?? 0;
    
    // Reports by damage type
    $stmt = $pdo->query("
        SELECT damage_type, COUNT(*) as count 
        FROM Damage_Report 
        GROUP BY damage_type 
        ORDER BY count DESC 
        LIMIT 3
    ");
    $damageTypes = $stmt->fetchAll();
    $stats['top_damage_types'] = $damageTypes;
    
    // Reports by condition status
    $stmt = $pdo->query("
        SELECT ri.condition_status, COUNT(*) as count 
        FROM Damage_Report dr 
        INNER JOIN Return_Items ri ON dr.return_item_id = ri.id 
        WHERE ri.condition_status IN ('damaged', 'lost') 
        GROUP BY ri.condition_status
    ");
    $conditionStats = $stmt->fetchAll();
    $stats['condition_stats'] = $conditionStats;
    
    // Total estimated costs
    $stmt = $pdo->query("
        SELECT 
            SUM(repair_cost) as total_repair_cost,
            SUM(replacement_cost) as total_replacement_cost,
            COUNT(CASE WHEN repair_cost > 0 THEN 1 END) as repairs_with_cost,
            COUNT(CASE WHEN replacement_cost > 0 THEN 1 END) as replacements_with_cost
        FROM Damage_Report
    ");
    $costs = $stmt->fetch();
    $stats['total_repair_cost'] = $costs['total_repair_cost'] ?? 0;
    $stats['total_replacement_cost'] = $costs['total_replacement_cost'] ?? 0;
    $stats['repairs_with_cost'] = $costs['repairs_with_cost'] ?? 0;
    $stats['replacements_with_cost'] = $costs['replacements_with_cost'] ?? 0;
    
    // Recent reports (last 7 days)
    $stmt = $pdo->query("SELECT COUNT(*) as recent FROM Damage_Report WHERE report_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['recent_reports'] = $stmt->fetch()['recent'] ?? 0;
    
    return $stats;
}

// Get damage reports with pagination and filters
function getDamageReports($pdo, $page = 1, $limit = 10, $filters = []) {
    $offset = ($page - 1) * $limit;
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if (!empty($filters['search'])) {
        $whereClause .= " AND (dr.damage_type LIKE :search OR dr.damage_description LIKE :search OR bi.item_description LIKE :search OR e.name LIKE :search OR c.name LIKE :search)";
        $params['search'] = "%" . $filters['search'] . "%";
    }
    
    if (!empty($filters['damage_type'])) {
        $whereClause .= " AND dr.damage_type = :damage_type";
        $params['damage_type'] = $filters['damage_type'];
    }
    
    if (!empty($filters['condition_status'])) {
        $whereClause .= " AND ri.condition_status = :condition_status";
        $params['condition_status'] = $filters['condition_status'];
    }
    
    if (!empty($filters['reported_by'])) {
        $whereClause .= " AND dr.reported_by = :reported_by";
        $params['reported_by'] = $filters['reported_by'];
    }
    
    if (!empty($filters['date_from'])) {
        $whereClause .= " AND DATE(dr.report_date) >= :date_from";
        $params['date_from'] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $whereClause .= " AND DATE(dr.report_date) <= :date_to";
        $params['date_to'] = $filters['date_to'];
    }
    
    $query = "SELECT dr.*, 
                     bi.item_description as return_item_description,
                     ri.quantity_returned,
                     ri.condition_status,
                     ri.damage_notes as return_damage_notes,
                     ri.return_date,
                     bt.transaction_type,
                     bt.transaction_date,
                     br.id as request_id,
                     br.purpose as request_purpose,
                     br.status as request_status,
                     c.name as customer_name,
                     c.customer_type,
                     e.name as reported_by_name,
                     e.employee_id as reported_by_employee_id,
                     bi.item_description as borrowed_item_description,
                     bit.name as item_type_name
              FROM Damage_Report dr 
              INNER JOIN Return_Items ri ON dr.return_item_id = ri.id 
              INNER JOIN Borrowing_Transaction bt ON ri.borrowing_transaction_id = bt.id 
              INNER JOIN Borrowing_Request br ON bt.borrowing_request_id = br.id 
              INNER JOIN Customer c ON br.customer_id = c.id 
              INNER JOIN Employee e ON dr.reported_by = e.id 
              INNER JOIN Borrowing_Items bi ON ri.borrowing_item_id = bi.id 
              LEFT JOIN Borrowing_Item_Types bit ON bi.item_type_id = bit.id 
              $whereClause 
              ORDER BY dr.report_date DESC, dr.id DESC 
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
    
    // Get damage types
    $stmt = $pdo->query("SELECT DISTINCT damage_type FROM Damage_Report ORDER BY damage_type");
    $options['damage_types'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get employees
    $stmt = $pdo->query("SELECT id, name, employee_id FROM Employee WHERE status = 'active' ORDER BY name");
    $options['employees'] = $stmt->fetchAll();
    
    // Get return items with damage
    $stmt = $pdo->query("
        SELECT ri.id, bi.item_description, ri.condition_status, bt.transaction_date, c.name as customer_name
        FROM Return_Items ri 
        INNER JOIN Borrowing_Transaction bt ON ri.borrowing_transaction_id = bt.id 
        INNER JOIN Borrowing_Request br ON bt.borrowing_request_id = br.id 
        INNER JOIN Customer c ON br.customer_id = c.id 
        INNER JOIN Borrowing_Items bi ON ri.borrowing_item_id = bi.id 
        WHERE ri.condition_status IN ('damaged', 'lost')
        AND ri.id NOT IN (SELECT return_item_id FROM Damage_Report)
        ORDER BY ri.return_date DESC 
        LIMIT 50
    ");
    $options['return_items'] = $stmt->fetchAll();
    
    return $options;
}

// Get current filters and pagination
$filters = [
    'search' => $_GET['search'] ?? '',
    'damage_type' => $_GET['damage_type'] ?? '',
    'condition_status' => $_GET['condition_status'] ?? '',
    'reported_by' => $_GET['reported_by'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

$page = (int)($_GET['page'] ?? 1);
$limit = 10;

// Get data
$stats = getDamageReportStats($pdo);
$reports = getDamageReports($pdo, $page, $limit, $filters);
$filterOptions = getFilterOptions($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Damage Reports - Warehouse Admin</title>
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
                        <div class="p-3 rounded-full bg-red-100">
                            <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Total Reports</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['total_reports']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-orange-100">
                            <i class="fas fa-tools text-orange-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Repair Cost</p>
                            <p class="text-2xl font-semibold text-gray-900">$<?php echo number_format($stats['total_repair_cost'], 2); ?></p>
                            <p class="text-xs text-gray-400"><?php echo $stats['repairs_with_cost']; ?> items</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100">
                            <i class="fas fa-exchange-alt text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Replacement Cost</p>
                            <p class="text-2xl font-semibold text-gray-900">$<?php echo number_format($stats['total_replacement_cost'], 2); ?></p>
                            <p class="text-xs text-gray-400"><?php echo $stats['replacements_with_cost']; ?> items</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100">
                            <i class="fas fa-chart-pie text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Top Damage Type</p>
                            <p class="text-lg font-semibold text-gray-900">
                                <?php echo !empty($stats['top_damage_types']) ? htmlspecialchars($stats['top_damage_types'][0]['damage_type']) : 'N/A'; ?>
                            </p>
                            <p class="text-xs text-gray-400">
                                <?php echo !empty($stats['top_damage_types']) ? $stats['top_damage_types'][0]['count'] . ' reports' : '0 reports'; ?>
                            </p>
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
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['recent_reports']); ?></p>
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
                                   placeholder="Search reports, damage types, items..." 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Damage Type</label>
                            <select name="damage_type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All Types</option>
                                <?php foreach ($filterOptions['damage_types'] as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>" 
                                            <?php echo $filters['damage_type'] === $type ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Condition Status</label>
                            <select name="condition_status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All Conditions</option>
                                <option value="damaged" <?php echo $filters['condition_status'] === 'damaged' ? 'selected' : ''; ?>>Damaged</option>
                                <option value="lost" <?php echo $filters['condition_status'] === 'lost' ? 'selected' : ''; ?>>Lost</option>
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
                            <a href="reports.php" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50">
                                <i class="fas fa-times mr-2"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Reports Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800">Damage Reports</h3>
                    
                    <div class="flex items-center space-x-3">
                        <select onchange="handleBulkAction(this.value)" class="px-3 py-2 border border-gray-300 rounded-md">
                            <option value="">Bulk Actions</option>
                            <option value="delete">Delete Selected</option>
                            <option value="export">Export Selected</option>
                        </select>
                        
                        <button onclick="openModal('add-report-modal')" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>Add Report
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Report Info</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item & Damage Details</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Costs</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer & Request</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($reports as $report): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <input type="checkbox" class="report-checkbox rounded border-gray-300" data-id="<?php echo $report['id']; ?>">
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    Report #<?php echo $report['id']; ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo date('M j, Y g:i A', strtotime($report['report_date'])); ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    Reported by: <?php echo htmlspecialchars($report['reported_by_name']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    ID: <?php echo htmlspecialchars($report['reported_by_employee_id']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">
                                            <?php echo htmlspecialchars($report['return_item_description']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php
                                            $conditionColors = [
                                                'good' => 'bg-green-100 text-green-800',
                                                'damaged' => 'bg-orange-100 text-orange-800',
                                                'lost' => 'bg-red-100 text-red-800'
                                            ];
                                            $conditionClass = $conditionColors[$report['condition_status']] ?? 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $conditionClass; ?>">
                                                <?php echo ucfirst($report['condition_status']); ?>
                                            </span>
                                        </div>
                                        <div class="text-sm text-gray-500 mt-1">
                                            <strong>Type:</strong> <?php echo htmlspecialchars($report['damage_type']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500 truncate max-w-xs">
                                            <?php echo htmlspecialchars(substr($report['damage_description'], 0, 60)); ?>...
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">
                                            <?php if ($report['repair_cost']): ?>
                                                <div>Repair: $<?php echo number_format($report['repair_cost'], 2); ?></div>
                                            <?php endif; ?>
                                            <?php if ($report['replacement_cost']): ?>
                                                <div>Replace: $<?php echo number_format($report['replacement_cost'], 2); ?></div>
                                            <?php endif; ?>
                                            <?php if (!$report['repair_cost'] && !$report['replacement_cost']): ?>
                                                <div class="text-gray-500">No costs recorded</div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">
                                            <?php echo htmlspecialchars($report['customer_name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            Request #<?php echo $report['request_id']; ?>
                                        </div>
                                        <div class="text-sm text-gray-500 truncate max-w-xs">
                                            <?php echo htmlspecialchars(substr($report['request_purpose'], 0, 40)); ?>...
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            Return: <?php echo date('M j, Y', strtotime($report['return_date'])); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button onclick="viewReport(<?php echo $report['id']; ?>)" 
                                                    class="text-blue-600 hover:text-blue-900" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button onclick="editReport(<?php echo $report['id']; ?>)" 
                                                    class="text-indigo-600 hover:text-indigo-900" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="deleteReport(<?php echo $report['id']; ?>)" 
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
                            Showing <?php echo count($reports); ?> reports
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

    <!-- Add Report Modal -->
    <div id="add-report-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('add-report-modal')"></div>
        <div class="modal-content max-w-2xl">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Add New Damage Report</h3>
                <button onclick="closeModal('add-report-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="add-report-form">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4 p-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Return Item *</label>
                        <select name="return_item_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Damaged/Lost Return Item</option>
                            <?php foreach ($filterOptions['return_items'] as $item): ?>
                                <option value="<?php echo $item['id']; ?>">
                                    <?php echo htmlspecialchars($item['item_description']); ?> 
                                    (<?php echo ucfirst($item['condition_status']); ?>) - 
                                    <?php echo htmlspecialchars($item['customer_name']); ?> - 
                                    <?php echo date('M j, Y', strtotime($item['transaction_date'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Damage Type *</label>
                        <input type="text" name="damage_type" required 
                               placeholder="e.g., Physical damage, Missing parts, etc." 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Reported By *</label>
                        <select name="reported_by" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Employee</option>
                            <?php foreach ($filterOptions['employees'] as $employee): ?>
                                <option value="<?php echo $employee['id']; ?>">
                                    <?php echo htmlspecialchars($employee['name']); ?> (ID: <?php echo htmlspecialchars($employee['employee_id']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Damage Description *</label>
                    <textarea name="damage_description" rows="4" required 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                              placeholder="Describe the damage in detail..."></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Repair Cost</label>
                        <input type="number" name="repair_cost" step="0.01" min="0" 
                               placeholder="0.00" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Replacement Cost</label>
                        <input type="number" name="replacement_cost" step="0.01" min="0" 
                               placeholder="0.00" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('add-report-modal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Create Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Report Modal -->
    <div id="edit-report-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('edit-report-modal')"></div>
        <div class="modal-content max-w-2xl">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Edit Damage Report</h3>
                <button onclick="closeModal('edit-report-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="edit-report-form">
                <input type="hidden" id="edit-report-id" name="id">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Damage Type *</label>
                        <input type="text" name="damage_type" id="edit-damage-type" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Reported By *</label>
                        <select name="reported_by" id="edit-reported-by" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <?php foreach ($filterOptions['employees'] as $employee): ?>
                                <option value="<?php echo $employee['id']; ?>">
                                    <?php echo htmlspecialchars($employee['name']); ?> (ID: <?php echo htmlspecialchars($employee['employee_id']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Damage Description *</label>
                    <textarea name="damage_description" id="edit-damage-description" rows="4" required 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Repair Cost</label>
                        <input type="number" name="repair_cost" id="edit-repair-cost" step="0.01" min="0" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Replacement Cost</label>
                        <input type="number" name="replacement_cost" id="edit-replacement-cost" step="0.01" min="0" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('edit-report-modal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Update Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Report Modal -->
    <div id="view-report-modal" class="modal hidden">
        <div class="modal-overlay" onclick="closeModal('view-report-modal')"></div>
        <div class="modal-content max-w-4xl">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Damage Report Details</h3>
                <button onclick="closeModal('view-report-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div id="report-details-content">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <script src="js/dashboard.js"></script>
    <script>
        // Initialize select all functionality
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.report-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Global variables
        let editingReportId = null;

        // Report management functions
        async function viewReport(id) {
            try {
                const response = await fetch(`api/damage-reports.php?action=get&id=${id}`);
                const result = await response.json();
                
                if (result.success) {
                    showReportDetails(result.data);
                } else {
                    showNotification('Failed to load report details', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            }
        }

        function showReportDetails(report) {
            const conditionColors = {
                'good': 'bg-green-100 text-green-800',
                'damaged': 'bg-orange-100 text-orange-800',
                'lost': 'bg-red-100 text-red-800'
            };
            
            const conditionClass = conditionColors[report.condition_status] || 'bg-gray-100 text-gray-800';
            
            const content = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <h4 class="text-md font-semibold text-gray-700">Report Information</h4>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="grid grid-cols-1 gap-3">
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Report ID:</span>
                                    <p class="text-sm text-gray-900">#${report.id}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Report Date:</span>
                                    <p class="text-sm text-gray-900">${new Date(report.report_date).toLocaleString()}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Reported By:</span>
                                    <p class="text-sm text-gray-900">${report.reported_by_name}</p>
                                    <p class="text-xs text-gray-500">ID: ${report.reported_by_employee_id}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Damage Type:</span>
                                    <p class="text-sm text-gray-900">${report.damage_type}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <h4 class="text-md font-semibold text-gray-700">Item Information</h4>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="grid grid-cols-1 gap-3">
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Item Description:</span>
                                    <p class="text-sm text-gray-900">${report.return_item_description}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Condition Status:</span>
                                    <div class="mt-1">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${conditionClass}">
                                            ${report.condition_status.charAt(0).toUpperCase() + report.condition_status.slice(1)}
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Quantity Returned:</span>
                                    <p class="text-sm text-gray-900">${report.quantity_returned}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Return Date:</span>
                                    <p class="text-sm text-gray-900">${new Date(report.return_date).toLocaleDateString()}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6">
                    <h4 class="text-md font-semibold text-gray-700 mb-3">Damage Description</h4>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-900">${report.damage_description}</p>
                    </div>
                </div>
                
                <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <h4 class="text-md font-semibold text-gray-700">Cost Information</h4>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="grid grid-cols-1 gap-3">
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Repair Cost:</span>
                                    <p class="text-sm text-gray-900">$${report.repair_cost ? parseFloat(report.repair_cost).toFixed(2) : '0.00'}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Replacement Cost:</span>
                                    <p class="text-sm text-gray-900">$${report.replacement_cost ? parseFloat(report.replacement_cost).toFixed(2) : '0.00'}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Total Cost:</span>
                                    <p class="text-sm font-bold text-gray-900">$${(parseFloat(report.repair_cost || 0) + parseFloat(report.replacement_cost || 0)).toFixed(2)}</p>
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
                                    <p class="text-sm text-gray-900">#${report.request_id}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Customer:</span>
                                    <p class="text-sm text-gray-900">${report.customer_name}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Purpose:</span>
                                    <p class="text-sm text-gray-900">${report.request_purpose}</p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Transaction Type:</span>
                                    <p class="text-sm text-gray-900">${report.transaction_type.charAt(0).toUpperCase() + report.transaction_type.slice(1)}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                ${report.return_damage_notes ? `
                    <div class="mt-6">
                        <h4 class="text-md font-semibold text-gray-700 mb-3">Return Notes</h4>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-900">${report.return_damage_notes}</p>
                        </div>
                    </div>
                ` : ''}
            `;
            
            document.getElementById('report-details-content').innerHTML = content;
            openModal('view-report-modal');
        }

        async function editReport(id) {
            try {
                const response = await fetch(`api/damage-reports.php?action=get&id=${id}`);
                const result = await response.json();
                
                if (result.success) {
                    populateEditForm(result.data);
                    editingReportId = id;
                    openModal('edit-report-modal');
                } else {
                    showNotification('Failed to load report details', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            }
        }

        function populateEditForm(report) {
            document.getElementById('edit-report-id').value = report.id;
            document.getElementById('edit-damage-type').value = report.damage_type;
            document.getElementById('edit-damage-description').value = report.damage_description;
            document.getElementById('edit-reported-by').value = report.reported_by;
            document.getElementById('edit-repair-cost').value = report.repair_cost || '';
            document.getElementById('edit-replacement-cost').value = report.replacement_cost || '';
        }

        async function deleteReport(id) {
            if (!confirm('Are you sure you want to delete this damage report? This action cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch('api/damage-reports.php', {
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
                    showNotification('Damage report deleted successfully!', 'success');
                    location.reload();
                } else {
                    showNotification(result.error || 'Failed to delete report', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            }
        }

        async function handleBulkAction(action) {
            if (!action) return;
            
            const selectedReports = document.querySelectorAll('.report-checkbox:checked');
            if (selectedReports.length === 0) {
                showNotification('Please select reports first', 'warning');
                return;
            }

            const selectedIds = Array.from(selectedReports).map(report => report.dataset.id);

            switch (action) {
                case 'delete':
                    await bulkDeleteReports(selectedIds);
                    break;
                case 'export':
                    await exportReports(selectedIds);
                    break;
            }
            
            // Reset the select dropdown
            document.querySelector('select').value = '';
        }

        async function bulkDeleteReports(reportIds) {
            if (!confirm(`Are you sure you want to delete ${reportIds.length} damage reports? This action cannot be undone.`)) {
                return;
            }

            try {
                const response = await fetch('api/damage-reports.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'bulk_delete',
                        ids: reportIds
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    showNotification(result.message, 'success');
                    location.reload();
                } else {
                    showNotification(result.message || 'Failed to delete reports', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            }
        }

        async function exportReports(reportIds = null) {
            const idsParam = reportIds ? `&report_ids=${reportIds.join(',')}` : '';
            const url = `api/damage-reports.php?action=export&format=csv${idsParam}`;
            
            try {
                window.open(url, '_blank');
                showNotification('Export started successfully!', 'success');
            } catch (error) {
                console.error('Error:', error);
                showNotification('Failed to export data', 'error');
            }
        }

        // Form submission handlers
        document.getElementById('add-report-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const reportData = {
                action: 'create',
                return_item_id: formData.get('return_item_id'),
                damage_type: formData.get('damage_type'),
                damage_description: formData.get('damage_description'),
                repair_cost: formData.get('repair_cost'),
                replacement_cost: formData.get('replacement_cost'),
                reported_by: formData.get('reported_by')
            };

            try {
                const response = await fetch('api/damage-reports.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(reportData)
                });

                const result = await response.json();
                
                if (result.success) {
                    showNotification('Damage report created successfully!', 'success');
                    closeModal('add-report-modal');
                    location.reload();
                } else {
                    showNotification(result.error || 'Failed to create report', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            }
        });

        document.getElementById('edit-report-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const reportData = {
                action: 'update',
                id: formData.get('id'),
                damage_type: formData.get('damage_type'),
                damage_description: formData.get('damage_description'),
                repair_cost: formData.get('repair_cost'),
                replacement_cost: formData.get('replacement_cost'),
                reported_by: formData.get('reported_by')
            };

            try {
                const response = await fetch('api/damage-reports.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(reportData)
                });

                const result = await response.json();
                
                if (result.success) {
                    showNotification('Damage report updated successfully!', 'success');
                    closeModal('edit-report-modal');
                    location.reload();
                } else {
                    showNotification(result.error || 'Failed to update report', 'error');
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
