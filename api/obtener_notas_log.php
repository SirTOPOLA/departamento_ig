<?php
header('Content-Type: application/json');

$response = ['status' => false, 'message' => '', 'data' => []];
$logFilePath = 'logs/log.txt'; // Ruta al archivo de log

try {
    if (file_exists($logFilePath)) {
        $lines = file($logFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            $objetos = [];
            foreach ($lines as $line) {
                $decoded = json_decode($line, true); // Decodifica la línea como JSON
                if (json_last_error() === JSON_ERROR_NONE) {
                    $objetos[] = $decoded;
                } else {
                    // Si la línea no es JSON válido, puedes omitirla o reportarla
                    error_log("Línea inválida en log.txt: $line");
                }
            }

            $response['status'] = true;
            $response['data'] = $objetos;
        } else {
            $response['message'] = 'No se pudo leer el archivo de log.';
        }
    } else {
        $response['status'] = true;
        $response['message'] = 'El archivo de log de notas pendientes no existe o está vacío.';
    }
} catch (Exception $e) {
    $response['message'] = 'Error al obtener el log de notas: ' . $e->getMessage();
    error_log("Error en obtener_notas_log.php: " . $e->getMessage());
}

echo json_encode($response);
?>
