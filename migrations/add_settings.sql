-- ============================================================
--  MIGRACIÓN: Tabla de configuración del panel
--  Ejecutar UNA sola vez en inte_panelws
-- ============================================================

CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key`   VARCHAR(100) NOT NULL,
  `setting_value` TEXT         NOT NULL,
  `description`   VARCHAR(255) NULL,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
  (
    'business_hours',
    '{"1":{"open":true,"start":"08:00","end":"18:00"},"2":{"open":true,"start":"08:00","end":"18:00"},"3":{"open":true,"start":"08:00","end":"18:00"},"4":{"open":true,"start":"08:00","end":"18:00"},"5":{"open":true,"start":"08:00","end":"18:00"},"6":{"open":true,"start":"08:00","end":"14:00"},"7":{"open":false,"start":"00:00","end":"00:00"}}',
    'Horarios de atención por día (1=Lun … 7=Dom)'
  ),
  (
    'force_schedule',
    'auto',
    'Forzar horario: auto | open | closed'
  ),
  (
    'timezone',
    'America/Bogota',
    'Zona horaria usada para evaluar horarios'
  ),
  (
    'out_of_hours_message',
    '',
    'Mensaje personalizado fuera de horario (vacío = mensaje por defecto)'
  )
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;
