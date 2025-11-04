<?php
session_start();
require_once '../../includes/auth.php';
requireAdminAuth(); // Hanya admin yang bisa akses

include '../../includes/db.php';
$activePage = 'laporan_manajemen';
$baseURL = './';

// =====================================================================
// PERBAIKAN: SET TIMEZONE DAN TAMBAHKAN FUNGSI TANGGAL
// =====================================================================
date_default_timezone_set('Asia/Makassar'); // Set zona waktu ke WITA

/**
 * Fungsi untuk mengubah tanggal ke format Indonesia
 * @param string $format Format output (e.g., "d F Y" atau "l, d F Y")
 * @param string|null $timestamp Timestamp opsional (default: sekarang)
 * @return string Tanggal dalam format Indonesia
 */
function formatTanggalIndonesia($format, $timestamp = null) {
    $timestamp = $timestamp === null ? time() : (is_numeric($timestamp) ? $timestamp : strtotime($timestamp));
    
    // 1. Dapatkan string tanggal dalam bahasa Inggris dulu
    $date_str = date($format, $timestamp);
    
    // 2. Siapkan array terjemahan
    $hariInggris = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $hariIndonesia = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    
    $bulanInggris = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $bulanIndonesia = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    // 3. Terjemahkan string yang sudah jadi
    $date_str_id = str_replace($hariInggris, $hariIndonesia, $date_str);
    $date_str_id = str_replace($bulanInggris, $bulanIndonesia, $date_str_id);
    
    return $date_str_id;
}
// =====================================================================

// === AMBIL FILTER TAHUN ===
$filter_tahun = isset($_GET['tahun']) && $_GET['tahun'] != '' ? $_GET['tahun'] : 'semua';

// Query kondisi filter
$where_gelombang = "";
if($filter_tahun != 'semua') {
    $where_gelombang = " WHERE g.tahun = '$filter_tahun'";
}

// === AMBIL DAFTAR TAHUN UNTUK DROPDOWN ===
$tahun_list = mysqli_query($conn, "SELECT DISTINCT tahun FROM gelombang ORDER BY tahun DESC");

// === DATA PENDAFTARAN ===
$query_pendaftar = "SELECT COUNT(p.id_pendaftar) as total FROM pendaftar p JOIN gelombang g ON p.id_gelombang = g.id_gelombang" . $where_gelombang;
$total_pendaftar = mysqli_fetch_assoc(mysqli_query($conn, $query_pendaftar))['total'];

$query_belum_verif = "SELECT COUNT(p.id_pendaftar) as total FROM pendaftar p JOIN gelombang g ON p.id_gelombang = g.id_gelombang WHERE p.status_pendaftaran = 'Belum di Verifikasi'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
$pendaftar_belum_verif = mysqli_fetch_assoc(mysqli_query($conn, $query_belum_verif))['total'];

$query_terverifikasi = "SELECT COUNT(p.id_pendaftar) as total FROM pendaftar p JOIN gelombang g ON p.id_gelombang = g.id_gelombang WHERE p.status_pendaftaran = 'Terverifikasi'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
$pendaftar_terverifikasi = mysqli_fetch_assoc(mysqli_query($conn, $query_terverifikasi))['total'];

$query_diterima = "SELECT COUNT(p.id_pendaftar) as total FROM pendaftar p JOIN gelombang g ON p.id_gelombang = g.id_gelombang WHERE p.status_pendaftaran = 'Diterima'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
$pendaftar_diterima = mysqli_fetch_assoc(mysqli_query($conn, $query_diterima))['total'];

// === DATA SISWA ===
$query_siswa_aktif = "SELECT COUNT(s.id_siswa) as total FROM siswa s JOIN kelas k ON s.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang WHERE s.status_aktif = 'aktif'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
$total_siswa_aktif = mysqli_fetch_assoc(mysqli_query($conn, $query_siswa_aktif))['total'];

$query_alumni = "SELECT COUNT(s.id_siswa) as total FROM siswa s JOIN kelas k ON s.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang WHERE s.status_aktif = 'nonaktif'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
$total_alumni = mysqli_fetch_assoc(mysqli_query($conn, $query_alumni))['total'];

$query_laki = "SELECT COUNT(s.id_siswa) as total FROM siswa s JOIN kelas k ON s.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang WHERE s.jenis_kelamin = 'Laki-Laki' AND s.status_aktif = 'aktif'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
$siswa_laki = mysqli_fetch_assoc(mysqli_query($conn, $query_laki))['total'];

$query_perempuan = "SELECT COUNT(s.id_siswa) as total FROM siswa s JOIN kelas k ON s.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang WHERE s.jenis_kelamin = 'Perempuan' AND s.status_aktif = 'aktif'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
$siswa_perempuan = mysqli_fetch_assoc(mysqli_query($conn, $query_perempuan))['total'];

// === DATA INSTRUKTUR ===
$total_instruktur = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id_instruktur) as total FROM instruktur"))['total'];
$instruktur_aktif = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id_instruktur) as total FROM instruktur WHERE status_aktif = 'aktif'"))['total'];
$instruktur_nonaktif = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id_instruktur) as total FROM instruktur WHERE status_aktif = 'nonaktif'"))['total'];

// === DATA KELAS & GELOMBANG ===
$query_kelas = "SELECT COUNT(k.id_kelas) as total FROM kelas k JOIN gelombang g ON k.id_gelombang = g.id_gelombang" . $where_gelombang;
$total_kelas = mysqli_fetch_assoc(mysqli_query($conn, $query_kelas))['total'];

$query_gelombang_aktif = "SELECT COUNT(id_gelombang) as total FROM gelombang WHERE status = 'aktif'" . ($filter_tahun != 'semua' ? " AND tahun = '$filter_tahun'" : "");
$gelombang_aktif = mysqli_fetch_assoc(mysqli_query($conn, $query_gelombang_aktif))['total'];

$query_gelombang_selesai = "SELECT COUNT(id_gelombang) as total FROM gelombang WHERE status = 'selesai'" . ($filter_tahun != 'semua' ? " AND tahun = '$filter_tahun'" : "");
$gelombang_selesai = mysqli_fetch_assoc(mysqli_query($conn, $query_gelombang_selesai))['total'];

$query_kapasitas = "SELECT SUM(k.kapasitas) as total FROM kelas k JOIN gelombang g ON k.id_gelombang = g.id_gelombang" . $where_gelombang;
$total_kapasitas = mysqli_fetch_assoc(mysqli_query($conn, $query_kapasitas))['total'] ?? 0;
$utilisasi_kelas = ($total_kapasitas > 0) ? round(($total_siswa_aktif / $total_kapasitas) * 100, 1) : 0;

// === DATA KELULUSAN ===
$query_lulus = "SELECT COUNT(n.id_nilai) as total FROM nilai n JOIN siswa s ON n.id_siswa = s.id_siswa JOIN kelas k ON n.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang WHERE n.status_kelulusan = 'lulus'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
$total_lulus = mysqli_fetch_assoc(mysqli_query($conn, $query_lulus))['total'];

$query_tidak_lulus = "SELECT COUNT(n.id_nilai) as total FROM nilai n JOIN siswa s ON n.id_siswa = s.id_siswa JOIN kelas k ON n.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang WHERE n.status_kelulusan = 'tidak lulus'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
$total_tidak_lulus = mysqli_fetch_assoc(mysqli_query($conn, $query_tidak_lulus))['total'];
$total_dinilai = $total_lulus + $total_tidak_lulus;
$tingkat_kelulusan = ($total_dinilai > 0) ? round(($total_lulus / $total_dinilai) * 100, 1) : 0;

// === NILAI RATA-RATA KESELURUHAN ===
$query_avg = "SELECT AVG(n.rata_rata) as avg FROM nilai n JOIN siswa s ON n.id_siswa = s.id_siswa JOIN kelas k ON n.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang WHERE n.rata_rata IS NOT NULL" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
$avg_nilai = mysqli_fetch_assoc(mysqli_query($conn, $query_avg))['avg'];
$rata_rata_keseluruhan = $avg_nilai ? round($avg_nilai, 2) : 0;

// === DATA KEHADIRAN SISWA ===
$query_absen_siswa = "SELECT COUNT(abs.id_absen) as total FROM absensi_siswa abs JOIN siswa s ON abs.id_siswa = s.id_siswa JOIN kelas k ON s.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang" . ($filter_tahun != 'semua' ? " WHERE g.tahun = '$filter_tahun'" : "");
$total_absen_siswa = mysqli_fetch_assoc(mysqli_query($conn, $query_absen_siswa))['total'];

$query_siswa_hadir = "SELECT COUNT(abs.id_absen) as total FROM absensi_siswa abs JOIN siswa s ON abs.id_siswa = s.id_siswa JOIN kelas k ON s.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang WHERE abs.status = 'hadir'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
$siswa_hadir = mysqli_fetch_assoc(mysqli_query($conn, $query_siswa_hadir))['total'];

$query_siswa_izin = "SELECT COUNT(abs.id_absen) as total FROM absensi_siswa abs JOIN siswa s ON abs.id_siswa = s.id_siswa JOIN kelas k ON s.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang WHERE abs.status = 'izin'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
$siswa_izin = mysqli_fetch_assoc(mysqli_query($conn, $query_siswa_izin))['total'];

$query_siswa_sakit = "SELECT COUNT(abs.id_absen) as total FROM absensi_siswa abs JOIN siswa s ON abs.id_siswa = s.id_siswa JOIN kelas k ON s.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang WHERE abs.status = 'sakit'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
$siswa_sakit = mysqli_fetch_assoc(mysqli_query($conn, $query_siswa_sakit))['total'];

$query_siswa_alpha = "SELECT COUNT(abs.id_absen) as total FROM absensi_siswa abs JOIN siswa s ON abs.id_siswa = s.id_siswa JOIN kelas k ON s.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang WHERE abs.status = 'tanpa keterangan'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
$siswa_alpha = mysqli_fetch_assoc(mysqli_query($conn, $query_siswa_alpha))['total'];
$tingkat_kehadiran_siswa = ($total_absen_siswa > 0) ? round(($siswa_hadir / $total_absen_siswa) * 100, 1) : 0;

// === DATA KEHADIRAN INSTRUKTUR ===
$query_absen_instruktur = "SELECT COUNT(ai.id_absen) as total FROM absensi_instruktur ai JOIN jadwal j ON ai.id_jadwal = j.id_jadwal JOIN kelas k ON j.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang" . $where_gelombang;
$total_absen_instruktur = mysqli_fetch_assoc(mysqli_query($conn, $query_absen_instruktur))['total'];

$query_instruktur_hadir = "SELECT COUNT(ai.id_absen) as total FROM absensi_instruktur ai JOIN jadwal j ON ai.id_jadwal = j.id_jadwal JOIN kelas k ON j.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang WHERE ai.status = 'hadir'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
$instruktur_hadir = mysqli_fetch_assoc(mysqli_query($conn, $query_instruktur_hadir))['total'];

$query_instruktur_izin = "SELECT COUNT(ai.id_absen) as total FROM absensi_instruktur ai JOIN jadwal j ON ai.id_jadwal = j.id_jadwal JOIN kelas k ON j.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang WHERE ai.status = 'izin'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
$instruktur_izin = mysqli_fetch_assoc(mysqli_query($conn, $query_instruktur_izin))['total'];

$query_instruktur_sakit = "SELECT COUNT(ai.id_absen) as total FROM absensi_instruktur ai JOIN jadwal j ON ai.id_jadwal = j.id_jadwal JOIN kelas k ON j.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang WHERE ai.status = 'sakit'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
$instruktur_sakit_abs = mysqli_fetch_assoc(mysqli_query($conn, $query_instruktur_sakit))['total'];

$query_instruktur_alpha = "SELECT COUNT(ai.id_absen) as total FROM absensi_instruktur ai JOIN jadwal j ON ai.id_jadwal = j.id_jadwal JOIN kelas k ON j.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang WHERE ai.status = 'tanpa keterangan'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
$instruktur_alpha = mysqli_fetch_assoc(mysqli_query($conn, $query_instruktur_alpha))['total'];
$tingkat_kehadiran_instruktur = ($total_absen_instruktur > 0) ? round(($instruktur_hadir / $total_absen_instruktur) * 100, 1) : 0;

// === DATA EVALUASI ===
$query_evaluasi_aktif = "SELECT COUNT(pe.id_periode) as total FROM periode_evaluasi pe JOIN gelombang g ON pe.id_gelombang = g.id_gelombang WHERE pe.status = 'aktif'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
$evaluasi_aktif = mysqli_fetch_assoc(mysqli_query($conn, $query_evaluasi_aktif))['total'];

$query_evaluasi_draft = "SELECT COUNT(pe.id_periode) as total FROM periode_evaluasi pe JOIN gelombang g ON pe.id_gelombang = g.id_gelombang WHERE pe.status = 'draft'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
$evaluasi_draft = mysqli_fetch_assoc(mysqli_query($conn, $query_evaluasi_draft))['total'];

$query_evaluasi_selesai = "SELECT COUNT(pe.id_periode) as total FROM periode_evaluasi pe JOIN gelombang g ON pe.id_gelombang = g.id_gelombang WHERE pe.status = 'selesai'" . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "");
$evaluasi_selesai = mysqli_fetch_assoc(mysqli_query($conn, $query_evaluasi_selesai))['total'];
$total_evaluasi = $evaluasi_aktif + $evaluasi_draft + $evaluasi_selesai;

// === DATA MATERI ===
$query_materi = "SELECT COUNT(m.id_materi) as total FROM materi m JOIN kelas k ON m.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang" . $where_gelombang;
$total_materi = mysqli_fetch_assoc(mysqli_query($conn, $query_materi))['total'];

// === DATA JADWAL ===
$query_jadwal = "SELECT COUNT(j.id_jadwal) as total FROM jadwal j JOIN kelas k ON j.id_kelas = k.id_kelas JOIN gelombang g ON k.id_gelombang = g.id_gelombang" . $where_gelombang;
$total_jadwal = mysqli_fetch_assoc(mysqli_query($conn, $query_jadwal))['total'];

// === RASIO ===
$rasio_instruktur_siswa = ($instruktur_aktif > 0) ? round($total_siswa_aktif / $instruktur_aktif, 1) : 0;

// === TINGKAT KONVERSI ===
$total_siswa_keseluruhan = $total_siswa_aktif + $total_alumni;
$tingkat_konversi = ($total_pendaftar > 0) ? round(($total_siswa_keseluruhan / $total_pendaftar) * 100, 1) : 0;

// === DATA DETAIL PER GELOMBANG ===
$data_gelombang = mysqli_query($conn, "
    SELECT 
        g.id_gelombang, g.nama_gelombang, g.tahun, g.gelombang_ke, g.status,
        COUNT(DISTINCT k.id_kelas) as jumlah_kelas,
        COUNT(DISTINCT p.id_pendaftar) as jumlah_pendaftar,
        COUNT(DISTINCT s.id_siswa) as jumlah_siswa
    FROM gelombang g
    LEFT JOIN kelas k ON g.id_gelombang = k.id_gelombang
    LEFT JOIN pendaftar p ON g.id_gelombang = p.id_gelombang
    LEFT JOIN siswa s ON k.id_kelas = s.id_kelas
    " . $where_gelombang . "
    GROUP BY g.id_gelombang
    ORDER BY g.tahun DESC, g.gelombang_ke DESC
");

// === DATA DETAIL PER KELAS ===
$data_kelas_query = "
    SELECT 
        k.id_kelas, k.nama_kelas, k.kapasitas,
        g.nama_gelombang,
        i.nama as nama_instruktur,
        COUNT(DISTINCT s.id_siswa) as jumlah_siswa,
        COUNT(DISTINCT m.id_materi) as jumlah_materi,
        COUNT(DISTINCT j.id_jadwal) as jumlah_jadwal
    FROM kelas k
    LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
    LEFT JOIN instruktur i ON k.id_instruktur = i.id_instruktur
    LEFT JOIN siswa s ON k.id_kelas = s.id_kelas AND s.status_aktif = 'aktif'
    LEFT JOIN materi m ON k.id_kelas = m.id_kelas
    LEFT JOIN jadwal j ON k.id_kelas = j.id_kelas
    " . $where_gelombang . "
    GROUP BY k.id_kelas
    ORDER BY g.tahun DESC, k.nama_kelas ASC
";
$data_kelas = mysqli_query($conn, $data_kelas_query);

// === DATA INSTRUKTUR DETAIL ===
$instruktur_where = $filter_tahun != 'semua' ? "WHERE g.tahun = '$filter_tahun'" : "";
$data_instruktur = mysqli_query($conn, "
    SELECT 
        i.id_instruktur, i.nama, i.status_aktif, i.angkatan,
        COUNT(DISTINCT k.id_kelas) as jumlah_kelas,
        COUNT(DISTINCT m.id_materi) as jumlah_materi,
        COUNT(DISTINCT ai.id_absen) as total_absen,
        SUM(CASE WHEN ai.status = 'hadir' THEN 1 ELSE 0 END) as hadir
    FROM instruktur i
    LEFT JOIN kelas k ON i.id_instruktur = k.id_instruktur
    LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
    LEFT JOIN materi m ON i.id_instruktur = m.id_instruktur
    LEFT JOIN jadwal j ON k.id_kelas = j.id_kelas
    LEFT JOIN absensi_instruktur ai ON i.id_instruktur = ai.id_instruktur AND j.id_jadwal = ai.id_jadwal
    " . $instruktur_where . "
    GROUP BY i.id_instruktur
    ORDER BY i.status_aktif DESC, i.nama ASC
");

// === DATA KELULUSAN PER KELAS ===
$data_kelulusan_query = "
    SELECT 
        k.nama_kelas, g.nama_gelombang,
        COUNT(n.id_nilai) as total_dinilai,
        SUM(CASE WHEN n.status_kelulusan = 'lulus' THEN 1 ELSE 0 END) as lulus,
        SUM(CASE WHEN n.status_kelulusan = 'tidak lulus' THEN 1 ELSE 0 END) as tidak_lulus,
        AVG(n.rata_rata) as rata_rata_kelas
    FROM nilai n
    JOIN siswa s ON n.id_siswa = s.id_siswa
    JOIN kelas k ON n.id_kelas = k.id_kelas
    JOIN gelombang g ON k.id_gelombang = g.id_gelombang
    WHERE n.status_kelulusan IS NOT NULL
    " . ($filter_tahun != 'semua' ? " AND g.tahun = '$filter_tahun'" : "") . "
    GROUP BY k.id_kelas
    ORDER BY g.tahun DESC, k.nama_kelas ASC
";
$data_kelulusan = mysqli_query($conn, $data_kelulusan_query);

// === GRAFIK PENDAFTAR PER GELOMBANG ===
$grafik_pendaftar_query = "
    SELECT g.nama_gelombang, COUNT(p.id_pendaftar) as jumlah 
    FROM pendaftar p 
    JOIN gelombang g ON p.id_gelombang = g.id_gelombang 
    " . $where_gelombang . "
    GROUP BY p.id_gelombang 
    ORDER BY g.tahun, g.gelombang_ke
";
$grafik_pendaftar = mysqli_query($conn, $grafik_pendaftar_query);

$grafik_labels = [];
$grafik_data = [];
if ($grafik_pendaftar) {
    while ($row = mysqli_fetch_assoc($grafik_pendaftar)) {
        $grafik_labels[] = $row['nama_gelombang'];
        $grafik_data[] = $row['jumlah'];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Manajemen - LKP Pradata Komputer</title>
    <link rel="icon" type="image/png" href="../../assets/img/favicon.png"/>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="../../assets/css/bootstrap-icons.css" />
    <link rel="stylesheet" href="../../assets/css/fonts.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/styles.css" />
    
    <style>
        .font-roboto {
            font-family: 'Roboto', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif !important;
        }
        @media print {
            body { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .no-print, .sidebar, .top-navbar { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; }
            .card { box-shadow: none !important; page-break-inside: avoid; }
            h4, h5 { page-break-after: avoid; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
        }
        .report-header { text-align: center; margin-bottom: 30px; border-bottom: 3px double #333; padding-bottom: 15px; }
        .report-header img { max-height: 70px; margin-bottom: 10px; }
        .stat-card { border: 1px solid #dee2e6; border-radius: .3rem; padding: 12px; text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; margin-bottom: 15px; }
        .stat-card .stat-number { font-size: 1.8rem; font-weight: 700; }
        .stat-card .stat-label { font-size: 0.85rem; margin-top: 3px; opacity: 0.95; }
        .stat-card.bg-success-gradient { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .stat-card.bg-warning-gradient { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-card.bg-info-gradient { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-card.bg-danger-gradient { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .section-title { background-color: #0d6efd; color: white; padding: 10px 15px; border-radius: .3rem; margin-top: 30px; margin-bottom: 15px; font-size: 1.1rem; }
        table { font-size: 0.9rem; }
        .table thead th { background-color: #0d6efd; color: white; font-weight: 600; }
        .badge { font-size: 0.8rem; }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include '../../includes/sidebar/admin.php'; ?>
    
    <div class="flex-fill main-content">
        <!-- TOP NAVBAR -->
        <nav class="top-navbar no-print">
            <div class="container-fluid px-3 px-md-4">
                <div class="d-flex align-items-center">
                    <!-- Left: Hamburger + Page Info -->
                    <div class="d-flex align-items-center flex-grow-1">
                        <!-- Sidebar Toggle Button -->
                        <button class="btn btn-link text-dark p-2 me-3 sidebar-toggle" type="button" id="sidebarToggle">
                            <i class="bi bi-list fs-4"></i>
                        </button>
                        
                        <!-- Page Title & Breadcrumb -->
                        <div class="page-info">
                            <h2 class="page-title mb-1">LAPORAN MANAJEMEN</h2>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb page-breadcrumb mb-0">
                                    <li class="breadcrumb-item">
                                        <a href="dashboard.php">Dashboard</a>
                                    </li>
                                    <li class="breadcrumb-item active" aria-current="page">Executive Summary</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                    
                    <!-- Right: Optional Info -->
                    <div class="d-flex align-items-center">
                        <div class="navbar-page-info d-none d-md-block">
                            <small class="text-muted">
                                <i class="bi bi-calendar3 me-1"></i>
                                <?= formatTanggalIndonesia('d M Y') ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <div class="container-fluid mt-4 mb-5">

            <div class="alert alert-light border no-print mb-4">
                <form method="GET" action="laporan_manajemen.php" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-bold"><i class="bi bi-funnel me-2"></i>Filter Berdasarkan Tahun:</label>
                        <select name="tahun" class="form-select">
                            <option value="semua" <?= $filter_tahun == 'semua' ? 'selected' : '' ?>>Semua Tahun</option>
                            <?php 
                            mysqli_data_seek($tahun_list, 0);
                            while($tahun = mysqli_fetch_assoc($tahun_list)): 
                            ?>
                                <option value="<?= $tahun['tahun'] ?>" <?= $filter_tahun == $tahun['tahun'] ? 'selected' : '' ?>>
                                    Tahun <?= $tahun['tahun'] ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-2"></i>Tampilkan
                        </button>
                        <?php if($filter_tahun != 'semua'): ?>
                            <a href="laporan_manajemen.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-counterclockwise me-2"></i>Reset
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 text-end">
                        <div>
                            <i class="bi bi-calendar3 me-2"></i>
                            <?= $filter_tahun == 'semua' ? 'Menampilkan Semua Data' : 'Tahun ' . $filter_tahun ?>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="card content-card">
                <div class="card-header d-flex justify-content-between align-items-center no-print">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-bar-graph-fill me-2"></i>Laporan Manajemen Lembaga</h5>
                    <button onclick="cetakLaporanManajemen()" class="btn btn-cetak-soft">
                        <i class="bi bi-printer me-2"></i>Cetak Data
                    </button>
                </div>
                <div class="card-body p-4">
                    <div class="report-header">
                        <img src="../../assets/img/favicon.png" alt="Logo LKP Pradata">
                        <h1 class="h3 mb-1 fw-bold">LEMBAGA KURSUS DAN PELATIHAN PRADATA KOMPUTER</h1>
                        <p class="mb-0">Jl. Ketimun S. 21 No. 3A Komplek Pertamina. Tanjung - Tabalong - Kalimantan Selatan 71571</p>
                        <p class="mb-0 small text-muted">Telp: (0526) 2023798 | Email: awiekpradata@gmail.com | Website: www.pradatacomputer.ac.id</p>
                        <h2 class="h4 fw-bold mt-3 mb-1">LAPORAN MANAJEMEN LEMBAGA</h2>
                        <p class="mb-0"><strong>Executive Summary & Data Operasional</strong></p>
                        <?php if($filter_tahun != 'semua'): ?>
                            <p class="mb-0 fw-bold text-primary">Periode: Tahun <?= $filter_tahun ?></p>
                        <?php else: ?>
                            <p class="mb-0 fw-bold text-primary">Periode: Semua Tahun</p>
                        <?php endif; ?>
                        
                        <p class="small text-muted mb-0">Dicetak pada: <?= formatTanggalIndonesia('d F Y, H:i') ?> WITA</p>
                    </div>

                    <h4 class="section-title"><i class="bi bi-speedometer2 me-2"></i>I. RINGKASAN EKSEKUTIF</h4>
                    
                    <h6 class="fw-bold mt-3 mb-3">A. Sistem Pendaftaran & Penerimaan</h6>
                    <div class="row g-3">
                        <div class="col-md-3 col-6">
                            <div class="stat-card">
                                <div class="stat-number"><?= $total_pendaftar ?></div>
                                <div class="stat-label">Total Pendaftar</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-card bg-warning-gradient">
                                <div class="stat-number"><?= $pendaftar_belum_verif ?></div>
                                <div class="stat-label">Belum Diverifikasi</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-card bg-info-gradient">
                                <div class="stat-number"><?= $pendaftar_terverifikasi ?></div>
                                <div class="stat-label">Terverifikasi</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-card bg-success-gradient">
                                <div class="stat-number"><?= $pendaftar_diterima ?></div>
                                <div class="stat-label">Diterima</div>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold mt-4 mb-3">B. Data Peserta Didik</h6>
                    <div class="row g-3">
                        <div class="col-md-2 col-6">
                            <div class="stat-card bg-success-gradient">
                                <div class="stat-number"><?= $total_siswa_aktif ?></div>
                                <div class="stat-label">Siswa Aktif</div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6">
                            <div class="stat-card bg-info-gradient">
                                <div class="stat-number"><?= $total_alumni ?></div>
                                <div class="stat-label">Alumni</div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6">
                            <div class="stat-card">
                                <div class="stat-number"><?= $siswa_laki ?></div>
                                <div class="stat-label">Laki-Laki</div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6">
                            <div class="stat-card">
                                <div class="stat-number"><?= $siswa_perempuan ?></div>
                                <div class="stat-label">Perempuan</div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6">
                            <div class="stat-card bg-success-gradient">
                                <div class="stat-number"><?= $tingkat_kehadiran_siswa ?>%</div>
                                <div class="stat-label">Kehadiran</div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6">
                            <div class="stat-card bg-warning-gradient">
                                <div class="stat-number"><?= $tingkat_konversi ?>%</div>
                                <div class="stat-label">Konversi</div>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold mt-4 mb-3">C. Sumber Daya Pengajar</h6>
                    <div class="row g-3">
                        <div class="col-md-3 col-6">
                            <div class="stat-card">
                                <div class="stat-number"><?= $total_instruktur ?></div>
                                <div class="stat-label">Total Instruktur</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-card bg-success-gradient">
                                <div class="stat-number"><?= $instruktur_aktif ?></div>
                                <div class="stat-label">Aktif</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-card bg-info-gradient">
                                <div class="stat-number"><?= $tingkat_kehadiran_instruktur ?>%</div>
                                <div class="stat-label">Kehadiran</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-card bg-warning-gradient">
                                <div class="stat-number">1:<?= $rasio_instruktur_siswa ?></div>
                                <div class="stat-label">Rasio Mengajar</div>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold mt-4 mb-3">D. Fasilitas & Pembelajaran</h6>
                    <div class="row g-3">
                        <div class="col-md-2 col-6">
                            <div class="stat-card">
                                <div class="stat-number"><?= $total_kelas ?></div>
                                <div class="stat-label">Total Kelas</div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6">
                            <div class="stat-card bg-success-gradient">
                                <div class="stat-number"><?= $gelombang_aktif ?></div>
                                <div class="stat-label">Gelombang Aktif</div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6">
                            <div class="stat-card bg-info-gradient">
                                <div class="stat-number"><?= $utilisasi_kelas ?>%</div>
                                <div class="stat-label">Utilisasi</div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6">
                            <div class="stat-card">
                                <div class="stat-number"><?= $total_materi ?></div>
                                <div class="stat-label">Materi</div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6">
                            <div class="stat-card">
                                <div class="stat-number"><?= $total_jadwal ?></div>
                                <div class="stat-label">Jadwal</div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6">
                            <div class="stat-card">
                                <div class="stat-number"><?= $total_kapasitas ?></div>
                                <div class="stat-label">Kapasitas</div>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold mt-4 mb-3">E. Evaluasi & Pencapaian</h6>
                    <div class="row g-3">
                        <div class="col-md-3 col-6">
                            <div class="stat-card bg-success-gradient">
                                <div class="stat-number"><?= $evaluasi_aktif ?></div>
                                <div class="stat-label">Evaluasi Aktif</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-card">
                                <div class="stat-number"><?= $total_lulus ?></div>
                                <div class="stat-label">Total Lulus</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-card bg-warning-gradient">
                                <div class="stat-number"><?= $tingkat_kelulusan ?>%</div>
                                <div class="stat-label">Tingkat Kelulusan</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-card bg-info-gradient">
                                <div class="stat-number"><?= $rata_rata_keseluruhan ?></div>
                                <div class="stat-label">Rata-rata Nilai</div>
                            </div>
                        </div>
                    </div>

                    <h4 class="section-title"><i class="bi bi-calendar-event me-2"></i>II. DATA PER GELOMBANG</h4>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Gelombang</th>
                                    <th>Tahun</th>
                                    <th>Status</th>
                                    <th>Jumlah Kelas</th>
                                    <th>Jumlah Pendaftar</th>
                                    <th>Jumlah Siswa</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1;
                                mysqli_data_seek($data_gelombang, 0);
                                while($row = mysqli_fetch_assoc($data_gelombang)): 
                                    $status_badge = '';
                                    if($row['status'] == 'aktif') $status_badge = 'bg-success';
                                    elseif($row['status'] == 'selesai') $status_badge = 'bg-secondary';
                                    else $status_badge = 'bg-primary';
                                ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= $row['nama_gelombang'] ?></td>
                                    <td><?= $row['tahun'] ?></td>
                                    <td><span class="badge <?= $status_badge ?>"><?= ucfirst($row['status']) ?></span></td>
                                    <td><?= $row['jumlah_kelas'] ?></td>
                                    <td><?= $row['jumlah_pendaftar'] ?></td>
                                    <td><?= $row['jumlah_siswa'] ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <h4 class="section-title"><i class="bi bi-door-open me-2"></i>III. DATA PER KELAS</h4>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Kelas</th>
                                    <th>Gelombang</th>
                                    <th>Instruktur</th>
                                    <th>Kapasitas</th>
                                    <th>Terisi</th>
                                    <th>Utilisasi</th>
                                    <th>Jumlah Materi</th>
                                    <th>Jumlah Jadwal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1;
                                mysqli_data_seek($data_kelas, 0);
                                while($row = mysqli_fetch_assoc($data_kelas)): 
                                    $utilisasi = ($row['kapasitas'] > 0) ? round(($row['jumlah_siswa'] / $row['kapasitas']) * 100, 1) : 0;
                                    $badge_class = $utilisasi >= 80 ? 'bg-success' : ($utilisasi >= 50 ? 'bg-warning' : 'bg-danger');
                                ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><strong><?= $row['nama_kelas'] ?></strong></td>
                                    <td><?= $row['nama_gelombang'] ?></td>
                                    <td><?= $row['nama_instruktur'] ?? '-' ?></td>
                                    <td><?= $row['kapasitas'] ?></td>
                                    <td><?= $row['jumlah_siswa'] ?></td>
                                    <td><span class="badge <?= $badge_class ?>"><?= $utilisasi ?>%</span></td>
                                    <td><?= $row['jumlah_materi'] ?></td>
                                    <td><?= $row['jumlah_jadwal'] ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <h4 class="section-title"><i class="bi bi-person-badge me-2"></i>IV. DATA INSTRUKTUR</h4>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Instruktur</th>
                                    <th>Status</th>
                                    <th>Angkatan</th>
                                    <th>Jumlah Kelas Diampu</th>
                                    <th>Jumlah Materi</th>
                                    <th>Total Absen</th>
                                    <th>Tingkat Kehadiran</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1;
                                mysqli_data_seek($data_instruktur, 0);
                                while($row = mysqli_fetch_assoc($data_instruktur)): 
                                    $tingkat_hadir = ($row['total_absen'] > 0) ? round(($row['hadir'] / $row['total_absen']) * 100, 1) : 0;
                                    $status_badge = $row['status_aktif'] == 'aktif' ? 'bg-success' : 'bg-secondary';
                                    $hadir_badge = $tingkat_hadir >= 90 ? 'bg-success' : ($tingkat_hadir >= 75 ? 'bg-warning' : 'bg-danger');
                                ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><strong><?= $row['nama'] ?></strong></td>
                                    <td><span class="badge <?= $status_badge ?>"><?= ucfirst($row['status_aktif']) ?></span></td>
                                    <td><?= $row['angkatan'] ?? '-' ?></td>
                                    <td><?= $row['jumlah_kelas'] ?></td>
                                    <td><?= $row['jumlah_materi'] ?></td>
                                    <td><?= $row['total_absen'] ?></td>
                                    <td><span class="badge <?= $hadir_badge ?>"><?= $tingkat_hadir ?>%</span></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <h4 class="section-title"><i class="bi bi-trophy me-2"></i>V. DATA KELULUSAN PER KELAS</h4>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Kelas</th>
                                    <th>Gelombang</th>
                                    <th>Total Dinilai</th>
                                    <th>Lulus</th>
                                    <th>Tidak Lulus</th>
                                    <th>Tingkat Kelulusan</th>
                                    <th>Rata-rata Nilai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1;
                                mysqli_data_seek($data_kelulusan, 0);
                                while($row = mysqli_fetch_assoc($data_kelulusan)): 
                                    $persen_lulus = ($row['total_dinilai'] > 0) ? round(($row['lulus'] / $row['total_dinilai']) * 100, 1) : 0;
                                    $badge_lulus = $persen_lulus >= 80 ? 'bg-success' : ($persen_lulus >= 60 ? 'bg-warning' : 'bg-danger');
                                    $rata_nilai = $row['rata_rata_kelas'] ? round($row['rata_rata_kelas'], 2) : 0;
                                ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><strong><?= $row['nama_kelas'] ?></strong></td>
                                    <td><?= $row['nama_gelombang'] ?></td>
                                    <td><?= $row['total_dinilai'] ?></td>
                                    <td><?= $row['lulus'] ?></td>
                                    <td><?= $row['tidak_lulus'] ?></td>
                                    <td><span class="badge <?= $badge_lulus ?>"><?= $persen_lulus ?>%</span></td>
                                    <td><strong><?= $rata_nilai ?></strong></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <h4 class="section-title"><i class="bi bi-clipboard-check me-2"></i>VI. REKAP KEHADIRAN</h4>
                    
                    <h6 class="fw-bold mt-3 mb-3">A. Kehadiran Siswa</h6>
                    <div class="row g-3">
                        <div class="col-md-3 col-6">
                            <div class="stat-card bg-success-gradient">
                                <div class="stat-number"><?= $siswa_hadir ?></div>
                                <div class="stat-label">Hadir</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-card bg-info-gradient">
                                <div class="stat-number"><?= $siswa_izin ?></div>
                                <div class="stat-label">Izin</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-card bg-warning-gradient">
                                <div class="stat-number"><?= $siswa_sakit ?></div>
                                <div class="stat-label">Sakit</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-card bg-danger-gradient">
                                <div class="stat-number"><?= $siswa_alpha ?></div>
                                <div class="stat-label">Alpha</div>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold mt-4 mb-3">B. Kehadiran Instruktur</h6>
                    <div class="row g-3">
                        <div class="col-md-3 col-6">
                            <div class="stat-card bg-success-gradient">
                                <div class="stat-number"><?= $instruktur_hadir ?></div>
                                <div class="stat-label">Hadir</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-card bg-info-gradient">
                                <div class="stat-number"><?= $instruktur_izin ?></div>
                                <div class="stat-label">Izin</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-card bg-warning-gradient">
                                <div class="stat-number"><?= $instruktur_sakit_abs ?></div>
                                <div class="stat-label">Sakit</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-card bg-danger-gradient">
                                <div class="stat-number"><?= $instruktur_alpha ?></div>
                                <div class="stat-label">Alpha</div>
                            </div>
                        </div>
                    </div>

                    <h4 class="section-title"><i class="bi bi-bar-chart-line me-2"></i>VII. GRAFIK PENDAFTAR PER GELOMBANG</h4>
                    <div style="max-height: 400px;">
                        <canvas id="myBarChart"></canvas>
                    </div>

                    <h4 class="section-title mt-4"><i class="bi bi-lightbulb me-2"></i>VIII. ANALISIS & KESIMPULAN</h4>
                    
                    <div class="alert alert-primary">
                        <h6 class="fw-bold mb-3"><i class="bi bi-check-circle me-2"></i>Kinerja Positif</h6>
                        <ul class="mb-0">
                            <?php if($tingkat_kelulusan >= 80): ?>
                            <li>Tingkat kelulusan <strong><?= $tingkat_kelulusan ?>%</strong> menunjukkan kualitas pembelajaran yang baik</li>
                            <?php endif; ?>
                            <?php if($tingkat_kehadiran_instruktur >= 90): ?>
                            <li>Kehadiran instruktur <strong><?= $tingkat_kehadiran_instruktur ?>%</strong> menandakan dedikasi pengajar yang tinggi</li>
                            <?php endif; ?>
                            <?php if($tingkat_konversi >= 70): ?>
                            <li>Tingkat konversi pendaftar ke siswa <strong><?= $tingkat_konversi ?>%</strong> cukup efektif</li>
                            <?php endif; ?>
                            <?php if($utilisasi_kelas >= 70): ?>
                            <li>Utilisasi kelas <strong><?= $utilisasi_kelas ?>%</strong> menunjukkan optimalisasi fasilitas yang baik</li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <div class="alert alert-warning">
                        <h6 class="fw-bold mb-3"><i class="bi bi-exclamation-triangle me-2"></i>Area yang Perlu Perhatian</h6>
                        <ul class="mb-0">
                            <?php if($tingkat_kelulusan < 80): ?>
                            <li>Tingkat kelulusan <strong><?= $tingkat_kelulusan ?>%</strong> perlu ditingkatkan melalui pembimbingan intensif</li>
                            <?php endif; ?>
                            <?php if($tingkat_kehadiran_siswa < 85): ?>
                            <li>Kehadiran siswa <strong><?= $tingkat_kehadiran_siswa ?>%</strong> perlu ditingkatkan dengan sistem reward</li>
                            <?php endif; ?>
                            <?php if($utilisasi_kelas < 70): ?>
                            <li>Utilisasi kelas <strong><?= $utilisasi_kelas ?>%</strong> masih bisa dioptimalkan dengan strategi marketing</li>
                            <?php endif; ?>
                            <?php if($pendaftar_belum_verif > 0): ?>
                            <li>Terdapat <strong><?= $pendaftar_belum_verif ?> pendaftar</strong> yang belum diverifikasi, perlu tindak lanjut</li>
                            <?php endif; ?>
                            <?php if($rasio_instruktur_siswa > 20): ?>
                            <li>Rasio instruktur:siswa <strong>1:<?= $rasio_instruktur_siswa ?></strong> perlu penambahan instruktur</li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <div class="alert alert-success">
                        <h6 class="fw-bold mb-3"><i class="bi bi-graph-up-arrow me-2"></i>Ringkasan Statistik Kunci</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="mb-0">
                                    <li>Total Pendaftar: <strong><?= $total_pendaftar ?> orang</strong></li>
                                    <li>Siswa Aktif: <strong><?= $total_siswa_aktif ?> orang</strong></li>
                                    <li>Alumni: <strong><?= $total_alumni ?> orang</strong></li>
                                    <li>Instruktur Aktif: <strong><?= $instruktur_aktif ?> orang</strong></li>
                                    <li>Total Kelas: <strong><?= $total_kelas ?> kelas</strong></li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="mb-0">
                                    <li>Tingkat Konversi: <strong><?= $tingkat_konversi ?>%</strong></li>
                                    <li>Tingkat Kelulusan: <strong><?= $tingkat_kelulusan ?>%</strong></li>
                                    <li>Rata-rata Nilai: <strong><?= $rata_rata_keseluruhan ?></strong></li>
                                    <li>Utilisasi Kelas: <strong><?= $utilisasi_kelas ?>%</strong></li>
                                    <li>Rasio Instruktur:Siswa: <strong>1:<?= $rasio_instruktur_siswa ?></strong></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 pt-4 border-top">
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Disusun oleh:</strong></p>
                                <p class="mb-0">Tim Manajemen LKP Pradata Komputer</p>
                            </div>
                            <div class="col-md-6 text-end">
                                <p class="mb-1">Tabalong, <?= formatTanggalIndonesia('d F Y') ?></p>
                                <p class="mb-5"><strong>Kepala Lembaga</strong></p>
                                <p class="mb-0 mt-4">Awiek Hadi Widodo</p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../../assets/js/bootstrap.bundle.min.js"></script>
<script src="../../assets/js/scripts.js"></script>
<script>

function cetakLaporanManajemen() {
    const tahun = '<?= $filter_tahun ?>';
    let url = 'cetak_laporan.php';
    if(tahun !== 'semua') {
        url += '?tahun=' + tahun;
    }
    window.open(url, '_blank');
}

document.addEventListener("DOMContentLoaded", function() {
    new Chart(document.getElementById("myBarChart"), {
        type: 'bar',
        data: {
            labels: <?= json_encode($grafik_labels) ?>,
            datasets: [{
                label: "Jumlah Pendaftar",
                backgroundColor: "rgba(13, 110, 253, 0.8)",
                borderColor: "rgba(13, 110, 253, 1)",
                borderWidth: 2,
                data: <?= json_encode($grafik_data) ?>,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { 
                legend: { 
                    display: true, 
                    position: 'top',
                    labels: { font: { size: 12 } }
                },
                title: { display: false }
            },
            scales: { 
                y: { 
                    beginAtZero: true,
                    ticks: { 
                        stepSize: 1,
                        callback: function(value) { 
                            if (Number.isInteger(value)) return value; 
                        } 
                    },
                    grid: { display: true, color: 'rgba(0,0,0,0.1)' }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
});

</script>
</body>
</html>