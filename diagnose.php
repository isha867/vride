<?php
/**
 * Diagnostic Tool for VRide Database
 * Access at: https://your-domain.com/diagnose.php
 */

require_once 'db.php';

header('Content-Type: application/json');

$result = [
    'status' => 'error',
    'timestamp' => date('Y-m-d H:i:s'),
    'environment' => php_uname(),
    'php_version' => phpversion(),
];

// Check environment variables
$result['env_vars'] = [
    'DATABASE_URL_SET' => !empty(getenv('DATABASE_URL')),
    'DB_HOST_SET' => !empty(getenv('DB_HOST')),
    'DB_USER_SET' => !empty(getenv('DB_USER')),
];

// Check database connection
$pdo = getDB();
if (!$pdo) {
    $result['database'] = [
        'connected' => false,
        'error' => 'Failed to connect to database',
        'config' => [
            'host' => DB_HOST,
            'port' => DB_PORT,
            'database' => DB_NAME,
        ]
    ];
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$result['database'] = [
    'connected' => true,
    'config' => [
        'host' => DB_HOST,
        'port' => DB_PORT,
        'database' => DB_NAME,
    ]
];

// Check tables
$tables = ['users', 'vehicles', 'bookings'];
$result['tables'] = [];

foreach ($tables as $table) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
        $stmt->execute([$table]);
        $exists = (int)$stmt->fetchColumn() > 0;
        
        if ($exists) {
            $countStmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
            $count = (int)$countStmt->fetchColumn();
            $result['tables'][$table] = ['exists' => true, 'records' => $count];
        } else {
            $result['tables'][$table] = ['exists' => false, 'records' => 0];
        }
    } catch (Exception $e) {
        $result['tables'][$table] = ['exists' => false, 'error' => $e->getMessage()];
    }
}

// Check specific columns
$result['columns'] = [];
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'bookings' AND column_name = 'duration_type'");
    $result['columns']['bookings.duration_type'] = (int)$stmt->fetchColumn() > 0;
} catch (Exception $e) {
    $result['columns']['bookings.duration_type'] = false;
}

// Check admin accounts
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'");
    $adminCount = (int)$stmt->fetchColumn();
    $result['admin_accounts'] = $adminCount;
} catch (Exception $e) {
    $result['admin_accounts'] = 0;
    $result['admin_error'] = $e->getMessage();
}

// Determine overall status
$allTablesExist = array_reduce($result['tables'], function($carry, $item) {
    return $carry && $item['exists'];
}, true);

if ($result['database']['connected'] && $allTablesExist) {
    $result['status'] = 'success';
} else {
    $result['status'] = 'incomplete';
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
