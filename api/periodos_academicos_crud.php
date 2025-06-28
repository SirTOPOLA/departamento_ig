<?php
// periodos_academicos_crud.php
require_once '../includes/conexion.php';

header('Content-Type: application/json');

$response = ['status' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Crear o Actualizar Período ---
    $id_periodo = $_POST['id_periodo'] ?? null;
    $nombre_periodo = $_POST['nombre_periodo'] ?? null;
    $fecha_inicio = $_POST['fecha_inicio'] ?? null;
    $fecha_fin = $_POST['fecha_fin'] ?? null;
    $activo = isset($_POST['activo']) ? (int)$_POST['activo'] : 0; // 0 o 1

    if (!$nombre_periodo || !$fecha_inicio || !$fecha_fin) {
        $response['message'] = 'Todos los campos son obligatorios.';
        echo json_encode($response);
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Si se marca como activo, desactivar todos los demás primero
        if ($activo) {
            $stmt = $pdo->prepare("UPDATE periodos_academicos SET activo = 0 WHERE activo = 1");
            $stmt->execute();
        }

        if ($id_periodo) {
            // Actualizar
            $stmt = $pdo->prepare("UPDATE periodos_academicos SET nombre_periodo = :nombre, fecha_inicio = :fecha_inicio, fecha_fin = :fecha_fin, activo = :activo WHERE id_periodo = :id_periodo");
            $stmt->bindParam(':id_periodo', $id_periodo, PDO::PARAM_INT);
            $response['message'] = 'Período académico actualizado correctamente.';
        } else {
            // Insertar
            $stmt = $pdo->prepare("INSERT INTO periodos_academicos (nombre_periodo, fecha_inicio, fecha_fin, activo) VALUES (:nombre, :fecha_inicio, :fecha_fin, :activo)");
            $response['message'] = 'Período académico creado correctamente.';
        }

        $stmt->bindParam(':nombre', $nombre_periodo, PDO::PARAM_STR);
        $stmt->bindParam(':fecha_inicio', $fecha_inicio, PDO::PARAM_STR);
        $stmt->bindParam(':fecha_fin', $fecha_fin, PDO::PARAM_STR);
        $stmt->bindParam(':activo', $activo, PDO::PARAM_INT);
        $stmt->execute();

        $pdo->commit();
        $response['status'] = true;
        echo json_encode($response);

    } catch (PDOException $e) {
        $pdo->rollBack();
        $response['message'] = 'Error de base de datos al guardar período: ' . $e->getMessage();
        error_log('Error en periodos_academicos_crud.php (POST): ' . $e->getMessage());
        echo json_encode($response);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // --- Obtener Período o Activar ---
    $action = $_GET['action'] ?? null;
    $id_periodo = $_GET['id'] ?? null;

    if ($action === 'activate' && $id_periodo) {
        try {
            $pdo->beginTransaction();
            // Desactivar todos los demás primero
            $stmt = $pdo->prepare("UPDATE periodos_academicos SET activo = 0 WHERE activo = 1");
            $stmt->execute();

            // Activar el período especificado
            $stmt = $pdo->prepare("UPDATE periodos_academicos SET activo = 1 WHERE id_periodo = :id_periodo");
            $stmt->bindParam(':id_periodo', $id_periodo, PDO::PARAM_INT);
            $stmt->execute();
            $pdo->commit();
            $response['status'] = true;
            $response['message'] = 'Período activado correctamente.';
            echo json_encode($response);
        } catch (PDOException $e) {
            $pdo->rollBack();
            $response['message'] = 'Error al activar período: ' . $e->getMessage();
            error_log('Error en periodos_academicos_crud.php (Activate): ' . $e->getMessage());
            echo json_encode($response);
        }
    } elseif ($action === 'delete' && $id_periodo) {
        // --- Eliminar Período ---
        try {
            // Verificar si hay horarios asociados a este período
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM horarios WHERE id_periodo = :id_periodo");
            $stmtCheck->bindParam(':id_periodo', $id_periodo, PDO::PARAM_INT);
            $stmtCheck->execute();
            if ($stmtCheck->fetchColumn() > 0) {
                $response['message'] = 'No se puede eliminar el período porque tiene horarios asociados. Elimine los horarios primero.';
                echo json_encode($response);
                exit();
            }

            $stmt = $pdo->prepare("DELETE FROM periodos_academicos WHERE id_periodo = :id_periodo");
            $stmt->bindParam(':id_periodo', $id_periodo, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $response['status'] = true;
                $response['message'] = 'Período eliminado correctamente.';
            } else {
                $response['message'] = 'Período no encontrado.';
            }
            echo json_encode($response);
        } catch (PDOException $e) {
            $response['message'] = 'Error al eliminar período: ' . $e->getMessage();
            error_log('Error en periodos_academicos_crud.php (Delete): ' . $e->getMessage());
            echo json_encode($response);
        }
    } elseif ($id_periodo) {
        // --- Obtener un solo período ---
        try {
            $stmt = $pdo->prepare("SELECT * FROM periodos_academicos WHERE id_periodo = :id_periodo");
            $stmt->bindParam(':id_periodo', $id_periodo, PDO::PARAM_INT);
            $stmt->execute();
            $periodo = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($periodo) {
                $response['status'] = true;
                $response['periodo'] = $periodo;
            } else {
                $response['message'] = 'Período no encontrado.';
            }
            echo json_encode($response);
        } catch (PDOException $e) {
            $response['message'] = 'Error al obtener período: ' . $e->getMessage();
            error_log('Error en periodos_academicos_crud.php (GET single): ' . $e->getMessage());
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
