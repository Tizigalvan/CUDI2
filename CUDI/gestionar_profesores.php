<?php
include 'conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'gestionar_profesor':
            $modo = $_POST['modo'] ?? '';
            $nombre = $conn->real_escape_string($_POST['nombre'] ?? '');
            $apellido = $conn->real_escape_string($_POST['apellido'] ?? '');
            $correo = $conn->real_escape_string($_POST['correo'] ?? '');
            $telefono = $conn->real_escape_string($_POST['telefono'] ?? '');
            
            if (empty($nombre) || empty($apellido)) {
                echo json_encode(['success' => false, 'message' => 'Nombre y apellido son obligatorios']);
                exit;
            }
            
            if ($modo === 'agregar') {
                $sql = "INSERT INTO profesores (nombre, apellido, correo, telefono) VALUES ('$nombre', '$apellido', '$correo', '$telefono')";
                $mensaje = 'Profesor agregado exitosamente';
            } else {
                $profesor_id = intval($_POST['profesor_id'] ?? 0);
                $sql = "UPDATE profesores SET nombre='$nombre', apellido='$apellido', correo='$correo', telefono='$telefono' WHERE id_profesor=$profesor_id";
                $mensaje = 'Profesor actualizado exitosamente';
            }
            
            if ($conn->query($sql)) {
                echo json_encode(['success' => true, 'message' => $mensaje]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $conn->error]);
            }
            break;
            
        case 'eliminar_profesor':
            $profesor_id = intval($_POST['profesor_id'] ?? 0);
            
            if ($profesor_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID de profesor inválido']);
                exit;
            }
            
            // --- ¡CORRECCIÓN IMPORTANTE! ---
            // Verificar si el profesor está asignado en la nueva tabla de unión
            $check_sql = "SELECT COUNT(*) as count FROM materia_profesor WHERE profesor_id = $profesor_id";
            $result = $conn->query($check_sql);
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                echo json_encode(['success' => false, 'message' => 'No se puede eliminar el profesor porque está asignado a materias. Desasígnelo primero.']);
                exit;
            }
            
            $sql = "DELETE FROM profesores WHERE id_profesor = $profesor_id";
            
            if ($conn->query($sql)) {
                echo json_encode(['success' => true, 'message' => 'Profesor eliminado exitosamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $conn->error]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            break;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}

$conn->close();
?>