SET NAMES utf8mb4;
SET time_zone = '+08:00';

ALTER TABLE `liuyan_reply`
    ADD COLUMN `status` ENUM('draft', 'published') NOT NULL DEFAULT 'published' AFTER `content`,
    ADD COLUMN `published_at` DATETIME NULL DEFAULT NULL AFTER `status`;

UPDATE `liuyan_reply`
SET `published_at` = `created_at`
WHERE `status` = 'published' AND `published_at` IS NULL;

ALTER TABLE `liuyan_reply`
    DROP INDEX `idx_reply_message`,
    ADD KEY `idx_reply_message` (`message_id`, `deleted_at`, `status`, `id`);
