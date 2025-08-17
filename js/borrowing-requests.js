// Borrowing Requests JavaScript functionality
const API_BASE_URL = 'api/borrowing-requests.php';

// Global state
let currentPage = 1;
let currentFilters = {};
let requestsData = [];
let itemTypesData = [];
let customersData = [];
let employeesData = [];
let locationsData = [];

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeBorrowingRequests();
});

function initializeBorrowingRequests() {
    console.log('Initializing borrowing requests...');
    setupEventListeners();
    
    // Load data sequentially to debug issues
    setTimeout(() => {
        console.log('Loading initial data...');
        loadInitialData();
    }, 100);
    
    setTimeout(() => {
        console.log('Loading statistics...');
        loadStatistics();
    }, 200);
    
    setTimeout(() => {
        console.log('Loading requests data...');
        loadRequestsData();
    }, 300);
}

function setupEventListeners() {
    // Search functionality
    const searchInput = document.getElementById('search');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentFilters.search = e.target.value;
                loadRequestsData();
            }, 500);
        });
    }

    // Filter changes
    ['status-filter', 'customer-filter', 'location-filter', 'date-from', 'date-to'].forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('change', function(e) {
                const filterName = id.replace('-filter', '').replace('-', '_');
                currentFilters[filterName] = e.target.value;
                currentPage = 1; // Reset to first page
                loadRequestsData();
            });
        }
    });

    // Select all checkbox
    const selectAllCheckbox = document.getElementById('select-all');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function(e) {
            const checkboxes = document.querySelectorAll('.request-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = e.target.checked;
            });
        });
    }

    // Form submission
    const newRequestForm = document.getElementById('new-request-form');
    console.log('Form element found:', newRequestForm);
    if (newRequestForm) {
        console.log('Adding event listener to form');
        newRequestForm.addEventListener('submit', function(e) {
            console.log('Form submit event triggered');
            e.preventDefault();
            handleCreateRequest();
        });
    } else {
        console.log('Form not found during initialization');
    }

    // Modal close buttons
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay') || e.target.closest('.close-modal')) {
            const modal = e.target.closest('.modal') || e.target.classList.contains('modal') ? e.target : null;
            if (modal) {
                closeModal(modal.id);
            }
        }
    });
}

// API Functions
async function apiRequest(endpoint, options = {}) {
    try {
        const url = endpoint.includes('?') ? `${API_BASE_URL}?${endpoint.split('?')[1]}` : `${API_BASE_URL}?action=${endpoint}`;
        
        console.log(`Making API request to: ${url}`);
        console.log(`Full endpoint parameter: "${endpoint}"`);
        console.log(`API_BASE_URL: "${API_BASE_URL}"`);
        
        const defaultOptions = {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        // Only set Content-Type to application/json if we're not sending FormData
        if (!(options.body instanceof FormData)) {
            defaultOptions.headers['Content-Type'] = 'application/json';
        }

        const finalOptions = { ...defaultOptions, ...options };
        
        // If options.headers is provided and empty (for FormData), merge properly
        if (options.headers && Object.keys(options.headers).length === 0) {
            finalOptions.headers = { 'X-Requested-With': 'XMLHttpRequest' };
        }

        const response = await fetch(url, finalOptions);
        
        console.log(`API response status: ${response.status} ${response.statusText}`);
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error(`API Error Response:`, errorText);
            throw new Error(`HTTP ${response.status}: ${response.statusText}${errorText ? ` - ${errorText}` : ''}`);
        }
        
        const data = await response.json();
        console.log(`API response data:`, data);
        
        if (!data.success) {
            throw new Error(data.error || 'API request failed - no error message provided');
        }
        
        return data;
    } catch (error) {
        console.error('API request failed:', error);
        
        // Provide more specific error messages
        let errorMessage = error.message;
        if (error.name === 'TypeError' && error.message.includes('fetch')) {
            errorMessage = 'Network error: Unable to connect to the server. Please check if the server is running.';
        } else if (error.message.includes('404')) {
            errorMessage = 'API endpoint not found. Please check if the API file exists.';
        } else if (error.message.includes('500')) {
            errorMessage = 'Server error. Please check the server logs for more details.';
        }
        
        showNotification('API Error: ' + errorMessage, 'error');
        throw error;
    }
}

// Load initial data
async function loadInitialData() {
    try {
        console.log('Loading initial data...');
        
        // Load materials, customers, employees, locations for dropdowns
        const requests = [
            fetch('api/borrowing-requests.php?action=get_item_types'),
            fetch('api/customers.php?action=list&active_only=1'),
            fetch('api/employees.php?action=list&active_only=1'),
            fetch('api/locations.php?action=list')
        ];

        const responses = await Promise.allSettled(requests);
        
        // Process each response individually to identify which one failed
        const results = await Promise.allSettled(
            responses.map(async (response, index) => {
                const apiNames = ['item types', 'customers', 'employees', 'locations'];
                if (response.status === 'rejected') {
                    throw new Error(`${apiNames[index]} API request failed: ${response.reason}`);
                }
                
                if (!response.value.ok) {
                    throw new Error(`${apiNames[index]} API returned ${response.value.status}: ${response.value.statusText}`);
                }
                
                return response.value.json();
            })
        );

        // Extract data from successful responses
        const [itemTypes, customers, employees, locations] = results.map((result, index) => {
            const apiNames = ['item types', 'customers', 'employees', 'locations'];
            if (result.status === 'rejected') {
                console.error(`Failed to load ${apiNames[index]}:`, result.reason);
                showNotification(`Failed to load ${apiNames[index]}: ${result.reason}`, 'error');
                return { success: false, data: [] };
            }
            return result.value;
        });

        itemTypesData = itemTypes.success ? itemTypes.data : [];
        customersData = customers.success ? customers.data : [];
        employeesData = employees.success ? employees.data : [];
        locationsData = locations.success ? locations.data : [];

        console.log('Initial data loaded:', {
            itemTypes: itemTypesData.length,
            customers: customersData.length,
            employees: employeesData.length,
            locations: locationsData.length
        });

        populateDropdowns();
        
        // Show summary of what was loaded
        const loadedItems = [];
        if (itemTypesData.length > 0) loadedItems.push(`${itemTypesData.length} item types`);
        if (customersData.length > 0) loadedItems.push(`${customersData.length} customers`);
        if (employeesData.length > 0) loadedItems.push(`${employeesData.length} employees`);
        if (locationsData.length > 0) loadedItems.push(`${locationsData.length} locations`);
        
        if (loadedItems.length > 0) {
            console.log(`Successfully loaded: ${loadedItems.join(', ')}`);
        }
        
    } catch (error) {
        console.error('Error loading initial data:', error);
        showNotification(`Failed to load initial data: ${error.message}`, 'error');
    }
}

// Load statistics
async function loadStatistics() {
    try {
        console.log('Loading statistics...');
        const stats = await apiRequest('stats');
        console.log('Statistics loaded:', stats.data);
        updateStatisticsCards(stats.data);
    } catch (error) {
        console.error('Error loading statistics:', error);
        showNotification(`Failed to load statistics: ${error.message}`, 'warning');
        
        // Set default statistics to prevent UI errors
        updateStatisticsCards({
            total_requests: 0,
            status_breakdown: {},
            overdue_requests: 0,
            today_requests: 0,
            week_requests: 0
        });
    }
}

// Load requests data
async function loadRequestsData() {
    try {
        showLoading();
        
        const params = new URLSearchParams({
            page: currentPage,
            limit: 20,
            ...currentFilters
        });

        console.log('Loading requests with params:', params.toString());

        const response = await apiRequest(`list?${params.toString()}`);
        
        requestsData = response.data;
        console.log(`Loaded ${response.data.length} requests`);
        
        updateRequestsTable(response.data);
        updatePagination(response.pagination);
        
    } catch (error) {
        console.error('Error loading requests:', error);
        
        // Show empty table with error message
        const tableBody = document.querySelector('#requests-table tbody');
        if (tableBody) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="10" class="px-6 py-8 text-center text-red-500">
                        <i class="fas fa-exclamation-triangle text-4xl mb-4 block"></i>
                        <div class="text-lg font-medium mb-2">Failed to load borrowing requests</div>
                        <div class="text-sm">${error.message}</div>
                    </td>
                </tr>
            `;
        }
        
        showNotification(`Failed to load requests: ${error.message}`, 'error');
    } finally {
        hideLoading();
    }
}

// Handle create request
async function handleCreateRequest() {
    console.log('handleCreateRequest called');
    
    // Show immediate feedback
    showNotification('Processing request...', 'info');
    
    try {
        console.log('Getting form data...');
        const formData = getFormData('new-request-form');
        console.log('Form data:', formData);
        
        console.log('Getting items...');
        const items = getItemsFromForm();
        console.log('Items:', items);
        
        console.log('Validating form...');
        if (!validateRequestForm(formData, items)) {
            console.log('Form validation failed');
            return;
        }

        console.log('Form validation passed, sending request...');
        showLoading();

        const requestData = {
            customer_id: formData.customer_id,
            employee_id: formData.employee_id,
            location_id: formData.location_id,
            required_date: formData.required_date,
            purpose: formData.purpose,
            notes: formData.notes || '',
            items: items
        };

        console.log('Request data to send:', requestData);

        const response = await apiRequest('create', {
            method: 'POST',
            body: JSON.stringify(requestData)
        });

        console.log('API response:', response);

        showNotification('Borrowing request created successfully', 'success');
        closeModal('new-request-modal');
        resetForm('new-request-form');
        loadRequestsData();
        loadStatistics();

    } catch (error) {
        console.error('Error creating request:', error);
    } finally {
        hideLoading();
    }
}

// Get form data
function getFormData(formId) {
    const form = document.getElementById(formId);
    if (!form) {
        console.error('Form not found:', formId);
        return {};
    }
    
    const formData = new FormData(form);
    const data = {};
    
    for (const [key, value] of formData.entries()) {
        // Skip materials array fields as they are handled separately
        if (!key.startsWith('materials[')) {
            data[key] = value;
        }
    }
    
    return data;
}

// Get items from form
function getItemsFromForm() {
    const itemRows = document.querySelectorAll('.item-row');
    const items = [];
    
    itemRows.forEach(row => {
        const itemTypeSelect = row.querySelector('select[name*="item_type_id"]');
        const descriptionInput = row.querySelector('input[name*="item_description"]');
        const quantityInput = row.querySelector('input[name*="quantity"]');
        
        if (itemTypeSelect && descriptionInput && quantityInput && 
            itemTypeSelect.value && descriptionInput.value && quantityInput.value) {
            items.push({
                item_type_id: parseInt(itemTypeSelect.value),
                item_description: descriptionInput.value.trim(),
                quantity: parseInt(quantityInput.value)
            });
        }
    });
    
    return items;
}

// Validate request form
function validateRequestForm(formData, items) {
    if (!formData.customer_id) {
        showNotification('Please select a customer', 'error');
        return false;
    }
    
    if (!formData.employee_id) {
        showNotification('Please select an employee', 'error');
        return false;
    }
    
    if (!formData.location_id) {
        showNotification('Please select a location', 'error');
        return false;
    }
    
    if (!formData.purpose || formData.purpose.trim() === '') {
        showNotification('Please enter the purpose of borrowing', 'error');
        return false;
    }
    
    if (items.length === 0) {
        showNotification('Please add at least one item', 'error');
        return false;
    }
    
    return true;
}

// Request actions
async function viewRequest(id) {
    try {
        showLoading();
        const response = await apiRequest(`get?id=${id}`);
        showRequestDetailsModal(response.data);
    } catch (error) {
        console.error('Error loading request details:', error);
        showNotification(`Failed to load request details: ${error.message}`, 'error');
    } finally {
        hideLoading();
    }
}

async function editRequest(id) {
    try {
        showLoading();
        const response = await apiRequest(`get?id=${id}`);
        showEditRequestModal(response.data);
    } catch (error) {
        console.error('Error loading request for editing:', error);
        showNotification(`Failed to load request for editing: ${error.message}`, 'error');
    } finally {
        hideLoading();
    }
}

async function approveRequest(id) {
    if (!confirm('Are you sure you want to approve this request?')) {
        return;
    }

    try {
        showLoading();
        await apiRequest('approve', {
            method: 'POST',
            body: JSON.stringify({ id: id })
        });

        showNotification('Request approved successfully', 'success');
        loadRequestsData();
        loadStatistics();
    } catch (error) {
        console.error('Error approving request:', error);
        showNotification(`Failed to approve request: ${error.message}`, 'error');
    } finally {
        hideLoading();
    }
}

async function rejectRequest(id) {
    const reason = prompt('Please enter the reason for rejection:');
    if (!reason || reason.trim() === '') {
        showNotification('Rejection reason is required', 'error');
        return;
    }

    try {
        showLoading();
        await apiRequest('reject', {
            method: 'POST',
            body: JSON.stringify({ 
                id: id,
                rejection_reason: reason.trim()
            })
        });

        showNotification('Request rejected successfully', 'success');
        loadRequestsData();
        loadStatistics();
    } catch (error) {
        console.error('Error rejecting request:', error);
        showNotification(`Failed to reject request: ${error.message}`, 'error');
    } finally {
        hideLoading();
    }
}

async function processReturn(id) {
    try {
        showLoading();
        const response = await apiRequest(`get?id=${id}`);
        showReturnProcessModal(response.data);
    } catch (error) {
        console.error('Error loading request for return processing:', error);
        showNotification(`Failed to load request for return processing: ${error.message}`, 'error');
    } finally {
        hideLoading();
    }
}

async function deleteRequest(id) {
    if (!confirm('Are you sure you want to delete this request? This action cannot be undone.')) {
        return;
    }

    try {
        showLoading();
        
        // Send as form data since the API expects $_POST['id']
        const formData = new FormData();
        formData.append('id', id);
        
        await apiRequest('delete', {
            method: 'POST',
            body: formData,
            headers: {
                // Remove Content-Type header to let browser set it for FormData
            }
        });

        showNotification('Request deleted successfully', 'success');
        loadRequestsData();
        loadStatistics();
    } catch (error) {
        console.error('Error deleting request:', error);
        showNotification(`Failed to delete request: ${error.message}`, 'error');
    } finally {
        hideLoading();
    }
}

// Bulk actions
function handleBulkAction(action) {
    const selectedIds = getSelectedRequestIds();
    
    if (selectedIds.length === 0) {
        showNotification('Please select at least one request', 'error');
        return;
    }

    const actionText = action === 'delete' ? 'delete' : action;
    if (!confirm(`Are you sure you want to ${actionText} ${selectedIds.length} request(s)?`)) {
        return;
    }

    processBulkAction(action, selectedIds);
}

async function processBulkAction(action, requestIds) {
    try {
        showLoading();
        
        const response = await apiRequest('bulk_action', {
            method: 'POST',
            body: JSON.stringify({
                action: action,
                request_ids: requestIds
            })
        });

        showNotification(response.message, 'success');
        
        if (response.errors && response.errors.length > 0) {
            console.warn('Bulk action errors:', response.errors);
        }
        
        loadRequestsData();
        loadStatistics();
        
        // Uncheck select all
        const selectAllCheckbox = document.getElementById('select-all');
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = false;
        }

    } catch (error) {
        console.error('Error processing bulk action:', error);
    } finally {
        hideLoading();
    }
}

function getSelectedRequestIds() {
    const checkboxes = document.querySelectorAll('.request-checkbox:checked');
    return Array.from(checkboxes).map(checkbox => parseInt(checkbox.value));
}

// Export functionality
function exportRequests() {
    const selectedIds = getSelectedRequestIds();
    let url = `${API_BASE_URL}?action=export&format=csv`;
    
    if (selectedIds.length > 0) {
        url += `&request_ids=${selectedIds.join(',')}`;
    }
    
    // Apply current filters to export
    const params = new URLSearchParams(currentFilters);
    if (params.toString()) {
        url += `&${params.toString()}`;
    }
    
    window.open(url, '_blank');
}

// Item management functions
function addItemRow() {
    const container = document.getElementById('items-container');
    const rowIndex = container.children.length;
    
    const newRow = document.createElement('div');
    newRow.className = 'item-row grid grid-cols-12 gap-2 items-end mb-2';
    newRow.innerHTML = `
        <div class="col-span-4">
            <select name="item_type_id_${rowIndex}" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required onchange="updateItemDisplay(this)">
                <option value="">Select Item Type</option>
                ${itemTypesData.map(itemType => 
                    `<option value="${itemType.id}" data-unit="${itemType.unit}" data-estimated-value="${itemType.estimated_value || 0}">
                        ${itemType.name}
                    </option>`
                ).join('')}
            </select>
        </div>
        <div class="col-span-3">
            <input type="text" name="item_description_${rowIndex}" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Description" required>
        </div>
        <div class="col-span-2">
            <input type="number" name="quantity_${rowIndex}" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Qty" required>
        </div>
        <div class="col-span-2">
            <input type="text" class="unit-display w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100" readonly placeholder="Unit">
        </div>
        <div class="col-span-1">
            <button type="button" onclick="removeItemRow(this)" class="w-full px-3 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition-colors">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    
    container.appendChild(newRow);
}

function removeItemRow(button) {
    const row = button.closest('.item-row');
    row.remove();
}

function updateItemDisplay(select) {
    const row = select.closest('.item-row');
    const unitDisplay = row.querySelector('.unit-display');
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption.value) {
        const unit = selectedOption.dataset.unit || '';
        unitDisplay.value = unit;
    } else {
        unitDisplay.value = '';
    }
}

// UI Update Functions
function updateStatisticsCards(stats) {
    // Update statistics cards with the stats data
    updateStatCard('total-requests', stats.total_requests || 0);
    updateStatCard('pending-requests', stats.status_breakdown?.pending || 0);
    updateStatCard('active-requests', stats.status_breakdown?.active || 0);
    updateStatCard('overdue-requests', stats.overdue_requests || 0);
    updateStatCard('today-requests', stats.today_requests || 0);
    updateStatCard('week-requests', stats.week_requests || 0);
}

function updateStatCard(cardId, value) {
    const element = document.getElementById(cardId);
    if (element) {
        const valueElement = element.querySelector('.stat-value') || element;
        valueElement.textContent = value.toLocaleString();
    }
}

function updateRequestsTable(requests) {
    const tableBody = document.querySelector('#requests-table tbody');
    if (!tableBody) return;
    
    if (requests.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="10" class="px-6 py-8 text-center text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-4 block"></i>
                    No borrowing requests found
                </td>
            </tr>
        `;
        return;
    }
    
    tableBody.innerHTML = requests.map(request => `
        <tr class="hover:bg-gray-50">
            <td class="px-6 py-4 whitespace-nowrap">
                <input type="checkbox" class="request-checkbox rounded" value="${request.id}">
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">#${request.id}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">${request.customer_name || 'N/A'}</div>
                <div class="text-sm text-gray-500">${request.customer_email || ''}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">${request.employee_name || 'N/A'}</div>
                <div class="text-sm text-gray-500">${request.employee_id || ''}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">${request.location_name || 'N/A'}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">${formatDate(request.request_date)}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">${request.required_date ? formatDate(request.required_date) : 'N/A'}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${getStatusBadgeClass(request.status)}">
                    ${request.status}
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                ${request.total_items || 0} items
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <div class="flex space-x-1">
                    <button onclick="viewRequest(${request.id})" class="text-blue-600 hover:text-blue-900" title="View">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${request.status === 'pending' ? `
                        <button onclick="editRequest(${request.id})" class="text-green-600 hover:text-green-900" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="approveRequest(${request.id})" class="text-blue-600 hover:text-blue-900" title="Approve">
                            <i class="fas fa-check"></i>
                        </button>
                        <button onclick="rejectRequest(${request.id})" class="text-red-600 hover:text-red-900" title="Reject">
                            <i class="fas fa-times"></i>
                        </button>
                    ` : ''}
                    ${request.status === 'active' ? `
                        <button onclick="processReturn(${request.id})" class="text-purple-600 hover:text-purple-900" title="Process Return">
                            <i class="fas fa-undo"></i>
                        </button>
                    ` : ''}
                    ${['pending', 'rejected', 'returned'].includes(request.status) ? `
                        <button onclick="deleteRequest(${request.id})" class="text-red-600 hover:text-red-900" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    ` : ''}
                </div>
            </td>
        </tr>
    `).join('');
}

function updatePagination(pagination) {
    const paginationContainer = document.getElementById('pagination');
    if (!paginationContainer || !pagination) return;
    
    const { current_page, total_pages, has_prev, has_next } = pagination;
    
    let paginationHtml = '<div class="flex items-center justify-between">';
    
    // Previous button
    paginationHtml += `
        <button onclick="changePage(${current_page - 1})" 
                class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 ${!has_prev ? 'opacity-50 cursor-not-allowed' : ''}"
                ${!has_prev ? 'disabled' : ''}>
            Previous
        </button>
    `;
    
    // Page info
    paginationHtml += `
        <span class="text-sm text-gray-700">
            Page ${current_page} of ${total_pages}
        </span>
    `;
    
    // Next button
    paginationHtml += `
        <button onclick="changePage(${current_page + 1})" 
                class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 ${!has_next ? 'opacity-50 cursor-not-allowed' : ''}"
                ${!has_next ? 'disabled' : ''}>
            Next
        </button>
    `;
    
    paginationHtml += '</div>';
    
    paginationContainer.innerHTML = paginationHtml;
}

function changePage(page) {
    currentPage = page;
    loadRequestsData();
}

// Helper functions
function getStatusBadgeClass(status) {
    const statusClasses = {
        'pending': 'bg-yellow-100 text-yellow-800',
        'approved': 'bg-blue-100 text-blue-800',
        'active': 'bg-green-100 text-green-800',
        'returned': 'bg-gray-100 text-gray-800',
        'rejected': 'bg-red-100 text-red-800',
        'overdue': 'bg-red-100 text-red-800'
    };
    return statusClasses[status] || 'bg-gray-100 text-gray-800';
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function populateDropdowns() {
    // Populate customer dropdown
    const customerSelect = document.getElementById('customer_id');
    if (customerSelect) {
        customerSelect.innerHTML = '<option value="">Select Customer</option>' +
            customersData.map(customer => 
                `<option value="${customer.id}">${customer.name} - ${customer.email || customer.customer_type || ''}</option>`
            ).join('');
    }
    
    // Populate employee dropdown
    const employeeSelect = document.getElementById('employee_id');
    if (employeeSelect) {
        employeeSelect.innerHTML = '<option value="">Select Employee</option>' +
            employeesData.map(employee => 
                `<option value="${employee.id}">${employee.name} (${employee.employee_id}) - ${employee.department || ''}</option>`
            ).join('');
    }
    
    // Populate location dropdown
    const locationSelect = document.getElementById('location_id');
    if (locationSelect) {
        locationSelect.innerHTML = '<option value="">Select Location</option>' +
            locationsData.map(location => 
                `<option value="${location.id}">${location.name}</option>`
            ).join('');
    }
    
    // Populate filter dropdowns
    const customerFilterSelect = document.getElementById('customer-filter');
    if (customerFilterSelect) {
        customerFilterSelect.innerHTML = '<option value="">All Customers</option>' +
            customersData.map(customer => 
                `<option value="${customer.id}">${customer.name}</option>`
            ).join('');
    }
    
    const locationFilterSelect = document.getElementById('location-filter');
    if (locationFilterSelect) {
        locationFilterSelect.innerHTML = '<option value="">All Locations</option>' +
            locationsData.map(location => 
                `<option value="${location.id}">${location.name}</option>`
            ).join('');
    }
}

// Modal helper functions
function showRequestDetailsModal(requestData) {
    // Create a detailed view modal for the request
    const modalContent = `
        <div class="modal" id="view-request-modal">
            <div class="modal-overlay" onclick="closeModal('view-request-modal')"></div>
            <div class="modal-content max-w-4xl">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-800">Request Details - #${requestData.id}</h3>
                    <button onclick="closeModal('view-request-modal')" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <h4 class="font-medium text-gray-700">Customer Information</h4>
                            <div class="mt-2 p-4 bg-gray-50 rounded-lg">
                                <p><strong>Name:</strong> ${requestData.customer_name || 'N/A'}</p>
                                <p><strong>Email:</strong> ${requestData.customer_email || 'N/A'}</p>
                                <p><strong>Phone:</strong> ${requestData.customer_phone || 'N/A'}</p>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="font-medium text-gray-700">Request Details</h4>
                            <div class="mt-2 p-4 bg-gray-50 rounded-lg">
                                <p><strong>Employee:</strong> ${requestData.employee_name || 'N/A'}</p>
                                <p><strong>Location:</strong> ${requestData.location_name || 'N/A'}</p>
                                <p><strong>Request Date:</strong> ${formatDate(requestData.request_date)}</p>
                                <p><strong>Required Date:</strong> ${requestData.required_date ? formatDate(requestData.required_date) : 'N/A'}</p>
                                <p><strong>Status:</strong> <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${getStatusBadgeClass(requestData.status)}">${requestData.status}</span></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <div>
                            <h4 class="font-medium text-gray-700">Requested Items</h4>
                            <div class="mt-2 p-4 bg-gray-50 rounded-lg">
                                <div class="space-y-2">
                                    ${requestData.items && requestData.items.length > 0 ? 
                                        requestData.items.map(item => `
                                            <div class="flex justify-between">
                                                <span>${item.material_name}</span>
                                                <span>Qty: ${item.quantity_requested} ${item.unit}</span>
                                            </div>
                                        `).join('') :
                                        '<p>No items found</p>'
                                    }
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="font-medium text-gray-700">Purpose</h4>
                            <div class="mt-2 p-4 bg-gray-50 rounded-lg">
                                <p>${requestData.purpose || 'No purpose provided'}</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6 pt-6 border-t">
                    <button onclick="closeModal('view-request-modal')" class="btn btn-secondary">Close</button>
                    ${requestData.status === 'pending' ? `
                        <button onclick="approveRequest(${requestData.id}); closeModal('view-request-modal');" class="btn btn-success">
                            <i class="fas fa-check mr-2"></i>Approve
                        </button>
                        <button onclick="rejectRequest(${requestData.id}); closeModal('view-request-modal');" class="btn btn-danger">
                            <i class="fas fa-times mr-2"></i>Reject
                        </button>
                    ` : ''}
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('view-request-modal');
    if (existingModal) {
        existingModal.remove();
    }
    
    document.body.insertAdjacentHTML('beforeend', modalContent);
    openModal('view-request-modal');
}

function showEditRequestModal(requestData) {
    // For now, just show a placeholder - this would open the edit form with pre-filled data
    showNotification('Edit functionality will be implemented in a future update', 'info');
}

function showReturnProcessModal(requestData) {
    // For now, just show a placeholder - this would open the return processing form
    showNotification('Return processing functionality will be implemented in a future update', 'info');
}

// Modal functions
function openModal(modalId) {
    console.log('openModal called with:', modalId);
    const modal = document.getElementById(modalId);
    console.log('Modal element found:', modal);
    if (modal) {
        // Ensure modal is displayed as flex and not hidden
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
        modal.style.alignItems = 'center';
        modal.style.justifyContent = 'center';
        document.body.style.overflow = 'hidden';
        console.log('Modal opened successfully');
        
        // Re-initialize form listeners when modal opens
        if (modalId === 'new-request-modal') {
            setTimeout(() => {
                initializeModalFormListeners();
            }, 100);
        }
    } else {
        console.log('Modal not found');
    }
}

function initializeModalFormListeners() {
    console.log('Initializing modal form listeners...');
    const form = document.getElementById('new-request-form');
    console.log('Form found in modal:', form);
    
    if (form) {
        // Clear any existing listeners by cloning the form
        const newForm = form.cloneNode(true);
        form.parentNode.replaceChild(newForm, form);
        
        // Add fresh event listener for form submission
        newForm.addEventListener('submit', function(e) {
            console.log('Modal form submit event triggered');
            e.preventDefault();
            handleCreateRequest();
        });
        
        // Also add click listener to submit button as backup
        const submitButton = newForm.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.addEventListener('click', function(e) {
                console.log('Submit button clicked directly');
                if (e.target.type === 'submit') {
                    e.preventDefault();
                    handleCreateRequest();
                }
            });
            console.log('Submit button click listener added');
        }
        
        console.log('Fresh event listeners added to form');
    }
}

function handleFormSubmit(e) {
    console.log('Form submit handler called');
    e.preventDefault();
    handleCreateRequest();
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

function resetForm(formId) {
    const form = document.getElementById(formId);
    if (form) {
        form.reset();
        
        // Clear material rows except the first one
        const materialsContainer = document.getElementById('materials-container');
        if (materialsContainer) {
            const rows = materialsContainer.querySelectorAll('.material-row');
            for (let i = 1; i < rows.length; i++) {
                rows[i].remove();
            }
            
            // Reset first row
            if (rows[0]) {
                const select = rows[0].querySelector('select');
                const unitDisplay = rows[0].querySelector('.unit-display');
                const availabilityStatus = rows[0].querySelector('.availability-status');
                
                if (select) select.value = '';
                if (unitDisplay) unitDisplay.value = '';
                if (availabilityStatus) availabilityStatus.innerHTML = '';
            }
        }
    }
}

// Show/Hide loading
function showLoading() {
    // Implement loading spinner
    console.log('Loading...');
}

function hideLoading() {
    // Hide loading spinner
    console.log('Loading complete');
}

// Notification system
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 p-4 rounded-md shadow-lg transition-all duration-300 ${getNotificationClass(type)}`;
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${getNotificationIcon(type)} mr-2"></i>
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-lg font-semibold">&times;</button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

function getNotificationClass(type) {
    const classes = {
        'success': 'bg-green-500 text-white',
        'error': 'bg-red-500 text-white',
        'warning': 'bg-yellow-500 text-white',
        'info': 'bg-blue-500 text-white'
    };
    return classes[type] || classes.info;
}

function getNotificationIcon(type) {
    const icons = {
        'success': 'fa-check-circle',
        'error': 'fa-times-circle',
        'warning': 'fa-exclamation-triangle',
        'info': 'fa-info-circle'
    };
    return icons[type] || icons.info;
}

// Filter functions for backward compatibility
function applyFilters() {
    loadRequestsData();
}

function clearFilters() {
    currentFilters = {};
    currentPage = 1;
    
    // Reset form elements
    document.getElementById('search').value = '';
    document.getElementById('status-filter').value = '';
    document.getElementById('customer-filter').value = '';
    document.getElementById('location-filter').value = '';
    document.getElementById('date-from').value = '';
    document.getElementById('date-to').value = '';
    
    loadRequestsData();
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
