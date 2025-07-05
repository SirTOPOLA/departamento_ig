<?php
// cv.php

// Incluye funciones de utilidad y configuración de la base de datos
require_once '../includes/functions.php';
require_once '../config/database.php';

// Asegura que solo los profesores puedan acceder a esta página
// Redirige a la página de login si no está autenticado o no tiene el rol correcto
check_login_and_role('Profesor');

// Obtiene los mensajes flash si existen para mostrarlos al usuario
$flash_messages = get_flash_messages();

// Obtiene el ID del usuario actual de la sesión
$current_user_id = $_SESSION['user_id'];

// Busca el ID del profesor asociado al ID de usuario actual
// Esto es crucial para vincular el CV al perfil correcto del profesor
$stmt_profesor_id = $pdo->prepare("SELECT id FROM profesores WHERE id_usuario = :id_usuario");
$stmt_profesor_id->bindParam(':id_usuario', $current_user_id, PDO::PARAM_INT);
$stmt_profesor_id->execute();
$id_profesor_actual = $stmt_profesor_id->fetchColumn();

// Si no se encuentra un perfil de profesor, redirige y muestra un error
if (!$id_profesor_actual) {
    set_flash_message('danger', 'Error: No se encontró el perfil de profesor asociado a su usuario.');
    header('Location: ../logout.php'); // O a una página de error adecuada
    exit;
}

 
// --- Obtener datos para la vista del CV (se ejecuta siempre que se carga la página) ---
// Consulta para obtener la información del CV actual del profesor
$profesor_cv = null;
$stmt_cv = $pdo->prepare("SELECT nombre_archivo, ruta_archivo FROM cvs_profesores WHERE id_profesor = :id_profesor");
$stmt_cv->bindParam(':id_profesor', $id_profesor_actual, PDO::PARAM_INT);
$stmt_cv->execute();
$profesor_cv = $stmt_cv->fetch(PDO::FETCH_ASSOC);

// Incluye el encabezado HTML (contiene el inicio del HTML, head, body, y la barra lateral)
include_once '../includes/header.php';
?>

<main>
    <div class="container-fluid px-4">
        <h1 class="mt-4 text-center text-primary mb-4"><i class="fas fa-file-alt me-2"></i> Gestión de Currículum Vitae</h1>
        <p class="lead text-center text-muted">Aquí puedes subir y actualizar tu CV.</p>

        <hr>

        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Subir / Actualizar CV</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Muestra los mensajes flash (éxito, error, advertencia)
                        foreach ($flash_messages as $msg) {
                            echo '<div class="alert alert-' . htmlspecialchars($msg['type']) . ' alert-dismissible fade show" role="alert">';
                            echo htmlspecialchars($msg['message']);
                            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                            echo '</div>';
                        }
                        ?>

                        <?php if ($profesor_cv): ?>
                            <div class="alert alert-success d-flex align-items-center" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <div>Tu CV actual: <a href="<?php echo htmlspecialchars($profesor_cv['ruta_archivo']); ?>" target="_blank" class="alert-link"><?php echo htmlspecialchars($profesor_cv['nombre_archivo']); ?></a></div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning d-flex align-items-center" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <div>Aún no has subido tu CV. Por favor, súbelo para completar tu perfil.</div>
                            </div>
                        <?php endif; ?>

                        <form action="../api/cv.php" method="POST" enctype="multipart/form-data">
                            
                            <div class="mb-3">
                                <label for="profesor_cv" class="form-label">Seleccionar archivo CV (PDF, DOC, DOCX)</label>
                                <input class="form-control" type="file" id="profesor_cv" name="profesor_cv" accept=".pdf,.doc,.docx" required>
                                <div class="form-text">Tamaño máximo de archivo: 5MB.</div>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-2"></i> Subir CV</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include_once '../includes/footer.php'; // Incluye el pie de página y scripts de Bootstrap ?>

<!-- Contenedor para los toasts (mensajes emergentes) -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100;"></div>

<script>
    // JavaScript para mostrar los mensajes flash como toasts de Bootstrap
    const flashMessages = <?php echo json_encode($flash_messages); ?>;

    function showToast(type, message) {
        const toastContainer = document.querySelector('.toast-container');
        const toastId = 'toast-' + Date.now();

        let bgColor = '';
        let icon = '';
        switch (type) {
            case 'success': bgColor = 'bg-success'; icon = '<i class="fas fa-check-circle me-2"></i>'; break;
            case 'danger': bgColor = 'bg-danger'; icon = '<i class="fas fa-exclamation-triangle me-2"></i>'; break;
            case 'warning': bgColor = 'bg-warning text-dark'; icon = '<i class="fas fa-exclamation-circle me-2"></i>'; break;
            case 'info': bgColor = 'bg-info'; icon = '<i class="fas fa-info-circle me-2"></i>'; break;
            default: bgColor = 'bg-secondary'; icon = '<i class="fas fa-bell me-2"></i>'; break;
        }

        const toastHtml = `
            <div id="${toastId}" class="toast align-items-center text-white ${bgColor} border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
                <div class="d-flex">
                    <div class="toast-body d-flex align-items-center">
                        ${icon} ${message}
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
        // Muestra cualquier mensaje flash al cargar la página
        flashMessages.forEach(msg => {
            showToast(msg.type, msg.message);
        });
    });
</script>