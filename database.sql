CREATE DATABASE IF NOT EXISTS logbook_db;

USE logbook_db;

-- Users table for authentication and role management
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('shift_engineer', 'plant_supervisor', 'compliance_team', 'admin') NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Equipment table to track plant machinery and systems
CREATE TABLE IF NOT EXISTS equipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(50) NOT NULL,
    location VARCHAR(100) NOT NULL,
    installation_date DATE,
    last_maintenance_date DATE,
    status ENUM('operational', 'maintenance', 'offline', 'fault') NOT NULL
);

-- Main logs table with enhanced fields
CREATE TABLE IF NOT EXISTS logs (
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
);

-- Compliance records table
CREATE TABLE IF NOT EXISTS compliance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_id INT NOT NULL,
    regulation_code VARCHAR(50) NOT NULL,
    compliance_status ENUM('compliant', 'non_compliant', 'pending_review') NOT NULL,
    review_date DATE NOT NULL,
    reviewed_by INT NOT NULL,
    comments TEXT,
    FOREIGN KEY (log_id) REFERENCES logs(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
);

-- Audit trail table
CREATE TABLE IF NOT EXISTS audit_trail (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action_type ENUM('create', 'update', 'delete') NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    record_id INT NOT NULL,
    changes JSON NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Create indexes for better performance
CREATE INDEX idx_logs_date_time ON logs(date_time);
CREATE INDEX idx_logs_category ON logs(category);
CREATE INDEX idx_compliance_regulation ON compliance_records(regulation_code);
CREATE INDEX idx_audit_timestamp ON audit_trail(timestamp);
    