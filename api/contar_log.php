<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'administrador') {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit;
}

$logPath = "logs/log.txt";

$count = 0;
if (file_exists($logPath)) {
    $lineas = file($logPath, FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES);
    $count = count($lineas);
}

header('Content-Type: application/json');
echo json_encode(['count' => $count]);
