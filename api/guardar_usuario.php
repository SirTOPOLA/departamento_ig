<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../includes/conexion.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => false, 'message' => 'Método no permitido']);
    exit;
}

$id_usuario   = intval($_POST['id_usuario'] ?? 0);
$nombre       = trim($_POST['nombre'] ?? '');
$apellido     = trim($_POST['apellido'] ?? '');
$email        = trim($_POST['email'] ?? '');
$dni          = trim($_POST['dni'] ?? '');
$direccion    = trim($_POST['direccion'] ?? '');
$telefono     = trim($_POST['telefono'] ?? '');
$rol          = trim($_POST['rol'] ?? '');
$especialidad = trim($_POST['especialidad'] ?? '');
$matricula    = trim($_POST['matricula'] ?? '');
$id_curso     = intval($_POST['id_curso'] ?? 0);
$id_anio      = intval($_POST['id_anio'] ?? 0); // <- Asegúrate de enviar este campo desde tu formulario

// Validaciones básicas
if ($nombre === '' || $email === '' || $dni === '' || $rol === '') {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Complete todos los campos obligatorios.']);
    exit;
}

if (!preg_match('/^[0-9]{6,12}$/', $dni)) {
    echo json_encode(['status' => false, 'message' => 'DNI inválido. Debe tener entre 6 y 12 dígitos.']);
    exit;
}

$rolesValidos = ['administrador', 'profesor', 'estudiante'];
if (!in_array($rol, $rolesValidos)) {
    echo json_encode(['status' => false, 'message' => 'Rol inválido.']);
    exit;
}

if ($rol === 'profesor' && $especialidad === '') {
    echo json_encode(['status' => false, 'message' => 'La especialidad es requerida para profesor.']);
    exit;
}

if ($rol === 'estudiante') {
    if ($matricula === '' || !$id_curso ) {
        echo json_encode(['status' => false, 'message' => 'Matrícula, curso y año académico requeridos para estudiante.']);
        exit;
    }
}

try {
    // Validar email/dni duplicado
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE (email = ? OR dni = ?) AND id_usuario != ?");
    $stmt->execute([$email, $dni, $id_usuario]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['status' => false, 'message' => 'El correo o DNI ya están registrados.']);
        exit;
    }

    // NUEVO USUARIO
    if ($id_usuario === 0) {
        $contrasena = $_POST['contrasena'] ?? '';
        if (strlen($contrasena) < 6) {
            echo json_encode(['status' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres.']);
            exit;
        }

        $hash = password_hash($contrasena, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, apellido, email, dni, direccion, telefono, rol, contrasena)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nombre, $apellido, $email, $dni, $direccion, $telefono, $rol, $hash]);
        $nuevoId = $pdo->lastInsertId();

        if ($rol === 'profesor') {
            $pdo->prepare("INSERT INTO profesores (id_profesor, especialidad) VALUES (?, ?)")
                ->execute([$nuevoId, $especialidad]);
        } elseif ($rol === 'estudiante') {
            $pdo->prepare("INSERT INTO estudiantes (id_estudiante, matricula) VALUES (?, ?)")
                ->execute([$nuevoId, $matricula]);

            $pdo->prepare("INSERT INTO curso_estudiante (id_estudiante, id_curso, id_anio)
                           VALUES (?, ?, ?)")
                ->execute([$nuevoId, $id_curso, $id_anio]);
        }

        echo json_encode(['status' => true, 'message' => 'Usuario registrado correctamente.']);
    }
    // ACTUALIZAR USUARIO
    else {
        $stmt = $pdo->prepare("UPDATE usuarios SET nombre=?, apellido=?, email=?, dni=?, direccion=?, telefono=?, rol=? WHERE id_usuario=?");
        $stmt->execute([$nombre, $apellido, $email, $dni, $direccion, $telefono, $rol, $id_usuario]);

        if ($rol === 'profesor') {
            $pdo->prepare("INSERT INTO profesores (id_profesor, especialidad) 
                           VALUES (?, ?) 
                           ON DUPLICATE KEY UPDATE especialidad = VALUES(especialidad)")
                ->execute([$id_usuario, $especialidad]);
        } elseif ($rol === 'estudiante') {
            $pdo->prepare("INSERT INTO estudiantes (id_estudiante, matricula) 
                           VALUES (?, ?) 
                           ON DUPLICATE KEY UPDATE matricula = VALUES(matricula)")
                ->execute([$id_usuario, $matricula]);

            $pdo->prepare("INSERT INTO curso_estudiante (id_estudiante, id_curso, id_anio)
                           VALUES (?, ?, ?)
                           ON DUPLICATE KEY UPDATE estado = 'activo'")
                ->execute([$id_usuario, $id_curso, $id_anio]);
        }

        echo json_encode(['status' => true, 'message' => 'Usuario actualizado correctamente.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
