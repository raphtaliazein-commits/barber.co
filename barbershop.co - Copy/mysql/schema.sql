-- =====================================================================
-- BARBERSHOP.CO (Click&Cut) - FULL DATABASE SCHEMA
-- The Barber Co - Carmona, Cavite
--
-- HOW TO USE THIS FILE:
-- 1. Open phpMyAdmin (http://localhost/phpmyadmin)
-- 2. Click "SQL" tab
-- 3. Copy everything in this file and click "Go"
-- This will DROP the old database and create a brand new, complete one.
-- =====================================================================

DROP DATABASE IF EXISTS barbershop_database;

CREATE DATABASE barbershop_database
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE barbershop_database;


-- =====================================================================
-- CUSTOMERS TABLE (also stores the Administrator account)
-- =====================================================================

CREATE TABLE customers (
    customer_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) NOT NULL UNIQUE,
    email_address VARCHAR(150) NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    profile_image VARCHAR(255) NULL,
    date_of_birth DATE NULL,
    email_verified_at TIMESTAMP NULL,
    email_verification_token VARCHAR(64) NULL,
    email_verification_sent_at TIMESTAMP NULL,
    customer_role ENUM('Customer', 'Administrator') NOT NULL DEFAULT 'Customer',
    account_status ENUM('Active', 'Inactive', 'Blocked') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


-- =====================================================================
-- BARBERS TABLE
-- =====================================================================

CREATE TABLE barbers (
    barber_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    barber_name VARCHAR(100) NOT NULL,
    barber_description TEXT NULL,
    barber_image VARCHAR(255) NULL,
    barber_status ENUM('Available', 'Busy', 'Offline') NOT NULL DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


-- =====================================================================
-- SERVICES TABLE (the 5 fixed packages)
-- =====================================================================

CREATE TABLE services (
    service_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(150) NOT NULL,
    service_description TEXT NULL,
    service_price DECIMAL(10,2) NOT NULL,
    estimated_duration_minutes INT UNSIGNED NOT NULL DEFAULT 30,
    service_image VARCHAR(255) NULL,
    service_status ENUM('Available', 'Unavailable') NOT NULL DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


-- =====================================================================
-- BARBER SCHEDULES TABLE
-- =====================================================================

CREATE TABLE barber_schedules (
    barber_schedule_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    barber_id INT UNSIGNED NOT NULL,
    schedule_day ENUM(
        'Monday', 'Tuesday', 'Wednesday', 'Thursday',
        'Friday', 'Saturday', 'Sunday'
    ) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    schedule_status ENUM('Available', 'Day Off') NOT NULL DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT foreign_key_barber_schedule_barber
        FOREIGN KEY (barber_id) REFERENCES barbers(barber_id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    UNIQUE KEY unique_barber_schedule (barber_id, schedule_day, start_time, end_time)
);


-- =====================================================================
-- HOLIDAYS TABLE (admin marks days the shop is fully closed)
-- =====================================================================

CREATE TABLE holidays (
    holiday_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    holiday_date DATE NOT NULL UNIQUE,
    holiday_name VARCHAR(150) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- =====================================================================
-- APPOINTMENTS TABLE
-- barber_id / appointment_start_time / appointment_end_time are
-- NULLABLE because a "Reservation" appointment does not have a
-- confirmed barber or exact time yet (admin assigns it later).
-- =====================================================================

CREATE TABLE appointments (
    appointment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_code VARCHAR(20) UNIQUE,

    customer_id INT UNSIGNED NOT NULL,
    barber_id INT UNSIGNED NULL,
    preferred_barber_id INT UNSIGNED NULL,
    service_id INT UNSIGNED NOT NULL,

    appointment_date DATE NOT NULL,
    appointment_start_time TIME NULL,
    appointment_end_time TIME NULL,
    requested_time TIME NULL,
    check_in_time DATETIME NULL,

    booking_type ENUM('Auto Booking', 'Reservation') NOT NULL DEFAULT 'Auto Booking',

    appointment_status ENUM(
        'Pending', 'Confirmed', 'Waiting',
        'Completed', 'Cancelled', 'No Show'
    ) NOT NULL DEFAULT 'Pending',

    -- Tracks the moment appointment_status last changed. Used to
    -- drive the live queue countdowns (10-minute Waiting grace
    -- period, and to auto-expire a no-show).
    status_updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

    customer_notes TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT foreign_key_appointment_customer
        FOREIGN KEY (customer_id) REFERENCES customers(customer_id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT foreign_key_appointment_barber
        FOREIGN KEY (barber_id) REFERENCES barbers(barber_id)
        ON DELETE SET NULL ON UPDATE CASCADE,

    CONSTRAINT foreign_key_appointment_preferred_barber
        FOREIGN KEY (preferred_barber_id) REFERENCES barbers(barber_id)
        ON DELETE SET NULL ON UPDATE CASCADE,

    CONSTRAINT foreign_key_appointment_service
        FOREIGN KEY (service_id) REFERENCES services(service_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
);


-- =====================================================================
-- PAYMENTS TABLE
-- =====================================================================

CREATE TABLE payments (
    payment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT UNSIGNED NOT NULL,

    payment_method ENUM('GCash', 'PayMaya', 'Cash') NOT NULL,
    payment_purpose ENUM('Reservation Fee', 'Full Payment') NOT NULL DEFAULT 'Full Payment',

    payment_amount DECIMAL(10,2) NOT NULL,
    payment_reference_number VARCHAR(100) NULL,
    payment_proof_image VARCHAR(255) NULL,

    payment_status ENUM('Pending', 'Verified', 'Rejected', 'Refunded') NOT NULL DEFAULT 'Pending',

    admin_notes VARCHAR(255) NULL,
    paid_at TIMESTAMP NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT foreign_key_payment_appointment
        FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id)
        ON DELETE CASCADE ON UPDATE CASCADE
);


-- =====================================================================
-- CONTACT MESSAGES TABLE (Contact Us page)
-- =====================================================================

CREATE TABLE contact_messages (
    contact_message_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email_address VARCHAR(150) NOT NULL,
    phone_number VARCHAR(20) NULL,
    subject VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    message_status ENUM('New', 'Read', 'Replied') NOT NULL DEFAULT 'New',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- =====================================================================
-- REFUND REQUESTS TABLE
-- Submitted by a customer who already paid (GCash/PayMaya/Cash verified)
-- but then cancelled their booking and wants their money back.
-- =====================================================================

CREATE TABLE refund_requests (
    refund_request_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    customer_id INT UNSIGNED NOT NULL,
    appointment_id INT UNSIGNED NULL,

    full_name VARCHAR(100) NOT NULL,
    email_address VARCHAR(150) NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    message TEXT NULL,
    payment_proof_image VARCHAR(255) NULL,

    request_status ENUM('Pending', 'Resolved') NOT NULL DEFAULT 'Pending',
    admin_notes VARCHAR(255) NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT foreign_key_refund_request_customer
        FOREIGN KEY (customer_id) REFERENCES customers(customer_id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT foreign_key_refund_request_appointment
        FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id)
        ON DELETE SET NULL ON UPDATE CASCADE
);


-- =====================================================================
-- SEED DATA: ADMINISTRATOR ACCOUNT
-- Phone Number : 09999999999
-- Password     : Admin123!
-- (Please change this password after your first login.)
-- =====================================================================

INSERT INTO customers
(full_name, phone_number, email_address, password_hash, customer_role, account_status, email_verified_at)
VALUES
('Shop Administrator', '09999999999', 'admin@thebarberco.com',
'$2y$10$Qa/azL1lBRL0/gLw2u/xJeoEsWarX6pf4nvyPo.rWB81hRWYIIte2',
'Administrator', 'Active', NOW());


-- =====================================================================
-- SEED DATA: BARBERS
-- =====================================================================

INSERT INTO barbers (barber_name, barber_description, barber_status)
VALUES
('Juan Dela Cruz', 'Senior barber specializing in modern and classic haircuts.', 'Available'),
('Mark Santos', 'Professional barber specializing in fades and beard grooming.', 'Available'),
('Carlo Reyes', 'Barber specializing in premium styling and modern cuts.', 'Available');


-- =====================================================================
-- SEED DATA: BARBER SCHEDULES (Monday - Saturday, closed Sunday)
-- =====================================================================

INSERT INTO barber_schedules (barber_id, schedule_day, start_time, end_time, schedule_status)
VALUES
(1, 'Monday',    '08:00:00', '19:00:00', 'Available'),
(1, 'Tuesday',   '08:00:00', '19:00:00', 'Available'),
(1, 'Wednesday', '08:00:00', '19:00:00', 'Available'),
(1, 'Thursday',  '08:00:00', '19:00:00', 'Available'),
(1, 'Friday',    '08:00:00', '19:00:00', 'Available'),
(1, 'Saturday',  '08:00:00', '19:00:00', 'Available'),

(2, 'Monday',    '08:00:00', '19:00:00', 'Available'),
(2, 'Tuesday',   '08:00:00', '19:00:00', 'Available'),
(2, 'Wednesday', '08:00:00', '19:00:00', 'Available'),
(2, 'Thursday',  '08:00:00', '19:00:00', 'Available'),
(2, 'Friday',    '08:00:00', '19:00:00', 'Available'),
(2, 'Saturday',  '08:00:00', '19:00:00', 'Available'),

(3, 'Monday',    '08:00:00', '19:00:00', 'Available'),
(3, 'Tuesday',   '08:00:00', '19:00:00', 'Available'),
(3, 'Wednesday', '08:00:00', '19:00:00', 'Available'),
(3, 'Thursday',  '08:00:00', '19:00:00', 'Available'),
(3, 'Friday',    '08:00:00', '19:00:00', 'Available'),
(3, 'Saturday',  '08:00:00', '19:00:00', 'Available');


-- =====================================================================
-- SEED DATA: 5 SERVICE PACKAGES (as requested)
-- =====================================================================

INSERT INTO services (service_name, service_description, service_price, estimated_duration_minutes)
VALUES
('Haircut',
 'A clean and professional haircut customized to your preferred style.',
 150.00, 30),

('Haircut + Beard',
 'Complete haircut paired with professional beard grooming and shaping.',
 200.00, 45),

('Haircut with Pomade and Beard Shave',
 'Haircut, clean beard shave, and finished off with premium pomade styling.',
 250.00, 45),

('Haircut with Pomade, Shampoo, and Beard Shave',
 'Full haircut, relaxing shampoo wash, beard shave, and pomade styling.',
 300.00, 60),

('Premium Package',
 'Our complete grooming experience: haircut, shampoo, beard shave, pomade styling, and hot towel finish.',
 350.00, 75);
