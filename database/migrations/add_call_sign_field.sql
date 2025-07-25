-- Add call_sign field to stations table
-- Migration: add_call_sign_field

ALTER TABLE stations ADD COLUMN call_sign VARCHAR(4) NULL COMMENT '4-letter call sign for station (e.g., WEHC)' AFTER name;

-- Add index on call_sign for performance
ALTER TABLE stations ADD INDEX idx_call_sign (call_sign);

-- Update existing stations with default call signs based on name
-- These should be updated manually with actual call signs
UPDATE stations SET call_sign = 'TEST' WHERE call_sign IS NULL;