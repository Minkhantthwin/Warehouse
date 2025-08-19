<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Check remember me token
checkRememberMe();

// Require login
requireLogin();

$currentAdmin = getLoggedInAdmin();

// Get comprehensive dashboard statistics
function getDashboardStats($pdo) {
    $stats = [];
    
    // Borrowing Requests Statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM Borrowing_Request");
    $stats['total_requests'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as pending FROM Borrowing_Request WHERE status = 'pending'");
    $stats['pending_requests'] = $stmt->fetch()['pending'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as active FROM Borrowing_Request WHERE status = 'active'");
    $stats['active_borrowings'] = $stmt->fetch()['active'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as overdue FROM Borrowing_Request WHERE status = 'overdue'");
    $stats['overdue_items'] = $stmt->fetch()['overdue'] ?? 0;
    
    // Customer Statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM Customer WHERE status = 'active'");
    $stats['total_customers'] = $stmt->fetch()['total'] ?? 0;
    
    // Borrowing Items Statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM Borrowing_Items");
    $stats['total_items'] = $stmt->fetch()['total'] ?? 0;
    
    // Transaction Statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM Borrowing_Transaction");
    $stats['total_transactions'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as borrow FROM Borrowing_Transaction WHERE transaction_type = 'borrow'");
    $stats['borrow_transactions'] = $stmt->fetch()['borrow'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as return_count FROM Borrowing_Transaction WHERE transaction_type = 'return'");
    $stats['return_transactions'] = $stmt->fetch()['return_count'] ?? 0;
    
    // Return Items Statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM Return_Items");
    $stats['total_returns'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as damaged FROM Return_Items WHERE condition_status = 'damaged'");
    $stats['damaged_returns'] = $stmt->fetch()['damaged'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as lost FROM Return_Items WHERE condition_status = 'lost'");
    $stats['lost_items'] = $stmt->fetch()['lost'] ?? 0;
    
    // Damage Reports Statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM Damage_Report");
    $stats['total_damage_reports'] = $stmt->fetch()['total'] ?? 0;
    
    // Recent activity (last 7 days)
    $stmt = $pdo->query("SELECT COUNT(*) as recent FROM Borrowing_Request WHERE request_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['recent_requests'] = $stmt->fetch()['recent'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as recent FROM Borrowing_Transaction WHERE transaction_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['recent_transactions'] = $stmt->fetch()['recent'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as recent FROM Return_Items WHERE return_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['recent_returns'] = $stmt->fetch()['recent'] ?? 0;
    
    return $stats;
}

// Get chart data for requests over time (last 30 days)
function getRequestsChartData($pdo) {
    $stmt = $pdo->query("
        SELECT DATE(request_date) as date, COUNT(*) as count 
        FROM Borrowing_Request 
        WHERE request_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(request_date)
        ORDER BY date ASC
    ");
    return $stmt->fetchAll();
}

// Get transaction type distribution
function getTransactionTypeData($pdo) {
    $stmt = $pdo->query("
        SELECT transaction_type, COUNT(*) as count 
        FROM Borrowing_Transaction 
        GROUP BY transaction_type
    ");
    return $stmt->fetchAll();
}

// Get status distribution for requests
function getRequestStatusData($pdo) {
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM Borrowing_Request 
        GROUP BY status
    ");
    return $stmt->fetchAll();
}

// Get condition status for returns
function getReturnConditionData($pdo) {
    $stmt = $pdo->query("
        SELECT condition_status, COUNT(*) as count 
        FROM Return_Items 
        GROUP BY condition_status
    ");
    return $stmt->fetchAll();
}

// Get top customers by request count
function getTopCustomers($pdo) {
    $stmt = $pdo->query("
        SELECT c.name, c.customer_type, COUNT(br.id) as request_count 
        FROM Customer c 
        LEFT JOIN Borrowing_Request br ON c.id = br.customer_id 
        WHERE c.status = 'active'
        GROUP BY c.id 
        ORDER BY request_count DESC 
        LIMIT 10
    ");
    return $stmt->fetchAll();
}

// Get recent activity
function getRecentActivity($pdo) {
    $stmt = $pdo->query("
        SELECT 'request' as type, br.id, c.name as customer_name, c.email as customer_email, 
               br.purpose as description, br.status, br.request_date as activity_date
        FROM Borrowing_Request br 
        INNER JOIN Customer c ON br.customer_id = c.id 
        WHERE br.request_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        
        UNION ALL
        
        SELECT 'transaction' as type, bt.id, c.name as customer_name, c.email as customer_email,
               CONCAT(bt.transaction_type, ' - ', COALESCE(bt.notes, 'No notes')) as description, 
               bt.transaction_type as status, bt.transaction_date as activity_date
        FROM Borrowing_Transaction bt 
        INNER JOIN Borrowing_Request br ON bt.borrowing_request_id = br.id 
        INNER JOIN Customer c ON br.customer_id = c.id 
        WHERE bt.transaction_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        
        UNION ALL
        
        SELECT 'return' as type, ri.id, c.name as customer_name, c.email as customer_email,
               CONCAT('Returned ', ri.quantity_returned, ' item(s) - ', ri.condition_status) as description,
               ri.condition_status as status, ri.return_date as activity_date
        FROM Return_Items ri 
        INNER JOIN Borrowing_Transaction bt ON ri.borrowing_transaction_id = bt.id 
        INNER JOIN Borrowing_Request br ON bt.borrowing_request_id = br.id 
        INNER JOIN Customer c ON br.customer_id = c.id 
        WHERE ri.return_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        
        ORDER BY activity_date DESC 
        LIMIT 10
    ");
    return $stmt->fetchAll();
}

// Get all data
$stats = getDashboardStats($pdo);
$requestsChartData = getRequestsChartData($pdo);
$transactionTypeData = getTransactionTypeData($pdo);
$requestStatusData = getRequestStatusData($pdo);
$returnConditionData = getReturnConditionData($pdo);
$topCustomers = getTopCustomers($pdo);
$recentActivity = getRecentActivity($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-gray-100">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="lg:ml-64">
        <?php include 'includes/navbar.php'; ?>

        <!-- Main Content Area -->
        <main class="p-6">
            <!-- Welcome Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Welcome back, <?php echo htmlspecialchars($currentAdmin['name']); ?>!</h1>
                <p class="text-gray-600">Here's what's happening in your warehouse today.</p>
            </div>

            <!-- Primary Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-clipboard-list text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-sm font-medium text-gray-600">Pending Requests</h2>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['pending_requests']); ?></p>
                            <p class="text-xs text-gray-500">
                                <?php echo $stats['recent_requests']; ?> new this week
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-check-circle text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-sm font-medium text-gray-600">Active Borrowings</h2>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['active_borrowings']); ?></p>
                            <p class="text-xs text-gray-500">
                                <?php echo $stats['borrow_transactions']; ?> total borrows
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-yellow-500">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                            <i class="fas fa-exclamation-triangle text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-sm font-medium text-gray-600">Overdue Items</h2>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['overdue_items']); ?></p>
                            <p class="text-xs text-gray-500">
                                <?php echo $stats['lost_items']; ?> lost items
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-purple-500">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <i class="fas fa-users text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-sm font-medium text-gray-600">Active Customers</h2>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($stats['total_customers']); ?></p>
                            <p class="text-xs text-gray-500">
                                Total registered
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Secondary Stats Row -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-md p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Total Items</p>
                            <p class="text-xl font-semibold text-gray-900"><?php echo number_format($stats['total_items']); ?></p>
                        </div>
                        <i class="fas fa-boxes text-blue-500 text-xl"></i>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Transactions</p>
                            <p class="text-xl font-semibold text-gray-900"><?php echo number_format($stats['total_transactions']); ?></p>
                        </div>
                        <i class="fas fa-exchange-alt text-green-500 text-xl"></i>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Returns</p>
                            <p class="text-xl font-semibold text-gray-900"><?php echo number_format($stats['total_returns']); ?></p>
                        </div>
                        <i class="fas fa-undo text-orange-500 text-xl"></i>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Damaged Items</p>
                            <p class="text-xl font-semibold text-gray-900"><?php echo number_format($stats['damaged_returns']); ?></p>
                        </div>
                        <i class="fas fa-tools text-red-500 text-xl"></i>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Damage Reports</p>
                            <p class="text-xl font-semibold text-gray-900"><?php echo number_format($stats['total_damage_reports']); ?></p>
                        </div>
                        <i class="fas fa-file-alt text-yellow-500 text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Requests Trend Chart -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Borrowing Requests Trend (Last 30 Days)</h3>
                    <div class="h-64">
                        <canvas id="requestsChart"></canvas>
                    </div>
                </div>

                <!-- Transaction Types Pie Chart -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Transaction Distribution</h3>
                    <div class="h-64">
                        <canvas id="transactionTypesChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Status Distribution and Top Customers -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Request Status Distribution -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Request Status Distribution</h3>
                    <div class="h-64">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>

                <!-- Top Customers -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Top Customers by Requests</h3>
                    <div class="space-y-4 max-h-64 overflow-y-auto">
                        <?php foreach ($topCustomers as $customer): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                                        <?php echo strtoupper(substr($customer['name'], 0, 2)); ?>
                                    </div>
                                    <div class="ml-3">
                                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($customer['name']); ?></p>
                                        <p class="text-sm text-gray-600"><?php echo ucfirst($customer['customer_type']); ?></p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold text-gray-900"><?php echo $customer['request_count']; ?></p>
                                    <p class="text-xs text-gray-500">requests</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Return Condition Chart and Quick Actions -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Return Condition Status -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Return Item Conditions</h3>
                    <div class="h-64">
                        <canvas id="returnConditionChart"></canvas>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <a href="borrowing-requests.php" class="p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                            <div class="flex items-center">
                                <i class="fas fa-plus-circle text-blue-600 text-2xl mr-3"></i>
                                <div>
                                    <p class="font-medium text-blue-900">New Request</p>
                                    <p class="text-sm text-blue-600">Create borrowing request</p>
                                </div>
                            </div>
                        </a>
                        
                        <a href="borrowing-transactions.php" class="p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                            <div class="flex items-center">
                                <i class="fas fa-exchange-alt text-green-600 text-2xl mr-3"></i>
                                <div>
                                    <p class="font-medium text-green-900">Process Transaction</p>
                                    <p class="text-sm text-green-600">Handle borrow/return</p>
                                </div>
                            </div>
                        </a>
                        
                        <a href="return-items.php" class="p-4 bg-orange-50 rounded-lg hover:bg-orange-100 transition-colors">
                            <div class="flex items-center">
                                <i class="fas fa-undo text-orange-600 text-2xl mr-3"></i>
                                <div>
                                    <p class="font-medium text-orange-900">Return Items</p>
                                    <p class="text-sm text-orange-600">Process returns</p>
                                </div>
                            </div>
                        </a>
                        
                        <a href="reports.php" class="p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors">
                            <div class="flex items-center">
                                <i class="fas fa-chart-bar text-purple-600 text-2xl mr-3"></i>
                                <div>
                                    <p class="font-medium text-purple-900">View Reports</p>
                                    <p class="text-sm text-purple-600">Damage reports</p>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Recent Activity Table -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Recent Activity</h3>
                    <div class="flex space-x-2">
                        <span class="px-3 py-1 bg-blue-100 text-blue-800 text-xs font-medium rounded-full">
                            <?php echo $stats['recent_requests']; ?> new requests
                        </span>
                        <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-medium rounded-full">
                            <?php echo $stats['recent_transactions']; ?> new transactions
                        </span>
                        <span class="px-3 py-1 bg-orange-100 text-orange-800 text-xs font-medium rounded-full">
                            <?php echo $stats['recent_returns']; ?> new returns
                        </span>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Activity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($recentActivity as $activity): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-gray-500 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                                                <?php echo strtoupper(substr($activity['customer_name'], 0, 2)); ?>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($activity['customer_name']); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($activity['customer_email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars(substr($activity['description'], 0, 50)) . (strlen($activity['description']) > 50 ? '...' : ''); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $statusColors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'approved' => 'bg-blue-100 text-blue-800',
                                            'active' => 'bg-green-100 text-green-800',
                                            'completed' => 'bg-green-100 text-green-800',
                                            'overdue' => 'bg-red-100 text-red-800',
                                            'borrow' => 'bg-blue-100 text-blue-800',
                                            'return' => 'bg-green-100 text-green-800',
                                            'partial_return' => 'bg-orange-100 text-orange-800',
                                            'good' => 'bg-green-100 text-green-800',
                                            'damaged' => 'bg-orange-100 text-orange-800',
                                            'lost' => 'bg-red-100 text-red-800'
                                        ];
                                        $statusClass = $statusColors[$activity['status']] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $activity['status'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php 
                                        $date = new DateTime($activity['activity_date']);
                                        $now = new DateTime();
                                        $diff = $now->diff($date);
                                        
                                        if ($diff->days == 0) {
                                            if ($diff->h == 0) {
                                                echo $diff->i . ' minutes ago';
                                            } else {
                                                echo $diff->h . ' hours ago';
                                            }
                                        } else {
                                            echo $diff->days . ' days ago';
                                        }
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $typeColors = [
                                            'request' => 'bg-blue-100 text-blue-800',
                                            'transaction' => 'bg-green-100 text-green-800',
                                            'return' => 'bg-orange-100 text-orange-800'
                                        ];
                                        $typeClass = $typeColors[$activity['type']] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $typeClass; ?>">
                                            <?php echo ucfirst($activity['type']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script src="js/dashboard.js"></script>
    <script>
        // Chart data from PHP
        const requestsData = <?php echo json_encode($requestsChartData); ?>;
        const transactionTypeData = <?php echo json_encode($transactionTypeData); ?>;
        const requestStatusData = <?php echo json_encode($requestStatusData); ?>;
        const returnConditionData = <?php echo json_encode($returnConditionData); ?>;

        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Requests Trend Chart
            const requestsCtx = document.getElementById('requestsChart').getContext('2d');
            new Chart(requestsCtx, {
                type: 'line',
                data: {
                    labels: requestsData.map(item => {
                        const date = new Date(item.date);
                        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                    }),
                    datasets: [{
                        label: 'Requests',
                        data: requestsData.map(item => item.count),
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.1,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Transaction Types Chart
            const transactionTypesCtx = document.getElementById('transactionTypesChart').getContext('2d');
            new Chart(transactionTypesCtx, {
                type: 'doughnut',
                data: {
                    labels: transactionTypeData.map(item => item.transaction_type.replace('_', ' ').toUpperCase()),
                    datasets: [{
                        data: transactionTypeData.map(item => item.count),
                        backgroundColor: [
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(245, 158, 11, 0.8)',
                            'rgba(239, 68, 68, 0.8)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

            // Request Status Chart
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            new Chart(statusCtx, {
                type: 'bar',
                data: {
                    labels: requestStatusData.map(item => item.status.toUpperCase()),
                    datasets: [{
                        label: 'Requests',
                        data: requestStatusData.map(item => item.count),
                        backgroundColor: [
                            'rgba(245, 158, 11, 0.8)',
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(239, 68, 68, 0.8)',
                            'rgba(107, 114, 128, 0.8)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Return Condition Chart
            const returnConditionCtx = document.getElementById('returnConditionChart').getContext('2d');
            new Chart(returnConditionCtx, {
                type: 'pie',
                data: {
                    labels: returnConditionData.map(item => item.condition_status.toUpperCase()),
                    datasets: [{
                        data: returnConditionData.map(item => item.count),
                        backgroundColor: [
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(245, 158, 11, 0.8)',
                            'rgba(239, 68, 68, 0.8)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        });
    </script>
</body>
</html>
