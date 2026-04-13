<?php
require_once 'app_config.php';
cleango_boot_session();
require_once 'db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['redirect_file'] ?? 'customer.php'));
    exit();
}

$error = '';
$rememberUsername = $_COOKIE['remember_username'] ?? '';
$rememberChecked = !empty($rememberUsername);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $rememberMe = isset($_POST['remember_me']);
    $rememberChecked = $rememberMe;

    if (empty($username) || empty($password)) {
        $error = "Username dan password harus diisi!";
    } else {
        $found = false;

        $stmt = $pdo->prepare("SELECT * FROM owner WHERE username = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $u = $stmt->fetch();
        if ($u && $password === $u['sandi_owner']) {
            $_SESSION['user_id'] = $u['id_owner'];
            $_SESSION['user'] = $u['username'];
            $_SESSION['nama'] = $u['nama_owner'];
            $_SESSION['role'] = 'owner';
            $_SESSION['redirect_file'] = 'owner.php';
            $pdo->prepare("INSERT INTO login_logs (role,actor_id,ip_address) VALUES (?,?,?)")->execute(['owner', $u['id_owner'], $_SERVER['REMOTE_ADDR']]);
            if ($rememberMe) cleango_set_cookie('remember_username', $username);
            else cleango_delete_cookie('remember_username');
            header("Location: owner.php"); exit();
        }

        $stmt = $pdo->prepare("SELECT * FROM staff WHERE username = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $u = $stmt->fetch();
        if ($u && $password === $u['sandi']) {
            $_SESSION['user_id'] = $u['id_staff'];
            $_SESSION['user'] = $u['username'];
            $_SESSION['nama'] = $u['nama'];
            $_SESSION['role'] = 'staff';
            $_SESSION['redirect_file'] = 'staff.php';
            $pdo->prepare("INSERT INTO login_logs (role,actor_id,ip_address) VALUES (?,?,?)")->execute(['staff', $u['id_staff'], $_SERVER['REMOTE_ADDR']]);
            if ($rememberMe) cleango_set_cookie('remember_username', $username);
            else cleango_delete_cookie('remember_username');
            header("Location: staff.php"); exit();
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $u = $stmt->fetch();
        if ($u && $password === $u['sandi_cust']) {
            $_SESSION['user_id'] = $u['id_cust'];
            $_SESSION['user'] = $u['username'];
            $_SESSION['nama'] = $u['nama_cust'];
            $_SESSION['role'] = 'customer';
            $_SESSION['redirect_file'] = 'customer.php';
            $pdo->prepare("INSERT INTO login_logs (role,actor_id,ip_address) VALUES (?,?,?)")->execute(['customer', $u['id_cust'], $_SERVER['REMOTE_ADDR']]);
            if ($rememberMe) cleango_set_cookie('remember_username', $username);
            else cleango_delete_cookie('remember_username');
            header("Location: customer.php"); exit();
        }

        if (!$found) $error = "Username atau password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - CleanGo Laundry</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-900 via-blue-700 to-blue-400 p-5">

<div class="flex bg-white rounded-3xl overflow-hidden shadow-2xl w-full max-w-3xl min-h-[520px]">

  <!-- Brand Panel -->
  <div class="hidden md:flex flex-col justify-center bg-gradient-to-b from-blue-900 to-blue-500 flex-1 p-12 text-white">
    <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center text-3xl mb-7">
      <i class="fas fa-soap"></i>
    </div>
    <h1 class="text-3xl font-extrabold mb-3 tracking-tight">CleanGo</h1>
    <p class="text-sm opacity-85 leading-relaxed mb-7">Sistem manajemen laundry terintegrasi.<br>Dari pemesanan hingga invoice — semua dalam satu platform.</p>
    <ul class="space-y-3">
      <li class="flex items-center gap-3 text-sm opacity-90"><span class="w-7 h-7 bg-white/20 rounded-lg flex items-center justify-center text-xs flex-shrink-0"><i class="fas fa-database"></i></span> Database MySQL real-time</li>
      <li class="flex items-center gap-3 text-sm opacity-90"><span class="w-7 h-7 bg-white/20 rounded-lg flex items-center justify-center text-xs flex-shrink-0"><i class="fas fa-users"></i></span> 3 Role: Owner, Staff, Customer</li>
      <li class="flex items-center gap-3 text-sm opacity-90"><span class="w-7 h-7 bg-white/20 rounded-lg flex items-center justify-center text-xs flex-shrink-0"><i class="fas fa-sync-alt"></i></span> Data tersinkron antar panel</li>
      <li class="flex items-center gap-3 text-sm opacity-90"><span class="w-7 h-7 bg-white/20 rounded-lg flex items-center justify-center text-xs flex-shrink-0"><i class="fas fa-file-invoice"></i></span> Tracking order & invoice otomatis</li>
    </ul>
  </div>

  <!-- Login Panel -->
  <div class="flex-1 flex flex-col justify-center p-8 md:p-10">
    <h2 class="text-2xl font-bold text-slate-800 mb-1">Selamat Datang 👋</h2>
    <p class="text-slate-500 text-sm mb-6">Masuk dengan akun terdaftar di database CleanGo.</p>

    <?php if ($error): ?>
    <div class="flex items-center gap-2 bg-red-50 border border-red-200 text-red-600 text-sm rounded-xl px-4 py-3 mb-4">
      <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
      <div>
        <label class="block text-xs font-semibold text-slate-500 mb-1.5">Username</label>
        <div class="relative">
          <i class="fas fa-user absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none"></i>
          <input type="text" name="username" placeholder="Masukkan username" autocomplete="username"
            value="<?= htmlspecialchars($_POST['username'] ?? $rememberUsername) ?>"
            class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-100 transition">
        </div>
      </div>

      <div>
        <label class="block text-xs font-semibold text-slate-500 mb-1.5">Password</label>
        <div class="relative">
          <i class="fas fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none"></i>
          <input type="password" name="password" id="pwd" placeholder="Masukkan password"
            class="w-full pl-10 pr-10 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-100 transition">
          <button type="button" onclick="togglePwd()" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-blue-500 transition">
            <i class="fas fa-eye" id="eyeIco"></i>
          </button>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <input type="checkbox" name="remember_me" id="remember_me" <?= $rememberChecked ? 'checked' : '' ?>
          class="w-4 h-4 accent-blue-600 cursor-pointer">
        <label for="remember_me" class="text-xs text-slate-500 cursor-pointer">
          Ingat saya<span class="text-slate-400">(Cookie)</span>
        </label>
      </div>

      <button type="submit"
        class="w-full py-3 bg-gradient-to-r from-blue-500 to-blue-900 text-white font-semibold rounded-xl text-sm hover:-translate-y-0.5 hover:shadow-lg hover:shadow-blue-300 transition-all duration-200">
        <i class="fas fa-sign-in-alt mr-2"></i>Masuk ke Dashboard
      </button>
    </form>

    <div class="mt-4 bg-blue-50 border border-blue-200 rounded-xl p-3.5">
      <p class="text-xs font-bold text-blue-800 mb-2"><i class="fas fa-info-circle mr-1"></i> Akun Demo dari Database:</p>
      <table class="w-full text-xs">
        <tr><td class="font-bold text-slate-800 py-0.5 w-20">owner</td><td class="text-slate-600 py-0.5">owner123</td><td class="py-0.5"><span class="inline-block px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-800 font-semibold text-[10px]">Owner</span></td></tr>
        <tr><td class="font-bold text-slate-800 py-0.5">staff</td><td class="text-slate-600 py-0.5">staff123</td><td class="py-0.5"><span class="inline-block px-2 py-0.5 rounded-full bg-green-100 text-green-800 font-semibold text-[10px]">Staff</span></td></tr>
        <tr><td class="font-bold text-slate-800 py-0.5">dhira</td><td class="text-slate-600 py-0.5">dhira123</td><td class="py-0.5"><span class="inline-block px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 font-semibold text-[10px]">Customer</span></td></tr>
      </table>
    </div>
  </div>
</div>

<script>
function togglePwd(){
  var i=document.getElementById('pwd'),e=document.getElementById('eyeIco');
  i.type=i.type==='password'?'text':'password';
  e.className=i.type==='password'?'fas fa-eye':'fas fa-eye-slash';
}
</script>
</body>
</html>
