<?php
require_once '../includes/functions.php'; 
check_login_and_role('Administrador');

require_once '../config/database.php';

$page_title = "Gestión de Asignaturas";
include_once '../includes/header.php';

// Los mensajes flash se manejarán principalmente con JavaScript ahora
$flash_messages = get_flash_messages();

// Procesar adición/edición (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_asignatura = trim($_POST['nombre_asignatura'] ?? '');
    $creditos = filter_var($_POST['creditos'] ?? 0, FILTER_VALIDATE_FLOAT);
    $id_prerequisito = filter_var($_POST['id_prerequisito'] ?? null, FILTER_VALIDATE_INT);
    $id_curso = filter_var($_POST['id_curso'] ?? null, FILTER_VALIDATE_INT);
    $semestre_recomendado = filter_var($_POST['semestre_recomendado'] ?? null, FILTER_VALIDATE_INT);

    // **Validaciones Iniciales**
    if (empty($nombre_asignatura)) {
        set_flash_message('danger', 'Error: El nombre de la asignatura es obligatorio.');
    } elseif ($creditos === false || $creditos <= 0) {
        set_flash_message('danger', 'Error: Los créditos son obligatorios y deben ser un número positivo.');
    } elseif ($id_curso === null || $id_curso <= 0) {
        set_flash_message('danger', 'Error: Debe seleccionar un curso para la asignatura.');
    } elseif ($semestre_recomendado === null || $semestre_recomendado <= 0) {
        set_flash_message('danger', 'Error: El semestre recomendado es obligatorio y debe ser un número positivo.');
    } else {
        // Lógica para determinar si es edición o adición
        if (isset($_POST['id_asignatura']) && $_POST['id_asignatura'] !== '') {
            // EDICIÓN de Asignatura
            $id_asignatura = filter_var($_POST['id_asignatura'], FILTER_VALIDATE_INT);

            if ($id_asignatura === false || $id_asignatura <= 0) {
                set_flash_message('danger', 'Error: ID de asignatura inválido para la edición.');
            } elseif ($id_prerequisito !== null && $id_prerequisito == $id_asignatura) {
                set_flash_message('danger', 'Error: Una asignatura no puede ser su propio prerrequisito.');
            } else {
                try {
                    // **Verificación de unicidad personalizada para ACTUALIZACIONES**
                    // Busca si ya existe OTRA asignatura con el mismo nombre (excluyendo la que estamos editando)
                    $stmt_check_name = $pdo->prepare("SELECT COUNT(*) FROM asignaturas WHERE nombre_asignatura = :nombre AND id != :id_actual");
                    $stmt_check_name->bindParam(':nombre', $nombre_asignatura);
                    $stmt_check_name->bindParam(':id_actual', $id_asignatura, PDO::PARAM_INT);
                    $stmt_check_name->execute();

                    if ($stmt_check_name->fetchColumn() > 0) {
                        set_flash_message('danger', 'Error: Ya existe otra asignatura con ese nombre.');
                    } else {
                        // Proceder con la actualización si el nombre es único o es el mismo registro
                        $stmt = $pdo->prepare("UPDATE asignaturas SET nombre_asignatura = :nombre, creditos = :creditos, id_prerequisito = :prerequisito, id_curso = :id_curso, semestre_recomendado = :semestre_recomendado WHERE id = :id");
                        $stmt->bindParam(':nombre', $nombre_asignatura);
                        $stmt->bindParam(':creditos', $creditos);
                        // Convertir a NULL si id_prerequisito es 0 o null (si el select está vacío)
                       // Convertir a NULL si id_prerequisito es 0 o null (si el select está vacío)
                    $prerequisito_final = ($id_prerequisito == 0 || $id_prerequisito === null) ? null : $id_prerequisito;
                    $stmt->bindParam(':prerequisito', $prerequisito_final, PDO::PARAM_INT); // Pasa la variable temporal
                    $stmt->bindParam(':id_curso', $id_curso, PDO::PARAM_INT);
                    $stmt->bindParam(':semestre_recomendado', $semestre_recomendado, PDO::PARAM_INT);
                        $stmt->bindParam(':id', $id_asignatura, PDO::PARAM_INT);

                        if ($stmt->execute()) {
                            set_flash_message('success', 'Asignatura actualizada exitosamente.');
                        } else {
                            set_flash_message('danger', 'Error al actualizar la asignatura.');
                        }
                    }
                } catch (PDOException $e) {
                    set_flash_message('danger', 'Error de base de datos al actualizar: ' . $e->getMessage());
                }
            }
        } else {
            // AÑADIR Nueva Asignatura
            try {
                // **Verificación de unicidad personalizada para AÑADIR**
                // Busca si ya existe alguna asignatura con el mismo nombre
                $stmt_check_name = $pdo->prepare("SELECT COUNT(*) FROM asignaturas WHERE nombre_asignatura = :nombre");
                $stmt_check_name->bindParam(':nombre', $nombre_asignatura);
                $stmt_check_name->execute();

                if ($stmt_check_name->fetchColumn() > 0) {
                    set_flash_message('danger', 'Error: Ya existe una asignatura con ese nombre.');
                } else {
                    // Proceder con la inserción si el nombre es único
                    $stmt = $pdo->prepare("INSERT INTO asignaturas (nombre_asignatura, creditos, id_prerequisito, id_curso, semestre_recomendado) VALUES (:nombre, :creditos, :prerequisito, :id_curso, :semestre_recomendado)");
                    $stmt->bindParam(':nombre', $nombre_asignatura);
                    $stmt->bindParam(':creditos', $creditos);
                    // Convertir a NULL si id_prerequisito es 0 o null (si el select está vacío)
                   // Convertir a NULL si id_prerequisito es 0 o null (si el select está vacío)
                   $prerequisito_final = ($id_prerequisito == 0 || $id_prerequisito === null) ? null : $id_prerequisito;
                   $stmt->bindParam(':prerequisito', $prerequisito_final, PDO::PARAM_INT); // Pasa la variable temporal
                   $stmt->bindParam(':id_curso', $id_curso, PDO::PARAM_INT);
                   $stmt->bindParam(':semestre_recomendado', $semestre_recomendado, PDO::PARAM_INT);
                    
                    if ($stmt->execute()) {
                        set_flash_message('success', 'Asignatura añadida exitosamente.');
                    } else {
                        set_flash_message('danger', 'Error al añadir la asignatura.');
                    }
                }
            } catch (PDOException $e) {
                set_flash_message('danger', 'Error de base de datos al añadir: ' . $e->getMessage());
            }
        }
    }
   
}

// Procesar eliminación (GET request)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id_asignatura = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($id_asignatura === false || $id_asignatura <= 0) {
        set_flash_message('danger', 'ID de asignatura inválido para eliminar.');
    } else {
        try {
            // **Validaciones antes de eliminar (Cruciales)**

            // 1. Verificar si es prerrequisito de alguna otra asignatura
            $stmt_check_prereq = $pdo->prepare("SELECT COUNT(*) FROM asignaturas WHERE id_prerequisito = :id");
            $stmt_check_prereq->bindParam(':id', $id_asignatura, PDO::PARAM_INT);
            $stmt_check_prereq->execute();
            if ($stmt_check_prereq->fetchColumn() > 0) {
                set_flash_message('danger', 'No se puede eliminar la asignatura porque es prerrequisito de otra(s) asignatura(s).');
            } else {
                // 2. Verificar si está en algún horario (horarios activos o pasados)
                $stmt_check_horario = $pdo->prepare("SELECT COUNT(*) FROM horarios WHERE id_asignatura = :id");
                $stmt_check_horario->bindParam(':id', $id_asignatura, PDO::PARAM_INT);
                $stmt_check_horario->execute();
                if ($stmt_check_horario->fetchColumn() > 0) {
                    set_flash_message('danger', 'No se puede eliminar la asignatura porque está asociada a horarios existentes. Elimine los horarios primero.');
                } else {
                    // 3. Verificar si está en el historial académico de algún estudiante
                    $stmt_check_historial = $pdo->prepare("SELECT COUNT(*) FROM historial_academico WHERE id_asignatura = :id");
                    $stmt_check_historial->bindParam(':id', $id_asignatura, PDO::PARAM_INT);
                    $stmt_check_historial->execute();
                    if ($stmt_check_historial->fetchColumn() > 0) {
                        set_flash_message('danger', 'No se puede eliminar la asignatura porque está registrada en el historial académico de estudiantes.');
                    } else {
                        // Si pasa todas las validaciones, proceder con la eliminación
                        $stmt_delete = $pdo->prepare("DELETE FROM asignaturas WHERE id = :id");
                        $stmt_delete->bindParam(':id', $id_asignatura, PDO::PARAM_INT);
                        if ($stmt_delete->execute()) {
                            set_flash_message('success', 'Asignatura eliminada exitosamente.');
                        } else {
                            set_flash_message('danger', 'Error al eliminar la asignatura.');
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            set_flash_message('danger', 'Error de base de datos al eliminar: ' . $e->getMessage());
        }
    }
     
}

// Obtener todas las asignaturas para el listado
$stmt_asignaturas = $pdo->query("SELECT a.id, a.nombre_asignatura, a.creditos,
                                 pa.nombre_asignatura AS prerequisito_nombre,
                                 c.nombre_curso, a.semestre_recomendado,
                                 a.id_prerequisito, a.id_curso
                                 FROM asignaturas a
                                 LEFT JOIN asignaturas pa ON a.id_prerequisito = pa.id
                                 LEFT JOIN cursos c ON a.id_curso = c.id
                                 ORDER BY a.nombre_asignatura ASC");
$asignaturas = $stmt_asignaturas->fetchAll(PDO::FETCH_ASSOC);

// Obtener todas las asignaturas para el select de prerrequisitos (sin sí mismas)
$stmt_all_asignaturas = $pdo->query("SELECT id, nombre_asignatura FROM asignaturas ORDER BY nombre_asignatura ASC");
$all_asignaturas = $stmt_all_asignaturas->fetchAll(PDO::FETCH_ASSOC);

// Obtener todos los cursos para el select de cursos
$stmt_cursos = $pdo->query("SELECT id, nombre_curso FROM cursos ORDER BY nombre_curso ASC");
$cursos_list = $stmt_cursos->fetchAll(PDO::FETCH_ASSOC);

?>

<h1 class="mt-4">Gestión de Asignaturas</h1>
<p class="lead">Gestiona las asignaturas, sus créditos, prerrequisitos, y el curso y semestre al que pertenecen.</p>

<div class="d-flex justify-content-between mb-3 align-items-center">
    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#subjectModal" id="addNewSubjectBtn">
        <i class="fas fa-plus me-2"></i> Nueva Asignatura
    </button>
    <div class="col-md-4">
        <input type="search" class="form-control" id="searchInput" placeholder="Buscar asignatura...">
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Lista de Asignaturas</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped" id="asignaturasTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Créditos</th>
                        <th>Prerrequisito</th>
                        <th>Curso</th>
                        <th>Semestre</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($asignaturas) > 0): ?>
                        <?php foreach ($asignaturas as $asignatura): ?>
                            <tr data-id="<?php echo htmlspecialchars($asignatura['id']); ?>"
                                data-nombre_asignatura="<?php echo htmlspecialchars($asignatura['nombre_asignatura']); ?>"
                                data-creditos="<?php echo htmlspecialchars($asignatura['creditos']); ?>"
                                data-id_prerequisito="<?php echo htmlspecialchars($asignatura['id_prerequisito']); ?>"
                                data-id_curso="<?php echo htmlspecialchars($asignatura['id']); ?>"
                                data-semestre_recomendado="<?php echo htmlspecialchars($asignatura['semestre_recomendado']); ?>">
                                <td><?php echo htmlspecialchars($asignatura['id']); ?></td>
                                <td><?php echo htmlspecialchars($asignatura['nombre_asignatura']); ?></td>
                                <td><?php echo htmlspecialchars($asignatura['creditos']); ?></td>
                                <td><?php echo htmlspecialchars($asignatura['prerequisito_nombre'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($asignatura['nombre_curso'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($asignatura['semestre_recomendado'] ?? '-'); ?></td>
                                <td>
                                    <button type="button" class="btn btn-warning btn-sm edit-btn me-1" title="Editar"
                                            data-bs-toggle="modal" data-bs-target="#subjectModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="asignaturas.php?action=delete&id=<?php echo htmlspecialchars($asignatura['id']); ?>"
                                       class="btn btn-danger btn-sm delete-btn" title="Eliminar"
                                       onclick="return confirm('¿Estás seguro de que quieres eliminar esta asignatura? Esta acción es irreversible y podría afectar otros registros.');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No hay asignaturas registradas.</td>
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

<div class="modal fade" id="subjectModal" tabindex="-1" aria-labelledby="subjectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="asignaturas.php" method="POST" id="subjectForm">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="subjectModalLabel">Añadir Nueva Asignatura</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_asignatura" id="modal_id_asignatura">

                    <div class="mb-3">
                        <label for="modal_nombre_asignatura" class="form-label">Nombre de la Asignatura</label>
                        <input type="text" class="form-control" id="modal_nombre_asignatura" name="nombre_asignatura" required>
                    </div>

                    <div class="mb-3">
                        <label for="modal_creditos" class="form-label">Créditos</label>
                        <input type="number" step="0.01" class="form-control" id="modal_creditos" name="creditos" min="0.5" required>
                    </div>

                    <div class="mb-3">
                        <label for="modal_id_prerequisito" class="form-label">Prerrequisito (Opcional)</label>
                        <select class="form-select" id="modal_id_prerequisito" name="id_prerequisito">
                            <option value="">Ninguno</option>
                            <?php foreach ($all_asignaturas as $asignatura_option): ?>
                                <option value="<?php echo htmlspecialchars($asignatura_option['id']); ?>">
                                    <?php echo htmlspecialchars($asignatura_option['nombre_asignatura']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="modal_id_curso" class="form-label">Curso al que Pertenece</label>
                        <select class="form-select" id="modal_id_curso" name="id_curso" required>
                            <option value="">Seleccione un curso...</option>
                            <?php foreach ($cursos_list as $curso_option): ?>
                                <option value="<?php echo htmlspecialchars($curso_option['id']); ?>">
                                    <?php echo htmlspecialchars($curso_option['nombre_curso']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="modal_semestre_recomendado" class="form-label">Semestre Recomendado (dentro del Curso)</label>
                        <input type="number" class="form-control" id="modal_semestre_recomendado" name="semestre_recomendado" min="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i> Cerrar</button>
                    <button type="submit" class="btn btn-primary" id="modalSaveBtn"><i class="fas fa-save me-2"></i> Guardar Asignatura</button>
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
    const subjectModal = new bootstrap.Modal(document.getElementById('subjectModal'));

    // --- Funcionalidad para botón "Nueva Asignatura" ---
    document.getElementById('addNewSubjectBtn').addEventListener('click', function() {
        // Limpiar formulario y establecer título para "Añadir"
        document.getElementById('subjectForm').reset();
        document.getElementById('modal_id_asignatura').value = ''; // Asegurar que el ID esté vacío para una nueva asignatura
        document.getElementById('subjectModalLabel').innerText = 'Añadir Nueva Asignatura';
        document.getElementById('modalSaveBtn').innerText = 'Guardar Asignatura';
        document.getElementById('modalSaveBtn').classList.remove('btn-warning');
        document.getElementById('modalSaveBtn').classList.add('btn-primary');
    });

    // --- Funcionalidad para botón "Editar" ---
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const row = this.closest('tr');
            document.getElementById('modal_id_asignatura').value = row.dataset.id;
            document.getElementById('modal_nombre_asignatura').value = row.dataset.nombre_asignatura;
            document.getElementById('modal_creditos').value = row.dataset.creditos;
            document.getElementById('modal_id_prerequisito').value = row.dataset.id_prerequisito;
            document.getElementById('modal_id_curso').value = row.dataset.id_curso;
            document.getElementById('modal_semestre_recomendado').value = row.dataset.semestre_recomendado;

            // Cambiar título y texto del botón para "Editar"
            document.getElementById('subjectModalLabel').innerText = 'Editar Asignatura';
            document.getElementById('modalSaveBtn').innerText = 'Actualizar Asignatura';
            document.getElementById('modalSaveBtn').classList.remove('btn-primary');
            document.getElementById('modalSaveBtn').classList.add('btn-warning');
        });
    });

    // --- Búsqueda dinámica ---
    document.getElementById('searchInput').addEventListener('keyup', function() {
        var input, filter, table, tr, td, i, j, txtValue;
        input = document.getElementById("searchInput");
        filter = input.value.toUpperCase();
        table = document.getElementById("asignaturasTable"); // Asegúrate que el ID de la tabla coincida
        tr = table.getElementsByTagName("tr");

        document.getElementById('pagination').style.display = 'none'; // Ocultar paginación al buscar

        for (i = 1; i < tr.length; i++) { // Ignorar la fila de encabezado
            tr[i].style.display = "none";
            td = tr[i].getElementsByTagName("td");
            // Buscar en todas las columnas excepto la última (acciones)
            for (j = 0; j < td.length - 1; j++) {
                if (td[j]) {
                    txtValue = td[j].textContent || td[j].innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                        break;
                    }
                }
            }
        }
        if (filter === "") { // Volver a mostrar paginación si el campo de búsqueda está vacío
            document.getElementById('pagination').style.display = 'flex';
            showPage(currentPage);
        }
    });

    // --- Paginación ---
    const rowsPerPage = 10;
    let currentPage = 1;
    let totalPages = 0;

    function setupPagination() {
        const table = document.getElementById('asignaturasTable'); // Asegúrate que el ID de la tabla coincida
        const tbodyRows = table.querySelectorAll('tbody tr');
        totalPages = Math.ceil(tbodyRows.length / rowsPerPage);

        const paginationUl = document.getElementById('pagination');
        paginationUl.innerHTML = ''; // Limpiar paginación existente

        if (tbodyRows.length <= rowsPerPage && document.getElementById('searchInput').value === "") {
            paginationUl.style.display = 'none'; // Ocultar si no se necesita paginación y no hay búsqueda activa
            tbodyRows.forEach(row => row.style.display = ''); // Asegurar que todas las filas sean visibles
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
        const table = document.getElementById('asignaturasTable'); // Asegúrate que el ID de la tabla coincida
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

    // Función para mostrar un Toast de Bootstrap
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

    // Inicializar la paginación y mostrar los toasts al cargar
    document.addEventListener('DOMContentLoaded', function() {
        setupPagination();
        showPage(currentPage);

        flashMessages.forEach(msg => {
            showToast(msg.type, msg.message);
        });
    });

</script>