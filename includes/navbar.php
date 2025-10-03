<?php 
// Determine the correct path prefix based on the current directory
$pathPrefix = '';
if (strpos($_SERVER['REQUEST_URI'], '/user-management/') !== false || 
    strpos($_SERVER['REQUEST_URI'], '/auth/') !== false || 
    strpos($_SERVER['REQUEST_URI'], '/inventory/') !== false) {
    $pathPrefix = '../';
}

if (!isset($currentAdmin)) {
    // Include auth with correct path
    if ($pathPrefix) {
        require_once $pathPrefix . 'includes/auth.php';
    } else {
        require_once 'includes/auth.php';
    }
    $currentAdmin = getLoggedInAdmin();
}

// Get the page title from the current file
$pageTitles = [
    'index.php' => 'Dashboard',
    'admin-management.php' => 'Admin Management',
    'customer-management.php' => 'Customer Management',
    'employee-management.php' => 'Employee Management',
    'materials.php' => 'Material Management',
    'categories.php' => 'Category Management',
    'locations.php' => 'Location Management',
    'inventory.php' => 'Inventory Management',
    'borrowing-requests.php' => 'Storage Requests',
    'transactions.php' => 'Transaction Management',
    'reports.php' => 'Reports & Analytics',
    'services.php' => 'Service Management'
];

$currentFile = basename($_SERVER['PHP_SELF']);
$pageTitle = $pageTitles[$currentFile] ?? 'Admin Dashboard';
?>

<!-- Top Navigation -->
<header class="bg-white shadow-lg">
    <div class="flex items-center justify-between px-6 py-4">
        <div class="flex items-center">
            <button onclick="toggleSidebar()" class="lg:hidden text-gray-500 hover:text-gray-700 focus:outline-none">
                <i class="fas fa-bars text-xl"></i>
            </button>
            <h1 class="ml-4 text-2xl font-semibold text-gray-800"><?php echo $pageTitle; ?></h1>
        </div>
        
        <div class="flex items-center space-x-4">
            <!-- Quick Actions (customizable per page) -->
            <?php if (isset($quickActions) && !empty($quickActions)): ?>
                <?php foreach ($quickActions as $action): ?>
                    <button onclick="<?php echo $action['onclick']; ?>" class="btn <?php echo $action['class']; ?>">
                        <i class="<?php echo $action['icon']; ?> mr-2"></i><?php echo $action['text']; ?>
                    </button>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- Notifications -->
            <div class="relative">
                <button onclick="toggleNotifications()" class="p-2 text-gray-400 hover:text-gray-600 focus:outline-none relative">
                    <i class="fas fa-bell text-xl"></i>
                    <span id="notification-badge" class="absolute -top-1 -right-1 h-4 w-4 bg-red-500 text-white text-xs rounded-full flex items-center justify-center hidden">3</span>
                </button>
                
                <!-- Notifications Dropdown -->
                <div id="notifications-dropdown" class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl border border-gray-200 z-50 hidden">
                    <div class="p-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">Notifications</h3>
                    </div>
                    <div class="max-h-64 overflow-y-auto">
                        <div id="notifications-list" class="divide-y divide-gray-200">
                            <!-- Notifications will be loaded here -->
                            <div class="p-4 text-center text-gray-500 text-sm">
                                No new notifications
                            </div>
                        </div>
                    </div>
                    <div class="p-3 border-t border-gray-200 text-center">
                        <a href="#" class="text-primary hover:text-secondary text-sm font-medium">View All Notifications</a>
                    </div>
                </div>
            </div>
            
            <!-- User Profile -->
            <div class="relative">
                <button onclick="toggleUserMenu()" class="flex items-center text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                    <div class="h-8 w-8 rounded-full bg-primary flex items-center justify-center text-white font-semibold">
                        <?php echo strtoupper(substr($currentAdmin['name'], 0, 1)); ?>
                    </div>
                    <span class="ml-2 text-gray-700 hidden md:block"><?php echo htmlspecialchars($currentAdmin['name']); ?></span>
                    <i class="fas fa-chevron-down ml-1 text-gray-400 hidden md:block"></i>
                </button>
                
                <div id="user-menu" class="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-xl border border-gray-200 py-2 z-50 hidden">
                    <!-- User Info Header -->
                    <div class="px-4 py-3 border-b border-gray-200">
                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($currentAdmin['name']); ?></p>
                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($currentAdmin['email']); ?></p>
                        <p class="text-xs text-gray-400 mt-1">
                            <i class="fas fa-shield-alt mr-1"></i>
                            <?php echo ucfirst(str_replace('-', ' ', $currentAdmin['role'])); ?>
                        </p>
                    </div>
                    
                    <!-- Menu Items -->
                    <div class="py-1">
                        <a href="#" onclick="showProfile()" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-200">
                            <i class="fas fa-user mr-3 text-gray-400"></i>My Profile
                        </a>
                        <a href="#" onclick="showSettings()" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-200">
                            <i class="fas fa-cog mr-3 text-gray-400"></i>Settings
                        </a>
                        <a href="#" onclick="showActivity()" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-200">
                            <i class="fas fa-clock mr-3 text-gray-400"></i>Activity Log
                        </a>
                        
                        <!-- Divider -->
                        <hr class="my-2 border-gray-200">
                        
                        <a href="#" onclick="showHelp()" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-200">
                            <i class="fas fa-question-circle mr-3 text-gray-400"></i>Help & Support
                        </a>
                        
                        <!-- Divider -->
                        <hr class="my-2 border-gray-200">
                        
                        <button onclick="confirmLogout()" class="w-full flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors duration-200">
                            <i class="fas fa-sign-out-alt mr-3 text-red-500"></i>Sign Out
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Profile Modal -->
<div id="profile-modal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('profile-modal')"></div>
    <div class="modal-content max-w-2xl">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-800">My Profile</h3>
            <button onclick="closeModal('profile-modal')" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="profile-form">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Profile Picture -->
                <div class="md:col-span-2 flex items-center space-x-6">
                    <div class="h-20 w-20 rounded-full bg-primary flex items-center justify-center text-white text-2xl font-bold">
                        <?php echo strtoupper(substr($currentAdmin['name'], 0, 1)); ?>
                    </div>
                    <div>
                        <h4 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($currentAdmin['name']); ?></h4>
                        <p class="text-sm text-gray-500"><?php echo ucfirst(str_replace('-', ' ', $currentAdmin['role'])); ?></p>
                        <button type="button" class="mt-2 text-sm text-primary hover:text-secondary">Change Picture</button>
                    </div>
                </div>
                
                <!-- Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                    <input type="text" id="profile-name" value="<?php echo htmlspecialchars($currentAdmin['name']); ?>" 
                           class="form-input w-full">
                </div>
                
                <!-- Email -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                    <input type="email" id="profile-email" value="<?php echo htmlspecialchars($currentAdmin['email']); ?>" 
                           class="form-input w-full" readonly>
                </div>
                
                <!-- Phone -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                    <input type="tel" id="profile-phone" placeholder="Enter phone number" 
                           class="form-input w-full">
                </div>
                
                <!-- Role (readonly) -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                    <input type="text" value="<?php echo ucfirst(str_replace('-', ' ', $currentAdmin['role'])); ?>" 
                           class="form-input w-full bg-gray-50" readonly>
                </div>
            </div>
            
            <!-- Password Change Section -->
            <div class="border-t border-gray-200 pt-6">
                <h4 class="text-md font-medium text-gray-900 mb-4">Change Password</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                        <input type="password" id="current-password" class="form-input w-full">
                    </div>
                    <div></div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                        <input type="password" id="new-password" class="form-input w-full">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                        <input type="password" id="confirm-new-password" class="form-input w-full">
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 mt-6">
                <button type="button" onclick="closeModal('profile-modal')" class="btn btn-secondary">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save mr-2"></i>Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Navbar functionality
function toggleUserMenu() {
    const userMenu = document.getElementById('user-menu');
    const notificationsDropdown = document.getElementById('notifications-dropdown');
    
    // Close notifications if open
    notificationsDropdown.classList.add('hidden');
    
    userMenu.classList.toggle('hidden');
    
    // Close menu when clicking outside
    if (!userMenu.classList.contains('hidden')) {
        document.addEventListener('click', function closeUserMenu(e) {
            if (!e.target.closest('#user-menu') && !e.target.closest('button[onclick="toggleUserMenu()"]')) {
                userMenu.classList.add('hidden');
                document.removeEventListener('click', closeUserMenu);
            }
        });
    }
}

function toggleNotifications() {
    const notificationsDropdown = document.getElementById('notifications-dropdown');
    const userMenu = document.getElementById('user-menu');
    
    // Close user menu if open
    userMenu.classList.add('hidden');
    
    notificationsDropdown.classList.toggle('hidden');
    
    // Load notifications if opening
    if (!notificationsDropdown.classList.contains('hidden')) {
        loadNotifications();
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function closeNotifications(e) {
            if (!e.target.closest('#notifications-dropdown') && !e.target.closest('button[onclick="toggleNotifications()"]')) {
                notificationsDropdown.classList.add('hidden');
                document.removeEventListener('click', closeNotifications);
            }
        });
    }
}

function showProfile() {
    document.getElementById('user-menu').classList.add('hidden');
    openModal('profile-modal');
    loadProfileData();
}

function showSettings() {
    document.getElementById('user-menu').classList.add('hidden');
    // Implement settings modal
    showNotification('Settings page coming soon!', 'info');
}

function showActivity() {
    document.getElementById('user-menu').classList.add('hidden');
    // Implement activity log modal
    showNotification('Activity log coming soon!', 'info');
}

function showHelp() {
    document.getElementById('user-menu').classList.add('hidden');
    // Implement help modal
    showNotification('Help & Support coming soon!', 'info');
}

async function confirmLogout() {
    document.getElementById('user-menu').classList.add('hidden');
    
    if (confirm('Are you sure you want to sign out?')) {
        try {
            const response = await fetch('<?php echo $pathPrefix; ?>api/auth.php?action=logout', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                showNotification('Signing out...', 'success');
                setTimeout(() => {
                    window.location.href = '<?php echo $pathPrefix; ?>auth/login.php';
                }, 1000);
            } else {
                showNotification('Logout failed. Please try again.', 'error');
            }
        } catch (error) {
            console.error('Logout error:', error);
            showNotification('Network error occurred during logout.', 'error');
        }
    }
}

async function loadNotifications() {
    try {
        // Mock notifications - replace with actual API call
        const notifications = [
            {
                id: 1,
                title: 'New borrowing request',
                message: 'Customer John Doe submitted a new request',
                time: '5 minutes ago',
                type: 'info',
                unread: true
            },
            {
                id: 2,
                title: 'Low stock alert',
                message: 'Material "Steel Rods" is running low',
                time: '1 hour ago',
                type: 'warning',
                unread: true
            },
            {
                id: 3,
                title: 'System backup completed',
                message: 'Daily backup completed successfully',
                time: '2 hours ago',
                type: 'success',
                unread: false
            }
        ];
        
        const notificationsList = document.getElementById('notifications-list');
        const unreadCount = notifications.filter(n => n.unread).length;
        
        // Update badge
        const badge = document.getElementById('notification-badge');
        if (unreadCount > 0) {
            badge.textContent = unreadCount;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
        
        // Render notifications
        if (notifications.length > 0) {
            notificationsList.innerHTML = notifications.map(notification => `
                <div class="p-4 hover:bg-gray-50 ${notification.unread ? 'bg-blue-50' : ''}">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <div class="h-8 w-8 rounded-full flex items-center justify-center ${
                                notification.type === 'info' ? 'bg-blue-100 text-blue-600' :
                                notification.type === 'warning' ? 'bg-yellow-100 text-yellow-600' :
                                'bg-green-100 text-green-600'
                            }">
                                <i class="fas ${
                                    notification.type === 'info' ? 'fa-info' :
                                    notification.type === 'warning' ? 'fa-exclamation-triangle' :
                                    'fa-check'
                                } text-sm"></i>
                            </div>
                        </div>
                        <div class="ml-3 flex-1">
                            <p class="text-sm font-medium text-gray-900">${notification.title}</p>
                            <p class="text-sm text-gray-500">${notification.message}</p>
                            <p class="text-xs text-gray-400 mt-1">${notification.time}</p>
                        </div>
                        ${notification.unread ? '<div class="flex-shrink-0"><div class="h-2 w-2 bg-blue-500 rounded-full"></div></div>' : ''}
                    </div>
                </div>
            `).join('');
        }
        
    } catch (error) {
        console.error('Error loading notifications:', error);
    }
}

async function loadProfileData() {
    try {
        // Load additional profile data from API if needed
        const response = await fetch('<?php echo $pathPrefix; ?>api/auth.php?action=check');
        const result = await response.json();
        
        if (result.authenticated && result.user) {
            // Populate additional fields if available
            if (result.user.phone) {
                document.getElementById('profile-phone').value = result.user.phone;
            }
        }
    } catch (error) {
        console.error('Error loading profile data:', error);
    }
}

// Handle profile form submission
document.getElementById('profile-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = {
        name: document.getElementById('profile-name').value,
        phone: document.getElementById('profile-phone').value,
        current_password: document.getElementById('current-password').value,
        new_password: document.getElementById('new-password').value,
        confirm_new_password: document.getElementById('confirm-new-password').value
    };
    
    // Validate password change if provided
    if (formData.new_password) {
        if (!formData.current_password) {
            showNotification('Current password is required to change password', 'error');
            return;
        }
        
        if (formData.new_password !== formData.confirm_new_password) {
            showNotification('New passwords do not match', 'error');
            return;
        }
        
        if (formData.new_password.length < 8) {
            showNotification('New password must be at least 8 characters long', 'error');
            return;
        }
    }
    
    try {
        const response = await fetch('<?php echo $pathPrefix; ?>api/profile.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        });
        
        const result = await response.json();
        
        if (response.ok && result.success) {
            showNotification('Profile updated successfully!', 'success');
            closeModal('profile-modal');
            
            // Update displayed name if changed
            if (formData.name !== '<?php echo addslashes($currentAdmin['name']); ?>') {
                setTimeout(() => location.reload(), 1000);
            }
        } else {
            showNotification(result.error || 'Failed to update profile', 'error');
        }
    } catch (error) {
        console.error('Profile update error:', error);
        showNotification('Network error occurred', 'error');
    }
});
</script>
