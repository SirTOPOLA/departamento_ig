<?php
require_once '../includes/conexion.php';
header('Content-Type: application/json');

// Función para subir el archivo modelo
function subirArchivoModelo($input_name, $carpeta_destino = '../uploads/requisitos/')
{
    if (!isset($_FILES[$input_name]) || $_FILES[$input_name]['error'] !== 0) {
        return null;
    }

    $archivo = $_FILES[$input_name];
    $permitidos = ['application/pdf'];
    $max_tamano = 3 * 1024 * 1024; // 3MB

    if (!in_array($archivo['type'], $permitidos)) {
        return ['error' => 'Solo se permiten archivos PDF'];
    }

    if ($archivo['size'] > $max_tamano) {
        return ['error' => 'El archivo excede los 3MB permitidos'];
    }

    if (!is_dir($carpeta_destino)) {
        mkdir($carpeta_destino, 0755, true);
    }

    $ext = pathinfo($archivo['name'], PATHINFO_EXTENSION);
    $nombre_final = uniqid('modelo_', true) . '.' . strtolower($ext);
    $ruta_final = $carpeta_destino . $nombre_final;

    if (move_uploaded_file($archivo['tmp_name'], $ruta_final)) {
        return str_replace('../', '', $ruta_final); // Ruta relativa
    } else {
        return ['error' => 'No se pudo guardar el archivo en el servidor'];
    }
}

// Datos del formulario
$id = isset($_POST['id_requisito']) ? (int) $_POST['id_requisito'] : null;
$titulo = trim($_POST['titulo'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');
$tipo = $_POST['tipo'] ?? 'nuevo';
$obligatorio = isset($_POST['obligatorio']) ? 1 : 0;
$visible = isset($_POST['visible']) ? 1 : 0;

// Validación de campos requeridos
if ($titulo === '' || $descripcion === '') {
    echo json_encode(['status' => false, 'message' => 'Faltan campos obligatorios']);
    exit;
}

// Subida del archivo modelo (si existe)
$ruta_modelo = subirArchivoModelo('archivo_modelo');

if (is_array($ruta_modelo) && isset($ruta_modelo['error'])) {
    echo json_encode(['status' => false, 'message' => $ruta_modelo['error']]);
    exit;
}

try {
    if ($id) {
        // Actualización
        $sql = "UPDATE requisitos_matricula SET titulo=?, descripcion=?, tipo=?, obligatorio=?, visible=?";
        $params = [$titulo, $descripcion, $tipo, $obligatorio, $visible];

        if ($ruta_modelo) {
            $sql .= ", archivo_modelo=?";
            $params[] = $ruta_modelo;
        }

        $sql .= " WHERE id_requisito=?";
        $params[] = $id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo json_encode(['status' => true, 'message' => 'Requisito actualizado']);
    } else {
        // Inserción
        $stmt = $pdo->prepare("INSERT INTO requisitos_matricula (titulo, descripcion, tipo, obligatorio, visible, archivo_modelo) 
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $titulo,
            $descripcion,
            $tipo,
            $obligatorio,
            $visible,
            $ruta_modelo ?: null
        ]);

        echo json_encode(['status' => true, 'message' => 'Requisito creado']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => false, 'message' => 'Error en base de datos: ' . $e->getMessage()]);
}
