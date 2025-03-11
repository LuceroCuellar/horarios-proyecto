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

// Lógica para generar horarios
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generar'])) {
    $grupo_id = intval($_POST['grupo_id']);
    $carrera_id = intval($_POST['carrera_id']);
    $turno = $_POST['turno']; // Obtener el turno seleccionado
    
    $conn->begin_transaction();
    
    try {
        // Eliminar horarios previos
        $conn->query("DELETE FROM horarios WHERE grupo_id = $grupo_id");
        
        // Obtener información del grupo
        $grupo = $conn->query("SELECT * FROM grupos WHERE id = $grupo_id")->fetch_assoc();
        $aula = $grupo['aula']; // Obtener el aula designada para el grupo
        
        // Configuración de turnos
        $config = [
            'matutino' => [
                'hora_inicio_min' => '07:00:00',
                'hora_fin_max' => '14:00:00',
                'duracion_modulo' => [100, 50] // minutos
            ],
            'vespertino' => [
                'hora_inicio_min' => '14:00:00',
                'hora_fin_max' => '20:40:00',
                'duracion_modulo' => [100, 50] // minutos
            ]
        ];
        
        // Obtener materias del grupo desde la tabla grupo_materia
        $materias = $conn->query("
            SELECT m.* 
            FROM materias m
            JOIN grupo_materia gm ON m.id = gm.materia_id
            WHERE gm.grupo_id = $grupo_id
            AND m.estado = 1
        ")->fetch_all(MYSQLI_ASSOC);
        
        // Asignar materias obligatorias administradas por departamentos
        $materias_obligatorias = $conn->query("
            SELECT m.*, md.departamento_id
            FROM materias m
            JOIN materias_departamentos md ON m.id = md.materia_id
            WHERE m.estado = 1
        ")->fetch_all(MYSQLI_ASSOC);
        
        foreach ($materias_obligatorias as $materia) {
            echo "Procesando materia obligatoria: {$materia['nombre']}<br>";
            
            // Obtener horarios proporcionados por el departamento
            $horarios_departamento = $conn->query("
                SELECT * 
                FROM disponibilidad_departamento
                WHERE departamento_id = {$materia['departamento_id']}
            ")->fetch_all(MYSQLI_ASSOC);
            
            foreach ($horarios_departamento as $slot) {
                // Verificar si el horario está disponible para el grupo
                if (horarioDisponible($conn, $grupo_id, null, $materia['id'], $slot['dia'], $slot['hora_inicio'], $slot['hora_fin'])) {
                    echo "Asignando materia obligatoria: {$materia['nombre']} ({$slot['dia']} {$slot['hora_inicio']}-{$slot['hora_fin']})<br>";
                    
                    // Insertar horario (sin profesor, ya que lo asigna el departamento)
                    $conn->query("
                        INSERT INTO horarios 
                        (grupo_id, materia_id, dia, hora_inicio, hora_fin, estado)
                        VALUES (
                            $grupo_id,
                            {$materia['id']},
                            '{$slot['dia']}',
                            '{$slot['hora_inicio']}',
                            '{$slot['hora_fin']}',
                            'aprobado'
                        )
                    ");
                } else {
                    echo "Conflicto de horario para la materia obligatoria: {$materia['nombre']} ({$slot['dia']} {$slot['hora_inicio']}-{$slot['hora_fin']})<br>";
                }
            }
        }
        
        // Asignar el resto de las materias (no obligatorias)
        $materias_no_obligatorias = $conn->query("
            SELECT m.*
            FROM materias m
            LEFT JOIN materias_departamentos md ON m.id = md.materia_id
            WHERE md.departamento_id IS NULL
            AND m.estado = 1
        ")->fetch_all(MYSQLI_ASSOC);
        
        foreach ($materias_no_obligatorias as $materia) {
            echo "Procesando materia no obligatoria: {$materia['nombre']}<br>";
        
            // Verificar si ya hay un profesor asignado a esta materia en el grupo
            $profesor_asignado = $conn->query("
                SELECT profesor_id 
                FROM horarios 
                WHERE grupo_id = $grupo_id 
                AND materia_id = {$materia['id']}
            ")->fetch_assoc();
        
            if ($profesor_asignado) {
                echo "Ya hay un profesor asignado a esta materia en el grupo.<br>";
                continue; // Saltar a la siguiente materia
            }
        
            // Obtener profesores disponibles para la materia
            $profesores = $conn->query("
                SELECT p.* 
                FROM profesores p
                JOIN profesor_materia pm ON pm.profesor_id = p.id
                WHERE pm.materia_id = {$materia['id']}
                AND p.estado = 1
                AND p.horas_disponibles >= {$materia['horas_semanales']}
            ")->fetch_all(MYSQLI_ASSOC);
        
            echo "Profesores disponibles para {$materia['nombre']}: " . count($profesores) . "<br>";
        
            foreach ($profesores as $profesor) {
                echo "Procesando profesor: {$profesor['nombre']}<br>";
        
                // Obtener disponibilidad del profesor
                $disponibilidad = $conn->query("
                    SELECT * 
                    FROM disponibilidad_profesor
                    WHERE profesor_id = {$profesor['id']}
                    AND hora_inicio >= '{$config[$turno]['hora_inicio_min']}'
                    AND hora_fin <= '{$config[$turno]['hora_fin_max']}'
                    ORDER BY dia, hora_inicio
                ")->fetch_all(MYSQLI_ASSOC);
        
                echo "Bloques de disponibilidad: " . count($disponibilidad) . "<br>";
        
                $horas_asignadas = 0;
                foreach ($disponibilidad as $slot) {
                    foreach ($config[$turno]['duracion_modulo'] as $duracion) {
                        $hora_fin = date('H:i:s', strtotime($slot['hora_inicio'] . " + $duracion minutes"));
        
                        if (strtotime($hora_fin) > strtotime($slot['hora_fin'])) {
                            echo "Fin del bloque alcanzado.<br>";
                            continue;
                        }
        
                        // Verificar disponibilidad (incluyendo conflictos de materia)
                        if (horarioDisponible($conn, $grupo_id, $profesor['id'], $materia['id'], $slot['dia'], $slot['hora_inicio'], $hora_fin)) {
                            echo "Horario asignado.<br>";
        
                            // Insertar horario (con profesor)
                            $conn->query("
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
        
                            // Actualizar contadores
                            $horas_asignadas += $duracion / 60;
                            $profesor['horas_disponibles'] -= $duracion / 60;
                            $slot['hora_inicio'] = $hora_fin;
        
                            if ($horas_asignadas >= $materia['horas_semanales']) {
                                echo "Horas completadas para la materia.<br>";
                                break 3; // Salir si se completan las horas semanales
                            }
                        } else {
                            echo "Conflicto de horario o materia.<br>";
                        }
                    }
                }
            }
        }
        
        $conn->commit();
        $mensaje = "Horarios generados exitosamente";
        $tipo = "success";
        
    } catch (Exception $e) {
        $conn->rollback();
        $mensaje = "Error al generar horarios: " . $e->getMessage();
        $tipo = "danger";
    }
}

// Obtener datos necesarios
$carreras = $conn->query("SELECT * FROM carreras WHERE estado = 1")->fetch_all(MYSQLI_ASSOC);
$grupos = $conn->query("SELECT * FROM grupos")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Generar Horarios</title>
    <?php include 'header.php'; ?>
</head>
<body>
    <?php include 'nav.php'; ?>
    
    <div class="container">
        <h2>Generar Horarios</h2>
        
        <?php if(isset($mensaje)): ?>
            <div class="alert alert-<?= $tipo ?>"><?= $mensaje ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Seleccionar Carrera:</label>
                <select name="carrera_id" class="form-control" required>
                    <?php foreach($carreras as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= $c['nombre'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Seleccionar Grupo:</label>
                <select name="grupo_id" class="form-control" required>
                    <?php foreach($grupos as $g): ?>
                        <option value="<?= $g['id'] ?>"><?= $g['nombre'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Seleccionar Turno:</label>
                <select name="turno" class="form-control" required>
                    <option value="matutino">Matutino</option>
                    <option value="vespertino">Vespertino</option>
                </select>
            </div>
            
            <button type="submit" name="generar" class="btn btn-primary">
                Generar Horarios
            </button>
        </form>
    </div>
</body>
</html>