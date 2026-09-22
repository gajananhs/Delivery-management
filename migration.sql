-- Delivery Management: migration for the 4 requested changes
-- Run this once against the live Hostinger database (u924350731_Delivery).
-- All statements are safe to re-run / no-ops if already applied.

-- 1 & 2. Driver assignment + one-OTP-per-driver-per-day
-- New table: stores exactly one pincode per (driver_id, calendar date).
-- assign_task_api.php reads/writes this instead of generating a fresh
-- random pincode per task. Does not touch drivers.lat/lng/location_updated_at
-- used by GPS tracking - that stays keyed on driver_id only.
CREATE TABLE IF NOT EXISTS driver_daily_otp (
  driver_id   VARCHAR(64) NOT NULL,
  otp_date    DATE NOT NULL,
  pincode     CHAR(4) NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (driver_id, otp_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Driver registration phone number
-- drivers.phone already exists and is already used by add_driver_api.php /
-- update_driver_api.php / get_fleet_registries_api.php. This is only a
-- safety net in case this specific database was never migrated to have it.
-- (MySQL 8.0.29+ required for "ADD COLUMN IF NOT EXISTS" - if your Hostinger
-- MySQL is older and this line errors, first run:
--   SHOW COLUMNS FROM drivers LIKE 'phone';
-- and only run the ALTER below if that returns no rows.)
ALTER TABLE drivers ADD COLUMN IF NOT EXISTS phone VARCHAR(20) NULL AFTER name;

-- 4. Customer phone number visible to the driver
-- tasks.customer is free text (not a foreign key to `customers`), so the
-- phone is captured directly on the task at creation time.
ALTER TABLE tasks ADD COLUMN IF NOT EXISTS customer_phone VARCHAR(20) NULL AFTER customer;

-- 5. Items tab: item master list + per-task item lines (what's being
-- collected/supplied on each delivery/collection stop). item_code is
-- optional so a one-off item can still be typed in without pre-registering
-- it first, matching how `customer` already works.
CREATE TABLE IF NOT EXISTS items (
  code        VARCHAR(32) NOT NULL,
  name        VARCHAR(255) NOT NULL,
  unit        VARCHAR(20) NULL,
  status      VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS task_items (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  task_id     VARCHAR(64) NOT NULL,
  item_code   VARCHAR(32) NULL,
  item_name   VARCHAR(255) NOT NULL,
  quantity    DECIMAL(12,2) NOT NULL DEFAULT 1,
  unit        VARCHAR(20) NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY task_id_idx (task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Whole "items list" PDF upload on the dispatch form, as an alternative
-- to typing individual item rows - see api/upload_task_pdf_api.php, which
-- saves the file under /uploads/task_pdfs/ and returns this URL.
ALTER TABLE tasks ADD COLUMN IF NOT EXISTS items_pdf_url VARCHAR(500) NULL;
