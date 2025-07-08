<?php
require_once '../includes/functions.php';

check_login_and_role('Administrador');

require_once '../config/database.php';

$page_title = "Gestión de Años Académicos";
include_once '../includes/header.php';

 

// --- Obtener todos los años académicos para la tabla ---
// [CAMBIO] Seleccionar también el campo 'estado'
$stmt_anios_academicos = $pdo->query("SELECT id, nombre_anio, fecha_inicio, fecha_fin, estado FROM anios_academicos ORDER BY nombre_anio DESC");
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
                        <th>Estado</th> <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($anios_academicos) > 0): ?>
                        <?php foreach ($anios_academicos as $anio): ?>
                            <tr data-id="<?php echo htmlspecialchars($anio['id']); ?>"
                                data-nombre_anio="<?php echo htmlspecialchars($anio['nombre_anio']); ?>"
                                data-fecha_inicio="<?php echo htmlspecialchars($anio['fecha_inicio']); ?>"
                                data-fecha_fin="<?php echo htmlspecialchars($anio['fecha_fin']); ?>"
                                data-estado="<?php echo htmlspecialchars($anio['estado']); ?>"> <td><?php echo htmlspecialchars($anio['id']); ?></td>
                                <td><?php echo htmlspecialchars($anio['nombre_anio']); ?></td>
                                <td><?php echo htmlspecialchars($anio['fecha_inicio']); ?></td>
                                <td><?php echo htmlspecialchars($anio['fecha_fin']); ?></td>
                                <td>
                                    <?php
                                        // [CAMBIO] Mostrar el estado con un badge de Bootstrap
                                        $badge_class = '';
                                        switch ($anio['estado']) {
                                            case 'Activo': $badge_class = 'bg-success'; break;
                                            case 'Inactivo': $badge_class = 'bg-warning text-dark'; break;
                                            case 'Cerrado': $badge_class = 'bg-danger'; break;
                                            case 'Futuro': $badge_class = 'bg-info'; break;
                                            default: $badge_class = 'bg-secondary'; break;
                                        }
                                    ?>
                                    <span class="badge <?= $badge_class ?>"><?= htmlspecialchars($anio['estado']) ?></span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-warning btn-sm edit-btn me-1" title="Editar" data-bs-toggle="modal" data-bs-target="#yearModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form action="anios_academicos.php" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de que quieres eliminar este año académico? Esto también podría afectar semestres y datos de estudiantes asociados.');">
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
                            <td colspan="6" class="text-center">No hay años académicos registrados.</td> </tr>
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
            <form id="yearForm" action="../api/guardar_anios.php" method="POST">
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
                    <div class="mb-3">
                        <label for="estado" class="form-label">Estado del Año <span class="text-danger">*</span></label>
                        <select class="form-select" id="estado" name="estado" required>
                            <option value="Futuro">Futuro</option>
                            <option value="Activo">Activo</option>
                            <option value="Inactivo">Inactivo</option>
                            <option value="Cerrado">Cerrado</option>
                        </select>
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
        // [CAMBIO] Establecer un estado por defecto si se añade un año
        document.getElementById('estado').value = 'Futuro'; 
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
            // [CAMBIO] Cargar el estado del año académico al editar
            document.getElementById('estado').value = row.dataset.estado; 
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
            // [CAMBIO] El índice de td cambia porque añadimos una columna de estado
            // Ahora hay 6 columnas: ID, Año, F. Inicio, F. Fin, Estado, Acciones
            // Buscar en las columnas 1 (Año Académico) y 4 (Estado)
            if (td[1] || td[4]) { 
                txtValue = (td[1] ? td[1].textContent || td[1].innerText : '') + ' ' +
                           (td[4] ? td[4].textContent || td[4].innerText : ''); // [CAMBIO] Concatenar texto del estado
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                    break;
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