<?php
// Establece el encabezado para indicar que la respuesta será JSON
header('Content-Type: application/json');

// Incluye el archivo de conexión a la base de datos
// Asegúrate de que tu archivo 'conexion.php' establezca una variable $pdo
// que sea una instancia de PDO conectada a tu base de datos.
require_once '../includes/conexion.php';

$anios_academicos = [];
$status = false;
$message = '';

try {
    // Verifica que la conexión PDO esté establecida (asumiendo que $pdo es global o devuelto por conexion.php)
    if (isset($pdo) && $pdo instanceof PDO) {
        // Prepara la consulta SQL para obtener los años académicos activos
        // Ordenados de forma descendente por el nombre del año
        $stmt = $pdo->prepare("SELECT id_anio, anio FROM anios_academicos WHERE activo = 1 ORDER BY anio DESC");
        $stmt->execute();

        // Verifica si la consulta se ejecutó correctamente y hay resultados
        if ($stmt) {
            $status = true;
            // Itera sobre los resultados y los agrega al array
            while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Mapea los nombres de las columnas de la tabla a los nombres esperados en JavaScript
                // JavaScript espera 'id_anio_academico' y 'nombre_anio'
                $anios_academicos[] = [
                    'id_anio_academico' => $fila['id_anio'],
                    'nombre_anio' => $fila['anio']
                ];
            }
        } else {
            // Si la consulta no se pudo ejecutar, captura el error
            $message = "Error en la consulta PDO.";
            // Para depuración, puedes obtener información más detallada del error de PDO
            // $message .= " Detalles: " . implode(" - ", $pdo->errorInfo());
            error_log($message); // Registra el error en los logs del servidor
        }
    } else {
        $message = "Error: La conexión PDO a la base de datos no está disponible o no es válida.";
        error_log($message);
    }
} catch (PDOException $e) {
    // Captura cualquier excepción de PDO (errores de conexión o ejecución)
    $status = false;
    $message = "Excepción PDO al obtener años académicos: " . $e->getMessage();
    error_log($message);
} catch (Exception $e) {
    // Captura cualquier otra excepción general
    $status = false;
    $message = "Excepción general al obtener años académicos: " . $e->getMessage();
    error_log($message);
}

// Devuelve la respuesta en formato JSON
echo json_encode(['status' => $status, 'data' => $anios_academicos, 'message' => $message]);
?>