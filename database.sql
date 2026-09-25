-- ========================================================
-- Community Health Record System Database Schema
-- Database Name: chr_db
-- Compatible with: MySQL 5.7+, MySQL 8.0+, MariaDB, TiDB, Aiven, Railway
-- ========================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Users Table (Patients, Doctors, Admins)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('patient', 'doctor', 'admin') NOT NULL DEFAULT 'patient',
  `phone` VARCHAR(50) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `specialization` VARCHAR(255) DEFAULT NULL,
  `qualification` VARCHAR(255) DEFAULT NULL,
  `location` VARCHAR(255) DEFAULT NULL,
  `hospital_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Hospitals Table (Hospitals and Laboratories)
CREATE TABLE IF NOT EXISTS `hospitals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `type` ENUM('hospital', 'laboratory') NOT NULL DEFAULT 'hospital',
  `name` VARCHAR(255) NOT NULL,
  `address` TEXT NOT NULL,
  `phone` VARCHAR(50) NOT NULL,
  `open_time` VARCHAR(50) DEFAULT NULL,
  `close_time` VARCHAR(50) DEFAULT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `latitude` DECIMAL(10, 8) DEFAULT NULL,
  `longitude` DECIMAL(11, 8) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Doctor Profiles Table
CREATE TABLE IF NOT EXISTS `doctor_profiles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `doctor_id` INT NOT NULL UNIQUE,
  `address` TEXT DEFAULT NULL,
  `specialization` VARCHAR(255) DEFAULT NULL,
  `qualification` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`doctor_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Doctor Leaves Table
CREATE TABLE IF NOT EXISTS `doctor_leaves` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `doctor_id` INT NOT NULL,
  `leave_date` DATE NOT NULL,
  `reason` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`doctor_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Health Records Table
CREATE TABLE IF NOT EXISTS `records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `record_date` DATE NOT NULL,
  `file_path` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Appointments Table
CREATE TABLE IF NOT EXISTS `appointments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `patient_id` INT NOT NULL,
  `doctor_id` INT NOT NULL,
  `appt_date` DATE NOT NULL,
  `appt_time` VARCHAR(50) DEFAULT 'Any Time',
  `reason` TEXT DEFAULT NULL,
  `status` ENUM('pending', 'accepted', 'rejected', 'rescheduled') NOT NULL DEFAULT 'pending',
  `rescheduled_date` DATE DEFAULT NULL,
  `rescheduled_time` TIME DEFAULT NULL,
  `reschedule_msg` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`doctor_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Lab Appointments Table
CREATE TABLE IF NOT EXISTS `lab_appointments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `patient_id` INT NOT NULL,
  `lab_id` INT NOT NULL,
  `test_name` VARCHAR(255) NOT NULL,
  `appointment_date` DATE NOT NULL,
  `status` ENUM('pending', 'accepted', 'rejected') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`lab_id`) REFERENCES `hospitals`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Lab Reports Table
CREATE TABLE IF NOT EXISTS `lab_reports` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `appointment_id` INT NOT NULL,
  `patient_id` INT NOT NULL,
  `lab_id` INT NOT NULL,
  `report_file` VARCHAR(255) NOT NULL,
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`appointment_id`) REFERENCES `lab_appointments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`lab_id`) REFERENCES `hospitals`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Notifications Table
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `message` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ========================================================
-- DEFAULT SEED DATA
-- Default Admin Account:
-- Email: admin@chr.com
-- Password: password123
-- ========================================================
INSERT INTO `users` (`name`, `email`, `password`, `role`, `phone`, `address`)
VALUES ('System Admin', 'admin@chr.com', '$2y$10$4Uqn8GeP1Wls05pI/W6fL.MMHqUjCTDoB2YASRSoffTTRBaa5zijq', 'admin', '1234567890', 'Headquarters')
ON DUPLICATE KEY UPDATE `id`=`id`;
