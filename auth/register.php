<?php
// auth/register.php
require_once '../config/database.php';
require_once '../config/functions.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success = '';

if ($_POST) {
    $nim = sanitize($_POST['nim']);
    $nama = sanitize($_POST['nama']);
    $email = sanitize($_POST['email']);
    $no_telepon = sanitize($_POST['no_telepon']);
    $jurusan = sanitize($_POST['jurusan']);
    $angkatan = sanitize($_POST['angkatan']);
    $alamat = sanitize($_POST['alamat']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validasi
    if (empty($nim) || empty($nama) || empty($email) || empty($password)) {
        $error = 'Data wajib harus diisi';
    } elseif ($password !== $confirm_password) {
        $error = 'Password dan konfirmasi password tidak sama';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter';
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        // Cek apakah NIM sudah ada
        $query = "SELECT COUNT(*) FROM mahasiswa WHERE nim = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$nim]);
        
        if ($stmt->fetchColumn() > 0) {
            $error = 'NIM sudah terdaftar';
        } else {
            // Cek apakah email sudah ada
            $query = "SELECT COUNT(*) FROM mahasiswa WHERE email = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$email]);
            
            if ($stmt->fetchColumn() > 0) {
                $error = 'Email sudah terdaftar';
            } else {
                // Upload foto jika ada
                $foto = null;
                if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
                    $foto = uploadFile($_FILES['foto'], '../uploads/');
                    if (!$foto) {
                        $error = 'Gagal upload foto. Pastikan file berformat JPG/PNG dan ukuran maksimal 5MB';
                    }
                }
                
                if (!$error) {
                    // Insert data mahasiswa
                    $query = "INSERT INTO mahasiswa (nim, nama, email, no_telepon, jurusan, angkatan, alamat, foto, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    
                    if ($stmt->execute([
                        $nim, $nama, $email, $no_telepon, $jurusan, $angkatan, $alamat, $foto,
                        password_hash($password, PASSWORD_DEFAULT)
                    ])) {
                        $success = 'Pendaftaran berhasil! Silakan login dengan NIM dan password Anda.';
                    } else {
                        $error = 'Gagal mendaftar. Silakan coba lagi.';
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - Sistem UKM Polinela</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }
        .register-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        .register-header {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .form-floating input:focus, .form-floating select:focus, .form-floating textarea:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        .btn-register {
            background: #3498db;
            border: none;
            border-radius: 25px;
            padding: 12px 0;
            font-weight: 600;
        }
        .btn-register:hover {
            background: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="register-card">
                    <div class="register-header">
                        <i class="fas fa-user-plus fa-3x mb-3"></i>
                        <h3>Daftar Mahasiswa Baru</h3>
                        <p class="mb-0">Sistem UKM Politeknik Negeri Lampung</p>
                    </div>
                    <div class="p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?= $success ?>
                                <div class="mt-2">
                                    <a href="login.php" class="btn btn-success btn-sm">Login Sekarang</a>
                                </div>
                            </div>
                        <?php else: ?>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="nim" name="nim" placeholder="NIM" required>
                                        <label for="nim">
                                            <i class="fas fa-id-card"></i> NIM *
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="nama" name="nama" placeholder="Nama Lengkap" required>
                                        <label for="nama">
                                            <i class="fas fa-user"></i> Nama Lengkap *
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="email" class="form-control" id="email" name="email" placeholder="Email" required>
                                        <label for="email">
                                            <i class="fas fa-envelope"></i> Email *
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="tel" class="form-control" id="no_telepon" name="no_telepon" placeholder="No. Telepon">
                                        <label for="no_telepon">
                                            <i class="fas fa-phone"></i> No. Telepon
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="jurusan" name="jurusan" required>
                                            <option value="">Pilih Jurusan</option>
                                            <option value="Teknik Informatika">Teknik Informatika</option>
                                            <option value="Teknik Elektro">Teknik Elektro</option>
                                            <option value="Teknik Mesin">Teknik Mesin</option>
                                            <option value="Teknik Sipil">Teknik Sipil</option>
                                            <option value="Akuntansi">Akuntansi</option>
                                            <option value="Administrasi Bisnis">Administrasi Bisnis</option>
                                        </select>
                                        <label for="jurusan">
                                            <i class="fas fa-graduation-cap"></i> Jurusan *
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="angkatan" name="angkatan" required>
                                            <option value="">Pilih Angkatan</option>
                                            <?php for($year = date('Y'); $year >= 2020; $year--): ?>
                                                <option value="<?= $year ?>"><?= $year ?></option>
                                            <?php endfor; ?>
                                        </select>
                                        <label for="angkatan">
                                            <i class="fas fa-calendar"></i> Angkatan *
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="alamat" name="alamat" style="height: 100px" placeholder="Alamat"></textarea>
                                <label for="alamat">
                                    <i class="fas fa-map-marker-alt"></i> Alamat
                                </label>
                            </div>
                            
                            <div class="mb-3">
                                <label for="foto" class="form-label">
                                    <i class="fas fa-camera"></i> Foto Profil (Opsional)
                                </label>
                                <input type="file" class="form-control" id="foto" name="foto" accept="image/*">
                                <div class="form-text">Format: JPG, PNG. Maksimal 5MB.</div>
                            </div>
                            
                            <hr>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                                        <label for="password">
                                            <i class="fas fa-lock"></i> Password *
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Konfirmasi Password" required>
                                        <label for="confirm_password">
                                            <i class="fas fa-lock"></i> Konfirmasi Password *
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-register">
                                    <i class="fas fa-user-plus"></i> Daftar Sekarang
                                </button>
                            </div>
                        </form>
                        
                        <?php endif; ?>
                        
                        <div class="text-center">
                            <p class="mb-2">Sudah punya akun?</p>
                            <a href="login.php" class="btn btn-outline-primary">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </a>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="../index.php" class="text-decoration-none">
                                <i class="fas fa-arrow-left"></i> Kembali ke Beranda
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>