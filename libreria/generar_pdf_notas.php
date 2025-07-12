<?php
require('fpdf.php');
require('../config/database.php');
require_once '../includes/functions.php';

// Función para convertir texto UTF-8 a ISO-8859-1 (usado por FPDF)
function utf($text) {
    return utf8_decode($text);
}

// Validar acceso
check_login_and_role('Estudiante');

// ID del estudiante
$id_estudiante = $_GET['id_estudiante'] ?? 0;
if (empty($id_estudiante)) {
    die('Estudiante no encontrado');
}

// Datos del estudiante
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

// Datos del departamento
$stmtDep = $pdo->prepare("SELECT * FROM departamento ");
$stmtDep->execute( );
$departamento = $stmtDep->fetch(PDO::FETCH_ASSOC);

// Historial académico
$stmtHist = $pdo->prepare("
    SELECT a.nombre_asignatura, a.creditos, n.nota, n.estado, s.numero_semestre, aa.nombre_anio
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

// Iniciar PDF
ob_clean();
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetMargins(15, 15, 15);

// -------------------- Encabezado con logos --------------------
if (file_exists('../' . $departamento['logo_pais'])) {
    $pdf->Image('../' . $departamento['logo_pais'], 15, 10, 25); // Izquierda
}
if (file_exists('../' . $departamento['logo_unge'])) {
    $pdf->Image('../' . $departamento['logo_unge'], 170, 10, 25); // Derecha
}

// Encabezado institucional
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, utf($departamento['universidad']), 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, utf('FACULTAD DE CIENCIAS ECONÓMICAS, GESTIÓN Y ADMINISTRACIÓN'), 0, 1, 'C');
$pdf->Cell(0, 8, utf(strtoupper($departamento['nombre'])), 0, 1, 'C');

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, utf('Dirección: ' . $departamento['direccion']), 0, 1, 'C');
$pdf->Cell(0, 6, utf('Teléfono: ' . $departamento['telefono']), 0, 1, 'C');
$pdf->Ln(8);

// -------------------- Título --------------------
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, utf('HISTORIAL ACADÉMICO'), 0, 1, 'C');
$pdf->Ln(5);

// -------------------- Datos del estudiante --------------------
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 8, utf('Nombre: ' . $estudiante['nombre_completo']), 0, 1);
$pdf->Cell(0, 8, utf('Código de Registro: ' . $estudiante['codigo_registro']), 0, 1);
$pdf->Ln(5);

// -------------------- Tabla de historial --------------------
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(230, 230, 230); // Color de encabezado
$pdf->Cell(60, 8, utf('Asignatura'), 1, 0, 'C', true);
$pdf->Cell(20, 8, utf('Créditos'), 1, 0, 'C', true);
$pdf->Cell(20, 8, utf('Nota'), 1, 0, 'C', true);
$pdf->Cell(30, 8, utf('Estado'), 1, 0, 'C', true);
$pdf->Cell(20, 8, utf('Sem.'), 1, 0, 'C', true);
$pdf->Cell(40, 8, utf('Año Académico'), 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 10);
if (count($historial) === 0) {
    $pdf->Cell(0, 8, utf('No hay registros con acta final confirmada.'), 1, 1, 'C');
} else {
    foreach ($historial as $row) {
        $pdf->Cell(60, 8, utf($row['nombre_asignatura']), 1);
        $pdf->Cell(20, 8, $row['creditos'], 1, 0, 'C');
        $pdf->Cell(20, 8, $row['nota'], 1, 0, 'C');
        $pdf->Cell(30, 8, utf($row['estado']), 1, 0, 'C');
        $pdf->Cell(20, 8, $row['numero_semestre'], 1, 0, 'C');
        $pdf->Cell(40, 8, utf($row['nombre_anio']), 1, 1, 'C');
    }
}

$pdf->Ln(10);
$pdf->SetFont('Arial', 'I', 9);
$pdf->Cell(0, 5, utf('Documento generado automáticamente el ' . date('d/m/Y') . '.'), 0, 1, 'R');

// Salida del PDF
$pdf->Output('I', 'Historial_Academico_' . $estudiante['codigo_registro'] . '.pdf');
