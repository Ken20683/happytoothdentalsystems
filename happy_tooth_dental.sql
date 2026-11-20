-- ======================================================
-- DATABASE: happy_tooth_dental
-- CLINIC: HAPPY TOOTH DENTAL
-- LOCATION: LILONGWE AREA 25C NEAR NAZAREEN CHURCH
-- CONTACT: 0992239889
-- ======================================================

-- Create the database
CREATE DATABASE IF NOT EXISTS happy_tooth_dental;
USE happy_tooth_dental;

-- ======================================================
-- TABLE: patients
-- Stores all patient information
-- ======================================================
CREATE TABLE patients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    date_of_birth DATE,
    gender ENUM('Male', 'Female', 'Other'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- TABLE: procedures
-- Stores all dental procedures with their prices
-- ======================================================
CREATE TABLE procedures (
    id INT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    category VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- TABLE: visits
-- Stores patient visits and procedures performed
-- ======================================================
CREATE TABLE visits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    procedure_id INT NOT NULL,
    visit_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (procedure_id) REFERENCES procedures(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- TABLE: expenses
-- Stores clinic operating expenses
-- ======================================================
CREATE TABLE expenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    expense_date DATE NOT NULL,
    payment_method VARCHAR(50) DEFAULT 'Cash',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- TABLE: materials_tracker
-- Stores daily material usage per patient
-- ======================================================
CREATE TABLE materials_tracker (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_name VARCHAR(255) NOT NULL,
    tracked_date DATE NOT NULL,
    gloves_used INT NOT NULL DEFAULT 0,
    dental_needles_used INT NOT NULL DEFAULT 0,
    lignocaine_used INT NOT NULL DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- TABLE: receipts
-- Stores receipt information for each visit
-- ======================================================
CREATE TABLE receipts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    visit_id INT NOT NULL,
    receipt_number VARCHAR(20) UNIQUE NOT NULL,
    payment_method ENUM('Cash', 'Card', 'Mobile Money', 'Insurance', 'Other') DEFAULT 'Cash',
    payment_status ENUM('Paid', 'Pending', 'Partial') DEFAULT 'Paid',
    printed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stores Airtel Money collection attempts and gateway statuses
CREATE TABLE airtel_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- TABLE: users (For future admin login)
-- Stores system users
-- ======================================================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role ENUM('Admin', 'Receptionist', 'Doctor') DEFAULT 'Receptionist',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- TABLE: appointments (For future appointment booking)
-- Stores patient appointments
-- ======================================================
CREATE TABLE appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    procedure_id INT,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('Scheduled', 'Confirmed', 'Completed', 'Cancelled', 'No-Show') DEFAULT 'Scheduled',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (procedure_id) REFERENCES procedures(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- TABLE: clinic_settings
-- Stores clinic configuration
-- ======================================================
CREATE TABLE clinic_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT,
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- TABLE: monthly_archives
-- Stores monthly data summaries for historical records
-- ======================================================
CREATE TABLE monthly_archives (
    id INT PRIMARY KEY AUTO_INCREMENT,
    archive_month VARCHAR(7) NOT NULL UNIQUE,
    total_patients INT DEFAULT 0,
    total_visits INT DEFAULT 0,
    total_revenue DECIMAL(15,2) DEFAULT 0,
    total_expenses_amount DECIMAL(15,2) DEFAULT 0,
    archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- INSERT DATA
-- ======================================================

-- Insert clinic settings
INSERT INTO clinic_settings (setting_key, setting_value, description) VALUES
('clinic_name', 'HAPPY TOOTH DENTAL', 'Name of the clinic'),
('clinic_location', 'LILONGWE AREA 25C NEAR NAZAREEN CHURCH', 'Clinic address'),
('clinic_contact', '0992239889', 'Contact phone number'),
('clinic_email', 'happytoothdental@gmail.com', 'Contact email'),
('currency', 'MK', 'Currency symbol'),
('tax_rate', '0', 'Tax rate percentage');

-- Insert all 24 procedures with prices
INSERT INTO procedures (id, name, price, category, description) VALUES
(1, 'Extraction', 20000.00, 'Surgery', 'Simple tooth extraction'),
(2, 'Disimpaction/Surgical Extraction', 25000.00, 'Surgery', 'Surgical removal of impacted tooth'),
(3, 'Infected/Dry Socket', 12000.00, 'Surgery', 'Treatment for infected or dry socket'),
(4, 'Scaling and Polishing', 18000.00, 'Preventive', 'Professional teeth cleaning and polishing'),
(5, 'Composite Filling', 8000.00, 'Restorative', 'Tooth-colored filling'),
(6, 'Amalgam Filling', 7000.00, 'Restorative', 'Silver filling for cavities'),
(7, 'Temporary Filling', 5000.00, 'Restorative', 'Temporary filling material'),
(8, 'Root Canal Therapy - Single Canal', 35000.00, 'Endodontic', 'Root canal treatment for single canal'),
(9, 'Root Canal Therapy - Two Canal', 45000.00, 'Endodontic', 'Root canal treatment for two canals'),
(10, 'Root Canal Therapy - Multiple Canals', 55000.00, 'Endodontic', 'Root canal treatment for multiple canals'),
(11, 'Dentures up to Tooth', 20000.00, 'Prosthodontic', 'Partial denture for up to one tooth'),
(12, 'Acrylic Crown', 30000.00, 'Prosthodontic', 'Acrylic material crown'),
(13, 'Acrylic Bridge', 40000.00, 'Prosthodontic', 'Acrylic bridge for missing teeth'),
(14, 'Porcelain Crown', 60000.00, 'Prosthodontic', 'Porcelain dental crown'),
(15, 'Zirconium Crown', 80000.00, 'Prosthodontic', 'Zirconium dental crown'),
(16, 'Zirconium Bridge', 100000.00, 'Prosthodontic', 'Zirconium bridge for missing teeth'),
(17, 'Gold/Silver Inlays and Crowns', 90000.00, 'Prosthodontic', 'Gold or silver inlays and crowns'),
(18, 'Gold/Silver Bridge', 110000.00, 'Prosthodontic', 'Gold or silver bridge'),
(19, 'Star and Other Tooth Jewellery Installation', 25000.00, 'Cosmetic', 'Tooth jewellery installation'),
(20, 'Orthodontic Treatment (Braces)', 150000.00, 'Orthodontic', 'Full braces treatment'),
(21, 'Suturing', 10000.00, 'Surgery', 'Suturing of oral tissues'),
(22, 'Teeth Splinting', 15000.00, 'Restorative', 'Teeth splinting procedure'),
(23, 'Consultation', 5000.00, 'Diagnostic', 'General consultation'),
(24, 'Dentures - Any Addition Tooth', 25000.00, 'Prosthodontic', 'Adding tooth to existing denture');

-- Insert default admin user (password: admin123)
INSERT INTO users (username, password, full_name, email, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@happytooth.com', 'Admin'),
('receptionist', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Reception Staff', 'reception@happytooth.com', 'Receptionist');

-- ======================================================
-- CREATE INDEXES FOR BETTER PERFORMANCE
-- ======================================================

-- Patients indexes
CREATE INDEX idx_patients_name ON patients(name);
CREATE INDEX idx_patients_phone ON patients(phone);
CREATE INDEX idx_patients_email ON patients(email);

-- Visits indexes
CREATE INDEX idx_visits_patient ON visits(patient_id);
CREATE INDEX idx_visits_procedure ON visits(procedure_id);
CREATE INDEX idx_visits_date ON visits(visit_date);
CREATE INDEX idx_visits_patient_date ON visits(patient_id, visit_date);

-- Appointments indexes
CREATE INDEX idx_appointments_patient ON appointments(patient_id);
CREATE INDEX idx_appointments_date ON appointments(appointment_date);
CREATE INDEX idx_appointments_status ON appointments(status);

-- Receipts indexes
CREATE INDEX idx_receipts_visit ON receipts(visit_id);
CREATE INDEX idx_receipts_number ON receipts(receipt_number);

-- ======================================================
-- CREATE VIEWS FOR COMMON QUERIES
-- ======================================================

-- View: Patient summary with visit count and total spent
CREATE OR REPLACE VIEW patient_summary AS
SELECT 
    p.id,
    p.name,
    p.phone,
    p.email,
    p.gender,
    COUNT(v.id) AS total_visits,
    COALESCE(SUM(v.amount), 0) AS total_spent,
    MAX(v.visit_date) AS last_visit_date,
    p.created_at
FROM patients p
LEFT JOIN visits v ON p.id = v.patient_id
GROUP BY p.id
ORDER BY p.name;

-- View: Procedure statistics
CREATE OR REPLACE VIEW procedure_statistics AS
SELECT 
    pr.id,
    pr.name,
    pr.price,
    COUNT(v.id) AS patient_count,
    COALESCE(SUM(v.amount), 0) AS total_revenue,
    pr.category
FROM procedures pr
LEFT JOIN visits v ON pr.id = v.procedure_id
GROUP BY pr.id
ORDER BY pr.id;

-- View: Daily revenue summary
CREATE OR REPLACE VIEW daily_revenue AS
SELECT 
    visit_date,
    COUNT(id) AS visit_count,
    SUM(amount) AS total_revenue,
    AVG(amount) AS average_amount
FROM visits
GROUP BY visit_date
ORDER BY visit_date DESC;

-- View: Patient visit history with details
CREATE OR REPLACE VIEW patient_visit_history AS
SELECT 
    v.id AS visit_id,
    p.id AS patient_id,
    p.name AS patient_name,
    p.phone AS patient_phone,
    pr.name AS procedure_name,
    pr.price AS procedure_price,
    v.visit_date,
    v.amount,
    v.notes,
    r.receipt_number,
    r.payment_method,
    r.payment_status,
    r.printed_at
FROM visits v
JOIN patients p ON v.patient_id = p.id
JOIN procedures pr ON v.procedure_id = pr.id
LEFT JOIN receipts r ON v.id = r.visit_id
ORDER BY v.visit_date DESC;

-- View: Monthly statistics
CREATE OR REPLACE VIEW monthly_statistics AS
SELECT 
    DATE_FORMAT(visit_date, '%Y-%m') AS month,
    COUNT(DISTINCT patient_id) AS unique_patients,
    COUNT(id) AS total_visits,
    SUM(amount) AS total_revenue,
    AVG(amount) AS average_revenue
FROM visits
GROUP BY DATE_FORMAT(visit_date, '%Y-%m')
ORDER BY month DESC;

-- ======================================================
-- STORED PROCEDURES
-- ======================================================

-- Stored Procedure: Add new patient with validation
DELIMITER //
CREATE PROCEDURE sp_add_patient(
    IN p_name VARCHAR(100),
    IN p_phone VARCHAR(20),
    IN p_email VARCHAR(100),
    IN p_address TEXT,
    IN p_dob DATE,
    IN p_gender VARCHAR(10)
)
BEGIN
    INSERT INTO patients (name, phone, email, address, date_of_birth, gender)
    VALUES (p_name, p_phone, p_email, p_address, p_dob, p_gender);
    
    SELECT LAST_INSERT_ID() AS patient_id;
END//
DELIMITER ;

-- Stored Procedure: Add visit and generate receipt
DELIMITER //
CREATE PROCEDURE sp_add_visit(
    IN p_patient_id INT,
    IN p_procedure_id INT,
    IN p_visit_date DATE,
    IN p_payment_method VARCHAR(20),
    IN p_payment_status VARCHAR(20),
    IN p_notes TEXT
)
BEGIN
    DECLARE v_price DECIMAL(10,2);
    DECLARE v_visit_id INT;
    DECLARE v_receipt_number VARCHAR(20);
    
    -- Get procedure price
    SELECT price INTO v_price FROM procedures WHERE id = p_procedure_id;
    
    -- Insert visit
    INSERT INTO visits (patient_id, procedure_id, visit_date, amount, notes)
    VALUES (p_patient_id, p_procedure_id, p_visit_date, v_price, p_notes);
    
    SET v_visit_id = LAST_INSERT_ID();
    
    -- Generate receipt number
    SET v_receipt_number = CONCAT('RCP-', DATE_FORMAT(p_visit_date, '%Y%m%d'), '-', LPAD(v_visit_id, 4, '0'));
    
    -- Insert receipt
    INSERT INTO receipts (visit_id, receipt_number, payment_method, payment_status)
    VALUES (v_visit_id, v_receipt_number, p_payment_method, p_payment_status);
    
    SELECT v_visit_id AS visit_id, v_receipt_number AS receipt_number;
END//
DELIMITER ;

-- ======================================================
-- TRIGGERS
-- ======================================================

-- Trigger: Auto-update patient updated_at
DELIMITER //
CREATE TRIGGER trg_patients_update 
BEFORE UPDATE ON patients
FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END//
DELIMITER ;

-- Trigger: Auto-update visits updated_at
DELIMITER //
CREATE TRIGGER trg_visits_update 
BEFORE UPDATE ON visits
FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END//
DELIMITER ;

-- ======================================================
-- SAMPLE DATA FOR TESTING
-- ======================================================

-- Insert sample patients
INSERT INTO patients (name, phone, email, address, date_of_birth, gender) VALUES
('John Doe', '0999123456', 'john.doe@email.com', 'Area 25, Lilongwe', '1985-03-15', 'Male'),
('Jane Smith', '0999234567', 'jane.smith@email.com', 'Area 18, Lilongwe', '1990-07-22', 'Female'),
('Robert Mwale', '0999345678', 'robert.mwale@email.com', 'Area 47, Lilongwe', '1978-11-05', 'Male'),
('Sarah Kamanga', '0999456789', 'sarah.k@email.com', 'Area 25C, Lilongwe', '1995-01-30', 'Female'),
('David Banda', '0999567890', 'david.b@email.com', 'Area 10, Lilongwe', '1982-09-12', 'Male');

-- Insert sample visits
INSERT INTO visits (patient_id, procedure_id, visit_date, amount, notes) VALUES
(1, 4, '2026-01-15', 18000.00, 'Regular cleaning'),
(1, 5, '2026-01-15', 8000.00, 'Composite filling on tooth #3'),
(2, 23, '2026-01-16', 5000.00, 'Initial consultation'),
(2, 8, '2026-01-20', 35000.00, 'Root canal treatment'),
(3, 1, '2026-01-18', 15000.00, 'Extraction of wisdom tooth'),
(4, 20, '2026-01-22', 150000.00, 'Braces installation'),
(5, 14, '2026-01-25', 60000.00, 'Porcelain crown on tooth #7'),
(1, 23, '2026-02-01', 5000.00, 'Follow-up consultation');

-- Insert sample receipts
INSERT INTO receipts (visit_id, receipt_number, payment_method, payment_status) VALUES
(1, 'RCP-20260115-0001', 'Cash', 'Paid'),
(2, 'RCP-20260115-0002', 'Cash', 'Paid'),
(3, 'RCP-20260116-0003', 'Mobile Money', 'Paid'),
(4, 'RCP-20260120-0004', 'Card', 'Paid'),
(5, 'RCP-20260118-0005', 'Cash', 'Paid'),
(6, 'RCP-20260122-0006', 'Insurance', 'Partial'),
(7, 'RCP-20260125-0007', 'Mobile Money', 'Paid'),
(8, 'RCP-20260201-0008', 'Cash', 'Paid');

-- ======================================================
-- BACKUP COMMANDS (For reference)
-- ======================================================

-- Export entire database:
-- mysqldump -u root -p happy_tooth_dental > happy_tooth_dental_backup.sql

-- Import database:
-- mysql -u root -p happy_tooth_dental < happy_tooth_dental_backup.sql

-- ======================================================
-- END OF DATABASE SCHEMA
-- ======================================================