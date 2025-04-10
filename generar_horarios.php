<?php
include 'conexion.php';

// Función para verificar si un horario está disponible
function horarioDisponible($conn, $grupo_id, $profesor_id, $materia_id, $dia, $hora_inicio, $hora_fin) {
    // Verificar conflictos de horarios
    $sql_conflicto_horario = "
        SELECT COUNT(*) as total 
        FROM horarios 
        WHERE grupo_id = ? 
        AND dia = ?
        AND (
            (hora_inicio < ? AND hora_fin > ?) OR
            (hora_inicio < ? AND hora_fin > ?) OR
            (hora_inicio >= ? AND hora_fin <= ?)
        )
    ";
    
    // Verificar si la materia ya está asignada a otro profesor en el mismo grupo
    $sql_conflicto_materia = "
        SELECT COUNT(*) as total 
        FROM horarios 
        WHERE grupo_id = ? 
        AND materia_id = ?
        AND profesor_id != ?
    ";

    // Verificar conflictos de horarios
    $stmt_conflicto_horario = $conn->prepare($sql_conflicto_horario);
    if (!$stmt_conflicto_horario) {
        throw new Exception("Error al preparar la consulta de conflicto de horarios: " . $conn->error);
    }
    $stmt_conflicto_horario->bind_param("isssssss", $grupo_id, $dia, $hora_fin, $hora_inicio, $hora_fin, $hora_inicio, $hora_inicio, $hora_fin);
    $stmt_conflicto_horario->execute();
    $result_conflicto_horario = $stmt_conflicto_horario->get_result();
    $row_conflicto_horario = $result_conflicto_horario->fetch_assoc();

    // Si el profesor_id es null (caso de materias de departamentos), solo verificamos conflictos de horario
    if ($profesor_id === null) {
        return $row_conflicto_horario['total'] == 0;
    }

    // Verificar conflictos de materia
    $stmt_conflicto_materia = $conn->prepare($sql_conflicto_materia);
    if (!$stmt_conflicto_materia) {
        throw new Exception("Error al preparar la consulta de conflicto de materia: " . $conn->error);
    }
    $stmt_conflicto_materia->bind_param("iii", $grupo_id, $materia_id, $profesor_id);
    $stmt_conflicto_materia->execute();
    $result_conflicto_materia = $stmt_conflicto_materia->get_result();
    $row_conflicto_materia = $result_conflicto_materia->fetch_assoc();

    // Retornar true si no hay conflictos de horarios ni de materia
    return $row_conflicto_horario['total'] == 0 && $row_conflicto_materia['total'] == 0;
}

// Función para contar las horas asignadas a una materia en un grupo
function horasAsignadasMateria($conn, $grupo_id, $materia_id) {
    $stmt = $conn->prepare("
        SELECT SUM(TIMESTAMPDIFF(MINUTE, hora_inicio, hora_fin)) / 60 as horas_totales
        FROM horarios
        WHERE grupo_id = ? AND materia_id = ?
    ");
    
    $stmt->bind_param("ii", $grupo_id, $materia_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['horas_totales'] ?? 0;
}

// Función para contar cuántas veces aparece una materia en un día
function contarAparicionesMateriaPorDia($conn, $grupo_id, $materia_id, $dia) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM horarios
        WHERE grupo_id = ? AND materia_id = ? AND dia = ?
    ");
    
    $stmt->bind_param("iis", $grupo_id, $materia_id, $dia);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['total'];
}

// Función para eliminar horarios
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar_horario'])) {
    $grupo_id = intval($_POST['grupo_id']);
    
    try {
        $conn->begin_transaction();
        
        // Obtener todos los horarios para restaurar las horas a los profesores
        $horarios = $conn->query("
            SELECT h.*, TIMESTAMPDIFF(MINUTE, h.hora_inicio, h.hora_fin) / 60 as duracion_horas
            FROM horarios h
            WHERE h.grupo_id = $grupo_id AND h.profesor_id IS NOT NULL
        ")->fetch_all(MYSQLI_ASSOC);
        
        // Restaurar horas a los profesores
        foreach ($horarios as $horario) {
            $profesor_id = $horario['profesor_id'];
            $duracion_horas = $horario['duracion_horas'];
            
            $conn->query("
                UPDATE profesores
                SET horas_disponibles = horas_disponibles + $duracion_horas
                WHERE id = $profesor_id
            ");
        }
        
        // Eliminar todos los horarios del grupo
        $conn->query("DELETE FROM horarios WHERE grupo_id = $grupo_id");
        
        $conn->commit();
        $mensaje = "Horarios eliminados correctamente";
        $tipo = "success";
    } catch (Exception $e) {
        $conn->rollback();
        $mensaje = "Error al eliminar horarios: " . $e->getMessage();
        $tipo = "danger";
    }
}

// Lógica para generar horarios
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generar'])) {
    $grupo_id = intval($_POST['grupo_id']);
    $carrera_id = intval($_POST['carrera_id']);
    $semestre = intval($_POST['semestre']);
    $turno = $_POST['turno']; // Obtener el turno seleccionado
    
    // Verificar si ya existe un horario para este grupo
    $verificar_horario = $conn->prepare("SELECT COUNT(*) as total FROM horarios WHERE grupo_id = ?");
    $verificar_horario->bind_param("i", $grupo_id);
    $verificar_horario->execute();
    $result_verificar = $verificar_horario->get_result();
    $row_verificar = $result_verificar->fetch_assoc();
    
    if ($row_verificar['total'] > 0) {
        $mensaje = "Ya existe un horario generado para este grupo. Utilice la opción 'Eliminar Horario' antes de generar uno nuevo.";
        $tipo = "warning";
    } else {
        $conn->begin_transaction();
        
        try {
            // Obtener información del grupo
            $grupo = $conn->query("SELECT * FROM grupos WHERE id = $grupo_id")->fetch_assoc();
            $aula = $grupo['aula']; // Obtener el aula designada para el grupo
            
            // Configuración de turnos
            $config = [
                'matutino' => [
                    'hora_inicio_min' => '07:00:00',
                    'hora_fin_max' => '13:40:00',
                    'duracion_modulo' => 50 // minutos
                ],
                'vespertino' => [
                    'hora_inicio_min' => '14:00:00',
                    'hora_fin_max' => '20:40:00',
                    'duracion_modulo' => 50 // minutos
                ]
            ];
            
            // Definir bloques de horario según turno
            $bloques_horario = [];
            $hora_actual = strtotime($config[$turno]['hora_inicio_min']);
            $hora_fin_max = strtotime($config[$turno]['hora_fin_max']);
            
            while ($hora_actual < $hora_fin_max) {
                $hora_inicio = date('H:i:s', $hora_actual);
                $hora_actual += $config[$turno]['duracion_modulo'] * 60; // Convertir minutos a segundos
                $hora_fin = date('H:i:s', $hora_actual);
                
                $bloques_horario[] = [
                    'hora_inicio' => $hora_inicio,
                    'hora_fin' => $hora_fin
                ];
            }
            
            // Obtener materias del grupo basadas en carrera y semestre
            $materias_query = "
                SELECT m.* 
                FROM materias m
                WHERE m.carrera_id = ? 
                AND m.semestre = ?
                AND m.estado = 1
                ORDER BY m.horas_semanales DESC
            ";
            
            $stmt_materias = $conn->prepare($materias_query);
            $stmt_materias->bind_param("ii", $carrera_id, $semestre);
            $stmt_materias->execute();
            $result_materias = $stmt_materias->get_result();
            $materias = $result_materias->fetch_all(MYSQLI_ASSOC);
            
            // 1. Primero asignar materias de departamentos (Inglés y Desarrollo Humano)
            $materias_departamentos_query = "
                SELECT m.*, d.nombre as departamento_nombre, md.departamento_id
                FROM materias m
                JOIN materias_departamentos md ON m.id = md.materia_id
                JOIN departamentos d ON md.departamento_id = d.id
                WHERE m.estado = 1
                AND m.carrera_id = ?
                AND m.semestre = ?
            ";
            
            $stmt_materias_departamentos = $conn->prepare($materias_departamentos_query);
            $stmt_materias_departamentos->bind_param("ii", $carrera_id, $semestre);
            $stmt_materias_departamentos->execute();
            $result_materias_departamentos = $stmt_materias_departamentos->get_result();
            $materias_departamentos = $result_materias_departamentos->fetch_all(MYSQLI_ASSOC);
            
            // Asignar horarios de departamentos
            foreach ($materias_departamentos as $materia) {
                // Obtener horarios disponibles del departamento para este grupo
                $horarios_departamento_query = "
                    SELECT * 
                    FROM disponibilidad_departamento
                    WHERE departamento_id = ?
                    AND grupo_id = ?
                    ORDER BY dia, hora_inicio
                ";
                
                $stmt_horarios_departamento = $conn->prepare($horarios_departamento_query);
                $stmt_horarios_departamento->bind_param("ii", $materia['departamento_id'], $grupo_id);
                $stmt_horarios_departamento->execute();
                $result_horarios_departamento = $stmt_horarios_departamento->get_result();
                $horarios_departamento = $result_horarios_departamento->fetch_all(MYSQLI_ASSOC);
                
                // Contador de horas asignadas para esta materia
                $horas_asignadas_departamento = 0;
                $horas_requeridas_departamento = $materia['horas_semanales'];
                
                foreach ($horarios_departamento as $slot) {
                    // Verificar que el horario del departamento cae dentro del turno del grupo
                    if (
                        $slot['hora_inicio'] >= $config[$turno]['hora_inicio_min'] &&
                        $slot['hora_fin'] <= $config[$turno]['hora_fin_max'] &&
                        $horas_asignadas_departamento < $horas_requeridas_departamento
                    ) {
                        // Verificar si el horario está disponible para el grupo
                        if (horarioDisponible($conn, $grupo_id, null, $materia['id'], $slot['dia'], $slot['hora_inicio'], $slot['hora_fin'])) {
                            // Insertar horario (sin profesor, ya que lo asigna el departamento)
                            $stmt_insert = $conn->prepare("
                                INSERT INTO horarios 
                                (grupo_id, materia_id, dia, hora_inicio, hora_fin, estado)
                                VALUES (?, ?, ?, ?, ?, 'aprobado')
                            ");
                            
                            $stmt_insert->bind_param("iisss", $grupo_id, $materia['id'], $slot['dia'], $slot['hora_inicio'], $slot['hora_fin']);
                            $stmt_insert->execute();
                            
                            // Actualizar contador de horas asignadas
                            $duracion_horas = (strtotime($slot['hora_fin']) - strtotime($slot['hora_inicio'])) / 3600;
                            $horas_asignadas_departamento += $duracion_horas;
                        }
                    }
                }
            }
            
            // 2. Filtrar las materias que no son de departamentos
            $materias_regulares = array_filter($materias, function($materia) use ($materias_departamentos) {
                foreach ($materias_departamentos as $md) {
                    if ($materia['id'] == $md['id']) {
                        return false;
                    }
                }
                return true;
            });
            
            // 3. Asignar el resto de las materias
            $dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];
            
            foreach ($materias_regulares as $materia) {
                // Obtener profesores que imparten esta materia
                $profesores_query = "
                    SELECT p.* 
                    FROM profesores p
                    JOIN profesor_materia pm ON p.id = pm.profesor_id
                    WHERE pm.materia_id = ?
                    AND p.estado = 1
                    AND p.horas_disponibles >= ?
                    ORDER BY p.horas_disponibles DESC
                ";
                
                $stmt_profesores = $conn->prepare($profesores_query);
                $stmt_profesores->bind_param("id", $materia['id'], $materia['horas_semanales']);
                $stmt_profesores->execute();
                $result_profesores = $stmt_profesores->get_result();
                $profesores = $result_profesores->fetch_all(MYSQLI_ASSOC);
                
                if (empty($profesores)) {
                    // Si no hay profesores disponibles, registrarlo y continuar con la siguiente materia
                    continue;
                }
                
                // Intentar asignar con cada profesor hasta encontrar uno disponible
                $horas_asignadas = 0;
                $horas_requeridas = $materia['horas_semanales'];
                
                // Intentar distribuir las horas de manera equilibrada entre los días
                $asignaciones_por_dia = [];
                foreach ($dias_semana as $dia) {
                    $asignaciones_por_dia[$dia] = 0;
                }
                
                foreach ($profesores as $profesor) {
                    if ($horas_asignadas >= $horas_requeridas) {
                        break; // Ya se asignaron todas las horas necesarias
                    }
                    
                    // Obtener disponibilidad del profesor
                    $disponibilidad_query = "
                        SELECT * 
                        FROM disponibilidad_profesor
                        WHERE profesor_id = ?
                        AND hora_inicio >= ?
                        AND hora_fin <= ?
                        ORDER BY dia, hora_inicio
                    ";
                    
                    $stmt_disponibilidad = $conn->prepare($disponibilidad_query);
                    $stmt_disponibilidad->bind_param("iss", $profesor['id'], $config[$turno]['hora_inicio_min'], $config[$turno]['hora_fin_max']);
                    $stmt_disponibilidad->execute();
                    $result_disponibilidad = $stmt_disponibilidad->get_result();
                    $disponibilidad = $result_disponibilidad->fetch_all(MYSQLI_ASSOC);
                    
                    // Distribuir las horas equilibradamente: intentar asignar 1 hora a cada día primero
                    foreach ($dias_semana as $dia) {
                        if ($horas_asignadas >= $horas_requeridas) {
                            break; // Ya se completaron las horas requeridas
                        }
                        
                        // Obtener la disponibilidad del profesor para este día
                        $disponibilidad_dia = array_filter($disponibilidad, function($d) use ($dia) {
                            return $d['dia'] == $dia;
                        });
                        
                        // Si ya hay 2 horas asignadas en este día, saltar al siguiente
                        if ($asignaciones_por_dia[$dia] >= 2) {
                            continue;
                        }
                        
                        foreach ($bloques_horario as $bloque) {
                            if ($horas_asignadas >= $horas_requeridas || $asignaciones_por_dia[$dia] >= 2) {
                                break; // Ya se completaron las horas o se alcanzó el límite diario
                            }
                            
                            // Verificar si el profesor está disponible en este bloque
                            $profesor_disponible = false;
                            foreach ($disponibilidad_dia as $disp) {
                                if (
                                    $bloque['hora_inicio'] >= $disp['hora_inicio'] &&
                                    $bloque['hora_fin'] <= $disp['hora_fin']
                                ) {
                                    $profesor_disponible = true;
                                    break;
                                }
                            }
                            
                            if ($profesor_disponible) {
                                // Verificar que no hay conflictos con otras materias ya asignadas
                                if (horarioDisponible($conn, $grupo_id, $profesor['id'], $materia['id'], $dia, $bloque['hora_inicio'], $bloque['hora_fin'])) {
                                    // Insertar horario
                                    $stmt_insert = $conn->prepare("
                                        INSERT INTO horarios 
                                        (grupo_id, materia_id, profesor_id, dia, hora_inicio, hora_fin, estado)
                                        VALUES (?, ?, ?, ?, ?, ?, 'preliminar')
                                    ");
                                    
                                    $stmt_insert->bind_param("iiisss", $grupo_id, $materia['id'], $profesor['id'], $dia, $bloque['hora_inicio'], $bloque['hora_fin']);
                                    
                                    if ($stmt_insert->execute()) {
                                        // Actualizar contadores
                                        $horas_asignadas += 1; // Cada bloque es 1 hora (50 minutos)
                                        $asignaciones_por_dia[$dia]++;
                                        
                                        // Actualizar horas disponibles del profesor
                                        $duracion_horas = (strtotime($bloque['hora_fin']) - strtotime($bloque['hora_inicio'])) / 3600;
                                        $profesor['horas_disponibles'] -= $duracion_horas;
                                        
                                        // Actualizar en la base de datos
                                        $stmt_update = $conn->prepare("
                                            UPDATE profesores
                                            SET horas_disponibles = horas_disponibles - ?
                                            WHERE id = ?
                                        ");
                                        $stmt_update->bind_param("di", $duracion_horas, $profesor['id']);
                                        $stmt_update->execute();
                                    }
                                }
                            }
                        }
                    }
                    
                    // Segunda pasada: si no se completaron las horas, intentar asignar hasta 2 horas por día
                    if ($horas_asignadas < $horas_requeridas) {
                        foreach ($dias_semana as $dia) {
                            if ($horas_asignadas >= $horas_requeridas) {
                                break; // Ya se completaron las horas requeridas
                            }
                            
                            // Si ya hay 2 bloques en este día, pasar al siguiente
                            if ($asignaciones_por_dia[$dia] >= 2) {
                                continue;
                            }
                            
                            // Obtener la disponibilidad del profesor para este día
                            $disponibilidad_dia = array_filter($disponibilidad, function($d) use ($dia) {
                                return $d['dia'] == $dia;
                            });
                            
                            foreach ($bloques_horario as $bloque) {
                                if ($horas_asignadas >= $horas_requeridas || $asignaciones_por_dia[$dia] >= 2) {
                                    break; // Ya se completaron las horas o se alcanzó el límite diario
                                }
                                
                                // Verificar si el profesor está disponible en este bloque
                                $profesor_disponible = false;
                                foreach ($disponibilidad_dia as $disp) {
                                    if (
                                        $bloque['hora_inicio'] >= $disp['hora_inicio'] &&
                                        $bloque['hora_fin'] <= $disp['hora_fin']
                                    ) {
                                        $profesor_disponible = true;
                                        break;
                                    }
                                }
                                
                                if ($profesor_disponible) {
                                    // Verificar que no hay conflictos con otras materias ya asignadas
                                    if (horarioDisponible($conn, $grupo_id, $profesor['id'], $materia['id'], $dia, $bloque['hora_inicio'], $bloque['hora_fin'])) {
                                        // Insertar horario
                                        $stmt_insert = $conn->prepare("
                                            INSERT INTO horarios 
                                            (grupo_id, materia_id, profesor_id, dia, hora_inicio, hora_fin, estado)
                                            VALUES (?, ?, ?, ?, ?, ?, 'preliminar')
                                        ");
                                        
                                        $stmt_insert->bind_param("iiisss", $grupo_id, $materia['id'], $profesor['id'], $dia, $bloque['hora_inicio'], $bloque['hora_fin']);
                                        
                                        if ($stmt_insert->execute()) {
                                            // Actualizar contadores
                                            $horas_asignadas += 1; // Cada bloque es 1 hora (50 minutos)
                                            $asignaciones_por_dia[$dia]++;
                                            
                                            // Actualizar horas disponibles del profesor
                                            $duracion_horas = (strtotime($bloque['hora_fin']) - strtotime($bloque['hora_inicio'])) / 3600;
                                            $profesor['horas_disponibles'] -= $duracion_horas;
                                            
                                            // Actualizar en la base de datos
                                            $stmt_update = $conn->prepare("
                                                UPDATE profesores
                                                SET horas_disponibles = horas_disponibles - ?
                                                WHERE id = ?
                                            ");
                                            $stmt_update->bind_param("di", $duracion_horas, $profesor['id']);
                                            $stmt_update->execute();
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                
                // Verificar si se asignaron todas las horas requeridas
                if ($horas_asignadas < $horas_requeridas) {
                    // No se pudieron asignar todas las horas requeridas para la materia
                    $mensaje_error = "No se pudieron asignar todas las horas requeridas para la materia: " . $materia['nombre'];
                    $mensaje_error .= ". Se asignaron " . $horas_asignadas . " de " . $horas_requeridas . " horas.";
                    
                    // Podemos continuar o lanzar una excepción, según lo que decidamos
                    // throw new Exception($mensaje_error);
                    
                    // O simplemente guardar el mensaje para mostrarlo al final
                    $mensajes_advertencia[] = $mensaje_error;
                }
            }
            
            $conn->commit();
            $mensaje = "Horarios generados exitosamente";
            $tipo = "success";
            
            // Si hay mensajes de advertencia, mostrarlos
            if (!empty($mensajes_advertencia)) {
                $mensaje .= ", pero con advertencias: <ul>";
                foreach ($mensajes_advertencia as $adv) {
                    $mensaje .= "<li>" . $adv . "</li>";
                }
                $mensaje .= "</ul>";
                $tipo = "warning";
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            $mensaje = "Error al generar horarios: " . $e->getMessage();
            $tipo = "danger";
        }
    }
}

// Obtener datos necesarios
$carreras = $conn->query("SELECT * FROM carreras WHERE estado = 1")->fetch_all(MYSQLI_ASSOC);
$grupos = $conn->query("SELECT g.*, c.nombre as carrera_nombre 
                        FROM grupos g 
                        JOIN carreras c ON g.carrera_id = c.id 
                        ORDER BY g.nombre")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Generar Horarios</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Fuente Montserrat para el modal -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include 'header.php'; ?>
    <style>
        .info-box {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #3b82f6;
        }
        
        .info-title {
            color: #1e293b;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .info-content {
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .turno-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 5px;
        }
        
        .turno-matutino {
            background-color: #dbeafe;
            color: #2563eb;
        }
        
        .turno-vespertino {
            background-color: #fef3c7;
            color: #d97706;
        }
    </style>
</head>
<body>
    <!-- Botón toggle para menú en móviles -->
    <button class="sidebar-toggle" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Incluir el menú lateral -->
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
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h2>Configuración de Generación</h2>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="form-group">
                                        <label for="carrera_id"><strong>Seleccionar Carrera:</strong></label>
                                        <select name="carrera_id" id="carrera_id" class="form-control" required>
                                            <option value="">Seleccione una carrera</option>
                                            <?php foreach($carreras as $c): ?>
                                                <option value="<?= $c['id'] ?>"><?= $c['nombre'] ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="semestre"><strong>Seleccionar Semestre:</strong></label>
                                        <select name="semestre" id="semestre" class="form-control" required>
                                            <option value="">Seleccione un semestre</option>
                                            <?php for ($i = 1; $i <= 9; $i++): ?>
                                                <option value="<?= $i ?>"><?= $i . '° semestre' ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="grupo_id"><strong>Seleccionar Grupo:</strong></label>
                                        <select name="grupo_id" id="grupo_id" class="form-control" required>
                                            <option value="">Primero seleccione carrera y semestre</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="turno"><strong>Turno:</strong></label>
                                        <select name="turno" id="turno" class="form-control" required>
                                            <option value="matutino">Matutino (7:00 - 13:40)</option>
                                            <option value="vespertino">Vespertino (14:00 - 20:40)</option>
                                        </select>
                                        <small class="form-text text-muted">El turno se autocompletará según el grupo seleccionado.</small>
                                    </div>
                                    

                                    
                                    <button type="submit" name="generar" class="btn-primary">
                                        <i class="fas fa-cogs"></i> Generar Horarios
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h2>Eliminar Horario Existente</h2>
                            </div>
                            <div class="card-body">
                                <form method="POST" onsubmit="return confirm('¿Está seguro de eliminar todos los horarios de este grupo? Esta acción no se puede deshacer.')">
                                    <div class="form-group">
                                        <label for="grupo_eliminar"><strong>Seleccionar Grupo:</strong></label>
                                        <select name="grupo_id" id="grupo_eliminar" class="form-control" required>
                                            <option value="">Seleccione un grupo</option>
                                            <?php foreach($grupos as $g): ?>
                                                <option value="<?= $g['id'] ?>">
                                                    <?= $g['nombre'] . ' - ' . $g['carrera_nombre'] ?>
                                                    <span class="turno-badge turno-<?= $g['turno'] ?>"><?= ucfirst($g['turno']) ?></span>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    

                                    
                                    <button type="submit" name="eliminar_horario" class="btn-danger btn-block">
                                        <i class="fas fa-trash"></i> Eliminar Horario
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <?php if(isset($grupo_id) && isset($tipo) && $tipo == "success"): ?>
                            <div class="card mt-4">
                                <div class="card-header">
                                    <h2>Acciones disponibles</h2>
                                </div>
                                <div class="card-body">
                                    <a href="revisar_horarios.php?grupo_id=<?= $grupo_id ?>" class="btn-primary btn-block mb-3">
                                        <i class="fas fa-eye"></i> Ver horario generado
                                    </a>
                                    <a href="revisar_horarios.php?grupo_id=<?= $grupo_id ?>" class="btn-info btn-block mb-3">
                                        <i class="fas fa-edit"></i> Editar horario
                                    </a>
                                    <a href="horarios_profesores.php" class="btn-secondary btn-block">
                                        <i class="fas fa-user-clock"></i> Ver horarios por profesor
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                
            </div>
        </div>

        <!-- Incluir el footer -->
        <?php include 'footer.php'; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        // Script para cargar grupos según la carrera y semestre seleccionados
        $(document).ready(function() {
            // Almacenar todos los grupos
            var todos_grupos = <?= json_encode($grupos) ?>;
            
            // Función para actualizar el select de grupos
            function actualizarGrupos() {
                var carrera_id = $('#carrera_id').val();
                var semestre = $('#semestre').val();
                var select_grupos = $('#grupo_id');
                
                // Limpiar el select
                select_grupos.empty();
                select_grupos.append('<option value="">Seleccione un grupo</option>');
                
                // Si no hay carrera o semestre seleccionado, no hacer nada más
                if (!carrera_id || !semestre) {
                    return;
                }
                
                // Filtrar grupos por carrera y semestre
                var grupos_filtrados = todos_grupos.filter(function(grupo) {
                    return grupo.carrera_id == carrera_id && grupo.semestre == semestre;
                });
                
                // Añadir los grupos filtrados al select
                grupos_filtrados.forEach(function(grupo) {
                    select_grupos.append(
                        '<option value="' + grupo.id + '" data-turno="' + grupo.turno + '">' + 
                        grupo.nombre + ' - ' + grupo.carrera_nombre + 
                        ' (' + (grupo.turno == 'matutino' ? 'Matutino' : 'Vespertino') + ')' +
                        '</option>'
                    );
                });
            }
            
            // Evento para cuando se selecciona un grupo
            $('#grupo_id').change(function() {
                var selected_option = $(this).find('option:selected');
                var turno = selected_option.data('turno');
                
                if (turno) {
                    $('#turno').val(turno);
                }
            });
            
            // Eventos para cambio de carrera o semestre
            $('#carrera_id, #semestre').change(function() {
                actualizarGrupos();
            });
        });
    </script>
</body>
</html>