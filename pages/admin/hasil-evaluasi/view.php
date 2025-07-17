<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'hasil-evaluasi'; 
$baseURL = '../';

// Validasi parameter
if (!isset($_GET['id_evaluasi']) || !is_numeric($_GET['id_evaluasi'])) {
    $_SESSION['error'] = "ID evaluasi tidak valid.";
    header("Location: index.php");
    exit;
}

$id_evaluasi = (int)$_GET['id_evaluasi'];

// Ambil data evaluasi dengan join ke siswa dan periode
$evaluasiQuery = "SELECT 
                    e.*,
                    s.id_siswa,
                    s.nama as nama_siswa,
                    s.nik,
                    s.pas_foto,
                    k.nama_kelas,
                    pe.id_periode,
                    pe.nama_evaluasi,
                    pe.jenis_evaluasi,
                    pe.materi_terkait,
                    g.nama_gelombang,
                    g.tahun
                  FROM evaluasi e
                  JOIN siswa s ON e.id_siswa = s.id_siswa
                  JOIN kelas k ON e.id_kelas = k.id_kelas
                  JOIN periode_evaluasi pe ON e.id_periode = pe.id_periode
                  LEFT JOIN gelombang g ON pe.id_gelombang = g.id_gelombang
                  WHERE e.id_evaluasi = ?";

$stmt = mysqli_prepare($conn, $evaluasiQuery);
mysqli_stmt_bind_param($stmt, "i", $id_evaluasi);
mysqli_stmt_execute($stmt);
$evaluasiResult = mysqli_stmt_get_result($stmt);

if (!$evaluasiResult || mysqli_num_rows($evaluasiResult) == 0) {
    $_SESSION['error'] = "Data evaluasi tidak ditemukan.";
    header("Location: index.php");
    exit;
}

$evaluasi = mysqli_fetch_assoc($evaluasiResult);

// Ambil semua jawaban evaluasi dengan join ke pertanyaan
$jawabanQuery = "SELECT 
                   je.*,
                   p.pertanyaan,
                   p.aspek_dinilai,
                   p.tipe_jawaban,
                   p.pilihan_jawaban
                 FROM jawaban_evaluasi je
                 JOIN pertanyaan_evaluasi p ON je.id_pertanyaan = p.id_pertanyaan
                 WHERE je.id_evaluasi = ?
                 ORDER BY je.id_jawaban";

$jawabanStmt = mysqli_prepare($conn, $jawabanQuery);
mysqli_stmt_bind_param($jawabanStmt, "i", $id_evaluasi);
mysqli_stmt_execute($jawabanStmt);
$jawabanResult = mysqli_stmt_get_result($jawabanStmt);

// Group jawaban berdasarkan aspek
$jawabanByAspek = [];
$allJawaban = [];
$stats_tipe = [
    'pilihan_ganda' => 0,
    'skala' => 0,
    'isian' => 0
];

while ($jawaban = mysqli_fetch_assoc($jawabanResult)) {
    $allJawaban[] = $jawaban;
    $aspek = $jawaban['aspek_dinilai'];
    if (!isset($jawabanByAspek[$aspek])) {
        $jawabanByAspek[$aspek] = [];
    }
    $jawabanByAspek[$aspek][] = $jawaban;
    
    // Count tipe jawaban
    $stats_tipe[$jawaban['tipe_jawaban']]++;
}

// Hitung statistik
$totalJawaban = count($allJawaban);
$jawabanSkala = 0;
$totalSkala = 0;

foreach ($allJawaban as $jawaban) {
    if ($jawaban['tipe_jawaban'] == 'skala') {
        $jawabanSkala++;
        $totalSkala += (int)$jawaban['jawaban'];
    }
}

$rataRataSkala = $jawabanSkala > 0 ? round($totalSkala / $jawabanSkala, 1) : 0;

// Label materi
$materi_labels = [
    'word' => 'Microsoft Word',
    'excel' => 'Microsoft Excel', 
    'ppt' => 'Microsoft PowerPoint',
    'internet' => 'Internet & Email'
];

// Fungsi untuk format tanggal Indonesia
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

// Helper function untuk mendapatkan label tipe jawaban
function getTipeJawabanLabel($tipe) {
    switch ($tipe) {
        case 'pilihan_ganda':
            return 'Pilihan Ganda';
        case 'skala':
            return 'Rating 1-5';
        case 'isian':
            return 'Isian Bebas';
        default:
            return 'Tidak Diketahui';
    }
}

// Function untuk decode pilihan jawaban
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
  <title>Jawaban Evaluasi - <?= htmlspecialchars($evaluasi['nama_siswa']) ?></title>
  <link rel="icon" type="image/png" href="../../../assets/img/favicon.png"/>
  <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../../assets/css/fonts.css" />
  <link rel="stylesheet" href="../../../assets/css/styles.css" />
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
                <h2 class="page-title mb-1">JAWABAN EVALUASI</h2>
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
                    <li class="breadcrumb-item">
                      <a href="detail.php?id_periode=<?= $evaluasi['id_periode'] ?>">Detail</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Jawaban</li>
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

        <!-- Header Simple -->
        <div class="card content-card mb-4">
          <div class="card-body">
            <div class="row align-items-center">
              <div class="col-auto">
                <?php if($evaluasi['pas_foto'] && file_exists('../../../uploads/pas_foto/'.$evaluasi['pas_foto'])): ?>
                  <img src="../../../uploads/pas_foto/<?= $evaluasi['pas_foto'] ?>" 
                       alt="Foto Siswa" 
                       class="rounded-circle border" 
                       style="width: 80px; height: 80px; object-fit: cover;">
                <?php else: ?>
                  <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center text-white" 
                       style="width: 80px; height: 80px;">
                    <i class="bi bi-person-fill fs-2"></i>
                  </div>
                <?php endif; ?>
              </div>
              <div class="col">
                <h4 class="mb-1 text-dark"><?= htmlspecialchars($evaluasi['nama_siswa']) ?></h4>
                <div class="text-muted mb-2">
                  Kelas <?= htmlspecialchars($evaluasi['nama_kelas']) ?> • 
                  <?= htmlspecialchars($evaluasi['nama_evaluasi']) ?>
                </div>
                <div class="d-flex align-items-center gap-3">
                  <?php if($evaluasi['jenis_evaluasi'] == 'per_materi'): ?>
                    <span class="badge bg-info-subtle text-info">Per Materi</span>
                    <?php if($evaluasi['materi_terkait']): ?>
                      <span class="text-muted"><?= $materi_labels[$evaluasi['materi_terkait']] ?? ucfirst($evaluasi['materi_terkait']) ?></span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="badge bg-primary-subtle text-primary">Akhir Kursus</span>
                  <?php endif; ?>
                  <span class="text-muted">
                    <i class="bi bi-clock me-1"></i>
                    <?= formatTanggalIndonesia($evaluasi['tanggal_evaluasi']) ?>
                  </span>
                </div>
              </div>
              <div class="col-auto">
                <a href="detail.php?id_periode=<?= $evaluasi['id_periode'] ?>" 
                   class="btn btn-kembali px-3">
                  <i class="bi bi-arrow-left me-1"></i>
                  Kembali
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Stats -->
        <?php if($totalJawaban > 0): ?>
        <div class="row mb-4">
          <!-- Statistik Umum -->
          <div class="col-md-8">
            <div class="card content-card">
              <div class="section-header">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-graph-up me-2"></i>Ringkasan Jawaban
                </h5>
              </div>
              <div class="card-body">
                <div class="row text-center">
                  <div class="col-md-4">
                    <div class="stat-item">
                      <div class="fs-3 fw-bold text-primary"><?= $totalJawaban ?></div>
                      <small class="text-muted">Total Jawaban</small>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="stat-item">
                      <div class="fs-3 fw-bold text-warning"><?= $rataRataSkala ?>/5</div>
                      <small class="text-muted">Rata-rata Rating</small>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="stat-item">
                      <div class="fs-3 fw-bold text-success">
                        <?php if($evaluasi['status_evaluasi'] == 'selesai'): ?>
                          <i class="bi bi-check-circle"></i>
                        <?php else: ?>
                          <i class="bi bi-clock"></i>
                        <?php endif; ?>
                      </div>
                      <small class="text-muted">
                        <?= $evaluasi['status_evaluasi'] == 'selesai' ? 'Selesai' : 'Proses' ?>
                      </small>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Komposisi Tipe Jawaban -->
          <div class="col-md-4">
            <div class="card content-card">
              <div class="section-header">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-pie-chart me-2"></i>Tipe Jawaban
                </h5>
              </div>
              <div class="card-body">
                <div class="row g-2 text-center">
                  <div class="col-12">
                    <div class="tipe-stat-small">
                      <i class="bi bi-<?= getTipeJawabanIcon('pilihan_ganda') ?> text-info me-2"></i>
                      <span class="fw-bold"><?= $stats_tipe['pilihan_ganda'] ?></span>
                      <small class="text-muted ms-1">Pilihan Ganda</small>
                    </div>
                  </div>
                  <div class="col-12">
                    <div class="tipe-stat-small">
                      <i class="bi bi-<?= getTipeJawabanIcon('skala') ?> text-warning me-2"></i>
                      <span class="fw-bold"><?= $stats_tipe['skala'] ?></span>
                      <small class="text-muted ms-1">Rating</small>
                    </div>
                  </div>
                  <div class="col-12">
                    <div class="tipe-stat-small">
                      <i class="bi bi-<?= getTipeJawabanIcon('isian') ?> text-secondary me-2"></i>
                      <span class="fw-bold"><?= $stats_tipe['isian'] ?></span>
                      <small class="text-muted ms-1">Isian</small>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Jawaban per Aspek -->
        <?php if (!empty($jawabanByAspek)): ?>
          <?php $aspekCounter = 1; ?>
          <?php foreach ($jawabanByAspek as $aspek => $jawaban_list): ?>
            <div class="card content-card mb-4">
              <div class="section-header">
                <h5 class="mb-0 text-dark">
                  <?= $aspekCounter ?>. <?= htmlspecialchars($aspek) ?>
                </h5>
                <small class="text-muted"><?= count($jawaban_list) ?> pertanyaan</small>
              </div>
              <div class="card-body">
                <?php foreach ($jawaban_list as $index => $jawaban): ?>
                  <div class="question-item <?= $index < count($jawaban_list) - 1 ? 'mb-4 pb-4 border-bottom' : '' ?>">
                    <!-- Pertanyaan -->
                    <div class="mb-3">
                      <div class="d-flex align-items-start">
                        <div class="question-number me-3">
                          <span class="badge bg-light text-dark border" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; font-weight: 600;">
                            <?= $index + 1 ?>
                          </span>
                        </div>
                        <div class="flex-grow-1">
                          <h6 class="mb-2 text-dark">
                            <?= nl2br(htmlspecialchars($jawaban['pertanyaan'])) ?>
                          </h6>
                          <small class="text-muted">
                            <i class="bi bi-<?= getTipeJawabanIcon($jawaban['tipe_jawaban']) ?> me-1"></i>
                            <?= getTipeJawabanLabel($jawaban['tipe_jawaban']) ?>
                          </small>
                        </div>
                      </div>
                    </div>

                    <!-- Jawaban -->
                    <div class="answer-section ms-5">
                      <?php if ($jawaban['tipe_jawaban'] == 'pilihan_ganda'): ?>
                        <!-- Pilihan Ganda -->
                        <?php 
                        $pilihan = getPilihanJawaban($jawaban['pilihan_jawaban']);
                        $jawabanIndex = (int)$jawaban['jawaban'];
                        $jawabanText = isset($pilihan[$jawabanIndex]) ? $pilihan[$jawabanIndex] : 'Jawaban tidak valid';
                        $jawabanLabel = chr(65 + $jawabanIndex); // A, B, C, D
                        ?>
                        <div class="multiple-choice-answer">
                          <div class="fw-bold text-dark mb-3">Jawaban:</div>
                          <div class="answer-choice p-3 bg-light rounded border-start border-info border-4">
                            <div class="d-flex align-items-center">
                              <div class="choice-label me-3">
                                <span class="badge bg-info rounded-circle d-inline-flex align-items-center justify-content-center" 
                                      style="width: 35px; height: 35px; font-size: 1rem; font-weight: 600;">
                                  <?= $jawabanLabel ?>
                                </span>
                              </div>
                              <div class="choice-text">
                                <div class="fw-medium text-dark"><?= htmlspecialchars($jawabanText) ?></div>
                              </div>
                            </div>
                          </div>

                          <!-- Show all options for context -->
                          <?php if (!empty($pilihan)): ?>
                            <div class="mt-3">
                              <small class="text-muted fw-bold">Pilihan yang tersedia:</small>
                              <div class="mt-2">
                                <?php foreach ($pilihan as $idx => $option): ?>
                                  <div class="option-item d-flex align-items-center mb-1 p-2 rounded <?= $idx == $jawabanIndex ? 'bg-info-subtle' : 'bg-light' ?>">
                                    <span class="badge bg-secondary me-2" style="width: 24px; height: 24px; font-size: 0.75rem;">
                                      <?= chr(65 + $idx) ?>
                                    </span>
                                    <small class="<?= $idx == $jawabanIndex ? 'fw-bold text-info' : 'text-muted' ?>">
                                      <?= htmlspecialchars($option) ?>
                                      <?= $idx == $jawabanIndex ? ' ✓' : '' ?>
                                    </small>
                                  </div>
                                <?php endforeach; ?>
                              </div>
                            </div>
                          <?php endif; ?>
                        </div>

                      <?php elseif ($jawaban['tipe_jawaban'] == 'skala'): ?>
                        <!-- Rating Skala -->
                        <div class="scale-answer p-3 bg-light rounded">
                          <div class="d-flex align-items-center">
                            <span class="fw-bold text-dark me-3">Jawaban:</span>
                            <div class="rating-display">
                              <?php 
                              $rating = (int)$jawaban['jawaban'];
                              for($i = 1; $i <= 5; $i++): 
                              ?>
                                <i class="bi bi-star<?= $i <= $rating ? '-fill text-warning' : ' text-muted' ?> me-1" style="font-size: 1.25rem;"></i>
                              <?php endfor; ?>
                              <span class="ms-2 fs-5 fw-bold text-dark"><?= $rating ?>/5</span>
                            </div>
                          </div>
                          <div class="mt-2">
                            <small class="text-muted">
                              <?php 
                              $ratingLabels = [
                                1 => 'Sangat Buruk',
                                2 => 'Buruk', 
                                3 => 'Cukup',
                                4 => 'Baik',
                                5 => 'Sangat Baik'
                              ];
                              echo $ratingLabels[$rating] ?? 'Tidak valid';
                              ?>
                            </small>
                          </div>
                        </div>

                      <?php else: ?>
                        <!-- Isian Bebas -->
                        <div class="text-answer">
                          <div class="fw-bold text-dark mb-2">Jawaban:</div>
                          <div class="answer-box p-3 bg-light rounded border-start border-primary border-4">
                            <div class="text-dark" style="line-height: 1.6; white-space: pre-wrap;"><?= htmlspecialchars($jawaban['jawaban']) ?></div>
                          </div>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php $aspekCounter++; ?>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="card content-card">
            <div class="card-body text-center py-5">
              <i class="bi bi-chat-x display-4 text-muted mb-3 d-block"></i>
              <h5>Belum Ada Jawaban</h5>
              <p class="text-muted">Siswa belum memberikan jawaban untuk evaluasi ini</p>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../../assets/js/scripts.js"></script>

  <script>
  document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    try {
      const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
      });
    } catch (e) {
      console.log('Tooltip initialization skipped');
    }

    // Smooth scroll to anchors
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
          target.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
        }
      });
    });

    // Highlight good/poor ratings
    document.querySelectorAll('.rating-display').forEach(function(element) {
      const ratingText = element.querySelector('span').textContent;
      const rating = parseInt(ratingText.split('/')[0]);
      
      if (rating >= 4) {
        element.closest('.scale-answer').classList.add('border-success');
        element.closest('.scale-answer').style.backgroundColor = '#f8fff9';
      } else if (rating <= 2) {
        element.closest('.scale-answer').classList.add('border-danger');
        element.closest('.scale-answer').style.backgroundColor = '#fff8f8';
      }
    });

    // Add hover effects for multiple choice answers
    document.querySelectorAll('.answer-choice').forEach(function(element) {
      element.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-2px)';
        this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
        this.style.transition = 'all 0.3s ease';
      });
      
      element.addEventListener('mouseleave', function() {
        this.style.transform = '';
        this.style.boxShadow = '';
      });
    });

    // Add hover effects for question items
    document.querySelectorAll('.question-item').forEach(item => {
      item.addEventListener('mouseenter', function() {
        this.style.backgroundColor = '#fafbfc';
        this.style.borderRadius = '8px';
        this.style.padding = '1rem';
        this.style.margin = '-1rem';
        this.style.transition = 'all 0.3s ease';
      });
      
      item.addEventListener('mouseleave', function() {
        this.style.backgroundColor = '';
        this.style.borderRadius = '';
        this.style.padding = '';
        this.style.margin = '';
      });
    });

    // Stats animation on scroll
    const observerOptions = {
      threshold: 0.5,
      rootMargin: '0px 0px -50px 0px'
    };

    const statsObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.style.transform = 'scale(1.05)';
          entry.target.style.transition = 'transform 0.3s ease';
          setTimeout(() => {
            entry.target.style.transform = '';
          }, 300);
        }
      });
    }, observerOptions);

    document.querySelectorAll('.stat-item').forEach(item => {
      statsObserver.observe(item);
    });
  });
  </script>

  <style>
 
  /* Section header styling */
  .section-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    padding: 1rem 1.25rem;
    border-radius: 0.5rem 0.5rem 0 0;
  }

  .section-header h5 {
    margin-bottom: 0;
    font-weight: 600;
  }

  /* Question item styling */
  .question-item {
    position: relative;
  }

  .question-number .badge {
    font-size: 0.875rem;
    font-weight: 600;
  }

  /* Answer styling */
  .answer-section {
    margin-left: 1rem;
  }

  /* Multiple choice specific styling */
  .multiple-choice-answer .choice-label .badge {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  }

  .option-item {
    transition: all 0.2s ease;
  }

  .option-item:hover {
    background-color: #e9ecef !important;
  }

  .bg-info-subtle {
    background-color: rgba(13, 202, 240, 0.1) !important;
  }

  /* Scale answer styling */
  .scale-answer {
    transition: all 0.3s ease;
  }

  .rating-display {
    display: flex;
    align-items: center;
  }

  /* Text answer styling */
  .answer-box {
    transition: all 0.3s ease;
    min-height: 50px;
    display: flex;
    align-items: center;
  }

  .answer-box:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  }

  /* Star rating enhancements */
  .rating-display i {
    transition: transform 0.2s ease;
  }

  .rating-display:hover i {
    transform: scale(1.1);
  }

  /* Statistics styling */
  .stat-item {
    position: relative;
    transition: transform 0.3s ease;
  }

  .stat-item:hover {
    transform: translateY(-2px);
  }

  .stat-item:not(:last-child)::after {
    content: '';
    position: absolute;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
    height: 50px;
    width: 1px;
    background-color: #dee2e6;
  }

  /* Tipe stats styling */
  .tipe-stat-small {
    display: flex;
    align-items: center;
    padding: 0.5rem;
    border-radius: 0.25rem;
    transition: background-color 0.2s ease;
  }

  .tipe-stat-small:hover {
    background-color: #f8f9fa;
  }

  /* Responsive adjustments */
  @media (max-width: 768px) {
    .answer-section {
      margin-left: 0;
      margin-top: 1rem;
    }
    
    .question-item .d-flex {
      flex-direction: column;
    }
    
    .question-number {
      margin-bottom: 0.5rem;
      margin-right: 0 !important;
      align-self: flex-start;
    }
    
    .rating-display {
      flex-wrap: wrap;
      gap: 0.25rem;
    }

    .stat-item:not(:last-child)::after {
      display: none;
    }

    .choice-label .badge {
      width: 30px !important;
      height: 30px !important;
      font-size: 0.9rem !important;
    }
  }

  /* Badge styling consistent with theme */
  .badge.bg-info-subtle {
    background-color: rgba(13, 202, 240, 0.1) !important;
    color: #0dcaf0 !important;
  }

  .badge.bg-primary-subtle {
    background-color: rgba(13, 110, 253, 0.1) !important;
    color: #0d6efd !important;
  }

  /* Photo styling */
  .rounded-circle {
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
  }

  .rounded-circle:hover {
    border-color: #0d6efd;
    transform: scale(1.05);
  }

  /* Empty state styling */
  .empty-state {
    padding: 3rem 1rem;
  }

  .empty-state i {
    opacity: 0.5;
  }

  .empty-state h5 {
    color: #6c757d;
    margin-bottom: 1rem;
  }

  /* Clean borders */
  .border-success {
    border-color: #28a745 !important;
  }

  .border-danger {
    border-color: #dc3545 !important;
  }

  .border-info {
    border-color: #17a2b8 !important;
  }

  /* Multiple choice answer styling */
  .answer-choice {
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
  }

  .answer-choice:hover {
    border-color: #17a2b8;
  }

  .choice-text {
    flex-grow: 1;
    line-height: 1.4;
  }

  /* Animation for page load */
  @keyframes fadeInUp {
    from {
      opacity: 0;
      transform: translateY(20px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .question-item {
    animation: fadeInUp 0.6s ease forwards;
  }

  .question-item:nth-child(1) { animation-delay: 0.1s; }
  .question-item:nth-child(2) { animation-delay: 0.2s; }
  .question-item:nth-child(3) { animation-delay: 0.3s; }
  .question-item:nth-child(4) { animation-delay: 0.4s; }
  .question-item:nth-child(5) { animation-delay: 0.5s; }

  /* Print styles */
  @media print {
    .btn, .top-navbar, .sidebar {
      display: none !important;
    }
    
    .main-content {
      margin-left: 0 !important;
    }
    
    .content-card {
      box-shadow: none !important;
      border: 1px solid #dee2e6 !important;
    }
    
    .answer-choice, .scale-answer, .answer-box {
      background-color: #f8f9fa !important;
      -webkit-print-color-adjust: exact;
    }
  }

  /* Accessibility improvements */
  .badge:focus,
  .answer-choice:focus-within {
    outline: 2px solid #0d6efd;
    outline-offset: 2px;
  }

  /* High contrast mode support */
  @media (prefers-contrast: high) {
    .content-card {
      border: 2px solid #000 !important;
    }
    
    .section-header {
      background-color: #000 !important;
      color: #fff !important;
    }
    
    .badge {
      border: 1px solid #000 !important;
    }
  }

  /* Reduced motion support */
  @media (prefers-reduced-motion: reduce) {
    .question-item {
      animation: none;
    }
    
    .content-card,
    .answer-choice,
    .scale-answer,
    .answer-box,
    .stat-item {
      transition: none;
    }
  }
  </style>
</body>
</html>