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
  `status`       enum('active','inactive','pending') DEFAULT 'active',
  `role`         enum('customer','admin')            DEFAULT 'customer',
  `last_login`   datetime         DEFAULT NULL,
  `notes`        text             DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
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
--   `backstage_checked`  varchar(3)  DEFAULT 'no',
--   `invitation_type`    int(11)     DEFAULT NULL,   -- integer code stored directly on creators
--   PRIMARY KEY (`id`)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
