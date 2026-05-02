-- =============================================
-- TokenMed — Hospital Reception Management System
-- Database Schema v2.0
-- =============================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET foreign_key_checks = 0;

CREATE DATABASE IF NOT EXISTS `token_med`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `token_med`;

-- =============================================
-- ROLES
-- =============================================

CREATE TABLE `roles` (
  `id`   int(11)     NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT 'admin, receptionist',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` VALUES (1, 'admin'), (2, 'receptionist');

-- =============================================
-- USERS
-- =============================================

CREATE TABLE `users` (
  `id`            int(11)                          NOT NULL AUTO_INCREMENT,
  `role_id`       int(11)                          NOT NULL,
  `first_name`    varchar(100)                     NOT NULL,
  `last_name`     varchar(100)                     NOT NULL,
  `email`         varchar(150)                     NOT NULL,
  `phone`         varchar(20)                      DEFAULT NULL,
  `password`      varchar(255)                     NOT NULL COMMENT 'bcrypt hashed',
  `gender`        enum('male','female','other')     DEFAULT NULL,
  `profile_image` varchar(255)                     DEFAULT NULL,
  `is_active`     tinyint(1)                       DEFAULT 1,
  `last_seen`     datetime                         DEFAULT NULL COMMENT 'for online status tracking',
  `created_at`    timestamp                        NOT NULL DEFAULT current_timestamp(),
  `updated_at`    timestamp                        NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- DOCTORS
-- =============================================

CREATE TABLE `doctors` (
  `id`             int(11)                          NOT NULL AUTO_INCREMENT,
  `name`           varchar(150)                     NOT NULL,
  `email`          varchar(150)                     DEFAULT NULL,
  `phone`          varchar(20)                      DEFAULT NULL,
  `gender`         enum('male','female','other')     DEFAULT NULL,
  `designation`    varchar(100)                     DEFAULT NULL,
  `specialization` varchar(150)                     NOT NULL,
  `fee`            decimal(10,2)                    NOT NULL DEFAULT 0.00,
  `is_active`      tinyint(1)                       DEFAULT 1,
  `created_at`     timestamp                        NOT NULL DEFAULT current_timestamp(),
  `updated_at`     timestamp                        NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TOKENS
-- =============================================

CREATE TABLE `tokens` (
  `token_number` int(11)  NOT NULL COMMENT 'per-doctor per-day sequential, resets to 1 each day',
  `doctor_id`    int(11)  NOT NULL,
  `token_date`   date     NOT NULL,
  `id`           int(11)  NOT NULL AUTO_INCREMENT,
  `created_at`   timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token_doctor_date` (`token_number`, `doctor_id`, `token_date`),
  FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT 'token_number + doctor_id + token_date = unique. Resets per doctor per day.';

-- =============================================
-- PATIENTS
-- =============================================

CREATE TABLE `patients` (
  `id`               int(11)                      NOT NULL AUTO_INCREMENT,
  `token_id`         int(11)                      NOT NULL,
  `serial_number`    int(11)                      NOT NULL COMMENT 'mirrors token_number',
  `name`             varchar(150)                 NOT NULL,
  `age`              tinyint(3) UNSIGNED          DEFAULT NULL,
  `phone`            varchar(20)                  DEFAULT NULL,
  `gender`           enum('male','female','other') NOT NULL,
  `address`          text                         DEFAULT NULL,
  `doctor_id`        int(11)                      NOT NULL,
  `receptionist_id`  int(11)                      NOT NULL,
  `visit_date`       date                         NOT NULL,
  `visit_time`       time                         NOT NULL,
  `notes`            text                         DEFAULT NULL,
  `created_at`       timestamp                    NOT NULL DEFAULT current_timestamp(),
  `updated_at`       timestamp                    NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`token_id`)        REFERENCES `tokens`(`id`)  ON DELETE CASCADE,
  FOREIGN KEY (`doctor_id`)       REFERENCES `doctors`(`id`),
  FOREIGN KEY (`receptionist_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- PAYMENTS
-- =============================================

CREATE TABLE `payments` (
  `id`             int(11)                                     NOT NULL AUTO_INCREMENT,
  `patient_id`     int(11)                                     NOT NULL,
  `amount`         decimal(10,2)                               NOT NULL DEFAULT 0.00,
  `status`         enum('paid','unpaid')                       NOT NULL DEFAULT 'unpaid',
  `payment_method` enum('cash','card','insurance','online')    DEFAULT NULL,
  `created_at`     timestamp                                   NOT NULL DEFAULT current_timestamp(),
  `updated_at`     timestamp                                   NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payments_patient` (`patient_id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- ROOMS
-- =============================================

CREATE TABLE `rooms` (
  `id`          int(11)                           NOT NULL AUTO_INCREMENT,
  `room_number` varchar(20)                       NOT NULL,
  `room_type`   enum('general','private','icu')   NOT NULL,
  `daily_fee`   decimal(10,2)                     NOT NULL DEFAULT 0.00,
  `is_active`   tinyint(1)                        DEFAULT 1 COMMENT '0 = under maintenance/disabled',
  `created_at`  timestamp                         NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- ADMISSIONS
-- =============================================

CREATE TABLE `admissions` (
  `id`                    int(11)                                              NOT NULL AUTO_INCREMENT,
  `patient_name`          varchar(150)                                         NOT NULL,
  `guardian_name`         varchar(150)                                         NOT NULL COMMENT 'Father or Husband name',
  `gender`                enum('male','female','other')                         NOT NULL,
  `address`               text                                                 NOT NULL,
  `doctor_id`             int(11)                                              NOT NULL,
  `room_id`               int(11)                                              NOT NULL,
  `disease_name`          varchar(150)                                         NOT NULL,
  `disease_treatment_cost` decimal(10,2)                                       NOT NULL DEFAULT 0.00,
  `admission_reason`      enum('operation','observation','emergency','treatment','other') NOT NULL,
  `room_fee_per_day`      decimal(10,2)                                        NOT NULL COMMENT 'snapshot of room fee at admission',
  `admitted_at`           datetime                                             NOT NULL,
  `discharged_at`         datetime                                             DEFAULT NULL,
  `status`                enum('admitted','discharged')                         NOT NULL DEFAULT 'admitted',
  `created_by`            int(11)                                              NOT NULL COMMENT 'user_id of admin or receptionist',
  `created_at`            timestamp                                            NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`doctor_id`)  REFERENCES `doctors`(`id`),
  FOREIGN KEY (`room_id`)    REFERENCES `rooms`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- SETTINGS
-- =============================================

CREATE TABLE `settings` (
  `id`            int(11)      NOT NULL AUTO_INCREMENT,
  `setting_key`   varchar(100) NOT NULL,
  `setting_value` text         DEFAULT NULL,
  `updated_at`    timestamp    NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('hospital_name',         'Hospital Management System'),
('hospital_name_urdu',    'ہسپتال مینجمنٹ سسٹم'),
('hospital_address',      ''),
('hospital_address_urdu', ''),
('contact_email',         'contact@hospital.com'),
('contact_phone',         ''),
('token_prefix',          'TKN'),
('token_reset_time',      '00:00'),
('max_tokens_per_day',    '200'),
('currency_symbol',       'Rs.'),
('receipt_show_urdu',     '1'),
('sound_effects',         '1'),
('online_threshold_mins', '5'),
('auto_logout_mins',      '60'),
('email_notifications',   '1'),
('sms_notifications',     '0');

-- =============================================
-- AUDIT LOGS
-- =============================================

CREATE TABLE `audit_logs` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `user_id`    int(11)      DEFAULT NULL,
  `action`     varchar(255) NOT NULL,
  `details`    text         DEFAULT NULL COMMENT 'JSON or plain text',
  `ip_address` varchar(50)  DEFAULT NULL,
  `created_at` timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
