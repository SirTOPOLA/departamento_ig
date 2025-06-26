<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'profesor') {
    header("Location: ../index.php"); // Redirigir si el rol no es profesor
    exit;
}

require '../includes/conexion.php'; // Asegúrate de que $pdo esté disponible
require('../fpdf/fpdf.php'); // Asegúrate de que la ruta a FPDF sea correcta

// Obtener ID del profesor de la sesión
$id_profesor = $_SESSION['id_usuario'] ?? null;
if (!$id_profesor) {
    die("Error: Profesor no identificado. Por favor, inicie sesión nuevamente.");
}

// Obtener los IDs de la URL
$id_asignatura = $_GET['id_asignatura'] ?? null;
$id_curso = $_GET['id_curso'] ?? null;
$id_semestre = $_GET['id_semestre'] ?? null;
$id_anio = $_GET['id_anio'] ?? null;

// Validar que todos los IDs necesarios estén presentes
if (!$id_asignatura || !$id_curso || !$id_semestre || !$id_anio) {
    die("Error: Faltan parámetros para generar el PDF (ID de Asignatura, Curso, Semestre o Año).");
}

try {
    // Datos del departamento (asumiendo una tabla 'departamento' para la información de la institución)
    // Ajusta esta consulta si tu tabla de departamento tiene otro nombre o estructura.
    $dep = $pdo->query("SELECT logo_pais, logo_unge, universidad, nombre FROM departamento LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$dep) {
        // Si no hay datos de departamento, se puede usar valores por defecto o mostrar un error
        $dep = [
            'logo_pais' => null,
            'logo_unge' => null,
            'universidad' => 'Universidad Desconocida',
            'nombre' => 'Departamento Desconocido'
        ];
    }

    // Consulta para obtener los estudiantes de la asignatura, curso, semestre y año específicos
    // También verifica que el profesor esté asignado a esta asignatura.
    $sql = "SELECT
        u.nombre AS nombre_estudiante,
        u.apellido AS apellido_estudiante,
        es.matricula,
        u.dni,
        a.nombre AS asignatura_nombre,
        c.nombre AS curso_nombre,
        c.turno,
        c.grupo,
        s.nombre AS semestre_nombre,
        an.anio AS anio_academico
    FROM
        inscripciones i
    INNER JOIN
        usuarios u ON i.id_estudiante = u.id_usuario
    INNER JOIN
        estudiantes es ON u.id_usuario = es.id_estudiante
    INNER JOIN
        asignaturas a ON i.id_asignatura = a.id_asignatura
    INNER JOIN
        cursos c ON a.curso_id = c.id_curso
    INNER JOIN
        semestres s ON a.semestre_id = s.id_semestre
    INNER JOIN
        anios_academicos an ON i.id_anio = an.id_anio
    INNER JOIN
        asignatura_profesor ap ON a.id_asignatura = ap.id_asignatura -- Para verificar que la asignatura está asignada al profesor
    WHERE
        ap.id_profesor = :id_profesor AND
        i.id_asignatura = :id_asignatura AND
        a.curso_id = :id_curso AND
        i.id_semestre = :id_semestre AND
        i.id_anio = :id_anio AND
        i.estado = 'confirmado' -- Solo estudiantes con inscripción confirmada
    ORDER BY
        u.apellido, u.nombre";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'id_profesor' => $id_profesor,
        'id_asignatura' => $id_asignatura,
        'id_curso' => $id_curso,
        'id_semestre' => $id_semestre,
        'id_anio' => $id_anio
    ]);
    $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($estudiantes)) {
        die("No hay estudiantes confirmados para esta asignatura en el curso, semestre y año especificados.");
    }

    // Tomamos datos del primer estudiante para la cabecera del PDF,
    // ya que todos los estudiantes en esta lista pertenecen a la misma combinación de Asignatura/Curso/Semestre/Año.
    $infoClase = $estudiantes[0];

    // --- Clase PDF extendida ---
    class PDF extends FPDF {
        public $dep; // Datos del departamento/universidad
        public $infoClase; // Información de la asignatura, curso, semestre, año

        function Header() {
            // Rutas de los logos (AJUSTA ESTAS RUTAS SI ES NECESARIO)
            $logoPais = !empty($this->dep['logo_pais']) && file_exists('../../api/' . $this->dep['logo_pais']) ? '../../api/' . $this->dep['logo_pais'] : '';
            $logoUnge = !empty($this->dep['logo_unge']) && file_exists('../../api/' . $this->dep['logo_unge']) ? '../../api/' . $this->dep['logo_unge'] : '';

            // Si los logos existen, se añaden al PDF
            if (!empty($logoPais)) {
                $this->Image($logoPais, 10, 8, 30);
            }
            if (!empty($logoUnge)) {
                $this->Image($logoUnge, 170, 8, 30);
            }

            // Títulos de la Universidad y Departamento
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(0, 10, utf8_decode(strtoupper($this->dep['universidad'])), 0, 1, 'C');
            $this->Cell(0, 8, utf8_decode(strtoupper($this->dep['nombre'])), 0, 1, 'C');
            $this->Ln(2); // Salto de línea

            // Título principal del documento
            $this->SetFont('Arial', 'B', 14);
            $this->Cell(0, 10, utf8_decode("LISTA DE ESTUDIANTES"), 0, 1, 'C');
            $this->Ln(2);

            // Información de la clase
            $fecha_actual = date('d/m/Y');
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 6, utf8_decode("Fecha de Emisión: $fecha_actual"), 0, 1, 'C');
            $this->Ln(2);

            $this->SetFont('Arial', 'B', 11);
            $this->Cell(0, 6, utf8_decode("Asignatura: ") . utf8_decode($this->infoClase['asignatura_nombre']), 0, 1, 'L');
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 6, utf8_decode("Curso: ") . utf8_decode($this->infoClase['curso_nombre']) .
                                 utf8_decode(" | Semestre: ") . utf8_decode($this->infoClase['semestre_nombre']) .
                                 utf8_decode(" | Año Académico: ") . utf8_decode($this->infoClase['anio_academico']), 0, 1, 'L');
            $this->Cell(0, 6, utf8_decode("Turno: ") . utf8_decode(ucfirst($this->infoClase['turno'])) .
                                 utf8_decode(" | Grupo: ") . $this->infoClase['grupo'], 0, 1, 'L');
            $this->Ln(5); // Salto de línea antes de la tabla
        }

        function Footer() {
            $this->SetY(-15); // Posición a 1.5 cm del final
            $this->SetFont('Arial', 'I', 8); // Fuente itálica, tamaño 8
            // Número de página
            $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }

    // Instanciar la clase PDF
    $pdf = new PDF();
    $pdf->dep = $dep; // Pasar los datos del departamento
    $pdf->infoClase = $infoClase; // Pasar la información de la clase
    $pdf->AliasNbPages(); // Para el conteo total de páginas {nb}
    $pdf->AddPage(); // Añadir la primera página
    $pdf->SetFont('Arial', '', 10); // Fuente para el contenido de la tabla

    // Cabecera de la tabla de estudiantes
    $pdf->SetFillColor(230, 230, 230); // Color de fondo para la cabecera
    $pdf->SetFont('Arial', 'B', 10); // Fuente negrita para cabecera
    $pdf->Cell(10, 8, '#', 1, 0, 'C', true); // Cell(ancho, alto, texto, borde, salto_linea, alineacion, relleno)
    $pdf->Cell(45, 8, utf8_decode('Nombre'), 1, 0, 'C', true);
    $pdf->Cell(45, 8, utf8_decode('Apellido'), 1, 0, 'C', true);
    $pdf->Cell(40, 8, utf8_decode('Matrícula'), 1, 0, 'C', true);
    $pdf->Cell(40, 8, 'DIP', 1, 1, 'C', true); // 1 al final para salto de línea

    // Filas de datos de estudiantes
    $pdf->SetFont('Arial', '', 10); // Fuente normal para las filas
    foreach ($estudiantes as $i => $est) {
        $pdf->Cell(10, 8, $i + 1, 1);
        $pdf->Cell(45, 8, utf8_decode($est['nombre_estudiante']), 1);
        $pdf->Cell(45, 8, utf8_decode($est['apellido_estudiante']), 1);
        $pdf->Cell(40, 8, utf8_decode($est['matricula']), 1);
        $pdf->Cell(40, 8, utf8_decode($est['dni']), 1);
        $pdf->Ln(); // Salto de línea para la siguiente fila
    }

    // Salida del PDF (se envía directamente al navegador)
    $pdf->Output('I', 'Lista_Estudiantes_' . utf8_decode($infoClase['asignatura_nombre']) . '.pdf');

} catch (PDOException $e) {
    // Manejo de errores de base de datos
    error_log("Error de base de datos al generar PDF de lista de estudiantes: " . $e->getMessage());
    die("Error en la base de datos al generar el PDF. Por favor, inténtelo de nuevo más tarde.");
} catch (Exception $e) {
    // Manejo de otros errores
    error_log("Error inesperado al generar PDF de lista de estudiantes: " . $e->getMessage());
    die("Error inesperado al generar el PDF: " . $e->getMessage());
}
?>
