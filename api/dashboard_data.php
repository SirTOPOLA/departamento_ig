<?php
require_once '../includes/conexion.php';  // Ajusta la ruta según tu proyecto

header('Content-Type: application/json');

try {
    // Total usuarios activos
    $totalUsuarios = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE estado = 1")->fetchColumn();

    // Total estudiantes activos
    $totalEstudiantes = $pdo->query("SELECT COUNT(*) FROM estudiantes e JOIN usuarios u ON e.id_estudiante = u.id_usuario WHERE u.estado = 1")->fetchColumn();

    // Total profesores activos
    $totalProfesores = $pdo->query("SELECT COUNT(*) FROM profesores p JOIN usuarios u ON p.id_profesor = u.id_usuario WHERE u.estado = 1")->fetchColumn();

    // Total cursos
    $totalCursos = $pdo->query("SELECT COUNT(*) FROM cursos")->fetchColumn();

    // Total semestres
    $totalSemestres = $pdo->query("SELECT COUNT(*) FROM semestres")->fetchColumn();

    // Total asignaturas
    $totalAsignaturas = $pdo->query("SELECT COUNT(*) FROM asignaturas")->fetchColumn();

    // Total aulas
    $totalAulas = $pdo->query("SELECT COUNT(*) FROM aulas")->fetchColumn();

    // Total horarios
    $totalHorarios = $pdo->query("SELECT COUNT(*) FROM horarios")->fetchColumn();

    // Total publicaciones visibles
    $totalPublicaciones = $pdo->query("SELECT COUNT(*) FROM publicaciones WHERE visible = 1")->fetchColumn();

    // Total requisitos de matrícula visibles
    $totalRequisitos = $pdo->query("SELECT COUNT(*) FROM requisitos_matricula WHERE visible = 1")->fetchColumn();

    // Total notas registradas
    $totalNotas = $pdo->query("SELECT COUNT(*) FROM notas")->fetchColumn();

    echo json_encode([
        'status' => true,
        'totales' => [
            'usuarios' => (int)$totalUsuarios,
            'estudiantes' => (int)$totalEstudiantes,
            'profesores' => (int)$totalProfesores,
            'cursos' => (int)$totalCursos,
            'semestres' => (int)$totalSemestres,
            'asignaturas' => (int)$totalAsignaturas,
            'aulas' => (int)$totalAulas,
            'horarios' => (int)$totalHorarios,
            'publicaciones' => (int)$totalPublicaciones,
            'requisitos' => (int)$totalRequisitos,
            'notas' => (int)$totalNotas
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'status' => false,
        'message' => 'Error en la base de datos: ' . $e->getMessage()
    ]);
}
