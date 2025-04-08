<?php
include 'conexion.php';

// Procesar formularios
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validaciones comunes
    $nombre = $conn->real_escape_string(trim($_POST['nombre'] ?? ''));
    $semestre = (int)($_POST['semestre'] ?? 0);
    $carrera_id = (int)($_POST['carrera_id'] ?? 0);
    $periodo = $conn->real_escape_string(trim($_POST['periodo'] ?? ''));
    $anio = (int)($_POST['anio'] ?? date('Y'));
    $turno = $conn->real_escape_string(trim($_POST['turno'] ?? 'matutino'));
    $aula = $conn->real_escape_string(trim($_POST['aula'] ?? ''));

    if (isset($_POST['agregar_grupo'])) {
        if (empty($nombre) || $semestre <= 0 || $carrera_id <= 0 || empty($periodo)) {
            $error = "Nombre, cuatrimestre, carrera y periodo son obligatorios";
        } else {
            $check = $conn->query("SELECT id FROM grupos WHERE nombre = '$nombre' AND periodo = '$periodo' AND anio = $anio");
            if ($check->num_rows > 0) {
                $error = "Ya existe un grupo con este nombre en el mismo periodo y año";
            } else {
                $sql = "INSERT INTO grupos (nombre, semestre, carrera_id, periodo, anio, turno, aula)
                        VALUES ('$nombre', $semestre, $carrera_id, '$periodo', $anio, '$turno', '$aula')";
                
                if ($conn->query($sql)) {
                    $message = "Grupo agregado correctamente";
                } else {
                    $error = "Error al agregar: " . $conn->error;
                }
            }
        }
    }
    
    if (isset($_POST['editar_grupo'])) {
        $id = (int)$_POST['id'];
        if (empty($nombre) || $semestre <= 0 || $carrera_id <= 0 || empty($periodo)) {
            $error = "Nombre, cuatrimestre, carrera y periodo son obligatorios";
        } else {
            $check = $conn->query("SELECT id FROM grupos WHERE nombre = '$nombre' AND periodo = '$periodo' AND anio = $anio AND id != $id");
            if ($check->num_rows > 0) {
                $error = "Ya existe otro grupo con este nombre en el mismo periodo y año";
            } else {
                $sql = "UPDATE grupos SET
                        nombre = '$nombre',
                        semestre = $semestre,
                        carrera_id = $carrera_id,
                        periodo = '$periodo',
                        anio = $anio,
                        turno = '$turno',
                        aula = '$aula'
                        WHERE id = $id";
                
                if ($conn->query($sql)) {
                    $message = "Grupo actualizado correctamente";
                } else {
                    $error = "Error al actualizar: " . $conn->error;
                }
            }
        }
    }
}

// Eliminar grupo
if (isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];
    
    $check = $conn->query("SELECT id FROM horarios WHERE grupo_id = $id");
    if ($check->num_rows > 0) {
        $error = "No se puede eliminar: Existen horarios relacionados";
    } else {
        if ($conn->query("DELETE FROM grupos WHERE id = $id")) {
            $message = "Grupo eliminado correctamente";
        } else {
            $error = "Error al eliminar: " . $conn->error;
        }
    }
}

// Obtener datos
$carreras = $conn->query("SELECT * FROM carreras WHERE estado = 1")->fetch_all(MYSQLI_ASSOC);
$grupos = $conn->query("
    SELECT g.*, c.nombre as carrera 
    FROM grupos g
    JOIN carreras c ON g.carrera_id = c.id
    ORDER BY g.anio DESC, g.periodo, g.nombre
")->fetch_all(MYSQLI_ASSOC);

// Opciones para los selects
$periodos = ['Enero-Abril', 'Mayo-Agosto', 'Septiembre-Diciembre'];
$turnos = ['matutino', 'vespertino'];
?>

<!DOCTYPE html>
<html>
<head>
    <?php include 'header.php'; ?>
</head>
<body>
    <button class="sidebar-toggle" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>

    <?php include 'nav.php'; ?>

    <div class="page-wrapper">
        <div class="content-wrapper">
            <div class="page-header">
                <h1>Gestión de Grupos</h1>
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
                    <i class="fas fa-plus-circle"></i> Nuevo Grupo
                </button>

                <div class="card">
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Cuatrimestre</th>
                                        <th>Carrera</th>
                                        <th>Periodo</th>
                                        <th>Año</th>
                                        <th>Turno</th>
                                        <th>Aula</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($grupos as $g): ?>
                                    <tr>
                                        <td><?= $g['nombre'] ?></td>
                                        <td><?= $g['semestre'] ?></td>
                                        <td><?= $g['carrera'] ?></td>
                                        <td><?= $g['periodo'] ?></td>
                                        <td><?= $g['anio'] ?></td>
                                        <td><?= ucfirst($g['turno']) ?></td>
                                        <td><?= $g['aula'] ?? 'Sin asignar' ?></td>
                                        <td>
                                            <button class="btn-edit" onclick="editarGrupo(
                                                <?= $g['id'] ?>, 
                                                '<?= $g['nombre'] ?>', 
                                                <?= $g['semestre'] ?>, 
                                                <?= $g['carrera_id'] ?>, 
                                                '<?= $g['periodo'] ?>', 
                                                <?= $g['anio'] ?>, 
                                                '<?= $g['turno'] ?>', 
                                                '<?= $g['aula'] ?>'
                                            )">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-danger" onclick="confirmarEliminar(<?= $g['id'] ?>)">
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

        <?php include 'footer.php'; ?>
    </div>

    <!-- Modal Agregar -->
    <div id="agregar" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('agregar')">&times;</span>
            <h2>Nuevo Grupo</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="nombre">Nombre del grupo:</label>
                    <input type="text" name="nombre" id="nombre" required>
                </div>
                <div class="form-group">
                    <label for="semestre">Cuatrimestre:</label>
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
                    <label for="periodo">Periodo:</label>
                    <select name="periodo" id="periodo" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($periodos as $p): ?>
                        <option value="<?= $p ?>"><?= $p ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="anio">Año:</label>
                    <input type="number" name="anio" id="anio" min="2000" max="2100" value="<?= date('Y') ?>" required>
                </div>
                <div class="form-group">
                    <label for="turno">Turno:</label>
                    <select name="turno" id="turno" required>
                        <?php foreach ($turnos as $t): ?>
                        <option value="<?= $t ?>"><?= ucfirst($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="aula">Aula (opcional):</label>
                    <input type="text" name="aula" id="aula">
                </div>
                <button type="submit" name="agregar_grupo" class="btn-primary">
                    <i class="fas fa-save"></i> Guardar
                </button>
            </form>
        </div>
    </div>

    <!-- Modal Editar -->
    <div id="editar" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('editar')">&times;</span>
            <h2>Editar Grupo</h2>
            <form method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label for="edit_nombre">Nombre del grupo:</label>
                    <input type="text" name="nombre" id="edit_nombre" required>
                </div>
                <div class="form-group">
                    <label for="edit_semestre">Cuatrimestre:</label>
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
                    <label for="edit_periodo">Periodo:</label>
                    <select name="periodo" id="edit_periodo" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($periodos as $p): ?>
                        <option value="<?= $p ?>"><?= $p ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_anio">Año:</label>
                    <input type="number" name="anio" id="edit_anio" min="2000" max="2100" required>
                </div>
                <div class="form-group">
                    <label for="edit_turno">Turno:</label>
                    <select name="turno" id="edit_turno" required>
                        <?php foreach ($turnos as $t): ?>
                        <option value="<?= $t ?>"><?= ucfirst($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_aula">Aula (opcional):</label>
                    <input type="text" name="aula" id="edit_aula">
                </div>
                <button type="submit" name="editar_grupo" class="btn-primary">
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

        function editarGrupo(id, nombre, semestre, carrera_id, periodo, anio, turno, aula) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nombre').value = nombre;
            document.getElementById('edit_semestre').value = semestre;
            document.getElementById('edit_carrera').value = carrera_id;
            document.getElementById('edit_periodo').value = periodo;
            document.getElementById('edit_anio').value = anio;
            document.getElementById('edit_turno').value = turno;
            document.getElementById('edit_aula').value = aula || '';
            openModal('editar');
        }

        function confirmarEliminar(id) {
            Swal.fire({
                title: '¿Eliminar grupo?',
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