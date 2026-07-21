SET NAMES utf8mb4;
SET time_zone = '+08:00';

-- 生产初始化只创建留言板业务表，不创建或修改老系统 admin 表。
CREATE TABLE IF NOT EXISTS `liuyan_message` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(100) NOT NULL,
    `content` VARCHAR(2000) NOT NULL,
    `audit_status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `display_status` ENUM('visible', 'hidden') NOT NULL DEFAULT 'visible',
    `source_ip` VARCHAR(45) NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_message_public` (`audit_status`, `display_status`, `deleted_at`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `liuyan_reply` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `message_id` BIGINT UNSIGNED NOT NULL,
    `admin_id` INT UNSIGNED NOT NULL,
    `content` VARCHAR(2000) NOT NULL,
    `status` ENUM('draft', 'published') NOT NULL DEFAULT 'published',
    `published_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_reply_message` (`message_id`, `deleted_at`, `status`, `id`),
    KEY `idx_reply_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `liuyan_operation_log` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id` INT UNSIGNED NOT NULL,
    `action` VARCHAR(64) NOT NULL,
    `target_type` VARCHAR(32) NOT NULL,
    `target_id` BIGINT UNSIGNED NOT NULL,
    `detail` TEXT NULL,
    `source_ip` VARCHAR(45) NOT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_log_admin_time` (`admin_id`, `created_at`),
    KEY `idx_log_target` (`target_type`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
