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
    $pertanyaan_ids = implode(',', array_map('intval', $pertanyaan_terpilih));
    $pertanyaanQuery = "SELECT p.id_pertanyaan, p.pertanyaan, p.aspek_dinilai, p.tipe_jawaban, p.pilihan_jawaban
                        FROM pertanyaan_evaluasi p
                        WHERE p.id_pertanyaan IN ($pertanyaan_ids)
                        ORDER BY p.aspek_dinilai, p.id_pertanyaan";
    
    $pertanyaanResult = mysqli_query($conn, $pertanyaanQuery);
    while ($pertanyaan = mysqli_fetch_assoc($pertanyaanResult)) {
        $pertanyaanList[] = $pertanyaan;
    }
}

// Hitung statistik per tipe jawaban
$stats_tipe = [
    'pilihan_ganda' => 0,
    'skala' => 0,
    'isian' => 0
];

foreach ($pertanyaanList as $pertanyaan) {
    $stats_tipe[$pertanyaan['tipe_jawaban']]++;
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

// Hitung statistik per pertanyaan
$statistikPertanyaan = [];
foreach ($pertanyaanList as $pertanyaan) {
    $id_pertanyaan = $pertanyaan['id_pertanyaan'];
    $allAnswers = [];
    
    foreach ($siswaData as $siswa) {
        if (isset($jawabanMatrix[$siswa['id_siswa']][$id_pertanyaan])) {
            $allAnswers[] = $jawabanMatrix[$siswa['id_siswa']][$id_pertanyaan];
        }
    }
    
    $statistikPertanyaan[$id_pertanyaan] = [
        'total_jawaban' => count($allAnswers),
        'rata_rata' => null,
        'avg_length' => 0,
        'distribusi_pilihan' => []
    ];
    
    if ($pertanyaan['tipe_jawaban'] == 'skala') {
        $numericAnswers = array_map('intval', array_filter($allAnswers, 'is_numeric'));
        if (!empty($numericAnswers)) {
            $statistikPertanyaan[$id_pertanyaan]['rata_rata'] = round(array_sum($numericAnswers) / count($numericAnswers), 1);
        }
    } elseif ($pertanyaan['tipe_jawaban'] == 'pilihan_ganda') {
        // Hitung distribusi pilihan untuk pilihan ganda
        $distribusi = [];
        foreach ($allAnswers as $jawaban) {
            $jawaban = (int)$jawaban;
            if (!isset($distribusi[$jawaban])) {
                $distribusi[$jawaban] = 0;
            }
            $distribusi[$jawaban]++;
        }
        $statistikPertanyaan[$id_pertanyaan]['distribusi_pilihan'] = $distribusi;
    } else {
        // Hitung rata-rata panjang karakter untuk isian
        $textLengths = array_map('strlen', array_filter($allAnswers, 'is_string'));
        if (!empty($textLengths)) {
            $statistikPertanyaan[$id_pertanyaan]['avg_length'] = round(array_sum($textLengths) / count($textLengths));
        }
    }
}

// Label materi
$materi_labels = [
    'word' => 'Microsoft Word',
    'excel' => 'Microsoft Excel', 
    'ppt' => 'Microsoft PowerPoint',
    'internet' => 'Internet & Email'
];

// Fungsi format tanggal
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

// Helper function untuk mendapatkan icon tipe jawaban
function getTipeJawabanIcon($tipe) {
    switch ($tipe) {
        case 'pilihan_ganda':
            return 'bi-check2-square';
        case 'skala':
            return 'bi-star';
        case 'isian':
            return 'bi-pencil';
        default:
            return 'bi-question-circle';
    }
}

// Helper function untuk decode pilihan jawaban
function getPilihanJawaban($pilihan_jawaban) {
    if (empty($pilihan_jawaban)) return [];
    $decoded = json_decode($pilihan_jawaban, true);
    return is_array($decoded) ? $decoded : [];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ringkasan Hasil Evaluasi - <?= htmlspecialchars($periode['nama_evaluasi']) ?></title>
  <link rel="icon" type="image/png" href="../../../assets/img/favicon.png"/>
  <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../../assets/css/fonts.css" />
  <link rel="stylesheet" href="../../../assets/css/styles.css" />
  
  <style>
  /* Content Card */
  .content-card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border-radius: 0.5rem;
    overflow: hidden;
  }

  /* Section Header */
  .section-header {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    padding: 1.25rem 1.5rem;
  }

  .section-header h5 {
    margin-bottom: 0;
    font-weight: 600;
  }

  /* Stats Cards - Sederhana tanpa gradient */
  .stats-item {
    text-align: center;
    padding: 1.5rem 1rem;
    border-radius: 0.5rem;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
  }

  .stats-number {
    font-size: 2.5rem;
    font-weight: bold;
    line-height: 1;
    margin-bottom: 0.5rem;
  }

  .stats-label {
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .stats-icon {
    display: flex;
    justify-content: center;
    margin-bottom: 0.75rem;
  }

  /* Controls Container */
  .controls-container {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 1rem;
  }

  .search-container {
    display: flex;
    align-items: center;
  }

  .search-label {
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 500;
  }

  .search-input {
    width: 200px;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
  }

  /* Control Buttons */
  .control-btn {
    border: 1px solid #d1d5db;
    background: #fff;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.375rem;
  }

  .control-btn:hover {
    background: #f3f4f6;
    border-color: #9ca3af;
  }

  /* Result Info */
  .result-info {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .info-badge {
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
    border-radius: 0.375rem;
    padding: 0.25rem 0.75rem;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
  }

  .info-count {
    font-weight: 600;
    color: #1f2937;
  }

  .info-separator {
    color: #9ca3af;
  }

  .info-total {
    font-weight: 600;
    color: #1f2937;
  }

  .info-label {
    color: #6b7280;
  }

  /* Table */
  .custom-table {
    margin-bottom: 0;
  }

  .custom-table thead th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    color: #495057;
    padding: 0.75rem;
  }

  .sticky-top {
    position: sticky;
    top: 0;
    z-index: 10;
  }

  /* Sticky Columns */
  .sticky-column {
    position: sticky;
    left: 0;
    background-color: white;
    z-index: 10;
    border-right: 2px solid #dee2e6;
  }

  .sticky-no {
    width: 60px;
    min-width: 60px;
  }

  .sticky-nama {
    left: 60px;
    width: 200px;
    min-width: 200px;
  }

  .sticky-kelas {
    left: 260px;
    width: 120px;
    min-width: 120px;
  }

  .sticky-top .sticky-column {
    z-index: 11;
    background-color: #f8f9fa;
  }

  /* Question Columns */
  .question-col {
    width: 150px;
    min-width: 150px;
    vertical-align: middle;
  }

  .question-header {
    text-align: center;
    line-height: 1.3;
    padding: 0.75rem;
  }

  .q-number {
    font-weight: bold;
    font-size: 0.9rem;
    color: #374151;
    margin-bottom: 0.25rem;
  }

  .q-type {
    font-size: 0.75rem;
    margin-bottom: 0.25rem;
    padding: 0.125rem 0.5rem;
    border-radius: 0.25rem;
    font-weight: 500;
  }

  .q-type.rating {
    background-color: #fef3c7;
    color: #d97706;
  }

  .q-type.isian {
    background-color: #dbeafe;
    color: #2563eb;
  }

  .q-type.pilihan-ganda {
    background-color: #ddd6fe;
    color: #7c3aed;
  }

  .q-aspect {
    font-size: 0.7rem;
    color: #6b7280;
    font-weight: 500;
    word-wrap: break-word;
  }

  /* Answer Cells */
  .answer-cell {
    vertical-align: middle;
    padding: 0.75rem 0.5rem;
  }

  /* Rating Display */
  .rating-display {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
  }

  .rating-value {
    font-size: 1.25rem;
    font-weight: bold;
    color: #1f2937;
  }

  .rating-stars {
    display: flex;
    gap: 2px;
    font-size: 0.8rem;
  }

  /* Multiple Choice Display */
  .choice-display {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 0.375rem;
    min-height: 80px;
    justify-content: center;
    border: 1px solid #e5e7eb;
    background: #fff;
    transition: all 0.2s ease;
  }

  .choice-display:hover {
    background-color: #f3f4f6;
    border-color: #7c3aed;
  }

  .choice-label {
    font-size: 1.5rem;
    font-weight: bold;
    color: #7c3aed;
    width: 30px;
    height: 30px;
    background: #ddd6fe;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .choice-text {
    font-size: 0.75rem;
    color: #374151;
    text-align: center;
    word-wrap: break-word;
    max-width: 130px;
    line-height: 1.3;
  }

  /* Text Display */
  .text-display {
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 0.375rem;
    min-height: 80px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    border: 1px solid #e5e7eb;
    background: #fff;
    transition: all 0.2s ease;
  }

  .text-display:hover {
    background-color: #f3f4f6;
    border-color: #3b82f6;
  }

  .text-preview {
    font-size: 0.75rem;
    color: #374151;
    text-align: center;
    word-wrap: break-word;
    max-width: 130px;
    line-height: 1.3;
  }

  /* Student Info */
  .student-info {
    text-align: left;
  }

  .student-name {
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 0.125rem;
  }

  .student-nik {
    color: #6b7280;
    font-size: 0.75rem;
  }

  /* Average Row */
  .average-row {
    background-color: #f8f9fa !important;
    font-weight: 600;
    border-top: 2px solid #dee2e6;
  }

  .average-row .sticky-column {
    background-color: #f8f9fa !important;
  }

  /* Question Cards - Sederhana */
  .question-card {
    background: #f8f9fa;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    padding: 1.5rem;
    height: 100%;
  }

  .question-card-header {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1rem;
  }

  .question-badge {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    color: white;
    font-size: 1rem;
    flex-shrink: 0;
  }

  .question-badge.rating {
    background: #f59e0b;
  }

  .question-badge.isian {
    background: #3b82f6;
  }

  .question-badge.pilihan-ganda {
    background: #7c3aed;
  }

  .question-meta {
    flex-grow: 1;
  }

  .question-title {
    margin-bottom: 0.5rem;
    color: #1f2937;
    font-weight: 600;
    font-size: 1.1rem;
  }

  .question-text {
    margin-bottom: 0;
    color: #4b5563;
    font-size: 0.9rem;
    line-height: 1.5;
  }

  .question-stats {
    border-top: 1px solid #e5e7eb;
    padding-top: 1rem;
  }

  .stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
  }

  .stat-item:last-child {
    margin-bottom: 0;
  }

  .stat-label {
    font-weight: 500;
    color: #6b7280;
    font-size: 0.875rem;
  }

  .stat-value {
    font-weight: 600;
    color: #1f2937;
    font-size: 0.875rem;
  }

  /* Answer Display Modal */
  .answer-display {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 0.75rem;
    border: 1px solid #e5e7eb;
  }

  .answer-display p {
    margin-bottom: 0;
    line-height: 1.6;
    color: #374151;
  }

  /* Empty State */
  .empty-state {
    text-align: center;
    padding: 3rem 1rem;
  }

  .empty-state i {
    color: #9ca3af;
    margin-bottom: 1rem;
  }

  .empty-state h5 {
    color: #6b7280;
    margin-bottom: 0.5rem;
  }

  .empty-state p {
    color: #9ca3af;
    font-size: 0.875rem;
  }

  /* Responsive */
  @media (max-width: 768px) {
    .stats-number {
      font-size: 2rem;
    }

    .sticky-nama {
      width: 150px;
      min-width: 150px;
    }

    .sticky-kelas {
      left: 210px;
      width: 100px;
      min-width: 100px;
    }

    .question-col {
      width: 130px;
      min-width: 130px;
    }

    .text-preview, .choice-text {
      max-width: 110px;
      font-size: 0.7rem;
    }

    .controls-container {
      flex-direction: column;
      align-items: stretch;
    }

    .result-info {
      margin-left: 0;
      justify-content: center;
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
                <h2 class="page-title mb-1">RINGKASAN HASIL EVALUASI</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="#">Evaluasi & Feedback</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="index.php">Hasil Evaluasi</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Ringkasan</li>
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
        <!-- Alert Success -->
        <?php if (isset($_SESSION['success'])): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>
            <?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
          <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <!-- Alert Error -->
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
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-clipboard-data me-2"></i>
                  <?= htmlspecialchars($periode['nama_evaluasi']) ?>
                </h5>
                <small class="text-muted">
                  <?= htmlspecialchars($periode['nama_gelombang']) ?> (<?= $periode['tahun'] ?>) • 
                  <?= count($siswaData) ?> siswa selesai • <?= count($pertanyaanList) ?> pertanyaan
                </small>
              </div>
              <div class="col-md-4 text-md-end mt-3 mt-md-0">
                 <div class="d-flex align-items-centre justify-content-end gap-1" role="group">
                  <a href="detail.php?id_periode=<?= $id_periode ?>" 
                     class="btn btn-secondary-formal btn-sm">
                    Kembali
                  </a>
                  <button type="button" 
                          class="btn btn-success btn-sm"
                          onclick="exportToExcel(); return false;"
                          title="Export data ke Excel">
                    <i class="bi bi-file-excel me-1"></i>
                    Export Excel
                  </button>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Stats Cards - Clean version dengan 4 cards penting saja -->
          <div class="card-body">
            <div class="row g-4">
              <div class="col-6 col-md-3">
                <div class="stats-item">
                  <div class="stats-icon">
                    <i class="bi bi-people-fill text-primary fs-2"></i>
                  </div>
                  <div class="stats-number text-primary"><?= count($siswaData) ?></div>
                  <div class="stats-label">Siswa Selesai</div>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="stats-item">
                  <div class="stats-icon">
                    <i class="bi bi-question-circle-fill text-info fs-2"></i>
                  </div>
                  <div class="stats-number text-info"><?= count($pertanyaanList) ?></div>
                  <div class="stats-label">Total Pertanyaan</div>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="stats-item">
                  <div class="stats-icon">
                    <i class="bi bi-check-circle-fill text-success fs-2"></i>
                  </div>
                  <div class="stats-number text-success">
                    <?php 
                    $completionRate = $periode['total_siswa_aktif'] > 0 ? round(($periode['selesai'] / $periode['total_siswa_aktif']) * 100, 1) : 0;
                    echo $completionRate;
                    ?>%
                  </div>
                  <div class="stats-label">Completion Rate</div>
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="stats-item">
                  <div class="stats-icon">
                    <i class="bi bi-calendar-check-fill text-warning fs-2"></i>
                  </div>
                  <div class="stats-number text-warning">
                    <span class="badge bg-<?= $periode['status'] == 'aktif' ? 'success' : ($periode['status'] == 'selesai' ? 'secondary' : 'warning') ?>-subtle text-<?= $periode['status'] == 'aktif' ? 'success' : ($periode['status'] == 'selesai' ? 'secondary' : 'warning') ?>" style="font-size: 1rem;">
                      <?= ucfirst($periode['status']) ?>
                    </span>
                  </div>
                  <div class="stats-label">Status Evaluasi</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Filter Controls -->
        <div class="card content-card mb-4">
          <div class="p-3 border-bottom">
            <div class="row align-items-center">
              <div class="col-12">
                <div class="controls-container">
                  <!-- Search Box -->
                  <div class="search-container">
                    <label for="searchSiswa" class="me-2 mb-0 search-label">
                      <small>Search:</small>
                    </label>
                    <input type="search" id="searchSiswa" class="form-control form-control-sm search-input" />
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
                    <div class="dropdown-menu dropdown-menu-end shadow p-3" style="min-width: 250px;">
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
                            <option value="<?= htmlspecialchars($kelas) ?>"><?= htmlspecialchars($kelas) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      
                      <hr class="my-3">
                      
                      <!-- Filter Buttons -->
                      <div class="row g-2">
                        <div class="col-6">
                          <button class="btn btn-primary btn-sm w-100" id="applyFilter" type="button">
                            <i class="bi bi-check-lg me-1"></i>
                            Terapkan
                          </button>
                        </div>
                        <div class="col-6">
                          <button class="btn btn-light btn-sm w-100" id="resetFilter" type="button">
                            <i class="bi bi-arrow-clockwise me-1"></i>
                            Reset
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Result Info -->
                  <div class="result-info">
                    <label class="me-2 mb-0 search-label">
                      <small>Show:</small>
                    </label>
                    <div class="info-badge">
                      <span class="info-count" id="showingCount">1-<?= count($siswaData) ?></span>
                      <span class="info-separator">dari</span>
                      <span class="info-total" id="totalCount"><?= count($siswaData) ?></span>
                      <span class="info-label">data</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Main Matrix Table -->
        <div class="card content-card">
          <div class="section-header">
            <div class="row align-items-center">
              <div class="col-md-8">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-table me-2"></i>Matrix Jawaban Evaluasi
                </h5>
              </div>
              <div class="col-md-4 text-md-end">
                <small class="text-muted">
                  <i class="bi bi-info-circle me-1"></i>
                  Scroll horizontal untuk melihat semua pertanyaan
                </small>
              </div>
            </div>
          </div>

          <!-- Responsive Table -->
          <div class="table-responsive" style="overflow-x: auto; overflow-y: visible;">
            <table class="table table-bordered table-sm mb-0 custom-table" id="matrixTable">
              <thead class="sticky-top">
                <tr>
                  <th class="sticky-column sticky-no">No</th>
                  <th class="sticky-column sticky-nama">Nama Siswa</th>
                  <th class="sticky-column sticky-kelas">Kelas</th>
                  <?php foreach ($pertanyaanList as $index => $pertanyaan): ?>
                    <th class="question-col text-center" 
                        title="<?= htmlspecialchars($pertanyaan['pertanyaan']) ?>">
                      <div class="question-header">
                        <div class="q-number">Q<?= $index + 1 ?></div>
                        <div class="q-type <?= $pertanyaan['tipe_jawaban'] == 'skala' ? 'rating' : ($pertanyaan['tipe_jawaban'] == 'pilihan_ganda' ? 'pilihan-ganda' : 'isian') ?>">
                          <?php if ($pertanyaan['tipe_jawaban'] == 'skala'): ?>
                            ★ Rating
                          <?php elseif ($pertanyaan['tipe_jawaban'] == 'pilihan_ganda'): ?>
                            ☑ Multiple
                          <?php else: ?>
                            💬 Isian
                          <?php endif; ?>
                        </div>
                        <div class="q-aspect">
                          <?= htmlspecialchars($pertanyaan['aspek_dinilai']) ?>
                        </div>
                      </div>
                    </th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody id="tableBody">
                <?php if (!empty($siswaData)): ?>
                  <?php $no = 1; foreach ($siswaData as $siswa): ?>
                    <tr class="student-row" 
                        data-nama="<?= strtolower($siswa['nama']) ?>" 
                        data-nik="<?= $siswa['nik'] ?>"
                        data-kelas="<?= $siswa['nama_kelas'] ?>">
                      <!-- No -->
                      <td class="sticky-column sticky-no text-center"><?= $no++ ?></td>
                      
                      <!-- Nama Siswa -->
                      <td class="sticky-column sticky-nama">
                        <div class="student-info">
                          <div class="student-name"><?= htmlspecialchars($siswa['nama']) ?></div>
                          <small class="student-nik">NIK: <?= htmlspecialchars($siswa['nik']) ?></small>
                        </div>
                      </td>
                      
                      <!-- Kelas -->
                      <td class="sticky-column sticky-kelas text-center">
                        <span class="badge bg-primary-subtle text-primary">
                          <?= htmlspecialchars($siswa['nama_kelas']) ?>
                        </span>
                      </td>
                      
                      <!-- Jawaban per Pertanyaan -->
                      <?php foreach ($pertanyaanList as $pertanyaan): ?>
                        <td class="text-center answer-cell">
                          <?php 
                          $jawaban = $jawabanMatrix[$siswa['id_siswa']][$pertanyaan['id_pertanyaan']] ?? null;
                          if ($jawaban !== null): 
                          ?>
                            <?php if ($pertanyaan['tipe_jawaban'] == 'skala'): ?>
                              <div class="rating-display">
                                <div class="rating-value"><?= htmlspecialchars($jawaban) ?></div>
                                <div class="rating-stars">
                                  <?php for($i = 1; $i <= 5; $i++): ?>
                                    <i class="bi bi-star<?= $i <= (int)$jawaban ? '-fill text-warning' : ' text-muted' ?>"></i>
                                  <?php endfor; ?>
                                </div>
                              </div>
                            
                            <?php elseif ($pertanyaan['tipe_jawaban'] == 'pilihan_ganda'): ?>
                              <?php 
                              $pilihan = getPilihanJawaban($pertanyaan['pilihan_jawaban']);
                              $jawabanIndex = (int)$jawaban;
                              $jawabanText = isset($pilihan[$jawabanIndex]) ? $pilihan[$jawabanIndex] : 'Invalid';
                              $jawabanLabel = chr(65 + $jawabanIndex); // A, B, C, D
                              ?>
                              <div class="choice-display" 
                                   onclick="showChoiceDetail('<?= htmlspecialchars(addslashes($jawabanText)) ?>', '<?= $jawabanLabel ?>', <?= json_encode($pilihan) ?>)">
                                <div class="choice-label"><?= $jawabanLabel ?></div>
                                <div class="choice-text">
                                  <?= strlen($jawabanText) > 20 ? htmlspecialchars(substr($jawabanText, 0, 20)) . '...' : htmlspecialchars($jawabanText) ?>
                                </div>
                              </div>
                            
                            <?php else: ?>
                              <div class="text-display" 
                                   onclick="showFullAnswer('<?= htmlspecialchars(addslashes($jawaban)) ?>')">
                                <i class="bi bi-chat-text text-info fs-5"></i>
                                <div class="text-preview">
                                  <?= strlen($jawaban) > 30 ? htmlspecialchars(substr($jawaban, 0, 30)) . '...' : htmlspecialchars($jawaban) ?>
                                </div>
                                <small class="text-muted"><?= strlen($jawaban) ?> karakter</small>
                              </div>
                            <?php endif; ?>
                          <?php else: ?>
                            <span class="text-muted">-</span>
                          <?php endif; ?>
                        </td>
                      <?php endforeach; ?>
                    </tr>
                  <?php endforeach; ?>
                  
                  <!-- Average Row -->
                  <tr class="table-secondary average-row">
                    <td class="sticky-column sticky-no text-center fw-bold">AVG</td>
                    <td class="sticky-column sticky-nama fw-bold">Rata-rata / Distribusi</td>
                    <td class="sticky-column sticky-kelas text-center fw-bold">-</td>
                    <?php foreach ($pertanyaanList as $pertanyaan): ?>
                      <td class="text-center fw-bold">
                        <?php if ($pertanyaan['tipe_jawaban'] == 'skala' && isset($statistikPertanyaan[$pertanyaan['id_pertanyaan']]['rata_rata'])): ?>
                          <div class="text-warning"><?= $statistikPertanyaan[$pertanyaan['id_pertanyaan']]['rata_rata'] ?? '-' ?>/5</div>
                        <?php elseif ($pertanyaan['tipe_jawaban'] == 'pilihan_ganda'): ?>
                          <?php
                          $distribusi = $statistikPertanyaan[$pertanyaan['id_pertanyaan']]['distribusi_pilihan'];
                          if (!empty($distribusi)) {
                            $maxCount = max($distribusi);
                            $mostChosen = array_search($maxCount, $distribusi);
                            $mostChosenLabel = chr(65 + $mostChosen);
                            echo "<div class='text-success'>$mostChosenLabel ($maxCount)</div>";
                          } else {
                            echo '<div class="text-muted">-</div>';
                          }
                          ?>
                        <?php else: ?>
                          <div class="text-info"><?= $statistikPertanyaan[$pertanyaan['id_pertanyaan']]['total_jawaban'] ?> jawaban</div>
                        <?php endif; ?>
                      </td>
                    <?php endforeach; ?>
                  </tr>
                  
                <?php else: ?>
                  <tr>
                    <td colspan="<?= 3 + count($pertanyaanList) ?>" class="text-center">
                      <div class="empty-state">
                        <i class="bi bi-table display-4 text-muted mb-3 d-block"></i>
                        <h5>Belum Ada Data</h5>
                        <p class="text-muted">Belum ada siswa yang menyelesaikan evaluasi ini</p>
                      </div>
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Question Details -->
        <div class="card content-card mt-4">
          <div class="section-header">
            <h5 class="mb-0 text-dark">
              <i class="bi bi-list-ol me-2"></i>Detail Pertanyaan Evaluasi
            </h5>
          </div>
          <div class="card-body">
            <div class="row">
              <?php foreach ($pertanyaanList as $index => $pertanyaan): ?>
                <div class="col-lg-6 mb-4">
                  <div class="question-card">
                    <div class="question-card-header">
                      <span class="question-badge <?= $pertanyaan['tipe_jawaban'] == 'skala' ? 'rating' : ($pertanyaan['tipe_jawaban'] == 'pilihan_ganda' ? 'pilihan-ganda' : 'isian') ?>">
                        Q<?= $index + 1 ?>
                      </span>
                      <div class="question-meta">
                        <h6 class="question-title"><?= htmlspecialchars($pertanyaan['aspek_dinilai']) ?></h6>
                        <p class="question-text"><?= htmlspecialchars($pertanyaan['pertanyaan']) ?></p>
                      </div>
                    </div>
                    <div class="question-stats">
                      <div class="stat-item">
                        <span class="stat-label">Tipe:</span>
                        <span class="badge bg-<?= $pertanyaan['tipe_jawaban'] == 'skala' ? 'warning' : ($pertanyaan['tipe_jawaban'] == 'pilihan_ganda' ? 'success' : 'info') ?>-subtle text-<?= $pertanyaan['tipe_jawaban'] == 'skala' ? 'warning' : ($pertanyaan['tipe_jawaban'] == 'pilihan_ganda' ? 'success' : 'info') ?>">
                          <?php if ($pertanyaan['tipe_jawaban'] == 'skala'): ?>
                            ★ Rating 1-5
                          <?php elseif ($pertanyaan['tipe_jawaban'] == 'pilihan_ganda'): ?>
                            ☑ Pilihan Ganda
                          <?php else: ?>
                            💬 Isian Bebas
                          <?php endif; ?>
                        </span>
                      </div>
                      <div class="stat-item">
                        <span class="stat-label">Statistik:</span>
                        <span class="stat-value">
                          <?php if ($pertanyaan['tipe_jawaban'] == 'skala' && isset($statistikPertanyaan[$pertanyaan['id_pertanyaan']]['rata_rata'])): ?>
                            Rata-rata: <?= $statistikPertanyaan[$pertanyaan['id_pertanyaan']]['rata_rata'] ?>/5 
                            <small class="text-muted">(<?= $statistikPertanyaan[$pertanyaan['id_pertanyaan']]['total_jawaban'] ?> jawaban)</small>
                          <?php elseif ($pertanyaan['tipe_jawaban'] == 'pilihan_ganda'): ?>
                            <?php
                            $distribusi = $statistikPertanyaan[$pertanyaan['id_pertanyaan']]['distribusi_pilihan'];
                            if (!empty($distribusi)) {
                              $maxCount = max($distribusi);
                              $mostChosen = array_search($maxCount, $distribusi);
                              $mostChosenLabel = chr(65 + $mostChosen);
                              echo "Terpopuler: $mostChosenLabel ($maxCount jawaban)";
                            } else {
                              echo "Belum ada jawaban";
                            }
                            ?>
                          <?php else: ?>
                            <?= $statistikPertanyaan[$pertanyaan['id_pertanyaan']]['total_jawaban'] ?> jawaban 
                            <small class="text-muted">(rata-rata <?= $statistikPertanyaan[$pertanyaan['id_pertanyaan']]['avg_length'] ?> karakter)</small>
                          <?php endif; ?>
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal for Full Answer -->
  <div class="modal fade" id="answerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="bi bi-chat-text text-info me-2"></i>Jawaban Lengkap
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="answer-display">
            <p id="fullAnswerText"></p>
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

  <!-- Modal for Choice Detail -->
  <div class="modal fade" id="choiceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="bi bi-check2-square text-success me-2"></i>Detail Pilihan Jawaban
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="answer-display">
            <div class="row align-items-center mb-3">
              <div class="col-auto">
                <span class="badge bg-success rounded-circle d-inline-flex align-items-center justify-content-center" 
                      style="width: 40px; height: 40px; font-size: 1.2rem; font-weight: 600;" id="choiceLabel">A</span>
              </div>
              <div class="col">
                <h6 class="mb-0">Jawaban yang dipilih:</h6>
                <p class="mb-0" id="choiceText">-</p>
              </div>
            </div>
            <hr>
            <h6>Semua pilihan yang tersedia:</h6>
            <div id="allChoices"></div>
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
    const rows = Array.from(document.querySelectorAll('.student-row'));
    const searchInput = document.getElementById('searchSiswa');
    const filterKelas = document.getElementById('filterKelas');
    const filterButton = document.getElementById('filterButton');
    const filterBadge = document.getElementById('filterBadge');
    const applyFilterBtn = document.getElementById('applyFilter');
    const resetFilterBtn = document.getElementById('resetFilter');
    
    let activeFilters = 0;

    // Filter functionality
    function applyFilters() {
      const searchTerm = (searchInput?.value || '').toLowerCase().trim();
      const kelasFilter = filterKelas?.value || '';
      
      let visibleCount = 0;
      activeFilters = 0;
      
      if (kelasFilter) activeFilters++;
      
      updateFilterBadge();
      
      rows.forEach((row, index) => {
        const nama = row.dataset.nama || '';
        const nik = row.dataset.nik || '';
        const kelas = row.dataset.kelas || '';
        
        let showRow = true;
        
        // Apply search filter
        if (searchTerm && !nama.includes(searchTerm) && !nik.includes(searchTerm)) {
          showRow = false;
        }
        
        // Apply kelas filter
        if (kelasFilter && kelas !== kelasFilter) {
          showRow = false;
        }
        
        row.style.display = showRow ? '' : 'none';
        if (showRow) {
          visibleCount++;
          // Update row numbers
          row.cells[0].textContent = visibleCount;
        }
      });
      
      // Update display count
      document.getElementById('showingCount').textContent = `1-${visibleCount}`;
      document.getElementById('totalCount').textContent = rows.length;
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

    // Event listeners
    let searchTimeout;
    if (searchInput) {
      searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
          applyFilters();
        }, 300);
      });
    }

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

    // Prevent dropdown close on click inside
    const filterDropdown = document.querySelector('.dropdown-menu.p-3');
    if (filterDropdown) {
      filterDropdown.addEventListener('click', function(e) {
        e.stopPropagation();
      });
    }

    // Show full answer function
    window.showFullAnswer = function(text) {
      document.getElementById('fullAnswerText').textContent = text;
      new bootstrap.Modal(document.getElementById('answerModal')).show();
    };

    // Show choice detail function
    window.showChoiceDetail = function(chosenText, chosenLabel, allChoices) {
      document.getElementById('choiceLabel').textContent = chosenLabel;
      document.getElementById('choiceText').textContent = chosenText;
      
      let allChoicesHtml = '';
      allChoices.forEach((choice, index) => {
        const label = String.fromCharCode(65 + index); // A, B, C, D
        const isChosen = label === chosenLabel;
        allChoicesHtml += `
          <div class="d-flex align-items-center mb-2 p-2 rounded ${isChosen ? 'bg-success-subtle' : 'bg-light'}">
            <span class="badge bg-secondary me-2" style="width: 24px; height: 24px; font-size: 0.75rem;">
              ${label}
            </span>
            <span class="${isChosen ? 'fw-bold text-success' : 'text-muted'}">
              ${choice} ${isChosen ? '✓' : ''}
            </span>
          </div>
        `;
      });
      
      document.getElementById('allChoices').innerHTML = allChoicesHtml;
      new bootstrap.Modal(document.getElementById('choiceModal')).show();
    };

    // Export to Excel functionality
    window.exportToExcel = function() {
      try {
        const wb = XLSX.utils.book_new();
        
        // Sheet: Matrix Jawaban
        const matrixData = [];
        
        // Header row
        const headerRow = ['No', 'Nama Siswa', 'Kelas'];
        <?php foreach ($pertanyaanList as $index => $pertanyaan): ?>
          headerRow.push('Q<?= $index + 1 ?> - <?= htmlspecialchars($pertanyaan['aspek_dinilai']) ?> (<?= ucfirst($pertanyaan['tipe_jawaban']) ?>)');
        <?php endforeach; ?>
        matrixData.push(headerRow);
        
        // Data rows (only visible)
        const visibleRows = Array.from(document.querySelectorAll('.student-row')).filter(row => row.style.display !== 'none');
        visibleRows.forEach((row, index) => {
          const rowData = [
            index + 1,
            row.cells[1].querySelector('.student-name').textContent,
            row.cells[2].querySelector('.badge').textContent
          ];
          
          // Add all answers
          for(let i = 3; i < row.cells.length; i++) {
            const cell = row.cells[i];
            const ratingEl = cell.querySelector('.rating-value');
            const choiceEl = cell.querySelector('.choice-display');
            const textEl = cell.querySelector('.text-display');
            
            if (ratingEl) {
              rowData.push(ratingEl.textContent);
            } else if (choiceEl) {
              // Extract choice label and text
              const choiceLabel = choiceEl.querySelector('.choice-label')?.textContent || '';
              const choiceText = choiceEl.querySelector('.choice-text')?.textContent || '';
              rowData.push(`${choiceLabel}: ${choiceText}`);
            } else if (textEl) {
              // Extract full answer from onclick attribute
              const onclickAttr = textEl.getAttribute('onclick');
              if (onclickAttr) {
                const match = onclickAttr.match(/showFullAnswer\('(.+?)'\)/);
                if (match) {
                  const fullText = match[1].replace(/\\'/g, "'").replace(/\\"/g, '"');
                  rowData.push(fullText);
                } else {
                  rowData.push(textEl.querySelector('.text-preview')?.textContent || 'Jawaban isian');
                }
              } else {
                rowData.push(textEl.querySelector('.text-preview')?.textContent || 'Jawaban isian');
              }
            } else {
              rowData.push('-');
            }
          }
          matrixData.push(rowData);
        });
        
        // Add statistics row
        const statsRow = ['AVG', 'Rata-rata / Distribusi', '-'];
        <?php foreach ($pertanyaanList as $pertanyaan): ?>
          <?php if ($pertanyaan['tipe_jawaban'] == 'skala' && isset($statistikPertanyaan[$pertanyaan['id_pertanyaan']]['rata_rata'])): ?>
            statsRow.push('<?= $statistikPertanyaan[$pertanyaan['id_pertanyaan']]['rata_rata'] ?? '-' ?>/5');
          <?php elseif ($pertanyaan['tipe_jawaban'] == 'pilihan_ganda'): ?>
            <?php
            $distribusi = $statistikPertanyaan[$pertanyaan['id_pertanyaan']]['distribusi_pilihan'];
            if (!empty($distribusi)) {
              $maxCount = max($distribusi);
              $mostChosen = array_search($maxCount, $distribusi);
              $mostChosenLabel = chr(65 + $mostChosen);
              echo "statsRow.push('Terpopuler: $mostChosenLabel ($maxCount)');";
            } else {
              echo "statsRow.push('Belum ada jawaban');";
            }
            ?>
          <?php else: ?>
            statsRow.push('<?= $statistikPertanyaan[$pertanyaan['id_pertanyaan']]['total_jawaban'] ?> jawaban');
          <?php endif; ?>
        <?php endforeach; ?>
        matrixData.push(statsRow);
        
        // Create worksheet with proper column widths
        const matrixWs = XLSX.utils.aoa_to_sheet(matrixData);
        const colWidths = [
          { wch: 5 },   // No
          { wch: 25 },  // Nama
          { wch: 15 },  // Kelas
        ];
        
        // Add column widths for questions
        <?php foreach ($pertanyaanList as $pertanyaan): ?>
          <?php if ($pertanyaan['tipe_jawaban'] == 'skala'): ?>
            colWidths.push({ wch: 15 });
          <?php elseif ($pertanyaan['tipe_jawaban'] == 'pilihan_ganda'): ?>
            colWidths.push({ wch: 30 }); // Lebar untuk pilihan ganda
          <?php else: ?>
            colWidths.push({ wch: 50 }); // Lebar untuk jawaban isian
          <?php endif; ?>
        <?php endforeach; ?>
        
        matrixWs['!cols'] = colWidths;
        XLSX.utils.book_append_sheet(wb, matrixWs, 'Matrix Jawaban');
        
        // Generate filename
        const filename = `Ringkasan_Evaluasi_<?= str_replace(' ', '_', $periode['nama_evaluasi']) ?>_<?= date('Ymd_His') ?>.xlsx`;
        
        // Export file
        XLSX.writeFile(wb, filename);
        
        // Show success notification
        const toast = document.createElement('div');
        toast.className = 'position-fixed top-0 end-0 p-3';
        toast.style.zIndex = '9999';
        toast.innerHTML = `
          <div class="toast show" role="alert">
            <div class="toast-header">
              <i class="bi bi-check-circle-fill text-success me-2"></i>
              <strong class="me-auto">Export Berhasil</strong>
              <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
              File Excel berhasil diunduh!
            </div>
          </div>
        `;
        document.body.appendChild(toast);
        
        setTimeout(() => {
          toast.remove();
        }, 4000);
        
      } catch (error) {
        console.error('Export error:', error);
        alert('Gagal mengexport file. Pastikan browser mendukung download file.');
      }
    };

    // Initialize tooltips
    try {
      const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
      });
    } catch (e) {
      console.log('Tooltip initialization skipped');
    }
  });
  </script>
</body>
</html>