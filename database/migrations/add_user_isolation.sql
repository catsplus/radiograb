-- Add user isolation to shows table
-- This allows each user to have their own collection of shows

-- Add user_id column to shows table
ALTER TABLE shows ADD COLUMN user_id INT NOT NULL DEFAULT 1;

-- Add foreign key constraint
ALTER TABLE shows ADD CONSTRAINT fk_shows_user_id FOREIGN KEY (user_id) REFERENCES users(id);

-- Add index for performance
ALTER TABLE shows ADD INDEX idx_shows_user_id (user_id);

-- Set existing shows to admin user (id=1) by default
UPDATE shows SET user_id = 1 WHERE user_id = 1;

-- Update shows to belong to the user who owns the station
UPDATE shows s 
JOIN stations st ON s.station_id = st.id 
SET s.user_id = st.user_id 
WHERE st.user_id IS NOT NULL;