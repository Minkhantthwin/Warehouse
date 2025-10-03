<?php
session_start();
require_once '../includes/config.php';

// Check if customer is logged in
if (!isset($_SESSION['customer_id'])) {
    header('Location: index.php');
    exit();
}

$customer_id = $_SESSION['customer_id'];
$request_id = $_GET['id'] ?? null;

if (!$request_id) {
    header('Location: index.php');
    exit();
}

// Get request details
$stmt = $pdo->prepare("
    SELECT br.*, 
           c.name as customer_name, 
           c.email as customer_email,
           e.name as employee_name,
           l.name as location_name, 
           l.city as location_city,
           a.name as approved_by_name
    FROM Borrowing_Request br
    LEFT JOIN Customer c ON br.customer_id = c.id
    LEFT JOIN Employee e ON br.employee_id = e.id
    LEFT JOIN Location l ON br.location_id = l.id
    LEFT JOIN Admin a ON br.approved_by = a.id
    WHERE br.id = ? AND br.customer_id = ?
");
$stmt->execute([$request_id, $customer_id]);
$request = $stmt->fetch();

if (!$request) {
    header('Location: index.php');
    exit();
}

// Get borrowing items
$itemsStmt = $pdo->prepare("
    SELECT bi.*, bit.name as type_name, bit.unit
    FROM Borrowing_Items bi
    LEFT JOIN Borrowing_Item_Types bit ON bi.item_type_id = bit.id
    WHERE bi.borrowing_request_id = ?
");
$itemsStmt->execute([$request_id]);
$items = $itemsStmt->fetchAll();

// Get transaction history
$transactionsStmt = $pdo->prepare("
    SELECT bt.*, e.name as processed_by_name
    FROM Borrowing_Transaction bt
    LEFT JOIN Employee e ON bt.processed_by = e.id
    WHERE bt.borrowing_request_id = ?
    ORDER BY bt.transaction_date DESC
");
$transactionsStmt->execute([$request_id]);
$transactions = $transactionsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Details - Vault-X</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#2563EB',
                        secondary: '#1E40AF',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="index.php" class="flex items-center">
                        <i class="fas fa-warehouse text-2xl text-primary mr-2"></i>
                        <span class="text-xl font-bold text-gray-900">Vault-X</span>
                    </a>
                </div>
                <div class="flex items-center">
                    <a href="index.php" class="text-gray-600 hover:text-primary">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Request Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">Request #<?php echo $request['id']; ?></h1>
                    <p class="text-gray-600">Submitted on <?php echo date('F j, Y \a\t g:i A', strtotime($request['request_date'])); ?></p>
                </div>
                <div>
                    <?php
                    $statusColors = [
                        'pending' => 'bg-yellow-100 text-yellow-800',
                        'approved' => 'bg-blue-100 text-blue-800',
                        'rejected' => 'bg-red-100 text-red-800',
                        'active' => 'bg-green-100 text-green-800',
                        'returned' => 'bg-gray-100 text-gray-800',
                        'overdue' => 'bg-red-100 text-red-800'
                    ];
                    $statusClass = $statusColors[$request['status']] ?? 'bg-gray-100 text-gray-800';
                    ?>
                    <span class="px-4 py-2 rounded-full text-sm font-semibold <?php echo $statusClass; ?>">
                        <?php echo ucfirst($request['status']); ?>
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-2">Purpose</h3>
                    <p class="text-gray-900"><?php echo htmlspecialchars($request['purpose']); ?></p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-2">Required Date</h3>
                    <p class="text-gray-900"><?php echo date('F j, Y \a\t g:i A', strtotime($request['required_date'])); ?></p>
                </div>
                <?php if ($request['location_name']): ?>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-2">Location</h3>
                    <p class="text-gray-900"><?php echo htmlspecialchars($request['location_name'] . ', ' . $request['location_city']); ?></p>
                </div>
                <?php endif; ?>
                <?php if ($request['approved_by_name']): ?>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-2">Approved By</h3>
                    <p class="text-gray-900"><?php echo htmlspecialchars($request['approved_by_name']); ?></p>
                    <p class="text-sm text-gray-500"><?php echo date('F j, Y', strtotime($request['approved_date'])); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($request['notes']): ?>
            <div class="mt-6">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Additional Notes</h3>
                <p class="text-gray-900"><?php echo htmlspecialchars($request['notes']); ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Requested Items -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Requested Items</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Requested</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Approved</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Borrowed</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Value</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($item['type_name'] ?? 'N/A'); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <?php echo htmlspecialchars($item['item_description']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $item['quantity_requested']; ?> <?php echo htmlspecialchars($item['unit'] ?? 'pcs'); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $item['quantity_approved'] ?? '-'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $item['quantity_borrowed'] ?? '-'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                $<?php echo number_format($item['estimated_value'], 2); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Transaction History -->
        <?php if (!empty($transactions)): ?>
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Transaction History</h2>
            <div class="space-y-4">
                <?php foreach ($transactions as $transaction): ?>
                <div class="border-l-4 border-primary pl-4 py-2">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-semibold text-gray-900">
                                <?php 
                                $transactionTypes = [
                                    'borrow' => 'Items Borrowed',
                                    'return' => 'Items Returned',
                                    'partial_return' => 'Partial Return'
                                ];
                                echo $transactionTypes[$transaction['transaction_type']] ?? ucfirst($transaction['transaction_type']);
                                ?>
                            </p>
                            <p class="text-sm text-gray-600">
                                Processed by: <?php echo htmlspecialchars($transaction['processed_by_name']); ?>
                            </p>
                            <?php if ($transaction['notes']): ?>
                            <p class="text-sm text-gray-700 mt-1"><?php echo htmlspecialchars($transaction['notes']); ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="text-sm text-gray-500">
                            <?php echo date('M j, Y g:i A', strtotime($transaction['transaction_date'])); ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 text-white ${getNotificationColor(type)}`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
        
        function getNotificationColor(type) {
            switch (type) {
                case 'success': return 'bg-green-500';
                case 'error': return 'bg-red-500';
                case 'warning': return 'bg-yellow-500';
                case 'info': return 'bg-blue-500';
                default: return 'bg-gray-500';
            }
        }
    </script>
</body>
</html>
