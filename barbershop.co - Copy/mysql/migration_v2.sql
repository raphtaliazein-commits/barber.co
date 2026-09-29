-- =====================================================================
-- MIGRATION: adds the columns needed for the new features
-- (age verification for online payments + barber selection)
--
-- Run this ONLY if you already have an existing barbershop_database
-- with data you want to keep.
--
-- If you don't mind starting fresh, you can just re-import schema.sql
-- instead — this file is not needed in that case.
-- =====================================================================

USE barbershop_database;

ALTER TABLE customers
    ADD COLUMN date_of_birth DATE NULL AFTER profile_image;

ALTER TABLE appointments
    ADD COLUMN preferred_barber_id INT UNSIGNED NULL AFTER barber_id;

ALTER TABLE appointments
    ADD CONSTRAINT foreign_key_appointment_preferred_barber
        FOREIGN KEY (preferred_barber_id) REFERENCES barbers(barber_id)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- After running this, any customer who registered before this update
-- will have date_of_birth = NULL, which means they will only be able
-- to pay via Cash until they add their birthdate under "My Profile".
