<?php
require_once '../includes/functions.php';
 
check_login_and_role('Administrador');

require_once '../config/database.php';

$page_title = "Gestión de Cursos";
include_once '../includes/header.php';

// --- Lógica para añadir/editar/eliminar cursos ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $nombre_curso = sanitize_input($_POST['nombre_curso'] ?? '');
    $descripcion = sanitize_input($_POST['descripcion'] ?? '');

    // Validaciones básicas
    if (empty($nombre_curso)) {
        set_flash_message('danger', 'Error: El nombre del curso es obligatorio.');
    } else {
        try {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO cursos (nombre_curso, descripcion) VALUES (:nombre_curso, :descripcion)");
                $stmt->bindParam(':nombre_curso', $nombre_curso);
                $stmt->bindParam(':descripcion', $descripcion);
                $stmt->execute();
                set_flash_message('success', 'Curso añadido correctamente.');
            } elseif ($action === 'edit') {
                if ($id === null) {
                    set_flash_message('danger', 'Error: ID de curso no válido para edición.');
                } else {
                    $stmt = $pdo->prepare("UPDATE cursos SET nombre_curso = :nombre_curso, descripcion = :descripcion WHERE id = :id");
                    $stmt->bindParam(':nombre_curso', $nombre_curso);
                    $stmt->bindParam(':descripcion', $descripcion);
                    $stmt->bindParam(':id', $id);
                    $stmt->execute();
                    set_flash_message('success', 'Curso actualizado correctamente.');
                }
            } elseif ($action === 'delete') {
                if ($id === null) {
                    set_flash_message('danger', 'Error: ID de curso no válido para eliminación.');
                } else {
                    // TODO: Añadir verificación si el curso está siendo utilizado por asignaturas o estudiantes
                    $stmt = $pdo->prepare("DELETE FROM cursos WHERE id = :id");
                    $stmt->bindParam(':id', $id);
                    $stmt->execute();
                    set_flash_message('success', 'Curso eliminado correctamente.');
                }
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') { // Violación de integridad (ej. nombre_curso UNIQUE)
                set_flash_message('danger', 'Error: Ya existe un curso con el mismo nombre.');
            } else {
                set_flash_message('danger', 'Error de base de datos: ' . $e->getMessage());
            }
        }
    }
     
}

// --- Obtener todos los cursos para la tabla ---
$stmt_cursos = $pdo->query("SELECT id, nombre_curso, descripcion FROM cursos ORDER BY nombre_curso ASC");
$cursos = $stmt_cursos->fetchAll();

// Obtener mensajes flash para JavaScript
$flash_messages = get_flash_messages();

?>

<h1 class="mt-4">Gestión de Cursos</h1>
<p class="lead">Administra los diferentes cursos académicos (ej. Primer Curso, Segundo Curso, Máster).</p>

<div class="mb-3 d-flex justify-content-between align-items-center">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#courseModal" id="addCourseBtn">
        <i class="fas fa-plus-circle me-2"></i>Añadir Nuevo Curso
    </button>
    <div class="col-md-4">
        <input type="search" class="form-control" id="searchInput" placeholder="Buscar curso...">
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0">Lista de Cursos</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped" id="cursosTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre Curso</th>
                        <th>Descripción</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($cursos) > 0): ?>
                        <?php foreach ($cursos as $curso): ?>
                            <tr data-id="<?php echo htmlspecialchars($curso['id']); ?>"
                                data-nombre_curso="<?php echo htmlspecialchars($curso['nombre_curso']); ?>"
                                data-descripcion="<?php echo htmlspecialchars($curso['descripcion']); ?>">
                                <td><?php echo htmlspecialchars($curso['id']); ?></td>
                                <td><?php echo htmlspecialchars($curso['nombre_curso']); ?></td>
                                <td><?php echo htmlspecialchars($curso['descripcion']); ?></td>
                                <td>
                                    <button type="button" class="btn btn-warning btn-sm edit-btn me-1" title="Editar" data-bs-toggle="modal" data-bs-target="#courseModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form action="cursos.php" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de que quieres eliminar este curso? Esto podría afectar a asignaturas y estudiantes asociados.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($curso['id']); ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Eliminar">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center">No hay cursos registrados.</td>
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

<div class="modal fade" id="courseModal" tabindex="-1" aria-labelledby="courseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <form id="courseForm" action="cursos.php" method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="courseModalLabel">Añadir Nuevo Curso</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="courseId">

                    <div class="mb-3">
                        <label for="nombre_curso" class="form-label">Nombre del Curso <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombre_curso" name="nombre_curso" required>
                        <small class="form-text text-muted">Ej: Primer Curso, Máster en Ingeniería de Software</small>
                    </div>
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                        <small class="form-text text-muted">Breve descripción del curso.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cerrar
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save me-1"></i> Añadir Curso
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

    // Lógica para abrir modal de "Añadir Nuevo Curso"
    document.getElementById('addCourseBtn').addEventListener('click', function() {
        document.getElementById('courseModalLabel').innerText = 'Añadir Nuevo Curso';
        document.getElementById('formAction').value = 'add';
        document.getElementById('courseId').value = '';
        document.getElementById('courseForm').reset();
        document.getElementById('submitBtn').innerText = 'Añadir Curso';
    });

    // Lógica para abrir modal de "Editar Curso"
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('courseModalLabel').innerText = 'Editar Curso';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('submitBtn').innerText = 'Guardar Cambios';

            const row = this.closest('tr');
            document.getElementById('courseId').value = row.dataset.id;
            document.getElementById('nombre_curso').value = row.dataset.nombre_curso;
            document.getElementById('descripcion').value = row.dataset.descripcion;
        });
    });

    // --- Búsqueda dinámica ---
    document.getElementById('searchInput').addEventListener('keyup', function() {
        var input, filter, table, tr, td, i, j, txtValue;
        input = document.getElementById("searchInput");
        filter = input.value.toUpperCase();
        table = document.getElementById("cursosTable");
        tr = table.getElementsByTagName("tr");

        document.getElementById('pagination').style.display = 'none';

        for (i = 1; i < tr.length; i++) { // Skip header row
            tr[i].style.display = "none";
            td = tr[i].getElementsByTagName("td");
            for (j = 0; j < td.length; j++) { // Check all columns
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
        const table = document.getElementById('cursosTable');
        const tbodyRows = table.querySelectorAll('tbody tr');
        totalPages = Math.ceil(tbodyRows.length / rowsPerPage);
        
        const paginationUl = document.getElementById('pagination');
        paginationUl.innerHTML = '';

        if (tbodyRows.length <= rowsPerPage && document.getElementById('searchInput').value === "") {
            paginationUl.style.display = 'none';
            tbodyRows.forEach(row => row.style.display = ''); // Ensure all rows are visible if no pagination
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
        const table = document.getElementById('cursosTable');
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

    // Función para mostrar un Toast de Bootstrap (reutilizada de la anterior)
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