<?php include 'conexion.php'; 
$sql = "SELECT p.*, 
(SELECT COUNT(*) FROM disponibilidad_profesor WHERE profesor_id = p.id) as tiene_disponibilidad 
FROM profesores p ORDER BY p.apellidos, p.nombre";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Disponibilidad Profesores</title>
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
        
        <h1>Disponibilidad de Profesores</h1>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Apellidos</th>
                        <th>Horas Disponibles</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT * FROM profesores WHERE estado = 1";
                    $result = $conn->query($sql);
                    
                    if ($result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            // Verificar si ya tiene horarios registrados
                            $sqlHorarios = "SELECT COUNT(*) as total FROM disponibilidad_profesor WHERE profesor_id = " . $row["id"];
                            $resultHorarios = $conn->query($sqlHorarios);
                            $totalHorarios = $resultHorarios->fetch_assoc()['total'];
                            
                            $estadoDisponibilidad = ($totalHorarios > 0) ? 
                                "<span style='color: green;'>Registrada</span>" : 
                                "<span style='color: red;'>Pendiente</span>";
                            
                            echo "<tr>";
                            echo "<td>" . $row["id"] . "</td>";
                            echo "<td>" . $row["nombre"] . "</td>";
                            echo "<td>" . $row["apellidos"] . "</td>";
                            echo "<td>" . $row["horas_disponibles"] . "</td>";
                            echo "<td>
                                <a href='disponibilidad_profesor.php?id=" . $row["id"] . "' class='btn-primary'>Gestionar Disponibilidad</a>
                                <span style='margin-left: 10px;'>" . $estadoDisponibilidad . "</span>
                            </td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5'>No hay profesores registrados</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>