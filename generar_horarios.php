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
            // Obtener horarios proporcionados por el departamento
            $horarios_departamento = $conn->query("
                SELECT * 
                FROM disponibilidad_departamento
                WHERE departamento_id = {$materia['departamento_id']}
            ")->fetch_all(MYSQLI_ASSOC);
            
            foreach ($horarios_departamento as $slot) {
                // Verificar si el horario está disponible para el grupo
                if (horarioDisponible($conn, $grupo_id, null, $materia['id'], $slot['dia'], $slot['hora_inicio'], $slot['hora_fin'])) {
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
            // Verificar si ya hay un profesor asignado a esta materia en el grupo
            $profesor_asignado = $conn->query("
                SELECT profesor_id 
                FROM horarios 
                WHERE grupo_id = $grupo_id 
                AND materia_id = {$materia['id']}
            ")->fetch_assoc();
        
            if ($profesor_asignado) {
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
        
            foreach ($profesores as $profesor) {
                // Obtener disponibilidad del profesor
                $disponibilidad = $conn->query("
                    SELECT * 
                    FROM disponibilidad_profesor
                    WHERE profesor_id = {$profesor['id']}
                    AND hora_inicio >= '{$config[$turno]['hora_inicio_min']}'
                    AND hora_fin <= '{$config[$turno]['hora_fin_max']}'
                    ORDER BY dia, hora_inicio
                ")->fetch_all(MYSQLI_ASSOC);
        
                $horas_asignadas = 0;
                foreach ($disponibilidad as $slot) {
                    foreach ($config[$turno]['duracion_modulo'] as $duracion) {
                        $hora_fin = date('H:i:s', strtotime($slot['hora_inicio'] . " + $duracion minutes"));
        
                        if (strtotime($hora_fin) > strtotime($slot['hora_fin'])) {
                            continue;
                        }
        
                        // Verificar disponibilidad (incluyendo conflictos de materia)
                        if (horarioDisponible($conn, $grupo_id, $profesor['id'], $materia['id'], $slot['dia'], $slot['hora_inicio'], $hora_fin)) {
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
                                break 3; // Salir si se completan las horas semanales
                            }
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Fuente Montserrat para el modal -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include 'header.php'; ?>
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
                                    <option value="matutino">Matutino</option>
                                    <option value="vespertino">Vespertino</option>
                                </select>
                            </div>
                            
                            <button type="submit" name="generar" class="btn-primary">
                                <i class="fas fa-cogs"></i> Generar Horarios
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Incluir el footer -->
        <?php include 'footer.php'; ?>
    </div>

    <!-- Script para el toggle del menú lateral -->
    <script>                               
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.querySelector('.sidebar');
            const contentWrapper = document.querySelector('.content-wrapper');
            const mainFooter = document.querySelector('.main-footer');
            
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                
                // Ajustar el margen del contenido y footer en dispositivos móviles
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
            
            // Restablecer estilos cuando se redimensiona la ventana
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
        });
    </script>
</body>
</html>