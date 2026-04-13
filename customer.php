<?php
require_once 'app_config.php';
cleango_boot_session();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php"); exit();
}

$id_cust      = (int)$_SESSION['user_id'];
$customerName = $_SESSION['nama'];
$page         = $_GET['page'] ?? 'dashboard';
$flash        = '';
$flashType    = 'success';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ft = $_POST['form_type'] ?? '';

    // --- Buat Booking Baru ---
    if ($ft === 'booking_submit') {
        $id_layanan = (int)($_POST['id_layanan'] ?? 0);
        $id_katalog = (int)($_POST['id_katalog'] ?? 0);
        $alamat     = trim($_POST['alamat'] ?? '');
        $tanggal    = $_POST['tanggal_jemput'] ?? '';
        $sesi       = $_POST['sesi_jemput'] ?? '';
        $catatan    = trim($_POST['catatan'] ?? '');

        // Validasi tanggal dan sesi tidak boleh sudah lewat
        $proceed_booking = false;
        if ($id_layanan && $id_katalog && $alamat && $tanggal && $sesi) {
            $now = new DateTime();
            $tanggal_pilih = DateTime::createFromFormat('Y-m-d', $tanggal);
            
            // Cek tanggal sudah lewat
            if ($tanggal_pilih && $tanggal_pilih < $now) {
                $flash = "❌ Tanggal yang dipilih sudah lewat. Silakan pilih tanggal lainnya.";
                $flashType = 'error';
            }
            // Cek sesi sudah lewat (hanya jika tanggal adalah hari ini)
            elseif ($tanggal_pilih && $tanggal_pilih->format('Y-m-d') === $now->format('Y-m-d')) {
                $waktu_awal = substr($sesi, 0, 5); // HH:MM
                $waktu_sekarang = $now->format('H:i');
                if ($waktu_awal <= $waktu_sekarang) {
                    $flash = "❌ Sesi jemput yang dipilih sudah lewat. Silakan pilih sesi lainnya.";
                    $flashType = 'error';
                } else {
                    $proceed_booking = true;
                }
            } else {
                $proceed_booking = true;
            }
        }

        if ($proceed_booking && $id_layanan && $id_katalog && $alamat && $tanggal && $sesi) {
            // Gabungkan tanggal dan waktu awal sesi
            $waktu_awal = substr($sesi, 0, 5); // Ambil HH:MM dari 'HH:MM-HH:MM'
            $jadwal = $tanggal . ' ' . $waktu_awal . ':00';

            $kode = generateKodeOrder($pdo);
            $pdo->prepare("
                INSERT INTO orders (kode_order,id_cust,id_layanan,alamat_penjemputan,jadwal_jemput,catatan,status_order)
                VALUES (?,?,?,?,?,?,'Menunggu Konfirmasi')
            ")->execute([$kode, $id_cust, $id_layanan, $alamat, $jadwal, $catatan]);
            $id_order = (int)$pdo->lastInsertId();

            $kat = $pdo->prepare("SELECT * FROM katalog WHERE id_katalog=?");
            $kat->execute([$id_katalog]);
            $katalogRow = $kat->fetch();
            if ($katalogRow) {
                $pdo->prepare("INSERT INTO order_detail (id_order,id_katalog,berat,qty,harga_satuan,subtotal) VALUES (?,?,NULL,NULL,?,0)")
                    ->execute([$id_order, $id_katalog, (float)$katalogRow['harga']]);
            }
            $pdo->prepare("INSERT INTO tracking (id_order,status,keterangan) VALUES (?,'Menunggu Konfirmasi','Order masuk dari customer')")
                ->execute([$id_order]);

            // NOTIFIKASI ke Staff & Owner
            $custName = htmlspecialchars($customerName);
            notifyAllStaff($pdo,
                '📦 Order Baru Masuk!',
                "Order {$kode} dari {$custName} menunggu konfirmasi. Segera proses!",
                "staff.php?page=order_masuk"
            );
            notifyAllOwner($pdo,
                '📦 Order Baru: ' . $kode,
                "Customer {$custName} membuat order baru ({$kode}). Laundry: " . ($katalogRow['jenis_layanan'] ?? '-'),
                "owner.php?page=semua_order"
            );

            $flash = "Booking <strong>{$kode}</strong> berhasil dibuat! Staff akan segera menghubungi kamu.";
            $page  = 'riwayat';
        } else {
            $flash = "Lengkapi semua field yang wajib diisi.";
            $flashType = 'error';
        }
    }

    // --- Upload Bukti Pembayaran ---
    if ($ft === 'upload_bukti') {
        $id_order = (int)($_POST['id_order'] ?? 0);
        $catatan  = trim($_POST['catatan_bayar'] ?? '');

        $stmt = $pdo->prepare("UPDATE pembayaran SET status_bayar='Menunggu Konfirmasi', catatan=?, waktu_bayar=NOW() WHERE id_order=? AND status_bayar='Pending'");
        $stmt->execute([$catatan, $id_order]);

        if ($stmt->rowCount() > 0) {
            // Ambil info order untuk notifikasi
            $oi = $pdo->prepare("SELECT o.kode_order, u.nama_cust FROM orders o JOIN users u ON u.id_cust=o.id_cust WHERE o.id_order=?");
            $oi->execute([$id_order]);
            $oi = $oi->fetch();

            notifyAllStaff($pdo,
                '💳 Pembayaran Masuk!',
                "Customer {$oi['nama_cust']} sudah upload bukti bayar untuk order {$oi['kode_order']}. Konfirmasi sekarang!",
                "staff.php?page=konfirmasi_bayar"
            );
            notifyAllOwner($pdo,
                '💳 Bukti Bayar Diterima',
                "Order {$oi['kode_order']} — {$oi['nama_cust']} mengirimkan bukti pembayaran.",
                "owner.php?page=semua_order"
            );

            $flash = "Bukti pembayaran berhasil dikirim. Tunggu konfirmasi staff.";
            header("Location: ?page=pembayaran");
            exit;
        } else {
            $flash = "Gagal mengirim bukti. Coba lagi.";
            $flashType = 'error';
        }
        $page = 'pembayaran';
    }

    // --- Update Profil ---
    if ($ft === 'update_profil') {
        $nama   = trim($_POST['nama_cust'] ?? '');
        $notelp = trim($_POST['notelp_cust'] ?? '');
        $alamat = trim($_POST['alamat_cust'] ?? '');
        if ($nama) {
            $pdo->prepare("UPDATE users SET nama_cust=?, notelp_cust=?, alamat_cust=? WHERE id_cust=?")
                ->execute([$nama, $notelp, $alamat, $id_cust]);
            $_SESSION['nama'] = $nama;
            $customerName = $nama;
            $flash = "Profil berhasil diperbarui.";
        }
        $page = 'profil';
    }
}

// ============================================================
// DATA QUERIES
// ============================================================
$katalogList = $pdo->query("
    SELECT k.*, l.nama_layanan
    FROM katalog k JOIN layanan l ON k.id_layanan=l.id_layanan
    WHERE k.status='Aktif' ORDER BY l.nama_layanan, k.varian
")->fetchAll();

$layananList = $pdo->query("SELECT * FROM layanan WHERE is_active=1")->fetchAll();

$myOrders = $pdo->prepare("
    SELECT o.*, l.nama_layanan,
           k.jenis_layanan, k.varian, k.harga, k.satuan,
           od.berat, od.qty, od.harga_satuan AS harga_od, od.subtotal,
           s.nama AS nama_staff,
           p.id_bayar, p.jumlah AS jumlah_bayar, p.status_bayar, p.metode
    FROM orders o
    JOIN layanan l ON o.id_layanan=l.id_layanan
    LEFT JOIN order_detail od ON od.id_order=o.id_order
    LEFT JOIN katalog k ON k.id_katalog=od.id_katalog
    LEFT JOIN staff s ON s.id_staff=o.id_staff
    LEFT JOIN pembayaran p ON p.id_order=o.id_order
    WHERE o.id_cust=?
    ORDER BY o.tanggal_pesan DESC
");
$myOrders->execute([$id_cust]);
$myOrders = $myOrders->fetchAll();

$statSelesai = count(array_filter($myOrders, fn($o) => $o['status_order']==='Selesai'));
$statAktif   = count(array_filter($myOrders, fn($o) => !in_array($o['status_order'], ['Selesai','Dibatalkan'])));
$statTotal   = array_sum(array_column($myOrders, 'jumlah_bayar'));

$profil = $pdo->prepare("SELECT * FROM users WHERE id_cust=?");
$profil->execute([$id_cust]);
$profil = $profil->fetch();

$selectedOrderId  = (int)($_GET['id'] ?? 0);
$selectedOrder    = null;
$selectedPayment  = null;
$selectedTracking = [];
if ($selectedOrderId) {
    $sq = $pdo->prepare("
        SELECT o.*, l.nama_layanan, k.jenis_layanan, k.varian, k.satuan,
               od.berat, od.qty, od.harga_satuan AS harga_od, od.subtotal,
               s.nama AS nama_staff
        FROM orders o
        JOIN layanan l ON o.id_layanan=l.id_layanan
        LEFT JOIN order_detail od ON od.id_order=o.id_order
        LEFT JOIN katalog k ON k.id_katalog=od.id_katalog
        LEFT JOIN staff s ON s.id_staff=o.id_staff
        WHERE o.id_order=? AND o.id_cust=?
    ");
    $sq->execute([$selectedOrderId, $id_cust]);
    $selectedOrder = $sq->fetch();
    if ($selectedOrder) {
        $sp = $pdo->prepare("SELECT * FROM pembayaran WHERE id_order=?");
        $sp->execute([$selectedOrderId]);
        $selectedPayment = $sp->fetch();
        $st = $pdo->prepare("SELECT * FROM tracking WHERE id_order=? ORDER BY waktu_update ASC");
        $st->execute([$selectedOrderId]);
        $selectedTracking = $st->fetchAll();
    }
}

$invoices = $pdo->prepare("
    SELECT i.*, p.jumlah, p.metode, p.status_bayar, o.kode_order, o.id_order, o.status_order, l.nama_layanan
    FROM invoice i
    JOIN pembayaran p ON p.id_bayar=i.id_bayar
    JOIN orders o ON o.id_order=p.id_order
    JOIN layanan l ON l.id_layanan=o.id_layanan
    WHERE o.id_cust=?
    ORDER BY i.tgl_invoice DESC
");
$invoices->execute([$id_cust]);
$invoices = $invoices->fetchAll();

// Order yang butuh pembayaran
$ordersBayar = array_filter($myOrders, fn($o) => $o['status_bayar'] === 'Pending' && $o['jumlah_bayar'] > 0);

// Notifikasi
$unreadCount = countUnread($pdo, 'customer', $id_cust);

$statusSteps  = ['Menunggu Konfirmasi'=>0,'Dijemput'=>1,'Dicuci'=>2,'Disetrika'=>3,'Dikirim'=>4,'Selesai'=>5,'Dibatalkan'=>-1];
$statusLabels = ['Menunggu Konfirmasi','Dijemput','Dicuci','Disetrika','Dikirim','Selesai'];
$statusIcons  = ['⏳','🚗','🧺','✨','📦','✅'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Customer - CleanGo Laundry</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','Segoe UI','sans-serif']}}}}</script>
</head>
<body data-role="customer">
<!-- SIDEBAR -->
<div class="sidebar">
  <div class="logo">
    <div class="logo-icon"><i class="fas fa-soap"></i></div>
    <div><div class="logo-text">CleanGo</div><div class="logo-sub">Customer Panel</div></div>
  </div>
  <nav>
    <a href="?page=dashboard" class="<?= $page==='dashboard'?'active':'' ?>"><i class="fas fa-home"></i> Dashboard</a>
    <a href="?page=booking" class="<?= $page==='booking'?'active':'' ?>"><i class="fas fa-plus-circle"></i> Buat Booking</a>
    <a href="?page=riwayat" class="<?= $page==='riwayat'?'active':'' ?>"><i class="fas fa-list"></i> Riwayat Order</a>
    <a href="?page=pembayaran" class="<?= $page==='pembayaran'?'active':'' ?>">
      <i class="fas fa-credit-card"></i> Pembayaran
      <?php if(count($ordersBayar)>0): ?><span class="notif-badge-nav"><?= count($ordersBayar) ?></span><?php endif; ?>
    </a>
    <a href="?page=tracking_saya" class="<?= $page==='tracking_saya'?'active':'' ?>"><i class="fas fa-map-marker-alt"></i> Tracking Order</a>
    <a href="?page=invoice" class="<?= $page==='invoice'?'active':'' ?>"><i class="fas fa-file-invoice"></i> Invoice</a>
    <a href="?page=profil" class="<?= $page==='profil'?'active':'' ?>"><i class="fas fa-user"></i> Profil</a>
  </nav>
  <div class="sidebar-bottom">
    <div class="user-card">
      <div class="avatar"><?= mb_strtoupper(mb_substr($customerName,0,1)) ?></div>
      <div class="user-info"><div class="name"><?= htmlspecialchars($customerName) ?></div><div class="role">Customer</div></div>
    </div>
    <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Keluar</a>
  </div>
</div>

<!-- MAIN -->
<div class="main">
  <!-- TOP BAR -->
  <div class="topbar">
    <div class="topbar-title">
      <i class="fas fa-soap" style="color:#1e90ff;margin-right:8px"></i>CleanGo Laundry
    </div>
    <div class="topbar-right">
      <span style="font-size:13px;color:#64748b">Halo, <strong><?= htmlspecialchars($customerName) ?></strong>!</span>
    </div>
  </div>

  <div class="content">
    <?php if ($flash): ?>
    <div class="flash <?= $flashType ?>"><i class="fas fa-<?= $flashType==='success'?'check-circle':'exclamation-circle' ?>"></i> <?= $flash ?></div>
    <?php endif; ?>

    <!-- Alert tagihan pending -->
    <?php foreach($ordersBayar as $ob): ?>
    <?php if($page !== 'pembayaran'): ?>
    <div class="alert-pay">
      <div class="alert-pay-ico">💳</div>
      <div>
        <strong>Tagihan Menunggu Pembayaran — <?= htmlspecialchars($ob['kode_order']) ?></strong>
        <p>Staff sudah memasukkan tagihan sebesar <strong><?= rupiah($ob['jumlah_bayar']) ?></strong>. Segera lakukan pembayaran via QRIS.</p>
      </div>
      <a href="?page=pembayaran&id=<?= $ob['id_order'] ?>" class="btn btn-warning btn-sm" style="margin-left:auto;flex-shrink:0">Bayar Sekarang</a>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>

    <?php // ============================================================
    // HALAMAN: DASHBOARD
    if ($page === 'dashboard'): ?>
    <div class="page-header">
      <h1>👋 Halo, <?= htmlspecialchars($customerName) ?>!</h1>
      <p>Selamat datang di CleanGo. Berikut ringkasan order kamu.</p>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="label">Order Aktif</div>
        <div class="value"><?= $statAktif ?></div>
        <div class="sub">Sedang diproses</div>
      </div>
      <div class="stat-card">
        <div class="label">Order Selesai</div>
        <div class="value"><?= $statSelesai ?></div>
        <div class="sub">Total pesanan selesai</div>
      </div>
      <div class="stat-card">
        <div class="label">Total Tagihan Lunas</div>
        <div class="value" style="font-size:18px"><?= rupiah($statTotal) ?></div>
        <div class="sub">Akumulasi pembayaran</div>
      </div>
    </div>

    <!-- Order Terbaru -->
    <div class="card">
      <div class="card-header">
        <h3>📋 Order Terbaru</h3>
        <a href="?page=booking" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Booking Baru</a>
      </div>
      <?php if (empty($myOrders)): ?>
      <div style="padding:40px;text-align:center;color:#94a3b8">
        <i class="fas fa-box-open" style="font-size:40px;margin-bottom:12px;display:block"></i>
        Belum ada order. <a href="?page=booking">Buat booking pertamamu!</a>
      </div>
      <?php else: ?>
      <table>
        <thead><tr><th>Kode</th><th>Layanan</th><th>Status</th><th>Total</th><th>Tanggal</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach(array_slice($myOrders,0,5) as $o): ?>
        <tr>
          <td><strong><?= htmlspecialchars($o['kode_order']) ?></strong></td>
          <td><?= htmlspecialchars($o['nama_layanan']) ?><?php if($o['varian']): ?> <span class="badge <?= $o['varian']==='Express' ? 'badge-express' : 'badge-blue' ?>"><?= $o['varian'] ?></span><?php endif; ?></td>
          <td><span class="badge <?= badgeStatus($o['status_order']) ?>"><?= $o['status_order'] ?></span></td>
          <td><?= $o['total_harga']>0 ? rupiah($o['total_harga']) : '<span style="color:#94a3b8">Menunggu</span>' ?></td>
          <td><?= date('d/m/Y', strtotime($o['tanggal_pesan'])) ?></td>
          <td><a href="?page=riwayat&id=<?= $o['id_order'] ?>" class="btn btn-outline btn-sm">Detail</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

    <?php // ============================================================
    // HALAMAN: BOOKING BARU
    elseif ($page === 'booking'): ?>
    <div class="page-header">
      <h1>📦 Buat Booking Baru</h1>
      <p>Pesan layanan laundry dengan mudah.</p>
    </div>

    <div style="max-width:640px;background:#fff;border:1px solid #e2e8f0;border-radius:20px;box-shadow:0 1px 4px rgba(0,0,0,.06);padding:28px 32px;margin-bottom:24px">
      <div style="display:flex;align-items:center;gap:8px;font-size:15px;font-weight:700;color:#1e293b;margin-bottom:24px">
        🧼 Form Pemesanan Laundry
      </div>
      <form method="POST">
        <input type="hidden" name="form_type" value="booking_submit">

        <!-- Row 1: Jenis Layanan + Paket -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:14px">
          <div>
            <label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px">Jenis Layanan *</label>
            <select name="id_layanan" id="selLayanan" onchange="filterKatalog()" required
              style="width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:9px 12px;font-size:13px;color:#1e293b;background:#fff;outline:none">
              <option value="">-- Pilih Layanan --</option>
              <?php foreach($layananList as $l): ?>
              <option value="<?= $l['id_layanan'] ?>"><?= htmlspecialchars($l['nama_layanan']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px">Paket / Varian *</label>
            <select name="id_katalog" id="selKatalog" required
              style="width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:9px 12px;font-size:13px;color:#1e293b;background:#fff;outline:none">
              <option value="">-- Pilih paket dulu --</option>
              <?php foreach($katalogList as $k): ?>
              <option value="<?= $k['id_katalog'] ?>" data-layanan="<?= $k['id_layanan'] ?>" data-harga="<?= $k['harga'] ?>" data-satuan="<?= $k['satuan'] ?>">
                <?= htmlspecialchars($k['varian']) ?> — Rp <?= number_format($k['harga'],0,',','.') ?>/<?= $k['satuan'] ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Info harga -->
        <div id="hargaInfo" style="display:none;margin-bottom:14px">
          <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:10px 14px;font-size:13px;color:#1d4ed8">
            <i class="fas fa-info-circle"></i> Harga: <strong id="hargaText">-</strong>
            <span style="color:#60a5fa;font-weight:400"> (tagihan final dihitung setelah berat/jumlah diverifikasi staff)</span>
          </div>
        </div>

        <!-- Alamat -->
        <div style="margin-bottom:14px">
          <label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px">Alamat Penjemputan *</label>
          <textarea name="alamat" placeholder="Masukkan alamat lengkap penjemputan..." required
            style="width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:9px 12px;font-size:13px;color:#1e293b;resize:vertical;min-height:60px;max-height:120px;outline:none;box-sizing:border-box"><?= htmlspecialchars($profil['alamat_cust'] ?? '') ?></textarea>
        </div>

        <!-- Row 2: Tanggal + Sesi -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:14px">
          <div>
            <label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px">Tanggal Jemput *</label>
            <input type="date" id="inputTanggal" name="tanggal_jemput" required min="<?= date('Y-m-d') ?>" onchange="updateSesiJemput()"
              style="width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:9px 12px;font-size:13px;color:#1e293b;outline:none;box-sizing:border-box">
          </div>
          <div>
            <label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px">Sesi Jemput *</label>
            <select id="selectSesi" name="sesi_jemput" required onchange="validateSesi()"
              style="width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:9px 12px;font-size:13px;color:#1e293b;background:#fff;outline:none">
              <option value="">-- Pilih Sesi --</option>
              <option value="08:00-09:00" id="sesi1">08:00 - 09:00</option>
              <option value="12:00-13:00" id="sesi2">12:00 - 13:00</option>
              <option value="15:00-16:00" id="sesi3">15:00 - 16:00</option>
            </select>
          </div>
        </div>

        <!-- Catatan -->
        <div style="margin-bottom:22px">
          <label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px">Catatan (opsional)</label>
          <input type="text" name="catatan" placeholder="Misal: pakaian sensitif, jangan diperas"
            style="width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:9px 12px;font-size:13px;color:#1e293b;outline:none;box-sizing:border-box">
        </div>

        <button type="submit"
          style="display:inline-flex;align-items:center;gap:8px;background:#1d4ed8;color:#fff;font-size:14px;font-weight:700;padding:11px 24px;border-radius:10px;border:none;cursor:pointer">
          <i class="fas fa-paper-plane"></i> Kirim Booking
        </button>
      </form>
    </div>

    <script>
    const hariIni = new Date('<?= date('Y-m-d') ?>').toISOString().split('T')[0];
    const waktuSekarang = new Date().getHours() * 60 + new Date().getMinutes();

    function updateSesiJemput() {
      const tanggalInput = document.getElementById('inputTanggal').value;
      const sesi1 = document.getElementById('sesi1');
      const sesi2 = document.getElementById('sesi2');
      const sesi3 = document.getElementById('sesi3');

      // Reset semua sesi ke enabled
      sesi1.disabled = false;
      sesi2.disabled = false;
      sesi3.disabled = false;

      // Jika tanggal adalah hari ini, disable sesi yang sudah lewat
      if (tanggalInput === hariIni) {
        // Sesi 1: 08:00 - 09:00
        if (waktuSekarang >= 8 * 60) sesi1.disabled = true;
        // Sesi 2: 12:00 - 13:00
        if (waktuSekarang >= 12 * 60) sesi2.disabled = true;
        // Sesi 3: 15:00 - 16:00
        if (waktuSekarang >= 15 * 60) sesi3.disabled = true;
      }
    }

    function validateSesi() {
      const tanggalInput = document.getElementById('inputTanggal').value;
      const sesiInput = document.getElementById('selectSesi').value;

      if (!sesiInput) return;

      // Jika tanggal kosong
      if (!tanggalInput) {
        alert('⚠️ Silakan pilih tanggal jemput terlebih dahulu.');
        document.getElementById('selectSesi').value = '';
        return;
      }

      // Jika tanggal adalah hari ini
      if (tanggalInput === hariIni) {
        const waktuSesi = parseInt(sesiInput.split(':')[0]) * 60;
        if (waktuSekarang >= waktuSesi) {
          alert('❌ Sesi jemput yang dipilih sudah lewat.\\n\\nSilakan pilih sesi lainnya.');
          document.getElementById('selectSesi').value = '';
        }
      }
    }
    </script>

    <?php // ============================================================
    // HALAMAN: RIWAYAT ORDER
    elseif ($page === 'riwayat'): ?>
    <div class="page-header">
      <h1>📋 Riwayat Order</h1>
      <p>Semua pesanan laundry kamu.</p>
    </div>
    <?php if ($selectedOrder): ?>
    <!-- Detail Order -->
    <a href="?page=riwayat" class="btn btn-outline btn-sm" style="margin-bottom:16px"><i class="fas fa-arrow-left"></i> Kembali</a>
    <div class="card" style="padding:24px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px">
        <div>
          <h2 style="font-size:20px"><?= htmlspecialchars($selectedOrder['kode_order']) ?></h2>
          <p style="color:#64748b;font-size:13px">Dipesan: <?= date('d F Y H:i', strtotime($selectedOrder['tanggal_pesan'])) ?></p>
        </div>
        <span class="badge <?= badgeStatus($selectedOrder['status_order']) ?>" style="font-size:14px;padding:6px 16px"><?= $selectedOrder['status_order'] ?></span>
      </div>

      <!-- TRACKING STEPS -->
      <?php if($selectedOrder['status_order'] !== 'Dibatalkan'): ?>
      <div class="tracking-steps">
        <?php $curStep = $statusSteps[$selectedOrder['status_order']] ?? 0;
        foreach($statusLabels as $i => $label): ?>
        <?php if($i > 0): ?><div class="step-line <?= $i <= $curStep ? 'done' : '' ?>"></div><?php endif; ?>
        <div class="step">
          <div class="step-icon <?= $i < $curStep ? 'done' : ($i == $curStep ? 'active' : '') ?>">
            <?= $statusIcons[$i] ?>
          </div>
          <div class="step-label" style="font-size:10px"><?= $label ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:16px">
        <div>
          <h4 style="font-size:13px;color:#64748b;margin-bottom:8px">DETAIL LAYANAN</h4>
          <p><strong><?= htmlspecialchars($selectedOrder['nama_layanan']) ?></strong></p>
          <?php if($selectedOrder['varian']): ?><p style="color:#64748b;font-size:13px"><?= $selectedOrder['varian'] ?></p><?php endif; ?>
          <?php if($selectedOrder['berat']): ?><p style="font-size:13px">Berat: <strong><?= $selectedOrder['berat'] ?> kg</strong></p><?php endif; ?>
          <?php if($selectedOrder['qty']): ?><p style="font-size:13px">Jumlah: <strong><?= $selectedOrder['qty'] ?> pcs</strong></p><?php endif; ?>
          <?php if($selectedOrder['total_harga']>0): ?><p style="font-size:14px;margin-top:6px">Total: <strong style="color:#0059b8"><?= rupiah($selectedOrder['total_harga']) ?></strong></p><?php endif; ?>
        </div>
        <div>
          <h4 style="font-size:13px;color:#64748b;margin-bottom:8px">INFO PENJEMPUTAN</h4>
          <p style="font-size:13px"><?= nl2br(htmlspecialchars($selectedOrder['alamat_penjemputan'])) ?></p>
          <?php if($selectedOrder['jadwal_jemput']): ?><p style="font-size:13px;margin-top:6px">📅 <?= date('d F Y H:i', strtotime($selectedOrder['jadwal_jemput'])) ?></p><?php endif; ?>
          <?php if($selectedOrder['nama_staff']): ?><p style="font-size:13px;margin-top:6px">👤 Staff: <?= htmlspecialchars($selectedOrder['nama_staff']) ?></p><?php endif; ?>
          <?php if($selectedOrder['catatan']): ?><p style="font-size:13px;margin-top:6px;color:#64748b">📝 <?= htmlspecialchars($selectedOrder['catatan']) ?></p><?php endif; ?>
        </div>
      </div>

      <!-- Timeline -->
      <?php if (!empty($selectedTracking)): ?>
      <div style="margin-top:24px;padding-top:20px;border-top:1px solid #e2e8f0">
        <h4 style="font-size:14px;font-weight:700;margin-bottom:14px">📍 Riwayat Tracking</h4>
        <ul class="timeline">
          <?php foreach(array_reverse($selectedTracking) as $t): ?>
          <li class="tl-item">
            <div class="tl-dot"></div>
            <div class="tl-body">
              <strong><?= htmlspecialchars($t['status']) ?></strong>
              <?php if($t['keterangan']): ?> — <?= htmlspecialchars($t['keterangan']) ?><?php endif; ?>
              <br><small><?= date('d/m/Y H:i', strtotime($t['waktu_update'])) ?></small>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>

    <?php else: ?>
    <div class="card">
      <div class="card-header"><h3>Semua Order</h3><a href="?page=booking" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Booking Baru</a></div>
      <?php if(empty($myOrders)): ?>
      <div style="padding:40px;text-align:center;color:#94a3b8"><i class="fas fa-inbox" style="font-size:40px;display:block;margin-bottom:12px"></i>Belum ada order.</div>
      <?php else: ?>
      <table>
        <thead><tr><th>Kode Order</th><th>Layanan</th><th>Status</th><th>Total</th><th>Tanggal</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach($myOrders as $o): ?>
        <tr>
          <td><strong><?= htmlspecialchars($o['kode_order']) ?></strong></td>
          <td><?= htmlspecialchars($o['nama_layanan']) ?><?php if($o['varian']): ?> <span class="badge <?= $o['varian']==='Express' ? 'badge-express' : 'badge-blue' ?>"><?= $o['varian'] ?></span><?php endif; ?></td>
          <td><span class="badge <?= badgeStatus($o['status_order']) ?>"><?= $o['status_order'] ?></span></td>
          <td><?= $o['total_harga']>0 ? rupiah($o['total_harga']) : '<span style="color:#94a3b8;font-size:12px">Menunggu verifikasi</span>' ?></td>
          <td><?= date('d/m/Y', strtotime($o['tanggal_pesan'])) ?></td>
          <td><a href="?page=riwayat&id=<?= $o['id_order'] ?>" class="btn btn-outline btn-sm">Detail</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php // ============================================================
    // HALAMAN: PEMBAYARAN
    elseif ($page === 'pembayaran'): ?>
    <div class="page-header">
      <h1>💳 Pembayaran</h1>
      <p>Lakukan pembayaran untuk order yang sudah diverifikasi staff.</p>
    </div>

    <?php
    $payId = (int)($_GET['id'] ?? 0);
    // Order yang perlu dibayar
    $pendingOrders = array_filter($myOrders, fn($o) => $o['status_bayar'] === 'Pending' && $o['jumlah_bayar'] > 0);
    $waitingOrders = array_filter($myOrders, fn($o) => $o['status_bayar'] === 'Menunggu Konfirmasi');
    $doneOrders    = array_filter($myOrders, fn($o) => $o['status_bayar'] === 'Lunas');
    ?>

    <?php if ($payId && $selectedPayment && $selectedOrder): ?>
    <!-- Form Bayar -->
    <a href="?page=pembayaran" class="btn btn-outline btn-sm" style="margin-bottom:20px"><i class="fas fa-arrow-left"></i> Kembali</a>
    <div class="form-card" style="padding:28px 32px">
      <h3 style="font-size:17px;font-weight:800;color:#1e293b;margin-bottom:24px;display:flex;align-items:center;gap:8px">
        💳 Pembayaran — <span style="color:#0ea5e9"><?= htmlspecialchars($selectedOrder['kode_order']) ?></span>
      </h3>
      <div style="display:flex;gap:24px;align-items:flex-start;margin-bottom:24px;flex-wrap:wrap">
        <!-- QRIS Box -->
        <div style="flex:1;min-width:240px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:16px;padding:24px;text-align:center">
          <div style="font-size:14px;font-weight:600;color:#1d4ed8;margin-bottom:16px;display:flex;align-items:center;justify-content:center;gap:6px">
            <i class="fas fa-qrcode"></i> Bayar via QRIS
          </div>
          <div style="width:130px;height:130px;margin:0 auto 14px;background:#fff;border:1px solid #dbeafe;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:48px">📱</div>
          <p style="font-size:12px;color:#475569;margin-bottom:14px">Scan QR code di atas menggunakan aplikasi pembayaran digital kamu</p>
          <div style="font-size:24px;font-weight:800;color:#1d4ed8"><?= rupiah($selectedPayment['jumlah']) ?></div>
        </div>
        <!-- Receipt Box -->
        <div style="flex:1;min-width:240px;background:#fff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden">
          <div style="background:#f8fafc;border-bottom:1px solid #e2e8f0;padding:16px 20px;display:flex;justify-content:space-between;align-items:flex-start">
            <div>
              <div style="font-weight:700;font-size:15px;color:#1e293b">CleanGo Laundry</div>
              <div style="font-size:12px;color:#94a3b8;margin-top:2px">Bukti Tagihan</div>
            </div>
            <div style="text-align:right">
              <div style="font-weight:700;font-size:13px;color:#1e293b"><?= htmlspecialchars($selectedOrder['kode_order']) ?></div>
              <div style="font-size:12px;color:#94a3b8;margin-top:2px"><?= date('d/m/Y', strtotime($selectedOrder['tanggal_pesan'])) ?></div>
            </div>
          </div>
          <div style="padding:4px 0">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 20px;border-bottom:1px solid #f1f5f9">
              <span style="font-size:13px;color:#64748b">Layanan</span>
              <span style="font-size:13px;font-weight:600;color:#1e293b"><?= htmlspecialchars($selectedOrder['nama_layanan']) ?></span>
            </div>
            <?php if($selectedOrder['varian']): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 20px;border-bottom:1px solid #f1f5f9">
              <span style="font-size:13px;color:#64748b">Varian</span>
              <span style="font-size:13px;font-weight:600;color:#1e293b"><?= $selectedOrder['varian'] ?></span>
            </div>
            <?php endif; ?>
            <?php if($selectedOrder['berat']): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 20px;border-bottom:1px solid #f1f5f9">
              <span style="font-size:13px;color:#64748b">Berat</span>
              <span style="font-size:13px;font-weight:600;color:#1e293b"><?= $selectedOrder['berat'] ?> kg</span>
            </div>
            <?php endif; ?>
            <?php if($selectedOrder['qty']): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 20px;border-bottom:1px solid #f1f5f9">
              <span style="font-size:13px;color:#64748b">Jumlah</span>
              <span style="font-size:13px;font-weight:600;color:#1e293b"><?= $selectedOrder['qty'] ?> pcs</span>
            </div>
            <?php endif; ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 20px;background:#f0fdf4">
              <span style="font-size:14px;font-weight:700;color:#166534">Total Tagihan</span>
              <span style="font-size:16px;font-weight:800;color:#059669"><?= rupiah($selectedPayment['jumlah']) ?></span>
            </div>
          </div>
        </div>
      </div>
      <form method="POST">
        <input type="hidden" name="form_type" value="upload_bukti">
        <input type="hidden" name="id_order" value="<?= $selectedOrder['id_order'] ?>">
        <div class="field" style="margin-bottom:16px">
          <label>Catatan Pembayaran (opsional)</label>
          <input type="text" name="catatan_bayar" placeholder="Misal: sudah transfer via GoPay pukul 14.00">
        </div>
        <button type="submit" style="width:100%;display:flex;align-items:center;justify-content:center;gap:8px;background:#059669;color:#fff;font-size:15px;font-weight:700;padding:14px 24px;border-radius:12px;border:none;cursor:pointer">
          <i class="fas fa-check-circle"></i> Konfirmasi Pembayaran
        </button>
      </form>
    </div>

    <?php else: ?>
    <!-- Daftar pembayaran -->
    <?php if(!empty($pendingOrders)): ?>
    <div class="card" style="border:2px solid #f59e0b">
      <div class="card-header" style="background:#fffbeb"><h3>⚠️ Menunggu Pembayaranmu (<?= count($pendingOrders) ?>)</h3></div>
      <table>
        <thead><tr><th>Kode</th><th>Layanan</th><th>Tagihan</th><th>Metode</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach($pendingOrders as $o): ?>
        <tr>
          <td><strong><?= htmlspecialchars($o['kode_order']) ?></strong></td>
          <td><?= htmlspecialchars($o['nama_layanan']) ?></td>
          <td style="font-weight:700;color:#0059b8"><?= rupiah($o['jumlah_bayar']) ?></td>
          <td><span class="badge badge-blue">QRIS</span></td>
          <td><a href="?page=pembayaran&id=<?= $o['id_order'] ?>" class="btn btn-warning btn-sm"><i class="fas fa-credit-card"></i> Bayar Sekarang</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php if(!empty($waitingOrders)): ?>
    <div class="card">
      <div class="card-header"><h3>🕐 Menunggu Konfirmasi Staff (<?= count($waitingOrders) ?>)</h3></div>
      <table>
        <thead><tr><th>Kode</th><th>Layanan</th><th>Tagihan</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach($waitingOrders as $o): ?>
        <tr>
          <td><strong><?= htmlspecialchars($o['kode_order']) ?></strong></td>
          <td><?= htmlspecialchars($o['nama_layanan']) ?></td>
          <td style="font-weight:700"><?= rupiah($o['jumlah_bayar']) ?></td>
          <td><span class="badge badge-warning">Menunggu Konfirmasi</span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php if(!empty($doneOrders)): ?>
    <div class="card">
      <div class="card-header"><h3>✅ Pembayaran Lunas (<?= count($doneOrders) ?>)</h3></div>
      <table>
        <thead><tr><th>Kode</th><th>Layanan</th><th>Jumlah</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach($doneOrders as $o): ?>
        <tr>
          <td><strong><?= htmlspecialchars($o['kode_order']) ?></strong></td>
          <td><?= htmlspecialchars($o['nama_layanan']) ?></td>
          <td><?= rupiah($o['jumlah_bayar']) ?></td>
          <td><span class="badge badge-success">Lunas ✓</span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php if(empty($pendingOrders) && empty($waitingOrders) && empty($doneOrders)): ?>
    <div style="text-align:center;padding:60px;color:#94a3b8"><i class="fas fa-check-circle" style="font-size:50px;display:block;margin-bottom:16px;color:#bbf7d0"></i>Belum ada transaksi pembayaran.</div>
    <?php endif; ?>
    <?php endif; ?>

    <?php // ============================================================
    // HALAMAN: TRACKING ORDER
    elseif ($page === 'tracking_saya'): ?>
    <div class="page-header">
      <h1>📍 Tracking Order</h1>
      <p>Pantau status laundry kamu secara real-time.</p>
    </div>
    <?php
    $activeOrders = array_filter($myOrders, fn($o) => !in_array($o['status_order'], ['Selesai','Dibatalkan']));
    if(empty($activeOrders)): ?>
    <div style="text-align:center;padding:60px;color:#94a3b8"><i class="fas fa-map-marker-alt" style="font-size:50px;display:block;margin-bottom:16px"></i>Tidak ada order aktif saat ini.</div>
    <?php else: ?>
    <?php foreach($activeOrders as $o):
    $st2 = $pdo->prepare("SELECT * FROM tracking WHERE id_order=? ORDER BY waktu_update DESC LIMIT 5");
    $st2->execute([$o['id_order']]);
    $trkList = $st2->fetchAll();
    $curStep = $statusSteps[$o['status_order']] ?? 0;
    ?>
    <div class="card" style="padding:24px;margin-bottom:20px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px">
        <div>
          <h3 style="font-size:17px"><?= htmlspecialchars($o['kode_order']) ?></h3>
          <p style="font-size:13px;color:#64748b"><?= htmlspecialchars($o['nama_layanan']) ?> <?php if($o['varian']): ?>· <?= $o['varian'] ?><?php endif; ?></p>
        </div>
        <span class="badge <?= badgeStatus($o['status_order']) ?>" style="font-size:13px;padding:5px 14px"><?= $o['status_order'] ?></span>
      </div>
      <div class="tracking-steps">
        <?php foreach($statusLabels as $i => $label): ?>
        <?php if($i>0): ?><div class="step-line <?= $i<=$curStep?'done':'' ?>"></div><?php endif; ?>
        <div class="step">
          <div class="step-icon <?= $i<$curStep?'done':($i==$curStep?'active':'') ?>"><?= $statusIcons[$i] ?></div>
          <div class="step-label" style="font-size:10px"><?= $label ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if(!empty($trkList)): ?>
      <div style="margin-top:14px;padding-top:14px;border-top:1px solid #e2e8f0">
        <p style="font-size:12px;color:#64748b;margin-bottom:8px">Update terakhir:</p>
        <ul class="timeline">
        <?php foreach($trkList as $t): ?>
        <li class="tl-item">
          <div class="tl-dot"></div>
          <div class="tl-body"><strong><?= htmlspecialchars($t['status']) ?></strong><?php if($t['keterangan']): ?> — <?= htmlspecialchars($t['keterangan']) ?><?php endif; ?><br><small><?= date('d/m/Y H:i', strtotime($t['waktu_update'])) ?></small></div>
        </li>
        <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
      <?php if($o['status_bayar']==='Pending' && $o['jumlah_bayar']>0): ?>
      <div style="margin-top:14px;padding:12px 16px;background:#fffbeb;border-radius:10px;border:1px solid #f59e0b;display:flex;align-items:center;justify-content:space-between">
        <span style="font-size:13px;color:#92400e"><strong>Tagihan:</strong> <?= rupiah($o['jumlah_bayar']) ?> — menunggu pembayaranmu</span>
        <a href="?page=pembayaran&id=<?= $o['id_order'] ?>" class="btn btn-warning btn-sm">Bayar</a>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php // ============================================================
    // HALAMAN: INVOICE
    elseif ($page === 'invoice'): ?>
    <div class="page-header">
      <h1>🧾 Invoice</h1>
      <p>Riwayat invoice dari transaksi yang telah selesai.</p>
    </div>
    <div class="card">
      <div class="card-header"><h3>Semua Invoice</h3></div>
      <?php if(empty($invoices)): ?>
      <div style="padding:40px;text-align:center;color:#94a3b8">Belum ada invoice.</div>
      <?php else: ?>
      <table>
        <thead><tr><th>No. Invoice</th><th>Kode Order</th><th>Layanan</th><th>Jumlah</th><th>Status Bayar</th><th>Tanggal</th></tr></thead>
        <tbody>
        <?php foreach($invoices as $inv): ?>
        <tr>
          <td><strong><?= htmlspecialchars($inv['no_invoice']) ?></strong></td>
          <td><?= htmlspecialchars($inv['kode_order']) ?></td>
          <td><?= htmlspecialchars($inv['nama_layanan']) ?></td>
          <td style="font-weight:700"><?= rupiah($inv['jumlah']) ?></td>
          <td><span class="badge <?= badgeStatus($inv['status_bayar']) ?>"><?= $inv['status_bayar'] ?></span></td>
          <td><?= date('d/m/Y', strtotime($inv['tgl_invoice'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

    <?php // ============================================================
    // HALAMAN: PROFIL
    elseif ($page === 'profil'): ?>
    <div class="page-header">
      <h1>👤 Profil Saya</h1>
      <p>Kelola informasi akun kamu.</p>
    </div>
    <div class="form-card" style="max-width:600px">
      <h3>Edit Informasi Pribadi</h3>
      <form method="POST">
        <input type="hidden" name="form_type" value="update_profil">
        <div class="form-grid">
          <div class="field">
            <label>Nama Lengkap</label>
            <input type="text" name="nama_cust" value="<?= htmlspecialchars($profil['nama_cust']) ?>" required>
          </div>
          <div class="field">
            <label>Username</label>
            <input type="text" value="<?= htmlspecialchars($profil['username']) ?>" disabled style="opacity:.6">
          </div>
          <div class="field">
            <label>No. Telepon</label>
            <input type="text" name="notelp_cust" value="<?= htmlspecialchars($profil['notelp_cust']) ?>">
          </div>
          <div class="field full">
            <label>Alamat</label>
            <textarea name="alamat_cust"><?= htmlspecialchars($profil['alamat_cust'] ?? '') ?></textarea>
          </div>
        </div>
        <div style="margin-top:16px">
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
        </div>
      </form>
    </div>
    <div class="form-card" style="max-width:600px;background:#f8fafc">
      <h3>Informasi Akun</h3>
      <p style="font-size:14px;color:#64748b">Member sejak: <strong><?= date('d F Y', strtotime($profil['created_at'])) ?></strong></p>
      <p style="font-size:14px;color:#64748b;margin-top:8px">Total Order: <strong><?= count($myOrders) ?></strong></p>
    </div>

    <?php endif; ?>
  </div><!-- /content -->
</div><!-- /main -->

<script>
// ============================================================
// BOOKING FORM: Filter Katalog
// ============================================================
function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function timeAgo(dateStr) {
  const d = new Date(dateStr.replace(' ','T'));
  const diff = Math.floor((Date.now() - d) / 1000);
  if (diff < 60) return 'Baru saja';
  if (diff < 3600) return Math.floor(diff/60) + ' menit lalu';
  if (diff < 86400) return Math.floor(diff/3600) + ' jam lalu';
  return Math.floor(diff/86400) + ' hari lalu';
}

// ============================================================
// BOOKING FORM: Filter Katalog
// ============================================================
function filterKatalog() {
  const layananId = document.getElementById('selLayanan').value;
  const sel = document.getElementById('selKatalog');
  const opts = sel.querySelectorAll('option');
  sel.value = '';
  opts.forEach(o => {
    if (!o.value) { o.style.display = ''; return; }
    o.style.display = o.dataset.layanan === layananId ? '' : 'none';
  });
  document.getElementById('hargaInfo').style.display = 'none';
}

document.getElementById('selKatalog') && document.getElementById('selKatalog').addEventListener('change', function() {
  const opt = this.options[this.selectedIndex];
  const info = document.getElementById('hargaInfo');
  if (opt && opt.value) {
    document.getElementById('hargaText').textContent = 'Rp ' + parseInt(opt.dataset.harga).toLocaleString('id') + ' / ' + opt.dataset.satuan;
    info.style.display = '';
  } else {
    info.style.display = 'none';
  }
});
</script>

<script>
(function(){
  const role = "customer";
  const body = document.body;
  body.className = 'min-h-screen bg-slate-50 text-slate-800';

  const colorMap = {
    customer: {
      sidebar: 'bg-gradient-to-b from-sky-950 via-blue-800 to-sky-500',
      primary: 'bg-sky-600 hover:bg-sky-700 text-white',
      outline: 'border border-sky-600 text-sky-700 hover:bg-sky-50',
      soft: 'bg-sky-50 text-sky-700',
      topIcon: 'text-sky-600',
      badgeNav: 'bg-rose-500 text-white'
    },
    owner: {
      sidebar: 'bg-gradient-to-b from-violet-950 via-violet-800 to-fuchsia-600',
      primary: 'bg-violet-600 hover:bg-violet-700 text-white',
      outline: 'border border-violet-600 text-violet-700 hover:bg-violet-50',
      soft: 'bg-violet-50 text-violet-700',
      topIcon: 'text-violet-600',
      badgeNav: 'bg-rose-500 text-white'
    },
    staff: {
      sidebar: 'bg-gradient-to-b from-emerald-950 via-emerald-800 to-emerald-500',
      primary: 'bg-emerald-600 hover:bg-emerald-700 text-white',
      outline: 'border border-emerald-600 text-emerald-700 hover:bg-emerald-50',
      soft: 'bg-emerald-50 text-emerald-700',
      topIcon: 'text-emerald-600',
      badgeNav: 'bg-rose-500 text-white'
    }
  };
  const c = colorMap[role];
  const add = (els, cls) => els.forEach(el => el && el.classList.add(...cls.split(' ')));
  const rem = (els, cls) => els.forEach(el => el && el.className && cls.split(' ').forEach(k => el.classList.remove(k)));

  add([document.querySelector('.sidebar')], `fixed inset-y-0 left-0 w-64 text-white flex flex-col p-5 z-40 overflow-y-auto ${c.sidebar}`);
  add([document.querySelector('.main')], 'min-h-screen flex-1 md:ml-64 flex flex-col');
  add([document.querySelector('.topbar')], 'sticky top-0 z-30 bg-white/90 backdrop-blur border-b border-slate-200 px-6 py-4 flex items-center justify-between');
  add([document.querySelector('.topbar-title')], 'text-sm md:text-base font-semibold text-slate-900 flex items-center');
  add([document.querySelector('.topbar-right')], 'flex items-center gap-3');
  add([document.querySelector('.content')], 'p-5 md:p-8');

  add([document.querySelector('.logo')], 'flex items-center gap-3 px-2 pb-6 mb-6 border-b border-white/15');
  add([document.querySelector('.logo-icon')], 'w-12 h-12 rounded-2xl bg-white/15 flex items-center justify-center text-xl');
  add([document.querySelector('.logo-text')], 'text-2xl font-extrabold tracking-tight');
  add([document.querySelector('.logo-sub')], 'text-xs text-white/70');

  document.querySelectorAll('nav a').forEach(a => {
    a.classList.add('flex','items-center','gap-3','px-4','py-3','rounded-xl','text-sm','font-medium','transition');
    if(a.classList.contains('active')) a.classList.add('bg-white/20','text-white','shadow-sm');
    else a.classList.add('text-white/85','hover:bg-white/10','hover:text-white');
    const i = a.querySelector('i'); if(i) i.classList.add('w-5','text-center');
  });
  document.querySelectorAll('.notif-badge-nav').forEach(b => b.classList.add('ml-auto','text-[10px]','font-bold','rounded-full','px-2','py-0.5','bg-rose-500','text-white'));

  add([document.querySelector('.sidebar-bottom')], 'mt-auto pt-5 border-t border-white/15');
  add([document.querySelector('.user-card')], 'flex items-center gap-3 px-2 py-2');
  add([document.querySelector('.avatar')], 'w-10 h-10 rounded-full bg-white/20 flex items-center justify-center font-bold');
  add(Array.from(document.querySelectorAll('.user-info .name')), 'text-sm font-semibold');
  add(Array.from(document.querySelectorAll('.user-info .role')), 'text-xs text-white/70');
  add(Array.from(document.querySelectorAll('.btn-logout')), 'mt-3 inline-flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold bg-rose-500/30 hover:bg-rose-500/40 text-white');

  add([document.querySelector('.page-header')], 'mb-6');
  add(Array.from(document.querySelectorAll('.page-header h1')), 'text-3xl font-bold tracking-tight text-slate-900');
  add(Array.from(document.querySelectorAll('.page-header p')), 'mt-1 text-sm text-slate-500');

  document.querySelectorAll('.flash').forEach(el => {
    el.classList.add('mb-5','flex','items-center','gap-3','rounded-2xl','border','px-4','py-3','text-sm','font-medium');
    if(el.classList.contains('success')) el.classList.add('bg-emerald-50','text-emerald-700','border-emerald-200');
    else if(el.classList.contains('error')) el.classList.add('bg-rose-50','text-rose-700','border-rose-200');
    else el.classList.add('bg-sky-50','text-sky-700','border-sky-200');
  });

  add([document.querySelector('.stats-grid')], 'grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-7');
  document.querySelectorAll('.stat-card').forEach(el => {
    el.classList.add('rounded-2xl','bg-white','shadow-sm','border','border-slate-200','p-5');
    if(el.classList.contains('alert-stat')) el.classList.add('ring-2','ring-amber-200');
    const label = el.querySelector('.label'); if(label) label.classList.add('text-xs','uppercase','tracking-wide','text-slate-500','mb-2');
    const value = el.querySelector('.value'); if(value) value.classList.add('text-3xl','font-extrabold','text-slate-900');
    const sub = el.querySelector('.sub'); if(sub) sub.classList.add('mt-1','text-xs','text-slate-500');
    const icon = el.querySelector('.icon'); if(icon) icon.classList.add('w-11','h-11','rounded-xl','flex','items-center','justify-center','mb-3');
  });

  document.querySelectorAll('.card, .form-card, .receipt, .order-card, .pay-box, .alert-pay, .modal-panel, .modal-box, .modal-card').forEach(el => {
    el.classList.add('bg-white','border','border-slate-200','rounded-2xl','shadow-sm');
  });
  document.querySelectorAll('.order-card').forEach(el => el.classList.add('p-6','mb-5','border-l-4'));
  document.querySelectorAll('.card').forEach(el => el.classList.add('mb-5','overflow-hidden'));
  document.querySelectorAll('.info-box').forEach(el => {
    el.classList.add('bg-white','border','border-slate-100','rounded-xl','shadow-sm','p-4');
    const lbl = el.querySelector('.label'); if(lbl) { lbl.classList.add('text-xs','font-medium','uppercase','tracking-wide','text-slate-400','mb-1'); }
    const val = el.querySelector('.val'); if(val) { val.classList.add('text-sm','font-semibold','text-slate-800'); }
  });
  document.querySelectorAll('.card-header').forEach(el => el.classList.add('flex','items-center','justify-between','gap-3','border-b','border-slate-200','px-5','py-4'));
  document.querySelectorAll('.card-header h3, .form-card h3').forEach(el => el.classList.add('font-bold','text-slate-900'));
  document.querySelectorAll('.form-card').forEach(el => el.classList.add('p-6','mb-6'));
  document.querySelectorAll('.form-grid, .info-grid').forEach(el => el.classList.add('grid','grid-cols-1','md:grid-cols-2','gap-4'));
  document.querySelectorAll('.field label').forEach(el => el.classList.add('mb-1.5','block','text-sm','font-medium','text-slate-700'));
  document.querySelectorAll('.field input, .field select, .field textarea').forEach(el => el.classList.add('w-full','rounded-xl','border','border-slate-300','bg-white','px-4','py-3','text-sm','shadow-sm','focus:outline-none','focus:ring-2','focus:ring-offset-0', role==='owner' ? 'focus:ring-violet-300' : role==='staff' ? 'focus:ring-emerald-300' : 'focus:ring-sky-300'));

  document.querySelectorAll('table').forEach(t => t.classList.add('w-full','text-sm'));
  document.querySelectorAll('th').forEach(el => el.classList.add('bg-slate-50','px-4','py-3','text-left','text-xs','font-semibold','uppercase','tracking-wide','text-slate-500','border-b','border-slate-200'));
  document.querySelectorAll('td').forEach(el => el.classList.add('px-4','py-3','border-b','border-slate-100','align-top'));
  document.querySelectorAll('tbody tr').forEach(el => el.classList.add('hover:bg-slate-50/80'));

  document.querySelectorAll('.badge').forEach(el => el.classList.add('inline-flex','items-center','rounded-full','px-2.5','py-1','text-xs','font-semibold'));
  const badgeMap = {
    'badge-warning':'bg-amber-100 text-amber-800',
    'badge-success':'bg-emerald-100 text-emerald-700',
    'badge-blue':'bg-sky-100 text-sky-700',
    'badge-express':'bg-indigo-100 text-indigo-700',
    'badge-process':'bg-blue-100 text-blue-700',
    'badge-purple':'bg-violet-100 text-violet-700',
    'badge-cyan':'bg-cyan-100 text-cyan-700',
    'badge-danger':'bg-rose-100 text-rose-700',
    'badge-yellow':'bg-yellow-100 text-yellow-700',
    'badge-default':'bg-slate-100 text-slate-700'
  };
  Object.entries(badgeMap).forEach(([k,v]) => document.querySelectorAll('.'+k).forEach(el => el.classList.add(...v.split(' '))));

  document.querySelectorAll('.btn').forEach(el => {
    el.classList.add('inline-flex','items-center','justify-center','gap-2','rounded-xl','px-4','py-2.5','text-sm','font-semibold','transition','shadow-sm');
    if(el.classList.contains('btn-primary')) el.classList.add(...c.primary.split(' '));
    else if(el.classList.contains('btn-outline')) el.classList.add(...c.outline.split(' '));
    else if(el.classList.contains('btn-success')) el.classList.add('bg-emerald-600','hover:bg-emerald-700','text-white');
    else if(el.classList.contains('btn-warning')) el.classList.add('bg-amber-500','hover:bg-amber-600','text-white');
    else if(el.classList.contains('btn-danger')) el.classList.add('bg-rose-50','text-rose-700','hover:bg-rose-100');
    else if(el.classList.contains('btn-blue')) el.classList.add('bg-sky-50','text-sky-700','hover:bg-sky-100');
  });
  document.querySelectorAll('.btn-sm').forEach(el => el.classList.add('px-3','py-2','text-xs'));

  document.querySelectorAll('.tracking-steps').forEach(el => el.classList.add('flex','items-center','gap-0','overflow-x-auto','py-4'));
  document.querySelectorAll('.step').forEach(el => el.classList.add('flex','min-w-[84px]','shrink-0','flex-col','items-center'));
  document.querySelectorAll('.step-icon').forEach(el => {
    el.classList.add('flex','h-10','w-10','items-center','justify-center','rounded-full','bg-slate-200','text-lg');
    if(el.classList.contains('done')) el.classList.add('bg-slate-900','text-white');
    if(el.classList.contains('active')) el.classList.add('ring-4', role==='owner' ? 'ring-violet-100' : role==='staff' ? 'ring-emerald-100' : 'ring-sky-100');
  });
  document.querySelectorAll('.step-line').forEach(el => el.classList.add('h-0.5','min-w-[26px]','flex-1','bg-slate-200'));
  document.querySelectorAll('.step-line.done').forEach(el => el.classList.add('bg-slate-900'));
  document.querySelectorAll('.step-label').forEach(el => el.classList.add('mt-2','text-center','text-[11px]','text-slate-500'));

  document.querySelectorAll('.timeline').forEach(el => el.classList.add('space-y-4'));
  document.querySelectorAll('.tl-item').forEach(el => el.classList.add('relative','flex','gap-3'));
  document.querySelectorAll('.tl-dot').forEach(el => el.classList.add('mt-1.5','h-3','w-3','rounded-full', role==='owner' ? 'bg-violet-600' : role==='staff' ? 'bg-emerald-600' : 'bg-sky-600'));
  document.querySelectorAll('.tl-body small').forEach(el => el.classList.add('text-xs','text-slate-500'));

  document.querySelectorAll('.alert-pay').forEach(el => el.classList.add('mb-5','flex','items-start','gap-4','rounded-2xl','border','border-amber-200','bg-amber-50','p-4'));
  document.querySelectorAll('.pay-box').forEach(el => el.classList.add('border-sky-200','bg-sky-50','p-6'));
  document.querySelectorAll('.qris-mock').forEach(el => el.classList.add('mx-auto','my-4','flex','h-36','w-36','items-center','justify-center','rounded-xl','border','border-slate-200','bg-white','text-5xl'));

  document.querySelectorAll('.modal-overlay, .custom-modal').forEach(el => el.classList.add('fixed','inset-0','z-[1000]','hidden','items-center','justify-center','bg-slate-900/40','p-5'));
  document.querySelectorAll('.modal-overlay.open, .custom-modal.open').forEach(el => el.classList.remove('hidden'));
})();
</script>

</body>
</html>
