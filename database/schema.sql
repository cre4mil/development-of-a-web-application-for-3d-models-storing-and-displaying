-- 3D Gallery database schema (MySQL / MariaDB, utf8mb4).
-- Fresh install:  mysql -u root -e "CREATE DATABASE db_3dmodels CHARACTER SET utf8mb4" && mysql -u root db_3dmodels < database/schema.sql
-- Existing install: apply the files in database/migrations/ in order.

-- Tables are listed alphabetically, so foreign keys are checked once everything exists.
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE `collections` (
  `user_id` int(11) NOT NULL,
  `model_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`,`model_id`),
  KEY `fk_collections_model` (`model_id`),
  CONSTRAINT `fk_collections_model` FOREIGN KEY (`model_id`) REFERENCES `models` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_collections_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `model_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_comment_model` (`model_id`),
  KEY `fk_comments_user` (`user_id`),
  CONSTRAINT `fk_comments_model` FOREIGN KEY (`model_id`) REFERENCES `models` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `creator_wallets` (
  `creator_id` int(11) NOT NULL,
  `available_balance` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Ó©óÓ©¡Ó©öÓ©ùÓ©ÁÓ╣êÓ©ûÓ©¡Ó©ÖÓ╣äÓ©öÓ╣ë',
  `total_earned` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Ó©úÓ©▓Ó©óÓ╣äÓ©öÓ╣ëÓ©¬Ó©░Ó©¬Ó©íÓ©ùÓ©▒Ó╣ëÓ©çÓ©½Ó©íÓ©ö',
  `pending_payout` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Ó©óÓ©¡Ó©öÓ©ùÓ©ÁÓ╣êÓ©úÓ©¡Ó╣éÓ©¡Ó©Ö',
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_account_no` varchar(50) DEFAULT NULL,
  `bank_account_name` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`creator_id`),
  CONSTRAINT `fk_cw_user` FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `likes` (
  `user_id` int(11) NOT NULL,
  `model_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`,`model_id`),
  KEY `fk_likes_model` (`model_id`),
  CONSTRAINT `fk_likes_model` FOREIGN KEY (`model_id`) REFERENCES `models` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_likes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `model_tags` (
  `model_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`model_id`,`tag_id`),
  KEY `fk_mt_tag` (`tag_id`),
  CONSTRAINT `fk_mt_model` FOREIGN KEY (`model_id`) REFERENCES `models` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mt_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `models` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `file_gltf` varchar(255) DEFAULT NULL,
  `file_glb` varchar(255) DEFAULT NULL,
  `file_usdz` varchar(255) DEFAULT NULL,
  `file_obj` varchar(255) DEFAULT NULL,
  `thumb` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  `license` varchar(50) NOT NULL DEFAULT 'CC BY',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `view_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_models_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `model_id` int(11) NOT NULL,
  `creator_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Ó©úÓ©▓Ó©äÓ©▓Ó╣éÓ©íÓ╣ÇÓ©öÓ©Ñ Ó©ô Ó╣ÇÓ©ºÓ©ÑÓ©▓Ó©ïÓ©ÀÓ╣ëÓ©¡',
  `platform_fee` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Ó©äÓ╣êÓ©▓Ó©ÿÓ©úÓ©úÓ©íÓ╣ÇÓ©ÖÓ©ÁÓ©óÓ©íÓ╣üÓ©×Ó©ÑÓ©òÓ©ƒÓ©¡Ó©úÓ╣îÓ©í',
  `creator_earning` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Ó©úÓ©▓Ó©óÓ╣äÓ©öÓ╣ëÓ©¬Ó©©Ó©ùÓ©ÿÓ©┤Ó©éÓ©¡Ó©ç Creator',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_model_id` (`model_id`),
  KEY `idx_creator_id` (`creator_id`),
  CONSTRAINT `fk_oi_creator` FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_oi_model` FOREIGN KEY (`model_id`) REFERENCES `models` (`id`),
  CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_ref` varchar(32) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `model_id` int(11) DEFAULT NULL,
  `seller_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT 0.00,
  `slip_file` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `admin_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Ó©óÓ©¡Ó©öÓ©úÓ©ºÓ©íÓ©ùÓ©ÁÓ╣êÓ©£Ó©╣Ó╣ëÓ©ïÓ©ÀÓ╣ëÓ©¡Ó©êÓ╣êÓ©▓Ó©ó',
  `payment_slip_url` varchar(255) DEFAULT NULL COMMENT 'path Ó©¬Ó©ÑÓ©┤Ó©øÓ©¡Ó©▒Ó©øÓ╣éÓ©½Ó©ÑÓ©ö',
  `payment_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_approved_by` int(11) DEFAULT NULL COMMENT 'admin user_id Ó©ùÓ©ÁÓ╣ê approve',
  `approved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_ref` (`order_ref`),
  KEY `idx_buyer` (`buyer_id`),
  KEY `idx_model` (`model_id`),
  KEY `idx_seller` (`seller_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_orders_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_orders_model` FOREIGN KEY (`model_id`) REFERENCES `models` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_orders_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `payout_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','transferred','rejected') NOT NULL DEFAULT 'pending',
  `admin_transfer_slip` varchar(255) DEFAULT NULL COMMENT 'path Ó©¬Ó©ÑÓ©┤Ó©øÓ©ùÓ©ÁÓ╣êÓ╣üÓ©¡Ó©öÓ©íÓ©┤Ó©ÖÓ╣éÓ©¡Ó©ÖÓ╣âÓ©½Ó╣ë Creator',
  `admin_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `transferred_at` timestamp NULL DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL COMMENT 'admin Ó©ùÓ©ÁÓ╣êÓ©öÓ©│Ó╣ÇÓ©ÖÓ©┤Ó©ÖÓ©üÓ©▓Ó©ú',
  PRIMARY KEY (`id`),
  KEY `idx_pr_creator` (`creator_id`),
  KEY `idx_pr_status` (`status`),
  CONSTRAINT `fk_pr_creator` FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `platform_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tag_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `promptpay_id` varchar(20) DEFAULT NULL,
  `promptpay_name` varchar(100) DEFAULT NULL,
  `wallet_id` varchar(50) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_account` varchar(50) DEFAULT NULL,
  `payment_name` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `follows` (
  `follower_id` int(11) NOT NULL,
  `followee_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`follower_id`,`followee_id`),
  KEY `idx_follows_followee` (`followee_id`),
  CONSTRAINT `fk_follows_follower` FOREIGN KEY (`follower_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_follows_followee` FOREIGN KEY (`followee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
