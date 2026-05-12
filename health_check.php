<?php
require_once 'db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "VRide Health Check\n";
echo "==================\n\n";

// Show current configuration
echo "Configuration:\n";
echo "- Host: " . DB_HOST . "\n";
echo "- Port: " . DB_PORT . "\n";
echo "- Database: " . DB_NAME . "\n";
echo "- User: " . DB_USER . "\n";
echo "- ENV Variables Present: " . (getenv('DATABASE_URL') ? 'Yes (DATABASE_URL)' : 'No') . "\n\n";

$pdo = getDB();
if (!$pdo) {
    echo "DB: FAIL (connection unavailable)\n";
    echo "\nTroubleshooting:\n";
    echo "1. Verify DATABASE_URL is set on Railway\n";
    echo "2. Check MySQL service is running\n";
    echo "3. Verify credentials and network connectivity\n";
    exit(1);
}

echo "DB: OK ✓\n\n";

$tables = ['users', 'vehicles', 'bookings'];
echo "Tables:\n";
foreach ($tables as $table) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$table]);
    $exists = (int)$stmt->fetchColumn() > 0;
    
    if ($exists) {
        $countStmt = $pdo->query("SELECT COUNT(*) FROM " . $table);
        $count = (int)$countStmt->fetchColumn();
        echo sprintf("  %-10s: OK ✓ (%d records)\n", $table, $count);
    } else {
        echo sprintf("  %-10s: MISSING ✗\n", $table);
    }
}

echo "\nColumns:\n";
$durationColStmt = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'bookings' AND column_name = 'duration_type'");
$durationColExists = (int)$durationColStmt->fetchColumn() > 0;
echo "  bookings.duration_type: " . ($durationColExists ? 'OK ✓' : 'MISSING ✗') . "\n";

echo "\nAdmin Accounts:\n";
$adminStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'");
$adminCount = (int)$adminStmt->fetchColumn();
echo "  Count: " . $adminCount . "\n";

echo "\nStatus: " . ($adminCount > 0 ? "READY ✓" : "INCOMPLETE - Need admin account") . "\n";
