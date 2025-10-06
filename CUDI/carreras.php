<?php
include 'conexion.php';
session_start();

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

// --- ACCIONES AJAX ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => 'Acción no válida'];

    // AGREGAR O EDITAR CARRERA
    if ($_POST['action'] === 'guardar') {
        $modo = $_POST['modo'] ?? '';
        $nombre = trim($_POST['nombre'] ?? '');
        $id_universidad = intval($_POST['id_universidad'] ?? 0);

        if ($modo === 'agregar') {
            $stmt = $conn->prepare("INSERT INTO carreras (nombre, universidad_id) VALUES (?, ?)");
            $stmt->bind_param("si", $nombre, $id_universidad);
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Carrera agregada correctamente'];
            } else {
                $response['message'] = 'Error al insertar: ' . $conn->error;
            }
        } elseif ($modo === 'editar') {
            $id_carrera = intval($_POST['id_carrera'] ?? 0);
            $stmt = $conn->prepare("UPDATE carreras SET nombre = ?, universidad_id = ? WHERE id_carrera = ?");
            $stmt->bind_param("sii", $nombre, $id_universidad, $id_carrera);
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Carrera actualizada'];
            } else {
                $response['message'] = 'Error al actualizar: ' . $conn->error;
            }
        }
        echo json_encode($response);
        exit;
    }

    // ELIMINAR CARRERA
    if ($_POST['action'] === 'eliminar') {
        $id = intval($_POST['id_carrera'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM carreras WHERE id_carrera = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $response = ['success' => true, 'message' => 'Carrera eliminada'];
        } else {
            $response['message'] = 'Error al eliminar: ' . $conn->error;
        }
        echo json_encode($response);
        exit;
    }
}

// --- DATOS PARA PÁGINA PRINCIPAL ---
$busqueda = $_GET['busqueda'] ?? '';
$busqueda_sql = $conn->real_escape_string($busqueda);

// Obtener carreras con JOIN correcto
$sql = "SELECT c.id_carrera, c.nombre AS carrera, u.nombre AS universidad, u.acronimo
        FROM carreras c
        LEFT JOIN universidades u ON c.universidad_id = u.id_universidad";

if (!empty($busqueda)) {
    $sql .= " WHERE c.nombre LIKE '%$busqueda_sql%' 
              OR u.nombre LIKE '%$busqueda_sql%' 
              OR u.acronimo LIKE '%$busqueda_sql%'";
}

$carreras = $conn->query($sql);

// Universidades para el <select>
$universidades = [];
$res = $conn->query("SELECT id_universidad, nombre, acronimo FROM universidades ORDER BY nombre");
while ($row = $res->fetch_assoc()) {
    $universidades[] = $row;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Carreras</title>
    <style>
        body { background:#f4f7fb; margin:0; font-family:'Segoe UI',Arial,sans-serif; }
        .main-container { max-width:1100px; margin:40px auto; background:#fff; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,0.10); padding:36px; }
        .materias-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:32px; border-bottom:2px solid #e3eefd; padding-bottom:12px; }
        .header-left { display:flex; align-items:center; gap:16px; }
        .btn-volver, .btn-add, .search-btn, .clear-btn, .btn-edit, .btn-delete { cursor:pointer; }
        .btn-volver { background:#6c757d; color:white; border:none; border-radius:8px; padding:10px 16px; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:8px; }
        .btn-add { background:  #0074ff;; color:#fff; border:none; border-radius:50%; width:48px; height:48px; font-size:1.7em; }
        .search-container { display:flex; gap:12px; margin-bottom:24px; }
        .search-input { flex:1; max-width:400px; padding:12px 16px; border:2px solid #e3eefd; border-radius:8px; font-size:1em; }
        .search-btn { background: #0074ff; color:white; border:none; border-radius:8px; padding:12px 20px; font-weight:600; }
        .search-btn:hover { background: #0056b3; }
        .clear-btn { background:#6c757d; color:white; border:none; border-radius:8px; padding:12px 16px; }
        .materias-table { width:100%; border-collapse:collapse; background:#fff; border-radius:12px; box-shadow:0 6px 32px rgba(30,64,175,0.08); }
        .materias-table th, .materias-table td { padding:14px 12px; text-align:left; border-bottom:1px solid #e0e0e0; }
        .materias-table th { background:#e3eefd; color:#263238; font-weight:700; }
        .acciones { display:flex; gap:8px; }
        .btn-edit { background: #0074ff;; color:#222; padding:6px 10px; border:none; border-radius:4px; }
        .btn-delete { background:  #0074ff;; color:#fff; padding:6px 10px; border:none; border-radius:4px; }
        .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); justify-content:center; align-items:center; z-index:1000; }
        .modal-content { background:#fff; padding:30px; border-radius:12px; width:400px; }
        .form-group { margin-bottom:16px; }
        .acciones-modal { display:flex; gap:12px; margin-top:16px; }
         .form-group {
        margin-bottom: 20px;
        display: flex;
        flex-direction: column;
    }

    .form-group label {
        font-weight: 600;
        margin-bottom: 8px;
        font-size: 15px;
        color: #333;
    }

    .form-group input[type="text"],
    .form-group select {
        padding: 12px 16px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 16px;
        background-color: #fff;
        transition: border-color 0.3s ease;
    }

    .form-group input[readonly] {
        background-color: #f0f0f0;
        color: #555;
    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: #007bff;
    }
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
        <?php if ($carreras && $carreras->num_rows > 0): ?>
            <?php while($c = $carreras->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($c['carrera']); ?></td>
                    <td><?= $c['universidad'] ? htmlspecialchars($c['universidad']) : '-'; ?></td>
                    <td><?= $c['acronimo'] ? htmlspecialchars($c['acronimo']) : '-'; ?></td>
                    <td class="acciones">
                        <button class="btn-edit"
                            onclick="abrirModalCarrera('editar', <?= $c['id_carrera']; ?>, '<?= htmlspecialchars($c['carrera'], ENT_QUOTES); ?>', <?= $c['universidad'] ? "'" . htmlspecialchars($c['universidad'], ENT_QUOTES) . "'" : "''"; ?>, <?= $c['acronimo'] ? "'" . htmlspecialchars($c['acronimo'], ENT_QUOTES) . "'" : "''"; ?>)">
                            ✏️
                        </button>
                        <button class="btn-delete" onclick="eliminarCarrera(<?= $c['id_carrera']; ?>, '<?= htmlspecialchars($c['carrera'], ENT_QUOTES); ?>')">
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

<!-- Modal para Agregar/Editar Carrera -->
<div class="modal" id="modal-carrera">
    <div class="modal-content">
        <h3 id="modal-carrera-titulo">Agregar Carrera</h3>
        <form id="form-carrera" onsubmit="guardarCarrera(event)">
            <input type="hidden" name="modo" id="modal-carrera-modo">
            <input type="hidden" name="id_carrera" id="modal-carrera-id">
            <div class="form-group">
    <label for="modal-carrera-nombre">Nombre de la carrera:</label>
    <input type="text" name="nombre" id="modal-carrera-nombre" required>
</div>

<div class="form-group">
    <label for="modal-carrera-universidad">Universidad:</label>
    <select name="id_universidad" id="modal-carrera-universidad" onchange="actualizarAcronimo()" required>
        <option value="">Seleccione una universidad</option>
        <?php foreach ($universidades as $uni): ?>
            <option value="<?= $uni['id_universidad']; ?>" data-acronimo="<?= htmlspecialchars($uni['acronimo']); ?>">
                <?= htmlspecialchars($uni['nombre']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="form-group">
    <label for="modal-carrera-acronimo">Acrónimo:</label>
    <input type="text" id="modal-carrera-acronimo" readonly>
</div>
            <div class="acciones-modal">
                <button type="submit" class="btn-edit">Guardar</button>
                <button type="button" onclick="cerrarModalCarrera()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalCarrera(modo, id = '', nombre = '', universidad = '', acronimo = '') {
    document.getElementById('modal-carrera').style.display = 'flex';
    document.getElementById('modal-carrera-titulo').textContent = modo === 'editar' ? 'Editar Carrera' : 'Agregar Carrera';
    document.getElementById('modal-carrera-modo').value = modo;
    document.getElementById('modal-carrera-id').value = id;
    document.getElementById('modal-carrera-nombre').value = nombre;

    const select = document.getElementById('modal-carrera-universidad');
    for (let i = 0; i < select.options.length; i++) {
        if (select.options[i].text === universidad) {
            select.selectedIndex = i;
            break;
        }
    }

    document.getElementById('modal-carrera-acronimo').value = acronimo;
}

function cerrarModalCarrera() {
    document.getElementById('modal-carrera').style.display = 'none';
    document.getElementById('form-carrera').reset();
}

function actualizarAcronimo() {
    const select = document.getElementById("modal-carrera-universidad");
    const acronimo = select.options[select.selectedIndex]?.getAttribute("data-acronimo") || '';
    document.getElementById("modal-carrera-acronimo").value = acronimo;
}

function guardarCarrera(event) {
    event.preventDefault();
    const formData = new FormData(document.getElementById('form-carrera'));
    formData.append('action', 'guardar');

    fetch('carreras.php', {
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
        alert('Error al guardar');
    });
}

function eliminarCarrera(id, nombre) {
    if (!confirm(`¿Estás seguro de eliminar la carrera "${nombre}"?`)) return;

    const formData = new FormData();
    formData.append('action', 'eliminar');
    formData.append('id_carrera', id);

    fetch('carreras.php', {
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
        alert('Error al eliminar');
    });
}
</script>

</body>
</html>
