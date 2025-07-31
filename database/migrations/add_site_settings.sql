-- Create the site_settings table
CREATE TABLE `site_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_name` varchar(255) NOT NULL,
  `setting_value` text,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_name` (`setting_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default settings
INSERT INTO `site_settings` (`setting_name`, `setting_value`) VALUES
('site_title', 'RadioGrab'),
('site_tagline', 'Your Personal Radio Recorder'),
('site_logo', '/assets/images/radiograb-logo.png'),
('brand_color', '#343a40'),
('footer_text', '&copy; 2025 RadioGrab. All rights reserved.');
