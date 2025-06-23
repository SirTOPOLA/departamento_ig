<?php
 
require_once '../includes/conexion.php';
header('Content-Type: application/json');

// Función para subir archivos
function subirArchivo($input_name, $carpeta_destino = 'uploads/departamento/')
{
    if (!isset($_FILES[$input_name]) || $_FILES[$input_name]['error'] !== 0) {
        return null;
    }

    $archivo = $_FILES[$input_name];
    $permitidos = ['image/jpeg', 'image/png', 'image/jpg'];
    $max_tamano = 2 * 1024 * 1024; // 2MB

    if (!in_array($archivo['type'], $permitidos)) {
        return ['error' => 'Solo se permiten archivos JPG y PNG'];
    }

    if ($archivo['size'] > $max_tamano) {
        return ['error' => 'El archivo excede el tamaño máximo de 2MB'];
    }

    if (!is_dir($carpeta_destino)) {
        mkdir($carpeta_destino, 0755, true);
    }

    $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
    $nombre_final = uniqid('img_', true) . '.' . strtolower($extension);
    $ruta_final = $carpeta_destino . $nombre_final;

    if (move_uploaded_file($archivo['tmp_name'], $ruta_final)) {
        return str_replace('../', '', $ruta_final); // Ruta relativa
    } else {
        return ['error' => 'Error al mover el archivo al servidor'];
    }
}

// Datos
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$nombre = trim($_POST['nombre'] ?? '');
$universidad = trim($_POST['universidad'] ?? '');
$historia = trim($_POST['historia'] ?? '');
$info_matricula = trim($_POST['info_matricula'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$horario = trim($_POST['horario'] ?? '');

// Validación obligatoria
if ($nombre === '' || $universidad === '') {
    echo json_encode(['status' => false, 'message' => 'Los campos nombre y universidad son obligatorios']);
    exit;
}

// Subidas de archivos
$ruta_imagen = subirArchivo('imagen');
$ruta_logo_unge = subirArchivo('logo_unge');
$ruta_logo_pais = subirArchivo('logo_pais');

// Manejar errores de subida
foreach (['imagen' => $ruta_imagen, 'logo_unge' => $ruta_logo_unge, 'logo_pais' => $ruta_logo_pais] as $campo => $valor) {
    if (is_array($valor) && isset($valor['error'])) {
        echo json_encode(['status' => false, 'message' => 'Error en ' . $campo . ': ' . $valor['error']]);
        exit;
    }
}

try {
    if ($id > 0) {
        // Actualizar
        $sql = "UPDATE departamento SET 
                    nombre = :nombre,
                    universidad = :universidad,
                    historia = :historia,
                    info_matricula = :info_matricula,
                    direccion = :direccion,
                    telefono = :telefono,
                    horario = :horario";

        $params = [
            ':nombre' => $nombre,
            ':universidad' => $universidad,
            ':historia' => $historia,
            ':info_matricula' => $info_matricula,
            ':direccion' => $direccion,
            ':telefono' => $telefono,
            ':horario' => $horario,
        ];

        if ($ruta_imagen) {
            $sql .= ", imagen = :imagen";
            $params[':imagen'] = $ruta_imagen;
        }
        if ($ruta_logo_unge) {
            $sql .= ", logo_unge = :logo_unge";
            $params[':logo_unge'] = $ruta_logo_unge;
        }
        if ($ruta_logo_pais) {
            $sql .= ", logo_pais = :logo_pais";
            $params[':logo_pais'] = $ruta_logo_pais;
        }

        $sql .= " WHERE id_departamento = :id";
        $params[':id'] = $id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo json_encode(['status' => true, 'message' => 'Departamento actualizado correctamente']);
    } else {
        // Verificar existencia previa
        $existe = $pdo->query("SELECT COUNT(*) FROM departamento")->fetchColumn();
        if ($existe > 0) {
            echo json_encode(['status' => false, 'message' => 'Ya existe un departamento registrado']);
            exit;
        }

        // Insertar
        $stmt = $pdo->prepare("INSERT INTO departamento 
            (nombre, universidad, historia, imagen, logo_unge, logo_pais, info_matricula, direccion, telefono, horario)
            VALUES (:nombre, :universidad, :historia, :imagen, :logo_unge, :logo_pais, :info_matricula, :direccion, :telefono, :horario)");

        $stmt->execute([
            ':nombre' => $nombre,
            ':universidad' => $universidad,
            ':historia' => $historia,
            ':imagen' => $ruta_imagen ?: '',
            ':logo_unge' => $ruta_logo_unge ?: '',
            ':logo_pais' => $ruta_logo_pais ?: '',
            ':info_matricula' => $info_matricula,
            ':direccion' => $direccion,
            ':telefono' => $telefono,
            ':horario' => $horario
        ]);

        echo json_encode(['status' => true, 'message' => 'Departamento registrado correctamente']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}

