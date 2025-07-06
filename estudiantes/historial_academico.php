<?php
// vistas/estudiante/historial_academico.php - Muestra el historial académico del estudiante

// --- INICIO DE DEPURACIÓN TEMPORAL ---
// ¡RECUERDA ESTABLECER display_errors A 0 EN PRODUCCIÓN!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- FIN DE DEPURACIÓN TEMPORAL ---
 
// Asegúrate de que las rutas sean correctas en relación a la ubicación de este script
require_once '../config/database.php'; // Ajusta la ruta si es necesario
require_once '../includes/functions.php'; // Ajusta la ruta si es necesario

// Asegúrate de que solo los estudiantes puedan acceder a esta página
check_login_and_role('Estudiante');

$idUsuario = $_SESSION['user_id'] ?? null;
$idEstudiante = null;
$nombreEstudiante = '';
$codigoRegistro = '';

try {
    // Obtener el ID del estudiante y su información básica (nombre completo, código de registro)
    $stmtEstudiante = $pdo->prepare("
        SELECT e.id, u.nombre_completo, e.codigo_registro
        FROM estudiantes e
        JOIN usuarios u ON e.id_usuario = u.id
        WHERE u.id = :id_usuario
    ");
    $stmtEstudiante->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
    $stmtEstudiante->execute();
    $datosEstudiante = $stmtEstudiante->fetch(PDO::FETCH_ASSOC);

    if (!$datosEstudiante) {
        // Si no se encuentra el perfil de estudiante, mostrar un error y salir
        echo '<div class="alert alert-danger text-center mt-5">Error: No se encontró el perfil de estudiante asociado a su cuenta. Por favor, contacte con la administración.</div>';
        exit;
    }
    $idEstudiante = $datosEstudiante['id'];
    $nombreEstudiante = htmlspecialchars($datosEstudiante['nombre_completo']);
    $codigoRegistro = htmlspecialchars($datosEstudiante['codigo_registro']);

    // --- Obtener el historial académico completo del estudiante ---
    $stmtHistorial = $pdo->prepare("
        SELECT
            ha.nota_final,
            ha.estado_final,
            ha.fecha_actualizacion,
            a.nombre_asignatura,
            a.creditos,
            a.semestre_recomendado,
            c.nombre_curso,
            c.id AS id_curso,
            s.numero_semestre,
            sa.nombre_anio,
            n.observaciones_admin -- Incluir observaciones del admin si existen en la tabla notas
        FROM historial_academico ha
        JOIN asignaturas a ON ha.id_asignatura = a.id
        JOIN semestres s ON ha.id_semestre = s.id
        JOIN anios_academicos sa ON s.id_anio_academico = sa.id
        JOIN cursos c ON a.id_curso = c.id
        LEFT JOIN inscripciones_estudiantes ie ON ha.id_estudiante = ie.id_estudiante AND ha.id_asignatura = ie.id_asignatura AND ha.id_semestre = ie.id_semestre
        LEFT JOIN notas n ON ie.id = n.id_inscripcion -- Unir con notas a través de inscripciones_estudiantes
        WHERE ha.id_estudiante = :id_estudiante
        AND ha.estado_final IN ('APROBADO', 'REPROBADO')
        ORDER BY sa.nombre_anio DESC, c.id ASC, s.numero_semestre ASC, a.semestre_recomendado ASC, a.nombre_asignatura ASC
    ");
    $stmtHistorial->bindParam(':id_estudiante', $idEstudiante, PDO::PARAM_INT);
    $stmtHistorial->execute();
    $historialAcademicoCrudo = $stmtHistorial->fetchAll(PDO::FETCH_ASSOC);

    // --- Estructurar el historial por Año Académico -> Curso -> Semestre ---
    $historialAgrupado = [];
    foreach ($historialAcademicoCrudo as $registro) {
        $anio = $registro['nombre_anio'];
        $cursoId = $registro['id_curso'];
        $nombreCurso = $registro['nombre_curso'];
        $semestreNum = $registro['numero_semestre'];

        if (!isset($historialAgrupado[$anio])) {
            $historialAgrupado[$anio] = [
                'nombre_anio' => $anio,
                'cursos' => []
            ];
        }
        if (!isset($historialAgrupado[$anio]['cursos'][$cursoId])) {
            $historialAgrupado[$anio]['cursos'][$cursoId] = [
                'id_curso' => $cursoId,
                'nombre_curso' => $nombreCurso,
                'semestres' => [],
                'total_asignaturas_aprobadas_curso' => 0, // Contador para la lógica de aprobación de curso
                'estado_curso' => 'EN PROGRESO' // Estado inicial del curso
            ];
        }
        if (!isset($historialAgrupado[$anio]['cursos'][$cursoId]['semestres'][$semestreNum])) {
            $historialAgrupado[$anio]['cursos'][$cursoId]['semestres'][$semestreNum] = [
                'numero_semestre' => $semestreNum,
                'asignaturas' => []
            ];
        }

        $historialAgrupado[$anio]['cursos'][$cursoId]['semestres'][$semestreNum]['asignaturas'][] = $registro;

        // Contar asignaturas aprobadas para la lógica de aprobación del curso
        if ($registro['estado_final'] === 'APROBADO') {
            $historialAgrupado[$anio]['cursos'][$cursoId]['total_asignaturas_aprobadas_curso']++;
        }
    }

    // --- Calcular el estado de aprobación de cada curso ---
    // Primero, obtener el total de asignaturas esperadas por curso (asumiendo 12 por curso: 6 por semestre)
    // Esto se basa en la definición de asignaturas en la BD para cada curso.
    $stmtTotalAsignaturasCurso = $pdo->prepare("
        SELECT id_curso, COUNT(id) AS total_asignaturas_esperadas
        FROM asignaturas
        GROUP BY id_curso
    ");
    $stmtTotalAsignaturasCurso->execute();
    $totalAsignaturasPorCurso = $stmtTotalAsignaturasCurso->fetchAll(PDO::FETCH_KEY_PAIR); // [id_curso => count]

    foreach ($historialAgrupado as $anio => &$datosAnio) {
        foreach ($datosAnio['cursos'] as $cursoId => &$datosCurso) {
            $totalEsperado = $totalAsignaturasPorCurso[$cursoId] ?? 0;
            // Un curso se considera aprobado si todas sus asignaturas (total esperado) han sido aprobadas
            if ($totalEsperado > 0 && $datosCurso['total_asignaturas_aprobadas_curso'] >= $totalEsperado) {
                $datosCurso['estado_curso'] = 'APROBADO';
            } else {
                $datosCurso['estado_curso'] = 'EN PROGRESO'; // Si no está completamente aprobado, está en progreso
            }
        }
    }

} catch (PDOException $e) {
    error_log("Error de base de datos al cargar historial académico: " . $e->getMessage());
    echo '<div class="alert alert-danger text-center mt-5">Ocurrió un error al cargar su historial académico. Por favor, inténtelo de nuevo más tarde.</div>';
    exit;
}
?>

<?php include '../includes/header.php'; // Asegúrate de que este archivo incluye Bootstrap 5 y Font Awesome ?>

<div class="container py-4">
    <div class="card shadow-lg rounded-3 mb-5 border-0">
        <div class="card-header bg-primary text-white text-center py-4 rounded-top">
            <h1 class="mb-0 fw-bold"><i class="fas fa-graduation-cap me-2"></i>Boletín Oficial de Calificaciones</h1>
            <p class="lead mb-0 mt-2">Mi Historial Académico </p>
        </div>
        <div class="card-body p-4">
            <div class="row mb-4 align-items-center">
                <div class="col-md-6">
                    <h4 class="text-primary mb-1"><i class="fas fa-user-graduate me-2"></i>Estudiante: <span class="text-dark"><?php echo $nombreEstudiante; ?></span></h4>
                </div>
                <div class="col-md-6 text-md-end">
                    <h4 class="text-primary mb-1"><i class="fas fa-id-card me-2"></i>Código de Registro: <span class="text-dark"><?php echo $codigoRegistro; ?></span></h4>
                </div>
            </div>

            <hr class="my-4 border-primary">

            <div class="text-end mb-4">
                <a href="../libreria/generar_pdf_notas.php?id_estudiante=<?php echo $idEstudiante; ?>" class="btn btn-danger btn-lg shadow-sm" target="_blank">
                    <i class="fas fa-file-pdf me-2"></i> Imprimir PDF
                </a>
            </div>

            <?php if (empty($historialAcademicoCrudo)): ?>
                <div class="alert alert-info text-center p-4 rounded-3 shadow-sm">
                    <h4 class="alert-heading"><i class="fas fa-info-circle me-2"></i>¡Historial Vacío!</h4>
                    <p class="mb-0">Aún no tienes asignaturas registradas en tu historial académico con una calificación final.</p>
                    <hr>
                    <p class="mb-0">Las asignaturas aparecerán aquí una vez que hayan sido calificadas como APROBADAS o REPROBADAS.</p>
                </div>
            <?php else: ?>
                <?php foreach ($historialAgrupado as $anio => $datosAnio): ?>
                    <div class="mb-5">
                        <h3 class="text-center text-secondary mb-4 p-3 bg-light rounded-pill shadow-sm border">
                            <i class="fas fa-calendar-alt me-2"></i>Año Académico: <span class="fw-bold text-dark"><?php echo htmlspecialchars($datosAnio['nombre_anio']); ?></span>
                        </h3>
                        <div class="accordion" id="acordeonAnio<?php echo str_replace('-', '', $anio); ?>">
                            <?php foreach ($datosAnio['cursos'] as $cursoId => $datosCurso):
                                $idAcordeonCurso = 'cursoColapsar' . $cursoId . str_replace('-', '', $anio);
                                $idCabeceraCurso = 'cursoEncabezado' . $cursoId . str_replace('-', '', $anio);

                                $claseInsigniaCurso = 'secondary';
                                $iconoCurso = 'fas fa-spinner fa-spin'; // Icono por defecto para en progreso
                                $claseFondoCurso = 'bg-light';

                                switch ($datosCurso['estado_curso']) {
                                    case 'APROBADO':
                                        $claseInsigniaCurso = 'success';
                                        $iconoCurso = 'fas fa-check-circle';
                                        $claseFondoCurso = 'bg-success-subtle';
                                        break;
                                    case 'EN PROGRESO':
                                        $claseInsigniaCurso = 'info';
                                        $iconoCurso = 'fas fa-sync-alt';
                                        $claseFondoCurso = 'bg-info-subtle';
                                        break;
                                    case 'PENDIENTE': // Si se usa este estado para cursos no iniciados o con problemas
                                        $claseInsigniaCurso = 'warning';
                                        $iconoCurso = 'fas fa-exclamation-triangle';
                                        $claseFondoCurso = 'bg-warning-subtle';
                                        break;
                                }
                            ?>
                                <div class="accordion-item mb-3 border rounded-3 shadow-sm">
                                    <h2 class="accordion-header" id="<?php echo $idCabeceraCurso; ?>">
                                        <button class="accordion-button <?php echo ($datosCurso['estado_curso'] === 'EN PROGRESO') ? '' : 'collapsed'; ?> <?php echo $claseFondoCurso; ?> text-dark fw-bold py-3" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $idAcordeonCurso; ?>" aria-expanded="<?php echo ($datosCurso['estado_curso'] === 'EN PROGRESO') ? 'true' : 'false'; ?>" aria-controls="<?php echo $idAcordeonCurso; ?>">
                                            <div class="d-flex align-items-center w-100">
                                                <i class="<?php echo $iconoCurso; ?> me-3 fs-4" style="min-width: 25px;"></i>
                                                <span class="flex-grow-1 fs-5">Curso: <strong class="text-primary"><?php echo htmlspecialchars($datosCurso['nombre_curso']); ?></strong></span>
                                                <span class="badge bg-<?php echo $claseInsigniaCurso; ?> ms-auto p-2 me-2 fs-6 text-uppercase">
                                                    <?php echo htmlspecialchars($datosCurso['estado_curso']); ?>
                                                </span>
                                            </div>
                                        </button>
                                    </h2>
                                    <div id="<?php echo $idAcordeonCurso; ?>" class="accordion-collapse collapse <?php echo ($datosCurso['estado_curso'] === 'EN PROGRESO') ? 'show' : ''; ?>" aria-labelledby="<?php echo $idCabeceraCurso; ?>" data-bs-parent="#acordeonAnio<?php echo str_replace('-', '', $anio); ?>">
                                        <div class="accordion-body p-4 bg-light">
                                            <?php foreach ($datosCurso['semestres'] as $semestreNum => $datosSemestre):
                                                // Determinar si el semestre está "aprobado" (todas las asignaturas cursadas en ese semestre están aprobadas)
                                                $asignaturasAprobadasSemestre = 0;
                                                $totalAsignaturasCursadasSemestre = count($datosSemestre['asignaturas']);
                                                foreach ($datosSemestre['asignaturas'] as $asigSem) {
                                                    if ($asigSem['estado_final'] === 'APROBADO') {
                                                        $asignaturasAprobadasSemestre++;
                                                    }
                                                }
                                                // Un semestre se considera 'APROBADO' si todas sus asignaturas registradas
                                                // en el historial (que ya tienen estado APROBADO/REPROBADO) están aprobadas.
                                                // Si no hay asignaturas registradas con estado final, se considera 'EN PROGRESO' por defecto aquí.
                                                $estadoSemestre = ($totalAsignaturasCursadasSemestre > 0 && $asignaturasAprobadasSemestre === $totalAsignaturasCursadasSemestre) ? 'APROBADO' : 'EN PROGRESO';
                                                $claseInsigniaSemestre = ($estadoSemestre === 'APROBADO') ? 'success' : 'info';
                                                $iconoSemestre = ($estadoSemestre === 'APROBADO') ? 'fas fa-check' : 'fas fa-circle';
                                            ?>
                                                <div class="mb-4 p-3 border rounded-3 shadow-sm bg-white">
                                                    <h5 class="mb-3 text-secondary d-flex align-items-center pb-2 border-bottom">
                                                        <i class="fas fa-book-open me-2"></i>Semestre: <span class="fw-bold text-dark"><?php echo htmlspecialchars($datosSemestre['numero_semestre']); ?></span>
                                                        <span class="badge bg-<?php echo $claseInsigniaSemestre; ?> ms-auto text-uppercase p-2">
                                                            <i class="<?php echo $iconoSemestre; ?> me-1"></i><?php echo htmlspecialchars($estadoSemestre); ?>
                                                        </span>
                                                    </h5>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-striped table-bordered table-hover caption-top align-middle">
                                                            <caption>Detalle de asignaturas para el Semestre <?php echo htmlspecialchars($datosSemestre['numero_semestre']); ?></caption>
                                                            <thead class="table-dark">
                                                                <tr>
                                                                    <th scope="col">Asignatura</th>
                                                                    <th scope="col" class="text-center">Créditos</th>
                                                                    <th scope="col" class="text-center">Nota Final</th>
                                                                    <th scope="col" class="text-center">Estado</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($datosSemestre['asignaturas'] as $asignatura):
                                                                    // Estas asignaturas ya están filtradas por APROBADO/REPROBADO por la consulta SQL
                                                                    $claseInsigniaAsignatura = ($asignatura['estado_final'] === 'APROBADO') ? 'success' : 'danger';
                                                                    $iconoAsignatura = ($asignatura['estado_final'] === 'APROBADO') ? 'fas fa-check' : 'fas fa-times';
                                                                ?>
                                                                    <tr>
                                                                        <td><?php echo htmlspecialchars($asignatura['nombre_asignatura']); ?></td>
                                                                        <td class="text-center"><?php echo htmlspecialchars($asignatura['creditos']); ?></td>
                                                                        <td class="text-center fs-6 fw-bold"><?php echo htmlspecialchars($asignatura['nota_final'] ?? 'N/A'); ?></td>
                                                                        <td class="text-center">
                                                                            <span class="badge bg-<?php echo $claseInsigniaAsignatura; ?> p-2 text-uppercase">
                                                                                <i class="<?php echo $iconoAsignatura; ?> me-1"></i><?php echo htmlspecialchars($asignatura['estado_final']); ?>
                                                                            </span>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; // Incluye el pie de página HTML ?>