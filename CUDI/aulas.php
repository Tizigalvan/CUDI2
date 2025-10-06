<?php
include 'conexion.php';

// --- LÓGICA PARA MANEJAR ACCIONES POST (CREAR, EDITAR, ELIMINAR) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // ACCIÓN: AGREGAR UNA NUEVA AULA
    if ($action === 'add') {
        $numero = $conn->real_escape_string($_POST['numero']);
        $piso = intval($_POST['piso']);
        $cantidad = intval($_POST['cantidad']);

        $stmt = $conn->prepare("INSERT INTO aulas (numero, piso, cantidad) VALUES (?, ?, ?)");
        $stmt->bind_param("sii", $numero, $piso, $cantidad);
        $stmt->execute();
        $stmt->close();
    }
    // ACCIÓN: EDITAR UN AULA EXISTENTE
    elseif ($action === 'edit') {
        $id = intval($_POST['id_aula']);
        $numero = $conn->real_escape_string($_POST['numero']);
        $piso = intval($_POST['piso']);
        $cantidad = intval($_POST['cantidad']);

        $stmt = $conn->prepare("UPDATE aulas SET numero=?, piso=?, cantidad=? WHERE id_aula=?");
        $stmt->bind_param("siii", $numero, $piso, $cantidad, $id);
        $stmt->execute();
        $stmt->close();
    }
    // ACCIÓN: ELIMINAR UN AULA
    elseif ($action === 'delete' && isset($_POST['id_aula'])) {
        $id = intval($_POST['id_aula']);

        // Medida de seguridad: Verificar si el aula está siendo usada en una tarjeta de disposición
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM tarjetas_disposicion WHERE aula_id = ?");
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $check_stmt->bind_result($count);
        $check_stmt->fetch();
        $check_stmt->close();

        // Solo borrar si no está en uso
        if ($count == 0) {
            $delete_stmt = $conn->prepare("DELETE FROM aulas WHERE id_aula = ?");
            $delete_stmt->bind_param("i", $id);
            $delete_stmt->execute();
            $delete_stmt->close();
        } else {
            // Opcional: podrías redirigir con un mensaje de error
            // header('Location: aulas.php?error=El aula está en uso y no puede ser eliminada.');
            // exit;
        }
    }

    // Redirigir a la misma página para evitar reenvío de formulario
    header('Location: aulas.php');
    exit;
}

// --- CAMBIO CLAVE: CONSULTA SQL CON JOIN ---
// Se unen las tablas 'aulas' y 'piso' para obtener el nombre del piso
$sql = "SELECT aulas.*, piso.nom_piso FROM aulas JOIN piso ON aulas.piso = piso.id_piso ORDER BY aulas.piso, aulas.numero";
$aulas = $conn->query($sql);

// --- CONSULTA ADICIONAL PARA OBTENER TODOS LOS PISOS ---
$sql_pisos = "SELECT id_piso, nom_piso FROM piso ORDER BY id_piso";
$pisos_result = $conn->query($sql_pisos);
$pisos = [];
if ($pisos_result && $pisos_result->num_rows > 0) {
    while($p = $pisos_result->fetch_assoc()) {
        $pisos[] = $p;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Aulas</title>
    <style>
        /* Estilos adaptados de profesores.php para consistencia visual */
        body { background: #f4f7fb; margin: 0; font-family: 'Segoe UI', Arial, sans-serif; }
        .main-container { max-width: 900px; margin: 40px auto; background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.10); padding: 36px; }
        .aulas-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 32px; border-bottom: 2px solid #e3eefd; padding-bottom: 12px; }
        .header-left { display: flex; align-items: center; gap: 20px; }
        .btn-volver { background: #6c757d; color: white; border: none; border-radius: 8px; padding: 10px 16px; font-size: 0.95em; font-weight: 600; cursor: pointer; text-decoration: none; }
        .aulas-header h2 { margin: 0; font-size: 2.3em; color: #1a237e; }
        .aulas-header .btn-add { background: #0074ff; color: #fff; border: none; border-radius: 50%; width: 48px; height: 48px; font-size: 1.7em; cursor: pointer; display: flex; justify-content: center; align-items: center; }
        .aulas-table { width: 100%; border-collapse: collapse; }
        .aulas-table th, .aulas-table td { padding: 14px 12px; text-align: left; border-bottom: 1px solid #e0e0e0; }
        .aulas-table th { background-color: #f8faff; color: #333; font-weight: 600; }
        .acciones button { border: none; border-radius: 6px; padding: 7px 12px; cursor: pointer; margin-right: 5px; }
        .btn-edit { background: #0074ff; color: #333; }
        .btn-delete { background: #0074ff; color: #fff; }
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); justify-content: center; align-items: center; }
        .modal-content { background: #fff; padding: 28px; border-radius: 14px; min-width: 350px; box-shadow: 0 5px 25px rgba(0,0,0,0.2); }
        .modal-content h3 { margin-top: 0; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; color: #555; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
        .acciones-modal { text-align: right; margin-top: 20px; }
        .acciones-modal button { padding: 10px 18px; border-radius: 6px; border: none; cursor: pointer; }
        .acciones-modal button[type="submit"] { background-color: #007bff; color: white; }
        .acciones-modal button[type="button"] { background-color: #6c757d; color: white; margin-left: 10px; }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="aulas-header">
             <div class="header-left">
                 <a href="disposicionaulica.php" class="btn-volver">← Volver</a>
                 <h2>Gestión de Aulas</h2>
             </div>
             <button class="btn-add" onclick="abrirModalAgregar()">+</button>
        </div>
        
        <table class="aulas-table">
            <thead>
                <tr>
                    <th>Número / Nombre</th>
                    <th>Piso</th>
                    <th>Capacidad</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($aulas && $aulas->num_rows > 0): ?>
                    <?php while($a = $aulas->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($a['numero']); ?></td>
                        <td><?php echo htmlspecialchars($a['nom_piso']); ?></td>
                        <td><?php echo htmlspecialchars($a['cantidad']); ?> asientos</td>
                        <td class="acciones">
                            <button class="btn-edit" onclick="abrirModalEditar(<?php echo $a['id_aula']; ?>, '<?php echo htmlspecialchars($a['numero'], ENT_QUOTES); ?>', <?php echo $a['piso']; ?>, <?php echo $a['cantidad']; ?>)">✏️</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Está seguro de que desea eliminar esta aula?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id_aula" value="<?php echo $a['id_aula']; ?>">
                                <button type="submit" class="btn-delete">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center; padding: 20px;">No se encontraron aulas. Agregue una para comenzar.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="modal" id="modal-aula">
        <div class="modal-content">
            <h3 id="modal-titulo">Agregar Aula</h3>
            <form method="POST" id="form-aula">
                <input type="hidden" name="action" id="modal-accion" value="add">
                <input type="hidden" name="id_aula" id="modal-id-aula">
                
                <div class="form-group">
                    <label for="modal-numero">Número o Nombre:</label>
                    <input type="text" name="numero" id="modal-numero" required>
                </div>
                <div class="form-group">
                    <label for="modal-piso">Piso:</label>
                    <select name="piso" id="modal-piso" required>
                        <?php foreach ($pisos as $p): ?>
                            <option value="<?php echo htmlspecialchars($p['id_piso']); ?>"><?php echo htmlspecialchars($p['nom_piso']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="modal-cantidad">Capacidad (N° de asientos):</label>
                    <input type="number" name="cantidad" id="modal-cantidad" required>
                </div>

                <div class="acciones-modal">
                    <button type="button" onclick="cerrarModal()">Cancelar</button>
                    <button type="submit">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function abrirModalAgregar() {
        document.getElementById('modal-titulo').textContent = 'Agregar Nueva Aula';
        document.getElementById('form-aula').reset(); // Limpia el formulario
        document.getElementById('modal-accion').value = 'add';
        document.getElementById('modal-aula').style.display = 'flex';
    }

    function abrirModalEditar(id, numero, piso, cantidad) {
        document.getElementById('modal-titulo').textContent = 'Editar Aula';
        
        // Asignar los valores a los campos del formulario
        document.getElementById('modal-accion').value = 'edit';
        document.getElementById('modal-id-aula').value = id;
        document.getElementById('modal-numero').value = numero;
        document.getElementById('modal-piso').value = piso;
        document.getElementById('modal-cantidad').value = cantidad;
        
        // Seleccionar la opción de piso correcta
        const pisoSelect = document.getElementById('modal-piso');
        for (let i = 0; i < pisoSelect.options.length; i++) {
            if (pisoSelect.options[i].value == piso) {
                pisoSelect.selectedIndex = i;
                break;
            }
        }

        document.getElementById('modal-aula').style.display = 'flex';
    }

    function cerrarModal() {
        document.getElementById('modal-aula').style.display = 'none';
    }

    // Cerrar modal si se hace clic fuera del contenido
    window.onclick = function(event) {
        const modal = document.getElementById('modal-aula');
        if (event.target == modal) {
            cerrarModal();
        }
    }
    </script>
</body>
</html>