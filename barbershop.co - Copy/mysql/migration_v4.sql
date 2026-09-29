-- =====================================================================
-- MIGRATION v4: Refund requests + notifications
--   - Adds the refund_requests table (customer refund/cancellation
--     requests after a verified payment)
--
-- Run this ONLY if you already have an existing barbershop_database
-- with data you want to keep. If starting fresh, just re-import
-- schema.sql instead.
-- =====================================================================

USE barbershop_database;

CREATE TABLE IF NOT EXISTS refund_requests (
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
