<?php
header('Content-Type: application/json');

$response = ['status' => false, 'message' => '', 'count' => 0, 'raw_lines' => []];
$logFilePath = 'logs/log.txt'; // Ruta al archivo de log

try {
    if (file_exists($logFilePath)) {
        $lines = file($logFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            $response['status'] = true;
            $response['count'] = count($lines);
            // Devolver las líneas crudas (o un fragmento) para la vista simplificada
            $response['raw_lines'] = array_map(function($line) {
                return $line; // Devuelve la línea completa, el JS la truncará si es muy larga
            }, $lines);
        } else {
            $response['message'] = 'No se pudo leer el archivo de log.';
        }
    } else {
        $response['status'] = true; // El archivo no existe, por lo tanto, el conteo es 0
        $response['message'] = 'El archivo de log no existe.';
    }
} catch (Exception $e) {
    $response['message'] = 'Error al obtener el conteo del log: ' . $e->getMessage();
    error_log("Error en obtener_notas_log_count.php: " . $e->getMessage());
}

echo json_encode($response);
?>
