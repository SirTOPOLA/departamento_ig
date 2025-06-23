-- Crear la base de datos
CREATE DATABASE IF NOT EXISTS departamento_it
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;
USE departamento_it;

-- =============================
-- 1. Tabla de usuarios base
-- =============================
CREATE TABLE usuarios (
  id_usuario INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  apellido VARCHAR(100),
  dni VARCHAR(100) UNIQUE NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  contrasena VARCHAR(255) NOT NULL,
  direccion VARCHAR(255) NOT NULL,
  telefono VARCHAR(30),
  rol ENUM('administrador', 'profesor', 'estudiante') NOT NULL,
  estado TINYINT(1) DEFAULT 1,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================
-- 2. Tabla de profesores
-- =============================
CREATE TABLE profesores (
  id_profesor INT PRIMARY KEY,
  especialidad VARCHAR(100),
  FOREIGN KEY (id_profesor) REFERENCES usuarios(id_usuario)
    ON DELETE CASCADE ON UPDATE CASCADE
);

-- =============================
-- 3. Tabla de estudiantes
-- =============================
CREATE TABLE estudiantes (
  id_estudiante INT PRIMARY KEY,
  matricula VARCHAR(20) UNIQUE NOT NULL,
  FOREIGN KEY (id_estudiante) REFERENCES usuarios(id_usuario)
    ON DELETE CASCADE ON UPDATE CASCADE
);

-- =============================
-- 4. Tabla de cursos
-- =============================
CREATE TABLE cursos (
  id_curso INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  turno ENUM('tarde', 'noche') NOT NULL,
  grupo INT NOT NULL DEFAULT 1,
  descripcion TEXT
);
CREATE TABLE curso_estudiante (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_estudiante INT NOT NULL,
  id_curso INT NOT NULL,
  id_anio INT NOT NULL,
  estado ENUM('activo', 'finalizado', 'reprobado') DEFAULT 'activo',
  fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_estudiante) REFERENCES estudiantes(id_estudiante)
    ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (id_curso) REFERENCES cursos(id_curso)
    ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (id_anio) REFERENCES anios_academicos(id_anio)
    ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE(id_estudiante, id_curso, id_anio)
);

-- =============================
-- 5. Tabla de años académicos
-- =============================
CREATE TABLE anios_academicos (
  id_anio INT AUTO_INCREMENT PRIMARY KEY,
  anio VARCHAR(10) NOT NULL, -- Ej: '2024-2025'
  activo BOOLEAN DEFAULT TRUE,
  fecha_inicio DATE,
  fecha_fin DATE
);

-- =============================
-- 6. Tabla de semestres
-- =============================
CREATE TABLE semestres (
  id_semestre INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL, -- Ej. "Primer Semestre"
  curso_id INT NOT NULL,
  FOREIGN KEY (curso_id) REFERENCES cursos(id_curso)
    ON DELETE CASCADE ON UPDATE CASCADE
);

-- =============================
-- 7. Tabla de asignaturas
-- =============================
CREATE TABLE asignaturas (
  id_asignatura INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  descripcion TEXT,
  codigo VARCHAR(20) UNIQUE,
  curso_id INT NOT NULL,
  semestre_id INT NOT NULL,
  FOREIGN KEY (curso_id) REFERENCES cursos(id_curso)
    ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (semestre_id) REFERENCES semestres(id_semestre)
    ON DELETE CASCADE ON UPDATE CASCADE
);

-- =============================
-- 8. Tabla de requisitos entre asignaturas
-- =============================
CREATE TABLE asignatura_requisitos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  asignatura_id INT NOT NULL,   -- Asignatura que requiere
  requisito_id INT NOT NULL,    -- Asignatura requerida
  FOREIGN KEY (asignatura_id) REFERENCES asignaturas(id_asignatura)
    ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (requisito_id) REFERENCES asignaturas(id_asignatura)
    ON DELETE CASCADE ON UPDATE CASCADE
);

-- =============================
-- 9. Relación asignatura-profesor
-- =============================
CREATE TABLE asignatura_profesor (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_profesor INT NOT NULL,
  id_asignatura INT NOT NULL,
  FOREIGN KEY (id_profesor) REFERENCES profesores(id_profesor)
    ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (id_asignatura) REFERENCES asignaturas(id_asignatura)
    ON DELETE CASCADE ON UPDATE CASCADE
);

-- =============================
-- 10. Tabla de inscripciones por estudiante
-- =============================
CREATE TABLE inscripciones (
  id_inscripcion INT AUTO_INCREMENT PRIMARY KEY,
  id_estudiante INT NOT NULL,
  id_asignatura INT NOT NULL,
  id_anio INT NOT NULL,
  id_semestre INT NOT NULL,
  estado ENUM('preinscrito', 'confirmado', 'rechazado') DEFAULT 'preinscrito',
  tipo ENUM('regular', 'arrastre') DEFAULT 'regular',
  fecha_inscripcion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(id_estudiante, id_asignatura, id_anio, id_semestre),
  FOREIGN KEY (id_estudiante) REFERENCES estudiantes(id_estudiante)
    ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (id_asignatura) REFERENCES asignaturas(id_asignatura)
    ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (id_anio) REFERENCES anios_academicos(id_anio)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (id_semestre) REFERENCES semestres(id_semestre)
    ON DELETE CASCADE ON UPDATE CASCADE
);

-- =============================
-- 11. Tabla de notas (vinculada a inscripción)
-- =============================
CREATE TABLE notas (
  id_nota INT AUTO_INCREMENT PRIMARY KEY,
  id_inscripcion INT NOT NULL,
  parcial_1 DECIMAL(5,2),
  parcial_2 DECIMAL(5,2),
  examen_final DECIMAL(5,2),
  promedio DECIMAL(5,2) GENERATED ALWAYS AS (
    ROUND((IFNULL(parcial_1, 0) + IFNULL(parcial_2, 0) + IFNULL(examen_final, 0)) / 3, 2)
  ) STORED,
  observaciones TEXT,
  FOREIGN KEY (id_inscripcion) REFERENCES inscripciones(id_inscripcion)
    ON DELETE CASCADE
);

-- =============================
-- 12. (Opcional) Historial académico del estudiante
-- =============================
CREATE TABLE historial_academico (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_estudiante INT NOT NULL,
  id_asignatura INT NOT NULL,
  resultado ENUM('aprobado', 'reprobado', 'abandono', 'convalidado') NOT NULL,
  nota_final DECIMAL(5,2),
  id_anio INT,
  observacion TEXT,
  fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_estudiante) REFERENCES estudiantes(id_estudiante),
  FOREIGN KEY (id_asignatura) REFERENCES asignaturas(id_asignatura),
  FOREIGN KEY (id_anio) REFERENCES anios_academicos(id_anio)
);

-- Tabla de publicaciones (notificaciones)
CREATE TABLE publicaciones (
  id_publicacion INT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(255) NOT NULL,
  contenido TEXT NOT NULL,
  tipo ENUM('evento', 'noticia', 'comunicado') DEFAULT 'noticia',
  imagen TEXT,                    -- Ruta a la imagen destacada (opcional)
  archivo_adjunto TEXT,           -- Ruta a PDF o documento opcional
  fecha_evento DATE,              -- Solo si aplica (para eventos)
  visible BOOLEAN DEFAULT TRUE,   -- Permite ocultar sin borrar
  creado_por INT,                 -- Usuario que creó el evento
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (creado_por) REFERENCES usuarios(id_usuario)
    ON DELETE SET NULL
    ON UPDATE CASCADE
);

-- Tabla de requisitos para matrícula
CREATE TABLE requisitos_matricula (
  id_requisito INT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(255) NOT NULL,
  descripcion TEXT NOT NULL,
  tipo ENUM('nuevo', 'antiguo', 'extranjero', 'otro') DEFAULT 'nuevo',
  obligatorio BOOLEAN DEFAULT TRUE,
  archivo_modelo TEXT,
  visible BOOLEAN DEFAULT TRUE
);

-- Tabla de aulas
CREATE TABLE aulas (
  id_aula INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL,
  capacidad INT NOT NULL DEFAULT 10,
  ubicacion VARCHAR(100)
);

-- Tabla intermedia curso-aula
CREATE TABLE curso_aula (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_curso INT NOT NULL,
  id_aula INT NOT NULL,
  FOREIGN KEY (id_curso) REFERENCES cursos(id_curso)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  FOREIGN KEY (id_aula) REFERENCES aulas(id_aula)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);
-- Tabla de horarios
CREATE TABLE horarios (
  id_horario INT AUTO_INCREMENT PRIMARY KEY,
  id_asignatura INT NOT NULL,
  id_profesor INT NOT NULL,
  aula_id INT NOT NULL,
  dia ENUM('Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado') NOT NULL,
  hora_inicio TIME NOT NULL,
  hora_fin TIME NOT NULL,
  FOREIGN KEY (id_asignatura) REFERENCES asignaturas(id_asignatura)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  FOREIGN KEY (id_profesor) REFERENCES profesores(id_profesor)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  FOREIGN KEY (aula_id) REFERENCES aulas(id_aula)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);
-- Información general del departamento
CREATE TABLE departamento (
  id_departamento INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  universidad VARCHAR(100) NOT NULL,
  historia TEXT,
  imagen TEXT,
  logo_unge TEXT,
  logo_pais TEXT,
  info_matricula TEXT,
  direccion VARCHAR(200),
  telefono VARCHAR(30),
  horario VARCHAR(30)
);
