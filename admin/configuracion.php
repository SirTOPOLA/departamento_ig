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

 
// Modificaremos esto para que la BD solo guarde 'uploads/configuracion/nombre_archivo.ext'
$base_upload_path = 'uploads/configuracion/'; // Ruta relativa a la raíz del sitio web

// Directorio físico completo para subir archivos
// Usamos $_SERVER['DOCUMENT_ROOT'] para obtener la raíz del servidor web
// y concatenamos con $base_upload_path para obtener la ruta física completa.
$upload_dir =  '../' . $base_upload_path;

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true); // Crea el directorio si no existe
}

// Función para manejar la subida de archivos (reutilizada)
function handle_upload($file_input_name, $current_path, $upload_dir_physical, $base_upload_path_relative, $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'pdf', 'doc', 'docx', 'txt']) {
    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] === UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES[$file_input_name]['tmp_name'];
        $file_name = basename($_FILES[$file_input_name]['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (in_array($file_ext, $allowed_ext)) {
            $unique_name = uniqid() . '.' . $file_ext;
            // La ruta de destino física para mover el archivo
            $destination_physical = $upload_dir_physical . $unique_name;
            // La ruta que se guardará en la base de datos (sin ../)
            $destination_db = $base_upload_path_relative . $unique_name;

            if (move_uploaded_file($file_tmp_name, $destination_physical)) {
                // Eliminar archivo antiguo si existe y no es el mismo.
                // Es importante reconstruir la ruta física del archivo antiguo para poder eliminarlo.
                if ($current_path && file_exists('../' . $current_path) && '../' . $current_path !== $destination_physical) {
                    unlink('../' . $current_path);
                }
                return $destination_db; // Retorna la ruta relativa al directorio raíz del sitio web
            } else {
                return false; // Error al mover el archivo
            }
        } else {
            return false; // Extensión de archivo no permitida
        }
    }
    return $current_path; // No se subió un nuevo archivo, mantener el existente (que ya está en formato DB)
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

                // Llama a handle_upload pasando el directorio físico y la ruta relativa para la DB
                $imagen_path = handle_upload('imagen', $imagen_path, $upload_dir, $base_upload_path, ['jpg', 'jpeg', 'png', 'webp','gif', 'svg']);
                $logo_unge_path = handle_upload('logo_unge', $logo_unge_path, $upload_dir, $base_upload_path, ['jpg', 'jpeg', 'png','webp', 'gif', 'svg']);
                $logo_pais_path = handle_upload('logo_pais', $logo_pais_path, $upload_dir, $base_upload_path, ['jpg', 'jpeg', 'png','webp', 'gif', 'svg']);

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

                // Llama a handle_upload con las rutas adecuadas
                $imagen_publicacion_path = handle_upload('imagen_publicacion', $imagen_publicacion_path, $upload_dir, $base_upload_path, ['jpg', 'jpeg', 'png', 'gif', 'svg']);
                $archivo_adjunto_path = handle_upload('archivo_adjunto', $archivo_adjunto_path, $upload_dir, $base_upload_path, ['pdf', 'doc', 'docx', 'txt']);

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

                // Obtener rutas de archivos para eliminarlos (ya están en formato de DB, hay que hacerlas físicas)
                $stmt_files = $pdo->prepare("SELECT imagen, archivo_adjunto FROM publicaciones WHERE id_publicacion = :id_publicacion");
                $stmt_files->execute([':id_publicacion' => $id_publicacion]);
                $files_to_delete = $stmt_files->fetch(PDO::FETCH_ASSOC);

                $stmt = $pdo->prepare("DELETE FROM publicaciones WHERE id_publicacion = :id_publicacion");
                $stmt->execute([':id_publicacion' => $id_publicacion]);

                if ($stmt->rowCount() > 0) {
                    // Eliminar archivos físicos si existen
                    if ($files_to_delete['imagen'] && file_exists('../' . $files_to_delete['imagen'])) unlink('../' . $files_to_delete['imagen']);
                    if ($files_to_delete['archivo_adjunto'] && file_exists('../' . $files_to_delete['archivo_adjunto'])) unlink('../' . $files_to_delete['archivo_adjunto']);
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

                // Llama a handle_upload con las rutas adecuadas
                $archivo_modelo_path = handle_upload('archivo_modelo', $archivo_modelo_path, $upload_dir, $base_upload_path, ['pdf', 'doc', 'docx', 'txt']);

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

                // Obtener ruta de archivo para eliminarlo (ya está en formato de DB, hay que hacerla física)
                $stmt_file = $pdo->prepare("SELECT archivo_modelo FROM requisitos_matricula WHERE id_requisito = :id_requisito");
                $stmt_file->execute([':id_requisito' => $id_requisito]);
                $file_to_delete = $stmt_file->fetchColumn();

                $stmt = $pdo->prepare("DELETE FROM requisitos_matricula WHERE id_requisito = :id_requisito");
                $stmt->execute([':id_requisito' => $id_requisito]);

                if ($stmt->rowCount() > 0) {
                    // Eliminar archivo físico si existe
                    if ($file_to_delete && file_exists('../' . $file_to_delete)) unlink('../' . $file_to_delete);
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

            <div class="tab-content" id="configTabsContent">
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
                                <dt class="col-sm-3">Imagen Principal:</dt><dd class="col-sm-9"><?php echo !empty($departamento_data['imagen']) ? '<img src="../' . htmlspecialchars($departamento_data['imagen']) . '" class="img-thumbnail" style="max-width: 100px;">' : 'N/A'; ?></dd>
                                <dt class="col-sm-3">Logo UNGE:</dt><dd class="col-sm-9"><?php echo !empty($departamento_data['logo_unge']) ? '<img src="../' . htmlspecialchars($departamento_data['logo_unge']) . '" class="img-thumbnail" style="max-width: 80px;">' : 'N/A'; ?></dd>
                                <dt class="col-sm-3">Logo País:</dt><dd class="col-sm-9"><?php echo !empty($departamento_data['logo_pais']) ? '<img src="../' . htmlspecialchars($departamento_data['logo_pais']) . '" class="img-thumbnail" style="max-width: 80px;">' : 'N/A'; ?></dd>
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
                                                            <a href="<?php echo htmlspecialchars($req['archivo_modelo']); ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Ver archivo">
                                                                <i class="fas fa-file-alt"></i> Ver
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
            </div>

<div class="modal fade" id="departamentoModal" tabindex="-1" aria-labelledby="departamentoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="departamentoModalLabel">Editar Información del Departamento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="configuracion.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_departamento">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nombre" class="form-label">Nombre del Departamento</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="universidad" class="form-label">Nombre de la Universidad</label>
                            <input type="text" class="form-control" id="universidad" name="universidad" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="historia" class="form-label">Historia</label>
                        <textarea class="form-control" id="historia" name="historia" rows="5"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="info_matricula" class="form-label">Información de Matrícula</label>
                        <textarea class="form-control" id="info_matricula" name="info_matricula" rows="5"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="direccion" class="form-label">Dirección</label>
                            <input type="text" class="form-control" id="direccion" name="direccion">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="text" class="form-control" id="telefono" name="telefono">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="horario" class="form-label">Horario de Atención</label>
                            <input type="text" class="form-control" id="horario" name="horario">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="imagen" class="form-label">Imagen Principal</label>
                            <input type="file" class="form-control" id="imagen" name="imagen" accept="image/*">
                            <small class="form-text text-muted">Imagen actual: <span id="current_imagen"></span></small>
                            <div id="imagen_preview" class="mt-2"></div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="logo_unge" class="form-label">Logo UNGE</label>
                            <input type="file" class="form-control" id="logo_unge" name="logo_unge" accept="image/*">
                            <small class="form-text text-muted">Logo actual: <span id="current_logo_unge"></span></small>
                            <div id="logo_unge_preview" class="mt-2"></div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="logo_pais" class="form-label">Logo País</label>
                            <input type="file" class="form-control" id="logo_pais" name="logo_pais" accept="image/*">
                            <small class="form-text text-muted">Logo actual: <span id="current_logo_pais"></span></small>
                            <div id="logo_pais_preview" class="mt-2"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="publicacionModal" tabindex="-1" aria-labelledby="publicacionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="publicacionModalLabel">Nueva Publicación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="configuracion.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" id="publicacion_action" value="add_publicacion">
                    <input type="hidden" name="id_publicacion" id="publicacion_id">
                    <div class="mb-3">
                        <label for="titulo" class="form-label">Título</label>
                        <input type="text" class="form-control" id="publicacion_titulo" name="titulo" required>
                    </div>
                    <div class="mb-3">
                        <label for="contenido" class="form-label">Contenido</label>
                        <textarea class="form-control" id="publicacion_contenido" name="contenido" rows="6" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="tipo" class="form-label">Tipo de Publicación</label>
                            <select class="form-select" id="publicacion_tipo" name="tipo" required>
                                <option value="">Seleccione...</option>
                                <option value="Noticia">Noticia</option>
                                <option value="Evento">Evento</option>
                                <option value="Comunicado">Comunicado</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="fecha_evento" class="form-label">Fecha del Evento (si aplica)</label>
                            <input type="date" class="form-control" id="publicacion_fecha_evento" name="fecha_evento">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="imagen_publicacion" class="form-label">Imagen</label>
                            <input type="file" class="form-control" id="imagen_publicacion" name="imagen_publicacion" accept="image/*">
                            <small class="form-text text-muted">Imagen actual: <span id="current_imagen_publicacion"></span></small>
                            <div id="imagen_publicacion_preview" class="mt-2"></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="archivo_adjunto" class="form-label">Archivo Adjunto (PDF, DOC, TXT)</label>
                            <input type="file" class="form-control" id="archivo_adjunto" name="archivo_adjunto" accept=".pdf,.doc,.docx,.txt">
                            <small class="form-text text-muted">Archivo actual: <span id="current_archivo_adjunto"></span></small>
                            <div id="archivo_adjunto_preview" class="mt-2"></div>
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" value="1" id="publicacion_visible" name="visible">
                        <label class="form-check-label" for="publicacion_visible">
                            Visible en el portal público
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-success" id="submitPublicacionBtn">Guardar Publicación</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="requisitoModal" tabindex="-1" aria-labelledby="requisitoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="requisitoModalLabel">Nuevo Requisito de Matrícula</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="configuracion.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" id="requisito_action" value="add_requisito">
                    <input type="hidden" name="id_requisito" id="requisito_id">
                    <div class="mb-3">
                        <label for="requisito_titulo" class="form-label">Título del Requisito</label>
                        <input type="text" class="form-control" id="requisito_titulo" name="titulo" required>
                    </div>
                    <div class="mb-3">
                        <label for="requisito_descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="requisito_descripcion" name="descripcion" rows="4"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="requisito_tipo" class="form-label">Tipo de Requisito</label>
                            <select class="form-select" id="requisito_tipo" name="tipo" required>
                                <option value="">Seleccione...</option>
                                <option value="Documento">Documento</option>
                                <option value="Pago">Pago</option>
                                <option value="Examen">Examen</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="archivo_modelo" class="form-label">Archivo Modelo (PDF, DOC, TXT)</label>
                            <input type="file" class="form-control" id="requisito_archivo_modelo" name="archivo_modelo" accept=".pdf,.doc,.docx,.txt">
                            <small class="form-text text-muted">Archivo actual: <span id="current_requisito_archivo_modelo"></span></small>
                            <div id="requisito_archivo_modelo_preview" class="mt-2"></div>
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" value="1" id="requisito_obligatorio" name="obligatorio">
                        <label class="form-check-label" for="requisito_obligatorio">
                            Es obligatorio
                        </label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" value="1" id="requisito_visible" name="visible">
                        <label class="form-check-label" for="requisito_visible">
                            Visible en el portal público
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-info" id="submitRequisitoBtn">Guardar Requisito</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar los toasts de Bootstrap
    var toastElList = [].slice.call(document.querySelectorAll('.toast'))
    var toastList = toastElList.map(function(toastEl) {
        return new bootstrap.Toast(toastEl, { delay: 5000 })
    });
    toastList.forEach(toast => toast.show());

    // Script para la pestaña de Departamento
    const departamentoModal = document.getElementById('departamentoModal');
    if (departamentoModal) {
        departamentoModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget; // Botón que activó el modal
            if (button.id === 'editDepartamentoBtn') {
                const nombre = button.getAttribute('data-nombre');
                const universidad = button.getAttribute('data-universidad');
                const historia = button.getAttribute('data-historia');
                const info_matricula = button.getAttribute('data-info_matricula');
                const direccion = button.getAttribute('data-direccion');
                const telefono = button.getAttribute('data-telefono');
                const horario = button.getAttribute('data-horario');
                const imagen = button.getAttribute('data-imagen');
                const logo_unge = button.getAttribute('data-logo_unge');
                const logo_pais = button.getAttribute('data-logo_pais');

                document.getElementById('departamentoModalLabel').textContent = 'Editar Información del Departamento';
                document.getElementById('nombre').value = nombre;
                document.getElementById('universidad').value = universidad;
                document.getElementById('historia').value = historia;
                document.getElementById('info_matricula').value = info_matricula;
                document.getElementById('direccion').value = direccion;
                document.getElementById('telefono').value = telefono;
                document.getElementById('horario').value = horario;

                document.getElementById('current_imagen').textContent = imagen ? imagen.split('/').pop() : 'Ninguna';
                document.getElementById('imagen_preview').innerHTML = imagen ? `<img src="${imagen}" class="img-thumbnail" style="max-width: 80px;">` : '';

                document.getElementById('current_logo_unge').textContent = logo_unge ? logo_unge.split('/').pop() : 'Ninguno';
                document.getElementById('logo_unge_preview').innerHTML = logo_unge ? `<img src="${logo_unge}" class="img-thumbnail" style="max-width: 60px;">` : '';

                document.getElementById('current_logo_pais').textContent = logo_pais ? logo_pais.split('/').pop() : 'Ninguno';
                document.getElementById('logo_pais_preview').innerHTML = logo_pais ? `<img src="${logo_pais}" class="img-thumbnail" style="max-width: 60px;">` : '';
            }
        });
    }

    // Script para la pestaña de Publicaciones
    const publicacionModal = document.getElementById('publicacionModal');
    if (publicacionModal) {
        publicacionModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget; // Botón que activó el modal
            document.getElementById('publicacion_action').value = 'add_publicacion';
            document.getElementById('publicacionModalLabel').textContent = 'Nueva Publicación';
            document.getElementById('publicacion_id').value = '';
            document.getElementById('publicacion_titulo').value = '';
            document.getElementById('publicacion_contenido').value = '';
            document.getElementById('publicacion_tipo').value = '';
            document.getElementById('publicacion_fecha_evento').value = '';
            document.getElementById('publicacion_visible').checked = true; // Por defecto visible
            document.getElementById('current_imagen_publicacion').textContent = 'Ninguna';
            document.getElementById('imagen_publicacion_preview').innerHTML = '';
            document.getElementById('current_archivo_adjunto').textContent = 'Ninguno';
            document.getElementById('archivo_adjunto_preview').innerHTML = '';
            document.getElementById('submitPublicacionBtn').textContent = 'Guardar Publicación';
            document.getElementById('submitPublicacionBtn').classList.remove('btn-warning');
            document.getElementById('submitPublicacionBtn').classList.add('btn-success');


            if (button.classList.contains('edit-publicacion-btn')) {
                const row = button.closest('tr');
                const id = row.getAttribute('data-id_publicacion');
                const titulo = row.getAttribute('data-titulo');
                const contenido = row.getAttribute('data-contenido');
                const tipo = row.getAttribute('data-tipo');
                const imagen = row.getAttribute('data-imagen');
                const archivo_adjunto = row.getAttribute('data-archivo_adjunto');
                const fecha_evento = row.getAttribute('data-fecha_evento');
                const visible = row.getAttribute('data-visible');

                document.getElementById('publicacion_action').value = 'edit_publicacion';
                document.getElementById('publicacionModalLabel').textContent = 'Editar Publicación';
                document.getElementById('publicacion_id').value = id;
                document.getElementById('publicacion_titulo').value = titulo;
                document.getElementById('publicacion_contenido').value = contenido;
                document.getElementById('publicacion_tipo').value = tipo;
                document.getElementById('publicacion_fecha_evento').value = fecha_evento;
                document.getElementById('publicacion_visible').checked = (visible == 1);

                document.getElementById('current_imagen_publicacion').textContent = imagen ? imagen.split('/').pop() : 'Ninguna';
                document.getElementById('imagen_publicacion_preview').innerHTML = imagen ? `<img src="${imagen}" class="img-thumbnail" style="max-width: 80px;">` : '';

                document.getElementById('current_archivo_adjunto').textContent = archivo_adjunto ? archivo_adjunto.split('/').pop() : 'Ninguno';
                document.getElementById('archivo_adjunto_preview').innerHTML = archivo_adjunto ? `<a href="${archivo_adjunto}" target="_blank"><i class="fas fa-file-alt"></i> Ver archivo</a>` : '';

                document.getElementById('submitPublicacionBtn').textContent = 'Actualizar Publicación';
                document.getElementById('submitPublicacionBtn').classList.remove('btn-success');
                document.getElementById('submitPublicacionBtn').classList.add('btn-warning');
            }
        });
    }

    // Script para la pestaña de Requisitos
    const requisitoModal = document.getElementById('requisitoModal');
    if (requisitoModal) {
        requisitoModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget; // Botón que activó el modal
            document.getElementById('requisito_action').value = 'add_requisito';
            document.getElementById('requisitoModalLabel').textContent = 'Nuevo Requisito de Matrícula';
            document.getElementById('requisito_id').value = '';
            document.getElementById('requisito_titulo').value = '';
            document.getElementById('requisito_descripcion').value = '';
            document.getElementById('requisito_tipo').value = '';
            document.getElementById('requisito_obligatorio').checked = false;
            document.getElementById('requisito_visible').checked = true; // Por defecto visible
            document.getElementById('current_requisito_archivo_modelo').textContent = 'Ninguno';
            document.getElementById('requisito_archivo_modelo_preview').innerHTML = '';
            document.getElementById('submitRequisitoBtn').textContent = 'Guardar Requisito';
            document.getElementById('submitRequisitoBtn').classList.remove('btn-warning');
            document.getElementById('submitRequisitoBtn').classList.add('btn-info');


            if (button.classList.contains('edit-requisito-btn')) {
                const row = button.closest('tr');
                const id = row.getAttribute('data-id_requisito');
                const titulo = row.getAttribute('data-titulo');
                const descripcion = row.getAttribute('data-descripcion');
                const tipo = row.getAttribute('data-tipo');
                const obligatorio = row.getAttribute('data-obligatorio');
                const archivo_modelo = row.getAttribute('data-archivo_modelo');
                const visible = row.getAttribute('data-visible');

                document.getElementById('requisito_action').value = 'edit_requisito';
                document.getElementById('requisitoModalLabel').textContent = 'Editar Requisito de Matrícula';
                document.getElementById('requisito_id').value = id;
                document.getElementById('requisito_titulo').value = titulo;
                document.getElementById('requisito_descripcion').value = descripcion;
                document.getElementById('requisito_tipo').value = tipo;
                document.getElementById('requisito_obligatorio').checked = (obligatorio == 1);
                document.getElementById('requisito_visible').checked = (visible == 1);

                document.getElementById('current_requisito_archivo_modelo').textContent = archivo_modelo ? archivo_modelo.split('/').pop() : 'Ninguno';
                document.getElementById('requisito_archivo_modelo_preview').innerHTML = archivo_modelo ? `<a href="${archivo_modelo}" target="_blank"><i class="fas fa-file-alt"></i> Ver archivo</a>` : '';

                document.getElementById('submitRequisitoBtn').textContent = 'Actualizar Requisito';
                document.getElementById('submitRequisitoBtn').classList.remove('btn-info');
                document.getElementById('submitRequisitoBtn').classList.add('btn-warning');
            }
        });
    }

    // Manejar el cambio de pestaña para que la URL se actualice (útil para recargas)
    var configTabs = document.getElementById('configTabs');
    if (configTabs) {
        configTabs.addEventListener('shown.bs.tab', function (event) {
            const activeTabId = event.target.id.replace('-tab', '');
            history.pushState(null, '', 'configuracion.php?tab=' + activeTabId);
        });
    }
});
</script>