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

// Fungsi untuk membersihkan data agar aman untuk JavaScript
function sanitizeForJS($text) {
    $text = preg_replace('/[^\p{L}\p{N}\s\-.,!?()]/u', '', $text);
    $text = trim($text);
    return $text;
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
    /* Controls - SAMA PERSIS DENGAN REFERENSI */
    .controls-container {
        gap: 8px;
    }
    
    .search-container {
        max-width: 300px;
    }
    
    .search-label {
        font-size: 0.85rem;
        color: #6c757d;
        font-weight: 500;
    }
    
    .search-input {
        border: 1px solid #ced4da;
        border-radius: 0.375rem;
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
    }
    
    .control-btn {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.375rem;
        border: 1px solid #ced4da;
    }
    
    .btn-icon {
        width: 36px;
        height: 36px;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .result-info {
        gap: 0.5rem;
    }
    
  
    
    .info-count {
        font-weight: 600;
        color: #495057;
    }
    
    .info-separator, .info-label {
        color: #6c757d;
    }
    
    .info-total {
        font-weight: 600;
        color: #495057;
    }

    /* Button Group */
    .button-group-header {
        gap: 8px;
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
        transform: translateZ(0);
    }

    /* Header Styling */
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

    /* Sticky Columns */
    .sticky-col {
        position: sticky !important;
        background: inherit;
        z-index: 20;
        border-right: 2px solid #dee2e6 !important;
        box-shadow: 2px 0 5px -2px rgba(0, 0, 0, 0.1);
        transform: translateZ(0);
    }

    .evaluation-table thead .sticky-col {
        z-index: 30 !important;
        background: #f8f9fa !important;
    }

    .evaluation-table tbody .sticky-col {
        background: white !important;
    }

    .evaluation-table tbody tr:hover .sticky-col {
        background: #f8f9fa !important;
    }

    .sticky-col.number {
        left: 0;
        width: 60px;
        min-width: 60px;
        max-width: 60px;
        text-align: center;
    }

    .sticky-col.student {
        left: 60px;
        width: 250px;
        min-width: 250px;
        max-width: 250px;
    }

    .sticky-col.class {
        left: 310px;
        width: 180px;
        min-width: 180px;
        max-width: 180px;
        text-align: center;
        padding: 0.5rem;
    }

    /* Question Headers */
    .question-header {
        text-align: center;
        line-height: 1.3;
        padding: 0.75rem 0.4rem;
        width: 170px;
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

    .question-preview {
        font-size: 0.7rem;
        color: #495057;
        line-height: 1.4;
        cursor: pointer;
        transition: all 0.2s;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
        height: auto;
        max-height: 4.2rem;
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

    /* Student Info */
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

    /* Class styling */
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

    /* Answer Displays */
    .answer-container {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 60px;
        padding: 0.5rem;
        width: 100%;
    }

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

    .empty-answer {
        text-align: center;
        color: #9e9e9e;
        font-style: italic;
        font-size: 0.75rem;
        padding: 1rem 0.5rem;
    }

    /* Stats */
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

    /* Empty States */
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

    /* Responsive */
    @media (max-width: 768px) {
        .controls-container {
            flex-direction: column;
            gap: 1rem;
        }
        
        .search-container {
            width: 100%;
            max-width: none;
        }
        
        .result-info {
            width: 100%;
            justify-content: center;
        }
        
        .button-group-header {
            flex-direction: column;
            width: 100%;
        }
        
        .button-group-header .btn {
            width: 100%;
            margin-bottom: 5px;
        }
        
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
                                <div class="d-flex align-items-center justify-content-end gap-2 button-group-header">
                                    <a href="index.php" class="btn btn-kembali">
                                       Kembali
                                    </a>
                                   <button type="button" class="btn btn-success" onclick="exportToExcel()">
                                        <i class="bi bi-file-excel me-1"></i>Export Excel
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

                <!-- Main Table -->
                <div class="card content-card">
                    <div class="section-header">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5 class="mb-0 text-dark">
                                    <i class="bi bi-table me-2"></i>Data Hasil Evaluasi
                                </h5>
                            </div>
                        </div>
                    </div>

                    <!-- Search/Filter Controls - STRUKTUR SAMA SEPERTI REFERENSI -->
                    <div class="p-3 border-bottom">
                        <div class="row align-items-center">  
                            <div class="col-12">
                                <div class="d-flex flex-wrap align-items-center gap-2 controls-container">
                                    <!-- Search Box -->
                                    <div class="d-flex align-items-center search-container">
                                        <label for="searchInput" class="me-2 mb-0 search-label">
                                            <small>Search:</small>
                                        </label>
                                        <input type="search" id="searchInput" class="form-control form-control-sm search-input" />
                                    </div>
                                    
                                    <!-- Sort Button -->
                                    <div class="dropdown">
                                        <button class="btn btn-light btn-icon position-relative control-btn" 
                                                type="button" 
                                                data-bs-toggle="dropdown" 
                                                data-bs-display="static"
                                                aria-expanded="false"
                                                title="Sort">
                                            <i class="bi bi-arrow-down-up"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width: 200px;">
                                            <li><h6 class="dropdown-header">Sort by</h6></li>
                                            <li>
                                                <a class="dropdown-item sort-option" href="#" data-sort="nama" data-order="asc">
                                                    <i class="bi bi-sort-alpha-down me-2"></i>Nama Siswa A-Z
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item sort-option" href="#" data-sort="nama" data-order="desc">
                                                    <i class="bi bi-sort-alpha-up me-2"></i>Nama Siswa Z-A
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                    
                                    <!-- Filter Button -->
                                    <div class="dropdown">
                                        <button class="btn btn-light btn-icon position-relative control-btn" 
                                                type="button" 
                                                data-bs-toggle="dropdown" 
                                                aria-expanded="false"
                                                id="filterButton"
                                                title="Filter">
                                            <i class="bi bi-funnel"></i>
                                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="filterBadge">
                                                0
                                            </span>
                                        </button>
                                        
                                        <!-- Filter Dropdown -->
                                        <div class="dropdown-menu dropdown-menu-end shadow p-3" style="min-width: 300px;">
                                            <h6 class="mb-3 fw-bold">
                                                <i class="bi bi-funnel me-2"></i>Filter Data
                                            </h6>
                                            
                                            <!-- Filter Kelas -->
                                            <div class="mb-3">
                                                <label class="form-label small text-muted mb-1">Kelas</label>
                                                <select class="form-select form-select-sm" id="filterKelas">
                                                    <option value="">Semua Kelas</option>
                                                    <?php 
                                                    $kelasList = array_unique(array_column($siswaData, 'nama_kelas'));
                                                    sort($kelasList);
                                                    foreach($kelasList as $kelas): ?>
                                                        <option value="<?= htmlspecialchars($kelas) ?>">
                                                            <?= htmlspecialchars($kelas) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <hr class="my-3">
                                            
                                            <!-- Filter Buttons -->
                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <button class="btn btn-primary btn-sm w-100 d-flex align-items-center justify-content-center" 
                                                            id="applyFilter" 
                                                            type="button"
                                                            style="height: 36px;">
                                                        <i class="bi bi-check-lg me-1"></i>
                                                        <span>Terapkan</span>
                                                    </button>
                                                </div>
                                                <div class="col-6">
                                                    <button class="btn btn-light btn-sm w-100 d-flex align-items-center justify-content-center" 
                                                            id="resetFilter" 
                                                            type="button"
                                                            style="height: 36px;">
                                                        <i class="bi bi-arrow-clockwise me-1"></i>
                                                        <span>Reset</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Result Info -->
                                    <div class="ms-auto result-info d-flex align-items-center">
                                        <label class="me-2 mb-0 search-label">
                                            <small>Show:</small>
                                        </label>
                                        <div class="info-badge">
                                            <span class="info-count" id="showingCount"><?= count($siswaData) ?></span>
                                            <span class="info-separator">dari</span>
                                            <span class="info-total" id="totalCount"><?= count($siswaData) ?></span>
                                            <span class="info-label">data</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table class="table evaluation-table" id="evaluationTable">
                            <thead>
                                <tr>
                                    <th class="sticky-col number">No</th>
                                    <th class="sticky-col student">Siswa</th>
                                    <th class="sticky-col class">Kelas</th>
                                    <?php foreach ($pertanyaanList as $index => $pertanyaan): ?>
                                        <th class="question-header" 
                                            data-qid="<?= $pertanyaan['id_pertanyaan'] ?>" 
                                            data-qtext="<?= htmlspecialchars(sanitizeForJS($pertanyaan['pertanyaan'])) ?>" 
                                            data-qaspect="<?= htmlspecialchars(sanitizeForJS($pertanyaan['aspek_dinilai'])) ?>" 
                                            data-qtype="<?= $pertanyaan['tipe_jawaban'] ?>" 
                                            onclick="showQuestionDetail(this)">
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
                                                    <span class="class-badge" title="<?= htmlspecialchars($siswa['nama_kelas']) ?>">
                                                        <?= htmlspecialchars($siswa['nama_kelas']) ?>
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
                                                            <?php if ($pertanyaan['tipe_jawaban'] == 'skala'): ?>
                                                                <!-- Rating Answer -->
                                                                <div class="rating-answer" 
                                                                     data-qtext="<?= htmlspecialchars(sanitizeForJS($pertanyaan['pertanyaan'])) ?>" 
                                                                     data-answer="<?= htmlspecialchars(sanitizeForJS($jawaban)) ?>" 
                                                                     data-student="<?= htmlspecialchars(sanitizeForJS($siswa['nama'])) ?>" 
                                                                     onclick="showAnswerDetail(this)">
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
                                                                <!-- Multiple Choice Answer -->
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
                                                                <div class="choice-answer" 
                                                                     data-qtext="<?= htmlspecialchars(sanitizeForJS($pertanyaan['pertanyaan'])) ?>" 
                                                                     data-chosen="<?= htmlspecialchars(sanitizeForJS($jawabanText)) ?>" 
                                                                     data-label="<?= $jawabanLabel ?>" 
                                                                     data-choices="<?= htmlspecialchars(json_encode(array_map('sanitizeForJS', $pilihan))) ?>" 
                                                                     data-student="<?= htmlspecialchars(sanitizeForJS($siswa['nama'])) ?>" 
                                                                     onclick="showChoiceDetail(this)">
                                                                    <div class="choice-letter"><?= $jawabanLabel ?></div>
                                                                    <div class="choice-preview">
                                                                        <?= strlen($jawabanText) > 25 ? htmlspecialchars(substr($jawabanText, 0, 25)) . '...' : htmlspecialchars($jawabanText) ?>
                                                                    </div>
                                                                </div>

                                                            <?php else: ?>
                                                                <!-- Text Answer -->
                                                                <div class="text-answer" 
                                                                     data-qtext="<?= htmlspecialchars(sanitizeForJS($pertanyaan['pertanyaan'])) ?>" 
                                                                     data-answer="<?= htmlspecialchars(sanitizeForJS($jawaban)) ?>" 
                                                                     data-student="<?= htmlspecialchars(sanitizeForJS($siswa['nama'])) ?>" 
                                                                     onclick="showTextDetail(this)">
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
        // Referensi elemen
        const table = document.getElementById('evaluationTable');
        if (!table) return;
        
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('.student-row')).filter(row => !row.querySelector('.empty-state'));
        const filterButton = document.getElementById('filterButton');
        const filterBadge = document.getElementById('filterBadge');
        const showingCount = document.getElementById('showingCount');
        const totalCount = document.getElementById('totalCount');
        
        const originalOrder = [...rows];
        let activeFilters = 0;

        // Force dropdown positioning
        function forceDropdownPositioning() {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.style.setProperty('position', 'absolute', 'important');
                menu.style.setProperty('top', '100%', 'important');
                menu.style.setProperty('bottom', 'auto', 'important');
                menu.style.setProperty('transform', 'none', 'important');
                menu.style.setProperty('z-index', '1055', 'important');
                menu.style.setProperty('margin-top', '2px', 'important');
                
                if (menu.classList.contains('dropdown-menu-end')) {
                    menu.style.setProperty('right', '0', 'important');
                    menu.style.setProperty('left', 'auto', 'important');
                }
            });
        }

        // Sort functionality
        function initializeSortOptions() {
            document.querySelectorAll('.sort-option').forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    document.querySelectorAll('.sort-option').forEach(opt => {
                        opt.classList.remove('active');
                        opt.style.backgroundColor = '';
                        opt.style.color = '';
                    });
                    
                    this.classList.add('active');
                    this.style.backgroundColor = '#0d6efd';
                    this.style.color = 'white';
                    
                    const sortType = this.dataset.sort;
                    const sortOrder = this.dataset.order;
                    
                    sortTable(sortType, sortOrder);
                    
                    setTimeout(() => {
                        const dropdownToggle = this.closest('.dropdown').querySelector('[data-bs-toggle="dropdown"]');
                        if (dropdownToggle) {
                            const dropdown = bootstrap.Dropdown.getInstance(dropdownToggle);
                            if (dropdown) dropdown.hide();
                        }
                    }, 150);
                });
            });
        }
        
        function sortTable(type, order) {
            let sortedRows;
            
            try {
                if (type === 'nama') {
                    sortedRows = [...rows].sort((a, b) => {
                        const aNama = (a.dataset.nama || '').trim().toLowerCase();
                        const bNama = (b.dataset.nama || '').trim().toLowerCase();
                        return order === 'asc' ? aNama.localeCompare(bNama) : bNama.localeCompare(aNama);
                    });
                } else {
                    sortedRows = [...originalOrder];
                }
                
                const fragment = document.createDocumentFragment();
                sortedRows.forEach(row => fragment.appendChild(row));
                tbody.appendChild(fragment);
                
                updateRowNumbers();
                
            } catch (error) {
                console.error('Sort error:', error);
                const fragment = document.createDocumentFragment();
                originalOrder.forEach(row => fragment.appendChild(row));
                tbody.appendChild(fragment);
                updateRowNumbers();
            }
        }

        // Filter functionality
        const searchInput = document.getElementById('searchInput');
        const filterKelas = document.getElementById('filterKelas');
        const applyFilterBtn = document.getElementById('applyFilter');
        const resetFilterBtn = document.getElementById('resetFilter');
        
        let searchTimeout;
        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                e.stopPropagation();
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    applyFilters();
                }, 300);
            });
        }
        
        function applyFilters() {
            const searchTerm = (searchInput?.value || '').toLowerCase().trim();
            const kelasFilter = filterKelas?.value || '';
            
            let visibleCount = 0;
            activeFilters = 0;
            
            if (kelasFilter) activeFilters++;
            
            updateFilterBadge();
            
            rows.forEach(row => {
                try {
                    const nama = (row.dataset.nama || '').toLowerCase();
                    const nik = (row.dataset.nik || '').toLowerCase();
                    const kelas = (row.dataset.kelas || '').trim();
                    
                    let showRow = true;
                    
                    // Filter search
                    if (searchTerm && 
                        !nama.includes(searchTerm) && 
                        !nik.includes(searchTerm)) {
                        showRow = false;
                    }
                    
                    // Filter kelas
                    if (kelasFilter && kelas !== kelasFilter) showRow = false;
                    
                    row.style.display = showRow ? '' : 'none';
                    if (showRow) visibleCount++;
                    
                } catch (error) {
                    console.error('Filter error for row:', error);
                    row.style.display = '';
                    visibleCount++;
                }
            });
            
            updateRowNumbers();
            updateShowingCount(visibleCount);
        }
        
        function updateFilterBadge() {
            if (!filterBadge || !filterButton) return;
            
            if (activeFilters > 0) {
                filterBadge.textContent = activeFilters;
                filterBadge.classList.remove('d-none');
                filterButton.classList.add('btn-primary');
                filterButton.classList.remove('btn-light');
            } else {
                filterBadge.classList.add('d-none');
                filterButton.classList.remove('btn-primary');
                filterButton.classList.add('btn-light');
            }
        }
        
        function updateRowNumbers() {
            let counter = 1;
            rows.forEach(row => {
                if (row.style.display !== 'none') {
                    const numberCell = row.querySelector('.sticky-col.number strong');
                    if (numberCell) numberCell.textContent = counter++;
                }
            });
        }

        function updateShowingCount(visibleCount) {
            if (showingCount) {
                showingCount.textContent = visibleCount;
            }
        }

        // Event listeners
        if (applyFilterBtn) {
            applyFilterBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                applyFilters();
                setTimeout(() => {
                    const dropdown = bootstrap.Dropdown.getInstance(filterButton);
                    if (dropdown) dropdown.hide();
                }, 100);
            });
        }
        
        if (resetFilterBtn) {
            resetFilterBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (searchInput) searchInput.value = '';
                if (filterKelas) filterKelas.value = '';
                applyFilters();
            });
        }
        
        const filterDropdown = document.querySelector('.dropdown-menu.p-3');
        if (filterDropdown) {
            filterDropdown.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }

        // Dropdown event handlers
        document.addEventListener('show.bs.dropdown', function (e) {
            forceDropdownPositioning();
        });
        
        document.addEventListener('shown.bs.dropdown', function (e) {
            const dropdown = e.target.nextElementSibling;
            if (dropdown && dropdown.classList.contains('dropdown-menu')) {
                dropdown.style.setProperty('position', 'absolute', 'important');
                dropdown.style.setProperty('top', '100%', 'important');
                dropdown.style.setProperty('bottom', 'auto', 'important');
                dropdown.style.setProperty('transform', 'none', 'important');
                dropdown.style.setProperty('z-index', '1055', 'important');
                dropdown.style.setProperty('margin-top', '2px', 'important');
                
                if (dropdown.classList.contains('dropdown-menu-end')) {
                    dropdown.style.setProperty('right', '0', 'important');
                    dropdown.style.setProperty('left', 'auto', 'important');
                }
            }
        });

        // Modal functions
        window.showQuestionDetail = function(element) {
            const questionNumber = Array.from(document.querySelectorAll('.question-header')).indexOf(element) + 1;
            
            document.getElementById('qNumber').textContent = `Q${questionNumber}`;
            
            let typeDisplay = '';
            switch(element.dataset.qtype) {
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
                    typeDisplay = element.dataset.qtype;
            }
            
            document.getElementById('qType').textContent = typeDisplay;
            document.getElementById('qAspect').textContent = element.dataset.qaspect;
            document.getElementById('qText').innerHTML = element.dataset.qtext.replace(/\n/g, '<br>');
            
            new bootstrap.Modal(document.getElementById('questionModal')).show();
        };

        window.showAnswerDetail = function(element) {
            document.getElementById('modalQuestion').textContent = element.dataset.qtext;
            document.getElementById('modalStudent').textContent = element.dataset.student;
            document.getElementById('modalAnswer').textContent = `Rating: ${element.dataset.answer}/5`;
            
            new bootstrap.Modal(document.getElementById('answerModal')).show();
        };

        window.showChoiceDetail = function(element) {
            document.getElementById('modalChoiceQuestion').textContent = element.dataset.qtext;
            document.getElementById('modalChoiceStudent').textContent = element.dataset.student;
            document.getElementById('modalChoiceLabel').textContent = element.dataset.label;
            document.getElementById('modalChoiceText').textContent = element.dataset.chosen;
            
            const allChoices = JSON.parse(element.dataset.choices);
            let choicesHtml = '';
            allChoices.forEach((choice, index) => {
                const label = String.fromCharCode(65 + index);
                const isSelected = label === element.dataset.label;
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

        window.showTextDetail = function(element) {
            document.getElementById('modalTextQuestion').textContent = element.dataset.qtext;
            document.getElementById('modalTextStudent').textContent = element.dataset.student;
            document.getElementById('modalTextAnswer').textContent = element.dataset.answer;
            document.getElementById('modalTextLength').textContent = element.dataset.answer.length;
            
            new bootstrap.Modal(document.getElementById('textModal')).show();
        };

        // Export to Excel - COMPACT & VISIBLE VERSION
        window.exportToExcel = function() {
            try {
                const wb = XLSX.utils.book_new();
                
                // Sheet 1: Data Utama (COMPACT)
                const wsData1 = [];
                
                // Info singkat di atas (hanya 3 baris)
                wsData1.push(['HASIL EVALUASI: <?= htmlspecialchars($periode['nama_evaluasi']) ?>']);
                wsData1.push(['Gelombang: <?= htmlspecialchars($periode['nama_gelombang']) ?> (<?= $periode['tahun'] ?>) | Siswa: <?= count($siswaData) ?> | Export: ' + new Date().toLocaleDateString('id-ID')]);
                wsData1.push(['']); // 1 baris kosong saja
                
                // Header dengan pertanyaan yang VISIBLE
                const headers1 = ['No', 'Nama Siswa', 'NIK', 'Kelas', 'Tanggal'];
                
                const questionHeaders = document.querySelectorAll('.question-header');
                questionHeaders.forEach((header, index) => {
                    const questionNumber = header.querySelector('.question-number').textContent;
                    const questionType = header.querySelector('.question-type').textContent;
                    const questionPreview = header.querySelector('.question-preview');
                    const fullQuestion = questionPreview.getAttribute('title') || questionPreview.textContent;
                    
                    // Header yang COMPACT tapi JELAS
                    const shortType = questionType === 'Rating' ? 'R' : questionType === 'Pilihan' ? 'PG' : 'T';
                    let questionText = fullQuestion;
                    
                    // Potong pertanyaan jika terlalu panjang, tapi tetap informatif
                    if (questionText.length > 60) {
                        questionText = questionText.substring(0, 57) + '...';
                    }
                    
                    headers1.push(`${questionNumber} [${shortType}] ${questionText}`);
                });
                
                wsData1.push(headers1);
                
                // Data rows - LANGSUNG DIMULAI
                const visibleRows = rows.filter(row => row.style.display !== 'none');
                visibleRows.forEach((row, index) => {
                    const studentInfo = row.querySelector('.student-info');
                    const nama = studentInfo.querySelector('.student-name').textContent.trim();
                    const studentDetails = studentInfo.querySelector('.student-details').textContent;
                    const nik = studentDetails.split('NIK: ')[1].split('\n')[0].trim();
                    const kelas = row.querySelector('.class-badge').textContent.trim();
                    
                    const tanggalMatch = studentDetails.match(/(\d{1,2}\s\w{3}\s\d{4})/);
                    const tanggal = tanggalMatch ? tanggalMatch[1] : '-';
                    
                    const rowData = [index + 1, nama, nik, kelas, tanggal];
                    
                    // Add answers dengan format COMPACT tapi JELAS
                    const answerCells = row.querySelectorAll('td:not(.sticky-col)');
                    answerCells.forEach((cell, cellIndex) => {
                        const ratingEl = cell.querySelector('.rating-answer');
                        const choiceEl = cell.querySelector('.choice-answer');
                        const textEl = cell.querySelector('.text-answer');
                        const emptyEl = cell.querySelector('.empty-answer');
                        
                        if (ratingEl) {
                            const rating = ratingEl.dataset.answer;
                            const labels = ['SB', 'B', 'C', 'Ba', 'SBa']; // Singkatan
                            const ratingLabel = labels[parseInt(rating) - 1] || 'X';
                            rowData.push(`${rating}/5 (${ratingLabel})`);
                        } else if (choiceEl) {
                            const choiceLabel = choiceEl.dataset.label;
                            let choiceText = choiceEl.dataset.chosen;
                            // Potong jawaban pilihan jika terlalu panjang
                            if (choiceText.length > 50) {
                                choiceText = choiceText.substring(0, 47) + '...';
                            }
                            rowData.push(`${choiceLabel}. ${choiceText}`);
                        } else if (textEl) {
                            let fullAnswer = textEl.dataset.answer || textEl.querySelector('.text-preview').textContent;
                            // Batasi jawaban isian agar tidak terlalu panjang di view
                            if (fullAnswer.length > 100) {
                                fullAnswer = fullAnswer.substring(0, 97) + '...';
                            }
                            rowData.push(fullAnswer);
                        } else {
                            rowData.push('-');
                        }
                    });
                    
                    wsData1.push(rowData);
                });
                
                const ws1 = XLSX.utils.aoa_to_sheet(wsData1);
                
                // Column widths yang OPTIMAL untuk VISIBILITAS
                const colWidths1 = [
                    { wch: 4 },   // No
                    { wch: 20 },  // Nama
                    { wch: 15 },  // NIK
                    { wch: 12 },  // Kelas
                    { wch: 12 },  // Tanggal
                ];
                
                // Width yang SEIMBANG untuk pertanyaan
                questionHeaders.forEach(header => {
                    const questionType = header.querySelector('.question-type').textContent.toLowerCase();
                    let width = 18; // Default yang cukup
                    
                    if (questionType.includes('isian')) {
                        width = 25; // Cukup untuk isian
                    } else if (questionType.includes('pilihan')) {
                        width = 22; // Cukup untuk pilihan
                    } else if (questionType.includes('rating')) {
                        width = 15; // Compact untuk rating
                    }
                    
                    colWidths1.push({ wch: width });
                });
                
                ws1['!cols'] = colWidths1;
                
                // Style untuk header info (baris 1-3)
                for (let row = 0; row <= 2; row++) {
                    for (let col = 0; col < 3; col++) {
                        const cellAddress = XLSX.utils.encode_cell({ r: row, c: col });
                        if (ws1[cellAddress]) {
                            ws1[cellAddress].s = {
                                font: { bold: true, color: { rgb: "1F4E79" } },
                                fill: { fgColor: { rgb: "E7F3FF" } },
                                alignment: { horizontal: "left" }
                            };
                        }
                    }
                }
                
                // Style untuk header kolom (baris 4)
                const headerRow = 3; // Index 3 = baris ke-4
                for (let col = 0; col < headers1.length; col++) {
                    const cellAddress = XLSX.utils.encode_cell({ r: headerRow, c: col });
                    if (ws1[cellAddress]) {
                        ws1[cellAddress].s = {
                            font: { bold: true, color: { rgb: "FFFFFF" } },
                            fill: { fgColor: { rgb: "4472C4" } },
                            alignment: { horizontal: "center", vertical: "center" },
                            border: {
                                top: { style: "thin", color: { rgb: "000000" } },
                                bottom: { style: "thin", color: { rgb: "000000" } },
                                left: { style: "thin", color: { rgb: "000000" } },
                                right: { style: "thin", color: { rgb: "000000" } }
                            }
                        };
                    }
                }
                
                XLSX.utils.book_append_sheet(wb, ws1, 'Data Evaluasi');
                
                // Sheet 2: Pertanyaan Detail (COMPACT)
                const wsData2 = [];
                wsData2.push(['DAFTAR PERTANYAAN LENGKAP']);
                wsData2.push(['']); // Hanya 1 baris kosong
                wsData2.push(['No', 'Soal', 'Tipe', 'Aspek', 'Pertanyaan Lengkap', 'Opsi Jawaban']);
                
                questionHeaders.forEach((header, index) => {
                    const questionNumber = header.querySelector('.question-number').textContent;
                    const questionType = header.querySelector('.question-type').textContent;
                    const questionPreview = header.querySelector('.question-preview');
                    const fullQuestion = questionPreview.getAttribute('title') || questionPreview.textContent;
                    const aspectValue = header.dataset.qaspect || '-';
                    
                    let pilihanJawaban = '';
                    if (questionType.toLowerCase().includes('pilihan')) {
                        // Cari contoh pilihan dari jawaban yang ada
                        const allChoices = document.querySelectorAll('.choice-answer[data-choices]');
                        if (allChoices.length > 0) {
                            try {
                                const choices = JSON.parse(allChoices[0].dataset.choices);
                                pilihanJawaban = choices.map((choice, idx) => `${String.fromCharCode(65 + idx)}. ${choice}`).join(' | ');
                            } catch (e) {
                                pilihanJawaban = 'Pilihan ganda tersedia';
                            }
                        }
                    } else if (questionType.toLowerCase().includes('rating')) {
                        pilihanJawaban = '1=Sangat Buruk | 2=Buruk | 3=Cukup | 4=Baik | 5=Sangat Baik';
                    } else {
                        pilihanJawaban = 'Jawaban bebas (text)';
                    }
                    
                    wsData2.push([
                        index + 1,
                        questionNumber,
                        questionType,
                        aspectValue,
                        fullQuestion,
                        pilihanJawaban
                    ]);
                });
                
                const ws2 = XLSX.utils.aoa_to_sheet(wsData2);
                
                // Column widths yang VISIBLE
                ws2['!cols'] = [
                    { wch: 4 },   // No
                    { wch: 8 },   // Soal
                    { wch: 12 },  // Tipe
                    { wch: 15 },  // Aspek
                    { wch: 50 },  // Pertanyaan
                    { wch: 60 }   // Opsi
                ];
                
                // Style untuk sheet 2
                ws2['A1'].s = {
                    font: { bold: true, size: 14, color: { rgb: "1F4E79" } },
                    fill: { fgColor: { rgb: "E7F3FF" } }
                };
                
                // Header row styling
                for (let col = 0; col < 6; col++) {
                    const cellAddress = XLSX.utils.encode_cell({ r: 2, c: col });
                    if (ws2[cellAddress]) {
                        ws2[cellAddress].s = {
                            font: { bold: true, color: { rgb: "FFFFFF" } },
                            fill: { fgColor: { rgb: "70AD47" } },
                            alignment: { horizontal: "center" }
                        };
                    }
                }
                
                XLSX.utils.book_append_sheet(wb, ws2, 'Detail Pertanyaan');
                
                // Sheet 3: Statistik (SUPER COMPACT)
                const wsData3 = [];
                wsData3.push(['RINGKASAN STATISTIK']);
                wsData3.push(['']);
                
                // Statistik dalam format tabel compact
                wsData3.push(['Metric', 'Value']);
                wsData3.push(['Total Siswa Selesai', visibleRows.length]);
                wsData3.push(['Total Pertanyaan', questionHeaders.length]);
                
                // Hitung tipe pertanyaan
                let ratingCount = 0, choiceCount = 0, textCount = 0;
                questionHeaders.forEach(header => {
                    const questionType = header.querySelector('.question-type').textContent.toLowerCase();
                    if (questionType.includes('rating')) ratingCount++;
                    else if (questionType.includes('pilihan')) choiceCount++;
                    else if (questionType.includes('isian')) textCount++;
                });
                
                wsData3.push(['Pertanyaan Rating', ratingCount]);
                wsData3.push(['Pertanyaan Pilihan Ganda', choiceCount]);
                wsData3.push(['Pertanyaan Isian', textCount]);
                wsData3.push(['']);
                
                // Statistik kelas
                wsData3.push(['Kelas', 'Jumlah Siswa']);
                const kelasStats = {};
                visibleRows.forEach(row => {
                    const kelas = row.dataset.kelas;
                    kelasStats[kelas] = (kelasStats[kelas] || 0) + 1;
                });
                
                Object.entries(kelasStats).forEach(([kelas, count]) => {
                    wsData3.push([kelas, count]);
                });
                
                const ws3 = XLSX.utils.aoa_to_sheet(wsData3);
                ws3['!cols'] = [{ wch: 25 }, { wch: 15 }];
                
                XLSX.utils.book_append_sheet(wb, ws3, 'Statistik');
                
                // Generate filename
                const now = new Date();
                const dateStr = now.getFullYear() + 
                    String(now.getMonth() + 1).padStart(2, '0') + 
                    String(now.getDate()).padStart(2, '0') + '_' +
                    String(now.getHours()).padStart(2, '0') + 
                    String(now.getMinutes()).padStart(2, '0');
                
                const filename = `Evaluasi_<?= str_replace([' ', '(', ')', '/', ','], ['_', '', '', '_', ''], $periode['nama_evaluasi']) ?>_${dateStr}.xlsx`;
                
                // Save file
                XLSX.writeFile(wb, filename);
                
                // Show success message dengan info yang jelas
                const toast = document.createElement('div');
                toast.className = 'position-fixed top-0 end-0 p-3';
                toast.style.zIndex = '9999';
                toast.innerHTML = `
                    <div class="toast show" role="alert">
                        <div class="toast-header">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            <strong class="me-auto">Export Berhasil!</strong>
                            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                        </div>
                        <div class="toast-body">
                            <strong>File Excel siap dibuka!</strong><br>
                            • <strong>Sheet 1</strong>: Data lengkap siswa & jawaban<br>
                            • <strong>Sheet 2</strong>: Detail pertanyaan & opsi<br>
                            • <strong>Sheet 3</strong>: Statistik ringkasan<br>
                            <small class="text-muted">Data langsung terlihat tanpa scroll berlebihan</small>
                        </div>
                    </div>
                `;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 6000);
                
            } catch (error) {
                console.error('Export error:', error);
                
                const toast = document.createElement('div');
                toast.className = 'position-fixed top-0 end-0 p-3';
                toast.style.zIndex = '9999';
                toast.innerHTML = `
                    <div class="toast show" role="alert">
                        <div class="toast-header">
                            <i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>
                            <strong class="me-auto">Export Gagal!</strong>
                            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                        </div>
                        <div class="toast-body">Error: ${error.message}<br>Silakan coba lagi.</div>
                    </div>
                `;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 5000);
            }
        };

        // Initialize everything
        forceDropdownPositioning();
        initializeSortOptions();
        
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' || mutation.type === 'attributes') {
                    forceDropdownPositioning();
                }
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class', 'style']
        });
        
        window.addEventListener('resize', forceDropdownPositioning);
        window.addEventListener('scroll', forceDropdownPositioning);

        // Initialize filters
        applyFilters();
    });
    </script>
</body>
</html>