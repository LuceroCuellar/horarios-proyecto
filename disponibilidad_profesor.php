<?php include 'conexion.php'; 

// Verificar si se ha enviado un ID
if(!isset($_GET['id'])) {
    header("Location: disponibilidad_profesores.php");
    exit();
}

$profesor_id = $_GET['id'];

// Obtener los datos del profesor
$sql = "SELECT * FROM profesores WHERE id=$profesor_id";
$result = $conn->query($sql);

if ($result->num_rows === 0) {
    header("Location: disponibilidad_profesores.php?error=Profesor no encontrado");
    exit();
}

$profesor = $result->fetch_assoc();

// Obtener disponibilidad actual
$disponibilidadActual = [];
$sqlDisponibilidad = "SELECT * FROM disponibilidad_profesor WHERE profesor_id=$profesor_id";
$resultDisponibilidad = $conn->query($sqlDisponibilidad);

if ($resultDisponibilidad->num_rows > 0) {
    while($row = $resultDisponibilidad->fetch_assoc()) {
        $disponibilidadActual[] = [
            'dia' => $row['dia'],
            'inicio' => $row['hora_inicio'],
            'fin' => $row['hora_fin']
        ];
    }
}

// Procesar formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Eliminar disponibilidad anterior
    $sqlDelete = "DELETE FROM disponibilidad_profesor WHERE profesor_id=$profesor_id";
    $conn->query($sqlDelete);
    
    // Procesar nueva disponibilidad
    if(isset($_POST['disponibilidad']) && !empty($_POST['disponibilidad'])) {
        $disponibilidad = json_decode($_POST['disponibilidad'], true);
        
        foreach($disponibilidad as $horario) {
            $dia = $horario['dia'];
            $inicio = $horario['inicio'];
            $fin = $horario['fin'];
            
            $sqlInsert = "INSERT INTO disponibilidad_profesor (profesor_id, dia, hora_inicio, hora_fin) 
                         VALUES ($profesor_id, '$dia', '$inicio', '$fin')";
            $conn->query($sqlInsert);
        }
        
        header("Location: disponibilidad_profesores.php?message=Disponibilidad registrada correctamente");
        exit();
    } else {
        $error = "No se ha registrado ninguna disponibilidad";
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Disponibilidad del Profesor</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="container">
        <div class="nav-menu">
            <a href="index.php">Inicio</a>
            <a href="disponibilidad_profesores.php">Volver a Disponibilidad</a>
        </div>
        
        <h1>Registro de Disponibilidad</h1>
        
        <?php if(isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="form-group">
            <h2>Profesor: <?php echo $profesor['nombre'] . ' ' . $profesor['apellidos']; ?></h2>
            <p>Horas disponibles semanales: <strong><?php echo $profesor['horas_disponibles']; ?></strong></p>
        </div>
        
        <form method="POST" action="">
            <div class="container-flex">
                <div style="flex: 2">
                    <h3>Días Laborales</h3>
                    <?php
                    $dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];
                    foreach($dias as $dia) {
                        // Verificar si el día ya tiene disponibilidad
                        $diaConDisponibilidad = false;
                        foreach($disponibilidadActual as $disp) {
                            if($disp['dia'] === $dia) {
                                $diaConDisponibilidad = true;
                                break;
                            }
                        }
                        
                        echo '<div class="day-section">
                                <label>
                                    <input type="checkbox" class="day-checkbox" data-dia="'.$dia.'" ' . ($diaConDisponibilidad ? 'checked' : '') . '>
                                    '.$dia.'
                                </label>
                                <div class="time-entries" id="'.$dia.'-entries" style="margin-top: 10px; display: ' . ($diaConDisponibilidad ? 'block' : 'none') . '">';
                                
                        // Si ya hay disponibilidad para este día, mostrarla
                        if($diaConDisponibilidad) {
                            foreach($disponibilidadActual as $disp) {
                                if($disp['dia'] === $dia) {
                                    echo '<div class="time-entry">
                                            <input type="time" class="hora-inicio" value="' . $disp['inicio'] . '">
                                            <span>a</span>
                                            <input type="time" class="hora-fin" value="' . $disp['fin'] . '">
                                            <button type="button" class="btn-delete" onclick="removeTimeSlot(this)">×</button>
                                          </div>';
                                }
                            }
                        }
                        
                        echo '<div class="time-entry">
                                <input type="time" class="hora-inicio">
                                <span>a</span>
                                <input type="time" class="hora-fin">
                                <button type="button" class="add-time-btn" onclick="addTimeSlot(this)">+</button>
                                </div>';
                      }
                      ?>
                  </div>
                  
                  <div style="flex: 1">
                      <h3>Resumen de Disponibilidad</h3>
                      <div id="resumen-disponibilidad">
                          <p>No hay disponibilidad registrada</p>
                      </div>
                  </div>
              </div>
              
              <input type="hidden" name="disponibilidad" id="disponibilidad-json">
              
              <div class="form-group" style="margin-top: 20px;">
                  <button type="submit" class="btn btn-primary">Guardar Disponibilidad</button>
              </div>
          </form>
      </div>
      
      <script>
          // Variables globales
          let disponibilidad = <?php echo !empty($disponibilidadActual) ? json_encode($disponibilidadActual) : '[]'; ?>;
          
          // Inicialización
          document.addEventListener('DOMContentLoaded', function() {
              // Configurar checkboxes
              const checkboxes = document.querySelectorAll('.day-checkbox');
              checkboxes.forEach(checkbox => {
                  checkbox.addEventListener('change', function() {
                      const dia = this.getAttribute('data-dia');
                      const entriesDiv = document.getElementById(dia + '-entries');
                      entriesDiv.style.display = this.checked ? 'block' : 'none';
                      
                      // Si se desmarca, eliminar disponibilidad de ese día
                      if (!this.checked) {
                          disponibilidad = disponibilidad.filter(d => d.dia !== dia);
                          actualizarResumenDisponibilidad();
                      }
                  });
              });
              
              // Cargar datos iniciales
              actualizarResumenDisponibilidad();
          });
          
          // Agregar nuevo horario
          function addTimeSlot(btn) {
              const timeEntryDiv = btn.parentElement;
              const parentDiv = timeEntryDiv.parentElement;
              const dayDiv = parentDiv.parentElement;
              const dayCheckbox = dayDiv.querySelector('.day-checkbox');
              const dia = dayCheckbox.getAttribute('data-dia');
              
              const horaInicio = timeEntryDiv.querySelector('.hora-inicio').value;
              const horaFin = timeEntryDiv.querySelector('.hora-fin').value;
              
              // Validar que ambos campos tengan valores
              if (!horaInicio || !horaFin) {
                  Swal.fire({
                      icon: 'error',
                      title: 'Error',
                      text: 'Debe seleccionar hora de inicio y fin'
                  });
                  return;
              }
              
              // Validar que la hora de fin sea mayor que la de inicio
              if (horaInicio >= horaFin) {
                  Swal.fire({
                      icon: 'error',
                      title: 'Error',
                      text: 'La hora de fin debe ser mayor que la hora de inicio'
                  });
                  return;
              }
              
              // Verificar si hay solapamiento con otros horarios del mismo día
              const solapado = disponibilidad.some(d => {
                  if (d.dia !== dia) return false;
                  
                  return (horaInicio < d.fin && horaFin > d.inicio);
              });
              
              if (solapado) {
                  Swal.fire({
                      icon: 'error',
                      title: 'Error',
                      text: 'El horario se solapa con otro ya registrado'
                  });
                  return;
              }
              
              // Agregar a la colección de disponibilidad
              disponibilidad.push({
                  dia: dia,
                  inicio: horaInicio,
                  fin: horaFin
              });
              
              // Crear nuevo elemento visual
              const newTimeEntry = document.createElement('div');
              newTimeEntry.className = 'time-entry';
              newTimeEntry.innerHTML = `
                  <input type="time" class="hora-inicio" value="${horaInicio}" readonly>
                  <span>a</span>
                  <input type="time" class="hora-fin" value="${horaFin}" readonly>
                  <button type="button" class="btn-delete" onclick="removeTimeSlot(this)">×</button>
              `;
              
              // Insertar antes del último elemento (que es el formulario para agregar)
              parentDiv.insertBefore(newTimeEntry, timeEntryDiv);
              
              // Limpiar el formulario
              timeEntryDiv.querySelector('.hora-inicio').value = '';
              timeEntryDiv.querySelector('.hora-fin').value = '';
              
              // Actualizar resumen
              actualizarResumenDisponibilidad();
          }
          
          // Eliminar horario
          function removeTimeSlot(btn) {
              const timeEntryDiv = btn.parentElement;
              const parentDiv = timeEntryDiv.parentElement;
              const dayDiv = parentDiv.parentElement;
              const dayCheckbox = dayDiv.querySelector('.day-checkbox');
              const dia = dayCheckbox.getAttribute('data-dia');
              
              const horaInicio = timeEntryDiv.querySelector('.hora-inicio').value;
              const horaFin = timeEntryDiv.querySelector('.hora-fin').value;
              
              // Eliminar de la colección
              disponibilidad = disponibilidad.filter(d => 
                  !(d.dia === dia && d.inicio === horaInicio && d.fin === horaFin)
              );
              
              // Eliminar elemento visual
              timeEntryDiv.remove();
              
              // Actualizar resumen
              actualizarResumenDisponibilidad();
              
              // Si no hay más horarios para ese día, desmarcar el checkbox
              const tieneHorarios = disponibilidad.some(d => d.dia === dia);
              if (!tieneHorarios) {
                  dayCheckbox.checked = false;
                  parentDiv.style.display = 'none';
              }
          }
          
          // Actualizar resumen y campo oculto para envío
          function actualizarResumenDisponibilidad() {
              // Ordenar por día y hora de inicio
              disponibilidad.sort((a, b) => {
                  const dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];
                  const indexA = dias.indexOf(a.dia);
                  const indexB = dias.indexOf(b.dia);
                  
                  if (indexA !== indexB) return indexA - indexB;
                  return a.inicio.localeCompare(b.inicio);
              });
              
              // Actualizar resumen visual
              const resumenDiv = document.getElementById('resumen-disponibilidad');
              
              if (disponibilidad.length === 0) {
                  resumenDiv.innerHTML = '<p>No hay disponibilidad registrada</p>';
              } else {
                  let html = '<ul>';
                  let diaActual = '';
                  
                  disponibilidad.forEach(d => {
                      if (d.dia !== diaActual) {
                          if (diaActual !== '') {
                              html += '</ul></li>';
                          }
                          html += `<li><strong>${d.dia}</strong><ul>`;
                          diaActual = d.dia;
                      }
                      
                      html += `<li>${d.inicio} a ${d.fin}</li>`;
                  });
                  
                  html += '</ul></li></ul>';
                  resumenDiv.innerHTML = html;
              }
              
              // Actualizar campo oculto para envío
              document.getElementById('disponibilidad-json').value = JSON.stringify(disponibilidad);
          }
      </script>
  </body>
  </html>