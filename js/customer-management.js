// Customer Management JavaScript Functions

document.addEventListener('DOMContentLoaded', function() {
    initializeCustomerManagement();
});

function initializeCustomerManagement() {
    // Initialize search functionality
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', filterCustomers);
    }

    // Initialize filter dropdowns
    document.getElementById('business-type-filter')?.addEventListener('change', filterCustomers);
    document.getElementById('tier-filter')?.addEventListener('change', filterCustomers);
    document.getElementById('status-filter')?.addEventListener('change', filterCustomers);

    // Initialize select all checkbox
    const selectAllCheckbox = document.getElementById('select-all');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', toggleSelectAll);
    }

    // Initialize individual checkboxes
    const customerCheckboxes = document.querySelectorAll('.customer-checkbox');
    customerCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectAllState);
    });

    // Initialize form submission
    const addCustomerForm = document.getElementById('add-customer-form');
    if (addCustomerForm) {
        addCustomerForm.addEventListener('submit', handleAddCustomer);
    }

    // Auto-generate customer ID
    generateCustomerId();
}

function filterCustomers() {
    const searchTerm = document.getElementById('search-input').value.toLowerCase();
    const businessTypeFilter = document.getElementById('business-type-filter').value;
    const tierFilter = document.getElementById('tier-filter').value;
    const statusFilter = document.getElementById('status-filter').value;

    const rows = document.querySelectorAll('#customers-table-body tr');
    
    rows.forEach(row => {
        const companyName = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
        const email = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
        const businessType = row.querySelector('td:nth-child(4) span').textContent.toLowerCase();
        const tier = row.querySelector('td:nth-child(5) span').textContent.toLowerCase();
        const status = row.querySelector('td:nth-child(6) span').textContent.toLowerCase();

        const matchesSearch = companyName.includes(searchTerm) || email.includes(searchTerm);
        const matchesBusinessType = !businessTypeFilter || businessType.includes(businessTypeFilter);
        const matchesTier = !tierFilter || tier.includes(tierFilter);
        const matchesStatus = !statusFilter || status.includes(statusFilter);

        if (matchesSearch && matchesBusinessType && matchesTier && matchesStatus) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function clearFilters() {
    document.getElementById('search-input').value = '';
    document.getElementById('business-type-filter').selectedIndex = 0;
    document.getElementById('tier-filter').selectedIndex = 0;
    document.getElementById('status-filter').selectedIndex = 0;
    filterCustomers();
}

function toggleSelectAll() {
    const selectAllCheckbox = document.getElementById('select-all');
    const customerCheckboxes = document.querySelectorAll('.customer-checkbox');
    
    customerCheckboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
}

function updateSelectAllState() {
    const selectAllCheckbox = document.getElementById('select-all');
    const customerCheckboxes = document.querySelectorAll('.customer-checkbox');
    const checkedCount = document.querySelectorAll('.customer-checkbox:checked').length;

    if (checkedCount === 0) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = false;
    } else if (checkedCount === customerCheckboxes.length) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = true;
    } else {
        selectAllCheckbox.indeterminate = true;
    }
}

function handleBulkAction(action) {
    if (!action) return;

    const selectedCustomers = Array.from(document.querySelectorAll('.customer-checkbox:checked'))
        .map(checkbox => checkbox.dataset.id);

    if (selectedCustomers.length === 0) {
        showNotification('Please select at least one customer.', 'warning');
        return;
    }

    switch (action) {
        case 'activate':
            bulkActivateCustomers(selectedCustomers);
            break;
        case 'suspend':
            bulkSuspendCustomers(selectedCustomers);
            break;
        case 'upgrade-tier':
            bulkUpgradeTier(selectedCustomers);
            break;
        case 'send-notification':
            bulkSendNotification(selectedCustomers);
            break;
    }

    // Reset the select dropdown
    document.querySelector('select[onchange="handleBulkAction(this.value)"]').selectedIndex = 0;
}

function bulkActivateCustomers(customerIds) {
    if (confirm(`Are you sure you want to activate ${customerIds.length} customer(s)?`)) {
        // Here you would make an API call to activate the customers
        console.log('Activating customers:', customerIds);
        showNotification(`${customerIds.length} customer(s) activated successfully.`, 'success');
        
        // Update UI
        customerIds.forEach(id => {
            const row = document.querySelector(`[data-id="${id}"]`).closest('tr');
            const statusBadge = row.querySelector('.badge');
            statusBadge.className = 'badge badge-active';
            statusBadge.textContent = 'Active';
        });
    }
}

function bulkSuspendCustomers(customerIds) {
    if (confirm(`Are you sure you want to suspend ${customerIds.length} customer(s)?`)) {
        // Here you would make an API call to suspend the customers
        console.log('Suspending customers:', customerIds);
        showNotification(`${customerIds.length} customer(s) suspended successfully.`, 'warning');
        
        // Update UI
        customerIds.forEach(id => {
            const row = document.querySelector(`[data-id="${id}"]`).closest('tr');
            const statusBadge = row.querySelector('.badge');
            statusBadge.className = 'badge badge-warning';
            statusBadge.textContent = 'Suspended';
        });
    }
}

function bulkUpgradeTier(customerIds) {
    const newTier = prompt('Enter new tier for selected customers (basic/premium/enterprise):');
    if (newTier && ['basic', 'premium', 'enterprise'].includes(newTier.toLowerCase())) {
        // Here you would make an API call to upgrade tiers
        console.log('Upgrading tier for customers:', customerIds, 'to:', newTier);
        showNotification(`Tier upgraded for ${customerIds.length} customer(s).`, 'success');
        
        // Update UI
        customerIds.forEach(id => {
            const row = document.querySelector(`[data-id="${id}"]`).closest('tr');
            const tierBadge = row.querySelector('td:nth-child(5) span');
            tierBadge.textContent = newTier.charAt(0).toUpperCase() + newTier.slice(1);
            
            // Update badge color based on tier
            const colorClass = newTier === 'enterprise' ? 'bg-purple-100 text-purple-800' :
                             newTier === 'premium' ? 'bg-blue-100 text-blue-800' :
                             'bg-gray-100 text-gray-800';
            tierBadge.className = `px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${colorClass}`;
        });
    } else if (newTier) {
        showNotification('Invalid tier. Please enter: basic, premium, or enterprise.', 'error');
    }
}

function bulkSendNotification(customerIds) {
    const subject = prompt('Enter notification subject:');
    if (subject) {
        const message = prompt('Enter notification message:');
        if (message) {
            // Here you would make an API call to send notifications
            console.log('Sending notification to customers:', customerIds, 'subject:', subject, 'message:', message);
            showNotification(`Notification sent to ${customerIds.length} customer(s).`, 'success');
        }
    }
}

function viewCustomer(customerId) {
    // Here you would open a modal or navigate to customer detail page
    console.log('Viewing customer:', customerId);
    showNotification('Customer details would be displayed here.', 'info');
}

function editCustomer(customerId) {
    // Here you would open edit modal with customer data
    console.log('Editing customer:', customerId);
    showNotification('Edit customer form would be displayed here.', 'info');
}

function viewBorrowingHistory(customerId) {
    // Here you would open borrowing history modal or page
    console.log('Viewing borrowing history for customer:', customerId);
    showNotification('Borrowing history would be displayed here.', 'info');
}

function toggleCustomerStatus(customerId) {
    const row = document.querySelector(`[data-id="${customerId}"]`).closest('tr');
    const statusBadge = row.querySelector('.badge');
    const toggleIcon = row.querySelector('.fa-toggle-on, .fa-toggle-off');
    
    const currentStatus = statusBadge.textContent.trim();
    const isActive = currentStatus === 'Active';
    const action = isActive ? 'suspend' : 'activate';
    
    if (confirm(`Are you sure you want to ${action} this customer?`)) {
        // Here you would make an API call to toggle status
        console.log(`${action} customer:`, customerId);
        
        // Update UI
        if (isActive) {
            statusBadge.className = 'badge badge-warning';
            statusBadge.textContent = 'Suspended';
            toggleIcon.className = 'fas fa-toggle-off';
        } else {
            statusBadge.className = 'badge badge-active';
            statusBadge.textContent = 'Active';
            toggleIcon.className = 'fas fa-toggle-on';
        }
        
        showNotification(`Customer ${action}d successfully.`, 'success');
    }
}

function generateCustomerId() {
    const customerIdField = document.querySelector('input[name="customer_id"]');
    if (customerIdField) {
        // Generate a simple customer ID (in real app, this would come from server)
        const randomId = Math.floor(Math.random() * 9000) + 1000;
        customerIdField.value = `#C${randomId}`;
    }
}

function handleAddCustomer(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const customerData = Object.fromEntries(formData.entries());
    
    // Collect categories
    const categories = Array.from(formData.getAll('categories[]'));
    customerData.categories = categories;
    
    // Validate required fields
    if (!customerData.company_name || !customerData.contact_person || !customerData.email || 
        !customerData.phone || !customerData.business_type || !customerData.membership_tier || 
        !customerData.address) {
        showNotification('Please fill in all required fields.', 'error');
        return;
    }
    
    // Validate email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(customerData.email)) {
        showNotification('Please enter a valid email address.', 'error');
        return;
    }
    
    // Validate phone format (basic validation)
    const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
    if (!phoneRegex.test(customerData.phone.replace(/\s+/g, ''))) {
        showNotification('Please enter a valid phone number.', 'error');
        return;
    }
    
    // Here you would make an API call to create the customer
    console.log('Creating customer:', customerData);
    
    // Simulate success
    showNotification('Customer added successfully.', 'success');
    closeModal('add-customer-modal');
    
    // Reset form
    event.target.reset();
    generateCustomerId(); // Generate new ID for next customer
    
    // In a real application, you would refresh the table or add the new row
}

function exportCustomers() {
    // Here you would generate and download customer data
    console.log('Exporting customer data...');
    showNotification('Customer data export started.', 'info');
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
