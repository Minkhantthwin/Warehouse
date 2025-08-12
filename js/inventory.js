// Inventory Management JavaScript functionality

let currentSortColumn = '';
let currentSortDirection = 'asc';
let inventoryData = [];

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeInventory();
});

function initializeInventory() {
    setupEventListeners();
    loadInventoryData();
    setupSelectAllCheckbox();
}

function setupEventListeners() {
    // Search functionality
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            handleSearch(e.target.value, filterInventory);
        });
    }

    // Filter dropdowns
    const categoryFilter = document.getElementById('category-filter');
    if (categoryFilter) {
        categoryFilter.addEventListener('change', function(e) {
            filterByCategory(e.target.value);
        });
    }

    const locationFilter = document.getElementById('location-filter');
    if (locationFilter) {
        locationFilter.addEventListener('change', function(e) {
            filterByLocation(e.target.value);
        });
    }

    const stockFilter = document.getElementById('stock-filter');
    if (stockFilter) {
        stockFilter.addEventListener('change', function(e) {
            filterByStockStatus(e.target.value);
        });
    }

    // Form submissions
    const addMaterialForm = document.getElementById('add-material-form');
    if (addMaterialForm) {
        addMaterialForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleAddMaterial();
        });
    }

    const stockAdjustmentForm = document.getElementById('stock-adjustment-form');
    if (stockAdjustmentForm) {
        stockAdjustmentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleStockAdjustment();
        });
    }
}

function setupSelectAllCheckbox() {
    const selectAllCheckbox = document.getElementById('select-all');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function(e) {
            const itemCheckboxes = document.querySelectorAll('.item-checkbox');
            itemCheckboxes.forEach(checkbox => {
                checkbox.checked = e.target.checked;
            });
        });
    }
}

function loadInventoryData() {
    // Simulate loading data from API
    console.log('Loading inventory data...');
    
    // Mock data for demonstration
    inventoryData = [
        {
            id: 1,
            name: 'Construction Hammer',
            sku: 'CT-HAM-001',
            category: 'Construction',
            quantity: 45,
            unit: 'pcs',
            location: 'Warehouse A',
            zone: 'Zone A-1',
            price: 25.00,
            status: 'In Stock',
            lastUpdated: '2025-01-20'
        },
        {
            id: 2,
            name: 'Safety Helmet',
            sku: 'SF-HLM-001',
            category: 'Safety',
            quantity: 8,
            unit: 'pcs',
            location: 'Warehouse B',
            zone: 'Zone B-2',
            price: 15.00,
            status: 'Low Stock',
            lastUpdated: '2025-01-18'
        },
        {
            id: 3,
            name: 'Office Paper Clips',
            sku: 'OF-PCP-001',
            category: 'Office',
            quantity: 0,
            unit: 'boxes',
            location: 'Main Office',
            zone: 'Storage Room',
            price: 2.50,
            status: 'Out of Stock',
            lastUpdated: '2025-01-15'
        }
    ];
    
    updateInventoryTable();
}

function updateInventoryTable() {
    // This would update the table with current data
    console.log('Updating inventory table with', inventoryData.length, 'items');
}

// Filtering functions
function filterInventory(searchTerm) {
    const table = document.getElementById('inventory-table-body');
    if (!table) return;

    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const matches = text.includes(searchTerm.toLowerCase());
        row.style.display = matches ? '' : 'none';
    });
}

function filterByCategory(category) {
    const table = document.getElementById('inventory-table-body');
    if (!table) return;

    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        if (!category) {
            row.style.display = '';
            return;
        }

        const categoryCell = row.querySelector('td:nth-child(3) span');
        if (categoryCell) {
            const rowCategory = categoryCell.textContent.toLowerCase();
            const matches = rowCategory === category.toLowerCase();
            row.style.display = matches ? '' : 'none';
        }
    });
}

function filterByLocation(location) {
    const table = document.getElementById('inventory-table-body');
    if (!table) return;

    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        if (!location) {
            row.style.display = '';
            return;
        }

        const locationCell = row.querySelector('td:nth-child(6)');
        if (locationCell) {
            const rowLocation = locationCell.textContent.toLowerCase();
            const matches = rowLocation.includes(location.toLowerCase().replace('-', ' '));
            row.style.display = matches ? '' : 'none';
        }
    });
}

function filterByStockStatus(status) {
    const table = document.getElementById('inventory-table-body');
    if (!table) return;

    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        if (!status) {
            row.style.display = '';
            return;
        }

        const statusCell = row.querySelector('td:nth-child(8) .badge');
        if (statusCell) {
            const rowStatus = statusCell.textContent.toLowerCase().replace(' ', '-');
            const matches = rowStatus === status;
            row.style.display = matches ? '' : 'none';
        }
    });
}

function clearFilters() {
    // Reset all filter inputs
    document.getElementById('search-input').value = '';
    document.getElementById('category-filter').value = '';
    document.getElementById('location-filter').value = '';
    document.getElementById('stock-filter').value = '';
    
    // Show all rows
    const table = document.getElementById('inventory-table-body');
    if (table) {
        const rows = table.querySelectorAll('tr');
        rows.forEach(row => {
            row.style.display = '';
        });
    }
    
    showNotification('Filters cleared', 'info');
}

// Sorting functionality
function sortTable(column) {
    const table = document.getElementById('inventory-table-body');
    if (!table) return;

    const rows = Array.from(table.querySelectorAll('tr'));
    
    // Determine sort direction
    if (currentSortColumn === column) {
        currentSortDirection = currentSortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        currentSortDirection = 'asc';
        currentSortColumn = column;
    }
    
    // Sort rows
    rows.sort((a, b) => {
        let aValue, bValue;
        
        switch(column) {
            case 'name':
                aValue = a.querySelector('td:nth-child(2) .text-sm.font-medium').textContent;
                bValue = b.querySelector('td:nth-child(2) .text-sm.font-medium').textContent;
                break;
            case 'quantity':
                aValue = parseInt(a.querySelector('td:nth-child(4)').textContent);
                bValue = parseInt(b.querySelector('td:nth-child(4)').textContent);
                break;
            case 'price':
                aValue = parseFloat(a.querySelector('td:nth-child(7)').textContent.replace('$', ''));
                bValue = parseFloat(b.querySelector('td:nth-child(7)').textContent.replace('$', ''));
                break;
            default:
                return 0;
        }
        
        if (typeof aValue === 'string') {
            aValue = aValue.toLowerCase();
            bValue = bValue.toLowerCase();
        }
        
        if (currentSortDirection === 'asc') {
            return aValue > bValue ? 1 : -1;
        } else {
            return aValue < bValue ? 1 : -1;
        }
    });
    
    // Re-append sorted rows
    rows.forEach(row => table.appendChild(row));
    
    // Update sort icons
    updateSortIcons(column);
}

function updateSortIcons(activeColumn) {
    // Reset all sort icons
    const sortIcons = document.querySelectorAll('th .fa-sort, th .fa-sort-up, th .fa-sort-down');
    sortIcons.forEach(icon => {
        icon.className = 'fas fa-sort ml-1';
    });
    
    // Update active column icon
    const activeIcon = document.querySelector(`th[onclick="sortTable('${activeColumn}')"] i`);
    if (activeIcon) {
        activeIcon.className = currentSortDirection === 'asc' 
            ? 'fas fa-sort-up ml-1' 
            : 'fas fa-sort-down ml-1';
    }
}

// Material actions
function viewMaterial(materialId) {
    console.log('Viewing material:', materialId);
    
    // Get material data (in real app, this would be an API call)
    const material = inventoryData.find(item => item.id === materialId) || {
        id: materialId,
        name: 'Construction Hammer',
        sku: 'CT-HAM-001',
        category: 'Construction',
        quantity: 45,
        unit: 'pcs',
        location: 'Warehouse A',
        zone: 'Zone A-1',
        price: 25.00,
        status: 'In Stock',
        lastUpdated: '2025-01-20',
        description: 'Heavy-duty construction hammer for general building work.'
    };
    
    const modalContent = `
        <div class="modal" id="view-material-modal">
            <div class="modal-overlay" onclick="closeModal('view-material-modal')"></div>
            <div class="modal-content max-w-2xl">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-800">Material Details</h3>
                    <button onclick="closeModal('view-material-modal')" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div class="text-center">
                            <div class="h-20 w-20 rounded-lg bg-blue-100 flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-hammer text-blue-600 text-3xl"></i>
                            </div>
                            <h4 class="text-xl font-semibold text-gray-900">${material.name}</h4>
                            <p class="text-gray-500">SKU: ${material.sku}</p>
                        </div>
                        
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Category:</span>
                                <span class="font-medium">${material.category}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Current Stock:</span>
                                <span class="font-medium">${material.quantity} ${material.unit}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Price per Unit:</span>
                                <span class="font-medium">$${material.price.toFixed(2)}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Status:</span>
                                <span class="badge badge-${material.status.toLowerCase().replace(' ', '-')}">${material.status}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <div>
                            <h5 class="font-medium text-gray-700 mb-2">Location Details</h5>
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <p><strong>Warehouse:</strong> ${material.location}</p>
                                <p><strong>Zone:</strong> ${material.zone}</p>
                            </div>
                        </div>
                        
                        <div>
                            <h5 class="font-medium text-gray-700 mb-2">Description</h5>
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <p>${material.description || 'No description available.'}</p>
                            </div>
                        </div>
                        
                        <div>
                            <h5 class="font-medium text-gray-700 mb-2">Last Updated</h5>
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <p>${formatDate(material.lastUpdated)}</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6 pt-6 border-t">
                    <button onclick="closeModal('view-material-modal')" class="btn btn-secondary">Close</button>
                    <button onclick="editMaterial(${materialId})" class="btn btn-primary">
                        <i class="fas fa-edit mr-2"></i>Edit
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalContent);
    
    setTimeout(() => {
        document.getElementById('view-material-modal').classList.remove('hidden');
    }, 10);
}

function editMaterial(materialId) {
    console.log('Editing material:', materialId);
    
    // Close view modal if open
    closeModal('view-material-modal');
    
    // Open add material modal with pre-filled data
    openModal('add-material-modal');
    
    // Change modal title
    const modalTitle = document.querySelector('#add-material-modal h3');
    if (modalTitle) {
        modalTitle.textContent = `Edit Material #${materialId}`;
    }
    
    // Change submit button text
    const submitBtn = document.querySelector('#add-material-form button[type="submit"]');
    if (submitBtn) {
        submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Update Material';
    }
    
    // Pre-fill form with existing data (in real app, would fetch from API)
    // This is a simplified example
}

function adjustStock(materialId) {
    console.log('Adjusting stock for material:', materialId);
    
    // Get material data
    const material = inventoryData.find(item => item.id === materialId) || {
        name: 'Construction Hammer',
        quantity: 45,
        unit: 'pcs'
    };
    
    // Update modal with material info
    document.getElementById('adjustment-material-name').textContent = material.name;
    document.getElementById('current-stock').textContent = material.quantity;
    
    // Reset form
    document.getElementById('stock-adjustment-form').reset();
    
    openModal('stock-adjustment-modal');
}

function deleteMaterial(materialId) {
    if (confirm(`Are you sure you want to delete material #${materialId}? This action cannot be undone.`)) {
        console.log('Deleting material:', materialId);
        
        // Simulate API call
        showNotification(`Material #${materialId} has been deleted`, 'success');
        
        // Remove row from table (in real app, would refetch data)
        const row = document.querySelector(`input[data-id="${materialId}"]`)?.closest('tr');
        if (row) {
            row.remove();
        }
    }
}

// Form handlers
function handleAddMaterial() {
    const form = document.getElementById('add-material-form');
    const formData = new FormData(form);
    
    // Validate form
    const errors = validateMaterialForm(formData);
    if (errors.length > 0) {
        showNotification(errors.join(', '), 'error');
        return;
    }
    
    // Simulate API call
    console.log('Adding new material...');
    
    setTimeout(() => {
        showNotification('Material added successfully!', 'success');
        closeModal('add-material-modal');
        
        // Reset form
        form.reset();
        
        // Refresh table
        loadInventoryData();
    }, 1000);
}

function handleStockAdjustment() {
    const materialName = document.getElementById('adjustment-material-name').textContent;
    const adjustmentType = document.getElementById('adjustment-type').value;
    const quantity = document.getElementById('adjustment-quantity').value;
    const reason = document.getElementById('adjustment-reason').value;
    
    if (!quantity) {
        showNotification('Please enter a quantity', 'error');
        return;
    }
    
    console.log('Processing stock adjustment:', {
        materialName,
        adjustmentType,
        quantity,
        reason
    });
    
    // Simulate API call
    setTimeout(() => {
        showNotification(`Stock ${adjustmentType} completed for ${materialName}`, 'success');
        closeModal('stock-adjustment-modal');
        
        // Update the table (in real app, would refetch data)
        loadInventoryData();
    }, 1000);
}

function updateAdjustmentType() {
    const adjustmentType = document.getElementById('adjustment-type').value;
    const quantityInput = document.getElementById('adjustment-quantity');
    
    // Update placeholder based on adjustment type
    switch(adjustmentType) {
        case 'add':
            quantityInput.placeholder = 'Quantity to add';
            break;
        case 'remove':
            quantityInput.placeholder = 'Quantity to remove';
            break;
        case 'set':
            quantityInput.placeholder = 'New stock level';
            break;
    }
}

function validateMaterialForm(formData) {
    const errors = [];
    
    // Add validation logic here
    console.log('Validating material form:', formData);
    
    return errors;
}

// Bulk actions
function handleBulkAction(action) {
    if (!action) return;
    
    const checkboxes = document.querySelectorAll('.item-checkbox:checked');
    const selectedIds = Array.from(checkboxes).map(cb => cb.dataset.id);
    
    if (selectedIds.length === 0) {
        showNotification('Please select at least one item', 'warning');
        return;
    }
    
    switch(action) {
        case 'delete':
            if (confirm(`Delete ${selectedIds.length} selected materials?`)) {
                console.log('Bulk deleting materials:', selectedIds);
                showNotification(`${selectedIds.length} materials deleted`, 'success');
                
                // Remove rows from table
                selectedIds.forEach(id => {
                    const row = document.querySelector(`input[data-id="${id}"]`)?.closest('tr');
                    if (row) row.remove();
                });
            }
            break;
        case 'update-location':
            showBulkLocationUpdateModal(selectedIds);
            break;
        case 'adjust-stock':
            showBulkStockAdjustmentModal(selectedIds);
            break;
    }
    
    // Reset select dropdown
    event.target.value = '';
}

function showBulkLocationUpdateModal(selectedIds) {
    const modalContent = `
        <div class="modal" id="bulk-location-modal">
            <div class="modal-overlay" onclick="closeModal('bulk-location-modal')"></div>
            <div class="modal-content">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-800">Update Location</h3>
                    <button onclick="closeModal('bulk-location-modal')" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <p class="text-gray-600 mb-4">Update location for ${selectedIds.length} selected materials</p>
                
                <form id="bulk-location-form">
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">New Location</label>
                            <select class="form-select" required>
                                <option value="">Select Location</option>
                                <option value="warehouse-a">Warehouse A</option>
                                <option value="warehouse-b">Warehouse B</option>
                                <option value="main-office">Main Office</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Zone/Shelf</label>
                            <input type="text" class="form-input" placeholder="e.g., A-1, B-2">
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('bulk-location-modal')" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Location</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalContent);
    
    setTimeout(() => {
        document.getElementById('bulk-location-modal').classList.remove('hidden');
    }, 10);
    
    document.getElementById('bulk-location-form').addEventListener('submit', function(e) {
        e.preventDefault();
        showNotification(`Location updated for ${selectedIds.length} materials`, 'success');
        closeModal('bulk-location-modal');
    });
}

function exportInventory() {
    console.log('Exporting inventory data...');
    
    // In a real application, this would generate and download a CSV/Excel file
    showNotification('Inventory data exported successfully!', 'success');
}

// Export functions for global access
window.inventoryFunctions = {
    viewMaterial,
    editMaterial,
    adjustStock,
    deleteMaterial,
    clearFilters,
    sortTable,
    handleBulkAction,
    exportInventory,
    updateAdjustmentType
};
