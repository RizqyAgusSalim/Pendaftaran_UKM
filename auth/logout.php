<?php
// auth/logout.php
// Hanya logout pengguna di perangkat/tab ini, tanpa menghancurkan seluruh sesi global

// Pastikan sesi dimulai dengan aman
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hapus hanya variabel sesi yang terkait dengan autentikasi
$auth_keys = [
    'user_id',
    'user_type',
    'user_role',
    'username',
    'nim',
    'nama',
    'ukm_id'
];

foreach ($auth_keys as $key) {
    unset($_SESSION[$key]);
}

// Opsional: beri flag logout jika perlu (misal untuk pesan)
$_SESSION['just_logged_out'] = true;

// Redirect ke halaman utama
header("Location: ../index.php");
exit();