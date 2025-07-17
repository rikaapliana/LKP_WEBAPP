<?php
session_start();  
require_once '../../../../includes/auth.php';  
requireAdminAuth();

include '../../../../includes/db.php';
$activePage = 'evaluasi'; 
$baseURL = '../../';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate input
    $pertanyaan = trim($_POST['pertanyaan']);
    $aspek_dinilai = trim($_POST['aspek_dinilai']);
    $jenis_evaluasi = $_POST['jenis_evaluasi']; // Enum: 'per_materi', 'akhir_kursus'
    $tipe_jawaban = $_POST['tipe_jawaban']; // Enum: 'skala', 'isian', 'pilihan_ganda'
    $materi_terkait = null;
    $pilihan_jawaban = null;
    
    // Conditional field: materi_terkait hanya untuk jenis_evaluasi = 'per_materi'
    if ($jenis_evaluasi === 'per_materi' && !empty($_POST['materi_terkait'])) {
        $materi_terkait = $_POST['materi_terkait']; // Enum: 'word', 'excel', 'ppt', 'internet'
    }
    
    // Validasi input
    $errors = [];
    
    if (empty($pertanyaan)) {
        $errors[] = "Pertanyaan tidak boleh kosong.";
    } elseif (strlen($pertanyaan) < 10) {
        $errors[] = "Pertanyaan minimal 10 karakter.";
    }
    
    if (empty($aspek_dinilai)) {
        $errors[] = "Aspek dinilai tidak boleh kosong.";
    }
    
    if (!in_array($jenis_evaluasi, ['per_materi', 'akhir_kursus'])) {
        $errors[] = "Jenis evaluasi tidak valid.";
    }
    
    if (!in_array($tipe_jawaban, ['skala', 'isian', 'pilihan_ganda'])) {
        $errors[] = "Tipe jawaban tidak valid.";
    }
    
    // Validasi khusus untuk per_materi
    if ($jenis_evaluasi === 'per_materi') {
        if (empty($materi_terkait)) {
            $errors[] = "Materi terkait wajib diisi untuk evaluasi per materi.";
        } elseif (!in_array($materi_terkait, ['word', 'excel', 'ppt', 'internet'])) {
            $errors[] = "Materi terkait tidak valid.";
        }
    }
    
    // Validasi khusus untuk pilihan ganda
    if ($tipe_jawaban === 'pilihan_ganda') {
        if (empty($_POST['pilihan_a']) || empty($_POST['pilihan_b']) || 
            empty($_POST['pilihan_c']) || empty($_POST['pilihan_d'])) {
            $errors[] = "Semua pilihan jawaban (A-D) wajib diisi untuk tipe pilihan ganda.";
        } else {
            $pilihan_jawaban = json_encode([
                trim($_POST['pilihan_a']),
                trim($_POST['pilihan_b']),
                trim($_POST['pilihan_c']),
                trim($_POST['pilihan_d'])
            ]);
        }
    }
    
    // Check for duplicate pertanyaan
    $checkQuery = "SELECT id_pertanyaan FROM pertanyaan_evaluasi WHERE pertanyaan = ? AND jenis_evaluasi = ?";
    $checkStmt = mysqli_prepare($conn, $checkQuery);
    mysqli_stmt_bind_param($checkStmt, "ss", $pertanyaan, $jenis_evaluasi);
    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);
    
    if (mysqli_num_rows($checkResult) > 0) {
        $errors[] = "Pertanyaan yang sama sudah ada untuk jenis evaluasi ini.";
    }
    
    if (empty($errors)) {
        // Insert dengan prepared statement untuk keamanan
        $insertQuery = "INSERT INTO pertanyaan_evaluasi (pertanyaan, aspek_dinilai, jenis_evaluasi, materi_terkait, tipe_jawaban, pilihan_jawaban) 
                        VALUES (?, ?, ?, ?, ?, ?)";
        
        $insertStmt = mysqli_prepare($conn, $insertQuery);
        mysqli_stmt_bind_param($insertStmt, "ssssss", $pertanyaan, $aspek_dinilai, $jenis_evaluasi, $materi_terkait, $tipe_jawaban, $pilihan_jawaban);
        
        if (mysqli_stmt_execute($insertStmt)) {
            $_SESSION['success'] = "Pertanyaan evaluasi berhasil ditambahkan ke bank soal!";
            header("Location: index.php");
            exit;
        } else {
            $error = "Gagal menyimpan pertanyaan: " . mysqli_error($conn);
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

// Data untuk dropdown aspek berdasarkan jenis evaluasi
$aspekPerMateri = [
    'Kejelasan Materi',
    'Kemudahan Pembelajaran',
    'Kualitas Contoh/Latihan',
    'Tingkat Kesulitan',
    'Manfaat Praktis',
    'Durasi Pembelajaran',
    'Kesesuaian dengan Kebutuhan',
    'Kelengkapan Pembahasan'
];

$aspekAkhirKursus = [
    'Fasilitas LKP',
    'Kualitas Instruktur',
    'Administrasi/Pelayanan',
    'Lingkungan Belajar',
    'Pencapaian Tujuan',
    'Kepuasan Keseluruhan',
    'Nilai Investasi',
    'Rekomendasi ke Orang Lain'
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tambah Pertanyaan Evaluasi</title>
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
          <!-- Left: Hamburger + Page Info -->
          <div class="d-flex align-items-center flex-grow-1">
            <!-- Sidebar Toggle Button -->
            <button class="btn btn-link text-dark p-2 me-3 sidebar-toggle" type="button" id="sidebarToggle">
              <i class="bi bi-list fs-4"></i>
            </button>
            
            <!-- Page Title & Breadcrumb -->
            <div class="page-info">
              <h2 class="page-title mb-1">TAMBAH PERTANYAAN EVALUASI</h2>
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="#">Evaluasi</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="../periode/index.php">Periode Evaluasi</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="index.php">Kelola Bank Soal</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Tambah Pertanyaan</li>
                  </ol>
              </nav>
            </div>
          </div>
          
          <!-- Right: Date Info -->
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

      <!-- Main Form Card -->
      <div class="card content-card">
        <div class="section-header">
          <h5 class="mb-0 text-dark">
            <i class="bi bi-plus-circle me-2"></i>Form Tambah Pertanyaan Evaluasi
          </h5>
        </div>

        <div class="card-body">
          <form action="" method="post" id="formTambahPertanyaan">
            <div class="row">
              
              <!-- Konfigurasi Evaluasi Section -->
              <div class="col-lg-6">
                <h6 class="section-title mb-4">
                  <i class="bi bi-gear me-2"></i>Konfigurasi Evaluasi
                </h6>
                
                <div class="mb-4">
                  <label class="form-label required">Jenis Evaluasi</label>
                  <select name="jenis_evaluasi" id="jenisEvaluasi" class="form-select" required>
                    <option value="">Pilih Jenis Evaluasi</option>
                    <option value="per_materi" <?= (isset($_POST['jenis_evaluasi']) && $_POST['jenis_evaluasi'] == 'per_materi') ? 'selected' : '' ?>>
                      Evaluasi Per Materi
                    </option>
                    <option value="akhir_kursus" <?= (isset($_POST['jenis_evaluasi']) && $_POST['jenis_evaluasi'] == 'akhir_kursus') ? 'selected' : '' ?>>
                      Evaluasi Akhir Kursus
                    </option>
                  </select>
                  <div class="form-text">
                    <small><strong>Per Materi:</strong> Evaluasi setelah selesai belajar materi tertentu</small><br>
                    <small><strong>Akhir Kursus:</strong> Evaluasi menyeluruh pengalaman LKP</small>
                  </div>
                </div>

                <!-- Conditional Field: Materi Terkait -->
                <div class="mb-4" id="materiTerkaitWrapper" style="display: none;">
                  <label class="form-label required">Materi Terkait</label>
                  <select name="materi_terkait" id="materiTerkait" class="form-select">
                    <option value="">Pilih Materi</option>
                    <option value="word" <?= (isset($_POST['materi_terkait']) && $_POST['materi_terkait'] == 'word') ? 'selected' : '' ?>>
                      Microsoft Word
                    </option>
                    <option value="excel" <?= (isset($_POST['materi_terkait']) && $_POST['materi_terkait'] == 'excel') ? 'selected' : '' ?>>
                      Microsoft Excel
                    </option>
                    <option value="ppt" <?= (isset($_POST['materi_terkait']) && $_POST['materi_terkait'] == 'ppt') ? 'selected' : '' ?>>
                      Microsoft PowerPoint
                    </option>
                    <option value="internet" <?= (isset($_POST['materi_terkait']) && $_POST['materi_terkait'] == 'internet') ? 'selected' : '' ?>>
                      Internet & Email
                    </option>
                  </select>
                  <div class="form-text"><small>Materi yang akan dievaluasi</small></div>
                </div>

                <div class="mb-4">
                  <label class="form-label required">Aspek yang Dinilai</label>
                  <select name="aspek_dinilai" id="aspekDinilai" class="form-select" required>
                    <option value="">Pilih jenis evaluasi terlebih dahulu</option>
                  </select>
                  <div class="form-text" id="aspekHelp">
                    <small>Pilih jenis evaluasi untuk melihat aspek yang tersedia</small>
                  </div>
                </div>

                <!-- Custom Aspek Input (Hidden by default) -->
                <div class="mb-4" id="customAspekWrapper" style="display: none;">
                  <label class="form-label">Aspek Custom</label>
                  <input type="text" id="customAspek" class="form-control" placeholder="Masukkan aspek yang dinilai...">
                  <div class="form-text"><small>Aspek custom akan disimpan dan dapat digunakan kembali</small></div>
                </div>

                <div class="mb-4">
                  <label class="form-label required">Tipe Jawaban</label>
                  <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="tipe_jawaban" id="tipeSkala" value="skala" 
                           <?= (isset($_POST['tipe_jawaban']) && $_POST['tipe_jawaban'] == 'skala') ? 'checked' : 'checked' ?>>
                    <label class="form-check-label" for="tipeSkala">
                      <strong>Skala 1-5</strong>
                      <div class="form-text"><small>Sangat Buruk (1) - Sangat Baik (5)</small></div>
                    </label>
                  </div>
                  <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="tipe_jawaban" id="tipeIsian" value="isian"
                           <?= (isset($_POST['tipe_jawaban']) && $_POST['tipe_jawaban'] == 'isian') ? 'checked' : '' ?>>
                    <label class="form-check-label" for="tipeIsian">
                      <strong>Isian Bebas</strong>
                      <div class="form-text"><small>Jawaban berupa teks/paragraph</small></div>
                    </label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="tipe_jawaban" id="tipePilihanGanda" value="pilihan_ganda"
                           <?= (isset($_POST['tipe_jawaban']) && $_POST['tipe_jawaban'] == 'pilihan_ganda') ? 'checked' : '' ?>>
                    <label class="form-check-label" for="tipePilihanGanda">
                      <strong>Pilihan Ganda (A-D)</strong>
                      <div class="form-text"><small>Jawaban berupa pilihan A, B, C, D</small></div>
                    </label>
                  </div>
                </div>
              </div>

              <!-- Konten Pertanyaan Section -->
              <div class="col-lg-6">
                <h6 class="section-title mb-4">
                  <i class="bi bi-chat-quote me-2"></i>Konten Pertanyaan
                </h6>

                <div class="mb-4">
                  <label class="form-label required">Pertanyaan</label>
                  <textarea name="pertanyaan" id="pertanyaan" class="form-control" rows="6" required 
                            placeholder="Tuliskan pertanyaan evaluasi yang jelas dan mudah dipahami..."><?= isset($_POST['pertanyaan']) ? htmlspecialchars($_POST['pertanyaan']) : '' ?></textarea>
                  <div class="form-text">
                    <small>Minimal 10 karakter. Gunakan bahasa yang jelas dan tidak bias.</small>
                    <div class="d-flex justify-content-between mt-1">
                      <span id="charCount" class="text-muted">0 karakter</span>
                      <span class="text-muted">Maks 500 karakter</span>
                    </div>
                  </div>
                </div>

                <!-- Pilihan Ganda Options (Hidden by default) -->
                <div class="mb-4" id="pilihanGandaWrapper" style="display: none;">
                  <label class="form-label required">Pilihan Jawaban</label>
                  <div class="row g-2">
                    <div class="col-md-6">
                      <label class="form-label small">A.</label>
                      <input type="text" name="pilihan_a" id="pilihanA" class="form-control" 
                             placeholder="Masukkan pilihan A..." 
                             value="<?= isset($_POST['pilihan_a']) ? htmlspecialchars($_POST['pilihan_a']) : '' ?>">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label small">B.</label>
                      <input type="text" name="pilihan_b" id="pilihanB" class="form-control" 
                             placeholder="Masukkan pilihan B..." 
                             value="<?= isset($_POST['pilihan_b']) ? htmlspecialchars($_POST['pilihan_b']) : '' ?>">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label small">C.</label>
                      <input type="text" name="pilihan_c" id="pilihanC" class="form-control" 
                             placeholder="Masukkan pilihan C..." 
                             value="<?= isset($_POST['pilihan_c']) ? htmlspecialchars($_POST['pilihan_c']) : '' ?>">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label small">D.</label>
                      <input type="text" name="pilihan_d" id="pilihanD" class="form-control" 
                             placeholder="Masukkan pilihan D..." 
                             value="<?= isset($_POST['pilihan_d']) ? htmlspecialchars($_POST['pilihan_d']) : '' ?>">
                    </div>
                  </div>
                  <div class="form-text">
                    <small>Isi semua pilihan jawaban A sampai D. Pilihan akan ditampilkan secara acak kepada siswa.</small>
                  </div>
                </div>

                <!-- Preview Pertanyaan -->
                <div class="mb-4">
                  <label class="form-label">Preview Pertanyaan</label>
                  <div class="border rounded p-3" style="background-color: #f8f9fa; min-height: 120px;">
                    <div id="previewContainer">
                      <div class="text-muted text-center py-3">
                        <i class="bi bi-eye-slash"></i>
                        <br><small>Preview akan muncul saat Anda mengetik pertanyaan</small>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Template Pertanyaan -->
                <div class="mb-4">
                  <label class="form-label">Template Pertanyaan</label>
                  <select class="form-select" id="templatePertanyaan">
                    <option value="">Pilih template untuk membantu...</option>
                  </select>
                  <div class="form-text"><small>Template akan disesuaikan dengan jenis evaluasi dan aspek yang dipilih</small></div>
                </div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="row mt-5 pt-4 border-top">
              <div class="col-12">
                <div class="d-flex justify-content-end gap-3">
                  <a href="index.php" class="btn btn-kembali px-3">
                   Kembali
                  </a>
                  <button type="submit" class="btn btn-simpan px-4">
                    <i class="bi bi-check-lg me-1"></i>Simpan
                  </button>
                </div>
              </div>
            </div>
          </form>
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
  const form = document.getElementById('formTambahPertanyaan');
  const jenisEvaluasi = document.getElementById('jenisEvaluasi');
  const materiTerkaitWrapper = document.getElementById('materiTerkaitWrapper');
  const materiTerkait = document.getElementById('materiTerkait');
  const aspekDinilai = document.getElementById('aspekDinilai');
  const customAspekWrapper = document.getElementById('customAspekWrapper');
  const customAspek = document.getElementById('customAspek');
  const pertanyaan = document.getElementById('pertanyaan');
  const charCount = document.getElementById('charCount');
  const previewContainer = document.getElementById('previewContainer');
  const templatePertanyaan = document.getElementById('templatePertanyaan');
  const pilihanGandaWrapper = document.getElementById('pilihanGandaWrapper');
  const tipePilihanGanda = document.getElementById('tipePilihanGanda');

  const aspekPerMateri = <?= json_encode($aspekPerMateri) ?>;
  const aspekAkhirKursus = <?= json_encode($aspekAkhirKursus) ?>;
  const selectedAspek = "<?= isset($_POST['aspek_dinilai']) ? htmlspecialchars($_POST['aspek_dinilai']) : '' ?>";

  // Conditional field: Show/hide materi terkait
  function toggleMateriTerkait() {
    if (jenisEvaluasi.value === 'per_materi') {
      materiTerkaitWrapper.style.display = 'block';
      materiTerkait.required = true;
      materiTerkaitWrapper.classList.add('show-animation');
    } else {
      materiTerkaitWrapper.style.display = 'none';
      materiTerkait.required = false;
      materiTerkait.value = '';
      materiTerkaitWrapper.classList.remove('show-animation');
    }
    updateAspekOptions();
  }

  // Show/hide pilihan ganda
  function togglePilihanGanda() {
    if (tipePilihanGanda && tipePilihanGanda.checked) {
      pilihanGandaWrapper.style.display = 'block';
      pilihanGandaWrapper.classList.add('show-animation');
      // Set required untuk pilihan ganda
      document.querySelectorAll('#pilihanGandaWrapper input').forEach(input => {
        input.required = true;
      });
    } else {
      pilihanGandaWrapper.style.display = 'none';
      pilihanGandaWrapper.classList.remove('show-animation');
      // Remove required untuk pilihan ganda
      document.querySelectorAll('#pilihanGandaWrapper input').forEach(input => {
        input.required = false;
        input.value = '';
      });
    }
  }

  // Update aspek options based on jenis evaluasi
  function updateAspekOptions() {
    const aspekSelect = document.getElementById('aspekDinilai');
    const aspekHelp = document.getElementById('aspekHelp');
    
    // Clear existing options
    aspekSelect.innerHTML = '';
    
    if (jenisEvaluasi.value === '') {
      aspekSelect.innerHTML = '<option value="">Pilih jenis evaluasi terlebih dahulu</option>';
      aspekHelp.innerHTML = '<small>Pilih jenis evaluasi untuk melihat aspek yang tersedia</small>';
      return;
    }
    
    // Add default option
    aspekSelect.innerHTML = '<option value="">Pilih Aspek yang Dinilai</option>';
    
    // Get appropriate aspek array
    const aspekArray = jenisEvaluasi.value === 'per_materi' ? aspekPerMateri : aspekAkhirKursus;
    
    // Add aspek options
    aspekArray.forEach(aspek => {
      const option = document.createElement('option');
      option.value = aspek;
      option.textContent = aspek;
      if (selectedAspek === aspek) {
        option.selected = true;
      }
      aspekSelect.appendChild(option);
    });
    
    // Add custom option
    const customOption = document.createElement('option');
    customOption.value = 'custom';
    customOption.textContent = 'Tulis Aspek Lain...';
    if (selectedAspek === 'custom') {
      customOption.selected = true;
    }
    aspekSelect.appendChild(customOption);
    
    // Update help text
    if (jenisEvaluasi.value === 'per_materi') {
      aspekHelp.innerHTML = '<small><i class="bi bi-info-circle me-1"></i>Aspek yang fokus pada kualitas dan efektivitas materi pembelajaran</small>';
    } else {
      aspekHelp.innerHTML = '<small><i class="bi bi-info-circle me-1"></i>Aspek yang menilai pengalaman keseluruhan di LKP</small>';
    }
    
    // Trigger custom aspek check
    toggleCustomAspek();
  }

  // Custom aspek handling
  function toggleCustomAspek() {
    if (aspekDinilai.value === 'custom') {
      customAspekWrapper.style.display = 'block';
      customAspek.required = true;
      customAspekWrapper.classList.add('show-animation');
    } else {
      customAspekWrapper.style.display = 'none';
      customAspek.required = false;
      customAspek.value = '';
      customAspekWrapper.classList.remove('show-animation');
    }
  }

  // Character counter for pertanyaan
  function updateCharCount() {
    const count = pertanyaan.value.length;
    charCount.textContent = count + ' karakter';
    
    if (count > 500) {
      charCount.classList.add('text-danger');
      pertanyaan.value = pertanyaan.value.substring(0, 500);
      charCount.textContent = '500 karakter (maksimal)';
    } else {
      charCount.classList.remove('text-danger');
    }
  }

  // Preview pertanyaan
  function updatePreview() {
    const text = pertanyaan.value.trim();
    
    if (text.length > 0) {
      const jenis = jenisEvaluasi.options[jenisEvaluasi.selectedIndex]?.text || 'Evaluasi';
      const aspek = aspekDinilai.value === 'custom' ? customAspek.value : aspekDinilai.value;
      const materi = materiTerkait.options[materiTerkait.selectedIndex]?.text || '';
      
      let previewContent = `
        <div class="preview-header mb-2">
          <small class="badge bg-primary">${jenis}</small>
          ${aspek ? '<small class="badge bg-secondary ms-1">' + aspek + '</small>' : ''}
          ${materi && jenisEvaluasi.value === 'per_materi' ? '<small class="badge bg-success ms-1">' + materi + '</small>' : ''}
        </div>
        <div class="preview-question">
          <strong>Pertanyaan:</strong><br>
          ${text.replace(/\n/g, '<br>')}
        </div>
      `;
      
      // Tambahkan preview pilihan ganda
      if (tipePilihanGanda && tipePilihanGanda.checked) {
        const pilihanA = document.getElementById('pilihanA').value;
        const pilihanB = document.getElementById('pilihanB').value;
        const pilihanC = document.getElementById('pilihanC').value;
        const pilihanD = document.getElementById('pilihanD').value;
        
        if (pilihanA || pilihanB || pilihanC || pilihanD) {
          previewContent += `
            <div class="mt-3">
              <strong>Pilihan Jawaban:</strong>
              <div class="pilihan-preview mt-2">
                ${pilihanA ? '<div class="pilihan-item"><span class="pilihan-label">A.</span> ' + pilihanA + '</div>' : ''}
                ${pilihanB ? '<div class="pilihan-item"><span class="pilihan-label">B.</span> ' + pilihanB + '</div>' : ''}
                ${pilihanC ? '<div class="pilihan-item"><span class="pilihan-label">C.</span> ' + pilihanC + '</div>' : ''}
                ${pilihanD ? '<div class="pilihan-item"><span class="pilihan-label">D.</span> ' + pilihanD + '</div>' : ''}
              </div>
            </div>
          `;
        }
      }
      
      previewContainer.innerHTML = previewContent;
    } else {
      previewContainer.innerHTML = `
        <div class="text-muted text-center py-3">
          <i class="bi bi-eye-slash"></i>
          <br><small>Preview akan muncul saat Anda mengetik pertanyaan</small>
        </div>
      `;
    }
  }

  // Template pertanyaan
  function updateTemplateOptions() {
    const jenis = jenisEvaluasi.value;
    const aspek = aspekDinilai.value;
    
    templatePertanyaan.innerHTML = '<option value="">Pilih template untuk membantu...</option>';
    
    if (!jenis || !aspek || aspek === 'custom') return;
    
    let templates = {};
    
    if (jenis === 'per_materi') {
      templates = {
        'kepuasan': `Bagaimana tingkat kepuasan Anda terhadap ${aspek.toLowerCase()} pada materi yang telah dipelajari?`,
        'kualitas': `Bagaimana Anda menilai ${aspek.toLowerCase()} dalam pembelajaran materi ini?`,
        'kemudahan': `Seberapa mudah Anda memahami materi berdasarkan ${aspek.toLowerCase()} yang diberikan?`,
        'manfaat': `Seberapa bermanfaat ${aspek.toLowerCase()} untuk penguasaan materi yang dipelajari?`,
        'perbaikan': `Apa saran Anda untuk meningkatkan ${aspek.toLowerCase()} pada materi ini?`
      };
    } else {
      templates = {
        'kepuasan': `Bagaimana tingkat kepuasan Anda secara keseluruhan terhadap ${aspek.toLowerCase()} di LKP ini?`,
        'pengalaman': `Bagaimana pengalaman Anda dengan ${aspek.toLowerCase()} selama mengikuti pelatihan?`,
        'penilaian': `Bagaimana penilaian Anda terhadap ${aspek.toLowerCase()} yang disediakan LKP?`,
        'rekomendasi': `Apakah Anda akan merekomendasikan LKP ini berdasarkan ${aspek.toLowerCase()} yang ada?`,
        'harapan': `Apakah ${aspek.toLowerCase()} di LKP ini sesuai dengan harapan Anda?`,
        'saran': `Apa saran Anda untuk meningkatkan ${aspek.toLowerCase()} di LKP ini?`
      };
    }
    
    Object.entries(templates).forEach(([key, template]) => {
      const option = document.createElement('option');
      option.value = key;
      option.textContent = template;
      templatePertanyaan.appendChild(option);
    });
  }

  function applyTemplate() {
    const templateKey = templatePertanyaan.value;
    if (!templateKey) return;
    
    const selectedOption = templatePertanyaan.options[templatePertanyaan.selectedIndex];
    if (selectedOption) {
      pertanyaan.value = selectedOption.textContent;
      updateCharCount();
      updatePreview();
      templatePertanyaan.value = '';
    }
  }

  // Event listeners
  jenisEvaluasi.addEventListener('change', function() {
    toggleMateriTerkait();
    updatePreview();
  });

  aspekDinilai.addEventListener('change', function() {
    toggleCustomAspek();
    updateTemplateOptions();
    updatePreview();
  });

  customAspek.addEventListener('input', function() {
    // Update aspek value for form submission
    if (aspekDinilai.value === 'custom') {
      aspekDinilai.setAttribute('data-custom-value', this.value);
    }
    updatePreview();
  });

  pertanyaan.addEventListener('input', function() {
    updateCharCount();
    updatePreview();
  });

  templatePertanyaan.addEventListener('change', applyTemplate);

  // Event listeners untuk radio buttons tipe jawaban
  document.querySelectorAll('input[name="tipe_jawaban"]').forEach(radio => {
    radio.addEventListener('change', function() {
      togglePilihanGanda();
      updatePreview();
    });
  });

  // Event listeners untuk pilihan ganda inputs
  document.querySelectorAll('#pilihanGandaWrapper input').forEach(input => {
    input.addEventListener('input', updatePreview);
  });

  // Form submission handling
  form.addEventListener('submit', function(e) {
    // Handle custom aspek
    if (aspekDinilai.value === 'custom') {
      if (!customAspek.value.trim()) {
        e.preventDefault();
        alert('Mohon isi aspek custom yang akan dinilai!');
        customAspek.focus();
        return;
      }
      // Create hidden input for custom aspek
      const hiddenInput = document.createElement('input');
      hiddenInput.type = 'hidden';
      hiddenInput.name = 'aspek_dinilai';
      hiddenInput.value = customAspek.value.trim();
      form.appendChild(hiddenInput);
      aspekDinilai.removeAttribute('name'); // Remove original select name
    }

    // Validasi untuk pilihan ganda
    if (tipePilihanGanda && tipePilihanGanda.checked) {
      const pilihanA = document.getElementById('pilihanA').value.trim();
      const pilihanB = document.getElementById('pilihanB').value.trim();
      const pilihanC = document.getElementById('pilihanC').value.trim();
      const pilihanD = document.getElementById('pilihanD').value.trim();
      
      if (!pilihanA || !pilihanB || !pilihanC || !pilihanD) {
        e.preventDefault();
        alert('Mohon lengkapi semua pilihan jawaban A, B, C, dan D!');
        return;
      }
    }

    // Validate pertanyaan length
    if (pertanyaan.value.trim().length < 10) {
      e.preventDefault();
      alert('Pertanyaan minimal 10 karakter!');
      pertanyaan.focus();
      return;
    }

    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Menyimpan...';
  });

  // Initialize on page load
  updateAspekOptions(); // Initialize aspek options first
  toggleMateriTerkait();
  toggleCustomAspek();
  togglePilihanGanda();
  updateCharCount();
  updatePreview();

  // Initialize tooltips
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
});
</script>

<style>
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

.preview-header .badge {
  font-size: 0.7em;
}

.preview-question {
  color: #333;
  line-height: 1.5;
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
  margin-bottom: 0.5rem;
  font-size: 0.875rem;
}

.pilihan-label {
  font-weight: 600;
  margin-right: 0.5rem;
  color: #0d6efd;
  min-width: 25px;
}

.form-check-label .form-text {
  margin-top: 0.25rem;
  margin-left: 0;
}

#pertanyaan:focus {
  border-color: #86b7fe;
  box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.section-title {
  color: #495057;
  border-bottom: 2px solid #e9ecef;
  padding-bottom: 0.5rem;
}

#pilihanGandaWrapper .form-label.small {
  font-weight: 600;
  color: #495057;
  margin-bottom: 0.25rem;
}

#pilihanGandaWrapper .form-control {
  border-color: #dee2e6;
  transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

#pilihanGandaWrapper .form-control:focus {
  border-color: #86b7fe;
  box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.required::after {
  content: " *";
  color: #dc3545;
}
</style>
</body>
</html>