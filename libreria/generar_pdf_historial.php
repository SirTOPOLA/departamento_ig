<?php
// reports/generate_historial_pdf.php

// Incluir FPDF
require('fpdf.php'); // Ajusta esta ruta si tu carpeta fpdf está en otro lugar
require_once '../includes/functions.php'; // Para funciones auxiliares si las tienes
require_once '../config/database.php';   // Para la conexión a la base de datos

// --- 1. Obtener y validar el ID del usuario ---
$id_usuario = filter_var($_GET['id_usuario'] ?? null, FILTER_VALIDATE_INT);

if (!$id_usuario) {
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
    // Esta estructura de consulta asume MySQL 8.0+ / MariaDB 10.2+ para ROW_NUMBER()
    $stmt_historial = $pdo->prepare("
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

    // --- 3. Obtener detalles del Departamento/Institución para encabezados/pies de página ---
    // Asumimos que la tabla 'departamento' contiene los detalles de la institución principal (ej. id_departamento = 1)
    $stmt_institution = $pdo->prepare("
        SELECT nombre, universidad, logo_unge, logo_pais
        FROM departamento
        WHERE id_departamento = 1 -- Ajusta este ID si tu institución principal tiene un ID diferente
        LIMIT 1
    ");
    $stmt_institution->execute();
    $institution_details = $stmt_institution->fetch(PDO::FETCH_ASSOC);

    // Obtener nombre del departamento específico
    $stmt_department = $pdo->prepare("
        SELECT nombre
        FROM departamento
        WHERE id_departamento = 2 -- Ajusta este ID al ID de tu departamento específico (ej. Informática de Gestión)
        LIMIT 1
    ");
    $stmt_department->execute();
    $specific_department_name = $stmt_department->fetchColumn();

    // Proporcionar valores por defecto si los detalles no se encuentran
    $institutionName = $institution_details['universidad'] ?? 'UNIVERSIDAD NACIONAL DE GUINEA ECUATORIAL';
    $facultyName = 'Facultad de Ciencias Económicas y Empresariales'; // Nombre de la facultad fijo
    $departmentName = $specific_department_name ?? 'Departamento de Informática de Gestión'; // Nombre del departamento, con fallback
    $ungeLogoPath = $institution_details['logo_unge'] ?? '../assets/img/logo_unge.png'; // Ruta por defecto al logo UNGE
    $countryLogoPath = $institution_details['logo_pais'] ?? '../assets/img/logo_pais.png'; // Ruta por defecto al logo del país

    // Ajustar la ruta del logo si se almacena sin el prefijo '../'
    if ($ungeLogoPath && strpos($ungeLogoPath, '../') !== 0 && file_exists('../' . $ungeLogoPath)) {
        $ungeLogoPath = '../' . $ungeLogoPath;
    }
    if ($countryLogoPath && strpos($countryLogoPath, '../') !== 0 && file_exists('../' . $countryLogoPath)) {
        $countryLogoPath = '../' . $countryLogoPath;
    }


    // --- 4. Definir la clase PDF personalizada ---
    class PDF extends FPDF
    {
        private $headerTitle = 'Historial Académico Estudiantil';
        private $ungeLogoPath;
        private $countryLogoPath;
        private $institutionName;
        private $facultyName;
        private $departmentName;
        private $studentName;
        private $studentRegistrationCode;

        function __construct($orientation = 'P', $unit = 'mm', $size = 'A4', $studentName = '', $studentRegistrationCode = '', $institutionName = '', $facultyName = '', $departmentName = '', $ungeLogoPath = '', $countryLogoPath = '')
        {
            parent::__construct($orientation, $unit, $size);
            $this->studentName = $studentName;
            $this->studentRegistrationCode = $studentRegistrationCode;
            $this->institutionName = $institutionName;
            $this->facultyName = $facultyName;
            $this->departmentName = $departmentName;
            $this->ungeLogoPath = $ungeLogoPath;
            $this->countryLogoPath = $countryLogoPath;
        }

        // Encabezado
        function Header()
        {
            // Logo UNGE (Izquierda)
            $logoHeight = 25; // Alto del logo
            $logoWidth = 25; // Ancho del logo, asumiendo una relación de aspecto similar
            $margin = 10; // Margen izquierdo

            if (file_exists($this->ungeLogoPath)) {
                $this->Image(utf8_decode($this->ungeLogoPath), $margin, 8, $logoWidth, $logoHeight);
            } else {
                $this->SetFont('Arial', 'B', 10);
                $this->SetTextColor(50, 50, 50);
                $this->Cell(30, 10, utf8_decode('UNGE'), 0, 0, 'L');
            }

            // Logo del País (Derecha)
            if (file_exists($this->countryLogoPath)) {
                // Cálculo de posición: Ancho de página (210 para A4) - margen derecho - ancho del logo
                $this->Image(utf8_decode($this->countryLogoPath), 210 - $margin - $logoWidth, 8, $logoWidth, $logoHeight);
            } else {
                // Podrías añadir un texto de marcador de posición si el logo falta, similar al logo de la UNGE
            }

            // Establecer posición para los textos del encabezado (centrado entre los logos)
            $textStartX = $margin + $logoWidth + 5; // Comienza después del logo UNGE + un pequeño espacio
            $textEndX = 210 - $margin - $logoWidth - 5; // Termina antes del logo del País + un pequeño espacio
            $textWidth = $textEndX - $textStartX;

            // Nombre de la Institución (centrado entre logos)
            $this->SetY(8); // Reiniciar Y al principio para el texto principal del encabezado
            $this->SetX($textStartX);
            $this->SetFont('Arial', 'B', 14);
            $this->SetTextColor(30, 30, 100); // Azul oscuro institucional
            $this->Cell($textWidth, 7, utf8_decode($this->institutionName), 0, 1, 'C'); // 'C' para centrar

            // Facultad
            $this->SetX($textStartX);
            $this->SetFont('Arial', 'B', 12);
            $this->SetTextColor(50, 50, 50); // Un color un poco más suave
            $this->Cell($textWidth, 6, utf8_decode($this->facultyName), 0, 1, 'C');

            // Nombre del Departamento
            $this->SetX($textStartX);
            $this->SetFont('Arial', '', 11); // Fuente normal, tamaño un poco más pequeño
            $this->SetTextColor(80, 80, 80); // Gris más claro
            $this->Cell($textWidth, 6, utf8_decode($this->departmentName), 0, 1, 'C');
            $this->Ln(3); // Pequeño espacio

            // Título principal del documento (centrado debajo de la información institucional)
            $this->SetX(10);
            $this->SetFont('Arial', 'B', 16);
            $this->SetTextColor(30, 30, 100);
            $this->Cell(0, 10, utf8_decode($this->headerTitle), 0, 1, 'C');
            $this->Ln(2); // Salto de línea

            // Línea separadora
            $this->SetDrawColor(0, 128, 255); // Azul institucional
            $this->SetLineWidth(0.5);
            $this->Line(10, $this->GetY(), 200, $this->GetY()); // Usar GetY() para la posición actual
            $this->Ln(5); // Espacio después de la línea

            // Información del estudiante (debajo de la línea separadora)
            $this->SetFont('Arial', 'B', 11);
            $this->SetTextColor(0, 0, 0); // Negro
            $this->Cell(95, 7, utf8_decode('Estudiante: ') . utf8_decode($this->studentName), 0, 0, 'L');
            $this->Cell(95, 7, utf8_decode('Cód. Registro: ') . utf8_decode($this->studentRegistrationCode), 0, 1, 'R');
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

        // Función para dibujar una tabla con estilo mejorado
        function ImprovedTable($header, $data)
        {
            // Anchuras de las columnas (ajusta según tus necesidades y el contenido)
            $w = [50, 25, 30, 25, 35]; // Nombre Asignatura, Semestre, Año Académico, Nota Final, Estado
            $this->SetFillColor(0, 128, 255); // Azul oscuro para el encabezado de la tabla
            $this->SetTextColor(255); // Texto blanco
            $this->SetDrawColor(0, 0, 0); // Borde negro
            $this->SetLineWidth(0.3);
            $this->SetFont('Arial', 'B', 10);

            // Cabecera de la tabla
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
                // Asegurar que el texto se ajuste a las celdas y los caracteres especiales se manejen
                $this->Cell($w[0], 6, utf8_decode($row['nombre_asignatura']), 'LR', 0, 'L', $fill);
                $this->Cell($w[1], 6, utf8_decode($row['numero_semestre']), 'LR', 0, 'C', $fill);

                // CORRECCIÓN: Formatear el año académico para evitar la duplicación
                // Si el año académico es igual al año de inicio del semestre, solo muestra el año académico.
                // Si son diferentes, muestra el año académico y el rango del semestre entre paréntesis.
                $anioAcademico = utf8_decode($row['anio_academico']);
                $semestreInicioYear = substr($row['semestre_inicio'], 0, 4);
                $semestreFinYear = substr($row['semestre_fin'], 0, 4);

                $displayAnio = $anioAcademico;
                if ($semestreInicioYear !== $anioAcademico || $semestreFinYear !== $anioAcademico) {
                    // Solo si el año académico es diferente al rango del semestre, añadimos el rango
                    if ($semestreInicioYear == $semestreFinYear) {
                        $displayAnio .= ' (' . $semestreInicioYear . ')';
                    } else {
                        $displayAnio .= ' (' . $semestreInicioYear . '-' . $semestreFinYear . ')';
                    }
                }

                $this->Cell($w[2], 6, $displayAnio, 'LR', 0, 'C', $fill);
                $this->Cell($w[3], 6, number_format($row['nota_final'], 2), 'LR', 0, 'C', $fill);

                // Estilo para el estado
                $estado = utf8_decode($row['estado_final']);
                $this->SetTextColor(0); // Restablecer color de texto
                if ($row['estado_final'] === 'APROBADO') {
                    $this->SetTextColor(0, 150, 0); // Verde
                } elseif ($row['estado_final'] === 'REPROBADO') {
                    $this->SetTextColor(200, 0, 0); // Rojo
                } else {
                    $this->SetTextColor(100, 100, 0); // Naranja/Marrón para Pendiente/Otro
                }
                $this->Cell($w[4], 6, $estado, 'LR', 0, 'C', $fill);

                $this->SetTextColor(0); // Restablecer color de texto a negro para la siguiente fila
                $this->Ln();
                $fill = !$fill; // Alternar color
            }
            // Línea de cierre de la tabla
            $this->Cell(array_sum($w), 0, '', 'T');
            $this->Ln(10); // Espacio después de la tabla
        }
    }

    // --- 5. Instanciar y configurar el PDF ---
    // Pasar los detalles dinámicos de la institución al constructor de la clase PDF
    $pdf = new PDF(
        'P',
        'mm',
        'A4',
        $student_details['nombre_completo'],
        $student_details['codigo_registro'],
        $institutionName,
        $facultyName,
        $departmentName,
        $ungeLogoPath,
        $countryLogoPath // Pasar la ruta del logo del país
    );
    $pdf->AliasNbPages(); // Necesario para el conteo total de páginas
    $pdf->AddPage();
    $pdf->SetMargins(10, 10, 10); // Márgenes (izquierda, arriba, derecha)

    // --- 6. Añadir el contenido de la tabla ---
    if (!empty($historial_academico)) {
        $header = ['Asignatura', 'Semestre', 'Año Académico', 'Nota Final', 'Estado'];
        $pdf->ImprovedTable($header, $historial_academico);
    } else {
        $pdf->SetFont('Arial', '', 12);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(0, 10, utf8_decode('No hay historial académico disponible para este estudiante.'), 0, 1, 'C');
    }

    // --- 7. Salida del PDF ---
    // 'I' para mostrar en el navegador, 'D' para forzar descarga, 'F' para guardar en el servidor
    $filename = 'Historial_Academico_' . str_replace(' ', '_', utf8_decode($student_details['nombre_completo'])) . '.pdf';
    $pdf->Output('I', $filename);

} catch (PDOException $e) {
    error_log("Error de BD al generar PDF: " . $e->getMessage());
    die('Error de base de datos al generar el historial PDF: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Error general al generar PDF: " . $e->getMessage());
    die('Ocurrió un error al generar el historial PDF: ' . $e->getMessage());
}
?>