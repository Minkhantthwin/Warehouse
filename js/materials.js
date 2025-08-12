// Materials Management JavaScript

// Determine the correct API path based on current location
let API_BASE;
if (window.location.pathname.includes('/inventory/')) {
    API_BASE = '../api/materials.php';
} else {
    API_BASE = 'api/materials.php';
}

class MaterialsManager {
    constructor() {
        this.currentPage = 1;
        this.itemsPerPage = 10;
        this.materials = [];
        this.categories = [];
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadMaterials();
        this.loadCategories();
    }

    bindEvents() {
        // Form submissions
        const addForm = document.getElementById('add-material-form');
        if (addForm) {
            addForm.addEventListener('submit', (e) => this.handleAddMaterial(e));
        }

        const editForm = document.getElementById('edit-material-form');
        if (editForm) {
            editForm.addEventListener('submit', (e) => this.handleEditMaterial(e));
        }

        // Search and filter
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.addEventListener('input', this.debounce(() => this.filterMaterials(), 300));
        }

        // File upload
        const fileInput = document.getElementById('import-file');
        if (fileInput) {
            fileInput.addEventListener('change', (e) => this.handleFileSelect(e));
        }

        // Select all checkbox
        const selectAllCheckbox = document.getElementById('select-all');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', (e) => this.handleSelectAll(e));
        }
    }

    // API Methods
    async loadMaterials(filters = {}) {
        try {
            const params = new URLSearchParams({
                action: 'list',
                page: this.currentPage,
                limit: this.itemsPerPage,
                ...filters
            });

            const response = await fetch(`${API_BASE}?${params}`);
            const result = await response.json();

            if (result.success) {
                this.materials = result.data;
                this.updateTable();
                this.updatePagination(result.pagination);
            } else {
                this.showNotification(result.error || 'Failed to load materials', 'error');
            }
        } catch (error) {
            console.error('Error loading materials:', error);
            this.showNotification('Network error occurred while loading materials', 'error');
        }
    }

    async loadCategories() {
        try {
            const response = await fetch(`${API_BASE}?action=categories`);
            const result = await response.json();

            if (result.success) {
                this.categories = result.data;
                this.updateCategoryDropdowns();
            }
        } catch (error) {
            console.error('Error loading categories:', error);
        }
    }

    async createMaterial(materialData) {
        try {
            const response = await fetch(`${API_BASE}?action=create`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(materialData)
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('Material created successfully!', 'success');
                this.loadMaterials(); // Refresh the list
                return true;
            } else {
                this.showNotification(result.error || 'Failed to create material', 'error');
                return false;
            }
        } catch (error) {
            console.error('Error creating material:', error);
            this.showNotification('Network error occurred', 'error');
            return false;
        }
    }

    async updateMaterial(id, materialData) {
        try {
            const response = await fetch(`${API_BASE}?action=update`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id, ...materialData })
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('Material updated successfully!', 'success');
                this.loadMaterials(); // Refresh the list
                return true;
            } else {
                this.showNotification(result.error || 'Failed to update material', 'error');
                return false;
            }
        } catch (error) {
            console.error('Error updating material:', error);
            this.showNotification('Network error occurred', 'error');
            return false;
        }
    }

    async deleteMaterial(id) {
        try {
            const response = await fetch(`${API_BASE}?action=delete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id })
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('Material deleted successfully!', 'success');
                this.loadMaterials(); // Refresh the list
                return true;
            } else {
                this.showNotification(result.error || 'Failed to delete material', 'error');
                return false;
            }
        } catch (error) {
            console.error('Error deleting material:', error);
            this.showNotification('Network error occurred', 'error');
            return false;
        }
    }

    async getMaterial(id) {
        try {
            const response = await fetch(`${API_BASE}?action=get&id=${id}`);
            const result = await response.json();

            if (result.success) {
                return result.data;
            } else {
                this.showNotification(result.error || 'Failed to load material', 'error');
                return null;
            }
        } catch (error) {
            console.error('Error loading material:', error);
            this.showNotification('Network error occurred', 'error');
            return null;
        }
    }

    // Event Handlers
    async handleAddMaterial(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const materialData = Object.fromEntries(formData.entries());

        // Validation
        if (!materialData.name || !materialData.unit || !materialData.price_per_unit) {
            this.showNotification('Please fill in all required fields', 'warning');
            return;
        }

        if (parseFloat(materialData.price_per_unit) < 0) {
            this.showNotification('Price must be a positive number', 'warning');
            return;
        }

        const success = await this.createMaterial(materialData);
        if (success) {
            closeModal('add-material-modal');
            e.target.reset();
        }
    }

    async handleEditMaterial(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const materialData = Object.fromEntries(formData.entries());
        const id = materialData.id;
        delete materialData.id;

        // Validation
        if (!materialData.name || !materialData.unit || !materialData.price_per_unit) {
            this.showNotification('Please fill in all required fields', 'warning');
            return;
        }

        if (parseFloat(materialData.price_per_unit) < 0) {
            this.showNotification('Price must be a positive number', 'warning');
            return;
        }

        const success = await this.updateMaterial(id, materialData);
        if (success) {
            closeModal('edit-material-modal');
        }
    }

    handleFileSelect(e) {
        const file = e.target.files[0];
        if (file) {
            if (!file.name.endsWith('.csv')) {
                this.showNotification('Please select a CSV file', 'warning');
                e.target.value = '';
                return;
            }
            
            // Update UI to show file selected
            const label = document.querySelector('label[for="import-file"] span');
            if (label) {
                label.textContent = `Selected: ${file.name}`;
            }
        }
    }

    handleSelectAll(e) {
        const checkboxes = document.querySelectorAll('.material-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = e.target.checked;
        });
    }

    // UI Update Methods
    updateTable() {
        const tbody = document.getElementById('materials-table-body');
        if (!tbody) return;

        if (this.materials.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                        <div class="flex flex-col items-center">
                            <i class="fas fa-cube text-4xl text-gray-300 mb-2"></i>
                            <p class="text-lg font-medium">No materials found</p>
                            <p class="text-sm">Add your first material to get started.</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = this.materials.map(material => this.renderMaterialRow(material)).join('');
    }

    renderMaterialRow(material) {
        const stockLevel = Math.random() * 100; // Mock stock level
        let statusClass, statusText, statusIcon;
        
        if (stockLevel > 50) {
            statusClass = 'badge-success';
            statusText = 'In Stock';
            statusIcon = 'fa-check-circle';
        } else if (stockLevel > 20) {
            statusClass = 'badge-warning';
            statusText = 'Low Stock';
            statusIcon = 'fa-exclamation-triangle';
        } else {
            statusClass = 'badge-danger';
            statusText = 'Out of Stock';
            statusIcon = 'fa-times-circle';
        }

        return `
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <input type="checkbox" class="rounded border-gray-300 material-checkbox" data-id="${material.id}">
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <div class="h-10 w-10 rounded-lg bg-gradient-to-r from-blue-400 to-blue-600 flex items-center justify-center text-white font-semibold">
                            <i class="fas fa-cube"></i>
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-medium text-gray-900">${this.escapeHtml(material.name)}</div>
                            <div class="text-sm text-gray-500 max-w-xs truncate" title="${this.escapeHtml(material.description || 'No description')}">
                                ${this.escapeHtml(material.description || 'No description')}
                            </div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    ${material.category_name ? 
                        `<span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                            ${this.escapeHtml(material.category_name)}
                        </span>` :
                        `<span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                            Uncategorized
                        </span>`
                    }
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${this.escapeHtml(material.unit || 'N/A')}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900">
                        $${parseFloat(material.price_per_unit || 0).toFixed(2)}
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="badge ${statusClass}">
                        <i class="fas ${statusIcon} mr-1"></i>
                        ${statusText}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <div class="flex space-x-2">
                        <button onclick="materialsManager.viewMaterial(${material.id})" class="text-blue-600 hover:text-blue-900" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="materialsManager.editMaterial(${material.id})" class="text-green-600 hover:text-green-900" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="materialsManager.viewStock(${material.id})" class="text-purple-600 hover:text-purple-900" title="Stock Levels">
                            <i class="fas fa-warehouse"></i>
                        </button>
                       
                    </div>
                </td>
            </tr>
        `;
    }

    updateCategoryDropdowns() {
        const addCategorySelect = document.querySelector('#add-material-form select[name="category_id"]');
        const editCategorySelect = document.querySelector('#edit-material-form select[name="category_id"]');
        
        const optionsHtml = this.categories.map(category => 
            `<option value="${category.id}">${this.escapeHtml(category.name)}</option>`
        ).join('');

        if (addCategorySelect) {
            const currentOptions = addCategorySelect.innerHTML;
            addCategorySelect.innerHTML = currentOptions.split('</option>')[0] + '</option>' + optionsHtml;
        }

        if (editCategorySelect) {
            const currentOptions = editCategorySelect.innerHTML;
            editCategorySelect.innerHTML = currentOptions.split('</option>')[0] + '</option>' + optionsHtml;
        }
    }

    updatePagination(pagination) {
        // Implementation for pagination updates
        // This would update the pagination controls based on the pagination object
        console.log('Pagination info:', pagination);
    }

    // Public Methods for UI Actions
    async viewMaterial(id) {
        const material = await this.getMaterial(id);
        if (material) {
            // Create and show material details modal
            this.showMaterialDetails(material);
        }
    }

    async editMaterial(id) {
        const material = await this.getMaterial(id);
        if (material) {
            this.populateEditForm(material);
            openModal('edit-material-modal');
        }
    }

    viewStock(id) {
        // Implementation for viewing stock levels
        this.showNotification('Stock levels feature will be implemented with inventory system', 'info');
    }

    async deleteMaterial(id) {
        if (confirm('Are you sure you want to delete this material? This action cannot be undone.')) {
            await this.deleteMaterial(id);
        }
    }

    async handleBulkAction(action) {
        const selectedCheckboxes = document.querySelectorAll('.material-checkbox:checked');
        const selectedIds = Array.from(selectedCheckboxes).map(cb => cb.dataset.id);

        if (selectedIds.length === 0) {
            this.showNotification('Please select at least one material', 'warning');
            return;
        }

        switch (action) {
            case 'delete':
                if (confirm(`Are you sure you want to delete ${selectedIds.length} materials?`)) {
                    // Implementation for bulk delete
                    this.showNotification('Bulk delete feature coming soon!', 'info');
                }
                break;
            case 'export':
                this.exportSelected(selectedIds);
                break;
            case 'update_category':
                this.showBulkCategoryUpdate(selectedIds);
                break;
        }
    }

    async exportMaterials(selectedIds = null) {
        try {
            const params = new URLSearchParams({
                action: 'export',
                format: 'csv'
            });

            if (selectedIds) {
                params.append('ids', selectedIds.join(','));
            }

            const response = await fetch(`${API_BASE}?${params}`);
            
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `materials_${new Date().toISOString().split('T')[0]}.csv`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                
                this.showNotification('Materials exported successfully!', 'success');
            } else {
                throw new Error('Export failed');
            }
        } catch (error) {
            console.error('Export error:', error);
            this.showNotification('Failed to export materials', 'error');
        }
    }

    async importMaterials() {
        const fileInput = document.getElementById('import-file');
        const file = fileInput.files[0];

        if (!file) {
            this.showNotification('Please select a file first', 'warning');
            return;
        }

        const formData = new FormData();
        formData.append('file', file);

        try {
            const response = await fetch(`${API_BASE}?action=import`, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(`Successfully imported ${result.imported} materials!`, 'success');
                this.loadMaterials(); // Refresh the list
                closeModal('bulk-import-modal');
                fileInput.value = '';
            } else {
                this.showNotification(result.error || 'Import failed', 'error');
            }
        } catch (error) {
            console.error('Import error:', error);
            this.showNotification('Network error occurred during import', 'error');
        }
    }

    downloadTemplate() {
        // Create and download CSV template
        const csvContent = 'name,description,unit,price_per_unit,category_id\n' +
                          'Sample Material,Material description,pieces,10.99,1\n' +
                          'Another Material,Another description,kg,25.50,2';

        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'materials_template.csv';
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);

        this.showNotification('Template downloaded successfully!', 'success');
    }

    // Helper Methods
    populateEditForm(material) {
        document.getElementById('edit-material-id').value = material.id;
        document.getElementById('edit-material-name').value = material.name || '';
        document.getElementById('edit-material-category').value = material.category_id || '';
        document.getElementById('edit-material-unit').value = material.unit || '';
        document.getElementById('edit-material-price').value = material.price_per_unit || '';
        document.getElementById('edit-material-description').value = material.description || '';
    }

    showMaterialDetails(material) {
        // Create a modal to show material details
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-overlay" onclick="this.parentElement.remove()"></div>
            <div class="modal-content max-w-2xl">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-800">Material Details</h3>
                    <button onclick="this.closest('.modal').remove()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Name</label>
                        <p class="text-gray-900">${this.escapeHtml(material.name)}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Category</label>
                        <p class="text-gray-900">${this.escapeHtml(material.category_name || 'Uncategorized')}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Unit</label>
                        <p class="text-gray-900">${this.escapeHtml(material.unit)}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Price per Unit</label>
                        <p class="text-gray-900">$${parseFloat(material.price_per_unit).toFixed(2)}</p>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Description</label>
                        <p class="text-gray-900">${this.escapeHtml(material.description || 'No description')}</p>
                    </div>
                </div>
                
                <div class="flex justify-end mt-6">
                    <button onclick="this.closest('.modal').remove()" class="btn btn-secondary">Close</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
    }

    filterMaterials() {
        // Get filter values
        const searchTerm = document.querySelector('input[name="search"]')?.value || '';
        const category = document.querySelector('select[name="category"]')?.value || '';
        const priceRange = document.querySelector('select[name="price_range"]')?.value || '';

        // Apply filters
        this.loadMaterials({
            search: searchTerm,
            category: category,
            price_range: priceRange
        });
    }

    showNotification(message, type = 'info') {
        const colors = {
            'success': 'bg-green-500 text-white',
            'error': 'bg-red-500 text-white',
            'warning': 'bg-yellow-500 text-white',
            'info': 'bg-blue-500 text-white'
        };
        
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 p-4 rounded-md shadow-lg z-50 ${colors[type]}`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    debounce(func, wait) {
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
}

// Initialize the materials manager when the page loads
let materialsManager;
document.addEventListener('DOMContentLoaded', function() {
    materialsManager = new MaterialsManager();
});

// Export functions for global access
window.materialsManager = materialsManager;
window.viewMaterial = (id) => materialsManager?.viewMaterial(id);
window.editMaterial = (id) => materialsManager?.editMaterial(id);
window.viewStock = (id) => materialsManager?.viewStock(id);

window.handleBulkAction = (action) => materialsManager?.handleBulkAction(action);
window.exportMaterials = () => materialsManager?.exportMaterials();
window.importMaterials = () => materialsManager?.importMaterials();
window.downloadTemplate = () => materialsManager?.downloadTemplate();
