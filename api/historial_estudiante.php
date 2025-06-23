<?php
require_once '../includes/conexion.php';
header('Content-Type: application/json');

$id = isset($_GET['id_estudiante']) ? (int)$_GET['id_estudiante'] : 0;
if ($id <= 0) {
  echo json_encode(['status' => false, 'message' => 'ID inválido']);
  exit;
}

// ✅ Datos del estudiante (corregido)
$stmt = $pdo->prepare("SELECT u.nombre, u.apellido, u.email, e.matricula
                       FROM estudiantes e
                       JOIN usuarios u ON u.id_usuario = e.id_estudiante
                       WHERE e.id_estudiante = ?");
$stmt->execute([$id]);
$estudiante = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$estudiante) {
  echo json_encode(['status' => false, 'message' => 'Estudiante no encontrado']);
  exit;
}

// ✅ Obtener asignaturas con notas (aunque no tenga aún)
$stmt2 = $pdo->prepare("
    SELECT 
        a.nombre AS asignatura,
        c.nombre AS curso,
        s.nombre AS semestre,
        IFNULL(n.parcial_1, NULL) AS parcial_1,
        IFNULL(n.parcial_2, NULL) AS parcial_2,
        IFNULL(n.examen_final, NULL) AS examen_final,
        IFNULL(n.promedio, NULL) AS promedio,
        IFNULL(n.observaciones, '') AS observaciones
    FROM asignatura_estudiante ae
    JOIN asignaturas a ON ae.id_asignatura = a.id_asignatura
    LEFT JOIN cursos c ON a.curso_id = c.id_curso
    LEFT JOIN semestres s ON a.semestre_id = s.id_semestre
    LEFT JOIN notas n ON n.id_asignatura = a.id_asignatura AND n.id_estudiante = ae.id_estudiante
    WHERE ae.id_estudiante = ?
    ORDER BY c.nombre, s.nombre, a.nombre
");
$stmt2->execute([$id]);
$notas = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// ✅ Devolver resultado limpio
echo json_encode([
  'status' => true,
  'estudiante' => $estudiante,
  'notas' => $notas
]);
