-- Migration: Add amenity and detail columns to room_blocks
-- Date: 2026-04-04

ALTER TABLE room_blocks
    ADD COLUMN IF NOT EXISTS street_id       INT REFERENCES streets(id) ON DELETE SET NULL,
    ADD COLUMN IF NOT EXISTS has_wifi        BOOLEAN        DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS has_ac          BOOLEAN        DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS has_parking     BOOLEAN        DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS allow_pet       BOOLEAN        DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS allow_cooking   BOOLEAN        DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS electric_price  NUMERIC(10,2)  DEFAULT 0,
    ADD COLUMN IF NOT EXISTS water_price     NUMERIC(10,2)  DEFAULT 0,
    ADD COLUMN IF NOT EXISTS internet_price  NUMERIC(10,2)  DEFAULT 0,
    ADD COLUMN IF NOT EXISTS deposit_months  INT            DEFAULT 1,
    ADD COLUMN IF NOT EXISTS floor           INT            DEFAULT 1,
    ADD COLUMN IF NOT EXISTS max_people      INT            DEFAULT 1,
    ADD COLUMN IF NOT EXISTS num_bedrooms    INT,
    ADD COLUMN IF NOT EXISTS num_bathrooms   INT,
    ADD COLUMN IF NOT EXISTS contact_phone   VARCHAR(20);
