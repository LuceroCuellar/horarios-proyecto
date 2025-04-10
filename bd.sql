CREATE DATABASE sistema_horarios2;
USE sistema_horarios2;

-- Tabla de carreras
CREATE TABLE carreras (
  id int(11) NOT NULL AUTO_INCREMENT,
  nombre varchar(100) NOT NULL,
  codigo varchar(10) NOT NULL,
  semestres int(11) NOT NULL,
  estado tinyint(1) DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabla de departamentos
CREATE TABLE departamentos (
  id int(11) NOT NULL AUTO_INCREMENT,
  nombre varchar(100) NOT NULL,
  estado tinyint(1) DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabla de profesores
CREATE TABLE profesores (
  id int(11) NOT NULL AUTO_INCREMENT,
  nombre varchar(100) NOT NULL,
  apellidos varchar(100) NOT NULL,
  email varchar(100) DEFAULT NULL,
  telefono varchar(20) DEFAULT NULL,
  horas_disponibles int(11) NOT NULL,
  estado tinyint(1) DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabla de materias (depende de carreras)
CREATE TABLE materias (
  id int(11) NOT NULL AUTO_INCREMENT,
  codigo varchar(20) NOT NULL,
  nombre varchar(100) NOT NULL,
  horas_semanales int(11) NOT NULL,
  semestre int(11) NOT NULL,
  carrera_id int(11) DEFAULT NULL,
  estado tinyint(1) DEFAULT 1,
  PRIMARY KEY (id),
  KEY carrera_id (carrera_id),
  CONSTRAINT materias_ibfk_1 FOREIGN KEY (carrera_id) REFERENCES carreras (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabla de disponibilidad_departamento (depende de departamentos y grupos)
CREATE TABLE disponibilidad_departamento (
  id int(11) NOT NULL AUTO_INCREMENT,
  departamento_id int(11) DEFAULT NULL,
  grupo_id int(11) DEFAULT NULL,
  dia varchar(20) NOT NULL,
  hora_inicio time NOT NULL,
  hora_fin time NOT NULL,
  PRIMARY KEY (id),
  KEY departamento_id (departamento_id),
  CONSTRAINT disponibilidad_departamento_ibfk_1 FOREIGN KEY (departamento_id) REFERENCES departamentos (id)
  CONSTRAINT grupo_id FOREIGN KEY (grupo_id) REFERENCES grupos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabla de disponibilidad_profesor (depende de profesores)
CREATE TABLE disponibilidad_profesor (
  id int(11) NOT NULL AUTO_INCREMENT,
  profesor_id int(11) DEFAULT NULL,
  dia varchar(20) NOT NULL,
  hora_inicio time NOT NULL,
  hora_fin time NOT NULL,
  PRIMARY KEY (id),
  KEY profesor_id (profesor_id),
  CONSTRAINT disponibilidad_profesor_ibfk_1 FOREIGN KEY (profesor_id) REFERENCES profesores (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Tabla de grupos (depende de carreras)
CREATE TABLE grupos (
  id int(11) NOT NULL AUTO_INCREMENT,
  nombre varchar(50) NOT NULL,
  semestre int(11) NOT NULL,
  carrera_id int(11) DEFAULT NULL,
  periodo varchar(50) NOT NULL,
  anio year(4) NOT NULL,
  turno ENUM('matutino', 'vespertino') NOT NULL,
  aula VARCHAR(20) NULL,
  PRIMARY KEY (id),
  KEY carrera_id (carrera_id),
  CONSTRAINT grupos_ibfk_1 FOREIGN KEY (carrera_id) REFERENCES carreras (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabla de profesor_materia (depende de profesores y materias)
CREATE TABLE profesor_materia (
  id int(11) NOT NULL AUTO_INCREMENT,
  profesor_id int(11) DEFAULT NULL,
  materia_id int(11) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY profesor_id (profesor_id, materia_id),
  KEY materia_id (materia_id),
  CONSTRAINT profesor_materia_ibfk_1 FOREIGN KEY (profesor_id) REFERENCES profesores (id),
  CONSTRAINT profesor_materia_ibfk_2 FOREIGN KEY (materia_id) REFERENCES materias (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabla de materias_departamentos (depende de materias y departamentos)
CREATE TABLE materias_departamentos (
  id_materia_departamento INT AUTO_INCREMENT PRIMARY KEY,
  materia_id INT NOT NULL,
  departamento_id INT NULL,
  FOREIGN KEY (materia_id) REFERENCES materias(id),
  FOREIGN KEY (departamento_id) REFERENCES departamentos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabla de horarios (depende de grupos, materias y profesores)
CREATE TABLE horarios (
  id int(11) NOT NULL AUTO_INCREMENT,
  grupo_id int(11) DEFAULT NULL,
  materia_id int(11) DEFAULT NULL,
  profesor_id int(11) DEFAULT NULL,
  dia varchar(20) NOT NULL,
  hora_inicio time NOT NULL,
  hora_fin time NOT NULL,
  estado varchar(20) DEFAULT 'preliminar',
  PRIMARY KEY (id),
  KEY grupo_id (grupo_id),
  KEY materia_id (materia_id),
  KEY profesor_id (profesor_id),
  CONSTRAINT horarios_ibfk_1 FOREIGN KEY (grupo_id) REFERENCES grupos (id),
  CONSTRAINT horarios_ibfk_2 FOREIGN KEY (materia_id) REFERENCES materias (id),
  CONSTRAINT horarios_ibfk_3 FOREIGN KEY (profesor_id) REFERENCES profesores (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabla de grupo_materia (depende de grupos y materias)
CREATE TABLE grupo_materia (
  id INT AUTO_INCREMENT PRIMARY KEY,
  grupo_id INT NOT NULL,
  materia_id INT NOT NULL,
  FOREIGN KEY (grupo_id) REFERENCES grupos(id),
  FOREIGN KEY (materia_id) REFERENCES materias(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

