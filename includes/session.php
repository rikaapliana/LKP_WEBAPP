<?php
// File: includes/session.php

// Start session jika belum dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include fungsi bantuan
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';

// Cek session timeout jika user sudah login
if (isLoggedIn()) {
    if (!checkSessionTimeout()) {
        // Session expired, redirect ke login
        redirect('../auth/login.php?expired=1');
    }
}
?>