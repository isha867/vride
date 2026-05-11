<?php
// ============================================================
// db.php — Database Configuration
// All database connection settings live here
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // Change to your MySQL username
define('DB_PASS', '');           // Change to your MySQL password
define('DB_NAME', 'vehicle_rental');
define('DB_PORT', getenv('DB_PORT') ?: '3306');

/** PDO options for MySQL — PHP 8.5+ deprecates PDO::MYSQL_ATTR_USE_BUFFERED_QUERY */
function pdo_mysql_options(): array {
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    if (PHP_VERSION_ID >= 80500) {
        $opts[\Pdo\Mysql::ATTR_USE_BUFFERED_QUERY] = true;
    } else {
        $opts[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
    }
    return $opts;
}

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8",
                DB_USER, DB_PASS,
                pdo_mysql_options()
            );
        } catch (PDOException $e) {
            // Try to create the database if it does not exist yet.
            try {
                $rootPdo = new PDO(
                    "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8",
                    DB_USER,
                    DB_PASS,
                    pdo_mysql_options()
                );
                $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8 COLLATE utf8_general_ci");

                $pdo = new PDO(
                    "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8",
                    DB_USER,
                    DB_PASS,
                    pdo_mysql_options()
                );
            } catch (PDOException $inner) {
                // If DB is still not available, return null gracefully.
                return null;
            }
        }
    }
    return $pdo;
}

// ── Auto-install: create tables if they don't exist ──────────
function installDB() {
    $pdo = getDB();
    if (!$pdo) return false;

    $sql = "
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(150) UNIQUE NOT NULL,
        phone VARCHAR(20),
        password VARCHAR(255) NOT NULL,
        role ENUM('user','admin') DEFAULT 'user',
        city VARCHAR(100),
        lat DECIMAL(10,8),
        lng DECIMAL(11,8),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS vehicles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        owner_id INT,
        title VARCHAR(150) NOT NULL,
        type ENUM('2wheeler','4wheeler') NOT NULL,
        category VARCHAR(50),
        model VARCHAR(100),
        description TEXT,
        price_per_day DECIMAL(10,2),
        final_price DECIMAL(10,2),
        damage_charge DECIMAL(10,2) DEFAULT 0,
        extra_hour_charge DECIMAL(10,2) DEFAULT 0,
        terms TEXT,
        availability_from DATE,
        availability_to DATE,
        city VARCHAR(100),
        lat DECIMAL(10,8),
        lng DECIMAL(11,8),
        image VARCHAR(255),
        image2 VARCHAR(255),
        image3 VARCHAR(255),
        status ENUM('pending','approved','rejected','rented') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
    );

    CREATE TABLE IF NOT EXISTS bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        vehicle_id INT,
        pickup_date DATE,
        return_date DATE,
        days INT,
        amount DECIMAL(10,2),
        final_amount DECIMAL(10,2),
        addons TEXT,
        payment_method VARCHAR(50) DEFAULT 'cash',
        payment_status ENUM('pending','paid','failed') DEFAULT 'pending',
        status ENUM('pending','approved','rejected','completed') DEFAULT 'pending',
        admin_note TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL
    );
    ";

    foreach (explode(';', $sql) as $q) {
        $q = trim($q);
        if ($q) $pdo->exec($q);
    }

    // Ensure image2 and image3 columns exist in case vehicles table is old.
    $col2 = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'vehicles' AND column_name = 'image2'")->fetchColumn();
    if ((int)$col2 === 0) {
        $pdo->exec("ALTER TABLE vehicles ADD COLUMN image2 VARCHAR(255) AFTER image");
    }

    $col3 = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'vehicles' AND column_name = 'image3'")->fetchColumn();
    if ((int)$col3 === 0) {
        $pdo->exec("ALTER TABLE vehicles ADD COLUMN image3 VARCHAR(255) AFTER image2");
    }

    $colPh = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'vehicles' AND column_name = 'price_3_hours'")->fetchColumn();
    if ((int)$colPh === 0) {
        $pdo->exec("ALTER TABLE vehicles ADD COLUMN price_per_hour DECIMAL(10,2) DEFAULT 0 AFTER final_price");
        $pdo->exec("ALTER TABLE vehicles ADD COLUMN price_3_hours DECIMAL(10,2) DEFAULT 0 AFTER price_per_hour");
        $pdo->exec("ALTER TABLE vehicles ADD COLUMN price_6_hours DECIMAL(10,2) DEFAULT 0 AFTER price_3_hours");
        $pdo->exec("ALTER TABLE vehicles ADD COLUMN price_12_hours DECIMAL(10,2) DEFAULT 0 AFTER price_6_hours");
    }

    $colDur = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'bookings' AND column_name = 'duration_type'")->fetchColumn();
    if ((int)$colDur === 0) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN duration_type VARCHAR(50) DEFAULT 'days' AFTER return_date");
        $pdo->exec("ALTER TABLE bookings MODIFY COLUMN days DECIMAL(10,2)");
    }

    // Insert default admin
    $check = $pdo->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetch();
    if (!$check) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (name,email,phone,password,role,city)
                    VALUES ('Admin','admin@vrental.com','0000000000','$hash','admin','All Cities')");
    }
    return true;
}

// Ensure base schema exists once per request.
function bootstrapDB() {
    static $bootstrapped = false;
    if ($bootstrapped) return;
    $bootstrapped = true;

    $pdo = getDB();
    if (!$pdo) return;

    try {
        $tableCount = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('users','vehicles','bookings')")->fetchColumn();
        if ($tableCount < 3) {
            installDB();
        }

        // Ensure bookings table exists even if earlier schema setup was partial.
        $pdo->exec("CREATE TABLE IF NOT EXISTS bookings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            vehicle_id INT,
            pickup_date DATE,
            return_date DATE,
            days INT,
            amount DECIMAL(10,2),
            final_amount DECIMAL(10,2),
            addons TEXT,
            payment_method VARCHAR(50) DEFAULT 'cash',
            payment_status ENUM('pending','paid','failed') DEFAULT 'pending',
            status ENUM('pending','approved','rejected','completed') DEFAULT 'pending',
            admin_note TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL
        )");

        // Ensure a default admin account exists.
        $adminExists = $pdo->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetchColumn();
        if (!$adminExists) {
            $hash = password_hash('admin123', PASSWORD_DEFAULT);
            $ins = $pdo->prepare("INSERT INTO users (name,email,phone,password,role,city) VALUES (?,?,?,?,?,?)");
            $ins->execute(['Admin','admin@vrental.com','0000000000',$hash,'admin','All Cities']);
        }

        // Ensure demo vehicles are in database with correct data
        $demoVehicles = [
            [1, 'Lamborghini Huracán', '4wheeler', 'Sports Car', 'LPU Main Gate', 12000, 12000, 'Huracán', 'https://images.unsplash.com/photo-1657217674164-9cbf85acfc6d?w=600&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8MTF8fExhbWJvcmdoaW5pJTIwSHVyYWMlQzMlQTFufGVufDB8fDB8fHww'],
            [2, 'Royal Enfield Classic 350', '2wheeler', 'Cruiser Bike', 'At Shop', 350, 350, 'Classic 350', 'https://images.unsplash.com/photo-1694956792421-e946fff94564?w=600&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8Mnx8cm95YWwlMjBlbmZpZWxkJTIwY2xhc3NpYyUyMDM1MHxlbnwwfHwwfHx8MA%3D%3D'],
            [3, 'Mercedes-Benz GLE', '4wheeler', 'Luxury SUV', 'Law Gate', 6500, 6500, 'GLE', 'https://images.unsplash.com/photo-1654306369985-0fb9e1a2baf5?w=600&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8OHx8TWVyY2VkZXMtQmVueiUyMEdMRXxlbnwwfHwwfHx8MA%3D%3D'],
            [4, 'Yamaha MT-15 V2', '2wheeler', 'Naked Sport', 'Green Valley', 450, 450, 'MT-15 V2', 'https://images.unsplash.com/photo-1722720251730-3f5df2030e2c?w=600&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8MXx8WWFtYWhhJTIwTVQtMTUlMjBWMiUyMGJsdWV8ZW58MHx8MHx8fDA%3D'],
            [5, 'Porsche 911 Turbo S', '4wheeler', 'Sports Car', 'LPU Main Gate', 15000, 15000, '911 Turbo S', 'https://images.unsplash.com/photo-1698131789135-9328c3e5644a?w=600&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8Nnx8UG9yc2NoZSUyMDkxMSUyMFR1cmJvJTIwU3xlbnwwfHwwfHx8MA%3D%3D'],
            [6, 'Toyota Innova Crysta', '4wheeler', 'SUV', 'At Shop', 2500, 2500, 'Innova Crysta', 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=800&q=80'],
            [7, 'Mahindra Thar', '4wheeler', 'Off-Road', 'LPU Main Gate', 3000, 3000, 'Thar 4x4', 'https://images.unsplash.com/photo-1723306975792-f5a053a59dd3?q=80&w=1200&auto=format&fit=crop'],
            [8, 'Swift Dzire', '4wheeler', 'Sedan', 'At Shop', 1200, 1200, 'Dzire 2022', 'https://images.unsplash.com/photo-1541443131876-44b03de101c3?w=1200&q=80'],
        ];

        foreach ($demoVehicles as $v) {
            $exists = $pdo->query("SELECT id FROM vehicles WHERE id = " . (int)$v[0])->fetchColumn();
            if ($exists) {
                // Update if exists to ensure prices are correct
                $pdo->prepare("UPDATE vehicles SET title=?, type=?, category=?, city=?, price_per_day=?, final_price=?, model=?, image=?, status='approved' WHERE id=?")
                    ->execute([$v[1], $v[2], $v[3], $v[4], $v[5], $v[6], $v[7], $v[8], $v[0]]);
            } else {
                // Insert if doesn't exist
                $pdo->prepare("INSERT INTO vehicles (id, title, type, category, city, price_per_day, final_price, model, image, status) VALUES (?,?,?,?,?,?,?,?,?,'approved')")
                    ->execute($v);
            }
        }
    } catch (Throwable $e) {
        // Keep app usable even if schema bootstrap fails.
    }
}

bootstrapDB();
session_start();

// ── Helpers ──────────────────────────────────────────────────
function isLoggedIn()  { return isset($_SESSION['user_id']); }
function isAdmin()     { return isset($_SESSION['role']) && $_SESSION['role'] === 'admin'; }
function currentUser() { return $_SESSION ?? []; }
function redirect($url){ header("Location: $url"); exit; }
function flash($msg, $type='success') { $_SESSION['flash'] = ['msg'=>$msg,'type'=>$type]; }
function getFlash() {
    if (isset($_SESSION['flash'])) { $f=$_SESSION['flash']; unset($_SESSION['flash']); return $f; }
    return null;
}

// ── Simple AI Admin Decision Engine ──────────────────────────
function aiAdminDecision($vehicle) {
    // Simulated AI: check price range, completeness, etc.
    $score = 0;
    if (!empty($vehicle['title']))       $score += 20;
    if (!empty($vehicle['model']))       $score += 15;
    if ($vehicle['price_per_day'] > 0)  $score += 20;
    if (!empty($vehicle['city']))        $score += 15;
    if (!empty($vehicle['terms']))       $score += 10;
    if ($vehicle['damage_charge'] > 0)  $score += 10;
    if (!empty($vehicle['description'])) $score += 10;

    // Suggest a price adjustment (AI-style)
    $suggested = $vehicle['price_per_day'];
    $type = strtolower($vehicle['type'] ?? '');
    if ($type === '2wheeler') {
        $suggested = max(200, min(1500, $vehicle['price_per_day']));
    } else {
        $suggested = max(500, min(5000, $vehicle['price_per_day']));
    }

    $decision = $score >= 60 ? 'approved' : 'pending';
    $note = $score >= 60
        ? "AI Review: Listing looks complete (score: $score/100). Suggested daily rate: ₹$suggested."
        : "AI Review: Listing incomplete (score: $score/100). Please fill all required fields.";

    return ['decision'=>$decision, 'suggested_price'=>$suggested, 'note'=>$note, 'score'=>$score];
}

// Haversine distance (km) between two lat/lng points
function haversine($lat1,$lng1,$lat2,$lng2) {
    $R = 6371;
    $dLat = deg2rad($lat2-$lat1);
    $dLng = deg2rad($lng2-$lng1);
    $a = sin($dLat/2)*sin($dLat/2)+cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLng/2)*sin($dLng/2);
    return $R * 2 * atan2(sqrt($a), sqrt(1-$a));
}
?>

