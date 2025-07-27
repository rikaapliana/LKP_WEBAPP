<?php
session_start();
require_once '../../../includes/auth.php';
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'gelombang';
$baseURL = '../';

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
        
        header('Location: kelola_formulir.php?id=' . $id_gelombang);
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
                 p.updated_at as pengaturan_updated
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

// Ambil statistik
$statsQuery = "SELECT 
    COUNT(DISTINCT k.id_kelas) as total_kelas,
    COALESCE(SUM(k.kapasitas), 0) as total_kapasitas_kelas,
    COUNT(CASE WHEN s.status_aktif = 'aktif' THEN s.id_siswa END) as total_siswa_aktif,
    COUNT(DISTINCT pd.id_pendaftar) as total_pendaftar,
    COUNT(CASE WHEN pd.status_pendaftaran = 'Belum di Verifikasi' THEN 1 END) as pendaftar_belum_verifikasi,
    COUNT(CASE WHEN pd.status_pendaftaran = 'Terverifikasi' THEN 1 END) as pendaftar_terverifikasi,
    COUNT(CASE WHEN pd.status_pendaftaran = 'Diterima' THEN 1 END) as pendaftar_diterima
FROM gelombang g
LEFT JOIN kelas k ON g.id_gelombang = k.id_gelombang
LEFT JOIN siswa s ON k.id_kelas = s.id_kelas
LEFT JOIN pendaftar pd ON g.id_gelombang = pd.id_gelombang
WHERE g.id_gelombang = $id_gelombang";

$statsResult = mysqli_query($conn, $statsQuery);
$stats = mysqli_fetch_assoc($statsResult);

// Hitung presentase
$total_kapasitas = $data['kuota_maksimal'] ?: $stats['total_kapasitas_kelas'];
$total_terisi = ($data['status'] === 'aktif') ? $stats['total_siswa_aktif'] : $stats['total_pendaftar'];
$presentase_terisi = $total_kapasitas > 0 ? round(($total_terisi / $total_kapasitas) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kelola Formulir - <?= htmlspecialchars($data['nama_gelombang']) ?> - LKP Pradata Komputer</title>
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
                <h2 class="page-title mb-1">KELOLA FORMULIR PENDAFTARAN</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="#">Data Pendaftaran</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="index.php">Data Gelombang</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Kelola Formulir</li>
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

        <!-- Header Info Card -->
        <div class="card content-card mb-4">
          <div class="section-header">
            <div class="row align-items-center">
              <div class="col-md-8">
                <h5 class="mb-1 text-dark">
                  <i class="bi bi-layers me-2"></i><?= htmlspecialchars($data['nama_gelombang']) ?>
                </h5>
                <div class="d-flex gap-2 align-items-center mt-2">
                  <span class="badge bg-light text-dark">
                    <i class="bi bi-calendar me-1"></i>Tahun <?= $data['tahun'] ?>
                  </span>
                  <span class="badge bg-info">
                    <i class="bi bi-hash me-1"></i>Gelombang Ke-<?= $data['gelombang_ke'] ?>
                  </span>
                  <span class="badge bg-<?= $data['status'] === 'aktif' ? 'success' : ($data['status'] === 'dibuka' ? 'primary' : 'secondary') ?>">
                    <i class="bi bi-<?= $data['status'] === 'aktif' ? 'play-circle' : ($data['status'] === 'dibuka' ? 'door-open' : 'stop-circle') ?> me-1"></i>
                    <?= ucfirst($data['status']) ?>
                  </span>
                </div>
              </div>
              <div class="col-md-4 text-md-end">
                <div class="d-flex gap-2 justify-content-md-end">
                  <a href="index.php" class="btn btn-kembali mb-1">
                     Kembali
                  </a>
                  <?php if ($data['status_pendaftaran'] === 'dibuka'): ?>
                    <a href="../../../pendaftaran.php" class="btn btn-kirim-soft mb-1" target="_blank">
                      <i class="bi bi-eye"></i> Lihat Formulir
                    </a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Progress Card -->
        <div class="card content-card mb-4">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h6 class="mb-0">
                <i class="bi bi-bar-chart-line me-2"></i>Progress Pengisian
              </h6>
              <small class="text-muted">
                <?= number_format($total_terisi) ?> dari <?= number_format($total_kapasitas) ?> orang
              </small>
            </div>
            <div class="progress mb-2" style="height: 10px;">
              <div class="progress-bar bg-<?= $presentase_terisi > 80 ? 'success' : ($presentase_terisi > 50 ? 'warning' : 'primary') ?>" 
                   style="width: <?= $presentase_terisi ?>%" 
                   data-bs-toggle="tooltip" 
                   title="<?= $presentase_terisi ?>% terisi">
              </div>
            </div>
            <div class="row text-center">
              <div class="col">
                <small class="text-muted">Status Formulir: </small>
                <?php if ($data['status_pendaftaran'] === 'dibuka'): ?>
                  <span class="badge bg-success">
                   Dibuka
                  </span>
                <?php elseif ($data['status_pendaftaran'] === 'ditutup'): ?>
                  <span class="badge bg-secondary">
                    Ditutup
                  </span>
                <?php else: ?>
                  <span class="badge bg-warning">
                    Belum Diatur
                  </span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="row">
          <!-- Form Pengaturan -->
          <div class="col-lg-8 mb-4">
            <div class="card content-card">
              <div class="section-header">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-gear me-2"></i>Pengaturan Formulir Pendaftaran
                </h5>
              </div>
              <div class="card-body">
                <form method="POST" id="formPengaturan">
                  <input type="hidden" name="action" value="update_pengaturan">
                  
                  <!-- Status & Kuota -->
                  <div class="row mb-4">
                    <div class="col-md-6">
                      <label class="form-label required">Status Formulir</label>
                      <select name="status_pendaftaran" class="form-select" required>
                        <option value="dibuka" <?= $data['status_pendaftaran'] === 'dibuka' ? 'selected' : '' ?>>
                          Dibuka
                        </option>
                        <option value="ditutup" <?= $data['status_pendaftaran'] === 'ditutup' ? 'selected' : '' ?>>
                          Ditutup
                        </option>
                      </select>
                    </div>
                    
                    
                    <div class="col-md-6">
                      <label class="form-label required">Kuota Maksimal</label>
                      <input type="number" name="kuota_maksimal" class="form-control" 
                             value="<?= $data['kuota_maksimal'] ?: 50 ?>" 
                             min="1" max="1000" required>
                  </div>
                  </div>
                  
                  <!-- Periode Pendaftaran -->
                  <div class="row mb-4">
                    <div class="col-md-6">
                      <label class="form-label">Tanggal & Waktu Buka</label>
                      <input type="datetime-local" name="tanggal_buka" class="form-control" 
                             value="<?= $data['tanggal_buka'] ? date('Y-m-d\TH:i', strtotime($data['tanggal_buka'])) : '' ?>">
                    </div>
                    
                    <div class="col-md-6">
                      <label class="form-label">Tanggal & Waktu Tutup</label>
                      <input type="datetime-local" name="tanggal_tutup" class="form-control" 
                             value="<?= $data['tanggal_tutup'] ? date('Y-m-d\TH:i', strtotime($data['tanggal_tutup'])) : '' ?>">
                    </div>
                  </div>
                  
                  <!-- Keterangan -->
                  <div class="mb-4">
                    <label class="form-label">Keterangan Tambahan</label>
                    <textarea name="keterangan" class="form-control" rows="4"><?= htmlspecialchars($data['keterangan'] ?? '') ?></textarea>
                   
                  </div>
                  
                  <!-- Button Actions -->
                  <div class="d-flex justify-content-end gap-3 pt-3 border-top">
                    <a href="index.php" class="btn btn-kembali">
                     Kembali
                    </a>
                    <button type="submit" class="btn btn-simpan">
                      Simpan Perubahan
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <!-- Informasi Detail -->
          <div class="col-lg-4 mb-4">
            <div class="card content-card h-100">
              <div class="section-header">
                <h6 class="mb-0 text-dark">
                  <i class="bi bi-info-circle me-2"></i>Informasi Detail
                </h6>
              </div>
              <div class="card-body">
                
                <!-- Status Saat Ini -->
                <div class="border-bottom pb-3 mb-3">
                  <h6 class="text-muted mb-2">Status Formulir Saat Ini</h6>
                  <?php if ($data['status_pendaftaran'] === 'dibuka'): ?>
                    <div class="d-flex align-items-center text-success">
                      <i class="bi bi-check-circle me-2"></i>
                      <span>Formulir sedang dibuka untuk pendaftaran</span>
                    </div>
                  <?php elseif ($data['status_pendaftaran'] === 'ditutup'): ?>
                    <div class="d-flex align-items-center text-danger">
                      <i class="bi bi-x-circle me-2"></i>
                      <span>Formulir ditutup untuk pendaftaran</span>
                    </div>
                  <?php else: ?>
                    <div class="d-flex align-items-center text-warning">
                      <i class="bi bi-exclamation-triangle me-2"></i>
                      <span>Belum ada pengaturan formulir</span>
                    </div>
                  <?php endif; ?>
                </div>

                <!-- Periode Pendaftaran -->
                <div class="border-bottom pb-3 mb-3">
                  <h6 class="text-muted mb-2">Periode Pendaftaran</h6>
                  <?php if ($data['tanggal_buka'] || $data['tanggal_tutup']): ?>
                    <?php if ($data['tanggal_buka']): ?>
                      <div class="d-flex align-items-center mb-1">
                        <i class="bi bi-calendar-event me-2 text-success"></i>
                        <small>Mulai: <?= date('d/m/Y H:i', strtotime($data['tanggal_buka'])) ?></small>
                      </div>
                    <?php endif; ?>
                    <?php if ($data['tanggal_tutup']): ?>
                      <div class="d-flex align-items-center">
                        <i class="bi bi-calendar-x me-2 text-danger"></i>
                        <small>Berakhir: <?= date('d/m/Y H:i', strtotime($data['tanggal_tutup'])) ?></small>
                      </div>
                    <?php endif; ?>
                  <?php else: ?>
                    <div class="d-flex align-items-center text-muted">
                      <i class="bi bi-infinity me-2"></i>
                      <small>Tidak ada batas waktu</small>
                    </div>
                  <?php endif; ?>
                </div>

                <!-- Update Terakhir -->
                <div class="border-bottom pb-3 mb-3">
                  <h6 class="text-muted mb-2">Terakhir Diperbarui</h6>
                  <?php if ($data['pengaturan_updated']): ?>
                    <div class="d-flex align-items-center">
                      <i class="bi bi-clock me-2 text-primary"></i>
                      <small><?= date('d/m/Y H:i', strtotime($data['pengaturan_updated'])) ?></small>
                    </div>
                  <?php else: ?>
                    <div class="d-flex align-items-center text-muted">
                      <i class="bi bi-dash-circle me-2"></i>
                      <small>Belum pernah diperbarui</small>
                    </div>
                  <?php endif; ?>
                </div>

                <!-- Statistik Pendaftar (jika ada) -->
                <?php if ($data['status'] === 'dibuka' && $stats['total_pendaftar'] > 0): ?>
                <div>
                  <h6 class="text-muted mb-2">Status Pendaftar</h6>
                  <div class="row g-2 text-center">
                    <div class="col-4">
                      <div class="p-2 bg-warning bg-opacity-10 rounded">
                        <div class="fw-bold text-warning"><?= $stats['pendaftar_belum_verifikasi'] ?></div>
                        <small class="text-muted">Belum Verifikasi</small>
                      </div>
                    </div>
                    <div class="col-4">
                      <div class="p-2 bg-info bg-opacity-10 rounded">
                        <div class="fw-bold text-info"><?= $stats['pendaftar_terverifikasi'] ?></div>
                        <small class="text-muted">Terverifikasi</small>
                      </div>
                    </div>
                    <div class="col-4">
                      <div class="p-2 bg-success bg-opacity-10 rounded">
                        <div class="fw-bold text-success"><?= $stats['pendaftar_diterima'] ?></div>
                        <small class="text-muted">Diterima</small>
                      </div>
                    </div>
                  </div>
                </div>
                <?php endif; ?>

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
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function(tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Auto-hide success alerts
        const successAlerts = document.querySelectorAll('.alert-success');
        successAlerts.forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
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
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';
                
                // Reset after some time if form submission fails
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }, 10000);
            });
        }
    });
    
    // Print functionality
    function printDetail() {
        const gelombangInfo = {
            nama: '<?= addslashes(htmlspecialchars($data['nama_gelombang'])) ?>',
            tahun: '<?= $data['tahun'] ?>',
            gelombang_ke: '<?= $data['gelombang_ke'] ?>',
            status: '<?= ucfirst($data['status']) ?>',
            status_formulir: '<?= $data['status_pendaftaran'] ? ucfirst($data['status_pendaftaran']) : 'Belum diatur' ?>',
            kuota: '<?= $data['kuota_maksimal'] ?: 'Tidak ditentukan' ?>',
            total_terisi: '<?= number_format($total_terisi) ?>',
            presentase: '<?= $presentase_terisi ?>%'
        };
        
        let periodeInfo = '';
        <?php if ($data['tanggal_buka'] || $data['tanggal_tutup']): ?>
            periodeInfo = '<p><strong>Periode Pendaftaran:</strong><br>';
            <?php if ($data['tanggal_buka']): ?>
                periodeInfo += 'Mulai: <?= date('d/m/Y H:i', strtotime($data['tanggal_buka'])) ?><br>';
            <?php endif; ?>
            <?php if ($data['tanggal_tutup']): ?>
                periodeInfo += 'Berakhir: <?= date('d/m/Y H:i', strtotime($data['tanggal_tutup'])) ?>';
            <?php endif; ?>
            periodeInfo += '</p>';
        <?php endif; ?>
        
        let keteranganInfo = '';
        <?php if ($data['keterangan']): ?>
            keteranganInfo = '<p><strong>Keterangan:</strong><br><?= addslashes(nl2br(htmlspecialchars($data['keterangan']))) ?></p>';
        <?php endif; ?>
        
        const printContent = `
            <div style="padding: 30px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; max-width: 800px; margin: 0 auto;">
                <div style="text-align: center; border-bottom: 3px solid #007bff; padding-bottom: 20px; margin-bottom: 30px;">
                    <h1 style="color: #007bff; margin-bottom: 5px;">LKP Pradata Komputer</h1>
                    <h2 style="color: #6c757d; font-weight: normal; margin: 0;">Detail Formulir Pendaftaran</h2>
                </div>
                
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
                    <h3 style="color: #495057; margin-top: 0;">${gelombangInfo.nama}</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
                        <div><strong>Tahun:</strong> ${gelombangInfo.tahun}</div>
                        <div><strong>Gelombang Ke:</strong> ${gelombangInfo.gelombang_ke}</div>
                        <div><strong>Status Gelombang:</strong> ${gelombangInfo.status}</div>
                        <div><strong>Status Formulir:</strong> ${gelombangInfo.status_formulir}</div>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 25px; text-align: center;">
                    <div style="background: white; padding: 20px; border-radius: 8px; border: 2px solid #007bff;">
                        <div style="font-size: 2em; font-weight: bold; color: #007bff;">${gelombangInfo.total_terisi}</div>
                        <div style="color: #6c757d;">Total Terisi</div>
                    </div>
                    <div style="background: white; padding: 20px; border-radius: 8px; border: 2px solid #17a2b8;">
                        <div style="font-size: 2em; font-weight: bold; color: #17a2b8;">${gelombangInfo.kuota}</div>
                        <div style="color: #6c757d;">Kuota Maksimal</div>
                    </div>
                    <div style="background: white; padding: 20px; border-radius: 8px; border: 2px solid #28a745;">
                        <div style="font-size: 2em; font-weight: bold; color: #28a745;">${gelombangInfo.presentase}</div>
                        <div style="color: #6c757d;">Tingkat Pengisian</div>
                    </div>
                </div>
                
                ${periodeInfo}
                ${keteranganInfo}
                
                <div style="border-top: 1px solid #dee2e6; padding-top: 20px; margin-top: 30px; text-align: center; color: #6c757d;">
                    <p style="margin: 0;"><strong>Dicetak pada:</strong> ${new Date().toLocaleString('id-ID')}</p>
                </div>
            </div>
        `;
        
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>Detail Formulir - ${gelombangInfo.nama}</title>
                    <style>
                        body { 
                            margin: 0; 
                            padding: 20px; 
                            background: #ffffff;
                            color: #333;
                            line-height: 1.6;
                        }
                        @media print {
                            body { padding: 0; }
                            .no-print { display: none; }
                        }
                        h1, h2, h3 { margin-top: 0; }
                        strong { color: #495057; }
                        @page { margin: 1cm; }
                    </style>
                </head>
                <body>
                    ${printContent}
                </body>
            </html>
        `);
        
        printWindow.document.close();
        setTimeout(() => {
            printWindow.print();
        }, 100);
    }
  </script>
</body>
</html>