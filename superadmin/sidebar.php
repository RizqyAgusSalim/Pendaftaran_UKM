<!-- SIDEBAR -->
<div class="col-2 sidebar">
    <h4 class="text-center mb-4">
        <i class="fas fa-crown"></i> Super Admin
    </h4>

    <a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
        <i class="fas fa-home"></i> Dashboard
    </a>
    <a href="kelola_adminUKM.php" class="<?= basename($_SERVER['PHP_SELF']) === 'kelola_adminUKM.php' ? 'active' : '' ?>">
        <i class="fas fa-users-cog"></i> Kelola Admin & UKM
    </a>
    <a href="kelola_mahasiswa.php" class="<?= basename($_SERVER['PHP_SELF']) === 'kelola_mahasiswa.php' ? 'active' : '' ?>">
        <i class="fas fa-user-graduate"></i> Kelola Mahasiswa
    </a>
    <a href="../admin/dashboard.php">
        <i class="fas fa-user-shield"></i> Mode Admin
    </a>
    <a href="../auth/logout.php" class="text-danger">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>