// Employee Management JavaScript Functions

// Detect the correct API path based on current location
const API_PATH = window.location.pathname.includes('/user-management/') ? '../api/' : 'api/';

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

    // Initialize form submission
    const addEmployeeForm = document.getElementById('add-employee-form');
    if (addEmployeeForm) {
        addEmployeeForm.addEventListener('submit', handleAddEmployee);
    }

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
    // Fetch employee details from API
    fetch(`${API_PATH}employees.php?action=get&id=${employeeId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showEmployeeModal(data.employee);
            } else {
                showNotification(data.message || 'Failed to load employee details', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error loading employee details', 'error');
        });
}

function editEmployee(employeeId) {
    // Fetch employee details and show in edit modal
    fetch(`${API_PATH}employees.php?action=get&id=${employeeId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showEditEmployeeModal(data.employee);
            } else {
                showNotification(data.message || 'Failed to load employee details', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error loading employee details', 'error');
        });
}

function deleteEmployee(employeeId) {
    if (confirm('Are you sure you want to delete this employee? This action cannot be undone.')) {
        fetch(`${API_PATH}employees.php`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'delete',
                id: employeeId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove the row from table
                const row = document.querySelector(`[data-id="${employeeId}"]`).closest('tr');
                row.remove();
                showNotification('Employee deleted successfully', 'success');
                updateEmployeeStats();
            } else {
                showNotification(data.message || 'Failed to delete employee', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error deleting employee', 'error');
        });
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
    
    const currentStatus = statusBadge.textContent.trim().toLowerCase();
    const isActive = currentStatus === 'active';
    const newStatus = isActive ? 'inactive' : 'active';
    const action = isActive ? 'deactivate' : 'activate';
    
    if (confirm(`Are you sure you want to ${action} this employee?`)) {
        fetch(`${API_PATH}employees.php`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'update',
                id: employeeId,
                status: newStatus
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update UI
                if (newStatus === 'active') {
                    statusBadge.className = 'badge badge-active';
                    statusBadge.textContent = 'Active';
                    toggleIcon.className = 'fas fa-toggle-on';
                } else {
                    statusBadge.className = 'badge badge-inactive';
                    statusBadge.textContent = 'Inactive';
                    toggleIcon.className = 'fas fa-toggle-off';
                }
                showNotification(`Employee ${action}d successfully`, 'success');
                updateEmployeeStats();
            } else {
                showNotification(data.message || `Failed to ${action} employee`, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification(`Error ${action}ing employee`, 'error');
        });
    }
}

function generateEmployeeId() {
    const employeeIdField = document.querySelector('input[name="employee_id"]');
    if (employeeIdField) {
        // Generate a simple employee ID (in real app, this would come from server)
        const randomId = Math.floor(Math.random() * 9000) + 1000;
        employeeIdField.value = `#E${randomId}`;
    }
}

function handleAddEmployee(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const employeeData = Object.fromEntries(formData.entries());
    
    // Validate required fields
    if (!employeeData.name || !employeeData.email || !employeeData.phone || 
        !employeeData.department || !employeeData.position || !employeeData.hire_date) {
        showNotification('Please fill in all required fields.', 'error');
        return;
    }
    
    // Validate email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(employeeData.email)) {
        showNotification('Please enter a valid email address.', 'error');
        return;
    }
    
    // Make API call to create employee
    fetch('api/employees.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'create',
            ...employeeData
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Employee added successfully', 'success');
            closeModal('add-employee-modal');
            
            // Reset form
            event.target.reset();
            generateEmployeeId(); // Generate new ID for next employee
            
            // Refresh the page to show new employee
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showNotification(data.message || 'Failed to add employee', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error adding employee', 'error');
    });
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

// Modal Functions
function showEmployeeModal(employee) {
    // Create and show employee details modal
    const modalHtml = `
        <div id="employee-details-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Employee Details</h3>
                    <button onclick="closeModal('employee-details-modal')" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Name</label>
                        <p class="mt-1 text-sm text-gray-900">${employee.name}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Employee ID</label>
                        <p class="mt-1 text-sm text-gray-900">${employee.employee_id}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <p class="mt-1 text-sm text-gray-900">${employee.email}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Phone</label>
                        <p class="mt-1 text-sm text-gray-900">${employee.phone}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Department</label>
                        <p class="mt-1 text-sm text-gray-900">${employee.department}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Position</label>
                        <p class="mt-1 text-sm text-gray-900">${employee.position}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <p class="mt-1 text-sm text-gray-900">${employee.status}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Hire Date</label>
                        <p class="mt-1 text-sm text-gray-900">${employee.hire_date}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Salary</label>
                        <p class="mt-1 text-sm text-gray-900">$${employee.salary || 'N/A'}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Shift</label>
                        <p class="mt-1 text-sm text-gray-900">${employee.shift || 'N/A'}</p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Address</label>
                        <p class="mt-1 text-sm text-gray-900">${employee.address || 'N/A'}</p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Emergency Contact</label>
                        <p class="mt-1 text-sm text-gray-900">${employee.emergency_contact || 'N/A'}</p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button onclick="editEmployee(${employee.id})" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        Edit Employee
                    </button>
                    <button onclick="closeModal('employee-details-modal')" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">
                        Close
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

function showEditEmployeeModal(employee) {
    // Create and show employee edit modal
    const modalHtml = `
        <div id="edit-employee-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Edit Employee</h3>
                    <button onclick="closeModal('edit-employee-modal')" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form id="edit-employee-form" onsubmit="handleEditEmployee(event, ${employee.id})">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Name*</label>
                            <input type="text" name="name" value="${employee.name}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Employee ID*</label>
                            <input type="text" name="employee_id" value="${employee.employee_id}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Email*</label>
                            <input type="email" name="email" value="${employee.email}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Phone*</label>
                            <input type="tel" name="phone" value="${employee.phone}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Department*</label>
                            <select name="department" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="warehouse" ${employee.department === 'warehouse' ? 'selected' : ''}>Warehouse</option>
                                <option value="inventory" ${employee.department === 'inventory' ? 'selected' : ''}>Inventory</option>
                                <option value="logistics" ${employee.department === 'logistics' ? 'selected' : ''}>Logistics</option>
                                <option value="quality-control" ${employee.department === 'quality-control' ? 'selected' : ''}>Quality Control</option>
                                <option value="administration" ${employee.department === 'administration' ? 'selected' : ''}>Administration</option>
                                <option value="security" ${employee.department === 'security' ? 'selected' : ''}>Security</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Position*</label>
                            <input type="text" name="position" value="${employee.position}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Status</label>
                            <select name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="active" ${employee.status === 'active' ? 'selected' : ''}>Active</option>
                                <option value="inactive" ${employee.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                                <option value="on-leave" ${employee.status === 'on-leave' ? 'selected' : ''}>On Leave</option>
                                <option value="terminated" ${employee.status === 'terminated' ? 'selected' : ''}>Terminated</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Hire Date*</label>
                            <input type="date" name="hire_date" value="${employee.hire_date}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Salary</label>
                            <input type="number" name="salary" value="${employee.salary || ''}" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Shift</label>
                            <select name="shift" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Select Shift</option>
                                <option value="morning" ${employee.shift === 'morning' ? 'selected' : ''}>Morning</option>
                                <option value="afternoon" ${employee.shift === 'afternoon' ? 'selected' : ''}>Afternoon</option>
                                <option value="night" ${employee.shift === 'night' ? 'selected' : ''}>Night</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Address</label>
                            <textarea name="address" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">${employee.address || ''}</textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Emergency Contact</label>
                            <input type="text" name="emergency_contact" value="${employee.emergency_contact || ''}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                            Update Employee
                        </button>
                        <button type="button" onclick="closeModal('edit-employee-modal')" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

function handleEditEmployee(event, employeeId) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const employeeData = Object.fromEntries(formData.entries());
    
    // Make API call to update employee
    fetch(`${API_PATH}employees.php`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'update',
            id: employeeId,
            ...employeeData
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Employee updated successfully', 'success');
            closeModal('edit-employee-modal');
            // Refresh the page to show updated data
            window.location.reload();
        } else {
            showNotification(data.message || 'Failed to update employee', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error updating employee', 'error');
    });
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.remove();
    }
}

function updateEmployeeStats() {
    // Refresh the page to update stats (in a real app, you might make an AJAX call)
    // For now, we'll just refresh the entire page
    setTimeout(() => {
        window.location.reload();
    }, 1000);
}
