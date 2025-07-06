<?php
// reports/generate_historial_pdf.php

// Incluir FPDF
require('fpdf.php'); // Ajusta la ruta si es necesario
require_once '../includes/functions.php'; // Para funciones auxiliares si las tienes
require_once '../config/database.php';   // Para la conexión a la base de datos

// --- 1. Obtener y validar el ID del usuario ---
$id_usuario = filter_var($_GET['id_usuario'] ?? null, FILTER_VALIDATE_INT);

if (!$id_usuario) {
    // Si no hay ID, redirigir o mostrar un error simple (no JSON aquí)
    die('ID de usuario no proporcionado o inválido para generar el historial.');
}

try {
    // Primero, obtener el ID del estudiante a partir del ID de usuario
    $stmt_estudiante = $pdo->prepare("SELECT id FROM estudiantes WHERE id_usuario = :id_usuario");
    $stmt_estudiante->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
    $stmt_estudiante->execute();
    $id_estudiante = $stmt_estudiante->fetchColumn();

    if (!$id_estudiante) {
        die('No se encontró el estudiante para el ID de usuario proporcionado.');
    }

    // --- 2. Obtener los datos del historial académico y del estudiante ---
    // Consulta SQL optimizada para obtener el historial académico
    // Utiliza la lógica con ROW_NUMBER() si tu MySQL/MariaDB es 8.0+/10.2+ (Recomendado)
    // O la lógica sin ROW_NUMBER() si tu base de datos es más antigua.
    // **Asegúrate de pegar aquí la consulta SQL FINALMENTE ELEGIDA y probada**
    $stmt_historial = $pdo->prepare("
        -- Aquí va la consulta SQL que me proporcionaste y que funciona correctamente
        -- Por ejemplo, si usas la versión con ROW_NUMBER():
        WITH RankedHistory AS (
            SELECT
                ha.id AS id_historial,
                ha.id_asignatura,
                ha.id_estudiante,
                ha.nota_final,
                ha.estado_final,
                s.numero_semestre,
                s.fecha_inicio AS semestre_inicio,
                s.fecha_fin AS semestre_fin,
                aa.nombre_anio AS anio_academico,
                a.nombre_asignatura,
                ROW_NUMBER() OVER (
                    PARTITION BY ha.id_asignatura, ha.id_estudiante
                    ORDER BY
                        CASE ha.estado_final
                            WHEN 'APROBADO' THEN 1
                            WHEN 'REPROBADO' THEN 2
                            ELSE 3
                        END,
                        ha.id DESC
                ) as rn
            FROM historial_academico ha
            JOIN asignaturas a ON ha.id_asignatura = a.id
            JOIN semestres s ON ha.id_semestre = s.id
            JOIN anios_academicos aa ON s.id_anio_academico = aa.id
            WHERE ha.id_estudiante = :id_estudiante
        )
        SELECT
            id_historial,
            nombre_asignatura,
            numero_semestre,
            semestre_inicio,
            semestre_fin,
            anio_academico,
            nota_final,
            estado_final
        FROM RankedHistory
        WHERE
            estado_final IN ('APROBADO', 'REPROBADO')
            OR
            (
                estado_final NOT IN ('APROBADO', 'REPROBADO')
                AND rn = 1
                AND NOT EXISTS (
                    SELECT 1
                    FROM RankedHistory AS rh_inner
                    WHERE rh_inner.id_asignatura = RankedHistory.id_asignatura
                    AND rh_inner.id_estudiante = RankedHistory.id_estudiante
                    AND rh_inner.estado_final IN ('APROBADO', 'REPROBADO')
                )
            )
        ORDER BY anio_academico DESC, numero_semestre DESC, nombre_asignatura ASC;
    ");

    $stmt_historial->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
    $stmt_historial->execute();
    $historial_academico = $stmt_historial->fetchAll(PDO::FETCH_ASSOC);

    $stmt_student_details = $pdo->prepare("
        SELECT u.nombre_completo, e.codigo_registro
        FROM estudiantes e
        JOIN usuarios u ON e.id_usuario = u.id
        WHERE e.id = :id_estudiante
    ");
    $stmt_student_details->bindParam(':id_estudiante', $id_estudiante, PDO::PARAM_INT);
    $stmt_student_details->execute();
    $student_details = $stmt_student_details->fetch(PDO::FETCH_ASSOC);

    if (!$student_details) {
        die('No se pudieron obtener los detalles del estudiante.');
    }

    // --- 3. Definir la clase PDF personalizada ---
    // Esto permite crear un diseño de encabezado y pie de página consistente y elegante.
    class PDF extends FPDF
    {
        // Propiedades para un diseño más limpio
        private $headerTitle = 'Historial Académico Estudiantil';
        private $headerSubtitle = 'Informe Detallado de Notas y Semestres';
        private $logoPath = '../assets/img/logo_institucion.png'; // Ruta a tu logo
        private $institutionName = 'Nombre de Tu Institución'; // Nombre de tu institución
        private $studentName;
        private $studentMatricula;

        // Constructor para pasar datos del estudiante
        function __construct($orientation = 'P', $unit = 'mm', $size = 'A4', $studentName = '', $studentMatricula = '')
        {
            parent::__construct($orientation, $unit, $size);
            $this->studentName = $studentName;
            $this->studentMatricula = $studentMatricula;
        }

        // Encabezado
        function Header()
        {
            // Logo
            if (file_exists($this->logoPath)) {
                $this->Image($this->logoPath, 10, 8, 25); // X, Y, Ancho (ajusta según tu logo)
            } else {
                // Si no hay logo, puedes poner un texto o dejar un espacio
                $this->SetFont('Arial', 'B', 14);
                $this->SetTextColor(50, 50, 50);
                $this->Cell(30, 10, $this->institutionName, 0, 0, 'L');
            }

            // Título del documento
            $this->SetFont('Arial', 'B', 18);
            $this->SetTextColor(30, 30, 100); // Azul oscuro institucional
            $this->Cell(0, 10, $this->headerTitle, 0, 1, 'C'); // Centrado

            // Subtítulo
            $this->SetFont('Arial', '', 10);
            $this->SetTextColor(100, 100, 100); // Gris
            $this->Cell(0, 7, $this->headerSubtitle, 0, 1, 'C');
            $this->Ln(5); // Salto de línea

            // Línea separadora
            $this->SetDrawColor(0, 128, 255); // Azul
            $this->SetLineWidth(0.5);
            $this->Line(10, 35, 200, 35); // X1, Y1, X2, Y2

            // Información del estudiante
            $this->SetFont('Arial', 'B', 12);
            $this->SetTextColor(0, 0, 0); // Negro
            $this->Ln(5); // Espacio después de la línea
            $this->Cell(0, 7, 'Estudiante: ' . utf8_decode($this->studentName), 0, 0, 'L');
            $this->Cell(0, 7, 'Matrícula: ' . utf8_decode($this->studentMatricula), 0, 1, 'R');
            $this->Ln(5); // Espacio antes de la tabla
        }

        // Pie de página
        function Footer()
        {
            $this->SetY(-15); // Posición a 1.5 cm del final
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(150, 150, 150); // Gris claro
            // Número de página
            $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
            // Información de la institución/fecha
            $this->SetX(10);
            $this->Cell(0, 10, utf8_decode($this->institutionName . ' - Generado el: ' . date('d/m/Y H:i')), 0, 0, 'R');
        }

        // Función para dibujar una tabla con estilo
        function ImprovedTable($header, $data)
        {
            // Anchuras de las columnas (ajusta según tus necesidades y el contenido)
            $w = [50, 25, 30, 25, 35]; // Nombre Asignatura, Semestre, Año Académico, Nota Final, Estado
            $this->SetFillColor(0, 128, 255); // Azul oscuro para el encabezado de la tabla
            $this->SetTextColor(255); // Texto blanco
            $this->SetDrawColor(0, 0, 0); // Borde negro
            $this->SetLineWidth(0.3);
            $this->SetFont('Arial', 'B', 10);

            // Cabecera
            for ($i = 0; $i < count($header); $i++) {
                $this->Cell($w[$i], 7, utf8_decode($header[$i]), 1, 0, 'C', true);
            }
            $this->Ln();

            // Restauración de colores y fuente para el cuerpo de la tabla
            $this->SetFillColor(240, 240, 240); // Gris claro para filas alternas
            $this->SetTextColor(0); // Texto negro
            $this->SetFont('Arial', '', 10);

            $fill = false; // Para alternar colores de fila
            foreach ($data as $row) {
                $this->Cell($w[0], 6, utf8_decode($row['nombre_asignatura']), 'LR', 0, 'L', $fill);
                $this->Cell($w[1], 6, utf8_decode($row['numero_semestre']), 'LR', 0, 'C', $fill);
                // Formatear el año académico para incluir el rango del semestre
                $anioSemestre = utf8_decode($row['anio_academico']);
                if (!empty($row['semestre_inicio']) && !empty($row['semestre_fin'])) {
                     // Solo mostrar el rango de años del semestre si es diferente al anio_academico global
                    $startYear = substr($row['semestre_inicio'], 0, 4);
                    $endYear = substr($row['semestre_fin'], 0, 4);
                    if ($startYear !== $anioSemestre && $endYear !== $anioSemestre) {
                         $anioSemestre .= ' (' . $startYear . '-' . $endYear . ')';
                    }
                }
                $this->Cell($w[2], 6, $anioSemestre, 'LR', 0, 'C', $fill);
                $this->Cell($w[3], 6, number_format($row['nota_final'], 2), 'LR', 0, 'C', $fill);
                
                // Estilo para el estado
                $estado = utf8_decode($row['estado_final']);
                $this->SetTextColor(0); // Restablecer color de texto
                if ($row['estado_final'] === 'APROBADO') {
                    $this->SetTextColor(0, 150, 0); // Verde
                } elseif ($row['estado_final'] === 'REPROBADO') {
                    $this->SetTextColor(200, 0, 0); // Rojo
                } else {
                    $this->SetTextColor(100, 100, 0); // Naranja/Marrón para Pendiente
                }
                $this->Cell($w[4], 6, $estado, 'LR', 0, 'C', $fill);
                
                $this->SetTextColor(0); // Restablecer color de texto a negro para la siguiente fila
                $this->Ln();
                $fill = !$fill; // Alternar color
            }
            // Línea de cierre
            $this->Cell(array_sum($w), 0, '', 'T');
            $this->Ln(10); // Espacio después de la tabla
        }
    }

    // --- 4. Instanciar y configurar el PDF ---
    $pdf = new PDF('P', 'mm', 'A4', $student_details['nombre_completo'], $student_details['codigo_registro']);
    $pdf->AliasNbPages(); // Necesario para el conteo total de páginas
    $pdf->AddPage();
    $pdf->SetMargins(10, 10, 10); // Margenes (izquierda, arriba, derecha)

    // --- 5. Añadir el contenido de la tabla ---
    if (!empty($historial_academico)) {
        $header = ['Asignatura', 'Semestre', 'Año Académico', 'Nota Final', 'Estado'];
        $pdf->ImprovedTable($header, $historial_academico);
    } else {
        $pdf->SetFont('Arial', '', 12);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(0, 10, utf8_decode('No hay historial académico disponible para este estudiante.'), 0, 1, 'C');
    }

    // --- 6. Salida del PDF ---
    // 'I' para mostrar en el navegador, 'D' para forzar descarga, 'F' para guardar en el servidor
    $filename = 'Historial_Academico_' . str_replace(' ', '_', $student_details['nombre_completo']) . '.pdf';
    $pdf->Output('I', $filename);

} catch (PDOException $e) {
    error_log("Error de BD al generar PDF: " . $e->getMessage());
    die('Error de base de datos al generar el historial PDF.');
} catch (Exception $e) {
    error_log("Error general al generar PDF: " . $e->getMessage());
    die('Ocurrió un error al generar el historial PDF: ' . $e->getMessage());
}
?>