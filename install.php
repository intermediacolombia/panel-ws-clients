<?php
/**
 * install.php — Asistente de instalación del Panel de Agentes.
 * Ejecutar una sola vez desde el navegador. Eliminar después de instalar.
 */

// ── Bloquear si config.php ya tiene BD configurada y la BD ya existe ─────────
// (descomenta si deseas protección extra en producción)
// if (file_exists(__DIR__ . '/.installed')) { die('Ya instalado.'); }

session_start();
$errors   = [];
$success  = false;
$step     = (int)($_POST['step'] ?? 1);

// ── Paso 2: crear tablas + usuario ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {

    $host   = trim($_POST['db_host']   ?? 'localhost');
    $port   = trim($_POST['db_port']   ?? '3306');
    $user   = trim($_POST['db_user']   ?? '');
    $pass   = $_POST['db_pass']        ?? '';
    $dbname = trim($_POST['db_name']   ?? '');

    $agUser  = trim($_POST['ag_user']  ?? '');
    $agName  = trim($_POST['ag_name']  ?? '');
    $agEmail = trim($_POST['ag_email'] ?? '');
    $agPass  = $_POST['ag_pass']       ?? '';
    $agPass2 = $_POST['ag_pass2']      ?? '';

    // Validaciones básicas
    if (empty($host))   $errors[] = 'Host de BD requerido.';
    if (empty($user))   $errors[] = 'Usuario de BD requerido.';
    if (empty($dbname)) $errors[] = 'Nombre de BD requerido.';
    if (empty($agUser)) $errors[] = 'Usuario del administrador requerido.';
    if (empty($agName)) $errors[] = 'Nombre del administrador requerido.';
    if (empty($agEmail) || !filter_var($agEmail, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Email del administrador inválido.';
    if (strlen($agPass) < 8)
        $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
    if ($agPass !== $agPass2)
        $errors[] = 'Las contraseñas no coinciden.';

    if (empty($errors)) {
        try {
            $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            // Crear BD si no existe
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`
                        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbname`");

            // ── DDL ─────────────────────────────────────────────────────────
            $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `agents` (
  `id`         int(11)                      NOT NULL AUTO_INCREMENT,
  `username`   varchar(50)                  NOT NULL,
  `password`   varchar(255)                 NOT NULL,
  `name`       varchar(100)                 NOT NULL,
  `email`      varchar(150)                 NOT NULL,
  `role`       enum('supervisor','agente')  NOT NULL DEFAULT 'agente',
  `status`     enum('active','inactive')    NOT NULL DEFAULT 'active',
  `last_seen`  datetime                     DEFAULT NULL,
  `created_at` datetime                     NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime                     NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `fcm_token`  varchar(255)                 DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  UNIQUE KEY `uq_email`    (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `departments` (
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

CREATE TABLE IF NOT EXISTS `agent_departments` (
  `agent_id`      int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  PRIMARY KEY (`agent_id`,`department_id`),
  KEY `fk_ad_dept` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `agent_sessions` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `agent_id`   int(11)      NOT NULL,
  `token`      varchar(128) NOT NULL,
  `ip`         varchar(45)  NOT NULL,
  `user_agent` text         DEFAULT NULL,
  `expires_at` datetime     NOT NULL,
  `created_at` datetime     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token`     (`token`),
  KEY `idx_token`           (`token`),
  KEY `idx_expires`         (`expires_at`),
  KEY `fk_sess_agent`       (`agent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bot_estados` (
  `ses_key`    varchar(120) NOT NULL,
  `estado`     varchar(50)  NOT NULL,
  `data`       longtext     CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '[]' CHECK (json_valid(`data`)),
  `timestamp`  int(11)      NOT NULL,
  `updated_at` datetime     NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ses_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conversations` (
  `id`               int(11)                                   NOT NULL AUTO_INCREMENT,
  `conv_key`         varchar(120)                              NOT NULL,
  `phone`            varchar(30)                               NOT NULL,
  `contact_name`     varchar(100)                              NOT NULL DEFAULT '',
  `client_id`        varchar(50)                               NOT NULL,
  `department_id`    int(11)                                   DEFAULT NULL,
  `area_label`       varchar(150)                              NOT NULL DEFAULT '',
  `status`           enum('pending','attending','resolved','bot') NOT NULL DEFAULT 'pending',
  `agent_id`         int(11)                                   DEFAULT NULL,
  `assigned_at`      datetime                                  DEFAULT NULL,
  `resolved_at`      datetime                                  DEFAULT NULL,
  `resolved_by`      int(11)                                   DEFAULT NULL,
  `first_contact_at` datetime                                  NOT NULL DEFAULT current_timestamp(),
  `last_message_at`  datetime                                  NOT NULL DEFAULT current_timestamp(),
  `unread_count`     int(11)                                   NOT NULL DEFAULT 0,
  `created_at`       datetime                                  NOT NULL DEFAULT current_timestamp(),
  `updated_at`       datetime                                  NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_conv_key`   (`conv_key`),
  KEY `idx_phone`            (`phone`),
  KEY `idx_status`           (`status`),
  KEY `idx_department`       (`department_id`),
  KEY `idx_agent`            (`agent_id`),
  KEY `idx_last_msg`         (`last_message_at`),
  KEY `fk_conv_resolved`     (`resolved_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`           int(11)     NOT NULL AUTO_INCREMENT,
  `ip`           varchar(45) NOT NULL,
  `attempted_at` datetime    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `messages` (
  `id`              int(11)                         NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11)                         NOT NULL,
  `direction`       enum('in','out')                NOT NULL,
  `type`            enum('text','image','document') NOT NULL DEFAULT 'text',
  `content`         text                            NOT NULL,
  `file_url`        varchar(500)                    DEFAULT NULL,
  `file_name`       varchar(255)                    DEFAULT NULL,
  `file_mime`       varchar(100)                    DEFAULT NULL,
  `file_size`       int(11)                         DEFAULT NULL,
  `caption`         text                            DEFAULT NULL,
  `agent_id`        int(11)                         DEFAULT NULL,
  `wa_message_id`   varchar(100)                    DEFAULT NULL,
  `status`          enum('sent','failed','pending') NOT NULL DEFAULT 'pending',
  `error_detail`    varchar(500)                    DEFAULT NULL,
  `created_at`      datetime                        NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_conv`    (`conversation_id`),
  KEY `idx_created` (`created_at`),
  KEY `fk_msg_agent`(`agent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id`              int(11)                                                    NOT NULL AUTO_INCREMENT,
  `agent_id`        int(11)                                                    NOT NULL,
  `conversation_id` int(11)                                                    NOT NULL,
  `type`            enum('new_conversation','new_message','assigned','resolved') NOT NULL DEFAULT 'new_message',
  `message`         text                                                        NOT NULL,
  `read_at`         datetime                                                    DEFAULT NULL,
  `created_at`      datetime                                                    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_agent_unread` (`agent_id`,`read_at`),
  KEY `fk_notif_conv`    (`conversation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key`   varchar(100) NOT NULL,
  `setting_value` text         NOT NULL,
  `description`   varchar(255) DEFAULT NULL,
  `updated_at`    datetime     NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

            // Ejecutar cada sentencia por separado
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                if ($stmt !== '') $pdo->exec($stmt);
            }

            // ── Foreign keys ────────────────────────────────────────────────
            $fkSql = [
                'ALTER TABLE `agent_departments`
                   ADD CONSTRAINT IF NOT EXISTS `fk_ad_agent` FOREIGN KEY (`agent_id`)      REFERENCES `agents`(`id`)      ON DELETE CASCADE,
                   ADD CONSTRAINT IF NOT EXISTS `fk_ad_dept`  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE',
                'ALTER TABLE `agent_sessions`
                   ADD CONSTRAINT IF NOT EXISTS `fk_sess_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents`(`id`) ON DELETE CASCADE',
                'ALTER TABLE `conversations`
                   ADD CONSTRAINT IF NOT EXISTS `fk_conv_agent`    FOREIGN KEY (`agent_id`)   REFERENCES `agents`(`id`) ON DELETE SET NULL,
                   ADD CONSTRAINT IF NOT EXISTS `fk_conv_dept`      FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
                   ADD CONSTRAINT IF NOT EXISTS `fk_conv_resolved`  FOREIGN KEY (`resolved_by`) REFERENCES `agents`(`id`) ON DELETE SET NULL',
                'ALTER TABLE `messages`
                   ADD CONSTRAINT IF NOT EXISTS `fk_msg_conv`  FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`) ON DELETE CASCADE,
                   ADD CONSTRAINT IF NOT EXISTS `fk_msg_agent` FOREIGN KEY (`agent_id`)        REFERENCES `agents`(`id`) ON DELETE SET NULL',
                'ALTER TABLE `notifications`
                   ADD CONSTRAINT IF NOT EXISTS `fk_notif_agent` FOREIGN KEY (`agent_id`)        REFERENCES `agents`(`id`)        ON DELETE CASCADE,
                   ADD CONSTRAINT IF NOT EXISTS `fk_notif_conv`  FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`) ON DELETE CASCADE',
            ];
            foreach ($fkSql as $fk) {
                try { $pdo->exec($fk); } catch (PDOException $e) { /* ignora si ya existe */ }
            }

            // ── Datos por defecto: settings ──────────────────────────────────
            $pdo->exec("INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
              ('business_hours', '{\"1\":{\"open\":true,\"start\":\"08:00\",\"end\":\"18:00\"},\"2\":{\"open\":true,\"start\":\"08:00\",\"end\":\"18:00\"},\"3\":{\"open\":true,\"start\":\"08:00\",\"end\":\"18:00\"},\"4\":{\"open\":true,\"start\":\"08:00\",\"end\":\"18:00\"},\"5\":{\"open\":true,\"start\":\"08:00\",\"end\":\"18:00\"},\"6\":{\"open\":true,\"start\":\"08:00\",\"end\":\"14:00\"},\"7\":{\"open\":false,\"start\":\"00:00\",\"end\":\"00:00\"}}', 'Horarios de atención por día (1=Lun … 7=Dom)'),
              ('force_schedule',        'auto', 'Forzar horario: auto | open | closed'),
              ('out_of_hours_message',  '',     'Mensaje personalizado fuera de horario (vacío = mensaje por defecto)'),
              ('timezone',              'America/Bogota', 'Zona horaria usada para evaluar horarios')
            ");

            // ── Crear usuario administrador ──────────────────────────────────
            $hash = password_hash($agPass, PASSWORD_BCRYPT);
            $ins  = $pdo->prepare(
                "INSERT INTO `agents` (`username`, `password`, `name`, `email`, `role`, `status`)
                 VALUES (?, ?, ?, ?, 'supervisor', 'active')"
            );
            $ins->execute([$agUser, $hash, $agName, $agEmail]);

            // ── Guardar config.php actualizado ───────────────────────────────
            $configPath = __DIR__ . '/config.php';
            if (file_exists($configPath)) {
                $cfg = file_get_contents($configPath);
                $cfg = preg_replace("/define\('DB_HOST',\s*'[^']*'\)/",  "define('DB_HOST', '$host')",   $cfg);
                $cfg = preg_replace("/define\('DB_PORT',\s*'[^']*'\)/",  "define('DB_PORT', '$port')",   $cfg);
                $cfg = preg_replace("/define\('DB_USER',\s*'[^']*'\)/",  "define('DB_USER', '$user')",   $cfg);
                $cfg = preg_replace("/define\('DB_PASS',\s*'[^']*'\)/",  "define('DB_PASS', '$pass')",   $cfg);
                $cfg = preg_replace("/define\('DB_NAME',\s*'[^']*'\)/",  "define('DB_NAME', '$dbname')", $cfg);
                file_put_contents($configPath, $cfg);
                $configUpdated = true;
            } else {
                $configUpdated = false;
            }

            // Marcar instalación completa
            file_put_contents(__DIR__ . '/.installed', date('Y-m-d H:i:s'));

            $success = true;
            $configMsg = $configUpdated
                ? 'config.php actualizado automáticamente.'
                : 'config.php no encontrado — actualiza manualmente las constantes DB_*.';

        } catch (PDOException $e) {
            $errors[] = 'Error de base de datos: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// ── Valores previos para repoblar el form en caso de error ───────────────────
$v = fn(string $k, string $d = '') => htmlspecialchars($_POST[$k] ?? $d);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalación — Panel de Agentes</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: system-ui, sans-serif; background: #f0f4f8; min-height: 100vh;
         display: flex; align-items: center; justify-content: center; padding: 24px; }
  .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.10);
          width: 100%; max-width: 540px; padding: 40px; }
  .logo { text-align: center; margin-bottom: 28px; }
  .logo h1 { font-size: 22px; color: #1a202c; margin-top: 10px; }
  .logo p  { font-size: 13px; color: #718096; margin-top: 4px; }
  .badge { display: inline-block; background: #25D366; color: #fff;
           font-size: 11px; font-weight: 700; padding: 2px 8px;
           border-radius: 20px; letter-spacing: .5px; margin-top: 6px; }
  h2 { font-size: 15px; font-weight: 700; color: #2d3748; border-bottom: 1px solid #e2e8f0;
       padding-bottom: 10px; margin: 24px 0 16px; }
  h2:first-of-type { margin-top: 0; }
  label { display: block; font-size: 13px; font-weight: 600; color: #4a5568; margin-bottom: 4px; }
  input { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e0; border-radius: 8px;
          font-size: 14px; color: #1a202c; background: #f7fafc; margin-bottom: 14px;
          transition: border-color .2s; }
  input:focus { outline: none; border-color: #25D366; background: #fff; }
  .hint { font-size: 11px; color: #718096; margin-top: -10px; margin-bottom: 14px; }
  .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
  .btn { width: 100%; padding: 13px; background: #25D366; color: #fff; border: none;
         border-radius: 8px; font-size: 15px; font-weight: 700; cursor: pointer;
         letter-spacing: .3px; margin-top: 8px; transition: background .2s; }
  .btn:hover { background: #128C7E; }
  .errors { background: #fff5f5; border: 1px solid #fed7d7; border-radius: 8px;
            padding: 14px 16px; margin-bottom: 20px; }
  .errors p { font-size: 13px; font-weight: 700; color: #c53030; margin-bottom: 6px; }
  .errors ul { padding-left: 18px; }
  .errors li { font-size: 13px; color: #c53030; line-height: 1.6; }
  .success { text-align: center; }
  .success .icon { font-size: 56px; margin-bottom: 16px; }
  .success h2 { border: none; font-size: 20px; color: #276749; justify-content: center; display: block; }
  .success p { font-size: 14px; color: #4a5568; margin: 8px 0; line-height: 1.6; }
  .success .warn { background: #fffbeb; border: 1px solid #f6e05e; border-radius: 8px;
                   padding: 12px 16px; margin: 18px 0; font-size: 13px; color: #744210; }
  .success .next { display: inline-block; margin-top: 18px; padding: 12px 28px;
                   background: #25D366; color: #fff; border-radius: 8px;
                   font-weight: 700; text-decoration: none; font-size: 14px; }
  .success .next:hover { background: #128C7E; }
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div style="font-size:40px">💬</div>
    <h1>Panel de Agentes</h1>
    <span class="badge">INSTALACIÓN</span>
    <p>Configuración inicial del sistema</p>
  </div>

<?php if ($success): ?>
  <div class="success">
    <div class="icon">✅</div>
    <h2>¡Instalación completada!</h2>
    <p>La base de datos y el usuario administrador han sido creados correctamente.</p>
    <p><?= htmlspecialchars($configMsg) ?></p>
    <div class="warn">
      ⚠️ <strong>Por seguridad, elimina este archivo</strong> del servidor antes de usar el panel:<br>
      <code>rm <?= htmlspecialchars(__FILE__) ?></code>
    </div>
    <a class="next" href="/login.php">Ir al Panel →</a>
  </div>

<?php else: ?>

  <?php if (!empty($errors)): ?>
  <div class="errors">
    <p>⚠️ Corrige los siguientes errores:</p>
    <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
  </div>
  <?php endif; ?>

  <form method="POST" autocomplete="off">
    <input type="hidden" name="step" value="2">

    <h2>🗄️ Conexión a la base de datos</h2>

    <div class="row2">
      <div>
        <label for="db_host">Host</label>
        <input id="db_host" name="db_host" value="<?= $v('db_host','localhost') ?>" required>
      </div>
      <div>
        <label for="db_port">Puerto</label>
        <input id="db_port" name="db_port" value="<?= $v('db_port','3306') ?>" required>
      </div>
    </div>

    <label for="db_name">Nombre de la base de datos</label>
    <input id="db_name" name="db_name" value="<?= $v('db_name') ?>"
           placeholder="inte_panelws" required>
    <p class="hint">Se creará si no existe.</p>

    <label for="db_user">Usuario MySQL</label>
    <input id="db_user" name="db_user" value="<?= $v('db_user') ?>" required>

    <label for="db_pass">Contraseña MySQL</label>
    <input id="db_pass" name="db_pass" type="password" value="<?= $v('db_pass') ?>">

    <h2>👤 Primer usuario administrador</h2>

    <div class="row2">
      <div>
        <label for="ag_user">Usuario (login)</label>
        <input id="ag_user" name="ag_user" value="<?= $v('ag_user') ?>" required>
      </div>
      <div>
        <label for="ag_name">Nombre completo</label>
        <input id="ag_name" name="ag_name" value="<?= $v('ag_name') ?>" required>
      </div>
    </div>

    <label for="ag_email">Correo electrónico</label>
    <input id="ag_email" name="ag_email" type="email" value="<?= $v('ag_email') ?>" required>

    <div class="row2">
      <div>
        <label for="ag_pass">Contraseña</label>
        <input id="ag_pass" name="ag_pass" type="password" minlength="8" required>
      </div>
      <div>
        <label for="ag_pass2">Confirmar contraseña</label>
        <input id="ag_pass2" name="ag_pass2" type="password" minlength="8" required>
      </div>
    </div>
    <p class="hint">Mínimo 8 caracteres.</p>

    <button class="btn" type="submit">Instalar Panel →</button>
  </form>

<?php endif; ?>
</div>
</body>
</html>
