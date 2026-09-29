-- =====================================================================
-- MIGRATION v5: Email verification for registration
--   - Adds email_verified_at / email_verification_token /
--     email_verification_sent_at to customers
--   - Marks all EXISTING accounts as already verified, so nobody who
--     already registered gets locked out
--
-- Run this ONLY if you already have an existing barbershop_database
-- with data you want to keep. If starting fresh, just re-import
-- schema.sql instead.
-- =====================================================================

USE barbershop_database;

ALTER TABLE customers
    ADD COLUMN email_verified_at TIMESTAMP NULL AFTER date_of_birth,
    ADD COLUMN email_verification_token VARCHAR(64) NULL AFTER email_verified_at,
    ADD COLUMN email_verification_sent_at TIMESTAMP NULL AFTER email_verification_token;

-- Anyone who already had an account before this update is grandfathered
-- in as verified, so this update doesn't lock out existing customers.
UPDATE customers
SET email_verified_at = NOW()
WHERE email_verified_at IS NULL;
