// Dashboard JavaScript functionality

// Global variables
let currentSection = 'dashboard';
let notifications = [];

// DOM loaded event
document.addEventListener('DOMContentLoaded', function() {
    initializeDashboard();
    loadDashboardData();
});

// Initialize dashboard functionality
function initializeDashboard() {
    // Set up event listeners
    setupEventListeners();
    
    // Initialize tooltips and other UI components
    initializeComponents();
    
    // Load user preferences
    loadUserPreferences();
}

// Set up event listeners
function setupEventListeners() {
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
        if (!event.target.closest('#user-menu') && !event.target.closest('[onclick="toggleUserMenu()"]')) {
            document.getElementById('user-menu').classList.add('hidden');
        }
    });
    
    // Handle keyboard navigation
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeModals();
            closeSidebar();
        }
    });
}

// Initialize UI components
function initializeComponents() {
    // Initialize any charts or widgets
    console.log('Initializing dashboard components...');
}

// Load user preferences
function loadUserPreferences() {
    const preferences = localStorage.getItem('userPreferences');
    if (preferences) {
        const prefs = JSON.parse(preferences);
        // Apply saved preferences
        console.log('Loading user preferences:', prefs);
    }
}

// Navigation functions
function showSection(sectionName) {
    // Hide all sections
    const sections = document.querySelectorAll('.section');
    sections.forEach(section => {
        section.classList.remove('active');
        section.classList.add('hidden');
    });
    
    // Show selected section
    const targetSection = document.getElementById(sectionName + '-section');
    if (targetSection) {
        targetSection.classList.remove('hidden');
        targetSection.classList.add('active');
    }
    
    // Update page title
    const pageTitle = document.getElementById('page-title');
    pageTitle.textContent = formatSectionName(sectionName);
    
    // Update active nav link
    updateActiveNavLink(sectionName);
    
    // Load section data
    loadSectionData(sectionName);
    
    // Update current section
    currentSection = sectionName;
    
    // Close sidebar on mobile
    if (window.innerWidth < 1024) {
        closeSidebar();
    }
}

// Format section name for display
function formatSectionName(sectionName) {
    const names = {
        'dashboard': 'Dashboard',
        'admins': 'Admin Management',
        'employees': 'Employee Management',
        'customers': 'Customer Management',
        'materials': 'Material Management',
        'categories': 'Category Management',
        'locations': 'Location Management',
        'inventory': 'Inventory Management',
        'borrowing-requests': 'Borrowing Requests',
        'transactions': 'Transaction Management',
        'reports': 'Reports & Analytics',
        'services': 'Service Management'
    };
    return names[sectionName] || 'Dashboard';
}

// Update active navigation link
function updateActiveNavLink(sectionName) {
    // Remove active class from all nav links
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.classList.remove('active');
    });
    
    // Add active class to current nav link
    const activeLink = document.querySelector(`[onclick="showSection('${sectionName}')"]`);
    if (activeLink) {
        activeLink.classList.add('active');
    }
}

// Toggle navigation group
function toggleNavGroup(groupId) {
    const group = document.getElementById(groupId);
    const header = document.querySelector(`[onclick="toggleNavGroup('${groupId}')"]`);
    
    if (group.classList.contains('show')) {
        group.classList.remove('show');
        group.classList.add('hidden');
        header.classList.remove('active');
    } else {
        group.classList.add('show');
        group.classList.remove('hidden');
        header.classList.add('active');
    }
}

// Sidebar functions
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    
    if (sidebar.classList.contains('-translate-x-full')) {
        // Open sidebar
        sidebar.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
    } else {
        // Close sidebar
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
    }
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    
    sidebar.classList.add('-translate-x-full');
    overlay.classList.add('hidden');
}

// User menu functions
function toggleUserMenu() {
    const userMenu = document.getElementById('user-menu');
    userMenu.classList.toggle('hidden');
}

// Data loading functions
function loadDashboardData() {
    // Simulate loading dashboard data
    console.log('Loading dashboard data...');
    
    // In a real application, this would make API calls
    // For now, we'll use mock data
    updateDashboardStats();
    loadRecentActivity();
    loadLowStockAlerts();
}

function loadSectionData(sectionName) {
    console.log(`Loading data for section: ${sectionName}`);
    
    // This would load specific data for each section
    switch(sectionName) {
        case 'dashboard':
            loadDashboardData();
            break;
        case 'borrowing-requests':
            loadBorrowingRequests();
            break;
        case 'inventory':
            loadInventoryData();
            break;
        case 'materials':
            loadMaterialsData();
            break;
        // Add more cases as needed
        default:
            console.log(`No specific data loader for section: ${sectionName}`);
    }
}

// Update dashboard statistics
function updateDashboardStats() {
    // Simulate API call to get stats
    const stats = {
        pendingRequests: Math.floor(Math.random() * 50) + 10,
        activeBorrowings: Math.floor(Math.random() * 200) + 100,
        overdueItems: Math.floor(Math.random() * 20) + 1,
        totalMaterials: Math.floor(Math.random() * 500) + 1000
    };
    
    // Update the UI (in a real app, you'd update actual elements)
    console.log('Dashboard stats updated:', stats);
}

// Load recent activity
function loadRecentActivity() {
    // Simulate loading recent activity data
    console.log('Loading recent activity...');
}

// Load low stock alerts
function loadLowStockAlerts() {
    // Simulate loading low stock alerts
    console.log('Loading low stock alerts...');
}

// Load borrowing requests
function loadBorrowingRequests() {
    console.log('Loading borrowing requests...');
    // This would fetch borrowing requests from the API
}

// Load inventory data
function loadInventoryData() {
    console.log('Loading inventory data...');
    // This would fetch inventory data from the API
}

// Load materials data
function loadMaterialsData() {
    console.log('Loading materials data...');
    // This would fetch materials data from the API
}

// Modal functions
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300); // Wait for transition
        document.body.style.overflow = 'auto';
    }
}

function closeModals() {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
    });
    document.body.style.overflow = 'auto';
}

// Notification functions
function showNotification(message, type = 'info', duration = 5000) {
    const notification = createNotificationElement(message, type);
    document.body.appendChild(notification);
    
    // Show notification with animation
    setTimeout(() => {
        notification.classList.add('slide-in-right');
    }, 100);
    
    // Auto-hide notification
    setTimeout(() => {
        hideNotification(notification);
    }, duration);
    
    return notification;
}

function createNotificationElement(message, type) {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    notification.innerHTML = `
        <div class="p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas ${icons[type]} text-${type === 'error' ? 'red' : type === 'warning' ? 'yellow' : type === 'success' ? 'green' : 'blue'}-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-gray-900">${message}</p>
                </div>
                <div class="ml-auto pl-3">
                    <button onclick="hideNotification(this.closest('.notification'))" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    
    return notification;
}

function hideNotification(notification) {
    notification.style.transform = 'translateX(100%)';
    notification.style.opacity = '0';
    
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 300);
}

// Form handling functions
function handleFormSubmit(formId, callback) {
    const form = document.getElementById(formId);
    if (!form) return;
    
    form.addEventListener('submit', function(event) {
        event.preventDefault();
        
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        if (typeof callback === 'function') {
            callback(data);
        }
    });
}

// Validation functions
function validateForm(formData, rules) {
    const errors = [];
    
    for (const field in rules) {
        const value = formData[field];
        const rule = rules[field];
        
        if (rule.required && (!value || value.trim() === '')) {
            errors.push(`${rule.label || field} is required`);
        }
        
        if (rule.minLength && value && value.length < rule.minLength) {
            errors.push(`${rule.label || field} must be at least ${rule.minLength} characters`);
        }
        
        if (rule.email && value && !isValidEmail(value)) {
            errors.push(`${rule.label || field} must be a valid email address`);
        }
    }
    
    return errors;
}

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Utility functions
function formatDate(date) {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function formatDateTime(date) {
    return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Search and filter functions
function handleSearch(searchTerm, callback) {
    const debouncedSearch = debounce((term) => {
        if (typeof callback === 'function') {
            callback(term);
        }
    }, 300);
    
    debouncedSearch(searchTerm);
}

function filterTable(tableId, searchTerm) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const matches = text.includes(searchTerm.toLowerCase());
        row.style.display = matches ? '' : 'none';
    });
}

// Local storage functions
function saveToLocalStorage(key, data) {
    try {
        localStorage.setItem(key, JSON.stringify(data));
        return true;
    } catch (error) {
        console.error('Error saving to localStorage:', error);
        return false;
    }
}

function loadFromLocalStorage(key, defaultValue = null) {
    try {
        const item = localStorage.getItem(key);
        return item ? JSON.parse(item) : defaultValue;
    } catch (error) {
        console.error('Error loading from localStorage:', error);
        return defaultValue;
    }
}

// Export functions for global access
window.dashboardFunctions = {
    showSection,
    toggleNavGroup,
    toggleSidebar,
    toggleUserMenu,
    showNotification,
    openModal,
    closeModal,
    handleFormSubmit,
    validateForm,
    formatDate,
    formatDateTime,
    formatCurrency,
    handleSearch,
    filterTable,
    saveToLocalStorage,
    loadFromLocalStorage
};

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeDashboard);
} else {
    initializeDashboard();
}
