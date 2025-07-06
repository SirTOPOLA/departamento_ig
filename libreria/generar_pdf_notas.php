<?php
require('fpdf.php');
require('../config/database.php'); // Tu archivo de conexión PDO
require_once '../includes/functions.php'; // Ajusta la ruta si es necesario

// Asegúrate de que solo los estudiantes puedan acceder a esta página
check_login_and_role('Estudiante');

$id_estudiante = $_GET['id_estudiante'] ?? 0;

if (empty($id_estudiante)) {
    die('Estudiante no encontrado');
}

// Consulta para obtener datos del estudiante
$stmtEst = $pdo->prepare("
    SELECT e.codigo_registro, u.nombre_completo
    FROM estudiantes e
    INNER JOIN usuarios u ON e.id_usuario = u.id
    WHERE e.id = :id_estudiante
");
$stmtEst->execute(['id_estudiante' => $id_estudiante]);
$estudiante = $stmtEst->fetch(PDO::FETCH_ASSOC);

if (!$estudiante) {
    die('Estudiante no encontrado en la base de datos');
}

// Consulta para el historial
$stmtHist = $pdo->prepare("
    SELECT 
        a.nombre_asignatura,
        a.creditos,
        n.nota,
        n.estado,
        s.numero_semestre,
        aa.nombre_anio
    FROM notas n
    INNER JOIN inscripciones_estudiantes ie ON n.id_inscripcion = ie.id
    INNER JOIN asignaturas a ON ie.id_asignatura = a.id
    INNER JOIN semestres s ON ie.id_semestre = s.id
    INNER JOIN anios_academicos aa ON s.id_anio_academico = aa.id
    WHERE ie.id_estudiante = :id_estudiante
      AND n.acta_final_confirmada = 1
    ORDER BY aa.fecha_inicio, s.numero_semestre, a.nombre_asignatura
");
$stmtHist->execute(['id_estudiante' => $id_estudiante]);
$historial = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

// Generación del PDF
ob_clean(); // Limpia cualquier salida previa
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 14);

// Título
$pdf->Cell(0, 10, 'Historial Academico', 0, 1, 'C');
$pdf->Ln(5);

// Datos del estudiante
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 8, 'Nombre: ' . utf8_decode($estudiante['nombre_completo']), 0, 1);
$pdf->Cell(0, 8, 'Codigo Registro: ' . $estudiante['codigo_registro'], 0, 1);
$pdf->Ln(5);

// Encabezados de tabla
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(60, 8, 'Asignatura', 1);
$pdf->Cell(20, 8, 'Creditos', 1, 0, 'C');
$pdf->Cell(20, 8, 'Nota', 1, 0, 'C');
$pdf->Cell(30, 8, 'Estado', 1, 0, 'C');
$pdf->Cell(20, 8, 'Semestre', 1, 0, 'C');
$pdf->Cell(40, 8, utf8_decode('Año Académico'), 1, 1, 'C');

$pdf->SetFont('Arial', '', 10);

if (count($historial) === 0) {
    $pdf->Cell(0, 8, 'No hay registros con acta final confirmada.', 1, 1, 'C');
} else {
    foreach ($historial as $row) {
        $pdf->Cell(60, 8, utf8_decode($row['nombre_asignatura']), 1);
        $pdf->Cell(20, 8, $row['creditos'], 1, 0, 'C');
        $pdf->Cell(20, 8, $row['nota'], 1, 0, 'C');
        $pdf->Cell(30, 8, $row['estado'], 1, 0, 'C');
        $pdf->Cell(20, 8, $row['numero_semestre'], 1, 0, 'C');
        $pdf->Cell(40, 8, utf8_decode($row['nombre_anio']), 1, 1, 'C');
    }
}

// Salida del PDF
$pdf->Output('I', 'Historial_Academico.pdf');
