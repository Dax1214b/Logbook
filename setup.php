<?php
require_once 'includes/config.php';

try {
    // Create the database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS logbook_db");
    
    // Select the database
    $pdo->exec("USE logbook_db");
    
    echo "Creating tables...<br>";

    // Create users table
    $pdo->exec("DROP TABLE IF EXISTS audit_trail");
    $pdo->exec("DROP TABLE IF EXISTS compliance_records");
    $pdo->exec("DROP TABLE IF EXISTS logs");
    $pdo->exec("DROP TABLE IF EXISTS equipment");
    $pdo->exec("DROP TABLE IF EXISTS users");

    echo "Creating users table...<br>";
    $pdo->exec("CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        role ENUM('shift_engineer', 'plant_supervisor', 'compliance_team', 'admin') NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    echo "Creating equipment table...<br>";
    $pdo->exec("CREATE TABLE equipment (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        type VARCHAR(50) NOT NULL,
        location VARCHAR(100) NOT NULL,
        installation_date DATE,
        last_maintenance_date DATE,
        status ENUM('operational', 'maintenance', 'offline', 'fault') NOT NULL
    ) ENGINE=InnoDB");

    echo "Creating logs table...<br>";
    $pdo->exec("CREATE TABLE logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        date_time TIMESTAMP NOT NULL,
        shift ENUM('morning', 'afternoon', 'night') NOT NULL,
        category ENUM('routine', 'incident', 'maintenance', 'compliance') NOT NULL,
        equipment_id INT,
        parameters JSON NOT NULL,
        readings JSON,
        incidents TEXT,
        actions_taken TEXT,
        notes TEXT,
        attachments VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (equipment_id) REFERENCES equipment(id)
    ) ENGINE=InnoDB");

    echo "Creating compliance_records table...<br>";
    $pdo->exec("CREATE TABLE compliance_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        log_id INT NOT NULL,
        regulation_code VARCHAR(50) NOT NULL,
        compliance_status ENUM('compliant', 'non_compliant', 'pending_review') NOT NULL,
        review_date DATE NOT NULL,
        reviewed_by INT NOT NULL,
        comments TEXT,
        FOREIGN KEY (log_id) REFERENCES logs(id),
        FOREIGN KEY (reviewed_by) REFERENCES users(id)
    ) ENGINE=InnoDB");

    echo "Creating audit_trail table...<br>";
    $pdo->exec("CREATE TABLE audit_trail (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        action_type ENUM('create', 'update', 'delete') NOT NULL,
        table_name VARCHAR(50) NOT NULL,
        record_id INT NOT NULL,
        changes JSON NOT NULL,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB");

    echo "Creating indexes...<br>";
    $pdo->exec("CREATE INDEX idx_logs_date_time ON logs(date_time)");
    $pdo->exec("CREATE INDEX idx_logs_category ON logs(category)");
    $pdo->exec("CREATE INDEX idx_compliance_regulation ON compliance_records(regulation_code)");
    $pdo->exec("CREATE INDEX idx_audit_timestamp ON audit_trail(timestamp)");

    echo "<strong>Database setup completed successfully!</strong>";
} catch (PDOException $e) {
    die("<strong>Setup failed:</strong> " . $e->getMessage() . "<br>Error Code: " . $e->getCode());
} 