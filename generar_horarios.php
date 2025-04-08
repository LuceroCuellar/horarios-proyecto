<?php
include 'conexion.php';

// Configurar logs detallados
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Obtener datos necesarios para los selects
$carreras = $conn->query("SELECT * FROM carreras WHERE estado = 1")->fetch_all(MYSQLI_ASSOC);
$grupos = $conn->query("SELECT * FROM grupos")->fetch_all(MYSQLI_ASSOC);

// Función mejorada para registrar logs
function registrarLog($conn, $grupo_id, $mensaje, $tipo = 'info', $materia_id = null, $profesor_id = null) {
    $mensaje = $conn->real_escape_string($mensaje);
    $tipo = $conn->real_escape_string($tipo);
    
    $query = "INSERT INTO log_horarios 
              (grupo_id, materia_id, profesor_id, tipo, mensaje, fecha) 
              VALUES (
                  $grupo_id,
                  " . ($materia_id ? $materia_id : 'NULL') . ",
                  " . ($profesor_id ? $profesor_id : 'NULL') . ",
                  '$tipo',
                  '$mensaje',
                  NOW()
              )";
    
    if (!$conn->query($query)) {
        error_log("Error al registrar log: " . $conn->error);
    }
    return true;
}

// Función mejorada para obtener profesores para una materia
function obtenerProfesoresParaMateria($conn, $materia_id, $horas_requeridas) {
    $query = "
        SELECT p.*, 
               IFNULL((
                   SELECT SUM(TIME_TO_SEC(TIMEDIFF(h.hora_fin, h.hora_inicio))/3600)
                   FROM horarios h 
                   WHERE h.profesor_id = p.id AND h.estado != 'cancelado'
               ), 0) as horas_asignadas
        FROM profesores p
        JOIN profesor_materia pm ON p.id = pm.profesor_id
        WHERE pm.materia_id = $materia_id
        AND p.estado = 1
        AND (p.horas_disponibles - IFNULL((
            SELECT SUM(TIME_TO_SEC(TIMEDIFF(h.hora_fin, h.hora_inicio))/3600) 
            FROM horarios h 
            WHERE h.profesor_id = p.id AND h.estado != 'cancelado'
        ), 0)) >= $horas_requeridas
        ORDER BY horas_asignadas ASC, p.horas_disponibles DESC
    ";
    
    $result = $conn->query($query);
    if (!$result) {
        throw new Exception("Error en consulta de profesores: " . $conn->error);
    }
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Función para obtener disponibilidad de profesor
function obtenerDisponibilidadProfesor($conn, $profesor_id, $config_turno) {
    $result = $conn->query("
        SELECT * 
        FROM disponibilidad_profesor
        WHERE profesor_id = $profesor_id
        AND hora_inicio >= '{$config_turno['hora_inicio_min']}'
        AND hora_fin <= '{$config_turno['hora_fin_max']}'
        ORDER BY FIELD(dia, 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'), hora_inicio
    ");
    
    if (!$result) {
        throw new Exception("Error en consulta de disponibilidad: " . $conn->error);
    }
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Función mejorada para verificar disponibilidad
function horarioDisponible($conn, $grupo_id, $profesor_id, $materia_id, $dia, $hora_inicio, $hora_fin) {
    // Verificar conflictos para el grupo
    $conflicto_grupo = $conn->query("
        SELECT COUNT(*) as total 
        FROM horarios 
        WHERE grupo_id = $grupo_id 
        AND dia = '$dia'
        AND (
            (hora_inicio < '$hora_fin' AND hora_fin > '$hora_inicio') OR
            (hora_inicio >= '$hora_inicio' AND hora_fin <= '$hora_fin')
        )
        AND estado != 'cancelado'
    ")->fetch_assoc();

    if ($conflicto_grupo['total'] > 0) {
        registrarLog($conn, $grupo_id, "Conflicto de horario para grupo $grupo_id el $dia entre $hora_inicio y $hora_fin", 'warning');
        return false;
    }

    // Verificar si la materia ya está asignada a otro profesor
    if ($profesor_id) {
        $materia_asignada = $conn->query("
            SELECT COUNT(*) as total 
            FROM horarios 
            WHERE grupo_id = $grupo_id 
            AND materia_id = $materia_id
            AND profesor_id != $profesor_id
            AND estado != 'cancelado'
        ")->fetch_assoc();

        if ($materia_asignada['total'] > 0) {
            registrarLog($conn, $grupo_id, "Materia $materia_id ya asignada a otro profesor en el grupo $grupo_id", 'warning', $materia_id);
            return false;
        }
    }

    // Verificar disponibilidad del profesor
    if ($profesor_id) {
        $conflicto_profesor = $conn->query("
            SELECT COUNT(*) as total 
            FROM horarios 
            WHERE profesor_id = $profesor_id 
            AND dia = '$dia'
            AND (
                (hora_inicio < '$hora_fin' AND hora_fin > '$hora_inicio') OR
                (hora_inicio >= '$hora_inicio' AND hora_fin <= '$hora_fin')
            )
            AND estado != 'cancelado'
        ")->fetch_assoc();

        if ($conflicto_profesor['total'] > 0) {
            registrarLog($conn, $grupo_id, "Profesor $profesor_id ya tiene clase el $dia entre $hora_inicio y $hora_fin", 'warning', null, $profesor_id);
            return false;
        }
    }

    return true;
}

// Función mejorada para asignar bloques de horarios
function asignarBloques($conn, $grupo_id, $materia, $profesor, $disponibilidad, $modulos, $turno, $config) {
    $horas_asignadas = 0;
    $duracion_minutos = $modulos * 50;
    $duracion_horas = $duracion_minutos / 60;
    $intentos = 0;
    $max_intentos = count($disponibilidad) * 3; // Aumentamos los intentos
    $horarios_intentados = [];

    while ($horas_asignadas < $materia['horas_semanales'] && $intentos < $max_intentos) {
        foreach ($disponibilidad as $slot) {
            $hora_fin = date('H:i:s', strtotime($slot['hora_inicio'] . " + $duracion_minutos minutes"));
            
            // Saltar si ya intentamos este horario
            $key = $slot['dia'].$slot['hora_inicio'].$hora_fin;
            if (isset($horarios_intentados[$key])) {
                continue;
            }
            $horarios_intentados[$key] = true;

            // Verificar que el bloque no exceda la disponibilidad del profesor
            if (strtotime($hora_fin) > strtotime($slot['hora_fin'])) {
                registrarLog($conn, $grupo_id, "Slot no válido (excede disponibilidad): {$slot['dia']} {$slot['hora_inicio']}-{$hora_fin}", 'debug', $materia['id'], $profesor['id']);
                continue;
            }
            
            if (horarioDisponible($conn, $grupo_id, $profesor['id'], $materia['id'], $slot['dia'], $slot['hora_inicio'], $hora_fin)) {
                $insert = $conn->query("
                    INSERT INTO horarios 
                    (grupo_id, materia_id, profesor_id, dia, hora_inicio, hora_fin, estado)
                    VALUES (
                        $grupo_id,
                        {$materia['id']},
                        {$profesor['id']},
                        '{$slot['dia']}',
                        '{$slot['hora_inicio']}',
                        '$hora_fin',
                        'preliminar'
                    )
                ");
                
                if (!$insert) {
                    registrarLog($conn, $grupo_id, "Error al insertar horario: " . $conn->error, 'error', $materia['id'], $profesor['id']);
                    continue;
                }
                
                $horas_asignadas += $duracion_horas;
                
                registrarLog($conn, $grupo_id, "Asignado bloque de $modulos módulos el {$slot['dia']} de {$slot['hora_inicio']} a $hora_fin (Horas asignadas: $horas_asignadas)", 'success', $materia['id'], $profesor['id']);
                
                if ($horas_asignadas >= $materia['horas_semanales']) {
                    return $horas_asignadas;
                }
            } else {
                registrarLog($conn, $grupo_id, "No se pudo asignar bloque de $modulos módulos el {$slot['dia']} de {$slot['hora_inicio']} a $hora_fin", 'warning', $materia['id'], $profesor['id']);
            }
        }
        $intentos++;
    }
    
    return $horas_asignadas;
}

// Configuración de turnos mejorada
$config = [
    'matutino' => [
        'hora_inicio_min' => '07:00:00',
        'hora_fin_max' => '13:40:00',
        'modulos' => [
            ['inicio' => '07:00:00', 'fin' => '07:50:00'],
            ['inicio' => '07:50:00', 'fin' => '08:40:00'],
            ['inicio' => '08:40:00', 'fin' => '09:30:00'],
            ['inicio' => '09:30:00', 'fin' => '10:20:00'],
            ['inicio' => '10:40:00', 'fin' => '11:30:00'],
            ['inicio' => '11:30:00', 'fin' => '12:20:00'],
            ['inicio' => '12:20:00', 'fin' => '13:10:00'],
            ['inicio' => '13:10:00', 'fin' => '14:00:00']
        ],
        'bloques' => [
            ['modulos' => 2, 'preferido' => true],
            ['modulos' => 1, 'preferido' => false]
        ]
    ],
    'vespertino' => [
        'hora_inicio_min' => '14:00:00',
        'hora_fin_max' => '20:40:00',
        'modulos' => [
            ['inicio' => '14:00:00', 'fin' => '14:50:00'],
            ['inicio' => '14:50:00', 'fin' => '15:40:00'],
            ['inicio' => '15:40:00', 'fin' => '16:30:00'],
            ['inicio' => '16:30:00', 'fin' => '17:20:00'],
            ['inicio' => '17:40:00', 'fin' => '18:30:00'],
            ['inicio' => '18:30:00', 'fin' => '19:20:00'],
            ['inicio' => '19:20:00', 'fin' => '20:10:00'],
            ['inicio' => '20:10:00', 'fin' => '21:00:00']
        ],
        'bloques' => [
            ['modulos' => 2, 'preferido' => true],
            ['modulos' => 1, 'preferido' => false]
        ]
    ]
];

// Lógica para generar horarios
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generar'])) {
    $grupo_id = intval($_POST['grupo_id']);
    $carrera_id = intval($_POST['carrera_id']);
    $turno = in_array($_POST['turno'], ['matutino', 'vespertino']) ? $_POST['turno'] : 'matutino';
    $preferencia_bloques = $_POST['preferencia_bloques'] ?? 'mixto';
    $evitar_huecos = isset($_POST['evitar_huecos']);
    
    registrarLog($conn, $grupo_id, "Iniciando generación de horarios para grupo $grupo_id, turno $turno", 'info');
    
    $conn->begin_transaction();
    
    try {
        // Eliminar horarios previos
        $conn->query("DELETE FROM horarios WHERE grupo_id = $grupo_id");
        registrarLog($conn, $grupo_id, "Eliminados horarios anteriores del grupo", 'info');
        
        // Obtener información del grupo
        $grupo = $conn->query("SELECT * FROM grupos WHERE id = $grupo_id")->fetch_assoc();
        if (!$grupo) {
            throw new Exception("No se encontró el grupo con ID $grupo_id");
        }
        registrarLog($conn, $grupo_id, "Grupo obtenido: {$grupo['nombre']} (Semestre: {$grupo['semestre']})", 'info');
        
        // 1. Asignar materias obligatorias administradas por departamentos
        registrarLog($conn, $grupo_id, "Buscando materias de departamento para el grupo", 'info');
        $materias_obligatorias = $conn->query("
            SELECT m.*, md.departamento_id, d.nombre as departamento
            FROM materias m
            JOIN materias_departamentos md ON m.id = md.materia_id
            JOIN grupo_materia gm ON m.id = gm.materia_id
            JOIN departamentos d ON md.departamento_id = d.id
            WHERE gm.grupo_id = $grupo_id
            AND m.estado = 1
        ")->fetch_all(MYSQLI_ASSOC);
        
        registrarLog($conn, $grupo_id, "Encontradas " . count($materias_obligatorias) . " materias de departamento", 'info');
        
        foreach ($materias_obligatorias as $materia) {
            registrarLog($conn, $grupo_id, "Procesando materia de departamento: {$materia['nombre']} (Horas: {$materia['horas_semanales']}, Departamento: {$materia['departamento']})", 'info', $materia['id']);
            
            $horarios_departamento = $conn->query("
                SELECT * 
                FROM disponibilidad_departamento
                WHERE departamento_id = {$materia['departamento_id']}
                AND hora_inicio >= '{$config[$turno]['hora_inicio_min']}'
                AND hora_fin <= '{$config[$turno]['hora_fin_max']}'
                ORDER BY dia, hora_inicio
            ")->fetch_all(MYSQLI_ASSOC);
            
            registrarLog($conn, $grupo_id, "Disponibilidad encontrada para departamento: " . count($horarios_departamento) . " slots", 'info', $materia['id']);
            
            foreach ($horarios_departamento as $slot) {
                $hora_fin = date('H:i:s', strtotime($slot['hora_inicio'] . " + 50 minutes")); // 1 módulo por defecto
                
                registrarLog($conn, $grupo_id, "Intentando asignar {$materia['nombre']} el {$slot['dia']} de {$slot['hora_inicio']} a $hora_fin", 'debug', $materia['id']);
                
                if (horarioDisponible($conn, $grupo_id, null, $materia['id'], $slot['dia'], $slot['hora_inicio'], $hora_fin)) {
                    $conn->query("
                        INSERT INTO horarios 
                        (grupo_id, materia_id, dia, hora_inicio, hora_fin, estado)
                        VALUES (
                            $grupo_id,
                            {$materia['id']},
                            '{$slot['dia']}',
                            '{$slot['hora_inicio']}',
                            '$hora_fin',
                            'aprobado'
                        )
                    ");
                    
                    registrarLog($conn, $grupo_id, "Asignada materia {$materia['nombre']} el {$slot['dia']} de {$slot['hora_inicio']} a $hora_fin", 'success', $materia['id']);
                } else {
                    registrarLog($conn, $grupo_id, "No se pudo asignar {$materia['nombre']} el {$slot['dia']} de {$slot['hora_inicio']} a $hora_fin", 'warning', $materia['id']);
                }
            }
        }
        
        // 2. Asignar materias no obligatorias (con profesores)
        registrarLog($conn, $grupo_id, "Buscando materias regulares para el grupo", 'info');
        $materias_no_obligatorias = $conn->query("
            SELECT m.*
            FROM materias m
            JOIN grupo_materia gm ON m.id = gm.materia_id
            LEFT JOIN materias_departamentos md ON m.id = md.materia_id
            WHERE gm.grupo_id = $grupo_id
            AND md.departamento_id IS NULL
            AND m.estado = 1
            ORDER BY m.horas_semanales DESC, m.nombre ASC
        ")->fetch_all(MYSQLI_ASSOC);
        
        registrarLog($conn, $grupo_id, "Encontradas " . count($materias_no_obligatorias) . " materias regulares", 'info');
        
        foreach ($materias_no_obligatorias as $materia) {
            $horas_requeridas = $materia['horas_semanales'];
            registrarLog($conn, $grupo_id, "Procesando materia regular: {$materia['nombre']} ($horas_requeridas horas semanales)", 'info', $materia['id']);
            
            // Obtener profesores disponibles para esta materia
            $profesores = obtenerProfesoresParaMateria($conn, $materia['id'], $horas_requeridas);
            
            // Si no hay profesores con horas suficientes, buscar con mínimo 1 hora
            if (empty($profesores) && $horas_requeridas > 1) {
                registrarLog($conn, $grupo_id, "Buscando profesores con disponibilidad mínima para {$materia['nombre']}", 'warning', $materia['id']);
                $profesores = obtenerProfesoresParaMateria($conn, $materia['id'], 1);
            }
            
            registrarLog($conn, $grupo_id, "Profesores disponibles para {$materia['nombre']}: " . count($profesores), 'info', $materia['id']);
            
            $horas_asignadas = 0;
            $profesor_index = 0;
            
            // Primero intentar con bloques grandes si es posible
            if ($horas_requeridas >= 1.67 && $preferencia_bloques != '1_modulo') {
                foreach ($profesores as $profesor) {
                    registrarLog($conn, $grupo_id, "Intentando asignar con profesor: {$profesor['nombre']} {$profesor['apellidos']} (Horas disp: {$profesor['horas_disponibles']})", 'info', $materia['id'], $profesor['id']);
                    
                    $disponibilidad = obtenerDisponibilidadProfesor($conn, $profesor['id'], $config[$turno]);
                    registrarLog($conn, $grupo_id, "Disponibilidad del profesor: " . count($disponibilidad) . " slots", 'info', $materia['id'], $profesor['id']);
                    
                    $asignadas = asignarBloques($conn, $grupo_id, $materia, $profesor, $disponibilidad, 2, $turno, $config);
                    $horas_asignadas += $asignadas;
                    
                    if ($horas_asignadas >= $horas_requeridas) {
                        break;
                    }
                }
            }
            
            // Luego intentar con bloques pequeños si aún faltan horas
            if ($horas_asignadas < $horas_requeridas) {
                foreach ($profesores as $profesor) {
                    $disponibilidad = obtenerDisponibilidadProfesor($conn, $profesor['id'], $config[$turno]);
                    $asignadas = asignarBloques($conn, $grupo_id, $materia, $profesor, $disponibilidad, 1, $turno, $config);
                    $horas_asignadas += $asignadas;
                    
                    if ($horas_asignadas >= $horas_requeridas) {
                        break;
                    }
                }
            }
            
            // Si aún faltan horas, intentar con otros profesores (asignación parcial)
            if ($horas_asignadas < $horas_requeridas) {
                registrarLog($conn, $grupo_id, "Buscando profesores alternativos para completar horas de {$materia['nombre']}", 'warning', $materia['id']);
                
                $profesores_alternativos = $conn->query("
                    SELECT p.* FROM profesores p
                    JOIN profesor_materia pm ON p.id = pm.profesor_id
                    WHERE pm.materia_id = {$materia['id']}
                    AND p.estado = 1
                    ORDER BY p.horas_disponibles DESC
                ")->fetch_all(MYSQLI_ASSOC);
                
                foreach ($profesores_alternativos as $profesor) {
                    $disponibilidad = obtenerDisponibilidadProfesor($conn, $profesor['id'], $config[$turno]);
                    $asignadas = asignarBloques($conn, $grupo_id, $materia, $profesor, $disponibilidad, 1, $turno, $config);
                    $horas_asignadas += $asignadas;
                    
                    if ($horas_asignadas >= $horas_requeridas) {
                        break;
                    }
                }
            }
            
            if ($horas_asignadas < $horas_requeridas) {
                $faltantes = $horas_requeridas - $horas_asignadas;
                registrarLog($conn, $grupo_id, "No se completaron todas las horas para {$materia['nombre']} (Faltan: $faltantes)", 'warning', $materia['id']);
            } else {
                registrarLog($conn, $grupo_id, "Materia {$materia['nombre']} completada satisfactoriamente", 'success', $materia['id']);
            }
        }
        
        // Verificar asignación completa
        registrarLog($conn, $grupo_id, "Verificando asignación completa de materias", 'info');
        $resultado = $conn->query("
            SELECT 
                m.id, 
                m.nombre, 
                m.horas_semanales, 
                IFNULL(SUM(
                    TIME_TO_SEC(
                        TIMEDIFF(h.hora_fin, h.hora_inicio)
                    )/3600
                ), 0) as horas_asignadas
            FROM materias m
            JOIN grupo_materia gm ON m.id = gm.materia_id
            LEFT JOIN horarios h ON m.id = h.materia_id AND h.grupo_id = $grupo_id AND h.estado != 'cancelado'
            WHERE gm.grupo_id = $grupo_id
            GROUP BY m.id, m.nombre, m.horas_semanales
        ");
        
        if (!$resultado) {
            throw new Exception("Error en consulta de verificación: " . $conn->error);
        }
        
        while ($row = $resultado->fetch_assoc()) {
            if ($row['horas_asignadas'] < $row['horas_semanales']) {
                registrarLog($conn, $grupo_id, 
                    "Advertencia: {$row['nombre']} tiene {$row['horas_asignadas']} horas asignadas de {$row['horas_semanales']} requeridas", 
                    'warning', 
                    $row['id']
                );
            } else {
                registrarLog($conn, $grupo_id, 
                    "Materia {$row['nombre']} completada: {$row['horas_asignadas']}/{$row['horas_semanales']} horas", 
                    'success', 
                    $row['id']
                );
            }
        }
        
        $conn->commit();
        $mensaje = "Horarios generados exitosamente para el grupo ".$grupo['nombre'];
        $tipo = "success";
        registrarLog($conn, $grupo_id, "Generación de horarios completada con éxito", 'success');
        
        // Recuperar logs para mostrar (limitamos a 500 para no sobrecargar)
        $logs = $conn->query("
            SELECT l.*, 
                   m.nombre as materia, 
                   CONCAT(p.nombre, ' ', p.apellidos) as profesor
            FROM log_horarios l
            LEFT JOIN materias m ON l.materia_id = m.id
            LEFT JOIN profesores p ON l.profesor_id = p.id
            WHERE l.grupo_id = $grupo_id
            ORDER BY l.fecha DESC
            LIMIT 500
        ")->fetch_all(MYSQLI_ASSOC);
        
    } catch (Exception $e) {
        $conn->rollback();
        $mensaje = "Error al generar horarios: " . $e->getMessage();
        $tipo = "danger";
        registrarLog($conn, $grupo_id, "Error en generación de horarios: " . $e->getMessage(), 'error');
        
        // Recuperar logs incluso en caso de error
        if (isset($grupo_id)) {
            $logs = $conn->query("
                SELECT l.*, 
                       m.nombre as materia, 
                       CONCAT(p.nombre, ' ', p.apellidos) as profesor
                FROM log_horarios l
                LEFT JOIN materias m ON l.materia_id = m.id
                LEFT JOIN profesores p ON l.profesor_id = p.id
                WHERE l.grupo_id = $grupo_id
                ORDER BY l.fecha DESC
                LIMIT 500
            ")->fetch_all(MYSQLI_ASSOC);
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Generar Horarios</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="horarios.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include 'header.php'; ?>
    <style>
        .log-container {
            max-height: 400px;
            overflow-y: auto;
            margin-top: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
            background-color: #f9f9f9;
        }
        .log-entry {
            padding: 8px;
            border-bottom: 1px solid #eee;
            font-family: monospace;
            font-size: 0.9em;
        }
        .log-info { color: #31708f; }
        .log-success { color: #3c763d; }
        .log-warning { color: #8a6d3b; }
        .log-error { color: #a94442; }
        .log-debug { color: #333; }
    </style>
</head>
<body>
    <button class="sidebar-toggle" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>

    <?php include 'nav.php'; ?>

    <div class="page-wrapper">
        <div class="content-wrapper">
            <div class="page-header">
                <h1>Generar Horarios</h1>
            </div>
            
            <div class="container">
                <?php if(isset($mensaje)): ?>
                    <div class="alert alert-<?= $tipo ?>"><?= $mensaje ?></div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h2>Configuración de Generación</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label for="carrera_id">Seleccionar Carrera:</label>
                                <select name="carrera_id" id="carrera_id" class="form-control" required>
                                    <?php foreach($carreras as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= $c['nombre'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="grupo_id">Seleccionar Grupo:</label>
                                <select name="grupo_id" id="grupo_id" class="form-control" required>
                                    <?php foreach($grupos as $g): ?>
                                        <option value="<?= $g['id'] ?>"><?= $g['nombre'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="turno">Seleccionar Turno:</label>
                                <select name="turno" id="turno" class="form-control" required>
                                    <option value="matutino">Matutino (7:00 - 13:40)</option>
                                    <option value="vespertino">Vespertino (14:00 - 20:40)</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="preferencia_bloques">Preferencia de bloques:</label>
                                <select name="preferencia_bloques" id="preferencia_bloques" class="form-control">
                                    <option value="2_modulos">Preferir bloques de 2 módulos (100 min)</option>
                                    <option value="1_modulo">Permitir bloques de 1 módulo (50 min)</option>
                                    <option value="mixto" selected>Mezcla de bloques (recomendado)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="evitar_huecos" name="evitar_huecos" checked>
                                    <label class="form-check-label" for="evitar_huecos">
                                        Intentar evitar huecos entre clases
                                    </label>
                                </div>
                            </div>
                            
                            <button type="submit" name="generar" class="btn-primary">
                                <i class="fas fa-cogs"></i> Generar Horarios
                            </button>
                        </form>
                    </div>
                </div>
                
                <?php if(isset($grupo_id) && isset($logs)): ?>
                    <div class="card mt-4">
                        <div class="card-header">
                            <h3>Vista Previa del Horario Generado</h3>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> Después de generar los horarios, puedes revisarlos y ajustarlos en la sección "Revisar Horarios".
                            </div>
                            <a href="revisar_horarios.php?grupo_id=<?= $grupo_id ?>" class="btn btn-primary">
                                <i class="fas fa-calendar-alt"></i> Ver Horario Completo
                            </a>
                        </div>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-header">
                            <h3>Registro de Proceso</h3>
                        </div>
                        <div class="card-body">
                            <div class="log-container">
                                <?php foreach($logs as $log): ?>
                                    <div class="log-entry log-<?= $log['tipo'] ?>">
                                        [<?= date('H:i:s', strtotime($log['fecha'])) ?>] 
                                        <?php if($log['materia']): ?>
                                            <strong><?= $log['materia'] ?></strong> 
                                        <?php endif; ?>
                                        <?php if($log['profesor']): ?>
                                            (Prof: <?= $log['profesor'] ?>) 
                                        <?php endif; ?>
                                        - <?= $log['mensaje'] ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php include 'footer.php'; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.querySelector('.sidebar');
            const contentWrapper = document.querySelector('.content-wrapper');
            const mainFooter = document.querySelector('.main-footer');
            
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                
                if (window.innerWidth <= 768) {
                    if (sidebar.classList.contains('active')) {
                        contentWrapper.style.marginLeft = '270px';
                        if (mainFooter) mainFooter.style.marginLeft = '270px';
                    } else {
                        contentWrapper.style.marginLeft = '0';
                        if (mainFooter) mainFooter.style.marginLeft = '0';
                    }
                }
            });
            
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    contentWrapper.style.marginLeft = '';
                    if (mainFooter) mainFooter.style.marginLeft = '';
                } else {
                    if (sidebar.classList.contains('active')) {
                        contentWrapper.style.marginLeft = '270px';
                        if (mainFooter) mainFooter.style.marginLeft = '270px';
                    } else {
                        contentWrapper.style.marginLeft = '0';
                        if (mainFooter) mainFooter.style.marginLeft = '0';
                    }
                }
            });
            
            // Confirmación antes de generar
            document.querySelector('button[name="generar"]').addEventListener('click', function(e) {
                if(!confirm('¿Estás seguro? Esto eliminará todos los horarios existentes para este grupo.')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>