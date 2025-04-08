<?php
// Determinar la página actual para resaltar el enlace activo
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar">
    <div class="logo-container">
        <h2>Sistema Horarios</h2>
    </div>
    <nav class="nav-menu-sidebar">
        <a href="index.php" class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
            <i class="fas fa-home" style="margin-right: 20px;"></i> <span>Inicio</span>
        </a>
        <a href="crud_profesores.php" class="<?php echo ($current_page == 'crud_profesores.php') ? 'active' : ''; ?>">
            <i class="fas fa-chalkboard-teacher" style="margin-right: 20px;"></i> <span>Profesores</span>
        </a>
        <a href="crud_materias.php" class="<?php echo ($current_page == 'crud_materias.php') ? 'active' : ''; ?>">
            <i class="fas fa-book" style="margin-right: 20px;"></i> <span>Materias</span>
        </a>
        <a href="crud_carreras.php" class="<?php echo ($current_page == 'crud_carreras.php') ? 'active' : ''; ?>">
            <i class="fas fa-graduation-cap" style="margin-right: 20px;"></i> <span>Carreras</span>
        </a>
        <a href="asignar_materias.php" class="<?php echo ($current_page == 'asignar_materias.php') ? 'active' : ''; ?>">
            <i class="fas fa-tasks" style="margin-right: 20px;"></i> <span>Asignar Materias</span>
        </a>
        <a href="disponibilidad_profesores.php" class="<?php echo ($current_page == 'disponibilidad_profesores.php') ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt" style="margin-right: 20px;"></i> <span>Disponibilidad<br>Profesores</span>
        </a>
        <a href="disponibilidad_departamentos.php" class="<?php echo ($current_page == 'disponibilidad_departamentos.php') ? 'active' : ''; ?>">
            <i class="fas fa-building"style="margin-right: 20px;"></i> <span>Disponibilidad<br>Departamentos</span>
        </a>
        <a href="generar_horarios.php" class="<?php echo ($current_page == 'generar_horarios.php') ? 'active' : ''; ?>">
            <i class="fas fa-cogs" style="margin-right: 20px;"></i> <span>Generar Horarios</span>
        </a>
        <a href="revisar_horarios.php" class="<?php echo ($current_page == 'revisar_horarios.php') ? 'active' : ''; ?>">
            <i class="fas fa-clipboard-check" style="margin-right: 20px;"></i> <span>Revisar Horarios</span>
        </a>
        <a href="horarios_profesores.php" class="<?php echo ($current_page == 'horarios_profesores.php') ? 'active' : ''; ?>">
            <i class="fas fa-user-clock" style="margin-right: 20px;"></i> <span>Horarios por<br>Profesor</span>
        </a>
        <a href="crud_grupos.php" class="<?php echo ($current_page == 'crud_grupos.php') ? 'active' : ''; ?>">
            <i class="fas fa-user-clock" style="margin-right: 20px;"></i> <span>Grupos</span>
        </a>
        <a href="materias_grupo.php" class="<?php echo ($current_page == 'materias_grupo.php') ? 'active' : ''; ?>">
            <i class="fas fa-tasks" style="margin-right: 20px;"></i> <span>Asignar Materias a Grupos</span>
        </a>
    </nav>
    <div class="sidebar-footer">
        <p>&copy; <?php echo date('Y'); ?> Sistema de Horarios</p>
    </div>
</div>
