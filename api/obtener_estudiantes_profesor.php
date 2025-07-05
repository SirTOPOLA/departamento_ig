<?php
// obtener_estudiantes_por_asignatura.php

// Incluye la configuración de la base de datos
require_once '../config/database.php';
// Puedes incluir aquí tu función check_login_and_role() si quieres que este endpoint también esté protegido
// require_once '../includes/functions.php';
// check_login_and_role('Profesor'); // Descomentar si deseas proteger este endpoint con sesión

header('Content-Type: text/html; charset=utf-8');

if (isset($_GET['id_horario']) && isset($_GET['turno'])) {
    $id_horario = intval($_GET['id_horario']);
    $turno = $_GET['turno']; // 'Tarde' o 'Noche'

    if ($id_horario <= 0 || !in_array($turno, ['Tarde', 'Noche'])) {
        echo '<div class="alert alert-warning" role="alert">Parámetros inválidos.</div>';
        exit;
    }

    try { 

        // Consulta para obtener los estudiantes inscritos en la asignatura para ese horario y turno específico
        $sql_estudiantes = "
            SELECT
                u.nombre_completo,
                e.codigo_registro,
                ie.fecha_inscripcion
            FROM
                inscripciones_estudiantes ie
            JOIN
                estudiantes e ON ie.id_estudiante = e.id
            JOIN
                usuarios u ON e.id_usuario = u.id
            JOIN
                horarios h ON ie.id_horario = h.id -- Unir con horarios para asegurar que coincida el turno
            WHERE
                ie.id_horario = :id_horario AND h.turno = :turno
            ORDER BY
                u.nombre_completo;
        ";

        $stmt_estudiantes = $pdo->prepare($sql_estudiantes);
        $stmt_estudiantes->execute([
            ':id_horario' => $id_horario,
            ':turno' => $turno
        ]);
        $lista_estudiantes = $stmt_estudiantes->fetchAll(PDO::FETCH_ASSOC);

        if (empty($lista_estudiantes)) {
            echo '<div class="alert alert-info" role="alert">No hay estudiantes registrados en esta asignatura para el turno seleccionado.</div>';
        } else {
            echo '<div class="table-responsive">';
            echo '<table class="table table-bordered table-striped">';
            echo '<thead class="thead-light"><tr><th>Nombre Completo</th><th>Código de Registro</th><th>Fecha de Inscripción</th></tr></thead>';
            echo '<tbody>';
            foreach ($lista_estudiantes as $estudiante) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($estudiante['nombre_completo']) . '</td>';
                echo '<td>' . htmlspecialchars($estudiante['codigo_registro']) . '</td>';
                echo '<td>' . htmlspecialchars($estudiante['fecha_inscripcion']) . '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        }

    } catch (PDOException $e) {
        error_log("Error al obtener estudiantes: " . $e->getMessage());
        echo '<div class="alert alert-danger" role="alert">Error de base de datos al cargar los estudiantes.</div>';
    } catch (Exception $e) {
        error_log("Error: " . $e->getMessage());
        echo '<div class="alert alert-danger" role="alert">Error inesperado al cargar los estudiantes.</div>';
    }
} else {
    echo '<div class="alert alert-danger" role="alert">Solicitud inválida.</div>';
}
?>