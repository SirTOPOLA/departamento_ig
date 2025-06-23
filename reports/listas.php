<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'profesor') {
    header("Location: ../login.php");
    exit;
}
require '../includes/conexion.php';
require('../fpdf/fpdf.php');

$id_profesor = $_SESSION['id_usuario'] ?? null;
if (!$id_profesor) {
    die("Error: Profesor no identificado.");
}

$asignatura_nombre = $_GET['asignatura'] ?? '';
if (!$asignatura_nombre) {
    die("Error: Asignatura no especificada.");
}

// Datos del departamento
$dep = $pdo->query("SELECT * FROM departamento LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$dep) {
    die("Error: No hay datos del departamento.");
}

// Consulta con semestre, grupo y turno
$sql = "SELECT
  u.nombre, u.apellido, es.matricula, u.dni,
  c.nombre AS curso, c.turno, c.grupo,
  s.nombre AS semestre, a.nombre AS asignatura
FROM asignaturas a
INNER JOIN asignatura_profesor ap ON ap.id_asignatura = a.id_asignatura
INNER JOIN cursos c ON a.curso_id = c.id_curso
INNER JOIN semestres s ON a.semestre_id = s.id_semestre
INNER JOIN asignatura_estudiante ae ON ae.id_asignatura = a.id_asignatura
INNER JOIN estudiantes es ON ae.id_estudiante = es.id_estudiante
INNER JOIN usuarios u ON es.id_estudiante = u.id_usuario
WHERE ap.id_profesor = :id_profesor AND a.nombre = :asignatura_nombre
ORDER BY u.apellido, u.nombre";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'id_profesor' => $id_profesor,
    'asignatura_nombre' => $asignatura_nombre
]);
$estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$estudiantes) {
    die("No hay estudiantes para esta asignatura.");
}

// Tomamos datos únicos para cabecera
$infoCurso = $estudiantes[0]; // Ya que todos están en mismo curso/asignatura

class PDF extends FPDF {
    public $dep;
    public $curso;

    function Header() {
        $logoPais = isset($this->dep['logo_pais']) ? '../api/' . $this->dep['logo_pais'] : '';
        $logoUnge = isset($this->dep['logo_unge']) ? '../api/' . $this->dep['logo_unge'] : '';
    
        if (!empty($logoPais) && file_exists($logoPais)) {
            $this->Image($logoPais, 10, 8, 30);
        }
        if (!empty($logoUnge) && file_exists($logoUnge)) {
            $this->Image($logoUnge, 170, 8, 30);
        }
    
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, utf8_decode(strtoupper($this->dep['universidad'])), 0, 1, 'C');
        $this->Cell(0, 8, utf8_decode(strtoupper($this->dep['nombre'])), 0, 1, 'C');
        $this->Ln(2);
    
        $this->SetFont('Arial', 'B', 13);
        $this->Cell(0, 10, utf8_decode("LISTA DE ESTUDIANTES"), 0, 1, 'C');
    
        // Fecha actual
        $fecha_actual = date('d/m/Y');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, utf8_decode("Fecha: $fecha_actual"), 0, 1, 'C');
    
        $this->SetFont('Arial', '', 11);
        $this->Cell(0, 6, utf8_decode("Asignatura: " . $this->curso['asignatura']), 0, 1, 'C');
        $this->Cell(0, 6, utf8_decode("Semestre: " . $this->curso['semestre'] . " | Turno: " . ucfirst($this->curso['turno']) . " | Grupo: " . $this->curso['grupo']), 0, 1, 'C');
        $this->Ln(5);
    }
    

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->dep = $dep;
$pdf->curso = $infoCurso;
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 11);

// Cabecera de tabla
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(10, 10, '#', 1, 0, 'C', true);
$pdf->Cell(45, 10, utf8_decode('Nombre'), 1, 0, 'C', true);
$pdf->Cell(45, 10, utf8_decode('Apellido'), 1, 0, 'C', true);
$pdf->Cell(40, 10, utf8_decode('Matrícula'), 1, 0, 'C', true);
$pdf->Cell(40, 10, 'DNI', 1, 1, 'C', true);

// Filas
foreach ($estudiantes as $i => $est) {
    $pdf->Cell(10, 10, $i + 1, 1);
    $pdf->Cell(45, 10, utf8_decode($est['nombre']), 1);
    $pdf->Cell(45, 10, utf8_decode($est['apellido']), 1);
    $pdf->Cell(40, 10, utf8_decode($est['matricula']), 1);
    $pdf->Cell(40, 10, $est['dni'], 1);
    $pdf->Ln();
}

$pdf->Output();
