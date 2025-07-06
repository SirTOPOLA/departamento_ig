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
    // Primero, obtener el ID del estudiante a partir del ID de usuario
    $stmt_estudiante = $pdo->prepare("SELECT id FROM estudiantes WHERE id_usuario = :id_usuario");
    $stmt_estudiante->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
    $stmt_estudiante->execute();
    $id_estudiante = $stmt_estudiante->fetchColumn();

    if (!$id_estudiante) {
        echo json_encode(['success' => false, 'message' => 'No se encontró el estudiante para el ID de usuario proporcionado.']);
        exit;
    }

    // Consulta SQL para obtener el historial académico único por asignatura,
    // priorizando 'APROBADO'/'REPROBADO' y el registro más reciente en caso de múltiples.
    // Esta consulta usa una subconsulta para encontrar el 'id_historial' más relevante para cada asignatura.
    $stmt_historial = $pdo->prepare("
      WITH RankedHistory AS (
            SELECT
                ha.id AS id_historial,
                ha.id_asignatura,
                ha.id_estudiante,
                ha.nota_final,
                ha.estado_final,
                s.numero_semestre,
                s.fecha_inicio AS semestre_inicio,
                s.fecha_fin AS semestre_fin,
                aa.nombre_anio AS anio_academico,
                a.nombre_asignatura,
                -- Asigna un número de fila para cada registro dentro de cada asignatura
                -- Prioriza 'APROBADO' (1), 'REPROBADO' (2), luego el ID más reciente
                ROW_NUMBER() OVER (
                    PARTITION BY ha.id_asignatura, ha.id_estudiante
                    ORDER BY
                        CASE ha.estado_final
                            WHEN 'APROBADO' THEN 1
                            WHEN 'REPROBADO' THEN 2
                            ELSE 3 -- Cualquier otro estado (ej. PENDIENTE)
                        END,
                        ha.id DESC -- Si hay empates en el estado_final, toma el más reciente por ID
                ) as rn
            FROM historial_academico ha
            JOIN asignaturas a ON ha.id_asignatura = a.id
            JOIN semestres s ON ha.id_semestre = s.id
            JOIN anios_academicos aa ON s.id_anio_academico = aa.id
            WHERE ha.id_estudiante = :id_estudiante
        )
        SELECT
            id_historial,
            nombre_asignatura,
            numero_semestre,
            semestre_inicio,
            semestre_fin,
            anio_academico,
            nota_final,
            estado_final
        FROM RankedHistory
        WHERE
            estado_final IN ('APROBADO', 'REPROBADO') -- Incluye todos los registros con estado final
            OR
            (
                estado_final NOT IN ('APROBADO', 'REPROBADO') -- Si el registro no tiene estado final
                AND rn = 1 -- Y es el registro 'PENDIENTE' (o no final) más prioritario (por orden definido arriba)
                AND NOT EXISTS ( -- Y no existe NINGÚN registro final para esta asignatura
                    SELECT 1
                    FROM RankedHistory AS rh_inner
                    WHERE rh_inner.id_asignatura = RankedHistory.id_asignatura
                    AND rh_inner.id_estudiante = RankedHistory.id_estudiante
                    AND rh_inner.estado_final IN ('APROBADO', 'REPROBADO')
                )
            )
        ORDER BY anio_academico DESC, numero_semestre DESC, nombre_asignatura ASC;
    
    
    ");

    // Vincular el parámetro al ID del estudiante
    $stmt_historial->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
    
    // Ejecutar la consulta
    $stmt_historial->execute();

    // Obtener todos los resultados como un array asociativo
    $historial_filtrado = $stmt_historial->fetchAll(PDO::FETCH_ASSOC);

    // Si también necesitas los detalles del estudiante (nombre, matrícula), los puedes obtener aquí
    $stmt_student_details = $pdo->prepare("
        SELECT u.nombre_completo, e.codigo_registro
        FROM estudiantes e
        JOIN usuarios u ON e.id_usuario = u.id
        WHERE e.id = :id_estudiante
    ");
    $stmt_student_details->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
    $stmt_student_details->execute();
    $student_details = $stmt_student_details->fetch(PDO::FETCH_ASSOC);

    // Devolver la respuesta en formato JSON
    echo json_encode([
        'success' => true,
        'student_details' => $student_details,
        'historial' => $historial_filtrado
    ]);

} catch (PDOException $e) {
    // Capturar cualquier error de la base de datos y devolver una respuesta JSON de error
    error_log("Error en get_historial_academico.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error de base de datos al obtener historial académico: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Capturar cualquier otra excepción
    error_log("Excepción en get_historial_academico.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno al obtener historial académico: ' . $e->getMessage()]);
}
?>