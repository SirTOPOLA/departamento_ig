<?php
require_once '../includes/functions.php';
session_start();
// Asegúrate de que solo los administradores puedan acceder a este archivo AJAX
if ( $_SESSION['user_role'] !== 'Administrador') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit();
}

require_once '../config/database.php';

header('Content-Type: application/json'); // Asegurarse de que la respuesta sea JSON

$action = $_REQUEST['action'] ?? ''; // Usar $_REQUEST para POST o GET

$response = ['success' => false, 'message' => 'Acción inválida.'];

switch ($action) {
    case 'get_cvs':
        $id_profesor = filter_var($_GET['id_profesor'] ?? null, FILTER_VALIDATE_INT);
        if ($id_profesor) {
            try {
                $stmt = $pdo->prepare("SELECT id, nombre_archivo, ruta_archivo, fecha_subida FROM cvs_profesores WHERE id_profesor = :id_profesor ORDER BY fecha_subida DESC");
                $stmt->bindParam(':id_profesor', $id_profesor, PDO::PARAM_INT);
                $stmt->execute();
                $cvs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $response = ['success' => true, 'cvs' => $cvs];
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => 'Error al obtener CVs: ' . $e->getMessage()];
            }
        } else {
            $response = ['success' => false, 'message' => 'ID de profesor no válido.'];
        }
        break;

    case 'upload_cv':
        $id_profesor = filter_var($_POST['id_profesor'] ?? null, FILTER_VALIDATE_INT);
        if ($id_profesor && isset($_FILES['cv_file'])) {
            $file = $_FILES['cv_file'];
            $upload_dir = 'uploads/cvs/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            $max_size = 5 * 1024 * 1024; // 5 MB

            if (!in_array($file['type'], $allowed_types)) {
                $response = ['success' => false, 'message' => 'Tipo de archivo no permitido. Solo PDF, DOC, DOCX.'];
            } elseif ($file['size'] > $max_size) {
                $response = ['success' => false, 'message' => 'El archivo es demasiado grande. Máximo 5MB.'];
            } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                $response = ['success' => false, 'message' => 'Error al subir el archivo: ' . $file['error']];
            } else {
                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $new_file_name = 'cv_profesor_' . $id_profesor . '_' . time() . '.' . $file_extension;
                $file_path = $upload_dir . $new_file_name;

                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO cvs_profesores (id_profesor, nombre_archivo, ruta_archivo) VALUES (:id_profesor, :nombre_archivo, :ruta_archivo)");
                        $stmt->bindParam(':id_profesor', $id_profesor, PDO::PARAM_INT);
                        $stmt->bindParam(':nombre_archivo', $file['name']);
                        $stmt->bindParam(':ruta_archivo', $file_path); // Guardar la ruta relativa desde la raíz del proyecto
                        $stmt->execute();
                        $response = ['success' => true, 'message' => 'CV subido exitosamente.'];
                    } catch (PDOException $e) {
                        // Si falla la inserción en DB, intentar borrar el archivo subido
                        if (file_exists($file_path)) {
                            unlink($file_path);
                        }
                        $response = ['success' => false, 'message' => 'Error de base de datos al guardar CV: ' . $e->getMessage()];
                    }
                } else {
                    $response = ['success' => false, 'message' => 'Error al mover el archivo subido.'];
                }
            }
        } else {
            $response = ['success' => false, 'message' => 'Datos incompletos para subir CV.'];
        }
        break;

    case 'delete_cv':
        $id_cv = filter_var($_POST['id_cv'] ?? null, FILTER_VALIDATE_INT);
        if ($id_cv) {
            try {
                // Obtener la ruta del archivo antes de eliminar el registro
                $stmt_get_path = $pdo->prepare("SELECT ruta_archivo FROM cvs_profesores WHERE id = :id");
                $stmt_get_path->bindParam(':id', $id_cv, PDO::PARAM_INT);
                $stmt_get_path->execute();
                $cv_path = $stmt_get_path->fetchColumn();

                $stmt = $pdo->prepare("DELETE FROM cvs_profesores WHERE id = :id");
                $stmt->bindParam(':id', $id_cv, PDO::PARAM_INT);
                if ($stmt->execute()) {
                    // Eliminar el archivo físico si existe
                    if ($cv_path && file_exists('../../' . $cv_path)) { // Ruta ajustada
                        unlink('../../' . $cv_path);
                    }
                    $response = ['success' => true, 'message' => 'CV eliminado exitosamente.'];
                } else {
                    $response = ['success' => false, 'message' => 'Error al eliminar CV de la base de datos.'];
                }
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => 'Error de base de datos al eliminar CV: ' . $e->getMessage()];
            }
        } else {
            $response = ['success' => false, 'message' => 'ID de CV no válido.'];
        }
        break;

    case 'get_suggested_subjects':
        $id_profesor = filter_var($_GET['id_profesor'] ?? null, FILTER_VALIDATE_INT);
        if ($id_profesor) {
            try {
                $stmt = $pdo->prepare("
                    SELECT pas.id, a.nombre_asignatura
                    FROM profesores_asignaturas_sugeridas pas
                    JOIN asignaturas a ON pas.id_asignatura = a.id
                    WHERE pas.id_profesor = :id_profesor
                    ORDER BY a.nombre_asignatura ASC
                ");
                $stmt->bindParam(':id_profesor', $id_profesor, PDO::PARAM_INT);
                $stmt->execute();
                $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // --- INICIO DE LA MODIFICACIÓN (YA APLICADA EN LA RESPUESTA ANTERIOR) ---
                // Asegurar que 'nombre_asignatura' siempre sea un string
                foreach ($subjects as &$subject) {
                    if (!isset($subject['nombre_asignatura']) || $subject['nombre_asignatura'] === null) {
                        $subject['nombre_asignatura'] = ''; // Convertir null a string vacío
                    } else {
                        $subject['nombre_asignatura'] = (string)$subject['nombre_asignatura']; // Asegurar que sea string
                    }
                }
                // --- FIN DE LA MODIFICACIÓN ---

                $response = ['success' => true, 'subjects' => $subjects];
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => 'Error al obtener asignaturas sugeridas: ' . $e->getMessage()];
            }
        } else {
            $response = ['success' => false, 'message' => 'ID de profesor no válido.'];
        }
        break;

    case 'assign_subject':
        $id_profesor = filter_var($_POST['id_profesor'] ?? null, FILTER_VALIDATE_INT);
        $id_asignatura = filter_var($_POST['id_asignatura'] ?? null, FILTER_VALIDATE_INT);

        if ($id_profesor && $id_asignatura) {
            try {
                // Verificar si ya existe esta sugerencia/asignación
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM profesores_asignaturas_sugeridas WHERE id_profesor = :id_profesor AND id_asignatura = :id_asignatura");
                $stmt_check->bindParam(':id_profesor', $id_profesor, PDO::PARAM_INT);
                $stmt_check->bindParam(':id_asignatura', $id_asignatura, PDO::PARAM_INT);
                $stmt_check->execute();

                if ($stmt_check->fetchColumn() > 0) {
                    $response = ['success' => false, 'message' => 'Esta asignatura ya está asignada o sugerida para este profesor.'];
                } else {
                    $stmt = $pdo->prepare("INSERT INTO profesores_asignaturas_sugeridas (id_profesor, id_asignatura) VALUES (:id_profesor, :id_asignatura)");
                    $stmt->bindParam(':id_profesor', $id_profesor, PDO::PARAM_INT);
                    $stmt->bindParam(':id_asignatura', $id_asignatura, PDO::PARAM_INT);
                    $stmt->execute();
                    $response = ['success' => true, 'message' => 'Asignatura asignada/sugerida exitosamente.'];
                }
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => 'Error de base de datos al asignar asignatura: ' . $e->getMessage()];
            }
        } else {
            $response = ['success' => false, 'message' => 'Datos incompletos para asignar asignatura.'];
        }
        break;

    case 'delete_suggested_subject':
        $id_sugerencia = filter_var($_POST['id_sugerencia'] ?? null, FILTER_VALIDATE_INT);
        if ($id_sugerencia) {
            try {
                $stmt = $pdo->prepare("DELETE FROM profesores_asignaturas_sugeridas WHERE id = :id");
                $stmt->bindParam(':id', $id_sugerencia, PDO::PARAM_INT);
                $stmt->execute();
                $response = ['success' => true, 'message' => 'Asignatura sugerida eliminada exitosamente.'];
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => 'Error de base de datos al eliminar asignatura sugerida: ' . $e->getMessage()];
            }
        } else {
            $response = ['success' => false, 'message' => 'ID de sugerencia no válido.'];
        }
        break;

    default:
        // Already set to invalid action
        break;
}

echo json_encode($response);
exit();
?>