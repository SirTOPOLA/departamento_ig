<?php
// Incluimos el archivo de conexión a la base de datos
require_once '../config/database.php';
require_once '../includes/functions.php'; // Para funciones de redirección, etc.

session_start(); // Iniciamos la sesión para manejar el estado del usuario

// Redirigir si el usuario ya está logueado
if (isset($_SESSION['user_id'])) {
    redirect_to_dashboard($_SESSION['user_role']);
}

$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['usuario'] ?? '');
    $password = $_POST['contrasena'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = "Por favor, introduce tu nombre de usuario y contraseña.";
    } else {
        try {
            // Preparamos la consulta para obtener el usuario y su rol
            // Ahora también obtenemos el NIP y el ID del profesor si aplica
            $stmt = $pdo->prepare("SELECT 
                                        u.id AS user_id, 
                                        u.nombre_usuario, 
                                        u.estado, 
                                        u.password_hash, 
                                        u.nip, -- Asegúrate de que nip esté seleccionado
                                        r.nombre_rol,
                                        p.id AS profesor_id -- ¡NUEVO! Obtenemos el ID de la tabla profesores
                                   FROM usuarios u
                                   JOIN roles r ON u.id_rol = r.id
                                   LEFT JOIN profesores p ON u.id = p.id_usuario -- ¡NUEVO! JOIN opcional con profesores
                                   WHERE u.nombre_usuario = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC); // Usar FETCH_ASSOC para nombres de columna

            if (!$user) {
                // Usuario no encontrado
                $error_message = "Usuario o contraseña incorrectos.";
                echo $error_message;
            } elseif (password_verify($password, $user['password_hash'])) {
                if ($user['estado'] === 'Activo') {
                    // Credenciales válidas y usuario activo, iniciar sesión
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['nombre_usuario'];
                    $_SESSION['user_role'] = $user['nombre_rol'];
                    $_SESSION['nip'] = $user['nip']; 

                    // Si el rol es Profesor, guardar también el ID del profesor
                    if ($user['nombre_rol'] === 'Profesor' && isset($user['profesor_id'])) {
                        $_SESSION['profesor_id'] = $user['profesor_id'];
                    }

                    // Redireccionar según el rol
                    if ($user['nombre_rol'] === 'Administrador') {
                        header('Location: ../admin/index.php');
                    } elseif ($user['nombre_rol'] === 'Estudiante') {
                        header('Location: ../estudiantes/index.php');
                    } elseif ($user['nombre_rol'] === 'Profesor') {
                        header('Location: ../profesores/index.php');
                    } else {
                        // Rol desconocido, redirigir a una página de error o login
                        header('Location: ../index.php?error=invalid_role');
                    }
                    exit();
                } else {
                    // Usuario inactivo o bloqueado
                    $error_message = "Tu cuenta no está activa. Por favor, contacta al administrador.";
                    echo $error_message;
                }
            } else {
                // Contraseña incorrecta
                $error_message = "Usuario o contraseña incorrectos.";
                echo $error_message;
            }

        } catch (PDOException $e) {
            $error_message = "Error en el servidor. Por favor, inténtalo de nuevo más tarde.";
            // En un entorno de producción, loggea $e->getMessage() para depuración.
            error_log("Error de PDO en login: " . $e->getMessage()); // Siempre es bueno loguear errores
            echo $error_message;
        }
    }
}
?>