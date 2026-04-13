-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3308
-- Waktu pembuatan: 11 Apr 2026 pada 16.22
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
-- Database: `cleango`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `invoice`
--

CREATE TABLE `invoice` (
  `id_invoice` int(11) NOT NULL,
  `id_bayar` int(11) NOT NULL,
  `no_invoice` varchar(50) NOT NULL,
  `tgl_invoice` timestamp NOT NULL DEFAULT current_timestamp(),
  `nomor_wa` varchar(20) NOT NULL,
  `status_kirim` enum('Belum Dikirim','Terkirim','Gagal Kirim') NOT NULL DEFAULT 'Belum Dikirim',
  `waktu_kirim` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `invoice`
--

INSERT INTO `invoice` (`id_invoice`, `id_bayar`, `no_invoice`, `tgl_invoice`, `nomor_wa`, `status_kirim`, `waktu_kirim`, `created_at`) VALUES
(1, 1, 'INV-20260410-001', '2026-04-10 20:39:22', '6284444444444', 'Terkirim', '2026-04-10 07:35:00', '2026-04-10 20:39:22');

-- --------------------------------------------------------

--
-- Struktur dari tabel `katalog`
--

CREATE TABLE `katalog` (
  `id_katalog` int(11) NOT NULL,
  `id_layanan` int(11) NOT NULL,
  `jenis_layanan` varchar(100) NOT NULL,
  `varian` enum('Regular','Express','Hemat') NOT NULL DEFAULT 'Regular',
  `harga` decimal(10,2) NOT NULL,
  `satuan` enum('kg','pcs') NOT NULL DEFAULT 'kg',
  `deskripsi` text DEFAULT NULL,
  `status` enum('Aktif','Nonaktif') NOT NULL DEFAULT 'Aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `katalog`
--

INSERT INTO `katalog` (`id_katalog`, `id_layanan`, `jenis_layanan`, `varian`, `harga`, `satuan`, `deskripsi`, `status`, `created_at`) VALUES
(1, 1, 'Cuci Kering', 'Regular', 7000.00, 'kg', NULL, 'Aktif', '2026-04-10 20:39:22'),
(2, 1, 'Cuci Kering', 'Express', 12000.00, 'kg', NULL, 'Aktif', '2026-04-10 20:39:22'),
(3, 2, 'Cuci Setrika', 'Regular', 10000.00, 'kg', NULL, 'Aktif', '2026-04-10 20:39:22'),
(4, 2, 'Cuci Setrika', 'Express', 15000.00, 'kg', NULL, 'Aktif', '2026-04-10 20:39:22'),
(5, 3, 'Setrika Saja', 'Regular', 6000.00, 'kg', '', 'Aktif', '2026-04-10 20:39:22'),
(6, 3, 'Setrika Saja', 'Express', 10000.00, 'kg', NULL, 'Nonaktif', '2026-04-10 20:39:22'),
(7, 4, 'Laundry Sepatu', 'Regular', 20000.00, 'pcs', NULL, 'Aktif', '2026-04-10 20:39:22'),
(8, 4, 'Laundry Sepatu', 'Express', 30000.00, 'pcs', NULL, 'Aktif', '2026-04-10 20:39:22');

-- --------------------------------------------------------

--
-- Struktur dari tabel `layanan`
--

CREATE TABLE `layanan` (
  `id_layanan` int(11) NOT NULL,
  `nama_layanan` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `layanan`
--

INSERT INTO `layanan` (`id_layanan`, `nama_layanan`, `deskripsi`, `is_active`, `created_at`) VALUES
(1, 'Cuci Kering', 'Layanan cuci dan pengeringan standar', 1, '2026-04-10 20:39:22'),
(2, 'Cuci Setrika', 'Layanan cuci lengkap dengan setrika', 1, '2026-04-10 20:39:22'),
(3, 'Setrika Saja', 'Khusus setrika pakaian', 1, '2026-04-10 20:39:22'),
(4, 'Laundry Sepatu', 'Pembersihan khusus sepatu dan tas', 1, '2026-04-10 20:39:22');

-- --------------------------------------------------------

--
-- Struktur dari tabel `login_logs`
--

CREATE TABLE `login_logs` (
  `id` int(11) NOT NULL,
  `role` enum('owner','staff','customer') NOT NULL,
  `actor_id` int(11) NOT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `login_logs`
--

INSERT INTO `login_logs` (`id`, `role`, `actor_id`, `login_time`, `ip_address`, `user_agent`) VALUES
(1, 'staff', 1, '2026-04-10 20:50:42', '::1', NULL),
(2, 'customer', 1, '2026-04-10 20:53:40', '::1', NULL),
(3, 'staff', 1, '2026-04-10 20:55:12', '::1', NULL),
(4, 'staff', 1, '2026-04-10 21:03:35', '::1', NULL),
(5, 'customer', 1, '2026-04-10 21:05:32', '::1', NULL),
(6, 'staff', 1, '2026-04-10 21:05:58', '::1', NULL),
(7, 'owner', 1, '2026-04-10 21:07:32', '::1', NULL),
(8, 'customer', 1, '2026-04-10 21:09:29', '::1', NULL),
(9, 'owner', 1, '2026-04-10 21:29:04', '::1', NULL),
(10, 'customer', 1, '2026-04-11 13:41:44', '::1', NULL),
(11, 'owner', 1, '2026-04-11 13:53:44', '::1', NULL),
(12, 'owner', 1, '2026-04-11 13:55:28', '::1', NULL),
(13, 'staff', 1, '2026-04-11 13:55:37', '::1', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `role` enum('customer','staff','owner') NOT NULL,
  `actor_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `notifications`
--

INSERT INTO `notifications` (`id`, `role`, `actor_id`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES
(1, 'staff', 1, '📦 Order Baru Masuk!', 'Order ORD-20260410-002 dari Dhira Cust menunggu konfirmasi. Segera proses!', 'staff.php?page=order_masuk', 0, '2026-04-10 20:54:18'),
(2, 'owner', 1, '📦 Order Baru: ORD-20260410-002', 'Customer Dhira Cust membuat order baru (ORD-20260410-002). Laundry: Cuci Kering', 'owner.php?page=semua_order', 0, '2026-04-10 20:54:18'),
(3, 'customer', 1, '🚗 Laundry Sedang Dijemput!', 'Order ORD-20260410-002 sedang dijemput oleh staff kami. Pastikan laundry sudah siap.', 'customer.php?page=tracking_saya', 0, '2026-04-10 20:55:18'),
(4, 'owner', 1, '🚗 Order Dijemput: ORD-20260410-002', 'Staff Karimah Staff menjemput laundry ORD-20260410-002 dari Dhira Cust.', 'owner.php?page=semua_order', 0, '2026-04-10 20:55:18'),
(5, 'customer', 1, '💳 Tagihan Laundry Kamu Sudah Siap!', 'Order ORD-20260410-002 — Tagihan sebesar Rp 36.000 sudah dimasukkan. Silakan bayar via QRIS.', 'customer.php?page=pembayaran', 0, '2026-04-10 21:04:52'),
(6, 'owner', 1, '📊 Tagihan Dibuat: ORD-20260410-002', 'Staff Karimah Staff memasukkan tagihan Rp 36.000 untuk Dhira Cust.', 'owner.php?page=semua_order', 0, '2026-04-10 21:04:52'),
(7, 'staff', 1, '💳 Pembayaran Masuk!', 'Customer Dhira Cust sudah upload bukti bayar untuk order ORD-20260410-002. Konfirmasi sekarang!', 'staff.php?page=konfirmasi_bayar', 0, '2026-04-10 21:05:42'),
(8, 'owner', 1, '💳 Bukti Bayar Diterima', 'Order ORD-20260410-002 — Dhira Cust mengirimkan bukti pembayaran.', 'owner.php?page=semua_order', 0, '2026-04-10 21:05:42'),
(9, 'customer', 1, '✅ Pembayaran Dikonfirmasi!', 'Pembayaran untuk order ORD-20260410-002 sudah dikonfirmasi. Laundry kamu sedang diproses!', 'customer.php?page=tracking_saya', 0, '2026-04-10 21:06:12'),
(10, 'owner', 1, '✅ Bayar Lunas: ORD-20260410-002', 'Staff Karimah Staff mengkonfirmasi pembayaran order ORD-20260410-002.', 'owner.php?page=semua_order', 0, '2026-04-10 21:06:12'),
(11, 'staff', 1, '📦 Order Baru Masuk!', 'Order ORD-20260410-003 dari Dhira Cust menunggu konfirmasi. Segera proses!', 'staff.php?page=order_masuk', 0, '2026-04-10 21:10:19'),
(12, 'owner', 1, '📦 Order Baru: ORD-20260410-003', 'Customer Dhira Cust membuat order baru (ORD-20260410-003). Laundry: Cuci Setrika', 'owner.php?page=semua_order', 0, '2026-04-10 21:10:19'),
(13, 'customer', 1, '🚗 Laundry Sedang Dijemput!', 'Order ORD-20260410-003 sedang dijemput oleh staff kami. Pastikan laundry sudah siap.', 'customer.php?page=tracking_saya', 0, '2026-04-10 21:10:37'),
(14, 'owner', 1, '🚗 Order Dijemput: ORD-20260410-003', 'Staff Karimah Staff menjemput laundry ORD-20260410-003 dari Dhira Cust.', 'owner.php?page=semua_order', 0, '2026-04-10 21:10:37'),
(15, 'customer', 1, '💳 Tagihan Laundry Kamu Sudah Siap!', 'Order ORD-20260410-003 — Tagihan sebesar Rp 75.000 sudah dimasukkan. Silakan bayar via QRIS.', 'customer.php?page=pembayaran', 0, '2026-04-10 21:10:58'),
(16, 'owner', 1, '📊 Tagihan Dibuat: ORD-20260410-003', 'Staff Karimah Staff memasukkan tagihan Rp 75.000 untuk Dhira Cust.', 'owner.php?page=semua_order', 0, '2026-04-10 21:10:58'),
(17, 'staff', 1, '📦 Order Baru Masuk!', 'Order ORD-20260410-004 dari Dhira Cust menunggu konfirmasi. Segera proses!', 'staff.php?page=order_masuk', 0, '2026-04-10 21:11:15'),
(18, 'owner', 1, '📦 Order Baru: ORD-20260410-004', 'Customer Dhira Cust membuat order baru (ORD-20260410-004). Laundry: Cuci Setrika', 'owner.php?page=semua_order', 0, '2026-04-10 21:11:15'),
(19, 'staff', 1, '💳 Pembayaran Masuk!', 'Customer Dhira Cust sudah upload bukti bayar untuk order ORD-20260410-003. Konfirmasi sekarang!', 'staff.php?page=konfirmasi_bayar', 0, '2026-04-10 21:11:39'),
(20, 'owner', 1, '💳 Bukti Bayar Diterima', 'Order ORD-20260410-003 — Dhira Cust mengirimkan bukti pembayaran.', 'owner.php?page=semua_order', 0, '2026-04-10 21:11:39'),
(21, 'customer', 1, '✅ Pembayaran Dikonfirmasi!', 'Pembayaran untuk order ORD-20260410-003 sudah dikonfirmasi. Laundry kamu sedang diproses!', 'customer.php?page=tracking_saya', 0, '2026-04-10 21:11:51'),
(22, 'owner', 1, '✅ Bayar Lunas: ORD-20260410-003', 'Staff Karimah Staff mengkonfirmasi pembayaran order ORD-20260410-003.', 'owner.php?page=semua_order', 0, '2026-04-10 21:11:51'),
(23, 'staff', 1, '📦 Order Baru Masuk!', 'Order ORD-20260411-005 dari Dhira Cust menunggu konfirmasi. Segera proses!', 'staff.php?page=order_masuk', 0, '2026-04-11 11:58:52'),
(24, 'owner', 1, '📦 Order Baru: ORD-20260411-005', 'Customer Dhira Cust membuat order baru (ORD-20260411-005). Laundry: Cuci Setrika', 'owner.php?page=semua_order', 0, '2026-04-11 11:58:52'),
(25, 'customer', 1, '🚗 Laundry Sedang Dijemput!', 'Order ORD-20260411-005 sedang dijemput oleh staff kami. Pastikan laundry sudah siap.', 'customer.php?page=tracking_saya', 0, '2026-04-11 11:59:09'),
(26, 'owner', 1, '🚗 Order Dijemput: ORD-20260411-005', 'Staff Karimah Staff menjemput laundry ORD-20260411-005 dari Dhira Cust.', 'owner.php?page=semua_order', 0, '2026-04-11 11:59:09'),
(27, 'customer', 1, '💳 Tagihan Laundry Kamu Sudah Siap!', 'Order ORD-20260411-005 — Tagihan sebesar Rp 50.000 sudah dimasukkan. Silakan bayar via QRIS.', 'customer.php?page=pembayaran', 0, '2026-04-11 11:59:23'),
(28, 'owner', 1, '📊 Tagihan Dibuat: ORD-20260411-005', 'Staff Karimah Staff memasukkan tagihan Rp 50.000 untuk Dhira Cust.', 'owner.php?page=semua_order', 0, '2026-04-11 11:59:23'),
(29, 'staff', 1, '📦 Order Baru Masuk!', 'Order ORD-20260411-006 dari Dhira Cust menunggu konfirmasi. Segera proses!', 'staff.php?page=order_masuk', 0, '2026-04-11 11:59:31'),
(30, 'owner', 1, '📦 Order Baru: ORD-20260411-006', 'Customer Dhira Cust membuat order baru (ORD-20260411-006). Laundry: Cuci Setrika', 'owner.php?page=semua_order', 0, '2026-04-11 11:59:31'),
(31, 'staff', 1, '💳 Pembayaran Masuk!', 'Customer Dhira Cust sudah upload bukti bayar untuk order ORD-20260411-005. Konfirmasi sekarang!', 'staff.php?page=konfirmasi_bayar', 0, '2026-04-11 11:59:42'),
(32, 'owner', 1, '💳 Bukti Bayar Diterima', 'Order ORD-20260411-005 — Dhira Cust mengirimkan bukti pembayaran.', 'owner.php?page=semua_order', 0, '2026-04-11 11:59:42'),
(33, 'customer', 1, '✅ Pembayaran Dikonfirmasi!', 'Pembayaran untuk order ORD-20260411-005 sudah dikonfirmasi. Laundry kamu sedang diproses!', 'customer.php?page=tracking_saya', 0, '2026-04-11 12:00:07'),
(34, 'owner', 1, '✅ Bayar Lunas: ORD-20260411-005', 'Staff Karimah Staff mengkonfirmasi pembayaran order ORD-20260411-005.', 'owner.php?page=semua_order', 0, '2026-04-11 12:00:07'),
(35, 'customer', 1, '🚗 Laundry Sedang Dijemput!', 'Order ORD-20260410-004 sedang dijemput oleh staff kami. Pastikan laundry sudah siap.', 'customer.php?page=tracking_saya', 0, '2026-04-11 12:05:02'),
(36, 'owner', 1, '🚗 Order Dijemput: ORD-20260410-004', 'Staff Karimah Staff menjemput laundry ORD-20260410-004 dari Dhira Cust.', 'owner.php?page=semua_order', 0, '2026-04-11 12:05:02'),
(37, 'customer', 1, '💳 Tagihan Laundry Kamu Sudah Siap!', 'Order ORD-20260410-004 — Tagihan sebesar Rp 15.000 sudah dimasukkan. Silakan bayar via QRIS.', 'customer.php?page=pembayaran', 0, '2026-04-11 12:05:55'),
(38, 'owner', 1, '📊 Tagihan Dibuat: ORD-20260410-004', 'Staff Karimah Staff memasukkan tagihan Rp 15.000 untuk Dhira Cust.', 'owner.php?page=semua_order', 0, '2026-04-11 12:05:55'),
(39, 'staff', 1, '💳 Pembayaran Masuk!', 'Customer Dhira Cust sudah upload bukti bayar untuk order ORD-20260410-004. Konfirmasi sekarang!', 'staff.php?page=konfirmasi_bayar', 0, '2026-04-11 12:06:22'),
(40, 'owner', 1, '💳 Bukti Bayar Diterima', 'Order ORD-20260410-004 — Dhira Cust mengirimkan bukti pembayaran.', 'owner.php?page=semua_order', 0, '2026-04-11 12:06:22'),
(41, 'customer', 1, '✅ Pembayaran Dikonfirmasi!', 'Pembayaran untuk order ORD-20260410-004 sudah dikonfirmasi. Laundry kamu sedang diproses!', 'customer.php?page=tracking_saya', 0, '2026-04-11 12:06:59'),
(42, 'owner', 1, '✅ Bayar Lunas: ORD-20260410-004', 'Staff Karimah Staff mengkonfirmasi pembayaran order ORD-20260410-004.', 'owner.php?page=semua_order', 0, '2026-04-11 12:06:59'),
(43, 'customer', 1, '✅ Update Order: Selesai', 'Order ORD-20260410-002 sudah selesai dan diterima! Terima kasih sudah menggunakan CleanGo.', 'customer.php?page=tracking_saya', 0, '2026-04-11 12:54:48'),
(44, 'owner', 1, '✅ Order ORD-20260410-002: Selesai', 'Staff Karimah Staff mengupdate status ke Selesai.', 'owner.php?page=semua_order', 0, '2026-04-11 12:54:48'),
(45, 'customer', 1, '✨ Update Order: Disetrika', 'Order ORD-20260410-003 sedang disetrika. Hampir selesai!', 'customer.php?page=tracking_saya', 0, '2026-04-11 13:03:05'),
(46, 'owner', 1, '✨ Order ORD-20260410-003: Disetrika', 'Staff Karimah Staff mengupdate status ke Disetrika.', 'owner.php?page=semua_order', 0, '2026-04-11 13:03:05'),
(47, 'customer', 1, '✅ Update Order: Selesai', 'Order ORD-20260410-003 sudah selesai dan diterima! Terima kasih sudah menggunakan CleanGo.', 'customer.php?page=tracking_saya', 0, '2026-04-11 13:03:20'),
(48, 'owner', 1, '✅ Order ORD-20260410-003: Selesai', 'Staff Karimah Staff mengupdate status ke Selesai.', 'owner.php?page=semua_order', 0, '2026-04-11 13:03:20'),
(49, 'customer', 1, '✅ Update Order: Selesai', 'Order ORD-20260410-004 sudah selesai dan diterima! Terima kasih sudah menggunakan CleanGo.', 'customer.php?page=tracking_saya', 0, '2026-04-11 13:03:35'),
(50, 'owner', 1, '✅ Order ORD-20260410-004: Selesai', 'Staff Karimah Staff mengupdate status ke Selesai.', 'owner.php?page=semua_order', 0, '2026-04-11 13:03:35'),
(51, 'customer', 1, '✅ Update Order: Selesai', 'Order ORD-20260411-005 sudah selesai dan diterima! Terima kasih sudah menggunakan CleanGo.', 'customer.php?page=tracking_saya', 0, '2026-04-11 13:03:49'),
(52, 'owner', 1, '✅ Order ORD-20260411-005: Selesai', 'Staff Karimah Staff mengupdate status ke Selesai.', 'owner.php?page=semua_order', 0, '2026-04-11 13:03:49'),
(53, 'customer', 1, '🚗 Laundry Sedang Dijemput!', 'Order ORD-20260411-006 sedang dijemput oleh staff kami. Pastikan laundry sudah siap.', 'customer.php?page=tracking_saya', 0, '2026-04-11 13:06:11'),
(54, 'owner', 1, '🚗 Order Dijemput: ORD-20260411-006', 'Staff Karimah Staff menjemput laundry ORD-20260411-006 dari Dhira Cust.', 'owner.php?page=semua_order', 0, '2026-04-11 13:06:11'),
(55, 'customer', 1, '💳 Tagihan Laundry Kamu Sudah Siap!', 'Order ORD-20260411-006 — Tagihan sebesar Rp 20.000 sudah dimasukkan. Silakan bayar via QRIS.', 'customer.php?page=pembayaran', 0, '2026-04-11 13:06:20'),
(56, 'owner', 1, '📊 Tagihan Dibuat: ORD-20260411-006', 'Staff Karimah Staff memasukkan tagihan Rp 20.000 untuk Dhira Cust.', 'owner.php?page=semua_order', 0, '2026-04-11 13:06:20'),
(57, 'staff', 1, '💳 Pembayaran Masuk!', 'Customer Dhira Cust sudah upload bukti bayar untuk order ORD-20260411-006. Konfirmasi sekarang!', 'staff.php?page=konfirmasi_bayar', 0, '2026-04-11 13:06:37'),
(58, 'owner', 1, '💳 Bukti Bayar Diterima', 'Order ORD-20260411-006 — Dhira Cust mengirimkan bukti pembayaran.', 'owner.php?page=semua_order', 0, '2026-04-11 13:06:37'),
(59, 'customer', 1, '✅ Pembayaran Dikonfirmasi!', 'Pembayaran untuk order ORD-20260411-006 sudah dikonfirmasi. Laundry kamu sedang diproses!', 'customer.php?page=tracking_saya', 0, '2026-04-11 13:06:44'),
(60, 'owner', 1, '✅ Bayar Lunas: ORD-20260411-006', 'Staff Karimah Staff mengkonfirmasi pembayaran order ORD-20260411-006.', 'owner.php?page=semua_order', 0, '2026-04-11 13:06:44'),
(61, 'customer', 1, '✅ Update Order: Selesai', 'Order ORD-20260411-006 sudah selesai dan diterima! Terima kasih sudah menggunakan CleanGo.', 'customer.php?page=tracking_saya', 0, '2026-04-11 13:12:48'),
(62, 'owner', 1, '✅ Order ORD-20260411-006: Selesai', 'Staff Karimah Staff mengupdate status ke Selesai.', 'owner.php?page=semua_order', 0, '2026-04-11 13:12:48');

-- --------------------------------------------------------

--
-- Struktur dari tabel `orders`
--

CREATE TABLE `orders` (
  `id_order` int(11) NOT NULL,
  `kode_order` varchar(20) NOT NULL,
  `id_cust` int(11) NOT NULL,
  `id_layanan` int(11) NOT NULL,
  `id_staff` int(11) DEFAULT NULL,
  `tanggal_pesan` datetime NOT NULL DEFAULT current_timestamp(),
  `estimasi_selesai` date DEFAULT NULL,
  `alamat_penjemputan` text NOT NULL,
  `jadwal_jemput` datetime DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `total_harga` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status_order` enum('Menunggu Konfirmasi','Dijemput','Dicuci','Disetrika','Dikirim','Selesai','Dibatalkan') NOT NULL DEFAULT 'Menunggu Konfirmasi',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `orders`
--

INSERT INTO `orders` (`id_order`, `kode_order`, `id_cust`, `id_layanan`, `id_staff`, `tanggal_pesan`, `estimasi_selesai`, `alamat_penjemputan`, `jadwal_jemput`, `catatan`, `total_harga`, `status_order`, `created_at`, `updated_at`) VALUES
(1, 'ORD-20260410-001', 1, 4, 1, '2026-04-11 03:39:22', NULL, 'Jl. Mawar No. 10', '2026-04-10 09:00:00', NULL, 40000.00, 'Selesai', '2026-04-10 20:39:22', '2026-04-10 20:39:22');

-- --------------------------------------------------------

--
-- Struktur dari tabel `order_detail`
--

CREATE TABLE `order_detail` (
  `id_detail` int(11) NOT NULL,
  `id_order` int(11) NOT NULL,
  `id_katalog` int(11) NOT NULL,
  `berat` decimal(8,2) DEFAULT NULL,
  `qty` int(11) DEFAULT NULL,
  `harga_satuan` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `order_detail`
--

INSERT INTO `order_detail` (`id_detail`, `id_order`, `id_katalog`, `berat`, `qty`, `harga_satuan`, `subtotal`) VALUES
(1, 1, 7, NULL, 2, 20000.00, 40000.00);

-- --------------------------------------------------------

--
-- Struktur dari tabel `owner`
--

CREATE TABLE `owner` (
  `id_owner` int(11) NOT NULL,
  `nama_owner` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `notelp_owner` varchar(20) NOT NULL,
  `sandi_owner` varchar(255) NOT NULL,
  `alamat_owner` text DEFAULT NULL,
  `redirect_file` varchar(100) NOT NULL DEFAULT 'owner.php',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `owner`
--

INSERT INTO `owner` (`id_owner`, `nama_owner`, `username`, `notelp_owner`, `sandi_owner`, `alamat_owner`, `redirect_file`, `is_active`, `created_at`) VALUES
(1, 'Asa Owner', 'owner', '081234567890', 'owner123', 'Jl. Merdeka No. 1', 'owner.php', 1, '2026-04-10 20:39:22');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pembayaran`
--

CREATE TABLE `pembayaran` (
  `id_bayar` int(11) NOT NULL,
  `id_order` int(11) NOT NULL,
  `metode` enum('QRIS') NOT NULL DEFAULT 'QRIS',
  `jumlah` decimal(10,2) NOT NULL,
  `status_bayar` enum('Pending','Menunggu Konfirmasi','Lunas','Gagal') NOT NULL DEFAULT 'Pending',
  `bukti_transfer` varchar(255) DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `waktu_bayar` timestamp NULL DEFAULT NULL,
  `dikonfirmasi_oleh` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `pembayaran`
--

INSERT INTO `pembayaran` (`id_bayar`, `id_order`, `metode`, `jumlah`, `status_bayar`, `bukti_transfer`, `catatan`, `waktu_bayar`, `dikonfirmasi_oleh`, `created_at`, `updated_at`) VALUES
(1, 1, 'QRIS', 40000.00, 'Lunas', NULL, NULL, '2026-04-10 07:30:00', 1, '2026-04-10 20:39:22', '2026-04-10 20:39:22');

-- --------------------------------------------------------

--
-- Struktur dari tabel `staff`
--

CREATE TABLE `staff` (
  `id_staff` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `notelp` varchar(20) NOT NULL,
  `sandi` varchar(255) NOT NULL,
  `alamat` text DEFAULT NULL,
  `redirect_file` varchar(100) NOT NULL DEFAULT 'staff.php',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `staff`
--

INSERT INTO `staff` (`id_staff`, `nama`, `username`, `notelp`, `sandi`, `alamat`, `redirect_file`, `is_active`, `created_at`) VALUES
(1, 'Karimah Staff', 'staff', '081111111111', 'staff123', 'Jl. Melati No. 3', 'staff.php', 1, '2026-04-10 20:39:22');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tracking`
--

CREATE TABLE `tracking` (
  `id_tracking` int(11) NOT NULL,
  `id_order` int(11) NOT NULL,
  `status` enum('Menunggu Konfirmasi','Dijemput','Dicuci','Disetrika','Dikirim','Selesai','Dibatalkan') NOT NULL,
  `keterangan` text DEFAULT NULL,
  `waktu_update` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `tracking`
--

INSERT INTO `tracking` (`id_tracking`, `id_order`, `status`, `keterangan`, `waktu_update`, `updated_by`) VALUES
(1, 1, 'Menunggu Konfirmasi', 'Order masuk dari customer', '2026-04-10 20:39:22', NULL),
(2, 1, 'Dijemput', 'Kurir menjemput sepatu', '2026-04-10 20:39:22', 1),
(3, 1, 'Dicuci', 'Proses pembersihan sepatu', '2026-04-10 20:39:22', 1),
(4, 1, 'Dikirim', 'Dikirim ke alamat customer', '2026-04-10 20:39:22', 1),
(5, 1, 'Selesai', 'Order selesai diterima', '2026-04-10 20:39:22', 1);

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id_cust` int(11) NOT NULL,
  `nama_cust` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `notelp_cust` varchar(20) NOT NULL,
  `sandi_cust` varchar(255) NOT NULL,
  `alamat_cust` text DEFAULT NULL,
  `redirect_file` varchar(100) NOT NULL DEFAULT 'customer.php',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id_cust`, `nama_cust`, `username`, `notelp_cust`, `sandi_cust`, `alamat_cust`, `redirect_file`, `is_active`, `created_at`) VALUES
(1, 'Dhira Cust', 'dhira', '084444444444', 'dhira123', 'Jl. Mawar No. 10', 'customer.php', 1, '2026-04-10 20:39:22');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `invoice`
--
ALTER TABLE `invoice`
  ADD PRIMARY KEY (`id_invoice`),
  ADD UNIQUE KEY `id_bayar` (`id_bayar`),
  ADD UNIQUE KEY `no_invoice` (`no_invoice`);

--
-- Indeks untuk tabel `katalog`
--
ALTER TABLE `katalog`
  ADD PRIMARY KEY (`id_katalog`),
  ADD KEY `id_layanan` (`id_layanan`);

--
-- Indeks untuk tabel `layanan`
--
ALTER TABLE `layanan`
  ADD PRIMARY KEY (`id_layanan`);

--
-- Indeks untuk tabel `login_logs`
--
ALTER TABLE `login_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif` (`role`,`actor_id`,`is_read`,`created_at`);

--
-- Indeks untuk tabel `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id_order`),
  ADD UNIQUE KEY `kode_order` (`kode_order`),
  ADD KEY `id_cust` (`id_cust`),
  ADD KEY `id_layanan` (`id_layanan`),
  ADD KEY `id_staff` (`id_staff`);

--
-- Indeks untuk tabel `order_detail`
--
ALTER TABLE `order_detail`
  ADD PRIMARY KEY (`id_detail`),
  ADD KEY `id_order` (`id_order`),
  ADD KEY `id_katalog` (`id_katalog`);

--
-- Indeks untuk tabel `owner`
--
ALTER TABLE `owner`
  ADD PRIMARY KEY (`id_owner`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD PRIMARY KEY (`id_bayar`),
  ADD UNIQUE KEY `id_order` (`id_order`),
  ADD KEY `dikonfirmasi_oleh` (`dikonfirmasi_oleh`);

--
-- Indeks untuk tabel `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id_staff`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `tracking`
--
ALTER TABLE `tracking`
  ADD PRIMARY KEY (`id_tracking`),
  ADD KEY `id_order` (`id_order`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id_cust`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `invoice`
--
ALTER TABLE `invoice`
  MODIFY `id_invoice` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `katalog`
--
ALTER TABLE `katalog`
  MODIFY `id_katalog` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `layanan`
--
ALTER TABLE `layanan`
  MODIFY `id_layanan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT untuk tabel `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT untuk tabel `orders`
--
ALTER TABLE `orders`
  MODIFY `id_order` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `order_detail`
--
ALTER TABLE `order_detail`
  MODIFY `id_detail` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `owner`
--
ALTER TABLE `owner`
  MODIFY `id_owner` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  MODIFY `id_bayar` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `staff`
--
ALTER TABLE `staff`
  MODIFY `id_staff` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `tracking`
--
ALTER TABLE `tracking`
  MODIFY `id_tracking` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id_cust` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `invoice`
--
ALTER TABLE `invoice`
  ADD CONSTRAINT `invoice_ibfk_1` FOREIGN KEY (`id_bayar`) REFERENCES `pembayaran` (`id_bayar`);

--
-- Ketidakleluasaan untuk tabel `katalog`
--
ALTER TABLE `katalog`
  ADD CONSTRAINT `katalog_ibfk_1` FOREIGN KEY (`id_layanan`) REFERENCES `layanan` (`id_layanan`);

--
-- Ketidakleluasaan untuk tabel `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`id_cust`) REFERENCES `users` (`id_cust`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`id_layanan`) REFERENCES `layanan` (`id_layanan`),
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`id_staff`) REFERENCES `staff` (`id_staff`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `order_detail`
--
ALTER TABLE `order_detail`
  ADD CONSTRAINT `order_detail_ibfk_1` FOREIGN KEY (`id_order`) REFERENCES `orders` (`id_order`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_detail_ibfk_2` FOREIGN KEY (`id_katalog`) REFERENCES `katalog` (`id_katalog`);

--
-- Ketidakleluasaan untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD CONSTRAINT `pembayaran_ibfk_1` FOREIGN KEY (`id_order`) REFERENCES `orders` (`id_order`),
  ADD CONSTRAINT `pembayaran_ibfk_2` FOREIGN KEY (`dikonfirmasi_oleh`) REFERENCES `staff` (`id_staff`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `tracking`
--
ALTER TABLE `tracking`
  ADD CONSTRAINT `tracking_ibfk_1` FOREIGN KEY (`id_order`) REFERENCES `orders` (`id_order`) ON DELETE CASCADE,
  ADD CONSTRAINT `tracking_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `staff` (`id_staff`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
