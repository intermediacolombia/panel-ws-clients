<?php
// ================= CONFIG =================
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', false);

$repoPath = __DIR__; // 👈 MISMA CARPETA
$secret = "mKJGdLBEXD0c92kWVD1uuETHIw7PHMRCwI7QCRmOC74GMECTL10ctmDJ5MzkJ9f8";

// ============== EJECUCIÓN GIT ==============
if (isset($_GET['run']) && isset($_GET['token'])) {

    if ($_GET['token'] !== $secret) {
        http_response_code(403);
        exit("Acceso denegado");
    }

    header('Content-Type: text/plain');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');

    if (!is_dir($repoPath . '/.git')) {
        echo "❌ No es un repositorio git válido\n";
        exit;
    }

    chdir($repoPath);

    $process = popen("git pull origin main 2>&1", "r");

    if (!$process) {
        echo "❌ Error ejecutando git pull\n";
        exit;
    }

    echo "🚀 Iniciando actualización...\n\n";

    while (!feof($process)) {
        echo fgets($process);
        ob_flush();
        flush();
        usleep(100000);
    }

    pclose($process);

    echo "\n\n✅ Proceso finalizado.";
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Deploy Git</title>
<style>
body {
    font-family: monospace;
    background: #111;
    color: #00ff88;
    padding: 20px;
}
button {
    padding: 12px 20px;
    font-size: 16px;
    cursor: pointer;
    background: #00ff88;
    border: none;
    border-radius: 5px;
    font-weight: bold;
}
#log {
    background: #000;
    padding: 15px;
    margin-top: 20px;
    height: 350px;
    overflow: auto;
    border: 1px solid #00ff88;
    white-space: pre-wrap;
}
</style>
</head>
<body>

<h2>🚀 Deploy Automático Git</h2>

<button onclick="runDeploy()">Actualizar ahora</button>

<pre id="log"></pre>

<script>
function runDeploy() {
    const log = document.getElementById('log');
    log.textContent = "";

    const xhr = new XMLHttpRequest();
    xhr.open('GET', '?run=1&token=<?= $secret ?>', true);

    xhr.onprogress = function () {
        log.textContent = xhr.responseText;
        log.scrollTop = log.scrollHeight;
    };

    xhr.send();
}
</script>

</body>
</html>