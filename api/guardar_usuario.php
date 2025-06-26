<?php
// Inicia la sesión si no está iniciada.
if (session_status() === PHP_SESSION_NONE) session_start();

// Incluye el archivo de conexión a la base de datos. Se asume que este archivo define la variable $pdo (PDO object).
require_once '../includes/conexion.php';

// Establece la cabecera de respuesta para indicar que se devuelve JSON.
header('Content-Type: application/json');

// --- Validación del Método de Solicitud ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => false, 'message' => 'Método no permitido. Solo se acepta POST.']);
    exit;
}

// --- Recolección y Sanitización de Datos ---
// Recoge todos los datos del POST y les aplica trim() para eliminar espacios en blanco al inicio y al final.
// Se usa el operador null coalescing (??) para asignar un valor por defecto si la variable POST no existe.
$id_usuario    = intval($_POST['id_usuario'] ?? 0);
$nombre        = trim($_POST['nombre'] ?? '');
$apellido      = trim($_POST['apellido'] ?? '');
$email         = trim($_POST['email'] ?? '');
$dni           = trim($_POST['dni'] ?? '');
$direccion     = trim($_POST['direccion'] ?? '');
$telefono      = trim($_POST['telefono'] ?? '');
$rol           = trim($_POST['rol'] ?? '');
$contrasena    = $_POST['contrasena'] ?? ''; // La contraseña no se trimea para permitir espacios intencionales.
$especialidad  = trim($_POST['especialidad'] ?? '');
$matricula     = trim($_POST['matricula'] ?? '');
$id_curso      = intval($_POST['id_curso'] ?? 0);
$id_anio       = intval($_POST['id_anio_academico'] ?? 0); // Campo crucial para la tabla curso_estudiante.

// --- Validaciones de Entrada ---
// Campos obligatorios generales.
if ($nombre === '' || $email === '' || $dni === '' || $rol === '') {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => false, 'message' => 'Error: Complete todos los campos obligatorios (Nombre, Correo, DNI, Rol).']);
    exit;
}

// Validación de formato de DNI.
if (!preg_match('/^[0-9A-Za-z]{6,12}$/', $dni)) { // DNI puede tener letras en algunos países.
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Error: DNI inválido. Debe tener entre 6 y 12 caracteres alfanuméricos.']);
    exit;
}

// Validación de roles permitidos.
$rolesValidos = ['administrador', 'profesor', 'estudiante'];
if (!in_array($rol, $rolesValidos)) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Error: Rol de usuario inválido.']);
    exit;
}

// Validaciones específicas según el rol.
if ($rol === 'profesor') {
    if ($especialidad === '') {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Error: La especialidad es requerida para un profesor.']);
        exit;
    }
} elseif ($rol === 'estudiante') {
    if ($matricula === '' || $id_curso === 0 || $id_anio === 0) {
        http_response_code(400);
        echo json_encode(['status' => false, 
        'message' => 'Error: Matrícula, curso actual y año académico son requeridos para un estudiante.',
        'data' => [
            'matricula' => $matricula,
            'curso' => $id_curso,
            'anio' => $id_anio 
        ]
    ]);
        exit;
    }
}

try {
    // Inicia una transacción para asegurar la atomicidad de las operaciones en la base de datos.
    $pdo->beginTransaction();

    // --- Validación de Duplicidad (Email o DNI) ---
    // Prepara una consulta para verificar si el email o DNI ya existen para otro usuario.
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE (email = ? OR dni = ?) AND id_usuario != ?");
    $stmt->execute([$email, $dni, $id_usuario]);
    if ($stmt->fetchColumn() > 0) {
        $pdo->rollBack(); // Deshace la transacción en caso de error.
        http_response_code(409); // Conflict
        echo json_encode(['status' => false, 'message' => 'Error: El correo electrónico o DNI ya están registrados para otro usuario.']);
        exit;
    }

    // --- Lógica para Nuevo Usuario o Actualización ---
    if ($id_usuario === 0) { // CERO indica que es un NUEVO USUARIO.
        // Validación de contraseña para nuevos usuarios.
        if (strlen($contrasena) < 6) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Error: La contraseña debe tener al menos 6 caracteres.']);
            exit;
        }

        // Hashea la contraseña antes de guardarla.
        $hash = password_hash($contrasena, PASSWORD_DEFAULT);

        // Inserta el nuevo usuario en la tabla 'usuarios'.
        $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, apellido, email, dni, direccion, telefono, rol, contrasena)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nombre, $apellido, $email, $dni, $direccion, $telefono, $rol, $hash]);
        $nuevoId = $pdo->lastInsertId(); // Obtiene el ID del usuario recién insertado.

        // Inserta datos específicos según el rol.
        if ($rol === 'profesor') {
            $pdo->prepare("INSERT INTO profesores (id_profesor, especialidad) VALUES (?, ?)")
                ->execute([$nuevoId, $especialidad]);
        } elseif ($rol === 'estudiante') {
            $pdo->prepare("INSERT INTO estudiantes (id_estudiante, matricula) VALUES (?, ?)")
                ->execute([$nuevoId, $matricula]);

            // Asocia el estudiante con un curso y año académico.
            $pdo->prepare("INSERT INTO curso_estudiante (id_estudiante, id_curso, id_anio, estado)
                           VALUES (?, ?, ?, 'activo')")
                ->execute([$nuevoId, $id_curso, $id_anio]);
        }

        echo json_encode(['status' => true, 'message' => 'Usuario registrado correctamente.']);

    } else { // Si $id_usuario no es CERO, es una ACTUALIZACIÓN de un usuario existente.
        // Primero, obtenemos el rol actual del usuario para manejar cambios de rol.
        $stmt = $pdo->prepare("SELECT rol FROM usuarios WHERE id_usuario = ?");
        $stmt->execute([$id_usuario]);
        $oldRol = $stmt->fetchColumn();

        // Construye la consulta de actualización de usuarios.
        $updateFields = "nombre=?, apellido=?, email=?, dni=?, direccion=?, telefono=?, rol=?";
        $params = [$nombre, $apellido, $email, $dni, $direccion, $telefono, $rol];

        // Si se proporcionó una nueva contraseña, la hashea y la añade a la actualización.
        if (!empty($contrasena)) {
            if (strlen($contrasena) < 6) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'Error: La nueva contraseña debe tener al menos 6 caracteres.']);
                exit;
            }
            $hash = password_hash($contrasena, PASSWORD_DEFAULT);
            $updateFields .= ", contrasena=?";
            $params[] = $hash;
        }

        $params[] = $id_usuario; // Añade el id_usuario al final de los parámetros.

        $stmt = $pdo->prepare("UPDATE usuarios SET $updateFields WHERE id_usuario=?");
        $stmt->execute($params);

        // --- Gestión de tablas específicas según el rol ---
        // 1. Limpiar registros de roles anteriores si el rol ha cambiado.
        if ($oldRol !== $rol) {
            if ($oldRol === 'profesor') {
                $pdo->prepare("DELETE FROM profesores WHERE id_profesor = ?")->execute([$id_usuario]);
            } elseif ($oldRol === 'estudiante') {
                // Considerar si realmente se debe eliminar el historial del estudiante
                // O solo marcar su estado en curso_estudiante como inactivo/finalizado.
                // Para simplificar, si cambia de rol, lo sacamos de la tabla de estudiantes y curso_estudiante.
                $pdo->prepare("DELETE FROM estudiantes WHERE id_estudiante = ?")->execute([$id_usuario]);
                $pdo->prepare("DELETE FROM curso_estudiante WHERE id_estudiante = ?")->execute([$id_usuario]);
            }
        }

        // 2. Insertar o actualizar registros para el rol actual.
        if ($rol === 'profesor') {
            // Usa ON DUPLICATE KEY UPDATE para actualizar si ya existe o insertar si no.
            // Esto requiere que id_profesor en la tabla profesores sea PRIMARY KEY o UNIQUE.
            $pdo->prepare("INSERT INTO profesores (id_profesor, especialidad)
                           VALUES (?, ?)
                           ON DUPLICATE KEY UPDATE especialidad = VALUES(especialidad)")
                ->execute([$id_usuario, $especialidad]);
        } elseif ($rol === 'estudiante') {
            // Usa ON DUPLICATE KEY UPDATE para estudiantes.
            // Requiere que id_estudiante en la tabla estudiantes sea PRIMARY KEY o UNIQUE.
            $pdo->prepare("INSERT INTO estudiantes (id_estudiante, matricula)
                           VALUES (?, ?)
                           ON DUPLICATE KEY UPDATE matricula = VALUES(matricula)")
                ->execute([$id_usuario, $matricula]);

            // Gestión de la relación curso_estudiante:
            // Verificamos si ya existe una inscripción activa para el estudiante en este curso/año.
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM curso_estudiante WHERE id_estudiante = ? AND id_curso = ? AND id_anio = ?");
            $stmtCheck->execute([$id_usuario, $id_curso, $id_anio]);

            if ($stmtCheck->fetchColumn() > 0) {
                // Si existe, actualiza el estado (quizás a 'activo' si el usuario está siendo re-asociado).
                // Aquí podrías decidir si solo se actualiza el estado o si se permite cambiar curso/año.
                // Esta lógica asume que solo se actualiza el estado del registro existente.
                $pdo->prepare("UPDATE curso_estudiante SET estado = 'activo' WHERE id_estudiante = ? AND id_curso = ? AND id_anio = ?")
                    ->execute([$id_usuario, $id_curso, $id_anio]);
            } else {
                // Si no existe, inserta una nueva relación.
                $pdo->prepare("INSERT INTO curso_estudiante (id_estudiante, id_curso, id_anio, estado)
                               VALUES (?, ?, ?, 'activo')")
                    ->execute([$id_usuario, $id_curso, $id_anio]);
            }
        }

        echo json_encode(['status' => true, 'message' => 'Usuario actualizado correctamente.']);
    }

    // Confirma la transacción si todo fue exitoso.
    $pdo->commit();

} catch (PDOException $e) {
    // En caso de cualquier error, se deshace la transacción y se devuelve un mensaje de error.
    $pdo->rollBack();
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Para otros tipos de errores (por ejemplo, validaciones internas).
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Ocurrió un error inesperado: ' . $e->getMessage()]);
}
?>