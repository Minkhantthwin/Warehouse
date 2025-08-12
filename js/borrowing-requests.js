// Borrowing Requests JavaScript functionality

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeBorrowingRequests();
});

function initializeBorrowingRequests() {
    setupEventListeners();
    loadRequestsData();
}

function setupEventListeners() {
    // Search functionality
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            handleSearch(e.target.value, filterRequests);
        });
    }

    // Status filter
    const statusFilter = document.getElementById('status-filter');
    if (statusFilter) {
        statusFilter.addEventListener('change', function(e) {
            filterByStatus(e.target.value);
        });
    }

    // Location filter
    const locationFilter = document.getElementById('location-filter');
    if (locationFilter) {
        locationFilter.addEventListener('change', function(e) {
            filterByLocation(e.target.value);
        });
    }

    // Date filter
    const dateFilter = document.getElementById('date-filter');
    if (dateFilter) {
        dateFilter.addEventListener('change', function(e) {
            filterByDate(e.target.value);
        });
    }

    // Form submission
    const addRequestForm = document.getElementById('add-request-form');
    if (addRequestForm) {
        addRequestForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleCreateRequest();
        });
    }
}

function loadRequestsData() {
    // Simulate loading data from API
    console.log('Loading borrowing requests data...');
    
    // In a real application, this would be an API call
    // fetch('/api/borrowing-requests')
    //     .then(response => response.json())
    //     .then(data => updateRequestsTable(data));
}

function filterRequests(searchTerm) {
    const table = document.querySelector('tbody');
    if (!table) return;

    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const matches = text.includes(searchTerm.toLowerCase());
        row.style.display = matches ? '' : 'none';
    });
}

function filterByStatus(status) {
    const table = document.querySelector('tbody');
    if (!table) return;

    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        if (!status) {
            row.style.display = '';
            return;
        }

        const statusBadge = row.querySelector('.badge');
        if (statusBadge) {
            const rowStatus = statusBadge.textContent.toLowerCase();
            const matches = rowStatus === status.toLowerCase();
            row.style.display = matches ? '' : 'none';
        }
    });
}

function filterByLocation(location) {
    const table = document.querySelector('tbody');
    if (!table) return;

    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        if (!location) {
            row.style.display = '';
            return;
        }

        const locationCell = row.children[4]; // Location column
        if (locationCell) {
            const rowLocation = locationCell.textContent.toLowerCase();
            const matches = rowLocation.includes(location.toLowerCase().replace('-', ' '));
            row.style.display = matches ? '' : 'none';
        }
    });
}

function filterByDate(date) {
    // Implementation for date filtering
    console.log('Filtering by date:', date);
}

// Request Actions
function viewRequest(requestId) {
    console.log('Viewing request:', requestId);
    
    // Create and show view modal
    const modalContent = `
        <div class="modal" id="view-request-modal">
            <div class="modal-overlay" onclick="closeModal('view-request-modal')"></div>
            <div class="modal-content max-w-4xl">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-800">Request Details - ${requestId}</h3>
                    <button onclick="closeModal('view-request-modal')" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <h4 class="font-medium text-gray-700">Customer Information</h4>
                            <div class="mt-2 p-4 bg-gray-50 rounded-lg">
                                <p><strong>Name:</strong> John Doe</p>
                                <p><strong>Email:</strong> john@example.com</p>
                                <p><strong>Phone:</strong> +1 234 567 8900</p>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="font-medium text-gray-700">Request Details</h4>
                            <div class="mt-2 p-4 bg-gray-50 rounded-lg">
                                <p><strong>Location:</strong> Warehouse A</p>
                                <p><strong>Request Date:</strong> Jan 15, 2025</p>
                                <p><strong>Required Date:</strong> Jan 20, 2025</p>
                                <p><strong>Status:</strong> <span class="badge badge-pending">Pending</span></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <div>
                            <h4 class="font-medium text-gray-700">Requested Items</h4>
                            <div class="mt-2 p-4 bg-gray-50 rounded-lg">
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span>Construction Tools</span>
                                        <span>Qty: 5</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Safety Equipment</span>
                                        <span>Qty: 2</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="font-medium text-gray-700">Purpose</h4>
                            <div class="mt-2 p-4 bg-gray-50 rounded-lg">
                                <p>Construction project for building renovation. Need tools for 5 days.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6 pt-6 border-t">
                    <button onclick="closeModal('view-request-modal')" class="btn btn-secondary">Close</button>
                    <button onclick="approveRequest('${requestId}')" class="btn btn-success">
                        <i class="fas fa-check mr-2"></i>Approve
                    </button>
                    <button onclick="rejectRequest('${requestId}')" class="btn btn-danger">
                        <i class="fas fa-times mr-2"></i>Reject
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalContent);
    
    // Show modal with animation
    setTimeout(() => {
        document.getElementById('view-request-modal').classList.remove('hidden');
    }, 10);
}

function approveRequest(requestId) {
    if (confirm(`Are you sure you want to approve request ${requestId}?`)) {
        console.log('Approving request:', requestId);
        
        // Simulate API call
        showNotification(`Request ${requestId} has been approved successfully!`, 'success');
        
        // Update the status in the table
        updateRequestStatus(requestId, 'approved');
        
        // Close any open modals
        closeModal('view-request-modal');
    }
}

function rejectRequest(requestId) {
    if (confirm(`Are you sure you want to reject request ${requestId}?`)) {
        console.log('Rejecting request:', requestId);
        
        // Simulate API call
        showNotification(`Request ${requestId} has been rejected.`, 'warning');
        
        // Update the status in the table
        updateRequestStatus(requestId, 'rejected');
        
        // Close any open modals
        closeModal('view-request-modal');
    }
}

function editRequest(requestId) {
    console.log('Editing request:', requestId);
    
    // Pre-populate the form with existing data
    openModal('add-request-modal');
    
    // Change modal title
    const modalTitle = document.querySelector('#add-request-modal h3');
    if (modalTitle) {
        modalTitle.textContent = `Edit Request ${requestId}`;
    }
    
    // Change submit button text
    const submitBtn = document.querySelector('#add-request-form button[type="submit"]');
    if (submitBtn) {
        submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Update Request';
    }
}

function processReturn(requestId) {
    console.log('Processing return for request:', requestId);
    
    const modalContent = `
        <div class="modal" id="return-modal">
            <div class="modal-overlay" onclick="closeModal('return-modal')"></div>
            <div class="modal-content">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-800">Process Return - ${requestId}</h3>
                    <button onclick="closeModal('return-modal')" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form id="return-form">
                    <div class="space-y-4">
                        <div>
                            <h4 class="font-medium text-gray-700 mb-3">Return Items</h4>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div>
                                        <span class="font-medium">Construction Tools</span>
                                        <div class="text-sm text-gray-500">Borrowed: 5</div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <input type="number" class="form-input w-20" placeholder="5" max="5">
                                        <select class="form-select w-32">
                                            <option value="good">Good</option>
                                            <option value="damaged">Damaged</option>
                                            <option value="lost">Lost</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div>
                                        <span class="font-medium">Safety Equipment</span>
                                        <div class="text-sm text-gray-500">Borrowed: 2</div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <input type="number" class="form-input w-20" placeholder="2" max="2">
                                        <select class="form-select w-32">
                                            <option value="good">Good</option>
                                            <option value="damaged">Damaged</option>
                                            <option value="lost">Lost</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Return Notes</label>
                            <textarea class="form-textarea" rows="3" placeholder="Any notes about the return..."></textarea>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeModal('return-modal')" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary">Process Return</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalContent);
    
    // Show modal
    setTimeout(() => {
        document.getElementById('return-modal').classList.remove('hidden');
    }, 10);
    
    // Handle form submission
    document.getElementById('return-form').addEventListener('submit', function(e) {
        e.preventDefault();
        handleProcessReturn(requestId);
    });
}

function sendReminder(requestId) {
    console.log('Sending reminder for request:', requestId);
    
    if (confirm(`Send reminder notification for request ${requestId}?`)) {
        // Simulate API call
        showNotification(`Reminder sent for request ${requestId}`, 'info');
    }
}

function updateRequestStatus(requestId, newStatus) {
    // Find the row with this request ID and update its status
    const table = document.querySelector('tbody');
    if (!table) return;

    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const idCell = row.querySelector('td:nth-child(2)');
        if (idCell && idCell.textContent.includes(requestId)) {
            const statusCell = row.querySelector('.badge');
            if (statusCell) {
                statusCell.className = `badge badge-${newStatus}`;
                statusCell.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
            }
        }
    });
}

// Form handling
function handleCreateRequest() {
    const form = document.getElementById('add-request-form');
    const formData = new FormData(form);
    
    // Validate form
    const errors = validateRequestForm(formData);
    if (errors.length > 0) {
        showNotification(errors.join(', '), 'error');
        return;
    }
    
    // Simulate API call
    console.log('Creating new request...');
    
    setTimeout(() => {
        showNotification('Request created successfully!', 'success');
        closeModal('add-request-modal');
        
        // Reset form
        form.reset();
        
        // Refresh table (in real app, would refetch data)
        loadRequestsData();
    }, 1000);
}

function handleProcessReturn(requestId) {
    console.log('Processing return for:', requestId);
    
    showNotification(`Return processed for request ${requestId}`, 'success');
    closeModal('return-modal');
    
    // Update status to returned
    updateRequestStatus(requestId, 'returned');
}

function validateRequestForm(formData) {
    const errors = [];
    
    // Add validation logic here
    console.log('Validating form data:', formData);
    
    return errors;
}

// Item management for the form
function addItem() {
    const container = document.getElementById('items-container');
    const itemDiv = document.createElement('div');
    itemDiv.className = 'grid grid-cols-3 gap-2 mb-2';
    
    itemDiv.innerHTML = `
        <select class="form-select">
            <option value="">Select Material</option>
            <option value="1">Construction Tools</option>
            <option value="2">Safety Equipment</option>
            <option value="3">Office Supplies</option>
            <option value="4">Electronic Equipment</option>
        </select>
        <input type="number" class="form-input" placeholder="Quantity" min="1">
        <button type="button" onclick="removeItem(this)" class="btn btn-danger">
            <i class="fas fa-trash"></i>
        </button>
    `;
    
    container.appendChild(itemDiv);
}

function removeItem(button) {
    const itemDiv = button.closest('.grid');
    if (itemDiv) {
        itemDiv.remove();
    }
}

// Bulk actions
function handleBulkAction(action) {
    const checkboxes = document.querySelectorAll('tbody input[type="checkbox"]:checked');
    const selectedIds = Array.from(checkboxes).map(cb => {
        const row = cb.closest('tr');
        const idCell = row.querySelector('td:nth-child(2)');
        return idCell ? idCell.textContent.trim() : null;
    }).filter(id => id);
    
    if (selectedIds.length === 0) {
        showNotification('Please select at least one request', 'warning');
        return;
    }
    
    switch(action) {
        case 'approve':
            if (confirm(`Approve ${selectedIds.length} selected requests?`)) {
                selectedIds.forEach(id => approveRequest(id));
            }
            break;
        case 'reject':
            if (confirm(`Reject ${selectedIds.length} selected requests?`)) {
                selectedIds.forEach(id => rejectRequest(id));
            }
            break;
        case 'delete':
            if (confirm(`Delete ${selectedIds.length} selected requests?`)) {
                // Handle delete
                showNotification(`${selectedIds.length} requests deleted`, 'info');
            }
            break;
    }
}

// Export functions
window.borrowingRequestsFunctions = {
    viewRequest,
    approveRequest,
    rejectRequest,
    editRequest,
    processReturn,
    sendReminder,
    addItem,
    removeItem,
    handleBulkAction
};
