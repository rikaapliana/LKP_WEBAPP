<?php
session_start();  
require_once '../../../../includes/auth.php';  
requireAdminAuth();

include '../../../../includes/db.php';
$activePage = 'evaluasi'; 
$baseURL = '../../';

// Get periode ID
$id_periode = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_periode <= 0) {
    $_SESSION['error'] = "ID periode tidak valid.";
    header("Location: index.php");
    exit;
}

// Ambil data periode dengan detail lengkap
$periodeQuery = "SELECT pe.*, 
                        g.nama_gelombang, g.tahun, g.gelombang_ke,
                        a.nama as nama_admin,
                        (SELECT COUNT(*) FROM evaluasi e WHERE e.id_periode = pe.id_periode) as total_responden,
                        (SELECT COUNT(*) FROM evaluasi e WHERE e.id_periode = pe.id_periode AND e.status_evaluasi = 'selesai') as responden_selesai
                 FROM periode_evaluasi pe 
                 LEFT JOIN gelombang g ON pe.id_gelombang = g.id_gelombang 
                 LEFT JOIN admin a ON pe.dibuat_oleh = a.id_admin 
                 WHERE pe.id_periode = ?";
$periodeStmt = mysqli_prepare($conn, $periodeQuery);
mysqli_stmt_bind_param($periodeStmt, "i", $id_periode);
mysqli_stmt_execute($periodeStmt);
$periodeResult = mysqli_stmt_get_result($periodeStmt);

if (!$periode = mysqli_fetch_assoc($periodeResult)) {
    $_SESSION['error'] = "Periode evaluasi tidak ditemukan.";
    header("Location: index.php");
    exit;
}

// Get selected questions from JSON - Handle multiple formats
$selected_questions_data = [];
$total_pertanyaan = 0;
$pertanyaan_ids = [];
$urutan_map = [];

if (!empty($periode['pertanyaan_terpilih'])) {
    $decoded_data = json_decode($periode['pertanyaan_terpilih'], true);
    
    if (is_array($decoded_data) && !empty($decoded_data)) {
        $total_pertanyaan = count($decoded_data);
        
        // Check format of data
        $first_item = reset($decoded_data);
        
        if (is_array($first_item) && isset($first_item['id'])) {
            // New format: [{"id":83,"urutan":1},{"id":84,"urutan":2}]
            foreach ($decoded_data as $item) {
                if (isset($item['id'])) {
                    $id = (int)$item['id'];
                    $pertanyaan_ids[] = $id;
                    $urutan_map[$id] = isset($item['urutan']) ? (int)$item['urutan'] : count($urutan_map) + 1;
                    $selected_questions_data[] = $item;
                }
            }
        } else {
            // Old format: [83,84,85,86,87,88,89,90,91,92]
            foreach ($decoded_data as $index => $id) {
                $id = (int)$id;
                $pertanyaan_ids[] = $id;
                $urutan_map[$id] = $index + 1;
                $selected_questions_data[] = ['id' => $id, 'urutan' => $index + 1];
            }
        }
    }
}

// Get selected questions details
$selectedQuestions = [];
if (!empty($pertanyaan_ids)) {
    $pertanyaan_ids = array_unique($pertanyaan_ids);
    $placeholders = implode(',', array_fill(0, count($pertanyaan_ids), '?'));
    $selectedQuery = "SELECT * FROM pertanyaan_evaluasi 
                     WHERE id_pertanyaan IN ($placeholders)
                     ORDER BY FIELD(id_pertanyaan, " . implode(',', $pertanyaan_ids) . ")";
    $selectedStmt = mysqli_prepare($conn, $selectedQuery);
    
    $types = str_repeat('i', count($pertanyaan_ids));
    mysqli_stmt_bind_param($selectedStmt, $types, ...$pertanyaan_ids);
    mysqli_stmt_execute($selectedStmt);
    $selectedResult = mysqli_stmt_get_result($selectedStmt);
    
    while ($question = mysqli_fetch_assoc($selectedResult)) {
        $selectedQuestions[] = $question;
    }
}

// Statistik pertanyaan berdasarkan tipe
$questionStats = [
    'pilihan_ganda' => 0,
    'skala' => 0,
    'isian' => 0
];

foreach ($selectedQuestions as $question) {
    if (isset($questionStats[$question['tipe_jawaban']])) {
        $questionStats[$question['tipe_jawaban']]++;
    }
}

// Count available questions for info display
$availableQuery = "SELECT COUNT(*) as total FROM pertanyaan_evaluasi pe 
                   WHERE pe.jenis_evaluasi = ? AND pe.is_active = 1";
$params = [$periode['jenis_evaluasi']];

if ($periode['jenis_evaluasi'] === 'per_materi' && $periode['materi_terkait']) {
    $availableQuery .= " AND pe.materi_terkait = ?";
    $params[] = $periode['materi_terkait'];
} else if ($periode['jenis_evaluasi'] === 'akhir_kursus') {
    $availableQuery .= " AND (pe.materi_terkait IS NULL OR pe.materi_terkait = '')";
}

$availableStmt = mysqli_prepare($conn, $availableQuery);
if (count($params) > 1) {
    mysqli_stmt_bind_param($availableStmt, "ss", $params[0], $params[1]);
} else {
    mysqli_stmt_bind_param($availableStmt, "s", $params[0]);
}
mysqli_stmt_execute($availableStmt);
$result = mysqli_fetch_assoc(mysqli_stmt_get_result($availableStmt));
$totalAvailable = $result['total'];
$availableCount = $totalAvailable - $total_pertanyaan;

// Helper functions
function getStatusBadge($status) {
    switch($status) {
        case 'draft':
            return '<span class="badge bg-secondary fs-6"><i class="bi bi-pencil-square me-1"></i>Draft</span>';
        case 'aktif':
            return '<span class="badge bg-success fs-6"><i class="bi bi-play-circle me-1"></i>Aktif</span>';
        case 'selesai':
            return '<span class="badge bg-primary fs-6"><i class="bi bi-check-circle me-1"></i>Selesai</span>';
        default:
            return '<span class="badge bg-light text-dark fs-6">Unknown</span>';
    }
}

function formatDateTime($datetime) {
    return date('d M Y, H:i', strtotime($datetime));
}

function getPilihanJawaban($pilihan_jawaban) {
    if (empty($pilihan_jawaban)) return [];
    $decoded = json_decode($pilihan_jawaban, true);
    return is_array($decoded) ? $decoded : [];
}

// Cek status periode saat ini
$now = time();
$buka = strtotime($periode['tanggal_buka']);
$tutup = strtotime($periode['tanggal_tutup']);

$periodStatus = '';
if ($periode['status'] == 'aktif') {
    if ($now < $buka) {
        $periodStatus = '<small class="text-warning"><i class="bi bi-clock me-1"></i>Belum dimulai</small>';
    } elseif ($now > $tutup) {
        $periodStatus = '<small class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Sudah berakhir</small>';
    } else {
        $periodStatus = '<small class="text-success"><i class="bi bi-play-circle me-1"></i>Sedang berjalan</small>';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Detail Periode Evaluasi</title>
  <link rel="icon" type="image/png" href="../../../../assets/img/favicon.png"/>
  <link rel="stylesheet" href="../../../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../../../assets/css/fonts.css" />
  <link rel="stylesheet" href="../../../../assets/css/styles.css" />
</head>

<body>
  <div class="d-flex">
    <?php include '../../../../includes/sidebar/admin.php'; ?>

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
                <h2 class="page-title mb-1">DETAIL PERIODE EVALUASI</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="#">Evaluasi</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="index.php">Periode Evaluasi</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Detail #<?= $id_periode ?></li>
                  </ol>
                </nav>
              </div>
            </div>
            
            <div class="d-flex align-items-center">
              <div class="navbar-page-info d-none d-xl-block">
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

        <!-- Header Section -->
        <div class="row mb-4">
          <div class="col-12">
            <div class="card content-card">
              <div class="section-header">
                <div class="row align-items-center">
                  <div class="col-md-8">
                    <h5 class="mb-0 text-dark">
                      <i class="bi bi-calendar-check me-2"></i><?= htmlspecialchars($periode['nama_evaluasi']) ?>
                    </h5>
                  </div>
                    <div class="col-md-4 text-md-end">
                     <div class="d-flex gap-3">
                  <a href="index.php" class="btn btn-kembali px-4">
                    Kembali
                  </a>
                <a href="edit.php?id=<?= $id_periode ?>"  class="btn btn-edit px-4">
                    <i class="bi bi-pencil me-1"></i>Edit Data
                  </a>
                </div>
                </div>
                </div>
              </div>
              <div class="card-body">
                <div class="row align-items-center">
                  <div class="col-md-8">
                    <div class="d-flex align-items-center gap-3 mb-2">
                      <?= getStatusBadge($periode['status']) ?>
                      <?php if($periode['jenis_evaluasi'] == 'akhir_kursus'): ?>
                        <span class="badge bg-success"><i class="bi bi-award me-1"></i>Akhir Kursus</span>
                      <?php else: ?>
                        <span class="badge bg-info"><i class="bi bi-book me-1"></i>Per Materi - <?= strtoupper($periode['materi_terkait']) ?></span>
                      <?php endif; ?>
                      <span class="badge bg-primary"><?= htmlspecialchars($periode['nama_gelombang']) ?> (<?= $periode['tahun'] ?>)</span>
                    </div>
                    <?php if (!empty($periode['deskripsi'])): ?>
                      <p class="text-muted mb-2"><?= nl2br(htmlspecialchars($periode['deskripsi'])) ?></p>
                    <?php endif; ?>
                    <div class="d-flex align-items-center gap-3 text-muted">
                      <small><i class="bi bi-calendar me-1"></i>Buka: <?= formatDateTime($periode['tanggal_buka']) ?></small>
                      <small><i class="bi bi-calendar-x me-1"></i>Tutup: <?= formatDateTime($periode['tanggal_tutup']) ?></small>
                    </div>
                    <?= $periodStatus ?>
                  </div>
                  <div class="col-md-4">
                    <div class="row text-center">
                      <div class="col-4">
                        <div class="stat-item">
                          <h4 class="fw-bold text-primary"><?= $total_pertanyaan ?></h4>
                          <small class="text-muted">Pertanyaan</small>
                        </div>
                      </div>
                      <div class="col-4">
                        <div class="stat-item">
                          <h4 class="fw-bold text-success"><?= $periode['total_responden'] ?></h4>
                          <small class="text-muted">Responden</small>
                        </div>
                      </div>
                      <div class="col-4">
                        <div class="stat-item">
                          <h4 class="fw-bold text-info">
                            <?= $periode['total_responden'] > 0 ? round(($periode['responden_selesai'] / $periode['total_responden']) * 100) : 0 ?>%
                          </h4>
                          <small class="text-muted">Selesai</small>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Content Row -->
        <div class="row">
          <!-- Main Content -->
          <div class="col-lg-8">
            <!-- Pertanyaan Terpilih -->
            <div class="card content-card mb-4">
              <div class="section-header">
                <div class="row align-items-center">
                  <div class="col-md-8">
                    <h5 class="mb-0 text-dark">
                      <i class="bi bi-list-check me-2"></i>Pertanyaan Evaluasi (<?= $total_pertanyaan ?>)
                    </h5>
                  </div>
                  <div class="col-md-4 text-md-end">
                    <?php if ($total_pertanyaan > 0): ?>
                      <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalViewQuestions">
                        <i class="bi bi-eye me-1"></i>Lihat Semua
                      </button>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <div class="card-body">
                <?php if ($total_pertanyaan > 0): ?>
                  <div class="questions-list">
                    <?php foreach ($selectedQuestions as $index => $pertanyaan): ?>
                      <div class="question-card mb-3">
                        <div class="card border-start border-success border-3">
                          <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                              <div class="flex-grow-1">
                                <div class="d-flex align-items-center mb-2">
                                  <span class="badge bg-primary me-2">#<?= $urutan_map[$pertanyaan['id_pertanyaan']] ?? ($index + 1) ?></span>
                                  <span class="badge bg-secondary me-2"><?= htmlspecialchars($pertanyaan['aspek_dinilai']) ?></span>
                                  <?php if($pertanyaan['tipe_jawaban'] == 'pilihan_ganda'): ?>
                                    <span class="badge bg-info"><i class="bi bi-check2-square me-1"></i>Pilihan Ganda</span>
                                  <?php elseif($pertanyaan['tipe_jawaban'] == 'skala'): ?>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-star me-1"></i>Skala 1-5</span>
                                  <?php else: ?>
                                    <span class="badge bg-primary"><i class="bi bi-pencil me-1"></i>Isian</span>
                                  <?php endif; ?>
                                </div>
                                <div class="question-text mb-2">
                                  <?= nl2br(htmlspecialchars($pertanyaan['pertanyaan'])) ?>
                                </div>
                                
                                <?php if ($pertanyaan['tipe_jawaban'] == 'pilihan_ganda'): ?>
                                  <?php $pilihan = getPilihanJawaban($pertanyaan['pilihan_jawaban']); ?>
                                  <?php if (!empty($pilihan)): ?>
                                    <div class="pilihan-preview mt-2">
                                      <small class="text-muted fw-bold">Pilihan:</small>
                                      <div class="pilihan-list mt-1">
                                        <?php foreach ($pilihan as $idx => $option): ?>
                                          <div class="pilihan-item">
                                            <span class="pilihan-label"><?= chr(65 + $idx) ?>.</span>
                                            <span class="pilihan-text"><?= htmlspecialchars($option) ?></span>
                                          </div>
                                        <?php endforeach; ?>
                                      </div>
                                    </div>
                                  <?php endif; ?>
                                <?php endif; ?>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <div class="empty-state text-center py-5">
                    <i class="bi bi-question-circle display-4 text-muted mb-3"></i>
                    <h6>Belum Ada Pertanyaan</h6>
                    <p class="text-muted">Pilih pertanyaan dari bank soal untuk ditambahkan ke periode ini</p>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <!-- Informasi Detail -->
            <div class="card content-card">
              <div class="section-header">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-info-circle me-2"></i>Informasi Detail
                </h5>
              </div>
              <div class="card-body">
                <div class="row g-3">
                  <div class="col-md-6">
                    <small class="text-muted">ID Periode</small>
                    <div class="fw-medium">#<?= $id_periode ?></div>
                  </div>
                  <div class="col-md-6">
                    <small class="text-muted">Jenis Evaluasi</small>
                    <div class="fw-medium"><?= $periode['jenis_evaluasi'] == 'per_materi' ? 'Evaluasi Per Materi' : 'Evaluasi Akhir Kursus' ?></div>
                  </div>
                  <?php if ($periode['materi_terkait']): ?>
                  <div class="col-md-6">
                    <small class="text-muted">Materi Terkait</small>
                    <div class="fw-medium"><?= strtoupper($periode['materi_terkait']) ?></div>
                  </div>
                  <?php endif; ?>
                  <div class="col-md-6">
                    <small class="text-muted">Dibuat Oleh</small>
                    <div class="fw-medium"><?= htmlspecialchars($periode['nama_admin'] ?? 'Admin') ?></div>
                  </div>
                  <div class="col-md-6">
                    <small class="text-muted">Dibuat Pada</small>
                    <div class="fw-medium"><?= formatDateTime($periode['created_at']) ?></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Sidebar -->
          <div class="col-lg-4">
            <!-- Statistik Pertanyaan -->
            <div class="card content-card mb-4">
              <div class="section-header">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-bar-chart me-2"></i>Statistik Pertanyaan
                </h5>
              </div>
              <div class="card-body">
                <div class="row text-center">
                  <div class="col-4">
                    <div class="question-stat">
                      <div class="stat-icon text-info mb-2">
                        <i class="bi bi-check2-square fs-2"></i>
                      </div>
                      <h4 class="fw-bold"><?= $questionStats['pilihan_ganda'] ?></h4>
                      <small class="text-muted">Pilihan Ganda</small>
                    </div>
                  </div>
                  <div class="col-4">
                    <div class="question-stat">
                      <div class="stat-icon text-warning mb-2">
                        <i class="bi bi-star fs-2"></i>
                      </div>
                      <h4 class="fw-bold"><?= $questionStats['skala'] ?></h4>
                      <small class="text-muted">Skala 1-5</small>
                    </div>
                  </div>
                  <div class="col-4">
                    <div class="question-stat">
                      <div class="stat-icon text-primary mb-2">
                        <i class="bi bi-pencil fs-2"></i>
                      </div>
                      <h4 class="fw-bold"><?= $questionStats['isian'] ?></h4>
                      <small class="text-muted">Isian Bebas</small>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Info Tambahan -->
            <div class="card content-card">
              <div class="section-header">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-info-circle me-2"></i>Informasi Tambahan
                </h5>
              </div>
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                  <small class="text-muted">Total Bank Soal Tersedia</small>
                  <span class="badge bg-info"><?= $totalAvailable ?></span>
                </div>
                <div class="d-flex align-items-center justify-content-between mb-3">
                  <small class="text-muted">Pertanyaan Dipilih</small>
                  <span class="badge bg-success"><?= $total_pertanyaan ?></span>
                </div>
                <div class="d-flex align-items-center justify-content-between mb-3">
                  <small class="text-muted">Sisa Tersedia</small>
                  <span class="badge bg-secondary"><?= max(0, $availableCount) ?></span>
                </div>
                <hr>
                <div class="text-center">
                  <small class="text-muted">
                    <i class="bi bi-lightbulb me-1"></i>
                    Gunakan <strong>Edit Periode</strong> untuk mengelola pertanyaan
                  </small>
                </div>
                
                <?php if ($periode['responden_selesai'] > 0): ?>
                  <hr>
                  <div class="text-center">
                    <a href="../../../hasil-evaluasi/ringkasan.php?id_periode=<?= $id_periode ?>" class="btn btn-success btn-sm w-100">
                      <i class="bi bi-table me-1"></i>Lihat Ringkasan Hasil
                    </a>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Lihat Semua Pertanyaan -->
  <div class="modal fade" id="modalViewQuestions" tabindex="-1">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="bi bi-list-check me-2"></i>Semua Pertanyaan Evaluasi (<?= $total_pertanyaan ?>)
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <?php if ($total_pertanyaan > 0): ?>
            <div class="row g-3">
              <?php foreach ($selectedQuestions as $index => $question): ?>
                <div class="col-12">
                  <div class="card question-card">
                    <div class="card-body">
                      <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="question-meta">
                          <span class="badge bg-primary me-2">#<?= $urutan_map[$question['id_pertanyaan']] ?? ($index + 1) ?></span>
                          <?php if($question['tipe_jawaban'] == 'pilihan_ganda'): ?>
                            <span class="badge bg-info"><i class="bi bi-check2-square me-1"></i>Pilihan Ganda</span>
                          <?php elseif($question['tipe_jawaban'] == 'skala'): ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-star me-1"></i>Skala 1-5</span>
                          <?php else: ?>
                            <span class="badge bg-primary"><i class="bi bi-pencil me-1"></i>Isian Bebas</span>
                          <?php endif; ?>
                          <span class="badge bg-light text-dark ms-1"><?= htmlspecialchars($question['aspek_dinilai']) ?></span>
                        </div>
                        <small class="text-muted">ID: <?= $question['id_pertanyaan'] ?></small>
                      </div>
                      
                      <div class="question-text mb-3">
                        <?= nl2br(htmlspecialchars($question['pertanyaan'])) ?>
                      </div>
                      
                      <?php if ($question['tipe_jawaban'] == 'pilihan_ganda'): ?>
                        <?php $pilihan = getPilihanJawaban($question['pilihan_jawaban']); ?>
                        <?php if (!empty($pilihan)): ?>
                          <div class="pilihan-preview">
                            <strong class="text-muted small">Pilihan Jawaban:</strong>
                            <div class="pilihan-list mt-2">
                              <?php foreach ($pilihan as $idx => $option): ?>
                                <div class="pilihan-item mb-1">
                                  <span class="pilihan-label fw-bold"><?= chr(65 + $idx) ?>.</span>
                                  <span class="pilihan-text"><?= htmlspecialchars($option) ?></span>
                                </div>
                              <?php endforeach; ?>
                            </div>
                          </div>
                        <?php endif; ?>
                      <?php elseif ($question['tipe_jawaban'] == 'skala'): ?>
                        <div class="skala-preview">
                          <strong class="text-muted small">Format Jawaban:</strong>
                          <div class="mt-2">
                            <span class="badge bg-light text-dark">1 - Sangat Tidak Setuju</span>
                            <span class="badge bg-light text-dark">2 - Tidak Setuju</span>
                            <span class="badge bg-light text-dark">3 - Netral</span>
                            <span class="badge bg-light text-dark">4 - Setuju</span>
                            <span class="badge bg-light text-dark">5 - Sangat Setuju</span>
                          </div>
                        </div>
                      <?php else: ?>
                        <div class="isian-preview">
                          <strong class="text-muted small">Format Jawaban:</strong>
                          <div class="mt-2">
                            <span class="badge bg-light text-dark">Isian teks bebas</span>
                          </div>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="text-center py-4">
              <i class="bi bi-question-circle display-4 text-muted mb-3"></i>
              <h6>Tidak Ada Pertanyaan</h6>
              <p class="text-muted">Belum ada pertanyaan yang dipilih untuk periode ini.</p>
            </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
        </div>
      </div>
    </div>
  </div>

  <script src="../../../../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../../../assets/js/scripts.js"></script>

  <script>
  function copyEvaluationLink() {
    // Construct the evaluation link for students
    const baseUrl = window.location.origin;
    const evaluationLink = `${baseUrl}/pages/siswa/evaluasi/index.php?periode=${<?= $id_periode ?>}`;
    
    // Copy to clipboard
    navigator.clipboard.writeText(evaluationLink).then(function() {
      // Show success toast
      const toast = document.createElement('div');
      toast.className = 'toast align-items-center text-white bg-success border-0';
      toast.setAttribute('role', 'alert');
      toast.style.position = 'fixed';
      toast.style.top = '20px';
      toast.style.right = '20px';
      toast.style.zIndex = '9999';
      toast.innerHTML = `
        <div class="d-flex">
          <div class="toast-body">
            <i class="bi bi-check-circle me-2"></i>
            Link evaluasi berhasil disalin!
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
      `;
      
      document.body.appendChild(toast);
      const bsToast = new bootstrap.Toast(toast);
      bsToast.show();
      
      // Remove toast after it's hidden
      toast.addEventListener('hidden.bs.toast', () => {
        document.body.removeChild(toast);
      });
    }).catch(function(err) {
      alert('Gagal menyalin link: ' + err);
    });
  }
  </script>

  <style>
  .question-card {
    transition: all 0.2s ease;
  }
  
  .question-card:hover {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
  }
  
  .question-text {
    font-size: 0.95rem;
    line-height: 1.5;
    color: #333;
  }
  
  .pilihan-preview {
    background-color: #f8f9fa;
    padding: 0.75rem;
    border-radius: 0.375rem;
    border-left: 3px solid #0d6efd;
  }
  
  .pilihan-item {
    display: flex;
    align-items: flex-start;
    margin-bottom: 0.25rem;
  }
  
  .pilihan-label {
    margin-right: 0.5rem;
    color: #0d6efd;
    min-width: 20px;
  }
  
  .skala-preview, .isian-preview {
    background-color: #f8f9fa;
    padding: 0.75rem;
    border-radius: 0.375rem;
    border-left: 3px solid #ffc107;
  }
  
  .question-stat {
    padding: 1rem;
  }
  
  .stat-item {
    text-align: center;
  }
  
  @media (max-width: 768px) {
    .question-text {
      font-size: 0.9rem;
    }
    
    .pilihan-preview {
      padding: 0.5rem;
    }
  }
  </style>
</body>
</html>