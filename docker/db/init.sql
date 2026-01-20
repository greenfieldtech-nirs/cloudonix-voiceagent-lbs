-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS cloudonix_boilerplate CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Grant permissions to laravel user
GRANT ALL PRIVILEGES ON cloudonix_boilerplate.* TO 'laravel'@'%';

-- Allow root user to connect from any host
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' IDENTIFIED BY 'password';
FLUSH PRIVILEGES;