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
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Fuente Montserrat para el modal -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include 'header.php'; ?>
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
            <div class="page-header">
                <h1>Gestión de Carreras</h1>
            </div>

            <div class="container">
                <!-- Notificaciones -->
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

                <button onclick="openModal('agregar')" class="btn-primary" style="margin-bottom: 20px;" type="button">
                    <i class="fas fa-plus-circle"></i> Nueva Carrera
                </button>

                <div class="card">
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Nombre</th>
                                        <th>Cuatrimestres</th>
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
                                        <td>
                                            <?php if($c['estado']): ?>
                                                <span class="badge badge-success">Activa</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Inactiva</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn-edit" onclick="editarCarrera(<?= $c['id'] ?>, '<?= $c['nombre'] ?>', '<?= $c['codigo'] ?>', <?= $c['semestres'] ?>, <?= $c['estado'] ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-danger" onclick="confirmarEliminar(<?= $c['id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Incluir el footer -->
        <?php include 'footer.php'; ?>
    </div>

    <!-- Modal Agregar -->
    <div id="agregar" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('agregar')">&times;</span>
            <h2>Nueva Carrera</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="nombre">Nombre:</label>
                    <input type="text" name="nombre" id="nombre" required>
                </div>
                <div class="form-group">
                    <label for="codigo">Código:</label>
                    <input type="text" name="codigo" id="codigo" required>
                </div>
                <div class="form-group">
                    <label for="semestres">Cuatrimestres:</label>
                    <input type="number" name="semestres" id="semestres" min="1" required>
                </div>
                <div class="form-group">
                    <label for="estado">Estado:</label>
                    <select name="estado" id="estado" required>
                        <option value="1">Activa</option>
                        <option value="0">Inactiva</option>
                    </select>
                </div>
                <button type="submit" name="agregar_carrera" class="btn-primary">
                    <i class="fas fa-save"></i> Guardar
                </button>
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
                    <label for="edit_nombre">Nombre:</label>
                    <input type="text" name="nombre" id="edit_nombre" required>
                </div>
                <div class="form-group">
                    <label for="edit_codigo">Código:</label>
                    <input type="text" name="codigo" id="edit_codigo" required>
                </div>
                <div class="form-group">
                    <label for="edit_semestres">Cuatrimestres:</label>
                    <input type="number" name="semestres" id="edit_semestres" min="1" required>
                </div>
                <div class="form-group">
                    <label for="edit_estado">Estado:</label>
                    <select name="estado" id="edit_estado" required>
                        <option value="1">Activa</option>
                        <option value="0">Inactiva</option>
                    </select>
                </div>
                <button type="submit" name="editar_carrera" class="btn-primary">
                    <i class="fas fa-save"></i> Actualizar
                </button>
            </form>
        </div>
    </div>

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
                        contentWrapper.style.marginLeft = '270px';
                        if (mainFooter) mainFooter.style.marginLeft = '270px';
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
                        contentWrapper.style.marginLeft = '270px';
                        if (mainFooter) mainFooter.style.marginLeft = '270px';
                    } else {
                        contentWrapper.style.marginLeft = '0';
                        if (mainFooter) mainFooter.style.marginLeft = '0';
                    }
                }
            });
        });
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