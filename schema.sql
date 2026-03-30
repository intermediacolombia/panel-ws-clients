-- ============================================================
--  PANEL DE AGENTES WHATSAPP — INTERMEDIA HOST
--  schema.sql — Ejecutar una sola vez en la BD inte_panelws
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── departments ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `departments` (
  `id`          INT          NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  `slug`        VARCHAR(50)  NOT NULL,
  `description` TEXT         NULL,
  `color`       VARCHAR(7)   NOT NULL DEFAULT '#25D366',
  `icon`        VARCHAR(50)  NOT NULL DEFAULT 'headset',
  `active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `departments` (`name`, `slug`, `description`, `color`, `icon`) VALUES
  ('Ventas',          'ventas',  'Área comercial',          '#25D366', 'shopping-cart'),
  ('Soporte Técnico', 'soporte', 'Asistencia técnica',      '#3498DB', 'wrench'),
  ('Medios de Pago',  'pagos',   'Validación de pagos',     '#E67E22', 'credit-card'),
  ('Otros',           'otros',   'Consultas generales',     '#9B59B6', 'question-circle')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- ── agents ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `agents` (
  `id`          INT          NOT NULL AUTO_INCREMENT,
  `username`    VARCHAR(50)  NOT NULL,
  `password`    VARCHAR(255) NOT NULL,
  `name`        VARCHAR(100) NOT NULL,
  `email`       VARCHAR(150) NOT NULL,
  `role`        ENUM('supervisor','agente') NOT NULL DEFAULT 'agente',
  `status`      ENUM('active','inactive')   NOT NULL DEFAULT 'active',
  `last_seen`   DATETIME     NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  UNIQUE KEY `uq_email`    (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `agents` (`username`, `password`, `name`, `email`, `role`, `status`) VALUES
  ('admin',    '$2y$10$9LiCKieyP6gN2Yt8qQKIJuWMOwqMFv8WxWRNrldpZTZCwoe2rrvYG', 'Administrador',   'admin@intermediahost.co',    'supervisor', 'active'),
  ('ventas1',  '$2y$10$hpXo4UfYc1qm0zIouHjriOiswy6j0f6JqY4P.k0GGJcxZRkiY/era', 'Asesor Ventas 1', 'ventas1@intermediahost.co',  'agente',     'active'),
  ('ventas2',  '$2y$10$hpXo4UfYc1qm0zIouHjriOiswy6j0f6JqY4P.k0GGJcxZRkiY/era', 'Asesor Ventas 2', 'ventas2@intermediahost.co',  'agente',     'active'),
  ('soporte1', '$2y$10$.9wxPUWUeKTsjXiE35nWuufeyu5IaDrYayL1Os0oHnrXi.cL9uS.O', 'Soporte Técnico 1','soporte1@intermediahost.co','agente',     'active'),
  ('soporte2', '$2y$10$.9wxPUWUeKTsjXiE35nWuufeyu5IaDrYayL1Os0oHnrXi.cL9uS.O', 'Soporte Técnico 2','soporte2@intermediahost.co','agente',     'active')
ON DUPLICATE KEY UPDATE `username` = VALUES(`username`);

-- ── agent_departments ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `agent_departments` (
  `agent_id`      INT NOT NULL,
  `department_id` INT NOT NULL,
  PRIMARY KEY (`agent_id`, `department_id`),
  CONSTRAINT `fk_ad_agent` FOREIGN KEY (`agent_id`)      REFERENCES `agents`(`id`)      ON DELETE CASCADE,
  CONSTRAINT `fk_ad_dept`  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- admin (supervisor) → todos los departamentos
INSERT IGNORE INTO `agent_departments` (`agent_id`, `department_id`)
SELECT a.id, d.id FROM `agents` a, `departments` d WHERE a.username = 'admin';

-- ventas1 y ventas2 → departamento ventas
INSERT IGNORE INTO `agent_departments` (`agent_id`, `department_id`)
SELECT a.id, d.id FROM `agents` a JOIN `departments` d ON d.slug = 'ventas'
WHERE a.username IN ('ventas1','ventas2');

-- soporte1 y soporte2 → departamento soporte
INSERT IGNORE INTO `agent_departments` (`agent_id`, `department_id`)
SELECT a.id, d.id FROM `agents` a JOIN `departments` d ON d.slug = 'soporte'
WHERE a.username IN ('soporte1','soporte2');

-- ── agent_sessions ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `agent_sessions` (
  `id`          INT          NOT NULL AUTO_INCREMENT,
  `agent_id`    INT          NOT NULL,
  `token`       VARCHAR(128) NOT NULL,
  `ip`          VARCHAR(45)  NOT NULL,
  `user_agent`  TEXT         NULL,
  `expires_at`  DATETIME     NOT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token`   (`token`),
  KEY `idx_token`   (`token`),
  KEY `idx_expires` (`expires_at`),
  CONSTRAINT `fk_sess_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── conversations ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `conversations` (
  `id`               INT          NOT NULL AUTO_INCREMENT,
  `conv_key`         VARCHAR(120) NOT NULL,
  `phone`            VARCHAR(30)  NOT NULL,
  `contact_name`     VARCHAR(100) NOT NULL DEFAULT '',
  `client_id`        VARCHAR(50)  NOT NULL,
  `department_id`    INT          NULL,
  `area_label`       VARCHAR(150) NOT NULL DEFAULT '',
  `status`           ENUM('pending','attending','resolved','bot') NOT NULL DEFAULT 'pending',
  `agent_id`         INT          NULL,
  `assigned_at`      DATETIME     NULL,
  `resolved_at`      DATETIME     NULL,
  `resolved_by`      INT          NULL,
  `first_contact_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_message_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `unread_count`     INT          NOT NULL DEFAULT 0,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_conv_key`   (`conv_key`),
  KEY `idx_phone`      (`phone`),
  KEY `idx_status`     (`status`),
  KEY `idx_department` (`department_id`),
  KEY `idx_agent`      (`agent_id`),
  KEY `idx_last_msg`   (`last_message_at`),
  CONSTRAINT `fk_conv_dept`     FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_conv_agent`    FOREIGN KEY (`agent_id`)      REFERENCES `agents`(`id`)      ON DELETE SET NULL,
  CONSTRAINT `fk_conv_resolved` FOREIGN KEY (`resolved_by`)   REFERENCES `agents`(`id`)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── messages ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `messages` (
  `id`              INT          NOT NULL AUTO_INCREMENT,
  `conversation_id` INT          NOT NULL,
  `direction`       ENUM('in','out') NOT NULL,
  `type`            ENUM('text','image','document') NOT NULL DEFAULT 'text',
  `content`         TEXT         NOT NULL,
  `file_url`        VARCHAR(500) NULL,
  `file_name`       VARCHAR(255) NULL,
  `file_mime`       VARCHAR(100) NULL,
  `file_size`       INT          NULL,
  `caption`         TEXT         NULL,
  `agent_id`        INT          NULL,
  `wa_message_id`   VARCHAR(100) NULL,
  `status`          ENUM('sent','failed','pending') NOT NULL DEFAULT 'pending',
  `error_detail`    VARCHAR(500) NULL,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conv`    (`conversation_id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_msg_conv`  FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_agent` FOREIGN KEY (`agent_id`)        REFERENCES `agents`(`id`)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── notifications ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`              INT  NOT NULL AUTO_INCREMENT,
  `agent_id`        INT  NOT NULL,
  `conversation_id` INT  NOT NULL,
  `type`            ENUM('new_conversation','new_message','assigned','resolved') NOT NULL DEFAULT 'new_message',
  `message`         TEXT NOT NULL,
  `read_at`         DATETIME NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_agent_unread` (`agent_id`, `read_at`),
  CONSTRAINT `fk_notif_agent` FOREIGN KEY (`agent_id`)        REFERENCES `agents`(`id`)        ON DELETE CASCADE,
  CONSTRAINT `fk_notif_conv`  FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── login_attempts ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`           INT         NOT NULL AUTO_INCREMENT,
  `ip`           VARCHAR(45) NOT NULL,
  `attempted_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── bot_estados (compartida con webhook) ─────────────────────
CREATE TABLE IF NOT EXISTS `bot_estados` (
  `ses_key`    VARCHAR(120) NOT NULL,
  `estado`     VARCHAR(50)  NOT NULL,
  `data`       JSON         NOT NULL DEFAULT (JSON_ARRAY()),
  `timestamp`  INT          NOT NULL,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ses_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
