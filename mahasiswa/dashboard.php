<?php
// Dashboard Mahasiswa - versi FINAL
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../config/functions.php';

if (!function_exists('isLoggedIn') || !function_exists('isMahasiswa')) {
    error_log('Fungsi tidak ditemukan');
    header('Location: ../auth/login.php');
    exit;
}

if (!isLoggedIn() || !isMahasiswa()) {
    header('Location: ../auth/login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    die("Error: Tidak dapat terhubung ke database");
}

$mahasiswa_id = (int)($_SESSION['user_id'] ?? 0);

// Ambil data mahasiswa
$mahasiswa = null;
if ($mahasiswa_id > 0) {
    $stmt = $db->prepare("SELECT * FROM mahasiswa WHERE id = ?");
    $stmt->execute([$mahasiswa_id]);
    $mahasiswa = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$mahasiswa) {
    header('Location: ../auth/logout.php');
    exit;
}

// Ambil pendaftaran
$sql = "
    SELECT 
        p.id AS pendaftaran_id,
        p.status,
        p.status_keanggotaan,
        p.created_at,
        u.nama_ukm,
        k.nama_kategori
    FROM pendaftaran p
    JOIN ukm u ON p.ukm_id = u.id
    LEFT JOIN kategori_ukm k ON u.kategori_id = k.id
    WHERE p.mahasiswa_id = ?
    ORDER BY p.created_at DESC
";
$stmt = $db->prepare($sql);
$stmt->execute([$mahasiswa_id]);
$pendaftaran_saya = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Statistik
$pending = $diterima = $ditolak = $cuti = $dikeluarkan = 0;
foreach ($pendaftaran_saya as $p) {
    $status = strtolower(trim($p['status'] ?? ''));
    $keanggotaan = strtolower(trim($p['status_keanggotaan'] ?? ''));
    if ($status === 'pending') $pending++;
    elseif ($status === 'ditolak') $ditolak++;
    elseif ($status === 'diterima') {
        $diterima++;
        if ($keanggotaan === 'cuti') $cuti++;
        elseif ($keanggotaan === 'dikeluarkan') $dikeluarkan++;
    }
}
$total_pendaftaran = count($pendaftaran_saya);

// Ambil SEMUA UKM aktif (sesuai struktur database Anda)
$stmt = $db->prepare("
    SELECT 
        u.id,
        u.nama_ukm,
        u.deskripsi,
        u.logo,
        k.nama_kategori
    FROM ukm u
    LEFT JOIN kategori_ukm k ON u.kategori_id = k.id
    WHERE u.status = 'aktif'
    ORDER BY u.nama_ukm
");
$stmt->execute();
$semua_ukm = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cek UKM mana yang sudah didaftar oleh mahasiswa
$stmt_cek = $db->prepare("SELECT ukm_id FROM pendaftaran WHERE mahasiswa_id = ? AND status = 'diterima'");
$stmt_cek->execute([$mahasiswa_id]);
$ukm_diterima = $stmt_cek->fetchAll(PDO::FETCH_COLUMN);

// Buat array untuk pengecekan cepat
$ukm_diterima_set = array_flip($ukm_diterima);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Mahasiswa - UKM Polinela</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
        }
        .navbar-custom {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        .dashboard-container { padding: 2rem 0; }
        .welcome-card { background: white; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); padding: 2rem; margin-bottom: 2rem; text-align: center; }
        .stats-card { background: white; border-radius: 15px; padding: 1.5rem; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.08); border: none; margin-bottom: 1rem; transition: transform 0.3s ease; }
        .stats-card:hover { transform: translateY(-5px); }
        .stats-number { font-size: 2rem; font-weight: bold; color: #667eea; margin-bottom: 0.5rem; }
        .content-card { background: white; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); margin-bottom: 1.5rem; }
        .btn-primary-custom { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 25px; padding: 0.8rem 2rem; font-weight: 600; color: white; text-decoration: none; transition: all 0.3s ease; }
        .btn-primary-custom:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3); color: white; }
        .empty-state { padding: 3rem; text-align: center; color: #6c757d; }
        .ukm-logo {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 50%;
            margin-bottom: 15px;
        }
        .category-badge {
            background: #3498db;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            display: inline-block;
            margin-bottom: 10px;
        }
        .ukm-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 30px;
        }
        .ukm-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-custom sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <i class="fas fa-graduation-cap me-2 text-primary"></i>Dashboard Mahasiswa
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../index.php"><i class="fas fa-home me-1"></i>Beranda</a>
                <a class="nav-link" href="edit_profil.php"><i class="fas fa-user-cog me-1"></i>Profil</a>
                <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-1"></i>Logout</a>
            </div>
        </div>
    </nav>

    <div class="dashboard-container">
        <div class="container">
            <div class="welcome-card">
                <div class="row align-items-center">
                    <div class="col-md-2">
                        <?php
                            $foto_path = $mahasiswa['foto'] ?? '';
                            $default_foto = 'https://via.placeholder.com/150?text=No+Photo';
                            $foto_url = !empty($foto_path) ? '../uploads/' . htmlspecialchars($foto_path) : $default_foto;
                        ?>
                        <img src="<?= $foto_url ?>" alt="Foto Profil" class="rounded-circle img-fluid" style="width: 100px; height: 100px; object-fit: cover;">
                    </div>
                    <div class="col-md-10 text-start">
                        <h2 class="fw-bold">Selamat Datang, <?= htmlspecialchars($mahasiswa['nama'], ENT_QUOTES, 'UTF-8') ?>!</h2>
                        <p class="mb-1">
                            <i class="fas fa-id-card me-2 text-primary"></i>
                            <strong>NIM:</strong> <?= htmlspecialchars($mahasiswa['nim'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <p class="mb-1">
                            <i class="fas fa-graduation-cap me-2 text-primary"></i>
                            <strong>Jurusan:</strong> <?= htmlspecialchars($mahasiswa['jurusan'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <p class="mb-0">
                            <i class="fas fa-calendar me-2 text-primary"></i>
                            <strong>Angkatan:</strong> <?= htmlspecialchars($mahasiswa['angkatan'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="stats-card">
                        <div class="stats-number"><?= (int)$total_pendaftaran ?></div>
                        <div class="text-muted">Total Pendaftaran</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stats-card">
                        <div class="stats-number text-warning"><?= (int)$pending ?></div>
                        <div class="text-muted">Menunggu</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stats-card">
                        <div class="stats-number text-success"><?= (int)$diterima ?></div>
                        <div class="text-muted">Diterima</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stats-card">
                        <div class="stats-number text-danger"><?= (int)$ditolak ?></div>
                        <div class="text-muted">Ditolak</div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-12">
                    <div class="content-card">
                        <div class="card-header bg-white border-0 p-4">
                            <h5 class="fw-bold mb-0">
                                <i class="fas fa-list-alt text-primary me-2"></i>Pendaftaran Saya
                            </h5>
                            <!-- ✅ TOMBOL "Daftar UKM" DIHAPUS SESUAI PERMINTAAN -->
                        </div>
                        <div class="card-body">
                            <?php if (empty($pendaftaran_saya)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-inbox fa-3x mb-3"></i>
                                    <h6>Anda belum mendaftar ke UKM manapun</h6>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>UKM</th>
                                                <th>Tanggal</th>
                                                <th>Status</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pendaftaran_saya as $p): ?>
                                                <?php
                                                    $nama_ukm = htmlspecialchars($p['nama_ukm'] ?? '-', ENT_QUOTES, 'UTF-8');
                                                    $nama_kategori = htmlspecialchars($p['nama_kategori'] ?? '-', ENT_QUOTES, 'UTF-8');
                                                    $created_at_raw = $p['created_at'] ?? '';
                                                    $created_at = '-';
                                                    if (!empty($created_at_raw) && strtotime($created_at_raw) !== false) {
                                                        $created_at = date('d/m/Y', strtotime($created_at_raw));
                                                    }
                                                    $status = $p['status'] ?? 'pending';
                                                    $keanggotaan = $p['status_keanggotaan'] ?? '';
                                                    $status_text = '';
                                                    $badge_class = 'bg-secondary';

                                                    if ($status === 'pending') {
                                                        $status_text = 'Menunggu';
                                                        $badge_class = 'bg-warning';
                                                    } elseif ($status === 'ditolak') {
                                                        $status_text = 'Ditolak';
                                                        $badge_class = 'bg-danger';
                                                    } elseif ($status === 'diterima') {
                                                        if ($keanggotaan === 'cuti') {
                                                            $status_text = 'Cuti';
                                                            $badge_class = 'bg-info';
                                                        } elseif ($keanggotaan === 'dikeluarkan') {
                                                            $status_text = 'Dikeluarkan';
                                                            $badge_class = 'bg-secondary';
                                                        } else {
                                                            $status_text = 'Aktif';
                                                            $badge_class = 'bg-success';
                                                        }
                                                    }
                                                ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= $nama_ukm ?></strong><br>
                                                        <small class="text-muted"><?= $nama_kategori ?></small>
                                                    </td>
                                                    <td><?= $created_at ?></td>
                                                    <td><span class="badge <?= $badge_class ?>"><?= $status_text ?></span></td>
                                                    <td>
                                                        <?php if ($status === 'diterima'): ?>
                                                            <a href="detail_pendaftaran.php?id=<?= $p['pendaftaran_id'] ?>" class="btn btn-sm btn-primary">
                                                                <i class="fas fa-id-card me-1"></i> Lihat KTM
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted">–</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ✅ UKM TERSEDIA: SESUAI DENGAN DATABASE & INDEX.PHP -->
                <div class="col-12">
                    <div class="content-card">
                        <div class="card-header bg-white border-0 p-4">
                            <h5 class="fw-bold mb-0">
                                <i class="fas fa-users text-success me-2"></i>Daftar UKM
                            </h5>
                        </div>
                        <div class="card-body p-3">
                            <?php if (empty($semua_ukm)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-users text-muted fa-3x mb-3"></i>
                                    <p class="text-muted">Belum ada UKM tersedia</p>
                                </div>
                            <?php else: ?>
                                <div class="row g-4">
                                    <?php foreach ($semua_ukm as $ukm): ?>
                                        <?php
                                            $ukm_id = (int)($ukm['id'] ?? 0);
                                            $ukm_nama = htmlspecialchars($ukm['nama_ukm'] ?? 'UKM Tidak Diketahui', ENT_QUOTES, 'UTF-8');
                                            $ukm_kategori = htmlspecialchars($ukm['nama_kategori'] ?? 'Umum', ENT_QUOTES, 'UTF-8');
                                            $ukm_deskripsi = htmlspecialchars($ukm['deskripsi'] ?? 'Tidak ada deskripsi', ENT_QUOTES, 'UTF-8');
                                            $logo_url = !empty($ukm['logo']) 
                                                ? '../uploads/' . htmlspecialchars($ukm['logo']) 
                                                : '';
                                            $sudah_diterima = isset($ukm_diterima_set[$ukm_id]);
                                        ?>
                                        <div class="col-md-6 col-lg-4">
                                            <div class="card ukm-card h-100">
                                                <div class="card-body text-center">
                                                    <?php if ($logo_url): ?>
                                                        <img src="<?= $logo_url ?>" alt="Logo <?= $ukm_nama ?>" class="ukm-logo">
                                                    <?php else: ?>
                                                        <div class="ukm-logo bg-secondary d-flex align-items-center justify-content-center mx-auto">
                                                            <i class="fas fa-users text-white fa-2x"></i>
                                                        </div>
                                                    <?php endif; ?>

                                                    <div class="category-badge"><?= $ukm_kategori ?></div>
                                                    <h5 class="card-title fw-bold"><?= $ukm_nama ?></h5>
                                                    <p class="card-text text-muted"><?= substr($ukm_deskripsi, 0, 100) ?>...</p>

                                                    <div class="mt-3">
                                                        <?php if ($sudah_diterima): ?>
                                                            <span class="badge bg-success text-white">Sudah Bergabung</span>
                                                        <?php else: ?>
                                                            <a href="../ukm/detail.php?id=<?= $ukm_id ?>" class="btn btn-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 25px; padding: 10px 25px; font-weight: 600; color: white; width: 100%;">
                                                                <i class="fas fa-info-circle me-1"></i> Lihat Detail
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>