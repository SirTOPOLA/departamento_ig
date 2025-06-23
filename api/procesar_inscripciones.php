<?php
require '../includes/conexion.php';
session_start();

$id_estudiante = $_SESSION['id_usuario'] ?? null;

if (!$id_estudiante) {
    die("No has iniciado sesión.");
}

if (!isset($_POST['id_semestre'], $_POST['id_asignaturas']) || !is_array($_POST['id_asignaturas'])) {
    die("Datos incompletos o inválidos.");
}

$id_semestre = intval($_POST['id_semestre']);
$asignaturas = $_POST['id_asignaturas']; // array de ids de asignaturas
$estado = $_POST['estado'] ?? 'preinscrito';

// Obtener id_curso del estudiante (opcional, para validaciones extra)
$stmt = $pdo->prepare("SELECT id_curso FROM estudiantes WHERE id_estudiante = ?");
$stmt->execute([$id_estudiante]);
$id_curso = $stmt->fetchColumn();

if (!$id_curso) {
    die("Curso del estudiante no encontrado.");
}

// Obtener id_anio activo (año académico vigente)
$stmt = $pdo->query("SELECT id_anio FROM anios_academicos WHERE activo = 1 LIMIT 1");
$id_anio = $stmt->fetchColumn();

if (!$id_anio) {
    die("No hay año académico activo.");
}

// Validar que no seleccione más de 6 asignaturas
if (count($asignaturas) > 6) {
    $_SESSION['mensaje_error'] = ["Solo puedes inscribirte en máximo 6 asignaturas."];
    header('Location: ../estudiante/inscripciones.php');
    exit;
}

// Insertar inscripciones (evitando duplicados)
$insertados = 0;
$errores = [];

foreach ($asignaturas as $id_asig_raw) {
    $id_asignatura = intval($id_asig_raw);

    // Validar que la asignatura pertenezca al curso y semestre seleccionado (opcional)
    $stmtVal = $pdo->prepare("SELECT 1 FROM asignaturas WHERE id_asignatura = ? AND curso_id = ? AND semestre_id = ?");
    $stmtVal->execute([$id_asignatura, $id_curso, $id_semestre]);
    if (!$stmtVal->fetch()) {
        $errores[] = "La asignatura ID $id_asignatura no corresponde al curso y semestre seleccionados.";
        continue;
    }

    // Verificar si ya está inscrito
    $stmtCheck = $pdo->prepare("
        SELECT 1 FROM inscripciones 
        WHERE id_estudiante = ? AND id_asignatura = ? AND id_anio = ? AND id_semestre = ?
    ");
    $stmtCheck->execute([$id_estudiante, $id_asignatura, $id_anio, $id_semestre]);
    if ($stmtCheck->fetch()) {
        $errores[] = "Ya estás inscrito en la asignatura ID $id_asignatura.";
        continue;
    }

    // Insertar
    $stmtInsert = $pdo->prepare("
        INSERT INTO inscripciones (id_estudiante, id_asignatura, id_anio, id_semestre, estado)
        VALUES (?, ?, ?, ?, ?)
    ");
    try {
        $stmtInsert->execute([$id_estudiante, $id_asignatura, $id_anio, $id_semestre, $estado]);
        $insertados++;
    } catch (PDOException $e) {
        $errores[] = "Error al inscribir la asignatura ID $id_asignatura.";
    }
}

// Guardar mensajes en sesión
if ($insertados > 0) {
    $_SESSION['mensaje_exito'] = "Se inscribieron correctamente $insertados asignatura(s).";
}

if (!empty($errores)) {
    $_SESSION['mensaje_error'] = $errores;
}

// Redireccionar a la página de inscripción
header('Location: ../estudiante/inscripciones.php');
exit;
