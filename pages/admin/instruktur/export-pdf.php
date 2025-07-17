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

// Filter parameters
$filterJK = isset($_GET['jk']) ? $_GET['jk'] : '';
$filterAngkatan = isset($_GET['angkatan']) ? $_GET['angkatan'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Build query dengan filter
$whereConditions = [];
$params = [];
$types = "";

if (!empty($filterJK)) {
    $whereConditions[] = "i.jenis_kelamin = ?";
    $params[] = $filterJK;
    $types .= "s";
}

if (!empty($filterAngkatan)) {
    $whereConditions[] = "i.angkatan = ?";
    $params[] = $filterAngkatan;
    $types .= "s";
}

if (!empty($searchTerm)) {
    $whereConditions[] = "(i.nama LIKE ? OR i.nik LIKE ? OR i.angkatan LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    $types .= "sss";
}

// Query data instruktur dengan kelas yang diampu
$query = "SELECT i.*, 
          GROUP_CONCAT(DISTINCT CONCAT(k.nama_kelas, ' (', g.nama_gelombang, ')') SEPARATOR ', ') as kelas_diampu,
          COUNT(DISTINCT k.id_kelas) as jumlah_kelas
          FROM instruktur i 
          LEFT JOIN kelas k ON i.id_instruktur = k.id_instruktur
          LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang";

if (!empty($whereConditions)) {
    $query .= " WHERE " . implode(" AND ", $whereConditions);
}

$query .= " GROUP BY i.id_instruktur ORDER BY i.nama ASC";

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $query);
}

// Hitung statistik dari hasil filter
$totalInstruktur = mysqli_num_rows($result);
$instrukturLaki = 0;
$instrukturPerempuan = 0;
$totalKelasAktif = 0;
$dataArray = [];

// Ambil semua data untuk statistik dan simpan dalam array
while ($row = mysqli_fetch_assoc($result)) {
    $dataArray[] = $row;
    if ($row['jenis_kelamin'] == 'Laki-Laki') $instrukturLaki++;
    if ($row['jenis_kelamin'] == 'Perempuan') $instrukturPerempuan++;
    $totalKelasAktif += $row['jumlah_kelas'];
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
    <title>Laporan Data Instruktur - LKP Pradata Komputer</title>
    <link rel="icon" type="image/png" href="../../../assets/img/favicon.png"/>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="../../../assets/css/bootstrap-icons.css" />
    <link rel="stylesheet" href="../../../assets/css/fonts.css" />
    <link rel="stylesheet" href="../../../assets/css/styles.css" />
</head>
<body>
    <!-- Print Controls -->
    <div class="report-print-controls report-no-print">
        <h5><i class="bi bi-file-earmark-pdf"></i> Laporan Data Instruktur</h5>
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
            <h1 class="report-document-title">Laporan Data Instruktur</h1>
            <p class="report-document-subtitle">Periode <?= formatTanggalIndonesia('', false) ?></p>
        </div>

        <!-- Document Meta -->
        <div class="report-document-meta report-page-break-avoid">
            <table class="report-meta-table">
                <tr>
                    <td class="report-meta-label">Nomor Dokumen</td>
                    <td class="report-meta-separator">:</td>
                    <td class="report-meta-value"><?= date('Y') ?>/LKP-PC/INST/<?= date('m') ?>/<?= str_pad(date('d'), 3, '0', STR_PAD_LEFT) ?></td>
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
                    <td class="report-meta-value"><?= $totalInstruktur ?> (<?= terbilang($totalInstruktur) ?>) instruktur</td>
                </tr>
            </table>
        </div>

        <!-- Filter Information -->
        <?php if (!empty($filterJK) || !empty($filterAngkatan) || !empty($searchTerm)): ?>
        <div class="report-filter-info report-page-break-avoid">
            <div class="report-filter-title">Filter yang Diterapkan:</div>
            <div class="row g-2">
                <?php if (!empty($filterJK)): ?>
                    <div class="col-6">• Jenis Kelamin: <?= htmlspecialchars($filterJK) ?></div>
                <?php endif; ?>
                <?php if (!empty($filterAngkatan)): ?>
                    <div class="col-6">• Angkatan: <?= htmlspecialchars($filterAngkatan) ?></div>
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
                    <th class="report-col-jk">JK</th>
                    <th style="width: 15%;">ANGKATAN</th>
                    <th style="width: 25%;">KELAS YANG DIAMPU</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($dataArray)): ?>
                    <?php $no = 1; ?>
                    <?php foreach ($dataArray as $instruktur): ?>
                        <tr>
                            <td class="report-text-center"><?= $no++ ?></td>
                            <td class="report-text-center"><?= htmlspecialchars($instruktur['nik'] ?? '-') ?></td>
                            <td class="report-text-left"><?= htmlspecialchars($instruktur['nama']) ?></td>
                            <td class="report-text-center"><?= $instruktur['jenis_kelamin'] == 'Laki-Laki' ? 'L' : 'P' ?></td>
                            <td class="report-text-center" style="width: 15%;"><?= htmlspecialchars($instruktur['angkatan'] ?? '-') ?></td>
                            <td class="report-text-left" style="width: 25%; line-height: 1.3;"><?php if($instruktur['kelas_diampu']): ?>
                                    <?= htmlspecialchars($instruktur['kelas_diampu']) ?>
                                <?php else: ?>
                                    <em style="color: #666;">Belum ada kelas</em>
                                <?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="report-text-center" style="padding: 20px; font-style: italic; color: #666;">
                            <i class="bi bi-info-circle"></i> Tidak ada data instruktur yang sesuai dengan filter yang diterapkan
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
                • Total record yang ditampilkan: <?= $totalInstruktur ?> instruktur<br>
                • Keterangan: L = Laki-laki, P = Perempuan<br>
                • Data kelas yang diampu berdasarkan penugasan aktif saat ini<br>
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
            console.log('Laporan Data Instruktur - Format Resmi Instansi');
            console.log('Total Records: <?= $totalInstruktur ?>');
        });
    </script>
</body>
</html>