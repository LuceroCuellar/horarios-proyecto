<?php $current_page = basename($_SERVER['PHP_SELF']); ?>
<div class="nav-menu">
    <a href="index.php" class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">Inicio</a>
    <a href="crud_profesores.php" class="<?php echo ($current_page == 'crud_profesores.php') ? 'active' : ''; ?>">Profesores</a>
    <a href="crud_materias.php" class="<?php echo ($current_page == 'crud_materias.php') ? 'active' : ''; ?>">Materias</a>
    <a href="crud_carreras.php" class="<?php echo ($current_page == 'crud_carreras.php') ? 'active' : ''; ?>">Carreras</a>
    <a href="asignar_materias.php" class="<?php echo ($current_page == 'asignar_materias.php') ? 'active' : ''; ?>">Asignar Materias</a>
    <a href="disponibilidad_profesores.php" class="<?php echo ($current_page == 'disponibilidad_profesores.php') ? 'active' : ''; ?>">Disponibilidad Profesores</a>
    <a href="disponibilidad_departamentos.php" class="<?php echo ($current_page == 'disponibilidad_departamentos.php') ? 'active' : ''; ?>">Disponibilidad Departamentos</a>
    <a href="generar_horarios.php" class="<?php echo ($current_page == 'generar_horarios.php') ? 'active' : ''; ?>">Generar Horarios</a>
    <a href="revisar_horarios.php" class="<?php echo ($current_page == 'revisar_horarios.php') ? 'active' : ''; ?>">Revisar Horarios</a>
    <a href="horarios_profesores.php" class="<?php echo ($current_page == 'horarios_profesores.php') ? 'active' : ''; ?>">Horarios por Profesor</a>
</div>
<div class="container mt-4">