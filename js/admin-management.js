// Admin Management JavaScript Functions

document.addEventListener('DOMContentLoaded', function() {
    initializeAdminManagement();
});

function initializeAdminManagement() {
    // Initialize search functionality
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', filterAdmins);
    }

    // Initialize filter dropdowns
    document.getElementById('role-filter')?.addEventListener('change', filterAdmins);
    document.getElementById('status-filter')?.addEventListener('change', filterAdmins);

    // Initialize select all checkbox
    const selectAllCheckbox = document.getElementById('select-all');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', toggleSelectAll);
    }

    // Initialize individual checkboxes
    const adminCheckboxes = document.querySelectorAll('.admin-checkbox');
    adminCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectAllState);
    });

    // Initialize form submission
    const addAdminForm = document.getElementById('add-admin-form');
    if (addAdminForm) {
        addAdminForm.addEventListener('submit', handleAddAdmin);
    }
}

function filterAdmins() {
    const searchTerm = document.getElementById('search-input').value.toLowerCase();
    const roleFilter = document.getElementById('role-filter').value;
    const statusFilter = document.getElementById('status-filter').value;

    const rows = document.querySelectorAll('#admins-table-body tr');
    
    rows.forEach(row => {
        const name = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
        const email = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
        const role = row.querySelector('td:nth-child(4) span').textContent.toLowerCase();
        const status = row.querySelector('td:nth-child(5) span').textContent.toLowerCase();

        const matchesSearch = name.includes(searchTerm) || email.includes(searchTerm);
        const matchesRole = !roleFilter || role.includes(roleFilter.replace('-', ' '));
        const matchesStatus = !statusFilter || status.includes(statusFilter);

        if (matchesSearch && matchesRole && matchesStatus) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function clearFilters() {
    document.getElementById('search-input').value = '';
    document.getElementById('role-filter').selectedIndex = 0;
    document.getElementById('status-filter').selectedIndex = 0;
    filterAdmins();
}

function toggleSelectAll() {
    const selectAllCheckbox = document.getElementById('select-all');
    const adminCheckboxes = document.querySelectorAll('.admin-checkbox');
    
    adminCheckboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
}

function updateSelectAllState() {
    const selectAllCheckbox = document.getElementById('select-all');
    const adminCheckboxes = document.querySelectorAll('.admin-checkbox');
    const checkedCount = document.querySelectorAll('.admin-checkbox:checked').length;

    if (checkedCount === 0) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = false;
    } else if (checkedCount === adminCheckboxes.length) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = true;
    } else {
        selectAllCheckbox.indeterminate = true;
    }
}

function handleBulkAction(action) {
    if (!action) return;

    const selectedAdmins = Array.from(document.querySelectorAll('.admin-checkbox:checked'))
        .map(checkbox => checkbox.dataset.id);

    if (selectedAdmins.length === 0) {
        showNotification('Please select at least one administrator.', 'warning');
        return;
    }

    switch (action) {
        case 'activate':
            bulkActivateAdmins(selectedAdmins);
            break;
        case 'deactivate':
            bulkDeactivateAdmins(selectedAdmins);
            break;
        case 'reset-password':
            bulkResetPasswords(selectedAdmins);
            break;
    }

    // Reset the select dropdown
    document.querySelector('select[onchange="handleBulkAction(this.value)"]').selectedIndex = 0;
}

function bulkActivateAdmins(adminIds) {
    if (confirm(`Are you sure you want to activate ${adminIds.length} administrator(s)?`)) {
        // Here you would make an API call to activate the admins
        console.log('Activating admins:', adminIds);
        showNotification(`${adminIds.length} administrator(s) activated successfully.`, 'success');
        
        // Update UI
        adminIds.forEach(id => {
            const row = document.querySelector(`[data-id="${id}"]`).closest('tr');
            const statusBadge = row.querySelector('.badge');
            statusBadge.className = 'badge badge-active';
            statusBadge.textContent = 'Active';
        });
    }
}

function bulkDeactivateAdmins(adminIds) {
    if (confirm(`Are you sure you want to deactivate ${adminIds.length} administrator(s)?`)) {
        // Here you would make an API call to deactivate the admins
        console.log('Deactivating admins:', adminIds);
        showNotification(`${adminIds.length} administrator(s) deactivated successfully.`, 'warning');
        
        // Update UI
        adminIds.forEach(id => {
            const row = document.querySelector(`[data-id="${id}"]`).closest('tr');
            const statusBadge = row.querySelector('.badge');
            statusBadge.className = 'badge badge-pending';
            statusBadge.textContent = 'Inactive';
        });
    }
}

function bulkResetPasswords(adminIds) {
    if (confirm(`Are you sure you want to reset passwords for ${adminIds.length} administrator(s)?`)) {
        // Here you would make an API call to reset passwords
        console.log('Resetting passwords for admins:', adminIds);
        showNotification(`Password reset emails sent to ${adminIds.length} administrator(s).`, 'info');
    }
}

function viewAdmin(adminId) {
    // Here you would open a modal or navigate to admin detail page
    console.log('Viewing admin:', adminId);
    showNotification('Admin details would be displayed here.', 'info');
}

function editAdmin(adminId) {
    // Here you would open edit modal with admin data
    console.log('Editing admin:', adminId);
    showNotification('Edit admin form would be displayed here.', 'info');
}

function resetPassword(adminId) {
    if (confirm('Are you sure you want to reset this administrator\'s password?')) {
        // Here you would make an API call to reset password
        console.log('Resetting password for admin:', adminId);
        showNotification('Password reset email sent successfully.', 'success');
    }
}

function toggleAdminStatus(adminId) {
    const row = document.querySelector(`[data-id="${adminId}"]`).closest('tr');
    const statusBadge = row.querySelector('.badge');
    const toggleIcon = row.querySelector('.fa-toggle-on, .fa-toggle-off');
    
    const isActive = statusBadge.textContent.trim() === 'Active';
    const action = isActive ? 'deactivate' : 'activate';
    
    if (confirm(`Are you sure you want to ${action} this administrator?`)) {
        // Here you would make an API call to toggle status
        console.log(`${action} admin:`, adminId);
        
        // Update UI
        if (isActive) {
            statusBadge.className = 'badge badge-pending';
            statusBadge.textContent = 'Inactive';
            toggleIcon.className = 'fas fa-toggle-off';
        } else {
            statusBadge.className = 'badge badge-active';
            statusBadge.textContent = 'Active';
            toggleIcon.className = 'fas fa-toggle-on';
        }
        
        showNotification(`Administrator ${action}d successfully.`, 'success');
    }
}

function handleAddAdmin(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const adminData = Object.fromEntries(formData.entries());
    
    // Collect permissions
    const permissions = Array.from(formData.getAll('permissions[]'));
    adminData.permissions = permissions;
    
    // Validate passwords match
    if (adminData.password !== adminData.confirm_password) {
        showNotification('Passwords do not match.', 'error');
        return;
    }
    
    // Here you would make an API call to create the admin
    console.log('Creating admin:', adminData);
    
    // Simulate success
    showNotification('Administrator created successfully.', 'success');
    closeModal('add-admin-modal');
    
    // Reset form
    event.target.reset();
    
    // In a real application, you would refresh the table or add the new row
}

function exportAdmins() {
    // Here you would generate and download admin data
    console.log('Exporting admin data...');
    showNotification('Admin data export started.', 'info');
}

// Utility function for notifications (this should be in a shared file)
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 text-white ${getNotificationColor(type)}`;
    notification.textContent = message;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Auto remove after 3 seconds
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
