-- Borrowing-only Warehouse System Database Schema
-- This schema focuses on borrowing requests without material inventory management

CREATE TABLE Admin (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    password_hash VARCHAR(255),
    phone VARCHAR(20),
    role ENUM('super-admin', 'admin') DEFAULT 'admin',
    status ENUM('active', 'inactive') DEFAULT 'active',
    permissions JSON,
    last_login TIMESTAMP NULL,
    remember_token VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
    password_hash VARCHAR(255),
    phone VARCHAR(20),
    address TEXT,
    customer_type ENUM('retail', 'wholesale', 'corporate', 'government') DEFAULT 'retail',
    location_type ENUM('local', 'regional', 'national', 'international') DEFAULT 'local',
    contact_person VARCHAR(100),
    alt_phone VARCHAR(20),
    credit_limit DECIMAL(10,2) DEFAULT 0.00,
    payment_terms ENUM('net-30', 'net-60', 'cod', 'advance') DEFAULT 'net-30',
    billing_address TEXT,
    shipping_address TEXT,
    status ENUM('active', 'inactive', 'vip', 'suspended') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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

-- Simplified Item table for borrowing (no complex inventory tracking)
CREATE TABLE Borrowing_Item_Types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),
    description TEXT,
    unit VARCHAR(50),
    estimated_value DECIMAL(10,2),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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

-- Items in each borrowing request
CREATE TABLE Borrowing_Items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    borrowing_request_id INT,
    item_type_id INT,
    item_description VARCHAR(255), -- Allow free-form description
    quantity_requested INT,
    quantity_approved INT,
    quantity_borrowed INT,
    estimated_value DECIMAL(10,2),
    FOREIGN KEY (borrowing_request_id) REFERENCES Borrowing_Request(id),
    FOREIGN KEY (item_type_id) REFERENCES Borrowing_Item_Types(id)
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
    borrowing_item_id INT,
    quantity_returned INT,
    condition_status ENUM('good', 'damaged', 'lost') DEFAULT 'good',
    damage_notes TEXT,
    return_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (borrowing_transaction_id) REFERENCES Borrowing_Transaction(id),
    FOREIGN KEY (borrowing_item_id) REFERENCES Borrowing_Items(id)
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

-- Activity Log table for tracking admin actions
CREATE TABLE Activity_Log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT,
    action ENUM('CREATE', 'UPDATE', 'DELETE', 'BULK_UPDATE', 'APPROVE', 'REJECT') NOT NULL,
    description TEXT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES Admin(id)
);

-- Sample data for testing
INSERT INTO Customer (name, email, phone, address, customer_type, location_type, contact_person, status) VALUES
('TechCorp Solutions', 'contact@techcorp.com', '+1 555 123 4567', 'New York, NY', 'corporate', 'national', 'John Smith', 'vip'),
('Green Valley Retail', 'orders@greenvalley.com', '+1 555 234 5678', 'Los Angeles, CA', 'wholesale', 'regional', 'Maria Lopez', 'active'),
('Metro City Stores', 'procurement@metrocity.com', '+1 555 345 6789', 'Chicago, IL', 'retail', 'local', 'David Chen', 'active'),
('City Government Office', 'supplies@cityoffice.gov', '+1 555 456 7890', 'Miami, FL', 'government', 'local', 'Lisa Johnson', 'active');

-- Insert sample admin data
INSERT INTO Admin (id, name, email, password_hash, phone, role, status, permissions, last_login) VALUES
(1, 'John Administrator', 'john.admin@warehouse.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1 234 567 8901', 'super-admin', 'active', '["user_management", "borrowing_management", "reports", "system_settings", "customer_management", "employee_management"]', '2024-01-15 09:30:00'),
(2, 'Sarah Manager', 'sarah.manager@warehouse.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1 234 567 8902', 'admin', 'active', '["borrowing_management", "customer_management", "employee_management"]', '2024-01-14 14:20:00'),
(3, 'Mike Supervisor', 'mike.supervisor@warehouse.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1 234 567 8903', 'admin', 'active', '["borrowing_management", "reports"]', '2024-01-13 11:45:00'),
(4, 'Lisa Coordinator', 'lisa.coordinator@warehouse.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+1 234 567 8904', 'super-admin', 'active', '["user_management", "borrowing_management", "reports", "system_settings", "customer_management", "employee_management"]', '2024-01-12 16:10:00');

-- Insert sample employees
INSERT INTO Employee (employee_id, name, email, phone, role, department, position, hire_date, salary, status, admin_id) VALUES
('EMP001', 'Robert Johnson', 'robert.johnson@warehouse.com', '+1 555 001 0001', 'warehouse_supervisor', 'warehouse', 'Warehouse Supervisor', '2023-03-15', 45000.00, 'active', 1),
('EMP002', 'Emily Davis', 'emily.davis@warehouse.com', '+1 555 001 0002', 'logistics_coordinator', 'logistics', 'Logistics Coordinator', '2023-05-20', 40000.00, 'active', 1),
('EMP003', 'Michael Brown', 'michael.brown@warehouse.com', '+1 555 001 0003', 'quality_inspector', 'quality', 'Quality Inspector', '2023-07-10', 38000.00, 'active', 2),
('EMP004', 'Jessica Wilson', 'jessica.wilson@warehouse.com', '+1 555 001 0004', 'warehouse_clerk', 'warehouse', 'Warehouse Clerk', '2023-09-01', 32000.00, 'active', 2);

-- Insert sample locations
INSERT INTO Location (name, address, city, state, zip_code, country) VALUES
('Main Warehouse', '1234 Industrial Blvd', 'Los Angeles', 'CA', '90210', 'USA'),
('Distribution Center East', '5678 Commerce Ave', 'New York', 'NY', '10001', 'USA'),
('Regional Hub South', '9012 Logistics Way', 'Miami', 'FL', '33101', 'USA'),
('Storage Facility North', '3456 Storage St', 'Chicago', 'IL', '60601', 'USA');

-- Insert sample borrowing item types
INSERT INTO Borrowing_Item_Types (name, description, unit, estimated_value) VALUES
('Bed Room', 'General construction and building tools', 'pieces', 50.00),
('Boxes', 'Office furniture and equipment', 'pieces', 200.00),
('Living Room', 'Personal protective equipment and safety gear', 'pieces', 25.00),
('Electronics', 'Electronic devices and equipment', 'pieces', 300.00),
('Office', 'Vehicle-related tools and equipment', 'pieces', 150.00),
('Kitchen', 'Cleaning and maintenance supplies', 'sets', 30.00);

-- Insert sample borrowing requests
INSERT INTO Borrowing_Request (customer_id, employee_id, location_id, required_date, purpose, status, approved_by, approved_date, notes) VALUES
(1, 1, 1, '2024-02-15 10:00:00', 'Office renovation project', 'active', 1, '2024-02-01 09:00:00', 'Approved for corporate client'),
(2, 2, 2, '2024-02-20 14:00:00', 'Store setup for new branch', 'pending', NULL, NULL, 'Waiting for approval'),
(3, 3, 1, '2024-02-10 08:00:00', 'Equipment for warehouse inspection', 'approved', 2, '2024-02-05 16:00:00', 'Standard inspection equipment'),
(4, 4, 3, '2024-02-25 12:00:00', 'Government office maintenance', 'pending', NULL, NULL, 'Government contract - priority');

-- Insert sample borrowing items
INSERT INTO Borrowing_Items (borrowing_request_id, item_type_id, item_description, quantity_requested, quantity_approved, quantity_borrowed, estimated_value) VALUES
(1, 1, 'Drill set with bits', 2, 2, 2, 100.00),
(1, 2, 'Folding tables', 5, 5, 5, 250.00),
(2, 3, 'Safety helmets and vests', 10, 0, 0, 250.00),
(3, 4, 'Digital multimeter', 1, 1, 1, 150.00),
(4, 5, 'Floor cleaning equipment', 3, 0, 0, 450.00);
