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
        body {
            background: linear-gradient(135deg, #ecf0f1, #bdc3c7);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 20px;
            min-height: 100vh;
        }

        .page-header {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        /* 💙 DESAIN KTA BIRU */
        .kta-card {
            width: 500px;
            margin: 0 auto;
            position: relative;
            overflow: hidden;
            box-shadow: 0 6px 18px rgba(0,0,0,0.15);
            background: white;
            font-family: Arial, sans-serif;
            border-radius: 10px;
        }

        /* Header Biru Tua */
        .kta-header {
            background: #2c3e50; /* Biru tua */
            color: white;
            padding: 20px;
            position: relative;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .kta-logo-ukm {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid rgba(255,255,255,0.3);
        }
        .kta-logo-ukm img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .kta-ukm-info h4 {
            margin: 0;
            font-weight: bold;
            font-size: 1.3rem;
        }
        .kta-ukm-info p {
            margin: 5px 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .kta-body {
            padding: 30px;
            position: relative;
            background: #f8f9fa;
            min-height: 250px;
        }

        .kta-photo {
            width: 120px;
            height: 160px;
            border: 2px solid #3498db;
            overflow: hidden;
            float: left;
            margin-right: 25px;
            background: #ecf0f1;
            border-radius: 8px;
        }
        .kta-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .kta-info {
            margin-left: 145px;
        }
        .kta-name {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
            margin: 0 0 8px;
        }
        .kta-role {
            font-size: 1.05rem;
            color: #34495e;
            margin: 0 0 6px;
        }
        .kta-nim {
            font-size: 1.25rem;
            font-weight: bold;
            color: #3498db;
            margin: 0;
        }

        /* Footer Biru Muda */
        .kta-footer {
            background: #3498db; /* Biru muda */
            padding: 12px 20px;
            text-align: center;
            font-weight: bold;
            color: white;
            font-size: 1.1rem;
        }
        .kta-footer img {
            height: 30px;
            vertical-align: middle;
            margin-right: 8px;
        }

        /* Tombol */
        .btn-blue {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            border-radius: 30px;
            padding: 10px 25px;
            font-weight: 600;
            box-shadow: 0 4px 10px rgba(52, 152, 219, 0.3);
            transition: all 0.3s ease;
        }
        .btn-blue:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(52, 152, 219, 0.4);
            background: linear-gradient(135deg, #2980b9, #2573a7);
            color: white;
        }

        .btn-back {
            background: transparent;
            color: #3498db;
            border: 2px solid #3498db;
            border-radius: 30px;
            padding: 10px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-back:hover {
            background: #3498db;
            color: white;
        }

        /* Badge Status */
        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .status-aktif { background: #d4edda; color: #155724; }
        .status-cuti { background: #fff3cd; color: #856404; }
        .status-dikeluarkan { background: #f8d7da; color: #721c24; }

        /* Print Style */
        @media print {
            body * { visibility: hidden; }
            .kta-card, .kta-card * { visibility: visible !important; }
            .kta-card {
                position: absolute;
                top: 10px;
                left: 50%;
                transform: translateX(-50%);
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
        }
    </style>
    <script>
        function printKTA() {
            window.print();
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="page-header d-flex justify-content-between align-items-center">
            <h2><i class="fas fa-file-alt me-2"></i>Detail Pendaftaran</h2>
            <a href="dashboard.php" class="btn btn-back">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Info Pendaftaran -->
                <div class="card shadow-sm mb-4">
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

                <!-- KTA -->
                <?php if ($data['status'] === 'diterima'): ?>
                    <div class="text-center mb-4">
                        <h4 class="text-primary"><i class="fas fa-id-card me-2"></i>Kartu Tanda Anggota (KTA)</h4>
                        <p class="text-muted">Kartu ini berlaku sebagai bukti keanggotaan resmi di UKM.</p>
                    </div>

                    <!-- Kartu KTA Biru -->
                    <div class="kta-card">
                        <div class="kta-header">
                            <div class="kta-logo-ukm">
                                <?php if (!empty($data['logo_ukm'])): ?>
                                    <img src="../uploads/<?= htmlspecialchars($data['logo_ukm']) ?>" alt="Logo UKM">
                                <?php else: ?>
                                    <div style="background:#34495e;height:100%;display:flex;align-items:center;justify-content:center;color:white;">
                                        <i class="fas fa-graduation-cap fa-2x"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="kta-ukm-info">
                                <h4>KARTU TANDA ANGGOTA<br>UNIT KEGIATAN MAHASISWA</h4>
                                <p><?= htmlspecialchars($data['nama_ukm']) ?> • <?= htmlspecialchars($data['nama_kategori'] ?? 'UKM') ?></p>
                            </div>
                        </div>

                        <div class="kta-body">
                            <?php if (!empty($data['foto_mahasiswa'])): ?>
                                <div class="kta-photo">
                                    <img src="../uploads/<?= htmlspecialchars($data['foto_mahasiswa']) ?>" alt="Foto Mahasiswa">
                                </div>
                            <?php else: ?>
                                <div class="kta-photo">
                                    <div style="background:#bdc3c7;height:100%;display:flex;align-items:center;justify-content:center;color:#7f8c8d;">
                                        <i class="fas fa-user fa-2x"></i>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="kta-info">
                                <div class="kta-name"><?= htmlspecialchars($data['nama_mahasiswa']) ?></div>
                                <div class="kta-role">Mahasiswa Anggota</div>
                                <div class="kta-role"><?= htmlspecialchars($data['jurusan']) ?></div>
                                <div class="kta-nim">NIM: <?= htmlspecialchars($data['nim']) ?></div>
                            </div>
                        </div>

                        <div class="kta-footer">
                            <?= strtoupper(htmlspecialchars($data['nama_ukm'])) ?>
                        </div>
                    </div>

                    <!-- Tombol Cetak -->
                    <div class="text-center mt-4">
                        <button onclick="printKTA()" class="btn btn-blue">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>