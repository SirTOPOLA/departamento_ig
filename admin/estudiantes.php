<?php
require_once '../includes/functions.php';
// Asegura que solo un administrador pueda acceder a esta página.
check_login_and_role('Administrador');

require_once '../config/database.php'; // Conexión PDO


// --- El resto del script que genera la página HTML (para la solicitud GET) ---
$titulo_pagina = "Gestión de Estudiantes e Inscripciones";
include_once '../includes/header.php';

// Obtener mensajes flash para JavaScript
$mensajes_flash = get_flash_messages();

// Obtener el semestre académico actual para filtrar inscripciones
$semestre_actual = get_current_semester($pdo);
$id_semestre_actual = $semestre_actual['id'] ?? null;
// Necesitamos el id_anio_academico_actual para la subconsulta de 'curso_actual'
$id_anio_academico_para_mostrar = $semestre_actual['id_anio_academico'] ?? 0; // Usar 0 si no existe para que la subconsulta no encuentre nada
$nombre_semestre_actual = $semestre_actual ? htmlspecialchars($semestre_actual['numero_semestre'] . ' (' . $semestre_actual['nombre_anio'] . ')') : 'N/A';

$anios_academicos_disponibles = $pdo->query("SELECT id, nombre_anio FROM anios_academicos ORDER BY nombre_anio DESC")->fetchAll(PDO::FETCH_ASSOC);



// --- Obtener estudiantes con inscripciones pendientes (sin duplicados) ---
$estudiantes_con_inscripciones_pendientes = [];
if ($id_semestre_actual) {
    $stmt_estudiantes_pendientes = $pdo->prepare("
        SELECT DISTINCT
            u.id AS id_usuario,
            u.nombre_completo AS nombre_estudiante,
            e.codigo_registro,
            u.email,
            u.telefono
        FROM inscripciones_estudiantes ie
        JOIN estudiantes e ON ie.id_estudiante = e.id
        JOIN usuarios u ON e.id_usuario = u.id
        WHERE ie.confirmada = 0
        AND ie.id_semestre = :id_semestre_actual
        ORDER BY u.nombre_completo ASC
    ");
    // Manejo de errores para prepare y execute
    if ($stmt_estudiantes_pendientes === false) {
        $info_error = $pdo->errorInfo();
        error_log("PDO Prepare Error (estudiantes_pendientes): " . $info_error[2]);
        set_flash_message('danger', 'Error interno al preparar la consulta de inscripciones pendientes.');
    } else {
        if (!$stmt_estudiantes_pendientes->execute([':id_semestre_actual' => $id_semestre_actual])) {
            $info_error = $stmt_estudiantes_pendientes->errorInfo();
            error_log("PDO Execute Error (estudiantes_pendientes): " . $info_error[2]);
            set_flash_message('danger', 'Error interno al ejecutar la consulta de inscripciones pendientes.');
        }
        $estudiantes_con_inscripciones_pendientes = $stmt_estudiantes_pendientes->fetchAll(PDO::FETCH_ASSOC);
    }
}

// --- Obtener TODOS los estudiantes activos (CORRECCIÓN CLAVE PARA EL ERROR 'id_curso_inicio') ---
$todos_estudiantes_activos = [];
$stmt_todos_estudiantes = $pdo->prepare("
    SELECT
        u.id AS id_usuario,
        u.nombre_completo AS nombre_estudiante,
        e.id AS id_estudiante_db, -- Añadir el id de la tabla estudiantes
        e.codigo_registro,
        u.email,
        u.telefono,
        -- Subconsulta para obtener el curso actual del estudiante para el año académico actual
        (
            SELECT c.nombre_curso
            FROM curso_estudiante ce
            JOIN cursos c ON ce.id_curso = c.id
            WHERE ce.id_estudiante = e.id
            AND ce.id_anio = :id_anio_academico_para_mostrar -- Filtra por el año académico actual
            AND ce.estado = 'activo' -- Asume que 'activo' en curso_estudiante significa el curso actual
            ORDER BY ce.fecha_registro DESC -- En caso de múltiples activos para el mismo año, toma el más reciente
            LIMIT 1
        ) AS curso_actual
    FROM usuarios u
    JOIN estudiantes e ON u.id = e.id_usuario
    WHERE u.id_rol = (SELECT id FROM roles WHERE nombre_rol = 'Estudiante')
    AND u.estado = 'Activo'
    ORDER BY u.nombre_completo ASC
");

// Manejo de errores para prepare y execute
if ($stmt_todos_estudiantes === false) {
    $info_error = $pdo->errorInfo();
    error_log("PDO Prepare Error (todos_estudiantes): " . $info_error[2]);
    set_flash_message('danger', 'Error interno al preparar la consulta de todos los estudiantes.');
} else {
    // Asegurarse de tener el id_rol_estudiante antes de ejecutar (aunque no se usa directamente en esta consulta)
    $stmt_rol_estudiante = $pdo->prepare("SELECT id FROM roles WHERE nombre_rol = 'Estudiante'");
    $stmt_rol_estudiante->execute();
    $id_rol_estudiante = $stmt_rol_estudiante->fetchColumn();

    if (!$stmt_todos_estudiantes->execute([':id_anio_academico_para_mostrar' => $id_anio_academico_para_mostrar])) {
        $info_error = $stmt_todos_estudiantes->errorInfo();
        error_log("PDO Execute Error (todos_estudiantes): " . $info_error[2]);
        set_flash_message('danger', 'Error al ejecutar la consulta de todos los estudiantes: ' . $info_error[2]);
    }
    $todos_estudiantes_activos = $stmt_todos_estudiantes->fetchAll(PDO::FETCH_ASSOC);
}


// Datos para los SELECTs en el modal de gestión de historial
$asignaturas = $pdo->query("SELECT id, nombre_asignatura, semestre_recomendado FROM asignaturas ORDER BY nombre_asignatura")->fetchAll(PDO::FETCH_ASSOC);
$semestres_disponibles = $pdo->query("
    SELECT s.id, s.numero_semestre, aa.nombre_anio, s.id_anio_academico
    FROM semestres s
    JOIN anios_academicos aa ON s.id_anio_academico = aa.id
    ORDER BY aa.nombre_anio DESC, s.numero_semestre DESC
")->fetchAll(PDO::FETCH_ASSOC);
$estados_finales = ['APROBADO', 'REPROBADO', 'PENDIENTE', 'RETIRADO']; // Define los estados posibles
?>

<h1 class="mt-4">Gestión de Estudiantes e Inscripciones</h1>

<ul class="nav nav-tabs mb-4" id="studentTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="pending-enrollments-tab" data-bs-toggle="tab"
            data-bs-target="#pendingEnrollments" type="button" role="tab" aria-controls="pendingEnrollments"
            aria-selected="true">
            <i class="fas fa-clipboard-list me-2"></i> Inscripciones Pendientes
            <?php if (count($estudiantes_con_inscripciones_pendientes) > 0): ?>
                <span class="badge bg-danger ms-2"><?php echo count($estudiantes_con_inscripciones_pendientes); ?></span>
            <?php endif; ?>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="all-students-tab" data-bs-toggle="tab" data-bs-target="#allStudents" type="button"
            role="tab" aria-controls="allStudents" aria-selected="false">
            <i class="fas fa-users me-2"></i> Todos los Estudiantes Activos
        </button>
    </li>
</ul>

<div class="tab-content" id="studentTabsContent">
    <div class="tab-pane fade show active" id="pendingEnrollments" role="tabpanel"
        aria-labelledby="pending-enrollments-tab">
        <div class="d-flex justify-content-between mb-3 align-items-center">
            <div id="pendingEnrollmentButtons"
                style="display: <?php echo (count($estudiantes_con_inscripciones_pendientes) > 0 && $semestre_actual) ? 'block' : 'none'; ?>;">
                <button type="button" class="btn btn-success" id="confirmAllEnrollmentsBtn">
                    <i class="fas fa-check-double me-2"></i> Confirmar Todas las Inscripciones Pendientes
                </button>
            </div>
            <div class="col-md-4">
                <input type="search" class="form-control" id="searchInputPending"
                    placeholder="Buscar estudiante en pendientes...">
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Estudiantes con Solicitudes de Inscripción Pendientes</h5>
            </div>
            <div class="card-body">
                <?php if (count($estudiantes_con_inscripciones_pendientes) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped" id="studentsTablePending">
                            <thead>
                                <tr>
                                    <th>Estudiante</th>
                                    <th>Cód. Registro</th>
                                    <th>Email</th>
                                    <th>Teléfono</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($estudiantes_con_inscripciones_pendientes as $estudiante): ?>
                                    <tr data-id_usuario="<?php echo htmlspecialchars($estudiante['id_usuario']); ?>"
                                        data-nombre_estudiante="<?php echo htmlspecialchars($estudiante['nombre_estudiante']); ?>">
                                        <td><?php echo htmlspecialchars($estudiante['nombre_estudiante']); ?></td>
                                        <td><?php echo htmlspecialchars($estudiante['codigo_registro']); ?></td>
                                        <td><?php echo htmlspecialchars($estudiante['email'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($estudiante['telefono'] ?? 'N/A'); ?></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-info btn-sm view-enrollments-btn"
                                                data-bs-toggle="modal" data-bs-target="#enrollmentDetailModal"
                                                title="Ver y Gestionar Inscripciones">
                                                <i class="fas fa-eye me-1"></i> Gestionar Inscripciones
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <nav>
                        <ul class="pagination justify-content-center" id="paginationPending">
                        </ul>
                    </nav>
                <?php else: ?>
                    <div class="alert alert-success mb-0">
                        <i class="fas fa-check-circle me-1"></i> No hay estudiantes con solicitudes de inscripción
                        pendientes para el semestre actual.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="allStudents" role="tabpanel" aria-labelledby="all-students-tab">
        <div class="d-flex justify-content-end mb-3 align-items-center">
            <div class="col-md-4">
                <input type="search" class="form-control" id="searchInputAll"
                    placeholder="Buscar en todos los estudiantes...">
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">Listado de Todos los Estudiantes Activos</h5>
            </div>
            <div class="card-body">
                <?php if (count($todos_estudiantes_activos) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped" id="studentsTableAll">
                            <thead>
                                <tr>
                                    <th>Estudiante</th>
                                    <th>Cód. Registro</th>
                                    <th>Email</th>
                                    <th>Teléfono</th>
                                    <th>Curso Actual</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($todos_estudiantes_activos as $estudiante): ?>
                                    <tr data-id_usuario="<?php echo htmlspecialchars($estudiante['id_usuario']); ?>"
                                        data-nombre_estudiante="<?php echo htmlspecialchars($estudiante['nombre_estudiante']); ?>"
                                        data-id_estudiante_db="<?php echo htmlspecialchars($estudiante['id_estudiante_db']); ?>">
                                        <td><?php echo htmlspecialchars($estudiante['nombre_estudiante']); ?></td>
                                        <td><?php echo htmlspecialchars($estudiante['codigo_registro']); ?></td>
                                        <td><?php echo htmlspecialchars($estudiante['email'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($estudiante['telefono'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($estudiante['curso_actual'] ?? 'N/A'); ?></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-primary btn-sm view-history-btn me-2"
                                                data-bs-toggle="modal" data-bs-target="#academicHistoryModal"
                                                title="Ver Historial Académico">
                                                <i class="fas fa-history me-1"></i> Ver Historial
                                            </button>
                                            <button type="button" class="btn btn-warning btn-sm manage-history-btn"
                                                data-bs-toggle="modal" data-bs-target="#manageAcademicHistoryModal"
                                                title="Gestionar Historial Académico">
                                                <i class="fas fa-edit me-1"></i> Gestionar Historial
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <nav>
                        <ul class="pagination justify-content-center" id="paginationAll">
                        </ul>
                    </nav>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-1"></i> No hay estudiantes activos registrados.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="enrollmentDetailModal" tabindex="-1" aria-labelledby="enrollmentDetailModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="enrollmentDetailModalLabel">Inscripciones Pendientes de: <span
                        id="modalStudentName"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modalStudentUserId">
                <div id="enrollmentsList">
                    <p class="text-center text-muted" id="loadingEnrollments">Cargando inscripciones...</p>
                </div>
                <div class="alert alert-info mt-3" id="noPendingEnrollmentsMessage" style="display: none;">
                    Este estudiante no tiene asignaturas pendientes de confirmación para el semestre actual.
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i
                        class="fas fa-times me-2"></i> Cerrar</button>
                <form action="estudiantes.php" method="POST" class="d-inline-block"
                    id="confirmAllStudentEnrollmentsForm">
                    <input type="hidden" name="action" value="confirm_student_enrollments">
                    <input type="hidden" name="id_estudiante" id="confirmAllStudentId">
                    <button type="submit" class="btn btn-success" id="confirmAllStudentEnrollmentsBtn"
                        style="display: none;"
                        onclick="return confirm('¿Estás seguro de que quieres CONFIRMAR TODAS las asignaturas pendientes para este estudiante?');">
                        <i class="fas fa-check-double me-2"></i> Confirmar Todas las Asignaturas
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="academicHistoryModal" tabindex="-1" aria-labelledby="academicHistoryModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title" id="academicHistoryModalLabel">Historial Académico de: <span
                        id="modalHistoryStudentName"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modalHistoryStudentUserId">
                <div id="academicHistoryContent">
                    <p class="text-center text-muted" id="loadingHistory">Cargando historial...</p>
                    <div class="table-responsive" style="display: none;" id="historyTableContainer">
                        <table class="table table-bordered table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Asignatura</th>
                                    <th>Código</th>
                                    <th>Semestre</th>
                                    <th>Año</th>
                                    <th>Nota Final</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody id="academicHistoryTableBody">
                            </tbody>
                        </table>
                    </div>
                    <div class="alert alert-info mt-3" id="noAcademicHistoryMessage" style="display: none;">
                        Este estudiante no tiene historial académico registrado.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i
                        class="fas fa-times me-2"></i> Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="manageAcademicHistoryModal" tabindex="-1" aria-labelledby="manageAcademicHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <form id="academicHistoryForm" action="../api/guardar_historial_anterior.php" method="POST">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="manageAcademicHistoryModalLabel">Gestionar Historial Académico de: <span id="manageHistoryStudentName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="guardar_historial_multiple">
                    <input type="hidden" name="id_estudiante_db" id="manageHistoryStudentDbId">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="historyAnioSelect" class="form-label">Año Académico:</label>
                            <select class="form-select" id="historyAnioSelect" name="id_anio_academico" required>
                                <option value="">Seleccione un año</option>
                                <?php foreach ($anios_academicos_disponibles as $anio): ?>
                                    <option value="<?php echo htmlspecialchars($anio['id']); ?>">
                                        <?php echo htmlspecialchars($anio['nombre_anio']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="historySemestreSelect" class="form-label">Semestre:</label>
                            <select class="form-select" id="historySemestreSelect" name="id_semestre" required disabled>
                                <option value="">Seleccione un semestre</option>
                            </select>
                        </div>
                    </div>

                    <hr>
                    <h5>Asignaturas del Semestre Seleccionado:</h5>
                    <div id="asignaturasContainer" class="p-3 border rounded bg-light" style="min-height: 150px;">
                        <p class="text-center text-muted" id="initialPrompt">Por favor, seleccione un Año y un Semestre para cargar las asignaturas.</p>
                        <p class="text-center text-danger" id="noAsignaturasMessage" style="display: none;">No se encontraron asignaturas para el semestre/curso seleccionado.</p>
                    </div>

                    <div class="alert alert-info mt-3" id="editDeleteHistoryMessage" style="display: none;">
                        Nota: Las asignaturas ya registradas para este estudiante en este semestre mostrarán sus datos y pueden ser actualizadas.
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i> Cerrar
                    </button>
                    <button type="submit" class="btn btn-primary" id="saveHistoryEntryBtn">
                        <i class="fas fa-save me-2"></i> Guardar Historial del Semestre
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const elementosPorPaginaPendientes = 10;
        const elementosPorPaginaTodos = 10;

        // --- Funciones de Paginación y Búsqueda ---
        function paginarTabla(idTabla, idPaginacion, elementosPorPagina) {
            const tabla = document.getElementById(idTabla);
            if (!tabla) return; // Salir si la tabla no existe

            const cuerpoTabla = tabla.querySelector('tbody');
            const filas = Array.from(cuerpoTabla.rows);
            const totalPaginas = Math.ceil(filas.length / elementosPorPagina);
            const contenedorPaginacion = document.getElementById(idPaginacion);

            if (!contenedorPaginacion) return; // Salir si el contenedor de paginación no existe

            function mostrarPagina(pagina) {
                filas.forEach((fila, indice) => {
                    fila.style.display = (indice >= (pagina - 1) * elementosPorPagina && indice < pagina * elementosPorPagina) ? '' : 'none';
                });
                actualizarBotonesPaginacion(pagina);
            }

            function actualizarBotonesPaginacion(paginaActual) {
                contenedorPaginacion.innerHTML = '';
                for (let i = 1; i <= totalPaginas; i++) {
                    const li = document.createElement('li');
                    li.classList.add('page-item');
                    if (i === paginaActual) {
                        li.classList.add('active');
                    }
                    const enlace = document.createElement('a');
                    enlace.classList.add('page-link');
                    enlace.href = '#';
                    enlace.textContent = i;
                    enlace.addEventListener('click', (e) => {
                        e.preventDefault();
                        mostrarPagina(i);
                    });
                    li.appendChild(enlace);
                    contenedorPaginacion.appendChild(li);
                }
            }
            if (filas.length > 0) {
                mostrarPagina(1); // Muestra la primera página por defecto
            } else {
                contenedorPaginacion.innerHTML = ''; // Si no hay filas, oculta la paginación
            }
        }

        function configurarBusqueda(idInput, idTabla, idPagina, elementosPorPagina) {
            const searchInput = document.getElementById(idInput); // Captura el elemento
            if (searchInput) { // <-- ¡Verifica que el input de búsqueda existe!
                searchInput.addEventListener('keyup', function() {
                    const textoBusqueda = this.value.toLowerCase();
                    const tabla = document.getElementById(idTabla);
                    if (!tabla) return;

                    const cuerpoTabla = tabla.querySelector('tbody');
                    const filas = Array.from(cuerpoTabla.rows);

                    filas.forEach(fila => {
                        const textoFila = fila.textContent.toLowerCase();
                        fila.style.display = textoFila.includes(textoBusqueda) ? '' : 'none';
                    });

                    if (textoBusqueda === '') {
                        paginarTabla(idTabla, idPagina, elementosPorPagina);
                    } else {
                        const paginacionElement = document.getElementById(idPagina);
                        if (paginacionElement) { // <-- ¡Verifica que el contenedor de paginación existe!
                            paginacionElement.innerHTML = '';
                        }
                    }
                });
            }
        }

        // Inicializar paginación y búsqueda al cargar la página
        paginarTabla('studentsTablePending', 'paginationPending', elementosPorPaginaPendientes);
        paginarTabla('studentsTableAll', 'paginationAll', elementosPorPaginaTodos);
        configurarBusqueda('searchInputPending', 'studentsTablePending', 'paginationPending', elementosPorPaginaPendientes);
        configurarBusqueda('searchInputAll', 'studentsTableAll', 'paginationAll', elementosPorPaginaTodos);

        // Manejo de mensajes flash
        const mensajesFlash = <?php echo json_encode($mensajes_flash); ?>;
        if (mensajesFlash && mensajesFlash.length > 0) {
            mensajesFlash.forEach(mensaje => {
                const divAlerta = `<div class="alert alert-${mensaje.type} alert-dismissible fade show mt-3" role="alert">
                                    ${mensaje.message}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                  </div>`;
                document.getElementById('mainContent')?.insertAdjacentHTML('afterbegin', divAlerta);
            });
        }

        // --- Lógica para Inscripciones Pendientes ---

        // Manejo del botón "Confirmar Todas las Inscripciones Pendientes"
        const confirmAllEnrollmentsBtn = document.getElementById('confirmAllEnrollmentsBtn');
        if (confirmAllEnrollmentsBtn) { // <-- ¡Verificación clave añadida!
            confirmAllEnrollmentsBtn.addEventListener('click', function() {
                if (confirm('¿Estás seguro de que quieres CONFIRMAR TODAS las inscripciones pendientes en el sistema para el semestre actual?')) {
                    const formulario = document.createElement('form');
                    formulario.action = 'estudiantes.php';
                    formulario.method = 'POST';
                    formulario.style.display = 'none';
                    formulario.innerHTML = '<input type="hidden" name="action" value="confirm_all_enrollments">';
                    document.body.appendChild(formulario);
                    formulario.submit();
                }
            });
        }

        // Abrir Modal de Inscripciones Pendientes por Estudiante
        document.querySelectorAll('.view-enrollments-btn').forEach(boton => {
            boton.addEventListener('click', async function() {
                const idUsuario = this.closest('tr').dataset.id_usuario;
                const nombreEstudiante = this.closest('tr').dataset.nombre_estudiante;

                const modalStudentName = document.getElementById('modalStudentName');
                const modalStudentUserId = document.getElementById('modalStudentUserId');
                const confirmAllStudentId = document.getElementById('confirmAllStudentId');

                if (modalStudentName) modalStudentName.textContent = nombreEstudiante;
                if (modalStudentUserId) modalStudentUserId.value = idUsuario;
                if (confirmAllStudentId) confirmAllStudentId.value = idUsuario;


                const listaInscripciones = document.getElementById('enrollmentsList');
                const cargandoInscripciones = document.getElementById('loadingEnrollments');
                const mensajeSinInscripciones = document.getElementById('noPendingEnrollmentsMessage');
                const botonConfirmarTodasEstudiante = document.getElementById('confirmAllStudentEnrollmentsBtn');

                if (listaInscripciones) listaInscripciones.innerHTML = '';
                if (cargandoInscripciones) cargandoInscripciones.style.display = 'block';
                if (mensajeSinInscripciones) mensajeSinInscripciones.style.display = 'none';
                if (botonConfirmarTodasEstudiante) botonConfirmarTodasEstudiante.style.display = 'none';

                try {
                    const respuesta = await fetch(`../api/obtener_historial_academico_admin.php?accion=obtener_inscripciones_pendientes&id_usuario=${idUsuario}`);
                    const datos = await respuesta.json();

                    if (cargandoInscripciones) cargandoInscripciones.style.display = 'none';

                    if (datos.success && datos.inscripciones.length > 0) {
                        datos.inscripciones.forEach(inscripcion => {
                            const htmlInscripcion = `
                                <div class="card mb-2 shadow-sm">
                                    <div class="card-body d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-0">${inscripcion.nombre_asignatura} (${inscripcion.codigo_asignatura})</h6>
                                            <small class="text-muted">Semestre recomendado: ${inscripcion.semestre_recomendado}º</small>
                                            <br>
                                            <small class="text-muted">Curso de la asignatura: ${inscripcion.nombre_curso_asignatura}</small>
                                            <br>
                                            <small class="text-info">Curso actual del estudiante (este año): ${inscripcion.curso_actual_estudiante || 'No matriculado este año'}</small>
                                        </div>
                                        <div>
                                            <form action="estudiantes.php" method="POST" class="d-inline-block">
                                                <input type="hidden" name="action" value="confirm_single_enrollment">
                                                <input type="hidden" name="id_inscripcion" value="${inscripcion.id_inscripcion}">
                                                <button type="submit" class="btn btn-success btn-sm me-2"
                                                        onclick="return confirm('¿Confirmar inscripción para ${inscripcion.nombre_asignatura}?');">
                                                    <i class="fas fa-check"></i> Confirmar
                                                </button>
                                            </form>
                                            <form action="estudiantes.php" method="POST" class="d-inline-block">
                                                <input type="hidden" name="action" value="reject_single_enrollment">
                                                <input type="hidden" name="id_inscripcion" value="${inscripcion.id_inscripcion}">
                                                <button type="submit" class="btn btn-danger btn-sm"
                                                        onclick="return confirm('¿Rechazar inscripción para ${inscripcion.nombre_asignatura}?');">
                                                    <i class="fas fa-times"></i> Rechazar
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            `;
                            if (listaInscripciones) listaInscripciones.insertAdjacentHTML('beforeend', htmlInscripcion);
                        });
                        if (botonConfirmarTodasEstudiante) botonConfirmarTodasEstudiante.style.display = 'block';
                    } else {
                        if (mensajeSinInscripciones) mensajeSinInscripciones.style.display = 'block';
                    }
                } catch (error) {
                    console.error('Error al obtener inscripciones pendientes:', error);
                    if (cargandoInscripciones) cargandoInscripciones.style.display = 'none';
                    if (listaInscripciones) listaInscripciones.innerHTML = `<div class="alert alert-danger">Error al cargar las inscripciones. Intente de nuevo.</div>`;
                }
            });
        });

        // --- Lógica para Historial Académico ---

        // Abrir Modal de Historial Académico (Solo Lectura)
        document.querySelectorAll('.view-history-btn').forEach(boton => {
            boton.addEventListener('click', async function() {
                const idUsuario = this.closest('tr').dataset.id_usuario;
                const nombreEstudiante = this.closest('tr').dataset.nombre_estudiante;

                const modalHistoryStudentName = document.getElementById('modalHistoryStudentName');
                const modalHistoryStudentUserId = document.getElementById('modalHistoryStudentUserId');
                if (modalHistoryStudentName) modalHistoryStudentName.textContent = nombreEstudiante;
                if (modalHistoryStudentUserId) modalHistoryStudentUserId.value = idUsuario;


                const historialContent = document.getElementById('academicHistoryContent');
                const cargandoHistorial = document.getElementById('loadingHistory');
                const tablaHistorial = document.getElementById('historyTableContainer');
                const cuerpoTablaHistorial = document.getElementById('academicHistoryTableBody');
                const noHistorialMessage = document.getElementById('noAcademicHistoryMessage');

                if (historialContent) {
                    historialContent.innerHTML = ''; // Limpiar el contenido anterior
                    if (cargandoHistorial) historialContent.appendChild(cargandoHistorial); // Restaurar el mensaje de carga
                }
                if (cargandoHistorial) cargandoHistorial.style.display = 'block';
                if (tablaHistorial) tablaHistorial.style.display = 'none';
                if (noHistorialMessage) noHistorialMessage.style.display = 'none';
                if (cuerpoTablaHistorial) cuerpoTablaHistorial.innerHTML = ''; // Limpiar tbody

                try {
                    const respuesta = await fetch(`../api/obtener_historial_academico_admin.php?accion=obtener_historial&id_usuario=${idUsuario}`);
                    const datos = await respuesta.json();

                    if (cargandoHistorial) cargandoHistorial.style.display = 'none';

                    if (datos.success && datos.historial.length > 0) {
                        if (tablaHistorial) tablaHistorial.style.display = 'block';
                        datos.historial.forEach(item => {
                            const fila = `
                                <tr>
                                    <td>${item.nombre_asignatura}</td>
                                    <td>${item.codigo_asignatura}</td>
                                    <td>${item.numero_semestre} (${item.nombre_anio})</td>
                                    <td>${item.nombre_curso}</td>
                                    <td>${item.nota_final !== null ? item.nota_final : 'N/A'}</td>
                                    <td>${item.estado}</td>
                                </tr>
                            `;
                            if (cuerpoTablaHistorial) cuerpoTablaHistorial.insertAdjacentHTML('beforeend', fila);
                        });
                    } else {
                        if (noHistorialMessage) noHistorialMessage.style.display = 'block';
                    }
                } catch (error) {
                    console.error('Error al obtener historial académico:', error);
                    if (cargandoHistorial) cargandoHistorial.style.display = 'none';
                    if (historialContent) historialContent.innerHTML = `<div class="alert alert-danger">Error al cargar el historial. Intente de nuevo.</div>`;
                }
            });
        });

        // Abrir Modal de Gestión de Historial Académico
        document.querySelectorAll('.manage-history-btn').forEach(boton => {
            boton.addEventListener('click', function() {
                const idEstudianteDb = this.closest('tr').dataset.id_estudiante_db;
                const nombreEstudiante = this.closest('tr').dataset.nombre_estudiante;

                const manageHistoryStudentName = document.getElementById('manageHistoryStudentName');
                if (manageHistoryStudentName) manageHistoryStudentName.textContent = nombreEstudiante;


                const manageHistoryStudentDbIdInput = document.getElementById('manageHistoryStudentDbId');
                if (manageHistoryStudentDbIdInput) { // <-- ¡Aquí está la verificación clave para este error!
                    manageHistoryStudentDbIdInput.value = idEstudianteDb;
                } else {
                    console.error("Error: El elemento con ID 'manageHistoryStudentDbId' no fue encontrado en el DOM.");
                    // Opcional: podrías mostrar un mensaje al usuario aquí
                }

                // Restablecer los select y el contenedor de asignaturas
                const historyAnioSelect = document.getElementById('historyAnioSelect');
                const historySemestreSelect = document.getElementById('historySemestreSelect');
                const asignaturasContainer = document.getElementById('asignaturasContainer');
                const initialPrompt = document.getElementById('initialPrompt');
                const noAsignaturasMessage = document.getElementById('noAsignaturasMessage');
                const editDeleteHistoryMessage = document.getElementById('editDeleteHistoryMessage');

                if (historyAnioSelect) historyAnioSelect.value = '';
                if (historySemestreSelect) {
                    historySemestreSelect.innerHTML = '<option value="">Seleccione un semestre</option>';
                    historySemestreSelect.disabled = true;
                }
                if (asignaturasContainer) asignaturasContainer.innerHTML = '';
                if (initialPrompt) initialPrompt.style.display = 'block';
                if (asignaturasContainer && initialPrompt) asignaturasContainer.appendChild(initialPrompt); // Asegura que el prompt esté dentro
                if (noAsignaturasMessage) noAsignaturasMessage.style.display = 'none';
                if (editDeleteHistoryMessage) editDeleteHistoryMessage.style.display = 'none';
            });
        });

        // Cargar semestres cuando se selecciona un año en el modal de gestión de historial
        const historyAnioSelect = document.getElementById('historyAnioSelect');
        const historySemestreSelect = document.getElementById('historySemestreSelect');
        const asignaturasContainer = document.getElementById('asignaturasContainer');
        const initialPrompt = document.getElementById('initialPrompt');
        const noAsignaturasMessage = document.getElementById('noAsignaturasMessage');
        const editDeleteHistoryMessage = document.getElementById('editDeleteHistoryMessage');
        const todosSemestresDisponibles = <?php echo json_encode($semestres_disponibles); ?>;
        const todosAsignaturas = <?php echo json_encode($asignaturas); ?>; // Aunque no se usa directamente aquí, es buena práctica tenerlo
        const estadosFinales = <?php echo json_encode($estados_finales); ?>;

        if (historyAnioSelect && historySemestreSelect && asignaturasContainer) {
            historyAnioSelect.addEventListener('change', function() {
                const idAnioSeleccionado = this.value;
                historySemestreSelect.innerHTML = '<option value="">Seleccione un semestre</option>';
                historySemestreSelect.disabled = true;
                asignaturasContainer.innerHTML = '';
                if (initialPrompt) initialPrompt.style.display = 'block';
                if (noAsignaturasMessage) noAsignaturasMessage.style.display = 'none';
                if (editDeleteHistoryMessage) editDeleteHistoryMessage.style.display = 'none';

                if (idAnioSeleccionado) {
                    const semestresFiltrados = todosSemestresDisponibles.filter(semestre =>
                        semestre.id_anio_academico == idAnioSeleccionado // Usa == para comparar string con number si es necesario
                    );
                    semestresFiltrados.forEach(semestre => {
                        const option = document.createElement('option');
                        option.value = semestre.id;
                        option.textContent = `${semestre.numero_semestre} (${semestre.nombre_anio})`;
                        historySemestreSelect.appendChild(option);
                    });
                    historySemestreSelect.disabled = false;
                }
            });

            historySemestreSelect.addEventListener('change', async function() {
                const idSemestreSeleccionado = this.value;
                const manageHistoryStudentDbIdInput = document.getElementById('manageHistoryStudentDbId');
                const idEstudianteDb = manageHistoryStudentDbIdInput ? manageHistoryStudentDbIdInput.value : null;

                asignaturasContainer.innerHTML = '';
                if (initialPrompt) initialPrompt.style.display = 'none';
                if (noAsignaturasMessage) noAsignaturasMessage.style.display = 'none';
                if (editDeleteHistoryMessage) editDeleteHistoryMessage.style.display = 'none';

                if (idSemestreSeleccionado && idEstudianteDb) {
                    try {
                        const respuesta = await fetch(`../api/obtener_historial_academico_admin.php?accion=obtener_asignaturas_semestre_y_historial_existente&id_semestre=${idSemestreSeleccionado}&id_estudiante_db=${idEstudianteDb}`);
                        const datos = await respuesta.json();

                        if (datos.success && datos.asignaturas_semestre && datos.asignaturas_semestre.length > 0) {
                            if (editDeleteHistoryMessage) editDeleteHistoryMessage.style.display = 'block';

                            datos.asignaturas_semestre.forEach(asignatura => {
                                const historialExistente = datos.historial_existente.find(h => h.id_asignatura == asignatura.id);
                                const notaValue = historialExistente ? (historialExistente.nota_final !== null ? historialExistente.nota_final : '') : '';
                                const estadoValue = historialExistente ? historialExistente.estado : '';
                                const idHistorialExistente = historialExistente ? historialExistente.id_historial : '';

                                const estadoOptions = estadosFinales.map(estado =>
                                    `<option value="${estado}" ${estado === estadoValue ? 'selected' : ''}>${estado}</option>`
                                ).join('');

                                const asignaturaHtml = `
                                    <div class="row mb-3 align-items-center border-bottom pb-2">
                                        <input type="hidden" name="asignaturas[${asignatura.id}][id_asignatura]" value="${asignatura.id}">
                                        <input type="hidden" name="asignaturas[${asignatura.id}][id_historial_existente]" value="${idHistorialExistente}">
                                        <div class="col-md-4">
                                            <label class="form-label mb-0"><strong>${asignatura.nombre_asignatura}</strong></label>
                                            <small class="text-muted d-block">${asignatura.codigo_asignatura}</small>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="nota_final_${asignatura.id}" class="form-label">Nota Final:</label>
                                            <input type="number" step="0.01" min="0" max="100" class="form-control"
                                                   id="nota_final_${asignatura.id}" name="asignaturas[${asignatura.id}][nota_final]"
                                                   value="${notaValue}">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="estado_${asignatura.id}" class="form-label">Estado:</label>
                                            <select class="form-select" id="estado_${asignatura.id}"
                                                    name="asignaturas[${asignatura.id}][estado]" required>
                                                <option value="">Seleccione</option>
                                                ${estadoOptions}
                                            </select>
                                        </div>
                                        <div class="col-md-2 text-end">
                                            ${idHistorialExistente ? `
                                                <button type="button" class="btn btn-danger btn-sm delete-history-entry-btn"
                                                        data-id-historial="${idHistorialExistente}"
                                                        onclick="return confirm('¿Está seguro de que desea eliminar este registro de historial?');">
                                                    <i class="fas fa-trash-alt"></i> Eliminar
                                                </button>
                                            ` : ''}
                                        </div>
                                    </div>
                                `;
                                asignaturasContainer.insertAdjacentHTML('beforeend', asignaturaHtml);
                            });

                            // Event listener para botones de eliminar (delegación de eventos si es posible, o directo si se añaden pocos)
                            asignaturasContainer.querySelectorAll('.delete-history-entry-btn').forEach(btn => {
                                btn.addEventListener('click', async function() {
                                    const idHistorial = this.dataset.idHistorial;
                                    if (confirm('¿Estás seguro de que deseas eliminar este registro del historial?')) {
                                        try {
                                            const formEliminar = document.createElement('form');
                                            formEliminar.action = '../api/guardar_historial_anterior.php';
                                            formEliminar.method = 'POST';
                                            formEliminar.style.display = 'none';
                                            formEliminar.innerHTML = `
                                                <input type="hidden" name="action" value="eliminar_historial">
                                                <input type="hidden" name="id_historial" value="${idHistorial}">
                                            `;
                                            document.body.appendChild(formEliminar);
                                            formEliminar.submit();
                                        } catch (error) {
                                            console.error('Error al eliminar historial:', error);
                                            alert('Hubo un error al intentar eliminar el registro.');
                                        }
                                    }
                                });
                            });

                        } else {
                            if (noAsignaturasMessage) noAsignaturasMessage.style.display = 'block';
                        }
                    } catch (error) {
                        console.error('Error al cargar asignaturas del semestre o historial existente:', error);
                        asignaturasContainer.innerHTML = `<div class="alert alert-danger">Error al cargar las asignaturas. Intente de nuevo.</div>`;
                    }
                } else {
                    if (initialPrompt) initialPrompt.style.display = 'block';
                }
            });
        }

        // Manejar el submit del formulario de historial académico
        const academicHistoryForm = document.getElementById('academicHistoryForm');
        if (academicHistoryForm) {
            academicHistoryForm.addEventListener('submit', async function(e) {
                e.preventDefault(); // Prevenir el envío normal del formulario

                const formData = new FormData(this);
                const actionInput = formData.get('action'); // Obtener el valor del campo 'action'

                // Verificar si la acción es "eliminar_historial", en cuyo caso no se usará este listener
                // (ya que los botones de eliminar individuales manejan su propio submit)
                if (actionInput === 'eliminar_historial') {
                    return;
                }

                try {
                    const response = await fetch(this.action, { // Usar this.action para la URL del formulario
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        alert(result.message);
                        // Recargar la página o el modal para reflejar los cambios
                        window.location.reload(); // Opción simple: recargar toda la página
                    } else {
                        alert('Error: ' + result.message);
                    }
                } catch (error) {
                    console.error('Error al guardar el historial:', error);
                    alert('Hubo un error al procesar la solicitud.');
                }
            });
        }

    }); // Fin DOMContentLoaded
</script>