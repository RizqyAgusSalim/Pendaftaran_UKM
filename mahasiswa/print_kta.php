<?php
session_start();
require_once '../config/database.php';

if (!isset($_GET['id'])) {
    exit('ID tidak valid');
}
if (!isset($_SESSION['user_id'])) {
    exit('Anda harus login.');
}

$database = new Database();
$db = $database->getConnection();

$pendaftaran_id = (int)$_GET['id'];
$mahasiswa_id = (int)$_SESSION['user_id'];

$stmt = $db->prepare("
    SELECT 
        m.nama,
        m.nim,
        m.jurusan,
        u.nama_ukm,
        u.logo,
        k.nama_kategori,
        p.status_keanggotaan
    FROM pendaftaran p
    JOIN mahasiswa m ON p.mahasiswa_id = m.id
    JOIN ukm u ON p.ukm_id = u.id
    JOIN kategori_ukm k ON u.kategori_id = k.id
    WHERE p.id = ? AND p.status_keanggotaan = 'aktif' AND m.id = ?
");
$stmt->execute([$pendaftaran_id, $mahasiswa_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    exit('KTA tidak ditemukan atau Anda tidak berhak mengakses.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kartu Tanda Anggota - <?= htmlspecialchars($data['nama_ukm']) ?></title>
    <style>
        body {
            background: #f5f7fa;
            margin: 0;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }

        .actions {
            margin-bottom: 25px;
            text-align: center;
        }

        .btn-print {
            background: #2c3e50;
            color: white;
            border: none;
            padding: 10px 24px;
            font-size: 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.3s;
        }
        .btn-print:hover {
            background: #1a252f;
        }

        /* Kartu KTA */
        .kta-card {
            max-width: 500px;
            width: 100%;
            padding: 25px;
            border: 2px solid #2c3e50;
            border-radius: 12px;
            background: white;
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
            position: relative;
        }

        .kta-logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 15px;
            overflow: hidden;
            border: 3px solid #2c3e50;
            background: #ecf0f1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .kta-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .kta-title {
            font-weight: bold;
            font-size: 1.3rem;
            text-align: center;
            margin: 0 0 5px;
            color: #2c3e50;
        }
        .kta-subtitle {
            text-align: center;
            color: #7f8c8d;
            font-size: 0.9rem;
            margin: 0 0 20px;
        }

        .field {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #ddd;
        }
        .label {
            font-size: 0.8rem;
            color: #7f8c8d;
            font-weight: 500;
            margin-bottom: 4px;
        }
        .value {
            font-weight: bold;
            font-size: 1.05rem;
            color: #2c3e50;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
            background: #d4edda;
            color: #155724;
        }
        .status-cuti {
            background: #fff3cd;
            color: #856404;
        }
        .status-dikeluarkan {
            background: #f8d7da;
            color: #721c24;
        }

        .footer-note {
            text-align: center;
            margin-top: 15px;
            font-size: 0.85rem;
            color: #7f8c8d;
        }

        /* STYLING UNTUK PRINT — PERTAHANKAN SEMUA GAYA */
        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }
            .actions {
                display: none;
            }
            .kta-card {
                box-shadow: none;
                border: 2px solid #2c3e50;
                margin: 0 auto;
                max-width: 500px;
                width: 100%;
                padding: 25px;
                background: white;
            }
            /* Jika ingin ukuran kertas A4, gunakan ini */
            @page {
                size: A4 portrait;
                margin: 1cm;
            }
            /* Pastikan warna dan border tetap tercetak */
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
    <div class="actions">
        <button class="btn-print" onclick="printKTA()">🖨️ Cetak Kartu Tanda Anggota</button>
    </div>

    <div class="kta-card">
        <div class="kta-logo">
            <?php if (!empty($data['logo'])): ?>
                <img src="../uploads/<?= htmlspecialchars($data['logo']) ?>" alt="Logo UKM">
            <?php else: ?>
                <span style="font-weight:bold;color:#2c3e50;">UKM</span>
            <?php endif; ?>
        </div>
        <h3 class="kta-title">KARTU TANDA ANGGOTA</h3>
        <p class="kta-subtitle"><?= htmlspecialchars($data['nama_kategori'] ?? 'Keilmuan') ?></p>
        
        <div class="field">
            <div class="label">NAMA</div>
            <div class="value"><?= htmlspecialchars($data['nama']) ?></div>
        </div>
        <div class="field">
            <div class="label">NIM</div>
            <div class="value"><?= htmlspecialchars($data['nim']) ?></div>
        </div>
        <div class="field">
            <div class="label">JURUSAN</div>
            <div class="value"><?= htmlspecialchars($data['jurusan'] ?? '—') ?></div>
        </div>
        <div class="field">
            <div class="label">ORGANISASI</div>
            <div class="value"><?= htmlspecialchars($data['nama_ukm']) ?></div>
        </div>
        <div class="field">
            <div class="label">STATUS KEANGGOTAAN</div>
            <div class="value">
                <span class="status-badge <?= 
                    ($data['status_keanggotaan'] === 'aktif') ? '' : 
                    (($data['status_keanggotaan'] === 'cuti') ? 'status-cuti' : 'status-dikeluarkan')
                ?>">
                    <?= ucfirst(htmlspecialchars($data['status_keanggotaan'])) ?>
                </span>
            </div>
        </div>

        <div class="footer-note">
            Berlaku selama menjadi anggota aktif
        </div>
    </div>
</body>
</html>