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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Fuente Montserrat para mejorar la tipografía -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include 'header.php'; ?>
    <style>
        /* Estilos mejorados para la tabla de horarios */
        .horario-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            font-family: 'Montserrat', sans-serif;
        }
        
        .horario-table thead {
            background: linear-gradient(45deg, #3b82f6, #2563eb);
        }
        
        .horario-table th {
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            padding: 14px 10px;
            text-align: center;
            border: none;
        }
        
        .horario-table td {
            padding: 12px 15px;
            text-align: center;
            border-bottom: 1px solid #e5e7eb;
            color: #4b5563;
            font-size: 0.95rem;
        }
        
        .horario-table tbody tr {
            transition: all 0.2s ease;
            background-color: white;
        }
        
        .horario-table tbody tr:hover {
            background-color: #f1f5f9;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        
        .horario-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* Estilos para los badges */
        .badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.8rem;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-success {
            background-color: #10b981;
            color: white;
        }
        
        .badge-warning {
            background-color: #f59e0b;
            color: white;
        }
        
        .badge-danger {
            background-color: #ef4444;
            color: white;
        }
        
        /* Estilos para el título del día */
        .dia-header {
            position: relative;
            margin: 30px 0 15px;
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
        }
        
        .dia-header::after {
            content: '';
            flex-grow: 1;
            height: 1px;
            background: #e5e7eb;
            margin-left: 15px;
        }
        
        /* Estilos para los botones de acción */
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0 3px;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        
        .save-btn {
            background-color: #3b82f6;
            color: white;
        }
        
        .save-btn:hover {
            background-color: #2563eb;
        }
        
        .delete-btn {
            background-color: #ef4444;
            color: white;
        }
        
        .delete-btn:hover {
            background-color: #dc2626;
        }
        
        /* Estilo para el select de estado */
        .estado-select {
            padding: 6px 8px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background-color: white;
            font-size: 0.85rem;
            color: #4b5563;
            margin-right: 8px;
            transition: all 0.2s;
        }
        
        .estado-select:hover, .estado-select:focus {
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
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
                <h1>Revisar Horarios Generados</h1>
            </div>
            
            <div class="container">
                <?php if(isset($mensaje)): ?>
                    <div class="alert alert-<?= $tipo ?>"><?= $mensaje ?></div>
                <?php endif; ?>
                
                <!-- Filtros por carrera y grupo -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h2>Filtros de Búsqueda</h2>
                    </div>
                    <div class="card-body">
                        <form method="GET">
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
                                    <button type="submit" class="btn-primary mt-4">
                                        <i class="fas fa-filter"></i> Filtrar
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Mostrar horarios en formato de tabla -->
                <?php if ($horarios_organizados): ?>
                    <div class="card">
                        <div class="card-body">
                            <?php foreach ($dias_semana as $dia): ?>
                                <?php if (isset($horarios_organizados[$dia])): ?>
                                    <h3 class="dia-header"><i class="fas fa-calendar-day mr-2"></i> <?= ucfirst($dia) ?></h3>
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
                                                    <td>
                                                        <strong><?= date('H:i', strtotime($h['hora_inicio'])) ?></strong> - 
                                                        <strong><?= date('H:i', strtotime($h['hora_fin'])) ?></strong>
                                                    </td>
                                                    <td><?= $h['materia'] ?></td>
                                                    <td><?= $h['profesor'] ?? '<span class="badge badge-info">Departamento</span>' ?></td>
                                                    <td><?= $h['aula'] ?></td>
                                                    <td>
                                                        <form method="POST" class="form-inline">
                                                            <input type="hidden" name="id" value="<?= $h['id'] ?>">
                                                            <select name="estado" class="estado-select">
                                                                <option value="preliminar" <?= ($h['estado'] == 'preliminar') ? 'selected' : '' ?>>Preliminar</option>
                                                                <option value="aprobado" <?= ($h['estado'] == 'aprobado') ? 'selected' : '' ?>>Aprobar</option>
                                                                <option value="rechazado" <?= ($h['estado'] == 'rechazado') ? 'selected' : '' ?>>Rechazar</option>
                                                            </select>
                                                            <button type="submit" name="cambiar_estado" class="action-btn save-btn">
                                                                <i class="fas fa-save"></i>
                                                            </button>
                                                            <button type="submit" name="eliminar" class="action-btn delete-btn" onclick="return confirm('¿Estás seguro de eliminar este horario?');">
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
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i> No hay horarios generados.
                    </div>
                <?php endif; ?>
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