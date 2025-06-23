<?php
require_once '../includes/conexion.php';
require('../fpdf/fpdf.php');

// Obtener ID del estudiante
$id = isset($_GET['id_estudiante']) ? (int)$_GET['id_estudiante'] : 0;

// Obtener información del estudiante
$stmt = $pdo->prepare("SELECT u.nombre, u.apellido, u.email, e.matricula
                       FROM estudiantes e
                       JOIN usuarios u ON e.id_estudiante = u.id_usuario
                       WHERE e.id_estudiante = ?");
$stmt->execute([$id]);
$e = $stmt->fetch();

// Iniciar PDF
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, utf8_decode('REPORTE ACADÉMICO'), 0, 1, 'C');
$pdf->Ln(5);

// Datos del estudiante
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, utf8_decode("Nombre: {$e['nombre']} {$e['apellido']}"), 0, 1);
$pdf->Cell(0, 10, utf8_decode("Matrícula: {$e['matricula']}"), 0, 1);
$pdf->Cell(0, 10, utf8_decode("Email: {$e['email']}"), 0, 1);
$pdf->Ln(5);

// Obtener notas del estudiante
$stmt2 = $pdo->prepare("SELECT a.nombre AS asignatura, c.nombre AS curso, s.nombre AS semestre,
                               n.parcial_1, n.parcial_2, n.examen_final, n.promedio
                        FROM notas n
                        JOIN asignaturas a ON n.id_asignatura = a.id_asignatura
                        LEFT JOIN cursos c ON a.curso_id = c.id_curso
                        LEFT JOIN semestres s ON a.semestre_id = s.id_semestre
                        WHERE n.id_estudiante = ?");
$stmt2->execute([$id]);
$notas = $stmt2->fetchAll();

// Encabezado de tabla
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(200, 220, 255);
$pdf->Cell(50, 8, utf8_decode('Asignatura'), 1, 0, 'C', true);
$pdf->Cell(30, 8, 'Curso', 1, 0, 'C', true);
$pdf->Cell(30, 8, 'Semestre', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'P1', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'P2', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Final', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'Prom.', 1, 1, 'C', true);

// Cuerpo de tabla
$pdf->SetFont('Arial', '', 10);
foreach ($notas as $n) {
    $pdf->Cell(50, 8, utf8_decode($n['asignatura']), 1);
    $pdf->Cell(30, 8, utf8_decode($n['curso']), 1);
    $pdf->Cell(30, 8, utf8_decode($n['semestre']), 1);
    $pdf->Cell(20, 8, $n['parcial_1'], 1, 0, 'C');
    $pdf->Cell(20, 8, $n['parcial_2'], 1, 0, 'C');
    $pdf->Cell(25, 8, $n['examen_final'], 1, 0, 'C');
    $pdf->Cell(20, 8, $n['promedio'], 1, 1, 'C');
}

// Mostrar PDF
$pdf->Output();
