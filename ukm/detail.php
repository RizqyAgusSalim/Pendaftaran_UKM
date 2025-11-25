<?php

// 1. PATH FILES DEPENDENCIES
// Karena detail.php ada di folder ukm/, kita harus keluar satu tingkat (../)
require_once '../config/database.php';
require_once '../config/functions.php'; // Mengandung isLoggedIn(), formatTanggal(), dsb.

// Inisialisasi Database
$database = new Database();
$db = $database->getConnection();

// Inisialisasi variabel UKM
$ukm_detail = null;
$ukm_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($ukm_id > 0) {
    // 2. QUERY UNTUK MENGAMBIL DATA UKM SECARA LENGKAP
    try {
        $query = "
            SELECT 
                u.*, 
                k.nama_kategori,
                (SELECT COUNT(m.id) FROM pendaftaran m WHERE m.ukm_id = u.id AND m.status = 'diterima') as total_anggota
            FROM 
                ukm u 
            LEFT JOIN 
                kategori_ukm k ON u.kategori_id = k.id 
            WHERE 
                u.id = :id AND u.status = 'aktif'
            LIMIT 1
        ";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $ukm_id, PDO::PARAM_INT);
        $stmt->execute();
        $ukm_detail = $stmt->fetch(PDO::FETCH_ASSOC);

        // 3. QUERY UNTUK MENGAMBIL BERITA TERKAIT UKM (Maksimal 3)
        $query_berita = "
            SELECT 
                * FROM 
                berita 
            WHERE 
                ukm_id = :id AND status = 'published' 
            ORDER BY 
                tanggal_publikasi DESC 
            LIMIT 3
        ";
        $stmt_berita = $db->prepare($query_berita);
        $stmt_berita->bindParam(':id', $ukm_id, PDO::PARAM_INT);
        $stmt_berita->execute();
        $berita_ukm = $stmt_berita->fetchAll(PDO::FETCH_ASSOC);


        // 4. Cek Status Pendaftaran Mahasiswa (Jika Login)
        $status_pendaftaran = 'belum_daftar';
        if (isMahasiswa()) {
             // Asumsi $_SESSION['user_id'] berisi ID mahasiswa yang sedang login
            $mahasiswa_id = $_SESSION['user_id']; 
            
            $query_check = "SELECT status FROM pendaftaran WHERE ukm_id = :ukm_id AND mahasiswa_id = :mhs_id LIMIT 1";
            $stmt_check = $db->prepare($query_check);
            $stmt_check->bindParam(':ukm_id', $ukm_id, PDO::PARAM_INT);
            $stmt_check->bindParam(':mhs_id', $mahasiswa_id, PDO::PARAM_INT);
            $stmt_check->execute();
            $check = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if ($check) {
                $status_pendaftaran = $check['status']; // 'menunggu', 'diterima', 'ditolak'
            }
        }


    } catch (PDOException $e) {
        // Handle error database jika diperlukan
        // echo "Error: " . $e->getMessage();
    }
}

// Jika UKM tidak ditemukan, kembalikan ke halaman daftar atau tampilkan pesan error
if (!$ukm_detail) {
    // Tampilkan pesan error dan berhenti
    $judul_halaman = "UKM Tidak Ditemukan";
    // Jika Anda punya file header terpisah, Anda harus me-require-nya di sini.
    // Asumsi kita menggunakan template sederhana
    echo "<!DOCTYPE html><html lang='id'><head><meta charset='UTF-8'><title>$judul_halaman</title>";
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '</head><body><div class="container mt-5"><div class="alert alert-danger">UKM yang Anda cari tidak aktif atau tidak ditemukan. <a href="../index.php#ukm">Kembali ke Daftar UKM</a></div></div></body></html>';
    exit;
}

// Data ditemukan, kita tampilkan
$judul_halaman = "Detail UKM - " . $ukm_detail['nama_ukm'];
// Jika Anda menggunakan file header.php terpisah, Anda bisa memanggilnya di sini:
// require_once '../header.php'; 
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $judul_halaman ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
        }
        .ukm-header {
            background-color: var(--primary-color);
            color: white;
            padding: 50px 0;
            border-radius: 10px;
            margin-top: 70px; /* Jarak dari navbar */
        }
        .ukm-logo-detail {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 5px solid white;
            box-shadow: 0 0 10px rgba(0,0,0,0.3);
        }
        .info-box {
            background: #f8f9fa;
            border-left: 5px solid var(--secondary-color);
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    
    <main class="container">
        
        <section class="ukm-header text-center mb-5">
            <div class="row align-items-center">
                <div class="col-12">
                    <?php 
                        $logo_path = !empty($ukm_detail['logo']) ? '../uploads/' . htmlspecialchars($ukm_detail['logo']) : '';
                        if ($logo_path): 
                    ?>
                        <img src="<?= $logo_path ?>" alt="Logo <?= htmlspecialchars($ukm_detail['nama_ukm']) ?>" class="ukm-logo-detail">
                    <?php else: ?>
                        <div class="ukm-logo-detail bg-secondary d-flex align-items-center justify-content-center mx-auto">
                            <i class="fas fa-users text-white fa-4x"></i>
                        </div>
                    <?php endif; ?>
                    
                    <h1 class="mt-3 fw-bold"><?= htmlspecialchars($ukm_detail['nama_ukm']) ?></h1>
                    <span class="badge bg-warning text-dark fs-6"><?= htmlspecialchars($ukm_detail['nama_kategori'] ?: 'Umum') ?></span>
                </div>
            </div>
        </section>

        <section class="mb-5">
            <div class="row">
                <div class="col-lg-8">
                    <h2 class="fw-bold mb-3 text-primary"><i class="fas fa-file-alt me-2"></i> Tentang Kami</h2>
                    <p class="lead text-dark"><?= nl2br(htmlspecialchars($ukm_detail['deskripsi'])) ?></p>

                    <h3 class="fw-bold mt-5 mb-3 text-primary"><i class="fas fa-cogs me-2"></i> Program Kerja Utama</h3>
                    <div class="card p-4">
                        <div class="card-body">
                             <?= nl2br(htmlspecialchars($ukm_detail['program_kerja'] ?? 'Informasi program kerja belum tersedia.')) ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card p-3 shadow-sm sticky-top" style="top: 80px;">
                        <h4 class="mb-3"><i class="fas fa-info-circle me-2"></i> Informasi Cepat</h4>
                        <div class="info-box">
                            <p class="mb-1"><small class="text-muted">Ketua Umum</small></p>
                            <h5 class="fw-bold"><?= htmlspecialchars($ukm_detail['ketua_umum']) ?></h5>
                        </div>
                        <div class="info-box">
                            <p class="mb-1"><small class="text-muted">Total Anggota Aktif</small></p>
                            <h5 class="fw-bold"><?= $ukm_detail['total_anggota'] ?> <i class="fas fa-users text-success"></i></h5>
                        </div>
                        <div class="info-box">
                            <p class="mb-1"><small class="text-muted">Tahun Berdiri</small></p>
                            <h5 class="fw-bold"><?= htmlspecialchars($ukm_detail['tahun_berdiri']) ?></h5>
                        </div>
                        <div class="info-box">
                            <p class="mb-1"><small class="text-muted">Kontak</small></p>
                            <h5 class="fw-bold"><i class="fab fa-whatsapp"></i> <?= htmlspecialchars($ukm_detail['kontak']) ?></h5>
                        </div>
                        
                        <hr>
                        
                        <?php if (isMahasiswa()): ?>
                            <?php if ($status_pendaftaran == 'diterima'): ?>
                                <button class="btn btn-success btn-lg mt-2" disabled>
                                    <i class="fas fa-check-circle"></i> Anda Sudah Menjadi Anggota
                                </button>
                            <?php elseif ($status_pendaftaran == 'menunggu'): ?>
                                <button class="btn btn-warning btn-lg mt-2" disabled>
                                    <i class="fas fa-clock"></i> Pendaftaran Anda Sedang Diproses
                                </button>
                            <?php else: ?>
                                <a href="../mahasiswa/daftar_ukm.php?ukm_id=<?= $ukm_detail['id'] ?>" class="btn btn-primary btn-lg mt-2">
                                    <i class="fas fa-plus"></i> Daftar UKM Ini Sekarang
                                </a>
                            <?php endif; ?>
                        <?php elseif (!isLoggedIn()): ?>
                            <a href="../auth/login.php" class="btn btn-secondary btn-lg mt-2">
                                <i class="fas fa-sign-in-alt"></i> Login untuk Mendaftar
                            </a>
                        <?php endif; ?>
                        <a href="../index.php#ukm" class="btn btn-outline-secondary mt-3"><i class="fas fa-arrow-left"></i> Kembali ke Daftar</a>

                    </div>
                </div>
            </div>
        </section>
        
        <?php if (!empty($berita_ukm)): ?>
        <section class="my-5">
            <h2 class="fw-bold mb-4 text-center"><i class="fas fa-newspaper me-2"></i> Berita Terbaru dari UKM Ini</h2>
            <div class="row">
                <?php foreach ($berita_ukm as $berita): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card shadow-sm h-100">
                        <?php if ($berita['gambar']): ?>
                            <img src="../uploads/<?= htmlspecialchars($berita['gambar']) ?>" class="card-img-top" style="height: 180px; object-fit: cover;">
                        <?php endif; ?>
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($berita['judul']) ?></h5>
                            <p class="card-text text-muted small">
                                <i class="fas fa-calendar-alt"></i> <?= formatTanggal($berita['tanggal_publikasi']) ?>
                            </p>
                            <a href="#" class="btn btn-sm btn-outline-primary">Baca Selengkapnya</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>