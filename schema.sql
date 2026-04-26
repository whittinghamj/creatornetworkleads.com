-- ============================================================
-- CreatorNetworkLeads.com – Additional Database Schema
-- Run this SQL against the existing tiktokcreatorleads database
-- The `creators` table already exists – do NOT re-create it.
-- ============================================================

-- Users table (customers + admins)
CREATE TABLE IF NOT EXISTS `users` (
  `id`           int(11) unsigned NOT NULL AUTO_INCREMENT,
  `created_at`   datetime         DEFAULT CURRENT_TIMESTAMP,
  `name`         varchar(100)     NOT NULL,
  `email`        varchar(255)     NOT NULL,
  `password`     varchar(255)     NOT NULL,
  `company`      varchar(100)     DEFAULT NULL,
  `phone`        varchar(30)      DEFAULT NULL,
  `signup_ip`    varchar(45)      DEFAULT NULL,
  `status`       enum('active','inactive','pending') DEFAULT 'active',
  `role`         enum('customer','admin')            DEFAULT 'customer',
  `last_login`   datetime         DEFAULT NULL,
  `last_login_ip` varchar(45)     DEFAULT NULL,
  `notes`        text             DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Packages table (customer subscription plans)
CREATE TABLE IF NOT EXISTS `packages` (
  `id`               int(11) unsigned NOT NULL AUTO_INCREMENT,
  `created_at`       datetime         DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       datetime         DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `name`             varchar(100)     NOT NULL,
  `description`      text             DEFAULT NULL,
  `leads_per_day`    int(11)          NOT NULL DEFAULT 0,
  `price_per_month`  decimal(10,2)    NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add package relation on users (run once)
ALTER TABLE `users`
  ADD COLUMN `package_id` int(11) unsigned DEFAULT NULL,
  ADD KEY `idx_users_package_id` (`package_id`);

-- Tracks how many leads were auto-assigned per customer each day
CREATE TABLE IF NOT EXISTS `customer_daily_lead_assignments` (
  `id`             int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id`        int(11) unsigned NOT NULL,
  `assign_date`    date             NOT NULL,
  `assigned_count` int(11)          NOT NULL DEFAULT 0,
  `created_at`     datetime         DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     datetime         DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_day` (`user_id`, `assign_date`),
  KEY `idx_assign_date` (`assign_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Message templates library (admin-managed, customer-viewable)
CREATE TABLE IF NOT EXISTS `message_templates` (
  `id`            int(11) unsigned NOT NULL AUTO_INCREMENT,
  `created_at`    datetime         DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    datetime         DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `title`         varchar(160)     NOT NULL,
  `category`      varchar(80)      DEFAULT NULL,
  `content`       text             NOT NULL,
  `is_published`  tinyint(1)       NOT NULL DEFAULT 1,
  `sort_order`    int(11)          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_templates_published` (`is_published`),
  KEY `idx_templates_category` (`category`),
  KEY `idx_templates_sort` (`sort_order`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- User IP audit history (signup/login events)
CREATE TABLE IF NOT EXISTS `user_ip_audit` (
  `id`          int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id`     int(11) unsigned NOT NULL,
  `event_type`  varchar(20)      NOT NULL,
  `ip_address`  varchar(45)      DEFAULT NULL,
  `created_at`  datetime         DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_ip_audit_user` (`user_id`),
  KEY `idx_user_ip_audit_event` (`event_type`),
  KEY `idx_user_ip_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Initial admin account
-- Run setup.php to create the first admin interactively, OR
-- generate a hash manually and paste below:
--   php -r "echo password_hash('YourPassword123!', PASSWORD_DEFAULT);"
-- Then uncomment and run:
-- INSERT INTO `users` (`name`, `email`, `password`, `role`, `status`)
-- VALUES ('Admin', 'admin@creatornetworkleads.com', '$2y$10$REPLACE_HASH_HERE', 'admin', 'active');
-- ============================================================

-- Reference: existing creators table (already present – do NOT recreate)
-- CREATE TABLE `creators` (
--   `id`                 int(11) unsigned NOT NULL AUTO_INCREMENT,
--   `added`              varchar(32) DEFAULT NULL,
--   `username`           varchar(32) DEFAULT NULL,
--   `display_name`       varchar(32) DEFAULT NULL,
--   `backstage_status`   varchar(32) DEFAULT 'unknown',
--   `backstage_region`   varchar(2)  DEFAULT 'uk',
--   `avatar`             text        DEFAULT NULL,
--   `assigned_customer`  int(11)     DEFAULT NULL,   -- FK → users.id
--   `assigned_at`        datetime    DEFAULT NULL,
--   `customer_status`    varchar(20) DEFAULT 'new',  -- new/invited/accepted/declined
--   `backstage_checked`  varchar(3)  DEFAULT 'no',
--   `invitation_type`    int(11)     DEFAULT NULL,   -- integer code stored directly on creators
--   PRIMARY KEY (`id`)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
