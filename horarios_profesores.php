<?php
include 'conexion.php';

// Obtener todos los profesores
$profesores = $conn->query("
    SELECT * 
    FROM profesores 
    WHERE estado = 1
    ORDER BY nombre
")->fetch_all(MYSQLI_ASSOC);

// Obtener horarios por profesor
$horarios = [];
if (isset($_GET['profesor_id'])) {
    $profesor_id = $_GET['profesor_id'];
    
    $horarios = $conn->query("
        SELECT h.*, 
        g.nombre as grupo,
        g.aula as aula,  -- Obtener el aula del grupo
        m.nombre as materia,
        c.nombre as carrera
        FROM horarios h
        JOIN grupos g ON h.grupo_id = g.id
        JOIN materias m ON h.materia_id = m.id
        JOIN carreras c ON m.carrera_id = c.id
        WHERE h.profesor_id = $profesor_id
        ORDER BY h.dia, h.hora_inicio
    ")->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Horarios por Profesor</title>
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
        /* Estilos mejorados para horarios de profesor */
        .horario-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            font-family: 'Montserrat', sans-serif;
        }
        
        .dia-header {
            background: linear-gradient(45deg, #3b82f6, #2563eb);
            color: white;
            padding: 15px 20px;
            font-weight: 600;
            font-size: 1.2rem;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
        }
        
        .dia-header i {
            margin-right: 10px;
            font-size: 18px;
        }
        
        .hora-slot {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.2s ease;
        }
        
        .hora-slot:hover {
            background-color: #f9fafb;
        }
        
        .hora-slot:last-child {
            border-bottom: none;
        }
        
        .hora-numero {
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
            font-size: 0.95rem;
        }
        
        .hora-numero i {
            margin-right: 8px;
            color: #64748b;
            font-size: 14px;
        }
        
        .class-card {
            background: #f1f5f9;
            border-left: 4px solid #3b82f6;
            border-radius: 8px;
            padding: 12px 15px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .class-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .class-card strong {
            color: #1e293b;
            font-size: 1.05rem;
            display: block;
            margin-bottom: 5px;
        }
        
        .class-info {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            color: #64748b;
            margin-top: 8px;
        }
        
        .class-info span {
            display: flex;
            align-items: center;
        }
        
        .class-info i {
            margin-right: 5px;
            font-size: 14px;
        }
        
        .profesor-selector {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .profesor-selector select {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            width: 100%;
            font-family: 'Montserrat', sans-serif;
            color: #1e293b;
            transition: all 0.2s ease;
        }
        
        .profesor-selector select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
            outline: none;
        }
        
        .btn-ver-horario {
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Montserrat', sans-serif;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 100%;
        }
        
        .btn-ver-horario:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.3);
        }
        
        .btn-ver-horario i {
            margin-right: 8px;
        }
        
        .empty-slot {
            padding: 15px;
            text-align: center;
            color: #94a3b8;
            font-style: italic;
            background: #f8fafc;
            border-radius: 6px;
        }
        
        .aula-badge {
            display: inline-block;
            background: #e0f2fe;
            color: #0369a1;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
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
                <h1>Horarios por Profesor</h1>
            </div>
            
            <div class="container">
                <div class="card mb-4">
                    <div class="card-header">
                        <h2>Seleccionar Profesor</h2>
                    </div>
                    <div class="card-body">
                        <form method="GET">
                            <div class="form-row">
                                <div class="col-md-8">
                                    <label for="profesor_id">Profesor:</label>
                                    <select name="profesor_id" id="profesor_id" class="form-control" required>
                                        <option value="">Seleccionar Profesor</option>
                                        <?php foreach($profesores as $p): ?>
                                            <option value="<?= $p['id'] ?>" <?= isset($_GET['profesor_id']) && $_GET['profesor_id'] == $p['id'] ? 'selected' : '' ?>>
                                                <?= $p['nombre'] . ' ' . $p['apellidos'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn-primary btn-block">
                                        <i class="fas fa-calendar-alt"></i> Mostrar Horario
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php if(!empty($horarios)): ?>
                    <?php
                    // Obtener el nombre del profesor seleccionado
                    $profesor_nombre = '';
                    foreach($profesores as $p) {
                        if($p['id'] == $_GET['profesor_id']) {
                            $profesor_nombre = $p['nombre'] . ' ' . $p['apellidos'];
                            break;
                        }
                    }
                    ?>
                    
                    <div class="mb-3">
                        <div class="alert alert-info">
                            <i class="fas fa-user-tie mr-2"></i> Mostrando horario de: <strong><?= $profesor_nombre ?></strong>
                        </div>
                    </div>
                    
                    <div class="horario-table">
                        <?php
                        $dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];
                        $horas = ['07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00'];
                        
                        foreach ($dias as $index => $dia): 
                            $clases_dia = array_filter($horarios, function($h) use ($dia) {
                                return $h['dia'] == $dia;
                            });
                            
                            // Iconos para los días de la semana
                            $iconos_dias = [
                                'Lunes' => 'calendar-day',
                                'Martes' => 'calendar-day',
                                'Miércoles' => 'calendar-day',
                                'Jueves' => 'calendar-day',
                                'Viernes' => 'calendar-day'
                            ];
                        ?>
                            <div class="dia-header">
                                <i class="fas fa-<?= $iconos_dias[$dia] ?>"></i> <?= $dia ?>
                            </div>
                            <div class="p-3">
                                <?php 
                                $tiene_clases = false;
                                foreach ($horas as $hora): 
                                    $clase = current(array_filter($clases_dia, function($c) use ($hora) {
                                        return $c['hora_inicio'] <= $hora && $c['hora_fin'] > $hora;
                                    }));
                                    
                                    if($clase) {
                                        $tiene_clases = true;
                                    }
                                ?>
                                    <div class="hora-slot">
                                        <div class="row">
                                            <div class="col-2 hora-numero">
                                                <i class="far fa-clock"></i> <?= $hora ?>
                                            </div>
                                            <div class="col-10">
                                                <?php if($clase): ?>
                                                    <div class="class-card">
                                                        <strong><?= $clase['materia'] ?></strong>
                                                        <div class="class-info">
                                                            <span><i class="fas fa-users"></i> Grupo: <?= $clase['grupo'] ?></span>
                                                            <span><i class="fas fa-door-open"></i> Aula: 
                                                                <?php if($clase['aula']): ?>
                                                                    <span class="aula-badge"><?= $clase['aula'] ?></span>
                                                                <?php else: ?>
                                                                    <span class="text-muted">Por asignar</span>
                                                                <?php endif; ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="empty-slot">
                                                        <i class="fas fa-coffee"></i> Tiempo libre
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php if(!$tiene_clases): ?>
                                    <div class="alert alert-light text-center my-3">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        No hay clases programadas para este día
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif(isset($_GET['profesor_id'])): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i>
                        No se encontraron horarios para este profesor
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