<?php
require_once 'app_config.php';
cleango_boot_session();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header("Location: login.php"); exit();
}

$id_owner  = (int)$_SESSION['user_id'];
$ownerName = $_SESSION['nama'];
$page      = $_GET['page'] ?? 'dashboard';
$flash     = '';

// ============================================================
// POST ACTIONS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ft = $_POST['form_type'] ?? '';

    if ($ft === 'tambah_katalog') {
        $pdo->prepare("INSERT INTO katalog (id_layanan,jenis_layanan,varian,harga,satuan,deskripsi,status) VALUES (?,?,?,?,?,?,?)")
            ->execute([$_POST['id_layanan'],'',$_POST['varian'],(float)$_POST['harga'],$_POST['satuan'],$_POST['deskripsi']??null,$_POST['status']]);
        $flash = "Katalog baru berhasil ditambahkan.";
    }
    if ($ft === 'edit_katalog') {
        $pdo->prepare("UPDATE katalog SET id_layanan=?,jenis_layanan=?,varian=?,harga=?,satuan=?,deskripsi=?,status=? WHERE id_katalog=?")
            ->execute([$_POST['id_layanan'],'',$_POST['varian'],(float)$_POST['harga'],$_POST['satuan'],$_POST['deskripsi']??null,$_POST['status'],(int)$_POST['id_katalog']]);
        $flash = "Katalog berhasil diperbarui.";
    }
    if ($ft === 'hapus_katalog') {
        $pdo->prepare("DELETE FROM katalog WHERE id_katalog=?")->execute([(int)$_POST['id_katalog']]);
        $flash = "Katalog dihapus.";
    }
    if ($ft === 'tambah_layanan') {
        $pdo->prepare("INSERT INTO layanan (nama_layanan,deskripsi) VALUES (?,?)")->execute([$_POST['nama_layanan'],$_POST['deskripsi']??null]);
        $flash = "Layanan baru ditambahkan.";
    }
    if ($ft === 'toggle_layanan') {
        $pdo->prepare("UPDATE layanan SET is_active = 1-is_active WHERE id_layanan=?")->execute([(int)$_POST['id_layanan']]);
        $flash = "Status layanan diperbarui.";
    }
    if ($ft === 'edit_layanan') {
        $pdo->prepare("UPDATE layanan SET nama_layanan=?,deskripsi=?,is_active=? WHERE id_layanan=?")
            ->execute([$_POST['nama_layanan'],$_POST['deskripsi']??null,(int)$_POST['status'],(int)$_POST['id_layanan']]);
        $flash = "Layanan berhasil diperbarui.";
    }
    if ($ft === 'hapus_layanan') {
        $pdo->prepare("DELETE FROM layanan WHERE id_layanan=?")->execute([(int)$_POST['id_layanan']]);
        $flash = "Layanan dihapus.";
    }
    if ($ft === 'batalkan_order') {
        $id_order = (int)$_POST['id_order'];
        $pdo->prepare("UPDATE orders SET status_order='Dibatalkan' WHERE id_order=?")->execute([$id_order]);
        $pdo->prepare("INSERT INTO tracking (id_order,status,keterangan) VALUES (?,'Dibatalkan','Dibatalkan oleh owner')")->execute([$id_order]);
        $oi = $pdo->prepare("SELECT o.kode_order, u.id_cust FROM orders o JOIN users u ON u.id_cust=o.id_cust WHERE o.id_order=?");
        $oi->execute([$id_order]);
        $oi = $oi->fetch();
        if ($oi) {
            sendNotification($pdo, 'customer', $oi['id_cust'], '❌ Order Dibatalkan', "Order {$oi['kode_order']} dibatalkan oleh pengelola. Hubungi kami jika ada pertanyaan.", "customer.php?page=riwayat");
            notifyAllStaff($pdo, '❌ Order Dibatalkan', "Owner membatalkan order {$oi['kode_order']}.", "staff.php?page=order_masuk");
        }
        $flash = "Order berhasil dibatalkan.";
    }
    if ($ft === 'tambah_staff') {
        $nama = trim($_POST['nama'] ?? '');
        $uname = trim($_POST['username'] ?? '');
        $telp  = trim($_POST['notelp'] ?? '');
        $sandi = trim($_POST['sandi'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        if ($nama && $uname && $telp && $sandi) {
            $pdo->prepare("INSERT INTO staff (nama,username,notelp,sandi,alamat) VALUES (?,?,?,?,?)")->execute([$nama,$uname,$telp,$sandi,$alamat]);
            $flash = "Staff baru berhasil ditambahkan.";
        }
    }
    header("Location: ?page={$page}"); exit();
}

// ============================================================
// DATA QUERIES
// ============================================================
$layananList = $pdo->query("SELECT * FROM layanan ORDER BY id_layanan")->fetchAll();
$katalogList = $pdo->query("SELECT k.*, l.nama_layanan FROM katalog k JOIN layanan l ON l.id_layanan=k.id_layanan ORDER BY l.id_layanan, k.varian")->fetchAll();
$staffList   = $pdo->query("SELECT * FROM staff ORDER BY nama")->fetchAll();

$allOrders = $pdo->query("
    SELECT o.*, l.nama_layanan, u.nama_cust, u.notelp_cust, s.nama AS nama_staff,
           p.jumlah AS jumlah_bayar, p.status_bayar
    FROM orders o
    JOIN layanan l ON l.id_layanan=o.id_layanan
    JOIN users u ON u.id_cust=o.id_cust
    LEFT JOIN staff s ON s.id_staff=o.id_staff
    LEFT JOIN pembayaran p ON p.id_order=o.id_order
    ORDER BY o.tanggal_pesan DESC
")->fetchAll();

// Stats
$totalOrder    = count($allOrders);
$orderAktif    = count(array_filter($allOrders, fn($o)=>!in_array($o['status_order'],['Selesai','Dibatalkan'])));
$orderSelesai  = count(array_filter($allOrders, fn($o)=>$o['status_order']==='Selesai'));
$totalOmzet    = array_sum(array_column(array_filter($allOrders, fn($o)=>$o['status_bayar']==='Lunas'), 'jumlah_bayar'));

// Laporan bulanan
$laporanBulan = $pdo->query("
    SELECT DATE_FORMAT(tanggal_pesan,'%Y-%m') AS bulan,
           COUNT(*) AS total_order, SUM(total_harga) AS total_omzet,
           SUM(CASE WHEN status_order='Selesai' THEN 1 ELSE 0 END) AS selesai
    FROM orders GROUP BY bulan ORDER BY bulan DESC LIMIT 12
")->fetchAll();

$invoiceList = $pdo->query("
    SELECT i.*, p.jumlah, p.metode, o.kode_order, u.nama_cust
    FROM invoice i JOIN pembayaran p ON p.id_bayar=i.id_bayar
    JOIN orders o ON o.id_order=p.id_order JOIN users u ON u.id_cust=o.id_cust
    ORDER BY i.tgl_invoice DESC LIMIT 30
")->fetchAll();

$selId = (int)($_GET['id'] ?? 0);
$selOrder = null;
$selTracking = [];
if ($selId) {
    $sq = $pdo->prepare("
        SELECT o.*, l.nama_layanan, u.nama_cust, u.notelp_cust, u.alamat_cust, s.nama AS nama_staff,
               od.berat, od.qty, od.subtotal, k.jenis_layanan, k.varian, k.satuan,
               p.jumlah AS jumlah_bayar, p.status_bayar, p.metode
        FROM orders o JOIN layanan l ON l.id_layanan=o.id_layanan
        JOIN users u ON u.id_cust=o.id_cust
        LEFT JOIN staff s ON s.id_staff=o.id_staff
        LEFT JOIN order_detail od ON od.id_order=o.id_order
        LEFT JOIN katalog k ON k.id_katalog=od.id_katalog
        LEFT JOIN pembayaran p ON p.id_order=o.id_order
        WHERE o.id_order=?
    ");
    $sq->execute([$selId]);
    $selOrder = $sq->fetch();
    if ($selOrder) {
        $st = $pdo->prepare("SELECT * FROM tracking WHERE id_order=? ORDER BY waktu_update ASC");
        $st->execute([$selId]);
        $selTracking = $st->fetchAll();
    }
}

$unreadCount = countUnread($pdo, 'owner', $id_owner);

function sBadge($s) {
    return match($s) {
        'Menunggu Konfirmasi'=>'badge-warning','Dijemput'=>'badge-blue',
        'Dicuci'=>'badge-process','Disetrika'=>'badge-purple',
        'Dikirim'=>'badge-cyan','Selesai'=>'badge-success',
        'Dibatalkan'=>'badge-danger','Lunas'=>'badge-success',
        'Pending'=>'badge-warning',default=>'badge-default'
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
<title>Owner - CleanGo Laundry</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','Segoe UI','sans-serif']}}}}</script>
<style>
.modal-overlay { display:none; position:fixed; inset:0; z-index:1000; align-items:center; justify-content:center; background:rgba(15,23,42,0.5); padding:20px; }
.modal-overlay[aria-hidden="false"] { display:flex !important; }
</style>
</head>
<body data-role="owner">
<div class="sidebar">
  <div class="logo">
    <div class="logo-icon"><i class="fas fa-crown"></i></div>
    <div><div class="logo-text">CleanGo</div><div class="logo-sub">Owner Panel</div></div>
  </div>
  <nav>
    <a href="?page=dashboard" class="<?= $page==='dashboard'?'active':'' ?>"><i class="fas fa-chart-pie"></i> Dashboard</a>
    <a href="?page=semua_order" class="<?= $page==='semua_order'?'active':'' ?>"><i class="fas fa-clipboard-list"></i> Semua Order</a>
    <a href="?page=katalog" class="<?= $page==='katalog'?'active':'' ?>"><i class="fas fa-tags"></i> Kelola Katalog</a>
    <a href="?page=layanan" class="<?= $page==='layanan'?'active':'' ?>"><i class="fas fa-soap"></i> Kelola Layanan</a>
    <a href="?page=staff" class="<?= $page==='staff'?'active':'' ?>"><i class="fas fa-users"></i> Data Staff</a>
    <a href="?page=invoice" class="<?= $page==='invoice'?'active':'' ?>"><i class="fas fa-file-invoice"></i> Invoice</a>
    <a href="?page=laporan" class="<?= $page==='laporan'?'active':'' ?>"><i class="fas fa-chart-bar"></i> Laporan</a>
  </nav>
  <div class="sidebar-bottom">
    <div class="user-card">
      <div class="avatar"><?= mb_strtoupper(mb_substr($ownerName,0,1)) ?></div>
      <div class="user-info"><div class="name"><?= htmlspecialchars($ownerName) ?></div><div class="role">Owner</div></div>
    </div>
    <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Keluar</a>
  </div>
</div>

<div class="main">
  <div class="topbar">
    <div class="topbar-title"><i class="fas fa-crown" style="color:#7c3aed;margin-right:8px"></i>CleanGo — Owner Dashboard</div>
    <div class="topbar-right">
      <span style="font-size:13px;color:#64748b">Halo, <strong><?= htmlspecialchars($ownerName) ?></strong>!</span>
    </div>
  </div>

  <div class="content">
    <?php if ($flash): ?>
    <div class="flash"><i class="fas fa-check-circle"></i> <?= $flash ?></div>
    <?php endif; ?>

    <?php // DASHBOARD
    if ($page === 'dashboard'): ?>
    <div class="page-header"><div><h1>👑 Dashboard Owner</h1><p>Ringkasan bisnis CleanGo secara keseluruhan.</p></div></div>
    <div class="stats-grid">
      <div class="stat-card">
        <div class="icon" style="background:#f5f3ff"><i class="fas fa-clipboard-list" style="color:#7c3aed"></i></div>
        <div class="label">Total Order</div><div class="value"><?= $totalOrder ?></div><div class="sub">Semua waktu</div>
      </div>
      <div class="stat-card">
        <div class="icon" style="background:#ecfdf5"><i class="fas fa-spinner" style="color:#059669"></i></div>
        <div class="label">Order Aktif</div><div class="value"><?= $orderAktif ?></div><div class="sub">Sedang berjalan</div>
      </div>
      <div class="stat-card">
        <div class="icon" style="background:#dcfce7"><i class="fas fa-check-circle" style="color:#166534"></i></div>
        <div class="label">Order Selesai</div><div class="value"><?= $orderSelesai ?></div><div class="sub">Total selesai</div>
      </div>
      <div class="stat-card">
        <div class="icon" style="background:#fef3c7"><i class="fas fa-money-bill-wave" style="color:#92400e"></i></div>
        <div class="label">Total Omzet</div><div class="value" style="font-size:16px"><?= rupiah($totalOmzet) ?></div><div class="sub">Pembayaran lunas</div>
      </div>
    </div>

    <!-- Order Terbaru -->
    <div class="card" style="margin-bottom:28px">
      <div class="card-header"><h3>Order Terbaru</h3><a href="?page=semua_order" class="btn btn-outline btn-sm">Lihat Semua</a></div>
      <table>
        <thead><tr><th>Kode</th><th>Customer</th><th>Layanan</th><th>Staff</th><th>Status</th><th>Total</th><th>Tanggal</th></tr></thead>
        <tbody>
        <?php foreach(array_slice($allOrders,0,8) as $o): ?>
        <tr>
          <td><strong><?= htmlspecialchars($o['kode_order']) ?></strong></td>
          <td><?= htmlspecialchars($o['nama_cust']) ?></td>
          <td><?= htmlspecialchars($o['nama_layanan']) ?></td>
          <td><?= $o['nama_staff'] ? htmlspecialchars($o['nama_staff']) : '<span style="color:#94a3b8">Belum</span>' ?></td>
          <td><span class="badge <?= sBadge($o['status_order']) ?>"><?= $o['status_order'] ?></span></td>
          <td><?= $o['total_harga']>0 ? rupiah($o['total_harga']) : '-' ?></td>
          <td><?= date('d/m/Y', strtotime($o['tanggal_pesan'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Staff Overview -->
    <div class="card">
      <div class="card-header"><h3>Tim Staff</h3><a href="?page=staff" class="btn btn-outline btn-sm">Kelola</a></div>
      <table>
        <thead><tr><th>Nama</th><th>Username</th><th>No. Telp</th><th>Order Selesai</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach($staffList as $s):
          $sc = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE id_staff=? AND status_order='Selesai'");
          $sc->execute([$s['id_staff']]);
          $sc = $sc->fetchColumn();
        ?>
        <tr>
          <td><strong><?= htmlspecialchars($s['nama']) ?></strong></td>
          <td><?= htmlspecialchars($s['username']) ?></td>
          <td><?= htmlspecialchars($s['notelp']) ?></td>
          <td><span class="badge badge-success"><?= $sc ?> order</span></td>
          <td><span class="badge <?= $s['is_active'] ? 'badge-success' : 'badge-danger' ?>"><?= $s['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php // SEMUA ORDER
    elseif ($page === 'semua_order'): ?>
    <div class="page-header"><div><h1>📋 Semua Order</h1><p>Monitor seluruh pesanan.</p></div></div>

    <?php if ($selId && $selOrder): ?>
    <a href="?page=semua_order" class="btn btn-outline btn-sm" style="margin-bottom:16px"><i class="fas fa-arrow-left"></i> Kembali</a>
    <div class="card" style="padding:24px">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:20px">
        <div>
          <h2 style="font-size:20px"><?= htmlspecialchars($selOrder['kode_order']) ?></h2>
          <p style="color:#64748b;font-size:13px"><?= date('d F Y H:i', strtotime($selOrder['tanggal_pesan'])) ?></p>
        </div>
        <span class="badge <?= sBadge($selOrder['status_order']) ?>" style="font-size:14px;padding:6px 16px"><?= $selOrder['status_order'] ?></span>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
        <div style="background:#f8fafc;padding:14px;border-radius:12px"><div style="font-size:12px;color:#64748b;margin-bottom:4px">Customer</div><div style="font-weight:600"><?= htmlspecialchars($selOrder['nama_cust']) ?></div><div style="font-size:13px;color:#64748b"><?= htmlspecialchars($selOrder['notelp_cust']) ?></div></div>
        <div style="background:#f8fafc;padding:14px;border-radius:12px"><div style="font-size:12px;color:#64748b;margin-bottom:4px">Layanan</div><div style="font-weight:600"><?= htmlspecialchars($selOrder['nama_layanan']) ?><?php if($selOrder['varian']): ?> · <?= $selOrder['varian'] ?><?php endif; ?></div></div>
        <div style="background:#f8fafc;padding:14px;border-radius:12px"><div style="font-size:12px;color:#64748b;margin-bottom:4px">Staff</div><div style="font-weight:600"><?= $selOrder['nama_staff'] ? htmlspecialchars($selOrder['nama_staff']) : 'Belum ditugaskan' ?></div></div>
        <div style="background:#f8fafc;padding:14px;border-radius:12px"><div style="font-size:12px;color:#64748b;margin-bottom:4px">Pembayaran</div><div style="font-weight:600"><?= rupiah($selOrder['jumlah_bayar'] ?? 0) ?> <span class="badge <?= sBadge($selOrder['status_bayar'] ?? '') ?>"><?= $selOrder['status_bayar'] ?? '-' ?></span></div></div>
      </div>
      <?php if (!in_array($selOrder['status_order'], ['Selesai','Dibatalkan'])): ?>
      <form method="POST" onsubmit="return confirm('Batalkan order ini?')">
        <input type="hidden" name="form_type" value="batalkan_order">
        <input type="hidden" name="id_order" value="<?= $selOrder['id_order'] ?>">
        <button class="btn btn-danger"><i class="fas fa-times-circle"></i> Batalkan Order</button>
      </form>
      <?php endif; ?>
      <?php if (!empty($selTracking)): ?>
      <div style="margin-top:20px;padding-top:20px;border-top:1px solid #e2e8f0">
        <h4 style="font-size:14px;font-weight:700;margin-bottom:14px">📍 Riwayat Tracking</h4>
        <ul class="timeline">
          <?php foreach(array_reverse($selTracking) as $t): ?>
          <li class="tl-item"><div class="tl-dot"></div><div class="tl-body"><strong><?= htmlspecialchars($t['status']) ?></strong><?php if($t['keterangan']): ?> — <?= htmlspecialchars($t['keterangan']) ?><?php endif; ?><br><small><?= date('d/m/Y H:i', strtotime($t['waktu_update'])) ?></small></div></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- Filter Status -->
    <div style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap">
      <?php $fStatus = $_GET['status'] ?? '';
      $statuses = [''=>'Semua','Menunggu Konfirmasi'=>'Menunggu','Dijemput'=>'Dijemput','Dicuci'=>'Dicuci','Disetrika'=>'Disetrika','Dikirim'=>'Dikirim','Selesai'=>'Selesai','Dibatalkan'=>'Dibatalkan'];
      foreach($statuses as $k=>$v): ?>
      <a href="?page=semua_order&status=<?= urlencode($k) ?>" class="btn btn-sm <?= $fStatus===$k?'btn-primary':'btn-outline' ?>"><?= $v ?></a>
      <?php endforeach; ?>
    </div>
    <div class="card">
      <div class="card-header"><h3>Semua Order (<?= $totalOrder ?>)</h3></div>
      <table>
        <thead><tr><th>Kode</th><th>Customer</th><th>Layanan</th><th>Staff</th><th>Status</th><th>Total</th><th>Tanggal</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php $filteredOrders = $fStatus ? array_filter($allOrders, fn($o)=>$o['status_order']===$fStatus) : $allOrders;
        foreach($filteredOrders as $o): ?>
        <tr>
          <td><strong><?= htmlspecialchars($o['kode_order']) ?></strong></td>
          <td><?= htmlspecialchars($o['nama_cust']) ?></td>
          <td><?= htmlspecialchars($o['nama_layanan']) ?></td>
          <td><?= $o['nama_staff'] ? htmlspecialchars($o['nama_staff']) : '<span style="color:#94a3b8">-</span>' ?></td>
          <td><span class="badge <?= sBadge($o['status_order']) ?>"><?= $o['status_order'] ?></span></td>
          <td><?= $o['total_harga']>0 ? rupiah($o['total_harga']) : '-' ?></td>
          <td><?= date('d/m/Y', strtotime($o['tanggal_pesan'])) ?></td>
          <td><a href="?page=semua_order&id=<?= $o['id_order'] ?>" class="btn btn-outline btn-sm">Detail</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php // KATALOG
    elseif ($page === 'katalog'): ?>
    <div class="page-header"><div><h1>🏷️ Kelola Katalog</h1><p>Atur harga dan jenis layanan.</p></div></div>
    <div class="card">
      <div class="card-header"><h3>Daftar Katalog (<?= count($katalogList) ?>)</h3><button type="button" class="btn btn-primary" id="tambahKatalogBtn"><i class="fas fa-plus"></i> Tambah Katalog</button></div>
      <table>
        <thead><tr><th>Layanan</th><th>Varian</th><th>Harga</th><th>Satuan</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach($katalogList as $k): ?>
        <tr>
          <td><?= htmlspecialchars($k['nama_layanan']) ?></td>
          <td><span class="badge <?= strtolower($k['varian']) === 'express' ? 'badge-purple' : 'badge-blue' ?>"><?= $k['varian'] ?></span></td>
          <td style="font-weight:700"><?= rupiah($k['harga']) ?></td>
          <td><?= $k['satuan'] ?></td>
          <td><span class="badge <?= $k['status']==='Aktif'?'badge-success':'badge-danger' ?>"><?= $k['status'] ?></span></td>
          <td style="white-space:nowrap">
            <button type="button" class="btn btn-warning btn-sm edit-katalog-btn"
              data-id="<?= $k['id_katalog'] ?>"
              data-id-layanan="<?= $k['id_layanan'] ?>"

              data-varian="<?= $k['varian'] ?>"
              data-harga="<?= $k['harga'] ?>"
              data-satuan="<?= $k['satuan'] ?>"
              data-status="<?= $k['status'] ?>"
              data-deskripsi="<?= htmlspecialchars($k['deskripsi'] ?? '', ENT_QUOTES) ?>"
            ><i class="fas fa-edit"></i></button>
            <form method="POST" style="display:inline" onsubmit="return confirm('Hapus katalog ini?')">
              <input type="hidden" name="form_type" value="hapus_katalog">
              <input type="hidden" name="id_katalog" value="<?= $k['id_katalog'] ?>">
              <button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div id="tambahKatalogModal" class="modal-overlay" aria-hidden="true">
      <div class="modal-card" style="background:#fff;border-radius:20px;padding:28px;width:100%;max-width:600px;max-height:90vh;overflow-y:auto;position:relative">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid #e2e8f0">
          <h3 style="font-size:17px;font-weight:700">➕ Tambah Katalog Baru</h3>
          <button type="button" class="modal-close-tambah" style="background:none;border:none;font-size:22px;cursor:pointer;color:#64748b;line-height:1">&times;</button>
        </div>
        <form method="POST" id="tambahKatalogForm">
          <input type="hidden" name="form_type" value="tambah_katalog">
          <div class="form-grid">
            <div class="field">
              <label>Layanan Induk *</label>
              <select name="id_layanan" id="tambah_id_layanan" required>
                <?php foreach($layananList as $l): ?>
                <?php if ($l['is_active']): ?>
                <option value="<?= $l['id_layanan'] ?>"><?= htmlspecialchars($l['nama_layanan']) ?></option>
                <?php endif; ?>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label>Varian *</label>
              <select name="varian" id="tambah_varian" required>
                <?php foreach(['Regular','Express'] as $v): ?>
                <option value="<?= $v ?>"><?= $v ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label>Harga (Rp) *</label>
              <input type="number" name="harga" id="tambah_harga" required>
            </div>
            <div class="field">
              <label>Satuan</label>
              <select name="satuan" id="tambah_satuan">
                <option value="kg">kg</option>
                <option value="pcs">pcs</option>
              </select>
            </div>
            <div class="field">
              <label>Status</label>
              <select name="status" id="tambah_status">
                <option value="Aktif">Aktif</option>
                <option value="Nonaktif">Nonaktif</option>
              </select>
            </div>
            <div class="field full">
              <label>Deskripsi</label>
              <input type="text" name="deskripsi" id="tambah_deskripsi">
            </div>
          </div>
          <div style="margin-top:20px;display:flex;gap:12px;justify-content:flex-end">
            <button type="button" class="btn btn-outline modal-close-tambah">Batal</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
          </div>
        </form>
      </div>
    </div>

    <div id="editKatalogModal" class="modal-overlay" aria-hidden="true">
      <div class="modal-card" style="background:#fff;border-radius:20px;padding:28px;width:100%;max-width:600px;max-height:90vh;overflow-y:auto;position:relative">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid #e2e8f0">
          <h3 style="font-size:17px;font-weight:700">✏️ Edit Katalog</h3>
          <button type="button" class="modal-close-edit" style="background:none;border:none;font-size:22px;cursor:pointer;color:#64748b;line-height:1">&times;</button>
        </div>
        <form method="POST" id="editKatalogForm">
          <input type="hidden" name="form_type" value="edit_katalog">
          <input type="hidden" name="id_katalog" id="modal_id_katalog">
          <div class="form-grid">
            <div class="field">
              <label>Layanan Induk *</label>
              <select name="id_layanan" id="modal_id_layanan" required>
                <?php foreach($layananList as $l): ?>
                <?php if ($l['is_active']): ?>
                <option value="<?= $l['id_layanan'] ?>"><?= htmlspecialchars($l['nama_layanan']) ?></option>
                <?php endif; ?>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label>Varian *</label>
              <select name="varian" id="modal_varian" required>
                <?php foreach(['Regular','Express'] as $v): ?>
                <option value="<?= $v ?>"><?= $v ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label>Harga (Rp) *</label>
              <input type="number" name="harga" id="modal_harga" required>
            </div>
            <div class="field">
              <label>Satuan</label>
              <select name="satuan" id="modal_satuan">
                <option value="kg">kg</option>
                <option value="pcs">pcs</option>
              </select>
            </div>
            <div class="field">
              <label>Status</label>
              <select name="status" id="modal_status">
                <option value="Aktif">Aktif</option>
                <option value="Nonaktif">Nonaktif</option>
              </select>
            </div>
            <div class="field full">
              <label>Deskripsi</label>
              <input type="text" name="deskripsi" id="modal_deskripsi">
            </div>
          </div>
          <div style="margin-top:20px;display:flex;gap:12px;justify-content:flex-end">
            <button type="button" class="btn btn-outline modal-close-edit">Batal</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Perbarui</button>
          </div>
        </form>
      </div>
    </div>

    <?php // LAYANAN
    elseif ($page === 'layanan'): ?>
    <div class="page-header"><div><h1>🧺 Kelola Layanan</h1><p>Master data layanan utama.</p></div></div>
    <div class="card">
      <div class="card-header">
        <h3>Daftar Layanan</h3>
        <button type="button" id="tambahLayananBtn" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah Layanan</button>
      </div>
      <table>
        <thead><tr><th>Nama Layanan</th><th>Deskripsi</th><th>Status</th><th>Dibuat</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach($layananList as $l): ?>
        <tr>
          <td><strong><?= htmlspecialchars($l['nama_layanan']) ?></strong></td>
          <td><?= htmlspecialchars($l['deskripsi'] ?? '-') ?></td>
          <td><span class="badge <?= $l['is_active']?'badge-success':'badge-danger' ?>"><?= $l['is_active']?'Aktif':'Nonaktif' ?></span></td>
          <td><?= date('d/m/Y', strtotime($l['created_at'])) ?></td>
          <td style="white-space:nowrap">
            <button type="button" class="btn btn-warning btn-sm edit-layanan-btn"
              data-id="<?= $l['id_layanan'] ?>"
              data-nama="<?= htmlspecialchars($l['nama_layanan'], ENT_QUOTES) ?>"
              data-deskripsi="<?= htmlspecialchars($l['deskripsi'] ?? '', ENT_QUOTES) ?>"
              data-status="<?= $l['is_active'] ?>"
            ><i class="fas fa-edit"></i></button>
            <form method="POST" style="display:inline" onsubmit="return confirm('Hapus layanan ini?')">
              <input type="hidden" name="form_type" value="hapus_layanan">
              <input type="hidden" name="id_layanan" value="<?= $l['id_layanan'] ?>">
              <button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Modal Tambah Layanan -->
    <div id="tambahLayananModal" class="modal-overlay" aria-hidden="true">
      <div style="background:#fff;border-radius:20px;padding:28px;width:100%;max-width:480px;position:relative">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid #e2e8f0">
          <h3 style="font-size:17px;font-weight:700">➕ Tambah Layanan Baru</h3>
          <button type="button" class="modal-close-layanan" style="background:none;border:none;font-size:22px;cursor:pointer;color:#64748b;line-height:1">&times;</button>
        </div>
        <form method="POST">
          <input type="hidden" name="form_type" value="tambah_layanan">
          <div class="form-grid">
            <div class="field"><label>Nama Layanan *</label><input type="text" name="nama_layanan" required></div>
            <div class="field"><label>Deskripsi</label><input type="text" name="deskripsi"></div>
          </div>
          <div style="margin-top:20px;display:flex;gap:12px;justify-content:flex-end">
            <button type="button" class="btn btn-outline modal-close-layanan">Batal</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Edit Layanan -->
    <div id="editLayananModal" class="modal-overlay" aria-hidden="true">
      <div style="background:#fff;border-radius:20px;padding:28px;width:100%;max-width:480px;position:relative">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid #e2e8f0">
          <h3 style="font-size:17px;font-weight:700">✏️ Edit Layanan</h3>
          <button type="button" class="modal-close-layanan-edit" style="background:none;border:none;font-size:22px;cursor:pointer;color:#64748b;line-height:1">&times;</button>
        </div>
        <form method="POST">
          <input type="hidden" name="form_type" value="edit_layanan">
          <input type="hidden" name="id_layanan" id="modal_id_layanan_edit">
          <div class="form-grid">
            <div class="field"><label>Nama Layanan *</label><input type="text" name="nama_layanan" id="modal_nama_layanan" required></div>
            <div class="field"><label>Deskripsi</label><input type="text" name="deskripsi" id="modal_deskripsi_layanan"></div>
            <div class="field"><label>Status *</label><select name="status" id="modal_status_layanan" required><option value="">Pilih Status</option><option value="1">Aktif</option><option value="0">Nonaktif</option></select></div>
          </div>
          <div style="margin-top:20px;display:flex;gap:12px;justify-content:flex-end">
            <button type="button" class="btn btn-outline modal-close-layanan-edit">Batal</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Simpan</button>
          </div>
        </form>
      </div>
    </div>

    <?php // STAFF
    elseif ($page === 'staff'): ?>
    <div class="page-header"><div><h1>👥 Data Staff</h1><p>Kelola tim staff CleanGo.</p></div></div>
    <div class="card">
      <div class="card-header">
        <h3>Daftar Staff (<?= count($staffList) ?>)</h3>
        <button type="button" id="tambahStaffBtn" class="btn btn-primary"><i class="fas fa-user-plus"></i> Tambah Staff</button>
      </div>
      <table>
        <thead><tr><th>Nama</th><th>Username</th><th>No. Telp</th><th>Alamat</th><th>Order Selesai</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach($staffList as $s):
          $sc = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE id_staff=? AND status_order='Selesai'");
          $sc->execute([$s['id_staff']]); $sc=$sc->fetchColumn();
        ?>
        <tr>
          <td><strong><?= htmlspecialchars($s['nama']) ?></strong></td>
          <td><?= htmlspecialchars($s['username']) ?></td>
          <td><?= htmlspecialchars($s['notelp']) ?></td>
          <td><?= htmlspecialchars($s['alamat'] ?? '-') ?></td>
          <td><span class="badge badge-process"><?= $sc ?></span></td>
          <td><span class="badge <?= $s['is_active']?'badge-success':'badge-danger' ?>"><?= $s['is_active']?'Aktif':'Nonaktif' ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Modal Tambah Staff -->
    <div id="tambahStaffModal" class="modal-overlay" aria-hidden="true">
      <div style="background:#fff;border-radius:20px;padding:28px;width:100%;max-width:560px;position:relative">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid #e2e8f0">
          <h3 style="font-size:17px;font-weight:700">👤 Tambah Staff Baru</h3>
          <button type="button" class="modal-close-staff" style="background:none;border:none;font-size:22px;cursor:pointer;color:#64748b;line-height:1">&times;</button>
        </div>
        <form method="POST">
          <input type="hidden" name="form_type" value="tambah_staff">
          <div class="form-grid">
            <div class="field"><label>Nama Lengkap *</label><input type="text" name="nama" required></div>
            <div class="field"><label>Username *</label><input type="text" name="username" required></div>
            <div class="field"><label>No. Telepon *</label><input type="text" name="notelp" required></div>
            <div class="field"><label>Password *</label><input type="password" name="sandi" required></div>
            <div class="field full"><label>Alamat</label><input type="text" name="alamat"></div>
          </div>
          <div style="margin-top:20px;display:flex;gap:12px;justify-content:flex-end">
            <button type="button" class="btn btn-outline modal-close-staff">Batal</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Tambah Staff</button>
          </div>
        </form>
      </div>
    </div>

    <?php // INVOICE
    elseif ($page === 'invoice'): ?>
    <div class="page-header"><div><h1>🧾 Invoice</h1><p>Semua invoice yang diterbitkan.</p></div></div>
    <div class="card">
      <div class="card-header"><h3>Daftar Invoice (<?= count($invoiceList) ?>)</h3></div>
      <table>
        <thead><tr><th>No. Invoice</th><th>Kode Order</th><th>Customer</th><th>Jumlah</th><th>Metode</th><th>Tgl Invoice</th></tr></thead>
        <tbody>
        <?php foreach($invoiceList as $inv): ?>
        <tr>
          <td><strong><?= htmlspecialchars($inv['no_invoice']) ?></strong></td>
          <td><?= htmlspecialchars($inv['kode_order']) ?></td>
          <td><?= htmlspecialchars($inv['nama_cust']) ?></td>
          <td style="font-weight:700"><?= rupiah($inv['jumlah']) ?></td>
          <td><span class="badge badge-blue"><?= $inv['metode'] ?></span></td>
          <td><?= date('d/m/Y H:i', strtotime($inv['tgl_invoice'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php // LAPORAN
    elseif ($page === 'laporan'): ?>
    <div class="page-header"><div><h1>📊 Laporan</h1><p>Analisis bisnis bulanan.</p></div></div>
    <?php
    $maxOmzet = max(array_column($laporanBulan, 'total_omzet') ?: [1]);
    ?>
    <div class="card" style="padding:24px">
      <h3 style="margin-bottom:20px;font-size:16px">Omzet & Order per Bulan</h3>
      <?php foreach($laporanBulan as $lb): ?>
      <div class="laporan-bar">
        <span style="font-size:12px;width:70px;flex-shrink:0;color:#64748b"><?= $lb['bulan'] ?></span>
        <div class="bar-bg"><div class="bar-fill" style="width:<?= min(100, round($lb['total_omzet']/$maxOmzet*100)) ?>%"></div></div>
        <span style="font-size:12px;width:130px;flex-shrink:0;text-align:right;font-weight:600"><?= rupiah($lb['total_omzet']) ?></span>
        <span style="font-size:12px;color:#94a3b8;flex-shrink:0;width:60px;text-align:right"><?= $lb['total_order'] ?> order</span>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="card">
      <div class="card-header"><h3>Detail Laporan Bulanan</h3></div>
      <table>
        <thead><tr><th>Bulan</th><th>Total Order</th><th>Selesai</th><th>Total Omzet</th></tr></thead>
        <tbody>
        <?php foreach($laporanBulan as $lb): ?>
        <tr>
          <td><strong><?= $lb['bulan'] ?></strong></td>
          <td><?= $lb['total_order'] ?></td>
          <td><span class="badge badge-success"><?= $lb['selesai'] ?></span></td>
          <td style="font-weight:700"><?= rupiah($lb['total_omzet']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
// ============================================================
// UNIVERSAL MODAL MANAGER
// ============================================================
(function(){
  function initModal(modalId, openBtnId, closeCls) {
    const modal = document.getElementById(modalId);
    const openBtn = document.getElementById(openBtnId);
    if (!modal) return;

    function openModal() {
      modal.style.display = 'flex';
      modal.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
    }
    function closeModal() {
      modal.style.display = 'none';
      modal.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
    }

    if (openBtn) openBtn.addEventListener('click', openModal);
    modal.querySelectorAll('.' + closeCls).forEach(btn => btn.addEventListener('click', closeModal));
    modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeModal(); });
  }

  // Modal Tambah Katalog
  initModal('tambahKatalogModal', 'tambahKatalogBtn', 'modal-close-tambah');

  // Modal Edit Katalog
  const editModal = document.getElementById('editKatalogModal');
  if (editModal) {
    const formFields = {
      id:       document.getElementById('modal_id_katalog'),
      layanan:  document.getElementById('modal_id_layanan'),

      varian:   document.getElementById('modal_varian'),
      harga:    document.getElementById('modal_harga'),
      satuan:   document.getElementById('modal_satuan'),
      status:   document.getElementById('modal_status'),
      deskripsi:document.getElementById('modal_deskripsi')
    };

    function openEditModal(data) {
      formFields.id.value       = data.id;
      formFields.layanan.value  = data.idLayanan;
      formFields.jenis.value    = data.jenisLayanan;
      formFields.varian.value   = data.varian;
      formFields.harga.value    = data.harga;
      formFields.satuan.value   = data.satuan;
      formFields.status.value   = data.status;
      formFields.deskripsi.value= data.deskripsi;
      editModal.style.display   = 'flex';
      editModal.setAttribute('aria-hidden','false');
      document.body.style.overflow = 'hidden';
    }
    function closeEditModal() {
      editModal.style.display = 'none';
      editModal.setAttribute('aria-hidden','true');
      document.body.style.overflow = '';
    }

    document.querySelectorAll('.edit-katalog-btn').forEach(btn => {
      btn.addEventListener('click', function(){
        openEditModal({
          id:           this.dataset.id,
          idLayanan:    this.dataset.idLayanan,
          jenisLayanan: this.dataset.jenisLayanan,
          varian:       this.dataset.varian,
          harga:        this.dataset.harga,
          satuan:       this.dataset.satuan,
          status:       this.dataset.status,
          deskripsi:    this.dataset.deskripsi
        });
      });
    });
    editModal.querySelectorAll('.modal-close-edit').forEach(btn => btn.addEventListener('click', closeEditModal));
    editModal.addEventListener('click', function(e){ if(e.target === editModal) closeEditModal(); });
    document.addEventListener('keydown', function(e){ if(e.key === 'Escape') closeEditModal(); });
  }

  // Modal Tambah Layanan
  initModal('tambahLayananModal', 'tambahLayananBtn', 'modal-close-layanan');

  // Modal Edit Layanan
  const editLayananModal = document.getElementById('editLayananModal');
  if (editLayananModal) {
    const formFields = {
      id:        document.getElementById('modal_id_layanan_edit'),
      nama:      document.getElementById('modal_nama_layanan'),
      deskripsi: document.getElementById('modal_deskripsi_layanan'),
      status:    document.getElementById('modal_status_layanan')
    };

    function openEditLayananModal(data) {
      formFields.id.value        = data.id;
      formFields.nama.value      = data.nama;
      formFields.deskripsi.value = data.deskripsi;
      formFields.status.value    = data.status;
      editLayananModal.style.display   = 'flex';
      editLayananModal.setAttribute('aria-hidden','false');
      document.body.style.overflow = 'hidden';
    }
    function closeEditLayananModal() {
      editLayananModal.style.display = 'none';
      editLayananModal.setAttribute('aria-hidden','true');
      document.body.style.overflow = '';
    }

    document.querySelectorAll('.edit-layanan-btn').forEach(btn => {
      btn.addEventListener('click', function(){
        openEditLayananModal({
          id:        this.dataset.id,
          nama:      this.dataset.nama,
          deskripsi: this.dataset.deskripsi,
          status:    this.dataset.status
        });
      });
    });
    editLayananModal.querySelectorAll('.modal-close-layanan-edit').forEach(btn => btn.addEventListener('click', closeEditLayananModal));
    editLayananModal.addEventListener('click', function(e){ if(e.target === editLayananModal) closeEditLayananModal(); });
    document.addEventListener('keydown', function(e){ if(e.key === 'Escape') closeEditLayananModal(); });
  }

  // Modal Tambah Staff
  initModal('tambahStaffModal', 'tambahStaffBtn', 'modal-close-staff');

  // Styling semua modal overlay
  document.querySelectorAll('.modal-overlay').forEach(el => {
    el.style.cssText = 'display:none;position:fixed;inset:0;z-index:1000;align-items:center;justify-content:center;background:rgba(15,23,42,0.5);padding:20px;';
  });
})();
</script>

<script>
(function(){
  const role = "owner";
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

  document.querySelectorAll('.card, .form-card, .receipt, .order-card, .info-box, .pay-box, .alert-pay, .modal-panel, .modal-box, .modal-card').forEach(el => {
    el.classList.add('bg-white','border','border-slate-200','rounded-2xl','shadow-sm');
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
