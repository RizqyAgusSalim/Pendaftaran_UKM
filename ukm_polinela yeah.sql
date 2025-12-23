-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 24 Des 2025 pada 00.29
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ukm_polinela`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` enum('superadmin','admin') NOT NULL DEFAULT 'admin',
  `ukm_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `admin`
--

INSERT INTO `admin` (`id`, `username`, `password`, `nama`, `email`, `created_at`, `role`, `ukm_id`) VALUES
(1, 'Bidang Seni', '$2y$10$rgPn7g6ALAEqp0q4kK0f7ekkz9jVevh/v.gLXW7n.YSOJh6KpNYvG', 'Administrator Bidang Seni', 'admin@polinela.ac.id', '2025-09-18 18:45:14', 'admin', 3),
(3, 'superadmin', '$2y$10$ZtPTZzC88PMYho3Pa9LLLeXIsyNwdiEOMrPkgF2.XWvJ4Wz.nFpJW', 'Super Admin', 'superadmin@polinela.ac.id', '2025-11-22 18:57:03', 'superadmin', NULL),
(4, 'Admin Unit Kegiatan Mahasiswa Olahraga', '$2y$10$IU6T1yhEtAESht2zEy2i/ueTN1xI1t1JJ66rf1Kcrf.WcIz79xddW', 'UKM OLAHRAGA', 'ukmolahraga_polinela@gmail.com', '2025-11-22 13:54:25', 'admin', 2),
(5, 'admin hmti', '$2y$10$3kdLQUl47K.5VVf.nycIZ.PsW61ffaQnnOn8HyGTX79VtPKvZwXrW', 'Himpunan mahasiswa Teknik Informatika', 'Hmti_polinela@gmail.com', '2025-11-22 14:39:53', 'admin', 1),
(6, 'English Club', '$2y$10$FZzskx8Z.55IARgZVE8W6ut0WOHljBNFfmQSAhfWp9qbQ/QqIp4jm', 'Admin English Club', 'Ec@gmail.com', '2025-11-22 16:36:10', 'admin', 4),
(10, 'Admin Poltapala', '$2y$10$GpENCx2dOrCuHLUlMDN8keofwZ3RmZxlS5HpE/VSH6UJSbFGzD4pa', 'Poltapala', 'poltapala_polinela@gmail.com', '2025-12-06 17:50:40', 'admin', 5),
(13, 'kopma', '$2y$10$MCrpiWAdC2xaeN3I1zqzwu1SkSX9IxTR4LKYSS4nNGcvc83UAUPn.', 'Kopma Mandiri', 'kopma@gmail.com', '2025-12-11 21:25:17', 'admin', 10),
(14, 'albana', '$2y$10$ME/YslE2teoWsHLTTAKjVe1pQYtGoWRAmeXAgc0gT/WD/LNyxJ35m', 'albana', 'albana@gmail.com', '2025-12-11 21:29:20', 'admin', 11),
(15, 'smart', '$2y$10$1edLvowCbq0cy27g6uFkvO4e52S1eDhUbxls4vGr9FO8Ne.vNJeQe', 'Smart', 'smart@gmai.com', '2025-12-15 08:53:39', 'admin', 12);

-- --------------------------------------------------------

--
-- Struktur dari tabel `berita`
--

CREATE TABLE `berita` (
  `id` int(11) NOT NULL,
  `ukm_id` int(11) DEFAULT NULL,
  `judul` varchar(200) NOT NULL,
  `konten` text DEFAULT NULL,
  `gambar` varchar(255) DEFAULT NULL,
  `penulis` varchar(100) DEFAULT NULL,
  `tanggal_publikasi` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('draft','published') DEFAULT 'draft'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `berita`
--

INSERT INTO `berita` (`id`, `ukm_id`, `judul`, `konten`, `gambar`, `penulis`, `tanggal_publikasi`, `status`) VALUES
(1, 2, 'PRESTASI UKM OLAHRAGA', 'Juara 1 ML', '69399c0403b1f.jpeg', 'UKM OLAHRAGA', '2025-12-10 16:12:52', 'published');

-- --------------------------------------------------------

--
-- Struktur dari tabel `divisi`
--

CREATE TABLE `divisi` (
  `id` int(11) NOT NULL,
  `ukm_id` int(11) NOT NULL,
  `nama_divisi` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `divisi`
--

INSERT INTO `divisi` (`id`, `ukm_id`, `nama_divisi`, `deskripsi`, `created_at`, `updated_at`) VALUES
(1, 12, 'Divisi Karya Tulis Ilmiah', 'Menulis, Membaca, dan Menelaah', '2025-12-16 14:56:01', '2025-12-16 14:56:01'),
(2, 2, 'Divisi Futsal', 'Kepala Divisi : M Nurrohman', '2025-12-16 15:18:20', '2025-12-16 16:54:58'),
(3, 2, 'Divisi Basket', 'Kepala Divisi : Clara Aneza Putri', '2025-12-16 16:54:07', '2025-12-16 16:55:26'),
(4, 2, 'Divisi Tenis Meja', 'Kepala Divisi : Rana Santayana', '2025-12-16 16:54:25', '2025-12-16 16:55:13'),
(5, 2, 'Divisi Badminton', 'Kepala Divisi : M Abdul Aziz', '2025-12-16 16:55:51', '2025-12-23 13:16:29'),
(6, 2, 'Divisi Volley', 'Kepala Divisi : Rifa Nabela Putra', '2025-12-16 16:56:36', '2025-12-16 16:56:36'),
(7, 2, 'Divisi Catur', 'Kepala Divisi : Ahmad Nur Huda', '2025-12-16 16:57:11', '2025-12-16 16:57:11'),
(8, 1, 'Divisi PSDM', 'Kepala Divisi : Atilla Akbar Tawaqal', '2025-12-20 16:53:41', '2025-12-20 16:53:41'),
(9, 1, 'Divisi Kominfo', 'Kepala Divisi : M Riswan Mufid', '2025-12-20 16:54:07', '2025-12-20 16:54:07');

-- --------------------------------------------------------

--
-- Struktur dari tabel `foto_kegiatan`
--

CREATE TABLE `foto_kegiatan` (
  `id` int(11) NOT NULL,
  `kegiatan_id` int(11) NOT NULL,
  `foto` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `foto_kegiatan`
--

INSERT INTO `foto_kegiatan` (`id`, `kegiatan_id`, `foto`, `file_name`, `keterangan`, `created_at`) VALUES
(1, 1, '693457efb2bfa.jpeg', '', '', '2025-12-06 16:21:03'),
(2, 3, '6934586e2fc54.jpeg', '', '', '2025-12-06 16:23:10'),
(4, 4, '693459a526580.png', '', 'Sumpah Jabatan', '2025-12-06 16:28:21'),
(5, 4, '69373cef4e63b.jpg', '', 'FUN Futsal HMJ TI', '2025-12-08 21:02:39'),
(7, 3, '694a960d416bb.jpg', '', '', '2025-12-23 13:15:57');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kategori_ukm`
--

CREATE TABLE `kategori_ukm` (
  `id` int(11) NOT NULL,
  `nama_kategori` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kategori_ukm`
--

INSERT INTO `kategori_ukm` (`id`, `nama_kategori`, `deskripsi`, `created_at`) VALUES
(1, 'Olahraga', 'Unit Kegiatan Mahasiswa bidang olahraga', '2025-09-18 18:45:15'),
(2, 'Seni dan Budaya', 'Unit Kegiatan Mahasiswa bidang seni dan budaya', '2025-09-18 18:45:15'),
(3, 'Keagamaan', 'Unit Kegiatan Mahasiswa bidang keagamaan', '2025-09-18 18:45:15'),
(4, 'Keilmuan', 'Unit Kegiatan Mahasiswa bidang keilmuan', '2025-09-18 18:45:15'),
(5, 'Minat dan Bakat', 'Unit Kegiatan Mahasiswa pengembangan minat dan bakat', '2025-09-18 18:45:15');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kegiatan_ukm`
--

CREATE TABLE `kegiatan_ukm` (
  `id` int(11) NOT NULL,
  `ukm_id` int(11) NOT NULL,
  `nama_kegiatan` varchar(255) NOT NULL,
  `deskripsi_kegiatan` text DEFAULT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date DEFAULT NULL,
  `lokasi` varchar(255) DEFAULT NULL,
  `biaya` decimal(10,2) DEFAULT 0.00,
  `status` enum('draft','published','completed') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kegiatan_ukm`
--

INSERT INTO `kegiatan_ukm` (`id`, `ukm_id`, `nama_kegiatan`, `deskripsi_kegiatan`, `tanggal_mulai`, `tanggal_selesai`, `lokasi`, `biaya`, `status`, `created_at`) VALUES
(1, 2, 'KIO 1', 'Sumpah Jabatan\r\nPelantikan Presidium Inti', '2025-12-01', '2025-12-01', 'Gedung Qb Politeknik Negeri Lampung', 2.00, 'published', '2025-12-06 14:24:18'),
(3, 2, 'Ramadhan E-Sport', 'Menjalin Silaturahmi berbasis Esport', '2025-03-19', '2025-03-22', 'Gedung GSG Politeknik Negeri Lampung', 4.50, 'published', '2025-12-06 16:22:51'),
(4, 1, 'Sumpah Jabatan', 'Pelantikan Presidium Inti HMTI', '2025-02-14', '2025-02-15', 'Gedung Sakura Politeknik Negeri Lampung', 0.00, 'published', '2025-12-06 16:28:03');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mahasiswa`
--

CREATE TABLE `mahasiswa` (
  `id` int(11) NOT NULL,
  `nim` varchar(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `no_telepon` varchar(20) DEFAULT NULL,
  `jurusan` varchar(100) DEFAULT NULL,
  `angkatan` year(4) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status_akun` enum('aktif','nonaktif') DEFAULT 'aktif',
  `ukm_id` int(11) DEFAULT NULL,
  `status_ukm` enum('menunggu','diterima','ditolak') DEFAULT 'menunggu'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `mahasiswa`
--

INSERT INTO `mahasiswa` (`id`, `nim`, `nama`, `email`, `no_telepon`, `jurusan`, `angkatan`, `alamat`, `foto`, `password`, `created_at`, `status_akun`, `ukm_id`, `status_ukm`) VALUES
(3, '3245211123', 'rojali', 'hamidtompell@gmail.com', '0234925942', 'Administrasi Bisnis', '2023', 'way kiri', '69047d8a30e7c.png', '$2y$10$Lhs3IZ3b4neV4Sk..i8DBOFOzvIX58lA9KvZHTIs5rZkQLU57VAhS', '2025-10-31 09:12:42', 'nonaktif', NULL, 'ditolak'),
(4, '24781053', 'Fahmi', 'fahmi12@gmail.com', '0856395203482', 'Teknik Informatika', '2024', 'Kotabumi, Lampung Utara', '6920905d68de6.png', '$2y$10$TqV9fOVzTyBStM79QNVr8O8gcVTi/FSPYKWPLAtXxYTA1f1JBew0S', '2025-11-21 16:16:29', 'aktif', NULL, 'menunggu'),
(5, '23753035', 'Rizqy Agus Salim', 'rizqysalim77@gmail.com', '0856395203482', 'Akuntansi Perpajakan', '2023', 'Kotabumi, Lampung Utara, Lampung - Sumatra  - Indonesia', '6925b3db8dd5d.jpg', '$2y$10$HYU7PTMY/ZCVhreA4RKeUumbAlTrtMU./.7CluuwiecF1G5wyFLia', '2025-11-25 13:49:15', 'aktif', NULL, 'menunggu'),
(6, '23734014', 'Rohman', 'rohman@gmail.com', '08235735829', 'Teknik Sipil', '2023', 'Metro', '6925da028dbdd.png', '$2y$10$IIJ6IpGsjIhaws0elkaYlec15.yLqvGc.kbnzpF.m9UCt43iMrSba', '2025-11-25 16:32:02', 'aktif', NULL, 'menunggu'),
(7, '23753039', 'agoy', 'agoy@gmail.com', '024323458238', 'Teknik Elektro', '2023', 'Banten', '6925de3da31d7.jpeg', '$2y$10$2/lLv4lHF15lxAxZg/EZcuCR9U4ne9jGsXSHeinBs3BXMZgIlyMty', '2025-11-25 16:50:05', 'aktif', NULL, 'menunggu'),
(8, '23753019', 'Herwan', 'herwan@gmail.com', '085609134706', 'Teknik Informatika', '2023', 'liwa', '692db983bf1b6.jpeg', '$2y$10$qLrd4ktx7NDQq1/WZPltjeiRqfizrjgH6z6E0wk6E/8toQmkrb28W', '2025-12-01 15:51:31', 'aktif', NULL, 'menunggu'),
(9, '22755063', 'mega', 'mega@gmail.com', '083259729492', 'Akuntansi', '2022', 'tanggamus', '6931991c63794.jpeg', '$2y$10$AorMXCYtN7rYBFglhdL1yeojdkDHYRg5dQazgesrXo4fFPTBP3ohy', '2025-12-04 14:22:20', 'aktif', NULL, 'menunggu'),
(10, '23722112', 'Ahmad Nur Huda', 'huda@gmail.com', '010342384823', 'Administrasi Bisnis', '2023', 'way Kanan', '69345ba04b886.jpeg', '$2y$10$YmX/A2FKejnscgNB2pKysO3dUGGYxT79n/yU3Cm6lLJKgKhnQ/a0q', '2025-12-06 16:36:48', 'aktif', NULL, 'menunggu'),
(11, '23753006', 'Ari Juliantot', 'ari@gmail.com', '239825480230394', 'Teknik Mesin', '2023', 'Palembang', '6939920f835e3.jpeg', '$2y$10$iCX65SZnAURUzmi0S.jdauKZBTIbobFd9FTnuWM/uXTd4zUM3KXvy', '2025-12-10 15:30:23', 'aktif', NULL, 'menunggu'),
(12, '457293492102930', 'yeyeyey', 'ajdakFNk@gmail.com', '2345354623466', 'Teknik Sipil', '2025', 'Kotabumi, Lampung Utara', '694b23a4ca618.jpg', '$2y$10$3dPdMVH3dXnE9asf6619fe044lk4gk0bRJmnY60UB3.hOUh2EXbmW', '2025-12-23 23:20:04', 'aktif', NULL, 'menunggu');

-- --------------------------------------------------------

--
-- Struktur dari tabel `notifikasi`
--

CREATE TABLE `notifikasi` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `pesan` text NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `dibaca` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `notifikasi`
--

INSERT INTO `notifikasi` (`id`, `user_id`, `pesan`, `url`, `dibaca`, `created_at`) VALUES
(1, 4, 'Mahasiswa baru mendaftar ke UKM Anda: Unit Kegiatan Mahasiswa Olahraga. Silakan periksa pendaftaran.', 'kelola_pendaftaran.php', 1, '2025-12-23 23:23:50');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pendaftaran`
--

CREATE TABLE `pendaftaran` (
  `id` int(11) NOT NULL,
  `mahasiswa_id` int(11) DEFAULT NULL,
  `ukm_id` int(11) DEFAULT NULL,
  `divisi_id` int(11) DEFAULT NULL,
  `tanggal_daftar` timestamp NOT NULL DEFAULT current_timestamp(),
  `alasan_bergabung` text DEFAULT NULL,
  `pengalaman_organisasi` text DEFAULT NULL,
  `status` enum('pending','diterima','ditolak') DEFAULT 'pending',
  `diproses_oleh` int(11) DEFAULT NULL,
  `tanggal_diproses` datetime DEFAULT NULL,
  `catatan_admin` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status_keanggotaan` enum('aktif','tidak_aktif','cuti','dikeluarkan') NOT NULL DEFAULT 'aktif',
  `kta_number` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pendaftaran`
--

INSERT INTO `pendaftaran` (`id`, `mahasiswa_id`, `ukm_id`, `divisi_id`, `tanggal_daftar`, `alasan_bergabung`, `pengalaman_organisasi`, `status`, `diproses_oleh`, `tanggal_diproses`, `catatan_admin`, `created_at`, `status_keanggotaan`, `kta_number`) VALUES
(1, 3, 1, 9, '2025-10-31 09:14:55', 'gabut', 'gada', 'diterima', NULL, NULL, 'Diterima oleh Admin melalui aksi cepat.', '2025-10-31 09:14:55', 'aktif', NULL),
(2, 4, 2, 6, '2025-11-21 16:17:10', 'gabut', '-', 'diterima', NULL, NULL, 'Diterima oleh Admin melalui aksi cepat.', '2025-11-21 16:17:10', 'aktif', NULL),
(3, 5, 2, 3, '2025-11-25 13:50:16', 'Gabut', 'Osis', 'diterima', NULL, NULL, 'Semangat gess', '2025-11-25 13:50:16', 'aktif', NULL),
(4, 5, 1, 8, '2025-11-25 15:01:56', 'gabut', 'gada', 'diterima', NULL, NULL, 'Diterima oleh Admin melalui aksi cepat.', '2025-11-25 15:01:56', 'aktif', NULL),
(5, 6, 4, NULL, '2025-11-25 16:33:41', 'pengen bahasa inggris', '-', 'diterima', NULL, NULL, 'Diterima oleh Admin melalui aksi cepat.', '2025-11-25 16:33:41', 'aktif', NULL),
(6, 7, 1, 9, '2025-11-25 16:50:39', 'heheh', 'heheh', 'diterima', NULL, NULL, 'Diterima oleh Admin melalui aksi cepat.', '2025-11-25 16:50:39', 'cuti', NULL),
(7, 8, 3, NULL, '2025-12-01 15:52:55', 'gabut heheh', 'nari wayang', 'diterima', NULL, NULL, 'Diterima oleh Admin melalui aksi cepat.', '2025-12-01 15:52:55', 'aktif', NULL),
(8, 9, 3, NULL, '2025-12-04 14:24:57', 'yahahaha', 'adaksfkandfkq', 'diterima', NULL, NULL, 'Diterima oleh Admin melalui aksi cepat.', '2025-12-04 14:24:57', 'aktif', NULL),
(9, 9, 1, 8, '2025-12-04 14:25:25', 'nknlkl kl', 'ihafinasakfn', 'diterima', 5, '2025-12-05 03:26:42', NULL, '2025-12-04 14:25:25', 'aktif', NULL),
(10, 9, 2, 5, '2025-12-04 23:00:27', 'hehe', 'hehehe', 'diterima', 4, '2025-12-05 06:01:12', NULL, '2025-12-04 23:00:27', 'aktif', NULL),
(11, 10, 2, 4, '2025-12-06 16:37:15', 'aSKDNpoNQEF`', 'UGDEFIBWQJNEFLKW', 'diterima', 4, '2025-12-07 01:02:22', NULL, '2025-12-06 16:37:15', 'aktif', NULL),
(12, 10, 1, NULL, '2025-12-06 16:37:53', 'DAISFIQNWKFM', 'KDNSAKF ALKSD', 'ditolak', 5, '2025-12-06 23:38:34', 'Goblog', '2025-12-06 16:37:53', 'aktif', NULL),
(13, 11, 5, NULL, '2025-12-10 15:30:57', 'jhafkjakj', 'klfjkdsn,mfnmsd', 'diterima', 10, '2025-12-10 22:48:45', NULL, '2025-12-10 15:30:57', 'aktif', NULL),
(14, 12, 2, 3, '2025-12-23 23:23:50', 'Pingin Main Bsket biar Jago', 'Juara 1 Basket Nasiona', 'diterima', 4, '2025-12-24 06:28:05', NULL, '2025-12-23 23:23:50', 'aktif', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengurus_ukm`
--

CREATE TABLE `pengurus_ukm` (
  `id` int(11) NOT NULL,
  `ukm_id` int(11) DEFAULT NULL,
  `mahasiswa_id` int(11) DEFAULT NULL,
  `jabatan` varchar(50) DEFAULT NULL,
  `tahun_periode` year(4) DEFAULT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `ukm`
--

CREATE TABLE `ukm` (
  `id` int(11) NOT NULL,
  `nama_ukm` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `tahun_berdiri` date DEFAULT NULL,
  `kategori_id` int(11) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `ketua_umum` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `no_telepon` varchar(20) DEFAULT NULL,
  `alamat_sekretariat` text DEFAULT NULL,
  `visi` text DEFAULT NULL,
  `misi` text DEFAULT NULL,
  `program_kerja` text DEFAULT NULL,
  `syarat_pendaftaran` text DEFAULT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `max_anggota` int(11) DEFAULT 100,
  `biaya_pendaftaran` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  `kontak` varchar(50) DEFAULT NULL,
  `admin_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `ukm`
--

INSERT INTO `ukm` (`id`, `nama_ukm`, `deskripsi`, `tahun_berdiri`, `kategori_id`, `logo`, `ketua_umum`, `email`, `no_telepon`, `alamat_sekretariat`, `visi`, `misi`, `program_kerja`, `syarat_pendaftaran`, `status`, `max_anggota`, `biaya_pendaftaran`, `created_at`, `updated_at`, `kontak`, `admin_id`) VALUES
(1, 'Himpunan Mahasiswa Teknik Informatika', 'Organisasi mahasiswa jurusan Teknik Informatika', '2023-11-19', 4, '6920c8985a5cb.png', 'Aditya Dwi Putra', 'hmti@polinela.ac.id', '0721-123456', 'Politeknik Negeri Lampung', 'Menjadi organisasi mahasiswa yang unggul dalam bidang teknologi informasi', 'Mengembangkan potensi mahasiswa dalam bidang IT', '1. Sumpah Jabatan\r\n2. Innovation 4 Force\r\n3. Upgrading HMJ TI', 'Muslim kaffah\r\nMahasiswa Aktif Jurusan Teknologi Informasi\r\nBersedia Mengikuti Seleksi Wawancara dan Tertulis \r\nMahasiswa Aktif Semester 1 dan 3\r\nBersedia Menaati Peraturan yang ada di HMJ TI', 'aktif', 100, 0.00, '2025-09-18 18:45:15', '2025-12-09 03:42:14', 'Instagram : @HMJTI', 5),
(2, 'Unit Kegiatan Mahasiswa Olahraga', 'Unit kegiatan mahasiswa untuk olahraga', '2012-07-07', 5, '6920c8a4442b3.png', 'Rizqy Agus Salim', 'ukmolahraga_polinela@polinela.ac.id', '0721-789012', 'Politeknik Negeri Lampung', 'Menjadi UKM olahraga terbaik di Lampung', 'Mengembangkan bakat olahraga mahasiswa', 'banyak', 'Muslim \r\nMahasiswa/i Politeknik Negeri Lampung', 'aktif', 100, 0.00, '2025-09-18 18:45:15', '2025-12-23 20:13:09', 'Instagram : @ukmolahraga_polinela', 4),
(3, 'Unit Kegiata Mahasiswa Bidang Seni', 'Unit kegiatan mahasiswa seni', NULL, 2, '6920c8ad47264.png', 'Djob Albert', 'Bidang_Seni@polinela.ac.id', '0721-345678', NULL, 'Melestarikan seni musik tradisional dan modern', 'Mengembangkan bakat seni musik mahasiswa', NULL, NULL, 'aktif', 100, 0.00, '2025-09-18 18:45:15', NULL, NULL, 1),
(4, 'UKM English Club', 'bhs inggris', NULL, 4, '69223b26907df.png', 'Ahmad Fauzi', 'Ecpolinela@gmail.com', '0721-123456', NULL, NULL, NULL, NULL, NULL, 'aktif', 100, 0.00, '2025-11-22 22:36:10', NULL, NULL, 6),
(5, 'UKM Poltapala', 'Gunung dan Hutan', '1998-03-19', 5, '693995c01bc91.png', 'Marbun', 'poltapala_polinela@gmail.com', '1232948027503', 'Politeknik Negeri Lampung', 'apa aja', 'ye', 'hehe', 'muslim', 'aktif', 100, 0.00, '2025-12-06 23:50:40', '2025-12-10 22:46:08', 'Instagram : @Poltapala123', 10),
(10, 'UKM KOPMA', 'asdhjhsjafbkdfso', NULL, 5, NULL, 'M Ilhamsyah', 'Kopmamandiri@gmail.com', '20759874095280', NULL, NULL, NULL, NULL, NULL, 'aktif', 100, 0.00, '2025-12-12 03:25:17', NULL, NULL, 13),
(11, 'UKM Al Banna', 'ahdfdjkawkgaworglagk', NULL, 3, NULL, 'Krisna Abrori', 'albanna@gmail.com', '29389857924705', NULL, NULL, NULL, NULL, NULL, 'aktif', 100, 0.00, '2025-12-12 03:29:20', NULL, NULL, 14),
(12, 'UKM Smart', 'UKM Studi Mahasiswa Riset Terapan Politeknik Negeri Lampung', '2007-03-19', 4, '694023b443b2c.png', 'Anton Wibowo', 'Smart@gmail.com', '08456724032840', 'Samping Gedung Arsip \r\nPoliteknik Negeri Lampung', 'makan', 'makan', '1. hehe\r\n2. hehe\r\n3. heeeeee', 'Muslim, islam, taslim', 'aktif', 100, 0.00, '2025-12-15 14:53:39', '2025-12-15 22:05:24', '084375234928350', 15);

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `berita`
--
ALTER TABLE `berita`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ukm_id` (`ukm_id`);

--
-- Indeks untuk tabel `divisi`
--
ALTER TABLE `divisi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ukm_id` (`ukm_id`);

--
-- Indeks untuk tabel `foto_kegiatan`
--
ALTER TABLE `foto_kegiatan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kegiatan_id` (`kegiatan_id`);

--
-- Indeks untuk tabel `kategori_ukm`
--
ALTER TABLE `kategori_ukm`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `kegiatan_ukm`
--
ALTER TABLE `kegiatan_ukm`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ukm_id` (`ukm_id`);

--
-- Indeks untuk tabel `mahasiswa`
--
ALTER TABLE `mahasiswa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nim` (`nim`),
  ADD KEY `fk_mahasiswa_ukm` (`ukm_id`);

--
-- Indeks untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `pendaftaran`
--
ALTER TABLE `pendaftaran`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mahasiswa_id` (`mahasiswa_id`),
  ADD KEY `ukm_id` (`ukm_id`),
  ADD KEY `fk_pendaftaran_divisi` (`divisi_id`);

--
-- Indeks untuk tabel `pengurus_ukm`
--
ALTER TABLE `pengurus_ukm`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ukm_id` (`ukm_id`),
  ADD KEY `mahasiswa_id` (`mahasiswa_id`);

--
-- Indeks untuk tabel `ukm`
--
ALTER TABLE `ukm`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kategori_id` (`kategori_id`),
  ADD KEY `fk_admin_ukm` (`admin_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT untuk tabel `berita`
--
ALTER TABLE `berita`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `divisi`
--
ALTER TABLE `divisi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `foto_kegiatan`
--
ALTER TABLE `foto_kegiatan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `kategori_ukm`
--
ALTER TABLE `kategori_ukm`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `kegiatan_ukm`
--
ALTER TABLE `kegiatan_ukm`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `mahasiswa`
--
ALTER TABLE `mahasiswa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `pendaftaran`
--
ALTER TABLE `pendaftaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT untuk tabel `pengurus_ukm`
--
ALTER TABLE `pengurus_ukm`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `ukm`
--
ALTER TABLE `ukm`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `berita`
--
ALTER TABLE `berita`
  ADD CONSTRAINT `berita_ibfk_1` FOREIGN KEY (`ukm_id`) REFERENCES `ukm` (`id`);

--
-- Ketidakleluasaan untuk tabel `divisi`
--
ALTER TABLE `divisi`
  ADD CONSTRAINT `divisi_ibfk_1` FOREIGN KEY (`ukm_id`) REFERENCES `ukm` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `foto_kegiatan`
--
ALTER TABLE `foto_kegiatan`
  ADD CONSTRAINT `foto_kegiatan_ibfk_1` FOREIGN KEY (`kegiatan_id`) REFERENCES `kegiatan_ukm` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `kegiatan_ukm`
--
ALTER TABLE `kegiatan_ukm`
  ADD CONSTRAINT `kegiatan_ukm_ibfk_1` FOREIGN KEY (`ukm_id`) REFERENCES `ukm` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mahasiswa`
--
ALTER TABLE `mahasiswa`
  ADD CONSTRAINT `fk_mahasiswa_ukm` FOREIGN KEY (`ukm_id`) REFERENCES `ukm` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD CONSTRAINT `notifikasi_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `mahasiswa` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pendaftaran`
--
ALTER TABLE `pendaftaran`
  ADD CONSTRAINT `fk_pendaftaran_divisi` FOREIGN KEY (`divisi_id`) REFERENCES `divisi` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pendaftaran_mahasiswa` FOREIGN KEY (`mahasiswa_id`) REFERENCES `mahasiswa` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pendaftaran_ukm` FOREIGN KEY (`ukm_id`) REFERENCES `ukm` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `pendaftaran_ibfk_1` FOREIGN KEY (`mahasiswa_id`) REFERENCES `mahasiswa` (`id`),
  ADD CONSTRAINT `pendaftaran_ibfk_2` FOREIGN KEY (`ukm_id`) REFERENCES `ukm` (`id`),
  ADD CONSTRAINT `pendaftaran_ibfk_3` FOREIGN KEY (`divisi_id`) REFERENCES `divisi` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `pengurus_ukm`
--
ALTER TABLE `pengurus_ukm`
  ADD CONSTRAINT `pengurus_ukm_ibfk_1` FOREIGN KEY (`ukm_id`) REFERENCES `ukm` (`id`),
  ADD CONSTRAINT `pengurus_ukm_ibfk_2` FOREIGN KEY (`mahasiswa_id`) REFERENCES `mahasiswa` (`id`);

--
-- Ketidakleluasaan untuk tabel `ukm`
--
ALTER TABLE `ukm`
  ADD CONSTRAINT `fk_admin_ukm` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ukm_ibfk_1` FOREIGN KEY (`kategori_id`) REFERENCES `kategori_ukm` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
