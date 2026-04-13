<?php
require_once 'app_config.php';
cleango_boot_session();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    cleango_delete_cookie('remember_username');
    session_destroy();
    header("Location: login.php"); exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<title>Logout - CleanGo</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','Segoe UI','sans-serif']}}}}</script>
</head>
<body class="min-h-screen bg-gradient-to-br from-sky-950 via-blue-800 to-sky-500 flex items-center justify-center p-5">
<div class="w-full max-w-md rounded-3xl bg-white p-10 text-center shadow-2xl">
  <h2 class="text-2xl font-bold">Yakin ingin keluar?</h2>
  <p class="mt-3 text-slate-600">Kamu akan keluar dari sesi CleanGo dan kembali ke halaman login.</p>
  <div class="mt-6 flex flex-col gap-3 items-center justify-center">
    <form method="POST" class="w-full">
      <button type="submit" class="w-full inline-flex items-center justify-center rounded-xl bg-rose-600 px-5 py-3 text-sm font-semibold text-white hover:bg-rose-700">Ya, Logout</button>
    </form>
    <a href="javascript:history.back()" class="w-full inline-flex items-center justify-center rounded-xl bg-slate-100 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-200">Batal</a>
  </div>
</div>
<script>
document.querySelectorAll('label').forEach(el=>el.classList.add('mb-1.5','block','text-sm','font-medium','text-slate-700'));
document.querySelectorAll('input').forEach(el=>el.classList.add('w-full','rounded-xl','border','border-slate-300','bg-white','px-4','py-3','text-sm','shadow-sm','focus:outline-none','focus:ring-2','focus:ring-sky-300'));
document.querySelectorAll('.tp').forEach(el=>el.classList.add('absolute','right-3','top-1/2','-translate-y-1/2','text-slate-400'));
</script></body>
</html>
