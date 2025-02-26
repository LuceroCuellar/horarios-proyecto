<?php
include 'conexion.php';

$profesor_id = (int)$_GET['profesor_id'] ?? 0;

// Materias asignadas
$asignadas = $conn->query("
    SELECT m.id, m.nombre 
    FROM materias m
    JOIN profesor_materia pm ON pm.materia_id = m.id
    WHERE pm.profesor_id = $profesor_id
    ORDER BY m.nombre
")->fetch_all(MYSQLI_ASSOC);

// Materias disponibles
$disponibles = $conn->query("
    SELECT m.id, m.nombre 
    FROM materias m
    WHERE m.estado = 1 AND m.id NOT IN (
        SELECT materia_id FROM profesor_materia WHERE profesor_id = $profesor_id
    )
    ORDER BY m.nombre
")->fetch_all(MYSQLI_ASSOC);

function generarOptions($materias) {
    $html = '';
    foreach ($materias as $m) {
        $html .= `<option value="{$m['id']}">{$m['nombre']}</option>`;
    }
    return $html;
}

echo json_encode([
    'asignadas' => generarOptions($asignadas),
    'disponibles' => generarOptions($disponibles)
]);