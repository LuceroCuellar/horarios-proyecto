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
    <?php include 'header.php'; ?>
    <style>
        .horario-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .dia-header {
            background: #3f51b5;
            color: white;
            padding: 10px;
        }
        .hora-slot {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>
    
    <div class="container">
        <h2>Horarios por Profesor</h2>
        
        <form method="GET" class="mb-4">
            <div class="form-row">
                <div class="col-md-8">
                    <select name="profesor_id" class="form-control" required>
                        <option value="">Seleccionar Profesor</option>
                        <?php foreach($profesores as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= isset($_GET['profesor_id']) && $_GET['profesor_id'] == $p['id'] ? 'selected' : '' ?>>
                                <?= $p['nombre'] . ' ' . $p['apellidos'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary btn-block">
                        Mostrar Horario
                    </button>
                </div>
            </div>
        </form>
        
        <?php if(!empty($horarios)): ?>
            <div class="horario-table">
                <?php
                $dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];
                $horas = ['07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00'];
                
                foreach ($dias as $dia): 
                    $clases_dia = array_filter($horarios, function($h) use ($dia) {
                        return $h['dia'] == $dia;
                    });
                ?>
                    <div class="dia-header"><?= $dia ?></div>
                    <div class="p-3">
                        <?php foreach ($horas as $hora): 
                            $clase = current(array_filter($clases_dia, function($c) use ($hora) {
                                return $c['hora_inicio'] <= $hora && $c['hora_fin'] > $hora;
                            }));
                        ?>
                            <div class="hora-slot">
                                <div class="row">
                                    <div class="col-2 font-weight-bold"><?= $hora ?></div>
                                    <div class="col-10">
                                        <?php if($clase): ?>
                                            <div class="class-card p-2 bg-light rounded">
                                                <strong><?= $clase['materia'] ?></strong><br>
                                                Grupo: <?= $clase['grupo'] ?><br>
                                                Aula: <?= $clase['aula'] ?? 'Por asignar' ?>  <!-- Mostrar el aula del grupo -->
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif(isset($_GET['profesor_id'])): ?>
            <div class="alert alert-info">No se encontraron horarios para este profesor</div>
        <?php endif; ?>
    </div>
</body>
</html>