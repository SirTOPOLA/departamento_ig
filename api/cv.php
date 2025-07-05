
<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/functions.php';
require_once '../config/database.php';

// Asegura que solo los profesores puedan acceder
check_login_and_role('Profesor');

// ID del profesor obtenido directamente de la sesión (debe estar bien definida en el login)
$profesor_id = $_SESSION['profesor_id'] ?? null;

if (!$profesor_id) { 
    set_flash_message('danger', 'No se pudo identificar al profesor. '  );
    
    header('Location: ../profesores/cv.php');
    exit;
}

// Validar que se ha enviado un archivo
if (!isset($_FILES['profesor_cv']) || $_FILES['profesor_cv']['error'] !== UPLOAD_ERR_OK) {
    set_flash_message('danger', 'No se ha subido ningún archivo válido. ' );
    
    header('Location: ../profesores/cv.php');
    exit;
}

$cv = $_FILES['profesor_cv'];
$max_size = 5 * 1024 * 1024; // 5MB
$allowed_ext = ['pdf', 'doc', 'docx'];
$upload_dir = '../uploads/cvs/';

// Crear directorio si no existe
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Validar tamaño
if ($cv['size'] > $max_size) { 
    set_flash_message('danger', 'El archivo excede los 5MB permitidos.' );
    header('Location: ../profesores/cv.php');
    exit;
}

// Validar extensión
$ext = strtolower(pathinfo($cv['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed_ext)) {
    set_flash_message('danger', 'Formato no permitido. Solo PDF, DOC o DOCX.' );
    header('Location: ../profesores/cv.php');
    exit;
}

// Generar nombre único y ruta final
$unique_name = 'CV_' . $profesor_id . '_' . time() . '.' . $ext;
$dest_path = $upload_dir . $unique_name;

if (!move_uploaded_file($cv['tmp_name'], $dest_path)) {
    set_flash_message('danger', 'Error al guardar el archivo' ); 
    header('Location: ../profesores/cv.php');
    exit;
}

// Guardar en la base de datos
// Guardar en la base de datos (verifica si ya existe un CV para este profesor)
try {
    // 1. Verificar si ya existe un CV
    $stmt_check = $pdo->prepare("SELECT id, ruta_archivo FROM cvs_profesores WHERE id_profesor = ?");
    $stmt_check->execute([$profesor_id]);
    $cv_existente = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if ($cv_existente) {
        // Si existe, elimina el archivo anterior (opcional pero recomendable)
        if (file_exists($cv_existente['ruta_archivo'])) {
            unlink($cv_existente['ruta_archivo']);
        }

        // Actualizar el registro
        $stmt_update = $pdo->prepare("UPDATE cvs_profesores 
                                      SET nombre_archivo = ?, ruta_archivo = ?, fecha_subida = NOW() 
                                      WHERE id_profesor = ?");
        $stmt_update->execute([$cv['name'], $dest_path, $profesor_id]);

        set_flash_message('success', 'CV actualizado exitosamente.');
    } else {
        // Si no existe, insertar nuevo
        $stmt_insert = $pdo->prepare("INSERT INTO cvs_profesores (id_profesor, nombre_archivo, ruta_archivo) 
                                      VALUES (?, ?, ?)");
        $stmt_insert->execute([$profesor_id, $cv['name'], $dest_path]);

        set_flash_message('success', 'CV subido exitosamente.');
    }

} catch (PDOException $e) {
    set_flash_message('danger', 'Error al registrar el CV en la base de datos: ' . $e->getMessage());
    error_log("Error al guardar/actualizar CV: " . $e->getMessage());
}

// Redirigir de vuelta
header('Location: ../profesores/cv.php');
exit;
