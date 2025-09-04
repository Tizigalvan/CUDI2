<?php
include 'conexion.php';
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

// Obtener término de búsqueda
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

// Consulta de carreras con unión a universidades (incluyendo acrónimo)
$sql = "SELECT c.id_carrera, c.nombre AS carrera, u.nombre AS universidad, u.acronimo
        FROM carreras c
        LEFT JOIN universidades u ON c.universidad_id = u.id_universidad";

if (!empty($busqueda)) {
    $busqueda_escaped = $conn->real_escape_string($busqueda);
    $sql .= " WHERE c.nombre LIKE '%$busqueda_escaped%' 
              OR u.nombre LIKE '%$busqueda_escaped%' 
              OR u.acronimo LIKE '%$busqueda_escaped%'";
}

$sql .= " ORDER BY c.nombre";
$carreras = $conn->query($sql);

// Obtener universidades para el select
$universidades = $conn->query("SELECT id_universidad, nombre, acronimo FROM universidades ORDER BY nombre");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Carreras</title>
    <link rel="stylesheet" href="css/añadir.css">
    <style> 
    body{background:#f4f7fb;margin:0;font-family:'Segoe UI',Arial,sans-serif;} 
     .main-container{max-width:1100px;margin:40px auto 0 auto;background:#fff; border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,0.10);padding:36px;} 
     .materias-header{display:flex;align-items:center;justify-content:space-between; margin-bottom:32px;border-bottom:2px solid #e3eefd;padding-bottom:12px;} 
     .btn-volver{background:#6c757d;color:white;border:none;border-radius:8px;padding:10px 16px; font-size:0.95em;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex; align-items:center;gap:8px;}
     ..materias-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 32px;
    border-bottom: 2px solid #e3eefd;
    padding-bottom: 12px;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 16px; /* espacio entre Volver y Carreras */
}

     .btn-add{background:#28a745;color:#fff;border:none;border-radius:50%; width:48px;height:48px;font-size:1.7em;cursor:pointer;}
     .search-container{margin-bottom:24px;display:flex;gap:12px;align-items:center;
    }
    .search-btn {
            background: #0074ff;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, box-shadow 0.2s;
        }
        .search-btn:hover {
            background: #0056b3;
            box-shadow: 0 4px 12px rgba(0,116,255,0.2);
        }
        .clear-btn {
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
     .search-input{flex:1;max-width:400px;padding:12px 16px;border:2px solid #e3eefd; border-radius:8px;font-size:1em;}
     .materias-table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px; overflow:hidden;box-shadow:0 6px 32px rgba(30,64,175,0.08);}
     .materias-table th,.materias-table td{padding:14px 12px;text-align:left;border-bottom:1px solid #e0e0e0;}
     .materias-table th{background:#e3eefd;color:#263238;font-weight:700;}
     .acciones{display:flex;gap:8px;}
     .acciones button{border:none;border-radius:4px;padding:7px 12px;cursor:pointer;}
     .btn-edit{background:#ffc107;color:#222;}
     .btn-delete{background:#dc3545;color:#fff;}
    </style>
</head>
<body>
<div class="main-container">
   <div class="materias-header">
    <div class="header-left">
        <a href="disposicionaulica.php" class="btn-volver">← Volver</a>
        <h2>Carreras</h2>
    </div>
 <button class="btn-add" onclick="abrirModalCarrera('agregar')">+</button>

</div>


    <!-- Barra búsqueda -->
    <form method="GET" class="search-container">
        <input type="text" name="busqueda" class="search-input" placeholder="Buscar carrera, universidad o acrónimo..."
               value="<?php echo htmlspecialchars($busqueda); ?>">
        <button type="submit" class="search-btn">Buscar</button>
        <?php if (!empty($busqueda)): ?>
            <a href="carreras.php" class="clear-btn">Limpiar</a>
        <?php endif; ?>
    </form>

    <table class="materias-table">
        <thead>
        <tr>
            <th>Carrera</th>
            <th>Universidad</th>
            <th>Acrónimo</th>
            <th>Acciones</th>
        </tr>
        </thead>
      <tbody>
<?php if ($carreras->num_rows > 0): ?>
    <?php while($c = $carreras->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($c['carrera']); ?></td>
            <td><?php echo $c['universidad'] ? htmlspecialchars($c['universidad']) : '-'; ?></td>
            <td><?php echo $c['acronimo'] ? htmlspecialchars($c['acronimo']) : '-'; ?></td>
            <td class="acciones">
                <button class="btn-edit"
                    onclick="abrirModalCarrera('editar', <?php echo $c['id_carrera']; ?>, '<?php echo htmlspecialchars($c['carrera'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($c['universidad'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($c['acronimo'], ENT_QUOTES); ?>')">
                    ✏️
                </button>
                <button class="btn-delete"
                    onclick="eliminarCarrera(<?php echo $c['id_carrera']; ?>, '<?php echo htmlspecialchars($c['carrera'], ENT_QUOTES); ?>')">
                    🗑️
                </button>
            </td>
        </tr>
    <?php endwhile; ?>
<?php else: ?>
    <tr><td colspan="4">No se encontraron carreras</td></tr>
<?php endif; ?>
</tbody>

    </table>
</div>
<div class="modal" id="modal-carrera">
    <div class="modal-content">
        <h3 id="modal-carrera-titulo">Agregar Carrera</h3>
        <form id="form-carrera" onsubmit="guardarCarrera(event)">
            <input type="hidden" name="modo" id="modal-carrera-modo" value="agregar">
            <input type="hidden" name="id_carrera" id="modal-carrera-id" value="">
            <div class="form-group">
                <label>Nombre:</label>
                <input type="text" name="nombre" id="modal-carrera-nombre" required>
            </div>
            <div class="form-group">
                <label>Universidad:</label>
                <select name="universidad_id" id="modal-carrera-universidad">
                    <option value="">Seleccione una universidad</option>
                    <?php $universidades->data_seek(0); while ($u = $universidades->fetch_assoc()): ?>
                        <option value="<?php echo $u['id_universidad']; ?>"><?php echo htmlspecialchars($u['nombre']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Acrónimo:</label>
                <input type="text" name="acronimo" id="modal-carrera-acronimo">
            </div>
            <div class="acciones-modal">
                <button type="submit" class="btn-edit">Guardar</button>
                <button type="button" onclick="cerrarModalCarrera()">Cancelar</button>
            </div>
        </form>
    </div>
</div>
<style>
/* Reutiliza estilos de tu modal materias, ajustando identificadores */
.modal { /* igual que tu estilo anterior */ }
.modal-content { /* ... */ }
.acciones-modal .btn-edit { /* ... */ }
</style>
    <script>
function abrirModalCarrera(modo, id = '', nombre = '', universidad = '', acronimo = '') {
    document.getElementById('modal-carrera').style.display = 'flex';
    document.getElementById('modal-carrera-titulo').textContent = modo === 'editar' ? 'Editar Carrera' : 'Agregar Carrera';
    document.getElementById('modal-carrera-modo').value = modo;
    document.getElementById('modal-carrera-id').value = id;
    document.getElementById('modal-carrera-nombre').value = nombre;
    document.getElementById('modal-carrera-universidad').value = universidad;
    document.getElementById('modal-carrera-acronimo').value = acronimo;
}

function cerrarModalCarrera() {
    document.getElementById('modal-carrera').style.display = 'none';
}

function guardarCarrera(event) {
    event.preventDefault();
    const formData = new FormData();
    formData.append('action', 'gestionar_carrera');
    formData.append('modo', document.getElementById('modal-carrera-modo').value);
    formData.append('nombre', document.getElementById('modal-carrera-nombre').value);
    formData.append('universidad_id', document.getElementById('modal-carrera-universidad').value);
    formData.append('acronimo', document.getElementById('modal-carrera-acronimo').value);
    if (document.getElementById('modal-carrera-modo').value === 'editar') {
        formData.append('id_carrera', document.getElementById('modal-carrera-id').value);
    }

    fetch('gestionar_carreras.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Error al guardar la carrera');
    });
}

function eliminarCarrera(id, nombre) {
    if (!confirm(`¿Está seguro de eliminar la carrera "${nombre}"?`)) return;
    const formData = new FormData();
    formData.append('action', 'eliminar_carrera');
    formData.append('id_carrera', id);
    fetch('gestionar_carreras.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Error al eliminar la carrera');
    });
}

window.onclick = function(e) {
    if (e.target === document.getElementById('modal-carrera')) cerrarModalCarrera();
}

function abrirModalEditar(idCarrera, nombreCarrera) {
    alert("Editar carrera ID: " + idCarrera + "\nNombre: " + nombreCarrera);
    // Aquí puedes abrir un modal y llenar los campos
    // por ejemplo: document.getElementById('nombre').value = nombreCarrera;
}

function eliminarCarrera(idCarrera, nombreCarrera) {
    if (confirm("¿Estás seguro de que deseas eliminar la carrera '" + nombreCarrera + "'?")) {
        // Redirige a un script PHP que maneje la eliminación
        window.location.href = "eliminar_carrera.php?id=" + idCarrera;
    }
}
</script>
</body>
</html>
