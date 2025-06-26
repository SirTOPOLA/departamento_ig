<?php
header('Content-Type: application/json');

$response = ['status' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit();
}

// Ruta al archivo de log donde se guardarán las notas.
// Asegúrate de que la carpeta 'data' exista y tenga permisos de escritura para el servidor web.
$logFilePath = 'logs/log.txt';

// Recoger los datos de la nota del POST
$id_inscripcion = filter_input(INPUT_POST, 'id_inscripcion', FILTER_VALIDATE_INT);
$parcial_1 = filter_input(INPUT_POST, 'parcial_1', FILTER_VALIDATE_FLOAT);
$parcial_2 = filter_input(INPUT_POST, 'parcial_2', FILTER_VALIDATE_FLOAT);
$examen_final = filter_input(INPUT_POST, 'examen_final', FILTER_VALIDATE_FLOAT);
$observaciones = filter_input(INPUT_POST, 'observaciones', FILTER_SANITIZE_STRING);

// Convertir notas vacías a NULL para el log (o simplemente dejarlas como string vacío si prefieres)
$parcial_1 = $parcial_1 !== false ? $parcial_1 : null;
$parcial_2 = $parcial_2 !== false ? $parcial_2 : null;
$examen_final = $examen_final !== false ? $examen_final : null;
$observaciones = $observaciones ?: ''; // Si es nulo, convertir a string vacío

if (!$id_inscripcion) {
    $response['message'] = 'ID de inscripción no proporcionado o inválido.';
    echo json_encode($response);
    exit();
}

// Crear un array asociativo con los datos de la nota para guardarlos en formato JSON
$noteData = [
    'timestamp'      => date('Y-m-d H:i:s'),
    'id_inscripcion'  => $id_inscripcion,
    'parcial_1'      => $parcial_1,
    'parcial_2'      => $parcial_2,
    'examen_final'   => $examen_final,
    'observaciones'  => $observaciones
];

// Convertir el array a una cadena JSON
$logEntry = json_encode($noteData);

// Asegúrate de que la carpeta 'data' exista
if (!is_dir(dirname($logFilePath))) {
    mkdir(dirname($logFilePath), 0775, true); // Crea el directorio con permisos recursivamente
}

// Intentar escribir la línea en el archivo de log.
// FILE_APPEND: Añade al final del archivo.
// LOCK_EX: Bloquea el archivo durante la escritura para evitar condiciones de carrera.
if (file_put_contents($logFilePath, $logEntry . PHP_EOL, FILE_APPEND | LOCK_EX) !== false) {
    $response['status'] = true;
    $response['message'] = 'Notas guardadas en el log exitosamente.';
} else {
    $response['message'] = 'Error al escribir las notas en el archivo de log. Verifique permisos.';
    error_log("Error al escribir en log.txt: No se pudo escribir en {$logFilePath}");
}

echo json_encode($response);
?>
