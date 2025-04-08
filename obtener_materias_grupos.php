<?php
include 'conexion.php';

header('Content-Type: application/json');

if (isset($_GET['semestre'])) {
    $semestre = intval($_GET['semestre']);
    
    $stmt = $conn->prepare("SELECT m.*, c.nombre as carrera_nombre FROM materias m 
                           LEFT JOIN carreras c ON m.carrera_id = c.id 
                           WHERE m.semestre = ? AND m.estado = 1 
                           ORDER BY m.nombre");
    $stmt->bind_param("i", $semestre);
    $stmt->execute();
    $result = $stmt->get_result();
    $materias = $result->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode($materias);
} else {
    echo json_encode([]);
}
?>