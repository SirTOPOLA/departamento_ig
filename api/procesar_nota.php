<?php
require '../includes/conexion.php';

$logPath = "logs/log.txt";
$logProcesadoPath = "logs/log_procesado.txt";
$errores = [];
$procesados = [];

// Obtener el año académico activo
$stmtAnio = $pdo->query("SELECT id_anio FROM anios_academicos WHERE activo = TRUE LIMIT 1");
$id_anio = $stmtAnio->fetchColumn();

if (!$id_anio) {
    exit("No hay un año académico activo configurado.");
}

// Verificar si el archivo existe
if (!file_exists($logPath)) {
    exit("No se encontró el archivo de log.");
}

$lineas = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($lineas as $linea) {
    if (!preg_match('/^REG-\w+ \| estudiante:\d+ \| asignatura:\d+ \| p1:[\d.]+ \| p2:[\d.]+ \| final:[\d.]+ \| obs:/', $linea)) {
        continue;
    }

    preg_match('/^REG-(\w+).*estudiante:(\d+).*asignatura:(\d+).*p1:([\d.]+).*p2:([\d.]+).*final:([\d.]+).*obs:(.*)$/', $linea, $matches);

    if (count($matches) !== 8) {
        $errores[] = "Línea inválida: $linea";
        continue;
    }

    list(, $refID, $id_estudiante, $id_asignatura, $p1, $p2, $final, $obs) = $matches;
    $p1 = floatval($p1);
    $p2 = floatval($p2);
    $final = floatval($final);
    $obs = trim($obs);

    foreach ([$p1, $p2, $final] as $nota) {
        if ($nota < 0 || $nota > 10) {
            $errores[] = "❌ Nota fuera de rango en línea: $linea";
            continue 2;
        }
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notas WHERE id_estudiante = ? AND id_asignatura = ?");
    $stmt->execute([$id_estudiante, $id_asignatura]);
    if ($stmt->fetchColumn() > 0) {
        $errores[] = "⚠️ Nota ya existe para estudiante $id_estudiante y asignatura $id_asignatura";
        continue;
    }

    $insert = $pdo->prepare("INSERT INTO notas (id_estudiante, id_asignatura, parcial_1, parcial_2, examen_final, observaciones, id_anio)
                             VALUES (?, ?, ?, ?, ?, ?, ?)");
    $exito = $insert->execute([$id_estudiante, $id_asignatura, $p1, $p2, $final, $obs, $id_anio]);

    if ($exito) {
        $procesados[] = $linea;
    } else {
        $errores[] = "❌ Error al insertar línea: $linea";
    }
}

if (!empty($procesados)) {
    file_put_contents($logProcesadoPath, implode("\n", $procesados) . "\n", FILE_APPEND);
    $lineasRestantes = array_diff($lineas, $procesados);
    file_put_contents($logPath, implode("\n", $lineasRestantes));
}

echo "<h3>Notas procesadas: " . count($procesados) . "</h3>";
if (!empty($errores)) {
    echo "<h4>Errores:</h4><ul>";
    foreach ($errores as $e) {
        echo "<li>" . htmlspecialchars($e) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>Sin errores.</p>";
}
?>
