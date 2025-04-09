<?php
// Conexión a la base de datos
include 'conexion.php';

// Obtener todos los grupos
$stmt_grupos = $conn->prepare("SELECT g.*, c.nombre as carrera_nombre FROM grupos g 
                              LEFT JOIN carreras c ON g.carrera_id = c.id 
                              ORDER BY g.anio DESC, g.periodo, g.nombre");
$stmt_grupos->execute();
$result_grupos = $stmt_grupos->get_result();
$grupos = $result_grupos->fetch_all(MYSQLI_ASSOC);

// Obtener todas las materias
$stmt_materias = $conn->prepare("SELECT m.*, c.nombre as carrera_nombre FROM materias m 
                                LEFT JOIN carreras c ON m.carrera_id = c.id 
                                WHERE m.estado = 1 ORDER BY m.semestre, m.nombre");
$stmt_materias->execute();
$result_materias = $stmt_materias->get_result();
$materias = $result_materias->fetch_all(MYSQLI_ASSOC);

// Procesar formulario para asignar materias a grupos
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['asignar_materia'])) {
    $grupo_id = $_POST['grupo_id'];
    $materia_id = $_POST['materia_id'];
    
    try {
        // Verificar si la materia ya está asignada al grupo
        $stmt_check = $conn->prepare("SELECT id FROM grupo_materia WHERE grupo_id = ? AND materia_id = ?");
        $stmt_check->bind_param("ii", $grupo_id, $materia_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            $mensaje = "Esta materia ya está asignada a este grupo";
            $tipo_mensaje = "warning";
        } else {
            // Obtener semestre del grupo y de la materia para validar
            $stmt_grupo = $conn->prepare("SELECT semestre FROM grupos WHERE id = ?");
            $stmt_grupo->bind_param("i", $grupo_id);
            $stmt_grupo->execute();
            $grupo_semestre = $stmt_grupo->get_result()->fetch_assoc()['semestre'];
            
            $stmt_materia = $conn->prepare("SELECT semestre FROM materias WHERE id = ?");
            $stmt_materia->bind_param("i", $materia_id);
            $stmt_materia->execute();
            $materia_semestre = $stmt_materia->get_result()->fetch_assoc()['semestre'];
            
            if ($materia_semestre != $grupo_semestre) {
                $mensaje = "No se puede asignar: El semestre de la materia no coincide con el del grupo";
                $tipo_mensaje = "danger";
            } else {
                $stmt = $conn->prepare("INSERT INTO grupo_materia (grupo_id, materia_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $grupo_id, $materia_id);
                $stmt->execute();
                $mensaje = "Materia asignada correctamente al grupo";
                $tipo_mensaje = "success";
                
                // Registrar en el log
                $stmt_log = $conn->prepare("INSERT INTO log_horarios (grupo_id, materia_id, tipo, mensaje, fecha) 
                                          VALUES (?, ?, 'info', ?, NOW())");
                $log_msg = "Materia asignada al grupo";
                $stmt_log->bind_param("iis", $grupo_id, $materia_id, $log_msg);
                $stmt_log->execute();
            }
        }
    } catch (Exception $e) {
        $mensaje = "Error al asignar materia al grupo: " . $e->getMessage();
        $tipo_mensaje = "danger";
    }
}

// Eliminar asignación
if (isset($_GET['eliminar']) && isset($_GET['grupo_id']) && isset($_GET['materia_id'])) {
    $grupo_id = $_GET['grupo_id'];
    $materia_id = $_GET['materia_id'];
    
    try {
        // Verificar si la materia tiene horarios asignados
        $stmt_check = $conn->prepare("SELECT id FROM horarios WHERE grupo_id = ? AND materia_id = ?");
        $stmt_check->bind_param("ii", $grupo_id, $materia_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            $mensaje = "No se puede eliminar: Esta materia ya tiene horarios asignados";
            $tipo_mensaje = "danger";
        } else {
            $stmt = $conn->prepare("DELETE FROM grupo_materia WHERE grupo_id = ? AND materia_id = ?");
            $stmt->bind_param("ii", $grupo_id, $materia_id);
            $stmt->execute();
            $mensaje = "Asignación eliminada correctamente";
            $tipo_mensaje = "success";
            
            // Registrar en el log
            $stmt_log = $conn->prepare("INSERT INTO log_horarios (grupo_id, materia_id, tipo, mensaje, fecha) 
                                      VALUES (?, ?, 'info', ?, NOW())");
            $log_msg = "Materia eliminada del grupo";
            $stmt_log->bind_param("iis", $grupo_id, $materia_id, $log_msg);
            $stmt_log->execute();
        }
    } catch (Exception $e) {
        $mensaje = "Error al eliminar asignación: " . $e->getMessage();
        $tipo_mensaje = "danger";
    }
}

// Obtener las asignaciones actuales
$stmt_asignaciones = $conn->prepare("
    SELECT gm.id, gm.grupo_id, gm.materia_id, 
           g.nombre as grupo_nombre, g.semestre as grupo_semestre, g.turno as grupo_turno,
           g.periodo as grupo_periodo, g.anio as grupo_anio,
           m.nombre as materia_nombre, m.codigo as materia_codigo, m.semestre as materia_semestre,
           c.nombre as carrera_nombre
    FROM grupo_materia gm
    JOIN grupos g ON gm.grupo_id = g.id
    JOIN materias m ON gm.materia_id = m.id
    LEFT JOIN carreras c ON m.carrera_id = c.id
    ORDER BY g.anio DESC, g.periodo, g.nombre, m.semestre, m.nombre
");
$stmt_asignaciones->execute();
$result_asignaciones = $stmt_asignaciones->get_result();
$asignaciones = $result_asignaciones->fetch_all(MYSQLI_ASSOC);

// Organizar materias por grupo para mostrar en la interfaz
$materias_por_grupo = [];
foreach ($asignaciones as $asignacion) {
    if (!isset($materias_por_grupo[$asignacion['grupo_id']])) {
        $materias_por_grupo[$asignacion['grupo_id']] = [];
    }
    $materias_por_grupo[$asignacion['grupo_id']][] = [
        'id' => $asignacion['id'],
        'materia_id' => $asignacion['materia_id'],
        'nombre' => $asignacion['materia_nombre'],
        'codigo' => $asignacion['materia_codigo'],
        'semestre' => $asignacion['materia_semestre'],
        'carrera' => $asignacion['carrera_nombre']
    ];
}

// Filtrar materias por semestre para el select
function filtrar_materias_por_semestre($materias, $semestre) {
    return array_filter($materias, function($materia) use ($semestre) {
        return $materia['semestre'] == $semestre;
    });
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asignación de Materias a Grupos</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
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
                <h1>Asignación de Materias a Grupos</h1>
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
                
                <div class="row mb-4">
                    <div class="col-md-12">
                        <button type="button" class="btn-primary" data-toggle="modal" data-target="#asignarMateriaModal">
                            <i class="fas fa-plus"></i> Asignar Materia a Grupo
                        </button>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3>Grupos y sus Materias Asignadas</h3>
                    </div>
                    <div class="card-body">
                        <div class="accordion" id="gruposAccordion">
                            <?php foreach ($grupos as $index => $grupo): ?>
                                <div class="card mb-3">
                                    <div class="card-header" id="heading<?php echo $grupo['id']; ?>">
                                        <h2 class="mb-0">
                                            <button class="btn btn-link btn-block text-left" type="button" data-toggle="collapse" 
                                                    data-target="#collapse<?php echo $grupo['id']; ?>" 
                                                    aria-expanded="<?php echo ($index === 0) ? 'true' : 'false'; ?>" 
                                                    aria-controls="collapse<?php echo $grupo['id']; ?>">
                                                <?php echo $grupo['nombre'] . ' - ' . $grupo['carrera_nombre']; ?>
                                                <span class="badge badge-info ml-2">
                                                    Semestre <?php echo $grupo['semestre']; ?>
                                                </span>
                                                <span class="badge badge-secondary ml-2">
                                                    <?php echo isset($materias_por_grupo[$grupo['id']]) ? count($materias_por_grupo[$grupo['id']]) : 0; ?> materias
                                                </span>
                                                <span class="badge badge-light ml-2">
                                                    <?php echo ucfirst($grupo['turno']); ?>
                                                </span>
                                                <span class="badge badge-light ml-2">
                                                    <?php echo $grupo['periodo'] . ' ' . $grupo['anio']; ?>
                                                </span>
                                            </button>
                                        </h2>
                                    </div>
                                    
                                    <div id="collapse<?php echo $grupo['id']; ?>" 
                                         class="collapse <?php echo ($index === 0) ? 'show' : ''; ?>" 
                                         aria-labelledby="heading<?php echo $grupo['id']; ?>" 
                                         data-parent="#gruposAccordion">
                                        <div class="card-body">
                                            <?php if (isset($materias_por_grupo[$grupo['id']])): ?>
                                                <div class="table-container">
                                                    <table>
                                                        <thead>
                                                            <tr>
                                                                <th>Código</th>
                                                                <th>Nombre de Materia</th>
                                                                <th>Cuatrimestre</th>
                                                                <th>Carrera</th>
                                                                <th>Acciones</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($materias_por_grupo[$grupo['id']] as $materia): ?>
                                                                <tr>
                                                                    <td><?php echo $materia['codigo']; ?></td>
                                                                    <td><?php echo $materia['nombre']; ?></td>
                                                                    <td><?php echo $materia['semestre']; ?></td>
                                                                    <td><?php echo $materia['carrera']; ?></td>
                                                                    <td>
                                                                        <a href="?eliminar=1&grupo_id=<?php echo $grupo['id']; ?>&materia_id=<?php echo $materia['materia_id']; ?>" 
                                                                           class="btn-danger" 
                                                                           onclick="return confirm('¿Está seguro de eliminar esta asignación?')">
                                                                            <i class="fas fa-trash"></i>
                                                                        </a>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php else: ?>
                                                <p class="text-muted">Este grupo no tiene materias asignadas.</p>
                                            <?php endif; ?>
                                            <button type="button" class="btn-primary mt-3" 
                                                    data-toggle="modal" 
                                                    data-target="#asignarMateriaModal" 
                                                    data-grupo="<?php echo $grupo['id']; ?>"
                                                    data-semestre="<?php echo $grupo['semestre']; ?>">
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

        <!-- Incluir el footer -->
        <?php include 'footer.php'; ?>
    </div>
    
    <!-- Modal para asignar materia a grupo -->
    <div class="modal fade" id="asignarMateriaModal" tabindex="-1" role="dialog" aria-labelledby="asignarMateriaModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="asignarMateriaModalLabel">Asignar Materia a Grupo</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="grupo_id">Grupo:</label>
                            <select class="form-control" id="grupo_id" name="grupo_id" required>
                                <option value="">Seleccione un grupo</option>
                                <?php foreach ($grupos as $grupo): ?>
                                    <option value="<?php echo $grupo['id']; ?>" data-semestre="<?php echo $grupo['semestre']; ?>">
                                        <?php echo $grupo['nombre'] . ' - ' . $grupo['carrera_nombre'] . 
                                               ' (Semestre ' . $grupo['semestre'] . ', ' . 
                                               ucfirst($grupo['turno']) . ', ' . 
                                               $grupo['periodo'] . ' ' . $grupo['anio'] . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="materia_id">Materia:</label>
                            <select class="form-control" id="materia_id" name="materia_id" required>
                                <option value="">Primero seleccione un grupo</option>
                            </select>
                            <small id="materiaHelp" class="form-text text-muted">
                                Solo se muestran materias del mismo semestre que el grupo
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" name="asignar_materia" class="btn-primary">
                            <i class="fas fa-save"></i> Asignar
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
            // Configurar modal de asignación al seleccionar grupo
            $('#asignarMateriaModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var grupoId = button.data('grupo');
                var semestre = button.data('semestre');
                
                if (grupoId) {
                    $(this).find('#grupo_id').val(grupoId);
                    actualizarMateriasPorSemestre(semestre);
                }
            });
            
            // Actualizar materias cuando cambia el grupo seleccionado
            $('#grupo_id').change(function() {
                var semestre = $(this).find(':selected').data('semestre');
                actualizarMateriasPorSemestre(semestre);
            });
            
            function actualizarMateriasPorSemestre(semestre) {
                if (!semestre) {
                    $('#materia_id').html('<option value="">Primero seleccione un grupo</option>');
                    return;
                }
                
                // Filtrar materias del mismo semestre que el grupo
                $.ajax({
                    url: 'obtener_materias_grupos.php',
                    type: 'GET',
                    data: { semestre: semestre },
                    success: function(data) {
                        var options = '<option value="">Seleccione una materia</option>';
                        $.each(data, function(index, materia) {
                            options += '<option value="' + materia.id + '">' + 
                                       materia.nombre + ' (' + materia.codigo + ') - ' + 
                                       materia.carrera_nombre + '</option>';
                        });
                        $('#materia_id').html(options);
                    },
                    error: function() {
                        $('#materia_id').html('<option value="">Error al cargar materias</option>');
                    }
                });
            }
        });
    </script>
</body>
</html>