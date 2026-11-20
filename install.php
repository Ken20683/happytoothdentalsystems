<?php
// install.php - Run this once to create database tables
require_once 'database.php';

try {
    // Create patients table
    $pdo->exec("CREATE TABLE IF NOT EXISTS patients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        email VARCHAR(100),
        address TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Create procedures table
    $pdo->exec("CREATE TABLE IF NOT EXISTS procedures (
        id INT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        price DECIMAL(10,2) NOT NULL
    )");

    // Create visits table
    $pdo->exec("CREATE TABLE IF NOT EXISTS visits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        procedure_id INT NOT NULL,
        visit_date DATE NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
        FOREIGN KEY (procedure_id) REFERENCES procedures(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS receipts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        visit_id INT NOT NULL,
        receipt_number VARCHAR(20) UNIQUE NOT NULL,
        payment_method ENUM('Cash', 'Card', 'Mobile Money', 'Insurance', 'Other') DEFAULT 'Cash',
        payment_status ENUM('Paid', 'Pending', 'Partial') DEFAULT 'Paid',
        printed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE
    )");

    // Create expenses table
    $pdo->exec("CREATE TABLE IF NOT EXISTS expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        category VARCHAR(100) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        expense_date DATE NOT NULL,
        payment_method VARCHAR(50) DEFAULT 'Cash',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Create receipt activity log table
    $pdo->exec("CREATE TABLE IF NOT EXISTS receipt_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(20) NOT NULL,
        item_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        source_page VARCHAR(100) NOT NULL,
        printed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Create materials tracker table
    $pdo->exec("CREATE TABLE IF NOT EXISTS materials_tracker (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_name VARCHAR(255) NOT NULL,
        tracked_date DATE NOT NULL,
        gloves_used INT NOT NULL DEFAULT 0,
        dental_needles_used INT NOT NULL DEFAULT 0,
        lignocaine_used INT NOT NULL DEFAULT 0,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Create users table for authentication
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        role ENUM('admin', 'staff') DEFAULT 'staff',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL,
        is_active BOOLEAN DEFAULT TRUE
    )");

    // Create monthly expense archive table for detailed monthly records
    $pdo->exec("CREATE TABLE IF NOT EXISTS monthly_expense_archives (
        id INT AUTO_INCREMENT PRIMARY KEY,
        archive_month VARCHAR(7) NOT NULL,
        expense_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        category VARCHAR(100) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        expense_date DATE NOT NULL,
        payment_method VARCHAR(50) DEFAULT 'Cash',
        notes TEXT,
        archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_monthly_expense_archive (archive_month, expense_id)
    )");

    // Create monthly archives table for historical data
    $pdo->exec("CREATE TABLE IF NOT EXISTS monthly_archives (
        id INT AUTO_INCREMENT PRIMARY KEY,
        archive_month VARCHAR(7) NOT NULL UNIQUE,
        total_patients INT DEFAULT 0,
        total_visits INT DEFAULT 0,
        total_revenue DECIMAL(15,2) DEFAULT 0,
        total_expenses_amount DECIMAL(15,2) DEFAULT 0,
        archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS clinic_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(50) UNIQUE NOT NULL,
        setting_value TEXT,
        description VARCHAR(255),
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS airtel_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        visit_id INT NOT NULL,
        receipt_id INT NOT NULL,
        transaction_id VARCHAR(100) UNIQUE NOT NULL,
        phone VARCHAR(20) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status ENUM('PENDING', 'SUCCESS', 'FAILED') DEFAULT 'PENDING',
        raw_response LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
        FOREIGN KEY (receipt_id) REFERENCES receipts(id) ON DELETE CASCADE
    )");

    require_once 'config.php';

    $stmt = $pdo->prepare("INSERT IGNORE INTO procedures (id, name, price) VALUES (?, ?, ?)");
    foreach ($procedures as $id => $name) {
        $stmt->execute([$id, $name, $procedure_prices[$id]]);
    }

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE username = 'admin'");
    $admin_exists = $stmt->fetch()['count'];

    if ($admin_exists == 0) {
        $default_password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['admin', $default_password, 'admin@happytooth.mw', 'System Administrator', 'admin']);
        echo "<div class='alert alert-info'>Default admin account created: username: <strong>admin</strong>, password: <strong>admin123</strong></div>";
    }

    echo "<div class='alert alert-success'>Database installed successfully! <a href='login.php'>Go to Login</a></div>";
} catch(PDOException $e) {
    die("Installation failed: " . $e->getMessage());
}
?>