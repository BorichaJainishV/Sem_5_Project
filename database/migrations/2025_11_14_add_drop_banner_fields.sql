-- Adds drop scheduling metadata to the flash_banners table.
-- Run with: mysql mystic_clothing < 2025_11_14_add_drop_banner_fields.sql

USE mystic_clothing;

CREATE TABLE IF NOT EXISTS flash_banners (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    message VARCHAR(255) NOT NULL,
    subtext TEXT NULL,
    cta VARCHAR(160) NULL,
    href VARCHAR(255) NULL,
    badge VARCHAR(120) NULL,
    variant ENUM('promo', 'info', 'alert') NOT NULL DEFAULT 'promo',
    dismissible TINYINT(1) NOT NULL DEFAULT 0,
    mode ENUM('standard', 'drop') NOT NULL DEFAULT 'standard',
    drop_label VARCHAR(120) NULL,
    drop_slug VARCHAR(120) NULL,
    schedule_start DATETIME NULL,
    schedule_end DATETIME NULL,
    start_at DATETIME NULL,
    end_at DATETIME NULL,
    countdown_enabled TINYINT(1) NOT NULL DEFAULT 0,
    countdown_target DATETIME NULL,
    countdown_label VARCHAR(120) NULL,
    countdown_mode VARCHAR(32) NULL,
    visibility JSON NULL,
    waitlist_enabled TINYINT(1) NOT NULL DEFAULT 0,
    waitlist_slug VARCHAR(120) NULL,
    waitlist_button_label VARCHAR(160) NULL,
    waitlist_success_copy VARCHAR(255) NULL,
    drop_teaser VARCHAR(255) NULL,
    drop_story TEXT NULL,
    drop_highlights JSON NULL,
    drop_access_notes TEXT NULL,
    drop_media_url VARCHAR(255) NULL,
    promotion_payload JSON NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_flash_banners_drop_slug (drop_slug)
);

ALTER TABLE flash_banners
    ADD COLUMN IF NOT EXISTS drop_label VARCHAR(120) NULL AFTER message;

ALTER TABLE flash_banners
    ADD COLUMN IF NOT EXISTS drop_slug VARCHAR(120) NULL AFTER drop_label;

ALTER TABLE flash_banners
    ADD COLUMN IF NOT EXISTS start_at DATETIME NULL AFTER schedule_start;

ALTER TABLE flash_banners
    ADD COLUMN IF NOT EXISTS end_at DATETIME NULL AFTER start_at;

ALTER TABLE flash_banners
    ADD COLUMN IF NOT EXISTS countdown_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER countdown_target;

ALTER TABLE flash_banners
    ADD COLUMN IF NOT EXISTS visibility JSON NULL AFTER countdown_enabled;

ALTER TABLE flash_banners
    ADD COLUMN IF NOT EXISTS waitlist_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER visibility;

-- Backfill existing rows so the new aliases match stored timestamps.
UPDATE flash_banners
SET
    start_at = COALESCE(schedule_start, start_at),
    end_at = COALESCE(schedule_end, end_at),
    countdown_enabled = CASE
        WHEN countdown_target IS NOT NULL THEN 1
        ELSE countdown_enabled
    END
WHERE 1 = 1;
