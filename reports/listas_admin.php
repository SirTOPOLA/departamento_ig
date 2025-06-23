<?php
require '../includes/conexion.php';
require '../fpdf/fpdf.php';

session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'profesor') {
    die("Acceso denegado.");
}

$id_profesor = $_SESSION['id_usuario'] ?? null;
$asignatura_id = $_GET['asignatura_id'] ?? null;

if (!$id_profesor || !$asignatura_id) {
    die("Datos faltantes.");
}

// === Obtener asignatura ===
$stmt = $pdo->prepare("SELECT nombre FROM asignaturas WHERE id_asignatura = ?");
$stmt->execute([$asignatura_id]);
$asignatura = $stmt->fetchColumn();

// === Obtener datos del departamento ===
$dep = $pdo->query("SELECT * FROM departamento LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$nombre_uni = $dep['universidad'] ?? 'Universidad';
$nombre_dep = $dep['nombre'] ?? 'Departamento Académico';
$logo_pais = '../api/' . $dep['logo_pais'];
$logo_unge = '../api/' . $dep['logo_unge'];

// === Año académico actual ===
$mes_actual = date('n');
$anio_inicio = $mes_actual >= 8 ? date('Y') : date('Y') - 1;
$anio_fin = $anio_inicio + 1;
$anio_actual = "{$anio_inicio}-{$anio_fin}";

$stmt = $pdo->prepare("SELECT * FROM anios_academicos WHERE anio = ? LIMIT 1");
$stmt->execute([$anio_actual]);
$anio_acad = $stmt->fetch(PDO::FETCH_ASSOC);
$anio_visible = $anio_acad ? $anio_acad['anio'] : $anio_actual;

// === Obtener estudiantes ===
$stmt = $pdo->prepare("
    SELECT u.nombre, u.apellido, es.matricula,
           n.parcial_1, n.parcial_2, n.examen_final, n.promedio
    FROM usuarios u
    INNER JOIN estudiantes es ON es.id_estudiante = u.id_usuario
    INNER JOIN asignatura_estudiante ae ON ae.id_estudiante = es.id_estudiante
    LEFT JOIN notas n ON n.id_estudiante = es.id_estudiante 
                     AND n.id_asignatura = ?
");
$stmt->execute([$asignatura_id]);
$estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// === Crear PDF ===
$pdf = new FPDF();
$pdf->AddPage();

// --- Encabezado gráfico ---
if (file_exists($logo_pais)) {
    $pdf->Image($logo_pais, 10, 8, 25); // Logo país a la izquierda
}
if (file_exists($logo_unge)) {
    $pdf->Image($logo_unge, 175, 8, 25); // Logo universidad a la derecha
}

// --- Títulos ---
$pdf->SetFont('Arial', 'B', 12);
$pdf->Ln(10);
$pdf->Cell(0, 6, utf8_decode($nombre_uni), 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, utf8_decode($nombre_dep), 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, utf8_decode("Listado de estudiantes con calificaciones"), 0, 1, 'C');
$pdf->Cell(0, 6, utf8_decode("Año Académico: $anio_visible"), 0, 1, 'C');
$pdf->Ln(5);

// --- Título asignatura ---
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Reporte de Notas - ' . utf8_decode($asignatura), 0, 1, 'C');
$pdf->Ln(2);

// --- Tabla ---
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(10, 8, '#', 1, 0, 'C');
$pdf->Cell(50, 8, 'Estudiante', 1);
$pdf->Cell(30, 8, 'Matrícula', 1);
$pdf->Cell(20, 8, 'P1', 1, 0, 'C');
$pdf->Cell(20, 8, 'P2', 1, 0, 'C');
$pdf->Cell(30, 8, 'Final', 1, 0, 'C');
$pdf->Cell(20, 8, 'Prom.', 1, 1, 'C');

$pdf->SetFont('Arial', '', 10);
foreach ($estudiantes as $i => $e) {
    $pdf->Cell(10, 8, $i + 1, 1, 0, 'C');
    $pdf->Cell(50, 8, utf8_decode($e['nombre'] . ' ' . $e['apellido']), 1);
    $pdf->Cell(30, 8, $e['matricula'], 1);
    $pdf->Cell(20, 8, $e['parcial_1'], 1, 0, 'C');
    $pdf->Cell(20, 8, $e['parcial_2'], 1, 0, 'C');
    $pdf->Cell(30, 8, $e['examen_final'], 1, 0, 'C');
    $pdf->Cell(20, 8, $e['promedio'], 1, 1, 'C');
}

$pdf->Output('I', 'Reporte_Notas_' . $asignatura . '.pdf');
?>
