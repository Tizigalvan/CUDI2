<?php
include 'conexion.php';

$universidades = [];
$result = $conn->query("SELECT id_universidad, nombre, acronimo FROM universidades");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $universidades[] = $row;
    }
}

session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

$sql = "SELECT id_curso_pre_admision, nombre_curso FROM cursos_pre_admisiones";

if (!empty($busqueda)) {
    $busqueda_escaped = $conn->real_escape_string($busqueda);
    $sql .= " WHERE nombre_curso LIKE '%$busqueda_escaped%'";
}

$sql .= " ORDER BY nombre_curso";

$cursos = $conn->query($sql);

if (!$cursos) {
    echo "Error en la consulta SQL: " . $conn->error;
    $cursos = null;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'guardar_curso') {
        $modo = $_POST['modo'] ?? '';
        $nombre = trim($_POST['nombre_curso'] ?? '');
        $id = intval($_POST['id_curso_pre_admision'] ?? 0);

        if ($modo === 'agregar') {
            $stmt = $conn->prepare("INSERT INTO cursos_pre_admisiones (nombre_curso) VALUES (?)");
            $stmt->bind_param("s", $nombre);
            $success = $stmt->execute();
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Curso agregado correctamente.' : 'Error al agregar curso.'
            ]);
            exit;
        } elseif ($modo === 'editar' && $id > 0) {
            $stmt = $conn->prepare("UPDATE cursos_pre_admisiones SET nombre_curso = ? WHERE id_curso_pre_admision = ?");
            $stmt->bind_param("si", $nombre, $id);
            $success = $stmt->execute();
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Curso actualizado correctamente.' : 'Error al actualizar curso.'
            ]);
            exit;
        }
    }

    if ($action === 'eliminar_curso') {
        $id = intval($_POST['id_curso_pre_admision'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM cursos_pre_admisiones WHERE id_curso_pre_admision = ?");
            $stmt->bind_param("i", $id);
            $success = $stmt->execute();
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Curso eliminado correctamente.' : 'Error al eliminar curso.'
            ]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
    exit;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Cursos de Pre-Admisión</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            margin: 0;
        }
        .main-container {
            max-width: 1100px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.1);
        }
        .materias-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .btn-volver {
            background: #6c757d;
            color: white;
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-add {
            background: #0074ff;;
            color: white;
            border: none;
            border-radius: 50%;
            width: 48px;
            height: 48px;
            font-size: 1.5em;
            cursor: pointer;
        }
        .search-container{
            margin-bottom:24px;
            display:flex;
            gap:12px;
            align-items:center;
    }
        .search-input {
    width: 50%;
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 1em;
}
        .search-btn, .clear-btn {
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 12px;
            border-bottom: 1px solid #ccc;
            text-align: left;
        }
        th {
            background: #e3eefd;
        }
        .acciones {
            display: flex;
            gap: 6px;
        }
        .btn-edit {
           background: linear-gradient(135deg, #0074ff 60%, #00c6ff 100%);
            color:#222;
            border: none;
            cursor: pointer;
        } 
        .btn-delete {
            background: linear-gradient(135deg, #0074ff 60%, #00c6ff 100%);
            color:#fff;
            border: none;
            cursor: pointer;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: white;
            padding: 24px;
            border-radius: 10px;
            width: 400px;
        }
        td.nombre-curso {
    font-size: 1.2em;
    font-weight: 500;
}

.btn-edit, .btn-delete {
    font-size: 1.2em;
    padding: 10px 14px;
    border-radius: 6px;
}
.titulo-con-volver {
    display: flex;
    align-items: center;
    gap: 12px;
}

.titulo-curso {
    margin: 0;
    font-size: 1.5em;
}

    </style>
</head>
<body>
<div class="main-container">
   <div class="materias-header">
    <div class="titulo-con-volver">
        <a href="disposicionaulica.php" class="btn-volver">← Volver</a>
        <h2 class="titulo-curso">Cursos de Pre-Admisión</h2>
    </div>
    <button class="btn-add" onclick="abrirModalCurso('agregar')">+</button>
</div>


    <form method="GET" class="search-container">
        <input type="text" name="busqueda" class="search-input" placeholder="Buscar curso..."
               value="<?php echo htmlspecialchars($busqueda); ?>">
        <button type="submit" class="search-btn">Buscar</button>
        <?php if (!empty($busqueda)): ?>
            <a href="curso_ingreso.php" class="clear-btn">Limpiar</a>
        <?php endif; ?>
    </form>

    <table>
        <thead>
        <tr>
            <th></th>
            <th>Nombre del Curso</th>
            <th>Acciones</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($cursos->num_rows > 0): ?>
            <?php while ($curso = $cursos->fetch_assoc()): ?>
                <tr>
                    <td></td>
                    <td><?php echo htmlspecialchars($curso['nombre_curso']); ?></td>
                    <td class="acciones">
                        <button class="btn-edit" onclick="abrirModalCurso('editar', <?php echo $curso['id_curso_pre_admision']; ?>, '<?php echo htmlspecialchars($curso['nombre_curso'], ENT_QUOTES); ?>')">✏️</button>
                        <button class="btn-delete" onclick="eliminarCurso(<?php echo $curso['id_curso_pre_admision']; ?>, '<?php echo htmlspecialchars($curso['nombre_curso'], ENT_QUOTES); ?>')">🗑️</button>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="3">No se encontraron cursos</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal -->
<div class="modal" id="modal-curso">
    <div class="modal-content">
        <h3 id="modal-curso-titulo">Agregar Curso</h3>
        <form id="form-curso" onsubmit="guardarCurso(event)">
            <input type="hidden" id="modo-curso" name="modo">
            <input type="hidden" id="curso-id" name="id_curso_pre_admision">
            <div style="margin-top:12px;">
    <label for="universidad">Universidad:</label>
    <select id="universidad" name="id_universidad" onchange="actualizarAcronimo()" required style="width:100%;padding:10px;margin-top:6px;">
        <option value="">Seleccione una universidad</option>
        <?php foreach ($universidades as $uni): ?>
            <option value="<?php echo $uni['id_universidad']; ?>" data-acronimo="<?php echo $uni['acronimo']; ?>">
                <?php echo htmlspecialchars($uni['nombre_universidad']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<div style="margin-top:12px;">
    <label for="acronimo">Acrónimo:</label>
    <input type="text" id="acronimo" name="acronimo" readonly style="width:100%;padding:10px;margin-top:6px;background:#eee;">
</div>

            <div style="margin-top:16px;display:flex;gap:10px;justify-content:flex-end;">
                <button type="submit" class="btn-edit">Guardar</button>
                <button type="button" onclick="cerrarModalCurso()">Cancelar</button>
            </div>
        </form>
    </div>
</div>
<script>
function actualizarAcronimo() {
    const select = document.getElementById("universidad");
    const acronimo = select.options[select.selectedIndex].getAttribute("data-acronimo");
    document.getElementById("acronimo").value = acronimo || '';
}
</script>

</body>
</html>
