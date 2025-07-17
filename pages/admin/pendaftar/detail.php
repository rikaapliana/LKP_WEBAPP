<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'pendaftar'; 
$baseURL = '../';

$id = $_GET['id'] ?? '';

// Validasi input
if (empty($id) || !is_numeric($id)) {
    $_SESSION['error'] = 'ID pendaftar tidak valid';
    header('Location: index.php');
    exit();
}

// Ambil data pendaftar
$query = "SELECT * FROM pendaftar WHERE id_pendaftar = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    $_SESSION['error'] = 'Data pendaftar tidak ditemukan';
    header('Location: index.php');
    exit();
}

$pendaftar = mysqli_fetch_assoc($result);

// Ambil data kelas untuk modal transfer (jika status terverifikasi)
if ($pendaftar['status_pendaftaran'] === 'Terverifikasi') {
    $kelasQuery = "SELECT k.*, g.nama_gelombang, 
                   (SELECT COUNT(*) FROM siswa s WHERE s.id_kelas = k.id_kelas AND s.status_aktif = 'aktif') as siswa_terdaftar
                   FROM kelas k 
                   LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang 
                   WHERE g.status = 'aktif'
                   ORDER BY k.nama_kelas";
    $kelasResult = mysqli_query($conn, $kelasQuery);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Detail Pendaftar - <?= htmlspecialchars($pendaftar['nama_pendaftar']) ?></title>
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
                <h2 class="page-title mb-1">DETAIL PENDAFTAR</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="#">Manajemen Siswa</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="index.php">Data Pendaftar</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Detail</li>
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

        <!-- Profile Header Card -->
        <div class="card content-card mb-4">
          <div class="card-body p-4">
            <div class="row align-items-start">
              <div class="col-auto">
                <div class="profile-photo">
                  <?php if($pendaftar['pas_foto'] && file_exists('../../../uploads/pas_foto_pendaftar/'.$pendaftar['pas_foto'])): ?>
                    <img src="../../../uploads/pas_foto_pendaftar/<?= $pendaftar['pas_foto'] ?>" 
                         alt="Foto <?= htmlspecialchars($pendaftar['nama_pendaftar']) ?>" 
                         class="rounded-circle" 
                         style="width: 100px; height: 100px; object-fit: cover; border: 4px solid #f8f9fa;">
                  <?php else: ?>
                    <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center" 
                         style="width: 100px; height: 100px; border: 4px solid #f8f9fa;">
                      <i class="bi bi-person-fill text-white" style="font-size: 3rem;"></i>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
              <div class="col">
                <div class="d-flex justify-content-between align-items-start">
                  <div>
                    <h3 class="mb-1 fw-bold"><?= htmlspecialchars($pendaftar['nama_pendaftar']) ?></h3>
                    <p class="text-muted mb-2">NIK: <?= htmlspecialchars($pendaftar['nik'] ?? '-') ?></p>
                    <div class="d-flex gap-3">
                      <span class="badge bg-info fs-6 px-3 py-2">
                        <i class="bi bi-person-plus me-1"></i>
                        Pendaftar
                      </span>
                      <?php 
                      $status = $pendaftar['status_pendaftaran'];
                      $badgeClass = '';
                      $icon = '';
                      switch($status) {
                        case 'Belum di Verifikasi':
                          $badgeClass = 'bg-warning';
                          $icon = 'bi-clock';
                          break;
                        case 'Terverifikasi':
                          $badgeClass = 'bg-success';
                          $icon = 'bi-shield-check';
                          break;
                        case 'Diterima':
                          $badgeClass = 'bg-primary';
                          $icon = 'bi-person-check';
                          break;
                        case 'Ditolak':
                          $badgeClass = 'bg-danger';
                          $icon = 'bi-x-circle';
                          break;
                        default:
                          $badgeClass = 'bg-secondary';
                          $icon = 'bi-circle-fill';
                      }
                      ?>
                      <span class="badge <?= $badgeClass ?> fs-6 px-3 py-2">
                        <i class="<?= $icon ?> me-1"></i>
                        <?= htmlspecialchars($status) ?>
                      </span>
                    </div>
                  </div>
                  <div class="d-flex gap-3">
                    <a href="index.php" class="btn btn-kembali px-4">
                      Kembali
                    </a>
                    
                    <?php if($pendaftar['status_pendaftaran'] == 'Belum di Verifikasi'): ?>
                      <button type="button" 
                              class="btn btn-edit px-4" 
                              data-bs-toggle="modal" 
                              data-bs-target="#modalVerifikasi">
                        <i class="bi bi-shield-check me-1"></i>Verifikasi
                      </button>
                    <?php endif; ?>
                    
                    <?php if($pendaftar['status_pendaftaran'] == 'Terverifikasi'): ?>
                      <button type="button" 
                              class="btn btn-success px-4" 
                              data-bs-toggle="modal" 
                              data-bs-target="#modalTransfer">
                        <i class="bi bi-arrow-right-circle me-1"></i>Transfer ke Siswa
                      </button>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row">
          <!-- Data Pendaftar -->
          <div class="col-lg-8">
            <!-- Data Pribadi -->
            <div class="card content-card mb-4">
              <div class="section-header">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-person-fill me-2"></i>Data Pribadi
                </h5>
              </div>
              <div class="card-body">
                <div class="row g-4">
                  <div class="col-md-6">
                    <div class="info-item">
                      <label class="form-label small text-muted mb-1">Nama Lengkap</label>
                      <div class="fw-medium"><?= htmlspecialchars($pendaftar['nama_pendaftar']) ?></div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="info-item">
                      <label class="form-label small text-muted mb-1">NIK</label>
                      <div class="fw-medium"><?= htmlspecialchars($pendaftar['nik'] ?? '-') ?></div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="info-item">
                      <label class="form-label small text-muted mb-1">Tempat Lahir</label>
                      <div class="fw-medium"><?= htmlspecialchars($pendaftar['tempat_lahir'] ?? '-') ?></div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="info-item">
                      <label class="form-label small text-muted mb-1">Tanggal Lahir</label>
                      <div class="fw-medium">
                        <?= $pendaftar['tanggal_lahir'] ? date('d F Y', strtotime($pendaftar['tanggal_lahir'])) : '-' ?>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="info-item">
                      <label class="form-label small text-muted mb-1">Jenis Kelamin</label>
                      <div class="fw-medium"><?= htmlspecialchars($pendaftar['jenis_kelamin'] ?? '-') ?></div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="info-item">
                      <label class="form-label small text-muted mb-1">Pendidikan Terakhir</label>
                      <div class="fw-medium"><?= htmlspecialchars($pendaftar['pendidikan_terakhir'] ?? '-') ?></div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="info-item">
                      <label class="form-label small text-muted mb-1">Status Pendaftaran</label>
                      <div class="fw-medium">
                        <span class="badge <?= $badgeClass ?> px-2 py-1">
                          <i class="<?= $icon ?> me-1"></i>
                          <?= htmlspecialchars($status) ?>
                        </span>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="info-item">
                      <label class="form-label small text-muted mb-1">Jam Pilihan</label>
                      <div class="fw-medium">
                        <span class="badge bg-info-soft text-info px-2 py-1">
                          <i class="bi bi-clock me-1"></i>
                          <?= htmlspecialchars($pendaftar['jam_pilihan'] ?? '-') ?>
                        </span>
                      </div>
                    </div>
                  </div>
                  <div class="col-12">
                    <div class="info-item">
                      <label class="form-label small text-muted mb-1">Alamat Lengkap</label>
                      <div class="fw-medium"><?= htmlspecialchars($pendaftar['alamat_lengkap'] ?? '-') ?></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Data Kontak -->
            <div class="card content-card mb-4">
              <div class="section-header">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-telephone-fill me-2"></i>Informasi Kontak
                </h5>
              </div>
              <div class="card-body">
                <div class="row g-4">
                  <div class="col-md-6">
                    <div class="info-item">
                      <label class="form-label small text-muted mb-1">No. Handphone</label>
                      <div class="fw-medium">
                        <?php if($pendaftar['no_hp']): ?>
                          <a href="tel:<?= htmlspecialchars($pendaftar['no_hp']) ?>" class="text-decoration-none">
                            <i class="bi bi-phone me-1"></i><?= htmlspecialchars($pendaftar['no_hp']) ?>
                          </a>
                        <?php else: ?>
                          <span class="text-muted">-</span>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="info-item">
                      <label class="form-label small text-muted mb-1">Email</label>
                      <div class="fw-medium">
                        <?php if($pendaftar['email']): ?>
                          <a href="mailto:<?= htmlspecialchars($pendaftar['email']) ?>" class="text-decoration-none">
                            <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($pendaftar['email']) ?>
                          </a>
                        <?php else: ?>
                          <span class="text-muted">-</span>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Sidebar Info -->
          <div class="col-lg-4">
            <!-- Info Pendaftar -->
            <div class="card content-card">
              <div class="section-header">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-info-circle me-2"></i>Info & Dokumen
                </h5>
              </div>
              <div class="card-body">
                <!-- Info Status -->
                <div class="mb-4">
                  <h6 class="fw-bold mb-3">
                    <i class="bi bi-shield-check me-2"></i>Status Pendaftaran
                  </h6>
                  <div class="text-center p-3">
                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                      <i class="<?= $icon ?> text-secondary fs-4"></i>
                    </div>
                    <h6 class="fw-bold mb-1"><?= htmlspecialchars($status) ?></h6>
                    <small class="text-muted">
                      <?php 
                      switch($status) {
                        case 'Belum di Verifikasi':
                          echo 'Menunggu review admin';
                          break;
                        case 'Terverifikasi':
                          echo 'Siap untuk transfer ke siswa';
                          break;
                        case 'Diterima':
                          echo 'Sudah menjadi siswa aktif';
                          break;
                        case 'Ditolak':
                          echo 'Tidak memenuhi persyaratan';
                          break;
                        default:
                          echo '-';
                      }
                      ?>
                    </small>
                  </div>
                </div>

                <!-- Divider -->
                <hr class="my-4">
                
                <!-- Dokumen -->
                <div>
                  <h6 class="fw-bold mb-3">
                    <i class="bi bi-files me-2"></i>Dokumen
                  </h6>
                  <div class="list-group list-group-flush">
                    <!-- KTP -->
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                      <div>
                        <i class="bi bi-card-text text-danger me-2"></i>
                        <span class="text-dark">KTP</span>
                      </div>
                      <?php if($pendaftar['ktp'] && file_exists('../../../uploads/ktp_pendaftar/'.$pendaftar['ktp'])): ?>
                        <a href="../../../uploads/ktp_pendaftar/<?= $pendaftar['ktp'] ?>" 
                           target="_blank" 
                           class="btn btn-sm btn-outline-danger"
                           download="KTP_<?= htmlspecialchars($pendaftar['nama_pendaftar']) ?>.pdf">
                          <i class="bi bi-download"></i>
                        </a>
                      <?php else: ?>
                        <small class="text-muted">Belum upload</small>
                      <?php endif; ?>
                    </div>

                    <!-- Kartu Keluarga -->
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                      <div>
                        <i class="bi bi-people-fill text-info me-2"></i>
                        <span class="text-dark">Kartu Keluarga</span>
                      </div>
                      <?php if($pendaftar['kk'] && file_exists('../../../uploads/kk_pendaftar/'.$pendaftar['kk'])): ?>
                        <a href="../../../uploads/kk_pendaftar/<?= $pendaftar['kk'] ?>" 
                           target="_blank" 
                           class="btn btn-sm btn-outline-info"
                           download="KK_<?= htmlspecialchars($pendaftar['nama_pendaftar']) ?>.pdf">
                          <i class="bi bi-download"></i>
                        </a>
                      <?php else: ?>
                        <small class="text-muted">Belum upload</small>
                      <?php endif; ?>
                    </div>

                    <!-- Ijazah -->
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 border-bottom-0">
                      <div>
                        <i class="bi bi-award-fill text-success me-2"></i>
                        <span class="text-dark">Ijazah</span>
                      </div>
                      <?php if($pendaftar['ijazah'] && file_exists('../../../uploads/ijazah_pendaftar/'.$pendaftar['ijazah'])): ?>
                        <a href="../../../uploads/ijazah_pendaftar/<?= $pendaftar['ijazah'] ?>" 
                           target="_blank" 
                           class="btn btn-sm btn-outline-success"
                           download="Ijazah_<?= htmlspecialchars($pendaftar['nama_pendaftar']) ?>.pdf">
                          <i class="bi bi-download"></i>
                        </a>
                      <?php else: ?>
                        <small class="text-muted">Belum upload</small>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>

                <!-- Divider -->
                <hr class="my-4">

                <!-- Quick Actions -->
                <div>
                  <h6 class="fw-bold mb-3">
                    <i class="bi bi-lightning me-2"></i>Quick Actions
                  </h6>
                  <div class="d-grid gap-2">
                    <?php if($pendaftar['status_pendaftaran'] == 'Belum di Verifikasi'): ?>
                      <button type="button" 
                              class="btn btn-primary btn-sm" 
                              data-bs-toggle="modal" 
                              data-bs-target="#modalVerifikasi">
                        <i class="bi bi-shield-check me-2"></i>Verifikasi Pendaftar
                      </button>
                    <?php endif; ?>
                    
                    <?php if($pendaftar['status_pendaftaran'] == 'Terverifikasi'): ?>
                      <button type="button" 
                              class="btn btn-success btn-sm" 
                              data-bs-toggle="modal" 
                              data-bs-target="#modalTransfer">
                        <i class="bi bi-arrow-right-circle me-2"></i>Transfer ke Siswa
                      </button>
                    <?php endif; ?>
                    
                    <?php if($pendaftar['status_pendaftaran'] !== 'Diterima'): ?>
                      <button type="button" 
                              class="btn btn-outline-danger btn-sm" 
                              data-bs-toggle="modal" 
                              data-bs-target="#modalHapus">
                        <i class="bi bi-trash me-2"></i>Hapus Data
                      </button>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Verifikasi -->
  <?php if($pendaftar['status_pendaftaran'] == 'Belum di Verifikasi'): ?>
  <div class="modal fade" id="modalVerifikasi" tabindex="-1" aria-labelledby="modalVerifikasiLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-primary text-white border-0">
          <div class="w-100">
            <h5 class="modal-title" id="modalVerifikasiLabel">
              <i class="bi bi-shield-check me-2"></i>Verifikasi Pendaftar
            </h5>
            <small>Review dan ubah status pendaftaran</small>
          </div>
        </div>
        
        <div class="modal-body">
          <form method="POST" action="update_status.php">
            <input type="hidden" name="id_pendaftar" value="<?= $pendaftar['id_pendaftar'] ?>">
            
            <div class="mb-3">
              <label class="form-label">Status Baru:</label>
              <select name="status_pendaftaran" class="form-select" required>
                <option value="Terverifikasi">Terverifikasi</option>
                <option value="Ditolak">Ditolak</option>
              </select>
            </div>
            
            <div class="mb-3">
              <label class="form-label">Catatan (opsional):</label>
              <textarea name="catatan" class="form-control" rows="3" placeholder="Tambahkan catatan jika diperlukan..."></textarea>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i>Update Status
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Modal Transfer -->
  <?php if($pendaftar['status_pendaftaran'] == 'Terverifikasi'): ?>
  <div class="modal fade" id="modalTransfer" tabindex="-1" aria-labelledby="modalTransferLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-success text-white border-0">
          <div class="w-100">
            <h5 class="modal-title" id="modalTransferLabel">
              <i class="bi bi-arrow-right-circle me-2"></i>Transfer ke Siswa
            </h5>
            <small>Jadikan pendaftar sebagai siswa aktif</small>
          </div>
        </div>
        
        <div class="modal-body">
          <form method="POST" action="transfer.php">
            <input type="hidden" name="id_pendaftar" value="<?= $pendaftar['id_pendaftar'] ?>">
            
            <div class="mb-3">
              <label class="form-label">Pilih Kelas:</label>
              <select name="id_kelas" class="form-select" required>
                <option value="">-- Pilih Kelas --</option>
                <?php 
                if (isset($kelasResult) && $kelasResult) {
                  while($kelas = mysqli_fetch_assoc($kelasResult)): 
                    $sisa_kapasitas = $kelas['kapasitas'] - $kelas['siswa_terdaftar'];
                ?>
                  <option value="<?= $kelas['id_kelas'] ?>" 
                          <?= ($sisa_kapasitas <= 0) ? 'disabled' : '' ?>>
                    <?= htmlspecialchars($kelas['nama_kelas']) ?> 
                    (<?= htmlspecialchars($kelas['nama_gelombang']) ?>) 
                    - Sisa: <?= $sisa_kapasitas ?>/<?= $kelas['kapasitas'] ?>
                    <?= ($sisa_kapasitas <= 0) ? ' - PENUH' : '' ?>
                  </option>
                <?php endwhile; } ?>
              </select>
              <small class="text-muted">Pilih kelas sesuai dengan jam pilihan pendaftar: <strong><?= htmlspecialchars($pendaftar['jam_pilihan'] ?? '-') ?></strong></small>
            </div>
            
            <div class="alert alert-info">
              <i class="bi bi-info-circle me-2"></i>
              <strong>Proses Transfer:</strong>
              <ul class="mb-0 mt-2">
                <li>Data pendaftar akan dipindah ke tabel siswa</li>
                <li>Username dan password otomatis dibuat</li>
                <li>Email credentials dikirim ke pendaftar</li>
                <li>Status berubah menjadi "Diterima"</li>
              </ul>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-success">
                <i class="bi bi-arrow-right-circle me-1"></i>Transfer ke Siswa
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Modal Hapus -->
  <div class="modal fade" id="modalHapus" tabindex="-1" aria-labelledby="modalHapusLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-danger text-white border-0">
          <div class="w-100">
            <h5 class="modal-title" id="modalHapusLabel">
              <i class="bi bi-exclamation-triangle me-2"></i>Konfirmasi Hapus
            </h5>
            <small>Tindakan ini tidak dapat dibatalkan</small>
          </div>
        </div>
        
        <div class="modal-body">
          <p>Anda yakin ingin menghapus data pendaftar:</p>
          
          <div class="text-center mb-3">
            <?php if($pendaftar['pas_foto'] && file_exists('../../../uploads/pas_foto_pendaftar/'.$pendaftar['pas_foto'])): ?>
              <img src="../../../uploads/pas_foto_pendaftar/<?= $pendaftar['pas_foto'] ?>" 
                   alt="Foto Pendaftar" 
                   class="rounded-circle mb-2"
                   style="width: 80px; height: 80px; object-fit: cover;">
            <?php else: ?>
              <div class="bg-secondary rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center" 
                   style="width: 80px; height: 80px;">
                <i class="bi bi-person-fill text-white fs-2"></i>
              </div>
            <?php endif; ?>
            <h6 class="fw-bold"><?= htmlspecialchars($pendaftar['nama_pendaftar']) ?></h6>
            <small class="text-muted">NIK: <?= htmlspecialchars($pendaftar['nik'] ?? '-') ?></small>
          </div>
          
          <div class="alert alert-warning">
            <i class="bi bi-info-circle me-2"></i>
            Data dan semua file terkait akan dihapus permanen
          </div>
          
          <?php if($pendaftar['status_pendaftaran'] === 'Diterima'): ?>
          <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Peringatan:</strong> Pendaftar ini sudah diterima sebagai siswa. Tidak dapat dihapus.
          </div>
          <?php endif; ?>
        </div>
        
        <div class="modal-footer border-0">
          <div class="row g-2 w-100">
            <div class="col-6">
              <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">
                <i class="bi bi-x-lg"></i> Batal
              </button>
            </div>
            <div class="col-6">
              <?php if($pendaftar['status_pendaftaran'] !== 'Diterima'): ?>
                <button type="button" 
                        class="btn btn-danger w-100" 
                        onclick="confirmDelete(<?= $pendaftar['id_pendaftar'] ?>, '<?= htmlspecialchars($pendaftar['nama_pendaftar']) ?>')">
                  <i class="bi bi-trash"></i> Hapus
                </button>
              <?php else: ?>
                <button type="button" class="btn btn-danger w-100" disabled>
                  <i class="bi bi-shield-x"></i> Tidak Dapat Dihapus
                </button>
              <?php endif; ?>
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
  // Fungsi konfirmasi hapus
  function confirmDelete(id, nama) {
    // Tutup modal Bootstrap terlebih dahulu
    const modal = bootstrap.Modal.getInstance(document.getElementById('modalHapus'));
    if (modal) {
      modal.hide();
    }
    
    // Tampilkan loading pada tombol
    const deleteBtn = document.querySelector('#modalHapus .btn-danger:not([disabled])');
    if (deleteBtn) {
      deleteBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Memproses...';
      deleteBtn.disabled = true;
    }
    
    // Tunggu modal tertutup, lalu redirect
    setTimeout(() => {
      window.location.href = `hapus.php?id=${id}&confirm=delete`;
    }, 1000);
  }

  // Initialize tooltips
  document.addEventListener('DOMContentLoaded', function() {
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