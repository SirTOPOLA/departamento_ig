<?php
session_start();
require_once '../includes/conexion.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'estudiante') {
    die("Acceso denegado.");
}

$id_estudiante = $_SESSION['id_usuario'] ?? null;
$id_anio = $_POST['id_anio'] ?? null;

if (!$id_estudiante || !$id_anio || !isset($_POST['asignaturas'])) {
    die("Datos incompletos.");
}

$asignaturas_por_semestre = $_POST['asignaturas'];
$errores = 0;
$total = 0;

foreach ($asignaturas_por_semestre as $id_semestre => $asignaturas) {
    foreach ($asignaturas as $id_asignatura) {
        // Evitar duplicados
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscripciones WHERE id_estudiante = ? AND id_asignatura = ? AND id_anio = ?");
        $stmt->execute([$id_estudiante, $id_asignatura, $id_anio]);
        if ($stmt->fetchColumn() > 0) continue;

        $stmt = $pdo->prepare("
            INSERT INTO inscripciones (id_estudiante, id_asignatura, id_anio, id_semestre)
            VALUES (?, ?, ?, ?)
        ");
        if (!$stmt->execute([$id_estudiante, $id_asignatura, $id_anio, $id_semestre])) {
            $errores++;
        }
        $total++;
    }
}

header("Location: inscribir.php?resultado=" . ($errores ? "error" : "ok") . "&total=$total");
exit;
