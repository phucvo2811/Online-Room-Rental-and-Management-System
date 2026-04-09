-- Migration: Add Google Maps location columns to room_blocks table
-- Run this script once against your database

ALTER TABLE room_blocks
    ADD COLUMN IF NOT EXISTS latitude    DOUBLE PRECISION,
    ADD COLUMN IF NOT EXISTS longitude   DOUBLE PRECISION,
    ADD COLUMN IF NOT EXISTS map_address VARCHAR(500);

COMMENT ON COLUMN room_blocks.latitude    IS 'GPS latitude selected via Google Maps picker';
COMMENT ON COLUMN room_blocks.longitude   IS 'GPS longitude selected via Google Maps picker';
COMMENT ON COLUMN room_blocks.map_address IS 'Formatted address returned by Google Places API';
