<?php
// Conexión a la base de datos
include 'conexion.php';

// Verificar conexión
if (!$conn) {
    die("Error de conexión: " . mysqli_connect_error());
}

// Obtener todos los profesores activos
$query_profesores = "SELECT * FROM profesores WHERE estado = 1 ORDER BY nombre";
$result_profesores = $conn->query($query_profesores);

$profesores = [];
if ($result_profesores && $result_profesores->num_rows > 0) {
    while ($row = $result_profesores->fetch_assoc()) {
        $profesores[] = $row;
    }
}

// Obtener profesor seleccionado si existe
$profesor_id = isset($_GET['id']) ? $_GET['id'] : (isset($_GET['profesor_id']) ? $_GET['profesor_id'] : (isset($_POST['profesor_id']) ? $_POST['profesor_id'] : null));

// Obtener disponibilidad del profesor si está seleccionado
$disponibilidad = [];
if ($profesor_id) {
    $stmt_disponibilidad = $conn->prepare("SELECT * FROM disponibilidad_profesor WHERE profesor_id = ? ORDER BY dia, hora_inicio");
    $stmt_disponibilidad->bind_param("i", $profesor_id);
    $stmt_disponibilidad->execute();
    $result_disponibilidad = $stmt_disponibilidad->get_result();

    if ($result_disponibilidad) {
        while ($row = $result_disponibilidad->fetch_assoc()) {
            $disponibilidad[$row['dia']][] = $row;
        }
    }
    $stmt_disponibilidad->close();
}

// Días de la semana
$dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];

// Agregar disponibilidad
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar_disponibilidad'])) {
    $profesor_id = $_POST['profesor_id'];
    $dia = $_POST['dia'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fin = $_POST['hora_fin'];
    
    // Validar que la hora de fin sea mayor que la hora de inicio
    if ($hora_inicio >= $hora_fin) {
        $mensaje = "La hora de fin debe ser mayor que la hora de inicio";
        $tipo_mensaje = "danger";
    } else {
        $stmt = $conn->prepare("INSERT INTO disponibilidad_profesor (profesor_id, dia, hora_inicio, hora_fin) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $profesor_id, $dia, $hora_inicio, $hora_fin);

        if ($stmt->execute()) {
            $mensaje = "Disponibilidad agregada correctamente";
            $tipo_mensaje = "success";
            
            // Recargar la página para mostrar la nueva disponibilidad
            header("Location: disponibilidad_profesor.php?profesor_id=$profesor_id&mensaje=$mensaje&tipo_mensaje=$tipo_mensaje");
            exit;
        } else {
            $mensaje = "Error al agregar disponibilidad: " . $stmt->error;
            $tipo_mensaje = "danger";
        }
        $stmt->close();
    }
}

// Eliminar disponibilidad
if (isset($_GET['eliminar']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    $profesor_id = $_GET['profesor_id'];
    
    $stmt = $conn->prepare("DELETE FROM disponibilidad_profesor WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $mensaje = "Disponibilidad eliminada correctamente";
        $tipo_mensaje = "success";
        
        // Recargar la página
        header("Location: disponibilidad_profesor.php?profesor_id=$profesor_id&mensaje=$mensaje&tipo_mensaje=$tipo_mensaje");
        exit;
    } else {
        $mensaje = "Error al eliminar disponibilidad: " . $stmt->error;
        $tipo_mensaje = "danger";
    }
    $stmt->close();
}

// Actualizar disponibilidad
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_disponibilidad'])) {
    $id = $_POST['id'];
    $profesor_id = $_POST['profesor_id'];
    $dia = $_POST['dia'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fin = $_POST['hora_fin'];
    
    // Validar que la hora de fin sea mayor que la hora de inicio
    if ($hora_inicio >= $hora_fin) {
        $mensaje = "La hora de fin debe ser mayor que la hora de inicio";
        $tipo_mensaje = "danger";
    } else {
        $stmt = $conn->prepare("UPDATE disponibilidad_profesor SET dia = ?, hora_inicio = ?, hora_fin = ? WHERE id = ?");
        $stmt->bind_param("sssi", $dia, $hora_inicio, $hora_fin, $id);

        if ($stmt->execute()) {
            $mensaje = "Disponibilidad actualizada correctamente";
            $tipo_mensaje = "success";
            
            // Recargar la página
            header("Location: disponibilidad_profesor.php?profesor_id=$profesor_id&mensaje=$mensaje&tipo_mensaje=$tipo_mensaje");
            exit;
        } else {
            $mensaje = "Error al actualizar disponibilidad: " . $stmt->error;
            $tipo_mensaje = "danger";
        }
        $stmt->close();
    }
}

// Obtener mensaje desde URL si existe
$mensaje = isset($_GET['mensaje']) ? $_GET['mensaje'] : null;
$tipo_mensaje = isset($_GET['tipo_mensaje']) ? $_GET['tipo_mensaje'] : null;

// Obtener información del profesor seleccionado
$profesor_info = null;
if ($profesor_id) {
    foreach ($profesores as $profesor) {
        if ($profesor['id'] == $profesor_id) {
            $profesor_info = $profesor;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Disponibilidad de Profesores</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                <h1>Gestión de Disponibilidad de Profesores</h1>
            </div>
            
            <div class="container">
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
                                <h2>Seleccionar Profesor</h2>
                            </div>
                            <div class="card-body">
                                <form method="GET" class="form-inline">
                                    <div class="form-group mr-2">
                                        <select class="form-control" id="profesor_id" name="profesor_id" required>
                                            <option value="">Seleccione un profesor</option>
                                            <?php foreach ($profesores as $profesor): ?>
                                                <option value="<?php echo $profesor['id']; ?>" <?php echo ($profesor_id == $profesor['id']) ? 'selected' : ''; ?>>
                                                    <?php echo $profesor['nombre'] . ' ' . $profesor['apellidos']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn-primary">
                                        <i class="fas fa-search"></i> Ver Disponibilidad
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($profesor_id && $profesor_info): ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h2>Registrar Disponibilidad</h2>
                                </div>
                                <div class="card-body">
                                    <h5>Profesor: <?php echo $profesor_info['nombre'] . ' ' . $profesor_info['apellidos']; ?></h5>
                                    <p>Horas disponibles: <?php echo $profesor_info['horas_disponibles']; ?> horas</p>
                                    
                                    <hr>
                                    
                                    <form method="POST" id="formDisponibilidad">
                                        <input type="hidden" name="profesor_id" value="<?php echo $profesor_id; ?>">
                                        
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
                                                <i class="fas fa-plus"></i> Agregar Disponibilidad
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h2>Disponibilidad Registrada</h2>
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
                                                                                    <a href="?eliminar=1&id=<?php echo $disp['id']; ?>&profesor_id=<?php echo $profesor_id; ?>" 
                                                                                       class="btn-danger" 
                                                                                       onclick="return confirm('¿Está seguro de eliminar esta disponibilidad?')">
                                                                                        <i class="fas fa-trash"></i>
                                                                                    </a>
                                                                                </td>
                                                                            </tr>
                                                                        <?php endforeach; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        <?php else: ?>
                                                            <p class="text-muted">No hay disponibilidad registrada para este día.</p>
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
                    <h5 class="modal-title" id="editarDisponibilidadModalLabel">Editar Disponibilidad</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <input type="hidden" name="id" id="editar_id">
                    <input type="hidden" name="profesor_id" value="<?php echo $profesor_id; ?>">
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