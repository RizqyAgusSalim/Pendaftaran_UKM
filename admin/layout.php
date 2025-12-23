<?php
// layout.php - Template Admin Terpadu (Responsif & Scrollable)
if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] !== 'superadmin' && $_SESSION['user_role'] !== 'admin')) {
    die("Akses ditolak.");
}

$is_superadmin = ($_SESSION['user_role'] === 'superadmin');
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = $page_title ?? 'Admin Panel';
$nama_ukm = $nama_ukm ?? 'UKM';

// 🔔 TAMBAHAN: Ambil jumlah notifikasi belum dibaca
$unread_notif = 0;
if (isset($_SESSION['user_id'])) {
    // Koneksi cepat hanya untuk notifikasi
    try {
        require_once '../config/database.php';
        $database = new Database();
        $db_temp = $database->getConnection();
        if ($db_temp) {
            $stmt = $db_temp->prepare("SELECT COUNT(*) FROM notifikasi WHERE user_id = ? AND dibaca = 0");
            $stmt->execute([$_SESSION['user_id']]);
            $unread_notif = $stmt->fetchColumn();
        }
    } catch (Exception $e) {
        // Gagal? abaikan, jangan ganggu UI
        $unread_notif = 0;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - UKM Polinela</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .navbar-admin {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            z-index: 1050;
        }
        .sidebar {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            position: fixed;
            top: 56px;
            left: 0;
            width: 250px;
            height: calc(100vh - 56px);
            z-index: 1040;
            overflow-y: auto;
            transition: left 0.3s ease;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.85);
            padding: 12px 20px;
            border-radius: 0;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white !important;
        }
        .main-content {
            background: #f8f9fa;
            margin-top: 56px;
            padding: 0;
            height: calc(100vh - 56px);
            overflow: auto;
        }
        @media (min-width: 992px) {
            .main-content {
                margin-left: 250px;
            }
        }
        @media (max-width: 991.98px) {
            .sidebar { left: -250px; }
            .sidebar.show { left: 0; }
            .main-content { margin-left: 0; }
        }
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.4);
            z-index: 1035;
        }
        .sidebar-overlay.show { display: block; }
        .sidebar .header {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        .sidebar .header h5 { color: white; margin-bottom: 5px; }
        .sidebar .header small { color: rgba(255,255,255,0.6); }
        body {
            overflow: hidden; /* mencegah double scroll */
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Navbar Atas -->
<nav class="navbar navbar-expand navbar-admin fixed-top">
    <div class="container-fluid">
        <button class="btn btn-light btn-toggle-sidebar d-lg-none" id="toggleSidebar">
            <i class="fas fa-bars"></i>
        </button>
        <a class="navbar-brand text-white fw-bold" href="dashboard.php">
            <i class="fas fa-cog me-2"></i>
            <?php if ($is_superadmin): ?>
                Super Admin
            <?php else: ?>
                Admin <?= htmlspecialchars($nama_ukm) ?>
            <?php endif; ?>
        </a>
        <div class="d-flex align-items-center">
            <!-- 🔔 TAMBAHAN: Ikon Notifikasi -->
            <a href="notifikasi.php" class="text-white me-3 position-relative" style="text-decoration: none;">
                <i class="fas fa-bell fa-lg"></i>
                <?php if ($unread_notif > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?= $unread_notif ?>
                        <span class="visually-hidden">notifikasi belum dibaca</span>
                    </span>
                <?php endif; ?>
            </a>
            
            <span class="text-white me-3">
                <i class="fas fa-user-circle me-1"></i>
                <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?>
            </span>
            <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-sign-out-alt me-1"></i>Logout
            </a>
        </div>
    </div>
</nav>

<!-- Sidebar -->
<div class="sidebar" id="mobileSidebar">
    <div class="header">
        <h5><i class="fas fa-university"></i> Admin UKM</h5>
        <small>Politeknik Negeri Lampung</small>
    </div>
    <nav class="nav flex-column">
        <?php
        function isActive(array $pages): string {
            global $current_page;
            return in_array($current_page, $pages) ? 'active' : '';
        }
        ?>

        <a class="nav-link <?= isActive(['dashboard.php']) ?>" href="dashboard.php">
            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
        </a>

        <?php if ($is_superadmin): ?>
            <a class="nav-link <?= isActive(['kelola_ukm.php']) ?>" href="kelola_ukm.php">
                <i class="fas fa-users me-2"></i> Kelola UKM
            </a>
            <a class="nav-link <?= isActive(['kelola_kategori.php']) ?>" href="kelola_kategori.php">
                <i class="fas fa-tags me-2"></i> Kategori UKM
            </a>
            <a class="nav-link <?= isActive(['kelola_mahasiswa.php']) ?>" href="kelola_mahasiswa.php">
                <i class="fas fa-user-graduate me-2"></i> Data Mahasiswa
            </a>
        <?php else: ?>
            <a class="nav-link <?= isActive(['kelola_ukm.php']) ?>" href="kelola_ukm.php">
                <i class="fas fa-building me-2"></i> Kelola UKM
            </a>
            <a class="nav-link <?= isActive(['kelola_divisi.php']) ?>" href="kelola_divisi.php">
                <i class="fas fa-sitemap me-2"></i> Kelola Divisi
            </a>
            <a class="nav-link <?= isActive(['kelola_mahasiswa.php']) ?>" href="kelola_mahasiswa.php">
                <i class="fas fa-user-graduate me-2"></i> Data Anggota
            </a>
            <a class="nav-link <?= isActive(['kelola_pendaftaran.php']) ?>" href="kelola_pendaftaran.php">
                <i class="fas fa-clipboard-list me-2"></i> Pendaftaran
            </a>
            <a class="nav-link <?= isActive(['kelola_berita.php', 'tambah_berita.php', 'edit_berita.php']) ?>" href="kelola_berita.php">
                <i class="fas fa-newspaper me-2"></i> Kelola Berita
            </a>
            <a class="nav-link <?= isActive(['kelola_kegiatan.php', 'tambah_kegiatan.php', 'edit_kegiatan.php']) ?>" href="kelola_kegiatan.php">
                <i class="fas fa-calendar-alt me-2"></i> Kelola Kegiatan
            </a>
            <a class="nav-link <?= isActive(['kelola_foto.php']) ?>" href="kelola_foto.php">
                <i class="fas fa-images me-2"></i> Galeri Foto
            </a>
        <?php endif; ?>

        <a class="nav-link <?= isActive(['laporan.php']) ?>" href="laporan.php">
            <i class="fas fa-chart-bar me-2"></i> Laporan
        </a>
        <hr class="border-secondary my-2 mx-3">
        <a class="nav-link" href="../index.php" target="_blank">
            <i class="fas fa-external-link-alt me-2"></i> Lihat Website
        </a>
        <a class="nav-link" href="../auth/logout.php">
            <i class="fas fa-sign-out-alt me-2"></i> Logout
        </a>
    </nav>
</div>

<!-- Konten Utama -->
<div class="main-content">

<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.getElementById('toggleSidebar');
    const sidebar = document.getElementById('mobileSidebar');
    const overlay = document.getElementById('sidebarOverlay');

    toggleBtn?.addEventListener('click', () => {
        sidebar.classList.add('show');
        overlay.classList.add('show');
    });

    overlay?.addEventListener('click', () => {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
    });
});
</script>