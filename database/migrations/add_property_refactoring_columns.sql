-- Migration: Add new columns to room_blocks for property refactoring
-- Date: 2026-04-03
-- This adds the missing columns that were defined in schema.sql

ALTER TABLE room_blocks
    ADD COLUMN IF NOT EXISTS price_min NUMERIC(12,2),
    ADD COLUMN IF NOT EXISTS price_max NUMERIC(12,2),
    ADD COLUMN IF NOT EXISTS area NUMERIC(8,1),
    ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending','approved','rejected'));

-- Also ensure block_images table exists
CREATE TABLE IF NOT EXISTS block_images (
    id SERIAL PRIMARY KEY,
    block_id INT NOT NULL REFERENCES room_blocks(id) ON DELETE CASCADE,
    image_path VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Update existing room_blocks with default status if they don't have one
UPDATE room_blocks SET status = 'approved' WHERE status IS NULL;

-- Verify the migration
SELECT column_name, data_type 
FROM information_schema.columns 
WHERE table_name = 'room_blocks' 
ORDER BY ordinal_position;
