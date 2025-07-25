<?php
// File: includes/auth.php

// Start session dan include functions
require_once __DIR__ . '/session.php';

// Fungsi untuk mendapatkan path yang benar ke login (FIXED VERSION)
function getLoginPath() {
    // Ambil path script saat ini
    $currentScript = $_SERVER['SCRIPT_NAME'];
    
    // Hitung berapa level setelah /pages/
    $afterPages = substr($currentScript, strpos($currentScript, '/pages/') + 7); // +7 untuk "/pages/"
    $levels = substr_count($afterPages, '/'); // Jangan dikurangi 1, karena kita hitung dari folder
    
    // Buat path relatif ke auth/login.php
    return str_repeat('../', $levels) . 'auth/login.php';
}

// Helper function untuk mendapatkan path dashboard yang benar
function getDashboardPath($role) {
    $currentScript = $_SERVER['SCRIPT_NAME'];
    $afterPages = substr($currentScript, strpos($currentScript, '/pages/') + 7);
    $levels = substr_count($afterPages, '/'); // Jangan dikurangi 1
    
    return str_repeat('../', $levels) . $role . '/dashboard.php';
}

// Fungsi untuk protect halaman admin (FIXED)
function requireAdminAuth() {
    if (!isLoggedIn()) {
        $loginPath = getLoginPath();
        header("Location: $loginPath");
        exit();
    }
    
    if (!hasRole('admin')) {
        // Jika bukan admin, redirect ke dashboard sesuai role atau ke login
        if (hasRole('instruktur')) {
            header("Location: " . getDashboardPath('instruktur'));
            exit();
        } elseif (hasRole('siswa')) {
            header("Location: " . getDashboardPath('siswa'));
            exit();
        } else {
            $loginPath = getLoginPath();
            header("Location: $loginPath");
            exit();
        }
    }
}

// Fungsi untuk protect halaman instruktur (FIXED)
function requireInstrukturAuth() {
    if (!isLoggedIn()) {
        $loginPath = getLoginPath();
        header("Location: $loginPath");
        exit();
    }
    
    if (!hasRole('instruktur') && !hasRole('admin')) {
        // Admin bisa akses halaman instruktur, siswa tidak
        if (hasRole('siswa')) {
            header("Location: " . getDashboardPath('siswa'));
            exit();
        } else {
            $loginPath = getLoginPath();
            header("Location: $loginPath");
            exit();
        }
    }
}

// Fungsi untuk protect halaman siswa (FIXED)
function requireSiswaAuth() {
    if (!isLoggedIn()) {
        $loginPath = getLoginPath();
        header("Location: $loginPath");
        exit();
    }
    
    if (!hasRole('siswa') && !hasRole('admin')) {
        // Admin bisa akses halaman siswa, instruktur tidak
        if (hasRole('instruktur')) {
            header("Location: " . getDashboardPath('instruktur'));
            exit();
        } else {
            $loginPath = getLoginPath();
            header("Location: $loginPath");
            exit();
        }
    }
}

// Fungsi untuk protect halaman umum (UNCHANGED)
function requireAuth() {
    if (!isLoggedIn()) {
        $loginPath = getLoginPath();
        header("Location: $loginPath");
        exit();
    }
}
?>