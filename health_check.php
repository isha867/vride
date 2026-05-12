<?php
require_once 'db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "VRide Health Check\n";
echo "==================\n";

$pdo = getDB();
if (!$pdo) {
    echo "DB: FAIL (connection unavailable)\n";
    exit(1);
}

echo "DB: OK\n";

$tables = ['users', 'vehicles', 'bookings'];
foreach ($tables as $table) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$table]);
    $exists = (int)$stmt->fetchColumn() > 0;
    echo sprintf("Table %-8s: %s\n", $table, $exists ? 'OK' : 'MISSING');
}

$durationColStmt = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'bookings' AND column_name = 'duration_type'");
$durationColExists = (int)$durationColStmt->fetchColumn() > 0;
echo "Column bookings.duration_type: " . ($durationColExists ? 'OK' : 'MISSING') . "\n";

$adminStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'");
$adminCount = (int)$adminStmt->fetchColumn();
echo "Admin users: " . $adminCount . "\n";
