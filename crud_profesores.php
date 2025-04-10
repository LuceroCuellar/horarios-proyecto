<?php include 'conexion.php';

// Eliminar profesor
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    // Eliminación lógica (cambiar estado a 0)
    $sql = "UPDATE profesores SET estado = 0 WHERE id = $id";

    if ($conn->query($sql) === TRUE) {
        $message = "Profesor eliminado correctamente";
    } else {
        $error = "Error al eliminar: " . $conn->error;
    }
}

// Agregar profesor
if (isset($_POST['agregar_profesor'])) {
    $nombre = $_POST['nombre'];
    $apellidos = $_POST['apellidos'];
    $email = $_POST['email'];
    $telefono = $_POST['telefono'];
    $horas_disponibles = $_POST['horas_disponibles'];

    $sql = "INSERT INTO profesores (nombre, apellidos, email, telefono, horas_disponibles) 
            VALUES ('$nombre', '$apellidos', '$email', '$telefono', $horas_disponibles)";

    if ($conn->query($sql) === TRUE) {
        $message = "Profesor agregado correctamente";
    } else {
        $error = "Error al agregar: " . $conn->error;
    }
}

// Actualizar profesor
if (isset($_POST['actualizar_profesor'])) {
    $id = $_POST['profesor_id'];
    $nombre = $_POST['nombre'];
    $apellidos = $_POST['apellidos'];
    $email = $_POST['email'];
    $telefono = $_POST['telefono'];
    $horas_disponibles = $_POST['horas_disponibles'];

    $sql = "UPDATE profesores SET nombre='$nombre', apellidos='$apellidos', 
            email='$email', telefono='$telefono', horas_disponibles=$horas_disponibles 
            WHERE id=$id";

    if ($conn->query($sql) === TRUE) {
        $message = "Profesor actualizado correctamente";
    } else {
        $error = "Error al actualizar: " . $conn->error;
    }
}

// Recuperar información del profesor para editar
$profesorEditar = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $sql = "SELECT * FROM profesores WHERE id=$id";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $profesorEditar = $result->fetch_assoc();
    }
}

// Mensajes de confirmación
if (isset($_GET['message'])) {
    $message = $_GET['message'];
}

if (isset($_GET['error'])) {
    $error = $_GET['error'];
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Gestión de Profesores</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">

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
                <h1>Gestión de Profesores</h1>
            </div>

            <div class="container">
                <button type="button" class="btn-primary" onclick="openModal('agregar-modal')" style="margin-bottom: 20px;">
                    <i class="fas fa-user-plus"></i> Agregar Nuevo Profesor
                </button>

                <div class="card">
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Apellidos</th>
                                        <th>Email</th>
                                        <th>Teléfono</th>
                                        <th>Horas Disponibles</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sql = "SELECT * FROM profesores WHERE estado = 1";
                                    $result = $conn->query($sql);

                                    if ($result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            echo "<tr>";
                                            echo "<td>" . $row["id"] . "</td>";
                                            echo "<td>" . $row["nombre"] . "</td>";
                                            echo "<td>" . $row["apellidos"] . "</td>";
                                            echo "<td>" . $row["email"] . "</td>";
                                            echo "<td>" . $row["telefono"] . "</td>";
                                            echo "<td>" . $row["horas_disponibles"] . "</td>";
                                            echo "<td>
                                                <button onclick='editarProfesor(" . $row["id"] . ", \"" . $row["nombre"] . "\", \"" . $row["apellidos"] . "\", \"" . $row["email"] . "\", \"" . $row["telefono"] . "\", " . $row["horas_disponibles"] . ")' class='btn-edit'><i class='fas fa-edit'></i></button>
                                                <button onclick='confirmarEliminar(" . $row["id"] . ")' class='btn-danger'><i class='fas fa-trash'></i></button>
                                                <a href='asignar_materias.php?id=" . $row["id"] . "' class='btn-view'><i class='fas fa-book'></i></a>
                                                <a href='disponibilidad_profesor.php?id=" . $row["id"] . "' class='btn-success'><i class='fas fa-calendar-alt'></i></a>
                                            </td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='7'>No hay profesores registrados</td></tr>";
                                    }
                                    ?>
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

    <!-- Modal para agregar profesor -->
    <div id="agregar-modal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('agregar-modal')">&times;</span>
            <h2>Agregar Nuevo Profesor</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="nombre">Nombre:</label>
                    <input type="text" id="nombre" name="nombre" required>
                </div>

                <div class="form-group">
                    <label for="apellidos">Apellidos:</label>
                    <input type="text" id="apellidos" name="apellidos" required>
                </div>

                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email">
                </div>

                <div class="form-group">
                    <label for="telefono">Teléfono:</label>
                    <input type="tel" id="telefono" name="telefono">
                </div>

                <div class="form-group">
                    <label for="horas_disponibles">Horas Disponibles Semanales:</label>
                    <input type="number" id="horas_disponibles" name="horas_disponibles" min="1" max="50" required>
                </div>

                <input type="hidden" name="agregar_profesor" value="1">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Guardar Profesor
                </button>
            </form>
        </div>
    </div>

    <!-- Modal para editar profesor -->
    <div id="editar-modal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('editar-modal')">&times;</span>
            <h2>Editar Profesor</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="edit_nombre">Nombre:</label>
                    <input type="text" id="edit_nombre" name="nombre" required>
                </div>

                <div class="form-group">
                    <label for="edit_apellidos">Apellidos:</label>
                    <input type="text" id="edit_apellidos" name="apellidos" required>
                </div>

                <div class="form-group">
                    <label for="edit_email">Email:</label>
                    <input type="email" id="edit_email" name="email">
                </div>

                <div class="form-group">
                    <label for="edit_telefono">Teléfono:</label>
                    <input type="tel" id="edit_telefono" name="telefono">
                </div>

                <div class="form-group">
                    <label for="edit_horas_disponibles">Horas Disponibles Semanales:</label>
                    <input type="number" id="edit_horas_disponibles" name="horas_disponibles" min="1" max="50" required>
                </div>

                <input type="hidden" name="profesor_id" id="edit_profesor_id">
                <input type="hidden" name="actualizar_profesor" value="1">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Actualizar Profesor
                </button>
            </form>
        </div>
    </div>

    <script>
        // Funciones para manejar los modales
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Función para editar profesor
        function editarProfesor(id, nombre, apellidos, email, telefono, horas) {
            document.getElementById('edit_profesor_id').value = id;
            document.getElementById('edit_nombre').value = nombre;
            document.getElementById('edit_apellidos').value = apellidos;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_telefono').value = telefono;
            document.getElementById('edit_horas_disponibles').value = horas;

            openModal('editar-modal');
        }

        // Función para confirmar eliminación
        function confirmarEliminar(id) {
            Swal.fire({
                title: '¿Estás seguro?',
                text: "Esta acción no se puede revertir",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3f51b5',
                cancelButtonColor: '#f44336',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'crud_profesores.php?delete=' + id;
                }
            });
        }

        // Mostrar mensajes con SweetAlert2
        <?php if (isset($message)): ?>
            Swal.fire({
                title: 'Éxito',
                text: '<?php echo $message; ?>',
                icon: 'success',
                confirmButtonColor: '#3f51b5'
            });
        <?php endif; ?>

        <?php if (isset($error)): ?>
            Swal.fire({
                title: 'Error',
                text: '<?php echo $error; ?>',
                icon: 'error',
                confirmButtonColor: '#3f51b5'
            });
        <?php endif; ?>

        // Cerrar modales al hacer clic fuera de ellos
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }

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