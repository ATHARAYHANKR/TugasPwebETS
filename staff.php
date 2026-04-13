<?php
require_once 'app_config.php';
cleango_boot_session();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php"); exit();
}

$id_staff  = (int)$_SESSION['user_id'];
$staffName = $_SESSION['nama'];
$page      = $_GET['page'] ?? 'dashboard';
$flash     = '';
$flashType = 'success';

// ============================================================
// POST ACTIONS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ft = $_POST['form_type'] ?? '';

    // --- Ambil Order (assign staff, status → Dijemput) ---
    if ($ft === 'ambil_order') {
        $id_order = (int)$_POST['id_order'];
        $pdo->prepare("UPDATE orders SET id_staff=?, status_order='Dijemput', updated_at=NOW() WHERE id_order=?")->execute([$id_staff, $id_order]);
        $pdo->prepare("INSERT INTO tracking (id_order,status,keterangan,updated_by) VALUES (?,'Dijemput','Staff menjemput laundry',?)")->execute([$id_order, $id_staff]);

        // Info order
        $oi = $pdo->prepare("SELECT o.kode_order, u.nama_cust, u.id_cust FROM orders o JOIN users u ON u.id_cust=o.id_cust WHERE o.id_order=?");
        $oi->execute([$id_order]);
        $oi = $oi->fetch();
        if ($oi) {
            sendNotification($pdo, 'customer', $oi['id_cust'],
                '🚗 Laundry Sedang Dijemput!',
                "Order {$oi['kode_order']} sedang dijemput oleh staff kami. Pastikan laundry sudah siap.",
                "customer.php?page=tracking_saya"
            );
            notifyAllOwner($pdo, '🚗 Order Dijemput: '.$oi['kode_order'], "Staff {$staffName} menjemput laundry {$oi['kode_order']} dari {$oi['nama_cust']}.", "owner.php?page=semua_order");
        }
        $flash = "Order berhasil diambil. Status → <strong>Dijemput</strong>.";
        $page  = 'order_masuk';
    }

    // --- Set Berat & Buat Tagihan ---
    if ($ft === 'set_berat') {
        $id_order   = (int)$_POST['id_order'];
        $id_katalog = (int)$_POST['id_katalog'];
        $berat      = (float)($_POST['berat'] ?? 0);
        $qty        = $_POST['qty'] ? (int)$_POST['qty'] : null;
        $harga_sat  = (float)($_POST['harga_satuan'] ?? 0);
        $satuan     = $_POST['satuan'] ?? 'kg';
        $subtotal   = $satuan === 'kg' ? $berat * $harga_sat : ($qty ?? 0) * $harga_sat;

        $pdo->prepare("UPDATE order_detail SET berat=?,qty=?,harga_satuan=?,subtotal=? WHERE id_order=?")->execute([$berat ?: null, $qty, $harga_sat, $subtotal, $id_order]);
        $pdo->prepare("UPDATE orders SET total_harga=?, updated_at=NOW() WHERE id_order=?")->execute([$subtotal, $id_order]);

        // Buat/Update pembayaran
        $cek = $pdo->prepare("SELECT id_bayar FROM pembayaran WHERE id_order=?");
        $cek->execute([$id_order]);
        if ($cek->fetch()) {
            $pdo->prepare("UPDATE pembayaran SET jumlah=?, status_bayar='Pending', updated_at=NOW() WHERE id_order=?")->execute([$subtotal, $id_order]);
        } else {
            $pdo->prepare("INSERT INTO pembayaran (id_order,metode,jumlah,status_bayar) VALUES (?,'QRIS',?,'Pending')")->execute([$id_order, $subtotal]);
        }
        $pdo->prepare("INSERT INTO tracking (id_order,status,keterangan,updated_by) VALUES (?,'Dijemput','Berat diverifikasi, tagihan dikirim ke customer',?)")->execute([$id_order, $id_staff]);

        // Notifikasi ke customer
        $oi = $pdo->prepare("SELECT o.kode_order, u.id_cust, u.nama_cust FROM orders o JOIN users u ON u.id_cust=o.id_cust WHERE o.id_order=?");
        $oi->execute([$id_order]);
        $oi = $oi->fetch();
        if ($oi) {
            sendNotification($pdo, 'customer', $oi['id_cust'],
                '💳 Tagihan Laundry Kamu Sudah Siap!',
                "Order {$oi['kode_order']} — Tagihan sebesar " . rupiah($subtotal) . " sudah dimasukkan. Silakan bayar via QRIS.",
                "customer.php?page=pembayaran"
            );
            notifyAllOwner($pdo, '📊 Tagihan Dibuat: '.$oi['kode_order'], "Staff {$staffName} memasukkan tagihan " . rupiah($subtotal) . " untuk {$oi['nama_cust']}.", "owner.php?page=semua_order");
        }
        $flash = "✅ Berat terverifikasi & tagihan dikirim ke customer. Silakan update status manual sesuai proses cuci.";
        $page  = 'kelola_order';
    }

    // --- Advance Status ---
    if ($ft === 'advance_status') {
        $id_order   = (int)$_POST['id_order'];
        $new_status = $_POST['new_status'] ?? '';
        $keterangan = trim($_POST['keterangan'] ?? '');
        $allowed    = ['Dicuci','Disetrika','Dikirim','Selesai','Dibatalkan'];

        // GUARD: cek apakah pembayaran sudah Lunas sebelum boleh update status
        $cekBayar = $pdo->prepare("SELECT status_bayar FROM pembayaran WHERE id_order=?");
        $cekBayar->execute([$id_order]);
        $bayarRow = $cekBayar->fetch();
        if (!$bayarRow || $bayarRow['status_bayar'] !== 'Lunas') {
            $flash = "❌ Tidak bisa update status. Customer belum melunasi pembayaran untuk order ini.";
            $flashType = 'error';
            header("Location: ?page=kelola_order&flash=".urlencode($flash)); exit();
        }

        if (in_array($new_status, $allowed)) {
            $pdo->prepare("UPDATE orders SET status_order=?, updated_at=NOW() WHERE id_order=?")->execute([$new_status, $id_order]);
            $pdo->prepare("INSERT INTO tracking (id_order,status,keterangan,updated_by) VALUES (?,?,?,?)")->execute([$id_order, $new_status, $keterangan ?: "Status diperbarui oleh staff", $id_staff]);

            // Invoice otomatis jika Selesai
            if ($new_status === 'Selesai') {
                $bayar = $pdo->prepare("SELECT id_bayar FROM pembayaran WHERE id_order=? AND status_bayar='Lunas'");
                $bayar->execute([$id_order]);
                $bayarRow = $bayar->fetch();
                if ($bayarRow) {
                    $noInv = generateNoInvoice($pdo);
                    $cekInv = $pdo->prepare("SELECT id_invoice FROM invoice WHERE id_bayar=?");
                    $cekInv->execute([$bayarRow['id_bayar']]);
                    if (!$cekInv->fetch()) {
                        $noWa = '';
                        $custQ = $pdo->prepare("SELECT u.notelp_cust FROM orders o JOIN users u ON u.id_cust=o.id_cust WHERE o.id_order=?");
                        $custQ->execute([$id_order]);
                        $custR = $custQ->fetch();
                        if ($custR) $noWa = '62' . ltrim($custR['notelp_cust'], '0');
                        $pdo->prepare("INSERT INTO invoice (id_bayar,no_invoice,nomor_wa) VALUES (?,?,?)")->execute([$bayarRow['id_bayar'], $noInv, $noWa]);
                    }
                }
            }

            // Notifikasi ke customer
            $oi = $pdo->prepare("SELECT o.kode_order, u.id_cust FROM orders o JOIN users u ON u.id_cust=o.id_cust WHERE o.id_order=?");
            $oi->execute([$id_order]);
            $oi = $oi->fetch();
            $statusEmoji = ['Dicuci'=>'🧺','Disetrika'=>'✨','Dikirim'=>'🚚','Selesai'=>'✅','Dibatalkan'=>'❌'];
            $ico = $statusEmoji[$new_status] ?? '📦';
            if ($oi) {
                $msg = match($new_status) {
                    'Dicuci'    => "Order {$oi['kode_order']} sedang dicuci. Proses laundry sedang berjalan!",
                    'Disetrika' => "Order {$oi['kode_order']} sedang disetrika. Hampir selesai!",
                    'Dikirim'   => "Order {$oi['kode_order']} sedang dalam perjalanan ke alamatmu. Siap-siap ya!",
                    'Selesai'   => "Order {$oi['kode_order']} sudah selesai dan diterima! Terima kasih sudah menggunakan CleanGo.",
                    'Dibatalkan'=> "Order {$oi['kode_order']} dibatalkan. Hubungi kami jika ada pertanyaan.",
                    default     => "Status order {$oi['kode_order']} diperbarui ke: {$new_status}",
                };
                sendNotification($pdo, 'customer', $oi['id_cust'], $ico . " Update Order: {$new_status}", $msg, "customer.php?page=tracking_saya");
                notifyAllOwner($pdo, $ico . " Order {$oi['kode_order']}: {$new_status}", "Staff {$staffName} mengupdate status ke {$new_status}.", "owner.php?page=semua_order");
            }
            $flash = "Status berhasil diupdate ke <strong>{$new_status}</strong>.";
        }
        $page = 'status_laundry';
    }

    // --- Konfirmasi Pembayaran ---
    if ($ft === 'konfirmasi_bayar') {
        $id_bayar = (int)$_POST['id_bayar'];
        $pdo->prepare("UPDATE pembayaran SET status_bayar='Lunas', dikonfirmasi_oleh=?, waktu_bayar=NOW(), updated_at=NOW() WHERE id_bayar=?")->execute([$id_staff, $id_bayar]);

        // Notifikasi ke customer
        $oi = $pdo->prepare("SELECT o.kode_order, u.id_cust FROM pembayaran p JOIN orders o ON o.id_order=p.id_order JOIN users u ON u.id_cust=o.id_cust WHERE p.id_bayar=?");
        $oi->execute([$id_bayar]);
        $oi = $oi->fetch();
        if ($oi) {
            sendNotification($pdo, 'customer', $oi['id_cust'],
                '✅ Pembayaran Dikonfirmasi!',
                "Pembayaran untuk order {$oi['kode_order']} sudah dikonfirmasi. Laundry kamu sedang diproses!",
                "customer.php?page=tracking_saya"
            );
            notifyAllOwner($pdo, '✅ Bayar Lunas: '.$oi['kode_order'], "Staff {$staffName} mengkonfirmasi pembayaran order {$oi['kode_order']}.", "owner.php?page=semua_order");
        }
        $flash = "Pembayaran berhasil dikonfirmasi.";
        $page  = 'status_laundry';
    }

    header("Location: ?page={$page}" . ($flash ? "&flash=".urlencode($flash) : '')); exit();
}

if (isset($_GET['flash'])) { $flash = urldecode($_GET['flash']); }

// ============================================================
// DATA QUERIES
// ============================================================
$allOrders = $pdo->query("
    SELECT o.*, l.nama_layanan, u.nama_cust, u.notelp_cust,
           od.id_katalog, od.berat, od.qty, od.harga_satuan AS harga_od, od.subtotal,
           k.jenis_layanan, k.varian, k.satuan,
           p.id_bayar, p.jumlah AS jumlah_bayar, p.status_bayar
    FROM orders o
    JOIN layanan l ON l.id_layanan=o.id_layanan
    JOIN users u ON u.id_cust=o.id_cust
    LEFT JOIN order_detail od ON od.id_order=o.id_order
    LEFT JOIN katalog k ON k.id_katalog=od.id_katalog
    LEFT JOIN pembayaran p ON p.id_order=o.id_order
    WHERE o.status_order NOT IN ('Selesai','Dibatalkan')
    ORDER BY o.tanggal_pesan ASC
")->fetchAll();

$ordersMasuk    = array_filter($allOrders, fn($o) => $o['status_order']==='Menunggu Konfirmasi');
$ordersDiproses = array_filter($allOrders, fn($o) => in_array($o['status_order'], ['Dijemput','Dicuci','Disetrika','Dikirim']));
$ordersNeedWeight = array_filter($allOrders, fn($o) => $o['status_order']==='Dijemput' && (!$o['total_harga'] || $o['total_harga']==0));
$ordersPaid     = array_filter($allOrders, fn($o) => $o['status_bayar']==='Lunas' && in_array($o['status_order'], ['Dijemput','Dicuci','Disetrika','Dikirim']));
// Order yang sudah ada tagihan tapi BELUM dibayar customer — hanya info, tidak bisa update status
$ordersTagihanDikirim = array_filter($ordersDiproses, fn($o) =>
    !in_array($o['id_order'], array_column($ordersNeedWeight,'id_order')) &&
    !in_array($o['id_order'], array_column($ordersPaid,'id_order'))
);
$ordersReady    = $ordersPaid;
$ordersStatusLaundry = $ordersPaid;
$statusColumn = $pdo->prepare("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tracking' AND COLUMN_NAME='status'");
$statusColumn->execute();
$statusColumnType = $statusColumn->fetchColumn();
$trackingStatuses = [];
if ($statusColumnType) {
    preg_match_all("/'([^']*)'/", $statusColumnType, $statusMatches);
    $trackingStatuses = $statusMatches[1];
}
$ordersKonfBayar= $pdo->query("SELECT p.*, o.kode_order, u.nama_cust, l.nama_layanan FROM pembayaran p JOIN orders o ON o.id_order=p.id_order JOIN users u ON u.id_cust=o.id_cust JOIN layanan l ON l.id_layanan=o.id_layanan WHERE p.status_bayar='Menunggu Konfirmasi'")->fetchAll();
$ordersSelesai  = $pdo->query("SELECT o.*, l.nama_layanan, u.nama_cust FROM orders o JOIN layanan l ON l.id_layanan=o.id_layanan JOIN users u ON u.id_cust=o.id_cust WHERE o.status_order='Selesai' AND o.id_staff={$id_staff} ORDER BY o.updated_at DESC LIMIT 20")->fetchAll();
$katalogAll     = $pdo->query("SELECT k.*, l.nama_layanan FROM katalog k JOIN layanan l ON l.id_layanan=k.id_layanan WHERE k.status='Aktif'")->fetchAll();

$selId = (int)($_GET['id'] ?? 0);
$selOrder = null;
if ($selId) {
    $sq = $pdo->prepare("
        SELECT o.*, l.nama_layanan, u.nama_cust, u.notelp_cust, u.alamat_cust,
               od.id_katalog, od.berat, od.qty, od.harga_satuan AS harga_od, od.subtotal,
               k.jenis_layanan, k.varian, k.satuan, k.harga AS harga_katalog,
               p.status_bayar
        FROM orders o JOIN layanan l ON l.id_layanan=o.id_layanan
        JOIN users u ON u.id_cust=o.id_cust
        LEFT JOIN order_detail od ON od.id_order=o.id_order
        LEFT JOIN katalog k ON k.id_katalog=od.id_katalog
        LEFT JOIN pembayaran p ON p.id_order=o.id_order
        WHERE o.id_order=?
    ");
    $sq->execute([$selId]);
    $selOrder = $sq->fetch();
}

// Stats
$statMasuk    = count($ordersMasuk);
$statDiproses = count($ordersDiproses);
$statBayar    = count($ordersKonfBayar);
$unreadCount  = countUnread($pdo, 'staff', $id_staff);

$nextStatusMap  = ['Dijemput'=>'Dicuci','Dicuci'=>'Disetrika','Disetrika'=>'Dikirim','Dikirim'=>'Selesai'];
$nextLabelMap   = ['Dijemput'=>'Lanjut ke Dicuci','Dicuci'=>'Lanjut ke Disetrika','Disetrika'=>'Tandai Dikirim','Dikirim'=>'Selesai'];

function statusBadge($s) {
    return match($s) {
        'Menunggu Konfirmasi'=>'badge-warning','Dijemput'=>'badge-blue',
        'Dicuci'=>'badge-process','Disetrika'=>'badge-purple',
        'Dikirim'=>'badge-cyan','Selesai'=>'badge-success',
        'Dibatalkan'=>'badge-danger','Lunas'=>'badge-success',
        'Pending'=>'badge-warning','Menunggu Konfirmasi'=>'badge-yellow',
        default=>'badge-default'
    };
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff - CleanGo Laundry</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','Segoe UI','sans-serif']}}}}</script>
</head>
<body data-role="staff">
<div class="sidebar">
  <div class="logo">
    <div class="logo-icon"><i class="fas fa-hard-hat"></i></div>
    <div><div class="logo-text">CleanGo</div><div class="logo-sub">Staff Panel</div></div>
  </div>
  <nav>
    <a href="?page=dashboard" class="<?= $page==='dashboard'?'active':'' ?>"><i class="fas fa-home"></i> Dashboard</a>
    <a href="?page=order_masuk" class="<?= $page==='order_masuk'?'active':'' ?>">
      <i class="fas fa-inbox"></i> Order Masuk
      <?php if($statMasuk>0): ?><span class="notif-badge-nav"><?= $statMasuk ?></span><?php endif; ?>
    </a>
    <a href="?page=kelola_order" class="<?= $page==='kelola_order'?'active':'' ?>">
      <i class="fas fa-tasks"></i> Kelola Order
      <?php if(count($ordersNeedWeight)>0): ?><span class="notif-badge-nav"><?= count($ordersNeedWeight) ?></span><?php endif; ?>
    </a>
    <a href="?page=status_laundry" class="<?= $page==='status_laundry'?'active':'' ?>">
      <i class="fas fa-soap"></i> Update Status
      <?php if(count($ordersStatusLaundry)>0): ?><span class="notif-badge-nav"><?= count($ordersStatusLaundry) ?></span><?php endif; ?>
    </a>
    <a href="?page=konfirmasi_bayar" class="<?= $page==='konfirmasi_bayar'?'active':'' ?>">
      <i class="fas fa-check-circle"></i> Konfirmasi Bayar
      <?php if($statBayar>0): ?><span class="notif-badge-nav"><?= $statBayar ?></span><?php endif; ?>
    </a>
    <a href="?page=history" class="<?= $page==='history'?'active':'' ?>"><i class="fas fa-history"></i> Riwayat Selesai</a>
  </nav>
  <div class="sidebar-bottom">
    <div class="user-card">
      <div class="avatar"><?= mb_strtoupper(mb_substr($staffName,0,1)) ?></div>
      <div class="user-info"><div class="name"><?= htmlspecialchars($staffName) ?></div><div class="role">Staff</div></div>
    </div>
    <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Keluar</a>
  </div>
</div>

<div class="main">
  <div class="topbar">
    <div class="topbar-title"><i class="fas fa-soap" style="color:#059669;margin-right:8px"></i>CleanGo — Staff Dashboard</div>
    <div class="topbar-right">
      <span style="font-size:13px;color:#64748b">Halo, <strong><?= htmlspecialchars($staffName) ?></strong>!</span>
    </div>
  </div>

  <div class="content">
    <?php if ($flash): ?>
    <div class="flash success"><i class="fas fa-check-circle"></i> <?= $flash ?></div>
    <?php endif; ?>

    <?php // DASHBOARD
    if ($page === 'dashboard'): ?>
    <div class="page-header">
      <h1>👷 Dashboard Staff</h1>
      <p>Selamat bekerja, <?= htmlspecialchars($staffName) ?>! Berikut ringkasan kerja hari ini.</p>
    </div>
    <div class="stats-grid">
      <div class="stat-card <?= $statMasuk>0?'alert-stat':'' ?>">
        <div class="label">Order Masuk</div>
        <div class="value"><?= $statMasuk ?></div>
        <div class="sub">Menunggu konfirmasi</div>
      </div>
      <div class="stat-card">
        <div class="label">Sedang Diproses</div>
        <div class="value"><?= $statDiproses ?></div>
        <div class="sub">Sedang dikerjakan</div>
      </div>
      <div class="stat-card <?= count($ordersReady)>0?'alert-stat':'' ?>">
        <div class="label">Siap Proses</div>
        <div class="value"><?= count($ordersReady) ?></div>
        <div class="sub">Sudah dibayar</div>
      </div>
      <div class="stat-card <?= $statBayar>0?'alert-stat':'' ?>">
        <div class="label">Konfirmasi Bayar</div>
        <div class="value"><?= $statBayar ?></div>
        <div class="sub">Menunggu verifikasi</div>
      </div>
      <div class="stat-card">
        <div class="label">Selesai (Saya)</div>
        <div class="value"><?= count($ordersSelesai) ?></div>
        <div class="sub">Total by saya</div>
      </div>
    </div>

    <?php if ($statMasuk > 0): ?>
    <div class="card" style="border:2px solid #f59e0b">
      <div class="card-header" style="background:#fffbeb"><h3>⚡ Order Baru — Perlu Aksi!</h3><a href="?page=order_masuk" class="btn btn-warning btn-sm">Lihat Semua</a></div>
      <table>
        <thead><tr><th>Kode</th><th>Customer</th><th>Layanan</th><th>Jadwal Jemput</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach(array_slice($ordersMasuk,0,5) as $o): ?>
        <tr>
          <td><strong><?= htmlspecialchars($o['kode_order']) ?></strong></td>
          <td><?= htmlspecialchars($o['nama_cust']) ?><br><small style="color:#94a3b8"><?= htmlspecialchars($o['notelp_cust']) ?></small></td>
          <td><?= htmlspecialchars($o['nama_layanan']) ?> <?php if($o['varian']): ?><span class="badge <?= $o['varian']==='Express' ? 'badge-express' : 'badge-blue' ?>"><?= $o['varian'] ?></span><?php endif; ?></td>
          <td><?= $o['jadwal_jemput'] ? date('d/m/Y H:i', strtotime($o['jadwal_jemput'])) : '-' ?></td>
          <td>
            <form method="POST" style="display:inline">
              <input type="hidden" name="form_type" value="ambil_order">
              <input type="hidden" name="id_order" value="<?= $o['id_order'] ?>">
              <button class="btn btn-primary btn-sm"><i class="fas fa-hand-paper"></i> Ambil Order</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php if (count($ordersReady) > 0): ?>
    <div class="card" style="border:2px solid #0ea5e9">
      <div class="card-header"><h3>🧺 Laundry Siap Dilanjutkan</h3><a href="?page=status_laundry" class="btn btn-primary btn-sm">Lihat Semua</a></div>
      <table>
        <thead><tr><th>Kode</th><th>Customer</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach(array_slice($ordersReady,0,4) as $o): ?>
        <tr>
          <td><strong><?= htmlspecialchars($o['kode_order']) ?></strong></td>
          <td><?= htmlspecialchars($o['nama_cust']) ?></td>
          <td><span class="badge <?= statusBadge($o['status_order']) ?>" style="font-size:12px;padding:4px 10px"><?= $o['status_order'] ?></span></td>
          <td>
            <form method="POST" style="display:inline">
              <input type="hidden" name="form_type" value="advance_status">
              <input type="hidden" name="id_order" value="<?= $o['id_order'] ?>">
              <input type="hidden" name="new_status" value="<?= $nextStatusMap[$o['status_order']] ?>">
              <button class="btn btn-primary btn-sm"><i class="fas fa-arrow-right"></i> <?= $nextLabelMap[$o['status_order']] ?></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php if ($statBayar > 0): ?>
    <div class="card" style="border:2px solid #10b981">
      <div class="card-header"><h3>💳 Konfirmasi Pembayaran Menunggu</h3><a href="?page=konfirmasi_bayar" class="btn btn-success btn-sm">Lihat Semua</a></div>
      <table>
        <thead><tr><th>Kode</th><th>Customer</th><th>Tagihan</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach(array_slice($ordersKonfBayar,0,3) as $b): ?>
        <tr>
          <td><strong><?= htmlspecialchars($b['kode_order']) ?></strong></td>
          <td><?= htmlspecialchars($b['nama_cust']) ?></td>
          <td style="font-weight:700;color:#059669"><?= rupiah($b['jumlah']) ?></td>
          <td>
            <form method="POST" style="display:inline">
              <input type="hidden" name="form_type" value="konfirmasi_bayar">
              <input type="hidden" name="id_bayar" value="<?= $b['id_bayar'] ?>">
              <button class="btn btn-success btn-sm"><i class="fas fa-check"></i> Konfirmasi Lunas</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php // ORDER MASUK
    elseif ($page === 'order_masuk'): ?>
    <div class="page-header">
      <h1>📥 Order Masuk</h1>
      <p>Order baru dari customer yang menunggu konfirmasi staff.</p>
    </div>
    <?php if (empty($ordersMasuk)): ?>
    <div style="text-align:center;padding:60px;color:#94a3b8"><i class="fas fa-inbox" style="font-size:50px;display:block;margin-bottom:16px"></i>Tidak ada order masuk saat ini.</div>
    <?php else: ?>
    <?php foreach($ordersMasuk as $o): ?>
    <div class="order-card urgent">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:14px">
        <div>
          <h3 style="font-size:17px"><?= htmlspecialchars($o['kode_order']) ?></h3>
          <p style="color:#64748b;font-size:13px"><?= date('d/m/Y H:i', strtotime($o['tanggal_pesan'])) ?></p>
        </div>
        <span class="badge badge-warning" style="font-size:13px">⏳ Menunggu Konfirmasi</span>
      </div>
      <div class="info-grid">
        <div class="info-box"><div class="label">Customer</div><div class="val"><?= htmlspecialchars($o['nama_cust']) ?></div><div style="font-size:12px;color:#64748b;margin-top:2px"><?= htmlspecialchars($o['notelp_cust']) ?></div></div>
        <div class="info-box"><div class="label">Layanan</div><div class="val"><?= htmlspecialchars($o['nama_layanan']) ?><?php if($o['varian']): ?> <span class="badge <?= $o['varian']==='Express' ? 'badge-express' : 'badge-blue' ?>"><?= $o['varian'] ?></span><?php endif; ?></div></div>
        <div class="info-box"><div class="label">Alamat Penjemputan</div><div class="val" style="font-size:13px"><?= nl2br(htmlspecialchars($o['alamat_penjemputan'])) ?></div></div>
        <div class="info-box"><div class="label">Jadwal Jemput</div><div class="val"><?= $o['jadwal_jemput'] ? date('d F Y H:i', strtotime($o['jadwal_jemput'])) : '-' ?></div></div>
        <?php if($o['catatan']): ?><div class="info-box full" style="grid-column:1/-1"><div class="label">Catatan Customer</div><div class="val" style="font-weight:400"><?= htmlspecialchars($o['catatan']) ?></div></div><?php endif; ?>
      </div>
      <form method="POST">
        <input type="hidden" name="form_type" value="ambil_order">
        <input type="hidden" name="id_order" value="<?= $o['id_order'] ?>">
        <button class="btn btn-primary"><i class="fas fa-hand-paper"></i> Ambil & Jemput Order Ini</button>
      </form>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php // KELOLA ORDER / TRACKING
    elseif ($page === 'kelola_order'): ?>
    <div class="page-header">
      <h1>⚙️ Kelola Order</h1>
      <p>Verifikasi berat & update status order yang sedang diproses.</p>
    </div>
    <?php if ($selId && $selOrder): ?>
    <!-- Detail Kelola Order -->
    <a href="?page=kelola_order" class="btn btn-outline btn-sm" style="margin-bottom:20px"><i class="fas fa-arrow-left"></i> Kembali</a>
    <div class="card" style="padding:28px 32px">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px">
        <div>
          <h2 style="font-size:22px;font-weight:800;color:#1e293b;margin-bottom:4px"><?= htmlspecialchars($selOrder['kode_order']) ?></h2>
          <p style="color:#64748b;font-size:13px">Customer: <strong><?= htmlspecialchars($selOrder['nama_cust']) ?></strong> · <?= htmlspecialchars($selOrder['notelp_cust']) ?></p>
        </div>
        <span class="badge <?= statusBadge($selOrder['status_order']) ?>" style="font-size:13px;padding:6px 16px"><?= $selOrder['status_order'] ?></span>
      </div>

      <div class="info-grid" style="margin-bottom:24px">
        <div class="info-box" style="padding:14px 16px">
          <div class="label" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;margin-bottom:4px">Layanan</div>
          <div class="val" style="font-weight:600;color:#1e293b"><?= htmlspecialchars($selOrder['nama_layanan']) ?><?php if($selOrder['varian']): ?> · <span style="color:#64748b"><?= $selOrder['varian'] ?></span><?php endif; ?></div>
        </div>
        <div class="info-box" style="padding:14px 16px">
          <div class="label" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;margin-bottom:4px">Harga Katalog</div>
          <div class="val" style="font-weight:600;color:#1e293b"><?= rupiah($selOrder['harga_katalog'] ?? 0) ?>/<?= $selOrder['satuan'] ?? 'kg' ?></div>
        </div>
        <div class="info-box" style="padding:14px 16px">
          <div class="label" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;margin-bottom:4px">Alamat</div>
          <div class="val" style="font-size:13px;color:#334155"><?= nl2br(htmlspecialchars($selOrder['alamat_penjemputan'])) ?></div>
        </div>
        <div class="info-box" style="padding:14px 16px">
          <div class="label" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;margin-bottom:4px">Jadwal Jemput</div>
          <div class="val" style="font-weight:600;color:#1e293b"><?= $selOrder['jadwal_jemput'] ? date('d/m/Y H:i', strtotime($selOrder['jadwal_jemput'])) : '-' ?></div>
        </div>
      </div>

      <!-- Form Set Berat (jika Dijemput) -->
      <?php if ($selOrder['status_order']==='Dijemput'): ?>
      <div style="border:2px solid #f59e0b;background:#fffbeb;border-radius:16px;padding:24px 28px;margin-top:8px">
        <h3 style="font-size:16px;font-weight:700;color:#92400e;display:flex;align-items:center;gap:8px;margin-bottom:6px">
          <span>⚖️</span> Input Berat & Buat Tagihan
        </h3>
        <p style="font-size:13px;color:#b45309;margin-bottom:20px">Masukkan berat atau jumlah item untuk menghitung tagihan customer.</p>
        <form method="POST">
          <input type="hidden" name="form_type" value="set_berat">
          <input type="hidden" name="id_order" value="<?= $selOrder['id_order'] ?>">
          <input type="hidden" name="id_katalog" value="<?= $selOrder['id_katalog'] ?>">
          <input type="hidden" name="satuan" value="<?= $selOrder['satuan'] ?? 'kg' ?>">
          <div class="form-grid" style="margin-bottom:20px">
            <?php if(($selOrder['satuan'] ?? 'kg') === 'kg'): ?>
            <div class="field">
              <label style="font-size:13px;font-weight:600;color:#78350f;display:block;margin-bottom:6px">Berat (kg) *</label>
              <input type="number" step="0.1" min="0.1" name="berat" id="inputBerat" placeholder="Contoh: 3.5" required
                onchange="hitungTotal()" oninput="hitungTotal()"
                style="width:100%;border-radius:10px;border:1px solid #fcd34d;background:#fff;padding:10px 14px;font-size:14px;outline:none">
            </div>
            <?php else: ?>
            <div class="field">
              <label style="font-size:13px;font-weight:600;color:#78350f;display:block;margin-bottom:6px">Jumlah (pcs) *</label>
              <input type="number" min="1" name="qty" id="inputBerat" placeholder="Contoh: 2" required
                onchange="hitungTotal()" oninput="hitungTotal()"
                style="width:100%;border-radius:10px;border:1px solid #fcd34d;background:#fff;padding:10px 14px;font-size:14px;outline:none">
            </div>
            <?php endif; ?>
            <div class="field">
              <label style="font-size:13px;font-weight:600;color:#78350f;display:block;margin-bottom:6px">Total (Rp)</label>
              <input type="text" id="displayTotal" placeholder="0" readonly
                style="width:100%;border-radius:10px;border:1px solid #fcd34d;background:#fef9c3;padding:10px 14px;font-size:14px;outline:none;cursor:not-allowed;color:#92400e;font-weight:700">
              <!-- hidden fields yang dikirim ke server -->
              <input type="hidden" name="harga_satuan" id="hargaKatalog" value="<?= $selOrder['harga_katalog'] ?? 0 ?>">
            </div>
          </div>
          <script>
          function hitungTotal() {
            var berat  = parseFloat(document.getElementById('inputBerat')?.value) || 0;
            var harga  = parseFloat(document.getElementById('hargaKatalog')?.value) || 0;
            var total  = berat * harga;
            var disp   = document.getElementById('displayTotal');
            if (disp) disp.value = total > 0 ? 'Rp ' + total.toLocaleString('id-ID') : '';
          }
          // Init jika sudah ada nilai berat
          document.addEventListener('DOMContentLoaded', hitungTotal);
          </script>
          <button type="submit" style="display:inline-flex;align-items:center;gap:8px;background:#f59e0b;hover:background:#d97706;color:#fff;font-weight:700;font-size:14px;padding:11px 22px;border-radius:10px;border:none;cursor:pointer">
            <i class="fas fa-receipt"></i> Kirim Tagihan ke Customer
          </button>
        </form>
      </div>
      <?php endif; ?>

    </div>

    <?php else: ?>
    <!-- Daftar order diproses -->
    <?php if (count($ordersNeedWeight) > 0): ?>
    <div class="card" style="border:2px solid #f59e0b">
      <div class="card-header"><h3>⚖️ Perlu Verifikasi Berat</h3></div>
      <table>
        <thead><tr><th>Kode</th><th>Customer</th><th>Layanan</th><th>Total</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach($ordersNeedWeight as $o): ?>
        <tr>
          <td><strong><?= htmlspecialchars($o['kode_order']) ?></strong></td>
          <td><?= htmlspecialchars($o['nama_cust']) ?></td>
          <td><?= htmlspecialchars($o['nama_layanan']) ?></td>
          <td><span style="color:#94a3b8">Menunggu berat</span></td>
          <td><a href="?page=kelola_order&id=<?= $o['id_order'] ?>" class="btn btn-warning btn-sm"><i class="fas fa-scale-balanced"></i> Input Berat</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php if (count($ordersTagihanDikirim) > 0): ?>
    <div class="card" style="border:1px solid #e2e8f0">
      <div class="card-header">
        <h3 style="display:flex;align-items:center;gap:8px">
          ⏳ Menunggu Pembayaran Customer (<?= count($ordersTagihanDikirim) ?>)
        </h3>
      </div>
      <table>
        <thead><tr><th>KODE</th><th>CUSTOMER</th><th>LAYANAN</th><th>STATUS</th><th>TOTAL</th><th>AKSI</th></tr></thead>
        <tbody>
        <?php foreach($ordersTagihanDikirim as $o): ?>
        <tr>
          <td><strong><?= htmlspecialchars($o['kode_order']) ?></strong></td>
          <td><?= htmlspecialchars($o['nama_cust']) ?></td>
          <td><?= htmlspecialchars($o['nama_layanan']) ?></td>
          <td><span class="badge <?= statusBadge($o['status_order']) ?>"><?= $o['status_order'] ?></span></td>
          <td><?= $o['total_harga']>0 ? '<strong style="color:#0369a1">'.rupiah($o['total_harga']).'</strong>' : '<span style="color:#94a3b8">Menunggu input</span>' ?></td>
          <td>
            <span style="display:inline-flex;align-items:center;gap:6px;background:#fef3c7;color:#92400e;font-size:12px;font-weight:600;padding:6px 12px;border-radius:8px;border:1px solid #fde68a">
              <i class="fas fa-lock"></i> Belum Dibayar
            </span>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php if (empty($ordersNeedWeight) && empty($ordersPaid) && empty($ordersTagihanDikirim)): ?>
    <div style="text-align:center;padding:60px;color:#94a3b8"><i class="fas fa-tasks" style="font-size:50px;display:block;margin-bottom:16px"></i>Tidak ada order yang sedang diproses.</div>
    <?php endif; ?>
    <?php endif; ?>

    <?php elseif ($page === 'status_laundry'): ?>
    <div class="page-header">
      <h1>🧺 Update Status Laundry</h1>
      <p>Kelola proses cuci, setrika, kirim, dan selesai setelah pembayaran dikonfirmasi.</p>
    </div>

    <?php if ($selOrder && $selOrder['status_bayar']==='Lunas' && in_array($selOrder['status_order'], ['Dijemput','Dicuci','Disetrika','Dikirim'])): ?>
    <a href="?page=status_laundry" class="btn btn-outline btn-sm" style="margin-bottom:16px"><i class="fas fa-arrow-left"></i> Kembali</a>
    <div class="card" style="padding:24px">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:20px">
        <div>
          <h2 style="font-size:20px"><?= htmlspecialchars($selOrder['kode_order']) ?></h2>
          <p style="color:#64748b;font-size:13px">Customer: <strong><?= htmlspecialchars($selOrder['nama_cust']) ?></strong> · <?= htmlspecialchars($selOrder['notelp_cust']) ?></p>
        </div>
        <span class="badge <?= statusBadge($selOrder['status_order']) ?>" style="font-size:13px;padding:6px 14px"><?= $selOrder['status_order'] ?></span>
      </div>

      <div class="info-grid">
        <div class="info-box"><div class="label">Layanan</div><div class="val"><?= htmlspecialchars($selOrder['nama_layanan']) ?><?php if($selOrder['varian']): ?> · <?= $selOrder['varian'] ?><?php endif; ?></div></div>
        <div class="info-box"><div class="label">Total Tagihan</div><div class="val"><?= rupiah($selOrder['total_harga']) ?></div></div>
        <div class="info-box"><div class="label">Alamat</div><div class="val" style="font-size:13px"><?= nl2br(htmlspecialchars($selOrder['alamat_penjemputan'])) ?></div></div>
        <div class="info-box"><div class="label">Jadwal Jemput</div><div class="val"><?= $selOrder['jadwal_jemput'] ? date('d/m/Y H:i', strtotime($selOrder['jadwal_jemput'])) : '-' ?></div></div>
      </div>

      <?php
      $nextStatuses = [];
      if ($selOrder && !empty($trackingStatuses)) {
          $currentIndex = array_search($selOrder['status_order'], $trackingStatuses, true);
          if ($currentIndex !== false) {
              $nextStatuses = array_slice($trackingStatuses, $currentIndex + 1);
          }
      }
      ?>
      <?php if (!empty($nextStatuses)): ?>
      <div style="display:flex;align-items:center;gap:12px;padding:16px;background:#f0f9ff;border:1px solid #93c5fd;border-radius:12px;margin-bottom:20px">
        <i class="fas fa-info-circle" style="color:#0f766e;font-size:16px"></i>
        <p style="font-size:13px;color:#0f766e;margin:0">Order ini sudah lunas. Klik tombol di bawah untuk update status.</p>
      </div>
      <button type="button" class="btn btn-primary open-status-update-modal" data-order-id="<?= $selOrder['id_order'] ?>" data-next-statuses="<?= htmlspecialchars(json_encode($nextStatuses)) ?>">
        <i class="fas fa-pencil-alt"></i> Update Status
      </button>
      <?php endif; ?>
    </div>

    <?php else: ?>
    <?php if (empty($ordersStatusLaundry)): ?>
    <div style="text-align:center;padding:60px;color:#94a3b8"><i class="fas fa-soap" style="font-size:50px;display:block;margin-bottom:16px"></i>Belum ada laundry lunas yang siap diupdate statusnya.</div>
    <?php else: ?>
    <div class="card" style="border:2px solid #0ea5e9">
      <div class="card-header"><h3>🧺 Daftar Laundry Siap Diproses (<?= count($ordersStatusLaundry) ?>)</h3></div>
      <table>
        <thead><tr><th>Kode</th><th>Customer</th><th>Layanan</th><th>Status</th><th>Total</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach($ordersStatusLaundry as $o): ?>
        <?php
          $nextStatusesForLaundry = [];
          if (!empty($trackingStatuses)) {
            $currentIdx = array_search($o['status_order'], $trackingStatuses, true);
            if ($currentIdx !== false) {
              $nextStatusesForLaundry = array_slice($trackingStatuses, $currentIdx + 1);
            }
          }
        ?>
        <tr>
          <td><strong><?= htmlspecialchars($o['kode_order']) ?></strong></td>
          <td><?= htmlspecialchars($o['nama_cust']) ?></td>
          <td><?= htmlspecialchars($o['nama_layanan']) ?></td>
          <td><span class="badge <?= statusBadge($o['status_order']) ?>"><?= $o['status_order'] ?></span></td>
          <td><?= rupiah($o['total_harga']) ?></td>
          <td><button type="button" style="display:inline-flex;align-items:center;gap:6px;background:#059669;color:#fff;font-size:13px;font-weight:600;padding:8px 14px;border-radius:8px;border:none;cursor:pointer" class="open-order-status-modal" data-order-id="<?= $o['id_order'] ?>" data-order-kode="<?= htmlspecialchars($o['kode_order']) ?>" data-customer-name="<?= htmlspecialchars($o['nama_cust']) ?>" data-service-name="<?= htmlspecialchars($o['nama_layanan']) ?>" data-current-status="<?= htmlspecialchars($o['status_order']) ?>" data-next-statuses="<?= htmlspecialchars(json_encode($nextStatusesForLaundry)) ?>"><i class="fas fa-pencil-alt"></i> Pilih Status</button></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php elseif ($page === 'konfirmasi_bayar'): ?>
    <div class="page-header">
      <h1>💳 Konfirmasi Pembayaran</h1>
      <p>Verifikasi pembayaran customer dan konfirmasi status lunas.</p>
    </div>
    <?php if (empty($ordersKonfBayar)): ?>
    <div style="text-align:center;padding:60px;color:#94a3b8"><i class="fas fa-check-circle" style="font-size:50px;display:block;margin-bottom:16px;color:#d1fae5"></i>Tidak ada pembayaran yang perlu dikonfirmasi.</div>
    <?php else: ?>
    <?php foreach($ordersKonfBayar as $b): ?>
    <div class="order-card" style="border-left-color:#059669;border-left-width:4px">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:14px">
        <div>
          <h3 style="font-size:17px;font-weight:700;color:#1e293b;margin-bottom:4px"><?= htmlspecialchars($b['kode_order']) ?></h3>
          <p style="font-size:13px;color:#64748b">Customer: <strong style="color:#334155"><?= htmlspecialchars($b['nama_cust']) ?></strong></p>
        </div>
        <div style="text-align:right">
          <div style="font-size:22px;font-weight:800;color:#059669;margin-bottom:4px"><?= rupiah($b['jumlah']) ?></div>
          <span class="badge badge-yellow" style="font-size:12px">Menunggu Konfirmasi</span>
        </div>
      </div>
      <div style="display:flex;gap:8px;align-items:center;background:#f8fafc;padding:10px 14px;border-radius:10px;margin-bottom:16px">
        <i class="fas fa-info-circle" style="color:#94a3b8;font-size:14px"></i>
        <span style="font-size:13px;color:#475569">Layanan: <strong><?= htmlspecialchars($b['nama_layanan']) ?></strong> · Metode: <strong><?= $b['metode'] ?></strong></span>
        <?php if($b['catatan']): ?><span style="font-size:13px;color:#64748b;margin-left:8px">· <?= htmlspecialchars($b['catatan']) ?></span><?php endif; ?>
      </div>
      <form method="POST">
        <input type="hidden" name="form_type" value="konfirmasi_bayar">
        <input type="hidden" name="id_bayar" value="<?= $b['id_bayar'] ?>">
        <button type="submit" style="display:inline-flex;align-items:center;gap:8px;background:#059669;color:#fff;font-size:14px;font-weight:700;padding:11px 22px;border-radius:10px;border:none;cursor:pointer">
          <i class="fas fa-check-circle"></i> Konfirmasi Pembayaran LUNAS
        </button>
      </form>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php // RIWAYAT SELESAI
    elseif ($page === 'history'): ?>
    <div class="page-header">
      <h1>✅ Riwayat Selesai</h1>
      <p>Order yang sudah selesai ditangani oleh kamu.</p>
    </div>
    <div class="card">
      <div class="card-header"><h3>Order Selesai (<?= count($ordersSelesai) ?>)</h3></div>
      <?php if(empty($ordersSelesai)): ?>
      <div style="padding:40px;text-align:center;color:#94a3b8">Belum ada order selesai.</div>
      <?php else: ?>
      <table>
        <thead><tr><th>Kode</th><th>Customer</th><th>Layanan</th><th>Total</th><th>Selesai</th></tr></thead>
        <tbody>
        <?php foreach($ordersSelesai as $o): ?>
        <tr>
          <td><strong><?= htmlspecialchars($o['kode_order']) ?></strong></td>
          <td><?= htmlspecialchars($o['nama_cust']) ?></td>
          <td><?= htmlspecialchars($o['nama_layanan']) ?></td>
          <td style="font-weight:700"><?= rupiah($o['total_harga']) ?></td>
          <td><?= date('d/m/Y H:i', strtotime($o['updated_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="custom-modal" id="confirmModal">
  <div class="modal-panel">
    <button type="button" class="modal-close" aria-label="Tutup">×</button>
    <h2>Konfirmasi Pembayaran</h2>
    <p>Apakah kamu yakin ingin mengonfirmasi pembayaran ini sebagai <strong>LUNAS</strong>?</p>
    <div class="modal-actions">
      <button type="button" class="btn btn-outline modal-close">Batal</button>
      <button type="button" class="btn btn-success" id="confirmModalSubmitBtn">Oke</button>
    </div>
  </div>
</div>

<div class="custom-modal" id="statusUpdateModal">
  <div class="modal-panel" style="max-width:500px;border-radius:20px;padding:32px">
    <button type="button" class="modal-close" aria-label="Tutup" style="position:absolute;top:16px;right:20px;background:none;border:none;font-size:22px;color:#94a3b8;cursor:pointer;line-height:1">×</button>
    <h2 style="font-size:18px;font-weight:800;margin-bottom:20px;color:#1e293b">Update Status — <span id="modalOrderKodeTitle" style="color:#0ea5e9">-</span></h2>
    <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:12px;padding:16px;margin-bottom:22px;font-size:13px">
      <div style="margin-bottom:10px;color:#334155"><strong style="color:#0369a1">Customer:</strong> <span id="modalCustomerName" style="color:#1e293b">-</span></div>
      <div style="margin-bottom:10px;color:#334155"><strong style="color:#0369a1">Layanan:</strong> <span id="modalServiceName" style="color:#1e293b">-</span></div>
      <div style="color:#334155"><strong style="color:#0369a1">Status Saat Ini:</strong> <span id="modalCurrentStatus" style="color:#0f766e;font-weight:600">-</span></div>
    </div>
    <form method="POST" id="statusUpdateForm">
      <input type="hidden" name="form_type" value="advance_status">
      <input type="hidden" name="id_order" value="">
      <div id="statusSelectField" style="margin-bottom:22px">
        <label style="font-size:13px;font-weight:600;color:#475569;display:block;margin-bottom:8px">Pilih Status Baru</label>
        <select name="new_status" required style="width:100%;border-radius:10px;border:1px solid #cbd5e1;padding:11px 14px;font-size:14px;color:#1e293b;background:#fff;outline:none">
          <option value="" disabled selected>-- Pilih status baru --</option>
        </select>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end">
        <button type="button" class="btn btn-outline modal-close" style="border-radius:10px;padding:10px 20px;font-size:14px">Batal</button>
        <button type="button" class="btn btn-primary" id="statusUpdateBtn" style="border-radius:10px;padding:10px 24px;font-size:14px;font-weight:700">Update Status</button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  const modal = document.getElementById('editKatalogModal');
  if (modal) {
    const closeButtons = modal.querySelectorAll('.modal-close');
    closeButtons.forEach(btn => btn.addEventListener('click', () => modal.classList.remove('open')));
  }

  const confirmModal = document.getElementById('confirmModal');
  const confirmSubmit = document.getElementById('confirmModalSubmitBtn');
  let activeConfirmForm = null;

  if (confirmModal && confirmSubmit) {
    const confirmButtons = document.querySelectorAll('.confirm-pay-btn');
    const closeButtons = confirmModal.querySelectorAll('.modal-close');

    confirmButtons.forEach(btn => {
      btn.addEventListener('click', function() {
        activeConfirmForm = this.closest('form');
        confirmModal.classList.add('open');
      });
    });

    closeButtons.forEach(btn => btn.addEventListener('click', () => confirmModal.classList.remove('open')));
    confirmModal.addEventListener('click', function(event) {
      if (event.target === this) {
        confirmModal.classList.remove('open');
      }
    });

    confirmSubmit.addEventListener('click', function() {
      if (activeConfirmForm) {
        activeConfirmForm.submit();
      }
    });
  }

  const statusUpdateModal = document.getElementById('statusUpdateModal');
  const statusUpdateForm = document.getElementById('statusUpdateForm');
  const statusUpdateBtn = document.getElementById('statusUpdateBtn');
  const statusSelectField = document.getElementById('statusSelectField');

  if (statusUpdateModal && statusUpdateBtn) {
    const openStatusButtons = document.querySelectorAll('.open-status-update-modal, .open-order-status-modal');
    const closeButtons = statusUpdateModal.querySelectorAll('.modal-close');

    openStatusButtons.forEach(btn => {
      btn.addEventListener('click', function() {
        const orderId = this.getAttribute('data-order-id');
        const nextStatuses = JSON.parse(this.getAttribute('data-next-statuses'));
        
        // Populate order details if available (from list page)
        const orderKode = this.getAttribute('data-order-kode');
        const customerName = this.getAttribute('data-customer-name');
        const serviceName = this.getAttribute('data-service-name');
        const currentStatus = this.getAttribute('data-current-status');
        
        if (orderKode) {
          document.getElementById('modalOrderKodeTitle').textContent = orderKode;
        }
        if (customerName) document.getElementById('modalCustomerName').textContent = customerName;
        if (serviceName) document.getElementById('modalServiceName').textContent = serviceName;
        if (currentStatus) document.getElementById('modalCurrentStatus').textContent = currentStatus;
        
        const selectElement = statusSelectField.querySelector('select');
        selectElement.innerHTML = '<option value="" disabled selected>-- Pilih status baru --</option>';
        nextStatuses.forEach(status => {
          const option = document.createElement('option');
          option.value = status;
          option.textContent = status;
          selectElement.appendChild(option);
        });
        const hiddenInput = statusUpdateForm.querySelector('input[name="id_order"]');
        if (hiddenInput) hiddenInput.value = orderId;
        statusUpdateModal.classList.add('open');
      });
    });

    closeButtons.forEach(btn => btn.addEventListener('click', () => statusUpdateModal.classList.remove('open')));
    statusUpdateModal.addEventListener('click', function(event) {
      if (event.target === this) {
        statusUpdateModal.classList.remove('open');
      }
    });

    statusUpdateBtn.addEventListener('click', function() {
      if (statusUpdateForm) {
        statusUpdateForm.submit();
      }
    });
  }
})();
</script>

<script>
(function(){
  const role = "staff";
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
  document.querySelectorAll('.order-card.urgent').forEach(el => el.classList.add('border-l-amber-400'));
  document.querySelectorAll('.card').forEach(el => el.classList.add('mb-5','overflow-hidden'));
  document.querySelectorAll('.card-header').forEach(el => el.classList.add('flex','items-center','justify-between','gap-3','border-b','border-slate-200','px-5','py-4'));
  document.querySelectorAll('.card-header h3, .form-card h3').forEach(el => el.classList.add('font-bold','text-slate-900'));
  document.querySelectorAll('.form-card').forEach(el => el.classList.add('p-6','mb-6'));
  document.querySelectorAll('.form-grid, .info-grid').forEach(el => el.classList.add('grid','grid-cols-1','md:grid-cols-2','gap-4'));
  document.querySelectorAll('.info-box').forEach(el => {
    el.classList.add('rounded-xl','border','border-slate-100','bg-slate-50','p-4');
    const lbl = el.querySelector('.label'); if(lbl) { lbl.classList.add('text-xs','font-medium','uppercase','tracking-wide','text-slate-400','mb-1'); }
    const val = el.querySelector('.val'); if(val) { val.classList.add('text-sm','font-semibold','text-slate-800'); }
  });
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

  document.querySelectorAll('.modal-panel, .modal-box, .modal-card').forEach(el => {
    el.classList.add('relative');
  });

  // Modal styling - gunakan display:none/flex langsung, bukan Tailwind hidden agar JS open/close bisa bekerja
  document.querySelectorAll('.modal-overlay, .custom-modal').forEach(el => {
    el.style.cssText = 'display:none;position:fixed;inset:0;z-index:1000;align-items:center;justify-content:center;background:rgba(15,23,42,0.4);padding:20px';
  });
  document.querySelectorAll('.modal-overlay.open, .custom-modal.open').forEach(el => {
    el.style.display = 'flex';
  });

  // Patch open/close agar gunakan style.display bukan classList hidden
  const _origAdd = DOMTokenList.prototype.add;
  // Override classList.add/remove untuk modal open class
  document.querySelectorAll('.modal-overlay, .custom-modal').forEach(modal => {
    const observer = new MutationObserver(() => {
      modal.style.display = modal.classList.contains('open') ? 'flex' : 'none';
    });
    observer.observe(modal, { attributes: true, attributeFilter: ['class'] });
  });
})();</script>

</body>
</html>
