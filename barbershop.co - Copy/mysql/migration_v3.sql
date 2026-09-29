-- =====================================================================
-- MIGRATION v3: Live Queue overhaul
--   - Removes the "Ongoing" status (merged into "Waiting")
--   - Adds status_updated_at (drives the live countdown timers)
--
-- Run this ONLY if you already have an existing barbershop_database
-- with data you want to keep. If starting fresh, just re-import
-- schema.sql instead.
-- =====================================================================

USE barbershop_database;

-- Move any existing "Ongoing" appointments into "Waiting" first,
-- since the ENUM change below would otherwise reject them.
UPDATE appointments
SET appointment_status = 'Waiting'
WHERE appointment_status = 'Ongoing';

ALTER TABLE appointments
    MODIFY COLUMN appointment_status ENUM(
        'Pending', 'Confirmed', 'Waiting',
        'Completed', 'Cancelled', 'No Show'
    ) NOT NULL DEFAULT 'Pending';

ALTER TABLE appointments
    ADD COLUMN status_updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER appointment_status;

-- Backfill: use each row's created_at as a starting point so the
-- 10-minute Waiting cooldown doesn't instantly expire everything
-- that already existed before this update.
UPDATE appointments
SET status_updated_at = created_at
WHERE status_updated_at IS NULL;
