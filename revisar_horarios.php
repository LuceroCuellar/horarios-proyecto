<?php
include 'conexion.php';

// Obtener el grupo para visualizar su horario
$grupo_id = isset($_GET['grupo_id']) ? intval($_GET['grupo_id']) : 0;

// Eliminar horario si se solicita
if (isset($_POST['eliminar_horario']) && isset($_POST['grupo_id'])) {
    $grupo_id_eliminar = intval($_POST['grupo_id']);
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    try {
        // Obtener todos los horarios para restaurar las horas a los profesores
        $horarios_eliminar = $conn->query("
            SELECT h.*, TIMESTAMPDIFF(MINUTE, h.hora_inicio, h.hora_fin) / 60 as duracion_horas
            FROM horarios h
            WHERE h.grupo_id = $grupo_id_eliminar AND h.profesor_id IS NOT NULL
        ")->fetch_all(MYSQLI_ASSOC);
        
        // Restaurar horas a los profesores
        foreach ($horarios_eliminar as $horario) {
            if ($horario['profesor_id']) {
                $profesor_id = $horario['profesor_id'];
                $duracion_horas = $horario['duracion_horas'];
                
                $stmt_update = $conn->prepare("
                    UPDATE profesores
                    SET horas_disponibles = horas_disponibles + ?
                    WHERE id = ?
                ");
                $stmt_update->bind_param("di", $duracion_horas, $profesor_id);
                $stmt_update->execute();
            }
        }
        
        // Eliminar todos los horarios del grupo
        $conn->query("DELETE FROM horarios WHERE grupo_id = $grupo_id_eliminar");
        
        $conn->commit();
        $mensaje = "Horario eliminado correctamente";
        $tipo = "success";
    } catch (Exception $e) {
        $conn->rollback();
        $mensaje = "Error al eliminar horario: " . $e->getMessage();
        $tipo = "danger";
    }
}

// Obtener información del grupo
$grupo_info = null;
if ($grupo_id) {
    $stmt = $conn->prepare("
        SELECT g.*, c.nombre as carrera_nombre
        FROM grupos g
        JOIN carreras c ON g.carrera_id = c.id
        WHERE g.id = ?
    ");
    
    $stmt->bind_param("i", $grupo_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $grupo_info = $result->fetch_assoc();
}

// Obtener horarios del grupo
$horarios = [];
$detalles_materias = []; // Para la leyenda
if ($grupo_id) {
    $stmt = $conn->prepare("
        SELECT h.*, 
               m.nombre as materia_nombre,
               m.codigo as materia_codigo,
               p.nombre as profesor_nombre,
               p.apellidos as profesor_apellidos
        FROM horarios h
        JOIN materias m ON h.materia_id = m.id
        LEFT JOIN profesores p ON h.profesor_id = p.id
        WHERE h.grupo_id = ?
        ORDER BY h.dia, h.hora_inicio
    ");
    
    $stmt->bind_param("i", $grupo_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $horarios_raw = $result->fetch_all(MYSQLI_ASSOC);
    
    // Organizar los horarios en una estructura para facilitar la visualización
    $dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];
    
    // Determinar el turno y los bloques de horario
    $turno = $grupo_info['turno'];
    $config = [
        'matutino' => [
            'hora_inicio_min' => '07:00:00',
            'hora_fin_max' => '13:40:00',
            'duracion_modulo' => 50 // minutos
        ],
        'vespertino' => [
            'hora_inicio_min' => '14:00:00',
            'hora_fin_max' => '20:40:00',
            'duracion_modulo' => 50 // minutos
        ]
    ];
    
    // Generar los bloques de horario
    $bloques_horario = [];
    $hora_actual = strtotime($config[$turno]['hora_inicio_min']);
    $hora_fin_max = strtotime($config[$turno]['hora_fin_max']);
    
    while ($hora_actual < $hora_fin_max) {
        $hora_inicio = date('H:i:s', $hora_actual);
        $hora_actual += $config[$turno]['duracion_modulo'] * 60; // Convertir minutos a segundos
        $hora_fin = date('H:i:s', $hora_actual);
        
        $bloques_horario[] = [
            'hora_inicio' => $hora_inicio,
            'hora_fin' => $hora_fin
        ];
    }
    
    // Inicializar la estructura de la tabla
    $tabla_horarios = [];
    foreach ($bloques_horario as $bloque) {
        $hora_inicio_format = date('H:i', strtotime($bloque['hora_inicio']));
        $hora_fin_format = date('H:i', strtotime($bloque['hora_fin']));
        $key = $hora_inicio_format . ' - ' . $hora_fin_format;
        
        $tabla_horarios[$key] = [
            'horario' => $key
        ];
        
        // Inicializar cada día como vacío
        foreach ($dias_semana as $dia) {
            $tabla_horarios[$key][$dia] = null;
        }
    }
    
    // Llenar la tabla con los horarios asignados
    foreach ($horarios_raw as $horario) {
        $hora_inicio_format = date('H:i', strtotime($horario['hora_inicio']));
        $hora_fin_format = date('H:i', strtotime($horario['hora_fin']));
        $key = $hora_inicio_format . ' - ' . $hora_fin_format;
        $dia = $horario['dia'];
        
        // Nombre corto del profesor (nombre y primer apellido)
        $nombre_profesor = $horario['profesor_nombre'];
        $apellido_profesor = '';
        if ($horario['profesor_apellidos']) {
            $apellidos = explode(' ', $horario['profesor_apellidos']);
            $apellido_profesor = $apellidos[0]; // Solo el primer apellido
        }
        $profesor_corto = $nombre_profesor ? $nombre_profesor . ' ' . $apellido_profesor : 'Depto';
        
        // Si el bloque ya existe, asignar la información
        if (isset($tabla_horarios[$key])) {
            $tabla_horarios[$key][$dia] = [
                'codigo' => $horario['materia_codigo'],
                'nombre' => $horario['materia_nombre'],
                'profesor' => $profesor_corto,
                'estado' => $horario['estado'],
                'materia_id' => $horario['materia_id']
            ];
        }
        
        // Guardar detalles de la materia para la leyenda
        if (!isset($detalles_materias[$horario['materia_codigo']])) {
            $detalles_materias[$horario['materia_codigo']] = [
                'nombre' => $horario['materia_nombre'],
                'codigo' => $horario['materia_codigo'],
                'profesor_completo' => $horario['profesor_nombre'] ? $horario['profesor_nombre'] . ' ' . $horario['profesor_apellidos'] : 'Departamento'
            ];
        }
    }
    
    // Ordenar la tabla por horario
    ksort($tabla_horarios);
    $horarios = array_values($tabla_horarios);
    
    // Ordenar detalles de materias por código
    ksort($detalles_materias);
}

// Obtener todos los grupos para el selector
$grupos = $conn->query("
    SELECT g.*, c.nombre as carrera_nombre 
    FROM grupos g 
    JOIN carreras c ON g.carrera_id = c.id 
    ORDER BY g.nombre
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Visualizar Horario</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include 'header.php'; ?>
    <style>
        .horario-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .horario-table thead {
            background: linear-gradient(45deg, #3b82f6, #2563eb);
        }
        
        .horario-table th {
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            padding: 15px 10px;
            text-align: center;
        }
        
        .horario-table td {
            padding: 8px 5px;
            border: 1px solid #e5e7eb;
            text-align: center;
            vertical-align: middle;
            transition: all 0.2s ease;
        }
        
        .horario-table tr:nth-child(even) {
            background-color: #f1f5f9;
        }
        
        .horario-table tr:hover td {
            background-color: #e0f2fe;
        }
        
        .horario-table .hora-columna {
            background-color: #f1f5f9;
            font-weight: 600;
            color: #1e293b;
            width: 100px;
        }
        
        .materia-celda {
            background-color: white;
            border-radius: 6px;
            padding: 8px 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            min-height: 65px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: center;
        }
        
        .materia-codigo {
            font-weight: 700;
            font-size: 1rem;
            color: #1e40af;
            margin-bottom: 3px;
        }
        
        .materia-profesor {
            font-size: 0.75rem;
            color: #64748b;
        }
        
        .grupo-info {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .grupo-detalles {
            display: flex;
            flex-direction: column;
        }
        
        .grupo-nombre {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 5px;
        }
        
        .grupo-carrera, .grupo-semestre, .grupo-turno {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 2px;
        }
        
        .acciones-grupo {
            display: flex;
            gap: 10px;
        }
        
        /* Estilos para la leyenda */
        .leyenda-materias {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .leyenda-titulo {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .leyenda-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .leyenda-item {
            padding: 10px;
            border-radius: 6px;
            background-color: #f8fafc;
            transition: all 0.2s;
        }
        
        .leyenda-item:hover {
            background-color: #f1f5f9;
            transform: translateY(-2px);
        }
        
        .leyenda-codigo {
            font-weight: 700;
            font-size: 1rem;
            color: #1e40af;
        }
        
        .leyenda-nombre {
            font-size: 0.9rem;
            color: #4b5563;
            margin: 5px 0;
        }
        
        .leyenda-profesor {
            font-size: 0.8rem;
            color: #64748b;
            font-style: italic;
        }
        
        /* Estilos para impresión */
        @media print {
            .no-print {
                display: none !important;
            }
            
            .page-wrapper {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                padding: 0 !important;
            }
            
            body {
                background: white !important;
            }
            
            .card {
                box-shadow: none !important;
                margin: 0 !important;
            }
            
            .card-header {
                background: white !important;
                color: black !important;
                padding: 10px 0 !important;
            }
            
            .horario-table {
                box-shadow: none !important;
            }
            
            .horario-table th {
                background: #f1f5f9 !important;
                color: black !important;
            }
            
            .leyenda-materias {
                box-shadow: none !important;
                border: 1px solid #e5e7eb;
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <!-- Botón toggle para menú en móviles -->
    <button class="sidebar-toggle no-print" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Incluir el menú lateral -->
    <?php include 'nav.php'; ?>

    <div class="page-wrapper">
        <div class="content-wrapper">
            <div class="page-header no-print">
                <h1>Visualizar Horario</h1>
            </div>
            
            <div class="container">
                <?php if(isset($mensaje)): ?>
                    <div class="alert alert-<?= $tipo ?> alert-dismissible fade show no-print">
                        <?= $mensaje ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <!-- Selector de grupo -->
                <div class="card mb-4 no-print">
                    <div class="card-header">
                        <h2>Seleccionar Grupo</h2>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="form-row">
                            <div class="col-md-9">
                                <select name="grupo_id" id="grupo_id" class="form-control" required>
                                    <option value="">Seleccione un grupo</option>
                                    <?php foreach($grupos as $g): ?>
                                        <option value="<?= $g['id'] ?>" <?= ($grupo_id == $g['id']) ? 'selected' : '' ?>>
                                            <?= $g['nombre'] . ' - ' . $g['carrera_nombre'] . ' (' . $g['semestre'] . '° semestre)' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn-primary btn-block">
                                    <i class="fas fa-search"></i> Ver Horario
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php if($grupo_info): ?>
                    <!-- Información del grupo -->
                    <div class="grupo-info">
                        <div class="grupo-detalles">
                            <div class="grupo-nombre"><?= $grupo_info['nombre'] ?></div>
                            <div class="grupo-carrera"><strong>Carrera:</strong> <?= $grupo_info['carrera_nombre'] ?></div>
                            <div class="grupo-semestre"><strong>Semestre:</strong> <?= $grupo_info['semestre'] ?>° semestre</div>
                            <div class="grupo-turno"><strong>Turno:</strong> <?= ucfirst($grupo_info['turno']) ?></div>
                        </div>
                        <div class="acciones-grupo no-print">
                            <button onclick="window.print()" class="btn-info">
                                <i class="fas fa-print"></i> Imprimir
                            </button>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="grupo_id" value="<?= $grupo_id ?>">
                                <button type="submit" name="eliminar_horario" class="btn-danger" onclick="return confirm('¿Está seguro de eliminar este horario? Esta acción restaurará las horas disponibles a los profesores.')">
                                    <i class="fas fa-trash"></i> Eliminar Horario
                                </button>
                            </form>

                        </div>
                    </div>
                    
                    <!-- Tabla de horario -->
                    <div class="card">
                        <div class="card-header">
                            <h2>Horario Semanal</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="horario-table">
                                    <thead>
                                        <tr>
                                            <th>Horario</th>
                                            <th>Lunes</th>
                                            <th>Martes</th>
                                            <th>Miércoles</th>
                                            <th>Jueves</th>
                                            <th>Viernes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($horarios as $bloque): ?>
                                            <tr>
                                                <td class="hora-columna"><?= $bloque['horario'] ?></td>
                                                <?php foreach(['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'] as $dia): ?>
                                                    <td>
                                                        <?php if(isset($bloque[$dia]) && $bloque[$dia]): ?>
                                                            <div class="materia-celda">
                                                                <div class="materia-codigo"><?= $bloque[$dia]['codigo'] ?></div>
                                                                <div class="materia-profesor"><?= $bloque[$dia]['profesor'] ?></div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Leyenda de materias -->
                            <?php if (!empty($detalles_materias)): ?>
                            <div class="leyenda-materias">
                                <div class="leyenda-titulo">
                                    <i class="fas fa-info-circle mr-2"></i> Materias del Horario
                                </div>
                                <div class="leyenda-grid">
                                    <?php foreach($detalles_materias as $materia): ?>
                                        <div class="leyenda-item">
                                            <div class="leyenda-codigo"><?= $materia['codigo'] ?></div>
                                            <div class="leyenda-nombre"><?= $materia['nombre'] ?></div>
                                            <div class="leyenda-profesor">Profesor: <?= $materia['profesor_completo'] ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif($grupo_id): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No hay horarios generados para este grupo. 
                        <a href="generar_horarios.php" class="alert-link">Generar horario</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Incluir el footer -->
        <?php include 'footer.php'; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.querySelector('.sidebar');
            const contentWrapper = document.querySelector('.content-wrapper');
            
            if (sidebarToggle && sidebar && contentWrapper) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                    
                    if (window.innerWidth <= 768) {
                        if (sidebar.classList.contains('active')) {
                            contentWrapper.style.marginLeft = '270px';
                        } else {
                            contentWrapper.style.marginLeft = '0';
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>