<?php
require_once 'includes/config.php';

echo "<h2>Database Test for Borrowing Requests</h2>";

try {
    // Check if tables exist
    $tables = ['Customer', 'Employee', 'Location', 'Borrowing_Item_Types', 'Borrowing_Request', 'Borrowing_Items'];
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $count = $stmt->fetch()['count'];
        echo "<p>$table: $count records</p>";
    }
    
    echo "<h3>Customers:</h3>";
    $stmt = $pdo->query("SELECT id, name, status FROM Customer LIMIT 5");
    while ($row = $stmt->fetch()) {
        echo "<p>ID: {$row['id']}, Name: {$row['name']}, Status: {$row['status']}</p>";
    }
    
    echo "<h3>Employees:</h3>";
    $stmt = $pdo->query("SELECT id, name, status FROM Employee LIMIT 5");
    while ($row = $stmt->fetch()) {
        echo "<p>ID: {$row['id']}, Name: {$row['name']}, Status: {$row['status']}</p>";
    }
    
    echo "<h3>Locations:</h3>";
    $stmt = $pdo->query("SELECT id, name, city FROM Location LIMIT 5");
    while ($row = $stmt->fetch()) {
        echo "<p>ID: {$row['id']}, Name: {$row['name']}, City: {$row['city']}</p>";
    }
    
    echo "<h3>Item Types:</h3>";
    $stmt = $pdo->query("SELECT id, name FROM Borrowing_Item_Types LIMIT 5");
    while ($row = $stmt->fetch()) {
        echo "<p>ID: {$row['id']}, Name: {$row['name']}</p>";
    }
    
    echo "<h3>Borrowing Requests:</h3>";
    $stmt = $pdo->query("SELECT br.id, br.status, c.name as customer_name, e.name as employee_name 
                        FROM Borrowing_Request br 
                        LEFT JOIN Customer c ON br.customer_id = c.id 
                        LEFT JOIN Employee e ON br.employee_id = e.id 
                        LIMIT 5");
    while ($row = $stmt->fetch()) {
        echo "<p>ID: {$row['id']}, Status: {$row['status']}, Customer: {$row['customer_name']}, Employee: {$row['employee_name']}</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
