<?php
include 'conexion.php';

// --- LÓGICA POST TOTALMENTE ACTUALIZADA PARA M-M ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add' || $action === 'edit') {
        $nombre = $conn->real_escape_string($_POST['nombre']);
        $apellido = $conn->real_escape_string($_POST['apellido']);
        $correo = $conn->real_escape_string($_POST['correo']);
        $telefono = $conn->real_escape_string($_POST['telefono']);
        
        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO profesores (nombre, apellido, correo, telefono) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $nombre, $apellido, $correo, $telefono);
        } else {
            $id = intval($_POST['id_profesor']);
            $stmt = $conn->prepare("UPDATE profesores SET nombre=?, apellido=?, correo=?, telefono=? WHERE id_profesor=?");
            $stmt->bind_param("ssssi", $nombre, $apellido, $correo, $telefono, $id);
        }
        $stmt->execute();
        $stmt->close();

    } elseif ($action === 'delete' && isset($_POST['id_profesor'])) {
        $id = intval($_POST['id_profesor']);
        // La validación ahora se hace en gestionar_profesores.php, pero por seguridad, no se borra si está enlazado
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM materia_profesor WHERE profesor_id = ?");
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $check_stmt->bind_result($count);
        $check_stmt->fetch();
        $check_stmt->close();

        if ($count == 0) {
             $conn->query("DELETE FROM profesores WHERE id_profesor=$id");
        } else {
            // Pasamos un mensaje de error a la URL si no se puede borrar
            header('Location: profesores.php?error=' . urlencode('No se puede eliminar el profesor porque tiene materias asignadas.'));
            exit;
        }

    } elseif ($action === 'link' && isset($_POST['id_profesor'], $_POST['id_materia'])) {
        $id_prof = intval($_POST['id_profesor']);
        $id_mat = intval($_POST['id_materia']);
        // Usamos INSERT IGNORE para que no falle si la relación ya existe
        $conn->query("INSERT IGNORE INTO materia_profesor (profesor_id, materia_id) VALUES ($id_prof, $id_mat)");

    } elseif ($action === 'unlink' && isset($_POST['id_materia'], $_POST['id_profesor'])) {
        $id_mat = intval($_POST['id_materia']);
        $id_prof = intval($_POST['id_profesor']);
        // Se necesita tanto el id de materia como el de profesor para eliminar la relación correcta
        $conn->query("DELETE FROM materia_profesor WHERE materia_id = $id_mat AND profesor_id = $id_prof");
    }
    header('Location: profesores.php');
    exit;
}

// --- CONSULTA SQL PRINCIPAL ACTUALIZADA ---
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$sql = "SELECT p.*, GROUP_CONCAT(m.nombre SEPARATOR ', ') AS materias_nombres
        FROM profesores p
        LEFT JOIN materia_profesor mp ON p.id_profesor = mp.profesor_id
        LEFT JOIN materias m ON mp.materia_id = m.id_materia";

if (!empty($busqueda)) {
    $busqueda_escaped = $conn->real_escape_string($busqueda);
    $sql .= " WHERE p.nombre LIKE '%$busqueda_escaped%' OR p.apellido LIKE '%$busqueda_escaped%' OR m.nombre LIKE '%$busqueda_escaped%'";
}

$sql .= " GROUP BY p.id_profesor ORDER BY p.apellido, p.nombre";
$profesores = $conn->query($sql);

$materias = $conn->query("SELECT id_materia, nombre FROM materias ORDER BY nombre");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Profesores</title>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        /* Estilos base */
        body { background: #f4f7fb; margin: 0; font-family: 'Segoe UI', Arial, sans-serif; }
        .main-container { max-width: 1100px; margin: 40px auto; background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.10); padding: 36px; }
        .profesores-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 32px; border-bottom: 2px solid #e3eefd; padding-bottom: 12px; }
        .header-left { display: flex; align-items: center; gap: 20px; }
        .btn-volver { background: #6c757d; color: white; border: none; border-radius: 8px; padding: 10px 16px; font-size: 0.95em; font-weight: 600; cursor: pointer; text-decoration: none; }
        .profesores-header h2 { margin: 0; font-size: 2.3em; color: #1a237e; }
        .profesores-header .btn-add { background: #0074ff; color: #fff; border: none; border-radius: 50%; width: 48px; height: 48px; font-size: 1.7em; cursor: pointer; }

        /* Estilos de Tabla Mejorados (como Materias) */
        .profesores-table { width: 100%; border-collapse: collapse; }
        .profesores-table th, .profesores-table td { padding: 14px 12px; text-align: left; border-bottom: 1px solid #e0e0e0; }
        .profesores-table th { background: #e3eefd; color: #263238; font-weight: 700; } /* Fondo de encabezado */
        
        /* Búsqueda (Implementación de la vista de Materias) */
        .search-container { margin-bottom: 24px; display: flex; gap: 12px; }
        .search-input { flex: 1; padding: 12px 16px; border: 2px solid #e3eefd; border-radius: 8px; font-size: 1em; }
        .search-btn, .clear-btn { background: #0074ff; color: white; border: none; border-radius: 8px; padding: 12px 20px; font-size: 1em; cursor: pointer; text-decoration: none; display: inline-block; text-align: center; }
        .clear-btn { background: #6c757d; }
        
        /* Chips de Materias */
        .materias-list { display: flex; flex-wrap: wrap; gap: 5px; }
        .materia-chip { 
            background: #e3eefd; 
            color: #1a237e; 
            border-radius: 16px; 
            padding: 3px 25px 3px 12px; 
            font-size: 0.9em; 
            position: relative; 
            line-height: 1.5; /* Ajuste para el texto */
        }
        .chip-remove { 
            position: absolute; 
            right: 5px; 
            top: 50%; 
            transform: translateY(-50%); 
            background: none; 
            border: none; 
            cursor: pointer; 
            color: #1a237e; 
            font-weight: bold; 
            display: none; 
            padding: 0;
        }
        .materia-chip:hover .chip-remove { display: inline; }

        /* Acciones */
        .acciones button { border: none; border-radius: 4px; padding: 7px 12px; cursor: pointer; margin-right: 5px; }
        .btn-edit { background: #0074ff; } .btn-delete { background: #0074ff; color: #fff; } .btn-link { background: #007bff; color: #fff; }

        /* Estilos de Modal Mejorados (como Materias) */
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.25); justify-content: center; align-items: center; }
        .modal-content { background: #fff; padding: 24px; border-radius: 14px; min-width: 320px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #333; }
        .form-group input, .form-group select { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #ccc; 
            border-radius: 6px; 
            box-sizing: border-box; 
            font-size: 1em;
        }
        .acciones-modal button { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; margin-right: 10px; font-weight: 600; }
        .acciones-modal button[type="submit"] { background: #007bff; color: white; }
        .acciones-modal button[type="button"] { background: #6c757d; color: white; }
    </style>
</head>
<body>
    <div class="main-container">

        <?php if (isset($_GET['error'])): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 8px; margin-bottom: 20px;">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <div class="profesores-header">
             <div class="header-left">
                 <a href="disposicionaulica.php" class="btn-volver">← Volver</a>
                 <h2>Gestión de Profesores</h2>
             </div>
             <button class="btn-add" onclick="abrirModalAgregar()">+</button>
        </div>

        <form method="GET" class="search-container">
            <input type="text" name="busqueda" class="search-input" placeholder="Buscar por nombre, apellido o materia..." value="<?php echo htmlspecialchars($busqueda); ?>">
            <button type="submit" class="search-btn">🔍 Buscar</button>
            <?php if (!empty($busqueda)): ?>
                <a href="profesores.php" class="clear-btn">Limpiar</a>
            <?php endif; ?>
        </form>
        
        <table class="profesores-table">
            <thead>
                <tr>
                    <th>Profesor</th>
                    <th>Información de Contacto</th>
                    <th>Materias Asignadas</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($profesores && $profesores->num_rows > 0): ?>
                    <?php while($p = $profesores->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($p['apellido'] . ', ' . $p['nombre']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($p['correo']); ?><br>
                            <?php echo htmlspecialchars($p['telefono']); ?>
                        </td>
                        <td>
                            <div class="materias-list">
                                <?php
                                // --- LÓGICA DE CHIPS ---
                                $sql_materias_profesor = "SELECT m.id_materia, m.nombre 
                                                          FROM materias m
                                                          JOIN materia_profesor mp ON m.id_materia = mp.materia_id
                                                          WHERE mp.profesor_id = " . $p['id_profesor'];
                                $res_materias = $conn->query($sql_materias_profesor);
                                if ($res_materias && $res_materias->num_rows > 0) {
                                    while ($materia_prof = $res_materias->fetch_assoc()) {
                                        echo '<span class="materia-chip">' . htmlspecialchars($materia_prof['nombre']) . 
                                             '<button class="chip-remove" onclick="desenlazarMateria(' . $p['id_profesor'] . ', ' . $materia_prof['id_materia'] . ')">&times;</button></span>';
                                    }
                                } else {
                                    echo "Sin materias asignadas.";
                                }
                                ?>
                            </div>
                        </td>
                        <td class="acciones">
                            <button class="btn-edit" onclick="abrirModalEditar(<?php echo $p['id_profesor']; ?>, '<?php echo htmlspecialchars($p['nombre'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($p['apellido'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($p['correo'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($p['telefono'], ENT_QUOTES); ?>')">✏️</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar profesor? Si tiene materias asignadas, no se podrá.');"><input type="hidden" name="action" value="delete"><input type="hidden" name="id_profesor" value="<?php echo $p['id_profesor']; ?>"><button type="submit" class="btn-delete">🗑️</button></form>
                            <button class="btn-link" onclick="abrirModalEnlazar(<?php echo $p['id_profesor']; ?>)">🔗 </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                     <tr><td colspan="4" style="text-align:center; padding: 20px;">No se encontraron profesores.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="modal" id="modal-profesor">
        <div class="modal-content">
            <h3 id="modal-titulo">Agregar Profesor</h3>
            <form method="POST" id="form-profesor">
                <input type="hidden" name="action" id="modal-accion" value="add">
                <input type="hidden" name="id_profesor" id="modal-id-profesor">
                
                <div class="form-group"><label>Nombre:</label><input type="text" name="nombre" id="modal-nombre" required></div>
                <div class="form-group"><label>Apellido:</label><input type="text" name="apellido" id="modal-apellido" required></div>
                <div class="form-group"><label>Correo:</label><input type="email" name="correo" id="modal-correo"></div>
                <div class="form-group"><label>Teléfono:</label><input type="text" name="telefono" id="modal-telefono"></div>
                
                <div class="acciones-modal">
                    <button type="submit">Guardar</button>
                    <button type="button" onclick="cerrarModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="modal-enlazar">
        <div class="modal-content">
            <h3>Asignar Materia a Profesor</h3>
            <form method="POST">
                <input type="hidden" name="action" value="link">
                <input type="hidden" name="id_profesor" id="enlazar-id-profesor">
                <div class="form-group">
                    <label for="materia-modal">Materia:</label>
                    <select id="materia-modal" name="id_materia" required>
                        <option value="">Seleccione una materia</option>
                        <?php $materias->data_seek(0); while ($row = $materias->fetch_assoc()): ?>
                            <option value="<?php echo $row['id_materia']; ?>"><?php echo htmlspecialchars($row['nombre']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="acciones-modal">
                    <button type="submit">Asignar</button>
                    <button type="button" onclick="cerrarModalEnlazar()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
    // --- FUNCIÓN DESENLAZAR (SIN CAMBIOS) ---
    function desenlazarMateria(idProfesor, idMateria) {
        if (!confirm('¿Quitar esta materia del profesor?')) return;
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';

        const actionInput = document.createElement('input');
        actionInput.name = 'action';
        actionInput.value = 'unlink';
        form.appendChild(actionInput);

        const profesorInput = document.createElement('input');
        profesorInput.name = 'id_profesor';
        profesorInput.value = idProfesor;
        form.appendChild(profesorInput);

        const materiaInput = document.createElement('input');
        materiaInput.name = 'id_materia';
        materiaInput.value = idMateria;
        form.appendChild(materiaInput);

        document.body.appendChild(form);
        form.submit();
    }
    
    // --- LÓGICA DE MODALES (SIN CAMBIOS) ---
    function abrirModalAgregar() {
        document.getElementById('modal-titulo').textContent = 'Agregar Profesor';
        document.getElementById('form-profesor').reset();
        document.getElementById('modal-accion').value = 'add';
        document.getElementById('modal-profesor').style.display = 'flex';
    }
    function abrirModalEditar(id, nombre, apellido, correo, telefono) {
        document.getElementById('modal-titulo').textContent = 'Editar Profesor';
        document.getElementById('form-profesor').reset();
        document.getElementById('modal-accion').value = 'edit';
        document.getElementById('modal-id-profesor').value = id;
        document.getElementById('modal-nombre').value = nombre;
        document.getElementById('modal-apellido').value = apellido;
        document.getElementById('modal-correo').value = correo;
        document.getElementById('modal-telefono').value = telefono;
        document.getElementById('modal-profesor').style.display = 'flex';
    }
    function cerrarModal() {
        document.getElementById('modal-profesor').style.display = 'none';
    }
    function abrirModalEnlazar(idProfesor) {
        document.getElementById('enlazar-id-profesor').value = idProfesor;
        document.getElementById('modal-enlazar').style.display = 'flex';
        // Inicializar Select2 para el select del modal
        $('#materia-modal').select2({ dropdownParent: $('#modal-enlazar') });
    }
    function cerrarModalEnlazar() {
        document.getElementById('modal-enlazar').style.display = 'none';
    }
    </script>
</body>
</html>