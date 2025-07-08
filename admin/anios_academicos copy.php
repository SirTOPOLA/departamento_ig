<?php
require_once '../includes/functions.php';

check_login_and_role('Administrador');

require_once '../config/database.php';

$page_title = "Gestión de Años Académicos";
include_once '../includes/header.php';

// --- Lógica para añadir/editar/eliminar años académicos ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $nombre_anio = sanitize_input($_POST['nombre_anio'] ?? '');
    $fecha_inicio = sanitize_input($_POST['fecha_inicio'] ?? '');
    $fecha_fin = sanitize_input($_POST['fecha_fin'] ?? '');

    // Validaciones básicas
    if (empty($nombre_anio) || empty($fecha_inicio) || empty($fecha_fin)) {
        set_flash_message('danger', 'Error: Todos los campos son obligatorios.');
    } elseif (!strtotime($fecha_inicio) || !strtotime($fecha_fin)) {
        set_flash_message('danger', 'Error: Las fechas no son válidas.');
    } elseif ($fecha_inicio >= $fecha_fin) {
        set_flash_message('danger', 'Error: La fecha de inicio debe ser anterior a la fecha de fin.');
    } else {
        try {
            // Iniciar transacción para asegurar atomicidad de la creación del año y la actualización de estudiantes
            $pdo->beginTransaction();

            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO anios_academicos (nombre_anio, fecha_inicio, fecha_fin) VALUES (:nombre_anio, :fecha_inicio, :fecha_fin)");
                $stmt->bindParam(':nombre_anio', $nombre_anio);
                $stmt->bindParam(':fecha_inicio', $fecha_inicio);
                $stmt->bindParam(':fecha_fin', $fecha_fin);
                $stmt->execute();
                $new_anio_id = $pdo->lastInsertId(); // Obtener el ID del año recién insertado

                // --- Lógica para actualizar los cursos de los estudiantes ---
                // 1. Obtener el ID del año académico anterior
                // Se asume que el "año anterior" es el año con el ID más alto antes del recién creado.
                $stmt_prev_anio = $pdo->prepare("SELECT id FROM anios_academicos WHERE id < :new_anio_id ORDER BY id DESC LIMIT 1");
                $stmt_prev_anio->bindParam(':new_anio_id', $new_anio_id, PDO::PARAM_INT);
                $stmt_prev_anio->execute();
                $prev_anio_id = $stmt_prev_anio->fetchColumn();

                if ($prev_anio_id) {
                    // Obtener todos los estudiantes actualmente activos en el año anterior
                    // También necesitamos el id del curso actual para determinar el avance
                    $stmt_students = $pdo->prepare("
                        SELECT ce.id AS curso_estudiante_id, ce.id_estudiante, ce.id_curso,
                                c.nombre_curso
                        FROM curso_estudiante ce
                        JOIN cursos c ON ce.id_curso = c.id
                        WHERE ce.id_anio = :prev_anio_id AND ce.estado = 'activo'
                    ");
                    $stmt_students->bindParam(':prev_anio_id', $prev_anio_id, PDO::PARAM_INT);
                    $stmt_students->execute();
                    $students_to_update = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($students_to_update as $student) {
                        $student_id = $student['id_estudiante'];
                        $current_course_id = $student['id_curso'];
                        $current_curso_estudiante_id = $student['curso_estudiante_id'];
                        $nombre_curso_actual = $student['nombre_curso'];

                        // 2. Verificar si el estudiante ha aprobado AL MENOS UNA asignatura de su curso en el año anterior
                        $stmt_approved_any_asignatura = $pdo->prepare("
                            SELECT COUNT(DISTINCT ha.id_asignatura)
                            FROM historial_academico ha
                            JOIN asignaturas a ON ha.id_asignatura = a.id
                            JOIN semestres s ON ha.id_semestre = s.id
                            WHERE ha.id_estudiante = :student_id
                            AND ha.estado_final = 'APROBADO'
                            AND s.id_anio_academico = :prev_anio_id
                            AND a.id_curso = :current_course_id
                        ");
                        $stmt_approved_any_asignatura->bindParam(':student_id', $student_id, PDO::PARAM_INT);
                        $stmt_approved_any_asignatura->bindParam(':prev_anio_id', $prev_anio_id, PDO::PARAM_INT);
                        $stmt_approved_any_asignatura->bindParam(':current_course_id', $current_course_id, PDO::PARAM_INT);
                        $stmt_approved_any_asignatura->execute();
                        $approved_asignaturas_count = $stmt_approved_any_asignatura->fetchColumn();

                        // Lógica para determinar si avanza de curso
                        $should_advance = ($approved_asignaturas_count > 0);
                        $next_course_id = $current_course_id;
                        $curso_estudiante_estado_anterior = 'reprobado'; // Por defecto, si no cumple la condición de avance
                        $create_new_course_entry = false; // Por defecto, no se crea nueva entrada

                        // Obtener los IDs de los cursos 'Primero', 'Segundo', 'Tercero'
                        // Esto se hace una vez para eficiencia o se asume conocimiento de sus IDs (ej. 1, 2, 3)
                        // Para robustez, es mejor consultarlos:
                        static $course_ids = [];
                        if (empty($course_ids)) {
                            $stmt_courses = $pdo->query("SELECT id, nombre_curso FROM cursos");
                            while ($row = $stmt_courses->fetch(PDO::FETCH_ASSOC)) {
                                $course_ids[$row['nombre_curso']] = $row['id'];
                            }
                        }

                        if ($should_advance) {
                            if ($current_course_id == $course_ids['Primero']) { // Estaba en Primero
                                $next_course_id = $course_ids['Segundo'] ?? null;
                                $curso_estudiante_estado_anterior = 'finalizado';
                                $create_new_course_entry = true;
                            } elseif ($current_course_id == $course_ids['Segundo']) { // Estaba en Segundo
                                $next_course_id = $course_ids['Tercero'] ?? null;
                                $curso_estudiante_estado_anterior = 'finalizado';
                                $create_new_course_entry = true;
                            } elseif ($current_course_id == $course_ids['Tercero']) { // Estaba en Tercero
                                // Se mantiene en Tercero, pero marca el anterior como finalizado
                                $next_course_id = $course_ids['Tercero'] ?? null; // Sigue siendo tercero
                                $curso_estudiante_estado_anterior = 'finalizado';
                                $create_new_course_entry = true; // Crea nueva entrada para el nuevo año en Tercero
                            } else {
                                // Cualquier otro curso que no tiene una progresión automática definida
                                // O no se hace nada o se mantiene en el mismo curso sin marcar como finalizado.
                                // Para este caso, vamos a considerar que no avanza automáticamente.
                                $should_advance = false; // Revertir a no avanzar si el curso no es 1, 2 o 3.
                                $curso_estudiante_estado_anterior = 'reprobado';
                                $create_new_course_entry = false;
                            }
                        }
                        
                        // Si no hay asignaturas aprobadas (y no es el caso de Tercero que no aprobó ninguna en su propio curso)
                        // Para los cursos 1 y 2, si no aprobó ninguna, se marca como reprobado y NO AVANZA
                        if (!$should_advance && ($current_course_id == $course_ids['Primero'] || $current_course_id == $course_ids['Segundo'])) {
                             $curso_estudiante_estado_anterior = 'reprobado';
                             $create_new_course_entry = false; // No se crea nueva entrada si reprobó totalmente
                        } elseif (!$should_advance && $current_course_id == $course_ids['Tercero']) {
                            // Si está en tercero y no aprobó ninguna, se marca como reprobado y no se crea nueva entrada (si es el fin de ciclo)
                            // Si debe repetir tercero, entonces la lógica cambia y sí se crearía una entrada con el mismo curso.
                            // Por la descripción original "se mantiene en tercero", la nueva entrada con 'activo' solo se crea si 'should_advance' es true para tercero.
                            $curso_estudiante_estado_anterior = 'reprobado';
                            $create_new_course_entry = false; // Asumiendo que no avanza/continúa en tercero si reprobó todo
                        }


                        // 3. Actualizar el estado del registro actual de curso_estudiante (del año anterior)
                        $stmt_update_current_ce = $pdo->prepare("
                            UPDATE curso_estudiante
                            SET estado = :estado, fecha_finalizacion = NOW()
                            WHERE id = :curso_estudiante_id
                        ");
                        $stmt_update_current_ce->bindParam(':estado', $curso_estudiante_estado_anterior);
                        $stmt_update_current_ce->bindParam(':curso_estudiante_id', $current_curso_estudiante_id, PDO::PARAM_INT);
                        $stmt_update_current_ce->execute();

                        // 4. Crear un nuevo registro en curso_estudiante para el siguiente año si avanza
                        if ($create_new_course_entry && $next_course_id) {
                            $stmt_insert_next_ce = $pdo->prepare("
                                INSERT INTO curso_estudiante (id_estudiante, id_curso, id_anio, estado, fecha_registro)
                                VALUES (:id_estudiante, :id_curso, :id_anio, 'activo', NOW())
                            ");
                            $stmt_insert_next_ce->bindParam(':id_estudiante', $student_id, PDO::PARAM_INT);
                            $stmt_insert_next_ce->bindParam(':id_curso', $next_course_id, PDO::PARAM_INT);
                            $stmt_insert_next_ce->bindParam(':id_anio', $new_anio_id, PDO::PARAM_INT);
                            $stmt_insert_next_ce->execute();
                        }
                    }
                } else {
                    set_flash_message('info', 'No se encontró un año académico anterior para procesar la progresión de estudiantes.');
                }
                // --- Fin de la lógica para actualizar los cursos de los estudiantes ---

                set_flash_message('success', 'Año académico añadido correctamente y cursos de estudiantes actualizados.');

            } elseif ($action === 'edit') {
                if ($id === null) {
                    set_flash_message('danger', 'Error: ID de año académico no válido para edición.');
                } else {
                    $stmt = $pdo->prepare("UPDATE anios_academicos SET nombre_anio = :nombre_anio, fecha_inicio = :fecha_inicio, fecha_fin = :fecha_fin WHERE id = :id");
                    $stmt->bindParam(':nombre_anio', $nombre_anio);
                    $stmt->bindParam(':fecha_inicio', $fecha_inicio);
                    $stmt->bindParam(':fecha_fin', $fecha_fin);
                    $stmt->bindParam(':id', $id);
                    $stmt->execute();
                    set_flash_message('success', 'Año académico actualizado correctamente.');
                }
            } elseif ($action === 'delete') {
                if ($id === null) {
                    set_flash_message('danger', 'Error: ID de año académico no válido para eliminación.');
                } else {
                    // Verificar si existen semestres asociados antes de eliminar
                    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM semestres WHERE id_anio_academico = :id");
                    $stmt_check->bindParam(':id', $id);
                    $stmt_check->execute();
                    if ($stmt_check->fetchColumn() > 0) {
                        set_flash_message('danger', 'Error: No se puede eliminar el año académico porque tiene semestres asociados.');
                    } else {
                        // Opcional: Eliminar registros de curso_estudiante asociados a este año si se elimina
                        // $stmt_delete_ce = $pdo->prepare("DELETE FROM curso_estudiante WHERE id_anio = :id");
                        // $stmt_delete_ce->bindParam(':id', $id);
                        // $stmt_delete_ce->execute();

                        $stmt = $pdo->prepare("DELETE FROM anios_academicos WHERE id = :id");
                        $stmt->bindParam(':id', $id);
                        $stmt->execute();
                        set_flash_message('success', 'Año académico eliminado correctamente.');
                    }
                }
            }
            $pdo->commit(); // Confirmar la transacción
        } catch (PDOException $e) {
            $pdo->rollBack(); // Revertir la transacción en caso de error
            // Manejar errores de duplicidad (ej. nombre_anio UNIQUE)
            if ($e->getCode() == '23000') { // Código SQLSTATE para violación de integridad
                set_flash_message('danger', 'Error: El nombre del año académico ya existe o hay un conflicto de datos.');
            } else {
                set_flash_message('danger', 'Error de base de datos al procesar año académico o estudiantes: ' . $e->getMessage());
                // Considera loggear el error completo para depuración
                error_log("Error al procesar año académico: " . $e->getMessage() . " en " . $e->getFile() . " en la línea " . $e->getLine());
            }
        }
    }
    header('Location: anios_academicos.php');
    exit();
}

// --- Obtener todos los años académicos para la tabla ---
$stmt_anios_academicos = $pdo->query("SELECT id, nombre_anio, fecha_inicio, fecha_fin FROM anios_academicos ORDER BY nombre_anio DESC");
$anios_academicos = $stmt_anios_academicos->fetchAll();

// Obtener mensajes flash para JavaScript
$flash_messages = get_flash_messages();

?>

<h1 class="mt-4">Gestión de Años Académicos</h1>
<p class="lead">Administra los años académicos de la universidad.</p>

<div class="mb-3 d-flex justify-content-between align-items-center">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#yearModal" id="addYearBtn">
        <i class="fas fa-plus-circle me-2"></i>Añadir Nuevo Año
    </button>
    <div class="col-md-4">
        <input type="search" class="form-control" id="searchInput" placeholder="Buscar año académico...">
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0">Lista de Años Académicos</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped" id="yearsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Año Académico</th>
                        <th>Fecha Inicio</th>
                        <th>Fecha Fin</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($anios_academicos) > 0): ?>
                        <?php foreach ($anios_academicos as $anio): ?>
                            <tr data-id="<?php echo htmlspecialchars($anio['id']); ?>"
                                data-nombre_anio="<?php echo htmlspecialchars($anio['nombre_anio']); ?>"
                                data-fecha_inicio="<?php echo htmlspecialchars($anio['fecha_inicio']); ?>"
                                data-fecha_fin="<?php echo htmlspecialchars($anio['fecha_fin']); ?>">
                                <td><?php echo htmlspecialchars($anio['id']); ?></td>
                                <td><?php echo htmlspecialchars($anio['nombre_anio']); ?></td>
                                <td><?php echo htmlspecialchars($anio['fecha_inicio']); ?></td>
                                <td><?php echo htmlspecialchars($anio['fecha_fin']); ?></td>
                                <td>
                                    <button type="button" class="btn btn-warning btn-sm edit-btn me-1" title="Editar" data-bs-toggle="modal" data-bs-target="#yearModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form action="anios_academicos.php" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de que quieres eliminar este año académico? Esto también podría afectar semestres asociados.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($anio['id']); ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Eliminar">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">No hay años académicos registrados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <nav>
            <ul class="pagination justify-content-center" id="pagination">
                </ul>
        </nav>
    </div>
</div>

<div class="modal fade" id="yearModal" tabindex="-1" aria-labelledby="yearModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <form id="yearForm" action="anios_academicos.php" method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="yearModalLabel">Añadir Nuevo Año Académico</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="yearId">

                    <div class="mb-3">
                        <label for="nombre_anio" class="form-label">Nombre del Año Académico <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombre_anio" name="nombre_anio" required>
                        <small class="form-text text-muted">Ej: 2023-2024</small>
                    </div>
                    <div class="mb-3">
                        <label for="fecha_inicio" class="form-label">Fecha de Inicio <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" required>
                    </div>
                    <div class="mb-3">
                        <label for="fecha_fin" class="form-label">Fecha de Fin <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cerrar
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save me-1"></i> Añadir Año
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>

<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
    </div>

<script>
    const flashMessages = <?php echo json_encode($flash_messages); ?>;

    // Lógica para abrir modal de "Añadir Nuevo Año Académico"
    document.getElementById('addYearBtn').addEventListener('click', function() {
        document.getElementById('yearModalLabel').innerText = 'Añadir Nuevo Año Académico';
        document.getElementById('formAction').value = 'add';
        document.getElementById('yearId').value = '';
        document.getElementById('yearForm').reset();
        document.getElementById('submitBtn').innerText = 'Añadir Año';
    });

    // Lógica para abrir modal de "Editar Año Académico"
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('yearModalLabel').innerText = 'Editar Año Académico';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('submitBtn').innerText = 'Guardar Cambios';

            const row = this.closest('tr');
            document.getElementById('yearId').value = row.dataset.id;
            document.getElementById('nombre_anio').value = row.dataset.nombre_anio;
            document.getElementById('fecha_inicio').value = row.dataset.fecha_inicio;
            document.getElementById('fecha_fin').value = row.dataset.fecha_fin;
        });
    });

    // --- Búsqueda dinámica ---
    document.getElementById('searchInput').addEventListener('keyup', function() {
        var input, filter, table, tr, td, i, j, txtValue;
        input = document.getElementById("searchInput");
        filter = input.value.toUpperCase();
        table = document.getElementById("yearsTable");
        tr = table.getElementsByTagName("tr");

        document.getElementById('pagination').style.display = 'none';

        for (i = 1; i < tr.length; i++) {
            tr[i].style.display = "none";
            td = tr[i].getElementsByTagName("td");
            for (j = 0; j < td.length; j++) {
                if (td[j]) {
                    txtValue = td[j].textContent || td[j].innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                        break;
                    }
                }
            }
        }
        if (filter === "") {
            document.getElementById('pagination').style.display = 'flex';
            showPage(currentPage);
        }
    });

    // --- Paginación ---
    const rowsPerPage = 10;
    let currentPage = 1;
    let totalPages = 0;

    function setupPagination() {
        const table = document.getElementById('yearsTable');
        const tbodyRows = table.querySelectorAll('tbody tr');
        totalPages = Math.ceil(tbodyRows.length / rowsPerPage);
        
        const paginationUl = document.getElementById('pagination');
        paginationUl.innerHTML = '';

        if (tbodyRows.length <= rowsPerPage && document.getElementById('searchInput').value === "") {
            paginationUl.style.display = 'none';
            tbodyRows.forEach(row => row.style.display = '');
            return;
        } else {
            paginationUl.style.display = 'flex';
        }

        for (let i = 1; i <= totalPages; i++) {
            const li = document.createElement('li');
            li.classList.add('page-item');
            if (i === currentPage) {
                li.classList.add('active');
            }
            const a = document.createElement('a');
            a.classList.add('page-link');
            a.href = '#';
            a.innerText = i;
            a.addEventListener('click', function(e) {
                e.preventDefault();
                currentPage = i;
                showPage(currentPage);
            });
            li.appendChild(a);
            paginationUl.appendChild(li);
        }
    }

    function showPage(page) {
        const table = document.getElementById('yearsTable');
        const tbodyRows = table.querySelectorAll('tbody tr');
        
        const startIndex = (page - 1) * rowsPerPage;
        const endIndex = startIndex + rowsPerPage;

        tbodyRows.forEach((row, index) => {
            if (index >= startIndex && index < endIndex) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });

        document.querySelectorAll('#pagination .page-item').forEach(li => {
            li.classList.remove('active');
        });
        const activePageLink = document.querySelector(`#pagination .page-item:nth-child(${page})`);
        if (activePageLink) {
            activePageLink.classList.add('active');
        }
    }

    // Función para mostrar un Toast de Bootstrap (copiada de functions.php)
    function showToast(type, message) {
        const toastContainer = document.querySelector('.toast-container');
        const toastId = 'toast-' + Date.now();

        let bgColor = '';
        switch (type) {
            case 'success': bgColor = 'bg-success'; break;
            case 'danger': bgColor = 'bg-danger'; break;
            case 'warning': bgColor = 'bg-warning text-dark'; break;
            case 'info': bgColor = 'bg-info'; break;
            default: bgColor = 'bg-secondary'; break;
        }

        const toastHtml = `
            <div id="${toastId}" class="toast align-items-center text-white ${bgColor} border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        toastContainer.insertAdjacentHTML('beforeend', toastHtml);

        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement);
        toast.show();

        toastElement.addEventListener('hidden.bs.toast', function () {
            toastElement.remove();
        });
    }

    // Inicializar la paginación, mostrar la primera página y los toasts al cargar
    document.addEventListener('DOMContentLoaded', function() {
        setupPagination();
        showPage(currentPage);

        flashMessages.forEach(msg => {
            showToast(msg.type, msg.message);
        });
    });

</script>