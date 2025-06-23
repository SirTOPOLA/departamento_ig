<?php
require_once '../includes/conexion.php';
header('Content-Type: application/json');

$id_profesor = $_GET['id_profesor'] ?? null;
$dia = $_GET['dia'] ?? null;
$hora_inicio = $_GET['hora_inicio'] ?? null;
$hora_fin = $_GET['hora_fin'] ?? null;
$id_horario_actual = $_GET['id_horario'] ?? null; // opcional para edición

if (!$id_profesor || !$dia || !$hora_inicio || !$hora_fin) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan parámetros obligatorios.']);
    exit;
}

$params = [
    'id_profesor' => $id_profesor,
    'dia' => $dia
];

$sql = "SELECT id_horario, hora_inicio, hora_fin, aula_id, id_asignatura
        FROM horarios
        WHERE id_profesor = :id_profesor AND dia = :dia";

if ($id_horario_actual) {
    $sql .= " AND id_horario != :id_horario";
    $params['id_horario'] = $id_horario_actual;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Convertimos horas a DateTime para validación
$nuevaInicio = new DateTime($hora_inicio);
$nuevaFin = new DateTime($hora_fin);
$solapamiento = false;

foreach ($horarios as $h) {
    $inicio = new DateTime($h['hora_inicio']);
    $fin = new DateTime($h['hora_fin']);

    // Verificar solapamiento: [A,B) solapa con [C,D) si A < D y B > C
    if ($nuevaInicio < $fin && $nuevaFin > $inicio) {
        $solapamiento = true;
        break;
    }
}

// Calcular total de horas
$totalHoras = 0;
foreach ($horarios as $h) {
    $ini = new DateTime($h['hora_inicio']);
    $fin = new DateTime($h['hora_fin']);
    $duracion = $fin->diff($ini);
    $totalHoras += $duracion->h + $duracion->i / 60;
}

// Respuesta
echo json_encode([
    'horarios' => $horarios,
    'total_horas' => $totalHoras,
    'solapamiento' => $solapamiento
]);
