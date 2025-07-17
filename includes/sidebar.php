<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? 'admin'; // Default admin jika tidak ditemukan

switch ($role) {
    case 'admin':
        include __DIR__ . '/sidebar/admin.php';
        break;
    case 'instruktur':
        include __DIR__ . '/sidebar/instruktur.php';
        break;
    case 'siswa':
        include __DIR__ . '/sidebar/siswa.php';
        break;
    default:
        echo "Role tidak dikenal!";
}
?>
