<?php
header('Content-Type: application/json');
require_once '../includes/conexion.php';

$response = ['status' => false, 'message' => '', 'data' => null];

if (!isset($_GET['id_profesor']) || !is_numeric($_GET['id_profesor'])) {
    $response['message'] = 'ID de profesor no proporcionado o inválido.';
    echo json_encode($response);
    exit();
}

$id_profesor = filter_var($_GET['id_profesor'], FILTER_VALIDATE_INT);

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $pdo->beginTransaction(); // Para consultas múltiples

        // 1. Obtener información básica del profesor (desde usuarios y profesores)
        $stmtProfesor = $pdo->prepare("
            SELECT
                u.id_usuario,
                u.nombre,
                u.apellido,
                u.email,
                u.telefono,
                p.especialidad
            FROM
                usuarios u
            LEFT JOIN
                profesores p ON u.id_usuario = p.id_profesor
            WHERE
                u.id_usuario = :id_profesor AND u.rol = 'profesor'
        ");
        $stmtProfesor->bindParam(':id_profesor', $id_profesor, PDO::PARAM_INT);
        $stmtProfesor->execute();
        $profesor_info = $stmtProfesor->fetch(PDO::FETCH_ASSOC);

        if (!$profesor_info) {
            $response['message'] = 'Profesor no encontrado o no tiene el rol correcto.';
            $pdo->rollBack();
            echo json_encode($response);
            exit();
        }

        // 2. Obtener Asignaturas asignadas al profesor y el conteo de alumnos inscritos en cada una
        $stmtAsignaturas = $pdo->prepare("
            SELECT
                ap.id_asignatura,
                a.nombre,
                a.codigo,
                c.nombre AS curso_nombre,
                s.nombre AS semestre_nombre,
                (
                    SELECT COUNT(DISTINCT i.id_estudiante)
                    FROM inscripciones i
                    WHERE i.id_asignatura = ap.id_asignatura AND i.estado = 'confirmado'
                ) AS alumnos_inscritos_count
            FROM
                asignatura_profesor ap
            JOIN
                asignaturas a ON ap.id_asignatura = a.id_asignatura
            LEFT JOIN
                cursos c ON a.curso_id = c.id_curso
            LEFT JOIN
                semestres s ON a.semestre_id = s.id_semestre
            WHERE
                ap.id_profesor = :id_profesor
            ORDER BY
                a.nombre ASC
        ");
        $stmtAsignaturas->bindParam(':id_profesor', $id_profesor, PDO::PARAM_INT);
        $stmtAsignaturas->execute();
        $asignaturas_asignadas = $stmtAsignaturas->fetchAll(PDO::FETCH_ASSOC);

        // 3. Obtener el total de alumnos únicos a cargo del profesor (en todas sus asignaturas confirmadas)
        $stmtTotalAlumnos = $pdo->prepare("
            SELECT COUNT(DISTINCT i.id_estudiante) AS total_alumnos
            FROM inscripciones i
            JOIN asignatura_profesor ap ON i.id_asignatura = ap.id_asignatura
            WHERE ap.id_profesor = :id_profesor AND i.estado = 'confirmado'
        ");
        $stmtTotalAlumnos->bindParam(':id_profesor', $id_profesor, PDO::PARAM_INT);
        $stmtTotalAlumnos->execute();
        $total_alumnos_a_cargo = $stmtTotalAlumnos->fetchColumn();

        // 4. Obtener Años Académicos disponibles (para el dropdown de filtro)
        $stmtAniosAcademicos = $pdo->query("SELECT id_anio, anio FROM anios_academicos ORDER BY anio DESC");
        $anios_academicos = $stmtAniosAcademicos->fetchAll(PDO::FETCH_ASSOC);


        $response['status'] = true;
        $response['data'] = [
            'profesor_info' => $profesor_info,
            'asignaturas_asignadas' => $asignaturas_asignadas,
            'total_alumnos_a_cargo' => $total_alumnos_a_cargo,
            'anios_academicos' => $anios_academicos
        ];
        $pdo->commit();

    } else {
        $response['message'] = 'Error: Conexión PDO no disponible.';
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Error de base de datos al obtener datos del dashboard del profesor: ' . $e->getMessage();
    error_log("Error en obtener_dashboard_profesor_data.php: " . $e->getMessage());
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Error inesperado al obtener datos del dashboard del profesor: ' . $e->getMessage();
    error_log("Error inesperado en obtener_dashboard_profesor_data.php: " . $e->getMessage());
}

echo json_encode($response);
?>
