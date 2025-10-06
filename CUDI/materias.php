<?php
include 'conexion.php';
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

// CORRECCIÓN: Inicializamos $busqueda a cadena vacía para evitar el 'Undefined variable'
//             y luego la asignamos usando el valor de GET, si existe.
$busqueda = ''; 
if (isset($_GET['busqueda'])) {
    $busqueda = trim($_GET['busqueda']);
}

// --- CONSULTA SQL PARA OBTENER MATERIAS Y PROFESORES ASIGNADOS (M:N) ---
$sql = "SELECT 
            m.id_materia, m.nombre, m.carrera_id, m.curso_pre_admision_id,
            c.nombre AS carrera, 
            cp.nombre_curso AS curso_pre_admision, 
            GROUP_CONCAT(DISTINCT CONCAT(p.nombre, ' ', p.apellido) SEPARATOR ', ') AS profesores_nombres
        FROM materias m 
        LEFT JOIN carreras c ON m.carrera_id = c.id_carrera 
        LEFT JOIN cursos_pre_admisiones cp ON m.curso_pre_admision_id = cp.id_curso_pre_admision
        LEFT JOIN materia_profesor mp ON m.id_materia = mp.materia_id
        LEFT JOIN profesores p ON mp.profesor_id = p.id_profesor";

if (!empty($busqueda)) {
    $busqueda_escaped = $conn->real_escape_string($busqueda);
    $sql .= " WHERE m.id_materia IN (
                SELECT DISTINCT m_inner.id_materia FROM materias m_inner
                LEFT JOIN materia_profesor mp_inner ON m_inner.id_materia = mp_inner.materia_id
                LEFT JOIN profesores p_inner ON mp_inner.profesor_id = p_inner.id_profesor
                LEFT JOIN carreras c_inner ON m_inner.carrera_id = c_inner.id_carrera
                WHERE m_inner.nombre LIKE '%$busqueda_escaped%' 
                OR c_inner.nombre LIKE '%$busqueda_escaped%' 
                OR p_inner.nombre LIKE '%$busqueda_escaped%' 
                OR p_inner.apellido LIKE '%$busqueda_escaped%'
             )";
}

$sql .= " GROUP BY m.id_materia, c.nombre, cp.nombre_curso ORDER BY m.nombre";
$materias = $conn->query($sql);

// Obtener carreras, cursos y profesores para los selects
$carreras = $conn->query("SELECT id_carrera, nombre FROM carreras ORDER BY nombre");
$cursos = $conn->query("SELECT id_curso_pre_admision, nombre_curso FROM cursos_pre_admisiones ORDER BY nombre_curso");
$profesores = $conn->query("SELECT id_profesor, nombre, apellido FROM profesores ORDER BY apellido, nombre");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Materias</title>
    <link rel="stylesheet" href="css/añadir.css">
    <style>
        body { background: #f4f7fb; margin: 0; font-family: 'Segoe UI', Arial, sans-serif; }
        .main-container { max-width: 1100px; margin: 40px auto; background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.10); padding: 36px; }
        .materias-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 32px; border-bottom: 2px solid #e3eefd; padding-bottom: 12px; }
        .header-left { display: flex; align-items: center; gap: 20px; }
        .btn-volver { background: #6c757d; color: white; border: none; border-radius: 8px; padding: 10px 16px; font-size: 0.95em; font-weight: 600; cursor: pointer; text-decoration: none; }
        .materias-header h2 { margin: 0; font-size: 2.3em; color: #1a237e; }
        .materias-header .btn-add { background: #28a745; color: #fff; border: none; border-radius: 50%; width: 48px; height: 48px; font-size: 1.7em; cursor: pointer; }
        .search-container { margin-bottom: 24px; display: flex; gap: 12px; }
        .search-input { flex: 1; padding: 12px 16px; border: 2px solid #e3eefd; border-radius: 8px; font-size: 1em; }
        .search-btn, .clear-btn { background: #0074ff; color: white; border: none; border-radius: 8px; padding: 12px 20px; font-size: 1em; cursor: pointer; text-decoration: none; display: inline-block; text-align: center; }
        .clear-btn { background: #6c757d; }
        .materias-table { width: 100%; border-collapse: collapse; }
        .materias-table th, .materias-table td { padding: 14px 12px; text-align: left; border-bottom: 1px solid #e0e0e0; }
        .materias-table th { background: #e3eefd; color: #263238; font-weight: 700; }
        .avatar-materia { width: 38px; height: 38px; border-radius: 50%; background: #b3c6ff; color: #1a237e; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; }
        .acciones { display: flex; gap: 8px; }
        .acciones button { border: none; border-radius: 4px; padding: 7px 12px; cursor: pointer; }
        .btn-edit { background: #ffc107; }
        .btn-delete { background: #dc3545; color: #fff; }
        .btn-link { background: #007bff; color: #fff; }
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.25); justify-content: center; align-items: center; }
        .modal-content { background: #fff; padding: 24px; border-radius: 14px; min-width: 320px; }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="materias-header">
            <div class="header-left">
                <a href="disposicionaulica.php" class="btn-volver">← Volver</a>
                <h2>Materias</h2>
            </div>
            <button class="btn-add" onclick="abrirModalAgregar()">+</button>
        </div>
        
        <form method="GET" class="search-container">
            <input type="text" name="busqueda" class="search-input" placeholder="Buscar por materia, carrera o profesor..." value="<?php echo htmlspecialchars($busqueda); ?>">
            <button type="submit" class="search-btn">🔍 Buscar</button>
            <?php if (!empty($busqueda)): ?>
                <a href="materias.php" class="clear-btn">Limpiar</a>
            <?php endif; ?>
        </form>
        
        <table class="materias-table">
            <thead>
                <tr>
                    <th></th>
                    <th>Nombre</th>
                    <th>Carrera / Curso</th>
                    <th>Profesor(es)</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($materias && $materias->num_rows > 0): ?>
                    <?php while($m = $materias->fetch_assoc()): ?>
                    <tr>
                        <td><span class="avatar-materia">📚</span></td>
                        <td><?php echo htmlspecialchars($m['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($m['carrera'] ?? $m['curso_pre_admision'] ?? '-'); ?></td>
                        <td><?php echo $m['profesores_nombres'] ? htmlspecialchars($m['profesores_nombres']) : '-'; ?></td>
                        <td class="acciones">
                            <button class="btn-edit" onclick="abrirModalEditar(<?php echo $m['id_materia']; ?>, '<?php echo htmlspecialchars($m['nombre'], ENT_QUOTES); ?>', '<?php echo $m['carrera_id']; ?>', '<?php echo $m['curso_pre_admision_id']; ?>')">✏️</button>
                            <button class="btn-delete" onclick="eliminarMateria(<?php echo $m['id_materia']; ?>, '<?php echo htmlspecialchars($m['nombre'], ENT_QUOTES); ?>')">🗑️</button>
                            <button class="btn-link" onclick="abrirModalEnlazar(<?php echo $m['id_materia']; ?>, '<?php echo htmlspecialchars($m['nombre'], ENT_QUOTES); ?>')">🔗</button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding: 20px;">No se encontraron materias.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="modal" id="modal-materia">
        <div class="modal-content">
            <h3 id="modal-titulo">Agregar Materia</h3>
            <form id="form-materia" onsubmit="guardarMateria(event)">
                <input type="hidden" name="modo" id="modal-accion" value="agregar">
                <input type="hidden" name="materia_id" id="modal-id-materia">
                <div class="form-group"><label>Nombre:</label><input type="text" name="nombre" id="modal-nombre" required></div>
                <div class="form-group"><label>Carrera:</label><select name="carrera_id" id="modal-carrera"><option value="">(Ninguna)</option><?php $carreras->data_seek(0); while($c = $carreras->fetch_assoc()): ?><option value="<?php echo $c['id_carrera']; ?>"><?php echo htmlspecialchars($c['nombre']); ?></option><?php endwhile; ?></select></div>
                <div class="form-group"><label>Curso Pre-Admisión:</label><select name="curso_pre_admision_id" id="modal-curso"><option value="">(Ninguno)</option><?php $cursos->data_seek(0); while($cp = $cursos->fetch_assoc()): ?><option value="<?php echo $cp['id_curso_pre_admision']; ?>"><?php echo htmlspecialchars($cp['nombre_curso']); ?></option><?php endwhile; ?></select></div>
                <div class="acciones-modal"><button type="submit">Guardar</button><button type="button" onclick="cerrarModal()">Cancelar</button></div>
            </form>
        </div>
    </div>

    <div class="modal" id="modal-enlazar">
        <div class="modal-content">
            <h3 id="enlazar-titulo">Asignar profesor a: </h3>
            <form id="form-enlazar" onsubmit="enlazarProfesor(event)">
                <input type="hidden" name="materia_id" id="enlazar-id-materia">
                <div class="form-group">
                    <label for="profesor-modal">Profesor:</label>
                    <select id="profesor-modal" name="profesor_id" required>
                        <option value="">Seleccione un profesor</option>
                        <?php $profesores->data_seek(0); while ($row = $profesores->fetch_assoc()): ?>
                        <option value="<?php echo $row['id_profesor']; ?>"><?php echo htmlspecialchars($row['apellido'] . ', ' . $row['nombre']); ?></option>
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

    <script>
    function guardarMateria(event) {
        event.preventDefault();
        const formData = new FormData(document.getElementById('form-materia'));
        formData.append('action', 'gestionar_materia');
        
        fetch('gestionar_materias.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function abrirModalAgregar() {
        document.getElementById('modal-titulo').textContent = 'Agregar Materia';
        document.getElementById('form-materia').reset();
        document.getElementById('modal-accion').value = 'agregar';
        document.getElementById('modal-carrera').disabled = false;
        document.getElementById('modal-curso').disabled = false;
        document.getElementById('modal-materia').style.display = 'flex';
    }

    function abrirModalEditar(id, nombre, carrera_id, curso_id) {
        document.getElementById('modal-titulo').textContent = 'Editar Materia';
        document.getElementById('form-materia').reset();
        document.getElementById('modal-accion').value = 'editar';
        document.getElementById('modal-id-materia').value = id;
        document.getElementById('modal-nombre').value = nombre;
        document.getElementById('modal-carrera').value = carrera_id;
        document.getElementById('modal-curso').value = curso_id;
        document.getElementById('modal-carrera').disabled = !!curso_id;
        document.getElementById('modal-curso').disabled = !!carrera_id;
        document.getElementById('modal-materia').style.display = 'flex';
    }

    function cerrarModal() {
        document.getElementById('modal-materia').style.display = 'none';
    }
    
    function eliminarMateria(id_materia, nombre) {
        if (!confirm('¿Está seguro de eliminar la materia "' + nombre + '"?')) return;
        
        const formData = new FormData();
        formData.append('action', 'eliminar_materia');
        formData.append('materia_id', id_materia);
        
        fetch('gestionar_materias.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    // --- NUEVAS FUNCIONES PARA ENLAZAR ---
    function abrirModalEnlazar(id_materia, nombre_materia) {
        document.getElementById('enlazar-titulo').textContent = 'Asignar profesor a: ' + nombre_materia;
        document.getElementById('enlazar-id-materia').value = id_materia;
        document.getElementById('profesor-modal').value = ''; // Reset select
        document.getElementById('modal-enlazar').style.display = 'flex';
    }
    
    function cerrarModalEnlazar() {
        document.getElementById('modal-enlazar').style.display = 'none';
    }
    
    function enlazarProfesor(event) {
        event.preventDefault();
        
        const formData = new FormData(document.getElementById('form-enlazar'));
        formData.append('action', 'agregar_profesor_a_materia');
        
        fetch('gestionar_materias.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => console.error('Error:', error));
    }
    
    // Lógica de bloqueo mutuo
    document.getElementById('modal-carrera').addEventListener('change', function() {
        document.getElementById('modal-curso').disabled = !!this.value;
        if (this.value) document.getElementById('modal-curso').value = '';
    });
    document.getElementById('modal-curso').addEventListener('change', function() {
        document.getElementById('modal-carrera').disabled = !!this.value;
        if (this.value) document.getElementById('modal-carrera').value = '';
    });
    </script>
</body>
</html>