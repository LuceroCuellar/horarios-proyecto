<?php include 'conexion.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <title>Sistema de Generación de Horarios</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="container">
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
        
        <h1>Sistema de Generación Automática de Horarios</h1>
        
        <div class="container-flex">
            <div style="flex: 2">
                <div class="horario-card">
                    <h2>Bienvenido al Sistema de Generación de Horarios</h2>
                    <p>Este sistema le permite administrar y generar automáticamente horarios académicos considerando:</p>
                    <ul style="margin-left: 20px; margin-top: 10px; margin-bottom: 20px;">
                        <li>Disponibilidad de profesores</li>
                        <li>Asignación de materias a profesores</li>
                        <li>Disponibilidad de departamentos especiales (Inglés y Desarrollo Humano)</li>
                        <li>Horas disponibles de cada profesor</li>
                        <li>Revisión y aprobación de horarios preliminares</li>
                    </ul>
                    
                    <h3>Pasos para la generación de horarios:</h3>
                    <ol style="margin-left: 20px; margin-top: 10px;">
                        <li>Registre las carreras en el sistema</li>
                        <li>Registre los profesores con sus horas disponibles</li>
                        <li>Registre las materias y asígnelas a las carreras</li>
                        <li>Asigne materias a los profesores</li>
                        <li>Establezca la disponibilidad horaria de cada profesor</li>
                        <li>Configure la disponibilidad de los departamentos de Inglés y Desarrollo Humano</li>
                        <li>Configure los grupos para los que desea generar horarios</li>
                        <li>Genere los horarios preliminares</li>
                        <li>Revise y apruebe los horarios generados</li>
                        <li>Visualice los horarios por profesor</li>
                    </ol>
                </div>
            </div>
            
            <div style="flex: 1">
                <div class="horario-card">
                    <h3>Estadísticas del Sistema</h3>
                    <?php
                    // Contar profesores
                    $sqlProfesores = "SELECT COUNT(*) as total FROM profesores WHERE estado = 1";
                    $resultProfesores = $conn->query($sqlProfesores);
                    $totalProfesores = $resultProfesores->fetch_assoc()['total'];
                    
                    // Contar materias
                    $sqlMaterias = "SELECT COUNT(*) as total FROM materias WHERE estado = 1";
                    $resultMaterias = $conn->query($sqlMaterias);
                    $totalMaterias = $resultMaterias->fetch_assoc()['total'];
                    
                    // Contar carreras
                    $sqlCarreras = "SELECT COUNT(*) as total FROM carreras WHERE estado = 1";
                    $resultCarreras = $conn->query($sqlCarreras);
                    $totalCarreras = $resultCarreras->fetch_assoc()['total'];
                    
                    // Contar horarios generados
                    $sqlHorarios = "SELECT COUNT(*) as total FROM horarios";
                    $resultHorarios = $conn->query($sqlHorarios);
                    $totalHorarios = $resultHorarios->fetch_assoc()['total'];
                    
                    // Contar horarios aprobados
                    $sqlAprobados = "SELECT COUNT(*) as total FROM horarios WHERE estado = 'aprobado'";
                    $resultAprobados = $conn->query($sqlAprobados);
                    $totalAprobados = $resultAprobados->fetch_assoc()['total'];
                    ?>
                    
                    <div class="preview-item">
                        <span>Total Profesores:</span>
                        <span><strong><?php echo $totalProfesores; ?></strong></span>
                    </div>
                    
                    <div class="preview-item">
                        <span>Total Materias:</span>
                        <span><strong><?php echo $totalMaterias; ?></strong></span>
                    </div>
                    
                    <div class="preview-item">
                        <span>Total Carreras:</span>
                        <span><strong><?php echo $totalCarreras; ?></strong></span>
                    </div>
                    
                    <div class="preview-item">
                        <span>Horarios Generados:</span>
                        <span><strong><?php echo $totalHorarios; ?></strong></span>
                    </div>
                    
                    <div class="preview-item">
                        <span>Horarios Aprobados:</span>
                        <span><strong><?php echo $totalAprobados; ?></strong></span>
                    </div>
                </div>
                
                <div class="horario-card">
                    <h3>Acciones Rápidas</h3>
                    <a href="generar_horarios.php" class="btn-primary" style="display: block; text-align: center; margin-bottom: 10px; text-decoration: none;">Generar Horarios</a>
                    <a href="revisar_horarios.php" class="btn-success" style="display: block; text-align: center; margin-bottom: 10px; text-decoration: none;">Revisar Horarios</a>
                    <a href="horarios_profesores.php" class="btn-secondary" style="display: block; text-align: center; text-decoration: none;">Ver Horarios por Profesor</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>