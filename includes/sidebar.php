<?php 
// Determine the correct path prefix based on the current directory
$pathPrefix = '';
if (strpos($_SERVER['REQUEST_URI'], '/user-management/') !== false || 
    strpos($_SERVER['REQUEST_URI'], '/auth/') !== false || 
    strpos($_SERVER['REQUEST_URI'], '/inventory/') !== false) {
    $pathPrefix = '../';
}

// Include auth with correct path
if ($pathPrefix) {
    require_once $pathPrefix . 'includes/auth.php';
} else {
    require_once 'includes/auth.php';
}

$currentAdmin = getLoggedInAdmin();
?>
<!-- Sidebar -->
<div id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-dark text-white transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out">
    <div class="flex items-center justify-center h-16 bg-secondary">
        <i class="fas fa-warehouse text-2xl mr-2"></i>
        <span class="text-xl font-bold">Warehouse Admin</span>
    </div>
    
    <!-- User Info -->
    <?php if ($currentAdmin): ?>
    <div class="px-4 py-3 bg-gray-800 border-b border-gray-700">
        <div class="flex items-center">
            <div class="w-10 h-10 bg-primary rounded-full flex items-center justify-center mr-3">
                <i class="fas fa-user text-white"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-white truncate">
                    <?php echo htmlspecialchars($currentAdmin['name']); ?>
                </p>
                <p class="text-xs text-gray-400 truncate">
                    <?php echo ucfirst(str_replace('-', ' ', $currentAdmin['role'])); ?>
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <nav class="mt-8">
        <div class="px-4 space-y-2">
            <a href="<?php echo $pathPrefix; ?>index.php" class="nav-link flex items-center px-4 py-3 text-gray-300 hover:bg-gray-700 hover:text-white rounded-lg transition-colors duration-200 <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt mr-3"></i>
                Dashboard
            </a>
            
            <div class="nav-group">
                <div class="nav-group-header flex items-center px-4 py-3 text-gray-300 hover:bg-gray-700 hover:text-white rounded-lg cursor-pointer" onclick="toggleNavGroup('user-management')">
                    <i class="fas fa-users mr-3"></i>
                    User Management
                    <i class="fas fa-chevron-down ml-auto transform transition-transform"></i>
                </div>
                <div id="user-management" class="nav-group-content ml-6 space-y-1 <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['admin-management.php', 'employee-management.php', 'customer-management.php'])) ? '' : 'hidden'; ?>">
                    <a href="<?php echo $pathPrefix; ?>user-management/admin-management.php" class="nav-link flex items-center px-4 py-2 text-gray-400 hover:bg-gray-700 hover:text-white rounded-lg <?php echo (basename($_SERVER['PHP_SELF']) == 'admin-management.php') ? 'active' : ''; ?>">
                        <i class="fas fa-user-shield mr-3"></i>
                        Admins
                    </a>
                    <a href="<?php echo $pathPrefix; ?>user-management/employee-management.php" class="nav-link flex items-center px-4 py-2 text-gray-400 hover:bg-gray-700 hover:text-white rounded-lg <?php echo (basename($_SERVER['PHP_SELF']) == 'employee-management.php') ? 'active' : ''; ?>">
                        <i class="fas fa-user-tie mr-3"></i>
                        Employees
                    </a>
                    <a href="<?php echo $pathPrefix; ?>user-management/customer-management.php" class="nav-link flex items-center px-4 py-2 text-gray-400 hover:bg-gray-700 hover:text-white rounded-lg <?php echo (basename($_SERVER['PHP_SELF']) == 'customer-management.php') ? 'active' : ''; ?>">
                        <i class="fas fa-user mr-3"></i>
                        Customers
                    </a>
                </div>
            </div>

            <div class="nav-group">
                <div class="nav-group-header flex items-center px-4 py-3 text-gray-300 hover:bg-gray-700 hover:text-white rounded-lg cursor-pointer" onclick="toggleNavGroup('inventory-management')">
                    <i class="fas fa-boxes mr-3"></i>
                    Inventory
                    <i class="fas fa-chevron-down ml-auto transform transition-transform"></i>
                </div>
                <div id="inventory-management" class="nav-group-content ml-6 space-y-1 <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['materials.php', 'categories.php', 'locations.php', 'inventory.php'])) ? '' : 'hidden'; ?>">
                    <a href="<?php echo $pathPrefix; ?>inventory/materials.php" class="nav-link flex items-center px-4 py-2 text-gray-400 hover:bg-gray-700 hover:text-white rounded-lg <?php echo (basename($_SERVER['PHP_SELF']) == 'materials.php') ? 'active' : ''; ?>">
                        <i class="fas fa-cube mr-3"></i>
                        Materials
                    </a>
                    <a href="<?php echo $pathPrefix; ?>inventory/categories.php" class="nav-link flex items-center px-4 py-2 text-gray-400 hover:bg-gray-700 hover:text-white rounded-lg <?php echo (basename($_SERVER['PHP_SELF']) == 'categories.php') ? 'active' : ''; ?>">
                        <i class="fas fa-tags mr-3"></i>
                        Categories
                    </a>
                    <a href="<?php echo $pathPrefix; ?>inventory/locations.php" class="nav-link flex items-center px-4 py-2 text-gray-400 hover:bg-gray-700 hover:text-white rounded-lg <?php echo (basename($_SERVER['PHP_SELF']) == 'locations.php') ? 'active' : ''; ?>">
                        <i class="fas fa-map-marker-alt mr-3"></i>
                        Locations
                    </a>
                    <a href="<?php echo $pathPrefix; ?>inventory/inventory.php" class="nav-link flex items-center px-4 py-2 text-gray-400 hover:bg-gray-700 hover:text-white rounded-lg <?php echo (basename($_SERVER['PHP_SELF']) == 'inventory.php') ? 'active' : ''; ?>">
                        <i class="fas fa-warehouse mr-3"></i>
                        Stock Levels
                    </a>
                </div>
            </div>

            <a href="<?php echo $pathPrefix; ?>borrowing-requests.php" class="nav-link flex items-center px-4 py-3 text-gray-300 hover:bg-gray-700 hover:text-white rounded-lg <?php echo (basename($_SERVER['PHP_SELF']) == 'borrowing-requests.php') ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-list mr-3"></i>
                Borrowing Requests
            </a>

            <a href="<?php echo $pathPrefix; ?>transactions.php" class="nav-link flex items-center px-4 py-3 text-gray-300 hover:bg-gray-700 hover:text-white rounded-lg <?php echo (basename($_SERVER['PHP_SELF']) == 'transactions.php') ? 'active' : ''; ?>">
                <i class="fas fa-exchange-alt mr-3"></i>
                Transactions
            </a>

            <a href="<?php echo $pathPrefix; ?>reports.php" class="nav-link flex items-center px-4 py-3 text-gray-300 hover:bg-gray-700 hover:text-white rounded-lg <?php echo (basename($_SERVER['PHP_SELF']) == 'reports.php') ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar mr-3"></i>
                Reports
            </a>

            <a href="<?php echo $pathPrefix; ?>services.php" class="nav-link flex items-center px-4 py-3 text-gray-300 hover:bg-gray-700 hover:text-white rounded-lg <?php echo (basename($_SERVER['PHP_SELF']) == 'services.php') ? 'active' : ''; ?>">
                <i class="fas fa-cogs mr-3"></i>
                Services
            </a>
        </div>
        
        <!-- Logout Section -->
        <?php if ($currentAdmin): ?>
        <div class="px-4 mt-8 pt-4 border-t border-gray-700">
            <button onclick="logout()" class="w-full flex items-center px-4 py-3 text-gray-300 hover:bg-red-600 hover:text-white rounded-lg transition-colors duration-200">
                <i class="fas fa-sign-out-alt mr-3"></i>
                Logout
            </button>
        </div>
        <?php endif; ?>
    </nav>
</div>

<!-- Mobile menu overlay -->
<div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>

<script>
// Logout function
async function logout() {
    if (confirm('Are you sure you want to logout?')) {
        try {
            const response = await fetch('<?php echo $pathPrefix; ?>api/auth.php?action=logout', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                window.location.href = '<?php echo $pathPrefix; ?>auth/login.php';
            } else {
                alert('Logout failed. Please try again.');
            }
        } catch (error) {
            console.error('Logout error:', error);
            alert('Network error occurred during logout.');
        }
    }
}
</script>
