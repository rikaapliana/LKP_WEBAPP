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

// Hitung statistik
$totalJadwal = $result ? mysqli_num_rows($result) : 0;
$jadwalHariIni = 0;
$jadwalSelesai = 0;
$jadwalTerjadwal = 0;
$kelasStats = [];
$instrukturStats = [];
$dataArray = [];

$today = strtotime(date('Y-m-d'));

// Ambil semua data untuk statistik dan simpan dalam array
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $dataArray[] = $row;
        
        $tanggalJadwal = strtotime($row['tanggal']);
        if ($tanggalJadwal == $today) {
            $jadwalHariIni++;
        } elseif ($tanggalJadwal < $today) {
            $jadwalSelesai++;
        } else {
            $jadwalTerjadwal++;
        }
        
        // Statistik per kelas
        $namaKelas = $row['nama_kelas'];
        if (!isset($kelasStats[$namaKelas])) {
            $kelasStats[$namaKelas] = 0;
        }
        $kelasStats[$namaKelas]++;
        
        // Statistik per instruktur
        $namaInstruktur = $row['nama_instruktur'] ?? 'Belum ditentukan';
        if (!isset($instrukturStats[$namaInstruktur])) {
            $instrukturStats[$namaInstruktur] = 0;
        }
        $instrukturStats[$namaInstruktur]++;
    }
}

// Set nama file dengan ekstensi .xls untuk auto-recognition Excel
$filename = "Laporan_Data_Jadwal_" . date('Y-m-d_H-i-s') . ".xls";

// Header yang tepat untuk Excel format
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');
header('Pragma: public');

// Tidak pakai DOCTYPE dan HTML5 tags - langsung table HTML
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
<x:Name>Data Jadwal</x:Name>
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
    background:#DEEBF7;
    mso-pattern:auto none;
    color:#1F4E79;
    font-size:9pt;
    font-weight:700;
    text-align:center;
}

.xl78 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#E1F5FE;
    mso-pattern:auto none;
    color:#01579B;
    font-size:9pt;
    font-weight:700;
    text-align:center;
}

.xl79 {
    mso-style-parent:style0;
    font-family:Arial, sans-serif;
    mso-font-charset:0;
    border:.5pt solid windowtext;
    background:#F3E5F5;
    mso-pattern:auto none;
    color:#4A148C;
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
    <td height="40" colspan="7" class="xl65">LEMBAGA KURSUS DAN PELATIHAN PRADATA KOMPUTER</td>
</tr>
<tr height="25">
    <td height="25" colspan="7" class="xl66">Jl. Ketimun S. 21 No. 3A Komplek Pertamina. Tanjung - Tabalong - Kalimantan Selatan 71571</td>
</tr>
<tr height="20">
    <td height="20" colspan="7" class="xl66">Telp: (0526) 2023798 | Email: awiekpradata@gmail.com | Website: www.pradatacomputer.ac.id</td>
</tr>
<tr height="10">
    <td height="10" colspan="7" style="border:none;"></td>
</tr>
</table>

<!-- Info Dokumen -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
<tr height="30">
    <td height="30" colspan="7" class="xl66">LAPORAN DATA JADWAL - PERIODE <?= strtoupper(formatTanggalIndonesia()) ?></td>
</tr>
<tr>
    <td class="xl74" width="150">Nomor Dokumen:</td>
    <td class="xl75" colspan="6"><?= date('Y') ?>/LKP-PC/JADWAL/<?= date('m') ?>/<?= str_pad(date('d'), 3, '0', STR_PAD_LEFT) ?></td>
</tr>
<tr>
    <td class="xl74">Tanggal Export:</td>
    <td class="xl75" colspan="6"><?= formatTanggalIndonesia(null, true) ?> WIB</td>
</tr>
<tr>
    <td class="xl74">Total Record:</td>
    <td class="xl75" colspan="6"><?= $totalJadwal ?> (<?= terbilang($totalJadwal) ?>) jadwal</td>
</tr>
<tr>
    <td class="xl74">Dicetak Oleh:</td>
    <td class="xl75" colspan="6">Administrator Sistem</td>
</tr>
<?php if (!empty($filterKelas) || !empty($filterInstruktur) || !empty($filterHari) || !empty($filterPeriode) || !empty($filterTanggal) || !empty($searchTerm)): ?>
<tr height="5">
    <td height="5" colspan="7" style="border:none;"></td>
</tr>
<tr>
    <td colspan="7" class="xl76">
        FILTER YANG DITERAPKAN:
        <?php if (!empty($filterKelas)): ?>• Kelas: <?= htmlspecialchars($filterKelas) ?> <?php endif; ?>
        <?php if (!empty($filterInstruktur)): ?>• Instruktur: <?= htmlspecialchars($filterInstruktur) ?> <?php endif; ?>
        <?php if (!empty($filterHari)): ?>• Hari: <?= htmlspecialchars($filterHari) ?> <?php endif; ?>
        <?php if (!empty($filterPeriode)): ?>• Periode: <?php
            $periodeLabels = [
                'today' => 'Hari Ini',
                'week' => 'Minggu Ini', 
                'month' => 'Bulan Ini',
                'past' => 'Yang Sudah Lewat',
                'upcoming' => 'Yang Akan Datang'
            ];
            echo htmlspecialchars($periodeLabels[$filterPeriode] ?? $filterPeriode);
        ?> <?php endif; ?>
        <?php if (!empty($filterTanggal)): ?>• Tanggal: <?= formatTanggalIndonesia($filterTanggal) ?> <?php endif; ?>
        <?php if (!empty($searchTerm)): ?>• Pencarian: "<?= htmlspecialchars($searchTerm) ?>" <?php endif; ?>
    </td>
</tr>
<?php endif; ?>
<tr height="10">
    <td height="10" colspan="7" style="border:none;"></td>
</tr>
</table>

<!-- Data Table -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
<thead>
<tr>
    <td class="xl67" width="40">No</td>
    <td class="xl67" width="100">Tanggal</td>
    <td class="xl67" width="80">Hari</td>
    <td class="xl67" width="120">Waktu</td>
    <td class="xl67" width="200">Kelas</td>
    <td class="xl67" width="180">Instruktur</td>
    <td class="xl67" width="100">Status</td>
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
        $statusClass = $isToday ? 'xl78' : ($isPast ? 'xl70' : 'xl77');
        $statusText = $isToday ? 'Hari Ini' : ($isPast ? 'Selesai' : 'Terjadwal');
        ?>
    <tr>
        <td class="xl68"><?= $no++ ?></td>
        <td class="xl68"><?= date('d/m/Y', strtotime($jadwal['tanggal'])) ?></td>
        <td class="xl68"><?= htmlspecialchars($jadwal['hari_indonesia']) ?></td>
        <td class="xl68">
            <?= date('H:i', strtotime($jadwal['waktu_mulai'])) ?> - 
            <?= date('H:i', strtotime($jadwal['waktu_selesai'])) ?>
        </td>
        <td class="xl69">
            <?= htmlspecialchars($jadwal['nama_kelas']) ?>
            <?php if($jadwal['nama_gelombang']): ?>
                (<?= htmlspecialchars($jadwal['nama_gelombang']) ?>)
            <?php endif; ?>
        </td>
        <td class="xl69"><?= htmlspecialchars($jadwal['nama_instruktur'] ?? 'Belum ditentukan') ?></td>
        <td class="<?= $statusClass ?>"><?= $statusText ?></td>
    </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr>
        <td colspan="7" class="xl68" style="font-style:italic; color:#999;">
            Tidak ada data jadwal yang sesuai dengan filter yang diterapkan
        </td>
    </tr>
<?php endif; ?>
</tbody>
</table>

<!-- Summary -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse; margin-top:20px;">
<tr height="10">
    <td height="10" colspan="7" style="border:none;"></td>
</tr>
<tr height="30">
    <td height="30" colspan="7" class="xl67">RINGKASAN DATA JADWAL</td>
</tr>
<tr>
    <td class="xl74" width="200">Total Jadwal:</td>
    <td class="xl75"><?= $totalJadwal ?> jadwal</td>
    <td class="xl74">Jadwal Hari Ini:</td>
    <td class="xl78"><?= $jadwalHariIni ?> jadwal</td>
    <td colspan="3" class="xl75"></td>
</tr>
<tr>
    <td class="xl74">Jadwal Selesai:</td>
    <td class="xl70"><?= $jadwalSelesai ?> jadwal</td>
    <td class="xl74">Jadwal Terjadwal:</td>
    <td class="xl77"><?= $jadwalTerjadwal ?> jadwal</td>
    <td colspan="3" class="xl75"></td>
</tr>
<tr>
    <td class="xl74">Kelas Teraktif:</td>
    <td class="xl75"><?php 
        if (!empty($kelasStats)) {
            arsort($kelasStats);
            $topKelas = array_key_first($kelasStats);
            echo htmlspecialchars($topKelas) . ' (' . $kelasStats[$topKelas] . ' jadwal)';
        } else {
            echo '-';
        }
    ?></td>
    <td class="xl74">Instruktur Teraktif:</td>
    <td class="xl75"><?php 
        if (!empty($instrukturStats)) {
            arsort($instrukturStats);
            $topInstruktur = array_key_first($instrukturStats);
            echo htmlspecialchars($topInstruktur) . ' (' . $instrukturStats[$topInstruktur] . ' jadwal)';
        } else {
            echo '-';
        }
    ?></td>
    <td colspan="3" class="xl75"></td>
</tr>
</table>

<!-- Footer -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse; margin-top:30px;">
<tr height="10">
    <td height="10" colspan="7" style="border:none;"></td>
</tr>
<tr>
    <td colspan="4" style="border:none;"></td>
    <td colspan="3" style="border:none; font-weight:bold;">Tabalong, <?= formatTanggalIndonesia() ?></td>
</tr>
<tr>
    <td colspan="4" style="border:none;"></td>
    <td colspan="3" style="border:none;">Mengetahui,</td>
</tr>
<tr height="60">
    <td height="60" colspan="4" style="border:none;"></td>
    <td colspan="3" style="border:none;"></td>
</tr>
<tr>
    <td colspan="4" style="border:none;"></td>
    <td colspan="3" style="border:none; font-weight:bold; text-decoration:underline;">Awiek Hadi Widodo</td>
</tr>
<tr>
    <td colspan="4" style="border:none;"></td>
    <td colspan="3" style="border:none;">Direktur</td>
</tr>
<tr height="20">
    <td height="20" colspan="7" style="border:none;"></td>
</tr>
<tr>
    <td colspan="7" style="border:none; text-align:center; font-size:10pt; color:#666; font-style:italic;">
        Laporan ini dibuat secara otomatis oleh Sistem Informasi LKP Pradata Komputer
    </td>
</tr>
</table>

</body>
</html>