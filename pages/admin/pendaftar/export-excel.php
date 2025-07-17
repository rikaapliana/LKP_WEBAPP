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

// Ambil filter dari URL
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';
$filterJam = isset($_GET['jam']) ? $_GET['jam'] : '';
$filterJK = isset($_GET['jk']) ? $_GET['jk'] : '';
$filterPendidikan = isset($_GET['pendidikan']) ? $_GET['pendidikan'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Build query dengan filter
$whereConditions = [];
$params = [];
$types = "";

if (!empty($filterStatus)) {
    $whereConditions[] = "p.status_pendaftaran = ?";
    $params[] = $filterStatus;
    $types .= "s";
}

if (!empty($filterJam)) {
    $whereConditions[] = "p.jam_pilihan = ?";
    $params[] = $filterJam;
    $types .= "s";
}

if (!empty($filterJK)) {
    $whereConditions[] = "p.jenis_kelamin = ?";
    $params[] = $filterJK;
    $types .= "s";
}

if (!empty($filterPendidikan)) {
    $whereConditions[] = "p.pendidikan_terakhir = ?";
    $params[] = $filterPendidikan;
    $types .= "s";
}

if (!empty($searchTerm)) {
    $whereConditions[] = "(p.nama_pendaftar LIKE ? OR p.nik LIKE ? OR p.email LIKE ? OR p.tempat_lahir LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    $types .= "ssss";
}

// Query data pendaftar dengan filter
$query = "SELECT p.* FROM pendaftar p";

if (!empty($whereConditions)) {
    $query .= " WHERE " . implode(" AND ", $whereConditions);
}

$query .= " ORDER BY p.nama_pendaftar ASC";

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $query);
}

// Hitung statistik
$totalPendaftar = mysqli_num_rows($result);
$belumVerifikasi = 0;
$terverifikasi = 0;
$diterima = 0;
$ditolak = 0;
$pendaftarLaki = 0;
$pendaftarPerempuan = 0;
$dataArray = [];

// Ambil semua data untuk statistik dan simpan dalam array
while ($row = mysqli_fetch_assoc($result)) {
    $dataArray[] = $row;
    
    switch($row['status_pendaftaran']) {
        case 'Belum di Verifikasi':
            $belumVerifikasi++;
            break;
        case 'Terverifikasi':
            $terverifikasi++;
            break;
        case 'Diterima':
            $diterima++;
            break;
        case 'Ditolak':
            $ditolak++;
            break;
    }
    
    if ($row['jenis_kelamin'] == 'Laki-Laki') $pendaftarLaki++;
    if ($row['jenis_kelamin'] == 'Perempuan') $pendaftarPerempuan++;
}

// Set nama file dengan ekstensi .xls untuk auto-recognition Excel
$filename = "Laporan_Data_Pendaftar_" . date('Y-m-d_H-i-s') . ".xls";

// Header yang tepat untuk Excel format
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');
header('Pragma: public');

?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" 
      xmlns:x="urn:schemas-microsoft-com:office:excel" 
      xmlns="http://www.w3.org/TR/REC-html40">

<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="ProgId" content="Excel.Sheet">
<meta name="Generator" content="Microsoft Excel 15">
<!--[if gte mso 9]><xml>
<x:ExcelWorkbook>
<x:ExcelWorksheets>
<x:ExcelWorksheet>
<x:Name>Data Pendaftar</x:Name>
<x:WorksheetOptions>
<x:DisplayGridlines/>
</x:WorksheetOptions>
</x:ExcelWorksheet>
</x:ExcelWorksheets>
</x:ExcelWorkbook>
</xml><![endif]-->

<style>
<!--
table {
    mso-displayed-decimal-separator:"\.";
    mso-displayed-thousand-separator:"\,";
}

.xl65 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#2E75B6;
    mso-pattern:auto none;
    color:white;
    font-size:16pt;
    font-weight:700;
    text-align:center;
}

.xl66 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#D5E4F7;
    mso-pattern:auto none;
    font-size:11pt;
    font-weight:700;
    text-align:center;
}

.xl67 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#4472C4;
    mso-pattern:auto none;
    color:white;
    font-size:10pt;
    font-weight:700;
    text-align:center;
}

.xl68 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    font-size:9pt;
    text-align:center;
    mso-number-format:"\@";
}

.xl69 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    font-size:9pt;
    text-align:left;
    mso-number-format:"\@";
}

.xl70 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#C6EFCE;
    mso-pattern:auto none;
    color:#006100;
    font-size:9pt;
    font-weight:700;
    text-align:center;
}

.xl71 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#FFC7CE;
    mso-pattern:auto none;
    color:#9C0006;
    font-size:9pt;
    font-weight:700;
    text-align:center;
}

.xl72 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#DDEBF7;
    mso-pattern:auto none;
    color:#1F4E79;
    font-size:9pt;
    font-weight:700;
    text-align:center;
}

.xl73 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#FCE4EC;
    mso-pattern:auto none;
    color:#880E4F;
    font-size:9pt;
    font-weight:700;
    text-align:center;
}

.xl74 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#E7E6E6;
    mso-pattern:auto none;
    font-size:10pt;
    font-weight:700;
}

.xl75 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#F2F2F2;
    mso-pattern:auto none;
    font-size:10pt;
}

.xl76 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#FFF2CC;
    mso-pattern:auto none;
    font-size:10pt;
    font-weight:700;
}

.xl77 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#FFF2CC;
    mso-pattern:auto none;
    color:#7F6000;
    font-size:9pt;
    font-weight:700;
    text-align:center;
}

.xl78 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#E2EFDA;
    mso-pattern:auto none;
    color:#375623;
    font-size:9pt;
    font-weight:700;
    text-align:center;
}

.xl79 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#DEEBF7;
    mso-pattern:auto none;
    color:#1F4E79;
    font-size:9pt;
    font-weight:700;
    text-align:center;
}

.xl80 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#FCE4D6;
    mso-pattern:auto none;
    color:#833C0C;
    font-size:9pt;
    font-weight:700;
    text-align:center;
}
-->
</style>
</head>

<body>

<!-- Header Institusi -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
<tr height="40">
    <td height="40" colspan="13" class="xl65">LEMBAGA KURSUS DAN PELATIHAN PRADATA KOMPUTER</td>
</tr>
<tr height="25">
    <td height="25" colspan="13" class="xl66">Jl. Ketimun S. 21 No. 3A Komplek Pertamina. Tanjung - Tabalong - Kalimantan Selatan 71571</td>
</tr>
<tr height="20">
    <td height="20" colspan="13" class="xl66">Telp: (0526) 2023798 | Email: awiekpradata@gmail.com | Website: www.pradatacomputer.ac.id</td>
</tr>
<tr height="10">
    <td height="10" colspan="13" style="border:none;"></td>
</tr>
</table>

<!-- Info Dokumen -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
<tr height="30">
    <td height="30" colspan="13" class="xl66">LAPORAN DATA PENDAFTAR - PERIODE <?= strtoupper(formatTanggalIndonesia('', false)) ?></td>
</tr>
<tr>
    <td class="xl74" width="150">Nomor Dokumen:</td>
    <td class="xl75" colspan="12"><?= date('Y') ?>/LKP-PC/<?= date('m') ?>/<?= str_pad(date('d'), 3, '0', STR_PAD_LEFT) ?>-PENDAFTAR</td>
</tr>
<tr>
    <td class="xl74">Tanggal Export:</td>
    <td class="xl75" colspan="12"><?= formatTanggalIndonesia('', true) ?> WIB</td>
</tr>
<tr>
    <td class="xl74">Total Record:</td>
    <td class="xl75" colspan="12"><?= $totalPendaftar ?> (<?= terbilang($totalPendaftar) ?>) pendaftar</td>
</tr>
<tr>
    <td class="xl74">Dicetak Oleh:</td>
    <td class="xl75" colspan="12">Administrator Sistem</td>
</tr>
<?php if (!empty($filterStatus) || !empty($filterJam) || !empty($filterJK) || !empty($filterPendidikan) || !empty($searchTerm)): ?>
<tr height="5">
    <td height="5" colspan="13" style="border:none;"></td>
</tr>
<tr>
    <td colspan="13" class="xl76">
        FILTER YANG DITERAPKAN:
        <?php if (!empty($filterStatus)): ?>• Status: <?= htmlspecialchars($filterStatus) ?> <?php endif; ?>
        <?php if (!empty($filterJam)): ?>• Jam: <?= htmlspecialchars($filterJam) ?> <?php endif; ?>
        <?php if (!empty($filterJK)): ?>• Jenis Kelamin: <?= htmlspecialchars($filterJK) ?> <?php endif; ?>
        <?php if (!empty($filterPendidikan)): ?>• Pendidikan: <?= htmlspecialchars($filterPendidikan) ?> <?php endif; ?>
        <?php if (!empty($searchTerm)): ?>• Pencarian: "<?= htmlspecialchars($searchTerm) ?>" <?php endif; ?>
    </td>
</tr>
<?php endif; ?>
<tr height="10">
    <td height="10" colspan="13" style="border:none;"></td>
</tr>
</table>

<!-- Data Table -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
<thead>
<tr>
    <td class="xl67" width="40">No</td>
    <td class="xl67" width="120">NIK</td>
    <td class="xl67" width="200">Nama Lengkap</td>
    <td class="xl67" width="120">Tempat Lahir</td>
    <td class="xl67" width="100">Tanggal Lahir</td>
    <td class="xl67" width="80">JK</td>
    <td class="xl67" width="100">Pendidikan</td>
    <td class="xl67" width="120">No. HP</td>
    <td class="xl67" width="200">Email</td>
    <td class="xl67" width="120">Jam Pilihan</td>
    <td class="xl67" width="120">Status Pendaftaran</td>
    <td class="xl67" width="80">Dok. Foto</td>
    <td class="xl67" width="80">Dok. KTP</td>
</tr>
</thead>
<tbody>
<?php if (!empty($dataArray)): ?>
    <?php $no = 1; ?>
    <?php foreach ($dataArray as $pendaftar): ?>
    <tr>
        <td class="xl68"><?= $no++ ?></td>
        <td class="xl68"><?= htmlspecialchars($pendaftar['nik'] ?? '-') ?></td>
        <td class="xl69"><?= htmlspecialchars($pendaftar['nama_pendaftar']) ?></td>
        <td class="xl68"><?= htmlspecialchars($pendaftar['tempat_lahir'] ?? '-') ?></td>
        <td class="xl68"><?= $pendaftar['tanggal_lahir'] ? date('d/m/Y', strtotime($pendaftar['tanggal_lahir'])) : '-' ?></td>
        <td class="<?= $pendaftar['jenis_kelamin'] == 'Laki-Laki' ? 'xl72' : 'xl73' ?>"><?= $pendaftar['jenis_kelamin'] == 'Laki-Laki' ? 'L' : 'P' ?></td>
        <td class="xl68"><?= htmlspecialchars($pendaftar['pendidikan_terakhir'] ?? '-') ?></td>
        <td class="xl68"><?= htmlspecialchars($pendaftar['no_hp'] ?? '-') ?></td>
        <td class="xl69"><?= htmlspecialchars($pendaftar['email'] ?? '-') ?></td>
        <td class="xl68"><?= htmlspecialchars($pendaftar['jam_pilihan'] ?? '-') ?></td>
        <td class="<?php 
            switch($pendaftar['status_pendaftaran']) {
                case 'Belum di Verifikasi':
                    echo 'xl77';
                    break;
                case 'Terverifikasi':
                    echo 'xl78';
                    break;
                case 'Diterima':
                    echo 'xl79';
                    break;
                case 'Ditolak':
                    echo 'xl80';
                    break;
                default:
                    echo 'xl68';
            }
        ?>"><?= htmlspecialchars($pendaftar['status_pendaftaran'] ?? '-') ?></td>
        <td class="xl68"><?= !empty($pendaftar['pas_foto']) ? 'Ada' : 'Belum' ?></td>
        <td class="xl68"><?= !empty($pendaftar['ktp']) ? 'Ada' : 'Belum' ?></td>
    </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr>
        <td colspan="13" class="xl68" style="font-style:italic; color:#999;">
            Tidak ada data pendaftar yang sesuai dengan filter yang diterapkan
        </td>
    </tr>
<?php endif; ?>
</tbody>
</table>

<!-- Summary -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse; margin-top:20px;">
<tr height="10">
    <td height="10" colspan="13" style="border:none;"></td>
</tr>
<tr height="30">
    <td height="30" colspan="13" class="xl67">RINGKASAN DATA PENDAFTAR</td>
</tr>
<tr>
    <td class="xl74" width="200">Total Pendaftar:</td>
    <td class="xl75"><?= $totalPendaftar ?> orang</td>
    <td class="xl74">Laki-laki:</td>
    <td class="xl72"><?= $pendaftarLaki ?> orang</td>
    <td colspan="9" class="xl75"></td>
</tr>
<tr>
    <td class="xl74">Belum Verifikasi:</td>
    <td class="xl77"><?= $belumVerifikasi ?> orang</td>
    <td class="xl74">Perempuan:</td>
    <td class="xl73"><?= $pendaftarPerempuan ?> orang</td>
    <td colspan="9" class="xl75"></td>
</tr>
<tr>
    <td class="xl74">Terverifikasi:</td>
    <td class="xl78"><?= $terverifikasi ?> orang</td>
    <td class="xl74">Persentase Terverifikasi:</td>
    <td class="xl75"><?= $totalPendaftar > 0 ? round((($terverifikasi + $diterima) / $totalPendaftar) * 100, 1) : 0 ?>%</td>
    <td colspan="9" class="xl75"></td>
</tr>
<tr>
    <td class="xl74">Diterima (Jadi Siswa):</td>
    <td class="xl79"><?= $diterima ?> orang</td>
    <td class="xl74">Persentase Diterima:</td>
    <td class="xl75"><?= $totalPendaftar > 0 ? round(($diterima / $totalPendaftar) * 100, 1) : 0 ?>%</td>
    <td colspan="9" class="xl75"></td>
</tr>
<tr>
    <td class="xl74">Ditolak:</td>
    <td class="xl80"><?= $ditolak ?> orang</td>
    <td class="xl74">Conversion Rate:</td>
    <td class="xl75"><?= $totalPendaftar > 0 ? round(($diterima / $totalPendaftar) * 100, 1) : 0 ?>%</td>
    <td colspan="9" class="xl75"></td>
</tr>
</table>

<!-- Breakdown by Education -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse; margin-top:20px;">
<tr height="10">
    <td height="10" colspan="13" style="border:none;"></td>
</tr>
<tr height="25">
    <td height="25" colspan="13" class="xl67">BREAKDOWN BERDASARKAN PENDIDIKAN TERAKHIR</td>
</tr>
<?php
// Hitung breakdown pendidikan
$pendidikanBreakdown = [];
foreach ($dataArray as $pendaftar) {
    $pendidikan = $pendaftar['pendidikan_terakhir'] ?? 'Tidak Diketahui';
    if (!isset($pendidikanBreakdown[$pendidikan])) {
        $pendidikanBreakdown[$pendidikan] = 0;
    }
    $pendidikanBreakdown[$pendidikan]++;
}
ksort($pendidikanBreakdown);
?>
<?php foreach ($pendidikanBreakdown as $pendidikan => $jumlah): ?>
<tr>
    <td class="xl74" width="200"><?= htmlspecialchars($pendidikan) ?>:</td>
    <td class="xl75"><?= $jumlah ?> orang</td>
    <td class="xl74">Persentase:</td>
    <td class="xl75"><?= $totalPendaftar > 0 ? round(($jumlah / $totalPendaftar) * 100, 1) : 0 ?>%</td>
    <td colspan="9" class="xl75"></td>
</tr>
<?php endforeach; ?>
</table>

<!-- Footer -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse; margin-top:30px;">
<tr height="10">
    <td height="10" colspan="13" style="border:none;"></td>
</tr>
<tr>
    <td colspan="9" style="border:none;"></td>
    <td colspan="4" style="border:none; font-weight:bold;">Tabalong, <?= formatTanggalIndonesia('', false) ?></td>
</tr>
<tr>
    <td colspan="9" style="border:none;"></td>
    <td colspan="4" style="border:none;">Mengetahui,</td>
</tr>
<tr height="60">
    <td height="60" colspan="9" style="border:none;"></td>
    <td colspan="4" style="border:none;"></td>
</tr>
<tr>
    <td colspan="9" style="border:none;"></td>
    <td colspan="4" style="border:none; font-weight:bold; text-decoration:underline;">Awiek Hadi Widodo</td>
</tr>
<tr>
    <td colspan="9" style="border:none;"></td>
    <td colspan="4" style="border:none;">Direktur</td>
</tr>
<tr height="20">
    <td height="20" colspan="13" style="border:none;"></td>
</tr>
<tr>
    <td colspan="13" style="border:none; text-align:center; font-size:10pt; color:#666; font-style:italic;">
        Laporan ini dibuat secara otomatis oleh Sistem Informasi LKP Pradata Komputer
    </td>
</tr>
</table>

</body>
</html>

<?php
// Log export activity
$logFile = '../../../uploads/export_log.txt';
$adminName = $_SESSION['nama_admin'] ?? 'Unknown Admin';
$timestamp = date('Y-m-d H:i:s');
$logEntry = "[{$timestamp}] Export Excel - Data Pendaftar - Admin: {$adminName} - File: {$filename} - Total: {$totalPendaftar} records\n";
file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
?>