<?php
// obtener_asignaturas_modal.php

session_start(); // Crucial: Inicia la sesión para acceder a $_SESSION['user_id']
ini_set('display_errors', 0); // Deshabilitar para producción
ini_set('display_startup_errors', 0); // Deshabilitar para producción
error_reporting(E_ALL); // Registrar todos los errores en producción, pero no mostrarlos

// Ajusta las rutas si es necesario. Es una buena práctica definir una ruta base si es posible.
require_once '../includes/functions.php';
require_once '../config/database.php'; // Este archivo debe establecer la conexión $pdo

// --- 1. Autenticación y Verificación de Rol ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Estudiante') {
    http_response_code(403); // Prohibido
    echo '<div class="alert alert-danger">Acceso no autorizado. Por favor, inicia sesión como estudiante.</div>';
    exit;
}

$idUsuario = $_SESSION['user_id'];

// --- 2. Obtener Detalles del Estudiante y su Curso Actual ---
// Usar un bloque try-catch para operaciones de base de datos es una buena práctica.
try {
    // Primero, obtener el ID del perfil principal del estudiante
    $stmtPerfilEstudiante = $pdo->prepare("SELECT id FROM estudiantes WHERE id_usuario = :id_usuario");
    $stmtPerfilEstudiante->bindParam(':id_usuario', $idUsuario, PDO::PARAM_INT);
    $stmtPerfilEstudiante->execute();
    $perfilEstudiante = $stmtPerfilEstudiante->fetch(PDO::FETCH_ASSOC);

    if (!$perfilEstudiante) {
        http_response_code(404); // No encontrado, ya que el perfil del estudiante no está vinculado
        echo '<div class="alert alert-danger">Error: No se encontró el perfil de estudiante asociado a su cuenta.</div>';
        exit;
    }
    $idEstudiante = $perfilEstudiante['id'];

    // Ahora, obtener el ID del curso actual de 'curso_estudiante' para el año académico activo
    // Necesitarás el ID del año académico actual; asumiremos que 'semestreActual['id_anio_academico']' está disponible.
    // Si no, podrías necesitar otra consulta para obtener el ID del año académico actual.
    // Primero, obtenemos el semestre actual para tener el id_anio_academico
    $semestreActual = get_current_semester($pdo);

    if (!$semestreActual) {
        echo '<div class="alert alert-warning text-center">No hay un semestre académico activo para la inscripción en este momento.</div>';
        exit;
    }

    $idSemestreActual = $semestreActual['id'];
    $numeroSemestreActual = $semestreActual['numero_semestre'];
    $esSemestreActualImpar = ($numeroSemestreActual % 2 !== 0);


    if (!isset($semestreActual['id_anio_academico'])) {
        // Fallback o consulta específica si 'semestreActual' no tiene esto
        $stmtAnioActual = $pdo->prepare("SELECT id FROM anios_academicos WHERE fecha_inicio <= CURDATE() AND fecha_fin >= CURDATE() ORDER BY fecha_inicio DESC LIMIT 1");
        $stmtAnioActual->execute();
        $anioAcademicoActual = $stmtAnioActual->fetch(PDO::FETCH_ASSOC);
        if (!$anioAcademicoActual) {
            echo '<div class="alert alert-warning text-center">No se pudo determinar el año académico actual.</div>';
            exit;
        }
        $idAnioAcademicoActual = $anioAcademicoActual['id'];
    } else {
        $idAnioAcademicoActual = $semestreActual['id_anio_academico'];
    }

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
        http_response_code(400); // Solicitud incorrecta, el estudiante no está inscrito en un curso para el año actual
        echo '<div class="alert alert-danger">Error: El estudiante no está inscrito en un curso activo para el año académico actual.</div>';
        exit;
    }
    $idCursoEstudiante = $cursoEstudiante['id_curso'];

} catch (PDOException $e) {
    error_log("Error de base de datos al obtener detalles del estudiante o curso: " . $e->getMessage());
    http_response_code(500);
    echo '<div class="alert alert-danger">Ocurrió un error interno al intentar obtener los detalles del estudiante o su curso.</div>';
    exit;
}

// --- 3. Obtener Asignaturas Reprobadas (Obligatorias) ---
$asignaturasReprobadas = [];
$idsAsignaturasReprobadas = [];
try {
    $stmtAsignaturasReprobadas = $pdo->prepare("
        SELECT
            ha.id_asignatura AS id,
            a.nombre_asignatura,
            a.creditos,
            c.nombre_curso,
            a.semestre_recomendado AS numero_semestre_recomendado,
            s.numero_semestre AS numero_semestre_historico,
            aa.nombre_anio AS nombre_anio_academico
        FROM historial_academico ha
        JOIN asignaturas a ON ha.id_asignatura = a.id
        LEFT JOIN cursos c ON a.id_curso = c.id
        JOIN semestres s ON ha.id_semestre = s.id
        JOIN anios_academicos aa ON s.id_anio_academico = aa.id
        WHERE ha.id_estudiante = :id_estudiante
        AND ha.estado_final = 'REPROBADO'
        AND a.id NOT IN (
            SELECT id_asignatura FROM inscripciones_estudiantes
            WHERE id_estudiante = :id_estudiante_actual AND id_semestre = :id_semestre_actual AND confirmada = 1
        )
        ORDER BY c.nombre_curso ASC, a.semestre_recomendado ASC, a.nombre_asignatura ASC
    ");
    $stmtAsignaturasReprobadas->bindParam(':id_estudiante', $idEstudiante, PDO::PARAM_INT);
    $stmtAsignaturasReprobadas->bindParam(':id_estudiante_actual', $idEstudiante, PDO::PARAM_INT);
    $stmtAsignaturasReprobadas->bindParam(':id_semestre_actual', $idSemestreActual, PDO::PARAM_INT);
    $stmtAsignaturasReprobadas->execute();
    $asignaturasReprobadas = $stmtAsignaturasReprobadas->fetchAll(PDO::FETCH_ASSOC);
    $idsAsignaturasReprobadas = array_column($asignaturasReprobadas, 'id');
} catch (PDOException $e) {
    error_log("Error de base de datos al obtener asignaturas reprobadas: " . $e->getMessage());
    // Continuar con un array vacío, ya que no es crítico detener todo el proceso
}


// --- 4. Obtener Asignaturas Aprobadas (para verificación de prerrequisitos) ---
$idsAsignaturasAprobadas = [];
try {
    $stmtAsignaturasAprobadas = $pdo->prepare("
        SELECT id_asignatura FROM historial_academico
        WHERE id_estudiante = :id_estudiante AND estado_final = 'APROBADO'
    ");
    $stmtAsignaturasAprobadas->bindParam(':id_estudiante', $idEstudiante, PDO::PARAM_INT);
    $stmtAsignaturasAprobadas->execute();
    $idsAsignaturasAprobadas = $stmtAsignaturasAprobadas->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error de base de datos al obtener asignaturas aprobadas: " . $e->getMessage());
    // Continuar con un array vacío
}


// --- 5. Obtener Asignaturas Ya Inscritas en el Semestre Actual (para deshabilitar) ---
$idsInscripcionesActuales = [];
try {
    $stmtInscripcionesActuales = $pdo->prepare("
        SELECT id_asignatura FROM inscripciones_estudiantes
        WHERE id_estudiante = :id_estudiante AND id_semestre = :id_semestre_actual
    ");
    $stmtInscripcionesActuales->bindParam(':id_estudiante', $idEstudiante, PDO::PARAM_INT);
    $stmtInscripcionesActuales->bindParam(':id_semestre_actual', $idSemestreActual, PDO::PARAM_INT);
    $stmtInscripcionesActuales->execute();
    $idsInscripcionesActuales = $stmtInscripcionesActuales->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error de base de datos al obtener inscripciones actuales: " . $e->getMessage());
    // Continuar con un array vacío
}

// --- 6. Obtener Asignaturas Disponibles (base para el modal) ---
$asignaturasDisponiblesCursoActual = [];
try {
    $stmtAsignaturasDisponibles = $pdo->prepare("
    SELECT DISTINCT
        a.id,
        a.nombre_asignatura,
        a.creditos,
        a.id_prerequisito,
        pa.nombre_asignatura AS nombre_prerrequisito,
        c.nombre_curso,
        a.semestre_recomendado
    FROM asignaturas a
    LEFT JOIN asignaturas pa ON a.id_prerequisito = pa.id
    JOIN cursos c ON a.id_curso = c.id
    JOIN grupos_asignaturas ga ON ga.id_asignatura = a.id -- Asegura que haya al menos un grupo asociado
    WHERE a.id_curso = :id_curso_estudiante
      AND a.id NOT IN (
          SELECT id_asignatura FROM historial_academico
          WHERE id_estudiante = :id_estudiante_historial_aprobado AND estado_final = 'APROBADO'
      )
      AND a.id NOT IN (
          SELECT id_asignatura FROM inscripciones_estudiantes
          WHERE id_estudiante = :id_estudiante_inscrito AND id_semestre = :id_semestre_inscrito
      )
      AND (
          (:es_semestre_actual_impar AND (a.semestre_recomendado % 2 != 0))
          OR
          (:es_semestre_actual_par AND (a.semestre_recomendado % 2 = 0))
      )
    ORDER BY c.nombre_curso ASC, a.semestre_recomendado ASC, a.nombre_asignatura ASC
");


    $stmtAsignaturasDisponibles->bindParam(':id_curso_estudiante', $idCursoEstudiante, PDO::PARAM_INT);
    $stmtAsignaturasDisponibles->bindParam(':id_estudiante_historial_aprobado', $idEstudiante, PDO::PARAM_INT);
    $stmtAsignaturasDisponibles->bindParam(':id_estudiante_inscrito', $idEstudiante, PDO::PARAM_INT);
    $stmtAsignaturasDisponibles->bindParam(':id_semestre_inscrito', $idSemestreActual, PDO::PARAM_INT);
    $stmtAsignaturasDisponibles->bindValue(':es_semestre_actual_impar', $esSemestreActualImpar ? 1 : 0, PDO::PARAM_INT);
    $stmtAsignaturasDisponibles->bindValue(':es_semestre_actual_par', !$esSemestreActualImpar ? 1 : 0, PDO::PARAM_INT);

    $stmtAsignaturasDisponibles->execute();
    $asignaturasDisponiblesCursoActual = $stmtAsignaturasDisponibles->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error de base de datos al obtener asignaturas disponibles: " . $e->getMessage());
    // Continuar con un array vacío
}

// --- 7. Obtener todos los semestres recomendados distintos para el filtro (dinámico) ---
$todosSemestresParaFiltro = [];
try {
    $stmtTodosSemestres = $pdo->prepare("SELECT DISTINCT semestre_recomendado FROM asignaturas WHERE id_curso = :id_curso ORDER BY semestre_recomendado ASC");
    $stmtTodosSemestres->bindParam(':id_curso', $idCursoEstudiante, PDO::PARAM_INT);
    $stmtTodosSemestres->execute();
    $todosSemestresParaFiltro = $stmtTodosSemestres->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error de base de datos al obtener semestres para el filtro: " . $e->getMessage());
    // Continuar con un array vacío
}

// --- 8. Generar HTML para la respuesta AJAX ---
?>
<div class="mb-3">
    <div class="row g-2">
        <div class="col-md-4">
            <select id="filtroCurso" class="form-select">
                <option value="">Filtrar por Curso</option>
                <?php
                // Obtener y mostrar los cursos asociados a las asignaturas del estudiante
                try {
                    $stmtCursos = $pdo->prepare("SELECT DISTINCT c.id, c.nombre_curso FROM cursos c JOIN asignaturas a ON c.id = a.id_curso WHERE a.id_curso = :id_curso_estudiante ORDER BY c.nombre_curso");
                    $stmtCursos->bindParam(':id_curso_estudiante', $idCursoEstudiante, PDO::PARAM_INT);
                    $stmtCursos->execute();
                    while ($curso = $stmtCursos->fetch(PDO::FETCH_ASSOC)) {
                        echo '<option value="' . htmlspecialchars($curso['nombre_curso']) . '">' . htmlspecialchars($curso['nombre_curso']) . '</option>';
                    }
                } catch (PDOException $e) {
                    error_log("Error de base de datos al obtener cursos para el filtro: " . $e->getMessage());
                    // Fallar elegantemente
                }
                ?>
            </select>
        </div>
        <div class="col-md-4">
            <select id="filtroSemestre" class="form-select">
                <option value="">Filtrar por Semestre</option>
                <?php
                // Rellenar dinámicamente con los semestres recomendados existentes para el curso del estudiante
                foreach ($todosSemestresParaFiltro as $numSem) {
                    echo '<option value="' . htmlspecialchars($numSem) . '">Semestre ' . htmlspecialchars($numSem) . '</option>';
                }
                ?>
            </select>
        </div>
        <div class="col-md-4">
            <input type="text" id="filtroBusqueda" class="form-control" placeholder="Buscar asignatura...">
        </div>
    </div>
</div>
<p><span id="contadorSeleccionadasModal"></span></p>

<hr>
<h4>Asignaturas Reprobadas (Obligatorias)</h4>
<?php if (!empty($asignaturasReprobadas)): ?>
    <p class="text-danger">Debes volver a cursar las siguientes asignaturas:</p>
    <div class="list-group mb-3">
        <?php foreach ($asignaturasReprobadas as $asigReprobada): ?>
            <label class="list-group-item d-flex justify-content-between align-items-center bg-warning-subtle reprobada-obligatoria">
                <input type="hidden" name="selected_asignaturas[]" value="<?php echo htmlspecialchars($asigReprobada['id']); ?>">
                <input class="form-check-input me-1" type="checkbox" value="<?php echo htmlspecialchars($asigReprobada['id']); ?>" checked disabled>
                <?php echo htmlspecialchars($asigReprobada['nombre_asignatura']); ?> (<?php echo htmlspecialchars($asigReprobada['creditos']); ?> Créditos)
                <span class="badge bg-danger rounded-pill">Reprobada</span>
            </label>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <p class="text-success">¡Enhorabuena! No tienes asignaturas reprobadas pendientes de cursar.</p>
<?php endif; ?>

<hr>
<h4>Asignaturas Disponibles para este Semestre</h4>
<div class="list-group" id="listaAsignaturasDisponibles">
    <?php if (!empty($asignaturasDisponiblesCursoActual)): ?>
        <?php foreach ($asignaturasDisponiblesCursoActual as $asig): ?>
            <?php
            $estaAprobada = in_array($asig['id'], $idsAsignaturasAprobadas);
            $estaInscritaEsteSemestre = in_array($asig['id'], $idsInscripcionesActuales);
            $esReprobadaObligatoria = in_array($asig['id'], $idsAsignaturasReprobadas);

            // Una asignatura está deshabilitada si ya está aprobada, inscrita este semestre, o es una asignatura reprobada obligatoria
            $estaDeshabilitada = $estaAprobada || $estaInscritaEsteSemestre || $esReprobadaObligatoria;
            $estadoChequeado = $esReprobadaObligatoria ? 'checked' : ''; // Solo marca si es una asignatura reprobada obligatoria

            $claseFondo = '';
            if ($estaAprobada) {
                $claseFondo = 'bg-success-subtle';
            } elseif ($estaInscritaEsteSemestre) {
                $claseFondo = 'bg-info-subtle';
            } elseif ($esReprobadaObligatoria) {
                $claseFondo = 'bg-warning-subtle'; // Esto anulará otras si también está reprobada
            }
            ?>
            <label class="list-group-item d-flex justify-content-between align-items-center elemento-asignatura <?php echo $claseFondo; ?>"
                   data-curso="<?php echo htmlspecialchars($asig['nombre_curso']); ?>"
                   data-semestre="<?php echo htmlspecialchars($asig['semestre_recomendado']); ?>"
                   data-nombre="<?php echo htmlspecialchars($asig['nombre_asignatura']); ?>">
                <input class="form-check-input me-1 <?php echo $esReprobadaObligatoria ? 'reprobada-obligatoria' : 'asig-normal'; ?>"
                       type="checkbox"
                       name="selected_asignaturas[]"
                       value="<?php echo htmlspecialchars($asig['id']); ?>"
                       <?php echo $estaDeshabilitada ? 'disabled' : ''; ?>
                       <?php echo $estadoChequeado; ?>>
                <?php echo htmlspecialchars($asig['nombre_asignatura']); ?> (<?php echo htmlspecialchars($asig['creditos']); ?> Créditos)
                <div class="ms-auto">
                    <?php if ($asig['id_prerequisito']): ?>
                        <span class="badge bg-secondary me-1" data-bs-toggle="tooltip" title="Prerrequisito: <?php echo htmlspecialchars($asig['nombre_prerrequisito']); ?>">PREREQ</span>
                    <?php endif; ?>
                    <?php if ($estaAprobada): ?>
                        <span class="badge bg-success">APROBADA</span>
                    <?php elseif ($estaInscritaEsteSemestre): ?>
                        <span class="badge bg-info">INSCRITA</span>
                    <?php elseif ($esReprobadaObligatoria): ?>
                        <span class="badge bg-danger">REPROBADA (OBLIGATORIA)</span>
                    <?php else: ?>
                        <span class="badge bg-primary">Semestre <?php echo htmlspecialchars($asig['semestre_recomendado']); ?></span>
                    <?php endif; ?>
                </div>
            </label>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="alert alert-info">No hay asignaturas disponibles para inscribirse en este momento que cumplan con los requisitos del semestre y los prerrequisitos.</p>
    <?php endif; ?>
</div>

<script>
    // Inicializar tooltips (requiere Bootstrap JS)
    var listaActivadoresTooltip = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var listaTooltips = listaActivadoresTooltip.map(function (elementoActivadorTooltip) {
        return new bootstrap.Tooltip(elementoActivadorTooltip)
    })

    // Lógica de filtro (esto normalmente estaría en un archivo JS separado o en línea para un componente pequeño)
    document.addEventListener('DOMContentLoaded', function() {
        const filtroCurso = document.getElementById('filtroCurso');
        const filtroSemestre = document.getElementById('filtroSemestre');
        const filtroBusqueda = document.getElementById('filtroBusqueda');
        const listaAsignaturasDisponibles = document.getElementById('listaAsignaturasDisponibles');
        const contadorSeleccionadasModal = document.getElementById('contadorSeleccionadasModal');

        function actualizarContadorSeleccionadas() {
            const contadorAsignaturasNormales = document.querySelectorAll('#listaAsignaturasDisponibles input.asig-normal:checked').length;
            const contadorAsignaturasReprobadasObligatorias = document.querySelectorAll('.reprobada-obligatoria input[type="checkbox"]:checked').length;
            const totalSeleccionadas = contadorAsignaturasNormales + contadorAsignaturasReprobadasObligatorias;
            contadorSeleccionadasModal.textContent = `Asignaturas seleccionadas: ${totalSeleccionadas}`;
        }

        function aplicarFiltros() {
            const cursoSeleccionado = filtroCurso.value.toLowerCase();
            const semestreSeleccionado = filtroSemestre.value;
            const terminoBusqueda = filtroBusqueda.value.toLowerCase();

            listaAsignaturasDisponibles.querySelectorAll('.elemento-asignatura').forEach(item => {
                const nombreCurso = item.dataset.curso.toLowerCase();
                const numeroSemestre = item.dataset.semestre;
                const nombreAsignatura = item.dataset.nombre.toLowerCase();

                const coincideCurso = cursoSeleccionado === '' || nombreCurso.includes(cursoSeleccionado);
                const coincideSemestre = semestreSeleccionado === '' || numeroSemestre === semestreSeleccionado;
                const coincideBusqueda = terminoBusqueda === '' || nombreAsignatura.includes(terminoBusqueda);

                if (coincideCurso && coincideSemestre && coincideBusqueda) {
                    item.style.display = 'flex'; // Mostrar el elemento
                } else {
                    item.style.display = 'none'; // Ocultar el elemento
                }
            });
            actualizarContadorSeleccionadas(); // Actualizar el contador después de filtrar
        }

        filtroCurso.addEventListener('change', aplicarFiltros);
        filtroSemestre.addEventListener('change', aplicarFiltros);
        filtroBusqueda.addEventListener('input', aplicarFiltros);

        // Listener de eventos para los cambios en los checkboxes para actualizar el contador
        listaAsignaturasDisponibles.addEventListener('change', function(event) {
            if (event.target.type === 'checkbox') {
                actualizarContadorSeleccionadas();
            }
        });

        // Actualización inicial cuando el contenido del modal se carga
        actualizarContadorSeleccionadas();
    });
</script>