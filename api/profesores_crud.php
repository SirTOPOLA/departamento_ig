<?php
// profesores_crud.php
require_once '../includes/conexion.php';

header('Content-Type: application/json');

$response = ['status' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Lógica para Crear o Actualizar un Profesor ---
    $id_usuario = $_POST['id_usuario'] ?? null;
    $nombre = $_POST['nombre'] ?? null;
    $apellido = $_POST['apellido'] ?? null;
    $email = $_POST['email'] ?? null;
    $dni = $_POST['dni'] ?? null;
    $telefono = $_POST['telefono'] ?? null;
    $direccion = $_POST['direccion'] ?? null;
    $especialidad = $_POST['especialidad'] ?? null;
    $estado = $_POST['estado'] ?? 1; // Por defecto activo
    $contrasena = $_POST['contrasena'] ?? null;
    $confirm_contrasena = $_POST['confirm_contrasena'] ?? null;

    // Validaciones básicas
    if (!$nombre || !$apellido || !$email || !$dni) {
        $response['message'] = 'Nombre, apellido, email y DNI son campos obligatorios.';
        echo json_encode($response);
        exit();
    }

    try {
        $pdo->beginTransaction();

        if (empty($id_usuario)) {
            // --- Crear Nuevo Profesor ---
            if (!$contrasena || !$confirm_contrasena) {
                $response['message'] = 'La contraseña y su confirmación son obligatorias para un nuevo profesor.';
                echo json_encode($response);
                $pdo->rollBack();
                exit();
            }
            if ($contrasena !== $confirm_contrasena) {
                $response['message'] = 'Las contraseñas no coinciden.';
                echo json_encode($response);
                $pdo->rollBack();
                exit();
            }
            if (strlen($contrasena) < 6) {
                $response['message'] = 'La contraseña debe tener al menos 6 caracteres.';
                echo json_encode($response);
                $pdo->rollBack();
                exit();
            }

            // Verificar si el email o DNI ya existen
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = :email OR dni = :dni");
            $stmtCheck->bindParam(':email', $email, PDO::PARAM_STR);
            $stmtCheck->bindParam(':dni', $dni, PDO::PARAM_STR);
            $stmtCheck->execute();
            if ($stmtCheck->fetchColumn() > 0) {
                $response['message'] = 'El email o DNI ya están registrados.';
                echo json_encode($response);
                $pdo->rollBack();
                exit();
            }

            $hashed_password = password_hash($contrasena, PASSWORD_DEFAULT);

            // Insertar en la tabla usuarios
            $stmtUser = $pdo->prepare("INSERT INTO usuarios (nombre, apellido, dni, email, contrasena, direccion, telefono, rol, estado)
                                        VALUES (:nombre, :apellido, :dni, :email, :contrasena, :direccion, :telefono, 'profesor', :estado)");
            $stmtUser->bindParam(':nombre', $nombre, PDO::PARAM_STR);
            $stmtUser->bindParam(':apellido', $apellido, PDO::PARAM_STR);
            $stmtUser->bindParam(':dni', $dni, PDO::PARAM_STR);
            $stmtUser->bindParam(':email', $email, PDO::PARAM_STR);
            $stmtUser->bindParam(':contrasena', $hashed_password, PDO::PARAM_STR);
            $stmtUser->bindParam(':direccion', $direccion, PDO::PARAM_STR);
            $stmtUser->bindParam(':telefono', $telefono, PDO::PARAM_STR);
            $stmtUser->bindParam(':estado', $estado, PDO::PARAM_INT);
            $stmtUser->execute();

            $new_profesor_id = $pdo->lastInsertId();

            // Insertar en la tabla profesores
            $stmtProfesor = $pdo->prepare("INSERT INTO profesores (id_profesor, especialidad) VALUES (:id_profesor, :especialidad)");
            $stmtProfesor->bindParam(':id_profesor', $new_profesor_id, PDO::PARAM_INT);
            $stmtProfesor->bindParam(':especialidad', $especialidad, PDO::PARAM_STR);
            $stmtProfesor->execute();

            $response['message'] = 'Profesor creado correctamente.';

        } else {
            // --- Actualizar Profesor Existente ---
            // Verificar si el email o DNI ya existen para otro usuario
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE (email = :email OR dni = :dni) AND id_usuario != :id_usuario");
            $stmtCheck->bindParam(':email', $email, PDO::PARAM_STR);
            $stmtCheck->bindParam(':dni', $dni, PDO::PARAM_STR);
            $stmtCheck->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
            $stmtCheck->execute();
            if ($stmtCheck->fetchColumn() > 0) {
                $response['message'] = 'El email o DNI ya están registrados para otro usuario.';
                echo json_encode($response);
                $pdo->rollBack();
                exit();
            }

            // Actualizar tabla usuarios
            $stmtUser = $pdo->prepare("UPDATE usuarios SET
                                        nombre = :nombre,
                                        apellido = :apellido,
                                        dni = :dni,
                                        email = :email,
                                        direccion = :direccion,
                                        telefono = :telefono,
                                        estado = :estado
                                        WHERE id_usuario = :id_usuario AND rol = 'profesor'"); // Asegurar que solo se actualice un profesor
            $stmtUser->bindParam(':nombre', $nombre, PDO::PARAM_STR);
            $stmtUser->bindParam(':apellido', $apellido, PDO::PARAM_STR);
            $stmtUser->bindParam(':dni', $dni, PDO::PARAM_STR);
            $stmtUser->bindParam(':email', $email, PDO::PARAM_STR);
            $stmtUser->bindParam(':direccion', $direccion, PDO::PARAM_STR);
            $stmtUser->bindParam(':telefono', $telefono, PDO::PARAM_STR);
            $stmtUser->bindParam(':estado', $estado, PDO::PARAM_INT);
            $stmtUser->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
            $stmtUser->execute();

            // Actualizar tabla profesores
            $stmtProfesor = $pdo->prepare("UPDATE profesores SET especialidad = :especialidad WHERE id_profesor = :id_profesor");
            $stmtProfesor->bindParam(':especialidad', $especialidad, PDO::PARAM_STR);
            $stmtProfesor->bindParam(':id_profesor', $id_usuario, PDO::PARAM_INT);
            $stmtProfesor->execute();

            $response['message'] = 'Profesor actualizado correctamente.';
        }

        $pdo->commit();
        $response['status'] = true;

    } catch (PDOException $e) {
        $pdo->rollBack();
        $response['message'] = 'Error de base de datos: ' . $e->getMessage();
        error_log('Error en profesores_crud.php (POST): ' . $e->getMessage());
    } catch (Exception $e) {
        $pdo->rollBack();
        $response['message'] = 'Error inesperado: ' . $e->getMessage();
        error_log('Error inesperado en profesores_crud.php (POST): ' . $e->getMessage());
    }
    echo json_encode($response);

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // --- Lógica para Obtener Detalles o Cambiar Estado ---
    $id_profesor = $_GET['id_profesor'] ?? null;
    $action = $_GET['action'] ?? null;
    $estado = $_GET['estado'] ?? null; // Para la acción toggle_status

    try {
        if ($action === 'toggle_status' && $id_profesor !== null && ($estado === '0' || $estado === '1')) {
            // Cambiar estado del profesor
            $stmt = $pdo->prepare("UPDATE usuarios SET estado = :estado WHERE id_usuario = :id_usuario AND rol = 'profesor'");
            $stmt->bindParam(':estado', $estado, PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario', $id_profesor, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $response['status'] = true;
                $response['message'] = 'Estado del profesor actualizado correctamente.';
            } else {
                $response['message'] = 'No se pudo actualizar el estado del profesor o el profesor no existe.';
            }
        } elseif ($id_profesor !== null) {
            // Obtener detalles de un profesor específico
            $stmt = $pdo->prepare("
                SELECT u.id_usuario, u.nombre, u.apellido, u.email, u.dni, u.estado, p.especialidad, u.telefono, u.direccion
                FROM usuarios u
                INNER JOIN profesores p ON u.id_usuario = p.id_profesor
                WHERE u.id_usuario = :id_usuario AND u.rol = 'profesor'
            ");
            $stmt->bindParam(':id_usuario', $id_profesor, PDO::PARAM_INT);
            $stmt->execute();
            $profesor = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($profesor) {
                $response['status'] = true;
                $response['profesor'] = $profesor;
            } else {
                $response['message'] = 'Profesor no encontrado.';
            }
        } else {
            $response['message'] = 'Parámetros GET inválidos.';
        }
    } catch (PDOException $e) {
        $response['message'] = 'Error de base de datos: ' . $e->getMessage();
        error_log('Error en profesores_crud.php (GET): ' . $e->getMessage());
    } catch (Exception $e) {
        $response['message'] = 'Error inesperado: ' . $e->getMessage();
        error_log('Error inesperado en profesores_crud.php (GET): ' . $e->getMessage());
    }
    echo json_encode($response);
} else {
    $response['message'] = 'Método de solicitud no permitido.';
    echo json_encode($response);
}
?>
