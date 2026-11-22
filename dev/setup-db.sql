-- Local dev database for 2 cans 1 string. Run as root (see how to run.txt §2a); then db/schema.sql.
-- The password must match config.local.php. Safe to re-run: it drops and recreates the database.

DROP DATABASE IF EXISTS cans;
CREATE DATABASE cans CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'cans'@'localhost' IDENTIFIED BY 'cans-local-dev';
CREATE USER IF NOT EXISTS 'cans'@'127.0.0.1' IDENTIFIED BY 'cans-local-dev';
GRANT ALL PRIVILEGES ON cans.* TO 'cans'@'localhost';
GRANT ALL PRIVILEGES ON cans.* TO 'cans'@'127.0.0.1';
USE cans;
