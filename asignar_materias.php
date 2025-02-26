<?php
// Conexión a la base de datos
include 'conexion.php';

// Obtener todos los profesores
$stmt_profesores = $conn->prepare("SELECT * FROM profesores WHERE estado = 1 ORDER BY nombre");
$stmt_profesores->execute();
$result_profesores = $stmt_profesores->get_result();
$profesores = $result_profesores->fetch_all(MYSQLI_ASSOC);

// Obtener todas las materias
$stmt_materias = $conn->prepare("SELECT m.*, c.nombre as carrera_nombre FROM materias m 
                                LEFT JOIN carreras c ON m.carrera_id = c.id 
                                WHERE m.estado = 1 ORDER BY m.nombre");
$stmt_materias->execute();
$result_materias = $stmt_materias->get_result();
$materias = $result_materias->fetch_all(MYSQLI_ASSOC);

// Procesar formulario para asignar materias
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['asignar_materia'])) {
    $profesor_id = $_POST['profesor_id'];
    $materia_id = $_POST['materia_id'];
    
    try {
        $stmt = $conn->prepare("INSERT INTO profesor_materia (profesor_id, materia_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $profesor_id, $materia_id);
        $stmt->execute();
        $mensaje = "Materia asignada correctamente";
        $tipo_mensaje = "success";
    } catch (Exception $e) {
        if ($conn->errno == 1062) { // Error de duplicado en MySQL
            $mensaje = "Esta materia ya está asignada a este profesor";
        } else {
            $mensaje = "Error al asignar materia: " . $e->getMessage();
        }
        $tipo_mensaje = "danger";
    }
}

// Eliminar asignación
if (isset($_GET['eliminar']) && isset($_GET['profesor_id']) && isset($_GET['materia_id'])) {
    $profesor_id = $_GET['profesor_id'];
    $materia_id = $_GET['materia_id'];
    
    try {
        $stmt = $conn->prepare("DELETE FROM profesor_materia WHERE profesor_id = ? AND materia_id = ?");
        $stmt->bind_param("ii", $profesor_id, $materia_id);
        $stmt->execute();
        $mensaje = "Asignación eliminada correctamente";
        $tipo_mensaje = "success";
    } catch (Exception $e) {
        $mensaje = "Error al eliminar asignación: " . $e->getMessage();
        $tipo_mensaje = "danger";
    }
}

// Actualizar asignación
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_asignacion'])) {
    $id = $_POST['id'];
    $profesor_id = $_POST['profesor_id'];
    $materia_id = $_POST['materia_id'];
    
    try {
        $stmt = $conn->prepare("UPDATE profesor_materia SET profesor_id = ?, materia_id = ? WHERE id = ?");
        $stmt->bind_param("iii", $profesor_id, $materia_id, $id);
        $stmt->execute();
        $mensaje = "Asignación actualizada correctamente";
        $tipo_mensaje = "success";
    } catch (Exception $e) {
        if ($conn->errno == 1062) { // Error de duplicado en MySQL
            $mensaje = "Esta materia ya está asignada a este profesor";
        } else {
            $mensaje = "Error al actualizar asignación: " . $e->getMessage();
        }
        $tipo_mensaje = "danger";
    }
}

// Obtener las asignaciones actuales
$stmt_asignaciones = $conn->prepare("
    SELECT pm.id, pm.profesor_id, pm.materia_id, 
           p.nombre as profesor_nombre, p.apellidos as profesor_apellidos,
           m.nombre as materia_nombre, m.codigo as materia_codigo,
           c.nombre as carrera_nombre
    FROM profesor_materia pm
    JOIN profesores p ON pm.profesor_id = p.id
    JOIN materias m ON pm.materia_id = m.id
    LEFT JOIN carreras c ON m.carrera_id = c.id
    ORDER BY p.nombre, m.nombre
");
$stmt_asignaciones->execute();
$result_asignaciones = $stmt_asignaciones->get_result();
$asignaciones = $result_asignaciones->fetch_all(MYSQLI_ASSOC);

// Obtener materias asignadas por profesor (para mostrar en la interfaz)
$materias_por_profesor = [];
foreach ($asignaciones as $asignacion) {
    if (!isset($materias_por_profesor[$asignacion['profesor_id']])) {
        $materias_por_profesor[$asignacion['profesor_id']] = [];
    }
    $materias_por_profesor[$asignacion['profesor_id']][] = [
        'id' => $asignacion['id'],
        'materia_id' => $asignacion['materia_id'],
        'nombre' => $asignacion['materia_nombre'],
        'codigo' => $asignacion['materia_codigo'],
        'carrera' => $asignacion['carrera_nombre']
    ];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asignación de Materias a Profesores</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container mt-4">

        <div class="nav-menu">
            <a href="index.php">Inicio</a>
            <a href="crud_profesores.php">Profesores</a>
            <a href="crud_materias.php">Materias</a>
            <a href="crud_carreras.php">Carreras</a>
            <a href="asignar_materias.php">Asignar Materias</a>
            <a href="disponibilidad_profesores.php">Disponibilidad Profesores</a>
            <a href="disponibilidad_departamentos.php">Disponibilidad Departamentos</a>
            <a href="generar_horarios.php">Generar Horarios</a>
            <a href="revisar_horarios.php">Revisar Horarios</a>
            <a href="horarios_profesores.php">Horarios por Profesor</a>
        </div>
        <h2>Asignación de Materias a Profesores</h2>
        
        <?php if (isset($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensaje; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        
        <div class="row mb-4">
            <div class="col-md-12">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#asignarMateriaModal">
                    <i class="fas fa-plus"></i> Asignar Materia a Profesor
                </button>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Profesores y sus Materias Asignadas</h4>
                    </div>
                    <div class="card-body">
                        <div class="accordion" id="profesoresAccordion">
                            <?php foreach ($profesores as $index => $profesor): ?>
                                <div class="card">
                                    <div class="card-header" id="heading<?php echo $profesor['id']; ?>">
                                        <h2 class="mb-0">
                                            <button class="btn btn-link btn-block text-left" type="button" data-toggle="collapse" 
                                                    data-target="#collapse<?php echo $profesor['id']; ?>" 
                                                    aria-expanded="<?php echo ($index === 0) ? 'true' : 'false'; ?>" 
                                                    aria-controls="collapse<?php echo $profesor['id']; ?>">
                                                <?php echo $profesor['nombre'] . ' ' . $profesor['apellidos']; ?>
                                                <span class="badge badge-info ml-2">
                                                    <?php echo isset($materias_por_profesor[$profesor['id']]) ? count($materias_por_profesor[$profesor['id']]) : 0; ?> materias
                                                </span>
                                            </button>
                                        </h2>
                                    </div>
                                    
                                    <div id="collapse<?php echo $profesor['id']; ?>" 
                                         class="collapse <?php echo ($index === 0) ? 'show' : ''; ?>" 
                                         aria-labelledby="heading<?php echo $profesor['id']; ?>" 
                                         data-parent="#profesoresAccordion">
                                        <div class="card-body">
                                            <?php if (isset($materias_por_profesor[$profesor['id']])): ?>
                                                <table class="table table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th>Código</th>
                                                            <th>Nombre de Materia</th>
                                                            <th>Carrera</th>
                                                            <th>Acciones</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($materias_por_profesor[$profesor['id']] as $materia): ?>
                                                            <tr>
                                                                <td><?php echo $materia['codigo']; ?></td>
                                                                <td><?php echo $materia['nombre']; ?></td>
                                                                <td><?php echo $materia['carrera']; ?></td>
                                                                <td>
                                                                    <button type="button" class="btn btn-sm btn-warning" 
                                                                            data-toggle="modal" 
                                                                            data-target="#editarAsignacionModal" 
                                                                            data-id="<?php echo $materia['id']; ?>"
                                                                            data-profesor="<?php echo $profesor['id']; ?>"
                                                                            data-materia="<?php echo $materia['materia_id']; ?>">
                                                                        <i class="fas fa-edit"></i>
                                                                    </button>
                                                                    <a href="?eliminar=1&profesor_id=<?php echo $profesor['id']; ?>&materia_id=<?php echo $materia['materia_id']; ?>" 
                                                                       class="btn btn-sm btn-danger" 
                                                                       onclick="return confirm('¿Está seguro de eliminar esta asignación?')">
                                                                        <i class="fas fa-trash"></i>
                                                                    </a>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            <?php else: ?>
                                                <p class="text-muted">Este profesor no tiene materias asignadas.</p>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-primary btn-sm" 
                                                    data-toggle="modal" 
                                                    data-target="#asignarMateriaModal" 
                                                    data-profesor="<?php echo $profesor['id']; ?>">
                                                <i class="fas fa-plus"></i> Agregar Materia
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para asignar materia -->
    <div class="modal fade" id="asignarMateriaModal" tabindex="-1" role="dialog" aria-labelledby="asignarMateriaModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="asignarMateriaModalLabel">Asignar Materia a Profesor</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="profesor_id">Profesor:</label>
                            <select class="form-control" id="profesor_id" name="profesor_id" required>
                                <option value="">Seleccione un profesor</option>
                                <?php foreach ($profesores as $profesor): ?>
                                    <option value="<?php echo $profesor['id']; ?>">
                                        <?php echo $profesor['nombre'] . ' ' . $profesor['apellidos']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="materia_id">Materia:</label>
                            <select class="form-control" id="materia_id" name="materia_id" required>
                                <option value="">Seleccione una materia</option>
                                <?php foreach ($materias as $materia): ?>
                                    <option value="<?php echo $materia['id']; ?>">
                                        <?php echo $materia['nombre'] . ' (' . $materia['codigo'] . ') - ' . 
                                                 $materia['carrera_nombre']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" name="asignar_materia" class="btn btn-primary">Asignar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal para editar asignación -->
    <div class="modal fade" id="editarAsignacionModal" tabindex="-1" role="dialog" aria-labelledby="editarAsignacionModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editarAsignacionModalLabel">Editar Asignación</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <input type="hidden" name="id" id="editar_id">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="editar_profesor_id">Profesor:</label>
                            <select class="form-control" id="editar_profesor_id" name="profesor_id" required>
                                <?php foreach ($profesores as $profesor): ?>
                                    <option value="<?php echo $profesor['id']; ?>">
                                        <?php echo $profesor['nombre'] . ' ' . $profesor['apellidos']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="editar_materia_id">Materia:</label>
                            <select class="form-control" id="editar_materia_id" name="materia_id" required>
                                <?php foreach ($materias as $materia): ?>
                                    <option value="<?php echo $materia['id']; ?>">
                                        <?php echo $materia['nombre'] . ' (' . $materia['codigo'] . ') - ' . 
                                                 $materia['carrera_nombre']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" name="actualizar_asignacion" class="btn btn-primary">Actualizar</button>
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
            // Configurar modal de asignación al seleccionar profesor
            $('#asignarMateriaModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var profesorId = button.data('profesor');
                if (profesorId) {
                    $(this).find('#profesor_id').val(profesorId);
                }
            });
            
            // Configurar modal de edición
            $('#editarAsignacionModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var id = button.data('id');
                var profesorId = button.data('profesor');
                var materiaId = button.data('materia');
                
                $('#editar_id').val(id);
                $('#editar_profesor_id').val(profesorId);
                $('#editar_materia_id').val(materiaId);
            });
        });
    </script>
</body>
</html>