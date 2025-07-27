<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['role'])) {
    echo json_encode([]);
    exit;
}

$role = mysqli_real_escape_string($conn, $_POST['role']);

if (!in_array($role, ['admin', 'instruktur', 'siswa'])) {
    echo json_encode([]);
    exit;
}

try {
    if ($role == 'admin') {
        $query = "SELECT a.id_admin as id, a.nama, a.email 
                  FROM admin a 
                  WHERE a.id_user IS NULL 
                  ORDER BY a.nama ASC";
    } elseif ($role == 'instruktur') {
        $query = "SELECT i.id_instruktur as id, i.nama, i.email 
                  FROM instruktur i 
                  WHERE i.id_user IS NULL 
                  ORDER BY i.nama ASC";
    } elseif ($role == 'siswa') {
        $query = "SELECT s.id_siswa as id, s.nama, s.email 
                  FROM siswa s 
                  WHERE s.id_user IS NULL 
                  ORDER BY s.nama ASC";
    }
    
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        throw new Exception("Database query error: " . mysqli_error($conn));
    }
    
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = [
            'id' => (int)$row['id'],
            'nama' => $row['nama'] ?? 'Nama tidak diatur',
            'email' => $row['email'] ?? null
        ];
    }
    
    echo json_encode($data);
    
} catch (Exception $e) {
    error_log("Error in get_target_data.php: " . $e->getMessage());
    echo json_encode([]);
}
?>