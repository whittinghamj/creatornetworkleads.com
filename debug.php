<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

echo "<h2>Debug Information</h2>\n";

try {
    $db = getDB();
    echo "<p style='color:green'><strong>✓ Database connection successful</strong></p>\n";
    
    // Check creators table structure
    echo "<h3>Creators Table Structure</h3>\n";
    $result = $db->query("DESCRIBE creators")->fetchAll();
    echo "<pre>";
    print_r($result);
    echo "</pre>\n";
    
    // Check users table exists
    echo "<h3>Users Table Structure</h3>\n";
    $result = $db->query("DESCRIBE users")->fetchAll();
    echo "<pre>";
    print_r($result);
    echo "</pre>\n";
    
    // Test admin query
    echo "<h3>Testing Admin Dashboard Queries</h3>\n";
    
    echo "<p><strong>Query 1: Count users</strong></p>\n";
    $result = $db->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
    echo "<p>Result: " . $result . "</p>\n";
    
    echo "<p><strong>Query 2: Recent leads</strong></p>\n";
    $result = $db->query(
        "SELECT c.id, c.display_name, c.username, c.backstage_region, c.backstage_status,
                c.invitation_type, u.name AS customer_name
         FROM creators c
         LEFT JOIN users u ON u.id = c.assigned_customer
         ORDER BY c.id DESC LIMIT 1"
    )->fetchAll();
    echo "<pre>";
    print_r($result);
    echo "</pre>\n";
    
    echo "<p style='color:green'><strong>✓ All queries successful</strong></p>\n";
    
} catch (Exception $e) {
    echo "<p style='color:red'><strong>✗ Error: " . htmlspecialchars($e->getMessage()) . "</strong></p>\n";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
}
?>
