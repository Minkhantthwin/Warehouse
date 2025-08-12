<?php
require_once 'includes/config.php';

try {
    echo "Database connection: ";
    $stmt = $pdo->query('SELECT COUNT(*) FROM Material_Categories');
    echo "Success - Categories count: " . $stmt->fetchColumn() . PHP_EOL;
    
    // Test if Activity_Log table exists
    $stmt = $pdo->query('SHOW TABLES LIKE "Activity_Log"');
    if ($stmt->rowCount() > 0) {
        echo "Activity_Log table exists" . PHP_EOL;
    } else {
        echo "Activity_Log table does not exist - creating..." . PHP_EOL;
        $pdo->exec('CREATE TABLE IF NOT EXISTS Activity_Log (
            id INT PRIMARY KEY AUTO_INCREMENT,
            admin_id INT,
            action ENUM("CREATE", "UPDATE", "DELETE", "BULK_DELETE", "IMPORT", "EXPORT", "MERGE", "LOGIN", "LOGOUT") NOT NULL,
            description TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_id) REFERENCES Admin(id) ON DELETE SET NULL
        )');
        echo "Activity_Log table created successfully" . PHP_EOL;
    }
    
    // Test categories API basic functionality
    echo "Testing categories API..." . PHP_EOL;
    
    // Simulate a basic categories list request
    $_GET['action'] = 'stats';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    
    ob_start();
    include 'api/categories.php';
    $output = ob_get_clean();
    
    $result = json_decode($output, true);
    if ($result && $result['success']) {
        echo "Categories API stats working: " . json_encode($result['data']) . PHP_EOL;
    } else {
        echo "Categories API error: " . $output . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
?>
