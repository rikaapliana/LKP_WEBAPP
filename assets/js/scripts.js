/**
 * LKP Webapp - Main JavaScript File
 * Scripts for sidebar, navigation, dashboard, and admin functionality
 * Version: 2.3 - Fixed Form Controller for Profile Edit
 */

(function() {
    'use strict';

    // ===========================================
    // UTILITY FUNCTIONS
    // ===========================================
    const Utils = {
        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        },
        
        showLoading: function(element) {
            if (element) element.classList.add('card-loading');
        },
        
        hideLoading: function(element) {
            if (element) element.classList.remove('card-loading');
        },
        
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        safeQuerySelector: function(selector) {
            try {
                return document.querySelector(selector);
            } catch (e) {
                console.warn('Invalid selector:', selector);
                return null;
            }
        },

        safeQuerySelectorAll: function(selector) {
            try {
                return document.querySelectorAll(selector);
            } catch (e) {
                console.warn('Invalid selector:', selector);
                return [];
            }
        }
    };

    // ===========================================
    // SIDEBAR CONTROLLER - UPDATED FOR DESKTOP & MOBILE
    // ===========================================
    const SidebarController = {
        sidebar: null,
        toggleButton: null,
        overlay: null,
        mainContent: null,
        isInitialized: false,
        
        init: function() {
            try {
                // Prevent double initialization
                if (this.isInitialized) return;
                
                this.sidebar = Utils.safeQuerySelector('.sidebar');
                this.mainContent = Utils.safeQuerySelector('.flex-fill');
                this.toggleButton = Utils.safeQuerySelector('#sidebarToggle');
                
                this.createOverlay();
                this.bindEvents();
                this.checkSavedState();
                this.handleResponsive();
                this.setupClickOutsideToClose();
                
                this.isInitialized = true;
                console.log('🚀 SidebarController initialized (Desktop & Mobile)');
            } catch (error) {
                console.error('SidebarController init error:', error);
            }
        },
        
        createOverlay: function() {
            this.overlay = Utils.safeQuerySelector('.sidebar-overlay');
            if (!this.overlay) {
                this.overlay = document.createElement('div');
                this.overlay.className = 'sidebar-overlay';
                document.body.appendChild(this.overlay);
            }
        },
        
        checkSavedState: function() {
            const sidebarState = localStorage.getItem('sidebarState');
            
            // Desktop: restore hidden state if saved
            if (window.innerWidth > 768 && sidebarState === 'hidden') {
                this.sidebar?.classList.add('sidebar-hidden');
                this.mainContent?.classList.add('sidebar-hidden-content');
            }
        },
        
        toggle: function() {
            if (window.innerWidth <= 768) {
                this.toggleMobile();
            } else {
                this.toggleDesktop();
            }
        },
        
        toggleDesktop: function() {
            if (!this.sidebar || !this.mainContent) return;
            
            const isHidden = this.sidebar.classList.contains('sidebar-hidden');
            console.log('🖥️ Desktop toggle - status sebelum:', isHidden ? 'tersembunyi' : 'terlihat');
            
            if (isHidden) {
                // Tampilkan sidebar
                this.sidebar.classList.remove('sidebar-hidden');
                this.mainContent.classList.remove('sidebar-hidden-content');
                localStorage.setItem('sidebarState', 'visible');
                console.log('✅ Desktop - Sidebar ditampilkan');
            } else {
                // Sembunyikan sidebar
                this.sidebar.classList.add('sidebar-hidden');
                this.mainContent.classList.add('sidebar-hidden-content');
                localStorage.setItem('sidebarState', 'hidden');
                console.log('✅ Desktop - Sidebar disembunyikan');
            }
        },
        
        toggleMobile: function() {
            const isOpen = this.sidebar?.classList.contains('show');
            console.log('📱 Mobile toggle - status sebelum:', isOpen ? 'terbuka' : 'tertutup');
            
            if (isOpen) {
                // Tutup sidebar
                this.sidebar?.classList.remove('show');
                this.overlay?.classList.remove('show');
                document.body.classList.remove('sidebar-open');
                console.log('✅ Mobile - Sidebar ditutup');
            } else {
                // Buka sidebar
                this.sidebar?.classList.add('show');
                this.overlay?.classList.add('show');
                document.body.classList.add('sidebar-open');
                console.log('✅ Mobile - Sidebar dibuka');
            }
        },
        
        close: function() {
            if (window.innerWidth <= 768) {
                this.sidebar?.classList.remove('show');
                this.overlay?.classList.remove('show');
                document.body.classList.remove('sidebar-open');
            }
        },
        
        hide: function() {
            if (window.innerWidth > 768) {
                this.sidebar?.classList.add('sidebar-hidden');
                this.mainContent?.classList.add('sidebar-hidden-content');
                localStorage.setItem('sidebarState', 'hidden');
            }
        },
        
        bindEvents: function() {
            const self = this;
            
            // Hamburger toggle button
            if (this.toggleButton) {
                this.toggleButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('🍔 Hamburger diklik!');
                    self.toggle();
                });
            }
            
            // Overlay click (mobile only)
            if (this.overlay) {
                this.overlay.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        console.log('🎯 Overlay diklik (Mobile)');
                        self.close();
                    }
                });
            }
            
            // Window resize handler
            window.addEventListener('resize', Utils.debounce(function() {
                self.handleResponsive();
            }, 250));
            
            // Setup sidebar links behavior
            this.setupSidebarLinks();
        },
        
        setupSidebarLinks: function() {
            const self = this;
            
            if (!this.sidebar) return;
            
            // Main menu links (Dashboard, Profil, Keluar)
            const mainMenuLinks = this.sidebar.querySelectorAll('.nav-link:not(.toggle-submenu):not(.submenu-link)');
            mainMenuLinks.forEach(link => {
                link.addEventListener('click', function() {
                    // Hanya tutup sidebar di mobile untuk main menu
                    if (window.innerWidth <= 768) {
                        console.log('🏠 Main menu diklik - tutup sidebar (Mobile only)');
                        self.close();
                    }
                });
            });
            
            // Submenu links (Pendaftar, Siswa, Instruktur, dll)
            const submenuLinks = this.sidebar.querySelectorAll('.submenu-link');
            submenuLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    console.log('📋 Submenu link diklik - sidebar tetap terbuka');
                    // Tidak ada kode tutup sidebar untuk semua device
                });
            });
            
            console.log('✅ Sidebar links setup - Main:', mainMenuLinks.length, 'Submenu:', submenuLinks.length);
        },
        
        setupClickOutsideToClose: function() {
            const self = this;
            
            document.addEventListener('click', function(e) {
                const isHamburgerButton = e.target.closest('#sidebarToggle');
                const isClickInsideSidebar = self.sidebar && self.sidebar.contains(e.target);
                
                if (window.innerWidth <= 768) {
                    // MOBILE BEHAVIOR - tetap sama (click outside = tutup)
                    const isSidebarOpen = self.sidebar && self.sidebar.classList.contains('show');
                    if (!isSidebarOpen) return;
                    
                    if (!isClickInsideSidebar && !isHamburgerButton) {
                        console.log('👆 Mobile - Klik di luar sidebar');
                        self.close();
                    }
                } else {
                    // DESKTOP BEHAVIOR - HANYA HAMBURGER yang bisa toggle
                    // Tidak ada auto-hide saat klik di luar
                    console.log('🖥️ Desktop - Click outside disabled, hanya hamburger yang aktif');
                    // Tidak ada kode untuk hide sidebar
                }
            });
            
            console.log('✅ Click outside setup - Mobile: auto-close, Desktop: hamburger only');
        },
        
        handleResponsive: function() {
            if (window.innerWidth <= 768) {
                // Mobile: reset desktop classes
                this.sidebar?.classList.remove('sidebar-hidden');
                this.mainContent?.classList.remove('sidebar-hidden-content');
                document.body.classList.add('is-mobile');
            } else {
                // Desktop: close mobile sidebar if open
                this.close();
                document.body.classList.remove('is-mobile');
            }
        }
    };

    // ===========================================
    // SUBMENU CONTROLLER - UPDATED TO PREVENT AUTO-CLOSE
    // ===========================================
    const SubmenuController = {
        init: function() {
            try {
                this.bindToggleEvents();
                this.autoOpenActiveSubmenu();
                this.activateByUrl();
                
                console.log('📁 SubmenuController initialized - No Auto Close Mode');
            } catch (error) {
                console.error('SubmenuController init error:', error);
            }
        },
        
        bindToggleEvents: function() {
            const toggles = Utils.safeQuerySelectorAll('.toggle-submenu');
            toggles.forEach(toggle => {
                toggle.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation(); // PENTING: Prevent bubbling to sidebar close
                    console.log('📁 Toggle menu diklik:', toggle.textContent.trim());
                    this.toggleSubmenu(toggle);
                });
            });
            
            console.log('✅ Submenu toggles bound:', toggles.length);
        },
        
        toggleSubmenu: function(toggleElement) {
            const submenu = toggleElement.nextElementSibling;
            if (!submenu || !submenu.classList.contains('submenu')) {
                console.warn('⚠️ Submenu tidak ditemukan untuk toggle');
                return;
            }
            
            const isShown = submenu.classList.contains('show');
            console.log('📁 Submenu status sebelum toggle:', isShown ? 'terbuka' : 'tertutup');
            
            // PENTING: TIDAK ADA closeAllSubmenus() - biarkan semua submenu terbuka
            // this.closeAllSubmenus(submenu); ← DIHAPUS
            
            // Toggle hanya submenu yang diklik
            if (isShown) {
                // Tutup submenu ini
                submenu.classList.remove('show', 'showing');
                toggleElement.classList.remove('has-active-submenu');
                this.toggleIcons(toggleElement, false);
                console.log('✅ Submenu ditutup');
            } else {
                // Buka submenu ini
                submenu.classList.add('show', 'showing');
                toggleElement.classList.add('has-active-submenu');
                this.toggleIcons(toggleElement, true);
                console.log('✅ Submenu dibuka');
            }
            
            console.log('🎯 Sidebar dan submenu lain TETAP dalam keadaan semula');
        },
        
        // Method ini TIDAK dipanggil lagi dalam toggleSubmenu
        closeAllSubmenus: function(exceptSubmenu) {
            const submenus = Utils.safeQuerySelectorAll('.submenu');
            submenus.forEach(menu => {
                if (menu !== exceptSubmenu) {
                    menu.classList.remove('show', 'showing');
                    const toggle = menu.previousElementSibling;
                    if (toggle && toggle.classList.contains('toggle-submenu')) {
                        toggle.classList.remove('has-active-submenu');
                        this.toggleIcons(toggle, false);
                    }
                }
            });
            console.log('📁 Semua submenu lain ditutup (method ini tidak dipakai lagi)');
        },
        
        toggleIcons: function(toggleElement, isActive) {
            const icon = toggleElement.querySelector('.submenu-icon');
            const caret = toggleElement.querySelector('.submenu-caret');
            
            if (icon) {
                icon.classList.toggle('rotate', isActive);
                console.log('🔄 Icon rotated:', isActive);
            }
            if (caret) {
                caret.classList.toggle('rotate', isActive);
                console.log('🔄 Caret rotated:', isActive);
            }
        },
        
        autoOpenActiveSubmenu: function() {
            const activeSubmenuItem = Utils.safeQuerySelector('.submenu .nav-link.active, .submenu .submenu-link.active');
            
            if (activeSubmenuItem) {
                const submenu = activeSubmenuItem.closest('.submenu');
                if (submenu) {
                    console.log('🎯 Auto-opening submenu berdasarkan halaman aktif');
                    this.openSubmenu(submenu);
                }
            }
        },
        
        openSubmenu: function(submenu) {
            submenu.classList.add('show', 'showing');
            
            const toggleButton = submenu.previousElementSibling;
            if (toggleButton && toggleButton.classList.contains('toggle-submenu')) {
                toggleButton.classList.add('has-active-submenu');
                this.toggleIcons(toggleButton, true);
                console.log('✅ Submenu dibuka otomatis');
            }
        },
        
        activateByUrl: function() {
            const currentUrl = window.location.href;
            
            // Keywords untuk Data Master
            const masterKeywords = ['instruktur', 'kelas', 'materi'];
            
            // Keywords untuk Data Akademik  
            const akademikKeywords = ['pendaftar', 'siswa', 'jadwal', 'nilai'];
            
            // Keywords untuk Evaluasi
            const evaluasiKeywords = ['pertanyaan', 'jawaban', 'grafik-hasil', 'kelola-pertanyaan', 'hasil-jawaban'];
            
            // Keywords untuk Laporan
            const laporanKeywords = ['laporan'];
            
            // Check dan activate submenu berdasarkan URL
            const masterMatch = masterKeywords.find(keyword => 
                currentUrl.includes('/' + keyword + '/') || currentUrl.includes('/' + keyword + '.php')
            );
            
            const akademikMatch = akademikKeywords.find(keyword => 
                currentUrl.includes('/' + keyword + '/') || currentUrl.includes('/' + keyword + '.php')
            );
            
            const evaluasiMatch = evaluasiKeywords.find(keyword => 
                currentUrl.includes('/' + keyword + '/') || currentUrl.includes('/' + keyword + '.php') || currentUrl.includes('/evaluasi/')
            );
            
            const laporanMatch = laporanKeywords.find(keyword => 
                currentUrl.includes('/' + keyword + '/') || currentUrl.includes('/' + keyword + '.php')
            );
            
            if (masterMatch) {
                this.activateSubmenuBySelector('#toggle-datamaster', masterMatch);
            }
            
            if (akademikMatch) {
                this.activateSubmenuBySelector('#toggle-dataakademik', akademikMatch);
            }
            
            if (evaluasiMatch) {
                this.activateSubmenuBySelector('#toggle-evaluasi, #toggle-manajemenevaluasi', evaluasiMatch);
            }
            
            if (laporanMatch) {
                this.activateSubmenuBySelector('#toggle-laporan, #toggle-laporanmaster, #toggle-laporanakademik', laporanMatch);
            }
            
            console.log('🎯 URL activation completed');
        },
        
        activateSubmenuBySelector: function(selector, keyword) {
            const toggle = Utils.safeQuerySelector(selector);
            if (!toggle) return;
            
            const submenu = toggle.nextElementSibling;
            if (submenu && submenu.classList.contains('submenu')) {
                this.openSubmenu(submenu);
                
                // Activate the specific link
                const submenuLinks = submenu.querySelectorAll('.nav-link, .submenu-link');
                submenuLinks.forEach(link => {
                    if (link.href.includes('/' + keyword + '/') || 
                        link.href.includes('/' + keyword + '.php') ||
                        link.href.includes('/evaluasi/') ||
                        link.href.includes('/laporan/')) {
                        link.classList.add('active');
                        console.log('✅ Link activated:', link.textContent.trim());
                    }
                });
            }
        }
    };

    // ===========================================
    // DASHBOARD CONTROLLER
    // ===========================================
    const DashboardController = {
        init: function() {
            try {
                this.animateNumbers();
                this.initTooltips();
                this.initProgressBars();
                this.bindQuickActions();
                
                console.log('📊 DashboardController initialized');
            } catch (error) {
                console.error('DashboardController init error:', error);
            }
        },
        
        animateNumbers: function() {
            const numbers = Utils.safeQuerySelectorAll('.stats-card h3');
            
            numbers.forEach((number, index) => {
                const target = parseInt(number.textContent.replace(/[^0-9]/g, '')) || 0;
                if (target === 0) return;
                
                const duration = 1000;
                const increment = target / (duration / 16);
                let current = 0;
                
                setTimeout(() => {
                    const timer = setInterval(() => {
                        current += increment;
                        if (current >= target) {
                            current = target;
                            clearInterval(timer);
                        }
                        number.textContent = Math.floor(current).toLocaleString('id-ID');
                    }, 16);
                }, index * 100);
            });
        },
        
        initTooltips: function() {
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                const tooltipTriggerList = Utils.safeQuerySelectorAll('[data-bs-toggle="tooltip"]');
                tooltipTriggerList.forEach(tooltipTriggerEl => {
                    new bootstrap.Tooltip(tooltipTriggerEl);
                });
            }
        },
        
        initProgressBars: function() {
            const progressBars = Utils.safeQuerySelectorAll('.progress-bar');
            
            progressBars.forEach((bar, index) => {
                const width = bar.style.width;
                bar.style.width = '0%';
                
                setTimeout(() => {
                    bar.style.transition = 'width 0.8s ease-in-out';
                    bar.style.width = width;
                }, index * 200);
            });
        },
        
        bindQuickActions: function() {
            const quickActionBtns = Utils.safeQuerySelectorAll('.quick-action-btn');
            
            quickActionBtns.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                });
            });
        }
    };

    // ===========================================
    // ADMIN SISWA CONTROLLER
    // ===========================================
    const AdminSiswaController = {
        init: function() {
            try {
                const siswaTable = Utils.safeQuerySelector('#siswaTable');
                if (!siswaTable) return;
                
                this.initTooltips();
                this.initSearch();
                this.bindDeleteButtons();
                
                console.log('👥 AdminSiswaController initialized');
            } catch (error) {
                console.error('AdminSiswaController init error:', error);
            }
        },
        
        initTooltips: function() {
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                const tooltipTriggerList = Utils.safeQuerySelectorAll('[data-bs-toggle="tooltip"]');
                tooltipTriggerList.forEach(tooltipTriggerEl => {
                    new bootstrap.Tooltip(tooltipTriggerEl);
                });
            }
        },
        
        initSearch: function() {
            const searchInput = Utils.safeQuerySelector('#searchInput');
            if (!searchInput) return;
            
            searchInput.addEventListener('input', Utils.debounce((e) => {
                this.performSearch(e.target.value);
            }, 300));
        },
        
        performSearch: function(filter) {
            const filterLower = filter.toLowerCase();
            const rows = Utils.safeQuerySelectorAll('#siswaTable tbody tr');
            
            rows.forEach(row => {
                if (row.querySelector('.empty-state')) return;
                
                const cells = row.querySelectorAll('td');
                const nik = cells[2]?.textContent.toLowerCase() || '';
                const nama = cells[3]?.textContent.toLowerCase() || '';
                const kontak = cells[7]?.textContent.toLowerCase() || '';
                
                const isVisible = nik.includes(filterLower) || 
                                nama.includes(filterLower) || 
                                kontak.includes(filterLower);
                
                row.style.display = isVisible ? '' : 'none';
            });
            
            this.updateDataCount();
        },
        
        updateDataCount: function() {
            const visibleRows = Utils.safeQuerySelectorAll('#siswaTable tbody tr:not([style*="display: none"])');
            const countElement = Utils.safeQuerySelector('.text-muted strong');
            
            if (countElement && visibleRows.length > 0) {
                const hasEmptyState = visibleRows[0].querySelector('.empty-state');
                const count = hasEmptyState ? 0 : visibleRows.length;
                countElement.textContent = count;
            }
        },
        
        bindDeleteButtons: function() {
            const deleteButtons = Utils.safeQuerySelectorAll('.btn-delete');
            deleteButtons.forEach(button => {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.handleDelete(button);
                });
            });
        },
        
        // Export functions
        printTable: function() {
            const contentCard = Utils.safeQuerySelector('.content-card');
            if (!contentCard) return;
            
            const printHTML = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Data Siswa - LKP</title>
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
                    <style>
                        body { font-family: Arial, sans-serif; }
                        .btn-group, .btn-action, .search-box, .dropdown { display: none !important; }
                        .table { font-size: 12px; }
                        .page-header { text-align: center; margin-bottom: 30px; }
                        @media print { .no-print { display: none !important; } }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="page-header">
                            <h2>Data Siswa</h2>
                            <p>Lembaga Kursus dan Pelatihan</p>
                            <p>Tanggal Cetak: ${new Date().toLocaleDateString('id-ID')}</p>
                        </div>
                        ${contentCard.outerHTML}
                    </div>
                </body>
                </html>
            `;
            
            const printWindow = window.open('', '_blank');
            if (printWindow) {
                printWindow.document.write(printHTML);
                printWindow.document.close();
                printWindow.onload = function() {
                    printWindow.print();
                    printWindow.close();
                };
            }
        },
        
        exportExcel: function() {
            alert('Fitur Export Excel akan segera tersedia.\nSilakan gunakan fitur Cetak untuk sementara.');
        },
        
        exportPDF: function() {
            alert('Fitur Export PDF akan segera tersedia.\nSilakan gunakan fitur Cetak untuk sementara.');
        }
    };

    // ===========================================
    // NOTIFICATION CONTROLLER
    // ===========================================
    const NotificationController = {
        init: function() {
            try {
                this.bindNotificationEvents();
                this.checkForUpdates();
                
                console.log('🔔 NotificationController initialized');
            } catch (error) {
                console.error('NotificationController init error:', error);
            }
        },
        
        bindNotificationEvents: function() {
            const notificationDropdown = Utils.safeQuerySelector('.navbar-notifications button');
            
            if (notificationDropdown) {
                notificationDropdown.addEventListener('shown.bs.dropdown', function() {
                    const badge = this.querySelector('.notification-badge');
                    if (badge) {
                        badge.style.animation = 'none';
                    }
                });
            }
        },
        
        checkForUpdates: function() {
            // Placeholder for real-time notification checking
            console.log('Notification system ready');
        }
    };

    // ===========================================
    // SMOOTH SCROLL CONTROLLER
    // ===========================================
    const SmoothScrollController = {
        init: function() {
            try {
                const anchors = Utils.safeQuerySelectorAll('a[href^="#"]');
                anchors.forEach(anchor => {
                    anchor.addEventListener('click', function(e) {
                        e.preventDefault();
                        const targetId = this.getAttribute('href');
                        if (targetId !== '#') {
                            const target = Utils.safeQuerySelector(targetId);
                            if (target) {
                                target.scrollIntoView({
                                    behavior: 'smooth',
                                    block: 'start'
                                });
                            }
                        }
                    });
                });
                
                console.log('🔄 SmoothScrollController initialized');
            } catch (error) {
                console.error('SmoothScrollController init error:', error);
            }
        }
    };

    // ===========================================
    // FORM CONTROLLER - FIXED FOR PROFILE FORM
    // ===========================================
    const FormController = {
        init: function() {
            try {
                this.preventDoubleSubmission();
                this.storeOriginalButtonText();
                
                console.log('📝 FormController initialized (Fixed for Profile)');
            } catch (error) {
                console.error('FormController init error:', error);
            }
        },
        
        preventDoubleSubmission: function() {
            document.addEventListener('submit', function(e) {
                const form = e.target;
                const submitBtn = form.querySelector('button[type="submit"]');
                
                // SKIP untuk form profil admin - deteksi berdasarkan field yang ada
                if (form.querySelector('input[name="nama"]') && 
                    form.querySelector('input[name="email"]') && 
                    form.querySelector('input[name="username"]')) {
                    console.log('🔓 Profile form detected - skipping double submission prevention');
                    return; // PENTING: Skip processing untuk form profil
                }
                
                // SKIP untuk form dengan class khusus
                if (form.classList.contains('no-double-submit-prevention')) {
                    console.log('🔓 Form with no-double-submit-prevention class - skipping');
                    return;
                }
                
                // SKIP untuk form debug
                if (form.innerHTML.includes('full_update') || form.innerHTML.includes('test_update')) {
                    console.log('🔓 Debug form detected - skipping double submission prevention');
                    return;
                }
                
                // Apply double submission prevention untuk form lain
                if (submitBtn && !submitBtn.disabled) {
                    console.log('🔒 Applying double submission prevention to form');
                    
                    const originalText = submitBtn.dataset.originalText || submitBtn.innerHTML;
                    
                    // Set loading state
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';
                    
                    // Reset after delay (memberi waktu untuk proses)
                    setTimeout(() => {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                            console.log('🔓 Button re-enabled after timeout');
                        }
                    }, 5000); // 5 detik timeout
                }
            });
        },
        
        storeOriginalButtonText: function() {
            const submitButtons = Utils.safeQuerySelectorAll('button[type="submit"]');
            submitButtons.forEach(btn => {
                if (!btn.dataset.originalText) {
                    btn.dataset.originalText = btn.innerHTML;
                }
            });
        }
    };

    // ===========================================
    // GLOBAL FUNCTIONS FOR HTML ONCLICK
    // ===========================================
    window.toggleSubmenu = function(menuId) {
        const submenu = Utils.safeQuerySelector('#submenu-' + menuId);
        const caret = Utils.safeQuerySelector('#caret-' + menuId);
        
        if (!submenu || !caret) {
            console.warn('⚠️ Submenu atau caret tidak ditemukan:', menuId);
            return;
        }
        
        // Toggle submenu
        const isShown = submenu.classList.contains('show');
        submenu.classList.toggle('show');
        caret.classList.toggle('rotate');
        
        console.log('🎯 Global toggleSubmenu called:', menuId, isShown ? 'menutup' : 'membuka');
        console.log('✅ Submenu lain tetap dalam keadaan semula (tidak auto-close)');
    };

    // ===========================================
    // MAIN APP CONTROLLER
    // ===========================================
    const LKPWebApp = {
        init: function() {
            try {
                // Initialize core controllers
                SidebarController.init();
                SubmenuController.init();
                FormController.init();
                NotificationController.init();
                SmoothScrollController.init();
                
                // Initialize page-specific controllers
                if (Utils.safeQuerySelector('.stats-card')) {
                    DashboardController.init();
                }
                
                if (Utils.safeQuerySelector('#siswaTable')) {
                    AdminSiswaController.init();
                }
                
                this.bindGlobalEvents();
                
                console.log('🎉 LKP Webapp initialized successfully (v2.2 - No Auto Close)');
                
            } catch (error) {
                console.error('LKP Webapp initialization error:', error);
            }
        },
        
        bindGlobalEvents: function() {
            // Handle page visibility change
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    NotificationController.checkForUpdates();
                }
            });
            
            // Handle online/offline status
            window.addEventListener('online', function() {
                console.log('Connection restored');
            });
            
            window.addEventListener('offline', function() {
                console.log('Connection lost');
            });
        }
    };

    // ===========================================
    // GLOBAL EXPORTS
    // ===========================================
    window.LKPUtils = Utils;
    window.adminSiswa = {
        printTable: AdminSiswaController.printTable.bind(AdminSiswaController),
        exportExcel: AdminSiswaController.exportExcel.bind(AdminSiswaController),
        exportPDF: AdminSiswaController.exportPDF.bind(AdminSiswaController)
    };

    // ===========================================
    // INITIALIZATION
    // ===========================================
    document.addEventListener('DOMContentLoaded', function() {
        LKPWebApp.init();
    });

})();