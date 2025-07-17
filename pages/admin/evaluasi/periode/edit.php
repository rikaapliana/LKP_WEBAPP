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

// Ambil data periode yang akan diedit
$periodeQuery = "SELECT pe.*, g.nama_gelombang, g.tahun, g.gelombang_ke,
                        (SELECT COUNT(*) FROM evaluasi e WHERE e.id_periode = pe.id_periode) as total_responden
                 FROM periode_evaluasi pe 
                 LEFT JOIN gelombang g ON pe.id_gelombang = g.id_gelombang 
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

// Cek apakah periode bisa diedit
$canEdit = ($periode['status'] != 'selesai');
$hasResponden = ($periode['total_responden'] > 0);

// Parse existing selected questions
$selectedQuestionsFromDB = [];
if (!empty($periode['pertanyaan_terpilih'])) {
    $decoded = json_decode($periode['pertanyaan_terpilih'], true);
    if (is_array($decoded)) {
        $selectedQuestionsFromDB = array_map('intval', $decoded);
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!$canEdit) {
        $_SESSION['error'] = "Periode yang sudah selesai tidak dapat diedit.";
        header("Location: detail.php?id=$id_periode");
        exit;
    }
    
    // Sanitize and validate input
    $nama_evaluasi = trim($_POST['nama_evaluasi']);
    $jenis_evaluasi = $_POST['jenis_evaluasi']; 
    $id_gelombang = (int)$_POST['id_gelombang'];
    $tanggal_buka = $_POST['tanggal_buka'];
    $tanggal_tutup = $_POST['tanggal_tutup'];
    $status = $_POST['status'] ?? 'draft';
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $materi_terkait = null;
    $pertanyaan_terpilih = null;
    
    // Conditional field: materi_terkait hanya untuk jenis_evaluasi = 'per_materi'
    if ($jenis_evaluasi === 'per_materi' && !empty($_POST['materi_terkait'])) {
        $materi_terkait = $_POST['materi_terkait'];
    }
    
    // Handle pertanyaan_terpilih
    if (!empty($_POST['pertanyaan_terpilih']) && is_array($_POST['pertanyaan_terpilih'])) {
        $selected_questions = array_map('intval', $_POST['pertanyaan_terpilih']);
        $pertanyaan_terpilih = json_encode($selected_questions);
    } else {
        $pertanyaan_terpilih = json_encode([]);
    }
    
    // Validasi input
    $errors = [];
    
    if (empty($nama_evaluasi)) {
        $errors[] = "Nama evaluasi tidak boleh kosong.";
    } elseif (strlen($nama_evaluasi) < 5) {
        $errors[] = "Nama evaluasi minimal 5 karakter.";
    }
    
    if (!in_array($jenis_evaluasi, ['per_materi', 'akhir_kursus'])) {
        $errors[] = "Jenis evaluasi tidak valid.";
    }
    
    if (empty($id_gelombang)) {
        $errors[] = "Gelombang tidak boleh kosong.";
    }
    
    if (empty($tanggal_buka)) {
        $errors[] = "Tanggal buka tidak boleh kosong.";
    }
    
    if (empty($tanggal_tutup)) {
        $errors[] = "Tanggal tutup tidak boleh kosong.";
    }
    
    if (!empty($tanggal_buka) && !empty($tanggal_tutup)) {
        if (strtotime($tanggal_tutup) <= strtotime($tanggal_buka)) {
            $errors[] = "Tanggal tutup harus setelah tanggal buka.";
        }
    }
    
    // Validasi khusus untuk per_materi
    if ($jenis_evaluasi === 'per_materi') {
        if (empty($materi_terkait)) {
            $errors[] = "Materi terkait wajib diisi untuk evaluasi per materi.";
        } elseif (!in_array($materi_terkait, ['word', 'excel', 'ppt', 'internet'])) {
            $errors[] = "Materi terkait tidak valid.";
        }
    }
    
    if (!in_array($status, ['draft', 'aktif', 'selesai'])) {
        $errors[] = "Status tidak valid.";
    }
    
    // Validasi pertanyaan terpilih
    if (empty($_POST['pertanyaan_terpilih']) || !is_array($_POST['pertanyaan_terpilih'])) {
        $errors[] = "Minimal 1 pertanyaan harus dipilih untuk periode evaluasi.";
    } elseif (count($_POST['pertanyaan_terpilih']) > 50) {
        $errors[] = "Maksimal 50 pertanyaan dapat dipilih.";
    }
    
    // Check for duplicate periode (exclude current periode)
    $checkQuery = "SELECT id_periode FROM periode_evaluasi WHERE id_gelombang = ? AND jenis_evaluasi = ? AND id_periode != ?";
    if ($jenis_evaluasi === 'per_materi' && $materi_terkait) {
        $checkQuery .= " AND materi_terkait = ?";
        $checkStmt = mysqli_prepare($conn, $checkQuery);
        mysqli_stmt_bind_param($checkStmt, "isis", $id_gelombang, $jenis_evaluasi, $id_periode, $materi_terkait);
    } else {
        $checkStmt = mysqli_prepare($conn, $checkQuery);
        mysqli_stmt_bind_param($checkStmt, "isi", $id_gelombang, $jenis_evaluasi, $id_periode);
    }
    
    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);
    
    if (mysqli_num_rows($checkResult) > 0) {
        if ($jenis_evaluasi === 'per_materi') {
            $errors[] = "Periode evaluasi untuk gelombang dan materi ini sudah ada.";
        } else {
            $errors[] = "Periode evaluasi akhir kursus untuk gelombang ini sudah ada.";
        }
    }
    
    // Validasi khusus jika ada responden
    if ($hasResponden) {
        // Tidak boleh ubah jenis evaluasi dan materi jika sudah ada responden
        if ($jenis_evaluasi !== $periode['jenis_evaluasi']) {
            $errors[] = "Tidak dapat mengubah jenis evaluasi karena sudah ada {$periode['total_responden']} responden.";
        }
        
        if ($jenis_evaluasi === 'per_materi' && $materi_terkait !== $periode['materi_terkait']) {
            $errors[] = "Tidak dapat mengubah materi terkait karena sudah ada {$periode['total_responden']} responden.";
        }
        
        if ($id_gelombang != $periode['id_gelombang']) {
            $errors[] = "Tidak dapat mengubah gelombang karena sudah ada {$periode['total_responden']} responden.";
        }
    }
    
    if (empty($errors)) {
        // Update dengan prepared statement including pertanyaan_terpilih
        $updateQuery = "UPDATE periode_evaluasi SET 
                        nama_evaluasi = ?, jenis_evaluasi = ?, materi_terkait = ?, 
                        id_gelombang = ?, tanggal_buka = ?, tanggal_tutup = ?, 
                        status = ?, deskripsi = ?, pertanyaan_terpilih = ?
                        WHERE id_periode = ?";
        
        $updateStmt = mysqli_prepare($conn, $updateQuery);
        mysqli_stmt_bind_param($updateStmt, "sssisssssi", $nama_evaluasi, $jenis_evaluasi, $materi_terkait, $id_gelombang, $tanggal_buka, $tanggal_tutup, $status, $deskripsi, $pertanyaan_terpilih, $id_periode);
        
        if (mysqli_stmt_execute($updateStmt)) {
            $total_questions = count($_POST['pertanyaan_terpilih']);
            $_SESSION['success'] = "Periode evaluasi berhasil diperbarui dengan $total_questions pertanyaan!";
            header("Location: detail.php?id=$id_periode");
            exit;
        } else {
            $error = "Gagal memperbarui periode: " . mysqli_error($conn);
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

// Data untuk dropdown
$gelombangQuery = "SELECT id_gelombang, nama_gelombang, tahun, gelombang_ke, status FROM gelombang WHERE status IN ('aktif', 'dibuka') ORDER BY tahun DESC, gelombang_ke DESC";
$gelombangResult = mysqli_query($conn, $gelombangQuery);

// Count bank soal yang tersedia
$bankSoalQuery = "SELECT 
                    COUNT(CASE WHEN jenis_evaluasi = 'per_materi' THEN 1 END) as per_materi,
                    COUNT(CASE WHEN jenis_evaluasi = 'akhir_kursus' THEN 1 END) as akhir_kursus,
                    COUNT(CASE WHEN jenis_evaluasi = 'per_materi' AND materi_terkait = 'word' THEN 1 END) as word,
                    COUNT(CASE WHEN jenis_evaluasi = 'per_materi' AND materi_terkait = 'excel' THEN 1 END) as excel,
                    COUNT(CASE WHEN jenis_evaluasi = 'per_materi' AND materi_terkait = 'ppt' THEN 1 END) as ppt,
                    COUNT(CASE WHEN jenis_evaluasi = 'per_materi' AND materi_terkait = 'internet' THEN 1 END) as internet
                  FROM pertanyaan_evaluasi WHERE is_active = 1";
$bankSoalResult = mysqli_query($conn, $bankSoalQuery);
$bankSoal = mysqli_fetch_assoc($bankSoalResult);

// Format tanggal untuk input datetime-local
$periode['tanggal_buka_formatted'] = date('Y-m-d\TH:i', strtotime($periode['tanggal_buka']));
$periode['tanggal_tutup_formatted'] = date('Y-m-d\TH:i', strtotime($periode['tanggal_tutup']));
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Periode Evaluasi</title>
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
              <h2 class="page-title mb-1">EDIT PERIODE EVALUASI</h2>
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
                  <li class="breadcrumb-item">
                    <a href="detail.php?id=<?= $id_periode ?>">Detail</a>
                  </li>
                  <li class="breadcrumb-item active" aria-current="page">Edit</li>
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
      <!-- Alert Error -->
      <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <i class="bi bi-exclamation-triangle me-2"></i>
          <?= $error ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <!-- Warning untuk periode yang tidak bisa diedit -->
      <?php if (!$canEdit): ?>
        <div class="alert alert-warning">
          <h6><i class="bi bi-exclamation-triangle me-2"></i>Periode Tidak Dapat Diedit</h6>
          <p class="mb-0">Periode evaluasi yang sudah selesai tidak dapat diedit. Anda hanya dapat melihat detail periode ini.</p>
        </div>
      <?php endif; ?>

      <!-- Warning untuk periode yang sudah ada responden -->
      <?php if ($hasResponden && $canEdit): ?>
        <div class="alert alert-info">
          <h6><i class="bi bi-info-circle me-2"></i>Perhatian: Ada Responden</h6>
          <p class="mb-0">
            Periode ini sudah memiliki <strong><?= $periode['total_responden'] ?> responden</strong>. 
            Beberapa field tidak dapat diubah (jenis evaluasi, materi, gelombang) untuk menjaga konsistensi data.
          </p>
        </div>
      <?php endif; ?>

      <!-- Info Bank Soal -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="alert alert-info">
            <div class="row align-items-center">
              <div class="col-md-8">
                <h6 class="mb-1"><i class="bi bi-info-circle me-2"></i>Bank Soal Tersedia</h6>
                <small>
                  <strong>Per Materi:</strong> <?= $bankSoal['per_materi'] ?> soal 
                  (Word: <?= $bankSoal['word'] ?>, Excel: <?= $bankSoal['excel'] ?>, PPT: <?= $bankSoal['ppt'] ?>, Internet: <?= $bankSoal['internet'] ?>) |
                  <strong>Akhir Kursus:</strong> <?= $bankSoal['akhir_kursus'] ?> soal
                </small>
              </div>
              <div class="col-md-4 text-md-end">
                <a href="detail.php?id=<?= $id_periode ?>" class="btn btn-sm btn-outline-primary">
                  <i class="bi bi-eye me-1"></i>Lihat Detail
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Form Card -->
      <div class="card content-card">
        <div class="section-header">
          <h5 class="mb-0 text-dark">
            <i class="bi bi-pencil-square me-2"></i>Form Edit Periode Evaluasi
          </h5>
        </div>

        <div class="card-body">
          <?php if ($canEdit): ?>
          <form action="" method="post" id="formEditPeriode">
            
            <!-- STEP 1: Konfigurasi Periode -->
            <div class="row mb-5">
              <div class="col-lg-6">
                <h6 class="section-title mb-4">
                  <i class="bi bi-gear me-2"></i>Konfigurasi Periode
                </h6>
                
                <div class="mb-4">
                  <label class="form-label required">Nama Evaluasi</label>
                  <input type="text" name="nama_evaluasi" id="namaEvaluasi" class="form-control" required
                         value="<?= htmlspecialchars($periode['nama_evaluasi']) ?>"
                         placeholder="Misal: Evaluasi Materi Word - Gelombang 1">
                  <div class="form-text">
                    <small>Nama yang mudah dikenali untuk periode evaluasi ini</small>
                    <div class="d-flex justify-content-between mt-1">
                      <span id="namaCharCount" class="text-muted">0 karakter</span>
                      <span class="text-muted">Min 5 karakter</span>
                    </div>
                  </div>
                </div>

                <div class="mb-4">
                  <label class="form-label required">Jenis Evaluasi</label>
                  <select name="jenis_evaluasi" id="jenisEvaluasi" class="form-select" required 
                          <?= $hasResponden ? 'disabled' : '' ?>>
                    <option value="">Pilih Jenis Evaluasi</option>
                    <option value="per_materi" <?= $periode['jenis_evaluasi'] == 'per_materi' ? 'selected' : '' ?>>
                      Evaluasi Per Materi
                    </option>
                    <option value="akhir_kursus" <?= $periode['jenis_evaluasi'] == 'akhir_kursus' ? 'selected' : '' ?>>
                      Evaluasi Akhir Kursus
                    </option>
                  </select>
                  <?php if ($hasResponden): ?>
                    <input type="hidden" name="jenis_evaluasi" value="<?= $periode['jenis_evaluasi'] ?>">
                    <div class="form-text"><small class="text-warning">Tidak dapat diubah karena sudah ada responden</small></div>
                  <?php else: ?>
                    <div class="form-text" id="jenisHelp">
                      <small>Pilih jenis untuk melihat bank soal yang tersedia</small>
                    </div>
                  <?php endif; ?>
                </div>

                <!-- Conditional Field: Materi Terkait -->
                <div class="mb-4" id="materiTerkaitWrapper" style="<?= $periode['jenis_evaluasi'] == 'per_materi' ? 'display: block;' : 'display: none;' ?>">
                  <label class="form-label required">Materi Terkait</label>
                  <select name="materi_terkait" id="materiTerkait" class="form-select"
                          <?= $hasResponden ? 'disabled' : '' ?>>
                    <option value="">Pilih Materi</option>
                    <option value="word" <?= $periode['materi_terkait'] == 'word' ? 'selected' : '' ?>>
                      Microsoft Word (<?= $bankSoal['word'] ?> soal)
                    </option>
                    <option value="excel" <?= $periode['materi_terkait'] == 'excel' ? 'selected' : '' ?>>
                      Microsoft Excel (<?= $bankSoal['excel'] ?> soal)
                    </option>
                    <option value="ppt" <?= $periode['materi_terkait'] == 'ppt' ? 'selected' : '' ?>>
                      Microsoft PowerPoint (<?= $bankSoal['ppt'] ?> soal)
                    </option>
                    <option value="internet" <?= $periode['materi_terkait'] == 'internet' ? 'selected' : '' ?>>
                      Internet & Email (<?= $bankSoal['internet'] ?> soal)
                    </option>
                  </select>
                  <?php if ($hasResponden): ?>
                    <input type="hidden" name="materi_terkait" value="<?= $periode['materi_terkait'] ?>">
                    <div class="form-text"><small class="text-warning">Tidak dapat diubah karena sudah ada responden</small></div>
                  <?php else: ?>
                    <div class="form-text"><small>Pilih materi yang akan dievaluasi</small></div>
                  <?php endif; ?>
                </div>

                <div class="mb-4">
                  <label class="form-label required">Gelombang</label>
                  <select name="id_gelombang" id="idGelombang" class="form-select" required
                          <?= $hasResponden ? 'disabled' : '' ?>>
                    <option value="">Pilih Gelombang</option>
                    <!-- Include current gelombang even if not in active list -->
                    <option value="<?= $periode['id_gelombang'] ?>" selected>
                      <?= htmlspecialchars($periode['nama_gelombang']) ?> (<?= $periode['tahun'] ?>) - Current
                    </option>
                    <?php if ($gelombangResult && mysqli_num_rows($gelombangResult) > 0): ?>
                      <?php while($gelombang = mysqli_fetch_assoc($gelombangResult)): ?>
                        <?php if ($gelombang['id_gelombang'] != $periode['id_gelombang']): ?>
                          <option value="<?= $gelombang['id_gelombang'] ?>" 
                                  data-tahun="<?= $gelombang['tahun'] ?>"
                                  data-status="<?= $gelombang['status'] ?>">
                            <?= htmlspecialchars($gelombang['nama_gelombang']) ?> (<?= $gelombang['tahun'] ?>) - <?= ucfirst($gelombang['status']) ?>
                          </option>
                        <?php endif; ?>
                      <?php endwhile; ?>
                    <?php endif; ?>
                  </select>
                  <?php if ($hasResponden): ?>
                    <input type="hidden" name="id_gelombang" value="<?= $periode['id_gelombang'] ?>">
                    <div class="form-text"><small class="text-warning">Tidak dapat diubah karena sudah ada responden</small></div>
                  <?php else: ?>
                    <div class="form-text">
                      <small>Gelombang yang akan mengikuti evaluasi ini</small>
                    </div>
                  <?php endif; ?>
                </div>

                <div class="mb-4">
                  <label class="form-label">Deskripsi</label>
                  <textarea name="deskripsi" id="deskripsi" class="form-control" rows="3"
                            placeholder="Deskripsi tambahan untuk periode evaluasi (opsional)"><?= htmlspecialchars($periode['deskripsi']) ?></textarea>
                  <div class="form-text">
                    <small>Informasi tambahan yang akan membantu siswa memahami evaluasi</small>
                  </div>
                </div>
              </div>

              <!-- Periode Waktu & Status Section -->
              <div class="col-lg-6">
                <h6 class="section-title mb-4">
                  <i class="bi bi-calendar-week me-2"></i>Periode Waktu & Status
                </h6>

                <div class="mb-4">
                  <label class="form-label required">Tanggal & Waktu Buka</label>
                  <input type="datetime-local" name="tanggal_buka" id="tanggalBuka" class="form-control" required
                         value="<?= $periode['tanggal_buka_formatted'] ?>">
                  <div class="form-text">
                    <small>Kapan siswa dapat mulai mengisi evaluasi</small>
                  </div>
                </div>

                <div class="mb-4">
                  <label class="form-label required">Tanggal & Waktu Tutup</label>
                  <input type="datetime-local" name="tanggal_tutup" id="tanggalTutup" class="form-control" required
                         value="<?= $periode['tanggal_tutup_formatted'] ?>">
                  <div class="form-text">
                    <small>Kapan periode evaluasi berakhir</small>
                  </div>
                </div>

                <div class="mb-4">
                  <label class="form-label required">Status</label>
                  <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="status" id="statusDraft" value="draft" 
                           <?= $periode['status'] == 'draft' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="statusDraft">
                      <strong>Draft</strong>
                      <div class="form-text"><small>Belum aktif, masih bisa diedit</small></div>
                    </label>
                  </div>
                  <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="status" id="statusAktif" value="aktif"
                           <?= $periode['status'] == 'aktif' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="statusAktif">
                      <strong>Aktif</strong>
                      <div class="form-text"><small>Siswa dapat mengisi evaluasi</small></div>
                    </label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="status" id="statusSelesai" value="selesai"
                           <?= $periode['status'] == 'selesai' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="statusSelesai">
                      <strong>Selesai</strong>
                      <div class="form-text"><small>Periode berakhir, tidak dapat diedit</small></div>
                    </label>
                  </div>
                </div>

                <!-- Quick Actions -->
                <div class="mb-4">
                  <label class="form-label">Quick Actions</label>
                  <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="extendPeriode7">
                      <i class="bi bi-calendar-plus me-1"></i>Perpanjang 7 Hari
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="extendPeriode14">
                      <i class="bi bi-calendar2-plus me-1"></i>Perpanjang 14 Hari
                    </button>
                    <button type="button" class="btn btn-outline-warning btn-sm" id="setSelesaiSekarang">
                      <i class="bi bi-stop-circle me-1"></i>Selesaikan Sekarang
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- STEP 2: PILIH PERTANYAAN (NEW SECTION!) -->
            <div class="row mb-5">
              <div class="col-12">
                <div class="border-top pt-4">
                  <h6 class="section-title mb-4">
                    <i class="bi bi-list-check me-2"></i>Pertanyaan Evaluasi
                    <span class="badge bg-primary ms-2" id="selectedCount">0 dipilih</span>
                  </h6>
                  
                  <!-- Filter & Search Pertanyaan -->
                  <div class="row mb-4">
                    <div class="col-md-6">
                      <div class="card border">
                        <div class="card-body py-3">
                          <div class="d-flex align-items-center gap-3">
                            <div class="flex-grow-1">
                              <input type="text" id="searchPertanyaan" class="form-control form-control-sm" 
                                     placeholder="Cari pertanyaan..." />
                            </div>
                            <div>
                              <button type="button" class="btn btn-outline-primary btn-sm" id="selectAllBtn">
                                <i class="bi bi-check-all me-1"></i>Pilih Semua
                              </button>
                            </div>
                            <div>
                              <button type="button" class="btn btn-outline-secondary btn-sm" id="clearAllBtn">
                                <i class="bi bi-x-circle me-1"></i>Bersihkan
                              </button>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="alert alert-info mb-0 py-2">
                        <small>
                          <i class="bi bi-info-circle me-1"></i>
                          <span id="questionInfo">Loading pertanyaan yang sudah dipilih...</span>
                        </small>
                      </div>
                    </div>
                  </div>

                  <!-- List Pertanyaan -->
                  <div id="pertanyaanContainer">
                    <div class="text-center py-4">
                      <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                      </div>
                      <div class="mt-2">Memuat pertanyaan...</div>
                    </div>
                  </div>

                  <!-- Preview Pertanyaan Terpilih -->
                  <div id="selectedPreview" style="display: none;">
                    <h6 class="mt-4 mb-3">
                      <i class="bi bi-eye me-2"></i>Preview Pertanyaan Terpilih
                    </h6>
                    <div id="selectedList" class="border rounded p-3 bg-light"></div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="row mt-5 pt-4 border-top">
              <div class="col-12">
                <div class="d-flex justify-content-end gap-3">
                  <a href="detail.php?id=<?= $id_periode ?>" class="btn btn-kembali px-3">
                   Kembali
                  </a>
                  <button type="submit" class="btn btn-simpan px-4">
                    <i class="bi bi-check-lg me-1"></i>Simpan Perubahan
                  </button>
                </div>
              </div>
            </div>
          </form>
          
          <?php else: ?>
          <!-- Read-only view untuk periode yang tidak bisa diedit -->
          <!-- ... READ ONLY VERSION (same as before) ... -->
          <div class="alert alert-warning">
            <h6><i class="bi bi-lock me-2"></i>Mode Read-Only</h6>
            <p class="mb-0">Periode evaluasi yang sudah selesai tidak dapat diedit.</p>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Scripts -->
<script src="../../../../assets/js/bootstrap.bundle.min.js"></script>
<script src="../../../../assets/js/scripts.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const canEdit = <?= $canEdit ? 'true' : 'false' ?>;
  const hasResponden = <?= $hasResponden ? 'true' : 'false' ?>;
  const selectedQuestionsFromDB = <?= json_encode($selectedQuestionsFromDB) ?>;
  
  if (!canEdit) {
    return; // Skip script untuk mode read-only
  }
  
  const form = document.getElementById('formEditPeriode');
  const namaEvaluasi = document.getElementById('namaEvaluasi');
  const jenisEvaluasi = document.getElementById('jenisEvaluasi');
  const materiTerkaitWrapper = document.getElementById('materiTerkaitWrapper');
  const materiTerkait = document.getElementById('materiTerkait');
  const idGelombang = document.getElementById('idGelombang');
  const tanggalBuka = document.getElementById('tanggalBuka');
  const tanggalTutup = document.getElementById('tanggalTutup');
  const namaCharCount = document.getElementById('namaCharCount');
  const jenisHelp = document.getElementById('jenisHelp');
  
  // Question selection elements
  const pertanyaanContainer = document.getElementById('pertanyaanContainer');
  const searchPertanyaan = document.getElementById('searchPertanyaan');
  const selectAllBtn = document.getElementById('selectAllBtn');
  const clearAllBtn = document.getElementById('clearAllBtn');
  const selectedCount = document.getElementById('selectedCount');
  const selectedPreview = document.getElementById('selectedPreview');
  const selectedList = document.getElementById('selectedList');
  const questionInfo = document.getElementById('questionInfo');

  const bankSoal = <?= json_encode($bankSoal) ?>;
  let availableQuestions = [];
  let filteredQuestions = [];
  let selectedQuestions = [...selectedQuestionsFromDB]; // Initialize with existing selection

  // Conditional field: Show/hide materi terkait
  function toggleMateriTerkait() {
    if (jenisEvaluasi.value === 'per_materi') {
      materiTerkaitWrapper.style.display = 'block';
      if (!hasResponden) materiTerkait.required = true;
      materiTerkaitWrapper.classList.add('show-animation');
      if (jenisHelp) jenisHelp.innerHTML = `<small><i class="bi bi-info-circle me-1"></i>Bank soal per materi: ${bankSoal.per_materi} soal tersedia</small>`;
    } else if (jenisEvaluasi.value === 'akhir_kursus') {
      materiTerkaitWrapper.style.display = 'none';
      materiTerkait.required = false;
      if (!hasResponden) materiTerkait.value = '';
      materiTerkaitWrapper.classList.remove('show-animation');
      if (jenisHelp) jenisHelp.innerHTML = `<small><i class="bi bi-info-circle me-1"></i>Bank soal akhir kursus: ${bankSoal.akhir_kursus} soal tersedia</small>`;
    } else {
      materiTerkaitWrapper.style.display = 'none';
      materiTerkait.required = false;
      if (jenisHelp) jenisHelp.innerHTML = '<small>Pilih jenis untuk melihat bank soal yang tersedia</small>';
    }
    loadQuestions(); // Load questions when jenis changes
  }

  // Character counter
  function updateCharCount() {
    const count = namaEvaluasi.value.length;
    namaCharCount.textContent = count + ' karakter';
    
    if (count < 5 && count > 0) {
      namaCharCount.classList.add('text-warning');
      namaCharCount.classList.remove('text-success');
    } else if (count >= 5) {
      namaCharCount.classList.add('text-success');
      namaCharCount.classList.remove('text-warning');
    } else {
      namaCharCount.classList.remove('text-warning', 'text-success');
    }
  }

  // Load questions from database
  async function loadQuestions() {
    const jenis = jenisEvaluasi.value;
    const materi = materiTerkait.value;
    
    if (!jenis) {
      pertanyaanContainer.innerHTML = `
        <div class="text-center py-5 text-muted">
          <i class="bi bi-list-ul display-4 mb-3 d-block"></i>
          <h6>Pilih Jenis Evaluasi Terlebih Dahulu</h6>
          <p class="mb-0">Pertanyaan akan muncul setelah Anda memilih jenis evaluasi dan materi (jika diperlukan)</p>
        </div>
      `;
      questionInfo.textContent = 'Pilih jenis evaluasi untuk melihat pertanyaan yang tersedia';
      return;
    }

    if (jenis === 'per_materi' && !materi) {
      pertanyaanContainer.innerHTML = `
        <div class="text-center py-5 text-muted">
          <i class="bi bi-book display-4 mb-3 d-block"></i>
          <h6>Pilih Materi Terkait</h6>
          <p class="mb-0">Pilih materi yang akan dievaluasi untuk melihat pertanyaan yang tersedia</p>
        </div>
      `;
      questionInfo.textContent = 'Pilih materi terkait untuk melihat pertanyaan yang tersedia';
      return;
    }

    // Show loading
    pertanyaanContainer.innerHTML = `
      <div class="text-center py-4">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <div class="mt-2">Memuat pertanyaan...</div>
      </div>
    `;

    try {
      // Fetch questions from database
      const formData = new FormData();
      formData.append('action', 'get_questions');
      formData.append('jenis_evaluasi', jenis);
      if (materi) formData.append('materi_terkait', materi);

      const response = await fetch('ajax_get_questions.php', {
        method: 'POST',
        body: formData
      });

      const data = await response.json();
      
      if (data.success) {
        availableQuestions = data.questions;
        filteredQuestions = [...availableQuestions];
        
        questionInfo.innerHTML = `
          <i class="bi bi-check-circle me-1 text-success"></i>
          Ditemukan ${availableQuestions.length} pertanyaan untuk ${jenis === 'per_materi' ? `materi ${materi.toUpperCase()}` : 'akhir kursus'}
        `;
        
        renderQuestions();
        updateSelectionUI(); // Update UI with existing selection
      } else {
        throw new Error(data.message || 'Error loading questions');
      }
    } catch (error) {
      pertanyaanContainer.innerHTML = `
        <div class="alert alert-danger">
          <i class="bi bi-exclamation-triangle me-2"></i>
          Error: ${error.message}
        </div>
      `;
      questionInfo.textContent = 'Error memuat pertanyaan';
    }
  }

  // Render questions list (SAME AS TAMBAH.PHP)
  function renderQuestions() {
    if (filteredQuestions.length === 0) {
      pertanyaanContainer.innerHTML = `
        <div class="text-center py-4 text-muted">
          <i class="bi bi-search display-4 mb-3 d-block"></i>
          <h6>Tidak Ada Pertanyaan Ditemukan</h6>
          <p class="mb-0">Coba ubah kata kunci pencarian</p>
        </div>
      `;
      return;
    }

    let html = '<div class="row g-3">';
    
    filteredQuestions.forEach((question, index) => {
      const isSelected = selectedQuestions.includes(question.id_pertanyaan);
      const tipeIcon = {
        'pilihan_ganda': 'bi-check2-square',
        'skala': 'bi-star',
        'isian': 'bi-pencil'
      };
      
      const tipeBadge = {
        'pilihan_ganda': 'bg-info',
        'skala': 'bg-warning text-dark',
        'isian': 'bg-primary'
      };

      html += `
        <div class="col-12">
          <div class="card question-card ${isSelected ? 'selected' : ''}" data-question-id="${question.id_pertanyaan}">
            <div class="card-body p-3">
              <div class="d-flex align-items-start">
                <div class="form-check me-3">
                  <input class="form-check-input question-checkbox" type="checkbox" 
                         value="${question.id_pertanyaan}" name="pertanyaan_terpilih[]" 
                         id="q${question.id_pertanyaan}" ${isSelected ? 'checked' : ''}>
                </div>
                <div class="flex-grow-1">
                  <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="question-meta">
                      <span class="badge ${tipeBadge[question.tipe_jawaban]} me-2">
                        <i class="${tipeIcon[question.tipe_jawaban]} me-1"></i>
                        ${question.tipe_jawaban.replace('_', ' ').toUpperCase()}
                      </span>
                      <span class="badge bg-light text-dark">
                        ${question.aspek_dinilai}
                      </span>
                    </div>
                    <small class="text-muted">#${question.id_pertanyaan}</small>
                  </div>
                  
                  <div class="question-text mb-2">
                    ${question.pertanyaan}
                  </div>
                  
                  ${question.tipe_jawaban === 'pilihan_ganda' && question.pilihan_jawaban ? 
                    `<div class="pilihan-preview">
                      <small class="text-muted fw-bold d-block mb-1">Pilihan:</small>
                      <div class="pilihan-list">
                        ${JSON.parse(question.pilihan_jawaban).map((opt, idx) => 
                          `<small class="d-block"><strong>${String.fromCharCode(65 + idx)}.</strong> ${opt}</small>`
                        ).join('')}
                      </div>
                    </div>` : ''
                  }
                </div>
              </div>
            </div>
          </div>
        </div>
      `;
    });
    
    html += '</div>';
    pertanyaanContainer.innerHTML = html;

    // Add event listeners to checkboxes
    document.querySelectorAll('.question-checkbox').forEach(checkbox => {
      checkbox.addEventListener('change', handleQuestionSelection);
    });

    // Add click handlers to cards
    document.querySelectorAll('.question-card').forEach(card => {
      card.addEventListener('click', function(e) {
        if (e.target.type !== 'checkbox') {
          const checkbox = this.querySelector('.question-checkbox');
          checkbox.checked = !checkbox.checked;
          checkbox.dispatchEvent(new Event('change'));
        }
      });
    });
  }

  // Handle question selection (SAME AS TAMBAH.PHP)
  function handleQuestionSelection(e) {
    const questionId = parseInt(e.target.value);
    const card = e.target.closest('.question-card');
    
    if (e.target.checked) {
      if (!selectedQuestions.includes(questionId)) {
        selectedQuestions.push(questionId);
      }
      card.classList.add('selected');
    } else {
      selectedQuestions = selectedQuestions.filter(id => id !== questionId);
      card.classList.remove('selected');
    }
    
    updateSelectionUI();
  }

  // Update selection UI (SAME AS TAMBAH.PHP)
  function updateSelectionUI() {
    selectedCount.textContent = `${selectedQuestions.length} dipilih`;
    
    if (selectedQuestions.length > 0) {
      selectedPreview.style.display = 'block';
      
      const selectedData = availableQuestions.filter(q => 
        selectedQuestions.includes(q.id_pertanyaan)
      );
      
      let previewHtml = `
        <div class="row g-2">
          ${selectedData.map((q, index) => `
            <div class="col-md-6">
              <div class="selected-item p-2 border rounded bg-white">
                <div class="d-flex justify-content-between align-items-start">
                  <div class="flex-grow-1">
                    <small class="fw-bold">${index + 1}. ${q.pertanyaan.substring(0, 60)}${q.pertanyaan.length > 60 ? '...' : ''}</small>
                    <div class="mt-1">
                      <span class="badge bg-secondary">${q.tipe_jawaban.replace('_', ' ')}</span>
                    </div>
                  </div>
                  <button type="button" class="btn btn-sm btn-outline-danger ms-2" 
                          onclick="removeQuestion(${q.id_pertanyaan})">
                    <i class="bi bi-x"></i>
                  </button>
                </div>
              </div>
            </div>
          `).join('')}
        </div>
      `;
      
      selectedList.innerHTML = previewHtml;
    } else {
      selectedPreview.style.display = 'none';
    }
  }

  // Remove question from selection (SAME AS TAMBAH.PHP)
  window.removeQuestion = function(questionId) {
    selectedQuestions = selectedQuestions.filter(id => id !== questionId);
    
    // Update checkbox
    const checkbox = document.querySelector(`input[value="${questionId}"]`);
    if (checkbox) {
      checkbox.checked = false;
      checkbox.closest('.question-card').classList.remove('selected');
    }
    
    updateSelectionUI();
  };

  // Search functionality (SAME AS TAMBAH.PHP)
  searchPertanyaan.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    
    if (searchTerm === '') {
      filteredQuestions = [...availableQuestions];
    } else {
      filteredQuestions = availableQuestions.filter(q => 
        q.pertanyaan.toLowerCase().includes(searchTerm) ||
        q.aspek_dinilai.toLowerCase().includes(searchTerm)
      );
    }
    
    renderQuestions();
  });

  // Select all functionality (SAME AS TAMBAH.PHP)
  selectAllBtn.addEventListener('click', function() {
    filteredQuestions.forEach(q => {
      if (!selectedQuestions.includes(q.id_pertanyaan)) {
        selectedQuestions.push(q.id_pertanyaan);
      }
    });
    
    // Update checkboxes
    document.querySelectorAll('.question-checkbox').forEach(checkbox => {
      checkbox.checked = true;
      checkbox.closest('.question-card').classList.add('selected');
    });
    
    updateSelectionUI();
  });

  // Clear all functionality (SAME AS TAMBAH.PHP)
  clearAllBtn.addEventListener('click', function() {
    selectedQuestions = [];
    
    // Update checkboxes
    document.querySelectorAll('.question-checkbox').forEach(checkbox => {
      checkbox.checked = false;
      checkbox.closest('.question-card').classList.remove('selected');
    });
    
    updateSelectionUI();
  });

  // Quick action functions
  function extendPeriode7() {
    const tutup = new Date(tanggalTutup.value);
    tutup.setDate(tutup.getDate() + 7);
    tanggalTutup.value = tutup.toISOString().slice(0, 16);
  }

  function extendPeriode14() {
    const tutup = new Date(tanggalTutup.value);
    tutup.setDate(tutup.getDate() + 14);
    tanggalTutup.value = tutup.toISOString().slice(0, 16);
  }

  function setSelesaiSekarang() {
    if (confirm('Yakin ingin menyelesaikan periode ini sekarang? Siswa tidak akan bisa mengisi evaluasi lagi.')) {
      const now = new Date();
      tanggalTutup.value = now.toISOString().slice(0, 16);
      document.getElementById('statusSelesai').checked = true;
    }
  }

  // Event listeners
  if (!hasResponden) {
    jenisEvaluasi.addEventListener('change', function() {
      toggleMateriTerkait();
    });

    materiTerkait.addEventListener('change', function() {
      loadQuestions();
    });

    idGelombang.addEventListener('change', function() {
      // No action for gelombang change in edit mode
    });
  }

  namaEvaluasi.addEventListener('input', function() {
    updateCharCount();
  });

  tanggalBuka.addEventListener('change', function() {
    // Auto-adjust tanggal tutup if it's before tanggal buka
    if (tanggalTutup.value && new Date(tanggalTutup.value) <= new Date(tanggalBuka.value)) {
      const buka = new Date(tanggalBuka.value);
      const tutup = new Date(buka);
      tutup.setDate(tutup.getDate() + 7);
      tutup.setHours(23, 59, 0, 0);
      tanggalTutup.value = tutup.toISOString().slice(0, 16);
    }
  });

  // Quick action event listeners
  document.getElementById('extendPeriode7').addEventListener('click', extendPeriode7);
  document.getElementById('extendPeriode14').addEventListener('click', extendPeriode14);
  document.getElementById('setSelesaiSekarang').addEventListener('click', setSelesaiSekarang);

  // Form validation (SAME AS TAMBAH.PHP)
  form.addEventListener('submit', function(e) {
    const nama = namaEvaluasi.value.trim();
    const buka = tanggalBuka.value;
    const tutup = tanggalTutup.value;
    
    // Validate nama length
    if (nama.length < 5) {
      e.preventDefault();
      alert('Nama evaluasi minimal 5 karakter!');
      namaEvaluasi.focus();
      return;
    }

    // Validate dates
    if (buka && tutup && new Date(tutup) <= new Date(buka)) {
      e.preventDefault();
      alert('Tanggal tutup harus setelah tanggal buka!');
      tanggalTutup.focus();
      return;
    }

    // Validate question selection
    if (selectedQuestions.length === 0) {
      e.preventDefault();
      alert('Minimal 1 pertanyaan harus dipilih!');
      return;
    }

    if (selectedQuestions.length > 50) {
      e.preventDefault();
      alert('Maksimal 50 pertanyaan dapat dipilih!');
      return;
    }

    // Confirm if setting to selesai
    const status = document.querySelector('input[name="status"]:checked')?.value;
    if (status === 'selesai' && '<?= $periode['status'] ?>' !== 'selesai') {
      if (!confirm('Yakin ingin mengubah status menjadi SELESAI? Periode tidak akan bisa diedit lagi.')) {
        e.preventDefault();
        return;
      }
    }

    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Menyimpan Perubahan...';
  });

  // Initialize
  toggleMateriTerkait();
  updateCharCount();
  loadQuestions(); // Load questions on page load untuk edit mode
});
</script>

<style>
/* SAME STYLES AS TAMBAH.PHP */
.show-animation {
  animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.section-title {
  color: #495057;
  border-bottom: 2px solid #e9ecef;
  padding-bottom: 0.5rem;
}

.question-card {
  border: 1px solid #dee2e6;
  transition: all 0.2s ease;
  cursor: pointer;
}

.question-card:hover {
  border-color: #86b7fe;
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.question-card.selected {
  border-color: #0d6efd;
  background-color: #f8f9ff;
  box-shadow: 0 0.25rem 0.5rem rgba(13, 110, 253, 0.15);
}

.question-text {
  font-size: 0.9rem;
  line-height: 1.4;
  color: #333;
}

.pilihan-preview {
  background-color: #f8f9fa;
  padding: 0.5rem;
  border-radius: 0.25rem;
  margin-top: 0.5rem;
}

.pilihan-list small {
  margin-bottom: 0.25rem;
}

.selected-item {
  transition: all 0.2s ease;
}

.selected-item:hover {
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.required::after {
  content: " *";
  color: #dc3545;
}

.form-control:focus,
.form-select:focus {
  border-color: #86b7fe;
  box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.form-check-input:checked {
  background-color: #0d6efd;
  border-color: #0d6efd;
}

/* Disabled field styling */
.form-select:disabled,
.form-control:disabled {
  background-color: #f8f9fa;
  opacity: 0.8;
}

@media (max-width: 768px) {
  .section-title {
    font-size: 1rem;
  }
  
  .question-card .card-body {
    padding: 1rem !important;
  }
  
  .selected-item {
    margin-bottom: 0.5rem;
  }
}
</style>
</body>
</html>