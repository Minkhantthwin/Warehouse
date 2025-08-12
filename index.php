<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Check remember me token
checkRememberMe();

// Require login
requireLogin();

$currentAdmin = getLoggedInAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse Admin Dashboard</title>
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
            <!-- Dashboard Section -->
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                <i class="fas fa-clipboard-list text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h2 class="text-sm font-medium text-gray-600">Pending Requests</h2>
                                <p class="text-2xl font-semibold text-gray-900">24</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-600">
                                <i class="fas fa-check-circle text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h2 class="text-sm font-medium text-gray-600">Active Borrowings</h2>
                                <p class="text-2xl font-semibold text-gray-900">156</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                                <i class="fas fa-exclamation-triangle text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h2 class="text-sm font-medium text-gray-600">Overdue Items</h2>
                                <p class="text-2xl font-semibold text-gray-900">8</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                                <i class="fas fa-boxes text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h2 class="text-sm font-medium text-gray-600">Total Materials</h2>
                                <p class="text-2xl font-semibold text-gray-900">1,247</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts and Recent Activity -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Recent Requests Chart -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Borrowing Requests</h3>
                        <div class="h-64 flex items-center justify-center bg-gray-50 rounded-lg">
                            <div class="text-center">
                                <i class="fas fa-chart-line text-4xl text-gray-400 mb-2"></i>
                                <p class="text-gray-500">Chart will be implemented here</p>
                            </div>
                        </div>
                    </div>

                    <!-- Inventory Status -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Low Stock Alert</h3>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg">
                                <div>
                                    <p class="font-medium text-gray-800">Construction Tools</p>
                                    <p class="text-sm text-gray-600">Location: Warehouse A</p>
                                </div>
                                <span class="px-2 py-1 bg-red-100 text-red-800 text-xs font-medium rounded-full">5 left</span>
                            </div>
                            <div class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg">
                                <div>
                                    <p class="font-medium text-gray-800">Safety Equipment</p>
                                    <p class="text-sm text-gray-600">Location: Warehouse B</p>
                                </div>
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-medium rounded-full">12 left</span>
                            </div>
                            <div class="flex items-center justify-between p-3 bg-orange-50 rounded-lg">
                                <div>
                                    <p class="font-medium text-gray-800">Office Supplies</p>
                                    <p class="text-sm text-gray-600">Location: Main Office</p>
                                </div>
                                <span class="px-2 py-1 bg-orange-100 text-orange-800 text-xs font-medium rounded-full">8 left</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity Table -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Recent Activity</h3>
                        <a href="#" class="text-primary hover:text-secondary text-sm font-medium">View All</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <img class="h-8 w-8 rounded-full" src="https://ui-avatars.com/api/?name=John+Doe&background=random" alt="">
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">John Doe</div>
                                                <div class="text-sm text-gray-500">john@example.com</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">Borrowed Construction Tools</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2 hours ago</td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <img class="h-8 w-8 rounded-full" src="https://ui-avatars.com/api/?name=Jane+Smith&background=random" alt="">
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">Jane Smith</div>
                                                <div class="text-sm text-gray-500">jane@example.com</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">Returned Office Supplies</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">Returned</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">5 hours ago</td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <img class="h-8 w-8 rounded-full" src="https://ui-avatars.com/api/?name=Bob+Wilson&background=random" alt="">
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">Bob Wilson</div>
                                                <div class="text-sm text-gray-500">bob@example.com</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">New Borrowing Request</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">1 day ago</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            

            <!-- Placeholder sections for other pages -->
            <div id="admins-section" class="section hidden">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Admin Management</h2>
                    <p class="text-gray-600">Admin management interface will be implemented here.</p>
                </div>
            </div>

            <div id="employees-section" class="section hidden">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Employee Management</h2>
                    <p class="text-gray-600">Employee management interface will be implemented here.</p>
                </div>
            </div>

            <div id="customers-section" class="section hidden">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Customer Management</h2>
                    <p class="text-gray-600">Customer management interface will be implemented here.</p>
                </div>
            </div>

            <div id="materials-section" class="section hidden">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Material Management</h2>
                    <p class="text-gray-600">Material management interface will be implemented here.</p>
                </div>
            </div>

            <div id="categories-section" class="section hidden">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Category Management</h2>
                    <p class="text-gray-600">Category management interface will be implemented here.</p>
                </div>
            </div>

            <div id="locations-section" class="section hidden">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Location Management</h2>
                    <p class="text-gray-600">Location management interface will be implemented here.</p>
                </div>
            </div>

            <div id="inventory-section" class="section hidden">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Inventory Management</h2>
                    <p class="text-gray-600">Inventory management interface will be implemented here.</p>
                </div>
            </div>

            <div id="borrowing-requests-section" class="section hidden">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Borrowing Requests</h2>
                    <p class="text-gray-600">Borrowing requests interface will be implemented here.</p>
                </div>
            </div>

            <div id="transactions-section" class="section hidden">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Transaction Management</h2>
                    <p class="text-gray-600">Transaction management interface will be implemented here.</p>
                </div>
            </div>

            <div id="reports-section" class="section hidden">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Reports & Analytics</h2>
                    <p class="text-gray-600">Reports and analytics interface will be implemented here.</p>
                </div>
            </div>

            <div id="services-section" class="section hidden">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Service Management</h2>
                    <p class="text-gray-600">Service management interface will be implemented here.</p>
                </div>
            </div>
        </main>
    </div>

    <script src="js/dashboard.js"></script>
</body>
</html>
