<?php
// actualizar_inscripcion_horario.php

// Asegúrate de que las rutas sean correctas según tu estructura de archivos
require_once '../config/database.php';
require_once '../includes/functions.php'; // Incluye funciones como obtenerIdUsuarioLogueado()
check_login_and_role('Estudiante');

header('Content-Type: application/json');

$respuesta = ['exito' => false, 'mensaje' => '']; // Variables en español

// Verificación de sesión
 
if (!$id_usuario_logueado) {
    $respuesta['mensaje'] = 'Acceso no autorizado. Por favor, inicie sesión.';
    echo json_encode($respuesta);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_enrollment']) && isset($_POST['id_horario'])) {
    $id_inscripcion = (int)$_POST['id_enrollment']; // Renombrado
    $id_horario_seleccionado = (int)$_POST['id_horario']; // Renombrado

    try { 
        // **PASO DE AUTORIZACIÓN CRÍTICO:**
        // Verificar que la inscripción pertenezca al usuario logueado.
        // Se une con la tabla 'estudiantes' y 'usuarios' para verificar el id_usuario.
        $consulta_verificar_propiedad = $pdo->prepare("
            SELECT ie.id
            FROM inscripciones_estudiantes ie
            JOIN estudiantes e ON ie.id_estudiante = e.id
            WHERE ie.id = :id_inscripcion AND e.id_usuario = :id_usuario_logueado;
        ");
        $consulta_verificar_propiedad->bindParam(':id_inscripcion', $id_inscripcion, PDO::PARAM_INT);
        $consulta_verificar_propiedad->bindParam(':id_usuario_logueado', $id_usuario_logueado, PDO::PARAM_INT);
        $consulta_verificar_propiedad->execute();

        if (!$consulta_verificar_propiedad->fetch()) {
            $respuesta['mensaje'] = 'No tienes permiso para modificar esta inscripción o la inscripción no existe.';
            $pdo->rollBack(); // Deshace la transacción si no hay permiso
            echo json_encode($respuesta);
            exit();
        }

        // 1. Actualiza la tabla `inscripciones_estudiantes`
        $consulta_actualizar = $pdo->prepare("
            UPDATE inscripciones_estudiantes
            SET id_horario = :id_horario_seleccionado, confirmada = 1
            WHERE id = :id_inscripcion;
        ");
        $consulta_actualizar->bindParam(':id_horario_seleccionado', $id_horario_seleccionado, PDO::PARAM_INT);
        $consulta_actualizar->bindParam(':id_inscripcion', $id_inscripcion, PDO::PARAM_INT);
        $consulta_actualizar->execute();

        if ($consulta_actualizar->rowCount() > 0) {
            // 2. Obtiene los detalles del horario seleccionado para devolverlos al frontend
            $consulta_detalles_horario = $pdo->prepare("
                SELECT
                    h.dia_semana,
                    h.hora_inicio,
                    h.hora_fin,
                    h.turno,
                    u.nombre_completo,
                    a.nombre_aula
                FROM
                    horarios h
                JOIN
                    profesores p ON h.id_profesor = p.id
                JOIN
                    usuarios u ON p.id_usuario = u.id
                JOIN
                    aulas a ON h.id_aula = a.id
                WHERE
                    h.id = :id_horario_seleccionado;
            ");
            $consulta_detalles_horario->bindParam(':id_horario_seleccionado', $id_horario_seleccionado, PDO::PARAM_INT);
            $consulta_detalles_horario->execute();
            $detalles_horario = $consulta_detalles_horario->fetch(PDO::FETCH_ASSOC);

            if ($detalles_horario) {
                $respuesta['exito'] = true;
                $respuesta['mensaje'] = 'Inscripción actualizada exitosamente.';
                $respuesta['horario_details'] = $detalles_horario; // Nombre de clave en inglés para consistencia con JS frontend
            } else {
                $respuesta['mensaje'] = 'Horario seleccionado, pero no se pudieron obtener sus detalles.';
                $respuesta['exito'] = true; // La inscripción se actualizó, aunque los detalles no se recuperaron.
            }

            $pdo->commit(); // Confirma la transacción

        } else {
            $respuesta['mensaje'] = 'No se encontró la inscripción para actualizar o no hubo cambios.';
            $pdo->rollBack(); // Deshace la transacción
        }

    } catch (PDOException $e) {
        $pdo->rollBack(); // En caso de error, deshace la transacción
        $respuesta['mensaje'] = 'Error de base de datos al actualizar la inscripción: ' . $e->getMessage();
        error_log('actualizar_inscripcion_horario.php PDOException: ' . $e->getMessage()); // Para depuración
    }
} else {
    $respuesta['mensaje'] = 'Solicitud inválida. Asegúrese de enviar el ID de inscripción y el ID del horario.';
}

echo json_encode($respuesta);
?>