<?php
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isLoggedIn() || $_SESSION['user_role'] !== 'superadmin') {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

// Hitung total admin
$total_admin = $db->query("SELECT COUNT(*) FROM admin")->fetchColumn();

// Hitung total UKM
$total_ukm = $db->query("SELECT COUNT(*) FROM ukm")->fetchColumn();

// Hitung total kategori UKM
$total_kategori = $db->query("SELECT COUNT(*) FROM kategori_ukm")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Super Admin - UKM Polinela</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f5f6fa;
        }
        .sidebar {
            height: 100vh;
            background: #2c3e50;
            padding-top: 20px;
            color: white;
        }
        .sidebar a {
            padding: 12px;
            display: block;
            color: white;
            text-decoration: none;
            font-weight: 500;
        }
        .sidebar a:hover {
            background: #1abc9c;
            border-radius: 5px;
        }
        .card-stats {
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .welcome-box {
            background: linear-gradient(135deg, #2980b9, #6dd5fa);
            padding: 25px;
            border-radius: 15px;
            color: white;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">

        <!-- SIDEBAR -->
        <div class="col-2 sidebar">
            <h4 class="text-center mb-4">
                <i class="fas fa-crown"></i> Super Admin
            </h4>

            <a href="dashboard.php">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="kelola_admin.php">
                <i class="fas fa-users-cog"></i> Kelola Admin
            </a>
            <a href="kelola_ukm.php">
                <i class="fas fa-sitemap"></i> Kelola UKM
            </a>
            <a href="../admin/dashboard.php">
                <i class="fas fa-user-shield"></i> Mode Admin
            </a>
            <a href="../auth/logout.php" class="text-danger">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

        <!-- KONTEN -->
        <div class="col-10 p-4">

            <div class="welcome-box mb-4">
                <h3>Selamat datang, <?= $_SESSION['nama'] ?> 👋</h3>
                <p>Anda sedang berada di panel <strong>Super Admin</strong> untuk mengelola sistem UKM.</p>
            </div>

            <!-- Statistik -->
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="card card-stats p-3">
                        <h5><i class="fas fa-users-cog text-primary"></i> Total Admin</h5>
                        <h2><?= $total_admin ?></h2>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card card-stats p-3">
                        <h5><i class="fas fa-building text-success"></i> Total UKM</h5>
                        <h2><?= $total_ukm ?></h2>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card card-stats p-3">
                        <h5><i class="fas fa-list text-warning"></i> Total Kategori</h5>
                        <h2><?= $total_kategori ?></h2>
                    </div>
                </div>
            </div>

            <!-- Section Kelola Admin -->
            <div class="mt-4">
                <a href="kelola_admin.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-user-gear"></i> Kelola Admin
                </a>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
