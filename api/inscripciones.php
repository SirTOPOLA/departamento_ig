<?php
// procesar_inscripcion_asignaturas.php (o como lo hayas nombrado)

// --- INICIO DE DEPURACIÓN TEMPORAL ---
// ¡RECUERDA ESTABLECER display_errors A 0 EN PRODUCCIÓN!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- FIN DE DEPURACIÓN TEMPORAL ---

// Es crucial: Inicia la sesión para acceder a $_SESSION['user_id'] y otros datos de sesión
session_start(); 

// Asegúrate de que las rutas sean correctas en relación a la ubicación de este script
require_once __DIR__ . '/../includes/functions.php'; // Por ejemplo, /departamento_ig/includes/functions.php
require_once __DIR__ . '/../config/database.php';   // Por ejemplo, /departamento_ig/config/database.php

// Establece la cabecera para indicar que la respuesta es JSON
header('Content-Type: application/json');

// Inicializa el array de respuesta
$respuesta = ['exito' => false, 'mensaje' => 'Solicitud no válida o error interno.'];

// --- 1. Validar Método de Solicitud ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $respuesta['mensaje'] = 'Método no permitido. Solo se aceptan solicitudes POST.';
    echo json_encode($respuesta);
    exit;
}

// --- 2. Autenticación de Usuario y Verificación de Rol ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Estudiante') {
    http_response_code(403); // Prohibido
    $respuesta['mensaje'] = 'Acceso no autorizado. Por favor, inicie sesión como estudiante.';
    echo json_encode($respuesta);
    exit;
}

$idUsuario = $_SESSION['user_id'];
$idEstudiante = null;       // Se completará después de obtener los detalles del estudiante
$semestreActual = null;     // Se completará después de obtener el semestre actual

try {
    // --- 3. Obtener el ID del Perfil del Estudiante ---
    $stmtPerfilEstudiante = $pdo->prepare("SELECT id FROM estudiantes WHERE id_usuario = :id_usuario");
    $stmtPerfilEstudiante->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
    $stmtPerfilEstudiante->execute();
    $perfilEstudiante = $stmtPerfilEstudiante->fetch(PDO::FETCH_ASSOC);

    if (!$perfilEstudiante) {
        $respuesta['mensaje'] = 'Error: No se encontró el perfil de estudiante asociado a su cuenta de usuario.';
        echo json_encode($respuesta);
        exit;
    }
    $idEstudiante = $perfilEstudiante['id'];

    // --- 4. Obtener el Semestre Académico Actual ---
    // Asumiendo que get_current_semester es robusto y devuelve un array o null
    $semestreActual = get_current_semester($pdo);

    if (!$semestreActual) {
        $respuesta['mensaje'] = 'No hay un semestre académico activo para la inscripción en este momento.';
        echo json_encode($respuesta);
        exit;
    }

    $idSemestreActual = $semestreActual['id'];
    // Asumiendo que semestreActual también podría proporcionar el ID del año académico
    $idAnioAcademicoActual = $semestreActual['id_anio_academico'] ?? null; 

    // Si el semestre actual no proporciona directamente el ID del año actual, lo buscamos
    if (!$idAnioAcademicoActual) {
        $stmtAnioActual = $pdo->prepare("SELECT id FROM anios_academicos WHERE fecha_inicio <= CURDATE() AND fecha_fin >= CURDATE() ORDER BY fecha_inicio DESC LIMIT 1");
        $stmtAnioActual->execute();
        $anioAcademicoActual = $stmtAnioActual->fetch(PDO::FETCH_ASSOC);
        if (!$anioAcademicoActual) {
            $respuesta['mensaje'] = 'No se pudo determinar el año académico actual para verificar la inscripción del curso.';
            echo json_encode($respuesta);
            exit;
        }
        $idAnioAcademicoActual = $anioAcademicoActual['id'];
    }

    // --- 5. Obtener el ID del Curso Actual del Estudiante ---
    // Esta consulta usa correctamente la tabla 'curso_estudiante'
    $stmtCursoEstudiante = $pdo->prepare("
        SELECT ce.id_curso
        FROM curso_estudiante ce
        WHERE ce.id_estudiante = :id_estudiante
          AND ce.id_anio = :id_anio_academico_actual
          AND ce.estado = 'activo'
        LIMIT 1
    ");
    $stmtCursoEstudiante->bindParam(':id_estudiante', $idEstudiante, PDO::PARAM_INT);
    $stmtCursoEstudiante->bindParam(':id_anio_academico_actual', $idAnioAcademicoActual, PDO::PARAM_INT);
    $stmtCursoEstudiante->execute();
    $cursoEstudiante = $stmtCursoEstudiante->fetch(PDO::FETCH_ASSOC);

    if (!$cursoEstudiante) {
        $respuesta['mensaje'] = 'Error: No estás inscrito en un curso activo para el año académico actual.';
        echo json_encode($respuesta);
        exit;
    }
    $idCursoEstudiante = $cursoEstudiante['id_curso']; // Este es el ID del curso del estudiante que necesitas

    // --- 6. Obtener Asignaturas Seleccionadas de los Datos POST ---
    // Asegúrate de que sea un array y limpia las entradas (aunque la vinculación PDO maneja la mayoría para IDs)
    $idsAsignaturasSeleccionadas = $_POST['selected_asignaturas'] ?? [];
    $idsAsignaturasSeleccionadas = array_filter(array_map('intval', (array)$idsAsignaturasSeleccionadas)); // Asegura que sea un array de enteros

    // --- 7. Validación Básica de la Selección ---
    if (empty($idsAsignaturasSeleccionadas)) {
        $respuesta['mensaje'] = 'No has seleccionado ninguna asignatura para inscribir.';
        echo json_encode($respuesta);
        exit;
    }
    if (count($idsAsignaturasSeleccionadas) > 6) { // Máximo 6 asignaturas
        $respuesta['mensaje'] = 'No puedes inscribirte en más de 6 asignaturas por semestre.';
        echo json_encode($respuesta);
        exit;
    }

    // --- 8. Iniciar Transacción para Operaciones Atómicas ---
    $pdo->beginTransaction();

    // --- 9. Validar Asignaturas Reprobadas Obligatorias ---
    // Busca asignaturas reprobadas que NO estén ya confirmadas como inscritas en el semestre actual
    $stmtReprobadas = $pdo->prepare("
        SELECT ha.id_asignatura
        FROM historial_academico ha
        WHERE ha.id_estudiante = :id_estudiante
        AND ha.estado_final = 'REPROBADO'
        AND ha.id_asignatura NOT IN (
            SELECT id_asignatura FROM inscripciones_estudiantes
            WHERE id_estudiante = :id_estudiante_check AND id_semestre = :id_semestre_check AND confirmada = 1
        )
    ");
    $stmtReprobadas->bindParam(':id_estudiante', $idEstudiante, PDO::PARAM_INT);
    $stmtReprobadas->bindParam(':id_estudiante_check', $idEstudiante, PDO::PARAM_INT);
    $stmtReprobadas->bindParam(':id_semestre_check', $idSemestreActual, PDO::PARAM_INT);
    $stmtReprobadas->execute();
    $idsReprobadasObligatorias = array_column($stmtReprobadas->fetchAll(PDO::FETCH_ASSOC), 'id_asignatura');

    foreach ($idsReprobadasObligatorias as $idReprobada) {
        if (!in_array($idReprobada, $idsAsignaturasSeleccionadas)) {
            // Obtener el nombre de la asignatura para un mensaje de error más claro
            $stmtNombreAsignatura = $pdo->prepare("SELECT nombre_asignatura FROM asignaturas WHERE id = :id_asignatura");
            $stmtNombreAsignatura->bindParam(':id_asignatura', $idReprobada, PDO::PARAM_INT);
            $stmtNombreAsignatura->execute();
            $nombreAsignatura = $stmtNombreAsignatura->fetchColumn() ?: 'una asignatura reprobada';
            throw new Exception("Debes seleccionar la asignatura reprobada obligatoria: '{$nombreAsignatura}'.");
        }
    }

    // --- 10. Validar Prerrequisitos para las Asignaturas Seleccionadas ---
    if (!empty($idsAsignaturasSeleccionadas)) {
        // Prepara los placeholders para la consulta IN
        $placeholders = implode(',', array_fill(0, count($idsAsignaturasSeleccionadas), '?'));
        $stmtAsignaturasSeleccionadas = $pdo->prepare("
            SELECT id, nombre_asignatura, id_prerequisito
            FROM asignaturas
            WHERE id IN ({$placeholders})
        ");
        // Ejecuta con los IDs de las asignaturas seleccionadas
        $stmtAsignaturasSeleccionadas->execute($idsAsignaturasSeleccionadas);
        $detallesAsignaturasSeleccionadas = $stmtAsignaturasSeleccionadas->fetchAll(PDO::FETCH_ASSOC);

        // Obtener todas las asignaturas que el estudiante ha APROBADO
        $stmtAprobadas = $pdo->prepare("
            SELECT id_asignatura FROM historial_academico
            WHERE id_estudiante = :id_estudiante AND estado_final = 'APROBADO'
        ");
        $stmtAprobadas->bindParam(':id_estudiante', $idEstudiante, PDO::PARAM_INT);
        $stmtAprobadas->execute();
        $idsAsignaturasAprobadas = $stmtAprobadas->fetchAll(PDO::FETCH_COLUMN);

        foreach ($detallesAsignaturasSeleccionadas as $asignatura) {
            // Omite la verificación de prerrequisitos para asignaturas reprobadas obligatorias
            if (in_array($asignatura['id'], $idsReprobadasObligatorias)) {
                continue;
            }

            if ($asignatura['id_prerequisito']) {
                if (!in_array($asignatura['id_prerequisito'], $idsAsignaturasAprobadas)) {
                    // Obtener el nombre del prerrequisito para un mensaje de error más claro
                    $stmtNombrePrerrequisito = $pdo->prepare("SELECT nombre_asignatura FROM asignaturas WHERE id = :id_prerrequisito");
                    $stmtNombrePrerrequisito->bindParam(':id_prerrequisito', $asignatura['id_prerequisito'], PDO::PARAM_INT);
                    $stmtNombrePrerrequisito->execute();
                    $nombrePrerrequisito = $stmtNombrePrerrequisito->fetchColumn() ?: 'un prerrequisito';
                    throw new Exception("No puedes inscribirte en '{$asignatura['nombre_asignatura']}' porque no has aprobado su prerrequisito: '{$nombrePrerrequisito}'.");
                }
            }
        }
    }

    // --- 11. Limpiar Inscripciones Pendientes Anteriores para este Semestre ---
    // Solo borra las inscripciones NO confirmadas para evitar perder las confirmadas previamente.
    $stmtBorrar = $pdo->prepare("
        DELETE FROM inscripciones_estudiantes
        WHERE id_estudiante = :id_estudiante AND id_semestre = :id_semestre_actual AND confirmada = 0
    ");
    $stmtBorrar->bindParam(':id_estudiante', $idEstudiante, PDO::PARAM_INT);
    $stmtBorrar->bindParam(':id_semestre_actual', $idSemestreActual, PDO::PARAM_INT);
    $stmtBorrar->execute();

    // --- 12. Insertar Nuevas Inscripciones ---
    $stmtInsertar = $pdo->prepare("
        INSERT INTO inscripciones_estudiantes (id_estudiante, id_semestre, id_asignatura, confirmada)
        VALUES (:id_estudiante, :id_semestre_actual, :id_asignatura, 0)
    ");
    foreach ($idsAsignaturasSeleccionadas as $idAsignatura) {
        // Verificar si la asignatura ya está confirmada para este estudiante en este semestre
        // La restricción UNIQUE en (id_estudiante, id_semestre, id_asignatura) también evitará duplicados reales.
        $stmtVerificarExistente = $pdo->prepare("
            SELECT COUNT(*) FROM inscripciones_estudiantes
            WHERE id_estudiante = :id_estudiante AND id_semestre = :id_semestre_actual AND id_asignatura = :id_asignatura AND confirmada = 1
        ");
        $stmtVerificarExistente->bindParam(':id_estudiante', $idEstudiante, PDO::PARAM_INT);
        $stmtVerificarExistente->bindParam(':id_semestre_actual', $idSemestreActual, PDO::PARAM_INT);
        $stmtVerificarExistente->bindParam(':id_asignatura', $idAsignatura, PDO::PARAM_INT);
        $stmtVerificarExistente->execute();

        if ($stmtVerificarExistente->fetchColumn() == 0) { // Solo inserta si no está ya confirmada
            $stmtInsertar->bindParam(':id_estudiante', $idEstudiante, PDO::PARAM_INT);
            $stmtInsertar->bindParam(':id_semestre_actual', $idSemestreActual, PDO::PARAM_INT);
            $stmtInsertar->bindParam(':id_asignatura', $idAsignatura, PDO::PARAM_INT);
            $stmtInsertar->execute();
        }
    }

    $pdo->commit();
    $respuesta['exito'] = true;
    $respuesta['mensaje'] = 'Inscripción registrada correctamente.';

} catch (Exception $e) {
    // Si hay un error, deshacer la transacción
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Registrar el error para depuración en el servidor
    error_log("Error de inscripción: " . $e->getMessage());
    $respuesta['mensaje'] = 'Error al procesar la inscripción: ' . $e->getMessage();
} catch (PDOException $e) {
    // Capturar errores específicos de PDO (ej. conexión a la base de datos perdida, error de sintaxis SQL)
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error de base de datos durante la inscripción: " . $e->getMessage());
    $respuesta['mensaje'] = 'Error de base de datos durante la inscripción. Por favor, inténtelo de nuevo más tarde.';
    // En producción, es posible que no quieras mostrar $e->getMessage() al usuario.
}

echo json_encode($respuesta);
exit;
?>