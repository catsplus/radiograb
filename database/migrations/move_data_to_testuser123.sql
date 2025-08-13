-- Move all existing stations, shows, and recordings to testuser123 (user_id = 3)
-- This ensures testuser123 becomes the owner of all current data

-- Update all stations to belong to testuser123
UPDATE stations SET user_id = 3 WHERE user_id != 3;

-- Add user_id column to shows table if it doesn't exist
-- (This might have been blocked earlier due to table locks)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'shows' 
     AND table_schema = 'radiograb' 
     AND column_name = 'user_id') > 0,
    'SELECT "user_id column already exists"',
    'ALTER TABLE shows ADD COLUMN user_id INT DEFAULT 3'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Set all shows to belong to testuser123
UPDATE shows SET user_id = 3 WHERE user_id IS NULL OR user_id != 3;

-- Add foreign key constraint if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
     WHERE table_name = 'shows' 
     AND table_schema = 'radiograb' 
     AND constraint_name = 'fk_shows_user_id') > 0,
    'SELECT "Foreign key already exists"',
    'ALTER TABLE shows ADD CONSTRAINT fk_shows_user_id FOREIGN KEY (user_id) REFERENCES users(id)'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_name = 'shows' 
     AND table_schema = 'radiograb' 
     AND index_name = 'idx_shows_user_id') > 0,
    'SELECT "Index already exists"',
    'ALTER TABLE shows ADD INDEX idx_shows_user_id (user_id)'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;