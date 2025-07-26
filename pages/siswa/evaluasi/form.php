<?php
session_start();
require_once '../../../includes/auth.php';
requireSiswaAuth(); // Hanya siswa yang bisa akses

include '../../../includes/db.php';
$activePage = 'evaluasi'; 
$baseURL = '../';

// Ambil ID periode evaluasi
$id_periode = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id_periode) {
    $_SESSION['error'] = "ID periode evaluasi tidak valid!";
    header("Location: index.php");
    exit();
}

// Ambil data siswa yang sedang login
$stmt = $conn->prepare("SELECT s.*, k.nama_kelas, g.nama_gelombang, g.id_gelombang, i.nama as nama_instruktur 
                       FROM siswa s 
                       LEFT JOIN kelas k ON s.id_kelas = k.id_kelas 
                       LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
                       LEFT JOIN instruktur i ON k.id_instruktur = i.id_instruktur
                       WHERE s.id_user = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$siswaData = $stmt->get_result()->fetch_assoc();

if (!$siswaData || !$siswaData['id_kelas']) {
    $_SESSION['error'] = "Data siswa atau kelas tidak ditemukan!";
    header("Location: ../dashboard.php");
    exit();
}

// Ambil data periode evaluasi dan validasi akses
$periodeQuery = "SELECT pe.*, 
                 CASE 
                   WHEN NOW() < pe.tanggal_buka THEN 'belum_buka'
                   WHEN NOW() > pe.tanggal_tutup THEN 'sudah_tutup'
                   WHEN pe.status = 'aktif' THEN 'bisa_dikerjakan'
                   ELSE 'tidak_aktif'
                 END as status_akses
                 FROM periode_evaluasi pe 
                 WHERE pe.id_periode = ? AND pe.id_gelombang = ?";

$periodeStmt = $conn->prepare($periodeQuery);
$periodeStmt->bind_param("ii", $id_periode, $siswaData['id_gelombang']);
$periodeStmt->execute();
$periodeData = $periodeStmt->get_result()->fetch_assoc();

if (!$periodeData) {
    $_SESSION['error'] = "Evaluasi tidak ditemukan atau tidak tersedia untuk gelombang Anda!";
    header("Location: index.php");
    exit();
}

// Cek apakah bisa dikerjakan
if ($periodeData['status_akses'] != 'bisa_dikerjakan') {
    $_SESSION['error'] = "Evaluasi ini tidak dapat dikerjakan saat ini!";
    header("Location: index.php");
    exit();
}

// Cek apakah sudah pernah dikerjakan
$cekSudahKerjaQuery = "SELECT id_evaluasi FROM evaluasi WHERE id_siswa = ? AND id_periode = ?";
$cekStmt = $conn->prepare($cekSudahKerjaQuery);
$cekStmt->bind_param("ii", $siswaData['id_siswa'], $id_periode);
$cekStmt->execute();
$sudahDikerjakan = $cekStmt->get_result()->num_rows > 0;

if ($sudahDikerjakan) {
    $_SESSION['error'] = "Anda sudah pernah mengerjakan evaluasi ini!";
    header("Location: index.php");
    exit();
}

// Ambil pertanyaan untuk evaluasi ini
$pertanyaanTerpilih = json_decode($periodeData['pertanyaan_terpilih'], true);

if (empty($pertanyaanTerpilih)) {
    $_SESSION['error'] = "Tidak ada pertanyaan tersedia untuk evaluasi ini!";
    header("Location: index.php");
    exit();
}

// PERBAIKAN: Ambil detail pertanyaan dari database dengan urutan yang benar
$pertanyaanIds = implode(',', array_map('intval', $pertanyaanTerpilih));
$pertanyaanQuery = "SELECT * FROM pertanyaan_evaluasi WHERE id_pertanyaan IN ($pertanyaanIds)";
$pertanyaanResult = $conn->query($pertanyaanQuery);

// Simpan hasil query dalam array dengan key id_pertanyaan
$pertanyaanFromDB = [];
while ($row = $pertanyaanResult->fetch_assoc()) {
    $pertanyaanFromDB[$row['id_pertanyaan']] = $row;
}

// Susun ulang sesuai urutan di pertanyaan_terpilih
$pertanyaanList = [];
foreach ($pertanyaanTerpilih as $id_pertanyaan) {
    if (isset($pertanyaanFromDB[$id_pertanyaan])) {
        $pertanyaanList[] = $pertanyaanFromDB[$id_pertanyaan];
    }
}

// Proses form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn->begin_transaction();
    
    try {
        // Insert ke tabel evaluasi
        $insertEvaluasi = "INSERT INTO evaluasi (id_siswa, id_kelas, id_periode, tanggal_evaluasi, status_evaluasi) VALUES (?, ?, ?, NOW(), 'selesai')";
        $evalStmt = $conn->prepare($insertEvaluasi);
        $evalStmt->bind_param("iii", $siswaData['id_siswa'], $siswaData['id_kelas'], $id_periode);
        $evalStmt->execute();
        
        $id_evaluasi = $conn->insert_id;
        
        // PERBAIKAN: Insert jawaban sesuai urutan pertanyaan yang benar
        $insertJawaban = "INSERT INTO jawaban_evaluasi (id_evaluasi, id_pertanyaan, id_siswa, jawaban) VALUES (?, ?, ?, ?)";
        $jawabanStmt = $conn->prepare($insertJawaban);
        
        foreach ($pertanyaanList as $pertanyaan) {
            $id_pertanyaan = $pertanyaan['id_pertanyaan'];
            $jawaban = isset($_POST['jawaban_' . $id_pertanyaan]) ? trim($_POST['jawaban_' . $id_pertanyaan]) : '';
            
            // Validasi jawaban tidak kosong
            if (empty($jawaban)) {
                throw new Exception("Semua pertanyaan harus dijawab!");
            }
            
            $jawabanStmt->bind_param("iiis", $id_evaluasi, $id_pertanyaan, $siswaData['id_siswa'], $jawaban);
            $jawabanStmt->execute();
        }
        
        $conn->commit();
        $_SESSION['success'] = "Evaluasi berhasil diselesaikan! Terima kasih atas partisipasi Anda.";
        header("Location: index.php");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Gagal menyimpan evaluasi: " . $e->getMessage();
    }
}

// Function untuk mendapatkan pilihan jawaban
function getPilihanJawaban($pilihan_jawaban) {
    if (empty($pilihan_jawaban)) return [];
    $decoded = json_decode($pilihan_jawaban, true);
    return is_array($decoded) ? $decoded : [];
}

// Function untuk nama materi
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
  <title>Form Evaluasi - <?= htmlspecialchars($periodeData['nama_evaluasi']) ?></title>
  <link rel="icon" type="image/png" href="../../../assets/img/favicon.png"/>
  <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../../assets/css/fonts.css" />
  <link rel="stylesheet" href="../../../assets/css/styles.css" />
</head>

<body>
  <div class="d-flex">
    <?php include '../../../includes/sidebar/siswa.php'; ?>
    
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
                <h2 class="page-title mb-1">FORM EVALUASI</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="index.php">Evaluasi Pembelajaran</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($periodeData['nama_evaluasi']) ?></li>
                  </ol>
                </nav>
              </div>
            </div>
            
            <div class="d-flex align-items-center">
              <div class="navbar-page-info d-none d-md-block">
                <small class="text-muted">
                  <i class="bi bi-clock me-1"></i>
                  <span id="waktuTersisa"></span>
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

        <!-- Info Evaluasi -->
        <div class="card content-card mb-4">
          <div class="card-body">
            <div class="row align-items-center">
              <div class="col-md-8">
                <h5 class="mb-1 text-dark"><?= htmlspecialchars($periodeData['nama_evaluasi']) ?></h5>
                <div class="mb-2">
                  <span class="badge bg-info px-2 py-1">
                    <i class="bi bi-<?= $periodeData['jenis_evaluasi'] == 'per_materi' ? 'book' : 'trophy' ?> me-1"></i>
                    <?= $periodeData['jenis_evaluasi'] == 'per_materi' ? 'Evaluasi Per Materi' : 'Evaluasi Akhir Kursus' ?>
                  </span>
                  <?php if($periodeData['materi_terkait']): ?>
                    <span class="badge bg-secondary px-2 py-1 ms-1">
                      <?= getNamaMateri($periodeData['materi_terkait']) ?>
                    </span>
                  <?php endif; ?>
                </div>
                
                <?php if($periodeData['deskripsi']): ?>
                  <p class="text-muted mb-0"><?= htmlspecialchars($periodeData['deskripsi']) ?></p>
                <?php endif; ?>
              </div>
              
              <div class="col-md-4 text-md-end">
                <div class="text-muted">
                  <small><strong>Total Pertanyaan:</strong> <?= count($pertanyaanList) ?></small><br>
                  <small><strong>Estimasi Waktu:</strong> <?= count($pertanyaanList) * 2 ?> menit</small><br>
                  <small><strong>Kelas:</strong> <?= htmlspecialchars($siswaData['nama_kelas']) ?></small>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Form Evaluasi -->
        <form method="POST" id="formEvaluasi">
          <div class="card content-card">
            <div class="section-header">
              <h5 class="mb-0 text-dark">
                <i class="bi bi-clipboard-check me-2"></i>Silakan isi evaluasi dengan jujur
              </h5>
            </div>

            <div class="card-body">
              <!-- Progress Bar -->
              <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <small class="text-muted">Progress Pengisian</small>
                  <small class="text-muted"><span id="progressText">0</span> dari <?= count($pertanyaanList) ?> pertanyaan</small>
                </div>
                <div class="progress" style="height: 8px;">
                  <div class="progress-bar bg-success" id="progressBar" style="width: 0%"></div>
                </div>
              </div>

              <!-- Pertanyaan -->
              <?php foreach ($pertanyaanList as $index => $pertanyaan): ?>
                <div class="question-card mb-4 p-4 border rounded" data-question="<?= $index + 1 ?>">
                  <div class="question-header mb-3">
                    <div class="d-flex align-items-start justify-content-between">
                      <div class="flex-grow-1">
                        <div class="question-number">
                          <span class="badge bg-primary fs-6 me-2"><?= $index + 1 ?></span>
                          <span class="badge bg-secondary small"><?= htmlspecialchars($pertanyaan['aspek_dinilai']) ?></span>
                        </div>
                        <h6 class="question-text mt-2 mb-0"><?= nl2br(htmlspecialchars($pertanyaan['pertanyaan'])) ?></h6>
                      </div>
                    </div>
                  </div>

                  <div class="question-answer">
                    <?php if ($pertanyaan['tipe_jawaban'] == 'skala'): ?>
                      <!-- Skala 1-5 -->
                      <div class="scale-options">
                        <div class="row text-center">
                          <?php for ($i = 1; $i <= 5; $i++): ?>
                            <div class="col">
                              <div class="scale-option">
                                <input type="radio" 
                                       name="jawaban_<?= $pertanyaan['id_pertanyaan'] ?>" 
                                       id="skala_<?= $pertanyaan['id_pertanyaan'] ?>_<?= $i ?>" 
                                       value="<?= $i ?>" 
                                       class="scale-input" 
                                       required>
                                <label for="skala_<?= $pertanyaan['id_pertanyaan'] ?>_<?= $i ?>" class="scale-label">
                                  <div class="scale-number"><?= $i ?></div>
                                  <div class="scale-text">
                                    <?php
                                    $scaleText = ['Sangat Buruk', 'Buruk', 'Cukup', 'Baik', 'Sangat Baik'];
                                    echo $scaleText[$i-1];
                                    ?>
                                  </div>
                                </label>
                              </div>
                            </div>
                          <?php endfor; ?>
                        </div>
                      </div>

                    <?php elseif ($pertanyaan['tipe_jawaban'] == 'pilihan_ganda'): ?>
                      <!-- PERBAIKAN: Pilihan Ganda -->
                      <?php $pilihan = getPilihanJawaban($pertanyaan['pilihan_jawaban']); ?>
                      <?php if (!empty($pilihan)): ?>
                        <div class="multiple-choice-options">
                          <?php foreach ($pilihan as $pilIndex => $option): ?>
                            <div class="form-check mb-2">
                              <input class="form-check-input" 
                                     type="radio" 
                                     name="jawaban_<?= $pertanyaan['id_pertanyaan'] ?>" 
                                     id="pilihan_<?= $pertanyaan['id_pertanyaan'] ?>_<?= $pilIndex ?>" 
                                     value="<?= htmlspecialchars($option) ?>" 
                                     required>
                              <label class="form-check-label" for="pilihan_<?= $pertanyaan['id_pertanyaan'] ?>_<?= $pilIndex ?>">
                                <strong><?= chr(65 + $pilIndex) ?>.</strong> <?= htmlspecialchars($option) ?>
                              </label>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>

                    <?php else: ?>
                      <!-- Isian Bebas -->
                      <div class="textarea-wrapper">
                        <textarea name="jawaban_<?= $pertanyaan['id_pertanyaan'] ?>" 
                                  id="jawaban_<?= $pertanyaan['id_pertanyaan'] ?>"
                                  class="form-control answer-textarea" 
                                  rows="4" 
                                  placeholder="Tuliskan jawaban Anda di sini..."
                                  required></textarea>
                        <div class="form-text">
                          <small class="text-muted">
                            <span class="char-count" data-target="jawaban_<?= $pertanyaan['id_pertanyaan'] ?>">0</span> karakter
                          </small>
                        </div>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>

              <!-- Konfirmasi -->
              <div class="confirmation-section mt-5 p-4 bg-light rounded">
                <h6 class="mb-3">
                  <i class="bi bi-shield-check me-2"></i>Konfirmasi Pengisian
                </h6>
                <div class="form-check mb-3">
                  <input class="form-check-input" type="checkbox" id="konfirmasiData" required>
                  <label class="form-check-label" for="konfirmasiData">
                    Saya menyatakan bahwa data yang saya isi dalam evaluasi ini adalah benar dan sesuai dengan pengalaman saya
                  </label>
                </div>
                <div class="form-check mb-3">
                  <input class="form-check-input" type="checkbox" id="konfirmasiSubmit" required>
                  <label class="form-check-label" for="konfirmasiSubmit">
                    Saya memahami bahwa evaluasi ini hanya dapat diisi sekali dan tidak dapat diubah setelah dikirim
                  </label>
                </div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="card-footer">
              <div class="d-flex justify-content-between align-items-center">
                <a href="index.php" class="btn btn-outline-secondary">
                  <i class="bi bi-arrow-left me-1"></i>Kembali
                </a>
                
                <div class="d-flex gap-2">
                  <button type="button" class="btn btn-warning" id="btnDraft">
                    <i class="bi bi-save me-1"></i>Simpan Draft
                  </button>
                  <button type="submit" class="btn btn-success" id="btnSubmit">
                    <i class="bi bi-check-lg me-1"></i>Kirim Evaluasi
                  </button>
                </div>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../../assets/js/scripts.js"></script>

  <script>
  document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('formEvaluasi');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const totalQuestions = <?= count($pertanyaanList) ?>;
    const waktuTutup = new Date('<?= $periodeData['tanggal_tutup'] ?>').getTime();

    // PERBAIKAN: Update progress yang lebih akurat
    function updateProgress() {
      let answeredCount = 0;
      
      // Hitung jawaban yang sudah diisi
      <?php foreach ($pertanyaanList as $pertanyaan): ?>
        <?php if ($pertanyaan['tipe_jawaban'] == 'skala' || $pertanyaan['tipe_jawaban'] == 'pilihan_ganda'): ?>
          if (form.querySelector('input[name="jawaban_<?= $pertanyaan['id_pertanyaan'] ?>"]:checked')) {
            answeredCount++;
          }
        <?php elseif ($pertanyaan['tipe_jawaban'] == 'isian'): ?>
          if (form.querySelector('textarea[name="jawaban_<?= $pertanyaan['id_pertanyaan'] ?>"]').value.trim()) {
            answeredCount++;
          }
        <?php endif; ?>
      <?php endforeach; ?>
      
      const percentage = Math.round((answeredCount / totalQuestions) * 100);
      
      progressBar.style.width = percentage + '%';
      progressText.textContent = answeredCount;
      
      // Change color based on progress
      if (percentage < 30) {
        progressBar.className = 'progress-bar bg-danger';
      } else if (percentage < 70) {
        progressBar.className = 'progress-bar bg-warning';
      } else {
        progressBar.className = 'progress-bar bg-success';
      }
    }

    // Update countdown timer
    function updateCountdown() {
      const now = new Date().getTime();
      const timeLeft = waktuTutup - now;
      
      if (timeLeft > 0) {
        const hours = Math.floor(timeLeft / (1000 * 60 * 60));
        const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
        
        document.getElementById('waktuTersisa').textContent = 
          hours > 0 ? `${hours}j ${minutes}m tersisa` : `${minutes} menit tersisa`;
      } else {
        document.getElementById('waktuTersisa').textContent = 'Waktu habis';
        // Disable form
        form.querySelectorAll('input, textarea, button').forEach(el => el.disabled = true);
        alert('Waktu evaluasi telah habis. Form akan dikunci.');
      }
    }

    // Character counter for textareas
    function updateCharCount() {
      document.querySelectorAll('.answer-textarea').forEach(textarea => {
        const counter = document.querySelector(`[data-target="${textarea.id}"]`);
        if (counter) {
          counter.textContent = textarea.value.length;
        }
      });
    }

    // Event listeners
    form.addEventListener('change', updateProgress);
    form.addEventListener('input', function(e) {
      if (e.target.tagName === 'TEXTAREA') {
        updateProgress();
        updateCharCount();
      }
    });

    // Smooth scroll to question on focus
    form.querySelectorAll('input, textarea').forEach(input => {
      input.addEventListener('focus', function() {
        const questionCard = this.closest('.question-card');
        if (questionCard) {
          questionCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      });
    });

    // PERBAIKAN: Form submission validation
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      
      // Check if all questions are answered
      const unanswered = [];
      
      <?php foreach ($pertanyaanList as $index => $pertanyaan): ?>
        <?php if ($pertanyaan['tipe_jawaban'] == 'skala' || $pertanyaan['tipe_jawaban'] == 'pilihan_ganda'): ?>
          if (!form.querySelector('input[name="jawaban_<?= $pertanyaan['id_pertanyaan'] ?>"]:checked')) {
            unanswered.push(<?= $index + 1 ?>);
          }
        <?php elseif ($pertanyaan['tipe_jawaban'] == 'isian'): ?>
          if (!form.querySelector('textarea[name="jawaban_<?= $pertanyaan['id_pertanyaan'] ?>"]').value.trim()) {
            unanswered.push(<?= $index + 1 ?>);
          }
        <?php endif; ?>
      <?php endforeach; ?>
      
      if (unanswered.length > 0) {
        alert(`Mohon jawab pertanyaan nomor: ${unanswered.join(', ')}`);
        // Scroll to first unanswered
        const firstUnanswered = document.querySelector(`[data-question="${unanswered[0]}"]`);
        if (firstUnanswered) {
          firstUnanswered.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return;
      }
      
      // Final confirmation
      if (confirm('Apakah Anda yakin ingin mengirim evaluasi ini? Evaluasi tidak dapat diubah setelah dikirim.')) {
        // Show loading
        const submitBtn = document.getElementById('btnSubmit');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Mengirim...';
        
        // Submit form
        form.submit();
      }
    });

    // Initialize
    updateProgress();
    updateCharCount();
    updateCountdown();
    
    // Update countdown every minute
    setInterval(updateCountdown, 60000);

    // Prevent accidental page leave
    window.addEventListener('beforeunload', function(e) {
      let hasAnswers = false;
      
      <?php foreach ($pertanyaanList as $pertanyaan): ?>
        <?php if ($pertanyaan['tipe_jawaban'] == 'skala' || $pertanyaan['tipe_jawaban'] == 'pilihan_ganda'): ?>
          if (form.querySelector('input[name="jawaban_<?= $pertanyaan['id_pertanyaan'] ?>"]:checked')) {
            hasAnswers = true;
          }
        <?php elseif ($pertanyaan['tipe_jawaban'] == 'isian'): ?>
          if (form.querySelector('textarea[name="jawaban_<?= $pertanyaan['id_pertanyaan'] ?>"]').value.trim()) {
            hasAnswers = true;
          }
        <?php endif; ?>
      <?php endforeach; ?>
      
      if (hasAnswers) {
        e.preventDefault();
        e.returnValue = '';
      }
    });

    // Auto-save draft functionality (optional)
    let autoSaveTimer;
    form.addEventListener('input', function() {
      clearTimeout(autoSaveTimer);
      autoSaveTimer = setTimeout(function() {
        console.log('Auto-saving draft...');
      }, 5000);
    });
  });
  </script>

  <style>
  .question-card {
    transition: all 0.3s ease;
    border: 2px solid #e9ecef !important;
  }
  
  .question-card:hover {
    border-color: #0d6efd !important;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
  }
  
  .question-number .badge {
    font-size: 0.9rem;
  }
  
  .question-text {
    font-size: 1.1rem;
    line-height: 1.5;
    color: #495057;
  }
  
  .scale-options {
    margin: 1rem 0;
  }
  
  .scale-option {
    position: relative;
    margin-bottom: 1rem;
  }
  
  .scale-input {
    display: none;
  }
  
  .scale-label {
    display: block;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    padding: 1rem;
    border: 2px solid #e9ecef;
    border-radius: 0.5rem;
  }
  
  .scale-label:hover {
    border-color: #0d6efd;
    background-color: #f8f9fa;
  }
  
  .scale-input:checked + .scale-label {
    border-color: #0d6efd;
    background-color: #0d6efd;
    color: white;
  }
  
  .scale-number {
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
  }
  
  .scale-text {
    font-size: 0.875rem;
  }
  
  .multiple-choice-options .form-check {
    border: 1px solid #e9ecef;
    border-radius: 0.375rem;
    padding: 0.75rem 1rem;
    transition: all 0.3s ease;
    cursor: pointer;
  }
  
  .multiple-choice-options .form-check:hover {
    border-color: #0d6efd;
    background-color: #f8f9fa;
  }
  
  .multiple-choice-options .form-check-input:checked + .form-check-label {
    color: #0d6efd;
    font-weight: 500;
  }
  
  .multiple-choice-options .form-check-input:checked {
    background-color: #0d6efd;
    border-color: #0d6efd;
  }
  
  .answer-textarea {
    border: 2px solid #e9ecef;
    transition: border-color 0.15s ease-in-out;
  }
  
  .answer-textarea:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
  }
  
  .confirmation-section {
    border: 2px dashed #dee2e6;
  }
  
  .char-count {
    font-size: 0.8rem;
  }
  
  .progress {
    background-color: #e9ecef;
    border-radius: 0.5rem;
  }
  
  .progress-bar {
    transition: width 0.3s ease;
  }
  
  @media (max-width: 768px) {
    .scale-options .col {
      margin-bottom: 0.5rem;
    }
    
    .scale-label {
      padding: 0.75rem 0.5rem;
    }
    
    .question-text {
      font-size: 1rem;
    }
    
    .multiple-choice-options .form-check {
      padding: 0.5rem 0.75rem;
    }
  }
  </style>
</html>