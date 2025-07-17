<?php
session_start();
require_once '../../../../includes/auth.php';
requireAdminAuth();

include '../../../../includes/db.php';
$activePage = 'pengaturan';
$baseURL = '../../';

// Validasi parameter ID
$id_gelombang = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_gelombang <= 0) {
    $_SESSION['error'] = "ID Gelombang tidak valid!";
    header('Location: index.php');
    exit();
}

// Proses form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_pengaturan') {
        $status_pendaftaran = $_POST['status_pendaftaran'];
        $kuota_maksimal = (int)$_POST['kuota_maksimal'];
        $tanggal_buka = $_POST['tanggal_buka'] ?: NULL;
        $tanggal_tutup = $_POST['tanggal_tutup'] ?: NULL;
        $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
        
        try {
            // Cek apakah sudah ada pengaturan untuk gelombang ini
            $checkQuery = "SELECT id_pengaturan FROM pengaturan_pendaftaran WHERE id_gelombang = $id_gelombang";
            $checkResult = mysqli_query($conn, $checkQuery);
            
            if (mysqli_num_rows($checkResult) > 0) {
                // Update existing
                $updateQuery = "UPDATE pengaturan_pendaftaran SET 
                               status_pendaftaran = '$status_pendaftaran',
                               kuota_maksimal = $kuota_maksimal,
                               tanggal_buka = " . ($tanggal_buka ? "'$tanggal_buka'" : "NULL") . ",
                               tanggal_tutup = " . ($tanggal_tutup ? "'$tanggal_tutup'" : "NULL") . ",
                               keterangan = '$keterangan',
                               updated_at = NOW()
                               WHERE id_gelombang = $id_gelombang";
            } else {
                // Insert new
                $updateQuery = "INSERT INTO pengaturan_pendaftaran 
                               (id_gelombang, status_pendaftaran, kuota_maksimal, tanggal_buka, tanggal_tutup, keterangan, created_at, updated_at) 
                               VALUES ($id_gelombang, '$status_pendaftaran', $kuota_maksimal, " . 
                               ($tanggal_buka ? "'$tanggal_buka'" : "NULL") . ", " . 
                               ($tanggal_tutup ? "'$tanggal_tutup'" : "NULL") . ", " . 
                               "'$keterangan', NOW(), NOW())";
            }
            
            if (mysqli_query($conn, $updateQuery)) {
                $_SESSION['success'] = "Pengaturan formulir berhasil diperbarui!";
            } else {
                $_SESSION['error'] = "Gagal memperbarui pengaturan: " . mysqli_error($conn);
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
        
        header('Location: detail.php?id=' . $id_gelombang);
        exit();
    }
}

// Ambil data gelombang dan pengaturan
$query = "SELECT g.*, 
                 p.status_pendaftaran,
                 p.kuota_maksimal,
                 p.tanggal_buka,
                 p.tanggal_tutup,
                 p.keterangan,
                 p.created_at as pengaturan_created,
                 p.updated_at as pengaturan_updated,
                 CASE 
                   WHEN g.status = 'aktif' THEN (
                     SELECT COUNT(*) FROM kelas k 
                     INNER JOIN siswa s ON k.id_kelas = s.id_kelas 
                     WHERE k.id_gelombang = g.id_gelombang AND s.status_aktif = 'aktif'
                   )
                   WHEN g.status = 'dibuka' THEN (
                     SELECT COUNT(*) FROM pendaftar pd 
                     WHERE pd.status_pendaftaran != 'Belum di Verifikasi'
                   )
                   ELSE 0
                 END as jumlah_pendaftar_siswa
          FROM gelombang g 
          LEFT JOIN pengaturan_pendaftaran p ON g.id_gelombang = p.id_gelombang
          WHERE g.id_gelombang = $id_gelombang";

$result = mysqli_query($conn, $query);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    $_SESSION['error'] = "Data gelombang tidak ditemukan!";
    header('Location: index.php');
    exit();
}

// Ambil data kelas untuk gelombang ini
$kelasQuery = "SELECT k.*, i.nama as nama_instruktur,
                      COUNT(s.id_siswa) as jumlah_siswa
               FROM kelas k
               LEFT JOIN instruktur i ON k.id_instruktur = i.id_instruktur
               LEFT JOIN siswa s ON k.id_kelas = s.id_kelas AND s.status_aktif = 'aktif'
               WHERE k.id_gelombang = $id_gelombang
               GROUP BY k.id_kelas
               ORDER BY k.nama_kelas";
$kelasResult = mysqli_query($conn, $kelasQuery);

// Ambil data pendaftar untuk gelombang ini (dengan filter id_gelombang)
$pendaftarQuery = "SELECT COUNT(*) as total_pendaftar,
                          COUNT(CASE WHEN status_pendaftaran = 'Belum di Verifikasi' THEN 1 END) as belum_verifikasi,
                          COUNT(CASE WHEN status_pendaftaran = 'Terverifikasi' THEN 1 END) as terverifikasi,
                          COUNT(CASE WHEN status_pendaftaran = 'Diterima' THEN 1 END) as diterima
                   FROM pendaftar 
                   WHERE id_gelombang = $id_gelombang";
$pendaftarResult = mysqli_query($conn, $pendaftarQuery);
$pendaftarStats = mysqli_fetch_assoc($pendaftarResult);

// Status summary
$statusSummary = [
    'total_kapasitas' => 0,
    'total_terisi' => 0,
    'presentase_terisi' => 0
];

// Hitung total kapasitas dan terisi
if ($data['status'] === 'aktif') {
    $kapasitasQuery = "SELECT SUM(k.kapasitas) as total_kapasitas,
                              COUNT(s.id_siswa) as total_siswa
                       FROM kelas k
                       LEFT JOIN siswa s ON k.id_kelas = s.id_kelas AND s.status_aktif = 'aktif'
                       WHERE k.id_gelombang = $id_gelombang";
    $kapasitasResult = mysqli_query($conn, $kapasitasQuery);
    $kapasitasData = mysqli_fetch_assoc($kapasitasResult);
    
    $statusSummary['total_kapasitas'] = $kapasitasData['total_kapasitas'] ?? 0;
    $statusSummary['total_terisi'] = $kapasitasData['total_siswa'] ?? 0;
} else {
    $statusSummary['total_kapasitas'] = $data['kuota_maksimal'] ?? 0;
    $statusSummary['total_terisi'] = $data['jumlah_pendaftar_siswa'] ?? 0;
}

if ($statusSummary['total_kapasitas'] > 0) {
    $statusSummary['presentase_terisi'] = round(($statusSummary['total_terisi'] / $statusSummary['total_kapasitas']) * 100, 1);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Detail Formulir - <?= htmlspecialchars($data['nama_gelombang']) ?> - LKP Pradata Komputer</title>
  <link rel="icon" type="image/png" href="../../../../assets/img/favicon.png"/>
  <link rel="stylesheet" href="../../../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../../../assets/css/fonts.css" />
  <link rel="stylesheet" href="../../../../assets/css/styles.css" />
  
  <style>
    .detail-card {
      border: none;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      border-radius: 12px;
      overflow: hidden;
    }
    
    .detail-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 2rem;
    }
    
    .status-badge-lg {
      font-size: 0.9rem;
      padding: 0.5rem 1rem;
      border-radius: 20px;
    }
    
    .progress-custom {
      height: 8px;
      border-radius: 4px;
      background-color: #e9ecef;
    }
    
    .progress-bar-custom {
      border-radius: 4px;
      transition: width 0.3s ease;
    }
    
    .info-item {
      border-left: 4px solid #007bff;
      padding-left: 1rem;
      margin-bottom: 1rem;
    }
    
    .info-item h6 {
      color: #495057;
      margin-bottom: 0.25rem;
    }
    
    .info-item p {
      color: #6c757d;
      margin-bottom: 0;
    }
    
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
      margin-bottom: 2rem;
    }
    
    .stat-item {
      text-align: center;
      padding: 1.5rem;
      background: #f8f9fa;
      border-radius: 8px;
      border: 1px solid #dee2e6;
    }
    
    .stat-number {
      font-size: 2rem;
      font-weight: bold;
      color: #007bff;
    }
    
    .stat-label {
      color: #6c757d;
      font-size: 0.9rem;
    }
    
    .table-custom {
      border-radius: 8px;
      overflow: hidden;
    }
    
    .table-custom thead {
      background-color: #f8f9fa;
    }
    
    .action-buttons {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
    }
    
    @media (max-width: 768px) {
      .detail-header {
        padding: 1.5rem;
      }
      
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }
      
      .action-buttons {
        flex-direction: column;
      }
      
      .action-buttons .btn {
        width: 100%;
      }
    }
    
    .form-section {
      background: #f8f9fa;
      padding: 1.5rem;
      border-radius: 8px;
      margin-bottom: 2rem;
    }
    
    .section-title {
      color: #495057;
      font-size: 1.1rem;
      font-weight: 600;
      margin-bottom: 1rem;
      padding-bottom: 0.5rem;
      border-bottom: 2px solid #dee2e6;
    }
  </style>
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
                <h2 class="page-title mb-1">DETAIL FORMULIR PENDAFTARAN</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="../index.php">Pengaturan</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="index.php">Formulir Pendaftaran</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Detail</li>
                  </ol>
                </nav>
              </div>
            </div>
            
            <div class="d-flex align-items-center">
              <div class="action-buttons">
                <a href="index.php" class="btn btn-secondary btn-sm">
                  <i class="bi bi-arrow-left"></i>
                  Kembali
                </a>
                
                <?php if ($data['status_pendaftaran'] === 'dibuka'): ?>
                  <a href="../../../../pendaftaran.php" class="btn btn-success btn-sm" target="_blank">
                    <i class="bi bi-eye"></i>
                    Lihat Formulir
                  </a>
                <?php endif; ?>
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

        <!-- Header Card -->
        <div class="card detail-card mb-4">
          <div class="detail-header">
            <div class="row align-items-center">
              <div class="col-md-8">
                <h3 class="mb-2"><?= htmlspecialchars($data['nama_gelombang']) ?></h3>
                <div class="d-flex gap-3 align-items-center">
                  <span class="badge bg-light text-dark px-3 py-2">
                    <i class="bi bi-calendar me-1"></i>
                    Tahun <?= $data['tahun'] ?>
                  </span>
                  <span class="badge bg-info px-3 py-2">
                    <i class="bi bi-hash me-1"></i>
                    Gelombang Ke-<?= $data['gelombang_ke'] ?>
                  </span>
                  <span class="badge bg-<?= $data['status'] === 'aktif' ? 'success' : ($data['status'] === 'dibuka' ? 'warning' : 'secondary') ?> px-3 py-2">
                    <i class="bi bi-<?= $data['status'] === 'aktif' ? 'play-circle' : ($data['status'] === 'dibuka' ? 'door-open' : 'stop-circle') ?> me-1"></i>
                    <?= ucfirst($data['status']) ?>
                  </span>
                </div>
              </div>
              <div class="col-md-4 text-md-end">
                <div class="text-white-50 small">Status Formulir</div>
                <?php if ($data['status_pendaftaran'] === 'dibuka'): ?>
                  <span class="status-badge-lg badge bg-success">
                    <i class="bi bi-door-open me-1"></i>Dibuka
                  </span>
                <?php elseif ($data['status_pendaftaran'] === 'ditutup'): ?>
                  <span class="status-badge-lg badge bg-danger">
                    <i class="bi bi-door-closed me-1"></i>Ditutup
                  </span>
                <?php else: ?>
                  <span class="status-badge-lg badge bg-warning">
                    <i class="bi bi-gear me-1"></i>Belum Diatur
                  </span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid">
          <div class="stat-item">
            <div class="stat-number"><?= number_format($statusSummary['total_terisi']) ?></div>
            <div class="stat-label">
              <?= $data['status'] === 'aktif' ? 'Siswa Aktif' : 'Pendaftar' ?>
            </div>
          </div>
          
          <div class="stat-item">
            <div class="stat-number"><?= number_format($statusSummary['total_kapasitas']) ?></div>
            <div class="stat-label">
              <?= $data['status'] === 'aktif' ? 'Total Kapasitas' : 'Kuota Maksimal' ?>
            </div>
          </div>
          
          <div class="stat-item">
            <div class="stat-number"><?= $statusSummary['presentase_terisi'] ?>%</div>
            <div class="stat-label">Tingkat Pengisian</div>
          </div>
          
          <div class="stat-item">
            <div class="stat-number"><?= mysqli_num_rows($kelasResult) ?></div>
            <div class="stat-label">Jumlah Kelas</div>
          </div>
        </div>

        <!-- Progress Bar -->
        <div class="card mb-4">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h6 class="mb-0">Progress Pengisian</h6>
              <small class="text-muted">
                <?= number_format($statusSummary['total_terisi']) ?> dari <?= number_format($statusSummary['total_kapasitas']) ?>
              </small>
            </div>
            <div class="progress progress-custom">
              <div class="progress-bar progress-bar-custom bg-<?= $statusSummary['presentase_terisi'] > 80 ? 'success' : ($statusSummary['presentase_terisi'] > 50 ? 'warning' : 'info') ?>" 
                   style="width: <?= $statusSummary['presentase_terisi'] ?>%">
              </div>
            </div>
          </div>
        </div>

        <div class="row">
          <!-- Pengaturan Formulir -->
          <div class="col-xl-6 mb-4">
            <div class="card">
              <div class="card-header">
                <h5 class="mb-0">
                  <i class="bi bi-gear me-2"></i>Pengaturan Formulir
                </h5>
              </div>
              <div class="card-body">
                <form method="POST" id="formPengaturan">
                  <input type="hidden" name="action" value="update_pengaturan">
                  
                  <div class="form-section">
                    <div class="section-title">Status & Kuota</div>
                    
                    <div class="row">
                      <div class="col-md-6">
                        <div class="mb-3">
                          <label class="form-label">Status Formulir</label>
                          <select name="status_pendaftaran" class="form-select" required>
                            <option value="dibuka" <?= $data['status_pendaftaran'] === 'dibuka' ? 'selected' : '' ?>>
                              Dibuka
                            </option>
                            <option value="ditutup" <?= $data['status_pendaftaran'] === 'ditutup' ? 'selected' : '' ?>>
                              Ditutup
                            </option>
                          </select>
                        </div>
                      </div>
                      
                      <div class="col-md-6">
                        <div class="mb-3">
                          <label class="form-label">Kuota Maksimal</label>
                          <input type="number" name="kuota_maksimal" class="form-control" 
                                 value="<?= $data['kuota_maksimal'] ?: 50 ?>" 
                                 min="1" max="1000" required>
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <div class="form-section">
                    <div class="section-title">Periode Pendaftaran</div>
                    
                    <div class="row">
                      <div class="col-md-6">
                        <div class="mb-3">
                          <label class="form-label">Tanggal Buka</label>
                          <input type="datetime-local" name="tanggal_buka" class="form-control" 
                                 value="<?= $data['tanggal_buka'] ? date('Y-m-d\TH:i', strtotime($data['tanggal_buka'])) : '' ?>">
                          <div class="form-text">Kosongkan untuk tidak ada batas waktu</div>
                        </div>
                      </div>
                      
                      <div class="col-md-6">
                        <div class="mb-3">
                          <label class="form-label">Tanggal Tutup</label>
                          <input type="datetime-local" name="tanggal_tutup" class="form-control" 
                                 value="<?= $data['tanggal_tutup'] ? date('Y-m-d\TH:i', strtotime($data['tanggal_tutup'])) : '' ?>">
                          <div class="form-text">Kosongkan untuk tidak ada batas waktu</div>
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <div class="form-section">
                    <div class="section-title">Keterangan</div>
                    
                    <div class="mb-3">
                      <label class="form-label">Keterangan Tambahan</label>
                      <textarea name="keterangan" class="form-control" rows="3" 
                                placeholder="Tambahkan keterangan khusus untuk formulir ini..."><?= htmlspecialchars($data['keterangan'] ?? '') ?></textarea>
                    </div>
                  </div>
                  
                  <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">
                      <i class="bi bi-save me-2"></i>Simpan Perubahan
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <!-- Informasi Detail -->
          <div class="col-xl-6 mb-4">
            <div class="card">
              <div class="card-header">
                <h5 class="mb-0">
                  <i class="bi bi-info-circle me-2"></i>Informasi Detail
                </h5>
              </div>
              <div class="card-body">
                <div class="info-item">
                  <h6>Periode Pendaftaran</h6>
                  <p>
                    <?php if ($data['tanggal_buka'] && $data['tanggal_tutup']): ?>
                      <?= date('d/m/Y H:i', strtotime($data['tanggal_buka'])) ?> - 
                      <?= date('d/m/Y H:i', strtotime($data['tanggal_tutup'])) ?>
                    <?php elseif ($data['tanggal_buka']): ?>
                      Mulai: <?= date('d/m/Y H:i', strtotime($data['tanggal_buka'])) ?>
                    <?php elseif ($data['tanggal_tutup']): ?>
                      Sampai: <?= date('d/m/Y H:i', strtotime($data['tanggal_tutup'])) ?>
                    <?php else: ?>
                      Tidak ada batas waktu
                    <?php endif; ?>
                  </p>
                </div>

                <div class="info-item">
                  <h6>Status Pendaftaran</h6>
                  <p>
                    <?php if ($data['status_pendaftaran'] === 'dibuka'): ?>
                      <i class="bi bi-check-circle text-success me-1"></i>
                      Formulir sedang dibuka untuk pendaftaran
                    <?php elseif ($data['status_pendaftaran'] === 'ditutup'): ?>
                      <i class="bi bi-x-circle text-danger me-1"></i>
                      Formulir ditutup untuk pendaftaran
                    <?php else: ?>
                      <i class="bi bi-exclamation-triangle text-warning me-1"></i>
                      Belum ada pengaturan formulir
                    <?php endif; ?>
                  </p>
                </div>

                <div class="info-item">
                  <h6>Terakhir Diperbarui</h6>
                  <p>
                    <?php if ($data['pengaturan_updated']): ?>
                      <i class="bi bi-clock me-1"></i>
                      <?= date('d/m/Y H:i', strtotime($data['pengaturan_updated'])) ?>
                    <?php else: ?>
                      <i class="bi bi-dash-circle me-1"></i>
                      Belum pernah diperbarui
                    <?php endif; ?>
                  </p>
                </div>

                <?php if ($data['keterangan']): ?>
                <div class="info-item">
                  <h6>Keterangan</h6>
                  <p><?= nl2br(htmlspecialchars($data['keterangan'])) ?></p>
                </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Tabel Kelas -->
        <?php if ($data['status'] === 'aktif'): ?>
        <div class="card">
          <div class="card-header">
            <h5 class="mb-0">
              <i class="bi bi-grid-3x3 me-2"></i>Daftar Kelas
            </h5>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-custom">
                <thead>
                  <tr>
                    <th>No</th>
                    <th>Nama Kelas</th>
                    <th>Instruktur</th>
                    <th>Kapasitas</th>
                    <th>Siswa Aktif</th>
                    <th>Tingkat Pengisian</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (mysqli_num_rows($kelasResult) > 0): ?>
                    <?php 
                    $no = 1;
                    mysqli_data_seek($kelasResult, 0); // Reset pointer
                    while ($kelas = mysqli_fetch_assoc($kelasResult)): 
                      $persentase = $kelas['kapasitas'] > 0 ? round(($kelas['jumlah_siswa'] / $kelas['kapasitas']) * 100, 1) : 0;
                    ?>
                      <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($kelas['nama_kelas']) ?></td>
                        <td>
                          <?php if ($kelas['nama_instruktur']): ?>
                            <i class="bi bi-person-check text-success me-1"></i>
                            <?= htmlspecialchars($kelas['nama_instruktur']) ?>
                          <?php else: ?>
                            <i class="bi bi-person-dash text-muted me-1"></i>
                            <span class="text-muted">Belum ditentukan</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <span class="badge bg-info"><?= $kelas['kapasitas'] ?> orang</span>
                        </td>
                        <td>
                          <span class="badge bg-primary"><?= $kelas['jumlah_siswa'] ?> siswa</span>
                        </td>
                        <td>
                          <div class="d-flex align-items-center">
                            <div class="progress progress-custom me-2" style="width: 100px;">
                              <div class="progress-bar bg-<?= $persentase > 80 ? 'success' : ($persentase > 50 ? 'warning' : 'info') ?>" 
                                   style="width: <?= $persentase ?>%"></div>
                            </div>
                            <small><?= $persentase ?>%</small>
                          </div>
                        </td>
                      </tr>
                    <?php endwhile; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="6" class="text-center py-4">
                        <i class="bi bi-grid-3x3 display-4 text-muted mb-3 d-block"></i>
                        <h6>Belum Ada Kelas</h6>
                        <p class="text-muted mb-0">Kelas belum dibuat untuk gelombang ini</p>
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Statistik Pendaftar (untuk status dibuka) -->
        <?php if ($data['status'] === 'dibuka'): ?>
        <div class="card mt-4">
          <div class="card-header">
            <h5 class="mb-0">
              <i class="bi bi-people me-2"></i>Statistik Pendaftar
            </h5>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-3">
                <div class="stat-item">
                  <div class="stat-number text-primary"><?= number_format($pendaftarStats['total_pendaftar']) ?></div>
                  <div class="stat-label">Total Pendaftar</div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="stat-item">
                  <div class="stat-number text-warning"><?= number_format($pendaftarStats['belum_verifikasi']) ?></div>
                  <div class="stat-label">Belum Verifikasi</div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="stat-item">
                  <div class="stat-number text-info"><?= number_format($pendaftarStats['terverifikasi']) ?></div>
                  <div class="stat-label">Terverifikasi</div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="stat-item">
                  <div class="stat-number text-success"><?= number_format($pendaftarStats['diterima']) ?></div>
                  <div class="stat-label">Diterima</div>
                </div>
              </div>
            </div>
            
            <div class="mt-4">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Progress Verifikasi</h6>
                <small class="text-muted">
                  <?= $pendaftarStats['total_pendaftar'] > 0 ? round(($pendaftarStats['terverifikasi'] + $pendaftarStats['diterima']) / $pendaftarStats['total_pendaftar'] * 100, 1) : 0 ?>% terverifikasi
                </small>
              </div>
              <div class="progress progress-custom">
                <div class="progress-bar bg-warning" 
                     style="width: <?= $pendaftarStats['total_pendaftar'] > 0 ? ($pendaftarStats['belum_verifikasi'] / $pendaftarStats['total_pendaftar'] * 100) : 0 ?>%"></div>
                <div class="progress-bar bg-info" 
                     style="width: <?= $pendaftarStats['total_pendaftar'] > 0 ? ($pendaftarStats['terverifikasi'] / $pendaftarStats['total_pendaftar'] * 100) : 0 ?>%"></div>
                <div class="progress-bar bg-success" 
                     style="width: <?= $pendaftarStats['total_pendaftar'] > 0 ? ($pendaftarStats['diterima'] / $pendaftarStats['total_pendaftar'] * 100) : 0 ?>%"></div>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="card mt-4">
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <h6>Aksi Cepat</h6>
                <div class="d-flex gap-2 flex-wrap">
                  <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Kembali ke Daftar
                  </a>
                  
                  <?php if ($data['status_pendaftaran'] === 'dibuka'): ?>
                    <a href="../../../../pendaftaran.php" class="btn btn-success" target="_blank">
                      <i class="bi bi-eye me-1"></i>Lihat Formulir Publik
                    </a>
                  <?php endif; ?>
                  
                  <button type="button" class="btn btn-info" onclick="printDetail()">
                    <i class="bi bi-printer me-1"></i>Cetak Detail
                  </button>
                </div>
              </div>
              
              <div class="col-md-6">
                <h6>Navigasi</h6>
                <div class="d-flex gap-2 flex-wrap">
                  <a href="../gelombang/index.php" class="btn btn-outline-primary">
                    <i class="bi bi-layers me-1"></i>Kelola Gelombang
                  </a>
                  
                  <?php if ($data['status'] === 'aktif'): ?>
                    <a href="../../kelas/index.php" class="btn btn-outline-secondary">
                      <i class="bi bi-grid-3x3 me-1"></i>Kelola Kelas
                    </a>
                  <?php endif; ?>
                  
                  <a href="../../pendaftar/index.php" class="btn btn-outline-info">
                    <i class="bi bi-people me-1"></i>Kelola Pendaftar
                  </a>
                </div>
              </div>
            </div>
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
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function(tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Auto-hide alerts
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            if (alert.classList.contains('alert-success')) {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            }
        });
        
        // Form validation
        const form = document.getElementById('formPengaturan');
        if (form) {
            form.addEventListener('submit', function(e) {
                const tanggalBuka = form.querySelector('input[name="tanggal_buka"]').value;
                const tanggalTutup = form.querySelector('input[name="tanggal_tutup"]').value;
                
                if (tanggalBuka && tanggalTutup) {
                    const buka = new Date(tanggalBuka);
                    const tutup = new Date(tanggalTutup);
                    
                    if (buka >= tutup) {
                        e.preventDefault();
                        alert('Tanggal buka harus lebih awal dari tanggal tutup!');
                        return false;
                    }
                }
                
                // Show loading state
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';
            });
        }
    });
    
    // Print functionality
    function printDetail() {
        const printContent = document.createElement('div');
        printContent.innerHTML = `
            <div style="padding: 20px; font-family: Arial, sans-serif;">
                <h2>Detail Formulir Pendaftaran</h2>
                <h3><?= htmlspecialchars($data['nama_gelombang']) ?></h3>
                <p><strong>Tahun:</strong> <?= $data['tahun'] ?></p>
                <p><strong>Status:</strong> <?= ucfirst($data['status']) ?></p>
                <p><strong>Status Formulir:</strong> <?= $data['status_pendaftaran'] ? ucfirst($data['status_pendaftaran']) : 'Belum diatur' ?></p>
                <p><strong>Kuota Maksimal:</strong> <?= $data['kuota_maksimal'] ?: 'Tidak ditentukan' ?></p>
                <p><strong>Total Terisi:</strong> <?= number_format($statusSummary['total_terisi']) ?></p>
                <p><strong>Presentase Pengisian:</strong> <?= $statusSummary['presentase_terisi'] ?>%</p>
                <?php if ($data['tanggal_buka'] || $data['tanggal_tutup']): ?>
                <p><strong>Periode:</strong> 
                    <?php if ($data['tanggal_buka']): ?>
                        <?= date('d/m/Y H:i', strtotime($data['tanggal_buka'])) ?>
                    <?php endif; ?>
                    <?php if ($data['tanggal_buka'] && $data['tanggal_tutup']): ?> - <?php endif; ?>
                    <?php if ($data['tanggal_tutup']): ?>
                        <?= date('d/m/Y H:i', strtotime($data['tanggal_tutup'])) ?>
                    <?php endif; ?>
                </p>
                <?php endif; ?>
                <?php if ($data['keterangan']): ?>
                <p><strong>Keterangan:</strong> <?= htmlspecialchars($data['keterangan']) ?></p>
                <?php endif; ?>
                <p><strong>Dicetak pada:</strong> ${new Date().toLocaleString('id-ID')}</p>
            </div>
        `;
        
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>Detail Formulir - <?= htmlspecialchars($data['nama_gelombang']) ?></title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        h2, h3 { color: #333; }
                        p { margin-bottom: 10px; }
                        strong { color: #555; }
                    </style>
                </head>
                <body>
                    ${printContent.innerHTML}
                </body>
            </html>
        `);
        
        printWindow.document.close();
        printWindow.print();
    }
  </script>
</body>
</html>