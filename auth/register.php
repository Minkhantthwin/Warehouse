<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Redirect if already logged in
redirectIfLoggedIn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Registration - Warehouse Management</title>
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
<body class="bg-gradient-to-br from-indigo-50 to-purple-100 min-h-screen">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl w-full space-y-8">
            <!-- Header -->
            <div class="text-center">
                <div class="mx-auto h-20 w-20 bg-primary rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-user-plus text-white text-2xl"></i>
                </div>
                <h2 class="text-3xl font-bold text-gray-900 mb-2">Create Admin Account</h2>
                <p class="text-gray-600">Join the warehouse management system as an administrator</p>
            </div>

            <!-- Registration Form -->
            <div class="bg-white rounded-xl shadow-lg p-8">
                <form id="register-form" class="space-y-6">
                    <!-- Personal Information Section -->
                    <div class="border-b border-gray-200 pb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-user mr-2 text-primary"></i>
                            Personal Information
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Full Name -->
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Full Name *
                                </label>
                                <input 
                                    type="text" 
                                    id="name" 
                                    name="name" 
                                    required 
                                    class="form-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition duration-200"
                                    placeholder="Enter your full name"
                                >
                            </div>

                            <!-- Phone Number -->
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                                    Phone Number
                                </label>
                                <input 
                                    type="tel" 
                                    id="phone" 
                                    name="phone" 
                                    class="form-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition duration-200"
                                    placeholder="+1 234 567 8900"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Account Information Section -->
                    <div class="border-b border-gray-200 pb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-envelope mr-2 text-primary"></i>
                            Account Information
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Email -->
                            <div class="md:col-span-2">
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                    Email Address *
                                </label>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    required 
                                    class="form-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition duration-200"
                                    placeholder="admin@warehouse.com"
                                >
                            </div>

                            <!-- Password -->
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                                    Password *
                                </label>
                                <div class="relative">
                                    <input 
                                        type="password" 
                                        id="password" 
                                        name="password" 
                                        required 
                                        class="form-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition duration-200 pr-12"
                                        placeholder="Create a secure password"
                                    >
                                    <button 
                                        type="button" 
                                        id="toggle-password" 
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600"
                                    >
                                        <i class="fas fa-eye" id="password-icon"></i>
                                    </button>
                                </div>
                                <!-- Password Strength Indicator -->
                                <div class="mt-2">
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div id="password-strength" class="h-2 rounded-full transition-all duration-300 w-0 bg-red-500"></div>
                                    </div>
                                    <p id="password-strength-text" class="text-xs text-gray-500 mt-1">Password strength: Weak</p>
                                </div>
                            </div>

                            <!-- Confirm Password -->
                            <div>
                                <label for="confirm-password" class="block text-sm font-medium text-gray-700 mb-2">
                                    Confirm Password *
                                </label>
                                <div class="relative">
                                    <input 
                                        type="password" 
                                        id="confirm-password" 
                                        name="confirm-password" 
                                        required 
                                        class="form-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition duration-200 pr-12"
                                        placeholder="Confirm your password"
                                    >
                                    <button 
                                        type="button" 
                                        id="toggle-confirm-password" 
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600"
                                    >
                                        <i class="fas fa-eye" id="confirm-password-icon"></i>
                                    </button>
                                </div>
                                <!-- Password Match Indicator -->
                                <div id="password-match" class="mt-2 text-xs hidden">
                                    <span class="text-red-500">
                                        <i class="fas fa-times mr-1"></i>Passwords do not match
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Admin Role Section -->
                    <div class="border-b border-gray-200 pb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-shield-alt mr-2 text-primary"></i>
                            Administrator Role
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Role Selection -->
                            <div>
                                <label for="role" class="block text-sm font-medium text-gray-700 mb-2">
                                    Admin Role *
                                </label>
                                <select 
                                    id="role" 
                                    name="role" 
                                    required 
                                    class="form-select w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition duration-200"
                                >
                                    <option value="">Select Role</option>
                                    <option value="admin">Administrator</option>
                                    <option value="super-admin">Super Administrator</option>
                                </select>
                            </div>

                            <!-- Permissions Preview -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Role Permissions
                                </label>
                                <div id="permissions-preview" class="bg-gray-50 rounded-lg p-3 text-sm text-gray-600">
                                    Select a role to see permissions
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Terms and Conditions -->
                    <div class="space-y-4">
                        <div class="flex items-start">
                            <input 
                                id="terms" 
                                name="terms" 
                                type="checkbox" 
                                required
                                class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded mt-1"
                            >
                            <label for="terms" class="ml-3 block text-sm text-gray-700">
                                I agree to the 
                                <a href="#" class="text-primary hover:text-secondary underline">Terms and Conditions</a> 
                                and 
                                <a href="#" class="text-primary hover:text-secondary underline">Privacy Policy</a>
                            </label>
                        </div>

                        <div class="flex items-start">
                            <input 
                                id="notifications" 
                                name="notifications" 
                                type="checkbox" 
                                class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded mt-1"
                            >
                            <label for="notifications" class="ml-3 block text-sm text-gray-700">
                                Send me system notifications and updates
                            </label>
                        </div>
                    </div>

                    <!-- Register Button -->
                    <button 
                        type="submit" 
                        class="w-full bg-primary hover:bg-secondary text-white font-semibold py-3 px-4 rounded-lg transition duration-200 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                    >
                        <i class="fas fa-user-plus mr-2"></i>
                        Create Admin Account
                    </button>

                    <!-- Login Link -->
                    <div class="text-center">
                        <p class="text-sm text-gray-600">
                            Already have an account? 
                            <a 
                                href="login.php" 
                                class="text-primary hover:text-secondary transition duration-200 font-medium"
                            >
                                Sign in here
                            </a>
                        </p>
                    </div>
                </form>
            </div>

            <!-- Footer -->
            <div class="text-center text-sm text-gray-500">
                <p>&copy; 2025 Warehouse Management System. All rights reserved.</p>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-6 flex items-center space-x-3">
            <div class="spinner"></div>
            <span class="text-gray-700">Creating account...</span>
        </div>
    </div>

    <!-- Notification Container -->
    <div id="notification-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

    <script>
        // Role permissions mapping
        const rolePermissions = {
            'admin': [
                'Borrowing Management',
                'Customer Management',
                'Employee Management',
                'View Reports'
            ],
            'super-admin': [
                'User Management',
                'Borrowing Management', 
                'Customer Management',
                'Employee Management',
                'System Settings',
                'All Reports Access'
            ]
        };

        // Toggle password visibility
        function setupPasswordToggle(inputId, iconId) {
            document.getElementById(`toggle-${inputId}`).addEventListener('click', function() {
                const passwordInput = document.getElementById(inputId);
                const passwordIcon = document.getElementById(`${inputId}-icon`);
                
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    passwordIcon.className = 'fas fa-eye-slash';
                } else {
                    passwordInput.type = 'password';
                    passwordIcon.className = 'fas fa-eye';
                }
            });
        }

        setupPasswordToggle('password', 'password');
        setupPasswordToggle('confirm-password', 'confirm-password');

        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('password-strength');
            const strengthText = document.getElementById('password-strength-text');
            
            let strength = 0;
            let strengthLabel = 'Very Weak';
            let color = 'bg-red-500';
            
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            switch (strength) {
                case 0:
                case 1:
                    strengthLabel = 'Very Weak';
                    color = 'bg-red-500';
                    break;
                case 2:
                    strengthLabel = 'Weak';
                    color = 'bg-orange-500';
                    break;
                case 3:
                    strengthLabel = 'Fair';
                    color = 'bg-yellow-500';
                    break;
                case 4:
                    strengthLabel = 'Good';
                    color = 'bg-blue-500';
                    break;
                case 5:
                    strengthLabel = 'Strong';
                    color = 'bg-green-500';
                    break;
            }
            
            const percentage = (strength / 5) * 100;
            strengthBar.style.width = `${percentage}%`;
            strengthBar.className = `h-2 rounded-full transition-all duration-300 ${color}`;
            strengthText.textContent = `Password strength: ${strengthLabel}`;
        });

        // Password match checker
        document.getElementById('confirm-password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            const matchIndicator = document.getElementById('password-match');
            
            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    matchIndicator.innerHTML = '<span class="text-green-500"><i class="fas fa-check mr-1"></i>Passwords match</span>';
                    matchIndicator.classList.remove('hidden');
                } else {
                    matchIndicator.innerHTML = '<span class="text-red-500"><i class="fas fa-times mr-1"></i>Passwords do not match</span>';
                    matchIndicator.classList.remove('hidden');
                }
            } else {
                matchIndicator.classList.add('hidden');
            }
        });

        // Role selection handler
        document.getElementById('role').addEventListener('change', function() {
            const selectedRole = this.value;
            const previewDiv = document.getElementById('permissions-preview');
            
            if (selectedRole && rolePermissions[selectedRole]) {
                const permissions = rolePermissions[selectedRole];
                previewDiv.innerHTML = `
                    <div class="space-y-1">
                        ${permissions.map(permission => `
                            <div class="flex items-center text-xs">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                <span>${permission}</span>
                            </div>
                        `).join('')}
                    </div>
                `;
            } else {
                previewDiv.textContent = 'Select a role to see permissions';
            }
        });

        // Handle form submission
        document.getElementById('register-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            
            // Validate passwords match
            if (data.password !== data['confirm-password']) {
                showNotification('Passwords do not match', 'error');
                return;
            }
            
            // Show loading
            document.getElementById('loading-overlay').classList.remove('hidden');
            
            try {
                const response = await fetch('../api/auth.php?action=register', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (response.ok && result.success) {
                    showNotification(result.message || 'Account created successfully! You can now login.', 'success');
                    
                    // Reset form after successful registration
                    setTimeout(() => {
                        this.reset();
                        document.getElementById('permissions-preview').textContent = 'Select a role to see permissions';
                        document.getElementById('password-strength').style.width = '0%';
                        document.getElementById('password-match').classList.add('hidden');
                        
                        // Redirect to login page
                        setTimeout(() => {
                            window.location.href = 'login.php';
                        }, 2000);
                    }, 1000);
                } else {
                    showNotification(result.error || 'Registration failed. Please try again.', 'error');
                }
                
            } catch (error) {
                console.error('Registration error:', error);
                showNotification('Network error occurred. Please try again.', 'error');
            } finally {
                document.getElementById('loading-overlay').classList.add('hidden');
            }
        });

        // Notification system
        function showNotification(message, type = 'info') {
            const colors = {
                'success': 'bg-green-500 text-white border-green-600',
                'error': 'bg-red-500 text-white border-red-600',
                'warning': 'bg-yellow-500 text-white border-yellow-600',
                'info': 'bg-blue-500 text-white border-blue-600'
            };

            const icons = {
                'success': 'fas fa-check-circle',
                'error': 'fas fa-exclamation-circle',
                'warning': 'fas fa-exclamation-triangle',
                'info': 'fas fa-info-circle'
            };

            const notification = document.createElement('div');
            notification.className = `${colors[type]} px-4 py-3 rounded-lg shadow-lg border-l-4 max-w-sm transform transition-all duration-300 translate-x-full opacity-0`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="${icons[type]} mr-3"></i>
                    <span class="text-sm font-medium">${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;

            document.getElementById('notification-container').appendChild(notification);

            // Animate in
            setTimeout(() => {
                notification.classList.remove('translate-x-full', 'opacity-0');
            }, 100);

            // Auto remove after 5 seconds
            setTimeout(() => {
                notification.classList.add('translate-x-full', 'opacity-0');
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        }

        // Add entrance animation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.max-w-2xl');
            form.classList.add('opacity-0', 'transform', 'translate-y-8');
            
            setTimeout(() => {
                form.classList.remove('opacity-0', 'translate-y-8');
                form.classList.add('transition-all', 'duration-500');
            }, 100);
        });
    </script>
</body>
</html>
