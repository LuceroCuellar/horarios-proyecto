<?php
include 'conexion.php';

// Procesar formularios
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validaciones comunes
    $nombre = $conn->real_escape_string(trim($_POST['nombre'] ?? ''));
    $codigo = $conn->real_escape_string(trim($_POST['codigo'] ?? ''));
    $horas = (int)($_POST['horas_semanales'] ?? 0);
    $semestre = (int)($_POST['semestre'] ?? 0);
    $carrera_id = (int)($_POST['carrera_id'] ?? 0);
    $estado = isset($_POST['estado']) ? 1 : 0;
    $departamento_id = (int)($_POST['departamento_id'] ?? 0);

    if (isset($_POST['agregar_materia'])) {
        if (empty($nombre) || empty($codigo) || $horas <= 0 || $semestre <= 0) {
            $error = "Todos los campos son obligatorios";
        } else {
            $check = $conn->query("SELECT id FROM materias WHERE codigo = '$codigo'");
            if ($check->num_rows > 0) {
                $error = "El código de materia ya existe";
            } else {
                // Insertar la materia
                $sql = "INSERT INTO materias (codigo, nombre, horas_semanales, semestre, carrera_id, estado)
                        VALUES ('$codigo', '$nombre', $horas, $semestre, $carrera_id, $estado)";
                
                if ($conn->query($sql)) {
                    $materia_id = $conn->insert_id; // Obtener el ID de la materia insertada

                    // Insertar la relación en materias_departamentos si se seleccionó un departamento
                    if ($departamento_id > 0) {
                        $sql_relacion = "INSERT INTO materias_departamentos (materia_id, departamento_id)
                                         VALUES ($materia_id, $departamento_id)";
                        if (!$conn->query($sql_relacion)) {
                            $error = "Error al asociar el departamento: " . $conn->error;
                        }
                    }

                    $message = "Materia agregada correctamente";
                } else {
                    $error = "Error al agregar: " . $conn->error;
                }
            }
        }
    }
    
    if (isset($_POST['editar_materia'])) {
        $id = (int)$_POST['id'];
        if (empty($nombre) || empty($codigo) || $horas <= 0 || $semestre <= 0) {
            $error = "Todos los campos son obligatorios";
        } else {
            $check = $conn->query("SELECT id FROM materias WHERE codigo = '$codigo' AND id != $id");
            if ($check->num_rows > 0) {
                $error = "El código de materia ya está en uso";
            } else {
                // Actualizar la materia
                $sql = "UPDATE materias SET
                        codigo = '$codigo',
                        nombre = '$nombre',
                        horas_semanales = $horas,
                        semestre = $semestre,
                        carrera_id = $carrera_id,
                        estado = $estado
                        WHERE id = $id";
                
                if ($conn->query($sql)) {
                    // Actualizar la relación en materias_departamentos
                    $conn->query("DELETE FROM materias_departamentos WHERE materia_id = $id"); // Eliminar relación existente
                    if ($departamento_id > 0) {
                        $sql_relacion = "INSERT INTO materias_departamentos (materia_id, departamento_id)
                                         VALUES ($id, $departamento_id)";
                        if (!$conn->query($sql_relacion)) {
                            $error = "Error al actualizar el departamento: " . $conn->error;
                        }
                    }

                    $message = "Materia actualizada correctamente";
                } else {
                    $error = "Error al actualizar: " . $conn->error;
                }
            }
        }
    }
}

// Eliminar materia (soft delete)
if (isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];
    
    $check = $conn->query("SELECT id FROM horarios WHERE materia_id = $id");
    if ($check->num_rows > 0) {
        $error = "No se puede eliminar: Existen horarios relacionados";
    } else {
        // Eliminar la relación con el departamento
        $conn->query("DELETE FROM materias_departamentos WHERE materia_id = $id");
        
        // Soft delete de la materia
        if ($conn->query("UPDATE materias SET estado = 0 WHERE id = $id")) {
            $message = "Materia eliminada correctamente";
        } else {
            $error = "Error al eliminar: " . $conn->error;
        }
    }
}

// Obtener datos
$carreras = $conn->query("SELECT * FROM carreras WHERE estado = 1")->fetch_all(MYSQLI_ASSOC);
$departamentos = $conn->query("SELECT * FROM departamentos WHERE estado = 1")->fetch_all(MYSQLI_ASSOC);
$materias = $conn->query("
    SELECT m.*, c.nombre as carrera, d.nombre as departamento, md.departamento_id
    FROM materias m
    LEFT JOIN carreras c ON m.carrera_id = c.id
    LEFT JOIN materias_departamentos md ON m.id = md.materia_id
    LEFT JOIN departamentos d ON md.departamento_id = d.id
    WHERE m.estado = 1
    ORDER BY m.nombre
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
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
            <!-- Incluir el header -->
                        <!-- Header de la página -->
            <div class="page-header">
                <h1>Gestión de Materias</h1>
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
                    <i class="fas fa-plus-circle"></i> Nueva Materia
                </button>

                <div class="card">
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Nombre</th>
                                        <th>Horas</th>
                                        <th>Cuatrimestres</th>
                                        <th>Carrera</th>
                                        <th>Estado</th>
                                        <th>Departamento</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($materias as $m): ?>
                                    <tr>
                                        <td><?= $m['codigo'] ?></td>
                                        <td><?= $m['nombre'] ?></td>
                                        <td><?= $m['horas_semanales'] ?></td>
                                        <td><?= $m['semestre'] ?></td>
                                        <td><?= $m['carrera'] ?? 'Sin asignar' ?></td>
                                        <td>
                                            <?php if($m['estado']): ?>
                                                <span class="badge badge-success">Activa</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Inactiva</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $m['departamento'] ?? 'Sin asignar' ?></td>
                                        <td>
                                            <button class="btn-edit" onclick="editarMateria(<?= $m['id'] ?>, '<?= $m['codigo'] ?>', '<?= $m['nombre'] ?>', <?= $m['horas_semanales'] ?>, <?= $m['semestre'] ?>, <?= $m['carrera_id'] ?>, <?= $m['estado'] ?>, <?= $m['departamento_id'] ?? 'null' ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-danger" onclick="confirmarEliminar(<?= $m['id'] ?>)">
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
            <h2>Nueva Materia</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="codigo">Código:</label>
                    <input type="text" name="codigo" id="codigo" required>
                </div>
                <div class="form-group">
                    <label for="nombre">Nombre:</label>
                    <input type="text" name="nombre" id="nombre" required>
                </div>
                <div class="form-group">
                    <label for="horas_semanales">Horas Semanales:</label>
                    <input type="number" name="horas_semanales" id="horas_semanales" min="1" required>
                </div>
                <div class="form-group">
                    <label for="semestre">Cuatrimestres:</label>
                    <input type="number" name="semestre" id="semestre" min="1" required>
                </div>
                <div class="form-group">
                    <label for="carrera_id">Carrera:</label>
                    <select name="carrera_id" id="carrera_id" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($carreras as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= $c['nombre'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="estado">Estado:</label>
                    <select name="estado" id="estado" required>
                        <option value="1">Activa</option>
                        <option value="0">Inactiva</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="departamento_id">Departamento:</label>
                    <select name="departamento_id" id="departamento_id">
                        <option value="">Seleccionar...</option>
                        <?php foreach ($departamentos as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= $d['nombre'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="agregar_materia" class="btn-primary">
                    <i class="fas fa-save"></i> Guardar
                </button>
            </form>
        </div>
    </div>

    <!-- Modal Editar -->
    <div id="editar" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('editar')">&times;</span>
            <h2>Editar Materia</h2>
            <form method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label for="edit_codigo">Código:</label>
                    <input type="text" name="codigo" id="edit_codigo" required>
                </div>
                <div class="form-group">
                    <label for="edit_nombre">Nombre:</label>
                    <input type="text" name="nombre" id="edit_nombre" required>
                </div>
                <div class="form-group">
                    <label for="edit_horas">Horas Semanales:</label>
                    <input type="number" name="horas_semanales" id="edit_horas" min="1" required>
                </div>
                <div class="form-group">
                    <label for="edit_semestre">Cuatrimestres:</label>
                    <input type="number" name="semestre" id="edit_semestre" min="1" required>
                </div>
                <div class="form-group">
                    <label for="edit_carrera">Carrera:</label>
                    <select name="carrera_id" id="edit_carrera" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($carreras as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= $c['nombre'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_estado">Estado:</label>
                    <select name="estado" id="edit_estado" required>
                        <option value="1">Activa</option>
                        <option value="0">Inactiva</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_departamento">Departamento:</label>
                    <select name="departamento_id" id="edit_departamento">
                        <option value="">Seleccionar...</option>
                        <?php foreach ($departamentos as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= $d['nombre'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="editar_materia" class="btn-primary">
                    <i class="fas fa-save"></i> Actualizar
                </button>
            </form>
        </div>
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

        function editarMateria(id, codigo, nombre, horas, semestre, carrera_id, estado, departamento_id) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_codigo').value = codigo;
            document.getElementById('edit_nombre').value = nombre;
            document.getElementById('edit_horas').value = horas;
            document.getElementById('edit_semestre').value = semestre;
            document.getElementById('edit_carrera').value = carrera_id;
            document.getElementById('edit_estado').value = estado;
            document.getElementById('edit_departamento').value = departamento_id || '';
            openModal('editar');
        }

        function confirmarEliminar(id) {
            Swal.fire({
                title: '¿Eliminar materia?',
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