 
<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
check_login_and_role('Administrador');

$response = ['status' => false, 'message' => 'Ocurrió un error.'];

// Aseguramos que los datos vienen por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id_horario = $_POST['id_horario'] ?? null;

    // Validaciones comunes
    $id_semestre = $_POST['id_semestre'] ?? null;
    $id_asignatura = $_POST['id_asignatura'] ?? null;
    $id_curso = $_POST['id_curso'] ?? null;
    $id_profesor = $_POST['id_profesor'] ?? null;
    $grupo = $_POST['grupo'] ?? 'A';
    $id_aula = $_POST['id_aula'] ?? null;
    $dia_semana = $_POST['dia_semana'] ?? null;
    $hora_inicio = $_POST['hora_inicio'] ?? null;
    $hora_fin = $_POST['hora_fin'] ?? null;
    $turno = $_POST['turno'] ?? null;

    // Verificamos que todo lo necesario esté presente
    if (
        !$id_semestre || !$id_asignatura || !$id_profesor || !$id_curso || !$id_aula ||
        !$dia_semana || !$hora_inicio || !$hora_fin || !$turno
    ) {
        set_flash_message('Faltan campos obligatorios.', 'danger');
        header('Location: ../admin/horarios.php');
        exit;
    }

    // Aseguramos que el grupo exista en la tabla grupos_asignaturas o lo creamos
    $stmt = $pdo->prepare("SELECT id FROM grupos_asignaturas 
        WHERE id_asignatura = ? AND id_profesor = ? AND id_curso = ? AND turno = ? AND grupo = ?");
    $stmt->execute([$id_asignatura, $id_profesor, $id_curso, $turno, $grupo]);
    $grupo_asignatura = $stmt->fetch();

    if ($grupo_asignatura) {
        $id_grupo_asignatura = $grupo_asignatura['id'];
    } else {
        $stmt_insert = $pdo->prepare("INSERT INTO grupos_asignaturas (id_asignatura, id_profesor, id_curso, turno, grupo) 
            VALUES (?, ?, ?, ?, ?)");
        $stmt_insert->execute([$id_asignatura, $id_profesor, $id_curso, $turno, $grupo]);
        $id_grupo_asignatura = $pdo->lastInsertId();
    }

    if ($action === 'add') {
        $stmt = $pdo->prepare("INSERT INTO horarios (
            id_grupo_asignatura, id_semestre, id_aula, dia_semana, hora_inicio, hora_fin, turno
        ) VALUES (?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute([
            $id_grupo_asignatura,
            $id_semestre,
            $id_aula,
            $dia_semana,
            $hora_inicio,
            $hora_fin,
            $turno
        ]);

        set_flash_message('Horario registrado exitosamente.', 'success');
        header('Location: ../admin/horarios.php');
        exit;

    } elseif ($action === 'edit' && is_numeric($id_horario)) {
        $stmt = $pdo->prepare("UPDATE horarios SET 
            id_grupo_asignatura = ?, 
            id_semestre = ?, 
            id_aula = ?, 
            dia_semana = ?, 
            hora_inicio = ?, 
            hora_fin = ?, 
            turno = ?
            WHERE id = ?");

        $stmt->execute([
            $id_grupo_asignatura,
            $id_semestre,
            $id_aula,
            $dia_semana,
            $hora_inicio,
            $hora_fin,
            $turno,
            $id_horario
        ]);

        set_flash_message('Horario actualizado correctamente.', 'success');
        header('Location: ../admin/horarios.php');
        exit;

    } elseif ($action === 'delete' && is_numeric($id_horario)) {
        // Verificar si hay estudiantes inscritos en ese horario
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM inscripciones_estudiantes 
            WHERE id_grupo_asignatura = (
                SELECT id_grupo_asignatura FROM horarios WHERE id = ?
            )");
        $stmt_check->execute([$id_horario]);
        $count = $stmt_check->fetchColumn();

        if ($count > 0) {
            set_flash_message('No se puede eliminar el horario: hay estudiantes inscritos.', 'danger');
        } else {
            $stmt_delete = $pdo->prepare("DELETE FROM horarios WHERE id = ?");
            $stmt_delete->execute([$id_horario]);
            set_flash_message('Horario eliminado correctamente.', 'success');
        }

        header('Location: ../admin/horarios.php');
        exit;
    } else {
        set_flash_message('Acción no válida.', 'danger');
        header('Location: ../admin/horarios.php');
        exit;
    }
} else {
    set_flash_message('Método no permitido.', 'danger');
    header('Location: ../admin/horarios.php');
    exit;
}
