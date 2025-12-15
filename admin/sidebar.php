<?php
// Pastikan session sudah dimulai & user sudah login sebagai admin
if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] !== 'superadmin' && $_SESSION['user_role'] !== 'admin')) {
    die("Akses ditolak.");
}

$is_superadmin = ($_SESSION['user_role'] === 'superadmin');
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar">
    <div class="p-3 text-center border-bottom border-secondary">
        <h5 class="text-white mb-0">
            <i class="fas fa-university"></i> Admin UKM
        </h5>
        <small class="text-white-50">Politeknik Negeri Lampung</small>
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
            <!-- Superadmin: kelola semua UKM -->
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
            <!-- Admin UKM: kelola UKM-nya sendiri -->
            <a class="nav-link <?= isActive(['kelola_ukm.php']) ?>" href="kelola_ukm.php">
                <i class="fas fa-building me-2"></i> Kelola UKM
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

        <div class="dropdown-divider bg-secondary"></div>
        <a class="nav-link" href="../index.php" target="_blank">
            <i class="fas fa-external-link-alt me-2"></i> Lihat Website
        </a>
        <a class="nav-link" href="../auth/logout.php">
            <i class="fas fa-sign-out-alt me-2"></i> Logout
        </a>
    </nav>
</div>