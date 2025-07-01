-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 01-07-2025 a las 15:05:04
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `departemento`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `anios_academicos`
--

CREATE TABLE `anios_academicos` (
  `id` int(11) NOT NULL,
  `nombre_anio` varchar(50) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `anios_academicos`
--

INSERT INTO `anios_academicos` (`id`, `nombre_anio`, `fecha_inicio`, `fecha_fin`) VALUES
(1, '2024-2025', '2024-10-29', '2025-07-30');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asignaturas`
--

CREATE TABLE `asignaturas` (
  `id` int(11) NOT NULL,
  `nombre_asignatura` varchar(255) NOT NULL,
  `creditos` decimal(4,2) NOT NULL,
  `id_prerequisito` int(11) DEFAULT NULL,
  `id_curso` int(11) DEFAULT NULL,
  `semestre_recomendado` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `asignaturas`
--

INSERT INTO `asignaturas` (`id`, `nombre_asignatura`, `creditos`, `id_prerequisito`, `id_curso`, `semestre_recomendado`) VALUES
(1, 'Fundamentos de programación', 5.00, NULL, 1, 1),
(3, 'Herramientas del Escritorio', 5.00, NULL, 1, 1),
(4, 'Inglés I', 5.00, NULL, 1, 1),
(5, 'Introduccion a la Informática', 5.00, NULL, 1, 1),
(6, 'Matemáticas discreta y lógica', 5.00, NULL, 1, 1),
(7, 'Análisis Matemático', 5.00, NULL, 1, 1),
(11, 'Escritura Técnica', 5.00, NULL, 1, 2),
(12, 'Inglés II', 5.00, 4, 2, 2),
(13, 'Tecnologia de la programación', 5.00, NULL, 2, 2),
(14, 'Probabilidad y Estadisticas I', 5.00, NULL, 1, 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `aulas`
--

CREATE TABLE `aulas` (
  `id` int(11) NOT NULL,
  `nombre_aula` varchar(100) NOT NULL,
  `capacidad` int(11) DEFAULT NULL,
  `ubicacion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `aulas`
--

INSERT INTO `aulas` (`id`, `nombre_aula`, `capacidad`, `ubicacion`) VALUES
(1, 'AULA 1', 25, 'EUA, planta baja');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cursos`
--

CREATE TABLE `cursos` (
  `id` int(11) NOT NULL,
  `nombre_curso` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cursos`
--

INSERT INTO `cursos` (`id`, `nombre_curso`, `descripcion`, `created_at`, `updated_at`) VALUES
(1, 'primero', 'primer curso básico de formacion de gestores informáticos', '2025-06-29 11:57:42', '2025-06-29 11:57:42'),
(2, 'segundo', 'segundo curso básico de formación de informáticos', '2025-06-29 11:59:02', '2025-06-29 11:59:02');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `curso_estudiante`
--

CREATE TABLE `curso_estudiante` (
  `id` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `id_anio` int(11) NOT NULL,
  `estado` enum('activo','finalizado','reprobado') DEFAULT 'activo',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `curso_estudiante`
--

INSERT INTO `curso_estudiante` (`id`, `id_estudiante`, `id_curso`, `id_anio`, `estado`, `fecha_registro`) VALUES
(6, 11, 1, 1, 'activo', '2025-07-01 12:27:02'),
(7, 12, 1, 1, 'activo', '2025-07-01 13:01:54');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cvs_profesores`
--

CREATE TABLE `cvs_profesores` (
  `id` int(11) NOT NULL,
  `id_profesor` int(11) NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `ruta_archivo` varchar(512) NOT NULL,
  `fecha_subida` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cvs_profesores`
--

INSERT INTO `cvs_profesores` (`id`, `id_profesor`, `nombre_archivo`, `ruta_archivo`, `fecha_subida`) VALUES
(3, 1, 'Notas_2024 - 2025_LMN-A-25-1.pdf', 'uploads/cvs/1_cv_1751246814.pdf', '2025-06-30 02:26:54');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `departamento`
--

CREATE TABLE `departamento` (
  `id_departamento` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `universidad` varchar(100) NOT NULL,
  `historia` text DEFAULT NULL,
  `imagen` text DEFAULT NULL,
  `logo_unge` text DEFAULT NULL,
  `logo_pais` text DEFAULT NULL,
  `info_matricula` text DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `horario` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `departamento`
--

INSERT INTO `departamento` (`id_departamento`, `nombre`, `universidad`, `historia`, `imagen`, `logo_unge`, `logo_pais`, `info_matricula`, `direccion`, `telefono`, `horario`) VALUES
(1, 'DEPARTAMENTO DE INFOIRMATICA DE GESTION', 'UNIVERSIDAD NACIONAL DE GE', '', NULL, NULL, NULL, '', '', '', '');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estudiantes`
--

CREATE TABLE `estudiantes` (
  `id` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `codigo_registro` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `estudiantes`
--

INSERT INTO `estudiantes` (`id`, `id_usuario`, `codigo_registro`) VALUES
(1, 12, 'FCE214'),
(2, 13, 'FCE245'),
(11, 22, 'FCE7045'),
(12, 23, 'FCE7245');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_academico`
--

CREATE TABLE `historial_academico` (
  `id` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `id_asignatura` int(11) NOT NULL,
  `id_semestre` int(11) NOT NULL,
  `nota_final` decimal(5,2) NOT NULL,
  `estado_final` enum('APROBADO','REPROBADO') NOT NULL,
  `fecha_actualizacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `horarios`
--

CREATE TABLE `horarios` (
  `id` int(11) NOT NULL,
  `id_semestre` int(11) NOT NULL,
  `id_asignatura` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `id_profesor` int(11) NOT NULL,
  `id_aula` int(11) NOT NULL,
  `dia_semana` enum('Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo') NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `turno` enum('Tarde','Noche') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `horarios`
--

INSERT INTO `horarios` (`id`, `id_semestre`, `id_asignatura`, `id_curso`, `id_profesor`, `id_aula`, `dia_semana`, `hora_inicio`, `hora_fin`, `turno`) VALUES
(2, 2, 11, 1, 1, 1, 'Lunes', '12:00:00', '13:00:00', 'Tarde');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inscripciones_estudiantes`
--

CREATE TABLE `inscripciones_estudiantes` (
  `id` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `id_semestre` int(11) NOT NULL,
  `id_asignatura` int(11) NOT NULL,
  `fecha_inscripcion` datetime DEFAULT current_timestamp(),
  `confirmada` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `inscripciones_estudiantes`
--

INSERT INTO `inscripciones_estudiantes` (`id`, `id_estudiante`, `id_semestre`, `id_asignatura`, `fecha_inscripcion`, `confirmada`) VALUES
(7, 1, 2, 11, '2025-06-29 23:07:12', 1),
(8, 1, 2, 14, '2025-06-29 23:07:12', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notas`
--

CREATE TABLE `notas` (
  `id` int(11) NOT NULL,
  `id_inscripcion` int(11) NOT NULL,
  `nota` decimal(5,2) DEFAULT NULL,
  `estado` enum('APROBADO','REPROBADO','PENDIENTE') DEFAULT 'PENDIENTE',
  `estado_envio_acta` enum('BORRADOR','ENVIADA_PROFESOR','APROBADA_ADMIN','RECHAZADA_ADMIN') NOT NULL DEFAULT 'BORRADOR',
  `fecha_envio_acta` datetime DEFAULT NULL,
  `fecha_revision_admin` datetime DEFAULT NULL,
  `id_admin_revisor` int(11) DEFAULT NULL,
  `observaciones_admin` text DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp(),
  `acta_final_confirmada` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `profesores`
--

CREATE TABLE `profesores` (
  `id` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `especialidad` varchar(255) DEFAULT NULL,
  `grado_academico` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `profesores`
--

INSERT INTO `profesores` (`id`, `id_usuario`, `especialidad`, `grado_academico`) VALUES
(1, 9, 'Matematicas', 'Licenciatura');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `profesores_asignaturas_asignadas`
--

CREATE TABLE `profesores_asignaturas_asignadas` (
  `id` int(11) NOT NULL,
  `id_profesor` int(11) NOT NULL,
  `id_asignatura` int(11) NOT NULL,
  `fecha_asignacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `profesores_asignaturas_asignadas`
--

INSERT INTO `profesores_asignaturas_asignadas` (`id`, `id_profesor`, `id_asignatura`, `fecha_asignacion`) VALUES
(1, 1, 11, '2025-07-01 13:56:26');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `profesores_asignaturas_sugeridas`
--

CREATE TABLE `profesores_asignaturas_sugeridas` (
  `id` int(11) NOT NULL,
  `id_profesor` int(11) NOT NULL,
  `id_asignatura` int(11) NOT NULL,
  `fecha_sugerencia` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `profesores_asignaturas_sugeridas`
--

INSERT INTO `profesores_asignaturas_sugeridas` (`id`, `id_profesor`, `id_asignatura`, `fecha_sugerencia`) VALUES
(1, 9, 11, '2025-06-29 15:08:19'),
(2, 1, 6, '2025-06-30 01:02:32');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `publicaciones`
--

CREATE TABLE `publicaciones` (
  `id_publicacion` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `contenido` text NOT NULL,
  `tipo` enum('evento','noticia','comunicado') DEFAULT 'noticia',
  `imagen` text DEFAULT NULL,
  `archivo_adjunto` text DEFAULT NULL,
  `fecha_evento` date DEFAULT NULL,
  `visible` tinyint(1) DEFAULT 1,
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `publicaciones`
--

INSERT INTO `publicaciones` (`id_publicacion`, `titulo`, `contenido`, `tipo`, `imagen`, `archivo_adjunto`, `fecha_evento`, `visible`, `creado_por`, `creado_en`) VALUES
(1, 'EDDED', 'DEDEDE', 'noticia', '../uploads/6863c2bd501bf.jpg', NULL, '2025-07-01', 1, 1, '2025-07-01 11:13:01');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `requisitos_matricula`
--

CREATE TABLE `requisitos_matricula` (
  `id_requisito` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `descripcion` text NOT NULL,
  `tipo` enum('nuevo','antiguo','extranjero','otro') DEFAULT 'nuevo',
  `obligatorio` tinyint(1) DEFAULT 1,
  `archivo_modelo` text DEFAULT NULL,
  `visible` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `requisitos_matricula`
--

INSERT INTO `requisitos_matricula` (`id_requisito`, `titulo`, `descripcion`, `tipo`, `obligatorio`, `archivo_modelo`, `visible`) VALUES
(1, 'MATRUCULA', 'ADEADEW', 'antiguo', 1, '../uploads/6863c30040798.pdf', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `nombre_rol` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `nombre_rol`) VALUES
(1, 'Administrador'),
(2, 'Estudiante'),
(3, 'Profesor');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `semestres`
--

CREATE TABLE `semestres` (
  `id` int(11) NOT NULL,
  `id_anio_academico` int(11) NOT NULL,
  `numero_semestre` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `id_curso_asociado_al_semestre` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `semestres`
--

INSERT INTO `semestres` (`id`, `id_anio_academico`, `numero_semestre`, `fecha_inicio`, `fecha_fin`, `id_curso_asociado_al_semestre`) VALUES
(1, 1, 1, '2024-10-29', '2025-01-22', 1),
(2, 1, 2, '2025-02-14', '2025-07-22', 1),
(4, 1, 3, '2024-09-29', '2025-02-01', 2),
(5, 1, 4, '2025-01-29', '2025-07-07', 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre_usuario` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `id_rol` int(11) NOT NULL,
  `nombre_completo` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `nip` varchar(50) DEFAULT NULL,
  `estado` enum('Activo','Inactivo','Bloqueado') NOT NULL DEFAULT 'Activo',
  `fecha_registro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre_usuario`, `password_hash`, `id_rol`, `nombre_completo`, `email`, `telefono`, `nip`, `estado`, `fecha_registro`) VALUES
(1, 'hakim', '$2y$10$JixeWVxhWCJztjbrUA2lBuLaiQRSJfE9FBiGi/PMIE94r.5rTXkEK', 1, 'Hakim Pergentino', 'perduino@gmail.com', '222001122', '011243', 'Activo', '2025-06-29 11:27:07'),
(9, 'salvador', '$2y$10$Va/cU6tiX4GLCBBtlDskRu3LL3NFba8iAU0O3o0kYQN..R7lzj8Uu', 3, 'Salvador Alo', 'salvador@gmail.com', '222011545', '0112430', 'Activo', '2025-06-29 14:48:52'),
(12, 'melania', '$2y$10$7M/rOsyFDa.1FAv08iMrNuVC.Pz8JECDC2jCaQtzFdX5mD0tWS9/C', 2, 'Melania Sima', 'sima@gmail.com', '555120124', '0112431', 'Activo', '2025-06-29 16:19:54'),
(13, 'mia', '$2y$10$n5is0tAZ/.uL47tQfLUk.e3SSVPxi03hJBjTK8GCFDq6sl6cFWaZG', 2, 'Mia Antonia Supe', 'mia@gmail.com', '222457487', '0014521', 'Activo', '2025-07-01 11:59:12'),
(22, 'lucia', '$2y$10$xbuencvwlP43ow9tkj.OhOXWH28uVdivIKW/BF1qWn7wi1xVoyi5O', 2, 'Lucia Boko', 'lucia@gmail.com', '222457896', '000914578', 'Activo', '2025-07-01 13:27:02'),
(23, 'monica', '$2y$10$8daDKLkojNalZU.Wt2FGCuRLO9aSKli8SQfN/EBH26J179ZRCm.nq', 2, 'monica mons', 'monica@gmail.com', '222145014', '0012101', 'Activo', '2025-07-01 14:01:54');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `anios_academicos`
--
ALTER TABLE `anios_academicos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre_anio` (`nombre_anio`);

--
-- Indices de la tabla `asignaturas`
--
ALTER TABLE `asignaturas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre_asignatura` (`nombre_asignatura`),
  ADD UNIQUE KEY `nombre_asignatura_2` (`nombre_asignatura`),
  ADD KEY `fk_prerequisito` (`id_prerequisito`),
  ADD KEY `fk_asignatura_curso` (`id_curso`);

--
-- Indices de la tabla `aulas`
--
ALTER TABLE `aulas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre_aula` (`nombre_aula`);

--
-- Indices de la tabla `cursos`
--
ALTER TABLE `cursos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre_curso` (`nombre_curso`);

--
-- Indices de la tabla `curso_estudiante`
--
ALTER TABLE `curso_estudiante`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_estudiante_curso_anio` (`id_estudiante`,`id_curso`,`id_anio`),
  ADD KEY `fk_cursoestudiante_estudiante` (`id_estudiante`),
  ADD KEY `fk_cursoestudiante_curso` (`id_curso`),
  ADD KEY `fk_cursoestudiante_anio` (`id_anio`);

--
-- Indices de la tabla `cvs_profesores`
--
ALTER TABLE `cvs_profesores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_profesor` (`id_profesor`);

--
-- Indices de la tabla `departamento`
--
ALTER TABLE `departamento`
  ADD PRIMARY KEY (`id_departamento`);

--
-- Indices de la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_usuario` (`id_usuario`),
  ADD UNIQUE KEY `codigo_registro` (`codigo_registro`);

--
-- Indices de la tabla `historial_academico`
--
ALTER TABLE `historial_academico`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_historial_estudiante` (`id_estudiante`),
  ADD KEY `fk_historial_asignatura` (`id_asignatura`),
  ADD KEY `fk_historial_semestre` (`id_semestre`);

--
-- Indices de la tabla `horarios`
--
ALTER TABLE `horarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_horario_semestre` (`id_semestre`),
  ADD KEY `fk_horario_asignatura` (`id_asignatura`),
  ADD KEY `fk_horario_curso` (`id_curso`),
  ADD KEY `fk_horario_aula` (`id_aula`),
  ADD KEY `fk_horario_profesor_new` (`id_profesor`);

--
-- Indices de la tabla `inscripciones_estudiantes`
--
ALTER TABLE `inscripciones_estudiantes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_estudiante` (`id_estudiante`,`id_semestre`,`id_asignatura`),
  ADD KEY `fk_inscripcion_semestre` (`id_semestre`),
  ADD KEY `fk_inscripcion_asignatura` (`id_asignatura`);

--
-- Indices de la tabla `notas`
--
ALTER TABLE `notas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_nota_inscripcion` (`id_inscripcion`),
  ADD KEY `fk_notas_admin_revisor` (`id_admin_revisor`);

--
-- Indices de la tabla `profesores`
--
ALTER TABLE `profesores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_profesor_id_usuario` (`id_usuario`);

--
-- Indices de la tabla `profesores_asignaturas_asignadas`
--
ALTER TABLE `profesores_asignaturas_asignadas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_profesor` (`id_profesor`,`id_asignatura`),
  ADD KEY `id_asignatura` (`id_asignatura`);

--
-- Indices de la tabla `profesores_asignaturas_sugeridas`
--
ALTER TABLE `profesores_asignaturas_sugeridas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_profesor` (`id_profesor`,`id_asignatura`),
  ADD KEY `fk_asignatura_sugerida` (`id_asignatura`);

--
-- Indices de la tabla `publicaciones`
--
ALTER TABLE `publicaciones`
  ADD PRIMARY KEY (`id_publicacion`),
  ADD KEY `fk_publicacion_creador` (`creado_por`);

--
-- Indices de la tabla `requisitos_matricula`
--
ALTER TABLE `requisitos_matricula`
  ADD PRIMARY KEY (`id_requisito`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre_rol` (`nombre_rol`);

--
-- Indices de la tabla `semestres`
--
ALTER TABLE `semestres`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_anio_academico` (`id_anio_academico`,`numero_semestre`),
  ADD KEY `fk_semestre_curso` (`id_curso_asociado_al_semestre`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre_usuario` (`nombre_usuario`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `nip` (`nip`),
  ADD KEY `fk_rol` (`id_rol`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `anios_academicos`
--
ALTER TABLE `anios_academicos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `asignaturas`
--
ALTER TABLE `asignaturas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `aulas`
--
ALTER TABLE `aulas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `cursos`
--
ALTER TABLE `cursos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `curso_estudiante`
--
ALTER TABLE `curso_estudiante`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `cvs_profesores`
--
ALTER TABLE `cvs_profesores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `departamento`
--
ALTER TABLE `departamento`
  MODIFY `id_departamento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `historial_academico`
--
ALTER TABLE `historial_academico`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `horarios`
--
ALTER TABLE `horarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `inscripciones_estudiantes`
--
ALTER TABLE `inscripciones_estudiantes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `notas`
--
ALTER TABLE `notas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `profesores`
--
ALTER TABLE `profesores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `profesores_asignaturas_asignadas`
--
ALTER TABLE `profesores_asignaturas_asignadas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `profesores_asignaturas_sugeridas`
--
ALTER TABLE `profesores_asignaturas_sugeridas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `publicaciones`
--
ALTER TABLE `publicaciones`
  MODIFY `id_publicacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `requisitos_matricula`
--
ALTER TABLE `requisitos_matricula`
  MODIFY `id_requisito` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `semestres`
--
ALTER TABLE `semestres`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `asignaturas`
--
ALTER TABLE `asignaturas`
  ADD CONSTRAINT `fk_asignatura_curso` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_prerequisito` FOREIGN KEY (`id_prerequisito`) REFERENCES `asignaturas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `curso_estudiante`
--
ALTER TABLE `curso_estudiante`
  ADD CONSTRAINT `fk_cursoestudiante_anio` FOREIGN KEY (`id_anio`) REFERENCES `anios_academicos` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cursoestudiante_curso` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cursoestudiante_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `cvs_profesores`
--
ALTER TABLE `cvs_profesores`
  ADD CONSTRAINT `fk_cv_profesor` FOREIGN KEY (`id_profesor`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  ADD CONSTRAINT `fk_estudiante_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `historial_academico`
--
ALTER TABLE `historial_academico`
  ADD CONSTRAINT `fk_historial_asignatura` FOREIGN KEY (`id_asignatura`) REFERENCES `asignaturas` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_historial_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_historial_semestre` FOREIGN KEY (`id_semestre`) REFERENCES `semestres` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `horarios`
--
ALTER TABLE `horarios`
  ADD CONSTRAINT `fk_horario_asignatura` FOREIGN KEY (`id_asignatura`) REFERENCES `asignaturas` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_horario_aula` FOREIGN KEY (`id_aula`) REFERENCES `aulas` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_horario_curso` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_horario_profesor_new` FOREIGN KEY (`id_profesor`) REFERENCES `profesores` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_horario_semestre` FOREIGN KEY (`id_semestre`) REFERENCES `semestres` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `inscripciones_estudiantes`
--
ALTER TABLE `inscripciones_estudiantes`
  ADD CONSTRAINT `fk_inscripcion_asignatura` FOREIGN KEY (`id_asignatura`) REFERENCES `asignaturas` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inscripcion_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inscripcion_semestre` FOREIGN KEY (`id_semestre`) REFERENCES `semestres` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `notas`
--
ALTER TABLE `notas`
  ADD CONSTRAINT `fk_nota_inscripcion` FOREIGN KEY (`id_inscripcion`) REFERENCES `inscripciones_estudiantes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notas_admin_revisor` FOREIGN KEY (`id_admin_revisor`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `profesores`
--
ALTER TABLE `profesores`
  ADD CONSTRAINT `fk_profesor_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `profesores_asignaturas_asignadas`
--
ALTER TABLE `profesores_asignaturas_asignadas`
  ADD CONSTRAINT `profesores_asignaturas_asignadas_ibfk_1` FOREIGN KEY (`id_profesor`) REFERENCES `profesores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `profesores_asignaturas_asignadas_ibfk_2` FOREIGN KEY (`id_asignatura`) REFERENCES `asignaturas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `profesores_asignaturas_sugeridas`
--
ALTER TABLE `profesores_asignaturas_sugeridas`
  ADD CONSTRAINT `fk_asignatura_sugerida` FOREIGN KEY (`id_asignatura`) REFERENCES `asignaturas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_profesor_sugiere` FOREIGN KEY (`id_profesor`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `publicaciones`
--
ALTER TABLE `publicaciones`
  ADD CONSTRAINT `fk_publicacion_creador` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `semestres`
--
ALTER TABLE `semestres`
  ADD CONSTRAINT `fk_anio_academico` FOREIGN KEY (`id_anio_academico`) REFERENCES `anios_academicos` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_semestre_curso` FOREIGN KEY (`id_curso_asociado_al_semestre`) REFERENCES `cursos` (`id`);

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
