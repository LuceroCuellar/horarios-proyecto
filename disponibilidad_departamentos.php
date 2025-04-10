<?php
// Conexión a la base de datos
include 'conexion.php';

// Obtener todos los departamentos
$stmt_departamentos = $conn->prepare("SELECT * FROM departamentos WHERE estado = 1 ORDER BY nombre");
$stmt_departamentos->execute();
$result = $stmt_departamentos->get_result();
$departamentos = $result->fetch_all(MYSQLI_ASSOC);

// Obtener todos los grupos para selección
$stmt_grupos = $conn->prepare("SELECT g.*, c.nombre as carrera_nombre 
                                FROM grupos g 
                                JOIN carreras c ON g.carrera_id = c.id 
                                ORDER BY g.nombre");
$stmt_grupos->execute();
$result = $stmt_grupos->get_result();
$grupos = $result->fetch_all(MYSQLI_ASSOC);

// Obtener departamento seleccionado si existe
$departamento_id = isset($_GET['departamento_id']) ? $_GET['departamento_id'] : (isset($_POST['departamento_id']) ? $_POST['departamento_id'] : null);
$grupo_id = isset($_GET['grupo_id']) ? $_GET['grupo_id'] : (isset($_POST['grupo_id']) ? $_POST['grupo_id'] : null);

// Obtener disponibilidad del departamento si está seleccionado
$disponibilidad = [];
if ($departamento_id && $grupo_id) {
    $stmt_disponibilidad = $conn->prepare("SELECT * FROM disponibilidad_departamento 
                                          WHERE departamento_id = ? AND grupo_id = ? 
                                          ORDER BY dia, hora_inicio");
    $stmt_disponibilidad->bind_param("ii", $departamento_id, $grupo_id);
    $stmt_disponibilidad->execute();
    $result = $stmt_disponibilidad->get_result();
    $disponibilidad_raw = $result->fetch_all(MYSQLI_ASSOC);
    
    // Organizar por día
    foreach ($disponibilidad_raw as $disp) {
        if (!isset($disponibilidad[$disp['dia']])) {
            $disponibilidad[$disp['dia']] = [];
        }
        $disponibilidad[$disp['dia']][] = $disp;
    }
}

// Días de la semana
$dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];

// Agregar disponibilidad
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar_disponibilidad'])) {
    $departamento_id = $_POST['departamento_id'];
    $grupo_id = $_POST['grupo_id'];
    $dia = $_POST['dia'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fin = $_POST['hora_fin'];
    
    // Validar que la hora de fin sea mayor que la hora de inicio
    if ($hora_inicio >= $hora_fin) {
        $mensaje = "La hora de fin debe ser mayor que la hora de inicio";
        $tipo_mensaje = "danger";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO disponibilidad_departamento (departamento_id, grupo_id, dia, hora_inicio, hora_fin) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisss", $departamento_id, $grupo_id, $dia, $hora_inicio, $hora_fin);
            $stmt->execute();
            
            $mensaje = "Horario agregado correctamente";
            $tipo_mensaje = "success";
            
            // Recargar la página para mostrar la nueva disponibilidad
            header("Location: departamentos_horarios.php?departamento_id=$departamento_id&grupo_id=$grupo_id&mensaje=$mensaje&tipo_mensaje=$tipo_mensaje");
            exit;
        } catch (Exception $e) {
            $mensaje = "Error al agregar horario: " . $e->getMessage();
            $tipo_mensaje = "danger";
        }
    }
}

// Eliminar disponibilidad
if (isset($_GET['eliminar']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    $departamento_id = $_GET['departamento_id'];
    $grupo_id = $_GET['grupo_id'];
    
    try {
        $stmt = $conn->prepare("DELETE FROM disponibilidad_departamento WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        $mensaje = "Horario eliminado correctamente";
        $tipo_mensaje = "success";
        
        // Recargar la página
        header("Location: departamentos_horarios.php?departamento_id=$departamento_id&grupo_id=$grupo_id&mensaje=$mensaje&tipo_mensaje=$tipo_mensaje");
        exit;
    } catch (Exception $e) {
        $mensaje = "Error al eliminar horario: " . $e->getMessage();
        $tipo_mensaje = "danger";
    }
}

// Actualizar disponibilidad
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_disponibilidad'])) {
    $id = $_POST['id'];
    $departamento_id = $_POST['departamento_id'];
    $grupo_id = $_POST['grupo_id'];
    $dia = $_POST['dia'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fin = $_POST['hora_fin'];
    
    // Validar que la hora de fin sea mayor que la hora de inicio
    if ($hora_inicio >= $hora_fin) {
        $mensaje = "La hora de fin debe ser mayor que la hora de inicio";
        $tipo_mensaje = "danger";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE disponibilidad_departamento SET dia = ?, hora_inicio = ?, hora_fin = ? WHERE id = ?");
            $stmt->bind_param("sssi", $dia, $hora_inicio, $hora_fin, $id);
            $stmt->execute();
            
            $mensaje = "Horario actualizado correctamente";
            $tipo_mensaje = "success";
            
            // Recargar la página
            header("Location: departamentos_horarios.php?departamento_id=$departamento_id&grupo_id=$grupo_id&mensaje=$mensaje&tipo_mensaje=$tipo_mensaje");
            exit;
        } catch (Exception $e) {
            $mensaje = "Error al actualizar horario: " . $e->getMessage();
            $tipo_mensaje = "danger";
        }
    }
}

// Obtener mensaje desde URL si existe
if (isset($_GET['mensaje']) && isset($_GET['tipo_mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $tipo_mensaje = $_GET['tipo_mensaje'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Horarios de Departamentos</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
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
                <h1>Gestión de Horarios de Departamentos</h1>
            </div>
            
            <div class="container">
                <p class="text-muted mb-4">Registre los horarios específicos por grupo para los departamentos de Inglés y Desarrollo Humano</p>
                
                <?php if (isset($mensaje)): ?>
                    <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                        <?php echo $mensaje; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-12 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h2>Seleccionar Departamento y Grupo</h2>
                            </div>
                            <div class="card-body">
                                <form method="GET" class="form-row">
                                    <div class="form-group col-md-5">
                                        <label for="departamento_id">Departamento:</label>
                                        <select class="form-control" id="departamento_id" name="departamento_id" required>
                                            <option value="">Seleccione un departamento</option>
                                            <?php foreach ($departamentos as $departamento): ?>
                                                <option value="<?php echo $departamento['id']; ?>" <?php echo ($departamento_id == $departamento['id']) ? 'selected' : ''; ?>>
                                                    <?php echo $departamento['nombre']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-5">
                                        <label for="grupo_id">Grupo:</label>
                                        <select class="form-control" id="grupo_id" name="grupo_id" required>
                                            <option value="">Seleccione un grupo</option>
                                            <?php foreach ($grupos as $grupo): ?>
                                                <option value="<?php echo $grupo['id']; ?>" <?php echo ($grupo_id == $grupo['id']) ? 'selected' : ''; ?>>
                                                    <?php echo $grupo['nombre'] . ' - ' . $grupo['carrera_nombre'] . ' (' . $grupo['semestre'] . '° semestre)'; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label>&nbsp;</label>
                                        <button type="submit" class="btn-primary btn-block">
                                            <i class="fas fa-search"></i> Ver Horarios
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($departamento_id && $grupo_id): ?>
                    <?php 
                    // Obtener información del departamento seleccionado
                    $departamento_info = null;
                    foreach ($departamentos as $departamento) {
                        if ($departamento['id'] == $departamento_id) {
                            $departamento_info = $departamento;
                            break;
                        }
                    }
                    
                    // Obtener información del grupo seleccionado
                    $grupo_info = null;
                    foreach ($grupos as $grupo) {
                        if ($grupo['id'] == $grupo_id) {
                            $grupo_info = $grupo;
                            break;
                        }
                    }
                    ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h2>Registrar Horario</h2>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-info">
                                        <strong>Departamento:</strong> <?php echo $departamento_info['nombre']; ?><br>
                                        <strong>Grupo:</strong> <?php echo $grupo_info['nombre'] . ' - ' . $grupo_info['carrera_nombre']; ?>
                                    </div>
                                    
                                    <hr>
                                    
                                    <form method="POST" id="formDisponibilidad">
                                        <input type="hidden" name="departamento_id" value="<?php echo $departamento_id; ?>">
                                        <input type="hidden" name="grupo_id" value="<?php echo $grupo_id; ?>">
                                        
                                        <div class="form-group">
                                            <label for="dia">Día de la semana:</label>
                                            <select class="form-control" id="dia" name="dia" required>
                                                <option value="">Seleccione un día</option>
                                                <?php foreach ($dias_semana as $dia): ?>
                                                    <option value="<?php echo $dia; ?>"><?php echo $dia; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div id="horariosContainer" style="display: none;">
                                            <div class="form-row">
                                                <div class="form-group col-md-6">
                                                    <label for="hora_inicio">Hora de inicio:</label>
                                                    <input type="time" class="form-control" id="hora_inicio" name="hora_inicio" required>
                                                </div>
                                                <div class="form-group col-md-6">
                                                    <label for="hora_fin">Hora de fin:</label>
                                                    <input type="time" class="form-control" id="hora_fin" name="hora_fin" required>
                                                </div>
                                            </div>
                                            
                                            <button type="submit" name="agregar_disponibilidad" class="btn-primary">
                                                <i class="fas fa-plus"></i> Agregar Horario
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h2>Horarios Registrados</h2>
                                </div>
                                <div class="card-body">
                                    <div class="accordion" id="disponibilidadAccordion">
                                        <?php foreach ($dias_semana as $index => $dia): ?>
                                            <div class="card mb-3">
                                                <div class="card-header" id="heading<?php echo $index; ?>">
                                                    <h2 class="mb-0">
                                                        <button class="btn btn-link btn-block text-left" type="button" 
                                                                data-toggle="collapse" 
                                                                data-target="#collapse<?php echo $index; ?>" 
                                                                aria-expanded="false" 
                                                                aria-controls="collapse<?php echo $index; ?>">
                                                            <?php echo $dia; ?>
                                                            <span class="badge badge-info ml-2">
                                                                <?php echo isset($disponibilidad[$dia]) ? count($disponibilidad[$dia]) : 0; ?> horarios
                                                            </span>
                                                        </button>
                                                    </h2>
                                                </div>
                                                
                                                <div id="collapse<?php echo $index; ?>" 
                                                     class="collapse" 
                                                     aria-labelledby="heading<?php echo $index; ?>" 
                                                     data-parent="#disponibilidadAccordion">
                                                    <div class="card-body">
                                                        <?php if (isset($disponibilidad[$dia]) && !empty($disponibilidad[$dia])): ?>
                                                            <div class="table-container">
                                                                <table>
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Hora Inicio</th>
                                                                            <th>Hora Fin</th>
                                                                            <th>Acciones</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php foreach ($disponibilidad[$dia] as $disp): ?>
                                                                            <tr>
                                                                                <td><?php echo date('H:i', strtotime($disp['hora_inicio'])); ?></td>
                                                                                <td><?php echo date('H:i', strtotime($disp['hora_fin'])); ?></td>
                                                                                <td>
                                                                                    <button type="button" class="btn-edit" 
                                                                                            data-toggle="modal" 
                                                                                            data-target="#editarDisponibilidadModal" 
                                                                                            data-id="<?php echo $disp['id']; ?>"
                                                                                            data-dia="<?php echo $disp['dia']; ?>"
                                                                                            data-inicio="<?php echo $disp['hora_inicio']; ?>"
                                                                                            data-fin="<?php echo $disp['hora_fin']; ?>">
                                                                                        <i class="fas fa-edit"></i>
                                                                                    </button>
                                                                                    <a href="?eliminar=1&id=<?php echo $disp['id']; ?>&departamento_id=<?php echo $departamento_id; ?>&grupo_id=<?php echo $grupo_id; ?>" 
                                                                                       class="btn-danger" 
                                                                                       onclick="return confirm('¿Está seguro de eliminar este horario?')">
                                                                                        <i class="fas fa-trash"></i>
                                                                                    </a>
                                                                                </td>
                                                                            </tr>
                                                                        <?php endforeach; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        <?php else: ?>
                                                            <p class="text-muted">No hay horarios registrados para este día.</p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Incluir el footer -->
        <?php include 'footer.php'; ?>
    </div>
    
    <!-- Modal para editar disponibilidad -->
    <div class="modal fade" id="editarDisponibilidadModal" tabindex="-1" role="dialog" aria-labelledby="editarDisponibilidadModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editarDisponibilidadModalLabel">Editar Horario</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <input type="hidden" name="id" id="editar_id">
                    <input type="hidden" name="departamento_id" value="<?php echo $departamento_id; ?>">
                    <input type="hidden" name="grupo_id" value="<?php echo $grupo_id; ?>">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="editar_dia">Día de la semana:</label>
                            <select class="form-control" id="editar_dia" name="dia" required>
                                <?php foreach ($dias_semana as $dia): ?>
                                    <option value="<?php echo $dia; ?>"><?php echo $dia; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="editar_hora_inicio">Hora de inicio:</label>
                                <input type="time" class="form-control" id="editar_hora_inicio" name="hora_inicio" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="editar_hora_fin">Hora de fin:</label>
                                <input type="time" class="form-control" id="editar_hora_fin" name="hora_fin" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" name="actualizar_disponibilidad" class="btn-primary">
                            <i class="fas fa-save"></i> Actualizar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Mostrar campos de horario al seleccionar un día
            $('#dia').change(function() {
                if ($(this).val()) {
                    $('#horariosContainer').show();
                } else {
                    $('#horariosContainer').hide();
                }
            });
            
            // Configurar modal de edición
            $('#editarDisponibilidadModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var id = button.data('id');
                var dia = button.data('dia');
                var horaInicio = button.data('inicio');
                var horaFin = button.data('fin');
                
                $('#editar_id').val(id);
                $('#editar_dia').val(dia);
                $('#editar_hora_inicio').val(horaInicio);
                $('#editar_hora_fin').val(horaFin);
            });
        });
    </script>
</body>
</html>