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
        $acronimo = trim($_POST['acronimo'] ?? '');
        $id = intval($_POST['id_curso_pre_admision'] ?? 0);

        // Concatenar el nombre y el acrónimo si existe
        if (!empty($acronimo)) {
            $nombre_con_acronimo = $nombre . ' (' . $acronimo . ')';
        } else {
            $nombre_con_acronimo = $nombre;
        }

        if ($modo === 'agregar') {
            $stmt = $conn->prepare("INSERT INTO cursos_pre_admisiones (nombre_curso) VALUES (?)");
            $stmt->bind_param("s", $nombre_con_acronimo); // Usar la variable concatenada
            $success = $stmt->execute();
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Curso agregado correctamente.' : 'Error al agregar curso.'
            ]);
            exit;
        } elseif ($modo === 'editar' && $id > 0) {
            $stmt = $conn->prepare("UPDATE cursos_pre_admisiones SET nombre_curso = ? WHERE id_curso_pre_admision = ?");
            $stmt->bind_param("si", $nombre_con_acronimo, $id); // Usar la variable concatenada
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .main-container {
            max-width: 1100px;
            margin: 40px auto;
            background: #ffffff;
            padding: 30px 40px;
            border-radius: 12px;
            box-shadow: 0 6px 30px rgba(0, 0, 0, 0.1);
        }
        .materias-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .btn-volver {
            background: #6c757d;
            color: white;
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.2s ease;
        }
        .btn-volver:hover {
            background: #5a6268;
        }
        .btn-add {
            background: #007bff;
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            font-size: 1.8em;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
            transition: all 0.2s ease;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .btn-add:hover {
            background: #0069d9;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 123, 255, 0.4);
        }
        .search-container {
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            align-items: center;
        }
        .search-input {
            flex: 1;
            padding: 12px;
            max-width: 400px;
            padding: 12px 16px;
            border-radius: 8px;
            border: 2px solid #ced4da;
            font-size: 1em;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        } 
        .search-input:focus {
            border-color: #80bdff;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .search-btn {
            background: #007bff;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 22px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease, box-shadow 0.2s ease;
        }
        .search-btn:hover {
            background: #0069d9;
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.2);
        }
        .clear-btn {
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 18px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        .clear-btn:hover {
            background: #5a6268;
        }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            overflow: hidden;
        }
        th, td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            text-align: left;
        }
        th {
            background: #e9f5ff;
            font-weight: 600;
            color: #34495e;
            text-transform: uppercase;
            font-size: 0.9em;
        }
        tr:last-child td {
            border-bottom: none;
        }
        .acciones {
            display: flex;
            gap: 8px;
        }
        .btn-edit, .btn-delete {
            font-size: 1em;
            padding: 8px 12px;
            border-radius: 6px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s ease, transform 0.2s ease;
        }
        .btn-edit {
            background: #007bff;
            color: white;
        }
        .btn-edit:hover {
            background: #007bff;
            transform: translateY(-1px);
        }
        .btn-delete {
            background: #007bff;
            color: white;
        }
        .btn-delete:hover {
            background: #007bff;
            transform: translateY(-1px);
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            position: relative;
            transform: translateY(-20px);
            transition: transform 0.3s ease-out;
        }
        .modal-content h3 {
            margin-top: 0;
            margin-bottom: 25px;
            color: #343a40;
            text-align: center;
            font-size: 1.8em;
            font-weight: 600;
        }
        .modal-content label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #495057;
        }
        .modal-content input[type="text"],
        .modal-content select {
            width: 100%;
            padding: 12px 15px;
            margin-bottom: 20px;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 1em;
            box-sizing: border-box;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .modal-content input[type="text"]:focus,
        .modal-content select:focus {
            border-color: #007bff;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .modal-content input[readonly] {
            background: #e9ecef;
            cursor: not-allowed;
        }
        .modal-content .btn-group {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 25px;
        }
        .modal-content .btn-guardar {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 600;
            transition: all 0.2s ease;
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.2);
        }
        .modal-content .btn-guardar:hover {
            background: linear-gradient(135deg, #0069d9 0%, #004085 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 123, 255, 0.3);
        }
        .modal-content .btn-cancelar {
            background: #f0f2f5;
            color: #6c757d;
            padding: 12px 25px;
            border: 1px solid #ced4da;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .modal-content .btn-cancelar:hover {
            background: #e2e6ea;
            color: #495057;
        }
        .titulo-con-volver {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .titulo-curso {
            margin: 0;
            font-size: 2em;
            color: #212529;
            font-weight: 700;
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
        <?php if ($cursos && $cursos->num_rows > 0): ?>
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

<div class="modal" id="modal-curso">
    <div class="modal-content">
        <h3 id="modal-curso-titulo">Agregar Curso</h3>
        <form id="form-curso" onsubmit="guardarCurso(event)">
            <input type="hidden" id="modo-curso" name="modo">
            <input type="hidden" id="curso-id" name="id_curso_pre_admision">
            
            <div style="margin-top:12px;">
                <label for="nombre_curso">Nombre del Curso:</label>
                <input type="text" id="nombre_curso" name="nombre_curso" required>
            </div>
            
            <div style="margin-top:12px;">
                <label for="universidad">Universidad:</label>
                <select id="universidad" name="id_universidad" onchange="actualizarAcronimo()" required>
                    <option value="">Seleccione una universidad</option>
                    <?php foreach ($universidades as $uni): ?>
                        <option value="<?php echo $uni['id_universidad']; ?>" data-acronimo="<?php echo $uni['acronimo']; ?>">
                            <?php echo htmlspecialchars($uni['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-top:12px;">
                <label for="acronimo">Acrónimo:</label>
                <input type="text" id="acronimo" name="acronimo" readonly>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn-guardar">Guardar</button>
                <button type="button" class="btn-cancelar" onclick="cerrarModalCurso()">Cancelar</button>
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

    // Funciones para el modal
    function abrirModalCurso(modo, id = null, nombre = null) {
        const modal = document.getElementById('modal-curso');
        const titulo = document.getElementById('modal-curso-titulo');
        const modoInput = document.getElementById('modo-curso');
        const idInput = document.getElementById('curso-id');
        const nombreInput = document.getElementById('nombre_curso');
        const universidadSelect = document.getElementById('universidad');

        if (modo === 'agregar') {
            titulo.textContent = 'Agregar Curso';
            modoInput.value = 'agregar';
            idInput.value = '';
            nombreInput.value = '';
            universidadSelect.selectedIndex = 0;
            document.getElementById('acronimo').value = '';
        } else if (modo === 'editar') {
            titulo.textContent = 'Editar Curso';
            modoInput.value = 'editar';
            idInput.value = id;
            
            // Separar el nombre y el acrónimo si existe
            const regex = /^(.*)\s\((.*)\)$/;
            const match = nombre.match(regex);
            
            if (match) {
                nombreInput.value = match[1];
                const acronimoEncontrado = match[2];
                // Intentar encontrar y seleccionar la universidad
                const opciones = universidadSelect.options;
                for (let i = 0; i < opciones.length; i++) {
                    if (opciones[i].getAttribute('data-acronimo') === acronimoEncontrado) {
                        universidadSelect.selectedIndex = i;
                        document.getElementById('acronimo').value = acronimoEncontrado;
                        break;
                    }
                }
            } else {
                nombreInput.value = nombre;
                universidadSelect.selectedIndex = 0;
                document.getElementById('acronimo').value = '';
            }
        }
        modal.style.display = 'flex';
    }

    function cerrarModalCurso() {
        const modal = document.getElementById('modal-curso');
        modal.style.display = 'none';
    }

    // Función para guardar (agregar/editar) un curso
    async function guardarCurso(event) {
        event.preventDefault();
        const form = document.getElementById('form-curso');
        const formData = new FormData(form);
        formData.append('action', 'guardar_curso');

        try {
            const response = await fetch('curso_ingreso.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                alert(result.message);
                cerrarModalCurso();
                window.location.reload();
            } else {
                alert(result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Ocurrió un error al guardar el curso.');
        }
    }

    // Función para eliminar un curso
    function eliminarCurso(id, nombre) {
        if (confirm(`¿Estás seguro de que quieres eliminar el curso "${nombre}"?`)) {
            const formData = new FormData();
            formData.append('action', 'eliminar_curso');
            formData.append('id_curso_pre_admision', id);

            fetch('curso_ingreso.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert(result.message);
                    window.location.reload();
                } else {
                    alert(result.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Ocurrió un error al eliminar el curso.');
            });
        }
    }
</script>
</body>
</html>