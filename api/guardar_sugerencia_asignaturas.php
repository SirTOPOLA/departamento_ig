<?php
// Incluye funciones esenciales para el sistema, como la verificación de sesión y rol.
require_once '../includes/functions.php';
// Asegura que solo los usuarios con el rol 'Profesor' puedan acceder a esta página.
check_login_and_role('Profesor');

// Incluye la configuración de la base de datos para establecer la conexión.
require_once '../config/database.php';
$id_usuario_actual = $_SESSION['user_id'];

$stmt_id_profesor = $pdo->prepare("SELECT id FROM profesores WHERE id_usuario = :id_usuario");
$stmt_id_profesor->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_INT);
$stmt_id_profesor->execute();
$id_profesor_actual = $stmt_id_profesor->fetchColumn();
// Se ejecuta si el formulario se envía mediante el método POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Determina la acción a realizar (sugerir o eliminar).
    $accion = $_POST['action'] ?? '';

    try {
        // Inicia una transacción para asegurar la atomicidad de las operaciones de base de datos.
        $pdo->beginTransaction();

        // Si la acción es 'suggest_subject' (sugerir asignatura).
        if ($accion === 'suggest_subject') {
            // Valida y filtra el ID de la asignatura recibida del formulario.
            $id_asignatura = filter_var($_POST['id_asignatura'] ?? null, FILTER_VALIDATE_INT);
            // Verifica si el ID de la asignatura es válido.
            if ($id_asignatura === null) {
                set_flash_message('danger', 'Error: Asignatura no válida.');
            } else {
                // Comprueba si el profesor ya ha sugerido esta asignatura.
                $stmt_verificar_sugerencia = $pdo->prepare("SELECT COUNT(*) FROM profesores_asignaturas_sugeridas WHERE id_profesor = :id_profesor AND id_asignatura = :id_asignatura");
                $stmt_verificar_sugerencia->bindParam(':id_profesor', $id_profesor_actual, PDO::PARAM_INT);
                $stmt_verificar_sugerencia->bindParam(':id_asignatura', $id_asignatura, PDO::PARAM_INT);
                $stmt_verificar_sugerencia->execute();

                // Si la asignatura ya ha sido sugerida, muestra una advertencia.
                if ($stmt_verificar_sugerencia->fetchColumn() > 0) {
                    set_flash_message('warning', 'Ya has sugerido esta asignatura anteriormente.');
                } else {
                    // Inserta la nueva sugerencia de asignatura en la base de datos.
                    $stmt_sugerir = $pdo->prepare("INSERT INTO profesores_asignaturas_sugeridas (id_profesor, id_asignatura, fecha_sugerencia) VALUES (:id_profesor, :id_asignatura, NOW())");
                    $stmt_sugerir->bindParam(':id_profesor', $id_profesor_actual, PDO::PARAM_INT);
                    $stmt_sugerir->bindParam(':id_asignatura', $id_asignatura, PDO::PARAM_INT);
                    $stmt_sugerir->execute();
                    set_flash_message('success', 'Asignatura sugerida correctamente.');
                }
            }
        }
        // Si la acción es 'remove_suggestion' (eliminar sugerencia).
        elseif ($accion === 'remove_suggestion') {
            // Valida y filtra el ID de la sugerencia a eliminar.
            $id_sugerencia = filter_var($_POST['id_sugerencia'] ?? null, FILTER_VALIDATE_INT);
            // Verifica si el ID de la sugerencia es válido.
            if ($id_sugerencia === null) {
                set_flash_message('danger', 'Error: Sugerencia no válida.');
            } else {
                // Elimina la sugerencia de la base de datos, asegurándose de que pertenezca al profesor actual.
                $stmt_eliminar = $pdo->prepare("DELETE FROM profesores_asignaturas_sugeridas WHERE id = :id_sugerencia AND id_profesor = :id_profesor");
                $stmt_eliminar->bindParam(':id_sugerencia', $id_sugerencia, PDO::PARAM_INT);
                $stmt_eliminar->bindParam(':id_profesor', $id_profesor_actual, PDO::PARAM_INT);
                $stmt_eliminar->execute();
                // Verifica si la eliminación fue exitosa.
                if ($stmt_eliminar->rowCount() > 0) {
                    set_flash_message('success', 'Sugerencia de asignatura eliminada.');
                } else {
                    set_flash_message('warning', 'La sugerencia no pudo ser eliminada o no te pertenece.');
                }
            }
        }

        // Confirma la transacción si todas las operaciones fueron exitosas.
        $pdo->commit();

    } catch (PDOException $e) {
        // En caso de error, revierte la transacción y muestra un mensaje de error.
        $pdo->rollBack();
        set_flash_message('danger', 'Error de base de datos: ' . $e->getMessage());
    }
    header('location: ../profesores/sugerencias.php');
}
