<?php
$host = "127.0.0.1";  // Ganti dari localhost ke IP
$user = "root";
$password = "";
$database = "lkp_webapp";

try {
    $conn = new mysqli($host, $user, $password, $database);
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}
?>