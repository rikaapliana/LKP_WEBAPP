<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="Daftar kursus komputer di LKP Pradata Komputer Tabalong." />
    <title>Pradata School of Computer</title>
    <link rel="icon" type="image/png" href="assets/img/favicon.png" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/styles.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<section class="hero">
    <div class="container">
        <div class="row align-items-center min-vh-50">
            <div class="col-lg-6 hero-content">
                <h1 class="display-4 fw-bold mb-4">
                    Selamat Datang <br> di Website Pelayanan <br> LKP Pradata Komputer
                </h1>
                <h6 class="lead mb-4">
                    <small>Tempat terbaik untuk belajar Microsoft Office, Internet, dan keterampilan pengembangan diri
                    dengan fasilitas modern dan pengajar profesional. </small>
                </h6>
                <div class="hero-buttons">
                    <a href="#features" class="btn btn-primary">Telusuri Program</a>
                    <a href="#" class="btn btn-white-black">Cek Persyaratan Umum</a>
                </div>
            </div>
            <div class="col-lg-6 text-center">
                <div class="hero-illustration">
                    <!-- SVG Illustration -->
                    <svg width="500" height="400" viewBox="0 0 500 400" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <!-- Background shapes -->
                        <circle cx="400" cy="100" r="60" fill="rgba(255,255,255,0.1)"/>
                        <circle cx="50" cy="300" r="40" fill="rgba(255,255,255,0.08)"/>
                        <circle cx="450" cy="350" r="30" fill="rgba(255,255,255,0.06)"/>
                        
                        <!-- Main character -->
                        <g transform="translate(200, 120)">
                            <!-- Body -->
                            <ellipse cx="50" cy="140" rx="25" ry="40" fill="#0d6efd"/>
                            <!-- Head -->
                            <circle cx="50" cy="80" r="25" fill="#ffdbac"/>
                            <!-- Arms -->
                            <ellipse cx="30" cy="120" rx="8" ry="25" fill="#ffdbac" transform="rotate(-20 30 120)"/>
                            <ellipse cx="70" cy="120" rx="8" ry="25" fill="#ffdbac" transform="rotate(20 70 120)"/>
                            <!-- Legs -->
                            <ellipse cx="40" cy="180" rx="8" ry="25" fill="#2c3e50"/>
                            <ellipse cx="60" cy="180" rx="8" ry="25" fill="#2c3e50"/>
                        </g>
                        
                        <!-- Laptop -->
                        <g transform="translate(150, 280)">
                            <rect x="0" y="0" width="80" height="50" rx="4" fill="#34495e"/>
                            <rect x="5" y="5" width="70" height="40" rx="2" fill="#3498db"/>
                            <rect x="35" y="50" width="10" height="5" fill="#95a5a6"/>
                        </g>
                        
                        <!-- Light bulb -->
                        <g transform="translate(320, 60)">
                            <circle cx="20" cy="25" r="15" fill="#f1c40f"/>
                            <rect x="15" y="35" width="10" height="8" fill="#95a5a6"/>
                            <rect x="12" y="43" width="16" height="3" fill="#7f8c8d"/>
                        </g>
                        
                        <!-- Floating elements -->
                        <circle cx="100" cy="80" r="3" fill="#3498db"/>
                        <circle cx="380" cy="200" r="4" fill="#e74c3c"/>
                        <circle cx="80" cy="200" r="2" fill="#2ecc71"/>
                        <circle cx="420" cy="250" r="3" fill="#9b59b6"/>
                        
                        <!-- Book -->
                        <g transform="translate(300, 300)">
                            <rect x="0" y="0" width="40" height="30" rx="2" fill="#e74c3c"/>
                            <rect x="0" y="5" width="40" height="20" rx="1" fill="#c0392b"/>
                            <line x1="5" y1="10" x2="35" y2="10" stroke="#fff" stroke-width="1"/>
                            <line x1="5" y1="15" x2="25" y2="15" stroke="#fff" stroke-width="1"/>
                            <line x1="5" y1="20" x2="30" y2="20" stroke="#fff" stroke-width="1"/>
                        </g>
                    </svg>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="features py-5" id="features">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Mengapa Memilih LKP Pradata?</h2>
            <p class="text-muted">Keunggulan yang membuat kami menjadi pilihan terbaik untuk pengembangan karir digital Anda</p>
        </div>
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="feature-card text-center">
                    <div class="feature-icon">
                        <i class="bi bi-stack"></i>
                    </div>
                    <h4>Dibangun untuk Developer</h4>
                    <p>Kurikulum yang dirancang khusus untuk membentuk developer handal dengan teknologi terkini dan best practices industri.</p>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="feature-card text-center">
                    <div class="feature-icon">
                        <i class="bi bi-phone"></i>
                    </div>
                    <h4>Desain Responsif Modern</h4>
                    <p>Pelajari cara membuat aplikasi web yang responsif dan user-friendly di berbagai perangkat dan platform.</p>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="feature-card text-center">
                    <div class="feature-icon">
                        <i class="bi bi-code-slash"></i>
                    </div>
                    <h4>Dokumentasi Lengkap</h4>
                    <p>Akses ke materi pembelajaran komprehensif, dokumentasi teknis, dan panduan step-by-step untuk setiap program.</p>
                </div>
            </div>
        </div>
        
        <!-- Additional Features Row -->
        <div class="row g-4 mt-4">
            <div class="col-lg-3 col-md-6">
                <div class="text-center">
                    <div class="feature-icon mx-auto mb-3" style="width: 60px; height: 60px;">
                        <i class="bi bi-laptop" style="font-size: 1.5rem;"></i>
                    </div>
                    <h5>Fasilitas Modern</h5>
                    <p class="text-muted small">Lab komputer dengan perangkat terbaru</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="text-center">
                    <div class="feature-icon mx-auto mb-3" style="width: 60px; height: 60px;">
                        <i class="bi bi-person-video3" style="font-size: 1.5rem;"></i>
                    </div>
                    <h5>Instruktur Bersertifikat</h5>
                    <p class="text-muted small">Pengajar profesional dan berpengalaman</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="text-center">
                    <div class="feature-icon mx-auto mb-3" style="width: 60px; height: 60px;">
                        <i class="bi bi-calendar-check" style="font-size: 1.5rem;"></i>
                    </div>
                    <h5>Jadwal Fleksibel</h5>
                    <p class="text-muted small">Pilihan waktu yang sesuai dengan jadwal Anda</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="text-center">
                    <div class="feature-icon mx-auto mb-3" style="width: 60px; height: 60px;">
                        <i class="bi bi-award" style="font-size: 1.5rem;"></i>
                    </div>
                    <h5>Sertifikat Resmi</h5>
                    <p class="text-muted small">Sertifikat yang diakui industri</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Add smooth scrolling
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        document.querySelector(this.getAttribute('href')).scrollIntoView({
            behavior: 'smooth'
        });
    });
});

// Add navbar transparency effect on scroll
window.addEventListener('scroll', function() {
    const navbar = document.querySelector('.navbar');
    if (window.scrollY > 50) {
        navbar.style.background = 'rgba(255, 255, 255, 0.98)';
        navbar.style.boxShadow = '0 2px 20px rgba(0, 0, 0, 0.1)';
    } else {
        navbar.style.background = 'rgba(255, 255, 255, 0.95)';
        navbar.style.boxShadow = 'none';
    }
});
</script>

</body>
</html>