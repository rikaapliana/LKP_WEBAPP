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
$filterType = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'all';

// Build WHERE clause for filters
$whereConditions = ["m.id_kelas = ?"];
$params = [$siswaData['id_kelas']];

if (!empty($searchTerm)) {
    $whereConditions[] = "(m.judul LIKE ? OR m.deskripsi LIKE ? OR i.nama LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
}

if ($filterType != 'all') {
    if ($filterType == 'with_file') {
        $whereConditions[] = "m.file_materi IS NOT NULL AND m.file_materi != ''";
    } elseif ($filterType == 'without_file') {
        $whereConditions[] = "(m.file_materi IS NULL OR m.file_materi = '')";
    }
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
$orderClause = "ORDER BY m.id_materi DESC";

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
          $orderClause";

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

// Statistik materi untuk siswa
$statsQuery = "SELECT 
    COUNT(*) as total_materi,
    SUM(CASE WHEN m.file_materi IS NOT NULL AND m.file_materi != '' THEN 1 ELSE 0 END) as dengan_file,
    SUM(CASE WHEN m.file_materi IS NULL OR m.file_materi = '' THEN 1 ELSE 0 END) as tanpa_file
    FROM materi m
    WHERE m.id_kelas = ?";

$statsStmt = $conn->prepare($statsQuery);
$statsStmt->bind_param("i", $siswaData['id_kelas']);
$statsStmt->execute();
$statsData = $statsStmt->get_result()->fetch_assoc();

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

        <!-- Main Content -->
        <div class="card content-card">
          <!-- Materi Card -->
              <div class="card content-card">
              <div class="section-header">
                <div class="row align-items-center">
                  <div class="col-md-6">
                    <h5 class="mb-0 text-dark">
                      <i class="bi bi-files me-2"></i>Materi Pembelajaran
                    </h5>
                  </div>
                </div>
              </div>

              <!-- Filter Controls -->
              <div class="p-3 border-bottom">
                <form method="GET" id="filterForm">
                  <div class="row align-items-center">
                    <div class="col-md-8">
                      <div class="d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center">
                          <label for="searchInput" class="me-2 mb-0">
                            <small class="text-muted">Cari:</small>
                          </label>
                          <input type="search" name="search" id="searchInput" class="form-control form-control-sm" style="width: 200px;" value="<?= htmlspecialchars($searchTerm) ?>"/>
                        </div>
                        
                        <div class="d-flex align-items-center">
                          <label for="filterType" class="me-2 mb-0">
                            <small class="text-muted">Filter:</small>
                          </label>
                          <select class="form-select form-select-sm" name="filter_type" id="filterType" style="width: auto;">
                            <option value="all" <?= ($filterType == 'all') ? 'selected' : '' ?>>Semua Materi</option>
                            <option value="with_file" <?= ($filterType == 'with_file') ? 'selected' : '' ?>>Ada File</option>
                            <option value="without_file" <?= ($filterType == 'without_file') ? 'selected' : '' ?>>Tanpa File</option>
                          </select>
                        </div>
                      </div>
                    </div>
                  </div>
                </form>
              </div>

              <!-- Materi Table -->
              <div class="table-responsive">
                <table class="custom-table mb-0">
                  <thead>
                    <tr>
                      <th width="5%">No</th>
                      <th width="40%">Judul Materi</th>
                      <th width="25%">Instruktur</th>
                      <th width="15%">Format File</th>
                      <th width="15%">Aksi</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ($result && mysqli_num_rows($result) > 0): ?>
                      <?php $no = 1; ?>
                      <?php while ($materi = mysqli_fetch_assoc($result)): ?>
                        <tr>
                          <td class="align-middle">
                            <span class="fw-medium text-muted"><?= $no++ ?></span>
                          </td>
                          
                          <td class="align-middle">
                            <div>
                              <div class="d-flex align-items-center gap-2 mb-1">
                                <h6 class="mb-0 fw-medium text-dark"><?= htmlspecialchars($materi['judul']) ?></h6>
                                
                                <?php if(isNewFile($materi['id_materi'], $siswaData['id_kelas'])): ?>
                                  <span class="badge bg-success px-2 py-1">
                                    <i class="bi bi-star-fill me-1"></i>Baru
                                  </span>
                                <?php endif; ?>
                              </div>
                              
                              <?php if($materi['deskripsi']): ?>
                                <small class="text-muted d-block">
                                  <?= htmlspecialchars(strlen($materi['deskripsi']) > 80 ? substr($materi['deskripsi'], 0, 80) . '...' : $materi['deskripsi']) ?>
                                </small>
                              <?php endif; ?>
                            </div>
                          </td>
                          
                          <td class="align-middle">
                            <div class="d-flex align-items-center">
                              <i class="bi bi-person-circle me-2 text-muted"></i>
                              <span><?= htmlspecialchars($materi['nama_instruktur']) ?></span>
                            </div>
                          </td>
                          
                          <td class="align-middle">
                            <?php if($materi['file_materi'] && $materi['file_materi'] != ''): ?>
                              <?php
                              $extension = strtolower(pathinfo($materi['file_materi'], PATHINFO_EXTENSION));
                              $extensionUpper = strtoupper($extension);
                              ?>
                              <div class="d-flex align-items-center">
                                <i class="<?= getFileIcon($materi['file_materi']) ?> me-2 fs-5"></i>
                                <span class="badge bg-primary px-2 py-1">
                                  <?= $extensionUpper ?>
                                </span>
                              </div>
                            <?php else: ?>
                              <span class="badge bg-secondary px-2 py-1">
                                <i class="bi bi-file-earmark-x me-1"></i>Tidak Ada
                              </span>
                            <?php endif; ?>
                          </td>
                          
                          <td class="align-middle">
                            <?php if($materi['file_materi'] && $materi['file_materi'] != ''): ?>
                              <a href="../../../uploads/materi/<?= htmlspecialchars($materi['file_materi']) ?>" 
                                 target="_blank" 
                                 class="btn btn-primary btn-sm"
                                 title="Download file materi">
                                <i class="bi bi-download me-1"></i>
                                Download
                              </a>
                            <?php else: ?>
                              <span class="text-muted small">-</span>
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endwhile; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="5" class="text-center">
                          <div class="empty-state py-5">
                            <i class="bi bi-files display-4 text-muted mb-3 d-block"></i>
                            <h5>
                              <?php if (!empty($searchTerm) || $filterType != 'all'): ?>
                                Tidak Ada Materi yang Sesuai Filter
                              <?php else: ?>
                                Belum Ada Materi
                              <?php endif; ?>
                            </h5>
                            <p class="mb-3 text-muted">
                              <?php if (!empty($searchTerm) || $filterType != 'all'): ?>
                                Coba ubah filter atau kata kunci pencarian
                              <?php else: ?>
                                Materi pembelajaran belum tersedia untuk kelas Anda
                              <?php endif; ?>
                            </p>
                            <a href="?" class="btn btn-primary">
                              <i class="bi bi-arrow-clockwise me-2"></i>Lihat Semua Materi
                            </a>
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
  </div>

  <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../../assets/js/scripts.js"></script>

  <script>
  document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('filterForm');
    const searchInput = document.getElementById('searchInput');
    const filterType = document.getElementById('filterType');
    
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
    
    // Auto submit when filter changes
    if (filterType) {
      filterType.addEventListener('change', function() {
        form.submit();
      });
    }
    
    // Download tracking
    const downloadLinks = document.querySelectorAll('a[href*="uploads/materi/"]');
    downloadLinks.forEach(function(link) {
      link.addEventListener('click', function() {
        console.log('File downloaded:', this.href);
      });
    });
  });
  </script>
</body>
</html>