<?php
require_once '../includes/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id_usuario']) ? intval($_POST['id_usuario']) : 0;

    if ($id > 0) {
        try {
            // Obtener estado actual
            $stmt = $pdo->prepare("SELECT estado FROM usuarios WHERE id_usuario = ?");
            $stmt->execute([$id]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario) {
                $nuevo_estado = $usuario['estado'] ? 0 : 1;

                // Actualizar estado
                $update = $pdo->prepare("UPDATE usuarios SET estado = ? WHERE id_usuario = ?");
                $update->execute([$nuevo_estado, $id]);

                echo json_encode([
                    'status' => true,
                    'nuevo_estado' => $nuevo_estado,
                    'message' => 'Estado actualizado correctamente.'
                ]);
            } else {
                echo json_encode(['status' => false, 'message' => 'Usuario no encontrado.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['status' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => false, 'message' => 'ID inválido.']);
    }
} else {
    echo json_encode(['status' => false, 'message' => 'Método no permitido.']);
}
