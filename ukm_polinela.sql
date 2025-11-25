-- Database: ukm_polinela
CREATE DATABASE ukm_polinela;
USE ukm_polinela;

-- Tabel Admin
CREATE TABLE admin (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Kategori UKM
CREATE TABLE kategori_ukm (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_kategori VARCHAR(100) NOT NULL,
    deskripsi TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel UKM/Organisasi
CREATE TABLE ukm (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_ukm VARCHAR(100) NOT NULL,
    deskripsi TEXT,
    kategori_id INT,
    logo VARCHAR(255),
    ketua_umum VARCHAR(100),
    email VARCHAR(100),
    no_telepon VARCHAR(20),
    alamat_sekretariat TEXT,
    visi TEXT,
    misi TEXT,
    program_kerja TEXT,
    syarat_pendaftaran TEXT,
    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    max_anggota INT DEFAULT 100,
    biaya_pendaftaran DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kategori_id) REFERENCES kategori_ukm(id)
);

-- Tabel Mahasiswa
CREATE TABLE mahasiswa (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nim VARCHAR(20) UNIQUE NOT NULL,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    no_telepon VARCHAR(20),
    jurusan VARCHAR(100),
    angkatan YEAR,
    alamat TEXT,
    foto VARCHAR(255),
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Pendaftaran
CREATE TABLE pendaftaran (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mahasiswa_id INT,
    ukm_id INT,
    tanggal_daftar TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    alasan_bergabung TEXT,
    pengalaman_organisasi TEXT,
    status ENUM('pending', 'diterima', 'ditolak') DEFAULT 'pending',
    catatan_admin TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mahasiswa_id) REFERENCES mahasiswa(id),
    FOREIGN KEY (ukm_id) REFERENCES ukm(id)
);

-- Tabel Pengurus UKM (untuk akses edit UKM)
CREATE TABLE pengurus_ukm (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ukm_id INT,
    mahasiswa_id INT,
    jabatan VARCHAR(50),
    tahun_periode YEAR,
    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ukm_id) REFERENCES ukm(id),
    FOREIGN KEY (mahasiswa_id) REFERENCES mahasiswa(id)
);

-- Tabel Berita/Pengumuman
CREATE TABLE berita (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ukm_id INT,
    judul VARCHAR(200) NOT NULL,
    konten TEXT,
    gambar VARCHAR(255),
    penulis VARCHAR(100),
    tanggal_publikasi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('draft', 'published') DEFAULT 'draft',
    FOREIGN KEY (ukm_id) REFERENCES ukm(id)
);

-- Insert data awal
INSERT INTO admin (username, password, nama, email) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@polinela.ac.id');

INSERT INTO kategori_ukm (nama_kategori, deskripsi) VALUES 
('Olahraga', 'Unit Kegiatan Mahasiswa bidang olahraga'),
('Seni dan Budaya', 'Unit Kegiatan Mahasiswa bidang seni dan budaya'),
('Keagamaan', 'Unit Kegiatan Mahasiswa bidang keagamaan'),
('Keilmuan', 'Unit Kegiatan Mahasiswa bidang keilmuan'),
('Minat dan Bakat', 'Unit Kegiatan Mahasiswa pengembangan minat dan bakat');

INSERT INTO ukm (nama_ukm, deskripsi, kategori_id, ketua_umum, email, no_telepon, visi, misi) VALUES 
('Himpunan Mahasiswa Teknik Informatika', 'Organisasi mahasiswa jurusan Teknik Informatika', 4, 'Ahmad Fauzi', 'hmti@polinela.ac.id', '0721-123456', 'Menjadi organisasi mahasiswa yang unggul dalam bidang teknologi informasi', 'Mengembangkan potensi mahasiswa dalam bidang IT'),
('Unit Kegiatan Mahasiswa Futsal', 'Unit kegiatan mahasiswa untuk olahraga futsal', 1, 'Budi Santoso', 'futsal@polinela.ac.id', '0721-789012', 'Menjadi UKM olahraga terbaik di Lampung', 'Mengembangkan bakat olahraga mahasiswa'),
('Paduan Suara Mahasiswa', 'Unit kegiatan mahasiswa seni musik vokal', 2, 'Sari Dewi', 'psm@polinela.ac.id', '0721-345678', 'Melestarikan seni musik tradisional dan modern', 'Mengembangkan bakat seni musik mahasiswa');