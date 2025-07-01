<?php
// get_historial_academico.php
// Este script se encarga de obtener el historial académico de un estudiante específico,
// incluyendo detalles de la asignatura, semestre y año académico, nota final y estado.

// Incluir funciones auxiliares y la configuración de la base de datos
require_once '../includes/functions.php';
require_once '../config/database.php'; // Conexión PDO

// Establecer el encabezado para indicar que la respuesta será JSON
header('Content-Type: application/json');

// Filtrar y validar el parámetro de entrada (GET)
$id_usuario = filter_var($_GET['id_usuario'] ?? null, FILTER_VALIDATE_INT);

// Verificar que el ID de usuario ha sido proporcionado y es válido
if (!$id_usuario) {
    echo json_encode(['success' => false, 'message' => 'ID de usuario no proporcionado o inválido.']);
    exit; // Terminar la ejecución si el parámetro no es válido
}

try {
    // Preparar la consulta SQL para obtener el historial académico
    // Se unen las tablas para obtener nombres de asignaturas, números de semestre,
    // nombres de años académicos, notas finales y estados finales.
    $stmt_historial = $pdo->prepare("
        SELECT
            ha.id AS id_historial,          -- ID del registro de historial
            a.nombre_asignatura,            -- Nombre de la asignatura
            s.numero_semestre,              -- Número del semestre
            aa.nombre_anio,                 -- Nombre del año académico
            ha.nota_final,                  -- Nota final obtenida
            ha.estado_final                 -- Estado final (APROBADO/REPROBADO/PENDIENTE)
        FROM historial_academico ha
        JOIN estudiantes e ON ha.id_estudiante = e.id  -- Unir con la tabla estudiantes
        JOIN usuarios u ON e.id_usuario = u.id          -- Unir con la tabla usuarios para filtrar por id_usuario
        JOIN asignaturas a ON ha.id_asignatura = a.id   -- Unir con la tabla asignaturas
        JOIN semestres s ON ha.id_semestre = s.id       -- Unir con la tabla semestres
        JOIN anios_academicos aa ON s.id_anio_academico = aa.id -- Unir con la tabla anios_academicos
        WHERE u.id = :id_usuario                        -- Filtrar por el ID de usuario del estudiante
        ORDER BY aa.nombre_anio DESC, s.numero_semestre DESC, a.nombre_asignatura ASC
    ");

    // Vincular el parámetro a la consulta preparada
    $stmt_historial->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);

    // Ejecutar la consulta
    $stmt_historial->execute();

    // Obtener todos los resultados como un array asociativo
    $historial = $stmt_historial->fetchAll(PDO::FETCH_ASSOC);

    // Devolver la respuesta en formato JSON con éxito y los datos del historial
    echo json_encode(['success' => true, 'historial' => $historial]);

} catch (PDOException $e) {
    // Capturar cualquier error de la base de datos y devolver una respuesta JSON de error
    error_log("Error en get_historial_academico.php: " . $e->getMessage()); // Registrar el error para depuración
    echo json_encode(['success' => false, 'message' => 'Error de base de datos al obtener historial académico: ' . $e->getMessage()]);
}
?>