<?php
// obtener_estudiantes_grupo.php

require_once '../config/database.php';
header('Content-Type: text/html; charset=utf-8');

if (!isset($_GET['id_grupo_asignatura']) || !is_numeric($_GET['id_grupo_asignatura'])) {
    echo '<div class="alert alert-danger" role="alert">Parámetro inválido o no proporcionado.</div>';
    exit;
}

$id_grupo_asignatura = intval($_GET['id_grupo_asignatura']);

try {
    // Consulta para obtener estudiantes inscritos en ese grupo
    $sql = "
        SELECT
            u.nombre_completo,
            e.codigo_registro,
            ie.fecha_inscripcion
        FROM
            inscripciones_estudiantes ie
        INNER JOIN estudiantes e ON ie.id_estudiante = e.id
        INNER JOIN usuarios u ON e.id_usuario = u.id
        WHERE
            ie.id_grupo_asignatura = :id_grupo
        ORDER BY
            u.nombre_completo
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_grupo' => $id_grupo_asignatura]);
    $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($estudiantes)) {
        echo '<div class="alert alert-info" role="alert">No hay estudiantes inscritos en este grupo.</div>';
    } else {
        echo '<div class="table-responsive">';
        echo '<table class="table table-bordered table-striped">';
        echo '<thead><tr><th>Nombre Completo</th><th>Código Registro</th><th>Fecha de Inscripción</th></tr></thead>';
        echo '<tbody>';
        foreach ($estudiantes as $est) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($est['nombre_completo']) . '</td>';
            echo '<td>' . htmlspecialchars($est['codigo_registro']) . '</td>';
            echo '<td>' . htmlspecialchars($est['fecha_inscripcion']) . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

} catch (PDOException $e) {
    error_log("Error en la base de datos: " . $e->getMessage());
    echo '<div class="alert alert-danger" role="alert">Error al cargar los estudiantes.</div>';
}
