<?php
// File: vendor/fpdf/lkp_pdf_charts_extension.php
// Extension untuk menambahkan chart functionality ke LKP_PDF

// Pastikan LKP_PDF sudah di-load terlebih dahulu
if (!class_exists('LKP_PDF')) {
    require_once(__DIR__ . '/lkp_pdf.php');
}

// Extend LKP_PDF dengan chart functionality
class LKP_PDF_Charts extends LKP_PDF
{
    // Warna untuk chart (professional color scheme)
    private $chart_colors = [
        [70, 130, 180],    // Steel Blue
        [220, 20, 60],     // Crimson
        [50, 205, 50],     // Lime Green
        [255, 165, 0],     // Orange
        [138, 43, 226],    // Blue Violet
        [255, 20, 147],    // Deep Pink
        [32, 178, 170],    // Light Sea Green
        [255, 215, 0]      // Gold
    ];
    
    // Draw Pie Chart
    public function drawPieChart($data, $total, $title = '', $x = null, $y = null, $radius = 40)
    {
        // Default position jika tidak disediakan
        if ($x === null) $x = $this->GetPageWidth() / 2;
        if ($y === null) $y = $this->GetY() + 10;
        
        // Title chart
        if (!empty($title)) {
            $this->SetFont('Arial', 'B', 12);
            $this->SetXY($x - 60, $y - 20);
            $this->Cell(120, 6, $title, 0, 0, 'C');
        }
        
        // Hitung sudut untuk setiap segmen
        $start_angle = 0;
        $color_index = 0;
        
        foreach ($data as $item) {
            $value = (float)$item['jumlah'];
            $percentage = $total > 0 ? ($value / $total) * 100 : 0;
            $angle = ($percentage / 100) * 360;
            
            if ($angle > 0) {
                // Gambar segmen pie
                $this->SetFillColor($this->chart_colors[$color_index % count($this->chart_colors)][0],
                                   $this->chart_colors[$color_index % count($this->chart_colors)][1],
                                   $this->chart_colors[$color_index % count($this->chart_colors)][2]);
                
                $this->drawPieSegment($x, $y, $radius, $start_angle, $start_angle + $angle);
                
                $start_angle += $angle;
                $color_index++;
            }
        }
        
        // Gambar border circle
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.8);
        $this->Circle($x, $y, $radius, 'D');
        
        // Legend di bawah chart (center)
        $legend_y = $y + $radius + 15;
        $legend_x = $x - 80; // Center legend
        $this->drawLegend($data, $total, $legend_x, $legend_y, 160, 'horizontal_center');
    }
    
    // Draw pie segment (helper function)
    private function drawPieSegment($x, $y, $radius, $start_angle, $end_angle)
    {
        $start_rad = deg2rad($start_angle - 90); // -90 untuk mulai dari atas
        $end_rad = deg2rad($end_angle - 90);
        
        // Start dari center
        $this->_out(sprintf('%.2f %.2f m', $x, $y));
        
        // Line ke start point
        $x1 = $x + $radius * cos($start_rad);
        $y1 = $y + $radius * sin($start_rad);
        $this->_out(sprintf('%.2f %.2f l', $x1, $y1));
        
        // Arc - simplified dengan beberapa line segments untuk smoothness
        $segments = max(1, intval(abs($end_angle - $start_angle) / 10)); // 1 segment per 10 derajat
        
        for ($i = 1; $i <= $segments; $i++) {
            $current_angle = $start_rad + ($end_rad - $start_rad) * ($i / $segments);
            $xi = $x + $radius * cos($current_angle);
            $yi = $y + $radius * sin($current_angle);
            $this->_out(sprintf('%.2f %.2f l', $xi, $yi));
        }
        
        // Kembali ke center dan fill
        $this->_out(sprintf('%.2f %.2f l', $x, $y));
        $this->_out('B'); // Fill and stroke
    }
    
    // Draw Circle (helper function)
    private function Circle($x, $y, $r, $style = 'D')
    {
        $op = ($style == 'F') ? 'f' : (($style == 'FD' || $style == 'DF') ? 'B' : 'S');
        
        $this->_out(sprintf('%.2f %.2f m', ($x + $r), $y));
        $this->_out(sprintf('%.2f %.2f %.2f %.2f %.2f %.2f c', 
            ($x + $r), ($y - (4/3) * $r), 
            ($x + (4/3) * $r), ($y - $r), 
            $x, ($y - $r)));
        $this->_out(sprintf('%.2f %.2f %.2f %.2f %.2f %.2f c', 
            ($x - (4/3) * $r), ($y - $r), 
            ($x - $r), ($y - (4/3) * $r), 
            ($x - $r), $y));
        $this->_out(sprintf('%.2f %.2f %.2f %.2f %.2f %.2f c', 
            ($x - $r), ($y + (4/3) * $r), 
            ($x - (4/3) * $r), ($y + $r), 
            $x, ($y + $r)));
        $this->_out(sprintf('%.2f %.2f %.2f %.2f %.2f %.2f c', 
            ($x + (4/3) * $r), ($y + $r), 
            ($x + $r), ($y + (4/3) * $r), 
            ($x + $r), $y));
        $this->_out($op);
    }
    
    // Draw Horizontal Bar Chart
    public function drawBarChart($data, $total, $title = '', $x = 15, $y = null, $width = 260, $height = 50)
    {
        if ($y === null) $y = $this->GetY() + 5;
        
        // Title chart
        if (!empty($title)) {
            $this->SetFont('Arial', 'B', 10);
            $this->SetXY($x, $y);
            $this->Cell($width, 5, $title, 0, 1, 'L');
            $y += 8;
        }
        
        // Find max value untuk scaling
        $max_value = 0;
        foreach ($data as $item) {
            $max_value = max($max_value, (float)$item['jumlah']);
        }
        
        if ($max_value == 0) return; // Avoid division by zero
        
        $bar_height = 8;
        $bar_spacing = 2;
        $current_y = $y;
        $color_index = 0;
        
        foreach ($data as $item) {
            $value = (float)$item['jumlah'];
            $percentage = $total > 0 ? ($value / $total) * 100 : 0;
            $bar_width = ($value / $max_value) * ($width - 80); // 80mm untuk label
            
            // Label kategori (kiri)
            $this->SetFont('Arial', '', 8);
            $this->SetXY($x, $current_y);
            $this->Cell(70, $bar_height, $this->truncateText($item['kategori'], 20), 0, 0, 'L');
            
            // Bar
            $this->SetFillColor($this->chart_colors[$color_index % count($this->chart_colors)][0],
                               $this->chart_colors[$color_index % count($this->chart_colors)][1],
                               $this->chart_colors[$color_index % count($this->chart_colors)][2]);
            $this->Rect($x + 75, $current_y + 1, $bar_width, $bar_height - 2, 'F');
            
            // Border bar
            $this->SetDrawColor(0, 0, 0);
            $this->Rect($x + 75, $current_y + 1, $bar_width, $bar_height - 2, 'D');
            
            // Value label (kanan bar)
            $this->SetXY($x + 80 + $bar_width, $current_y);
            $this->Cell(30, $bar_height, $value . ' (' . number_format($percentage, 1) . '%)', 0, 0, 'L');
            
            $current_y += $bar_height + $bar_spacing;
            $color_index++;
        }
        
        $this->SetY($current_y + 5);
    }
    
    // Draw Column Chart (Vertical Bar)
    public function drawColumnChart($data, $total, $title = '', $x = 20, $y = null, $width = 250, $height = 50)
    {
        if ($y === null) $y = $this->GetY() + 5;
        
        // Title chart
        if (!empty($title)) {
            $this->SetFont('Arial', 'B', 10);
            $this->SetXY($x, $y);
            $this->Cell($width, 5, $title, 0, 1, 'L');
            $y += 8;
        }
        
        // Find max value untuk scaling
        $max_value = 0;
        foreach ($data as $item) {
            $max_value = max($max_value, (float)$item['jumlah']);
        }
        
        if ($max_value == 0 || count($data) == 0) return;
        
        $chart_area_width = $width - 20; // margin
        $chart_area_height = $height;
        $column_width = ($chart_area_width / count($data)) - 5; // 5mm spacing
        $current_x = $x + 10;
        $base_y = $y + $chart_area_height;
        $color_index = 0;
        
        // Draw axes
        $this->SetDrawColor(0, 0, 0);
        $this->Line($x + 5, $base_y, $x + $width - 5, $base_y); // X axis
        $this->Line($x + 5, $y, $x + 5, $base_y); // Y axis
        
        foreach ($data as $item) {
            $value = (float)$item['jumlah'];
            $percentage = $total > 0 ? ($value / $total) * 100 : 0;
            $column_height = ($value / $max_value) * $chart_area_height;
            
            // Column
            $this->SetFillColor($this->chart_colors[$color_index % count($this->chart_colors)][0],
                               $this->chart_colors[$color_index % count($this->chart_colors)][1],
                               $this->chart_colors[$color_index % count($this->chart_colors)][2]);
            $this->Rect($current_x, $base_y - $column_height, $column_width, $column_height, 'F');
            
            // Border column
            $this->SetDrawColor(0, 0, 0);
            $this->Rect($current_x, $base_y - $column_height, $column_width, $column_height, 'D');
            
            // Value label di atas column
            $this->SetFont('Arial', '', 7);
            $this->SetXY($current_x, $base_y - $column_height - 6);
            $this->Cell($column_width, 4, $value, 0, 0, 'C');
            
            // Category label di bawah axis (rotasi sederhana dengan abbreviation)
            $this->SetFont('Arial', '', 6);
            $this->SetXY($current_x, $base_y + 2);
            $short_label = $this->abbreviateAgeGroup($item['kategori']);
            $this->Cell($column_width, 4, $short_label, 0, 0, 'C');
            
            $current_x += $column_width + 5;
            $color_index++;
        }
        
        // Y-axis labels (simplified)
        $this->SetFont('Arial', '', 6);
        $this->SetXY($x - 8, $y - 2);
        $this->Cell(10, 4, $max_value, 0, 0, 'R');
        $this->SetXY($x - 8, $base_y - 2);
        $this->Cell(10, 4, '0', 0, 0, 'R');
        
        $this->SetY($base_y + 15);
    }
    
    // Draw Legend
    private function drawLegend($data, $total, $x, $y, $width, $orientation = 'horizontal')
    {
        $this->SetFont('Arial', '', 9);
        $current_y = $y;
        $current_x = $x;
        $color_index = 0;
        
        foreach ($data as $item) {
            $value = (float)$item['jumlah'];
            $percentage = $total > 0 ? round($value / $total * 100, 1) : 0;
            
            if ($value > 0) {
                // Color box
                $this->SetFillColor($this->chart_colors[$color_index % count($this->chart_colors)][0],
                                   $this->chart_colors[$color_index % count($this->chart_colors)][1],
                                   $this->chart_colors[$color_index % count($this->chart_colors)][2]);
                $this->Rect($current_x, $current_y, 5, 5, 'F');
                $this->SetDrawColor(0, 0, 0);
                $this->Rect($current_x, $current_y, 5, 5, 'D');
                
                // Text
                if ($orientation == 'horizontal_center') {
                    // Legend horizontal center untuk pie chart
                    $this->SetXY($current_x + 8, $current_y);
                    $text = $item['kategori'] . ': ' . number_format($value) . ' (' . $percentage . '%)';
                    $this->Cell(0, 5, $text, 0, 1, 'L');
                    $current_y += 7;
                } elseif ($orientation == 'vertical') {
                    $this->SetXY($current_x + 6, $current_y - 1);
                    $text = $item['kategori'] . ': ' . $value . ' (' . number_format($percentage, 1) . '%)';
                    $this->Cell($width - 6, 5, $text, 0, 1, 'L');
                    $current_y += 6;
                } else {
                    $this->SetXY($current_x + 6, $current_y - 1);
                    $text = $item['kategori'] . ': ' . $value . ' (' . number_format($percentage, 1) . '%)';
                    $this->Cell($width - 6, 4, $text, 0, 1, 'L');
                    $current_y += 5;
                }
                
                $color_index++;
            }
        }
    }
    
    // Helper function untuk abbreviate age groups
    private function abbreviateAgeGroup($ageGroup)
    {
        $abbreviations = [
            '17-20 tahun' => '17-20',
            '21-25 tahun' => '21-25',
            '26-30 tahun' => '26-30',
            '31-35 tahun' => '31-35',
            '36+ tahun' => '36+'
        ];
        
        return $abbreviations[$ageGroup] ?? substr($ageGroup, 0, 8);
    }
    
    // Helper function untuk truncate text (dari parent class)
    private function truncateText($text, $maxLength)
    {
        if (strlen($text) > $maxLength) {
            return substr($text, 0, $maxLength - 3) . '...';
        }
        return $text;
    }
}

// Update LKP_ReportFactory untuk menggunakan LKP_PDF_Charts
class LKP_ReportFactory_Charts 
{
    // Laporan dengan chart capability
    public static function createGrafikPendaftarReport() {
        return new LKP_PDF_Charts('L', 'mm', 'A4'); // Landscape untuk chart
    }
    
    // Laporan lainnya tetap menggunakan LKP_PDF biasa
    public static function createSiswaReport() {
        return LKP_PDF::createAuto(7);
    }
    
    public static function createInstrukturReport() {
        return LKP_PDF::createAuto(6);
    }
    
    public static function createPendaftarReport() {
        return LKP_PDF::createAuto(6);
    }
}
?>