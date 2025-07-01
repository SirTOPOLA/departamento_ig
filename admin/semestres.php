<?php
// --- INICIO DE DEPURACIÓN TEMPORAL ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- FIN DE DEPURACIÓN TEMPORAL ---

// 1. Incluir funciones y configuración de la base de datos PRIMERO
require_once '../includes/functions.php';
require_once '../config/database.php';

// 2. Iniciar sesión si es necesario (si tus funciones no lo hacen ya)
// session_start(); // Descomenta si get_flash_messages o set_flash_message necesitan que la sesión esté iniciada aquí

// 3. Lógica de autenticación y roles
check_login_and_role('Administrador');

// --- Lógica para añadir/editar/eliminar semestres (TU CÓDIGO POST) --- 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $numero_semestre = sanitize_input($_POST['numero_semestre'] ?? '');
    $id_anio_academico = filter_var($_POST['id_anio_academico'] ?? null, FILTER_SANITIZE_STRING);
    $fecha_inicio = sanitize_input($_POST['fecha_inicio'] ?? '');
    $fecha_fin = sanitize_input($_POST['fecha_fin'] ?? '');
    $id_curso_asociado_al_semestre = filter_var($_POST['id_curso_asociado_al_semestre'] ?? null, FILTER_VALIDATE_INT);

    $should_redirect = false; // Bandera para la redirección

    // Validaciones básicas, incluyendo el nuevo campo
    if (empty($numero_semestre) || $id_anio_academico === null || empty($fecha_inicio) || empty($fecha_fin) || $id_curso_asociado_al_semestre === null) {
        set_flash_message('danger', 'Error: Todos los campos (incluido el curso asociado) son obligatorios.');
    } elseif (!strtotime($fecha_inicio) || !strtotime($fecha_fin)) {
        set_flash_message('danger', 'Error: Las fechas no son válidas.');
    } elseif ($fecha_inicio >= $fecha_fin) {
        set_flash_message('danger', 'Error: La fecha de inicio debe ser anterior a la fecha de fin.');
    } else {
        try {
            // Verificar si el id_anio_academico existe
            $stmt_check_anio = $pdo->prepare("SELECT COUNT(*) FROM anios_academicos WHERE id = :id_anio");
            $stmt_check_anio->bindParam(':id_anio', $id_anio_academico);
            $stmt_check_anio->execute();
            $anio_exists = ($stmt_check_anio->fetchColumn() > 0);

            // Verificar si el id_curso_asociado_al_semestre existe
            $stmt_check_curso = $pdo->prepare("SELECT COUNT(*) FROM cursos WHERE id = :id_curso");
            $stmt_check_curso->bindParam(':id_curso', $id_curso_asociado_al_semestre);
            $stmt_check_curso->execute();
            $curso_exists = ($stmt_check_curso->fetchColumn() > 0);

            if (!$anio_exists) {
                set_flash_message('danger', 'Error: El año académico seleccionado no es válido.');
            } elseif (!$curso_exists) {
                set_flash_message('danger', 'Error: El curso asociado seleccionado no es válido.');
            } else {
                // Si todas las validaciones y comprobaciones de FK pasan, procede con las operaciones de la base de datos
                if ($action === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO semestres (numero_semestre, id_anio_academico, fecha_inicio, fecha_fin, id_curso_asociado_al_semestre) VALUES (:numero_semestre, :id_anio_academico, :fecha_inicio, :fecha_fin, :id_curso_asociado_al_semestre)");
                    $stmt->bindParam(':numero_semestre', $numero_semestre);
                    $stmt->bindParam(':id_anio_academico', $id_anio_academico);
                    $stmt->bindParam(':fecha_inicio', $fecha_inicio);
                    $stmt->bindParam(':fecha_fin', $fecha_fin);
                    $stmt->bindParam(':id_curso_asociado_al_semestre', $id_curso_asociado_al_semestre, PDO::PARAM_INT);
                    $stmt->execute();
                    set_flash_message('success', 'Semestre añadido correctamente.');
                    $should_redirect = true; // Éxito, se puede redirigir
                } elseif ($action === 'edit') {
                    if ($id === null) {
                        set_flash_message('danger', 'Error: ID de semestre no válido para edición.');
                    } else {
                        $stmt = $pdo->prepare("UPDATE semestres SET numero_semestre = :numero_semestre, id_anio_academico = :id_anio_academico, fecha_inicio = :fecha_inicio, fecha_fin = :fecha_fin, id_curso_asociado_al_semestre = :id_curso_asociado_al_semestre WHERE id = :id");
                        $stmt->bindParam(':numero_semestre', $numero_semestre);
                        $stmt->bindParam(':id_anio_academico', $id_anio_academico);
                        $stmt->bindParam(':fecha_inicio', $fecha_inicio);
                        $stmt->bindParam(':fecha_fin', $fecha_fin);
                        $stmt->bindParam(':id_curso_asociado_al_semestre', $id_curso_asociado_al_semestre, PDO::PARAM_INT);
                        $stmt->bindParam(':id', $id);
                        $stmt->execute();
                        set_flash_message('success', 'Semestre actualizado correctamente.');
                        $should_redirect = true; // Éxito, se puede redirigir
                    }
                } elseif ($action === 'delete') {
                    if ($id === null) {
                        set_flash_message('danger', 'Error: ID de semestre no válido para eliminación.');
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM semestres WHERE id = :id");
                        $stmt->bindParam(':id', $id);
                        $stmt->execute();
                        set_flash_message('success', 'Semestre eliminado correctamente.');
                        $should_redirect = true; // Éxito, se puede redirigir
                    }
                }
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                set_flash_message('danger', 'Error: No se puede eliminar el semestre porque está asociado a otros registros (ej. horarios, estudiantes) o ya existe un semestre similar para este año académico.');
            } else {
                set_flash_message('danger', 'Error de base de datos: ' . $e->getMessage());
            }
        }
    }

    // REDIRECCIÓN CONDICIONAL: Solo redirige si la operación fue un éxito.
    if ($should_redirect) {
        header('Location: semestres.php');
        exit; // Siempre llama a exit después de header('Location')
    }
    // Si no hay redirección, el script continúa para renderizar la página con los mensajes flash actuales
}

// 4. Obtener datos (estas consultas se ejecutan cada vez que se carga la página)
$stmt_semestres = $pdo->query("SELECT s.id, s.numero_semestre, s.fecha_inicio, s.fecha_fin,
                                      aa.nombre_anio AS anio_academico, s.id_anio_academico,
                                      c.nombre_curso AS nombre_curso_asociado, s.id_curso_asociado_al_semestre
                               FROM semestres s
                               JOIN anios_academicos aa ON s.id_anio_academico = aa.id
                               LEFT JOIN cursos c ON s.id_curso_asociado_al_semestre = c.id
                               ORDER BY aa.nombre_anio DESC, s.numero_semestre ASC");
$semestres = $stmt_semestres->fetchAll();

$stmt_anios_academicos = $pdo->query("SELECT id, nombre_anio FROM anios_academicos ORDER BY nombre_anio DESC");
$anios_academicos_options = $stmt_anios_academicos->fetchAll();

$stmt_cursos = $pdo->query("SELECT id, nombre_curso FROM cursos ORDER BY nombre_curso ASC");
$cursos_options = $stmt_cursos->fetchAll();

// 5. Obtener mensajes flash para JavaScript (estos se obtienen justo antes de renderizar el HTML)
$flash_messages = get_flash_messages();

// 6. Ahora incluye el header.php y el resto del HTML
$page_title = "Gestión de Semestres"; // Definir el título ANTES de incluir header.php
include_once '../includes/header.php'; // Aquí empieza el HTML
?>

<h1 class="mt-4">Gestión de Semestres</h1>
<p class="lead">Administra los semestres para cada año académico, incluyendo el curso asociado.</p>

<div class="mb-3 d-flex justify-content-between align-items-center">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#semesterModal" id="addSemesterBtn">
        <i class="fas fa-plus-circle me-2"></i>Añadir Nuevo Semestre
    </button>
    <div class="col-md-4">
        <input type="search" class="form-control" id="searchInput" placeholder="Buscar semestre...">
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0">Lista de Semestres</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped" id="semestresTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Semestre</th>
                        <th>Año Académico</th>
                        <th>Curso Asociado</th>
                        <th>Fecha Inicio</th>
                        <th>Fecha Fin</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($semestres) > 0): ?>
                        <?php foreach ($semestres as $semestre): ?>
                            <tr data-id="<?php echo htmlspecialchars($semestre['id']); ?>"
                                data-numero_semestre="<?php echo htmlspecialchars($semestre['numero_semestre']); ?>"
                                data-id_anio_academico="<?php echo htmlspecialchars($semestre['id_anio_academico']); ?>"
                                data-fecha_inicio="<?php echo htmlspecialchars($semestre['fecha_inicio']); ?>"
                                data-fecha_fin="<?php echo htmlspecialchars($semestre['fecha_fin']); ?>"
                                data-id_curso_asociado_al_semestre="<?php echo htmlspecialchars($semestre['id_curso_asociado_al_semestre']); ?>">
                                <td><?php echo htmlspecialchars($semestre['id']); ?></td>
                                <td><?php echo htmlspecialchars($semestre['numero_semestre']); ?></td>
                                <td><?php echo htmlspecialchars($semestre['anio_academico']); ?></td>
                                <td><?php echo htmlspecialchars($semestre['nombre_curso_asociado'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($semestre['fecha_inicio']); ?></td>
                                <td><?php echo htmlspecialchars($semestre['fecha_fin']); ?></td>
                                <td>
                                    <button type="button" class="btn btn-warning btn-sm edit-btn me-1" title="Editar" data-bs-toggle="modal" data-bs-target="#semesterModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form action="semestres.php" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de que quieres eliminar este semestre? Esto podría afectar la programación de horarios y registros.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($semestre['id']); ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Eliminar">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No hay semestres registrados.</td>
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

<div class="modal fade" id="semesterModal" tabindex="-1" aria-labelledby="semesterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <form id="semesterForm" action="semestres.php" method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="semesterModalLabel">Añadir Nuevo Semestre</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="semesterId">

                    <div class="mb-3">
                        <label for="numero_semestre" class="form-label">Nombre del Semestre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="numero_semestre" name="numero_semestre" required>
                        <small class="form-text text-muted">Ej: Primer Semestre, Segundo Semestre</small>
                    </div>
                    <div class="mb-3">
                        <label for="id_anio_academico" class="form-label">Año Académico <span class="text-danger">*</span></label>
                        <select class="form-select" id="id_anio_academico" name="id_anio_academico" required>
                            <option value="">Seleccione un Año Académico</option>
                            <?php foreach ($anios_academicos_options as $anio): ?>
                                <option value="<?php echo htmlspecialchars($anio['id']); ?>">
                                    <?php echo htmlspecialchars($anio['nombre_anio']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="id_curso_asociado_al_semestre" class="form-label">Curso Asociado al Semestre <span class="text-danger">*</span></label>
                        <select class="form-select" id="id_curso_asociado_al_semestre" name="id_curso_asociado_al_semestre" required>
                            <option value="">Seleccione un Curso</option>
                            <?php foreach ($cursos_options as $curso): ?>
                                <option value="<?php echo htmlspecialchars($curso['id']); ?>">
                                    <?php echo htmlspecialchars($curso['nombre_curso']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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
                        <i class="fas fa-save me-1"></i> Añadir Semestre
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

    document.getElementById('addSemesterBtn').addEventListener('click', function() {
        document.getElementById('semesterModalLabel').innerText = 'Añadir Nuevo Semestre';
        document.getElementById('formAction').value = 'add';
        document.getElementById('semesterId').value = '';
        document.getElementById('semesterForm').reset();
        document.getElementById('submitBtn').innerText = 'Añadir Semestre';
    });

    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('semesterModalLabel').innerText = 'Editar Semestre';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('submitBtn').innerText = 'Guardar Cambios';

            const row = this.closest('tr');
            document.getElementById('semesterId').value = row.dataset.id;
            document.getElementById('numero_semestre').value = row.dataset.numero_semestre;
            document.getElementById('id_anio_academico').value = row.dataset.id_anio_academico;
            document.getElementById('fecha_inicio').value = row.dataset.fecha_inicio;
            document.getElementById('fecha_fin').value = row.dataset.fecha_fin;
            document.getElementById('id_curso_asociado_al_semestre').value = row.dataset.id_curso_asociado_al_semestre;
        });
    });

    document.getElementById('searchInput').addEventListener('keyup', function() {
        var input, filter, table, tr, td, i, j, txtValue;
        input = document.getElementById("searchInput");
        filter = input.value.toUpperCase();
        table = document.getElementById("semestresTable");
        tr = table.getElementsByTagName("tr");

        document.getElementById('pagination').style.display = 'none';

        for (i = 1; i < tr.length; i++) {
            tr[i].style.display = "none";
            td = tr[i].getElementsByTagName("td");
            let rowMatches = false;
            for (j = 0; j < td.length; j++) {
                if (td[j]) {
                    txtValue = td[j].textContent || td[j].innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        rowMatches = true;
                        break;
                    }
                }
            }
            if (rowMatches) {
                tr[i].style.display = "";
            }
        }
        if (filter === "") {
            document.getElementById('pagination').style.display = 'flex';
            showPage(currentPage);
        }
    });

    const rowsPerPage = 10;
    let currentPage = 1;
    let totalPages = 0;

    function setupPagination() {
        const table = document.getElementById('semestresTable');
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
        const table = document.getElementById('semestresTable');
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

    function showToast(type, message) {
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) {
            console.error('El contenedor de toasts no se encontró. Asegúrate de que el div.toast-container exista en el HTML.');
            return;
        }
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

    document.addEventListener('DOMContentLoaded', function() {
        setupPagination();
        showPage(currentPage);

        flashMessages.forEach(msg => {
            showToast(msg.type, msg.message);
        });
    });

</script>