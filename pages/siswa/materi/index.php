<?php
session_start();
require_once '../../../includes/auth.php';
requireSiswaAuth(); // Hanya siswa yang bisa akses

include '../../../includes/db.php';
$activePage = 'materi'; 
$baseURL = '../';

// Ambil data siswa yang sedang login
$stmt = $conn->prepare("SELECT s.*, k.nama_kelas, g.nama_gelombang, i.nama as nama_instruktur 
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

// Filter parameters
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Build WHERE clause for filters
$whereConditions = ["m.id_kelas = ?"];
$params = [$siswaData['id_kelas']];

if (!empty($searchTerm)) {
    $whereConditions[] = "(m.judul LIKE ? OR m.deskripsi LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Get materi data
$query = "SELECT m.*, 
          i.nama as nama_instruktur,
          CASE 
            WHEN m.file_materi IS NOT NULL AND m.file_materi != '' THEN 'Ada File'
            ELSE 'Tidak Ada File'
          END as status_file
          FROM materi m 
          LEFT JOIN instruktur i ON m.id_instruktur = i.id_instruktur
          $whereClause
          ORDER BY m.id_materi DESC";

$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    $types = str_repeat('s', count($params));
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
} else {
    $result = false;
}

// Function untuk mendapatkan icon file
function getFileIcon($filename) {
    if (!$filename || $filename == '') return 'bi-file-earmark text-muted';
    
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    switch($extension) {
        case 'pdf':
            return 'bi-file-pdf text-danger';
        case 'doc':
        case 'docx':
            return 'bi-file-word text-primary';
        case 'xls':
        case 'xlsx':
            return 'bi-file-excel text-success';
        case 'ppt':
        case 'pptx':
            return 'bi-file-ppt text-warning';
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
            return 'bi-file-image text-info';
        case 'zip':
        case 'rar':
            return 'bi-file-zip text-secondary';
        default:
            return 'bi-file-earmark text-muted';
    }
}

// Function untuk format ukuran file
function formatFileSize($filename) {
    if (!$filename || $filename == '') return '-';
    
    $filepath = '../../../uploads/materi/' . $filename;
    if (file_exists($filepath)) {
        $size = filesize($filepath);
        if ($size >= 1024 * 1024) {
            return number_format($size / (1024 * 1024), 1) . ' MB';
        } elseif ($size >= 1024) {
            return number_format($size / 1024, 1) . ' KB';
        } else {
            return $size . ' B';
        }
    }
    return 'N/A';
}

// Function untuk cek apakah file baru (5 materi terbaru)
function isNewFile($id_materi, $siswaKelas) {
    global $conn;
    $query = "SELECT id_materi FROM materi WHERE id_kelas = ? ORDER BY id_materi DESC LIMIT 5";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $siswaKelas);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $newIds = [];
    while ($row = $result->fetch_assoc()) {
        $newIds[] = $row['id_materi'];
    }
    
    return in_array($id_materi, $newIds);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Materi Kelas - Siswa</title>
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
                <h2 class="page-title mb-1">MATERI KELAS</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Materi Kelas</li>
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

        <!-- Main Content Card -->
        <div class="card content-card">
          <div class="section-header">
            <div class="row align-items-center">
              <div class="col-md-6">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-files me-2"></i>Materi Pembelajaran
                </h5>
              </div>
              <div class="col-md-6 text-end">
                <small class="text-muted">
                  Kelas: <strong><?= htmlspecialchars($siswaData['nama_kelas']) ?></strong>
                  <?php if($siswaData['nama_gelombang']): ?>
                    (<?= htmlspecialchars($siswaData['nama_gelombang']) ?>)
                  <?php endif; ?>
                </small>
              </div>
            </div>
          </div>

          <!-- Search Controls -->
          <div class="p-3 border-bottom">
            <form method="GET" id="filterForm">
              <div class="row align-items-center">
                <div class="col-md-8">
                  <div class="d-flex align-items-center search-container">
                    <label for="searchInput" class="me-2 mb-0">
                      <small class="text-muted">Search:</small>
                    </label>
                    <input type="search" name="search" id="searchInput" class="form-control form-control-sm" value="<?= htmlspecialchars($searchTerm) ?>"/>
                  </div>
                </div>
            </form>
          </div>

          <!-- Materi List -->
          <div class="card-body p-0">
            <?php if ($result && mysqli_num_rows($result) > 0): ?>
              <div class="list-group list-group-flush">
                <?php while ($materi = mysqli_fetch_assoc($result)): ?>
                  <div class="list-group-item border-0 py-3">
                    <div class="row align-items-center">
                      <div class="col-auto">
                        <div class="file-icon-container text-center" style="width: 50px;">
                          <i class="<?= getFileIcon($materi['file_materi']) ?> fs-1"></i>
                        </div>
                      </div>
                      
                      <div class="col">
                        <div class="d-flex align-items-start justify-content-between">
                          <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2 mb-1">
                              <h6 class="mb-0 fw-medium"><?= htmlspecialchars($materi['judul']) ?></h6>
                              
                              <?php if(isNewFile($materi['id_materi'], $siswaData['id_kelas'])): ?>
                                <span class="badge bg-success px-2 py-1 small">
                                  <i class="bi bi-star-fill me-1"></i>Baru
                                </span>
                              <?php endif; ?>
                            </div>
                            
                            <?php if($materi['deskripsi']): ?>
                              <p class="text-muted mb-2 small"><?= htmlspecialchars($materi['deskripsi']) ?></p>
                            <?php endif; ?>
                            
                            <div class="d-flex align-items-center gap-3 small text-muted">
                              <span>
                                <i class="bi bi-person me-1"></i>
                                <?= htmlspecialchars($materi['nama_instruktur']) ?>
                              </span>
                              <span>
                                <i class="bi bi-hash me-1"></i>
                                ID: <?= $materi['id_materi'] ?>
                              </span>
                              <?php if($materi['file_materi'] && $materi['file_materi'] != ''): ?>
                                <span>
                                  <i class="bi bi-hdd me-1"></i>
                                  <?= formatFileSize($materi['file_materi']) ?>
                                </span>
                              <?php endif; ?>
                            </div>
                          </div>
                          
                          <div class="flex-shrink-0 ms-3">
                            <?php if($materi['file_materi'] && $materi['file_materi'] != ''): ?>
                              <a href="../../../uploads/materi/<?= htmlspecialchars($materi['file_materi']) ?>" 
                                 target="_blank" 
                                 class="btn btn-primary btn-sm"
                                 title="Download file materi">
                                <i class="bi bi-download me-1"></i>
                                Download
                              </a>
                            <?php else: ?>
                              <span class="badge bg-secondary px-2 py-1">
                                <i class="bi bi-file-earmark-x me-1"></i>Tidak Ada File
                              </span>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endwhile; ?>
              </div>
            <?php else: ?>
              <div class="text-center py-5">
                <div class="empty-state">
                  <i class="bi bi-files display-4 text-muted mb-3 d-block"></i>
                  <h5>
                    <?php if (!empty($searchTerm)): ?>
                      Tidak Ada Materi yang Sesuai Pencarian
                    <?php else: ?>
                      Belum Ada Materi
                    <?php endif; ?>
                  </h5>
                  <p class="mb-3 text-muted">
                    <?php if (!empty($searchTerm)): ?>
                      Coba ubah kata kunci pencarian untuk hasil yang lebih tepat
                    <?php else: ?>
                      Materi pembelajaran belum tersedia untuk kelas Anda. Hubungi instruktur untuk informasi lebih lanjut.
                    <?php endif; ?>
                  </p>
                  <div class="btn-group">
                    <a href="../dashboard.php" class="btn btn-primary">
                      <i class="bi bi-house me-2"></i>Kembali ke Dashboard
                    </a>
                  </div>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../../assets/js/scripts.js"></script>

  <script>
  document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('filterForm');
    const searchInput = document.getElementById('searchInput');
    
    let searchTimeout;
    
    // Auto submit on search with delay
    if (searchInput) {
      searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
          form.submit();
        }, 500);
      });
    }
    
    // Download tracking
    const downloadLinks = document.querySelectorAll('a[href*="uploads/materi/"]');
    downloadLinks.forEach(function(link) {
      link.addEventListener('click', function() {
        console.log('File downloaded:', this.href);
        showDownloadNotification();
      });
    });
    
    function showDownloadNotification() {
      const notification = document.createElement('div');
      notification.className = 'alert alert-success alert-dismissible fade show position-fixed';
      notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 250px;';
      notification.innerHTML = `
        <i class="bi bi-check-circle me-2"></i>
        File sedang diunduh...
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      `;
      
      document.body.appendChild(notification);
      
      setTimeout(() => {
        if (notification.parentNode) {
          notification.remove();
        }
      }, 3000);
    }
  });
  </script>
</body>
</html>