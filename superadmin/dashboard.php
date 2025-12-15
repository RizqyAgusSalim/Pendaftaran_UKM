<?php
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isLoggedIn() || $_SESSION['user_role'] !== 'superadmin') {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

// === Statistik ===
$total_admin = $db->query("SELECT COUNT(*) FROM admin WHERE role = 'admin'")->fetchColumn();
$total_ukm = $db->query("SELECT COUNT(*) FROM ukm")->fetchColumn();
$total_kategori = $db->query("SELECT COUNT(*) FROM kategori_ukm")->fetchColumn();
$total_mahasiswa = $db->query("SELECT COUNT(*) FROM mahasiswa")->fetchColumn();
$total_pendaftaran_menunggu = $db->query("SELECT COUNT(*) FROM pendaftaran WHERE status = 'menunggu'")->fetchColumn();

// === Ambil 5 Admin & UKM Terbaru (Terpadu) ===
$stmt = $db->prepare("
    SELECT 
        a.id AS admin_id,
        a.nama AS admin_nama,
        a.username,
        u.nama_ukm,
        u.status AS ukm_status
    FROM admin a
    LEFT JOIN ukm u ON a.ukm_id = u.id
    WHERE a.role = 'admin'
    ORDER BY a.id DESC
    LIMIT 5
");
$stmt->execute();
$admin_ukm_terbaru = $stmt->fetchAll(PDO::FETCH_ASSOC);

// === Ambil 5 Mahasiswa dengan Pendaftaran Terbaru ===
$stmt = $db->prepare("
    SELECT 
        m.nama,
        m.nim,
        m.jurusan,
        u.nama_ukm,
        p.status AS status_pendaftaran,
        p.created_at
    FROM pendaftaran p
    JOIN mahasiswa m ON p.mahasiswa_id = m.id
    JOIN ukm u ON p.ukm_id = u.id
    ORDER BY p.created_at DESC
    LIMIT 5
");
$stmt->execute();
$mahasiswa_terbaru = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
        }
        .sidebar a {
            padding: 12px;
            display: block;
            color: white;
            text-decoration: none;
            font-weight: 500;
        }
        .sidebar a:hover, .sidebar a.active {
            background: #1abc9c;
            border-radius: 5px;
        }
        .main-content {
            margin-left: 16.66%;
            padding: 20px;
        }
        .welcome-box {
            background: linear-gradient(135deg, #2980b9, #6dd5fa);
            padding: 25px;
            border-radius: 15px;
            color: white;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            margin-bottom: 24px;
        }
        .card-stats {
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: transform 0.2s;
            cursor: default;
        }
        .card-stats:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
        }
        .section-title {
            border-left: 4px solid #2980b9;
            padding-left: 12px;
            margin: 28px 0 16px;
            color: #2c3e50;
            font-weight: 600;
        }
        .table-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        .table thead th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .badge-status {
            font-size: 0.82em;
            padding: 0.3em 0.6em;
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- SIDEBAR -->
        <?php include 'sidebar.php'; ?>

        <!-- KONTEN -->
        <div class="col-10 main-content">

            <div class="welcome-box">
                <h3>Selamat datang, <?= htmlspecialchars($_SESSION['nama']) ?> 👋</h3>
                <p>Anda sedang berada di panel <strong>Super Admin</strong> untuk mengelola sistem UKM.</p>
            </div>

            <!-- STATISTIK -->
            <div class="row">
                <div class="col-md-3 mb-3">
                    <div class="card card-stats p-3 text-center">
                        <h5><i class="fas fa-users-cog text-primary"></i> Total Admin</h5>
                        <h2 class="mb-0"><?= $total_admin ?></h2>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card card-stats p-3 text-center">
                        <h5><i class="fas fa-building text-success"></i> Total UKM</h5>
                        <h2 class="mb-0"><?= $total_ukm ?></h2>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card card-stats p-3 text-center">
                        <h5><i class="fas fa-list text-warning"></i> Kategori</h5>
                        <h2 class="mb-0"><?= $total_kategori ?></h2>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card card-stats p-3 text-center">
                        <h5><i class="fas fa-user-graduate text-info"></i> Mahasiswa</h5>
                        <h2 class="mb-0"><?= $total_mahasiswa ?></h2>
                    </div>
                </div>
            </div>

            <!-- TOMBOL AKSI (sesuai permintaan Anda) -->
            <div class="d-flex gap-3 mb-4">
                <a href="kelola_adminUKM.php" class="btn btn-primary">
                    <i class="fas fa-users-cog"></i> Kelola Admin & UKM
                </a>
                <a href="kelola_mahasiswa.php" class="btn btn-success">
                    <i class="fas fa-user-graduate"></i> Kelola Mahasiswa
                </a>
            </div>

            <!-- ADMIN & UKM TERPADU (seperti di kelola_adminUKM.php) -->
            <h4 class="section-title">Admin & UKM Terbaru</h4>
            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Admin</th>
                                <th>Username</th>
                                <th>UKM</th>
                                <th>Status UKM</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($admin_ukm_terbaru)): ?>
                                <?php foreach ($admin_ukm_terbaru as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['admin_nama']) ?></td>
                                        <td><?= htmlspecialchars($row['username']) ?></td>
                                        <td><?= $row['nama_ukm'] ? htmlspecialchars($row['nama_ukm']) : '<span class="text-muted">—</span>' ?></td>
                                        <td>
                                            <?php if ($row['nama_ukm']): ?>
                                                <span class="badge <?= $row['ukm_status'] === 'aktif' ? 'bg-success' : 'bg-danger' ?> badge-status">
                                                    <?= ucfirst($row['ukm_status']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning badge-status">Belum Ada</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center text-muted">Belum ada data admin & UKM.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- MAHASISWA TERBARU (seperti di kelola_mahasiswa.php) -->
            <h4 class="section-title mt-4">Pendaftaran Mahasiswa Terbaru</h4>
            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>NIM</th>
                                <th>Jurusan</th>
                                <th>UKM</th>
                                <th>Status</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($mahasiswa_terbaru)): ?>
                                <?php foreach ($mahasiswa_terbaru as $m): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($m['nama']) ?></td>
                                        <td><?= htmlspecialchars($m['nim']) ?></td>
                                        <td><?= htmlspecialchars($m['jurusan']) ?></td>
                                        <td><?= htmlspecialchars($m['nama_ukm']) ?></td>
                                        <td>
                                            <span class="badge <?= 
                                                $m['status_pendaftaran'] === 'diterima' ? 'bg-success' : 
                                                ($m['status_pendaftaran'] === 'ditolak' ? 'bg-danger' : 'bg-warning')
                                            ?> badge-status">
                                                <?= ucfirst($m['status_pendaftaran']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('d M Y', strtotime($m['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center text-muted">Belum ada pendaftaran.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>