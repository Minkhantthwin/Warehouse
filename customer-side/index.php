<?php
session_start();
require_once '../includes/config.php';

// Check if customer is logged in
$isLoggedIn = isset($_SESSION['customer_id']);
$customer = null;

if ($isLoggedIn) {
    $stmt = $pdo->prepare("SELECT * FROM Customer WHERE id = ?");
    $stmt->execute([$_SESSION['customer_id']]);
    $customer = $stmt->fetch();
}

// Get available item types for borrowing
$itemTypesStmt = $pdo->query("SELECT * FROM Borrowing_Item_Types ORDER BY name");
$itemTypes = $itemTypesStmt->fetchAll();

// Get customer's recent requests if logged in
$recentRequests = [];
if ($isLoggedIn) {
    $stmt = $pdo->prepare("
        SELECT br.*, COUNT(bi.id) as item_count
        FROM Borrowing_Request br
        LEFT JOIN Borrowing_Items bi ON br.id = bi.borrowing_request_id
        WHERE br.customer_id = ?
        GROUP BY br.id
        ORDER BY br.request_date DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['customer_id']]);
    $recentRequests = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vault-X - Storage Management Service</title>
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
                        accent: '#F59E0B',
                        success: '#10B981',
                        warning: '#F59E0B',
                        danger: '#EF4444'
                    }
                }
            }
        }
    </script>
    <style>
        .hero-gradient {
            background: linear-gradient(135deg, #2563EB 0%, #1E40AF 100%);
        }
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 50;
            overflow-y: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            border-radius: 0.5rem;
            max-width: 28rem;
            width: 90%;
            margin: 2rem;
            padding: 1.5rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .floating-label {
            position: relative;
        }
        .floating-label input:focus + label,
        .floating-label input:not(:placeholder-shown) + label {
            transform: translateY(-1.5rem) scale(0.75);
            color: #2563EB;
        }
        .floating-label label {
            position: absolute;
            left: 0.75rem;
            top: 0.75rem;
            transition: all 0.2s;
            pointer-events: none;
            color: #6B7280;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-warehouse text-2xl text-primary mr-2"></i>
                        <span class="text-xl font-bold text-gray-900">Vault-X</span>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <?php if ($isLoggedIn): ?>
                        <div class="flex items-center space-x-3">
                            <div class="flex items-center space-x-2">
                                <img class="h-8 w-8 rounded-full" src="https://ui-avatars.com/api/?name=<?php echo urlencode($customer['name']); ?>&background=2563EB&color=fff" alt="">
                                <span class="text-gray-700 font-medium"><?php echo htmlspecialchars($customer['name']); ?></span>
                            </div>
                            <button onclick="showProfile()" class="text-gray-600 hover:text-primary">
                                <i class="fas fa-user"></i>
                            </button>
                            <button onclick="logout()" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition-colors">
                                <i class="fas fa-sign-out-alt mr-1"></i> Logout
                            </button>
                        </div>
                    <?php else: ?>
                        <button onclick="showLogin()" class="text-primary hover:text-secondary font-medium">Login</button>
                        <button onclick="showRegister()" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-secondary transition-colors">Sign Up</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-gradient text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <div class="text-center">
                <h1 class="text-4xl md:text-6xl font-bold mb-6">
                    Storage to Storage<br>
                    <span class="text-yellow-300">Management Service</span>
                </h1>
                <p class="text-xl md:text-2xl mb-8 text-blue-100">
                    Access quality storage for your equipment and tools during your travels, without the upfront investment
                </p>
                <?php if ($isLoggedIn): ?>
                    <button onclick="showBorrowRequest()" class="bg-yellow-500 text-blue-900 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-yellow-400 transition-colors btn-pulse">
                        <i class="fas fa-plus mr-2"></i> Storage Request
                    </button>
                <?php else: ?>
                    <button onclick="showRegister()" class="bg-yellow-500 text-blue-900 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-yellow-400 transition-colors">
                        Get Started Today
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">Why Choose Our Service?</h2>
                <p class="text-lg text-gray-600">Item storing made simple and reliable</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center p-6">
                    <div class="bg-blue-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4 feature-icon">
                        <i class="fas fa-tools text-2xl text-primary"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Quality Storages</h3>
                    <p class="text-gray-600">Access to well-maintained, professional-grade storages and rooms</p>
                </div>
                
                <div class="text-center p-6">
                    <div class="bg-green-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4 feature-icon">
                        <i class="fas fa-clock text-2xl text-green-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Flexible Terms</h3>
                    <p class="text-gray-600">Store for the exact duration you need, from days to months</p>
                </div>
                
                <div class="text-center p-6">
                    <div class="bg-yellow-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4 feature-icon">
                        <i class="fas fa-handshake text-2xl text-yellow-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Reliable Service</h3>
                    <p class="text-gray-600">Professional support and timely delivery when you need it</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Equipment Categories -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">Select Your Items</h2>
                <p class="text-lg text-gray-600">That you want to store!</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($itemTypes as $itemType): ?>
                <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow p-6 equipment-card">
                    <div class="flex items-center mb-4">
                        <div class="bg-blue-100 rounded-lg w-12 h-12 flex items-center justify-center mr-4 feature-icon">
                            <i class="fas fa-tools text-primary"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($itemType['name']); ?></h3>
                            <p class="text-sm text-gray-500">Starting from $<?php echo number_format($itemType['estimated_value'], 2); ?></p>
                        </div>
                    </div>
                    <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($itemType['description']); ?></p>
                    <?php if ($isLoggedIn): ?>
                        <button onclick="requestItemType(<?php echo $itemType['id']; ?>, '<?php echo htmlspecialchars($itemType['name']); ?>')" 
                                class="w-full bg-primary text-white py-2 rounded-lg hover:bg-secondary transition-colors">
                            Store this Item
                        </button>
                    <?php else: ?>
                        <button onclick="showLogin()" class="w-full bg-gray-200 text-gray-700 py-2 rounded-lg hover:bg-gray-300 transition-colors">
                            Login to Store your Item
                        </button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php if ($isLoggedIn && !empty($recentRequests)): ?>
    <!-- Recent Requests -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">Your Recent Requests</h2>
                <p class="text-lg text-gray-600">Track your storage requests and their status</p>
            </div>
            
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="table-responsive">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purpose</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Required Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($recentRequests as $request): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo date('M j, Y', strtotime($request['request_date'])); ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <?php echo htmlspecialchars($request['purpose']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $request['item_count']; ?> item(s)
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $statusColors = [
                                        'pending' => 'status-pending',
                                        'approved' => 'status-approved',
                                        'rejected' => 'status-rejected',
                                        'active' => 'status-active',
                                        'returned' => 'status-returned',
                                        'overdue' => 'status-overdue'
                                    ];
                                    $statusClass = $statusColors[$request['status']] ?? 'status-pending';
                                    ?>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo ucfirst($request['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo date('M j, Y', strtotime($request['required_date'])); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div>
                    <div class="flex items-center mb-4">
                        <i class="fas fa-warehouse text-2xl text-primary mr-2"></i>
                        <span class="text-xl font-bold">Vault-X</span>
                    </div>
                    <p class="text-gray-400">Professional storage service for businesses of all sizes.</p>
                </div>
                
                <div>
                    <h3 class="text-lg font-semibold mb-4">Contact Info</h3>
                    <div class="space-y-2 text-gray-400">
                        <p><i class="fas fa-phone mr-2"></i> +1 (555) 123-4567</p>
                        <p><i class="fas fa-envelope mr-2"></i> info@warehousesolutions.com</p>
                        <p><i class="fas fa-map-marker-alt mr-2"></i> 1234 Industrial Blvd, Los Angeles, CA</p>
                    </div>
                </div>
                
                <div>
                    <h3 class="text-lg font-semibold mb-4">Business Hours</h3>
                    <div class="space-y-2 text-gray-400">
                        <p>Monday - Friday: 8:00 AM - 6:00 PM</p>
                        <p>Saturday: 9:00 AM - 4:00 PM</p>
                        <p>Sunday: Closed</p>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-800 mt-8 pt-8 text-center">
                <p class="text-gray-400">&copy; 2025 Vault-X. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Authentication Modal -->
    <div id="auth-modal" class="modal">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-6">
                <h2 id="auth-title" class="text-2xl font-bold text-gray-900">Login</h2>
                <button onclick="closeModal('auth-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <!-- Login Form -->
            <form id="login-form" style="display: block;">
                <div class="space-y-4">
                    <div class="floating-label">
                        <input type="email" id="login-email" name="email" placeholder=" " class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary" required>
                        <label for="login-email">Email Address</label>
                    </div>
                    
                    <div class="floating-label">
                        <input type="password" id="login-password" name="password" placeholder=" " class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary" required>
                        <label for="login-password">Password</label>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <label class="flex items-center">
                            <input type="checkbox" name="remember" class="rounded border-gray-300 text-primary">
                            <span class="ml-2 text-sm text-gray-600">Remember me</span>
                        </label>
                        <a href="#" class="text-sm text-primary hover:underline">Forgot password?</a>
                    </div>
                    
                    <button type="submit" class="w-full bg-primary text-white py-3 rounded-lg hover:bg-secondary transition-colors">
                        Sign In
                    </button>
                </div>
                
                <div class="mt-6 text-center">
                    <p class="text-gray-600">Don't have an account? 
                        <button type="button" onclick="switchToRegister()" class="text-primary hover:underline">Sign up</button>
                    </p>
                </div>
            </form>
            
            <!-- Register Form -->
            <form id="register-form" style="display: none;">
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="floating-label">
                            <input type="text" id="register-name" name="name" placeholder=" " class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary" required>
                            <label for="register-name">Full Name</label>
                        </div>
                        
                        <div class="floating-label">
                            <input type="email" id="register-email" name="email" placeholder=" " class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary" required>
                            <label for="register-email">Email Address</label>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="floating-label">
                            <input type="tel" id="register-phone" name="phone" placeholder=" " class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary" required>
                            <label for="register-phone">Phone Number</label>
                        </div>
                        
                        <div>
                            <select name="customer_type" class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary" required>
                                <option value="">Customer Type</option>
                                <option value="retail">Retail</option>
                                <option value="wholesale">Wholesale</option>
                                <option value="corporate">Corporate</option>
                                <option value="government">Government</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="floating-label">
                            <input type="password" id="register-password" name="password" placeholder=" " class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary" required minlength="6">
                            <label for="register-password">Password</label>
                            <div class="mt-1">
                                <div class="flex space-x-1">
                                    <div class="h-1 flex-1 bg-gray-200 rounded" id="strength-bar-1"></div>
                                    <div class="h-1 flex-1 bg-gray-200 rounded" id="strength-bar-2"></div>
                                    <div class="h-1 flex-1 bg-gray-200 rounded" id="strength-bar-3"></div>
                                    <div class="h-1 flex-1 bg-gray-200 rounded" id="strength-bar-4"></div>
                                </div>
                                <p class="text-xs text-gray-500 mt-1" id="password-strength-text">At least 6 characters</p>
                            </div>
                        </div>
                        
                        <div class="floating-label">
                            <input type="password" id="register-confirm-password" name="confirm_password" placeholder=" " class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary" required minlength="6">
                            <label for="register-confirm-password">Confirm Password</label>
                            <div class="mt-1">
                                <p class="text-xs text-gray-500" id="password-match-text">Passwords must match</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="floating-label">
                        <textarea id="register-address" name="address" placeholder=" " rows="3" class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary" required></textarea>
                        <label for="register-address">Address</label>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" id="terms" name="terms" class="rounded border-gray-300 text-primary" required>
                        <label for="terms" class="ml-2 text-sm text-gray-600">
                            I agree to the <a href="#" class="text-primary hover:underline">Terms of Service</a> and 
                            <a href="#" class="text-primary hover:underline">Privacy Policy</a>
                        </label>
                    </div>
                    
                    <button type="submit" class="w-full bg-primary text-white py-3 rounded-lg hover:bg-secondary transition-colors">
                        Create Account
                    </button>
                </div>
                
                <div class="mt-6 text-center">
                    <p class="text-gray-600">Already have an account? 
                        <button type="button" onclick="switchToLogin()" class="text-primary hover:underline">Sign in</button>
                    </p>
                </div>
            </form>
        </div>
    </div>

    <!-- Borrow Request Modal -->
    <div id="borrow-modal" class="modal">
        <div class="modal-content" style="max-width: 48rem;">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-900">Storage Request</h2>
                <button onclick="closeModal('borrow-modal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="borrow-form">
                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Required Date *</label>
                            <input type="datetime-local" name="required_date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Location</label>
                            <select name="location_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                                <option value="">Select Location</option>
                                <?php
                                $locationStmt = $pdo->query("SELECT * FROM Location ORDER BY name");
                                $locations = $locationStmt->fetchAll();
                                foreach ($locations as $location):
                                ?>
                                <option value="<?php echo $location['id']; ?>"><?php echo htmlspecialchars($location['name'] . ' - ' . $location['city']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description *</label>
                        <textarea name="purpose" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary" placeholder="Describe the purpose of borrowing..." required></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Storing Items</label>
                        <div id="items-container" class="space-y-3">
                            <div class="item-row grid grid-cols-1 md:grid-cols-3 gap-3 p-3 border border-gray-200 rounded-lg">
                                <div>
                                    <select name="items[0][type_id]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary" required>
                                        <option value="">Select Available Item Type</option>
                                        <?php foreach ($itemTypes as $itemType): ?>
                                        <option value="<?php echo $itemType['id']; ?>"><?php echo htmlspecialchars($itemType['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <input type="text" name="items[0][description]" placeholder="Specific description" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                                </div>
                                <div class="flex items-center space-x-2">
                                    <input type="number" name="items[0][quantity]" placeholder="Qty" min="1" value="1" class="w-20 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                                    <button type="button" onclick="removeItem(this)" class="text-red-500 hover:text-red-700">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <button type="button" onclick="addItem()" class="mt-3 text-primary hover:text-secondary">
                            <i class="fas fa-plus mr-1"></i> Add Another Item
                        </button>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Additional Notes</label>
                        <textarea name="notes" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary" placeholder="Any special requirements or notes..."></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('borrow-modal')" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-secondary">
                            Submit Request
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        let itemCounter = 1;
        
        // Modal functions
        function showLogin() {
            document.getElementById('auth-title').textContent = 'Login';
            document.getElementById('login-form').style.display = 'block';
            document.getElementById('register-form').style.display = 'none';
            document.getElementById('auth-modal').classList.add('show');
        }
        
        function showRegister() {
            document.getElementById('auth-title').textContent = 'Create Account';
            document.getElementById('login-form').style.display = 'none';
            document.getElementById('register-form').style.display = 'block';
            document.getElementById('auth-modal').classList.add('show');
        }
        
        function switchToLogin() {
            showLogin();
        }
        
        function switchToRegister() {
            showRegister();
        }
        
        function showBorrowRequest() {
            document.getElementById('borrow-modal').classList.add('show');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }
        
        function requestItemType(typeId, typeName) {
            showBorrowRequest();
            // Pre-select the item type
            const firstSelect = document.querySelector('select[name="items[0][type_id]"]');
            firstSelect.value = typeId;
        }
        
        // Item management
        function addItem() {
            const container = document.getElementById('items-container');
            const newItem = document.createElement('div');
            newItem.className = 'item-row grid grid-cols-1 md:grid-cols-3 gap-3 p-3 border border-gray-200 rounded-lg';
            newItem.innerHTML = `
                <div>
                    <select name="items[${itemCounter}][type_id]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                        <option value="">Select Equipment Type</option>
                        <?php foreach ($itemTypes as $itemType): ?>
                        <option value="<?php echo $itemType['id']; ?>"><?php echo htmlspecialchars($itemType['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <input type="text" name="items[${itemCounter}][description]" placeholder="Specific description" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                </div>
                <div class="flex items-center space-x-2">
                    <input type="number" name="items[${itemCounter}][quantity]" placeholder="Qty" min="1" value="1" class="w-20 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                    <button type="button" onclick="removeItem(this)" class="text-red-500 hover:text-red-700">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            container.appendChild(newItem);
            itemCounter++;
        }
        
        function removeItem(button) {
            const itemRow = button.closest('.item-row');
            itemRow.remove();
        }
        
        // Form handlers
        document.getElementById('login-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('login.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Login successful!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.message || 'Login failed', 'error');
                }
            } catch (error) {
                showNotification('An error occurred during login', 'error');
            }
        });
        
        document.getElementById('register-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Check if passwords match
            const password = document.getElementById('register-password').value;
            const confirmPassword = document.getElementById('register-confirm-password').value;
            
            if (password !== confirmPassword) {
                showNotification('Passwords do not match', 'error');
                return;
            }
            
            if (password.length < 6) {
                showNotification('Password must be at least 6 characters long', 'error');
                return;
            }
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('register.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Account created successfully!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.message || 'Registration failed', 'error');
                }
            } catch (error) {
                showNotification('An error occurred during registration', 'error');
            }
        });
        
        document.getElementById('borrow-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('submit-request.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Borrowing request submitted successfully!', 'success');
                    closeModal('borrow-modal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.message || 'Failed to submit request', 'error');
                }
            } catch (error) {
                showNotification('An error occurred while submitting request', 'error');
            }
        });
        
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }
        
        function showProfile() {
            // Implement profile modal or redirect to profile page
            showNotification('Profile feature coming soon!', 'info');
        }
        
        // Notification system
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
        
        // Close modals when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('show');
            }
        });
        
        // Password strength checker
        document.getElementById('register-password').addEventListener('input', function() {
            const password = this.value;
            const strength = checkPasswordStrength(password);
            updatePasswordStrengthUI(strength);
        });
        
        // Password match checker
        document.getElementById('register-confirm-password').addEventListener('input', function() {
            const password = document.getElementById('register-password').value;
            const confirmPassword = this.value;
            const matchText = document.getElementById('password-match-text');
            
            if (confirmPassword === '') {
                matchText.textContent = 'Passwords must match';
                matchText.className = 'text-xs text-gray-500';
            } else if (password === confirmPassword) {
                matchText.textContent = 'Passwords match ✓';
                matchText.className = 'text-xs text-green-600';
            } else {
                matchText.textContent = 'Passwords do not match';
                matchText.className = 'text-xs text-red-600';
            }
        });
        
        function checkPasswordStrength(password) {
            let score = 0;
            
            if (password.length >= 6) score++;
            if (password.length >= 8) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;
            
            return Math.min(score, 4);
        }
        
        function updatePasswordStrengthUI(strength) {
            const bars = [
                document.getElementById('strength-bar-1'),
                document.getElementById('strength-bar-2'),
                document.getElementById('strength-bar-3'),
                document.getElementById('strength-bar-4')
            ];
            
            const colors = ['bg-red-500', 'bg-orange-500', 'bg-yellow-500', 'bg-green-500'];
            const texts = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
            
            // Reset all bars
            bars.forEach(bar => {
                bar.className = 'h-1 flex-1 bg-gray-200 rounded';
            });
            
            // Fill bars based on strength
            for (let i = 0; i < strength; i++) {
                bars[i].className = `h-1 flex-1 rounded ${colors[Math.min(strength - 1, 3)]}`;
            }
            
            // Update text
            const strengthText = document.getElementById('password-strength-text');
            if (strength === 0) {
                strengthText.textContent = 'At least 6 characters';
                strengthText.className = 'text-xs text-gray-500 mt-1';
            } else {
                strengthText.textContent = texts[strength];
                strengthText.className = `text-xs mt-1 ${strength >= 3 ? 'text-green-600' : strength >= 2 ? 'text-yellow-600' : 'text-red-600'}`;
            }
        }
    </script>
</body>
</html>
