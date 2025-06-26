<?php
header('Content-Type: application/json');

$response = ['status' => false, 'message' => '', 'data' => []];
$logFilePath = 'logs/log.txt'; // Ruta al archivo de log

try {
    if (file_exists($logFilePath)) {
        $lines = file($logFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            $parsedEntries = [];
            foreach ($lines as $line) {
                $decodedLine = json_decode($line, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $parsedEntries[] = array_merge($decodedLine, ['parsed' => true]);
                } else {
                    // Si el JSON es inválido, guardamos la línea original y marcamos como no parseada
                    $parsedEntries[] = ['parsed' => false, 'raw_line' => $line];
                    error_log("Error de JSON al leer log de notas: " . json_last_error_msg() . " en línea: " . $line);
                }
            }
            $response['status'] = true;
            $response['data'] = $parsedEntries;
        } else {
            $response['message'] = 'No se pudo leer el archivo de log.';
        }
    } else {
        $response['status'] = true; // No es un error si el archivo no existe, simplemente está vacío
        $response['message'] = 'El archivo de log de notas pendientes no existe o está vacío.';
    }
} catch (Exception $e) {
    $response['message'] = 'Error al obtener y parsear el log de notas: ' . $e->getMessage();
    error_log("Error en obtener_notas_guardadas_log.php: " . $e->getMessage());
}

echo json_encode($response);
?>
