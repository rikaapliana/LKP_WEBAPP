<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';

// Set timezone Indonesia Barat
date_default_timezone_set('Asia/Jakarta');

// Function untuk format tanggal Indonesia
function formatTanggalIndonesia($date, $withTime = false) {
    $bulan = array(
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );
    
    if ($withTime) {
        $hari = date('d');
        $bulanNum = date('n');
        $tahun = date('Y');
        $jam = date('H:i:s');
        return $hari . ' ' . $bulan[$bulanNum] . ' ' . $tahun . ', ' . $jam;
    } else {
        $hari = date('d');
        $bulanNum = date('n');
        $tahun = date('Y');
        return $hari . ' ' . $bulan[$bulanNum] . ' ' . $tahun;
    }
}

// [Kode filter dan query sama seperti sebelumnya - tidak perlu diubah]
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';
$filterKelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';
$filterGelombang = isset($_GET['gelombang']) ? $_GET['gelombang'] : '';
$filterJK = isset($_GET['jk']) ? $_GET['jk'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Build query dengan filter
$whereConditions = [];
$params = [];
$types = "";

if (!empty($filterStatus)) {
    $whereConditions[] = "s.status_aktif = ?";
    $params[] = $filterStatus;
    $types .= "s";
}

if (!empty($filterKelas)) {
    $whereConditions[] = "k.nama_kelas = ?";
    $params[] = $filterKelas;
    $types .= "s";
}

if (!empty($filterGelombang)) {
    $whereConditions[] = "g.nama_gelombang = ?";
    $params[] = $filterGelombang;
    $types .= "s";
}

if (!empty($filterJK)) {
    $whereConditions[] = "s.jenis_kelamin = ?";
    $params[] = $filterJK;
    $types .= "s";
}

if (!empty($searchTerm)) {
    $whereConditions[] = "(s.nama LIKE ? OR s.nik LIKE ? OR s.email LIKE ? OR s.tempat_lahir LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    $types .= "ssss";
}

// Query data siswa dengan filter
$query = "SELECT s.*, k.nama_kelas, g.nama_gelombang 
          FROM siswa s 
          LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
          LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang";

if (!empty($whereConditions)) {
    $query .= " WHERE " . implode(" AND ", $whereConditions);
}

$query .= " ORDER BY s.nama ASC";

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $query);
}

// Hitung statistik dari hasil filter
$totalSiswa = mysqli_num_rows($result);
$siswaAktif = 0;
$siswaLaki = 0;
$siswaPerempuan = 0;
$dataArray = [];

// Ambil semua data untuk statistik dan simpan dalam array
while ($row = mysqli_fetch_assoc($result)) {
    $dataArray[] = $row;
    if ($row['status_aktif'] == 'aktif') $siswaAktif++;
    if ($row['jenis_kelamin'] == 'Laki-Laki') $siswaLaki++;
    if ($row['jenis_kelamin'] == 'Perempuan') $siswaPerempuan++;
}

$siswaInaktif = $totalSiswa - $siswaAktif;

// Function helper untuk terbilang
function terbilang($angka) {
    $angka = abs($angka);
    $baca = array("", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan", "sepuluh", "sebelas");
    $terbilang = "";
    
    if ($angka < 12) {
        $terbilang = " " . $baca[$angka];
    } else if ($angka < 20) {
        $terbilang = terbilang($angka - 10) . " belas";
    } else if ($angka < 100) {
        $terbilang = terbilang($angka / 10) . " puluh" . terbilang($angka % 10);
    } else if ($angka < 200) {
        $terbilang = " seratus" . terbilang($angka - 100);
    } else if ($angka < 1000) {
        $terbilang = terbilang($angka / 100) . " ratus" . terbilang($angka % 100);
    } else if ($angka < 2000) {
        $terbilang = " seribu" . terbilang($angka - 1000);
    } else if ($angka < 1000000) {
        $terbilang = terbilang($angka / 1000) . " ribu" . terbilang($angka % 1000);
    }
    
    return $terbilang;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Data Siswa - LKP Pradata Komputer</title>
    <link rel="icon" type="image/png" href="../../../assets/img/favicon.png"/>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="../../../assets/css/bootstrap-icons.css" />
    <link rel="stylesheet" href="../../../assets/css/fonts.css" />
    <link rel="stylesheet" href="../../../assets/css/styles.css" />
</head>
<body>
    <!-- Print Controls -->
    <div class="report-print-controls report-no-print">
        <h5><i class="bi bi-file-earmark-pdf"></i> Laporan Data Siswa</h5>
        <p class="mb-3">Format Resmi Lembaga - Siap Cetak A4</p>
        <button onclick="window.print()" class="btn report-btn-custom btn-lg me-3">
            <i class="bi bi-printer"></i> Cetak Laporan
        </button>
        <a href="index.php" class="btn report-btn-custom btn-lg">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    <!-- Watermark -->
    <div class="report-watermark report-no-print">LKP PRADATA</div>

    <div class="report-main-container">
        <!-- Letterhead Resmi -->
        <div class="report-letterhead report-page-break-avoid">
            <div class="report-institution-logo">
                <img src="../../../assets/img/favicon.png" alt="Logo LKP Pradata Komputer">
            </div>
            <div class="report-institution-name">
                LEMBAGA KURSUS DAN PELATIHAN PRADATA KOMPUTER
            </div>
            <div class="report-institution-address">
                Jl. Ketimun S. 21 No. 3A Komplek Pertamina. Tanjung - Tabalong - Kalimantan Selatan 71571
            </div>
            <div class="report-institution-contact">
                Telp: (0526) 2023798 | Email: awiekpradata@gmail.com | Website: www.pradatacomputer.ac.id
            </div>
        </div>

        <!-- Document Header -->
        <div class="report-document-header report-page-break-avoid">
            <h1 class="report-document-title">Laporan Data Siswa</h1>
            <p class="report-document-subtitle">Periode <?= formatTanggalIndonesia('', false) ?></p>
        </div>

        <!-- Document Meta -->
        <div class="report-document-meta report-page-break-avoid">
            <table class="report-meta-table">
                <tr>
                    <td class="report-meta-label">Nomor Dokumen</td>
                    <td class="report-meta-separator">:</td>
                    <td class="report-meta-value"><?= date('Y') ?>/LKP-PC/<?= date('m') ?>/<?= str_pad(date('d'), 3, '0', STR_PAD_LEFT) ?></td>
                </tr>
                <tr>
                    <td class="report-meta-label">Tanggal Cetak</td>
                    <td class="report-meta-separator">:</td>
                    <td class="report-meta-value"><?= formatTanggalIndonesia('', true) ?> WIB</td>
                </tr>
                <tr>
                    <td class="report-meta-label">Dicetak Oleh</td>
                    <td class="report-meta-separator">:</td>
                    <td class="report-meta-value">Administrator Sistem</td>
                </tr>
                <tr>
                    <td class="report-meta-label">Total Record</td>
                    <td class="report-meta-separator">:</td>
                    <td class="report-meta-value"><?= $totalSiswa ?> (<?= terbilang($totalSiswa) ?>) siswa</td>
                </tr>
            </table>
        </div>

        <!-- Filter Information -->
        <?php if (!empty($filterStatus) || !empty($filterKelas) || !empty($filterGelombang) || !empty($filterJK) || !empty($searchTerm)): ?>
        <div class="report-filter-info report-page-break-avoid">
            <div class="report-filter-title">Filter yang Diterapkan:</div>
            <div class="row g-2">
                <?php if (!empty($filterStatus)): ?>
                    <div class="col-6">• Status: <?= ucfirst($filterStatus) ?></div>
                <?php endif; ?>
                <?php if (!empty($filterKelas)): ?>
                    <div class="col-6">• Kelas: <?= htmlspecialchars($filterKelas) ?></div>
                <?php endif; ?>
                <?php if (!empty($filterGelombang)): ?>
                    <div class="col-6">• Gelombang: <?= htmlspecialchars($filterGelombang) ?></div>
                <?php endif; ?>
                <?php if (!empty($filterJK)): ?>
                    <div class="col-6">• Jenis Kelamin: <?= htmlspecialchars($filterJK) ?></div>
                <?php endif; ?>
                <?php if (!empty($searchTerm)): ?>
                    <div class="col-12">• Pencarian: "<?= htmlspecialchars($searchTerm) ?>"</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Data Table -->
        <table class="report-data-table">
            <thead>
                <tr>
                    <th class="report-col-no">NO</th>
                    <th class="report-col-nik">NIK</th>
                    <th class="report-col-nama">NAMA LENGKAP</th>
                    <th class="report-col-tempat">TEMPAT LAHIR</th>
                    <th class="report-col-tgl">TGL LAHIR</th>
                    <th class="report-col-jk">JK</th>
                    <th class="report-col-pendidikan">PENDIDIKAN</th>
                    <th class="report-col-hp">NO. HP</th>
                    <th class="report-col-email">EMAIL</th>
                    <th class="report-col-kelas">KELAS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($dataArray)): ?>
                    <?php $no = 1; ?>
                    <?php foreach ($dataArray as $siswa): ?>
                        <tr>
                            <td class="report-text-center"><?= $no++ ?></td>
                            <td class="report-text-center"><?= htmlspecialchars($siswa['nik']) ?></td>
                            <td class="report-text-left"><?= htmlspecialchars($siswa['nama']) ?></td>
                            <td class="report-text-center"><?= htmlspecialchars($siswa['tempat_lahir']) ?></td>
                            <td class="report-text-center"><?= date('d/m/Y', strtotime($siswa['tanggal_lahir'])) ?></td>
                            <td class="report-text-center"><?= $siswa['jenis_kelamin'] == 'Laki-Laki' ? 'L' : 'P' ?></td>
                            <td class="report-text-center"><?= htmlspecialchars($siswa['pendidikan_terakhir']) ?></td>
                            <td class="report-text-center"><?= htmlspecialchars($siswa['no_hp']) ?></td>
                            <td class="report-text-center"><?= htmlspecialchars($siswa['email'] ?? '-') ?></td>
                            <td class="report-text-center">
                                <?php if($siswa['nama_kelas']): ?>
                                    <?= htmlspecialchars($siswa['nama_kelas']) ?>
                                    <?php if($siswa['nama_gelombang']): ?>
                                        <br><small>(<?= htmlspecialchars($siswa['nama_gelombang']) ?>)</small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="report-text-center" style="padding: 20px; font-style: italic; color: #666;">
                            <i class="bi bi-info-circle"></i> Tidak ada data siswa yang sesuai dengan filter yang diterapkan
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Footer -->
        <div class="report-document-footer report-page-break-avoid">
            <div class="report-closing-statement">
                <strong>Catatan:</strong><br>
                • Laporan ini dicetak secara otomatis dari sistem informasi LKP Pradata Komputer<br>
                • Total record yang ditampilkan: <?= $totalSiswa ?> siswa<br>
                • Keterangan: L = Laki-laki, P = Perempuan<br>
            </div>

            <!-- Signature Section -->
            <div class="report-signature-section">
                <div class="report-signature-right">
                    <div class="report-signature-location">Tabalong, <?= formatTanggalIndonesia('', false) ?></div>
                    <div class="report-signature-title">Mengetahui,</div>
                    <div class="report-signature-name">Awiek Hadi Widodo</div>
                    <div class="report-signature-position">Direktur</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Optimize for print
        window.onbeforeprint = function() {
            // Adjust font sizes for print
            document.body.style.fontSize = '11px';
            
            // Ensure table cells don't break - gunakan class yang sudah diupdate
            document.querySelectorAll('.report-data-table td').forEach(function(cell) {
                cell.style.fontSize = '9px';
                cell.style.lineHeight = '1.2';
            });
            
            // Hide any remaining screen elements - gunakan class yang sudah diupdate
            document.querySelectorAll('.report-no-print').forEach(function(element) {
                element.style.display = 'none';
            });
        }
        
        window.onafterprint = function() {
            // Reset styles after print - gunakan class yang sudah diupdate
            document.body.style.fontSize = '';
            document.querySelectorAll('.report-data-table td').forEach(function(cell) {
                cell.style.fontSize = '';
                cell.style.lineHeight = '';
            });
        }
        
        // Add print date automatically
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Laporan Data Siswa - Format Resmi Instansi');
            console.log('Total Records: <?= $totalSiswa ?>');
        });
    </script>
</body>
</html>