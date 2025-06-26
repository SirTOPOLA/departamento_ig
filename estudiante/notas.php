<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'estudiante') {
    header("Location: ../index.php");
    exit;
}

require '../includes/conexion.php';

$id_estudiante = $_SESSION['id_usuario'] ?? null;
if (!$id_estudiante) {
    exit("Error: Estudiante no identificado.");
}

 
?>

<?php include 'header.php'; ?>

<style>
        body {
            font-family: "Inter", sans-serif;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 1000px;
        }
        .header-section {
            background-color: #007bff;
            color: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 2rem;
            text-align: center;
        }
        .card {
            border-radius: 0.75rem;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }
        .card-header-custom {
            background-color: #e9f5ff; /* Light blue */
            color: #007bff;
            font-weight: 600;
            padding: 1rem 1.5rem;
            border-top-left-radius: 0.75rem;
            border-top-right-radius: 0.75rem;
            border-bottom: 1px solid #cce5ff;
        }
        .card-header-semester {
            background-color: #f0f8ff; /* Even lighter blue */
            border-bottom: 1px solid #dee2e6;
            padding: 0.75rem 1.25rem;
            font-weight: 500;
        }
        .table thead th {
            background-color: #007bff;
            color: white;
            border-bottom: none;
        }
        .table-striped > tbody > tr:nth-of-type(odd) > * {
            background-color: rgba(0, 0, 0, 0.02);
        }
        .result-badge {
            font-size: 0.85em;
            padding: 0.3em 0.6em;
            border-radius: 0.5rem;
            white-space: nowrap;
        }
        .result-aprobado { background-color: #28a745; color: white; }
        .result-reprobado { background-color: #dc3545; color: white; }
        .result-abandono { background-color: #ffc107; color: #343a40; }
        .result-convalidado { background-color: #17a2b8; color: white; }
        .result-regular { background-color: #6c757d; color: white; }

        /* Estilos para impresión PDF */
        @media print {
            body {
                margin: 0;
                padding: 0;
                background-color: white !important;
                font-size: 10pt;
            }
            .no-print {
                display: none !important;
            }
            .header-section {
                background-color: #007bff !important;
                -webkit-print-color-adjust: exact; /* Para que Chrome imprima el color de fondo */
                color: white !important;
                border-radius: 0 !important;
                margin-bottom: 1rem !important;
                padding: 1rem !important;
            }
            .card {
                border: 1px solid #dee2e6 !important;
                box-shadow: none !important;
                page-break-inside: avoid; /* Evita que las tarjetas se rompan a la mitad */
                margin-bottom: 1rem !important;
            }
            .card-header-custom, .card-header-semester {
                background-color: #e9f5ff !important;
                -webkit-print-color-adjust: exact;
                color: #007bff !important;
                border-bottom: 1px solid #cce5ff !important;
                border-radius: 0 !important;
                padding: 0.5rem 1rem !important;
            }
            .card-header-semester {
                 background-color: #f0f8ff !important;
                 border-bottom: 1px solid #dee2e6 !important;
            }
            .table {
                width: 100% !important;
                margin-bottom: 0.5rem !important;
            }
            .table thead th {
                background-color: #007bff !important;
                -webkit-print-color-adjust: exact;
                color: white !important;
                font-size: 9pt;
                padding: 0.4rem;
            }
            .table tbody td {
                font-size: 9pt;
                padding: 0.4rem;
            }
            .result-badge {
                -webkit-print-color-adjust: exact;
                border: 1px solid transparent; /* Asegura un borde para visibilidad en B&W */
            }
            .result-aprobado { border-color: #28a745; }
            .result-reprobado { border-color: #dc3545; }
            .result-abandono { border-color: #ffc107; }
            .result-convalidado { border-color: #17a2b8; }
            .result-regular { border-color: #6c757d; }
        }
    </style>
 

<div class="container py-5">
    <div class="header-section no-print">
        <h1 class="mb-2"><i class="bi bi-mortarboard-fill me-3"></i> Historial Académico Completo</h1>
        <p class="lead">Detalle del progreso del estudiante a través de sus cursos y semestres.</p>
    </div>

    <!-- Contenido a generar PDF -->
    <div id="academicHistoryContent">
        <div class="text-center mb-4">
            <!-- <img src="https://placehold.co/150x80/007bff/ffffff?text=LOGO%20INST" alt="Logo Institucional" class="mb-3"> -->
            <h3>Historial Académico</h3>
            <h4 id="studentFullName">Cargando Nombre...</h4>
            <p class="text-muted mb-1">Matrícula: <strong id="studentMatricula">Cargando...</strong></p>
            <p class="text-muted">Fecha de Emisión: <strong id="currentDate"></strong></p>
            <hr class="my-4">
        </div>

        <div id="historyDetails">
            <p class="text-center text-muted">Cargando historial académico...</p>
        </div>
    </div>

    <div class="text-center mt-5 no-print">
        <button id="generatePdfBtn" class="btn btn-primary rounded-pill px-5 py-3">
            <i class="bi bi-file-earmark-pdf-fill me-2"></i> Generar PDF del Historial
        </button>
        <a href="javascript:history.back()" class="btn btn-secondary rounded-pill px-4 py-3 ms-3">
            <i class="bi bi-arrow-left-circle me-2"></i> Volver
        </a>
    </div>

</div>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- html2pdf.js for PDF generation -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
    const LOGGED_IN_STUDENT_ID = <?= json_encode($id_estudiante) ?>; // Pasa el ID del estudiante desde PHP
    const studentFullNameElem = document.getElementById('studentFullName');
    const studentMatriculaElem = document.getElementById('studentMatricula');
    const currentDateElem = document.getElementById('currentDate');
    const historyDetailsElem = document.getElementById('historyDetails');
    const generatePdfBtn = document.getElementById('generatePdfBtn');

    // Muestra la fecha actual
    currentDateElem.textContent = new Date().toLocaleDateString('es-ES', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });

    /**
     * Muestra un modal personalizado para mensajes.
     * Implementación copiada para auto-contención.
     */
    function mostrarMensajeModal(message) {
        // Simple alert for this self-contained example.
        // In a full application, use your Bootstrap modal logic.
        alert(message);
    }

    /**
     * Carga el historial académico completo del estudiante.
     */
    async function loadAcademicHistory() {
        if (!LOGGED_IN_STUDENT_ID) {
            mostrarMensajeModal('ID de estudiante no disponible.');
            historyDetailsElem.innerHTML = '<p class="text-center text-danger">Error: ID de estudiante no proporcionado.</p>';
            return;
        }

        historyDetailsElem.innerHTML = '<p class="text-center text-muted">Cargando historial académico...</p>';

        try {
            const res = await fetch(`../api/obtener_historial_academico_completo.php?id_estudiante=${LOGGED_IN_STUDENT_ID}`);
            if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
            const data = await res.json();

            if (data.status && data.data) {
                renderAcademicHistory(data.data);
            } else {
                historyDetailsElem.innerHTML = '<p class="text-center text-danger">Error al cargar historial: ' + (data.message || 'Datos inválidos.') + '</p>';
                console.error("Error al cargar historial académico completo:", data.message || data);
            }
        } catch (err) {
            historyDetailsElem.innerHTML = '<p class="text-center text-danger">Error de conexión al cargar historial académico.</p>';
            console.error("Error de conexión al cargar historial académico completo:", err);
        }
    }

    /**
     * Renderiza el historial académico en la página.
     * @param {object} data - Objeto que contiene student_info y courses_history.
     */
    function renderAcademicHistory(data) {
        studentFullNameElem.textContent = `${data.student_info.nombre || ''} ${data.student_info.apellido || ''}`;
        studentMatriculaElem.textContent = data.student_info.matricula || 'N/A';

        if (!data.courses_history || data.courses_history.length === 0) {
            historyDetailsElem.innerHTML = '<p class="text-center text-muted">No se encontró historial académico para este estudiante.</p>';
            return;
        }

        let historyHtml = '';
        data.courses_history.forEach(courseEntry => {
            historyHtml += `
                <div class="card mb-4">
                    <div class="card-header card-header-custom">
                        <h5 class="mb-0">
                            <i class="bi bi-mortarboard-fill me-2"></i> ${courseEntry.curso_nombre || 'N/A'} (${courseEntry.curso_turno || 'N/A'}, Grupo ${courseEntry.curso_grupo || 'N/A'})
                            <span class="badge bg-primary ms-2">${courseEntry.anio_academico_nombre || 'N/A'}</span>
                            <span class="badge bg-secondary ms-2">Estado: ${courseEntry.curso_estado || 'N/A'}</span>
                        </h5>
                    </div>
                    <div class="card-body p-0">
            `;

            if (courseEntry.semestres && courseEntry.semestres.length > 0) {
                courseEntry.semestres.forEach(semesterEntry => {
                    historyHtml += `
                        <div class="card-header card-header-semester">
                            <h6 class="mb-0 ms-3"><i class="bi bi-calendar-check me-2"></i> ${semesterEntry.semestre_nombre || 'N/A'}</h6>
                        </div>
                        <div class="p-4">
                    `;
                    if (semesterEntry.asignaturas && semesterEntry.asignaturas.length > 0) {
                        historyHtml += `
                            <table class="table table-sm table-striped">
                                <thead class="table-primary">
                                    <tr>
                                        <th>Código</th>
                                        <th>Asignatura</th>
                                        <th>Resultado</th>
                                        <th>Nota Final</th>
                                        <th>Observación</th>
                                    </tr>
                                </thead>
                                <tbody>
                        `;
                        semesterEntry.asignaturas.forEach(subjectEntry => {
                            let resultClass = '';
                            switch (subjectEntry.resultado) {
                                case 'aprobado': resultClass = 'result-aprobado'; break;
                                case 'reprobado': resultClass = 'result-reprobado'; break;
                                case 'abandono': resultClass = 'result-abandono'; break;
                                case 'convalidado': resultClass = 'result-convalidado'; break;
                                case 'regular': resultClass = 'result-regular'; break;
                                default: resultClass = 'bg-secondary'; break;
                            }
                            historyHtml += `
                                    <tr>
                                        <td>${subjectEntry.asignatura_codigo || 'N/A'}</td>
                                        <td>${subjectEntry.asignatura_nombre || 'N/A'}</td>
                                        <td><span class="result-badge ${resultClass}">${subjectEntry.resultado || 'N/A'}</span></td>
                                        <td>${subjectEntry.nota_final !== null ? parseFloat(subjectEntry.nota_final).toFixed(2) : 'N/A'}</td>
                                        <td class="text-break-word small">${subjectEntry.observacion || 'Ninguna'}</td>
                                    </tr>
                            `;
                        });
                        historyHtml += `
                                </tbody>
                            </table>
                        `;
                    } else {
                        historyHtml += '<p class="text-center text-muted">No hay asignaturas registradas para este semestre.</p>';
                    }
                    historyHtml += `</div>`; // Close semester content div
                });
            } else {
                historyHtml += '<div class="p-4"><p class="text-center text-muted">No hay semestres registrados para este curso.</p></div>';
            }
            historyHtml += `</div></div>`; // Close card-body and card
        });

        historyDetailsElem.innerHTML = historyHtml;
    }

    // --- Generación de PDF ---
    generatePdfBtn.addEventListener('click', () => {
        const element = document.getElementById('academicHistoryContent');
        const opt = {
            margin:       [10, 10, 10, 10], // top, left, bottom, right
            filename:     `historial_academico_${LOGGED_IN_STUDENT_ID}.pdf`,
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, logging: true, dpi: 192, letterRendering: true, useCORS: true },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' },
            pagebreak: { mode: ['css', 'avoid-all', 'legacy'] } // Intenta evitar cortes de elementos
        };

        // Usa un clono del elemento para que la impresión no afecte la vista actual
        const clonedElement = element.cloneNode(true);
        // Quita los elementos "no-print" del clon
        clonedElement.querySelectorAll('.no-print').forEach(el => el.remove());

        // Asegúrate de que los estilos CSS para impresión se apliquen al clon
        const style = document.createElement('style');
        style.innerHTML = `@media print { /* Tus estilos @media print aquí */ }`; // Copy print styles if external CSS is not loaded by html2pdf
        clonedElement.appendChild(style);

        html2pdf().from(clonedElement).set(opt).save();
    });


    // Carga el historial al cargar la página
    document.addEventListener('DOMContentLoaded', loadAcademicHistory);
</script>
<?php include 'footer.php'; ?>
