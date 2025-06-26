 
<?php 
if(session_status() == PHP_SESSION_NONE){
    session_start(); 
}
 
require '../includes/conexion.php';

$usuario = trim($_POST['usuario'] ?? '');
$contrasena = $_POST['contrasena'] ?? '';

if (empty($usuario) || empty($contrasena)) {
    $_SESSION['error'] = 'Debe ingresar ambos campos.';
    header("Location: ../index.php");
    exit;
}

// Buscar por email o DNI
$sql = "SELECT * FROM usuarios WHERE (email = :usuario OR dni = :usuario) AND estado = 1 LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute(['usuario' => $usuario]);
$usuarioDB = $stmt->fetch(PDO::FETCH_ASSOC);

if ($usuarioDB && password_verify($contrasena, $usuarioDB['contrasena'])) {
    $_SESSION['id_usuario'] = $usuarioDB['id_usuario'];
    $_SESSION['nombre'] = $usuarioDB['nombre'];
    $_SESSION['rol'] = $usuarioDB['rol'];

    // Redirigir según rol
    switch ($usuarioDB['rol']) {
        case 'administrador':
            header("Location: ../admin/index.php");
            break;
        case 'profesor':
            header("Location: ../profesor/index.php");
            break;
        case 'estudiante':
            header("Location: ../estudiante/index.php");
            break;
    }
} else {
    $_SESSION['error'] = 'Credenciales incorrectas o usuario inactivo.';
    header("Location: ../index.php");
}
