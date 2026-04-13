<?php
require_once 'app_config.php';
cleango_boot_session();
require_once 'db.php';

// Sudah login → redirect
if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['redirect_file'] ?? 'customer.php'));
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama     = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $notelp   = trim($_POST['notelp'] ?? '');
    $alamat   = trim($_POST['alamat'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm_password'] ?? '');

    // Validasi
    if (empty($nama) || empty($username) || empty($notelp) || empty($password) || empty($confirm)) {
        $error = "Semua field harus diisi!";
    } elseif (!preg_match('/^[0-9]+$/', $notelp)) {
        $error = "Nomor telepon hanya boleh diisi angka!";
        $notelp = '';
    } elseif ($password !== $confirm) {
        $error = "Password dan konfirmasi password tidak cocok!";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter!";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = "Username hanya boleh huruf, angka, dan underscore!";
    } else {
        // Cek username unik
        $stmt = $pdo->prepare("SELECT id_cust FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = "Username sudah digunakan!";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO users (nama_cust, username, notelp_cust, sandi_cust, alamat_cust, redirect_file, is_active)
                    VALUES (?, ?, ?, ?, ?, 'customer.php', 1)
                ");
                $stmt->execute([$nama, $username, $notelp, $password, $alamat]);
                header("Location: login.php");
                exit();
            } catch (PDOException $e) {
                $error = "Terjadi kesalahan: " . $e->getMessage();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $nama = $username = $notelp = $alamat = '';
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
<title>Registrasi - CleanGo Laundry</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script>
  tailwind.config = {
    theme: {
      extend: {
        boxShadow: {
          'card': '0 25px 50px rgba(0,0,0,0.2)',
          'btn':  '0 8px 22px rgba(30,144,255,0.38)',
        }
      }
    }
  }
</script>
<style>
  .gradient-bg    { background: linear-gradient(135deg, #0f4c75, #0059b8, #1e90ff); }
  .gradient-brand { background: linear-gradient(160deg, #003d8f, #1e90ff); }
  .gradient-btn   { background: linear-gradient(135deg, #1e90ff, #003d8f); }
  .input-focus:focus { border-color: #1e90ff; box-shadow: 0 0 0 3px rgba(30,144,255,0.1); }
</style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center p-5 font-sans">

<div class="flex bg-white rounded-3xl overflow-hidden shadow-card w-full max-w-3xl">

  <!-- Brand Panel -->
  <div class="gradient-brand flex-1 p-12 flex-col justify-center text-white hidden md:flex">
    <div class="w-[72px] h-[72px] bg-white/15 rounded-2xl flex items-center justify-center text-4xl mb-7">
      <i class="fas fa-soap"></i>
    </div>
    <h1 class="text-3xl font-extrabold mb-2 tracking-tight">CleanGo</h1>
    <p class="text-sm opacity-85 leading-7 mb-7">
      Sistem manajemen laundry terintegrasi.<br>
      Dari pemesanan hingga invoice — semua dalam satu platform.
    </p>
    <ul class="flex flex-col gap-3">
      <li class="flex items-center gap-3 text-sm opacity-95">
        <span class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center text-xs flex-shrink-0">
          <i class="fas fa-database"></i>
        </span>
        Database MySQL real-time
      </li>
      <li class="flex items-center gap-3 text-sm opacity-95">
        <span class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center text-xs flex-shrink-0">
          <i class="fas fa-users"></i>
        </span>
        3 Role: Owner, Staff, Customer
      </li>
      <li class="flex items-center gap-3 text-sm opacity-95">
        <span class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center text-xs flex-shrink-0">
          <i class="fas fa-sync-alt"></i>
        </span>
        Data tersinkron antar panel
      </li>
      <li class="flex items-center gap-3 text-sm opacity-95">
        <span class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center text-xs flex-shrink-0">
          <i class="fas fa-file-invoice"></i>
        </span>
        Tracking order &amp; invoice otomatis
      </li>
    </ul>
  </div>

  <!-- Form Panel -->
  <div class="flex-1 px-10 py-10 flex flex-col justify-center">
    <h2 class="text-2xl font-bold text-slate-800 mb-1">Daftar Akun Baru 👤</h2>
    <p class="text-slate-500 text-sm mb-6">Buat akun customer untuk mulai menggunakan layanan CleanGo.</p>

    <?php if ($error): ?>
    <div class="flex items-center gap-2 bg-red-50 border border-red-200 text-red-600 text-sm px-4 py-3 rounded-xl mb-5">
      <i class="fas fa-exclamation-circle"></i>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">

      <!-- Nama Lengkap -->
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Nama Lengkap</label>
        <div class="relative">
          <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none">
            <i class="fas fa-user"></i>
          </span>
          <input
            type="text"
            name="nama"
            placeholder="Masukkan nama lengkap"
            required
            value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>"
            class="w-full pl-10 pr-4 py-3 border border-slate-200 rounded-xl text-sm text-slate-800 bg-slate-50 outline-none transition-all duration-200 input-focus"
          >
        </div>
      </div>

      <!-- Username -->
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Username</label>
        <div class="relative">
          <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none">
            <i class="fas fa-at"></i>
          </span>
          <input
            type="text"
            name="username"
            placeholder="Masukkan username"
            required
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
            class="w-full pl-10 pr-4 py-3 border border-slate-200 rounded-xl text-sm text-slate-800 bg-slate-50 outline-none transition-all duration-200 input-focus"
          >
        </div>
      </div>

      <!-- Nomor Telepon -->
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Nomor Telepon</label>
        <div class="relative">
          <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none">
            <i class="fas fa-phone"></i>
          </span>
          <input
            type="tel"
            name="notelp"
            placeholder="Masukkan nomor telepon"
            inputmode="numeric"
            pattern="[0-9]+"
            title="Nomor telepon hanya boleh berisi angka"
            required
            value="<?= htmlspecialchars($_POST['notelp'] ?? '') ?>"
            class="w-full pl-10 pr-4 py-3 border border-slate-200 rounded-xl text-sm text-slate-800 bg-slate-50 outline-none transition-all duration-200 input-focus"
          >
        </div>
      </div>

      <!-- Alamat -->
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Alamat</label>
        <div class="relative">
          <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none">
            <i class="fas fa-map-marker-alt"></i>
          </span>
          <input
            type="text"
            name="alamat"
            placeholder="Masukkan alamat lengkap"
            required
            value="<?= htmlspecialchars($_POST['alamat'] ?? '') ?>"
            class="w-full pl-10 pr-4 py-3 border border-slate-200 rounded-xl text-sm text-slate-800 bg-slate-50 outline-none transition-all duration-200 input-focus"
          >
        </div>
      </div>

      <!-- Password -->
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Password</label>
        <div class="relative">
          <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none">
            <i class="fas fa-lock"></i>
          </span>
          <input
            type="password"
            name="password"
            id="pwd"
            placeholder="Masukkan password"
            required
            class="w-full pl-10 pr-11 py-3 border border-slate-200 rounded-xl text-sm text-slate-800 bg-slate-50 outline-none transition-all duration-200 input-focus"
          >
          <button type="button" onclick="togglePwd()"
            class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-blue-500 transition-colors text-sm bg-transparent border-0 cursor-pointer p-0">
            <i class="fas fa-eye" id="eyeIco"></i>
          </button>
        </div>
      </div>

      <!-- Konfirmasi Password -->
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Konfirmasi Password</label>
        <div class="relative">
          <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none">
            <i class="fas fa-lock"></i>
          </span>
          <input
            type="password"
            name="confirm_password"
            id="cpwd"
            placeholder="Konfirmasi password"
            required
            class="w-full pl-10 pr-11 py-3 border border-slate-200 rounded-xl text-sm text-slate-800 bg-slate-50 outline-none transition-all duration-200 input-focus"
          >
          <button type="button" onclick="toggleCpwd()"
            class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-blue-500 transition-colors text-sm bg-transparent border-0 cursor-pointer p-0">
            <i class="fas fa-eye" id="ceyeIco"></i>
          </button>
        </div>
      </div>

      <!-- Submit -->
      <button type="submit"
        class="gradient-btn w-full py-3.5 text-white font-semibold text-sm rounded-xl transition-all duration-300 hover:-translate-y-0.5 hover:shadow-btn tracking-wide">
        <i class="fas fa-user-plus mr-2"></i> Daftar Sekarang
      </button>
    </form>

    <p class="text-center text-xs text-slate-500 mt-5">
      Sudah punya akun?
      <a href="login.php" class="text-blue-500 font-semibold hover:underline">Masuk di sini</a>
    </p>
  </div>
</div>

<script>
function togglePwd() {
  var i = document.getElementById('pwd'), e = document.getElementById('eyeIco');
  i.type = i.type === 'password' ? 'text' : 'password';
  e.className = i.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
}
function toggleCpwd() {
  var i = document.getElementById('cpwd'), e = document.getElementById('ceyeIco');
  i.type = i.type === 'password' ? 'text' : 'password';
  e.className = i.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
}
</script>
</body>
</html>
