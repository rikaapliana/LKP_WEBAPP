<?php
session_start();
require_once '../../../includes/auth.php';
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'pendaftar';
$baseURL = '../';

// Ambil data untuk filter dropdown - initial load
$queryGelombang = "
    SELECT DISTINCT 
        g.id_gelombang,
        g.nama_gelombang,
        g.gelombang_ke,
        g.tahun,
        COUNT(p.id_pendaftar) as jumlah_pendaftar
    FROM gelombang g
    LEFT JOIN pendaftar p ON g.id_gelombang = p.id_gelombang
    GROUP BY g.id_gelombang, g.nama_gelombang, g.gelombang_ke, g.tahun
    HAVING jumlah_pendaftar > 0
    ORDER BY g.tahun DESC, g.gelombang_ke DESC
";
$resultGelombang = mysqli_query($conn, $queryGelombang);

$queryTahun = "
    SELECT DISTINCT 
        g.tahun,
        COUNT(p.id_pendaftar) as jumlah_pendaftar
    FROM gelombang g
    LEFT JOIN pendaftar p ON g.id_gelombang = p.id_gelombang
    GROUP BY g.tahun
    HAVING jumlah_pendaftar > 0
    ORDER BY g.tahun DESC
";
$resultTahun = mysqli_query($conn, $queryTahun);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grafik Statistik Pendaftar - LKP Pradata Komputer</title>
    <link rel="icon" type="image/png" href="../../../assets/img/favicon.png"/>
    
    <!-- Offline CSS Files -->
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="../../../assets/css/bootstrap-icons.css" />
    <link rel="stylesheet" href="../../../assets/css/fonts.css" />
    <link rel="stylesheet" href="../../../assets/css/styles.css" />
    
    <style>
        .chart-container {
            position: relative;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            border: 1px solid #e9ecef;
            padding: 24px;
            margin-bottom: 24px;
            transition: all 0.3s ease;
        }
        
        .chart-container:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .chart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f8f9fa;
        }
        
        .chart-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .chart-title i {
            margin-right: 8px;
            color: #0d6efd;
        }
        
        .chart-subtitle {
            font-size: 0.875rem;
            color: #6c757d;
            margin: 4px 0 0 0;
        }
        
        .chart-canvas-wrapper {
            position: relative;
            height: 350px;
            width: 100%;
        }
        
        .filter-section {
            background: linear-gradient(135deg, #0d6efd 0%, #0052cc 100%);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            color: white;
        }
        
        .filter-section h5 {
            color: white;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .filter-section .form-select {
            background-color: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
        }
        
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            z-index: 10;
        }
        
        .loading-spinner {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }
        
        .spinner-border-custom {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #0d6efd;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .stats-grid {
            margin-bottom: 24px;
        }
        
        .bg-primary-light { background-color: rgba(13, 110, 253, 0.1); }
        .bg-success-light { background-color: rgba(25, 135, 84, 0.1); }
        .bg-info-light { background-color: rgba(13, 202, 240, 0.1); }
        .bg-warning-light { background-color: rgba(255, 193, 7, 0.1); }
        .bg-pink-light { background-color: rgba(214, 51, 132, 0.1); }
        
        .text-pink { color: #d63384; }
        
        .no-data-message {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        
        .no-data-message i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        /* Fallback untuk Chart.js tidak tersedia */
        .chart-fallback {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 300px;
            background: #f8f9fa;
            border-radius: 8px;
            color: #6c757d;
            flex-direction: column;
            text-align: center;
            padding: 20px;
        }
        
        .chart-fallback i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        .chart-fallback .fallback-title {
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .chart-fallback .fallback-message {
            font-size: 0.875rem;
            color: #868e96;
        }
        
        /* Chart.js offline indicator */
        .offline-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #dc3545;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.875rem;
            z-index: 1050;
            display: none;
        }
        
        .offline-indicator.show {
            display: block;
        }
        
        @media (max-width: 768px) {
            .chart-container {
                margin-bottom: 16px;
                padding: 16px;
            }
            
            .chart-canvas-wrapper {
                height: 300px;
            }
            
            .filter-section {
                padding: 20px;
            }
            
            .offline-indicator {
                top: 10px;
                right: 10px;
                font-size: 0.75rem;
                padding: 6px 12px;
            }
        }
    </style>
</head>

<body>
    <!-- Offline Indicator -->
    <div class="offline-indicator" id="offlineIndicator">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Chart.js tidak tersedia - Mode offline
    </div>

    <div class="d-flex">
        <?php include '../../../includes/sidebar/admin.php'; ?>

        <div class="flex-fill main-content">
            <!-- TOP NAVBAR -->
            <nav class="top-navbar">
                <div class="container-fluid px-3 px-md-4">
                    <div class="d-flex align-items-center">
                        <!-- Left: Hamburger + Page Info -->
                        <div class="d-flex align-items-center flex-grow-1">
                            <!-- Sidebar Toggle Button -->
                            <button class="btn btn-link text-dark p-2 me-3 sidebar-toggle" type="button" id="sidebarToggle">
                                <i class="bi bi-list fs-4"></i>
                            </button>
                            
                            <!-- Page Title & Breadcrumb -->
                            <div class="page-info">
                                <h2 class="page-title mb-1">GRAFIK STATISTIK PENDAFTAR</h2>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb page-breadcrumb mb-0">
                                        <li class="breadcrumb-item">
                                            <a href="../dashboard.php">Dashboard</a>
                                        </li>
                                        <li class="breadcrumb-item">
                                            <a href="index.php">Data Pendaftar</a>
                                        </li>
                                        <li class="breadcrumb-item active" aria-current="page">Grafik Statistik</li>
                                    </ol>
                                </nav>
                            </div>
                        </div>
                        
                        <!-- Right: Optional Info -->
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
                <div id="alertContainer"></div>

                <!-- Filter Section -->
                <div class="filter-section">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="mb-3">
                                <i class="bi bi-funnel me-2"></i>Filter Statistik Pendaftar
                            </h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-white">Filter Gelombang:</label>
                                    <select class="form-select" id="filterGelombang">
                                        <option value="">Semua Gelombang</option>
                                        <?php while($gelombang = mysqli_fetch_assoc($resultGelombang)): ?>
                                            <option value="<?= $gelombang['id_gelombang'] ?>">
                                                <?= htmlspecialchars($gelombang['nama_gelombang']) ?> 
                                                (<?= $gelombang['tahun'] ?>) - <?= $gelombang['jumlah_pendaftar'] ?> pendaftar
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-white">Filter Tahun:</label>
                                    <select class="form-select" id="filterTahun">
                                        <option value="">Semua Tahun</option>
                                        <?php while($tahun = mysqli_fetch_assoc($resultTahun)): ?>
                                            <option value="<?= $tahun['tahun'] ?>">
                                                Tahun <?= $tahun['tahun'] ?> - <?= $tahun['jumlah_pendaftar'] ?> pendaftar
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            <button class="btn btn-light btn-lg" id="applyFilter">
                                <i class="bi bi-bar-chart me-2"></i>Update Grafik
                            </button>
                            <button class="btn btn-outline-light ms-2" id="resetFilter">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4" id="statsContainer">
                    <!-- Stats cards akan diisi oleh JavaScript -->
                </div>

                <!-- Charts Grid -->
                <div class="row">
                    <!-- Chart 1: Jenis Kelamin -->
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="chart-container">
                            <div class="chart-header">
                                <div>
                                    <h6 class="chart-title">
                                        <i class="bi bi-people"></i>
                                        Berdasarkan Jenis Kelamin
                                    </h6>
                                    <p class="chart-subtitle">Distribusi pendaftar laki-laki dan perempuan</p>
                                </div>
                            </div>
                            <div class="chart-canvas-wrapper">
                                <canvas id="chartJenisKelamin"></canvas>
                                <div class="loading-overlay" id="loadingJK">
                                    <div class="loading-spinner">
                                        <div class="spinner-border-custom"></div>
                                        <small>Memuat data...</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Chart 2: Pendidikan -->
                    <div class="col-lg-8 col-md-6 mb-4">
                        <div class="chart-container">
                            <div class="chart-header">
                                <div>
                                    <h6 class="chart-title">
                                        <i class="bi bi-mortarboard"></i>
                                        Berdasarkan Pendidikan Terakhir
                                    </h6>
                                    <p class="chart-subtitle">Jumlah pendaftar per jenjang pendidikan</p>
                                </div>
                            </div>
                            <div class="chart-canvas-wrapper">
                                <canvas id="chartPendidikan"></canvas>
                                <div class="loading-overlay" id="loadingPendidikan">
                                    <div class="loading-spinner">
                                        <div class="spinner-border-custom"></div>
                                        <small>Memuat data...</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Chart 3: Usia -->
                    <div class="col-12 mb-4">
                        <div class="chart-container">
                            <div class="chart-header">
                                <div>
                                    <h6 class="chart-title">
                                        <i class="bi bi-calendar-range"></i>
                                        Berdasarkan Kategori Usia
                                    </h6>
                                    <p class="chart-subtitle">Distribusi pendaftar berdasarkan kelompok usia</p>
                                </div>
                            </div>
                            <div class="chart-canvas-wrapper">
                                <canvas id="chartUsia"></canvas>
                                <div class="loading-overlay" id="loadingUsia">
                                    <div class="loading-spinner">
                                        <div class="spinner-border-custom"></div>
                                        <small>Memuat data...</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Offline JavaScript Files -->
    <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../../../assets/js/scripts.js"></script>
    
    <!-- Chart.js - OFFLINE VERSION -->
    <script src="../../../assets/js/chart.min.js"></script>

    <script>
    // Global variables
    let chartJenisKelamin = null;
    let chartPendidikan = null;
    let chartUsia = null;
    let isChartJsAvailable = false;

    // Check if Chart.js loaded successfully
    try {
        if (typeof Chart !== 'undefined') {
            isChartJsAvailable = true;
            console.log('Chart.js berhasil dimuat - Mode online');
        } else {
            throw new Error('Chart.js tidak tersedia');
        }
    } catch (error) {
        console.warn('Chart.js tidak tersedia - Mode fallback aktif');
        isChartJsAvailable = false;
        
        // Show offline indicator
        const offlineIndicator = document.getElementById('offlineIndicator');
        if (offlineIndicator) {
            offlineIndicator.classList.add('show');
        }
        
        // Show warning alert
        setTimeout(() => {
            showAlert('Chart.js tidak dapat dimuat. Pastikan file chart.min.js ada di folder assets/js/', 'warning');
        }, 1000);
    }

    // Chart configurations
    const chartColors = {
        primary: '#0d6efd',
        success: '#198754',
        danger: '#dc3545',
        warning: '#ffc107',
        info: '#0dcaf0',
        purple: '#6f42c1',
        pink: '#d63384',
        orange: '#fd7e14'
    };

    const genderColors = {
        'Laki-Laki': chartColors.primary,
        'Perempuan': chartColors.pink
    };

    const educationColors = [
        chartColors.danger,
        chartColors.warning,
        chartColors.success,
        chartColors.info,
        chartColors.purple,
        chartColors.primary,
        chartColors.orange,
        chartColors.pink
    ];

    const ageColors = [
        '#FF6384',
        '#36A2EB', 
        '#FFCE56',
        '#4BC0C0',
        '#9966FF'
    ];

    // DOM Elements
    const filterGelombang = document.getElementById('filterGelombang');
    const filterTahun = document.getElementById('filterTahun');
    const applyFilterBtn = document.getElementById('applyFilter');
    const resetFilterBtn = document.getElementById('resetFilter');
    const alertContainer = document.getElementById('alertContainer');
    const statsContainer = document.getElementById('statsContainer');

    // Utility functions
    function showLoading(chartId) {
        const loadingEl = document.getElementById(`loading${chartId}`);
        if (loadingEl) {
            loadingEl.style.display = 'flex';
        }
    }

    function hideLoading(chartId) {
        const loadingEl = document.getElementById(`loading${chartId}`);
        if (loadingEl) {
            loadingEl.style.display = 'none';
        }
    }

    function showAlert(message, type = 'danger') {
        const alertHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        alertContainer.innerHTML = alertHTML;
        
        // Auto hide after 5 seconds
        setTimeout(() => {
            const alert = alertContainer.querySelector('.alert');
            if (alert) {
                try {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                } catch (e) {
                    alert.remove();
                }
            }
        }, 5000);
    }

    // Create statistics cards
    function createStatsCards(data) {
        const stats = data.total;
        const totalPendaftar = parseInt(stats.total_pendaftar) || 0;
        const totalLaki = parseInt(stats.total_laki) || 0;
        const totalPerempuan = parseInt(stats.total_perempuan) || 0;
        const belumVerifikasi = parseInt(stats.belum_verifikasi) || 0;

        const cardsHTML = `
            <div class="col-md-3 mb-3">
                <div class="card stats-card stats-card-mobile">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center stats-card-content">
                            <div class="flex-grow-1 stats-text-content">
                                <h6 class="mb-1 stats-title">Total Pendaftar</h6>
                                <h3 class="mb-0 stats-number">${totalPendaftar.toLocaleString()}</h3>
                                <small class="text-muted stats-subtitle">Keseluruhan pendaftar</small>
                            </div>
                            <div class="stats-icon bg-info-light stats-icon-mobile">
                                <i class="bi bi-person-plus text-info"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card stats-card stats-card-mobile">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center stats-card-content">
                            <div class="flex-grow-1 stats-text-content">
                                <h6 class="mb-1 stats-title">Laki-laki</h6>
                                <h3 class="mb-0 stats-number">${totalLaki.toLocaleString()}</h3>
                                <small class="text-muted stats-subtitle">Pendaftar pria</small>
                            </div>
                            <div class="stats-icon bg-primary-light stats-icon-mobile">
                                <i class="bi bi-person text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card stats-card stats-card-mobile">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center stats-card-content">
                            <div class="flex-grow-1 stats-text-content">
                                <h6 class="mb-1 stats-title">Perempuan</h6>
                                <h3 class="mb-0 stats-number">${totalPerempuan.toLocaleString()}</h3>
                                <small class="text-muted stats-subtitle">Pendaftar wanita</small>
                            </div>
                            <div class="stats-icon bg-pink-light stats-icon-mobile">
                                <i class="bi bi-person text-pink"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card stats-card stats-card-mobile">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center stats-card-content">
                            <div class="flex-grow-1 stats-text-content">
                                <h6 class="mb-1 stats-title">Belum Verifikasi</h6>
                                <h3 class="mb-0 stats-number">${belumVerifikasi.toLocaleString()}</h3>
                                <small class="text-muted stats-subtitle">Menunggu review</small>
                            </div>
                            <div class="stats-icon bg-warning-light stats-icon-mobile">
                                <i class="bi bi-clock text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        statsContainer.innerHTML = cardsHTML;
    }

    // Create fallback message when Chart.js is not available
    function createChartFallback(containerId, title, data = null) {
        const container = document.getElementById(containerId).parentNode;
        
        let dataInfo = '';
        if (data && data.length > 0) {
            dataInfo = '<div class="mt-3"><strong>Data tersedia:</strong><br>';
            data.forEach(item => {
                dataInfo += `${item.label}: ${item.value}<br>`;
            });
            dataInfo += '</div>';
        }
        
        container.innerHTML = `
            <div class="chart-fallback">
                <i class="bi bi-exclamation-circle"></i>
                <div class="fallback-title">Grafik Tidak Tersedia</div>
                <div class="fallback-message">
                    File chart.min.js tidak ditemukan.<br>
                    Silakan download Chart.js untuk menampilkan ${title}
                </div>
                ${dataInfo}
            </div>
        `;
    }

    // Create Jenis Kelamin Chart (Pie)
    function createJenisKelaminChart(data) {
        if (!isChartJsAvailable) {
            createChartFallback('chartJenisKelamin', 'grafik jenis kelamin', data);
            hideLoading('JK');
            return;
        }

        const ctx = document.getElementById('chartJenisKelamin').getContext('2d');
        
        if (chartJenisKelamin) {
            chartJenisKelamin.destroy();
        }

        if (!data || data.length === 0) {
            hideLoading('JK');
            ctx.canvas.parentNode.innerHTML = `
                <div class="no-data-message">
                    <i class="bi bi-pie-chart"></i>
                    <div><strong>Tidak ada data</strong></div>
                    <div>Data jenis kelamin tidak tersedia</div>
                </div>
            `;
            return;
        }

        const labels = data.map(item => item.label);
        const values = data.map(item => item.value);
        const colors = labels.map(label => genderColors[label] || chartColors.primary);

        try {
            chartJenisKelamin = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors,
                        borderColor: '#ffffff',
                        borderWidth: 2,
                        hoverOffset: 10
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((context.parsed / total) * 100).toFixed(1);
                                    return `${context.label}: ${context.parsed} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error creating gender chart:', error);
            createChartFallback('chartJenisKelamin', 'grafik jenis kelamin', data);
        }

        hideLoading('JK');
    }

    // Create Pendidikan Chart (Bar)
    function createPendidikanChart(data) {
        if (!isChartJsAvailable) {
            createChartFallback('chartPendidikan', 'grafik pendidikan', data);
            hideLoading('Pendidikan');
            return;
        }

        const ctx = document.getElementById('chartPendidikan').getContext('2d');
        
        if (chartPendidikan) {
            chartPendidikan.destroy();
        }

        if (!data || data.length === 0) {
            hideLoading('Pendidikan');
            ctx.canvas.parentNode.innerHTML = `
                <div class="no-data-message">
                    <i class="bi bi-bar-chart"></i>
                    <div><strong>Tidak ada data</strong></div>
                    <div>Data pendidikan tidak tersedia</div>
                </div>
            `;
            return;
        }

        const labels = data.map(item => item.label);
        const values = data.map(item => item.value);

        try {
            chartPendidikan = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Jumlah Pendaftar',
                        data: values,
                        backgroundColor: educationColors.slice(0, labels.length),
                        borderColor: educationColors.slice(0, labels.length),
                        borderWidth: 1,
                        borderRadius: 6,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.label}: ${context.parsed.y} pendaftar`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error creating education chart:', error);
            createChartFallback('chartPendidikan', 'grafik pendidikan', data);
        }

        hideLoading('Pendidikan');
    }

    // Create Usia Chart (Horizontal Bar)
    function createUsiaChart(data) {
        if (!isChartJsAvailable) {
            createChartFallback('chartUsia', 'grafik usia', data);
            hideLoading('Usia');
            return;
        }

        const ctx = document.getElementById('chartUsia').getContext('2d');
        
        if (chartUsia) {
            chartUsia.destroy();
        }

        if (!data || data.length === 0) {
            hideLoading('Usia');
            ctx.canvas.parentNode.innerHTML = `
                <div class="no-data-message">
                    <i class="bi bi-bar-chart-steps"></i>
                    <div><strong>Tidak ada data</strong></div>
                    <div>Data usia tidak tersedia</div>
                </div>
            `;
            return;
        }

        const labels = data.map(item => item.label);
        const values = data.map(item => item.value);

        try {
            chartUsia = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Jumlah Pendaftar',
                        data: values,
                        backgroundColor: ageColors.slice(0, labels.length),
                        borderColor: ageColors.slice(0, labels.length),
                        borderWidth: 1,
                        borderRadius: 8,
                        borderSkipped: false,
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.label}: ${context.parsed.x} pendaftar`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error creating age chart:', error);
            createChartFallback('chartUsia', 'grafik usia', data);
        }

        hideLoading('Usia');
    }

    // Load data from API
    async function loadData() {
        try {
            showLoading('JK');
            showLoading('Pendidikan');
            showLoading('Usia');

            const gelombang = filterGelombang.value;
            const tahun = filterTahun.value;
            
            const params = new URLSearchParams();
            if (gelombang) params.append('gelombang', gelombang);
            if (tahun) params.append('tahun', tahun);
            
            const response = await fetch(`data_grafik.php?${params.toString()}`);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.message || 'Gagal mengambil data');
            }

            const data = result.data;
            
            // Create statistics cards and charts
            createStatsCards(data);
            createJenisKelaminChart(data.jenis_kelamin);
            createPendidikanChart(data.pendidikan);
            createUsiaChart(data.usia);
            
            // Show success message if charts are working
            if (isChartJsAvailable) {
                console.log('Data berhasil dimuat dan grafik ditampilkan');
            }
            
        } catch (error) {
            console.error('Error loading data:', error);
            showAlert(`Gagal memuat data: ${error.message}`);
            
            // Hide all loading states
            hideLoading('JK');
            hideLoading('Pendidikan');
            hideLoading('Usia');
            
            // Show fallback data if available
            if (!isChartJsAvailable) {
                // Create basic fallback with just message
                createChartFallback('chartJenisKelamin', 'grafik jenis kelamin');
                createChartFallback('chartPendidikan', 'grafik pendidikan');
                createChartFallback('chartUsia', 'grafik usia');
            }
        }
    }

    // Event listeners
    if (applyFilterBtn) {
        applyFilterBtn.addEventListener('click', loadData);
    }
    
    if (resetFilterBtn) {
        resetFilterBtn.addEventListener('click', function() {
            filterGelombang.value = '';
            filterTahun.value = '';
            loadData();
        });
    }

    // Load data when filter changes
    if (filterGelombang) {
        filterGelombang.addEventListener('change', function() {
            if (this.value) {
                filterTahun.value = ''; // Clear tahun filter when gelombang is selected
            }
        });
    }

    if (filterTahun) {
        filterTahun.addEventListener('change', function() {
            if (this.value) {
                filterGelombang.value = ''; // Clear gelombang filter when tahun is selected
            }
        });
    }

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM ready, initializing grafik pendaftar...');
        
        // Check Chart.js availability and show appropriate message
        if (!isChartJsAvailable) {
            console.warn('Chart.js tidak tersedia - Mode fallback aktif');
            showAlert('Chart.js tidak tersedia. Download file chart.min.js ke folder assets/js/ untuk menampilkan grafik.', 'info');
        }
        
        // Load initial data
        loadData();
        
        // Hide offline indicator after 10 seconds if Chart.js is working
        if (isChartJsAvailable) {
            setTimeout(() => {
                const offlineIndicator = document.getElementById('offlineIndicator');
                if (offlineIndicator) {
                    offlineIndicator.classList.remove('show');
                }
            }, 10000);
        }
    });

    // Handle window resize for responsive charts
    window.addEventListener('resize', function() {
        if (isChartJsAvailable) {
            try {
                if (chartJenisKelamin) chartJenisKelamin.resize();
                if (chartPendidikan) chartPendidikan.resize();
                if (chartUsia) chartUsia.resize();
            } catch (error) {
                console.warn('Error resizing charts:', error);
            }
        }
    });

    // Export function for debugging
    window.reloadCharts = function() {
        console.log('Manual chart reload triggered');
        loadData();
    };

    </script>
</body>
</html>