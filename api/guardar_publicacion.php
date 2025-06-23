<?php
require_once '../includes/conexion.php';
session_start();
header('Content-Type: application/json');

$id = isset($_POST['id_publicacion']) ? (int) $_POST['id_publicacion'] : null;
$titulo = trim($_POST['titulo'] ?? '');
$tipo = $_POST['tipo'] ?? 'noticia';
$contenido = trim($_POST['contenido'] ?? '');
$fecha_evento = $_POST['fecha_evento'] ?? null;
$creado_por = $_SESSION['id_usuario'] ?? 1;

// Validación mínima
if ($titulo === '' || $contenido === '') {
    echo json_encode(['status' => false, 'message' => 'Título y contenido son obligatorios']);
    exit;
}

// Función para subir archivos
function subirArchivo($input_name, $destino = '../uploads/publicaciones/')
{
    if (!isset($_FILES[$input_name]) || $_FILES[$input_name]['error'] !== 0) {
        return null;
    }

    $archivo = $_FILES[$input_name];
    $permitidos_img = ['image/jpeg', 'image/png', 'image/jpg'];
    $permitidos_doc = ['application/pdf'];

    $tipo = $archivo['type'];
    $max_tamano = 3 * 1024 * 1024; // 3MB

    // Verificar carpeta
    if (!is_dir($destino)) {
        mkdir($destino, 0755, true);
    }

    // Validación por tipo
    $es_imagen = in_array($tipo, $permitidos_img);
    $es_pdf = in_array($tipo, $permitidos_doc);

    if (!$es_imagen && !$es_pdf) {
        return ['error' => 'Archivo no permitido. Solo JPG, PNG o PDF'];
    }

    if ($archivo['size'] > $max_tamano) {
        return ['error' => 'Archivo supera el límite de 3MB'];
    }

    $ext = pathinfo($archivo['name'], PATHINFO_EXTENSION);
    $nombre_final = uniqid('file_', true) . '.' . strtolower($ext);
    $ruta_final = $destino . $nombre_final;

    if (move_uploaded_file($archivo['tmp_name'], $ruta_final)) {
        return str_replace('../', '', $ruta_final); // Ruta relativa
    } else {
        return ['error' => 'Error al guardar el archivo'];
    }
}

// Subidas
$ruta_imagen = subirArchivo('imagen');
$ruta_archivo = subirArchivo('archivo_adjunto');

// Validar errores de subida
foreach (['imagen' => $ruta_imagen, 'archivo_adjunto' => $ruta_archivo] as $campo => $resultado) {
    if (is_array($resultado) && isset($resultado['error'])) {
        echo json_encode(['status' => false, 'message' => 'Error en ' . $campo . ': ' . $resultado['error']]);
        exit;
    }
}

try {
    if ($id) {
        // Actualización
        $sql = "UPDATE publicaciones SET titulo = :titulo, tipo = :tipo, contenido = :contenido, fecha_evento = :fecha_evento";
        $params = [
            ':titulo' => $titulo,
            ':tipo' => $tipo,
            ':contenido' => $contenido,
            ':fecha_evento' => $fecha_evento ?: null,
            ':id' => $id
        ];

        if ($ruta_imagen) {
            $sql .= ", imagen = :imagen";
            $params[':imagen'] = $ruta_imagen;
        }

        if ($ruta_archivo) {
            $sql .= ", archivo_adjunto = :archivo_adjunto";
            $params[':archivo_adjunto'] = $ruta_archivo;
        }

        $sql .= " WHERE id_publicacion = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo json_encode(['status' => true, 'message' => 'Publicación actualizada']);
    } else {
        // Inserción
        $stmt = $pdo->prepare("INSERT INTO publicaciones 
            (titulo, tipo, contenido, fecha_evento, imagen, archivo_adjunto, creado_por) 
            VALUES (:titulo, :tipo, :contenido, :fecha_evento, :imagen, :archivo_adjunto, :creado_por)");

        $stmt->execute([
            ':titulo' => $titulo,
            ':tipo' => $tipo,
            ':contenido' => $contenido,
            ':fecha_evento' => $fecha_evento ?: null,
            ':imagen' => $ruta_imagen ?: null,
            ':archivo_adjunto' => $ruta_archivo ?: null,
            ':creado_por' => $creado_por
        ]);

        echo json_encode(['status' => true, 'message' => 'Publicación creada']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
