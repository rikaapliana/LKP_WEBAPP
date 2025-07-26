<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'hasil-evaluasi'; 
$baseURL = '../';

// Validasi parameter
if (!isset($_GET['id_periode']) || !is_numeric($_GET['id_periode'])) {
    $_SESSION['error'] = "ID periode evaluasi tidak valid.";
    header("Location: index.php");
    exit;
}

$id_periode = (int)$_GET['id_periode'];

// Ambil data periode evaluasi
$periodeQuery = "SELECT 
                    pe.*,
                    g.nama_gelombang,
                    g.tahun,
                    COUNT(DISTINCT e.id_evaluasi) as total_mengerjakan,
                    COUNT(DISTINCT CASE WHEN e.status_evaluasi = 'selesai' THEN e.id_evaluasi END) as selesai,
                    (SELECT COUNT(DISTINCT s.id_siswa) 
                     FROM siswa s 
                     JOIN kelas k ON s.id_kelas = k.id_kelas 
                     WHERE k.id_gelombang = pe.id_gelombang AND s.status_aktif = 'aktif') as total_siswa_aktif
                 FROM periode_evaluasi pe
                 LEFT JOIN gelombang g ON pe.id_gelombang = g.id_gelombang
                 LEFT JOIN evaluasi e ON pe.id_periode = e.id_periode
                 WHERE pe.id_periode = $id_periode
                 GROUP BY pe.id_periode";

$periodeResult = mysqli_query($conn, $periodeQuery);

if (!$periodeResult || mysqli_num_rows($periodeResult) == 0) {
    $_SESSION['error'] = "Periode evaluasi tidak ditemukan.";
    header("Location: index.php");
    exit;
}

$periode = mysqli_fetch_assoc($periodeResult);

// Ambil pertanyaan yang terpilih untuk periode ini
$pertanyaanList = [];
$pertanyaan_terpilih = [];

if ($periode['pertanyaan_terpilih']) {
    $pertanyaan_terpilih = json_decode($periode['pertanyaan_terpilih'], true);
    if (!is_array($pertanyaan_terpilih)) {
        $pertanyaan_terpilih = [];
    }
}

if (!empty($pertanyaan_terpilih)) {
    // Ambil pertanyaan dengan urutan yang benar
    $pertanyaan_ids = implode(',', array_map('intval', $pertanyaan_terpilih));
    $pertanyaanQuery = "SELECT p.id_pertanyaan, p.pertanyaan, p.aspek_dinilai, p.tipe_jawaban, p.pilihan_jawaban
                        FROM pertanyaan_evaluasi p
                        WHERE p.id_pertanyaan IN ($pertanyaan_ids)";
    
    $pertanyaanResult = mysqli_query($conn, $pertanyaanQuery);
    
    // Simpan hasil query dalam array dengan key id_pertanyaan
    $pertanyaanFromDB = [];
    while ($pertanyaan = mysqli_fetch_assoc($pertanyaanResult)) {
        $pertanyaanFromDB[$pertanyaan['id_pertanyaan']] = $pertanyaan;
    }
    
    // Susun ulang sesuai urutan di pertanyaan_terpilih
    foreach ($pertanyaan_terpilih as $id_pertanyaan) {
        if (isset($pertanyaanFromDB[$id_pertanyaan])) {
            $pertanyaanList[] = $pertanyaanFromDB[$id_pertanyaan];
        }
    }
}

// Ambil data siswa yang sudah selesai evaluasi
$siswaJawabanQuery = "SELECT 
                        s.id_siswa,
                        s.nama,
                        s.nik,
                        k.nama_kelas,
                        e.id_evaluasi,
                        e.status_evaluasi,
                        e.tanggal_evaluasi
                      FROM siswa s
                      JOIN kelas k ON s.id_kelas = k.id_kelas
                      JOIN evaluasi e ON s.id_siswa = e.id_siswa AND e.id_periode = ?
                      WHERE k.id_gelombang = ? AND s.status_aktif = 'aktif' AND e.status_evaluasi = 'selesai'
                      ORDER BY k.nama_kelas, s.nama";

$siswaStmt = mysqli_prepare($conn, $siswaJawabanQuery);
mysqli_stmt_bind_param($siswaStmt, "ii", $id_periode, $periode['id_gelombang']);
mysqli_stmt_execute($siswaStmt);
$siswaResult = mysqli_stmt_get_result($siswaStmt);

$siswaData = [];
while ($siswa = mysqli_fetch_assoc($siswaResult)) {
    $siswaData[] = $siswa;
}

// Ambil semua jawaban untuk periode ini (hanya yang selesai)
$jawabanQuery = "SELECT 
                   je.id_siswa,
                   je.id_pertanyaan,
                   je.jawaban
                 FROM jawaban_evaluasi je
                 JOIN evaluasi e ON je.id_evaluasi = e.id_evaluasi
                 WHERE e.id_periode = ? AND e.status_evaluasi = 'selesai'";

$jawabanStmt = mysqli_prepare($conn, $jawabanQuery);
mysqli_stmt_bind_param($jawabanStmt, "i", $id_periode);
mysqli_stmt_execute($jawabanStmt);
$jawabanResult = mysqli_stmt_get_result($jawabanStmt);

// Organize jawaban by siswa and pertanyaan
$jawabanMatrix = [];
while ($jawaban = mysqli_fetch_assoc($jawabanResult)) {
    $jawabanMatrix[$jawaban['id_siswa']][$jawaban['id_pertanyaan']] = $jawaban['jawaban'];
}

// Helper functions
function formatTanggalIndonesia($tanggal) {
    if (!$tanggal) return '-';
    
    $bulan = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Ags',
        9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
    ];
    
    $timestamp = strtotime($tanggal);
    $hari = date('d', $timestamp);
    $bulan_nama = $bulan[(int)date('m', $timestamp)];
    $tahun = date('Y', $timestamp);
    $jam = date('H:i', $timestamp);
    
    return "$hari $bulan_nama $tahun, $jam";
}

function getPilihanJawaban($pilihan_jawaban) {
    if (empty($pilihan_jawaban)) return [];
    $decoded = json_decode($pilihan_jawaban, true);
    return is_array($decoded) ? $decoded : [];
}

function getNamaMateri($materi) {
    switch($materi) {
        case 'word': return 'Microsoft Word';
        case 'excel': return 'Microsoft Excel';
        case 'ppt': return 'Microsoft PowerPoint';
        case 'internet': return 'Internet & Email';
        default: return ucfirst($materi);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Evaluasi - <?= htmlspecialchars($periode['nama_evaluasi']) ?></title>
    <link rel="icon" type="image/png" href="../../../assets/img/favicon.png"/>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="../../../assets/css/bootstrap-icons.css" />
    <link rel="stylesheet" href="../../../assets/css/fonts.css" />
    <link rel="stylesheet" href="../../../assets/css/styles.css" />
    
    <style>
   

    /* Controls */
    .controls-section {
        background: #f8f9fa;
        padding: 1.25rem;
        border-bottom: 1px solid #e9ecef;
    }

    .search-container {
        position: relative;
        max-width: 300px;
    }

    .search-input {
        border: 1px solid #ced4da;
        border-radius: 0.5rem;
        padding: 0.5rem 2.5rem 0.5rem 1rem;
        font-size: 0.9rem;
    }

    .search-icon {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: #6c757d;
    }

    .filter-select {
        border: 1px solid #ced4da;
        border-radius: 0.5rem;
        padding: 0.5rem 0.75rem;
        font-size: 0.9rem;
        min-width: 150px;
    }

    /* Table Container - Perbaikan utama */
    .table-responsive {
        position: relative;
        overflow-x: auto;
        overflow-y: visible;
        border: 1px solid #dee2e6;
        border-radius: 0.75rem;
        max-height: 80vh;
    }

    /* Table Structure */
    .evaluation-table {
        margin: 0;
        border: none;
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
        min-width: max-content;
        transform: translateZ(0); /* Hardware acceleration */
    }

    /* Header Styling - Diperbaiki */
    .evaluation-table thead th {
        background: #f8f9fa !important;
        border: none;
        border-bottom: 2px solid #dee2e6;
        border-right: 1px solid #e9ecef;
        font-weight: 600;
        color: #495057;
        padding: 1rem 0.75rem;
        text-align: center;
        vertical-align: middle;
        font-size: 0.85rem;
        position: sticky;
        top: 0;
        z-index: 10;
        white-space: nowrap;
    }

    /* Body Cell Styling */
    .evaluation-table tbody td {
        border: 1px solid #e9ecef;
        border-top: none;
        padding: 0.75rem;
        vertical-align: middle;
        font-size: 0.85rem;
        background: white;
        position: relative;
    }

    .evaluation-table tbody tr:hover td {
        background-color: #f8f9fa;
    }

    /* Sticky Columns - PERBAIKAN UTAMA */
    .sticky-col {
        position: sticky !important;
        background: inherit;
        z-index: 20;
        border-right: 2px solid #dee2e6 !important;
        box-shadow: 2px 0 5px -2px rgba(0, 0, 0, 0.1);
        transform: translateZ(0); /* Hardware acceleration */
    }

    /* Header sticky columns - z-index tertinggi */
    .evaluation-table thead .sticky-col {
        z-index: 30 !important;
        background: #f8f9fa !important;
    }

    /* Body sticky columns */
    .evaluation-table tbody .sticky-col {
        background: white !important;
    }

    .evaluation-table tbody tr:hover .sticky-col {
        background: #f8f9fa !important;
    }

    /* Posisi sticky columns yang diperbaiki */
    .sticky-col.number {
        left: 0;
        width: 60px;
        min-width: 60px;
        max-width: 60px;
        text-align: center;
    }

    .sticky-col.student {
        left: 60px;
        width: 250px;    /* Diperbesar sedikit */
        min-width: 250px;
        max-width: 250px;
    }

    .sticky-col.class {
        left: 310px;     /* Disesuaikan dengan lebar student column */
        width: 180px;    /* Diperbesar untuk nama kelas yang panjang */
        min-width: 180px;
        max-width: 180px;
        text-align: center;
        padding: 0.5rem; /* Padding yang cukup */
    }

    /* Question Headers - Diperbaiki dengan preview soal */
    .question-header {
        text-align: center;
        line-height: 1.3;
        padding: 0.75rem 0.4rem;
        width: 170px;    /* Sedikit diperbesar */
        min-width: 170px;
        max-width: 170px;
        cursor: pointer;
        transition: background-color 0.2s;
    }

    .question-header:hover {
        background-color: #e9ecef !important;
    }

    .question-header.highlighted {
        background-color: #fff3cd !important;
        border: 2px solid #ffc107 !important;
        transform: scale(1.02);
        transition: all 0.3s ease;
    }

    .question-number {
        font-weight: bold;
        color: #495057;
        margin-bottom: 0.5rem;
        font-size: 0.95rem;
    }

    .question-type {
        display: inline-block;
        padding: 0.2rem 0.6rem;
        border-radius: 0.3rem;
        font-size: 0.7rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .question-type.skala {
        background: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
    }

    .question-type.pilihan_ganda {
        background: #cce5ff;
        color: #0c5460;
        border: 1px solid #b3d9ff;
    }

    .question-type.isian {
        background: #d4edda;
        color: #155724;
        border: 1px solid #a3d9a5;
    }

    /* Preview soal yang lebih baik */
    .question-preview {
        font-size: 0.7rem;
        color: #495057;
        line-height: 1.4;
        cursor: pointer;
        transition: all 0.2s;
        display: -webkit-box;
        -webkit-line-clamp: 3; /* Maksimal 3 baris */
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
        height: auto;
        max-height: 4.2rem; /* 3 baris x 1.4 line-height */
        padding: 0.25rem;
        background: rgba(255, 255, 255, 0.8);
        border-radius: 0.25rem;
        border: 1px solid rgba(0, 0, 0, 0.1);
    }

    .question-preview:hover {
        color: #212529;
        background: rgba(255, 255, 255, 0.95);
        border-color: rgba(0, 0, 0, 0.2);
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .question-header.highlighted .question-preview {
        background: rgba(255, 193, 7, 0.1);
        border-color: #ffc107;
    }

    /* Student Info - diperbaiki overflow */
    .student-info {
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
        width: 100%;
        overflow: hidden;
        padding: 0.25rem;
    }

    .student-name {
        font-weight: 600;
        color: #212529;
        font-size: 0.9rem;
        line-height: 1.3;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }

    .student-details {
        font-size: 0.72rem;
        color: #6c757d;
        line-height: 1.4;
        word-wrap: break-word;
    }

    /* Perbaikan class badge agar tidak overflow */
    .class-container {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
        padding: 0.5rem 0.25rem;
    }

    .class-badge {
        background: #e3f2fd;
        color: #1565c0;
        padding: 0.4rem 0.8rem;
        border-radius: 1rem;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
        white-space: nowrap;
        border: 1px solid #bbdefb;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.2;
    }

    /* Untuk kelas dengan nama panjang, gunakan wrapping */
    .class-badge.long-name {
        white-space: normal;
        word-wrap: break-word;
        text-align: center;
        line-height: 1.3;
        padding: 0.3rem 0.6rem;
    }

    /* Khusus untuk kelas dengan nama sangat panjang */
    .class-badge.extra-long {
        font-size: 0.7rem;
        padding: 0.3rem 0.5rem;
        line-height: 1.2;
        max-width: calc(100% - 0.5rem);
        white-space: normal;
        word-break: break-word;
        text-align: center;
    }

    /* Answer Displays */
    .answer-container {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 60px;
        padding: 0.5rem;
        width: 100%;
    }

    /* Rating Display */
    .rating-answer {
        text-align: center;
        cursor: pointer;
        padding: 0.6rem 0.5rem;
        border-radius: 0.5rem;
        transition: all 0.2s;
        background: #fff8e1;
        border: 1px solid #ffecb3;
        width: 100%;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .rating-answer:hover {
        background: #fff3c4;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    .rating-number {
        font-size: 1.4rem;
        font-weight: bold;
        color: #f57f17;
        margin-bottom: 0.3rem;
    }

    .rating-stars {
        display: flex;
        justify-content: center;
        gap: 2px;
        margin-bottom: 0.3rem;
    }

    .rating-label {
        font-size: 0.65rem;
        color: #795548;
        font-weight: 500;
    }

    /* Multiple Choice Display */
    .choice-answer {
        text-align: center;
        cursor: pointer;
        padding: 0.6rem 0.5rem;
        border-radius: 0.5rem;
        transition: all 0.2s;
        background: #f3e5f5;
        border: 1px solid #e1bee7;
        width: 100%;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .choice-answer:hover {
        background: #e8eaf6;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    .choice-letter {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 26px;
        height: 26px;
        background: #7b1fa2;
        color: white;
        border-radius: 50%;
        font-weight: bold;
        font-size: 0.85rem;
        margin-bottom: 0.4rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .choice-preview {
        font-size: 0.65rem;
        color: #4a148c;
        line-height: 1.3;
        font-weight: 500;
    }

    /* Text Answer Display */
    .text-answer {
        text-align: center;
        cursor: pointer;
        padding: 0.6rem 0.5rem;
        border-radius: 0.5rem;
        transition: all 0.2s;
        background: #e8f5e8;
        border: 1px solid #c8e6c9;
        width: 100%;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .text-answer:hover {
        background: #dcedc8;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    .text-icon {
        color: #388e3c;
        font-size: 1.2rem;
        margin-bottom: 0.4rem;
    }

    .text-preview {
        font-size: 0.65rem;
        color: #2e7d32;
        line-height: 1.3;
        margin-bottom: 0.3rem;
        font-weight: 500;
    }

    .text-meta {
        font-size: 0.6rem;
        color: #558b2f;
    }

    /* Empty Answer */
    .empty-answer {
        text-align: center;
        color: #9e9e9e;
        font-style: italic;
        font-size: 0.75rem;
        padding: 1rem 0.5rem;
    }

    /* Modal Styling */
    .modal-content {
        border: none;
        border-radius: 0.75rem;
        box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.175);
    }

    .modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 0.75rem 0.75rem 0 0;
        padding: 1.25rem 1.5rem;
    }

    .modal-title {
        font-weight: 600;
    }

    .btn-close {
        filter: invert(1);
    }

    .modal-body {
        padding: 1.5rem;
    }

    .question-full {
        background: #f8f9fa;
        padding: 1.25rem;
        border-radius: 0.5rem;
        border-left: 4px solid #667eea;
        margin-bottom: 1.5rem;
    }

    .question-meta {
        display: flex;
        gap: 0.75rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }

    .meta-item {
        background: white;
        padding: 0.5rem 0.75rem;
        border-radius: 0.375rem;
        border: 1px solid #e9ecef;
        font-size: 0.8rem;
    }

    .meta-label {
        color: #6c757d;
        font-weight: 500;
    }

    .meta-value {
        color: #212529;
        font-weight: 600;
        margin-left: 0.5rem;
    }

    .answer-content {
        background: white;
        padding: 1.25rem;
        border-radius: 0.5rem;
        border: 1px solid #e9ecef;
    }

    .answer-label {
        color: #495057;
        font-weight: 600;
        margin-bottom: 0.75rem;
        font-size: 0.9rem;
    }

    .answer-text {
        color: #212529;
        line-height: 1.6;
        font-size: 0.95rem;
    }

    .choices-list {
        margin-top: 1rem;
    }

    .choice-item {
        display: flex;
        align-items: center;
        padding: 0.75rem;
        margin-bottom: 0.5rem;
        border-radius: 0.375rem;
        border: 1px solid #e9ecef;
        transition: all 0.2s;
    }

    .choice-item.selected {
        background: #e8f5e8;
        border-color: #4caf50;
    }

    .choice-item .choice-letter {
        margin-right: 0.75rem;
        margin-bottom: 0;
    }

    .choice-item.selected .choice-letter {
        background: #4caf50;
    }

    .choice-item .choice-text {
        flex: 1;
        font-size: 0.9rem;
        color: #212529;
    }

    .choice-item.selected .choice-text {
        font-weight: 500;
    }

    .choice-check {
        color: #4caf50;
        font-weight: bold;
        margin-left: 0.5rem;
    }

    /* Stats Info */
    .stats-info {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 0.5rem;
        margin-bottom: 1rem;
        border: 1px solid #e9ecef;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 1rem;
    }

    .stat-item {
        text-align: center;
    }

    .stat-number {
        font-size: 1.5rem;
        font-weight: bold;
        color: #495057;
        margin-bottom: 0.25rem;
    }

    .stat-label {
        font-size: 0.75rem;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 500;
    }

    /* Responsive untuk tablet */
    @media (max-width: 992px) {
        .sticky-col.student {
            width: 220px;
            min-width: 220px;
            max-width: 220px;
        }
        
        .sticky-col.class {
            left: 280px;
            width: 160px;
            min-width: 160px;
            max-width: 160px;
        }
        
        .question-header {
            width: 150px;
            min-width: 150px;
            max-width: 150px;
        }
    }

    /* Responsive untuk mobile */
    @media (max-width: 768px) {
        .sticky-col.student {
            width: 200px;
            min-width: 200px;
            max-width: 200px;
        }
        
        .sticky-col.class {
            left: 260px;
            width: 140px;
            min-width: 140px;
            max-width: 140px;
        }
        
        .question-header {
            width: 130px;
            min-width: 130px;
            max-width: 130px;
        }
        
        .class-badge {
            font-size: 0.7rem;
            padding: 0.3rem 0.6rem;
        }
        
        .controls-section {
            padding: 1rem;
        }
        
        .controls-section .row > div {
            margin-bottom: 0.75rem;
        }
    }

    @media (max-width: 576px) {
        .sticky-col.student {
            width: 180px;
            min-width: 180px;
            max-width: 180px;
        }
        
        .sticky-col.class {
            left: 240px;
            width: 120px;
            min-width: 120px;
            max-width: 120px;
        }
        
        .question-header {
            width: 110px;
            min-width: 110px;
            max-width: 110px;
        }
        
        .class-badge {
            font-size: 0.65rem;
            padding: 0.25rem 0.5rem;
        }
    }

    /* Loading & Empty States */
    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: #6c757d;
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    .loading-spinner {
        display: flex;
        justify-content: center;
        align-items: center;
        height: 200px;
    }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php include '../../../includes/sidebar/admin.php'; ?>

        <div class="flex-fill main-content">
            <!-- TOP NAVBAR -->
            <nav class="top-navbar">
                <div class="container-fluid px-3 px-md-4">
                    <div class="d-flex align-items-center">
                        <div class="d-flex align-items-center flex-grow-1">
                            <button class="btn btn-link text-dark p-2 me-3 sidebar-toggle" type="button" id="sidebarToggle">
                                <i class="bi bi-list fs-4"></i>
                            </button>
                            
                            <div class="page-info">
                                <h2 class="page-title mb-1">HASIL EVALUASI</h2>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb page-breadcrumb mb-0">
                                        <li class="breadcrumb-item">
                                            <a href="../dashboard.php">Dashboard</a>
                                        </li>
                                        <li class="breadcrumb-item">
                                            <a href="index.php">Evaluasi</a>
                                        </li>
                                        <li class="breadcrumb-item active" aria-current="page">Hasil</li>
                                    </ol>
                                </nav>
                            </div>
                        </div>
                        
                        <div class="d-flex align-items-center">
                            <div class="navbar-page-info d-none d-md-block">
                                <small class="text-muted">
                                    <i class="bi bi-calendar3 me-1"></i>
                                    <?= date('d M Y') ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="container-fluid mt-4">
                <!-- Alert Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i>
                        <?= $_SESSION['success'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?= $_SESSION['error'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- Header Info -->
                <div class="card content-card mb-4">
                    <div class="section-header">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5 class="mb-1">
                                    <i class="bi bi-clipboard-data me-2"></i>
                                    <?= htmlspecialchars($periode['nama_evaluasi']) ?>
                                </h5>
                                <div class="small opacity-75">
                                    <?= htmlspecialchars($periode['nama_gelombang']) ?> (<?= $periode['tahun'] ?>)
                                    <?php if($periode['materi_terkait']): ?>
                                        • <?= getNamaMateri($periode['materi_terkait']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                                <div class="d-flex align-items-center justify-content-end gap-2">
                                    <a href="index.php" class="btn btn-light btn-sm">
                                        <i class="bi bi-arrow-left me-1"></i>Kembali
                                    </a>
                                    <button type="button" class="btn btn-success btn-sm" onclick="exportToExcel()">
                                        <i class="bi bi-file-excel me-1"></i>Export
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="stats-info">
                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-number"><?= count($siswaData) ?></div>
                                <div class="stat-label">Siswa Selesai</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?= count($pertanyaanList) ?></div>
                                <div class="stat-label">Pertanyaan</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?= array_sum(array_map(function($p) { return $p['tipe_jawaban'] == 'skala' ? 1 : 0; }, $pertanyaanList)) ?></div>
                                <div class="stat-label">Rating</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?= array_sum(array_map(function($p) { return $p['tipe_jawaban'] == 'pilihan_ganda' ? 1 : 0; }, $pertanyaanList)) ?></div>
                                <div class="stat-label">Pilihan Ganda</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?= array_sum(array_map(function($p) { return $p['tipe_jawaban'] == 'isian' ? 1 : 0; }, $pertanyaanList)) ?></div>
                                <div class="stat-label">Isian</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Controls -->
                <div class="card content-card mb-4">
                    <div class="controls-section">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="search-container">
                                    <input type="search" id="searchInput" class="form-control search-input" placeholder="Cari nama atau NIK siswa...">
                                    <i class="bi bi-search search-icon"></i>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select id="kelasFilter" class="form-select filter-select">
                                    <option value="">Semua Kelas</option>
                                    <?php 
                                    $kelasList = array_unique(array_column($siswaData, 'nama_kelas'));
                                    sort($kelasList);
                                    foreach($kelasList as $kelas): ?>
                                        <option value="<?= htmlspecialchars($kelas) ?>"><?= htmlspecialchars($kelas) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 text-md-end">
                                <div class="text-muted small">
                                    <span id="showingInfo">Menampilkan <?= count($siswaData) ?> dari <?= count($siswaData) ?> data</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Table -->
                <div class="card content-card">
                    <div class="table-responsive">
                        <table class="table evaluation-table" id="evaluationTable">
                            <thead>
                                <tr>
                                    <th class="sticky-col number">No</th>
                                    <th class="sticky-col student">Siswa</th>
                                    <th class="sticky-col class">Kelas</th>
                                  <!-- Ganti bagian header pertanyaan -->
<?php foreach ($pertanyaanList as $index => $pertanyaan): ?>
    <th class="question-header" onclick="showQuestionDetail(<?= $pertanyaan['id_pertanyaan'] ?>, <?= json_encode($pertanyaan['pertanyaan']) ?>, <?= json_encode($pertanyaan['aspek_dinilai']) ?>, <?= json_encode($pertanyaan['tipe_jawaban']) ?>)">
        <div class="question-number">Q<?= $index + 1 ?></div>
        <div class="question-type <?= $pertanyaan['tipe_jawaban'] ?>">
            <?php
            switch($pertanyaan['tipe_jawaban']) {
                case 'skala': echo 'Rating'; break;
                case 'pilihan_ganda': echo 'Pilihan'; break;
                case 'isian': echo 'Isian'; break;
            }
            ?>
        </div>
        <div class="question-preview" title="<?= htmlspecialchars($pertanyaan['pertanyaan']) ?>">
            <?= htmlspecialchars($pertanyaan['pertanyaan']) ?>
        </div>
    </th>
<?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <?php if (!empty($siswaData)): ?>
                                    <?php foreach ($siswaData as $index => $siswa): ?>
                                        <tr class="student-row" 
                                            data-nama="<?= strtolower($siswa['nama']) ?>" 
                                            data-nik="<?= $siswa['nik'] ?>"
                                            data-kelas="<?= $siswa['nama_kelas'] ?>">
                                            
                                            <!-- Nomor -->
                                            <td class="sticky-col number">
                                                <strong><?= $index + 1 ?></strong>
                                            </td>
                                            
                                            <!-- Data Siswa -->
                                            <td class="sticky-col student">
                                                <div class="student-info">
                                                    <div class="student-name"><?= htmlspecialchars($siswa['nama']) ?></div>
                                                    <div class="student-details">
                                                        NIK: <?= htmlspecialchars($siswa['nik']) ?><br>
                                                        <i class="bi bi-calendar-event me-1"></i>
                                                        <?= formatTanggalIndonesia($siswa['tanggal_evaluasi']) ?>
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            <!-- Kelas -->
                                            <td class="sticky-col class">
                                                <div class="class-container">
                                                    <?php 
                                                    $namaKelas = htmlspecialchars($siswa['nama_kelas']);
                                                    $kelasLength = strlen($namaKelas);
                                                    
                                                    // Tentukan class CSS berdasarkan panjang nama kelas
                                                    if ($kelasLength > 20) {
                                                        $badgeClass = 'class-badge extra-long';
                                                    } elseif ($kelasLength > 15) {
                                                        $badgeClass = 'class-badge long-name';
                                                    } else {
                                                        $badgeClass = 'class-badge';
                                                    }
                                                    ?>
                                                    <span class="<?= $badgeClass ?>" title="<?= $namaKelas ?>">
                                                        <?= $namaKelas ?>
                                                    </span>
                                                </div>
                                            </td>
                                            
                                            <!-- Jawaban per Pertanyaan -->
                                            <?php foreach ($pertanyaanList as $pertanyaan): ?>
                                                <td>
                                                    <div class="answer-container">
                                                        <?php 
                                                        $jawaban = $jawabanMatrix[$siswa['id_siswa']][$pertanyaan['id_pertanyaan']] ?? null;
                                                        if ($jawaban !== null): 
                                                        ?>
                                                           <!-- Ganti bagian onclick untuk setiap jenis jawaban dalam file -->

<?php if ($pertanyaan['tipe_jawaban'] == 'skala'): ?>
    <!-- Rating Answer - DIPERBAIKI -->
    <div class="rating-answer" onclick="showAnswerDetail('Rating', <?= json_encode($pertanyaan['pertanyaan']) ?>, <?= json_encode($jawaban) ?>, <?= json_encode($siswa['nama']) ?>)">
        <div class="rating-number"><?= htmlspecialchars($jawaban) ?></div>
        <div class="rating-stars">
            <?php for($i = 1; $i <= 5; $i++): ?>
                <i class="bi bi-star<?= $i <= (int)$jawaban ? '-fill' : '' ?>" style="color: #ffd700;"></i>
            <?php endfor; ?>
        </div>
        <div class="rating-label">
            <?php
            $labels = ['Sangat Buruk', 'Buruk', 'Cukup', 'Baik', 'Sangat Baik'];
            echo $labels[(int)$jawaban - 1] ?? 'Invalid';
            ?>
        </div>
    </div>

<?php elseif ($pertanyaan['tipe_jawaban'] == 'pilihan_ganda'): ?>
    <!-- Multiple Choice Answer - DIPERBAIKI -->
    <?php 
    $pilihan = getPilihanJawaban($pertanyaan['pilihan_jawaban']);
    $jawabanText = $jawaban;
    $jawabanIndex = array_search($jawabanText, $pilihan);
    
    if ($jawabanIndex === false) {
        foreach ($pilihan as $index => $option) {
            if (trim($option) === trim($jawabanText)) {
                $jawabanIndex = $index;
                break;
            }
        }
    }
    
    if ($jawabanIndex === false) {
        $jawabanIndex = 0;
        $jawabanText = 'Jawaban tidak valid';
    } else {
        $jawabanText = $pilihan[$jawabanIndex];
    }
    
    $jawabanLabel = chr(65 + $jawabanIndex);
    ?>
    <div class="choice-answer" onclick="showChoiceDetail(<?= json_encode($pertanyaan['pertanyaan']) ?>, <?= json_encode($jawabanText) ?>, <?= json_encode($jawabanLabel) ?>, <?= json_encode($pilihan) ?>, <?= json_encode($siswa['nama']) ?>)">
        <div class="choice-letter"><?= $jawabanLabel ?></div>
        <div class="choice-preview">
            <?= strlen($jawabanText) > 25 ? htmlspecialchars(substr($jawabanText, 0, 25)) . '...' : htmlspecialchars($jawabanText) ?>
        </div>
    </div>

<?php else: ?>
    <!-- Text Answer - DIPERBAIKI -->
    <div class="text-answer" onclick="showTextDetail(<?= json_encode($pertanyaan['pertanyaan']) ?>, <?= json_encode($jawaban) ?>, <?= json_encode($siswa['nama']) ?>)">
        <div class="text-icon">
            <i class="bi bi-chat-text"></i>
        </div>
        <div class="text-preview">
            <?= strlen($jawaban) > 30 ? htmlspecialchars(substr($jawaban, 0, 30)) . '...' : htmlspecialchars($jawaban) ?>
        </div>
        <div class="text-meta">
            <?= strlen($jawaban) ?> karakter
        </div>
    </div>
<?php endif; ?>
                                                        <?php else: ?>
                                                            <div class="empty-answer">
                                                                <i class="bi bi-dash-circle"></i><br>
                                                                Tidak ada jawaban
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="<?= 3 + count($pertanyaanList) ?>" class="text-center">
                                            <div class="empty-state">
                                                <i class="bi bi-inbox"></i>
                                                <h5>Belum Ada Data</h5>
                                                <p>Belum ada siswa yang menyelesaikan evaluasi ini</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal untuk Detail Pertanyaan -->
    <div class="modal fade" id="questionModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-question-circle me-2"></i>Detail Pertanyaan
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="question-full">
                        <div class="question-meta">
                            <div class="meta-item">
                                <span class="meta-label">Nomor:</span>
                                <span class="meta-value" id="qNumber"></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Tipe:</span>
                                <span class="meta-value" id="qType"></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Aspek:</span>
                                <span class="meta-value" id="qAspect"></span>
                            </div>
                        </div>
                        <div class="answer-label">Pertanyaan:</div>
                        <div class="answer-text" id="qText"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal untuk Detail Jawaban Rating -->
    <div class="modal fade" id="answerModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-star me-2"></i>Detail Jawaban
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="question-full">
                        <div class="answer-label">Pertanyaan:</div>
                        <div class="answer-text" id="modalQuestion"></div>
                    </div>
                    <div class="answer-content">
                        <div class="answer-label">Jawaban dari <span id="modalStudent"></span>:</div>
                        <div class="answer-text" id="modalAnswer"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal untuk Detail Jawaban Pilihan Ganda -->
    <div class="modal fade" id="choiceModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-check2-square me-2"></i>Detail Jawaban Pilihan Ganda
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="question-full">
                        <div class="answer-label">Pertanyaan:</div>
                        <div class="answer-text" id="modalChoiceQuestion"></div>
                    </div>
                    <div class="answer-content">
                        <div class="answer-label">Jawaban dari <span id="modalChoiceStudent"></span>:</div>
                        <div class="d-flex align-items-center mb-3">
                            <span class="choice-letter me-3" id="modalChoiceLabel">A</span>
                            <span class="answer-text" id="modalChoiceText"></span>
                        </div>
                        <div class="choices-list">
                            <div class="answer-label">Semua pilihan yang tersedia:</div>
                            <div id="modalAllChoices"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal untuk Detail Jawaban Isian -->
    <div class="modal fade" id="textModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-chat-text me-2"></i>Detail Jawaban Isian
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="question-full">
                        <div class="answer-label">Pertanyaan:</div>
                        <div class="answer-text" id="modalTextQuestion"></div>
                    </div>
                    <div class="answer-content">
                        <div class="answer-label">Jawaban dari <span id="modalTextStudent"></span>:</div>
                        <div class="answer-text" style="white-space: pre-wrap; line-height: 1.6;" id="modalTextAnswer"></div>
                        <div class="mt-2">
                            <small class="text-muted">
                                <i class="bi bi-type me-1"></i>
                                <span id="modalTextLength"></span> karakter
                            </small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../../../assets/js/scripts.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const kelasFilter = document.getElementById('kelasFilter');
        const tableBody = document.getElementById('tableBody');
        const showingInfo = document.getElementById('showingInfo');
        const allRows = Array.from(tableBody.querySelectorAll('.student-row'));
        
        // Filter functionality
        function applyFilters() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            const selectedKelas = kelasFilter.value;
            
            let visibleCount = 0;
            
            allRows.forEach((row, index) => {
                const nama = row.dataset.nama || '';
                const nik = row.dataset.nik || '';
                const kelas = row.dataset.kelas || '';
                
                let show = true;
                
                // Apply search filter
                if (searchTerm && !nama.includes(searchTerm) && !nik.includes(searchTerm)) {
                    show = false;
                }
                
                // Apply class filter
                if (selectedKelas && kelas !== selectedKelas) {
                    show = false;
                }
                
                row.style.display = show ? '' : 'none';
                if (show) {
                    visibleCount++;
                    // Update row number
                    const numberCell = row.querySelector('.sticky-col.number strong');
                    if (numberCell) numberCell.textContent = visibleCount;
                }
            });
            
            // Update showing info
            showingInfo.textContent = `Menampilkan ${visibleCount} dari ${allRows.length} data`;
        }
        
        // Event listeners for filters
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(applyFilters, 300);
        });
        
        kelasFilter.addEventListener('change', applyFilters);
        
        // Fungsi untuk menangani sticky columns saat scroll
        function handleStickyScroll() {
            const tableContainer = document.querySelector('.table-responsive');
            const stickyHeaders = document.querySelectorAll('.evaluation-table thead .sticky-col');
            const stickyCells = document.querySelectorAll('.evaluation-table tbody .sticky-col');
            
            if (!tableContainer) return;
            
            tableContainer.addEventListener('scroll', function() {
                const scrollLeft = this.scrollLeft;
                
                // Update shadow untuk sticky columns berdasarkan posisi scroll
                const hasShadow = scrollLeft > 0;
                
                [...stickyHeaders, ...stickyCells].forEach(cell => {
                    if (hasShadow) {
                        cell.style.boxShadow = '2px 0 8px -2px rgba(0, 0, 0, 0.15)';
                    } else {
                        cell.style.boxShadow = '2px 0 5px -2px rgba(0, 0, 0, 0.1)';
                    }
                });
            });
        }
        
        // Fungsi untuk highlight pertanyaan yang dipilih
        function highlightQuestion(questionElement) {
            // Hapus highlight sebelumnya
            document.querySelectorAll('.question-header').forEach(header => {
                header.classList.remove('highlighted');
            });
            
            // Tambahkan highlight ke pertanyaan yang dipilih
            questionElement.classList.add('highlighted');
            
            // Hapus highlight setelah 3 detik
            setTimeout(() => {
                questionElement.classList.remove('highlighted');
            }, 3000);
        }
        
        // Modal functions
        window.showQuestionDetail = function(id, question, aspect, type) {
            const questionHeaders = document.querySelectorAll('.question-header');
            let questionNumber = 1;
            
            questionHeaders.forEach((header, index) => {
                const onclickAttr = header.getAttribute('onclick');
                if (onclickAttr && onclickAttr.includes(`showQuestionDetail(${id},`)) {
                    questionNumber = index + 1;
                }
            });
            
            document.getElementById('qNumber').textContent = `Q${questionNumber}`;
            
            // Perbaikan display tipe
            let typeDisplay = '';
            switch(type) {
                case 'skala': 
                    typeDisplay = 'Rating (1-5)'; 
                    break;
                case 'pilihan_ganda': 
                    typeDisplay = 'Pilihan Ganda'; 
                    break;
                case 'isian': 
                    typeDisplay = 'Isian Bebas'; 
                    break;
                default: 
                    typeDisplay = type;
            }
            
            document.getElementById('qType').textContent = typeDisplay;
            document.getElementById('qAspect').textContent = aspect;
            document.getElementById('qText').innerHTML = question.replace(/\n/g, '<br>');
            
            new bootstrap.Modal(document.getElementById('questionModal')).show();
        };
        
        window.showAnswerDetail = function(type, question, answer, student) {
            document.getElementById('modalQuestion').textContent = question;
            document.getElementById('modalStudent').textContent = student;
            document.getElementById('modalAnswer').textContent = `Rating: ${answer}/5`;
            
            new bootstrap.Modal(document.getElementById('answerModal')).show();
        };
        
        window.showChoiceDetail = function(question, chosenText, chosenLabel, allChoices, student) {
            document.getElementById('modalChoiceQuestion').textContent = question;
            document.getElementById('modalChoiceStudent').textContent = student;
            document.getElementById('modalChoiceLabel').textContent = chosenLabel;
            document.getElementById('modalChoiceText').textContent = chosenText;
            
            let choicesHtml = '';
            allChoices.forEach((choice, index) => {
                const label = String.fromCharCode(65 + index);
                const isSelected = label === chosenLabel;
                choicesHtml += `
                    <div class="choice-item ${isSelected ? 'selected' : ''}">
                        <span class="choice-letter">${label}</span>
                        <span class="choice-text">${choice}</span>
                        ${isSelected ? '<span class="choice-check">✓</span>' : ''}
                    </div>
                `;
            });
            
            document.getElementById('modalAllChoices').innerHTML = choicesHtml;
            new bootstrap.Modal(document.getElementById('choiceModal')).show();
        };
        
        window.showTextDetail = function(question, answer, student) {
            document.getElementById('modalTextQuestion').textContent = question;
            document.getElementById('modalTextStudent').textContent = student;
            document.getElementById('modalTextAnswer').textContent = answer;
            document.getElementById('modalTextLength').textContent = answer.length;
            
            new bootstrap.Modal(document.getElementById('textModal')).show();
        };
        
        // Helper function untuk toast notification
        function showSuccessToast(title, message) {
            const toast = document.createElement('div');
            toast.className = 'position-fixed top-0 end-0 p-3';
            toast.style.zIndex = '9999';
            toast.innerHTML = `
                <div class="toast show" role="alert">
                    <div class="toast-header">
                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                        <strong class="me-auto">${title}</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">${message}</div>
                </div>
            `;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 4000);
        }

        function showErrorToast(title, message) {
            const toast = document.createElement('div');
            toast.className = 'position-fixed top-0 end-0 p-3';
            toast.style.zIndex = '9999';
            toast.innerHTML = `
                <div class="toast show" role="alert">
                    <div class="toast-header">
                        <i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>
                        <strong class="me-auto">${title}</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">${message}</div>
                </div>
            `;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 4000);
        }
        
        // Export to Excel - Diperbaiki
        window.exportToExcel = function() {
            try {
                const wb = XLSX.utils.book_new();
                const wsData = [];
                
                // Header dengan informasi lengkap
                const headers = ['No', 'Nama Siswa', 'NIK', 'Kelas', 'Tanggal Evaluasi'];
                
                // Tambahkan header pertanyaan dengan detail
                const questionHeaders = document.querySelectorAll('.question-header');
                questionHeaders.forEach((header, index) => {
                    const questionNumber = header.querySelector('.question-number').textContent;
                    const questionType = header.querySelector('.question-type').textContent;
                    const questionPreview = header.querySelector('.question-preview');
                    
                    // Ambil teks lengkap dari title attribute jika ada, atau dari textContent
                    const fullQuestion = questionPreview.getAttribute('title') || questionPreview.textContent;
                    
                    headers.push(`${questionNumber} (${questionType}) - ${fullQuestion.substring(0, 50)}${fullQuestion.length > 50 ? '...' : ''}`);
                });
                
                wsData.push(headers);
                
                // Data rows - hanya yang visible
                const visibleRows = allRows.filter(row => row.style.display !== 'none');
                visibleRows.forEach((row, index) => {
                    const studentInfo = row.querySelector('.student-info');
                    const nama = studentInfo.querySelector('.student-name').textContent.trim();
                    const studentDetails = studentInfo.querySelector('.student-details').textContent;
                    const nik = studentDetails.split('NIK: ')[1].split('\n')[0].trim();
                    const kelas = row.querySelector('.class-badge').textContent.trim();
                    
                    // Ambil tanggal dari student details
                    const tanggalMatch = studentDetails.match(/(\d{1,2}\s\w{3}\s\d{4},\s\d{2}:\d{2})/);
                    const tanggal = tanggalMatch ? tanggalMatch[1] : '-';
                    
                    const rowData = [index + 1, nama, nik, kelas, tanggal];
                    
                    // Add answers dengan format yang lebih baik
                    const answerCells = row.querySelectorAll('td:not(.sticky-col)');
                    answerCells.forEach(cell => {
                        const ratingEl = cell.querySelector('.rating-number');
                        const choiceEl = cell.querySelector('.choice-letter');
                        const textEl = cell.querySelector('.text-preview');
                        const emptyEl = cell.querySelector('.empty-answer');
                        
                        if (ratingEl) {
                            const rating = ratingEl.textContent;
                            const ratingLabel = cell.querySelector('.rating-label').textContent;
                            rowData.push(`${rating}/5 (${ratingLabel})`);
                        } else if (choiceEl) {
                            const choiceText = cell.querySelector('.choice-preview').textContent;
                            rowData.push(`${choiceEl.textContent}: ${choiceText}`);
                        } else if (textEl) {
                            // Coba ambil teks lengkap dari onclick attribute
                            const textAnswer = cell.querySelector('.text-answer');
                            const onclickAttr = textAnswer.getAttribute('onclick');
                            if (onclickAttr) {
                                const match = onclickAttr.match(/showTextDetail\('.*?', '(.*?)', '.*?'\)/);
                                if (match) {
                                    rowData.push(match[1].replace(/\\'/g, "'"));
                                } else {
                                    rowData.push(textEl.textContent);
                                }
                            } else {
                                rowData.push(textEl.textContent);
                            }
                        } else if (emptyEl) {
                            rowData.push('-');
                        } else {
                            rowData.push('-');
                        }
                    });
                    
                    wsData.push(rowData);
                });
                
                const ws = XLSX.utils.aoa_to_sheet(wsData);
                
                // Set column widths yang lebih baik
                const colWidths = [
                    { wch: 5 },   // No
                    { wch: 25 },  // Nama
                    { wch: 18 },  // NIK
                    { wch: 15 },  // Kelas
                    { wch: 20 },  // Tanggal
                ];
                
                // Width untuk kolom pertanyaan berdasarkan tipe
                questionHeaders.forEach(header => {
                    const questionType = header.querySelector('.question-type').textContent.toLowerCase();
                    let width = 20;
                    
                    if (questionType.includes('isian')) {
                        width = 50;
                    } else if (questionType.includes('pilihan')) {
                        width = 35;
                    } else if (questionType.includes('rating')) {
                        width = 15;
                    }
                    
                    colWidths.push({ wch: width });
                });
                
                ws['!cols'] = colWidths;
                XLSX.utils.book_append_sheet(wb, ws, 'Hasil Evaluasi');
                
                const filename = `Hasil_Evaluasi_<?= str_replace(' ', '_', $periode['nama_evaluasi']) ?>_<?= date('Ymd_His') ?>.xlsx`;
                XLSX.writeFile(wb, filename);
                
                // Show success toast
                showSuccessToast('Export berhasil!', 'File Excel berhasil diunduh.');
                
            } catch (error) {
                console.error('Export error:', error);
                showErrorToast('Export gagal!', 'Silakan coba lagi.');
            }
        };
        
        // Initialize semua fungsi
        handleStickyScroll();
        
        // Tambahkan event listener untuk highlight saat klik question header
        document.querySelectorAll('.question-header').forEach(header => {
            header.addEventListener('click', function() {
                highlightQuestion(this);
            });
        });
        
        // Initialize filters
        applyFilters();
    });
    </script>
</body>
</html>