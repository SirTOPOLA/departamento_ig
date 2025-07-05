<?php
// obtener_horarios_profesores.php
 
// Incluye la configuración de la base de datos
require_once '../includes/functions.php'; // Si tienes alguna función de utilidad como get_db_connection() o check_login_and_role()
require_once '../config/database.php'; // Asegúrate de que $pdo esté disponible desde aquí

header('Content-Type: text/html; charset=utf-8');

check_login_and_role('Estudiante');

 
header('Content-Type: application/json');

$respuesta = ['exito' => false, 'mensaje' => '', 'horarios' => []]; // Variables en español

 

// Aquí podrías añadir una verificación de rol si solo los estudiantes deben ver esto
// verificarInicioSesionYRola('Estudiante');

if (isset($_GET['id_asignatura']) && isset($_GET['id_semestre'])) {
    $id_asignatura = (int)$_GET['id_asignatura'];
    $id_semestre = (int)$_GET['id_semestre'];

    try { 
        // Se unen las tablas para obtener los detalles del profesor (nombre_completo de usuarios)
        // y del aula (nombre_aula, capacidad)
        $consulta = $pdo->prepare("
            SELECT
                h.id,
                h.dia_semana,
                h.hora_inicio,
                h.hora_fin,
                h.turno,
                p.id AS id_profesor,
                u.nombre_completo,
                a.nombre_aula,
                a.capacidad
            FROM
                horarios h
            JOIN
                profesores p ON h.id_profesor = p.id
            JOIN
                usuarios u ON p.id_usuario = u.id
            JOIN
                aulas a ON h.id_aula = a.id
            WHERE
                h.id_asignatura = :id_asignatura AND h.id_semestre = :id_semestre
            ORDER BY
                h.dia_semana, h.hora_inicio;
        ");

        $consulta->bindParam(':id_asignatura', $id_asignatura, PDO::PARAM_INT);
        $consulta->bindParam(':id_semestre', $id_semestre, PDO::PARAM_INT);
        $consulta->execute();

        $lista_horarios = $consulta->fetchAll(PDO::FETCH_ASSOC);

        $respuesta['exito'] = true;
        $respuesta['horarios'] = $lista_horarios;

    } catch (PDOException $e) {
        $respuesta['mensaje'] = 'Error de base de datos al obtener horarios: ' . $e->getMessage();
        error_log('obtener_horarios.php PDOException: ' . $e->getMessage()); // Para depuración
    }
} else {
    $respuesta['mensaje'] = 'Parámetros de asignatura o semestre faltantes en la solicitud.';
}

echo json_encode($respuesta);
?>