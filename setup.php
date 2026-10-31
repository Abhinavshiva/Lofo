<?php
// LOFO - 1-Click Database Setup & Seeder Script
// Can be run from CLI (php setup.php) or via browser (http://localhost:8000/setup.php)

require_once __DIR__ . '/config/database.php';

$isCli = (php_sapi_name() === 'cli');

function outputMsg($msg, $type = 'info') {
    global $isCli;
    if ($isCli) {
        echo "[$type] $msg\n";
    } else {
        $color = $type === 'success' ? '#198754' : ($type === 'danger' ? '#dc3545' : '#0d6efd');
        echo "<div style='font-family: monospace; padding: 6px 12px; margin: 4px 0; background: #f8f9fa; border-left: 4px solid $color;'>[$type] " . htmlspecialchars($msg) . "</div>";
    }
}

if (!$isCli) {
    echo "<!DOCTYPE html><html><head><title>LOFO - Database Setup</title><link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css'></head><body class='bg-light p-4'><div class='container card shadow-sm p-4'><h2 class='text-primary mb-3'>LOFO Database Setup & Seeder</h2>";
}

try {
    $pdo = getDBConnection();
    outputMsg("Connected to MySQL database 'lofo_db' successfully.", "success");

    // Execute Schema
    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("schema.sql not found at $schemaFile");
    }

    $sql = file_get_contents($schemaFile);
    // Remove CREATE DATABASE and USE statements from multi-query if already in db
    $pdo->exec($sql);
    outputMsg("Database tables created / verified successfully.", "success");

    // Create uploads directory
    $uploadsDir = __DIR__ . '/uploads';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0777, true);
        outputMsg("Created directory: uploads/", "info");
    }

    // 1. Seed Categories
    $catCheck = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    if ($catCheck == 0) {
        $categories = [
            ['Electronics', 'fa-laptop', 'Laptops, smartphones, calculators, powerbanks, earphones, etc.'],
            ['Documents & ID Cards', 'fa-id-card', 'College ID cards, driving licenses, hall tickets, certificates.'],
            ['Keys & Locks', 'fa-key', 'Vehicle keys, hostel room keys, locker keys.'],
            ['Wallets & Purses', 'fa-wallet', 'Wallets, purses, money pouches, cardholders.'],
            ['Books & Stationery', 'fa-book', 'Notebooks, textbooks, lab manuals, pencil boxes.'],
            ['Clothing & Accessories', 'fa-glasses', 'Watches, spectacles, umbrellas, jackets, caps.'],
            ['Sports Equipment', 'fa-futbol', 'Rackets, balls, gym bottles, sports gear.'],
            ['Other Items', 'fa-box-open', 'Miscellaneous items not covered by other categories.']
        ];
        $catStmt = $pdo->prepare("INSERT INTO categories (name, icon, description) VALUES (?, ?, ?)");
        foreach ($categories as $cat) {
            $catStmt->execute($cat);
        }
        outputMsg("Seeded " . count($categories) . " categories.", "success");
    }

    // 2. Seed Campus Locations
    $locCheck = $pdo->query("SELECT COUNT(*) FROM locations")->fetchColumn();
    if ($locCheck == 0) {
        $locations = [
            ['UCC Central Library - Reading Hall', 'Academic'],
            ['UCC Central Library - Digital Section', 'Academic'],
            ['MCA Block - Ground Floor Lab', 'Academic'],
            ['MCA Block - 1st Floor Lecture Hall 3A', 'Academic'],
            ['Science Block - Physics/Chemistry Corridor', 'Academic'],
            ['College Main Canteen & Cafeteria', 'Amenities'],
            ['Administrative Building - Main Office', 'Administrative'],
            ['College Auditorium & Open Air Theatre', 'Amenities'],
            ['Basketball Court & Sports Ground', 'Sports'],
            ['Student Amenity Centre', 'Amenities'],
            ['Main Campus Security Gate', 'Security']
        ];
        $locStmt = $pdo->prepare("INSERT INTO locations (name, type) VALUES (?, ?)");
        foreach ($locations as $loc) {
            $locStmt->execute($loc);
        }
        outputMsg("Seeded " . count($locations) . " campus locations.", "success");
    }

    // 3. Seed Users
    $userCheck = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($userCheck == 0) {
        $users = [
            [
                'name' => 'Dr. Administrator',
                'email' => 'admin@ucc.edu.in',
                'phone' => '+91 98470 12345',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'role' => 'admin',
                'department' => 'Administration & Security',
                'year_or_designation' => 'System Administrator'
            ],
            [
                'name' => 'Ms. Veena Jose',
                'email' => 'faculty@ucc.edu.in',
                'phone' => '+91 94471 23456',
                'password' => password_hash('faculty123', PASSWORD_DEFAULT),
                'role' => 'faculty',
                'department' => 'Computer Applications (MCA)',
                'year_or_designation' => 'Assistant Professor'
            ],
            [
                'name' => 'Abhinav V B',
                'email' => 'student@ucc.edu.in',
                'phone' => '+91 97455 34567',
                'password' => password_hash('student123', PASSWORD_DEFAULT),
                'role' => 'student',
                'department' => 'Computer Applications (MCA)',
                'year_or_designation' => 'Batch 2025-2027 (Roll 32)'
            ],
            [
                'name' => 'Krishnaja G',
                'email' => 'krishnaja@ucc.edu.in',
                'phone' => '+91 96332 45678',
                'password' => password_hash('student123', PASSWORD_DEFAULT),
                'role' => 'student',
                'department' => 'Computer Applications (MCA)',
                'year_or_designation' => 'Batch 2025-2027 (Roll 4)'
            ]
        ];

        $userStmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, department, year_or_designation) VALUES (:name, :email, :phone, :password, :role, :department, :year_or_designation)");
        foreach ($users as $u) {
            $userStmt->execute($u);
        }
        outputMsg("Seeded " . count($users) . " default users (Admin, Faculty, Students).", "success");
    }

    // 4. Seed Lost Items
    $lostCheck = $pdo->query("SELECT COUNT(*) FROM lost_items")->fetchColumn();
    if ($lostCheck == 0) {
        $lostItems = [
            [
                'user_id' => 3, // Abhinav
                'item_name' => 'Casio Scientific Calculator fx-991EX',
                'category_id' => 1, // Electronics
                'description' => 'Black and white casing with silver keys. Has a small metallic scratch on the solar panel corner and my initials "AVB" scratched faintly on back cover.',
                'location_lost' => 'MCA Block - Ground Floor Lab',
                'date_lost' => date('Y-m-d', strtotime('-2 days')),
                'image_path' => null,
                'status' => 'Lost'
            ],
            [
                'user_id' => 4, // Krishnaja
                'item_name' => 'UCC College Student ID Card with Blue Lanyard',
                'category_id' => 2, // Documents & ID Cards
                'description' => 'Student ID card for MCA batch with name Krishnaja G, Roll No 4, inside a clear plastic card pouch with college blue silk lanyard.',
                'location_lost' => 'UCC Central Library - Reading Hall',
                'date_lost' => date('Y-m-d', strtotime('-3 days')),
                'image_path' => null,
                'status' => 'Matched'
            ],
            [
                'user_id' => 3, // Abhinav
                'item_name' => 'Fastrack Classic Black Water Bottle',
                'category_id' => 7, // Sports Equipment
                'description' => 'Matte black stainless steel 750ml water bottle with a silver metal loop on top lid.',
                'location_lost' => 'Basketball Court & Sports Ground',
                'date_lost' => date('Y-m-d', strtotime('-5 days')),
                'image_path' => null,
                'status' => 'Lost'
            ]
        ];

        $lostStmt = $pdo->prepare("INSERT INTO lost_items (user_id, item_name, category_id, description, location_lost, date_lost, image_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($lostItems as $item) {
            $lostStmt->execute(array_values($item));
        }
        outputMsg("Seeded sample lost item reports.", "success");
    }

    // 5. Seed Found Items
    $foundCheck = $pdo->query("SELECT COUNT(*) FROM found_items")->fetchColumn();
    if ($foundCheck == 0) {
        $foundItems = [
            [
                'user_id' => 2, // Faculty Veena
                'item_name' => 'Casio Scientific Calculator fx-991EX ClassWiz',
                'category_id' => 1, // Electronics
                'description' => 'Found left behind on Desk 14 in MCA Lab after afternoon practical session. Has initials on the back cover.',
                'location_found' => 'MCA Block - Ground Floor Lab',
                'date_found' => date('Y-m-d', strtotime('-1 days')),
                'image_path' => null,
                'status' => 'Available'
            ],
            [
                'user_id' => 1, // Admin / Security
                'item_name' => 'Union Christian College Student ID Card',
                'category_id' => 2, // Documents & ID Cards
                'description' => 'ID card with UCC emblem found on the reading table near reference section.',
                'location_found' => 'UCC Central Library - Reading Hall',
                'date_found' => date('Y-m-d', strtotime('-2 days')),
                'image_path' => null,
                'status' => 'Available'
            ],
            [
                'user_id' => 4, // Krishnaja
                'item_name' => 'Set of Honda Bike Keys with Red Rubber Keychain',
                'category_id' => 3, // Keys & Locks
                'description' => 'Two keys with a red rubber Honda logo keychain found near the front entrance steps of the canteen.',
                'location_found' => 'College Main Canteen & Cafeteria',
                'date_found' => date('Y-m-d', strtotime('-4 days')),
                'image_path' => null,
                'status' => 'Available'
            ]
        ];

        $foundStmt = $pdo->prepare("INSERT INTO found_items (user_id, item_name, category_id, description, location_found, date_found, image_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($foundItems as $fitem) {
            $foundStmt->execute(array_values($fitem));
        }
        outputMsg("Seeded sample found item records with physical locations.", "success");
    }

    // 6. Seed Smart Matches
    $matchCheck = $pdo->query("SELECT COUNT(*) FROM item_matches")->fetchColumn();
    if ($matchCheck == 0) {
        // Calculator match (Lost ID 1 and Found ID 1)
        $matchStmt = $pdo->prepare("INSERT INTO item_matches (lost_id, found_id, confidence_score, match_reasons, status) VALUES (?, ?, ?, ?, ?)");
        $matchStmt->execute([
            1, 1, 92,
            "Exact category match ('Electronics'), high title similarity ('Casio Scientific Calculator fx-991EX'), matching location ('MCA Block - Ground Floor Lab'), and dates within 1 day.",
            'Pending Review'
        ]);

        // ID card match (Lost ID 2 and Found ID 2)
        $matchStmt->execute([
            2, 2, 88,
            "Category match ('Documents & ID Cards'), location match ('UCC Central Library - Reading Hall'), keywords match ('Student ID Card').",
            'Confirmed'
        ]);
        outputMsg("Seeded smart item match candidates.", "success");
    }

    // 7. Seed Sample Claims
    $claimCheck = $pdo->query("SELECT COUNT(*) FROM claims")->fetchColumn();
    if ($claimCheck == 0) {
        $claimStmt = $pdo->prepare("INSERT INTO claims (found_id, lost_id, user_id, proof, stage1_answers, claim_date, status, admin_remark, verified_by, verification_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        // Stage 1 pending claim by Abhinav for Calculator
        $claimStmt->execute([
            1, 1, 3,
            "The calculator has my initials 'AVB' etched on the bottom right corner of the rear case cover. Also contains a tiny scratch on the top-left solar cell frame.",
            "Answers to Verification: \n1. Model: fx-991EX\n2. Casing color: Black & White\n3. Distinct marks: Initials 'AVB' engraved faintly",
            date('Y-m-d'),
            'Pending',
            null,
            null,
            null
        ]);

        // Approved claim for Krishnaja's ID card
        $claimStmt->execute([
            2, 2, 4,
            "The ID card has my photo, full name 'Krishnaja G', and Roll No 4 for MCA. Blue lanyard attached.",
            "Answers to Verification: Roll No 4, MCA Department, UCC ID verified.",
            date('Y-m-d', strtotime('-1 days')),
            'Approved',
            "Verified with claimant's institutional identity records. Verified by Librarian.",
            1,
            date('Y-m-d H:i:s', strtotime('-12 hours'))
        ]);
        outputMsg("Seeded sample two-stage ownership claims.", "success");
    }

    // 8. Seed Notifications
    $notifCheck = $pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn();
    if ($notifCheck == 0) {
        $notifs = [
            [
                3, // Abhinav
                'Potential Match Found!',
                'A found item "Casio Scientific Calculator fx-991EX ClassWiz" has a 92% match confidence with your lost report. Please review details and submit a claim.',
                'Match Found',
                'item-details.php?type=found&id=1',
                0
            ],
            [
                3,
                'Lost Report Submitted',
                'Your lost report for "Casio Scientific Calculator fx-991EX" has been logged into the LOFO college repository.',
                'Report Confirmation',
                'user/my-items.php',
                1
            ],
            [
                4, // Krishnaja
                'Claim Approved - Ready for Pickup!',
                'Your claim for "Union Christian College Student ID Card" has been Approved! You may collect it from Library Circulation Desk Counter.',
                'Claim Approved',
                'user/my-claims.php',
                0
            ],
            [
                1, // Admin
                'New Claim Pending Verification',
                'Abhinav V B has submitted an ownership claim for "Casio Scientific Calculator fx-991EX ClassWiz". Verification required.',
                'Verification Pending',
                'admin/claims.php',
                0
            ]
        ];

        $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link, is_read) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($notifs as $n) {
            $notifStmt->execute($n);
        }
        outputMsg("Seeded sample real-time notification alerts.", "success");
    }

    outputMsg("LOFO Database Setup & Seeding completed successfully!", "success");

    if (!$isCli) {
        echo "<div class='alert alert-success mt-4'>
            <h4>Setup Complete!</h4>
            <p>You can now sign in with any of the following demo credentials:</p>
            <ul>
                <li><strong>Administrator:</strong> <code>admin@ucc.edu.in</code> / <code>admin123</code></li>
                <li><strong>Faculty:</strong> <code>faculty@ucc.edu.in</code> / <code>faculty123</code></li>
                <li><strong>Student:</strong> <code>student@ucc.edu.in</code> / <code>student123</code></li>
            </ul>
            <a href='index.php' class='btn btn-primary'>Proceed to LOFO Home Portal</a>
        </div></div></body></html>";
    }

} catch (Exception $e) {
    outputMsg("Error during setup: " . $e->getMessage(), "danger");
    if (!$isCli) {
        echo "</div></body></html>";
    }
    exit(1);
}
