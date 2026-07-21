SET NAMES utf8mb4;

CREATE TABLE `admin` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(64) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `user_type` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_admin_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `admin` (`username`, `password`, `user_type`)
VALUES ('admin', '$2y$10$6vAzkHNbW95I8.GxUrTG.Of3OHnmSQ0aCRZfOrDld742xAVljAnAi', 1);
