-- LOFO: Lost and Found Management System
-- Database Schema for Union Christian College, Aluva (UCC - MCA)

CREATE DATABASE IF NOT EXISTS `lofo_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `lofo_db`;

-- Users Table
CREATE TABLE IF NOT EXISTS `users` (
    `user_id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `phone` VARCHAR(20) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('student', 'faculty', 'admin') NOT NULL DEFAULT 'student',
    `department` VARCHAR(100) DEFAULT NULL,
    `year_or_designation` VARCHAR(50) DEFAULT NULL,
    `account_status` ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Categories Table
CREATE TABLE IF NOT EXISTS `categories` (
    `category_id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `icon` VARCHAR(50) DEFAULT 'fa-box',
    `description` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Campus Locations Table
CREATE TABLE IF NOT EXISTS `locations` (
    `location_id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `type` VARCHAR(50) DEFAULT 'Academic',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Lost Items Table
CREATE TABLE IF NOT EXISTS `lost_items` (
    `lost_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `item_name` VARCHAR(100) NOT NULL,
    `category_id` INT NOT NULL,
    `description` TEXT NOT NULL,
    `location_lost` VARCHAR(150) NOT NULL,
    `date_lost` DATE NOT NULL,
    `image_path` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('Lost', 'Matched', 'Returned') NOT NULL DEFAULT 'Lost',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_lost_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_lost_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Found Items Table
CREATE TABLE IF NOT EXISTS `found_items` (
    `found_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `item_name` VARCHAR(100) NOT NULL,
    `category_id` INT NOT NULL,
    `description` TEXT NOT NULL,
    `location_found` VARCHAR(150) NOT NULL,
    `date_found` DATE NOT NULL,
    `image_path` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('Available', 'Claimed', 'Returned') NOT NULL DEFAULT 'Available',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_found_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_found_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Claims & Ownership Verification Table
CREATE TABLE IF NOT EXISTS `claims` (
    `claim_id` INT AUTO_INCREMENT PRIMARY KEY,
    `found_id` INT NOT NULL,
    `lost_id` INT DEFAULT NULL,
    `user_id` INT NOT NULL,
    `proof` TEXT NOT NULL,
    `proof_image` VARCHAR(255) DEFAULT NULL,
    `stage1_answers` TEXT DEFAULT NULL,
    `claim_date` DATE NOT NULL,
    `status` ENUM('Pending', 'Under Review', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
    `admin_remark` TEXT DEFAULT NULL,
    `verified_by` INT DEFAULT NULL,
    `verification_date` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_claim_found` FOREIGN KEY (`found_id`) REFERENCES `found_items` (`found_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_claim_lost` FOREIGN KEY (`lost_id`) REFERENCES `lost_items` (`lost_id`) ON DELETE SET NULL,
    CONSTRAINT `fk_claim_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_claim_verifier` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Smart Item Matches Table
CREATE TABLE IF NOT EXISTS `item_matches` (
    `match_id` INT AUTO_INCREMENT PRIMARY KEY,
    `lost_id` INT NOT NULL,
    `found_id` INT NOT NULL,
    `confidence_score` INT NOT NULL,
    `match_reasons` TEXT DEFAULT NULL,
    `status` ENUM('Pending Review', 'Confirmed', 'Dismissed') NOT NULL DEFAULT 'Pending Review',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_match_lost` FOREIGN KEY (`lost_id`) REFERENCES `lost_items` (`lost_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_match_found` FOREIGN KEY (`found_id`) REFERENCES `found_items` (`found_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Notifications Table
CREATE TABLE IF NOT EXISTS `notifications` (
    `notification_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `type` VARCHAR(50) NOT NULL DEFAULT 'General',
    `link` VARCHAR(255) DEFAULT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB;
