<?php
require_once '../includes/functions.php';
check_login_and_role('Administrador');

require_once '../config/database.php';

// --- Lógica para añadir/editar/eliminar horarios (PROCESAMIENTO POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $id_horario = filter_var($_POST['id_horario'] ?? null, FILTER_VALIDATE_INT);
    $id_semestre = filter_var($_POST['id_semestre'] ?? null, FILTER_VALIDATE_INT);
    $id_asignatura = filter_var($_POST['id_asignatura'] ?? null, FILTER_VALIDATE_INT);
    $id_curso = filter_var($_POST['id_curso'] ?? null, FILTER_VALIDATE_INT);
    // CAMBIO CLAVE: id_profesor ahora se refiere al ID de la tabla 'profesores'
    $id_profesor = filter_var($_POST['id_profesor'] ?? null, FILTER_VALIDATE_INT);
    $id_aula = filter_var($_POST['id_aula'] ?? null, FILTER_VALIDATE_INT);
    $dia_semana = sanitize_input($_POST['dia_semana'] ?? '');
    $hora_inicio = sanitize_input($_POST['hora_inicio'] ?? '');
    $hora_fin = sanitize_input($_POST['hora_fin'] ?? '');
    $turno = sanitize_input($_POST['turno'] ?? '');


    

    if ($action === 'delete') {
        if ($id_horario === null  ) {
            set_flash_message('danger', 'Error: ID de horario no válido para eliminación.');
        } else {
            $stmt = $pdo->prepare("DELETE FROM horarios WHERE id = :id_horario");
            $stmt->bindParam(':id_horario', $id_horario, PDO::PARAM_INT);

            if ($stmt->execute()) { // Verifica si la ejecución fue exitosa
                set_flash_message('success', 'Horario eliminado correctamente.');
            } else {
                set_flash_message('danger', 'Error al eliminar el horario.');
            }
        }
    } else { 

        // Validaciones básicas
        if (empty($id_semestre) || empty($id_asignatura) || empty($id_curso) || empty($id_profesor) || empty($id_aula) || empty($dia_semana) || empty($hora_inicio) || empty($hora_fin) || empty($turno)) {
            set_flash_message('danger', 'Error: Todos los campos son obligatorios.');
        } elseif (strtotime($hora_inicio) >= strtotime($hora_fin)) {
            set_flash_message('danger', 'Error: La hora de inicio debe ser anterior a la hora de fin.');
        } else {
            try {
                // --- Lógica de Detección de Conflictos ---
                $conflict_found = false;
                $conflict_message = '';

                // 1. Conflicto de Profesor: El profesor no puede estar en dos lugares al mismo tiempo.
                $stmt_profesor_conflict = $pdo->prepare("
                SELECT COUNT(*) FROM horarios
                WHERE id_profesor = :id_profesor
                AND id_semestre = :id_semestre
                AND dia_semana = :dia_semana
                AND (
                    (hora_inicio < :hora_fin AND hora_fin > :hora_inicio)
                )
                " . ($action === 'edit' ? " AND id != :id_horario" : "")
                );
                $stmt_profesor_conflict->bindParam(':id_profesor', $id_profesor, PDO::PARAM_INT);
                $stmt_profesor_conflict->bindParam(':id_semestre', $id_semestre, PDO::PARAM_INT);
                $stmt_profesor_conflict->bindParam(':dia_semana', $dia_semana);
                $stmt_profesor_conflict->bindParam(':hora_inicio', $hora_inicio);
                $stmt_profesor_conflict->bindParam(':hora_fin', $hora_fin);
                if ($action === 'edit') {
                    $stmt_profesor_conflict->bindParam(':id_horario', $id_horario, PDO::PARAM_INT);
                }
                $stmt_profesor_conflict->execute();
                if ($stmt_profesor_conflict->fetchColumn() > 0) {
                    $conflict_found = true;
                    $conflict_message = 'Conflicto: El profesor ya tiene otra clase programada en ese horario para este semestre.';
                }

                // 2. Conflicto de Aula: El aula no puede usarse para dos clases al mismo tiempo.
                if (!$conflict_found) {
                    $stmt_aula_conflict = $pdo->prepare("
                    SELECT COUNT(*) FROM horarios
                    WHERE id_aula = :id_aula
                    AND id_semestre = :id_semestre
                    AND dia_semana = :dia_semana
                    AND (
                        (hora_inicio < :hora_fin AND hora_fin > :hora_inicio)
                    )
                    " . ($action === 'edit' ? " AND id != :id_horario" : "")
                    );
                    $stmt_aula_conflict->bindParam(':id_aula', $id_aula, PDO::PARAM_INT);
                    $stmt_aula_conflict->bindParam(':id_semestre', $id_semestre, PDO::PARAM_INT);
                    $stmt_aula_conflict->bindParam(':dia_semana', $dia_semana);
                    $stmt_aula_conflict->bindParam(':hora_inicio', $hora_inicio);
                    $stmt_aula_conflict->bindParam(':hora_fin', $hora_fin);
                    if ($action === 'edit') {
                        $stmt_aula_conflict->bindParam(':id_horario', $id_horario, PDO::PARAM_INT);
                    }
                    $stmt_aula_conflict->execute();
                    if ($stmt_aula_conflict->fetchColumn() > 0) {
                        $conflict_found = true;
                        $conflict_message = 'Conflicto: El aula ya está ocupada en ese horario para este semestre.';
                    }
                }

                // 3. Conflicto de Asignatura/Curso/Semestre: Evitar duplicados exactos para la misma clase.
                // Esto es más un control de duplicidad que un conflicto de recursos
                if (!$conflict_found) {
                    $stmt_duplicate_horarios = $pdo->prepare("
                    SELECT COUNT(*) FROM horarios
                    WHERE id_semestre = :id_semestre
                    AND id_asignatura = :id_asignatura
                    AND id_curso = :id_curso
                    AND dia_semana = :dia_semana
                    AND hora_inicio = :hora_inicio
                    AND hora_fin = :hora_fin
                    AND turno = :turno
                    " . ($action === 'edit' ? " AND id != :id_horario" : "")
                    );
                    $stmt_duplicate_horarios->bindParam(':id_semestre', $id_semestre, PDO::PARAM_INT);
                    $stmt_duplicate_horarios->bindParam(':id_asignatura', $id_asignatura, PDO::PARAM_INT);
                    $stmt_duplicate_horarios->bindParam(':id_curso', $id_curso, PDO::PARAM_INT);
                    $stmt_duplicate_horarios->bindParam(':dia_semana', $dia_semana);
                    $stmt_duplicate_horarios->bindParam(':hora_inicio', $hora_inicio);
                    $stmt_duplicate_horarios->bindParam(':hora_fin', $hora_fin);
                    $stmt_duplicate_horarios->bindParam(':turno', $turno);
                    if ($action === 'edit') {
                        $stmt_duplicate_horarios->bindParam(':id_horario', $id_horario, PDO::PARAM_INT);
                    }
                    $stmt_duplicate_horarios->execute();
                    if ($stmt_duplicate_horarios->fetchColumn() > 0) {
                        $conflict_found = true;
                        $conflict_message = 'Conflicto: Ya existe un horario idéntico programado para esta asignatura, curso y semestre.';
                    }
                }

                if ($conflict_found) {
                    set_flash_message('danger', $conflict_message);
                } else {
                    // Si no hay conflictos, proceder con la operación CRUD
                    if ($action === 'add') {
                        $stmt = $pdo->prepare("INSERT INTO horarios (id_semestre, id_asignatura, id_curso, id_profesor, id_aula, dia_semana, hora_inicio, hora_fin, turno) VALUES (:id_semestre, :id_asignatura, :id_curso, :id_profesor, :id_aula, :dia_semana, :hora_inicio, :hora_fin, :turno)");
                        $stmt->bindParam(':id_semestre', $id_semestre, PDO::PARAM_INT);
                        $stmt->bindParam(':id_asignatura', $id_asignatura, PDO::PARAM_INT);
                        $stmt->bindParam(':id_curso', $id_curso, PDO::PARAM_INT);
                        $stmt->bindParam(':id_profesor', $id_profesor, PDO::PARAM_INT);
                        $stmt->bindParam(':id_aula', $id_aula, PDO::PARAM_INT);
                        $stmt->bindParam(':dia_semana', $dia_semana);
                        $stmt->bindParam(':hora_inicio', $hora_inicio);
                        $stmt->bindParam(':hora_fin', $hora_fin);
                        $stmt->bindParam(':turno', $turno);

                        if ($stmt->execute()) { // Verifica si la ejecución fue exitosa
                            set_flash_message('success', 'Horario añadido correctamente.');
                        } else {
                            set_flash_message('danger', 'Error al añadir el horario.');
                        }
                    } elseif ($action === 'edit') {
                        if ($id_horario === null) {
                            set_flash_message('danger', 'Error: ID de horario no válido para edición.');
                        } else {
                            $stmt = $pdo->prepare("UPDATE horarios SET id_semestre = :id_semestre, id_asignatura = :id_asignatura, id_curso = :id_curso, id_profesor = :id_profesor, id_aula = :id_aula, dia_semana = :dia_semana, hora_inicio = :hora_inicio, hora_fin = :hora_fin, turno = :turno WHERE id = :id_horario");
                            $stmt->bindParam(':id_semestre', $id_semestre, PDO::PARAM_INT);
                            $stmt->bindParam(':id_asignatura', $id_asignatura, PDO::PARAM_INT);
                            $stmt->bindParam(':id_curso', $id_curso, PDO::PARAM_INT);
                            $stmt->bindParam(':id_profesor', $id_profesor, PDO::PARAM_INT);
                            $stmt->bindParam(':id_aula', $id_aula, PDO::PARAM_INT);
                            $stmt->bindParam(':dia_semana', $dia_semana);
                            $stmt->bindParam(':hora_inicio', $hora_inicio);
                            $stmt->bindParam(':hora_fin', $hora_fin);
                            $stmt->bindParam(':turno', $turno);
                            $stmt->bindParam(':id_horario', $id_horario, PDO::PARAM_INT);

                            if ($stmt->execute()) { // Verifica si la ejecución fue exitosa
                                set_flash_message('success', 'Horario actualizado correctamente.');
                            } else {
                                set_flash_message('danger', 'Error al actualizar el horario.');
                            }
                        }
                    }
                }
            } catch (PDOException $e) {
                set_flash_message('danger', 'Error de base de datos: ' . $e->getMessage());
            }
        }
    }
    // REDIRECCIÓN POST-GET: CRUCIAL para que los mensajes flash se muestren
    header('Location: ../admin/horarios.php'); // Redirige a la misma página (GET request)
    exit; // Termina la ejecución del script
}
