<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'profil'; 
$baseURL = '../';

$user_id = $_SESSION['user_id'];

// Ambil data admin berdasarkan user_id
$query = "SELECT a.id_admin, a.id_user, a.nama, a.no_hp, a.email, 
                 COALESCE(a.foto, '') as foto, u.username, u.created_at
          FROM admin a 
          JOIN user u ON a.id_user = u.id_user 
          WHERE a.id_user = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$admin_data = $result->fetch_assoc();

if (!$admin_data) {
    $_SESSION['error'] = "Data admin tidak ditemukan!";
    header("Location: ../dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profil Admin - <?= htmlspecialchars($admin_data['nama']) ?></title>
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
            <!-- Left: Hamburger + Page Info -->
            <div class="d-flex align-items-center flex-grow-1">
              <!-- Sidebar Toggle Button -->
              <button class="btn btn-link text-dark p-2 me-3 sidebar-toggle" type="button" id="sidebarToggle">
                <i class="bi bi-list fs-4"></i>
              </button>
              
              <!-- Page Title & Breadcrumb -->
              <div class="page-info">
                <h2 class="page-title mb-1">PROFIL ADMIN</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Profil</li>
                  </ol>
                </nav>
              </div>
            </div>
            
            <!-- Right: Optional Info -->
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

        <!-- Profile Header Card -->
        <div class="card content-card mb-4">
          <div class="card-body p-4">
            <div class="row align-items-start">
              <div class="col-auto">
                <div class="profile-photo">
                  <?php if(!empty($admin_data['foto']) && file_exists('../../../uploads/profile_pictures/'.$admin_data['foto'])): ?>
                    <img src="../../../uploads/profile_pictures/<?= $admin_data['foto'] ?>" 
                         alt="Foto <?= htmlspecialchars($admin_data['nama']) ?>" 
                         class="rounded-circle" 
                         style="width: 120px; height: 120px; object-fit: cover; border: 4px solid #f8f9fa;">
                  <?php else: ?>
                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" 
                         style="width: 120px; height: 120px; border: 4px solid #f8f9fa;">
                      <i class="bi bi-person-fill text-white" style="font-size: 4rem;"></i>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
              <div class="col">
                <div class="d-flex justify-content-between align-items-start">
                  <div>
                    <h3 class="mb-1 fw-bold"><?= htmlspecialchars($admin_data['nama']) ?></h3>
                    <p class="text-muted mb-2">Username: <?= htmlspecialchars($admin_data['username']) ?></p>
                    <div class="d-flex gap-3">
                      <span class="badge bg-primary fs-6 px-3 py-2">
                        <i class="bi bi-shield-check me-1"></i>
                        Administrator
                      </span>
                      <span class="badge bg-success fs-6 px-3 py-2">
                        <i class="bi bi-circle-fill me-1"></i>
                        Aktif
                      </span>
                    </div>
                  </div>
                  <div class="d-flex gap-3">
                    <a href="../dashboard.php" class="btn btn-kembali px-4">
                      Kembali
                    </a>
                    <a href="edit.php" class="btn btn-edit px-4">
                      <i class="bi bi-pencil me-1"></i>Edit Profil
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row">
          <!-- Data Profil -->
          <div class="col-lg-8">
            <div class="card content-card mb-4">
              <div class="section-header">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-person-fill me-2"></i>Informasi Personal
                </h5>
              </div>
              <div class="card-body">
                <div class="row g-4">
                  <div class="col-md-6">
                    <div class="info-item">
                      <label class="form-label small text-muted mb-1">Nama Lengkap</label>
                      <div class="fw-medium"><?= htmlspecialchars($admin_data['nama']) ?></div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="info-item">
                      <label class="form-label small text-muted mb-1">Username</label>
                      <div class="fw-medium"><?= htmlspecialchars($admin_data['username']) ?></div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="info-item">
                      <label class="form-label small text-muted mb-1">Email</label>
                      <div class="fw-medium"><?= htmlspecialchars($admin_data['email']) ?></div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="info-item">
                      <label class="form-label small text-muted mb-1">No. HP</label>
                      <div class="fw-medium"><?= htmlspecialchars($admin_data['no_hp']) ?></div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="info-item">
                      <label class="form-label small text-muted mb-1">Role</label>
                      <div class="fw-medium">
                        <span class="badge bg-primary px-2 py-1">
                          <i class="bi bi-shield-check me-1"></i>
                          Administrator
                        </span>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="info-item">
                      <label class="form-label small text-muted mb-1">Bergabung Sejak</label>
                      <div class="fw-medium"><?= date('d F Y', strtotime($admin_data['created_at'])) ?></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Sidebar Info -->
          <div class="col-lg-4">
            <!-- Info Statistik -->
            <div class="card content-card mb-4">
              <div class="section-header">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-info-circle me-2"></i>Status Akun
                </h5>
              </div>
              <div class="card-body">
                <div class="text-center p-3">
                  <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                       style="width: 80px; height: 80px;">
                    <i class="bi bi-shield-check text-success" style="font-size: 2.5rem;"></i>
                  </div>
                  <h5 class="fw-bold mb-2 text-success">Akun Administrator</h5>
                  <p class="text-muted mb-3">Memiliki akses penuh ke semua fitur sistem</p>
                  
                  <div class="row text-center">
                    <div class="col-6">
                      <div class="p-2 bg-light rounded">
                        <i class="bi bi-key text-secondary fs-4 mb-1 d-block"></i>
                        <small class="text-muted fw-medium">Full Access</small>
                      </div>
                    </div>
                    <div class="col-6">
                      <div class="p-2 bg-light rounded">
                        <i class="bi bi-shield-lock text-secondary fs-4 mb-1 d-block"></i>
                        <small class="text-muted fw-medium">Secure</small>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../../assets/js/scripts.js"></script>

  <script>
    // Initialize tooltips
    document.addEventListener('DOMContentLoaded', function() {
      var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
      });
    });
  </script>
</body>
</html>