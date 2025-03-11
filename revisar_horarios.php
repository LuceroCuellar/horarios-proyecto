<?php
include 'conexion.php';

// Cambiar estado de horario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cambiar_estado'])) {
    $id = $_POST['id'];
    $estado = $_POST['estado'];
    
    $conn->query("UPDATE horarios SET estado = '$estado' WHERE id = $id");
    $mensaje = "Estado actualizado correctamente";
    $tipo = "success";
}

// Eliminar horario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar'])) {
    $id = $_POST['id'];
    
    $conn->query("DELETE FROM horarios WHERE id = $id");
    $mensaje = "Horario eliminado correctamente";
    $tipo = "success";
}

// Obtener carreras y grupos para los filtros
$carreras = $conn->query("SELECT * FROM carreras WHERE estado = 1")->fetch_all(MYSQLI_ASSOC);
$grupos = $conn->query("SELECT * FROM grupos")->fetch_all(MYSQLI_ASSOC);

// Filtrar horarios por carrera y grupo
$carrera_id = isset($_GET['carrera_id']) ? intval($_GET['carrera_id']) : null;
$grupo_id = isset($_GET['grupo_id']) ? intval($_GET['grupo_id']) : null;

$where = [];
if ($carrera_id) {
    $where[] = "m.carrera_id = $carrera_id";
}
if ($grupo_id) {
    $where[] = "h.grupo_id = $grupo_id";
}
$where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Obtener horarios generados
$horarios = $conn->query("
    SELECT h.*, 
    g.nombre as grupo,
    g.aula as aula,  -- Obtener el aula del grupo
    m.nombre as materia,
    p.nombre as profesor,
    c.nombre as carrera
    FROM horarios h
    JOIN grupos g ON h.grupo_id = g.id
    JOIN materias m ON h.materia_id = m.id
    LEFT JOIN profesores p ON h.profesor_id = p.id  -- Usar LEFT JOIN para incluir horarios sin profesor
    JOIN carreras c ON m.carrera_id = c.id
    $where_clause
    ORDER BY FIELD(h.dia, 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'), h.hora_inicio
")->fetch_all(MYSQLI_ASSOC);

// Organizar horarios por día y hora
$horarios_organizados = [];
foreach ($horarios as $h) {
    $dia = $h['dia'];
    $hora_inicio = $h['hora_inicio'];
    $hora_fin = $h['hora_fin'];
    
    if (!isset($horarios_organizados[$dia])) {
        $horarios_organizados[$dia] = [];
    }
    
    $horarios_organizados[$dia][] = $h;
}

// Definir el orden de los días de la semana
$dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Revisar Horarios</title>
    <?php include 'header.php'; ?>
    <style>
        .horario-table {
            width: 100%;
            border-collapse: collapse;
        }
        .horario-table th, .horario-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        .horario-table th {
            background-color: #f2f2f2;
        }
        .badge {
            padding: 5px 10px;
            border-radius: 5px;
        }
        .badge-success {
            background-color: #28a745;
            color: white;
        }
        .badge-warning {
            background-color: #ffc107;
            color: black;
        }
        .badge-danger {
            background-color: #dc3545;
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>
    
    <div class="container">
        <h2>Revisar Horarios Generados</h2>
        
        <?php if(isset($mensaje)): ?>
            <div class="alert alert-<?= $tipo ?>"><?= $mensaje ?></div>
        <?php endif; ?>
        
        <!-- Filtros por carrera y grupo -->
        <form method="GET" class="mb-4">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="carrera_id">Carrera:</label>
                    <select name="carrera_id" id="carrera_id" class="form-control">
                        <option value="">Todas las carreras</option>
                        <?php foreach($carreras as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($carrera_id == $c['id']) ? 'selected' : '' ?>>
                                <?= $c['nombre'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label for="grupo_id">Grupo:</label>
                    <select name="grupo_id" id="grupo_id" class="form-control">
                        <option value="">Todos los grupos</option>
                        <?php foreach($grupos as $g): ?>
                            <option value="<?= $g['id'] ?>" <?= ($grupo_id == $g['id']) ? 'selected' : '' ?>>
                                <?= $g['nombre'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <button type="submit" class="btn btn-primary mt-4">Filtrar</button>
                </div>
            </div>
        </form>
        
        <!-- Mostrar horarios en formato de tabla -->
        <?php if ($horarios_organizados): ?>
            <?php foreach ($dias_semana as $dia): ?>
                <?php if (isset($horarios_organizados[$dia])): ?>
                    <h3><?= ucfirst($dia) ?></h3>
                    <table class="horario-table">
                        <thead>
                            <tr>
                                <th>Hora</th>
                                <th>Materia</th>
                                <th>Profesor</th>
                                <th>Aula</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($horarios_organizados[$dia] as $h): ?>
                                <tr>
                                    <td><?= date('H:i', strtotime($h['hora_inicio'])) ?> - <?= date('H:i', strtotime($h['hora_fin'])) ?></td>
                                    <td><?= $h['materia'] ?></td>
                                    <td><?= $h['profesor'] ?? 'Departamento' ?></td> <!-- Mostrar "Departamento" si no hay profesor -->
                                    <td><?= $h['aula'] ?></td>
                                    <td>
                                        <form method="POST" class="form-inline">
                                            <input type="hidden" name="id" value="<?= $h['id'] ?>">
                                            <select name="estado" class="form-control form-control-sm mr-2">
                                                <option value="preliminar">Preliminar</option>
                                                <option value="aprobado">Aprobar</option>
                                                <option value="rechazado">Rechazar</option>
                                            </select>
                                            <button type="submit" name="cambiar_estado" class="btn btn-sm btn-primary mr-2">
                                                <i class="fas fa-save"></i>
                                            </button>
                                            <button type="submit" name="eliminar" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro de eliminar este horario?');">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">No hay horarios generados.</div>
        <?php endif; ?>
    </div>
</body>
</html>