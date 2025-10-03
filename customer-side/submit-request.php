<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

// Check if customer is logged in
if (!isset($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login to submit a request']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$customer_id = $_SESSION['customer_id'];
$employee_id = 1; // Always assign employee_id 1 for customer-side requests
$required_date = $_POST['required_date'] ?? '';
$purpose = trim($_POST['purpose'] ?? '');
$location_id = $_POST['location_id'] ?? null;
$notes = trim($_POST['notes'] ?? '');
$items = $_POST['items'] ?? [];

// Validate required fields
if (empty($required_date) || empty($purpose)) {
    echo json_encode(['success' => false, 'message' => 'Required date and purpose are required']);
    exit();
}

// Validate date format and ensure it's in the future
$required_datetime = DateTime::createFromFormat('Y-m-d\TH:i', $required_date);
if (!$required_datetime || $required_datetime <= new DateTime()) {
    echo json_encode(['success' => false, 'message' => 'Required date must be in the future']);
    exit();
}

// Validate items (optional, item type is also optional)
$valid_items = [];
if (!empty($items)) {
    foreach ($items as $item) {
        if (!empty($item['quantity']) && $item['quantity'] > 0) {
            $valid_items[] = [
                'type_id' => !empty($item['type_id']) ? (int)$item['type_id'] : null,
                'description' => trim($item['description'] ?? ''),
                'quantity' => (int)$item['quantity']
            ];
        }
    }
}

try {
    $pdo->beginTransaction();
    
    // Insert borrowing request
    $stmt = $pdo->prepare("
        INSERT INTO Borrowing_Request 
        (customer_id, employee_id, location_id, required_date, purpose, status, notes, request_date)
        VALUES (?, ?, ?, ?, ?, 'pending', ?, NOW())
    ");
    $stmt->execute([
        $customer_id,
        $employee_id,
        $location_id ?: null,
        $required_datetime->format('Y-m-d H:i:s'),
        $purpose,
        $notes
    ]);
    $request_id = $pdo->lastInsertId();
    // Insert borrowing items if any
    if (!empty($valid_items)) {
        $item_stmt = $pdo->prepare("
            INSERT INTO Borrowing_Items 
            (borrowing_request_id, item_type_id, item_description, quantity_requested, estimated_value)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($valid_items as $item) {
            // Get estimated value for the item type if provided
            $estimated_value = 0;
            if (!empty($item['type_id'])) {
                $value_stmt = $pdo->prepare("SELECT estimated_value FROM Borrowing_Item_Types WHERE id = ?");
                $value_stmt->execute([$item['type_id']]);
                $item_type = $value_stmt->fetch();
                $estimated_value = $item_type ? $item_type['estimated_value'] * $item['quantity'] : 0;
            }
            $item_stmt->execute([
                $request_id,
                $item['type_id'],
                $item['description'],
                $item['quantity'],
                $estimated_value
            ]);
        }
    }
    
    $pdo->commit();
    
    // Get the created request details for response
    $stmt = $pdo->prepare("
        SELECT br.*, COUNT(bi.id) as item_count 
        FROM Borrowing_Request br 
        LEFT JOIN Borrowing_Items bi ON br.id = bi.borrowing_request_id 
        WHERE br.id = ? 
        GROUP BY br.id
    ");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'message' => 'Borrowing request submitted successfully! You will be notified once it\'s reviewed.',
        'request' => [
            'id' => $request['id'],
            'request_date' => $request['request_date'],
            'required_date' => $request['required_date'],
            'purpose' => $request['purpose'],
            'status' => $request['status'],
            'item_count' => $request['item_count']
        ]
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Submit request error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while submitting your request']);
}
?>
