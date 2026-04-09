-- Migration: Add district_id and ward_id foreign keys to room_blocks
-- Date: 2026-04-04

ALTER TABLE room_blocks
    ADD COLUMN IF NOT EXISTS district_id INT REFERENCES districts(id) ON DELETE SET NULL,
    ADD COLUMN IF NOT EXISTS ward_id     INT REFERENCES wards(id)     ON DELETE SET NULL;

-- Backfill existing rows: attempt to match the text address to district/ward
-- (No-op if districts/wards cannot be matched automatically)
-- Manual backfill can be done via admin UI after running this migration.
