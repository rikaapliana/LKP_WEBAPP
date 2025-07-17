<?php
// File: includes/auth.php

// Start session dan include functions
require_once __DIR__ . '/session.php';

// Fungsi untuk mendapatkan path yang benar ke login
function getLoginPath() {
    // Deteksi level kedalaman folder berdasarkan $_SERVER['REQUEST_URI']
    $currentPath = $_SERVER['REQUEST_URI'];
    
    // Hitung berapa level naik yang dibutuhkan
    if (strpos($currentPath, '/pages/admin/') !== false) {
        if (substr_count($currentPath, '/') > substr_count('/pages/admin/', '/')) {
            // Di subfolder admin (seperti instruktur/, kelas/)
            return '../../../pages/auth/login.php';
        } else {
            // Di folder admin langsung
            return '../../pages/auth/login.php';
        }
    }
    
    // Default fallback
    return '../../pages/auth/login.php';
}

// Fungsi untuk protect halaman admin
function requireAdminAuth() {
    if (!isLoggedIn()) {
        $loginPath = getLoginPath();
        header("Location: $loginPath");
        exit();
    }
    
    if (!hasRole('admin')) {
        // Jika bukan admin, redirect ke dashboard sesuai role atau ke login
        if (hasRole('instruktur')) {
            header("Location: ../instruktur/dashboard.php");
            exit();
        } elseif (hasRole('siswa')) {
            header("Location: ../siswa/dashboard.php");
            exit();
        } else {
            $loginPath = getLoginPath();
            header("Location: $loginPath");
            exit();
        }
    }
}

// Fungsi untuk protect halaman instruktur
function requireInstrukturAuth() {
    if (!isLoggedIn()) {
        $loginPath = getLoginPath();
        header("Location: $loginPath");
        exit();
    }
    
    if (!hasRole('instruktur') && !hasRole('admin')) {
        // Admin bisa akses halaman instruktur, siswa tidak
        if (hasRole('siswa')) {
            header("Location: ../siswa/dashboard.php");
            exit();
        } else {
            $loginPath = getLoginPath();
            header("Location: $loginPath");
            exit();
        }
    }
}

// Fungsi untuk protect halaman siswa
function requireSiswaAuth() {
    if (!isLoggedIn()) {
        $loginPath = getLoginPath();
        header("Location: $loginPath");
        exit();
    }
    
    if (!hasRole('siswa') && !hasRole('admin')) {
        // Admin bisa akses halaman siswa, instruktur tidak
        if (hasRole('instruktur')) {
            header("Location: ../instruktur/dashboard.php");
            exit();
        } else {
            $loginPath = getLoginPath();
            header("Location: $loginPath");
            exit();
        }
    }
}

// Fungsi untuk protect halaman umum (semua role bisa akses)
function requireAuth() {
    if (!isLoggedIn()) {
        $loginPath = getLoginPath();
        header("Location: $loginPath");
        exit();
    }
}
?>