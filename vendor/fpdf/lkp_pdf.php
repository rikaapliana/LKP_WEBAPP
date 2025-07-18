<?php
// File: vendor/fpdf/lkp_pdf.php
// Library FPDF custom sesuai format laporan existing LKP Pradata Komputer

require_once(__DIR__ . '/fpdf.php');

class LKP_PDF extends FPDF
{
    private $title = '';
    private $subtitle = '';
    private $logo_path = '';
    private $filter_info = [];
    private $total_records = 0;
    private $printed_by = 'Administrator Sistem';
    private $is_landscape = false;
    
    public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4')
    {
        parent::__construct($orientation, $unit, $size);
        $this->SetAutoPageBreak(true, 30);
        $this->SetMargins(10, 10, 10);
        $this->is_landscape = ($orientation == 'L');
        
        // Set timezone WITA (sesuai permintaan)
        date_default_timezone_set('Asia/Makassar');
    }
    
    // Factory method - otomatis pilih orientation berdasarkan jumlah kolom
    public static function createAuto($columns_count = 10)
    {
        // Jika kolom > 8, gunakan landscape. Jika <= 8, gunakan portrait
        if ($columns_count > 8) {
            return new self('L', 'mm', 'A4'); // Landscape
        } else {
            return new self('P', 'mm', 'A4'); // Portrait
        }
    }
    
    // Set informasi laporan
    public function setReportInfo($title, $subtitle = '', $logo_path = '', $filter_info = [], $total_records = 0, $printed_by = 'Administrator Sistem')
    {
        $this->title = $title;
        $this->subtitle = $subtitle;
        $this->logo_path = $logo_path;
        $this->filter_info = $filter_info;
        $this->total_records = $total_records;
        $this->printed_by = $printed_by;
    }
    
    // Format tanggal Indonesia
    private function formatTanggalIndonesia($date = null, $withTime = false)
    {
        $bulan = array(
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        );
        
        $timestamp = $date ? strtotime($date) : time();
        
        if ($withTime) {
            $hari = date('d', $timestamp);
            $bulanNum = date('n', $timestamp);
            $tahun = date('Y', $timestamp);
            $jam = date('H:i:s', $timestamp);
            return $hari . ' ' . $bulan[$bulanNum] . ' ' . $tahun . ', ' . $jam;
        } else {
            $hari = date('d', $timestamp);
            $bulanNum = date('n', $timestamp);
            $tahun = date('Y', $timestamp);
            return $hari . ' ' . $bulan[$bulanNum] . ' ' . $tahun;
        }
    }
    
    // Fungsi terbilang
    private function terbilang($angka)
    {
        $angka = abs($angka);
        $baca = array("", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan", "sepuluh", "sebelas");
        $terbilang = "";
        
        if ($angka < 12) {
            $terbilang = " " . $baca[$angka];
        } else if ($angka < 20) {
            $terbilang = $this->terbilang($angka - 10) . " belas";
        } else if ($angka < 100) {
            $terbilang = $this->terbilang($angka / 10) . " puluh" . $this->terbilang($angka % 10);
        } else if ($angka < 200) {
            $terbilang = " seratus" . $this->terbilang($angka - 100);
        } else if ($angka < 1000) {
            $terbilang = $this->terbilang($angka / 100) . " ratus" . $this->terbilang($angka % 100);
        } else if ($angka < 2000) {
            $terbilang = " seribu" . $this->terbilang($angka - 1000);
        } else if ($angka < 1000000) {
            $terbilang = $this->terbilang($angka / 1000) . " ribu" . $this->terbilang($angka % 1000);
        }
        
        return trim($terbilang);
    }
    
    // Header halaman - meta info hanya di halaman pertama, logo lebih dekat
    function Header()
    {
        // Logo di atas center - jarak lebih dekat
        if (!empty($this->logo_path) && file_exists($this->logo_path)) {
            // Hitung posisi center untuk logo
            $logo_width = 25;
            $page_width = $this->GetPageWidth();
            $logo_x = ($page_width - $logo_width) / 2;
            $this->Image($this->logo_path, $logo_x, 10, $logo_width);
            $start_y = 32; // Start text lebih dekat dengan logo (sebelum: 40)
        } else {
            $start_y = 15; // Start text tanpa logo
        }
        
        // Nama Lembaga - center
        $this->SetY($start_y);
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 8, 'LEMBAGA KURSUS DAN PELATIHAN PRADATA KOMPUTER', 0, 1, 'C');
        
        // Alamat - center, font lebih kecil
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 5, 'Jl. Ketimun S. 21 No. 3A Komplek Pertamina. Tanjung - Tabalong - Kalimantan Selatan 71571', 0, 1, 'C');
        
        // Kontak - center, font lebih kecil
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 5, 'Telp: (0526) 2023798 | Email: awiekpradata@gmail.com | Website: www.pradatacomputer.ac.id', 0, 1, 'C');
        
        // Garis horizontal double
        $this->SetLineWidth(0.8);
        $this->Line(10, $this->GetY() + 5, $this->GetPageWidth() - 10, $this->GetY() + 5);
        $this->SetLineWidth(0.3);
        $this->Line(10, $this->GetY() + 7, $this->GetPageWidth() - 10, $this->GetY() + 7);
        
        // Document Header - HANYA di halaman pertama
        if ($this->PageNo() == 1) {
            $this->Ln(15);
            $this->SetFont('Arial', 'B', 14);
            $this->Cell(0, 8, strtoupper($this->title), 0, 1, 'C');
            
            if (!empty($this->subtitle)) {
                $this->SetFont('Arial', 'I', 11);
                $this->Cell(0, 6, $this->subtitle, 0, 1, 'C');
            } else {
                $this->SetFont('Arial', 'I', 11);
                $this->Cell(0, 6, 'Periode ' . $this->formatTanggalIndonesia(), 0, 1, 'C');
            }
            
            $this->Ln(10);
            
            // Document Meta - 1 KOLOM SAJA (tidak dibagi 2)
            $this->SetFont('Arial', '', 9);
            
            $this->Cell(30, 5, 'Nomor Dokumen', 0, 0, 'L');
            $this->Cell(5, 5, ':', 0, 0, 'C');
            $this->Cell(0, 5, date('Y') . '/LKP-PC/' . date('m') . '/' . str_pad(date('d'), 3, '0', STR_PAD_LEFT), 0, 1, 'L');
            
            $this->Cell(30, 5, 'Tanggal Cetak', 0, 0, 'L');
            $this->Cell(5, 5, ':', 0, 0, 'C');
            $this->Cell(0, 5, $this->formatTanggalIndonesia(null, true) . ' WITA', 0, 1, 'L');
            
            $this->Cell(30, 5, 'Dicetak Oleh', 0, 0, 'L');
            $this->Cell(5, 5, ':', 0, 0, 'C');
            $this->Cell(0, 5, $this->printed_by, 0, 1, 'L');
            
            $this->Cell(30, 5, 'Total Record', 0, 0, 'L');
            $this->Cell(5, 5, ':', 0, 0, 'C');
            $this->Cell(0, 5, $this->total_records . ' (' . $this->terbilang($this->total_records) . ') siswa', 0, 1, 'L');
            
            // Filter Information - jika ada
            if (!empty($this->filter_info)) {
                $this->Ln(8);
                $this->SetFont('Arial', 'B', 9);
                $this->Cell(0, 5, 'Filter yang Diterapkan:', 0, 1, 'L');
                $this->SetFont('Arial', '', 8);
                
                foreach ($this->filter_info as $filter) {
                    // Gunakan nomor urut sebagai pengganti bullet
                    static $counter = 0;
                    $counter++;
                    
                    // JARAK 5MM - Nomor lebih dekat dengan tulisan
                    $this->Cell(5, 4, $counter . '.', 0, 0, 'L');   // 5mm = jarak rapat
                    $this->Cell(0, 4, ' ' . $filter, 0, 1, 'L');   // Tambah spasi kecil di depan
                }
            }
        } else {
            // Halaman 2 dst - hanya spasi kecil setelah garis
            $this->Ln(8);
        }
        
        $this->Ln(10);
    }
    
    // Footer halaman - sesuai format existing
    function Footer()
    {
        $this->SetY(-25);
        
        // Garis horizontal
        $this->SetLineWidth(0.3);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        
        $this->Ln(3);
        
        // Catatan
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(0, 4, 'Catatan:', 0, 1, 'L');
        $this->SetFont('Arial', '', 8);
        
        // JARAK 5MM untuk Catatan di Footer:
        $this->Cell(5, 3, '1.', 0, 0, 'L');   // 5mm = jarak rapat
        $this->Cell(0, 3, ' Laporan ini dicetak secara otomatis dari sistem informasi LKP Pradata Komputer', 0, 1, 'L');
        $this->Cell(5, 3, '2.', 0, 0, 'L');
        $this->Cell(0, 3, ' Total record yang ditampilkan: ' . $this->total_records . ' siswa', 0, 1, 'L');
        $this->Cell(5, 3, '3.', 0, 0, 'L');
        $this->Cell(0, 3, ' Keterangan: L = Laki-laki, P = Perempuan', 0, 1, 'L');
        
        // Info halaman di kanan bawah
        $this->SetY(-8);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 4, 'Halaman ' . $this->PageNo() . ' dari {nb}', 0, 0, 'R');
    }
    
    // Tabel data siswa - versi compact (kolom penting saja agar muat)
    public function createSiswaTableCompact($data)
    {
        // Header tabel - hanya kolom penting yang muat di kertas
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(220, 220, 220);
        
        // Lebar kolom untuk versi compact - HANYA 7 kolom penting
        if ($this->is_landscape) {
            // Landscape - 7 kolom
            $w = [20, 45, 60, 35, 25, 15, 40]; // Total ~240mm (landscape ~297mm)
            $headers = ['NO', 'NIK', 'NAMA LENGKAP', 'TEMPAT LAHIR', 'TGL LAHIR', 'JK', 'KELAS'];
        } else {
            // Portrait - 6 kolom saja agar muat
            $w = [15, 35, 50, 30, 22, 38]; // Total ~190mm (portrait ~210mm)
            $headers = ['NO', 'NIK', 'NAMA LENGKAP', 'TEMPAT LAHIR', 'TGL LAHIR', 'KELAS'];
        }
        
        for ($i = 0; $i < count($headers); $i++) {
            $this->Cell($w[$i], 8, $headers[$i], 1, 0, 'C', true);
        }
        $this->Ln();
        
        // Data tabel
        $font_size = $this->is_landscape ? 9 : 8;
        $this->SetFont('Arial', '', $font_size);
        $this->SetFillColor(255, 255, 255);
        
        $no = 1;
        foreach ($data as $row) {
            // Zebra striping
            if ($no % 2 == 0) {
                $this->SetFillColor(248, 248, 248);
            } else {
                $this->SetFillColor(255, 255, 255);
            }
            
            // Auto adjust text length berdasarkan orientation
            if ($this->is_landscape) {
                $name_length = 35;
                $tempat_length = 20;
                $kelas_length = 25;
                
                $this->Cell($w[0], 7, $no++, 1, 0, 'C', true);
                $this->Cell($w[1], 7, $this->truncateText($row['nik'] ?? '', 22), 1, 0, 'C', true);
                $this->Cell($w[2], 7, $this->truncateText($row['nama'] ?? '', $name_length), 1, 0, 'L', true);
                $this->Cell($w[3], 7, $this->truncateText($row['tempat_lahir'] ?? '', $tempat_length), 1, 0, 'L', true);
                $this->Cell($w[4], 7, isset($row['tanggal_lahir']) ? date('d/m/Y', strtotime($row['tanggal_lahir'])) : '', 1, 0, 'C', true);
                $this->Cell($w[5], 7, ($row['jenis_kelamin'] ?? '') == 'Laki-Laki' ? 'L' : 'P', 1, 0, 'C', true);
                
                // Kelas dan gelombang
                $kelas_text = '';
                if (!empty($row['nama_kelas'])) {
                    $kelas_text = $row['nama_kelas'];
                    if (!empty($row['nama_gelombang'])) {
                        $kelas_text .= ' (' . $row['nama_gelombang'] . ')';
                    }
                } else {
                    $kelas_text = '-';
                }
                $this->Cell($w[6], 7, $this->truncateText($kelas_text, $kelas_length), 1, 0, 'L', true);
                
            } else {
                // Portrait - 6 kolom tanpa JK
                $name_length = 28;
                $tempat_length = 18;
                $kelas_length = 22;
                
                $this->Cell($w[0], 7, $no++, 1, 0, 'C', true);
                $this->Cell($w[1], 7, $this->truncateText($row['nik'] ?? '', 17), 1, 0, 'C', true);
                $this->Cell($w[2], 7, $this->truncateText($row['nama'] ?? '', $name_length), 1, 0, 'L', true);
                $this->Cell($w[3], 7, $this->truncateText($row['tempat_lahir'] ?? '', $tempat_length), 1, 0, 'L', true);
                $this->Cell($w[4], 7, isset($row['tanggal_lahir']) ? date('d/m/Y', strtotime($row['tanggal_lahir'])) : '', 1, 0, 'C', true);
                
                // Kelas dan gelombang
                $kelas_text = '';
                if (!empty($row['nama_kelas'])) {
                    $kelas_text = $row['nama_kelas'];
                    if (!empty($row['nama_gelombang'])) {
                        $kelas_text .= ' (' . $row['nama_gelombang'] . ')';
                    }
                } else {
                    $kelas_text = '-';
                }
                $this->Cell($w[5], 7, $this->truncateText($kelas_text, $kelas_length), 1, 0, 'L', true);
            }
            
            $this->Ln();
        }
    }
    
    // Update method utama untuk gunakan versi compact
    public function createSiswaTable($data)
    {
        // Gunakan versi compact secara default
        $this->createSiswaTableCompact($data);
    }
    
    // Tabel fleksibel untuk laporan lain (pendaftar, nilai, dll)
    public function createTable($headers, $data, $column_widths = [], $options = [])
    {
        // Auto determine orientation berdasarkan jumlah kolom
        $col_count = count($headers);
        $auto_landscape = $col_count > 8;
        
        // Default options
        $defaults = [
            'header_bg' => [70, 130, 180],
            'header_text' => [255, 255, 255],
            'row_bg_1' => [255, 255, 255],
            'row_bg_2' => [245, 245, 245],
            'border' => true,
            'zebra' => true,
            'font_size' => $auto_landscape ? 8 : 7,
            'header_font_size' => $auto_landscape ? 9 : 8,
            'cell_height' => 6,
            'header_height' => 8
        ];
        
        $options = array_merge($defaults, $options);
        
        // Auto calculate column widths jika tidak disediakan
        if (empty($column_widths)) {
            $table_width = $this->is_landscape ? 277 : 190; // margin kiri kanan
            $column_widths = array_fill(0, $col_count, $table_width / $col_count);
        }
        
        // Header tabel
        $this->SetFont('Arial', 'B', $options['header_font_size']);
        $this->SetFillColor($options['header_bg'][0], $options['header_bg'][1], $options['header_bg'][2]);
        $this->SetTextColor($options['header_text'][0], $options['header_text'][1], $options['header_text'][2]);
        
        for ($i = 0; $i < count($headers); $i++) {
            $border = $options['border'] ? 1 : 0;
            $this->Cell($column_widths[$i], $options['header_height'], $headers[$i], $border, 0, 'C', true);
        }
        $this->Ln();
        
        // Data tabel
        $this->SetFont('Arial', '', $options['font_size']);
        $this->SetTextColor(0, 0, 0);
        
        $row_num = 0;
        foreach ($data as $row) {
            // Zebra striping
            if ($options['zebra'] && $row_num % 2 == 1) {
                $this->SetFillColor($options['row_bg_2'][0], $options['row_bg_2'][1], $options['row_bg_2'][2]);
                $fill = true;
            } else {
                $this->SetFillColor($options['row_bg_1'][0], $options['row_bg_1'][1], $options['row_bg_1'][2]);
                $fill = $options['zebra'];
            }
            
            $col_num = 0;
            foreach ($row as $cell) {
                if ($col_num < count($column_widths)) {
                    $border = $options['border'] ? 1 : 0;
                    $align = is_numeric($cell) ? 'R' : 'L';
                    if ($col_num == 0) $align = 'C'; // Nomor urut center
                    
                    // Auto truncate berdasarkan column width
                    $max_chars = floor($column_widths[$col_num] * 2.5); // estimasi karakter per mm
                    
                    $this->Cell($column_widths[$col_num], $options['cell_height'], 
                               $this->truncateText($cell, $max_chars), 
                               $border, 0, $align, $fill);
                    $col_num++;
                }
            }
            $this->Ln();
            $row_num++;
        }
    }
    
    // Tanda tangan - dengan proteksi agar tidak terpisah halaman
    public function addSignature()
    {
        // Cek apakah signature muat di halaman ini (butuh ~35mm)
        $signature_height = 35;
        $current_y = $this->GetY();
        $page_height = $this->GetPageHeight();
        $bottom_margin = 25; // margin bottom dari constructor
        
        // Jika tidak muat, buat halaman baru
        if (($current_y + $signature_height) > ($page_height - $bottom_margin)) {
            $this->AddPage();
        }
        
        $this->Ln(10);
        
        // Posisi kanan
        $this->SetX(140);
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, 'Tabalong, ' . $this->formatTanggalIndonesia(), 0, 1, 'L');
        
        $this->SetX(140);
        $this->Cell(0, 5, 'Mengetahui,', 0, 1, 'L');
        
        $this->Ln(15); // Space untuk tanda tangan
        
        $this->SetX(140);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 5, 'Awiek Hadi Widodo', 0, 1, 'L');
        
        $this->SetX(140);
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, 'Direktur', 0, 1, 'L');
    }
    
    // Utility function
    private function truncateText($text, $maxLength)
    {
        if (strlen($text) > $maxLength) {
            return substr($text, 0, $maxLength - 3) . '...';
        }
        return $text;
    }
    
    // Override untuk menghitung total halaman
    public function AliasNbPages($alias = '{nb}')
    {
        parent::AliasNbPages($alias);
    }
}

// Shortcut functions untuk berbagai jenis laporan
class LKP_ReportFactory 
{
    // Laporan Siswa (7 kolom landscape, 6 kolom portrait - auto pilih)
    public static function createSiswaReport() {
        return LKP_PDF::createAuto(7); // Landscape untuk 7 kolom
    }
    
    // Laporan Pendaftar (6 kolom - auto portrait)  
    public static function createPendaftarReport() {
        return LKP_PDF::createAuto(6);
    }
    
    // Laporan Nilai (12+ kolom - auto landscape)
    public static function createNilaiReport() {
        return LKP_PDF::createAuto(12);
    }
    
    // Laporan Evaluasi (5 kolom - auto portrait)
    public static function createEvaluasiReport() {
        return LKP_PDF::createAuto(5);
    }
    
    // Laporan Compact - selalu portrait dengan kolom minimal
    public static function createCompactReport() {
        return new LKP_PDF('P', 'mm', 'A4');
    }
}
?>