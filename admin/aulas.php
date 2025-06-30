<?php
require_once '../includes/functions.php';
 
check_login_and_role('Administrador');

require_once '../config/database.php';

$page_title = "Gestión de Aulas";
include_once '../includes/header.php';

// --- Lógica para añadir/editar/eliminar aulas ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $nombre_aula = sanitize_input($_POST['nombre_aula'] ?? '');
    $capacidad = filter_var($_POST['capacidad'] ?? null, FILTER_VALIDATE_INT);
    $ubicacion = sanitize_input($_POST['ubicacion'] ?? '');

    // Validaciones básicas
    if (empty($nombre_aula) || $capacidad === null || empty($ubicacion)) {
        set_flash_message('danger', 'Error: Todos los campos son obligatorios.');
    } elseif ($capacidad <= 0) {
        set_flash_message('danger', 'Error: La capacidad debe ser un número positivo.');
    } else {
        try {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO aulas (nombre_aula, capacidad, ubicacion) VALUES (:nombre_aula, :capacidad, :ubicacion)");
                $stmt->bindParam(':nombre_aula', $nombre_aula);
                $stmt->bindParam(':capacidad', $capacidad);
                $stmt->bindParam(':ubicacion', $ubicacion);
                $stmt->execute();
                set_flash_message('success', 'Aula añadida correctamente.');
            } elseif ($action === 'edit') {
                if ($id === null) {
                    set_flash_message('danger', 'Error: ID de aula no válido para edición.');
                } else {
                    $stmt = $pdo->prepare("UPDATE aulas SET nombre_aula = :nombre_aula, capacidad = :capacidad, ubicacion = :ubicacion WHERE id = :id");
                    $stmt->bindParam(':nombre_aula', $nombre_aula);
                    $stmt->bindParam(':capacidad', $capacidad);
                    $stmt->bindParam(':ubicacion', $ubicacion);
                    $stmt->bindParam(':id', $id);
                    $stmt->execute();
                    set_flash_message('success', 'Aula actualizada correctamente.');
                }
            } elseif ($action === 'delete') {
                if ($id === null) {
                    set_flash_message('danger', 'Error: ID de aula no válido para eliminación.');
                } else {
                    // TODO: Añadir verificación si el aula está siendo utilizada en algún horario
                    $stmt = $pdo->prepare("DELETE FROM aulas WHERE id = :id");
                    $stmt->bindParam(':id', $id);
                    $stmt->execute();
                    set_flash_message('success', 'Aula eliminada correctamente.');
                }
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') { // Violación de integridad (ej. nombre_aula UNIQUE)
                set_flash_message('danger', 'Error: Ya existe un aula con el mismo nombre.');
            } else {
                set_flash_message('danger', 'Error de base de datos: ' . $e->getMessage());
            }
        }
    }
   // header('Location: aulas.php');
   // exit();
}

// --- Obtener todas las aulas para la tabla ---
$stmt_aulas = $pdo->query("SELECT id, nombre_aula, capacidad, ubicacion FROM aulas ORDER BY nombre_aula ASC");
$aulas = $stmt_aulas->fetchAll();

// Obtener mensajes flash para JavaScript
$flash_messages = get_flash_messages();

?>

<h1 class="mt-4">Gestión de Aulas</h1>
<p class="lead">Administra las aulas disponibles en la universidad.</p>

<div class="mb-3 d-flex justify-content-between align-items-center">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#classroomModal" id="addClassroomBtn">
        <i class="fas fa-plus-circle me-2"></i>Añadir Nueva Aula
    </button>
    <div class="col-md-4">
        <input type="search" class="form-control" id="searchInput" placeholder="Buscar aula...">
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0">Lista de Aulas</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped" id="aulasTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre Aula</th>
                        <th>Capacidad</th>
                        <th>Ubicación</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($aulas) > 0): ?>
                        <?php foreach ($aulas as $aula): ?>
                            <tr data-id="<?php echo htmlspecialchars($aula['id']); ?>"
                                data-nombre_aula="<?php echo htmlspecialchars($aula['nombre_aula']); ?>"
                                data-capacidad="<?php echo htmlspecialchars($aula['capacidad']); ?>"
                                data-ubicacion="<?php echo htmlspecialchars($aula['ubicacion']); ?>">
                                <td><?php echo htmlspecialchars($aula['id']); ?></td>
                                <td><?php echo htmlspecialchars($aula['nombre_aula']); ?></td>
                                <td><?php echo htmlspecialchars($aula['capacidad']); ?></td>
                                <td><?php echo htmlspecialchars($aula['ubicacion']); ?></td>
                                <td>
                                    <button type="button" class="btn btn-warning btn-sm edit-btn me-1" title="Editar" data-bs-toggle="modal" data-bs-target="#classroomModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form action="aulas.php" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de que quieres eliminar esta aula? Si está asignada a horarios, esto podría causar problemas.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($aula['id']); ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Eliminar">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">No hay aulas registradas.</td>
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

<div class="modal fade" id="classroomModal" tabindex="-1" aria-labelledby="classroomModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <form id="classroomForm" action="aulas.php" method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="classroomModalLabel">Añadir Nueva Aula</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="classroomId">

                    <div class="mb-3">
                        <label for="nombre_aula" class="form-label">Nombre del Aula <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombre_aula" name="nombre_aula" required>
                        <small class="form-text text-muted">Ej: Aula 101, Laboratorio B</small>
                    </div>
                    <div class="mb-3">
                        <label for="capacidad" class="form-label">Capacidad <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="capacidad" name="capacidad" min="1" required>
                        <small class="form-text text-muted">Número máximo de estudiantes.</small>
                    </div>
                    <div class="mb-3">
                        <label for="ubicacion" class="form-label">Ubicación <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="ubicacion" name="ubicacion" required>
                        <small class="form-text text-muted">Ej: Edificio C, Planta Baja</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cerrar
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save me-1"></i> Añadir Aula
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

    // Lógica para abrir modal de "Añadir Nueva Aula"
    document.getElementById('addClassroomBtn').addEventListener('click', function() {
        document.getElementById('classroomModalLabel').innerText = 'Añadir Nueva Aula';
        document.getElementById('formAction').value = 'add';
        document.getElementById('classroomId').value = '';
        document.getElementById('classroomForm').reset();
        document.getElementById('submitBtn').innerText = 'Añadir Aula';
    });

    // Lógica para abrir modal de "Editar Aula"
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('classroomModalLabel').innerText = 'Editar Aula';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('submitBtn').innerText = 'Guardar Cambios';

            const row = this.closest('tr');
            document.getElementById('classroomId').value = row.dataset.id;
            document.getElementById('nombre_aula').value = row.dataset.nombre_aula;
            document.getElementById('capacidad').value = row.dataset.capacidad;
            document.getElementById('ubicacion').value = row.dataset.ubicacion;
        });
    });

    // --- Búsqueda dinámica ---
    document.getElementById('searchInput').addEventListener('keyup', function() {
        var input, filter, table, tr, td, i, j, txtValue;
        input = document.getElementById("searchInput");
        filter = input.value.toUpperCase();
        table = document.getElementById("aulasTable");
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
        const table = document.getElementById('aulasTable');
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
        const table = document.getElementById('aulasTable');
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