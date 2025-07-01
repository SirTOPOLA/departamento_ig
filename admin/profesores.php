<?php
// professors.php
require_once '../includes/functions.php'; // Asegúrate de que esta ruta sea correcta
check_login_and_role('Administrador'); // Solo administradores pueden acceder

require_once '../config/database.php'; // Asegúrate de que esta ruta sea correcta

$page_title = "Gestión de Profesores";
include_once '../includes/header.php'; // Asegúrate de que esta ruta sea correcta

// Obtener y limpiar mensajes flash para su uso en JavaScript
$flash_messages = get_flash_messages();

// --- Lógica para eliminar profesor ---
// Esta sección se ejecuta cuando se recibe una solicitud GET con 'action=delete' y 'id'
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    // El ID recibido de $_GET['id'] debe ser el ID de la tabla 'profesores'
    $profesor_id_from_url = filter_var($_GET['id'], FILTER_VALIDATE_INT);

    if ($profesor_id_from_url === false || $profesor_id_from_url <= 0) {
        set_flash_message('danger', 'ID de profesor inválido para eliminar.');
    } else {
        try {
            $pdo->beginTransaction(); // Iniciar transacción para asegurar atomicidad de las operaciones

            // 1. Obtener los IDs (profesor y usuario) y confirmar el rol antes de proceder
            $stmt_check_profesor = $pdo->prepare("
                SELECT
                    p.id AS profesor_id,        -- ID de la tabla profesores
                    u.id AS user_id,            -- ID de la tabla usuarios asociado
                    r.nombre_rol
                FROM profesores p
                JOIN usuarios u ON p.id_usuario = u.id
                JOIN roles r ON u.id_rol = r.id
                WHERE p.id = :profesor_id_from_url
                AND r.nombre_rol = 'Profesor' -- Aseguramos que el registro sea de un profesor
            ");
            $stmt_check_profesor->bindParam(':profesor_id_from_url', $profesor_id_from_url, PDO::PARAM_INT);
            $stmt_check_profesor->execute();
            $profesor_info = $stmt_check_profesor->fetch(PDO::FETCH_ASSOC);

            if (!$profesor_info) {
                // Si no se encuentra el profesor o no tiene el rol 'Profesor'
                set_flash_message('danger', 'Error: El ID proporcionado no corresponde a un profesor válido o no existe.');
                $pdo->rollBack(); // Revertir si no es un profesor válido
            } else {
                $actual_profesor_id = $profesor_info['profesor_id']; // El ID de la tabla 'profesores'
                $associated_user_id = $profesor_info['user_id'];   // El ID de la tabla 'usuarios'

                // 2. Verificar si tiene horarios asignados (referencia: `id_profesor` en `horarios` es `profesores.id`)
                $stmt_check_horarios = $pdo->prepare("SELECT COUNT(*) FROM horarios WHERE id_profesor = :profesor_id");
                $stmt_check_horarios->bindParam(':profesor_id', $actual_profesor_id, PDO::PARAM_INT);
                $stmt_check_horarios->execute();

                if ($stmt_check_horarios->fetchColumn() > 0) {
                    set_flash_message('danger', 'No se puede eliminar al profesor porque tiene horarios de clases asignados. Elimina sus horarios primero.');
                    $pdo->rollBack(); // Revertir si tiene horarios
                } else {
                    // 3. Eliminar CVs asociados (referencia: `id_profesor` en `cvs_profesores` es `profesores.id`)
                    $stmt_delete_cvs = $pdo->prepare("DELETE FROM cvs_profesores WHERE id_profesor = :profesor_id");
                    $stmt_delete_cvs->bindParam(':profesor_id', $actual_profesor_id, PDO::PARAM_INT);
                    $stmt_delete_cvs->execute();

                    // 4. Eliminar asignaturas sugeridas asociadas (referencia: `id_profesor` en `profesores_asignaturas_sugeridas` es `profesores.id`)
                    $stmt_delete_sugeridas = $pdo->prepare("DELETE FROM profesores_asignaturas_sugeridas WHERE id_profesor = :profesor_id");
                    $stmt_delete_sugeridas->bindParam(':profesor_id', $actual_profesor_id, PDO::PARAM_INT);
                    $stmt_delete_sugeridas->execute();
                    
                    // 5. Eliminar asignaturas asignadas asociadas (referencia: `id_profesor` en `profesores_asignaturas_asignadas` es `profesores.id`)
                    $stmt_delete_asignadas = $pdo->prepare("DELETE FROM profesores_asignaturas_asignadas WHERE id_profesor = :profesor_id");
                    $stmt_delete_asignadas->bindParam(':profesor_id', $actual_profesor_id, PDO::PARAM_INT);
                    $stmt_delete_asignadas->execute();

                    // 6. Eliminar el registro del profesor de la tabla 'profesores'
                    $stmt_delete_from_profesores_table = $pdo->prepare("DELETE FROM profesores WHERE id = :profesor_id");
                    $stmt_delete_from_profesores_table->bindParam(':profesor_id', $actual_profesor_id, PDO::PARAM_INT);
                    $stmt_delete_from_profesores_table->execute();

                    // 7. Finalmente, eliminar el usuario (profesor) de la tabla 'usuarios'
                    // Esto se hace al final para evitar problemas de FK antes de eliminar el registro de profesores.
                    $stmt_delete_user = $pdo->prepare("DELETE FROM usuarios WHERE id = :user_id");
                    $stmt_delete_user->bindParam(':user_id', $associated_user_id, PDO::PARAM_INT);

                    if ($stmt_delete_user->execute()) {
                        set_flash_message('success', 'Profesor y todos sus datos relacionados eliminados exitosamente.');
                        $pdo->commit(); // Confirmar la transacción
                    } else {
                        // Si la eliminación del usuario falla por alguna razón inesperada
                        set_flash_message('danger', 'Error al eliminar el usuario asociado al profesor. La operación fue revertida.');
                        $pdo->rollBack(); // Revertir si falla la eliminación principal
                    }
                }
            }
        } catch (PDOException $e) {
            $pdo->rollBack(); // Revertir en caso de cualquier excepción de PDO
            set_flash_message('danger', 'Error de base de datos al intentar eliminar el profesor: ' . $e->getMessage());
            // En un entorno de producción, es crucial loggear estos errores en un archivo.
            error_log("Error al eliminar profesor: " . $e->getMessage()); 
        }
    }
    // Siempre redirigir después de una operación POST/GET de eliminación para evitar reenvíos
    header('Location: professors.php');
    exit();
}

// --- Obtener la lista de profesores (usuarios con rol 'Profesor') ---
// Esta consulta recupera los datos de los profesores para mostrarlos en la tabla
try {
    $stmt_profesores = $pdo->query("
        SELECT
            u.id AS user_id,        -- ID de la tabla usuarios
            u.nombre_usuario,
            u.nombre_completo,
            u.email,
            u.telefono,
            u.estado,
            p.id AS profesor_id,    -- ID de la tabla profesores (¡Importante para las acciones de eliminar/detalles!)
            p.especialidad,
            p.grado_academico,
            (SELECT COUNT(*) FROM cvs_profesores WHERE id_profesor = p.id) AS total_cvs, -- Conteo de CVs usando p.id
            (SELECT COUNT(*) FROM profesores_asignaturas_asignadas WHERE id_profesor = p.id) AS total_asignaturas_asignadas
        FROM usuarios u
        JOIN roles r ON u.id_rol = r.id
        JOIN profesores p ON u.id = p.id_usuario
        WHERE r.nombre_rol = 'Profesor'
        ORDER BY u.nombre_completo ASC
    ");
    $profesores = $stmt_profesores->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    set_flash_message('danger', 'Error al cargar la lista de profesores: ' . $e->getMessage());
    $profesores = []; // Asegurar que $profesores sea un array vacío en caso de error
    error_log("Error al cargar lista de profesores: " . $e->getMessage());
}

// Obtener todas las asignaturas para el select en el modal de asignación
try {
    $stmt_asignaturas = $pdo->query("SELECT id, nombre_asignatura FROM asignaturas ORDER BY nombre_asignatura ASC");
    $asignaturas_disponibles = $stmt_asignaturas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    set_flash_message('danger', 'Error al cargar asignaturas disponibles: ' . $e->getMessage());
    $asignaturas_disponibles = [];
    error_log("Error al cargar asignaturas disponibles: " . $e->getMessage());
}
?>

<h1 class="mt-4">Gestión de Profesores</h1>
<p class="lead">Gestiona la información de los profesores, sus CVs y asigna asignaturas.</p>

<div class="d-flex justify-content-end mb-3 align-items-center">
    <div class="col-md-4">
        <input type="search" class="form-control" id="searchInput" placeholder="Buscar profesor por nombre o email...">
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Lista de Profesores Registrados</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped" id="professorsTable">
                <thead>
                    <tr>
                        <th>ID Profesor</th>
                        <th>Nombre Completo</th>
                        <th>Nombre de Usuario</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Estado</th>
                        <th>CVs</th>
                        <th>Asignaturas Asignadas</th> <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($profesores) > 0): ?>
                        <?php foreach ($profesores as $profesor): ?>
                            <tr data-profesor_id="<?php echo htmlspecialchars($profesor['profesor_id']); ?>">
                                <td><?php echo htmlspecialchars($profesor['profesor_id']); ?></td>
                                <td><?php echo htmlspecialchars($profesor['nombre_completo']); ?></td>
                                <td><?php echo htmlspecialchars($profesor['nombre_usuario']); ?></td>
                                <td><?php echo htmlspecialchars($profesor['email'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($profesor['telefono'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge <?php 
                                        echo ($profesor['estado'] == 'Activo' ? 'bg-success' : 
                                            ($profesor['estado'] == 'Inactivo' ? 'bg-warning text-dark' : 'bg-danger')); ?>">
                                        <?php echo htmlspecialchars($profesor['estado']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($profesor['total_cvs']); ?></td>
                                <td>
                                    <button type="button" class="btn btn-primary btn-sm view-assigned-subjects-btn"
                                            title="Ver Asignaturas Asignadas" data-bs-toggle="modal"
                                            data-bs-target="#professorDetailsModal"
                                            data-profesor_id="<?php echo htmlspecialchars($profesor['profesor_id']); ?>"
                                            data-nombre_completo="<?php echo htmlspecialchars($profesor['nombre_completo']); ?>">
                                        <i class="fas fa-book"></i> <?php echo htmlspecialchars($profesor['total_asignaturas_asignadas']); ?>
                                    </button>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-info btn-sm view-details-btn me-1"
                                            title="Ver Detalles y Gestionar" data-bs-toggle="modal"
                                            data-bs-target="#professorDetailsModal"
                                            data-profesor_id="<?php echo htmlspecialchars($profesor['profesor_id']); ?>"
                                            data-nombre_completo="<?php echo htmlspecialchars($profesor['nombre_completo']); ?>">
                                        <i class="fas fa-eye"></i> Detalles
                                    </button>
                                    <a href="professors.php?action=delete&id=<?php echo htmlspecialchars($profesor['profesor_id']); ?>"
                                        class="btn btn-danger btn-sm delete-btn" title="Eliminar Profesor"
                                        onclick="return confirm('¿Estás seguro de que quieres eliminar a este profesor y todos sus datos asociados (CVs, sugerencias, asignaturas asignadas)? Esta acción es irreversible y solo se permitirá si no tiene horarios asignados.');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center">No hay profesores registrados.</td>
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

<div class="modal fade" id="professorDetailsModal" tabindex="-1" aria-labelledby="professorDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="professorDetailsModalLabel">Detalles del Profesor: <span id="profNameDisplay"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="current_profesor_id">

                <ul class="nav nav-tabs mb-3" id="profDetailsTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="cv-tab" data-bs-toggle="tab" data-bs-target="#cv-content"
                            type="button" role="tab" aria-controls="cv-content" aria-selected="true">Gestión de CVs</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="assignments-tab" data-bs-toggle="tab"
                            data-bs-target="#assignments-content" type="button" role="tab"
                            aria-controls="assignments-content" aria-selected="false">Asignar Asignaturas Sugeridas</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="assigned-subjects-tab" data-bs-toggle="tab"
                            data-bs-target="#assigned-subjects-content" type="button" role="tab"
                            aria-controls="assigned-subjects-content" aria-selected="false">Asignaturas Asignadas</button>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="cv-content" role="tabpanel" aria-labelledby="cv-tab">
                        <h6>Subir Nuevo CV</h6>
                        <form id="uploadCvForm" enctype="multipart/form-data" class="mb-4">
                            <input type="hidden" name="action" value="upload_cv">
                            <input type="hidden" name="id_profesor" id="cv_profesor_id"> <div class="input-group">
                                <input type="file" class="form-control" id="cvFile" name="cv_file"
                                    accept=".pdf,.doc,.docx" required>
                                <button class="btn btn-outline-secondary" type="submit"><i
                                        class="fas fa-upload me-2"></i> Subir CV</button>
                            </div>
                            <small class="form-text text-muted">Archivos permitidos: PDF, DOC, DOCX. Máx. 5MB.</small>
                        </form>

                        <h6>CVs Existentes</h6>
                        <div id="cvsList" class="list-group">
                            <p class="text-center text-muted">Cargando CVs...</p>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="assignments-content" role="tabpanel" aria-labelledby="assignments-tab">
                        <h6>Asignar Asignatura Sugerida al Profesor</h6>
                        <form id="assignSubjectForm" class="mb-4">
                            <input type="hidden" name="action" value="assign_subject_sugerida"> <input type="hidden" name="id_profesor" id="assign_profesor_id_sugerida"> <div class="input-group">
                                <select class="form-select" id="availableSubjectsSugeridas" name="id_asignatura" required>
                                    <option value="">Seleccione una asignatura...</option>
                                    <?php foreach ($asignaturas_disponibles as $asig): ?>
                                        <option value="<?php echo htmlspecialchars($asig['id']); ?>">
                                            <?php echo htmlspecialchars($asig['nombre_asignatura']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-plus me-2"></i> Asignar Sugerida</button>
                            </div>
                        </form>

                        <h6>Asignaturas Preferidas/Sugeridas por el Profesor (del CV o manual)</h6>
                        <div id="suggestedSubjectsList" class="list-group">
                            <p class="text-center text-muted">Cargando asignaturas sugeridas...</p>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="assigned-subjects-content" role="tabpanel" aria-labelledby="assigned-subjects-tab">
                        <h6>Asignar Nueva Asignatura (Oficial)</h6>
                        <form id="assignOfficialSubjectForm" class="mb-4">
                            <input type="hidden" name="action" value="assign_subject_oficial">
                            <input type="hidden" name="id_profesor" id="assign_profesor_id_oficial">
                            <div class="input-group">
                                <select class="form-select" id="availableSubjectsOficiales" name="id_asignatura" required>
                                    <option value="">Seleccione una asignatura...</option>
                                    <?php foreach ($asignaturas_disponibles as $asig): ?>
                                        <option value="<?php echo htmlspecialchars($asig['id']); ?>">
                                            <?php echo htmlspecialchars($asig['nombre_asignatura']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-success" type="submit"><i class="fas fa-check-circle me-2"></i> Asignar Oficialmente</button>
                            </div>
                        </form>

                        <h6>Asignaturas Oficialmente Asignadas al Profesor</h6>
                        <div id="assignedSubjectsList" class="list-group">
                            <p class="text-center text-muted">Cargando asignaturas asignadas...</p>
                        </div>
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

<?php include_once '../includes/footer.php'; ?>

<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100;"></div>

<script>
    // Función de escape HTML para evitar XSS en el cliente
    function escapeHtml(str) {
        if (typeof str !== 'string') {
            return ''; // Devuelve una cadena vacía si no es una cadena
        }
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // Mostrar un toast con mensajes
    function showToast(type, message) {
        const toastContainer = document.querySelector('.toast-container');
        const toastId = 'toast-' + Date.now();
        const toastHtml = `
            <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        toastContainer.insertAdjacentHTML('beforeend', toastHtml);
        const toastEl = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastEl);
        toast.show();
    }

    // Recuperar mensajes flash generados en PHP
    const flashMessages = <?php echo json_encode($flash_messages); ?>;
    flashMessages.forEach(msg => {
        showToast(msg.type, msg.message);
    });

    // --- Funcionalidad para el Modal de Detalles del Profesor ---
    document.querySelectorAll('.view-details-btn').forEach(button => {
        button.addEventListener('click', function () {
            const profesorId = this.dataset.profesor_id;
            const profesorNombre = this.dataset.nombre_completo;

            // Establecer IDs en los campos ocultos del modal para su uso en formularios AJAX
            document.getElementById('current_profesor_id').value = profesorId;
            document.getElementById('cv_profesor_id').value = profesorId;
            document.getElementById('assign_profesor_id_sugerida').value = profesorId; // ID para asignaturas sugeridas
            document.getElementById('assign_profesor_id_oficial').value = profesorId; // ID para asignaturas asignadas
            document.getElementById('profNameDisplay').innerText = profesorNombre;

            // Asegurarse de que la pestaña de CVs se muestre primero por defecto
            const cvTab = new bootstrap.Tab(document.getElementById('cv-tab'));
            cvTab.show();

            // Cargar CVs del profesor, asignaturas sugeridas y asignaturas asignadas
            loadProfessorCvs(profesorId);
            loadSuggestedSubjects(profesorId);
            loadAssignedSubjects(profesorId); // Nueva llamada
        });
    });

    // Nuevo Event Listener para el botón "Ver Asignaturas Asignadas"
    document.querySelectorAll('.view-assigned-subjects-btn').forEach(button => {
        button.addEventListener('click', function () {
            const profesorId = this.dataset.profesor_id;
            const profesorNombre = this.dataset.nombre_completo;

            document.getElementById('current_profesor_id').value = profesorId;
            document.getElementById('cv_profesor_id').value = profesorId;
            document.getElementById('assign_profesor_id_sugerida').value = profesorId;
            document.getElementById('assign_profesor_id_oficial').value = profesorId;
            document.getElementById('profNameDisplay').innerText = profesorNombre;

            // Activar la pestaña de "Asignaturas Asignadas"
            const assignedSubjectsTab = new bootstrap.Tab(document.getElementById('assigned-subjects-tab'));
            assignedSubjectsTab.show();

            // Cargar solo las asignaturas asignadas
            loadAssignedSubjects(profesorId);
        });
    });


    // --- Cargar CVs del Profesor vía AJAX ---
    function loadProfessorCvs(profesorId) {
        const cvsListDiv = document.getElementById('cvsList');
        cvsListDiv.innerHTML = '<p class="text-center text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Cargando CVs...</p>';

        fetch('../api/profesor.php?action=get_cvs&id_profesor=' + profesorId)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                cvsListDiv.innerHTML = ''; // Limpiar el contenido anterior
                if (data.success) {
                    if (data.cvs.length > 0) {
                        data.cvs.forEach(cv => {
                            const cvItem = document.createElement('div');
                            cvItem.classList.add('list-group-item', 'd-flex', 'justify-content-between', 'align-items-center');
                            cvItem.innerHTML = `
                                <span><i class="fas fa-file-alt me-2"></i> ${escapeHtml(cv.nombre_archivo)} <small class="text-muted ms-2">(Subido: ${escapeHtml(cv.fecha_subida)})</small></span>
                                <div>
                                    <a href="../profesores/${escapeHtml(cv.ruta_archivo)}" target="_blank" class="btn btn-info btn-sm me-2" title="Ver CV"><i class="fas fa-eye"></i></a>
                                    <button type="button" class="btn btn-danger btn-sm delete-cv-btn" data-id_cv="${escapeHtml(String(cv.id))}" title="Eliminar CV"><i class="fas fa-trash"></i></button>
                                </div>
                            `;
                            cvsListDiv.appendChild(cvItem);
                        });
                        // Adjuntar event listeners a los nuevos botones de eliminar CV
                        document.querySelectorAll('.delete-cv-btn').forEach(button => {
                            button.addEventListener('click', function () {
                                if (confirm('¿Estás seguro de que quieres eliminar este CV?')) {
                                    deleteCv(this.dataset.id_cv, profesorId);
                                }
                            });
                        });
                    } else {
                        cvsListDiv.innerHTML = '<p class="text-center text-muted">No hay CVs subidos para este profesor.</p>';
                    }
                } else {
                    cvsListDiv.innerHTML = `<p class="text-center text-danger">Error al cargar CVs: ${escapeHtml(data.message)}</p>`;
                    showToast('danger', `Error al cargar CVs: ${data.message}`);
                }
            })
            .catch(error => {
                console.error('Error al cargar CVs:', error);
                cvsListDiv.innerHTML = '<p class="text-center text-danger">Error de conexión al cargar CVs.</p>';
                showToast('danger', 'Error de conexión al cargar CVs.');
            });
    }

    // --- Subir CV vía AJAX ---
    document.getElementById('uploadCvForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        const profesorId = document.getElementById('cv_profesor_id').value; // Usamos el profesor_id correcto

        fetch('../api/profesor.php', {
            method: 'POST',
            body: formData
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showToast('success', data.message);
                    document.getElementById('cvFile').value = ''; // Limpiar campo de archivo
                    loadProfessorCvs(profesorId); // Recargar CVs
                    updateProfessorsTableDisplay(); // Actualizar la tabla principal (esto es un placeholder, realmente necesitas recargar la tabla PHP o actualizar el conteo en el DOM)
                } else {
                    showToast('danger', data.message);
                }
            })
            .catch(error => {
                console.error('Error al subir CV:', error);
                showToast('danger', 'Error de conexión al subir CV.');
            });
    });

    // --- Eliminar CV vía AJAX ---
    function deleteCv(idCv, profesorId) {
        const formData = new FormData();
        formData.append('action', 'delete_cv');
        formData.append('id_cv', idCv);

        fetch('../api/profesor.php', {
            method: 'POST',
            body: formData
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showToast('success', data.message);
                    loadProfessorCvs(profesorId); // Recargar CVs
                    updateProfessorsTableDisplay(); // Actualizar la tabla principal (esto es un placeholder, realmente necesitas recargar la tabla PHP o actualizar el conteo en el DOM)
                } else {
                    showToast('danger', data.message);
                }
            })
            .catch(error => {
                console.error('Error al eliminar CV:', error);
                showToast('danger', 'Error de conexión al eliminar CV.');
            });
    }

    // --- Cargar Asignaturas Sugeridas vía AJAX ---
    function loadSuggestedSubjects(profesorId) {
        const suggestedSubjectsListDiv = document.getElementById('suggestedSubjectsList');
        suggestedSubjectsListDiv.innerHTML = '<p class="text-center text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Cargando asignaturas sugeridas...</p>';

        fetch('../api/profesor.php?action=get_suggested_subjects&id_profesor=' + profesorId)
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        console.error('Respuesta de Error HTTP (texto):', text);
                        throw new Error(`¡Error HTTP! Estado: ${response.status} - ${text.substring(0, 100)}...`);
                    });
                }
                return response.json();
            })
            .then(data => {
                suggestedSubjectsListDiv.innerHTML = ''; // Limpiar el contenido anterior
                if (data.success) {
                    if (data.subjects && Array.isArray(data.subjects) && data.subjects.length > 0) {
                        data.subjects.forEach(subject => {
                            const subjectName = (subject.nombre_asignatura !== null && typeof subject.nombre_asignatura !== 'undefined')
                                ? String(subject.nombre_asignatura)
                                : 'Nombre Desconocido'; // Valor por defecto si es nulo/indefinido
                            const suggestedId = String(subject.id);

                            const subjectItem = document.createElement('div');
                            subjectItem.classList.add('list-group-item', 'd-flex', 'justify-content-between', 'align-items-center');
                            subjectItem.innerHTML = `
                                <span><i class="fas fa-tag me-2"></i> ${escapeHtml(subjectName)}</span>
                                <div>
                                    <button type="button" class="btn btn-danger btn-sm delete-suggested-subject-btn" data-id_sugerencia="${escapeHtml(suggestedId)}" title="Eliminar Asignatura Sugerida"><i class="fas fa-trash"></i></button>
                                </div>
                            `;
                            suggestedSubjectsListDiv.appendChild(subjectItem);
                        });
                        // Adjuntar event listeners a los nuevos botones de eliminar asignatura sugerida
                        document.querySelectorAll('.delete-suggested-subject-btn').forEach(button => {
                            button.addEventListener('click', function () {
                                if (confirm('¿Estás seguro de que quieres eliminar esta asignatura sugerida?')) {
                                    deleteSuggestedSubject(this.dataset.id_sugerencia, profesorId);
                                }
                            });
                        });
                    } else {
                        suggestedSubjectsListDiv.innerHTML = '<p class="text-center text-muted">No hay asignaturas sugeridas para este profesor.</p>';
                    }
                } else {
                    suggestedSubjectsListDiv.innerHTML = `<p class="text-center text-danger">Error al cargar asignaturas sugeridas: ${escapeHtml(data.message)}</p>`;
                    showToast('danger', `Error al cargar asignaturas sugeridas: ${data.message}`);
                }
            })
            .catch(error => {
                console.error('Error al cargar asignaturas sugeridas (bloque catch):', error);
                suggestedSubjectsListDiv.innerHTML = '<p class="text-center text-danger">Error de conexión al cargar asignaturas sugeridas.</p>';
                showToast('danger', 'Error de conexión al cargar asignaturas sugeridas.');
            });
    }

    // --- Asignar Asignatura Sugerida vía AJAX ---
    document.getElementById('assignSubjectForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        const profesorId = document.getElementById('assign_profesor_id_sugerida').value; // Usamos el profesor_id correcto

        fetch('../api/profesor.php', {
            method: 'POST',
            body: formData
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showToast('success', data.message);
                    document.getElementById('availableSubjectsSugeridas').value = ''; // Limpiar select
                    loadSuggestedSubjects(profesorId); // Recargar sugerencias
                } else {
                    showToast('danger', data.message);
                }
            })
            .catch(error => {
                console.error('Error al asignar asignatura sugerida:', error);
                showToast('danger', 'Error de conexión al asignar asignatura sugerida.');
            });
    });

    // --- Eliminar Asignatura Sugerida vía AJAX ---
    function deleteSuggestedSubject(idSugerencia, profesorId) {
        const formData = new FormData();
        formData.append('action', 'delete_suggested_subject');
        formData.append('id_sugerencia', idSugerencia);

        fetch('../api/profesor.php', {
            method: 'POST',
            body: formData
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showToast('success', data.message);
                    loadSuggestedSubjects(profesorId); // Recargar sugerencias
                } else {
                    showToast('danger', data.message);
                }
            })
            .catch(error => {
                console.error('Error al eliminar sugerencia:', error);
                showToast('danger', 'Error de conexión al eliminar sugerencia.');
            });
    }

    // --- NUEVA FUNCIONALIDAD: Cargar Asignaturas Asignadas vía AJAX ---
    function loadAssignedSubjects(profesorId) {
        const assignedSubjectsListDiv = document.getElementById('assignedSubjectsList');
        assignedSubjectsListDiv.innerHTML = '<p class="text-center text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Cargando asignaturas asignadas...</p>';

        fetch('../api/profesor.php?action=get_assigned_subjects&id_profesor=' + profesorId)
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        console.error('Respuesta de Error HTTP (texto):', text);
                        throw new Error(`¡Error HTTP! Estado: ${response.status} - ${text.substring(0, 100)}...`);
                    });
                }
                return response.json();
            })
            .then(data => {
                assignedSubjectsListDiv.innerHTML = ''; // Limpiar el contenido anterior
                if (data.success) {
                    if (data.subjects && Array.isArray(data.subjects) && data.subjects.length > 0) {
                        data.subjects.forEach(subject => {
                            const subjectName = (subject.nombre_asignatura !== null && typeof subject.nombre_asignatura !== 'undefined')
                                ? String(subject.nombre_asignatura)
                                : 'Nombre Desconocido';
                            const assignedId = String(subject.id); // Este es el ID de la tabla `profesores_asignaturas_asignadas`

                            const subjectItem = document.createElement('div');
                            subjectItem.classList.add('list-group-item', 'd-flex', 'justify-content-between', 'align-items-center');
                            subjectItem.innerHTML = `
                                <span><i class="fas fa-check-circle me-2 text-success"></i> ${escapeHtml(subjectName)}</span>
                                <div>
                                    <button type="button" class="btn btn-danger btn-sm delete-assigned-subject-btn" data-id_asignacion="${escapeHtml(assignedId)}" title="Desasignar Asignatura"><i class="fas fa-minus-circle"></i></button>
                                </div>
                            `;
                            assignedSubjectsListDiv.appendChild(subjectItem);
                        });
                        // Adjuntar event listeners a los nuevos botones de desasignar asignatura
                        document.querySelectorAll('.delete-assigned-subject-btn').forEach(button => {
                            button.addEventListener('click', function () {
                                if (confirm('¿Estás seguro de que quieres desasignar esta asignatura?')) {
                                    deleteAssignedSubject(this.dataset.id_asignacion, profesorId);
                                }
                            });
                        });
                    } else {
                        assignedSubjectsListDiv.innerHTML = '<p class="text-center text-muted">No hay asignaturas asignadas oficialmente a este profesor.</p>';
                    }
                } else {
                    assignedSubjectsListDiv.innerHTML = `<p class="text-center text-danger">Error al cargar asignaturas asignadas: ${escapeHtml(data.message)}</p>`;
                    showToast('danger', `Error al cargar asignaturas asignadas: ${data.message}`);
                }
            })
            .catch(error => {
                console.error('Error al cargar asignaturas asignadas (bloque catch):', error);
                assignedSubjectsListDiv.innerHTML = '<p class="text-center text-danger">Error de conexión al cargar asignaturas asignadas.</p>';
                showToast('danger', 'Error de conexión al cargar asignaturas asignadas.');
            });
    }

    // --- NUEVA FUNCIONALIDAD: Asignar Asignatura Oficialmente vía AJAX ---
    document.getElementById('assignOfficialSubjectForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        const profesorId = document.getElementById('assign_profesor_id_oficial').value;

        fetch('../api/profesor.php', {
            method: 'POST',
            body: formData
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showToast('success', data.message);
                    document.getElementById('availableSubjectsOficiales').value = ''; // Limpiar select
                    loadAssignedSubjects(profesorId); // Recargar asignaturas asignadas
                    updateProfessorsTableDisplay(); // Actualizar el contador en la tabla principal
                } else {
                    showToast('danger', data.message);
                }
            })
            .catch(error => {
                console.error('Error al asignar asignatura oficialmente:', error);
                showToast('danger', 'Error de conexión al asignar asignatura oficialmente.');
            });
    });

    // --- NUEVA FUNCIONALIDAD: Desasignar Asignatura Oficialmente vía AJAX ---
    function deleteAssignedSubject(idAsignacion, profesorId) {
        const formData = new FormData();
        formData.append('action', 'delete_assigned_subject');
        formData.append('id_asignacion', idAsignacion);

        fetch('../api/profesor.php', {
            method: 'POST',
            body: formData
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showToast('success', data.message);
                    loadAssignedSubjects(profesorId); // Recargar asignaturas asignadas
                    updateProfessorsTableDisplay(); // Actualizar el contador en la tabla principal
                } else {
                    showToast('danger', data.message);
                }
            })
            .catch(error => {
                console.error('Error al desasignar asignatura:', error);
                showToast('danger', 'Error de conexión al desasignar asignatura.');
            });
    }

    // --- Función para actualizar la tabla principal después de cambios (ej. conteo de CVs/asignaturas) ---
    // Esta función recarga la página para reflejar los cambios en la tabla principal.
    // En una aplicación más compleja, se podría actualizar solo la fila relevante con AJAX.
    function updateProfessorsTableDisplay() {
        // Simple recarga de la página para ver el conteo actualizado.
        // Considera una actualización más granular para una mejor UX.
        location.reload();
    }

    // --- Paginación y Búsqueda dinámica ---
    // (Tu código existente para paginación y búsqueda iría aquí)
    // Asegúrate de que tu función de búsqueda maneje la nueva columna si es necesario.
    document.getElementById('searchInput').addEventListener('keyup', function () {
        var input, filter, table, tr, td, i, txtValue;
        input = document.getElementById("searchInput");
        filter = input.value.toUpperCase();
        table = document.getElementById("professorsTable");
        tr = table.getElementsByTagName("tr");

        for (i = 1; i < tr.length; i++) { // Empieza en 1 para saltar el thead
            // Obtén las celdas de Nombre Completo (índice 1) y Email (índice 3)
            var tdNombre = tr[i].getElementsByTagName("td")[1];
            var tdEmail = tr[i].getElementsByTagName("td")[3];
            
            if (tdNombre || tdEmail) {
                var nombreTxt = tdNombre ? tdNombre.textContent || tdNombre.innerText : '';
                var emailTxt = tdEmail ? tdEmail.textContent || tdEmail.innerText : '';

                if (nombreTxt.toUpperCase().indexOf(filter) > -1 || emailTxt.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }
    });

</script>