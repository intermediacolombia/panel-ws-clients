-- Migración: agregar campos phone y wa_alerts a la tabla agents
-- Ejecutar en phpMyAdmin o MySQL CLI

ALTER TABLE `agents`
  ADD COLUMN IF NOT EXISTS `phone`     VARCHAR(30) DEFAULT NULL   AFTER `email`,
  ADD COLUMN IF NOT EXISTS `wa_alerts` TINYINT(1)  NOT NULL DEFAULT 0 AFTER `phone`;
