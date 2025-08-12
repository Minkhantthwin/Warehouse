// User Management JavaScript functionality

let currentUserType = 'customers';

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeUserManagement();
});

function initializeUserManagement() {
    setupEventListeners();
    switchUserType('customers');
    setupSelectAllCheckbox();
}

function setupEventListeners() {
    // Search functionality
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            handleSearch(e.target.value, filterUsers);
        });
    }

    // Filter dropdowns
    const roleFilter = document.getElementById('role-filter');
    if (roleFilter) {
        roleFilter.addEventListener('change', function(e) {
            filterByRole(e.target.value);
        });
    }

    const statusFilter = document.getElementById('status-filter');
    if (statusFilter) {
        statusFilter.addEventListener('change', function(e) {
            filterByStatus(e.target.value);
        });
    }

    // Form submission
    const addUserForm = document.getElementById('add-user-form');
    if (addUserForm) {
        addUserForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleAddUser();
        });
    }
}

function setupSelectAllCheckbox() {
    const selectAllCheckbox = document.getElementById('select-all');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function(e) {
            const userCheckboxes = document.querySelectorAll('.user-checkbox');
            userCheckboxes.forEach(checkbox => {
                if (!checkbox.closest('tr').classList.contains('hidden')) {
                    checkbox.checked = e.target.checked;
                }
            });
        });
    }
}

// User type switching
function switchUserType(userType) {
    currentUserType = userType;
    
    // Update table title
    const tableTitle = document.getElementById('table-title');
    const titleMap = {
        'customers': 'Customers',
        'employees': 'Employees',
        'admins': 'Admins'
    };
    tableTitle.textContent = titleMap[userType];
    
    // Update total count
    const totalCount = document.getElementById('total-count');
    const countMap = {
        'customers': '487',
        'employees': '23',
        'admins': '5'
    };
    totalCount.textContent = countMap[userType];
    
    // Show/hide appropriate rows
    const allRows = document.querySelectorAll('#users-table-body tr');
    allRows.forEach(row => {
        row.classList.add('hidden');
    });
    
    const targetRows = document.querySelectorAll(`.${userType.slice(0, -1)}-row`);
    targetRows.forEach(row => {
        row.classList.remove('hidden');
    });
    
    // Update role filter options
    updateRoleFilter(userType);
    
    // Update role column header
    const roleColumn = document.getElementById('role-column');
    if (userType === 'customers') {
        roleColumn.textContent = 'Type';
    } else {
        roleColumn.textContent = 'Role';
    }
    
    // Reset filters
    clearFilters();
    
    console.log('Switched to user type:', userType);
}

function updateRoleFilter(userType) {
    const roleFilter = document.getElementById('role-filter');
    const roleFilterContainer = document.getElementById('role-filter-container');
    
    // Clear existing options
    roleFilter.innerHTML = '<option value="">All Roles</option>';
    
    switch(userType) {
        case 'customers':
            roleFilter.innerHTML = `
                <option value="">All Types</option>
                <option value="premium">Premium</option>
                <option value="regular">Regular</option>
            `;
            roleFilterContainer.querySelector('label').textContent = 'Type';
            break;
        case 'employees':
            roleFilter.innerHTML = `
                <option value="">All Roles</option>
                <option value="manager">Manager</option>
                <option value="supervisor">Supervisor</option>
                <option value="operator">Operator</option>
            `;
            roleFilterContainer.querySelector('label').textContent = 'Role';
            break;
        case 'admins':
            roleFilter.innerHTML = `
                <option value="">All Roles</option>
                <option value="super-admin">Super Admin</option>
                <option value="admin">Admin</option>
            `;
            roleFilterContainer.querySelector('label').textContent = 'Role';
            break;
    }
}

// Filtering functions
function filterUsers(searchTerm) {
    const visibleRows = document.querySelectorAll(`#users-table-body .${currentUserType.slice(0, -1)}-row`);
    
    visibleRows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const matches = text.includes(searchTerm.toLowerCase());
        row.style.display = matches ? '' : 'none';
    });
}

function filterByRole(role) {
    const visibleRows = document.querySelectorAll(`#users-table-body .${currentUserType.slice(0, -1)}-row`);
    
    visibleRows.forEach(row => {
        if (!role) {
            row.style.display = '';
            return;
        }

        const roleCell = row.querySelector('td:nth-child(4) span');
        if (roleCell) {
            const rowRole = roleCell.textContent.toLowerCase().replace(' ', '-');
            const matches = rowRole === role;
            row.style.display = matches ? '' : 'none';
        }
    });
}

function filterByStatus(status) {
    const visibleRows = document.querySelectorAll(`#users-table-body .${currentUserType.slice(0, -1)}-row`);
    
    visibleRows.forEach(row => {
        if (!status) {
            row.style.display = '';
            return;
        }

        const statusCell = row.querySelector('td:nth-child(5) .badge');
        if (statusCell) {
            const rowStatus = statusCell.textContent.toLowerCase();
            const matches = rowStatus === status;
            row.style.display = matches ? '' : 'none';
        }
    });
}

function clearFilters() {
    // Reset all filter inputs
    document.getElementById('search-input').value = '';
    document.getElementById('role-filter').value = '';
    document.getElementById('status-filter').value = '';
    
    // Show all rows for current user type
    const visibleRows = document.querySelectorAll(`#users-table-body .${currentUserType.slice(0, -1)}-row`);
    visibleRows.forEach(row => {
        row.style.display = '';
    });
    
    showNotification('Filters cleared', 'info');
}

// User actions
function viewUser(userId, userType) {
    console.log('Viewing user:', userId, userType);
    
    // Mock user data
    const userData = {
        id: userId,
        name: 'John Doe',
        email: 'john@example.com',
        phone: '+1 234 567 8900',
        role: userType === 'customer' ? 'Premium' : 'Manager',
        status: 'Active',
        joined: 'Jan 15, 2024',
        lastActive: '2 hours ago',
        address: '123 Main St, City, State 12345'
    };
    
    const modalContent = `
        <div class="modal" id="view-user-modal">
            <div class="modal-overlay" onclick="closeModal('view-user-modal')"></div>
            <div class="modal-content max-w-2xl">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-800">User Details</h3>
                    <button onclick="closeModal('view-user-modal')" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="text-center lg:text-left">
                        <div class="h-20 w-20 rounded-full mx-auto lg:mx-0 mb-4">
                            <img class="h-20 w-20 rounded-full" src="https://ui-avatars.com/api/?name=${userData.name}&background=random" alt="">
                        </div>
                        <h4 class="text-xl font-semibold text-gray-900">${userData.name}</h4>
                        <p class="text-gray-500">${userType.charAt(0).toUpperCase() + userType.slice(1)} ID: #${userType.charAt(0).toUpperCase()}${userId.toString().padStart(3, '0')}</p>
                        <div class="mt-4">
                            <span class="badge badge-${userData.status.toLowerCase()}">${userData.status}</span>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <div>
                            <h5 class="font-medium text-gray-700 mb-3">Contact Information</h5>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Email:</span>
                                    <span class="font-medium">${userData.email}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Phone:</span>
                                    <span class="font-medium">${userData.phone}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Role:</span>
                                    <span class="font-medium">${userData.role}</span>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h5 class="font-medium text-gray-700 mb-3">Account Information</h5>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Joined:</span>
                                    <span class="font-medium">${userData.joined}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Last Active:</span>
                                    <span class="font-medium">${userData.lastActive}</span>
                                </div>
                            </div>
                        </div>
                        
                        ${userType === 'customer' ? `
                        <div>
                            <h5 class="font-medium text-gray-700 mb-3">Address</h5>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-sm">${userData.address}</p>
                            </div>
                        </div>
                        ` : ''}
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6 pt-6 border-t">
                    <button onclick="closeModal('view-user-modal')" class="btn btn-secondary">Close</button>
                    <button onclick="editUser(${userId}, '${userType}')" class="btn btn-primary">
                        <i class="fas fa-edit mr-2"></i>Edit
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalContent);
    
    setTimeout(() => {
        document.getElementById('view-user-modal').classList.remove('hidden');
    }, 10);
}

function editUser(userId, userType) {
    console.log('Editing user:', userId, userType);
    
    // Close view modal if open
    closeModal('view-user-modal');
    
    // Open add user modal with pre-filled data
    openAddUserModal();
    
    // Change modal title
    const modalTitle = document.getElementById('add-user-title');
    modalTitle.textContent = `Edit ${userType.charAt(0).toUpperCase() + userType.slice(1)}`;
    
    // Change submit button text
    const submitBtn = document.getElementById('add-user-submit');
    submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Update User';
    
    // Pre-fill form with existing data (simplified example)
    const form = document.getElementById('add-user-form');
    form.name.value = 'John Doe';
    form.email.value = 'john@example.com';
    form.phone.value = '+1 234 567 8900';
}

function toggleUserStatus(userId, userType) {
    if (confirm(`Are you sure you want to toggle the status for user #${userId}?`)) {
        console.log('Toggling status for user:', userId, userType);
        
        // Simulate API call
        showNotification(`User #${userId} status updated successfully!`, 'success');
        
        // Update the status in the table (simplified)
        const row = document.querySelector(`input[data-id="${userId}"]`)?.closest('tr');
        if (row) {
            const statusBadge = row.querySelector('.badge');
            if (statusBadge && statusBadge.textContent === 'Active') {
                statusBadge.className = 'badge badge-pending';
                statusBadge.textContent = 'Inactive';
            } else if (statusBadge) {
                statusBadge.className = 'badge badge-active';
                statusBadge.textContent = 'Active';
            }
        }
    }
}

function deleteUser(userId, userType) {
    if (confirm(`Are you sure you want to delete ${userType} #${userId}? This action cannot be undone.`)) {
        console.log('Deleting user:', userId, userType);
        
        // Simulate API call
        showNotification(`${userType.charAt(0).toUpperCase() + userType.slice(1)} #${userId} has been deleted`, 'success');
        
        // Remove row from table
        const row = document.querySelector(`input[data-id="${userId}"]`)?.closest('tr');
        if (row) {
            row.remove();
        }
    }
}

// Modal functions
function openAddUserModal() {
    // Update modal based on current user type
    const modalTitle = document.getElementById('add-user-title');
    const submitBtn = document.getElementById('add-user-submit');
    const roleSelect = document.getElementById('user-role-select');
    const addressContainer = document.getElementById('address-container');
    const adminSelectContainer = document.getElementById('admin-select-container');
    
    // Reset form
    document.getElementById('add-user-form').reset();
    
    switch(currentUserType) {
        case 'customers':
            modalTitle.textContent = 'Add New Customer';
            submitBtn.textContent = 'Add Customer';
            roleSelect.innerHTML = `
                <option value="">Select Type</option>
                <option value="premium">Premium Customer</option>
                <option value="regular">Regular Customer</option>
            `;
            addressContainer.style.display = 'block';
            adminSelectContainer.style.display = 'none';
            break;
        case 'employees':
            modalTitle.textContent = 'Add New Employee';
            submitBtn.textContent = 'Add Employee';
            roleSelect.innerHTML = `
                <option value="">Select Role</option>
                <option value="manager">Manager</option>
                <option value="supervisor">Supervisor</option>
                <option value="operator">Operator</option>
            `;
            addressContainer.style.display = 'none';
            adminSelectContainer.style.display = 'block';
            break;
        case 'admins':
            modalTitle.textContent = 'Add New Admin';
            submitBtn.textContent = 'Add Admin';
            roleSelect.innerHTML = `
                <option value="">Select Role</option>
                <option value="super-admin">Super Admin</option>
                <option value="admin">Admin</option>
            `;
            addressContainer.style.display = 'none';
            adminSelectContainer.style.display = 'none';
            break;
    }
    
    openModal('add-user-modal');
}

function handleAddUser() {
    const form = document.getElementById('add-user-form');
    const formData = new FormData(form);
    
    // Validate form
    const errors = validateUserForm(formData);
    if (errors.length > 0) {
        showNotification(errors.join(', '), 'error');
        return;
    }
    
    // Check password confirmation
    if (formData.get('password') !== formData.get('confirm_password')) {
        showNotification('Passwords do not match', 'error');
        return;
    }
    
    console.log('Adding new user:', Object.fromEntries(formData));
    
    // Simulate API call
    setTimeout(() => {
        const userType = currentUserType.slice(0, -1); // Remove 's'
        showNotification(`${userType.charAt(0).toUpperCase() + userType.slice(1)} added successfully!`, 'success');
        closeModal('add-user-modal');
        
        // Reset form
        form.reset();
        
        // In a real app, would refresh the data
        console.log('User added successfully');
    }, 1000);
}

function validateUserForm(formData) {
    const errors = [];
    
    if (!formData.get('name')) {
        errors.push('Name is required');
    }
    
    if (!formData.get('email')) {
        errors.push('Email is required');
    } else if (!isValidEmail(formData.get('email'))) {
        errors.push('Invalid email format');
    }
    
    if (!formData.get('password')) {
        errors.push('Password is required');
    } else if (formData.get('password').length < 6) {
        errors.push('Password must be at least 6 characters');
    }
    
    if (!formData.get('role')) {
        errors.push('Role is required');
    }
    
    return errors;
}

// Bulk actions
function handleBulkAction(action) {
    if (!action) return;
    
    const checkboxes = document.querySelectorAll('.user-checkbox:checked');
    const selectedIds = Array.from(checkboxes).map(cb => cb.dataset.id).filter(id => {
        const row = document.querySelector(`input[data-id="${id}"]`)?.closest('tr');
        return row && !row.classList.contains('hidden');
    });
    
    if (selectedIds.length === 0) {
        showNotification('Please select at least one user', 'warning');
        return;
    }
    
    switch(action) {
        case 'activate':
            if (confirm(`Activate ${selectedIds.length} selected users?`)) {
                console.log('Bulk activating users:', selectedIds);
                showNotification(`${selectedIds.length} users activated`, 'success');
                updateBulkStatus(selectedIds, 'Active');
            }
            break;
        case 'deactivate':
            if (confirm(`Deactivate ${selectedIds.length} selected users?`)) {
                console.log('Bulk deactivating users:', selectedIds);
                showNotification(`${selectedIds.length} users deactivated`, 'warning');
                updateBulkStatus(selectedIds, 'Inactive');
            }
            break;
        case 'delete':
            if (confirm(`Delete ${selectedIds.length} selected users? This action cannot be undone.`)) {
                console.log('Bulk deleting users:', selectedIds);
                showNotification(`${selectedIds.length} users deleted`, 'success');
                
                // Remove rows from table
                selectedIds.forEach(id => {
                    const row = document.querySelector(`input[data-id="${id}"]`)?.closest('tr');
                    if (row) row.remove();
                });
            }
            break;
    }
    
    // Reset select dropdown
    event.target.value = '';
    
    // Uncheck all checkboxes
    document.getElementById('select-all').checked = false;
    checkboxes.forEach(cb => cb.checked = false);
}

function updateBulkStatus(userIds, newStatus) {
    userIds.forEach(id => {
        const row = document.querySelector(`input[data-id="${id}"]`)?.closest('tr');
        if (row) {
            const statusBadge = row.querySelector('.badge');
            if (statusBadge) {
                statusBadge.className = `badge badge-${newStatus.toLowerCase()}`;
                statusBadge.textContent = newStatus;
            }
        }
    });
}

function exportUsers() {
    console.log('Exporting users data for:', currentUserType);
    
    // In a real application, this would generate and download a CSV/Excel file
    showNotification(`${currentUserType.charAt(0).toUpperCase() + currentUserType.slice(1)} data exported successfully!`, 'success');
}

// Export functions for global access
window.userManagementFunctions = {
    switchUserType,
    viewUser,
    editUser,
    toggleUserStatus,
    deleteUser,
    openAddUserModal,
    clearFilters,
    handleBulkAction,
    exportUsers
};
