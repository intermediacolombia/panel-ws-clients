# ============================================
# BUILD SCRIPT - Panel de Agentes
# Uso: powershell -ExecutionPolicy Bypass -File build.ps1 -empresa intermedia
# Uso: powershell -ExecutionPolicy Bypass -File build.ps1 -empresa intermedia -run
# Uso: powershell -ExecutionPolicy Bypass -File build.ps1 -empresa intermedia -apk
# Uso: powershell -ExecutionPolicy Bypass -File build.ps1 -empresa intermedia -release
# ============================================

param(
    [Parameter(Mandatory=$true)]
    [string]$empresa,
    [switch]$run,
    [switch]$apk,
    [switch]$release
)

$ROOT   = $PSScriptRoot
$BASE   = "$ROOT\app_base"
$EMPDIR = "$ROOT\$empresa"

# ============================================
# FUNCION: matar procesos Gradle/Java
# ============================================
function Kill-Gradle {
    Write-Host "  Liberando procesos Gradle..." -ForegroundColor DarkGray
    Get-Process -Name "java"   -ErrorAction SilentlyContinue | Stop-Process -Force
    Get-Process -Name "gradle" -ErrorAction SilentlyContinue | Stop-Process -Force
    Start-Sleep -Seconds 2
}

# ============================================
# VERIFICACIONES
# ============================================
if (!(Test-Path $BASE)) {
    Write-Host "ERROR: No existe el proyecto base en: $BASE" -ForegroundColor Red
    exit 1
}

if (!(Test-Path $EMPDIR)) {
    Write-Host "ERROR: No existe la carpeta de la empresa: $EMPDIR" -ForegroundColor Red
    Write-Host ""
    Write-Host "Crea la carpeta con esta estructura:" -ForegroundColor Yellow
    Write-Host "  $ROOT\$empresa\config.txt"
    Write-Host "  $ROOT\$empresa\icon.png                  (opcional)"
    Write-Host "  $ROOT\$empresa\google-services.json       (opcional - para notificaciones push)"
    Write-Host ""
    Write-Host "Ejemplo config.txt:" -ForegroundColor Yellow
    Write-Host "  APP_NAME=Panel Agentes"
    Write-Host "  PACKAGE=co.intermedia.panelws.empresa"
    Write-Host "  VERSION=1.0.0+1"
    Write-Host "  BASE_URL=https://panelws.empresa.co"
    exit 1
}

# ============================================
# LEER config.txt
# ============================================
$configFile = "$EMPDIR\config.txt"

if (!(Test-Path $configFile)) {
    Write-Host "ERROR: No se encontro config.txt en $EMPDIR" -ForegroundColor Red
    exit 1
}

$appName     = "Panel de Agentes"  # valor por defecto
$packageName = $null
$baseUrl     = $null
$version     = $null

foreach ($line in Get-Content $configFile -Encoding UTF8) {
    if ($line -match '^\s*APP_NAME\s*=\s*(.+)') { $appName     = $Matches[1].Trim() }
    if ($line -match '^\s*PACKAGE\s*=\s*(.+)')   { $packageName = $Matches[1].Trim() }
    if ($line -match '^\s*BASE_URL\s*=\s*(.+)')  { $baseUrl     = $Matches[1].Trim() }
    if ($line -match '^\s*VERSION\s*=\s*(.+)')   { $version     = $Matches[1].Trim() }
}

if (!$packageName -or !$baseUrl) {
    Write-Host "ERROR: config.txt incompleto. Debe tener al menos PACKAGE y BASE_URL" -ForegroundColor Red
    Write-Host ""
    Write-Host "Ejemplo:" -ForegroundColor Yellow
    Write-Host "  APP_NAME=Panel Agentes"
    Write-Host "  PACKAGE=co.intermedia.panelws.empresa"
    Write-Host "  VERSION=1.0.0+1"
    Write-Host "  BASE_URL=https://panelws.empresa.co"
    exit 1
}

Write-Host ""
Write-Host "Ensamblando panel para: $empresa" -ForegroundColor Cyan
Write-Host "  APP_NAME : $appName"     -ForegroundColor DarkGray
Write-Host "  PACKAGE  : $packageName" -ForegroundColor DarkGray
Write-Host "  BASE_URL : $baseUrl"     -ForegroundColor DarkGray
if ($version) {
    Write-Host "  VERSION  : $version" -ForegroundColor DarkGray
}
Write-Host "--------------------------------------" -ForegroundColor DarkGray

Kill-Gradle

# ============================================
# COPIAR google-services.json (para FCM)
# ============================================
$gcSrc = "$EMPDIR\google-services.json"
$gcDst = "$BASE\android\app\google-services.json"

if (Test-Path $gcSrc) {
    Copy-Item $gcSrc $gcDst -Force
    Write-Host "OK google-services.json copiado" -ForegroundColor Green
} else {
    Write-Host "INFO: Sin google-services.json - notificaciones en background desactivadas" -ForegroundColor DarkGray
}

# ============================================
# INYECTAR BASE_URL en constants.dart
# ============================================
$constantsDst = "$BASE\lib\core\constants.dart"

if (Test-Path $constantsDst) {
    $content = Get-Content $constantsDst -Raw -Encoding UTF8

    $content = $content -replace "static const String baseUrl\s*=\s*'[^']*'",
        "static const String baseUrl = '$baseUrl'"

    Set-Content -Path $constantsDst -Value $content -Encoding UTF8 -NoNewline
    Write-Host "OK constants.dart actualizado -> $baseUrl" -ForegroundColor Green
} else {
    Write-Host "ERROR: No se encontro constants.dart en $constantsDst" -ForegroundColor Red
    exit 1
}

# ============================================
# GENERAR strings.xml (nombre de la app)
# ============================================
$stringsPath = "$BASE\android\app\src\main\res\values\strings.xml"

$stringsContent = @"
<?xml version="1.0" encoding="utf-8"?>
<resources>
    <string name="app_name">$appName</string>
</resources>
"@

[System.IO.File]::WriteAllText(
    $stringsPath,
    $stringsContent,
    (New-Object System.Text.UTF8Encoding $false)
)
Write-Host "OK strings.xml generado: $appName" -ForegroundColor Green

# ============================================
# ACTUALIZAR android:label EN AndroidManifest.xml
# ============================================
$manifestPath = "$BASE\android\app\src\main\AndroidManifest.xml"

if (Test-Path $manifestPath) {
    $content = [System.IO.File]::ReadAllText($manifestPath, [System.Text.Encoding]::UTF8)
    $content = $content -replace 'android:label="[^"]*"', 'android:label="@string/app_name"'
    [System.IO.File]::WriteAllText($manifestPath, $content, (New-Object System.Text.UTF8Encoding $false))
    Write-Host "OK AndroidManifest.xml -> android:label=@string/app_name ($appName)" -ForegroundColor Green
} else {
    Write-Host "AVISO: No se encontro AndroidManifest.xml" -ForegroundColor Yellow
}

# ============================================
# ACTUALIZAR VERSION EN pubspec.yaml
# ============================================
if ($version) {
    $pubspecPath = "$BASE\pubspec.yaml"

    if (Test-Path $pubspecPath) {
        $content = Get-Content $pubspecPath -Raw -Encoding UTF8
        $content = $content -replace 'version:\s*[^\r\n]+', "version: $version"
        Set-Content -Path $pubspecPath -Value $content -Encoding UTF8 -NoNewline
        Write-Host "OK pubspec.yaml version: $version" -ForegroundColor Green
    }
} else {
    Write-Host "INFO: VERSION no definida, pubspec.yaml sin cambios" -ForegroundColor DarkGray
}

# ============================================
# ACTUALIZAR build.gradle.kts
# ============================================
$gradlePath = "$BASE\android\app\build.gradle.kts"

if (Test-Path $gradlePath) {
    $content = Get-Content $gradlePath -Raw -Encoding UTF8

    $content = $content -replace 'namespace\s*=\s*"[^"]*"',     "namespace = `"$packageName`""
    $content = $content -replace 'applicationId\s*=\s*"[^"]*"', "applicationId = `"$packageName`""

    Set-Content -Path $gradlePath -Value $content -Encoding UTF8 -NoNewline
    Write-Host "OK build.gradle.kts actualizado" -ForegroundColor Green
} else {
    Write-Host "AVISO: No se encontro build.gradle.kts" -ForegroundColor Yellow
}

# ============================================
# CAMBIAR PACKAGE NAME
# ============================================
Write-Host "Cambiando package name a: $packageName..." -ForegroundColor Cyan
Set-Location $BASE
flutter pub get
flutter pub run change_app_package_name:main $packageName

if ($LASTEXITCODE -ne 0) {
    Write-Host "ERROR: Fallo al cambiar el package name" -ForegroundColor Red
    exit 1
}

Write-Host "OK Package name actualizado" -ForegroundColor Green

# ============================================
# COPIAR ICONO (si la empresa tiene uno propio)
# ============================================
$iconSrc = "$EMPDIR\icon.png"
$iconDst = "$BASE\assets\images\icon.png"

if (Test-Path $iconSrc) {
    Copy-Item $iconSrc $iconDst -Force
    Write-Host "OK icon.png copiado desde $empresa" -ForegroundColor Green
} else {
    Write-Host "INFO: Usando icono base (assets/images/icon.png)" -ForegroundColor DarkGray
}

# Generar iconos launcher
Write-Host "Generando iconos launcher..." -ForegroundColor Cyan
dart run flutter_launcher_icons

if ($LASTEXITCODE -ne 0) {
    Write-Host "AVISO: flutter_launcher_icons fallo - el icono puede no actualizarse" -ForegroundColor Yellow
} else {
    Write-Host "OK Iconos launcher generados" -ForegroundColor Green
}

Kill-Gradle

Write-Host "--------------------------------------" -ForegroundColor DarkGray

# ============================================
# COMPILAR O CORRER
# ============================================
$outDir = "$ROOT\builds"
if (!(Test-Path $outDir)) {
    New-Item -ItemType Directory -Path $outDir | Out-Null
}
$timestamp = Get-Date -Format "yyyyMMdd_HHmm"

if ($run) {
    Write-Host "Ejecutando flutter run..." -ForegroundColor Cyan
    flutter run

} elseif ($apk) {
    Write-Host "Compilando APK release..." -ForegroundColor Cyan
    flutter build apk --release

    $apkSrc  = "$BASE\build\app\outputs\flutter-apk\app-release.apk"
    $apkName = "panel_${empresa}_${timestamp}.apk"
    Copy-Item $apkSrc "$outDir\$apkName" -Force

    Write-Host ""
    Write-Host "APK listo en: $outDir\$apkName" -ForegroundColor Green

} elseif ($release) {
    Write-Host "Compilando AAB release..." -ForegroundColor Cyan
    flutter build appbundle --release

    $aabSrc  = "$BASE\build\app\outputs\bundle\release\app-release.aab"
    $aabName = "panel_${empresa}_${timestamp}.aab"
    Copy-Item $aabSrc "$outDir\$aabName" -Force

    Write-Host ""
    Write-Host "AAB listo en: $outDir\$aabName" -ForegroundColor Green

} else {
    Write-Host "Panel ensamblado para '$empresa'. Ahora puedes ejecutar:" -ForegroundColor Green
    Write-Host ""
    Write-Host "  flutter run                        <- probar en dispositivo" -ForegroundColor White
    Write-Host "  flutter build apk --release        <- APK para compartir"   -ForegroundColor White
    Write-Host "  flutter build appbundle --release  <- AAB para Play Store"  -ForegroundColor White
    Write-Host ""
    Write-Host "  O con el script:" -ForegroundColor DarkGray
    Write-Host "  powershell -ExecutionPolicy Bypass -File build.ps1 -empresa $empresa -run"     -ForegroundColor DarkGray
    Write-Host "  powershell -ExecutionPolicy Bypass -File build.ps1 -empresa $empresa -apk"     -ForegroundColor DarkGray
    Write-Host "  powershell -ExecutionPolicy Bypass -File build.ps1 -empresa $empresa -release" -ForegroundColor DarkGray
}

Write-Host ""
