<?php
require_once 'includes/db.php'; 

function uploadFile($file, $folder) {
    $targetDir = "uploads/$folder/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $filename = basename($file["name"]);
    $targetFile = $targetDir . time() . '_' . $filename;

    if (move_uploaded_file($file["tmp_name"], $targetFile)) {
        return $targetFile;
    } else {
        return false;
    }
}

// Ambil data dari form
$nik = $_POST['nik'];
$nama = $_POST['nama_pendaftar'];
$tempat = $_POST['tempat_lahir'];
$tanggal = $_POST['tanggal_lahir'];
$jk = $_POST['jenis_kelamin'];
$jam_pilihan = $_POST['jam_pilihan'];
$alamat = $_POST['alamat_lengkap'];
$no_hp = $_POST['no_hp'];
$email = $_POST['email'];
$pendidikan = $_POST['pendidikan_terakhir'];

// Upload file
$foto = uploadFile($_FILES['pas_foto'], 'pas_foto');
$ktp = uploadFile($_FILES['ktp'], 'ktp');
$kk = uploadFile($_FILES['kk'], 'kk');
$ijazah = uploadFile($_FILES['ijazah'], 'ijazah');

if ($foto && $ktp && $kk && $ijazah) {
    $sql = "INSERT INTO pendaftar 
        (nik, nama_pendaftar, tempat_lahir, tanggal_lahir, jenis_kelamin, jam_pilihan, alamat_lengkap, no_hp, email, pendidikan_terakhir, pas_foto, ktp, kk, ijazah, status_pendaftaran)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'menunggu verifikasi')";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssssssss", 
        $nik, $nama, $tempat, $tanggal, $jk, $jam_pilihan, $alamat, $no_hp, $email, 
        $pendidikan, $foto, $ktp, $kk, $ijazah);

    if ($stmt->execute()) {
        echo "<script>alert('Pendaftaran berhasil!'); window.location.href='index.php';</script>";
    } else {
        echo "<script>alert('Terjadi kesalahan saat menyimpan data.'); window.history.back();</script>";
    }
} else {
    echo "<script>alert('Gagal mengunggah salah satu file.'); window.history.back();</script>";
}
?>
