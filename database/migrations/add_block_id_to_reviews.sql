-- Allow property-level reviews (for mini_house / full_house that have no room records)
ALTER TABLE reviews ALTER COLUMN room_id DROP NOT NULL;
ALTER TABLE reviews ADD COLUMN IF NOT EXISTS block_id INT REFERENCES room_blocks(id) ON DELETE CASCADE;
