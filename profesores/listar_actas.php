<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Asegura que solo los profesores o administradores puedan acceder a esta página
check_login_and_role('Profesor');

$profesor_id_sesion = $_SESSION['profesor_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? null;

// Obtener parámetros de la URL
$asignatura_id = filter_var($_GET['asignatura_id'] ?? null, FILTER_VALIDATE_INT);
$semestre_id = filter_var($_GET['semestre_id'] ?? null, FILTER_VALIDATE_INT);
$anio_academico_id = filter_var($_GET['anio_academico_id'] ?? null, FILTER_VALIDATE_INT);

// Validar que los IDs sean válidos
if (!$asignatura_id || !$semestre_id || !$anio_academico_id) {
    die("Parámetros insuficientes o inválidos para generar el acta.");
}

// Validar que el profesor de la sesión sea válido si es un profesor
if ($user_role === 'Profesor' && !$profesor_id_sesion) {
    die("Error: ID de profesor no disponible en la sesión.");
}

// Obtener información de la asignatura, semestre y año académico
try {
    $stmt_info = $pdo->prepare("
        SELECT
            a.nombre_asignatura,
            s.numero_semestre,
            s.fecha_inicio,
            s.fecha_fin,
            aa.nombre_anio,
            aa.fecha_inicio AS anio_inicio,
            aa.fecha_fin AS anio_fin,
            -- Asignamos el profesor directamente desde grupos_asignaturas si está presente en el contexto del acta
            (SELECT u.nombre_completo FROM profesores pr JOIN usuarios u ON pr.id_usuario = u.id WHERE pr.id = ga.id_profesor LIMIT 1) AS nombre_profesor_asignado
        FROM asignaturas a
        JOIN grupos_asignaturas ga ON a.id = ga.id_asignatura
        JOIN semestres s ON ga.id_curso = s.id_curso_asociado_al_semestre -- Unir por curso asociado al semestre
        JOIN anios_academicos aa ON s.id_anio_academico = aa.id
        WHERE ga.id_asignatura = :asignatura_id
          AND s.id = :semestre_id
          AND aa.id = :anio_academico_id
        GROUP BY a.nombre_asignatura, s.numero_semestre, s.fecha_inicio, s.fecha_fin, aa.nombre_anio, aa.fecha_inicio, aa.fecha_fin, nombre_profesor_asignado
        LIMIT 1
    ");
    $stmt_info->execute([
        'asignatura_id' => $asignatura_id,
        'semestre_id' => $semestre_id,
        'anio_academico_id' => $anio_academico_id
    ]);
    $info_acta = $stmt_info->fetch(PDO::FETCH_ASSOC);

    if (!$info_acta) {
        die("No se encontró información para la asignatura, semestre y año académico seleccionados.");
    }

    // --- OBTENER ESTUDIANTES Y SUS DATOS DEL HISTORIAL ACADÉMICO ---
    // Excluyendo observaciones y filtrando por estado_final
    $sql_historial = "
        SELECT
            u.nombre_completo AS nombre_estudiante,
            ha.nota_final,
            ha.estado_final,
            ha.fecha_actualizacion
            -- n.observaciones_admin -- Obviado
        FROM historial_academico ha
        JOIN estudiantes e ON ha.id_estudiante = e.id
        JOIN usuarios u ON e.id_usuario = u.id
        JOIN inscripciones_estudiantes ie ON ha.id_estudiante = ie.id_estudiante AND ha.id_asignatura = ie.id_asignatura AND ha.id_semestre = ie.id_semestre
        JOIN grupos_asignaturas ga ON ie.id_grupo_asignatura = ga.id
        -- LEFT JOIN notas n ON ie.id = n.id_inscripcion -- Ya no es necesario si no se usa observaciones_admin
        WHERE ha.id_asignatura = :asignatura_id
          AND ha.id_semestre = :semestre_id
          AND ga.id_profesor = :profesor_id_sesion
          AND ha.estado_final IN ('APROBADO', 'REPROBADO') -- FILTRO CRUCIAL: Solo registros con estado final
        ORDER BY u.nombre_completo
    ";

    $stmt_historial = $pdo->prepare($sql_historial);
    $stmt_historial->execute([
        'asignatura_id' => $asignatura_id,
        'semestre_id' => $semestre_id,
        'profesor_id_sesion' => $profesor_id_sesion
    ]);
    $datos_historial_estudiantes = $stmt_historial->fetchAll(PDO::FETCH_ASSOC);

    /* obtenemos la ruta del logo guardado en la base de datos */
    $logo = '';
    $stmt = $pdo->query("SELECT * FROM departamento LIMIT 1");
    $info = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error al generar acta: " . $e->getMessage());
    die("Error interno al cargar los datos del acta.");
}

// Verificar y construir rutas de imagen/logo si existen
$logo_path = '../' . ($info['logo_unge'] ?? '');

// Configurar encabezados para que el navegador sepa que es un documento HTML para imprimir
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Acta - <?= htmlspecialchars($info_acta['nombre_asignatura']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
            color: #333;
        }

        .container-a4 {
            width: 210mm;
            min-height: 297mm;
            margin: 20mm auto;
            background: #fff;
            padding: 25mm;
            box-shadow: 0 0 8px rgba(0, 0, 0, 0.12);
        }

        .header-acta {
            text-align: center;
            margin-bottom: 25px;
        }

        .header-acta img {
            height: 60px;
            margin-bottom: 10px;
        }

        .header-acta h1 {
            font-size: 22px;
            font-weight: 700;
            margin: 10px 0 4px;
        }

        .header-acta h2 {
            font-size: 16px;
            font-weight: 600;
            color: #444;
        }

        .header-acta h3 {
            font-size: 14px;
            color: #666;
        }

        .info-acta {
            margin: 25px 0;
            font-size: 14px;
            line-height: 1.5;
        }

        .info-acta div strong {
            color: #000;
        }

        .table-notas {
            font-size: 14px;
        }

        .table-notas th {
            background-color: #e9ecef;
            color: #212529;
        }

        .footer-acta {
            margin-top: 60px;
            font-size: 12px;
            text-align: center;
            color: #444;
        }

        .signature-line {
            border-bottom: 1px solid #333;
            width: 220px;
            margin: 40px auto 5px;
        }

        @media print {
            body {
                background: none;
                margin: 0;
            }

            .container-a4 {
                margin: 0;
                box-shadow: none;
                page-break-after: always;
                padding: 15mm 20mm;
            }

            .no-print {
                display: none !important;
            }

            .table-notas thead {
                display: table-header-group;
            }

            .table-notas tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="container-a4">
        <div class="header-acta">
            <?php if (!empty($logo_path) && file_exists($logo_path)): ?>
                <img src="<?= htmlspecialchars($logo_path) ?>" alt="Logo UNGE" class="rounded">
            <?php else: ?>
                <div style="font-size: 40px;"><i class="fas fa-university"></i></div>
            <?php endif; ?>
            <h1>Universidad Nacional de Guinea Ecuatorial (UNGE)</h1>
            <h2>Facultad de Ciencias Económicas </h2>
            <h2></h2> Departamento de Informática de Gestión</h2>
            <h3>ACTA DE CALIFICACIONES FINALES</h3>
            <hr />
        </div>

        <div class="info-acta">
            <div><strong>Asignatura:</strong> <?= htmlspecialchars($info_acta['nombre_asignatura']) ?></div>
            <div><strong>Profesor:</strong> <?= htmlspecialchars($info_acta['nombre_profesor_asignado'] ?: 'No Asignado') ?></div>
            <div><strong>Semestre:</strong> <?= htmlspecialchars($info_acta['numero_semestre']) ?> (<?= format_date($info_acta['fecha_inicio']) . ' - ' . format_date($info_acta['fecha_fin']) ?>)</div>
            <div><strong>Año Académico:</strong> <?= htmlspecialchars($info_acta['nombre_anio']) ?> (<?= htmlspecialchars($info_acta['anio_inicio'] . ' - ' . $info_acta['anio_fin']) ?>)</div>
            <div><strong>Fecha de Emisión:</strong> <?= date('d/m/Y') ?></div>
        </div>

        <?php if (!empty($datos_historial_estudiantes)): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-notas">
                    <thead>
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th style="width: 50%;">Nombre del Estudiante</th>
                            <th style="width: 15%;">Nota Final</th>
                            <th style="width: 20%;">Estado Final</th>
                            </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($datos_historial_estudiantes as $index => $data): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($data['nombre_estudiante']) ?></td>
                                <td><?= number_format($data['nota_final'], 2) ?></td>
                                <td class="<?= $data['estado_final'] === 'APROBADO' ? 'text-success' : 'text-danger' ?>">
                                    <?= htmlspecialchars($data['estado_final']) ?>
                                </td>
                                </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-warning text-center">No hay datos de historial académico con estado final para esta asignatura, semestre y grupo del profesor.</div>
        <?php endif; ?>

        <div class="footer-acta">
            <div class="signature-line"></div>
            <p>Firma y Sello del Profesor</p>
            <p><strong><?= htmlspecialchars($info_acta['nombre_profesor_asignado'] ?: '_________________________') ?></strong></p>
            <p class="mt-4">Este documento es una copia oficial de las calificaciones. Cualquier alteración invalida el acta.</p>
            <p><small>Generado automáticamente por el Sistema de Gestión Académica UNGE</small></p>
        </div>
    </div>
</body>
</html>