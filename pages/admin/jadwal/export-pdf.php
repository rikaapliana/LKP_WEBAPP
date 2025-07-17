<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';

// Set timezone Indonesia Barat
date_default_timezone_set('Asia/Jakarta');

// Function untuk format tanggal Indonesia
function formatTanggalIndonesia($date = null, $withTime = false) {
    $bulan = array(
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );
    
    // Jika date kosong atau null, gunakan tanggal hari ini
    if (empty($date)) {
        $timestamp = time();
    } else {
        $timestamp = is_string($date) ? strtotime($date) : $date;
    }
    
    $hari = date('d', $timestamp);
    $bulanNum = date('n', $timestamp);
    $tahun = date('Y', $timestamp);
    
    if ($withTime) {
        $jam = date('H:i:s', $timestamp);
        return $hari . ' ' . $bulan[$bulanNum] . ' ' . $tahun . ', ' . $jam;
    } else {
        return $hari . ' ' . $bulan[$bulanNum] . ' ' . $tahun;
    }
}

// Function untuk format hari Indonesia
function formatHariIndonesia($dayName) {
    $hariMap = [
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa', 
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu',
        'Sunday' => 'Minggu'
    ];
    return isset($hariMap[$dayName]) ? $hariMap[$dayName] : $dayName;
}

// Get filter parameters from URL (same as index.php)
$filterKelas = isset($_GET['filter_kelas']) ? $_GET['filter_kelas'] : '';
$filterInstruktur = isset($_GET['filter_instruktur']) ? $_GET['filter_instruktur'] : '';
$filterHari = isset($_GET['filter_hari']) ? $_GET['filter_hari'] : '';
$filterPeriode = isset($_GET['filter_periode']) ? $_GET['filter_periode'] : '';
$filterTanggal = isset($_GET['filter_tanggal']) ? $_GET['filter_tanggal'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Build WHERE clause for filters (same logic as index.php)
$whereConditions = [];
$params = [];

if (!empty($searchTerm)) {
    $whereConditions[] = "(k.nama_kelas LIKE ? OR i.nama LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
}

if (!empty($filterKelas)) {
    $whereConditions[] = "k.nama_kelas = ?";
    $params[] = $filterKelas;
}

if (!empty($filterInstruktur)) {
    $whereConditions[] = "i.nama = ?";
    $params[] = $filterInstruktur;
}

if (!empty($filterTanggal)) {
    $whereConditions[] = "j.tanggal = ?";
    $params[] = $filterTanggal;
}

if (!empty($filterHari)) {
    $hariMap = [
        'Senin' => 'Monday',
        'Selasa' => 'Tuesday', 
        'Rabu' => 'Wednesday',
        'Kamis' => 'Thursday',
        'Jumat' => 'Friday',
        'Sabtu' => 'Saturday',
        'Minggu' => 'Sunday'
    ];
    if (isset($hariMap[$filterHari])) {
        $whereConditions[] = "DAYNAME(j.tanggal) = ?";
        $params[] = $hariMap[$filterHari];
    }
}

if (!empty($filterPeriode)) {
    $today = date('Y-m-d');
    switch($filterPeriode) {
        case 'today':
            $whereConditions[] = "j.tanggal = ?";
            $params[] = $today;
            break;
        case 'week':
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
            $whereConditions[] = "j.tanggal BETWEEN ? AND ?";
            $params[] = $startOfWeek;
            $params[] = $endOfWeek;
            break;
        case 'month':
            $startOfMonth = date('Y-m-01');
            $endOfMonth = date('Y-m-t');
            $whereConditions[] = "j.tanggal BETWEEN ? AND ?";
            $params[] = $startOfMonth;
            $params[] = $endOfMonth;
            break;
        case 'past':
            $whereConditions[] = "j.tanggal < ?";
            $params[] = $today;
            break;
        case 'upcoming':
            $whereConditions[] = "j.tanggal > ?";
            $params[] = $today;
            break;
    }
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// Order by nama kelas as requested
$orderClause = "ORDER BY k.nama_kelas ASC, j.tanggal ASC, j.waktu_mulai ASC";

// Query data jadwal dengan kelas dan instruktur
$query = "SELECT j.*, 
          k.nama_kelas, 
          g.nama_gelombang,
          i.nama as nama_instruktur,
          DAYNAME(j.tanggal) as hari_nama,
          CASE DAYNAME(j.tanggal)
            WHEN 'Monday' THEN 'Senin'
            WHEN 'Tuesday' THEN 'Selasa' 
            WHEN 'Wednesday' THEN 'Rabu'
            WHEN 'Thursday' THEN 'Kamis'
            WHEN 'Friday' THEN 'Jumat'
            WHEN 'Saturday' THEN 'Sabtu'
            WHEN 'Sunday' THEN 'Minggu'
          END as hari_indonesia
          FROM jadwal j 
          LEFT JOIN kelas k ON j.id_kelas = k.id_kelas
          LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang  
          LEFT JOIN instruktur i ON j.id_instruktur = i.id_instruktur
          $whereClause
          $orderClause";

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        $result = false;
    }
} else {
    $result = mysqli_query($conn, $query);
}

// Hitung total jadwal
$totalJadwal = $result ? mysqli_num_rows($result) : 0;
$dataArray = [];

// Ambil semua data dan simpan dalam array
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $dataArray[] = $row;
    }
}

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
    <title>Laporan Data Jadwal - LKP Pradata Komputer</title>
    <link rel="icon" type="image/png" href="../../../assets/img/favicon.png"/>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="../../../assets/css/bootstrap-icons.css" />
    <link rel="stylesheet" href="../../../assets/css/fonts.css" />
    <link rel="stylesheet" href="../../../assets/css/styles.css" />
</head>
<body>
    <!-- Print Controls -->
    <div class="report-print-controls report-no-print">
        <h5><i class="bi bi-file-earmark-pdf"></i> Laporan Data Jadwal</h5>
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
            <h1 class="report-document-title">Laporan Data Jadwal</h1>
            <p class="report-document-subtitle">Periode <?= formatTanggalIndonesia() ?></p>
        </div>

        <!-- Document Meta -->
        <div class="report-document-meta report-page-break-avoid">
            <table class="report-meta-table">
                <tr>
                    <td class="report-meta-label">Nomor Dokumen</td>
                    <td class="report-meta-separator">:</td>
                    <td class="report-meta-value"><?= date('Y') ?>/LKP-PC/JADWAL/<?= date('m') ?>/<?= str_pad(date('d'), 3, '0', STR_PAD_LEFT) ?></td>
                </tr>
                <tr>
                    <td class="report-meta-label">Tanggal Cetak</td>
                    <td class="report-meta-separator">:</td>
                    <td class="report-meta-value"><?= formatTanggalIndonesia(null, true) ?> WIB</td>
                </tr>
                <tr>
                    <td class="report-meta-label">Dicetak Oleh</td>
                    <td class="report-meta-separator">:</td>
                    <td class="report-meta-value">Administrator Sistem</td>
                </tr>
                <tr>
                    <td class="report-meta-label">Total Record</td>
                    <td class="report-meta-separator">:</td>
                    <td class="report-meta-value"><?= $totalJadwal ?> (<?= terbilang($totalJadwal) ?>) jadwal</td>
                </tr>
            </table>
        </div>

        <!-- Filter Information -->
        <?php if (!empty($filterKelas) || !empty($filterInstruktur) || !empty($filterHari) || !empty($filterPeriode) || !empty($filterTanggal) || !empty($searchTerm)): ?>
        <div class="report-filter-info report-page-break-avoid">
            <div class="report-filter-title">Filter yang Diterapkan:</div>
            <div class="row g-2">
                <?php if (!empty($filterKelas)): ?>
                    <div class="col-6">• Kelas: <?= htmlspecialchars($filterKelas) ?></div>
                <?php endif; ?>
                <?php if (!empty($filterInstruktur)): ?>
                    <div class="col-6">• Instruktur: <?= htmlspecialchars($filterInstruktur) ?></div>
                <?php endif; ?>
                <?php if (!empty($filterHari)): ?>
                    <div class="col-6">• Hari: <?= htmlspecialchars($filterHari) ?></div>
                <?php endif; ?>
                <?php if (!empty($filterPeriode)): ?>
                    <div class="col-6">• Periode: <?php
                        $periodeLabels = [
                            'today' => 'Hari Ini',
                            'week' => 'Minggu Ini',
                            'month' => 'Bulan Ini',
                            'past' => 'Yang Sudah Lewat',
                            'upcoming' => 'Yang Akan Datang'
                        ];
                        echo htmlspecialchars($periodeLabels[$filterPeriode] ?? $filterPeriode);
                    ?></div>
                <?php endif; ?>
                <?php if (!empty($filterTanggal)): ?>
                    <div class="col-12">• Tanggal: <?= formatTanggalIndonesia($filterTanggal) ?></div>
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
                    <th style="width: 12%;">TANGGAL</th>
                    <th style="width: 8%;">HARI</th>
                    <th style="width: 12%;">WAKTU</th>
                    <th style="width: 20%;">KELAS</th>
                    <th style="width: 18%;">INSTRUKTUR</th>
                    <th style="width: 10%;">STATUS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($dataArray)): ?>
                    <?php $no = 1; ?>
                    <?php foreach ($dataArray as $jadwal): ?>
                        <?php
                        $tanggalJadwal = strtotime($jadwal['tanggal']);
                        $today = strtotime(date('Y-m-d'));
                        $isToday = $tanggalJadwal == $today;
                        $isPast = $tanggalJadwal < $today;
                        ?>
                        <tr>
                            <td class="report-text-center"><?= $no++ ?></td>
                            <td class="report-text-center"><?= date('d/m/Y', strtotime($jadwal['tanggal'])) ?></td>
                            <td class="report-text-center"><?= htmlspecialchars($jadwal['hari_indonesia']) ?></td>
                            <td class="report-text-center" style="font-size: 9px;">
                                <?= date('H:i', strtotime($jadwal['waktu_mulai'])) ?> - 
                                <?= date('H:i', strtotime($jadwal['waktu_selesai'])) ?>
                            </td>
                            <td class="report-text-left" style="line-height: 1.3;">
                                <strong><?= htmlspecialchars($jadwal['nama_kelas']) ?></strong>
                                <?php if($jadwal['nama_gelombang']): ?>
                                    <br><small style="color: #666;">(<?= htmlspecialchars($jadwal['nama_gelombang']) ?>)</small>
                                <?php endif; ?>
                            </td>
                            <td class="report-text-left"><?= htmlspecialchars($jadwal['nama_instruktur'] ?? 'Belum ditentukan') ?></td>
                            <td class="report-text-center" style="font-size: 8px;">
                                <?php if($isToday): ?>
                                    Hari Ini
                                <?php elseif($isPast): ?>
                                    Selesai
                                <?php else: ?>
                                    Terjadwal
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="report-text-center" style="padding: 20px; font-style: italic; color: #666;">
                            <i class="bi bi-info-circle"></i> Tidak ada data jadwal yang sesuai dengan filter yang diterapkan
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
                • Total record yang ditampilkan: <?= $totalJadwal ?> jadwal<br>
                • Data diurutkan berdasarkan nama kelas kemudian tanggal<br>
                • Status jadwal berdasarkan tanggal pelaksanaan saat ini<br>
            </div>

            <!-- Signature Section -->
            <div class="report-signature-section">
                <div class="report-signature-right">
                    <div class="report-signature-location">Tabalong, <?= formatTanggalIndonesia() ?></div>
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
            
            // Ensure table cells don't break
            document.querySelectorAll('.report-data-table td').forEach(function(cell) {
                cell.style.fontSize = '9px';
                cell.style.lineHeight = '1.2';
            });
            
            // Hide any remaining screen elements
            document.querySelectorAll('.report-no-print').forEach(function(element) {
                element.style.display = 'none';
            });
        }
        
        window.onafterprint = function() {
            // Reset styles after print
            document.body.style.fontSize = '';
            document.querySelectorAll('.report-data-table td').forEach(function(cell) {
                cell.style.fontSize = '';
                cell.style.lineHeight = '';
            });
        }
        
        // Add print date automatically
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Laporan Data Jadwal - Format Resmi Instansi');
            console.log('Total Records: <?= $totalJadwal ?>');
        });
    </script>
</body>
</html>