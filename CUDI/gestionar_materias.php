<?php
include 'conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    function capitalizar_palabras($str) {
        return mb_convert_case(trim($str), MB_CASE_TITLE, "UTF-8");
    }
    
    switch ($action) {
        case 'gestionar_materia':
            $modo = $_POST['modo'] ?? '';
            $nombre = capitalizar_palabras($conn->real_escape_string($_POST['nombre'] ?? ''));
            $carrera_id = isset($_POST['carrera_id']) && $_POST['carrera_id'] !== '' ? intval($_POST['carrera_id']) : 'NULL';
            $curso_pre_admision_id = isset($_POST['curso_pre_admision_id']) && $_POST['curso_pre_admision_id'] !== '' ? intval($_POST['curso_pre_admision_id']) : 'NULL';
            
            if (empty($nombre)) {
                echo json_encode(['success' => false, 'message' => 'El nombre de la materia es obligatorio']);
                exit;
            }
            
            if ($carrera_id !== 'NULL' && $curso_pre_admision_id !== 'NULL') {
                echo json_encode(['success' => false, 'message' => 'Una materia no puede pertenecer tanto a una carrera como a un curso pre-admisión']);
                exit;
            }
            
            if ($modo === 'agregar') {
                $sql = "INSERT INTO materias (nombre, carrera_id, curso_pre_admision_id) VALUES ('$nombre', $carrera_id, $curso_pre_admision_id)";
                $mensaje = 'Materia agregada exitosamente';
            } else {
                $materia_id = intval($_POST['materia_id'] ?? 0);
                if ($materia_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'ID de materia inválido']);
                    exit;
                }
                $sql = "UPDATE materias SET nombre='$nombre', carrera_id=$carrera_id, curso_pre_admision_id=$curso_pre_admision_id WHERE id_materia=$materia_id";
                $mensaje = 'Materia actualizada exitosamente';
            }
            
            if ($conn->query($sql)) {
                echo json_encode(['success' => true, 'message' => $mensaje]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $conn->error]);
            }
            break;
            
        case 'eliminar_materia':
            $materia_id = intval($_POST['materia_id'] ?? 0);
            
            if ($materia_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID de materia inválido']);
                exit;
            }
            
            $check_sql = "SELECT COUNT(*) as count FROM tarjetas_disposicion WHERE materia_id = $materia_id";
            $result = $conn->query($check_sql);
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                echo json_encode(['success' => false, 'message' => 'No se puede eliminar la materia porque está asignada a disposiciones áulicas']);
                exit;
            }
            
            $sql = "DELETE FROM materias WHERE id_materia = $materia_id";
            
            if ($conn->query($sql)) {
                echo json_encode(['success' => true, 'message' => 'Materia eliminada exitosamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $conn->error]);
            }
            break;
        
        // --- ACCIÓN PARA ENLAZAR PROFESOR A MATERIA (IMPACTO EN LA BASE DE DATOS) ---
        case 'agregar_profesor_a_materia':
            $materia_id = intval($_POST['materia_id'] ?? 0);
            $profesor_id = intval($_POST['profesor_id'] ?? 0);

            if ($materia_id <= 0 || $profesor_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'IDs de materia y profesor inválidos']);
                exit;
            }

            // La consulta INSERT INTO es la que impacta la tabla pivote materia_profesor.
            $sql = "INSERT INTO materia_profesor (materia_id, profesor_id) VALUES ($materia_id, $profesor_id)";
            
            if ($conn->query($sql)) {
                echo json_encode(['success' => true, 'message' => 'Profesor asignado a la materia correctamente']);
            } else {
                // Captura el error de entrada duplicada (código 1062) si la relación ya existe.
                if ($conn->errno == 1062) {
                    echo json_encode(['success' => false, 'message' => 'Este profesor ya está asignado a esta materia.']);
                } else {
                    // Captura otros errores (ej. claves foráneas)
                    echo json_encode(['success' => false, 'message' => 'Error al asignar profesor: ' . $conn->error]);
                }
            }
            break;
        // --- FIN ACCIÓN ENLAZAR PROFESOR ---

        case 'eliminar_profesor_de_materia':
            $materia_id = intval($_POST['materia_id'] ?? 0);
            $profesor_id = intval($_POST['profesor_id'] ?? 0);

            if ($materia_id <= 0 || $profesor_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'IDs de materia y profesor inválidos']);
                exit;
            }

            $sql = "DELETE FROM materia_profesor WHERE materia_id = $materia_id AND profesor_id = $profesor_id";
            
            if ($conn->query($sql)) {
                echo json_encode(['success' => true, 'message' => 'Relación profesor-materia eliminada correctamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al eliminar relación: ' . $conn->error]);
            }
            break;

        case 'obtener_materia':
            // La consulta para obtener una materia ahora debe traer una lista de profesores
            $materia_id = intval($_POST['materia_id'] ?? 0);
            
            if ($materia_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID de materia inválido']);
                exit;
            }
            
            $sql = "SELECT m.*, c.nombre AS carrera_nombre, cp.nombre_curso AS curso_nombre,
                                   GROUP_CONCAT(DISTINCT CONCAT(p.nombre, ' ', p.apellido) SEPARATOR ', ') AS profesores_nombres
                            FROM materias m 
                            LEFT JOIN carreras c ON m.carrera_id = c.id_carrera 
                            LEFT JOIN cursos_pre_admisiones cp ON m.curso_pre_admision_id = cp.id_curso_pre_admision
                            LEFT JOIN materia_profesor mp ON m.id_materia = mp.materia_id
                            LEFT JOIN profesores p ON mp.profesor_id = p.id_profesor
                            WHERE m.id_materia = $materia_id
                            GROUP BY m.id_materia";
            
            $result = $conn->query($sql);
            
            if ($result && $result->num_rows > 0) {
                $materia = $result->fetch_assoc();
                echo json_encode(['success' => true, 'materia' => $materia]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Materia no encontrada']);
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