<?php
include 'conexion.php';

// Procesar formularios
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validaciones comunes
    $nombre = $conn->real_escape_string(trim($_POST['nombre'] ?? ''));
    $codigo = $conn->real_escape_string(trim($_POST['codigo'] ?? ''));
    $semestres = (int)($_POST['semestres'] ?? 0);
    $estado = isset($_POST['estado']) ? 1 : 0;

    if (isset($_POST['agregar_carrera'])) {
        if (empty($nombre) || empty($codigo) || $semestres <= 0) {
            $error = "Nombre, código y semestres son obligatorios";
        } else {
            $check = $conn->query("SELECT id FROM carreras WHERE codigo = '$codigo'");
            if ($check->num_rows > 0) {
                $error = "El código de carrera ya existe";
            } else {
                $sql = "INSERT INTO carreras (nombre, codigo, semestres, estado) 
                        VALUES ('$nombre', '$codigo', $semestres, $estado)";
                
                if ($conn->query($sql)) {
                    $message = "Carrera agregada correctamente";
                } else {
                    $error = "Error al agregar: " . $conn->error;
                }
            }
        }
    }
    
    if (isset($_POST['editar_carrera'])) {
        $id = (int)$_POST['id'];
        if (empty($nombre) || empty($codigo) || $semestres <= 0) {
            $error = "Nombre, código y semestres son obligatorios";
        } else {
            $check = $conn->query("SELECT id FROM carreras WHERE codigo = '$codigo' AND id != $id");
            if ($check->num_rows > 0) {
                $error = "El código de carrera ya está en uso";
            } else {
                $sql = "UPDATE carreras SET
                        nombre = '$nombre',
                        codigo = '$codigo',
                        semestres = $semestres,
                        estado = $estado
                        WHERE id = $id";
                
                if ($conn->query($sql)) {
                    $message = "Carrera actualizada correctamente";
                } else {
                    $error = "Error al actualizar: " . $conn->error;
                }
            }
        }
    }
}

// Eliminar carrera (soft delete)
if (isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];
    
    $check = $conn->query("SELECT id FROM materias WHERE carrera_id = $id AND estado = 1");
    if ($check->num_rows > 0) {
        $error = "No se puede eliminar: Tiene materias activas asociadas";
    } else {
        if ($conn->query("UPDATE carreras SET estado = 0 WHERE id = $id")) {
            $message = "Carrera eliminada correctamente";
        } else {
            $error = "Error al eliminar: " . $conn->error;
        }
    }
}

// Obtener carreras activas
$carreras = $conn->query("
    SELECT c.*, 
    (SELECT COUNT(*) FROM materias WHERE carrera_id = c.id AND estado = 1) as num_materias
    FROM carreras c 
    WHERE c.estado = 1
    ORDER BY c.nombre
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Gestión de Carreras</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 600px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            position: relative;
        }
        
        .close-modal {
            position: absolute;
            right: 20px;
            top: 10px;
            font-size: 30px;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
        }
    </style>
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
        
        <h1>Gestión de Carreras</h1>

        <?php if(isset($error)): ?>
            <script>
                Swal.fire({icon: 'error', title: 'Error', text: '<?= $error ?>'});
            </script>
        <?php endif; ?>
        
        <?php if(isset($message)): ?>
            <script>
                Swal.fire({icon: 'success', title: 'Éxito', text: '<?= $message ?>'});
            </script>
        <?php endif; ?>

        <button onclick="openModal('agregar')" class="btn-primary" style="margin-bottom: 20px;" type="button">Nueva Carrera</button>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Semestres</th>
                        <th>Materias</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($carreras as $c): ?>
                    <tr>
                        <td><?= $c['codigo'] ?></td>
                        <td><?= $c['nombre'] ?></td>
                        <td><?= $c['semestres'] ?></td>
                        <td><?= $c['num_materias'] ?></td>
                        <td><?= $c['estado'] ? 'Activa' : 'Inactiva' ?></td>
                        <td>
                            <button class="btn-edit" onclick="editarCarrera(<?= $c['id'] ?>, '<?= $c['nombre'] ?>', '<?= $c['codigo'] ?>', <?= $c['semestres'] ?>, <?= $c['estado'] ?>)">
                                Editar
                            </button>
                            <button class="btn-danger" onclick="confirmarEliminar(<?= $c['id'] ?>)">
                                Eliminar
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Agregar -->
    <div id="agregar" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('agregar')">&times;</span>
            <h2>Nueva Carrera</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Nombre: 
                        <input type="text" name="nombre" required>
                    </label>
                </div>
                <div class="form-group">
                    <label>Código: 
                        <input type="text" name="codigo" required>
                    </label>
                </div>
                <div class="form-group">
                    <label>Semestres: 
                        <input type="number" name="semestres" min="1" required>
                    </label>
                </div>
                <div class="form-group">
                    <label>Estado: 
                        <select name="estado" required>
                            <option value="1">Activa</option>
                            <option value="0">Inactiva</option>
                        </select>
                    </label>
                </div>
                <button type="submit" name="agregar_carrera" class="btn-primary">Guardar</button>
            </form>
        </div>
    </div>

    <!-- Modal Editar -->
    <div id="editar" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('editar')">&times;</span>
            <h2>Editar Carrera</h2>
            <form method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label>Nombre: 
                        <input type="text" name="nombre" id="edit_nombre" required>
                    </label>
                </div>
                <div class="form-group">
                    <label>Código: 
                        <input type="text" name="codigo" id="edit_codigo" required>
                    </label>
                </div>
                <div class="form-group">
                    <label>Semestres: 
                        <input type="number" name="semestres" id="edit_semestres" min="1" required>
                    </label>
                </div>
                <div class="form-group">
                    <label>Estado: 
                        <select name="estado" id="edit_estado" required>
                            <option value="1">Activa</option>
                            <option value="0">Inactiva</option>
                        </select>
                    </label>
                </div>
                <button type="submit" name="editar_carrera" class="btn-primary">Actualizar</button>
            </form>
        </div>
    </div>

    <script>
        // Funciones para modales
        function openModal(type) {
            document.getElementById(type).style.display = 'block';
        }

        function closeModal(type) {
            document.getElementById(type).style.display = 'none';
        }

        function editarCarrera(id, nombre, codigo, semestres, estado) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nombre').value = nombre;
            document.getElementById('edit_codigo').value = codigo;
            document.getElementById('edit_semestres').value = semestres;
            document.getElementById('edit_estado').value = estado;
            openModal('editar');
        }

        function confirmarEliminar(id) {
            Swal.fire({
                title: '¿Eliminar carrera?',
                text: "¡Esta acción no se puede revertir!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, eliminar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `?eliminar=${id}`;
                }
            });
        }

        // Cerrar modales al hacer clic fuera
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>