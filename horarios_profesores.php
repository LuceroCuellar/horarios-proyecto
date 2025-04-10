<?php
include 'conexion.php';

// Obtener todos los profesores
$profesores = $conn->query("
    SELECT * 
    FROM profesores 
    WHERE estado = 1
    ORDER BY nombre, apellidos
")->fetch_all(MYSQLI_ASSOC);

// Obtener horarios por profesor
$horarios = [];
$recursamientos = [];
$dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];
$horas = [];
$detalles_materias = []; // Para la leyenda

if (isset($_GET['profesor_id'])) {
    $profesor_id = $_GET['profesor_id'];
    
    // Obtener información del profesor
    $profesor_info = null;
    foreach ($profesores as $p) {
        if ($p['id'] == $profesor_id) {
            $profesor_info = $p;
            break;
        }
    }
    
    // Determinar turnos a mostrar (podemos mostrar ambos si el profesor tiene clases en ambos)
    $turnos_profesor = $conn->query("
        SELECT DISTINCT g.turno
        FROM horarios h
        JOIN grupos g ON h.grupo_id = g.id
        WHERE h.profesor_id = $profesor_id
        UNION
        SELECT DISTINCT CASE 
                            WHEN r.hora_inicio < '14:00:00' THEN 'matutino' 
                            ELSE 'vespertino' 
                        END as turno
        FROM recursamientos r
        WHERE r.profesor_id = $profesor_id
    ")->fetch_all(MYSQLI_ASSOC);
    
    // Si no hay turnos específicos, mostrar ambos por defecto
    if (empty($turnos_profesor)) {
        $turnos_profesor = [
            ['turno' => 'matutino'],
            ['turno' => 'vespertino']
        ];
    }
    
    $config_turnos = [
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
    
    // Generar todos los bloques de horario para los turnos del profesor
    foreach ($turnos_profesor as $turno_data) {
        $turno = $turno_data['turno'];
        $hora_actual = strtotime($config_turnos[$turno]['hora_inicio_min']);
        $hora_fin_max = strtotime($config_turnos[$turno]['hora_fin_max']);
        
        while ($hora_actual < $hora_fin_max) {
            $hora_inicio = date('H:i:s', $hora_actual);
            $hora_actual += $config_turnos[$turno]['duracion_modulo'] * 60; // Convertir minutos a segundos
            $hora_fin = date('H:i:s', $hora_actual);
            
            $hora_key = date('H:i', strtotime($hora_inicio));
            if (!in_array($hora_key, $horas)) {
                $horas[] = $hora_key;
            }
        }
    }
    
    // Ordenar las horas
    sort($horas);
    
    // Obtener clases regulares del profesor
    $clases_regulares = $conn->query("
        SELECT h.*, 
        g.nombre as grupo_nombre,
        g.aula as aula,
        g.turno as turno,
        m.nombre as materia_nombre,
        m.codigo as materia_codigo,
        c.nombre as carrera_nombre
        FROM horarios h
        JOIN grupos g ON h.grupo_id = g.id
        JOIN materias m ON h.materia_id = m.id
        JOIN carreras c ON g.carrera_id = c.id
        WHERE h.profesor_id = $profesor_id
        ORDER BY h.dia, h.hora_inicio
    ")->fetch_all(MYSQLI_ASSOC);
    
    // Obtener recursamientos del profesor
    $recursamientos_data = $conn->query("
        SELECT r.*, 
        g.nombre as grupo_nombre,
        g.aula as aula, 
        m.nombre as materia_nombre,
        m.codigo as materia_codigo,
        c.nombre as carrera_nombre
        FROM recursamientos r
        JOIN grupos g ON r.grupo_id = g.id
        JOIN materias m ON r.materia_id = m.id
        JOIN carreras c ON g.carrera_id = c.id
        WHERE r.profesor_id = $profesor_id
        AND r.estado = 1
        ORDER BY r.dia, r.hora_inicio
    ")->fetch_all(MYSQLI_ASSOC);
    
    // Inicializar matriz de horario (día x hora)
    $horario_matriz = [];
    foreach ($dias_semana as $dia) {
        $horario_matriz[$dia] = [];
        foreach ($horas as $hora) {
            $horario_matriz[$dia][$hora] = null;
        }
    }
    
    // Llenar la matriz con clases regulares
    foreach ($clases_regulares as $clase) {
        // Guardar en la leyenda para información detallada
        $codigo = $clase['materia_codigo'];
        if (!isset($detalles_materias[$codigo])) {
            $detalles_materias[$codigo] = [
                'codigo' => $codigo,
                'nombre' => $clase['materia_nombre'],
                'grupo' => $clase['grupo_nombre'],
                'aula' => $clase['aula'] ?: 'No asignada',
                'carrera' => $clase['carrera_nombre'],
                'tipo' => 'regular'
            ];
        }
        
        // Usar formato de 24 horas para las horas
        $hora_inicio = date('H:i', strtotime($clase['hora_inicio']));
        $dia = $clase['dia'];
        
        // Ubicar la hora en nuestro array de horas
        $hora_index = array_search($hora_inicio, $horas);
        if ($hora_index !== false) {
            $horario_matriz[$dia][$horas[$hora_index]] = [
                'tipo' => 'regular',
                'materia_codigo' => $clase['materia_codigo'],
                'grupo_nombre' => $clase['grupo_nombre'],
                'aula' => $clase['aula'] ?: 'N/A',
                'estado' => $clase['estado']
            ];
        }
    }
    
    // Añadir recursamientos a la matriz
    foreach ($recursamientos_data as $recu) {
        // Guardar en la leyenda para información detallada
        $codigo = $recu['materia_codigo'];
        if (!isset($detalles_materias[$codigo])) {
            $detalles_materias[$codigo] = [
                'codigo' => $codigo,
                'nombre' => $recu['materia_nombre'],
                'grupo' => $recu['grupo_nombre'],
                'aula' => $recu['aula'] ?: 'No asignada',
                'carrera' => $recu['carrera_nombre'],
                'tipo' => 'recursamiento'
            ];
        }
        
        // Usar formato de 24 horas para las horas
        $hora_inicio = date('H:i', strtotime($recu['hora_inicio']));
        $dia = $recu['dia'];
        
        // Ubicar la hora en nuestro array de horas
        $hora_index = array_search($hora_inicio, $horas);
        if ($hora_index !== false) {
            $horario_matriz[$dia][$horas[$hora_index]] = [
                'tipo' => 'recursamiento',
                'materia_codigo' => $recu['materia_codigo'],
                'grupo_nombre' => $recu['grupo_nombre'],
                'aula' => $recu['aula'] ?: 'N/A',
                'estado' => 'recursamiento'
            ];
        }
    }
    
    // Pasar la matriz a la vista
    $horario_completo = $horario_matriz;
    
    // Ordenar detalles de materias por código
    ksort($detalles_materias);
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
            height: 80px;
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
            height: auto;
            vertical-align: middle;
        }
        
        .clase-card {
            background-color: #f8fafc;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 8px 5px;
            height: 100%;
            display: flex;
            flex-direction: column;
            transition: all 0.2s ease;
        }
        
        .clase-card-regular {
            border-left: 4px solid #3b82f6;
        }
        
        .clase-card-recursamiento {
            border-left: 4px solid #f59e0b;
        }
        
        .clase-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 6px rgba(0,0,0,0.1);
        }
        
        .clase-codigo {
            font-weight: 700;
            font-size: 1rem;
            color: #1e40af;
            margin-bottom: 3px;
        }
        
        .clase-grupo {
            font-size: 0.75rem;
            color: #4b5563;
            margin-bottom: 3px;
        }
        
        .clase-aula {
            font-size: 0.7rem;
            background: #e0f2fe;
            color: #0369a1;
            padding: 2px 4px;
            border-radius: 4px;
            display: inline-block;
        }
        
        .badge-recursamiento {
            position: absolute;
            top: 2px;
            right: 2px;
            background-color: #fff7ed;
            color: #f59e0b;
            padding: 2px 4px;
            border-radius: 4px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .profesor-selector {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .profesor-info {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .profesor-nombre {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 5px;
        }
        
        .profesor-contacto {
            font-size: 0.9rem;
            color: #64748b;
        }
        
        .profesor-contacto i {
            margin-right: 5px;
            color: #3b82f6;
        }
        
        .profesor-horas {
            font-size: 0.9rem;
            color: #1e293b;
            margin-top: 10px;
        }
        
        .profesor-horas-valor {
            font-weight: 600;
            color: #0369a1;
        }
        
        .resumen-clases {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .resumen-titulo {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .resumen-titulo i {
            margin-right: 10px;
            color: #3b82f6;
        }
        
        .turno-badge {
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .turno-matutino {
            background-color: #dbeafe;
            color: #2563eb;
        }
        
        .turno-vespertino {
            background-color: #fef3c7;
            color: #d97706;
        }
        
        .acciones-horario {
            display: flex;
            gap: 10px;
        }
        
        .btn-imprimir {
            background-color: #3b82f6;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-imprimir:hover {
            background-color: #2563eb;
            transform: translateY(-2px);
        }
        
        .btn-imprimir i {
            margin-right: 5px;
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
            position: relative;
            overflow: hidden;
        }
        
        .leyenda-item:hover {
            background-color: #f1f5f9;
            transform: translateY(-2px);
        }
        
        .leyenda-item.recursamiento {
            border-left: 4px solid #f59e0b;
        }
        
        .leyenda-item.regular {
            border-left: 4px solid #3b82f6;
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
        
        .leyenda-grupo, .leyenda-aula, .leyenda-carrera {
            font-size: 0.8rem;
            color: #64748b;
            margin-bottom: 2px;
        }
        
        .leyenda-tipo {
            position: absolute;
            top: 5px;
            right: 5px;
            padding: 2px 5px;
            border-radius: 4px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .leyenda-tipo.recursamiento {
            background-color: #fff7ed;
            color: #f59e0b;
        }
        
        .leyenda-tipo.regular {
            background-color: #dbeafe;
            color: #2563eb;
        }
        
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
                <h1>Horarios por Profesor</h1>
            </div>
            
            <div class="container">
                <!-- Selector de profesor -->
                <div class="profesor-selector no-print">
                    <form method="GET" class="form-row">
                        <div class="col-md-9">
                            <label for="profesor_id"><strong>Seleccionar Profesor:</strong></label>
                            <select name="profesor_id" id="profesor_id" class="form-control" required>
                                <option value="">Seleccione un profesor</option>
                                <?php foreach($profesores as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= isset($_GET['profesor_id']) && $_GET['profesor_id'] == $p['id'] ? 'selected' : '' ?>>
                                        <?= $p['nombre'] . ' ' . $p['apellidos'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn-primary btn-block">
                                <i class="fas fa-calendar-alt"></i> Mostrar Horario
                            </button>
                        </div>
                    </form>
                </div>
                
                <?php if(isset($_GET['profesor_id']) && $profesor_info): ?>
                    <!-- Información del profesor -->
                    <div class="profesor-info">
                        <div>
                            <div class="profesor-nombre"><?= $profesor_info['nombre'] . ' ' . $profesor_info['apellidos'] ?></div>
                            <?php if($profesor_info['email']): ?>
                                <div class="profesor-contacto">
                                    <i class="fas fa-envelope"></i> <?= $profesor_info['email'] ?>
                                </div>
                            <?php endif; ?>
                            <?php if($profesor_info['telefono']): ?>
                                <div class="profesor-contacto">
                                    <i class="fas fa-phone"></i> <?= $profesor_info['telefono'] ?>
                                </div>
                            <?php endif; ?>
                            <div class="profesor-horas">
                                Horas disponibles: <span class="profesor-horas-valor"><?= $profesor_info['horas_disponibles'] ?></span>
                            </div>
                        </div>
                        <div class="acciones-horario no-print">
                            <button onclick="window.print()" class="btn-imprimir">
                                <i class="fas fa-print"></i> Imprimir Horario
                            </button>
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
                                            <?php foreach($dias_semana as $dia): ?>
                                                <th><?= $dia ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($horas as $hora): ?>
                                            <tr>
                                                <td class="hora-columna"><?= $hora ?></td>
                                                <?php foreach($dias_semana as $dia): ?>
                                                    <td>
                                                        <?php if(isset($horario_completo[$dia][$hora]) && $horario_completo[$dia][$hora]): ?>
                                                            <?php $clase = $horario_completo[$dia][$hora]; ?>
                                                            <div class="clase-card clase-card-<?= $clase['tipo'] ?>" style="position: relative;">
                                                                <div class="clase-codigo"><?= $clase['materia_codigo'] ?></div>
                                                                <div class="clase-grupo"><?= $clase['grupo_nombre'] ?></div>
                                                                <div class="clase-aula"><?= $clase['aula'] ?></div>
                                                                <?php if($clase['tipo'] == 'recursamiento'): ?>
                                                                    <div class="badge-recursamiento">R</div>
                                                                <?php endif; ?>
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
                                    <i class="fas fa-info-circle mr-2"></i> Materias Asignadas
                                </div>
                                <div class="leyenda-grid">
                                    <?php foreach($detalles_materias as $materia): ?>
                                        <div class="leyenda-item <?= $materia['tipo'] ?>">
                                            <div class="leyenda-codigo"><?= $materia['codigo'] ?></div>
                                            <div class="leyenda-nombre"><?= $materia['nombre'] ?></div>
                                            <div class="leyenda-grupo"><strong>Grupo:</strong> <?= $materia['grupo'] ?></div>
                                            <div class="leyenda-aula"><strong>Aula:</strong> <?= $materia['aula'] ?></div>
                                            <div class="leyenda-carrera"><strong>Carrera:</strong> <?= $materia['carrera'] ?></div>
                                            <div class="leyenda-tipo <?= $materia['tipo'] ?>">
                                                <?= $materia['tipo'] == 'recursamiento' ? 'Recursamiento' : 'Regular' ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Resumen de clases por turno -->
                    <div class="row mt-4 no-print">
                        <?php 
                        // Contar clases por turno
                        $clases_matutino = 0;
                        $clases_vespertino = 0;
                        $recursamientos_matutino = 0;
                        $recursamientos_vespertino = 0;
                        
                        foreach($dias_semana as $dia) {
                            foreach($horas as $hora) {
                                if(isset($horario_completo[$dia][$hora]) && $horario_completo[$dia][$hora]) {
                                    $clase = $horario_completo[$dia][$hora];
                                    $es_matutino = strtotime($hora) < strtotime('14:00');
                                    
                                    if($es_matutino) {
                                        if($clase['tipo'] == 'regular') {
                                            $clases_matutino++;
                                        } else {
                                            $recursamientos_matutino++;
                                        }
                                    } else {
                                        if($clase['tipo'] == 'regular') {
                                            $clases_vespertino++;
                                        } else {
                                            $recursamientos_vespertino++;
                                        }
                                    }
                                }
                            }
                        }
                        
                        $tiene_matutino = $clases_matutino > 0 || $recursamientos_matutino > 0;
                        $tiene_vespertino = $clases_vespertino > 0 || $recursamientos_vespertino > 0;
                        ?>
                        
                        <?php if($tiene_matutino): ?>
                        <div class="col-md-6">
                            <div class="resumen-clases">
                                <div class="resumen-titulo">
                                    <i class="fas fa-sun"></i> Turno Matutino
                                    <span class="turno-badge turno-matutino">
                                        <?= $clases_matutino + $recursamientos_matutino ?> horas
                                    </span>
                                </div>
                                <ul>
                                    <li><strong><?= $clases_matutino ?></strong> horas de clases regulares</li>
                                    <li><strong><?= $recursamientos_matutino ?></strong> horas de recursamientos</li>
                                </ul>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if($tiene_vespertino): ?>
                        <div class="col-md-6">
                            <div class="resumen-clases">
                                <div class="resumen-titulo">
                                    <i class="fas fa-moon"></i> Turno Vespertino
                                    <span class="turno-badge turno-vespertino">
                                        <?= $clases_vespertino + $recursamientos_vespertino ?> horas
                                    </span>
                                </div>
                                <ul>
                                    <li><strong><?= $clases_vespertino ?></strong> horas de clases regulares</li>
                                    <li><strong><?= $recursamientos_vespertino ?></strong> horas de recursamientos</li>
                                </ul>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                <?php elseif(isset($_GET['profesor_id'])): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No hay horarios registrados para este profesor.
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