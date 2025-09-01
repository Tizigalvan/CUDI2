<?php
include 'conexion.php';

$action = $_POST['action'] ?? '';

if ($action === 'guardar') {
    $id_carrera = $_POST['id_carrera'] ?? '';
    $nombre = trim($_POST['nombre']);
    $universidad_id = $_POST['universidad_id'];

    if ($id_carrera == '') {
        // Crear nueva carrera
        $stmt = $conn->prepare("INSERT INTO carreras (nombre, universidad_id) VALUES (?, ?)");
        $stmt->bind_param("si", $nombre, $universidad_id);
        $stmt->execute();
    } else {
        // Editar carrera existente
        $stmt = $conn->prepare("UPDATE carreras SET nombre = ?, universidad_id = ? WHERE id_carrera = ?");
        $stmt->bind_param("sii", $nombre, $universidad_id, $id_carrera);
        $stmt->execute();
    }
    echo "OK";
} elseif ($action === 'eliminar') {
    $id_carrera = $_POST['id_carrera'] ?? 0;
    $stmt = $conn->prepare("DELETE FROM carreras WHERE id_carrera = ?");
    $stmt->bind_param("i", $id_carrera);
    $stmt->execute();
    echo "OK";
}
