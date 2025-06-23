-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 20, 2025 at 09:02 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sistema_it`
--

-- --------------------------------------------------------

--
-- Table structure for table `anios_academicos`
--

CREATE TABLE `anios_academicos` (
  `id_anio` int(11) NOT NULL,
  `anio` varchar(10) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `anios_academicos`
--

INSERT INTO `anios_academicos` (`id_anio`, `anio`, `activo`, `fecha_inicio`, `fecha_fin`) VALUES
(1, '2024-2025', 1, '2024-06-19', '2025-11-12');

-- --------------------------------------------------------

--
-- Table structure for table `asignaturas`
--

CREATE TABLE `asignaturas` (
  `id_asignatura` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `curso_id` int(11) NOT NULL,
  `semestre_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `asignaturas`
--

INSERT INTO `asignaturas` (`id_asignatura`, `nombre`, `descripcion`, `curso_id`, `semestre_id`) VALUES
(1, 'Arquitectura del computador', 'BA', 1, 1),
(2, 'herramientas del escritorio', 'BA', 1, 1),
(3, 'Inglés II', 'BA', 2, 1),
(4, 'Inglés I', 'BA', 1, 2),
(5, 'Analisis y Diseño de sistemas 1', 'OB', 2, 1),
(6, 'Analisis y Diseño de sistemas 2', 'OB', 2, 2),
(7, 'Escritura Técnica', 'BA', 1, 2);

-- --------------------------------------------------------

--
-- Table structure for table `asignatura_estudiante`
--

CREATE TABLE `asignatura_estudiante` (
  `id` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `id_asignatura` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `asignatura_estudiante`
--

INSERT INTO `asignatura_estudiante` (`id`, `id_estudiante`, `id_asignatura`) VALUES
(1, 7, 7);

-- --------------------------------------------------------

--
-- Table structure for table `asignatura_profesor`
--

CREATE TABLE `asignatura_profesor` (
  `id` int(11) NOT NULL,
  `id_profesor` int(11) NOT NULL,
  `id_asignatura` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `asignatura_profesor`
--

INSERT INTO `asignatura_profesor` (`id`, `id_profesor`, `id_asignatura`) VALUES
(1, 3, 7);

-- --------------------------------------------------------

--
-- Table structure for table `asignatura_requisitos`
--

CREATE TABLE `asignatura_requisitos` (
  `id` int(11) NOT NULL,
  `asignatura_id` int(11) NOT NULL,
  `requisito_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `asignatura_requisitos`
--

INSERT INTO `asignatura_requisitos` (`id`, `asignatura_id`, `requisito_id`) VALUES
(1, 3, 4),
(2, 6, 5);

-- --------------------------------------------------------

--
-- Table structure for table `aulas`
--

CREATE TABLE `aulas` (
  `id_aula` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `capacidad` int(11) NOT NULL DEFAULT 10,
  `ubicacion` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `aulas`
--

INSERT INTO `aulas` (`id_aula`, `nombre`, `capacidad`, `ubicacion`) VALUES
(1, 'AULA 1', 12, 'EUA'),
(2, 'AULA 2', 15, 'EUA');

-- --------------------------------------------------------

--
-- Table structure for table `cursos`
--

CREATE TABLE `cursos` (
  `id_curso` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `turno` enum('tarde','noche') NOT NULL,
  `grupo` int(11) NOT NULL DEFAULT 1,
  `descripcion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cursos`
--

INSERT INTO `cursos` (`id_curso`, `nombre`, `turno`, `grupo`, `descripcion`) VALUES
(1, 'primero', 'tarde', 1, 'Basica'),
(2, 'segundo', 'noche', 1, 'Preparatoria');

-- --------------------------------------------------------

--
-- Table structure for table `curso_aula`
--

CREATE TABLE `curso_aula` (
  `id` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `id_aula` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departamento`
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

-- --------------------------------------------------------

--
-- Table structure for table `estudiantes`
--

CREATE TABLE `estudiantes` (
  `id_estudiante` int(11) NOT NULL,
  `matricula` varchar(20) NOT NULL,
  `id_curso` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `estudiantes`
--

INSERT INTO `estudiantes` (`id_estudiante`, `matricula`, `id_curso`) VALUES
(6, 'M3014', 1),
(7, 'M31014', 1);

-- --------------------------------------------------------

--
-- Table structure for table `horarios`
--

CREATE TABLE `horarios` (
  `id_horario` int(11) NOT NULL,
  `id_asignatura` int(11) NOT NULL,
  `id_profesor` int(11) NOT NULL,
  `aula_id` int(11) NOT NULL,
  `dia` enum('Lunes','Martes','Miércoles','Jueves','Viernes','Sábado') NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `horarios`
--

INSERT INTO `horarios` (`id_horario`, `id_asignatura`, `id_profesor`, `aula_id`, `dia`, `hora_inicio`, `hora_fin`) VALUES
(1, 7, 3, 1, 'Lunes', '12:00:00', '13:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `inscripciones`
--

CREATE TABLE `inscripciones` (
  `id_inscripcion` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `id_asignatura` int(11) NOT NULL,
  `id_anio` int(11) NOT NULL,
  `id_semestre` int(11) NOT NULL,
  `fecha_inscripcion` timestamp NOT NULL DEFAULT current_timestamp(),
  `estado` enum('preinscrito','confirmado','rechazado') DEFAULT 'preinscrito'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notas`
--

CREATE TABLE `notas` (
  `id_nota` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `id_asignatura` int(11) NOT NULL,
  `parcial_1` decimal(5,2) DEFAULT NULL,
  `parcial_2` decimal(5,2) DEFAULT NULL,
  `examen_final` decimal(5,2) DEFAULT NULL,
  `id_anio` int(11) NOT NULL,
  `promedio` decimal(5,2) GENERATED ALWAYS AS (round((ifnull(`parcial_1`,0) + ifnull(`parcial_2`,0) + ifnull(`examen_final`,0)) / 3,2)) STORED,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `profesores`
--

CREATE TABLE `profesores` (
  `id_profesor` int(11) NOT NULL,
  `especialidad` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `profesores`
--

INSERT INTO `profesores` (`id_profesor`, `especialidad`) VALUES
(2, 'Pedagogo'),
(3, 'matematico');

-- --------------------------------------------------------

--
-- Table structure for table `publicaciones`
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

-- --------------------------------------------------------

--
-- Table structure for table `requisitos_matricula`
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

-- --------------------------------------------------------

--
-- Table structure for table `semestres`
--

CREATE TABLE `semestres` (
  `id_semestre` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `curso_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `semestres`
--

INSERT INTO `semestres` (`id_semestre`, `nombre`, `curso_id`) VALUES
(1, 'primero', 1),
(2, 'segundo', 1);

-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `dni` varchar(100) NOT NULL,
  `apellido` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `contrasena` varchar(255) NOT NULL,
  `direccion` varchar(255) NOT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `rol` enum('administrador','profesor','estudiante') NOT NULL,
  `estado` tinyint(1) DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nombre`, `dni`, `apellido`, `email`, `contrasena`, `direccion`, `telefono`, `rol`, `estado`, `creado_en`) VALUES
(1, 'Hakim Pergentino', '0078196', 'Esimi', 'admin@gmail.com', '$2y$10$uBg3MOhEfnRx6ohRJRcbuevZMEMUU2d0uIz.8gJ5SF1M.VRQAdSU6', 'begoña 1', '222001122', 'administrador', 1, '2025-06-20 16:52:30'),
(2, 'Isaia', '000781960', 'Motu', 'profesor1@gmail.com', '$2y$10$FcR3eGeal07qr8qDakipZOGSnhvSmXXDC92iEK8beD4paKCof1PAy', 'begoña 2', '55120456', 'profesor', 1, '2025-06-20 16:53:27'),
(3, 'Salvador', '001178196', 'Alo', 'profesor@gmail.com', '$2y$10$7Rm.X9HOPhwoZQMrQE7X2.zRwLJ7WsxPZjwPOuir4QVEZxCHYgBpC', 'begoña 2', '222001122', 'profesor', 1, '2025-06-20 17:02:32'),
(6, 'melani', '0001457896', 'compe', 'este@gmail.com', '$2y$10$2YVZ/vLX2hmwa366GUJ7seX6CPPae2GScQWb/N0WZdaYb3tYuapgG', 'begoña 2', '', 'estudiante', 1, '2025-06-20 18:10:50'),
(7, 'Melani', '0057896', 'Rope', 'estudiante1@gmail.com', '$2y$10$AsiGVs14tpgXXMT.c4Fcu.ZtBMlpwRCp/mBUXxehthcgW4HoCbhGi', 'begoña 1', '', 'estudiante', 1, '2025-06-20 18:13:17');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `anios_academicos`
--
ALTER TABLE `anios_academicos`
  ADD PRIMARY KEY (`id_anio`);

--
-- Indexes for table `asignaturas`
--
ALTER TABLE `asignaturas`
  ADD PRIMARY KEY (`id_asignatura`),
  ADD KEY `curso_id` (`curso_id`),
  ADD KEY `semestre_id` (`semestre_id`);

--
-- Indexes for table `asignatura_estudiante`
--
ALTER TABLE `asignatura_estudiante`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_estudiante` (`id_estudiante`),
  ADD KEY `id_asignatura` (`id_asignatura`);

--
-- Indexes for table `asignatura_profesor`
--
ALTER TABLE `asignatura_profesor`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_profesor` (`id_profesor`),
  ADD KEY `id_asignatura` (`id_asignatura`);

--
-- Indexes for table `asignatura_requisitos`
--
ALTER TABLE `asignatura_requisitos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `asignatura_id` (`asignatura_id`),
  ADD KEY `requisito_id` (`requisito_id`);

--
-- Indexes for table `aulas`
--
ALTER TABLE `aulas`
  ADD PRIMARY KEY (`id_aula`);

--
-- Indexes for table `cursos`
--
ALTER TABLE `cursos`
  ADD PRIMARY KEY (`id_curso`);

--
-- Indexes for table `curso_aula`
--
ALTER TABLE `curso_aula`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_curso` (`id_curso`),
  ADD KEY `id_aula` (`id_aula`);

--
-- Indexes for table `departamento`
--
ALTER TABLE `departamento`
  ADD PRIMARY KEY (`id_departamento`);

--
-- Indexes for table `estudiantes`
--
ALTER TABLE `estudiantes`
  ADD PRIMARY KEY (`id_estudiante`),
  ADD UNIQUE KEY `matricula` (`matricula`),
  ADD KEY `id_curso` (`id_curso`);

--
-- Indexes for table `horarios`
--
ALTER TABLE `horarios`
  ADD PRIMARY KEY (`id_horario`),
  ADD KEY `id_asignatura` (`id_asignatura`),
  ADD KEY `id_profesor` (`id_profesor`),
  ADD KEY `aula_id` (`aula_id`);

--
-- Indexes for table `inscripciones`
--
ALTER TABLE `inscripciones`
  ADD PRIMARY KEY (`id_inscripcion`),
  ADD UNIQUE KEY `id_estudiante` (`id_estudiante`,`id_asignatura`,`id_anio`,`id_semestre`),
  ADD KEY `id_asignatura` (`id_asignatura`),
  ADD KEY `id_anio` (`id_anio`),
  ADD KEY `id_semestre` (`id_semestre`);

--
-- Indexes for table `notas`
--
ALTER TABLE `notas`
  ADD PRIMARY KEY (`id_nota`),
  ADD KEY `id_estudiante` (`id_estudiante`),
  ADD KEY `id_asignatura` (`id_asignatura`),
  ADD KEY `id_anio` (`id_anio`);

--
-- Indexes for table `profesores`
--
ALTER TABLE `profesores`
  ADD PRIMARY KEY (`id_profesor`);

--
-- Indexes for table `publicaciones`
--
ALTER TABLE `publicaciones`
  ADD PRIMARY KEY (`id_publicacion`),
  ADD KEY `creado_por` (`creado_por`);

--
-- Indexes for table `requisitos_matricula`
--
ALTER TABLE `requisitos_matricula`
  ADD PRIMARY KEY (`id_requisito`);

--
-- Indexes for table `semestres`
--
ALTER TABLE `semestres`
  ADD PRIMARY KEY (`id_semestre`),
  ADD KEY `curso_id` (`curso_id`);

--
-- Indexes for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `dni` (`dni`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `anios_academicos`
--
ALTER TABLE `anios_academicos`
  MODIFY `id_anio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `asignaturas`
--
ALTER TABLE `asignaturas`
  MODIFY `id_asignatura` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `asignatura_estudiante`
--
ALTER TABLE `asignatura_estudiante`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `asignatura_profesor`
--
ALTER TABLE `asignatura_profesor`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `asignatura_requisitos`
--
ALTER TABLE `asignatura_requisitos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `aulas`
--
ALTER TABLE `aulas`
  MODIFY `id_aula` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `cursos`
--
ALTER TABLE `cursos`
  MODIFY `id_curso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `curso_aula`
--
ALTER TABLE `curso_aula`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departamento`
--
ALTER TABLE `departamento`
  MODIFY `id_departamento` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `horarios`
--
ALTER TABLE `horarios`
  MODIFY `id_horario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `inscripciones`
--
ALTER TABLE `inscripciones`
  MODIFY `id_inscripcion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notas`
--
ALTER TABLE `notas`
  MODIFY `id_nota` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `publicaciones`
--
ALTER TABLE `publicaciones`
  MODIFY `id_publicacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `requisitos_matricula`
--
ALTER TABLE `requisitos_matricula`
  MODIFY `id_requisito` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `semestres`
--
ALTER TABLE `semestres`
  MODIFY `id_semestre` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `asignaturas`
--
ALTER TABLE `asignaturas`
  ADD CONSTRAINT `asignaturas_ibfk_1` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `asignaturas_ibfk_2` FOREIGN KEY (`semestre_id`) REFERENCES `semestres` (`id_semestre`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `asignatura_estudiante`
--
ALTER TABLE `asignatura_estudiante`
  ADD CONSTRAINT `asignatura_estudiante_ibfk_1` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `asignatura_estudiante_ibfk_2` FOREIGN KEY (`id_asignatura`) REFERENCES `asignaturas` (`id_asignatura`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `asignatura_profesor`
--
ALTER TABLE `asignatura_profesor`
  ADD CONSTRAINT `asignatura_profesor_ibfk_1` FOREIGN KEY (`id_profesor`) REFERENCES `profesores` (`id_profesor`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `asignatura_profesor_ibfk_2` FOREIGN KEY (`id_asignatura`) REFERENCES `asignaturas` (`id_asignatura`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `asignatura_requisitos`
--
ALTER TABLE `asignatura_requisitos`
  ADD CONSTRAINT `asignatura_requisitos_ibfk_1` FOREIGN KEY (`asignatura_id`) REFERENCES `asignaturas` (`id_asignatura`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `asignatura_requisitos_ibfk_2` FOREIGN KEY (`requisito_id`) REFERENCES `asignaturas` (`id_asignatura`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `curso_aula`
--
ALTER TABLE `curso_aula`
  ADD CONSTRAINT `curso_aula_ibfk_1` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `curso_aula_ibfk_2` FOREIGN KEY (`id_aula`) REFERENCES `aulas` (`id_aula`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `estudiantes`
--
ALTER TABLE `estudiantes`
  ADD CONSTRAINT `estudiantes_ibfk_1` FOREIGN KEY (`id_estudiante`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `estudiantes_ibfk_2` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON UPDATE CASCADE;

--
-- Constraints for table `horarios`
--
ALTER TABLE `horarios`
  ADD CONSTRAINT `horarios_ibfk_1` FOREIGN KEY (`id_asignatura`) REFERENCES `asignaturas` (`id_asignatura`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `horarios_ibfk_2` FOREIGN KEY (`id_profesor`) REFERENCES `profesores` (`id_profesor`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `horarios_ibfk_3` FOREIGN KEY (`aula_id`) REFERENCES `aulas` (`id_aula`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `inscripciones`
--
ALTER TABLE `inscripciones`
  ADD CONSTRAINT `inscripciones_ibfk_1` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `inscripciones_ibfk_2` FOREIGN KEY (`id_asignatura`) REFERENCES `asignaturas` (`id_asignatura`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `inscripciones_ibfk_3` FOREIGN KEY (`id_anio`) REFERENCES `anios_academicos` (`id_anio`) ON UPDATE CASCADE,
  ADD CONSTRAINT `inscripciones_ibfk_4` FOREIGN KEY (`id_semestre`) REFERENCES `semestres` (`id_semestre`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `notas`
--
ALTER TABLE `notas`
  ADD CONSTRAINT `notas_ibfk_1` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `notas_ibfk_2` FOREIGN KEY (`id_asignatura`) REFERENCES `asignaturas` (`id_asignatura`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `notas_ibfk_3` FOREIGN KEY (`id_anio`) REFERENCES `anios_academicos` (`id_anio`) ON UPDATE CASCADE;

--
-- Constraints for table `profesores`
--
ALTER TABLE `profesores`
  ADD CONSTRAINT `profesores_ibfk_1` FOREIGN KEY (`id_profesor`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `publicaciones`
--
ALTER TABLE `publicaciones`
  ADD CONSTRAINT `publicaciones_ibfk_1` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `semestres`
--
ALTER TABLE `semestres`
  ADD CONSTRAINT `semestres_ibfk_1` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
