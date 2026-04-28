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
  `paypal_plan_id`   varchar(120)     DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `packages`
  ADD KEY `idx_packages_paypal_plan_id` (`paypal_plan_id`);

-- Add package relation on users (run once)
ALTER TABLE `users`
  ADD COLUMN `package_id` int(11) unsigned DEFAULT NULL,
  ADD KEY `idx_users_package_id` (`package_id`);

ALTER TABLE `users`
  ADD COLUMN `paypal_subscription_id` varchar(64) DEFAULT NULL,
  ADD COLUMN `payment_exempt` tinyint(1) NOT NULL DEFAULT 0,
  ADD COLUMN `subscription_status` varchar(32) NOT NULL DEFAULT 'none',
  ADD COLUMN `subscription_package_id` int(11) unsigned DEFAULT NULL,
  ADD COLUMN `subscription_started_at` datetime DEFAULT NULL,
  ADD COLUMN `subscription_ends_at` datetime DEFAULT NULL,
  ADD COLUMN `subscription_updated_at` datetime DEFAULT NULL,
  ADD KEY `idx_users_paypal_subscription_id` (`paypal_subscription_id`),
  ADD KEY `idx_users_payment_exempt` (`payment_exempt`),
  ADD KEY `idx_users_subscription_status` (`subscription_status`);

CREATE TABLE IF NOT EXISTS `paypal_subscriptions` (
  `id`                     int(11) unsigned NOT NULL AUTO_INCREMENT,
  `created_at`             datetime         DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             datetime         DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `user_id`                int(11) unsigned NOT NULL,
  `package_id`             int(11) unsigned DEFAULT NULL,
  `paypal_subscription_id` varchar(64)      NOT NULL,
  `paypal_plan_id`         varchar(120)     DEFAULT NULL,
  `status`                 varchar(32)      NOT NULL DEFAULT 'pending',
  `subscriber_email`       varchar(255)     DEFAULT NULL,
  `next_billing_time`      datetime         DEFAULT NULL,
  `raw_payload`            longtext         DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_paypal_subscription_id` (`paypal_subscription_id`),
  KEY `idx_ps_user_id` (`user_id`),
  KEY `idx_ps_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `paypal_payments` (
  `id`                     int(11) unsigned NOT NULL AUTO_INCREMENT,
  `created_at`             datetime         DEFAULT CURRENT_TIMESTAMP,
  `user_id`                int(11) unsigned DEFAULT NULL,
  `paypal_subscription_id` varchar(64)      DEFAULT NULL,
  `paypal_transaction_id`  varchar(64)      NOT NULL,
  `status`                 varchar(32)      NOT NULL,
  `amount`                 decimal(10,2)    DEFAULT NULL,
  `currency_code`          varchar(10)      DEFAULT NULL,
  `payer_email`            varchar(255)     DEFAULT NULL,
  `paid_at`                datetime         DEFAULT NULL,
  `raw_payload`            longtext         DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_paypal_transaction_id` (`paypal_transaction_id`),
  KEY `idx_pp_user_id` (`user_id`),
  KEY `idx_pp_subscription_id` (`paypal_subscription_id`),
  KEY `idx_pp_status` (`status`),
  KEY `idx_pp_paid_at` (`paid_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `paypal_webhook_events` (
  `id`                   int(11) unsigned NOT NULL AUTO_INCREMENT,
  `created_at`           datetime         DEFAULT CURRENT_TIMESTAMP,
  `paypal_event_id`      varchar(64)      NOT NULL,
  `event_type`           varchar(80)      NOT NULL,
  `resource_type`        varchar(80)      DEFAULT NULL,
  `verification_status`  varchar(20)      DEFAULT NULL,
  `processing_status`    varchar(20)      NOT NULL DEFAULT 'received',
  `error_message`        varchar(500)     DEFAULT NULL,
  `raw_payload`          longtext         DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pwe_event_id` (`paypal_event_id`),
  KEY `idx_pwe_event_type` (`event_type`),
  KEY `idx_pwe_processing_status` (`processing_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

-- TikTok Backstage login account pool (used by automation account rotation)
CREATE TABLE IF NOT EXISTS `backstage_accounts` (
  `id`          int(11) unsigned NOT NULL AUTO_INCREMENT,
  `created_at`  datetime         DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  datetime         DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `email`       varchar(255)     NOT NULL,
  `password`    varchar(255)     NOT NULL,
  `label`       varchar(100)     DEFAULT NULL,
  `is_active`   tinyint(1)       NOT NULL DEFAULT 1,
  `last_used_at` datetime         DEFAULT NULL,
  `last_success_at` datetime      DEFAULT NULL,
  `last_failure_at` datetime      DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_backstage_accounts_email` (`email`),
  KEY `idx_backstage_accounts_active` (`is_active`)
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
