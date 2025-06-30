<?php
// Incluir conexión a base de datos y funciones de seguridad
require_once '../config/database.php';
require_once '../includes/functions.php';

// Asegúrate de que solo los administradores puedan acceder
check_login_and_role('Administrador'); // Ajusta 'Admin' si tu rol de administrador tiene otro nombre

$current_page = basename($_SERVER['PHP_SELF']);
$current_folder = basename(dirname($_SERVER['PHP_SELF']));

$message = '';
$message_type = '';

// Directorio para subir archivos (asegúrate de que exista y tenga permisos de escritura)
$upload_dir = '../uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true); // Crea el directorio si no existe
}

// Función para manejar la subida de archivos (reutilizada)
function handle_upload($file_input_name, $current_path, $upload_dir, $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'pdf', 'doc', 'docx', 'txt']) {
    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] === UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES[$file_input_name]['tmp_name'];
        $file_name = basename($_FILES[$file_input_name]['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (in_array($file_ext, $allowed_ext)) {
            $unique_name = uniqid() . '.' . $file_ext;
            $destination = $upload_dir . $unique_name;

            if (move_uploaded_file($file_tmp_name, $destination)) {
                // Eliminar archivo antiguo si existe y no es el mismo
                if ($current_path && file_exists($current_path) && $current_path !== $destination) {
                    unlink($current_path);
                }
                return $destination;
            } else {
                return false; // Error al mover el archivo
            }
        } else {
            return false; // Extensión de archivo no permitida
        }
    }
    return $current_path; // No se subió un nuevo archivo, mantener el existente
}


// --- Lógica para procesar formularios POST ---
$active_tab = 'departamento'; // Pestaña por defecto

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        $pdo->beginTransaction();

        switch ($action) {
            // --- Departamento ---
            case 'update_departamento':
                $nombre = sanitize_input($_POST['nombre'] ?? '');
                $universidad = sanitize_input($_POST['universidad'] ?? '');
                $historia = sanitize_input($_POST['historia'] ?? '');
                $info_matricula = sanitize_input($_POST['info_matricula'] ?? '');
                $direccion = sanitize_input($_POST['direccion'] ?? '');
                $telefono = sanitize_input($_POST['telefono'] ?? '');
                $horario = sanitize_input($_POST['horario'] ?? '');

                $existing_data = $pdo->query("SELECT imagen, logo_unge, logo_pais FROM departamento LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                $imagen_path = $existing_data['imagen'] ?? null;
                $logo_unge_path = $existing_data['logo_unge'] ?? null;
                $logo_pais_path = $existing_data['logo_pais'] ?? null;

                $imagen_path = handle_upload('imagen', $imagen_path, $upload_dir, ['jpg', 'jpeg', 'png', 'gif', 'svg']);
                $logo_unge_path = handle_upload('logo_unge', $logo_unge_path, $upload_dir, ['jpg', 'jpeg', 'png', 'gif', 'svg']);
                $logo_pais_path = handle_upload('logo_pais', $logo_pais_path, $upload_dir, ['jpg', 'jpeg', 'png', 'gif', 'svg']);

                if ($imagen_path === false || $logo_unge_path === false || $logo_pais_path === false) {
                    throw new Exception('Error al subir una o más imágenes. Asegúrate de que son archivos de imagen válidos (JPG, PNG, GIF, SVG) y que el directorio de subida tiene permisos.');
                }

                $stmt_check = $pdo->query("SELECT COUNT(*) FROM departamento");
                $count = $stmt_check->fetchColumn();

                if ($count > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE departamento SET
                            nombre = :nombre, universidad = :universidad, historia = :historia,
                            imagen = :imagen, logo_unge = :logo_unge, logo_pais = :logo_pais,
                            info_matricula = :info_matricula, direccion = :direccion,
                            telefono = :telefono, horario = :horario
                        WHERE id_departamento = (SELECT MIN(id_departamento) FROM (SELECT id_departamento FROM departamento) AS temp)
                    ");
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO departamento (nombre, universidad, historia, imagen, logo_unge, logo_pais, info_matricula, direccion, telefono, horario)
                        VALUES (:nombre, :universidad, :historia, :imagen, :logo_unge, :logo_pais, :info_matricula, :direccion, :telefono, :horario)
                    ");
                }
                $stmt->execute([
                    ':nombre' => $nombre, ':universidad' => $universidad, ':historia' => $historia,
                    ':imagen' => $imagen_path, ':logo_unge' => $logo_unge_path, ':logo_pais' => $logo_pais_path,
                    ':info_matricula' => $info_matricula, ':direccion' => $direccion,
                    ':telefono' => $telefono, ':horario' => $horario
                ]);
                set_flash_message('success', 'Configuración del departamento actualizada correctamente.');
                $active_tab = 'departamento';
                break;

            // --- Publicaciones ---
            case 'add_publicacion':
            case 'edit_publicacion':
                $id_publicacion = filter_var($_POST['id_publicacion'] ?? null, FILTER_VALIDATE_INT);
                $titulo = sanitize_input($_POST['titulo'] ?? '');
                $contenido = sanitize_input($_POST['contenido'] ?? '');
                $tipo = sanitize_input($_POST['tipo'] ?? '');
                $fecha_evento = sanitize_input($_POST['fecha_evento'] ?? null);
                $visible = isset($_POST['visible']) ? 1 : 0;
                $creado_por = $_SESSION['user_id']; // ID del admin logueado

                $current_publicacion_data = null;
                if ($action === 'edit_publicacion' && $id_publicacion) {
                    $stmt_current = $pdo->prepare("SELECT imagen, archivo_adjunto FROM publicaciones WHERE id_publicacion = :id_publicacion");
                    $stmt_current->execute([':id_publicacion' => $id_publicacion]);
                    $current_publicacion_data = $stmt_current->fetch(PDO::FETCH_ASSOC);
                }
                $imagen_publicacion_path = $current_publicacion_data['imagen'] ?? null;
                $archivo_adjunto_path = $current_publicacion_data['archivo_adjunto'] ?? null;

                $imagen_publicacion_path = handle_upload('imagen_publicacion', $imagen_publicacion_path, $upload_dir, ['jpg', 'jpeg', 'png', 'gif', 'svg']);
                $archivo_adjunto_path = handle_upload('archivo_adjunto', $archivo_adjunto_path, $upload_dir, ['pdf', 'doc', 'docx', 'txt']);

                if ($imagen_publicacion_path === false || $archivo_adjunto_path === false) {
                    throw new Exception('Error al subir archivos de la publicación. Verifica formatos y permisos.');
                }

                if ($action === 'add_publicacion') {
                    $stmt = $pdo->prepare("INSERT INTO publicaciones (titulo, contenido, tipo, imagen, archivo_adjunto, fecha_evento, visible, creado_por) VALUES (:titulo, :contenido, :tipo, :imagen, :archivo_adjunto, :fecha_evento, :visible, :creado_por)");
                } else { // edit_publicacion
                    if (!$id_publicacion) throw new Exception("ID de publicación no válido para edición.");
                    $stmt = $pdo->prepare("UPDATE publicaciones SET titulo = :titulo, contenido = :contenido, tipo = :tipo, imagen = :imagen, archivo_adjunto = :archivo_adjunto, fecha_evento = :fecha_evento, visible = :visible, creado_por = :creado_por WHERE id_publicacion = :id_publicacion");
                }
                $params = [
                    ':titulo' => $titulo, ':contenido' => $contenido, ':tipo' => $tipo,
                    ':imagen' => $imagen_publicacion_path, ':archivo_adjunto' => $archivo_adjunto_path,
                    ':fecha_evento' => $fecha_evento, ':visible' => $visible, ':creado_por' => $creado_por
                ];
                if ($action === 'edit_publicacion') $params[':id_publicacion'] = $id_publicacion;

                $stmt->execute($params);
                set_flash_message('success', 'Publicación ' . ($action === 'add_publicacion' ? 'añadida' : 'actualizada') . ' correctamente.');
                $active_tab = 'publicaciones';
                break;

            case 'delete_publicacion':
                $id_publicacion = filter_var($_POST['id_publicacion'] ?? null, FILTER_VALIDATE_INT);
                if (!$id_publicacion) throw new Exception("ID de publicación no válido para eliminación.");

                // Obtener rutas de archivos para eliminarlos
                $stmt_files = $pdo->prepare("SELECT imagen, archivo_adjunto FROM publicaciones WHERE id_publicacion = :id_publicacion");
                $stmt_files->execute([':id_publicacion' => $id_publicacion]);
                $files_to_delete = $stmt_files->fetch(PDO::FETCH_ASSOC);

                $stmt = $pdo->prepare("DELETE FROM publicaciones WHERE id_publicacion = :id_publicacion");
                $stmt->execute([':id_publicacion' => $id_publicacion]);

                if ($stmt->rowCount() > 0) {
                    // Eliminar archivos físicos si existen
                    if ($files_to_delete['imagen'] && file_exists($files_to_delete['imagen'])) unlink($files_to_delete['imagen']);
                    if ($files_to_delete['archivo_adjunto'] && file_exists($files_to_delete['archivo_adjunto'])) unlink($files_to_delete['archivo_adjunto']);
                    set_flash_message('success', 'Publicación eliminada correctamente.');
                } else {
                    set_flash_message('danger', 'Error al eliminar la publicación o no se encontró.');
                }
                $active_tab = 'publicaciones';
                break;

            // --- Requisitos de Matrícula ---
            case 'add_requisito':
            case 'edit_requisito':
                $id_requisito = filter_var($_POST['id_requisito'] ?? null, FILTER_VALIDATE_INT);
                $titulo = sanitize_input($_POST['titulo'] ?? '');
                $descripcion = sanitize_input($_POST['descripcion'] ?? '');
                $tipo = sanitize_input($_POST['tipo'] ?? '');
                $obligatorio = isset($_POST['obligatorio']) ? 1 : 0;
                $visible = isset($_POST['visible']) ? 1 : 0;

                $current_requisito_data = null;
                if ($action === 'edit_requisito' && $id_requisito) {
                    $stmt_current = $pdo->prepare("SELECT archivo_modelo FROM requisitos_matricula WHERE id_requisito = :id_requisito");
                    $stmt_current->execute([':id_requisito' => $id_requisito]);
                    $current_requisito_data = $stmt_current->fetch(PDO::FETCH_ASSOC);
                }
                $archivo_modelo_path = $current_requisito_data['archivo_modelo'] ?? null;

                $archivo_modelo_path = handle_upload('archivo_modelo', $archivo_modelo_path, $upload_dir, ['pdf', 'doc', 'docx', 'txt']);

                if ($archivo_modelo_path === false) {
                    throw new Exception('Error al subir el archivo modelo. Verifica formato y permisos.');
                }

                if ($action === 'add_requisito') {
                    $stmt = $pdo->prepare("INSERT INTO requisitos_matricula (titulo, descripcion, tipo, obligatorio, archivo_modelo, visible) VALUES (:titulo, :descripcion, :tipo, :obligatorio, :archivo_modelo, :visible)");
                } else { // edit_requisito
                    if (!$id_requisito) throw new Exception("ID de requisito no válido para edición.");
                    $stmt = $pdo->prepare("UPDATE requisitos_matricula SET titulo = :titulo, descripcion = :descripcion, tipo = :tipo, obligatorio = :obligatorio, archivo_modelo = :archivo_modelo, visible = :visible WHERE id_requisito = :id_requisito");
                }
                $params = [
                    ':titulo' => $titulo, ':descripcion' => $descripcion, ':tipo' => $tipo,
                    ':obligatorio' => $obligatorio, ':archivo_modelo' => $archivo_modelo_path, ':visible' => $visible
                ];
                if ($action === 'edit_requisito') $params[':id_requisito'] = $id_requisito;

                $stmt->execute($params);
                set_flash_message('success', 'Requisito de matrícula ' . ($action === 'add_requisito' ? 'añadido' : 'actualizado') . ' correctamente.');
                $active_tab = 'requisitos';
                break;

            case 'delete_requisito':
                $id_requisito = filter_var($_POST['id_requisito'] ?? null, FILTER_VALIDATE_INT);
                if (!$id_requisito) throw new Exception("ID de requisito no válido para eliminación.");

                // Obtener ruta de archivo para eliminarlo
                $stmt_file = $pdo->prepare("SELECT archivo_modelo FROM requisitos_matricula WHERE id_requisito = :id_requisito");
                $stmt_file->execute([':id_requisito' => $id_requisito]);
                $file_to_delete = $stmt_file->fetchColumn();

                $stmt = $pdo->prepare("DELETE FROM requisitos_matricula WHERE id_requisito = :id_requisito");
                $stmt->execute([':id_requisito' => $id_requisito]);

                if ($stmt->rowCount() > 0) {
                    // Eliminar archivo físico si existe
                    if ($file_to_delete && file_exists($file_to_delete)) unlink($file_to_delete);
                    set_flash_message('success', 'Requisito de matrícula eliminado correctamente.');
                } else {
                    set_flash_message('danger', 'Error al eliminar el requisito o no se encontró.');
                }
                $active_tab = 'requisitos';
                break;

            default:
                set_flash_message('danger', 'Acción no reconocida.');
                break;
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash_message('danger', 'Error: ' . $e->getMessage());
        error_log("Error en configuracion.php: " . $e->getMessage());
    }
    // Redirigir a la misma página, activando la pestaña correcta
    header('Location: configuracion.php?tab=' . $active_tab);
    exit;
}

// Determinar la pestaña activa en la carga de la página (GET)
if (isset($_GET['tab']) && in_array($_GET['tab'], ['departamento', 'publicaciones', 'requisitos'])) {
    $active_tab = $_GET['tab'];
}


// --- Obtener datos para mostrar en la página (GET request) ---

// 1. Datos del Departamento
$departamento_data = [];
try {
    $stmt = $pdo->query("SELECT * FROM departamento LIMIT 1");
    $departamento_data = $stmt->fetch(PDO::FETCH_ASSOC);
    // Si no hay datos, inicializar con valores vacíos para que el formulario no de errores
    if (!$departamento_data) {
        $departamento_data = [
            'id_departamento' => null, 'nombre' => '', 'universidad' => '', 'historia' => '', 'imagen' => '',
            'logo_unge' => '', 'logo_pais' => '', 'info_matricula' => '',
            'direccion' => '', 'telefono' => '', 'horario' => ''
        ];
    }
} catch (PDOException $e) {
    set_flash_message('danger', 'Error al cargar la configuración del departamento: ' . $e->getMessage());
    error_log("PDOException al cargar departamento: " . $e->getMessage());
}

// 2. Publicaciones
$publicaciones = [];
try {
    $stmt = $pdo->query("SELECT p.*, u.nombre_completo AS creado_por_nombre FROM publicaciones p LEFT JOIN usuarios u ON p.creado_por = u.id ORDER BY creado_en DESC");
    $publicaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    set_flash_message('danger', 'Error al cargar las publicaciones: ' . $e->getMessage());
    error_log("PDOException al cargar publicaciones: " . $e->getMessage());
}

// 3. Requisitos de Matrícula
$requisitos_matricula = [];
try {
    $stmt = $pdo->query("SELECT * FROM requisitos_matricula ORDER BY titulo ASC");
    $requisitos_matricula = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    set_flash_message('danger', 'Error al cargar los requisitos de matrícula: ' . $e->getMessage());
    error_log("PDOException al cargar requisitos: " . $e->getMessage());
}

// Obtener mensajes flash para JavaScript
$flash_messages = get_flash_messages();

?>

<?php include '../includes/header.php'; ?>

 
            <h1 class="mt-4">Módulo de Configuración</h1>
            <p class="lead">Gestiona la información general, publicaciones y requisitos de matrícula del departamento.</p>

            <?php if (!empty($flash_messages)): ?>
                <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
                    <?php foreach ($flash_messages as $msg_data): ?>
                        <div class="toast align-items-center text-white bg-<?php echo $msg_data['type']; ?> border-0" role="alert" aria-live="assertive" aria-atomic="true">
                            <div class="d-flex">
                                <div class="toast-body">
                                    <?php echo $msg_data['message']; ?>
                                </div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Pestañas de Navegación -->
            <ul class="nav nav-tabs mb-4" id="configTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo ($active_tab == 'departamento') ? 'active' : ''; ?>" id="departamento-tab" data-bs-toggle="tab" data-bs-target="#departamento" type="button" role="tab" aria-controls="departamento" aria-selected="<?php echo ($active_tab == 'departamento') ? 'true' : 'false'; ?>">
                        <i class="fas fa-building me-2"></i> Departamento
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo ($active_tab == 'publicaciones') ? 'active' : ''; ?>" id="publicaciones-tab" data-bs-toggle="tab" data-bs-target="#publicaciones" type="button" role="tab" aria-controls="publicaciones" aria-selected="<?php echo ($active_tab == 'publicaciones') ? 'true' : 'false'; ?>">
                        <i class="fas fa-bullhorn me-2"></i> Publicaciones
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo ($active_tab == 'requisitos') ? 'active' : ''; ?>" id="requisitos-tab" data-bs-toggle="tab" data-bs-target="#requisitos" type="button" role="tab" aria-controls="requisitos" aria-selected="<?php echo ($active_tab == 'requisitos') ? 'true' : 'false'; ?>">
                        <i class="fas fa-clipboard-list me-2"></i> Requisitos Matrícula
                    </button>
                </li>
            </ul>

            <!-- Contenido de las Pestañas -->
            <div class="tab-content" id="configTabsContent">
                <!-- Pestaña: Información del Departamento -->
                <div class="tab-pane fade <?php echo ($active_tab == 'departamento') ? 'show active' : ''; ?>" id="departamento" role="tabpanel" aria-labelledby="departamento-tab">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Detalles del Departamento</h5>
                        </div>
                        <div class="card-body">
                            <p>Aquí puedes ver y actualizar la información general de la institución, como su nombre, universidad, historia, datos de contacto y logos.</p>
                            <dl class="row">
                                <dt class="col-sm-3">Nombre:</dt><dd class="col-sm-9"><?php echo htmlspecialchars($departamento_data['nombre'] ?? 'N/A'); ?></dd>
                                <dt class="col-sm-3">Universidad:</dt><dd class="col-sm-9"><?php echo htmlspecialchars($departamento_data['universidad'] ?? 'N/A'); ?></dd>
                                <dt class="col-sm-3">Historia (extracto):</dt><dd class="col-sm-9"><?php echo htmlspecialchars(substr($departamento_data['historia'] ?? 'N/A', 0, 150)) . '...'; ?></dd>
                                <dt class="col-sm-3">Info Matrícula (extracto):</dt><dd class="col-sm-9"><?php echo htmlspecialchars(substr($departamento_data['info_matricula'] ?? 'N/A', 0, 150)) . '...'; ?></dd>
                                <dt class="col-sm-3">Dirección:</dt><dd class="col-sm-9"><?php echo htmlspecialchars($departamento_data['direccion'] ?? 'N/A'); ?></dd>
                                <dt class="col-sm-3">Teléfono:</dt><dd class="col-sm-9"><?php echo htmlspecialchars($departamento_data['telefono'] ?? 'N/A'); ?></dd>
                                <dt class="col-sm-3">Horario:</dt><dd class="col-sm-9"><?php echo htmlspecialchars($departamento_data['horario'] ?? 'N/A'); ?></dd>
                                <dt class="col-sm-3">Imagen Principal:</dt><dd class="col-sm-9"><?php echo !empty($departamento_data['imagen']) ? '<img src="' . htmlspecialchars($departamento_data['imagen']) . '" class="img-thumbnail" style="max-width: 100px;">' : 'N/A'; ?></dd>
                                <dt class="col-sm-3">Logo UNGE:</dt><dd class="col-sm-9"><?php echo !empty($departamento_data['logo_unge']) ? '<img src="' . htmlspecialchars($departamento_data['logo_unge']) . '" class="img-thumbnail" style="max-width: 80px;">' : 'N/A'; ?></dd>
                                <dt class="col-sm-3">Logo País:</dt><dd class="col-sm-9"><?php echo !empty($departamento_data['logo_pais']) ? '<img src="' . htmlspecialchars($departamento_data['logo_pais']) . '" class="img-thumbnail" style="max-width: 80px;">' : 'N/A'; ?></dd>
                            </dl>
                            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#departamentoModal" id="editDepartamentoBtn"
                                data-nombre="<?php echo htmlspecialchars($departamento_data['nombre']); ?>"
                                data-universidad="<?php echo htmlspecialchars($departamento_data['universidad']); ?>"
                                data-historia="<?php echo htmlspecialchars($departamento_data['historia']); ?>"
                                data-info_matricula="<?php echo htmlspecialchars($departamento_data['info_matricula']); ?>"
                                data-direccion="<?php echo htmlspecialchars($departamento_data['direccion']); ?>"
                                data-telefono="<?php echo htmlspecialchars($departamento_data['telefono']); ?>"
                                data-horario="<?php echo htmlspecialchars($departamento_data['horario']); ?>"
                                data-imagen="<?php echo htmlspecialchars($departamento_data['imagen']); ?>"
                                data-logo_unge="<?php echo htmlspecialchars($departamento_data['logo_unge']); ?>"
                                data-logo_pais="<?php echo htmlspecialchars($departamento_data['logo_pais']); ?>">
                                <i class="fas fa-edit me-2"></i> Editar Información del Departamento
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Pestaña: Gestión de Publicaciones -->
                <div class="tab-pane fade <?php echo ($active_tab == 'publicaciones') ? 'show active' : ''; ?>" id="publicaciones" role="tabpanel" aria-labelledby="publicaciones-tab">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Gestión de Publicaciones (Noticias, Eventos, Comunicados)</h5>
                            <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#publicacionModal" id="addNewPublicacionBtn">
                                <i class="fas fa-plus me-2"></i> Nueva Publicación
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Título</th>
                                            <th>Tipo</th>
                                            <th>Fecha Evento</th>
                                            <th>Visible</th>
                                            <th>Creado Por</th>
                                            <th>Fecha Creación</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($publicaciones)): ?>
                                            <?php foreach ($publicaciones as $pub): ?>
                                                <tr data-id_publicacion="<?php echo htmlspecialchars($pub['id_publicacion']); ?>"
                                                    data-titulo="<?php echo htmlspecialchars($pub['titulo']); ?>"
                                                    data-contenido="<?php echo htmlspecialchars($pub['contenido']); ?>"
                                                    data-tipo="<?php echo htmlspecialchars($pub['tipo']); ?>"
                                                    data-imagen="<?php echo htmlspecialchars($pub['imagen']); ?>"
                                                    data-archivo_adjunto="<?php echo htmlspecialchars($pub['archivo_adjunto']); ?>"
                                                    data-fecha_evento="<?php echo htmlspecialchars($pub['fecha_evento']); ?>"
                                                    data-visible="<?php echo htmlspecialchars($pub['visible']); ?>">
                                                    <td><?php echo htmlspecialchars($pub['id_publicacion']); ?></td>
                                                    <td><?php echo htmlspecialchars($pub['titulo']); ?></td>
                                                    <td><span class="badge bg-info"><?php echo htmlspecialchars($pub['tipo']); ?></span></td>
                                                    <td><?php echo htmlspecialchars($pub['fecha_evento'] ?? 'N/A'); ?></td>
                                                    <td>
                                                        <?php if ($pub['visible']): ?>
                                                            <span class="badge bg-success">Sí</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">No</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($pub['creado_por_nombre'] ?? 'Desconocido'); ?></td>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($pub['creado_en'])); ?></td>
                                                    <td>
                                                        <button type="button" class="btn btn-warning btn-sm edit-publicacion-btn me-1" title="Editar" data-bs-toggle="modal" data-bs-target="#publicacionModal">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form action="configuracion.php" method="POST" class="d-inline-block" onsubmit="return confirm('¿Estás seguro de que quieres eliminar esta publicación?');">
                                                            <input type="hidden" name="action" value="delete_publicacion">
                                                            <input type="hidden" name="id_publicacion" value="<?php echo htmlspecialchars($pub['id_publicacion']); ?>">
                                                            <button type="submit" class="btn btn-danger btn-sm" title="Eliminar"><i class="fas fa-trash"></i></button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center">No hay publicaciones registradas.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pestaña: Gestión de Requisitos de Matrícula -->
                <div class="tab-pane fade <?php echo ($active_tab == 'requisitos') ? 'show active' : ''; ?>" id="requisitos" role="tabpanel" aria-labelledby="requisitos-tab">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Gestión de Requisitos de Matrícula</h5>
                            <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#requisitoModal" id="addNewRequisitoBtn">
                                <i class="fas fa-plus me-2"></i> Nuevo Requisito
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Título</th>
                                            <th>Tipo</th>
                                            <th>Obligatorio</th>
                                            <th>Visible</th>
                                            <th>Archivo Modelo</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($requisitos_matricula)): ?>
                                            <?php foreach ($requisitos_matricula as $req): ?>
                                                <tr data-id_requisito="<?php echo htmlspecialchars($req['id_requisito']); ?>"
                                                    data-titulo="<?php echo htmlspecialchars($req['titulo']); ?>"
                                                    data-descripcion="<?php echo htmlspecialchars($req['descripcion']); ?>"
                                                    data-tipo="<?php echo htmlspecialchars($req['tipo']); ?>"
                                                    data-obligatorio="<?php echo htmlspecialchars($req['obligatorio']); ?>"
                                                    data-archivo_modelo="<?php echo htmlspecialchars($req['archivo_modelo']); ?>"
                                                    data-visible="<?php echo htmlspecialchars($req['visible']); ?>">
                                                    <td><?php echo htmlspecialchars($req['id_requisito']); ?></td>
                                                    <td><?php echo htmlspecialchars($req['titulo']); ?></td>
                                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($req['tipo']); ?></span></td>
                                                    <td>
                                                        <?php if ($req['obligatorio']): ?>
                                                            <span class="badge bg-success">Sí</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">No</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($req['visible']): ?>
                                                            <span class="badge bg-success">Sí</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">No</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($req['archivo_modelo'])): ?>
                                                            <a href="<?php echo htmlspecialchars($req['archivo_modelo']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-download me-1"></i> Descargar
                                                            </a>
                                                        <?php else: ?>
                                                            N/A
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-warning btn-sm edit-requisito-btn me-1" title="Editar" data-bs-toggle="modal" data-bs-target="#requisitoModal">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form action="configuracion.php" method="POST" class="d-inline-block" onsubmit="return confirm('¿Estás seguro de que quieres eliminar este requisito?');">
                                                            <input type="hidden" name="action" value="delete_requisito">
                                                            <input type="hidden" name="id_requisito" value="<?php echo htmlspecialchars($req['id_requisito']); ?>">
                                                            <button type="submit" class="btn btn-danger btn-sm" title="Eliminar"><i class="fas fa-trash"></i></button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center">No hay requisitos de matrícula registrados.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div> <!-- Fin tab-content -->
 

<!-- Modal para Configuración del Departamento -->
<div class="modal fade" id="departamentoModal" tabindex="-1" aria-labelledby="departamentoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="configuracion.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_departamento">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="departamentoModalLabel">Editar Información del Departamento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="modal_departamento_nombre" class="form-label">Nombre del Departamento:</label>
                            <input type="text" class="form-control" id="modal_departamento_nombre" name="nombre" required>
                        </div>
                        <div class="col-md-6">
                            <label for="modal_departamento_universidad" class="form-label">Universidad:</label>
                            <input type="text" class="form-control" id="modal_departamento_universidad" name="universidad" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="modal_departamento_historia" class="form-label">Historia:</label>
                        <textarea class="form-control" id="modal_departamento_historia" name="historia" rows="5"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="modal_departamento_info_matricula" class="form-label">Información de Matrícula:</label>
                        <textarea class="form-control" id="modal_departamento_info_matricula" name="info_matricula" rows="5"></textarea>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="modal_departamento_direccion" class="form-label">Dirección:</label>
                            <input type="text" class="form-control" id="modal_departamento_direccion" name="direccion">
                        </div>
                        <div class="col-md-4">
                            <label for="modal_departamento_telefono" class="form-label">Teléfono:</label>
                            <input type="text" class="form-control" id="modal_departamento_telefono" name="telefono">
                        </div>
                        <div class="col-md-4">
                            <label for="modal_departamento_horario" class="form-label">Horario de Atención:</label>
                            <input type="text" class="form-control" id="modal_departamento_horario" name="horario">
                        </div>
                    </div>

                    <hr class="my-4">
                    <h5>Imágenes y Logos</h5>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="modal_departamento_imagen" class="form-label">Imagen Principal:</label>
                            <input type="file" class="form-control" id="modal_departamento_imagen" name="imagen" accept="image/*">
                            <div class="mt-2" id="current_imagen_display"></div>
                        </div>
                        <div class="col-md-4">
                            <label for="modal_departamento_logo_unge" class="form-label">Logo UNGE:</label>
                            <input type="file" class="form-control" id="modal_departamento_logo_unge" name="logo_unge" accept="image/*">
                            <div class="mt-2" id="current_logo_unge_display"></div>
                        </div>
                        <div class="col-md-4">
                            <label for="modal_departamento_logo_pais" class="form-label">Logo País:</label>
                            <input type="file" class="form-control" id="modal_departamento_logo_pais" name="logo_pais" accept="image/*">
                            <div class="mt-2" id="current_logo_pais_display"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i> Cerrar</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i> Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Publicaciones -->
<div class="modal fade" id="publicacionModal" tabindex="-1" aria-labelledby="publicacionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="configuracion.php" method="POST" enctype="multipart/form-data" id="publicacionForm">
                <input type="hidden" name="id_publicacion" id="modal_publicacion_id">
                <input type="hidden" name="action" id="modal_publicacion_action" value="add_publicacion">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="publicacionModalLabel">Nueva Publicación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_publicacion_titulo" class="form-label">Título:</label>
                        <input type="text" class="form-control" id="modal_publicacion_titulo" name="titulo" required>
                    </div>
                    <div class="mb-3">
                        <label for="modal_publicacion_contenido" class="form-label">Contenido:</label>
                        <textarea class="form-control" id="modal_publicacion_contenido" name="contenido" rows="5" required></textarea>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="modal_publicacion_tipo" class="form-label">Tipo:</label>
                            <select class="form-select" id="modal_publicacion_tipo" name="tipo" required>
                                <option value="noticia">Noticia</option>
                                <option value="evento">Evento</option>
                                <option value="comunicado">Comunicado</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="modal_publicacion_fecha_evento" class="form-label">Fecha del Evento (si aplica):</label>
                            <input type="date" class="form-control" id="modal_publicacion_fecha_evento" name="fecha_evento">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_publicacion_imagen" class="form-label">Imagen:</label>
                        <input type="file" class="form-control" id="modal_publicacion_imagen" name="imagen_publicacion" accept="image/*">
                        <div class="mt-2" id="current_publicacion_imagen_display"></div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_publicacion_archivo_adjunto" class="form-label">Archivo Adjunto:</label>
                        <input type="file" class="form-control" id="modal_publicacion_archivo_adjunto" name="archivo_adjunto" accept=".pdf,.doc,.docx,.txt">
                        <div class="mt-2" id="current_publicacion_adjunto_display"></div>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="modal_publicacion_visible" name="visible" checked>
                        <label class="form-check-label" for="modal_publicacion_visible">
                            Visible
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i> Cerrar</button>
                    <button type="submit" class="btn btn-success" id="publicacionSaveBtn"><i class="fas fa-save me-2"></i> Guardar Publicación</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Requisitos de Matrícula -->
<div class="modal fade" id="requisitoModal" tabindex="-1" aria-labelledby="requisitoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="configuracion.php" method="POST" enctype="multipart/form-data" id="requisitoForm">
                <input type="hidden" name="id_requisito" id="modal_requisito_id">
                <input type="hidden" name="action" id="modal_requisito_action" value="add_requisito">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="requisitoModalLabel">Nuevo Requisito de Matrícula</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_requisito_titulo" class="form-label">Título:</label>
                        <input type="text" class="form-control" id="modal_requisito_titulo" name="titulo" required>
                    </div>
                    <div class="mb-3">
                        <label for="modal_requisito_descripcion" class="form-label">Descripción:</label>
                        <textarea class="form-control" id="modal_requisito_descripcion" name="descripcion" rows="5" required></textarea>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="modal_requisito_tipo" class="form-label">Tipo:</label>
                            <select class="form-select" id="modal_requisito_tipo" name="tipo" required>
                                <option value="nuevo">Nuevo Ingreso</option>
                                <option value="antiguo">Antiguo Ingreso</option>
                                <option value="extranjero">Extranjero</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" value="1" id="modal_requisito_obligatorio" name="obligatorio" checked>
                                <label class="form-check-label" for="modal_requisito_obligatorio">
                                    Obligatorio
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="modal_requisito_visible" name="visible" checked>
                                <label class="form-check-label" for="modal_requisito_visible">
                                    Visible
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_requisito_archivo_modelo" class="form-label">Archivo Modelo (PDF, DOC, TXT):</label>
                        <input type="file" class="form-control" id="modal_requisito_archivo_modelo" name="archivo_modelo" accept=".pdf,.doc,.docx,.txt">
                        <div class="mt-2" id="current_requisito_archivo_display"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i> Cerrar</button>
                    <button type="submit" class="btn btn-info" id="requisitoSaveBtn"><i class="fas fa-save me-2"></i> Guardar Requisito</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Función para mostrar toasts
        function showToast(message, type) {
            const toastContainer = document.querySelector('.toast-container');
            const toastHtml = `
                <div class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            toastContainer.insertAdjacentHTML('beforeend', toastHtml);
            const toastEl = toastContainer.lastElementChild;
            const toast = new bootstrap.Toast(toastEl);
            toast.show();
        }

        // Mostrar mensajes flash al cargar la página
        const flashMessages = <?php echo json_encode($flash_messages); ?>;
        flashMessages.forEach(msg => {
            showToast(msg.message, msg.type);
        });

        // --- Lógica para el Modal de Departamento ---
        const departamentoModal = document.getElementById('departamentoModal');
        departamentoModal.addEventListener('show.bs.modal', function() {
            // Rellenar el formulario con los datos actuales del departamento
            document.getElementById('modal_departamento_nombre').value = document.getElementById('editDepartamentoBtn').dataset.nombre;
            document.getElementById('modal_departamento_universidad').value = document.getElementById('editDepartamentoBtn').dataset.universidad;
            document.getElementById('modal_departamento_historia').value = document.getElementById('editDepartamentoBtn').dataset.historia;
            document.getElementById('modal_departamento_info_matricula').value = document.getElementById('editDepartamentoBtn').dataset.info_matricula;
            document.getElementById('modal_departamento_direccion').value = document.getElementById('editDepartamentoBtn').dataset.direccion;
            document.getElementById('modal_departamento_telefono').value = document.getElementById('editDepartamentoBtn').dataset.telefono;
            document.getElementById('modal_departamento_horario').value = document.getElementById('editDepartamentoBtn').dataset.horario;

            // Mostrar imágenes actuales
            const currentImagen = document.getElementById('editDepartamentoBtn').dataset.imagen;
            const currentLogoUnge = document.getElementById('editDepartamentoBtn').dataset.logo_unge;
            const currentLogoPais = document.getElementById('editDepartamentoBtn').dataset.logo_pais;

            document.getElementById('current_imagen_display').innerHTML = currentImagen ? `<img src="${currentImagen}" class="img-thumbnail" style="max-width: 150px;"><small class="d-block text-muted">Actual</small>` : 'No hay imagen actual.';
            document.getElementById('current_logo_unge_display').innerHTML = currentLogoUnge ? `<img src="${currentLogoUnge}" class="img-thumbnail" style="max-width: 100px;"><small class="d-block text-muted">Actual</small>` : 'No hay logo UNGE actual.';
            document.getElementById('current_logo_pais_display').innerHTML = currentLogoPais ? `<img src="${currentLogoPais}" class="img-thumbnail" style="max-width: 100px;"><small class="d-block text-muted">Actual</small>` : 'No hay logo País actual.';
        });

        // --- Lógica para el Modal de Publicaciones ---
        const publicacionModal = document.getElementById('publicacionModal');
        const publicacionForm = document.getElementById('publicacionForm');
        const publicacionModalLabel = document.getElementById('publicacionModalLabel');
        const publicacionSaveBtn = document.getElementById('publicacionSaveBtn');
        const modalPublicacionId = document.getElementById('modal_publicacion_id');
        const modalPublicacionAction = document.getElementById('modal_publicacion_action');
        const modalPublicacionTitulo = document.getElementById('modal_publicacion_titulo');
        const modalPublicacionContenido = document.getElementById('modal_publicacion_contenido');
        const modalPublicacionTipo = document.getElementById('modal_publicacion_tipo');
        const modalPublicacionFechaEvento = document.getElementById('modal_publicacion_fecha_evento');
        const modalPublicacionVisible = document.getElementById('modal_publicacion_visible');
        const currentPublicacionImagenDisplay = document.getElementById('current_publicacion_imagen_display');
        const currentPublicacionAdjuntoDisplay = document.getElementById('current_publicacion_adjunto_display');
        const modalPublicacionImagenInput = document.getElementById('modal_publicacion_imagen');
        const modalPublicacionArchivoAdjuntoInput = document.getElementById('modal_publicacion_archivo_adjunto');


        document.getElementById('addNewPublicacionBtn').addEventListener('click', function() {
            publicacionForm.reset();
            modalPublicacionId.value = '';
            modalPublicacionAction.value = 'add_publicacion';
            publicacionModalLabel.innerText = 'Nueva Publicación';
            publicacionSaveBtn.innerText = 'Guardar Publicación';
            publicacionSaveBtn.classList.remove('btn-warning');
            publicacionSaveBtn.classList.add('btn-success');
            modalPublicacionVisible.checked = true; // Por defecto visible
            currentPublicacionImagenDisplay.innerHTML = '';
            currentPublicacionAdjuntoDisplay.innerHTML = '';
            modalPublicacionImagenInput.required = false; // No requerido al añadir
            modalPublicacionArchivoAdjuntoInput.required = false; // No requerido al añadir
        });

        document.querySelectorAll('.edit-publicacion-btn').forEach(button => {
            button.addEventListener('click', function() {
                const row = this.closest('tr');
                modalPublicacionId.value = row.dataset.id_publicacion;
                modalPublicacionAction.value = 'edit_publicacion';
                publicacionModalLabel.innerText = 'Editar Publicación';
                publicacionSaveBtn.innerText = 'Actualizar Publicación';
                publicacionSaveBtn.classList.remove('btn-success');
                publicacionSaveBtn.classList.add('btn-warning');

                modalPublicacionTitulo.value = row.dataset.titulo;
                modalPublicacionContenido.value = row.dataset.contenido;
                modalPublicacionTipo.value = row.dataset.tipo;
                modalPublicacionFechaEvento.value = row.dataset.fecha_evento;
                modalPublicacionVisible.checked = (row.dataset.visible === '1');

                const currentImagen = row.dataset.imagen;
                const currentAdjunto = row.dataset.archivo_adjunto;

                currentPublicacionImagenDisplay.innerHTML = currentImagen ? `<img src="${currentImagen}" class="img-thumbnail" style="max-width: 100px;"><small class="d-block text-muted">Actual</small>` : 'No hay imagen actual.';
                currentPublicacionAdjuntoDisplay.innerHTML = currentAdjunto ? `<a href="${currentAdjunto}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-download me-1"></i> Descargar actual</a><small class="d-block text-muted">Actual</small>` : 'No hay archivo adjunto actual.';
                
                modalPublicacionImagenInput.required = false; // No requerido al editar
                modalPublicacionArchivoAdjuntoInput.required = false; // No requerido al editar
            });
        });

        // --- Lógica para el Modal de Requisitos de Matrícula ---
        const requisitoModal = document.getElementById('requisitoModal');
        const requisitoForm = document.getElementById('requisitoForm');
        const requisitoModalLabel = document.getElementById('requisitoModalLabel');
        const requisitoSaveBtn = document.getElementById('requisitoSaveBtn');
        const modalRequisitoId = document.getElementById('modal_requisito_id');
        const modalRequisitoAction = document.getElementById('modal_requisito_action');
        const modalRequisitoTitulo = document.getElementById('modal_requisito_titulo');
        const modalRequisitoDescripcion = document.getElementById('modal_requisito_descripcion');
        const modalRequisitoTipo = document.getElementById('modal_requisito_tipo');
        const modalRequisitoObligatorio = document.getElementById('modal_requisito_obligatorio');
        const modalRequisitoVisible = document.getElementById('modal_requisito_visible');
        const currentRequisitoArchivoDisplay = document.getElementById('current_requisito_archivo_display');
        const modalRequisitoArchivoModeloInput = document.getElementById('modal_requisito_archivo_modelo');

        document.getElementById('addNewRequisitoBtn').addEventListener('click', function() {
            requisitoForm.reset();
            modalRequisitoId.value = '';
            modalRequisitoAction.value = 'add_requisito';
            requisitoModalLabel.innerText = 'Nuevo Requisito de Matrícula';
            requisitoSaveBtn.innerText = 'Guardar Requisito';
            requisitoSaveBtn.classList.remove('btn-warning');
            requisitoSaveBtn.classList.add('btn-info');
            modalRequisitoObligatorio.checked = true; // Por defecto obligatorio
            modalRequisitoVisible.checked = true; // Por defecto visible
            currentRequisitoArchivoDisplay.innerHTML = '';
            modalRequisitoArchivoModeloInput.required = false; // No requerido al añadir
        });

        document.querySelectorAll('.edit-requisito-btn').forEach(button => {
            button.addEventListener('click', function() {
                const row = this.closest('tr');
                modalRequisitoId.value = row.dataset.id_requisito;
                modalRequisitoAction.value = 'edit_requisito';
                requisitoModalLabel.innerText = 'Editar Requisito de Matrícula';
                requisitoSaveBtn.innerText = 'Actualizar Requisito';
                requisitoSaveBtn.classList.remove('btn-info');
                requisitoSaveBtn.classList.add('btn-warning');

                modalRequisitoTitulo.value = row.dataset.titulo;
                modalRequisitoDescripcion.value = row.dataset.descripcion;
                modalRequisitoTipo.value = row.dataset.tipo;
                modalRequisitoObligatorio.checked = (row.dataset.obligatorio === '1');
                modalRequisitoVisible.checked = (row.dataset.visible === '1');

                const currentArchivo = row.dataset.archivo_modelo;
                currentRequisitoArchivoDisplay.innerHTML = currentArchivo ? `<a href="${currentArchivo}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-download me-1"></i> Descargar actual</a><small class="d-block text-muted">Actual</small>` : 'No hay archivo modelo actual.';
                
                modalRequisitoArchivoModeloInput.required = false; // No requerido al editar
            });
        });

        // Lógica para activar la pestaña correcta al cargar la página
        const urlParams = new URLSearchParams(window.location.search);
        const activeTabFromUrl = urlParams.get('tab');
        if (activeTabFromUrl) {
            const tabButton = document.getElementById(`${activeTabFromUrl}-tab`);
            if (tabButton) {
                const bsTab = new bootstrap.Tab(tabButton);
                bsTab.show();
            }
        }
    });
</script>
