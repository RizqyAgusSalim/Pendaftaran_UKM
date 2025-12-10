<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isLoggedIn() || !isMahasiswa()) {
    header('Location: ../auth/login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();
$mahasiswa_id = $_SESSION['user_id'];
$pendaftaran_id = $_GET['id'] ?? 0;

// Ambil data pendaftaran + relasi
$stmt = $db->prepare("
    SELECT 
        p.id, p.status, p.status_keanggotaan,
        m.nama AS nama_mahasiswa,
        m.nim,
        m.jurusan,
        m.foto AS foto_mahasiswa,
        u.nama_ukm,
        u.logo AS logo_ukm,
        k.nama_kategori
    FROM pendaftaran p
    JOIN mahasiswa m ON p.mahasiswa_id = m.id
    JOIN ukm u ON p.ukm_id = u.id
    LEFT JOIN kategori_ukm k ON u.kategori_id = k.id
    WHERE p.id = ? AND p.mahasiswa_id = ?
");
$stmt->execute([$pendaftaran_id, $mahasiswa_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die("<div class='alert alert-danger m-4'>Data pendaftaran tidak ditemukan.</div>");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pendaftaran - <?= htmlspecialchars($data['nama_ukm']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Warna utama */
        :root {
            --header-bg: #005A2B; /* Hijau tua */
            --footer-bg: #FFD700; /* Kuning */
            --text-header: white;
            --text-body: #005A2B;
            --border-color: #ddd;
        }

        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .kta-card {
            width: 500px;
            margin: 0 auto;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            background: white;
            font-family: Arial, sans-serif;
        }

        /* Header Hijau Tua */
        .kta-header {
            background: var(--header-bg);
            color: var(--text-header);
            padding: 15px 20px;
            position: relative;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .kta-header::after {
            content: '';
            position: absolute;
            bottom: -20px;
            right: 0;
            width: 100%;
            height: 20px;
            background: var(--footer-bg);
            clip-path: polygon(0 0, 100% 0, 100% 100%, 0 100%);
        }

        .kta-logo-ukm {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid white;
        }
        .kta-logo-ukm img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .kta-ukm-info {
            flex: 1;
        }
        .kta-ukm-info h4 {
            margin: 0;
            font-weight: bold;
            font-size: 1.2rem;
        }
        .kta-ukm-info p {
            margin: 5px 0 0;
            font-size: 0.85rem;
            opacity: 0.9;
        }

        /* Body Utama */
        .kta-body {
            padding: 25px;
            position: relative;
            background-image: url('https://i.imgur.com/6JZlHdN.png'); /* Peta Indonesia transparan */
            background-size: 70%;
            background-position: right bottom;
            background-repeat: no-repeat;
            min-height: 250px;
        }

        /* Foto Mahasiswa - Kotak di kiri atas */
        .kta-photo {
            width: 120px;
            height: 160px;
            border: 2px solid var(--border-color);
            overflow: hidden;
            float: left;
            margin-right: 20px;
            background: #ecf0f1;
        }
        .kta-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Informasi Mahasiswa */
        .kta-info {
            margin-left: 140px;
        }
        .kta-name {
            font-size: 1.4rem;
            font-weight: bold;
            color: var(--text-body);
            margin: 0 0 5px;
        }
        .kta-role {
            font-size: 1rem;
            color: var(--text-body);
            margin: 0 0 5px;
        }
        .kta-nim {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--text-body);
            margin: 0;
        }

        /* Barcode */
        .kta-barcode {
            text-align: center;
            margin-top: 20px;
            padding: 10px 0;
            border-top: 1px dashed var(--border-color);
        }
        .barcode-img {
            width: 80%;
            max-width: 300px;
            height: auto;
            border: 1px solid #ccc;
        }

        /* Footer Kuning */
        .kta-footer {
            background: var(--footer-bg);
            padding: 10px 20px;
            text-align: center;
            font-weight: bold;
            color: #005A2B;
        }
        .kta-footer img {
            height: 30px;
            vertical-align: middle;
            margin-right: 5px;
        }

        /* Tombol Cetak */
        .btn-print {
            background: linear-gradient(135deg, #005A2B, #004420);
            color: white;
            border: none;
            border-radius: 25px;
            padding: 10px 25px;
            font-weight: 600;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
        }

        /* Print Style */
        @media print {
            body * {
                visibility: hidden;
            }
            .kta-card, .kta-card * {
                visibility: visible !important;
            }
            .kta-card {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 500px;
                margin: 0;
                padding: 0;
                box-shadow: none;
                border: none;
            }
            @page {
                size: A4 portrait;
                margin: 0;
            }
            * {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
        }
    </style>
    <script>
        function printKTA() {
            window.print();
        }
    </script>
</head>
<body>
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-file-alt me-2"></i>Detail Pendaftaran</h2>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <!-- Info Umum -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="mb-3"><i class="fas fa-info-circle text-primary"></i> Informasi Pendaftaran</h5>
                        <p><strong>UKM:</strong> <?= htmlspecialchars($data['nama_ukm']) ?></p>
                        <p><strong>Status Pendaftaran:</strong> 
                            <span class="badge bg-<?= ($data['status'] === 'diterima') ? 'success' : (($data['status'] === 'ditolak') ? 'danger' : 'warning') ?>">
                                <?= ucfirst(htmlspecialchars($data['status'])) ?>
                            </span>
                        </p>
                        <?php if ($data['status'] === 'diterima'): ?>
                            <p><strong>Status Keanggotaan:</strong> 
                                <span class="status-badge <?= 
                                    ($data['status_keanggotaan'] === 'aktif') ? 'status-aktif' : 
                                    (($data['status_keanggotaan'] === 'cuti') ? 'status-cuti' : 'status-dikeluarkan')
                                ?>">
                                    <?= ucfirst(htmlspecialchars($data['status_keanggotaan'])) ?>
                                </span>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- KARTU TANDA ANGGOTA -->
                <?php if ($data['status'] === 'diterima'): ?>
                    <div class="text-center mb-4 no-print">
                        <h4 class="text-success"><i class="fas fa-id-card me-2"></i>Kartu Tanda Anggota (KTA)</h4>
                        <p class="text-muted">Kartu ini berlaku sebagai bukti keanggotaan resmi di UKM.</p>
                    </div>

                    <!-- KARTU UTAMA -->
                    <div class="kta-card" id="kta-card">
                        <!-- Header Hijau Tua — Ambil dari tabel ukm -->
                        <div class="kta-header">
                            <!-- Logo UKM -->
                            <div class="kta-logo-ukm">
                                <?php if (!empty($data['logo_ukm'])): ?>
                                    <img src="../uploads/<?= htmlspecialchars($data['logo_ukm']) ?>" alt="Logo UKM">
                                <?php else: ?>
                                    <div style="background:#ddd;height:100%;display:flex;align-items:center;justify-content:center;">
                                        <i class="fas fa-graduation-cap fa-2x"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="kta-ukm-info">
                                <h4><?= htmlspecialchars($data['nama_ukm']) ?></h4>
                                <p><?= htmlspecialchars($data['nama_kategori'] ?? 'Unit Kegiatan Mahasiswa') ?></p>
                            </div>
                        </div>

                        <!-- Body Utama -->
                        <div class="kta-body">
                            <!-- Foto Mahasiswa -->
                            <?php if (!empty($data['foto_mahasiswa'])): ?>
                                <div class="kta-photo">
                                    <img src="../uploads/<?= htmlspecialchars($data['foto_mahasiswa']) ?>" alt="Foto Mahasiswa">
                                </div>
                            <?php else: ?>
                                <div class="kta-photo">
                                    <div style="background:#ddd;height:100%;display:flex;align-items:center;justify-content:center;">
                                        <i class="fas fa-user fa-2x"></i>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Informasi Mahasiswa -->
                            <div class="kta-info">
                                <div class="kta-name"><?= htmlspecialchars($data['nama_mahasiswa']) ?></div>
                                <div class="kta-role">Mahasiswa</div>
                                <div class="kta-role"><?= htmlspecialchars($data['jurusan']) ?> - <?= htmlspecialchars($data['nama_ukm']) ?></div>
                                <div class="kta-nim"><?= htmlspecialchars($data['nim']) ?></div>
                            </div>

                            <!-- Barcode -->
                            <div class="kta-barcode">
                                <!-- Simulasi barcode -->
                                <img src="https://via.placeholder.com/300x50/ffffff/000000?text=BARCODE+SIMULASI" alt="Barcode" class="barcode-img">
                            </div>
                        </div>

                        <!-- Footer Kuning — Logo UKM -->
                        <div class="kta-footer">
                            <?php if (!empty($data['logo_ukm'])): ?>
                                <img src="../uploads/<?= htmlspecialchars($data['logo_ukm']) ?>" alt="Logo UKM" style="height:25px;margin-right:5px;"> 
                            <?php endif; ?>
                            Kartu Tanda Anggota UKM
                        </div>
                    </div>

                    <!-- ✅ TOMBOL CETAK LANGSUNG -->
                    <div class="text-center mt-4 no-print">
                        <button onclick="printKTA()" class="btn btn-print">
                            <i class="fas fa-print me-1"></i> Cetak Kartu Tanda Anggota
                        </button>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning text-center">
                        <i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>
                        Kartu Tanda Anggota hanya tersedia untuk mahasiswa yang <strong>diterima</strong> di UKM.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</body>
</html>