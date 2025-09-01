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
     .search-container{margin-bottom:24px;display:flex;gap:12px;align-items:center;}
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
    <button class="btn-add" onclick="abrirModalAgregar()">+</button>
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
            <?php while ($c = $carreras->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($c['carrera']); ?></td>
                    <td><?php echo $c['universidad'] ? htmlspecialchars($c['universidad']) : '-'; ?></td>
                    <td><?php echo $c['acronimo'] ? htmlspecialchars($c['acronimo']) : '-'; ?></td>
                    <td class="acciones">
                        <button class="btn-edit" 
                                onclick="abrirModalEditar(<?php echo $c['id_carrera']; ?>, 
                                '<?php echo htmlspecialchars($c['carrera']); ?>', 
                                '<?php echo $c['universidad']; ?>')">✏️</button>
                        <button class="btn-delete" 
                                onclick="eliminarCarrera(<?php echo $c['id_carrera']; ?>, 
                                '<?php echo htmlspecialchars($c['carrera']); ?>')">🗑️</button>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="4">No se encontraron carreras</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
