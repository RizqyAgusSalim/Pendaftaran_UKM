<?php
// ukm/detail.php — TAMPILKAN SEMUA DATA DARI admin/kelola_ukm.php
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    if (function_exists('redirect')) {
        redirect('../ukm/index.php');
    } else {
        header('Location: ../ukm/index.php');
        exit();
    }
}

$ukm_id = (int)$_GET['id'];
$database = new Database();
$db = $database->getConnection();

$stmt = $db->prepare("
    SELECT u.*, k.nama_kategori 
    FROM ukm u 
    LEFT JOIN kategori_ukm k ON u.kategori_id = k.id 
    WHERE u.id = :id
");
$stmt->bindParam(':id', $ukm_id, PDO::PARAM_INT);
$stmt->execute();
$ukm = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ukm) {
    if (function_exists('redirect')) {
        redirect('../ukm/index.php');
    } else {
        header('Location: ../ukm/index.php');
        exit();
    }
}

// Hitung anggota
$stmt_anggota = $db->prepare("SELECT COUNT(*) FROM pendaftaran WHERE ukm_id = ? AND status = 'diterima'");
$stmt_anggota->execute([$ukm_id]);
$anggota_count = (int)$stmt_anggota->fetchColumn();

// Ambil kegiatan_ukm
$kegiatan_list = [];
try {
    $stmt_keg = $db->prepare("
        SELECT * FROM kegiatan_ukm 
        WHERE ukm_id = ? 
        ORDER BY tanggal_mulai DESC 
        LIMIT 3
    ");
    $stmt_keg->execute([$ukm_id]);
    $kegiatan_list = $stmt_keg->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $kegiatan_list = [];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($ukm['nama_ukm']) ?> - Detail UKM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1e3c72;
            --secondary-color: #2a5298;
            --accent-color: #ffc107;
        }
        .ukm-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 80px 0;
            border-bottom-left-radius: 50px;
            border-bottom-right-radius: 50px;
            margin-bottom: 30px;
        }
        .logo-container {
            width: 150px;
            height: 150px;
            background: white;
            border-radius: 50%;
            padding: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
            margin-bottom: 20px;
            border: 5px solid rgba(255, 255, 255, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logo-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .badge-kategori {
            font-size: 0.9rem;
            font-weight: 600;
            padding: 0.5rem 1.2rem;
            border-radius: 50px;
            background-color: var(--accent-color) !important;
            color: #333 !important;
        }
        .info-card {
            border: 1px solid #f0f0f0;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
        }
        .info-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .section-title {
            color: var(--primary-color);
            font-weight: 700;
            padding-bottom: 15px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        .kegiatan-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: none;
        }
        .foto-thumb {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 6px;
        }
        .btn-daftar {
            padding: 15px 50px;
            font-size: 1.1rem;
            border-radius: 50px;
            background-color: var(--accent-color) !important; 
            color: #333 !important;
            font-weight: bold;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .btn-daftar:hover {
            background-color: #ffca2c !important; 
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body>
    <div class="ukm-header">
        <div class="container text-center">
            <div class="logo-container mx-auto">
                <?php if (!empty($ukm['logo'])): ?>
                    <img src="../uploads/<?= htmlspecialchars($ukm['logo']) ?>" alt="<?= htmlspecialchars($ukm['nama_ukm']) ?>">
                <?php else: ?>
                    <i class="fas fa-cubes fa-5x text-secondary opacity-75"></i> 
                <?php endif; ?>
            </div>
            <h1 class="display-3 fw-bolder mb-3"><?= htmlspecialchars($ukm['nama_ukm']) ?></h1>
            <p class="lead mb-0">
                <span class="badge badge-kategori">
                    <i class="fas fa-tag me-1"></i> <?= htmlspecialchars($ukm['nama_kategori'] ?? 'Umum') ?>
                </span>
            </p>
        </div>
    </div>

    <div class="container my-5">
        <div class="row">
            <div class="col-lg-8 mb-4">
                <!-- Profil Lengkap UKM -->
                <div class="card info-card mb-5">
                    <div class="card-body p-4">
                        <h3 class="section-title"><i class="fas fa-book-open"></i> Profil Lengkap UKM</h3>
                        
                        <!-- Deskripsi -->
                        <?php if (!empty($ukm['deskripsi'])): ?>
                            <h5 class="mt-3"><i class="fas fa-info-circle text-primary"></i> Deskripsi</h5>
                            <p class="text-secondary"><?= nl2br(htmlspecialchars($ukm['deskripsi'])) ?></p>
                        <?php endif; ?>

                        <!-- Visi -->
                        <?php if (!empty($ukm['visi'])): ?>
                            <h5 class="mt-4"><i class="fas fa-eye text-success"></i> Visi</h5>
                            <p class="text-secondary"><?= nl2br(htmlspecialchars($ukm['visi'])) ?></p>
                        <?php endif; ?>

                        <!-- Misi -->
                        <?php if (!empty($ukm['misi'])): ?>
                            <h5 class="mt-4"><i class="fas fa-bullseye text-danger"></i> Misi</h5>
                            <p class="text-secondary"><?= nl2br(htmlspecialchars($ukm['misi'])) ?></p>
                        <?php endif; ?>

                        <!-- Program Kerja -->
                        <?php if (!empty($ukm['program_kerja'])): ?>
                            <h5 class="mt-4"><i class="fas fa-tasks text-warning"></i> Program Kerja</h5>
                            <p class="text-secondary"><?= nl2br(htmlspecialchars($ukm['program_kerja'])) ?></p>
                        <?php endif; ?>

                        <!-- Syarat Pendaftaran -->
                        <?php if (!empty($ukm['syarat_pendaftaran'])): ?>
                            <h5 class="mt-4"><i class="fas fa-clipboard-list text-info"></i> Syarat Pendaftaran</h5>
                            <p class="text-secondary"><?= nl2br(htmlspecialchars($ukm['syarat_pendaftaran'])) ?></p>
                        <?php endif; ?>

                        <!-- Alamat Sekretariat -->
                        <?php if (!empty($ukm['alamat_sekretariat'])): ?>
                            <h5 class="mt-4"><i class="fas fa-map-marker-alt text-secondary"></i> Alamat Sekretariat</h5>
                            <p class="text-secondary"><?= nl2br(htmlspecialchars($ukm['alamat_sekretariat'])) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Kegiatan UKM -->
                <div class="card info-card mb-4">
                    <div class="card-body p-4">
                        <h3 class="section-title"><i class="fas fa-calendar-check text-success"></i> Kegiatan Terbaru</h3>
                        
                        <?php if (empty($kegiatan_list)): ?>
                            <div class="text-center py-5 bg-light rounded-3">
                                <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Belum ada kegiatan terpublikasi.</h5>
                                <p class="text-secondary">Nantikan pembaruan dari pengurus UKM!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($kegiatan_list as $keg): ?>
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h5 class="fw-bold"><?= htmlspecialchars($keg['nama_kegiatan']) ?></h5>
                                        <p class="text-muted mb-2">
                                            <i class="fas fa-calendar-day me-1"></i> 
                                            <?= $keg['tanggal_mulai'] ? date('d F Y', strtotime($keg['tanggal_mulai'])) : '—' ?> |
                                            <i class="fas fa-map-marker-alt me-1"></i> <?= htmlspecialchars($keg['lokasi'] ?? '—') ?>
                                        </p>
                                        <p><?= nl2br(htmlspecialchars($keg['deskripsi_kegiatan'] ?? '—')) ?></p>

                                        <!-- Foto Kegiatan -->
                                        <?php
                                        $stmt_foto = $db->prepare("SELECT * FROM foto_kegiatan WHERE kegiatan_id = ? ORDER BY id ASC");
                                        $stmt_foto->execute([$keg['id']]);
                                        $foto_list = $stmt_foto->fetchAll(PDO::FETCH_ASSOC);
                                        ?>
                                        <?php if (!empty($foto_list)): ?>
                                            <div class="mt-3">
                                                <h6 class="mb-2"><i class="fas fa-images text-primary"></i> Dokumentasi</h6>
                                                <div class="row">
                                                    <?php foreach ($foto_list as $foto): ?>
                                                        <div class="col-md-6 col-lg-4 mb-2">
                                                            <img src="../uploads/kegiatan/<?= htmlspecialchars($foto['foto']) ?>" 
                                                                 class="foto-thumb" 
                                                                 alt="Foto kegiatan">
                                                            <?php if (!empty($foto['keterangan'])): ?>
                                                                <small class="text-muted d-block mt-1"><?= htmlspecialchars($foto['keterangan']) ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-end mt-3">
                                <a href="kegiatan.php?ukm_id=<?= $ukm['id'] ?>" class="btn btn-outline-primary btn-sm rounded-pill">
                                    Lihat Semua <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <div class="card info-card mb-4">
                    <div class="card-body p-4">
                        <h4 class="text-center fw-bold mb-4 text-secondary"><i class="fas fa-clipboard-list me-2"></i> Detail UKM</h4>
                        
                        <!-- Tanggal Berdiri -->
                        <div class="d-flex align-items-center mb-3">
                            <div class="info-icon me-3" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div>
                                <h6 class="text-muted mb-0">Tanggal Berdiri</h6>
                                <h5 class="fw-bold">
                                    <?php if (!empty($ukm['tahun_berdiri'])): ?>
                                        <?php
                                            $date = new DateTime($ukm['tahun_berdiri']);
                                            echo $date->format('d F Y');
                                        ?>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </h5>
                            </div>
                        </div>

                        <hr class="my-3">

                        <div class="d-flex align-items-center mb-3">
                            <div class="info-icon me-3" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                <i class="fas fa-users"></i>
                            </div>
                            <div>
                                <h6 class="text-muted mb-0">Anggota Aktif</h6>
                                <h5 class="fw-bold"><?= $anggota_count ?> Orang</h5>
                            </div>
                        </div>

                        <hr class="my-3">

                        <div class="d-flex align-items-center mb-3">
                            <div class="info-icon me-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div>
                                <h6 class="text-muted mb-0">Ketua Umum</h6>
                                <h5 class="fw-bold"><?= htmlspecialchars($ukm['ketua_umum'] ?? 'N/A') ?></h5>
                            </div>
                        </div>

                        <?php if (!empty($ukm['email'])): ?>
                        <hr class="my-3">
                        <div class="d-flex align-items-center mb-3">
                            <div class="info-icon me-3" style="background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 100%);">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div>
                                <h6 class="text-muted mb-0">Email</h6>
                                <h5 class="fw-bold"><?= htmlspecialchars($ukm['email']) ?></h5>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($ukm['no_telepon'])): ?>
                        <hr class="my-3">
                        <div class="d-flex align-items-center">
                            <div class="info-icon me-3" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                <i class="fas fa-phone-alt"></i>
                            </div>
                            <div>
                                <h6 class="text-muted mb-0">Kontak</h6>
                                <h5 class="fw-bold"><?= htmlspecialchars($ukm['no_telepon']) ?></h5>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tombol Daftar -->
                <div class="card info-card border-0" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);">
                    <div class="card-body p-4 text-center text-white">
                        <h4 class="mb-3 fw-bold">Tertarik Bergabung?</h4>
                        <p class="mb-4">Daftarkan dirimu sekarang dan kembangkan bakatmu!</p>
                        <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
                            <a href="../mahasiswa/daftar_ukm.php?ukm_id=<?= $ukm['id'] ?>" class="btn btn-warning btn-daftar w-100">
                                <i class="fas fa-user-plus"></i> Daftar UKM Ini Sekarang
                            </a>
                        <?php else: ?>
                            <a href="../auth/login.php" class="btn btn-warning btn-daftar w-100">
                                <i class="fas fa-sign-in-alt"></i> Login untuk Mendaftar
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-3 text-center">
                    <a href="../ukm/index.php" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-arrow-left me-2"></i> Kembali ke Daftar UKM
                    </a>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container text-center">
            <p class="mb-0">&copy; <?= date('Y') ?> Sistem Pendaftaran UKM - Politeknik Negeri Lampung</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>