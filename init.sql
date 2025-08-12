CREATE TABLE Admin (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    password_hash VARCHAR(255),
    phone VARCHAR(20),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE Employee (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id VARCHAR(20) UNIQUE,
    name VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    password_hash VARCHAR(255),
    phone VARCHAR(20),
    role VARCHAR(50),
    department ENUM('warehouse', 'logistics', 'inventory', 'quality', 'maintenance', 'administration') DEFAULT 'warehouse',
    position VARCHAR(100),
    hire_date DATE,
    salary DECIMAL(10,2),
    shift ENUM('day', 'night', 'rotating') DEFAULT 'day',
    address TEXT,
    emergency_contact VARCHAR(255),
    status ENUM('active', 'inactive', 'on-leave', 'terminated') DEFAULT 'active',
    admin_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES Admin(id)
);

CREATE TABLE Customer (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    phone VARCHAR(20),
    address TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE Service_Categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),
    description TEXT
);

CREATE TABLE Service (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),
    description TEXT,
    category_id INT,
    price DECIMAL(10,2),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES Service_Categories(id)
);

CREATE TABLE Location (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    zip_code VARCHAR(20),
    country VARCHAR(100)
);

CREATE TABLE Material_Categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),
    description TEXT
);

CREATE TABLE Material (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),
    description TEXT,
    category_id INT,
    unit VARCHAR(50),
    price_per_unit DECIMAL(10,2),
    FOREIGN KEY (category_id) REFERENCES Material_Categories(id)
);

CREATE TABLE Inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    material_id INT,
    quantity INT,
    location_id INT,
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (material_id) REFERENCES Material(id),
    FOREIGN KEY (location_id) REFERENCES Location(id)
);

CREATE TABLE Borrowing_Request (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT,
    employee_id INT,
    location_id INT,
    request_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    required_date DATETIME,
    purpose TEXT,
    status ENUM('pending', 'approved', 'rejected', 'active', 'returned', 'overdue') DEFAULT 'pending',
    approved_by INT,
    approved_date DATETIME,
    notes TEXT,
    FOREIGN KEY (customer_id) REFERENCES Customer(id),
    FOREIGN KEY (employee_id) REFERENCES Employee(id),
    FOREIGN KEY (location_id) REFERENCES Location(id),
    FOREIGN KEY (approved_by) REFERENCES Admin(id)
);

CREATE TABLE Borrowing_Items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    borrowing_request_id INT,
    material_id INT,
    quantity_requested INT,
    quantity_approved INT,
    quantity_borrowed INT,
    unit_price DECIMAL(10,2),
    FOREIGN KEY (borrowing_request_id) REFERENCES Borrowing_Request(id),
    FOREIGN KEY (material_id) REFERENCES Material(id)
);

CREATE TABLE Borrowing_Transaction (
    id INT PRIMARY KEY AUTO_INCREMENT,
    borrowing_request_id INT,
    transaction_type ENUM('borrow', 'return', 'partial_return') DEFAULT 'borrow',
    transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_by INT,
    notes TEXT,
    FOREIGN KEY (borrowing_request_id) REFERENCES Borrowing_Request(id),
    FOREIGN KEY (processed_by) REFERENCES Employee(id)
);

CREATE TABLE Return_Items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    borrowing_transaction_id INT,
    material_id INT,
    quantity_returned INT,
    condition_status ENUM('good', 'damaged', 'lost') DEFAULT 'good',
    damage_notes TEXT,
    return_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (borrowing_transaction_id) REFERENCES Borrowing_Transaction(id),
    FOREIGN KEY (material_id) REFERENCES Material(id)
);

CREATE TABLE Damage_Report (
    id INT PRIMARY KEY AUTO_INCREMENT,
    return_item_id INT,
    damage_type VARCHAR(100),
    damage_description TEXT,
    repair_cost DECIMAL(10,2),
    replacement_cost DECIMAL(10,2),
    reported_by INT,
    report_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (return_item_id) REFERENCES Return_Items(id),
    FOREIGN KEY (reported_by) REFERENCES Employee(id)
);

-- Additional columns for Customer table to support the management interface
ALTER TABLE Customer ADD COLUMN customer_type ENUM('retail', 'wholesale', 'corporate', 'government') DEFAULT 'retail';
ALTER TABLE Customer ADD COLUMN location_type ENUM('local', 'regional', 'national', 'international') DEFAULT 'local';
ALTER TABLE Customer ADD COLUMN contact_person VARCHAR(100);
ALTER TABLE Customer ADD COLUMN alt_phone VARCHAR(20);
ALTER TABLE Customer ADD COLUMN credit_limit DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE Customer ADD COLUMN payment_terms ENUM('net-30', 'net-60', 'cod', 'advance') DEFAULT 'net-30';
ALTER TABLE Customer ADD COLUMN billing_address TEXT;
ALTER TABLE Customer ADD COLUMN shipping_address TEXT;
ALTER TABLE Customer ADD COLUMN status ENUM('active', 'inactive', 'vip', 'suspended') DEFAULT 'active';

-- Sample data for testing
INSERT INTO Customer (name, email, phone, address, customer_type, location_type, contact_person, status) VALUES
('TechCorp Solutions', 'contact@techcorp.com', '+1 555 123 4567', 'New York, NY', 'corporate', 'national', 'John Smith', 'vip'),
('Green Valley Retail', 'orders@greenvalley.com', '+1 555 234 5678', 'Los Angeles, CA', 'wholesale', 'regional', 'Maria Lopez', 'active'),
('Metro City Stores', 'procurement@metrocity.com', '+1 555 345 6789', 'Chicago, IL', 'retail', 'local', 'David Chen', 'active'),
('City Government Office', 'supplies@cityoffice.gov', '+1 555 456 7890', 'Miami, FL', 'government', 'local', 'Lisa Johnson', 'active');

ALTER TABLE Admin 
ADD COLUMN IF NOT EXISTS role ENUM('super-admin', 'admin') DEFAULT 'admin' AFTER phone,
ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive') DEFAULT 'active' AFTER role,
ADD COLUMN IF NOT EXISTS permissions JSON AFTER status,
ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL AFTER permissions,
ADD COLUMN IF NOT EXISTS remember_token VARCHAR(255) NULL AFTER last_login,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER remember_token;

-- Insert sample admin data
INSERT IGNORE INTO Admin (id, name, email, password_hash, phone, role, status, permissions, last_login) VALUES
(1, 'John Administrator', 'john.admin@warehouse.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1 234 567 8901', 'super-admin', 'active', '["user_management", "inventory_management", "reports", "system_settings", "customer_management", "employee_management"]', '2024-01-15 09:30:00'),
(2, 'Sarah Manager', 'sarah.manager@warehouse.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1 234 567 8902', 'admin', 'active', '["inventory_management", "customer_management", "employee_management"]', '2024-01-14 14:20:00'),
(3, 'Mike Supervisor', 'mike.supervisor@warehouse.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1 234 567 8903', 'admin', 'active', '["inventory_management", "reports"]', '2024-01-13 11:45:00'),
(4, 'Lisa Coordinator', 'lisa.coordinator@warehouse.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1 234 567 8904', 'super-admin', 'active', '["user_management", "inventory_management", "reports", "system_settings", "customer_management", "employee_management"]', '2024-01-12 16:10:00'),
(5, 'Tom Assistant', 'tom.assistant@warehouse.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1 234 567 8905', 'admin', 'inactive', '["inventory_management"]', NULL);

-- Note: The password for all sample accounts is "password" (without quotes)

-- Insert sample employee data
INSERT IGNORE INTO Employee (id, employee_id, name, email, password_hash, phone, department, position, hire_date, salary, shift, status, admin_id) VALUES
(1, 'EMP0001', 'Michael Johnson', 'michael.johnson@warehouse.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1 234 567 9001', 'warehouse', 'Warehouse Supervisor', '2023-01-15', 65000.00, 'day', 'active', 1),
(2, 'EMP0002', 'Sarah Williams', 'sarah.williams@warehouse.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1 234 567 9002', 'logistics', 'Logistics Coordinator', '2023-02-20', 58000.00, 'day', 'active', 1),
(3, 'EMP0003', 'David Brown', 'david.brown@warehouse.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1 234 567 9003', 'inventory', 'Inventory Clerk', '2023-03-10', 45000.00, 'day', 'active', 2),
(4, 'EMP0004', 'Jennifer Davis', 'jennifer.davis@warehouse.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1 234 567 9004', 'quality', 'Quality Control Technician', '2023-04-05', 52000.00, 'day', 'active', 2),
(5, 'EMP0005', 'Robert Miller', 'robert.miller@warehouse.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1 234 567 9005', 'maintenance', 'Maintenance Technician', '2023-05-12', 48000.00, 'rotating', 'active', 3),
(6, 'EMP0006', 'Lisa Wilson', 'lisa.wilson@warehouse.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1 234 567 9006', 'warehouse', 'Warehouse Operator', '2023-06-18', 42000.00, 'night', 'active', 3),
(7, 'EMP0007', 'James Garcia', 'james.garcia@warehouse.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1 234 567 9007', 'logistics', 'Shipping Clerk', '2023-07-22', 40000.00, 'day', 'active', 1),
(8, 'EMP0008', 'Maria Rodriguez', 'maria.rodriguez@warehouse.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1 234 567 9008', 'inventory', 'Stock Supervisor', '2023-08-30', 55000.00, 'day', 'active', 2),
(9, 'EMP0009', 'Thomas Anderson', 'thomas.anderson@warehouse.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1 234 567 9009', 'quality', 'Quality Inspector', '2023-09-15', 47000.00, 'day', 'on-leave', 3),
(10, 'EMP0010', 'Angela Martinez', 'angela.martinez@warehouse.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1 234 567 9010', 'administration', 'Administrative Assistant', '2023-10-01', 38000.00, 'day', 'active', 1);

-- Update some employees to have admin assignments
UPDATE Employee SET admin_id = 1 WHERE id IN (1, 2);
UPDATE Employee SET admin_id = 2 WHERE id IN (3, 4);
UPDATE Employee SET admin_id = 3 WHERE id IN (5, 6, 7);

-- Add additional columns to Material table using ALTER TABLE
ALTER TABLE Material 
ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive', 'discontinued') DEFAULT 'active' AFTER price_per_unit,
ADD COLUMN IF NOT EXISTS created_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER status,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Insert sample material categories
INSERT IGNORE INTO Material_Categories (id, name, description) VALUES
(1, 'Steel Products', 'Various steel materials and products'),
(2, 'Hardware', 'Screws, bolts, nuts, and fasteners'),
(3, 'Tools', 'Hand tools and power tools'),
(4, 'Safety Equipment', 'Personal protective equipment and safety gear'),
(5, 'Electrical', 'Electrical components and wiring'),
(6, 'Lumber', 'Wood materials and lumber products'),
(7, 'Pipes & Fittings', 'Plumbing pipes and fittings'),
(8, 'Paint & Chemicals', 'Paints, solvents, and chemical products');

-- Insert sample materials
INSERT IGNORE INTO Material (id, name, description, category_id, unit, price_per_unit, status) VALUES
(1, 'Steel Rod 12mm', 'High-grade steel rod, 12mm diameter, 6m length', 1, 'pieces', 25.50, 'active'),
(2, 'Hex Bolt M10x50', 'Stainless steel hex bolt, M10 thread, 50mm length', 2, 'pieces', 2.25, 'active'),
(3, 'Cordless Drill', 'Professional cordless drill with battery and charger', 3, 'pieces', 189.99, 'active'),
(4, 'Safety Helmet', 'Industrial safety helmet with adjustable strap', 4, 'pieces', 45.00, 'active'),
(5, 'Copper Wire 2.5mm', 'Electrical copper wire, 2.5mm² cross-section', 5, 'meters', 3.75, 'active'),
(6, 'Pine Lumber 2x4', 'Pine lumber board, 2"x4"x8ft', 6, 'pieces', 12.99, 'active'),
(7, 'PVC Pipe 4inch', 'PVC drainage pipe, 4 inch diameter, 10ft length', 7, 'pieces', 18.50, 'active'),
(8, 'Latex Paint White', 'Interior latex paint, white color, premium quality', 8, 'liters', 35.99, 'active'),
(9, 'Steel Plate 10mm', 'Carbon steel plate, 10mm thickness, 1m x 2m', 1, 'pieces', 145.00, 'active'),
(10, 'Socket Set', '42-piece socket set with ratchet handle', 3, 'pieces', 89.99, 'active'),
(11, 'Safety Goggles', 'Clear safety goggles with side protection', 4, 'pieces', 15.75, 'active'),
(12, 'Galvanized Pipe', 'Galvanized steel pipe, 2 inch diameter, 20ft', 7, 'pieces', 42.50, 'active'),
(13, 'Angle Grinder', 'Electric angle grinder, 4.5 inch disc', 3, 'pieces', 125.00, 'active'),
(14, 'Concrete Screws', 'Self-tapping concrete screws, 6mm x 40mm', 2, 'pieces', 0.85, 'active'),
(15, 'Extension Cord', 'Heavy duty extension cord, 50ft, 12 AWG', 5, 'pieces', 67.99, 'active');

-- Insert sample inventory data to show stock levels
INSERT IGNORE INTO Inventory (material_id, quantity, location_id, last_updated) VALUES
(1, 150, 1, NOW()),
(2, 2500, 1, NOW()),
(3, 25, 1, NOW()),
(4, 80, 1, NOW()),
(5, 500, 1, NOW()),
(6, 300, 1, NOW()),
(7, 75, 1, NOW()),
(8, 40, 1, NOW()),
(9, 20, 1, NOW()),
(10, 35, 1, NOW()),
(11, 120, 1, NOW()),
(12, 60, 1, NOW()),
(13, 15, 1, NOW()),
(14, 5000, 1, NOW()),
(15, 45, 1, NOW());

-- Insert a default location if it doesn't exist
INSERT IGNORE INTO Location (id, name, address, city, state, zip_code, country) VALUES
(1, 'Main Warehouse', '123 Industrial Blvd', 'Industrial City', 'State', '12345', 'USA');

-- Create Activity Log table for tracking admin actions
ALTER TABLE Material ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive') DEFAULT 'active';
ALTER TABLE Material ADD COLUMN IF NOT EXISTS created_at DATETIME DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE Material ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

CREATE TABLE IF NOT EXISTS Activity_Log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT,
    action ENUM('CREATE', 'UPDATE', 'DELETE', 'BULK_DELETE', 'IMPORT', 'EXPORT', 'MERGE', 'LOGIN', 'LOGOUT') NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES Admin(id) ON DELETE SET NULL
);
