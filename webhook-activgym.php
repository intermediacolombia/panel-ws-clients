<?php
/**
 * ============================================================
 *  WEBHOOK WHATSAPP — ACTIVGYM
 *  Bot idéntico al original + integración con Panel de Agentes.
 *  Estados: MySQL bot_estados (en lugar de JSON file).
 *  Asesor:  Panel controla la conversación (incoming.php).
 *  Horarios: Dinámicos desde BD del panel (settings)
 * ============================================================
 */

// ══════════════════════════════════════════════════════════════
//  CONFIGURACIÓN DEL PANEL (solo lo que SysGym no provee)
// ══════════════════════════════════════════════════════════════
define('PANEL_URL',     'https://whatsapp.activgym.com.co');  // URL pública del panel de ActivGym
define('AGENT_SECRET',  '3RYj2gjSBiusBKlHZBq2btRK77B8dPDYb8pV2SiaHykvvXD4j8v7e2kd1HIGCl9i');               // Copiar de config.php del panel de ActivGym
define('GYM_CLIENT_ID', 'activgym');                         // Debe coincidir con client_id de la API WA
define('GYM_DEPT_SLUG', 'atencion');                         // Slug del departamento en el panel
define('GYM_LOG_FILE',  __DIR__ . '/webhook-activgym.log');

// BD del panel de ActivGym (distinta a la BD de SysGym)
define('PANEL_DB_HOST', 'intermediahost.co');
define('PANEL_DB_PORT', '3306');
define('PANEL_DB_NAME', 'inte_whatsapp_activ');
define('PANEL_DB_USER', 'inte_whatsapp_activ');
define('PANEL_DB_PASS', '+GLMoLvFhcn-ssvq');

// ── Config de SysGym — provee: NAME_GYM, TEL_GYM, WA_API_URL,
//    DAYS_ALLOWED_BEFORE_DUE, EXCLUDE_WS_MENU, URLBASE, db() ─
require_once '/home/activgym/app.activgym.com.co/inc/config.php';

// La API key viene como variable desde SysGym config
define('GYM_WA_API_KEY', $api_ws);

// ── Timeouts de estado ───────────────────────────────────────
define('MENU_TIMEOUT_SECS',   5 * 60);
define('ASESOR_TIMEOUT_SECS', 45 * 60);

// ── Horarios del gym (por defecto, se puede sobreescribir desde BD) ──
define('HORARIOS_GYM_DEFAULT',
    "🏋️ *" . NAME_GYM . "* — Horarios de atención\n\n" .
    "📅 *Lunes a Viernes*\n" .
    "   ⏰ 5:00 AM – 10:00 PM\n\n" .
    "📅 *Sábados*\n" .
    "   ⏰ 7:00 AM – 2:00 PM\n\n" .
    "🚫 *Domingos:* Cerramos para recargar energías junto a ti. ¡Descansa que mañana volvemos con todo! 💤\n\n" .
    "💡 *¿Sin tiempo entre semana?* ¡De lunes a viernes tenemos 17 horas seguidas para que no tengas excusas! 😄\n\n" .
    "📍 ¡Te esperamos con las puertas abiertas y toda la energía! 💪🔥\n\n" .
    "Escribe *Menú* para volver al menú principal."
);

// ════════════════════════════════════════════════════════════════
//  UTILIDADES
// ════════════════════════════════════════════════════════════════
function wlog(string $msg): void
{
    file_put_contents(GYM_LOG_FILE, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

function esReset(string $mensaje): bool
{
    return (bool)preg_match('/^\s*(0|cancelar|menu|menú|inicio|volver)\s*$/i', $mensaje);
}

function esSaludo(string $mensajeLower): bool
{
    return (bool)preg_match('/\b(hola|hi|buenas|buenos dias|buenas tardes|buenas noches|start)\b/i', $mensajeLower);
}

function esSalidaAsesor(string $mensaje): bool
{
    return (bool)preg_match('/^\s*(menu|menú)\s*$/i', $mensaje);
}

// ════════════════════════════════════════════════════════════════
//  CONFIGURACIÓN DE HORARIOS DESDE BD (tabla settings)
// ════════════════════════════════════════════════════════════════

/**
 * Lee todos los settings de la BD con caché estático por request.
 */
function getPanelSettings(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;

    try {
        $pdo = panelDb();
        $stmt = $pdo->query('SELECT setting_key, setting_value FROM settings');
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $cache = is_array($rows) ? $rows : [];
    } catch (PDOException $e) {
        wlog("DB error getPanelSettings: " . $e->getMessage());
        $cache = [];
    }
    return $cache;
}

/**
 * Verifica si el gimnasio está abierto según horarios configurados en BD.
 * Si no hay configuración, usa valores por defecto.
 */
function gimnasioAbierto(): bool
{
    $settings = getPanelSettings();

    // 1. ¿Hay forzado manual?
    $force = $settings['force_schedule'] ?? 'auto';
    if ($force === 'open')   return true;
    if ($force === 'closed') return false;

    // 2. Leer horarios configurados
    $hoursJson = $settings['business_hours'] ?? null;
    $tz        = $settings['timezone']       ?? 'America/Bogota';

    if (!$hoursJson) {
        wlog("Settings: business_hours no encontrado, usando fallback hardcodeado");
        return _gimnasioAbiertoFallback();
    }

    $hours = json_decode($hoursJson, true);
    if (!is_array($hours)) {
        wlog("Settings: business_hours JSON inválido, usando fallback");
        return _gimnasioAbiertoFallback();
    }

    try {
        $ahora = new DateTime('now', new DateTimeZone($tz));
    } catch (Exception $e) {
        $ahora = new DateTime('now', new DateTimeZone('America/Bogota'));
    }

    $dia  = $ahora->format('N'); // 1=Lun … 7=Dom
    $hora = $ahora->format('H:i');

    $diaConfig = $hours[$dia] ?? null;
    if (!$diaConfig || empty($diaConfig['open'])) {
        return false;
    }

    return $hora >= $diaConfig['start'] && $hora < $diaConfig['end'];
}

/**
 * Fallback si la tabla settings no existe o está vacía
 * (valores originales de ActivGym)
 */
function _gimnasioAbiertoFallback(): bool
{
    $ahora       = new DateTime('now', new DateTimeZone('America/Bogota'));
    $diaSemana   = (int)$ahora->format('N');
    $horaDecimal = (int)$ahora->format('G') + ((int)$ahora->format('i') / 60);

    if ($diaSemana === 7)     return false;
    elseif ($diaSemana === 6) return ($horaDecimal >= 7  && $horaDecimal < 14);
    else                      return ($horaDecimal >= 5  && $horaDecimal < 22);
}

/**
 * Retorna texto amigable del próximo momento de apertura
 * basado en los horarios configurados en la BD.
 */
function cuandoAbreGimnasio(): string
{
    $settings  = getPanelSettings();
    $hoursJson = $settings['business_hours'] ?? null;
    $tz        = $settings['timezone']       ?? 'America/Bogota';

    if (!$hoursJson) return _cuandoAbreFallback();

    $hours = json_decode($hoursJson, true);
    if (!is_array($hours)) return _cuandoAbreFallback();

    try {
        $ahora = new DateTime('now', new DateTimeZone($tz));
    } catch (Exception $e) {
        $ahora = new DateTime('now', new DateTimeZone('America/Bogota'));
    }

    $nombresDia = [1=>'lunes',2=>'martes',3=>'miércoles',4=>'jueves',5=>'viernes',6=>'sábado',7=>'domingo'];

    // Buscar el próximo slot abierto en los próximos 7 días
    for ($i = 1; $i <= 7; $i++) {
        $check     = clone $ahora;
        $check->modify("+{$i} day");
        $dia       = (int)$check->format('N');
        $diaConfig = $hours[$dia] ?? null;

        if ($diaConfig && !empty($diaConfig['open'])) {
            $start  = $diaConfig['start'] ?? '08:00';
            $startF = ltrim($start, '0') ?: '0';   // "08:00" → "8:00"
            $nombre = $nombresDia[$dia] ?? '';

            if ($i === 1) {
                return "mañana *{$nombre} desde las {$startF}*";
            }
            return "el *{$nombre} desde las {$startF}*";
        }
    }

    return "próximamente";
}

function _cuandoAbreFallback(): string
{
    $ahora     = new DateTime('now', new DateTimeZone('America/Bogota'));
    $diaSemana = (int)$ahora->format('N');
    $hora      = (int)$ahora->format('G');

    if ($diaSemana === 7) {
        $cuando = "el *lunes desde las 5:00 AM*";
    } elseif ($diaSemana === 6) {
        $cuando = $hora < 7 ? "hoy *desde las 7:00 AM*" : "el *lunes desde las 5:00 AM*";
    } else {
        if ($hora < 5) {
            $cuando = "hoy *desde las 5:00 AM*";
        } else {
            $manana    = clone $ahora;
            $manana->modify('+1 day');
            $diaMañana = (int)$manana->format('N');
            if ($diaMañana === 6)     $cuando = "mañana *sábado desde las 7:00 AM*";
            elseif ($diaMañana === 7) $cuando = "el *lunes desde las 5:00 AM*";
            else                      $cuando = "mañana *desde las 5:00 AM*";
        }
    }
    return $cuando;
}

/**
 * Genera el texto de horario para los mensajes de ausencia,
 * basado en la configuración de la BD.
 */
function horarioGymTexto(): string
{
    $settings  = getPanelSettings();
    $hoursJson = $settings['business_hours'] ?? null;

    if (!$hoursJson) return _horarioTextoFallback();

    $hours = json_decode($hoursJson, true);
    if (!is_array($hours)) return _horarioTextoFallback();

    $nombresDia = [1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes',6=>'Sábado',7=>'Domingo'];
    $lineas     = [];

    foreach ($hours as $d => $cfg) {
        if (!empty($cfg['open'])) {
            $start  = $cfg['start'] ?? '08:00';
            $end    = $cfg['end']   ?? '18:00';
            $nombre = $nombresDia[(int)$d] ?? "Día $d";
            $lineas[] = "   {$nombre}: {$start} – {$end}";
        }
    }

    if (empty($lineas)) return _horarioTextoFallback();

    return "🕐 *Horario de atención:*\n" . implode("\n", $lineas) . "\n\n";
}

function _horarioTextoFallback(): string
{
    return
        "🕐 *Horario de atención:*\n" .
        "   Lunes a Viernes: 5:00 AM – 10:00 PM\n" .
        "   Sábados: 7:00 AM – 2:00 PM\n" .
        "   Domingos: Cerrado\n\n";
}

function mensajeAusencia(): string
{
    $settings = getPanelSettings();
    $personalizado = trim($settings['out_of_hours_message'] ?? '');
    
    if ($personalizado !== '') {
        return $personalizado . "\n\nEscribe *Menú* para volver al menú principal.";
    }

    $cuando = cuandoAbreGimnasio();
    
    return
        "🌙 *¡Ups! Fuera de horario.*\n\n" .
        "En este momento nuestros asesores no están disponibles. 😴\n\n" .
        "Estaremos de vuelta {$cuando} con toda la energía para atenderte. 💪\n\n" .
        horarioGymTexto() .
        "📋 Mientras tanto puedes:\n" .
        "   • Consultar tu plan → escribe *3*\n" .
        "   • Realizar tu pago → escribe *4*\n" .
        "   • Ver nuestros horarios → escribe *2*\n\n" .
        "Escribe *Menú* para volver al menú principal.";
}

function obtenerHorariosGym(): string
{
    $settings = getPanelSettings();
    $hoursJson = $settings['business_hours'] ?? null;
    
    if (!$hoursJson) {
        return HORARIOS_GYM_DEFAULT;
    }
    
    $hours = json_decode($hoursJson, true);
    if (!is_array($hours)) {
        return HORARIOS_GYM_DEFAULT;
    }
    
    $nombresDia = [1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes',6=>'Sábado',7=>'Domingo'];
    $lineas = [];
    
    foreach ($hours as $d => $cfg) {
        if (!empty($cfg['open'])) {
            $start = $cfg['start'] ?? '08:00';
            $end = $cfg['end'] ?? '18:00';
            $nombre = $nombresDia[(int)$d] ?? "Día $d";
            $lineas[] = "📅 *{$nombre}*: {$start} – {$end}";
        } elseif ((int)$d === 7) {
            $lineas[] = "📅 *Domingos*: Cerramos para recargar energías junto a ti. ¡Descansa que mañana volvemos con todo! 💤";
        }
    }
    
    $horarioTxt = "🏋️ *" . NAME_GYM . "* — Horarios de atención\n\n" . implode("\n\n", $lineas);
    
    $horarioTxt .= "\n\n💡 *¿Sin tiempo entre semana?* ¡Tenemos amplios horarios para que no tengas excusas! 😄\n\n";
    $horarioTxt .= "📍 ¡Te esperamos con las puertas abiertas y toda la energía! 💪🔥\n\n";
    $horarioTxt .= "Escribe *Menú* para volver al menú principal.";
    
    return $horarioTxt;
}

// ════════════════════════════════════════════════════════════════
//  ENVÍO WS — texto o texto + PDF
// ════════════════════════════════════════════════════════════════
function wsSend(string $telefono, string $mensaje, ?string $pdfUrl = null): bool
{
    $payload = ['phonenumber' => $telefono, 'text' => $mensaje];
    if ($pdfUrl) $payload['url'] = $pdfUrl;

    $ch = curl_init(rtrim(WA_API_URL, '/') . '/send');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . GYM_WA_API_KEY,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $success = ($code >= 200 && $code < 300 && !empty(json_decode($response, true)['success']));
    wlog("wsSend $telefono HTTP=$code " . ($success ? 'OK' : 'FAIL') . ($pdfUrl ? ' [PDF]' : '') . ' msg=' . mb_substr($mensaje, 0, 60));
    return $success;
}

// ════════════════════════════════════════════════════════════════
//  BD DEL PANEL (bot_estados, conversations, agents, departments, settings)
// ════════════════════════════════════════════════════════════════
function panelDb(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . PANEL_DB_HOST . ';port=' . PANEL_DB_PORT
             . ';dbname=' . PANEL_DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, PANEL_DB_USER, PANEL_DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

// ════════════════════════════════════════════════════════════════
//  ESTADOS — MySQL bot_estados (BD del panel)
// ════════════════════════════════════════════════════════════════
function obtenerEstado(string $sesKey): ?array
{
    try {
        $stmt = panelDb()->prepare('SELECT estado, data, timestamp FROM bot_estados WHERE ses_key = ? LIMIT 1');
        $stmt->execute([$sesKey]);
        $row  = $stmt->fetch();

        if (!$row) return null;

        $elapsed = time() - (int)$row['timestamp'];
        $estado  = $row['estado'];
        $limite  = $estado === 'asesor'
            ? ASESOR_TIMEOUT_SECS
            : (in_array($estado, ['espera_doc_plan', 'espera_doc_pago', 'espera_doc_cert'])
                ? MENU_TIMEOUT_SECS * 2
                : MENU_TIMEOUT_SECS);

        if ($elapsed > $limite) {
            panelDb()->prepare('DELETE FROM bot_estados WHERE ses_key = ?')->execute([$sesKey]);
            wlog("Estado expirado: $sesKey ($estado)");
            return null;
        }

        $data = json_decode($row['data'], true);
        return ['estado' => $estado, 'data' => is_array($data) ? $data : [], 'timestamp' => (int)$row['timestamp']];

    } catch (PDOException $e) {
        wlog("obtenerEstado DB error: " . $e->getMessage());
        return null;
    }
}

function guardarEstado(string $sesKey, ?string $nuevoEstado, array $data = []): void
{
    try {
        $pdo = panelDb();
        if ($nuevoEstado === null) {
            $pdo->prepare('DELETE FROM bot_estados WHERE ses_key = ?')->execute([$sesKey]);
            wlog("Estado borrado: $sesKey");
        } else {
            $pdo->prepare(
                'INSERT INTO bot_estados (ses_key, estado, data, timestamp, updated_at)
                 VALUES (?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE estado=VALUES(estado), data=VALUES(data),
                                         timestamp=VALUES(timestamp), updated_at=NOW()'
            )->execute([$sesKey, $nuevoEstado, json_encode($data), time()]);
            wlog("Estado: $sesKey → $nuevoEstado");
        }
    } catch (PDOException $e) {
        wlog("guardarEstado DB error: " . $e->getMessage());
    }
}

function resetMenu(string $sesKey, string $nombre): string
{
    guardarEstado($sesKey, 'menu_principal');
    wlog("RESET menu_principal: $sesKey");
    return menuPrincipal($nombre);
}

// ════════════════════════════════════════════════════════════════
//  INTEGRACIÓN CON EL PANEL
// ════════════════════════════════════════════════════════════════

function notifyPanel(string $phone, string $name, string $message, string $messageType, string $area): void
{
    $payload = json_encode([
        'phone'       => $phone,
        'name'        => $name,
        'message'     => $message,
        'messageType' => $messageType,
        'clientId'    => GYM_CLIENT_ID,
        'area'        => $area,
        'direction'   => 'in',
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init(PANEL_URL . '/incoming.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Agent-Secret: ' . AGENT_SECRET,
        ],
        CURLOPT_POSTFIELDS => $payload,
    ]);
    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);

    wlog("notifyPanel HTTP=$code area=$area phone=$phone" . ($err ? " err=$err" : '') . ' resp=' . substr($response ?? '', 0, 80));
}

function panelSetBot(string $phone): void
{
    try {
        $convKey = GYM_CLIENT_ID . '_' . $phone;
        panelDb()->prepare(
            "UPDATE conversations
             SET status = 'bot', agent_id = NULL, unread_count = 0, updated_at = NOW()
             WHERE conv_key = ? AND status IN ('pending','attending')"
        )->execute([$convKey]);
        wlog("panelSetBot: $convKey → bot");
    } catch (PDOException $e) {
        wlog("panelSetBot DB error: " . $e->getMessage());
    }
}

function panelDevolvioAlBot(string $phone): bool
{
    try {
        $convKey = GYM_CLIENT_ID . '_' . $phone;
        $stmt    = panelDb()->prepare('SELECT status FROM conversations WHERE conv_key = ? LIMIT 1');
        $stmt->execute([$convKey]);
        $row = $stmt->fetch();
        return $row && in_array($row['status'], ['bot', 'resolved']);
    } catch (PDOException $e) {
        wlog("panelDevolvioAlBot DB error: " . $e->getMessage());
        return false;
    }
}

function notificarAsesor(string $nombreCliente, string $from, string $motivo): void
{
    $msg =
        "🔔 *Alerta de atención — " . NAME_GYM . "*\n\n" .
        "El cliente *{$nombreCliente}* solicita atención por WhatsApp.\n\n" .
        "📌 Motivo: {$motivo}\n" .
        "📞 Número: {$from}\n\n" .
        "_Por favor atender a la brevedad._";

    $numeros = [];
    try {
        $stmt = panelDb()->prepare(
            "SELECT DISTINCT a.phone
             FROM agents a
             JOIN agent_departments ad ON ad.agent_id = a.id
             JOIN departments d ON d.id = ad.department_id
             WHERE d.slug = ?
               AND a.wa_alerts = 1
               AND a.status = 'active'
               AND a.phone IS NOT NULL AND a.phone <> ''"
        );
        $stmt->execute([GYM_DEPT_SLUG]);
        $numeros = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        wlog("notificarAsesor DB error: " . $e->getMessage());
    }

    if (empty($numeros)) {
        wlog("notificarAsesor: sin destinatarios para dept=" . GYM_DEPT_SLUG);
        return;
    }

    $endpoint = rtrim(WA_API_URL, '/') . '/send';
    foreach ($numeros as $numero) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . GYM_WA_API_KEY,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode(['phonenumber' => $numero, 'text' => $msg], JSON_UNESCAPED_UNICODE),
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        wlog("notificarAsesor => $numero HTTP=$code motivo=$motivo cliente=$nombreCliente ($from)");
    }
}

// ════════════════════════════════════════════════════════════════
//  MENÚ PRINCIPAL
// ════════════════════════════════════════════════════════════════
function menuPrincipal(string $nombre = ''): string
{
    $saludo = $nombre
        ? "¡Hola *{$nombre}*! 👋 Bienvenido a *" . NAME_GYM . "*\n\n"
        : "👋 ¡Bienvenido a *" . NAME_GYM . "*!\n\n";

    return $saludo .
        "Estamos aquí para ayudarte a lograr tus metas. ¿En qué te podemos ayudar hoy?\n\n" .
        "1️⃣  Ver Planes\n" .
        "2️⃣  Horarios\n" .
        "3️⃣  Consultar mi Plan\n" .
        "4️⃣  Realizar Pago\n" .
        "5️⃣  Certificado de Inscripción\n" .
        "6️⃣  Hablar con un Asesor\n\n" .
        "_Escribe el número de la opción que deseas._";
}

// ════════════════════════════════════════════════════════════════
//  CONSULTAS BD DEL GIMNASIO — usa db() de SysGym config
// ════════════════════════════════════════════════════════════════
function planesDisponibles(): string
{
    try {
        $rows = db()->query(
            "SELECT nombre, precio FROM planes
              WHERE estado='activo' AND borrado=0
              HAVING precio > 0
              ORDER BY precio ASC"
        )->fetchAll();

        if (empty($rows)) {
            return "⚠️ No hay planes disponibles en este momento. Escríbenos *Menú* para volver.";
        }

        $txt = "💪 *NUESTROS PLANES — " . NAME_GYM . "*\n\n";
        foreach ($rows as $p) {
            if ((float)$p['precio'] <= 0) continue;
            $precio = '$' . number_format((float)$p['precio'], 0, ',', '.');
            $txt   .= "▸ *{$p['nombre']}*\n  💰 {$precio}\n\n";
        }
        $txt .= "¡El mejor momento para empezar es hoy! 🏆\n\n";
        $txt .= "Escribe *Asesor* para hablar con alguien de nuestro equipo\no *Menú* para volver al menú principal.";
        return $txt;

    } catch (Exception $e) {
        wlog("ERROR planesDisponibles: " . $e->getMessage());
        return "⚠️ No fue posible cargar los planes en este momento. Intenta más tarde.";
    }
}

function consultarPlanCliente(string $doc): string
{
    try {
        $st = db()->prepare(
            "SELECT c.nombres, c.apellidos, c.vencimiento_plan, c.congelado,
                    p.nombre AS plan_nombre, p.precio AS plan_precio
               FROM clientes c LEFT JOIN planes p ON p.id = c.plan
              WHERE c.identificacion = :doc AND c.borrado = 0 LIMIT 1"
        );
        $st->execute([':doc' => $doc]);
        $c = $st->fetch();

        if (!$c) {
            return
                "❌ No encontramos ningún cliente con el documento *{$doc}*.\n\n" .
                "Verifica que sea correcto e inténtalo de nuevo, o escribe *Menú* para volver.\n\n" .
                "Si aún no eres miembro, ¡es el momento perfecto para unirte! 💪";
        }

        $nombre  = trim($c['nombres'] . ' ' . $c['apellidos']);
        $hoy     = new DateTime(date('Y-m-d'));
        $venc    = new DateTime($c['vencimiento_plan']);
        $diff    = (int)$hoy->diff($venc)->format('%r%a');
        $vTxt    = $venc->format('d/m/Y');

        if ($c['congelado']) {
            $est = "🧊 *MEMBRESÍA CONGELADA*";
            $consejo = "Contáctanos para reactivar tu plan y retomar tu entrenamiento. 💪";
        } elseif ($diff < 0) {
            $est = "🔴 *VENCIDA*";
            $consejo = "¡No pierdas tu ritmo! Renueva tu plan y sigue entrenando. 🏃";
        } elseif ($diff === 0) {
            $est = "🟡 *Vence HOY*";
            $consejo = "Renueva hoy para no perder ni un día de entrenamiento. ⚡";
        } elseif ($diff <= 5) {
            $est = "🟡 *Vence pronto*";
            $consejo = "¡Renueva pronto y mantén tu racha! 🔥";
        } else {
            $est = "🟢 *ACTIVA*";
            $consejo = "¡Sigue así, vas muy bien! 💪";
        }

        $pNombre = $c['plan_nombre'] ?? 'Sin plan asignado';
        $pPrecio = $c['plan_precio'] ? '$' . number_format($c['plan_precio'], 0, ',', '.') : '—';

        return
            "👤 *{$nombre}*\n\n" .
            "📋 Plan: *{$pNombre}*\n" .
            "💰 Valor: {$pPrecio}\n" .
            "📅 Vencimiento: {$vTxt}\n" .
            "Estado: {$est}\n\n" .
            "_{$consejo}_\n\n" .
            "Escribe *Menú* para volver al menú principal.";

    } catch (Exception $e) {
        wlog("ERROR consultarPlanCliente: " . $e->getMessage());
        return "⚠️ Ocurrió un error al consultar. Por favor intenta más tarde.";
    }
}

function gestionarPago(string $doc): string
{
    try {
        $st = db()->prepare(
            "SELECT c.nombres, c.apellidos, c.vencimiento_plan, c.congelado
               FROM clientes c WHERE c.identificacion = :doc AND c.borrado = 0 LIMIT 1"
        );
        $st->execute([':doc' => $doc]);
        $c = $st->fetch();

        if (!$c) {
            return
                "❌ No encontramos ningún cliente con el documento *{$doc}*.\n\n" .
                "Verifica que sea correcto e inténtalo de nuevo, o escribe *Menú* para volver.\n\n" .
                "¿Aún no eres miembro? ¡Escríbenos y te ayudamos a inscribirte! 🏋️";
        }

        if ($c['congelado']) {
            return
                "🧊 Tu membresía está *congelada*.\n\n" .
                "Contáctanos para reactivarla y volver a entrenar. ¡Te esperamos!\n\n" .
                "Escribe *Asesor* para hablar con un asesor.";
        }

        $hoy    = new DateTime(date('Y-m-d'));
        $venc   = new DateTime($c['vencimiento_plan']);
        $diff   = (int)$hoy->diff($venc)->format('%r%a');
        $nombre = trim($c['nombres'] . ' ' . $c['apellidos']);
        $vTxt   = $venc->format('d/m/Y');
        $dias   = DAYS_ALLOWED_BEFORE_DUE;

        if ($diff <= $dias) {
            $link = rtrim(URLBASE, '/') . "/pay/?doc={$doc}";
            return
                "✅ ¡Hola *{$nombre}*! Tu pago ya está disponible.\n\n" .
                "🔗 *Enlace de pago:*\n{$link}\n\n" .
                "📅 Vencimiento actual: *{$vTxt}*\n\n" .
                "_El proceso es rápido, seguro y en línea._ 🔒\n\n" .
                "¡Gracias por renovar tu compromiso con tu salud! 💪\n\n" .
                "Escribe *Menú* para volver al menú principal.";
        }

        $en = $diff - $dias;
        return
            "⏳ ¡Hola *{$nombre}*! Tu pago aún no está habilitado.\n\n" .
            "📅 Tu plan vence el *{$vTxt}*.\n\n" .
            "El sistema habilita el pago *{$dias} día(s) antes* del vencimiento.\n" .
            "Podrás realizar tu pago en aproximadamente *{$en} día(s)*.\n\n" .
            "_Te avisaremos cuando esté disponible._ 😊\n\n" .
            "Escribe *Menú* para volver al menú principal.";

    } catch (Exception $e) {
        wlog("ERROR gestionarPago: " . $e->getMessage());
        return "⚠️ Ocurrió un error al procesar. Por favor intenta más tarde.";
    }
}

function generarCertificado(string $doc, string $telefono): array
{
    try {
        $st = db()->prepare(
            "SELECT id, nombres, apellidos, congelado, vencimiento_plan
               FROM clientes WHERE identificacion = :doc AND borrado = 0 LIMIT 1"
        );
        $st->execute([':doc' => $doc]);
        $c = $st->fetch();

        if (!$c) {
            return [
                "❌ No encontramos ningún cliente con el documento *{$doc}*.\n\n" .
                "Verifica que sea correcto e inténtalo de nuevo, o escribe *Menú* para volver.\n\n" .
                "Si aún no eres miembro, ¡es el momento perfecto para unirte! 💪",
                null
            ];
        }

        if ($c['congelado']) {
            return [
                "🧊 Tu membresía está *congelada*.\n\n" .
                "No es posible generar el certificado mientras tu plan esté congelado.\n\n" .
                "Contáctanos para reactivar tu membresía. 💪\n\n" .
                "Escribe *Menú* para volver al menú principal.",
                null
            ];
        }

        $hoy  = new DateTime(date('Y-m-d'));
        $venc = new DateTime($c['vencimiento_plan']);
        if ($hoy > $venc) {
            return [
                "🔴 Tu membresía está *vencida*.\n\n" .
                "No es posible generar el certificado con un plan vencido.\n\n" .
                "Renueva tu plan y vuelve a intentarlo. 🏃\n\n" .
                "Escribe *4* para realizar tu pago o *Menú* para volver.",
                null
            ];
        }

        $clienteId = $c['id'];
        $nombre    = trim($c['nombres'] . ' ' . $c['apellidos']);
        $baseUrl   = rtrim(URLBASE, '/');
        $pdfSrcUrl = $baseUrl . '/pdf/?type=cert&id=' . $clienteId;

        $tempDir = __DIR__ . '/uploads/certs_activgym/';
        if (!is_dir($tempDir)) mkdir($tempDir, 0755, true);

        $pdfFilename = 'cert_' . $clienteId . '_' . time() . '.pdf';
        $pdfFilePath = $tempDir . $pdfFilename;
        $pdfUrl      = PANEL_URL . '/uploads/certs_activgym/' . $pdfFilename;

        $ch2 = curl_init($pdfSrcUrl);
        curl_setopt_array($ch2, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $pdfContent = curl_exec($ch2);
        $pdfCode    = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        $pdfError   = curl_error($ch2);
        curl_close($ch2);

        wlog("CERT download: HTTP=$pdfCode err=$pdfError bytes=" . strlen($pdfContent ?: ''));

        if ($pdfContent === false || $pdfCode !== 200 || strlen($pdfContent) < 100) {
            return [
                "⚠️ No fue posible generar tu certificado en este momento.\n\n" .
                "Por favor intenta más tarde o escribe *Menú* para volver.",
                null
            ];
        }

        file_put_contents($pdfFilePath, $pdfContent);
        wlog("CERT generado: cliente=$clienteId doc=$doc archivo=$pdfFilename");

        return [
            "🏅 *¡Tu Certificado de Inscripción está listo!*\n\n" .
            "👤 *{$nombre}*\n\n" .
            "Tu certificado ha sido generado exitosamente. 🎉\n\n" .
            "_Guárdalo o compártelo cuando lo necesites._\n\n" .
            "💪 ¡Sigue entrenando con todo!\n\n" .
            "Escribe *Menú* para volver al menú principal.",
            $pdfUrl
        ];

    } catch (Exception $e) {
        wlog("ERROR generarCertificado: " . $e->getMessage());
        return ["⚠️ Ocurrió un error al generar el certificado. Por favor intenta más tarde.", null];
    }
}

// ════════════════════════════════════════════════════════════════
//  ENTRADA DEL WEBHOOK
// ════════════════════════════════════════════════════════════════
$rawInput = file_get_contents('php://input');
wlog("RECIBIDO: $rawInput");

$data = json_decode($rawInput, true);
if (!$data) { http_response_code(400); exit('Invalid JSON'); }

$from        = trim($data['from']        ?? '');
$jid         = trim($data['jid']         ?? '');
$mensaje     = trim($data['message']     ?? '');
$nombre      = $data['pushName']          ?? '';
$clientId    = $data['client_id']         ?? GYM_CLIENT_ID;
$messageId   = $data['messageId']         ?? '';
$messageType = $data['messageType']       ?? 'text';

// Para ENVIAR (bot y panel): conservar el jid tal como viene.
// La API acepta @s.whatsapp.net y @lid. Solo usar $from si no hay jid.
$telefono = !empty($jid) ? $jid : $from;

// Guardar from original para decidir qué phone usar en el panel
$fromOriginal = $from;

// Normalizar $from a número limpio (solo para sesión y clave interna).
// Prioridad: número del @s.whatsapp.net > quitar @lid de from > from tal cual.
if (!empty($jid) && strpos($jid, '@s.whatsapp.net') !== false) {
    $from = explode('@', $jid)[0];       // número real desde jid confiable
} elseif (strpos($from, '@') !== false) {
    $from = explode('@', $from)[0];      // quitar sufijo @lid u otro
}
$from = preg_replace('/[^0-9+]/', '', $from);
// Fallback si from llegó vacío: usar parte numérica del jid como identificador de sesión
if (empty($from) && !empty($jid)) {
    $from = preg_replace('/[^0-9+]/', '', explode('@', $jid)[0]);
}

// Phone que se envía al panel para guardar en conversations.phone y poder responder:
// - Si from original era un número real (sin @): usar el número limpio
// - Si from era vacío o tenía @lid: usar el jid completo (ej: "280...@lid")
//   para que apiSend pueda enviarlo correctamente cuando el agente responda
$phoneForPanel = ($fromOriginal !== '' && strpos($fromOriginal, '@') === false)
    ? $from       // número real normalizado
    : $telefono;  // jid completo (@lid o @s.whatsapp.net)

wlog("[$clientId] from normalizado: $from  |  jid: $jid  |  telefono envío: $telefono  |  phone panel: $phoneForPanel");

// Sesión siempre basada en número normalizado para evitar duplicados LID vs número
$sesKey = $from . '_' . GYM_CLIENT_ID;

// ── Anti-duplicados ──────────────────────────────────────────
if (!empty($messageId)) {
    try {
        $dupKey = '_dup_' . $messageId;
        $stmt   = panelDb()->prepare('SELECT timestamp FROM bot_estados WHERE ses_key = ? LIMIT 1');
        $stmt->execute([$dupKey]);
        $dup = $stmt->fetch();

        if ($dup && (time() - (int)$dup['timestamp']) < 300) {
            wlog("Duplicado ignorado: $messageId");
            http_response_code(200); exit('OK');
        }
        panelDb()->prepare(
            'INSERT INTO bot_estados (ses_key, estado, data, timestamp) VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE timestamp=VALUES(timestamp)'
        )->execute([$dupKey, '_dup', '[]', time()]);
    } catch (PDOException $e) {
        wlog("Anti-dup DB error: " . $e->getMessage());
    }
}

// ── Números excluidos ────────────────────────────────────────
if (!empty(EXCLUDE_WS_MENU)) {
    $excluidos = array_map('trim', explode(',', EXCLUDE_WS_MENU));
    $jidLimpio = explode('@', $jid)[0];
    if (in_array($from, $excluidos) || in_array($jidLimpio, $excluidos)) {
        wlog("[$clientId] Excluido: $from");
        http_response_code(200); exit('OK');
    }
    foreach ($excluidos as $excl) {
        if (mb_strtolower(trim($nombre)) === mb_strtolower($excl)) {
            wlog("[$clientId] Excluido por nombre: $nombre");
            http_response_code(200); exit('OK');
        }
    }
}

if (empty($from)) { http_response_code(200); exit('OK'); }

// ── Mensaje vacío (multimedia, sticker, audio, etc.) ──────────
if (empty($mensaje)) {
    if ($messageType === 'text') {
        wlog("[$clientId] Doble evento text vacío ignorado");
        http_response_code(200); exit('OK');
    }

    $sesDataTemp = obtenerEstado($sesKey);
    $estadoTemp  = $sesDataTemp['estado'] ?? null;

    if ($estadoTemp === 'asesor') {
        notifyPanel($phoneForPanel, $nombre, '[' . $messageType . ']', $messageType, 'Atención al Cliente');
        wlog("[$clientId] Multimedia con asesor activo — notificando panel");
        http_response_code(200); exit('OK');
    }

    $avisoTipo = [
        'image'    => '🖼️ imagen',
        'audio'    => '🎵 audio',
        'video'    => '🎬 video',
        'sticker'  => '😄 sticker',
        'document' => '📄 documento',
    ];
    $tipoTexto = $avisoTipo[$messageType] ?? '📎 archivo multimedia';
    wlog("[$clientId] Multimedia ($messageType) — recordando opciones");
    wsSend($telefono,
        "Recibí tu {$tipoTexto}, pero por este canal solo puedo responder mensajes de texto. 😊\n\n" .
        "Por favor elige una opción escribiendo el número:\n\n" .
        "1️⃣  Ver Planes\n" .
        "2️⃣  Horarios\n" .
        "3️⃣  Consultar mi Plan\n" .
        "4️⃣  Realizar Pago\n" .
        "5️⃣  Certificado de Inscripción\n" .
        "6️⃣  Hablar con un Asesor\n\n" .
        "_Escribe el número de la opción que deseas._"
    );
    http_response_code(200); exit('OK');
}

$mensajeLower = mb_strtolower($mensaje);
wlog("[$clientId] $telefono ($nombre) → \"$mensaje\"");

// Estado previo (antes de verificar expiración)
$estadoPrevio = null;
try {
    $stmt = panelDb()->prepare('SELECT estado FROM bot_estados WHERE ses_key = ? LIMIT 1');
    $stmt->execute([$sesKey]);
    $row          = $stmt->fetch();
    $estadoPrevio = $row ? $row['estado'] : null;
} catch (PDOException $e) { /* ignorar */ }

$sesData = obtenerEstado($sesKey);
$estado  = $sesData['estado'] ?? null;
wlog("[$clientId] Estado: " . ($estado ?? 'NINGUNO') . ($estadoPrevio && !$estado ? " (expiró: $estadoPrevio)" : ''));

$respuesta = null;
$pdfUrl    = null;

// ── A. Estado ASESOR activo ───────────────────────────────────
if ($estado === 'asesor') {

    if (panelDevolvioAlBot($from)) {
        wlog("[$clientId] Panel devolvió control al bot — reseteando estado");
        guardarEstado($sesKey, null);
        $respuesta = resetMenu($sesKey, $nombre);

    } elseif (esSalidaAsesor($mensaje)) {
        wlog("[$clientId] Salida de asesor por MENÚ");
        panelSetBot($from);
        $respuesta = resetMenu($sesKey, $nombre);

    } else {
        notifyPanel($phoneForPanel, $nombre, $mensaje, $messageType, 'Atención al Cliente');
        wlog("[$clientId] Silenciado — asesor activo");
        http_response_code(200); exit('OK');
    }

// ── B. Palabra "asesor" → verificar horario ──────────────────
} elseif (preg_match('/^\s*asesor\s*$/i', $mensajeLower)) {
    if (gimnasioAbierto()) {
        $respuesta =
            "🧑‍💼 *¡Conectando con un asesor!*\n\n" .
            "En breve alguien de nuestro equipo en *" . NAME_GYM . "* te atenderá personalmente.\n\n" .
            "📞 También puedes llamarnos al: *" . TEL_GYM . "*\n\n" .
            "_Por favor espera, no es necesario escribir más._ 😊\n\n" .
            "Escribe *Menú* si deseas volver al menú principal.";
        guardarEstado($sesKey, 'asesor', ['solicitado' => time()]);
        notifyPanel($phoneForPanel, $nombre, $mensaje, 'text', 'Atención al Cliente');
        notificarAsesor($nombre, $from, "Solicitud directa de asesor");
        wlog("[$clientId] ASESOR por palabra clave: $nombre ($telefono)");
    } else {
        $respuesta = mensajeAusencia();
        guardarEstado($sesKey, 'menu_principal');
        wlog("[$clientId] Asesor fuera de horario: $nombre");
    }

// ── C. Reset o saludo ────────────────────────────────────────
} elseif (esReset($mensaje) || esSaludo($mensajeLower)) {
    wlog("[$clientId] Reset por: \"$mensaje\"");
    $respuesta = resetMenu($sesKey, $nombre);

// ── D. Menú principal ─────────────────────────────────────────
} elseif ($estado === 'menu_principal') {
    switch ($mensaje) {
        case '1':
            $respuesta = planesDisponibles();
            guardarEstado($sesKey, 'menu_principal');
            break;

        case '2':
            $respuesta = obtenerHorariosGym();
            guardarEstado($sesKey, 'menu_principal');
            break;

        case '3':
            $respuesta =
                "🔍 *Consultar mi Plan*\n\n" .
                "Por favor envíame tu *número de documento* de identidad.\n\n" .
                "⚠️ Solo números, sin espacios, sin puntos, sin comas.\n" .
                "_Ejemplo: 123456789_\n\n" .
                "Escribe *Cancelar* si deseas volver al menú.";
            guardarEstado($sesKey, 'espera_doc_plan');
            break;

        case '4':
            $respuesta =
                "💳 *Realizar Pago*\n\n" .
                "Por favor envíame tu *número de documento* de identidad.\n\n" .
                "⚠️ Solo números, sin espacios, sin puntos, sin comas.\n" .
                "_Ejemplo: 123456789_\n\n" .
                "Escribe *Cancelar* si deseas volver al menú.";
            guardarEstado($sesKey, 'espera_doc_pago');
            break;

        case '5':
            $respuesta =
                "🏅 *Certificado de Inscripción*\n\n" .
                "Por favor envíame tu *número de documento* de identidad.\n\n" .
                "⚠️ Solo números, sin espacios, sin puntos, sin comas.\n" .
                "_Ejemplo: 123456789_\n\n" .
                "Escribe *Cancelar* si deseas volver al menú.";
            guardarEstado($sesKey, 'espera_doc_cert');
            break;

        case '6':
            if (gimnasioAbierto()) {
                $respuesta =
                    "🧑‍💼 *¡Conectando con un asesor!*\n\n" .
                    "En breve alguien de nuestro equipo en *" . NAME_GYM . "* te atenderá personalmente.\n\n" .
                    "📞 También puedes llamarnos al: *" . TEL_GYM . "*\n\n" .
                    "_Por favor espera, no es necesario escribir más._ 😊\n\n" .
                    "Escribe *Menú* si deseas volver al menú principal.";
                guardarEstado($sesKey, 'asesor', ['solicitado' => time()]);
                notifyPanel($phoneForPanel, $nombre, $mensaje, 'text', 'Atención al Cliente');
                notificarAsesor($nombre, $from, "Opción 6 — Solicitud de asesor");
                wlog("[$clientId] ASESOR SOLICITADO: $nombre ($telefono)");
            } else {
                $respuesta = mensajeAusencia();
                guardarEstado($sesKey, 'menu_principal');
                wlog("[$clientId] Asesor fuera de horario (opción 6): $nombre");
            }
            break;

        default:
            $respuesta = "⚠️ No reconocemos esa opción.\n\n" . menuPrincipal($nombre);
            guardarEstado($sesKey, 'menu_principal');
    }

// ── E. Esperando documento — consultar plan ───────────────────
} elseif ($estado === 'espera_doc_plan') {
    if (preg_match('/^\d{5,15}$/', $mensaje)) {
        $respuesta = consultarPlanCliente($mensaje);
        wlog("[$clientId] CONSULTA PLAN doc=$mensaje");
        guardarEstado($sesKey, 'menu_principal');
    } else {
        $respuesta =
            "⚠️ El documento ingresado no es válido.\n\n" .
            "Por favor envía *solo números*, sin espacios ni caracteres especiales.\n" .
            "_Ejemplo: 123456789_\n\n" .
            "Inténtalo de nuevo o escribe *Cancelar* para volver al menú.";
        guardarEstado($sesKey, 'espera_doc_plan');
    }

// ── F. Esperando documento — pago ────────────────────────────
} elseif ($estado === 'espera_doc_pago') {
    if (preg_match('/^\d{5,15}$/', $mensaje)) {
        $respuesta = gestionarPago($mensaje);
        wlog("[$clientId] SOLICITUD PAGO doc=$mensaje");
        guardarEstado($sesKey, 'menu_principal');
    } else {
        $respuesta =
            "⚠️ El documento ingresado no es válido.\n\n" .
            "Por favor envía *solo números*, sin espacios ni caracteres especiales.\n" .
            "_Ejemplo: 123456789_\n\n" .
            "Inténtalo de nuevo o escribe *Cancelar* para volver al menú.";
        guardarEstado($sesKey, 'espera_doc_pago');
    }

// ── G. Esperando documento — certificado ─────────────────────
} elseif ($estado === 'espera_doc_cert') {
    if (preg_match('/^\d{5,15}$/', $mensaje)) {
        wlog("[$clientId] SOLICITUD CERT doc=$mensaje");
        wsSend($telefono, "⏳ Generando tu certificado, un momento por favor...");
        [$textoCert, $pdfUrl] = generarCertificado($mensaje, $telefono);
        guardarEstado($sesKey, 'menu_principal');

        if ($pdfUrl) {
            $chPdf = curl_init(rtrim(WA_API_URL, '/') . '/send');
            curl_setopt_array($chPdf, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode([
                    'phonenumber' => $telefono,
                    'text'        => '📎 Certificado de Inscripción',
                    'url'         => $pdfUrl,
                ], JSON_UNESCAPED_UNICODE),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . GYM_WA_API_KEY,
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
            ]);
            $respPdf = curl_exec($chPdf);
            $codePdf = curl_getinfo($chPdf, CURLINFO_HTTP_CODE);
            curl_close($chPdf);
            $okPdf = ($codePdf >= 200 && $codePdf < 300 && !empty(json_decode($respPdf, true)['success']));
            wlog("[$clientId] CERT send HTTP=$codePdf ok=" . ($okPdf ? 'SI' : 'NO'));
            wsSend($telefono, $textoCert);
        } else {
            wsSend($telefono, $textoCert);
        }
        http_response_code(200); exit('OK');

    } else {
        $respuesta =
            "⚠️ El documento ingresado no es válido.\n\n" .
            "Por favor envía *solo números*, sin espacios ni caracteres especiales.\n" .
            "_Ejemplo: 123456789_\n\n" .
            "Inténtalo de nuevo o escribe *Cancelar* para volver al menú.";
        guardarEstado($sesKey, 'espera_doc_cert');
    }

// ── H. Sin estado (primera vez o expirado) ───────────────────
} else {
    wlog("[$clientId] Sin estado — menú inicial");
    if ($estadoPrevio === 'asesor') {
        wlog("[$clientId] Sesión asesor expirada — retomando bot");
        panelSetBot($from);
        $respuesta =
            "⏱️ _Tu conversación con el asesor finalizó por inactividad._\n\n" .
            "El bot retomó la atención. Si necesitas más ayuda, estamos aquí. 😊\n\n" .
            resetMenu($sesKey, $nombre);
    } else {
        $respuesta = resetMenu($sesKey, $nombre);
    }
}

// ── Enviar respuesta ──────────────────────────────────────────
if ($respuesta) {
    if (!wsSend($telefono, $respuesta, $pdfUrl)) {
        wlog("[$clientId] ERROR enviando a $telefono");
    }
}

http_response_code(200);
echo 'OK';
