<?php
require_once '../includes/functions.php';
require_once '../config/database.php';

check_login_and_role('Profesor');

$id_usuario_actual = $_SESSION['user_id'] ?? null;

if (!$id_usuario_actual) {
    set_flash_message('danger', 'Usuario no autenticado.');
    header('Location: ../login.php');
    exit;
}

// Obtener el ID del profesor asociado al usuario
$stmt_id_profesor = $pdo->prepare("SELECT id FROM profesores WHERE id_usuario = :id_usuario");
$stmt_id_profesor->execute([':id_usuario' => $id_usuario_actual]);
$id_profesor_actual = $stmt_id_profesor->fetchColumn();

if (!$id_profesor_actual) {
    set_flash_message('danger', 'No se encontró el perfil del profesor.');
    header('Location: ../login.php');
    exit;
}

// Procesamiento del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['action'] ?? '';

    try {
        $pdo->beginTransaction();

        if ($accion === 'suggest_subject') {
            $id_asignatura = filter_input(INPUT_POST, 'id_asignatura', FILTER_VALIDATE_INT);

            if (!$id_asignatura) {
                set_flash_message('danger', 'Asignatura no válida.');
            } else {
                // Verificar si existe la asignatura
                $stmt_verificar = $pdo->prepare("SELECT COUNT(*) FROM asignaturas WHERE id = :id");
                $stmt_verificar->execute([':id' => $id_asignatura]);

                if ($stmt_verificar->fetchColumn() === 0) {
                    set_flash_message('danger', 'La asignatura no existe.');
                } else {
                    // Verificar si ya fue sugerida
                    $stmt_check = $pdo->prepare("
                        SELECT COUNT(*) FROM profesores_asignaturas_sugeridas
                        WHERE id_profesor = :id_profesor AND id_asignatura = :id_asignatura
                    ");
                    $stmt_check->execute([
                        ':id_profesor' => $id_profesor_actual,
                        ':id_asignatura' => $id_asignatura
                    ]);

                    if ($stmt_check->fetchColumn() > 0) {
                        set_flash_message('warning', 'Ya has sugerido esta asignatura anteriormente.');
                    } else {
                        $stmt_insert = $pdo->prepare("
                            INSERT INTO profesores_asignaturas_sugeridas (id_profesor, id_asignatura, fecha_sugerencia)
                            VALUES (:id_profesor, :id_asignatura, NOW())
                        ");
                        $stmt_insert->execute([
                            ':id_profesor' => $id_profesor_actual,
                            ':id_asignatura' => $id_asignatura
                        ]);
                        set_flash_message('success', 'Asignatura sugerida correctamente.');
                    }
                }
            }
        }

        elseif ($accion === 'remove_suggestion') {
            $id_sugerencia = filter_input(INPUT_POST, 'id_sugerencia', FILTER_VALIDATE_INT);

            if (!$id_sugerencia) {
                set_flash_message('danger', 'Sugerencia no válida.');
            } else {
                $stmt_delete = $pdo->prepare("
                    DELETE FROM profesores_asignaturas_sugeridas
                    WHERE id = :id AND id_profesor = :id_profesor
                ");
                $stmt_delete->execute([
                    ':id' => $id_sugerencia,
                    ':id_profesor' => $id_profesor_actual
                ]);

                if ($stmt_delete->rowCount() > 0) {
                    set_flash_message('success', 'Sugerencia eliminada correctamente.');
                } else {
                    set_flash_message('warning', 'No se pudo eliminar. Puede que no te pertenezca.');
                }
            }
        }

        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        set_flash_message('danger', 'Error al procesar la solicitud: ' . $e->getMessage());
    }

    header('Location: ../profesores/sugerencias.php');
    exit;
}
