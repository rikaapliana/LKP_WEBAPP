<?php
session_start();  

// Set timezone Indonesia untuk Kalimantan Selatan (WITA)
date_default_timezone_set('Asia/Makassar'); // UTC+8 untuk Kalimantan Selatan

require_once '../../includes/auth.php';
requireAdminAuth(); // Hanya admin yang bisa akses

$role = $_SESSION['role'] ?? 'admin'; 
$activePage = 'dashboard'; 
$baseURL = './';
include '../../includes/db.php'; 

// Ambil data admin berdasarkan user_id
$user_id = $_SESSION['user_id'];
$admin_query = "SELECT a.nama, a.email, a.foto, u.username 
                FROM admin a 
                JOIN user u ON a.id_user = u.id_user 
                WHERE a.id_user = ?";
$stmt = $conn->prepare($admin_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$AdminData = $result->fetch_assoc();

// Fallback jika data admin tidak ditemukan
if (!$AdminData) {
    $AdminData = [
        'nama' => $_SESSION['username'] ?? 'Administrator',
        'email' => '',
        'foto' => '',
        'username' => $_SESSION['username'] ?? 'admin'
    ];
}

// Statistik utama - 4 baris pertama (tetap seperti sebelumnya)
$jumlahSiswa        = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM siswa"))[0];
$jumlahPendaftar    = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM pendaftar"))[0];
$jumlahKelas        = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM kelas"))[0];
$jumlahInstruktur   = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM instruktur"))[0];

// Statistik tambahan yang diperlukan
$siswaAktif = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM siswa WHERE status_aktif = 'aktif'"))[0];
$pendaftarBelumVerifikasi = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM pendaftar WHERE status_pendaftaran = 'Belum di Verifikasi'"))[0];
$jadwalHariIni = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM jadwal WHERE tanggal = CURDATE()"))[0];

// Statistik baris kedua (5-8)
// 5. Analisis hasil evaluasi (tetap)
$avgQuery = "SELECT AVG((nilai_word + nilai_excel + nilai_ppt + nilai_internet + nilai_pengembangan) / 5) as rata_rata FROM nilai WHERE rata_rata IS NOT NULL";
$avgResult = mysqli_query($conn, $avgQuery);
$avgNilai = mysqli_fetch_assoc($avgResult)['rata_rata'];
$avgNilai = $avgNilai ? round($avgNilai, 1) : 0;

// 6. Gelombang
$gelombangAktif = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM gelombang WHERE status = 'aktif'"))[0];
$gelombangDibuka = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM gelombang WHERE status = 'dibuka'"))[0];

// 7. Status formulir pendaftaran
$formulirAktif = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM pengaturan_pendaftaran WHERE status_pendaftaran = 'dibuka'"))[0];

// 8. Grafik Pendaftar (menggantikan evaluasi aktif)
$pendaftarGelombangAktif = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM pendaftar p JOIN gelombang g ON p.id_gelombang = g.id_gelombang WHERE g.status IN ('aktif', 'dibuka')"))[0];
// Query untuk tabel
$queryKelas = "SELECT k.nama_kelas, k.kapasitas, i.nama AS instruktur, g.nama_gelombang, g.status as status_gelombang,
               COUNT(s.id_siswa) as jumlah_siswa
               FROM kelas k
               LEFT JOIN instruktur i ON k.id_instruktur = i.id_instruktur
               LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
               LEFT JOIN siswa s ON k.id_kelas = s.id_kelas AND s.status_aktif = 'aktif'
               GROUP BY k.id_kelas
               ORDER BY k.id_kelas DESC
               LIMIT 5";
$resultKelas = mysqli_query($conn, $queryKelas);

$queryPendaftaranTerbaru = "SELECT p.nama_pendaftar, p.status_pendaftaran, p.id_pendaftar, g.nama_gelombang
                           FROM pendaftar p
                           LEFT JOIN gelombang g ON p.id_gelombang = g.id_gelombang
                           ORDER BY p.id_pendaftar DESC
                           LIMIT 5";
$resultPendaftaran = mysqli_query($conn, $queryPendaftaranTerbaru);

// Format tanggal dan waktu Indonesia
$hariIni = date('l, d F Y');
$jamSekarang = date('H:i');
$tanggalNotifikasi = date('d/m/Y');

// Translate hari ke bahasa Indonesia
$hariInggris = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$hariIndonesia = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$hariIni = str_replace($hariInggris, $hariIndonesia, $hariIni);

// Translate bulan ke bahasa Indonesia
$bulanInggris = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$bulanIndonesia = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$hariIni = str_replace($bulanInggris, $bulanIndonesia, $hariIni);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dashboard Admin - LKP Pradata Komputer</title>
  <link rel="icon" type="image/png" href="../../assets/img/favicon.png"/>
  <link rel="stylesheet" href="../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../assets/css/fonts.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/styles.css" />
</head>
<style>
 
.font-roboto {
    font-family: 'Roboto', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif !important;
}

</style>

<body>
  <div class="d-flex">
    <!-- Sidebar -->
    <?php include '../../includes/sidebar/admin.php'; ?>

    <!-- Main Content -->
    <div class="flex-fill dashboard-bg">
      <!-- Top Navbar -->
      <nav class="top-navbar">
        <div class="container-fluid px-3 px-md-4">
          <div class="d-flex align-items-center">
            <!-- Left section - Hamburger Menu (hanya tampil di mobile) -->
            <div class="d-flex align-items-center">
              <button class="btn sidebar-toggle me-3" type="button" id="sidebarToggle">
                <i class="bi bi-list fs-5"></i>
              </button>
            </div>
            
            <!-- Spacer untuk push content ke kanan -->
            <div class="flex-grow-1"></div>
            
            <!-- Right section -->
            <div class="d-flex align-items-center">
              <!-- Notifications -->
              <div class="navbar-notifications me-3">
                <button class="btn position-relative" type="button" data-bs-toggle="dropdown">
                  <i class="bi bi-bell fs-5"></i>
                  <?php if ($pendaftarBelumVerifikasi > 0): ?>
                  <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                    <?= $pendaftarBelumVerifikasi ?>
                  </span>
                  <?php endif; ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg" style="min-width: 300px;">
                  <li class="dropdown-header d-flex justify-content-between align-items-center">
                    <span>Notifikasi</span>
                    <small class="text-muted"><?= $tanggalNotifikasi ?></small>
                  </li>
                  <li><hr class="dropdown-divider"></li>
                  <?php if ($pendaftarBelumVerifikasi > 0): ?>
                  <li>
                    <a class="dropdown-item py-2" href="pendaftar/">
                      <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                          <div class="bg-warning rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="bi bi-person-plus text-white"></i>
                          </div>
                        </div>
                        <div class="flex-grow-1">
                          <div class="fw-semibold"><?= $pendaftarBelumVerifikasi ?> Pendaftar Baru</div>
                          <small class="text-muted">Menunggu verifikasi</small>
                        </div>
                      </div>
                    </a>
                  </li>
                  <?php endif; ?>
                  <?php if ($jadwalHariIni > 0): ?>
                  <li>
                    <a class="dropdown-item py-2" href="jadwal/">
                      <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                          <div class="bg-info rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="bi bi-calendar-check text-white"></i>
                          </div>
                        </div>
                        <div class="flex-grow-1">
                          <div class="fw-semibold"><?= $jadwalHariIni ?> Jadwal Hari Ini</div>
                          <small class="text-muted">Kelas berlangsung</small>
                        </div>
                      </div>
                    </a>
                  </li>
                  <?php endif; ?>
                  <?php if ($pendaftarBelumVerifikasi == 0 && $jadwalHariIni == 0): ?>
                  <li>
                    <div class="dropdown-item py-3 text-center">
                      <i class="bi bi-check-circle text-success fs-2 mb-2"></i>
                      <div class="text-muted">Tidak ada notifikasi baru</div>
                    </div>
                  </li>
                  <?php endif; ?>
                </ul>
              </div>
              
              <!-- User Profile Dropdown -->
              <div class="dropdown">
                <button class="btn p-0 border-0 bg-transparent" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                  <div class="d-flex align-items-center">
                    <!-- User Avatar -->
                    <div class="me-3">
                      <?php if (!empty($AdminData['foto']) && file_exists('../../uploads/profile_pictures/' . $AdminData['foto'])): ?>
                        <img src="../../uploads/profile_pictures/<?= $AdminData['foto'] ?>" 
                             alt="Foto Profil" 
                             class="rounded-circle" 
                             style="width: 45px; height: 45px; object-fit: cover; border: 2px solid #e9ecef;">
                      <?php else: ?>
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                             style="width: 45px; height: 45px; border: 2px solid #e9ecef;">
                          <i class="bi bi-person-fill fs-5"></i>
                        </div>
                      <?php endif; ?>
                    </div>
                    
                    <!-- User Info -->
                    <div class="text-start d-none d-md-block">
                      <div class="fw-semibold text-dark" style="font-size: 14px;">
                        <?= htmlspecialchars($AdminData['nama']) ?>
                      </div>
                      <small class="text-muted">Administrator</small>
                    </div>
                    
                    <!-- Dropdown Caret -->
                    <i class="bi bi-chevron-down ms-2 text-muted" style="font-size: 12px;"></i>
                  </div>
                </button>
                
                <ul class="dropdown-menu dropdown-menu-end shadow-lg" style="min-width: 250px;">
                  <li class="dropdown-header">
                    <div class="d-flex align-items-center">
                      <?php if (!empty($AdminData['foto']) && file_exists('../../uploads/profile_pictures/' . $AdminData['foto'])): ?>
                        <img src="../../uploads/profile_pictures/<?= $AdminData['foto'] ?>" 
                             alt="Foto Profil" 
                             class="rounded-circle me-3" 
                             style="width: 50px; height: 50px; object-fit: cover;">
                      <?php else: ?>
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" 
                             style="width: 50px; height: 50px;">
                          <i class="bi bi-person-fill fs-4"></i>
                        </div>
                      <?php endif; ?>
                      <div>
                        <div class="fw-semibold"><?= htmlspecialchars($AdminData['nama']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($AdminData['email']) ?></small>
                      </div>
                    </div>
                  </li>
                  <li><hr class="dropdown-divider"></li>
                  <li>
                    <a class="dropdown-item" href="profil/">
                      <i class="bi bi-person-circle me-2"></i>Profil Saya
                    </a>
                  </li>
                  <li>
                    <a class="dropdown-item" href="dashboard.php">
                      <i class="bi bi-speedometer2 me-2"></i>Dashboard
                    </a>
                  </li>
                  <li><hr class="dropdown-divider"></li>
                  <li>
                    <a class="dropdown-item text-danger" href="../auth/logout.php">
                      <i class="bi bi-box-arrow-right me-2"></i>Keluar
                    </a>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </nav>
      
      <div class="p-3 p-md-4">

        <!-- Welcome Section -->
        <div class="welcome-card corporate">
          <div class="row align-items-center">
            <div class="col-md-8">
              <div class="d-flex align-items-center mb-3">
                
                <!-- Welcome Text -->
                <div>
                  <h2 class="mb-1 font-roboto">Selamat Datang, <?= htmlspecialchars($AdminData['nama']) ?></h2>
                  <p class="mb-0 opacity-90">
                    Anda sedang login sebagai <strong>Administrator</strong><br>
                    <strong>LKP Pradata Komputer Kabupaten Tabalong</strong>
                  </p>
                </div>
              </div>
              
              <!-- Quick Stats in Welcome -->
              <div class="row mt-3">
                <div class="col-auto">
                  <small class="opacity-75">
                    <?= $hariIni ?>
                  </small>
                </div>
                <div class="col-auto">
                  <small class="opacity-75">
                    <?= $jamSekarang ?> WITA 
                  </small>
                </div>
              </div>
            </div>
            <div class="col-md-4 text-end ">
              <i class="bi bi-person-workspace fs-1 opacity-75"></i>
            </div>
          </div>
        </div>

        <!-- Statistik Cards - Row 1: Data Akademik Utama -->
        <div class="row g-3 g-md-4 mb-4">
         <div class="col-6 col-lg-3">
            <a href="pendaftar/" class="text-decoration-none">
              <div class="card stats-card stats-card-clickable">
                <div class="card-body text-center p-3">
                  <div class="stats-icon stats-success text-white mb-2">
                    <i class="bi bi-person-plus-fill"></i>
                  </div>
                  <h4 class="fw-bold mb-1"><?= number_format($jumlahPendaftar) ?></h4>
                  <p class="text-muted mb-0 small">Total Pendaftar</p>
                  <small class="text-warning"><i class="bi bi-clock"></i> <?= $pendaftarBelumVerifikasi ?> belum verifikasi</small>
                </div>
              </div>
            </a>
          </div>

          <div class="col-6 col-lg-3">
            <a href="siswa/" class="text-decoration-none">
              <div class="card stats-card stats-card-clickable">
                <div class="card-body text-center p-3">
                  <div class="stats-icon stats-primary text-white mb-2">
                    <i class="bi bi-people-fill"></i>
                  </div>
                  <h4 class="fw-bold mb-1"><?= number_format($jumlahSiswa) ?></h4>
                  <p class="text-muted mb-0 small">Total Siswa</p>
                  <small class="text-success"><i class="bi bi-check-circle"></i> <?= $siswaAktif ?> aktif</small>
                </div>
              </div>
            </a>
          </div>
      
          <div class="col-6 col-lg-3">
            <a href="kelas/" class="text-decoration-none">
              <div class="card stats-card stats-card-clickable">
                <div class="card-body text-center p-3">
                  <div class="stats-icon stats-warning text-white mb-2">
                    <i class="bi bi-building-fill-check"></i>
                  </div>
                  <h4 class="fw-bold mb-1"><?= number_format($jumlahKelas) ?></h4>
                  <p class="text-muted mb-0 small">Total Kelas</p>
                  <small class="text-warning"><i class="bi bi-calendar"></i> <?= $jadwalHariIni ?> jadwal hari ini</small>
                </div>
              </div>
            </a>
          </div>
          
          <div class="col-6 col-lg-3">
            <a href="instruktur/" class="text-decoration-none">
              <div class="card stats-card stats-card-clickable">
                <div class="card-body text-center p-3">
                  <div class="stats-icon stats-danger text-white mb-2">
                    <i class="bi bi-person-badge-fill"></i>
                  </div>
                  <h4 class="fw-bold mb-1"><?= number_format($jumlahInstruktur) ?></h4>
                  <p class="text-muted mb-0 small">Total Instruktur</p>
                  <small class="text-info"><i class="bi bi-person-check"></i> Tersedia</small>
                </div>
              </div>
            </a>
          </div>
        </div>

        <!-- Statistik Cards - Row 2: Manajemen & Sistem -->
        <div class="row g-3 g-md-4 mb-4">

         <div class="col-6 col-lg-3">
            <a href="laporan_manajemen.php" target="_blank" class="text-decoration-none">
                <div class="card stats-card stats-card-clickable h-100">
                    <div class="card-body text-center p-3">
                        <div class="stats-icon bg-primary text-white mb-2">
                            <i class="bi bi-pie-chart-fill"></i>
                        </div>
                        <p class="mb-1">Executive Summary</p>
                        <small class="text-primary">Ringkasan Data & Analitik</small>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-6 col-lg-3">
          <a href="pendaftar/grafik.php" class="text-decoration-none">
            <div class="card stats-card stats-card-clickable">
              <div class="card-body text-center p-3">
                <div class="stats-icon bg-secondary text-white mb-2">
                  <i class="bi bi-bar-chart-line-fill"></i>
                </div>
                <p class="text-muted mb-0 small">Grafik Pendaftar</p>
                <small class="text-info">
                  <i class="bi bi-people-fill"></i> 
                  Pendaftar gelombang aktif
                </small>
              </div>
            </div>
          </a>
        </div>

        <div class="col-6 col-lg-3">
            <a href="analisis-evaluasi/" class="text-decoration-none">
              <div class="card stats-card stats-card-clickable">
                <div class="card-body text-center p-3">
                  <div class="stats-icon bg-info text-white mb-2">
                    <i class="bi bi-graph-up"></i>
                  </div>
                  <p class="text-muted mb-0 small">Analisis Hasil Evaluasi</p>
                  <small class="text-primary"><i class="bi bi-bar-chart"></i> Hasil evaluasi</small>
                </div>
              </div>
            </a>
          </div>

       
          
          <div class="col-6 col-lg-3">
            <a href="../../pendaftaran.php" class="text-decoration-none">
              <div class="card stats-card stats-card-clickable">
                <div class="card-body text-center p-3">
                  <div class="stats-icon bg-teal text-white mb-2">
                    <i class="bi bi-clipboard-data-fill"></i>
                  </div>
                  <p class="text-muted mb-0 small">Formulir Pendaftaran</p>
                  <small class="text-<?= $formulirAktif > 0 ? 'success' : 'warning' ?>">
                    <i class="bi bi-<?= $formulirAktif > 0 ? 'toggle-on' : 'toggle-off' ?>"></i> 
                    <?= $formulirAktif > 0 ? 'Pendaftaran dibuka' : 'Pendaftaran ditutup' ?>
                  </small>
                </div>
              </div>
            </a>
          </div>
        </div>

  <!-- Sidebar Overlay -->
  <div class="sidebar-overlay"></div>

  <!-- Scripts - Offline -->
  <script src="../../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../assets/js/scripts.js"></script>
</body>
</html>