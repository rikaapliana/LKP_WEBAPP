<?php
// File: pages/auth/logout.php

session_start();

// Hapus semua session
session_destroy();

// Redirect ke login dengan pesan logout
header("Location: login.php?logout=1");
exit();
?>