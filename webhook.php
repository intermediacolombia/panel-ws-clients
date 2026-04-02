<?php
/**
 * ============================================================
 *  WEBHOOK WHATSAPP — INTERMEDIA HOST
 *  Estados migrados a MySQL (tabla bot_estados).
 *  Notifica al panel en incoming.php al activar modo asesor.
 *  Verifica si el panel devolvió control al bot antes de silenciar.
 * ============================================================
 */

// ── Constantes propias del webhook ──────────────────────────
define('API_KEY',  'e9a745d149950ff4650681538bc7a385ae1f6b9311ac59c109a7198f4b2adc32');
define('API_URL',  'https://api.intermediahost.co/api/send');
define('LOG_FILE', __DIR__ . '/webhook-log.txt');

define('MENU_TIMEOUT_SECS',   10 * 60);
define('ASESOR_TIMEOUT_SECS', 45 * 60);

// ── Incluir config y DB del panel ───────────────────────────
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// ════════════════════════════════════════════════════════════════
//  LOG
// ════════════════════════════════════════════════════════════════
function wlog($msg)
{
    file_put_contents(LOG_FILE, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

// ════════════════════════════════════════════════════════════════
//  DETECCIÓN DE PALABRAS CLAVE
// ════════════════════════════════════════════════════════════════
function esReset($mensaje)
{
    return (bool)preg_match('/^\s*(menu|menú|inicio|volver|cancelar)\s*$/i', $mensaje);
}

function esSaludo($mensajeLower)
{
    return (bool)preg_match('/\b(hola|hi|buenos dias|buenas tardes|buenas noches|start)\b/i', $mensajeLower);
}

function esSalidaAsesor($mensaje)
{
    return (bool)preg_match('/^\s*(menu|menú)\s*$/i', $mensaje);
}

// ════════════════════════════════════════════════════════════════
//  CONFIGURACIÓN DESDE BD (tabla settings)
// ════════════════════════════════════════════════════════════════

/**
 * Lee todos los settings de la BD con caché estático por request.
 * Si la BD falla, retorna array vacío (funciones usan fallback hardcodeado).
 */
function getSettings(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;

    try {
        $pdo  = DB::get();
        $stmt = $pdo->query('SELECT setting_key, setting_value FROM settings');
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $cache = is_array($rows) ? $rows : [];
    } catch (PDOException $e) {
        wlog("DB error getSettings: " . $e->getMessage());
        $cache = [];
    }
    return $cache;
}

// ════════════════════════════════════════════════════════════════
//  CONTROL DE HORARIO (lee de BD, fallback a valores hardcodeados)
// ════════════════════════════════════════════════════════════════

function empresaAbierta(): bool
{
    $settings = getSettings();

    // 1. ¿Hay forzado manual?
    $force = $settings['force_schedule'] ?? 'auto';
    if ($force === 'open')   return true;
    if ($force === 'closed') return false;

    // 2. Leer horarios configurados
    $hoursJson = $settings['business_hours'] ?? null;
    $tz        = $settings['timezone']       ?? 'America/Bogota';

    if (!$hoursJson) {
        wlog("Settings: business_hours no encontrado, usando fallback hardcodeado");
        return _empresaAbiertaFallback();
    }

    $hours = json_decode($hoursJson, true);
    if (!is_array($hours)) {
        wlog("Settings: business_hours JSON inválido, usando fallback");
        return _empresaAbiertaFallback();
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

/** Fallback si la tabla settings no existe o está vacía */
function _empresaAbiertaFallback(): bool
{
    $ahora       = new DateTime('now', new DateTimeZone('America/Bogota'));
    $diaSemana   = (int)$ahora->format('N');
    $horaDecimal = (int)$ahora->format('G') + ((int)$ahora->format('i') / 60);

    if ($diaSemana === 7)     return false;
    elseif ($diaSemana === 6) return ($horaDecimal >= 8 && $horaDecimal < 14);
    else                      return ($horaDecimal >= 8 && $horaDecimal < 18);
}

/**
 * Retorna texto amigable del próximo momento de apertura
 * basado en los horarios configurados en la BD.
 */
function cuandoVuelven(): string
{
    $settings  = getSettings();
    $hoursJson = $settings['business_hours'] ?? null;
    $tz        = $settings['timezone']       ?? 'America/Bogota';

    if (!$hoursJson) return _cuandoVuelvenFallback();

    $hours = json_decode($hoursJson, true);
    if (!is_array($hours)) return _cuandoVuelvenFallback();

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

function _cuandoVuelvenFallback(): string
{
    $ahora     = new DateTime('now', new DateTimeZone('America/Bogota'));
    $diaSemana = (int)$ahora->format('N');
    $hora      = (int)$ahora->format('G');

    if ($diaSemana === 7) {
        return "el *lunes desde las 8:00*";
    } elseif ($diaSemana === 6) {
        return $hora < 8 ? "hoy *desde las 8:00*" : "el *lunes desde las 8:00*";
    } else {
        if ($hora < 8) return "hoy *desde las 8:00*";
        $manana    = clone $ahora;
        $manana->modify('+1 day');
        $diaMañana = (int)$manana->format('N');
        if ($diaMañana === 6)     return "mañana *sábado desde las 8:00*";
        elseif ($diaMañana === 7) return "el *lunes desde las 8:00*";
        else                      return "mañana *desde las 8:00*";
    }
}

/**
 * Genera el texto de horario para los mensajes de ausencia,
 * basado en la configuración de la BD.
 */
function horarioTexto(): string
{
    $settings  = getSettings();
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
        "   Lunes a Viernes: 8:00 AM – 6:00 PM\n" .
        "   Sábados: 8:00 AM – 2:00 PM\n" .
        "   Domingos y Festivos: solo tickets\n\n";
}

/**
 * Mensaje de ausencia personalizado si está configurado en BD,
 * o null si se debe usar el mensaje por defecto.
 */
function mensajeAusenciaPersonalizado(): ?string
{
    $settings = getSettings();
    $msg      = trim($settings['out_of_hours_message'] ?? '');
    return $msg !== '' ? $msg : null;
}

function mensajeAusenciaVentas(): string
{
    $personalizado = mensajeAusenciaPersonalizado();
    if ($personalizado !== null) {
        return $personalizado . "\n\nEscribe *Menú* para volver al menú principal.";
    }

    return
        "😴 *En este momento no estamos disponibles.*\n\n" .
        "Estaremos de vuelta " . cuandoVuelven() . ".\n\n" .
        horarioTexto() .
        "Si deseas asesoría para comprar servicios, déjanos un mensaje y te respondemos en cuanto estemos disponibles. 🙂\n\n" .
        "🎫 *Asistencia técnica 24/7 vía ticket:*\n" .
        "https://clientes.intermediahost.co/submitticket.php\n\n" .
        "Escribe *Menú* para volver al menú principal.";
}

function mensajeAusenciaSoporte($servicio = '')
{
    $srv = $servicio ? " para *{$servicio}*" : "";
    return
        "😴 *En este momento no hay técnicos disponibles{$srv}.*\n\n" .
        "Estaremos de vuelta " . cuandoVuelven() . ".\n\n" .
        horarioTexto() .
        "🎫 *¿Necesitas ayuda urgente? ¡Abre un ticket ahora!*\n" .
        "Nuestro equipo lo revisará a la brevedad:\n" .
        "https://clientes.intermediahost.co/submitticket.php\n\n" .
        "👤 También puedes ingresar al área de cliente:\n" .
        "http://clientes.intermediahost.co\n\n" .
        "Escribe *Menú* para volver al menú principal.";
}

// ════════════════════════════════════════════════════════════════
//  ENVÍO WS
// ════════════════════════════════════════════════════════════════
function wsSend($destino, $mensaje)
{
    $payload = ['text' => $mensaje];
    if (strpos($destino, '@') !== false) {
        $payload['jid'] = $destino;
    } else {
        $payload['phonenumber'] = $destino;
    }

    $ch = curl_init(API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . API_KEY,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $success = false;
    if ($code >= 200 && $code < 300) {
        $decoded = json_decode($response, true);
        $success = !empty($decoded['success']);
    }
    wlog("wsSend $destino HTTP=$code success=" . ($success ? 'SI' : 'NO'));
    return $success;
}

// ════════════════════════════════════════════════════════════════
//  NOTIFICACIÓN INTERNA AL ASESOR (WA)
//  Envía alerta por WhatsApp a todos los agentes del departamento
//  que tengan wa_alerts=1 y phone configurado en la BD.
//  $deptSlug: slug del departamento ('ventas','soporte','pagos','otros')
// ════════════════════════════════════════════════════════════════
function notificarAsesor($nombreCliente, $from, $motivo, $deptSlug = null)
{
    $msg =
        "🔔 *Alerta de atención*\n\n" .
        "El cliente *{$nombreCliente}* está intentando contactar por WhatsApp.\n\n" .
        "📌 Motivo: {$motivo}\n" .
        "📞 Número: {$from}\n\n" .
        "_Por favor atender a la brevedad._";

    // Obtener teléfonos de agentes con alertas activas del departamento indicado
    $numeros = [];
    try {
        $pdo = DB::get();

        if ($deptSlug) {
            // Agentes del departamento específico con wa_alerts=1
            $stmt = $pdo->prepare(
                "SELECT DISTINCT a.phone
                 FROM agents a
                 JOIN agent_departments ad ON ad.agent_id = a.id
                 JOIN departments d ON d.id = ad.department_id
                 WHERE d.slug = ?
                   AND a.wa_alerts = 1
                   AND a.status = 'active'
                   AND a.phone IS NOT NULL
                   AND a.phone <> ''"
            );
            $stmt->execute([$deptSlug]);
        } else {
            // Sin departamento: todos los agentes con alertas activas
            $stmt = $pdo->query(
                "SELECT phone FROM agents
                 WHERE wa_alerts = 1 AND status = 'active'
                   AND phone IS NOT NULL AND phone <> ''"
            );
        }
        $numeros = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        wlog("notificarAsesor DB error: " . $e->getMessage());
    }

    if (empty($numeros)) {
        wlog("notificarAsesor: sin destinatarios para dept=$deptSlug motivo=$motivo");
        return;
    }

    foreach ($numeros as $numero) {
        $ch = curl_init(API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . API_KEY,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'phonenumber' => $numero,
                'text'        => $msg,
            ], JSON_UNESCAPED_UNICODE),
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        wlog("notificarAsesor => $numero HTTP=$code dept=$deptSlug motivo=$motivo cliente=$nombreCliente ($from)");
    }
}

// ════════════════════════════════════════════════════════════════
//  NOTIFICAR AL PANEL (incoming.php)
//  Llama al panel web para que registre la conversación y
//  notifique a los agentes del departamento correspondiente.
// ════════════════════════════════════════════════════════════════
function notifyPanel($phone, $name, $message, $messageType, $clientId, $area,
                     $fileUrl = '', $caption = '', $mediaBase64 = null, $mimetype = '', $filename = '')
{
    $payload = [
        'phone'       => $phone,
        'name'        => $name,
        'message'     => $message,
        'messageType' => $messageType,
        'clientId'    => $clientId,
        'area'        => $area,
        'direction'   => 'in',
        'fileUrl'     => $fileUrl,
        'caption'     => $caption,
    ];
    // Campos nuevos solo si hay media base64
    if ($mediaBase64 !== null && $mediaBase64 !== '') {
        $payload['mediaBase64'] = $mediaBase64;
        $payload['mimetype']    = $mimetype;
        $payload['filename']    = $filename;
        unset($payload['fileUrl']); // no mezclar con legado
    }
    $payload = json_encode($payload, JSON_UNESCAPED_UNICODE);

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

    if ($err) {
        wlog("notifyPanel cURL error: $err");
    } else {
        wlog("notifyPanel HTTP=$code area=$area phone=$phone resp=" . substr($response, 0, 100));
    }
}

// ════════════════════════════════════════════════════════════════
//  DEVOLVER CONVERSACIÓN AL BOT EN EL PANEL
//  El usuario escribió "menú" mientras estaba con un asesor.
//  Actualiza directamente la BD para que el panel refleje status=bot.
// ════════════════════════════════════════════════════════════════
function panelSetBot($phone, $clientId)
{
    try {
        $pdo  = DB::get();
        $pdo->prepare(
            "UPDATE conversations
             SET status = 'bot', updated_at = NOW()
             WHERE phone = ? AND status IN ('attending','pending')"
        )->execute([$phone]);
        wlog("panelSetBot: $phone → bot");
    } catch (PDOException $e) {
        wlog("DB error panelSetBot: " . $e->getMessage());
    }
}

/**
 * Auto-resuelve una conversación desde el bot (sin agente).
 * Usado para comprobantes fuera de horario: el sistema cierra la conv
 * automáticamente después de confirmar la recepción del pago.
 */
function panelSetResolved($phone)
{
    try {
        $pdo = DB::get();
        $pdo->prepare(
            "UPDATE conversations
             SET status = 'resolved', resolved_at = NOW(), agent_id = NULL,
                 assigned_at = NULL, updated_at = NOW()
             WHERE phone = ? AND status IN ('attending','pending','bot')"
        )->execute([$phone]);
        wlog("panelSetResolved: $phone → resolved (auto)");
    } catch (PDOException $e) {
        wlog("DB error panelSetResolved: " . $e->getMessage());
    }
}

// ════════════════════════════════════════════════════════════════
//  CONSULTAR ESTADO DE CONVERSACIÓN EN EL PANEL
//  Busca por teléfono directamente (sin depender del client_id)
//  para mayor compatibilidad.
//  Retorna: 'attending', 'pending', 'bot', 'resolved', o null
// ════════════════════════════════════════════════════════════════
function panelConvStatus($phone)
{
    try {
        $pdo  = DB::get();
        // Buscar por número de teléfono directamente, sin depender del conv_key
        $stmt = $pdo->prepare(
            'SELECT status FROM conversations WHERE phone = ? ORDER BY updated_at DESC LIMIT 1'
        );
        $stmt->execute([$phone]);
        $row = $stmt->fetch();
        return $row ? $row['status'] : null;
    } catch (PDOException $e) {
        wlog("DB error panelConvStatus: " . $e->getMessage());
        return null;
    }
}

function panelDevolvioBot($phone, $clientId)
{
    return panelConvStatus($phone) === 'bot';
}

function panelEstaAtendiendo($phone)
{
    return panelConvStatus($phone) === 'attending';
}

// ════════════════════════════════════════════════════════════════
//  GESTIÓN DE ESTADOS EN MYSQL (reemplaza JSON)
// ════════════════════════════════════════════════════════════════

/**
 * Lee el estado RAW sin verificar expiración.
 * Usado para detectar si una sesión expiró entre mensajes.
 */
function obtenerEstadoRaw($key)
{
    try {
        $pdo  = DB::get();
        $stmt = $pdo->prepare('SELECT estado FROM bot_estados WHERE ses_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['estado'] : null;
    } catch (PDOException $e) {
        wlog("DB error obtenerEstadoRaw: " . $e->getMessage());
        return null;
    }
}

/**
 * Lee el estado verificando expiración.
 * Elimina el registro si expiró.
 * Retorna null si no existe o expiró.
 */
function obtenerEstado($key)
{
    try {
        $pdo  = DB::get();
        $stmt = $pdo->prepare('SELECT * FROM bot_estados WHERE ses_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        if (!$row) return null;

        $elapsed = time() - (int)$row['timestamp'];
        $limite  = $row['estado'] === 'asesor' ? ASESOR_TIMEOUT_SECS : MENU_TIMEOUT_SECS;

        if ($elapsed > $limite) {
            $pdo->prepare('DELETE FROM bot_estados WHERE ses_key = ?')->execute([$key]);
            wlog("Estado expirado y eliminado: $key (era: {$row['estado']})");
            return null;
        }

        return [
            'estado'    => $row['estado'],
            'data'      => json_decode($row['data'], true) ?: [],
            'timestamp' => (int)$row['timestamp'],
        ];
    } catch (PDOException $e) {
        wlog("DB error obtenerEstado: " . $e->getMessage());
        return null;
    }
}

/**
 * Guarda o elimina un estado en bot_estados.
 * $nuevoEstado = null → eliminar el registro.
 */
function guardarEstado($key, $nuevoEstado, array $data = [])
{
    try {
        $pdo = DB::get();

        if ($nuevoEstado === null) {
            $pdo->prepare('DELETE FROM bot_estados WHERE ses_key = ?')->execute([$key]);
            wlog("Estado borrado: $key");
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO bot_estados (ses_key, estado, data, timestamp)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   estado    = VALUES(estado),
                   data      = VALUES(data),
                   timestamp = VALUES(timestamp)'
            );
            $stmt->execute([
                $key,
                $nuevoEstado,
                json_encode($data, JSON_UNESCAPED_UNICODE),
                time(),
            ]);
            wlog("Estado guardado: $key → $nuevoEstado");
        }
    } catch (PDOException $e) {
        wlog("DB error guardarEstado: " . $e->getMessage());
    }
}

function resetMenu($key, $nombre)
{
    guardarEstado($key, 'menu_principal', []);
    wlog("RESET menu_principal: $key");
    return menuPrincipal($nombre);
}

// ════════════════════════════════════════════════════════════════
//  MENÚS
// ════════════════════════════════════════════════════════════════
function menuPrincipal($nombre = '')
{
    $saludo = $nombre ? "¡Hola *{$nombre}*! 👋\n\n" : "👋 ¡Bienvenido!\n\n";
    return $saludo .
        "🌐 *Bienvenido a InterMedia Host*\n\n" .
        "Selecciona el área que necesitas:\n\n" .
        "1️⃣  Ventas\n" .
        "2️⃣  Soporte Técnico\n" .
        "3️⃣  Ver medios de pago / Enviar comprobante\n" .
        "4️⃣  Otros\n\n" .
        "_Escribe solo el número de la opción._";
}

function menuVentas()
{
    return "💼 *ÁREA DE VENTAS*\n\n" .
        "¿Qué servicio te interesa?\n\n" .
        "1️⃣  Streaming Radio\n" .
        "2️⃣  Streaming AutoDJ\n" .
        "3️⃣  Streaming Video\n" .
        "4️⃣  Hosting Web\n" .
        "5️⃣  Dominios\n\n" .
        "Escribe *Menú* para volver al menú principal.";
}

function menuSoporte()
{
    return "🛠️ *SOPORTE TÉCNICO*\n\n" .
        "¿Con qué servicio necesitas ayuda?\n\n" .
        "1️⃣  Streaming Radio\n" .
        "2️⃣  Streaming AutoDJ\n" .
        "3️⃣  Streaming Video\n" .
        "4️⃣  Hosting Web\n" .
        "5️⃣  Dominios\n\n" .
        "Escribe *Menú* para volver al menú principal.";
}

function mediosDePago()
{
    return
        "Hola 👋, te compartimos los medios de pago disponibles para *Intermedia Host*:\n\n" .
        "💳 *Transferencia Bancaria:*\n" .
        "🏦 Bancolombia Ahorros: *29735308295*\n" .
        "🏦 Davivienda Ahorros: *488413242998*\n\n" .
        "🔑 *Llave Bre-B:*\n" .
        "intermediacolombia@gmail.com\n\n" .
        "📲 *Nequi o Daviplata:*\n" .
        "3147165269\n\n" .
        "🌐 *PayPal:*\n" .
        "intermediacolombia@gmail.com\n\n" .
        "Por favor, una vez realices el pago, no olvides enviar el *comprobante* para validar el pago y activar o renovar tu servicio.\n\n" .
        "¡Gracias por confiar en nosotros! 💻✨\n\n" .
        "📎 *Envía aquí tu comprobante* (imagen o PDF) y será registrado de inmediato.\n\n" .
        "📌 *Opciones disponibles:*\n" .
        "   • Escribe *Menú* si no deseas enviar el comprobante ahora.\n" .
        (empresaAbierta()
            ? "   • Escribe *asesor* si prefieres hablar directamente con un agente de ventas."
            : "   • Fuera de horario comercial — envía tu comprobante y lo procesamos en el próximo día hábil.");
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
$messageType = $data['messageType']      ?? 'text';
$nombre      = $data['pushName']         ?? '';
$clientId    = $data['client_id']        ?? (defined('WA_CLIENT_ID') ? WA_CLIENT_ID : 'default');
// Formato nuevo: mediaBase64. Formato legado: mediaUrl/fileUrl
$mediaBase64 = $data['mediaBase64']      ?? null;
$mimetypeRaw = trim($data['mimetype']    ?? '');
$mediaFilename = trim($data['filename']  ?? '');
$mediaUrl    = trim($data['mediaUrl']    ?? $data['url'] ?? $data['fileUrl'] ?? $data['media_url'] ?? '');
$caption     = trim($data['caption']     ?? $data['text'] ?? '');

// ── Normalizar número de teléfono ────────────────────────────
// WhatsApp a veces envía from="573001234567@lid" (LID de dispositivo vinculado)
// en lugar del número real. El jid "@s.whatsapp.net" siempre contiene el número correcto.
// Prioridad: jid @s.whatsapp.net → limpiar @sufijo del from → from tal cual
if (!empty($jid) && strpos($jid, '@s.whatsapp.net') !== false) {
    $from = explode('@', $jid)[0];          // "573001234567"
} elseif (strpos($from, '@') !== false) {
    $from = explode('@', $from)[0];          // quitar @lid u otro sufijo
}
// Limpiar caracteres no numéricos salvo el + inicial (por si acaso)
$from = preg_replace('/[^0-9+]/', '', $from);

wlog("[$clientId] from normalizado: $from  |  jid original: $jid");

// Log para verificar campo de media (solo si es multimedia)
if (!empty($messageType) && $messageType !== 'text') {
    $mediaInfo = $mediaBase64 !== null
        ? 'base64(' . strlen($mediaBase64) . 'chars) mime=' . $mimetypeRaw
        : 'url=' . ($mediaUrl ?: 'VACÍO');
    wlog("[$clientId] MEDIA FIELDS: type=$messageType $mediaInfo keys=" . implode(',', array_keys($data)));
}

// Para enviar mensajes: usar jid completo @s.whatsapp.net si está disponible
$destino = (strpos($jid, '@s.whatsapp.net') !== false) ? $jid : $from;

// Sesión siempre por número normalizado para evitar duplicados LID vs número
$sesKey = $from . '_' . $clientId;

if (empty($from)) { http_response_code(200); exit('OK'); }

wlog("[$clientId] $from ($nombre) tipo=$messageType → \"$mensaje\"");

// ── Multimedia / mensaje vacío ───────────────────────────────
// Entra si messageType no es texto (aunque lleve caption en $mensaje)
// o si el mensaje está vacío (doble-evento, stickers, etc.)
$esMultimedia = ($messageType !== 'text') && ($mediaBase64 !== null || $mediaUrl !== '' || empty($mensaje));
if ($esMultimedia || empty($mensaje)) {
    $messageType = $data['messageType'] ?? 'text';

    // Doble evento: type=text con message vacío → ignorar siempre
    if ($messageType === 'text') {
        wlog("[$clientId] Doble evento text vacío ignorado");
        http_response_code(200); exit('OK');
    }

    // Multimedia real
    $sesDataTemp = obtenerEstado($sesKey);
    $estadoTemp  = $sesDataTemp['estado'] ?? null;

    // Con asesor activo: verificar si el panel devolvió control al bot
    if ($estadoTemp === 'asesor') {
        if (panelDevolvioBot($from, $clientId)) {
            wlog("[$clientId] Panel devolvió control al bot (multimedia) — limpiando estado asesor");
            guardarEstado($sesKey, null);
            // Dejar que el flujo continúe abajo para procesar el mensaje
        } else {
            // Registrar el archivo en el panel para que el agente lo vea
            notifyPanel($from, $nombre, $mensaje, $messageType, $clientId, '',
                        $mediaUrl, $caption, $mediaBase64, $mimetypeRaw, $mediaFilename);
            wlog("[$clientId] Multimedia con asesor activo — registrado en panel");
            http_response_code(200); exit('OK');
        }
    }

    // Verificar si está esperando comprobante de pago
    $sesDataTemp2 = obtenerEstado($sesKey);
    $estadoTemp2  = $sesDataTemp2['estado'] ?? null;

    if ($estadoTemp2 === 'espera_comprobante' && in_array($messageType, ['image', 'document', 'video'])) {
        $abierto = empresaAbierta();
        wlog("[$clientId] Comprobante recibido ($messageType) — horario=" . ($abierto ? 'abierto' : 'cerrado'));

        // Registrar el comprobante en el panel (siempre, sin importar horario)
        notifyPanel($from, $nombre, $mensaje ?: '[comprobante]', $messageType, $clientId, 'Medios de Pago',
                    $mediaUrl, $caption, $mediaBase64, $mimetypeRaw, $mediaFilename);

        // Notificar al asesor por WA (siempre — los pagos son 24/7)
        notificarAsesor($nombre, $from, "Comprobante de pago recibido — pendiente validación", 'pagos');

        if ($abierto) {
            // ── En horario: queda pendiente para que el agente lo valide ──
            guardarEstado($sesKey, 'asesor', ['comprobante' => true]);

            $msgComprobante =
                "✅ *¡Comprobante recibido!*\n\n" .
                "Gracias por enviarlo. 🙏\n\n" .
                "Estamos en horario de atención — un asesor validará tu pago y te confirmará la activación o renovación de tu servicio en unos minutos.\n\n" .
                "⏳ _Por favor espera, no es necesario escribir más._\n\n" .
                "Escribe *Menú* si deseas volver al menú principal.";

            wsSend($destino, $msgComprobante);

        } else {
            // ── Fuera de horario: confirmar, resolver automáticamente y pasar al bot ──
            $msgComprobante =
                "✅ *¡Comprobante recibido!*\n\n" .
                "Gracias por enviarlo. 🙏\n\n" .
                "En este momento estamos fuera de horario comercial, pero *no te preocupes* — tu pago quedó registrado y entrará en revisión.\n\n" .
                "📧 *Recibirás la confirmación por correo electrónico* una vez sea procesado.\n\n" .
                horarioTexto() .
                "_No es necesario escribir más. Te notificaremos pronto._ 😊\n\n" .
                "Escribe *Menú* si deseas volver al menú principal.";

            wsSend($destino, $msgComprobante);

            // Auto-resolver la conversación y devolver al bot
            panelSetResolved($from);
            guardarEstado($sesKey, null); // bot retoma el control
            wlog("[$clientId] Comprobante fuera de horario — conversación auto-resuelta");
        }

        http_response_code(200); exit('OK');
    }

    // Sin asesor y sin espera de comprobante: avisar que solo se aceptan opciones del menú
    $avisoTipo = [
        'image'    => '🖼️ imagen',
        'audio'    => '🎵 audio',
        'video'    => '🎬 video',
        'sticker'  => '😄 sticker',
        'document' => '📄 documento',
    ];
    $tipoTexto = $avisoTipo[$messageType] ?? '📎 archivo multimedia';
    wlog("[$clientId] Multimedia ($messageType) — recordando opciones");
    wsSend($destino,
        "Recibí tu {$tipoTexto}, pero por este canal solo puedo responder mensajes de texto. 😊\n\n" .
        "Por favor elige una opción escribiendo el número:\n\n" .
        "1️⃣  Ventas\n" .
        "2️⃣  Soporte Técnico\n" .
        "3️⃣  Ver medios de pago / Enviar comprobante\n" .
        "4️⃣  Otros\n\n" .
        "_Escribe el número de la opción que deseas._"
    );
    http_response_code(200); exit('OK');
}

$mensajeLower = mb_strtolower($mensaje);

// Guardar estado previo ANTES de la verificación de expiración
$estadoPrevio = obtenerEstadoRaw($sesKey);

// Obtener estado actual (con verificación de expiración)
$sesData = obtenerEstado($sesKey);
$estado  = $sesData['estado'] ?? null;

// ── Sincronizar con el panel ──────────────────────────────────
// Si el panel tiene la conversación como 'attending' pero bot_estados
// no tiene estado 'asesor' (ej: conversación iniciada desde el panel),
// forzar estado 'asesor' y guardarlo para futuras peticiones.
if ($estado !== 'asesor' && panelEstaAtendiendo($from)) {
    wlog("[$clientId] Panel tiene conv 'attending' sin estado asesor — sincronizando");
    guardarEstado($sesKey, 'asesor');
    $sesData = ['estado' => 'asesor'];
    $estado  = 'asesor';
}

wlog("[$clientId] Estado: " . ($estado ?? 'NINGUNO') . ($estadoPrevio && !$estado ? " (expiró: $estadoPrevio)" : ''));

$respuesta = null;

// ── A. Estado ASESOR activo ───────────────────────────────────
if ($estado === 'asesor') {
    if (esSalidaAsesor($mensaje)) {
        // Usuario escribe "menú" → sale de modo asesor voluntariamente
        wlog("[$clientId] Salida de asesor por MENÚ");
        guardarEstado($sesKey, null);
        panelSetBot($from, $clientId);   // actualizar panel a modo bot
        $respuesta = resetMenu($sesKey, $nombre);
    } else {
        // Verificar si el panel ya devolvió el control al bot
        if (panelDevolvioBot($from, $clientId)) {
            wlog("[$clientId] Panel devolvió control al bot — retomando bot");
            guardarEstado($sesKey, null);
            // No notificar al panel: la conv ya está en status=bot, no reabrir ni alertar agentes
            $respuesta = resetMenu($sesKey, $nombre);
        } else {
            // Registrar SIEMPRE el mensaje (incluye saludos, palabras de activación, etc.)
            notifyPanel($from, $nombre, $mensaje, $messageType, $clientId, '');
            wlog("[$clientId] Asesor activo — mensaje registrado en panel: \"$mensaje\"");
            http_response_code(200); exit('OK');
        }
    }

// ── B. Reset por navegación o saludo ────────────────────────
} elseif (esReset($mensaje) || esSaludo($mensajeLower)) {
    wlog("[$clientId] Reset por: \"$mensaje\"");
    $respuesta = resetMenu($sesKey, $nombre);

// ── C. Palabras clave directas (bandera invisible) ────────────
} elseif (preg_match('/^\s*asesor\s*$/i', $mensajeLower)) {
    if (empresaAbierta()) {
        $respuesta =
            "⏱️ *Un momento por favor...*\n\n" .
            "Te vamos a contactar con un asesor que te ayudará con tu solicitud. 😊\n\n" .
            "📌 Mientras tanto puedes visitar:\n" .
            "🌐 https://www.intermediahost.co\n" .
            "👤 http://clientes.intermediahost.co\n\n" .
            "Escribe *Menú* si deseas volver al menú principal.";
        guardarEstado($sesKey, 'asesor', ['area' => 'Ventas']);
        wlog("[$clientId] KEYWORD ASESOR: $nombre ($from)");
        notifyPanel($from, $nombre, $mensaje, 'text', $clientId, 'Ventas');
        notificarAsesor($nombre, $from, "Solicitud directa — palabra clave \"asesor\"", 'ventas');
    } else {
        $respuesta = mensajeAusenciaVentas();
        guardarEstado($sesKey, 'menu_principal');
        wlog("[$clientId] KEYWORD ASESOR fuera de horario: $nombre");
    }

} elseif (preg_match('/^\s*soporte\s*$/i', $mensajeLower)) {
    if (empresaAbierta()) {
        $respuesta =
            "⏱️ *Un momento por favor...*\n\n" .
            "Te vamos a conectar con soporte técnico. 🛠️\n\n" .
            "📌 *Accesos útiles mientras esperas:*\n" .
            "👤 Área de Cliente: http://clientes.intermediahost.co\n" .
            "🎫 Crear Ticket: https://clientes.intermediahost.co/submitticket.php\n\n" .
            "Escribe *Menú* si deseas volver al menú principal.";
        guardarEstado($sesKey, 'asesor', ['area' => 'Soporte']);
        wlog("[$clientId] KEYWORD SOPORTE: $nombre ($from)");
        notifyPanel($from, $nombre, $mensaje, 'text', $clientId, 'Soporte Técnico');
        notificarAsesor($nombre, $from, "Solicitud directa — palabra clave \"soporte\"", 'soporte');
    } else {
        $respuesta = mensajeAusenciaSoporte();
        guardarEstado($sesKey, 'menu_principal');
        wlog("[$clientId] KEYWORD SOPORTE fuera de horario: $nombre");
    }

// ── D. Menú principal ─────────────────────────────────────────
} elseif ($estado === 'menu_principal') {
    switch ($mensaje) {
        case '1':
            if (empresaAbierta()) {
                $respuesta = menuVentas();
                guardarEstado($sesKey, 'menu_ventas');
            } else {
                $respuesta = mensajeAusenciaVentas();
                guardarEstado($sesKey, 'menu_principal');
                wlog("[$clientId] Ventas fuera de horario: $nombre");
            }
            break;
        case '2':
            $respuesta = menuSoporte();
            guardarEstado($sesKey, 'menu_soporte');
            break;
        case '3':
            $respuesta = mediosDePago();
            guardarEstado($sesKey, 'espera_comprobante');
            wlog("[$clientId] Medios de pago: $nombre ($from)");
            break;
        case '4':
            $respuesta =
                "📝 *OTROS TEMAS*\n\n" .
                "Por favor escribe tu consulta o solicitud.\n\n" .
                "Un asesor te responderá a la brevedad. 😊\n\n" .
                "Escribe *Menú* para volver al menú principal.";
            guardarEstado($sesKey, 'otros_solicitud');
            break;
        default:
            $respuesta = "⚠️ Opción no válida.\n\n" . menuPrincipal($nombre);
            guardarEstado($sesKey, 'menu_principal');
    }

// ── E. Menú Ventas ────────────────────────────────────────────
} elseif ($estado === 'menu_ventas') {
    $servicios = [
        '1' => 'Streaming Radio',
        '2' => 'Streaming AutoDJ',
        '3' => 'Streaming Video',
        '4' => 'Hosting Web',
        '5' => 'Dominios',
    ];

    if (isset($servicios[$mensaje])) {
        $servicio = $servicios[$mensaje];
        if (empresaAbierta()) {
            $respuesta =
                "⏱️ *Un momento por favor...*\n\n" .
                "Te vamos a conectar con un asesor de ventas que te ayudará con *{$servicio}*.\n\n" .
                "📌 Mientras tanto puedes visitar:\n" .
                "🌐 https://www.intermediahost.co\n" .
                "👤 http://clientes.intermediahost.co\n\n" .
                "Escribe *Menú* si deseas volver al menú principal.";
            guardarEstado($sesKey, 'asesor', ['servicio' => $servicio, 'area' => 'Ventas - ' . $servicio]);
            wlog("[$clientId] LEAD VENTAS: $nombre ($from) — $servicio");
            notifyPanel($from, $nombre, $mensaje, 'text', $clientId, 'Ventas - ' . $servicio);
            notificarAsesor($nombre, $from, "Ventas — {$servicio}", 'ventas');
        } else {
            $respuesta = mensajeAusenciaVentas();
            guardarEstado($sesKey, 'menu_principal');
            wlog("[$clientId] Ventas fuera de horario: $nombre — $servicio");
        }
    } else {
        $respuesta = "⚠️ Opción no válida.\n\n" . menuVentas();
    }

// ── F. Menú Soporte ───────────────────────────────────────────
} elseif ($estado === 'menu_soporte') {
    $servicios = [
        '1' => 'Streaming Radio',
        '2' => 'Streaming AutoDJ',
        '3' => 'Streaming Video',
        '4' => 'Hosting Web',
        '5' => 'Dominios',
    ];

    if (isset($servicios[$mensaje])) {
        $servicio = $servicios[$mensaje];
        if (empresaAbierta()) {
            $respuesta =
                "⏱️ *Un momento por favor...*\n\n" .
                "Te vamos a conectar con soporte técnico para *{$servicio}*.\n\n" .
                "📌 *Enlaces útiles:*\n" .
                "👤 Área de Cliente: http://clientes.intermediahost.co\n" .
                "🎫 Crear Ticket: https://clientes.intermediahost.co/submitticket.php\n\n" .
                "Escribe *Menú* si deseas volver al menú principal.";
        } else {
            $respuesta = mensajeAusenciaSoporte($servicio);
        }
        guardarEstado($sesKey, 'asesor', ['servicio' => $servicio, 'area' => 'Soporte - ' . $servicio]);
        wlog("[$clientId] TICKET SOPORTE: $nombre ($from) — $servicio");
        notifyPanel($from, $nombre, $mensaje, 'text', $clientId, 'Soporte - ' . $servicio);
        notificarAsesor($nombre, $from, "Soporte Técnico — {$servicio}", 'soporte');
    } else {
        $respuesta = "⚠️ Opción no válida.\n\n" . menuSoporte();
    }

// ── G. Espera comprobante — texto recibido ────────────────────
} elseif ($estado === 'espera_comprobante') {
    if (esReset($mensaje) || esSaludo($mensajeLower)) {
        $respuesta = resetMenu($sesKey, $nombre);
    } elseif (preg_match('/^\s*asesor\s*$/i', $mensajeLower) && empresaAbierta()) {
        // Usuario quiere hablar con un asesor en lugar de enviar comprobante
        $respuesta =
            "⏱️ *Un momento por favor...*\n\n" .
            "Te vamos a conectar con un asesor de ventas que te ayudará. 😊\n\n" .
            "📌 Mientras tanto puedes visitar:\n" .
            "🌐 https://www.intermediahost.co\n" .
            "👤 http://clientes.intermediahost.co\n\n" .
            "Escribe *Menú* si deseas volver al menú principal.";
        guardarEstado($sesKey, 'asesor', ['area' => 'Ventas']);
        notifyPanel($from, $nombre, $mensaje, 'text', $clientId, 'Ventas');
        notificarAsesor($nombre, $from, "Solicita asesor desde área de pagos", 'ventas');
    } elseif (preg_match('/^\s*asesor\s*$/i', $mensajeLower) && !empresaAbierta()) {
        // Escribe "asesor" pero fuera de horario
        $respuesta = mensajeAusenciaVentas();
        guardarEstado($sesKey, 'espera_comprobante');
    } else {
        $respuesta =
            "📎 Por favor envía tu *comprobante de pago* como imagen o PDF.\n\n" .
            "Si tienes alguna duda sobre los medios de pago escribe *3* para verlos de nuevo.\n\n" .
            "📌 *Opciones:*\n" .
            "   • Escribe *Menú* si no deseas enviar el comprobante ahora.\n" .
            (empresaAbierta()
                ? "   • Escribe *asesor* para hablar con un agente de ventas."
                : "   • Fuera de horario — envía tu comprobante y lo procesamos el próximo día hábil.");
        guardarEstado($sesKey, 'espera_comprobante');
    }

// ── H. Otros — esperando mensaje libre ───────────────────────
} elseif ($estado === 'otros_solicitud') {
    // Registrar siempre la consulta en el panel (queda como historial)
    notifyPanel($from, $nombre, $mensaje, 'text', $clientId, 'Otros');
    wlog("[$clientId] OTROS: $nombre ($from) — $mensaje");

    if (empresaAbierta()) {
        $respuesta =
            "⏱️ *Un momento por favor...*\n\n" .
            "Tu mensaje ha sido registrado y vamos a contactar con un asesor para que te ayude con tu solicitud. 😊\n\n" .
            "📌 Sitio web: https://www.intermediahost.co\n" .
            "👤 Área de cliente: http://clientes.intermediahost.co\n\n" .
            "Escribe *Menú* si deseas volver al menú principal.";
        guardarEstado($sesKey, 'asesor', ['consulta' => $mensaje, 'area' => 'Otros']);
        notificarAsesor($nombre, $from, "Otros — " . mb_substr($mensaje, 0, 60), 'otros');
    } else {
        $respuesta =
            "📩 *Mensaje recibido.*\n\n" .
            "Gracias por escribirnos. Tu consulta quedó registrada y te responderemos en cuanto estemos disponibles. 🙂\n\n" .
            "Estaremos de vuelta " . cuandoVuelven() . ".\n\n" .
            horarioTexto() .
            "🎫 *¿Necesitas soporte urgente? ¡Abre un ticket ahora!*\n" .
            "https://clientes.intermediahost.co/submitticket.php\n\n" .
            "Escribe *Menú* para volver al menú principal.";
        // Fuera de horario: limpiar estado para que el bot retome el control
        // y mensajes posteriores no se acumulen en el panel como si hubiera asesor
        guardarEstado($sesKey, null);
        wlog("[$clientId] OTROS fuera de horario — estado limpiado, bot retoma");
    }

// ── I. Sin estado (primera vez o expirado) ───────────────────
} else {
    wlog("[$clientId] Sin estado — menú inicial");
    if ($estadoPrevio === 'asesor') {
        wlog("[$clientId] Sesión asesor expirada — retomando bot");
        $respuesta =
            "⏱️ _Tu conversación con el asesor finalizó por inactividad._\n\n" .
            "El bot retomó la atención. Si necesitas más ayuda, estamos aquí. 😊\n\n" .
            resetMenu($sesKey, $nombre);
    } else {
        $respuesta = resetMenu($sesKey, $nombre);
    }
}

// ── Enviar ────────────────────────────────────────────────────
if ($respuesta) {
    if (!wsSend($destino, $respuesta)) {
        wlog("[$clientId] ERROR enviando a $destino");
    }
}

http_response_code(200);
echo 'OK';
