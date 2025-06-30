-- Tabla para los roles de usuario (Administrador, Estudiante, Profesor)
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_rol VARCHAR(50) UNIQUE NOT NULL
);

-- Tabla para la información del departamento (nueva tabla)
CREATE TABLE departamento (
    id_departamento INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    universidad VARCHAR(100) NOT NULL,
    historia TEXT DEFAULT NULL,
    imagen TEXT DEFAULT NULL, -- URL o ruta a la imagen del departamento
    logo_unge TEXT DEFAULT NULL, -- URL o ruta al logo de la UNGE
    logo_pais TEXT DEFAULT NULL, -- URL o ruta al logo del país
    info_matricula TEXT DEFAULT NULL,
    direccion VARCHAR(200) DEFAULT NULL,
    telefono VARCHAR(30) DEFAULT NULL,
    horario VARCHAR(30) DEFAULT NULL
);

-- Tabla para los usuarios del sistema
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_usuario VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    id_rol INT NOT NULL,    
    nombre_completo VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NULL,
    telefono VARCHAR(50) NULL,
    nip VARCHAR(50) UNIQUE NOT NULL; 
    estado ENUM('Activo', 'Inactivo', 'Bloqueado') NOT NULL DEFAULT 'Activo';
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rol
        FOREIGN KEY (id_rol) REFERENCES roles(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);

-- Tabla para años académicos (ej. 2023-2024)
CREATE TABLE anios_academicos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_anio VARCHAR(50) UNIQUE NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL
);

-- Tabla para semestres dentro de un año académico
CREATE TABLE semestres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_anio_academico INT NOT NULL,
    numero_semestre INT NOT NULL, -- 1 o 2 (u otros si la universidad los tiene)
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    UNIQUE (id_anio_academico, numero_semestre), -- Un semestre por año es único
    CONSTRAINT fk_anio_academico
        FOREIGN KEY (id_anio_academico) REFERENCES anios_academicos(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);

-- Tabla para los cursos (ej. Primer Curso, Segundo Curso)
CREATE TABLE cursos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_curso VARCHAR(100) UNIQUE NOT NULL
);

-- Tabla para aulas o salones
CREATE TABLE aulas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_aula VARCHAR(100) UNIQUE NOT NULL,
    capacidad INT NULL
);

-- Tabla para asignaturas o materias
CREATE TABLE asignaturas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_asignatura VARCHAR(255) UNIQUE NOT NULL,
    creditos DECIMAL(4,2) NOT NULL,
    codigo_registro VARCHAR(50) UNIQUE NOT NULL;
    id_prerequisito INT NULL, -- Para asignaturas que dependen de otras
    CONSTRAINT fk_prerequisito
        FOREIGN KEY (id_prerequisito) REFERENCES asignaturas(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS cursos_asignaturas (
    id_curso INT NOT NULL,
    id_asignatura INT NOT NULL,
    ALTER TABLE cursos_asignaturas
 semestre_recomendado INT NULL; 

    PRIMARY KEY (id_curso, id_asignatura), -- La combinación de curso y asignatura debe ser única
    
    CONSTRAINT fk_ca_curso
        FOREIGN KEY (id_curso) REFERENCES cursos(id)
        ON DELETE CASCADE -- Si borras un curso, se borran sus asociaciones con asignaturas aquí.
        ON UPDATE CASCADE,
    
    CONSTRAINT fk_ca_asignatura
        FOREIGN KEY (id_asignatura) REFERENCES asignaturas(id)
        ON DELETE CASCADE -- Si borras una asignatura, se borran sus asociaciones con cursos aquí.
        ON UPDATE CASCADE
);
-- Tabla para almacenar la información específica de los estudiantes
CREATE TABLE estudiantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT UNIQUE NOT NULL,
    id_anio_inicio INT NOT NULL,
    id_curso_inicio INT NOT NULL,
    CONSTRAINT fk_estudiante_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_estudiante_anio_inicio
        FOREIGN KEY (id_anio_inicio) REFERENCES anios_academicos(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_estudiante_curso_inicio
        FOREIGN KEY (id_curso_inicio) REFERENCES cursos(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);

-- Tabla para la sugerencia de asignaturas por parte de los profesores
CREATE TABLE profesores_asignaturas_sugeridas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_profesor INT NOT NULL,
    id_asignatura INT NOT NULL,
    fecha_sugerencia DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_profesor_sugiere
        FOREIGN KEY (id_profesor) REFERENCES usuarios(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_asignatura_sugerida
        FOREIGN KEY (id_asignatura) REFERENCES asignaturas(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    UNIQUE (id_profesor, id_asignatura)
);

-- Tabla para los horarios de las clases
CREATE TABLE horarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_semestre INT NOT NULL,
    id_asignatura INT NOT NULL,
    id_curso INT NOT NULL, -- El curso al que pertenece este horario (ej. "Primer Curso")
    id_profesor INT NOT NULL,
    id_aula INT NOT NULL,
    dia_semana ENUM('Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo') NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    turno ENUM('Tarde', 'Noche') NOT NULL,
    CONSTRAINT fk_horario_semestre
        FOREIGN KEY (id_semestre) REFERENCES semestres(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_horario_asignatura
        FOREIGN KEY (id_asignatura) REFERENCES asignaturas(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_horario_curso
        FOREIGN KEY (id_curso) REFERENCES cursos(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_horario_profesor
        FOREIGN KEY (id_profesor) REFERENCES usuarios(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_horario_aula
        FOREIGN KEY (id_aula) REFERENCES aulas(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);

-- Tabla para la inscripción de estudiantes a asignaturas en un semestre
CREATE TABLE inscripciones_estudiantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_estudiante INT NOT NULL,
    id_semestre INT NOT NULL,
    id_asignatura INT NOT NULL,
    fecha_inscripcion DATETIME DEFAULT CURRENT_TIMESTAMP,
    confirmada BOOLEAN DEFAULT FALSE, -- Administrador debe confirmar la inscripción
    CONSTRAINT fk_inscripcion_estudiante
        FOREIGN KEY (id_estudiante) REFERENCES estudiantes(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_inscripcion_semestre
        FOREIGN KEY (id_semestre) REFERENCES semestres(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_inscripcion_asignatura
        FOREIGN KEY (id_asignatura) REFERENCES asignaturas(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    UNIQUE (id_estudiante, id_semestre, id_asignatura) -- Un estudiante solo se inscribe una vez por asignatura/semestre
);

-- Tabla para las notas de los estudiantes
CREATE TABLE notas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_inscripcion INT NOT NULL, -- Relaciona con la inscripción específica
    nota DECIMAL(5,2) NULL, -- Podría ser NULL si aún no se ha calificado
    estado ENUM('APROBADO', 'REPROBADO', 'PENDIENTE') DEFAULT 'PENDIENTE',
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    acta_final_confirmada BOOLEAN DEFAULT FALSE, -- Si el admin ha confirmado el acta
    CONSTRAINT fk_nota_inscripcion
        FOREIGN KEY (id_inscripcion) REFERENCES inscripciones_estudiantes(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- Tabla para historial académico (se podría poblar a partir de las notas confirmadas)
CREATE TABLE historial_academico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_estudiante INT NOT NULL,
    id_asignatura INT NOT NULL,
    id_semestre INT NOT NULL,
    nota_final DECIMAL(5,2) NOT NULL,
    estado_final ENUM('APROBADO', 'REPROBADO') NOT NULL,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_historial_estudiante
        FOREIGN KEY (id_estudiante) REFERENCES estudiantes(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_historial_asignatura
        FOREIGN KEY (id_asignatura) REFERENCES asignaturas(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_historial_semestre
        FOREIGN KEY (id_semestre) REFERENCES semestres(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);

-- Tabla para almacenar los CVs de los profesores
CREATE TABLE cvs_profesores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_profesor INT UNIQUE NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta_archivo VARCHAR(512) NOT NULL,
    fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cv_profesor
        FOREIGN KEY (id_profesor) REFERENCES usuarios(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- Tabla para publicaciones (noticias, eventos, comunicados) (nueva tabla)
CREATE TABLE publicaciones (
    id_publicacion INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    contenido TEXT NOT NULL,
    tipo ENUM('evento','noticia','comunicado') DEFAULT 'noticia',
    imagen TEXT DEFAULT NULL, -- URL o ruta a la imagen de la publicación
    archivo_adjunto TEXT DEFAULT NULL, -- URL o ruta al archivo adjunto
    fecha_evento DATE DEFAULT NULL, -- Relevante para eventos
    visible BOOLEAN DEFAULT TRUE,
    creado_por INT DEFAULT NULL, -- FK a usuarios.id (Administrador o Profesor)
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_publicacion_creador
        FOREIGN KEY (creado_por) REFERENCES usuarios(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
);

-- Tabla para requisitos de matrícula (nueva tabla)
CREATE TABLE requisitos_matricula (
    id_requisito INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT NOT NULL,
    tipo ENUM('nuevo','antiguo','extranjero','otro') DEFAULT 'nuevo',
    obligatorio BOOLEAN DEFAULT TRUE,
    archivo_modelo TEXT DEFAULT NULL, -- URL o ruta a un archivo modelo (ej. formulario)
    visible BOOLEAN DEFAULT TRUE
);

-- Insertar roles básicos
INSERT INTO roles (nombre_rol) VALUES ('Administrador');
INSERT INTO roles (nombre_rol) VALUES ('Estudiante');
INSERT INTO roles (nombre_rol) VALUES ('Profesor');