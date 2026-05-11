<?php
require_once 'db.php';
require_once 'admin_lib.php';
$pageTitle = 'Book Vehicle — VRide';
if (!isLoggedIn()) { flash('Please login to book.','error'); redirect('login.php'); }

/* Invalid or empty ?id= yields intval 0 and can match garbage rows → false "always booked" errors. */
$vid = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($vid < 1) {
  $vid = 1;
}
$titleHint = trim((string)($_GET['t'] ?? $_GET['title'] ?? ''));
$pdo = getDB();
$vehicle = null;
if ($pdo) {
    $stmt = $pdo->prepare("SELECT v.*, u.name as owner_name FROM vehicles v LEFT JOIN users u ON v.owner_id=u.id WHERE v.id=? AND v.status='approved'");
    $stmt->execute([$vid]);
    $vehicle = $stmt->fetch();
}
// Check if the vehicle is currently booked (approved booking overlaps today)
$isBookedNow = false;
$bookedUntil = null;
if ($pdo && $vehicle && !empty($vehicle['id'])) {
  $bookedStmt = $pdo->prepare("SELECT return_date FROM bookings WHERE vehicle_id=? AND status='approved' AND CURDATE() BETWEEN pickup_date AND return_date ORDER BY return_date DESC LIMIT 1");
  $bookedStmt->execute([$vehicle['id']]);
  $bookedUntil = $bookedStmt->fetchColumn();
  $isBookedNow = !empty($bookedUntil);
}
// Demo fallback
if (!$vehicle) {
    // If DB is empty (or vehicle not approved yet), fall back to a small demo catalog
    // keyed by id so the booking form still matches the clicked vehicle.
    $demoCatalog = [
        1 => ["id"=>1,"title"=>"Lamborghini Huracán","type"=>"4wheeler","category"=>"Sports Car","city"=>"LPU Main Gate","final_price"=>12000,"price_per_day"=>12000,"price_12_hours"=>7000,"price_6_hours"=>4000,"price_3_hours"=>2500,"model"=>"Huracán","image"=>"https://images.unsplash.com/photo-1657217674164-9cbf85acfc6d?w=600&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8MTF8fExhbWJvcmdoaW5pJTIwSHVyYWMlQzMlQTFufGVufDB8fDB8fHww","description"=>"High-performance luxury supercar with 645 HP engine. Perfect for adrenaline seekers.","damage_charge"=>50000,"extra_hour_charge"=>5000,"terms"=>"Valid international DL required. No off-road driving.","owner_name"=>"VRide Fleet"],
        2 => ["id"=>2,"title"=>"Royal Enfield Classic 350","type"=>"2wheeler","category"=>"Cruiser Bike","city"=>"At Shop","final_price"=>350,"price_per_day"=>350,"price_12_hours"=>200,"price_6_hours"=>150,"price_3_hours"=>80,"model"=>"Classic 350","image"=>"https://images.unsplash.com/photo-1694956792421-e946fff94564?w=600&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8Mnx8cm95YWwlMjBlbmZpZWxkJTIwY2xhc3NpYyUyMDM1MHxlbnwwfHwwfHx8MA%3D%3D","description"=>"Iconic cruiser, perfect for long highway rides. Smooth engine, comfortable seat.","damage_charge"=>5000,"extra_hour_charge"=>50,"terms"=>"Fuel not included. Return clean.","owner_name"=>"VRide Fleet"],
        3 => ["id"=>3,"title"=>"Mercedes-Benz GLE","type"=>"4wheeler","category"=>"Luxury SUV","city"=>"Law Gate","final_price"=>6500,"price_per_day"=>6500,"price_12_hours"=>3500,"price_6_hours"=>2000,"price_3_hours"=>1200,"model"=>"GLE","image"=>"https://images.unsplash.com/photo-1654306369985-0fb9e1a2baf5?w=600&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8OHx8TWVyY2VkZXMtQmVueiUyMEdMRXxlbnwwfHwwfHx8MA%3D%3D","description"=>"Premium luxury SUV with all modern amenities. Ideal for business travel.","damage_charge"=>15000,"extra_hour_charge"=>1000,"terms"=>"Driver not included. Return with full tank.","owner_name"=>"VRide Fleet"],
        4 => ["id"=>4,"title"=>"Yamaha MT-15 V2","type"=>"2wheeler","category"=>"Naked Sport","city"=>"Green Valley","final_price"=>450,"price_per_day"=>450,"price_12_hours"=>250,"price_6_hours"=>150,"price_3_hours"=>100,"model"=>"MT-15 V2","image"=>"https://images.unsplash.com/photo-1722720251730-3f5df2030e2c?w=600&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8MXx8WWFtYWhhJTIwTVQtMTUlMjBWMiUyMGJsdWV8ZW58MHx8MHx8fDA%3D","description"=>"Aggressive naked sport bike. Best for city thrill riders who want agility.","damage_charge"=>8000,"extra_hour_charge"=>80,"terms"=>"Full gear required. No highway night riding.","owner_name"=>"VRide Fleet"],
        5 => ["id"=>5,"title"=>"Porsche 911 Turbo S","type"=>"4wheeler","category"=>"Sports Car","city"=>"LPU Main Gate","final_price"=>15000,"price_per_day"=>15000,"price_12_hours"=>8000,"price_6_hours"=>4500,"price_3_hours"=>2800,"model"=>"911 Turbo S","image"=>"https://images.unsplash.com/photo-1698131789135-9328c3e5644a?w=600&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8Nnx8UG9yc2NoZSUyMDkxMSUyMFR1cmJvJTIwU3xlbnwwfHwwfHx8MA%3D%3D","description"=>"Ultimate supercar with 650 HP. One of the fastest production cars.","damage_charge"=>60000,"extra_hour_charge"=>6000,"terms"=>"Valid international DL required. Insurance mandatory.","owner_name"=>"VRide Fleet"],
        6 => ["id"=>6,"title"=>"Toyota Innova Crysta","type"=>"4wheeler","category"=>"SUV","city"=>"At Shop","final_price"=>2500,"price_per_day"=>2500,"price_12_hours"=>1500,"price_6_hours"=>900,"price_3_hours"=>600,"model"=>"Innova Crysta","image"=>"https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=800&q=80","description"=>"Spacious 7-seater, ideal for family trips and corporate travel.","damage_charge"=>10000,"extra_hour_charge"=>200,"terms"=>"Driver not included. Return clean.","owner_name"=>"VRide Fleet"],
        7 => ["id"=>7,"title"=>"Mahindra Thar","type"=>"4wheeler","category"=>"Off-Road","city"=>"LPU Main Gate","final_price"=>3000,"price_per_day"=>3000,"price_12_hours"=>1800,"price_6_hours"=>1000,"price_3_hours"=>600,"model"=>"Thar 4x4","image"=>"https://images.unsplash.com/photo-1723306975792-f5a053a59dd3?q=80&w=1200&auto=format&fit=crop","description"=>"Open-top 4x4 built for adventure. Beaches, trails, hills — it handles all.","damage_charge"=>20000,"extra_hour_charge"=>250,"terms"=>"4WD lock for off-road only. Return mud-free.","owner_name"=>"VRide Fleet"],
        8 => ["id"=>8,"title"=>"Swift Dzire","type"=>"4wheeler","category"=>"Sedan","city"=>"At Shop","final_price"=>1200,"price_per_day"=>1200,"price_12_hours"=>700,"price_6_hours"=>400,"price_3_hours"=>250,"model"=>"Dzire 2022","image"=>"https://images.unsplash.com/photo-1541443131876-44b03de101c3?w=1200&q=80","description"=>"Comfortable compact sedan. Great mileage, smooth drive for city and highway.","damage_charge"=>5000,"extra_hour_charge"=>100,"terms"=>"Fuel not included. Return with full tank.","owner_name"=>"VRide Fleet"],
    ];

    // If the click originated from a demo card (index/vehicles), prefer matching by title hint.
    $keyByTitle = [];
    foreach ($demoCatalog as $it) {
        $k = strtolower(trim((string)($it['title'] ?? '')));
        if ($k !== '') $keyByTitle[$k] = $it;
    }

    $hintKey = strtolower($titleHint);
    $vehicle =
        ($hintKey !== '' && isset($keyByTitle[$hintKey])) ? $keyByTitle[$hintKey] :
        ($demoCatalog[$vid] ?? ["id"=>$vid,"title"=>($titleHint ?: "Demo Vehicle"),"type"=>"2wheeler","category"=>"Vehicle","city"=>"LPU Main Gate","final_price"=>500,"price_per_day"=>500,"model"=>"Demo","image"=>"https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800&q=80","description"=>"Demo vehicle used when database is empty.","damage_charge"=>500,"extra_hour_charge"=>50,"terms"=>"Fuel not included. Return clean.","owner_name"=>"VRide Fleet"]);
}

// Homepage / fleet links pass ?t=name so the heading matches the card the user clicked. Prefer that over DB title when IDs collide (e.g. demo cards id 1–8 vs real rows).
$dbTitleTrim = trim((string)($vehicle['title'] ?? ''));
$displayTitle = $titleHint !== '' ? $titleHint : ($dbTitleTrim !== '' ? $dbTitleTrim : 'Vehicle');
if (strlen($displayTitle) > 200) {
  $displayTitle = substr($displayTitle, 0, 200);
}

$rawTypeHint = strtolower(trim((string)($_GET['type'] ?? '')));
$effectiveType = in_array($rawTypeHint, ['2wheeler', '4wheeler'], true)
  ? $rawTypeHint
  : (((($vehicle['type'] ?? '') === '4wheeler')) ? '4wheeler' : '2wheeler');

$categoryLine = ($effectiveType === '4wheeler')
  ? 'CAR · 4 WHEELER'
  : 'BIKE · 2 WHEELER';

$reClassicImage = 'https://images.pexels.com/photos/2611684/pexels-photo-2611684.jpeg?auto=compress&cs=tinysrgb&w=1200';
$default2wImage = 'https://images.pexels.com/photos/2393835/pexels-photo-2393835.jpeg?auto=compress&cs=tinysrgb&w=1200';
$default4wImage = 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=800&q=80';
$imageFallback = ($effectiveType === '2wheeler') ? $default2wImage : $default4wImage;

$localReClassicPublic = 'img/re_classic/side.png';
$localReClassicFile = __DIR__ . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 're_classic' . DIRECTORY_SEPARATOR . 'side.png';
$localReClassicData = null;
if (is_file($localReClassicFile)) {
  $bin = @file_get_contents($localReClassicFile);
  if ($bin !== false) {
    $localReClassicData = 'data:image/png;base64,' . base64_encode($bin);
  }
}

$vehicleImage = trim((string)($vehicle['image'] ?? ''));
$titleLower = strtolower((string)($vehicle['title'] ?? ''));

// Force a fresh remote image URL for Royal Enfield Classic 350.
if (str_contains($titleLower, 'royal enfield') || str_contains($titleLower, 'classic 350')) {
  $vehicleImage = $localReClassicData ?: $localReClassicPublic;
  $imageFallback = $vehicleImage;
}

if ($vehicleImage === '') {
  $vehicleImage = $imageFallback;
}

$imgHintSanitized = '';
if (isset($_GET['img'])) {
  $u = trim((string)$_GET['img']);
  if ($u !== '' && !preg_match('#^javascript:#i', $u) && !preg_match('#^data:#i', $u)) {
    if (filter_var($u, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $u)) {
      $imgHintSanitized = $u;
    } elseif ((str_starts_with($u, 'img/') || str_starts_with($u, './img/')) && !str_contains($u, '..')) {
      $imgHintSanitized = $u;
    }
  }
}
$displayVehicleImage = $imgHintSanitized !== '' ? $imgHintSanitized : $vehicleImage;

$success = false;
$bookingRef = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $pickupDate = trim($_POST['pickup_date'] ?? '');
  $duration = trim($_POST['duration'] ?? 'days');
  $returnDate = trim($_POST['return_date'] ?? '');

  if (!$pickupDate || ($duration === 'days' && !$returnDate)) {
    $error = 'Please select required dates.';
  } else {
    try {
      $pickup = new DateTime($pickupDate);
      if ($duration === 'days') {
        $return = new DateTime($returnDate);
        if ($return < $pickup) {
          $error = 'Return date cannot be earlier than pickup date.';
        }
      } else {
        $returnDate = $pickupDate; // same day return for hourly
        $return = new DateTime($returnDate);
      }
    } catch (Throwable $e) {
      $error = 'Invalid date format. Please choose valid dates.';
    }
  }

  if (!$error) {
    if ($duration === 'days') {
        $days = max(1, (int)$pickup->diff($return)->days);
        $amount = ($vehicle['final_price'] ?? $vehicle['price_per_day']) * $days;
    } else {
        $days = 1; // 1 day for hourly addons calculation
        if ($duration === '12h') $amount = $vehicle['price_12_hours'] ?? ($vehicle['final_price'] ?? $vehicle['price_per_day']) * 0.6;
        elseif ($duration === '6h') $amount = $vehicle['price_6_hours'] ?? ($vehicle['final_price'] ?? $vehicle['price_per_day']) * 0.4;
        elseif ($duration === '3h') $amount = $vehicle['price_3_hours'] ?? ($vehicle['final_price'] ?? $vehicle['price_per_day']) * 0.2;
        else $amount = $vehicle['final_price'] ?? $vehicle['price_per_day'];
    }
    
    // Calculate addon costs
    $addonsCost = vride_calculate_addon_total($_POST['addons'] ?? [], $days);
    $finalAmount = $amount + $addonsCost;
    
    $bookingRef = 'VR-' . strtoupper(substr(md5(uniqid()), 0, 8));

    if ($pdo) {
      try {
        // Inclusive date overlap: intervals share a day iff existing.pickup <= new.return AND existing.return >= new.pickup
        // Skip rows with NULL dates or inverted ranges (bad data) so they cannot block every booking.
        $overlap = $pdo->prepare(
            "SELECT COUNT(*) FROM bookings WHERE vehicle_id = ? AND status = 'approved'
             AND pickup_date IS NOT NULL AND return_date IS NOT NULL
             AND pickup_date <= return_date
             AND pickup_date <= ? AND return_date >= ?"
        );
        $overlap->execute([$vid, $returnDate, $pickupDate]);
        if ((int)$overlap->fetchColumn() > 0) {
          $error = 'This vehicle is already booked for the selected dates. Please choose different dates.';
        }

        if (!$error) {
        // Ensure the vehicle exists in the database to satisfy the Foreign Key constraint for the demo fallback.
        $checkV = $pdo->prepare("SELECT id FROM vehicles WHERE id=?");
        $checkV->execute([$vid]);
        $existingVehicle = $checkV->fetch();
        
        if (!$existingVehicle) {
             // Insert complete vehicle data to prevent data mismatch
             $vehicleTitle = $displayTitle ?: ($vehicle['title']??'Demo Vehicle');
             $vehicleType = $vehicle['type']??'2wheeler';
             $vehicleCategory = $vehicle['category']??'Bike';
             $vehicleCity = $vehicle['city']??'LPU Main Gate';
             $vehiclePrice = (float)($vehicle['price_per_day']??500);
             $vehicleFinalPrice = (float)($vehicle['final_price']??$vehiclePrice);
             $vehicleModel = $vehicle['model']??'';
             $vehicleImage = $vehicle['image']??'';
             
             $pdo->prepare("INSERT INTO vehicles (id, title, type, category, city, price_per_day, final_price, model, image, status) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved')")
                 ->execute([$vid, $vehicleTitle, $vehicleType, $vehicleCategory, $vehicleCity, $vehiclePrice, $vehicleFinalPrice, $vehicleModel, $vehicleImage]);
        } else {
             // Update existing vehicle to ensure price matches what customer saw
             $vehicleFinalPrice = (float)($vehicle['final_price']??($vehicle['price_per_day']??500));
             $pdo->prepare("UPDATE vehicles SET final_price=?, price_per_day=? WHERE id=?")
                 ->execute([$vehicleFinalPrice, (float)($vehicle['price_per_day']??500), $vid]);
        }

        $stmt = $pdo->prepare("INSERT INTO bookings (user_id,vehicle_id,pickup_date,return_date,days,amount,final_amount,addons,payment_method,status,duration_type) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$_SESSION['user_id'],$vid,$pickupDate,$returnDate,$days,$amount,$finalAmount,json_encode($_POST['addons']??[]),$_POST['payment']??'cash','pending', $duration]);
        $success = true;
        }
      } catch (PDOException $e) {
        $error = 'Could not save booking to database. Error: ' . $e->getMessage();
      }
    } else {
      $error = 'Database connection is unavailable. Please try again later.';
    }
    }
}
?>
<?php include 'header.php'; ?>
<style>
.bv-wrap{padding-top:var(--nav-h);padding-left:var(--sidebar-w);min-height:100vh;}
.bv-inner{max-width:1100px;margin:0 auto;padding:3.5rem 2rem 6rem;display:grid;grid-template-columns:1fr 360px;gap:2.5rem;align-items:start;}
.sticky-summary{position:sticky;top:90px;}
.sum-card{background:var(--card);border:1px solid rgba(255,255,255,.07);overflow:hidden;}
.sum-img{height:185px;overflow:hidden;position:relative;}
.sum-img img{width:100%;height:100%;object-fit:cover;}
.sum-img-ov{position:absolute;inset:0;background:linear-gradient(to top,var(--card),transparent 60%);}
.sum-body{padding:1.4rem;}
.sum-vcat{font-family:inherit;font-size:.6rem;font-weight:700;letter-spacing:.22em;text-transform:uppercase;color:var(--blue);margin-bottom:.3rem;}
.sum-vname{font-family:inherit;font-size:1.35rem;font-weight:700;color:var(--white);margin-bottom:1.1rem;}
.sum-rows{display:flex;flex-direction:column;gap:.55rem;margin-bottom:1.2rem;padding-bottom:1rem;border-bottom:1px solid rgba(255,255,255,.05);}
.sum-row{display:flex;justify-content:space-between;align-items:center;font-size:.8rem;}
.sum-row-l{color:var(--txt2);}
.sum-row-v{font-weight:600;}
.sum-total{display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem;}
.sum-total-l{font-family:inherit;font-size:.65rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:var(--txt2);}
.sum-total-v{font-family:inherit;font-size:1.6rem;font-weight:900;color:var(--blue);}
.trust-list{padding:.9rem 1.2rem;background:var(--bg3);border-top:1px solid rgba(255,255,255,.04);}
.trust-item{display:flex;align-items:center;gap:.6rem;font-size:.75rem;color:var(--txt2);margin-bottom:.5rem;}
.trust-item:last-child{margin-bottom:0;}
/* Success */
.success-box{max-width:680px;margin:0 auto;padding:3.5rem 2rem;text-align:center;}
.sb-icon{font-size:4rem;animation:pop .5s ease;}
@keyframes pop{0%{transform:scale(0);}80%{transform:scale(1.1);}100%{transform:scale(1);}}
.sb-h{font-family:inherit;font-size:2.5rem;font-weight:700;text-transform:uppercase;color:var(--white);margin:.8rem 0;}
.sb-h span{color:var(--blue);}
.sb-ref{display:inline-flex;align-items:center;gap:1rem;padding:1rem 2rem;background:var(--card);border:1px solid rgba(26,140,255,.2);margin:1rem 0 2rem;}
.sb-ref-l{font-family:inherit;font-size:.6rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:var(--txt2);}
.sb-ref-v{font-family:inherit;font-size:1.2rem;font-weight:900;color:var(--blue);}
.flow-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:2rem;text-align:left;}
.fstep{padding:1.2rem;background:var(--card);border:1px solid rgba(255,255,255,.05);}
.fstep-n{font-family:inherit;font-size:1.6rem;font-weight:900;color:rgba(26,140,255,.18);margin-bottom:.4rem;}
.fstep-t{font-family:inherit;font-size:.7rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:var(--white);margin-bottom:.3rem;}
.fstep-d{font-size:.76rem;color:var(--txt2);line-height:1.5;}
@media(max-width:850px){.bv-inner{grid-template-columns:1fr;}.sticky-summary{position:static;}.flow-steps{grid-template-columns:1fr;}}
.bv-form-preview-wrap{margin-bottom:1.5rem;padding-bottom:1.25rem;border-bottom:1px solid rgba(255,255,255,.06);}
.bv-form-preview{border-radius:10px;overflow:hidden;border:1px solid rgba(255,255,255,.08);background:var(--bg3);aspect-ratio:16/10;max-height:240px;}
.bv-form-preview img{width:100%;height:100%;object-fit:cover;display:block;vertical-align:top;}
.bv-form-head{margin-top:1rem;}
.bv-form-head .sum-vcat{font-size:.58rem;font-weight:700;letter-spacing:.22em;text-transform:uppercase;color:var(--blue);margin-bottom:.35rem;}
.bv-form-head .sum-vname{font-size:1.2rem;font-weight:700;color:var(--white);line-height:1.2;}
</style>
<div class="bv-wrap">
  <?php if ($success): ?>
  <div class="success-box">
    <div class="sb-icon"><i class="fas fa-check" style="font-size:3rem;color:var(--success);"></i></div>
    <h1 class="sb-h">BOOKING <span>SUBMITTED!</span></h1>
    <p style="color:var(--txt2);font-size:.9rem;margin-bottom:1rem;">Your request has been sent to admin for approval. You'll be notified once confirmed.</p>
    <div class="sb-ref"><div><div class="sb-ref-l">Booking Ref</div><div class="sb-ref-v"><?= $bookingRef ?></div></div></div>
    <div class="flow-steps">
      <div class="fstep"><div class="fstep-n">01</div><div class="fstep-t">Admin Review</div><div class="fstep-d">Admin reviews your request and verifies details within 30 minutes.</div></div>
      <div class="fstep"><div class="fstep-n">02</div><div class="fstep-t">Price Confirmed</div><div class="fstep-d">Final price is confirmed and you'll receive a notification to pay.</div></div>
      <div class="fstep"><div class="fstep-n">03</div><div class="fstep-t">Vehicle Delivery</div><div class="fstep-d">Vehicle is delivered to your location on the pickup date.</div></div>
    </div>
    <a href="dashboard.php" class="btn btn-primary">View My Bookings →</a>
  </div>
  <?php else: ?>
  <div class="bv-inner" style="padding-bottom:6rem;">
    <!-- FORM -->
    <div>
      <div class="sec-label">Reserve Your Ride</div>
      <div class="sec-h" style="margin-bottom:2rem;">BOOKING <span class="dim">FORM</span></div>
      <?php if ($isBookedNow): ?>
      <div style="margin-bottom:1rem;padding:.9rem 1rem;border:1px solid rgba(255,56,96,.35);background:rgba(255,56,96,.08);color:#ffd5de;font-size:.82rem;">
        This vehicle is currently booked<?= $bookedUntil ? ' until '.htmlspecialchars($bookedUntil) : '' ?>. You can only request dates after it becomes available.
      </div>
      <?php endif; ?>
      <?php if (!empty($error)): ?>
      <div style="margin-bottom:1rem;padding:.9rem 1rem;border:1px solid rgba(255,56,96,.35);background:rgba(255,56,96,.08);color:#ffd5de;font-size:.82rem;">
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>
      <div class="form-card">
        <?php
        $bookActionQs = ['id' => $vid, 't' => $displayTitle, 'type' => $effectiveType];
        if ($imgHintSanitized !== '') {
          $bookActionQs['img'] = $imgHintSanitized;
        }
        $bookAction = 'book_vehicle.php?' . http_build_query($bookActionQs, '', '&', PHP_QUERY_RFC3986);
        ?>
        <form method="POST" action="<?= htmlspecialchars($bookAction, ENT_QUOTES, 'UTF-8') ?>">
          <div class="bv-form-preview-wrap">
            <div class="bv-form-preview">
              <img src="<?= htmlspecialchars($displayVehicleImage, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($displayTitle, ENT_QUOTES, 'UTF-8') ?>" onerror="if(!this.dataset.fallback){this.dataset.fallback='1';this.src='<?= htmlspecialchars($imageFallback, ENT_QUOTES, 'UTF-8') ?>';}else{this.onerror=null;this.style.display='none';}">
            </div>
            <div class="bv-form-head">
              <div class="sum-vcat"><?= htmlspecialchars($categoryLine) ?></div>
              <div class="sum-vname"><?= htmlspecialchars($displayTitle) ?></div>
            </div>
          </div>
          <div class="form-section-title"><i class="fas fa-user"></i> Your Details</div>
          <div class="form-row">
            <div class="form-group"><label>Full Name *</label><input type="text" name="name" value="<?= htmlspecialchars($_SESSION['name']??'') ?>" required></div>
            <div class="form-group"><label>Phone *</label><input type="tel" name="phone" placeholder="+91 XXXXX XXXXX" required></div>
          </div>
          <div class="form-group"><label>Email Address *</label><input type="email" name="email" value="<?= htmlspecialchars($_SESSION['email']??'') ?>" required></div>
          <div class="form-group"><label>Driving License Number *</label><input type="text" name="license" placeholder="DL-1234567890" required></div>

          <div class="form-section-title" style="margin-top:1.5rem;"><i class="fas fa-calendar"></i> Rental Schedule</div>
          <div class="form-row">
            <div class="form-group"><label>Pick-up Date *</label><input type="date" name="pickup_date" id="pdate" required></div>
            <div class="form-group"><label>Duration *</label>
              <select name="duration" id="durationSelect" required>
                <option value="days" selected>Per Day (₹<?= number_format($vehicle['final_price'] ?? $vehicle['price_per_day'] ?? 0) ?>)</option>
                <option value="12h">12 Hours (₹<?= number_format($vehicle['price_12_hours'] ?? (($vehicle['final_price'] ?? $vehicle['price_per_day'] ?? 0) * 0.6)) ?>)</option>
                <option value="6h">6 Hours (₹<?= number_format($vehicle['price_6_hours'] ?? (($vehicle['final_price'] ?? $vehicle['price_per_day'] ?? 0) * 0.4)) ?>)</option>
                <option value="3h">3 Hours (₹<?= number_format($vehicle['price_3_hours'] ?? (($vehicle['final_price'] ?? $vehicle['price_per_day'] ?? 0) * 0.2)) ?>)</option>
              </select>
            </div>
          </div>
          <div class="form-row" id="returnDateGroup">
            <div class="form-group"><label>Return Date *</label><input type="date" name="return_date" id="rdate"></div>
          </div>
          <div class="form-group"><label>Pick-up Location *</label>
            <select name="pickup_location"><option>Same as vehicle city (<?= htmlspecialchars($vehicle['city']??'') ?>)</option><option>LPU Main Gate</option><option>At Shop</option><option>Law Gate</option><option>Green Valley</option></select>
          </div>
          <div class="form-group"><label>Delivery Address *</label><input type="text" name="delivery_address" placeholder="Hotel name or full address..." required></div>
          <input type="hidden" name="days" id="daysInput" value="1">

          <div class="form-section-title" style="margin-top:1.5rem;"><i class="fas fa-sparkles"></i> Optional Add-ons</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.7rem;">
            <?php foreach(["GPS Navigation (+₹100/day)","Helmet (+₹50/day)","Roadside Assistance (+₹150/day)","Extra Driver (+₹200/day)","Child Seat (+₹80/day)","Fuel Package (+₹250/day)"] as $a): ?>
            <label style="display:flex;align-items:center;gap:.6rem;padding:.75rem;background:var(--bg3);border:1px solid rgba(255,255,255,.06);cursor:pointer;font-size:.8rem;transition:border-color .3s;" onmouseover="this.style.borderColor='rgba(26,140,255,.3)'" onmouseout="this.style.borderColor='rgba(255,255,255,.06)'">
              <input type="checkbox" name="addons[]" value="<?= $a ?>" style="width:15px;height:15px;accent-color:var(--blue);flex-shrink:0;">
              <?= $a ?>
            </label>
            <?php endforeach; ?>
          </div>

          <div class="form-section-title" style="margin-top:1.5rem;"><i class="fas fa-credit-card"></i> Payment Method & Discounts</div>
          <div style="background: rgba(26, 140, 255, 0.08); border: 1px solid rgba(26, 140, 255, 0.3); padding: 1rem; border-radius: 6px; font-size: 0.85rem; color: rgba(233, 237, 248, 0.85);">
             <strong><i class="fas fa-info-circle"></i> Payment is collected physically at the shop upon vehicle pickup.</strong><br><br>
             We happily accept the following payment methods at the desk:
             <ul style="margin-top:0.4rem; margin-bottom: 0.8rem; margin-left: 1.2rem; display: flex; flex-direction: column; gap: 0.2rem;">
               <li>Cash</li>
               <li>UPI / QR (Google Pay, PhonePe, Paytm, etc.)</li>
               <li>Credit/Debit Cards & Netbanking</li>
             </ul>
             <span style="color: #ffeb3b; font-weight: 600;"><i class="fas fa-gift" style="margin-right: 4px;"></i> Seasonal & Occasional Discounts:</span>
             <p style="margin-top: 0.3rem;">If you are booking during holidays or special occasions, any applicable exclusive discounts will be calculated and directly deducted from your total bill at the shop during the time of payment!</p>
          </div>
          <!-- Keep functionality for database schema: -->
          <input type="hidden" name="payment" value="pay_at_shop">

          <div class="form-group" style="margin-top:1.5rem;"><label>Special Requests *</label><textarea name="notes" placeholder="Any special requirements for our team..." required></textarea></div>

          <div class="form-section-title" style="margin-top:1.5rem; color: #ffeb3b;"><i class="fas fa-file-contract"></i> Rental Agreement & Terms</div>
          <div style="background: rgba(255, 235, 59, 0.05); border: 1px solid rgba(255, 235, 59, 0.2); padding: 1rem; border-radius: 6px; font-size: 0.85rem; color: rgba(233, 237, 248, 0.85); margin-bottom: 1rem;">
            <ul style="margin-left: 1.2rem; margin-bottom: 0.8rem; display: flex; flex-direction: column; gap: 0.4rem;">
              <li><strong>Damage Policy:</strong> You are fully responsible for any damages incurred during the rental period. Repair costs will be calculated upon return. Please handle the vehicle with care.</li>
              <li><strong>ID Proof / License:</strong> You must submit your original Driver's License or a valid ID proof at the shop during pickup. It will be safely kept and returned to you when you return the vehicle.</li>
              <li><strong>Payment Terms:</strong> Full advance payment is required at the time of pickup/booking in order to release the vehicle.</li>
              <li><strong>Vehicle Inspection:</strong> You are encouraged to thoroughly check the vehicle's condition at our shop prior to taking it.</li>
              <li><strong>Legal Agreement:</strong> By proceeding, you acknowledge that you must physically sign a legal rental agreement at the shop before handover.</li>
            </ul>
            <label style="display:flex;align-items:flex-start;gap:.6rem;cursor:pointer;font-size:0.85rem;font-weight:600; color:#fff; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 0.8rem; margin-top: 0.5rem;">
              <input type="checkbox" name="terms_agreed" required style="width:16px;height:16px;accent-color:var(--blue);flex-shrink:0;margin-top:2px;">
              I hereby agree to the rental terms, conditions, and acknowledge the requirement to sign the physical agreement at the shop.
            </label>
          </div>

          <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:1.5rem;">
            Submit Booking Request
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </button>
        </form>
      </div>
    </div>

    <!-- SUMMARY -->
    <div class="sticky-summary">
      <div class="sum-card">
        <div class="sum-img">
          <img src="<?= htmlspecialchars($displayVehicleImage, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($displayTitle, ENT_QUOTES, 'UTF-8') ?>" onerror="if(!this.dataset.fallback){this.dataset.fallback='1';this.src='<?= htmlspecialchars($imageFallback, ENT_QUOTES, 'UTF-8') ?>';}else{this.onerror=null;this.style.display='none';}">
          <div class="sum-img-ov"></div>
        </div>
        <div class="sum-body">
          <div class="sum-vcat"><?= htmlspecialchars($categoryLine) ?></div>
          <div class="sum-vname"><?= htmlspecialchars($displayTitle) ?></div>
          <div class="sum-rows">
            <div class="sum-row"><span class="sum-row-l">Base Rate</span><span class="sum-row-v" id="baseRateLabel">₹<?= number_format($vehicle['final_price']??$vehicle['price_per_day']) ?></span></div>
            <div class="sum-row"><span class="sum-row-l">Duration</span><span class="sum-row-v" id="durLabel">—</span></div>
            <div class="sum-row"><span class="sum-row-l">Owner</span><span class="sum-row-v"><?= htmlspecialchars($vehicle['owner_name']??'VRide') ?></span></div>
            <div class="sum-row"><span class="sum-row-l">Location</span><span class="sum-row-v"><i class="fas fa-map-pin"></i> <?= htmlspecialchars($vehicle['city']??'') ?></span></div>
            <div class="sum-row"><span class="sum-row-l">Damage Deposit</span><span class="sum-row-v">₹<?= number_format($vehicle['damage_charge']??0) ?></span></div>
            <div class="sum-row"><span class="sum-row-l">Add-ons</span><span class="sum-row-v" id="addonLabel">₹0</span></div>
          </div>
          <div class="sum-total">
            <div class="sum-total-l">Estimated Total</div>
            <div class="sum-total-v" id="totalAmt">₹<?= number_format($vehicle['final_price']??$vehicle['price_per_day']) ?></div>
          </div>
        </div>
        <div class="trust-list">
          <div class="trust-item"><i class="fas fa-shield"></i> Admin-verified pricing</div>
          <div class="trust-item"><i class="fas fa-check"></i> Instant booking confirmation</div>
          <div class="trust-item"><i class="fas fa-lock"></i> Payment protected at shop</div>
          <div class="trust-item"><i class="fas fa-undo"></i> Free cancellation (24h before)</div>
          <div class="trust-item"><i class="fas fa-phone"></i> 24/7 support</div>
        </div>
      </div>
    </div>
  </div>
  <?php endif ?>
</div>

<script>
const today=new Date().toISOString().split('T')[0];
const pd=document.getElementById('pdate');
const rd=document.getElementById('rdate');
const durationSelect=document.getElementById('durationSelect');
const returnDateGroup=document.getElementById('returnDateGroup');
if(pd){pd.min=today;pd.value=today;}
if(rd){const t=new Date();t.setDate(t.getDate()+1);rd.min=t.toISOString().split('T')[0];rd.value=t.toISOString().split('T')[0];}

const dailyRate = <?= $vehicle['final_price'] ?? $vehicle['price_per_day'] ?? 0 ?>;
const price12 = <?= $vehicle['price_12_hours'] ?? (($vehicle['final_price'] ?? $vehicle['price_per_day'] ?? 0) * 0.6) ?>;
const price6 = <?= $vehicle['price_6_hours'] ?? (($vehicle['final_price'] ?? $vehicle['price_per_day'] ?? 0) * 0.4) ?>;
const price3 = <?= $vehicle['price_3_hours'] ?? (($vehicle['final_price'] ?? $vehicle['price_per_day'] ?? 0) * 0.2) ?>;

function getBaseRateData(duration, days) {
    if(duration === '12h') return { rate: price12, label: '12 Hours' };
    if(duration === '6h') return { rate: price6, label: '6 Hours' };
    if(duration === '3h') return { rate: price3, label: '3 Hours' };
    return { rate: dailyRate * days, label: days + ' day' + (days > 1 ? 's' : '') };
}

// Extract addon cost from string like "GPS Navigation (+₹100/day)"
function extractAddonCost(addonStr) {
  const match = addonStr.match(/\(\+₹(\d+(?:\.\d{1,2})?)\//i);
  return match ? parseFloat(match[1]) : 0;
}

// Calculate total addon cost for selected addons
function calculateAddonCost(days) {
  const addonCheckboxes = document.querySelectorAll('input[name="addons[]"]:checked');
  let totalAddonCost = 0;
  addonCheckboxes.forEach(checkbox => {
    const dailyCost = extractAddonCost(checkbox.value);
    totalAddonCost += dailyCost * days;
  });
  return totalAddonCost;
}

function updateTotal(){
  if(!pd||!pd.value)return;
  const duration = durationSelect.value;
  
  if (duration === 'days') {
      returnDateGroup.style.display = 'block';
      rd.required = true;
  } else {
      returnDateGroup.style.display = 'none';
      rd.required = false;
  }

  let days = 1;
  if(duration === 'days' && rd && rd.value) {
      days = Math.max(1, Math.round((new Date(rd.value)-new Date(pd.value))/86400000));
  }
  document.getElementById('daysInput').value = days;
  
  const rateData = getBaseRateData(duration, days);
  document.getElementById('durLabel').textContent = rateData.label;
  document.getElementById('baseRateLabel').textContent = '₹' + new Intl.NumberFormat('en-IN').format(rateData.rate);
  
  const addonTotal = calculateAddonCost(days);
  const finalTotal = rateData.rate + addonTotal;
  
  document.getElementById('addonLabel').textContent='₹'+new Intl.NumberFormat('en-IN').format(addonTotal);
  document.getElementById('totalAmt').textContent='₹'+new Intl.NumberFormat('en-IN').format(finalTotal);
}

durationSelect?.addEventListener('change', updateTotal);
pd?.addEventListener('change',updateTotal);
rd?.addEventListener('change',updateTotal);

// Listen for addon checkbox changes
const addonCheckboxes = document.querySelectorAll('input[name="addons[]"]');
addonCheckboxes.forEach(checkbox => {
  checkbox.addEventListener('change', updateTotal);
});

updateTotal();
</script>
</body>
</html>


