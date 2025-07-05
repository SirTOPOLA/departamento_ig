<?php
// Incluye funciones esenciales para el sistema, como la verificación de sesión y rol.
require_once '../includes/functions.php';
// Asegura que solo los usuarios con el rol 'Profesor' puedan acceder a esta página.
check_login_and_role('Profesor');

// Incluye la configuración de la base de datos para establecer la conexión.
require_once '../config/database.php';

// Define el título de la página que aparecerá en la barra del navegador.
$titulo_pagina = "Panel del Profesor - Sugerencias de Asignaturas";
// Incluye el encabezado de la página que contiene el HTML inicial, Bootstrap y Font Awesome.
include_once '../includes/header.php';

// Recupera los mensajes flash (notificaciones temporales) que puedan haberse configurado en sesiones anteriores.
$mensajes_flash = get_flash_messages();

// Obtiene el ID del usuario actual de la sesión.
$id_usuario_actual = $_SESSION['user_id'];

// Prepara y ejecuta una consulta para obtener el ID del profesor asociado al usuario logueado.
$stmt_id_profesor = $pdo->prepare("SELECT id FROM profesores WHERE id_usuario = :id_usuario");
$stmt_id_profesor->bindParam(':id_usuario', $id_usuario_actual, PDO::PARAM_INT);
$stmt_id_profesor->execute();
$id_profesor_actual = $stmt_id_profesor->fetchColumn();

// Si no se encuentra un perfil de profesor asociado, redirige al logout con un mensaje de error.
if (!$id_profesor_actual) {
    set_flash_message('danger', 'Error: No se encontró el perfil de profesor asociado a su usuario.');
    header('Location: ../logout.php'); // Asegúrate de que esta ruta sea correcta para tu sistema
    exit;
}

 

// --- Obtener datos para la vista del profesor (solo lo necesario para las sugerencias) ---

// Obtiene todas las asignaturas disponibles de la tabla `asignaturas`.
$asignaturas_disponibles = $pdo->query("SELECT id, nombre_asignatura, creditos FROM asignaturas ORDER BY nombre_asignatura ASC")->fetchAll(PDO::FETCH_ASSOC);

// Obtiene las asignaturas que ya han sido sugeridas por el profesor actual.
$asignaturas_sugeridas = [];
$stmt_sugeridas = $pdo->prepare("
    SELECT pas.id, a.nombre_asignatura, a.creditos
    FROM profesores_asignaturas_sugeridas pas
    JOIN asignaturas a ON pas.id_asignatura = a.id
    WHERE pas.id_profesor = :id_profesor
    ORDER BY a.nombre_asignatura ASC
");
$stmt_sugeridas->bindParam(':id_profesor', $id_profesor_actual, PDO::PARAM_INT);
$stmt_sugeridas->execute();
$asignaturas_sugeridas = $stmt_sugeridas->fetchAll(PDO::FETCH_ASSOC);

?>

<h1 class="mt-4 text-center text-primary mb-4"><i class="fas fa-chalkboard-teacher me-2"></i> Gestion de sugerencias</h1>
 

<hr>
 

<div class="tab-content" id="contenidoPestañasProfesor">
    <div class="tab-pane fade show active" id="seccionSugerencias" role="tabpanel" aria-labelledby="sugerencias-tab">
        <div class="container mt-4">
            <?php
            if (!empty($mensajes_flash)): ?>
                <div class="mb-3">
                    <?php foreach ($mensajes_flash as $mensaje): ?>
                        <div class="alert alert-<?php echo htmlspecialchars($mensaje['type']); ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($mensaje['message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="text-end mb-3">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalSugerirAsignatura">
                    <i class="fas fa-plus-circle me-2"></i> Sugerir Nueva Asignatura
                </button>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Lista de Asignaturas Sugeridas</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($asignaturas_sugeridas)): ?>
                        <div class="alert alert-info" role="alert">
                            Aún no has sugerido ninguna asignatura.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Asignatura</th>
                                        <th>Créditos</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($asignaturas_sugeridas as $indice => $asignatura): ?>
                                        <tr>
                                            <td><?php echo $indice + 1; ?></td>
                                            <td><?php echo htmlspecialchars($asignatura['nombre_asignatura']); ?></td>
                                            <td><?php echo htmlspecialchars($asignatura['creditos']); ?></td>
                                            <td>
                                                <form action="" method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="remove_suggestion">
                                                    <input type="hidden" name="id_sugerencia" value="<?php echo htmlspecialchars($asignatura['id']); ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('¿Estás seguro de que quieres eliminar esta sugerencia?');">
                                                        <i class="fas fa-trash-alt me-1"></i> Eliminar
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalSugerirAsignatura" tabindex="-1" aria-labelledby="modalSugerirAsignaturaLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="modalSugerirAsignaturaLabel"><i class="fas fa-plus-circle me-2"></i> Sugerir Nueva Asignatura</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form action="../api/guardar_sugerencia_asignaturas.php" method="POST">
                    <input type="hidden" name="action" value="suggest_subject">
                    <div class="mb-3">
                        <label for="id_asignatura" class="form-label">Selecciona una asignatura:</label>
                        <select class="form-select" id="id_asignatura" name="id_asignatura" required>
                            <option value="">-- Selecciona una asignatura --</option>
                            <?php foreach ($asignaturas_disponibles as $asignatura): ?>
                                <option value="<?php echo htmlspecialchars($asignatura['id']); ?>">
                                    <?php echo htmlspecialchars($asignatura['nombre_asignatura']); ?> (<?php echo htmlspecialchars($asignatura['creditos']); ?> créditos)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus-circle me-2"></i> Sugerir Asignatura
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Incluye el pie de página que contiene el cierre del HTML y scripts adicionales.
include_once '../includes/footer.php';
?>

<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100;"></div>

<script>
    // Convierte los mensajes flash PHP en un objeto JavaScript.
    const mensajesFlash = <?php echo json_encode($mensajes_flash); ?>;

    /**
     * Muestra un mensaje de notificación tipo "Toast".
     * @param {string} tipo El tipo de mensaje (success, danger, warning, info).
     * @param {string} mensaje El texto del mensaje a mostrar.
     */
    function mostrarToast(tipo, mensaje) {
        const contenedorToast = document.querySelector('.toast-container');
        const idToast = 'toast-' + Date.now(); // Genera un ID único para cada toast.

        let colorFondo = '';
        let icono = '';
        // Asigna el color de fondo y el icono según el tipo de mensaje.
        switch (tipo) {
            case 'success': colorFondo = 'bg-success'; icono = '<i class="fas fa-check-circle me-2"></i>'; break;
            case 'danger': colorFondo = 'bg-danger'; icono = '<i class="fas fa-exclamation-triangle me-2"></i>'; break;
            case 'warning': colorFondo = 'bg-warning text-dark'; icono = '<i class="fas fa-exclamation-circle me-2"></i>'; break;
            case 'info': colorFondo = 'bg-info'; icono = '<i class="fas fa-info-circle me-2"></i>'; break;
            default: colorFondo = 'bg-secondary'; icono = '<i class="fas fa-bell me-2"></i>'; break;
        }

        // Construye el HTML del toast.
        const htmlToast = `
            <div id="${idToast}" class="toast align-items-center text-white ${colorFondo} border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
                <div class="d-flex">
                    <div class="toast-body d-flex align-items-center">
                        ${icono} ${mensaje}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
                </div>
            </div>
        `;
        // Inserta el toast en el contenedor.
        contenedorToast.insertAdjacentHTML('beforeend', htmlToast);

        // Muestra el toast usando la API de Bootstrap.
        const elementoToast = document.getElementById(idToast);
        const toast = new bootstrap.Toast(elementoToast);
        toast.show();

        // Elimina el toast del DOM una vez que se ha ocultado.
        elementoToast.addEventListener('hidden.bs.toast', function () {
            elementoToast.remove();
        });
    }

    // Cuando el DOM esté completamente cargado, muestra los mensajes flash existentes.
    document.addEventListener('DOMContentLoaded', function() {
        mensajesFlash.forEach(msg => {
            mostrarToast(msg.type, msg.message);
        });
    });
</script>