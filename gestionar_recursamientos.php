<?php
include 'conexion.php';

// Registrar nuevo recursamiento
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registrar_recursamiento'])) {
    $profesor_id = $_POST['profesor_id'];
    $materia_id = $_POST['materia_id'];
    $grupo_id = $_POST['grupo_id'];
    $dia = $_POST['dia'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fin = $_POST['hora_fin'];
    $periodo = $_POST['periodo'];
    $anio = $_POST['anio'];
    
    // Validar que la hora de fin sea mayor que la hora de inicio
    if ($hora_inicio >= $hora_fin) {
        $mensaje = "La hora de fin debe ser mayor que la hora de inicio";
        $tipo = "danger";
    } else {
        // Verificar disponibilidad del profesor
        $disponible = verificarDisponibilidadProfesor($conn, $profesor_id, $dia, $hora_inicio, $hora_fin);
        
        if (!$disponible) {
            $mensaje = "El profesor no está disponible en el horario seleccionado";
            $tipo = "danger";
        } else {
            // Verificar que no hay conflictos con otros horarios
            $conflicto = verificarConflictoHorario($conn, $profesor_id, $dia, $hora_inicio, $hora_fin);
            
            if ($conflicto) {
                $mensaje = "El profesor ya tiene asignada otra clase en este horario";
                $tipo = "danger";
            } else {
                // Registrar el recursamiento
                $stmt = $conn->prepare("
                    INSERT INTO recursamientos (profesor_id, materia_id, grupo_id, dia, hora_inicio, hora_fin, periodo, anio)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->bind_param("iiissssi", $profesor_id, $materia_id, $grupo_id, $dia, $hora_inicio, $hora_fin, $periodo, $anio);
                
                if ($stmt->execute()) {
                    // Actualizar las horas disponibles del profesor
                    $duracion_horas = (strtotime($hora_fin) - strtotime($hora_inicio)) / 3600;
                    
                    $stmt_update = $conn->prepare("
                        UPDATE profesores
                        SET horas_disponibles = horas_disponibles - ?
                        WHERE id = ?
                    ");
                    
                    $stmt_update->bind_param("di", $duracion_horas, $profesor_id);
                    $stmt_update->execute();
                    
                    $mensaje = "Recursamiento registrado correctamente";
                    $tipo = "success";
                } else {
                    $mensaje = "Error al registrar recursamiento: " . $conn->error;
                    $tipo = "danger";
                }
            }
        }
    }
}

// Eliminar recursamiento
if (isset($_GET['eliminar']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Obtener información del recursamiento antes de eliminarlo
    $recursamiento = $conn->query("
        SELECT r.*, TIMESTAMPDIFF(MINUTE, r.hora_inicio, r.hora_fin) / 60 as duracion_horas
        FROM recursamientos r
        WHERE r.id = $id
    ")->fetch_assoc();
    
    if ($recursamiento) {
        // Eliminar el recursamiento
        $resultado = $conn->query("DELETE FROM recursamientos WHERE id = $id");
        
        if ($resultado) {
            // Restaurar las horas disponibles del profesor
            $profesor_id = $recursamiento['profesor_id'];
            $duracion_horas = $recursamiento['duracion_horas'];
            
            $conn->query("
                UPDATE profesores
                SET horas_disponibles = horas_disponibles + $duracion_horas
                WHERE id = $profesor_id
            ");
            
            $mensaje = "Recursamiento eliminado correctamente";
            $tipo = "success";
        } else {
            $mensaje = "Error al eliminar el recursamiento: " . $conn->error;
            $tipo = "danger";
        }
    } else {
        $mensaje = "No se encontró el recursamiento a eliminar";
        $tipo = "danger";
    }
}

// Función para verificar disponibilidad del profesor
function verificarDisponibilidadProfesor($conn, $profesor_id, $dia, $hora_inicio, $hora_fin) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM disponibilidad_profesor
        WHERE profesor_id = ?
        AND dia = ?
        AND hora_inicio <= ?
        AND hora_fin >= ?");
    
    $stmt->bind_param("isss", $profesor_id, $dia, $hora_inicio, $hora_fin);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $row = $resultado->fetch_assoc();
    
    return $row['total'] > 0;
}

// Función para verificar conflictos en horarios
function verificarConflictoHorario($conn, $profesor_id, $dia, $hora_inicio, $hora_fin) {
    // Verificar conflictos en horarios normales
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM horarios
        WHERE profesor_id = ?
        AND dia = ?
        AND (
            (hora_inicio < ? AND hora_fin > ?) OR
            (hora_inicio < ? AND hora_fin > ?) OR
            (hora_inicio >= ? AND hora_fin <= ?)
        )
    ");
    
    $stmt->bind_param("isssssss", $profesor_id, $dia, $hora_fin, $hora_inicio, $hora_fin, $hora_inicio, $hora_inicio, $hora_fin);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $row = $resultado->fetch_assoc();
    
    if ($row['total'] > 0) {
        return true;
    }
    
    // Verificar conflictos en otros recursamientos
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM recursamientos
        WHERE profesor_id = ?
        AND dia = ?
        AND (
            (hora_inicio < ? AND hora_fin > ?) OR
            (hora_inicio < ? AND hora_fin > ?) OR
            (hora_inicio >= ? AND hora_fin <= ?)
        )
    ");
    
    $stmt->bind_param("isssssss", $profesor_id, $dia, $hora_fin, $hora_inicio, $hora_fin, $hora_inicio, $hora_inicio, $hora_fin);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $row = $resultado->fetch_assoc();
    
    return $row['total'] > 0;
}

// Obtener carreras para filtrar
$carreras = $conn->query("SELECT * FROM carreras WHERE estado = 1 ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);

// Variables para filtrado
$carrera_id = isset($_GET['carrera_id']) ? intval($_GET['carrera_id']) : 0;
$semestre = isset($_GET['semestre']) ? intval($_GET['semestre']) : 0;

// Obtener profesores con horas disponibles después de considerar horarios ya asignados
$profesores = $conn->query("
    SELECT p.*, 
           IFNULL(SUM(TIMESTAMPDIFF(MINUTE, r.hora_inicio, r.hora_fin) / 60), 0) as horas_asignadas_recursamiento,
           (
               SELECT IFNULL(SUM(TIMESTAMPDIFF(MINUTE, h.hora_inicio, h.hora_fin) / 60), 0)
               FROM horarios h
               WHERE h.profesor_id = p.id
           ) as horas_asignadas_clases
    FROM profesores p
    LEFT JOIN recursamientos r ON p.id = r.profesor_id AND r.estado = 1
    WHERE p.estado = 1
    GROUP BY p.id
    HAVING p.horas_disponibles - horas_asignadas_recursamiento - horas_asignadas_clases > 0
    ORDER BY p.nombre
")->fetch_all(MYSQLI_ASSOC);

// Obtener materias según filtros
$materias = [];
if ($carrera_id && $semestre) {
    $materias = $conn->query("
        SELECT * FROM materias 
        WHERE carrera_id = $carrera_id 
        AND semestre = $semestre 
        AND estado = 1
        ORDER BY nombre
    ")->fetch_all(MYSQLI_ASSOC);
}

// Obtener grupos para selección
$grupos = $conn->query("SELECT * FROM grupos ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);

// Obtener los recursamientos registrados para mostrarlos
$recursamientos = $conn->query("
    SELECT r.*,
           p.nombre as profesor_nombre, 
           p.apellidos as profesor_apellidos,
           m.nombre as materia_nombre,
           g.nombre as grupo_nombre,
           c.nombre as carrera_nombre
    FROM recursamientos r
    JOIN profesores p ON r.profesor_id = p.id
    JOIN materias m ON r.materia_id = m.id
    JOIN grupos g ON r.grupo_id = g.id
    JOIN carreras c ON g.carrera_id = c.id
    WHERE r.estado = 1
    ORDER BY r.dia, r.hora_inicio
")->fetch_all(MYSQLI_ASSOC);

// Organizar recursamientos por día
$recursamientos_por_dia = [];
foreach ($recursamientos as $r) {
    if (!isset($recursamientos_por_dia[$r['dia']])) {
        $recursamientos_por_dia[$r['dia']] = [];
    }
    $recursamientos_por_dia[$r['dia']][] = $r;
}

// Días de la semana
$dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Recursamientos</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include 'header.php'; ?>
    <style>
        .profesor-horas-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            background-color: #dbeafe;
            color: #1e40af;
            margin-left: 5px;
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
                <h1>Gestión de Recursamientos</h1>
            </div>
            
            <div class="container">
                <?php if (isset($mensaje)): ?>
                    <div class="alert alert-<?php echo $tipo; ?> alert-dismissible fade show" role="alert">
                        <?php echo $mensaje; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <!-- Formulario para registrar recursamiento -->
                    <div class="col-lg-5">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h2>Registrar Recursamiento</h2>
                            </div>
                            <div class="card-body">
                                <!-- Formulario de filtrado de materias -->
                                <div class="form-group">
                                    <label for="carrera_id"><strong>Filtrar materias por carrera:</strong></label>
                                    <select class="form-control" id="carrera_id" name="carrera_id" onchange="filtrarMaterias()">
                                        <option value="">Seleccione una carrera</option>
                                        <?php foreach ($carreras as $carrera): ?>
                                            <option value="<?php echo $carrera['id']; ?>" <?php echo ($carrera_id == $carrera['id']) ? 'selected' : ''; ?>>
                                                <?php echo $carrera['nombre']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="semestre"><strong>Semestre:</strong></label>
                                    <select class="form-control" id="semestre" name="semestre" onchange="filtrarMaterias()">
                                        <option value="">Seleccione un semestre</option>
                                        <?php for ($i = 1; $i <= 9; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo ($semestre == $i) ? 'selected' : ''; ?>>
                                                <?php echo $i . '° semestre'; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                
                                <hr>
                                
                                <!-- Formulario para registrar recursamiento -->
                                <form method="POST" id="formRecursamiento">
                                    <div class="form-group">
                                        <label for="profesor_id"><strong>Profesor:</strong></label>
                                        <select class="form-control" id="profesor_id" name="profesor_id" required>
                                            <option value="">Seleccione un profesor</option>
                                            <?php foreach ($profesores as $profesor): 
                                                // Calcular horas realmente disponibles
                                                $horas_reales_disponibles = $profesor['horas_disponibles'] - $profesor['horas_asignadas_recursamiento'] - $profesor['horas_asignadas_clases'];
                                            ?>
                                                <option value="<?php echo $profesor['id']; ?>" data-horas="<?php echo $horas_reales_disponibles; ?>">
                                                    <?php echo $profesor['nombre'] . ' ' . $profesor['apellidos']; ?> 
                                                    <span class="profesor-horas-badge"><?php echo $horas_reales_disponibles; ?> hrs. disponibles</span>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="materia_id"><strong>Materia para recursamiento:</strong></label>
                                        <select class="form-control" id="materia_id" name="materia_id" required>
                                            <option value="">Primero seleccione carrera y semestre</option>
                                            <?php foreach ($materias as $materia): ?>
                                                <option value="<?php echo $materia['id']; ?>" data-horas="<?php echo $materia['horas_semanales']; ?>">
                                                    <?php echo $materia['nombre']; ?> (<?php echo $materia['horas_semanales']; ?> horas)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="grupo_id"><strong>Grupo:</strong></label>
                                        <select class="form-control" id="grupo_id" name="grupo_id" required>
                                            <option value="">Seleccione un grupo</option>
                                            <?php foreach ($grupos as $grupo): ?>
                                                <option value="<?php echo $grupo['id']; ?>">
                                                    <?php echo $grupo['nombre']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="dia"><strong>Día de la semana:</strong></label>
                                        <select class="form-control" id="dia" name="dia" required>
                                            <option value="">Seleccione un día</option>
                                            <?php foreach ($dias_semana as $dia): ?>
                                                <option value="<?php echo $dia; ?>"><?php echo $dia; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label for="hora_inicio"><strong>Hora de inicio:</strong></label>
                                            <input type="time" class="form-control" id="hora_inicio" name="hora_inicio" required>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label for="hora_fin"><strong>Hora de fin:</strong></label>
                                            <input type="time" class="form-control" id="hora_fin" name="hora_fin" required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label for="periodo"><strong>Periodo:</strong></label>
                                            <select class="form-control" id="periodo" name="periodo" required>
                                                <option value="Ene-Abr">Enero - Abril</option>
                                                <option value="May-Ago">Mayo - Agosto</option>
                                                <option value="Sep-Dic">Septiembre - Diciembre</option>
                                            </select>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label for="anio"><strong>Año:</strong></label>
                                            <select class="form-control" id="anio" name="anio" required>
                                                <?php
                                                $year = date('Y');
                                                for ($i = $year - 1; $i <= $year + 1; $i++) {
                                                    echo "<option value=\"$i\"" . ($i == $year ? " selected" : "") . ">$i</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="alert alert-info mt-3 mb-3" id="horas_info" style="display: none;">
                                        <i class="fas fa-info-circle"></i> 
                                        <span id="horas_mensaje"></span>
                                    </div>
                                    
                                    <button type="submit" name="registrar_recursamiento" class="btn-primary btn-block">
                                        <i class="fas fa-plus"></i> Registrar Recursamiento
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recursamientos registrados -->
                    <div class="col-lg-7">
                        <div class="card">
                            <div class="card-header">
                                <h2>Recursamientos Registrados</h2>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recursamientos)): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> No hay recursamientos registrados.
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($dias_semana as $dia): ?>
                                        <?php if (isset($recursamientos_por_dia[$dia]) && !empty($recursamientos_por_dia[$dia])): ?>
                                            <div class="mb-4">
                                                <div class="dia-header">
                                                    <i class="fas fa-calendar-day mr-2"></i> <?php echo $dia; ?>
                                                </div>
                                                
                                                <?php foreach ($recursamientos_por_dia[$dia] as $recursamiento): ?>
                                                    <div class="recursamiento-card">
                                                        <div class="recursamiento-header">
                                                            <div class="recursamiento-materia">
                                                                <?php echo $recursamiento['materia_nombre']; ?>
                                                            </div>
                                                            <div class="recursamiento-horario">
                                                                <i class="far fa-clock"></i> 
                                                                <?php 
                                                                    echo date('H:i', strtotime($recursamiento['hora_inicio'])) . ' - ' . 
                                                                        date('H:i', strtotime($recursamiento['hora_fin'])); 
                                                                ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="recursamiento-profesor">
                                                            <i class="fas fa-user-tie"></i> 
                                                            <?php echo $recursamiento['profesor_nombre'] . ' ' . $recursamiento['profesor_apellidos']; ?>
                                                        </div>
                                                        
                                                        <div class="recursamiento-grupo">
                                                            <i class="fas fa-users"></i> 
                                                            <?php echo $recursamiento['grupo_nombre'] . ' - ' . $recursamiento['carrera_nombre']; ?>
                                                        </div>
                                                        
                                                        <div class="recursamiento-acciones">
                                                            <a href="?eliminar=1&id=<?php echo $recursamiento['id']; ?>" 
                                                               class="btn-danger" 
                                                               onclick="return confirm('¿Está seguro de eliminar este recursamiento? Esto restaurará las horas disponibles del profesor.')">
                                                                <i class="fas fa-trash"></i> Eliminar
                                                            </a>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
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
        // Función para filtrar materias por carrera y semestre
        function filtrarMaterias() {
            var carreraId = $('#carrera_id').val();
            var semestre = $('#semestre').val();
            
            if (carreraId && semestre) {
                window.location.href = 'gestionar_recursamientos.php?carrera_id=' + carreraId + '&semestre=' + semestre;
            }
        }
        
        $(document).ready(function() {
            // Mostrar información de horas al seleccionar profesor y materia
            $('#profesor_id, #materia_id').change(function() {
                var profesorId = $('#profesor_id').val();
                var materiaId = $('#materia_id').val();
                
                if (profesorId && materiaId) {
                    var horasDisponibles = parseFloat($('#profesor_id option:selected').attr('data-horas'));
                    var horasMateria = parseFloat($('#materia_id option:selected').attr('data-horas'));
                    
                    $('#horas_info').show();
                    
                    if (horasDisponibles >= horasMateria) {
                        $('#horas_mensaje').html('El profesor tiene <strong>' + horasDisponibles + '</strong> horas disponibles. ' +
                                                'La materia requiere <strong>' + horasMateria + '</strong> horas semanales.');
                        $('#horas_info').removeClass('alert-danger').addClass('alert-info');
                    } else {
                        $('#horas_mensaje').html('¡Advertencia! El profesor solo tiene <strong>' + horasDisponibles + '</strong> horas disponibles, ' +
                                                'pero la materia requiere <strong>' + horasMateria + '</strong> horas semanales.');
                        $('#horas_info').removeClass('alert-info').addClass('alert-danger');
                    }
                } else {
                    $('#horas_info').hide();
                }
            });
            
            // Calcular duración de clase para validar horas disponibles
            $('#hora_inicio, #hora_fin').change(function() {
                var horaInicio = $('#hora_inicio').val();
                var horaFin = $('#hora_fin').val();
                
                if (horaInicio && horaFin) {
                    // Validar que la hora de fin sea mayor que la hora de inicio
                    if (horaInicio >= horaFin) {
                        alert('La hora de fin debe ser mayor que la hora de inicio');
                        $('#hora_fin').val('');
                    } else {
                        // Calcular la duración en horas
                        var inicio = new Date('2023-01-01T' + horaInicio + ':00');
                        var fin = new Date('2023-01-01T' + horaFin + ':00');
                        var duracionHoras = (fin - inicio) / (1000 * 60 * 60);
                        
                        // Actualizar el mensaje de información si hay un profesor y materia seleccionados
                        var profesorId = $('#profesor_id').val();
                        var materiaId = $('#materia_id').val();
                        
                        if (profesorId && materiaId) {
                            var horasDisponibles = parseFloat($('#profesor_id option:selected').attr('data-horas'));
                            
                            $('#horas_info').show();
                            if (horasDisponibles >= duracionHoras) {
                                $('#horas_mensaje').html('Este bloque ocupará <strong>' + duracionHoras + '</strong> horas. ' +
                                                       'El profesor tiene <strong>' + horasDisponibles + '</strong> horas disponibles.');
                                $('#horas_info').removeClass('alert-danger').addClass('alert-info');
                            } else {
                                $('#horas_mensaje').html('¡Advertencia! Este bloque ocupará <strong>' + duracionHoras + '</strong> horas, ' +
                                                       'pero el profesor solo tiene <strong>' + horasDisponibles + '</strong> horas disponibles.');
                                $('#horas_info').removeClass('alert-info').addClass('alert-danger');
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>