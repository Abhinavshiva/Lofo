<?php
// LOFO - Database Schema Alignment & Migration Script
require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDBConnection();
    echo "[INFO] Running LOFO schema alignment...\n";

    // Helper to safely add column if missing
    function addColumnIfMissing($pdo, $table, $column, $definition) {
        $cols = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array($column, $cols)) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            echo "  [+] Added column `$column` to `$table`\n";
        }
    }

    // 1. Table: users
    addColumnIfMissing($pdo, 'users', 'department', "VARCHAR(100) DEFAULT 'Union Christian College'");
    addColumnIfMissing($pdo, 'users', 'year_or_designation', "VARCHAR(50) DEFAULT 'Student / Member'");
    addColumnIfMissing($pdo, 'users', 'account_status', "ENUM('active', 'suspended') NOT NULL DEFAULT 'active'");
    $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('user', 'admin', 'student', 'faculty') NOT NULL DEFAULT 'user'");

    // 2. Table: lost_items
    addColumnIfMissing($pdo, 'lost_items', 'image_path', "VARCHAR(255) DEFAULT NULL");
    addColumnIfMissing($pdo, 'lost_items', 'category_id', "INT DEFAULT NULL");
    $pdo->exec("ALTER TABLE `lost_items` MODIFY COLUMN `status` ENUM('Lost', 'Matched', 'Returned', 'Claimed') NOT NULL DEFAULT 'Lost'");

    // 3. Table: found_items
    addColumnIfMissing($pdo, 'found_items', 'image_path', "VARCHAR(255) DEFAULT NULL");
    addColumnIfMissing($pdo, 'found_items', "VARCHAR(150) NOT NULL DEFAULT 'Main Campus Security Gate'");
    addColumnIfMissing($pdo, 'found_items', 'category_id', "INT DEFAULT NULL");
    $pdo->exec("ALTER TABLE `found_items` MODIFY COLUMN `status` ENUM('Available', 'Claimed', 'Returned') NOT NULL DEFAULT 'Available'");

    // 4. Table: claims
    addColumnIfMissing($pdo, 'claims', 'lost_id', "INT DEFAULT NULL");
    addColumnIfMissing($pdo, 'claims', 'proof_image', "VARCHAR(255) DEFAULT NULL");
    addColumnIfMissing($pdo, 'claims', 'stage1_answers', "TEXT DEFAULT NULL");
    addColumnIfMissing($pdo, 'claims', 'verified_by', "INT DEFAULT NULL");
    addColumnIfMissing($pdo, 'claims', 'verification_date', "DATETIME DEFAULT NULL");
    addColumnIfMissing($pdo, 'claims', 'created_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    $pdo->exec("ALTER TABLE `claims` MODIFY COLUMN `status` ENUM('Pending', 'Under Review', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending'");
    $pdo->exec("ALTER TABLE `claims` MODIFY COLUMN `proof` TEXT NOT NULL");

    // 5. Table: notifications
    addColumnIfMissing($pdo, 'notifications', 'type', "VARCHAR(50) NOT NULL DEFAULT 'General'");
    addColumnIfMissing($pdo, 'notifications', 'link', "VARCHAR(255) DEFAULT NULL");
    addColumnIfMissing($pdo, 'notifications', 'is_read', "TINYINT(1) NOT NULL DEFAULT 0");

    // 6. Ensure default categories exist and match
    $cats = [
        ['Electronics', 'fa-laptop', 'Laptops, mobile phones, earphones, calculators, chargers'],
        ['Documents & ID Cards', 'fa-id-card', 'College ID cards, driving licenses, certificates, hall tickets'],
        ['Keys & Locks', 'fa-key', 'Vehicle keys, hostel room keys, locker keys'],
        ['Wallets & Bags', 'fa-wallet', 'Wallets, purses, backpacks, cardholders'],
        ['Books & Stationery', 'fa-book', 'Notebooks, textbooks, lab manuals, pencil boxes'],
        ['Clothing & Accessories', 'fa-glasses', 'Watches, spectacles, umbrellas, jackets, caps'],
        ['Sports Equipment', 'fa-futbol', 'Rackets, balls, gym bottles, sports gear'],
        ['Other Items', 'fa-box-open', 'Miscellaneous campus items']
    ];

    $catStmt = $pdo->prepare("INSERT INTO categories (name, icon, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE icon = VALUES(icon), description = VALUES(description)");
    foreach ($cats as $c) {
        $catStmt->execute($c);
    }

    // Map category string to category_id in lost_items and found_items
    $categoryRows = $pdo->query("SELECT category_id, name FROM categories")->fetchAll();
    $catMap = [];
    foreach ($categoryRows as $row) {
        $catMap[strtolower($row['name'])] = $row['category_id'];
    }

    // Default Electronics to 1 if available
    $defaultCatId = $categoryRows[0]['category_id'] ?? 1;

    // Update lost_items category_id
    $lostList = $pdo->query("SELECT lost_id, category FROM lost_items WHERE category_id IS NULL OR category_id = 0")->fetchAll();
    foreach ($lostList as $l) {
        $foundId = $defaultCatId;
        $catLower = strtolower($l['category'] ?? '');
        foreach ($catMap as $name => $id) {
            if (strpos($catLower, $name) !== false || strpos($name, $catLower) !== false) {
                $foundId = $id;
                break;
            }
        }
        $pdo->prepare("UPDATE lost_items SET category_id = ? WHERE lost_id = ?")->execute([$foundId, $l['lost_id']]);
    }

    // Update found_items category_id
    $foundList = $pdo->query("SELECT found_id, category, FROM found_items WHERE category_id IS NULL OR category_id = 0 ")->fetchAll();
    foreach ($foundList as $f) {
        $foundId = $defaultCatId;
        $catLower = strtolower($f['category'] ?? '');
        foreach ($catMap as $name => $id) {
            if (strpos($catLower, $name) !== false || strpos($name, $catLower) !== false) {
                $foundId = $id;
                break;
            }
        }
       
       $pdo->prepare("
    UPDATE found_items
    SET category_id = ?
    WHERE found_id = ?
")->execute([$foundId, $f['found_id']]);
    }

    // 7. Ensure test credentials exist
    $testUsers = [
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
            'year_or_designation' => 'Assistant Professor & Guide'
        ],
        [
            'name' => 'Abhinav V B',
            'email' => 'student@ucc.edu.in',
            'phone' => '+91 97455 34567',
            'password' => password_hash('student123', PASSWORD_DEFAULT),
            'role' => 'student',
            'department' => 'Computer Applications (MCA)',
            'year_or_designation' => 'Batch 2025-2027 (Roll 32)'
        ]
    ];

    foreach ($testUsers as $tu) {
        $chk = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $chk->execute([$tu['email']]);
        if ($chk->fetch()) {
            $pdo->prepare("UPDATE users SET password = ?, role = ?, department = ?, year_or_designation = ? WHERE email = ?")
                ->execute([$tu['password'], $tu['role'], $tu['department'], $tu['year_or_designation'], $tu['email']]);
        } else {
            $pdo->prepare("INSERT INTO users (name, email, phone, password, role, department, year_or_designation, account_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')")
                ->execute([$tu['name'], $tu['email'], $tu['phone'], $tu['password'], $tu['role'], $tu['department'], $tu['year_or_designation']]);
        }
    }

    // Also update admin@uccollege.edu.in to have admin123 password so both work
    $adminOld = $pdo->prepare("UPDATE users SET password = ? WHERE email = 'admin@uccollege.edu.in'");
    $adminOld->execute([password_hash('admin123', PASSWORD_DEFAULT)]);

    echo "[SUCCESS] LOFO database migration & schema alignment complete!\n";

} catch (Exception $e) {
    echo "[ERROR] Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
