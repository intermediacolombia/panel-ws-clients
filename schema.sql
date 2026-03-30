-- ============================================================
--  Panel de Agentes — Schema completo
--  Versión limpia para instalación desde cero.
--  Compatible con MySQL 8+ y MariaDB 10.4+
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
SET time_zone = "+00:00";
/*!40101 SET NAMES utf8mb4 */;

-- ──────────────────────────────────────────────────────────────
--  TABLA: agents
-- ──────────────────────────────────────────────────────────────
CREATE TABLE `agents` (
  `id`         int(11)                      NOT NULL AUTO_INCREMENT,
  `username`   varchar(50)                  NOT NULL,
  `password`   varchar(255)                 NOT NULL,
  `name`       varchar(100)                 NOT NULL,
  `email`      varchar(150)                 NOT NULL,
  `phone`      varchar(30)                  DEFAULT NULL,
  `wa_alerts`  tinyint(1)                   NOT NULL DEFAULT 0,
  `role`       enum('supervisor','agente')  NOT NULL DEFAULT 'agente',
  `status`     enum('active','inactive')    NOT NULL DEFAULT 'active',
  `last_seen`  datetime                     DEFAULT NULL,
  `fcm_token`  varchar(255)                 DEFAULT NULL,
  `created_at` datetime                     NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime                     NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  UNIQUE KEY `uq_email`    (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────
--  TABLA: departments
-- ──────────────────────────────────────────────────────────────
CREATE TABLE `departments` (
  `id`          int(11)      NOT NULL AUTO_INCREMENT,
  `name`        varchar(100) NOT NULL,
  `slug`        varchar(50)  NOT NULL,
  `description` text         DEFAULT NULL,
  `color`       varchar(7)   NOT NULL DEFAULT '#25D366',
  `icon`        varchar(50)  NOT NULL DEFAULT 'headset',
  `active`      tinyint(1)   NOT NULL DEFAULT 1,
  `created_at`  datetime     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────
--  TABLA: agent_departments
-- ──────────────────────────────────────────────────────────────
CREATE TABLE `agent_departments` (
  `agent_id`      int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  PRIMARY KEY (`agent_id`, `department_id`),
  KEY `fk_ad_dept` (`department_id`),
  CONSTRAINT `fk_ad_agent` FOREIGN KEY (`agent_id`)      REFERENCES `agents`(`id`)      ON DELETE CASCADE,
  CONSTRAINT `fk_ad_dept`  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────
--  TABLA: agent_sessions
-- ──────────────────────────────────────────────────────────────
CREATE TABLE `agent_sessions` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `agent_id`   int(11)      NOT NULL,
  `token`      varchar(128) NOT NULL,
  `ip`         varchar(45)  NOT NULL,
  `user_agent` text         DEFAULT NULL,
  `expires_at` datetime     NOT NULL,
  `created_at` datetime     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token`   (`token`),
  KEY `idx_token`         (`token`),
  KEY `idx_expires`       (`expires_at`),
  KEY `fk_sess_agent`     (`agent_id`),
  CONSTRAINT `fk_sess_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────
--  TABLA: bot_estados
-- ──────────────────────────────────────────────────────────────
CREATE TABLE `bot_estados` (
  `ses_key`    varchar(120) NOT NULL,
  `estado`     varchar(50)  NOT NULL,
  `data`       longtext     CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '[]' CHECK (json_valid(`data`)),
  `timestamp`  int(11)      NOT NULL,
  `updated_at` datetime     NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ses_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────
--  TABLA: conversations
-- ──────────────────────────────────────────────────────────────
CREATE TABLE `conversations` (
  `id`               int(11)                                       NOT NULL AUTO_INCREMENT,
  `conv_key`         varchar(120)                                  NOT NULL,
  `phone`            varchar(30)                                   NOT NULL,
  `contact_name`     varchar(100)                                  NOT NULL DEFAULT '',
  `client_id`        varchar(50)                                   NOT NULL,
  `department_id`    int(11)                                       DEFAULT NULL,
  `area_label`       varchar(150)                                  NOT NULL DEFAULT '',
  `status`           enum('pending','attending','resolved','bot')  NOT NULL DEFAULT 'pending',
  `agent_id`         int(11)                                       DEFAULT NULL,
  `assigned_at`      datetime                                      DEFAULT NULL,
  `resolved_at`      datetime                                      DEFAULT NULL,
  `resolved_by`      int(11)                                       DEFAULT NULL,
  `first_contact_at` datetime                                      NOT NULL DEFAULT current_timestamp(),
  `last_message_at`  datetime                                      NOT NULL DEFAULT current_timestamp(),
  `unread_count`     int(11)                                       NOT NULL DEFAULT 0,
  `created_at`       datetime                                      NOT NULL DEFAULT current_timestamp(),
  `updated_at`       datetime                                      NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_conv_key`   (`conv_key`),
  KEY `idx_phone`            (`phone`),
  KEY `idx_status`           (`status`),
  KEY `idx_department`       (`department_id`),
  KEY `idx_agent`            (`agent_id`),
  KEY `idx_last_msg`         (`last_message_at`),
  KEY `fk_conv_resolved`     (`resolved_by`),
  CONSTRAINT `fk_conv_agent`   FOREIGN KEY (`agent_id`)      REFERENCES `agents`(`id`)      ON DELETE SET NULL,
  CONSTRAINT `fk_conv_dept`    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_conv_resolved` FOREIGN KEY (`resolved_by`)  REFERENCES `agents`(`id`)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────
--  TABLA: login_attempts
-- ──────────────────────────────────────────────────────────────
CREATE TABLE `login_attempts` (
  `id`           int(11)     NOT NULL AUTO_INCREMENT,
  `ip`           varchar(45) NOT NULL,
  `attempted_at` datetime    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────
--  TABLA: messages
-- ──────────────────────────────────────────────────────────────
CREATE TABLE `messages` (
  `id`              int(11)                          NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11)                          NOT NULL,
  `direction`       enum('in','out')                 NOT NULL,
  `type`            enum('text','image','document')  NOT NULL DEFAULT 'text',
  `content`         text                             NOT NULL,
  `file_url`        varchar(500)                     DEFAULT NULL,
  `file_name`       varchar(255)                     DEFAULT NULL,
  `file_mime`       varchar(100)                     DEFAULT NULL,
  `file_size`       int(11)                          DEFAULT NULL,
  `caption`         text                             DEFAULT NULL,
  `agent_id`        int(11)                          DEFAULT NULL,
  `wa_message_id`   varchar(100)                     DEFAULT NULL,
  `status`          enum('sent','failed','pending')  NOT NULL DEFAULT 'pending',
  `error_detail`    varchar(500)                     DEFAULT NULL,
  `created_at`      datetime                         NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_conv`     (`conversation_id`),
  KEY `idx_created`  (`created_at`),
  KEY `fk_msg_agent` (`agent_id`),
  CONSTRAINT `fk_msg_conv`  FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_agent` FOREIGN KEY (`agent_id`)        REFERENCES `agents`(`id`)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────
--  TABLA: notifications
-- ──────────────────────────────────────────────────────────────
CREATE TABLE `notifications` (
  `id`              int(11)                                                      NOT NULL AUTO_INCREMENT,
  `agent_id`        int(11)                                                      NOT NULL,
  `conversation_id` int(11)                                                      NOT NULL,
  `type`            enum('new_conversation','new_message','assigned','resolved')  NOT NULL DEFAULT 'new_message',
  `message`         text                                                          NOT NULL,
  `read_at`         datetime                                                      DEFAULT NULL,
  `created_at`      datetime                                                      NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_agent_unread` (`agent_id`, `read_at`),
  KEY `fk_notif_conv`    (`conversation_id`),
  CONSTRAINT `fk_notif_agent` FOREIGN KEY (`agent_id`)        REFERENCES `agents`(`id`)        ON DELETE CASCADE,
  CONSTRAINT `fk_notif_conv`  FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────
--  TABLA: settings
-- ──────────────────────────────────────────────────────────────
CREATE TABLE `settings` (
  `setting_key`   varchar(100) NOT NULL,
  `setting_value` text         NOT NULL,
  `description`   varchar(255) DEFAULT NULL,
  `updated_at`    datetime     NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────
--  DATOS INICIALES: settings
-- ──────────────────────────────────────────────────────────────
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
  ('business_hours',       '{"1":{"open":true,"start":"08:00","end":"18:00"},"2":{"open":true,"start":"08:00","end":"18:00"},"3":{"open":true,"start":"08:00","end":"18:00"},"4":{"open":true,"start":"08:00","end":"18:00"},"5":{"open":true,"start":"08:00","end":"18:00"},"6":{"open":true,"start":"08:00","end":"14:00"},"7":{"open":false,"start":"00:00","end":"00:00"}}', 'Horarios de atención por día (1=Lun … 7=Dom)'),
  ('force_schedule',       'auto',             'Forzar horario: auto | open | closed'),
  ('out_of_hours_message', '',                 'Mensaje personalizado fuera de horario (vacío = mensaje por defecto)'),
  ('timezone',             'America/Bogota',   'Zona horaria usada para evaluar horarios');

SET FOREIGN_KEY_CHECKS = 1;
