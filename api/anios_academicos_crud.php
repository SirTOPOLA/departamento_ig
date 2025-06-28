<?php
// anios_academicos_crud.php
require_once '../includes/conexion.php';

header('Content-Type: application/json');

$response = ['status' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Crear o Actualizar Año Académico ---
    $id_anio = $_POST['id_anio'] ?? null;
    $anio = $_POST['anio'] ?? null;
    $fecha_inicio = $_POST['fecha_inicio'] ?? null;
    $fecha_fin = $_POST['fecha_fin'] ?? null;
    $activo = isset($_POST['activo']) ? (int)$_POST['activo'] : 0; // 0 o 1

    if (!$anio || !$fecha_inicio || !$fecha_fin) {
        $response['message'] = 'Todos los campos son obligatorios.';
        echo json_encode($response);
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Si se marca como activo, desactivar todos los demás primero
        if ($activo) {
            $stmt = $pdo->prepare("UPDATE anios_academicos SET activo = 0 WHERE activo = 1");
            $stmt->execute();
        }

        if ($id_anio) {
            // Actualizar
            $stmt = $pdo->prepare("UPDATE anios_academicos SET anio = :anio, fecha_inicio = :fecha_inicio, fecha_fin = :fecha_fin, activo = :activo WHERE id_anio = :id_anio");
            $stmt->bindParam(':id_anio', $id_anio, PDO::PARAM_INT);
            $response['message'] = 'Año académico actualizado correctamente.';
        } else {
            // Insertar
            $stmt = $pdo->prepare("INSERT INTO anios_academicos (anio, fecha_inicio, fecha_fin, activo) VALUES (:anio, :fecha_inicio, :fecha_fin, :activo)");
            $response['message'] = 'Año académico creado correctamente.';
        }

        $stmt->bindParam(':anio', $anio, PDO::PARAM_STR);
        $stmt->bindParam(':fecha_inicio', $fecha_inicio, PDO::PARAM_STR);
        $stmt->bindParam(':fecha_fin', $fecha_fin, PDO::PARAM_STR);
        $stmt->bindParam(':activo', $activo, PDO::PARAM_INT);
        $stmt->execute();

        $pdo->commit();
        $response['status'] = true;
        echo json_encode($response);

    } catch (PDOException $e) {
        $pdo->rollBack();
        $response['message'] = 'Error de base de datos al guardar año académico: ' . $e->getMessage();
        error_log('Error en anios_academicos_crud.php (POST): ' . $e->getMessage());
        echo json_encode($response);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // --- Obtener Año o Activar o Eliminar ---
    $action = $_GET['action'] ?? null;
    $id_anio = $_GET['id'] ?? null;

    if ($action === 'activate' && $id_anio) {
        try {
            $pdo->beginTransaction();
            // Desactivar todos los demás primero
            $stmt = $pdo->prepare("UPDATE anios_academicos SET activo = 0 WHERE activo = 1");
            $stmt->execute();

            // Activar el año especificado
            $stmt = $pdo->prepare("UPDATE anios_academicos SET activo = 1 WHERE id_anio = :id_anio");
            $stmt->bindParam(':id_anio', $id_anio, PDO::PARAM_INT);
            $stmt->execute();
            $pdo->commit();
            $response['status'] = true;
            $response['message'] = 'Año académico activado correctamente.';
            echo json_encode($response);
        } catch (PDOException $e) {
            $pdo->rollBack();
            $response['message'] = 'Error al activar año académico: ' . $e->getMessage();
            error_log('Error en anios_academicos_crud.php (Activate): ' . $e->getMessage());
            echo json_encode($response);
        }
    } elseif ($action === 'delete' && $id_anio) {
        // --- Eliminar Año Académico ---
        try {
            // Verificar si hay horarios asociados a este año
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM horarios WHERE id_anio = :id_anio");
            $stmtCheck->bindParam(':id_anio', $id_anio, PDO::PARAM_INT);
            $stmtCheck->execute();
            if ($stmtCheck->fetchColumn() > 0) {
                $response['message'] = 'No se puede eliminar el año académico porque tiene horarios asociados. Elimine los horarios primero.';
                echo json_encode($response);
                exit();
            }

            $stmt = $pdo->prepare("DELETE FROM anios_academicos WHERE id_anio = :id_anio");
            $stmt->bindParam(':id_anio', $id_anio, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $response['status'] = true;
                $response['message'] = 'Año académico eliminado correctamente.';
            } else {
                $response['message'] = 'Año académico no encontrado.';
            }
            echo json_encode($response);
        } catch (PDOException $e) {
            $response['message'] = 'Error al eliminar año académico: ' . $e->getMessage();
            error_log('Error en anios_academicos_crud.php (Delete): ' . $e->getMessage());
            echo json_encode($response);
        }
    } elseif ($id_anio) {
        // --- Obtener un solo año académico ---
        try {
            $stmt = $pdo->prepare("SELECT * FROM anios_academicos WHERE id_anio = :id_anio");
            $stmt->bindParam(':id_anio', $id_anio, PDO::PARAM_INT);
            $stmt->execute();
            $anio_academico = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($anio_academico) {
                $response['status'] = true;
                $response['anio'] = $anio_academico;
            } else {
                $response['message'] = 'Año académico no encontrado.';
            }
            echo json_encode($response);
        } catch (PDOException $e) {
            $response['message'] = 'Error al obtener año académico: ' . $e->getMessage();
            error_log('Error en anios_academicos_crud.php (GET single): ' . $e->getMessage());
            echo json_encode($response);
        }
    } else {
        $response['message'] = 'Acción GET no válida o ID faltante.';
        echo json_encode($response);
    }
} else {
    $response['message'] = 'Método de solicitud no permitido.';
    echo json_encode($response);
}
?>
