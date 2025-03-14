<?php include 'conexion.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <title>Sistema de Generación de Horarios</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
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
            <!-- Header de la página -->
            <div class="page-header">
                <h1>Sistema de Generación Automática de Horarios</h1>
            </div>

            <div class="container">
                <div class="container-flex">
                    <div style="flex: 2">
                        <div class="card">
                            <div class="card-header">
                                <h2>Bienvenido al Sistema de Generación de Horarios</h2>
                            </div>
                            <div class="card-body">
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
                    </div>
                    
                    <div style="flex: 1">
                        <div class="card">
                            <div class="card-header">
                                <h3>Estadísticas del Sistema</h3>
                            </div>
                            <div class="card-body">
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
                                    <span><i class="fas fa-chalkboard-teacher"></i> Total Profesores:</span>
                                    <span><strong><?php echo $totalProfesores; ?></strong></span>
                                </div>
                                
                                <div class="preview-item">
                                    <span><i class="fas fa-book"></i> Total Materias:</span>
                                    <span><strong><?php echo $totalMaterias; ?></strong></span>
                                </div>
                                
                                <div class="preview-item">
                                    <span><i class="fas fa-graduation-cap"></i> Total Carreras:</span>
                                    <span><strong><?php echo $totalCarreras; ?></strong></span>
                                </div>
                                
                                <div class="preview-item">
                                    <span><i class="fas fa-calendar-alt"></i> Horarios Generados:</span>
                                    <span><strong><?php echo $totalHorarios; ?></strong></span>
                                </div>
                                
                                <div class="preview-item">
                                    <span><i class="fas fa-check-circle"></i> Horarios Aprobados:</span>
                                    <span><strong><?php echo $totalAprobados; ?></strong></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <h3>Acciones Rápidas</h3>
                            </div>
                            <div class="card-body">
                                <div class="quick-actions">
                                    <a href="generar_horarios.php" class="btn-primary">
                                        <i class="fas fa-cogs"></i> Generar Horarios
                                    </a>
                                    <a href="revisar_horarios.php" class="btn-success">
                                        <i class="fas fa-clipboard-check"></i> Revisar Horarios
                                    </a>
                                    <a href="horarios_profesores.php" class="btn-secondary">
                                        <i class="fas fa-user-clock"></i> Horarios por Profesor
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
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
                contentWrapper.style.marginLeft = '270px'; // Actualizado a 270px
                if (mainFooter) mainFooter.style.marginLeft = '270px'; // Actualizado a 270px
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
                contentWrapper.style.marginLeft = '270px'; // Actualizado a 270px
                if (mainFooter) mainFooter.style.marginLeft = '270px'; // Actualizado a 270px
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