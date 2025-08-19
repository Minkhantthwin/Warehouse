// Employee Management JavaScript Functions

document.addEventListener('DOMContentLoaded', function() {
    initializeEmployeeManagement();
});

function initializeEmployeeManagement() {
    // Initialize search functionality
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', filterEmployees);
    }

    // Initialize filter dropdowns
    document.getElementById('department-filter')?.addEventListener('change', filterEmployees);
    document.getElementById('position-filter')?.addEventListener('change', filterEmployees);
    document.getElementById('status-filter')?.addEventListener('change', filterEmployees);

    // Initialize select all checkbox
    const selectAllCheckbox = document.getElementById('select-all');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', toggleSelectAll);
    }

    // Initialize individual checkboxes
    const employeeCheckboxes = document.querySelectorAll('.employee-checkbox');
    employeeCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectAllState);
    });

    // Auto-generate employee ID
    generateEmployeeId();
}

function filterEmployees() {
    const searchTerm = document.getElementById('search-input').value.toLowerCase();
    const departmentFilter = document.getElementById('department-filter').value;
    const positionFilter = document.getElementById('position-filter').value;
    const statusFilter = document.getElementById('status-filter').value;

    const rows = document.querySelectorAll('#employees-table-body tr');
    
    rows.forEach(row => {
        const name = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
        const email = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
        const department = row.querySelector('td:nth-child(4) span').textContent.toLowerCase();
        const position = row.querySelector('td:nth-child(5)').textContent.toLowerCase();
        const status = row.querySelector('td:nth-child(6) span').textContent.toLowerCase();

        const matchesSearch = name.includes(searchTerm) || email.includes(searchTerm);
        const matchesDepartment = !departmentFilter || department.includes(departmentFilter);
        const matchesPosition = !positionFilter || position.includes(positionFilter);
        const matchesStatus = !statusFilter || status.includes(statusFilter);

        if (matchesSearch && matchesDepartment && matchesPosition && matchesStatus) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function clearFilters() {
    document.getElementById('search-input').value = '';
    document.getElementById('department-filter').selectedIndex = 0;
    document.getElementById('position-filter').selectedIndex = 0;
    document.getElementById('status-filter').selectedIndex = 0;
    filterEmployees();
}

function toggleSelectAll() {
    const selectAllCheckbox = document.getElementById('select-all');
    const employeeCheckboxes = document.querySelectorAll('.employee-checkbox');
    
    employeeCheckboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
}

function updateSelectAllState() {
    const selectAllCheckbox = document.getElementById('select-all');
    const employeeCheckboxes = document.querySelectorAll('.employee-checkbox');
    const checkedCount = document.querySelectorAll('.employee-checkbox:checked').length;

    if (checkedCount === 0) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = false;
    } else if (checkedCount === employeeCheckboxes.length) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = true;
    } else {
        selectAllCheckbox.indeterminate = true;
    }
}

function handleBulkAction(action) {
    if (!action) return;

    const selectedEmployees = Array.from(document.querySelectorAll('.employee-checkbox:checked'))
        .map(checkbox => checkbox.dataset.id);

    if (selectedEmployees.length === 0) {
        showNotification('Please select at least one employee.', 'warning');
        return;
    }

    switch (action) {
        case 'activate':
            bulkActivateEmployees(selectedEmployees);
            break;
        case 'deactivate':
            bulkDeactivateEmployees(selectedEmployees);
            break;
        case 'update-department':
            bulkUpdateDepartment(selectedEmployees);
            break;
        case 'send-notification':
            bulkSendNotification(selectedEmployees);
            break;
    }

    // Reset the select dropdown
    document.querySelector('select[onchange="handleBulkAction(this.value)"]').selectedIndex = 0;
}

function bulkActivateEmployees(employeeIds) {
    if (confirm(`Are you sure you want to activate ${employeeIds.length} employee(s)?`)) {
        // Here you would make an API call to activate the employees
        console.log('Activating employees:', employeeIds);
        showNotification(`${employeeIds.length} employee(s) activated successfully.`, 'success');
        
        // Update UI
        employeeIds.forEach(id => {
            const row = document.querySelector(`[data-id="${id}"]`).closest('tr');
            const statusBadge = row.querySelector('.badge');
            statusBadge.className = 'badge badge-active';
            statusBadge.textContent = 'Active';
        });
    }
}

function bulkDeactivateEmployees(employeeIds) {
    if (confirm(`Are you sure you want to deactivate ${employeeIds.length} employee(s)?`)) {
        // Here you would make an API call to deactivate the employees
        console.log('Deactivating employees:', employeeIds);
        showNotification(`${employeeIds.length} employee(s) deactivated successfully.`, 'warning');
        
        // Update UI
        employeeIds.forEach(id => {
            const row = document.querySelector(`[data-id="${id}"]`).closest('tr');
            const statusBadge = row.querySelector('.badge');
            statusBadge.className = 'badge badge-pending';
            statusBadge.textContent = 'Inactive';
        });
    }
}

function bulkUpdateDepartment(employeeIds) {
    const newDepartment = prompt('Enter new department for selected employees:');
    if (newDepartment) {
        // Here you would make an API call to update departments
        console.log('Updating department for employees:', employeeIds, 'to:', newDepartment);
        showNotification(`Department updated for ${employeeIds.length} employee(s).`, 'success');
    }
}

function bulkSendNotification(employeeIds) {
    const message = prompt('Enter notification message:');
    if (message) {
        // Here you would make an API call to send notifications
        console.log('Sending notification to employees:', employeeIds, 'message:', message);
        showNotification(`Notification sent to ${employeeIds.length} employee(s).`, 'success');
    }
}

function viewEmployee(employeeId) {
    // Here you would open a modal or navigate to employee detail page
    console.log('Viewing employee:', employeeId);
    showNotification('Employee details would be displayed here.', 'info');
}

function editEmployee(employeeId) {
    // Here you would open edit modal with employee data
    console.log('Editing employee:', employeeId);
    showNotification('Edit employee form would be displayed here.', 'info');
}

function viewEmployeeHistory(employeeId) {
    // Here you would open employee history modal or page
    console.log('Viewing employee history for employee:', employeeId);
    showNotification('Employee history would be displayed here.', 'info');
}

function deleteEmployee(employeeId) {
    if (confirm('Are you sure you want to delete this employee? This action cannot be undone.')) {
        // Here you would make an API call to delete the employee
        console.log('Deleting employee:', employeeId);
        showNotification('Employee deleted successfully', 'success');
        
        // Remove the row from table
        const row = document.querySelector(`[data-id="${employeeId}"]`).closest('tr');
        if (row) {
            row.remove();
        }
    }
}

function assignShift(employeeId) {
    // Here you would open shift assignment modal
    console.log('Assigning shift for employee:', employeeId);
    showNotification('Shift assignment feature coming soon!', 'info');
}

function toggleEmployeeStatus(employeeId) {
    const row = document.querySelector(`[data-id="${employeeId}"]`).closest('tr');
    const statusBadge = row.querySelector('.badge');
    const toggleIcon = row.querySelector('.fa-toggle-on, .fa-toggle-off');
    
    const currentStatus = statusBadge.textContent.trim();
    const isActive = currentStatus === 'Active';
    const action = isActive ? 'suspend' : 'activate';
    
    if (confirm(`Are you sure you want to ${action} this employee?`)) {
        // Here you would make an API call to toggle status
        console.log(`${action} employee:`, employeeId);
        
        // Update UI
        if (isActive) {
            statusBadge.className = 'badge badge-suspended';
            statusBadge.textContent = 'Suspended';
            if (toggleIcon) toggleIcon.className = 'fas fa-toggle-off';
        } else {
            statusBadge.className = 'badge badge-active';
            statusBadge.textContent = 'Active';
            if (toggleIcon) toggleIcon.className = 'fas fa-toggle-on';
        }
        
        showNotification(`Employee ${action}d successfully`, 'success');
    }
}

function generateEmployeeId() {
    const employeeIdField = document.querySelector('input[name="employee_id"]');
    if (employeeIdField) {
        // Generate a simple employee ID (in real app, this would come from server)
        const timestamp = Date.now().toString().slice(-6);
        employeeIdField.value = `EMP${timestamp}`;
    }
}

function handleAddEmployee(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const employeeData = Object.fromEntries(formData.entries());
    
    // Validate required fields
    if (!employeeData.name || !employeeData.email || !employeeData.phone || 
        !employeeData.department || !employeeData.position) {
        showNotification('Please fill in all required fields.', 'error');
        return;
    }
    
    // Validate email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(employeeData.email)) {
        showNotification('Please enter a valid email address.', 'error');
        return;
    }
    
    // Validate phone format (basic validation)
    const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
    if (!phoneRegex.test(employeeData.phone.replace(/\s+/g, ''))) {
        showNotification('Please enter a valid phone number.', 'error');
        return;
    }
    
    // Here you would make an API call to create the employee
    console.log('Creating employee:', employeeData);
    
    // Simulate success
    showNotification('Employee added successfully.', 'success');
    closeModal('add-employee-modal');
    
    // Reset form
    event.target.reset();
    generateEmployeeId(); // Generate new ID for next employee
    
    // In a real application, you would refresh the table or add the new row
}

function exportEmployees() {
    // Here you would generate and download employee data
    console.log('Exporting employee data...');
    showNotification('Employee data export started.', 'info');
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
