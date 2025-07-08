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
    SELECT s.id, s.numero_semestre, aa.nombre_anio
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

<div class="modal fade" id="manageAcademicHistoryModal" tabindex="-1" aria-labelledby="manageAcademicHistoryModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="academicHistoryForm" action="../api/guardar_historial_anterior.php" method="POST">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="manageAcademicHistoryModalLabel">Gestionar Historial Académico de: <span
                                id="manageHistoryStudentName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="manage_academic_history">
                    <input type="hidden" name="id_usuario_estudiante" id="manageHistoryStudentUserId">
                    <input type="hidden" name="operacion_historial" id="operacionHistorial" value="add">
                    <input type="hidden" name="id_historial_entry" id="idHistorialEntry">
                    <div class="mb-3">
                        <label for="asignaturaSelect" class="form-label">Asignatura:</label>
                        <select class="form-select" id="asignaturaSelect" name="id_asignatura" required>
                            <option value="">Seleccione una asignatura</option>
                            <?php foreach ($asignaturas as $asignatura): ?>
                                <option value="<?php echo htmlspecialchars($asignatura['id']); ?>">
                                    <?php echo htmlspecialchars($asignatura['nombre_asignatura'] . ' ( ' . $asignatura['semestre_recomendado'] . 'º semestre)'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="semestreSelect" class="form-label">Semestre / Año Académico:</label>
                        <select class="form-select" id="semestreSelect" name="id_semestre" required>
                            <option value="">Seleccione un semestre</option>
                            <?php foreach ($semestres_disponibles as $semestre): ?>
                                <option value="<?php echo htmlspecialchars($semestre['id']); ?>">
                                    <?php echo htmlspecialchars($semestre['numero_semestre'] . ' (' . $semestre['nombre_anio'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="notaFinal" class="form-label">Nota Final:</label>
                        <input type="number" class="form-control" id="notaFinal" name="nota_final" step="0.01" min="0" max="10" required>
                    </div>

                    <div class="mb-3">
                        <label for="estadoFinal" class="form-label">Estado:</label>
                        <select class="form-select" id="estadoFinal" name="estado_final" required>
                            <option value="">Seleccione un estado</option>
                            <?php foreach ($estados_finales as $estado): ?>
                                <option value="<?php echo htmlspecialchars($estado); ?>">
                                    <?php echo htmlspecialchars($estado); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="alert alert-info mt-3" id="editDeleteHistoryMessage" style="display: none;">
                        Selecciona una entrada del historial a continuación para editarla o eliminarla.
                    </div>

                    <h5>Historial Actual del Estudiante (para Editar/Eliminar)</h5>
                    <div class="table-responsive" id="currentHistoryTableContainer" style="display: none;">
                        <table class="table table-bordered table-striped table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>Asignatura</th>
                                    <th>Semestre</th>
                                    <th>Nota</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="manageAcademicHistoryTableBody">
                                </tbody>
                        </table>
                    </div>
                    <div class="alert alert-warning mt-3" id="noCurrentHistoryToManageMessage" style="display: none;">
                        Este estudiante no tiene historial académico registrado para gestionar.
                    </div>
                    <p class="text-center text-muted" id="loadingHistoryManage" style="display: none;">Cargando historial para gestión...</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i
                                class="fas fa-times me-2"></i> Cerrar</button>
                    <button type="submit" class="btn btn-primary" id="saveHistoryEntryBtn"><i
                                class="fas fa-save me-2"></i> Guardar Historial</button>
                    <button type="button" class="btn btn-danger" id="deleteHistoryEntryBtn" style="display: none;"><i
                                class="fas fa-trash me-2"></i> Eliminar</button>
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
            if (!tabla) return;

            const cuerpoTabla = tabla.querySelector('tbody');
            const filas = Array.from(cuerpoTabla.rows);
            const totalPaginas = Math.ceil(filas.length / elementosPorPagina);
            const contenedorPaginacion = document.getElementById(idPaginacion);

            if (!contenedorPaginacion) return;

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
            document.getElementById(idInput)?.addEventListener('keyup', function() {
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
                    document.getElementById(idPagina).innerHTML = '';
                }
            });
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
        document.getElementById('confirmAllEnrollmentsBtn')?.addEventListener('click', function() {
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

        // Abrir Modal de Inscripciones Pendientes por Estudiante
        document.querySelectorAll('.view-enrollments-btn').forEach(boton => {
            boton.addEventListener('click', async function() {
                const idUsuario = this.closest('tr').dataset.id_usuario;
                const nombreEstudiante = this.closest('tr').dataset.nombre_estudiante;

                document.getElementById('modalStudentName').textContent = nombreEstudiante;
                document.getElementById('modalStudentUserId').value = idUsuario;
                document.getElementById('confirmAllStudentId').value = idUsuario;

                const listaInscripciones = document.getElementById('enrollmentsList');
                const cargandoInscripciones = document.getElementById('loadingEnrollments');
                const mensajeSinInscripciones = document.getElementById('noPendingEnrollmentsMessage');
                const botonConfirmarTodasEstudiante = document.getElementById('confirmAllStudentEnrollmentsBtn');

                listaInscripciones.innerHTML = '';
                cargandoInscripciones.style.display = 'block';
                mensajeSinInscripciones.style.display = 'none';
                botonConfirmarTodasEstudiante.style.display = 'none';

                try {
                    const respuesta = await fetch(`../api/obtener_historial_academico_admin.php?accion=obtener_inscripciones_pendientes&id_usuario=${idUsuario}`);
                    const datos = await respuesta.json();

                    cargandoInscripciones.style.display = 'none';

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
                                                        onclick="return confirm('¿Rechazar (eliminar) inscripción para ${inscripcion.nombre_asignatura}?');">
                                                    <i class="fas fa-times"></i> Rechazar
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            `;
                            listaInscripciones.insertAdjacentHTML('beforeend', htmlInscripcion);
                        });
                        botonConfirmarTodasEstudiante.style.display = 'block';
                    } else {
                        mensajeSinInscripciones.style.display = 'block';
                        botonConfirmarTodasEstudiante.style.display = 'none';
                    }
                } catch (error) {
                    cargandoInscripciones.style.display = 'none';
                    mensajeSinInscripciones.textContent = 'Error al cargar las inscripciones: ' + error.message;
                    mensajeSinInscripciones.style.display = 'block';
                    console.error("Error al cargar inscripciones:", error);
                }
            });
        });


        // --- Lógica para Historial Académico (Ver) ---

        document.querySelectorAll('.view-history-btn').forEach(boton => {
            boton.addEventListener('click', async function() {
                const idUsuario = this.closest('tr').dataset.id_usuario;
                const nombreEstudiante = this.closest('tr').dataset.nombre_estudiante;

                document.getElementById('modalHistoryStudentName').textContent = nombreEstudiante;
                document.getElementById('modalHistoryStudentUserId').value = idUsuario;

                const cuerpoTablaHistorialAcademico = document.getElementById('academicHistoryTableBody');
                const cargandoHistorial = document.getElementById('loadingHistory');
                const mensajeSinHistorialAcademico = document.getElementById('noAcademicHistoryMessage');
                const contenedorTablaHistorial = document.getElementById('historyTableContainer');

                cuerpoTablaHistorialAcademico.innerHTML = '';
                cargandoHistorial.style.display = 'block';
                mensajeSinHistorialAcademico.style.display = 'none';
                contenedorTablaHistorial.style.display = 'none';

                try {
                    const respuesta = await fetch(`../api/obtener_historial_academico_admin.php?accion=obtener_historial_academico&id_usuario=${idUsuario}`);
                    const datos = await respuesta.json();

                    cargandoHistorial.style.display = 'none';

                    if (datos.success && datos.historial.length > 0) {
                        contenedorTablaHistorial.style.display = 'block';
                        datos.historial.forEach(entrada => {
                            const fila = `
                                <tr>
                                    <td>${entrada.nombre_asignatura}</td>
                                    <td>${entrada.codigo_asignatura}</td>
                                    <td>${entrada.numero_semestre}</td>
                                    <td>${entrada.nombre_anio}</td>
                                    <td>${entrada.nota_final}</td>
                                    <td>${entrada.estado_final}</td>
                                </tr>
                            `;
                            cuerpoTablaHistorialAcademico.insertAdjacentHTML('beforeend', fila);
                        });
                    } else {
                        mensajeSinHistorialAcademico.style.display = 'block';
                    }
                } catch (error) {
                    cargandoHistorial.style.display = 'none';
                    mensajeSinHistorialAcademico.textContent = 'Error al cargar el historial: ' + error.message;
                    mensajeSinHistorialAcademico.style.display = 'block';
                    console.error("Error al cargar historial:", error);
                }
            });
        });

        // --- Lógica para Gestionar Historial Académico (Añadir/Editar/Eliminar) ---

        // Función para cargar y mostrar el historial académico en el modal de gestión
        async function cargarGestionHistorialAcademico(idUsuario) {
            const cuerpoModal = document.getElementById('manageAcademicHistoryTableBody');
            const mensajeCargando = document.getElementById('loadingHistoryManage');
            const mensajeSinHistorial = document.getElementById('noCurrentHistoryToManageMessage');
            const contenedorTablaHistorial = document.getElementById('currentHistoryTableContainer');

            cuerpoModal.innerHTML = '';
            mensajeCargando.style.display = 'block';
            mensajeSinHistorial.style.display = 'none';
            contenedorTablaHistorial.style.display = 'none';

            try {
                const respuesta = await fetch(`../api/obtener_historial_academico_admin.php?accion=obtener_historial_academico&id_usuario=${idUsuario}`);
                const datos = await respuesta.json();

                mensajeCargando.style.display = 'none';
                if (datos.success && datos.historial.length > 0) {
                    contenedorTablaHistorial.style.display = 'block';
                    datos.historial.forEach(entrada => {
                        const fila = `<tr data-id_historial="${entrada.id}"
                                        data-id_asignatura="${entrada.id_asignatura}"
                                        data-id_semestre="${entrada.id_semestre}"
                                        data-nota_final="${entrada.nota_final}"
                                        data-estado_final="${entrada.estado_final}">
                                        <td>${entrada.nombre_asignatura}</td>
                                        <td>${entrada.numero_semestre} (${entrada.nombre_anio})</td>
                                        <td>${entrada.nota_final}</td>
                                        <td>${entrada.estado_final}</td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-info editar-entrada-historial me-2" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger eliminar-entrada-historial" title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>`;
                        cuerpoModal.insertAdjacentHTML('beforeend', fila);
                    });
                } else {
                    mensajeSinHistorial.style.display = 'block';
                }
            } catch (error) {
                mensajeCargando.style.display = 'none';
                mensajeSinHistorial.style.display = 'block';
                console.error("Error al cargar historial para gestión:", error);
                alert("Error al cargar el historial académico del estudiante. Inténtalo de nuevo.");
            }
        }

        // Event listener para los botones "Gestionar Historial"
        document.querySelectorAll('.manage-history-btn').forEach(boton => {
            boton.addEventListener('click', function() {
                const idUsuario = this.closest('tr').dataset.id_usuario;
                const nombreEstudiante = this.closest('tr').dataset.nombre_estudiante;

                document.getElementById('manageHistoryStudentUserId').value = idUsuario;
                document.getElementById('manageHistoryStudentName').textContent = nombreEstudiante;

                // Resetear el formulario a modo "añadir"
                document.getElementById('operacionHistorial').value = 'add';
                document.getElementById('idHistorialEntry').value = '';
                document.getElementById('asignaturaSelect').value = '';
                document.getElementById('semestreSelect').value = '';
                document.getElementById('notaFinal').value = '';
                document.getElementById('estadoFinal').value = '';
                document.getElementById('saveHistoryEntryBtn').innerHTML = '<i class="fas fa-save me-2"></i> Guardar Historial';
                document.getElementById('saveHistoryEntryBtn').classList.remove('btn-warning');
                document.getElementById('saveHistoryEntryBtn').classList.add('btn-primary');
                document.getElementById('deleteHistoryEntryBtn').style.display = 'none';
                document.getElementById('editDeleteHistoryMessage').style.display = 'none';

                cargarGestionHistorialAcademico(idUsuario); // Cargar el historial para edición/eliminación
            });
        });

        // Event listener para clic en una fila de historial para editar/eliminar
        document.getElementById('manageAcademicHistoryTableBody').addEventListener('click', function(evento) {
            if (evento.target.closest('.editar-entrada-historial')) {
                const boton = evento.target.closest('.editar-entrada-historial');
                const fila = boton.closest('tr');
                const idHistorial = fila.dataset.id_historial;
                const idAsignatura = fila.dataset.id_asignatura;
                const idSemestre = fila.dataset.id_semestre;
                const notaFinal = fila.dataset.nota_final;
                const estadoFinal = fila.dataset.estado_final;

                // Llenar el formulario con los datos de la fila
                document.getElementById('operacionHistorial').value = 'edit';
                document.getElementById('idHistorialEntry').value = idHistorial;
                document.getElementById('asignaturaSelect').value = idAsignatura;
                document.getElementById('semestreSelect').value = idSemestre;
                document.getElementById('notaFinal').value = notaFinal;
                document.getElementById('estadoFinal').value = estadoFinal;

                // Cambiar el texto del botón y mostrar el botón de eliminar
                document.getElementById('saveHistoryEntryBtn').innerHTML = '<i class="fas fa-edit me-2"></i> Actualizar Historial';
                document.getElementById('saveHistoryEntryBtn').classList.remove('btn-primary');
                document.getElementById('saveHistoryEntryBtn').classList.add('btn-warning');
                document.getElementById('deleteHistoryEntryBtn').style.display = 'block';
                document.getElementById('editDeleteHistoryMessage').style.display = 'block';
            }

            if (evento.target.closest('.eliminar-entrada-historial')) {
                const boton = evento.target.closest('.eliminar-entrada-historial');
                const fila = boton.closest('tr');
                const idHistorial = fila.dataset.id_historial;
                const nombreAsignatura = fila.children[0].textContent;
                const semestreAsignatura = fila.children[1].textContent;

                if (confirm(`¿Estás seguro de que quieres ELIMINAR la entrada de historial para "${nombreAsignatura}" del ${semestreAsignatura}?`)) {
                    // Configurar el formulario para la operación de eliminación
                    document.getElementById('operacionHistorial').value = 'delete';
                    document.getElementById('idHistorialEntry').value = idHistorial;
                    // Enviar el formulario
                    document.getElementById('academicHistoryForm').submit();
                }
            }
        });

        // Asegurarse de que el formulario se envíe con la operación correcta al guardar
        document.getElementById('academicHistoryForm')?.addEventListener('submit', function(e) {
            return true;
        });

    }); // Fin DOMContentLoaded
</script>