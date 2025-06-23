<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'profesor') {
    http_response_code(403);
    exit('Acceso no autorizado.');
}

require '../includes/conexion.php';

$id_profesor = $_SESSION['id_usuario'] ?? null;
if (!$id_profesor) {
    exit("Error: Profesor no identificado.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}

// Sanitizar y validar inputs
$asignatura_id = isset($_POST['asignatura_id']) ? intval($_POST['asignatura_id']) : null;
$parcial_1 = $_POST['parcial_1'] ?? [];
$parcial_2 = $_POST['parcial_2'] ?? [];
$examen_final = $_POST['examen_final'] ?? [];
$observaciones = $_POST['observaciones'] ?? [];

if (!$asignatura_id || !is_array($parcial_1)) {
    exit("Datos incompletos o inválidos.");
}

// Verificar existencia de carpeta
$logDir = "logs";
if (!is_dir($logDir)) {
    if (!mkdir($logDir, 0775, true)) {
        exit("Error: No se pudo crear el directorio de logs.");
    }
}

// Verificar existencia o crearlo
$logPath = "$logDir/log.txt";
if (!file_exists($logPath)) {
    if (!touch($logPath)) {
        exit("Error: No se pudo crear el archivo de log.");
    }
}

// Verificar permisos de escritura
if (!is_writable($logPath)) {
    exit("Error: El archivo de log no tiene permisos de escritura.");
}

// Abrir el archivo de log
$logFile = fopen($logPath, "a");
if (!$logFile) {
    exit("Error: No se pudo abrir el archivo de log.");
}

$timestamp = date("Y-m-d H:i:s");
fwrite($logFile, "\n--- REGISTRO DE NOTAS POR PROFESOR (ID: $id_profesor) - $timestamp ---\n");

foreach ($parcial_1 as $id_estudiante => $nota1) {
    $nota1 = is_numeric($nota1) ? round($nota1, 2) : null;
    $nota2 = isset($parcial_2[$id_estudiante]) && is_numeric($parcial_2[$id_estudiante]) ? round($parcial_2[$id_estudiante], 2) : null;
    $final = isset($examen_final[$id_estudiante]) && is_numeric($examen_final[$id_estudiante]) ? round($examen_final[$id_estudiante], 2) : null;
    $obs = trim($observaciones[$id_estudiante] ?? '');

    // Validar rango de notas (0 a 10)
    foreach ([$nota1, $nota2, $final] as $n) {
        if (!is_null($n) && ($n < 0 || $n > 10)) {
            fwrite($logFile, "❌ ERROR: Nota fuera de rango para estudiante $id_estudiante. P1:$nota1 P2:$nota2 Final:$final\n");
            continue 2;
        }
    }

    // Escribir registro válido en el log
    $logID = uniqid("REG-");
    $linea = "$logID | estudiante:$id_estudiante | asignatura:$asignatura_id | p1:$nota1 | p2:$nota2 | final:$final | obs: $obs\n";
    fwrite($logFile, $linea);
}

fclose($logFile);

// Redirección o respuesta
header("Location: ../profesor/notas.php?asignatura_id=" . urlencode($asignatura_id) . "&mensaje=notas_guardadas_en_log");
exit;
